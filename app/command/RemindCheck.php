<?php
// +----------------------------------------------------------------------
// | 提醒触发引擎命令 — 供 crontab 周期调用：php think remind:check
// | REV-30：将驾驶舱 GET 渲染时的写库触发(RemindService::check)迁移到命令行/定时任务，
// | 页面（DashboardController::index）仅做读取展示(getTodayAlerts)，避免 GET 请求产生写副作用。
// +----------------------------------------------------------------------

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use app\common\service\RemindService;

class RemindCheck extends Command
{
    protected function configure()
    {
        $this->setName('remind:check')
            ->setDescription('触发站内提醒引擎（写入 remind_log 去重），供 crontab 周期调用；页面渲染不再写库');
    }

    protected function execute(Input $input, Output $output)
    {
        $alerts = [];
        $r = RemindService::check($alerts);
        $output->writeln(sprintf(
            '[%s] 提醒引擎触发完成：合同提醒 %d 条，回款提醒 %d 条',
            date('Y-m-d H:i:s'),
            $r['contracts'] ?? 0,
            $r['payments'] ?? 0
        ));
        return 0;
    }
}
