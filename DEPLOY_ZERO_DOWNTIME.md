# 零停机部署说明 — 合同管理系统

> 配套脚本：`scripts/deploy.sh`（随发布包提供，位于 `scripts/` 目录内）
> 适用版本：v2.35.8 起。
> 本文档聚焦于「如何在不中断线上服务的情况下升级系统」，完整部署流程（环境准备、安装、配置、权限、移动端、MySQL 迁移、故障排查）见 `DEPLOY.md`。

---

## 一、这套部署解决了什么问题

传统「停服 → 覆盖文件 → 重启」方式在每次小版本迭代时都会中断服务。本方案采用 **symlink 原子替换**：

- 线上服务始终指向 `current/` 符号链接；
- 新版本在**旁路** `releases/` 目录解压、配置完毕后再「一秒切换」符号链接；
- 切换瞬间完成，**用户无感知、请求不中断**；
- 出问题可**秒级回滚**到上一版本。

---

## 二、部署目录结构

```
DEPLOY_ROOT/                       # 可自定义，默认 /var/www/contract
├── current -> releases/20260727-133000/   # 符号链接（Web 服务器 DocumentRoot 指向 current/public/）
├── releases/
│   ├── 20260727-120000/           # 旧版本（保留用于回滚）
│   └── 20260727-133000/           # 当前版本
└── shared/                        # 跨版本共享，不随代码更新被覆盖
    ├── .env                       #   敏感配置（数据库密码、JWT 密钥等）
    ├── runtime/                   #   缓存 / 日志 / SQLite 数据库
    └── public/uploads/            #   用户上传的附件
```

**关键原则**：`.env`、`runtime/`、`public/uploads/` 三者在版本间共享，部署新版本时**不会丢失配置与数据**。

---

## 三、前置条件（仅需做一次）

1. **Web 服务器 DocumentRoot 指向 `DEPLOY_ROOT/current/public/`**（而非某个具体版本目录）。
   Nginx 示例：
   ```nginx
   server {
       listen 80;
       server_name contract.example.com;
       root /var/www/contract/current/public;   # 注意指向 current/public
       index index.php;

       location / {
           if (!-e $request_filename) {
               rewrite ^(.*)$ /index.php?s=$1 last;
               break;
           }
       }

       location ~ \.php$ {
           fastcgi_pass   unix:/run/php/php8.4-fpm.sock;
           fastcgi_index  index.php;
           include        fastcgi_params;
           fastcgi_param  SCRIPT_FILENAME $document_root$fastcgi_script_name;
       }
   }
   ```
2. **`DEPLOY_ROOT/shared/.env` 已配置**（数据库、密钥等）。首次部署时 `deploy.sh` 会从发布包内的 `demo.env.example` 复制为模板，再请你手动填入真实值。
3. **PHP CLI 可用**：部署脚本需要 `php` 命令执行数据库迁移。

---

## 四、首次部署

```bash
# 1. 把发布包传到服务器，进入包所在目录
cd /path/to/package

# 2. 执行首次部署（DEPLOY_ROOT 可自定义，默认 /var/www/contract）
DEPLOY_ROOT=/var/www/contract bash scripts/deploy.sh deploy contract-dingtalk-v2.35.8.tar.gz
```

脚本会自动完成：

1. 解包到 `releases/<时间戳>/`；
2. 初始化 `shared/`，把包内 `demo.env.example` 复制为 `shared/.env`（**已存在则跳过，不覆盖**）；
3. 符号链接 `shared/.env`、`shared/runtime`、`shared/public/uploads` 到当前版本；
4. 自动执行 `database/migration_*.sql`（见第六节）；
5. 原子切换 `current` 符号链接指向新版本；
6. 清理超出保留数（默认 5）的旧版本。

首次部署后，请编辑 `shared/.env` 填入真实数据库与密钥，并确认 Web 服务器已重启指向 `current/public/`。

---

## 五、后续更新（零停机）

拿到新版本发布包后，执行**完全相同的命令**：

```bash
DEPLOY_ROOT=/var/www/contract bash scripts/deploy.sh deploy contract-dingtalk-v2.35.9.tar.gz
```

