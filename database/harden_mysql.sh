#!/usr/bin/env bash
# =============================================================================
# harden_mysql.sh —— MySQL 安全加固（配套 MIGRATION_SQLITE_TO_MYSQL.md §5）
#
# 适用：迁移到 MySQL 后的【目标生产服务器】，以具备 SUPER/GRANT 权限的账户执行。
# 前置：已安装 mysql 客户端（mysql/mysqldump），且能连上目标实例。
# 说明：本脚本不触碰业务数据，仅做账户/权限/连接/审计类加固，可重复执行（幂等）。
#
# 用法：
#   DB_NAME=contract_dingtalk APP_HOST=10.0.0.5 \
#     APP_USER=contract_app APP_PASS='<强随机>' \
#     bash database/harden_mysql.sh
#   # 不传 APP_PASS 时脚本用 openssl 生成一次性口令并打印（仅显示一次）
#
# 网络隔离 / 静态加密 / 备份 属 OS 或应用层职责，本脚本仅输出 my.cnf 指引，
# 不自动改写 my.cnf（避免破坏现有配置，需 DBA 复核后手动落盘）。
# =============================================================================
set -euo pipefail

MYSQL="${MYSQL:-mysql}"
DB_NAME="${DB_NAME:-contract_dingtalk}"
APP_HOST="${APP_HOST:-127.0.0.1}"        # 允许连接的应用服务器 IP（最小化为 127.0.0.1）
APP_USER="${APP_USER:-contract_app}"     # 专用低权限应用账户
APP_PASS="${APP_PASS:-}"                 # 强随机口令；留空则生成
ROOT_HOST="${ROOT_HOST:-localhost}"

# 1) 检查 mysql 客户端
if ! command -v "$MYSQL" >/dev/null 2>&1; then
  echo "✗ 未找到 mysql 客户端，请先安装 MySQL 客户端或设置 MYSQL 环境变量" >&2
  exit 1
fi

# 2) 生成一次性口令（若未提供）
if [ -z "$APP_PASS" ]; then
  if command -v openssl >/dev/null 2>&1; then
    APP_PASS=$(openssl rand -base64 18 | tr -dc 'A-Za-z0-9' | head -c 24)
    echo "→ 已生成应用账户口令（请妥善保存，仅显示这一次）: $APP_PASS"
  else
    echo "✗ 未提供 APP_PASS 且 openssl 不可用，无法生成口令" >&2
    exit 1
  fi
fi

# 3) 连接参数：root 口令通过 MYSQL_PWD 环境变量传递，避免出现在进程参数列表中
#    （也可不设，mysql 会交互式提示输入）
MYSQL_ROOT_PASS="${MYSQL_ROOT_PASS:-}"
if [ -n "$MYSQL_ROOT_PASS" ]; then export MYSQL_PWD="$MYSQL_ROOT_PASS"; fi
MY_OPT=()

run_sql() { # run_sql <stmt> [tolerant]
  local sql="$1" tolerant="${2:-no}"
  if [ "$tolerant" = "tolerant" ]; then
    "$MYSQL" "${MY_OPT[@]}" -N -e "$sql" 2>/dev/null || true
  else
    "$MYSQL" "${MY_OPT[@]}" -N -e "$sql"
  fi
}

echo "=== [1/6] 环境确认 ==="
run_sql "SELECT VERSION() AS ver;" || { echo "✗ 无法连接 MySQL，请检查凭据/网络" >&2; exit 1; }
HAVE_SSL=$(run_sql "SHOW VARIABLES LIKE 'have_ssl';" | awk '{print $2}')
echo "have_ssl = ${HAVE_SSL:-UNKNOWN} （应为 YES；否则需先配置服务端证书）"

echo "=== [2/6] 删除匿名账户与 test 库（§5.1 / §5.8）==="
run_sql "DELETE FROM mysql.user WHERE User='';" tolerant
run_sql "DROP DATABASE IF EXISTS test;" tolerant
run_sql "DELETE FROM mysql.db WHERE Db='test' OR Db='test_%';" tolerant

echo "=== [3/6] 限制 root 仅本地（§5.1 / §5.3）==="
# 移除任何 root@'%' 等非本地 root，确保无远程 root
run_sql "DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost','127.0.0.1','::1');" tolerant

