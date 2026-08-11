<?php
// ------------------------------------------------------------
// ApprovalSubmitService — 审批提交服务
// 从 ApprovalLogic 按职责拆出（v2.38.1 大文件拆分）
// ------------------------------------------------------------

namespace app\common\logic;

use think\facade\Db;
use think\facade\Log;
use app\common\service\DingTalkService;
use app\common\service\InternalNotify;

class ApprovalSubmitService
{

    /**
     * 匹配审批流程（F1：按业务类型过滤——发票专用流不会命中合同提交）
     * @return array|null
     */
    public static function matchFlow(string $category, float $amount, int $tradeAttr = 1, string $bizType = 'contract'): ?array
    {
        $flows = Db::name('approval_flow')
            ->where('status', 1)
            ->select()->toArray();

        // 收集所有命中（业务类型 + 分类匹配 + 金额在区间内/不启用金额）的流程
        $matched = [];
        foreach ($flows as $flow) {
            // F1：业务类型过滤——空值兼容旧数据（视为 contract）；发票流程只匹配发票提交
            $flowBiz = (string)($flow['biz_type'] ?? '');
            if ($flowBiz !== '' && $flowBiz !== $bizType) continue;

            // 分类匹配：category_list 优先（多选）；为空时回退遗留单值 category；两者皆空=适用全部
            $catList = [];
            if (!empty($flow['category_list'])) {
                $decoded = json_decode($flow['category_list'], true);
                if (is_array($decoded)) $catList = $decoded;
            }
            // v2.38.22：提交分类支持多选（数组/JSON/逗号分隔串），任一命中即匹配
            $submitted = self::toList($category);
            if (!empty($catList)) {
                if (empty(array_intersect($submitted, $catList))) continue;
            } elseif (!empty($flow['category'])) {
                if (!in_array($flow['category'], $submitted, true)) continue;
            }

            // 金额条件（M10 修复）：非交易合同（trade_attr=0）无金额概念，金额条件不适用，直接命中；
            // 交易合同（trade_attr=1，含金额为 0 的）use_amount=0 时不限金额，直接命中；=1 时需在 [min,max] 区间内
            $isTrade = ($tradeAttr === 1);
            $useAmount = isset($flow['use_amount']) ? (int)$flow['use_amount'] : 1;
            if ($useAmount === 1 && $isTrade) {
                if (!($amount >= (float)$flow['min_amount'] && $amount <= (float)$flow['max_amount'])) continue;
            }
            $matched[] = $flow;
        }
        if (empty($matched)) return null;

        // 优先匹配最具体（金额区间最窄；未启用金额条件视为最宽）的流程；范围相同则取下限更高的
        // v2.38.24：同类型流程内手动排序（sort_order 越小越靠前）为第一优先级；
        //           sort_order 相同（含全 0 旧数据）回退原有逻辑——交易按金额区间最窄、
        //           M10 修复：非交易合同金额维度无意义，按流程 id 升序（先创建优先），
        //           不再按金额下限排序（否则会错误命中"大额"流程——非交易没有金额概念）
        usort($matched, function ($a, $b) use ($isTrade) {
            $sa = (int)($a['sort_order'] ?? 0);
            $sb = (int)($b['sort_order'] ?? 0);
            if ($sa != $sb) return $sa <=> $sb;
            if (!$isTrade) {
                return (int)$a['id'] <=> (int)$b['id'];
            }
            $ua = isset($a['use_amount']) ? (int)$a['use_amount'] : 1;
            $ub = isset($b['use_amount']) ? (int)$b['use_amount'] : 1;
            $ra = $ua ? ((float)$a['max_amount'] - (float)$a['min_amount']) : PHP_FLOAT_MAX;
            $rb = $ub ? ((float)$b['max_amount'] - (float)$b['min_amount']) : PHP_FLOAT_MAX;
            if ($ra != $rb) return $ra <=> $rb;
            return (float)$b['min_amount'] <=> (float)$a['min_amount'];
        });
        return $matched[0];
    }

