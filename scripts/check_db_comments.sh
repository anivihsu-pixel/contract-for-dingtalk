#!/usr/bin/env bash
# ============================================================
# 校验数据库初始化 / 迁移脚本的「表级 + 字段级」中文注释完整性
# 规则（详见 DEVELOPMENT_GUIDE.md §7）：
#   * 每张表都必须有表级中文注释：
#       - MySQL（init_mysql.php / init.sql）：闭合行 COMMENT='中文表名'
#       - SQLite（init_sqlite.php / migration_*.sql）：CREATE TABLE 内首行 `-- 表注释：中文表名——说明`
#   * 每个字段都必须在行尾附带 `-- 中文注释`（含迁移脚本的 ALTER TABLE ... ADD COLUMN 新增字段）
#   * MySQL 建库脚本（init_mysql.php / init.sql / migration_*.sql 的 CREATE TABLE）字段须同时带
#     MySQL 原生 COMMENT '中文' 子句（与行尾 `-- 注释` 并存、内容一致），使生产 MySQL 库
#     在 Navicat/DBeaver 等工具中可显示中文注释；SQLite（init_sqlite.php）不支持 COMMENT，仅源码注释
# 覆盖范围：三份初始化脚本 + database/ 下全部 migration_*.sql 增量迁移脚本
# 缺注释则打印明细并以非零状态退出，可用于发布前卡点。
# ============================================================
set -euo pipefail

DIR="$(cd "$(dirname "$0")/.." && pwd)"
cd "$DIR"

python3 - <<'PY'
import re, sys, glob

TYPES = r'(INTEGER|INT|BIGINT|SMALLINT|TINYINT|MEDIUMINT|TEXT|LONGTEXT|MEDIUMTEXT|TINYTEXT|VARCHAR|CHAR|DECIMAL|NUMERIC|FLOAT|DOUBLE|REAL|DATETIME|DATE|TIMESTAMP|BLOB|BOOLEAN|BOOL|JSON|YEAR|BINARY|VARBINARY|ENUM)'
COL_RE = re.compile(r'^\s*["`]?[\w]+["`]?\s+' + TYPES + r'\b', re.IGNORECASE)
CREATE_RE = re.compile(r'(?i)create\s+table\s+(?:if\s+not\s+exists\s+)?[`"]?(\w+)')
CONST_RE = re.compile(r'^(PRIMARY|FOREIGN|UNIQUE|KEY|INDEX|CONSTRAINT|CHECK)\b', re.IGNORECASE)
# 迁移脚本：本行含 ADD [COLUMN] 列定义即检查（覆盖多行 ALTER 续行与动态 SQL 字符串内；ADD INDEX/KEY 等非列定义行不匹配）
ADD_COL_RE = re.compile(r'(?i)(?<![-\w])add(?:\s+column)?\s+[`"]?[\w]+[`"]?\s+' + TYPES + r'\b')
# MySQL 表注释：闭合行 COMMENT='...'
TBL_COMMENT_MYSQL = re.compile(r"COMMENT='([^']+)'")
# SQLite 表注释：CREATE TABLE 内首行 `-- 表注释：中文名——说明`（表 与 ：之间允许有「注释」等字）
TBL_COMMENT_SQLITE = re.compile(r'表[^：:]*[:：]\s*(.+)$')

files = [
    'database/init_sqlite.php',
    'database/init_mysql.php',
    'database/init.sql',
] + sorted(glob.glob('database/migration_*.sql'))

total_missing_field = 0
total_missing_table = 0
total_missing_comment = 0

for fn in files:
    try:
        lines = open(fn, encoding='utf-8').read().splitlines()
    except FileNotFoundError:
        print(f"[FAIL] 找不到文件: {fn}")
        total_missing_field += 1
        continue

    # SQLite 不支持列 COMMENT，仅 MySQL 建库脚本要求字段带 MySQL 原生 COMMENT
    require_comment = 'init_sqlite.php' not in fn
    missing_field, total_field = [], 0
    missing_comment = []
    missing_table, total_table = [], 0
    in_block, cur, table_comment = False, None, None

    for i, line in enumerate(lines, 1):
        s = line.strip()
        if not in_block:
            m = CREATE_RE.search(s)
            if m:
                in_block, cur, table_comment = True, m.group(1), None
                total_table += 1
                continue
            # 迁移脚本：ADD [COLUMN] 列定义行（含多行 ALTER 续行；行内须带 -- 中文注释）
            if ADD_COL_RE.search(line):
                total_field += 1
                if '--' not in line:
                    missing_field.append((i, s[:80]))
                continue
        else:
            if s.startswith(')'):
                # 闭合行可能携带 MySQL 表注释
                mc = TBL_COMMENT_MYSQL.search(line)
                if mc:
                    table_comment = mc.group(1)
                if not table_comment:
                    missing_table.append(cur)
                in_block = False
                continue
            # SQLite 表注释：块内首条 `-- 表...` 注释
            if table_comment is None and s.startswith('--') and '表' in s:
                mm = TBL_COMMENT_SQLITE.search(s)
                if mm:
                    table_comment = mm.group(1).strip()
                continue
            if s == '' or s.startswith('--'):
                continue
            if CONST_RE.match(s):
                continue
            if COL_RE.match(line):
                total_field += 1
                if '--' not in line:
                    missing_field.append((i, s[:80]))
                elif require_comment and not re.search(r"COMMENT\s+['\"]", line, re.IGNORECASE):
                    missing_comment.append((i, s[:80]))

    ok = (not missing_field) and (not missing_table) and (not missing_comment)
    status = "OK" if ok else "FAIL"
    print(f"[{status}] {fn}: 表={total_table-len(missing_table)}/{total_table} "
          f"带表注释, 列={total_field} 缺字段={len(missing_field)} 缺表注释={len(missing_table)} 缺COMMENT={len(missing_comment)}")
    for t in missing_table:
        print(f"     表缺注释: {t}")
    for i, t in missing_field:
        print(f"     L{i}: {t}")
    for i, t in missing_comment:
        print(f"     L{i} 缺 MySQL COMMENT: {t}")

    total_missing_field += len(missing_field)
    total_missing_table += len(missing_table)
    total_missing_comment += len(missing_comment)

problems = total_missing_field + total_missing_table + total_missing_comment
if problems:
    print(f"\n校验未通过：共 {total_missing_table} 张表缺表注释、{total_missing_field} 个字段缺 `-- 中文注释`、"
          f"{total_missing_comment} 个 MySQL 字段缺 COMMENT 子句")
    sys.exit(1)
print("\n校验通过：所有表均带表级中文注释，所有字段均带 `-- 中文注释`，MySQL 建库字段均带 COMMENT 子句")
PY
