<?php
namespace app\controller;

use app\BaseController;
use app\common\logic\ContractLogic;
use app\common\logic\InvoiceLogic;
use app\common\logic\PaymentLogic;
use app\common\service\AuditService;
use think\facade\Db;
use think\facade\Log;

class PaymentController extends BaseController
{
    public function collectionList($paymentId)
    {
        $this->requirePermission('payment:view');
        $payment = PaymentLogic::getById((int)$paymentId);
        if (!$payment || !ContractLogic::accessible((int)$payment['contract_id'])) return json_error('无权限或记录不存在', 403);
        return json_success(PaymentLogic::getCollectionFollows((int)$paymentId));
    }

    public function collectionAdd()
    {
        $this->requirePermission('customer:edit');
        $paymentId = (int)$this->getPost('payment_id', 0);
        $payment = PaymentLogic::getById($paymentId);
        if (!$payment || !ContractLogic::accessible((int)$payment['contract_id'])) return json_error('无权限或记录不存在', 403);
        try {
            $id = PaymentLogic::addCollectionFollow($paymentId, $this->userId, [
                'content'=>$this->getPost('content',''), 'customer_promise'=>$this->getPost('customer_promise',''),
                'reason'=>$this->getPost('reason',''), 'promise_date'=>$this->getPost('promise_date',''),
                'next_follow_at'=>$this->getPost('next_follow_at',''),
            ]);
            AuditService::log($this->userId, 'collection_follow', 'payment_record', $paymentId, ['follow_id'=>$id]);
            return json_success(['id'=>$id], '催收跟进已记录');
        } catch (\RuntimeException $e) { return json_error($e->getMessage()); }
    }
    /** 合同的回款列表 */
    public function list($contractId)
    {
        $this->requirePermission('payment:view');
        if (!ContractLogic::accessible((int)$contractId)) {
            return json_error('无权限或无此合同');
        }
        $list = PaymentLogic::getListByContract((int)$contractId);
        return json_success($list);
    }

    /** 添加回款计划 */
    public function add()
    {
        $this->requirePermission('payment:create');
        $contractId   = (int)$this->getPost('contract_id', 0);
        $amount       = (float)$this->getPost('amount', 0);
        $plannedDate  = $this->getPost('planned_date', '');
        $description  = $this->getPost('description', '');
        $paymentType  = $this->getPost('payment_type', 'RECEIVABLE');
        $paymentMethod = $this->getPost('payment_method', '');
        $milestone    = $this->getPost('milestone', '');

        if (!$contractId || $amount <= 0) {
            return json_error('请填写合同和金额');
        }

        // 金额上限校验：回款/发票累计不得超过合同金额，避免财务数据失真
        $contract = ContractLogic::accessible($contractId);
        if (!$contract) {
            return json_error('无权限或无此合同');
        }
        // 非交易合同：不计入收支，禁止登记回款（友好拦截，非异常堆栈）
        if ((int)$contract['trade_attr'] === 0) {
            return json_error('该合同为非交易合同，不计入收支，无需登记回款');
        }
        // P2-4（M6）：合同审批通过进入执行后才允许登记回款，
        // 草稿/待审批/已驳回/已完成/已终止/已到期/已归档等状态禁止登记，给出明确提示。
        // 复用 InvoiceLogic::canRegister 作为状态校验单一来源，与发票登记保持一致。
        if (!InvoiceLogic::canRegister($contractId)) {
            return json_error('该合同当前状态不可登记回款');
        }
        $committed = PaymentLogic::sumCommitted($contractId);
        if (($committed + $amount) > $contract['amount']) {
            return json_error('回款总额已超过合同金额（合同 ¥' . number_format($contract['amount'], 0) . '，已登记 ¥' . number_format($committed, 0) . '）');
        }

        try {
            $id = PaymentLogic::createWithinLimit($contractId, $paymentType, $amount, $plannedDate ?: null, $description, $this->userId, $paymentMethod, $milestone);
            return json_success(['id' => $id], '添加成功');
        } catch (\RuntimeException $e) {
            return json_error($e->getMessage());
        } catch (\Throwable $e) {
            Log::error('回款添加失败', ['error' => $e->getMessage(), 'contract_id' => $contractId]);
            return json_error('添加失败，请稍后重试');
        }
    }

