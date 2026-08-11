<?php
// +----------------------------------------------------------------------
// | 总经理经营周报聚合（v2.47.0）
// | 口径与 ReportLogic/FinanceLogic 完全一致：仅交易合同(trade_attr=1)、
// | 排除草稿/驳回/审批中、排除框架合同预算上限、已收回款统一 paid_amount。
// | 周报 = 上周（周一~周日）实际发生（新增合同/回款）+ 当前时点快照（逾期/待审批），
// | 供每周一经营例会参考；钉钉仅发极简提示（无摘要），站内信带摘要，完整内容在 /report/weekly 与 /m/report/weekly。
// +----------------------------------------------------------------------

namespace app\common\logic;

use think\facade\Db;

class WeeklyReportLogic
{
    /** 排除的未生效状态（与驾驶舱/月报一致） */
    const EXCLUDED_STATUS = ['DRAFT', 'REJECTED', 'PENDING_APPROVAL'];

    /**
     * 周范围（周一~周日）。默认返回上一自然周；传 $weekMonday（周一日期 Y-m-d）可回溯任意周。
     * @return array{start:string,end:string}
     */
    public static function weekRange(?string $weekMonday = null): array
    {
        if ($weekMonday && preg_match('/^\d{4}-\d{2}-\d{2}$/', $weekMonday)) {
            $start = $weekMonday;
        } else {
            $dow       = (int)date('N'); // 1=Mon .. 7=Sun
            $thisMonday = date('Y-m-d', strtotime('-' . ($dow - 1) . ' days'));
            $start      = date('Y-m-d', strtotime($thisMonday . ' -7 days'));
        }
        $end = date('Y-m-d', strtotime($start . ' +6 days'));
        return [$start, $end];
    }

