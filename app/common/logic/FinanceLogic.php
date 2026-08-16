<?php
namespace app\common\logic;

use think\facade\Db;
use app\common\logic\AuthLogic;

/**
 * 财务收支概览逻辑（Phase 1.2：从 Mobile/Desktop 两端提取统一汇总，消除重复与 Db 直查）
 */
class FinanceLogic
{
    /**
     * 收支概览：按合同方向（销售=应收/收款，采购=应付/付款）汇总金额与笔数。
     * 统一数据权限收敛（SELF/DEPT 仅见自身范围），与驾驶舱/列表口径一致，杜绝越权看全公司收支。
     *
     * @return array ['sales'=>['total'=>float,'cnt'=>int], 'purchase'=>['total'=>float,'cnt'=>int]]
     */
    public static function getSummary(): array
    {
        $finQuery = Db::name('contract')->where('is_deleted', 0)
            ->where('trade_attr', 1)
            ->where('direction', 'in', ['sales', 'purchase'])
            // P1-3：与 getSummaryByPeriod 口径一致，经营聚合排除未生效状态（草稿/驳回/审批中）
            ->where('status', 'not in', [ContractLogic::STATUS_DRAFT, ContractLogic::STATUS_REJECTED, ContractLogic::STATUS_PENDING_APPROVAL]);
        // 兼容旧版本函数调用；轻量合同管理下不会排除任何合同。
        \exclude_framework_contracts($finQuery);
        // 数据权限收敛：管理员看全部，SELF/DEPT 仅见范围内
        AuthLogic::appendDataScope($finQuery, 'owner_id', 'dept_id');
        $rows = $finQuery->field('direction, SUM(amount) AS total, COUNT(*) AS cnt')
            ->group('direction')->select()->toArray();
        $sum = ['sales' => ['total' => 0.0, 'cnt' => 0], 'purchase' => ['total' => 0.0, 'cnt' => 0]];
        foreach ($rows as $r) {
            if (isset($sum[$r['direction']])) {
                $sum[$r['direction']] = ['total' => (float)$r['total'], 'cnt' => (int)$r['cnt']];
            }
        }
        return $sum;
    }

    /**
     * 收支概览（按周期筛选版）：仅统计「周期内新增」的交易合同（created_at 落周期）按方向汇总，
     * 与 getMobileSummaryByPeriod 的「本期新增合同」口径一致；period=all 时等价于 getSummary（累计）。
     * @param string $period month|quarter|year|all
     * @return array ['sales'=>['total'=>float,'cnt'=>int], 'purchase'=>['total'=>float,'cnt'=>int], 'period_label'=>string]
     */
    public static function getSummaryByPeriod(string $period): array
    {
        $range = period_range($period);
        $labelMap = ['month' => '本月', 'quarter' => '本季', 'year' => '本年'];
        $finQuery = Db::name('contract')->where('is_deleted', 0)
            ->where('trade_attr', 1)
            ->where('direction', 'in', ['sales', 'purchase'])
            // P1-3：经营聚合排除未生效状态（草稿/驳回/审批中）
            ->where('status', 'not in', [ContractLogic::STATUS_DRAFT, ContractLogic::STATUS_REJECTED, ContractLogic::STATUS_PENDING_APPROVAL]);
        // 兼容旧版本函数调用；轻量合同管理下不会排除任何合同。
        \exclude_framework_contracts($finQuery);
        if ($range !== null) {
            [$start, $end] = $range;
            $finQuery->where('created_at', '>=', $start)->where('created_at', '<=', $end);
        }
        AuthLogic::appendDataScope($finQuery, 'owner_id', 'dept_id');
        $rows = $finQuery->field('direction, SUM(amount) AS total, COUNT(*) AS cnt')
            ->group('direction')->select()->toArray();
        $sum = ['sales' => ['total' => 0.0, 'cnt' => 0], 'purchase' => ['total' => 0.0, 'cnt' => 0]];
        foreach ($rows as $r) {
            if (isset($sum[$r['direction']])) {
                $sum[$r['direction']] = ['total' => (float)$r['total'], 'cnt' => (int)$r['cnt']];
            }
        }
        $sum['period_label'] = $range === null ? '累计' : ($labelMap[$period] ?? '累计');
        $sum['period']       = $range === null ? 'all' : $period;
        return $sum;
    }

