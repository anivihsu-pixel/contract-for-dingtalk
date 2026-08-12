<?php
// +----------------------------------------------------------------------
// | 移动端工作台（P1-D 轻量版）
// | 复用钉钉免登入口（/dingtalk/entry?to=/mobile），面向手机端：
// |   1) 我的待办：待我审批 + 今日提醒
// |   2) 我的合同：最近参与的合同（按数据权限）
// |   3) 快速登记回款：搜索合同 + 登记一笔待收款
// +----------------------------------------------------------------------

namespace app\controller;

use app\BaseController;
use think\facade\Db;
use app\common\logic\ApprovalLogic;
use app\common\logic\AuthLogic;
use app\common\logic\FinanceLogic;
use app\common\logic\CustomerLogic;
use app\common\logic\CustomerContactLogic;
use app\common\logic\ContractLogic;
use app\common\logic\ProjectLogic;
use app\common\logic\ReportLogic;
use app\common\logic\PartyLogic;
use app\common\logic\PaymentLogic;
use app\common\logic\UserLogic;
use app\common\logic\CompanyLogic;
use app\common\logic\ResourceLogic;
use app\common\logic\RoleLogic;
use app\common\logic\ApproverResolver;
use app\common\logic\SupplierLogic;
use app\common\logic\AdminLogic;
use app\common\service\RemindService;
use app\common\service\InternalNotify;
use think\facade\View;
use think\facade\Cache;

class MobileController extends BaseController
{
    /** 移动端工作台首页 */
    public function index()
    {
        // isAdmin 语义：is_admin=1 ∪ admin 角色（钉钉部署 is_admin=0 同效）——全公司提醒折叠/管理层行为
        $isAdmin = $this->isSuperAdmin();
        $canApprove = $isAdmin || $this->hasPermission('approval:view');

        // 1) 待我审批（有审批权限者才取；取前 10 条供待办流/数字卡）
        $pending = $canApprove
            ? \app\common\logic\ApprovalQueryService::getPendingList($this->userId, 1, 10)
            : ['list' => [], 'total' => 0];

        // 2) 今日提醒（到期/回款）+ 站内审批消息（统一切换 getOutstandingCount，与PC侧边栏一致）
        $alerts = [];
        $remindCount = 0;
        $notifUnread = 0;
        $notifList = [];
        $hasFin = $this->hasPermission('payment:view');
        try {
            $alerts = RemindService::getTodayAlerts($this->userId, $isAdmin, $hasFin);
            $remindCount = RemindService::getOutstandingCount($this->userId, $isAdmin, $hasFin);
        } catch (\Throwable $e) {
            $alerts = [];
        }
        try {
            $notifUnread = InternalNotify::unreadCount($this->userId);
            $notifList = InternalNotify::unreadList($this->userId, 5);
        } catch (\Throwable $e) { $notifUnread = 0; $notifList = []; }

        // 3) 我的合同总数（按数据权限，用于工作台概览数字；列表浏览交给底部"合同"Tab）
        $myContractTotal = ContractLogic::getMyCount();

        // 4) 统一待办流：待我审批 > 审批消息 > 到期/回款提醒（按优先级排序，供待办卡渲染）
        $todo = self::buildTodoStream($pending['list'] ?? [], $notifList, $alerts);
        // 待办总数口径：三路全计（待我审批全量 + 审批消息未读 + 合同/回款提醒），与工作台数字卡/角标一致
        $todoTotal = ($pending['total'] ?? 0) + $remindCount + $notifUnread;

        View::assign('pending_list', $pending['list'] ?? []);
        View::assign('pending_total', $pending['total'] ?? 0);
        View::assign('alerts', $alerts);
        View::assign('notif_unread', $notifUnread);
        View::assign('total_remind', $remindCount + $notifUnread); // 合同提醒 + 审批消息未读（与PC侧边栏统一口径）
        View::assign('todo_list', $todo);
        View::assign('todo_total', $todoTotal);
        View::assign('my_contracts_total', $myContractTotal);
        View::assign('can_pay', $this->hasPermission('payment:create'));
        // v2.38.18：开票申请入口（对齐财务页 m_can_apply_invoice 口径：invoice:apply 或 invoice:create）
        View::assign('can_invoice', $this->hasPermission('invoice:apply') || $this->hasPermission('invoice:create'));
        // 审批快捷操作入口：与原底部「审批」Tab 一致——管理员或具 approval:view 权限者可见（无权限者无审批入口，避免死入口）
        View::assign('can_approve', $canApprove);
        // v2.40.1：工作台新建 FAB 权限（对齐 contracts/customers/suppliers 列表页口径）
        View::assign('can_create_contract', $this->hasPermission('contract:create'));
        View::assign('can_create_customer', $this->hasPermission('customer:create'));
        View::assign('can_create_supplier', $this->hasPermission('supplier:create'));
        // v2.40.1：快捷操作扩展入口（资料库/客户池/报表，与各自列表页 requirePermission 口径一致）
        View::assign('can_library', $isAdmin || $this->hasPermission('library:view'));
        View::assign('can_customer_pool', $isAdmin || $this->hasPermission('customer:view'));
        View::assign('can_report', $isAdmin || $this->hasPermission('payment:view') || $this->hasPermission('invoice:view'));
        // v2.48.0：工作台「记录跟进」——负责人/超管可见（有 customer:edit 者）；可选客户=超管全部/普通=本人负责（均排除公海，公海需先认领）
        $canFollow = $isAdmin || $this->hasPermission('customer:edit');
        View::assign('can_follow', $canFollow);
        $quickFollowCust = [];
        if ($canFollow) {
            try {
                $q = Db::name('customer')->where('is_deleted', 0)->where('owner_id', '>', 0);
                if (!$isAdmin) {
                    $q->where('owner_id', $this->userId);
                }
                $quickFollowCust = $q->field('id,name')->limit(200)->order('id', 'desc')->select()->toArray();
            } catch (\Throwable $e) {
                $quickFollowCust = [];
            }
        }
        View::assign('quick_follow_customers', $quickFollowCust);
        View::assign('is_admin', $isAdmin); // 今日提醒折叠逻辑（导航优化 Phase2：管理员全公司提醒默认折叠）
        // v2.40.0：管理层差异化卡片——总经理看全公司部门排名（不显示我的业绩），
        // 部门经理看本部门汇总+成员排名（同时显示我的业绩），普通商务仅显示我的业绩
        // v2.40.3：总经理判定改权限码 dashboard:company（角色配置可勾选，is_admin 自动拥有），
        //          取代原 is_admin/gm/admin 角色码硬编码
        // v2.40.4：部门经营卡片判定改权限码 dashboard:dept（角色配置可勾选），
        //          取代原 user.id == department.leader_user_id 身份判定；有部门（dept_id>0）且勾选即显示
        $isGeneralManager = $this->hasPermission('dashboard:company');
        $canDept          = $this->hasPermission('dashboard:dept');

        $deptOverview = [];   // [{dept_id, dept_name, cnt, total_amount, paid_amount, recovery_rate}]
        $deptMembers  = [];   // [{user_id, user_name, cnt, total_amount, paid_amount, recovery_rate}]
        $deptTitle    = '';   // 卡片标题（全公司经营 / 本部门经营·XX部）
        $isDeptLeader = false;

        if ($isGeneralManager) {
            // 总经理：全公司部门排名（allowAll=管理层角色判定，兼容 is_admin=0 + gm/admin 角色）
            $deptOverview = \app\common\logic\ReportLogic::deptSummary($this->user, null, $isGeneralManager);
            $deptTitle    = '全公司经营';
        } elseif ($canDept) {
            $deptId = (int)($this->user['dept_id'] ?? 0);
            if ($deptId > 0) {
                $isDeptLeader = true;
                $deptOverview = \app\common\logic\ReportLogic::deptSummary($this->user, $deptId);
                $deptMembers  = \app\common\logic\ReportLogic::deptMembers($deptId, 5);
                $deptName     = UserLogic::getDeptName($deptId);
                $deptTitle    = '本部门经营·' . (trim($deptName) !== '' ? $deptName : '未命名');
            }
        }
        View::assign('dept_overview', $deptOverview);
        View::assign('dept_members', $deptMembers);
        View::assign('dept_title', $deptTitle);
        View::assign('is_general_manager', $isGeneralManager);
        View::assign('is_dept_leader', $isDeptLeader);

        // 我的业绩概览（/m/my-stats）——v2.40.3：显示由权限码 dashboard:stats 控制（角色配置可勾选）；
        // 总经理角色未勾该权限则维持「总经理看全公司经营、不显示我的业绩」的设计（is_admin 用户 hasPermission 恒真，
        // 但受 !$isGeneralManager 约束同样不显示，与原行为一致）
        $canMyStats = !$isGeneralManager && $this->hasPermission('dashboard:stats');
        View::assign('can_my_stats', $canMyStats);
        View::assign('my_stats', $canMyStats ? \app\common\logic\ReportLogic::personalStats($this->user) : []);

        return View::fetch('mobile/index');
    }

