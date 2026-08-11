# 钉钉免登（自动登录）排查与配置说明

> 适用版本：v2.35.3 及以上
> 适用场景：系统已配置钉钉并同步了组织架构/用户，但**在钉钉工作台打开应用时仍要求输入账号密码、不能自动登录**。
> 文档目标：帮助运维/实施人员按顺序排查并正确配置，使「钉钉内打开 → 自动登录」生效。

---

## 1. 先厘清两件事：同步 ≠ 免登

很多同学会误以为「同步了用户就一定能自动登录」，其实这是**两条相互独立**的链路：

| 链路 | 作用 | 关键数据 |
|---|---|---|
| **同步（Sync）** | 把钉钉的部门、用户**拉进本地数据库** | `department` 表、`user.dingtalk_userid` |
| **免登（SSO）** | 钉钉工作台打开应用时，**凭钉钉身份自动建立本地会话** | `user.dingtalk_userid` + 钉钉免登接口 |

- **同步**只解决「本地有这个人」；
- **免登**要解决「钉钉知道你是谁 → 系统认出你 → 直接登录」。

只同步、不实配免登，打开应用必然还要输密码。

---

## 2. 免登工作原理（一句话看懂）

```
钉钉工作台点开应用
   ↓  (访问「应用首页地址」)
/dingtalk/entry?to=/dashboard
   ↓  前端注入 dd.config → requestAuthCode 拿到临时 code
POST /dingtalk/sso-login  { code }
   ↓  后端用 code 换 userid（钉钉 user/getuserinfo）
按 user.dingtalk_userid 匹配本地账号
   ↓  匹配成功 → 写 Cookie 会话
自动跳转 /dashboard（已登录，无需输密码）
```

如果「应用首页地址」不是 `/dingtalk/entry`，而是 `/login`、`/` 或 `/dashboard`，
链路第一步就断了 → 直接进登录页 → 必须输密码。**这是最常见的根因。**

---

## 3. 已修复的代码缺陷（v2.33.1）

登录页 `app/view/auth/login.php` 内嵌的钉钉免登脚本，原有一处致命 BUG：

```js
// 修复前（错误）：corpId 被写死为空字符串，且缺少 dd.config 权限注入
dd.runtime.permission.requestAuthCode({ corpId: '', onSuccess: ... });
```

- `corpId` 为空 → `requestAuthCode` 调用必失败 → 免登永远不会触发 → 退回输密码。
- 缺少 `dd.config` 注入 → 钉钉 JSAPI 无调用权限。

**v2.33.1 已修复**：改为先 `GET /dingtalk/jsapi-config` 取真实 `corpId`/`agentId`，正确 `dd.config` 注入权限后再 `requestAuthCode`，成功后尊重 `redirect` 深链。

> ✅ 该修复已经 jsdom 端到端验证：在 Mock 钉钉环境下，`dd.config.corpId` 与 `requestAuthCode.corpId` 均取到真实注入值（非空），免登 `onSuccess` 正常触发。
> ❗ 请确认你部署的包版本 **≥ v2.33.1**，否则请先升级。

---

## 3.1 v2.35.1 关键修复：钉钉打开应用报 `dd is not defined`

**现象**：在钉钉工作台 / 移动端打开应用，控制台报错 `Uncaught ReferenceError: dd is not defined`，免登完全失效。注意这是与「打开要输密码」**不同的症状**——本问题连登录页都进不去，直接脚本报错中断。

**根因**：钉钉免登依赖全局 `dd` 对象，而该对象由官方 JSAPI SDK 脚本（`https://g.alicdn.com/dingding/dingtalk-jsapi/3.1.0/dingtalk.open.js`）定义。**新版钉钉客户端 / 工作台 H5 不会自动注入 `dd`**，本系统此前从未引入该 SDK，导致 `dd` 恒为 `undefined`；且 `/dingtalk/entry` 旧代码用 `typeof dd === 'undefined'` 判定「非钉钉环境」，进一步把回退逻辑带偏。

**修复（v2.35.1）**：
1. `app/view/dingtalk/entry.php`、`app/view/auth/login.php` 的 `<head>` 均**显式引入官方 JSAPI SDK**，加载后 `dd` 即被定义。
2. 钉钉环境判定**改为以 UA 为主**（`/DingTalk/i`），不再依赖 `dd` 是否存在。
3. `entry.php` 增加 **8 秒看门狗**：SDK 加载失败 / 用户拒绝授权 / 权限不足时自动回退 `/login?redirect=...`，避免永久转圈。
4. 免登调用兼容新旧 SDK：`dd.getAuthCode` 优先，否则 `dd.runtime.permission.requestAuthCode`；`dd.config` 包 try/catch。

**部署注意**：本修复仅改 2 个视图文件，打包版本 ≥ v2.35.1 即含；若自行定制免登入口页，**必须自行引入上述 SDK**，否则仍会 `dd is not defined`。

