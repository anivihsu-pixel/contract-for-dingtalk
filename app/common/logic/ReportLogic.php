<?php
// +----------------------------------------------------------------------
// | 经营报表聚合逻辑（驾驶舱汇总 + 经营月报）
// | 口径与 DashboardController / FinanceController 一致，便于导出复用
// +----------------------------------------------------------------------

namespace app\common\logic;

use think\facade\Cache;
use think\facade\Db;

class ReportLogic
{
    /**
     * 回款基础查询（关联交易合同 trade_attr=1，按数据范围收敛）
     * @param array $user 当前登录用户（用于数据权限）
     */
    private static function payScope(array $user)
    {
        // P1-3：回款口径与合同额侧（dir 收支方向）对齐——排除未生效合同关联的回款记录 + 排除框架合同
        $q = Db::name('payment_record')->alias('p')
            ->join('contract c', 'p.contract_id = c.id')
            ->where('c.is_deleted', 0)
            ->where('c.trade_attr', 1)
            ->where('c.status', 'not in', [ContractLogic::STATUS_DRAFT, ContractLogic::STATUS_REJECTED, ContractLogic::STATUS_PENDING_APPROVAL]);
        \exclude_framework_contracts($q, 'c');
        if (empty($user['is_admin'])) {
            AuthLogic::appendDataScope($q, 'c.owner_id', 'c.dept_id');
        }
        return $q;
    }