    /**
     * 提交审批（自动匹配流程）
     * 匹配顺序：指定 flowId → 按分类+金额 matchFlow → 报错
     * @param int $contractId
     * @param int $submitterId
     * @param int $flowId  为 0 时自动匹配
     * @return int|false  审批实例 ID
     */
    public static function submit(int $contractId, int $submitterId, int $flowId = 0)
    {
        // P0-4【严重·性能雪崩】修复：移除提交时「机会式」全表超时扫描。
        // 超时处理统一交由定时任务 php think approval:escalate（crontab 周期触发）承载，避免每次提交都全表扫描审批实例。

        Db::startTrans();
        $committed = false; // M6：标记事务是否已提交，避免 commit 后通知阶段异常误 rollback 已提交事务
        try {
            // P1-1（并发幂等）：事务内加锁重读合同（MySQL FOR UPDATE 行锁，SQLite 靠事务串行化），
            // 并在锁内检查该合同是否已有 PENDING 审批实例——两个并发提交若同时通过状态校验会各插一个实例，
            // 造成双实例/双份节点记录/双份通知（InnoDB 下第二条 UPDATE 等锁后继续执行且不报错）
            $contractQuery = Db::name('contract')->where('id', $contractId);
            if (config('database.default') === 'mysql') {
                $contractQuery->lock(true);
            }
            $contract = $contractQuery->find();
            if (!$contract) throw new \RuntimeException('合同不存在');

            // P1-2（M1）：提交前置状态校验——仅草稿(DRAFT)或驳回(REJECTED)状态的合同可提交审批。
            // 此校验位于创建审批实例之前，校验不通过直接抛异常，杜绝“孤儿实例”。
            // （与 ApprovalController::submit 中的权限/可见性校验 ContractLogic::accessible 衔接：可见性在前、状态合法性在后）
            if (!in_array($contract['status'], ['DRAFT', 'REJECTED'], true)) {
                throw new \RuntimeException('仅草稿或驳回状态的合同可提交审批');
            }
            // 幂等：已存在审批中的实例则拒绝重复提交（与前端防连点锁形成后端兜底）
            $pendingInstance = Db::name('approval_instance')
                ->where('contract_id', $contractId)
                ->where('biz_type', 'contract')
                ->where('status', 'PENDING')
                ->find();
            if ($pendingInstance) {
                throw new \RuntimeException('该合同已有审批中的流程，请勿重复提交');
            }

            // 1) 解析适用流程：指定 flowId > 分类+金额匹配
            // P1（2026-08-09）：提交人可控 flow_id 防护——指定流程必须满足：
            //  ① 存在且 status=1（启用）；
            //  ② 业务类型匹配（biz_type 为空视为 contract，发票流不可用于合同提交）；
            //  ③ 必须是「合同自身 flow_id」（保存时经校验）或「matchFlow 按分类+金额命中的流程」，
            //     否则提交人可选用更弱/自审流程绕过既定审批链。
            if ($flowId) {
                $flow = Db::name('approval_flow')->find($flowId);
                if (!$flow || (int)($flow['status'] ?? 0) !== 1) {
                    $flow = null;
                } elseif ((string)($flow['biz_type'] ?? '') !== '' && (string)$flow['biz_type'] !== 'contract') {
                    $flow = null;
                } elseif ((int)($contract['flow_id'] ?? 0) !== (int)$flowId) {
                    $auto = \app\common\logic\ApprovalSubmitService::matchFlow($contract['category'] ?? '', (float)($contract['amount'] ?? 0), (int)($contract['trade_attr'] ?? 1));
                    if (!$auto || (int)$auto['id'] !== (int)$flowId) {
                        $flow = null;
                    }
                }
            } else {
                $flow = \app\common\logic\ApprovalSubmitService::matchFlow($contract['category'] ?? '', (float)($contract['amount'] ?? 0), (int)($contract['trade_attr'] ?? 1));
            }
            if (!$flow) {
                throw new \RuntimeException('未匹配到适用的审批流程，请联系管理员配置');
            }

            $flowId = $flow['id'];

            // 创建审批实例
            $instanceId = Db::name('approval_instance')->insertGetId([
                'contract_id'  => $contractId,
                'flow_id'      => $flowId,
                'status'       => 'PENDING',
                'current_node_order' => 1,
                'submitted_by' => $submitterId,
                'submitted_at' => date('Y-m-d H:i:s'),
            ]);

            $nodes = json_decode($flow['nodes'], true) ?: [];
            // v2.38.0：防御性剔除遗留 CC 节点（存量库未执行迁移时），抄送改由 cc_list 处理，
            // 避免旧数据里 type=CC 的节点被当成审批节点生成待审批记录（即此前“纯抄送人能审批”的根因）。
            $nodes = array_values(array_filter($nodes, function ($n) {
                return ($n['type'] ?? '') !== 'CC';
            }));
            // v2.38.3：按节点级金额条件过滤不活跃节点（amount_min/amount_max 不满足→跳过；v2.38.25 激活条件已下线）
            $rawActiveCount = count($nodes); // 过滤前（已剔除 CC）的审批节点数，用于区分「真免签」与「条件全不满足」
            $nodes = self::filterActiveNodes($nodes, (float)($contract['amount'] ?? 0), $contract);
            $order = 1;
            // v2.38.1：抄送通知移至审批全部通过后触发（advanceAfterNode），不在提交时发送

            if ($order > count($nodes)) {
                // H4 修复：流程原本有审批节点但全部被条件过滤 → 拒绝提交并提示，
                // 杜绝「未知条件保守剔除 → 全剔除 → 免签直接放行」的反向风险（v2.38.0+ 审计发现）
                if ($rawActiveCount > 0) {
                    throw new \RuntimeException('当前流程中没有满足条件的审批节点，无法提交，请联系管理员调整流程或合同属性');
                }
                // 全部为抄送节点：无实质审批表决，属「免审批/免签」流程（REV-33）
                // 原实现直接 transitionStatus(APPROVED) 因 DRAFT->APPROVED 非合法一跳而静默失败，合同永远卡在草稿态。
                // 改为沿状态机合法跃迁 DRAFT->PENDING_APPROVAL->APPROVED，并标注免签，使合同可立即进入执行进度跟踪。
                Db::name('approval_instance')->where('id', $instanceId)->update([
                    'status'             => 'APPROVED',
                    'current_node_order' => $order,
                    'finished_at'        => date('Y-m-d H:i:s'),
                ]);
                // 顺状态机合法跃迁（免签，无实质审批节点）：DRAFT -> PENDING_APPROVAL -> APPROVED -> EXECUTING
                // P0-1：首跳同样校验返回值（免签流程若由 REJECTED 态重提，起点合法取决于状态机放开）
                if (!ContractLogic::transitionStatus($contractId, 'PENDING_APPROVAL', $submitterId)) {
                    throw new \RuntimeException('免签流程提交失败：合同当前状态不允许进入待审批');
                }
                ContractLogic::transitionStatus($contractId, 'APPROVED', $submitterId);
                // P1-1（M4）：全抄送免签路径同样推进至 EXECUTING，与下方“已进入待执行状态”文案一致（杜绝落 APPROVED）
                ContractLogic::transitionStatus($contractId, 'EXECUTING', $submitterId);
                // 审计/日志留痕：明确免签路径（REV-33）
                Log::info('全抄送流程免审批通过（免签，可进入执行进度）', [
                    'contract_id' => $contractId,
                    'instance_id' => $instanceId,
                    'operator'    => $submitterId,
                ]);
                Db::commit();
                $committed = true; // M6：已提交，后续通知/抄送异常不得再回滚
                // P0-5【严重·并发/可靠性】修复：钉钉外呼移出数据库事务（网络 I/O 不持锁），主流程落库后再发通知
                $link = DingTalkService::approvalEntryUrl($instanceId);
                \app\common\logic\ApprovalNotifyService::queueNotify([$submitterId], '审批已通过（免签）',
                    "合同 {$contract['title']} 为全抄送流程，无需审批/签署，已直接进入待执行状态！", $link, InternalNotify::TYPE_APPROVAL_APPROVED);
                // 免签流程无审批节点、advanceAfterNode 不会被触发，故在此显式触发抄送知会（修复：全抄送流抄送人收不到通知）
                self::fireCc($instanceId, $flow, $contract, (int)$submitterId); // M1：显式传提交人，避免读不存在的 contract.submitted_by
                \app\common\logic\ApprovalNotifyService::flushNotify();
                return $instanceId;
            }

            // 校验每个审批节点（非抄送）均能解析出审批人，避免提交后实例卡死（CR-02）
            foreach ($nodes as $idx => $node) {
                if (($node['type'] ?? '') === \app\common\logic\ApproverResolver::NODE_CC) {
                    continue;
                }
                if (empty(\app\common\logic\ApprovalActionService::resolveApprovers($node, $submitterId))) {
                    $name = $node['name'] ?? ('第' . ($idx + 1) . '个节点');
                    throw new \RuntimeException("审批节点「{$name}」未配置审批人，无法提交，请联系管理员补充审批人");
                }
            }

            // 首（非抄送）节点生成待审批记录
            $firstNode = $nodes[$order - 1];
            $approvers = \app\common\logic\ApprovalActionService::resolveApprovers($firstNode, $submitterId);
            Db::name('approval_instance')->where('id', $instanceId)->update([
                'current_node_order' => $order,
            ]);
            foreach ($approvers as $approverId) {
                Db::name('approval_record')->insert([
                    'instance_id' => $instanceId,
                    'node_order'  => $order,
                    'node_name'   => $firstNode['name'] ?? '审批节点',
                    'approver_id' => $approverId,
                    'action'      => 'PENDING',
                    'created_at'  => date('Y-m-d H:i:s'),
                ]);
            }

            // 更新合同状态（P0-1：校验 transitionStatus 返回值，非法跃迁必须回滚并明确报错，
            // 杜绝「审批实例已建、合同状态却静默卡死」的脏数据。）
            $ok = ContractLogic::transitionStatus($contractId, 'PENDING_APPROVAL', $submitterId);
            if (!$ok) {
                throw new \RuntimeException('提交审批失败：合同当前状态不允许进入待审批，请检查合同状态');
            }

            Db::commit();
            $committed = true; // M6：已提交，后续通知异常不得再回滚
            // P0-5【严重·并发/可靠性】修复：钉钉外呼移出数据库事务（网络 I/O 不持锁），主流程落库后再发通知
            if (!empty($approvers)) {
                $link = DingTalkService::approvalEntryUrl($instanceId);
                $msg = "新的审批请求\n\n"
                    . "合同标题：{$contract['title']}\n"
                    . "合同金额：¥" . format_money($contract['amount'] ?? 0) . "\n\n"
                    . "请尽快处理审批。";
                \app\common\logic\ApprovalNotifyService::queueNotify($approvers, '新的合同审批', $msg, $link, InternalNotify::TYPE_APPROVAL_SUBMITTED);
            }
            \app\common\logic\ApprovalNotifyService::flushNotify();
            return $instanceId;
        } catch (\RuntimeException $e) {
            // 校验类异常（合同不存在 / 未匹配流程 / 审批人为空等）向上抛出，由控制器给出明确提示
            if (!$committed) Db::rollback();
            throw $e;
        } catch (\Throwable $e) {
            if (!$committed) Db::rollback();
            // 记录异常日志便于排查（CR-60）
            Log::error('审批提交失败', [
                'contract_id'   => $contractId,
                'submitter_id'  => $submitterId,
                'flow_id'       => $flowId,
                'error'         => $e->getMessage(),
                'trace'         => $e->getTraceAsString(),
            ]);
            return false;
        }
    }

