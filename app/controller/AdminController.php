<?php
// +----------------------------------------------------------------------
// | 系统管理控制器
// +----------------------------------------------------------------------

namespace app\controller;

use think\facade\View;
use think\facade\Session;
use app\BaseController;
use app\common\service\RbacService;
use app\common\logic\AdminLogic;
use app\common\logic\UserLogic;

class AdminController extends BaseController
{
    /** 系统管理主页（兼容 ?tab= 参数与独立路由 /admin/<area>） */
    public function index($forceTab = null)
    {
        $this->requirePermission('system:user');
        $tab = $forceTab ?: $this->getParam('tab', 'user');

        // 用户（含角色ID列表）：在职成员(status=1)显示在部门用户列表；禁用(status=2)进入回收站，不显示于此
        $users = RbacService::getUserList(1, 100, '', 'active');
        $leaderMap = AdminLogic::getLeaderMap();
        // P2-11【M-A2】N+1 消除：批量预载全部用户角色映射（原逐用户 getUserRoleIds 查询）
        $activeRoleMap = AdminLogic::getUserRoleIdsMap(array_map('intval', array_column($users['list'], 'id')));
        foreach ($users['list'] as &$u) {
            $u['_role_ids'] = $activeRoleMap[(int)$u['id']] ?? [];
            $u['_is_leader'] = isset($leaderMap[$u['dept_id']]) && $leaderMap[$u['dept_id']] == $u['id'];
        }
        View::assign('users', $users['list']);
        // 回收站：被禁用(status=2)/锁定(status=0)的用户单独列表展示，可恢复（#1 修复：与弹窗"禁用"选项 value=2 对齐，避免用户被孤立）
        $disabled = RbacService::getUserList(1, 500, '', 'recycle');
        $disabledRoleMap = AdminLogic::getUserRoleIdsMap(array_map('intval', array_column($disabled['list'], 'id')));
        foreach ($disabled['list'] as &$du) {
            $du['_role_ids'] = $disabledRoleMap[(int)$du['id']] ?? [];
            $du['_is_leader'] = isset($leaderMap[$du['dept_id']]) && $leaderMap[$du['dept_id']] == $du['id'];
        }
        View::assign('disabledUsers', $disabled['list']);

        // v2.38.25：待交接队列（钉钉同步自动标记 need_handover=1 的疑似离职员工，管理员在此办理数据移交）
        // 仅列在职用户（禁用/回收站内的办理入口仍走原回收站列表）；附名下客户/合同/待审批数量供决策
        View::assign('handoverUsers', AdminLogic::getHandoverUsers());

        // 角色（含权限ID列表 + 自定义部门ID列表）——P2-11【M-A2】N+1 消除：批量预载权限/部门映射
        $roles = RbacService::getRoles();
        $roleIds = array_map('intval', array_column($roles, 'id'));
        $permMap = AdminLogic::getRolePermIdsMap($roleIds);
        $deptMap = AdminLogic::getRoleDeptIdsMap($roleIds);
        foreach ($roles as &$r) {
            $r['_permIds'] = $permMap[(int)$r['id']] ?? [];
            $r['_deptIds'] = $deptMap[(int)$r['id']] ?? [];
        }
        // v2.40.2：角色配置隐藏「全员默认基础权限」（普通员工能力集，判定层已默认开放）；
        // 其余高级权限照常可配
        $hiddenCodes = \app\common\logic\AuthLogic::DEFAULT_PERMISSION_CODES;
        $permissions = array_values(array_filter(
            RbacService::getPermissions(),
            static fn($p) => !in_array($p['code'], $hiddenCodes, true)
        ));
        View::assign('roles', $roles);
        View::assign('permissions', $permissions);

        // 审批流程：展示全部流程（含已停用 status=0），停用项在列表提供「恢复」入口；
        // 保留数据行以关联历史/进行中的审批实例，不影响已发起的审批。
        $flows = AdminLogic::getAllFlows();
        View::assign('flows', $flows);

        // 合同分类
        View::assign('categories', contract_categories());

        // 部门列表（用户列表「部门」列 + 按部门筛选下拉 + 编辑弹窗部门下拉；getDeptTree 返回扁平 [id,name,parent_id]）
        View::assign('depts', UserLogic::getDeptTree());

        // v2.28.2：视图层 Db::name 下沉 — 字典配置由 Logic 取数后注入视图
        View::assign('dicts', AdminLogic::getDicts());

        // F6：发票申请表单字段配置（钉钉表单式设计器：含停用项全量 + 字段类型白名单）
        View::assign('invoice_form_fields', AdminLogic::getInvoiceFormFields());
        View::assign('invoice_field_types', \app\common\form\InvoiceFormConfig::types());
        // F9：字段联动规则（设计器可配置；form-linkage.js 通用组件消费）
        View::assign('invoice_form_linkage', \app\common\form\InvoiceFormConfig::rules());

        // v2.38.25：发票流程编辑器（弹窗，参照合同审批编辑器）——Step2 角色/用户/公司/分类下拉数据
        View::assign('inv_builder_roles', $roles);
        View::assign('inv_builder_users', \app\common\logic\UserLogic::getOptions());
        View::assign('inv_builder_companies', \app\common\logic\CompanyLogic::getListWithDefault());
        View::assign('inv_builder_categories', contract_categories());

        View::assign('tab', $tab);
        // 部门负责人下拉仅超级管理员可见可配（is_admin=1 ∪ admin 角色），非超管编辑用户不提交 is_leader，避免静默清除负责人
        View::assign('is_super_admin', $this->isSuperAdmin());

        return View::fetch('index');
    }