    /**
     * 驾驶舱经营概览（P1-7：与 DashboardController::index 共用单一口径来源，消除直查重复聚合）
     * 汇总仪表盘所需全部指标：合同/回款/收支方向/客户/供应商/审批/到期/趋势/近期列表。
     * 数字口径与移动端 getMobileSummary()、合同详情页保持一致（已收统一用 paid_amount，P0-1）。
     * v2.38.17：新增 $period 时间维度（month/quarter/year/all）——合同按 effective_date（生效日）
     * 过滤、回款按 planned_date/actual_date 过滤；'all'（累计）行为与原版完全一致。
     * 注：本方法内部统一经 AuthLogic::appendDataScope 收敛数据权限，key 以用户隔离缓存即可防串数据。
     * @param array $user 当前登录用户（用于数据权限收敛，含 is_admin / id）
     * @param string $period 时间周期：month|quarter|year|all（默认 all=累计）
     * @return array 键名与 app/view/dashboard/index.php 所用变量一一对应
     */
    public static function dashboardSummary(array $user, string $period = 'all'): array
    {
        $isAdmin = !empty($user['is_admin']);
        $userId  = (int)($user['id'] ?? 0);
        // 周期范围（all=null 表示累计不过滤；合同按生效日、回款按计划/实收日过滤）
        $range = period_range($period);
        $contractDateFn = null;
        if ($range !== null) {
            [$cStart, $cEnd] = $range;
            $contractDateFn = function ($q) use ($cStart, $cEnd) {
                $q->where('effective_date', '>=', $cStart)->where('effective_date', '<=', $cEnd);
            };
        }

        // === 合同基础范围（统一数据权限：本人/部门/全部） ===
        $baseQuery = Db::name('contract')->where('is_deleted', 0);
        AuthLogic::appendDataScope($baseQuery, 'owner_id', 'dept_id');
        if ($contractDateFn) $contractDateFn($baseQuery);

        // P1-3：合同总额/经营额口径收敛——仅统计「已生效交易合同」
        // （排除草稿/驳回/审批中 + 排除框架合同预算上限），与收支方向/部门经营/财务中心口径一致
        $tradeBase = (clone $baseQuery)->where('trade_attr', 1)
            ->where('status', 'not in', [ContractLogic::STATUS_DRAFT, ContractLogic::STATUS_REJECTED, ContractLogic::STATUS_PENDING_APPROVAL]);
        \exclude_framework_contracts($tradeBase);
        $totalContracts = (clone $tradeBase)->count();
        $totalAmount    = (clone $tradeBase)->sum('amount') ?? 0;

        // 合同状态计数（10 态对齐，单一口径来源）
        // v2.47.1 修复：状态分布/待审批/生效合同数/即将到期均为「时点快照」指标，
        // 须用不含 effective_date 周期过滤的累计查询（仅数据权限范围）——与 getMobileSummaryByPeriod
        // 文档化设计一致（周期外指标保持累计口径），修复 period=month/quarter/year 下
        // 状态分布卡空白、待审批/生效合同数恒 0 的问题（此前误复用被周期过滤的 $baseQuery）
        $snapBase = Db::name('contract')->where('is_deleted', 0);
        AuthLogic::appendDataScope($snapBase, 'owner_id', 'dept_id');
        $statusCounts    = self::statusCountMap($snapBase);
        $pendingApproval = $statusCounts['PENDING_APPROVAL'] ?? 0;
        // 生效合同口径：审批通过后进入执行的合同。
        $signedContracts = $statusCounts['EXECUTING'] ?? 0;

        // 30 天内即将到期（EXECUTING，时点快照累计口径）
        $expiringSoon = (clone $snapBase)
            ->where('status', 'EXECUTING')
            ->where('expiry_date', '<=', date('Y-m-d', strtotime('+30 days')))
            ->where('expiry_date', '>=', date('Y-m-d'))
            ->count();

        // 最近合同（列表，供仪表盘直接展示）——不带周期过滤，始终展示最新 8 条
        $recentContracts = Db::name('contract')->where('is_deleted', 0);
        AuthLogic::appendDataScope($recentContracts, 'owner_id', 'dept_id');
        $recentContracts = $recentContracts->order('id', 'desc')->limit(8)->select()->toArray();

        // === 回款基础范围（关联交易合同 trade_attr=1，按数据范围收敛） ===
        $pay = self::payScope($user);
        // v2.38.17：周期过滤——应收/已收/待收/逾期按计划日/实收日落入周期
        if ($range !== null) {
            [$cStart, $cEnd] = $range;
            $pay->where(function ($q) use ($cStart, $cEnd) {
                $q->where(function ($q2) use ($cStart, $cEnd) {
                    // 未收（PENDING/OVERDUE）：按计划日
                    $q2->whereIn('p.status', ['PENDING', 'OVERDUE'])->where('p.planned_date', '>=', $cStart)->where('p.planned_date', '<=', $cEnd);
                })->whereOr(function ($q3) use ($cStart, $cEnd) {
                    // 已收（PAID）：按实收日
                    $q3->where('p.status', 'PAID')->where('p.actual_date', '>=', $cStart)->where('p.actual_date', '<=', $cEnd);
                });
            });
        }

        $totalReceivable = (float)((clone $pay)->where('p.payment_type', 'RECEIVABLE')->sum('p.amount') ?? 0);
        // P0-1：已收口径统一用 paid_amount（实际确认金额），避免部分确认场景下用 amount 高估
        $receivedAmount  = (float)((clone $pay)->where('p.payment_type', 'RECEIVABLE')->where('p.status', 'PAID')->sum('p.paid_amount') ?? 0);
        $pendingAmount   = (float)((clone $pay)->where('p.payment_type', 'RECEIVABLE')->where('p.status', 'PENDING')->sum('p.amount') ?? 0);
        $overdueAmount   = (float)((clone $pay)->where('p.payment_type', 'RECEIVABLE')->where('p.status', 'OVERDUE')->sum('p.amount') ?? 0);
        $overdueCount    = (clone $pay)->where('p.payment_type', 'RECEIVABLE')->where('p.status', 'OVERDUE')->count();
        $recoveryRate    = $totalReceivable > 0 ? round($receivedAmount / $totalReceivable * 100, 1) : 0;

        // 本月/周期（预计回款 / 已收）按数据范围收敛
        // v2.38.17：period 非 all 时"周期经营"跟随所选周期（$pay 已按周期过滤，直接聚合即可）；
        // period=all（累计）时保持原「本月」口径不变（与移动端/历史一致）
        $thisMonthStart = date('Y-m-01');
        $thisMonthEnd   = date('Y-m-t');
        if ($range !== null) {
            // 周期模式：$pay 已按 planned/actual 过滤，取 PENDING 合计为预计、PAID 合计为已收
            $monthExpected = (float)((clone $pay)->where('p.payment_type', 'RECEIVABLE')->where('p.status', 'PENDING')
                ->sum('p.amount') ?? 0);
            $monthReceived = (float)((clone $pay)->where('p.payment_type', 'RECEIVABLE')->where('p.status', 'PAID')
                ->sum('p.paid_amount') ?? 0);
        } else {
            $monthExpected = (float)((clone $pay)->where('p.payment_type', 'RECEIVABLE')->where('p.status', 'PENDING')
                ->where('p.planned_date', '>=', $thisMonthStart)->where('p.planned_date', '<=', $thisMonthEnd)
                ->sum('p.amount') ?? 0);
            $monthReceived = (float)((clone $pay)->where('p.payment_type', 'RECEIVABLE')->where('p.status', 'PAID')
                ->where('p.actual_date', '>=', $thisMonthStart)->where('p.actual_date', '<=', $thisMonthEnd)
                ->sum('p.paid_amount') ?? 0);
        }

        // 收支方向（销售=我方收款/应收，采购=我方付款/应付，仅交易合同；v2.38.17 按周期过滤生效日）
        // P1-3：排除未生效状态（草稿/驳回/审批中），避免未生效合同金额计入收支方向
        $dirBase = Db::name('contract')->where('is_deleted', 0)
            ->where('trade_attr', 1)
            ->where('direction', 'in', ['sales', 'purchase'])
            ->where('status', 'not in', [ContractLogic::STATUS_DRAFT, ContractLogic::STATUS_REJECTED, ContractLogic::STATUS_PENDING_APPROVAL]);
        // P1-3：收支方向同样排除框架合同预算上限
        \exclude_framework_contracts($dirBase);
        if ($contractDateFn) $contractDateFn($dirBase);
        if (!$isAdmin) {
            AuthLogic::appendDataScope($dirBase, 'owner_id', 'dept_id');
        }
        $dirRows = $dirBase->field('direction, SUM(amount) AS total, COUNT(*) AS cnt')
            ->group('direction')->select()->toArray();
        $dir = ['sales' => ['total' => 0.0, 'cnt' => 0], 'purchase' => ['total' => 0.0, 'cnt' => 0]];
        foreach ($dirRows as $r) {
            $key = ($r['direction'] === 'purchase') ? 'purchase' : 'sales';
            $dir[$key]['total'] += (float)$r['total'];
            $dir[$key]['cnt']   += (int)$r['cnt'];
        }

        // 客户 / 供应商（数据权限作用域）
        $custQuery = Db::name('customer')->where('is_deleted', 0);
        AuthLogic::appendDataScope($custQuery, 'owner_id', 'dept_id');
        $totalCustomers = $custQuery->count();
        $suppQuery = Db::name('supplier')->where('is_deleted', 0);
        AuthLogic::appendDataScope($suppQuery, 'owner_id', 'dept_id');
        $totalSuppliers = $suppQuery->count();

        // 待我审批（按审批人精确匹配，不走数据范围）
        $pendingCount = Db::name('approval_record')
            ->where('approver_id', $userId)->where('action', 'PENDING')->count();

        // 近6季度合同趋势（v2.39.0：月度改季度——平滑单月噪声，贴合活动外包按项目周期汇报；合同生效金额 + 已收回款，双序列）
        $trend = [];
        $curQ  = (int)ceil((int)date('n') / 3);   // 当前季度 1-4
        $curY  = (int)date('Y');
        for ($i = 5; $i >= 0; $i--) {
            $qNum  = $curQ - $i;                  // 回退 i 个季度
            $qYear = $curY;
            while ($qNum <= 0) { $qNum += 4; $qYear--; }
            $qStartMonth = ($qNum - 1) * 3 + 1;
            $qStart = sprintf('%04d-%02d-01', $qYear, $qStartMonth);
            $qEnd   = date('Y-m-t', strtotime(sprintf('%04d-%02d-01', $qYear, $qStartMonth + 2)));
            // P1-3：趋势合同额口径与 dir 收支方向对齐——排除未生效状态 + 排除框架合同预算上限
            $q = Db::name('contract')->where('is_deleted', 0)->where('trade_attr', 1)
                ->where('effective_date', '>=', $qStart)->where('effective_date', '<=', $qEnd)
                ->where('status', 'not in', [ContractLogic::STATUS_DRAFT, ContractLogic::STATUS_REJECTED, ContractLogic::STATUS_PENDING_APPROVAL]);
            \exclude_framework_contracts($q);
            if (!$isAdmin) {
                AuthLogic::appendDataScope($q, 'owner_id', 'dept_id');
            }
            $amt = (float)($q->sum('amount') ?? 0);
            // 同期已收（按实收日；口径与合同额侧对齐：交易合同 + 排除未生效 + 排除框架合同）
            $rq = Db::name('payment_record')->alias('p')->join('contract c', 'p.contract_id = c.id')
                ->where('c.is_deleted', 0)->where('c.trade_attr', 1)->where('p.payment_type', 'RECEIVABLE')->where('p.status', 'PAID')
                ->where('c.status', 'not in', [ContractLogic::STATUS_DRAFT, ContractLogic::STATUS_REJECTED, ContractLogic::STATUS_PENDING_APPROVAL])
                ->where('p.actual_date', '>=', $qStart)->where('p.actual_date', '<=', $qEnd);
            \exclude_framework_contracts($rq, 'c');
            if (!$isAdmin) {
                AuthLogic::appendDataScope($rq, 'c.owner_id', 'c.dept_id');
            }
            $recv = (float)($rq->sum('p.paid_amount') ?? 0);
            $trend[] = ['ym' => $qStart, 'month' => sprintf('%02dQ%d', $qYear % 100, $qNum), 'amount' => $amt, 'received' => $recv];
        }

        // 近期回款计划（未来30天，列表供仪表盘展示；与原仪表盘直查口径保持一致，不叠加 trade_attr）
        $upcoming = Db::name('payment_record')->alias('p')
            ->join('contract c', 'p.contract_id = c.id')
            ->field('p.*, c.contract_no, c.title as contract_title')
            ->where('c.is_deleted', 0)
            ->where('p.payment_type', 'RECEIVABLE')
            ->where('p.status', 'PENDING')
            ->where('p.planned_date', '>=', date('Y-m-d'))
            ->where('p.planned_date', '<=', date('Y-m-d', strtotime('+30 days')));
        if (!$isAdmin) {
            AuthLogic::appendDataScope($upcoming, 'c.owner_id', 'c.dept_id');
        }
        $upcomingPayments = $upcoming->order('p.planned_date', 'asc')->limit(8)->select()->toArray();

        // 需求(2026-08-01)：近期回款以合同标题为主展示。join 别名在个别场景（旧缓存/字段缺失）可能为空，
        // 统一兜底归一化：contract_title = title 优先，其次合同编号，保证仪表盘主标题始终有值。
        foreach ($upcomingPayments as &$up) {
            $up['contract_title'] = trim((string)($up['contract_title'] ?? '')) !== ''
                ? $up['contract_title']
                : (trim((string)($up['title'] ?? '')) !== '' ? $up['title'] : ($up['contract_no'] ?? ''));
        }
        unset($up);

        return [
            'total_contracts'  => $totalContracts,
            'total_amount'     => $totalAmount,
            'status_counts'    => $statusCounts,
            'pending_approval' => $pendingApproval,
            'signed_contracts' => $signedContracts,
            'expiring_soon'    => $expiringSoon,
            'recent_contracts' => $recentContracts,
            'total_receivable' => $totalReceivable,
            'received_amount'  => $receivedAmount,
            'pending_amount'   => $pendingAmount,
            'overdue_amount'   => $overdueAmount,
            'overdue_count'    => $overdueCount,
            'recovery_rate'    => $recoveryRate,
            'month_expected'   => $monthExpected,
            'month_received'   => $monthReceived,
            'dir'              => $dir,
            'trend'            => $trend,
            'total_customers'  => $totalCustomers,
            'total_suppliers'  => $totalSuppliers,
            'pending_count'    => $pendingCount,
            'upcoming_payments' => $upcomingPayments,
            'period_label'     => $range === null ? '累计' : (['month' => '本月', 'quarter' => '本季', 'year' => '本年'][$period] ?? '累计'),
        ];
    }

