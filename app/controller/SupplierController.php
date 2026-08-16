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

        [$sortField, $sortOrder] = $this->getSortParams([
            'id'         => 'id',
            'name'       => 'name',
            'created_at' => 'created_at',
        ], 'id', 'desc');

        list($page, $pageSize) = $this->getPageParams();
        $result = SupplierLogic::getIndexList([
            'keyword'    => $keyword,
            'type'       => $type,
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
