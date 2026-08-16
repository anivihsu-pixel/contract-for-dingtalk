<?php
// +----------------------------------------------------------------------
// | 合同业务逻辑 — 状态机 + CRUD
// +----------------------------------------------------------------------

namespace app\common\logic;

use think\facade\Db;
use app\common\service\AuditService;

class ContractLogic
{
    /** 合同状态枚举（REV-36：状态字符串收敛为常量，避免散落字面量导致状态机与标签不一致） */
    const STATUS_DRAFT            = 'DRAFT';
    const STATUS_PENDING_APPROVAL = 'PENDING_APPROVAL';
    const STATUS_REJECTED         = 'REJECTED';
    const STATUS_EXECUTING        = 'EXECUTING';
    const STATUS_COMPLETED        = 'COMPLETED';
    const STATUS_TERMINATED       = 'TERMINATED';
    const STATUS_EXPIRED          = 'EXPIRED';
    const STATUS_ARCHIVED         = 'ARCHIVED';

    /** 状态 -> 中文标签（与全局 contract_status_map() 共用同一口径，单一事实来源） */
    const STATUS_LABELS = [
        self::STATUS_DRAFT            => '草稿',
        self::STATUS_PENDING_APPROVAL => '待审批',
        self::STATUS_REJECTED         => '已驳回',
        self::STATUS_EXECUTING        => '执行中',
        self::STATUS_COMPLETED        => '已完成',
        self::STATUS_TERMINATED       => '已终止',
        self::STATUS_EXPIRED          => '已到期',
        self::STATUS_ARCHIVED         => '已归档',
    ];

    /** 合同状态流转规则（REV-36：状态键/值统一引用 STATUS_* 常量，杜绝字面量漂移） */
    const TRANSITIONS = [
        self::STATUS_DRAFT            => [self::STATUS_PENDING_APPROVAL],
        // P1-5（M27）：放开「审批中 → 草稿」跃迁，使撤回(recall)功能可用（审批代理从 PENDING_APPROVAL 回退到 DRAFT）
        self::STATUS_PENDING_APPROVAL => [self::STATUS_EXECUTING, self::STATUS_REJECTED, self::STATUS_DRAFT],
        // P0-1【严重·C1】放开「驳回(REJECTED) → 待审批(PENDING_APPROVAL)」跃迁，支持驳回后修改重提审批；
        // 此前仅允许 REJECTED→DRAFT/ARCHIVED，导致 submit() 调 transitionStatus(PENDING_APPROVAL) 静默失败、合同状态卡死在已驳回。
        self::STATUS_REJECTED         => [self::STATUS_DRAFT, self::STATUS_ARCHIVED, self::STATUS_PENDING_APPROVAL],
        self::STATUS_EXECUTING        => [self::STATUS_COMPLETED, self::STATUS_EXPIRED, self::STATUS_TERMINATED, self::STATUS_ARCHIVED],
        // M35：已完成/已到期/已终止 允许反向回到执行中，支持误操作撤销
        self::STATUS_COMPLETED        => [self::STATUS_ARCHIVED, self::STATUS_EXECUTING],
        self::STATUS_EXPIRED          => [self::STATUS_ARCHIVED, self::STATUS_EXECUTING],
        self::STATUS_TERMINATED       => [self::STATUS_ARCHIVED, self::STATUS_EXECUTING],
        // CR-07：归档可逆——取消归档回退至 EXECUTING（执行中），便于纠正误归档
        self::STATUS_ARCHIVED         => [self::STATUS_EXECUTING],
    ];

