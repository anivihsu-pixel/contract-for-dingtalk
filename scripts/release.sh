#!/usr/bin/env bash
# ========================================================================
# release.sh — 版本发布 / 打包
# 作用：
#   1) 校验 VERSION.md 与 CHANGELOG.md 顶部版本号一致（防版本追踪错位）
#   2) 生成可部署的代码快照 tar.gz 到 releases/
#   3) 写入 MANIFEST.txt（版本 / 时间 / 校验和 / 内容摘要），便于回滚与审计
#
# 用法：
#   bash scripts/release.sh                 # 自动读取 VERSION.md 的当前版本
#   bash scripts/release.sh v2.21.1         # 指定版本（须与 VERSION/CHANGELOG 一致）
#   bash scripts/release.sh --force         # 跳过版本一致性校验（不推荐）
# ========================================================================
set -euo pipefail

# 定位项目根目录（scripts 的上一级）
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$ROOT_DIR"

RELEASES_DIR="$ROOT_DIR/releases"
MANIFEST="$RELEASES_DIR/MANIFEST.txt"
mkdir -p "$RELEASES_DIR"

FORCE=0
SKIP_TESTS=0
ARG_VER=""
for a in "$@"; do
  case "$a" in
    --force) FORCE=1 ;;
    --skip-tests) SKIP_TESTS=1 ;;
    v*)      ARG_VER="$a" ;;
    *)       echo "未知参数: $a"; exit 2 ;;
  esac
done

# ---- 提取版本号 ----
# VERSION.md: 形如 "## 当前版本：v2.21.1（2026-07-15）"
VER_FROM_VERSION="$(grep -oE '当前版本：v[0-9]+\.[0-9]+(\.[0-9]+)?' VERSION.md | head -1 | grep -oE 'v[0-9]+\.[0-9]+(\.[0-9]+)?' || true)"
# CHANGELOG.md: 形如 "## v2.21.1 (2026-07-15) — ..."
VER_FROM_CHANGELOG="$(grep -oE '^## v[0-9]+\.[0-9]+(\.[0-9]+)?' CHANGELOG.md | head -1 | grep -oE 'v[0-9]+\.[0-9]+(\.[0-9]+)?' || true)"

echo "VERSION.md   顶部版本 : ${VER_FROM_VERSION:-<未识别>}"
echo "CHANGELOG.md 顶部版本 : ${VER_FROM_CHANGELOG:-<未识别>}"

# ---- 一致性校验 ----
if [ "$FORCE" -ne 1 ]; then
  if [ -z "$VER_FROM_VERSION" ] || [ -z "$VER_FROM_CHANGELOG" ]; then
    echo "✗ 无法从 VERSION.md / CHANGELOG.md 识别版本号，请检查格式（或 --force 跳过）"; exit 1
  fi
  if [ "$VER_FROM_VERSION" != "$VER_FROM_CHANGELOG" ]; then
    echo "✗ 版本不一致：VERSION.md=$VER_FROM_VERSION 但 CHANGELOG.md=$VER_FROM_CHANGELOG"
    echo "  请先同步两个文件的顶部版本号后再发布（这正是历史版本追踪错位的根因）。"; exit 1
  fi
fi

VERSION="${ARG_VER:-$VER_FROM_VERSION}"
if [ -z "$VERSION" ]; then echo "✗ 未确定发布版本号"; exit 1; fi
if [ -n "$ARG_VER" ] && [ "$FORCE" -ne 1 ] && [ "$ARG_VER" != "$VER_FROM_VERSION" ]; then
  echo "✗ 指定版本 $ARG_VER 与 VERSION.md 的 $VER_FROM_VERSION 不一致"; exit 1
fi
echo "✓ 版本一致性校验通过：$VERSION"

