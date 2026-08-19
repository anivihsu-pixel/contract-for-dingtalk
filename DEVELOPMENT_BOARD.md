# 开发进度看板（产品经理窗口）

> 本看板用于管理合同管理系统的开发进度：需求 → 开发 → 闭环测试 → 发布，集中跟踪模块状态与待办。
> 最后更新：2026-08-19

## 一、项目概览

| 项 | 说明 |
|---|---|
| 项目 | 合同管理系统（PC + 移动端） |
| 当前版本 | v2.51.14（2026-08-19） |
| 技术栈 | ThinkPHP 8.0 / PHP 8.4 / MySQL（演示库，本地 127.0.0.1:3307 contract_dingtalk） / 钉钉集成 |
| 演示地址 | `http://0.0.0.0:8099`（局域网可访问） |
| 演示账号 | admin / 85151818；manager01、employee01、finance01（密码 password） |
| 服务方式 | `php think run -H 0.0.0.0 -p 8099` |

## 二、产品模块看板

| 模块 | 需求 | 开发 | 闭环测试 | 状态 | 备注 |
|---|---|---|---|---|---|
| 认证（登录/登出/改密/失败锁定） | ✅ | ✅ | ✅ | 已发布 | 失败 5 次锁定 15 分钟 |
| 客户域（客户/联系人/跟进/转移/共享/集团/360） | ✅ | ✅ | ✅ | 已发布 | 数据范围 SELF/DEPT/ALL；移动端详情页含编辑资料入口、联系人增删改；PC 详情页操作区下移+内嵌往来统计卡+基本信息联系人卡片+集团归属标注+集团层级树（归属路径面包屑/图标缩进/本客户高亮）；PC 列表/详情删除入口（软删除+关联校验+级联清理从属数据） |
| 供应商 | ✅ | ✅ | ✅ | 已发布 | CRUD；PC 列表/详情删除入口（软删除+关联采购合同校验） |
| 项目（含验收/终止/撤销） | ✅ | ✅ | ✅ | 已发布 | 终止联动合同 |
| 合同生命周期（新建→审批→执行→回款→开票→归档） | ✅ | ✅ | ✅ | 已发布 | 10 态状态机 |
| 审批中心（待办/已办/我提交/通过/驳回/转交/撤回） | ✅ | ✅ | ✅ | 已发布 | 多级审批流 + 抄送 |
| 发票闭环（申请→审批→开票→红冲→作废→驳回重提） | ✅ | ✅ | ✅ | 已发布 | 独立发票审批流；随合同申请开票（v2.51.10 过审自动生成待开票发票 + 流程级开票通知人，v2.51.14 入口迁移至合同编辑页底部并复用申请开票表单）；PC 开票申请撤回入口（v2.51.14） |
| 财务中心 + 报表（收支概览/税务/月报/周报/账龄） | ✅ | ✅ | ✅ | 已发布 | 只读可见、写操作权限隔离 |
| 移动端（工作台/客户/合同/审批/财务/发票/报表） | ✅ | ✅ | ✅ | 已发布 | 钉钉免登；部门经营详情页（工作台/周报部门行可点击进入） |
| 系统管理（用户/角色/审批流/字典/配置/离职交接） | ✅ | ✅ | ✅ | 已发布 | 离职交接批量转移 |
| 归档 / 数据回收站 | ✅ | ✅ | ✅ | 已发布 | 回收站恢复/彻底删除仅超管 |
| 审计 / 资料库 / 全局搜索 / 提醒 / 消息中心 | ✅ | ✅ | ✅ | 已发布 | 中央审计日志；审计中心已中文化 + 对象标题 + 详情展开 |
| 侧边栏「提醒」入口 | — | — | — | 已移除 | 仪表盘「今日提醒/草稿提醒」已覆盖，未读提醒仅经仪表盘呈现 |
| 客户公海体系 | — | — | — | 已移除 | 按需求下线，生命周期仅 POTENTIAL/ACTIVE |
| 合同变更（revision） | — | — | — | 已移除 | 按需求下线 |

## 三、全功能闭环测试结果（2026-08-15）

| 测试域 | 通过/总数 | 结果 |
|---|---|---|
| 审批中心转交闭环 | 12/12 | ✅ |
| 移动端闭环（19 页面 + 修复点回归） | 22/26 | ⚠️ MySQL 演示库纯净种子态：4 项失败依赖历史审批实例/客户 360 动态（seed_demo 不产生这两类运行时数据，SQLite 演示库的历史数据为测试残留，不迁移） |
| 权限 / 数据范围（四角色视角） | 18/18 | ✅ |
| 财务中心 + 报表 | 10/10 | ✅ |
| 系统管理（用户 CRUD + 离职交接） | 29/29 | ✅ |
| 回收站 / 归档 / 审计 / 资料库 / 搜索 / 提醒 | 20/20 | ✅ |

回归脚本（可复跑）：`test_approval_transfer.php`、`test_mobile.php`、`test_perm.php`、`test_finance.php`、`test_admin.php`、`test_misc.php`

### 测试中发现并已修复

