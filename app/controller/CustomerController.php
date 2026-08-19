<?php
// +----------------------------------------------------------------------
// | 客户控制器
// +----------------------------------------------------------------------

namespace app\controller;

use think\facade\View;
use think\facade\Log;
use think\facade\Db;
use think\facade\Cache;
use app\BaseController;
use app\common\logic\CustomerLogic;
use app\common\logic\CustomerContactLogic;
use app\common\logic\AuthLogic;
use app\common\logic\PartyLogic;        // v2.38.14 往来汇总（360 口径）
use app\common\service\AuditService;

class CustomerController extends BaseController
{
    /** 客户列表 */
    public function index()
    {
        $this->requirePermission('customer:view');
        $filter = [
            'keyword' => $this->getParam('keyword', ''),
            'status'  => $this->getParam('status', ''),
            // v2.38.9：客户生命周期筛选
            'lifecycle_status' => $this->getParam('lifecycle_status', ''),
        ];

        // v2.52.2：查看范围「我的客户/全部客户」——默认「我的客户」，localStorage 记忆由前端覆盖 URL 参数重拉；
        // owner_id=me / scope=me → 本人；显式归属人（数字）→ 该归属人；scope=all → 数据范围内全部
        $ownerId  = $this->getParam('owner_id', '');
        $scopeParam = $this->getParam('scope', '');
        $visScope = AuthLogic::visibility();
        $canScopeToggle = $visScope['has_all'] || !empty($visScope['dept_ids']);   // 仅能查看他人客户的账号显示切换
        if ($scopeParam === 'me' || $ownerId === 'me') {
            $filter['owner_id'] = $this->userId;
        } elseif ($scopeParam === 'all') {
            // 全部客户：不加归属人过滤（数据范围兜底）
        } elseif ($ownerId !== '' && is_numeric($ownerId)) {
            $filter['owner_id'] = (int)$ownerId;
        } else {
            $filter['owner_id'] = $this->userId;   // 无参数（独立进入）默认「我的客户」
        }

        list($page, $pageSize) = $this->getPageParams();
        [$sortField, $sortOrder] = $this->getSortParams([
            'id'         => 'id',
            'name'       => 'name',
            'created_at' => 'created_at',
        ], 'id', 'desc');
        $result = CustomerLogic::getList($page, $pageSize, $filter, [$sortField, $sortOrder]);

        if (request()->isAjax()) {
            return layui_table($result['list'], $result['total']);
        }

        View::assign('customers', $result['list']);
        View::assign('filter', $filter);
        // v2.52.2：查看范围切换（前端 customer.js 据此渲染，scope 由前端 URL/记忆决定）
        View::assign('can_scope_toggle', $canScopeToggle);
        // v2.52.x：删除入口门控（与后端 delete() 守卫同口径；JS 用 window._canDeleteCustomer 渲染操作列按钮）
        View::assign('can_delete', $this->hasPermission('customer:delete'));
        // M10 客户生命周期漏斗看板：各阶段客户数（POTENTIAL/ACTIVE）
        View::assign('funnel', CustomerLogic::lifecycleFunnel());
        View::assign('lifecycle_dict', dict('customer_lifecycle'));
        // v2.45.0：共享徽标数据——共享给我的客户 ID（前端据此标记「共享」）
        View::assign('my_shared_ids', CustomerLogic::getSharedCustomerIds($this->userId, (int)($this->user['dept_id'] ?? 0)));
        // v2.47.8：我共享出去的客户 ID（created_by=本人；前端标记「我共享」方向）
        View::assign('my_shared_out_ids', array_values(array_unique(array_map('intval',
            Db::name('customer_share')->where('created_by', $this->userId)->column('customer_id')))));
        // v2.47.8：超管判定统一走 isSuperAdmin（is_admin=1 ∪ admin 角色），供列表快捷共享按钮可见性
        View::assign('is_super_admin', AuthLogic::isSuperAdmin($this->userId, $this->user ?? []));
        // v2.47.8：列表快捷共享弹层数据（全公司用户 + 部门，复用详情页口径）
        View::assign('share_target_options', \app\common\logic\UserLogic::getTransferTargets(
            $this->userId,
            !empty($this->user['is_admin']),
            (int)($this->user['dept_id'] ?? 0),
            '',
            true
        ));
        View::assign('share_departments', Db::name('department')->field('id, name')->order('id', 'asc')->select()->toArray());
        return View::fetch();
    }

