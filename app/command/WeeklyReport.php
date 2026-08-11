<?php
// +----------------------------------------------------------------------
// | 经营周报推送命令（v2.47.0）— 供 crontab 调用：php think report:weekly
// | 每周一开会前生成上一自然周经营周报并推送给总经理：
// | 钉钉工作通知仅发极简提示（不携带摘要，省接口额度），站内信兜底（带摘要），完整内容在 /m/report/weekly。
// +----------------------------------------------------------------------

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;
use think\facade\Log;
use app\common\logic\WeeklyReportLogic;
use app\common\service\DingTalkService;
use app\common\service\InternalNotify;

class WeeklyReport extends Command
{
    protected function configure()
    {
        $this->setName('report:weekly')
            ->setDescription('生成并推送上周经营周报给总经理（钉钉极简提示 + 站内信摘要；完整内容见 /m/report/weekly）');
    }

    protected function execute(Input $input, Output $output)
    {
        [$start, $end] = WeeklyReportLogic::weekRange();
        $data = WeeklyReportLogic::build($start, $end);

        // 接收人：总经理角色（按角色 code=gm 定位，不依赖角色 id，与数据范围 ALL 一致）
        $gmRoleId = Db::name('role')->where('code', 'gm')->value('id');
        $gmIds = $gmRoleId
            ? Db::name('user_role')->where('role_id', (int)$gmRoleId)->column('user_id')
            : [];
        $gmIds = array_values(array_unique(array_filter(array_map('intval', $gmIds))));
        if (empty($gmIds)) {
            $output->writeln('未找到总经理角色（gm）用户，周报已生成但未推送');
            return 0;
        }

        $title    = "经营周报（{$start} ~ {$end}）";
        $url      = '/m/report/weekly'; // 移动端完整周报页（钉钉/站内信点击直达）
        $summary  = WeeklyReportLogic::summaryMarkdown($data); // 站内信内容（落库无额度成本，保留摘要）
        $notice   = "经营周报已生成（{$start} ~ {$end}），点击查看完整周报。"; // 钉钉仅发极简提示，不携带摘要

        // 站内信兜底：始终落库（未绑定钉钉/推送失败仍可读）
        InternalNotify::send($gmIds, 'WEEKLY_REPORT', $title, $summary, $url);

        // 钉钉工作通知：受开关控制，默认开启；关闭时仅站内信
        if (sys_config('weekly_report_dd_enabled', '1') === '1') {
            try {
                DingTalkService::sendToLocalUsers($gmIds, $title, $notice, $url, 'WEEKLY_REPORT');
                $output->writeln("周报已推送（钉钉+站内信）：{$start} ~ {$end}，接收 " . count($gmIds) . ' 人');
            } catch (\Throwable $e) {
                Log::warning('经营周报钉钉推送失败（站内信已发）', ['error' => $e->getMessage()]);
                $output->writeln("周报钉钉推送失败（站内信已发）：{$e->getMessage()}");
            }
        } else {
            $output->writeln("钉钉推送已关闭（weekly_report_dd_enabled=0），仅站内信：{$start} ~ {$end}，接收 " . count($gmIds) . ' 人');
        }
        return 0;
    }
}
