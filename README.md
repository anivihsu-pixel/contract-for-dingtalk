# 合同管理系统（Contract Review System）

> PHP 8.4 + ThinkPHP 6.1 + SQLite + Bootstrap 5 的合同管理与审批系统，覆盖合同全生命周期、客户/供应商、项目、发票三段式审批、财务统计、提醒中心、钉钉集成、RBAC 权限。

---

## 功能特性

- **合同全生命周期**：草稿 → 审批 → 生效 → 归档，状态机流转 + 审批流级抄送
- **客户/供应商管理**：档案、行业、跟进记录、共享与集团层级
- **项目经营**：项目阶段、里程碑、收支统计
- **发票三段式审批**：申请 → 审批 → 财务开票（红冲/作废）
- **财务统计**：驾驶舱、经营月报/周报、应收账龄、口径对齐
- **移动工作台**：钉钉免登、个人/部门/全公司经营看板
- **RBAC 权限**：角色 + 数据范围（部门/个人/全部）

---

## 发布门禁（Release Gates）

本项目设 **6 道发布门禁**，发布前须全部通过（`release.sh` 已内置前 5 道为发布卡点）：

| # | 门禁 | 脚本 | 作用 |
|---|------|------|------|
| 1 | schema_parity | `scripts/check_schema_parity.sh` | 三份初始化脚本（`init_mysql.php`/`init_sqlite.php`/`init.sql`）「表+字段」1:1 一致性校验 |
| 2 | db_comments | `scripts/check_db_comments.sh` | 全部表级 + 字段级中文注释完整性校验 |
| 3 | view_globals | `scripts/check_view_globals.sh` | 视图公共全局变量/函数未声明在 `$tab` 分支内（防跨 tab 缺失回归） |
| 4 | frontend | `scripts/check_frontend.sh` | 前端脚本加载顺序 + DCL 防护 + 禁止英文枚举直出 |
| 5 | dead_entry | `scripts/check_dead_entry.sh` | 页面路由可达性（路由已注册但全站无入口的死功能拦截） |
| 6 | PHPUnit | `scripts/test.sh` | 单元测试基线（`tests/unit/`，当前 43 tests / 89 assertions） |

### 门禁脚本架构：Bash 包装器 + Python 内核

5 道 `check_*.sh` 门禁脚本均采用 **bash 包装器 + Python heredoc 内核** 的结构：

```bash
#!/usr/bin/env bash
set -euo pipefail
python3 - <<'PYEOF'
# 实际校验逻辑（Python 实现）
import sys, re, pathlib
...
sys.exit(0 if ok else 1)
PYEOF
```

- **bash 层**：仅负责 `set -euo pipefail` 严格模式与调用 `python3`，无业务逻辑。
- **Python 层**（heredoc）：承载全部校验逻辑——解析 SQL/PHP/JS 文件、状态机词法分析、字段对照、正则匹配等。
- **设计原因**：Python 跨平台、字符串/正则处理能力强，适合做静态校验；bash 仅作入口，保持与 `release.sh`/`backup.sh` 等 shell 脚本一致的调用风格。

### 跨平台运行

#### Linux / macOS（原生 bash）

```bash
bash scripts/check_schema_parity.sh    # 单道门禁
bash scripts/check_db_comments.sh
bash scripts/test.sh                   # PHPUnit
```

#### Windows（无 bash 环境时的 PowerShell 运行器）

Windows 默认无 `bash`/Git Bash，无法直接执行 `.sh` 脚本。为此提供 **`scripts/run_gates.ps1`** ——一个纯 PowerShell 门禁运行器，无需安装 bash：

```powershell
# 运行全部 5 道门禁 + PHPUnit
.\scripts\run_gates.ps1 all

# 运行单道门禁
.\scripts\run_gates.ps1 schema_parity
.\scripts\run_gates.ps1 db_comments
.\scripts\run_gates.ps1 view_globals
.\scripts\run_gates.ps1 frontend
.\scripts\run_gates.ps1 dead_entry

# 仅运行 PHPUnit
.\scripts\run_gates.ps1 test
```

**`run_gates.ps1` 工作原理**：

1. 读取指定的 `check_*.sh` bash 包装器文件内容；
2. 用正则 `python3?\s*-\s*<<['"]?(\w+)['"]?` 定位 Python heredoc 起始标记；
3. 逐行提取 heredoc 内的 Python 代码直到结束标记；
4. 写入临时 `.py` 文件，调用 `python3` 执行；
5. 透传 Python 的 stdout/stderr 与退出码，临时文件自动清理。

