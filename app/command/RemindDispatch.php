<?php
// +----------------------------------------------------------------------
// | 每日提醒推送命令 — 供 crontab 调用：php think remind:dispatch
// +----------------------------------------------------------------------

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use app\common\service\RemindService;

class RemindDispatch extends Command
{
    protected function configure()
    {
        $this->setName('remind:dispatch')
            ->setDescription('扫描合同到期/回款逾期并通过钉钉工作通知主动推送给负责人与财务');
    }

    protected function execute(Input $input, Output $output)
    {
        $r = RemindService::dispatch();
        $output->writeln(sprintf(
            '[%s] 提醒推送完成：合同提醒 %d 条，回款提醒 %d 条，通知用户 %d 人',
            date('Y-m-d H:i:s'),
            $r['contracts'],
            $r['payments'],
            $r['notified']
        ));
        return 0;
    }
}