    /**
     * 统一待办流组装（v2.38.18 去重调整）：
     * 仅返回「待我审批」动作待办——审批消息/到期提醒分别由 Tab3/Tab2 独立展示，
     * 避免三路合并与分类 Tab 内容重复（用户 2026-08-04 指出重复）。
     * public：PC /remind 待办中心复用同一口径，保证 PC/移动一致
     * 每项：['kind'=>'approval', 'level', 'text', 'sub', 'link']
     */
    public static function buildTodoStream(array $pendingList, array $notifList, array $alerts): array
    {
        $todo = [];

        // 待我审批（动作型待办，最高优先级）
        foreach ($pendingList as $p) {
            $todo[] = [
                'kind'  => 'approval',
                'level' => 'danger',
                'text'  => '待我审批：《' . ($p['contract_title'] ?? '合同') . '》',
                'sub'   => ($p['submitter_name'] ?? '') . ' 提交 · ' . ($p['flow_name'] ?? '审批'),
                'link'  => '/m/approval/' . (int)$p['id'],
            ];
        }

        return $todo;
    }

    /** 移动端资料库列表（查看型：仅阅读，上传/编辑/删除走 PC 端——v2.43.6 起移动端纯只读）
     * 复用 ResourceLogic::getList 取数；前端搜索/分类切换复用 PC 端 /resource/list AJAX。 */
    public function resource()
    {
        $this->requirePermission('library:view');
        $category = $this->getParam('category', '');
        $keyword  = trim($this->getParam('keyword', ''));
        $page     = (int)$this->getParam('page', 1);
        if ($page < 1) { $page = 1; }
        $pageSize = 20;
        $result   = ResourceLogic::getList($category, 0, $keyword, $page, $pageSize);
        View::assign('list', $result['list']);
        View::assign('total', $result['total']);
        View::assign('categories', ResourceLogic::categories());
        View::assign('keyword', $keyword);
        View::assign('category', $category);
        return View::fetch('mobile/resource');
    }

    /** 移动端资料库详情（查看型：仅阅读 + 开票资料一键复制；无编辑/删除入口）
     * INVOICE 类展示 content 结构化字段卡；有 file_url 提供内嵌预览（复用 /m/office-preview，钉钉不跳出）。 */
    public function resourceDetail($id)
    {
        $this->requirePermission('library:view');
        $id   = (int)$id;
        $item = ResourceLogic::findRaw($id);
        if (!$item) {
            View::assign('item', null);
            return View::fetch('mobile/resource_detail');
        }
        $cats      = ResourceLogic::categories();
        $companyMap = array_column(CompanyLogic::getSelectNames(), 'name', 'id');
        $item['category_name'] = $cats[$item['category']] ?? $item['category'];
        $item['content_arr']   = ResourceLogic::decodedContent($item['content'] ?? '');
        $item['company_name']  = $item['company_id'] > 0 ? ($companyMap[$item['company_id']] ?? '') : '';
        View::assign('item', $item);
        View::assign('invoice_fields', ResourceLogic::$INVOICE_FIELDS);
        return View::fetch('mobile/resource_detail');
    }

    /** 移动端"更多"聚合页（导航优化 Phase1：把浏览型模块财务/报表/项目/归档/资料库收进固定入口，
     *  消除二级页无高亮；按权限裁剪模块并按角色频率排序） */
    public function more()
    {
        $isAdmin = !empty($this->user['is_admin']);
        $canViewProject  = $this->hasPermission('project:view');
        $canResource     = $this->hasPermission('library:view');
        $canViewContract = $this->hasPermission('contract:view'); // 归档合同属合同子集，按 contract:view 裁剪
        $canParty        = $this->hasPermission('party:view');    // v2.38.11: 相对方 360（PC 视图，移动端跳转可用）

        // P1：角色识别——通过权限码组合判断角色画像（避免直接依赖 role.code）
        // 财务画像：有 payment:create 且无 supplier:create（财务录回款但不创建供应商）
        // 部门经理画像：有 approval:approve 且有 supplier:create（经理审批+业务创建）
        // （v2.40.2：contract:create 已为全员默认基础权限，不再能区分画像，改用 supplier:create 标记业务经理）
        $canPay       = $this->hasPermission('payment:create');
        $canApproveOp = $this->hasPermission('approval:approve');
        $canSupplier  = $this->hasPermission('supplier:create');
        $isFinance  = !$isAdmin && $canPay && !$canSupplier;
        $isManager  = !$isAdmin && $canApproveOp && $canSupplier;

        // v2.38.25/26：待交接管理入口——system:user（PC 用户管理）或 system:handover（离职交接独立权限码）任一可见，角标显示待交接人数
        $canHandover = $this->hasPermission('system:handover') || $this->hasPermission('system:user');
        $hoCount = $canHandover
            ? UserLogic::countPendingHandover()
            : 0;
        View::assign('handover_count', $hoCount);

        // 消息中心入口（所有用户可见，置顶）——2026-08-05 去重：站内信并入待办中心 Tab3，模块改指 /m/remind
        $notif  = ['/m/remind', 'bi-bell', '待办中心', true];
        // v2.39.0：我的业绩（所有用户可见，个人自视，置顶位置靠前）
        $mystats = ['/m/my-stats', 'bi-person-vcard', '我的业绩', true];
        $finance= ['/m/finance',  'bi-cash-coin',        '财务概览', true];
        $reports= ['/m/reports',  'bi-bar-chart-line',   '报表概览', true];
        $proj   = ['/m/projects', 'bi-folder2',          '项目列表', $canViewProject];
        $res    = ['/m/resource', 'bi-folder2-open',     '资料库',   $canResource];
        $arch   = ['/m/archive',  'bi-archive',          '归档合同', $canViewContract];
        $party  = ['/m/party',    'bi-arrow-left-right', '往来档案', $canParty];
        $handover = ['/m/handover', 'bi-person-x', '数据交接', $canHandover];

        // P1：4 档排序——按角色高频场景重排，财务前置财务/报表，经理前置项目，普通员工前置归档
        if ($isAdmin) {
            $order = [$notif, $mystats, $handover, $finance, $reports, $proj, $res, $arch, $party];
        } elseif ($isFinance) {
            // 财务：财务→报表→往来档案（移除项目/资料库/归档低频项）
            $order = [$notif, $mystats, $finance, $reports, $party, $arch, $res, $proj, $handover];
        } elseif ($isManager) {
            // 部门经理：项目→归档→报表→财务→资料库→往来
            $order = [$notif, $mystats, $proj, $arch, $reports, $finance, $res, $party, $handover];
        } else {
            // 普通员工：归档→项目→资料库→报表→往来（财务低频后置）
            $order = [$notif, $mystats, $arch, $proj, $res, $reports, $party, $finance, $handover];
        }
        $modules = array_values(array_filter($order, function ($m) { return $m[3]; }));
        View::assign('modules', $modules);
        return View::fetch('mobile/more');
    }

    /** v2.39.0：移动端「我的业绩」个人自视页（只看自己的合同/回款/客户，纵向环比不做排行）
     *  所有登录用户可见（商务自视）；数据按 owner_id=本人收敛，无越权 */
    public function myStats()
    {
        $user = $this->user;
        // 会话用户可能缺部门名，补查（个人自视展示"XX部门·XX"；P1-1：下沉 UserLogic::getWithDept）
        if (empty($user['dept_name'])) {
            $u = UserLogic::getWithDept($this->userId);
            if ($u) {
                $user['name']      = $u['name'] ?? '';
                $user['dept_name'] = $u['dept_name'] ?? '';
            }
        }
        View::assign('stats', \app\common\logic\ReportLogic::personalStats($user));
        return View::fetch('mobile/my_stats');
    }

