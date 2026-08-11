#!/usr/bin/env bash
# ============================================================
# 三份数据库初始化脚本「表 + 字段」一致性对照校验
# ------------------------------------------------------------
# 基准（唯一事实来源）：database/init_mysql.php
# 被比对文件           ：database/init_sqlite.php
#                       database/init.sql（纯 SQL 镜像）
#
# 规则（详见 DEVELOPMENT_GUIDE.md §9）：
#   1. 三份脚本必须包含完全相同的表集合（缺表 / 多表均判为不一致）；
#   2. 每张同名表的字段集合必须完全一致（缺字段 / 多字段均判为不一致）。
#   3. 三份脚本的「种子 INSERT」列集合必须存在于对应表自己的 CREATE TABLE
#      字段中（防 v2.35.14 前出现的 `init.sql` 漏改：CREATE 已精简为 7 列、
#      INSERT 仍写 13 列旧字段 → DBA 导入即报 Unknown column）。
#   4. 同一张表在三份脚本中的种子 INSERT 列集合须 1:1 一致（跨文件漂移即判不一致）。
# 任一不一致则打印明细并以非零状态退出，用于发布前卡点，
# 防止「某文件缺字段 / 种子列引用不存在字段导致部署报错」这类问题复现。
# ============================================================
set -euo pipefail

DIR="$(cd "$(dirname "$0")/.." && pwd)"
cd "$DIR"

python3 - <<'PY'
import re, sys

# --- 与 check_db_comments.sh 相同的健壮词法（逐行状态机，兼容 SQLite 的
#     datetime('now','localtime') 括号默认值，不会被非贪婪正则误伤） ---
TYPES = (r'(INTEGER|INT|BIGINT|SMALLINT|TINYINT|MEDIUMINT|TEXT|LONGTEXT|MEDIUMTEXT|'
         r'TINYTEXT|VARCHAR|CHAR|DECIMAL|NUMERIC|FLOAT|DOUBLE|REAL|DATETIME|DATE|'
         r'TIMESTAMP|BLOB|BOOLEAN|BOOL|JSON|YEAR|BINARY|VARBINARY|ENUM)')
# 捕获列名 + 类型（列名可被反引号或双引号包裹）
COL_RE = re.compile(r'^\s*["`]?([\w]+)["`]?\s+' + TYPES + r'\b', re.IGNORECASE)
CREATE_RE = re.compile(r'(?i)create\s+table\s+(?:if\s+not\s+exists\s+)?[`"]?(\w+)')
CONST_RE = re.compile(r'^(PRIMARY|FOREIGN|UNIQUE|KEY|INDEX|CONSTRAINT|CHECK)\b',
                      re.IGNORECASE)
# 捕获 INSERT ... (列清单) VALUES：列清单不含括号（列名简单、无嵌套），
# 故用 [^)]* 精准取到首个 ) 前的列清单；VALUES 必须紧随其后（排除 INSERT...SELECT）。
INSERT_RE = re.compile(
    r'(?i)INSERT\s+(?:IGNORE\s+)?INTO\s+[`"]?(\w+)[`"]?\s*\(([^)]*)\)\s*VALUES'
)

BASELINE = 'database/init_mysql.php'
TARGETS = ['database/init_sqlite.php', 'database/init.sql']
ALL = [BASELINE] + TARGETS


def parse(fn):
    """返回 {表名: [字段名, ...]}；解析失败抛异常由上层处理。"""
    tables = {}
    lines = open(fn, encoding='utf-8').read().splitlines()
    in_block, cur = False, None
    for line in lines:
        s = line.strip()
        # 跳过空行与注释（兼容 SQL 的 --、PHP 的 // 与 #），
        # 避免注释里的 DDL 关键字（如“CREATE TABLE IF NOT EXISTS”）被误判为表。
        if s == '' or s.startswith('--') or s.startswith('//') or s.startswith('#'):
            continue
        if not in_block:
            m = CREATE_RE.search(s)
            if m:
                in_block, cur = True, m.group(1)
                tables[cur] = []
            continue
        # 块内：遇到独立右括号视为表定义结束
        if s.startswith(')'):
            in_block, cur = False, None
            continue
        if CONST_RE.match(s):
            continue
        m = COL_RE.match(line)
        if m:
            tables[cur].append(m.group(1))
    return tables


def parse_inserts(fn):
    """返回 {表名: [[列, ...], ...]}：每个 INSERT 语句的列清单（已去反引号/引号）。"""
    inserts = {}
    text = open(fn, encoding='utf-8').read()
    for m in INSERT_RE.finditer(text):
        tbl = m.group(1)
        cols = [c.strip().strip('`"') for c in m.group(2).split(',') if c.strip()]
        inserts.setdefault(tbl, []).append(cols)
    return inserts