    /**
     * 合同状态计数（REV-20：统计收口）
     * 统一由本方法产出 10 态计数映射，驾驶舱与报表复用，避免口径分散。
     * @param \think\db\Query $baseQuery 已套数据权限的合同基础查询
     */
    public static function statusCountMap($baseQuery): array
    {
        $all = ['DRAFT','PENDING_APPROVAL','REJECTED',
                'EXECUTING','COMPLETED','TERMINATED','EXPIRED','ARCHIVED'];
        $map = array_fill_keys($all, 0);
        $rows = (clone $baseQuery)
            ->field('status, COUNT(*) AS cnt')
            ->group('status')
            ->select()->toArray();
        foreach ($rows as $r) {
            $map[$r['status']] = (int)$r['cnt'];
        }
        return $map;
    }

    /**
     * 移动端报表概览（Phase 1.3：合并 6 类统计为统一聚合，复用 dashboardSummary/statusCountMap/payScope，消除 Db 直查）
     * @param array $user 当前登录用户（用于数据权限收敛）
     * @return array 键名与 mobile/reports 视图变量一一对应
     */
    public static function getMobileSummary(array $user): array
    {
        // P3-7【m-Pf1】移动报表加缓存（与驾驶舱 120s 对齐），按用户隔离数据权限作用域，避免每次请求重算
        $cacheKey = 'mobile_summary_' . ((int)($user['id'] ?? 0));
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }
        // 合同基础范围（统一数据权限）
        $baseQuery = Db::name('contract')->where('is_deleted', 0);
        AuthLogic::appendDataScope($baseQuery, 'owner_id', 'dept_id');
        $totalContracts = $baseQuery->count();
        // P1-3：经营总额口径与驾驶舱一致——仅统计「已生效交易合同」（排除草稿/驳回/审批中 + 排除框架合同预算上限）
        $tradeBase = (clone $baseQuery)->where('trade_attr', 1)
            ->where('status', 'not in', [ContractLogic::STATUS_DRAFT, ContractLogic::STATUS_REJECTED, ContractLogic::STATUS_PENDING_APPROVAL]);
        \exclude_framework_contracts($tradeBase);
        $totalAmount    = $tradeBase->sum('amount') ?? 0;
        $statusCounts   = self::statusCountMap($baseQuery);

