<?php
// ------------------------------------------------------------
// ApprovalNotifyService — 审批通知服务
// 从 ApprovalLogic 按职责拆出（v2.38.1 大文件拆分）
// ------------------------------------------------------------

namespace app\common\logic;

use think\facade\Db;
use think\facade\Log;
use app\common\service\DingTalkService;
use app\common\service\InternalNotify;

class ApprovalNotifyService
{
    /** @var array 待发送通知队列：[ [userIds, title, markdown], ... ] */
    public static array $notifyQueue = [];

    /** 将钉钉通知入队（不在事务内发送，避免网络 I/O 持有 DB 锁）
     * @param string|null $url 跳转链接（可选，传入则改 action_card 在钉钉内打开）
     */
    public static function queueNotify(array $userIds, string $title, string $markdown, ?string $url = null, string $type = ''): void
    {
        self::$notifyQueue[] = [$userIds, $title, $markdown, $url, $type];
        // 站内信兜底：与钉钉通知并行落库，确保接收人未绑定钉钉/钉钉发送失败时，
        // 应用内「消息中心」仍可见该提醒（弥补仅钉钉通知的缺陷）。本地 DB 写，无网络 I/O。
        InternalNotify::send($userIds, $type, $title, $markdown, $url);
    }

    /** 事务提交后统一发送队列中的通知；单条失败不影响其余与主流程 */
    public static function flushNotify(): void
    {
        if (empty(self::$notifyQueue)) {
            return;
        }
        foreach (self::$notifyQueue as $task) {
            // P2-16（M35）：经“失败重试一次”封装发送，提升外呼成功率与可观测性
            // 注意：必须透传第 4 参数 $url（审批深链）与第 5 参数 $type（消息类型，用于钉钉按钮语义区分）
            // —— queueNotify 已存入 $task[3]/$task[4]，若此处漏传，深链丢失、抄送消息仍显示“点击处理”误导。
            \app\common\logic\ApprovalNotifyService::sendNotifyWithRetry($task[0], $task[1], $task[2], $task[3] ?? null, $task[4] ?? '');
        }
        self::$notifyQueue = [];
    }

    /**
     * 发送单条钉钉通知，失败重试一次，仍失败则告警（P2-16）。
     * 重试与告警均不抛异常，保证外呼失败不阻断主流程、且失败可观测。

    /**
     * 发送单条钉钉通知，失败重试一次，仍失败则告警（P2-16）。
     * 重试与告警均不抛异常，保证外呼失败不阻断主流程、且失败可观测。
     */
    private static function sendNotifyWithRetry(array $userIds, string $title, string $markdown, ?string $url = null, string $type = ''): void
    {
        try {
            DingTalkService::sendToLocalUsers($userIds, $title, $markdown, $url, $type);
        } catch (\Throwable $e) {
            // 首次失败：重试一次（网络抖动/瞬时超时常见，重试即可恢复）
            try {
                DingTalkService::sendToLocalUsers($userIds, $title, $markdown, $url, $type);
            } catch (\Throwable $e2) {
                // 重试后仍失败：告警记录，不影响主流程与其余通知
                Log::warning('钉钉通知发送失败（重试一次后仍失败，不影响主流程）', [
                    'title'    => $title,
                    'err'      => $e2->getMessage(),
                    'user_ids' => $userIds,
                ]);
            }
        }
    }
}