    /** 移动端待交接办理页（v2.38.25）：有权账号在手机上直接办理离职数据移交，
     *  v2.38.26：权限为 system:user 或 system:handover（独立权限码，权限管理可单独勾选），
     *  复用 PC AJAX 接口 /ajax/admin/user/handover（BaseController 权限守卫一致） */
    public function handover()
    {
        $this->requireAnyPermission(['system:user', 'system:handover']);
        View::assign('handoverUsers', AdminLogic::getHandoverUsers());
        // 接收人候选/在职交接列表：全部在职用户（含部门，前端下拉排除交接人本人；P1-1：下沉 UserLogic::getActiveWithDept）
        View::assign('ho_users', UserLogic::getActiveWithDept());
        return View::fetch('mobile/handover');
    }

    /** 移动端消息中心（P1 补齐：独立站内信列表页，所有登录用户可见）
     * 2026-08-05 去重：站内信能力已并入「待办中心」/m/remind 的 Tab3（同一套 notification.js 渲染），
     * 本页改为重定向，保留路由与旧链接不 404。 */
    public function notifications()
    {
        return redirect('/m/remind?tab=notif');
    }

    /** 移动端今日提醒列表页（v2.35.7）：工作台顶部「今日提醒」点击后的真实目的地
     *  v2.38.15：改造为统一待办中心——Tab1 待办（审批+审批消息+提醒合并流）/ Tab2 提醒 / Tab3 审批消息 */
    public function reminders()
    {
        $isAdmin = !empty($this->user['is_admin']);
        $hasFin = $this->hasPermission('payment:view');
        $canApprove = $isAdmin || $this->hasPermission('approval:view');
        $alerts = [];
        $remindCount = 0;
        try {
            $alerts = RemindService::getTodayAlerts($this->userId, $isAdmin, $hasFin);
            $remindCount = RemindService::getOutstandingCount($this->userId, $isAdmin, $hasFin);
        } catch (\Throwable $e) {
            $alerts = [];
            $remindCount = 0;
        }
        $notifUnread = 0;
        $notifList = [];
        try {
            $notifUnread = InternalNotify::unreadCount($this->userId);
            $notifList = InternalNotify::unreadList($this->userId, 5);
        } catch (\Throwable $e) {}
        $pending = $canApprove
            ? \app\common\logic\ApprovalQueryService::getPendingList($this->userId, 1, 10)
            : ['list' => [], 'total' => 0];
        $todo = self::buildTodoStream($pending['list'] ?? [], $notifList, $alerts);
        $todoTotal = ($pending['total'] ?? 0) + $remindCount + $notifUnread;
        View::assign('alerts', $alerts);
        View::assign('pending_list', $pending['list'] ?? []);
        View::assign('pending_total', $pending['total'] ?? 0);
        View::assign('notif_unread', $notifUnread);
        View::assign('notif_list', $notifList);
        View::assign('todo_list', $todo);
        View::assign('todo_total', $todoTotal);
        View::assign('total_remind', $remindCount + $notifUnread);
        return View::fetch('mobile/reminders');
    }

    /** 通用文档预览页（v2.43.6：doc-preview 升级为 office-preview，支持 pdf/docx/xlsx）
     * 接收顶层 p/t 参数拼 /preview 代理 URL（/m/office-preview?p=...&t=...&name=...）；
     * 顶层参数在外部浏览器 URL 规范化下不会丢令牌；f 参数保留兼容旧链接；
     * 页面按文件名扩展名选渲染器（pdf→PDF.js / docx→docx-preview / xlsx→SheetJS）。 */
    public function officePreview()
    {
        $path  = input('get.p', '');
        $token = input('get.t', '');
        if ($path !== '') {
            $fileUrl = '/preview?p=' . urlencode($path) . ($token !== '' ? '&t=' . urlencode($token) : '');
        } else {
            $fileUrl = input('get.f', '');  // 兼容旧格式 f=/preview?p=...&t=...
        }
        $fileName = input('get.name', '文档预览');  // 文件名（显示在顶部标题栏，按扩展名选渲染器）
        View::assign('fileUrl', $fileUrl);
        View::assign('fileName', $fileName);
        return View::fetch('mobile/office_preview');
    }

    /** 移动端审批待办列表（S 级独立视图，卡片流） */
    public function approvals()
    {
        // UX 门控：与 PC 端 Approval 列表同口径，approval:view 校验（数据均按本人收敛）
        $this->requirePermission('approval:view');
        // 三 Tab：待办 / 已办 / 我提交
        $type = $this->getParam('type', 'todo');
        if (!in_array($type, ['todo', 'done', 'submitted'], true)) $type = 'todo';
        [$page, $pageSize] = $this->mobilePage();

        $loader = [
            'todo'      => [\app\common\logic\ApprovalQueryService::class, 'getPendingList'],
            'done'      => [\app\common\logic\ApprovalQueryService::class, 'getProcessedList'],
            'submitted' => [\app\common\logic\ApprovalQueryService::class, 'getMySubmittedList'],
        ];
        $data = call_user_func($loader[$type], $this->userId, $page, $pageSize);

        // 各 Tab 总数（角标）
        // REV-39：角标计数加 30s 短缓存，避免每次请求额外 3 次计数查询；
        //         当前 Tab 使用本次已查的实时总数覆盖缓存值，保证当前页数字准确。
        $counts = Cache::remember('m_approval_counts_' . $this->userId, function () use ($loader) {
            return [
                'todo'      => call_user_func($loader['todo'], $this->userId, 1, 1)['total'] ?? 0,
                'done'      => call_user_func($loader['done'], $this->userId, 1, 1)['total'] ?? 0,
                'submitted' => call_user_func($loader['submitted'], $this->userId, 1, 1)['total'] ?? 0,
            ];
        }, 30);
        $counts[$type] = $data['total'] ?? 0;

        if (request()->isAjax()) {
            return json(['code' => 0, 'msg' => 'ok', 'data' => $data['list'], 'total' => $data['total']]);
        }
        View::assign('type', $type);
        View::assign('list', $data['list'] ?? []);
        View::assign('total', $data['total'] ?? 0);
        View::assign('counts', $counts);
        return View::fetch('mobile/approvals');
    }

    /** 移动端审批详情 + 操作（S 级独立视图，仅通过/驳回） */
    public function approvalDetail($id)
    {
        $detail = \app\common\logic\ApprovalQueryService::getDetail((int)$id);
        if (!$detail) {
            throw new \think\exception\HttpException(404, '审批不存在');
        }

        // 数据权限：合同归属可查（S3：非数据范围内用户拒绝查看他人合同审批）
        $contract = ContractLogic::getById((int)$detail['contract_id']);
        if (!$contract) {
            throw new \think\exception\HttpException(404, '关联合同不存在');
        }

        // PERM-1：审批参与者（提交人 / 任一节点审批人 / 抄送人）默认可查看本审批实例及其合同，
        // 即使无数据范围权限——其拥有合法的知悉权，不应被拦截。
        if (!\app\common\logic\ApprovalQueryService::isParticipant((int)$id, $this->userId)
            && !AuthLogic::canAccessRecord((int)$contract['owner_id'], (int)($contract['dept_id'] ?? 0))) {
            throw new \think\exception\HttpException(403, '无权限查看该审批');
        }

        // 当前用户是否为当前节点的待审批人
        $canAct = \app\common\logic\ApprovalQueryService::getPendingAction((int)$id, $this->userId);

        // CR-37：转交可选目标用户（启用且非本人；非管理员仅同部门；支持关键词搜索）
        $transferUsers = UserLogic::getTransferTargets(
            $this->userId,
            !empty($this->user['is_admin']),
            (int)($this->user['dept_id'] ?? 0),
            trim((string)$this->getParam('kw', ''))
        );

        // 提交人可撤回（自身提交且仍待审批）
        $canRecall = ((int)($detail['submitted_by'] ?? 0) === $this->userId)
            && ($detail['status'] ?? '') === 'PENDING';

        // 合同正文 / 附件（内联全文查看，避免跳出决策）
        $attachments = [];
        $rawFile = trim((string)($contract['file_url'] ?? ''));
        if ($rawFile !== '') {
            $dec = json_decode($rawFile, true);
            if (is_array($dec)) $attachments = $dec;
        }
        // 对方主体名称（本公司主体 our_company_id → company_profile.name）
        $ourCompany = CompanyLogic::getName((int)($contract['our_company_id'] ?? 0));

        View::assign('detail', $detail);
        View::assign('contract', $contract);
        View::assign('can_act', !empty($canAct));
        View::assign('can_recall', $canRecall);
        View::assign('transfer_users', $transferUsers);
        View::assign('attachments', $attachments);
        View::assign('our_company', $ourCompany);
        return View::fetch('mobile/approval_detail');
    }