    /**
     * 发票申请提交审批（F1/F3，v2.38.7）：
     * 与合同 submit 共享审批引擎（实例/节点/通知/超时），仅业务对象为发票——
     * 创建 biz_type=invoice 的审批实例并写发票状态（PENDING_APPROVAL），
     * 审批通过/驳回由 ApprovalActionService 按 biz_type 分流更新发票状态。
     * @param int $invoiceId  发票 id
     * @param int $submitterId 申请人
     * @param int $flowId 指定发票审批流（0=自动匹配 biz_type=invoice 的启用流程）
     * @param int $contractId 关联合同（可选，0=不关联）
     * @return int|false 审批实例 ID
     */
    public static function submitInvoice(int $invoiceId, int $submitterId, int $flowId = 0, int $contractId = 0)
    {
        Db::startTrans();
        $committed = false;
        try {
            $invoice = Db::name('contract_invoice')->find($invoiceId);
            if (!$invoice) throw new \RuntimeException('发票申请不存在');
            // P2-3：撤回(CANCELLED)的申请与驳回(REJECTED)一样可重新提交（状态机已放开 CANCELLED -> PENDING_APPROVAL）
            if (($invoice['status'] ?? '') !== \app\common\logic\InvoiceLogic::STATUS_PENDING_APPROVAL
                && ($invoice['status'] ?? '') !== \app\common\logic\InvoiceLogic::STATUS_REJECTED
                && ($invoice['status'] ?? '') !== \app\common\logic\InvoiceLogic::STATUS_CANCELLED) {
                throw new \RuntimeException('当前状态不可提交审批');
            }

            if ($flowId) {
                $flow = Db::name('approval_flow')->find($flowId);
                if (!$flow || ($flow['biz_type'] ?? '') !== 'invoice') {
                    throw new \RuntimeException('指定的流程不是发票审批流程');
                }
            } else {
                // H4：按开票公司等表单字段条件匹配分支流程（无条件流程为默认兜底）
                $flow = self::matchInvoiceFlow($invoice);
            }
            if (!$flow) {
                throw new \RuntimeException('未匹配到发票审批流程，请联系管理员配置「发票审批」流程');
            }
            $flowId = $flow['id'];

            // P2-3（M-R5）：驳回(REJECTED)/撤回(CANCELLED)后重新提交，先把发票状态迁回 PENDING_APPROVAL。
            // 此前不回迁直接建审批实例，审批通过后 transitionStatus(APPROVED) 从 REJECTED/CANCELLED 出发
            // 非法返回 false 且无人校验，发票永久卡死、财务待开票列表查不到。
            // 回迁失败抛异常使整个事务回滚（TP6 事务闭包 return false 不会回滚，必须用异常）。
            if (in_array($invoice['status'], [
                \app\common\logic\InvoiceLogic::STATUS_REJECTED,
                \app\common\logic\InvoiceLogic::STATUS_CANCELLED,
            ], true)) {
                if (!\app\common\logic\InvoiceLogic::transitionStatus((int)$invoiceId, 'PENDING_APPROVAL', (int)$submitterId)) {
                    throw new \RuntimeException('发票状态回迁失败：当前状态不允许重新提交审批，请联系管理员');
                }
            }

            // 创建审批实例（biz_type=invoice，target_id=发票id）
            $instanceId = Db::name('approval_instance')->insertGetId([
                'contract_id'  => (int)$contractId,
                'biz_type'     => 'invoice',
                'target_id'    => (int)$invoiceId,
                'flow_id'      => $flowId,
                'status'       => 'PENDING',
                'current_node_order' => 1,
                'submitted_by' => (int)$submitterId,
                'submitted_at' => date('Y-m-d H:i:s'),
            ]);

            $nodes = json_decode($flow['nodes'], true) ?: [];
            $nodes = array_values(array_filter($nodes, function ($n) {
                return ($n['type'] ?? '') !== 'CC';
            }));
            // 发票流程金额条件不适用：按金额 0 过滤（发票流程 use_amount=0 时节点无金额限制）
            $nodes = self::filterActiveNodes($nodes, 0.0, ['amount' => 0, 'trade_attr' => 1]);
            if (empty($nodes)) {
                throw new \RuntimeException('发票审批流程没有可用审批节点，请联系管理员配置');
            }

            // 校验每个节点能解析出审批人
            foreach ($nodes as $node) {
                if (empty(\app\common\logic\ApprovalActionService::resolveApprovers($node, $submitterId))) {
                    throw new \RuntimeException('发票审批节点「' . ($node['name'] ?? '') . '」未配置审批人，请联系管理员');
                }
            }

            // 首节点生成待审批记录
            $firstNode = $nodes[0];
            $approvers = \app\common\logic\ApprovalActionService::resolveApprovers($firstNode, $submitterId);
            foreach ($approvers as $approverId) {
                Db::name('approval_record')->insert([
                    'instance_id' => $instanceId,
                    'node_order'  => 1,
                    'node_name'   => $firstNode['name'] ?? '审批节点',
                    'approver_id' => $approverId,
                    'action'      => 'PENDING',
                    'created_at'  => date('Y-m-d H:i:s'),
                ]);
            }
            // 发票状态由创建时的 PENDING_APPROVAL 保持不变（驳回/撤回重提时已在上方迁回）

            Db::commit();
            $committed = true;
            if (!empty($approvers)) {
                $link = DingTalkService::approvalEntryUrl($instanceId);
                $msg = "新的发票开票申请\n\n"
                    . "开票内容：{$invoice['content_desc']}\n"
                    . "金额：¥" . format_money($invoice['amount'] ?? 0) . "\n\n"
                    . "请尽快审批。";
                \app\common\logic\ApprovalNotifyService::queueNotify($approvers, '新的发票审批', $msg, $link, InternalNotify::TYPE_APPROVAL_SUBMITTED);
            }
            \app\common\logic\ApprovalNotifyService::flushNotify();
            return $instanceId;
        } catch (\RuntimeException $e) {
            if (!$committed) Db::rollback();
            throw $e;
        } catch (\Throwable $e) {
            if (!$committed) Db::rollback();
            Log::error('发票审批提交失败', [
                'invoice_id'  => $invoiceId,
                'submitter'   => $submitterId,
                'error'       => $e->getMessage(),
                'trace'       => $e->getTraceAsString(),
            ]);
            return false;
        }
    }