    /** 各设置区独立路由入口（与 index 同源，仅默认 tab 不同） */
    public function user()     { return $this->index('user'); }
    public function role()     { return $this->index('role'); }
    public function flow()     { return $this->index('flow'); }
    public function dict()     { return $this->index('dict'); }
    public function dingtalk() { return $this->index('dingtalk'); }
    public function config()   { return $this->index('config'); }   // 系统配置（版权信息等，v2.34.0）
    public function invoiceForm() { return $this->index('invoice-form'); } // F6：发票申请表单设计器（钉钉表单式）

    /**
     * F6：保存发票申请表单字段配置（启停/排序/标签/必填 + 新增自定义字段 + F9 联动规则）
     * 请求：rows=[...]&new_fields=[...]&linkage=[{trigger_field,trigger_value,target_field,action,options}]
     * 安全：字段类型白名单；系统字段仅可改 enabled/排序，禁止删除/改 key；联动动作白名单 + 字段存在性校验。
     */
    public function saveInvoiceForm()
    {
        $this->requirePermission('system:user');
        $rows = json_decode((string)$this->getPost('rows', '[]'), true) ?: [];
        $newFields = json_decode((string)$this->getPost('new_fields', '[]'), true) ?: [];
        $linkage = json_decode((string)$this->getPost('linkage', '[]'), true) ?: [];
        // P2-10【M-A1】分层铁律下沉：字段/联动全量重存逻辑移至 AdminLogic，控制器零 Db 直查
        $res = AdminLogic::saveInvoiceFormFields($rows, $newFields, $linkage);
        return $res['ok'] ? json_success(null, $res['msg']) : json_error($res['msg']);
    }