    /** 移动端提交审批页（S 级独立视图，避免跳回桌面 create 页） */
    public function approvalCreate($id)
    {
        $this->requirePermission('approval:submit');
        $id = (int)$id;
        $contract = \app\common\logic\ContractLogic::accessible($id);
        if (!$contract) {
            throw new \think\exception\HttpException(404, '合同不存在或无权限查看');
        }

        if (!in_array($contract['status'] ?? '', [ContractLogic::STATUS_DRAFT, ContractLogic::STATUS_REJECTED])) {
            throw new \think\exception\HttpException(403, '当前合同状态不可提交审批');
        }

        // 自动匹配将使用的审批流程（预览，无需用户手动选择；去模板落地：仅按分类+金额）
        $flow = \app\common\logic\ApprovalSubmitService::matchFlow($contract['category'] ?? '', (float)($contract['amount'] ?? 0), (int)($contract['trade_attr'] ?? 1));

        // 解析每个审批节点的实际用户
        $rawNodes = json_decode($flow['nodes'] ?? '[]', true) ?: [];
        $allUserIds = [];
        // P3b-7：缓存每个节点的解析结果，避免第二次循环重复调用 ApproverResolver::resolve
        $resolvedByNode = [];
        foreach ($rawNodes as $idx => $n) {
            $ids = ApproverResolver::resolve($n, $this->userId);
            $resolvedByNode[$idx] = $ids;
            $allUserIds = array_merge($allUserIds, $ids);
        }
        // v2.38.1：同步解析流程级抄送列表（cc_list），提交页展示抄送人
        $ccList  = json_decode($flow['cc_list'] ?? '{}', true) ?: [];
        $ccRoles = $ccList['role_codes'] ?? [];
        $ccUsers = $ccList['cc_user_ids'] ?? [];
        $ccRoleNames   = [];
        $ccResolvedIds = [];
        if (!empty($ccRoles)) {
            $roleMap = RoleLogic::getMap();
            foreach ($ccRoles as $rc) {
                $ccRoleNames[] = $roleMap[$rc] ?? $rc;
                $ccResolvedIds = array_merge($ccResolvedIds, ApproverResolver::resolveRoleCodes([$rc]));
            }
        }
        if (!empty($ccUsers)) {
            $ccResolvedIds = array_merge($ccResolvedIds, $ccUsers);
        }
        $ccResolvedIds = array_unique(array_map('intval', $ccResolvedIds));
        $allUserIds    = array_merge($allUserIds, $ccResolvedIds);
        $userMap = UserLogic::getNamesByIds($allUserIds);
        $flowNodes = [];
        foreach ($rawNodes as $idx => $n) {
            $ids = $resolvedByNode[$idx];
            $n['resolved_names'] = array_values(array_filter(array_map(function ($id) use ($userMap) {
                return $userMap[$id] ?? null;
            }, $ids)));
            $flowNodes[] = $n;
        }
        $ccNames = array_values(array_filter(array_map(function ($id) use ($userMap) {
            return $userMap[$id] ?? null;
        }, $ccResolvedIds)));

        View::assign('contract', $contract);
        View::assign('matched_flow', $flow);
        View::assign('role_map', RoleLogic::getMap());
        View::assign('flow_nodes', $flowNodes);
        View::assign('cc_names', $ccNames);
        View::assign('cc_roles', $ccRoleNames);
        View::assign('has_cc', !empty($ccNames) || !empty($ccRoles));
        return View::fetch('mobile/approval_create');
    }

    /** 移动端合同详情（S 级独立视图） */
    public function contractDetail($id)
    {
        $this->requirePermission('contract:view');
        $id = (int)$id;
        $contract = \app\common\logic\ContractLogic::accessible($id);
        if (!$contract) {
            throw new \think\exception\HttpException(404, '合同不存在或无权限查看');
        }

        // 归属人姓名（下沉至 ContractLogic，Phase 1.6）
        $contract['owner_name'] = ContractLogic::getOwnerName((int)($contract['owner_id'] ?? 0));

        // 回款/付款计划时间线（下沉至 PaymentLogic，Phase 1.6）
        $payTimeline = PaymentLogic::getContractTimeline($id);
        $payments    = $payTimeline['payments'];
        $paidAmount  = $payTimeline['paid_amount'];
        $planAmount  = $payTimeline['plan_amount'];

        // 审批记录（CR-09：含节点意见的历史）
        $approvals = \app\common\logic\ApprovalQueryService::getApprovalHistory($id);

        // 当前用户是否待审批此合同的某条审批（下沉至 ApprovalLogic，Phase 1.6）
        // P2-1【M-Pf2】复用上方已拉取的历史，避免 getPendingApprovalId 内部再次全量拉取
        $pendingApprovalId = \app\common\logic\ApprovalQueryService::getPendingApprovalId($id, $this->userId, $approvals);

        // 附件（下沉至 ContractLogic，Phase 1.6）
        $attachments = ContractLogic::parseFileUrls((string)($contract['file_url'] ?? ''));

        // v2.38.14：甲乙方往来摘要（360 能力内嵌）——乙方客户/关联供应商内嵌轻量摘要，点击跳相对方全景
        // v2.46.0：甲方客户/甲方供应商对称展示
        $party360 = [];
        if ((int)($contract['party_a_customer_id'] ?? 0) > 0) {
            $party360['customer_a'] = PartyLogic::getSummary('customer', (int)$contract['party_a_customer_id']);
        }
        if ((int)($contract['party_a_supplier_id'] ?? 0) > 0) {
            $party360['supplier_a'] = PartyLogic::getSummary('supplier', (int)$contract['party_a_supplier_id']);
        }
        if ((int)($contract['party_b_customer_id'] ?? 0) > 0) {
            $party360['customer'] = PartyLogic::getSummary('customer', (int)$contract['party_b_customer_id']);
        }
        if ((int)($contract['supplier_id'] ?? 0) > 0) {
            $party360['supplier'] = PartyLogic::getSummary('supplier', (int)$contract['supplier_id']);
        }

        // 自定义结构化字段（schema 仅由合同自身 custom_fields 决定）
        $customSchema = [];
        $customValues = [];
        $rawCv = trim((string)($contract['custom_fields'] ?? ''));
        if ($rawCv !== '' && $rawCv !== '{}') {
            $dv = json_decode($rawCv, true);
            if (is_array($dv)) $customValues = $dv;
        }

        // 可用状态操作（用于底部操作栏）
        $actions = \app\common\logic\ContractLogic::getAvailableActions($contract['status']);
        // UX 门控：编辑按钮 = 状态允许 + contract:edit 权限 + 数据范围（与桌面端 detail 门控同口径）
        $canEdit = in_array($contract['status'], [ContractLogic::STATUS_DRAFT, ContractLogic::STATUS_REJECTED])
            && $this->hasPermission('contract:edit')
            && \app\common\logic\AuthLogic::canAccessRecord($contract['owner_id'], $contract['dept_id'] ?? 0);

        // Phase 2.3：状态手动变更权限（与桌面端 statusTransition 一致：归属人/创建人/管理员 + contract:edit 权限层）
        $canStatusChange = !empty($actions)
            && $this->hasPermission('contract:edit')
            && !in_array($contract['status'], [ContractLogic::STATUS_DRAFT, ContractLogic::STATUS_REJECTED, ContractLogic::STATUS_PENDING_APPROVAL])
            && \app\common\logic\AuthLogic::canAccessRecord($contract['owner_id'], $contract['dept_id'] ?? 0);

        // Phase 2.4：回款确认/撤销权限
        $canPayment = $this->hasPermission('payment:create');

        View::assign('contract', $contract);
        View::assign('payments', $payments);
        View::assign('paid_amount', $paidAmount);
        View::assign('plan_amount', $planAmount);
        View::assign('approvals', $approvals);
        View::assign('pending_approval_id', $pendingApprovalId);
        View::assign('attachments', $attachments);
        View::assign('custom_schema', $customSchema);
        View::assign('custom_values', $customValues);
        View::assign('party360', $party360);            // v2.38.14 甲乙方往来摘要
        View::assign('actions', $actions);
        View::assign('can_edit', $canEdit);
        View::assign('can_status_change', $canStatusChange);   // Phase 2.3
        View::assign('can_payment', $canPayment);               // Phase 2.4
        // UX 门控：提交审批按钮按 approval:submit 权限渲染（后端 /m/contract/:id/approval 已有守卫）
        View::assign('can_submit_approval', $this->hasPermission('approval:submit'));
        return View::fetch('mobile/contract_detail');
    }

