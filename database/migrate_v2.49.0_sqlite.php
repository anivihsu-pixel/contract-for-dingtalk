<?php
define('ROOT_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);
require ROOT_PATH . 'vendor/autoload.php';
if (is_file(ROOT_PATH . '.env')) Dotenv\Dotenv::createImmutable(ROOT_PATH)->load();
$app = new think\App(ROOT_PATH); $app->initialize();
$db = think\facade\Db::connect('sqlite');
$db->execute("CREATE TABLE IF NOT EXISTS contract_early_execution (
 id INTEGER PRIMARY KEY AUTOINCREMENT, contract_id INTEGER NOT NULL DEFAULT 0,
 risk_description TEXT NOT NULL, status TEXT NOT NULL DEFAULT 'PENDING', applicant_id INTEGER NOT NULL DEFAULT 0,
 reviewer_id INTEGER NOT NULL DEFAULT 0, review_comment TEXT DEFAULT '', reviewed_at TEXT DEFAULT NULL,
 created_at TEXT DEFAULT (datetime('now','localtime')), updated_at TEXT DEFAULT (datetime('now','localtime')))");
$db->execute('CREATE INDEX IF NOT EXISTS idx_early_contract_status ON contract_early_execution(contract_id,status)');
$db->execute('CREATE INDEX IF NOT EXISTS idx_early_reviewer ON contract_early_execution(reviewer_id,status)');
echo "SQLite v2.49.0 early execution migration complete\n";