    /** AJAX: 保存用户 */
    public function saveUser()
    {
        $this->requirePermission('system:user');
        $id = (int)$this->getPost('id', 0);
        $data = [
            'username' => $this->getPost('username', ''),
            'name'     => $this->getPost('name', ''),
            'email'    => $this->getPost('email', ''),
            'mobile'   => $this->getPost('mobile', ''),
            'dept_id'  => (int)$this->getPost('dept_id', 0),
            // v2.40.5：管理员身份不再由「是否管理员」开关设置，统一由「超级管理员」角色（角色配置勾选）授权，
            //           编辑用户弹窗已移除该开关；存量 is_admin=1 用户（预置超管）能力保留，内部判定不受影响
            // 部门负责人仅超级管理员可设置；非超管省略 is_leader key（不传 0），
            // 避免 AdminLogic::saveUser 静默清除既有部门负责人（控制 department.leader_user_id，支撑部门经理审批节点）
            'status'   => (int)$this->getPost('status', 1),
        ];
        if ($this->isSuperAdmin()) {
            $data['is_leader'] = (int)$this->getPost('is_leader', 0);
        }

        $isUpdate = ($id > 0);
        if ($id) {
            if ($this->getPost('password', '') !== '') {
                $data['password'] = password_hash($this->getPost('password'), PASSWORD_BCRYPT);
            }
            $id = AdminLogic::saveUser($id, $data);
        } else {
            $pwd = $this->getPost('password', '');
            if (strlen($pwd) < 8) {
                return json_error('请设置登录密码（至少 8 位）');
            }
            $data['password'] = password_hash($pwd, PASSWORD_BCRYPT);
            $id = AdminLogic::saveUser($id, $data);
        }

        // 分配角色
        // Handle role_ids from both formats
        $roleIds = request()->post('role_ids/a', []);
        if (empty($roleIds)) {
            $raw = request()->post();
            $roleIds = $raw['role_ids'] ?? [];
            if (is_string($roleIds)) $roleIds = explode(',', $roleIds);
        }
        $roleIds = array_map('intval', $roleIds);
        // S-02（R1 安全审查）：提权防护——仅超级管理员可分配/移除「超级管理员」角色；
        // 非超管操作者从提交中强制剔除 admin 角色，且不得编辑已拥有 admin 角色的用户（防越权降权/篡改超管）
        if (!$this->isSuperAdmin()) {
            $adminRoleIds = AdminLogic::adminRoleIds();
            $roleIds = array_values(array_diff($roleIds, $adminRoleIds));
            if ($id > 0 && AdminLogic::userHasRoleCode($id, 'admin')) {
                return json_error('无权修改超级管理员用户');
            }
        }
        // 全量替换（含清空）：不勾选任何角色时也应解除既有角色绑定（否则超管卸任/降权不生效）
        RbacService::assignRoles($id, $roleIds);

        // v2.44.1 P1：用户/角色分配属权限变更，补审计留痕
        \app\common\service\AuditService::log($this->userId, 'save_user', 'user', $id, [
            'role_ids' => $roleIds,
            'action'   => $isUpdate ? 'update' : 'create',
        ]);
        return json_success(null, '保存成功');
    }

    /** AJAX: 禁用用户 */
    public function deleteUser()
    {
        $this->requirePermission('system:user');
        $id = (int)$this->getPost('id', 0);
        // v2.47.2：禁用前校验进行中审批——有审批在身即禁用会使其成为无人能审批/撤回的
        // 僵尸审批（对应合同卡死审批中无法删除），先办离职交接（审批转交他人）再禁用
        $pending = \app\common\logic\AdminLogic::countPendingApprovals($id);
        if ($pending > 0) {
            return json_error("该用户有 {$pending} 条进行中的审批（待审批/已提交未完结），请先在「离职交接」中将审批转交给他人后再禁用");
        }
        AdminLogic::disableUser($id);
        // v2.44.1 P1：用户禁用属权限变更，补审计留痕
        \app\common\service\AuditService::log($this->userId, 'disable_user', 'user', $id);
        return json_success(null, '已禁用');
    }

