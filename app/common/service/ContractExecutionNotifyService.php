<?php

namespace app\common\service;

use app\common\logic\ApproverResolver;
use think\facade\Db;

/** 合同进入执行后的流程抄送。 */
class ContractExecutionNotifyService
{
    public static function dispatch(array $contract, int $operatorId): int
    {
        $flowId = (int)($contract['flow_id'] ?? 0);
        if ($flowId <= 0) return 0;
        $flow = Db::name('approval_flow')->where('id', $flowId)->find();
        if (!$flow) return 0;
        $cc = json_decode((string)($flow['cc_list'] ?? ''), true) ?: [];
        $roleUsers = ApproverResolver::resolveRoleCodes(is_array($cc['role_codes'] ?? null) ? $cc['role_codes'] : []);
        $userIds = array_map('intval', is_array($cc['cc_user_ids'] ?? null) ? $cc['cc_user_ids'] : []);
        $deptIds = array_map('intval', is_array($cc['dept_ids'] ?? null) ? $cc['dept_ids'] : []);
        if ($deptIds) {
            $userIds = array_merge($userIds, Db::name('user')->whereIn('dept_id', $deptIds)->where('status', 1)->column('id'));
        }
        $recipients = array_values(array_unique(array_filter(array_merge($roleUsers, $userIds), static fn($id) => (int)$id > 0)));
        if (!$recipients) return 0;

        $needsAck = !empty($cc['require_ack']) ? 1 : 0;
        $existing = Db::name('contract_execution_cc')->where('contract_id', (int)$contract['id'])->column('user_id');
        $recipients = array_values(array_diff($recipients, array_map('intval', $existing ?: [])));
        if (!$recipients) return 0;
        $now = date('Y-m-d H:i:s');
        $rows = [];
        foreach ($recipients as $uid) {
            $rows[] = ['contract_id' => (int)$contract['id'], 'user_id' => $uid, 'needs_ack' => $needsAck,
                'acknowledged_at' => null, 'created_by' => $operatorId, 'created_at' => $now];
        }
        Db::name('contract_execution_cc')->insertAll($rows);
        $title = '合同已进入执行：' . $contract['title'];
        $content = $needsAck ? '该合同已进入执行，请查看并确认知悉。' : '该合同已进入执行，请查看。';
        $url = '/contract/' . (int)$contract['id'];
        InternalNotify::send($recipients, InternalNotify::TYPE_CONTRACT_EXECUTION_CC, $title, $content, $url);
        try {
            DingTalkService::sendToLocalUsers($recipients, $title, $content,
                rtrim((string)config('dingtalk.app_url'), '/') . $url, InternalNotify::TYPE_APPROVAL_CC);
        } catch (\Throwable $e) {
            // 站内信与轨迹已落库；钉钉失败不回滚业务状态。
        }
        return count($recipients);
    }
}
