<?php

define('ROOT_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);
require ROOT_PATH . 'vendor/autoload.php';
if (is_file(ROOT_PATH . '.env')) Dotenv\Dotenv::createImmutable(ROOT_PATH)->load();
$app = new think\App(ROOT_PATH);
$app->initialize();

use think\facade\Db;

Db::transaction(function (): void {
    Db::name('approval_flow')->where('code', 'QUICK')->where('sort_order', 0)->update(['sort_order' => 10]);
    Db::name('approval_flow')->whereIn('code', ['STANDARD', 'LARGE'])->where('sort_order', 0)->update(['sort_order' => 20]);
    Db::name('approval_flow')->where('code', 'INVOICE')->where('sort_order', 0)->update(['sort_order' => 10]);
});

echo "SQLite v2.48.2 flow priority migration complete\n";