    /**
     * 聚合指定周的经营周报（全公司 + 部门 + 明细）
     * @param string $start 周一（Y-m-d）
     * @param string $end   周日（Y-m-d）
     * @return array{
     *   start:string,end:string,
     *   summary:array,
     *   departments:array,
     *   new_contracts:array,
     *   overdue_payments:array
     * }
     */
    public static function build(string $start, string $end): array
    {
        $endTs    = $end . ' 23:59:59';
        $excluded = \exclude_framework_contracts_ids();

        // ---- 上周新增交易合同（created_at 落周内，已生效状态）----
        $newQ = Db::name('contract')->where('is_deleted', 0)
            ->where('trade_attr', 1)
            ->where('status', 'not in', self::EXCLUDED_STATUS)
            ->whereNotIn('id', $excluded)
            ->whereBetween('created_at', [$start, $endTs]);
        $newContracts = (clone $newQ)->field('id, contract_no, title, amount, status, dept_id, effective_date, owner_id')
            ->order('id', 'desc')->select()->toArray();
        $contractCnt   = count($newContracts);
        $contractAmt   = array_sum(array_map('floatval', array_column($newContracts, 'amount')));

        // ---- 上周实际回款（RECEIVABLE + PAID，actual_date 落周内，paid_amount）----
        $payRows = Db::name('payment_record')->alias('p')
            ->join('contract c', 'p.contract_id = c.id')
            ->where('c.is_deleted', 0)->where('c.trade_attr', 1)
            ->where('p.payment_type', 'RECEIVABLE')->where('p.status', 'PAID')
            ->whereBetween('p.actual_date', [$start, $endTs])
            ->field('p.paid_amount, p.contract_id, c.dept_id')
            ->select()->toArray();
        $received = array_sum(array_map('floatval', array_column($payRows, 'paid_amount')));

        // ---- 当前逾期快照（OVERDUE）----
        $ovdRows = Db::name('payment_record')->alias('p')
            ->join('contract c', 'p.contract_id = c.id')
            ->where('c.is_deleted', 0)->where('c.trade_attr', 1)
            ->where('p.payment_type', 'RECEIVABLE')->where('p.status', 'OVERDUE')
            ->field('p.amount, p.planned_date, p.contract_id, c.contract_no, c.title, c.dept_id')
            ->order('p.planned_date', 'asc')->select()->toArray();
        $overdueAmt = array_sum(array_map('floatval', array_column($ovdRows, 'amount')));
        $overdueCnt = count($ovdRows);

        // ---- 待审批快照（PENDING 合同审批实例）----
        $pendingRows = Db::name('approval_instance')->alias('ai')
            ->leftJoin('contract c', 'ai.contract_id = c.id')
            ->where('ai.status', 'PENDING')->where('ai.biz_type', 'contract')
            ->field('ai.id, ai.contract_id, c.dept_id')
            ->select()->toArray();
        $pendingCnt = count($pendingRows);

        // ---- 部门维度聚合（union 上述四类数据出现的 dept_id）----
        $deptMeta = []; // dept_id => [contract_cnt, contract_amount, received, overdue, overdue_cnt, pending]
        $deptIds  = [];
        $touch = function (int $deptId) use (&$deptMeta, &$deptIds) {
            if (!isset($deptMeta[$deptId])) {
                $deptMeta[$deptId] = ['contract_cnt' => 0, 'contract_amount' => 0.0, 'received' => 0.0, 'overdue' => 0.0, 'overdue_cnt' => 0, 'pending' => 0];
                $deptIds[$deptId]  = true;
            }
        };
        foreach ($newContracts as $c) {
            $touch((int)$c['dept_id']);
            $deptMeta[(int)$c['dept_id']]['contract_cnt']++;
            $deptMeta[(int)$c['dept_id']]['contract_amount'] += (float)$c['amount'];
        }
        foreach ($payRows as $r) {
            $touch((int)$r['dept_id']);
            $deptMeta[(int)$r['dept_id']]['received'] += (float)$r['paid_amount'];
        }
        foreach ($ovdRows as $r) {
            $touch((int)$r['dept_id']);
            $deptMeta[(int)$r['dept_id']]['overdue'] += (float)$r['amount'];
            $deptMeta[(int)$r['dept_id']]['overdue_cnt']++;
        }
        foreach ($pendingRows as $r) {
            $touch((int)$r['dept_id']);
            $deptMeta[(int)$r['dept_id']]['pending']++;
        }

        // 部门名称（department 表 + 未分配兜底）
        $deptName = [];
        if ($deptIds) {
            $rows = Db::name('department')->whereIn('id', array_keys($deptIds))->field('id, name')->select()->toArray();
            foreach ($rows as $d) {
                $deptName[(int)$d['id']] = $d['name'];
            }
        }
        $departments = [];
        foreach ($deptMeta as $deptId => $m) {
            $departments[] = [
                'dept_id'         => $deptId,
                'dept_name'       => $deptId > 0 ? (trim((string)($deptName[$deptId] ?? '')) !== '' ? $deptName[$deptId] : '未命名部门') : '未分配部门',
                'contract_cnt'    => $m['contract_cnt'],
                'contract_amount' => round($m['contract_amount'], 2),
                'received'        => round($m['received'], 2),
                'overdue'         => round($m['overdue'], 2),
                'overdue_cnt'     => $m['overdue_cnt'],
                'pending'         => $m['pending'],
            ];
        }
        usort($departments, function ($a, $b) {
            return $b['contract_amount'] <=> $a['contract_amount'];
        });

        // ---- 上周新增合同明细（供页面/消息展示，含部门名）----
        foreach ($newContracts as &$c) {
            $c['dept_name'] = (int)$c['dept_id'] > 0
                ? (trim((string)($deptName[(int)$c['dept_id']] ?? '')) !== '' ? $deptName[(int)$c['dept_id']] : '未命名部门')
                : '未分配部门';
        }
        unset($c);

        // ---- 逾期明细（含部门名）----
        foreach ($ovdRows as &$o) {
            $o['dept_name'] = (int)$o['dept_id'] > 0
                ? (trim((string)($deptName[(int)$o['dept_id']] ?? '')) !== '' ? $deptName[(int)$o['dept_id']] : '未命名部门')
                : '未分配部门';
        }
        unset($o);

        return [
            'start'           => $start,
            'end'             => $end,
            'summary'         => [
                'contract_cnt'    => $contractCnt,
                'contract_amount' => round($contractAmt, 2),
                'received'        => round($received, 2),
                'overdue'         => round($overdueAmt, 2),
                'overdue_cnt'     => $overdueCnt,
                'pending'         => $pendingCnt,
            ],
            'departments'     => $departments,
            'new_contracts'   => $newContracts,
            'overdue_payments'=> $ovdRows,
        ];
    }

    /**
     * 站内信推送内容（带摘要；钉钉仅发极简提示，不调用此方法）
     */
    public static function summaryMarkdown(array $data): string
    {
        $s = $data['summary'];
        $lines = [];
        $lines[] = "📊 经营周报（{$data['start']} ~ {$data['end']}）";
        $lines[] = "新增合同 {$s['contract_cnt']} 份 / ¥" . number_format((float)$s['contract_amount'], 0);
        $lines[] = "上周回款 ¥" . number_format((float)$s['received'], 0);
        $lines[] = "当前逾期 ¥" . number_format((float)$s['overdue'], 0) . "（{$s['overdue_cnt']} 笔）";
        $lines[] = "待审批 {$s['pending']} 笔";
        foreach ($data['departments'] as $d) {
            $part = "· {$d['dept_name']}：新增{$d['contract_cnt']}份/¥" . number_format((float)$d['contract_amount'], 0)
                . "，回款¥" . number_format((float)$d['received'], 0);
            if ($d['overdue_cnt'] > 0) {
                $part .= "，逾期¥" . number_format((float)$d['overdue'], 0);
            }
            $lines[] = $part;
        }
        $lines[] = '点击查看完整周报（合同可点开详情）';
        return implode("\n", $lines);
    }
}