# ---- 将版本号写入 config/version.php（运行时直接读取，根治部署后 VERSION.md 缺失导致的 'unknown'） ----
echo "== 写入 config/version.php =="
cat > "$ROOT_DIR/config/version.php" <<'PHPEOF'
<?php
// 当前系统版本号（由 scripts/release.sh 打包时自动写入，请勿手动编辑！）
// 部署后系统侧栏底部、「系统配置→当前版本」统一展示此值。
// 优先级高于 VERSION.md：发布包内始终存在该文件，不依赖根目录文件部署位置。
return 'VERSION_PLACEHOLDER';
PHPEOF
# 替换占位符（heredoc 以 'PHPEOF' 引用，变量不展开，需 sed 二次写入实际版本号）
sed -i '' "s/VERSION_PLACEHOLDER/$VERSION/" "$ROOT_DIR/config/version.php" 2>/dev/null || \
  sed -i "s/VERSION_PLACEHOLDER/$VERSION/" "$ROOT_DIR/config/version.php"
echo "  ✓ config/version.php → $VERSION"

# ---- 语法体检（发布前最后一道闸）----
PHP_BIN="${PHP_BIN:-/Users/fengjian/bin/php}"
command -v "$PHP_BIN" >/dev/null 2>&1 || PHP_BIN="php"
echo "== php -l 全量语法体检 =="
lint_err=0
while IFS= read -r f; do
  out="$("$PHP_BIN" -l "$f" 2>&1)" || true
  echo "$out" | grep -q "No syntax errors" || { echo "✗ 语法错误: $f"; echo "$out"; lint_err=1; }
done < <(find app config route -name "*.php" 2>/dev/null)
[ "$lint_err" -eq 0 ] && echo "✓ 语法体检通过" || { echo "✗ 存在语法错误，发布中止"; exit 1; }

# ---- 数据库脚本一致性闸门（发布前卡点）----
# 1) 三份初始化脚本「表+字段」须与基准 init_mysql.php 完全一致（防缺字段部署报错）
# 2) 所有字段须带 `-- 中文注释`（项目开发规约 §7）
if [ "$FORCE" -ne 1 ]; then
  echo "== 数据库脚本「表+字段」一致性对照 =="
  bash "$SCRIPT_DIR/check_schema_parity.sh" || { echo "✗ 三份初始化脚本字段不一致，发布中止（--force 可跳过）"; exit 1; }
  echo "== 数据库字段中文注释完整性 =="
  bash "$SCRIPT_DIR/check_db_comments.sh"   || { echo "✗ 存在字段缺中文注释，发布中止（--force 可跳过）"; exit 1; }
  # 视图公共全局变量声明检查：防止公共 JS 符号被误声明在 $tab 分支内（v2.31.0 回归防护）
  echo "== 视图公共全局变量声明检查 =="
  bash "$SCRIPT_DIR/check_view_globals.sh"  || { echo "✗ 存在应在全局声明的公共符号被声明在 \$tab 分支内，发布中止（--force 可跳过）"; exit 1; }
  # 前端脚本加载顺序检查（v2.38.9 新增）：拦截「依赖 $ajax 的独立 JS 无 DCL 防护 + 顶层调用」回归
  # （历史：contract.js 07-25、notification/customer_pool.js 08-03 同类 bug 反复，沉淀为门禁）
  echo "== 前端脚本加载顺序检查 =="
  bash "$SCRIPT_DIR/check_frontend.sh"      || { echo "✗ 存在依赖 \$ajax 但未 DCL 防护的脚本，发布中止（--force 可跳过）"; exit 1; }
  # 页面入口可达性检查（2026-08-03 新增）：拦截「页面路由已注册但侧边栏/移动端/页内均无入口」死功能回归
  # （历史：相对方 360、应收账龄、客户生命周期漏斗——完整实现但全站无链接，沉淀为门禁）
  echo "== 页面入口可达性检查 =="
  bash "$SCRIPT_DIR/check_dead_entry.sh"    || { echo "✗ 存在无入口的页面路由（死功能），发布中止（--force 可跳过）"; exit 1; }
  # 图标子集白名单检查（v2.43.0 新增）：拦截「新增图标未补白名单 → 子集字体缺字形空白」与「子集 CSS 漂移」
  echo "== 图标子集白名单检查 =="
  bash "$SCRIPT_DIR/check_icons.sh"         || { echo "✗ 图标白名单校验失败（新增图标须补 scripts/icons_whitelist.txt），发布中止（--force 可跳过）"; exit 1; }
