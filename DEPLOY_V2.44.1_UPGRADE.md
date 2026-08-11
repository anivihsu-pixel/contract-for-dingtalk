# v2.44.1 部署操作手册

> **适用场景**：线上已部署 **v2.43.5 及更早版本**，升级到 **v2.44.1**。
> **升级性质**：PATCH 安全修复（P0/P1/P2 全批次），含 1 个跨版本必需数据库迁移。

---

## 一、本版本变更与影响（先读这里）

| 类别 | 说明 |
|------|------|
| 代码变更 | P0 存储型 XSS（上传文件名净化、视图 JSON_HEX）、P0 跨部门数据泄露（PartyLogic 数据范围）、P1 认证/审批/合同/路由加固、P2 公式注入中和/JSON_HEX 补齐/索引/孤儿附件清理/导出超时/口令置空/死配置清理/重复检测精度 |
| **数据库** | **v2.44.1 本身无 DB 变更**；但跨版本升级（v2.43.5 → v2.44.1）**必须执行 `database/migration_v2.43.6_library_perms.sql`**（v2.44.0 的资料库权限拆分，见第二节） |
| **配置（升级前必改）** | **`.env` 必须显式配置 `DB_PASS`**——v2.44.1 起代码内不再内置默认口令 `root/root`，缺省空串将无法连接数据库 |
| 静态资源 | 包内含 v2.44.0 的 office-preview 三渲染库（docx-preview/jszip/xlsx），零停机部署随包自动生效 |

**权限拆分的影响（若未执行迁移）**：manager / gm 等非 admin 角色在 PC 资料库将无上传/编辑/删除入口（后端按 `library:upload/edit/delete` 权限码拦截），查看/预览/下载正常。admin（is_admin 短路）不受影响。

---

## 二、升级前准备（5 分钟）

### 1. 备份（强制）

```bash
# 数据库备份（SQLite）
cp -a /var/www/contract/shared/runtime/data/contract.db /var/www/contract/shared/runtime/data/contract.db.bak_$(date +%Y%m%d)

# 或 MySQL
mysqldump -h 127.0.0.1 -u root -p contract_dingtalk > contract_dingtalk_$(date +%Y%m%d).sql

# 发布包与当前目录快照
cp -a /var/www/contract /var/www/contract_bak_$(date +%Y%m%d)   # 传统覆盖式建议全量备份
```

### 2. 检查并更新 `.env`（关键）

编辑 `DEPLOY_ROOT/shared/.env`，**确认以下项显式存在**（v2.44.1 起 `DB_PASS` 无代码默认值）：

```ini
DB_TYPE=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=contract_dingtalk
DB_USER=你的数据库用户
DB_PASS=你的数据库口令   # ← 必须显式填写，缺省会连不上库
```

改完后**先重启一次应用/清理缓存验证连接**，再继续部署（避免升级后才发现连不上）。

### 3. 确认环境

```bash
php -v          # 建议 PHP 8.x
which sqlite3   # SQLite 部署需要；MySQL 部署需要 mysql 客户端
```

---

## 三、方式 A：零停机部署（推荐，symlink 模式）

> 若线上已是「current → releases/时间戳」symlink 结构（v2.35.8 起），一条命令完成升级，迁移自动执行，切换对用户透明。

```bash
# 把发布包传到服务器
scp contract-dingtalk-v2.44.1.tar.gz user@server:/var/www/contract/

# 进入包所在目录执行升级（DEPLOY_ROOT 按实际部署路径）
cd /var/www/contract
DEPLOY_ROOT=/var/www/contract bash scripts/deploy.sh deploy contract-dingtalk-v2.44.1.tar.gz
```

脚本自动完成：解包到 `releases/<时间戳>/` → 链接 `shared/`（.env / runtime / public/uploads 跨版本保留，**不覆盖**）→ **自动执行全部 `database/migration_*.sql`（含 v2.43.6 权限拆分）** → 原子切换 `current` 符号链接 → 清理旧版本（默认保留 5 个）。

升级后确认：

```bash
DEPLOY_ROOT=/var/www/contract bash scripts/deploy.sh status
# 期望：当前版本 releases/<时间戳>（系统版本 v2.44.1）；shared 三项 ✓
```

### 若当前是传统覆盖式部署（非 symlink）

需**一次性迁移到 symlink 结构**后再走方式 A：

```bash
# 1. 备份线上目录（见第二节第 1 步）
# 2. 停 Web 服务，把旧目录整体移到 shared 并搭 symlink 骨架
mv /var/www/contract /var/www/contract_old
mkdir -p /var/www/contract/shared
mv /var/www/contract_old/.env /var/www/contract/shared/.env
mv /var/www/contract_old/runtime /var/www/contract/shared/runtime
mv /var/www/contract_old/public/uploads /var/www/contract/shared/public/uploads
# 3. 执行首次部署（会从包内复制缺失的 shared 项并链接）
DEPLOY_ROOT=/var/www/contract bash scripts/deploy.sh deploy contract-dingtalk-v2.44.1.tar.gz
# 4. Web 服务器 DocumentRoot 指向 /var/www/contract/current/public，重启
```

