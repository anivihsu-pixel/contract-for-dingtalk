<?php
// +----------------------------------------------------------------------
// | 发票控制器（F1/F4，v2.38.7：申请→审批→财务开票三段式）
// | 权限：invoice:apply=提交申请（普通用户）；invoice:create=开票/红冲/作废/删除（财务/经理/法务）
// +----------------------------------------------------------------------

namespace app\controller;

use app\BaseController;
use app\common\logic\ContractLogic;
use app\common\logic\InvoiceLogic;
use app\common\logic\CompanyLogic;
use app\common\logic\ApprovalSubmitService;
use app\common\service\AuditService;
use think\facade\Db;
use think\facade\Log;

class InvoiceController extends BaseController
{
    /** 合同下发票列表（合同详情页） */
    public function list($contractId)
    {
        $this->requirePermission('invoice:view');
        // 越权防护：仅允许访问自己数据范围内的合同发票
        if (!ContractLogic::accessible((int)$contractId)) {
            return json_error('无权限或无此合同');
        }
        $list = InvoiceLogic::getList((int)$contractId);
        return json_success($list);
    }

    /**
     * 提交开票申请（F1：写入发票 + 创建审批实例，事务内一步到位）
     * 普通用户（invoice:apply）可申请；关联合同可选——选合同则校验合同状态/金额上限，
     * 未选合同（独立快捷申请）仅校验金额为正。
     */
    public function add()
    {
        $this->requirePermission('invoice:apply');
        $contractId = (int)$this->getPost('contract_id', 0);
        $amount     = (float)$this->getPost('amount', 0);
        $ourCompanyId = (int)$this->getPost('our_company_id', 0);
        $contentDesc  = trim((string)$this->getPost('content_desc', ''));

        if ($amount <= 0) {
            return json_error('请填写正确的开票金额');
        }
        if ($ourCompanyId <= 0) {
            return json_error('请选择开票主体');
        }
        if ($contentDesc === '') {
            return json_error('请填写开票内容');
        }

        // 关联合同校验（可选）：选了合同才做合同维度校验
        if ($contractId) {
            $contract = ContractLogic::accessible($contractId);
            if (!$contract) {
                return json_error('无权限或无此合同');
            }
            if ((int)$contract['trade_attr'] === 0) {
                return json_error('该合同为非交易合同，不计入收支，无需开具发票');
            }
            // 2026-08-11：仅销售合同（我方收款方，向客户开票）可申请开票；采购合同（我方付款方）不开放
            if (($contract['direction'] ?? '') !== 'sales') {
                return json_error('仅销售合同（我方收款）可申请开票');
            }
            if (!InvoiceLogic::canRegister($contractId)) {
                return json_error('该合同当前状态不可登记开票');
            }
            $committed = InvoiceLogic::sumCommitted($contractId);
            if (($committed + $amount) > $contract['amount']) {
                return json_error('发票金额已超过合同金额（合同 ¥' . number_format($contract['amount'], 2) . '，已开票 ¥' . number_format($committed, 2) . '）');
            }
        }

        // 税率与税额：税率按开票主体（company_profile.invoice_tax_rate，后台公司管理配置）强制读取，
        // 不信任表单提交值（2026-08-02：表单已无税率组件，选主体自动带出；后端兜底防篡改/防旧客户端漏传）
        $taxRate   = CompanyLogic::getInvoiceTaxRate($ourCompanyId);
        $taxAmount = round($amount / (1 + $taxRate) * $taxRate, 2);

        // 写入发票（默认 PENDING_APPROVAL）
        try {
            $id = InvoiceLogic::createWithinLimit([
            'contract_id'    => $contractId,
            'our_company_id' => $ourCompanyId,
            'content_desc'   => $contentDesc,
            'customer_id'    => (int)$this->getPost('customer_id', 0),
            'amount'         => $amount,
            'tax_rate'       => $taxRate,
            'tax_amount'     => $taxAmount,
            'invoice_type'   => $this->getPost('invoice_type', 'VAT_SPECIAL'),
            'invoice_title'  => $this->getPost('invoice_title', ''),
            'tax_no'         => $this->getPost('tax_no', ''),
            'remark'         => $this->getPost('remark', ''),
            'applicant_id'   => $this->userId,
            'operator_id'    => $this->userId,
            ]);
        } catch (\RuntimeException $e) {
            return json_error($e->getMessage());
        } catch (\Throwable $e) {
            Log::error('开票申请创建失败', ['contract_id' => $contractId, 'error' => $e->getMessage()]);
            return json_error('开票申请创建失败，请稍后重试');
        }

        // 提交发票审批（biz_type=invoice 流程）；失败则删除发票并报错，避免孤儿申请
        try {
            $instanceId = ApprovalSubmitService::submitInvoice($id, $this->userId, 0, $contractId);
            if (!$instanceId) {
                InvoiceLogic::delete($id);
                return json_error('提交审批失败，请稍后重试或联系管理员');
            }
            InvoiceLogic::update($id, ['approval_instance_id' => $instanceId]);
        } catch (\RuntimeException $e) {
            // S-04：业务校验异常（如流程未配置、状态不允许提交）文案可回显
            InvoiceLogic::delete($id);
            return json_error($e->getMessage() ?: '提交审批失败');
        } catch (\Throwable $e) {
            // S-04：系统异常（DB/SQL/内部错误）写日志并回显友好文案，不泄露内部结构
            InvoiceLogic::delete($id);
            \think\facade\Log::error('发票提交审批失败', ['invoice_id' => $id, 'error' => $e->getMessage()]);
            return json_error('提交审批失败，请稍后重试或联系管理员');
        }

        AuditService::log($this->userId, 'create', 'invoice', $id);
        return json_success(['id' => $id, 'approval_instance_id' => $instanceId], '开票申请已提交，等待审批');
    }