else
  echo "⚠ 已 --force：跳过数据库一致性 / 注释校验 / 视图全局变量检查"
fi

# ---- 自动化测试门禁（固化 P1-1 测试基线，发布前最后一道闸）----
# 运行 scripts/test.sh：全量 PHPUnit 用例必须全绿；失败则中止打包。
# 仅在 --force 或 --skip-tests 时跳过（--skip-tests 会失去发布前回归保障，不推荐）。
if [ "$FORCE" -ne 1 ] && [ "${SKIP_TESTS:-0}" -ne 1 ]; then
  echo "== 自动化测试门禁（bash scripts/test.sh）=="
  bash "$SCRIPT_DIR/test.sh" || { echo "✗ 自动化测试未通过，发布中止（--skip-tests 可跳过，不推荐）"; exit 1; }
  echo "✓ 自动化测试通过"
elif [ "${SKIP_TESTS:-0}" -eq 1 ]; then
  echo "⚠ 已 --skip-tests：跳过自动化测试门禁"
fi

# ---- 打包 ----
STAMP="$(date +%Y%m%d_%H%M%S)"
PKG="contract-dingtalk-${VERSION}.tar.gz"
PKG_PATH="$RELEASES_DIR/$PKG"

# 是否将演示数据（仿真库 + seed 脚本 + 演示文档）打入发布包
# v2.44.0 起默认关闭（用户要求：出包不携带演示库，演示数据仅在开发/测试环境加载）——
# 纯生产包不含 runtime/data/contract.db、seed_demo.php、demo.env.example。
# 需要演示包时显式执行 DEMO_DATA=1 bash scripts/release.sh。
DEMO_DATA="${DEMO_DATA:-0}"

# 公共排除项（敏感文件 / 运行时产物 / 发布与版本控制目录）
# 交付文档（CHANGELOG/DEPLOY/DINGTALK_SSO_GUIDE/VERSION 等 md）不随 gz 打包，仅在 zip 交付层提供
EXCLUDES=(
  --exclude='./.env'
  --exclude='./.env.bak'
  --exclude='./.env.bak2'
  --exclude='./.env.*'
  # v2.44.0 起演示数据（seed 脚本 + 演示配置模板）不随源码树进包：
  # DEMO_DATA=1 时打包前显式注入（见下），DEMO_DATA=0 纯生产包彻底无演示数据。
  --exclude='./database/seed_demo.php'
  --exclude='./demo.env.example'
  --exclude='./.git'
  --exclude='./releases'
  --exclude='./backups'
  --exclude='./node_modules'
  --exclude='./phpunit.phar'
  --exclude='*.DS_Store'
  --exclude='./runtime'
  --exclude='./CHANGELOG.md'
  --exclude='./DEPLOY.md'
  --exclude='./DEPLOY_ZERO_DOWNTIME.md'
  --exclude='./DINGTALK_SSO_GUIDE.md'
  --exclude='./VERSION.md'
)

# 内部研发 / 设计 / 审查 / 审计报告：非客户交付物，一律不进发布包（多为英文标题、随仓库沉淀）
DOC_EXCLUDES=(
  --exclude='./DESIGN_*.md'
  --exclude='./DEV_PLAN_*.md'
  --exclude='./DEVELOPMENT_GUIDE.md'
  --exclude='./FIX_PLAN_*.md'
  --exclude='./MOBILE_*.md'
  --exclude='./P2_VERIFICATION.md'
  --exclude='./PRODUCT_AUDIT_*.md'
  --exclude='./PRODUCT_REVIEW_*.md'
  --exclude='./REVIEW_*.md'
  --exclude='./ROADMAP.md'
  --exclude='./TEST_REPORT_*.md'
  --exclude='./ASSESSMENT_APPROVAL_OPT_*.md'
  --exclude='./PM审查报告_*.md'
  --exclude='./AUDIT_*.html'
  --exclude='./AUDIT_*.md'
  --exclude='./MIGRATION_SQLITE_TO_MYSQL.md'
  --exclude='./DEPLOY_DEMO.md'
  --exclude='./README_DEMO.txt'
  # v2.43.0 出包审查补齐：内部开发/测试/审查产物一律不进发布包
  # （此前 Windows 手工打包只排除 .env/node_modules/runtime/交付 md，导致 outputs 审查报告、
  #   tests 单测、e2e 回归脚本、临时文件混入交付包）
  --exclude='./outputs'
  --exclude='./tests'
  --exclude='./e2e_*.py'
  --exclude='./e2e_*.js'
  --exclude='./loginpage.html'
  --exclude='./watchdog.ps1'
  --exclude='./setup-firewall.bat'
  --exclude='./package.json'
  --exclude='./package-lock.json'
  --exclude='./phpunit.xml.dist'
  --exclude='./合同类型'
)