    /** 检查状态变更是否合法 */
    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? []);
    }

    /** 创建合同 */
    public static function create(array $data): int
    {
        // P2-3（M5）：trade_attr 默认值兜底——未显式传入时按【非交易(0)】处理，
        // 杜绝「漏传即被静默计入收支」的统计口径漂移。前端创建页已通过隐藏域显式提交 trade_attr，
        // 故改为 0 不影响正常交易合同保存，仅兜底防御后端直调/接口漏传。
        if (!isset($data['trade_attr']) || $data['trade_attr'] === '') {
            $data['trade_attr'] = 0;
        }
        $data['contract_no'] = generate_contract_no();
        $data['status']      = self::STATUS_DRAFT;
        $data['created_at']  = date('Y-m-d H:i:s');
        $data['updated_at']  = date('Y-m-d H:i:s');

        return Db::name('contract')->insertGetId($data);
    }

    /** 更新合同 */
    public static function update(int $id, array $data): bool
    {
        $contract = Db::name('contract')->find($id);
        if (!$contract) return false;

        $data['updated_at'] = date('Y-m-d H:i:s');
        return Db::name('contract')->where('id', $id)->update($data) !== false;
    }

    /**
     * 状态变更（P1-5/M27：状态机校验后更新合同状态）
     * @param int $id 合同 id
     * @param string $newStatus 目标状态
     * @param int $operatorId 操作人 id
     * @return bool 是否成功（状态机不允许的跃迁返回 false）
     */
    public static function transitionStatus(int $id, string $newStatus, int $operatorId): bool
    {
        $contract = Db::name('contract')->find($id);
        if (!$contract) return false;

        if (!self::canTransition($contract['status'], $newStatus)) {
            return false;
        }

        return Db::name('contract')->where('id', $id)
            ->update(['status' => $newStatus, 'updated_at' => date('Y-m-d H:i:s')]) !== false;
    }

    /**
     * CR-08：将「执行中(EXECUTING)」且已过到期日的合同自动流转为「已到期(EXPIRED)」
     * 可通过 system_config 的 contract_auto_expire=0 关闭（默认开启）
     * 每个流转都会写审计留痕，返回处理的合同数量
     */
    public static function autoExpire(): int
    {
        $switch = Db::name('system_config')->where('config_key', 'contract_auto_expire')->value('config_value');
        if ($switch === '0') {
            return 0; // 开关关闭
        }
        $today = date('Y-m-d');
        $ids = Db::name('contract')
            ->where('status', 'EXECUTING')
            ->where('is_deleted', 0)
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<>', '')
            ->where('expiry_date', '<', $today)
            ->column('id');

        // P1-5（M28）：整个过期流转循环包外层事务，要么全部 EXECUTING→EXPIRED 成功落库，
        // 要么整体回滚，避免批量过期中途失败导致部分合同状态变更、部分未变更的脏数据。
        return Db::transaction(function () use ($ids) {
            $count = 0;
            foreach ($ids as $id) {
                // 状态机保证 EXECUTING => EXPIRED 合法
                if (self::transitionStatus($id, 'EXPIRED', 0)) {
                    $count++;
                    AuditService::log(0, 'auto_expire', 'contract', $id); // operatorId=0 代表系统自动
                }
            }
            return $count;
        });
    }

    /**
     * 按数据范围取单条合同（越权防护）
     * @return array|null 无权或无此合同时返回 null
     */
    public static function accessible(int $id): ?array
    {
        $q = Db::name('contract')->where('id', $id)->where('is_deleted', 0);
        AuthLogic::appendDataScope($q, 'owner_id', 'dept_id');
        $contract = $q->find();
        if ($contract) return $contract;
        // 执行抄送人获得该合同的只读查看权，避免通知深链落到 404。
        $userId = (int)\think\facade\Session::get('user_id', 0);
        if ($userId > 0 && Db::name('contract_execution_cc')->where('contract_id', $id)->where('user_id', $userId)->find()) {
            return Db::name('contract')->where('id', $id)->where('is_deleted', 0)->find() ?: null;
        }
        return null;
    }

    /** 获取合同详情 */
    public static function getDetail(int $id): ?array
    {
        return Db::name('contract')->alias('c')
            ->leftJoin('customer cu', 'c.party_b_customer_id = cu.id')
            ->leftJoin('user u', 'c.creator_id = u.id')
            ->leftJoin('project pr', 'c.project_id = pr.id')
            ->field('c.*, cu.name as customer_name, u.name as creator_name, pr.name as project_name')
            ->where('c.id', $id)
            ->where('c.is_deleted', 0)
            ->find() ?: null;
    }

    /**
     * 草稿待处理列表（v2.40.0：PC 仪表盘「草稿待处理」卡片 + 列表页快捷筛选共用）
     * 走数据权限（AuthLogic::appendDataScope），仅草稿状态、未删除，按更新时间倒序。
     * @param array $user  当前用户
     * @param int   $limit 返回条数
     * @return array ['list' => 草稿数组, 'total' => 草稿总数]
     */
    public static function draftList(array $user, int $limit = 5): array
    {
        $query = Db::name('contract')->alias('c')
            ->leftJoin('user u', 'c.owner_id = u.id')
            ->field('c.id, c.contract_no, c.title, c.amount, c.created_at, c.updated_at, u.name as owner_name')
            ->where('c.is_deleted', 0)
            ->where('c.status', self::STATUS_DRAFT);
        AuthLogic::appendDataScope($query, 'c.owner_id', 'c.dept_id');
        $total = (clone $query)->count();
        $list  = $query->order('c.updated_at', 'desc')->limit($limit)->select()->toArray();
        foreach ($list as &$r) {
            $r['amount'] = (float)($r['amount'] ?? 0);
        }
        unset($r);
        return ['list' => $list, 'total' => $total];
    }

    /** 合同列表 */
    public static function getList(int $page, int $pageSize, array $filter = [], array $sort = ['c.id', 'desc']): array
    {
        $query = Db::name('contract')->alias('c')
            ->leftJoin('user u', 'c.owner_id = u.id')
            ->leftJoin('project pr', 'c.project_id = pr.id')
            ->field('c.*, u.name as owner_name, pr.name as project_name')
            ->where('c.is_deleted', 0);

        // 数据范围
        AuthLogic::appendDataScope($query, 'c.owner_id', 'c.dept_id');

        // P3b-9：筛选条件集中到 applyListFilters，主方法聚焦编排（count/select/聚合）
        self::applyListFilters($query, $filter);

        $total = $query->count();
        // v2.44.4：草稿置顶排序——sort[0]='draft_first' 时草稿优先、其余按 id 倒序（PC/移动端列表默认视图；显式列排序仍走 order）
        if (($sort[0] ?? '') === 'draft_first') {
            $list = $query->orderRaw("CASE WHEN c.status='" . self::STATUS_DRAFT . "' THEN 0 ELSE 1 END, c.id DESC")
                ->page($page, $pageSize)
                ->select()->toArray();
        } else {
            $list = $query->order($sort[0], $sort[1])
                ->page($page, $pageSize)
                ->select()->toArray();
        }

        return ['list' => $list, 'total' => $total];
    }

    /**
     * 应用合同列表筛选条件到查询构造器（P3b-9 从 getList 拆分）
     * 涵盖：关键字全文检索 + 10 态状态 + 类别 + 方向 + 交易属性 + 项目/客户关联 +
     *       日期区间 + 金额区间 + 相对方/签约主体/归属人。
     * 注意：所有条件均为 AND，关键字用闭包 whereOr 分组以避免与外层 where 优先级歧义。
     *
     * @param mixed $query  ThinkPHP Query 对象（引用传递，无需返回）
     * @param array $filter 前端提交的筛选数组
     */
    private static function applyListFilters($query, array $filter): void
    {
        if (!empty($filter['keyword'])) {
            // P1-2（deep review）：全文检索 — 在标题/合同号/关键词之外，追加 content_plain（概要纯文本）LIKE 匹配；
            // 用 where 闭包 + whereOr 显式分组，避免与后续独立 where 条件产生隐式 AND/OR 优先级歧义。
            $kw = '%' . $filter['keyword'] . '%';
            $query->where(function ($q) use ($kw) {
                $q->where('c.title', 'like', $kw)
                    ->whereOr('c.contract_no', 'like', $kw)
                    ->whereOr('c.keywords', 'like', $kw)
                    ->whereOr('c.content_plain', 'like', $kw);
            });
        }
        if (!empty($filter['status'])) {
            $query->where('c.status', $filter['status']);
        }
        if (!empty($filter['business_type'])) {
            $query->where('c.business_type', $filter['business_type']);
        }
        if (!empty($filter['direction'])) {
            $query->where('c.direction', $filter['direction']);
        }
        // 非交易合同筛选（列表"非交易"视图）：提交 trade_attr=0 时精确过滤
        if (isset($filter['trade_attr'])) {
            $query->where('c.trade_attr', (int)$filter['trade_attr']);
        }
        // 按项目筛选（P2-5）
        if (isset($filter['project_id']) && $filter['project_id'] !== '') {
            $query->where('c.project_id', (int)$filter['project_id']);
        }
        // 按客户（乙方）筛选（N-m4：客户详情「查看全部关联合同」入口）
        if (isset($filter['customer_id']) && $filter['customer_id'] !== '') {
            $query->where('c.party_b_customer_id', (int)$filter['customer_id']);
        }
        // 按部门筛选（v2.51.3：移动端部门经营详情页「查看全部合同」入口）
        if (isset($filter['dept_id']) && $filter['dept_id'] !== '') {
            $query->where('c.dept_id', (int)$filter['dept_id']);
        }
        if (!empty($filter['date_start'])) {
            $query->where('c.created_at', '>=', $filter['date_start']);
        }
        if (!empty($filter['date_end'])) {
            $query->where('c.created_at', '<=', $filter['date_end'] . ' 23:59:59');
        }
        // REV-29：高级筛选 — 金额区间
        if (isset($filter['amount_min']) && $filter['amount_min'] !== '') {
            $query->where('c.amount', '>=', (float)$filter['amount_min']);
        }
        if (isset($filter['amount_max']) && $filter['amount_max'] !== '') {
            $query->where('c.amount', '<=', (float)$filter['amount_max']);
        }
        // REV-29：高级筛选 — 相对方名称（模糊匹配甲方或乙方）
        if (!empty($filter['party_name'])) {
            $query->where('c.party_a_name|c.party_b_name', 'like', '%' . $filter['party_name'] . '%');
        }
        // REV-29：高级筛选 — 本公司签约主体
        if (isset($filter['our_company_id']) && $filter['our_company_id'] !== '') {
            $query->where('c.our_company_id', (int)$filter['our_company_id']);
        }
        // REV-29：高级筛选 — 合同归属人
        if (isset($filter['owner_id']) && $filter['owner_id'] !== '') {
            $query->where('c.owner_id', (int)$filter['owner_id']);
        }
    }

    /**
     * 软删除合同（P2-5/M7：统一删除入口的可删状态集合）
     * 允许无业务活性状态软删除：DRAFT（草稿）/ REJECTED（已驳回）/ ARCHIVED（已归档）/
     * COMPLETED（已完成）/ EXPIRED（已到期）/ TERMINATED（已终止），
     * 与控制器 delete()/batchDelete() 的「可删状态」承诺保持一致，避免接口层放行、底层却硬校验
     * DRAFT 导致其他状态无法删除的矛盾。归档/已完成/已到期/已终止放开删除是测试数据清理出口
     * （2026-08-10，用户要求测试数据可移除）；有回款/发票/审批/子合同关联的仍会被 deleteBlockers 拦截。
     */
    public static function softDelete(int $id): bool
    {
        return Db::name('contract')->where('id', $id)
            ->whereIn('status', ['DRAFT', 'REJECTED', 'ARCHIVED', 'COMPLETED', 'EXPIRED', 'TERMINATED'])
            ->update(['is_deleted' => 1, 'updated_at' => date('Y-m-d H:i:s')]) > 0;
    }

    /**
     * 超管强制删除的审批终结（2026-08-11）：将指定合同的 PENDING 审批实例置 RECALLED 终态，
     * 并将合同状态回退草稿（DRAFT），解除 deleteBlockers 的「进行中的审批流程」拦截后即可走 softDelete。
     * 用于审批人/提交人已失效（被禁用/删除）导致无法撤回/审批的僵尸审批中合同清理（测试数据清理出口）。
     * 审批轨迹保留（实例终态 RECALLED），待办列表按 instance.status=PENDING 过滤会自动消失，不产生悬空待办。
     * @return bool 是否有 PENDING 审批实例被终结（无则返回 false）
     */
    public static function forceTerminateApproval(int $contractId): bool
    {
        $instances = Db::name('approval_instance')
            ->where('contract_id', $contractId)
            ->where('status', 'PENDING')
            ->column('id');
        if (!$instances) {
            return false;
        }
        $now = date('Y-m-d H:i:s');
        Db::name('approval_instance')->whereIn('id', $instances)->update([
            'status'      => 'RECALLED',
            'finished_at' => $now,
        ]);
        Db::name('contract')->where('id', $contractId)->where('status', 'PENDING_APPROVAL')->update([
            'status'     => 'DRAFT',
            'updated_at' => $now,
        ]);
        return true;
    }

    /**
     * 删除前关联校验（CR-15）：返回阻塞删除的具体原因列表；空数组表示可安全删除。
     * 覆盖：进行中审批实例、未撤销回款记录、发票记录。
     */
    public static function deleteBlockers(int $id): array
    {
        return self::deleteBlockersMap([$id])[$id] ?? [];
    }

    /**
     * 批量删除阻塞项映射（P2-16【M-A2】回收站列表 N+1 消除）：与 deleteBlockers 同语义同文案，
     * 单次 whereIn + GROUP BY 聚合，返回 [contractId => [阻塞提示]]；无阻塞的 id 不出现。
     */
    public static function deleteBlockersMap(array $ids): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        $map = [];
        if (!$ids) return $map;

        // 进行中的审批流程（PENDING 状态实例）
        foreach (Db::name('approval_instance')->whereIn('contract_id', $ids)->where('status', 'PENDING')
            ->field('contract_id, COUNT(*) AS cnt')->group('contract_id')->select()->toArray() as $r) {
            $map[(int)$r['contract_id']][] = '存在进行中的审批流程';
        }
        // 未撤销的回款记录（当前 PENDING/PAID/OVERDUE；预留 REVOKED/CANCELLED 撤销态）
        foreach (Db::name('payment_record')->whereIn('contract_id', $ids)->whereNotIn('status', ['REVOKED', 'CANCELLED'])
            ->field('contract_id, COUNT(*) AS cnt')->group('contract_id')->select()->toArray() as $r) {
            $map[(int)$r['contract_id']][] = '存在未撤销的回款记录';
        }
        // 发票记录（VOID/RED/CANCELLED/REJECTED 作废/撤回/驳回态不阻塞删除）
        foreach (Db::name('contract_invoice')->whereIn('contract_id', $ids)->whereNotIn('status', ['VOID', 'RED', 'CANCELLED', 'REJECTED'])
            ->field('contract_id, COUNT(*) AS cnt')->group('contract_id')->select()->toArray() as $r) {
            $map[(int)$r['contract_id']][] = '存在发票记录';
        }
        return $map;
    }

    /** 获取当前状态可用的操作 */
    public static function getAvailableActions(string $status): array
    {
        return self::TRANSITIONS[$status] ?? [];
    }

    /**
     * 归档合同列表（Phase 1.4：从 MobileController::archive 下沉，消除 Db 直查）
     * 仅返回 status=ARCHIVED 且未删除的合同，按行级数据权限(SELF/DEPT/ALL)过滤，支持标题/合同号模糊搜索。
     * @param int $page 页码
     * @param int $pageSize 每页大小
     * @param string $keyword 标题/合同号模糊搜索
     * @return array ['list'=>array, 'total'=>int]
     */
    /**
     * 归档合同列表（P2-1：查询逻辑已下沉至 ContractQuery::getArchivedList，此处保留委托桩）
     */
    public static function getArchivedList(int $page, int $pageSize, string $keyword = ''): array
    {
        return ContractQuery::getArchivedList($page, $pageSize, $keyword);
    }

    /**
     * 归属人姓名（Phase 1.6：从 MobileController::contractDetail 下沉，消除 user 表直查）
     */
    public static function getOwnerName(int $ownerId): string
    {
        return Db::name('user')->where('id', $ownerId)->value('name') ?: '';
    }

    /**
     * 解析合同附件 URL（JSON 数组字符串 → 数组；Phase 1.6：从 MobileController::contractDetail 下沉）
     */
    public static function parseFileUrls(string $fileUrl): array
    {
        $raw = trim($fileUrl);
        if ($raw === '') return [];
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    /** 我的合同总数（按数据权限，is_deleted=0；工作台概览用，Phase 1.9 从 index() 下沉） */
    /**
     * 当前登录用户合同总数（P2-1：查询逻辑已下沉至 ContractQuery::getMyCount，此处保留委托桩）
     */
    public static function getMyCount(): int
    {
        return ContractQuery::getMyCount();
    }

    /** 合同原始记录（按 id 直查，不做删除/权限过滤；权限由调用方校验，Phase 1.9 从 approvalDetail() 下沉） */
    public static function getById(int $id): ?array
    {
        $row = Db::name('contract')->find($id);
        return $row ?: null;
    }

    /**
     * 客户关联合同列表（乙方 party_b_customer_id；非管理员追加数据权限，Phase 1.9 从 customerDetail() 下沉）
     * @param int $customerId 客户 id（作为乙方）
     * @param bool $isAdmin 是否管理员（决定是否追加数据权限）
     * @return array 合同列表（id, contract_no, title, status, amount, direction, trade_attr）
     */
    /**
     * 客户关联合同列表（P2-1：查询逻辑已下沉至 ContractQuery::getRelatedList，此处保留委托桩）
     */
    public static function getRelatedList(int $customerId, bool $isAdmin, int $limit = 20): array
    {
        return ContractQuery::getRelatedList($customerId, $isAdmin, $limit);
    }

    /**
     * 客户关联合同总数（N-m4：与 getRelatedList 同口径，用于客户详情页判断是否显示「查看全部」入口）
     * @param int $customerId 客户 id（作为乙方）
     * @param bool $isAdmin 是否管理员（决定是否追加数据权限）
     * @return int 关联合同总数（含数据范围过滤）
     */
    /**
     * 客户关联合同总数（P2-1：查询逻辑已下沉至 ContractQuery::getRelatedCount，此处保留委托桩）
     */
    public static function getRelatedCount(int $customerId, bool $isAdmin): int
    {
        return ContractQuery::getRelatedCount($customerId, $isAdmin);
    }

    /** 取原始行（不含 is_deleted 过滤，供越权校验/审计读取归属人/部门） */
    public static function findRaw(int $id): ?array
    {
        return Db::name('contract')->find($id) ?: null;
    }

    /** 编辑前校验用：查未删除合同基础行（P1-1：替代控制器 Db 直查，保留 is_deleted 过滤语义） */
    public static function findEditable(int $id): ?array
    {
        return Db::name('contract')->where('id', $id)->where('is_deleted', 0)->find() ?: null;
    }

    /**
     * 重复合同检测（防呆）：标题 + 甲乙双方 + 金额完全一致的未删除合同视为重复（P1-1：从控制器下沉）
     * @return array|null 重复的既存合同行（含 contract_no/title 供提示）
     */
    public static function findDuplicate(array $data, int $excludeId): ?array
    {
        // P2：金额按 DECIMAL(15,2) 的两位小数字符串归一比对，避免 float 二进制尾差
        // （如 0.1+0.2=0.30000000000000004）导致与库中 0.30 不匹配而漏判重复。
        $amount = sprintf('%.2f', (float)($data['amount'] ?? 0));
        return Db::name('contract')->where('is_deleted', 0)
            ->where('id', '<>', $excludeId)
            ->where('title', $data['title'] ?? '')
            ->where('party_a_name', $data['party_a_name'] ?? '')
            ->where('party_b_name', $data['party_b_name'] ?? '')
            ->where('amount', $amount)
            ->find() ?: null;
    }

    /**
     * 批量预加载合同（含数据范围过滤），供批量归档/删除循环内复用避免 N+1（P1-1：从控制器下沉）
     * @return array key=合同 id → 行
     */
    public static function batchLoad(array $ids): array
    {
        if (!$ids) return [];
        $q = Db::name('contract')->whereIn('id', array_map('intval', $ids))->where('is_deleted', 0);
        AuthLogic::appendDataScope($q, 'owner_id', 'dept_id');
        return array_column($q->select()->toArray(), null, 'id');
    }

    /**
     * AJAX 合同搜索（带数据范围：SELF/DEPT/ALL）
     * 返回 id,contract_no,title,status，与原控制器内联查询等价。
     */
    /**
     * AJAX 合同搜索（P2-1：查询逻辑已下沉至 ContractQuery::search，此处保留委托桩）
     */
    public static function search(string $keyword): array
    {
        return ContractQuery::search($keyword);
    }

    /**
     * 导出合同（CSV/XLSX 共用）：构建带数据范围的查询并逐行回调处理
     * 状态/日期过滤 + 字段/排序/分块均在 Logic 内完成，控制器仅负责写出格式。
     * @param callable $handler 逐行回调，接收单行关联数组
     * @return int 处理行数（用于审计条数）
     */
    public static function eachExportRow(string $status, string $dateStart, string $dateEnd, callable $handler, bool $includeSensitive = false): int
    {
        $query = Db::name('contract')->where('is_deleted', 0);
        AuthLogic::appendDataScope($query, 'owner_id', 'dept_id');

        if ($status) {
            $query->where('status', $status);
        }
        if ($dateStart) {
            $query->where('created_at', '>=', $dateStart);
        }
        if ($dateEnd) {
            $query->where('created_at', '<=', $dateEnd . ' 23:59:59');
        }

        // REV-45：边 chunk 边回调，内存峰值恒定，避免超大导出拼接进内存
        $fields = 'id, contract_no, title, business_type, status, amount, party_a_name, party_b_name';
        if ($includeSensitive) $fields .= ', party_b_credit_code';
        $fields .= ', effective_date, expiry_date, created_at';
        $query->field($fields)
            ->order('id', 'desc');

        $total = 0;
        $query->chunk(500, function ($rows) use ($handler, &$total) {
            foreach ($rows as $row) {
                unset($row['id']); // 去掉 chunk 游标用的主键，保持导出列与表头顺序一致
                $handler($row);
                $total++;
            }
        });
        return $total;
    }

    public static function countExportRows(string $status, string $dateStart, string $dateEnd): int
    {
        $query=Db::name('contract')->where('is_deleted',0);AuthLogic::appendDataScope($query,'owner_id','dept_id');
        if($status)$query->where('status',$status);if($dateStart)$query->where('created_at','>=',$dateStart);if($dateEnd)$query->where('created_at','<=',$dateEnd.' 23:59:59');
        return (int)$query->count();
    }

    /**
     * 附件预览鉴权：按文件名模糊预筛候选合同（含 owner_id/dept_id/file_url）
     * 不带数据范围——需先取出候选合同再由调用方精确匹配路径并做 canAccessRecord 判定。
     */
    /**
     * 附件预览鉴权候选合同（P2-1：查询逻辑已下沉至 ContractQuery::findByAttachmentPath，此处保留委托桩）
     */
    public static function findByAttachmentPath(string $like): array
    {
        return ContractQuery::findByAttachmentPath($like);
    }

    /**
     * 高频关键词：仅统计「当前登录用户自己创建」的合同 keywords 字段，
     * 按英文逗号拆词后统计词频，返回词频最高的 TopN（用于新建合同时快速选填）。
     * 数据来源与口径：keywords 入库前已由 normalize_keywords() 归一化为英文逗号分隔，
     * 故此处直接按逗号拆分即可；同时兼容历史可能存在的中文逗号/顿号。
     *
     * @param int $userId   当前登录用户 ID（仅取 creator_id = 该用户的合同）
     * @param int $limit    返回条数上限，默认 10
     * @return string[]     关键词数组（按词频降序）
     */
    /**
     * 高频关键词（P2-1：查询与分词逻辑已下沉至 ContractQuery::getHotKeywords / tokenizeKeywords，此处保留委托桩）
     */
    public static function getHotKeywords(int $userId, int $limit = 10): array
    {
        return ContractQuery::getHotKeywords($userId, $limit);
    }
}
