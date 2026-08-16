<?php

define('ROOT_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);
require ROOT_PATH . 'vendor/autoload.php';
if (is_file(ROOT_PATH . '.env')) Dotenv\Dotenv::createImmutable(ROOT_PATH)->load();
$app = new think\App(ROOT_PATH);
$app->initialize();

use think\facade\Db;

Db::transaction(function (): void {
    Db::name('system_config')->where('config_key', 'site_version')->delete();
    $raw = (string)Db::name('system_config')->where('config_key', 'dict_contract_status')->value('config_value');
    $statuses = json_decode($raw, true);
    if (is_array($statuses)) {
        $statuses['SIGNED'] = '已签署';
        Db::name('system_config')->where('config_key', 'dict_contract_status')->update([
            'config_value' => json_encode($statuses, JSON_UNESCAPED_UNICODE),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }
    $invoice = Db::name('approval_flow')->where('code', 'INVOICE')->find();
    if ($invoice) {
        Db::name('approval_flow')->where('id', (int)$invoice['id'])->update(['biz_type' => 'invoice']);
    } else {
        Db::name('approval_flow')->insert([
            'name' => '发票审批', 'code' => 'INVOICE', 'min_amount' => 0, 'max_amount' => 99999999.99,
            'use_amount' => 0, 'nodes' => json_encode([['name'=>'财务审批','type'=>'ROLE','role_code'=>'finance','mode'=>'OR']], JSON_UNESCAPED_UNICODE),
            'cc_list' => json_encode(['role_codes'=>[],'cc_user_ids'=>[]], JSON_UNESCAPED_UNICODE),
            'biz_type' => 'invoice', 'status' => 1, 'creator_id' => 1,
        ]);
    }
});

echo "SQLite v2.48.1 regression cleanup complete\n";