# 交付文档：仓库内源文件名 -> 包内中文化文件名（仅这些随包交付）
DELIVER_DOCS=(
  "CHANGELOG.md:迭代日志.md"
  "DEPLOY.md:部署说明.md"
  "DEPLOY_ZERO_DOWNTIME.md:零停机部署说明.md"
  "DINGTALK_SSO_GUIDE.md:钉钉免登配置说明.md"
  "VERSION.md:版本记录.md"
)

# 构建待发布文件树（排除敏感/运行时/内部研发文档/交付文档；交付 md 仅进 zip 交付层，不随 gz）
build_staging() {
  local STG="$1"
  rm -rf "$STG"; mkdir -p "$STG"
  tar --disable-copyfile "${EXCLUDES[@]}" "${DOC_EXCLUDES[@]}" -cf - -C "$ROOT_DIR" . \
    | tar --disable-copyfile -xf - -C "$STG"
  # 同步修正 deploy.sh 内的文档引用（交付文档在 zip 层，文件名已中文化）
  local XREF="s/DEPLOY_ZERO_DOWNTIME\.md/零停机部署说明.md/g; s/DINGTALK_SSO_GUIDE\.md/钉钉免登配置说明.md/g; s/DEPLOY_DEMO\.md/部署说明.md/g; s/README_DEMO\.txt/部署说明.md/g; s/CHANGELOG\.md/迭代日志.md/g; s/VERSION\.md/版本记录.md/g; s/DEPLOY\.md/部署说明.md/g"
  [ -f "$STG/scripts/deploy.sh" ] && sed -i '' -E "$XREF" "$STG/scripts/deploy.sh"
  echo "$STG"
}

STAGING="$(mktemp -d)"
build_staging "$STAGING"

if [ "$DEMO_DATA" != "0" ] && [ -f "$ROOT_DIR/runtime/data/contract.db" ]; then
  echo "== 打包 $PKG (默认含演示数据) =="
  # 注入仿真数据库（单文件 SQLite；复制前做一次 checkpoint 保证跨请求一致）
  if command -v sqlite3 >/dev/null 2>&1; then
    sqlite3 "$ROOT_DIR/runtime/data/contract.db" "PRAGMA wal_checkpoint(TRUNCATE);" 2>/dev/null || true
  fi
  mkdir -p "$STAGING/runtime/data"
  cp "$ROOT_DIR/runtime/data/contract.db" "$STAGING/runtime/data/"
  mkdir -p "$STAGING/runtime"/{cache,log,session,temp}
  # 注入演示 seed 脚本与配置模板（演示说明已并入 部署说明.md）
  [ -f "$ROOT_DIR/database/seed_demo.php" ] && cp "$ROOT_DIR/database/seed_demo.php" "$STAGING/database/"
  [ -f "$ROOT_DIR/demo.env.example" ] && cp "$ROOT_DIR/demo.env.example" "$STAGING/"
  tar --disable-copyfile --exclude='*.DS_Store' -czf "$PKG_PATH" -C "$STAGING" .
  echo "  ✓ 已注入演示数据：runtime/data/contract.db + seed_demo.php + demo.env.example（演示说明见 部署说明.md）"