    /**
     * 复制自上期：返回该合同最近一期回款计划的可预填字段，供「添加回款」弹窗预填，
     * 用户核对（尤其计划日期）后保存即生成新一期。服务端不落库，复用 add() 的校验与写入。
     * M14 里程碑回款务实方案：项目里程碑模块未启用时，用「复制上期」快速延续回款计划。
     */
    public function copyPrev()
    {
        $this->requirePermission('payment:create');
        $contractId = (int)$this->getPost('contract_id', 0);
        if (!$contractId) {
            return json_error('请指定合同');
        }
        $contract = ContractLogic::accessible($contractId);
        if (!$contract) {
            return json_error('无权限或无此合同');
        }
        // 非交易合同 / 不可登记状态：与 add() 保持一致，避免错误引导
        if ((int)$contract['trade_attr'] === 0) {
            return json_error('该合同为非交易合同，不计入收支，无需登记回款');
        }
        if (!InvoiceLogic::canRegister($contractId)) {
            return json_error('该合同当前状态不可登记回款');
        }
        // 上期回款计划：按计划日期倒序、id 倒序取最近一条（下沉 PaymentLogic，控制器零直查）
        $prev = \app\common\logic\PaymentLogic::getPrevByContract($contractId);
        if (!$prev) {
            return json_error('该合同暂无上期回款计划可复制');
        }
        $data = [
            'amount'         => (float)($prev['amount'] ?? 0),
            'payment_type'   => $prev['payment_type'] ?? 'RECEIVABLE',
            'payment_method' => $prev['payment_method'] ?? '',
            'milestone'      => $prev['milestone'] ?? '',
            'description'    => $prev['description'] ?? '',
            'planned_date'   => $prev['planned_date'] ?? '',
        ];
        return json_success($data, '已载入上期回款计划，请核对计划日期后保存');
    }

    /**
     * v2.40.0 P1-5：收款/付款计划模板一键生成多期（预付/中期/尾款比例拆分，事务批量写入）
     * 金额合计 ≤ 合同余额，逐期复用 PaymentLogic::create，与单条添加同口径。
     */
    public function batchAdd()
    {
        $this->requirePermission('payment:create');
        $contractId  = (int)$this->getPost('contract_id', 0);
        $paymentType = $this->getPost('payment_type', 'RECEIVABLE');
        $itemsJson   = $this->getPost('items', '[]');
        $items       = json_decode($itemsJson, true);

        if (!is_array($items) || empty($items)) {
            return json_error('请先生成收款计划');
        }
        if (count($items) > 10) {
            return json_error('单次最多生成10期');
        }
        $contract = ContractLogic::accessible($contractId);
        if (!$contract) {
            return json_error('无权限或无此合同');
        }
        if ((int)$contract['trade_attr'] === 0) {
            return json_error('该合同为非交易合同，不计入收支');
        }
        if (!InvoiceLogic::canRegister($contractId)) {
            return json_error('该合同当前状态不可登记回款');
        }
        // 逐期校验 + 合计
        $total = 0.0;
        foreach ($items as $it) {
            $amt = (float)($it['amount'] ?? 0);
            if ($amt <= 0) {
                return json_error('存在无效的金额，请检查各期计划');
            }
            $total += $amt;
        }
        $committed = PaymentLogic::sumCommitted($contractId);
        if (($committed + $total) > $contract['amount'] + 0.01) {
            return json_error('各期合计超过合同余额（合同 ¥' . number_format($contract['amount'], 0) . '，已登记 ¥' . number_format($committed, 0) . '）');
        }

        try {
            PaymentLogic::createBatchWithinLimit($contractId, $paymentType, $items, $this->userId);
            return json_success(null, '已生成 ' . count($items) . ' 期计划');
        } catch (\RuntimeException $e) {
            return json_error($e->getMessage());
        } catch (\Throwable $e) {
            Log::error('批量生成回款计划失败', ['error' => $e->getMessage(), 'contract_id' => $contractId]);
            return json_error('生成失败，请稍后重试');
        }
    }

