<?php
namespace app\common\logic;

use think\facade\Db;

/**
 * 回款/付款计划逻辑（Phase 1.6：从 MobileController::contractDetail 下沉，消除 Db 直查）
 */
class PaymentLogic
{
    /**
     * 合同回款/付款计划时间线 + 汇总
     * @param int $contractId
     * @return array ['payments'=>array, 'paid_amount'=>float, 'plan_amount'=>float]
     */
    public static function getContractTimeline(int $contractId): array
    {
        $payments = Db::name('payment_record')
            ->where('contract_id', $contractId)
            ->order('planned_date', 'asc')
            ->select()->toArray();
        $paidAmount = 0.0;
        $planAmount = 0.0;
        foreach ($payments as $p) {
            $planAmount += (float)($p['amount'] ?? 0);
            // P0-1：已收口径统一用 paid_amount（实际确认金额），部分确认时 amount 已调减，二者一致
            if (($p['status'] ?? '') === 'PAID') {
                $paidAmount += (float)($p['paid_amount'] ?? 0);
            }
        }
        return ['payments' => $payments, 'paid_amount' => $paidAmount, 'plan_amount' => $planAmount];
    }

    /**
     * 按合同 id 批量查询回款/付款记录（Phase 1.9 从 customerDetail() 下沉）
     * @param array $cIds 合同 id 列表
     * @param array $titleMap [contract_id => title] 可选，提供则回填 contract_title
     * @return array 回款/付款记录列表
     */
    public static function getListByContractIds(array $cIds, array $titleMap = []): array
    {
        if (empty($cIds)) return [];
        $payments = Db::name('payment_record')
            ->where('contract_id', 'in', $cIds)
            ->order('planned_date', 'desc')
            ->limit(30)
            ->select()
            ->toArray();
        if (!empty($titleMap)) {
            foreach ($payments as &$p) {
                $p['contract_title'] = $titleMap[$p['contract_id']] ?? '';
            }
            unset($p);
        }
        return $payments;
    }

    /** 取单条回款记录（用于确认/撤销/删除前的归属与状态校验） */
    public static function getById(int $id): ?array
    {
        return Db::name('payment_record')->find($id) ?: null;
    }

    /** 合同下回款列表（按计划日期升序） */
    public static function getListByContract(int $contractId): array
    {
        $rows = Db::name('payment_record')
            ->where('contract_id', $contractId)
            ->order('planned_date', 'asc')
            ->select()
            ->toArray();
        // 收款方式中文标签：从系统字典读取，避免回款列表外露 BANK/CASH 等英文原始码（PC 与移动端共用）
        $methodMap = dict('payment_method');
        foreach ($rows as &$row) {
            $m = $row['payment_method'] ?? '';
            $row['payment_method_label'] = $m ? ($methodMap[$m] ?? $m) : '';
        }
        unset($row);
        return $rows;
    }

    /** 商务催收跟进；不改变到账状态，财务确认职责保持独立。 */
    public static function addCollectionFollow(int $paymentId, int $userId, array $data): int
    {
        $payment = self::getById($paymentId);
        if (!$payment || ($payment['payment_type'] ?? '') !== 'RECEIVABLE') throw new \RuntimeException('应收计划不存在');
        if (!in_array($payment['status'] ?? '', ['PENDING', 'OVERDUE'], true)) throw new \RuntimeException('仅待收或逾期计划可以记录催收');
        $content = trim((string)($data['content'] ?? ''));
        if ($content === '') throw new \RuntimeException('请填写催收内容');
        $now = date('Y-m-d H:i:s');
        return Db::name('payment_collection_follow')->insertGetId([
            'payment_id'=>$paymentId, 'contract_id'=>(int)$payment['contract_id'], 'user_id'=>$userId,
            'content'=>mb_substr($content,0,1000), 'customer_promise'=>mb_substr(trim((string)($data['customer_promise']??'')),0,500),
            'reason'=>mb_substr(trim((string)($data['reason']??'')),0,500), 'promise_date'=>!empty($data['promise_date'])?$data['promise_date']:null,
            'next_follow_at'=>!empty($data['next_follow_at'])?$data['next_follow_at']:null, 'created_at'=>$now, 'updated_at'=>$now,
        ]);
    }

    public static function getCollectionFollows(int $paymentId): array
    {
        return Db::name('payment_collection_follow')->alias('f')->leftJoin('user u','u.id=f.user_id')
            ->field('f.*,u.name user_name')->where('f.payment_id',$paymentId)->order('f.id','desc')->limit(100)->select()->toArray();
    }