else
  echo "== 打包 $PKG (纯生产包，不含演示数据) =="
  tar --disable-copyfile --exclude='*.DS_Store' -czf "$PKG_PATH" -C "$STAGING" .
  [ "$DEMO_DATA" = "0" ] && echo "  （DEMO_DATA=0：已跳过演示数据）" \
                          || echo "  （未找到 runtime/data/contract.db，按纯包处理）"
fi
rm -rf "$STAGING"

SIZE="$(du -h "$PKG_PATH" | awk '{print $1}')"
# 校验和（macOS 用 shasum，Linux 用 sha256sum）
if command -v shasum >/dev/null 2>&1; then SHA="$(shasum -a 256 "$PKG_PATH" | awk '{print $1}')";
elif command -v sha256sum >/dev/null 2>&1; then SHA="$(sha256sum "$PKG_PATH" | awk '{print $1}')";
else SHA="(无校验工具)"; fi

GIT_COMMIT="$( [ -d .git ] && git rev-parse --short HEAD 2>/dev/null || echo 'N/A' )"

# ---- 写 MANIFEST ----
{
  echo "----------------------------------------"
  echo "版本      : $VERSION"
  echo "打包时间  : $(date '+%Y-%m-%d %H:%M:%S')"
  echo "文件      : $PKG"
  echo "大小      : $SIZE"
  echo "SHA256    : $SHA"
  echo "git commit: $GIT_COMMIT"
  echo "演示数据  : $([ "$DEMO_DATA" != "0" ] && echo '已包含' || echo '未包含')"
} >> "$MANIFEST"

echo ""
echo "✓ 发布完成"
echo "  包路径   : releases/$PKG ($SIZE)"
echo "  SHA256   : $SHA"
echo "  清单     : releases/MANIFEST.txt"
echo ""
echo "提示：发布包默认含仿真数据库（runtime/data/contract.db）与演示配置模板 demo.env.example，客户解包两条命令即可预览；"
echo "      已排除 .env（含密钥）。需要纯生产包时执行 DEMO_DATA=0 bash scripts/release.sh。"

# ---- 桌面交付（仅本地开发机存在桌面时自动生成交付文件夹）----
# 约定：桌面生成「合同管理系统_vX.Y.Z」文件夹（不含日期），内含
#       tar.gz + MANIFEST.txt
#       + 中文化交付文档：迭代日志.md / 部署说明.md / 零停机部署说明.md / 钉钉免登配置说明.md / 版本记录.md
#       + 演示配置模板 demo.env.example（演示说明见 部署说明.md）
# 服务器（无桌面）自动跳过，不报错。
DESKTOP_DIR="$HOME/Desktop"
if [ -d "$DESKTOP_DIR" ]; then
  DELIVERY="$DESKTOP_DIR/合同管理系统_${VERSION}"
  # 重建交付目录，避免历史版本残留的英文旧文档累积（仅删我们自建的交付文件夹）
  rm -rf "$DELIVERY"
  mkdir -p "$DELIVERY"
  cp "$PKG_PATH" "$DELIVERY/"
  cp "$MANIFEST" "$DELIVERY/"
  # 交付文档：md 不随 gz 打包，由仓库英文原稿改名复制到交付层（zip 内），并同步版本号
  for entry in "${DELIVER_DOCS[@]}"; do
    src="${entry%%:*}" dst="${entry##*:}"
    if [ -f "$ROOT_DIR/$src" ]; then
      cp "$ROOT_DIR/$src" "$DELIVERY/$dst"
      sed -i '' -E "s/v[0-9]+\.[0-9]+\.[0-9]+/$VERSION/g" "$DELIVERY/$dst" 2>/dev/null || true
    fi
  done
  # 演示配置模板：仅演示包（DEMO_DATA=1）随桌面交付，纯生产包不携带（v2.44.0 起）
  if [ "$DEMO_DATA" != "0" ]; then
    cp demo.env.example "$DELIVERY/" 2>/dev/null
  fi
  # v2.40.7：桌面交付为文件夹（不打包 zip；交付目录内含 tar.gz + MANIFEST + 中文化文档 + demo.env.example）
  echo "✓ 桌面交付目录: $DELIVERY（文件夹交付，不打包 zip）"
else
  echo "（无桌面环境，跳过桌面交付）"
fi
