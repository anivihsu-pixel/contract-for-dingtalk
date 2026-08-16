<?php
define('ROOT_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);
require ROOT_PATH . 'vendor/autoload.php';
if (is_file(ROOT_PATH . '.env')) Dotenv\Dotenv::createImmutable(ROOT_PATH)->load();
$app = new think\App(ROOT_PATH); $app->initialize();
$db = think\facade\Db::connect('sqlite');

$db->execute("UPDATE contract SET status='EXECUTING' WHERE status IN ('APPROVED','SIGNED')");
$dict = '{"DRAFT":"草稿","PENDING_APPROVAL":"待审批","REJECTED":"已驳回","EXECUTING":"执行中","COMPLETED":"已完成","TERMINATED":"已终止","EXPIRED":"已到期","ARCHIVED":"已归档"}';
$db->name('system_config')->where('config_key', 'dict_contract_status')->update(['config_value' => $dict]);
$db->execute('DROP TABLE IF EXISTS contract_early_execution');

$columns = array_column($db->query("PRAGMA table_info('contract')"), 'name');
foreach (['sign_status', 'our_signed_date', 'counterpart_signed_date', 'signed_completed_date',
          'original_copy_count', 'original_received', 'original_storage', 'original_keeper_id'] as $column) {
    if (in_array($column, $columns, true)) {
        $db->execute('ALTER TABLE contract DROP COLUMN ' . $column);
    }
}

echo "SQLite v2.50.5 simplified contract lifecycle migration complete\n";
