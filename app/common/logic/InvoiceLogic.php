<?php
// +----------------------------------------------------------------------
// | 发票业务逻辑（P0-6 分层铁律下沉：从 InvoiceController 提取 Db 直查）
// | 权限校验（ContractLogic::accessible）由控制器在调用前把关，
// | 本类仅承载发票数据的读写，避免控制器直接 Db::name 直查导致权限收敛点分散。
// +----------------------------------------------------------------------

namespace app\common\logic;

use think\facade\Db;
use app\common\logic\ContractLogic;
use app\common\service\AuditService;

class InvoiceLogic
{
    /** 发票状态枚举（F1/F2，v2.38.7：申请→审批→财务开票三段式） */
    const STATUS_PENDING_APPROVAL = 'PENDING_APPROVAL'; // 待审批（提交后）
    const STATUS_APPROVED         = 'APPROVED';         // 已通过（待开票）
    const STATUS_REJECTED         = 'REJECTED';         // 已驳回（可改重提）
    const STATUS_ISSUED           = 'ISSUED';           // 已开票
    const STATUS_VOID             = 'VOID';             // 已作废
    const STATUS_RED              = 'RED';              // 已红冲
    const STATUS_CANCELLED        = 'CANCELLED';        // 已撤回
    const STATUS_APPLIED          = 'APPLIED';          // 历史申请态（v2.38.7 前数据，只读展示）

    /** 状态 -> 中文标签（前端/列表共用单一真源） */
    const STATUS_LABELS = [
        self::STATUS_PENDING_APPROVAL => '待审批',
        self::STATUS_APPROVED         => '待开票',
        self::STATUS_REJECTED         => '已驳回',
        self::STATUS_ISSUED           => '已开票',
        self::STATUS_VOID             => '已作废',
        self::STATUS_RED              => '已红冲',
        self::STATUS_CANCELLED        => '已撤回',
        self::STATUS_APPLIED          => '申请中（旧）',
    ];

    /** 发票状态流转（F1：审批引擎按 biz_type 分流调用） */
    const TRANSITIONS = [
        self::STATUS_PENDING_APPROVAL => [self::STATUS_APPROVED, self::STATUS_REJECTED, self::STATUS_CANCELLED],
        self::STATUS_APPROVED         => [self::STATUS_ISSUED, self::STATUS_CANCELLED],
        self::STATUS_REJECTED         => [self::STATUS_PENDING_APPROVAL, self::STATUS_CANCELLED],
        self::STATUS_ISSUED           => [self::STATUS_RED, self::STATUS_VOID],
        self::STATUS_CANCELLED        => [self::STATUS_PENDING_APPROVAL], // P2-3：撤回后可重提（与合同撤回回 DRAFT 对称，避免误撤回只能删除重建）
        self::STATUS_APPLIED          => [self::STATUS_ISSUED, self::STATUS_VOID], // 历史申请态兼容：仍可开票/作废
    ];

