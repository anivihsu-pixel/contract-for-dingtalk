<?php
// +----------------------------------------------------------------------
// | 相对方 360 聚合逻辑（客户 / 供应商统一往来档案）
// +----------------------------------------------------------------------

namespace app\common\logic;

use think\facade\Db;
use think\facade\Session;

class PartyLogic
{
    /** 相对方类型白名单 */
    const TYPES = ['customer', 'supplier'];

    /**
     * 相对方行查询（客户/供应商共用骨架，P2-8【M-A2】收敛重复逻辑）
     * 原 CustomerLogic/SupplierLogic::getPartyRows 两处近乎一致的查询统一到此。
     * @param string $table      表名 customer|supplier
     * @param string $extraField 除公共字段外额外选择的字段（当前供应商使用 type，客户不传）
     * @param string $keyword    关键词（名称/联系人/电话）
     * @param int    $limit      安全上限（arch P1-3：客户/供应商合并列表防全量载入，命中即截断）
     */
    public static function getPartyRows(string $table, string $extraField, string $keyword, int $limit = 200): array
    {
        $q = Db::name($table)->where('is_deleted', 0);
        AuthLogic::appendDataScope($q, 'owner_id', 'dept_id');
        self::appendCustomerShare($q, $table); // v2.45.0：追加共享客户（合同/相对方选择器可见）
        if ($keyword) {
            $q->where('name|contact_name|contact_mobile', 'like', '%' . $keyword . '%');
        }
        $fields = 'id, name, contact_name, status, owner_id, dept_id';
        if ($extraField !== '') {
            $fields = 'id, name, contact_name, ' . $extraField . ', status, owner_id, dept_id';
        }
        return $q->field($fields)
            ->order('id', 'desc')
            ->limit($limit)
            ->select()->toArray();
    }

    /**
     * 联合搜索相对方（合同创建选甲乙方用，P2-8【M-A2】收敛重复逻辑）
     * 字段形状保持与原 CustomerLogic/SupplierLogic::searchParty 一致，供控制器统一打标签合并。
     * @param array $opts 选项：
     *   - selfDefault   bool   无关键词时是否返回"本公司"列表（客户 true / 供应商 false）
     *   - selfField     string 无关键词时自滤字段（默认 is_self）
     *   - selfLiteral   string 无关键词时 type_name 字面量（如 '客户'）
     *   - partyType     string party_type 值（'customer' / 'supplier'）
     *   - searchFields  string 关键词搜索字段（如 'name|contact_name|contact_mobile'）
     *   - typeNameField string 关键词分支 type_name 表达式（如 "''" 或 "type"）
     *   - creditCode    bool   是否选择真实 credit_code 字段
     *   - orderBy       string|null 排序（如 'is_self desc'）
     */
    public static function searchParty(string $table, string $keyword, array $opts): array
    {
        if ($keyword === '') {
            if (empty($opts['selfDefault'])) {
                return [];
            }
            // 空关键词分支（本公司默认客户）同样收敛数据范围，避免未来收紧基础权限后成为缺口
            $q = Db::name($table)->where('is_deleted', 0)->where('status', 1)
                ->where($opts['selfField'] ?? 'is_self', 1);
            AuthLogic::appendDataScope($q, 'owner_id', 'dept_id');
            // v2.47.x：contact_mobile 一并返回——合同表单选择客户时带出电话
            return $q->field("id, name, contact_name, contact_mobile, credit_code, '" . ($opts['selfLiteral'] ?? '') . "' AS type_name, '" . $opts['partyType'] . "' AS party_type, is_self")
                ->limit(10)->select()->toArray();
        }
        $q = Db::name($table)->where('is_deleted', 0)->where('status', 1)
            ->where($opts['searchFields'], 'like', '%' . $keyword . '%');
        AuthLogic::appendDataScope($q, 'owner_id', 'dept_id');
        self::appendCustomerShare($q, $table); // v2.45.0：追加共享客户（合同创建搜索可见）
        $typeName = $opts['typeNameField'] ?? "''";
        $credit   = !empty($opts['creditCode']) ? 'credit_code' : "'' AS credit_code";
        $self     = !empty($opts['selfDefault']) ? 'is_self' : '0 AS is_self';
        $q->field("id, name, contact_name, contact_mobile, {$credit}, {$typeName} AS type_name, '" . $opts['partyType'] . "' AS party_type, {$self}");
        if (!empty($opts['orderBy'])) {
            [$obField, $obDir] = explode(' ', $opts['orderBy'], 2) + [1 => 'desc'];
            $q->order($obField, $obDir ?: 'desc');
        }
        return $q->limit(10)->select()->toArray();
    }