    /**
     * 开票（财务操作）：仅「已通过(APPROVED)」可开票（历史 APPLIED 兼容）。
     * 填写发票号/开票日期后置 ISSUED。
     */
    public function update()
    {
        $this->requirePermission('invoice:create');
        $id = (int)$this->getPost('id', 0);
        $inv = InvoiceLogic::find($id);
        if (!$inv) {
            return json_error('发票不存在');
        }
        // 开票权限校验：有 invoice:create 即可（无需再校验合同数据范围——开票是财务职权）
        $status = (string)($inv['status'] ?? '');
        if (!in_array($status, [InvoiceLogic::STATUS_APPROVED, InvoiceLogic::STATUS_APPLIED], true)) {
            return json_error('仅「待开票」状态的发票可开票（当前：' . (InvoiceLogic::STATUS_LABELS[$status] ?? $status) . '）');
        }
        $invoiceNo = trim((string)$this->getPost('invoice_no', ''));
        if ($invoiceNo === '') {
            return json_error('请填写发票号码');
        }
        if (!InvoiceLogic::transitionStatus($id, InvoiceLogic::STATUS_ISSUED, $this->userId)) {
            return json_error('开票状态流转失败，请刷新后重试');
        }
        InvoiceLogic::update($id, [
            'invoice_no'  => $invoiceNo,
            'issued_date' => $this->getPost('issued_date', date('Y-m-d')),
            'issued_by'   => $this->userId,
        ]);
        AuditService::log($this->userId, 'invoice_issue', 'invoice', $id);
        return json_success(null, '开票成功');
    }

    /** 删除开票申请：仅「已驳回/已撤回/历史申请」可删（待审批须先撤回） */
    public function delete()
    {
        $this->requirePermission('invoice:create');
        $id = (int)$this->getPost('id', 0);
        $inv = InvoiceLogic::find($id);
        if (!$inv) {
            return json_error('发票不存在');
        }
        // 申请人/开票权限均可删自己提交的申请
        if ($inv['applicant_id'] != $this->userId && !$this->hasPermission('invoice:create')) {
            return json_error('仅申请人或财务可删除');
        }
        $status = (string)($inv['status'] ?? '');
        if (!in_array($status, [InvoiceLogic::STATUS_REJECTED, InvoiceLogic::STATUS_CANCELLED, InvoiceLogic::STATUS_APPLIED], true)) {
            return json_error('仅已驳回/已撤回的申请可删除，审批中请先撤回');
        }
        InvoiceLogic::delete($id);
        AuditService::log($this->userId, 'delete', 'invoice', $id);
        return json_success(null, '已删除');
    }

