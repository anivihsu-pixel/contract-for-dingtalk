<?php

namespace app\command;

use app\common\service\ProductionCheckService;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\console\input\Option;

class SystemCheck extends Command
{
    protected function configure()
    {
        $this->setName('system:check')
            ->addOption('basic', null, Option::VALUE_NONE, '仅检查数据库和运行目录')
            ->setDescription('检查生产配置、数据库连接和运行目录');
    }

    protected function execute(Input $input, Output $output)
    {
        $result = ProductionCheckService::run(!$input->getOption('basic'));
        foreach ($result['checks'] as $check) {
            $tag = $check['ok'] ? '<info>PASS</info>' : '<error>FAIL</error>';
            $output->writeln(sprintf('[%s] %s：%s', $tag, $check['name'], $check['message']));
        }
        $output->writeln($result['ok'] ? '<info>系统自检通过</info>' : '<error>系统自检未通过</error>');
        return $result['ok'] ? 0 : 1;
    }
}
