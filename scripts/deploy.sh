#!/usr/bin/env bash
# ========================================================================
# deploy.sh — 零停机部署 / 回滚 / 状态查询（symlink 模式）
#
# 架构：
#   DEPLOY_ROOT/
#   ├── current -> releases/20260727-133000/    # 符号链接，Web 服务器 DocumentRoot = current/public/
#   ├── releases/
#   │   ├── 20260727-120000/                    # 旧版本（保留用于回滚）
#   │   └── 20260727-133000/                    # 当前版本
#   └── shared/                                 # 跨版本共享（不随代码更新覆盖）
#       ├── .env                                #   敏感配置
#       ├── runtime/                            #   缓存/日志/SQLite 数据库
#       └── public/uploads/                     #   用户上传附件
#
# 用法：
#   bash scripts/deploy.sh deploy  <包路径>     # 部署新版本（零停机）
#   bash scripts/deploy.sh rollback [N]         # 回滚到最近第 N 个版本（默认 1=上一版）
#   bash scripts/deploy.sh status               # 查看部署状态（当前版本 / 历史列表）
#   bash scripts/deploy.sh clean [--keep=N]     # 清理旧版本（默认保留最近 5 个）
#
# 完整使用说明（首次部署 / 后续更新 / 回滚 / 迁移自动化 / 故障排查）见
#   DEPLOY_ZERO_DOWNTIME.md（随发布包提供，位于发布包解压后根目录）
#
# 前置条件（仅需执行一次）：
#   - Web 服务器 DocumentRoot 指向 DEPLOY_ROOT/current/public/
#   - DEPLOY_ROOT/shared/ 下的 .env 已配置
#   - PHP CLI 可用于运行迁移脚本
#
# ========================================================================
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"

# ---- 配置（可按实际部署路径修改）----
DEPLOY_ROOT="${DEPLOY_ROOT:-/var/www/contract}"   # 部署根目录
SHARED_DIR="$DEPLOY_ROOT/shared"                   # 共享文件目录
RELEASES_DIR="$DEPLOY_ROOT/releases"               # 版本目录
CURRENT_LINK="$DEPLOY_ROOT/current"                # 当前版本符号链接
KEEP="${KEEP:-5}"                                  # 默认保留版本数

# ---- 工具函数 ----
red()    { echo -e "\033[31m$*\033[0m"; }
green()  { echo -e "\033[32m$*\033[0m"; }
yellow() { echo -e "\033[33m$*\033[0m"; }

die() { red "✗ $*"; exit 1; }

# 列出所有版本（按时间倒序）
list_releases() {
  if [ -d "$RELEASES_DIR" ]; then
    ls -1t "$RELEASES_DIR" 2>/dev/null || true
  fi
}

# 获取当前版本名（从 current 符号链接解析）
current_release() {
  if [ -L "$CURRENT_LINK" ]; then
    basename "$(readlink "$CURRENT_LINK")" 2>/dev/null || echo "(无法解析)"
  else
    echo "(未部署)"
  fi
}

# 获取当前版本中的版本号（从 config/version.php 读取）
current_version() {
  local target="$CURRENT_LINK/config/version.php"
  if [ -f "$target" ]; then
    php -r "echo include '$target';" 2>/dev/null || echo "?"
  else
    echo "?"
  fi
}

# ========================================================================
# 子命令：status — 查看当前部署状态
# ========================================================================
cmd_status() {
  echo "部署根目录 : $DEPLOY_ROOT"
  echo "当前版本   : $(current_release)  (系统版本 $(current_version))"
  echo ""
  echo "历史版本（最近 10 个）："
  local count=0
  local cur="$(current_release)"
  while IFS= read -r rel; do
    [ -z "$rel" ] && continue
    count=$((count + 1))
    local marker=" "
    [ "$rel" = "$cur" ] && marker="→"
    local ver="?"
    [ -f "$RELEASES_DIR/$rel/config/version.php" ] && ver="$(php -r "echo include '$RELEASES_DIR/$rel/config/version.php';" 2>/dev/null || echo '?')"
    printf "  %s %-22s  %s\n" "$marker" "$rel" "$ver"
    [ $count -ge 10 ] && break
  done < <(list_releases)

  echo ""
  echo "共享文件 :"
  if [ -d "$SHARED_DIR" ]; then
    for d in .env runtime public/uploads; do
      if [ -e "$SHARED_DIR/$d" ]; then
        echo "  ✓ $d"
      else
        echo "  ✗ $d（缺失）"
      fi
    done
  else
    echo "  （shared/ 目录尚未初始化，首次部署时会自动创建）"
  fi
}