    /** 发票作废：仅已开票(ISSUED)可作废 */
    public function void()
    {
        $this->requirePermission('invoice:create');
        $id = (int)$this->getPost('id', 0);
        $inv = InvoiceLogic::find($id);
        if (!$inv) {
            return json_error('发票不存在');
        }
        if (($inv['status'] ?? '') !== InvoiceLogic::STATUS_ISSUED) {
            return json_error('仅「已开票」状态的发票可作废');
        }
        if (!InvoiceLogic::transitionStatus($id, InvoiceLogic::STATUS_VOID, $this->userId)) {
            return json_error('作废状态流转失败，请刷新后重试');
        }
        AuditService::log($this->userId, 'invoice_void', 'invoice', $id);
        return json_success(null, '发票已作废');
    }

    /** 发票红冲：仅已开票(ISSUED)可红冲，生成负数红字发票并关联原发票 */
    public function red()
    {
        $this->requirePermission('invoice:create');
        $id = (int)$this->getPost('id', 0);
        $inv = InvoiceLogic::find($id);
        if (!$inv) {
            return json_error('发票不存在');
        }
        if (($inv['status'] ?? '') !== InvoiceLogic::STATUS_ISSUED) {
            return json_error('仅「已开票」状态的发票可红冲');
        }
        if ((float)$inv['amount'] <= 0) {
            return json_error('金额非正的发票不可红冲');
        }

        // P2-6（M-R8）：红冲三写（插入红字发票 + 原票置 RED + 审计留痕）包同一事务，
        // 任一写失败整体回滚，消除原「先插红字、失败再补偿删除」的崩溃窗口（补偿删除本身也可能失败）。
        $redId = 0;
        try {
            Db::transaction(function () use ($id, $inv, &$redId) {
                $redId = InvoiceLogic::createRed([
                    'contract_id'    => $inv['contract_id'],
                    'our_company_id' => $inv['our_company_id'] ?? 0,
                    'content_desc'   => $inv['content_desc'] ?? '',
                    'amount'         => -abs((float)$inv['amount']),
                    'tax_rate'       => $inv['tax_rate'],
                    'tax_amount'     => -abs((float)$inv['tax_amount']),
                    'invoice_type'   => $inv['invoice_type'],
                    'invoice_title'  => $inv['invoice_title'],
                    'tax_no'         => $inv['tax_no'],
                    'invoice_no'     => $this->getPost('invoice_no', ''),
                    'remark'         => '红冲原发票 #' . $id,
                    'status'         => InvoiceLogic::STATUS_RED,
                    'issued_date'    => date('Y-m-d'),
                    'related_id'     => $id,
                    'operator_id'    => $this->userId,
                ]);
                if (!InvoiceLogic::transitionStatus($id, InvoiceLogic::STATUS_RED, $this->userId)) {
                    throw new \RuntimeException('红冲状态流转失败，请刷新后重试');
                }
                AuditService::log($this->userId, 'invoice_red', 'invoice', $redId);
            });
        } catch (\RuntimeException $e) {
            // 业务校验异常（状态机不允许红冲等）：回显友好文案
            return json_error($e->getMessage());
        } catch (\Throwable $e) {
            Log::error('发票红冲失败，已回滚', ['id' => $id, 'error' => $e->getMessage()]);
            return json_error('红冲失败，请稍后重试');
        }
        return json_success(['id' => $redId], '已开具红字发票');
    }

    /** 我的开票申请（普通用户视角：本人提交的申请列表） */
    public function myList()
    {
        $this->requirePermission('invoice:view');
        [$page, $pageSize] = $this->getPageParams();
        $status = $this->getParam('status', '');
        [$list, $total] = InvoiceLogic::pageMyList($this->userId, $page, $pageSize, $status);
        return layui_table(self::decorate($list), $total);
    }