    /**
     * 匹配发票专用审批流（F1）：biz_type=invoice 且启用的第一条（发票流程不做分类/金额条件）
     * @return array|null
     */
    /**
     * H4：按表单条件匹配发票审批流程（如不同开票公司 → 不同审批人/抄送人）。
     * 流程 form_condition 存 [{field, value}]；与业务数据（发票行）逐条比对，全部命中才匹配。
     * v2.38.22：value 含逗号视为多选（in 语义）；业务值为数组（JSON）或逗号分隔串时按集合匹配。
     * 优先返回条件命中的流程；无命中回退无条件的默认流程；均无返回 null。
     * @param array $bizData 业务数据行（发票：含 our_company_id 等表单字段）
     * @return array|null
     */
    public static function matchInvoiceFlow(array $bizData = []): ?array
    {
        $flows = Db::name('approval_flow')
            ->where('status', 1)
            ->where('biz_type', 'invoice')
            ->order('sort_order', 'asc')  // v2.38.24：同类型内手动排序优先（默认流程取最靠前）
            ->order('id', 'asc')
            ->select()->toArray();

        $fallback = null;
        foreach ($flows as $flow) {
            $cond = json_decode((string)($flow['form_condition'] ?? ''), true) ?: [];
            if (empty($cond)) {
                if ($fallback === null) $fallback = $flow; // 无条件流程：默认兜底（取第一条）
                continue;
            }
            // 条件全命中才匹配（如 our_company_id=1；category=服务合同,采购合同 多选）
            $hit = true;
            foreach ($cond as $c) {
                $field = (string)($c['field'] ?? '');
                $value = (string)($c['value'] ?? '');
                if ($field === '') { $hit = false; break; }
                if (!self::formFieldMatch($bizData[$field] ?? '', $value)) { $hit = false; break; }
            }
            if ($hit) return $flow;
        }
        return $fallback;
    }

