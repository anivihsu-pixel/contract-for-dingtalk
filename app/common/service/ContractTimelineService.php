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
     * 获取合同的完整时间线（合并 contract_revision + approval_record + payment_record）
     * 勾股OA 做法：合同详情页底部展示操作历史，合并多个来源
     */
    public static function getTimeline(int $contractId): array
    {
        $events = [];

        // 1. 合同变更记录 (contract_revision)
        $revisions = Db::name('contract_revision')
            ->alias('r')
            ->leftJoin('user u', 'r.operator_id = u.id')
            ->field('r.*, u.name as operator_name')
            ->where('r.contract_id', $contractId)
            ->order('r.created_at', 'asc')
            ->select()->toArray();

        // 字段中文名映射（操作记录里字段名需中文化，避免「修改 status」这类英文外露）
        $fieldLabelMap = [
            'status'        => '状态',
            'amount'        => '金额',
            'title'         => '标题',
            'category'      => '分类',
            'direction'     => '方向',
            'party_a_name'  => '甲方',
            'party_b_name'  => '乙方',
            'effective_date'=> '生效日期',
            'expiry_date'   => '到期日期',
            'content'       => '内容',
            'remark'        => '备注',
            'owner_id'      => '负责人',
            'trade_attr'    => '交易属性',
        ];
        foreach ($revisions as $r) {
            $field   = $r['field_name'];
            $fieldLabel = $fieldLabelMap[$field] ?? $field;
            $oldVal  = $r['old_value'] ?? '';
            $newVal  = $r['new_value'] ?? '';
            // 状态变更：值经合同状态机中文标签本地化（如 PENDING_APPROVAL → 待审批）
            if ($field === 'status') {
                $oldVal = \app\common\logic\ContractLogic::STATUS_LABELS[$oldVal] ?? $oldVal;
                $newVal = \app\common\logic\ContractLogic::STATUS_LABELS[$newVal] ?? $newVal;
            }
            $title  = $field === 'system' ? '创建合同' : "修改{$fieldLabel}";
            // 有旧值且新旧不同 → 展示「旧 → 新」过渡；否则仅展示新值
            $detail = ($oldVal !== '' && $oldVal !== $newVal) ? "{$oldVal} → {$newVal}" : $newVal;
            $events[] = [
                'time'       => $r['created_at'],
                'type'       => 'revision',
                'icon'       => 'bi-pencil',
                'color'      => '#0b5ed7',
                'title'      => $title,
                'detail'     => $detail,
                'operator'   => $r['operator_name'] ?? '',
            ];
        }

        // 2. 审批记录 (approval_record)
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

        // 3. 回款记录 (payment_record)
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
