<?php
// ------------------------------------------------------------
// ApprovalQueryService — 审批查询服务
// 从 ApprovalLogic 按职责拆出（v2.38.1 大文件拆分）
// ------------------------------------------------------------

namespace app\common\logic;

use think\facade\Db;
use think\facade\Log;
use app\common\service\DingTalkService;
use app\common\service\InternalNotify;

class ApprovalQueryService
{

    /**
     * 审批实例展示行归一化：发票审批（biz_type=invoice）可能无关联合同（contract_id=0，独立快捷申请），
     * 此时 contract 联表字段为空，回退用发票标题/号码/金额展示，避免列表/详情显示空白或「合同」占位
     */
    private static function normalizeRow(array $r): array
    {
        if (($r['biz_type'] ?? '') === 'invoice' && empty($r['contract_title'])) {
            $r['contract_title'] = $r['invoice_title'] ?: '发票审批';
            $r['contract_no']    = $r['invoice_no'] ?? '';
            $r['amount']         = $r['invoice_amount'] ?? $r['amount'] ?? 0;
        }
        return $r;
    }

    /** 待审批列表 */
    public static function getPendingList(int $userId, int $page, int $pageSize, array $sort = ['ai.id', 'desc']): array
    {
        // 用子查询替代先 column 全量 instance_id 再 whereIn，避免无上限内存与 N+1（CR-60）
        $sub = function ($q) use ($userId) {
            $q->table('approval_record')
                ->where('approver_id', $userId)
                ->where('action', 'PENDING')
                ->field('DISTINCT instance_id');
        };

        $total = Db::name('approval_instance')->alias('ai')
            ->where('ai.status', 'PENDING')
            ->whereIn('ai.id', $sub)
            ->count();
        if ($total == 0) return ['list' => [], 'total' => 0];

        $list = Db::name('approval_instance')->alias('ai')
            ->leftJoin('contract c', 'ai.contract_id = c.id')
            ->leftJoin('contract_invoice ci', 'ci.id = ai.target_id AND ai.biz_type = \'invoice\'')
            ->join('user u', 'ai.submitted_by = u.id')
            ->join('approval_flow af', 'ai.flow_id = af.id')
            ->field('ai.*, c.contract_no, c.title as contract_title, c.amount, ci.invoice_title, ci.invoice_no, ci.amount AS invoice_amount, u.name as submitter_name, af.name as flow_name')
            ->where('ai.status', 'PENDING')
            ->whereIn('ai.id', $sub)
            ->order($sort[0], $sort[1])
            ->page($page, $pageSize)
            ->select()->toArray();

        return ['list' => array_map([self::class, 'normalizeRow'], $list), 'total' => $total];
    }

    /**
     * 已审批列表（撤销 REV-05 外层 status=PENDING 限制）
     * 口径：凡当前用户在 approval_record 中存在 APPROVED/REJECTED/TRANSFERRED 动作的实例均计入，
     * 不再限定实例自身状态，从而已办 Tab 可含「审批中/已通过/已驳回/已撤回」各种终态，避免列表恒为空。
     * 与 getPendingList（仅 PENDING+待我处理）、getMySubmittedList（我提交）三 Tab 口径对齐（REV-25）。

    /**
     * 已审批列表（撤销 REV-05 外层 status=PENDING 限制）
     * 口径：凡当前用户在 approval_record 中存在 APPROVED/REJECTED/TRANSFERRED 动作的实例均计入，
     * 不再限定实例自身状态，从而已办 Tab 可含「审批中/已通过/已驳回/已撤回」各种终态，避免列表恒为空。
     * 与 getPendingList（仅 PENDING+待我处理）、getMySubmittedList（我提交）三 Tab 口径对齐（REV-25）。
     */
    public static function getProcessedList(int $userId, int $page, int $pageSize, array $sort = ['ai.id', 'desc']): array
    {
        // 用子查询替代先 column 全量 instance_id 再 whereIn，避免无上限内存与 N+1（CR-60）
        $sub = function ($q) use ($userId) {
            $q->table('approval_record')
                ->where('approver_id', $userId)
                ->whereIn('action', ['APPROVED', 'REJECTED', 'TRANSFERRED'])
                ->field('DISTINCT instance_id');
        };

        // 注意：此处不再叠加 ai.status='PENDING'，否则实例一旦离开审批中即被排除，已办几乎永远为空
        $total = Db::name('approval_instance')->alias('ai')
            ->whereIn('ai.id', $sub)
            ->count();
        if ($total == 0) return ['list' => [], 'total' => 0];

        $list = Db::name('approval_instance')->alias('ai')
            ->leftJoin('contract c', 'ai.contract_id = c.id')
            ->leftJoin('contract_invoice ci', 'ci.id = ai.target_id AND ai.biz_type = \'invoice\'')
            ->join('user u', 'ai.submitted_by = u.id')
            ->join('approval_flow af', 'ai.flow_id = af.id')
            ->field('ai.*, c.contract_no, c.title as contract_title, c.amount, ci.invoice_title, ci.invoice_no, ci.amount AS invoice_amount, u.name as submitter_name, af.name as flow_name')
            ->whereIn('ai.id', $sub)
            ->order($sort[0], $sort[1])
            ->page($page, $pageSize)
            ->select()->toArray();

        return ['list' => array_map([self::class, 'normalizeRow'], $list), 'total' => $total];
    }