        // 回款概览 + 收支方向（复用驾驶舱聚合，避免重复统计）
        $dash = self::dashboardSummary($user);
        $pay  = self::payScope($user);
        $overdueCount = (clone $pay)
            ->where('p.payment_type', 'RECEIVABLE')
            ->where('p.status', 'OVERDUE')
            ->count();

        // 客户 / 供应商（S1/S2：数据权限作用域）
        $custQuery = Db::name('customer')->where('is_deleted', 0);
        AuthLogic::appendDataScope($custQuery, 'owner_id', 'dept_id');
        $totalCustomers = $custQuery->count();
        $supQuery = Db::name('supplier')->where('is_deleted', 0);
        AuthLogic::appendDataScope($supQuery, 'owner_id', 'dept_id');
        $totalSuppliers = $supQuery->count();

        // 30 天内即将到期（EXECUTING）
        $expiringSoon = (clone $baseQuery)
            ->where('status', 'EXECUTING')
            ->where('expiry_date', '<=', date('Y-m-d', strtotime('+30 days')))
            ->where('expiry_date', '>=', date('Y-m-d'))
            ->count();

        $result = [
            'total_contracts'  => $totalContracts,
            'total_amount'     => $totalAmount,
            'status_counts'    => $statusCounts,
            'pending_approval' => $statusCounts['PENDING_APPROVAL'] ?? 0,
            // 生效合同即执行中合同。
            'signed_contracts' => $statusCounts['EXECUTING'] ?? 0,
            'total_receivable' => $dash['total_receivable'],
            'received_amount'  => $dash['received_amount'],
            'pending_amount'   => $dash['pending_amount'],
            'overdue_amount'   => $dash['overdue_amount'],
            'overdue_count'    => $overdueCount,
            'recovery_rate'    => $dash['recovery_rate'],
            'dir_summary'      => $dash['dir'],
            'total_customers'  => $totalCustomers,
            'total_suppliers'  => $totalSuppliers,
            'expiring_soon'    => $expiringSoon,
        ];
        Cache::set($cacheKey, $result, 120);
        return $result;
    }

    /**
     * 移动端报表概览（按周期筛选版）：复用 getMobileSummary 的结构性指标（状态分布/客户/供应商/即将到期等时效口径不变），
     * 仅将「经营/回款/收支方向」等流量类指标按周期（本月/本季/本年）收敛，避免与累计值混淆。
     * 周期外指标（合同状态分布、基础数据、30 天即将到期）保持累计口径，因其本就不是「周期发生量」。
     * @param array  $user   当前登录用户（数据权限收敛）
     * @param string $period month|quarter|year|all（非法回退 all=累计）
     * @return array 键名与 mobile/reports 视图变量一致；额外带 period_label 供前端展示
     */
    public static function getMobileSummaryByPeriod(array $user, string $period): array
    {
        $range = period_range($period);   // [start, end] 或 null(累计)
        $base  = self::getMobileSummary($user);   // 复用累计版（含缓存）+ 结构性指标

        if ($range === null) {
            $base['period_label'] = '累计';
            $base['period']       = 'all';
            return $base;
        }
        [$start, $end] = $range;

        $pay = self::payScope($user);
        // 本期计划应收（RECEIVABLE，planned_date 落周期）
        $receivable = (float)((clone $pay)->where('p.payment_type', 'RECEIVABLE')
            ->where('p.planned_date', '>=', $start)->where('p.planned_date', '<=', $end)
            ->sum('p.amount') ?? 0);
        // 本期实际回款（RECEIVABLE 且 PAID，actual_date 落周期）
        $collected = (float)((clone $pay)->where('p.payment_type', 'RECEIVABLE')->where('p.status', 'PAID')
            ->where('p.actual_date', '>=', $start)->where('p.actual_date', '<=', $end)
            ->sum('p.paid_amount') ?? 0);
        // P2-12：本期未收 = 本期 PENDING+OVERDUE 状态金额之和（按计划日），
        // 避免「计划日在本期、实收日在下期」或反例导致的减法为负/虚增，与驾驶舱 pending 口径统一
        $pending = (float)((clone $pay)->where('p.payment_type', 'RECEIVABLE')
            ->whereIn('p.status', ['PENDING', 'OVERDUE'])
            ->where('p.planned_date', '>=', $start)->where('p.planned_date', '<=', $end)
            ->sum('p.amount') ?? 0);
        // 本期逾期（planned_date 落周期 且 状态 OVERDUE）
        $overdueAmount = (float)((clone $pay)->where('p.payment_type', 'RECEIVABLE')->where('p.status', 'OVERDUE')
            ->where('p.planned_date', '>=', $start)->where('p.planned_date', '<=', $end)
            ->sum('p.amount') ?? 0);
        $overdueCount = (clone $pay)->where('p.payment_type', 'RECEIVABLE')->where('p.status', 'OVERDUE')
            ->where('p.planned_date', '>=', $start)->where('p.planned_date', '<=', $end)->count();
        $recoveryRate = $receivable > 0 ? round($collected / $receivable * 100, 1) : 0;

        // 本期新增合同（created_at 落周期，仅交易合同 trade_attr=1）
        // P1-3：排除未生效状态（草稿/驳回/审批中）+ 排除框架合同预算上限，与 monthlyReport 收支方向口径一致
        $contractBase = Db::name('contract')->where('is_deleted', 0)
            ->where('trade_attr', 1)
            ->where('status', 'not in', [ContractLogic::STATUS_DRAFT, ContractLogic::STATUS_REJECTED, ContractLogic::STATUS_PENDING_APPROVAL])
            ->whereNotIn('id', \exclude_framework_contracts_ids())
            ->where('created_at', '>=', $start)->where('created_at', '<=', $end);
        if (empty($user['is_admin'])) {
            AuthLogic::appendDataScope($contractBase, 'owner_id', 'dept_id');
        }
        $totalContracts = $contractBase->count();
        $totalAmount    = $contractBase->where('trade_attr', 1)->sum('amount') ?? 0;
        // 本期收支方向（新增合同按方向）
        $dirRows = (clone $contractBase)->field('direction, SUM(amount) AS total, COUNT(*) AS cnt')
            ->group('direction')->select()->toArray();
        $dir = ['sales' => ['total' => 0.0, 'cnt' => 0], 'purchase' => ['total' => 0.0, 'cnt' => 0]];
        foreach ($dirRows as $r) {
            $key = ($r['direction'] === 'purchase') ? 'purchase' : 'sales';
            $dir[$key]['total'] += (float)$r['total'];
            $dir[$key]['cnt']   += (int)$r['cnt'];
        }

        $labelMap = ['month' => '本月', 'quarter' => '本季', 'year' => '本年'];
        return [
            'period'          => $period,
            'period_label'    => $labelMap[$period] ?? '累计',
            'total_contracts' => $totalContracts,
            'total_amount'    => $totalAmount,
            'status_counts'   => $base['status_counts'],
            'pending_approval' => $base['pending_approval'],
            'signed_contracts' => $base['signed_contracts'],
            'total_receivable' => $receivable,
            'received_amount' => $collected,
            'pending_amount'  => $pending,
            'overdue_amount'  => $overdueAmount,
            'overdue_count'   => $overdueCount,
            'recovery_rate'   => $recoveryRate,
            'dir_summary'     => $dir,
            'total_customers' => $base['total_customers'],
            'total_suppliers' => $base['total_suppliers'],
            'expiring_soon'   => $base['expiring_soon'],
        ];
    }

    /**
     * 指定月份经营月报
     * 口径：仅统计交易合同(trade_attr=1)；回款类指标基于 RECEIVABLE 回款计划；
     * 收支方向=当月新增合同(created_at 落在月内)按方向汇总。
     * @param string $ym YYYY-MM（非法值回退当前月）
     */
    public static function monthlyReport(array $user, string $ym): array
    {
        if (!preg_match('/^\d{4}-\d{2}$/', $ym)) {
            $ym = date('Y-m');
        }
        $start   = $ym . '-01';
        $end     = date('Y-m-t', strtotime($start)) . ' 23:59:59';
        $prevYm  = date('Y-m', strtotime($start . ' -1 month'));
        $prevStart = $prevYm . '-01';
        $prevEnd   = date('Y-m-t', strtotime($prevStart)) . ' 23:59:59';

        $pay = self::payScope($user);

        // 本月计划应收（RECEIVABLE，planned_date 落当月）
        $receivable = (float)((clone $pay)->where('p.payment_type', 'RECEIVABLE')
            ->where('p.planned_date', '>=', $start)
            ->where('p.planned_date', '<=', $end)
            ->sum('p.amount') ?? 0);
        // 本月实际回款（RECEIVABLE 且 PAID，actual_date 落当月）——P0-1：已收用 paid_amount
        $collected = (float)((clone $pay)->where('p.payment_type', 'RECEIVABLE')->where('p.status', 'PAID')
            ->where('p.actual_date', '>=', $start)
            ->where('p.actual_date', '<=', $end)
            ->sum('p.paid_amount') ?? 0);
        // 本月逾期（planned_date 落当月 且 状态 OVERDUE）
        $overdue = (float)((clone $pay)->where('p.payment_type', 'RECEIVABLE')->where('p.status', 'OVERDUE')
            ->where('p.planned_date', '>=', $start)
            ->where('p.planned_date', '<=', $end)
            ->sum('p.amount') ?? 0);
        $recoveryRate = $receivable > 0 ? round($collected / $receivable * 100, 1) : 0;
        // P2-12：应收未收（待收 + 逾期）= 本月 PENDING+OVERDUE 状态金额之和（按计划日），
        // 避免计划日/实收日跨期导致的减法为负，与驾驶舱 pending 口径统一
        $uncollected = (float)((clone $pay)->where('p.payment_type', 'RECEIVABLE')
            ->whereIn('p.status', ['PENDING', 'OVERDUE'])
            ->where('p.planned_date', '>=', $start)
            ->where('p.planned_date', '<=', $end)
            ->sum('p.amount') ?? 0);

        // 上月对比（环比）
        $prevReceivable = (float)((clone $pay)->where('p.payment_type', 'RECEIVABLE')
            ->where('p.planned_date', '>=', $prevStart)
            ->where('p.planned_date', '<=', $prevEnd)
            ->sum('p.amount') ?? 0);
        $prevCollected = (float)((clone $pay)->where('p.payment_type', 'RECEIVABLE')->where('p.status', 'PAID')
            ->where('p.actual_date', '>=', $prevStart)
            ->where('p.actual_date', '<=', $prevEnd)
            ->sum('p.paid_amount') ?? 0);

        // 收支方向：当月新增合同（created_at 落月内）按方向
        // P1-3：与驾驶舱口径一致，排除未生效状态（草稿/驳回/审批中），避免未生效合同金额计入收支方向
        $dirBase = Db::name('contract')->where('is_deleted', 0)
            ->where('trade_attr', 1)
            ->where('direction', 'in', ['sales', 'purchase'])
            ->where('status', 'not in', [ContractLogic::STATUS_DRAFT, ContractLogic::STATUS_REJECTED, ContractLogic::STATUS_PENDING_APPROVAL])
            // P1-3：月报收支方向排除框架合同预算上限
            ->whereNotIn('id', \exclude_framework_contracts_ids())
            ->where('created_at', '>=', $start)
            ->where('created_at', '<=', $end);
        if (empty($user['is_admin'])) {
            AuthLogic::appendDataScope($dirBase, 'owner_id', 'dept_id');
        }
        $dirRows = $dirBase->field('direction, SUM(amount) AS total, COUNT(*) AS cnt')
            ->group('direction')->select()->toArray();
        $dir = ['sales' => ['total' => 0.0, 'cnt' => 0], 'purchase' => ['total' => 0.0, 'cnt' => 0]];
        foreach ($dirRows as $r) {
            $key = ($r['direction'] === 'purchase') ? 'purchase' : 'sales';
            $dir[$key]['total'] += (float)$r['total'];
            $dir[$key]['cnt']   += (int)$r['cnt'];
        }

        return [
            'ym'              => $ym,
            'prev_ym'         => $prevYm,
            'receivable'      => $receivable,
            'collected'       => $collected,
            'overdue'         => $overdue,
            'uncollected'     => $uncollected,
            'recovery_rate'   => $recoveryRate,
            'prev_receivable' => $prevReceivable,
            'prev_collected'  => $prevCollected,
            'dir'             => $dir,
        ];
    }

    /** v2.38.3 应收账龄分析：按逾期天数分组统计 */
    public static function agingReport(array $user): array
    {
        $today = date('Y-m-d');
        $buckets = ['0-30天'=>[0,30], '31-60天'=>[31,60], '61-90天'=>[61,90], '90天以上'=>[91,99999]];
        $result = [];
        foreach ($buckets as $label => [$from, $to]) {
            $fromDate = date('Y-m-d', strtotime("-{$to} days"));
            $toDate = date('Y-m-d', strtotime("-{$from} days"));
            $q = Db::name('payment_record')->alias('p')
                ->join('contract c', 'p.contract_id = c.id')
                ->where('p.status', 'OVERDUE')
                ->where('p.planned_date', '>=', $fromDate)
                ->where('p.planned_date', '<=', $toDate)
                ->where('c.is_deleted', 0);
            \app\common\logic\AuthLogic::appendDataScope($q, 'c.owner_id', 'c.dept_id');
            $rows = $q->field('c.title, p.amount, p.planned_date, c.owner_id')
                ->order('p.planned_date')->select()->toArray();
            $result[] = ['label'=>$label, 'count'=>count($rows), 'total'=>array_sum(array_column($rows,'amount')), 'items'=>$rows];
        }
        return $result;
    }

    /**
     * 按部门经营汇总（v2.39.0：驾驶舱「部门经营」卡片，管理层概览用，非排行榜）
     * 累计口径（trade_attr=1 交易合同）：合同数/合同额/已收回款/回款率，按合同归属部门 dept_id 聚合。
     * 仅管理员有数据（普通用户返回空数组，前端不渲染）；按合同额降序便于管理层看业务分布。
     * @param array $user   当前登录用户
     * @param int|null $deptId 指定部门时返回该部门汇总（调用方负责权限，部门经理看本部门）
     * @param bool $allowAll 允许查看全公司汇总（管理层角色判定由调用方负责：is_admin 或 gm/admin 角色）
     * @return array [{dept_id, dept_name, cnt, total_amount, paid_amount, recovery_rate}]
     */
    public static function deptSummary(array $user, ?int $deptId = null, bool $allowAll = false): array
    {
        // 不指定部门时仅管理层可见全公司汇总；指定部门时由调用方负责权限（部门经理看本部门）
        if ($deptId === null && empty($user['is_admin']) && !$allowAll) {
            return [];
        }
        $q = Db::name('contract')->alias('c')
            ->leftJoin('department d', 'c.dept_id = d.id')
            ->where('c.is_deleted', 0)->where('c.trade_attr', 1)->where('c.dept_id', '>', 0)
            // P1-3：经营聚合排除未生效状态（草稿/驳回/审批中），与 deptMembers 口径一致
            ->where('c.status', 'not in', [ContractLogic::STATUS_DRAFT, ContractLogic::STATUS_REJECTED, ContractLogic::STATUS_PENDING_APPROVAL])
            // P1-3：部门经营排除框架合同预算上限
            ->whereNotIn('c.id', \exclude_framework_contracts_ids());
        if ($deptId !== null) {
            $q->where('c.dept_id', $deptId);
        }
        $rows = $q->field('c.dept_id, d.name as dept_name, count(*) as cnt, sum(c.amount) as total_amount')
            ->group('c.dept_id')
            ->order('total_amount', 'desc')
            ->select()->toArray();
        if (!$rows) {
            return [];
        }
        // 部门已收（按实收日，仅 PAID 记录，口径与驾驶舱一致）
        $paidQ = Db::name('payment_record')->alias('p')
            ->join('contract c', 'p.contract_id = c.id')
            ->where('c.is_deleted', 0)->where('c.trade_attr', 1)
            ->where('p.payment_type', 'RECEIVABLE')->where('p.status', 'PAID')
            ->where('c.dept_id', '>', 0);
        if ($deptId !== null) {
            $paidQ->where('c.dept_id', $deptId);
        }
        $paid = $paidQ->field('c.dept_id, sum(p.paid_amount) as paid')
            ->group('c.dept_id')
            ->select()->toArray();
        $paidMap = [];
        foreach ($paid as $r) {
            $paidMap[$r['dept_id']] = (float)$r['paid'];
        }
        foreach ($rows as &$r) {
            $r['total_amount']  = (float)$r['total_amount'];
            $r['paid_amount']   = $paidMap[$r['dept_id']] ?? 0.0;
            $r['recovery_rate'] = $r['total_amount'] > 0 ? round($r['paid_amount'] / $r['total_amount'] * 100, 1) : 0.0;
            $r['dept_name']     = trim((string)($r['dept_name'] ?? '')) !== '' ? $r['dept_name'] : '未命名部门';
        }
        unset($r);
        return $rows;
    }

    /**
     * 部门成员业绩排名（v2.40.0：部门经理工作台「本部门经营」用）
     * 按合同 owner_id 聚合本部门成员的交易合同额/回款，前 N 名。
     * @param int $deptId  部门 ID
     * @param int $limit   返回条数（默认 5）
     * @return array [{user_id, user_name, cnt, total_amount, paid_amount, recovery_rate}]
     */
    public static function deptMembers(int $deptId, int $limit = 5): array
    {
        if ($deptId <= 0) {
            return [];
        }
        $rows = Db::name('contract')->alias('c')
            ->leftJoin('user u', 'c.owner_id = u.id')
            ->where('c.is_deleted', 0)->where('c.trade_attr', 1)
            ->where('c.dept_id', $deptId)->where('c.owner_id', '>', 0)
            // P1-3：经营聚合排除未生效状态（草稿/驳回/审批中）
            ->where('c.status', 'not in', [ContractLogic::STATUS_DRAFT, ContractLogic::STATUS_REJECTED, ContractLogic::STATUS_PENDING_APPROVAL])
            // P1-3：成员业绩排除框架合同预算上限
            ->whereNotIn('c.id', \exclude_framework_contracts_ids())
            ->field('c.owner_id as user_id, u.name as user_name, count(*) as cnt, sum(c.amount) as total_amount')
            ->group('c.owner_id')
            ->order('total_amount', 'desc')
            ->limit($limit)
            ->select()->toArray();
        if (!$rows) {
            return [];
        }
        $ownerIds = array_column($rows, 'user_id');
        $paid = Db::name('payment_record')->alias('p')
            ->join('contract c', 'p.contract_id = c.id')
            ->where('c.is_deleted', 0)->where('c.trade_attr', 1)
            ->where('p.payment_type', 'RECEIVABLE')->where('p.status', 'PAID')
            ->whereIn('c.owner_id', $ownerIds)
            ->field('c.owner_id, sum(p.paid_amount) as paid')
            ->group('c.owner_id')
            ->select()->toArray();
        $paidMap = [];
        foreach ($paid as $r) {
            $paidMap[$r['owner_id']] = (float)$r['paid'];
        }
        foreach ($rows as &$r) {
            $r['total_amount']  = (float)$r['total_amount'];
            $r['paid_amount']   = $paidMap[$r['user_id']] ?? 0.0;
            $r['recovery_rate'] = $r['total_amount'] > 0 ? round($r['paid_amount'] / $r['total_amount'] * 100, 1) : 0.0;
            $r['user_name']     = trim((string)($r['user_name'] ?? '')) !== '' ? $r['user_name'] : '未命名';
        }
        unset($r);
        return $rows;
    }

    /**
     * 商务个人自视（v2.39.0：移动端「我的业绩」，仅看自己的数据，纵向对比不做排行）
     * 全部按 owner_id=本人收敛（不越权）；合同按交易口径 trade_attr=1、未删除；
     * 合同"本月/上月"按 created_at（签约时点），已收回款按 actual_date。
     * @param array $user 当前登录用户（取 id）
     * @return array 个人合同/回款/客户/待办 + 环比字段
     */
    public static function personalStats(array $user): array
    {
        $uid = (int)($user['id'] ?? 0);
        if ($uid <= 0) {
            return [];
        }
        $mStart = date('Y-m-01');
        $mEnd   = date('Y-m-t');
        $pStart = date('Y-m-01', strtotime('-1 month'));
        $pEnd   = date('Y-m-t', strtotime('-1 month'));

        // 我的合同（交易口径，owner_id=我；P1-3：仅统计生效状态 + 排除框架合同预算上限，业绩=实际成交）
        $base = Db::name('contract')->where('owner_id', $uid)->where('is_deleted', 0)->where('trade_attr', 1)
            ->where('status', 'not in', [ContractLogic::STATUS_DRAFT, ContractLogic::STATUS_REJECTED, ContractLogic::STATUS_PENDING_APPROVAL])
            ->whereNotIn('id', \exclude_framework_contracts_ids());
        $totalCnt = (int)$base->count();
        $totalAmt = (float)$base->sum('amount');
        $mCnt = (int)(clone $base)->whereBetween('created_at', [$mStart, $mEnd . ' 23:59:59'])->count();
        $mAmt = (float)(clone $base)->whereBetween('created_at', [$mStart, $mEnd . ' 23:59:59'])->sum('amount');
        $pAmt = (float)(clone $base)->whereBetween('created_at', [$pStart, $pEnd . ' 23:59:59'])->sum('amount');

        // 我的已收回款（join contract owner_id=我，PAID 用 paid_amount）
        $payBase = Db::name('payment_record')->alias('p')
            ->join('contract c', 'p.contract_id = c.id')
            ->where('c.owner_id', $uid)->where('c.is_deleted', 0)
            ->where('p.payment_type', 'RECEIVABLE')->where('p.status', 'PAID');
        $paidTotal = (float)(clone $payBase)->sum('p.paid_amount');
        $paidM = (float)(clone $payBase)->whereBetween('p.actual_date', [$mStart, $mEnd])->sum('p.paid_amount');
        $paidP = (float)(clone $payBase)->whereBetween('p.actual_date', [$pStart, $pEnd])->sum('p.paid_amount');
        // 我的待收/逾期（PENDING 按计划金额 amount，OVERDUE 同）
        $pendingQ = Db::name('payment_record')->alias('p')
            ->join('contract c', 'p.contract_id = c.id')
            ->where('c.owner_id', $uid)->where('c.is_deleted', 0)
            ->where('p.payment_type', 'RECEIVABLE');
        $pendingAmt = (float)(clone $pendingQ)->where('p.status', 'PENDING')->sum('p.amount');
        $overdueAmt = (float)(clone $pendingQ)->where('p.status', 'OVERDUE')->sum('p.amount');

        // 我的客户（owner_id=我）
        $custTotal = (int)Db::name('customer')->where('owner_id', $uid)->where('is_deleted', 0)->count();
        $custM = (int)Db::name('customer')->where('owner_id', $uid)->where('is_deleted', 0)
            ->whereBetween('created_at', [$mStart, $mEnd . ' 23:59:59'])->count();

        // 纵向环比（本月 vs 上月；上月为 0 时本月有值记 +100%，两者皆 0 记 0）
        $amtChg   = $pAmt > 0 ? round(($mAmt - $pAmt) / $pAmt * 100, 1) : ($mAmt > 0 ? 100 : 0);
        $paidChg  = $paidP > 0 ? round(($paidM - $paidP) / $paidP * 100, 1) : ($paidM > 0 ? 100 : 0);
        $rate     = $totalAmt > 0 ? round($paidTotal / $totalAmt * 100, 1) : 0;

        return [
            'user_name'    => trim((string)($user['name'] ?? '')),
            'dept_name'    => trim((string)($user['dept_name'] ?? '')),
            // 合同
            'total_cnt'    => $totalCnt,
            'total_amt'    => $totalAmt,
            'month_cnt'    => $mCnt,
            'month_amt'    => $mAmt,
            'prev_amt'     => $pAmt,
            'amt_chg'      => $amtChg,
            // 回款
            'paid_total'   => $paidTotal,
            'paid_month'   => $paidM,
            'paid_prev'    => $paidP,
            'paid_chg'     => $paidChg,
            'pending_amt'  => $pendingAmt,
            'overdue_amt'  => $overdueAmt,
            'recovery_rate'=> $rate,
            // 客户
            'cust_total'   => $custTotal,
            'cust_month'   => $custM,
        ];
    }
}
