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
     * P2-4（M6）：判断合同是否处于「可登记回款/开票」状态。
     * 采用「正向白名单（默认拒绝）」：仅处于生效/执行中的状态允许登记财务，
     * 其余状态一律拒绝，避免对未生效或已关闭的合同登记回款/开票导致财务失真。
     * 允许登记的状态与合同状态字典（ContractLogic::STATUS_*）保持一致：
     *   - APPROVED  已通过
     *   - SIGNED    历史已签（签署功能已移除，仅存量）
     *   - EXECUTING 执行中
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
            ContractLogic::STATUS_APPROVED,
            ContractLogic::STATUS_SIGNED,
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