    /** 我提交的审批 */
    public static function getMySubmittedList(int $userId, int $page, int $pageSize, array $sort = ['ai.id', 'desc']): array
    {
        $query = Db::name('approval_instance')->alias('ai')
            ->leftJoin('contract c', 'ai.contract_id = c.id')
            ->leftJoin('contract_invoice ci', 'ci.id = ai.target_id AND ai.biz_type = \'invoice\'')
            ->join('approval_flow af', 'ai.flow_id = af.id')
            ->field('ai.*, c.contract_no, c.title as contract_title, c.amount, ci.invoice_title, ci.invoice_no, ci.amount AS invoice_amount, af.name as flow_name')
            ->where('ai.submitted_by', $userId);

        $total = $query->count();
        $list  = $query->order($sort[0], $sort[1])->page($page, $pageSize)->select()->toArray();

        return ['list' => array_map([self::class, 'normalizeRow'], $list), 'total' => $total];
    }

    /** 获取审批详情 */
    public static function getDetail(int $instanceId): ?array
    {
        $instance = Db::name('approval_instance')->alias('ai')
            ->leftJoin('contract c', 'ai.contract_id = c.id')
            ->leftJoin('contract_invoice ci', 'ci.id = ai.target_id AND ai.biz_type = \'invoice\'')
            ->join('user u', 'ai.submitted_by = u.id')
            ->join('approval_flow af', 'ai.flow_id = af.id')
            ->field('ai.*, c.contract_no, c.title as contract_title, c.amount, ci.invoice_title, ci.invoice_no, ci.amount AS invoice_amount, u.name as submitter_name, af.name as flow_name, af.nodes')
            ->where('ai.id', $instanceId)
            ->find();

        if (!$instance) return null;

        $instance = self::normalizeRow($instance);

        $instance['records'] = Db::name('approval_record')->alias('ar')
            ->leftJoin('user u', 'ar.approver_id = u.id')
            ->field('ar.*, u.name as approver_name')
            ->where('ar.instance_id', $instanceId)
            ->order('ar.node_order, ar.id')
            ->select()->toArray();

        // v2.38.0：抄送轨迹独立表（流程级知会，提交时一次性写入），与审批表决记录分开拼装时间轴
        $instance['cc_log'] = Db::name('approval_cc_log')->alias('acl')
            ->leftJoin('user u', 'acl.user_id = u.id')
            ->field('acl.*, u.name as user_name')
            ->where('acl.instance_id', $instanceId)
            ->order('acl.id')
            ->select()->toArray();

        return $instance;
    }

    /** 获取合同关联的审批 */
    public static function getByContract(int $contractId): array
    {
        return Db::name('approval_instance')->alias('ai')
            ->join('approval_flow af', 'ai.flow_id = af.id')
            ->join('user u', 'ai.submitted_by = u.id')
            ->field('ai.*, af.name as flow_name, u.name as submitter_name')
            ->where('ai.contract_id', $contractId)
            ->order('ai.id', 'desc')
            ->select()->toArray();
    }

