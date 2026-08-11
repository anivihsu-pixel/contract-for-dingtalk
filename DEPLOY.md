# 部署说明 — 合同管理系统 v2.35.8

> 本文档为**交付部署包**的配套详细说明，覆盖从环境准备、安装、生产部署、配置、权限、移动端、MySQL 迁移、升级回滚到故障排查的完整流程。
> 版本：v2.35.8（2026-07-27）｜ 技术栈：PHP 8.4 + ThinkPHP 6.1 + Bootstrap 5 | 默认 MySQL / 本地开发可选 SQLite

**演示数据说明**：发布包默认内含仿真数据库（`runtime/data/contract.db`）与演示配置模板 `demo.env.example`。解包后执行 `cp demo.env.example .env && php think run -H 0.0.0.0 -p 8099` 即可预览全部流程（演示账号、内置数据范围等见本文「四、快速开始」与「十、权限模型与演示账号」）；如需纯生产部署（不含 runtime），打包时设置 `DEMO_DATA=0 bash scripts/release.sh`。

---

## 一、产品概述

面向中小企业/法务/财务团队的**合同全生命周期管理系统**，覆盖合同起草、审批流转、客户与供应商管理、项目归集、回款与发票跟踪、到期提醒、审计留痕。**v2.22.0 新增移动端 S 级深度支持与设备自动判断**，手机/平板/微信/钉钉 WebView 访问自动识别并切换移动界面。

### 桌面端核心能力

| 模块 | 主要功能 |
|------|----------|
| 仪表盘 | 合同总额/状态分布、应收已收与回款率、收支方向概览、按项目 TOP N、近期回款计划、今日提醒 |
| 今日提醒 | 到期/逾期合同自动提醒（引擎写入 `remind_log`，支持钉钉工作通知推送） |
| 合同管理 | 列表（筛选/排序/分页/导出 Excel/按项目筛选）、新建/编辑、详情（基本信息/审批流/回款计划/发票/关联项目/时间线）、状态流转、附件上传、归档、删除、对方搜索 |
| 客户管理 | 列表、新增、详情、公海池、认领/分配/释放、删除 |
| 供应商 | 列表、新增、编辑、详情、删除 |
| 项目管理 | 列表、新建、详情（经营聚合：合同数/销售额/应收已收/回款率 + 关联合同）、删除 |
| 合同审批 | 流程自动匹配（按分类+金额）、提交、审批（通过/驳回/转交）、撤回、待办/已办/已提交、抄送节点（CC）、会签/或签 |
| 财务中心 | 回款管理（计划/确认/逾期/删除）、发票管理（列表/新增/编辑/删除）、税务汇总（进项/销项/税额） |
| 经营月报 | 指定月经营数据（应收/已收/逾期/回款率 + 环比）、CSV 导出 |
| 资料库 | 组织级共享资料的上传/列表/删除 |
| 系统设置 | 用户管理、角色权限（含数据范围）、审批流程、合同模板、本公司主体、字典、钉钉设置 |
| 审计中心 | 高危操作（删合同、导出等）留痕可追溯 |
| 强制改密 | 首次登录 `force_reset` 服务端守卫，未改密前禁止进入业务页 |

### 移动端 S 级能力（v2.22.0 完整闭环）

| 模块 | 移动端支持 |
|------|----------|
| 工作台 | 可点导航枢纽（待审批/提醒/合同数量）、快捷动作（新建合同/客户/财务统计/登记回款） |
| 底部导航 | 4 Tab：工作台 / 合同 / 客户 / 审批，全站可达 |
| 合同 | 列表（搜索+状态筛选+加载更多）、详情（含回款/审批/附件）、新建/编辑（含附件上传） |
| 客户 | 列表（搜索+客户/供应商切换）、详情（关联合同/回款付款记录）、新建/编辑 |
| 供应商 | 列表（搜索+客户/供应商切换）、详情、新建/编辑 |
| 审批 | 待办/已办/已提交三 Tab、详情（含合同正文+附件独立卡片）、通过/驳回/转交/撤回 |
| 财务 | 收支概览双卡 + 回款/发票/税务三 Tab、FAB 快速登记回款 |
| 登录 | 移动专属登录页、`force_reset` 强制改密支持 |
| 设备判断 | 自动识别手机/平板/微信/钉钉 WebView，PC 端与移动端分流 |

---

## 二、技术栈与运行环境

**依赖**
- PHP ≥ 8.1（**推荐 8.4**），需启用扩展：`pdo_sqlite`（SQLite 回退用）、`pdo_mysql`（默认）、`mbstring`、`json`、`openssl`、`curl`（钉钉回调）、`fileinfo`（上传）、`zip`（XLSX 导出；缺失时 XlsxHelper 自动降级为 CSV 导出）
- Composer 依赖已随包提供（`vendor/`），无需联网安装
- Web 服务器：内置 `php think run`（演示）或 Nginx + PHP-FPM（生产）
- 数据库：**默认 MySQL**（原生生产库，零额外代码改动）；本地开发可显式设 `DB_TYPE=sqlite` 用 SQLite 兼容

