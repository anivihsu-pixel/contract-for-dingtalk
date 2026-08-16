<?php
// +----------------------------------------------------------------------
// | 发票申请独立入口控制器（F5，v2.38.7）
// | 「发票申请」顶级菜单 → 我的申请 / 待我审批 / 申请开票（表单字段后台可配）
// +----------------------------------------------------------------------

namespace app\controller;

use app\BaseController;
use think\facade\View;
use app\common\logic\CompanyLogic;
use app\common\logic\InvoiceLogic;
use app\common\form\InvoiceFormConfig;

class InvoiceApplyController extends BaseController
{
    /** 发票申请页：我的申请 + 待我审批 + 快捷申请弹窗（表单由 InvoiceFormConfig 渲染） */
    public function index()
    {
        $this->requirePermission('invoice:view');
        $companies = CompanyLogic::getListWithDefault();
        // 2026-08-11：开票客户数据源改为后端搜索（/ajax/party/search，cs-wrap data-cs-url），
        // 不再向前端注入全量客户（原 getInvoiceOptions 全量注入 __formData.customer_id）

        View::assign('title', '发票申请');
        View::assign('menu_active', 'invoice_apply');
        View::assign('companies', $companies);
        View::assign('customers', []);
        View::assign('can_apply', $this->hasPermission('invoice:apply'));
        View::assign('can_create', $this->hasPermission('invoice:create'));
        View::assign('status_labels', InvoiceLogic::STATUS_LABELS);
        // 申请表单字段（服务端渲染到申请弹窗；后台「系统设置→发票表单」可配置字段组合）
        View::assign('apply_fields', InvoiceFormConfig::pcRender([], ['companies' => $companies]));
        // F9：字段联动规则（通用组件 form-linkage.js 消费；后台设计器可配置）
        View::assign('invoice_form_rules', InvoiceFormConfig::rules());
        // 客户数据源：已改后端搜索，__formData 不再注入全量客户
        View::assign('invoice_customers', []);
        return View::fetch();
    }

    /** 发票申请详情页（2026-08-15：列表整行点击/内容链接跳转，替代弹窗） */
    public function detail()
    {
        $this->requirePermission('invoice:view');
        $id = (int)$this->getParam('id', 0);
        if ($id <= 0) {
            View::assign('error', '缺少申请 ID');
            return View::fetch();
        }
        $res = \app\controller\InvoiceController::detailData($id, $this->userId, $this->hasPermission('invoice:create'));
        if (!$res['ok']) {
            View::assign('error', $res['msg']);
            return View::fetch();
        }
        View::assign('detail', $res['data']);
        return View::fetch();
    }
}
