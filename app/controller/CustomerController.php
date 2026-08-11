<?php
// +----------------------------------------------------------------------
// | 客户控制器
// +----------------------------------------------------------------------

namespace app\controller;

use think\facade\View;
use think\facade\Log;
use think\facade\Db;
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
        // M10 客户生命周期漏斗看板：各阶段客户数（POTENTIAL/ACTIVE/INACTIVE）
        View::assign('funnel', CustomerLogic::lifecycleFunnel());
        View::assign('lifecycle_dict', dict('customer_lifecycle'));
        // v2.45.0：共享徽标数据——共享给我的客户 ID（前端据此标记「共享」）
        View::assign('my_shared_ids', CustomerLogic::getSharedCustomerIds($this->userId, (int)($this->user['dept_id'] ?? 0)));
        return View::fetch();
    }

    /** 创建/编辑客户 */
    public function create($id = 0, $template = '')
    {
        $id = (int)($this->getParam('id', $id));
        // UX 门控：新建需 customer:create，编辑需 customer:edit（与 save() 口径一致，原 :view 过宽）
        $this->requirePermission($id ? 'customer:edit' : 'customer:create');
        $customer = $id ? CustomerLogic::getDetail($id) : null;
        // v2.45.0：统一访问判定（公海 / 数据范围 / 显式共享 / 集团祖先 / 合同引用者）
        if ($customer && !CustomerLogic::canAccessCustomer($this->userId, $customer, (int)($this->user['dept_id'] ?? 0))) {
            return '无权查看该客户';
        }
        View::assign('customer', $customer);
        return $template ? View::fetch($template) : View::fetch();
    }

    /** AJAX: 保存客户 */
    public function save()
    {
        $id = (int)$this->getPost('id', 0);
        // 新建需 customer:create，编辑需 customer:edit
        $this->requirePermission($id ? 'customer:edit' : 'customer:create');

        // 越权防护：编辑仅允许归属人/部门/管理员
        if ($id) {
            $existing = CustomerLogic::findRaw($id);
            if (!$existing || ($existing['owner_id'] != 0
                    && !AuthLogic::canAccessRecord($existing['owner_id'], $existing['dept_id'] ?? 0))) {
                return json_error('无权限编辑该客户');
            }
        }

        $data = [
            'name'           => $this->getPost('name', ''),
            'credit_code'    => $this->getPost('credit_code', ''),
            'legal_person'   => $this->getPost('legal_person', ''),
            'contact_name'   => $this->getPost('contact_name', ''),
            'contact_mobile' => $this->getPost('contact_mobile', ''),
            'contact_email'  => $this->getPost('contact_email', ''),
            'address'        => $this->getPost('address', ''),
            'source'         => $this->getPost('source', 'MANUAL'), // v2.38.2 客户来源
            'credit_score'   => (int)$this->getPost('credit_score', 100), // v2.38.3 信用评分(手动维护)
            'status'         => (int)$this->getPost('status', 1),
            // v2.38.9：客户生命周期（客户/成交/公海）——补全 M10 漏斗的字段编辑入口
            'lifecycle_status' => $this->getPost('lifecycle_status', 'ACTIVE'),
            // v2.40.0 P1-7：客户行业（GOV/REAL_ESTATE/FOOD_TOURISM/OTHER）
            'industry'         => $this->getPost('industry', ''),
        ];

        // 生命周期值白名单校验（防篡改：仅允许字典内值，非法回退 ACTIVE）
        if (!in_array($data['lifecycle_status'], ['POTENTIAL', 'ACTIVE', 'INACTIVE'], true)) {
            $data['lifecycle_status'] = 'ACTIVE';
        }

        // 行业值白名单校验（防篡改：仅允许字典内值，非法回退空）
        if (!in_array($data['industry'], ['GOV', 'REAL_ESTATE', 'FOOD_TOURISM', 'OTHER'], true)) {
            $data['industry'] = '';
        }

        if (empty($data['name'])) {
            return json_error('请输入客户名称');
        }

        // v2.38.2：新建时检测重复客户
        if (!$id) {
            $dup = CustomerLogic::checkDuplicate($data['name'], $data['credit_code'] ?? '');
            if (!empty($dup['duplicates'])) {
                $names = array_map(function($c){ return $c['name'] . '(#' . $c['id'] . ')'; }, $dup['duplicates']);
                $hint = empty($data['credit_code']) ? '' : '（信用代码匹配）';
                return json_error('可能存在重复客户：' . implode('、', $names) . $hint . '。若确认为不同客户请修改名称，或使用已有客户', 409);
            }
        }
        // CR-21：统一社会信用代码格式校验（非空时校验 18 位 + 校验码）
        if (!validate_credit_code($data['credit_code'])) {
            return json_error('统一社会信用代码格式不正确（应为 18 位）');
        }

        if ($id) {
            // M8 修复：编辑时用户改动过信用评分 → credit_manual=1 人工锁定，
            // 自动重算（recalcCreditScore）将跳过评分/等级覆盖；未改则保持原锁定状态。
            if ($existing && (int)($existing['credit_score'] ?? 100) !== $data['credit_score']) {
                $data['credit_manual'] = 1;
            }
            CustomerLogic::update($id, $data);
            AuditService::log($this->userId, 'update', 'customer', $id);
        } else {
            $data['owner_id'] = $this->userId;
            $data['dept_id']  = $this->user['dept_id'] ?? 0;
            $id = CustomerLogic::create($data);
            AuditService::log($this->userId, 'create', 'customer', $id);
        }

        return json_success(['id' => $id], '保存成功');
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
        // v2.45.0：统一访问判定（公海 / 数据范围 / 显式共享 / 集团祖先 / 合同引用者），
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
        View::assign('contact_roles', CustomerContactLogic::ROLES);
        // v2.38.14：往来汇总（360 交易合同口径）+ 最近动态——360 能力内嵌 PC 客户详情（统计 tab 升级，与移动端同源）
        $g360 = PartyLogic::get360('customer', (int)$id);
        View::assign('g360', !empty($g360['ok']) ? $g360 : null);
        // 2026-08-03：PC 端客户操作（认领/释放/转移，与移动端 REV-31 对齐）——归属人=本人可释放/转移，公海可认领
        View::assign('is_owner', ((int)$customer['owner_id'] ?? 0) === $this->userId);
        View::assign('is_public_pool', ((int)$customer['owner_id'] ?? 0) === 0);
        View::assign('can_edit', $this->hasPermission('customer:edit'));
        // 转移选人弹窗初始列表（启用用户、排除本人、非管理员仅同部门；与移动端同源 getTransferTargets）
        View::assign('transfer_users', \app\common\logic\UserLogic::getTransferTargets(
            $this->userId,
            !empty($this->user['is_admin']),
            (int)($this->user['dept_id'] ?? 0)
        ));
        // v2.45.0：共享设置面板数据（共享成员 + 是否可管理；集团树/汇总由前端懒加载 groupInfo）
        View::assign('share_can_manage', $this->canManageCustomer($customer));
        View::assign('share_list', CustomerLogic::getShares((int)$id));
        View::assign('share_target_options', \app\common\logic\UserLogic::getTransferTargets(
            $this->userId,
            !empty($this->user['is_admin']),
            (int)($this->user['dept_id'] ?? 0)
        ));
        View::assign('share_departments', Db::name('department')
            ->field('id, name')->order('id', 'asc')->select()->toArray());
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
        if (!$existing || ($existing['owner_id'] != 0
                && !AuthLogic::canAccessRecord($existing['owner_id'], $existing['dept_id'] ?? 0))) {
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

    /** 公海池 */
    public function pool()
    {
        $this->requirePermission('customer:view');
        $keyword = $this->getParam('keyword', '');
        list($page, $pageSize) = $this->getPageParams();
        [$sortField, $sortOrder] = $this->getSortParams([
            'id'         => 'id',
            'name'       => 'name',
            'created_at' => 'created_at',
        ], 'id', 'desc');
        $result = CustomerLogic::getPoolList($page, $pageSize, $keyword, [$sortField, $sortOrder]);

        if (request()->isAjax()) {
            return layui_table($result['list'], $result['total']);
        }

        View::assign('customers', $result['list']);
        View::assign('keyword', $keyword);
        return View::fetch();
    }

    /** AJAX: 认领客户 */
    public function claim($id)
    {
        $this->requirePermission('customer:edit');
        $result = CustomerLogic::claim((int)$id, $this->userId, $this->user['dept_id'] ?? 0, \app\common\logic\CustomerLogic::DAILY_CLAIM_LIMIT);
        if ($result === true) {
            return json_success(null, '认领成功');
        }
        return json_error(is_string($result) ? $result : '认领失败，该客户已被他人认领');
    }

    /** AJAX: 转移客户（2026-08-03：管理员可从公海直接分配，传 allowFromPool=true） */
    public function transfer($id)
    {
        $this->requirePermission('customer:edit');
        $toUserId      = (int)$this->getPost('to_user_id', 0);
        $isAdmin       = !empty($this->user['is_admin']);
        $allowFromPool = $isAdmin;
        if (CustomerLogic::transfer((int)$id, $this->userId, $toUserId, $allowFromPool)) {
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

    /** AJAX: 释放到公海 */
    public function release($id)
    {
        $this->requirePermission('customer:edit');
        try {
            if (CustomerLogic::releaseToPool((int)$id, $this->userId)) {
                return json_success(null, '已释放到公海');
            }
            return json_error('释放失败，客户不存在或已不属于您');
        } catch (\RuntimeException $e) {
            return json_error($e->getMessage());
        } catch (\Throwable $e) {
            Log::error('释放客户到公海失败', ['id' => (int)$id, 'error' => $e->getMessage()]);
            return json_error('释放失败，请稍后重试');
        }
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
     * 公海客户必须先认领；数据权限收敛到归属人或其部门。
     */
    public function addActivity($id)
    {
        $this->requirePermission('customer:edit');
        $customerId = (int)$id;
        $existing = CustomerLogic::findRaw($customerId);
        if (!$existing || !empty($existing['is_deleted'])) {
            return json_error('客户不存在');
        }
        if ((int)$existing['owner_id'] === 0) {
            return json_error('公海客户请先认领再记录跟进');
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