    /** 创建/编辑客户 */
    public function create($id = 0, $template = '')
    {
        $id = (int)($this->getParam('id', $id));
        // UX 门控：新建需 customer:create，编辑需 customer:edit（与 save() 口径一致，原 :view 过宽）
        $this->requirePermission($id ? 'customer:edit' : 'customer:create');
        $customer = $id ? CustomerLogic::getDetail($id) : null;
        // v2.45.0：统一访问判定（数据范围 / 显式共享 / 集团祖先 / 合同引用者）
        if ($customer && !CustomerLogic::canAccessCustomer($this->userId, $customer, (int)($this->user['dept_id'] ?? 0))) {
            return '无权查看该客户';
        }
        View::assign('customer', $customer);
        View::assign('lifecycle_dict', dict_options('customer_lifecycle'));
        View::assign('industry_dict', dict_options('customer_industry'));
        return $template ? View::fetch($template) : View::fetch();
    }

    /** AJAX: 保存客户 */
    public function save()
    {
        $idemKey=(string)$this->getPost('idempotency_key','');
        try{$cached=\app\common\service\IdempotencyService::cached($this->userId,'customer.save',$idemKey);if($cached)return json_success($cached,'保存成功');}catch(\RuntimeException $e){return json_error($e->getMessage());}
        $id = (int)$this->getPost('id', 0);
        // 新建需 customer:create，编辑需 customer:edit
        $this->requirePermission($id ? 'customer:edit' : 'customer:create');

        // 越权防护：编辑仅允许归属人/部门/管理员
        if ($id) {
            $existing = CustomerLogic::findRaw($id);
            if (!$existing || !AuthLogic::canAccessRecord($existing['owner_id'], $existing['dept_id'] ?? 0)) {
                return json_error('无权限编辑该客户');
            }
        }

        $data = [
            'name'           => $this->getPost('name', ''),
            'credit_code'    => $this->getPost('credit_code', ''),
            'legal_person'   => $this->getPost('legal_person', ''),
            'contact_name'   => $this->getPost('contact_name', ''),
            'contact_mobile' => $this->getPost('contact_mobile', ''),
            'address'        => $this->getPost('address', ''),
            'remark'         => mb_substr(trim($this->getPost('remark', '')), 0, 255),
            'source'         => $this->getPost('source', ''), // v2.51.4 客户来源：新建表单必填（快速建档不传该字段则跳过校验）
            'status'         => (int)$this->getPost('status', 1),
            // 客户生命周期（客户/成交）——补全 M10 漏斗的字段编辑入口
            'lifecycle_status' => $this->getPost('lifecycle_status', $id ? 'ACTIVE' : 'POTENTIAL'),
            // v2.40.0 P1-7：客户行业（GOV/REAL_ESTATE/FOOD_TOURISM/OTHER）
            'industry'         => $this->getPost('industry', ''),
        ];

        // 生命周期值白名单校验（业务状态码固定，字典仅负责可配置显示名称）
        if (!in_array($data['lifecycle_status'], ['POTENTIAL', 'ACTIVE'], true)) {
            $data['lifecycle_status'] = $id ? 'ACTIVE' : 'POTENTIAL';
        }

        // 行业值白名单校验：允许后台「客户行业」字典新增项，非法值仍回退未设置。
        if ($data['industry'] !== '' && !array_key_exists($data['industry'], dict('customer_industry'))) {
            $data['industry'] = '';
        }
        // v2.51.4：来源值白名单校验，非法值回退空
        if ($data['source'] !== '' && !array_key_exists($data['source'], dict('customer_source'))) {
            $data['source'] = '';
        }

        if (empty($data['name'])) {
            return json_error('请输入客户名称');
        }
        // v2.51.4：新增客户时来源/行业必填——仅当请求显式带该字段（正式表单）时校验，
        // 合同/发票等「快速建档」只传基础字段，不强制，避免轻量流程被拦截。
        if (!$id) {
            if ($this->getPost('source', null) !== null && $data['source'] === '') {
                return json_error('请选择客户来源');
            }
            if ($this->getPost('industry', null) !== null && $data['industry'] === '') {
                return json_error('请选择客户行业');
            }
        }

        // v2.38.2：新建时检测重复客户
        if (!$id) {
            $dup = CustomerLogic::checkDuplicate($data['name'], $data['credit_code'] ?? '', $data['contact_mobile'] ?? '');
            if (!empty($dup['duplicates'])) {
                $names = array_map(function($c){ return $c['name'] . '(#' . $c['id'] . ')'; }, $dup['duplicates']);
                if (!empty($dup['exact_credit'])) {
                    return json_error('统一社会信用代码已存在：' . implode('、', $names) . '，禁止重复创建', 409);
                }
                $confirmed = $this->getPost('duplicate_confirmed','') === '1';
                if (!$this->isSuperAdmin() || !$confirmed) {
                    $hint = !empty($dup['exact_phone']) ? '（联系电话相同）' : '（名称疑似重复）';
                    return json_error('可能存在重复客户：' . implode('、', $names) . $hint . '。仅管理员确认后可继续创建', 409, ['requires_admin_confirmation'=>true]);
                }
            }
        }
        // CR-21：统一社会信用代码格式校验（非空时校验 18 位 + 校验码）
        if (!validate_credit_code($data['credit_code'])) {
            return json_error('统一社会信用代码格式不正确（应为 18 位）');
        }

        if ($id) {
            CustomerLogic::update($id, $data);
            AuditService::log($this->userId, 'update', 'customer', $id);
        } else {
            $data['owner_id'] = $this->userId;
            $data['dept_id']  = $this->user['dept_id'] ?? 0;
            $id = CustomerLogic::create($data);
            AuditService::log($this->userId, 'create', 'customer', $id);
        }

        $result=['id'=>$id];\app\common\service\IdempotencyService::remember($this->userId,'customer.save',$idemKey,$result);
        return json_success($result, '保存成功');
    }

