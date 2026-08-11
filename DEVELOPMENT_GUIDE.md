# 合同管理系统 — 开发规范（Development Guide）

> 目的：统一版本管理、发布打包、备份回滚流程，杜绝历史上出现过的"代码已 v2.21、版本文件仍 v2.19.2"版本追踪错位。
> 适用：所有参与本项目开发/发布/运维的成员。

---

## 1. 版本号规范（语义化版本 SemVer）

版本号格式 `vMAJOR.MINOR.PATCH`（前缀 `v`）：

| 段位 | 递增时机 | 示例 |
|---|---|---|
| MAJOR | 不兼容的重大架构调整（如数据库引擎更换、路由体系重构） | v2 → v3 |
| MINOR | 新增功能、模块（向后兼容） | v2.20 → v2.21 |
| PATCH | 修复 bug、安全加固、性能优化（不新增功能） | v2.21 → v2.21.1 |

- 全量代码审查修复归 **PATCH**（如本轮 F1–F4 → v2.21.1）。
- 一个功能迭代归 **MINOR**（如审批流程自动化 → v2.21）。

---

## 2. 版本文件同步规范（强约束）

**每次发布前，以下两个文件的顶部版本号必须一致，否则 `release.sh` 会拒绝打包：**

- `VERSION.md` —— 顶部 `## 当前版本：vX.Y.Z（YYYY-MM-DD）`
- `CHANGELOG.md` —— 顶部 `## vX.Y.Z (YYYY-MM-DD) — 一句话摘要`

规则：
1. **改代码 → 同一次提交里同步更新 CHANGELOG.md 顶部条目 + VERSION.md 当前版本**。二者缺一不可。
2. CHANGELOG 只增不改历史条目；新版本追加在最顶部。
3. 版本号不可跳跃遗漏（历史曾从 v2.19.2 直接跳到 v2.21 漏掉 v2.20，已补回）。
4. 发布脚本 `scripts/release.sh` 自动比对两文件版本号，不一致直接中止——这是防错位的最后一道闸。

---

## 3. 发布 / 打包流程

```bash
# 1) 确认 VERSION.md 与 CHANGELOG.md 顶部版本号已同步
# 2) 一键发布（自动校验版本一致性 + php -l 体检 + 打包 + 写 MANIFEST）
bash scripts/release.sh
# 或指定版本（须与 VERSION.md 一致）
bash scripts/release.sh v2.21.1
```

`release.sh` 行为：
- 校验 `VERSION.md` / `CHANGELOG.md` 版本号一致（不一致中止；`--force` 可跳过，不推荐）。
- 全量 `php -l` 语法体检（`app/ config/ route/`），有错中止。
- 生成 `releases/contract-dingtalk-vX.Y.Z.tar.gz`。
- 追加记录到 `releases/MANIFEST.txt`（版本 / 时间 / 大小 / SHA256 / git commit）。