**已内置的健壮性能力（无需额外配置）**
- 全局 CSRF 校验（Double-Submit Cookie，移动端 JS 自动注入 `X-CSRF-TOKEN` 头）
- `Auth` 鉴权 + `force_reset` 改密守卫 + 行级数据权限（SELF/DEPT/ALL）
- SQLite 场景：`WAL` 模式 + `busy_timeout` 等待 + 唯一索引 `INSERT OR IGNORE` 原子去重（并发安全）
- 移动端与桌面端统一的权限门（`requirePermission`/`requireAnyPermission`）
- 设备自动判断（`is_mobile_request()`，见第三节）

---

## 三、设备自动判断（v2.22.0）

系统根据 `HTTP_USER_AGENT` 自动识别访问设备并分流：

| 入口 | 桌面浏览器 | 手机/平板/微信/钉钉 WebView |
|------|-----------|---------------------------|
| 访问根路径 `/` | 跳转 `/dashboard` | 跳转 `/m`（移动工作台） |
| 未登录访问任意页 | 跳转 `/login` | 跳转 `/m/login`（移动登录页） |
| 登录成功 | 跳转 `/dashboard` | 跳转 `/m` |
| `/m/login` | 跳转 `/dashboard`（已登录） | 显示移动登录页（未登录） |

**无需任何配置，代码层面自动生效。** 识别规则覆盖：Mobile / Android / iPhone / iPad / Windows Phone / MicroMessenger / DingTalk 等 UA 关键字。平板设备归入移动端。

---

## 四、快速开始（SQLite，演示 / 中小团队）

```bash
# 1. 解压到目标目录
tar -xzf contract-dingtalk-v2.35.3.tar.gz
cd contract-review

# 2. 赋予运行时目录写权限
chmod -R 755 runtime
chmod 600 .env            # 含密钥，限当前用户

# 3. 配置 SQLite（开发/演示）
# 编辑 .env 文件，设置：
#   DB_TYPE=sqlite
# 其余 MySQL 相关配置（DB_HOST/DB_NAME 等）可忽略

# 4. 初始化数据库（生成 runtime/data/contract.db 与种子数据）
php database/init_sqlite.php
# 首次登录会被强制改密；演示账号 force_reset=0 可直接登录

# 5. 启动（开发/演示）
php think run -H 0.0.0.0 -p 8099
# 桌面访问：http://<服务器IP>:8099
# 手机访问：同一地址，自动跳转移动界面
```

> 内置服务器为单进程，仅适合演示与小流量。**生产请使用 Nginx + PHP-FPM（见第五节）并使用 MySQL。**

### 4.1 局域网开发/演示服务器管理（启动 · 停止 · 重启 · 开机自启）

服务器绑定 `0.0.0.0:8099`，同网段设备用 `http://<服务器IP>:8099` 访问（手机同地址自动跳移动端）。

#### 方式 A：临时启动（当前终端/会话）
适合快速联调，进程随会话结束而停止：
```bash
cd <项目目录>
php think run -H 0.0.0.0 -p 8099
# 停止：Ctrl+C
```

#### 方式 B：macOS 开机自启（推荐长期演示）
用 launchd 托管，登录自动拉起、崩溃自动重启，不依赖任何会话：

1. 创建 `~/Library/LaunchAgents/com.contractreview.devserver.plist`（内容如下，把 `<PROJECT_DIR>` 换成项目绝对路径，`/usr/bin/php` 换成实际 PHP 可执行文件绝对路径，可用 `which php` 查看）：
```xml
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
    <key>Label</key>
    <string>com.contractreview.devserver</string>
    <key>ProgramArguments</key>
    <array>
        <string>/usr/bin/php</string>
        <string><PROJECT_DIR>/think</string>
        <string>run</string>
        <string>-H</string>
        <string>0.0.0.0</string>
        <string>-p</string>
        <string>8099</string>
    </array>
    <key>WorkingDirectory</key>
    <string><PROJECT_DIR></string>
    <key>RunAtLoad</key>
    <true/>
    <key>KeepAlive</key>
    <true/>
    <key>StandardOutPath</key>
    <string>/tmp/contractreview_devserver.out</string>
    <key>StandardErrorPath</key>
    <string>/tmp/contractreview_devserver.err</string>
</dict>
</plist>
```

2. 加载（在**你自己 Mac 的终端**执行，需处于登录会话；agent/CI 沙箱内 `launchctl` 受会话限制可能无法注册，会报 `Bootstrap failed: 5`）：
```bash
launchctl bootstrap gui/$(id -u) ~/Library/LaunchAgents/com.contractreview.devserver.plist
# 旧版 macOS 也可：launchctl load ~/Library/LaunchAgents/com.contractreview.devserver.plist
```

3. 常用运维命令：
```bash
# 启动 / 修改 plist 后重载（先 bootout 再 bootstrap，或直接 kickstart）
launchctl kickstart gui/$(id -u)/com.contractreview.devserver

# 停止服务（保留开机自启）
launchctl bootout gui/$(id -u)/com.contractreview.devserver
# 旧版：launchctl unload ~/Library/LaunchAgents/com.contractreview.devserver.plist

# 彻底移除开机自启：bootout 后删除 plist 文件
rm ~/Library/LaunchAgents/com.contractreview.devserver.plist
```