    /** 移动端合同列表（S 级独立视图，P0 修复：原「合同」Tab / 工作台「全部」/ 快捷入口均指向桌面 /contract，现独立移动页） */
    public function contracts()
    {
        $this->requirePermission('contract:view');
        $keyword  = $this->getParam('keyword', '');
        $status   = $this->getParam('status', '');
        $direction   = $this->getParam('direction', '');
        $category    = $this->getParam('category', '');
        $tradeAttr   = $this->getParam('trade_attr', '');
        $framework   = $this->getParam('framework', '');
        $partyName   = $this->getParam('party_name', '');
        $ourCompany  = $this->getParam('our_company_id', '');
        $ownerId     = $this->getParam('owner_id', '');
        $amountMin   = $this->getParam('amount_min', '');
        $amountMax   = $this->getParam('amount_max', '');
        $projectId   = $this->getParam('project_id', '');   // P2-3【M-Pf5】项目详情"查看全部合同"入口
        $customerId  = $this->getParam('customer_id', '');  // N-m4 客户详情"查看全部关联合同"入口
        [$page, $pageSize] = $this->mobilePage();
        $filter = [];
        if ($keyword  !== '') $filter['keyword']  = $keyword;
        if ($status   !== '') $filter['status']   = $status;
        if ($direction!== '') $filter['direction']= $direction;
        if ($category !== '') $filter['category'] = $category;
        // 注意：trade_attr 用 isset 守卫（0/1 均有效），仅明确选择时入参，避免空串误筛为 0
        if ($tradeAttr !== '') $filter['trade_attr'] = (int)$tradeAttr;
        if ($framework !== '') $filter['framework'] = $framework;
        if ($partyName !== '') $filter['party_name'] = $partyName;
        if ($ourCompany!== '' && is_numeric($ourCompany)) $filter['our_company_id'] = (int)$ourCompany;
        if ($ownerId  !== '' && is_numeric($ownerId))   $filter['owner_id'] = (int)$ownerId;
        if ($projectId!== '' && is_numeric($projectId)) $filter['project_id'] = (int)$projectId;
        if ($customerId!== '' && is_numeric($customerId)) $filter['customer_id'] = (int)$customerId;
        if ($amountMin !== '' && is_numeric($amountMin)) $filter['amount_min'] = $amountMin;
        if ($amountMax !== '' && is_numeric($amountMax)) $filter['amount_max'] = $amountMax;

        // v2.44.4：移动端合同列表草稿置顶（草稿卡片同时浅琥珀底区分，见 contracts.php）
        $res  = ContractLogic::getList($page, $pageSize, $filter, ['draft_first', 'desc']);
        $list = $res['list'] ?? [];

        $statusMap = contract_status_map();     // CR-57：复用公共 helper，全 10 态
        $statusBadge = contract_status_badge();
        $categories = contract_categories();          // 合同类别字典（code => name）
        $companies  = CompanyLogic::getList();        // 签约主体（零 Db 直查，Phase 2.2）
        // v2.40.1：归属人下拉按数据范围收敛（ALL=全部用户 / DEPT=本部门用户 / SELF=仅本人），
        // 与 PC 端 ContractController 口径一致，避免移动端下拉出现范围外用户误导筛选
        $owners = UserLogic::getOptions();
        $vis = AuthLogic::visibility();
        if (!$vis['has_all']) {
            $visibleIds = [];
            if (!empty($vis['owner_self'])) {
                $visibleIds[] = (int)$this->userId;
            }
            if (!empty($vis['dept_ids'])) {
                $visibleIds = array_merge($visibleIds, UserLogic::getIdsByDeptIds($vis['dept_ids']));
            }
            $visibleIds = array_unique(array_map('intval', $visibleIds));
            $owners = array_values(array_filter($owners, fn($o) => in_array((int)$o['id'], $visibleIds, true)));
        }

        if (request()->isAjax()) {
            return json([
                'code'       => 0,
                'msg'        => 'ok',
                'data'       => $list,
                'total'      => $res['total'] ?? 0,
                'statusMap'  => $statusMap,
                'statusBadge'=> $statusBadge,
            ]);
        }

        View::assign('list', $list);
        View::assign('total', $res['total'] ?? 0);
        View::assign('keyword', $keyword);
        View::assign('status', $status);
        View::assign('filter', $filter);              // 回显已选高级筛选条件
        View::assign('statusMap', $statusMap);
        View::assign('statusBadge', $statusBadge);
        View::assign('categories', $categories);
        View::assign('companies', $companies);
        View::assign('owners', $owners);
        View::assign('can_create_contract', $this->hasPermission('contract:create'));   // 移动端合同列表「新增」悬浮按钮权限（与客户的/供应商一致）
        return View::fetch('mobile/contracts');
    }

    /** 移动端客户列表（S 级独立视图，Phase 4） */
    public function customers()
    {
        $this->requirePermission('customer:view');
        $keyword = $this->getParam('keyword', '');
        // v2.38.9：客户生命周期筛选（客户/成交/公海）
        $lifecycle = $this->getParam('lifecycle', '');
        [$page, $pageSize] = $this->mobilePage();
        $filter = ['keyword' => $keyword, 'lifecycle_status' => $lifecycle];
        $res = CustomerLogic::getList($page, $pageSize, $filter);
        $list = $res['list'] ?? [];

        // 归属人姓名
        $ownerIds = array_values(array_unique(array_column($list, 'owner_id')));
        $owners = UserLogic::getNamesByIds($ownerIds);
        $statusMap = [1 => '正常', 0 => '禁用'];
        // P3b-8：列表接口字段统一，AJAX 同时返回 statusMap 与 lifecycle_dict 供前端渲染
        $lifecycleDict = dict('customer_lifecycle');
        // v2.40.0 P1-7：客户行业字典（AJAX + 视图渲染共用）
        $industryDict = dict('customer_industry');

        if (request()->isAjax()) {
            return json([
                'code'          => 0,
                'msg'           => 'ok',
                'data'          => $list,
                'total'         => $res['total'] ?? 0,
                'statusMap'     => $statusMap,
                'lifecycleDict' => $lifecycleDict,
                'industryDict'  => $industryDict,
            ]);
        }
        View::assign('list', $list);
        View::assign('total', $res['total'] ?? 0);
        View::assign('keyword', $keyword);
        View::assign('owners', $owners);
        View::assign('statusMap', $statusMap);
        View::assign('can_create_customer', $this->hasPermission('customer:create'));
        // M10 客户生命周期漏斗看板
        View::assign('funnel', CustomerLogic::lifecycleFunnel());
        View::assign('lifecycle_dict', $lifecycleDict);
        // v2.40.0 P1-7：客户行业字典
        View::assign('industry_dict', $industryDict);
        return View::fetch('mobile/customers');
    }

    /** 移动端公海池（v2.38.2） */
    public function customerPool()
    {
        $this->requirePermission('customer:view');
        $keyword = $this->getParam('keyword', '');
        [$page, $pageSize] = $this->mobilePage();
        $res = CustomerLogic::getPoolList($page, $pageSize, $keyword);
        $list = $res['list'] ?? [];

        if (request()->isAjax()) {
            return json(['code' => 0, 'msg' => 'ok', 'data' => $list, 'total' => $res['total'] ?? 0]);
        }
        View::assign('list', $list);
        View::assign('total', $res['total'] ?? 0);
        View::assign('keyword', $keyword);
        return View::fetch('mobile/customer_pool');
    }