    /** 客户详情（v2.38.3：360° 聚合视图） */
    public function detail($id)
    {
        $this->requirePermission('customer:view');
        $isAdmin = !empty($this->user['is_admin']);
        // 质量修复：先查基础客户并鉴权，再跑聚合（原先 getDashboard 聚合完才鉴权，
        // 无权限用户也会触发合同/回款等聚合查询，扩大数据外泄面）
        $base = CustomerLogic::getDetail((int)$id);
        if (empty($base)) return '客户不存在';
        // v2.45.0：统一访问判定（数据范围 / 显式共享 / 集团祖先 / 合同引用者），
        // 替换原 owner||canAccessRecord||getSharedViewers 三段式
        if (!CustomerLogic::canAccessCustomer($this->userId, $base, (int)($this->user['dept_id'] ?? 0))) {
            return '无权查看该客户';
        }
        $dash = CustomerLogic::getDashboard((int)$id, $isAdmin);
        if (empty($dash)) return '客户不存在';
        $customer = $dash['customer'];

        View::assign('customer', $customer);
        View::assign('owner_name', $dash['owner_name']);
        View::assign('contracts', $dash['contracts']);
        View::assign('contract_total', $dash['contract_total']);
        View::assign('contract_limit', $dash['contract_limit']);
        View::assign('payments', $dash['payments']);
        View::assign('activities', $dash['activities']);
        View::assign('stats', $dash['stats']);
        // v2.38.9：生命周期展示
        View::assign('lifecycle_dict', dict('customer_lifecycle'));
        // v2.40.0 P1-7：客户行业展示
        View::assign('industry_dict', dict('customer_industry'));
        // v2.38.3：M9 独立联系人矩阵
        // v2.38.11: 主联系人字段兜底（customer_contact 空时展示 customer.contact_name）
        View::assign('contacts', CustomerContactLogic::getListForDisplay((int)$id, $customer));
        // v2.38.14：往来汇总（360 交易合同口径）+ 最近动态——360 能力内嵌 PC 客户详情（统计 tab 升级，与移动端同源）
        $g360 = PartyLogic::get360('customer', (int)$id);
        View::assign('g360', !empty($g360['ok']) ? $g360 : null);
        // PC 端客户操作（转移）——归属人=本人可转移
        View::assign('is_owner', ((int)$customer['owner_id'] ?? 0) === $this->userId);
        View::assign('can_edit', $this->hasPermission('customer:edit'));
        // v2.52.x：删除入口门控（与后端 delete() 守卫同口径，无权限不渲染删除按钮）
        View::assign('can_delete', $this->hasPermission('customer:delete'));
        // 转移选人弹窗初始列表（启用用户、排除本人、非管理员仅同部门；与移动端同源 getTransferTargets）
        View::assign('transfer_users', \app\common\logic\UserLogic::getTransferTargets(
            $this->userId,
            !empty($this->user['is_admin']),
            (int)($this->user['dept_id'] ?? 0)
        ));
        // v2.45.0：共享设置面板数据（共享成员 + 是否可管理；集团树/汇总由前端懒加载 groupInfo）
        View::assign('share_can_manage', $this->canManageCustomer($customer));
        View::assign('share_list', CustomerLogic::getShares((int)$id));
        // v2.47.8：共享选人放开为全公司（$scopeAll=true，搜索式选择器）；转移/转交仍限同部门
        View::assign('share_target_options', \app\common\logic\UserLogic::getTransferTargets(
            $this->userId,
            !empty($this->user['is_admin']),
            (int)($this->user['dept_id'] ?? 0),
            '',
            true
        ));
        View::assign('share_departments', Db::name('department')
            ->field('id, name')->order('id', 'asc')->select()->toArray());
        // v2.47.8：集团归属搜索选择器数据源（全量客户，排除自身在视图端处理）
        View::assign('group_options', CustomerLogic::getOptionsForSelect(100));
        // v2.51.4：基本信息集团归属标注——成员显示所属集团名；根节点显示子公司数
        $group_parent_name = null;
        $group_child_count = 0;
        $pid = (int)($customer['parent_id'] ?? 0);
        if ($pid > 0) {
            $group_parent_name = Db::name('customer')->where('id', $pid)->where('is_deleted', 0)->value('name');
        } else {
            $group_child_count = (int)Db::name('customer')->where('parent_id', (int)$id)->where('is_deleted', 0)->count();
        }
        View::assign('group_parent_name', $group_parent_name);
        View::assign('group_child_count', $group_child_count);
        return View::fetch();
    }

