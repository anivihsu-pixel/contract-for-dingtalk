#!/bin/bash
# ============================================================
# check_frontend.sh — 前端门禁（2026-08-03 新增，08-03 扩展视图内联脚本 + 枚举直出检测）
# 目的：
#   A) 拦截「依赖 app.js 全局($ajax/esc/showToast)但初始化未 DOMContentLoaded 包裹」的回归
#      ——此类脚本若在 footer 的 app.js 之前加载且顶层直接调用，会 ReferenceError 导致功能加载不出
#      （历史 bug：contract.js 2026-07-25；notification.js / customer_pool.js 2026-08-03；
#        report/monthly.php、finance/tax.php 视图内联脚本 2026-08-03——故门禁扩展覆盖视图内联脚本）
#   B) 拦截「视图直出英文枚举」（htmlspecialchars($x['type'])）——前端禁止英文枚举上屏
#      （历史 bug：客户详情跟进记录直出 RELEASE/CLAIM 等 2026-08-03，须走 activity_type_label()）
# 用法：bash scripts/check_frontend.sh
# ============================================================
cd "$(dirname "$0")/.." || exit 1

echo "== 前端脚本加载顺序检查（依赖 \$ajax 的独立 JS + 视图内联脚本是否 DCL 防护） =="

# 用 python 精确检测：
# 1) 独立 JS：使用 $ajax 且无 DCL 防护且 IIFE 尾部有裸 load/init/render 调用
# 2) 视图内联脚本：在 footer 之前执行、顶层直接调用 $ajax/load()/init() 等、无 DCL 包裹
python3 - <<'PYEOF'
import re, glob, sys

fail = 0

def check(f, label):
    global fail
    s = open(f, encoding='utf-8').read()
    if 'DOMContentLoaded' in s or 'readyState' in s:
        print('  [OK]   ' + label + '（已 DCL 防护）')
        return
    if '$ajax' not in s:
        return
    tail = s[-200:]
    risky = bool(re.search(r'(?:^|[};])\s*(load|init|render)\([^)]*\)\s*;', tail))
    if risky:
        print('  [FAIL] ' + label + '：使用 $ajax 且无 DOMContentLoaded 防护，且 IIFE 尾部有顶层调用——若在 app.js 前加载会 ReferenceError')
        fail = 1
    else:
        print('  [OK]   ' + label + '（无顶层调用，事件/函数内使用，安全）')

# 1) 独立 JS 文件
for f in sorted(glob.glob('public/static/js/**/*.js', recursive=True)):
    if f.endswith('app.js'):
        continue
    check(f, f)

# 2) 视图内联脚本（footer 之前执行 + 顶层直接调用 $ajax/load()/init() 等 + 无 DCL）
for v in sorted(glob.glob('app/view/**/*.php', recursive=True)):
    s = open(v, encoding='utf-8').read()
    footer_pos = s.find('footer.php')
    if footer_pos < 0:
        continue
    for m in re.finditer(r'<script>(.*?)</script>', s, re.S):
        if m.end() > footer_pos:
            break
        block = m.group(1)
        if '<?' in block:   # 跳过含 PHP 的模板片段
            continue
        if 'DOMContentLoaded' in block or 'readyState' in block:
            continue       # 已有 DCL 防护
        # 拦截条件：视图内联脚本在 footer 前执行 + 深度0 顶层调用 + 脚本内使用 $ajax（fetch 不依赖 app.js 不拦）
        risky_lines = []
        depth = 0
        uses_ajax = '$ajax' in block
        for line in block.split('\n'):
            d0 = depth
            depth += line.count('{') - line.count('}')
            ls = line.strip()
            if 'function' in ls or ls.startswith('//'):
                continue
            if re.match(r'^(?:window\.)?\$ajax\(', ls) or re.match(r'^(?:load|init|render|gen\w+|refresh\w+|load\w+)\([^)]*\)\s*;?$', ls):
                if d0 == 0 and uses_ajax:
                    risky_lines.append(ls[:60])
        if risky_lines:
            print('  [FAIL] ' + v + '：视图内联脚本在 footer 前顶层调用（无 DCL）→ 若用 $ajax 会 ReferenceError：' + '; '.join(risky_lines[:2]))
            fail = 1
        else:
            print('  [OK]   ' + v + '（视图内联脚本无顶层裸调用）')

# 3) 视图枚举直出检测（v2.38.14 新增）：htmlspecialchars($x['type']) 直出枚举字段
#    ——枚举展示必须走 *_label() 中文映射（activity_type_label/audit_action_label 等），禁止英文原始码上屏
#    （历史 bug：移动/PC 客户详情跟进记录直出 RELEASE/CLAIM 等英文类型码 2026-08-03）
for v in sorted(glob.glob('app/view/**/*.php', recursive=True)):
    s = open(v, encoding='utf-8').read()
    for m in re.finditer(r"htmlspecialchars\(\$[A-Za-z_]+\[['\"]type['\"]\]", s):
        print('  [FAIL] ' + v + '：枚举字段 type 被 htmlspecialchars 直出（前端禁止英文枚举上屏）→ 改用 activity_type_label() 等中文标签函数')
        fail = 1

if fail:
    print('X 前端脚本加载顺序门禁未通过（请按 contract.js 模式补 DOMContentLoaded 包裹初始化）')
else:
    print('OK 前端脚本加载顺序门禁通过')
sys.exit(fail)
PYEOF
RET=$?
exit $RET

