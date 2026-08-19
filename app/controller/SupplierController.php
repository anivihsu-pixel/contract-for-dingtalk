<?php
namespace app\controller;

use think\facade\View;
use app\BaseController;
use app\common\service\AuditService;
use app\common\logic\AuthLogic;
use app\common\logic\SupplierLogic;

/**
 * 供应商管理控制器
 * 支持列表/新增/编辑/删除/详情/搜索，统一走数据权限（SELF/DEPT/ALL）
 */
class SupplierController extends BaseController
{
    /**
     * 供应商列表页 — 搜索 + 类型筛选 + 分页
     * AJAX 请求返回 layui 表格数据，普通请求渲染完整页面
     */
    public function index()
    {
        $this->requirePermission('supplier:view');
        $keyword = $this->getParam('keyword', '');
        $type    = $this->getParam('type', '');

        // v2.52.2：查看范围「我的供应商/全部供应商」——默认「我的供应商」，显式 scope/owner_id 覆盖；
        // localStorage 记忆由前端在无 scope 参数时按记忆重定向 URL 生效
        $ownerId  = $this->getParam('owner_id', '');
        $scopeParam = $this->getParam('scope', '');
        $visScope = AuthLogic::visibility();
        $canScopeToggle = $visScope['has_all'] || !empty($visScope['dept_ids']);   // 仅能查看他人供应商的账号显示切换
        $filterOwner = '';
        $scope = 'all';
        if ($scopeParam === 'me' || $ownerId === 'me') {
            $scope = 'me';
            $filterOwner = $this->userId;
        } elseif ($scopeParam === 'all') {
            // 全部供应商：不加归属人过滤（数据范围兜底）
        } elseif ($ownerId !== '' && is_numeric($ownerId)) {
            $filterOwner = (int)$ownerId;
        } else {
            $scope = 'me';   // 无参数（独立进入）默认「我的供应商」
            $filterOwner = $this->userId;
        }

        [$sortField, $sortOrder] = $this->getSortParams([
            'id'         => 'id',
            'name'       => 'name',
            'created_at' => 'created_at',
        ], 'id', 'desc');

        list($page, $pageSize) = $this->getPageParams();
        $result = SupplierLogic::getIndexList([
            'keyword'    => $keyword,
            'type'       => $type,
            'owner_id'   => $filterOwner,
            'sortField'  => $sortField,
            'sortOrder'  => $sortOrder,
            'isAjax'     => request()->isAjax(),
            'page'       => $page,
            'pageSize'   => $pageSize,
        ]);

        if (request()->isAjax()) {
            return layui_table($result['list'], $result['total']);
        }
        View::assign('suppliers', $result['list']);
        View::assign('type', $type);
        View::assign('scope', $scope);
        View::assign('owner_id', $filterOwner);
        // v2.52.2：归属列——补充归属人姓名（列表视图渲染用）
        $ownerIds = array_values(array_unique(array_filter(array_column($result['list'], 'owner_id'))));
        View::assign('owner_names', $ownerIds ? \app\common\logic\UserLogic::getNamesByIds($ownerIds) : []);
        // v2.52.2：查看范围切换（视图据此渲染「我的供应商/全部供应商」，scope 由 URL 驱动）
        View::assign('can_scope_toggle', $canScopeToggle);
        // v2.52.x：删除入口门控（与后端 delete() 守卫同口径，无权限不渲染删除按钮）
        View::assign('can_delete', $this->hasPermission('supplier:delete'));
        return View::fetch();
    }

    /**
     * 供应商新建/编辑页 — id=0 为新建，id>0 为编辑
     * 编辑时有越权防护（canAccessRecord）
     * @param int    $id       供应商 ID，0 表示新建
     * @param string $template 可选自定义模板（edit 时复用 create 视图）
     */
    public function create($id = 0, $template = '')
    {
        $id = (int)($this->getParam('id', $id));
        // UX 门控：新建需 supplier:create，编辑需 supplier:edit（与 save() 口径一致，原 :view 过宽）
        $this->requirePermission($id ? 'supplier:edit' : 'supplier:create');
        $s   = $id ? SupplierLogic::findRaw($id) : null;
        // 越权防护：编辑页仅允许归属人/部门/管理员打开
        if ($s && !AuthLogic::canAccessRecord($s['owner_id'] ?? 0, $s['dept_id'] ?? 0)) {
            return '无权查看该供应商';
        }
        View::assign('supplier', $s);
        return $template ? View::fetch($template) : View::fetch();
    }