    /** 客户编辑页 */
    public function edit($id)
    {
        return $this->create($id, 'customer/create');
    }

    /** AJAX: 删除客户 */
    public function delete()
    {
        $this->requirePermission('customer:delete');
        $id = (int)$this->getPost('id', 0);
        $existing = CustomerLogic::findRaw($id);
        if (!$existing || !AuthLogic::canAccessRecord($existing['owner_id'], $existing['dept_id'] ?? 0)) {
            return json_error('无权限删除该客户');
        }
        // CR-16：删除前检查关联活跃合同，存在则拒绝并提示合同编号
        $blockers = \app\common\logic\CustomerLogic::deleteBlockers($id);
        if (!empty($blockers)) {
            return json_error('删除失败：' . implode('；', $blockers) . '。请先解除关联');
        }
        if (CustomerLogic::softDelete($id)) {
            AuditService::log($this->userId, 'delete', 'customer', $id);
            return json_success(null, '已删除');
        }
        return json_error('删除失败');
    }

    /** AJAX: 转移客户 */
    public function transfer($id)
    {
        $this->requirePermission('customer:edit');
        $toUserId = (int)$this->getPost('to_user_id', 0);
        if (CustomerLogic::transfer((int)$id, $this->userId, $toUserId)) {
            return json_success(null, '转移成功');
        }
        return json_error('转移失败');
    }