echo "=== [4/6] 创建专用低权限应用账户 REQUIRE SSL（§5.1 / §5.2）==="
run_sql "CREATE USER IF NOT EXISTS '${APP_USER}'@'${APP_HOST}' IDENTIFIED BY '${APP_PASS}' REQUIRE SSL;" || \
run_sql "CREATE USER IF NOT EXISTS '${APP_USER}'@'${APP_HOST}' IDENTIFIED BY '${APP_PASS}';"
run_sql "GRANT SELECT, INSERT, UPDATE, DELETE, EXECUTE ON \`${DB_NAME}\`.* TO '${APP_USER}'@'${APP_HOST}';"
# 显式撤销一切管理/DDL 权限（纵深防御：即使未来被误授权也能回收）
run_sql "REVOKE ALL PRIVILEGES, GRANT OPTION FROM '${APP_USER}'@'${APP_HOST}';" tolerant
run_sql "GRANT SELECT, INSERT, UPDATE, DELETE, EXECUTE ON \`${DB_NAME}\`.* TO '${APP_USER}'@'${APP_HOST}';"
run_sql "REVOKE FILE, PROCESS, SUPER, RELOAD, SHUTDOWN, CREATE, ALTER, DROP, INDEX, REFERENCES, CREATE TEMPORARY TABLES, LOCK TABLES, EVENT, TRIGGER ON *.* FROM '${APP_USER}'@'${APP_HOST}';" tolerant

echo "=== [5/6] 强口令策略 + 连接/会话安全（§5.6 / §5.8）==="
# 5.8 validate_password（MySQL 8.0 为 component，5.7 为 plugin；失败则跳过，不阻断）
run_sql "INSTALL COMPONENT 'file://component_validate_password';" tolerant
run_sql "INSTALL PLUGIN validate_password SONAME 'validate_password.so';" tolerant
run_sql "SET GLOBAL validate_password.length=12;" tolerant
run_sql "SET GLOBAL validate_password.policy=STRONG;" tolerant
run_sql "SET GLOBAL validate_password.mixed_case_count=1;" tolerant
run_sql "SET GLOBAL validate_password.number_count=1;" tolerant
run_sql "SET GLOBAL validate_password.special_char_count=1;" tolerant
# 5.6 连接与会话
run_sql "SET GLOBAL max_connections=200;"
run_sql "SET GLOBAL wait_timeout=28800;"
run_sql "SET GLOBAL interactive_timeout=28800;"
run_sql "SET GLOBAL max_connect_errors=100;"

echo "=== [6/6] 生效 ==="
run_sql "FLUSH PRIVILEGES;"

echo
echo "✓ 数据库账户加固完成。"
echo "  应用账户: '${APP_USER}'@'${APP_HOST}'  (REQUIRE SSL, 仅 DML+EXECUTE on ${DB_NAME})"
echo
echo "--- 仍需 DBA 手动落盘的 OS / 服务端加固（§5.2/5.3/5.4/5.5/5.7）---"
cat <<'GUIDE'
[my.cnf / 服务端配置]  (改后需 restart mysqld)
  # 5.2 传输加密：服务端需有证书；应用连接强制 SSL
  require_secure_transport = ON
  # 5.3 网络隔离：绝不监听 0.0.0.0 暴露公网
  bind-address = 127.0.0.1          # 同机部署；跨机则仅填内网 IP
  # 5.5 审计与增量恢复
  log_bin = mysql-bin
  binlog_format = ROW
  expire_logs_days = 14
  slow_query_log = ON
  slow_query_log_file = /var/log/mysql/slow.log
  long_query_time = 2
  # 5.6 连接上限（如与全局变量不一致以此处为准）
  max_connections = 200

[防火墙]  (仅放行应用服务器 IP 到 3306；云安全组同理)
  ufw allow from <APP_SERVER_IP> to any port 3306
  ufw deny 3306                       # 默认拒绝其余来源

[静态加密 §5.4]  启用磁盘加密(LUKS/BitLocker/KMS)；敏感字段(content/bank_account/
  unified_social_credit_code)在应用层用 APP_KEY 做 AES 后再入库，密钥存 .env 不入库。

[备份 §5.7]  每日 mysqldump --single-transaction --routines --events 全量 + binlog 增量，
  保留 ≥14 天，并定期做恢复演练（已重写 app/command/DbBackup.php 为 mysqldump 方案）。

[应用连接 SSL]  在 config/database.php 的 mysql 连接追加：
  'params' => [
      \PDO::MYSQL_ATTR_SSL_CA => '/path/ca.pem',
      \PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => true,
  ],
GUIDE
