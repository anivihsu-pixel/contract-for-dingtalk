<?php
// +----------------------------------------------------------------------
// | 回款逾期自动标记命令 — 供 crontab 调用：php think payment:mark-overdue
// | 将「待收(PENDING)且计划回款日已过」的回款记录自动置为「逾期(OVERDUE)」，
// | 与账龄统计/信用评级/提醒引擎三处口径统一（P1-4，deep review 2026-08-01）
// +----------------------------------------------------------------------

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use app\common\logic\PaymentLogic;

class PaymentMarkOverdue extends Command
{
    protected function configure()
    {
        $this->setName('payment:mark-overdue')
            ->setDescription('扫描已过计划回款日仍为待收的回款记录，自动置为「逾期」，并联动重算客户信用评级');
    }

    protected function execute(Input $input, Output $output)
    {
        $count = PaymentLogic::autoMarkOverdue();
        $output->writeln(sprintf('[%s] 回款逾期自动标记完成：处理 %d 条', date('Y-m-d H:i:s'), $count));
        return 0;
    }
}
