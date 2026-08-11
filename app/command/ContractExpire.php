<?php
// +----------------------------------------------------------------------
// | 合同到期自动流转命令 — 供 crontab 调用：php think contract:expire
// | 将「执行中」且已过到期日的合同自动转为「已到期」
// +----------------------------------------------------------------------

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use app\common\logic\ContractLogic;

class ContractExpire extends Command
{
    protected function configure()
    {
        $this->setName('contract:expire')
            ->setDescription('扫描执行中且已过期的合同，自动流转为「已到期」');
    }

    protected function execute(Input $input, Output $output)
    {
        $count = ContractLogic::autoExpire();
        $output->writeln(sprintf('[%s] 合同到期自动流转完成：处理 %d 条', date('Y-m-d H:i:s'), $count));
        return 0;
    }
}