# ========================================================================
# 子命令：deploy <包路径> — 零停机部署新版本
# ========================================================================
cmd_deploy() {
  local pkg="$1"

  [ -f "$pkg" ] || die "包文件不存在: $pkg"
  echo "部署包     : $pkg"

  # 1. 确保目录结构
  mkdir -p "$RELEASES_DIR" "$SHARED_DIR"

  # 2. 首次部署：初始化 shared 目录
  if [ ! -f "$SHARED_DIR/.env" ]; then
    yellow "⚠ shared/.env 不存在，将使用包内 .env（首次部署后务必手动配置生产环境密钥）"
  fi

  # 3. 解压到时间戳目录
  local stamp="$(date +%Y%m%d-%H%M%S)"
  local target="$RELEASES_DIR/$stamp"
  mkdir -p "$target"
  echo "解压到     : $target"
  tar -xzf "$pkg" -C "$target" || die "解压失败，请检查包完整性"
  echo "  ✓ 已解压 ($(du -sh "$target" | awk '{print $1}'))"

  # 4. 处理共享文件（首次部署时从包内复制，后续部署时建立符号链接）
  _setup_shared "$target"

  # 5. 运行数据库迁移（如果存在）
  _run_migrations "$target"

  # 6. 原子替换 current 符号链接（零停机关键步骤）
  echo "切换符号链接…"
  ln -sfn "$target" "$CURRENT_LINK"
  echo "  ✓ current → $stamp"

  # 7. 清理旧版本
  _cleanup

  local new_ver="$(current_version)"
  green "✓ 部署完成：$(current_release)  (系统版本 $new_ver)"
  echo ""
  echo "如需回滚：bash scripts/deploy.sh rollback"
}

# ---- 内部：设置共享文件 ----
_setup_shared() {
  local target="$1"

  # .env — 敏感配置，首次从包复制，后续保持 shared 版本
  if [ -f "$SHARED_DIR/.env" ]; then
    rm -f "$target/.env"
    ln -sfn "$SHARED_DIR/.env" "$target/.env"
    echo "  ✓ .env → shared/"
  else
    if [ -f "$target/.env" ]; then
      cp "$target/.env" "$SHARED_DIR/.env"
      rm -f "$target/.env"
      ln -sfn "$SHARED_DIR/.env" "$target/.env"
      yellow "  ⚠ .env 已从包内复制到 shared/（请编辑 shared/.env 配置生产环境密钥后重新部署）"
    else
      yellow "  ⚠ 包内无 .env，跳过（请手动创建 shared/.env）"
    fi
  fi

  # runtime/ — 缓存/日志/数据库，跨版本保持
  if [ -d "$SHARED_DIR/runtime" ]; then
    rm -rf "$target/runtime"
    ln -sfn "$SHARED_DIR/runtime" "$target/runtime"
    echo "  ✓ runtime/ → shared/"
  else
    if [ -d "$target/runtime" ]; then
      mv "$target/runtime" "$SHARED_DIR/runtime"
      ln -sfn "$SHARED_DIR/runtime" "$target/runtime"
      echo "  ✓ runtime/ 已迁移到 shared/"
    else
      mkdir -p "$SHARED_DIR/runtime"/{cache,log,session,temp,data}
      ln -sfn "$SHARED_DIR/runtime" "$target/runtime"
      echo "  ✓ runtime/ 已初始化（空）→ shared/"
    fi
  fi

  # public/uploads/ — 用户上传附件，跨版本保持
  if [ -d "$SHARED_DIR/public/uploads" ]; then
    rm -rf "$target/public/uploads"
    ln -sfn "$SHARED_DIR/public/uploads" "$target/public/uploads"
    echo "  ✓ public/uploads/ → shared/"
  else
    if [ -d "$target/public/uploads" ]; then
      mv "$target/public/uploads" "$SHARED_DIR/public/uploads"
      ln -sfn "$SHARED_DIR/public/uploads" "$target/public/uploads"
      echo "  ✓ public/uploads/ 已迁移到 shared/"
    else
      mkdir -p "$SHARED_DIR/public/uploads"
      ln -sfn "$SHARED_DIR/public/uploads" "$target/public/uploads"
      echo "  ✓ public/uploads/ 已初始化（空）→ shared/"
    fi
  fi
}