    /** AJAX: 离职交接（v2.38.16）——将用户客户/合同/待审批批量转移给接收人，可同时禁用离职用户
     *  v2.38.26：权限放宽为 system:user 或 system:handover（离职交接独立权限码，可单独授予角色） */
    public function handoverUser()
    {
        $this->requireAnyPermission(['system:user', 'system:handover']);
        $fromId = (int)$this->getPost('from_user_id', 0);
        $toId   = (int)$this->getPost('to_user_id', 0);
        $scope = [
            'customer' => (int)$this->getPost('scope_customer', 1) === 1,
            'contract' => (int)$this->getPost('scope_contract', 1) === 1,
            'approval' => (int)$this->getPost('scope_approval', 1) === 1,
        ];
        $disableFrom = (int)$this->getPost('disable_from', 1) === 1;
        $result = AdminLogic::handoverUser($fromId, $toId, $scope, $disableFrom);
        if (!$result['ok']) {
            return json_error($result['msg']);
        }
        $c = $result['counts'];
        // 审计日志（事务外记录）：交接动作留痕，含范围与数量
        \app\common\service\AuditService::log(
            $this->userId,
            'handover',
            'user',
            $fromId,
            [
                'to_user_id'   => $toId,
                'scope'        => $scope,
                'disable_from' => $disableFrom,
                'counts'       => $c,
            ]
        );
        return json_success(null, "交接完成：客户 {$c['customer']} 个、合同 {$c['contract']} 个、待审批 {$c['approval']} 条");
    }

    /** AJAX: 恢复禁用用户（从回收站恢复为在职） */
    public function restoreUser()
    {
        $this->requirePermission('system:user');
        $id = (int)$this->getPost('id', 0);
        AdminLogic::restoreUser($id);
        return json_success(null, '已恢复');
    }

    /** AJAX: 清除待交接标记（v2.38.25）——管理员确认该员工未离职（误报/已回岗），仅清标记不做数据移交
     *  v2.38.26：权限放宽为 system:user 或 system:handover */
    public function clearHandover()
    {
        $this->requireAnyPermission(['system:user', 'system:handover']);
        $id = (int)$this->getPost('id', 0);
        if ($id <= 0) {
            return json_error('参数无效');
        }
        AdminLogic::clearHandover($id);
        \app\common\service\AuditService::log($this->userId, 'clear_handover', 'user', $id, []);
        return json_success(null, '已清除待交接标记');
    }

    /** AJAX: 保存角色 */
    public function saveRole()
    {
        $this->requirePermission('system:role');
        $id = (int)$this->getPost('id', 0);
        // v2.40.2：编码自动生成——用户无需填写（表单已隐藏该输入项）；
        // 新建时生成唯一 code（供审批节点按 code 匹配角色），编辑时保留原 code
        $code = trim((string)$this->getPost('code', ''));
        if ($code === '') {
            if ($id > 0) {
                $code = AdminLogic::getRoleCode($id);
            } else {
                $code = AdminLogic::generateRoleCode();
            }
        }
        // 编码唯一性（新建/编辑统一校验，排除自身）：审批节点按 code 匹配角色，重复编码会导致解析歧义
        if (AdminLogic::roleCodeExists($code, $id)) {
            return json_error('角色编码已被其他角色使用');
        }
        $dataScope = $this->getPost('data_scope', 'SELF');
        // P1-2（越权扩权防护）：仅超管可保存 ALL/CUSTOM 数据范围——
        // 防止被授予 system:role 的非超管角色自建 data_scope=ALL 角色并分配给自己，突破数据范围看全量数据
        if (!$this->isSuperAdmin() && in_array($dataScope, ['ALL', 'CUSTOM'], true)) {
            $dataScope = 'SELF';
        }
        $data = [
            'name'        => $this->getPost('name', ''),
            'code'        => $code,
            'description' => $this->getPost('description', ''),
            'data_scope'  => $dataScope,
        ];

        if ($id) {
            RbacService::updateRole($id, $data);
        } else {
            $id = RbacService::createRole($data);
        }

        // Handle perm_ids from both checkbox array and FormData
        $permIds = request()->post('perm_ids/a', []);
        if (empty($permIds)) {
            // Try raw POST
            $raw = request()->post();
            $permIds = $raw['perm_ids'] ?? [];
            if (is_string($permIds)) $permIds = explode(',', $permIds);
        }
        $permIds = array_map('intval', $permIds);
        // 全量替换（含清空）：不勾选任何权限时也应解除既有高级权限绑定（saveRolePerms 内部强制合并基础权限，不会误删）
        RbacService::saveRolePerms($id, $permIds);

        // v2.37.0：自定义数据范围(CUSTOM)的可访问部门集合
        $deptIds = request()->post('role_dept_ids/a', []);
        if (empty($deptIds)) {
            $raw = request()->post();
            $deptIds = $raw['role_dept_ids'] ?? [];
            if (is_string($deptIds)) $deptIds = explode(',', $deptIds);
        }
        if (!empty($deptIds)) {
            RbacService::saveRoleDepts($id, $deptIds);
        } else {
            // 非 CUSTOM 或清空选择时，确保 role_dept 为空（避免残留旧部门）
            RbacService::saveRoleDepts($id, []);
        }

        // v2.44.1 P1：角色保存/权限变更属敏感操作，补审计留痕
        \app\common\service\AuditService::log($this->userId, 'save_role', 'role', $id, [
            'name'       => $data['name'],
            'code'       => $data['code'],
            'data_scope' => $dataScope,
            'perm_ids'   => $permIds,
        ]);
        return json_success(null, '保存成功');
    }

