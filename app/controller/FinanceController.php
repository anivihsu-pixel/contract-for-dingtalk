<?php
namespace app\controller;

use think\facade\View;
use app\BaseController;
use app\common\logic\FinanceLogic;

class FinanceController extends BaseController
{
    public function index()
    {
        // 统一权限门面：拥有回款或发票查看权限即可进入财务中心（与报表/移动端一致）
        $this->financialGate();

        // 收支概览：统一下沉至 FinanceLogic::getSummary（Phase 1.2 提取，消除与移动端重复与 Db 直查）
        $sum = FinanceLogic::getSummary();
        View::assign('fin_summary', $sum);
        // UX 门控：行内确认收款/确认付款按钮按 payment:create、发票开票/红冲/作废/删除按 invoice:create 渲染（与后端守卫同口径）
        View::assign('can_pay', $this->hasPermission('payment:create'));
        View::assign('can_issue', $this->hasPermission('invoice:create'));
        // v2.38.7：tab 传入侧边栏，供「回款管理/发票管理」子菜单 active 高亮
        View::assign('tab', $this->getParam('tab', ''));
        return View::fetch();
    }

    /** AJAX: 全部回款计划（按数据范围收敛，分页返回避免大表拖垮性能） */
    public function paymentList()
    {
        $this->requirePermission('payment:view');
        [$page, $pageSize] = $this->getPageParams();
        // P1-7：直查下沉到 FinanceLogic::getPaymentList（单一口径 + 数据权限收敛）
        [$sortField, $sortOrder] = $this->getSortParams([
            'id'           => 'p.id',
            'planned_date' => 'p.planned_date',
            'amount'       => 'p.amount',
        ], 'p.planned_date', 'asc');
        $kw = $this->getParam('kw', '');
        // v2.40.0 P1-4：付款管理 tab 按类型过滤（空=全部）
        $paymentType = trim((string)$this->getParam('payment_type', ''));
        $res = FinanceLogic::getPaymentList($page, $pageSize, $sortField, $sortOrder, $kw, $paymentType);
        return layui_table($res['list'], $res['total']); // REV-43：统一经 layui_table 返回（code/data/count 约定）
    }

    /** AJAX: 全部发票（按数据范围收敛，分页返回避免大表拖垮性能） */
    public function invoiceList()
    {
        $this->requirePermission('invoice:view');
        [$page, $pageSize] = $this->getPageParams();
        // P1-7：直查下沉到 FinanceLogic::getInvoiceList（单一口径 + 数据权限收敛）
        [$sortField, $sortOrder] = $this->getSortParams([
            'id'         => 'i.id',
            'created_at' => 'i.created_at',
            'amount'     => 'i.amount',
        ], 'i.id', 'desc');
        $res = FinanceLogic::getInvoiceList($page, $pageSize, $sortField, $sortOrder);
        return layui_table($res['list'], $res['total']); // REV-43：统一经 layui_table 返回（code/data/count 约定）
    }

    /** 税务汇总页：按月、按进项/销项汇总金额与税额 */
    public function tax()
    {
        $this->requirePermission('invoice:view');
        return View::fetch();
    }

    /** AJAX: 税务汇总数据（按月 + 进项/销项） */
    public function taxData()
    {
        $this->requirePermission('invoice:view');
        // P1-7：直查下沉到 FinanceLogic::getTaxSummary（单一口径 + 数据权限收敛）
        $result = FinanceLogic::getTaxSummary();
        return json_success($result);
    }
}