4. 查看运行状态与日志：
```bash
launchctl list | grep contractreview          # 是否在运行
cat /tmp/contractreview_devserver.err          # 启动报错看这里
lsof -nP -iTCP:8099 -sTCP:LISTEN               # 确认端口在监听
```

> 端口占用：若 8099 已被其它实例占用，`bootstrap`/`kickstart` 会失败。先 `bootout` 旧实例或结束占用进程（如 `lsof` 查到的 PID 后 `kill`）再启动。

---

## 五、生产部署（Nginx + PHP-FPM + MySQL）

### 5.1 系统环境

```bash
chown -R www:www /path/to/contract-review
chmod -R 755 runtime
chmod 600 .env
```

### 5.2 Nginx 配置

```nginx
server {
    listen 80;
    server_name oa.your-company.com;
    root /path/to/contract-review/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    location ~ \.php$ {
        fastcgi_pass  unix:/run/php-fpm/www.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
    # 安全：上传目录禁止执行任何脚本（Apache 由 public/uploads/.htaccess 提供等价防护）
    location ~ ^/uploads/.*\.(php|php3|php4|php5|php7|php8|phtml|phar|pl|py|cgi|asp|aspx|jsp|sh)$ {
        deny all;
    }
    # 安全：禁止访问敏感文件
    location ~ /\.(ht|env|git|svn) { deny all; }
    location ~ /(runtime|backups|releases) { deny all; }
}
```

> ⚠️ **移动端也走同一 Nginx 配置**，设备判断在应用层完成，无需额外 server 块或重定向规则。

### 5.3 PHP-FPM 建议

- `php.ini`：`session.save_path` 可写；`upload_max_filesize` / `post_max_size` 按需调大（合同附件可能数 MB～数十 MB）
- `date.timezone = Asia/Shanghai`

### 5.4 MySQL 初始化（一键）

```bash
# 编辑 .env，填写 MySQL 连接信息：
#   DB_TYPE=mysql
#   DB_HOST=127.0.0.1
#   DB_PORT=3306
#   DB_NAME=contract_dingtalk
#   DB_USER=root
#   DB_PASS=your_root_password

# 一键迁移（建库+建表/种子+切 DB_TYPE）
bash database/migrate_to_mysql.sh

# 安全加固（创建专用低权限应用账户 contract_app）
bash database/harden_mysql.sh
# 按提示输入 root 密码，脚本自动创建 contract_app（仅 DML+EXECUTE）
# 完成后将 .env 的 DB_USER/DB_PASS 改为 contract_app 及生成的密码

# 清理运行时缓存
rm -rf runtime/cache/*
```

> 详细逐步操作手册见 `MIGRATION_SQLITE_TO_MYSQL.md` 第 7 节「目标服务器执行 Runbook」。

### 5.5 生产安全加固（务必执行）

```bash
# 生成随机强密钥，覆盖 .env 中随仓库分发的弱 APP_KEY / JWT_SECRET
php database/generate_secrets.php
```
并将 `.env` 的 `APP_DEBUG` 保持 `false`（代码已默认 `env('APP_DEBUG', false)`）。

---

## 六、配置项说明（`.env` 全量）

| 配置 | 说明 | 默认值 |
|------|------|--------|
| `APP_DEBUG` | 是否开启调试（**生产必须 false**） | false |
| `APP_KEY` | 应用密钥，**部署前用 generate_secrets.php 重生成** | 弱密钥（需替换） |
| `DB_TYPE` | 数据库类型 `mysql`（默认，生产）/ `sqlite`（仅本地开发） | mysql |
| `DB_HOST` / `DB_PORT` | MySQL 主机/端口（sqlite 忽略） | 127.0.0.1 / 3306 |
| `DB_NAME` / `DB_USER` / `DB_PASS` | MySQL 库名/账号/密码 | contract_dingtalk / root / root |
| `DB_PREFIX` | 表前缀 | 空 |
| `CACHE_TYPE` | 缓存驱动 `file` / `redis` | file |
| `REDIS_HOST` / `REDIS_PORT` / `REDIS_PASSWORD` | Redis 连接（cache=redis 时） | 127.0.0.1 / 6379 / 空 |
| `DINGTALK_APP_KEY` / `APP_SECRET` / `CORP_ID` / `AGENT_ID` | 钉钉企业内部应用凭证 | 测试值 |
| `DINGTALK_APP_URL` | 应用首页地址（消息深链拼接用，须与钉钉后台一致） | 空 |
| `DINGTALK_MOCK_MODE` | 钉钉 Mock 模式（true=不实际调用，写 `/tmp/dingtalk_mock.log`） | true |
| `JWT_SECRET` | 钉钉免登 JWT 签名密钥，**部署前重生成** | 弱密钥（需替换） |
| `JWT_TTL` | JWT 有效期（秒） | 86400 |

---

## 七、数据库与备份

### MySQL（生产，默认）