    /**
     * 回款计划分页列表（P1-7：从 FinanceController 直查下沉，保持单一口径来源）
     * 统一数据权限收敛（SELF/DEPT 仅见范围内），分页与排序参数由控制器校验后传入，
     * 返回结构与 layui_table 所需一致。
     * @param int    $page      页码（已校验 >=1）
     * @param int    $pageSize  每页条数（已校验 1~100）
     * @param string $sortField 排序字段表达式（白名单校验后）
     * @param string $sortOrder 'asc' | 'desc'
     * @return array ['list' => array, 'total' => int]
     */
    public static function getPaymentList(int $page, int $pageSize, string $sortField, string $sortOrder, string $kw = '', string $paymentType = ''): array
    {
        $q = Db::name('payment_record')->alias('p')
            ->join('contract c', 'p.contract_id = c.id')
            ->field('p.*, c.contract_no, c.title as contract_title, c.status as contract_status');
        // 数据权限收敛：按合同归属人/部门过滤，管理员看全部
        AuthLogic::appendDataScope($q, 'c.owner_id', 'c.dept_id');
        $q->where('c.is_deleted', 0);
        // 按发票号搜索（v2.38.3：回款发票关联）
        if ($kw !== '') {
            $q->where('p.invoice_no', 'like', '%' . $kw . '%');
        }
        // v2.40.0 P1-4：按收/付类型过滤（财务中心回款/付款管理两个 tab 复用同一接口）
        if ($paymentType !== '') {
            $q->where('p.payment_type', $paymentType);
        }

        $total = $q->count();
        $list  = $q->order($sortField, $sortOrder)->page($page, $pageSize)->select()->toArray();
        return ['list' => $list, 'total' => $total];
    }

    /**
     * 发票分页列表（P1-7：从 FinanceController 直查下沉）
     * 统一数据权限收敛，分页/排序参数由控制器校验后传入，返回结构与 layui_table 所需一致。
     * @param int    $page      页码
     * @param int    $pageSize  每页条数
     * @param string $sortField 排序字段表达式（白名单校验后）
     * @param string $sortOrder 'asc' | 'desc'
     * @return array ['list' => array, 'total' => int]
     */
    public static function getInvoiceList(int $page, int $pageSize, string $sortField, string $sortOrder): array
    {
        $q = Db::name('contract_invoice')->alias('i')
            ->join('contract c', 'i.contract_id = c.id')
            ->field('i.*, c.contract_no, c.title as contract_title');
        AuthLogic::appendDataScope($q, 'c.owner_id', 'c.dept_id');
        $q->where('c.is_deleted', 0);

        $total = $q->count();
        $list  = $q->order($sortField, $sortOrder)->page($page, $pageSize)->select()->toArray();
        return ['list' => $list, 'total' => $total];
    }

    /**
     * 税务汇总数据（P1-7：从 FinanceController 直查下沉）
     * 仅统计已开票发票，关联合同方向：sales=销项 / purchase=进项；按月 + 方向汇总金额与税额。
     * 跨库兼容的「按月」格式化走全局辅助 db_month_expr()。
     * @return array 组织为 [{ym, output:{amount,tax}, input:{amount,tax}, payable}, ...]
     */
    public static function getTaxSummary(): array
    {
        $ymExpr = db_month_expr('i.created_at');
        $q = Db::name('contract_invoice')->alias('i')
            ->join('contract c', 'i.contract_id = c.id')
            ->where('c.is_deleted', 0)
            ->where('c.direction', 'in', ['sales', 'purchase'])
            // P1-5：仅统计已开票（ISSUED）与红冲（RED，负向冲抵）发票，排除作废(VOID)/驳回/撤回等未生效状态，
            // 与 InvoiceLogic::sumCommitted「VOID 不占开票额度」口径一致，避免作废发票虚增销项/进项与应纳税额
            ->where('i.status', 'in', [\app\common\logic\InvoiceLogic::STATUS_ISSUED, \app\common\logic\InvoiceLogic::STATUS_RED])
            ->field("{$ymExpr} AS ym, c.direction, SUM(i.amount) AS amount, SUM(i.tax_amount) AS tax")
            ->group('ym, c.direction')
            ->order('ym', 'desc');
        AuthLogic::appendDataScope($q, 'c.owner_id', 'c.dept_id');
        $rows = $q->select()->toArray();

        // 组织成 { 月份: { output(销项), input(进项) } } 结构
        $months = [];
        foreach ($rows as $r) {
            $ym = $r['ym'] ?: '未知';
            if (!isset($months[$ym])) {
                $months[$ym] = [
                    'ym'     => $ym,
                    'output' => ['amount' => 0, 'tax' => 0], // 销项
                    'input'  => ['amount' => 0, 'tax' => 0], // 进项
                ];
            }
            $key = $r['direction'] === 'sales' ? 'output' : 'input';
            $months[$ym][$key] = ['amount' => (float)$r['amount'], 'tax' => (float)$r['tax']];
        }
        // 应纳税额 = 销项税额 - 进项税额
        $result = [];
        foreach ($months as $m) {
            $m['payable'] = round($m['output']['tax'] - $m['input']['tax'], 2);
            $result[] = $m;
        }
        return $result;
    }

    /** v2.38.3 未来N天预计回款总额 */
    public static function getForecast(int $days): float
    {
        $endDate = date('Y-m-d', strtotime("+{$days} days"));
        $q = Db::name('payment_record')->alias('p')
            ->join('contract c', 'p.contract_id = c.id')
            ->where('p.status', 'PENDING')
            ->where('p.planned_date', '>=', date('Y-m-d'))
            ->where('p.planned_date', '<=', $endDate)
            ->where('c.is_deleted', 0);
        AuthLogic::appendDataScope($q, 'c.owner_id', 'c.dept_id');
        return (float)($q->sum('p.amount') ?: 0);
    }
}
