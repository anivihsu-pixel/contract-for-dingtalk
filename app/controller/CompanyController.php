<?php
// +----------------------------------------------------------------------
// | 本公司主体管理
// +----------------------------------------------------------------------

namespace app\controller;

use think\facade\View;
use app\BaseController;
use app\common\logic\CompanyLogic;

class CompanyController extends BaseController
{
    /** 本公司主体管理页（系统设置内） */
    public function index()
    {
        $this->requirePermission('company:manage');
        $list = CompanyLogic::getList();
        View::assign('list', $list);
        View::assign('menu_active', 'admin');
        View::assign('tab', 'company');
        return View::fetch();
    }

    /** AJAX: 供合同创建/编辑页下拉与「本公司」快捷按钮使用 */
    public function options()
    {
        $this->requireAnyPermission(['contract:create', 'contract:edit', 'company:manage']);
        $list = CompanyLogic::getManageOptions();
        return json_success($list);
    }

    /** AJAX: 保存（新增/编辑）本公司主体 */
    public function save()
    {
        $this->requirePermission('company:manage');
        $id = (int)$this->getPost('id', 0);
        $taxRate = (float)$this->getPost('invoice_tax_rate', 0.06);
        // 开票税率白名单校验（0=免税；0<rate<1 合法；非法回退默认 6%）
        if ($taxRate < 0 || $taxRate >= 1) {
            return json_error('开票税率不合法：应为 0（免税）或小于 100% 的数值');
        }
        $data = [
            'name'                      => trim($this->getPost('name', '')),
            'short_name'                => trim($this->getPost('short_name', '')),
            'unified_social_credit_code' => trim($this->getPost('unified_social_credit_code', '')),
            'invoice_tax_rate'          => $taxRate,
            'is_default'                => (int)$this->getPost('is_default', 0),
        ];
        if ($data['name'] === '') {
            return json_error('请填写公司全称');
        }
        $id = CompanyLogic::save($data, $id);
        return json_success(['id' => $id], '保存成功');
    }

    /** AJAX: 删除本公司主体 */
    public function delete()
    {
        $this->requirePermission('company:manage');
        $id = (int)$this->getPost('id', 0);
        $res = CompanyLogic::delete($id);
        if (!$res['ok']) {
            return json_error($res['msg']);
        }
        return json_success(null, $res['msg']);
    }
}