- 代码默认 `DB_TYPE=mysql`，填好 `.env` 连接信息即生效
- 初始化：`bash database/migrate_to_mysql.sh`（建库+建表+种子）
- 加固：`bash database/harden_mysql.sh`（创建专用低权限账户 `contract_app`）
- 备份：使用 `mysqldump` 定时备份（见第八节 crontab）
- 时区：应用层 `default_timezone=Asia/Shanghai`；MySQL 连接层自动 `SET time_zone = '+08:00'`（无需服务器全局设时区）

### SQLite（本地开发/演示兼容）

- 路径：`runtime/data/contract.db`（打包已排除，首次部署由 `init_sqlite.php` 生成）
- 模式：WAL（并发读写友好，`-wal`/`-shm` 为临时文件，空闲自动回收）
- 使用方式：在 `.env` 中显式设置 `DB_TYPE=sqlite` 即可，无需改代码
- 备份：`php think db:backup --keep=14`（优先 `VACUUM INTO` 一致性快照）

### 审计留痕

删除合同、导出合同 Excel 均写入审计日志，可在「审计中心」追溯（含范围与条数）。

---

## 八、定时任务（crontab 推荐）

```bash
crontab -e
# 每天 09:00 扫描到期/逾期并推送钉钉提醒
0 9 * * * cd /path/to/contract-review && /usr/bin/php think remind:dispatch >> runtime/remind.log 2>&1
# 每天 02:30 备份数据库（保留 14 份）
30 2 * * * cd /path/to/contract-review && /usr/bin/php think db:backup >> runtime/backup.log 2>&1
# MySQL 环境更推荐用 mysqldump
30 2 * * * /usr/bin/mysqldump -ucontract_app -p'<password>' contract_dingtalk > /backup/contract_$(date +\%Y\%m\%d).sql
# 每天 01:30 回款逾期自动标记（P1-4）：过计划日仍未确认的回款置 OVERDUE，联动客户信用评级
30 1 * * * cd /path/to/contract-review && /usr/bin/php think payment:mark-overdue >> runtime/remind.log 2>&1
# 其余建议命令（见下）：customer:pool-release / remind:check / approval:sla-check / approval:escalate / contract:expire / customer:credit-check
```

> 两个 think 命令已在 `config/console.php` 注册：`remind:dispatch` → `app\command\RemindDispatch`、`db:backup` → `app\command\DbBackup`。

---

## 九、钉钉集成上线

实现「审批 → 钉钉工作通知 → 点击消息免登进入系统审批」闭环，**移动端与桌面端均支持**。

> ⚠️ **钉钉 JSAPI SDK（v2.35.1 关键修复）**：免登依赖全局 `dd` 对象，而**新版钉钉/工作台 H5 不会自动注入 `dd`**。系统内置免登页（`/dingtalk/entry`、`/login`）已在 `<head>` 显式引入官方 SDK（`https://g.alicdn.com/dingding/dingtalk-jsapi/3.1.0/dingtalk.open.js`），无需手动处理；但若自行定制免登入口页，**必须自行引入该 SDK**，否则免登会报 `Uncaught ReferenceError: dd is not defined` 而失效。环境判定以 UA（`/DingTalk/i`）为主，并带 8 秒看门狗回退登录页。详见 `DINGTALK_SSO_GUIDE.md` 第 3.1 节。

1. **钉钉开放平台**创建企业内部应用，获取 AppKey/AppSecret/CorpId/AgentId，配置「首页地址」为对外域名（须与 `DINGTALK_APP_URL` 完全一致，否则消息深链失败）。
2. **配置**：后台「系统设置 → 钉钉设置」填写上述信息 + APP_URL + 关闭 Mock；或直接改 `.env`。
3. **绑定身份**：未绑定 `dingtalk_userid` 的用户收不到通知（自动跳过并写日志，不报错）。可在「组织同步」自动写入，或在用户编辑页手动填「钉钉 UserID」。
4. **验证**：提交合同审批 → 审批人收钉钉通知 → 点击经 `{APP_URL}/dingtalk/entry?to=/approval/{id}` 免登直达。Mock 模式不实际调用钉钉，便于本地验证链路。
5. **移动端**：钉钉 WebView 打开自动识别为移动设备，走移动 UI；审批详情含合同正文 + 附件独立卡片，通过/驳回/转交/撤回全在移动端完成。

---

## 十、权限模型与演示账号

**RBAC**：37 项细粒度权限码，分 13 组（合同管理 / 合同模板 / 审批管理 / 客户管理 / 系统设置 / 钉钉设置 / 供应商管理 / 回款管理 / 发票管理 / 签署管理 / 提醒管理 / 资料库 / 项目管理）。

**内置角色（受保护不可删）**

| 角色 | 数据范围 | 说明 |
|------|----------|------|
| 超级管理员 | 全部(ALL) | 系统设置全部权限 |
| 部门经理 | 本部门(DEPT) | 含项目管理/审批等 |
| 法务 | 仅自己(SELF) | 合同/模板相关 |
| 财务 | 仅自己(SELF) | 回款/发票相关 |
| 普通用户 | 仅自己(SELF) | 基础合同/客户/项目 |