    /**
     * CR-09：取合同全部审批历史（含已撤回/驳回实例及其节点意见）
     * 返回按实例倒序的数组，每个实例含 nodes（审批节点记录，含审批人/动作/意见），
     * 保证撤回/驳回后原审批意见不丢失，详情页可查看完整历史

    /**
     * CR-09：取合同全部审批历史（含已撤回/驳回实例及其节点意见）
     * 返回按实例倒序的数组，每个实例含 nodes（审批节点记录，含审批人/动作/意见），
     * 保证撤回/驳回后原审批意见不丢失，详情页可查看完整历史
     */
    public static function getApprovalHistory(int $contractId): array
    {
        $instances = \app\common\logic\ApprovalQueryService::getByContract($contractId);
        if (empty($instances)) {
            return [];
        }
        // P1-4【M-Pf1】消除 N+1：一次性取出该合同全部实例的审批记录（单条 IN 查询），
        // 再按 instance_id 分组挂接，查询数与实例数无关（由 N+1 降为固定 2 条）。
        $instanceIds = array_column($instances, 'id');
        $records = Db::name('approval_record')->alias('ar')
            ->join('user u', 'ar.approver_id = u.id')
            ->field('ar.*, u.name as approver_name')
            ->where('ar.instance_id', 'in', $instanceIds)
            ->order('ar.node_order', 'asc')
            ->select()->toArray();
        $grouped = [];
        foreach ($records as $r) {
            $grouped[$r['instance_id']][] = $r;
        }
        foreach ($instances as &$inst) {
            $inst['nodes'] = $grouped[$inst['id']] ?? [];
        }
        return $instances;
    }

    /**
     * 当前用户是否待审批某合同的审批实例（Phase 1.6：从 MobileController::contractDetail 下沉）
     * @param array|null $approvals 可选：调用方已拉取的审批历史（含 nodes），传入可避免重复全量拉取
     * @return int 待审批实例 ID，无则 0

    /**
     * 当前用户是否待审批某合同的审批实例（Phase 1.6：从 MobileController::contractDetail 下沉）
     * @param array|null $approvals 可选：调用方已拉取的审批历史（含 nodes），传入可避免重复全量拉取
     * @return int 待审批实例 ID，无则 0
     */
    public static function getPendingApprovalId(int $contractId, int $userId, ?array $approvals = null): int
    {
        // P2-1【M-Pf2】消除重复调用：优先复用调用方已拉取的历史，避免单次详情页两次全量拉取
        if ($approvals === null) {
            $approvals = \app\common\logic\ApprovalQueryService::getApprovalHistory($contractId);
        }
        foreach ($approvals as $a) {
            if (($a['status'] ?? '') !== 'PENDING') {
                continue;
            }
            // 复用 getApprovalHistory 已返回的 nodes（含审批记录），无需再对 approval_record 发冗余查询
            $nodes = $a['nodes'] ?? [];
            foreach ($nodes as $rec) {
                if (($rec['approver_id'] ?? 0) == $userId && ($rec['action'] ?? '') === 'PENDING') {
                    return (int)$a['id'];
                }
            }
        }
        return 0;
    }

