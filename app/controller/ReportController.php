<?php
// +----------------------------------------------------------------------
// | 经营报表（P3-2 报表导出深化）：经营月报 + 驾驶舱数据导出
// | 权限复用 payment:view / invoice:view（与财务中心一致，避免新增权限种子）
// +----------------------------------------------------------------------

namespace app\controller;

use think\facade\View;
use app\BaseController;
use app\common\logic\ReportLogic;
use app\common\service\AuditService;
use app\common\response\StreamedFileResponse;
use app\common\helper\XlsxHelper;

class ReportController extends BaseController
{
    /** 财务类报表统一权限门（老板/财务可看），委托至 BaseController::financialGate（v2.38.1 统一门面） */
    private function financeGate(): void
    {
        $this->financialGate();
    }

    /**
     * m15：清洗导出文件名中的月份参数
     * 仅保留字母、数字及 . _ - ，过滤回车/换行/引号/分号等字符，
     * 防止响应头注入（Content-Disposition 文件名污染）。
     * @param string $ym 原始月份参数
     * @return string 仅含安全字符的文件名片段
     */
    private function safeExportName(string $ym): string
    {
        return preg_replace('/[^0-9A-Za-z._-]/', '', $ym);
    }

    /** 经营月报页面（月份选择器 + 生成 + 导出） */
    public function monthly()
    {
        $this->financeGate();
        View::assign('default_month', date('Y-m'));
        View::assign('menu_active', 'finance');
        return View::fetch('report/monthly');
    }

    /** 经营周报页面（v2.47.0：总经理周一例会参考；支持 ?week=周一日期 回溯任意周）
     *  权限为 dashboard:company——周报是全公司口径数据，仅总经理/超管可见，与「全公司经营」卡片同权限。 */
    public function weekly()
    {
        $this->requirePermission('dashboard:company');
        $week = $this->getParam('week', '');
        [$start, $end] = \app\common\logic\WeeklyReportLogic::weekRange(is_string($week) && $week !== '' ? $week : null);
        $data = \app\common\logic\WeeklyReportLogic::build($start, $end);
        View::assign('weekly', $data);
        View::assign('menu_active', 'finance');
        View::assign('tab', 'weekly'); // 侧边栏财务中心「经营周报」二级菜单高亮
        return View::fetch('report/weekly');
    }

    /** AJAX: 指定月份经营月报数据 */
    public function monthlyData()
    {
        $this->financeGate();
        $ym   = $this->getParam('month', date('Y-m'));
        $data = ReportLogic::monthlyReport($this->user, $ym);
        return json_success($data);
    }

