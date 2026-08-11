#!/usr/bin/env bash
# ========================================================================
# backup.sh — 完整备份（代码快照 + 数据库）
# 作用：一次生成同一时间戳的「代码快照」与「数据库备份」，用于发布前/变更前留底。
#   - 代码：tar.gz 到 backups/code/
#   - 数据库：调用 php think db:backup（SQLite VACUUM INTO / MySQL mysqldump），产物在 runtime/backup/
# 用法：
#   bash scripts/backup.sh              # 完整备份，DB 默认保留最近 14 份
#   bash scripts/backup.sh --keep=30    # DB 保留最近 30 份
# ========================================================================
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$ROOT_DIR"

KEEP=14
for a in "$@"; do
  case "$a" in
    --keep=*) KEEP="${a#--keep=}" ;;
    *) echo "未知参数: $a"; exit 2 ;;
  esac
done

STAMP="$(date +%Y%m%d_%H%M%S)"
CODE_DIR="$ROOT_DIR/backups/code"
mkdir -p "$CODE_DIR"

# ---- 1. 代码快照 ----
CODE_PKG="$CODE_DIR/code_${STAMP}.tar.gz"
echo "== 代码快照 =="
tar --disable-copyfile \
  --exclude='./.env' \
  --exclude='./.git' \
  --exclude='./runtime/*' \
  --exclude='./releases' \
  --exclude='./backups' \
  --exclude='./node_modules' \
  -czf "$CODE_PKG" -C "$ROOT_DIR" . 2>/dev/null
echo "✓ 代码备份: backups/code/$(basename "$CODE_PKG") ($(du -h "$CODE_PKG" | awk '{print $1}'))"

# ---- 2. 数据库备份（复用既有 CLI）----
echo "== 数据库备份（php think db:backup）=="
PHP_BIN="${PHP_BIN:-/Users/fengjian/bin/php}"
command -v "$PHP_BIN" >/dev/null 2>&1 || PHP_BIN="php"
"$PHP_BIN" think db:backup --keep="$KEEP" || echo "⚠ DB 备份命令返回非 0，请检查数据库配置"

echo ""
echo "✓ 完整备份完成（时间戳 $STAMP）"
echo "  代码: backups/code/  |  数据库: runtime/backup/"
