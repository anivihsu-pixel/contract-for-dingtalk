#!/bin/bash
# ============================================================
# check_dead_entry.sh — 页面入口可达性门禁（2026-08-03 新增）
# 目的：拦截「页面级路由已注册但侧边栏/移动端均无入口」的死功能回归
#       （历史：相对方 360、应收账龄、客户生命周期漏斗均为此类——完整实现
#        但全站无链接，用户无法发现。已 3 次出现，沉淀为门禁）
# 用法：bash scripts/check_dead_entry.sh
# ============================================================
cd "$(dirname "$0")/.." || exit 1

echo "== 页面入口可达性检查（路由注册 vs 侧边栏/移动端入口） =="

python3 - <<'PYEOF'
import re, sys

# 1) 解析路由表：只取"页面级 GET 路由"（非 ajax 组、无参数、非 redirect）
s = open('route/app.php', encoding='utf-8').read()
routes = re.findall(r"Route::get\(\s*'([^']+)'", s)
routes += re.findall(r'Route::get\(\s*"([^"]+)"', s)
page_routes = set(r for r in routes
                  if not r.startswith(('ajax/', 'export/')) and '<' not in r
                  and r not in ('/login', '/logout', '/', '/m'))
# 去掉显式 redirect 的旧链接（template/mobile 等）
redirected = set(re.findall(r"Route::get\(\s*'([^']+)'[\s\S]{0,80}?redirect\(", s))
page_routes -= redirected

# 2) 收集入口：PC 侧边栏 + 移动端工作台/更多页 + 所有业务页内导航（含控制器 modules 的 /m/... 入口）
sidebar = set(re.findall(r'href="(/[a-z][a-z0-9_\-/?&=]*)', open('app/view/layout/sidebar.php', encoding='utf-8').read()))
mobile_links = set()
for f in ['app/view/mobile/index.php', 'app/view/mobile/more.php']:
    mobile_links |= set(re.findall(r'href="(/[a-z][a-z0-9_\-/?&=]*)', open(f, encoding='utf-8').read()))
mc = open('app/controller/MobileController.php', encoding='utf-8').read()
mobile_links |= set(re.findall(r"/m/[a-z][a-z0-9_\-/]*", mc))
# 页内导航（业务页内按钮/链接，如 finance 页"经营月报"）
inner_links = set()
import glob as _g
for f in _g.glob('app/view/**/*.php', recursive=True):
    inner_links |= set(re.findall(r'href="(/[a-z][a-z0-9_\-/?&=]*)', open(f, encoding='utf-8').read()))

ALL_ENTRIES = sidebar | mobile_links | inner_links

def covered(path):
    for e in ALL_ENTRIES:
        e_clean = e.split('?')[0]
        # 直接匹配 / 前缀匹配 / m 前缀匹配
        if e_clean == path or e_clean.startswith(path + '/') or path.startswith(e_clean + '/'):
            return True
        if e_clean == '/m' + path:
            return True
    return False

# 白名单：内部/子视图（由其他页面加载，非独立入口）
ALLOW = {'/preview', '/profile/change-password', '/dingtalk/entry', '/m/login',
         '/admin/template', '/admin/invoice-form'}  # admin tab 内渲染（发票表单=form-builder 历史路径）
dead = sorted(p for p in page_routes if p.startswith('/') and not covered(p) and p not in ALLOW)

if dead:
    print('  [FAIL] 以下页面路由无任何入口（侧边栏/移动端均无链接）——死功能：')
    for p in dead:
        print('    ' + p)
    print('  请补入口（侧边栏或移动端加链接）或用 --force 跳过')
    sys.exit(1)
else:
    print('  OK 全部页面路由均有入口')
    sys.exit(0)
PYEOF
RET=$?
exit $RET