    /** AJAX: 删除角色 */
    public function deleteRole()
    {
        $this->requirePermission('system:role');
        $id = (int)$this->getPost('id', 0);
        if (RbacService::deleteRole($id)) {
            // v2.44.1 P1：角色删除属权限变更，补审计留痕
            \app\common\service\AuditService::log($this->userId, 'delete_role', 'role', $id);
            return json_success(null, '已删除');
        }
        return json_error('系统角色不可删除');
    }

    /** AJAX: 保存审批流程 */
    public function saveFlow()
    {
        $this->requirePermission('system:user');
        $id = (int)$this->getPost('id', 0);
        // v2.40.2：编码自动生成——用户无需填写（表单已隐藏该输入项）；
        // 新建时生成唯一 code（合同流程匹配按分类+金额，不依赖 code，仅作标识），编辑时保留原 code
        $code = trim((string)$this->getPost('code', ''));
        if ($code === '') {
            if ($id > 0) {
                $code = AdminLogic::getFlowCode($id);
            } else {
                $code = AdminLogic::generateFlowCode();
            }
        }
        $data = [
            'name'         => $this->getPost('name', ''),
            'code'         => $code,
            'category'     => $this->getPost('category', ''),       // 遗留单值字段，保留兼容
            'category_list'=> $this->getPost('category_list', '[]'), // v2.31.0：适用分类多选(JSON)
            'use_amount'   => (int)$this->getPost('use_amount', 1),  // v2.31.0：金额条件开关
            'min_amount'   => (float)$this->getPost('min_amount', 0),
            'max_amount'   => (float)$this->getPost('max_amount', 99999999.99),
            'nodes'        => $this->getPost('nodes', '[]'),
            'cc_list'      => $this->getPost('cc_list', '[]'), // v2.38.0：流程级抄送（JSON 字符串）
            'status'       => (int)$this->getPost('status', 1),
        ];

        if (is_array($data['nodes'])) {
            $data['nodes'] = json_encode($data['nodes'], JSON_UNESCAPED_UNICODE);
        }
        // 质量修复：nodes 与 cc_list 对称校验——非法 nodes JSON 拒绝保存（此前非法 nodes 入库
        // → matchFlow 解析为空 → 合同免审批直接通过，属高危配置错误路径）
        if (!is_string($data['nodes']) || json_decode($data['nodes']) === null) {
            return json_error('审批节点数据格式不正确（应为 JSON 数组）');
        }
        // cc_list 已是 JSON 字符串（前端 JSON.stringify 提交），确保为合法 JSON 再落库
        if (!is_string($data['cc_list']) || json_decode($data['cc_list']) === null) {
            $data['cc_list'] = '[]';
        }

        if ($id) {
            $id = AdminLogic::saveFlow($id, $data);
        } else {
            $data['creator_id'] = $this->userId;
            $id = AdminLogic::saveFlow($id, $data);
        }
        // v2.44.1 P1：审批流保存属流程配置变更，补审计留痕
        \app\common\service\AuditService::log($this->userId, 'save_flow', 'approval_flow', $id, [
            'name' => $data['name'],
            'code' => $data['code'],
        ]);
        return json_success(null, '保存成功');
    }