这样 **同一份 Python 校验逻辑在两个平台共享同一份源码**，无需维护两套校验实现——bash 平台直接跑 `.sh`，Windows 平台由 `run_gates.ps1` 提取并执行相同的 Python 内核。

#### Windows 环境要求

- **PHP 8.4+**（含 `pdo_sqlite`/`mbstring`/`openssl`/`curl`/`fileinfo`/`sqlite3` 扩展）
- **Python 3.10+**（门禁脚本内核）
- **PowerShell 执行策略**：须允许本地脚本运行。若遇 `UnauthorizedAccess` 报错，执行：
  ```powershell
  Set-ExecutionPolicy -ExecutionPolicy RemoteSigned -Scope CurrentUser -Force
  ```
- `run_gates.ps1` 会自动把已知的 PHP/Python 安装路径加入 `PATH`（见脚本内 `$phpPath`/`$pyPath`），无需手动配置环境变量。

#### Windows 注意事项

- PowerShell 的 `curl` 是 `Invoke-WebRequest` 的别名，与系统 `curl.exe` 语法不同。测试本地服务时须用 `curl.exe` 显式调用：
  ```powershell
  curl.exe -s -o NUL -w "HTTP %{http_code}" http://127.0.0.1:8099/login
  ```

---

## 快速开始

```bash
# 1. 安装依赖
composer install

# 2. 初始化数据库（SQLite）
php database/init_sqlite.php

# 3. 启动开发服务器
php -S 127.0.0.1:8099 -t public public/router.php
# 或用保活脚本
python scripts/keep_alive_devserver.py
```

默认账号：`admin`（口令由 `init_sqlite.php` / `init_mysql.php` 随机生成并打印，首登强制改密）。生产环境请用 `php database/generate_secrets.php` 生成强密钥。

---

## 目录结构

| 目录 | 用途 |
|------|------|
| `app/controller/` | 控制器（薄层，权限校验 + 调 Logic） |
| `app/common/logic/` | 业务逻辑层（数据范围、状态机、聚合） |
| `app/common/service/` | 服务层（钉钉、JWT、RBAC、审计、提醒） |
| `app/view/` | 视图（PC 端 + `mobile/` 移动端） |
| `public/static/` | 静态资源（CSS/JS，`asset_url()` 加 filemtime 指纹） |
| `config/` | 配置（`version.php` 当前版本号） |
| `database/` | 初始化脚本、迁移脚本（三份 init 须 1:1 对齐） |
| `scripts/` | 运维脚本（release/backup/rollback + 6 道门禁） |
| `tests/unit/` | PHPUnit 单元测试 |

---

## 发布门禁

项目设 6 道发布门禁（`scripts/run_gates.ps1 all` 一键运行，Windows/Linux 均可）：

| # | 门禁 | 作用 |
|---|------|------|
| 1 | schema_parity | 三份初始化脚本「表+字段」1:1 一致性校验 |
| 2 | db_comments | 全部表级 + 字段级中文注释完整性校验 |
| 3 | view_globals | 视图公共全局变量未声明在 tab 分支内 |
| 4 | frontend | 前端脚本加载顺序 + DCL 防护 |
| 5 | dead_entry | 路由已注册但全站无入口的死功能拦截 |
| 6 | PHPUnit | 单元测试基线 |

```powershell
# 运行全部门禁（Windows）
.\scripts\run_gates.ps1 all

# Linux/macOS
bash scripts/check_schema_parity.sh && bash scripts/check_db_comments.sh && ...
```

发布：`bash scripts/release.sh`（内置前 5 道门禁为发布卡点）。

---

## 安全提示

- **密钥**：部署前务必执行 `php database/generate_secrets.php` 生成随机 `APP_KEY` / `JWT_SECRET`，禁用代码库默认占位符 `please_change_me`
- **管理员口令**：`init_mysql.php` / `init_sqlite.php` 随机生成并打印，首登强制改密（`force_reset=1`）
- **`.env` 文件**：含敏感信息，已被 `.gitignore` 排除，禁止提交版本库
- **钉钉 Mock 模式**：仅用于本地开发，生产环境必须关闭（`DINGTALK_MOCK_MODE=false`）
- **演示数据**：`seed_demo.php` 仅用于本地预览，生产环境禁止执行

---

## 许可证

[Apache License 2.0](LICENSE)