    /** 确认收款（CR-12：支持部分确认，金额 ≤ 应收；剩余自动拆为新的待收记录） */
    public function confirm()
    {
        $this->requirePermission('payment:create');
        $id           = (int)$this->getPost('id', 0);
        $method       = $this->getPost('payment_method', '银行转账');
        $actDate      = $this->getPost('actual_date', date('Y-m-d'));
        $confirmAmount = (float)$this->getPost('confirm_amount', 0);
        $invoiceNo    = $this->getPost('invoice_no', '');
        $description  = $this->getPost('description', '');

        $record = PaymentLogic::getById($id);
        if (!$record) return json_error('记录不存在');
        if (!ContractLogic::accessible((int)$record['contract_id'])) return json_error('无权限操作该回款');
        if ($record['status'] === 'PAID') return json_error('该回款已确认收款，无需重复操作');
        // P0（三维度审查）：归档后财务口径统一——新增回款/开票/确认收款均拒绝（与 InvoiceLogic::canRegister 一致）
        $__ct = ContractLogic::getDetail((int)$record['contract_id']);
        if ($__ct && ($__ct['status'] ?? '') === \app\common\logic\ContractLogic::STATUS_ARCHIVED) {
            return json_error('合同已归档，不可确认收款');
        }

        try {
            PaymentLogic::confirm($id, $method, $actDate, $confirmAmount, $invoiceNo, $description);
            return json_success(null, '确认收款成功');
        } catch (\RuntimeException $e) {
            // 业务校验异常（如确认金额超过应收/非法金额）：回显友好文案
            return json_error($e->getMessage());
        } catch (\Throwable $e) {
            Log::error('回款确认失败', ['error' => $e->getMessage(), 'id' => $id]);
            return json_error('确认收款失败，请稍后重试');
        }
    }

    /**
     * CR-13：撤销已收款（回退为待确认）
     * 仅允许已收款(PAID)记录撤销；部分确认时拆分出的剩余待收子记录一并清除，避免重复计应收
     */
    public function revoke()
    {
        $this->requirePermission('payment:create');
        $id = (int)$this->getPost('id', 0);
        $record = PaymentLogic::getById($id);
        if (!$record) return json_error('记录不存在');
        if (!ContractLogic::accessible((int)$record['contract_id'])) return json_error('无权限操作该回款');
        if ($record['status'] !== 'PAID') return json_error('仅已收款记录可撤销');

        try {
            PaymentLogic::revoke($id);
            AuditService::log($this->userId, 'payment_revoke', 'payment', $id);
            return json_success(null, '已撤销收款，回退为待确认');
        } catch (\RuntimeException $e) {
            return json_error($e->getMessage());
        } catch (\Throwable $e) {
            Log::error('回款撤销失败', ['error' => $e->getMessage(), 'id' => $id]);
            return json_error('撤销收款失败，请稍后重试');
        }
    }

    /** 标记逾期 */
    public function overdue()
    {
        $this->requirePermission('payment:create');
        $id = (int)$this->getPost('id', 0);
        $record = PaymentLogic::getById($id);
        if (!$record) return json_error('记录不存在');
        if (!ContractLogic::accessible((int)$record['contract_id'])) return json_error('无权限操作该回款');
        if ($record['status'] === 'PAID') return json_error('已收款项不可标记逾期');
        if ($record['status'] === 'OVERDUE') return json_error('该回款已标记为逾期');
        PaymentLogic::markOverdue($id);
        return json_success(null, '已标记逾期');
    }

    /** 删除回款记录 */
    public function delete()
    {
        $this->requirePermission('payment:create');
        $id = (int)$this->getPost('id', 0);
        $record = PaymentLogic::getById($id);
        if (!$record) return json_error('记录不存在');
        if (!ContractLogic::accessible((int)$record['contract_id'])) return json_error('无权限删除该回款');
        if ($record['status'] === 'PAID') return json_error('已收款项不可直接删除，请先撤销收款');
        PaymentLogic::delete($id);
        return json_success(null, '已删除');
    }
}
