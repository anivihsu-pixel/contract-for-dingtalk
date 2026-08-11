<?php
// 公海自动回落命令（v2.38.2）：将认领后 N 天无跟进的客户自动释放回公海
// 用法：php think customer:pool-release [--days=30]
// 建议 crontab：每天凌晨 3:00 执行一次 0 3 * * * php think customer:pool-release

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use app\common\logic\CustomerLogic;

class CustomerPoolRelease extends Command
{
    protected function configure()
    {
        $this->setName('customer:pool-release')
            ->setDescription('公海自动回落：认领后N天无跟进自动释放回公海')
            ->addOption('days', 'd', \think\console\input\Option::VALUE_OPTIONAL, '无活动天数（默认30）', 30);
    }

    protected function execute(Input $input, Output $output)
    {
        // 2026-08-01：天数默认值改由 PC 后台「系统设置→系统配置→业务规则」的 rule_pool_release_days 控制，
        // CLI 显式传 --days 仍可覆盖（优先级：CLI 参数 > 后台配置 > 30）
        $days   = (int)$input->getOption('days');
        if ($days <= 0) {
            $days = (int)sys_config('rule_pool_release_days', '30') ?: 30;
        }
        $output->writeln("<info>公海自动回落检查：{$days} 天无跟进 → 释放回公海</info>");

        $released = CustomerLogic::autoReleaseStale($days);
        $output->writeln("<info>完成：释放 {$released} 个客户</info>");

        return 0;
    }
}
