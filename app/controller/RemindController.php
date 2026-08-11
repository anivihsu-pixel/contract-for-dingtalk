<?php
namespace app\controller;

use think\facade\View;
use app\BaseController;
use app\common\service\RemindService;
use app\common\service\DingTalkService;
use app\common\service\dd\DingTalkMock;

class RemindController extends BaseController
{
    /** 提醒页（v2.38.17 升级为统一待办中心）：Tab1 待办（待我审批动作待办）/ Tab2 提醒 / Tab3 审批消息 */
    public function index()
    {
        $isAdmin = !empty($this->user['is_admin']);
        $hasFin = $this->hasPermission('payment:view');
        $canApprove = $isAdmin || $this->hasPermission('approval:view');
        $alerts = RemindService::getTodayAlerts($this->userId, $isAdmin, $hasFin);
        $remindCount = RemindService::getOutstandingCount($this->userId, $isAdmin, $hasFin);
        $notifUnread = 0;
        $notifList = [];
        try {
            $notifUnread = \app\common\service\InternalNotify::unreadCount($this->userId);
            $notifList = \app\common\service\InternalNotify::unreadList($this->userId, 5);
        } catch (\Throwable $e) {}
        $pending = $canApprove
            ? \app\common\logic\ApprovalQueryService::getPendingList($this->userId, 1, 10)
            : ['list' => [], 'total' => 0];
        // 复用移动端 buildTodoStream 口径（v2.38.18 去重后：仅待我审批动作待办），保证 PC/移动一致
        $todo = \app\controller\MobileController::buildTodoStream($pending['list'] ?? [], $notifList, $alerts);
        $todoTotal = $pending['total'] ?? 0;
        View::assign('alerts', $alerts);
        View::assign('total', $remindCount);
        View::assign('notif_unread', $notifUnread);
        View::assign('pending_total', $pending['total'] ?? 0);
        View::assign('todo_list', $todo);
        View::assign('todo_total', $todoTotal);
        return View::fetch();
    }

    /** AJAX: 手动触发提醒检查（写入 remind_log 并展示当前用户视角结果） */
    public function check()
    {
        // S-03：接口守卫——remind:view 为全员基础权限，挡住未授权访问；引擎写入由 RemindService 60s 节流防高频触发
        $this->requirePermission('remind:view');
        $engineAlerts = [];
        RemindService::check($engineAlerts); // 触发引擎，全局去重写入
        $hasFin = $this->hasPermission('payment:view');
        $alerts = RemindService::getTodayAlerts($this->userId, !empty($this->user['is_admin']), $hasFin);
        return json_success([
            'alerts' => $alerts,
            'total'  => count($alerts),
        ]);
    }

    /** AJAX: 立即触发提醒推送（扫描到期/逾期并通过钉钉工作通知主动触达负责人/财务） */
    public function dispatch()
    {
        $this->requirePermission('remind:manage');
        $r = RemindService::dispatch();
        return json_success([
            'contracts' => $r['contracts'],
            'payments'  => $r['payments'],
            'notified'  => $r['notified'],
            'mock'      => DingTalkService::isMock(),
            'msg'       => sprintf('已推送：合同提醒 %d 条 / 回款提醒 %d 条 / 通知 %d 人', $r['contracts'], $r['payments'], $r['notified']),
        ]);
    }

    /** AJAX: 查看钉钉推送记录（Mock 模式下为本地模拟发送日志，便于演示与排查） */
    public function pushLog()
    {
        $this->requirePermission('remind:manage');
        $logs = array_values(array_filter(DingTalkMock::getLogs(), function ($l) {
            return ($l['method'] ?? '') === 'sendWorkNotice';
        }));
        return json_success([
            'mock'  => DingTalkService::isMock(),
            'logs'  => array_slice($logs, 0, 30),
            'total' => count($logs),
        ]);
    }
}
