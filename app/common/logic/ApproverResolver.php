<?php
// +----------------------------------------------------------------------
// | 审批人解析器 — 从审批节点配置解析出待审批 / 抄送用户 ID 列表
// | 从 ApprovalLogic::resolveApprovers 安全拆分（P2-1，v2.35.3）
// +----------------------------------------------------------------------

namespace app\common\logic;

use think\facade\Db;

class ApproverResolver
{
    /** 抄送节点类型常量（与 ApprovalLogic::NODE_CC 保持一致） */
    public const NODE_CC = 'CC';

    /**
     * 解析审批节点应通知的用户 ID 列表
     *
     * 解析规则（与历史行为完全一致，仅从 ApprovalLogic 下沉至此）：
     *  - SPECIFIC_USER：直接使用节点 approvers 列表（intval 归一）
     *  - DEPT_LEADER  ：取提交人所在部门的管理员；无部门 / 本部门无主管时回退超级管理员
     *  - ROLE         ：按角色 code 解析拥有该角色的用户
     *  - CC           ：按角色（role_codes）+ 指定用户（cc_user_ids）解析并去重
     *  - v2.44.1 P1（自审批防护）：所有分支统一排除提交人自身（submitted_by），
     *    典型场景：提交人兼任本部门负责人（DEPT_LEADER）时不再成为自己合同的审批人；
     *    排除后为空（仅提交人自己）时 DEPT_LEADER 回退超级管理员兜底。
     *
     * @param array $node        节点配置（含 type / approvers / role_code / role_codes / cc_user_ids 等）
     * @param int   $submitterId 提交人 ID（用于部门主管回溯 + 自审批排除）
     * @return int[] 用户 ID 列表（去重后的整数数组）
     */
    public static function resolve(array $node, int $submitterId): array
    {
        $type = $node['type'] ?? 'SPECIFIC_USER';
        $approvers = $node['approvers'] ?? [];
        $result = [];

        if ($type === 'SPECIFIC_USER') {
            $result = array_map('intval', $approvers);
        } elseif ($type === 'DEPT_LEADER') {
            $user = Db::name('user')->find($submitterId);
            if (!$user || !$user['dept_id']) {
                // 提交人无部门：回退给超级管理员审批，避免实例卡死（is_admin=1 ∪ admin 角色，钉钉部署同效）
                $result = \app\common\logic\AuthLogic::getAdminUserIds(false);
            } else {
                // 优先取「部门负责人」(department.leader_user_id)，即真实的部门经理
                $leaderId = Db::name('department')->where('id', $user['dept_id'])->value('leader_user_id');
                if ($leaderId && Db::name('user')->where('id', $leaderId)->count()) {
                    $result = [$leaderId];
                } else {
                    // 回退：本部门管理员（无负责人时的兜底，避免实例卡死）
                    $members = \app\common\logic\AuthLogic::getAdminUserIds(false);
                    // 本部门内命中管理员则用之，否则回退全部超级管理员（?: [0] 防空 IN 条件）
                    $deptMembers = Db::name('user')
                        ->where('dept_id', $user['dept_id'])
                        ->whereIn('id', $members ?: [0])
                        ->column('id');
                    $result = $deptMembers ?: $members;
                }
            }
        } elseif ($type === 'ROLE') {
            $roleCode = $node['role_code'] ?? '';
            if ($roleCode) {
                $result = Db::name('user_role')->alias('ur')
                    ->join('role r', 'ur.role_id = r.id')
                    ->where('r.code', $roleCode)
                    ->column('ur.user_id');
            }
        } elseif ($type === self::NODE_CC) {
            // 抄送节点：按角色解析收件人（可多角色）+ 指定用户（cc_user_ids）
            $recipients = [];
            $roleCodes = $node['role_codes'] ?? [];
            if (!empty($roleCodes)) {
                $ids = Db::name('user_role')->alias('ur')
                    ->join('role r', 'ur.role_id = r.id')
                    ->whereIn('r.code', $roleCodes)
                    ->column('ur.user_id');
                if ($ids) $recipients = array_merge($recipients, $ids);
            }
            $ccUserIds = $node['cc_user_ids'] ?? [];
            if (!empty($ccUserIds)) {
                $recipients = array_merge($recipients, array_map('intval', $ccUserIds));
            }
            $result = $recipients;
        }

        // P1（自审批防护）：统一排除提交人自身——提交人不能审批自己提交的申请
        $filtered = array_values(array_filter($result, function ($uid) use ($submitterId) {
            return (int)$uid !== (int)$submitterId;
        }));
        // DEPT_LEADER 排除提交人后为空（提交人即部门唯一负责人）：回退超级管理员兜底，避免实例卡死
        if ($type === 'DEPT_LEADER' && empty($filtered)) {
            return \app\common\logic\AuthLogic::getAdminUserIds(false);
        }
        // 去重并保持顺序
        return array_values(array_unique($filtered));
    }

    /**
     * v2.38.0：按角色码数组解析拥有这些角色的用户 ID 并集（流程级抄送 cc_list 复用）。
     * @param string[] $roleCodes 角色 code 列表
     * @return int[] 用户 ID（去重）
     */
    public static function resolveRoleCodes(array $roleCodes): array
    {
        if (empty($roleCodes)) {
            return [];
        }
        $ids = Db::name('user_role')->alias('ur')
            ->join('role r', 'ur.role_id = r.id')
            ->whereIn('r.code', $roleCodes)
            ->column('ur.user_id');
        return $ids ?: [];
    }
}