    /**
     * AJAX: 全量保存合同审批流程（v2.38.22 画布式编辑器：分支卡片并列，一次提交全部流程）
     * 请求：flows=[{id,name,code,category_list,use_amount,min_amount,max_amount,status,nodes,cc_list}]
     * 语义：全量重存——本次提交的流程更新/新增；原库中本次未提交的流程停用（status=0，保留历史实例关联）。
     */
    public function saveAllFlows()
    {
        $this->requirePermission('system:user');
        $raw = json_decode((string)$this->getPost('flows', '[]'), true) ?: [];
        // P2-10【M-A1】分层铁律下沉：全量重存（更新/新增/停用未提交）逻辑移至 AdminLogic
        $res = AdminLogic::saveAllFlowsList($raw, $this->userId);
        return $res['ok'] ? json_success(null, $res['msg']) : json_error($res['msg']);
    }

    /** AJAX: 审批流程拖动排序（v2.38.24：同类流程内 sort_order=1..N 重新编号，靠前优先级越高） */
    public function sortFlows()
    {
        $this->requirePermission('system:user');
        $ids = (string)$this->getPost('ids', '');
        $idArr = array_values(array_filter(array_map('intval', explode(',', (string)$ids))));
        if (empty($idArr)) return json_error('流程ID列表为空');
        // P2-10【M-A1】分层铁律下沉：存在性校验 + 分组重排序逻辑移至 AdminLogic
        AdminLogic::sortFlowsByIds($idArr);
        return json_success(null, '排序已保存');
    }

    /** AJAX: 删除审批流程（软删除：停用并隐藏，保留历史审批实例关联） */
    public function deleteFlow()
    {
        $this->requirePermission('system:user');
        $id = (int)$this->getPost('id', 0);
        if ($id <= 0) return json_error('参数错误');
        AdminLogic::disableFlow($id);
        // v2.44.1 P1：流程停用属流程配置变更，补审计留痕
        \app\common\service\AuditService::log($this->userId, 'delete_flow', 'approval_flow', $id);
        return json_success(null, '已删除');
    }

    /** AJAX: 恢复停用的审批流程（status=0 → 1，重新参与新合同审批匹配） */
    public function restoreFlow()
    {
        $this->requirePermission('system:user');
        $id = (int)$this->getPost('id', 0);
        if ($id <= 0) return json_error('参数错误');
        AdminLogic::enableFlow($id);
        // v2.44.1 P1：流程恢复属流程配置变更，补审计留痕
        \app\common\service\AuditService::log($this->userId, 'restore_flow', 'approval_flow', $id);
        return json_success(null, '已恢复');
    }

    /** AJAX: 彻底删除审批流程（永久删除；仅当无审批实例 / 模板引用时允许，保护历史关联） */
    public function purgeFlow()
    {
        $this->requirePermission('system:user');
        $id = (int)$this->getPost('id', 0);
        if ($id <= 0) return json_error('参数错误');
        // P2-10【M-A1】分层铁律下沉：实例/模板引用计数保护 + 删除逻辑移至 AdminLogic
        $res = AdminLogic::purgeFlowById($id);
        if ($res['ok']) {
            // v2.44.1 P1：流程彻底删除属敏感配置变更，补审计留痕
            \app\common\service\AuditService::log($this->userId, 'purge_flow', 'approval_flow', $id);
        }
        return $res['ok'] ? json_success(null, $res['msg']) : json_error($res['msg']);
    }

