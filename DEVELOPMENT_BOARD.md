# 开发进度看板（产品经理窗口）

> 本看板用于管理合同管理系统的开发进度：需求 → 开发 → 闭环测试 → 发布，集中跟踪模块状态与待办。
> 最后更新：2026-08-15

## 一、项目概览

| 项 | 说明 |
|---|---|
| 项目 | 合同管理系统（PC + 移动端） |
| 技术栈 | ThinkPHP 8.0 / PHP 8.4 / SQLite（演示库） / 钉钉集成 |
| 演示地址 | `http://0.0.0.0:8099`（局域网可访问） |
| 演示账号 | admin / 85151818；manager01、employee01、finance01（密码 password） |
| 服务方式 | `php think run -H 0.0.0.0 -p 8099` |

## 二、产品模块看板

| 模块 | 需求 | 开发 | 闭环测试 | 状态 | 备注 |
|---|---|---|---|---|---|
| 认证（登录/登出/改密/失败锁定） | ✅ | ✅ | ✅ | 已发布 | 失败 5 次锁定 15 分钟 |
| 客户域（客户/联系人/跟进/转移/共享/集团/360） | ✅ | ✅ | ✅ | 已发布 | 数据范围 SELF/DEPT/ALL；移动端详情页含编辑资料入口、联系人增删改；PC 详情页操作区下移+内嵌往来统计卡+基本信息联系人卡片+集团归属标注+集团层级树（归属路径面包屑/图标缩进/本客户高亮） |
| 供应商 | ✅ | ✅ | ✅ | 已发布 | CRUD |
| 项目（含验收/终止/撤销） | ✅ | ✅ | ✅ | 已发布 | 终止联动合同 |
| 合同生命周期（新建→审批→执行→回款→开票→归档） | ✅ | ✅ | ✅ | 已发布 | 10 态状态机 |
| 审批中心（待办/已办/我提交/通过/驳回/转交/撤回） | ✅ | ✅ | ✅ | 已发布 | 多级审批流 + 抄送 |
| 发票闭环（申请→审批→开票→红冲→作废→驳回重提） | ✅ | ✅ | ✅ | 已发布 | 独立发票审批流 |
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
| 移动端闭环（19 页面 + 3 修复点回归） | 26/26 | ✅ |
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
- [ ] （待产品确认）后续需求池：按需补充，进入开发前更新本看板

## 五、维护约定

- 每轮开发/测试完成同步更新本看板（状态列 + 测试结果 + 问题清单）。
- 新增功能须先在此登记需求，开发后补闭环测试并更新状态。
