<?php
// +----------------------------------------------------------------------
// | 站内消息通知服务（站内信兜底）
// +----------------------------------------------------------------------
// | 审批等关键事件除钉钉工作通知外，同时落库 notification 表，
// | 确保接收人未绑定钉钉 userid 或钉钉 API 发送失败时，
// | 用户在应用内「消息中心」仍可见该提醒（弥补仅钉钉通知的缺陷）。
// +----------------------------------------------------------------------

namespace app\common\service;

use think\facade\Db;
use think\facade\Log;

class InternalNotify
{
    // 通知类型常量（与 ApprovalLogic 调用点对应）
    const TYPE_APPROVAL_SUBMITTED   = 'APPROVAL_SUBMITTED';    // 提交审批（通知审批人）
    const TYPE_APPROVAL_REJECTED    = 'APPROVAL_REJECTED';     // 审批被驳回（通知提交人）
    const TYPE_APPROVAL_APPROVED    = 'APPROVAL_APPROVED';     // 审批通过（通知提交人）
    const TYPE_APPROVAL_TRANSFERRED = 'APPROVAL_TRANSFERRED';  // 审批转交（通知转交目标）
    const TYPE_APPROVAL_CC          = 'APPROVAL_CC';           // 审批抄送知会（通知抄送人）
    const TYPE_APPROVAL_OVERDUE     = 'APPROVAL_OVERDUE';      // 审批超时催办（通知待审批人）

    /**
     * 发送站内信（批量，本地 DB 写，无网络 I/O，可在事务内直接调用）
     * @param array       $userIds 接收人本地用户ID数组
     * @param string      $type    通知类型（见上方常量）
     * @param string      $title   标题
     * @param string      $content 内容（markdown）
     * @param string|null $url     点击跳转链接（如 /approval/{id}）
     */
    public static function send(array $userIds, string $type, string $title, string $content, ?string $url = null): void
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));
        if (empty($userIds)) {
            return;
        }
        $now  = date('Y-m-d H:i:s');
        $rows = [];
        foreach ($userIds as $uid) {
            $rows[] = [
                'user_id'    => $uid,
                'type'       => $type,
                'title'      => mb_substr($title, 0, 128),
                'content'    => $content,
                'url'        => $url ?? '',
                'is_read'    => 0,
                'created_at' => $now,
            ];
        }
        try {
            Db::name('notification')->insertAll($rows);
        } catch (\Throwable $e) {
            // 站内信写入失败不影响主流程
            Log::warning('站内消息写入失败（不影响主流程）', [
                'err'      => $e->getMessage(),
                'user_ids' => $userIds,
                'type'     => $type,
            ]);
        }
    }

    /** 未读数量（顶栏红点） */
    public static function unreadCount(int $userId): int
    {
        return (int) Db::name('notification')->where('user_id', $userId)->where('is_read', 0)->count();
    }

    /** 未读列表（工作台待办卡 / 提醒页 Tab 复用，按时间倒序） */
    public static function unreadList(int $userId, int $limit = 5): array
    {
        if ($limit <= 0) return [];
        return Db::name('notification')
            ->where('user_id', $userId)
            ->where('is_read', 0)
            ->order('id', 'desc')
            ->limit($limit)
            ->select()->toArray();
    }

    /**
     * 分页列表（P1-1：控制器不再直查 notification 表）
     * @return array [rows, total]
     */
    public static function pageList(int $userId, int $page, int $pageSize): array
    {
        $total = (int)Db::name('notification')->where('user_id', $userId)->count();
        $rows  = Db::name('notification')
            ->where('user_id', $userId)
            ->order('id', 'desc')
            ->page($page, $pageSize)
            ->select()->toArray();
        return [$rows, $total];
    }

    /** 查本人单条消息（P1-1：替代控制器直查，仅限本人） */
    public static function findOwn(int $userId, int $id): ?array
    {
        return Db::name('notification')->where('id', $id)->where('user_id', $userId)->find() ?: null;
    }

    /** 标记单条已读（仅限本人） */
    public static function markRead(int $userId, int $id): bool
    {
        return Db::name('notification')->where('user_id', $userId)->where('id', $id)
                ->update(['is_read' => 1]) > 0;
    }

    /** 标记全部已读（仅限本人） */
    public static function markAllRead(int $userId): void
    {
        Db::name('notification')->where('user_id', $userId)->where('is_read', 0)
            ->update(['is_read' => 1]);
    }
}
