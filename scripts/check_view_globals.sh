#!/usr/bin/env bash
# ============================================================
# 校验「视图公共 JS 符号」是否被正确声明在全局（而非 $tab 分支内）
# 规则（详见 DEVELOPMENT_GUIDE.md §9 发布清单）：
#   多 tab 共用的全局变量 / 函数（如 allRoles / allUsers / flowCats / esc）
#   必须声明在所有 $tab 分支之外的「公共脚本块」，否则访问其他 tab 时
#   该符号缺失 → 运行时 ReferenceError → 弹窗打不开 / 按钮点不动。
#   历史回归：v2.31.0 把公共符号误声明在仅「审批流」tab 渲染的脚本块，
#   导致「用户管理」tab 下角色选择 / 编辑点击失效（v2.31.1 修复）。
#   本脚本作为发布前卡点，从根上防止同类问题再发生。
# 白名单：内置默认 + 可选 scripts/public_view_globals.txt（每行一个符号，# 开头注释）。
# ============================================================
set -euo pipefail

DIR="$(cd "$(dirname "$0")/.." && pwd)"
cd "$DIR"

python3 - <<'PY'
import re, sys, glob, os

ROOT = os.getcwd()

# ---- 公共符号白名单（应在全局声明，禁止落在 $tab 分支内）----
DEFAULT_PUBLIC = {'allRoles', 'allUsers', 'flowCats', 'esc'}

wl_file = os.path.join(ROOT, 'scripts', 'public_view_globals.txt')
public = set(DEFAULT_PUBLIC)
if os.path.exists(wl_file):
    with open(wl_file, encoding='utf-8') as f:
        for line in f:
            s = line.strip()
            if s and not s.startswith('#'):
                public.add(s)

files = sorted(glob.glob(os.path.join(ROOT, 'app', 'view', '**', '*.php'), recursive=True))

# 顶层声明提取（行首无缩进视为顶层 JS 声明；函数内声明有缩进会被跳过）
decl_re = re.compile(r'(?:let|const|var)\s+([A-Za-z_$][\w$]*)')
func_re = re.compile(r'function\s+([A-Za-z_$][\w$]*)')
tab_open_re = re.compile(r'(?:if|elseif)\s*\([^)\n]*\$tab')
tab_end_re = re.compile(r'endif\s*;')

violations = []
scanned = 0
for fn in files:
    try:
        lines = open(fn, encoding='utf-8').read().splitlines()
    except Exception as e:
        print(f"! 无法读取 {fn}: {e}")
        continue
    scanned += 1
    tab_depth = 0          # 当前未闭合的 $tab 分支层数
    in_script = False
    script_buf = []        # 脚本块内逐行
    script_start = 0
    script_in_tab = False

    for i, line in enumerate(lines, 1):
        # 追踪 $tab 分支层数（else 不增减，承接当前分支）
        tab_depth += len(tab_open_re.findall(line))
        tab_depth -= len(tab_end_re.findall(line))
        if tab_depth < 0:
            tab_depth = 0

        if not in_script:
            if '<script' in line and '</script>' not in line:
                in_script = True
                script_buf = []
                script_start = i
                script_in_tab = (tab_depth > 0)
            # 单行 <script>...</script> 极罕见，跳过
            continue

        if '</script>' in line:
            # 处理该脚本块中「声明在 $tab 分支内」的顶层符号
            declared = set()
            for bl in script_buf:
                if bl[:1] not in (' ', '\t'):
                    declared.update(decl_re.findall(bl))
                    declared.update(func_re.findall(bl))
            # 结束行中 </script> 之前的片段也可能含声明
            pre = line.split('</script>')[0]
            for pl in pre.split('\n'):
                if pl[:1] not in (' ', '\t'):
                    declared.update(decl_re.findall(pl))
                    declared.update(func_re.findall(pl))
            if script_in_tab:
                bad = sorted(declared & public)
                for sym in bad:
                    violations.append((fn, script_start, sym))
            in_script = False
            script_buf = []
        else:
            script_buf.append(line)

if violations:
    print("✗ 发现应在「全局」声明的公共符号被声明在 $tab 分支内的 <script> 块：")
    for fn, ln, sym in violations:
        print(f"    {fn}:{ln}  符号 '{sym}' 落在 $tab 分支内（白名单要求全局声明）")
    print(f"  共 {len(violations)} 处违规。请将这些符号上提到所有 tab 之外声明的公共脚本块。")
    sys.exit(1)
else:
    print(f"✓ 视图公共全局变量声明检查通过（扫描 {scanned} 个视图文件，公共符号白名单 {sorted(public)}）")
    sys.exit(0)
PY