# --- 解析三份文件 ---
parsed = {}
insert_parsed = {}
for fn in ALL:
    try:
        parsed[fn] = parse(fn)
        insert_parsed[fn] = parse_inserts(fn)
    except FileNotFoundError:
        print(f"[FAIL] 找不到文件: {fn}")
        sys.exit(2)

base = parsed[BASELINE]
base_tables = set(base)
problems = 0

print(f"基准文件: {BASELINE}  表数={len(base_tables)}  "
      f"字段总数={sum(len(v) for v in base.values())}")
print("-" * 60)

for fn in TARGETS:
    t = parsed[fn]
    t_tables = set(t)
    n_fields = sum(len(v) for v in t.values())
    miss_tables = sorted(base_tables - t_tables)   # 相对基准缺失的表
    extra_tables = sorted(t_tables - base_tables)  # 相对基准多出的表

    field_diffs = []
    for tbl in sorted(base_tables & t_tables):
        bcols, tcols = set(base[tbl]), set(t[tbl])
        miss = sorted(bcols - tcols)   # 缺字段
        extra = sorted(tcols - bcols)  # 多字段
        if miss or extra:
            field_diffs.append((tbl, miss, extra))

    ok = not (miss_tables or extra_tables or field_diffs)
    status = "OK" if ok else "FAIL"
    print(f"[{status}] {fn}: 表数={len(t_tables)} 字段总数={n_fields}")
    if miss_tables:
        print(f"     缺表({len(miss_tables)}): {', '.join(miss_tables)}")
    if extra_tables:
        print(f"     多表({len(extra_tables)}): {', '.join(extra_tables)}")
    for tbl, miss, extra in field_diffs:
        if miss:
            print(f"     [{tbl}] 缺字段: {', '.join(miss)}")
        if extra:
            print(f"     [{tbl}] 多字段: {', '.join(extra)}")
    if not ok:
        problems += 1

# --- 规则 3：种子 INSERT 列必须存在于对应表自己的 CREATE TABLE 字段中 ---
print("-" * 60)
print("== 种子 INSERT 列 vs CREATE TABLE 字段校验 ==")
intra = 0
for fn in ALL:
    tbl_create = parsed[fn]
    ins = insert_parsed[fn]
    for tbl, collists in ins.items():
        if tbl not in tbl_create:
            # 该表在本文件无 CREATE（可能由迁移脚本建表），跳过避免误报
            continue
        create_cols = set(tbl_create[tbl])
        for cols in collists:
            bad = [c for c in cols if c not in create_cols]
            if bad:
                intra += 1
                print(f"     [{tbl}] {fn}: INSERT 列在 CREATE 中不存在: {', '.join(bad)}")
if intra:
    problems += intra
    print(f"     → {intra} 处 INSERT 引用了不存在的列，已计入校验未通过。")
else:
    print("     ✓ 所有种子 INSERT 列均存在于对应 CREATE TABLE。")

# --- 规则 4：同一张表的种子 INSERT 列集合在三份脚本间 1:1 一致 ---
print("-" * 60)
print("== 种子 INSERT 列 跨文件 1:1 对照 ==")
insert_sets = {}
for fn in ALL:
    for tbl, collists in insert_parsed[fn].items():
        acc = insert_sets.setdefault(tbl, {}).setdefault(fn, set())
        for cols in collists:
            acc.update(cols)
cross = 0
for tbl in sorted(insert_sets):
    fmap = insert_sets[tbl]
    if len(fmap) < 2:
        continue
    ref_fn = BASELINE if BASELINE in fmap else next(iter(fmap))
    ref_set = fmap[ref_fn]
    for fn, cols in fmap.items():
        if fn == ref_fn:
            continue
        if cols != ref_set:
            cross += 1
            print(f"     [{tbl}] INSERT 列跨文件不一致: {ref_fn}={sorted(ref_set)} vs {fn}={sorted(cols)}")
if cross:
    problems += cross
    print(f"     → {cross} 处跨文件 INSERT 列不一致，已计入校验未通过。")
else:
    print("     ✓ 各表种子 INSERT 列在三份脚本间一致。")

print("-" * 60)
if problems:
    print(f"校验未通过：{problems} 项与基准 {BASELINE} 不一致，请补齐后再发布。")
    sys.exit(1)
print("校验通过：三份初始化脚本「表 + 字段 + 种子 INSERT 列」与基准完全一致。")
PY