    /** 基础档案字段（按类型取表名与名称/联系字段） */
    private static function tableOf(string $type): ?string
    {
        return $type === 'customer' ? 'customer' : ($type === 'supplier' ? 'supplier' : null);
    }

    /**
     * v2.45.0：客户查询追加共享可见（用户级/部门级白名单放行）。
     * 仅对 customer 表生效；has_all（全量可见）或非 Web 上下文无需追加。
     */
    private static function appendCustomerShare(&$q, string $table): void
    {
        if ($table !== 'customer') return;
        $user = Session::get('user');
        if (!$user) return;
        if (AuthLogic::visibility()['has_all']) return;
        $sharedIds = CustomerLogic::getSharedCustomerIds((int)$user['id'], (int)($user['dept_id'] ?? 0));
        if ($sharedIds) {
            $q->whereOr('id', 'in', $sharedIds);
        }
    }

    /** 合同关联字段（按类型）——v2.46.0：客户=甲方+乙方客户；供应商=乙方供应商+甲方供应商 */
    private static function linkFieldOf(string $type): ?array
    {
        if ($type === 'customer') {
            return ['party_b_customer_id', 'party_a_customer_id'];
        }
        if ($type === 'supplier') {
            return ['supplier_id', 'party_a_supplier_id'];
        }
        return null;
    }

    /** 回款类型（按类型：客户=应收，供应商=应付） */
    private static function paymentTypeOf(string $type): ?string
    {
        return $type === 'customer' ? 'RECEIVABLE' : ($type === 'supplier' ? 'PAYABLE' : null);
    }

    /**
     * 取相对方基础档案
     * @return array|null
     */
    public static function getBase(string $type, int $id): ?array
    {
        if (!in_array($type, self::TYPES, true)) return null;
        $tbl = self::tableOf($type);
        $row = Db::name($tbl)->where('id', $id)->where('is_deleted', 0)->find();
        if ($row) {
            $row['_type']   = $type;
            $row['_role']   = $type === 'customer' ? '应收' : '应付';   // 往来角色
            $row['_role_field'] = $type === 'customer' ? '客户' : '供应商';
        }
        return $row ?: null;
    }

    /** 关联合同 id 列表 */
    public static function getContractIds(string $type, int $id): array
    {
        $fields = self::linkFieldOf($type);
        if (!$fields) return [];
        return Db::name('contract')->where(function ($query) use ($fields, $id) {
            foreach ($fields as $f) { $query->whereOr($f, $id); }
        })->where('is_deleted', 0)
            ->column('id');
    }

