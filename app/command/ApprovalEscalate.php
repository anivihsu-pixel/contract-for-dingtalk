<?php
// +----------------------------------------------------------------------
// | 审批超时升级命令 — 供 crontab 调用：php think approval:escalate
// | 将超时会签/或签节点的未处理审批人自动标记为「超时自动通过」并推进流程，
// | 避免单个审批人不动作导致合同永久卡死（CR-03）。
// +----------------------------------------------------------------------

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use app\common\logic\ApprovalLogic;

class ApprovalEscalate extends Command
{
    protected function configure()
    {
        $this->setName('approval:escalate')
            ->setDescription('扫描并自动处理超时的会签/或签审批节点（将未处理审批人标记为超时自动通过并推进流程）');
    }

    protected function execute(Input $input, Output $output)
    {
        $handled = \app\common\logic\ApprovalActionService::processOverdueApprovals();
        $output->writeln(sprintf(
            '[%s] 审批超时升级完成：自动处理 %d 个超时审批实例',
            date('Y-m-d H:i:s'),
            $handled
        ));
        return 0;
    }
}