旧版本保留在 `releases/` 下，随时可回滚。**整个切换过程对线上用户透明**。

---

## 六、数据库迁移自动化

`deploy` 子命令会自动执行发布包内 `database/migration_*.sql` 下的所有迁移脚本：

- **MySQL**：从 `shared/.env` 解析 `DB_HOST` / `DB_NAME` / `DB_USER` / `DB_PASS`（或 `MYSQL_*` 系列），逐文件执行；
- **SQLite**：直接对 `shared/runtime/data/contract.db` 执行。

迁移脚本均为**幂等**写法（如 `migration_v2.35.4_perm_version.sql` 用 `information_schema` 判重），重复执行安全。

> 若不想自动跑迁移，可手动执行后部署，或在部署前自行运行迁移脚本。

---

## 七、回滚（出问题秒级恢复）

```bash
# 回滚到上一版本（默认 N=1）
DEPLOY_ROOT=/var/www/contract bash scripts/deploy.sh rollback

# 回滚到更早的版本（N 表示往前第 N 个）
DEPLOY_ROOT=/var/www/contract bash scripts/deploy.sh rollback 2
```

回滚仅切换 `current` 符号链接，**数据不受影响**（数据在 `shared/` 中），瞬时完成。

---

## 八、查看状态 / 清理旧版

```bash
# 查看当前版本、历史版本列表、共享文件状态
DEPLOY_ROOT=/var/www/contract bash scripts/deploy.sh status

# 清理旧版本（默认保留最近 5 个）
DEPLOY_ROOT=/var/www/contract bash scripts/deploy.sh clean

# 自定义保留数量
DEPLOY_ROOT=/var/www/contract bash scripts/deploy.sh clean --keep=3
```

---

## 九、环境变量（可选）

| 变量 | 说明 | 默认值 |
|------|------|--------|
| `DEPLOY_ROOT` | 部署根目录 | `/var/www/contract` |
| `DB_TYPE` | 数据库类型 | `mysql`（可选 `sqlite`） |
| `KEEP` | 清理时保留的版本数 | `5` |

示例：

```bash
DEPLOY_ROOT=/opt/contract DB_TYPE=sqlite bash scripts/deploy.sh deploy contract-dingtalk-v2.35.8.tar.gz
```

---

## 十、查看脚本内置帮助

```bash
bash scripts/deploy.sh          # 打印用法
bash scripts/deploy.sh help     # 同上
```

---

## 十一、注意事项

1. **`.env` 不会被覆盖**：首次从包内 `demo.env.example` 生成 `shared/.env` 后，后续部署只链接、不覆盖。密钥变更请手动编辑 `shared/.env`。
2. **钉钉 / 微信回调地址**：配置回调 URL 时指向 `current/public/` 下的入口（如 `https://域名/dingtalk/entry`），不要写死具体版本目录，避免部署后回调失效。
3. **文件权限**：确保 Web 服务用户（如 `www-data`）对 `current/`、`shared/` 有读取权限；若使用 PHP-FPM，必要时 `chown -R www-data:www-data shared runtime`。
4. **符号链接支持**：共享主机若不支持符号链接，请改用 `DEPLOY.md` 中「传统方式升级（停服覆盖）」。
5. **回滚不回滚数据**：回滚只切代码符号链接，数据库结构以最新迁移为准；如迁移包含破坏性变更，回滚前请确认兼容。

---

## 十二、故障排查

| 现象 | 可能原因 | 处理 |
|------|----------|------|
| 部署后页面 500 | `shared/.env` 未配置或 DB 不可达 | 检查 `shared/.env`；`bash scripts/deploy.sh status` 看共享文件状态 |
| 新功能不生效 | 部署到了非 `current` 目录，或 Web 指向旧版本 | 确认 Nginx `root` 指向 `.../current/public/`；执行 `status` 核对当前版本 |
| 上传附件丢失 | `public/uploads` 未正确符号链接到 `shared/` | 检查 `shared/public/uploads` 是否存在、链接是否正确 |
| 想立即恢复 | 新版本有严重问题 | 执行 `rollback`，秒级切回上一版 |
