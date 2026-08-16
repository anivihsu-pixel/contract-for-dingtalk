<?php
// ------------------------------------------------------------
// ApprovalActionService — 审批操作服务
// 从 ApprovalLogic 按职责拆出（v2.38.1 大文件拆分）
// ------------------------------------------------------------

namespace app\common\logic;

use think\facade\Db;
use think\facade\Log;
use app\common\service\DingTalkService;
use app\common\service\InternalNotify;

class ApprovalActionService
{
    // 节点模式常量（与 ApprovalLogic 保持一致；改动须 ApprovalLogic / ApprovalNodeExecutor 三处同步）
    public const MODE_AND = 'AND';
    public const MODE_OR  = 'OR';
    // 抄送节点类型常量（与 ApprovalLogic::NODE_CC 一致）
    public const NODE_CC  = 'CC';

    /**
     * 审批操作 (同意/驳回/转交)
     * @param int $instanceId
     * @param int $approverId
     * @param string $action  APPROVED / REJECTED / TRANSFERRED
     * @param string $comment
     * @param int|null $transferTo  转交目标用户 ID
     * @return bool
     */
    public static function action(int $instanceId, int $approverId, string $action, string $comment = '', ?int $transferTo = null, array $extras = []): bool
    {
        $instance = Db::name('approval_instance')->find($instanceId);
        if (!$instance || $instance['status'] !== 'PENDING') return false;

        $flow = Db::name('approval_flow')->find($instance['flow_id']);
        if (!$flow) return false;

        // 取出当前审批人待处理记录（读操作，置于事务外）
        $record = Db::name('approval_record')
            ->where('instance_id', $instanceId)
            ->where('node_order', $instance['current_node_order'])
            ->where('approver_id', $approverId)
            ->where('action', 'PENDING')
            ->find();
        if (!$record) return false;

        // CR-30：事务包裹 record / instance / contract 三表写操作，
        // 任一环节异常则整体回滚，避免审批记录与合同状态不一致；并记录上下文日志
        $ok = false;
        try {
            $ok = Db::transaction(function () use ($instanceId, $instance, $flow, $record, $approverId, $action, $comment, $transferTo, $extras) {
                // P2-5【转交目标前置校验】：先校验后写库。此前在事务内无条件先把当前记录标 TRANSFERRED
                // 再查目标用户，目标无效时静默 return false 不会触发回滚（TP6 事务闭包仅抛异常才回滚），
                // 会留下「原记录已标转交、目标用户无效、无新 PENDING 记录」的无人可审批卡死实例。
                // 校验置于事务内、写库前，失败抛异常使整个事务回滚，原记录保持 PENDING 不变。
                if ($action === 'TRANSFERRED') {
                    $transferTo = (int)$transferTo;
                    if ($transferTo <= 0 || !Db::name('user')->where('id', $transferTo)->where('status', 1)->find()) {
                        throw new \RuntimeException('转交目标用户无效');
                    }
                }
                // 标记当前审批人的处理动作
                Db::name('approval_record')->where('id', $record['id'])->update([
                    'action'   => $action,
                    'comment'  => $comment,
                    'acted_at' => date('Y-m-d H:i:s'),
                ]);

                // P1-1【严重·M-P1】并发幂等防护：事务内（MySQL 加 FOR UPDATE 行锁，SQLite 靠事务串行化）
                // 重新读取实例并复核 current_node_order 是否仍为进入事务时的节点。
                // 或签(OR)节点两名审批人并发点击时：先提交者已推进节点序并插入下一节点记录，
                // 后提交者读到变更后的节点序即跳过 advanceAfterNode，避免下一节点审批人记录被重复插入、
                // current_node_order 重复前移、审批轨迹冗余。
                $instQuery = Db::name('approval_instance')->where('id', $instanceId);
                if (config('database.default') === 'mysql') {
                    $instQuery->lock(true);
                }
                $lockedInstance = $instQuery->find();
                if (!$lockedInstance || $lockedInstance['status'] !== 'PENDING'
                    || $lockedInstance['current_node_order'] != $instance['current_node_order']) {
                    // 实例已被并发事务推进/结束，本审批人动作记录已更新，幂等返回，不再重复推进
                    return true;
                }

                // F1/F3：业务对象按实例类型获取——合同审批取合同；发票审批取发票（构造轻量行供节点条件/通知文案用）
                $bizType  = (string)($instance['biz_type'] ?? 'contract');
                $contract = null;
                if ($bizType === 'invoice') {
                    $inv = Db::name('contract_invoice')->find($instance['target_id'] ?: $instance['contract_id']);
                    $contract = $inv ? [
                        'amount' => (float)($inv['amount'] ?? 0),
                        'title'  => ($inv['content_desc'] ?? '') !== '' ? '开票：' . $inv['content_desc'] : '发票申请',
                    ] : ['amount' => 0.0, 'title' => '发票申请'];
                } else {
                    $contract = Db::name('contract')->find($instance['contract_id'])
                        ?: ['amount' => 0.0, 'title' => '合同'];
                }

                if ($action === 'REJECTED') {
                    // v2.38.3：支持驳回到指定前序节点（reject_to_order），而非总是回起点
                    $rejectTo = (int)($extras['reject_to_order'] ?? 0);
                    $currentOrder = (int)$instance['current_node_order'];
                    if ($rejectTo > 0 && $rejectTo < $currentOrder) {
                        // 驳回到指定节点：清理该节点及之后所有 record，重建目标节点 PENDING
                        Db::name('approval_record')
                            ->where('instance_id', $instanceId)
                            ->where('node_order', '>=', $rejectTo)
                            ->delete();
                        Db::name('approval_instance')->where('id', $instanceId)->update([
                            'current_node_order' => $rejectTo,
                            'status' => 'PENDING',
                            'finished_at' => null,
                        ]);
                        $nodes = json_decode($flow['nodes'], true) ?: [];
                        // H3 修复：驳回目标节点与 submit 的 node_order（活跃序号）口径统一，先过滤条件剔除的节点再索引
                        $nodes = \app\common\logic\ApprovalSubmitService::filterActiveNodes($nodes, (float)($contract['amount'] ?? 0), $contract);
                        $targetNode = $nodes[$rejectTo - 1] ?? [];
                        $targetApprovers = self::resolveApprovers($targetNode, $instance['submitted_by']);
                        // P1（2026-08-09）：目标节点 ROLE 运行期无成员（如角色无人）时
                        // 重建插入 0 条 PENDING → 实例永久卡死；立即检查并整体驳回到起点（结束审批，业务对象回退）
                        if (empty($targetApprovers)) {
                            Db::name('approval_record')->where('instance_id', $instanceId)->delete();
                            Db::name('approval_instance')->where('id', $instanceId)->update([
                                'status'      => 'REJECTED',
                                'finished_at' => date('Y-m-d H:i:s'),
                            ]);
                            if ($bizType === 'invoice') {
                                \app\common\logic\InvoiceLogic::transitionStatus((int)($instance['target_id'] ?: $instance['contract_id']), 'REJECTED', $approverId);
                            } else {
                                ContractLogic::transitionStatus($instance['contract_id'], 'REJECTED', $approverId);
                            }
                            $link = DingTalkService::approvalEntryUrl($instanceId);
                            \app\common\logic\ApprovalNotifyService::queueNotify([$instance['submitted_by']],
                                '审批被驳回',
                                "{$contract['title']} 已被驳回（驳回目标节点无可用审批人）" . ($comment !== '' ? "\n\n驳回意见：{$comment}" : ''), $link, InternalNotify::TYPE_APPROVAL_REJECTED);
                        } else {
                            foreach ($targetApprovers as $tid) {
                                Db::name('approval_record')->insert([
                                    'instance_id' => $instanceId,
                                    'node_order'  => $rejectTo,
                                    'node_name'   => $targetNode['name'] ?? '节点' . $rejectTo,
                                    'approver_id' => $tid,
                                    'action'      => 'PENDING',
                                    'created_at'  => date('Y-m-d H:i:s'),
                                ]);
                            }
                            $link = DingTalkService::approvalEntryUrl($instanceId);
                            \app\common\logic\ApprovalNotifyService::queueNotify($targetApprovers,
                                '审批驳回（回退至节点）',
                                "{$contract['title']} 被驳回至「{$targetNode['name']}」" . ($comment !== '' ? "\n驳回意见：{$comment}" : ''), $link, InternalNotify::TYPE_APPROVAL_REJECTED);
                        }
                    } else {
                        // 驳回回起点：结束审批，业务对象回退
                        Db::name('approval_instance')->where('id', $instanceId)->update([
                            'status'      => 'REJECTED',
                            'finished_at' => date('Y-m-d H:i:s'),
                        ]);
                        // F1/F3：发票审批驳回→发票 REJECTED；合同审批驳回→合同 REJECTED
                        if ($bizType === 'invoice') {
                            $ok = \app\common\logic\InvoiceLogic::transitionStatus((int)($instance['target_id'] ?: $instance['contract_id']), 'REJECTED', $approverId);
                        } else {
                            $ok = ContractLogic::transitionStatus($instance['contract_id'], 'REJECTED', $approverId);
                        }
                        // P2-5：状态机不允许当前跃迁（transitionStatus 返回 false）则抛异常整体回滚，
                        // 杜绝「实例已驳回、业务对象状态未回退」的脏数据（事务闭包 return false 不会回滚）
                        if (!$ok) {
                            throw new \RuntimeException('驳回后业务状态回退失败（状态机不允许当前跃迁）');
                        }
                        $link = DingTalkService::approvalEntryUrl($instanceId);
                        \app\common\logic\ApprovalNotifyService::queueNotify([$instance['submitted_by']],
                            '审批被驳回',
                            "{$contract['title']} 已被驳回" . ($comment !== '' ? "\n\n驳回意见：{$comment}" : ''), $link, InternalNotify::TYPE_APPROVAL_REJECTED);
                    }

                } elseif ($action === 'APPROVED') {
                    $nodes = json_decode($flow['nodes'], true) ?: [];
                    // H3 修复：取当前节点判定 AND/OR 模式前，先过滤条件剔除的节点，与 submit 的 node_order 口径一致
                    $nodes = \app\common\logic\ApprovalSubmitService::filterActiveNodes($nodes, (float)($contract['amount'] ?? 0), $contract);
                    $currentOrder = $instance['current_node_order'];
                    $node = $nodes[$currentOrder - 1] ?? [];
                    $nodeMode = strtoupper((string)($node['mode'] ?? self::MODE_OR));

                    // 会签(AND)：本节点全部审批人通过才推进；或签(OR)：任一通过即推进
                    if ($nodeMode === self::MODE_AND) {
                        $pending = Db::name('approval_record')
                            ->where('instance_id', $instanceId)
                            ->where('node_order', $currentOrder)
                            ->where('action', 'PENDING')
                            ->count();
                        if ($pending > 0) {
                            // 会签未完成，等待其余审批人；实例保持当前节点
                            return true;
                        }
                    }

                    // 节点审批完成，推进流程（复用 advanceAfterNode，供超时自动通过复用）
                    self::advanceAfterNode($instanceId, $instance, $flow, $nodes, $contract, $currentOrder, $approverId);

                } elseif ($action === 'TRANSFERRED') {
                    // 转交目标有效性已在事务开头前置校验（P2-5），无效目标抛异常回滚，不会走到这里
                    // 转交：更新记录为已转交，创建新记录
                    Db::name('approval_record')->where('id', $record['id'])->update([
                        'action'   => 'TRANSFERRED',
                        'comment'  => $comment,
                        'acted_at' => date('Y-m-d H:i:s'),
                    ]);
                    Db::name('approval_record')->insert([
                        'instance_id' => $instanceId,
                        'node_order'  => $instance['current_node_order'],
                        'node_name'   => $record['node_name'],
                        'approver_id' => $transferTo,
                        'action'      => 'PENDING',
                        'created_at'  => date('Y-m-d H:i:s'),
                    ]);
                    $link = DingTalkService::approvalEntryUrl($instanceId);
                    \app\common\logic\ApprovalNotifyService::queueNotify([$transferTo],
                        '审批转交',
                        "合同 {$contract['title']} 审批已转交给你，请尽快处理。", $link, InternalNotify::TYPE_APPROVAL_TRANSFERRED);
                }

                return true;
            });
        } catch (\RuntimeException $e) {
            // 业务校验异常（转交目标无效/状态机不允许跃迁等）语义明确，向调用方（Controller）上抛，
            // 由 Controller 回显具体原因；事务闭包已回滚，原记录保持 PENDING 不变
            throw $e;
        } catch (\Throwable $e) {
            Log::error('审批操作失败，已回滚', [
                'instance_id' => $instanceId,
                'approverId'  => $approverId,
                'action'      => $action,
                'error'       => $e->getMessage(),
                'trace'       => $e->getTraceAsString(),
            ]);
            $ok = false;
        }
        // P0-5【严重·并发/可靠性】修复：仅当事务成功提交后才发送钉钉通知（网络 I/O 不持锁）；失败则清空队列避免泄漏
        if ($ok) {
            \app\common\logic\ApprovalNotifyService::flushNotify();
            $finished = Db::name('approval_instance')->where('id', $instanceId)->find();
            if (($finished['status'] ?? '') === 'APPROVED'
                && ($finished['biz_type'] ?? 'contract') === 'contract') {
                $executingContract = Db::name('contract')->where('id', (int)$finished['contract_id'])->find();
                if (($executingContract['status'] ?? '') === ContractLogic::STATUS_EXECUTING) {
                    \app\common\service\ContractExecutionNotifyService::dispatch($executingContract, $approverId);
                }
            }
            // 审批人已在详情页完成处理：自动读掉进入该审批的站内消息，保持移动工作台各角标一致。
            InternalNotify::markApprovalRead($approverId, $instanceId);
            // P2-10（arch）：审批动作完成后主动失效操作人的待办角标短缓存，避免 60s 滞后
            \think\facade\Cache::delete('badge_approval_' . $approverId);
        } else {
            \app\common\logic\ApprovalNotifyService::$notifyQueue = [];
        }
        return $ok;
    }

