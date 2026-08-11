#!/usr/bin/env bash
# ========================================================================
# rollback.sh — 回滚到指定发布版本
# 作用：从 releases/ 选择一个历史发布包，回滚代码。
#   安全措施：
#     1) 回滚前自动对「当前代码」做一次备份（backups/code/pre_rollback_*.tar.gz）
#     2) 仅覆盖代码，不动 .env 与 runtime/data（数据库需单独用备份恢复，脚本会提示）
# 用法：
#   bash scripts/rollback.sh            # 列出可回滚版本
#   bash scripts/rollback.sh v2.21.0    # 回滚到指定版本
#   bash scripts/rollback.sh --file=releases/legacy/contract-dingtalk-v2.16.tar.gz
# ========================================================================
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$ROOT_DIR"

RELEASES_DIR="$ROOT_DIR/releases"

list_versions() {
  echo "== 可回滚的发布包 =="
  local found=0
  for f in "$RELEASES_DIR"/*.tar.gz "$RELEASES_DIR"/legacy/*.tar.gz; do
    [ -e "$f" ] || continue
    found=1
    printf "  %-45s %s\n" "$(basename "$f")" "$(du -h "$f" | awk '{print $1}')"
  done
  [ "$found" -eq 0 ] && echo "  （无发布包，请先运行 scripts/release.sh）"
}

TARGET_FILE=""
TARGET_VER=""
for a in "$@"; do
  case "$a" in
    --file=*) TARGET_FILE="${a#--file=}" ;;
    v*)       TARGET_VER="$a" ;;
    *) echo "未知参数: $a"; exit 2 ;;
  esac
done

# 无参数 → 仅列出
if [ -z "$TARGET_FILE" ] && [ -z "$TARGET_VER" ]; then
  list_versions
  echo ""
  echo "用法: bash scripts/rollback.sh <版本号如 v2.21.0>  或  --file=<包路径>"
  exit 0
fi

# 解析目标包
if [ -z "$TARGET_FILE" ]; then
  # 按版本号在 releases 与 legacy 中查找
  for cand in "$RELEASES_DIR/contract-dingtalk-${TARGET_VER}.tar.gz" \
              "$RELEASES_DIR/legacy/contract-dingtalk-${TARGET_VER}.tar.gz"; do
    [ -e "$cand" ] && { TARGET_FILE="$cand"; break; }
  done
fi
if [ -z "$TARGET_FILE" ] || [ ! -e "$TARGET_FILE" ]; then
  echo "✗ 找不到目标发布包: ${TARGET_VER:-$TARGET_FILE}"
  echo ""
  list_versions
  exit 1
fi

echo "目标回滚包: $TARGET_FILE"
echo "⚠ 回滚将用该包内容覆盖当前代码（.env 与 runtime/data 保留不动）。"
read -r -p "确认继续？(yes/no) " ans
[ "$ans" = "yes" ] || { echo "已取消。"; exit 0; }

# ---- 1. 回滚前备份当前代码 ----
STAMP="$(date +%Y%m%d_%H%M%S)"
PRE_DIR="$ROOT_DIR/backups/code"
mkdir -p "$PRE_DIR"
PRE_PKG="$PRE_DIR/pre_rollback_${STAMP}.tar.gz"
echo "== 回滚前备份当前代码 =="
tar --disable-copyfile \
  --exclude='./.env' --exclude='./.git' \
  --exclude='./runtime/*' \
  --exclude='./releases' --exclude='./backups' --exclude='./node_modules' \
  -czf "$PRE_PKG" -C "$ROOT_DIR" . 2>/dev/null
echo "✓ 当前代码已备份: backups/code/$(basename "$PRE_PKG")"

# ---- 2. 解包覆盖 ----
echo "== 解包回滚 =="
tar -xzf "$TARGET_FILE" -C "$ROOT_DIR"
echo "✓ 代码已回滚到: $(basename "$TARGET_FILE")"

echo ""
echo "后续手动步骤："
echo "  1) 若该版本数据库结构不同，请用 runtime/backup/ 中对应时间点的备份恢复数据库。"
echo "     SQLite:  cp runtime/backup/contract_<时间戳>.db runtime/data/contract.db"
echo "     MySQL :  mysql -u<user> -p <db> < runtime/backup/contract_<时间戳>.sql"
echo "  2) 检查 .env 是否与该版本兼容；必要时执行 composer install。"
echo "  3) 回滚失败可用 backups/code/$(basename "$PRE_PKG") 恢复。"