    /** 待我审批的发票申请（审批人视角：biz_type=invoice 且当前节点包含我的 PENDING 记录） */
    public function pendingApproval()
    {
        $this->requirePermission('invoice:view');
        [$page, $pageSize] = $this->getPageParams();
        [$list, $total] = InvoiceLogic::pagePendingApproval($this->userId, $page, $pageSize);
        return layui_table(self::decorate($list), $total);
    }

    /** 待开票列表（财务视角）：APPROVED（已通过待开票）+ 历史 APPLIED；未关联合同的申请也在此可处理 */
    public function pendingIssue()
    {
        $this->requirePermission('invoice:create');
        [$page, $pageSize] = $this->getPageParams();
        [$list, $total] = InvoiceLogic::pagePendingIssue($page, $pageSize);
        return layui_table(InvoiceLogic::decorateList($list), $total);
    }

    /** 驳回后修改重提（REJECTED → 重新提交审批） */
    public function resubmit()
    {
        $this->requirePermission('invoice:apply');
        $id = (int)$this->getPost('id', 0);
        $inv = InvoiceLogic::find($id);
        if (!$inv || $inv['applicant_id'] != $this->userId) {
            return json_error('无权限操作该申请');
        }
        // P2-3：已撤回(CANCELLED)的申请与已驳回(REJECTED)一样可重新提交（状态机已放开对应重提路径）
        if (!in_array(($inv['status'] ?? ''), [InvoiceLogic::STATUS_REJECTED, InvoiceLogic::STATUS_CANCELLED], true)) {
            return json_error('仅已驳回或已撤回的申请可重新提交');
        }
        try {
            $instanceId = ApprovalSubmitService::submitInvoice($id, $this->userId, 0, (int)$inv['contract_id']);
            if (!$instanceId) {
                return json_error('提交审批失败，请稍后重试');
            }
            InvoiceLogic::update($id, ['approval_instance_id' => $instanceId]);
        } catch (\RuntimeException $e) {
            // S-04：业务校验异常文案可回显
            return json_error($e->getMessage() ?: '提交审批失败');
        } catch (\Throwable $e) {
            // S-04：系统异常写日志并回显友好文案，不泄露内部结构
            \think\facade\Log::error('发票重新提交审批失败', ['invoice_id' => $id, 'error' => $e->getMessage()]);
            return json_error('提交审批失败，请稍后重试或联系管理员');
        }
        return json_success(['id' => $id], '已重新提交审批');
    }

    /** 发票申请详情（AJAX，2026-08-15：我的申请/待我审批/待开票列表共用） */
    public function detail()
    {
        $this->requirePermission('invoice:view');
        $id = (int)$this->getParam('id', 0);
        if ($id <= 0) {
            return json_error('缺少申请 ID');
        }
        $res = self::detailData($id, $this->userId, $this->hasPermission('invoice:create'));
        return $res['ok'] ? json_success($res['data']) : json_error($res['msg']);
    }