    /**
     * 当前用户的审批待办数量（P3-2【m-A1】从 BaseController 下沉，统一审批计数口径，消除控制器 Db 直查）
     * @return int

    /**
     * 当前用户的审批待办数量（P3-2【m-A1】从 BaseController 下沉，统一审批计数口径，消除控制器 Db 直查）
     * @return int
     */
    public static function getPendingCountForUser(int $userId): int
    {
        try {
            return (int)Db::name('approval_record')
                ->where('approver_id', $userId)
                ->where('action', 'PENDING')->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /** 解析审批人 */
    /**
     * 审批人解析（P2-1：逻辑已下沉至 ApproverResolver::resolve；
     * 此处保留委托桩，避免改动大量 \app\common\logic\ApprovalActionService::resolveApprovers 调用点）

    /**
     * 当前用户是否为某审批实例当前节点的待审批人（Phase 1.9 从 approvalDetail() 下沉）
     * @param int $instanceId 审批实例 id
     * @param int $userId 当前用户 id
     * @return array|null 命中返回审批记录，否则 null
     */
    public static function getPendingAction(int $instanceId, int $userId): ?array
    {
        $row = Db::name('approval_record')
            ->where('instance_id', $instanceId)
            ->where('approver_id', $userId)
            ->where('action', 'PENDING')
            ->find();
        return $row ?: null;
    }

    /**
     * PERM-1：判断用户是否为某审批实例的「参与者」。
     * 参与者 = 提交人(submitted_by) 或 任一审批/抄送记录(approval_record.approver_id) 的归属人。
     * 用途：审批参与者默认可查看本审批实例及其合同详情，即使其无 approval:view 权限或超出数据范围
     * —— 其对该审批实例拥有合法的「经办 / 知会」知悉权，不应被数据隔离或角色权限拦截
     * （典型场景：普通用户被抄送一份审批，点开钉钉消息链接应能看到，而非「无权限查看该审批」）。
     *
     * @param int $instanceId 审批实例 ID
     * @param int $userId     当前用户 ID

    /**
     * PERM-1：判断用户是否为某审批实例的「参与者」。
     * 参与者 = 提交人(submitted_by) 或 任一审批/抄送记录(approval_record.approver_id) 的归属人。
     * 用途：审批参与者默认可查看本审批实例及其合同详情，即使其无 approval:view 权限或超出数据范围
     * —— 其对该审批实例拥有合法的「经办 / 知会」知悉权，不应被数据隔离或角色权限拦截
     * （典型场景：普通用户被抄送一份审批，点开钉钉消息链接应能看到，而非「无权限查看该审批」）。
     *
     * @param int $instanceId 审批实例 ID
     * @param int $userId     当前用户 ID
     */
    public static function isParticipant(int $instanceId, int $userId): bool
    {
        if ($userId <= 0) return false;
        $inst = Db::name('approval_instance')->where('id', $instanceId)->field('submitted_by')->find();
        if (!$inst) return false;
        if ((int)$inst['submitted_by'] === $userId) return true;
        if (Db::name('approval_record')
            ->where('instance_id', $instanceId)
            ->where('approver_id', $userId)
            ->find()) {
            return true;
        }
        // v2.38.0：抄送用户存在于 approval_cc_log，同样视为参与者（知会知悉权，
        // 保障纯抄送人点开钉钉/应用内消息可查看审批详情，而非「无权限查看」）。
        return (bool)Db::name('approval_cc_log')
            ->where('instance_id', $instanceId)
            ->where('user_id', $userId)
            ->find();
    }

    /** 取审批实例原始行（用于审计日志读取 contract_id；审批动作已授权，不附加数据范围） */
    public static function findRaw(int $instanceId): ?array
    {
        return Db::name('approval_instance')->find($instanceId) ?: null;
    }

    /** 取单条审批流（按 id；用于合同提交页预览默认流，全局配置不附加数据范围） */
    public static function getFlowById(int $flowId): ?array
    {
        if ($flowId <= 0) {
            return null;
        }
        return Db::name('approval_flow')->find($flowId) ?: null;
    }

    /** 启用中的审批流列表（全局配置不附加数据范围）

    /** 启用中的审批流列表（全局配置不附加数据范围）
     *  P2-8【M-A1】作为 getEnabledFlows 的唯一实现，AdminLogic 委托至此，避免两处定义漂移 */
    public static function getEnabledFlows(): array
    {
        return Db::name('approval_flow')->where('status', 1)->order('id')->select()->toArray();
    }

    /** 全部审批流程（含已停用 status=0）：管理列表展示用，停用项提供「恢复」入口 */
    public static function getAllFlows(): array
    {
        // v2.38.24：分组展示——合同流程（含空 biz_type 旧数据）在前、发票流程在后；
        //           组内按手动排序（sort_order 升序）展示，未排序的按 id 兜底
        return Db::name('approval_flow')->order('status', 'desc')->order('biz_type', 'asc')->order('sort_order', 'asc')->order('id')->select()->toArray();
    }

}
