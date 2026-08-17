<?php
// +----------------------------------------------------------------------
// | 控制台配置：注册自定义命令
// +----------------------------------------------------------------------

return [
    'commands' => [
        'remind:check'      => \app\command\RemindCheck::class,
        'remind:dispatch'   => \app\command\RemindDispatch::class,
        'db:backup'         => \app\command\DbBackup::class,
        'approval:escalate' => \app\command\ApprovalEscalate::class,
        'approval:sla-check' => \app\command\ApprovalSlaCheck::class,
        'contract:expire'   => \app\command\ContractExpire::class,
        // P1-4（deep review）：逾期自动置 OVERDUE，每日 cron 执行，统一账龄/提醒口径
        'payment:mark-overdue' => \app\command\PaymentMarkOverdue::class,
        // v2.47.0：经营周报推送（每周一 cron：0 8 * * 1 php think report:weekly）
        'report:weekly' => \app\command\WeeklyReport::class,
        'system:check' => \app\command\SystemCheck::class,
        'ops:alert' => \app\command\OpsAlert::class,
    ],
];
