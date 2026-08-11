<?php
// +----------------------------------------------------------------------
// | 审批消息（站内信兜底）控制器
// +----------------------------------------------------------------------
// | 审批等关键事件的站内信兜底（与钉钉通知并行落库），任何登录用户
// | 均可在「今日提醒」页的「审批消息」板块查看，弥补仅钉钉通知、
// | 未绑定钉钉或钉钉推送失败时收不到的缺陷。本控制器仅提供 AJAX 接口，
// | 页面入口统一复用 /remind（与今日提醒共用侧边栏红点）。
// +----------------------------------------------------------------------

namespace app\controller;

use think\facade\Log;
use app\BaseController;
use app\common\service\InternalNotify;

class NotificationController extends BaseController
{
    /** AJAX: 我的消息列表（分页，供「今日提醒」页「审批消息」板块调用） */
    public function list()
    {
        list($page, $pageSize) = $this->getPageParams();
        [$rows, $total] = InternalNotify::pageList($this->userId, $page, $pageSize);

        $data = [];
        foreach ($rows as $n) {
            $data[] = [
                'id'         => $n['id'],
                'type'       => $n['type'],
                'title'      => $n['title'],
                'content'    => $n['content'],
                'url'        => $n['url'],
                'is_read'    => $n['is_read'],
                'created_at' => $n['created_at'],
            ];
        }
        return json(['code' => 0, 'msg' => 'ok', 'data' => $data, 'count' => $total]);
    }

    /** AJAX: 未读数量（顶栏红点） */
    public function unreadCount()
    {
        $cnt = InternalNotify::unreadCount($this->userId);
        return json(['code' => 0, 'msg' => 'ok', 'data' => ['count' => $cnt], 'count' => $cnt]);
    }

    /** AJAX: 标记单条已读（仅限本人） */
    public function markRead()
    {
        $id = (int) $this->getParam('id', 0);
        if ($id > 0) {
            InternalNotify::markRead($this->userId, $id);
        }
        // P2：已读后失效顶栏角标短缓存，否则红点最长滞后 60s
        \think\facade\Cache::delete('badge_remind_' . $this->userId);
        return json_success(null, '已标记为已读');
    }

    /** AJAX: 全部已读（仅限本人） */
    public function markAllRead()
    {
        InternalNotify::markAllRead($this->userId);
        // P2：同上，立即刷新顶栏红点
        \think\facade\Cache::delete('badge_remind_' . $this->userId);
        return json_success(null, '全部已读');
    }

    /**
     * AJAX: 检查消息指向的审批目标是否存在
     * 前端点击审批类消息前调用，避免跳转到已删除的审批页面。
     * 若审批已删除，后端同时标记该消息为已读。
     * 返回 {code:0, data:{exists:bool, url:string}}
     */
    public function checkTarget()
    {
        $id = (int) $this->getParam('id', 0);
        if ($id <= 0) {
            Log::warning('NotificationController.checkTarget 参数错误', ['error' => '缺少消息 ID', 'id' => $id]);
            return json_error('缺少消息 ID');
        }

        // 查消息记录
        $n = InternalNotify::findOwn($this->userId, $id);
        if (!$n) {
            Log::warning('NotificationController.checkTarget 消息不存在', [
                'notification_id' => $id,
                'user_id'         => $this->userId,
            ]);
            return json_error('消息不存在');
        }

        $url = $n['url'] ?? '';
        $exists = true;

        // 从 url 中提取审批 ID（支持 /approval/xxx 和 to=%2Fapproval%2Fxxx 两种格式）
        $apprId = 0;
        if (preg_match('#(?:m/)?approval/(\d+)#', $url, $m)) {
            $apprId = (int) $m[1];
        } elseif (preg_match('/[?&]to=([^&]+)/', $url, $m2)) {
            $decoded = urldecode($m2[1]);
            if (preg_match('#(?:m/)?approval/(\d+)#', $decoded, $m3)) {
                $apprId = (int) $m3[1];
            }
        }

        // 检查审批是否存在
        if ($apprId > 0) {
            $instance = \app\common\logic\ApprovalQueryService::getDetail($apprId);
            if (!$instance) {
                $exists = false;
                // 审批已删除，自动标记已读
                if ($n['is_read'] == 0) {
                    InternalNotify::markRead($this->userId, $id);
                }
            }
        }

        return json_success([
            'exists' => $exists,
            'url'    => $url,
        ]);
    }
}