    /** CSV: 经营月报导出（高危操作，写审计） */
    public function monthlyExport()
    {
        $this->financeGate();
        $ym = $this->getParam('month', date('Y-m'));
        // m15：导出文件名仅允许字母数字与 . _ - ，过滤回车/换行/引号等字符，
        // 防止攻击者在 month 参数注入响应头（CRLF/引号），污染 Content-Disposition。
        $safeYm = $this->safeExportName($ym);
        $r  = ReportLogic::monthlyReport($this->user, $ym);

        $csv  = "\xEF\xBB\xBF";
        $csv .= "经营月报,{$ym}\n";
        $csv .= "指标,数值\n";
        $csv .= "本月计划应收,{$r['receivable']}\n";
        $csv .= "本月实际回款,{$r['collected']}\n";
        $csv .= "本月逾期金额,{$r['overdue']}\n";
        $csv .= "应收未收余额,{$r['uncollected']}\n";
        $csv .= "回款率(%),{$r['recovery_rate']}\n";
        $csv .= "上月计划应收,{$r['prev_receivable']}\n";
        $csv .= "上月实际回款,{$r['prev_collected']}\n";
        $csv .= "\n";
        $csv .= "收支方向(本月新增合同),销售金额,销售笔数,采购金额,采购笔数\n";
        $csv .= ",{$r['dir']['sales']['total']},{$r['dir']['sales']['cnt']},{$r['dir']['purchase']['total']},{$r['dir']['purchase']['cnt']}\n";

        AuditService::log($this->userId, 'export', 'report_monthly', 0, [
            'month'          => $ym,
            'receivable'     => $r['receivable'],
            'collected'      => $r['collected'],
            'recovery_rate'  => $r['recovery_rate'],
        ]);

        return response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="monthly_report_' . $safeYm . '.csv"',
        ]);
    }

    /** CSV: 驾驶舱经营概览导出（高危操作，写审计） */
    public function dashboardExport()
    {
        $this->financeGate();
        $s = ReportLogic::dashboardSummary($this->user);

        $csv  = "\xEF\xBB\xBF";
        $csv .= "驾驶舱经营概览," . date('Y-m-d H:i:s') . "\n";
        $csv .= "指标,数值\n";
        $csv .= "累计应收,{$s['total_receivable']}\n";
        $csv .= "累计已收,{$s['received_amount']}\n";
        $csv .= "应收待收,{$s['pending_amount']}\n";
        $csv .= "逾期金额,{$s['overdue_amount']}\n";
        $csv .= "回款率(%),{$s['recovery_rate']}\n";
        $csv .= "销售合同额,{$s['dir']['sales']['total']}\n";
        $csv .= "销售合同数,{$s['dir']['sales']['cnt']}\n";
        $csv .= "采购合同额,{$s['dir']['purchase']['total']}\n";
        $csv .= "采购合同数,{$s['dir']['purchase']['cnt']}\n";
        $csv .= "\n";
        $csv .= "近6季度合同趋势\n";
        $csv .= "季度,合同金额,已收回款\n";
        foreach ($s['trend'] as $t) {
            $csv .= "{$t['month']},{$t['amount']},{$t['received']}\n";
        }

        AuditService::log($this->userId, 'export', 'report_dashboard', 0, [
            'recovery_rate'    => $s['recovery_rate'],
            'total_receivable' => $s['total_receivable'],
        ]);

        return response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="dashboard_' . date('Ymd') . '.csv"',
        ]);
    }

    /** XLSX: 经营月报导出（REV-27：新增 Excel 格式） */
    public function monthlyExportXlsx()
    {
        $this->financeGate();
        $ym = $this->getParam('month', date('Y-m'));
        $safeYm = $this->safeExportName($ym); // m15：清洗导出文件名中的月份参数，防响应头注入
        $r  = ReportLogic::monthlyReport($this->user, $ym);

        $headers = ['指标', '数值'];
        $rows = [
            ['本月计划应收', $r['receivable']],
            ['本月实际回款', $r['collected']],
            ['本月逾期金额', $r['overdue']],
            ['应收未收余额', $r['uncollected']],
            ['回款率(%)', $r['recovery_rate']],
            ['上月计划应收', $r['prev_receivable']],
            ['上月实际回款', $r['prev_collected']],
            ['— 收支方向（本月新增合同）—', ''],
            ['销售金额', $r['dir']['sales']['total']],
            ['销售笔数', $r['dir']['sales']['cnt']],
            ['采购金额', $r['dir']['purchase']['total']],
            ['采购笔数', $r['dir']['purchase']['cnt']],
        ];

        AuditService::log($this->userId, 'export', 'report_monthly', 0, [
            'format'        => 'xlsx',
            'month'         => $ym,
            'receivable'    => $r['receivable'],
            'collected'     => $r['collected'],
            'recovery_rate' => $r['recovery_rate'],
        ]);

        return XlsxHelper::export($headers, $rows, 'monthly_report_' . $safeYm . '.xlsx');
    }

    /** XLSX: 驾驶舱经营概览导出（REV-27：新增 Excel 格式） */
    public function dashboardExportXlsx()
    {
        $this->financeGate();
        $s = ReportLogic::dashboardSummary($this->user);

        $headers = ['指标', '数值'];
        $rows = [
            ['累计应收', $s['total_receivable']],
            ['累计已收', $s['received_amount']],
            ['应收待收', $s['pending_amount']],
            ['逾期金额', $s['overdue_amount']],
            ['回款率(%)', $s['recovery_rate']],
            ['销售合同额', $s['dir']['sales']['total']],
            ['销售合同数', $s['dir']['sales']['cnt']],
            ['采购合同额', $s['dir']['purchase']['total']],
            ['采购合同数', $s['dir']['purchase']['cnt']],
        ];
        foreach ($s['trend'] as $t) {
            $rows[] = [$t['month'] . ' 合同金额', $t['amount']];
            $rows[] = [$t['month'] . ' 已收回款', $t['received']];
        }

        AuditService::log($this->userId, 'export', 'report_dashboard', 0, [
            'format'           => 'xlsx',
            'recovery_rate'    => $s['recovery_rate'],
            'total_receivable' => $s['total_receivable'],
        ]);

        return XlsxHelper::export($headers, $rows, 'dashboard_' . date('Ymd') . '.xlsx');
    }

    /** v2.38.3 应收账龄分析 */
    public function aging()
    {
        $this->financeGate();
        $aging = ReportLogic::agingReport($this->user);
        // v2.38.11: tab=aging 标识——侧边栏财务中心「应收账龄」二级菜单据此高亮（此前仅 menu_active=finance
        // 导致「回款管理」误高亮：其 active 条件为 finance && tab!='invoice'，tab 为空时误中）
        View::assign('tab', 'aging');
        View::assign('aging', $aging);
        return View::fetch('report/aging');
    }
}
