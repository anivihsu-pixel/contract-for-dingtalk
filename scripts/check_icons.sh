#!/usr/bin/env bash
# ========================================================================
# check_icons.sh — 图标子集白名单门禁（v2.43.0 新增）
# 作用：
#   1) 全站静态引用 `bi bi-xxx` 的图标类必须都在白名单（scripts/icons_whitelist.txt），
#      新增图标未补白名单即失败——防止子集字体缺失字形显示空白；
#   2) 子集 CSS 必须包含白名单全部图标规则，防 CSS 与白名单漂移。
# 说明：附件类型动态拼接值域（file-earmark-pdf/image/text、filetype-*、file-word 等）
#       以及业务/错误页动态值域（customer_detail 收付款方向、admin 字典页 $labels、
#       error/500.html 错误页）由白名单文件「动态拼接值域」区人工维护（grep 抓不到拼接字符串）。
# 用法：bash scripts/check_icons.sh
# ========================================================================
set -euo pipefail
cd "$(dirname "$0")/.."

WL="scripts/icons_whitelist.txt"
CSS="public/static/vendor/bootstrap-icons/bootstrap-icons.v2.43.2.min.css"
err=0

if [ ! -f "$CSS" ]; then
  echo "✗ 未找到子集 CSS: $CSS"; exit 1
fi

# ---- 1) 静态引用校验：全站 `bi bi-xxx` 类必须命中白名单 ----
# 注意：`bi bi-` 前可能有换行/多空格，用 grep -oE 提取类名并去重；
#       扫描范围含整个 app/（视图 + common.php/form 等 PHP 代码生成处，底部 Tab 图标在 common.php）；
#       过滤以连字符结尾的拼接残缺（如 `bi-file-earmark-<?=` 动态模板，动态值域由白名单人工维护）
missing=$(grep -rhoE 'bi[[:space:]]+bi-[a-z0-9-]+' app public/static/js 2>/dev/null \
  | sed -E 's/.*bi-([a-z0-9-]+).*/\1/' | sort -u \
  | grep -vE '.*-$' \
  | grep -vxFf <(grep -vE '^#|^[[:space:]]*$' "$WL") || true)
if [ -n "$missing" ]; then
  echo "✗ 以下图标未在白名单（新增图标须补 $WL）:"
  echo "$missing"
  err=1
fi

# ---- 2) 子集 CSS 完整性：白名单每个图标必须在子集 CSS 中有 ::before 规则 ----
css_missing=$(grep -vE '^#|^[[:space:]]*$' "$WL" | while read -r ic; do
  grep -q "\.bi-${ic}::before" "$CSS" || echo "$ic"
done)
if [ -n "$css_missing" ]; then
  echo "✗ 子集 CSS 缺少以下白名单图标规则（需重新生成子集: php scripts/generate_icons_subset.php + fontTools.subset）:"
  echo "$css_missing"
  err=1
fi

if [ "$err" -eq 0 ]; then
  echo "✓ 图标白名单门禁通过（$(grep -vcE '^#|^[[:space:]]*$' "$WL") 个图标）"
fi
exit "$err"
