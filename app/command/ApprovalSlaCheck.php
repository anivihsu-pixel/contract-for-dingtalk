<?php
namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;

/**
 * 审批 SLA 超时检查 + 自动催办/升级（v2.38.3）
 * 用法：php think approval:sla-check
 * cron: every 30 min, php think approval:sla-check
 */
class ApprovalSlaCheck extends Command
{
    protected function configure()
    {
        $this->setName('approval:sla-check')
            ->setDescription('审批超时催办：扫描超时 PENDING 节点，发送催办通知；2倍超时自动升级到上级');
    }

    protected function execute(Input $input, Output $output)
    {
        $now = date('Y-m-d H:i:s');
        $escalated = 0;
        $reminded = 0;

        // 1. 扫描当前 PENDING 的审批实例
        $instances = Db::name('approval_instance')
            ->where('status', 'PENDING')
            ->select()->toArray();

        foreach ($instances as $inst) {
            $flow = Db::name('approval_flow')->find($inst['flow_id']);
            if (!$flow) continue;

            $nodes = json_decode($flow['nodes'], true) ?: [];
            $currentOrder = (int)$inst['current_node_order'];
            if ($currentOrder < 1 || $currentOrder > count($nodes)) continue;

            $currentNode = $nodes[$currentOrder - 1];
            $timeoutHours = (float)($currentNode['timeout_hours'] ?? 0);
            if ($timeoutHours <= 0) continue; // 未设超时不处理
            // M11 修复：或签(OR)节点超时由 approval:escalate 自动通过推进，本命令仅催办、不升级，
            // 避免同一 OR 节点「sla-check 升级上级」与「escalate 自动通过」双发冲突。
            // 会签(AND)节点超时不自动放行（REV-01），本命令承担催办 + 2倍超时升级上级。
            $nodeMode = strtoupper((string)($currentNode['mode'] ?? 'OR'));

            // M11 修复：每实例事务 + 并发复核（N-M1 模式，与 processOverdueApprovals 一致），
            // 防并发双跑 sla-check 重复插入 PENDING/ESCALATED/SLA_REMIND 记录与重复通知。
            Db::startTrans();
            try {
                $lockQuery = Db::name('approval_instance')->where('id', $inst['id']);
                if (config('database.default') === 'mysql') {
                    $lockQuery->lock(true);
                }
                $locked = $lockQuery->find();
                if (!$locked || $locked['status'] !== 'PENDING' || (int)$locked['current_node_order'] !== $currentOrder) {
                    Db::rollback();
                    continue; // 实例已被并发推进/结束，幂等跳过
                }

                // 找到该节点最早的 PENDING 记录时间（事务内读取，保证与复核一致）
                $firstPending = Db::name('approval_record')
                    ->where('instance_id', $inst['id'])
                    ->where('node_order', $currentOrder)
                    ->where('action', 'PENDING')
                    ->order('created_at', 'asc')
                    ->find();
                if (!$firstPending) {
                    Db::rollback();
                    continue;
                }

                $elapsed = (strtotime($now) - strtotime($firstPending['created_at'])) / 3600;
                if ($elapsed < $timeoutHours) {
                    Db::rollback();
                    continue; // 未超时
                }

                // 获取审批人列表
                $pendingRecords = Db::name('approval_record')
                    ->where('instance_id', $inst['id'])
                    ->where('node_order', $currentOrder)
                    ->where('action', 'PENDING')
                    ->select()->toArray();
                $approverIds = array_column($pendingRecords, 'approver_id');

                $contract = Db::name('contract')->find($inst['contract_id']) ?: [];
                // F1/F3：发票审批实例 contract_id 可为 0（target_id=发票），取发票标题作通知/文案兜底
                if (empty($contract) && ($inst['biz_type'] ?? '') === 'invoice') {
                    $inv = Db::name('contract_invoice')->find($inst['target_id'] ?: $inst['contract_id']);
                    $contract = ['title' => $inv ? ('开票：' . ($inv['content_desc'] ?? '')) : '发票申请'];
                }

                // 2倍超时：仅会签(AND)节点升级到直属上级（上级插入真实 PENDING 任务可审批推进）；
                // 或签(OR)节点跳过升级，交由 approval:escalate 超时自动通过，避免双发。
                if ($elapsed >= $timeoutHours * 2 && $nodeMode === 'AND') {
                    $supervisorIds = $this->getSupervisors($approverIds);
                    if (!empty($supervisorIds)) {
                        // 已有 PENDING 上级记录则跳过（防并发重复插入）
                        $existingPending = Db::name('approval_record')
                            ->where('instance_id', $inst['id'])
                            ->where('node_order', $currentOrder)
                            ->where('action', 'PENDING')
                            ->column('approver_id');
                        $existingPending = array_map('intval', $existingPending);
                        foreach ($supervisorIds as $sid) {
                            if (in_array($sid, $existingPending, true)) {
                                continue; // 该上级本就是当前节点审批人，无需重复插入
                            }
                            Db::name('approval_record')->insert([
                                'instance_id' => $inst['id'],
                                'node_order'  => $currentOrder,
                                'node_name'   => ($currentNode['name'] ?? '节点' . $currentOrder) . '（超时升级）',
                                'approver_id' => $sid,
                                'action'      => 'PENDING',
                                'created_at'  => $now,
                            ]);
                            // 升级审计痕迹（引擎忽略，仅供轨迹查看）
                            Db::name('approval_record')->insert([
                                'instance_id' => $inst['id'],
                                'node_order'  => $currentOrder,
                                'node_name'   => ($currentNode['name'] ?? '节点' . $currentOrder) . '（超时升级）',
                                'approver_id' => $sid,
                                'action'      => 'ESCALATED',
                                'created_at'  => $now,
                            ]);
                        }
                        // 发送升级通知（含原审批人，知会已升级）
                        $notifyIds = array_values(array_unique(array_merge($supervisorIds, $approverIds)));
                        \app\common\logic\ApprovalNotifyService::queueNotify($notifyIds,
                            '审批超时升级',
                            "合同《{$contract['title']}》审批节点「{$currentNode['name']}」已超时 " . round($elapsed, 1) . " 小时，已升级至您的上级处理，请紧急处理。",
                            \app\common\service\DingTalkService::approvalEntryUrl($inst['id']),
                            \app\common\service\InternalNotify::TYPE_APPROVAL_OVERDUE
                        );
                        $escalated++;
                    }
                } elseif ($elapsed >= $timeoutHours) {
                    // 首次超时：发送催办通知（OR/AND 均催办；OR 由 escalate 自动通过，催办仅提示）
                    $lastRemind = Db::name('approval_record')
                        ->where('instance_id', $inst['id'])
                        ->where('node_order', $currentOrder)
                        ->where('action', 'SLA_REMIND')
                        ->order('created_at', 'desc')
                        ->find();
                    // 每超时间隔再催（避免每条 cron 都发）
                    $hoursSinceLastRemind = $lastRemind
                        ? (strtotime($now) - strtotime($lastRemind['created_at'])) / 3600
                        : 999;
                    if ($hoursSinceLastRemind >= max(1, $timeoutHours * 0.5)) {
                        // 记录催办
                        foreach ($approverIds as $aid) {
                            Db::name('approval_record')->insert([
                                'instance_id' => $inst['id'],
                                'node_order'  => $currentOrder,
                                'node_name'   => ($currentNode['name'] ?? '节点' . $currentOrder) . '（催办提醒）',
                                'approver_id' => $aid,
                                'action'      => 'SLA_REMIND',
                                'created_at'  => $now,
                            ]);
                        }
                        \app\common\logic\ApprovalNotifyService::queueNotify($approverIds,
                            '审批催办',
                            "合同《{$contract['title']}》审批节点「{$currentNode['name']}」已等待 " . round($elapsed, 1) . " 小时，请尽快审批。",
                            \app\common\service\DingTalkService::approvalEntryUrl($inst['id']),
                            \app\common\service\InternalNotify::TYPE_APPROVAL_OVERDUE // M7 修复：此前硬编码 1，站内信 type 落库为 "1" 与常量不一致
                        );
                        $reminded++;
                    }
                }

                Db::commit();
                // P0-5：事务提交后再发通知，不持锁
                \app\common\logic\ApprovalNotifyService::flushNotify();
            } catch (\Throwable $e) {
                Db::rollback();
                // 单实例异常不中断整轮扫描，记录后继续
                \think\facade\Log::error('approval:sla-check 实例处理异常: ' . $e->getMessage(), [
                    'instance_id' => $inst['id'] ?? 0,
                ]);
            }
        }

        $output->writeln("SLA 检查完成：{$now}");
        $output->writeln("  催办 {$reminded} 项，升级 {$escalated} 项");

        // v2.38.3 重做：命令结束前必须 flush 通知队列，否则 queueNotify 入队后永不发送
        \app\common\logic\ApprovalNotifyService::flushNotify();
        return 0;
    }

    /** 获取审批人列表的直属上级用户ID */
    private function getSupervisors(array $userIds): array
    {
        $supervisors = [];
        $users = Db::name('user')->whereIn('id', $userIds)->select()->toArray();
        foreach ($users as $u) {
            // 通过部门负责人作为上级（dept_id → department.leader_user_id）
            if (!empty($u['dept_id'])) {
                $dept = Db::name('department')->where('id', $u['dept_id'])->find();
                if ($dept && !empty($dept['leader_user_id']) && $dept['leader_user_id'] != $u['id']) {
                    $supervisors[] = (int)$dept['leader_user_id'];
                }
            }
        }
        // 若无部门负责人，回退到超级管理员（is_admin=1 ∪ admin 角色，钉钉部署 is_admin=0 同效）
        if (empty($supervisors)) {
            $supervisors = \app\common\logic\AuthLogic::getAdminUserIds(false);
        }
        return array_unique($supervisors);
    }
}