    /** AJAX: 转移目标用户（选人弹窗搜索 + 分页，2026-08-03；与审批转交同权限范围：非管理员仅同部门） */
    public function transferTargets()
    {
        $this->requirePermission('customer:edit');
        $keyword  = trim((string)$this->getParam('keyword', ''));
        $page     = max(1, (int)$this->getParam('page', 1));
        $pageSize = 20;

        $res = \app\common\logic\UserLogic::getTransferTargetsPaged(
            $this->userId,
            !empty($this->user['is_admin']),
            (int)($this->user['dept_id'] ?? 0),
            $keyword,
            $page,
            $pageSize
        );

        return json_success([
            'list'     => $res['list'],
            'total'    => $res['total'],
            'page'     => $page,
            'has_more' => ($page * $pageSize) < $res['total'],
        ]);
    }

    // ===================== v2.45.0 客户协作共享 / 集团层级 =====================

    /** AJAX: 共享成员列表（负责人/超管可管理；查看者只读） */
    public function shareList($id)
    {
        $this->requirePermission('customer:view');
        $customerId = (int)$id;
        $customer = CustomerLogic::findRaw($customerId);
        if (!$customer || !empty($customer['is_deleted'])) {
            return json_error('客户不存在');
        }
        $canManage = $this->canManageCustomer($customer);
        if (!$canManage && !CustomerLogic::canAccessCustomer($this->userId, $customer, (int)($this->user['dept_id'] ?? 0))) {
            return json_error('无权查看该客户', 403);
        }
        return json_success([
            'can_manage' => $canManage,
            'shares'     => CustomerLogic::getShares($customerId),
        ]);
    }

    /** AJAX: 添加共享（仅客户负责人/超管） */
    public function share($id)
    {
        $this->requirePermission('customer:edit');
        $customerId = (int)$id;
        $customer = CustomerLogic::findRaw($customerId);
        if (!$customer || !empty($customer['is_deleted'])) {
            return json_error('客户不存在');
        }
        if (!$this->canManageCustomer($customer)) {
            return json_error('仅客户负责人或管理员可共享', 403);
        }
        $targetType = strtoupper((string)$this->getPost('target_type', 'USER'));
        $targetId   = (int)$this->getPost('target_id', 0);
        if (!in_array($targetType, ['USER', 'DEPT'], true)) {
            return json_error('共享对象类型不正确');
        }
        if ($targetId <= 0) {
            return json_error('请选择共享对象');
        }
        // 目标存在性校验
        if ($targetType === 'USER') {
            if (!Db::name('user')->where('id', $targetId)->where('status', 1)->value('id')) {
                return json_error('目标用户不存在或已禁用');
            }
            if ($targetId === (int)$customer['owner_id']) {
                return json_error('该客户负责人本就可见，无需共享');
            }
        } elseif (!Db::name('department')->where('id', $targetId)->value('id')) {
            return json_error('目标部门不存在');
        }
        if (CustomerLogic::shareCustomer($customerId, $targetType, $targetId, $this->userId)) {
            \app\common\service\AuditService::log($this->userId, 'customer_share', 'customer', $customerId,
                ['target_type' => $targetType, 'target_id' => $targetId, 'action' => 'add']);
            return json_success(null, '共享成功');
        }
        return json_error('共享失败');
    }