    /**
     * 保存供应商 — 新建/编辑统一入口
     * 新建需 supplier:create 权限，编辑需 supplier:edit 权限
     * 写审计日志（create / update）
     */
    public function save()
    {
        $idemKey=(string)$this->getPost('idempotency_key','');
        try{$cached=\app\common\service\IdempotencyService::cached($this->userId,'supplier.save',$idemKey);if($cached)return json_success($cached,'保存成功');}catch(\RuntimeException $e){return json_error($e->getMessage());}
        $id = (int)$this->getPost('id', 0);
        // 新建需 supplier:create，编辑需 supplier:edit
        $this->requirePermission($id ? 'supplier:edit' : 'supplier:create');
        $data = [
            'name'           => $this->getPost('name', ''),
            'type'           => $this->getPost('type', 'MEDIA'),
            'contact_name'   => $this->getPost('contact_name', ''),
            'contact_mobile' => $this->getPost('contact_mobile', ''),
            'address'        => $this->getPost('address', ''),
            // v2.51.3：原联系邮箱改为备注（供应商表单语义统一为客户口径，字段更名）
            'remark'         => mb_substr(trim($this->getPost('remark', '')), 0, 255),
            'status'         => (int)$this->getPost('status', 1),
        ];
        if (empty($data['name'])) return json_error('请输入供应商名称');
        // CR-46：手机号格式校验（非空时校验）；邮箱已随 v2.51.3 下架
        if (!validate_mobile($data['contact_mobile'])) {
            return json_error('联系人手机号格式不正确');
        }
        if ($id) {
            $existing = SupplierLogic::findRaw($id);
            if (!$existing || !AuthLogic::canAccessRecord($existing['owner_id'] ?? 0, $existing['dept_id'] ?? 0)) {
                return json_error('无权限编辑该供应商');
            }
            SupplierLogic::update($id, $data);
        } else {
            $data['owner_id'] = $this->userId; $data['dept_id'] = $this->user['dept_id'] ?? 0;
            $id = SupplierLogic::create($data);
        }
        AuditService::log($this->userId, $id ? 'update' : 'create', 'supplier', $id);
        $result=['id'=>$id];\app\common\service\IdempotencyService::remember($this->userId,'supplier.save',$idemKey,$result);
        return json_success($result, '保存成功');
    }

    /**
     * 删除供应商（软删除：is_deleted = 1）
     * 需 supplier:delete 权限 + 数据范围校验
     */
    public function delete()
    {
        $this->requirePermission('supplier:delete');
        $id = (int)$this->getPost('id', 0);
        $existing = SupplierLogic::findRaw($id);
        if (!$existing || !AuthLogic::canAccessRecord($existing['owner_id'] ?? 0, $existing['dept_id'] ?? 0)) {
            return json_error('无权限删除该供应商');
        }
        // P0-3【严重·数据完整性】删除前校验关联采购合同，避免产生悬空引用
        $blockers = \app\common\logic\SupplierLogic::deleteBlockers($id);
        if (!empty($blockers)) {
            return json_error('该供应商无法删除：' . implode('；', $blockers));
        }
        SupplierLogic::softDelete($id);
        return json_success(null, '已删除');
    }

    /**
     * 供应商详情页
     * 需 supplier:view 权限 + 越权防护
     */
    public function detail($id)
    {
        $this->requirePermission('supplier:view');
        $s = SupplierLogic::findRaw((int)$id);
        if (!$s) return '供应商不存在';
        if (!AuthLogic::canAccessRecord($s['owner_id'] ?? 0, $s['dept_id'] ?? 0)) return '无权查看该供应商';
        View::assign('supplier', $s);
        // v2.52.x：删除入口门控（与后端 delete() 守卫同口径，无权限不渲染删除按钮）
        View::assign('can_delete', $this->hasPermission('supplier:delete'));
        return View::fetch();
    }

    /** 编辑供应商 — 复用 create 视图 */
    public function edit($id)
    {
        return $this->create($id, 'supplier/create');
    }
    /**
     * AJAX 供应商搜索 — 供合同创建页乙方选择器调用
     * 按数据范围过滤（SELF/DEPT），模糊匹配名称/联系人/手机
     */
    public function search()
    {
        $this->requirePermission('supplier:view');
        $q = $this->getParam('q', '');
        $uid = $this->userId;
        $deptId = $this->user['dept_id'] ?? 0;
        $list = SupplierLogic::search($q, $uid, $deptId);
        return json_success($list);
    }
}
