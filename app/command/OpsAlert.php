<?php

namespace app\command;

use app\common\service\DingTalkService;
use app\common\service\InternalNotify;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\console\input\Argument;
use think\facade\Db;

class OpsAlert extends Command
{
    protected function configure()
    {
        $this->setName('ops:alert')
            ->addArgument('task', Argument::REQUIRED, '失败的任务名')
            ->addArgument('message', Argument::OPTIONAL, '错误摘要', '执行失败，请检查服务器日志')
            ->addArgument('failures', Argument::OPTIONAL, '连续失败次数', '1')
            ->setDescription('向系统管理员发送定时任务失败告警');
    }

    protected function execute(Input $input, Output $output)
    {
        $task = mb_substr((string)$input->getArgument('task'), 0, 64);
        $message = mb_substr((string)$input->getArgument('message'), 0, 500);
        $failures = max(1, (int)$input->getArgument('failures'));
        $admins = Db::name('user')->where('is_admin', 1)->where('status', 1)->column('id');
        if (!$admins) {
            $output->writeln('<error>没有可接收告警的启用管理员账号</error>');
            return 1;
        }
        $title = '系统任务失败：' . $task;
        $content = $message . "\n\n连续失败：{$failures} 次\n时间：" . date('Y-m-d H:i:s');
        InternalNotify::send($admins, 'SYSTEM_TASK_FAILED', $title, $content, '/admin');
        if ($failures >= 3) {
            try {
                DingTalkService::sendToLocalUsers($admins, $title, $content, rtrim((string)config('dingtalk.app_url'), '/') . '/admin');
            } catch (\Throwable $e) {
                $output->writeln('<comment>钉钉告警发送失败，站内信已保留</comment>');
            }
        }
        $output->writeln('<info>告警已发送</info>');
        return 0;
    }
}