---

## 4. 排查清单（按优先级，从上往下查）

### P0 钉钉后台「应用首页地址」配错了（最常见，占 80% 案例）

打开**钉钉开发者后台 → 你的应用 → 应用设置 / 基础信息 → 应用首页地址**，确认填的是：

```
PC / 桌面端：  https://你的域名/dingtalk/entry?to=/dashboard
移动端：        https://你的域名/dingtalk/entry?to=/m
```

- ❌ 错误填法：`https://域名/`、`/login`、`/dashboard`、`/admin` —— 这些都会直接进登录页。
- `to` 参数决定免登成功后跳哪：PC 用 `/dashboard`，手机用 `/m`。

### P1 系统 `.env` 钉钉凭据未配齐 或 处于演示模式

编辑项目根目录 `.env`，确认以下项**全部非空**且**非占位符**：

```dotenv
DINGTALK_APP_KEY=xxxx          # 钉钉应用 AppKey
DINGTALK_APP_SECRET=xxxx        # 钉钉应用 AppSecret
DINGTALK_CORP_ID=xxxx           # 企业 CorpId
DINGTALK_AGENT_ID=xxxx          # 应用 AgentId
DINGTALK_MOCK_MODE=false        # ❗必须为 false（生产模式）
```

- 若 `DINGTALK_MOCK_MODE=true`：生产环境免登会被安全拦截，直接报「演示模式禁用」。
- 改完 `.env` 需**重启 PHP 服务 / 清空缓存**生效。

### P2 钉钉后台权限与可信域名未开

在**钉钉开发者后台 → 你的应用 → 权限管理**确保已开通：
- 「成员信息读权限」（用于免登后获取 userid）
- JSAPI 权限：`runtime.permission.requestAuthCode`

在**钉钉开发者后台 → 你的应用 → 安全设置（或开发管理）→ 可信域名 / JS 安全域名**：
- 把你的服务器域名（如 `yourdomain.com`，**不含 https://**）加入白名单。
- 未加白名单时 `requestAuthCode` 会报「无权限」或「域名不在安全域内」。

### P3 用户 `dingtalk_userid` 未真正同步写入（数据前提）

免登后端是按 `user.dingtalk_userid` 匹配本地账号的。即使「同步」按钮点过，
若当时 `MOCK_MODE=true` 或凭据无效，userid 不会写入，免登照样匹配不到用户。

**数据库核查（SQLite 示例）**：
```sql
SELECT CASE WHEN dingtalk_userid='' OR dingtalk_userid IS NULL THEN '空(未同步)' ELSE '非空(已同步)' END AS 状态,
       count(*) AS 人数
FROM user GROUP BY 状态;
```
- 期望结果：所有用户 `dingtalk_userid` **非空**。
- 若大量为空：重新点「同步钉钉」（确保 P1 凭据正确、`MOCK_MODE=false`），同步成功后再打开应用。

### P4 部署版本过旧

确认线上包版本 ≥ v2.33.1（见第 3 节修复）。低于该版本请先升级。

### P5 消息卡片（action_card）点击只跳登录页、需先经工作台进入才能免登

**现象**：工作台打开应用能自动登录；但钉钉里点审批消息卡片（`single_url` 指向 `/dingtalk/entry?to=/approval/{id}`）却只进登录页，必须先去工作台进入一次应用、再回来点消息才正常。
**本质**：消息卡片打开的是**独立的消息 webview**，其与工作台打开的「微应用容器」不是同一个 webview；会话 Cookie 在两者之间**共享**，所以工作台登录后消息点击能命中已登录态直接跳转——但这只是绕过，并非消息 webview 自身免登成功。消息 webview 自身免登失败的根因通常是 `dd.ready` 未触发或 JSAPI 原生桥缺失。

**已修复（v2.37.3）**：`dingtalk/entry.php` 与 `auth/login.php` 改为**不依赖 `dd.ready`** 直接调用 `dd.getAuthCode`（钉钉 JSAPI 2.0 下该接口可独立调用），`dd.config` 仅 best-effort、失败不阻断。多数情况下消息 webview 现可独立免登直达。

**若升级 v2.37.3 后仍只跳登录页**，说明消息 webview 确实拿不到 JSAPI 原生桥，属于**钉钉后台配置**问题（代码层已无能为力），按以下顺序核查：
1. **可信域名（JS 接口安全域名）**：钉钉开发者后台 → 应用 → 安全设置 → 把服务器域名（不含 `https://`）加入「可信域名 / JS 接口安全域名」。消息 webview 要能用 `dd.getAuthCode` 必须在此白名单内（工作台首页通常已配，但消息链接可能被当成独立域名校验）。
2. **`single_url` 是否被当作外部链接**：若钉钉把 `single_url` 当普通外链打开（而非以微应用身份打开），则 webview 无微应用 JSAPI 上下文，`dd.getAuthCode` 必然失败。可尝试把 `single_url` 与「应用首页地址」保持同域同路径（`https://域名/dingtalk/entry?to=...`），并确保应用已发布到该组织。
3. **移动端/PC 端分别验证**：iOS / Android / PC 钉钉对消息 webview 的 JSAPI 注入行为可能不同，逐一确认。