    /**
     * 发票申请详情数据（AJAX 与独立详情页共用）
     * @param int $id       发票申请 ID
     * @param int $userId   当前用户 ID
     * @param bool $isFinance 是否财务开票人（invoice:create）
     * @return array ['ok'=>bool, 'msg'=>string, 'data'=>array]
     */
    public static function detailData(int $id, int $userId, bool $isFinance = false): array
    {
        $inv = InvoiceLogic::find($id);
        if (!$inv) {
            return ['ok' => false, 'msg' => '申请不存在'];
        }
        // 可见范围：本人申请 / 财务（invoice:create 开票人）/ 该申请审批链上的审批人
        $isApprover = Db::name('approval_record')->alias('r')
            ->join('approval_instance i', 'r.instance_id = i.id')
            ->where('i.biz_type', 'invoice')->where('i.target_id', $id)
            ->where('r.approver_id', $userId)->count() > 0;
        if ((int)$inv['applicant_id'] !== $userId && !$isFinance && !$isApprover) {
            return ['ok' => false, 'msg' => '无权限查看该申请'];
        }

        // 关联合同
        $contractNo = $contractTitle = '';
        if ((int)($inv['contract_id'] ?? 0) > 0) {
            $c = Db::name('contract')->where('id', (int)$inv['contract_id'])->field('title,contract_no')->find();
            if ($c) {
                $contractTitle = (string)$c['title'];
                $contractNo    = (string)$c['contract_no'];
            }
        }
        // 开票主体 / 申请人
        $companyName = (string)Db::name('company_profile')->where('id', (int)($inv['our_company_id'] ?? 0))->value('name');
        $applicantName = (string)Db::name('user')->where('id', (int)($inv['applicant_id'] ?? 0))->value('name');
        // 发票类型中文（与移动端审批详情口径一致）
        $invTypeMap = ['VAT_SPECIAL' => '增值税专用发票', 'VAT_NORMAL' => '增值税普通发票', 'E_INVOICE' => '电子发票', 'OTHER' => '其他'];
        // 审批流水（该发票全部审批实例，按实例与节点排序）
        $records = Db::name('approval_record')->alias('r')
            ->join('approval_instance i', 'r.instance_id = i.id')
            ->leftJoin('user u', 'r.approver_id = u.id')
            ->where('i.biz_type', 'invoice')->where('i.target_id', $id)
            ->field('r.node_name,r.action,r.comment,r.acted_at,u.name as approver_name')
            ->order('r.instance_id', 'asc')->order('r.id', 'asc')
            ->select()->toArray();
        $actionLabels = [
            'PENDING'     => '待处理',
            'APPROVED'    => '通过',
            'REJECTED'    => '驳回',
            'TRANSFERRED' => '转交',
            'RECALLED'    => '撤回',
        ];
        foreach ($records as &$rec) {
            $rec['action_label'] = $actionLabels[$rec['action']] ?? $rec['action'];
        }
        unset($rec);

        return ['ok' => true, 'msg' => '', 'data' => [
            'id'               => (int)$inv['id'],
            'inst_id'          => (int)($inv['approval_instance_id'] ?? 0),
            'is_applicant'     => (int)$inv['applicant_id'] === $userId,
            // 撤回入口门控（与列表/后端 recall 校验同口径：仅申请人本人、待审批、且已挂审批实例）
            'can_recall'       => (int)$inv['applicant_id'] === $userId
                && ($inv['status'] ?? '') === InvoiceLogic::STATUS_PENDING_APPROVAL
                && (int)($inv['approval_instance_id'] ?? 0) > 0,
            'content_desc'     => $inv['content_desc'] ?? '',
            'invoice_title'    => $inv['invoice_title'] ?? '',
            'tax_no'           => $inv['tax_no'] ?? '',
            'invoice_type'     => $inv['invoice_type'] ?? '',
            'invoice_type_label' => $invTypeMap[$inv['invoice_type'] ?? ''] ?? ($inv['invoice_type'] ?? ''),
            'amount'           => (float)($inv['amount'] ?? 0),
            'tax_rate'         => $inv['tax_rate'] ?? 0,
            'tax_amount'       => (float)($inv['tax_amount'] ?? 0),
            'invoice_no'       => $inv['invoice_no'] ?? '',
            'issued_date'      => $inv['issued_date'] ?? '',
            'status'           => $inv['status'] ?? '',
            'status_label'     => InvoiceLogic::STATUS_LABELS[$inv['status'] ?? ''] ?? ($inv['status'] ?? ''),
            'remark'           => $inv['remark'] ?? '',
            'contract_id'      => (int)($inv['contract_id'] ?? 0),
            'contract_no'      => $contractNo,
            'contract_title'   => $contractTitle,
            'our_company_name' => $companyName,
            'applicant_name'   => $applicantName,
            'created_at'       => $inv['created_at'] ?? '',
            'records'          => $records,
        ]];
    }

    /** 列表装饰：复用 InvoiceLogic::decorateList（开票主体名/申请人名/状态标签） */
    private static function decorate(array $list): array
    {
        return InvoiceLogic::decorateList($list);
    }
}
