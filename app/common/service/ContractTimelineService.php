<?php
// ===========================================================================
// 合同操作日志/时间线 — 参考 勾股OA 和 Laravel Contract CMP 的审计方案
// 勾股OA: https://github.com/gouguoyin/office (AuditLog/操作日志)
// Laravel CMP: https://github.com/mounimRhouli/Laravel-Contract-Management-Project
// ===========================================================================

namespace app\common\service;

use think\facade\Db;

class ContractTimelineService
{
    /**
     * 获取合同的完整时间线（合并 approval_record + payment_record）
     * 勾股OA 做法：合同详情页底部展示操作历史，合并多个来源
     */
    public static function getTimeline(int $contractId): array
    {
        $events = [];

        // 1. 审批记录 (approval_record)
        $approvals = Db::name('approval_record')
            ->alias('ar')
            ->join('approval_instance ai', 'ar.instance_id = ai.id')
            ->leftJoin('user u', 'ar.approver_id = u.id')
            ->field('ar.*, ai.contract_id, u.name as approver_name')
            ->where('ai.contract_id', $contractId)
            ->where('ar.action', '<>', 'PENDING')
            ->order('ar.acted_at', 'asc')
            ->select()->toArray();

        foreach ($approvals as $a) {
            $events[] = [
                'time'       => $a['acted_at'],
                'type'       => 'approval',
                'icon'       => 'bi-check-circle',
                'color'      => $a['action'] === 'APPROVED' ? '#198754' : '#dc3545',
                'title'      => $a['action'] === 'APPROVED' ? '审批通过' : '审批驳回',
                'detail'     => "{$a['node_name']} - {$a['approver_name']}" . ($a['comment'] ? "：{$a['comment']}" : ''),
                'operator'   => $a['approver_name'] ?? '',
            ];
        }

        // 2. 回款记录 (payment_record)
        $payments = Db::name('payment_record')
            ->where('contract_id', $contractId)
            ->order('created_at', 'asc')
            ->select()->toArray();

        foreach ($payments as $p) {
            if ($p['status'] === 'PAID') {
                $events[] = [
                    'time'       => $p['actual_date'] ?? $p['created_at'],
                    'type'       => 'payment',
                    'icon'       => 'bi-cash-stack',
                    'color'      => '#198754',
                    'title'      => '确认收款',
                    'detail'     => "¥{$p['amount']} {$p['description']}（" . dict('payment_method', $p['payment_method']) . "）",
                    'operator'   => '',
                ];
            }
        }

        // 按时间排序
        usort($events, fn($a, $b) => strcmp($b['time'] ?? '', $a['time'] ?? ''));

        return $events;
    }
}
