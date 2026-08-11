<?php
// +----------------------------------------------------------------------
// | 项目业务逻辑 (P2-5 合同→项目关联)
// +----------------------------------------------------------------------

namespace app\common\logic;

use think\facade\Db;

class ProjectLogic
{
    /** 创建项目 */
    public static function create(array $data): int
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');
        return Db::name('project')->insertGetId($data);
    }

    /** 更新项目 */
    public static function update(int $id, array $data): bool
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        return Db::name('project')->where('id', $id)->update($data) !== false;
    }

    /** 软删除项目 */
    public static function softDelete(int $id): bool
    {
        return Db::name('project')->where('id', $id)
            ->update(['is_deleted' => 1, 'updated_at' => date('Y-m-d H:i:s')]) > 0;
    }

    /** 项目详情 */
    public static function getDetail(int $id): ?array
    {
        return Db::name('project')->where('id', $id)->where('is_deleted', 0)->find() ?: null;
    }

    /** 项目列表（走数据权限） */
    public static function getList(int $page, int $pageSize, array $filter = [], array $sort = ['id', 'desc']): array
    {
        $query = Db::name('project')->where('is_deleted', 0);
        AuthLogic::appendDataScope($query, 'owner_id', 'dept_id');

        if (!empty($filter['keyword'])) {
            $query->where('name|code|remark', 'like', '%' . $filter['keyword'] . '%');
        }
        if (isset($filter['status']) && $filter['status'] !== '') {
            $query->where('status', $filter['status']);
        }

        $total = $query->count();
        $list  = $query->order($sort[0], $sort[1])->page($page, $pageSize)->select()->toArray();

        // 附带每个项目的合同数（仅交易合同）
        $ids = array_column($list, 'id');
        $cntMap = [];
        if ($ids) {
            $rows = Db::name('contract')->where('is_deleted', 0)
                ->where('project_id', 'in', $ids)
                ->where('trade_attr', 1)
                ->field('project_id, COUNT(*) AS cnt')
                ->group('project_id')->select()->toArray();
            foreach ($rows as $r) $cntMap[$r['project_id']] = (int)$r['cnt'];
        }
        foreach ($list as &$row) {
            $row['contract_count'] = $cntMap[$row['id']] ?? 0;
        }
        unset($row);

        return ['list' => $list, 'total' => $total];
    }

    /**
     * 项目经营聚合（口径沿用 v2.18：仅交易合同 trade_attr=1、direction in sales/purchase）
     * @return array{contract_count:int,sales_amount:float,purchase_amount:float,gross_margin:float,gross_margin_rate:float,receivable:float,received:float,recovery_rate:float}
     */
    public static function aggregate(int $projectId): array
    {
        // 交易合同额（销售/采购分开；P1-3：排除未生效状态 + 排除框架合同预算上限，避免草稿/驳回/审批中/框架金额计入经营与毛利）
        // v2.44.1 P1：项目经营聚合补数据范围——此前仅按 project_id 过滤，跨部门合同金额会泄入本项目聚合
        $dirQuery = Db::name('contract')->alias('c')->where('c.is_deleted', 0)
            ->where('c.project_id', $projectId)
            ->where('c.trade_attr', 1)
            ->where('c.direction', 'in', ['sales', 'purchase'])
            ->where('c.status', 'not in', [ContractLogic::STATUS_DRAFT, ContractLogic::STATUS_REJECTED, ContractLogic::STATUS_PENDING_APPROVAL])
            ->whereNotIn('c.id', \exclude_framework_contracts_ids())
            ->field('c.direction, SUM(c.amount) AS total, COUNT(*) AS cnt')
            ->group('c.direction');
        AuthLogic::appendDataScope($dirQuery, 'c.owner_id', 'c.dept_id');
        $dirRows = $dirQuery->select()->toArray();
        $salesAmount = 0.0; $purchaseAmount = 0.0; $contractCount = 0;
        foreach ($dirRows as $r) {
            $contractCount += (int)$r['cnt'];
            if ($r['direction'] === 'purchase') $purchaseAmount += (float)$r['total'];
            else $salesAmount += (float)$r['total'];
        }

        // 应收 / 已收（回款口径同驾驶舱：payment_record RECEIVABLE / PAID）
        // P1-3：回款口径与上方 dirQuery 合同额侧对齐——排除未生效合同关联的回款 + 排除框架合同
        $payBase = function () use ($projectId) {
            $q = Db::name('payment_record')->alias('p')
                ->join('contract c', 'p.contract_id = c.id')
                ->where('c.is_deleted', 0)
                ->where('c.trade_attr', 1)
                ->where('c.project_id', $projectId)
                ->where('c.status', 'not in', [ContractLogic::STATUS_DRAFT, ContractLogic::STATUS_REJECTED, ContractLogic::STATUS_PENDING_APPROVAL])
                ->whereNotIn('c.id', \exclude_framework_contracts_ids())
                ->where('p.payment_type', 'RECEIVABLE');
            // v2.44.1 P1：回款聚合同样限定合同数据范围
            AuthLogic::appendDataScope($q, 'c.owner_id', 'c.dept_id');
            return $q;
        };
        $receivable = $payBase()->sum('p.amount') ?? 0;
        // P0-1：已收口径统一用 paid_amount（实际确认金额）
        $received   = $payBase()->where('p.status', 'PAID')->sum('p.paid_amount') ?? 0;
        $recoveryRate = $receivable > 0 ? round($received / $receivable * 100, 1) : 0;

        // P0-1：项目毛利 = 销售合同额 − 采购合同额；毛利率按销售口径计算
        $grossMargin     = (float)$salesAmount - (float)$purchaseAmount;
        $grossMarginRate = $salesAmount > 0 ? round($grossMargin / $salesAmount * 100, 1) : 0;

        return [
            'contract_count'     => $contractCount,
            'sales_amount'       => (float)$salesAmount,
            'purchase_amount'    => (float)$purchaseAmount,
            'gross_margin'       => (float)$grossMargin,
            'gross_margin_rate'  => (float)$grossMarginRate,
            'receivable'         => (float)$receivable,
            'received'           => (float)$received,
            'recovery_rate'      => (float)$recoveryRate,
        ];
    }

    /** 项目下的合同列表
     * @param int $limit 最大返回条数；>0 时生效（P2-3【M-Pf5】避免大项目一次性全量加载到内存），默认 200
     * v2.44.1 P1：补数据范围——项目下合同查询此前仅按 project_id 过滤，
     * 跨部门用户可看到该项目下其他部门合同（getContracts 主查询）
     */
    public static function getContracts(int $projectId, int $limit = 200): array
    {
        $query = Db::name('contract')->alias('c')->where('c.is_deleted', 0)
            ->where('c.project_id', $projectId)
            ->field('c.id,c.contract_no,c.title,c.direction,c.trade_attr,c.amount,c.status,c.created_at')
            ->order('c.id', 'desc');
        // v2.44.1 P1：数据范围收敛（带 c. 别名）
        AuthLogic::appendDataScope($query, 'c.owner_id', 'c.dept_id');
        // P2-3【M-Pf5】大项目绑定上限，防止全量物化
        if ($limit > 0) {
            $query->limit($limit);
        }
        return $query->select()->toArray();
    }

    /** 项目下合同总数（P2-3【M-Pf5】配合 getContracts 的 LIMIT，供视图展示"共 N 条"与"查看全部"） */
    public static function getContractsCount(int $projectId): int
    {
        $query = Db::name('contract')->alias('c')->where('c.is_deleted', 0)
            ->where('c.project_id', $projectId);
        // v2.44.1 P1：数据范围收敛（带 c. 别名）
        AuthLogic::appendDataScope($query, 'c.owner_id', 'c.dept_id');
        return (int)$query->count();
    }

    /** 下拉选项（供合同创建页关联项目用） */
    public static function options(): array
    {
        $query = Db::name('project')->where('is_deleted', 0)->where('status', 'not in', ['ARCHIVED', 'TERMINATED']);
        AuthLogic::appendDataScope($query, 'owner_id', 'dept_id');
        return $query->field('id,name,code')->order('id', 'desc')->select()->toArray();
    }

    /**
     * 项目搜索（合同创建页「关联项目」搜索选择器，2026-08-05）。
     * - 仅未归档项目，按数据权限收敛；
     * - 返回标记 my=1 的「与我有关」（owner_id=当前用户）项目排最前，供前端推荐展示。
     * @param string $keyword 空串时仅返回推荐项（供「点开即推荐」场景）
     * @param int    $limit   安全上限
     */
    public static function search(string $keyword, int $limit = 20): array
    {
        $uid = (int)\think\facade\Session::get('user_id', 0);
        $query = Db::name('project')->where('is_deleted', 0)->where('status', 'not in', ['ARCHIVED', 'TERMINATED']);
        AuthLogic::appendDataScope($query, 'owner_id', 'dept_id');
        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->whereLike('name', '%' . $keyword . '%')->whereOr('code', 'like', '%' . $keyword . '%');
            });
        }
        $list = $query->field('id,name,code,owner_id')->order('id', 'desc')->limit($limit)->select()->toArray();
        // 「与我有关」置顶：owner_id=当前用户标记 my=1 并排最前，作为推荐备选
        usort($list, function ($a, $b) use ($uid) {
            $am = ((int)$a['owner_id'] === $uid) ? 0 : 1;
            $bm = ((int)$b['owner_id'] === $uid) ? 0 : 1;
            return $am <=> $bm;
        });
        foreach ($list as &$p) {
            $p['my'] = ((int)($p['owner_id'] ?? 0) === $uid) ? 1 : 0;
        }
        unset($p);
        return $list;
    }

    /** 取原始行（不含 is_deleted 过滤，供越权校验读取归属人/部门） */
    public static function findRaw(int $id): ?array
    {
        return Db::name('project')->find($id) ?: null;
    }

    /**
     * 验收联动：项目下销售交易合同（执行中/已通过/历史已签）批量置已完成（P1-1：从控制器下沉）
     * v2.44.1 P1：写操作限定数据范围——仅联动当前用户有权访问的合同，
     * 防止把项目中其他部门的销售合同批量置 COMPLETED（跨范围写）。
     * @return int 受影响行数
     */
    public static function completeSalesContracts(int $projectId): int
    {
        $query = Db::name('contract')->alias('c')
            ->where('c.project_id', $projectId)
            ->where('c.is_deleted', 0)
            ->where('c.trade_attr', 1)
            ->where('c.direction', 'sales')
            ->where('c.status', 'in', ['EXECUTING', 'APPROVED', 'SIGNED']);
        AuthLogic::appendDataScope($query, 'c.owner_id', 'c.dept_id');
        return $query->update(['status' => 'COMPLETED', 'updated_at' => date('Y-m-d H:i:s')]);
    }

    /**
     * 待收尾款统计：项目关联合同下的 PENDING 尾款（FINAL_PAYMENT 或说明含「尾款」）合计（P1-1：从控制器下沉）
     * v2.44.1 P1：统计同样限定合同数据范围
     */
    public static function sumPendingTailAmount(int $projectId): float
    {
        $q = Db::name('contract')->alias('c')->where('c.project_id', $projectId)->where('c.is_deleted', 0);
        AuthLogic::appendDataScope($q, 'c.owner_id', 'c.dept_id');
        $cids = $q->column('c.id');
        if (!$cids) {
            return 0.0;
        }
        return (float)(Db::name('payment_record')
            ->where('contract_id', 'in', $cids)
            ->where('payment_type', 'RECEIVABLE')
            ->where('status', 'PENDING')
            ->where(function ($q) {
                $q->where('milestone', 'FINAL_PAYMENT')->whereOr('description', 'like', '%尾款%');
            })
            ->sum('amount') ?: 0);
    }

    /**
     * 删除项目时解除关联合同的项目绑定（project_id 置 0，避免悬空引用）
     * 与软删除包裹在同一调用处的业务语义内，由控制器编排事务/审计。
     */
    public static function unlinkContracts(int $projectId): void
    {
        Db::name('contract')->where('project_id', $projectId)->update([
            'project_id'  => 0,
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * 驾驶舱：按项目 TOP N 应收/已收（仅交易合同）
     */
    public static function topProjects(int $limit = 5): array
    {
        // 取有交易合同的项目
        $query = Db::name('payment_record')->alias('p')
            ->join('contract c', 'p.contract_id = c.id')
            ->join('project pr', 'c.project_id = pr.id')
            ->where('c.is_deleted', 0)
            ->where('pr.is_deleted', 0)
            ->where('c.trade_attr', 1)
            ->where('p.payment_type', 'RECEIVABLE');
        // 数据权限收敛（与驾驶舱其余口径一致：非管理员按 c.owner_id/c.dept_id）
        AuthLogic::appendDataScope($query, 'c.owner_id', 'c.dept_id');
        $rows = $query
            // P2-9【M-P3】已收口径统一用 paid_amount（实际确认金额），与 aggregate()/报表/往来方一致
            ->field("pr.id, pr.name, SUM(p.amount) AS receivable, SUM(CASE WHEN p.status='PAID' THEN p.paid_amount ELSE 0 END) AS received")
            ->group('pr.id')
            ->order('receivable', 'desc')
            ->limit($limit)->select()->toArray();
        foreach ($rows as &$r) {
            $r['receivable'] = (float)$r['receivable'];
            $r['received']   = (float)$r['received'];
            $r['recovery_rate'] = $r['receivable'] > 0 ? round($r['received'] / $r['receivable'] * 100, 1) : 0;
        }
        unset($r);
        return $rows;
    }
}