    /** 上期回款计划（「复制自上期」用）：按计划日期倒序、id 倒序取最近一条 */
    public static function getPrevByContract(int $contractId): ?array
    {
        $prev = Db::name('payment_record')
            ->where('contract_id', $contractId)
            ->order('planned_date', 'desc')
            ->order('id', 'desc')
            ->find();
        return $prev ?: null;
    }

    /** 已登记回款合计（用于金额上限校验：PENDING/PAID/OVERDUE 计入） */
    public static function sumCommitted(int $contractId): float
    {
        return (float)(Db::name('payment_record')
            ->where('contract_id', $contractId)
            ->where('status', 'in', ['PENDING', 'PAID', 'OVERDUE'])
            ->sum('amount') ?: 0);
    }

    /**
     * 新增回款计划
     * @return int 新记录 id
     */
    public static function create(int $contractId, string $paymentType, float $amount, ?string $plannedDate, string $description, int $operatorId, string $paymentMethod = '', string $milestone = ''): int
    {
        return Db::name('payment_record')->insertGetId([
            'contract_id'    => $contractId,
            'payment_type'   => $paymentType,
            'amount'         => $amount,
            'planned_date'   => $plannedDate ?: null,
            'status'         => 'PENDING',
            'description'    => $description,
            'operator_id'    => $operatorId,
            'payment_method' => $paymentMethod,
            'milestone'      => $milestone,
            'created_at'     => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * 在合同额度锁内新增单期计划，防止并发请求同时通过“已登记金额”校验后造成超额。
     * @throws \RuntimeException 合同不可登记或额度不足
     */
    public static function createWithinLimit(int $contractId, string $paymentType, float $amount, ?string $plannedDate, string $description, int $operatorId, string $paymentMethod = '', string $milestone = ''): int
    {
        if ($amount <= 0) throw new \RuntimeException('回款金额必须大于 0');
        return Db::transaction(function () use ($contractId, $paymentType, $amount, $plannedDate, $description, $operatorId, $paymentMethod, $milestone) {
            $contract = self::lockContractForFinancialWrite($contractId);
            self::assertFinancialWriteAllowed($contract);
            $committed = self::sumCommitted($contractId);
            if ($committed + $amount > (float)$contract['amount'] + 0.001) {
                throw new \RuntimeException('回款总额已超过合同金额（合同 ¥' . number_format((float)$contract['amount'], 0)
                    . '，已登记 ¥' . number_format($committed, 0) . '）');
            }
            return self::create($contractId, $paymentType, $amount, $plannedDate, $description, $operatorId, $paymentMethod, $milestone);
        });
    }

    /** 在同一合同额度锁内批量新增计划，保证整批全成或全败。 */
    public static function createBatchWithinLimit(int $contractId, string $paymentType, array $items, int $operatorId): int
    {
        if (empty($items) || count($items) > 10) throw new \RuntimeException('单次计划数量不正确');
        return Db::transaction(function () use ($contractId, $paymentType, $items, $operatorId) {
            $contract = self::lockContractForFinancialWrite($contractId);
            self::assertFinancialWriteAllowed($contract);
            $total = 0.0;
            foreach ($items as $item) {
                $amount = (float)($item['amount'] ?? 0);
                if ($amount <= 0) throw new \RuntimeException('存在无效的金额，请检查各期计划');
                $total += $amount;
            }
            $committed = self::sumCommitted($contractId);
            if ($committed + $total > (float)$contract['amount'] + 0.001) {
                throw new \RuntimeException('各期合计超过合同余额（合同 ¥' . number_format((float)$contract['amount'], 0)
                    . '，已登记 ¥' . number_format($committed, 0) . '）');
            }
            foreach ($items as $item) {
                self::create($contractId, $paymentType, (float)$item['amount'],
                    !empty($item['planned_date']) ? trim((string)$item['planned_date']) : null,
                    trim((string)($item['description'] ?? '')), $operatorId,
                    trim((string)($item['payment_method'] ?? '')), trim((string)($item['milestone'] ?? '')));
            }
            return count($items);
        });
    }

    /** MySQL 使用合同行锁串行化同一合同的财务写入；SQLite 由写事务串行化。 */
    private static function lockContractForFinancialWrite(int $contractId): ?array
    {
        $q = Db::name('contract')->where('id', $contractId)->where('is_deleted', 0);
        if (config('database.default') === 'mysql') $q->lock(true);
        return $q->find() ?: null;
    }

    private static function assertFinancialWriteAllowed(?array $contract): void
    {
        if (!$contract) throw new \RuntimeException('合同不存在或已删除');
        if ((int)($contract['trade_attr'] ?? 0) === 0) throw new \RuntimeException('该合同为非交易合同，不计入收支');
        if (!in_array($contract['status'] ?? '', [
            ContractLogic::STATUS_EXECUTING,
        ], true)) throw new \RuntimeException('该合同当前状态不可登记回款');
    }

    /**
     * 确认收款（CR-12：支持部分确认，金额 ≤ 应收；剩余自动拆为新的待收记录）
     * 事务包裹母记录调减 + 子记录拆分，保证应收总额不变，杜绝资金虚增。
     */
    public static function confirm(int $id, string $method, string $actDate, float $confirmAmount, ?string $invoiceNo = null, ?string $description = null): void
    {
        $contractId = null;
        Db::transaction(function () use ($id, $method, $actDate, $confirmAmount, $invoiceNo, $description, &$contractId) {
            // P1-2（并发幂等）：事务内加锁重读记录并复核状态/金额（MySQL FOR UPDATE，SQLite 靠事务串行化）。
            // 两个并发确认对同一 PENDING 记录做部分确认时，先提交者已把母记录调减为确认额并插入剩余子记录，
            // 后提交者锁内重读将看到状态已变（PAID）或金额已调减，被前置拦截，避免重复拆分虚增应收。
            $q = Db::name('payment_record')->where('id', $id);
            if (config('database.default') === 'mysql') {
                $q->lock(true);
            }
            $record = $q->find();
            if (!$record || $record['status'] === 'PAID') {
                return;
            }
            // P2-5：非法金额显式报错（此前静默 return 会被控制器误判为"确认收款成功"）。
            // 未传参时控制器以 0 入参 = 全额确认语义保留；仅对显式非法值（负数/超额）抛业务异常。
            if ($confirmAmount < 0) {
                throw new \RuntimeException('确认金额必须大于 0');
            }
            // 默认全额确认；部分确认须 ≤ 应收金额
            if ($confirmAmount == 0) {
                $confirmAmount = (float)$record['amount'];
            }
            if ($confirmAmount > (float)$record['amount'] + 0.001) {
                throw new \RuntimeException('确认金额不能超过应收金额');
            }

            $originalAmount = (float)$record['amount'];
            $isPartial      = $confirmAmount < $originalAmount - 0.001;
            // 母记录应收(amount)同步调减为实际确认额（部分确认时）
            $update = [
                'status'         => 'PAID',
                'actual_date'    => $actDate,
                'payment_method' => $method,
                'paid_amount'    => $confirmAmount,
                'amount'         => $isPartial ? $confirmAmount : $originalAmount,
                'invoice_no'     => $invoiceNo !== null ? trim($invoiceNo) : $record['invoice_no'],
                'updated_at'     => date('Y-m-d H:i:s'),
            ];
            // 备注：非空时覆盖原值（允许确认时补充说明），空字符串保留原值，避免覆盖添加回款时的说明
            if ($description !== null && trim($description) !== '') {
                $update['description'] = trim($description);
            }
            Db::name('payment_record')->where('id', $id)->update($update);
            // 部分确认：剩余金额拆为新的待收记录，保持应收不丢
            $remain = $originalAmount - $confirmAmount;
            if ($remain > 0.001) {
                Db::name('payment_record')->insert([
                    'contract_id'  => $record['contract_id'],
                    'payment_type' => $record['payment_type'],
                    'amount'       => $remain,
                    'planned_date' => $record['planned_date'],
                    'status'       => 'PENDING',
                    'parent_id'    => $id,
                    'operator_id'  => $record['operator_id'],
                    'created_at'   => date('Y-m-d H:i:s'),
                ]);
            }
            $contractId = (int)$record['contract_id'];
        });
    }

    /**
     * 撤销已收款（CR-13：回退为待确认，部分确认拆出的剩余待收子记录一并清除避免重复计应收）
     * 事务包裹：清除子记录 + 母记录恢复原始应收。
     * @throws \RuntimeException 存在已确认/逾期的下级记录（二次部分确认等）时拒绝撤销，提示先处理下级
     */
    public static function revoke(int $id): void
    {
        $record = Db::name('payment_record')->find($id);
        if (!$record) {
            return;
        }
        if ($record['status'] !== 'PAID') {
            return;
        }
        // P2-2：部分确认链更深（子记录又被二次确认/逾期）时，撤销母记录会残留「母 PENDING + 子 PAID/OVERDUE」并存，
        // sumCommitted/账龄口径下状态分组重复。存在非 PENDING 子记录时先处理下级，再撤销母记录。
        $unresolvedChildren = (int)Db::name('payment_record')
            ->where('parent_id', $id)
            ->whereNotIn('status', ['PENDING', 'REVOKED', 'CANCELLED'])
            ->count();
        if ($unresolvedChildren > 0) {
            throw new \RuntimeException('该收款存在已确认或逾期的下级记录，请先撤销下级记录');
        }
        Db::transaction(function () use ($id, $record) {
            // 部分确认拆出的剩余待收子记录金额加回母记录，恢复原始应收
            $childRemain = (float)(Db::name('payment_record')
                ->where('parent_id', $id)
                ->where('status', 'PENDING')
                ->sum('amount') ?? 0);
            // 清除部分确认生成的剩余待收子记录（未单独收款）
            Db::name('payment_record')->where('parent_id', $id)->where('status', 'PENDING')->delete();
            Db::name('payment_record')->where('id', $id)->update([
                'status'         => 'PENDING',
                'paid_amount'    => 0,
                'actual_date'    => null,
                'payment_method' => '',
                'amount'         => (float)$record['amount'] + $childRemain,
                'updated_at'     => date('Y-m-d H:i:s'),
            ]);
        });
    }

    /**
     * 合同是否存在逾期未结回款（P2-10：手动终结合同前的财务校验，仅拦截逾期，质保金等 PENDING 不受影响；
     * 仅应收 RECEIVABLE——应付(PAYABLE)逾期属我方付款计划，不拦截我方合同收尾）
     */
    public static function hasOverdue(int $contractId): bool
    {
        return (int)Db::name('payment_record')
            ->where('contract_id', $contractId)
            ->where('status', 'OVERDUE')
            ->where('payment_type', 'RECEIVABLE')
            ->count() > 0;
    }

    /** 标记逾期 */
    public static function markOverdue(int $id): void
    {
        $record = Db::name('payment_record')->where('id', $id)->find();
        if (!$record) return;
        Db::transaction(function () use ($record) {
            Db::name('payment_record')->where('id', (int)$record['id'])->update([
                'status'     => 'OVERDUE',
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        });
    }

    /**
     * P1-4（deep review）：逾期自动置 OVERDUE 批量扫描 —
     * 将「待收(PENDING)且计划回款日已过」的应收(RECEIVABLE)回款记录自动置为逾期(OVERDUE)，
     * 统一账龄/信用/提醒三处口径（此前仅手动标记，未标记的逾期不计入账龄与信用评级）。
     * 仅处理应收：应付(PAYABLE)计划逾期不标记（无消费方，避免误判"我方回款逾期"）。
     * 幂等：已 OVERDUE 的记录不在扫描范围；重复执行安全。
     * 供命令 php think payment:mark-overdue 每日调用。
     * @return int 本次置为逾期的记录数
     */
    public static function autoMarkOverdue(): int
    {
        $today = date('Y-m-d');
        $ids = Db::name('payment_record')
            ->where('status', 'PENDING')
            ->where('payment_type', 'RECEIVABLE')
            ->whereNotNull('planned_date')
            ->where('planned_date', '<>', '')
            ->where('planned_date', '<', $today)
            ->column('id');

        // P2-5（M-R7）：整个批量置逾期包外层事务（参照 ContractLogic::autoExpire 写法），
        // 要么全部置 OVERDUE 成功落库，要么整体回滚，避免中途失败导致部分记录已逾期、
        // 部分未处理与审计不一致的脏数据。markOverdue 内部嵌套事务以 savepoint 形式并入本事务（TP6 原生支持）。
        // 幂等：已 OVERDUE 的记录不在扫描范围，重复执行安全。
        return Db::transaction(function () use ($ids) {
            $count = 0;
            foreach ($ids as $id) {
                self::markOverdue((int)$id);
                \app\common\service\AuditService::log(0, 'mark_overdue', 'payment_record', (int)$id); // operatorId=0 代表系统自动
                $count++;
            }
            return $count;
        });
    }

    /** 删除回款记录 */
    public static function delete(int $id): void
    {
        $record = Db::name('payment_record')->where('id', $id)->find();
        Db::name('payment_record')->where('id', $id)->delete();
    }

}