    /** 移动端客户新建/编辑表单（P1-a：补齐移动端客户 CRUD 的新建入口） */
    public function customerForm($id = 0)
    {
        // UX 门控：与 PC 端 Customer::create 同口径（编辑→customer:edit，新建→customer:create）
        $this->requirePermission($id ? 'customer:edit' : 'customer:create');
        $id = (int)$this->getParam('id', $id);
        $customer = $id ? CustomerLogic::getDetail($id) : null;
        if ($id && $customer && $customer['owner_id'] != 0
            && !AuthLogic::canAccessRecord($customer['owner_id'], $customer['dept_id'] ?? 0)) {
            throw new \think\exception\HttpException(403, '无权查看该客户');
        }
        View::assign('customer', $customer);
        View::assign('is_edit', (bool)$id);
        return View::fetch('mobile/customer_form');
    }

    /** 移动端客户详情（S 级独立视图，Phase 4） */
    public function customerDetail($id)
    {
        $this->requirePermission('customer:view');
        $id = (int)$id;
        $isAdmin = !empty($this->user['is_admin']);
        // v2.38.3：360° 聚合视图，一次查询返回基本信息+信用+关联合同+回款+跟进+统计
        $dash = CustomerLogic::getDashboard($id, $isAdmin);
        if (empty($dash)) {
            throw new \think\exception\HttpException(404, '客户不存在');
        }
        $customer = $dash['customer'];
        // v2.45.0：统一访问判定（公海/数据范围/显式共享/集团祖先/合同引用者）
        if (!CustomerLogic::canAccessCustomer($this->userId, $customer, (int)($this->user['dept_id'] ?? 0))) {
            throw new \think\exception\HttpException(403, '无权查看该客户');
        }

        // REV-31：移动端客户详情补充认领/转移/释放动作。公海客户(owner_id=0)可认领；归属人为当前用户时可转移/释放
        $isPublicPool = ((int)($customer['owner_id'] ?? 0) === 0);
        $isOwner      = ((int)($customer['owner_id'] ?? 0) === $this->userId);
        $statusMap = [1 => '正常', 0 => '禁用'];
        View::assign('customer', $customer);
        View::assign('owner_name', $dash['owner_name']);
        View::assign('contracts', $dash['contracts']);
        View::assign('contract_total', $dash['contract_total']); // N-m4 关联合同总数
        View::assign('contract_limit', $dash['contract_limit']); // N-m4 首屏展示上限
        View::assign('payments', $dash['payments']);
        View::assign('activities', $dash['activities']);
        View::assign('stats', $dash['stats']);
        View::assign('statusMap', $statusMap);
        // v2.38.9：生命周期标签（与漏斗同色）
        View::assign('lifecycle_dict', dict('customer_lifecycle'));
        // v2.40.0 P1-7：客户行业标签
        View::assign('industry_dict', dict('customer_industry'));
        View::assign('is_public_pool', $isPublicPool);
        View::assign('is_owner', $isOwner);
        // v2.40.0 P0-2：手动记录跟进权限（复用 customer:edit）
        View::assign('can_edit', $this->hasPermission('customer:edit'));
        // v2.48.0：详情页超管标记（记录跟进入口=负责人或超管）+ 当前用户名（保存后局部插入列表用）
        View::assign('is_super_admin', $this->isSuperAdmin());
        View::assign('me_name', $this->user['name'] ?? '我');
        // 2026-08-03：转移选人弹窗初始列表（非管理员仅同部门、排除本人；与审批转交同权限范围）
        View::assign('transfer_users', UserLogic::getTransferTargets(
            $this->userId,
            !empty($this->user['is_admin']),
            (int)($this->user['dept_id'] ?? 0)
        ));
        // v2.47.8：移动端「添加共享」弹层用户放开全公司（$scopeAll=true，与 PC 共享选人一致）
        View::assign('share_target_options', UserLogic::getTransferTargets(
            $this->userId,
            !empty($this->user['is_admin']),
            (int)($this->user['dept_id'] ?? 0),
            '',
            true
        ));
        // v2.38.3：M9 独立联系人矩阵
        // v2.38.11: 主联系人字段兜底（customer_contact 空时展示 customer.contact_name）
        View::assign('contacts', CustomerContactLogic::getListForDisplay($id, $customer));
        View::assign('contact_roles', CustomerContactLogic::ROLES);
        // v2.38.14：往来汇总（360 交易合同口径）+ 最近动态——360 能力内嵌客户详情（统计卡升级）
        $g360 = PartyLogic::get360('customer', $id);
        View::assign('g360', !empty($g360['ok']) ? $g360 : null);
        // v2.45.0：移动端共享成员（只读展示）+ 集团归属（管理动作在 PC 端）
        View::assign('share_list', CustomerLogic::getShares($id));
        // 2026-08-11：移动端补共享/集团管理入口（负责人/超管可设，复用 PC 端 AJAX 接口 share/unshare/join-group/group-info）
        View::assign('share_can_manage', $this->isSuperAdmin() || ((int)($customer['owner_id'] ?? 0) === $this->userId));
        View::assign('share_departments', Db::name('department')->field('id, name')->order('id', 'asc')->select()->toArray());
        $parentName = '';
        if (!empty($customer['parent_id'])) {
            $parentName = (string)Db::name('customer')->where('id', (int)$customer['parent_id'])->value('name');
        }
        View::assign('parent_name', $parentName);
        // v2.47.8：集团归属可选父客户服务端注入（移动端弹层打开即就绪，不依赖 AJAX 往返）
        View::assign('group_options', CustomerLogic::getOptionsForSelect(100));
        return View::fetch('mobile/customer_detail');
    }

    /** 移动端供应商列表（P1-b：补齐核心主数据移动可用性） */
    public function suppliers()
    {
        $this->requirePermission('supplier:view');
        $keyword = $this->getParam('keyword', '');
        $type    = $this->getParam('type', '');
        [$page, $pageSize] = $this->mobilePage();

        $res   = SupplierLogic::getList($page, $pageSize, ['keyword' => $keyword, 'type' => $type]);
        $list  = $res['list'];
        $total = $res['total'];

        $types = dict('supplier_type');
        if (empty($types)) {
            $types = ['MEDIA' => '媒体渠道', 'MATERIAL' => '物料供应商', 'SERVICE' => '服务供应商', 'OTHER' => '其他'];
        }
        $statusMap = [1 => '正常', 0 => '禁用'];

        if (request()->isAjax()) {
            // P3b-8：统一返回 statusMap + types 字典，与 contracts/customers 列表口径一致
            return json([
                'code'      => 0,
                'msg'       => 'ok',
                'data'      => $list,
                'total'     => $total,
                'statusMap' => $statusMap,
                'types'     => $types,
            ]);
        }
        View::assign('list', $list);
        View::assign('total', $total);
        View::assign('keyword', $keyword);
        View::assign('type', $type);
        View::assign('types', $types);
        View::assign('statusMap', $statusMap);
        View::assign('can_create_supplier', $this->hasPermission('supplier:create'));
        return View::fetch('mobile/suppliers');
    }

    /** 移动端供应商详情（P1-b） */
    public function supplierDetail($id)
    {
        $this->requirePermission('supplier:view');
        $id = (int)$id;
        $s = SupplierLogic::getDetail($id);
        if (!$s) {
            throw new \think\exception\HttpException(404, '供应商不存在');
        }
        if (!AuthLogic::canAccessRecord($s['owner_id'] ?? 0, $s['dept_id'] ?? 0)) {
            throw new \think\exception\HttpException(403, '无权查看该供应商');
        }

        $ownerName = UserLogic::getName((int)($s['owner_id'] ?? 0));
        $types = dict('supplier_type');
        if (empty($types)) {
            $types = ['MEDIA' => '媒体渠道', 'MATERIAL' => '物料供应商', 'SERVICE' => '服务供应商', 'OTHER' => '其他'];
        }
        $statusMap = [1 => '正常', 0 => '禁用'];

        // v2.38.14：往来汇总 + 关联合同 + 最近动态（360 能力内嵌，供应商详情补全）
        $g360 = PartyLogic::get360('supplier', $id);
        View::assign('g360', !empty($g360['ok']) ? $g360 : null);

        View::assign('supplier', $s);
        View::assign('owner_name', $ownerName);
        View::assign('type_text', $types[$s['type']] ?? $s['type']);
        View::assign('statusMap', $statusMap);
        return View::fetch('mobile/supplier_detail');
    }