    /**
     * v2.38.22：表单字段值 vs 流程条件值匹配（多选兼容）。
     *  - 条件值含逗号 → in 语义（属于其一）；
     *  - 业务值为数组（JSON 解码）或逗号分隔串 → 按集合处理，任一命中即匹配；
     *  - 均不含逗号 → 严格等值。
     */
    private static function formFieldMatch($actual, string $expect): bool
    {
        $expectList = null;
        if (strpos($expect, ',') !== false) {
            $expectList = array_values(array_filter(array_map('trim', explode(',', $expect)), fn($v) => $v !== ''));
        }
        $actualArr = null;
        if (is_array($actual)) {
            $actualArr = array_values(array_filter(array_map('strval', $actual), fn($v) => $v !== ''));
        } elseif (is_string($actual) && strpos($actual, ',') !== false) {
            $actualArr = array_values(array_filter(array_map('trim', explode(',', $actual)), fn($v) => $v !== ''));
        }
        if ($expectList !== null && $actualArr !== null) {
            return !empty(array_intersect($actualArr, $expectList)); // 多选字段 vs 多选条件
        }
        if ($expectList !== null) {
            return in_array((string)$actual, $expectList, true); // 单值字段 vs 多选条件
        }
        if ($actualArr !== null) {
            return in_array($expect, $actualArr, true); // 多选字段 vs 单值条件
        }
        return (string)$actual === $expect; // 单值等值
    }


