<?php
// +----------------------------------------------------------------------
// | 客户信用评级命令 — 供 crontab 调用：php think customer:credit-check
// | 遍历全部非本公司客户，按逾期回款重算信用评分/高风险标记/等级。
// +----------------------------------------------------------------------

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use app\common\logic\CustomerLogic;

class CustomerCreditCheck extends Command
{
    protected function configure()
    {
        $this->setName('customer:credit-check')
            ->setDescription('按逾期回款重算全部客户信用评级（评分/高风险/等级）');
    }

    protected function execute(Input $input, Output $output)
    {
        $stats = CustomerLogic::recalcAllCreditScores();
        $output->writeln(sprintf(
            '[%s] 客户信用评级完成：扫描 %d 个客户，标记高风险 %d 个',
            date('Y-m-d H:i:s'),
            $stats['total'],
            $stats['high_risk']
        ));
        return 0;
    }
}
