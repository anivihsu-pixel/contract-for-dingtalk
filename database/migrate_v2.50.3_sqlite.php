<?php
define('ROOT_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);
require ROOT_PATH . 'vendor/autoload.php';
if (is_file(ROOT_PATH . '.env')) Dotenv\Dotenv::createImmutable(ROOT_PATH)->load();
$app = new think\App(ROOT_PATH); $app->initialize();
$db = think\facade\Db::connect('sqlite');
$columns = $db->query("PRAGMA table_info('customer')");
$hasRemark = false;
foreach ($columns as $column) {
    if (($column['name'] ?? '') === 'remark') { $hasRemark = true; break; }
}
if (!$hasRemark) $db->execute("ALTER TABLE customer ADD COLUMN remark TEXT DEFAULT ''");
echo "SQLite v2.50.3 customer remark migration complete\n";