    /**
     * 按金额过滤不活跃节点（v2.38.3；v2.38.25 节点级激活条件 activate_when 已下线，
     * 两处编辑器（合同/发票）均不再提供该配置项，执行时也不再按属性条件剔除节点）：
     *  - 节点级 amount_min/amount_max 不满足→跳过。
     * @param array $nodes    流程节点数组
     * @param float $amount   合同金额（发票流程传 0，发票流程节点无金额限制）
     * @param array $contract 兼容保留（激活条件下线后不再使用）
     * @return array 过滤后保留的节点（保持原顺序，键重置）
     */
    public static function filterActiveNodes(array $nodes, float $amount, array $contract = []): array
    {
        return array_values(array_filter($nodes, function ($n) use ($amount) {
            $min = isset($n['amount_min']) && $n['amount_min'] !== '' ? (float)$n['amount_min'] : null;
            $max = isset($n['amount_max']) && $n['amount_max'] !== '' ? (float)$n['amount_max'] : null;
            if ($min !== null && $amount < $min) return false;
            if ($max !== null && $amount > $max) return false;
            return true;
        }));
    }

    /** v2.38.22：把字段值/条件值统一为字符串列表（数组、JSON 数组、逗号分隔串、单值） */
    private static function toList($v): array
    {
        if (is_array($v)) {
            return array_values(array_filter(array_map('strval', $v), fn($x) => $x !== ''));
        }
        $s = (string)$v;
        if (strpos($s, '[') === 0) {
            $decoded = json_decode($s, true);
            if (is_array($decoded)) return self::toList($decoded);
        }
        if (strpos($s, ',') !== false) {
            return array_values(array_filter(array_map('trim', explode(',', $s)), fn($x) => $x !== ''));
        }
        return $s === '' ? [] : [$s];
    }