    /** AJAX: 保存合同分类 */
    public function saveCategory()
    {
        $this->requirePermission('system:user');
        $code = $this->getPost('code', '');
        $name = $this->getPost('name', '');
        if (!$code || !$name) return json_error('编码和名称不能为空');

        $cats = contract_categories();
        $cats[$code] = $name;
        AdminLogic::saveContractCategories($cats);
        return json_success(null, '保存成功');
    }

    /** AJAX: 删除合同分类 */
    public function deleteCategory()
    {
        $this->requirePermission('system:user');
        $code = $this->getPost('code', '');
        $cats = contract_categories();
        unset($cats[$code]);
        AdminLogic::saveContractCategories($cats);
        return json_success(null, '已删除');
    }

    /** 强制改密页（首次登录 force_reset=1 跳转至此） */
    public function changePasswordPage()
    {
        return View::fetch('profile/change_password');
    }

    /** AJAX: 修改密码 */
    public function changePassword()
    {
        $oldPwd = $this->getPost('old_password', '');
        $newPwd = $this->getPost('new_password', '');
        $force  = (int)$this->getPost('force', 0);

        if (strlen($newPwd) < 8) return json_error('新密码至少 8 位');

        $user = AdminLogic::getUserById($this->userId);
        // 强制改密模式（首次部署 / 管理员重置）：跳过旧密码校验
        if (!($force && !empty($user['force_reset']))) {
            if (!password_verify($oldPwd, $user['password'])) {
                return json_error('旧密码错误');
            }
        }

        AdminLogic::updateUserPassword($this->userId, password_hash($newPwd, PASSWORD_BCRYPT));
        // REV-12：改密成功后轮换会话 ID，防范会话固定攻击
        if (PHP_SAPI !== 'cli') {
            Session::regenerate(true);
        }
        // 同步刷新会话中的 force_reset，避免改密后仍被强制改密守卫循环重定向
        $u = Session::get('user', []);
        $u['force_reset'] = 0;
        Session::set('user', $u);
        return json_success(null, '密码修改成功');
    }

    /** AJAX: 保存系统配置（含字典项操作） */
    public function saveConfig()
    {
        $this->requirePermission('system:user');
        $key   = $this->getPost('key', '');
        $value = $this->getPost('value', '');

        // 字典项/整字典/普通配置的统一保存逻辑下沉至 AdminLogic
        $itemKey   = $this->getPost('item_key', '');
        $itemValue = $this->getPost('item_value', '');
        $oldKey    = $this->getPost('old_key', '');
        $res = AdminLogic::saveConfig($key, $value, $itemKey, $itemValue, $oldKey);
        if ($res['ok']) {
            return json_success(null, $res['msg']);
        }
        return json_error($res['msg']);
    }

    /** AJAX: 部门树（选人弹窗左侧部门导航用） */
    public function deptTree()
    {
        $this->requirePermission('system:user');
        return json_success(UserLogic::getDeptTree());
    }

