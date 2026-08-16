<?php
// 本地 SQLite 验收库幂等升级；生产 MySQL 使用同版本 SQL 迁移。
define('ROOT_PATH', __DIR__ . '/../');
require ROOT_PATH . 'vendor/autoload.php';
if (is_file(ROOT_PATH . '.env')) \Dotenv\Dotenv::createImmutable(ROOT_PATH)->load();
$app = new \think\App(ROOT_PATH);
$app->initialize();

$db = \think\facade\Db::connect('sqlite');
$db->execute('CREATE INDEX IF NOT EXISTS idx_apv_biz_target_status ON approval_instance(biz_type, target_id, status)');
$db->execute('CREATE INDEX IF NOT EXISTS idx_activity_follow ON customer_activity(next_follow_at, customer_id)');
$existing = array_column($db->query("PRAGMA table_info('contract')"), 'name');
$columns = [
    'sign_status' => "TEXT NOT NULL DEFAULT 'WAITING'",
    'our_signed_date' => 'TEXT DEFAULT NULL',
    'counterpart_signed_date' => 'TEXT DEFAULT NULL',
    'signed_completed_date' => 'TEXT DEFAULT NULL',
    'original_copy_count' => 'INTEGER DEFAULT NULL',
    'original_received' => 'INTEGER DEFAULT NULL',
    'original_storage' => "TEXT DEFAULT ''",
    'original_keeper_id' => 'INTEGER DEFAULT 0',
];
foreach ($columns as $name => $definition) {
    if (!in_array($name, $existing, true)) {
        $db->execute("ALTER TABLE contract ADD COLUMN {$name} {$definition}");
        echo "added {$name}\n";
    }
}
$db->execute("UPDATE contract SET sign_status='COMPLETED', signed_completed_date=COALESCE(signed_completed_date, effective_date) WHERE status IN ('SIGNED','EXECUTING','EXPIRED','COMPLETED','ARCHIVED') AND sign_status='WAITING'");
$db->execute("CREATE TABLE IF NOT EXISTS contract_execution_cc (
    id INTEGER PRIMARY KEY AUTOINCREMENT, contract_id INTEGER NOT NULL DEFAULT 0,
    user_id INTEGER NOT NULL DEFAULT 0, needs_ack INTEGER NOT NULL DEFAULT 0,
    acknowledged_at TEXT DEFAULT NULL, created_by INTEGER NOT NULL DEFAULT 0,
    created_at TEXT DEFAULT (datetime('now','localtime')), UNIQUE(contract_id, user_id)
)");
$db->execute('CREATE INDEX IF NOT EXISTS idx_execution_cc_user ON contract_execution_cc(user_id, needs_ack)');
echo "SQLite v2.48.0 migration complete\n";