    /** 移动端供应商新建/编辑表单（P1-b） */
    public function supplierForm($id = 0)
    {
        // UX 门控：与 PC 端 Supplier::create 同口径（编辑→supplier:edit，新建→supplier:create）
        $this->requirePermission($id ? 'supplier:edit' : 'supplier:create');
        $id = (int)$this->getParam('id', $id);
        $s = $id ? SupplierLogic::getDetail($id) : null;
        if ($id && $s && !AuthLogic::canAccessRecord($s['owner_id'] ?? 0, $s['dept_id'] ?? 0)) {
            throw new \think\exception\HttpException(403, '无权查看该供应商');
        }
        $types = dict('supplier_type');
        if (empty($types)) {
            $types = ['MEDIA' => '媒体渠道', 'MATERIAL' => '物料供应商', 'SERVICE' => '服务供应商', 'OTHER' => '其他'];
        }
        View::assign('supplier', $s);
        View::assign('is_edit', (bool)$id);
        View::assign('types', $types);
        return View::fetch('mobile/supplier_form');
    }

    /** 移动端合同新建/编辑表单（消除工作台「新建合同」回退桌面 /contract/create 的缺口） */
    public function contractForm($id = 0)
    {
        // P2-17：表单门控改为 contract:create，与提交保存(contractSubmit/save)权限一致，避免"能进表单不能存"
        $this->requirePermission('contract:create');
        $id = (int)$this->getParam('id', $id);
        $contract = $id ? ContractLogic::getDetail($id) : null;

        // 越权防护：编辑页仅允许归属人/创建人或管理员打开
        if ($contract && !AuthLogic::canAccessRecord($contract['owner_id'], $contract['dept_id'] ?? 0)) {
            throw new \think\exception\HttpException(403, '无权查看该合同');
        }
        // 仅草稿 / 驳回状态可编辑
        if ($contract && !in_array($contract['status'], [ContractLogic::STATUS_DRAFT, ContractLogic::STATUS_REJECTED])) {
            throw new \think\exception\HttpException(403, '当前状态不可编辑');
        }

        // 本公司主体（默认带出签约主体）
        $companies = CompanyLogic::getList();
        $defaultCompanyId = CompanyLogic::getDefaultId();

        View::assign('contract', $contract);
        View::assign('is_edit', (bool)$id);
        View::assign('categories', contract_categories());
        // 去模板落地（Phase 1.5）：合同创建不再集成模板下拉，移除模板查询
        View::assign('companies', $companies);
        View::assign('default_company_id', $defaultCompanyId);
        // M13：字段配置化——供 ContractFormConfig 渲染器使用（项目/框架合同下拉）
        View::assign('projects', \app\common\logic\ProjectLogic::options());
        View::assign('parent_contracts', \app\common\logic\ContractLogic::getFrameworkOptions(500));
        // v2.47.x：当前登录用户（我方侧联系人/电话按登录用户带出）
        View::assign('current_user', [
            'name'   => $this->user['name'] ?? '',
            'mobile' => $this->user['mobile'] ?? '',
        ]);
        return View::fetch('mobile/contract_form');
    }

    /** 移动财务模块（P1-d：补齐工作台「财务概览」原生页，复用既有收支概览 + AJAX 接口） */
    public function finance()
    {
        // 拥有回款或发票查看权限即可进入
        $this->financialGate(); // v2.38.1 统一门面

        // v2.38.1：XHR 请求直接返回 JSON 摘要，避免重复渲染完整 HTML
        if (request()->header('X-Requested-With') === 'XMLHttpRequest') {
            $sum = FinanceLogic::getSummary();
            return json_success($sum);
        }

        // 收支概览：统一下沉至 FinanceLogic::getSummary（Phase 1.2 提取，消除与桌面端重复与 Db 直查）
        $sum = FinanceLogic::getSummary();
        // v2.38.3 回款预测：服务端预渲染，避免页面加载时预测卡空白
        // （loadFinSummary 仅在切换周期时调用，初始不触发，故必须服务端带数）
        $sum['forecast30'] = FinanceLogic::getForecast(30);
        $sum['forecast60'] = FinanceLogic::getForecast(60);

        View::assign('fin_summary', $sum);
        // F7：移动端发票申请（申请→审批→开票；申请表单字段由 InvoiceFormConfig 渲染，后台可配）
        $companies = \app\common\logic\CompanyLogic::getListWithDefault();
        // 2026-08-11：开票客户数据源改后端搜索，不再向前端注入全量客户
        View::assign('m_can_apply_invoice', $this->hasPermission('invoice:apply') || $this->hasPermission('invoice:create'));
        View::assign('m_can_create_invoice', $this->hasPermission('invoice:create'));
        // UX 门控：登记回款/确认收款入口按 payment:create 权限渲染（与工作台快捷操作口径一致）
        View::assign('m_can_pay', $this->hasPermission('payment:create'));
        View::assign('m_invoice_fields', \app\common\form\InvoiceFormConfig::mobileRender([], ['companies' => $companies]));
        // F9：移动端发票申请表单字段联动（form-linkage.js 通用组件）
        View::assign('m_invoice_form_rules', \app\common\form\InvoiceFormConfig::rules());
        // 客户数据源：已改后端搜索，__formData 不再注入全量客户
        View::assign('m_invoice_customers', []);
        return View::fetch('mobile/finance');
    }

    /**
     * 移动端报表概览（CR-17 补齐：与桌面驾驶舱对齐的核心经营数字）
     * 复用 Desktop DashboardController 的统计口径，但按移动端做精简摘要展示
     */
    public function reports()
    {
        $this->financialGate(); // v2.38.1 统一门面：与 PC 端报表权限一致
        // 移动端报表概览统一下沉至 ReportLogic::getMobileSummary（Phase 1.3：合并 6 类统计，消除 Db 直查）
        $summary = ReportLogic::getMobileSummary($this->user);
        View::assign($summary);
        View::assign('can_view_project', $this->hasPermission('project:view'));
        return View::fetch('mobile/reports');
    }

    /**
     * 移动端经营周报（v2.47.0）：总经理每周一例会参考，钉钉通知/站内信点击直达本页。
     * 完整周报（全公司+部门+合同明细）复用 WeeklyReportLogic，支持 ?week=周一日期 回溯。
     * 权限为 dashboard:company——周报为全公司口径，仅总经理/超管可见。
     */
    public function weeklyReport()
    {
        $this->requirePermission('dashboard:company');
        $week = $this->getParam('week', '');
        [$start, $end] = \app\common\logic\WeeklyReportLogic::weekRange(is_string($week) && $week !== '' ? $week : null);
        $data = \app\common\logic\WeeklyReportLogic::build($start, $end);
        View::assign('weekly', $data);
        View::assign('tab', 'more');
        return View::fetch('mobile/weekly');
    }

    /** 移动端发票申请独立页（v2.38.18）：与 PC /invoice-apply 同源——我的申请 + 申请开票表单
     *  申请表单字段由 InvoiceFormConfig::mobileRender 渲染（后台「系统设置→发票表单」可配），
     *  联动规则走 form-linkage.js 通用组件；提交复用 POST /ajax/invoice/add；
     *  我的申请列表由页面 JS 调 /ajax/invoice/my-list 异步加载（与财务页 loadInv 同口径） */
    public function invoiceApply()
    {
        $this->requirePermission('invoice:view');
        $canApply = $this->hasPermission('invoice:apply') || $this->hasPermission('invoice:create');
        $companies = \app\common\logic\CompanyLogic::getListWithDefault();
        // 2026-08-11：开票客户数据源改后端搜索，不再向前端注入全量客户
        View::assign('m_can_apply_invoice', $canApply);
        View::assign('m_invoice_fields', \app\common\form\InvoiceFormConfig::mobileRender([], ['companies' => $companies]));
        View::assign('m_invoice_form_rules', \app\common\form\InvoiceFormConfig::rules());
        // 客户数据源：已改后端搜索，__formData 不再注入全量客户
        View::assign('m_invoice_customers', []);
        return View::fetch('mobile/invoice_apply');
    }