    /**
     * 相对方 360 聚合
     * 统计口径：仅 trade_attr=1 且 direction∈(sales,purchase) 计入收支；余额=总额−已收/已付(PAID)
     */
    public static function get360(string $type, int $id): array
    {
        if (!in_array($type, self::TYPES, true)) {
            return ['ok' => false, 'msg' => '未知相对方类型'];
        }
        $base = self::getBase($type, $id);
        if (!$base) {
            return ['ok' => false, 'msg' => '相对方不存在或已删除'];
        }

        $field     = self::linkFieldOf($type);
        $payType   = self::paymentTypeOf($type);
        $contractIds = self::getContractIds($type, $id);

        // 关联合同（含项目名、方向、交易属性）
        // 客户/供应商 360 视图按数据范围收敛（带 c. 别名）
        $contracts = [];
        if ($contractIds) {
            $q = Db::name('contract')->alias('c')
                ->leftJoin('project p', 'c.project_id = p.id')
                ->whereIn('c.id', $contractIds)
                ->where('c.is_deleted', 0)
                ->field('c.id, c.contract_no, c.title, c.business_type, c.direction, c.trade_attr,
                         c.amount, c.status, c.effective_date, c.expiry_date, p.name as project_name')
                ->order('c.id', 'desc');
            AuthLogic::appendDataScope($q, 'c.owner_id', 'c.dept_id');
            $contracts = $q->select()->toArray();
            // 过滤后的合同 id 集：后续回款/发票/动态仅针对用户可见的合同
            $contractIds = array_column($contracts, 'id') ?: [];
        }

        // 统计：应收/应付总额（仅交易且已生效合同）
        $totalAmount = 0.0;
        $tradeIds = [];
        $excluded = \exclude_framework_contracts_ids();
        foreach ($contracts as $c) {
            if (!empty($c['trade_attr']) && in_array($c['direction'], ['sales', 'purchase'], true)
                && !in_array($c['status'], [ContractLogic::STATUS_DRAFT, ContractLogic::STATUS_REJECTED, ContractLogic::STATUS_PENDING_APPROVAL], true)
                && !in_array((int)$c['id'], $excluded, true)) {
                $totalAmount += (float)($c['amount'] ?? 0);
                $tradeIds[] = (int)$c['id'];
            }
        }

        // 已收/已付（PAID）—— P2-9【M-P3】口径统一用 paid_amount（实际确认金额）
        // 仅交易合同计入，与 totalAmount 口径一致，避免非交易合同回款污染余额
        $receivedPaid = 0.0;
        if ($tradeIds) {
            $receivedPaid = (float)Db::name('payment_record')
                ->whereIn('contract_id', $tradeIds)
                ->where('payment_type', $payType)
                ->where('status', 'PAID')
                ->sum('paid_amount');
        }

        $balance = round($totalAmount - $receivedPaid, 2);
        $pending = round($totalAmount - $receivedPaid, 2); // 未收/未付 = 总额 - 已收/已付

        // 回款记录（最近 10 条）
        $payments = [];
        if ($contractIds) {
            $payments = Db::name('payment_record')->alias('p')
                ->leftJoin('contract c', 'p.contract_id = c.id')
                ->whereIn('p.contract_id', $contractIds)
                ->where('p.payment_type', $payType)
                ->field('p.id, p.contract_id, c.title as contract_title, p.amount, p.planned_date,
                         p.actual_date, p.status, p.payment_method, p.description')
                ->order('p.id', 'desc')
                ->limit(10)->select()->toArray();
        }

        // 发票记录（按方向判定销项/进项）
        $invoices = [];
        if ($contractIds) {
            $invoices = Db::name('contract_invoice')->alias('i')
                ->leftJoin('contract c', 'i.contract_id = c.id')
                ->whereIn('i.contract_id', $contractIds)
                ->field('i.id, i.contract_id, c.title as contract_title, i.amount, i.tax_rate,
                         i.tax_amount, i.invoice_type, i.status, i.issued_date, i.invoice_no')
                ->order('i.id', 'desc')
                ->limit(10)->select()->toArray();
        }

        // 最近动态（审计日志：关联合同的操作；join contract 取标题，避免显示「合同 #id」）
        $activity = [];
        if ($contractIds) {
            $activity = Db::name('audit_log')->alias('al')
                ->leftJoin('contract c', 'al.target_id = c.id')
                ->where('al.target_type', 'contract')
                ->whereIn('al.target_id', $contractIds)
                ->field('al.id, al.user_id, al.action, al.target_id, al.content, al.created_at, c.title as contract_title')
                ->order('al.id', 'desc')
                ->limit(10)->select()->toArray();
        }

        return [
            'ok'      => true,
            'type'    => $type,
            'base'    => $base,
            'stats'   => [
                'contract_count' => count($contracts),
                'total_amount'   => round($totalAmount, 2),
                'received_paid'  => round($receivedPaid, 2),
                'balance'        => $balance,
                'pending'        => $pending,
                'role'           => $base['_role'],
            ],
            'contracts' => $contracts,
            'payments'  => $payments,
            'invoices'  => $invoices,
            'activity'  => $activity,
        ];
    }

    /**
     * 相对方下拉选项（客户+供应商合并，供合同列表筛选等使用）
     * 返回 [['type'=>'customer','id'=>..,'name'=>..], ...]
     */
    public static function options(): array
    {
        $out = [];
        // P2-3（arch）：下拉选项加 limit(500) 安全上限，防客户/供应商全量载入；大数据量建议改用搜索式下拉
        $customers = Db::name('customer')->where('is_deleted', 0)
            ->field('id, name')->order('name')->limit(500)->select()->toArray();
        foreach ($customers as $c) {
            $out[] = ['type' => 'customer', 'id' => $c['id'], 'name' => $c['name'], 'tag' => '客户'];
        }
        $suppliers = Db::name('supplier')->where('is_deleted', 0)
            ->field('id, name')->order('name')->limit(500)->select()->toArray();
        foreach ($suppliers as $s) {
            $out[] = ['type' => 'supplier', 'id' => $s['id'], 'name' => $s['name'], 'tag' => '供应商'];
        }
        return $out;
    }

    /**
     * 相对方往来摘要（v2.38.14：360 能力内嵌业务页用，仅统计+信用，轻量版）
     * 与 get360 同一统计口径（仅 trade_attr=1 且 direction∈(sales,purchase) 计入收支；余额=总额−已收/已付 PAID）。
     * 供合同详情甲乙方 / 供应商详情等业务页内嵌，避免全量 get360（合同/回款/发票/动态多表查询）的开销。
     * @return array|null 相对方不存在或类型非法返回 null
     */
    public static function getSummary(string $type, int $id): ?array
    {
        if (!in_array($type, self::TYPES, true)) {
            return null;
        }
        $base = self::getBase($type, $id);
        if (!$base) {
            return null;
        }

        $contractIds = self::getContractIds($type, $id);
        $payType     = self::paymentTypeOf($type);

        // 统计：应收/应付总额（仅交易且已生效合同，与 get360 口径一致）
        $totalAmount = 0.0;
        $tradeIds = [];
        $excluded = \exclude_framework_contracts_ids();
        if ($contractIds) {
            $q = Db::name('contract')->alias('c')->whereIn('c.id', $contractIds)->where('c.is_deleted', 0)
                ->field('c.id, c.amount, c.direction, c.trade_attr, c.status');
            // P0-2：往来摘要统计同样限定数据范围，避免跨部门合同金额泄露
            AuthLogic::appendDataScope($q, 'c.owner_id', 'c.dept_id');
            $rows = $q->select()->toArray();
            foreach ($rows as $c) {
                if (!empty($c['trade_attr']) && in_array($c['direction'], ['sales', 'purchase'], true)
                    && !in_array($c['status'], [ContractLogic::STATUS_DRAFT, ContractLogic::STATUS_REJECTED, ContractLogic::STATUS_PENDING_APPROVAL], true)
                    && !in_array((int)$c['id'], $excluded, true)) {
                    $totalAmount += (float)($c['amount'] ?? 0);
                    $tradeIds[] = (int)$c['id'];
                }
            }
        }

        // 已收/已付（PAID，用 paid_amount 实际确认金额）
        $receivedPaid = 0.0;
        if ($tradeIds) {
            $receivedPaid = (float)Db::name('payment_record')
                ->whereIn('contract_id', $tradeIds)
                ->where('payment_type', $payType)
                ->where('status', 'PAID')
                ->sum('paid_amount');
        }

        return [
            'type'           => $type,
            'id'             => (int)$id,
            'name'           => (string)($base['name'] ?? ''),
            'contract_count' => count($contractIds),
            'total_amount'   => round($totalAmount, 2),
            'received_paid'  => round($receivedPaid, 2),
            'balance'        => round($totalAmount - $receivedPaid, 2),
            'pending'        => round($totalAmount - $receivedPaid, 2),
            'role'           => $base['_role'] ?? '应收',
        ];
    }

    /**
     * 批量往来汇总（v2.38.14：往来档案列表「往来」列，避免逐行 getSummary 的 N+1）
     * 与 get360/getSummary 同一统计口径（仅 trade_attr=1 且 direction∈(sales,purchase) 计入；余额=总额−已收/已付 PAID）。
     * @param array $parties 相对方列表（每行含 type: customer|supplier, id）
     * @return array key="customer:102" → ['total','received','balance','pending','role','pending_word']
     */
    public static function summarizeBatch(array $parties): array
    {
        $out = [];
        $idsByType = ['customer' => [], 'supplier' => []];
        foreach ($parties as $p) {
            if (isset($idsByType[$p['type']])) {
                $idsByType[$p['type']][] = (int)$p['id'];
            }
        }
        foreach (['customer', 'supplier'] as $type) {
            $ids = array_values(array_unique($idsByType[$type]));
            if (!$ids) {
                continue;
            }
            // 关联合同 + 交易合同判定（按相对方分组，一次查询；排除未生效状态）
            // v2.46.0 同步：与 getContractIds/linkFieldOf 同源，客户=乙方客户+甲方客户、供应商=乙方供应商+甲方供应商，
            // 避免「对方为甲方（我方=乙方）」的合同在往来档案列表漏统计（此前仅 party_b_customer_id/supplier_id）。
            // P0-2：批量往来汇总同样限定数据范围——仅统计用户可见合同的金额，防止跨部门金额泄露
            $linkFields = self::linkFieldOf($type);
            $q = Db::name('contract')->alias('c')
                ->where(function ($query) use ($linkFields, $ids) {
                    foreach ($linkFields as $f) {
                        $query->whereOr('c.' . $f, 'in', $ids);
                    }
                })
                ->where('c.is_deleted', 0)
                ->field('c.id, c.amount, c.direction, c.trade_attr, c.status, c.party_b_customer_id, c.party_a_customer_id, c.supplier_id, c.party_a_supplier_id');
            AuthLogic::appendDataScope($q, 'c.owner_id', 'c.dept_id');
            $rows = $q->select()->toArray();
            $totalByParty = [];
            $tradeContractPid = [];   // 交易合同 id => 相对方 id
            $excluded = \exclude_framework_contracts_ids();
            foreach ($rows as $c) {
                // 归属相对方：按类型取命中字段（客户=乙方/甲方客户，供应商=乙方/甲方供应商）
                $pid = 0;
                if ($type === 'customer') {
                    if (in_array((int)$c['party_b_customer_id'], $ids, true)) $pid = (int)$c['party_b_customer_id'];
                    elseif (in_array((int)$c['party_a_customer_id'], $ids, true)) $pid = (int)$c['party_a_customer_id'];
                } else {
                    if (in_array((int)$c['supplier_id'], $ids, true)) $pid = (int)$c['supplier_id'];
                    elseif (in_array((int)$c['party_a_supplier_id'], $ids, true)) $pid = (int)$c['party_a_supplier_id'];
                }
                if ($pid <= 0) {
                    continue;
                }
                if (!empty($c['trade_attr']) && in_array($c['direction'], ['sales', 'purchase'], true)
                    && !in_array($c['status'], [ContractLogic::STATUS_DRAFT, ContractLogic::STATUS_REJECTED, ContractLogic::STATUS_PENDING_APPROVAL], true)
                    && !in_array((int)$c['id'], $excluded, true)) {
                    $totalByParty[$pid] = ($totalByParty[$pid] ?? 0) + (float)($c['amount'] ?? 0);
                    $tradeContractPid[(int)$c['id']] = $pid;
                }
            }
            // 已收/已付（PAID）按合同批量汇总
            $paidByContract = [];
            if ($tradeContractPid) {
                $payType = $type === 'customer' ? 'RECEIVABLE' : 'PAYABLE';
                $prows = Db::name('payment_record')->whereIn('contract_id', array_keys($tradeContractPid))
                    ->where('payment_type', $payType)
                    ->where('status', 'PAID')
                    ->field('contract_id, SUM(paid_amount) as amt')
                    ->group('contract_id')->select()->toArray();
                foreach ($prows as $pr) {
                    $paidByContract[(int)$pr['contract_id']] = (float)$pr['amt'];
                }
            }
            $paidByParty = [];
            foreach ($tradeContractPid as $cid => $pid) {
                $paidByParty[$pid] = ($paidByParty[$pid] ?? 0) + ($paidByContract[$cid] ?? 0);
            }
            foreach ($ids as $pid) {
                $total = round($totalByParty[$pid] ?? 0, 2);
                $paid  = round($paidByParty[$pid] ?? 0, 2);
                $out["$type:$pid"] = [
                    'total'        => $total,
                    'received'     => $paid,
                    'balance'      => round($total - $paid, 2),
                    'pending'      => round($total - $paid, 2),
                    'role'         => $type === 'customer' ? '应收' : '应付',
                    'pending_word' => $type === 'customer' ? '待收' : '待付',
                ];
            }
        }
        return $out;
    }
}