    /** 状态变更是否合法 */
    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    /**
     * 发票状态变更（F1/F3）：状态机校验 + 更新 + 审计留痕同事务。
     * 供审批引擎（ApprovalActionService biz_type 分流）、开票/红冲/作废/撤回等调用。
     * @param int $id 发票 id
     * @param string $newStatus 目标状态
     * @param int $operatorId 操作人（0=系统自动）
     * @return bool
     */
    public static function transitionStatus(int $id, string $newStatus, int $operatorId = 0): bool
    {
        $row = Db::name('contract_invoice')->find($id);
        if (!$row) return false;
        $from = (string)($row['status'] ?? '');
        if (!self::canTransition($from, $newStatus)) {
            return false;
        }
        return Db::transaction(function () use ($id, $from, $newStatus, $operatorId) {
            Db::name('contract_invoice')->where('id', $id)->update([
                'status'     => $newStatus,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            AuditService::log($operatorId, 'status_change', 'invoice', $id, $from . ' -> ' . $newStatus);
            return true;
        });
    }

    /** 合同下发票列表（按 id 倒序） */
    public static function getList(int $contractId): array
    {
        return Db::name('contract_invoice')
            ->where('contract_id', $contractId)
            ->order('id', 'desc')
            ->select()
            ->toArray();
    }

    /** 已开票金额合计（用于开票上限校验） */
    public static function sumCommitted(int $contractId): float
    {
        // M2 修复：排除已作废(VOID)发票——作废不占开票额度；红冲(RED)为负数金额天然冲抵，保留参与求和
        return (float)(Db::name('contract_invoice')
            ->where('contract_id', $contractId)
            ->where('status', '<>', 'VOID')
            ->sum('amount') ?: 0);
    }

    /** 新增发票，返回新记录 id（F1：默认 PENDING_APPROVAL 待审批，由审批引擎推进） */
    public static function create(array $data): int
    {
        $data['status']     = $data['status'] ?? self::STATUS_PENDING_APPROVAL;
        $data['created_at'] = date('Y-m-d H:i:s');
        return Db::name('contract_invoice')->insertGetId($data);
    }

    /**
     * 在合同额度锁内创建开票申请，避免并发申请合计超过合同金额。
     * 无关联合同的独立申请不占合同额度，直接创建。
     */
    public static function createWithinLimit(array $data): int
    {
        $contractId = (int)($data['contract_id'] ?? 0);
        $amount = (float)($data['amount'] ?? 0);
        if ($amount <= 0) throw new \RuntimeException('开票金额必须大于 0');
        if ($contractId <= 0) return self::create($data);

        return Db::transaction(function () use ($data, $contractId, $amount) {
            $contract = self::lockContractForFinancialWrite($contractId);
            if (!$contract) throw new \RuntimeException('合同不存在或已删除');
            if ((int)($contract['trade_attr'] ?? 0) === 0) throw new \RuntimeException('该合同为非交易合同，不计入收支，无需开具发票');
            if (($contract['direction'] ?? '') !== 'sales') throw new \RuntimeException('仅销售合同（我方收款）可申请开票');
            if (!in_array($contract['status'] ?? '', [
                ContractLogic::STATUS_EXECUTING,
            ], true)) throw new \RuntimeException('该合同当前状态不可登记开票');
            $committed = self::sumCommitted($contractId);
            if ($committed + $amount > (float)$contract['amount'] + 0.001) {
                throw new \RuntimeException('发票金额已超过合同金额（合同 ¥' . number_format((float)$contract['amount'], 2)
                    . '，已开票 ¥' . number_format($committed, 2) . '）');
            }
            return self::create($data);
        });
    }

    /**
     * 合同过审自动开票（v2.51.10）：
     * 合同提交审批时可勾选「随合同申请开票」（contract.invoice_intent 存 JSON，apply=1）；
     * 合同审批通过（含全抄送免审批）进入 EXECUTING 后调用本方法——
     * 校验开票意图 → 生成一张「待开票(APPROVED)」发票（跳过发票审批流，合同审批已把关）→ 审计留痕 →
     * 清空合同 intent（防重复生成）→ 通知配置的开票确认人（默认财务角色）。
     * 与手动开票申请的区别：本方法生成的发票不占发票审批流（approval_instance_id=0），财务直接在待开票列表确认开票。
     * 校验不通过（金额/主体/额度等不合法）时静默跳过（返回 null），不阻断合同过审主流程。
     *
     * @param array $contract 合同行（含 invoice_intent/id/amount/trade_attr/direction/title/contract_no）
     * @param int   $operatorId 过审操作人（审批通过者/系统）
     * @param int   $submitterId 合同提交人（作发票申请人）
     * @return int|null 生成的发票 id；无意图或校验不通过返回 null
     */
    public static function createAutoForExecutingContract(array $contract, int $operatorId, int $submitterId): ?int
    {
        $contractId = (int)($contract['id'] ?? 0);
        $intent     = json_decode((string)($contract['invoice_intent'] ?? ''), true);
        if ($contractId <= 0 || !is_array($intent) || empty($intent['apply'])) {
            return null;
        }
        $amount       = (float)($intent['amount'] ?? 0);
        $ourCompanyId = (int)($intent['our_company_id'] ?? 0);
        $contentDesc  = trim((string)($intent['content_desc'] ?? ''));
        if ($amount <= 0 || $ourCompanyId <= 0 || $contentDesc === '') {
            return null;
        }
        if ((int)($contract['trade_attr'] ?? 1) === 0 || ($contract['direction'] ?? '') !== 'sales') {
            return null;
        }

        $invoiceId = null;
        try {
            $invoiceId = Db::transaction(function () use ($contract, $contractId, $intent, $amount, $ourCompanyId, $contentDesc, $operatorId, $submitterId) {
                // 行锁重读合同，校验 intent 仍在（防并发重复生成）且合同仍为执行中
                $locked = self::lockContractForFinancialWrite($contractId);
                if (!$locked || ($locked['status'] ?? '') !== ContractLogic::STATUS_EXECUTING) {
                    return null;
                }
                $lockedIntent = json_decode((string)($locked['invoice_intent'] ?? ''), true);
                if (!is_array($lockedIntent) || empty($lockedIntent['apply'])) {
                    return null;
                }
                // 额度校验：已开票 + 本次 ≤ 合同金额（超出静默跳过，可稍后手动申请）
                $committed = self::sumCommitted($contractId);
                if ($committed + $amount > (float)$locked['amount'] + 0.001) {
                    return null;
                }

                $taxRate   = CompanyLogic::getInvoiceTaxRate($ourCompanyId);
                $taxAmount = round($amount / (1 + $taxRate) * $taxRate, 2);

                $id = self::create([
                    'contract_id'    => $contractId,
                    'our_company_id' => $ourCompanyId,
                    'content_desc'   => $contentDesc,
                    'customer_id'    => (int)($intent['customer_id'] ?? 0),
                    'amount'         => $amount,
                    'tax_rate'       => $taxRate,
                    'tax_amount'     => $taxAmount,
                    'invoice_type'   => (string)($intent['invoice_type'] ?? 'VAT_SPECIAL'),
                    'invoice_title'  => (string)($intent['invoice_title'] ?? ''),
                    'tax_no'         => (string)($intent['tax_no'] ?? ''),
                    'remark'         => (string)($intent['remark'] ?? ''),
                    'applicant_id'   => $submitterId,
                    'operator_id'    => 0,
                    'status'         => self::STATUS_APPROVED, // 合同已过审，跳过发票审批流，直接待开票
                ]);
                // 清空 intent，防重复生成
                Db::name('contract')->where('id', $contractId)->update(['invoice_intent' => null]);
                AuditService::log($operatorId, 'create', 'invoice', $id, '合同过审自动开票（随合同申请）');
                return $id;
            });
        } catch (\Throwable $e) {
            \think\facade\Log::error('合同过审自动开票失败（不影响合同过审）', [
                'contract_id' => $contractId,
                'error'       => $e->getMessage(),
            ]);
            return null;
        }
        if ($invoiceId) {
            self::notifyAutoInvoicePending($invoiceId, $contract, $amount, $contentDesc);
        }
        return $invoiceId;
    }

    /**
     * 通知开票确认人（v2.51.10）：按合同所属审批流程的 invoice_notify 配置读取确认人
     * （{role_codes:[],user_ids:[]}，每流程独立）；配置为空时默认通知财务角色成员。
     * 站内信指向 PC 待开票列表，钉钉指向移动端财务页。
     */
    private static function notifyAutoInvoicePending(int $invoiceId, array $contract, float $amount, string $contentDesc): void
    {
        // 流程级开票通知配置
        $userIds = [];
        $flowId  = (int)($contract['flow_id'] ?? 0);
        $notify  = [];
        if ($flowId > 0) {
            $raw = Db::name('approval_flow')->where('id', $flowId)->value('invoice_notify');
            if ($raw) {
                $parsed = json_decode((string)$raw, true);
                if (is_array($parsed)) $notify = $parsed;
            }
        }
        foreach (($notify['user_ids'] ?? []) as $uid) {
            $userIds[] = (int)$uid;
        }
        foreach (($notify['role_codes'] ?? []) as $code) {
            $code = trim((string)$code);
            if ($code === '') continue;
            $rid = Db::name('role')->where('code', $code)->value('id');
            if ($rid) {
                $userIds = array_merge($userIds, Db::name('user_role')->where('role_id', (int)$rid)->column('user_id'));
            }
        }
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds), fn($v) => $v > 0)));
        if (empty($userIds)) {
            // 默认兜底：财务角色
            $fid = Db::name('role')->where('code', 'finance')->value('id');
            $userIds = $fid ? Db::name('user_role')->where('role_id', (int)$fid)->column('user_id') : [];
        }
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds), fn($v) => $v > 0)));
        if (empty($userIds)) {
            return;
        }

        $title   = '待开票：' . ($contract['contract_no'] ?? '') . '《' . ($contract['title'] ?? '') . '》';
        $content = "合同已审批通过并进入执行，随合同申请的开票已生成待开票：\n\n"
            . "开票内容：{$contentDesc}\n金额：¥" . number_format($amount, 2) . "\n\n请前往财务中心确认开票。";
        \app\common\service\InternalNotify::send($userIds, \app\common\service\InternalNotify::TYPE_INVOICE_AUTO_PENDING,
            $title, $content, '/finance?tab=invoice');
        try {
            \app\common\service\DingTalkService::sendToLocalUsers($userIds, $title, $content,
                rtrim((string)config('dingtalk.app_url'), '/') . '/m/finance', \app\common\service\InternalNotify::TYPE_INVOICE_AUTO_PENDING);
        } catch (\Throwable $e) {
            // 钉钉失败不影响主流程
        }
    }

    private static function lockContractForFinancialWrite(int $contractId): ?array
    {
        $q = Db::name('contract')->where('id', $contractId)->where('is_deleted', 0);
        if (config('database.default') === 'mysql') $q->lock(true);
        return $q->find() ?: null;
    }

    /**
     * P2-4（M6）：判断合同是否处于「可登记回款/开票」状态。
     * 采用「正向白名单（默认拒绝）」：仅处于生效/执行中的状态允许登记财务，
     * 其余状态一律拒绝，避免对未生效或已关闭的合同登记回款/开票导致财务失真。
     * 允许登记的状态与合同状态字典（ContractLogic::STATUS_*）保持一致：仅 EXECUTING 执行中。
     * 拒绝的状态（示例）：DRAFT 草稿、PENDING_APPROVAL 待审批、REJECTED 已驳回、
     *   COMPLETED 已完成、TERMINATED 已终止、EXPIRED 已到期、ARCHIVED 已归档。
     * 注意：本方法仅校验状态，合同是否存在及数据权限（ContractLogic::accessible）由调用方把关。
     *
     * @param int $contractId 合同 id
     * @return bool 是否可登记
     */
    public static function canRegister(int $contractId): bool
    {
        // 直接按 id 读取合同状态（含已删除过滤），避免与合同状态字典产生字面量漂移
        $contract = Db::name('contract')
            ->where('id', $contractId)
            ->where('is_deleted', 0)
            ->field('status')
            ->find();
        if (empty($contract)) {
            return false; // 合同不存在（或不归当前数据权限范围）视为不可登记
        }
        // 可登记状态白名单：以 ContractLogic 状态常量为单一事实来源
        $registerable = [
            ContractLogic::STATUS_EXECUTING,
        ];
        return in_array($contract['status'] ?? '', $registerable, true);
    }

    /** 查询单条发票（含权限校验由调用方处理） */
    public static function find(int $id): ?array
    {
        return Db::name('contract_invoice')->find($id) ?: null;
    }

    /** 更新发票 */
    public static function update(int $id, array $data): bool
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        return Db::name('contract_invoice')->where('id', $id)->update($data) !== false;
    }

    /** 删除发票 */
    public static function delete(int $id): bool
    {
        return Db::name('contract_invoice')->where('id', $id)->delete() > 0;
    }

    /** 开具红字发票（负数金额关联原发票），返回红字记录 id */
    public static function createRed(array $data): int
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        return Db::name('contract_invoice')->insertGetId($data);
    }

    /**
     * 我的开票申请分页（P1-1：从 InvoiceController 下沉，替代控制器 Db 直查）
     * @return array [rows, total]
     */
    public static function pageMyList(int $userId, int $page, int $pageSize, string $status = ''): array
    {
        $q = Db::name('contract_invoice')->where('applicant_id', $userId);
        if ($status !== '') {
            $q->where('status', $status);
        }
        $total = $q->count();
        $list  = $q->order('id', 'desc')->page($page, $pageSize)->select()->toArray();
        return [$list, $total];
    }

    /**
     * 待我审批的发票申请分页（审批人视角，P1-1：从 InvoiceController 下沉）
     * @return array [rows, total]
     */
    public static function pagePendingApproval(int $userId, int $page, int $pageSize): array
    {
        $q = Db::name('approval_record')->alias('r')
            ->join('approval_instance i', 'r.instance_id = i.id')
            ->join('contract_invoice v', 'i.target_id = v.id')
            ->field('v.*, i.id as inst_id, i.submitted_by, i.submitted_at, i.current_node_order, r.node_name')
            ->where('i.biz_type', 'invoice')
            ->where('i.status', 'PENDING')
            ->where('r.action', 'PENDING')
            ->where('r.approver_id', $userId);
        $total = $q->count();
        $list  = $q->order('i.id', 'desc')->page($page, $pageSize)->select()->toArray();
        return [$list, $total];
    }

    /**
     * 待开票列表分页（财务视角：APPROVED 待开票 + 历史 APPLIED，P1-1：从 InvoiceController 下沉）
     * @return array [rows, total]
     */
    public static function pagePendingIssue(int $page, int $pageSize): array
    {
        $q = Db::name('contract_invoice')
            ->where('status', 'in', [self::STATUS_APPROVED, self::STATUS_APPLIED]);
        $total = $q->count();
        $list  = $q->order('id', 'desc')->page($page, $pageSize)->select()->toArray();
        return [$list, $total];
    }

    /**
     * 列表装饰（F5）：补充开票主体名/申请人名/状态中文标签，供我的申请/待审批/财务中心共用。
     * @param array $list 发票行数组（引用语义返回新数组）
     * @return array
     */
    public static function decorateList(array $list): array
    {
        $companyIds = array_unique(array_filter(array_map(fn($v) => (int)($v['our_company_id'] ?? 0), $list)));
        $userIds    = array_unique(array_filter(array_map(fn($v) => (int)($v['applicant_id'] ?? 0), $list)));
        $companies  = $companyIds ? Db::name('company_profile')->whereIn('id', $companyIds)->column('name', 'id') : [];
        $users      = $userIds ? Db::name('user')->whereIn('id', $userIds)->column('name', 'id') : [];
        foreach ($list as &$v) {
            $v['our_company_name'] = $companies[$v['our_company_id'] ?? 0] ?? '';
            $v['applicant_name']   = $users[$v['applicant_id'] ?? 0] ?? '';
            $v['status_label']     = self::STATUS_LABELS[$v['status'] ?? ''] ?? ($v['status'] ?? '');
        }
        return $list;
    }
}