    /**
     * 移动端财务报表「周期筛选」AJAX：返回按周期（本月/本季/本年/累计）收敛的财务统计与报表概览。
     * 复用 FinanceLogic::getSummaryByPeriod / ReportLogic::getMobileSummaryByPeriod（数据权限一致）。
     * @return \think\response\Json
     */
    public function financeSummary()
    {
        $this->financialGate(); // v2.38.1 统一门面
        $period = $this->getParam('period', 'all');
        if (!in_array($period, ['month', 'quarter', 'year', 'all'], true)) {
            $period = 'all';
        }
        $sum = FinanceLogic::getSummaryByPeriod($period);
        // v2.38.3 回款预测：未来30/60天预计收款
        $sum['forecast30'] = FinanceLogic::getForecast(30);
        $sum['forecast60'] = FinanceLogic::getForecast(60);
        return json_success($sum);
    }

    /**
     * 移动端报表概览「周期筛选」AJAX：返回按周期收敛的核心数字 + 回款概览 + 收支方向。
     * @return \think\response\Json
     */
    public function reportsSummary()
    {
        $this->financialGate(); // v2.38.1 统一门面：与 PC 端报表权限一致
        $period = $this->getParam('period', 'all');
        if (!in_array($period, ['month', 'quarter', 'year', 'all'], true)) {
            $period = 'all';
        }
        $summary = ReportLogic::getMobileSummaryByPeriod($this->user, $period);
        return json_success($summary);
    }

    /** 移动端归档查看（CR-17 补齐：仅展示已归档合同，走数据权限） */
    public function archive()
    {
        $this->requirePermission('contract:view');
        $keyword = $this->getParam('keyword', '');
        [$page, $pageSize] = $this->mobilePage();

        // 归档列表统一下沉至 ContractLogic::getArchivedList（Phase 1.4：消除 Db 直查）
        $res   = ContractLogic::getArchivedList($page, $pageSize, $keyword);
        $list  = $res['list'];
        $total = $res['total'];

        $statusMap = contract_status_map();     // CR-57：复用公共 helper
        $statusBadge = contract_status_badge();

        if (request()->isAjax()) {
            return json([
                'code'        => 0,
                'msg'         => 'ok',
                'data'        => $list,
                'total'       => $total,
                'statusMap'   => $statusMap,
                'statusBadge' => $statusBadge,
            ]);
        }

        View::assign('list', $list);
        View::assign('total', $total);
        View::assign('keyword', $keyword);
        View::assign('statusMap', $statusMap);
        View::assign('statusBadge', $statusBadge);
        View::assign('can_archive', $this->hasPermission('contract:edit')); // Phase 2.5：归档操作权限
        return View::fetch('mobile/archive');
    }

    /** 移动端项目列表（CR-17 补齐：与桌面 /project 对齐，走数据权限） */
    public function projects()
    {
        $this->requirePermission('project:view');
        $keyword = $this->getParam('keyword', '');
        [$page, $pageSize] = $this->mobilePage();

        $res  = ProjectLogic::getList($page, $pageSize, ['keyword' => $keyword]);
        $list = $res['list'] ?? [];

        $statusDict = dict('project_status');

        if (request()->isAjax()) {
            return json([
                'code'       => 0,
                'msg'        => 'ok',
                'data'       => $list,
                'total'      => $res['total'] ?? 0,
                'statusMap'  => $statusDict,  // P3b-8：统一字段名为 statusMap（保留 statusDict 别名兼容前端）
                'statusDict' => $statusDict,
            ]);
        }

        View::assign('list', $list);
        View::assign('total', $res['total'] ?? 0);
        View::assign('keyword', $keyword);
        View::assign('statusDict', $statusDict);
        return View::fetch('mobile/projects');
    }

    /** 移动端项目详情（Phase 2.6：复用 ProjectLogic，零 Db 直查） */
    public function projectDetail($id)
    {
        $this->requirePermission('project:view');
        $id = (int)$id;
        $project = \app\common\logic\ProjectLogic::getDetail($id);
        if (!$project) {
            throw new \think\exception\HttpException(404, '项目不存在');
        }

        // 行级数据权限校验（与桌面端一致）
        if (!\app\common\logic\AuthLogic::canAccessRecord($project['owner_id'] ?? 0, $project['dept_id'] ?? 0)) {
            throw new \think\exception\HttpException(403, '无权查看该项目');
        }

        // 经营聚合 + 关联合同列表（均下沉至 ProjectLogic）
        $aggregate = \app\common\logic\ProjectLogic::aggregate($id);
        // P2-3【M-Pf5】合同列表绑定上限（默认 200），并取总数供视图展示"查看全部"
        $contracts = \app\common\logic\ProjectLogic::getContracts($id);
        $contractTotal = \app\common\logic\ProjectLogic::getContractsCount($id);

        // 状态字典
        $statusDict = dict('project_status');

        View::assign('project', $project);
        View::assign('aggregate', $aggregate);
        View::assign('contracts', $contracts);
        View::assign('contract_total', $contractTotal);
        View::assign('contract_limit', 200);
        View::assign('statusDict', $statusDict);
        View::assign('statusMap', contract_status_map());
        return View::fetch('mobile/project_detail');
    }

    /** 统一移动端分页参数（REV-41：收敛各列表方法硬编码的每页 20 条）
     *  默认每页 20 条；后续若需调整仅改此处一处。
     *  @return array [page, pageSize]
     */
    protected function mobilePage(): array
    {
        $page     = max(1, (int)$this->getParam('page', 1));
        $pageSize = 20;
        return [$page, $pageSize];
    }

    /** 简单错误输出（与 ContractController 一致） */
    /** 相对方 360 移动列表（v2.38.11 原生移动版）：客户+供应商相对方，搜索/类型筛选 */
    public function partyList()
    {
        $this->requirePermission('party:view');
        $keyword = $this->getParam('keyword', '');
        $type    = $this->getParam('type', '');

        $parties = [];
        $truncated = false;
        $partyLimit = 200; // arch P1-3：合并列表单类型安全上限，防全量载入
        if ($type !== 'supplier') {
            $rows = CustomerLogic::getPartyRows($keyword, $partyLimit);
            if (count($rows) >= $partyLimit) {
                $truncated = true;
            }
            foreach ($rows as $r) {
                $r['type']       = 'customer';
                $r['type_label'] = '客户';
                $parties[] = $r;
            }
        }
        if ($type !== 'customer') {
            $rows = SupplierLogic::getPartyRows($keyword, $partyLimit);
            if (count($rows) >= $partyLimit) {
                $truncated = true;
            }
            foreach ($rows as $r) {
                $supType     = $r['type'] ?? '';              // supplier.type 字典码（MEDIA/SERVICE…）
                $r['type']       = 'supplier';
                $r['type_label'] = '供应商';
                // v2.38.11 修复：tag 显示供应商类型中文（原误显 'supplier'）
                $r['tag']        = $supType ? (dict('supplier_type')[$supType] ?? $supType) : '';
                $parties[] = $r;
            }
        }

        // v2.38.14：往来档案卡片「往来」行——批量汇总（复用 PartyLogic::summarizeBatch，与 PC 同源）
        $sums = PartyLogic::summarizeBatch($parties);
        foreach ($parties as $i => $p) {
            $parties[$i]['_sum'] = $sums[$p['type'] . ':' . $p['id']] ?? null;
        }

        View::assign('parties', $parties);
        View::assign('keyword', $keyword);
        View::assign('type', $type);
        View::assign('truncated', $truncated);
        return View::fetch('mobile/party');
    }

    /** 相对方 360 移动详情（v2.38.11 原生移动版）：复用 PartyLogic::get360 聚合
     *  S-01（R1 安全审查）：补 type 白名单 + 行级数据权限校验，与 PC 端 PartyController::view 对齐，
     *  堵住任意登录用户遍历 id 读取他人客户/供应商档案的越权（IDOR）。 */
    public function partyView($type = '', $id = 0)
    {
        $this->requirePermission('party:view');
        $id = (int)$id;
        if (!in_array($type, PartyLogic::TYPES, true) || $id <= 0) {
            throw new \think\exception\HttpException(404, '参数错误');
        }
        $data = PartyLogic::get360($type, $id);
        if (!$data['ok']) {
            throw new \think\exception\HttpException(404, $data['msg'] ?? '相对方不存在');
        }
        // 越权防护：基础档案按数据权限（owner/dept/admin）校验行级访问
        $base = $data['base'];
        if (!AuthLogic::canAccessRecord($base['owner_id'] ?? 0, $base['dept_id'] ?? 0)) {
            throw new \think\exception\HttpException(403, '无权查看该相对方');
        }
        View::assign('d', $data);
        return View::fetch('mobile/party_360');
    }
}