---



## 5. 标准配置步骤（新环境一次配好）

### 步骤 1：系统 `.env` 配置
填写第 4 节 P1 的五项，保存后重启服务。

### 步骤 2：后台执行一次同步
登录系统后台 → 用户管理页 → 点「同步钉钉」（或在钉钉设置页同步）。
- 观察日志/提示：成功会显示同步了 N 个部门、M 个用户。
- 同步后按 P3 的 SQL 复核 `dingtalk_userid` 非空。

### 步骤 3：钉钉开发者后台配置
1. 填「应用首页地址」为 `/dingtalk/entry?to=/dashboard`（PC）、`?to=/m`（移动）。
2. 开通免登相关权限（P2）。
3. 把服务器域名加入「可信域名 / JS 安全域名」（P2）。

### 步骤 4：钉钉工作台验证
在钉钉里打开应用，应**直接进入系统首页，不弹登录框**。

---

## 6. 验证方法

### A. 后端接口自测（确认配置下发正常）
浏览器/接口工具访问（需登录态或内网）：
```
GET https://你的域名/dingtalk/jsapi-config
```
期望返回（示例）：
```json
{
  "corpId": "真实企业CorpId",
  "agentId": "真实AgentId",
  "timestamp": "1700000000",
  "nonceStr": "xxx",
  "signature": "xxx",
  "url": "https://你的域名/dingtalk/entry"
}
```
若 `corpId` 为空或为占位符 → 回到 P1 检查 `.env`。

### B. 数据库核查
见第 4 节 P3 的 SQL。

### C. 日志核查
查看 `runtime/log/` 下日志，搜索关键字：
- 「钉钉免登失败」「MOCK 模式被拦截」「requestAuthCode」「userid 不匹配」
- 有相关报错按对应章节处理。

### D. 浏览器实测
在钉钉内（非浏览器）打开应用，确认无登录框、直接进入。

---

## 7. 常见问题（FAQ）

**Q1：同步成功了，为什么打开还是要密码？**
同步只解决「本地有这个人」，自动登录还需：① 首页地址指向 `/dingtalk/entry`（P0）；② 用户 `dingtalk_userid` 非空（P3）；③ 版本 ≥ v2.33.1（P4）。三项缺一不可。

**Q2：本地开发能测免登吗？**
需真实钉钉 AppKey/Secret/CorpId 且 `MOCK_MODE=false`；本地若只想看界面效果，可临时 `DINGTALK_MOCK_MODE=true`，但免登链路会被安全拦截（仅用于演示同步 UI）。

**Q3：手机钉钉和 PC 钉钉都要配吗？**
都要。两者「应用首页地址」相同，仅 `to` 参数不同（移动 `?to=/m`，PC `?to=/dashboard`）。

**Q4：改了 `.env` 没生效？**
重启 PHP 服务（如 `php think run` 或 Web 服务器），并清空 `runtime/cache`。

**Q5：requestAuthCode 报「无权限 / 域名不在安全域」？**
见 P2：补开免登权限 + 把域名加入钉钉后台「可信域名 / JS 安全域名」。

---

## 8. 相关代码位置（供二次开发参考）

| 文件 | 作用 |
|---|---|
| `app/view/dingtalk/entry.php` | 免登入口页（前端 JSAPI 初始化 + 跳 sso-login） |
| `app/view/auth/login.php` | 登录页（v2.33.1 修复了 corpId 空串 BUG） |
| `app/controller/DingTalkController.php` | `entry` / `jsapiConfig` / `ssoLogin` 接口 |
| `app/common/logic/DingTalkLogic.php` | 免登换票、签名、userid 匹配逻辑 |
| `app/common/service/DingTalkService.php` | 同步组织、`user.dingtalk_userid` 写入 |
| `route/app.php` | 路由：`/dingtalk/entry`、`/dingtalk/jsapi-config`、`/dingtalk/sso-login`、`/ajax/dingtalk/sync-org` |

---

*本文档基于 v2.33.1（corpId 空串修复）与 v2.35.1（JSAPI SDK 引入 / dd is not defined 修复）实际代码与链路撰写。排查「打开要输密码」严格按 P0→P4 顺序，绝大多数落在 P0（首页地址）与 P3（userid 未同步）；若控制台报 `dd is not defined` 见第 3.1 节（与输密码是不同症状）。*
