<?php
namespace app\controller;

use think\facade\View;
use think\facade\Cache;
use app\BaseController;
use app\common\service\RemindService;

class DashboardController extends BaseController
{
    public function index()
    {
        // 移动端访问电脑端首页时，自动分流到移动版工作台
        if (is_mobile_request()) {
            return redirect('/m');
        }

        $userId = $this->userId;
        $user   = $this->user;
        // v2.38.17：时间周期筛选（month/quarter/year/all，默认当月；校验白名单）
        $period = strtolower((string)$this->getParam('period', 'month'));
        if (!in_array($period, ['month', 'quarter', 'year', 'all'], true)) $period = 'month';
        $isAjaxPartial = request()->isAjax();

        // P1-7：驾驶舱核心聚合整体下沉到 ReportLogic::dashboardSummary（单一口径来源，
        // 与移动端/合同详情页数字一致，已收统一用 paid_amount），消除原 index() 内约 8 处 Db 直查重复聚合。
        // P2-12：整体加 120s 缓存，key 按用户隔离（数据权限随用户，避免串数据）；
        // v2.38.17：缓存 key 含 period，周期切换不串数据。
        $summary = Cache::remember('dashboard_summary_' . $userId . '_' . $period, function () use ($user, $period) {
            return \app\common\logic\ReportLogic::dashboardSummary($user, $period);
        }, 120);

        // 按项目 TOP N（来自 ProjectLogic，独立保留 120s 缓存，与核心聚合解耦）
        $topProjects = Cache::remember('dashboard_topprojects_' . $userId, function () {
            return \app\common\logic\ProjectLogic::topProjects(5);
        }, 120);

        // v2.39.0：按部门经营汇总（管理层概览，累计口径；仅管理层有数据，普通用户空数组）
        // v2.40.3：显示由权限码控制（角色配置可勾选；is_admin 自动拥有），
        //          取代原 is_admin/gm/admin 角色码硬编码判定（生产环境 is_admin=0 + admin 角色同效）
        // v2.40.4：与移动端部门经营卡片统一由 dashboard:dept 控制（两端一致，勾选即两端均显示）
        // 两端语义统一：dashboard:company → 全公司部门排名；仅 dashboard:dept → 本部门汇总（与移动端一致，
        //           杜绝部门经理仅勾 dashboard:dept 时在 PC 端越权看到全公司其他部门数据）
        $isCompany = $this->hasPermission('dashboard:company');
        $canDept   = $this->hasPermission('dashboard:dept');
        $deptSummary = [];
        if ($isCompany) {
            // 总经理/超管：全公司部门排名
            $deptSummary = Cache::remember('dashboard_dept_summary', function () use ($user) {
                return \app\common\logic\ReportLogic::deptSummary($user, null, true);
            }, 120);
        } elseif ($canDept) {
            // 部门经理：仅本部门汇总（dept_id>0 才显示，无部门不显示）
            $deptId = (int)($user['dept_id'] ?? 0);
            if ($deptId > 0) {
                $deptSummary = Cache::remember('dashboard_dept_summary_' . $deptId, function () use ($user, $deptId) {
                    return \app\common\logic\ReportLogic::deptSummary($user, $deptId);
                }, 120);
            }
        }

        // v2.40.0：草稿待处理（数据权限范围内最新 5 条 + 总数，60s 短缓存）
        $draftContracts = Cache::remember('dashboard_drafts_' . $userId, function () use ($user) {
            return \app\common\logic\ContractLogic::draftList($user, 5);
        }, 60);

        // 今日提醒（读写分离：展示走 RemindService 纯读，写库由 CLI 负责；内部已加 60s 短缓存）
        // isAdmin 语义：is_admin=1 ∪ admin 角色（钉钉部署 is_admin=0 同效），管理员看全公司提醒
        $remindAlerts = RemindService::getTodayAlerts($userId, $this->isSuperAdmin(), $this->hasPermission('payment:view'));

        // 站内审批消息未读数（与今日提醒合并进 /remind 统一入口；表缺失时保守为 0）
        try {
            $msgUnread = \app\common\service\InternalNotify::unreadCount($userId);
        } catch (\Throwable $e) {
            $msgUnread = 0;
        }

        // v2.38.17：角色裁剪——KPI 卡/区块顺序按角色动态渲染（与移动端统一待办中心同口径）
        // v2.43.0 修复：approval:view / payment:view 已为全员默认基础权限（v2.40.2 起），
        // 原「hasPermission('approval:view') 判审批人、hasPermission('payment:view') 判财务」导致
        // 所有非管理员恒命中审批人分支，财务/普通员工分支永不可达。
        // 改用与 sidebar.php / MobileController::more() 同口径的角色画像判定（独立变量，不覆盖 can_approve）：
        //   财务画像 = 有 payment:create 且无 supplier:create（财务录回款但不创建供应商）
        //   经理画像 = 有 approval:approve 且有 supplier:create（经理审批 + 业务创建）
        //   （v2.40.2 后 contract:create 为全员基础权限，不再能区分画像，以 supplier:create 标记业务经理）
        $isAdmin = !empty($user['is_admin']);
        $canPay       = $this->hasPermission('payment:create');
        $canApproveOp = $this->hasPermission('approval:approve');
        $canSupplier  = $this->hasPermission('supplier:create');
        $isFinance = !$isAdmin && $canPay && !$canSupplier;
        $isManager = !$isAdmin && $canApproveOp && $canSupplier;
        // can_approve 保留「有审批查看能力」语义（approval:view 全员默认，供快捷操作按钮/顶部入口渲染）
        $canApprove = $isAdmin || $this->hasPermission('approval:view');
        // can_finance 保留「有回款查看能力」语义（payment:view 全员默认，供快捷操作等渲染）
        $canFinance = $isAdmin || $this->hasPermission('payment:view');

        View::assign([
            'period'           => $period,
            'period_label'     => $summary['period_label'] ?? '累计',
            'total_contracts'  => $summary['total_contracts'],
            'total_amount'     => $summary['total_amount'],
            'pending_approval' => $summary['pending_approval'],
            'signed_contracts' => $summary['signed_contracts'],
            'total_customers'  => $summary['total_customers'],
            'pool_count'       => $summary['pool_count'],
            'total_suppliers'  => $summary['total_suppliers'],
            'pending_count'    => $summary['pending_count'],
            'total_receivable' => $summary['total_receivable'],
            'received_amount'  => $summary['received_amount'],
            'pending_amount'   => $summary['pending_amount'],
            'overdue_amount'   => $summary['overdue_amount'],
            'overdue_count'    => $summary['overdue_count'],
            'recovery_rate'    => $summary['recovery_rate'],
            'month_expected'   => $summary['month_expected'],
            'month_received'   => $summary['month_received'],
            'dir_summary'      => $summary['dir'],
            'expiring_soon'    => $summary['expiring_soon'],
            'recent_contracts' => $summary['recent_contracts'],
            'trend_data'       => $summary['trend'],
            'upcoming_payments'=> $summary['upcoming_payments'],
            'status_counts'    => $summary['status_counts'],
            'remind_alerts'    => $remindAlerts,
            'msg_unread'       => $msgUnread,
            'top_projects'     => $topProjects,
            'dept_summary'     => $deptSummary,
            'draft_contracts'  => $draftContracts,
            'is_admin'         => $isAdmin,
            'is_manager'       => $isManager,
            'is_finance'       => $isFinance,
            'can_approve'      => $canApprove,
            'can_finance'      => $canFinance,
            'can_pay'          => $isAdmin || $this->hasPermission('payment:create'),
            'can_create'       => $isAdmin || $this->hasPermission('contract:create'),
        ]);

        // v2.38.17：周期切换走 AJAX 局部刷新——只返回 KPI 区 + 经营/收支 + 趋势，避免整页重载
        if ($isAjaxPartial) {
            return View::fetch('dashboard/_partial');
        }

        return View::fetch();
    }
}