> 角色管理支持新建自定义角色、勾选权限、设置数据范围（本人/本部门/全部）。菜单按权限自动隐藏无权限入口（纵深防御），移动端与桌面端权限规则一致。

**演示账号（密码均为 `password`，仅演示用）**

| 账号 | 姓名 | 角色 | 数据范围 |
|------|------|------|----------|
| admin | 系统管理员 | 超级管理员 | 全部 |
| manager01 | 张经理 | 部门经理 | 本部门 |
| employee01 | 李员工 | 普通用户 | 仅自己 |
| finance01 | 王财务 | 财务 | 仅自己 |

> ⚠️ 生产环境请**删除演示账号**、改用真实员工账号，并跑 `generate_secrets.php` 重置密钥、修改 admin 初始密码。

---

## 十一、升级与回滚（推荐零停机方式）

### 11.0 零停机部署（推荐，v2.35.8 起支持）

自 v2.35.8 起，提供 `scripts/deploy.sh` 支持 **symlink 原子替换**式零停机部署——新版本在 `releases/` 下独立解压 → 自动运行迁移 → `ln -sfn` 一次替换 `current` 符号链接，**线上服务不中断**，出问题秒级回滚。

> 完整使用说明（首次部署 / 后续更新 / 回滚 / 迁移自动化 / 环境变量 / 故障排查）单独成篇：**`DEPLOY_ZERO_DOWNTIME.md`**（随发布包提供，位于发布包解压后根目录）。下文仅作概要。

**目录结构**：
```
/var/www/contract/            # DEPLOY_ROOT（Web 服务器 DocumentRoot = current/public/）
├── current -> releases/20260727-133000/
├── releases/
│   ├── 20260727-120000/
│   └── 20260727-133000/      # 当前版本
└── shared/                   # 跨版本共享（不随代码覆盖）
    ├── .env                  #   敏感配置（首次部署后手动编辑）
    ├── runtime/              #   缓存/日志/SQLite 数据库
    └── public/uploads/       #   用户上传附件
```

**首次部署**（在服务器上执行）：
```bash
# 1. 创建部署根目录
mkdir -p /var/www/contract

# 2. 使用 deploy.sh 部署包（自动初始化 shared/ + 建立符号链接）
DEPLOY_ROOT=/var/www/contract bash scripts/deploy.sh deploy contract-dingtalk-v2.35.7.tar.gz

# 3. 编辑 shared/.env 配置生产环境密钥
vi /var/www/contract/shared/.env

# 4. 配置 Nginx DocumentRoot 指向 current/public/
#    root /var/www/contract/current/public;

# 5. 重载 Nginx
systemctl reload nginx
```

**后续更新**（零停机）：
```bash
DEPLOY_ROOT=/var/www/contract bash scripts/deploy.sh deploy contract-dingtalk-v2.35.8.tar.gz
# 自动完成：解包 → 链接 shared/ → 运行迁移 → 原子切换 current → 清理旧版本
```

**回滚**（秒级，不停机）：
```bash
DEPLOY_ROOT=/var/www/contract bash scripts/deploy.sh rollback     # 回到上一个版本
DEPLOY_ROOT=/var/www/contract bash scripts/deploy.sh rollback 2   # 回到前两个版本
```

**查看状态**：
```bash
DEPLOY_ROOT=/var/www/contract bash scripts/deploy.sh status
# 列出当前版本、历史版本列表、共享文件状态
```

**清理旧版本**（保留最近 5 个）：
```bash
DEPLOY_ROOT=/var/www/contract bash scripts/deploy.sh clean --keep=5
```

> ⚠️ **首次部署后务必继续执行 §5.2 Nginx 配置**（DocumentRoot 必须指向 `current/public/` 而非直接指向 `releases/xxx/public/`）和 **§5.5 生产安全加固**。
>
> ⚠️ **共享文件机制**：`shared/.env`、`shared/runtime/`、`shared/public/uploads/` 在所有版本间共享。首次部署时会自动从包内迁移到 `shared/`；后续更新直接符号链接，不会丢失数据。
>
> ⚠️ **迁移幂等**：`deploy.sh` 自动运行 `database/migration_*.sql`；所有迁移脚本均用 `information_schema` 判重（可重复执行）。MySQL 连接信息从 `shared/.env` 自动解析。

---

### 11.1 传统方式升级（停服覆盖，简单但需中断服务）

如果无法使用 symlink 部署（如共享主机不支持符号链接），可用传统方式：

- 从 v2.9+ 升级：**无需**重建数据库（表结构向后兼容，新增列/索引由 `init_sqlite.php` 或 `init_mysql.php` 兼容处理；若跨大版本请先备份再执行初始化脚本重建并重新导入）。
- 步骤：停服 → 解压新包覆盖（**保留** `runtime/`、`.env`、`vendor/` 可覆盖）→ 若涉及表结构变更执行 `php database/init_sqlite.php` 或 `php database/init_mysql.php`（请先备份）→ 重启。
- 升级后首次以 admin 登录会被要求强制改密（预期行为）。
- 发布包不包含 `.env`（含密钥）与 `runtime/`（含运行数据库），覆盖部署不会丢失配置与数据。