**发布包内容约定：**
- ✅ 含：`app/ config/ route/ public/ database/ vendor/ scripts/ index.php composer.json` 及文档、`.env.example`。
- ❌ 不含：`.env`（含密钥）、`runtime/`（运行时/日志/会话/**数据库文件**）、`releases/`、`backups/`、`node_modules/`、`.git/`。
- 部署方用 `.env.example` 生成 `.env`，用 `database/init_sqlite.php` 初始化库或从备份恢复，不随包携带运行数据库（避免覆盖生产数据）。

---

## 4. 备份规范

### 4.1 手动完整备份（发布前/重大变更前）
```bash
bash scripts/backup.sh            # 代码快照 + 数据库备份
bash scripts/backup.sh --keep=30  # 数据库保留最近 30 份
```
- 代码快照 → `backups/code/code_<时间戳>.tar.gz`
- 数据库 → 复用 `php think db:backup`，产物在 `runtime/backup/`

### 4.2 数据库定时备份（crontab）
```bash
# 每日 02:30 备份，保留最近 14 份
30 2 * * * cd /path/to/contract-review && php think db:backup --keep=14
```
- SQLite：`VACUUM INTO` 在线一致性快照（3.27+），失败回退文件拷贝。
- MySQL：`mysqldump --single-transaction` 热备不锁表。

---

## 5. 回滚流程

```bash
bash scripts/rollback.sh                 # 列出所有可回滚版本（releases/ + releases/legacy/）
bash scripts/rollback.sh v2.21.0         # 回滚到指定版本
bash scripts/rollback.sh --file=releases/legacy/contract-dingtalk-v2.16.tar.gz
```

安全措施：
1. 回滚前**自动备份当前代码**到 `backups/code/pre_rollback_<时间戳>.tar.gz`（回滚失败可还原）。
2. 仅覆盖代码，**不动 `.env` 与 `runtime/data`**。
3. 若目标版本数据库结构不同，需**手动**用 `runtime/backup/` 中对应时间点的备份恢复数据库（脚本会打印命令）。

---

## 6. 目录约定

| 目录 | 用途 | 是否入库 |
|---|---|---|
| `scripts/` | 运维脚本（release / backup / rollback） | ✅ |
| `releases/` | 发布包 + `MANIFEST.txt` | 目录入库，`*.tar.gz` 不入库 |
| `releases/legacy/` | 历史归档包（v2.14/15/16 等旧快照） | 同上 |
| `backups/code/` | 代码备份 / 回滚前快照 | ❌ |
| `runtime/` | 运行时（日志/会话/缓存/数据库文件） | ❌ |
| `database/` | 初始化脚本、迁移脚本、验收脚本 | ✅ |
| `tests/` | 验收/回归测试脚本 | ✅ |

---

## 7. 数据库表 + 字段中文注释约定（强制）

**适用范围**：所有「表结构定义」文件——`database/init_sqlite.php`、`database/init_mysql.php` 与 `database/init.sql` 三份初始化脚本，以及 `database/` 下全部 `migration_*.sql` 增量迁移脚本。前三者表结构须 **1:1 对齐**（当前均为 **32 张表 / 335 个字段**，以 `scripts/check_schema_parity.sh` 实测为准），字段注释亦须一一对应；迁移脚本新增的表须带表级注释、`ALTER TABLE ... ADD [COLUMN]` 新增的字段须带字段级 `-- 中文注释`。表+字段一致性校验详见 §7.1。

**硬性规则**：
- **表级注释（强制）**：每张表都必须有中文表名注释，说明表的业务含义：
  - MySQL（`init_mysql.php` / `init.sql`）：在 `CREATE TABLE` 闭合行用 `COMMENT='中文表名'`（如 `) ENGINE=InnoDB ... COMMENT='合同主表'`）。
  - SQLite（`init_sqlite.php` / `migration_*.sql`）：SQLite 不支持表级 `COMMENT`，须在 `CREATE TABLE` 内**首行**写 `-- 表注释：中文表名——一句话说明`（如 `-- 表注释：合同主表——合同核心信息`）。该注释是 SQL 注释，随 DDL 一同执行、可逐表比对。
- **字段级注释（强制）**：每个 `CREATE TABLE` 的**每一个字段**，必须在行尾附带 `-- 中文注释`，说明字段含义；枚举 / 格式类字段须把可选值写进注释（如 `状态：ACTIVE/DONE/ARCHIVED`）。
- **MySQL 原生 COMMENT（强制，仅 MySQL 脚本）**：`init_mysql.php`、`init.sql` 与 `migration_*.sql` 的 `CREATE TABLE` 字段定义须同时带 MySQL 原生 `COMMENT '中文'` 子句（内容与行尾 `-- 注释` 一致），二者并存——`-- 注释` 保证源码可读，`COMMENT` 存入 MySQL `information_schema`，使 Navicat/DBeaver 等数据库工具中可显示中文注释。SQLite（`init_sqlite.php`）内核不支持列 COMMENT，仅源码 `-- 注释` 可见。
- `init_mysql.php` **不允许**用 MySQL 原生 `COMMENT '...'` 替代字段的 `-- 中文注释`。两者须并存（2026-08-10 起全部字段已同时具备），行尾 `--` 注释与 `COMMENT` 子句均为硬性要求——保证两份脚本阅读体验一致、可逐字段比对、降低接手成本。
- 新建表：全员遵守，表级 + 所有字段一次性补注释（MySQL 字段含 `COMMENT` 子句）。
- 修改已有表 / 新增字段：**必须**为新增字段补 `-- 中文注释`（MySQL 脚本新增字段同时补 `COMMENT '...'` 子句）；若新增表，还须补表级注释。
- 迁移脚本（`migration_*.sql`）：`ALTER TABLE ... ADD [COLUMN]` 新增字段须在行尾补 `-- 中文注释`（可保留 MySQL 原生 `COMMENT '...'`，行尾 `--` 为硬性要求）；`CREATE TABLE` 新表须补表级注释（MySQL 闭合行 `COMMENT='中文表名'` 或 SQLite 首行 `-- 表注释：`）。

示例：
```sql
"CREATE TABLE demo (
    -- 表注释：演示表——用于说明注释规范
    id INTEGER PRIMARY KEY AUTOINCREMENT,          -- 主键
    name TEXT NOT NULL DEFAULT '',                 -- 名称
    owner_id INTEGER DEFAULT 0,                    -- 归属人ID
    status TEXT DEFAULT 'ACTIVE',                  -- 状态：ACTIVE/DONE/ARCHIVED
    created_at TEXT DEFAULT (datetime('now','localtime')), -- 创建时间
)"
```

**存量补齐记录**：
- v2.20：`init_sqlite.php` 全量 262 字段补齐。
- v2.21.1：`init_mysql.php` 补齐（按 `表+字段` 从 `init_sqlite.php` 1:1 复用 `--` 注释）。
- **2026-07-16 复核**：发现 `init_mysql.php` 仍有 5 个字段误用 `COMMENT '...'`（status ×3、data_scope、nodes）而缺 `--` 行尾注释，已统一改为 `-- 中文注释` 形式。现两份脚本均为 **262/262 字段带 `-- 中文注释`、0 缺失**。
- **2026-07-16（表级注释）**：`init_sqlite.php` 26 张表补齐 `-- 表注释：` 首行注释；`migration_v2.2.sql` 3 张表补齐表级 + 字段级注释；`check_db_comments.sh` 升级为同时校验表级注释。现四份文件 **81 张表均带表级注释、所有字段带 `-- 中文注释`、0 缺失**。
- **2026-08-10（迁移脚本注释全覆盖）**：全量复核 `database/migration_*.sql` 增量迁移脚本——5 张迁移新表补 `-- 表注释：` 首行（customer_activity / invoice_form_field / form_field_linkage / notification / role_dept），25 处 `ADD [COLUMN]` 字段补 `--` 行尾注释（含 7 处多行 `ALTER TABLE` 续行字段，如 v2.31 category_list/use_amount、v2.40.0 stage/progress，此前校验仅覆盖四份核心脚本、迁移脚本缺注释未被拦截）；`check_db_comments.sh` 扩展为覆盖三份 init 脚本 + 全部 `migration_*.sql` 的新表与新增字段，ADD 正则匹配任意位置的 `ADD [COLUMN]` 列定义行（含多行 ALTER 续行与动态 SQL 字符串内，`ADD INDEX/KEY` 等非列定义行不参与）。现全部数据库脚本 **表级注释与字段 `-- 注释` 0 缺失**。
- **2026-08-10（MySQL 字段 COMMENT 子句补齐）**：生产 MySQL 部署后数据库工具（Navicat/DBeaver）中字段无中文注释——根因是 `init_mysql.php`/`init.sql` 字段仅带 SQL 注释 `-- 中文`（不存入数据库），缺 MySQL 原生 `COMMENT '...'` 子句（2026-07-16 曾将仅有的 5 处 COMMENT 误改为 `--` 形式）。已为 init_mysql.php 335 字段（含 2 个 JSON 注释含转义引号 `\"` 的字段手动补齐）与 init.sql 335 字段、迁移脚本 CREATE TABLE 块 71 字段（v2.2 33 + 新表 38）全部补 `COMMENT` 子句，内容与行尾 `-- 注释` 一致；`check_db_comments.sh` 新增「MySQL 建库字段须带 COMMENT」检查（init_sqlite.php 除外）。MySQL 全库字段 `COMMENT` 0 缺失、700+ 处 COMMENT 字符串引号配对静态校验通过。

**校验**（发布前可运行，`release.sh` 已内置为发布卡点；缺表注释或字段注释均非零退出；已覆盖三份 init 脚本 + 全部 `migration_*.sql`）：
```bash
bash scripts/check_db_comments.sh
```

---

## 7.1 三份初始化脚本「表+字段」对照机制（强制）

**背景**：曾出现 `init.sql` 相对基准缺 7 张表 + 18 个字段，导致以该文件建库时部署报错。为杜绝此类「字段缺失」问题复现，建立三文件对照卡点。

**唯一事实来源（基准）**：`database/init_mysql.php`。任何表结构变更**先改基准**，再同步到另外两份。

| 文件 | 用途 | 与基准的关系 |
| --- | --- | --- |
| `database/init_mysql.php` | 生产 MySQL 初始化（PHP 执行） | **基准** |
| `database/init_sqlite.php` | 本地开发 SQLite 初始化（PHP 执行） | 按「表+字段」1:1 对齐 |
| `database/init.sql` | 纯 SQL 版 MySQL 镜像（DBA 直接导入） | 基准的 1:1 SQL 镜像 |

**硬性规则**：
- 三份脚本必须包含**完全相同的表集合**（26 张），缺表 / 多表均为不合规。
- 每张同名表的**字段集合必须完全一致**，缺字段 / 多字段均为不合规。
- 新增 / 修改字段时，**三份文件须在同一次提交内同步更新**。

**校验**（发布前运行，任一不一致则非零退出；`release.sh` 已内置为发布卡点）：
```bash
bash scripts/check_schema_parity.sh
```
> 解析采用逐行状态机（复用 `check_db_comments.sh` 的健壮词法），可正确处理 SQLite 的 `datetime('now','localtime')` 括号默认值，不会误判。

---

## 7.2 前端展示规范：禁止英文枚举直出（强制，2026-08-03）

> 背景：移动端客户详情跟进记录直出 `RELEASE`、`INACTIVE` 等英文原始码（2026-08-03 用户反馈）——活动类型码、状态码等枚举值直接 `htmlspecialchars($x['type'])` 上屏。

**硬规则：**

1. **枚举字段展示必须走中文标签函数/映射，禁止直接输出原始码**。
   - 活动类型：`activity_type_label($type)`（app/common.php，覆盖 CLAIM/TRANSFER/RELEASE/NOTE，未命中回退中文「跟进」）
   - 审计动作：`audit_action_labels()` / 对应 label 函数
   - 审批状态/动作：`approval_status_label()` / `approval_action_label()`
   - 业务字典：`dict('xxx', $key)`（**注意：dict 未命中会回退原值 $key——若原值可能是英文枚举，须自行回退中文**，如 supplier 列表类型回退「其他」）
2. **`htmlspecialchars($x['type'])` 直出 = 门禁 FAIL**（check_frontend.sh 已内置检测），须改用上述函数。
3. **写日志/跟进内容（content）时禁止夹带英文枚举**（如「沉睡/INACTIVE」），直接写中文。
4. 新增枚举类型（活动类型等）时：逻辑层写码 + 公共 label 函数映射 + 双端视图走函数，三处同步；不要在前端视图里 new 一个映射数组绕过公共函数。

---

## 8. 敏感信息与安全规范

- `.env` **禁止入库、禁止打入发布包**（已在 `.gitignore` 与打包排除规则中固化）。
- 生产密钥用 `php database/generate_secrets.php` 生成随机强值（`APP_KEY` / `JWT_SECRET`）。
- 生产 `APP_DEBUG=false`（默认值已加固为 false，见 v2.21.1 F1）。
- HTTP 状态码语义：拒绝类响应须返回对应状态码（未登录 401、无权限/CSRF 403），不可依赖 `json()` 默认 200（见 v2.21.1 F4）。

---

## 9. 发布前检查清单（Checklist）

- [ ] 功能自测 / 回归通过
- [ ] `VERSION.md` 与 `CHANGELOG.md` 顶部版本号已同步且一致
- [ ] CHANGELOG 顶部条目描述本次改动（含修复项编号如 F1–F4）
- [ ] `php -l` 全量语法体检通过（`release.sh` 会自动执行）
- [ ] `bash scripts/check_db_comments.sh` 通过（三份 init 脚本 + 全部 migration_*.sql 均带表级中文注释、所有字段带 `-- 中文注释`）
- [ ] `bash scripts/check_schema_parity.sh` 通过（三份 init 脚本「表+字段」与基准 `init_mysql.php` 完全一致）
- [ ] `bash scripts/check_view_globals.sh` 通过（视图公共全局变量/函数未声明在 `$tab` 分支内，防跨 tab 缺失回归）
- [ ] 已执行 `bash scripts/backup.sh` 留底
- [ ] `bash scripts/release.sh` 成功生成发布包与 MANIFEST 记录
- [ ] 生产 `.env` 的 `APP_DEBUG=false`、密钥非默认值

---

## 10. 架构优化 Backlog（待评估）

> 本节记录已落地但不追求一步到位的轻量优化、以及暂缓项，供后续版本评估是否推进。
> 背景：v2.28.2 架构评估结论「暂不前后端分离」，以下优化均为零架构改动的渐进式改善。

### 10.1 视图层 Db::name 残留下沉 ✅ 已完成（v2.28.2）
- 4 处视图内联 `Db::name` 已下沉到 Logic：`contract/detail.php`（→ `ApprovalLogic::getFlowById` + `CompanyLogic::getById`）、`template/index.php`（→ `ApprovalLogic::getEnabledFlows`）、`admin/index.php`（→ `AdminLogic::getDicts`）。
- 现状：视图层 `Db::` 调用清零（仅注释提及），分层铁律巩固。

### 10.2 内联 JS 抽离到独立文件 ⏸ 部分完成，剩余暂缓（v2.28.2）
- **已抽离（低风险纯 JS，无 PHP 变量耦合）**：`template/index.php`→ `static/js/template.js`、`company/index.php`→ `static/js/company.js`、`resource/index.php`→ `static/js/resource.js`（含 `window.__RES_CAN_MANAGE` 桥接）。共约 150 行。
- **暂缓（重耦合块，待评估后再决定）**：剩余约 1900 行内联 JS，集中在 `mobile/contract_form.php`(386)、`contract/create.php`(375)、`admin/index.php`(244) 及 10+ 个 `mobile/*` 页面。这些 JS 大量直接读 PHP 变量/模板输出，抽离须先在视图留 `window.__PAGE__ = <?=json_encode(...)>` 桥接再搬逻辑，属风险改造。
- **决策（2026-07-18）**：剩余块**现在不值得强行抽离**——关键共享文件（bootstrap/jquery/app.js/contract.js/customer.js 等）早已独立且已加指纹，页面专属表单 JS 内联缓存收益有限；移动端表单是最重要转化路径，动它风险>收益。
- **未来触发重新评估的条件**：接原生 App / 开放对外 API / 引入前端构建工具（Vite 等）时，再系统处理。推荐折中方案：视图仅保留 thin bootstrap 注入 `window.__PAGE__`，行为主体搬外部文件。

### 10.3 静态资源版本号指纹 ✅ 已完成（v2.28.2）
- `app/common.php` 新增 `asset_url(string $path): string`，以 `filemtime` 为文件级指纹。
- 18 个视图共 19 处 `<script src>` / `<link href>` 已收敛为 `<?=asset_url('...')?>`（旧零散 `?v=2.23.1/2.25.0/2.27.0` 全部替换）。改一个文件仅刷新该文件缓存。

---

_最后更新：2026-07-18（v2.28.2）_
