<?php
// +----------------------------------------------------------------------
// | 供应商业务逻辑（Phase 1 移动端重构：从 MobileController 提取 Db 直查）
// +----------------------------------------------------------------------

namespace app\common\logic;

use think\facade\Db;

class SupplierLogic
{
    /** 创建供应商 */
    public static function create(array $data): int
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');
        return Db::name('supplier')->insertGetId($data);
    }

    /** 更新供应商 */
    public static function update(int $id, array $data): bool
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        return Db::name('supplier')->where('id', $id)->update($data) !== false;
    }

    /** 软删除供应商 */
    public static function softDelete(int $id): bool
    {
        return Db::name('supplier')->where('id', $id)
            ->update(['is_deleted' => 1, 'updated_at' => date('Y-m-d H:i:s')]) > 0;
    }

    /** 供应商详情（含权限校验由调用方处理） */
    public static function getDetail(int $id): ?array
    {
        return Db::name('supplier')->where('id', $id)->where('is_deleted', 0)->find() ?: null;
    }

    /**
     * P0-3【严重·数据完整性】删除关联校验（参照 CustomerLogic::deleteBlockers）
     * 删除供应商前校验是否仍被采购类合同关联，避免产生悬空引用（孤儿数据）。
     * @param int $id 供应商 ID
     * @return array 阻塞原因列表（非空则禁止删除，元素为可读提示）
     */
    public static function deleteBlockers(int $id): array
    {
        return self::deleteBlockersMap([$id])[$id] ?? [];
    }

    /**
     * 批量删除阻塞项映射（P2-16【M-A2】回收站列表 N+1 消除）：与 deleteBlockers 同语义同文案，
     * 单次 whereIn 聚合关联采购合同，返回 [supplierId => [阻塞提示]]；无阻塞的 id 不出现。
     */
    public static function deleteBlockersMap(array $ids): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        $map = [];
        if (!$ids) return $map;

        // 关联采购合同：supplier_id（乙方供应商）或 party_a_supplier_id（甲方供应商，v2.46.0）指向本供应商且未软删
        $rows = Db::name('contract')->where(function ($query) use ($ids) {
            $query->whereIn('supplier_id', $ids)->whereOr('party_a_supplier_id', 'in', $ids);
        })->where('is_deleted', 0)
            ->field('supplier_id, party_a_supplier_id, contract_no')->select()->toArray();
        $nosMap = [];
        foreach ($rows as $r) {
            $sid = (int)$r['supplier_id'] ?: (int)$r['party_a_supplier_id'];
            $nosMap[$sid][] = $r['contract_no'];
        }
        foreach ($nosMap as $sid => $nos) {
            $map[$sid][] = '存在关联采购合同（' . count($nos) . ' 笔）：' . implode('、', $nos);
        }
        return $map;
    }

    /**
     * 供应商列表（带数据权限 + 关键词/类型过滤 + 分页）
     * @param int $page 页码
     * @param int $pageSize 每页条数
     * @param array $filter 筛选条件：keyword, type
     * @param array $sort 排序 [字段, 方向]，默认 ['id', 'desc']
     * @return array ['list' => [], 'total' => 0]
     */
    public static function getList(int $page, int $pageSize, array $filter = [], array $sort = ['id', 'desc']): array
    {
        $query = Db::name('supplier')->where('is_deleted', 0);
        AuthLogic::appendDataScope($query, 'owner_id', 'dept_id');

        if (!empty($filter['keyword'])) {
            $query->where('name|contact_name|contact_mobile', 'like', '%' . $filter['keyword'] . '%');
        }
        if (!empty($filter['type'])) {
            $query->where('type', $filter['type']);
        }

        $total = $query->count();
        $list  = $query->order($sort[0], $sort[1])->page($page, $pageSize)->select()->toArray();

        return ['list' => $list, 'total' => $total];
    }

    /** 供应商总数（带数据权限——与桌面 Dashboard 口径一致） */
    public static function count(): int
    {
        $q = Db::name('supplier')->where('is_deleted', 0);
        AuthLogic::appendDataScope($q, 'owner_id', 'dept_id');
        return $q->count();
    }

    /** 取原始行（不含 is_deleted 过滤，供越权校验读取归属人/部门） */
    public static function findRaw(int $id): ?array
    {
        return Db::name('supplier')->find($id) ?: null;
    }

    /**
     * 供应商列表页查询（带数据范围 + 关键词/类型过滤 + 分页/首屏上限）
     * 与原供应商列表 action 内联查询 100% 等价：
     * AJAX 走分页(count+page)返回 ['list','total']；非 AJAX 走 limit(200) 返回 ['list']。
     */
    public static function getIndexList(array $params): array
    {
        $keyword   = $params['keyword']   ?? '';
        $type      = $params['type']      ?? '';
        $sortField = $params['sortField'] ?? 'id';
        $sortOrder = $params['sortOrder'] ?? 'desc';
        $isAjax    = !empty($params['isAjax']);
        $page      = (int)($params['page']      ?? 1);
        $pageSize  = (int)($params['pageSize']  ?? 20);

        $query = Db::name('supplier')->where('is_deleted', 0);
        AuthLogic::appendDataScope($query, 'owner_id', 'dept_id');
        if ($keyword) {
            $query->where('name|contact_name|contact_mobile', 'like', '%' . $keyword . '%');
        }
        if ($type) {
            $query->where('type', $type);
        }

        if ($isAjax) {
            $total = $query->count();
            $list  = $query->order($sortField, $sortOrder)->page($page, $pageSize)->select()->toArray();
            return ['list' => $list, 'total' => $total];
        }
        // 非 AJAX 首屏列表加安全上限，避免全表加载（真实分页由 AJAX 数据表承接）
        $list = $query->order($sortField, $sortOrder)->limit(200)->select()->toArray();
        return ['list' => $list];
    }

    /**
     * AJAX 供应商搜索（供合同创建页选择乙方）
     * 统一走可见性谓词：ALL 全部；否则按(本人 OR 所属部门集合)过滤；
     * 支持 DEPT_AND_CHILD / CUSTOM（覆盖旧版 SELF/DEPT 分支，修复新档位退化为全量的问题）。
     */
    public static function search(string $keyword, int $uid, int $deptId): array
    {
        $query = Db::name('supplier')->where('is_deleted', 0);
        $conds = AuthLogic::scopeOrConditions('owner_id', 'dept_id');
        if (!empty($conds)) {
            $query->where(function ($qb) use ($conds) {
                foreach ($conds as $c) {
                    $qb->whereOr($c[0], $c[1], $c[2]);
                }
            });
        }
        return $query->where('name|contact_name|contact_mobile', 'like', '%' . $keyword . '%')
            ->field('id, name, type')
            ->limit(20)
            ->select()
            ->toArray();
    }

    /**
     * 相对方视图供应商行（带数据范围）
     * 返回 id,name,contact_name,type,status,owner_id,dept_id，供控制器补充类型/标签后合并。
     * P2-8【M-A2】收敛重复逻辑：委托 PartyLogic::getPartyRows 共用骨架。
     * @param int $limit 安全上限（arch P1-3）
     */
    public static function getPartyRows(string $keyword, int $limit = 200): array
    {
        return PartyLogic::getPartyRows('supplier', 'type', $keyword, $limit);
    }

    /**
     * 联合搜索供应商（合同创建选择甲乙方用）
     * 返回字段形状与控制器原内联查询一致，便于控制器统一打标签后合并。
     * P2-8【M-A2】收敛重复逻辑：委托 PartyLogic::searchParty 共用骨架（无关键词返回空）。
     */
    public static function searchParty(string $keyword): array
    {
        return PartyLogic::searchParty('supplier', $keyword, [
            'selfDefault'   => false, 'partyType' => 'supplier',
            'searchFields'  => 'name|contact_name',
            'typeNameField' => 'type', 'creditCode' => false,
        ]);
    }
}
