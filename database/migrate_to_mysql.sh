#!/usr/bin/env bash
# =====================================================================
#  SQLite -> MySQL 迁移脚本（全新建库模式，无历史数据）
#  适用：本系统确认无存量业务数据。迁移 = 建库 + 建表/种子 + 切换连接。
#  用法：在【目标服务器】项目根目录执行  bash database/migrate_to_mysql.sh
#  前置：已安装 mysql-client、php（含 pdo_mysql 扩展）、ThinkPHP 项目已部署。
#  说明：本脚本仅做引导与串联；每个步骤的详细背景见 MIGRATION_SQLITE_TO_MYSQL.md。
# =====================================================================
set -euo pipefail

# 载入 .env（DB_HOST / DB_PORT / DB_NAME / DB_USER / DB_PASS / DB_TYPE）
set -a
[ -f .env ] && . ./.env
set +a

DB_HOST=${DB_HOST:-127.0.0.1}
DB_PORT=${DB_PORT:-3306}
DB_NAME=${DB_NAME:-contract_dingtalk}
DB_USER=${DB_USER:-root}
DB_PASS=${DB_PASS:-root}

echo "== [1/5] 在 MySQL 创建目标库（utf8mb4）=="
mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" <<SQL
CREATE DATABASE IF NOT EXISTS $DB_NAME
  DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
SQL

echo "== [2/5] 建表 + 种子（init_mysql.php 自带防覆盖检测，存在表则中止以避免覆盖）=="
php database/init_mysql.php

echo "== [3/5] 切换连接：DB_TYPE=sqlite -> mysql =="
sed -i.bak 's/^DB_TYPE=.*/DB_TYPE=mysql/' .env
php think clear 2>/dev/null || true

echo "== [4/5] 冒烟测试（详见 MIGRATION_SQLITE_TO_MYSQL.md 3.8）=="
echo "   登录 admin/password；重点验证：税务汇总页（DATE_FORMAT 修复）、移动端 /m、审批自动匹配流程。"
echo "   冒烟通过且观察期稳定后，再执行 [5/5] 退役 SQLite。"
echo ""
echo "== [5/5] 退役 SQLite（不可逆！确认 MySQL 冒烟通过且已生成 mysqldump 备份后再执行）=="
RETIRE_TS=$(date +%Y%m%d)
if [ -f runtime/data/contract.db ]; then
  mv runtime/data/contract.db "runtime/data/contract.db.retired-$RETIRE_TS"
  echo "   已归档 contract.db -> runtime/data/contract.db.retired-$RETIRE_TS"
else
  echo "   未找到 runtime/data/contract.db，跳过"
fi
if [ -f database/init_sqlite.php ]; then
  mv database/init_sqlite.php "database/init_sqlite.php.retired-$RETIRE_TS"
fi
if [ -f database/migrate_v217.php ]; then
  mv database/migrate_v217.php "database/migrate_v217.php.retired-$RETIRE_TS"
fi
echo "   SQLite 已退役；旧 .db 与专有脚本归档为 .retired-$RETIRE_TS（观察期后手动 rm）"
echo "   可选：删除 config/database.php 的 sqlite 连接项；SqliteGuard.php 已加守卫可保留或删。"
echo ""
echo "== 回滚（基于 MySQL 备份，不再依赖 SQLite）=="
echo "   # 切换前务必已 mysqldump 全量备份；回滚用备份恢复："
echo "   mysql -h\$DB_HOST -u\$DB_USER -p\$DB_PASS \$DB_NAME < /path/to/pre_cutover.sql"
echo "   （旧 SQLite 已退役，不再作为回滚兜底；详见 MIGRATION_SQLITE_TO_MYSQL.md 4.2）"
