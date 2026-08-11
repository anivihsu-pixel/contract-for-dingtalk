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
        // H2：开票客户下拉数据源（客户复用开票信息：联动 fill 自动带出抬头/税号）
        $customers = \app\common\logic\CustomerLogic::getInvoiceOptions();

        View::assign('title', '发票申请');
        View::assign('menu_active', 'invoice_apply');
        View::assign('companies', $companies);
        View::assign('customers', $customers);
        View::assign('can_apply', $this->hasPermission('invoice:apply'));
        View::assign('can_create', $this->hasPermission('invoice:create'));
        View::assign('status_labels', InvoiceLogic::STATUS_LABELS);
        // 申请表单字段（服务端渲染到申请弹窗；后台「系统设置→发票表单」可配置字段组合）
        View::assign('apply_fields', InvoiceFormConfig::pcRender([], ['companies' => $companies, 'customers' => $customers]));
        // F9：字段联动规则（通用组件 form-linkage.js 消费；后台设计器可配置）
        View::assign('invoice_form_rules', InvoiceFormConfig::rules());
        // H3：客户数据源注入（联动 fill 动作消费：选客户 → 带出抬头/税号）
        View::assign('invoice_customers', $customers);
        return View::fetch();
    }
}