| 问题 | 修复 |
|---|---|
| 移动工作台 `/m` 500（待办流 `kind` 缺键） | [index.php](app/view/mobile/index.php) 模板改为 `($t['kind'] ?? '')` 容错 |
| 创建重复用户名返回 500 | [AdminController.php](app/controller/AdminController.php) `saveUser` 增加用户名唯一性前置校验，返回"用户名已存在，请更换后重试"（新建/改名共用） |
| 移动端新建合同点击日期后「提交」按钮消失 | [mobile-common.js](public/static/js/mobile-common.js) 键盘遮挡处理排除 date/datetime-local/time 等原生选择器控件（聚焦不触发失焦，导致 `.m-submitbar` 永久隐藏） |
| 移动端申请开票关联合同未真正生效 | [invoice_apply.php](app/view/mobile/invoice_apply.php) / [finance.php](app/view/mobile/finance.php) 选择合同仅更新闭包变量，提交读取的隐藏 input 恒为 0——选中时同步写回隐藏字段 |
| 移动端新建/编辑客户、供应商页底部字段被提交栏遮挡 | [customer_form.php](app/view/mobile/customer_form.php) / [supplier_form.php](app/view/mobile/supplier_form.php) 补 `.m-page.has-submitbar` 预留底部空间 |
| PC 客户详情页错位（main-content 塌缩至 182px、统计卡 30px、Tab 游离出主容器） | [detail.php](app/view/customer/detail.php) 跟进记录循环（t-act）多输出一个 `</div>`，HTML 解析器容错导致 t-stat 起 4 个 tab-pane 被挤出 `.main-content`、flex-grow 失效——凡有跟进记录的客户详情页均错位（演示数据中集团客户恰好都有跟进记录）；已删冗余 `</div>`，1440px 下 main-content 恢复 1205px、统计卡 286px、8 个 Tab 全部归位 |
| PC 发票申请列表发票类型显示英文枚举 | [invoice-apply.js](public/static/js/invoice-apply.js) 直接输出 `invoice_type` 原始值；[index.php](app/view/invoice_apply/index.php) 注入 `window.__invTypeLabels`（`dict('invoice_type')`），「我的申请/待开票」类型列映射为中文（VAT_SPECIAL→我要开增值税专用发票 等），无匹配回退原文 |

### 已知问题（待处理）

> 当前无未处理问题。

## 四、后续迭代 Backlog