### v2.38.6 升级要点（交付级审查达标 · 签署移除 · 规则配置 · 提醒引擎修复）

> **从 < v2.38.6 升级，请按序执行以下迁移与清理，缺一不可：**

1. **按序执行 3 个数据库迁移**（幂等，可重复执行）：
   - `migration_v2.38.4_remove_sign.sql` —— 删除签署残留：`sign_task` 表、`contract.sign_date` 列、`sign:view/sign:manage` 权限（38→36 项）与角色映射、`dict_sign_type/dict_seal_type` 字典。
   - `migration_v2.38.5_rules.sql` —— 新增业务规则配置种子（公海释放天数 / 合同到期提醒 / 回款提醒提前天数）。
   - `migration_v2.38.6_credit_manual.sql` —— `customer` 表加 `credit_manual` 列（信用评分人工锁定）。
   - 执行方式：`mysql -h<H> -u<U> -p <DB> < database/migration_v2.38.6_credit_manual.sql`（MySQL）；SQLite 见文件内注释。
2. **清缓存（必做）**：删除 `runtime/route_list.php`（旧路由缓存可能仍含已移除的 Sign 路由）与 `runtime/cache/*`、`runtime/temp/*`。
3. **签署功能已移除**：审批链不变（终审通过后直接 →EXECUTING，无签署环节）；`SIGNED` 状态仅历史存量保留，筛选/字典仍显示。
4. **站内提醒引擎修复**（曾因 think-orm 缺 `insertOrIgnore` 从未落库）：升级后首次跑 `remind:check`/`remind:dispatch` 会补发历史积压提醒，属正常；提前天数现可在「系统设置→系统配置→业务规则」配置。
5. **业务规则配置入口**：系统设置→系统配置→业务规则（公海自动释放天数、合同到期/回款到期提醒提前天数），保存即时生效；CLI `customer:pool-release --days=N` 可临时覆盖。
6. **信用评级**：客户编辑修改信用评分后自动锁定（`credit_manual=1`），定时重算不再覆盖人工值；收款确认/撤销/删除回款会自动重算（恢复路径）。
7. **crontab 建议**（如未配置请补齐）：`customer:pool-release`、`remind:check`、`remind:dispatch`、`approval:sla-check`、`approval:escalate`、`contract:expire`、`customer:credit-check`、`payment:mark-overdue`（回款逾期自动置 OVERDUE，建议每日 01:30），详见第八节。

### v2.35.8 升级要点（移动端 PDF 钉钉内预览 + 版本号固化 + 零停机部署）

- **改动性质**：前端新增（PDF.js 预览）+ 构建体系（版本固化 + 部署脚本），**无 DB 结构变更、无新迁移脚本**（v2.35.4 的 `perm_version` 幂等迁移属存量优化，见下）。
- **改动2（PDF 钉钉内预览）**：新增 `public/static/lib/pdfjs/`（约 4MB，浏览器长期缓存）、移动端预览页 `/m/doc-preview`、控制器 `MobileController::docPreview()`；`lightbox.js` 对 PDF 附件改为钉钉 WebView 内 canvas 渲染、不跳出外部浏览器。覆盖部署即生效。
- **改动3（版本号固化）**：新增 `config/version.php`（由 `release.sh` 打包时自动写入当前版本号），`app_version()` 优先读该配置，部署后侧栏/后台「当前版本」不再回退 `unknown`。**注意**：`config/version.php` 为自动生成文件（已加入 `.gitignore`），请勿手动编辑。
- **改动4（零停机部署）**：新增 `scripts/deploy.sh`（symlink 原子替换 + 共享 `.env`/`runtime`/`uploads` + 自动跑 `migration_*.sql` + 秒级回滚）。推荐后续小版本迭代使用，避免停服覆盖。详见 §11「零停机部署（推荐）」。
- **改动1（迁移幂等）**：`database/migration_v2.35.4_perm_version.sql` 改为 `information_schema` 判重 + 动态 SQL 幂等写法（MySQL 8.0 兼容、可重复执行）。**存量库从 < v2.35.4 升级时仍需执行此脚本一次**补 `perm_version` 列；已 ≥ v2.35.4 的环境列已存在，自动跳过。

### v2.35.7 升级要点（移动工作台今日提醒跳转 + 合同未保存返回弹窗修复）
- **改动性质**：纯前端视图 / JS 修复，**无 DB 结构变更、无迁移脚本**。
- **问题13**：移动工作台顶部「今日提醒」卡片原为页内锚点（点击无跳转）；改为真实路由 `/m/remind`（新增提醒列表页 + 控制器方法 + 路由），点击进入提醒列表并支持跳转到关联合同。
- **问题14**：移动端新建/编辑合同页未提交时触发「返回」弹窗，点「确定」不离开（根因 `_confirmLeave` 错误依赖 `mConfirm`「确定」回调的不存在参数）；并补齐顶部「返回」箭头也走同一确认流程。覆盖部署即可生效，无需跑迁移。