    /**
     * 撤回审批 (仅提交人可操作)
     * P1-5（M34）：实例置 RECALLED 与合同状态回退至草稿(DRAFT) 两步写包裹在同一事务内，
     * 任一写失败整体回滚，避免“实例已撤回但合同仍在审批中”的孤儿数据。

    /**
     * 撤回审批 (仅提交人可操作)
     * P1-5（M34）：实例置 RECALLED 与合同状态回退至草稿(DRAFT) 两步写包裹在同一事务内，
     * 任一写失败整体回滚，避免“实例已撤回但合同仍在审批中”的孤儿数据。
     */
    public static function recall(int $instanceId, int $userId): bool
    {
        $instance = Db::name('approval_instance')->find($instanceId);
        if (!$instance || $instance['status'] !== 'PENDING') return false;
        if ($instance['submitted_by'] != $userId) return false;

        Db::startTrans();
        try {
            // 步骤1：审批实例置为已撤回
            Db::name('approval_instance')->where('id', $instanceId)->update([
                'status'      => 'RECALLED',
                'finished_at' => date('Y-m-d H:i:s'),
            ]);
            // 步骤2：业务对象回退——合同回退草稿；发票撤回置 CANCELLED（F1/F3）
            $bizType = (string)($instance['biz_type'] ?? 'contract');
            if ($bizType === 'invoice') {
                $ok = \app\common\logic\InvoiceLogic::transitionStatus((int)($instance['target_id'] ?: $instance['contract_id']), 'CANCELLED', $userId);
            } else {
                $ok = ContractLogic::transitionStatus($instance['contract_id'], 'DRAFT', $userId);
            }
            if (!$ok) {
                // transitionStatus 返回 false 多为状态机不允许当前跃迁；主动抛异常以触发整体回滚，杜绝孤儿数据
                throw new \RuntimeException('撤回后业务状态回退失败（状态机不允许当前跃迁）');
            }
            Db::commit();
            return true;
        } catch (\Throwable $e) {
            Db::rollback();
            Log::error('审批撤回失败，已回滚', [
                'instance_id' => $instanceId,
                'user_id'     => $userId,
                'error'       => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * 节点审批完成后推进流程（会签/或签通用）
     * 自动流转连续抄送(CC)节点，并推进到下一审批节点或整体通过。
     * 抽取自 action()，供「会签超时自动通过」复用，避免逻辑重复。

    /**
     * 节点审批完成后推进流程（会签/或签通用）
     * 自动流转连续抄送(CC)节点，并推进到下一审批节点或整体通过。
     * 抽取自 action()，供「会签超时自动通过」复用，避免逻辑重复。
     */
    private static function advanceAfterNode(int $instanceId, array $instance, array $flow, array $nodes, array $contract, int $completedOrder, int $actorId): void
    {
        // v2.38.3：按合同金额 + 属性条件过滤不活跃节点（跳过 amount_min/amount_max / activate_when 不满足的节点）
        $nodes = \app\common\logic\ApprovalSubmitService::filterActiveNodes($nodes, (float)($contract['amount'] ?? 0), $contract);
        $nextOrder = $completedOrder + 1;
        // v2.38.0：抄送为流程级一次性知会（提交时已触发），不再随节点推进逐节点处理

        if ($nextOrder <= count($nodes)) {
            // 推进到下一审批节点
            $nextNode = $nodes[$nextOrder - 1];
            $approvers = self::resolveApprovers($nextNode, $instance['submitted_by']);
            Db::name('approval_instance')->where('id', $instanceId)->update([
                'current_node_order' => $nextOrder,
            ]);
            foreach ($approvers as $uid) {
                Db::name('approval_record')->insert([
                    'instance_id' => $instanceId,
                    'node_order'  => $nextOrder,
                    'node_name'   => $nextNode['name'] ?? '节点' . $nextOrder,
                    'approver_id' => $uid,
                    'action'      => 'PENDING',
                    'created_at'  => date('Y-m-d H:i:s'),
                ]);
            }
            // 通知下一节点审批人（P0-5：入队，事务提交后发送）
            $link = DingTalkService::approvalEntryUrl($instanceId);
            \app\common\logic\ApprovalNotifyService::queueNotify($approvers,
                '新的审批节点',
                "{$contract['title']} 已进入 {$nextNode['name']}，请尽快审批。", $link, InternalNotify::TYPE_APPROVAL_APPROVED);
        } else {
            // 所有审批节点通过
            Db::name('approval_instance')->where('id', $instanceId)->update([
                'status'      => 'APPROVED',
                'finished_at' => date('Y-m-d H:i:s'),
            ]);
            // 合同审批通过后直接进入执行；发票走独立状态机。
            // F1/F3：发票审批通过→发票 APPROVED（待开票，由财务开票时再置 ISSUED），不进入合同状态机。
            $bizType = (string)($instance['biz_type'] ?? 'contract');
            if ($bizType === 'invoice') {
                $ok = \app\common\logic\InvoiceLogic::transitionStatus((int)($instance['target_id'] ?: $instance['contract_id']), 'APPROVED', $actorId);
            } else {
                $ok = ContractLogic::transitionStatus($instance['contract_id'], ContractLogic::STATUS_EXECUTING, $actorId);
            }
            // P2-5：审批通过后业务状态推进失败（transitionStatus 返回 false，多为状态机不允许当前跃迁）
            // 则抛异常整体回滚，杜绝「实例已通过、业务对象仍停留旧状态」的脏数据
            if (!$ok) {
                throw new \RuntimeException('审批通过后业务状态推进失败（状态机不允许当前跃迁）');
            }

            // 通知提交人审批通过
            $link = DingTalkService::approvalEntryUrl($instanceId);
            \app\common\logic\ApprovalNotifyService::queueNotify([$instance['submitted_by']],
                '审批已通过',
                $bizType === 'invoice'
                    ? "发票申请「{$contract['title']}」审批已通过，请财务开票。"
                    : "合同 {$contract['title']} 审批已全部通过，现已进入执行。", $link, InternalNotify::TYPE_APPROVAL_APPROVED);
        }
    }

    /**
     * 处理审批节点超时（CR-03 / REV-01）：
     *  - 或签(OR)节点：超过阈值仍有审批人未处理时，自动标记为「超时自动通过」并推进流程（符合或签语义）。
     *  - 会签(AND)节点：超时不自动放行（REV-01 合规红线），改为向待审批人发送钉钉催办通知，
     *    节点保持待审不推进，等待人工处理，避免部分审批人未实质审核即放行合同。
     * 节点起始时间取该节点最早一条待处理审批记录的创建时间。
     *
     * 触发方式：
     *  - 定时任务（推荐）：php think approval:escalate
     *  - 提交审批时机会式触发（见 submit）
     *
     * @return int 已处理的实例数量

    /**
     * 处理审批节点超时（CR-03 / REV-01）：
     *  - 或签(OR)节点：超过阈值仍有审批人未处理时，自动标记为「超时自动通过」并推进流程（符合或签语义）。
     *  - 会签(AND)节点：超时不自动放行（REV-01 合规红线），改为向待审批人发送钉钉催办通知，
     *    节点保持待审不推进，等待人工处理，避免部分审批人未实质审核即放行合同。
     * 节点起始时间取该节点最早一条待处理审批记录的创建时间。
     *
     * 触发方式：
     *  - 定时任务（推荐）：php think approval:escalate
     *  - 提交审批时机会式触发（见 submit）
     *
     * @return int 已处理的实例数量
     */
    public static function processOverdueApprovals(): int
    {
        // P1-5（deep review）：SLA 超时单一来源 — 节点级 timeout_hours 优先；
        // 全局 andTimeoutHours()（approval_and_timeout_hours，默认 72h）仅作为「节点未配置超时」的降级兜底，
        // 与 approval:sla-check 口径完全一致，消除双命令阈值不一致导致的重复/漏处理。
        $globalHours = self::andTimeoutHours();
        $handled = 0;

        // P0-4【严重·性能雪崩】修复：避免全表一次性 select()->toArray() 载入内存，
        // 改用「id 游标 + LIMIT」分批拉取；并预载审批流消除每个实例的 N+1 查询。
        $flowMap = Db::name('approval_flow')->column('*', 'id');
        $lastId = 0;
        $batchSize = 200;
        $instances = [];
        do {
            $batch = Db::name('approval_instance')
                ->where('status', 'PENDING')
                ->where('id', '>', $lastId)
                ->order('id', 'asc')
                ->limit($batchSize)
                ->select()->toArray();
            foreach ($batch as $inst) {
                $instances[] = $inst;
                $lastId = $inst['id'];
            }
        } while (!empty($batch));
        unset($batch);

        foreach ($instances as $instance) {
            $flow = $flowMap[$instance['flow_id']] ?? null;
            if (!$flow) continue;
            $nodes = json_decode($flow['nodes'], true) ?: [];
            $contract = Db::name('contract')->find($instance['contract_id']) ?: [];
            // H3 修复：SLA 判定按活跃节点过滤后取节点（与 submit 的 node_order 活跃序号口径一致），
            // 避免金额条件/条件路由剔除节点后超时判定落在错误节点上（可能错误放行）
            $nodes = \app\common\logic\ApprovalSubmitService::filterActiveNodes($nodes, (float)($contract['amount'] ?? 0), $contract);
            $order = (int)$instance['current_node_order'];
            $node = $nodes[$order - 1] ?? [];
            // v2.38.0：nodes 仅含审批节点，不再有抄送(CC)节点

            // 该节点最早的待处理记录时间，作为节点起始时间
            $earliest = Db::name('approval_record')
                ->where('instance_id', $instance['id'])
                ->where('node_order', $order)
                ->where('action', 'PENDING')
                ->order('created_at', 'asc')
                ->value('created_at');
            if (!$earliest) {
                continue; // 无待处理记录
            }
            // P1-5：节点级超时阈值（未配置时降级全局默认）；以节点阈值判定是否达到该节点超时
            $nodeHours = (float)($node['timeout_hours'] ?? 0);
            $hours     = $nodeHours > 0 ? $nodeHours : $globalHours;
            $threshold = date('Y-m-d H:i:s', strtotime("-{$hours} hours"));
            if ($earliest > $threshold) {
                continue; // 未达到该节点超时阈值
            }

            $pendingRecords = Db::name('approval_record')
                ->where('instance_id', $instance['id'])
                ->where('node_order', $order)
                ->where('action', 'PENDING')
                ->select()->toArray();
            if (empty($pendingRecords)) continue;

            $nodeMode = strtoupper((string)($node['mode'] ?? self::MODE_OR));

            Db::startTrans();
            try {
                // N-M1【并发正确性】超时自动推进前，事务内重读实例并加锁复核：
                // MySQL 加 FOR UPDATE 行锁；SQLite 靠事务串行化。若真人审批已并发推进/结束该实例
                // （status 非 PENDING，或 current_node_order 已前移），则本次超时处理跳过，
                // 避免超时任务与人工审批「双写」同一节点：重复推进、重复插入下一节点审批记录、轨迹冗余。
                $lockQuery = Db::name('approval_instance')->where('id', $instance['id']);
                if (config('database.default') === 'mysql') {
                    $lockQuery->lock(true);
                }
                $locked = $lockQuery->find();
                if (!$locked || $locked['status'] !== 'PENDING' || (int)$locked['current_node_order'] !== $order) {
                    Db::rollback();
                    continue; // 实例已被并发事务推进/结束，幂等跳过
                }

                if (!ApprovalNodeExecutor::shouldAutoApproveOnTimeout($nodeMode)) {
                    // REV-01：会签(AND)节点超时不自动放行——会签本意「全部审批人通过才放行」，
                    // 静默自动通过会让合同在部分审批人未实质审核情况下被放行，属审批合规红线。
                    // 改为升级/催办：钉钉通知待审批人，节点保持待审不推进，等待人工处理。
                    $pendingIds = array_column($pendingRecords, 'approver_id');
                    // P2-5（M-R6）：催办去重——定时任务周期运行时，同一实例同一节点只催办一次。
                    // 以 approval_record 的 OVERDUE_URGE 记录作为「已催办」标记（与催办同事务写入），
                    // 避免每次运行都对超时 AND 节点重复发钉钉催办与站内信。
                    $urged = Db::name('approval_record')
                        ->where('instance_id', $instance['id'])
                        ->where('node_order', $order)
                        ->where('action', 'OVERDUE_URGE')
                        ->count();
                    if ($urged > 0) {
                        Db::commit();
                        $handled++;
                        continue;
                    }
                    if (!empty($contract)) {
                        $link = DingTalkService::approvalEntryUrl($instance['id']);
                        $msg = "审批催办（会签超时）\n\n"
                            . "合同标题：{$contract['title']}\n"
                            . "节点「{$node['name']}」会签已超时，请尽快处理（超时不会自动通过）。";
                        // P0-5：钉钉外呼入队，待本实例事务提交后发送（不持锁）
                        \app\common\logic\ApprovalNotifyService::queueNotify($pendingIds, '审批催办', $msg, $link, InternalNotify::TYPE_APPROVAL_OVERDUE);
                        Log::warning('会签超时自动通过已禁用(REV-01)，改发催办通知', [
                            'instance_id'      => $instance['id'],
                            'contract_no'      => $contract['contract_no'] ?? '',
                            'node'             => $node['name'] ?? $order,
                            'timeout_hours'    => $hours,
                            'pending_approvers'=> $pendingIds,
                        ]);
                        // 写已催办标记（同事务），供下次运行去重
                        Db::name('approval_record')->insert([
                            'instance_id' => $instance['id'],
                            'node_order'  => $order,
                            'node_name'   => $node['name'] ?? ('节点' . $order),
                            'approver_id' => (int)($pendingIds[0] ?? 0),
                            'action'      => 'OVERDUE_URGE',
                            'comment'     => '会签超时催办',
                            'acted_at'    => date('Y-m-d H:i:s'),
                            'created_at'  => date('Y-m-d H:i:s'),
                        ]);
                    }
                    // 不更新审批记录、不推进流程；节点保持 PENDING，等待审批人实质处理
                    Db::commit();
                    \app\common\logic\ApprovalNotifyService::flushNotify(); // P0-5：本实例事务提交后再发钉钉通知（不持锁）
                    $handled++;
                    continue;
                }

                // 或签(OR)节点：超时自动通过（或签语义即「任一通过即推进」，自动通过合规）
                foreach ($pendingRecords as $rec) {
                    Db::name('approval_record')->where('id', $rec['id'])->update([
                        'action'   => 'AUTO_APPROVED',
                        'comment'  => "或签超时({$hours}小时)自动通过",
                        'acted_at' => date('Y-m-d H:i:s'),
                    ]);
                }
                if (!empty($contract)) {
                    Log::warning('或签超时自动通过', [
                        'instance_id'   => $instance['id'],
                        'contract_no'   => $contract['contract_no'] ?? '',
                        'node'          => $node['name'] ?? $order,
                        'timeout_hours' => $hours,
                    ]);
                }
                // 节点现已“全部通过”，推进流程（与或签全部通过一致）
                self::advanceAfterNode($instance['id'], $instance, $flow, $nodes, $contract, $order, (int)($instance['submitted_by'] ?? 0));
                Db::commit();
                \app\common\logic\ApprovalNotifyService::flushNotify(); // P0-5：本实例事务提交后再发钉钉通知（不持锁）
                $handled++;
            } catch (\Throwable $e) {
                Db::rollback();
                // M26：回滚后记录异常日志，避免静默失败不可见（便于排查超时扫描中的异常实例）
                Log::error('超时审批处理失败，已回滚', [
                    'err'        => $e->getMessage(),
                    'instance_id'=> $instance['id'] ?? null,
                    'trace'      => $e->getTraceAsString(),
                ]);
                \app\common\logic\ApprovalNotifyService::$notifyQueue = []; // 回滚的实例不发送通知，清空队列避免泄漏
            }
        }
        return $handled;
    }

    /** 会签/审批节点超时阈值（小时），可由 system_config 的 approval_and_timeout_hours 覆盖，默认 72 */
    private static function andTimeoutHours(): int
    {
        $v = Db::name('system_config')->where('config_key', 'approval_and_timeout_hours')->value('config_value');
        return ($v !== null && (int)$v > 0) ? (int)$v : 72;
    }

    /** 解析审批人 */
    /**
     * 审批人解析（P2-1：逻辑已下沉至 ApproverResolver::resolve；
     * 此处保留委托桩，避免改动大量 self::resolveApprovers 调用点）
     */
    public static function resolveApprovers(array $node, int $submitterId): array
    {
        return ApproverResolver::resolve($node, $submitterId);
    }
}
