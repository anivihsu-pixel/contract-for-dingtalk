<?php
// +----------------------------------------------------------------------
// | P0-1 历史回款数据校准脚本（一次性）
// | 修复「部分确认导致母记录 amount 翻倍虚增」的历史数据：
// | 旧逻辑部分确认时母记录 amount 保持原额、又插入 amount=remain 的子记录，
// | 导致应收总额翻倍；新逻辑（PaymentController::confirm）已把母记录 amount 调减为实收额。
// | 本脚本幂等地把「已部分确认（PAID 母记录 + 其下 PENDING 子记录）且 amount 仍为原额」的
// | 母记录 amount 修正为 paid_amount（实收额），使 应收总额 = 母记录(实收) + 子记录(剩余) = 原应收。
// | 用法：php scripts/calibrate_payment_amounts.php [--dry-run]
// +----------------------------------------------------------------------

define('ROOT_PATH', __DIR__ . '/../');
require ROOT_PATH . 'vendor/autoload.php';

if (is_file(ROOT_PATH . '.env')) {
    $dotenv = new \Dotenv\Dotenv(ROOT_PATH);
    $dotenv->load();
}

$app = new \think\App(ROOT_PATH);
$app->initialize();

use think\facade\Db;

$dryRun = in_array('--dry-run', $argv, true);
echo $dryRun ? "[DRY-RUN] 仅统计，不写入\n" : "[EXECUTE] 开始校准\n";

// 待校准母记录：状态 PAID 且存在 PENDING 子记录，且 amount 仍大于 paid_amount（即尚未调减）
$rows = Db::name('payment_record')
    ->where('status', 'PAID')
    ->where('id', 'IN', function ($q) {
        $q->table('payment_record')->where('status', 'PENDING')->where('parent_id', '>', 0)
            ->field('parent_id');
    })
    ->where('amount', '>', Db::raw('paid_amount'))
    ->field('id, amount, paid_amount')
    ->select()
    ->toArray();

if (empty($rows)) {
    echo "无需校准：未检测到翻倍虚增的历史回款母记录。\n";
    exit(0);
}

$totalReduced = 0.0;
$affected = 0;
foreach ($rows as $r) {
    $reduce = (float)$r['amount'] - (float)$r['paid_amount'];
    echo sprintf(
        "母记录 id=%d  原 amount=%.2f -> 修正为 paid_amount=%.2f  (调减 %.2f)\n",
        $r['id'], $r['amount'], $r['paid_amount'], $reduce
    );
    if (!$dryRun) {
        Db::name('payment_record')->where('id', $r['id'])->update(['amount' => $r['paid_amount']]);
    }
    $totalReduced += $reduce;
    $affected++;
}

echo sprintf(
    "== %s：校准母记录 %d 条，应收总额合计调减 ¥%.2f ==\n",
    $dryRun ? 'DRY-RUN 预计' : '已完成',
    $affected,
    $totalReduced
);