### v2.35.6 升级要点（移动端合同详情审批记录状态中文化）
- **改动性质**：纯前端视图修复，**无 DB 结构变更、无迁移脚本**。
- **问题**：移动端合同详情页「审批记录」区块误用合同状态映射表渲染审批实例状态，导致 `PENDING` / `RECALLED` 等英文状态原样显示。
- **修复**：新增审批实例专用状态映射（审批中 / 已通过 / 已驳回 / 已撤回），与 `approval_status_label()` 文本一致；覆盖部署即可生效，无需跑迁移。

### v2.35.5 升级要点（部门经理审批节点定位 · 部门负责人字段）
- **DB 变更**：`department` 表新增 `leader_user_id` 字段（整数，默认 0，指向该部门真实负责人 `user.id`）。本版 `DEPT_LEADER` 审批节点优先取该字段定位部门经理，而非再近似 `is_admin=1`。
- **必须执行迁移**（不执行则审批解析/用户编辑会因缺列报错）：
  - **MySQL**：`mysql -h<H> -u<U> -p <DB> < database/migration_v2.35.5_department_leader.sql`
  - **SQLite**：`sqlite3 runtime/data/contract.db < database/migration_v2.35.5_department_leader.sql`
- 迁移**幂等**（建列已判重），可重复执行；本地 SQLite 已由 `init_sqlite.php` 建表时直接包含该列。
- 升级后由超级管理员在「系统设置→用户管理」编辑弹窗「部门负责人」下拉为各部门指定负责人，部门经理审批节点即生效。

### v2.35.4 升级要点（权限会话实时刷新 · RV-01）
- **DB 变更**：`user` 表新增 `perm_version` 字段（整数，默认 0）。本版实现"角色/权限/`is_admin` 变更后，已登录用户（含钉钉端）下次请求自动刷新会话权限"，依赖该字段做版本比对。
- **必须执行迁移**（不执行则登录会因缺列报错）：
  - **MySQL**：`mysql -h<H> -u<U> -p <DB> < database/migration_v2.35.4_perm_version.sql`
  - **SQLite**：`sqlite3 runtime/data/contract.db < database/migration_v2.35.4_perm_version.sql`
- 迁移**幂等**（`ADD COLUMN` 已判重），可重复执行。
- 升级后**无需强制重新登录**：后台改角色/权限的用户，刷新页面或发起新请求即自动拿到最新权限；手机钉钉端同理。

### 回滚

系统提供三种回滚方式：

**方式一：运维脚本一键回滚**
```bash
bash scripts/rollback.sh              # 列出可回滚版本
bash scripts/rollback.sh v2.21.1      # 回滚到指定版本（自动备份当前代码）
```
> 回滚仅覆盖代码文件，不动 `.env` / `runtime/`。

**方式二：手动回滚（SQLite）**
停服 → 恢复上一版代码 → 将备份库覆盖回 `runtime/data/contract.db` → 重启。

**方式三：手动回滚（MySQL）**
停服 → 恢复上一版代码 → `mysql < backup_YYYYMMDD.sql` → 重启。

### 备份脚本

```bash
bash scripts/backup.sh        # 全量备份（代码快照 + DB）
bash scripts/backup.sh code   # 仅代码快照到 backups/code/
bash scripts/backup.sh db     # 仅 DB 备份（调 php think db:backup）
```

---

## 十二、安全上线清单（必做）

- [ ] 执行 `php database/generate_secrets.php` 重置 `APP_KEY` / `JWT_SECRET`
- [ ] 修改 admin 初始密码，删除/停用演示账号
- [ ] `.env` 权限 `chmod 600`，且**不要**提交进代码仓库
- [ ] `APP_DEBUG=false`
- [ ] 生产关闭 `DINGTALK_MOCK_MODE=false`
- [ ] Nginx 屏蔽 `.env` / `.git` / 隐藏文件 / `runtime/`
- [ ] 配置 HTTPS（钉钉深链与 JWT 免登均需可信域名）
- [ ] 配置 crontab 定时备份与提醒
- [ ] 生产使用 MySQL（默认），执行 `migrate_to_mysql.sh` + `harden_mysql.sh`
- [ ] 在手机/平板/微信/钉钉上验证移动端设备自动判断与 UI

---

## 十三、故障排查 FAQ