# ---- 内部：运行数据库迁移 ----
_run_migrations() {
  local target="$1"
  local migration_dir="$target/database"

  # 查找增量迁移脚本（v2.35.4_perm_version.sql 等）
  local migrations=()
  if [ -d "$migration_dir" ]; then
    while IFS= read -r f; do
      migrations+=("$f")
    done < <(find "$migration_dir" -maxdepth 1 -name "migration_*.sql" | sort)
  fi

  if [ ${#migrations[@]} -eq 0 ]; then
    echo "  （无增量迁移脚本，跳过）"
    return
  fi

  echo "数据库迁移  : ${#migrations[@]} 个脚本"
  for m in "${migrations[@]}"; do
    local name="$(basename "$m")"
    echo -n "  执行 $name … "

    # 确认目标数据库类型
    local db_type="${DB_TYPE:-mysql}"

    if [ "$db_type" = "sqlite" ]; then
      local db_path="$SHARED_DIR/runtime/data/contract.db"
      if [ -f "$db_path" ]; then
        sqlite3 "$db_path" < "$m" 2>&1 && echo "✓" || { echo "✗（已跳过或失败）"; }
      else
        echo "⚠（SQLite 数据库不存在）"
      fi
    else
      # MySQL：从 shared/.env 读取连接信息
      if [ -f "$SHARED_DIR/.env" ]; then
        local db_host="$(grep -oP 'DB_HOST\s*=\s*\K.*' "$SHARED_DIR/.env" | tr -d '"' | tr -d "'" || echo '127.0.0.1')"
        local db_name="$(grep -oP 'DB_NAME\s*=\s*\K.*' "$SHARED_DIR/.env" | tr -d '"' | tr -d "'" || echo '')"
        local db_user="$(grep -oP 'DB_USER\s*=\s*\K.*' "$SHARED_DIR/.env" | tr -d '"' | tr -d "'" || echo '')"
        local db_pass="$(grep -oP 'DB_PASS\s*=\s*\K.*' "$SHARED_DIR/.env" | tr -d '"' | tr -d "'" || echo '')"
        if [ -n "$db_name" ] && [ -n "$db_user" ]; then
          MYSQL_PWD="$db_pass" mysql -h "$db_host" -u "$db_user" "$db_name" < "$m" 2>&1 && echo "✓" || echo "✗（已跳过或失败）"
        else
          echo "⚠（无法从 .env 解析 MySQL 连接信息，请手动执行：mysql < $m）"
        fi
      else
        yellow "  ⚠ shared/.env 不存在，跳过 MySQL 迁移（请手动执行）"
      fi
    fi
  done
}

# ---- 内部：清理旧版本 ----
_cleanup() {
  local keep="${1:-$KEEP}"
  local releases=()
  while IFS= read -r rel; do
    [ -z "$rel" ] && continue
    releases+=("$rel")
  done < <(list_releases)

  if [ ${#releases[@]} -le "$keep" ]; then
    return
  fi

  echo "清理旧版本（保留最近 $keep 个）…"
  for ((i = keep; i < ${#releases[@]}; i++)); do
    local old="$RELEASES_DIR/${releases[$i]}"
    rm -rf "$old"
    echo "  ✗ 已删除 ${releases[$i]}"
  done
}

# ========================================================================
# 子命令：rollback [N] — 回滚到上一个版本（N=1）或前第 N 个
# ========================================================================
cmd_rollback() {
  local n="${1:-1}"
  local releases=()
  while IFS= read -r rel; do
    [ -z "$rel" ] && continue
    releases+=("$rel")
  done < <(list_releases)

  local cur="$(current_release)"

  if [ ${#releases[@]} -lt $((n + 1)) ]; then
    die "历史版本不足（共 ${#releases[@]} 个，无法回滚到前第 $n 个）"
  fi

  local target_name="${releases[$n]}"
  local target_dir="$RELEASES_DIR/$target_name"

  [ -d "$target_dir" ] || die "目标版本目录不存在: $target_dir"

  echo "当前版本   : $cur  ($(current_version))"
  # 读取目标版本号
  local target_ver="?"
  if [ -f "$target_dir/config/version.php" ]; then
    target_ver="$(php -r 'echo include $argv[1];' "$target_dir/config/version.php" 2>/dev/null || echo '?')"
  fi
  echo "回滚目标   : $target_name  ($target_ver)"

  # 原子替换符号链接（零停机回滚）
  ln -sfn "$target_dir" "$CURRENT_LINK"
  green "✓ 回滚完成：current → $target_name"
}

# ========================================================================
# 子命令：clean [--keep=N] — 清理旧版本
# ========================================================================
cmd_clean() {
  local keep="$KEEP"
  for a in "$@"; do
    case "$a" in
      --keep=*) keep="${a#*=}" ;;
    esac
  done
  _cleanup "$keep"
  echo "✓ 清理完成（保留最近 $keep 个版本）"
}

# ========================================================================
# 主入口
# ========================================================================
case "${1:-}" in
  deploy)
    [ $# -ge 2 ] || die "用法: bash scripts/deploy.sh deploy <包路径.tar.gz>"
    cmd_deploy "$2"
    ;;
  rollback)
    cmd_rollback "${2:-1}"
    ;;
  status)
    cmd_status
    ;;
  clean)
    shift
    cmd_clean "$@"
    ;;
  *)
    echo "用法:"
    echo "  bash scripts/deploy.sh deploy  <包路径.tar.gz>   部署新版本（零停机）"
    echo "  bash scripts/deploy.sh rollback [N]              回滚到最近第 N 个版本（默认 1）"
    echo "  bash scripts/deploy.sh status                    查看部署状态"
    echo "  bash scripts/deploy.sh clean [--keep=N]          清理旧版本（默认保留 5 个）"
    echo ""
    echo "环境变量（可选）："
    echo "  DEPLOY_ROOT    部署根目录（默认 /var/www/contract）"
    echo "  DB_TYPE        数据库类型（默认 mysql；可选 sqlite）"
    echo "  KEEP           清理时保留版本数（默认 5）"
    exit 1
    ;;
esac