    /** AJAX: 撤销共享（仅客户负责人/超管） */
    public function unshare($id)
    {
        $this->requirePermission('customer:edit');
        $customerId = (int)$id;
        $customer = CustomerLogic::findRaw($customerId);
        if (!$customer || !empty($customer['is_deleted'])) {
            return json_error('客户不存在');
        }
        if (!$this->canManageCustomer($customer)) {
            return json_error('仅客户负责人或管理员可操作', 403);
        }
        $targetType = strtoupper((string)$this->getPost('target_type', 'USER'));
        $targetId   = (int)$this->getPost('target_id', 0);
        if (!in_array($targetType, ['USER', 'DEPT'], true)) {
            return json_error('共享对象类型不正确');
        }
        if (CustomerLogic::unshareCustomer($customerId, $targetType, $targetId)) {
            \app\common\service\AuditService::log($this->userId, 'customer_share', 'customer', $customerId,
                ['target_type' => $targetType, 'target_id' => $targetId, 'action' => 'remove']);
            return json_success(null, '已撤销共享');
        }
        return json_error('撤销失败或该共享不存在');
    }

    /** AJAX: 集团视图（树+汇总；以所在集团根为根） */
    public function groupInfo($id)
    {
        $this->requirePermission('customer:view');
        $customerId = (int)$id;
        $customer = CustomerLogic::findRaw($customerId);
        if (!$customer || !empty($customer['is_deleted'])) {
            return json_error('客户不存在');
        }
        if (!CustomerLogic::canAccessCustomer($this->userId, $customer, (int)($this->user['dept_id'] ?? 0))) {
            return json_error('无权查看该客户', 403);
        }
        // 定位集团根（沿 parent 向上取最顶层）
        $rootId = (int)$customerId;
        foreach (CustomerLogic::getGroupAncestorIds($customerId) as $aid) {
            $rootId = $aid;
        }
        return json_success([
            'root_id' => $rootId,
            'is_root' => $rootId === (int)$customerId,
            'current_parent_id' => (int)$customer['parent_id'],
            'parent_name' => (string)Db::name('customer')->where('id', (int)$customer['parent_id'])->value('name'),
            'root_name' => (string)Db::name('customer')->where('id', $rootId)->value('name'),
            'tree'    => CustomerLogic::getGroupTree($rootId),
            'summary' => CustomerLogic::getGroupSummary($rootId),
            // 加入集团可选父客户（全量客户；提交时后端仍会做访问权限与防环校验）
            'options' => CustomerLogic::getOptionsForSelect(100),
        ]);
    }

    /** AJAX: 加入集团（设置父客户；仅客户负责人/超管；防环） */
    public function joinGroup($id)
    {
        $this->requirePermission('customer:edit');
        $customerId = (int)$id;
        $customer = CustomerLogic::findRaw($customerId);
        if (!$customer || !empty($customer['is_deleted'])) {
            return json_error('客户不存在');
        }
        if (!$this->canManageCustomer($customer)) {
            return json_error('仅客户负责人或管理员可操作', 403);
        }
        $parentId = (int)$this->getPost('parent_id', 0);
        if ($parentId === $customerId) {
            return json_error('不能将客户设为自身的父客户');
        }
        if ($parentId > 0) {
            $parent = CustomerLogic::findRaw($parentId);
            if (!$parent || !empty($parent['is_deleted'])) {
                return json_error('父客户不存在或已被删除');
            }
            if (!CustomerLogic::canAccessCustomer($this->userId, $parent, (int)($this->user['dept_id'] ?? 0))) {
                return json_error('无权查看所选父客户', 403);
            }
            // 防环：目标父客户的祖先链中不得含本客户（本客户不能成为自己子孙的父）
            if (in_array($customerId, CustomerLogic::getGroupAncestorIds($parentId), true)) {
                return json_error('不能将客户加入其子孙客户名下（层级成环）');
            }
        }
        CustomerLogic::update($customerId, ['parent_id' => $parentId]);
        \app\common\service\AuditService::log($this->userId, 'customer_join_group', 'customer', $customerId,
            ['parent_id' => $parentId]);
        return json_success(null, $parentId > 0 ? '已加入集团' : '已取消集团归属');
    }