| 现象 | 可能原因 | 处理 |
|------|----------|------|
| 页面 500 | `.env` 缺失/权限、runtime 不可写、DB 未初始化 | 检查 `runtime/` 写权限；执行 `init_sqlite.php` 或 `init_mysql.php` |
| `database is locked` | SQLite 高并发写 | 已通过 WAL+busy_timeout 缓解；仍频繁则切 MySQL（默认就是 MySQL） |
| 登录后跳回登录页/循环 | `force_reset=1` 未改密 | 首次登录按提示改密；或 `UPDATE user SET force_reset=0` |
| 上传附件失败 | `upload_max_filesize` 过小 | 调大 php.ini 对应项并重启 FPM |
| 钉钉通知收不到 | 未绑定 `dingtalk_userid` 或 Mock 开启 | 绑定身份；生产关 Mock；查 `/tmp/dingtalk_mock.log` |
| 时间显示偏差 | 服务器时区 | 确认 `date.timezone = Asia/Shanghai` |
| 手机访问显示 PC 界面 | UA 未被识别（罕见） | 手动访问 `/m` 进入移动端；正常手机/微信/钉钉 UA 均自动识别 |
| 钉钉打开应用报 `dd is not defined` | 免登页未引入 JSAPI SDK（新版钉钉不自动注入 `dd`） | 确认使用系统内置免登页（已含 SDK）；自定页面须引入 `g.alicdn.com/dingding/dingtalk-jsapi/3.1.0/dingtalk.open.js`；包版本须 ≥ v2.35.1 |
| 移动端底部图标不显示 | CDN 未加载 Bootstrap Icons | 检查服务器外网连通性；CDN 加载 `bootstrap-icons@1.11.3` |
| 移动端操作返回 403 | CSRF token 未携带 | 确认移动页 JS 含 `csrfToken()` 函数从 cookie 读取并注入 `X-CSRF-TOKEN` 头 |
| 移动端「编辑/提交审批」跳到 PC 页 | v2.22.0 前历史 bug | 已全量修复（3 处跳桌面路径 + 全量移动视图排查零遗漏），确认升级到 v2.22.0+ |
| MySQL 连接报错 | 未执行 `init_mysql.php` 建表 | 运行 `bash database/migrate_to_mysql.sh` 一键初始化 |
| MySQL 时区偏移 8 小时 | 服务器全局时区非 +08:00 | 代码已连接层强制 `SET time_zone = '+08:00'`，无需手动设 |

---

## 十四、目录结构

```
contract-review/
├── app/
│   ├── controller/      # 控制器（桌面 + MobileController 移动端）
│   ├── common/
│   │   ├── logic/       # 业务逻辑（Contract/Customer/Project/Approval/Auth/DingTalk）
│   │   └── service/     # 服务（Audit/Remind/Rbac/Jwt/DingTalk/ContractTimeline）
│   ├── middleware/      # Auth / Csrf / SqliteGuard
│   └── view/
│       ├── layout/      # 桌面布局（header/footer）
│       └── mobile/      # 移动端 S 级原生视图（16 个页面）
├── config/              # 配置（database/session/cache/console...）
├── database/
│   ├── init_mysql.php         # MySQL 建库 + 种子（**基准/唯一事实来源**，26 表/268 字段）
│   ├── init_sqlite.php        # SQLite 建库 + 种子 + WAL 固化（1:1 对齐 init_mysql.php）
│   ├── init.sql              # 纯 SQL 版 MySQL 镜像（供 DBA 直接导入，1:1 对齐 init_mysql.php）
│   ├── migrate_to_mysql.sh    # 一键 MySQL 迁移脚本（5 步：建库→建表/种子→切 DB_TYPE→冒烟→退役 SQLite）
│   ├── harden_mysql.sh        # MySQL 安全加固（专用低权限账户 contract_app）
│   └── generate_secrets.php   # 生产密钥生成
├── public/
│   ├── index.php        # Web 入口
│   └── static/
│       ├── css/mobile.css     # 移动端设计系统（导航/卡片/列表/表单/FAB/弹层/时间线/Toast）
│       └── js/                # 各模块交互 JS（含 CSRF token 注入）
├── route/app.php        # 路由（桌面+移动+AJAX）
├── runtime/             # 运行时（data/、log/、session/）——部署时自动生成
├── scripts/             # 运维脚本
│   ├── release.sh       # 版本一致性校验 + 语法体检 + DB脚本对照/注释卡点 + 打包
│   ├── check_schema_parity.sh # 三份初始化脚本「表+字段」对照校验（基准 init_mysql.php）
│   ├── check_db_comments.sh   # 三份初始化脚本字段中文注释完整性校验
│   ├── backup.sh        # 代码快照 + DB 备份
│   └── rollback.sh      # 版本回滚（自动备份 + 安全覆盖）
├── releases/            # 发布包存档
│   ├── MANIFEST.txt     # 发布清单（版本/时间/大小/SHA256）
│   └── legacy/          # 历史版本归档
├── tests/               # 验收/复现脚本（可移植 ${PHP_BIN:-php}）
├── vendor/              # Composer 依赖（含 ThinkPHP 6.1 等）
├── .env.example         # .env 脱敏模板（部署时复制为 .env）
├── .gitignore           # Git 忽略规则（.env/runtime/releases/backups）
├── DEPLOY.md            # 本文件
├── CHANGELOG.md         # 全量迭代日志
├── VERSION.md           # 版本说明
├── DEVELOPMENT_GUIDE.md # 开发规范（SemVer/发布/备份/回滚/目录约定）
├── MIGRATION_SQLITE_TO_MYSQL.md  # MySQL 迁移方案 + Runbook
└── CODE_REVIEW_v222.md  # v2.22.0 全量代码审查报告
```

---

*部署问题与定制需求请联系实施方。本文档随交付包提供，版本号以 `VERSION.md` 为准。*