    /**
     * 系统配置备份：下载 JSON 快照（不含 user 表，v2.36.0）
     * 通过浏览器直接导航到本接口即可触发下载（会话 cookie 随行）。
     * v2.45.1：支持 ?tables[]=role&tables[]=permission 只导出勾选表（缺省 = 全量导出）。
     */
    public function configBackup()
    {
        // ⑥ 权限收紧（评估优化）：备份/恢复覆盖权限/角色等整簇配置，仅超管（is_admin=1 或 admin 角色）可操作
        if (!$this->isSuperAdmin()) {
            $this->deny();
        }
        $selected = (array)$this->getParam('tables/a', []);
        $payload  = AdminLogic::exportConfigArray($selected ?: null);
        $filename = 'config_backup_' . date('Ymd_His') . '.json';
        return response(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), 200, [
            'Content-Type'        => 'application/json; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * 系统配置恢复：预览或提交（v2.36.0）
     * - mode=preview：解析上传文件，返回各表行数 + 风险告警，不改库。
     * - mode=commit ：事务内清空并重新写入允许列表内的表（保留原 id），失败整体回滚。
     * 上传方式：multipart/form-data 字段 backup_file（文件）。
     */
    public function configRestore()
    {
        // ⑥ 权限收紧（评估优化）：恢复会整簇覆盖权限/角色矩阵，仅超管可操作
        if (!$this->isSuperAdmin()) {
            $this->deny();
        }
        $mode = $this->getPost('mode', 'preview');

        // ③ 上传超限友好提示（评估优化）：PHP 默认 upload_max_filesize=2M，
        // 大配置备份（模板/资料库大字段）超限时文件被丢弃，明确提示而非误报「解析失败」
        if (isset($_FILES['backup_file']) && (int)$_FILES['backup_file']['error'] === UPLOAD_ERR_INI_SIZE) {
            return json_error('配置文件超过服务器上传大小限制（php.ini upload_max_filesize），请调整限制后重试');
        }
        $file = request()->file('backup_file');
        if (!empty($file)) {
            $content = file_get_contents($file->getRealPath());
        } else {
            $raw = $this->getPost('raw_json', '');
            $content = $raw ?: '';
        }
        if ($content === '') {
            return json_error('请上传配置文件');
        }
        $payload = json_decode($content, true);
        if (!is_array($payload) || empty($payload['tables'])) {
            return json_error('配置文件解析失败或格式不正确');
        }
        // v2.45.1：恢复可自选表——仅覆盖勾选的表，未勾选表保持现状
        $selected = (array)$this->getPost('tables/a', []);
        if ($selected) {
            $payload = AdminLogic::filterPayloadTables($payload, $selected);
        }

        if ($mode === 'commit') {
            // ① 防自锁（评估优化）：user 表不参与恢复（is_admin 恒保留），但 admin 角色 / system:user
            // 授权随 role/user_role/role_permission 被覆盖——若备份未包含当前账号的管理授权，恢复后操作者将失去权限被锁死
            if (empty($this->user['is_admin']) && !AdminLogic::restorePreservesAdmin($this->userId, $payload)) {
                return json_error('已阻止：恢复后当前账号将失去系统管理权限（备份须包含当前账号的 admin 角色或 system:user 授权）');
            }
            $res = AdminLogic::commitConfigImport($payload);
            if ($res['ok']) {
                // ② 审计留痕（评估优化）：敏感整簇覆盖操作须可追溯（谁/何时/覆盖了哪些表行数）
                \app\common\service\AuditService::log($this->userId, 'config_restore', 'system_config', 0,
                    ['restored' => $res['restored']]);
                return json_success($res['restored'] ?? [], $res['msg']);
            }
            return json_error($res['msg']);
        }
        // 默认 / mode=preview：仅预览
        $preview = AdminLogic::previewConfigImport($payload);
        // ① 预览预警：恢复后当前账号将失去系统管理权限（提交将被阻断）
        if (empty($this->user['is_admin']) && !AdminLogic::restorePreservesAdmin($this->userId, $payload)) {
            $preview['warnings'][] = '恢复后当前账号将失去系统管理权限，提交将被阻止（备份须包含当前账号的 admin 角色或 system:user 授权）。';
        }
        return json_success($preview);
    }

    /** AJAX: 选人弹窗用户搜索（支持部门过滤 + 关键词分页） */
    public function userPicker()
    {
        $this->requirePermission('system:user');
        $deptId   = (int)$this->getParam('dept_id', 0);
        $keyword  = $this->getParam('keyword', '');
        $page     = max(1, (int)$this->getParam('page', 1));
        $pageSize = 20;
        $res = UserLogic::searchPicker($deptId, $keyword, $page, $pageSize);
        return json_success($res);
    }
}