    /** 当前用户是否可管理该客户（负责人/超管；共享成员仅 VIEW 只读） */
    private function canManageCustomer(array $customer): bool
    {
        if ($this->isSuperAdmin()) return true;
        return (int)($customer['owner_id'] ?? 0) === $this->userId;
    }

    /**
     * AJAX: 记录跟进（v2.40.0 P0-2 手动录入：电话/拜访/会议/微信 + 下次跟进时间）
     * 数据权限收敛到归属人或其部门。
     */
    public function addActivity($id)
    {
        $this->requirePermission('customer:edit');
        $customerId = (int)$id;
        $existing = CustomerLogic::findRaw($customerId);
        if (!$existing || !empty($existing['is_deleted'])) {
            return json_error('客户不存在');
        }
        if (!AuthLogic::canAccessRecord($existing['owner_id'], $existing['dept_id'] ?? 0)) {
            return json_error('无权限记录该客户跟进');
        }
        $type = trim((string)$this->getPost('type', ''));
        if (!in_array($type, ['phone', 'visit', 'meeting', 'wechat'], true)) {
            return json_error('请选择有效的跟进方式');
        }
        $content = trim((string)$this->getPost('content', ''));
        if ($content === '') {
            return json_error('请填写跟进内容');
        }
        if (mb_strlen($content) > 500) {
            return json_error('跟进内容过长（最多500字）');
        }
        // 下次跟进时间（可选）：兼容 datetime-local(2026-08-06T14:30) 与 datetime
        $next = trim((string)$this->getPost('next_follow_at', ''));
        $nextFollowAt = null;
        if ($next !== '') {
            $ts = strtotime($next);
            if ($ts === false) {
                return json_error('下次跟进时间格式不正确');
            }
            $nextFollowAt = date('Y-m-d H:i:s', $ts);
        }

        CustomerLogic::addActivity($customerId, $this->userId, $type, $content, $nextFollowAt);
        // 跟进计划会即时改变提醒列表；清除当前负责人及操作人的短缓存，避免最多 60 秒看不到新状态。
        foreach (array_unique([(int)$existing['owner_id'], $this->userId]) as $uid) {
            if ($uid <= 0) continue;
            Cache::delete('remind_scan_' . $uid);
        }
        return json_success(null, '已记录跟进');
    }

    /** AJAX: 客户搜索 */
    public function search()
    {
        $this->requirePermission('customer:view');
        $q = $this->getParam('q', '');
        $uid   = $this->userId;
        $deptId = $this->user['dept_id'] ?? 0;
        $list = CustomerLogic::search($q, $uid, $deptId);
        return json_success($list);
    }

    /** v2.38.2 AJAX: 客户合并（v2.40.5：仅超管判定兼容钉钉部署 is_admin=0 + admin 角色） */
    public function merge()
    {
        if (!$this->isSuperAdmin()) return json_error('仅管理员可合并客户', 403);
        $masterId = (int)$this->getPost('master_id', 0);
        $targetId = (int)$this->getPost('target_id', 0);
        $result = CustomerLogic::merge($masterId, $targetId, $this->userId);
        if ($result['ok']) {
            return json_success($result['merged'], '合并成功');
        }
        return json_error($result['msg'] ?? '合并失败');
    }

    /** v2.38.2 AJAX: 查重扫描（v2.40.5：仅超管判定兼容钉钉部署 is_admin=0 + admin 角色） */
    public function duplicates()
    {
        if (!$this->isSuperAdmin()) return json_error('仅管理员', 403);
        $pairs = CustomerLogic::findDuplicates();
        return json_success($pairs);
    }
}
