<?php
// +----------------------------------------------------------------------
// | MySQL 存量库字段中文注释同步（2026-08-10）
// | 背景：已部署的 MySQL 库表结构已存在，重跑 init_mysql.php（CREATE TABLE IF NOT EXISTS）
// |       不会改写现有表——存量库字段在 information_schema 中无 COMMENT，Navicat/DBeaver
// |       等工具点击英文字段名看不到中文注释（新部署执行 init_mysql.php 后无需本脚本）。
// | 原理：读取基准 database/init_mysql.php 的字段定义（类型/默认值/非空 + COMMENT），
// |       对现有库逐表执行 ALTER TABLE ... MODIFY COLUMN ... COMMENT '中文'。
// | 幂等：仅对 information_schema 中 COLUMN_COMMENT 为空的列执行，可重复运行。
// | 用法：先在 .env 配置 DB_HOST/DB_PORT/DB_NAME/DB_USER/DB_PASS（mysql 连接），然后：
// |       php database/sync_mysql_comments.php
// | 注意：MODIFY COLUMN 会重建对应表（大表耗时、期间锁表），建议业务低峰期执行。
// +----------------------------------------------------------------------

define('ROOT_PATH', __DIR__ . '/../');
require ROOT_PATH . 'vendor/autoload.php';

// Load .env
if (is_file(ROOT_PATH . '.env')) {
    $dotenv = new \Dotenv\Dotenv(ROOT_PATH);
    $dotenv->load();
}

$app = new \think\App(ROOT_PATH);
$app->initialize();

use think\facade\Db;

// 显式使用 mysql 连接（config/database.php 中已预置 'mysql' 连接，读取 .env）
$db = Db::connect('mysql');
$targetDb = env('DB_NAME', 'contract_dingtalk');

// 读取基准 init_mysql.php 源码，解析每张表的字段定义（与 init.sql 1:1，故只解析基准）
$src = file_get_contents(__DIR__ . '/init_mysql.php');
if ($src === false) {
    echo "无法读取 database/init_mysql.php\n";
    exit(1);
}

// 匹配每个 CREATE TABLE IF NOT EXISTS 块（闭合行以 ENGINE 开头，避免误吞字段内括号）
if (!preg_match_all('/CREATE TABLE IF NOT EXISTS `(\w+)`\s*\((.*?)\)\s*ENGINE/s', $src, $tables, PREG_SET_ORDER)) {
    echo "init_mysql.php 中未解析到任何 CREATE TABLE\n";
    exit(1);
}

$total = 0;
$skipped = 0;
$failed = 0;

foreach ($tables as $tbl) {
    $table = $tbl[1];
    $lines = explode("\n", $tbl[2]);
    foreach ($lines as $line) {
        $s = trim($line);
        if ($s === '' || strpos($s, '--') === 0) continue;                       // 空行 / 注释行（含 -- 表注释）
        if (preg_match('/^(PRIMARY|FOREIGN|UNIQUE|KEY|INDEX|CONSTRAINT|CHECK)\b/i', $s)) continue; // 约束行
        if (!preg_match('/^`(\w+)`(.*)$/s', $s, $mm)) continue;                  // 非字段定义行
        $col = $mm[1];
        $rest = $mm[2];
        if (($p = strpos($rest, '--')) !== false) $rest = substr($rest, 0, $p);  // 去掉行尾 -- 注释
        $rest = rtrim($rest);
        $comment = '';
        if (preg_match("/COMMENT\s+'((?:[^']|'')*)'/", $rest, $cm)) {
            $comment = $cm[1];
            $rest = substr($rest, 0, strpos($rest, 'COMMENT'));
        }
        $rest = rtrim($rest);
        if (substr($rest, -1) === ',') $rest = rtrim(substr($rest, 0, -1));      // 去掉字段尾逗号
        $def = trim($rest);
        // 还原 init_mysql.php（PHP 双引号字符串）中的转义：\" -> "，'' -> '（MySQL 单引号转义）
        $comment = str_replace(['\\"', "''"], ['"', "'"], $comment);
        if ($def === '' || $comment === '') {
            echo "  [跳过] {$table}.{$col}: 无有效定义/注释\n";
            $skipped++;
            continue;
        }
        // 幂等：仅补 COLUMN_COMMENT 为空的列
        $row = $db->query(
            "SELECT COLUMN_COMMENT FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?",
            [$targetDb, $table, $col]
        );
        if (!empty($row[0]['COLUMN_COMMENT'])) {
            $skipped++;
            continue;
        }
        $sql = "ALTER TABLE `{$table}` MODIFY COLUMN `{$col}` {$def} COMMENT '"
            . str_replace("'", "''", $comment) . "'";
        try {
            $db->execute($sql);
            echo "  [OK]   {$table}.{$col}: {$def} COMMENT '{$comment}'\n";
            $total++;
        } catch (\Throwable $e) {
            echo "  [FAIL] {$table}.{$col}: " . $e->getMessage() . "\n  SQL: {$sql}\n";
            $failed++;
        }
    }
}

echo "\n完成：补注释 {$total} 列，跳过 {$skipped} 列，失败 {$failed} 列。\n";
if ($failed > 0) exit(1);
echo "可在 Navicat/DBeaver 中刷新表结构查看字段中文注释。\n";