- [x] 修复：重复用户名友好提示（2026-08-15 已修复并验证）
- [x] 移除侧边栏「提醒」入口及未读角标（2026-08-15，仅保留仪表盘「今日提醒/草稿提醒」）
- [x] 审计中心优化（2026-08-15）：补全 11 个操作中文映射；对象列显示合同标题/客户名称；详情 JSON 中文化（审批意见/转交人/离职交接/状态流转等）；超长详情「查看完整」弹窗；audit_log 新增 target_title 标题快照（写入时自动记录，对象删除后仍可定位）
- [x] PC 发票申请查看详情（2026-08-15）：三个 Tab（我的申请/待我审批/待开票）整行/内容点击跳转独立详情页 `/invoice-apply/detail?id=x`（基本信息 + 审批流水 + 返回）；数据查询抽为 `InvoiceController::detailData` 供 AJAX 与页面共用；可见范围：本人/财务/审批人
- [x] 移动端工作台精简（2026-08-15）：移除顶部三数字卡（待我审批/今日提醒/我的合同）；「今天要处理」与「今日提醒」合并为单卡（审批/消息优先、到期/回款/跟进提醒后置），并移除卡片右上角角标（原角标口径 `all_todo_total` 含提醒数、与列表条数对不上）；控制器同步移除 `getMyCount/visibility/getOutstandingCount` 等仅服务已删 UI 的取值；头部铃铛角标一并移除（未读审批消息已在「今天要处理」卡以「消息」徽章逐条展示，角标重复）
- [x] 移动端申请开票表单体验（2026-08-15）：开票主体/开票类型/开票内容统一「展示框 + 底部弹层」选择（`.m-pick-*` 公共样式提炼 + `initInvPickers` 通用组件，`InvoiceFormConfig::mobileRender` 渲染 `data-inv-pick` 结构），税率随主体带出；申请表单分区（客户信息 / 开票金额 与主体/类型等基本信息分隔，`.m-field-section`）；关联合同自动带出乙方抬头/税号（新增 `GET /ajax/contract/invoice-info`，前端 `mInvFillByContract` 回填，可再改选开票客户覆盖）；「我的申请」改顶部标签切换（申请开票 / 我的申请），提交成功后自动切到我的申请
- [x] 修复：移动端新建合同日期控件隐藏提交按钮（2026-08-15，mobile-common.js `NO_KBD` 排除原生选择器控件）；申请开票关联合同 `contract_id` 恒为 0（选中时同步隐藏 input）
- [x] 移除「我的申请」Tab 条数角标（2026-08-15，角标无信息增量，用户要求去掉；标签切换保留）
- [x] 补充 `migration_v2.51.3_audit_target_title.sql`（2026-08-15）：v2.51.3 审计中心「对象标题快照」新增 `audit_log.target_title` 列，但此前仅 init 脚本含该列、缺迁移脚本——2.51.1 老库升级会因缺列导致审计写入静默失败，现补齐幂等迁移（MySQL 自动执行；SQLite 手动 `ALTER TABLE audit_log ADD COLUMN target_title TEXT DEFAULT ''`）
- [x] 修复：PC 客户详情页错位（2026-08-15，detail.php 跟进记录循环冗余 `</div>` 导致 4 个 Tab 游离主容器、布局塌缩；已删冗余标签，三场景验证：集团成员/集团根/独立客户）
- [x] PC 客户详情基本信息增强（2026-08-15）：联系人卡片（原表格「联系人/手机」行拆出，展示联系人列表+主联系人标记+添加/编辑/删除）+ 集团归属标注（成员显示所属集团名、集团根显示子公司数、独立客户显示 —）；CustomerController::detail 补充 group_parent_name/group_child_count 查询
- [x] 修复：PC 发票申请列表发票类型显示英文（2026-08-15，invoice-apply.js 两处类型列接 `window.__invTypeLabels` 中文映射）
- [x] PC 客户详情集团 Tab 层级展示优化（2026-08-15）：树顶归属路径面包屑（根→…→本客户）；节点改图标+层级缩进（根 bi-diagram-3、有下级 bi-folder2-open、叶子 bi-building、本客户 bi-buildings）+「集团/本客户/N 家下级」标签，本客户品牌浅底高亮（.g-tree/.g-node/.g-crumb CSS，detail.php renderGroup/buildTreeHtml 重写 + buildPath 定位祖先链）
- [x] 移动端客户详情操作区轻量化（2026-08-15）：概要卡「编辑资料/转移」由 48px 大按钮（各占半行）改为紧凑胶囊 `.m-act`（12px 小字+图标，左对齐，flex-wrap 防溢出，margin-left:0 覆盖 .m-act 默认右推）；doCustTransfer 禁用逻辑适配 a 标签（pointerEvents/opacity 替代 disabled）；联系人卡片显示姓名+手机号+备注（保留主标记与编辑/删除，去邮箱）
- [x] 跟进记录「用户#id」改为实际用户名（2026-08-15）：CustomerLogic::transfer / AdminLogic::handoverUser 生成跟进文案时查询来源用户真实姓名（查不到才回退「用户#id」）；存量 7 条记录中 2 条可映射的已替换（用户#1→系统管理员、用户#19→离职测试乙），其余 5 条来源用户已物理删除、保留原文
- [x] 修复：移动端新建合同收付款方向/合同性质选择后「创建合同」按钮消失（2026-08-15，mobile-common.js `isInput` 将 select/radio/checkbox 等不弹软键盘的控件误判为输入控件——聚焦即隐藏 `.m-submitbar`、选择后焦点停留不触发失焦恢复；`NO_KBD` 补 radio/checkbox/button/range/file，并移除 SELECT 分支；Playwright 回归：select 聚焦/选择、radio 聚焦提交栏均可见，text 输入框聚焦隐藏/失焦恢复不受影响）
- [x] 修复：移动端新建合同关联项目搜索下拉被遮挡（2026-08-15，两层根因——① `details.m-fold`（更多选项白底卡片）`overflow:hidden` 裁剪 absolute 弹出的搜索下拉，改 `visible`；② 搜索框位于表单最底部、软键盘为 overlay 模式（`innerHeight` 不变），`placeSuggest` 自适应定位升级：用 `visualViewport` 计算真实可视区 + 监听 visualViewport/window resize + 页面 scroll（捕获）重算展开方向与高度，聚焦延迟 350ms 补算；卡片上限 240→280px；实测下拉可完整浮出卡片、滚动贴底时向上展开、视口缩小后重算均完全可见）
- [x] 移动端合同/客户/供应商表单提交按钮改造（2026-08-15）：去掉 fixed 悬浮提交栏（`.m-submitbar`），按钮直接放入表单内容末尾随内容滚动——不再悬浮遮挡字段；去掉按钮对勾图标 `bi-check-lg`；按钮居中自适应宽度（`flex:none; min-width:160px`）；同步清理死代码：mobile-common.js 键盘遮挡 IIFE、mobile.css `.m-submitbar`/`.m-page.has-submitbar`、contract_form 内联 `.m-fold-badge` 残留样式；失败还原文案同步去图标
- [x] 移动端新建合同移除「已选N项」徽章（2026-08-15，更多选项折叠标题 `foldBadge/foldCount` 数值小看不清，用户要求去掉；ContractFormConfig::mobileRenderAll 删徽章 DOM，contract_form.php 删 refreshFoldBadge + MutationObserver）
- [x] 移动端客户列表标签重排（2026-08-15）：「正常」状态标签移到行最前面（行业标签之前），标签行 `justify-content:space-between` → `normal` + `gap:8px`，状态/行业标签左对齐、归属标签 `margin-left:auto` 右推；服务端渲染与 JS `cardHtml` 两处同步改（验证：正常 x=28 最左、行业 x=76、归属 x=275）
- [x] 新增客户来源/行业必填且不默认选项（2026-08-15）：移动端/PC 表单来源下拉去除默认「手动录入」改为空占位「请选择客户来源」、行业占位「未设置」→「请选择客户行业」并标红星必填；后端 CustomerController::save 来源白名单校验 + 新建时必填（`getPost('source', null)` 判字段显式存在，合同/发票快速建档不传字段不受影响）；顺带修复 `$this->request` 属性不存在导致保存 500（改用 getPost 判空）；前端提交前校验拦截，编辑旧数据（行业为空）不强制
- [x] 移动端客户/供应商列表顶部统一设计（2026-08-15）：删除客户页生命周期漏斗卡片（含控制器 funnel 赋值、mobile.css `.lc-funnel-stage` 残留样式）；两页顶部统一「客户 / 供应商」等宽分段切换（直角无圆角）；布局顺序对齐为「切换 → 搜索 → 筛选 → 列表」，筛选标签容器 gap 统一 8px、搜索框/chip 样式一致；新增 `.m-hide-scrollbar` 隐藏标签横向滚动条（保留滑动能力，不显示下方线条）；筛选与栏目跳转验证正常
- [x] 审计中心权限收敛至管理员（2026-08-17）：AuditController index/list 改 `requireSuperAdmin`（BaseController 新增）；侧边栏审计中心入口仅超管可见。验收：admin 正常访问；manager01 页面 403、AJAX 403、入口隐藏
- [x] 钉钉同步用户默认授予普通用户角色（2026-08-17）：`DingTalkService::grantDefaultRole` 幂等授予 `role.code=user`（角色缺失仅记日志不中断）；组织同步新建用户与免登自动开户两处建号入口均接入。验收：新用户正确获得角色 5（普通用户）、重复调用不重复插入、演示库回归无影响
- [x] 系统配置备份/恢复纳入用户与角色（2026-08-17）：user 表纳入备份（含钉钉同步字段、密码哈希，UI 提示妥善保管）；恢复按 id 对齐 upsert——覆盖备份内用户资料、按原 id 补入、不删除备份之外的用户（保护合同/审批业务归属）；防自锁升级（user 参与恢复后 is_admin 可能被覆盖，以备份值为准+admin 角色兜底）；预览孤儿引用按「恢复后」视角判定。验收：导出含 user/role/user_role；恢复覆盖/补入/保留备份外用户均通过；备份降权当前账号被拦截；再次钉钉同步（更新分支不触碰 user_role）角色不被覆盖
- [x] 后台用户按部门关闭使用（2026-08-17）：部门树选中部门（范围跟随「包含子部门」勾选）→ 成员列表标题栏「禁用本部门成员」一键禁用；有进行中审批者与当前登录账号跳过并汇总原因（需先办离职交接转交审批）；确认弹窗展示部门/范围/成员名单 + 结果明细弹窗；用户列表删除图标 `bi-trash` → `bi-person-dash`（禁用语义）。验收：演示库真实审批数据（employee01×1、finance01×2）正确跳过、其余禁用；构造审批后追加跳过；当前登录账号防护生效；已禁用用户不重复处理
- [x] 修复：部署 MySQL 老库新建客户 500「数据表字段不存在:[credit_score]」（2026-08-17）：v2.38.3 引入 credit_score/lifecycle_status 时仅补了 high_risk 的迁移脚本，老库升级缺列致客户 INSERT 报 Unknown column（ThinkPHP 10500）→ 500。本地 MySQL 8.4.9 + init.sql 完整复现（删列→INSERT 同错→补列→通过）；新增合并迁移 `database/migration_v2.38.3_customer_credit_columns.sql`（一次补齐 credit_score/lifecycle_status/high_risk/credit_manual/industry 5 列，逐列幂等；本地实测删 5 列→执行→全恢复→复跑幂等→INSERT 通过）。部署库执行该文件即修复
- [x] 本地开发环境切换 MySQL（2026-08-17）：.env 改 `DB_TYPE=mysql`、`DB_PORT=3307`（独立数据目录 `data_dev`、root 空密码，与机器 3306 既有实例完全隔离，不独占端口/不注册服务）；新增 `dev_mysql_start.ps1` / `dev_mysql_stop.ps1` 一键启停；`php database/init_mysql.php` 建表+种子（32 表，customer 5 个 v2.38.3+ 列齐全）；端到端验证：登录（admin/85151818，force_reset 已清零）→ 强制改密 → 新建客户保存成功——此前的缺列 500 在 MySQL 下彻底消失
- [x] 移除客户信用评级功能（2026-08-17）：产品评估——轻量化合同客户管理，信用评分（0-100）无任何业务消费（不联动赊销/发货/审批），credit_manual 人工锁定补丁证明模型水土不服，逾期风险已由回款报表/仪表盘/提醒独立覆盖。移除 customer 表 credit_score/high_risk/credit_manual 三列（`database/migration_v2.51.7_remove_customer_credit.sql` 合并幂等 DROP，参照 v2.38.13 风格）、CustomerLogic 两个重算方法、`customer:credit-check` 命令及注册、PaymentMarkOverdue/autoMarkOverdue 联动文案、PC 表单评分输入与详情风险/评分展示（基本信息表重排保持四列对称）、common.php 死注释；init.sql/init_mysql.php/init_sqlite.php/seed_demo.php 同步去列。保留生命周期（漏斗）/行业/统一社会信用代码等档案属性。验收：php -l 12 文件全过；本地 MySQL 3307 执行迁移 remain=0、复跑 init_mysql 幂等；登录→新建客户保存成功→删除测试客户→详情页 200
- [x] 记录测试环境 admin 固定口令（2026-08-17）：init_mysql.php `$initPwdAdmin=85151818` 固定注释说明（上传 GitHub 前改回随机强口令）；底部 `Default login` 打印由写死的 `admin / password` 改为动态 `{$initPwdAdmin}`，杜绝打印与实现脱节误导。验收：php -l 通过；复跑 init_mysql.php 输出 `Default login: admin / 85151818`
- [x] 移动端提交审批抄送节点样式对齐（2026-08-17）：抄送知会由独立内联蓝色块改为 `m-flow` 列表内 `<li>`（与审批节点同列表行/分隔线/间距），节点名用 `m-flow-name`，角色与抄送人名渲染为蓝色 `m-tag m-tag-info`（区别于灰色审批人标签）。验收：php -l 通过；浏览器实测（临时草稿合同+含抄送流程）显示「部门经理审批→总经理审批→抄送知会（财务/王财务/李员工）」同一列表；测试数据已清理
- [x] 合同草稿私有化（2026-08-17，v2.51.9）：草稿（DRAFT）仅创建者本人与超管可见，部门/总经理等数据范围不再作用于草稿（未定稿内容不对外暴露）；正式合同保持原有数据范围。落地 ContractLogic 五处：新增 `applyDraftPrivacy()`（非超管拼 `status<>DRAFT OR owner_id=本人`），getList/countExportRows/eachExportRow 统一挂接、draftList 直接加归属人过滤、accessible 详情对非本人草稿返 null（超管全量放行）。验收：manager01 列表仅见自己草稿+部门内正式合同、他人草稿详情 404、自己草稿详情 200；admin 列表见全部草稿+正式合同、他人草稿/正式合同详情均 200；php -l 通过；测试数据已清理。无 DB 变更
- [x] 字典设置交互优化 + 无使用字典移除（2026-08-17，并入 v2.51.9 出包）：①**编辑/添加/删除提交后不折叠**——dictEdit/dictShowForm/dictDelItem 原 `location.reload()` 整页刷新导致字典全部收起，改为后端字典项操作返回最新快照（AdminLogic::dictRowData + 控制器透传 data），前端 `rebuildDictRow()` 局部重建该行（保持展开），dictToggleItem 同步改造；②**0% 免税无法停用修复**——编码为字符串 "0" 时后端 `!$itemKey` 判空被 `empty("0")` 误拦（PHP 陷阱），改严格 `$itemKey === ''`；__REORDER_ITEMS__ 的 array_filter 同样会滤掉 "0"，改为 `fn($v)=>$v!==''`；③**排序仅保留 ▲▼ 按钮**——HTML5 原生拖拽难以精确定位已移除（删 initDictDrag DnD 事件、.dict-dragging/.dict-drag-over 视觉样式、draggable 属性），每个启用项 ▲▼ 按钮（dict-move）点击一次移动一位立即保存；④**移除 4 个无使用字典**——全项目检索确认 dict_tax_rate（税率已绑定开票主体 company_profile.invoice_tax_rate）/dict_invoice_status/dict_payment_status（label 均视图硬编码 statusMap）/dict_data_scope（角色页硬编码 $scopes）已无任何 dict() 消费，删除 4 行 + 测试遗留 dict_disabled_tax_rate（`database/migration_v2.51.9_remove_unused_dicts.sql` 幂等），SYSTEM_DICT_KEYS 同步移除 3 项，种子脚本 init.sql/init_mysql.php/init_sqlite.php 同步去行。验收：php -l 5 文件全过；字典页 10 个字典无报错；编辑改 3% 名称/添加测试来源/删除均保持展开且数据正确；0% 免税停用成功落库 dict_disabled_tax_rate=["0"]；↑↓ 移动两位 DOM+DB 顺序一致后还原；发票申请/财务/角色管理/合同详情回归 200；测试数据已清理。**DB 变更**：部署库执行上述迁移文件
- [x] 用户管理编辑不刷新 + 禁用图标修复（2026-08-17）：**编辑不刷新**——saveUser 成功改为后端返回该用户最新行数据（`AdminController::buildUserRow` 新私有方法，与 index 注入 $users 同构，含 roles 角色名/_role_ids/_is_leader/dept_name），前端新增 `rebuildUserRow()` 用 DOM API 局部重建该行（首渲染行补 `data-user-id` 定位属性；行结构/操作按钮与 PHP 渲染同构，按钮事件用闭包避免引号转义），保存后自动关闭弹窗、不整页刷新、部门树选中与过滤保持（重建后重调 renderUserList）；delUser 保持原整页刷新——回收站数据为 PHP 首渲染快照、无 AJAX 接口，局部移除行会造成回收站列表不同步，故不纳入局部化。**禁用图标修复**——`bi-person-dash` 不在图标白名单子集（icons_whitelist.txt 缺失该图标 → CSS 无 `.bi-person-dash::before` 字形 → 按钮塌缩为空白小方块），全站 7 处统一换 `bi-x-circle`（白名单已有、实测渲染正常），行内/顶部/确认弹窗禁用按钮全部对齐。验收：php -l 通过；浏览器实测编辑保存行更新且 window 标记确认不刷新、弹窗自动关闭、重建行四按钮图标正常且尺寸统一（30×29）、连续编辑下一用户正常；禁用后回收站可恢复；测试数据已清理。无 DB 变更
- [x] 提交审批页用户/角色/部门标签统一蓝底（2026-08-17）：**移动端** mobile/approval_create.php 审批人标签 `m-tag-muted`（灰底）→ `m-tag-info`（#e8f1ff），与抄送标签一致；**PC 端** approval/create.php 审批人由灰色括号文本 → `pc-tag-info` 蓝底标签（去括号），抄送用户 `text-muted` → `pc-tag-info`（抄送角色本就 `pc-tag-info`）。三类标签（指定用户/角色/提交人部门负责人/抄送角色/抄送用户）全部统一 #e8f1ff 蓝底 / #0b5ed7 字。验收：php -l 通过；临时构造 部门+三类节点流程+抄送+DRAFT 合同 后 PC 与移动端提交审批页实测 5 处标签背景色均为 rgb(232,241,255)（#e8f1ff）；验证数据与临时脚本已清理（含恢复 admin 部门归属）。无 DB 变更
- [x] 提交审批页抄送角色只显示成员用户（2026-08-17）：审批节点 ROLE 本已由 ApproverResolver::resolve 解析为成员用户，无需改；抄送按角色不再列出角色名——ApprovalController::create 移除 ccRoleNames 组装（抄送角色仅 resolveRoleCodes 解析成员并入 ccNames）、has_cc 改为仅依赖实际成员、删除 role_map/cc_roles 视图变量；PC 端 approval/create.php 删除「角色：××」标签、移动端 mobile/approval_create.php 删除抄送角色循环，均只展示成员用户。验收：php -l 3 文件通过；构造 抄送角色 manager+指定用户 王财务 的流程与 DRAFT 合同，PC 与移动端提交审批页抄送均只显示「张经理、王财务」（无角色名），审批节点显示成员不变；验证数据与临时脚本已清理。无 DB 变更
- [x] PC 端快捷操作按钮样式统一（2026-08-18）：工作台「快捷操作」区 5 个按钮（新建合同/新建客户/审批/登记回款/申请开票）颜色统一为「新建合同」的 `btn btn-primary btn-sm` 实心主色样式，原 outline 五彩描边（outline-primary/info/success/warning）全部移除。验收：php -l 通过；浏览器实测工作台按钮 class 全部 `btn btn-primary btn-sm`（其余「查看全部/全部合同」等非快捷操作按钮不受影响）。无 DB 变更
- [x] 随合同申请开票（2026-08-18，v2.51.10）：提交合同审批时可勾选并填写开票信息（主体/类型/内容/金额/抬头/税号），合同过审（含全抄送免审批）后自动生成「待开票」发票并通知开票确认人，财务确认开票；跳过发票审批流（合同审批已把关）；contract 新增 invoice_intent 字段（迁移 `migration_v2.51.10_contract_invoice_intent.sql`）；**开票通知确认人按流程独立配置**（approval_flow 新增 invoice_notify，迁移 `migration_v2.51.10_flow_invoice_notify.sql`），配置入口在「审批流程 → 新建/编辑流程弹窗」（角色下拉+用户选择器，与抄送一致），未配置回退财务角色；金额 ≤ 合同金额可分批；验收：流程弹窗配置（回显/取值/保存落库）+ 提交过审（免审批）→ 发票 APPROVED → 通知按流程配置送达（user_ids=[1]）→ intent 清空，全链路浏览器 + DB 验证通过，测试数据已清理
- [x] 回款提醒范围统一 + 回款通知人配置 + 演示库升级 MySQL（2026-08-18，v2.51.11）：①**回款口径统一仅应收**——提醒引擎 6 处扫描、自动置逾期（autoMarkOverdue）、终结合同校验（hasOverdue）、客户概要卡已回款/逾期统计四处加 RECEIVABLE 过滤（应付 PAYABLE 不再误报为"回款逾期"），逾期 status 三处统一 PENDING∪OVERDUE（覆盖自动标记前后窗口）；②**钉钉推送在职过滤**——dispatch 按 status=1 过滤收件人（离职/禁用不再收，消除无效推送告警）；③**回款提醒通知人配置**——approval_flow 新增 payment_notify（{role_codes:[],user_ids:[]}），流程弹窗配置（交互同开票通知人，icon 用白名单 bi-cash-coin），提醒引擎按合同 flow_id 读配置、空回退财务角色、$pmtCache 请求内缓存，适配正式部署多名财务定向通知（迁移 `migration_v2.51.11_flow_payment_notify.sql`）；④**演示库升级 MySQL**——本地 3307 contract_dingtalk 执行 init_mysql + seed_demo 全量种子（客户 8/供应商 4/合同 12/回款 13/发票 3/审批流 2），修复 seed_demo 供应商旧字段 contact_email→remark（v2.51.3 结构对齐），保持纯净种子态（不迁移 SQLite 测试残留审批实例，见已知问题区）；验收：php -l/node --check 全过、resolvePaymentNotify 6 用例单测全过、dispatch 冒烟无回归、8099 登录 OK、test_mobile 22/26
- [x] 到期/逾期提醒封顶 + 配置化（2026-08-18，v2.51.13）：合同已到期与回款逾期不再无限每天推送，共用单一配置项 rule_overdue_remind_days（默认 30，0=到期/逾期后不提醒）——到期（expiry_date）/逾期（planned_date）距今天数超过该值即静默（防钉钉通知接口调用无限浪费），结清/终结后自然停止。统一判定 overdueOverWindow()（check/scanExpiredContracts/scanOverduePayments/scanAlerts 六处口径一致），查询层用 max(1, days) 收窄窗口。配置 UI「系统设置→系统配置→业务规则」单输入框「到期/逾期提醒封顶（天）」走 saveRuleConfig + config/save 清缓存。DB：init 三处同步种子（清理 v2.51.13 早期区分方案的两个旧键）+ `database/migration_v2.51.13_overdue_remind_config.sql`。验收：合同/回款各 3 场景（10 天提醒、40 天静默、恰好 30 天边界提醒）判定+推送队列全过；配置改 5 后 10 天逾期转静默（动态生效）；Playwright 设置页单输入框渲染 + 保存 20 落库 + 旧键清理 + 恢复 30 全链路通过
- [x] 移动端执行抄送通知钉钉链接跳 PC 版（2026-08-18，v2.51.12）：`ContractExecutionNotifyService` 合同进入执行的**钉钉工作通知链接**由 PC 路由 `/contract/<id>` 改为移动端 `/m/contract/<id>`（钉钉内置浏览器打开移动端详情，与开票/回款通知钉钉口径一致）；站内信保持 PC 路由（PC 端消息中心直用，移动端站内消息/待办列表已有 JS/视图层重映射，此前仅钉钉无转换层直接跳 PC 版）。验收：php -l 通过；构造流程 cc_list + dispatch 实测站内信 url=/contract/<id> 保持、抄送轨迹落库，测试数据已清理。无 DB 变更
- [x] 合同列表「查看范围」默认我的合同（2026-08-18，并入 v2.51.12 积攒批次）：PC/移动端合同列表新增「我的合同/全部合同」切换——首次默认「我的合同」+ localStorage 记忆上次选择；切换仅对能查看他人合同的账号显示（DEPT/CUSTOM/ALL，SELF 隐藏）；「我的合同」下高级筛选归属人选择器禁用（已选他人归属人保留，切回「全部合同」自动恢复）；项目/客户/部门入口与仪表盘草稿/KPI 卡、报表状态入口显式保持「全部合同」语义（URL `scope=all`）。验收：php -l 全绿；回归 perm 18/18、finance 10/10、admin 29/29、misc 20/20、mobile 22/26（基线一致）；E2E 两端默认我的/切换/记忆/入口恒全部/SELF 不显示均通过。无 DB 变更
- [x] 客户/供应商列表「查看范围」默认我的（2026-08-18，并入 v2.51.12 积攒批次）：客户/供应商列表沿用合同列表模式——PC 客户/供应商 + 移动端客户/供应商四端新增「我的/全部」切换，首次默认「我的」+ localStorage 记忆，SELF 账号隐藏；PC 供应商列表新增「归属」列；移动端生命周期/类型 chips 选择器排除 scope-chip（修复误绑清除高亮）+ 导航栏计数重拉后同步。验收：php -l 全绿；回归五套全过（基线一致）；E2E 四端默认我的/切全部/记忆/SELF 不显示均通过。无 DB 变更
- [x] 全局搜索建议链接核对（2026-08-18）：**保持现状合理，无需修改**——GlobalSearchLogic 搜索建议 url 为 PC 路由（/contract|/customer|/supplier|/project/<id>），唯一消费端是 PC `/search` 页（SearchController 服务端渲染 href，PC 端点击正确）；移动端无全局搜索入口（无 /m/search 路由），移动端搜索均为列表/表单内选择器（/ajax/contract/search、/ajax/party/search 等独立接口，不消费 GlobalSearchLogic）
- [x] 免审批流程配置限制评估（2026-08-18）：**确认不处理**——审批流程编辑器「至少 1 个审批节点」校验导致 nodes=[] 全抄送免审批流程无法经 UI 保存（其开票/回款通知人配置回退财务角色）；产品确认无此需求（免审批流程由 SQL/旧数据产生），限制保持现状
- [x] 删除能力评估（2026-08-18）：三实体（客户/供应商/合同）后端删除接口/权限码/回收站均已就绪，前端入口仅 PC 合同有；数据完整性缺口——客户物理清除（回收站 purge）不级联清理从属数据（共享/联系人/跟进/交接）会留孤儿；权限分配保持现状（admin/gm/manager 有删除权，finance/legal/user 无——职责分离合理，无需调整权限码）
- [x] PC 供应商删除入口 + 客户删除完整性补全（2026-08-18，积攒批次）：PC 供应商列表/详情新增删除按钮（`supplier:delete` 门控，软删除+关联采购合同校验+回收站文案）；客户删除完整性——deleteBlockersMap 补集团子客户阻塞（删除母公司须先解除集团归属）、softDelete 事务级联清理共享/联系人/跟进/交接记录、RecycleBin::purge 客户分支级联清理（防物理清除留孤儿）。验收：php -l 全绿；回归 perm 18/18、admin 29/29、misc 20/20；HTTP 专项 15/15；E2E admin 列表/详情按钮+弹窗、employee01 无按钮。无 DB 变更
- [x] PC 客户删除入口（2026-08-18，积攒批次）：PC 客户列表（桌面表格 + 窄屏卡片响应式）与详情页新增删除按钮（`customer:delete` 门控，window._canDeleteCustomer 渲染门控，stopPropagation 防卡片跳详情），删除软删除+关联合同/集团子客户校验+回收站文案。移动端 /m 页面不引 customer.js，保持无删除入口（评估决策）。验收：php -l/node --check 全绿；回归 perm/misc 全过；HTTP 9/9；E2E 桌面/窄屏/详情按钮+确认弹窗+内联 script 编译全 OK。无 DB 变更
- [x] 合同详情页「甲方（我方）」误标修复（2026-08-18，v2.51.13）：PC/移动端合同详情页甲乙方标签原按 `trade_attr===1`（合同性质=交易合同）误判「甲方（我方）」（自 v2.47.1 初始 commit 即存在），凡交易合同甲方一律带（我方）、我司反被标「外部」；改为按 v2.46.0「对方侧必关联档案」约束反推我方身份（仅甲方侧关联→我方=乙方；仅乙方侧关联→我方=甲方；两侧同有关/同无关不标注），「（我方）」标到真正我方侧、我方侧类型不再显示「外部」，PC tag 同口径显示「我方」。验收：php -l 全绿；E2E 三类案例（我方=乙方·交易/非交易 id23/id22 → 甲方（客户）乙方（我方）；我方=甲方·采购 id21 → 甲方（我方）乙方（供应商））两端全对；test_mobile 22/26 与基线一致。无 DB 变更
- [ ] 已生效合同「更正甲乙方」能力评估（2026-08-18，**C 方案，搁置未实施**）：已生效状态（执行中/已完成/已到期/已归档/已终止）合同不允许直接编辑（守卫仅放行草稿/已驳回）。C 方案=独立「更正甲乙方」入口（仅放开甲乙方名称+档案关联，强制审计，复用 /ajax/party/search 与既有选择器）。**锁定规则已拍板：「有发票/收付记录时仅允许改名称」**——注意与 v2.46.0「名称须与档案一致」校验存在冲突（档案不动则名称不可变），实施时需定义例外处理；另已确认 detail.php 中 searchChangeParty 等换签 JS 引用不存在的 changeForm 弹窗（死代码，无入口）。评估清单已入项目记忆，**待用户通知再实施**
- [x] 修复：钉钉「开票/回款通知人设置点击无反应」（2026-08-18，积攒批次）：部署到钉钉（PC 客户端内置浏览器）后，审批流程编辑弹窗右侧「开票通知确认人」「回款提醒通知人」区块点击无反应，网页端正常、同弹窗左侧抄送配置正常。根因——flow-editor.js 加载 URL 硬编码 `?v=8` 自 v2.47.1 起从未更新，v2.51.10/v2.51.11 两次改文件内容（新增 invNotify/pmtNotify 系列函数）未升版本号，钉钉 WebView 启发式缓存命中旧 JS（无新函数），新区块 inline onclick 调用未定义函数 → 点击无反应；抄送由旧 JS 自渲染故正常。修复——admin/index.php 改 `asset_url('js/admin/flow-editor.js')` 按 mtime 自动版本化（URL 全新强制拉新，未来改文件自动升版本号，杜绝同类遗漏；同类 pickers.js?v=2 内容未变无风险、form-builder.js?v=time() 无缓存问题，均不动）。验收：php -l 通过；Playwright 实测 script 标签输出 mtime 版本号、编辑弹窗区块齐全、角色下拉填充 6 角色、点「选择用户」正常弹出选人窗。无 DB 变更；部署后首次访问需刷新一次页面
- [x] 修复：PC 端提交的开票申请无撤回入口（2026-08-19，积攒批次）：列表「我的申请」与详情页均无撤回按钮。根因——`InvoiceLogic::pageMyList` 返回 `contract_invoice.*` 未含 `inst_id`（approval_instance_id），而前端撤回按钮显示条件 `v.status==='PENDING_APPROVAL' && v.inst_id` 恒不成立（撤回逻辑/接口本身已支持发票，缺的是入口）。修复——①pageMyList 补 `inst_id` 别名（与 pagePendingApproval 口径一致）；②`InvoiceController::detailData` 补 `inst_id/is_applicant/can_recall`（门控与后端 `ApprovalActionService::recall` 校验同口径：仅申请人本人 + 待审批 + 已挂实例）；③详情页 `invoice_apply/detail.php` 顶部加「撤回」按钮（二次确认后调 `/ajax/approval/<id>/recall`，成功置 CANCELLED 可重新提交）。验收：php -l 3 文件全绿；浏览器实测提交开票申请 → 列表/详情撤回按钮均出现 → 点击撤回 → 状态变「已撤回」；测试数据已清理还原。无 DB 变更
- [x] 随合同申请开票入口迁移至合同编辑页 + 复用申请开票表单（2026-08-19，v2.51.14）：移动端测试反馈「勾选样式不明显」「自创样式」「主体选不了/开票内容与后台配置不一致」——根因：v2.51.10 的开票区块在提交审批页自创样式（移动端 `MobileController::approvalCreate` 漏 assign `$companies` 致主体下拉恒空；开票内容硬编码 5 选项与 invoice_form_field 配置脱节）。改法（用户拍板：双端同步 + 新建也展示）——入口从提交审批页（PC/移动）迁移至合同编辑页底部（明显卡片勾选开关，勾选态高亮）；开票字段复用 `InvoiceFormConfig`（pcRender/mobileRender 新增 `$prefix` 参数传 `'inv_'`，避免与合同表单 our_company_id/amount 同名冲突，customer 的 data-fill-* 与 select/company 的 data-pick-name 同步加前缀）；`contract.invoice_intent` 写入/清除由 `ApprovalController::submit` 迁移至 `ContractController::save`（buildInvoiceIntent 校验后落库、未勾选清除，submit 不再触碰意图；intent 新增 customer_id 键，消费端 createAutoForExecutingContract 兼容）。验收：php -l 8 文件全绿；回归 admin 29/29、misc 20/20、perm 18/18、finance 10/10、mobile 23/26、transfer 4/12（失败项均为纯净库缺历史审批实例，基线内）；E2E——编辑页勾选保存落库/取消勾选清除/编辑回显、提交审批页无开票区块且 intent 保留、完整过审链路（提交→双节点通过→EXECUTING + 发票自动生成 + intent 清空）全通过；测试数据已清理还原。**无 DB 变更**（沿用 v2.51.10 的 invoice_intent 列）
- [ ] （待产品确认）后续需求池：按需补充，进入开发前更新本看板

## 五、维护约定

- 每轮开发/测试完成同步更新本看板（状态列 + 测试结果 + 问题清单）。
- 新增功能须先在此登记需求，开发后补闭环测试并更新状态。
