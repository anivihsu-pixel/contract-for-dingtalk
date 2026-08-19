<?php
// +----------------------------------------------------------------------
// | 客户业务逻辑
// +----------------------------------------------------------------------

namespace app\common\logic;

use think\facade\Db;
use think\facade\Session;

class CustomerLogic
{
    /**
     * 开票客户下拉选项（发票申请表单 customer 字段数据源）：
     * 返回未删除客户 [{id, name, credit_code}]，供前端联动 fill 动作带出抬头/税号。
     */
    public static function getInvoiceOptions(): array
    {
        return Db::name('customer')->where('is_deleted', 0)
            ->field('id, name, credit_code')->order('id', 'asc')->select()->toArray();
    }

    /** 创建客户 */
    public static function create(array $data): int
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');
        return Db::name('customer')->insertGetId($data);
    }

    /**
     * 创建前去重检测（v2.38.2）
     * @return array{duplicates:array, exact_credit:bool} 可能的重复客户列表
     */
    public static function checkDuplicate(string $name, string $creditCode = '', string $mobile = ''): array
    {
        $duplicates = [];
        // 1) 信用代码精确匹配（高置信度）
        if (!empty($creditCode) && mb_strlen(trim($creditCode)) >= 15) {
            $exact = Db::name('customer')->where('is_deleted', 0)
                ->where('credit_code', trim($creditCode))->find();
            if ($exact) $duplicates[] = $exact;
        }
        // 2) 名称模糊匹配：去除省市区及常见后缀后对比
        $normalized = self::normalizeName($name);
        if (mb_strlen($normalized) >= 4) {
            $candidates = Db::name('customer')->where('is_deleted', 0)
                ->where('name', 'like', '%' . $normalized . '%')
                ->limit(5)->select()->toArray();
            foreach ($candidates as $c) {
                if (self::normalizeName($c['name']) === $normalized && !in_array($c['id'], array_column($duplicates, 'id'))) {
                    $duplicates[] = $c;
                }
            }
        }
        $exactCredit = !empty($duplicates) && trim($creditCode) !== '';
        $exactPhone = false;
        $mobile = preg_replace('/\D+/', '', $mobile);
        if ($mobile !== '') {
            $phoneRows = Db::name('customer')->where('is_deleted', 0)->where('contact_mobile', trim($mobile))->limit(5)->select()->toArray();
            foreach ($phoneRows as $row) {
                $exactPhone = true;
                if (!in_array($row['id'], array_column($duplicates, 'id'))) $duplicates[] = $row;
            }
        }
        return ['duplicates' => $duplicates, 'exact_credit' => $exactCredit, 'exact_phone' => $exactPhone];
    }

    /** 名称归一化：去省/市/区/县/有限公司/股份 等后缀，保留核心名称 */
    private static function normalizeName(string $name): string
    {
        // v2.46.1 修复：去括号正则缺 /u 修饰符——PCRE 无 u 时按字节匹配，字符类 [（(] 的组成字节
        // （全角括号 UTF-8 = E3 80 88/89）会误命中普通汉字字节（如「创」=E5 88 9B 含 0x88），
        // 导致名称被误删字节产生乱码、客户查重失效（同名客户可反复创建，实测「北京华夏文创传媒有限公司」必现）。
        $name = preg_replace('/\s+/u', '', $name);
        $name = preg_replace('/[（(].*?[)）]/u', '', $name); // 去除括号内容
        $suffixes = ['有限责任公司','股份有限公司','有限公司','责任公司','集团','总公司','分公司'];
        foreach ($suffixes as $s) {
            if (mb_substr($name, -mb_strlen($s)) === $s) {
                $name = mb_substr($name, 0, -mb_strlen($s));
                break;
            }
        }
        // 去除省市前缀
        $prefixes = ['北京市','上海市','天津市','重庆市','浙江省','江苏省','广东省','四川省','湖北省','山东省','河南省',
            '湖南省','福建省','安徽省','河北省','辽宁省','陕西省','云南省','贵州省','吉林省','黑龙江省','海南省',
            '深圳市','广州市','杭州市','南京市','武汉市','成都市','西安市','郑州市','长沙市','青岛市','大连市',
            '北京','上海','天津','重庆','浙江','江苏','广东','四川','湖北','山东','深圳','广州','杭州','南京',
            '武汉','成都'];
        foreach ($prefixes as $p) {
            if (mb_strpos($name, $p) === 0) {
                $name = mb_substr($name, mb_strlen($p));
                break;
            }
        }
        return $name;
    }

    /**
     * 共享可见性（v2.38.2）：当客户被多个用户的合同引用时，所有引用者均可查看该客户。
     * 用于客户详情页/编辑页/列表的权限放宽：除归属人和数据范围能看的之外，合同引用者也可看。
     */
    public static function getSharedViewers(int $customerId): array
    {
        return Db::name('contract')
            ->where('party_b_customer_id', $customerId)
            ->where('is_deleted', 0)
            ->where('creator_id', '>', 0)
            ->distinct(true)
            ->column('creator_id');
    }

    // ===================== v2.45.0 客户协作共享 =====================

    /**
     * 客户访问/关联统一判定（v2.45.0，解决「客户归 A 但 B 也要关联签合同」）
     * 判定链：数据范围(本人/部门/ALL) → 显式共享(用户级/部门级) → 集团祖先可见。
     * 共享为白名单放行，仅放行该客户，不改变全局数据范围。
     * @param int $userId 访问者用户ID
     * @param array $customer 客户行（须含 id/owner_id/dept_id/parent_id）
     * @param int $userDeptId 访问者部门ID（0 表示无部门）
     */
    public static function canAccessCustomer(int $userId, array $customer, int $userDeptId = 0): bool
    {
        // 数据范围（本人/部门/ALL，管理员在此放行）
        if (AuthLogic::canAccessRecord((int)($customer['owner_id'] ?? 0), $customer['dept_id'] ?? null)) return true;
        // 显式共享
        if (self::isShared($userId, (int)$customer['id'], $userDeptId)) return true;
        // 历史兼容：曾为该客户签过合同的用户视为可见（与既有 getSharedViewers 语义一致）
        if (Db::name('contract')->where('party_b_customer_id', (int)$customer['id'])
            ->where('is_deleted', 0)->where('creator_id', $userId)->value('id')) {
            return true;
        }
        // 集团层级：任一祖先客户对访问者可见（负责人/共享）即可访问本客户
        $ancIds = self::getGroupAncestorIds((int)$customer['id']);
        if ($ancIds) {
            $ancestors = Db::name('customer')->whereIn('id', $ancIds)
                ->field('id, owner_id, dept_id')->select()->toArray();
            foreach ($ancestors as $a) {
                if (AuthLogic::canAccessRecord((int)$a['owner_id'], $a['dept_id'] ?? null)) {
                    return true;
                }
                if (self::isShared($userId, (int)$a['id'], $userDeptId)) return true;
            }
        }
        return false;
    }

    /** 是否已将某客户共享给该用户或其部门（用户级 USER / 部门级 DEPT） */
    private static function isShared(int $userId, int $customerId, int $userDeptId): bool
    {
        $query = Db::name('customer_share')->where('customer_id', $customerId);
        $query->where(function ($q) use ($userId, $userDeptId) {
            $q->where('target_type', 'USER')->where('target_id', $userId);
            if ($userDeptId > 0) {
                $q->whereOr(function ($qq) use ($userDeptId) {
                    $qq->where('target_type', 'DEPT')->where('target_id', $userDeptId);
                });
            }
        });
        return (bool)$query->value('id');
    }

    /** 当前用户通过共享可见的客户 ID 列表（用户级 + 部门级并集） */
    public static function getSharedCustomerIds(int $userId, int $deptId): array
    {
        $query = Db::name('customer_share')->field('customer_id');
        $query->where(function ($q) use ($userId, $deptId) {
            $q->where('target_type', 'USER')->where('target_id', $userId);
            if ($deptId > 0) {
                $q->whereOr(function ($qq) use ($deptId) {
                    $qq->where('target_type', 'DEPT')->where('target_id', $deptId);
                });
            }
        });
        return array_values(array_unique(array_map('intval', $query->column('customer_id'))));
    }

    /** 客户共享列表（带对象名称，供共享设置面板渲染） */
    public static function getShares(int $customerId): array
    {
        $rows = Db::name('customer_share')->where('customer_id', $customerId)->order('id', 'asc')->select()->toArray();
        $uids = $dids = [];
        foreach ($rows as $r) {
            if (($r['target_type'] ?? '') === 'DEPT') {
                $dids[] = (int)$r['target_id'];
            } else {
                $uids[] = (int)$r['target_id'];
            }
        }
        $uName = $uids ? Db::name('user')->whereIn('id', $uids)->column('name', 'id') : [];
        $dName = $dids ? Db::name('department')->whereIn('id', $dids)->column('name', 'id') : [];
        foreach ($rows as &$r) {
            $tid = (int)$r['target_id'];
            $r['target_name'] = ($r['target_type'] ?? '') === 'DEPT'
                ? ($dName[$tid] ?? '部门#' . $tid)
                : ($uName[$tid] ?? '用户#' . $tid);
        }
        return $rows;
    }

    /**
     * 共享客户给用户/部门（幂等：已存在直接成功）
     * 权限由调用方校验（负责人/超管）。
     */
    public static function shareCustomer(int $customerId, string $targetType, int $targetId, int $createdBy): bool
    {
        $targetType = strtoupper($targetType) === 'DEPT' ? 'DEPT' : 'USER';
        if ($targetId <= 0) return false;
        $exists = Db::name('customer_share')
            ->where('customer_id', $customerId)
            ->where('target_type', $targetType)
            ->where('target_id', $targetId)
            ->value('id');
        if ($exists) return true;
        try {
            Db::name('customer_share')->insert([
                'customer_id' => $customerId,
                'target_type' => $targetType,
                'target_id'   => $targetId,
                'share_level' => 'VIEW',
                'created_by'  => $createdBy,
                'created_at'  => date('Y-m-d H:i:s'),
            ]);
            return true;
        } catch (\Throwable $e) {
            // 并发下唯一键冲突视为已共享
            return Db::name('customer_share')
                ->where('customer_id', $customerId)
                ->where('target_type', $targetType)
                ->where('target_id', $targetId)
                ->value('id') > 0;
        }
    }

    /** 撤销客户共享 */
    public static function unshareCustomer(int $customerId, string $targetType, int $targetId): bool
    {
        $targetType = strtoupper($targetType) === 'DEPT' ? 'DEPT' : 'USER';
        return Db::name('customer_share')
            ->where('customer_id', $customerId)
            ->where('target_type', $targetType)
            ->where('target_id', $targetId)
            ->delete() > 0;
    }

    // ===================== v2.45.0 集团客户层级 =====================

    /** 沿 parent_id 向上收集祖先客户 ID（不含自身，防环保护） */
    public static function getGroupAncestorIds(int $customerId): array
    {
        $ids  = [];
        $cur  = (int)$customerId;
        $seen = [];
        for ($i = 0; $i < 20; $i++) {
            if (isset($seen[$cur])) break; // 防环
            $seen[$cur] = true;
            $pid = (int)Db::name('customer')->where('id', $cur)->value('parent_id');
            if ($pid <= 0 || $pid === $cur) break;
            $ids[] = $pid;
            $cur = $pid;
        }
        return $ids;
    }

    /** 向下收集客户及其全部子孙客户 ID（含自身，集团聚合用） */
    public static function getGroupDescendantIds(int $customerId): array
    {
        $result   = [(int)$customerId];
        $children = [];
        $rows = Db::name('customer')->where('is_deleted', 0)
            ->field('id, parent_id')->select()->toArray();
        foreach ($rows as $r) {
            $children[(int)$r['parent_id']][] = (int)$r['id'];
        }
        $stack = [(int)$customerId];
        while ($stack) {
            $cur = array_pop($stack);
            foreach ($children[$cur] ?? [] as $c) {
                $result[] = $c;
                $stack[]  = $c;
            }
        }
        return $result;
    }

    /** 集团树（自 root 向下构建，节点含 id/name/owner_id/children） */
    public static function getGroupTree(int $rootId): array
    {
        $rows = Db::name('customer')->where('is_deleted', 0)
            ->field('id, name, parent_id, owner_id')->select()->toArray();
        $byParent = [];
        foreach ($rows as $r) {
            $byParent[(int)$r['parent_id']][] = $r;
        }
        $build = null;
        $build = function (int $pid) use (&$build, $byParent) {
            $nodes = [];
            foreach ($byParent[$pid] ?? [] as $r) {
                $nodes[] = [
                    'id'       => (int)$r['id'],
                    'name'     => $r['name'],
                    'owner_id' => (int)$r['owner_id'],
                    'children' => $build((int)$r['id']),
                ];
            }
            return $nodes;
        };
        return $build($rootId);
    }

    /**
     * 集团合同汇总（含自身与全部子孙客户，v2.45.0）
     * @return array{contract_total:int, contract_amount:float, paid_amount:float, children:array}
     */
    public static function getGroupSummary(int $customerId): array
    {
        $descIds = self::getGroupDescendantIds((int)$customerId);
        $contracts = Db::name('contract')->where('is_deleted', 0)
            ->where(function ($q) use ($descIds) {
                $q->whereIn('party_b_customer_id', $descIds)
                    ->whereOr(function ($qq) use ($descIds) {
                        $qq->where('party_a_customer_id', '>', 0)
                            ->whereIn('party_a_customer_id', $descIds);
                    });
            })
            ->field('id, amount, trade_attr, party_b_customer_id, party_a_customer_id')
            ->select()->toArray();

        $contractTotal = count($contracts);
        $contractAmount = 0.0;
        $perCustomer = [];
        foreach ($contracts as $c) {
            // 归属：优先乙方客户，否则甲方客户（避免 a/b 双集团客户重复归属）
            $cid = (int)($c['party_b_customer_id'] ?: $c['party_a_customer_id']);
            $perCustomer[$cid]['count'] = ($perCustomer[$cid]['count'] ?? 0) + 1;
            if ((int)($c['trade_attr'] ?? 1) !== 0) {
                $contractAmount += (float)$c['amount'];
                $perCustomer[$cid]['amount'] = ($perCustomer[$cid]['amount'] ?? 0) + (float)$c['amount'];
            }
        }

        // 已回款合计（PAID）
        $paidAmount = 0.0;
        if ($contracts) {
            $payments = PaymentLogic::getListByContractIds(
                array_values(array_unique(array_column($contracts, 'id'))),
                []
            );
            foreach ($payments as $p) {
                if (($p['status'] ?? '') === 'PAID') {
                    $paidAmount += (float)($p['paid_amount'] ?? $p['amount'] ?? 0);
                }
            }
        }

        // 直接子客户明细（每项聚合其自身+子孙）
        $children = [];
        foreach (self::getGroupTree((int)$customerId) as $node) {
            $cnt = 0;
            $amt = 0.0;
            foreach (self::getGroupDescendantIds((int)$node['id']) as $did) {
                if (isset($perCustomer[$did])) {
                    $cnt += $perCustomer[$did]['count'];
                    $amt += (float)($perCustomer[$did]['amount'] ?? 0);
                }
            }
            $children[] = [
                'id'             => $node['id'],
                'name'           => $node['name'],
                'owner_id'       => $node['owner_id'],
                'contract_total' => $cnt,
                'contract_amount'=> $amt,
            ];
        }

        return [
            'contract_total'  => $contractTotal,
            'contract_amount' => $contractAmount,
            'paid_amount'     => $paidAmount,
            'children'        => $children,
        ];
    }

    /** 更新客户 */
    public static function update(int $id, array $data): bool
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        return Db::name('customer')->where('id', $id)->update($data) !== false;
    }

    /** 软删除客户（级联清理从属数据：共享/联系人/跟进/交接记录——客户档案删除后这些从属信息无保留意义，
     *  否则软删客户留下不可达的孤儿子记录，回收站物理清除（purge）时更会变成永久孤儿） */
    public static function softDelete(int $id): bool
    {
        $ok = false;
        Db::transaction(function () use ($id, &$ok) {
            foreach (['customer_share', 'customer_contact', 'customer_activity', 'customer_transfer_record'] as $t) {
                Db::name($t)->where('customer_id', $id)->delete();
            }
            $ok = Db::name('customer')->where('id', $id)
                ->update(['is_deleted' => 1, 'updated_at' => date('Y-m-d H:i:s')]) > 0;
        });
        return $ok;
    }

    /**
     * 删除前关联校验（CR-16）：返回阻塞删除的具体原因列表；空数组表示可安全删除。
     * 覆盖：该客户作为乙方（party_b_customer_id）或甲方（party_a_customer_id，v2.40.0 我方=乙方时对方为甲方）
     * 被未删除合同引用的活跃合同，并列出合同编号便于处理。
     */
    public static function deleteBlockers(int $id): array
    {
        return self::deleteBlockersMap([$id])[$id] ?? [];
    }

    /**
     * 批量删除阻塞项映射（P2-16【M-A2】回收站列表 N+1 消除）：与 deleteBlockers 同语义同文案，
     * 单次 whereIn 聚合甲方/乙方关联合同 + 集团子客户，返回 [customerId => [阻塞提示]]；无阻塞的 id 不出现。
     */
    public static function deleteBlockersMap(array $ids): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        $map = [];
        if (!$ids) return $map;

        // 乙方引用
        $rowsB = Db::name('contract')->whereIn('party_b_customer_id', $ids)->where('is_deleted', 0)
            ->field('party_b_customer_id, contract_no')->select()->toArray();
        $bNos = [];
        foreach ($rowsB as $r) {
            $bNos[(int)$r['party_b_customer_id']][] = $r['contract_no'];
        }
        foreach ($bNos as $cid => $nos) {
            $map[$cid][] = '存在关联合同：' . implode('、', $nos);
        }
        // 甲方引用（v2.40.0 新增 party_a_customer_id，删除后甲方引用将悬空）
        $rowsA = Db::name('contract')->whereIn('party_a_customer_id', $ids)->where('is_deleted', 0)
            ->field('party_a_customer_id, contract_no')->select()->toArray();
        $aNos = [];
        foreach ($rowsA as $r) {
            $aNos[(int)$r['party_a_customer_id']][] = $r['contract_no'];
        }
        foreach ($aNos as $cid => $nos) {
            $map[$cid][] = '存在甲方关联合同：' . implode('、', $nos);
        }
        // v2.52.x：集团子客户（parent_id 指向本客户且未删除）——子公司为独立客户实体，删除母公司须先解除集团归属，
        // 否则子公司 parent_id 悬空、集团层级断裂
        $rowsChild = Db::name('customer')->whereIn('parent_id', $ids)->where('is_deleted', 0)
            ->field('parent_id, name')->select()->toArray();
        $childNames = [];
        foreach ($rowsChild as $r) {
            $childNames[(int)$r['parent_id']][] = $r['name'];
        }
        foreach ($childNames as $pid => $names) {
            $map[$pid][] = '存在集团子客户（' . count($names) . ' 个）：' . implode('、', $names) . '，请先解除集团归属';
        }
        return $map;
    }

    /** 客户详情 */
    public static function getDetail(int $id): ?array
    {
        return Db::name('customer')->where('id', $id)->where('is_deleted', 0)->find() ?: null;
    }

    /**
     * 客户 360° 聚合视图数据（v2.38.3）：一次查询返回基本信息+信用+关联合同+回款+跟进+统计。
     * 供 PC/移动端详情页 Tab 渲染，避免多次往返查询。
     * @param int $customerId
     * @param bool $isAdmin 是否管理员（决定关联合同数据范围）
     * @return array 空数组表示客户不存在
     */
    public static function getDashboard(int $customerId, bool $isAdmin = false): array
    {
        $customer = self::getDetail($customerId);
        if (!$customer) {
            return [];
        }
        $contractLimit = 20;
        $contracts = ContractLogic::getRelatedList($customerId, $isAdmin, $contractLimit);
        $contractTotal = ContractLogic::getRelatedCount($customerId, $isAdmin);
        $cIds = array_values(array_unique(array_column($contracts, 'id')));
        $cmap = array_column($contracts, 'title', 'id');
        $payments = PaymentLogic::getListByContractIds($cIds, $cmap);
        $activities = self::getActivities($customerId, 20);

        // 统计
        $contractAmount = 0.0;
        foreach ($contracts as $c) {
            // 质量修复：非交易合同(trade_attr=0)金额不计入客户合同金额统计（与全局财务口径一致）
            if ((int)($c['trade_attr'] ?? 1) === 0) continue;
            $contractAmount += (float)($c['amount'] ?? 0);
        }
        $paidAmount = 0.0;
        $overdueAmount = 0.0;
        foreach ($payments as $p) {
            // 仅统计应收（RECEIVABLE）：应付(PAYABLE)为我方付款计划，不计入客户回款/逾期金额（口径统一）
            if (($p['payment_type'] ?? '') !== 'RECEIVABLE') continue;
            $st = $p['status'] ?? '';
            if ($st === 'PAID') {
                $paidAmount += (float)($p['paid_amount'] ?? $p['amount'] ?? 0);
            } elseif ($st === 'OVERDUE') {
                $overdueAmount += (float)($p['amount'] ?? 0);
            }
        }

        $ownerName = '';
        if (!empty($customer['owner_id'])) {
            $ownerName = (string)Db::name('user')->where('id', (int)$customer['owner_id'])->value('name');
        }

        return [
            'customer'       => $customer,
            'owner_name'     => $ownerName,
            'contracts'      => $contracts,
            'contract_total' => $contractTotal,
            'contract_limit' => $contractLimit,
            'payments'       => $payments,
            'activities'     => $activities,
            'stats'          => [
                'contract_total'  => $contractTotal,
                'contract_amount' => $contractAmount,
                'paid_amount'     => $paidAmount,
                'overdue_amount'  => $overdueAmount,
                'activity_count'  => count($activities),
            ],
        ];
    }

    /** 客户列表（含归属人姓名，v2.38.2） */
    public static function getList(int $page, int $pageSize, array $filter = [], array $sort = ['id', 'desc']): array
    {
        // v2.47.8：child_count（子客户数=集团根标记）+ parent_name（集团成员 tooltip 用）
        $query = Db::name('customer')->alias('c')->where('c.is_deleted', 0)
            ->leftJoin('user u', 'c.owner_id = u.id')
            ->leftJoin('customer p', 'p.id = c.parent_id')
            ->field('c.*, u.name as owner_name, p.name as parent_name, '
                . '(select count(*) from customer cc where cc.parent_id = c.id and cc.is_deleted = 0) as child_count');
        // M4 修复：join user 后 owner_id/dept_id 两表都有，必须带别名，否则 MySQL 下非管理员列表报 ambiguous column
        AuthLogic::appendDataScope($query, 'c.owner_id', 'c.dept_id');
        // v2.45.0：追加共享客户（白名单放行，仅客户列表；has_all 无需追加）
        if (!AuthLogic::visibility()['has_all']) {
            $user = Session::get('user');
            $sharedIds = self::getSharedCustomerIds((int)($user['id'] ?? 0), (int)($user['dept_id'] ?? 0));
            if ($sharedIds) {
                $query->whereOr('c.id', 'in', $sharedIds);
            }
        }

        if (!empty($filter['keyword'])) {
            $query->where('c.name|c.contact_name|c.contact_mobile', 'like', '%' . $filter['keyword'] . '%');
        }
        if (isset($filter['status']) && $filter['status'] !== '') {
            $query->where('c.status', (int)$filter['status']);
        }
        // 客户生命周期筛选（客户/成交）
        if (isset($filter['lifecycle_status']) && $filter['lifecycle_status'] !== '') {
            $query->where('c.lifecycle_status', $filter['lifecycle_status']);
        }
        // v2.52.2：查看范围「我的客户」——owner_id 过滤（AND 追加；共享/数据范围条件与其组合仍正确）
        if (isset($filter['owner_id']) && $filter['owner_id'] !== '') {
            $query->where('c.owner_id', (int)$filter['owner_id']);
        }

        $total = $query->count();
        $list  = $query->order('c.' . $sort[0], $sort[1])->page($page, $pageSize)->select()->toArray();

        return ['list' => $list, 'total' => $total];
    }

    /**
     * 转移客户（P2-6/M8）
     * 用「owner_id=fromUserId 条件下原子更新」替代先查后改：仅当客户当前确由 fromUserId 持有时更新才生效，
     * 受影响行数=1 才视为转移成功，避免并发转移导致归属被覆盖错乱。
     * 转移记录插入与归属更新包裹同一事务，保证二者原子一致。
     * @param int $customerId 客户 id
     * @param int $fromUserId 当前归属人 id
     * @param int $toUserId 目标归属人 id
     * @return bool 转移成功返回 true
     */
    public static function transfer(int $customerId, int $fromUserId, int $toUserId): bool
    {
        // 目标用户必须存在且启用（选人弹窗保证前端可选，后端兜底防无效 ID 致客户"丢失"）
        if ($toUserId <= 0 || $toUserId === $fromUserId) {
            return false;
        }
        $toUser = Db::name('user')->where('id', $toUserId)->where('status', 1)->find();
        if (empty($toUser)) {
            return false;
        }

        return Db::transaction(function () use ($customerId, $fromUserId, $toUserId, $toUser) {
            // 普通转移：原子匹配 owner_id=fromUserId 保证仅当前归属人可转出
            $affected = Db::name('customer')
                ->where('id', $customerId)
                ->where('owner_id', $fromUserId)
                ->update([
                    'owner_id'   => $toUserId,
                    'dept_id'    => (int)($toUser['dept_id'] ?? 0),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            if ($affected !== 1) {
                return false; // 客户不存在或已不属于预期归属
            }

            // 转移记录
            Db::name('customer_transfer_record')->insert([
                'customer_id'  => $customerId,
                'from_user_id' => $fromUserId,
                'to_user_id'   => $toUserId,
                'created_at'   => date('Y-m-d H:i:s'),
            ]);

            // 跟进记录（v2.51.4：来源显示实际用户名，替代「用户#id」占位）
            $fromName = Db::name('user')->where('id', $fromUserId)->value('name') ?: ('用户#' . $fromUserId);
            self::addActivity($customerId, $toUserId, 'TRANSFER', '从 ' . $fromName . ' 转入');

            return true;
        });
    }

    /**
     * 客户生命周期漏斗统计（M10，v2.38.3）。
     * 返回各阶段客户数：POTENTIAL/ACTIVE，以及总数。
     * @param int $companyId 可选，按本公司数据范围过滤（默认不过滤，统计全量非删除客户）
     * @return array
     */
    public static function lifecycleFunnel(int $companyId = 0): array
    {
        $stages = ['POTENTIAL' => 0, 'ACTIVE' => 0];
        $query = Db::name('customer')->where('is_deleted', 0);
        // 本公司主体客户（is_self=1）不计入销售漏斗
        $query->where('is_self', 0);
        $rows = $query->field('lifecycle_status, COUNT(*) AS cnt')
            ->group('lifecycle_status')->select()->toArray();
        $total = 0;
        foreach ($rows as $r) {
            $st = (string)$r['lifecycle_status'];
            $cnt = (int)$r['cnt'];
            if (isset($stages[$st])) {
                $stages[$st] = $cnt;
            }
            $total += $cnt;
        }

        // v2.40.0 P1-7：各阶段客户的销售合同金额合计（金额维度）
        // 口径：customer.party_b 关联销售合同（trade_attr=1, direction=sales, 未删除、已生效）金额 SUM
        $amounts = ['POTENTIAL' => 0.0, 'ACTIVE' => 0.0];
        $activeStatuses = [ContractLogic::STATUS_DRAFT, ContractLogic::STATUS_REJECTED, ContractLogic::STATUS_PENDING_APPROVAL];
        // 兼容旧版本函数调用；轻量合同管理下不会排除任何合同。
        $excludedIds = \exclude_framework_contracts_ids();
        $excludedSql = $excludedIds ? ' AND ct.id NOT IN (' . implode(',', $excludedIds) . ')' : '';
        $amtRows = Db::name('customer')->alias('c')
            ->leftJoin('contract ct', "ct.party_b_customer_id = c.id AND ct.is_deleted = 0 AND ct.trade_attr = 1 AND ct.direction = 'sales' AND ct.status NOT IN ('" . implode("','", $activeStatuses) . "')" . $excludedSql)
            ->where('c.is_deleted', 0)
            ->where('c.is_self', 0)
            ->field('c.lifecycle_status, COALESCE(SUM(ct.amount), 0) AS amt')
            ->group('c.lifecycle_status')
            ->select()->toArray();
        foreach ($amtRows as $r) {
            $st = (string)$r['lifecycle_status'];
            if (isset($amounts[$st])) {
                $amounts[$st] = round((float)$r['amt'], 2);
            }
        }

        return ['stages' => $stages, 'total' => $total, 'amounts' => $amounts];
    }

    /**
     * 合同关联客户 → 生命周期升 ACTIVE（M10，v2.38.3）。
     * 当一份合同关联某客户（乙方）时，该客户从客户(POTENTIAL)转为成交(ACTIVE)。
     * @param int $customerId
     * @return void
     */
    public static function promoteToActive(int $customerId): void
    {
        if ($customerId <= 0) {
            return;
        }
        Db::name('customer')->where('id', $customerId)
            ->where('is_self', 0)
            ->where('is_deleted', 0)
            ->update(['lifecycle_status' => 'ACTIVE', 'updated_at' => date('Y-m-d H:i:s')]);
    }

    /** 写入跟进记录 */
    public static function addActivity(int $customerId, int $userId, string $type, string $content, ?string $nextFollowAt = null): int
    {
        return Db::name('customer_activity')->insertGetId([
            'customer_id'    => $customerId,
            'user_id'        => $userId,
            'type'           => $type,
            'content'        => $content,
            'created_at'     => date('Y-m-d H:i:s'),
            'next_follow_at' => $nextFollowAt ?: null,
        ]);
    }

    /** 获取客户跟进记录 */
    public static function getActivities(int $customerId, int $limit = 20): array
    {
        return Db::name('customer_activity')->alias('a')
            ->leftJoin('user u', 'a.user_id = u.id')
            ->where('a.customer_id', $customerId)
            ->field('a.*, u.name as user_name')
            ->order('a.id', 'desc')
            ->limit($limit)
            ->select()
            ->toArray();
    }

    /**
     * 客户合并（v2.38.2）：将重复客户 target 的数据迁入 master，target 软删除。
     * 合并范围：合同引用、跟进记录、转移记录。
     * 归属：若 master 无归属人而 target 有归属人，则 master 继承 target 的归属；
     *       否则保持 master 归属不变。
     */
    public static function merge(int $masterId, int $targetId, int $actorId): array
    {
        $master = Db::name('customer')->where('id', $masterId)->where('is_deleted', 0)->find();
        $target = Db::name('customer')->where('id', $targetId)->where('is_deleted', 0)->find();
        if (!$master || !$target) return ['ok' => false, 'msg' => '客户不存在'];
        if ($masterId === $targetId) return ['ok' => false, 'msg' => '不能合并相同客户'];

        $merged = [];
        Db::transaction(function () use ($masterId, $targetId, $master, $target, $actorId, &$merged) {
            // 1) 合同引用迁到 master（乙方 + P1-6 甲方引用一并迁移，避免合并后甲方引用悬空）
            $contractMoved = Db::name('contract')
                ->where('party_b_customer_id', $targetId)->where('is_deleted', 0)
                ->update(['party_b_customer_id' => $masterId]);
            $contractAMoved = Db::name('contract')
                ->where('party_a_customer_id', $targetId)->where('is_deleted', 0)
                ->update(['party_a_customer_id' => $masterId]);
            $merged['contracts'] = $contractMoved + $contractAMoved;

            // 2) 跟进记录迁到 master
            $actMoved = Db::name('customer_activity')
                ->where('customer_id', $targetId)
                ->update(['customer_id' => $masterId]);
            $merged['activities'] = $actMoved;

            // 2.5) 联系人迁到 master（M9 修复：此前 merge 未迁移 customer_contact，合并后原联系人不可见）
            $contacts = Db::name('customer_contact')->where('customer_id', $targetId)->select()->toArray();
            if (!empty($contacts)) {
                $masterHasPrimary = (bool)Db::name('customer_contact')
                    ->where('customer_id', $masterId)->where('is_primary', 1)->find();
                foreach ($contacts as $c) {
                    // master 已有主联系人时，target 的主联系人降级为普通，避免双主
                    $isPrimary = ($c['is_primary'] && !$masterHasPrimary) ? 1 : 0;
                    Db::name('customer_contact')->where('id', $c['id'])->update([
                        'customer_id' => $masterId,
                        'is_primary'  => $isPrimary,
                    ]);
                }
                $merged['contacts'] = count($contacts);
            }

            // 3) 转移记录迁到 master
            Db::name('customer_transfer_record')->where('customer_id', $targetId)
                ->update(['customer_id' => $masterId]);

            // 4) 归属继承：如果 master 无归属人而 target 有归属人，上移至 target 的归属
            if (empty($master['owner_id']) && !empty($target['owner_id'])) {
                Db::name('customer')->where('id', $masterId)->update([
                    'owner_id' => $target['owner_id'],
                    'dept_id'  => $target['dept_id'] ?? 0,
                ]);
                $merged['owner_updated'] = true;
            }

            // 5) target 软删除，记录来源
            Db::name('customer')->where('id', $targetId)->update([
                'is_deleted' => 1,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            // 6) 审计记录
            self::addActivity($masterId, $actorId, 'NOTE',
                "合并客户 \"{$target['name']}\"(#" . $targetId . ")，迁移 {$contractMoved} 份合同、" . $actMoved . " 条跟进");
        });

        return ['ok' => true, 'merged' => $merged];
    }

    /**
     * 客户查重扫描（v2.38.2）：找出可能的重复客户对。
     * @return array 可能重复的客户对 [{a: {...}, b: {...}, reason: string}]
     */
    public static function findDuplicates(): array
    {
        $pairs = [];
        $customers = Db::name('customer')->where('is_deleted', 0)
            ->order('id')->select()->toArray();

        $normalized = []; // normalized_name => [customer_ids]
        foreach ($customers as $c) {
            $n = self::normalizeName($c['name']);
            if (mb_strlen($n) >= 4) {
                $normalized[$n][] = $c;
            }
        }

        foreach ($normalized as $name => $group) {
            if (count($group) >= 2) {
                for ($i = 0; $i < count($group); $i++) {
                    for ($j = $i + 1; $j < count($group); $j++) {
                        $pairs[] = [
                            'a' => $group[$i],
                            'b' => $group[$j],
                            'reason' => '名称归一化相同',
                        ];
                    }
                }
            }
        }

        // 信用代码相同也是强信号
        $byCredit = [];
        foreach ($customers as $c) {
            if (!empty($c['credit_code']) && mb_strlen($c['credit_code']) >= 15) {
                $byCredit[$c['credit_code']][] = $c;
            }
        }
        foreach ($byCredit as $code => $group) {
            if (count($group) >= 2) {
                for ($i = 0; $i < count($group); $i++) {
                    for ($j = $i + 1; $j < count($group); $j++) {
                        $alreadyExists = false;
                        foreach ($pairs as $p) {
                            if (($p['a']['id'] == $group[$i]['id'] && $p['b']['id'] == $group[$j]['id']) ||
                                ($p['a']['id'] == $group[$j]['id'] && $p['b']['id'] == $group[$i]['id'])) {
                                $alreadyExists = true; break;
                            }
                        }
                        if (!$alreadyExists) {
                            $pairs[] = [
                                'a' => $group[$i],
                                'b' => $group[$j],
                                'reason' => '信用代码相同：' . $code,
                            ];
                        }
                    }
                }
            }
        }

        return $pairs;
    }

    /** 取原始行（不含 is_deleted 过滤，供越权校验读取归属人/部门） */
    public static function findRaw(int $id): ?array
    {
        return Db::name('customer')->find($id) ?: null;
    }

    /** 查未删除客户原始行（P1-1：联系人模块等归属校验替代控制器 Db 直查，保留 is_deleted 过滤语义） */
    public static function findActive(int $id): ?array
    {
        return Db::name('customer')->where('id', $id)->where('is_deleted', 0)->find() ?: null;
    }

    /**
     * AJAX 客户搜索（供合同创建页选择甲方）
     * 按可见性谓词(本人 OR 所属部门集合)过滤，并追加共享客户（用户级/部门级白名单）。
     * 支持 DEPT_AND_CHILD / CUSTOM（覆盖旧版 SELF/DEPT 分支，修复新档位退化为全量的问题）。
     */
    public static function search(string $keyword, int $uid, int $deptId): array
    {
        $query = Db::name('customer')->where('is_deleted', 0);
        $conds = AuthLogic::scopeOrConditions('owner_id', 'dept_id');
        if (!empty($conds)) {
            // 追加共享客户（用户级/部门级白名单）
            $sharedIds = self::getSharedCustomerIds($uid, $deptId);
            $query->where(function ($qb) use ($conds, $sharedIds) {
                foreach ($conds as $c) {
                    $qb->whereOr($c[0], $c[1], $c[2]);
                }
                if (!empty($sharedIds)) {
                    $qb->whereOr('id', 'in', $sharedIds);
                }
            });
        }
        return $query->where('name|contact_name|contact_mobile', 'like', '%' . $keyword . '%')
            ->field('id, name, contact_name')
            ->limit(20)
            ->select()
            ->toArray();
    }

    /**
     * 相对方视图客户行（带数据范围）
     * 返回 id,name,contact_name,status,owner_id,dept_id，供控制器补充类型/标签后合并。
     * P2-8【M-A2】收敛重复逻辑：委托 PartyLogic::getPartyRows 共用骨架。
     * @param int $limit 安全上限（arch P1-3）
     */
    public static function getPartyRows(string $keyword, int $limit = 200): array
    {
        return PartyLogic::getPartyRows('customer', '', $keyword, $limit);
    }

    /**
     * 合同创建页客户下拉选项（is_deleted=0，按 id 倒序，限制条数）
     * 注：下拉选择辅助，保持原「看全部」可见性，不附加数据范围（避免缩小可选范围）。
     */
    public static function getOptionsForSelect(int $limit): array
    {
        return Db::name('customer')->where('is_deleted', 0)
            ->field('id, name')
            ->order('id', 'desc')
            ->limit($limit)
            ->select()
            ->toArray();
    }

    /**
     * 联合搜索客户（合同创建选择甲乙方用）
     * 无关键词时返回「本公司」客户(is_self=1)；有关键词时按关键词+数据范围搜索。
     * 返回字段形状与控制器原内联查询一致，便于控制器统一打标签后合并。
     * P2-8【M-A2】收敛重复逻辑：委托 PartyLogic::searchParty 共用骨架。
     */
    public static function searchParty(string $keyword): array
    {
        if ($keyword === '') {
            return PartyLogic::searchParty('customer', $keyword, [
                'selfDefault' => true, 'selfField' => 'is_self', 'selfLiteral' => '客户',
                'partyType'   => 'customer', 'creditCode' => true,
            ]);
        }
        return PartyLogic::searchParty('customer', $keyword, [
            'selfDefault'   => true, 'partyType' => 'customer',
            'searchFields'  => 'name|contact_name|contact_mobile',
            'typeNameField' => "''", 'creditCode' => true, 'orderBy' => 'is_self desc',
        ]);
    }

}