> 迁移后**钉钉/微信回调地址应指向 `current/public` 入口**（如 `https://xxx/dingtalk/entry`），勿写死某个版本目录路径——版本切换后写死路径会失效。

---

## 四、方式 B：传统覆盖式部署（非 symlink，可停机）

```bash
# 1. 备份（第二节第 1 步）
# 2. 解压覆盖
cd /var/www/contract
tar -xzf contract-dingtalk-v2.44.1.tar.gz   # 解压到新目录后整体替换代码文件
# 3. 手动执行数据库迁移（见第五节）
# 4. 清理缓存（让新配置/版本号立即生效）
rm -rf runtime/cache/*
# 5. 重启 PHP-FPM / Web 服务
```

---

## 五、数据库迁移（deploy.sh 自动执行；手动执行方法）

升级到 v2.44.1 涉及的唯一必需迁移：**`database/migration_v2.43.6_library_perms.sql`**（资料库权限拆分：删除旧 `library:manage`(33)，新增 `library:upload`(44) / `library:edit`(45) / `library:delete`(46)，并绑定 admin/manager/gm 三角色）。脚本为**幂等**写法，重复执行安全。

**MySQL 手动执行：**

```bash
mysql -h 127.0.0.1 -u root -p contract_dingtalk < database/migration_v2.43.6_library_perms.sql
```

**SQLite 手动执行：**

```bash
sqlite3 runtime/data/contract.db < database/migration_v2.43.6_library_perms.sql
```

**迁移后验证（应各返回对应行）：**

```sql
-- MySQL
SELECT id,name,code FROM permission WHERE code LIKE 'library:%';   -- 应含 upload/edit/delete 三条
SELECT COUNT(*) FROM role_permission rp JOIN permission p ON p.id=rp.perm_id
  WHERE p.code LIKE 'library:%';                                     -- 应 ≥9（3 权限 × 3 角色）
```

---

## 六、升级后验证清单

| 验证项 | 方法 | 期望 |
|--------|------|------|
| 版本号 | 登录后「系统配置→当前版本」/ 侧栏底部 | v2.44.1 |
| 登录与会话 | 表单登录 admin，跳转 dashboard | 正常，无强制登出 |
| 核心页面 | 依次访问 `/dashboard` `/contract` `/approvals` `/finance` `/m` | 200 正常渲染 |
| 资料库权限 | 用 manager/gm 登录，PC 资料库页 | 有上传/编辑/删除入口（迁移已生效）；未迁移则只有查看 |
| 导出 CSV | 合同列表导出 CSV 后用文本编辑器打开 | `=` `+` `-` `@` 开头字段前置单引号 `'` |
| 回收站彻底删除 | `/recycle` 对一条已软删合同执行彻底删除 | 无引用附件物理文件被清理 |
| 钉钉免登 | 工作台打开应用 | 免登正常（回调地址未写死版本目录） |

---

## 七、回滚

**方式 A（symlink）——秒级回滚，数据不受影响：**

```bash
DEPLOY_ROOT=/var/www/contract bash scripts/deploy.sh rollback
```

**方式 B（覆盖式）——用升级前备份还原：**

```bash
# 还原第二节第 1 步的备份（代码目录 + 数据库），重启服务
```

> 回滚后如需保留新库数据，注意 migration 是幂等可重跑、权限拆分无破坏性回退需求。

---

## 八、常见问题（FAQ）

**Q1：升级后报数据库连接失败？**
`.env` 缺 `DB_PASS` 或值为空。v2.44.1 起无代码默认口令，补齐 `DB_PASS` 后清理缓存重启。

**Q2：迁移报错「表已存在/字段已存在」？**
迁移脚本幂等（`WHERE NOT EXISTS` / 先删后插判空），重复执行安全；报错多为已执行过，忽略即可，deploy.sh 也会标「已跳过或失败」继续。

**Q3：manager/gm 在资料库没有上传/编辑/删除？**
`migration_v2.43.6_library_perms.sql` 未执行（或角色权限未勾选新权限码）。手动执行迁移后到「系统配置→角色权限」确认新码勾选状态。

**Q4：侧栏/系统配置仍显示旧版本号？**
缓存未清理。`rm -rf runtime/cache/*` 后刷新；确认 `config/version.php` 内容为 `return 'v2.44.1';`。

**Q5：升级过程中用户是否受影响？**
方式 A 零停机：切换符号链接瞬时完成，迁移在切换前执行，线上用户无感知；方式 B 覆盖式需短暂停机窗口，建议业务低峰执行。

---

*随包交付：`contract-dingtalk-v2.44.1.tar.gz`（SHA256 `091e69183cf52d34ab343b9d2dc852c739391ea8f1ec1667ccd8c3c656dd7b0c`）。详细部署架构见《零停机部署说明.md》，常规运维见《部署说明.md》。*