    /**
     * 收集流程内所有「审批节点(ROLE)」解析出的审批人 ID（去重）。
     * 用于抄送去重：同一个人在本流程中若同时是某审批节点的审批人，
     * 他已经会收到「请尽快审批」的审批催办，再发一份「抄送知会」属于冗余且语义矛盾的重复通知，
     * 故抄送节点广播时应将其剔除（设计缺陷修复：CC 节点与审批节点同处 nodes 数组、引擎无隔离）。
     * @param array $nodes        流程节点数组
     * @param int   $submitterId 提交人 ID
     * @return array 去重后的审批人 ID 列表
     */
    private static function collectFlowApprovers(array $nodes, int $submitterId): array
    {
        $approvers = [];
        foreach ($nodes as $node) {
            // v2.38.0：nodes 仅含审批节点（ROLE/DEPT_LEADER/SPECIFIC_USER），不再含 CC
            $ids = \app\common\logic\ApprovalActionService::resolveApprovers($node, $submitterId);
            if ($ids) $approvers = array_merge($approvers, $ids);
        }
        return array_values(array_unique($approvers));
    }

    /**
     * v2.38.0：触发流程级抄送知会。
     * 抄送已从审批节点链独立为 approval_flow.cc_list（{role_codes:[], cc_user_ids:[]}），
     * 与 nodes 平级：提交审批时一次性触发，不再写 approval_record、不再参与节点推进判断。
     * 去重语义与 v2.37.5 一致：同一人既是审批节点审批人又是抄送人时，仅收审批催办，不再重复收抄送知会。
     *
     * @param int   $instanceId
     * @param array $flow      审批流行（含 cc_list / nodes）
     * @param array $contract  合同行（含 title / submitted_by）

    /**
     * v2.38.0：触发流程级抄送知会。
     * 抄送已从审批节点链独立为 approval_flow.cc_list（{role_codes:[], cc_user_ids:[]}），
     * 与 nodes 平级：提交审批时一次性触发，不再写 approval_record、不再参与节点推进判断。
     * 去重语义与 v2.37.5 一致：同一人既是审批节点审批人又是抄送人时，仅收审批催办，不再重复收抄送知会。
     *
     * @param int   $instanceId
     * @param array $flow      审批流行（含 cc_list / nodes）
     * @param array $contract  合同行（含 title / submitted_by）
     */
    public static function fireCc(int $instanceId, array $flow, array $contract, int $submitterId = 0): void
    {
        $cc      = json_decode($flow['cc_list'] ?? '[]', true) ?: [];
        $roleCodes = $cc['role_codes'] ?? [];
        $userIds   = array_map('intval', $cc['cc_user_ids'] ?? []);
        if (!is_array($roleCodes)) $roleCodes = [];
        if (!is_array($userIds)) $userIds = [];

        // 角色码 -> 用户 ID 并集
        $roleUsers = ApproverResolver::resolveRoleCodes($roleCodes);
        $all = array_values(array_unique(array_merge($roleUsers, $userIds)));
        if (empty($all)) {
            return; // 无抄送配置
        }

        // 去重：既是审批节点审批人，又出现在抄送列表 -> 只发审批催办，不发抄送知会
        $approverIds = \app\common\logic\ApprovalSubmitService::collectFlowApprovers(json_decode($flow['nodes'], true) ?: [], (int)$submitterId); // M1：提交人显式传入（contract 无 submitted_by 列）
        $ccOnly = array_values(array_diff($all, $approverIds));
        if (empty($ccOnly)) {
            return; // 抄送人全部也是审批人，仅审批催办已覆盖
        }

        $link = DingTalkService::approvalEntryUrl($instanceId);
        $msg = "审批抄送知会\n\n"
            . "合同标题：{$contract['title']}\n\n"
            . "该合同审批流转已抄送知会给你，请在应用内查看详情。";

        $now = date('Y-m-d H:i:s');
        $ccLogRows = [];
        foreach ($ccOnly as $uid) {
            $ccLogRows[] = [
                'instance_id' => $instanceId,
                'user_id'     => $uid,
                'node_order'  => 0, // 流程级抄送，提交即触发
                'role_codes'  => json_encode($roleCodes, JSON_UNESCAPED_UNICODE),
                'cc_user_ids' => json_encode($userIds, JSON_UNESCAPED_UNICODE),
                'created_at'  => $now,
            ];
        }
        // 批量插入抄送轨迹（同事务，随审批实例提交）
        Db::name('approval_cc_log')->insertAll($ccLogRows);
        // 钉钉 + 站内信知会（入队，事务提交后发送，不持锁）
        \app\common\logic\ApprovalNotifyService::queueNotify($ccOnly, '审批抄送知会', $msg, $link, InternalNotify::TYPE_APPROVAL_CC);
    }
}
