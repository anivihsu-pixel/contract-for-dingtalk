# 迭代日志

## v2.47.1（2026-08-11）— 经营统计口径对齐：trend 趋势 + 回款口径排除非生效合同 + 框架合同（PATCH）

经营统计已建立的统一口径为「交易合同 `trade_attr=1` + 排除非生效状态（DRAFT/REJECTED/PENDING_APPROVAL）+ 排除框架合同预算上限」。trend 近6季度趋势与项目/公司级回款口径未对齐，本次统一收口三处偏差。

### trend 近6季度合同趋势（ReportLogic::dashboardSummary）
- 合同额与同期已收两条序列此前仅过滤 `trade_attr=1` + `effective_date`/`actual_date` 区间，未排除非生效状态与框架合同——草稿/驳回/审批中合同金额被计入季度合同额，框架合同预算上限也被计入
- 现追加 `status not in [DRAFT,REJECTED,PENDING_APPROVAL]` + `exclude_framework_contracts`，与同方法 `dir` 收支方向口径完全对齐

### 公司级驾驶舱回款口径（ReportLogic::payScope）
- 回款基础查询未限定合同状态，非生效合同关联的回款记录可能计入应收/已收/待收/逾期
- 现追加同口径状态过滤 + 排除框架合同

### 项目经营聚合付款侧（ProjectLogic::aggregate payBase）
- 同一方法内合同额侧（dirQuery）已排除非生效状态 + 排除框架合同，但付款侧 payBase 未排除，导致项目经营页应收/已收/回款率与合同额/毛利口径不对齐
- 现追加同口径过滤，与 dirQuery 完全对齐

### 验证
- php -l 全绿
- SQL 对照：构造 DRAFT 合同 #9（9.6 万）effective_date=2026-05-15 落入 26Q2，修复前 trend 26Q2=2,197,000，修复后=2,101,000，差异恰好 96,000 = DRAFT 合同金额
- 浏览器 E2E：驾驶舱趋势图 26Q2 显示 2,101,000（修复后值），未出现 2,197,000
- 演示库已恢复原状，临时脚本/备份已清理

### 无 DB 结构变更

- v2.47.1 发布时补录 v2.47.0 的配置项 migration（`database/migration_v2.47.0_weekly_report_config.sql`）——原 v2.47.0 未提供 migration 文件，存量库升级需手动 INSERT `weekly_report_dd_enabled` 配置项；现补上后 deploy.sh 自动执行（MySQL 段幂等 `INSERT IGNORE`），无需手动 SQL

## v2.47.0（2026-08-10）— 总经理经营周报 + 双端入口（MINOR，含系统配置）

用户需求：为总经理生成公司经营周报，每周一开会可作为参考（各部门上周合同/回款/逾期），并要求移动端与 PC 端能通过卡片/图标/通知快速查看。先出原型确认（钉钉仅发极简提示省接口额度、通知点击直达移动端）后实施。

### 周报聚合与页面
- `WeeklyReportLogic` 聚合（口径与驾驶舱/月报一致）：仅交易合同 `trade_attr=1`、排除草稿/驳回/审批中与框架合同；上周新增合同（`created_at` 落周内）、上周实际回款（`RECEIVABLE+PAID` 按 `actual_date`/`paid_amount`）、当前逾期快照（`OVERDUE`）、待审批实例数；按部门聚合 + 明细
- 新增 PC `/report/weekly` 与移动 `/m/report/weekly`：4 指标卡（新增合同/金额/回款/逾期）+ 各部门经营卡 + 上周新增合同表 + 逾期合同卡，合同可点开详情，`?week=周一` 周切换回溯
- 命令 `report:weekly`（`config/console.php` 注册）：按 `role.code='gm'` 定位总经理，站内信始终发送（带摘要，落库无额度成本）+ 钉钉工作通知仅发极简提示（「经营周报已生成（日期），点击查看完整周报」，不携带摘要省接口额度），受 `weekly_report_dd_enabled` 开关控制（系统配置页可切，默认开）；crontab：`0 8 * * 1 php think report:weekly`
- 初始化脚本（init_sqlite/init_mysql）新增 `weekly_report_dd_enabled` 配置项

### 双端入口（方案 C：通知主推 + 卡片/菜单兜底）
- 移动工作台「全公司经营」卡片头部新增「经营周报」胶囊按钮（仅 `dashboard:company` 可见）→ `/m/report/weekly`，不改经营卡片数据与渲染逻辑
- PC 侧边栏「财务中心」下新增「经营周报」二级菜单（仅 `dashboard:company` 角色可见），回款管理 active 判定排除 `weekly` tab

### 权限收紧
- 周报为全公司口径数据，页面由 `financialGate`（payment:view 全员默认，普通员工可越权查看）收紧为 `requirePermission('dashboard:company')`，与「全公司经营」卡片同权限，仅总经理/超管可见

### 修复
- 侧边栏/周报页标题/移动按钮原用 `bi-calendar-week` 图标，项目 bootstrap-icons v2.43.2 中不存在致图标空白——统一换为 `bi-calendar-check`（已入白名单）

### 验证
- php -l 全绿；浏览器 E2E：GM 正向（PC 周报页/移动周报页/工作台按钮/侧边栏菜单）5 项全绿，普通用户负向（PC/移动周报 403、无按钮）3 项全红，周切换回溯 `?week=` 正常；钉钉 mock 确认仅极简提示；无 GM 用户时命令优雅降级「已生成未推送」；测试用户/临时脚本已清理
- 无 DB 变更（仅新增一条配置项，含于系统配置表初始化）

## v2.46.0（2026-08-10）— 签约方强制关联档案 + 甲方供应商独立字段 + 我方身份切换修复（MINOR，带 DB 变更）

用户实测「新建合同时甲方或乙方还是可以文本输入」，结合 v2.45.0 客户查重/共享优化（防止新建合同时自由输入任意名称），评估确认优化方向：**仅新建强制关联 + 未登记相对方表单内快速新建**（参考成熟 CRM 处理方式），并新增甲方供应商独立字段。对方侧选择天然覆盖 v2.45.0 客户共享体系（搜索接口 `appendCustomerShare` + FK 校验 `canAccessCustomer`）。

### 签约方强制关联档案（仅新建生效，编辑旧数据不追溯）
- 名称框只读化：PC `ContractFormConfig` 名称框常驻 `readonly`（名称仅可通过搜索选择/快速新建/本公司按钮填充），移动端名称 hidden 只读；搜索框始终可编辑（输入即解除关联并清空 ID，可随时重新搜索）
- 后端强制校验（`ContractController::save` 新建分支，按 `our_side` 判定对方侧）：对方侧客户/供应商 ID>0 且名称 trim 与档案一致；未关联拦截「请选择已登记的甲方/乙方客户或供应商（未登记可点「快速新建」，勿手输名称）」，名称不一致拦截「名称与所选客户/供应商档案不一致，请重新选择」
- 前端双端提交拦截：PC `wizardValidate` step2 / 移动提交前 `mPartyLinkedOk` 校验对方侧
- `our_side` 非交易合同也提交（后端依赖其判定对方侧做强制校验）

### 甲方供应商独立字段（contract.party_a_supplier_id）
- 甲方为供应商的采购合同不再误落乙方 `supplier_id`；`PartyLogic::linkFieldOf` 改数组返回 + `getContractIds` 双字段 whereOr；`SupplierLogic::deleteBlockersMap` 双字段；PC/移动详情 + party360 甲方往来摘要对称展示
- 迁移 `database/migration_v2.46.0_party_a_supplier.sql`（MySQL 幂等 ALTER + 索引，SQLite 注释段）；三份 init 同步

### 表单内快速新建客户/供应商
- 搜索无匹配渲染「快速新建」（PC modal / 移动底部弹层），复用 `/ajax/customer/save`、`/ajax/supplier/save`（自带查重 409 + 数据权限，新建归本人），成功回填该侧

### 修复
- **我方身份切换残留（移动端实测）**：我方=乙方填甲方→切我方=甲方→原甲方信息被误当本方——切换时清空两侧名称/搜索框/关联 ID/锁定，`applyOurSide()` 给新我方带出主体名；PC 同步修复
- 移动端 `selectParty` 供应商分支清空列表含 sidEl 自身（`supplier_id`/`party_a_supplier_id` 刚设置又被清 0）；快速新建回调误读 `res.id`（应 `res.data.id`）

### 验证
- php -l 全绿；移动端浏览器 E2E 全链路（切换清空、供应商按侧回填、客户回填、搜索框重选、快速新建回填、后端 403 拦截、正向放行）；PC E2E 前次已验证；测试数据已清理、临时脚本已删除

## v2.45.1（2026-08-10）— 系统配置备份/恢复自选表 + 中文表名（PATCH）

用户需求：系统配置导出和恢复时表对应注释中文名，且备份/恢复可自选哪些表。

### 表中文名（对应各表注释）
- `AdminLogic::CONFIG_TABLE_LABELS` 内置 10 表中文名映射（角色 / 权限 / 角色权限关系 / 用户角色关系 / 部门 / 本公司主体 / 审批流程 / 合同模板 / 资料库 / 系统配置），`tableLabel()` 供后端与模板共用；预览恢复表格、导出勾选 UI 均显示「中文名（英文表名）」

### 导出可自选表
- 备份区新增「选择导出表」可折叠块（默认全选 + 全选切换，`_cfgPickTabs` 全局状态）；`backupExport()` 带 `tables[]` 参数下载
- `exportConfigArray(?array $selected)`：仅导出勾选表（收敛到 CONFIG_BACKUP_TABLES 白名单、保持依赖顺序），空选择回退全量（防误导出空文件恢复时清空全部），`meta.tables` 记录实际导出表；`configBackup()` 支持 `?tables[]=` 子集导出

### 恢复可自选表
- 预览结果每表新增「恢复」勾选框（默认全选 + 表头全选/取消，`_cfgRestorePick` 状态）；确认文案改为「覆盖上方勾选表的全部配置（未勾选表保持现状）」
- `configRestore()` 预览/提交前按 `tables[]` 调 `filterPayloadTables()` 过滤 payload（空勾选前端拦截提示「请至少勾选一个」），未勾选表保持现状不覆盖
- `restorePreservesAdmin()` 部分恢复不涉及权限表（role/role_permission/user_role/permission）时直接放行——不会覆盖当前账号管理授权，无自锁风险

### 配套修复
- `BaseController::getPost/getParam` 改为 `request()->post/param($key, $default)`：支持 ThinkPHP `name/type` 语法（如 `tables/a` 强制数组），原 `$data[$key]` 数组索引方式不支持该语法导致 `tables` 勾选参数恒空（E2E 实测发现）
- **移动端草稿卡片钉钉灰底修复（部署实测反馈）**：`.m-card.is-draft` 原用 `color-mix(in srgb, var(--m-warn) 12%, #fff)` 浅色底——钉钉旧 WebView 不支持 `color-mix` 特性，background 声明失效，部署后草稿卡片只剩灰/白底（无浅色区分）；且原竖条取 `--m-warn`（#ff9d00 橙）偏离设计规范。改为设计规范琥珀纯色 `background:#fff4e5` + 竖条 `#d4860b`（与草稿徽标 `m-tag-warn` 同色系），零新 CSS 特性依赖，任何 WebView 一致渲染；浏览器实测底色 rgb(255,244,229)/竖条 rgb(212,134,11) 就位

### 验证
- php -l 全绿（BaseController / AdminController / AdminLogic / admin/index.php）；PHPUnit 65 tests / 172 assertions 全绿
- 浏览器 E2E（admin 超管）：导出勾选 UI 10 表默认全选、中文名映射就位；子集导出 `?tables[]=role&tables[]=permission` JSON 仅含 2 表（43 行权限）；全量备份上传预览返回 10 表中文名 + 每表勾选默认全选；UI 取消勾选 role/permission 后提交恢复，`restored` 仅含 8 表、role（6）/permission（43）/role_permission（160）/user_role（7）原值不变
- **无 DB 变更**；演示库数据未改动

## v2.45.0（2026-08-10）— 客户协作共享 + 集团层级（MINOR，用户商务场景）

### 背景
实际商务使用中两种情况：①客户属于 A 用户，但客户也可能和 B 用户签订合同；②客户可能是同一集团下的多个分公司，各分公司分属不同用户。参照成熟 CRM 产品（共享规则/集团层级）设计并实施。

### 共享机制（双粒度 + 只读）
- **共享粒度：用户 + 部门**（`customer_share` 表 `target_type` USER/DEPT 双粒度，一次到位，满足 5 部门约 20 商务的公司规模）；**共享级别：VIEW 只读**——可查看 + 可关联合同，不可编辑档案
- **白名单共享不放宽全局范围**：`CustomerLogic::getList()/search()` 对共享客户追加 `whereOr(c.id in sharedIds)`，仅放行具体客户，不改变全局数据范围（owner_id/dept_id 仍决定数据范围）
- **统一访问判定** `CustomerLogic::canAccessCustomer()`：公海(owner=0) → 数据范围 `canAccessRecord` → 显式共享（用户/部门白名单 `isShared`）→ 历史合同引用者 `getSharedViewers` → 集团祖先可见（子公司 owner 可见集团根与同集团客户）
- **共享管理**：`shareCustomer`（幂等，唯一键 `uk_share_customer_target`）/ `unshareCustomer` / `getShares`（带名称）/ `getSharedCustomerIds`；`CustomerController::shareList/share/unshare` 接口（`canManageCustomer` 门控：超管、admin、客户 owner 或 owner 部门上级可见共享管理）
- **合同 FK 校验换用统一判定**：`ContractController::save()` 客户分支改调 `canAccessCustomer`——解决「B 无法关联 A 的客户」死循环（原仅校验 owner 归属）；无权限返回「无权关联该客户（可联系客户负责人申请共享）」403
- **相对方选择**：`PartyLogic::getPartyRows/searchParty` 追加共享客户（仅 customer 表、has_all 跳过、追加共享 ID 不扩全局范围）
- **审计**：share/unshare/joinGroup/取消集团归属均写 `AuditService`

### 集团层级（多级树 parent_id 自引用）
- `customer.parent_id` 自引用多级树（0=顶层），与既有「重复客户合并(merge)」语义并存：合并=去重，parent_id=层级关系
- `getGroupAncestorIds`（防环保护）/ `getGroupDescendantIds`（聚合子树）/ `getGroupTree`（树+标识）/ `getGroupSummary`（子树合同数/金额/已回款 + 子孙明细）
- **聚合只读**：集团聚合仅是查看维度，子公司 owner 仅见本公司数据（不因父客户放宽数据范围）；`groupInfo` 对子客户返回整棵集团树
- **joinGroup 防环**：不能将客户加入其子孙客户名下；加入/取消集团归属均审计留痕
- PC 客户详情「共享与集团」Tab + 移动端客户详情「共享与集团」卡（懒加载）

### 数据库
- `database/migration_v2.45.0_customer_share_group.sql`（MySQL information_schema 幂等 + SQLite 段）；三份初始化脚本同步：`init_mysql.php`/`init.sql`/`init_sqlite.php`（parent_id + idx_customer_parent + customer_share 表，含中文注释）
- 发布卡点：`check_v245_comments` 注释规约校验 PASS（表注释/行尾 `-- 注释`/MySQL COMMENT 全带）

### 验证
- php -l 全绿（CustomerLogic/PartyLogic/ContractController/CustomerController/MobileController/route/init 双脚本）
- 浏览器 E2E：共享链路——李员工关联客户13 → 403「无权关联该客户（可联系客户负责人申请共享）」→ admin 共享 USER → shareList 返回 VIEW → 再关联通过 FK 校验；集团链路——groupInfo 树+汇总（3 合同 ¥350,000）+ 孙客户定位集团根 + joinGroup 防环拒绝 + 加入/取消成功；PC 列表共享/分公司徽标、PC 详情共享 Tab 与集团 Tab 树、移动端共享与集团卡均正常
- 测试数据已清理，临时脚本已删除，演示库已还原
- **复测补充（2026-08-10 再次全链路 E2E）**：三链路全部复验通过（403 拒绝 → 共享放行 → FK 通过；集团树动态更新/防环/恢复；PC/移动端 UI；列表徽标）；发现并修复 1 处规范问题——PC 客户详情共享撤销原用原生 `confirm`（项目 v2.38.8 已全站清零为 pcConfirm），已改为 `pcConfirm({danger:true})` 统一弹层；复测后测试数据与临时脚本再次清理
- **复测补充二（2026-08-10 部门级共享 + 撤销链路专项）**：① 部门级共享验证——admin 共享客户13 给商务部(DEPT4)，撤销用户级共享后商务部成员李员工仍可关联客户13（FK 放行）+ 列表可见带「共享」徽标；非共享部门（采购部 SELF）访问客户13 详情与 share-list 均 403「无权查看该客户」，确认白名单共享不放大全局范围；② 撤销确认弹层 UI 链路——点击撤销弹出 `pcConfirm` 弹层（确认操作 + danger 红按钮）→ 点确定 → 行删除 + toast「已撤销」→ shareList 清空；③ 修复 1 处前端空态瑕疵——撤销最后一条共享后未补「暂无共享成员」空态行（原 `tr.remove()` 仅删行），已补空态行渲染；php -l 全绿，测试数据与临时脚本已清理
- **追加（2026-08-10 移除客户「标记为本公司」）**：新建/编辑客户表单的「标记为本公司」（is_self）入口移除——PC `create.php` 复选框 + 移动端 `customer_form.php` 开关（历史遗留：v2.16 起建合同「本公司」快捷选择已改走 `company_profile`，该标记失去用途）；`CustomerController::save` 同步移除 is_self 接收（落库恒 0）；数据库 `is_self` 字段与统计/漏斗过滤逻辑保留（字段级防御，无存量 is_self=1 数据）；php -l 全绿；浏览器 E2E——PC/移动端表单均无该控件、新建客户保存正常（is_self=0）、测试数据已清理

## v2.44.4（2026-08-10）— 附件上传体验修复 + 移动端 Excel 直传（PATCH）

### 系统配置备份/恢复：成功提示文案 + 跨库类型告警（功能评估修复 P2/P3）
- **P2 文案**：`AdminController::configRestore()` 提交恢复用 `json_success($res)` 未显式传 msg，前端 toast 显示默认「ok」而非业务提示——改为 `json_success($res['restored'], $res['msg'])`，实测返回「系统配置已恢复」
- **P3 告警**：`AdminLogic::previewConfigImport()` 增加 db_type 差异告警（文件=mysql / 当前=sqlite 等跨库恢复时字段类型/默认值可能不兼容，导入失败将整体回滚）——与 app_version 差异告警同类，预览即提示
- **验证**：php -l 通过；浏览器实测——构造 db_type=mysql 备份预览返回「数据库类型不一致」警告；commit 返回「系统配置已恢复」；往返恢复 10 表行数一致、系统正常

### 系统配置备份/恢复：MySQL 生产环境完善 6 项（用户需求：真实部署下是 MySQL，分析是否有完善优化空间，六项全部确认实施）
- **① 恢复防自锁**：user 表不参与恢复（is_admin 恒保留），但 admin 角色 / system:user 授权随 role/user_role/role_permission 被覆盖——备份缺失当前账号管理授权时，非超管操作者恢复后将被锁死。新增 `AdminLogic::restorePreservesAdmin($userId, $payload)`：解析恢复数据中 code='admin' 的角色、授予 system:user 的 role_id、该用户的 user_role 命中关系判定；commit 前阻断并提示「备份须包含当前账号的 admin 角色或 system:user 授权」，预览分支追加同类预警（is_admin=1 用户恒安全直接放行）
- **② 恢复审计留痕**：`commitConfigImport` 成功后 `AuditService::log($this->userId, 'config_restore', 'system_config', 0, ['restored' => 各表行数])`——敏感整簇覆盖操作可追溯（谁/何时/覆盖了哪些表行数）
- **③ 上传超限友好提示**：PHP 默认 `upload_max_filesize=2M`，大配置备份（模板/资料库大字段）超限时 `$_FILES['backup_file']['error']===UPLOAD_ERR_INI_SIZE` 文件被丢弃——显式提示「超过服务器上传大小限制（php.ini upload_max_filesize）」，而非误报「解析失败」
- **④ 导出一致性快照**：`exportConfigArray()` 用 `Db::transaction()` 包裹 10 表逐表读取——MySQL InnoDB 一致性读保证备份文件处于同一时间点，避免备份过程中配置被并发修改产生表间不一致
- **⑤ 恢复分批写入**：`commitConfigImport()` 事务内 `array_chunk($clean, 200)` 分批 `insertAll`——大备份（上千行模板/权限）避免单条 insertAll 的 SQL 超长与内存峰值
- **⑥ 权限收紧**：备份/恢复入口权限由 `requirePermission('system:user')` 收紧为 `isSuperAdmin()`——覆盖整簇权限/角色矩阵的操作仅限超管（is_admin=1 或 admin 角色），普通具备 system:user 权限的用户不可再导出全量权限矩阵/覆盖恢复
- **验证**：php -l 通过；浏览器 E2E（admin 超管）——backup→preview 返回 previewCode=0、10 表行数 6/43/160/7/5/2/3/3/2/21、无告警、selfLockWarn=false；commit 返回 commitCode=0、commitMsg=「系统配置已恢复」、10 表行数一致；防自锁/审计/上传超限分支为防御性逻辑（正常路径不触发），判定函数 `restorePreservesAdmin` 逻辑经人工核对

### PC 新建合同：非交易合同时金额字段隐藏（用户反馈：金额不能填写但还显示着；方案 A 确认）
- **问题**：`create.php` `syncTradeAttr()` 非交易时仅将金额输入框 `disabled`（灰显仍占位），方向行 `directionRow` 已隐藏——同一「非交易」语义下金额与方向行为不一致，用户困惑
- **修复**：方案 A——非交易时金额行 `amountField` 一并 `display:none`（与方向行对称，字段配置 `hide=direction,amount` 的本意完全落地）；金额值仍强制置 0 落库；切换回交易恢复显示并可重新填写
- **验证**：php -l 通过；PC 1440 浏览器 E2E——交易态两行显示、金额可填；切「非交易合同」`amountField`/`directionRow` 均 `display:none`、金额值=0、提示文案正确；切回交易两行恢复、金额可重新填写

### PC 新建合同：拖拽上传后 dropzone 背景高亮不复位（附件双端全体验 QA 实测发现）
- **现象**：文件拖入上传区后，背景色永久停留在蓝色高亮（#e8f0fe），视觉上一直处于"拖拽悬停/上传中"状态
- **根因**：`ContractFormConfig.php` 上传区 `ondragover` 悬停设高亮 → `handleDrop` 复位 → `uploadFile` 上传开始又设高亮（"上传中"指示）→ 上传成功/失败后无任何分支复位
- **修复**：`contract/create.php` `uploadFile` 的 `.then` 与 `.catch` 均补 `dz.style.background=''`（上传完成/失败即复位）
- **验证**：php -l 通过；浏览器 E2E——拖拽上传中高亮 `#e8f0fe`，上传完成后背景复位空白

### 移动端合同附件：文档入口支持 Excel 直传（用户需求）
- **改动**：`ContractFormConfig.php` 移动端上传区 `#fileDocInput` accept 由 PDF/Word 扩为 PDF/Word/Excel（`.xls,.xlsx` + Excel MIME）；「上传文档」入口文案 `PDF/Word` → `PDF/Word/Excel`；JS 格式白名单本就含 xls/xlsx 无需改动
- **验证**：php -l 通过；移动端 390px E2E——通过「上传文档」入口直传真实 xlsx 成功（条目渲染 Excel 图标 + 大小、无幽灵条目、`f_file_url` 同步）

### PC 新建合同：参考资料库按钮缩小并移至合同概要区（用户需求：不用这么大的按钮，放更合适位置）
- **改动**：`ContractFormConfig.php`「参考资料库」按钮从「辅助信息」分组移除，改放 Step2「合同概要」分区标题行右侧（`d-flex justify-content-between` 右对齐小按钮 `btn btn-sm`，title 提示"参考合同范本/开票资料拟定概要"）——起草概要时参考范本随手可取，不再深埋在表单底部辅助信息区
- **验证**：php -l 通过；浏览器实测 Step1 无按钮、Step2 概要标题行右侧 103×29px 小按钮、辅助信息分组无残留、点击弹窗正常（分类/资料列表渲染）

### 合同列表：草稿置顶 + 浅琥珀底区分（用户需求：草稿卡片区分 + 列表最前展示，方案 A 确认后同步 PC 端）
- **排序**：`ContractLogic::getList()` 新增 `draft_first` 排序分支（`orderRaw("CASE WHEN c.status='DRAFT' THEN 0 ELSE 1 END, c.id DESC")`）。PC 端 `ContractController::index()` 默认视图（无 sort 参数）草稿置顶、点击列排序时遵循所选列；移动端 `MobileController::contracts()` 始终草稿置顶；分页「加载更多」由同一排序查询切片自然延续
- **视觉（方案 A）**：移动端草稿卡片 `is-draft` 浅琥珀底（`color-mix(in srgb, var(--m-warn) 12%, #fff)`，fallback `#fff3e0`）+ 左侧 3px 琥珀竖条（`inset 3px 0 0 var(--m-warn)`）；草稿徽标由灰改琥珀——移动端 `contract_status_badge()` DRAFT → `m-tag-warn`、PC 端 `contract.js` `statusB()` DRAFT → `pc-tag-warn`（#fff4e5/#d4860b）
- **验证**：php -l 全绿；浏览器 E2E——PC 1440 默认视图草稿置顶（前 3 行）+ 琥珀徽标计算样式 #fff4e5/#d4860b；点「金额」列排序草稿不置顶（金额升序）；移动端 390 草稿置顶（前 3 卡）、`is-draft` 浅琥珀底 + 3px 琥珀竖条、草稿徽标 `m-tag-warn`、非草稿卡白底无竖条；状态筛选（JS 渲染路径）卡片同样带 `is-draft`；18 份合同单页全量、page=2 无剩余（分页延续由后端排序保证）

### 移动端新建/编辑表单：底部固定「创建合同/保存修改」按钮遮挡输入字段（用户反馈：编辑输入字段时按钮遮挡影响输入）
- **根因**：`.m-submitbar` 为 `position:fixed; bottom` 固定提交栏，Android/iOS 软键盘弹出时浏览器将其顶到键盘上方，正好悬在正在编辑的字段上造成遮挡
- **修复**：`mobile-common.js` 新增全局键盘遮挡处理——输入控件（input/textarea/select）`focusin` 时隐藏提交栏、`focusout` 且焦点已离开输入控件后恢复（延迟 0ms 判 `activeElement`，输入框 A→B 切换不闪烁）；事件回调内动态查询 `.m-submitbar`（脚本在 head 加载时 DOM 未就绪）；覆盖合同/客户/供应商三个移动端新建编辑表单（共用 `.m-submitbar` 结构）
- **验证**：移动端 390px 浏览器 E2E——聚焦「合同标题」提交栏 `display:none`；失焦（焦点回 body）恢复可见；输入框 A→B 切换提交栏保持隐藏不闪烁

### 移动端新建/编辑表单：底部「创建合同」按钮收缩为居左窄块（部署实测反馈：按钮居左很窄 + 打勾图标，无文字全宽蓝底）
- **根因**：`.m-submitbar` 定义缺 `display:flex`（对比 `.m-actionbar` 有）——子按钮 `.m-btn` 的 `flex:1` 在非 flex 父容器下失效，按钮收缩为内容宽（390px 视口下实测 98px）且居左，仅显示图标+文字窄块
- **修复**：`mobile.css` `.m-submitbar` 补 `display:flex; gap:12px`（与 `.m-actionbar` 同构），子按钮 `flex:1` 生效撑满全宽；覆盖合同/客户/供应商三个移动表单（共用该结构）
- **验证**：移动端 390px 浏览器 E2E——修复前按钮 98px 居左；修复后提交栏 `display:flex`、按钮 343px 撑满全宽蓝底白字；键盘遮挡逻辑回归正常（聚焦隐藏、失焦恢复 flex）

### 移动端草稿详情页徽标一致性（全量 QA 回归发现）
- **问题**：移动端合同详情页（`contract_detail.php`）与相对方360（`party_360.php`）各有本地硬编码合同状态色映射，DRAFT 仍为 `m-tag-muted`（灰）——列表已改琥珀，详情页仍灰，同一草稿在不同页面颜色不一致
- **修复**：两处本地映射 DRAFT → `m-tag-warn`（琥珀，与列表/`contract_status_badge()` 统一）
- **验证**：移动端 390px 浏览器 E2E——草稿详情页徽标 `m-tag-warn`、计算样式 #fff4e5/#d4860b；php -l 全绿

### PC 端草稿徽标一致性（全量 QA 回归复验发现）
- **问题**：PC 端统一状态徽标函数 `contract_status_label()`（`common.php`）的 DRAFT 仍映射 `pc-tag-muted`（灰）——PC 合同详情页、PC 相对方 360、仪表盘合同/草稿列表均经此函数渲染，同一草稿在 PC 列表（`statusB()` 琥珀）与详情/360/仪表盘（灰）颜色不一致
- **修复**：`common.php` `contract_status_label()` classMap DRAFT → `pc-tag-warn`（琥珀）；PC 详情页、相对方 360、仪表盘随之统一
- **验证**：浏览器 E2E——PC 1440 草稿详情页、相对方 360、仪表盘草稿徽标均 `pc-tag pc-tag-warn`（#fff4e5/#d4860b）；php -l 全绿

### 数据库脚本中文注释全覆盖（用户要求：交付源码字段需中文注释，开发时也要做好）
- **问题**：`scripts/check_db_comments.sh` 此前仅校验四份核心脚本（init_sqlite/init_mysql/init.sql/migration_v2.2），`database/` 下 33 个增量迁移脚本的新表与新字段缺注释不会被拦截——全量复核发现 **5 张迁移新表缺表级注释**（customer_activity / invoice_form_field / form_field_linkage / notification / role_dept）、**25 处 `ADD [COLUMN]` 字段缺行尾 `--` 注释**（v2.35.4/v2.35.5/v2.38.3/v2.38.6/v2.38.7×11/v2.38.12/v2.38.25/v2.40.0_party_a_customer，其中 `user.need_handover` 完全无注释；另有 7 处为多行 `ALTER TABLE` 续行字段——v2.31 category_list/use_amount、v2.38.24 sort_order、v2.40.0 next_follow_at/industry/stage/progress，第一轮扫描仅覆盖行首为 ALTER TABLE 的行、续行漏检，第二轮全模式扫描补齐）
- **修复**：逐一补齐 5 张新表 `-- 表注释：` 首行 + 25 处新增字段行尾 `-- 中文注释`（MySQL 原生 `COMMENT '...'` 保留，行尾 `--` 为硬性要求，`--` 后带空格兼容 MySQL/SQLite 行注释语法）；`check_db_comments.sh` 扩展覆盖三份 init 脚本 + 全部 `migration_*.sql`，且 ADD 正则升级为匹配任意位置的 `ADD [COLUMN]` 列定义行（含多行 ALTER 续行与动态 SQL 字符串内，`ADD INDEX/KEY` 等非列定义行不匹配）；DEVELOPMENT_GUIDE §7 适用范围、硬性规则、发布清单同步
- **验证**：等效 Python 校验实跑 3 份 init + 31 个迁移文件全部 OK（init 32 表/335 列、迁移新表/新字段均带注释）**0 缺失**；仅注释改动无实际 DDL，本地库不受影响

### MySQL 数据库字段注释入库：Navicat/DBeaver 可显示中文注释（技术反馈：打开数据库查询具体内容时英文名字段看不到中文注释）
- **根因**：`init_mysql.php`/`init.sql` 字段定义只有 SQL 注释 `-- 中文`（不存入数据库），无 MySQL 原生 `COMMENT '...'` 子句（2026-07-16 曾把仅有的 5 处 COMMENT 误改为 `--` 形式）——生产 MySQL 建库后 `information_schema.COLUMNS.COLUMN_COMMENT` 全空，数据库工具点击英文字段名看不到注释；SQLite 内核不支持列注释，仅源码可读
- **修复**：为 MySQL 建库脚本全部字段补 `COMMENT '中文'` 子句（与行尾 `-- 注释` 并存、内容一致）——init_mysql.php 335 字段（含 2 个 JSON 注释含转义引号 `\"` 的字段手动补齐）、init.sql 335 字段、迁移脚本 CREATE TABLE 块 71 字段（v2.2 33 + 新表 38）；`check_db_comments.sh` 新增「MySQL 建库字段须带 COMMENT」检查（init_sqlite.php 除外），release.sh 发布卡点自动生效；存量 MySQL 库补注释脚本 `database/sync_mysql_comments.php`（读 init_mysql.php 定义逐列 `ALTER ... MODIFY COLUMN ... COMMENT`，幂等仅补空注释列）；DEVELOPMENT_GUIDE §7 规则同步
- **验证**：php -l 通过；等效 Python 校验 3 init + 31 迁移 **0 缺失**（缺 `--` 注释 0、缺 COMMENT 0）；全部 700+ 处 COMMENT 字符串引号配对静态校验通过（`''` 转义合法）；sync 脚本解析 init_mysql.php 32 表 / 335 字段定义全部成功（含 `\"` 转义还原）；本机无 MySQL 服务，建表/MODIFY 语法待生产部署验证（均为标准 MySQL 5.7/8.0 语法）

- **无 DB 结构变更**（仅补齐注释；新部署重新执行 init_mysql.php 后字段注释即入库；存量库需补 COMMENT 见下）

## v2.44.3（2026-08-10）— 移动端预览修复 + PC 附件预览分离（PATCH）

### PC 附件预览分离：图片画廊与文档预览不再混用（部署环境反馈：图片预览时能切换到文档，跳出打开浏览器）
- **背景**：PC 合同详情统一预览弹窗按全量附件列表（图片+文档混合）实现 prev/next——图片弹窗里点「下一个」切到 PDF/DOCX/XLSX 时 `window.open` 跳新标签页，体验割裂
- **改动**：`contract/detail.php` 预览弹窗改为图片画廊专用——`imgList` 仅含图片附件，prev/next/索引/键盘左右键只在图片间切换；文档附件点击直接 `openDocPreview()`（PDF/DOCX/XLSX → office-preview 新标签页；doc/xls 等不可内嵌格式 → 直接下载兜底），不再进入图片弹窗
- **验证**：php -l 通过；浏览器 E2E（PC 视口，临时给合同 8 造 2 图+1 PDF 混合附件）：图片弹窗索引「1/2→2/2」仅在图片间切换、next 到末张禁用、`window.open` 零调用；PDF「预览」按钮直接 office-preview 新标签页且弹窗未打开；演示库已还原（file_url 恢复空数组、临时脚本清理）
- **无 DB 变更**

### 移动端 Word 预览内容两侧遮挡（部署环境反馈：预览内容两侧被遮挡）
- **根因**：docx-preview 以容器宽度排版——移动端窄容器（390px）下 A4 页面被 flex 压缩到 330px 宽，段落/表格按窄宽重新排版挤变；且 wrapper 默认 `align-items:center` 居中，超宽页面左右溢出时左侧不可达（与 PDF 曾出现的遮挡同因）
- **修复**：`mobile/office_preview.php` docx 分支撑宽容器（`max(容器宽, 850px)`）以桌面宽度渲染 → 渲染完成后固化每页 `section.docx` 宽度并恢复容器 → 页面保持原宽 + 横向滚动可达；CSS 强制 wrapper `align-items:flex-start`（`#officeContainer .docx-wrapper`）避免居中溢出。交互与 PDF 预览（A4 原宽 + 横滚）一致
- **验证**：php -l 通过；浏览器 E2E（390px 移动视口，admin 预览资料库 docx）：渲染前 section 330px（压缩）→ 修复后 790px 原宽、段落宽 790px（内容按宽排版）、横向滚动 maxScrollLeft=430 右端对齐视口可达、左侧起点完整；单页/多页文档均正常；xlsx/PDF 分支不受影响

### 移动端图片预览加载慢（部署环境反馈：图片预览加载很慢）
- **根因**：`preview_token()` 每次签发 `exp=time()+ttl`（秒级变化）→ 每次进入详情页 token 不同 → 预览 URL（带 t 参数）每次不同 → 浏览器缓存（/preview 已带 Cache-Control: max-age=3600）按 URL 隔离永不命中 → 每次全量重新下载原图/文档
- **修复**：`app/common.php` `preview_token()` 窗口化——exp 对齐到「下下个」TTL 窗口边界，同一文件在同一窗口内签发的令牌完全一致 → URL 稳定 → 缓存可命中。取「下下个边界」保证窗口内任意时刻签发的最短有效期 ≥ ttl（避免窗口尾部签发即过期导致预览 401），最长有效期 2 倍 ttl，与 max-age=3600 缓存期限对齐；令牌仍为路径绑定 + HMAC 签名 + 定时失效，安全影响可忽略
- **验证**：php -l 通过；浏览器验证：两次进入详情页 token 完全一致；`/preview` 同 URL 二次请求 `fetch(cache:'only-if-cached')` 返回 200（浏览器缓存命中，不再全量下载）；预览链路（含 token 校验）正常
- **无 DB 变更**

## v2.44.2（2026-08-10）— 项目管理终止/归档删除/移动端上传修复/生命周期对称/PC关键词弹层（PATCH · 积攒收口）

### PC 合同新建：关键词推荐对齐移动端弹层（用户需求：关键词推荐不用列在框下面，参考移动端处理方法）
- **改动**：`ContractFormConfig.php` PC 端 keywords 字段改为移动端同构结构——hidden 承载值 + 只读展示区（点击添加关键词）+ 顶部弹层（输入框 + 添加按钮 + 常用标签推荐 + 已选区）；`contract.js` 关键词控件重写为移动端同构交互（`#kwDisplay` 点击开弹层、`/ajax/keyword/hot` 常用标签拉取只缓存一次、已选置灰点击加入、弹层内已选可 × 删除、Esc/mask/关闭按钮关闭、提交前收弹层输入残留）；旧 `#kwHotBox` 框下平铺推荐行与 kw-chips 直输样式删除
- **保留**：后端 `ContractController::hotKeywords()` 与路由 `keyword/hot`（PC 弹层与移动端弹层共用）；关键词归一化口径不变（normalize_keywords）
- **验证**：php -l 通过；浏览器 E2E（admin 打开 PC 新建合同页）：`#kwHotBox` 不存在、展示区渲染「点击添加关键词」、点击弹层打开、常用标签 12 条渲染、输入+添加/点推荐标签加入→已选区与展示区同步、× 删除、关闭弹层、隐藏字段值同步（`测试词`）
- **无 DB 变更**

### 客户生命周期对称修复（PC/移动端甲方客户升成交，用户需求：PC 两步新建对齐移动端）
- **背景**：`CustomerLogic::promoteToActive()` 原仅由 `party_b_customer_id` 触发——PC 端甲方选客户/移动端「我方=乙方」时客户不升成交，客户详情生命周期卡在潜在/跟进状态
- **改动**：`ContractController::save()` 合同落库后对 `party_a_customer_id`/`party_b_customer_id` 去重统一提升生命周期为 ACTIVE（v2.45 对称修复）；PC 新建向导 Step2 增「我方身份」分段引导（我方=乙方/甲方，切换时由 create.php JS 带出签约主体名到对应侧），与移动端 my|our 语义对齐
- **验证**：php -l 通过；浏览器 E2E（admin 编辑草稿合同 HT-2026-07-0002）：上传测试附件（会话来源校验通过）→ 甲方搜索选择客户 12（青岛海蓝生物，预置 LEAD）→ 保存跳详情页 → 客户 12 生命周期 LEAD→ACTIVE；演示库已还原（合同甲方/附件字段还原、客户 12 回 ACTIVE、测试附件与临时脚本清理）

### 移动端合同附件上传修复：上传成功后残留「上传中… 99%」幽灵条目（部署环境反馈：所有格式附件上传后出现重复，重复的一条卡在 99%）
- **根因**：2026-08-05 将 `renderUploads()` 由「uploadList 整体重建」改为「只重建 `.m-up-done` 成功项、保留错误项」后，上传成功回调（`uploadFile` 的 xhr load 分支）从未显式移除进度条目，而进度上限 `Math.min(99, …)` 永不达 100%——每次成功上传都会在列表残留一条「上传中… 99%」条目，与成功项同名重复显示。文档/图片/拍照共用同一 `uploadFile`，三类入口全部复现；PC 端 `create.php` 因整体重建 `innerHTML` 不受影响
- **修复**：`mobile/contract_form.php` 成功回调在 `uploaded.push(res.data)` 后补 `removeUploadItem(itemId)`，先移除进度条目再由 `renderUploads()` 重建成功项
- **验证**：php -l 通过；浏览器 E2E（admin 登录 /m 新建合同页）：连续上传 2 个 PDF → `done=2 / progress=0 / error=0`，`f_file_url` 仅含 2 条正确记录，无「上传中 99%」条目；测试上传文件与临时脚本已清理

### 项目管理：终止/撤销终止（用户需求：项目管理无法终止）
- **新增**：`ProjectController::terminate()`（仅进行中 ACTIVE 且未完结 stage!=COMPLETED 项目可终止，联动终止项目下执行中/已通过/历史已签的销售合同——复用合同状态机置 TERMINATED，存在逾期未结回款的合同跳过并返回清单不阻塞）与 `restore()`（TERMINATED→ACTIVE 撤销终止，合同状态不联动恢复）；新增路由 `POST /ajax/project/terminate`、`/ajax/project/restore`；审计留痕（terminate/restore）
- **视图**：项目详情页操作区按状态/权限渲染「终止项目」「撤销终止」按钮（badgeMap 加 TERMINATED→danger）；移动端项目列表/详情 TERMINATED 红色徽章
- **联动收敛**：`ProjectLogic::options()/search()` 排除 TERMINATED 项目（新建合同关联下拉不再出现已终止项目）；三份 init 脚本（init_sqlite/init_mysql/init.sql）字典种子加 `TERMINATED:已终止`
- **存量库**：演示库 system_config 字典已直接补齐 TERMINATED；生产升级需手动执行同类 UPDATE（无 migration 文件）
- **验证**：php -l 全绿；浏览器 E2E：终止项目→联动 2 份销售合同转已终止（已完成/已归档/草稿合同不受影响）→撤销终止恢复 ACTIVE；演示库恢复原状

### 归档/已完成/已到期/已终止合同可删除（测试数据清理出口，用户需求：归档合同仍计入营销统计）
- **背景**：营销统计为「累计经营」口径（排除草稿/驳回/审批中），已归档合同代表历史成交保留计入属设计行为；真正痛点是测试数据没有清理出口（原仅 DRAFT/REJECTED 可删，归档/已完成合同成「统计钉子户」）
- **改动**：`ContractLogic::softDelete()` 可删状态扩为 `['DRAFT','REJECTED','ARCHIVED','COMPLETED','EXPIRED','TERMINATED']`（全部无业务活性状态，经评估后由原 DRAFT/REJECTED/ARCHIVED/COMPLETED 补齐 EXPIRED/TERMINATED——同步 `ContractController::batchDelete()` 逐条校验与注释）；合同详情页删除按钮门控同口径放宽，确认文案更新（删除后进入回收站）；delete 失败文案改「当前状态不可删除」
- **完整性保护保留**：有回款记录（PENDING/PAID/OVERDUE）/发票（有效态）/进行中审批/子合同的合同仍被 `deleteBlockers` 拦截并提示具体阻塞项；无关联数据的归档/已完成/已到期/已终止合同可删除→进回收站→超级管理员彻底删除（既有链路）
- **验证**：php -l 全绿；浏览器 E2E：归档合同详情页删除按钮可点→有回款+发票被拦截（提示「存在未撤销的回款记录；存在发票记录」）→撤销回款、作废发票、删除回款计划后删除成功→项目统计立即减少（¥1,116,000→¥1,040,000）→回收站可见且 can_purge=true；EXPIRED/TERMINATED 状态放行验证（有回款+发票的 EXPIRED 合同被 deleteBlockers 拦截提示、无关联的 TERMINATED 合同删除成功）；演示库恢复原状
- **无 DB 变更**

## v2.44.1（2026-08-09）— 安全批量修复 P0/P1/P2 全批次（PATCH · 积攒收口）

### v2.44.0 审查 P2 收敛：附件 MIME 白名单单一事实来源（app/common.php）
- **背景**：workbuddy 审查报告 `contract_review_v2.44.0.md` 指出 `resolve_attachment_ext()` 与 `resolve_library_attachment_ext()` 两函数各自维护 7 类白名单 + OLE 回退（逐行重复），存在漂移风险（违反「四端白名单同步」约定）；且 `resolve_attachment_ext()` 注释仍写「四类」与实际七类不符
- **修复**：`resolve_library_attachment_ext()` 收敛为委托 `resolve_attachment_ext()`（单一事实来源，行为与历史实现完全一致：finfo 真实 MIME 精确白名单 → x-ole-storage/vnd.ms-office 按原始扩展名回退 → octet-stream 拒绝）；`resolve_attachment_ext()` 注释更正为七类说明
- **验证**：php -l 通过；phpunit 全套 65 tests / 172 assertions 全绿（含 ResourceControllerTest 单参数调用兼容 + AttachmentUploadTypeTest）；无行为变更、无 DB 变更
- **2026-08-09 随 v2.44.1 发布**

### v2.44.1 安全批量修复（2026-08-09，依据 workbuddy `contract_audit_full_v2.44.0.md` 全面审查）
- **P0-1 存储型 XSS**：合同/资料库上传文件名净化（`preg_replace` 移除 `<>"` 与控制字符，空名回退 `attachment.<ext>`，`ContractController::upload` / `ResourceController::handleUploadFile`）；21 个视图文件 `<script>` 内 `json_encode` 统一补 `JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT`（与 contract/detail 对齐）；`PreviewController::resolveDisplayName` 返回前净化 CRLF/控制字符/引号
- **P0-2 跨部门数据泄露**：`PartyLogic` 的 `get360()`/`getSummary()`/`summarizeBatch()` 补 `AuthLogic::appendDataScope`（带 `c.` 别名），360 详情联动回款/发票/动态均经范围过滤
- **P1 认证**：禁用/锁定用户实时吊销——Auth 中间件 JWT 通道（status!=1 删 dingtalk_session+缓存+401）与 Cookie 通道（user_status 30s 缓存+logout+401/跳登录）双通道兜底；登录失败锁定键改「账号+IP」防单账号 DoS；钉钉 SSO 加一次性 `dingtalk_oauth_state` 防重放；`isSuperAdmin()` 属性缓存 + `hasPermission()` 先超管短路
- **P1 审批**：`ApproverResolver::resolve` 排除提交人后兜底超管；指定流程校验（存在+启用+业务匹配）；驳回重建时无目标审批人整体驳回到发起人
- **P1 合同/附件**：trade_attr 漏传保留旧值+显式 0/1 白名单；合同归属字段（owner_id/dept_id）编辑时不可改；项目/客户/供应商/父合同外键 canAccessRecord 校验；附件 URL 归属校验（`rememberUploadedUrl`/`validateAttachmentUrls`）
- **P1 路由/数据范围/审计/索引**：`url_route_must` 改 true（写方法 GET 不可达，防 CSRF 绕过）；ProjectLogic 全部聚合补数据范围；回收站恢复/清空与用户/角色/流程管理补审计日志；approval_instance/payment_record 补查询索引
- **安全 404 页**：ExceptionHandle 404 页面请求不再回退框架 debug 渲染（防 APP_DEBUG 误开泄露堆栈），自渲染 `error/404.php`
- **验证修复过程中发现并修复的回归**：Auth 中间件 Cookie 通道 `user_status_X` 缓存经 ThinkPHP File 缓存数字字符串化后返回 `string '1'`，原 `!== 1` 严格比较恒真导致**所有已登录用户被误判禁用而强制登出**（会话在首个请求后即失效）；改为 `(int)$status !== 1`
- **验证**：php -l 39 文件全绿；phpunit 65 tests / 172 assertions 全绿；浏览器 E2E（表单登录→dashboard→13 条关键路由 200、写方法 GET 404、404 安全页、改密页、会话跨导航保持）全部通过；无 DB 变更（存量库无需 migration）
- **部署提醒**：v2.44.1 起 `DB_PASS` 无代码默认值，升级前须在 .env 显式配置 DB_PASS；存量库升级需执行 `migration_v2.43.6_library_perms.sql`（deploy.sh 自动执行）
- **2026-08-09 随 v2.44.1 发布**

### v2.44.1 审查 P2「建议现在做」批次（2026-08-09，9 项全部落地）
- **公式注入中和**（CSV 真实风险点）：`export_safe_cell()` 对 `= + - @` 开头值前置单引号（app/common.php），`ContractController::exportCsv` fputcsv 前逐格中和；XlsxHelper `csvRow()`（XLSX 内嵌 CSV 路径）与 `esc()`（inlineStr，纵深防御）同样过中和
- **XSS 纵深 JSON_HEX 补齐**：`dashboard/_partial`（5 处 trend_data）、`finance/index`（invoice_type）、`contract/detail`（payment_milestone/invoice_type）`json_encode` 补 `JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT`（与 P0-1 视图批次对齐）；`admin/index` 的 htmlspecialchars(json_encode(...)) 已确认属性上下文安全不改
- **索引补强**：contract 表补 `party_a_customer_id` / `supplier_id` 索引（init.sql / init_mysql.php / init_sqlite.php 三份 1:1 同步；MySQL 语法 `KEY idx_...`、SQLite 语法 `CREATE INDEX IF NOT EXISTS`）
- **孤儿附件清理**：`RecycleBinLogic::purge` 彻底删除合同前读附件清单，事务提交后按 URL 做引用检查（`file_url LIKE %url%`，含回收站内合同）确认无引用才 `remove_upload_file()` 物理删除；`remove_upload_file()` 收敛为公共函数并带 realpath 边界校验（仅允许 `public/uploads/` 内文件被 unlink，防目录穿越）
- **导出超时**：exportCsv / exportXlsx 开头 `@set_time_limit(0)`（大表导出防 FPM/CLI 超时截断）
- **DB 默认口令置空**：`config/database.php` DB_PASS 默认 `root` → `''`（缺省空串连接失败，强制部署环境 .env 显式配置，避免弱口令 root/root 上线）
- **死配置清理**：`config/middleware.php` 删除 `cache_page` 别名（指向不存在的 `CacheHeader` 类，休眠死配置）
- **重复合同检测精度**：`ContractLogic::findDuplicate` 金额比对由 float 改 `sprintf('%.2f')` 两位小数字符串（对齐 DECIMAL(15,2)，防 float 二进制尾差漏判重复）
- **里程碑合计校验**：评估后**不做**——现有「合计 ≤ 余额+0.01」已是正确业务约束，强制「恰等于合同额」会破坏真实场景（部分回款/质保金留尾），记录决策
- **验证**：php -l 全绿；phpunit 65 tests / 172 assertions 全绿；核心函数脚本实测（export_safe_cell 9 用例 + csvRow/esc + remove_upload_file 目录穿越防护 + 金额归一全 PASS）；浏览器 E2E（admin 登录：导出 CSV 中 `=cmd`/`@SUM`/`=HYPERLINK` 均前置单引号且无裸公式；回收站 purge 合同 A 后其独享附件 a 物理删除、共享附件 b 因合同 B 引用保留，合同 B 再 purge 后 b 删除）全部通过；测试数据与临时脚本全清、演示库恢复原状
- **2026-08-09 随 v2.44.1 发布**

## v2.44.0（2026-08-08）— Word/Excel 在线预览 + 资料库权限拆分与移动端只读 + 附件全链路修复（MINOR · 积攒收口）

本轮将第五轮至第八轮积攒的改动统一收口发布（含 v2.43.6 功能升级、v2.43.7、第八轮 OLE 修复），详细排查/修复/验证过程见下文「第三轮~第八轮」各节。

### 功能升级（Word/Excel 在线预览 + 资料库权限拆分 + 移动端纯只读）

- **通用预览页 `/m/office-preview?p=&t=&name=`**（`mobile/office_preview.php`，由 doc-preview 泛化）：pdf→PDF.js canvas（容器自适应缩放 + DPR 高清）；docx→docx-preview **0.3.7** + JSZip 3.10.1（`docx.renderAsync` 语义还原）；xlsx→SheetJS **0.20.3**（`sheet_to_html` 多 sheet 分块渲染）；三渲染库**自托管** `public/static/vendor/office-preview/`（v2.42.0 起全站禁 CDN，钉钉内网可用性 P0）；顶部导航统一「← 返回 + 文件名 + 下载」
- **Auth 中间件放行路径同步**：`m/doc-preview` → `m/office-preview`（令牌模式同 `/preview`，旧 `/m/doc-preview` f 格式保留兼容解析）
- **资料库权限拆分**：`library:manage`（id=33）→ `library:upload`（44）/`library:edit`（45）/`library:delete`（46），角色权限配置页自动分组渲染可逐角色勾选；PC 端上传/编辑/删除、移动端上传入口均按新权限码门控
- **资料库格式收窄七类**：移除 gif/webp（与合同附件 `resolve_attachment_ext()` 口径一致）；PC 上传弹窗 accept 与提示同步
- **移动端资料库纯只读**：删除 `resourceForm()`/`resourceEdit()` 方法、`/m/resource/create` 与 `/m/resource/<id>/edit` 路由、`mobile/resource_form.php` 整视图；列表去上传入口（右侧恒定「N 条」）、详情去编辑/删除，保留阅读 + 开票资料一键复制
- **附件白名单扩容 xls/xlsx 七类**（pdf/doc/docx/xls/xlsx/jpg/png）：`resolve_attachment_ext()` 精确白名单 + OLE 按扩展名回退；PC/移动双端 accept、ContractFormConfig 同步

### 修复（第五~八轮）

- **docx 下载变 preview.htm**：`PreviewController::resolveDisplayName()` 返回业务原始文件名（`Content-Disposition` 从物理名改业务名）；移动端 doc/docx 不再 iframe 内嵌，与 xls/xlsx 同走「不支持在线预览 + 下载原文件」兜底（带令牌 `/preview` 代理 + 业务名 fetch+blob）；原生下载 `<a download="业务名">` 双保险
- **移动端 PDF 预览模糊**：canvas 内部像素 × devicePixelRatio + `page.render({transform:[dpr,...]})`（PDF.js 官方高清做法），桌面 dpr=1 行为不变
- **资料库移动端同步合同附件「下载 + 预览」**（v2.43.7）：附件卡片「下载 + 预览」双按钮；下载走 `Attachments.normalizeUrl(fileUrl, ptoken)` → 带令牌 `/preview` 代理 → `Attachments.download()`（业务原始文件名；iOS 新窗口兜底）；预览沿用 v2.43.6 分流
- **图片下载格式/预览没反应**：移动端图片下载改 `window.open('/preview?p=&t=')` 打开大图长按保存（WebView 对 blob+a[download] 支持差）；移动端图片预览 `img.src` 走令牌代理（无会话 WebView 不再 401 空白）；PC 图片预览改 Bootstrap Modal 内嵌（钉钉 PC 拦截新标签）
- **`Attachments.normalizeUrl()` 令牌点号判断缺陷**：base64 令牌不含点号导致 `indexOf('.')` 恒 false → 全部 URL 缺 `t` 参数；改 `if (token)` 非空即拼
- **资料库 OLE 回退修复（第八轮）**：`resolve_library_attachment_ext('application/x-ole-storage')` 原返回 NULL 致老格式 doc/xls 上传被拒（合同侧有 OLE 回退、资料库侧缺失）；补 x-ole-storage/vnd.ms-office 按客户端原始扩展名回退 doc/xls，octet-stream 一律拒绝；`ResourceController::save` 改传 `$file->getOriginalExtension()`；新增 `testOleDocXlsFallback()` 单测
- **移动端资料库上传保存 403 / 双同名 file 字段**：裸 fetch 补 `X-CSRF-TOKEN` 头；`new FormData` 剔除空 file input（防 PHP 多文件语义误判）

### 验证

- PC 1280px + 移动 390px（钉钉 UA）七类全格式双端回归（资料库详情 + 合同附件）真实点击全部通过；office-preview 三渲染器实测渲染成功（PDF canvas 382×540 1/1、docx docx-wrapper、xlsx Sheet1 表格数据全对）
- curl 无 Cookie 外部浏览器：七类 `/preview` 有效令牌 200 + 正确 Content-Type + 业务名 Content-Disposition；无效/无令牌 302 跳登录
- php -l 全绿；phpunit **65 tests / 172 assertions 全绿**
- 测试数据与临时脚本全清，admin 密码已恢复原值
- **部署提示**：存量库需执行 `database/migration_v2.43.6_library_perms.sql`（资料库权限拆分；新部署由初始化脚本带出）；包内新增静态资源 `public/static/vendor/office-preview/`（docx-preview.min.js / jszip.min.js / xlsx.full.min.js）需随包部署；无其他数据库变更，可零停机部署

### 出包审查补充（2026-08-08 交付收口）

- **演示库健康修复（用户报告：打包演示库有待审批合同不在审批流程中、没有审批人、部署后管理员无法终止）**：排查确认原演示库为历史累积（seed + 多轮测试残留）——6 个异常审批实例（5001/5002/5042/5043/5044/5049：含仅 CC 无审批人的 5001/5002、孤儿实例 5049）、4 个 seed 合同状态被测试污染（智联科技-小程序开发 DRAFT→PENDING_APPROVAL、精密制造-注塑模具 DRAFT→EXECUTING、云算力 PENDING_APPROVAL→REJECTED、两江数字 DRAFT→PENDING_APPROVAL）、审计日志 407 条（seed 仅 5 条）、notification 91 条、孤儿发票/修订等。修复：用 `init_sqlite.php + seed_demo.php` **重建演示库**（各表与 seed 完全一致：合同 18 / 审批实例 16 / 审计 5 / 资料库 2）；浏览器实测待审批合同（前海云创 node1 张经理待审、云算力 node2 财务会签待审）审批人齐全、审批进度正常、「撤回审批」可用
- **出包策略变更（用户要求：后续出包清除演示库，演示数据仅在开发/测试环境加载）**：v2.44.0 起出包默认**纯生产包**（release.sh `DEMO_DATA` 默认 0）——不携带 `runtime/data/contract.db`、`database/seed_demo.php`、`demo.env.example`（三者从源码树排除，仅 `DEMO_DATA=1` 时显式注入）；桌面交付同步不含 demo.env.example；MANIFEST 标注「演示数据：未包含」。本地开发/测试环境演示库照常由 `init_sqlite.php + seed_demo.php` 加载
- 最终交付：`releases/contract-dingtalk-v2.44.0.tar.gz`（纯生产包 5.16MB / SHA256 `aef947dd1d649a15781286e61b96d7124fc4a3b50b6010a7a4aeeb5786a1f467`）+ 桌面「合同管理系统_v2.44.0」（tar.gz + MANIFEST + 5 份中文化文档，无演示数据）

---

## 图标显示问题修复总览（v2.43.1 + v2.43.2，2026-08-08）

钉钉移动端图标显示为「长方形对角线」（缺字形方框）问题，经两轮定位与修复闭环。最终根因：**钉钉 WebView 对 woff2 字体加载失败**（v2.43.0 子集化时删除了官方 woff 回退格式）。两轮修复均保持 PATCH 级版本（v2.43.1 → v2.43.2），无数据库变更，可直接零停机部署。

| 版本 | 修复内容 | 状态 |
|---|---|---|
| v2.43.1 | 图标子集产物**文件名版本化**（消除同名文件缓存混用） | 已发布，未根治 |
| v2.43.2 | @font-face **双格式回退**（woff2 外链 + woff base64 内嵌） | 已发布，根治 |

---

## v2.43.5（2026-08-08）— 附件预览跳出钉钉免登录：doc-preview 页面令牌放行 + 资料库预览补令牌 + JWT fallback 密钥持久化修复（PATCH）

### 用户报告
- 附件预览跳出钉钉（被 WebView 甩到外部浏览器/查看器）后提示账号密码登录。

### 排查过程（三层根因）
1. **doc-preview 页面本身需登录**：合同/审批 PDF 预览链接已带预览令牌（`/preview?p=...&t=...`），但 PDF.js 预览**页面** `/m/doc-preview` 不在免认证范围——外部浏览器无会话 Cookie 时，页面请求先被 Auth 中间件拦截跳登录页，**令牌机制根本没机会生效**（令牌只在 iframe 的 /preview 文件流上）；
2. **移动端资料库预览链接未带令牌**：[resource_detail.php](app/view/mobile/resource_detail.php) 拼的是 `/preview?p=<file_url>`（无 t），外部浏览器访问直接 401；
3. **JWT fallback 密钥持久化失败（Windows 实踩）**：[AuthLogic::resolveFallbackJwtSecret()](app/common/logic/AuthLogic.php) 用 `runtime_path('jwt_secret.txt')` 取文件路径，而 ThinkPHP `runtime_path()` **恒把参数当「目录名」**（返回 `runtime\jwt_secret.txt\`，尾随分隔符）→ `is_file()`/`file_put_contents()` 必然失败 → 每次请求生成新的随机密钥 → 预览令牌签发与校验请求间密钥不一致，**令牌校验全部失败**（本地/演示/未配强 JWT_SECRET 环境受影响）。

### 修复方案（3 处）
**① Auth 中间件对 `/m/doc-preview` 令牌放行**（`app/middleware/Auth.php`）——与 `/preview` 同模式：解析 `f` 参数内嵌的 `/preview?p=...&t=...`，`validate_preview_token` 有效即放行（页面不含业务数据，数据经 `/preview` 流再次鉴权，纵深防御不变）；无令牌/无效令牌仍走正常 Auth 拦截：
```php
if ($path === 'preview' || $path === 'm/doc-preview') {
    $pt = $request->param('t', '');
    $pp = $request->param('p', '');
    if ($path === 'm/doc-preview') {
        $f = $request->param('f', '');
        if ($f !== '' && strpos($f, '/preview') === 0) {
            $qp = [];
            parse_str((string)parse_url($f, PHP_URL_QUERY), $qp);
            $pp = (string)($qp['p'] ?? '');
            $pt = (string)($qp['t'] ?? '');
        }
    }
    if ($pt !== '' && function_exists('validate_preview_token') && validate_preview_token($pp, $pt)) {
        return $next($request);
    }
}
```

**② 移动端资料库预览链接补令牌**（`app/view/mobile/resource_detail.php`）：
```php
var ptoken = <?=json_encode(preview_token($item['file_url']))?>;
var previewUrl = '/preview?p=' + encodeURIComponent(fileUrl) + '&t=' + encodeURIComponent(ptoken);
```

**③ fallback 密钥路径修复**（`app/common/logic/AuthLogic.php`）：
```php
$file = function_exists('runtime_path')
    ? rtrim(runtime_path(), '/\\') . DIRECTORY_SEPARATOR . 'jwt_secret.txt'
    : (sys_get_temp_dir() . '/contract_review_jwt_secret.txt');
```

### 验证（curl 无 Cookie 模拟外部浏览器）
- `/m/doc-preview?f=/preview?p=<真实PDF>&t=<有效令牌>` → **200** 渲染 PDF.js 预览页（含 pdf-container）✓
- `/m/doc-preview?f=...&t=<无效令牌>` → **302** 跳登录页（控制组）✓
- `/preview?p=<真实PDF>&t=<有效令牌>` → **200** `application/pdf` inline ✓
- 移动端资料库点击「预览」生成 URL 已含 `&t=<令牌>`（浏览器实测跳转）✓
- 修复前 `jwtSecret()` 两次调用返回不同密钥（SAME=0）；修复后一致（SAME=1），`runtime/jwt_secret.txt` 正常生成 ✓
- php -l 三个改动文件全绿

### 第二轮修复（2026-08-08 用户复测：已部署 v2.43.5 后 PDF 预览/下载仍跳出外部浏览器停登录页 + 移动端 PDF 左侧被遮挡）

**复测反馈**：① 移动端点击「下载」跳外部浏览器停登录页；② PC 端点击「预览」/「手动打开」跳外部浏览器停登录页（地址栏有 redirect 参数）；③ 移动端钉钉内 PDF 预览能打开但左侧内容被遮挡（预览窗口在最左仍看不到 PDF 左侧）。

**补充根因**：
- **f 嵌套参数脆弱**：预览页跳转用 `?f=/preview?p=...&t=...`（f 值内嵌嵌套参数、双重编码）——钉钉把页面甩给外部浏览器时 URL 规范化/二次解码会把 f 内的 `?p&t` 拆散，服务端解析不到令牌 → 跳登录；
- **下载 URL 无令牌**：移动端下载走原始 `/uploads/...` 静态路径（无 t），外部浏览器无会话时被鉴权拦截（PC 有会话所以能下载）；
- **PDF.js 固定 scale=1.5**：A4 宽页面 ×1.5 后 canvas 远超手机屏宽，`.pdf-container` 居中 + overflow 时左侧溢出不可达 → 左侧内容看不到。

**补充修复（4 处）**：
- **补丁② 令牌改顶层参数**（`Auth.php` + `MobileController::docPreview()` + `lightbox.js`/`contract/detail.php`/`resource_detail.php`）：预览页跳转改 `/m/doc-preview?p=<路径>&t=<令牌>&name=<名>`——顶层参数无嵌套无双重编码，外部浏览器 URL 规范化不会丢令牌；`f` 旧格式保留兼容解析（不破坏已分享的短时链接）；
- **补丁③ 下载走带令牌的 /preview 代理**（`lightbox.js` 下载按钮 + `contract/detail.php` 全部 `Attachments.download` 调用点）：下载 URL 统一 `Attachments.normalizeUrl(url, ptoken)` → `/preview?p=...&t=...`，外部浏览器凭令牌免登录下载；
- **补丁④ PDF 缩放自适应容器宽度**（`pdfjs/viewer.js` + `doc_preview.php` CSS）：`scale` 由固定 1.5 改为按容器宽度适配（`min(2.5, max(0.5, (容器宽-8)/页面宽))`，整页可见），`.pdf-container` 改 `justify-content:flex-start`（防居中溢出左侧不可达）；
- 版本号**保持 v2.43.5 不升**（用户要求：修复打包不升版本号），重新出包替换。

**补充验证**（curl 无 Cookie + 浏览器移动端 390px）：
- 顶层参数有效令牌 `/m/doc-preview?p=...&t=...` → **200**；旧 `f` 格式 → **200**（兼容）；无令牌 → **302** ✓
- 移动端 390px 首次渲染：canvas 宽 382px、`left=4/right=386` 整页在视口内（`canvasVisible=true`），左侧不再遮挡 ✓
- 下载 URL 已统一为带令牌的 `/preview` 代理（调用点逐一核对）✓
- php -l 相关文件全绿

### 第三轮修复（资料库三问题 + 去除 TXT 上传，2026-08-08）

**用户报告**：① 移动端资料库上传文档后保存没成功（点击保存只有按钮刷新一下）；② 移动端上传 .docx 后在钉钉内预览提示「文档加载失败，请返回重试」；③ PC 端资料库所有内容点击无法打开详情。另：资料库去除 TXT 上传。

**排查与修复（5 处）**：
- **① 移动端保存 403（`app/view/mobile/resource_form.php`）**：裸 fetch 未带 `X-CSRF-TOKEN` 头 → 后端 Csrf 中间件 403 拦截、`r.json()` 解析失败走 catch（PC 端 `$ajax` 自动带头所以正常）。补丁：fetch 显式携带 `X-CSRF-TOKEN: csrfToken()`；
- **② 移动端保存「服务器接收异常」（`app/view/mobile/resource_form.php`，根因复盘）**：表单含两个同名 `name="file"` 的 input（`fileInput` + `cameraInput`），`new FormData(this)` 会把空的那个也提交为空 File 条目 → PHP 侧 `$_FILES['file']` 被识别为多文件上传（error=4）→ `ResourceController::save` 抛「文件上传失败（服务器接收异常）」。补丁：`fd.delete('file')` 后仅追加实际有文件的 input，保证单文件语义；
- **③ 移动端 docx 预览「文档加载失败」（`app/view/mobile/resource_detail.php`）**：原实现所有类型直跳 `/m/doc-preview`（PDF.js 只能渲染 PDF），docx 被 PDF.js 当 PDF 解析失败。补丁：引入 `lightbox.js`，预览按类型分流 `window.openPreview(fileUrl, ext, fileName, ptoken)`——PDF→PDF.js 预览页、图片→lightbox、Office→iframe 代理预览（不支持时给下载兜底）；
- **④ PC 资料库点击无反应（`route/app.php` + `ResourceController::detail` + `app/view/resource/detail.php` + `public/static/js/resource.js`）**：列表卡片无点击事件、PC 端无 `/resource/<id>` 详情路由/视图。补丁：新增 `Route::get('/resource/<id>', 'Resource/detail')` + `ResourceController::detail()`（`library:view` 权限 + `findRaw` 404 + 分类/主体/开票结构化字段展示）+ 新建 PC 详情页（附件预览/下载走带令牌 `/preview` 代理、`can_manage` 门控删除）+ 卡片整体可点击跳详情；
- **⑤ 资料库去除 TXT 上传**（`app/common.php` `resolve_library_attachment_ext` 移除 `text/plain=>txt`、PC/移动端 `accept` 与提示文案去掉 TXT、`resource.js` 图标映射移除 txt、`tests/unit/ResourceControllerTest` 断言改为拒绝）。

**验证**：
- 单元测试 `ResourceControllerTest` 6 测试 29 断言全绿（含 txt 拒绝断言）✓
- 移动端 390px 实机上传 PNG → 保存成功跳 `/m/resource`、新纪录出现 ✓；上传 TXT → 后端拒绝「文件真实类型不被支持（text/plain）」✓
- 移动端 docx 预览 lightbox 打开且 iframe 出现 ✓；PC `/resource/<id>` 详情页渲染正常 ✓
- 测试数据（临时资料 65 + 物理文件 + runtime 测试文件）全部清理 ✓

### 第四轮：合同/资料库附件全链路测试 + 白名单增加 Excel（xls/xlsx，2026-08-08）

**用户要求**：测试合同附件的上传、预览、下载在钉钉移动端/PC 端内部的可用性，覆盖设计允许的所有格式；合同附件白名单增加 .XLS/.XLSX，资料库附件类型同步增加。

**白名单扩容（合同附件：pdf/doc/docx/xls/xlsx/jpg/png 七类）**：
- `app/common.php` `resolve_attachment_ext`：精确白名单新增 `application/vnd.ms-excel=>xls`、`spreadsheetml.sheet=>xlsx`；OLE 回退改按扩展名区分（`application/x-ole-storage` / `application/vnd.ms-office` → doc/xls）；
- 前端三处同步：`contract/create.php`、`mobile/contract_form.php`（`allowed` 数组 + 文案）、`ContractFormConfig.php`（upload accept MIME + 文案）；
- 资料库 9 类白名单本已含 xls/xlsx（`resolve_library_attachment_ext`），双端 accept/文案已含，无需改动；
- 单元测试 `AttachmentUploadTypeTest`：新增 xls/xlsx 放行 + OLE+xls 扩展名回退 + octet-stream 拒绝断言；**15 tests / 48 assertions 全绿**。

**实机测试发现并修复 3 处缺陷**：
1. **`Attachments.normalizeUrl()` 令牌点号判断缺陷（v2.43.5 第二轮修复实际未生效的根因）**：`preview_token()` 返回 `base64(exp.signature)`——base64 字符集不含点号，而 `attachments.js` 用 `token.indexOf('.') !== -1` 判断是否拼 `t=` → 真实令牌恒为 false → Office iframe 预览/全部下载 URL 缺 `t` 参数（curl 无 Cookie 时被鉴权拦截跳登录）。修复：`if (token)` 非空即拼 t；
2. **移动端 lightbox 缺下载按钮**：`contract_detail.php`/`approval_detail.php` 手写 `#lbOverlay` 只有「标题+关闭」，而 `lightbox.js ensureDom()` 见 `#lbOverlay` 已存在就直接 return，不补 `lbDownloadBtn` → 移动端正常预览时无下载入口。修复：`ensureDom()` 对已存在的手写结构**补齐下载按钮**，样式注入提前（幂等），自动创建分支不变；
3. **Excel（xls/xlsx）预览口径不一致**：浏览器无法内嵌渲染 Excel（`/preview` 回 `application/octet-stream`），PC 资料详情页已有「不支持在线预览」提示，但移动端 lightbox 与 PC 合同详情页仍塞 iframe 空等 12s/15s 超时。修复：三处统一直接给「该格式浏览器不支持在线预览 + 下载」兜底（`lightbox.js` Excel 分支直走 `showOfficeFallback` 且兜底按钮走带令牌的 `/preview` 代理；`contract/detail.php` 新增 XLS/XLSX 分支）。

**全链路实测结论（Browser 插件不可用，回退 Playwright，已记录）**：
- **PC 端**：`/contract/<id>` 7 类附件全部展示；PDF 预览跳转 PDF.js 页（URL 含 `p&t&name` 顶层参数）✓；doc/docx iframe 预览 `/preview?p=...&t=...` 带令牌 ✓；xls/xlsx 直显「不支持在线预览+下载」✓；资料库 xlsx 上传成功、详情页下载带令牌 ✓；
- **移动端**：附件列表 7 类展示；docx lightbox iframe src 含 `t=` ✓；jpg 图片 WebView 内渲染 ✓；PDF 整页跳 `/m/doc-preview?p=...&t=...` canvas 渲染 1/1 页 ✓；下载按钮出现且 `_lbDownloadInfo.url` 带令牌 ✓；xlsx 直走「不支持在线预览+下载原文件」（带令牌）✓；移动端资料库上传 xlsx 成功 ✓；
- **无 Cookie 外部浏览器（curl 模拟）**：`/preview?p=...&t=有效` → 200；无 t/坏 t → 302 ✓；`/m/doc-preview?p=...&t=有效` → 200（PDF.js 页渲染）✓；
- 令牌签名密钥：演示/本地 `.env` 的 JWT_SECRET 在黑名单 → `AuthLogic::jwtSecret()` 实际走 `runtime/jwt_secret.txt` 持久化密钥（v2.43.5 ③ 已修复），测试脚本需按该文件生成令牌；
- php -l 改动 PHP 文件全绿；测试数据（合同 10084、资料库 66/67、9 个物理上传文件、runtime 临时脚本/att_test）全部清理。

### 第五轮：docx 下载变 preview.htm + 移动端 PDF 预览模糊 + 资料库二次编辑与权限拆分（2026-08-08，积攒不出包）

**用户报告**：① docx 下载时变成 preview.htm；② 移动端打开 PDF 预览很模糊；③ docx 文件需要下载打开，但移动端仍有预览按钮、不能点击、没有下载按钮；④ 资料库上传后没法二次编辑，上传/编辑等权限新增为角色权限配置项。

**① docx 下载变 preview.htm（根因 + 三层修复）**：
- **根因**：移动端 docx 走 iframe 内嵌预览，钉钉 WebView 无法内嵌 Word → 触发**原生下载**；下载器对 `/preview` 的 URL pathname 段「preview」+ 无法识别的 Office MIME 命名文件 → 错存为 `preview.htm`（a[download] 无文件名、`Content-Disposition filename` 为物理名 `Ymd_His_随机` 同样影响原生下载路径）；
- **① PreviewController 返回业务原始文件名**（`resolveDisplayName()`：合同附件 `file_url` JSON 精确匹配 url 的 `name` / 资料库 `file_name`），`Content-Disposition filename` 从物理名改为业务名——任何原生下载路径（iframe 触发 / a[download] / window.open 降级）都拿到正确文件名。curl 实测：`Content-Disposition: filename="测试文档_下载测试.docx"` ✓；
- **② 移动端 doc/docx 不再 iframe 内嵌**（`lightbox.js` openPreview）：DOC/DOCX 与 XLS/XLSX 同走「当前环境不支持在线预览该文件 + 下载原文件」兜底（下载走带令牌的 `/preview` 代理 + 业务名 fetch+blob），杜绝 WebView 触发 preview.htm 下载；
- **③ 原生下载链接补 download 属性文件名**：`resource/detail.php`（PC 资料详情）与 `mobile/doc_preview.php`（PDF 预览页顶部「下载」）的 `<a download>` 补 `download="业务名"`，双保险。

**② 移动端 PDF 预览模糊（DPR 适配）**：`mobile/doc_preview.php` 的 `viewer.js renderPage` 此前 canvas 内部分辨率 = CSS 显示尺寸（`canvas.width = viewport.width`），钉钉移动端 WebView DPR≥2 时被放大显示 → 文字/线条发虚。修复：canvas 内部像素 `× devicePixelRatio`（`Math.floor(viewport.width * dpr)`）+ 固定 CSS 尺寸 + `page.render({transform:[dpr,0,0,dpr,0,0]})`（PDF.js 官方高清渲染做法，与 pdf_viewer.js 一致）。桌面 dpr=1 行为不变。

**④ 资料库二次编辑 + 权限拆分（library:manage → upload/edit/delete）**：
- **权限码拆分**：删除 `library:manage`（id=33），新增 `library:upload`（44 上传资料库）/ `library:edit`（45 编辑资料库）/ `library:delete`（46 删除资料库）——角色权限配置页（`/admin/role`）自动按 permission 表分组渲染，可逐角色勾选；默认绑定保持原口径：admin / manager / gm 三角色全量；
- **编辑功能（PC + 移动端）**：新增 `ResourceController::update`（`library:edit`，标题/分类/说明/关联主体/开票字段可改，文件**可选替换**——传新文件才替换并删除旧物理文件，物理删除复用 `removePhysicalFile()` 边界校验）；PC 详情页「编辑」按钮打开编辑弹窗（回填 + 提交 `/ajax/resource/update`）；移动端详情页「编辑」跳 `/m/resource/<id>/edit`（`Mobile::resourceEdit`），复用 `resource_form.php` 编辑模式（回填、文件选填、按钮/文案/端点按 `$isEdit` 切换）；
- **入口/按钮权限对齐**：PC 资料库列表「上传资料」按钮与上传弹窗（`library:upload`）、列表卡片删除按钮（`library:delete`）；PC 详情页编辑/删除按钮（`library:edit`/`library:delete`）；移动端资料库「上传」入口（`library:upload`）、详情页编辑/删除按钮（`library:edit`/`library:delete`）；`resource.js` 权限标志拆为 `__RES_CAN_UPLOAD/EDIT/DELETE`；
- **迁移**：`database/migration_v2.43.6_library_perms.sql`（存量库：删 role_permission 33 → 删 permission 33 → 按 code 判空插 44/45/46 → 绑定 admin/manager/gm）；三份初始化脚本 `init_sqlite.php` / `init_mysql.php` / `init.sql` 权限种子与角色绑定同步（33 → 44/45/46）。

**实测（Playwright，Browser 插件不可用已记录）**：PC 资料详情编辑弹窗打开/回填/提交成功（标题更新、category 兜底 OTHER）；角色权限配置页出现「上传资料库/编辑资料库/删除资料库」✓；移动端详情页编辑链接/删除按钮/预览按钮齐全 ✓；docx 预览点击 → lightbox 显示「不支持在线预览 + 下载原文件」按钮、无 iframe ✓；移动端编辑页标题/按钮/文件标签按编辑模式渲染 ✓；curl 验证 /preview Content-Disposition 为业务原始文件名 ✓；php -l 11 个改动文件全绿。测试数据（资料库 68 + 物理 docx）与临时脚本全部清理。**未出包（积攒）**。

### 第六轮：Word/Excel 在线预览 + 资料库收窄七类 + 移动端资料库纯只读（2026-08-08，功能升级，积攒不出包）

**用户需求**：合同附件要实现 pdf/word/jpg/png 的上传、预览或下载（最好支持预览，可引用成熟产品设计）；资料库只需 PC 端上传和重新编辑，移动端不需要上传/编辑，只需阅读和开票资料复制；资料库上传格式扩展 xls/xlsx。

**澄清结论（AskUserQuestion 确认）**：① 合同附件**保留七类**（pdf/doc/docx/xls/xlsx/jpg/png，不按字面收窄）；② 资料库格式**收窄至与合同附件一致七类**（移除 gif/webp）；③ 移动端资料库**纯只读**；④ 部署在**公司公网服务器**（非离线），允许引前端渲染库。

**① Word/Excel 在线预览（前端渲染库方案，成熟库评估后落地）**：
- **库选型**：docx-preview **0.3.7**（Apache-2.0、活跃维护、HTML 语义还原；0.4.0 起改 ESM-only，0.3.7 是最后一个带全局 `window.docx` UMD 构建的稳定版）+ JSZip 3.10.1 + SheetJS xlsx **0.20.3**（Apache-2.0、约 7.8M 周下载）；三个库均**自托管** `public/static/vendor/office-preview/`（v2.42.0 起全站禁 CDN，钉钉内网可用性 P0）；
- **通用预览页** `/m/office-preview?p=<路径>&t=<令牌>&name=<业务名>`（`mobile/office_preview.php`，由 doc-preview 泛化，`MobileController::docPreview()` 改名 `officePreview()`）：pdf→PDF.js canvas（保留容器自适应缩放 + DPR 逻辑）；docx→JSZip 解压 + docx-preview `docx.renderAsync()` 渲染 `#officeContainer`（标题/正文/表格/结束语语义还原）；xlsx→SheetJS `XLSX.read` + `sheet_to_html` 多 sheet 分块渲染（`.xlsx-sheet-title` 标题 + `.xlsx-wrap` 壳）；顶部导航统一「← 返回 + 文件名 + 下载」；旧 `/m/doc-preview` f 参数格式保留兼容解析；
- **Auth 中间件放行路径同步**：`m/doc-preview` → `m/office-preview`（令牌模式同 /preview，页面无业务数据，数据经 /preview 流再次鉴权）；
- **老格式 doc/xls 兜底**：浏览器无法内嵌渲染 → 统一「不支持在线预览 + 下载原文件」（带令牌 /preview 代理），不塞 iframe 空等。

**② 资料库格式收窄七类**：`resolve_library_attachment_ext()` 移除 `image/gif`/`image/webp`（与合同附件 `resolve_attachment_ext()` 口径一致，注释记录 v2.43.6 收窄）；PC 上传弹窗 accept 与提示文案去 gif/webp；`ResourceControllerTest` 断言改 `assertNull`。

**③ 移动端资料库纯只读（彻底移除而非仅藏按钮）**：删除 `MobileController::resourceForm()`/`resourceEdit()` 方法、`/m/resource/create` 与 `/m/resource/<id>/edit` 路由、`mobile/resource_form.php` 整视图；列表页去导航「上传」链接与 sticky 上传按钮区块（右侧恒定显示「N 条」）；详情页去编辑/删除按钮区块与 `delRes()` 脚本，保留阅读 + 开票资料一键复制。上传/编辑/删除入口只保留 PC 端（受 library:upload/edit/delete 权限门控）。

**④ 前端分流更新（3 处）**：`lightbox.js openPreview`——图片→lightbox 内嵌；PDF/DOCX/XLSX→跳 `/m/office-preview` 整页渲染；DOC/XLS→`showOfficeFallback` 下载兜底；其他→iframe；`contract/detail.php`——PDF/DOCX/XLSX 新标签开 office-preview（动态 fIcon/fLabel「已在新标签页打开 Word/Excel/PDF 预览」），DOC/XLS 下载兜底；`resource/detail.php`——新增 `$__isOfficePreview`（docx/xlsx）预览按钮走 office-preview。

**验证（Playwright 1280px PC + 390px 移动 + curl 无 Cookie 外部浏览器）**：
- PC office-preview：docx 标题/正文/表格/结束语全部渲染、无控制台错误；xlsx 双 sheet 标题 + 2 个表格、表头/数据行全对；PDF canvas 382px 1/1 页回归通过 ✓
- 移动端：docx/xlsx 点击预览真实跳转 `/m/office-preview` 并渲染成功；PDF 整页预览正常 ✓
- 移动端资料库只读：列表无上传入口、详情无编辑/删除 ✓
- PC 合同详情 docx 点击 → 「已在新标签页打开 Word 预览」提示 ✓；PC 资料详情 docx/xlsx 预览按钮走 office-preview ✓
- curl 无 Cookie：有效令牌 → 200、无效令牌 → 302 ✓
- PC 上传弹窗 accept 已收窄七类（Playwright 实测）✓
- php -l 11 个改动文件全绿；phpunit 全套 64 tests / 166 assertions 全绿 ✓
- 测试数据（资料库 78/79/80、合同 10086、6 个物理上传文件、7 个临时脚本）全部清理，演示库回落 maxRes=39 / maxC=9925 ✓
- **未出包（积攒规则）**；新增静态资源 `public/static/vendor/office-preview/`（docx-preview.min.js / jszip.min.js / xlsx.full.min.js）需随包部署；`/m/doc-preview` 路由已更名 `/m/office-preview`（旧链接由 f 兼容解析兜底，不影响已分享短时链接）。

### 第七轮：移动端资料库同步合同附件的预览与下载（2026-08-08，积攒不出包）

**用户需求**：资料库移动端除只读和开票复制外，同步合同附件的预览和下载功能。

**改动（`app/view/mobile/resource_detail.php` 单文件）**：
- 附件卡片按钮区由单个「预览」扩展为「**下载 + 预览**」两个按钮；
- 下载走与合同附件完全一致的链路：`Attachments.normalizeUrl(fileUrl, ptoken)` → 带令牌 `/preview?p=&t=` 代理 → `Attachments.download()`（fetch + blob + `a[download]` 业务原始文件名；iOS/无 fetch 时新窗口打开兜底）——被甩到外部浏览器（无会话 Cookie）时同样凭令牌免登录下载；
- 预览沿用 v2.43.6 分流（图片→lightbox / PDF/DOCX/XLSX→office-preview 整页 / DOC/XLS→下载兜底），与合同附件完全一致。

**验证（Playwright 390px 移动端 + curl 无 Cookie）**：
- docx 资料详情页「下载 + 预览」双按钮渲染 ✓；点击下载真实发起 `/preview?p=...&t=<有效令牌>` fetch（页面不跳转）✓；
- curl 无 Cookie：有效令牌 → **200** + docx MIME + `Content-Disposition: filename="<业务名>.docx"` ✓；
- 点击预览 → 跳 `/m/office-preview?p=&t=&name=` 渲染成功（标题/正文完整、无错误）✓；
- 图片资料（INVOICE 类）详情页双按钮渲染 ✓；预览 → lightbox 图片内嵌 + 下载按钮 ✓；开票资料「一键复制」保留 ✓；
- php -l 通过；测试数据（临时资源 81 + 物理 docx）已清理，maxRes=39 回落 ✓；
- **未出包（积攒规则）**。

**实测反馈修复（图片下载格式 + 预览没反应，2026-08-08）**：

用户测试「义乌十八腔网络科技有限公司」开票资料图片（id=39，jpg）：下载后不是文件实际格式、点击预览没反应。排查确认服务端 `/preview` 正常（200 + image/jpeg + 业务名 + 182728 字节与物理文件一致），根因在**客户端/WebView 链路**，三处修复：

- **① 移动端图片下载改走新窗口打开大图**（`attachments.js downloadAttachment`）：图片类型（jpg/jpeg/png/gif/webp/bmp）不再走 blob + `a[download]`——钉钉/微信 WebView 对该机制支持差，下载产物可能损坏/被系统误存 → 「下载后不是文件实际格式」；统一 `window.open('/preview?p=&t=')` 打开 inline 大图，用户长按保存原图（微信/钉钉成熟模式），iOS/无 fetch 兜底路径本就如此；
- **② 移动端图片预览走令牌代理**（`lightbox.js renderImage`）：`img.src` 原直连 `/uploads` 原始路径，无会话（被甩外部浏览器 / WebView 无 Cookie）时 401 → lightbox 弹窗空白 → 「预览没反应」；改 `Attachments.normalizeUrl(url, token)` 带令牌 `/preview` 代理（与下载/office 预览同链路），错误兜底链接同步带令牌；
- **③ PC 端图片预览改 Bootstrap Modal 内嵌**（`resource/detail.php`）：原 `target="_blank"` 新标签打开 `/preview`——钉钉 PC 内嵌浏览器拦截新标签 → 「点击预览没反应」；改为页面内 Modal 放大（img 走带令牌 `/preview` 代理），与合同详情 PC 端 Modal 预览口径一致；顺带修 `contract/detail.php` PC 端图片预览 `img.src` 同步走令牌代理（同类根因）。

**验证**：移动端（390px + 钉钉 Android UA）——预览 lightbox 弹出且 img.src 带令牌 `/preview?p=&t=` ✓、点下载捕获 `window.open('/preview?p=&t=<令牌>')` ✓；PC 端（1280px）——点预览 Modal 弹出（modalShown=true）且 img.src 带令牌 ✓；php -l 通过；测试数据与临时脚本（q_users/reset_admin_pwd + hash 备份）全清，admin 密码已恢复原值。

### 第八轮：全格式双端回归验证 + 资料库 OLE 回退修复（2026-08-08，积攒不出包）

**用户指令**：重新在移动端和 PC 端验证所有格式的预览与下载功能。

**范围**：七类（pdf/doc/docx/xls/xlsx/jpg/png）PC 1280px + 移动 390px（钉钉 UA）双端真实点击：资料库详情页 + 合同详情附件，覆盖在线预览（PDF.js / docx-preview / SheetJS）、图片 Lightbox/Modal、下载兜底、令牌代理下载。

**发现并修复 1 个真实缺陷：资料库 doc/xls 上传被拒（OLE 回退缺失）**
- 现象：回归中发现 `resolve_library_attachment_ext('application/x-ole-storage')` 返回 NULL——资料库宣称支持 doc/xls，但真实 OLE 老格式 Word/Excel 上传会被拒（合同附件 `resolve_attachment_ext()` 已有 OLE 回退，资料库侧缺失，两口径不一致）。
- 修复：`app/common.php` `resolve_library_attachment_ext()` 补 OLE 回退映射（`x-ole-storage`/`vnd.ms-office` 按客户端原始扩展名 doc/xls 放行，octet-stream 一律拒绝防伪装）；`ResourceController.php` 上传校验调用点改传 `$file->getOriginalExtension()`；`tests/unit/ResourceControllerTest.php` 新增 `testOleDocXlsFallback()` 断言（x-ole-storage+doc/xls 放行、伪装 exe 拒绝）。

**验证矩阵（Playwright 真实点击 + curl 无 Cookie）**：
- PC 合同 9925 附件区 7 行 + 2 缩略图渲染、下载按钮全带令牌 ✓；jpg 缩略图 → Modal 弹出且 img.src=`/preview?p=&t=` 令牌化 ✓；pdf/docx/xlsx → 「已在新标签页打开 PDF/Word/Excel 预览」提示 ✓；doc/xls → 「不支持在线预览 + 下载文件」兜底 ✓；
- PC office-preview 三渲染器实测：PDF canvas 382×540 1/1 页、docx docx-wrapper、xlsx Sheet1 表格 + 单元格数据全对 ✓；
- 移动端资料库 pdf/docx/xlsx 预览 → office-preview 三渲染器全部成功 ✓；doc/xls → Lightbox「不支持在线预览 + 下载原文件」✓；png/jpg → Lightbox img.src 带令牌 ✓；下载捕获 `Attachments.download('/preview?p=&t=', '业务名')` ✓；
- 移动端合同 9925 pdf/docx/jpg/doc 附件真实点击 → office-preview 渲染成功 / Lightbox 令牌化 / 下载兜底 ✓；
- curl 无 Cookie 七类 `/preview` → 200 + 正确 Content-Type + Content-Disposition 业务名 ✓；无效/无令牌 → 302 跳登录 ✓；
- phpunit ResourceControllerTest 7 tests / 35 assertions 全绿 ✓；
- 测试数据全清（资料库 82-87、合同 9925 file_url 恢复 `[]`、14 个物理文件、10 个临时脚本），admin 密码已恢复原 hash ✓；
- **未出包（积攒规则）**；存量库无需迁移（OLE 回退为校验逻辑修复）。

### 部署提示
- **本机/存量库需执行 `database/migration_v2.43.6_library_perms.sql`**（资料库权限拆分；新部署库由初始化脚本自动带出）；
- **本包新增静态资源**：`public/static/vendor/office-preview/`（docx-preview.min.js / jszip.min.js / xlsx.full.min.js，Word/Excel 在线预览渲染库，自托管不依赖外部 CDN）；
- 无数据库变更（迁移文件仅存量库手动执行），可零停机部署；
- 生产环境若已配置强 `JWT_SECRET`（≥32 字符），③ 的 fallback 不触发，不影响既有部署；未配置/弱密钥环境（演示、本地）由本次修复兜底。

---

## v2.43.4（2026-08-08）— 合同详情删除口径统一 + 状态操作入口补齐（PATCH）

### 用户反馈
- 「执行中/已归档/审批中的合同无法删除，管理员也不可」→ 确认为**数据完整性设计而非缺陷**（删除会破坏审计/回款/发票记录，应走状态操作），但暴露两个真实缺口：
  1. **删除按钮口径与后端不一致**：后端软删除（`ContractLogic::softDelete`）允许「草稿+已驳回」，详情页却仅草稿可点、已驳回不可点；
  2. **「撤回」「终止」「作废」等状态操作在合同详情页无入口**（撤回入口只在审批页、终止后端有接口但前端无按钮、作废概念不存在）。

### 修复方案（app/view/contract/detail.php）

**① 删除按钮与后端 softDelete 口径一致**——DRAFT/REJECTED 均可删除，非可删状态禁用 + 说明：
```php
<?php if(!empty($can_delete)): ?>
<?php if(in_array($contract['status'],['DRAFT','REJECTED'])): ?>
<button class="btn btn-outline-danger btn-sm" onclick="delContract(<?=$contract['id']?>)"><i class="bi bi-trash"></i> 删除</button>
<?php else: ?>
<button class="btn btn-outline-danger btn-sm" disabled title="仅草稿/已驳回状态可删除"><i class="bi bi-trash"></i> 删除</button>
<?php endif; ?>
<?php endif; ?>
```

**② 新增「撤回审批」入口**——存在 PENDING 审批实例且 `can_submit_approval` 时显示，调 `POST /ajax/approval/<id>/recall`（提交人撤回，合同回草稿可继续编辑/删除），与审批页既有撤回入口并存：
```php
$__pendingApprovalId = 0;
if (!empty($approvals)) {
    foreach ($approvals as $__a) {
        if (($__a['status'] ?? '') === 'PENDING') { $__pendingApprovalId = (int)($__a['id'] ?? 0); break; }
    }
}
```
```js
function doRecallApproval(id){
  pcConfirm({message:'确定撤回该审批？撤回后合同回到草稿状态，可继续编辑或删除。',danger:true})
    .then(function(ok){if(!ok)return;$ajax('/ajax/approval/'+id+'/recall',{method:'POST',loading:false})
      .then(res=>{showToast(res.msg||'操作完成',res.code===0?'success':'error');if(res.code===0)location.reload();}).catch(function(){});});
}
```

**③ 新增「终止」入口**——SIGNED/EXECUTING 且 `can_edit` 时显示，调 `POST /ajax/contract/status-transition`（status=TERMINATED），后端自带逾期未结回款校验（`PaymentLogic::hasOverdue`）+ 审计留痕：
```js
function doTerminate(){
  pcConfirm({message:'确定终止该合同？终止后需另走新合同，请确认无逾期未结回款。',danger:true})
    .then(function(ok){if(!ok)return;$ajax('/ajax/contract/status-transition',{method:'POST',body:new URLSearchParams({id:<?=(int)$contract['id']?>,status:'TERMINATED'}),loading:false})
      .then(res=>{showToast(res.msg||'操作完成',res.code===0?'success':'error');if(res.code===0)location.reload();}).catch(function(){});});
}
```

**④ 「作废」决策**——合同状态机无「作废」状态（终态仅 `TERMINATED`，见 `ContractLogic::STATUS_LABELS`/`TRANSITIONS`），经用户确认**不新增**：终止即终态，已覆盖作废语义；发票作废（VOID/RED）为既有功能，与合同无关。

### 验证
- 待审批合同（9908）：显示「撤回审批」、删除禁用 ✓
- 已驳回合同（9913）：删除可点击 ✓
- 执行中合同（9916）：显示「终止」→ 点击 → pcConfirm 确认 → 状态变「已终止」✓
- 终止测试后演示数据已恢复（9916 恢复 EXECUTING + 审计记录清理、临时脚本删除）
- php -l 相关文件全绿

### 部署提示
- 无数据库变更，可直接零停机部署。

---

## v2.43.3（2026-08-08）— 合同列表编辑入口状态门控 + 403 友好错误页（PATCH）

### 用户报告
- 合同管理列表点击「编辑」报「当前状态不可编辑」，且页面显示 **ThinkPHP 框架默认错误页**（V6.1.4 品牌页，含「官方手册」链接）。

### 排查过程（两层问题定位）
1. **后端校验**：[ContractController::create()](app/controller/ContractController.php) 对已有合同仅允许 **草稿(DRAFT)/已驳回(REJECTED)** 状态编辑，其余状态（待审批/执行中/已归档/已到期等）抛 403「当前状态不可编辑」——**业务拦截本身正确**；
2. **前端入口**：PC 列表页 [contract.js](public/static/js/contract.js) 对**所有状态**的合同无条件渲染编辑按钮（对比详情页 detail.php 已有 `['DRAFT','REJECTED']` 状态门控，列表页缺失）→ 用户点不可编辑合同的编辑按钮必然触发后端拦截；
3. **错误页呈现**：异常处理器 [ExceptionHandle.php](app/exception/ExceptionHandle.php) 对 HttpException 403 的**普通页面请求**直接交回 ThinkPHP 父类渲染——debug 模式下显示框架默认错误页（与 BaseController::deny() 自行渲染自定义 403 页的行为不一致）。

### 修复方案（3 处，与后端校验口径一致）

**① 列表页编辑按钮状态门控**（`public/static/js/contract.js`）——从源头消除误点报错：
```js
// 编辑按钮仅对草稿/驳回状态渲染（与后端 create() 状态校验一致）
h+='<td>'+(c.status==='DRAFT'||c.status==='REJECTED'
    ?'<a href="/contract/'+c.id+'/edit" class="btn btn-sm btn-outline-secondary" aria-label="编辑" onclick="event.stopPropagation()"><i class="bi bi-pencil"></i></a>'
    :'')+'</td>';
```

**② ExceptionHandle 403 页面请求渲染友好 403 页**（`app/exception/ExceptionHandle.php`）——替代 ThinkPHP debug 默认错误页，与 BaseController::deny() 行为对齐（返回驾驶舱/工作台按钮 + 移动端自适应），业务拦截消息随异常 message 传入模板：
```php
if ($e->getStatusCode() === 403) {
    $isMobile = function_exists('is_mobile_request') && is_mobile_request();
    View::assign('back_url', $isMobile ? '/m' : '/dashboard');
    View::assign('home_text', $isMobile ? '返回工作台' : '返回驾驶舱');
    View::assign('err_msg', $e->getMessage());
    return Response::create((string) View::fetch('error/403'), 'html', 403);
}
```
（JSON/AJAX 请求保持既有 {code,msg} 标准化返回不变）

**③ 403 模板支持业务消息展示**（`app/view/error/403.php`）——`$err_msg` 非空时标题显示具体业务原因 + 操作引导，缺省保持原「权限不足」文案。

### 验证
- **列表页门控**：执行中/待审批/已归档/已到期/已完成/已通过 → 无编辑按钮；已驳回 → 有编辑按钮 ✓；
- **友好错误页**：直接访问 `/contract/9910/edit`（待审批、同部门）→ 自定义 403 页显示「当前状态不可编辑」+ 返回按钮，**无 ThinkPHP 品牌错误页** ✓；
- **越权场景**：访问他人合同编辑 URL → 显示「无权查看该合同」友好页（同一机制）✓；
- php -l 相关文件全绿；包核验无残留。

### 部署提示
- 本版本无数据库变更，可直接零停机部署；
- 部署后：不可编辑状态的合同不再显示编辑按钮；即使直接输入 URL 访问编辑页，也显示友好业务提示而非框架错误页。

---

## v2.43.2（2026-08-08）— 钉钉 WebView 字体加载兼容：@font-face 双格式 + woff base64 内嵌（PATCH · 最终修复）

### 问题现象
- v2.43.1 部署并**清除缓存**后，钉钉内移动端图标仍显示「长方形对角线」（缺字形方框），PC 端正常；
- **决定性线索：微信内打开图标正常**——同一份文件，微信 WebView 能加载字体、钉钉不能。

### 排查过程（排除法收敛）
1. 本地复核：PC + 移动模拟下 CSS/字体加载全部正常（`fontsCheck:true`、158 码点完整）→ 包内文件无缺陷；
2. 已部署 v2.43.1 + 清缓存仍复现 → 排除缓存错位；
3. 微信正常、钉钉异常（同源同文件）→ 排除文件缺失/服务器 MIME/网络因素；
4. 收敛结论：问题在**钉钉 WebView 对 woff2 字体的加载行为**。

### 根因
- v2.43.0 子集化时 `@font-face` 仅声明 woff2 格式（`generate_icons_subset.php` 原注释"字体 URL 去 woff 旧格式"），**删除了官方 bootstrap-icons 原版的双格式声明（woff2 + woff）**；
- 钉钉 WebView（X5/系统内核）对 woff2 加载失败（格式兼容或字体请求被 WebView 安全策略拦截）→ `.bi::before` 的 PUA 码点无字形 → fallback 系统字体渲染为缺字形方框；PC Chrome / 微信 WebView 支持 woff2 → 正常。

### 修复方案：@font-face 双格式回退（woff2 外链 + woff base64 内嵌）
```css
@font-face{font-display:block;font-family:bootstrap-icons;
  src:url("fonts/bootstrap-icons.v2.43.2.woff2") format("woff2"),          /* 现代浏览器优先（12.8KB 外链，可缓存） */
      url(data:font/woff;base64,<woff子集>) format("woff")}                /* 钉钉/旧 WebView 回退（base64 内嵌，无需网络请求） */
```
- **woff 子集**：fontTools 从子集 woff2 转换（`flavor='woff'`，158 码点完整，15.4KB → base64 20.5KB）；
- **效果**：CSS 能加载即能渲染图标——彻底规避钉钉 WebView 的字体加载差异；CSS 总量 6.7KB → 27.4KB（移动端可接受，对比原始全量 211KB）；
- **产物**：保持文件名版本化（bootstrap-icons.v2.43.2.min.css / fonts/bootstrap-icons.v2.43.2.woff2），删除 v2.43.1 同名产物；
- **同步**：6 处引用（layout/header.php、mobile/_head.php、auth/login.php、mobile/login.php、error/403.php、error/500.html）+ scripts/check_icons.sh + config/version.php；`generate_icons_subset.php` 改为双格式生成逻辑（woff base64 素材存 runtime/_bi_subset.woff.base64.txt 供后续重建）。

### 验证
- 门禁通过：子集 CSS 158 规则完整；@font-face 双格式声明正确；woff base64 解码魔数 `wOFF` 合法（15,404 bytes）；
- **回退路径专项验证**：浏览器动态 `new FontFace('bi-woff-fallback','url(data:font/woff;base64,...)')` 加载成功且 `document.fonts.check()` 返回 true → 钉钉场景下 CSS 能加载则图标必能渲染；
- 浏览器 PC/移动模拟实测：新 CSS 加载、`fontsCheck:true`、图标正常；php -l 全绿；包核验无残留。

### 部署提示
- 本版本 @font-face 自带 woff 回退，钉钉 WebView **无需任何缓存操作**即可恢复图标；
- 若个别老钉钉版本仍有异常，可在钉钉「设置→通用→清除缓存」后再试（兜底）。

---

## v2.43.1（2026-08-08）— 图标子集产物文件名版本化（PATCH · 第一轮修复，已并入 v2.43.2 无需单独部署）

### 问题现象
- v2.43.0 部署升级后，钉钉移动端所有图标显示为「长方形内对角线」（缺字形方框），PC 端正常。

### 排查过程
1. 本地核验：全站 134 个图标类名全部命中白名单、子集 CSS 158 规则完整、PC/移动模拟渲染正常 → 包内文件无缺陷；
2. 初判根因：v2.42.0 自托管**全量**图标（CSS 83.9KB / woff2 127.3KB），v2.43.0 子集化（6.7KB / 12.8KB）但**产物保持同名文件**（`bootstrap-icons.min.css` + `fonts/bootstrap-icons.woff2`）→ 生产 symlink 升级后同名文件被覆盖为子集，移动端 WebView 缓存旧完整 CSS 命中新子集字体 → 旧 CSS 数百图标码点在 158 字形中缺失 → 方框；
3. 该修复消除了同名文件混用隐患，但未覆盖"钉钉 WebView 对 woff2 加载失败"这一更深层根因（v2.43.2 定位）。

### 修复方案：子集产物文件名版本化
- CSS `bootstrap-icons.min.css` → `bootstrap-icons.v2.43.1.min.css`、字体 → `fonts/bootstrap-icons.v2.43.1.woff2`，@font-face 指向版本化字体名——新旧 URL 彻底分离，WebView 无法命中旧缓存；
- 删除旧同名文件（不保留兼容层）；6 处引用 + check_icons.sh + generate_icons_subset.php 同步版本化。

### 验证
- 门禁双侧通过；浏览器 PC/移动模拟实测 `fontsCheck:true` 图标正常；php -l 全绿；包内无旧同名文件残留。

### 说明
- 本版本修复内容（文件名版本化）已完整包含在 v2.43.2 中，**无需单独部署 v2.43.1**，直接部署 v2.43.2 即可。

---

## v2.43.0（2026-08-08）— 档 3 前端瘦身：Chart.js 按需加载 + 图标子集 + 白名单门禁（MINOR）

### 档 3-C：Chart.js 视口懒加载（dashboard 首屏瘦身）
- **现状**：dashboard 顶部同步加载 chart.umd.min.js（~200KB）+ datalabels（~13KB），首屏无图表时也全量下载解析
- **修复**：`dashboard/index.php` 移除顶部同步引用，改为底部 `loadChartLibs()` 动态注入；`IntersectionObserver` 监听 `#dashPartial` 容器（`rootMargin:200px`）可见才注入 chart → datalabels（依赖链有序加载）；无 IO 环境降级 `DOMContentLoaded` 立即加载；`window.__chartLoading` 锁防并发重复注入
- **验证**：浏览器实测 dashboard 图表正常渲染（Chart 全局可用、canvas 1 个、依赖顺序正确）

### 档 3-A：bootstrap-icons 图标子集瘦身 + 白名单门禁
- **现状**：bootstrap-icons.min.css 83.9KB + woff2 127.3KB（全量 2000+ 字形），实际全站仅用少量图标
- **修复**：提取全站实际图标白名单 `scripts/icons_whitelist.txt`（158 个：静态引用 133 + 附件类型/业务/错误页动态值域 25）；`runtime/_icons.php` 解析全量 CSS 提取码点生成子集 CSS（6.7KB）；fontTools 从**全量字体**子集化 woff2（12.8KB，删除旧 woff 格式）；替换 `public/static/vendor/bootstrap-icons/` 产物
- **门禁**：新增 `scripts/check_icons.sh`（静态引用 `bi bi-*` 扫描命中白名单 + 子集 CSS 完整性校验）接入 release.sh 发布卡点；动态拼接值域（`bi-<?=...?>` 三元、admin 字典页 `$labels` 数组、error/500.html）人工登记白名单
- **子集化暴露并修复 3 处真实图标 bug**：mobile/resource.php `bi-fileearmark-text` 缺连字符（图标一直不显示）→ `bi-file-earmark-text`；admin/index.php 离职交接弹窗 `bi-person-arrows`（图标不存在）→ `bi-arrow-left-right`；mobile/customer_detail.php 联系人标题 `bi-person-lines`（图标不存在）→ `bi-person-lines-fill`
- **验证**：字体 cmap 全覆盖 158 码点；PC 1280px（admin 用户/钉钉/字典 tab）+ 移动 375px（/m、/m/resource、/m/customer/102）浏览器实测可见图标零缺失（隐藏容器内 width=0 属正常行为）；门禁模拟 PASS

### 收尾：卡片标题文案口径优化（与「仅已生效交易合同」口径对齐）
- **背景**：审查中发现仪表盘「合同总额」类卡片标题与后端口径（`ReportLogic::dashboardSummary` 仅统计已生效交易合同，排除 DRAFT/REJECTED/PENDING_APPROVAL/非交易/框架）表述不一致，标题会误导用户以为含全部状态
- **修复**：`dashboard/_partial.php` 4 处卡片标题对齐口径——管理员「合同总额」→「生效合同总额」、审批人「本范围合同总额」→「本范围生效合同总额」、财务「合同总额」→「生效合同总额」、普通员工「我的合同总额」→「我的生效合同总额」（注释同步）；`customer/detail.php` fallback 分支「合同总额」→「关联合同金额」（该值仅统计关联列表前 20 条合同，非全量）
- **全站盘点**：grep 33 处「总额」文案逐一核实口径（ReportLogic/PartyLogic/ProjectLogic/CustomerLogic/移动报表），其余均正确无需改动
- **验证**：php -l 全绿；浏览器实测管理员/审批人分支标题正确显示，800px 单行无横向滚动

### 收尾：仪表盘 KPI 分支角色画像判定修复（v2.40.2 基础权限回归）
- **背景**：v2.40.2 将 `approval:view`/`payment:view` 纳入全员默认基础权限后，`DashboardController` 原「hasPermission('approval:view') 判审批人、hasPermission('payment:view') 判财务」失效——所有非管理员恒命中审批人分支，财务分支与普通员工分支（_partial.php 的 elseif/else）永不可达
- **修复**：分支判定改用与 `sidebar.php` / `MobileController::more()` 同口径的角色画像变量（不覆盖 can_approve/can_finance 原语义，快捷操作按钮不受影响）：
  - `is_manager` = 非管理员且有 `approval:approve` + `supplier:create`（经理审批 + 业务创建）
  - `is_finance` = 非管理员且有 `payment:create` + 无 `supplier:create`（财务录回款但不创建供应商）
  - `_partial.php` 分支：`is_admin → is_manager → is_finance → else（普通员工）`
- **验证**：php -l 全绿；浏览器四角色实测——admin 管理员分支、manager01 审批人分支（待我审批 4）、finance01 财务分支（已收金额 ¥420,000）、ccnoperm 普通员工分支（我的生效合同总额）全部正确渲染

## v2.42.0（2026-08-08）— 钉钉内网可用性：静态资源本地化 + 品牌蓝统一 + 移动工作台瘦身（MINOR）

### 档 1：CDN 静态资源本地化（P0 钉钉内网可用性）
- **根因**：全站 19 处引 jsDelivr CDN（bootstrap css/js、bootstrap-icons、chart.js、chartjs-plugin-datalabels），钉钉企业内网/受限网络下 CDN 不可达时 PC 布局全崩、图标全无、图表不渲染——HTML 在但样式全丢
- **修复**：5 个静态资源下载自托管至 `public/static/vendor/`（bootstrap@5.3.3 css/js、bootstrap-icons@1.11.3 + woff2/woff 字体、chart.js@4.4.1、chartjs-plugin-datalabels@2.2.0）；全站 19 处引用改 `asset_url()` 本地路径（`error/500.html` 纯静态页用 `/static/` 相对路径）；钉钉 JSAPI（`g.alicdn.com`）保留不动（阿里 CDN 钉钉内网可达性无问题）
- **涉及**：layout/header.php、layout/footer.php、auth/login.php、dingtalk/entry.php、error/403.php、error/500.html、dashboard/index.php、mobile/_head.php、mobile/login.php

### 档 2：首屏瘦身 + 品牌一致性
- **移动工作台去 Bootstrap**：`mobile/index.php` 移除完整 `bootstrap.min.css`（约 200KB 首屏冗余）与内联重复组件定义（`.m-card`/`.m-tabbar` 系列/全局去下划线——`.m-tabbar` 收敛回 mobile.css v2.20 新版，恢复 safe-area 与 44px 触控高度）；`mobile.css` 补齐工作台用到的 Bootstrap 工具类子集（badge/rounded-pill/text-muted|warning|primary|danger|success/text-center/w-100/d-block/d-flex/mt-*/mb-*/me-*/ms-*），视觉不变
- **登录/entry 品牌蓝统一**：PC 登录页背景由紫色渐变（#667eea→#764ba2，全站唯一非品牌 VI 页面）改品牌蓝渐变（#0b5ed7→#6ea8fe，对齐移动登录页）；钉钉免登 entry 页灰底改品牌蓝渐变 + 白字白 spinner

### 验证
- 浏览器实测：PC dashboard 图表（本地 chart.js）渲染正常、零 CDN 请求、5 个 vendor 资源带 filemtime 指纹；移动工作台 375px 无横向滚动、4 Tab（56px）、badge/卡片/图标字体正常；PC 合同列表 800px 正常（按钮 10px/输入 40px 品牌 VI 保持）；移动合同列表 375px 正常；改动 10 文件 php -l 全绿

## v2.41.0（2026-08-08）— 审查修复 P0/P1/P2 全批次 + 存量门禁修复 + 真实环境回归（MINOR）

### P0 数据风险修复
- 审批提交防重复锁：`app/view/approval/create.php` 新增 `__approvalSubmitting` 锁 + 提交按钮禁用 spinner + `.catch` 兜底释放（三路径全覆盖）
- 财务「确认开票」防连点锁：`app/view/finance/index.php` 新增 `__invActing` 锁（P2-8 发票管理 Tab 直开发票入口）
- 移动端 Loading 卡死修复：`public/static/js/mobile-common.js` 补 `hideLoading()` 定义（boolean 版 showLoading 设 `window.__mobileShowLoading` 阻止 app.js 计数器版覆盖）

### P1 全站状态/异常一致性
- **裸 fetch 收敛 `$ajax`**：contract/detail.php 14 处、customer.js / project.js / approval_index.js / customer_pool.js / resource.js / notification.js / invoice-apply.js 列表加载、supplier/recycle/finance tax/remind/archive/report monthly/admin index 等——统一走 app.js 封装（非 2xx JSON 透出后端 msg、loading/silent 控制）
- **加载失败重试**：列表页加载失败渲染「加载失败，点击重试」+ 重试按钮
- **空态组件化**：`emptyState()` 统一空态（icon/title/desc/CTA），`canCreate=false` 时显示「无新建权限，请联系管理员」
- **导出防重复**：contract.js `__contractExporting`、report monthly/admin `__backupExporting` 5s 锁防连点重复导出
- **离页保护**：contract/customer/supplier/company 4 个 create 页 `beforeunload` + `__formDirty` 脏标记防误关
- **字段级校验**：contract/create.php `is-invalid` 标红 + 滚动定位首个错误字段 + 输入时清除错误态
- **移动端可用性**：mobile.css 触控目标 38-44px；finance/customer_pool 弱网重试；approval_detail 驳回二次确认；reminders Tab1/Tab2 加载更多

### P1-7 全面化
- invoice-apply.js / contract/detail.php 开票表单：applyFields 校验 `firstBad` + `is-invalid` 标红
- finance/index.php + detail.php：confirmPay/submitConfirmPay 金额超限标红 + 输入清除

### P2 交互与反馈
- app.js `$ajax` 非 2xx JSON 响应透出后端 `msg`（原只显示「请求失败」）
- approval/detail.php doRecall、contract/detail.php markOverdue 补 `pcConfirm()` 二次确认
- 移动端 reminders 加载更多、finance focusin 滚动、contract_form mFlagReq 必填红框

### 产品决策：移动端底部导航固定 4 Tab
- `app/common.php` 的 `mobile_tabbar()` 固定为 工作台/合同/客户/更多，移除角色替换逻辑（原 `$tab3` 按角色替换为审批/财务/客户，但页面只传 home/contract/customer/more 固定 key，导致替换分支永不命中、审批/财务入口丢失且高亮失效）

### 存量门禁修复（2026-08-08 首次 6 门禁全绿）
- **schema_parity**：init_mysql.php / init_sqlite.php 补齐 v2.40.0 新增字段——customer_activity.next_follow_at、customer.industry、user.need_handover、project.stage、project.progress
- **dead_entry**：删除 /notification 死功能——`route/app.php` 的 `Route::get('/notification', 'Notification/index')`、`NotificationController::index()`、`app/view/notification/index.php`（AJAX 接口与移动端消息中心保留）

### 真实环境回归新发现并修复（本版本关键）
- **合同详情页模板编译 500**：`loadPayments(){$ajax('/ajax/payment/list/<?=$contract['id']?>',{loading:false}...)` 中 JS 对象字面量 `{$` 紧邻被 ThinkPHP 模板引擎误解析为变量标签，编译产物 PHP 语法错误 `unexpected identifier "id"`，页面 500——静态门禁（php -l）无法捕获，真实环境浏览器回归才暴露；修复为 URL 变量提前拼接（`var __payUrl=...;$ajax(__payUrl,...)`）消除 `{$` 紧邻

### 验证
- 6 道发布门禁全绿：schema_parity / db_comments / view_globals / frontend / dead_entry / PHPUnit
- 真实环境浏览器 E2E 闭环：登录 → 建合同 10083（两步向导+附件上传）→ 提交审批 5101 → 法务（admin，legal 角色）+ 部门经理（manager01）两节点审批通过 → 登记回款 ¥5,000 成功 → 申请开票 ¥10,000（增值税专用发票，待审批）→ JS 错误 0
- 回归测试数据（合同/审批/回款/发票）与临时脚本已全部清理

### 审查补充修复（部署一致性 P1×3 + 登录页收敛，2026-08-08）
- **init_sqlite.php Dotenv v5 API 兼容（P1）**：`new \Dotenv\Dotenv(ROOT_PATH)` 为 v3/v4 旧构造函数，composer 锁定 phpdotenv v5.6.1（vendor 已装 v5）下实测抛 TypeError（`must be of type StoreInterface, string given`）——存在 `.env` 时全新部署执行 init 直接 Fatal；改为 `\Dotenv\Dotenv::createImmutable(ROOT_PATH)`（v5 API），实测正常
- **init_sqlite.php approval_cc_log 尾逗号（P1）**：建表语句末列 `created_at` 后多余逗号，SQLite 报 syntax error 导致全新安装缺该表（审批流级抄送不可用）；删除尾逗号（init_mysql.php / init.sql 无此问题，仅 SQLite 镜像）
- **密码口径统一（P1）**：三份 init 脚本（init_sqlite.php / init_mysql.php / init.sql）admin 口令统一，与 seed_demo.php 演示口令对齐（此前 init 与 seed 两条部署路径互相矛盾，按提示登录必失败）；演示用户保持统一口令；force_reset 语义不变（init 生产首登强制改密=1 / seed 演示开箱即登=0）。注：后续版本（v2.47.1）已改为随机生成+打印，不再使用固定口令。
- **移动登录页去除 admin 提示**：`app/view/mobile/login.php` 移除「默认账号」提示行及其专用样式（生产级交付包不暴露管理员账号）；PC 端登录页本就无该提示，未动

### 回款/付款方式与里程碑入口下拉化（2026-08-08）
- **移动端登记回款（`app/view/mobile/finance.php`）**：付款方式、里程碑由文本输入改为字典下拉（`dict_options('payment_method'/'payment_milestone')`，含「- 请选择 -」空占位），提交校验「请选择付款方式」；「复制自上期」回填时字典外历史值动态补选项
- **移动端确认收款（`app/view/mobile/finance.php` + `app/view/mobile/contract_detail.php`）**：收款方式改下拉 + 必选校验「请选择收款方式」，移除默认「银行转账」赋值
- **PC 合同详情（`app/view/contract/detail.php`）**：「添加回款/付款」弹窗付款方式必选下拉（`*` 标记）+ 里程碑下拉；`addPayment` 校验「请选择付款方式」；「确认回款/付款」收款方式下拉 + 校验「请选择<?=$__payWord?>方式」，移除默认赋值
- **PC 财务中心（`app/view/finance/index.php`）**：确认收款弹窗「收款方式」改下拉 + 校验「请选择收款方式」，移除默认赋值
- **验证**：浏览器端到端实测移动端回款页面——登录 → `/m/finance` 登记回款弹层付款方式（银行转账/现金/支票/支付宝/微信支付）与里程碑（预付款/中期款/尾款/质保金）均为 SELECT 且选项齐全、确认收款弹层收款方式同为下拉；空提交分别触发「请选择付款方式」「请选择收款方式」；正向选择 BANK + 预付款后校验通过进入提交；4 个改动视图 php -l 全绿

### 多维度多角色全量审查修复（2026-08-08，四维并行 + 6 角色浏览器实测）
- **前端 P0（列表页崩溃）**：`public/static/js/customer.js` 顶层 `load(1)` 无 DCL 防护——脚本在 body 中先于 app.js 加载时 `$ajax` 未定义，ReferenceError 致客户列表「加载中…」永久转圈、漏斗筛选绑定丢失；补 DCL 后浏览器实测列表正常渲染 12 条
- **业务 P0（公海误释放）**：`CustomerLogic::autoReleaseStale` 原按全部认领记录过滤，认领→释放→重新认领的旧记录会误判「刚重新认领的客户超期」；且释放 UPDATE 无 `owner_id` 原子条件、无有效合同拦截、未置 INACTIVE。修复：按客户取最新认领记录（子查询 MAX）判定、释放带 `owner_id` 条件（affected=0 跳过）、复用手动释放的活跃合同校验（审批中/已批准/已签署/执行中拦截）、同步置 `lifecycle_status=INACTIVE`；30 天实测 0 误释放
- **业务 P1-1（审批提交并发）**：`ApprovalSubmitService::submit` 事务内合同行加锁重读（MySQL FOR UPDATE / SQLite 靠事务串行化）+ 锁内检查既有 PENDING 实例，杜绝双实例/双份通知
- **业务 P1-2（回款确认并发）**：`PaymentLogic::confirm` 状态/金额校验移入事务并锁内重读，先提交者调减母记录后，后提交者锁内可见状态/金额变更被拦截，避免重复拆分虚增应收
- **业务 P1-3（发票审批详情 404）**：`ApprovalQueryService` 四个列表/详情方法 contract 联表改 LEFT JOIN + 关联 `contract_invoice`（biz_type=invoice 按 target_id），`normalizeRow()` 以发票标题/号码/金额回退展示——独立快捷申请（contract_id=0）的发票审批实例不再被 INNER JOIN 丢弃，钉钉深链可达
- **业务 P1-4（移动报表口径）**：`ReportLogic::getMobileSummary` 经营总额对齐驾驶舱——排除 DRAFT/REJECTED/PENDING_APPROVAL + 排除框架合同（旧口径把草稿合同算入）；实测 ¥2,105,000（旧口径含草稿为 ¥4,451,000）
- **业务 P1-5（税务汇总含作废）**：`FinanceLogic::getTaxSummary` 仅统计 ISSUED + RED（负向冲抵），排除 VOID/驳回/撤回，与 `sumCommitted`「VOID 不占额度」口径一致；实测 2026-07 销项 ¥664,000/税额 ¥37,584.91 与库内 ISSUED 汇总一致
- **前端 P1（PC 合同保存防重）**：`contract.js` 合同表单提交加 `__cfSaving` 锁 + `btnSave` 禁用（双击不再并发建两份合同），补齐与审批/开票/移动表单一致的防连点
- **安全 P1-1（任意文件删除链）**：`AdminLogic::commitConfigImport` 恢复 `resource_library` 时校验 `file_url` 必须以 `/uploads/` 开头（否则丢弃该行）；`ResourceController::delete` 补 realpath 边界比较——此前恶意配置包可注入 `../../config/.env` 路径配合删除接口删 public 外任意文件
- **安全 P1-2（角色越权扩权）**：`AdminController::saveRole` 仅超管可保存 ALL/CUSTOM 数据范围，非超管提交自动降级 SELF，防止被授予 system:role 的非超管自建全量角色
- **架构 P1-3（HTTP 异常 JSON 分拣）**：`ExceptionHandle` 对 403/404 等 HttpException 在 JSON/AJAX 请求下返回 `{code,msg}`，与 `BaseController::deny()` 分拣行为对齐（前端 `$ajax` 打到不存在路由不再收到 HTML 错误页）
- **安全 P2**：`safe_redirect_url` 拒绝反斜杠变体（`/\evil.com`）；合同金额上限 9.99 亿（防浮点溢出失真）
- **架构/前端 P2**：`markRead/markAllRead` 失效 `badge_remind_` 角标缓存（红点立即刷新）；`AuthLogic::appendDataScope` 无会话兜底 `where('id',0)` 改 `where('1=0')`（联表查询下裸 id 歧义）；contract/create.php 原生 alert 死分支清零
- **验证**：6 角色浏览器实测（admin 全量/manager01 DEPT 收敛+无系统管理+越权 403/employee01 SELF 收敛+payment:view 财务中心数据收敛/tmp_gm 移动经营看板 3 部门+越权 403/finance01 ALL 确认收款下拉/ccnoperm 基础权限装载）；版本三处一致 v2.41.0；改动 15 文件 php -l 全绿；测试用户/临时脚本/误释放数据已恢复清理

### 遗留 P2 修复批次（2026-08-08，多维度审查收尾）
- **P2-10【M-A1】控制器直查清零（分层）**：AdminController 20 处、FormBuilderController 12 处 Db 直查下沉 AdminLogic（表单字段/审批流/配置导入/saveRole/saveUser/saveFlow 编码逻辑），PaymentController::copyPrev 下沉 PaymentLogic——控制器回归「薄壳 + 委托」分层铁律
- **P2-11【M-A2】系统管理 N+1 消除**：用户列表/角色编辑改用 AdminLogic 批量预载映射（getUserRoleIdsMap/getRolePermIdsMap/getRoleDeptIdsMap 单次 whereIn 聚合），单值方法无引用后删除
- **P2-12【S-A3/S-A4/S-A5/S-A6】安全加固**：GET /logout 改 POST（route/app.php + layout/header.php + app.js doLogout）；public/uploads/.htaccess 加 php_flag engine off + 可执行扩展拒绝 + Options -Indexes（nginx 侧 DEPLOY.md 补 deny all）；JWT 通道新增会话 user_id 与 payload user_id 比对（防登录态归属串号）；RbacService 用户关键词 LIKE 通配符转义（ESCAPE '\'）
- **P2-13【M-A3】移动端消息中心收敛**：删除 mobile/notifications.php 死代码页（Mobile::notifications 已重定向 /m/remind?tab=notif，复用公共实现）
- **P2-14【M-A4】XLSX 流式导出**：XlsxHelper::buildTempFile/exportFrom 数据行签名扩为 `iterable|callable`——回调式生产者（接收 sink 回调逐行喂入，与 ContractLogic::eachExportRow 的 chunk 回调同构，超大导出内存恒定）；ContractController::exportXlsx 改用回调式生产者，修复导出 500（原实现把 yield 写在 eachExportRow 内部回调里导致 $gen() 返回 null）；CSV 降级路径同步支持回调式；浏览器实测 200 + 表头 + 36 行数据
- **P2-15【M-A5】视图注入**：contract/detail.php 移除第 7 行 timeline 直查，改由 ContractController::detail() 末尾 View::assign('timeline', ContractTimelineService::getTimeline((int)$id))
- **P2-16【M-A2】回收站 N+1 消除**：RecycleBinLogic::listDeleted 批量调用各实体 deleteBlockersMap（单次 whereIn + GROUP BY 聚合返回 [id => 阻塞提示]），单值 deleteBlockers 委托 map 方法
- **P2-17【F-A1/F-A2】前端防护**：admin/pickers.js 顶层 5 处绑定改判空防护；attachments.js 移除无效 toastId 与空 .finally 块；contract/create.php 移除顶层 showStep(1) 双重调用
- **清理**：database/ 下删除 9 个遗留迁移/校验脚本（migration_resource_content.sql / migrate_v217.php / migrate_permissions.php / migrate_cc_independent.php / repair_rbac.sql / create_test_user.php / verify_v217.sh / verify_v218.sh / verify_v218_p28.sh）
- **验证**：改动 18 文件 php -l 全绿 + 4 JS node --check 全绿；浏览器实测——POST logout 生效（登出后 /login 显示登录页）/ GET /logout 404（不再登出）/ 用户列表含角色正常渲染 / 合同详情 timeline 正常 / 回收站阻塞项批量显示 / /m/notifications 重定向待办中心 / XLSX 导出 200（本机无 ZipArchive 自动降级 CSV 合规）；运行库数据完好（未执行破坏性操作）

## v2.40.8（2026-08-07）— 移动工作台经营看板空态渲染修复（PATCH）

### 根因
- 经营看板卡片渲染条件为「有权限且有数据」（`!empty($dept_title) && (!empty($dept_overview) || !empty($dept_members))`），而 `ReportLogic::deptSummary` 仅统计「生效状态 + dept_id>0 + 交易属性」的合同——全新部署/暂无生效合同时返回空数组，卡片整体不渲染
- 后果：即使总经理（gm）角色权限配置正确（权限码 dashboard:company 已绑定），全新部署/无生效合同数据的部署环境下移动工作台也看不到经营看板入口，误以为配置失败

### 修复（app/view/mobile/index.php）
- 渲染条件由「有权限且有数据」放宽为「有权限」（`!empty($dept_title)`）即渲染卡片外壳
- 数据为空时显示空态提示「暂无生效合同数据」，替代卡片整体消失
- 卡片头部「N 个部门」徽章仅在 `$deptCnt > 0` 时显示，避免空态下显示「0 个部门」误导

### 验证
- 浏览器端到端两场景：有数据（演示库 3 个部门排名正常显示）+ 无数据（dept_id 全置 0 模拟全新部署，卡片外壳 + 空态提示正常渲染）
- php -l 全绿；演示库已恢复原状，临时脚本/备份已清理；版本三处一致 v2.40.8

## v2.40.7（2026-08-07）— 字典增删安全性保护 + 字典项停用/启用 + 项目状态白名单去字典化（PATCH）

### 字典删除保护（用户决策：方案1 实施）
- **8 个系统枚举字典只读保护**：合同状态/回款状态/发票状态/回款里程碑/客户生命周期/项目状态/数据范围/合同分类——`AdminLogic::SYSTEM_DICT_KEYS` 常量定义保护清单，`saveConfig` 在 `__DELETE_ITEM__`（删项）与 `__DELETE_DICT__`（整删）两处拦截返回「系统枚举字典项/字典不可删除」；`getDicts()` 输出 `system` 标记，视图据此隐藏字典项删除按钮
- **保护粒度**：禁删项、禁整删；放行修改显示名称、放行新增自定义项——不影响业务数据型字典（客户来源/供应商类型/付款方式/发票类型/税率/行业）自由增删

### 字典项停用/启用（用户决策：本次交付加入）
- **需求**：系统枚举字典项不可删，但部分项（如合同分类「劳动合同」）需从选项下拉隐藏
- **机制**：新增 `dict_disabled_{name}` 配置行存停用 KEY 集合；common.php 新增 `dict_disabled()` / `dict_enabled()` / `dict_options()`（选项下拉=启用项，编辑场景当前值被停用自动补回）；`AdminLogic::saveConfig` 新增 `__TOGGLE_ITEM__` 分支，`getDicts()` 附带 disabled 状态，缓存清理统一覆盖停用集合
- **停用语义**：仅影响「新建/编辑选项下拉」（合同表单分类、回款付款方式/里程碑、供应商类型、项目状态下拉已接入）；浏览/筛选/统计与历史 label 解析（`dict()` 全量）不受影响，历史数据照常显示
- **UI**：字典页每个项显示「停用/启用」切换按钮（系统字典替代删除位）；**停用项收进「已停用 N 项」折叠区，主列表仅展示启用项保持干净**，折叠区可展开查看/恢复；停用/启用切换成功后**局部 DOM 更新，不整页刷新、不折叠当前字典**

### 项目状态白名单去字典化（用户决策：方案3 实施）
- `ProjectController::save` 项目状态校验由 `array_keys(dict('project_status'))` 改为代码常量枚举 `['ACTIVE','DONE','ARCHIVED']`——此前字典任意增项会放行未识别状态入库（列表/统计不认识），字典现仅用于显示 label

### 验证
- 7 个改动文件 php -l 全绿；浏览器端到端：停用「劳动合同」后合同表单分类下拉消失、字典页灰线+启用按钮、`dict()` 历史 label 仍显示「劳动合同」、`dict_options` 编辑补回、启用后下拉恢复 7 项；测试数据与临时脚本已清理；版本三处一致 v2.40.7

## v2.40.6（2026-08-07）— 审查问题全量修复：无障碍 + 前端交互 + 数据/安全/性能/架构（PATCH）

### 无障碍（P2-06 / P2-07 / P2-08）
- **label for/id 关联**：全站 27 个视图文件 + 4 个 JS 文件（app.js 动态弹窗、flow-editor.js、form-builder.js、pickers.js 包裹式除外）补齐 `<label for>` 与控件 `id`——PC 端 124 处、移动端 54 处、动态模板 16 处；新增 id 均经查重不与既有 JS 引用冲突
- **icon-only 按钮 aria-label**：视图与 JS 动态模板共 50 余处补齐（编辑/删除/查看/返回/上移/下移/下载等语义）；移动端全部 `.back` 返回链接补 aria-label="返回"
- **整行点击键盘可达**：contract.js 桌面列表行、dashboard 三处列表行、contract/detail.php 子合同行加 `role="link" tabindex="0"` + Enter/Space 触发跳转（行内 input/select 聚焦时按键不劫持）
- **viewport 缩放**：PC/移动七处移除 `maximum-scale=1, user-scalable=no`（WCAG 1.4.4）

### 前端交互
- 移动端「选择合同」（finance.php / invoice_apply.php）：mPrompt 输序号改为**搜索建议列表直选**（复用 `.m-party-suggest` 模式，点击即选、点击外部收起、弹窗打开重置）——补充观察 4
- supplier/index.php 关键词 `oninput` 每击键整页刷新 → `onchange` + Enter 提交（防抖）——补充观察 3
- admin/index.php `syncDingTalk(btn)` 显式传参，消除全局 event 依赖——补充观察 6
- contract/create.php 参考资料弹窗 `r.file_name.split('.')` 判空防 TypeError——补充观察 5

### 数据一致性（P2）
- PaymentLogic::revoke 增加子记录非 PENDING 校验（已确认回款不可撤销，抛 RuntimeException 回显）
- ContractController::statusTransition 对终态合同（COMPLETED/TERMINATED/EXPIRED/ARCHIVED）拦截有 OVERDUE 未结回款（用户决策：仅拦截逾期）
- CustomerLogic::releaseToPool 拦截存在有效合同（PENDING_APPROVAL/APPROVED/SIGNED/EXECUTING）的客户释放公海（用户决策：拦截有效合同）
- ReportLogic pending/uncollected 统计改状态直统计，消除负数/负回款口径（P2-12）
- 日志级别按 APP_DEBUG 收敛：生产 error/warning、本地全量（S-07）；NotificationController::checkTarget 清理 7 处 info 日志

### 安全（P1/S）
- AuthController 登录失败文件缓存计数 + 900s 锁窗（S-04）
- AuthLogic JWT 弱密钥黑名单（含仓库固定值）强制回退运行时随机密钥（S-05）
- RemindService remind/check 权限守卫 + 60s 全局节流（S-09/S-12）
- CSRF 白名单前缀精确匹配防 'sso-login-evil' 绕过（S-03）；Auth::handle JWT 校验前先验证 Bearer（S-06/S-11）
- 发票/合同表单 pcConfirm/pcPrompt 替换残留原生 confirm/prompt（P1-02/P1-03，驳回意见口径对齐选填）

### 性能与缓存（P1/P2）
- PartyLogic 相对方列表安全上限 200 条 + truncated 提示（PC/mobile），searchParty 数据范围收敛（P1 性能）
- RemindService::scanAlerts 四类扫描 limit(100)、PartyLogic::options limit(500)（P2-2/P2-3）
- AdminLogic 新增 clearDictCache 修复未定义函数调用，字典增删改统一清 dict_ 前缀缓存（P1-5）
- 审批 action / 回款确认后主动失效 badge_approval_/badge_remind_ 角标缓存（P2-10）

### 架构治理（P1-1，用户决策：低风险下沉）
- 控制器 Db 直查下沉 Logic：ContractLogic（findEditable/findDuplicate/batchLoad/getIdsByDeptIds）、CustomerLogic（findActive）、InvoiceLogic（pageMyList/pagePendingApproval/pagePendingIssue）、ProjectLogic（completeSalesContracts/sumPendingTailAmount）、InternalNotify（pageList/findOwn）、UserLogic（getDeptName/countPendingHandover 等）；MobileController 清零 Db 直查

### 验证
- 162 个 PHP 文件 php -l 全绿；修改 JS 文件 node --check 全绿
- 浏览器端到端：登录、仪表盘（合同总额/部门经营）、合同列表（桌面行键盘属性/编辑按钮 aria-label）、合同详情、审批流程设计器（flow-editor/form-builder label for）、移动端 finance 选合同建议列表（输入→建议渲染→点击选中收起）全部通过
- 无新增测试数据残留；临时脚本已清理；admin 测试账号密码已复位

## v2.40.5（2026-08-07）— 全量审查回归修复（周期统计 P1 BUG + 收支口径统一 + 存量库权限对齐）（PATCH）

### 修复：周期统计漏计「周期首日生效」记录（P1）
- `app/common.php::period_range()`：start 由带时分秒（`Y-m-01 00:00:00`）改为纯日期（`Y-m-01`）——合同 `effective_date`/回款 `planned_date` 存储为纯日期字符串，字符串比较 `'2026-08-01' < '2026-08-01 00:00:00'` 会把周期首日生效的记录全部漏计（曾致仪表盘「本月合同总额恒为 ¥0」）；end 保留 `23:59:59` 避免 created_at 等带时间字段的月末记录漏计
- 影响面：`ReportLogic::dashboardSummary` / `ReportLogic::getMobileSummaryByPeriod` / `FinanceLogic::getSummaryByPeriod` 三个调用方，浏览器回归本月 ¥716,000 / 本季 / 本年均正确

### 修复：收支方向/经营聚合未排除未生效状态（口径统一 P1-3）
- 以下统计点此前遗漏「排除草稿/驳回/审批中」，草稿合同金额被计入经营数据：
  - `ReportLogic::monthlyReport` 收支方向（本月新增合同）——草稿采购合同 ¥350,000 被计入应付
  - `ReportLogic::getMobileSummaryByPeriod` 本期新增合同
  - `ReportLogic::deptSummary` 按部门经营（合同额/合同数）
  - `FinanceLogic::getSummary` 财务中心收支概览（销售应收 25 份→8 份）
- 与 `dashboardSummary` / `deptMembers` / `ProjectLogic` / `CustomerLogic` / `PartyLogic` / `getSummaryByPeriod` 既有 P1-3 口径一致；浏览器回归月报/驾驶舱/财务中心全部正确

### 修复：审批转交无效目标只报「操作失败」
- `ApprovalActionService` 外层 `catch (\Throwable)` 吞掉业务 `RuntimeException`，转交无效用户回显笼统「操作失败」；改为 `RuntimeException` 上抛 + `ApprovalController::action()` 捕获回显具体原因（如「转交目标用户无效」），事务已回滚、原记录保持 PENDING

### 数据：存量库权限对齐（与三份 init 脚本一致）
- admin +invoice:apply（40→41）、finance +dashboard:company（14→15）、user +dashboard:stats（18→19）、gm 移除 5 项系统管理权限（41→36，system:user/role/config、dingtalk:sync、system:handover）
- 三份初始化脚本（init.sql / init_mysql.php / init_sqlite.php）角色权限映射逐项核对一致（admin=41 / manager=32 / legal=11 / finance=15 / user=19 / gm=36）

### 验证
- app 目录 162 个 PHP 文件 php -l 全绿；浏览器端到端回归：仪表盘（合同总额/收支方向/部门经营）、经营月报、财务中心、发票申请（4 处 pcConfirm）、字典设置折叠面板（14 字典含回款里程碑）、审批列表/详情（转交/同意/驳回弹窗）全部通过；测试数据已清理、无残留

## v2.40.4（2026-08-07）— 经营看板权限码细分（dashboard:company/dept）+ 部门经营门控（PATCH）

### 权限：经营看板三权限码独立控制
- 新增 `dashboard:company`（全公司经营）与 `dashboard:dept`（部门经营）权限码，与既有 `dashboard:stats`（我的业绩）并列；PC 仪表盘「按部门经营」卡片与移动端「本部门经营」卡片统一由权限码控制，消除部门经理 PC 端越权看全公司排名
- 三份 init 脚本角色绑定：dashboard:company → admin/gm，dashboard:dept → admin/gm/manager；finance 增补 dashboard:company（财务中心概览）
- 迁移：`migration_v2.40.4_dashboard_perms.sql`

## v2.40.3（2026-08-07）— 经营看板卡片权限配置化（PATCH）

### 权限：工作台卡片按角色配置
- 移动工作台「经营看板」卡片显示由 `dashboard:stats` 权限码控制（角色权限可勾选；is_admin 自动拥有全部），此前依赖 is_admin 近似判定

## v2.40.2（2026-08-07）— 新增总经理角色（gm）+ 权限矩阵评审（PATCH）

### 角色：总经理（gm）
- 新增 role id=12「总经理」（data_scope=ALL 看全公司经营概览），管理层业务全量权限（36 项，不含系统管理：system:user/role/config、dingtalk:sync、system:handover）
- 迁移：`migration_v2.40.2_gm_role.sql`

### 权限矩阵评审（对齐产品预期）
- admin +invoice:apply（41 项）；finance +dashboard:company（15 项）；gm 移除 5 项系统管理权限（41→36）；user 保持 19 项
- 三份 init 脚本（init.sql / init_mysql.php / init_sqlite.php）角色绑定显式化对齐，修复死绑定问题

## v2.40.1（2026-08-06）— 审批详情 UI 优化 + 权限一致性 + 开票资料字段拆分 + 移动工作台权限门控（PATCH）

### 优化：审批详情页 UI（PC + 移动端，对齐同一原型）
- **移动端**（`app/view/mobile/approval_detail.php`）：合同摘要卡新增「进度 x/y」徽章；审批进度改为按流程定义渲染完整时间线——已完成节点（绿点+「已同意」）、当前节点（蓝点高亮+「审批中」）、未来节点（灰点+「待审批」）占位、驳回节点红色高亮、抄送知会条目，每节点显示审批人/状态标签/意见/时间
- **PC 端**（`app/view/approval/detail.php`）：信息卡新增「进度 x/y」徽章；步骤条每节点新增彩色状态标签（已同意=绿/审批中=蓝/待审批=灰/已驳回=红/已转交=黄），与节点圆状态一致；修复审批记录行状态圆点与文本重叠（`.p-2` 的 `!important` 覆盖行内 `padding-left`，改为行内完整 padding）
- 两端状态语义统一，浏览器实测：5042（进度 0/2、审批中/待审批）、5043（进度 1/2、已同意/审批中）均与原型一致

### 优化：开票资料开户行/账号字段拆分
- `ResourceLogic::$INVOICE_FIELDS` 拆为独立「开户行」「账号」两字段（原合并），顺序为 单位名称/税号 → 开户行/账号 → 地址/电话；PC 上传弹窗与移动端开票资料表单字段顺序、前端标签映射（resource.js / resource_form.php）同步更新，浏览器实测两端顺序一致

### 修复：移动端待办中心「全部已读」黑边
- `app/view/mobile/reminders.php`：Bootstrap `btn btn-sm btn-link` 在移动端未加载 Bootstrap CSS 时显示浏览器默认黑边框，改为 `<a>` 标签（品牌蓝文字、无边框），实测 `border:0` 通过

### 修复：无审批权限用户不再可见审批操作
- `ApprovalController::detail()` 的 `can_act` 叠加 `approval:approve` 权限判断：即使某用户是历史审批记录的节点审批人（角色调整/数据遗留导致），无审批权限时详情页不再渲染「同意/驳回」按钮，与后端 `action()` 的 `requirePermission('approval:approve')` 保持一致，消除「看得到点不了」的误导

### 优化：移动工作台快捷操作权限门控 + 扩展入口
- 快捷操作区图标按权限显示：审批（approval 相关）/登记回款（payment:create）/申请开票（invoice:apply）/资料库（library:view）/客户池（customer:view）/报表（payment:view 或 invoice:view），管理员全量；实测 employee01 与 finance01 权限组合显示各自 5 项符合预期
- 新增 FAB 新建菜单按 contract:create / customer:create / supplier:create 权限门控
- 「我的业绩」图形化卡片（can_my_stats = 管理员或 customer:view/contract:view）：2×2 网格 + 环比徽章 + 金额单位换算，整卡跳转 /m/my-stats

### 修复：归属人筛选下拉按数据范围收敛
- `ContractController::index()` 与 `MobileController::contracts()` 归属人下拉按可见性谓词收敛：ALL=全部用户 / DEPT=本部门用户 / SELF=仅本人，与合同列表数据范围一致，避免下拉出现范围外用户误导筛选（筛选逻辑本身无越权，仅收敛可选项）

### 优化：移动端合同列表搜索/筛选/标签
- 修复「高级筛选点选任意选项即关闭弹窗」：遮罩点击监听改为仅遮罩本体触发关闭（`e.target === mask`），sheet 内 select/chips 点击不再冒泡误关
- 筛选入口由纯漏斗图标改为「图标+文字」按钮，有边框，选中态可辨识，保留已选条件角标
- 顶部状态标签收敛为高频 4 项（全部/草稿/待审批/执行中）；已驳回/已终止/已到期移入高级筛选抽屉新增「合同状态」下拉，与顶部 chips 共用 `filter.status`（抽屉未选时保留顶部高频选择）
- 新增已选条件标签行（方案 A）：类别/方向/状态/性质/类型/签约主体/归属人/相对方/金额等条件以可删除标签展示（`状态:已驳回 ✕`），点 ✕ 单条移除并即时刷新；高频状态由 chips 表达不重复展示；标签文字单行省略（max-width:45vw + ellipsis），长名称（相对方等）不撑爆标签行
- 搜索框新增「清除」按钮，输入时联动显示
- 归属人/相对方改为搜索式选择：输入关键词出现下拉候选（归属人本地过滤数据范围内用户；相对方复用 `/ajax/party/search` 客户+供应商候选），点选回填；未选择默认全部（清空输入视为未选择，`ownerPick` 与输入框值双向校验）

### 数据修正：历史审批卡单重新归属
- 10 条 PENDING「部门经理/经理审批」节点审批人由普通用户（sales02，无 `approval:approve` 权限）修正为当前 manager 角色用户（张经理），消除卡单；已处理记录不受影响

### 验证
- manager01 待我审批 0→10，审批详情页可正常操作；sales02 待审批 10→0，详情页无操作按钮；归属人下拉：manager01 仅商务部 4 人（PC 与移动端一致）、admin 仍为全部 7 人；admin 非节点审批人时详情页无按钮（行为正确）
- 移动端：点选抽屉内方向/状态下拉弹窗保持打开、遮罩点击可关闭；顶部 chips「待审批」筛选 12→2 条、抽屉「已驳回」筛选 1 条并生成标签行/角标；标签单删、关键词清除、重置全部通过浏览器实测（php -l 通过、无新增 JS 错误）

## v2.40.0（2026-08-06）— 业绩看板 + 项目毛利 + 跟进闭环 + 付款闭环 + 项目验收联动 + 客户行业（MINOR）

### 业绩看板：个人自视 + 部门归集（P0-3）
- 移动端「我的业绩」页（`/m/my-stats`）：个人经营数字（客户/合同/成交额/回款）+ 本月/累计双口径 + 环比徽章（升绿降红）；PC 仪表盘新增「按部门经营」卡片（管理员可见，`ReportLogic::deptSummary` 部门聚合 + `personalStats` 个人自视）
- 考虑商务人员分属不同部门、直接比较无意义——采用「个人自视 + 部门归集」双视角，不做个人排名

### 项目毛利（P0-1）
- 项目详情「经营聚合」统计卡 5 卡自适应（交易合同数/销售合同额/项目毛利/应收已收/回款率）；`ProjectLogic::aggregate` 新增 gross_margin / gross_margin_rate（毛利=销售合同额−采购合同额，毛利率按销售额，正绿负红）

### 客户跟进手动录入（P0-2）
- PC/移动端客户详情新增「记录跟进」：方式（电话/拜访/会议/微信）+ 内容 + 下次跟进时间；`CustomerLogic::addActivity` 写入 next_follow_at，列表展示下次跟进时间；新增 `/ajax/customer/add-activity`（权限/公海/类型白名单/长度/时间格式校验）

### 应付付款闭环（P1-4）
- 合同详情付款方向联动（sales→收、purchase→付），新增「添加付款」「确认收款/付款」弹窗；财务中心新增「付款管理」Tab（复用 payment-list 接口按 payment_type 过滤，PAYABLE 走确认付款）

### 收款计划模板（P1-5）
- 回款/付款里程碑改字典下拉（dict_payment_milestone：预付款/中期款/尾款/质保金）；一键模板生成（30/50/20、50/30/20、40/40/20、一次性）批量创建批次（`/ajax/payment/batch-add`：事务写入、合计≤合同余额、上限 10 期、金额四舍五入取整）

### 项目执行进度 + 验收联动（P1-6）
- project 新增 stage（筹备/执行中/验收中/已完结）+ progress（0-100），PC/移动端表单与详情展示进度条；「标记验收完成」（`/ajax/project/accept`）：项目置已完结/进度 100，联动项目下执行中/已通过/历史已签的销售合同置已完成，并提示待收尾款金额

### 客户行业字段 + 漏斗金额维度（P1-7）
- customer 新增 industry（GOV 政府单位/REAL_ESTATE 房地产/FOOD_TOURISM 餐饮旅游/OTHER 其他）+ dict_customer_industry 字典；PC/移动端表单下拉、列表列/卡片标签、详情展示（白名单校验）
- 生命周期漏斗新增金额维度：各阶段客户销售合同额合计（`CustomerLogic::lifecycleFunnel` 返回 amounts，PC/移动端漏斗卡片展示「N 户 · ¥金额」）

### 合同列表草稿快捷筛选（PC）
- 合同列表新增「全部合同 / 草稿 / 我的草稿」快捷筛选 chips（`owner_id=me` 后端自动转当前用户，URL 直访 `/contract?status=DRAFT&owner_id=me` 状态一致）；仪表盘新增「草稿待处理」卡片（数据权限内最新 5 条 + 总数，60s 缓存，行可点击进详情）

### 移动端新建合同优化（附件即合同）
- 字段顺序重构：标题 → 合同概要（简化 3 行，提示"合同具体内容以附件为准"）→ 合同附件（上移首屏）→ 签约方 → 金额与期限（金额/生效/到期移出独立区）→ 更多信息（分类/性质/方向）→ 更多选项（关键词/关联项目/关联框架合同）
- 签约方重构：「我方身份」切换（默认我方=乙方，业务多为服务方）+ 我方行自动带出签约主体（多主体可切换）+ 对方行搜索式输入（聚焦联想客户/供应商，选中回填名称+客户ID+联系人），移除独立「搜索对方」字段
- 必填字段保持现状（标题/双方名称/金额/日期/概要/附件）；分类/方向/性质改「请选择/显式必选」无默认值避免错填；日期不设默认；备注移动端移除（PC 保留）
- 更多选项折叠增强：副标题说明 + 已选徽章实时计数；修复「乙方联系人/电话」重复 label、supplier_id/our_side 重复 id
- **DB**：contract 新增 `party_a_customer_id`（我方=乙方时对方为甲方，客户关联落甲方侧；`migration_v2.40.0_party_a_customer.sql`）

### 验证
- 项目验收联动实测：阶段「筹备」→「已完结」、进度 0→100%、验收按钮隐藏、执行中/已通过合同置已完成、待审批合同不受影响；演示数据已还原
- 客户行业保存/展示实测：新建 GOV 客户 → PC 列表「行业」列 / 详情行 / 移动卡片标签 / 移动详情标签全链路正确；漏斗金额（成交阶段 ¥2,618,000）正确；测试数据已清理
- 移动端新建合同全链路实测：我方=乙方默认、对方（甲方）搜索选中客户回填 `party_a_customer_id=102` 落库、交易/非交易 radio 显式必选、分类/方向「请选择」无默认、编辑态回显一致（身份/radio/分类/方向/客户ID）、PC 端表单零影响；PC 草稿 chips「我的草稿」owner_id=me 过滤生效、仪表盘草稿卡片渲染正常
- php -l 全绿
- **DB**：`migration_v2.40.0_customer_followup.sql`（customer.next_follow_at）、`migration_v2.40.0_payment_milestone.sql`（dict_payment_milestone）、`migration_v2.40.0_project_stage.sql`（project.stage/progress）、`migration_v2.40.0_customer_industry.sql`（customer.industry + dict_customer_industry）、`migration_v2.40.0_party_a_customer.sql`（contract.party_a_customer_id）

## v2.39.0（2026-08-05）— 离职/在职数据交接 + 消息中心优化 + 仪表盘升级（MINOR）

### 离职交接自动化（钉钉同步 → 待交接队列 → 办理移交）
- 钉钉同步检测疑似离职员工自动标记 `user.need_handover=1`，进入「待交接」队列（`AdminLogic::getHandoverUsers` 供 PC/移动端共用，含客户/合同/待审批计数）
- 管理员/有权账号办理数据移交：客户/合同/待审批批量转移给指定账号，可勾选「交接完成后禁用」进入回收站；「未离职」仅清除标记；交接/恢复/清除后标记统一清零
- 新增独立权限码 `system:handover`（permission id=40，admin 角色绑定），守卫 `requireAnyPermission(['system:user','system:handover'])`；迁移 `migration_v2.38.25_user_need_handover.sql` / `migration_v2.38.26_permission_system_handover.sql`，三份初始化脚本 + repair_rbac.sql 同步种子

### 在职员工间交接（PC + 移动端）
- PC 用户管理操作列新增「数据交接」按钮（复用 #handoverModal，默认不禁用）；移动端「在职交接」Tab（`/m/handover` 双 Tab）
- 在职员工间批量移交（接收人可跨部门），双方保持在职；与离职交接共用 `AdminLogic::handoverUser` 公共逻辑（含待审批 approval_record PENDING 转交 + 去重）

### 交接弹窗选择器（不能用下拉菜单）
- 接收人由 `<select>` 下拉改为「搜索框 + radio 单选选择器」：弹窗默认不列出全部用户（提示「输入姓名搜索接收人」），输入关键词后渲染匹配项（显示部门），复用 .m-user-list/.m-user-opt 组件
- sheet 显隐统一 `.show` 类模式（CSS 默认 opacity:0 + pointer-events:none，只改 display 会"弹出但不可见不可点"）

### 消息中心优化
- PC/移动端数据流统一（复用 /ajax/notification/*）；移动工作台铃铛入口 + `.palace-dot` 红点计数；点击即标记已读并刷新角标（标记已读前置到点击开始）
- 移动端「全部已读」按钮、check-target 校验被删审批目标并友好提示、read/unread 卡片化视觉区分（read 状态由页面重载自然呈现）

### 仪表盘升级
- 趋势图升级 Chart.js 4.4.1（hover tooltip / 动画 / Y 轴自适应），状态栏上移卡片化，月/季/年/累计周期筛选左对齐，切换周期加载中遮罩（spinner + 「加载中…」）
- 「最近合同」固定返回最新 8 条（ReportLogic 去周期过滤）；近期回款行可点击跳合同详情；.stat-icon 尺寸/圆角抽 CSS 变量

### 响应式修复（钉钉 PC 内嵌场景）
- 800px 宽度无横向滚动/无内容遮挡，600px 自动切移动布局；body overflow-x:hidden + .main-content min-width:0；768-1199px 侧栏 180px；多列表格 .table-responsive；canvas 容器 overflow:hidden

### 附件 MIME 白名单统一
- ContractController / PreviewController / 前端 create.php / contract_form.php 四端一致：仅 PDF/Word/JPG/PNG，拒绝 TXT/XLS；PreviewController 改用 `force(false)` 实现浏览器内联预览（修复 isInline() 500）

### 权限审计 + P3 重构
- 侧边栏入口权限与后端守卫对齐（system:user 一致）；全站权限码扫描确认无遗漏
- `ContractLogic::getList` 拆 applyListFilters（12 项筛选）+ countChildrenPerFramework（GROUP BY 消除 N+1）；`RemindService::dispatch` 拆 4 个扫描方法（合同到期/已到期/逾期回款/临近回款）+ pushToUsers（钉钉推送重试 + Log::warning）

### 验证
- 离职交接全流程实测：204（4客户5合同）→ 203 全部转移、204 禁用（status=2）、need_handover 清零、审计日志完整（handover disable_from:true）
- 在职交接全流程实测：204→203 不禁用（双方在职）、反向 203→204 数据还原（与初始一致）
- 选择器交互实测：弹窗初始不列用户、输入关键词渲染匹配、radio 单选、确认文案正确；php -l 全绿

## v2.38.26（2026-08-04）— 审批/发票流程编辑器全面对齐 + 移动端两处修复（PATCH）

### 审批流程与发票流程合并单一入口 + 发票编辑器弹窗化（v2.38.24-26）
- **合并入口**：PC 侧边栏删除独立「发票流程」菜单，统一为「审批流程」；列表加「类型」列（发票/合同标签），顶部两个新建入口——「新建发票流程」+「新建合同流程」分流打开对应配置
- **发票流程编辑器弹窗化**（参照合同审批编辑器样式）：列表发票行「编辑」与右上角「新建发票流程」均打开弹窗编辑器（左分支画布 + 右流程配置面板）；保留静态 href（dead_entry 门禁入口 + 独立页深层链接回退），`openInvoiceEditor()` 动态标题（无存量流程→「新建发票流程」/有存量→「发票流程配置」）；form-builder.js 暴露 `window.fbFlowHasSaved` 判定存量
- **审批流程拖动排序**（v2.38.24）：approval_flow 加 `sort_order`（同类型内优先级，越小越靠前），列表 HTML5 DnD 同类拖拽排序自动保存；手动排序覆盖金额区间自动选择（提示文案明示）；3 个插入点（saveFlow/saveAllFlows/FormBuilder::saveFlow）新流程追加同类型末尾
- **发票 Step2 流程配置侧边栏**：金额条件从分支卡片内移入右侧「流程配置」面板（每分支独立 amount，仅 UI 位置变化，数据模型零改动）；取消 Step1 表单设计环节，开票内容选项作为固定配置项入侧边栏
- **条件分支系列收敛**：条件字段固定为「开票主体」（去字段下拉选框）、移除激活条件配置（发票+合同双侧）、抄送角色统一「下拉多选 + 已选 chips」、分支头部布局调整（删除按钮右移、主体独立一行）、含税价税拆分移到含税金额行下方、开票主体下拉拉长、去除发起人连接线保留间距、删除两段说明文字
- 修复：发票条件分支下拉打不开（fbSelectGroup 全量重建 DOM → 仅切高亮+刷新配置面板）；视图缺失 `__formBuilder` 注入致角色/公司下拉空（控制器补注入）；saveAllFlows Db 构建器 where 累积致 update 0 行
- **验证**：jsdom 实跑（弹窗打开/下拉/chips/保存/标题区分）+ curl HTML 断言 + 6 门禁全绿（PHPUnit 43/43）；测试数据从备份恢复
- **DB**：approval_flow.sort_order（新迁移 `migration_v2.38.24_flow_sort_order.sql`，deploy.sh 自动扫描执行）

### 移动端财务统计「申请开票」无法点击修复（v2.38.26）
- **根因**：`.m-sheet-mask` CSS 默认 `opacity:0 + pointer-events:none`，全站靠 `.show` 类控制显隐；财务页发票申请/开票两弹层用 `display:none/flex` 控制 → 打开后仍透明不可点
- **修复**（finance.php）：两个弹层对齐全站 `.show` 类模式——HTML 去内联 display:none，6 处 JS 改 classList.add/remove('show')（登记回款弹层本就正确）
- **验证**：jsdom 实跑（点击「申请」→ 弹层获 .show、零 JS 错误）+ 三门禁绿

### 移动端首页快捷操作单行 4 图标横滑（v2.38.26）
- 原 5 项时 flex-wrap 3+2 两行难看 → quick-grid 改 nowrap+overflow-x:auto+隐藏滚动条，每项固定 25% 宽
- 溢出提示：JS 检测 scrollWidth>clientWidth → `#quick.has-more` → 标题右侧「左右滑动」小字 + 右侧白色渐隐遮罩（4 项内不显示）
- **验证**：jsdom 双场景（5 项→提示显示 / 4 项→隐藏）+ 三门禁绿

### 发票流程 Step1 复用申请开票表单 + 侧边栏改名（用户 2026-08-04）

- **背景**：发票表单 Step1 原为字段配置表格（过度设计），用户要求直接复用发票申请页的申请开票表单；侧边栏「发票表单」改「发票流程」，删除标题与冗余按钮
- **DB**：`invoice_form_field` 的 content_desc 由 textarea 改 select（默认 5 选项：软件开发服务费/咨询服务费/运维服务费/硬件销售费/其他，值=中文显示名——审批/通知等展示逻辑零改动）；remark（申请说明）sort_order 移至表单最末。四份脚本同步：init.sql / init_mysql.php / init_sqlite.php / migration_v2.38.7_invoice_approval.sql + 运行时库；`InvoiceFormConfig::presetFields()` 兜底同步
- **Step1 改造**（`form_builder/index.php` + `form-builder.js` + `FormBuilderController`）：
  - Step1 服务端渲染 `InvoiceFormConfig::pcRender` 申请表单（与 /invoice-apply 同源）；仅「开票内容选项」可编辑——chips 面板 + 编辑弹窗，保存走新增轻量接口 `POST /ajax/form-builder/save-content-options`（只更新 content_desc 的 field_options，不碰其它字段），保存后 chips/表单内下拉即时刷新
  - 删除 h4「发票申请表单设计器」标题、「预览表单/保存当前步骤」按钮；Step2 面板内新增「保存审批设置」按钮（`window.fbSaveFlow` 暴露）；清理联动配置 UI/预览弹窗等死代码（保留 linkFieldOptions 供 Step2 条件字段、linkRules 加载供申请页引擎）
  - 合同详情页 `showAddInvoice` 开票内容 textarea 引用改 select（合同标题命中选项则预选）
- **验证**：浏览器端到端（标题/无 h4/表单 8 字段顺序=申请说明最末/5 chips/编辑保存→DB→下拉即时刷新/Step2 画布+保存按钮/save-flow 闭环/合同详情下拉）全过；PC+移动申请页 content_desc 下拉渲染正常；5 门禁全绿；PHPUnit 43/43；测试数据已还原

### 发票表单 Step1 表格化（用户 2026-08-04：Step1 现有的设计是过度设计，恢复原有的发票申请表，但融入当前新的样式）

- **背景**：Step1 原为钉钉式三栏画布（控件库/画布/属性面板），用户认为过度设计，要求恢复原有发票申请表形态（表格化配置），保留新样式
- **改造**（`form_builder/index.php` Step1 + `form-builder.js`）：
  - 三栏画布 → 「申请表单字段」表格（排序/启用/标签/类型/必填/选项/属性/操作 8 列，行内编辑）
  - 新增「新增自定义字段」按钮（pcPrompt）、选项编辑弹窗 `#fbOptModal`；保留联动规则/预览/保存/Step2 全部
  - 删除 renderPool/renderAttr/renderPreviewOnly/fbDeleteSelected 等旧画布代码；数据格式与 saveForm payload 未动（后端零改动）
- **验证**：浏览器端到端（表格 9 行 8 列、行内编辑/类型切换出选项按钮/选项弹窗/新增/移动/保存跳 Step2/申请页渲染）全过；43/43 PHPUnit；4 门禁全绿

### 合同审批流程画布对齐发票 Step2（用户 2026-08-04：合同审批流程的流程画布和发票里的 step2 不一样，少了流程分支，添加节点的按钮位置也不一样，请按照发票 step2 里的画布来设计）

- **背景**：原合同审批流编辑器（admin/flow）为单流程纵向链 + 右侧配置面板，与发票 Step2「分支区并列卡片」形态不一致
- **改造**（`app/view/admin/index.php` flow tab + `public/static/js/admin/flow-editor.js` v4 + `AdminController` + 路由）：
  - HTML：flowModal 改为「发起人 → 分支区（每条合同流程一张并列卡片）→ 添加条件分支 → 结束」；CSS 复用发票 Step2 类（.fb-flow/.fb-branch/.fb-bnode/.fb-chip 等）；列表编辑按钮改 `editFlow()` 无参
  - JS：`flowGroups` 数组驱动（每条=id/name/code/category_list/use_amount/min/max/status/nodes/cc）；分支卡片头部含名称/编码/适用分类 chips/金额条件/状态；节点/抄送/「添加节点」按钮均在卡片内；保存走新接口 `POST /ajax/admin/flow/save-all` 全量重存
  - 后端：`AdminController::saveAllFlows`（本次提交更新/新增，未提交的旧 contract 流停用 status=0；修复 Db 构建器 where 条件累积致 update 0 行）；视图注入 `window.__flowAll`（仅 biz_type=contract，过滤发票流）
- **验证**：浏览器端到端（新建 1 默认分支、编辑 3 条并列、节点增删/分类 chips/金额显隐、save-all 落库+停用逻辑、发票 Step2 不受影响）全过；43/43 PHPUnit；4 门禁全绿；测试数据已从备份恢复

### 审批流程编辑器图形化改造（用户 2026-08-04：将审批流程的审批节点抄送节点部分优化成发票那样的图形化，其余设置项做成侧边的配置项）

- **背景**：原审批流编辑器（admin/flow）为「头部配置 + 纵向节点列表 + 底部抄送」堆叠式弹窗，不直观
- **改造**（`app/view/admin/index.php` flow tab + `public/static/js/admin/flow-editor.js` v3）：
  - flowModal 改左右双栏：左侧图形化画布（发起人 → 节点链 → 抄送 → 结束，CSS ::before 连接线）+ 右侧 300px 配置面板（名称/编码/适用分类/金额/状态/添加节点按钮）
  - `renderFlowCanvasFrame()` 渲染骨架；addNode 节点包 `.flow-cv-node` 插入抄送前；moveNode/removeNode 改操作 `.flow-cv-node`；node-card 内部表单与 getNodesData 保存格式完全未动（后端零改动）
  - 补 `updateNodeData()` 空实现（消除 onchange 遗留 ReferenceError）；弹窗宽度显式覆写 1180px（Bootstrap modal-xxl 在 CDN 未生效）
- **验证**：浏览器端到端（新建/编辑回填/节点增删移动/类型切换选人/抄送弹窗选人/保存落库）全过；4 节点画布滚动正常；43/43 PHPUnit；4 门禁全绿

### 表单设计器 Step2 图形化审批流程（用户 2026-08-04：参考钉钉的审批设计，按条件带出不同审批抄送流程）

- **背景**：发票表单编辑器 Step2「审批与抄送」原为纯表单节点列表（无图形、无连线、无排序）；后端 `matchInvoiceFlow` 已支持按表单字段（如开票公司 our_company_id）等值条件分流，但设计器无法直观表达
- **改造**（`form-builder.js` + `form_builder/index.php` 内联 CSS）：
  - **钉钉式纵向流程图**：每个流程组渲染「发起人 → 竖线箭头连线 → 审批节点卡片（人形图标+类型徽标+审批人+或签/会签）→ 抄送区（黄卡）→ 结束」完整流程
  - 审批节点卡片新增**上移/下移/删除**（`fbNodeMove` 源/目标位置均校验，防越界写坏数组）
  - **条件分支**：默认组（无条件兜底）+ 条件组（选字段=值，如开票主体=某公司），每组独立完整流程图，与后端 `matchInvoiceFlow` 数据模型无缝对接（groups=[{condition,nodes,cc}] 不变）
- **验证**：jsdom 实测——多组图形化渲染（发起人/结束/连线/节点/抄送）、添加节点、上移排序、添加条件分支、Step2 保存 payload（默认组 condition=null + 条件组 our_company_id=1 带节点/抄送/审批人）全部正确；六门禁全绿（PHPUnit 43·89）

### 移动端申请开票独立页（用户 2026-08-04：申请开票需要独立页面，同步 PC 发票表单）

- **背景**：移动端申请开票原为财务页弹窗（/m/finance#apply），用户要求独立页面并复用 PC 发票表单
- **新增 `/m/invoice-apply` 独立页**（`MobileController::invoiceApply()` + `app/view/mobile/invoice_apply.php` + 路由）：
  - **申请开票表单**：与 PC `/invoice-apply` 同源——`InvoiceFormConfig::mobileRender` 渲染（后台「系统设置→发票表单」可配，税率绑定主体 data-rate 联动、价税拆分实时展示）；`form-linkage.js` 通用联动（选客户带出抬头/税号）；提交复用 `POST /ajax/invoice/add`
  - **我的申请列表**：异步加载 `/ajax/invoice/my-list`（与财务页同口径），含状态徽标/撤回（走审批通用 `/ajax/approval/<id>/recall`）/驳回重新提交（`/ajax/invoice/resubmit` 二次确认）
  - 工作台快捷操作「申请开票」改跳独立页（原 `/m/finance#apply` 弹窗保留不影响）
- **验证**：admin 渲染 200（配置化字段/联动/提交/列表齐全）；jsdom 实测列表加载+状态徽标+必填拦截+提交成功；六门禁全绿（PHPUnit 43·89）

### 审批驳回意见改为选填（用户 2026-08-04）

- **背景**：驳回场景意见原为必填（PC/移动端前端拦截 + 后端强制校验），用户要求选填
- **改动（5 处）**：
  - `ApprovalController::action()`：移除 `REJECTED && empty($comment)` 强制校验（保留驳回到节点参数白名单）
  - PC `approval/detail.php`：弹窗 placeholder「驳回意见（必填）」→「选填」+ 提示"意见可留空"；`act()` 移除空意见拦截
  - 移动 `approval_detail.php`：文案改「驳回意见（选填）」；`btnConfirm` 移除 `!c` 拦截
  - 移动 `finance.php`（开票审批驳回）：mPrompt「必填」→「选填」、移除空值拦截
  - `ApprovalActionService`：驳回通知文案意见为空时省略"驳回意见："尾巴
- **验证**：admin 空意见驳回实例 5023 端到端成功（code=0、实例/合同 REJECTED、审计留痕），数据还原；PC 弹窗文案实测「选填/意见可留空」；六门禁全绿（PHPUnit 43·89）

### PC 仪表盘优化 + 通知中心升级（用户反馈 2026-08-03：合同总额/待回款/收支无时间筛选，参考性低）

- **问题**：仪表盘全页无时间维度——合同总额为全生命周期累计、待回款无周期口径、趋势仅合同数量非金额；PC 端角色差异只做半套
- **PC 仪表盘（v2.38.17，三大模块）**：
  - **时间筛选器**：顶部「本月/本季/本年/累计」chips（默认累计=原行为不变），AJAX 局部刷新 KPI+经营/收支+趋势（`/dashboard?period=xx`，缓存 key 含 period）；`ReportLogic::dashboardSummary($user, $period)` 周期过滤——合同按 `effective_date`、回款按 `planned_date/actual_date`（未收按计划日、已收按实收日），'all' 与原版完全一致
  - **KPI 卡按角色裁剪**（与移动端同口径）：管理员（合同总额/待回款/回款率/今日待办）· 审批人（待我审批置顶）· 财务（待回款/已收/回款率）· 普通员工（合同总额/待回款/回款率/今日提醒）；`is_admin/can_approve/can_finance` 注入视图
  - **趋势改金额**：近6月双色柱条（合同生效额 vs 已收回款，纯 CSS 零依赖）；收支方向卡显示周期标签；经营卡跟随周期（period 非 all 时"本季/本年经营"）
  - 新 `dashboard/_partial.php` 局部模板（AJAX 刷新用）；报表导出（CSV/XLSX）同步改金额字段
- **PC 通知中心升级**（/remind → 待办中心，与移动端统一待办中心完全一致）：Tab1 待办（合并流：待我审批>审批消息>提醒，复用 `MobileController::buildTodoStream` 改 public）/ Tab2 提醒（独立，含续约操作）/ Tab3 审批消息（异步）；`RemindController::index()` 组装三路；保留检查提醒/钉钉推送/推送记录管理按钮
- **验证**：admin 整页+KPI 4 卡+金额趋势+周期 AJAX 切换实测 200；employee01（有 approval:view）正确走审批人分支；PC /remind 三 Tab 渲染、待办流 3 条审批；六门禁全绿（PHPUnit 43·89）

### 新增：离职交接功能（用户询问 2026-08-03：用户从钉钉离职后客户/合同如何转移交接）

- **背景**：系统仅有客户单条转移/公海认领/审批转交，无离职交接入口——合同归属无法转移、离职用户存量数据成"孤儿"
- **新增 `AdminLogic::handoverUser()`**（事务内批量，任一失败整体回滚）：
  - ① 客户：`owner_id=from→to` 批量转移 + 部门同步 + `customer_transfer_record` 记录 + 跟进记录「离职交接：从用户#X 转入」
  - ② 合同：`owner_id=from→to` 批量转移 + 部门同步（`creator_id` 保留原值追溯）+ 写 `contract_revision` 变更日志（field_name=owner_id）
  - ③ 待审批：`approval_record` 中 from 的 PENDING 记录转给接收人；同实例同节点已有接收人 PENDING 记录则跳过，避免重复待办
  - ④ 按需禁用离职用户（status=2 进回收站）
- **接口**：`POST /ajax/admin/user/handover`（`system:user` 权限，接收人须在职），交接动作写审计日志（action=handover，含范围/数量/接收人）
- **前端**：用户管理列表 + 回收站各加「交接」按钮 → 交接弹窗（接收人下拉排除本人、交接范围勾选：客户/合同/待审批、交接后禁用勾选）；`AdminController::index` 注入 `handover_users`（全量在职用户）
- **UI 优化（用户反馈 2026-08-03）**：① 交接按钮图标 `bi-person-arrows`（偏宽）→ `bi-person-x`，与编辑/删除图标按钮同规格；回收站带文字按钮改纯图标 ② 接收人选择由 `<select>` 下拉改为**复用系统统一选人组件** `openUserPicker({multiple:false, exclude:[交接人]})`（部门树+搜索+radio 单选，与审批指定用户/抄送同组件）；`pickers.js` 通用增强支持 `opts.exclude` 过滤（不影响 flow-editor 既有调用）；移除已废弃的 `handover_users` 注入。jsdom 全链路验证：打开弹窗→选人（排除本人）→回填→提交参数正确→未选拦截
- **验证**：HTTP 实测全链路（客户 107→203、合同 9908→203 含变更日志、审批 175→203、leave01 禁用 status=2、transfer_record+audit_log 留痕），数据还原；顺手修正 `SecurityHelperTest::testFormatMoney` 过期断言（v2.38.12 金额整数化遗留）；六门禁全绿（PHPUnit 43·89）
- **P2 钉钉离职检测（同日补齐）**：`DingTalkService::syncOrganization()` 收集本轮钉钉员工集合，同步后扫描本地在职且绑定钉钉 userid 但不在集合中的用户 → 返回 `departed` 列表（含名下客户/合同/待审批数），**不自动禁用**（防误伤）；`DingTalkLogic` 透传；前端同步完成后弹窗提示名单 + 引导「用户管理 → 离职交接」。验证：mock 模式同步 4 部门 3 用户，正确识别 mock_user_999 绑定用户 leave02（客户 1 个）；数据/`.env` 已还原；六门禁全绿（PHPUnit 43·89）

### 移动端工作台升级为「统一待办中心」（用户反馈 2026-08-03：今日提醒数字/列表应外显审批消息，且与列表页功能重复）

- **问题**：1) `total_remind` 数字已含审批消息未读，但工作台卡片/提醒列表只渲染合同回款提醒——数字与列表对不上，审批消息不外显；2) 工作台今日提醒卡 = 提醒列表页顶部缩略版，功能重复
- **方案A「统一待办中心」**（三路合并、按角色裁剪）：
  - `MobileController::index()`/`reminders()` 新增 `buildTodoStream()`：待我审批 > 审批消息 > 到期/回款提醒，合并排序；`resolveNotifUrl()` 服务端先做钉钉深链重映射（与 notification.js 同规则）
  - 数字口径统一：`todo_total` = 待我审批全量 + 审批消息未读 + 合同提醒，工作台数字卡/角标/提醒页 Tab 全用此口径
  - 工作台待办卡：最多 5 条 + 按类型标签（审批红/消息蓝/提醒按 level），超出显示「查看全部」跳 /m/remind
  - 概览数字卡：无 `approval:view` 权限者不显示「待我审批」卡（角色差异）
  - `/m/remind` 改 Tab 结构：待办（合并流）/ 提醒（独立）/ 审批消息（站内信异步加载）
  - `InternalNotify::unreadList()` 新增（取未读前 N 条）
- **验证**：admin 登录实测工作台待办卡渲染 3 条审批待办、角标口径一致、提醒页三 Tab 结构完整；反射验证排序（审批>消息>逾期>提醒）与 URL 重映射；六门禁全绿

### PC 端客户详情补齐操作（用户反馈 2026-08-03：PC 客户无"释放到公海""转移"，有权账号需要这些功能）

- **问题**：PC 客户详情只有「编辑/返回」，无认领/释放/转移操作（移动端 REV-31 已有三件套）
- **修复**（`app/view/customer/detail.php` + `CustomerController::detail`，与移动端对齐）：
  - 顶部按钮区按状态显示：公海客户（owner_id=0）→「认领」；本人客户（is_owner）→「释放到公海」（warning 色，危险确认）+「转移」（outline 色）；均要求 `customer:edit` 权限（`can_edit` 注入）
  - **转移选人弹窗**（Bootstrap modal）：单选用户列表 + 姓名搜索 + 加载更多，复用 `/ajax/customer/transfer-targets`（与移动端同接口同权限范围）；`transfer_users` 服务端注入初始列表
  - 交互复用 PC 公共组件：`pcConfirm`（释放危险确认）/`$ajax`/`showToast`/`esc`
  - 释放/认领后端接口已有（`POST /ajax/customer/<id>/release|claim`），仅前端补入口
- **验证**：本人客户显示释放+转移按钮、公海客户显示认领按钮（临时改 107 归属验证后还原）；非归属人（employee01 看 admin 客户 107）不显示任何操作按钮；jsdom 全链路（打开弹窗→搜索"张"→过滤"张经理"→选中→确认→POST `/ajax/customer/107/transfer` body=FormData(to_user_id)）；六门禁全绿（PHPUnit 43·89）；admin/employee01 密码还原

### 客户转移改为选人弹窗（用户反馈 2026-08-03：要求输入接收人用户 ID 是愚蠢的设计，应弹出用户选择）

- **问题**：移动端客户详情「转移」用 `mPrompt('请输入接收人用户 ID')` 让用户手输数字 ID——不可选、易输错、体验差
- **修复（复用审批转交选人组件模式）**：
  - `MobileController::customerDetail` 注入 `transfer_users`（`UserLogic::getTransferTargets`：启用用户、排除本人、非管理员仅同部门，与审批转交同权限范围）
  - `customer_detail.php` 新增选人弹窗（m-sheet 底部抽屉 + 姓名搜索 + radio 用户列表 + 加载更多），`custTransfer` 由 mPrompt 改为打开弹窗；`doCustTransfer` 确认时校验必选
  - 新增 `CustomerController::transferTargets()` AJAX 接口（复用 `UserLogic::getTransferTargetsPaged` 搜索+分页，权限 customer:edit）+ 路由 `GET /ajax/customer/transfer-targets`
  - **样式抽取**：`.m-user-list/.m-user-opt/.m-user-opt input/.m-sheet max-height` 从 approval_detail.php 的 `$pageStyle` 抽取到 mobile.css 全局（用户选择器第二次复用，公共化；approval_detail 清理冗余保留 lightbox）
  - **后端加固**：`CustomerLogic::transfer` 增加目标用户有效性校验（>0、非本人、存在且 status=1）——防无效 ID 转移致客户"丢失"（选人弹窗前端保障 + 后端兜底）
- **验证**：jsdom 全交互链路（打开弹窗→选人→确认→POST `/ajax/customer/107/transfer` body=`to_user_id=203`→关闭→按钮禁用「转移中…」；取消关闭；未选择 toast「请选择接收人」；搜索"张"→过滤"张经理"、清空恢复初始 6 项、无结果空态「未找到匹配用户」+加载更多隐藏）；接口权限范围（admin 全量 / employee01 仅同部门 3 人排除本人）；后端校验（自转/无效 99999/0 均失败、有效 203 成功并验证 owner_id 变更后还原）；审批详情样式抽取无回归（弹窗/lightbox 正常）；admin/employee01 密码还原；六门禁全绿（PHPUnit 43·89）

### 全站非发票金额统一整数显示（v2.38.12：去掉小数点后两位，仅前端显示，数据库不变）

- **决策**：方案A——仅改前端 `number_format(...,2)`→`number_format(...,0)`，DB 保留 `DECIMAL(15,2)` 不动
- **但发票金额保留 2 位小数**（InvoiceController 错误提示、发票视图，确保开票精度不丢失）
- **改动范围（11 文件 18 处）**：
  - 核心函数：`app/common.php` `format_money()` 2→0
  - PC 视图：contract/detail（往来余额）、customer/detail（合同列表+回款记录；统计卡片已0位不变）、finance/index（销售/采购总额）、report/aging（账龄分组+明细）、project/detail（销售合同额+预算+采购额+关联合同列表）
  - 后端：`PaymentController` 回款超额错误提示 2→0
  - 移动端：contract_detail（顶部金额+往来余额）、approval_detail/approval_create（审批金额）、party_360（往来全景 `$fmt` 闭包）
  - 仪表盘（`number_format()` 无精度参数=整数）和 RemindService 提醒推送已为整数，无需改动
- **改动前状态**：同一客户详情页内表格 2 位、统计卡片 0 位——展示不一致。移动端大部分 0 位但合同详情/审批详情/往来全景又用了 2 位
- **验证**：六门禁全绿；PHP lint 11 文件全过；确认 InvoiceController 唯一保留 2 位

### 移动端归档合同列表精简（用户反馈 2026-08-03：单条占面积太大、标题前图标多余）

- **调整**（`app/view/mobile/archive.php`，HTML 渲染 + JS cardHtml 两处同步）：
  - 删除单条卡片标题前的归档图标（bi-archive，pic 列）——标题/编号直接起排，空态图标保留
  - 压缩占用：卡片内边距 14px → 12px、金额行距 10px → 8px、取消归档按钮行距 8px → 6px
- **验证**：真实浏览器实测单卡 156px（has_pic=false、padding 12px），列表项内图标零残留（仅空态图标保留）；admin 密码还原；六门禁全绿
- **再压缩（用户反馈：取消归档按钮占太大）**：「取消归档」由独立一行块状按钮（m-btn-ghost）改为轻量纯文字链接（12px 品牌色，仅 contract:edit 权限显示），独立一行右对齐、行距 4px（曾尝试并入金额行，用户反馈"更不对"——三信息挤一行杂乱，回退独立轻量行）
- **终版对齐合同列表（用户反馈 2026-08-03 第四次："显示更加不正常，参考合同列表或客户列表修改"）**：归档卡片完全复刻合同列表结构——恢复 pic 图标（bi-archive）、卡片内边距 14px、金额行对齐（方向标签 m-tag-recv/pay/muted + ¥金额 + 到期，行距 10px）；取消归档保持轻量文字链接行；HTML 渲染 + JS cardHtml 两处同步（JS 补 dirCls/dirTxt）；验证：图标/14px/方向标签/金额结构全部命中、3 卡一致；六门禁全绿；已截图交用户确认
- **第六版（用户反馈："改了很多次后更加差了"）**：根因——多轮迭代累积内联样式覆盖致结构与合同列表严重偏离。①标题 `white-space:normal` 覆盖默认 `nowrap+ellipsis`→长标题换行致卡片变高（最主要原因）；②金额行多余 `min-width:0`/`flex:none`；③「取消归档」用 `<a>` 嵌套在 `<a class="m-card">` 内→HTML 规范不允许 `<a>` 嵌套，浏览器/JSDOM 提前闭合外层 `<a>` 致卡片结构断裂。修复：彻底清除所有内联样式覆盖，归档卡片与合同列表 1:1 结构一致（仅三处差异：图标 bi-archive、标签「已归档」、金额行右侧多「取消归档」）；取消归档由 `<a>` 改 `<span role="button">` 消除嵌套 `<a>`；HTML 渲染 + JS cardHtml 两处同步；验证：jsdom 解析卡片结构完整（标题/副标题/标签/金额/取消归档全部命中、卡片内嵌套 `<a>` 数=0）；六门禁全绿

### 缺失附件点击报「控制器不存在」修复（用户反馈 2026-08-03：测试合同附件）

- **问题**：测试合同（HT-20260730-0013 等 6 条 REG- 回归残留）file_url 指向 `/uploads/reg/t.pdf`（无实际文件），点击附件 → 静态文件缺失被框架路由解析 → 404 错误页「控制器不存在:app\controller\UploadsController」
- **修复（双层）**：
  - **渲染层**：新增公共函数 `attachment_exists()`（public 下文件存在性校验）；PC 合同详情 + 移动合同详情 + 移动审批详情三处附件列表——缺失附件显示红色「（文件缺失）」标记、半透明、不可点击（PC 无下载按钮、移动 toast「文件缺失或已被删除」）；正常附件行为不变
  - **框架层**：`ExceptionHandle` 对 `/uploads/` 前缀的 404（框架抛 HttpException 'controller not exists'，**非 ClassNotFoundException**——已实测确认）返回友好 404 文本「文件不存在或已被删除」，不再呈现 debug 错误页
- **验证**：直接访问 /uploads/reg/t.pdf → 404「文件不存在或已被删除」（原先为控制器不存在错误页）；PC/移动 9953 附件显示「文件缺失」且无下载/预览动作；临时将 9953 附件指向真实 demo 文件 → PC 下载按钮、移动 openPreview 正常（验证后还原）；正常附件 /uploads/demo/service-contract.pdf 200；admin 密码还原；六门禁全绿



- **根因**：app.css 等高规则 `.row > [class*="col-"] > .stat-card { height:100% }` 会命中栅格列内**所有** stat-card——合同详情 `col-lg-8`（5 卡）/`col-lg-4`（2 卡）垂直堆叠的卡片全部被强制拉伸到列高 1035px，内容下方大片空白（真实浏览器实测：每卡 csH=1035.44px）
- **修复**：规则追加 `:only-child` → `.row > [class*="col-"] > .stat-card:only-child { height:100% }`——等高语义仅对「列内唯一卡片」（仪表盘 KPI 四卡、客户详情统计 4 卡）成立，垂直堆叠场景恢复内容自然高度
- **验证**（真实浏览器 getBoundingClientRect）：合同详情卡片恢复自然高度（基本信息 611 / 合同概要 91 / 附件 84 / 回款 95 / 发票 95 / 操作记录 387 / 审批记录 88）；仪表盘 KPI 四卡仍等高 106px；客户详情统计 4 卡仍等高 79px；admin 密码还原；六门禁全绿

### 合同详情全部模块被强制等高拉伸修复（用户反馈 2026-08-03：HT-20260730-0013 所有模块高度异常）



- **问题**：客户列表与相对方展示同一批实体（客户+供应商）且列维度几乎一致——用户分不清两个二级菜单的区别；相对方本应定位"钱"视角但列表无资金列，实际只承担跨实体检索（重复）
- **方案（差异化重定位，不删入口）**：客户管理分组管"人"（客户/供应商主数据），往来档案管"钱"（资金往来台账）
  - 更名：PC 侧边栏 + 移动更多页入口 + 移动列表页「相对方」→「往来档案」，图标换 bi-cash-coin
  - PC 列表页重定位：副标题「客户与供应商资金往来台账（余额仅统计交易合同）」+ 新增**「往来」列**（总额 + 余额，待收/待付红色警示、已清绿色、无往来灰）
  - `PartyLogic::summarizeBatch()` 批量汇总（按类型一次查合同 + 按合同分组汇总 PAID，避免逐行 getSummary N+1），`PartyController::index` 挂 `_sum`
  - 详情页「往来全景」、路由 /party 不动；合同筛选「相对方名称」字段名保留（语义指合同对方）
- **验证**：PC 列表标题/副标题/侧边栏/360 breadcrumb、移动列表/更多页全更新；资金列批量汇总正确（客户 102 总额 ¥786,000/余额 ¥486,000 待收、供应商 72 余额 ¥260,000 待付、多行待收/待付/已清渲染正常）；admin 密码还原；六门禁全绿
- **移动端对齐（2026-08-03 追加）**：`/m/party` 列表卡片新增「往来」行——卡片底部显示往来总额 + 待收/待付余额标签（橙色）或「已清」（绿色），无往来不显示；`MobileController::partyList` 复用 `PartyLogic::summarizeBatch` 批量汇总（与 PC 同源，无 N+1）；验证：13 个有往来卡片显示资金行、金额与 PC 完全一致（客户 102 待收 ¥486,000、供应商 72 待付 ¥260,000、3 个已清）；admin 密码还原；六门禁全绿

### 「相对方」重定位为「往来档案」资金台账（用户产品需求 2026-08-03：与客户列表重叠）



- **PC 合同详情**：基本信息表乙方行下新增「乙方往来」行——乙方为客户/关联供应商时显示信用标签（梯度文本色/高风险）+ 往来余额（待收/待付）+「往来全景」按钮 → /party/<type>/<id>；`ContractController::detail` 注入 `party360`（复用 `PartyLogic::getSummary`，与移动端同源）
- **PC 客户详情**：「统计」tab 升级为往来汇总（360 交易合同口径 4 卡：往来总额/已收/待收余额/逾期金额）+「往来全景」按钮 + 最近动态列表（审计动作中文标签 + 合同号，前 5 条）；`CustomerController::detail` 注入 g360（复用 `PartyLogic::get360`，与移动端同源）
- **踩坑修复**：PC 两控制器原未 `use PartyLogic` → 裸名调用运行时 Class not found 500（php -l 不查类解析）；已补 use，PC 详情页恢复 200
- **验证**：PC 合同 9908「乙方往来：信用 100（深绿）+ ¥往来余额 + 往来全景」、9911 供应商摘要；客户 102 统计 tab ¥786,000/待收余额/逾期金额/往来全景按钮；最近动态「状态变更 合同 #9908」（临时插审计验证后删）；验证数据清理 + admin 密码还原；六门禁全绿

### 往来全景融入 PC 合同/客户详情（用户需求 2026-08-03：PC 端对齐移动端）

### 往来汇总冗余行清理 + 「相对方 360」黑话文案统一（用户反馈 2026-08-03）



- **问题**：①客户/供应商详情往来汇总 2×2 已有「待收/待付余额」格，底部还多一行「待收 X 元」tag（冗余）；②「往来全景」链接进入的页面标题仍为「相对方 360」（内部黑话，与用户侧文案决策不符）
- **修复**：
  - 删除三处冗余 tag：客户详情（customer_detail）、供应商详情（supplier_detail）、往来全景页（party_360）底部「待收/待付 X 元」；往来全景页余额格标签由「余额」改为「待收余额/待付余额」（语义明确）
  - **文案统一**（用户侧去「360」黑话）：移动详情页 + PC 详情页标题 →「往来全景」；移动/PC 列表页 + 侧边栏 + 更多页入口 →「相对方」；PC 列表操作按钮「360 视图」→「往来全景」
- **验证**：客户 102/供应商 72 汇总卡无冗余行；/m/party/customer/102 标题「往来全景」+「待收余额」；/m/party 列表标题「相对方」；PC /party 标题/按钮、更多页入口全部更新；用户可见「360」字样全站清零；六门禁全绿

### 相对方 360 能力融入移动端业务页（用户产品需求 2026-08-03，P0+P1 全量实施）

- **背景**：360 是"入口孤岛"（藏在更多→相对方列表），日常浏览客户/合同/供应商时感知不到；且客户详情「统计」卡（全部合同口径）与 360「收支汇总」（交易合同口径）两套数字打架
- **方案**：360 能力按实体下沉业务页——内嵌摘要（一屏决策）+ 直达独立全景页；独立 /m/party 保留（跨实体检索）；用户侧文案统一「往来全景」（不用内部黑话 360）
- **① 合同详情甲乙方**（P0）：乙方为客户/关联供应商时，甲乙方卡内嵌往来摘要行——信用标签（梯度/高风险）+ 往来余额（待收/待付）+「往来全景」链接 → /m/party/<type>/<id>；乙方角色判定补「供应商」（原仅客户/外部）
- **② 客户详情**（P0）：「统计」卡升级为「往来汇总」卡（360 交易合同口径 2×2：往来总额/已收/待收余额/逾期金额 + 待收 tag），卡内嵌「最近动态」（审计动作中文标签 + 合同号，前 3 条折叠 + 展开按钮，模块数不变 8）
- **③ 供应商详情**（P1）：补「往来汇总 2×2（应付口径）+ 关联合同（默认 3 条 + 展开）+ 最近动态」三卡，与客户详情信息对等
- **实现**：`PartyLogic::getSummary()`（轻量摘要，与 get360 同口径，供合同详情避免全量查询开销）；`MobileController` 三处详情注入 party360/g360；`credit_grade()` 公共函数（信用分档单一来源，双端拼类名）；`mShowMore()` 从 customer_detail 内联提取至 mobile-common.js（多页复用）
- **验证**：合同 9908（乙方客户 102）显示「信用 100 + 深绿标签 + 往来余额 + 往来全景」、9911（供应商 72）显示「乙方（供应商）」；客户 102 往来汇总 ¥786,000（=186,000+600,000 正确）、待收 ¥486,000；供应商 72 汇总 ¥528,000、待付 ¥260,000、关联合同 2 条、最近动态「审批驳回 合同 #9911」2 条；360 跳转页 200；临时插审计验证客户侧动态渲染后删除；验证数据清理 + admin 密码还原；六门禁全绿

### 跟进记录英文枚举中文化 + 前端枚举直出规则（用户反馈 2026-08-03）

- **问题**：移动端客户详情「跟进记录」多处英文 RELEASE、INACTIVE——活动类型码（`$a['type']`）被直接 `htmlspecialchars` 上屏，且逻辑层写入内容夹带「沉睡/INACTIVE」
- **修复**：
  - 新增公共函数 `activity_type_label()`（app/common.php）：CLAIM=认领 / TRANSFER=转移 / RELEASE=释放 / NOTE=跟进，未命中回退中文「跟进」——**绝不回退英文原始码**（与 dict() 回退原值行为刻意区分）
  - 移动客户详情（`mobile/customer_detail.php`）+ PC 客户详情（`customer/detail.php`）跟进记录标签统一走该函数
  - `CustomerLogic::releaseToPool` 内容去英文：'释放到公海（生命周期置为沉睡/INACTIVE）' → '…沉睡）'；**存量数据已清洗**（REPLACE 沉睡/INACTIVE→沉睡）
  - 顺带修 supplier 列表类型回退英文模式：dict 未命中时显示「其他」而非原始英文码
- **规则落地（防复发）**：
  - `scripts/check_frontend.sh` 新增**枚举直出检测**：视图 `htmlspecialchars($x['type'])` 直出 → 门禁 FAIL（已反向验证：临时插入直出代码触发 FAIL、删除后 PASS）
  - `DEVELOPMENT_GUIDE.md` 新增「7.2 前端展示规范：禁止英文枚举直出（强制）」
- **验证**：临时插入 CLAIM/TRANSFER/NOTE + 存量 RELEASE 共 4 类型，移动端标签显示「认领/跟进/转移/释放」、PC 端「跟进/转移/释放」全部中文，页面零英文枚举（RELEASE/TRANSFER/NOTE/CLAIM/INACTIVE 均不出现）；验证数据已删除、admin 密码已还原；六门禁全绿

### 信用评分标签五档颜色梯度（用户反馈 2026-08-03：高信用分灰色不佳 → 分高色深）

- **调整**：移动端客户详情概要信用标签由统一灰标（m-tag-muted）改为**五档梯度**（浅底深字，与 m-tag 体系一致）：90+ 深绿 / 80+ 绿 / 60+ 蓝 / 40+ 橙 / <40 红——信用越高颜色越深；高风险客户仍优先显示红色「高风险」徽标
- **PC 端同步**：客户详情「信用评分」数字由默认黑色改为同梯度文本色（`.credit-a~e`），双端口径一致；暗色模式补等价色（对齐暗色 pc-tag 色系）
- **实现**：mobile.css 新增 `.m-tag-credit-a~e` 五类；app.css 新增 `.credit-a~e` 文本色 + `@media (prefers-color-scheme: dark)` 覆盖；`app/view/mobile/customer_detail.php` 概要标签区 + `app/view/customer/detail.php` 评分行按分数映射
- **验证**：临时改 4 个客户分数（85/70/50/30）双端 curl 断言 5 档 class 全命中（PC `credit-b~e`、移动 `m-tag-credit-b~e`，100 分 → credit-a/m-tag-credit-a）；jsdom 实跑计算样式 10 项全过（含背景/文字色精确值）；验证后分数与 admin 密码已还原、零污染；六门禁全绿

### PC 客户生命周期漏斗移至客户列表上方 + 去除标题（用户反馈 2026-08-03）

- **调整**：`app/view/customer/index.php` 生命周期漏斗看板（含阶段点击筛选 + 筛选栏）从表格**下方**移至**上方**——漏斗提供全局概览与筛选入口（先看分布再看明细），列表承载明细
- **去标题**：删除漏斗 card-header「客户生命周期漏斗 / 共 N 个有效客户」——四段卡片自带阶段标签与数字（线索/商机/成交客户/公海沉睡），标题冗余（与移动端一致）；清理未使用变量 $funnelTotal
- **选中态优化**：漏斗阶段选中样式由 `outline: 2px solid` 描边框（生硬）改为 `.lc-active` 类——浅品牌蓝底（--brand-light）+ 圆角 10px + 标签主色，hover 同底色提示，对齐 pc-chips active 风格
- **去除「当前筛选」栏**：lcFilterBar 整块删除——选中态（浅蓝底）即筛选状态指示；清除能力改为「再次点击已选中阶段取消筛选」（toggle），clearLcFilter 保留供复用
- **验证**：真实浏览器漏斗在表格上方（漏斗 y=70 < 表格 y=223）、无 card-header、四段标签数字正常（成交客户 17）；点「成交客户」→ 选中态浅蓝底无描边 + 筛出 15 行、再次点击取消恢复；四门禁全绿

### 移动端联系人栏目空 + 360 视图无移动入口修复（用户反馈 2026-08-03）

- **问题 1：客户有联系人但移动端联系人栏目为空**
  - **根因**：客户表单仅维护 `customer.contact_name`（主联系人字段），详情页联系人栏目读 M9 独立表 `customer_contact`——存量客户该表无记录 → 栏目"暂无联系人"（演示库 customer_contact 全为孤儿数据）
  - **修复**：`CustomerContactLogic::getListForDisplay()`——联系人表为空且有主联系人字段时，构造一条 `is_primary=1` 兜底记录展示（标"主/主联系人"）；PC（CustomerController::detail）+ 移动（MobileController::customerDetail）统一改用；兜底记录（from_primary）不显示编辑/删除（移动+PC 视图均限制，主联系人随客户资料维护）
  - **验证**：移动 103 显示"赵敏/主/主联系人/电话/邮箱"；有 M9 表记录时正常显示表记录（手工插记录验证后删除）；PC 联系人 tab 同步兜底
- **问题 2：360 视图移动端无入口 → 原生移动版（用户确认跳 PC 版不合适）**
  - **修复**：新建移动版——`/m/party` 相对方列表（搜索 + 客户/供应商 chips + 卡片）+ `/m/party/<type>/<id>` 360 详情（概要卡：名称/等级/联系人/税号；收支汇总 2×2：关联合同/往来总额/已收(付)/余额 + 待收(付)；关联合同列表带状态标签；最近动态），复用 PartyLogic::get360 聚合（统计口径与 PC 一致）；MobileController 加 partyList/partyView；更多页入口改 `/m/party`（party:view 裁剪）
  - **验证**：更多页入口 → 列表 26 相对方 + 供应商 chips 筛选 8 条 + 搜索"前海"命中 1 条；客户 103 应收汇总（1 合同/¥420,000/余额 ¥420,000）、供应商 72 应付汇总（2 合同/已付 ¥268,000/余额 ¥260,000）全正常
- **移动版 360 五处 UI/文案修复（用户反馈 2026-08-03）**：
  1. **版权信息不居中**：移动端未引 Bootstrap（text-center 类无定义）→ mobile.css 补 `.m-footer-copyright{text-align:center}`（全局修复所有移动页）
  2. **供应商列表英文 supplier**：`$r['type']` 覆盖 supplier.type 字典码后 tag 误显 'supplier' → PC+移动统一先取原字典码、tag 用 `dict('supplier_type')` 中文（服务商/媒体渠道等），PC 版同类 bug 一并修复
  3. **最近动态乱码**：audit_log.content 是 JSON 详情 → 移动版改显示 `audit_action_label()` 中文动作标签 + 合同号（对齐 PC 主展示）
  4. **长标题截断**：列表标题 white-space:normal 换行完整显示（不再省略号）
  5. **列表图标多余**：去掉相对方卡片左侧图标（类型已有标签）
  - **验证**：供应商列表 tag"服务商"（PC+移动）、标题换行、版权 text-align:center、动态"审批驳回 合同 #9911"无 JSON
### 移除信用额度（用户决策 2026-08-03：小规模企业非赊销模式，额度无业务逻辑）

- **背景**：产品评估——信用评级（credit_score/high_risk 自动重算 + 审批条件路由）保留（零维护、风险提示有价值）；信用额度（credit_limit）仅有字段与展示、无任何额度校验逻辑，小企业非赊销场景收益低 → 用户决定**直接移除**
- **移除内容**：`customer.credit_limit` 列（三份脚本 1:1 删列 + 新迁移脚本 `database/migration_v2.38.13_remove_credit_limit.sql` MySQL 幂等 DROP + SQLite 注释 + 演示库 DROP COLUMN）；PC 表单字段、PC 详情信用额度格（评分行 colspan 合并）、移动详情额度行、CustomerController::save 白名单全部移除
- **验证**：PC 详情/表单、移动详情三页面无「信用额度」、信用评分保留；代码层零残留；六门禁全绿
- **注意**：本版含表结构变更（删列），生产部署需执行 `migration_v2.38.13_remove_credit_limit.sql`

### 移动端客户详情模块收纳优化（用户确认方案 2026-08-03：模块多、下拉不便）

- **① 信用评级并入概要卡**：概要标签区加信用标签（高风险→红色「高风险」徽标 / 正常→灰色「信用 N」），删除独立「信用评级」卡——风险一眼可见（第一屏）
- **② 列表类模块折叠**：关联合同 / 回款记录 / 跟进记录默认各显示前 3 条 + 「展开全部 N 条」按钮（点击展开剩余、按钮消失）；空列表不渲染按钮
- **③ 模块数 9 → 6**（删除信用评级卡后）；基本信息/操作区原顺序合理不动
- **实现细节**：折叠项内联 `display:none` 控制（不依赖 CSS 类，避免清内联后回退 CSS 隐藏的坑）；`mShowMore()` 恢复时按元素类型设置（m-row→flex、m-kv→block）；展开按钮 if 条件与折叠 class 统一无空格写法
- **验证**：真实浏览器——信用评级卡移除、概要有「信用 N」标签（103）；临时阈值 1 验证折叠+展开全流程（102：折叠 1 条可见 → 点展开 2 条全显示 + 按钮隐藏），阈值还原 3（演示库合同最多 2 个不触发折叠，机制已证）；四门禁全绿

### 独立联系人「更多信息」备注字段（用户需求 2026-08-03 + 详情页评估）

- **新增 `customer_contact.remark`（备注/更多信息，微信号等）**：
  - 数据层：三份脚本 1:1 加 `remark` 列（sqlite TEXT / mysql VARCHAR(255)）+ 新迁移脚本 `database/migration_v2.38.12_contact_remark.sql`（MySQL 幂等 ALTER + SQLite 注释）
  - 逻辑层：CustomerContactLogic::save 白名单加 remark（trim，无格式校验）；**CustomerContactController::save 控制器白名单补 remark**（此前字段被丢弃——实测发现）
  - PC 端：联系人弹窗加「更多信息（微信号等）」textarea（新增/编辑回填 + 提交）、联系人表格加「备注」列（colspan 6→7）
  - 移动端：联系人表单加「更多信息」textarea（新增/编辑回填 + 提交）、联系人卡片显示备注
  - **验证**：移动端保存 remark 入库（"微信号 wx_test2"）+ 卡片显示；PC 弹窗保存 remark 入库（"PC端微信号测试"）；六门禁全绿
- **联系人详情页评估结论：不新增独立详情页**——联系人字段少（6+备注），PC 表格/移动卡片已全字段展示、编辑弹窗可看全部信息，独立详情页信息密度过低、维护成本高；备注已保证在列表/卡片完整可见

- **移动端生命周期漏斗阶段可点击筛选（用户反馈：PC 可点移动不可点）**：漏斗四段加 `.lc-funnel-stage`（data-lc + 点击样式），JS 提取 `setLcFilter()` 公共函数——漏斗阶段与下方筛选 chips **双向联动**（点漏斗 chips 同步高亮 + 列表筛选；点 chips 漏斗同步高亮），选中态浅品牌蓝底；与 PC 端交互对齐
  - **验证**：点漏斗「成交客户」→ 筛出 17 条 + chips/漏斗同时高亮（浅蓝底 rgb(232,241,255)）；点 chips「公海/沉睡」→ 漏斗 INACTIVE 同步高亮
- **门禁**：四门禁全绿（PHPUnit 43·89 + view_globals + frontend + dead-entry）

### 独立卡片异常超高 + 财务/客户二级菜单高亮错位修复（用户反馈 2026-08-03）

- **问题 1：仪表盘今日提醒/按项目 TOP、项目管理页列表卡片异常超高（1310px）**
  - **根因**：KPI 等高规则 `.stat-card { height:100% }` 作用于**全局** stat-card——Bootstrap 5 `.card` 是 flex-column 且 `.card-body` 有 `flex:1`，直接挂在 `.main-content`（高 ~1350px）下的独立卡片被撑满父容器 → card-body 自动填充 → 整卡 1310px 异常超高
  - **修复**：等高规则收窄为 `仅栅格列内`——`.row > [class*="col-"] > .stat-card { height:100% }`（KPI 卡等高场景保留），独立卡片高度自适应
  - **验证**：今日提醒 1310→130px、按项目 TOP 1310→213px、项目页卡片 136/247px 正常、**KPI 四卡等高 106px 仍成立**
- **问题 2：点应收账龄二级菜单高亮错位到回款管理**
  - **根因**：`/report/aging` 仅注入 `menu_active='finance'` 未注入 tab，而「回款管理」active 条件为 `finance && tab!='invoice'`（tab 为空误中）；应收账龄链接本身无 active 判断
  - **修复**：ReportController::aging 注入 `tab='aging'`；sidebar 应收账龄补 active 条件、回款管理条件改 `!in_array(tab,['invoice','aging'])`
  - **同类修复**：/party（相对方 360）也未注入 menu_active → 子菜单不高亮（父分组展开条件已含 party 但链接无高亮）→ PartyController::index 注入 `menu_active='party'`
  - **验证**：/report/aging 应收账龄高亮、/finance 回款管理高亮（回归）、/party 相对方 360 高亮+分组展开
- **门禁**：四门禁全绿（PHPUnit 43·89 + view_globals + frontend + dead-entry）

## v2.38.11（2026-08-03）— 仪表盘/表单响应式修复 + 签署残留清理 + 三维度审查 + 门禁增强 + 客户生命周期补全（MINOR）

### 仪表盘 KPI 卡 2×2 降级布局（用户反馈 2026-08-03：侧边栏还在时四模块还是很丑）

- **根因**：上一轮 clamp(vw) 字号修复不足——clamp 基于**视口**而 KPI 卡宽由**内容区（视口-220 侧边栏）**决定，vw 与卡宽非线性对应；视口 800-1100（内容区 580-880px）时 `col-md-3` 四列每卡仅 130-205px，金额"¥4,431,000"（26px 字号 140px 宽）+ 图标在 130px 卡里必然溢出/挤压，视觉参差
- **修复**（dashboard/index.php + app.css）：KPI 行加 `.kpi-row` 类，`@media (min-width:768px) and (max-width:1199.98px)`（侧边栏存在区间）下 `.kpi-row > .col-md-3 { width: 50% }`——四卡降级 **2×2 布局**，每卡 ≥274px，金额/图标/副标题从容放下且两行等高；≥1200px 保持 4 列；<768px 由 col-6 天然两列
- **验证**：真实浏览器内容区 480/580/680/880px 模拟——卡宽 225/275/325/425px、金额 140px 全部 fits、图标可见且在卡内、高度 106px 全等高；第二行（本月经营 col-lg-7/5）不受影响
- **门禁**：六门禁全绿（PHPUnit 43·89）

### 新建/编辑合同表单响应式列宽修复（用户反馈 2026-08-03：侧边栏还在时多个字段遮挡显示不全）

- **根因**：侧边栏 fixed 220px 占位，Bootstrap 断点按**视口**计算而非内容区——视口 768-1199px 时内容区仅 548-980px，`col-md-3`(25%)/`col-md-4`(33%) 列宽仅 137-245px，长文本 select（签约主体 13 字/关联项目 11 字）与输入框内容截断显示不全；≤767px 侧边栏消失 + `col-md-*` 失效变单列，所以"侧边栏消失就正常"
- **修复**（app.css，不再逐个字段打补丁）：`@media (min-width:768px) and (max-width:1199.98px)` 下 `#contractForm .col-md-3/.col-md-4` 统一 `width:50%`（两列布局），内容区 548-980px 时每列 ≥274px，全字段内容完整；≥1200px 保持原 4 列布局（此前已验证够宽）
- **验证**：真实浏览器内容区 580/680/780px 模拟——50% 列宽下所有 select/输入框内容放得下（签约主体 340px 列 ✅、关联项目 ✅），三步骤 wizard 字段均被规则覆盖（step2/3 默认隐藏，进入时同规则生效）；关键词为 hidden 降级组件非截断；编辑页复用同一表单页自动生效
- **门禁**：六门禁全绿（PHPUnit 43·89）

### PC 端三处 UI/文案优化（用户反馈 2026-08-03）
1. **提醒页说明文字精简**：原两行长文案（crontab 配置细节）→ 一行核心信息"提醒已支持每日自动推送（crontab 调用 php think remind:dispatch）…「立即推送到钉钉」仅用于手动触发或测试"；三按钮保留（检查提醒=所有人、立即推送/推送记录=管理员 remind:manage）
2. **仪表盘 KPI 四模块高度不齐修复**：窄屏（钉钉桌面版 ~680px 列宽）下"回款率 · 本月预计"长标题换行导致卡片 124px vs 其他 106px → ①`.stat-card` 加 `height:100%`（col flex 等高）②标题精简"回款率 · 本月预计"→"本月回款率"；实测 680px 下四卡全等高 106px
3. **新建合同表单长文本字段宽度自适应**：合同分类/收付款方向/签约主体 col_pc 3→4（宽屏 3 列、窄屏 33% 列宽不截断）——实测 1050px 下"义乌十八腔网络科技有限公司"完整显示（原 266px 截断）
- 验证：真实浏览器多宽度实测 + 权限正确性（employee01 仅见"检查提醒"）+ 六门禁全绿

### 三维度审查建议落地（2026-08-03）
- **check_frontend.sh 扩展覆盖视图内联脚本**：新增检测"footer 之前执行的视图内联脚本中，深度 0 顶层调用 + 使用 $ajax + 无 DCL 防护"（本轮 monthly/tax 两个 bug 的根治）；括号深度法排除函数体内调用、fetch 不依赖 app.js 不拦截；验证拦截能力（模拟 monthly 旧 bug → FAIL 定位 genReport()；还原 → OK，退出码风险 1/正常 0）
- **SIGNED 状态机流转保留**（评估后无需删除）：存量虽删但状态机定义保留以兼容历史库数据，补充注释说明
- 六门禁全绿（PHPUnit 43·89）

### 产品·前端·后端三维度综合审查（2026-08-03，outputs/review_20260803.md）
- **产品层**：合同/审批/发票/回款四大闭环验证全部通过（状态机 10 态、审批自动推进 EXECUTING、发票 biz_type 分流、权限可见性无缺口）
- **后端层**：安全防护全绿——CSRF 全局中间件、权限守卫+数据范围（canAccessRecord）、SQL 全参数化（唯一拼接表名来自硬编码常量）、越权实测（employee01 访问 admin/audit 全 403）、参数白名单
- **前端层**：门禁 3 道全过 + jsdom 30 页零错误 + **发现并修复 2 个隐藏 bug**（同类根因：视图内联脚本顶层调用 $ajax 早于 app.js）：
  1. **经营月报**（report/monthly.php）：顶层 genReport() → ReferenceError → 默认统计加载失败
  2. **税务汇总**（finance/tax.php）：顶层 load() → 同上
  - 均改为 DOMContentLoaded 包裹（对齐 contract.js 模式）
- **建议**：check_frontend.sh 扩展"视图内联脚本顶层调用检测"（本轮 2 bug 门禁未拦住，因只查独立 JS）；SIGNED 状态机流转清理（低优先级）
- 六门禁全绿（PHPUnit 43·89）

### 存量 SIGNED 合同清理 + 生效合同统计修正（用户 2026-08-03：存量签署合同可删除）
- **存量清理**：2 条历史 SIGNED 合同（9914/9915，各关联 1 张发票）软删（is_deleted=1，数据回收站可恢复）；SIGNED 存量归零，仪表盘状态条不再出现"历史已签"标签
- **统计修正**：`ReportLogic::dashboardSummary` / `getMobileSummary` 的 `signed_contracts` 原只统计 SIGNED（签署移除后恒 0）→ 改为 **SIGNED + EXECUTING**（"生效合同"语义：执行中的合同数）；移动报表实测"生效合同=8"（8 个执行中合同）；驾驶舱无 signed 标签展示不受影响
- **验证**：真实浏览器——仪表盘状态条 SIGNED 标签消失、移动报表"生效合同=8"、回收站可恢复 2 条；六门禁全绿

### 签署功能移除后的标签残留清理（用户反馈 2026-08-03：仪表盘等页面有"已签署"残留）
- **背景**：签署功能已移除（v2.38.4 remove_sign，审批通过直接 EXECUTING），但 SIGNED 状态仍有 2 条历史存量合同，`dict_contract_status` 字典及各视图硬编码仍显示"已签署"
- **修复**："已签署"标签 → **"历史已签"**（标明 SIGNED 是签署功能移除前的历史存量）：ContractLogic STATUS_LABELS（主源）、contract/create.php statusMap、移动端 contract_detail/project_detail statusMap、InvoiceLogic 注释同步；**三份字典脚本 1:1 同步**（init_sqlite/init_mysql/init.sql）
- **移动报表"已签约"标签 → "生效合同"**（统计 SIGNED+EXECUTING，签署已移除后"生效合同"语义更准）
- **演示库字典已更新 + ThinkPHP 文件缓存清理**（dict() 走 Cache::remember 300s，须删 runtime/cache/*.php 才生效）
- **验证**：真实浏览器——仪表盘状态条"已签署 2"→"历史已签 2"、SIGNED 合同 9914 移动详情"历史已签"、移动报表"生效合同"；代码层全库 grep 无"已签署/已签约"残留（签约主体概念除外）；六门禁全绿
- 注：SIGNED 状态本身保留（历史存量数据），仅标签语义调整

## v2.38.10（2026-08-03）— 客户生命周期补全 + 死功能排查修复 + 门禁增强 + VI 补全（MINOR）

### PC 侧边栏二级菜单排序整理（用户反馈 2026-08-03）
- **系统设置分组排序调整**：原「用户管理 → 角色权限 → 审批流程 → 本公司主体 → 字典设置 → 钉钉设置 → 系统配置 → 发票表单」→ 调整为「用户管理 → 角色权限 → 审批流程 → **发票表单（业务表单工具前移）** → 本公司主体 → 字典设置 → 钉钉设置 → **系统配置（兜底设置垫底）**」
- **其余分组排序核对**（全部合理，未改动）：合同管理（列表/新建/归档）、客户管理（列表/新增/公海池/供应商/相对方 360）、项目管理（列表/新建）、财务中心（回款/发票/应收账龄）
- **验证**：真实浏览器实测系统设置 8 项排序正确、其余 4 分组排序无误；门禁全绿

### 统一 VI 视觉系统补全（2026-08-03，outputs/vi_audit_20260803.md）
- **发现**：v2.38.8 视觉重构覆盖了静态展示类（顶栏/标签/卡片/表格），但**交互控件类仍是 Bootstrap 默认样式**——主按钮 #0d6efd 蓝+3.75px 圆角、保存按钮 Bootstrap 绿、表单 5.625px 圆角+灰边框+36px 高，与品牌 VI 及移动端基准（#0b5ed7 蓝+12px 圆角、10px 圆角+44px 高）不一致
- **修复（app.css 新增"PC 交互控件对齐移动端 VI"段）**：`.btn` 圆角 10px；`.btn-primary`/`.btn-success` 用 `--bs-btn-*` 变量全部指向品牌蓝（保存/提交主操作语义统一）；`.form-control/.form-select` 10px 圆角 + `--line` 边框 + 40px 高；`.table thead th` #fafbfc → var(--bg-page) token 化；暗色模式不受影响
- **验证**：真实浏览器计算样式——btn-primary rgb(11,94,215)+10px、提交按钮品牌蓝、form-control 10px+#ebedf0+40px、表头 token；门禁全绿
- 残留小项：outline-primary 变体边框仍默认蓝（次要）、顶栏高度 53vs56px（可接受）

### 页面入口可达性门禁（2026-08-03，承接死功能排查）
- **新增 scripts/check_dead_entry.sh**：自动检测"页面路由已注册但侧边栏/移动端/页内均无入口"的死功能（相对方 360、应收账龄、生命周期漏斗 3 次同类问题的根治）；入口来源含侧边栏+移动端+业务页内导航，admin tab 内子路由白名单豁免
- **已接入 release.sh 门禁链**（--force 可跳过）；验证拦截能力（临时移除应收账龄入口 → FAIL 并定位 /report/aging；还原 → OK）
- 发布门禁现共 6 道：schema parity / db comments / view globals / **frontend 加载顺序** / **dead entry 可达性** / PHPUnit

### 半成品/死功能排查与修复（2026-08-03，outputs/dead_feature_audit_20260803.md）
- **排查**：看板统计/DB 字段 vs 表单/页面路由 vs 入口/字典使用/短视图/JS 死函数六维度全站扫描
- **修复 2 个"完整实现但无入口"死功能**（与生命周期漏斗同类）：
  1. **相对方 360**（/party）：相对方列表+360 汇总视图（合同/回款/发票）完整实现但全站无链接 → 侧边栏「客户管理」分组加「相对方 360」入口（按 party:view 权限裁剪）
  2. **应收账龄分析**（/report/aging）：v2.38.3 新增的 30/60/90 天账龄分组报表无入口 → 侧边栏「财务中心」分组加「应收账龄」入口
- **排除误报**：移动报表概览（更多页有入口）、high_risk/credit_manual（评级系统内部字段）、tax_rate 字典（有意冗余）、短视图（JS 表格骨架）、JS 死函数（内部调用）
- **验证**：真实浏览器 admin 侧边栏两个新入口可见、/party 与 /report/aging 页面正常渲染；门禁全绿
- **预防**：页面路由 vs 入口差集检查建议纳入发布门禁

### 客户生命周期功能补全（用户反馈 2026-08-03：线索/商机无入口 + 漏斗标题冗余）
- **背景**：M10 客户生命周期漏斗仅有展示（四段：线索/商机/成交客户/公海沉睡），但 `lifecycle_status` 字段无任何编辑入口、列表无筛选、详情无展示——"线索/商机"是半成品死功能（漏斗永远显示 0）
- **表单**：PC + 移动客户创建/编辑表单加「生命周期」下拉（线索/商机/成交客户/公海沉睡）；保存接口支持该字段 + 值白名单校验（非法值回退 ACTIVE，防篡改）
- **列表**：PC 客户列表漏斗阶段可点击筛选（data-lifecycle + 筛选栏 + 清除），表格加生命周期列（pc-tag 同色系）；移动客户列表加生命周期筛选 chips + 卡片生命周期标签
- **详情**：PC 客户详情信息表加「生命周期」行、移动客户详情概要加生命周期标签（均与漏斗同色）
- **去标题**：移动端客户页漏斗移除「客户生命周期漏斗（N 个）」标题（四段卡片自带阶段标签，标题冗余；PC 保留带统计说明的标题）
- **验证**：真实浏览器全链路——新建 LEAD 客户成功/非法值回退 ACTIVE、PC 漏斗点 ACTIVE 筛选出 15 行且生命周期列正确、移动 chips 点线索筛出客户、移动/PC 详情生命周期标签正确、PC 表单下拉四选项；五门禁全绿（PHPUnit 43·89 + 前端门禁）

## v2.38.9（2026-08-03）— 移动端交互优化 + 前端系统性巡检修复 + 脚本加载门禁（MINOR）

### 巡检建议落地（2026-08-03）
- **新增 scripts/check_frontend.sh 前端脚本加载顺序门禁**：自动拦截「依赖 $ajax 的独立 JS 无 DOMContentLoaded 防护 + IIFE 顶层调用」回归（contract.js 07-25 / notification.js、customer_pool.js 08-03 同类 bug 反复的根治）；已接入 release.sh 门禁链（--force 可跳过）
- **核实全库独立 JS 初始化模式**：依赖 $ajax 且顶层调用的仅 notification.js/customer_pool.js（均已 DCL 修复）；其余用原生 fetch 或无顶层调用或已 DCL，全部安全

### 前端系统性巡检（2026-08-03，outputs/frontend_audit_20260803.md）
- **巡检方法**：静态扫描（onclick 引用有效性 27 项核实、100 个 href 路由可达、109 个 fetch 端点 HTTP 实测）+ 动态验证（jsdom 25 页运行时、真实浏览器 24 页网络/console、关键页交互抽查）
- **修复 2 个隐藏功能 bug**（同类根因：脚本在 footer 的 app.js 之前加载 + IIFE 顶层立即调用 `$ajax` → ReferenceError → 列表永远加载不出）：
  1. **通知列表**（notification.js）：PC 提醒页/通知页 + 移动端提醒页 3 处受影响，修复为 DOMContentLoaded 包裹初始化
  2. **公海池列表**（customer_pool.js）：客户公海池页受影响，同法修复
  - **历史线索**：contract.js 2026-07-25 曾修复完全相同的"脚本先于 app.js"问题，本次两文件漏改——已记入报告建议统一加载策略
- **其余全绿**：onclick/href/fetch 引用全部有效、jsdom 25 页 0 错误、浏览器 24 页 0 网络失败、移动合同编辑/审批详情交互正常

### PC 侧边栏二级菜单折叠修复（用户反馈 2026-08-03）
- **根因**：点击二级菜单整页跳转后 sidebar 重渲染，父分组展开条件只认一级菜单 `$menu_active`——「归档管理」激活时 `menu_active='archive'`≠'contract'、「供应商」激活时 `menu_active='supplier'`≠'customer'，导致合同管理/客户管理分组折叠
- **修复**：`sidebar.php` 父分组展开条件 `in_array` 纳入归属二级菜单激活态——合同管理分组含 archive、客户管理分组含 supplier；并核对全部分组（project/finance/admin 子菜单激活态均已覆盖，无其他同类问题）
- **验证**：真实浏览器——进 /archive 合同管理保持展开（show+aria-expanded=true+父高亮）、进 /supplier 客户管理保持展开；回归 /contract /customer /finance /admin/user 各只展开对应分组互不干扰；四门禁全绿（PHPUnit 43·89）

### 移动端合同详情底部操作栏优化（用户反馈 2026-08-03：拥挤 + 续约突兀 + "已X"歧义）
- **最终方案（产品确认后实施）：主操作 + 动作面板**——底部仅「续约（品牌蓝主操作）+ 更多（次级）」2 个按钮，操作栏高度 153px → **69px**（内容区多 84px）；点「更多」滑出底部动作面板（复用现成 `.m-sheet` 组件，contract 筛选/审批驳回同款），列出全部状态变更动作
- **破坏性操作红色区分**：终止合同/归档合同在面板内红色标出（`.m-sheet-item-danger`），普通动作（标记完成/标记到期）默认深色，反向纠错（取消归档/取消完成/取消到期/取消终止/恢复执行）中性色——降低误触风险
- **交互链路**：更多 → 面板滑出 → 点动作 → 面板自动关闭 + mConfirm 确认弹窗 → 执行；遮罩点击/关闭按钮/「取消」项均可关面板
- **文案语义**：正向状态动作用动作式文案（标记完成/标记到期/终止合同/归档合同）消除"已X"歧义；反向保留取消归档/取消完成等
- **验证**：真实浏览器 5 状态实测——EXECUTING（标记完成/标记到期/终止红/归档红）、ARCHIVED（取消归档）、COMPLETED（归档红+取消完成）、TERMINATED/EXPIRED 同理；点「终止合同」→ 面板关闭 + 确认弹窗"确认将合同状态变更为「终止合同」？"；面板取消/遮罩关闭正常；`.m-page.detail` 留白回调 76px；四门禁全绿（PHPUnit 43·89）；PC 端无同类状态动作组（仅归档/续约两按钮，无歧义）
- 中间过程（v1 网格 → v2 stacked 容器 → v3 动作面板）经验：fixed 悬浮操作栏内多行布局必须整体放容器内，否则被遮挡；状态动作低频操作应收进动作面板而非平铺

### 移动端原生弹窗清零 + 续约跳转修复（用户反馈 2026-08-03）
- **移动端 8 处原生 confirm/prompt 清零**（根因：原生弹窗在钉钉 webview 被禁用/无反应 → 用户感知"点击没反应"）：finance.php 6 处（发票通过/驳回/撤回/重新提交/删除确认 + 合同搜索选择 prompt）、contract_detail.php 1 处（续约 confirm）→ 全部改用 mobile-common.js 既有 `mConfirm`/`mPrompt` 自定义弹窗（项目早已为规避 webview 问题而实现，但这两页漏改）；移动端原生弹窗全量复查 0 残留
- **移动端续约跳 PC 修复**：`/ajax/contract/<id>/renew` 后端返回 PC 路径 `/contract/<id>/edit`，mRenew 直接跳转导致移动端续约跳到 PC 版页面 → 改跳移动端 `/m/contract/<id>/edit`；同时确认移动端无其他"后端返回 PC url 前端直跳"模式、无 PC 路径 href、无 window.open
- **验证**：jsdom 实跑（mConfirm 弹出+确定回调、mPrompt 输入值回调、mApprove 驳回先弹意见弹窗）；真实浏览器（EXECUTING 合同续约按钮 → mConfirm 弹窗正常弹出）；移动端 17 页全量 JS 语法核验 0 失败；四门禁全绿（PHPUnit 43·89）

## v2.38.8（2026-08-03）— PC 端 UI 视觉体系重构（对标移动端）+ 两轮质量复查修复（MINOR）

### PC 端 UI 视觉底座（P0，方案 outputs/pc_ui_style_plan.md，以移动端设计体系为基准）
- **设计 token 完整化**：`app.css :root` 从 7 变量扩展至与移动端同源（--primary/--brand-light/--bg-page/--text-main/--text-2/--text-3/--line/--success/--danger/--warn/--radius-card 14px/--radius-sm/--shadow-card/--shadow-pop），PC 语义别名指向 mobile.css 真源
- **品牌顶栏**：新增 `.navbar-app`（品牌蓝 #0b5ed7 实底 + 白字 + 轻阴影，复刻移动端 .m-nav），替换 `navbar-dark bg-primary`（Bootstrap 默认蓝 #0d6efd ≠ 品牌色）；header.php 内联 style 双源重复治理（stat-card/stat-icon/party-* 迁入 app.css）
- **状态标签全站浅底收敛**：合同/审批/回款/财务/客户/项目/供应商/仪表盘等全部实底 `badge bg-*` → 浅底 `pc-tag pc-tag-*`（与移动端 .m-tag 同色：ok/warn/danger/info/muted）；仅「未读数字角标」（今日提醒/审批消息）保留实底 pill 强强调；动态类（项目状态 $stBg、相对方类型）同步映射；pc-tag 冗余混合类（bg-opacity-10/text-*）清理
- **卡片/模态圆角统一**：.card/.stat-card/.modal-content 12px → 14px（对齐 --m-radius）；卡片阴影轻量化 --shadow-card、hover 浮起 --shadow-pop
- **硬编码色 token 化**：视图层 style/<style> 上下文中 #0b5ed7/#8a9099/#07c160/#fa5151/#333/#999/#dc3545/#374151 等 20 类色值 → var(--m-*/--x)（7 文件 60+ 处；JS 逻辑色保守不动）
- **验证**：四门禁全绿（parity/comments/view_globals/PHPUnit 43·89）；12 页双角色走查（品牌顶栏+pc-tag+无残留实底状态 badge 全 OK）；jsdom 5 页回归（finance/合同详情/客户/审批/合同列表）10/10 零 JS 错误

### PC 端 UI 组件与交互对齐（P1/P2，2026-08-02，pc_ui_style_plan.md）
- **筛选芯片化（P1-1）**：项目管理/供应商管理状态与类型筛选由 `select` → 胶囊 chip 条（`.pc-chips`，对齐移动端 `.m-chip`，active 品牌蓝实底）；project 点击 chip 写 hidden 触发表单加载、supplier 走 GET 链接保留关键词
- **驾驶舱与表格（P1-2/3）**：dashboard 趋势柱状图渐变 token 化（--primary→--brand-light）；表格密度统一（padding 0.6rem、表头 13px 深灰、分隔线 --m-line、hover --brand-light）；金额列 `.text-num` 等宽数字对齐
- **原生弹层全量替换（P2-1）**：新增全局 `window.pcConfirm/pcPrompt`（Promise 风格 Bootstrap Modal，对齐移动端 m-sheet 质感，app.js 注入）；**全站 44 处原生 confirm/prompt 清零**（合同详情归档/删除/续约/回款确认/发票红冲作废删除、审批驳回、后台用户/角色/自定义字段/联动规则、客户合并/联系人、提醒续约等 6 视图）；删除类确认带 danger 红按钮
- **表单/空态/暗色（P2-2/3）**：`.form-control:focus` 品牌蓝描边（--primary + weak 光环）；`.pc-empty` 空态组件；**PC 端暗色模式**（@media prefers-color-scheme: dark 全量 token 覆盖：页面/卡片/侧栏/表格/标签/芯片；移动端钉钉 webview 保持 light 防反色）
- **验证**：10 页真实渲染 JS node 语法核验全过；jsdom 13/13（project chip 交互、pcConfirm/pcPrompt 弹窗创建/标题/danger 按钮/resolve 值、各页零 JS 错误）；E2E 主链路冒烟 5/5（发票申请提交+税率随主体）；四门禁全绿
- **修复（2026-08-03）：P0-P2 视觉样式未生效**——app.css（含全部 token/品牌顶栏/pc-tag/暗色模式）**从未被任何页面引入**（header.php 仅引 mobile.css），P0 把顶栏 `bg-primary`→`.navbar-app`、badge→`pc-tag` 后类名在但样式缺失，页面主题色全失。已补 `header.php` 引入 `css/app.css`；真实浏览器 getComputedStyle 验证：PC 顶栏 #0b5ed7、pc-tag 浅底深字、移动端仍只引 mobile.css（m-* 体系不受影响）；四门禁全绿。**教训：视觉类改动必须验证 CSS 文件实际加载（link 存在 + 计算样式生效），仅断言 HTML 类名不够**
- **复查修复（2026-08-03，outputs/p012_quality_review.md）**：①JS 动态 badge 残留收敛——project.js/resource.js/form-builder.js 的实底 `badge bg-*` → `pc-tag-*`（P0 只收敛了视图层静态类，JS 字符串模板漏网；选中人员 chip 与未读角标保留实底属合理）；②破坏性操作补 danger 红按钮 11 处（合同详情草稿删除/回款删除/撤销/红冲/作废/发票删除、后台禁用用户/删除角色、财务红冲/作废/删除）；③暗色模式覆盖补全——pc-tag 全色系暗色浅底 + Bootstrap 表单/分页/下拉/步骤条 + `bg-white`（21 页 56 处）+ dashboard 回款语义行（暗色块 28→41 条规则）；④project chip 筛选 active 高亮不同步修复（load() 同步 active 与条件）。验证：jsdom 16/16（组件全场景+4 页零错误）、真实浏览器（删除弹窗 btn-danger、chip 高亮跟随+动态 pc-tag 浅底）、E2E 7/7、四门禁全绿
- **二轮深度复查（2026-08-03 用户追问"确定完全修复"）**：①**归档页 JS 崩溃（历史遗留真实 bug）**——archive/index.php 内联 JS `href=\\'` 转义渲染后非法，归档列表加载不出，已改双引号修复；②**原生 alert 清零**——P2 只处理 confirm/prompt 漏了 alert（contract/create 3 处、项目/供应商/客户/审批表单 4 处、登录 1 处）→ showToast/轻量 toast，仅保留 2 处带 fallback 的防御写法；③**pcConfirm 真实双链路**——取消不误删（发票仍在）、合法链路「撤回→删除」5/5（PENDING 不可删是后端正确业务规则+前端按钮已按状态显示）；④**全量核验**：34 PC 视图 php -l + 真实渲染 JS node 核验全过、24 移动页走查（m-* 体系无 app.css 污染、业务拒绝正确）、暗色规则 28 条选择器命中真实元素。四门禁全绿（PHPUnit 43·89）

## v2.38.7（2026-08-02）— 发票三段式审批 + 配置化表单设计器 + 开票税率绑定主体（MINOR）

### 发票三段式重构（用户需求 2026-08-02：申请→审批→财务开票 + 独立入口 + 钉钉式表单配置）
- **流程重构**：发票由「直接开票」改为「**申请→审批→财务开票**」三段式。新增 `invoice:apply`（申请开票，普通用户默认授权）与既有 `invoice:create`（开票/红冲/作废/删除，财务/经理/法务）分离；`approval_flow`/`approval_instance` 新增 `biz_type`（contract/invoice）与 `target_id`，审批引擎按业务类型分流（发票审批通过→APPROVED 待开票，不进入合同状态机）；发票状态机重构 `PENDING_APPROVAL→APPROVED→ISSUED→RED/VOID`，`REJECTED` 可改重提、`CANCELLED` 可撤回，旧 `APPLIED` 历史态只读兼容
- **独立入口**：侧边栏新增「**发票申请**」顶级菜单 → `/invoice-apply`（我的申请/待我审批/待开票三 tab + 快捷申请弹窗 + 开票弹窗）；工作台「最近合同」卡片新增「申请开票」快捷按钮；财务中心发票 tab 与合同详情发票区同步新状态机；默认发票审批流（`INVOICE`，财务单节点）随迁移种子
- **表单设计器（钉钉式）**：新表 `invoice_form_field` 字段池（8 个系统预置字段：开票主体/开票内容/发票类型/金额/税率/抬头/税号/说明），后台「系统设置→发票表单」可启停/排序/编辑标签必填/新增自定义字段（系统字段禁删），`InvoiceFormConfig` 单源驱动 PC+移动双端申请表单渲染
- **开票主体与内容**：发票表新增 `our_company_id`（开票主体，我方公司）+ `content_desc`（开票内容/品目）+ `applicant_id`/`approval_instance_id`/`issued_by`；关联合同可选（选合同校验合同状态/金额上限，未选可快捷申请）
- **迁移**：`database/migration_v2.38.7_invoice_approval.sql`（approval 两表加列、contract_invoice 加 5 列+索引、新表 invoice_form_field+种子、发票审批流、invoice:apply 权限按 code 关联授权）

### 字段联动组件（用户需求 2026-08-02：不同主体联动不同开票内容，通用组件复用未来审批表单）
- **通用联动机制**：新表 `form_field_linkage`（form_key/trigger_field/trigger_value/target_field/action/options）——表单无关，任何配置化表单（发票申请/未来审批表单）换 `form_key` 即可复用；三脚本 + migration 同步
- **前端通用引擎 `form-linkage.js`**：读 `window.__formRules` 自动初始化，支持三种动作——`options`（触发值命中替换目标 select 选项，失配恢复字段自身选项）、`show`/`hide`（显隐目标字段行）；同 target 多条规则按「最后命中者胜」汇总应用（避免失配恢复覆盖命中替换）；PC/移动端通用
- **设计器可视化配置**：后台「系统设置→发票表单」新增「字段联动规则」卡片（规则列表 + 新增/编辑/删除弹窗：触发字段/触发值/动作/目标字段/联动选项），随「保存字段配置」一并提交，后端按 form_key 全量重存 + 字段存在性/动作白名单校验
- **双端生效**：PC 申请弹窗与移动端申请弹层均注入规则 + 引入引擎；示例配置：开票主体=1 → 开票内容选项（软件开发/咨询服务），主体=2 → 选项（租赁/运维）
- **一触发多目标联动（用户反馈 2026-08-02：同一主体同时联动税率+开票内容等多个下拉）**：联动弹窗升级为「触发条件 + 目标行为多行表格」（目标字段/动作/联动选项可增删行，同目标去重），一个触发值可同时配置多个目标字段，保存展开为多条平铺规则（引擎/数据结构零改动，通用）；规则列表按触发组聚合显示（一行多目标 badge）
- **税率字段下拉化**：`tax_rate` 预置字段类型 number→select（3%/6%/9%/13% 预置选项），设计器字段池/属性面板同步——税率作为下拉可被 options 联动替换；`InvoiceController::add` 兼容（小数税率值原校验即放行）
- **联动保存防呆**：`FormBuilderController::saveForm` 新增校验——「替换选项」动作的目标字段必须为下拉类（select/company），否则拦截并提示「请在字段画布中把该字段类型改为下拉选择」（避免配置了选项却不生效）

### 通用表单设计器（用户需求 2026-08-02：两阶段配置 + 钉钉式所见即所得，通用组件）
- **两阶段向导**：发票表单配置拆分「Step1 表单设计 → Step2 审批与抄送」，保存当前步骤后自动进入下一步（`/admin/form-builder?form=invoice_apply`，步骤条 + 保存当前步骤按钮）
- **所见即所得画布（Step1，通用组件 form-builder.js）**：三栏布局——左「字段池」（8 系统预置字段 + 自定义字段类型白名单，点击添加）→ 中「表单预览」画布（与申请页一致形态实时渲染，点选字段高亮、上下移排序）→ 右「属性面板」（标签/类型/必填/启用/选项行编辑，实时重绘）；底部「字段联动规则」卡片（新增/编辑/删除，随表单保存）
- **审批与抄送（Step2）**：可视化编辑发票专用审批流节点（节点名/审批人类型[审批角色/指定用户/提交人部门负责人]/或签会签/增删节点）+ 抄送知会（角色 chips 多选 + 指定用户 chips）；保存写 `approval_flow`（biz_type=invoice，code=INVOICE，无则创建）
- **通用架构**：`FormBuilderController` 由 `form_key` 驱动（字段表映射 FORM_TABLES 扩展点 + 联动表 form_key 维度 + 审批流 biz_type 维度）——未来审批表单登记映射即可复用同一页面/引擎/接口；原表格行式设计器（admin invoice-form tab）入口迁移至新设计器
- **所见即所得预览（用户反馈 2026-08-02：原「预览申请页」误跳转发票申请页）**：改为页内「预览表单」弹窗——渲染**当前画布字段**（未保存的编辑也实时可见，可交互控件带 name），「电脑 / 手机」双形态切换；预览容器注入当前联动规则并初始化 `FormLinkage`，切换触发字段（如开票主体）→ 税率+开票内容等目标字段**即时联动**；公司主体下拉使用真实公司列表（控制器注入）
- **验证**：jsdom 设计器 12 项交互全通过（画布回填/字段池添加/属性实时编辑/联动配置/Step2 节点抄送回填/保存请求体）；E2E 闭环——save-form 加自定义字段+联动 → save-flow 财务节点+抄送 → 申请页渲染新字段 → 员工申请自动走配置的 INVOICE 审批流；四门禁全绿，测试数据已清理
- **预览验证**：jsdom 实跑——预览弹窗渲染 8 个可交互字段、公司下拉 3 项（请选择+2 公司）；配置多目标联动后预览中切主体 1 → 税率(6%/13%)+开票内容(软件/咨询) **即时同时联动**，清空恢复自身选项；切「手机」形态 8 个 .m-field 且联动同样生效；零 JS 错误

### 公司开票流程优化（用户提供公司真实申请流程 2026-08-02：含税金额→开票类型→开票公司→开票内容→对方开票信息→按公司分支审批抄送）
- **表单贴合流程**：`amount` 标签改「含税金额（元）」；`invoice_type` 选项改「我要开增值税专用发票 / 我要开普通发票」两选项（字典同步，历史 E_INVOICE 兼容）；新增预置字段「**开票客户**」（customer 类型：客户下拉，选择后自动带出对方开票信息）；申请弹窗/移动端弹层新增**价税实时拆分展示**（含税 ¥A = 不含税 ¥B + 税额 ¥C，金额/税率变化即时刷新）
- **客户信息复用**：`contract_invoice` 加 `customer_id`；联动引擎新增 **fill 动作**（trigger_value 支持 `*` 任意值命中，options 配 `source_field`，从 `window.__formData[触发字段]` 数据行取值填充目标字段）——设计器配「选客户 → 抬头=客户名(name)、税号=信用代码(credit_code)」即可自动带出，可手动修改；PC/移动端双端注入客户数据源
- **审批按开票公司分支（H4）**：`approval_flow` 加 `form_condition`（JSON [{field,value}]，空=默认兜底流）；`matchInvoiceFlow($invoice)` 条件匹配（全命中才命中，回退无条件默认流）；设计器 Step2 升级为**多流程条件分组**——默认流程（兜底）+「添加条件分支」（条件字段/值 + 节点 + 抄送，可增删），保存为多条 INVOICE 流（code 唯一），旧流软停用不删除（进行中审批实例按 flow_id 回溯节点定义不受影响）
- **修正**：`optionList` 二次转换 numeric 字符串键（如税率 '0.03'）被当简单列表致 value 变 label（价税展示暴露）——判定改 `is_string($k) && $k !== ''`，幂等
- **迁移**：migration_v2.38.7 追加第 9 节（form_condition/customer_id 列 + 字段文案 + dict + customer_id 种子，幂等 UPDATE/INSERT）

### 功能完整性（P1，7/7 完成）
- **PC 合同列表状态筛选补全 10 态**：此前仅 4 态（草稿/待审批/已签署/执行中），现与状态机单一真源 `ContractLogic::STATUS_LABELS` 同源渲染全部 10 态（含已驳回/已完成/已到期/已终止/已归档），后端过滤本就支持，补齐前端下拉
- **全文检索加概要正文**：列表关键词检索在标题/合同号/关键词之外，追加 `content_plain`（概要纯文本）LIKE 匹配（where 闭包 + whereOr 显式分组，避免隐式优先级歧义）
- **重复合同检测**：新建/编辑合同时按「标题 + 甲乙双方 + 金额」完全一致拦截，提示既存合同号；防呆而非强禁（确系新合同改标题/金额后重提即可）
- **逾期自动置 OVERDUE 命令**：新增 `php think payment:mark-overdue`（crontab 每日）——将「待收(PENDING)且计划回款日已过」自动置逾期，复用 `PaymentLogic::markOverdue`（状态+客户信用重算同事务）并写审计；统一账龄/信用/提醒三处口径（此前仅手动标记，未标记的逾期不计入账龄与信用评级）
- **SLA 双超时机制统一单源**：`approval:escalate`（processOverdueApprovals）原用全局 72h，与 `approval:sla-check` 的节点级 `timeout_hours` 阈值不一致（会签节点双命令重复催办/阈值漂移）。现统一**节点级 `timeout_hours` 优先**，全局 `approval_and_timeout_hours` 仅作节点未配置时的降级兜底，两命令口径一致
- **提醒天数 dispatch/scanAlerts 读配置生效**：`RemindService::dispatch()`/`scanAlerts()` 原硬编码 30/15/7/3/1 与 7/3/1，改走私有 `remindDays()` 单源读 `sys_config`（rule_expire_remind_days / rule_payment_remind_days），与 `check()` 口径统一——后台改配置后钉钉推送/提醒角标即时生效

### UI/交互（P1）
- **合同列表高级筛选抽屉化**：11 个筛选条件收敛为「关键词 + 状态 + 高级筛选按钮」主行，其余 9 项（分类/方向/性质/类型/项目/金额区间/相对方/签约主体/归属人/日期区间）移入 Bootstrap offcanvas 抽屉（字段仍在 `#searchForm` 内，JS 自动收集，抽屉内提供「重置」「应用筛选」）；移动端更友好
- **列表首屏骨架屏**：替换原 spinner（loading 转圈），5 行 shimmer 骨架 + `prefers-reduced-motion` 降级，加载完成/失败自动替换，消除空白感与"永久转圈"观感
- **PC 财务中心侧边栏补「发票管理」菜单**（用户反馈）：sidebar 财务中心分组原仅「回款管理」（发票入口 P0 恢复前端时漏了侧边栏）；现补「发票管理」子菜单（`/finance?tab=invoice`，按 `invoice:view` 权限显示），分组 gate 放宽为 `payment:view OR invoice:view`；FinanceController::index 传 `tab` 供子菜单 active 高亮（回款/发票互斥高亮）
- **财务中心发票列表合同列「标题主显示」**（用户反馈，与回款列表对齐）：原为「编号 + 标题」单格；改为「标题（链接进合同详情）主行 + 编号小字次行」，一眼识别合同

### 验证（2026-08-02 全部通过）
- `php -l` 9 个改动文件 0 错误；三门禁全绿：check_schema_parity（30 表/295 列 1:1）、check_view_globals（65 视图）、check_db_comments（0 缺失）、PHPUnit 43/89 通过
- 接口走查 10/10：admin 登录、页面结构（10 态/抽屉/骨架屏）、content_plain 检索「试模」命中、status=ARCHIVED 筛选、重复合同拦截（提示既存合同号）、非重复新建正常且清理
- jsdom 实跑合同列表页：零 JS 错误，骨架屏替换/空态渲染/resetFilters/表单提交全部正常
- jsdom 实跑财务中心发票 tab：零错误；侧边栏双菜单（回款管理+发票管理）渲染、发票面板激活、发票行「标题主显示+编号次行」正确
- **发票三段式 E2E 12/12**：员工申请（快捷+关联合同）→PENDING_APPROVAL+审批实例 biz_type=invoice → 员工越权开票 403 → 财务待审批列表 → 审批通过 APPROVED → 开票 ISSUED（发票号+开票人）→ 红冲 RED+红字负数关联 → 超合同金额上限拦截；测试数据全清理
- **jsdom 4 页批量**（PC 发票申请页/财务中心/后台设计器/移动端 finance）零 JS 错误：申请弹窗字段渲染、三 tab、设计器 8 字段行、移动端发票面板与 chip 切换全正常；自定义字段闭环验证（后台新增→申请表单即时渲染→清理）
- **联动组件验证**：jsdom 实跑「开票主体→开票内容选项」联动——主体 1→(软件开发/咨询)、主体 2→(租赁/运维)、切回恢复、未匹配回退自身选项全通过；show/hide 与 options 并存联动正确；测试规则已清理
- **多目标联动验证（v2.38.7 优化后）**：jsdom 设计器——新建弹窗默认 1 行目标、添加至 2 行、保存后按触发组聚合显示（2 目标 badge）、编辑回填 2 行（targets/选项正确）、删除一行后剩 1 目标；申请页——切换主体 1 → **税率(6%/13%)+开票内容(软件/咨询)两个下拉同时联动**、主体 2 → 税率 9%+内容(租赁/运维) 同时变、切回恢复、清空回退字段自身选项，零 JS 错误；接口校验——options 目标为非下拉（invoice_title）被拦截提示改类型
- **公司流程 E2E 10/10 + jsdom**：E2E——save-flow 多流程组（默认=财务+抄送、公司1=财务、公司2=部门经理+抄送）三流落库 → 员工公司1申请走 `INVOICE_our_company_id_1` 流、公司2申请走 `INVOICE_our_company_id_2`（manager 节点）流 → customer_id 落库 → 清理复位；jsdom——设计器 Step2 默认组回填/添加条件分支(our_company_id=2)/条件组加节点/save-flow groups 请求体正确；申请页选客户自动带出抬头（税号无信用代码留空）、价税实时展示「含税 1,060.00 = 不含税 1,000.00 + 税额 60.00（税率 6%）」；零 JS 错误
- 命令实跑：临时造「已过计划日 PENDING」记录 → `payment:mark-overdue` 置 OVERDUE + 审计留痕，临时数据已清理

### 开票税率绑定开票主体（H6，用户需求 2026-08-02：税率直接在选择公司主体时绑定，无需单独税率组件）
- **数据层**：`company_profile` 新增 `invoice_tax_rate`（开票税率，0=免税，默认 6%）；三脚本 1:1 + migration_v2.38.7 追加第 10 节（幂等：加列 + 演示主体税率 UPDATE + 表单税率组件停用）；init_mysql.php 补齐 invoice_form_field 种子（此前仅 sqlite/init.sql 有，MySQL 新装库配置表为空会回退预置池）
- **后台配置**：公司主体管理页新增「开票税率」列与表单下拉（0%/1%/3%/5%/6%/9%/13%，2026-08-02 扩充 1%、5% 常用档），保存接口白名单校验（0≤rate<1）；开票申请选主体后自动带出该税率
- **表单移除税率组件**：`invoice_form_field.tax_rate` 停用（enabled=0 不渲染）；`InvoiceFormConfig` 兜底预置池与渲染层双重过滤（tax_rate 永不渲染，即使旧库未停用）；申请表单内保留隐藏 `tax_rate` 字段承接主体税率（前端价税拆分读取），**后端 `InvoiceController::add` 强制从开票主体读取税率**（不信任表单提交值，防篡改/防旧客户端漏传）
- **合同详情申请开票对齐（H6b）**：合同详情「申请开票」弹窗移除独立税率下拉（改只读展示「X%（随主体）+ 引导到公司管理配置」）、补「开票内容」必填（预填合同标题）、提交携带合同 `our_company_id`（此前缺失会报"请选择开票主体"；税率下拉亦已失效——后端改从主体读后选择无意义）
- **合同详情复用配置化表单（H6c，用户确认"复用发票表单更合适"）**：合同详情「申请开票」弹窗改为 **`InvoiceFormConfig::pcRender` 配置化渲染**（与独立入口 /invoice-apply 单源一致）——字段/联动/自定义字段由后台「系统设置→发票表单」设计器统一维护，一处配置双端生效；手写字段（金额/内容/类型/抬头/税号/备注）全部移除；弹窗内开票主体默认合同主体（可切换，option 带 data-rate 自动带出税率+刷新价税拆分）、开票内容预填合同标题、关联合同固定显示当前合同（只读）、引入 form-linkage.js 联动（选主体→内容/选客户→带出抬头税号）；提交仍走 /ajax/invoice/add（后端按 contract_id 校验状态与金额上限）
- **验证**：E2E 16/16（公司管理页 1%/5% 档、保存税率 1%→DB、合同详情无税率下拉/只读 6%/主体 hidden/提交→DB 0.06 税额 60、清理还原）；jsdom 10/10（合同详情弹窗提交 body 带 our_company_id+content_desc 且不带 tax_rate、零 JS 错误）；四门禁全绿（PHPUnit 43·89）
- **前端联动**：PC/移动端公司下拉 option 渲染 `data-rate`，切换开票主体即时带出税率并刷新价税拆分展示（PC `refreshApplyCompanyRate` / 移动 `refreshMInvRate`，替代原税率下拉变更监听）
- **验证**：E2E 15/15（后台税率列/保存→DB、申请页无税率组件+data-rate、公司2申请不传税率→DB 0.09/税额 90、公司1→0.06/税额 60、清理还原）；jsdom 18/18（PC+移动：无税率 select、切主体税率带出 0.13/0.06、价税 1130=1000+130(13%)、零 JS 错误）；四门禁全绿（parity/comments/view_globals/PHPUnit 43·89）

## v2.38.6（2026-08-01）— 交付级审查达标 + 功能补全与四轮修复（MINOR）

### P0 修复（2026-08-02，三维度深度审查后）
- **发票模块前端恢复（最重要）**：此前后端 API 完备但前端入口全部隐藏（详情/财务中心"已隐藏，后端保留"）——用户无法开票。已恢复合同详情发票区（列表+开票合计+申请开票/开票/红冲/作废/删除）+ 财务中心「发票管理」tab（跨合同发票查询+操作+税务汇总入口）；E2E 申请→开票→红冲→作废全链路 PASS
- **归档按钮按可用动作渲染**：改用 `ContractLogic::getAvailableActions()`（此前 PENDING_APPROVAL 等不可归档状态也显示归档按钮，点击必失败）
- **归档后财务口径统一**：确认收款增加归档校验（与新增回款/开票一致，归档合同不再有财务活动）
- **状态字面量收敛**：Contract/Mobile/Approval/Archive 四控制器合同状态字面量全量替换 `ContractLogic::STATUS_*` 常量
- **PC 设计 token 底座**：app.css 建立 `:root` token（--primary/--text-muted/--bg-page/--radius-card 等），header.php 内联样式硬编码色收敛；删除 app.css 与 header 重复的 sticky sidebar 死代码；合同列表状态标签统一浅底深字 `.pc-tag`（与详情/移动端对齐）
- ContractLogic 8 个查询转发桩保留（门面转发为合法模式），补充注释避免误判

### UI/体验（2026-08-01 用户需求 4 项）
- **移动端财务统计顶部布局优化**：回款预测卡由竖排三行改为「左大数字 + 右标签列」横排紧凑布局，与收支概览（销售应收/采购应付）间距拉开，顶部三块数字不再拥挤（`mobile/finance.php` + `mobile.css`）
- **PC 仪表盘近期回款以合同标题为主**：数据层 `ReportLogic` 对 `contract_title` 兜底归一化（title 优先→合同编号），视图 fallback 链加固，旧缓存结构下也保证标题优先
- **移动端资料库底部菜单修复**：`mobile/resource.php` 补 `_foot.php` 引入（此前缺底栏，与其它模块不一致）；`doc_preview.php` 全屏预览页保持无底栏
- **PC 后台「业务规则」设置项**（系统设置→系统配置）：新增公海客户自动释放天数（`rule_pool_release_days`）、合同到期提醒提前天数（`rule_expire_remind_days`）、回款到期提醒提前天数（`rule_payment_remind_days`）三个可配置项；`customer:pool-release` / `remind:check` / `remind:dispatch` 读取配置，CLI 传参可临时覆盖；保存即清 `sys_config` 缓存即时生效。三脚本 + `migration_v2.38.5_rules.sql` 同步

### 遗留问题修复（2026-08-01 第二轮，见 outputs/audit_redo_v2.38_20260801.md 第八节）
- **M8 信用评级**：加 `customer.credit_manual` 人工锁定列（改过评分即锁定，自动重算跳过评分/等级，high_risk 仍客观）；confirm/revoke/delete 补重算恢复路径；markOverdue 状态+重算同事务；软删合同逾期不再计入（`migration_v2.38.6_credit_manual.sql`）
- **M10 流程匹配**：非交易合同跳过金额条件按 id 序匹配（原 amount=0 无法命中金额流程）
- **M11 SLA**：sla-check 每实例事务+并发复核；OR 节点跳过升级（消除与 escalate 自动通过双发）
- **M13 表单配置**：标量选项 value=label=自身；未知 type 抛异常暴露配置错误（顺带补移动端 parent_search 渲染缺口）
- **质量项 11 项**：saveFlow nodes JSON 校验、reject_to_order 白名单、FQN→self、财务预测 0 刷新、账龄橙色内联、claim 上限常量+事务复核、getDashboard 过滤非交易、死代码常量清理、抄送角色多选、同意弹窗防重、客户详情先鉴权后聚合

### 缺陷修复（2026-08-01，E2E 暴露）
- **【严重】站内提醒引擎从未落库**：`RemindService::shouldRemind` 调用了 think-orm v1.x 不存在的 `insertOrIgnore()`，每次抛 `BadMethodCallException` 被 catch 吞掉返回 false——`remind:check`/`remind:dispatch` 跑多年报 0 条，remind_log 仅剩种子数据。已改为原生 `INSERT OR IGNORE`（SQLite）/`INSERT IGNORE`（MySQL），按 `Db::getConfig('default')` 判断驱动，保留 uk_remind_dedup 原子去重；E2E（配置 1 天提前→隔离合同→remind:check→断言 expiry_1d 落库）PASS
- 提醒天数解析容错：逗号分隔非法项自动过滤，空配置回退原默认（30,15,7,3,1 / 7,3,1）

### 安全与功能补全（此前攒批并入）
- **签署功能彻底移除**（v2.38.4 迁移）：SignController/SignLogic/sign 视图/路由/permission 26·27/sign_task 表/contract.sign_date 列/dict 种子全清，三脚本 30 表/294 字段（`migration_v2.38.4_remove_sign.sql`）；审批链不变（终审直接→EXECUTING）
- **续约越权修复**：`renew()` 加 `canAccessRecord` 数据范围校验 + 防重复续约 + 事务 + 补字段复制
- **M13 字段配置化 / M14 里程碑回款**（PC + 移动端「复制自上期」）：见 `outputs/redo_gap_analysis.md`
- **全量审查 P0/P1 修复 10 项**（H1 XSS、H2 联系人越权、H3 审批节点索引、M2 发票状态机等）：见 `outputs/audit_redo_v2.38_20260801.md`

### 交付级审查修复（2026-08-01 第三轮，见 outputs/delivery_audit_20260801.md）
- **移动端财务页内联 JS 语法错误修复**：上轮「预测为 0 时刷新」改动把 `//` 注释插在 `if(fEl)` 与 `{` 之间吞掉大括号 → 整段 JS 失效（回款列表/登记/复制上期不可用）；已修正闭合并同步横排布局，jsdom 复验 PASS
- 交付审查结论：页面矩阵 0 FAIL、AJAX 82/82、E2E 主链路 24/24、jsdom 12/12、三门禁全绿，**系统达到交付水平**

## v2.38.2（2026-07-31）— 四大模块审查优化 + 客户功能完善（PATCH）

### 客户功能完善（公海+去重+信用）
- **公海自动回落**：`CustomerLogic::autoReleaseStale(N)` + `php think customer:pool-release`
- **客户去重合并**：名称归一化+信用代码检测+一键合并（合同/跟进迁移）
- **共享可见性**：合同引用者可查看客户详情
- **信用评级**：customer 新增 credit_limit/credit_score + 按逾期自动计分
- **来源字段**：PC/移动端表单 source 下拉

### 审批增强
- **金额条件自动跳过**：`filterActiveNodes()` 按节点 amount_min/amount_max 过滤
- **SLA 超时自动升级**：`php think approval:sla-check` 催办+自动升级
- **抄送通知时机修正**：提交时→全部审批通过后
- **PC/移动提交页显示抄送人**

### 移动端修复
- **财务页加载中卡死**：内联脚本在 app.js 前执行导致 $ajax 未定义→DOMContentLoaded 延迟
- **客户/供应商/公海池标签统一**：三页 chip 顺序一致+返回路径统一 `/m`
- **移动端 500**：ApprovalLogic::class 遗留引用修复

### PC 端优化
- 审批同意弹窗 Bootstrap Modal（替代黑色原生 alert）
- 提醒角标动态更新（notification.js 完全重写）
- 新建合同向导 4→2 步+方向默认销售+金额空默认+自适应 col

## v2.38.1（2026-07-31）— 全面审查优化修复 + PC 审批弹窗协调（PATCH）

### P0-1 移动端报表 403 — 权限码不存在（高优先级）
- **现象与根因**：MobileController::reports / reportsSummary 使用 `requirePermission('report:view')`，但 `report:view` 权限码在种子表中不存在、未授予任何角色，导致除超管外所有用户访问 `/m/reports` 与 `/ajax/mobile/reports-summary` 均 403。PC 端 `/report/monthly` 用 financeGate（payment:view/invoice:view）正常。
- **修复**：MobileController 的 `reports()` 与 `reportsSummary()` 改为 `requireAnyPermission(['payment:view','invoice:view'])`，与 PC 端 ReportController::financeGate 统一门槛。
- **验证**：php -l 全绿；schema parity(29/282) ✓；db comments(282/0) ✓；view-globals(64) ✓；PHPUnit 43/89 ✓。

### P0-2 admin 用户管理 tab 防 esc 加载时序中断（高优先级）
- **现象与根因**：jsdom 实跑 admin?tab=user 捕获 `esc is not defined`。虽实际浏览器中 app.js 正常加载，但以防 app.js 因 CDN 延迟/HTTP2 push 重排等使内联脚本先于 app.js 执行，所有使用 `esc()` 的内联脚本都会抛错。
- **修复**：在 `admin/index.php` 公共脚本块顶部增加 `esc` fallback：`if(typeof window.esc!=='function') window.esc=function(s){return s==null?'':String(s);};`。app.js 正常加载时 fallback 不生效（`window.esc` 已是函数）；仅在 app.js 异常时才介入，零业务影响。
- **验证**：php -l 全绿；视图不引入新全局符号（check_view_globals 仍通过）。

### P0-3 财务角色数据范围 SELF→ALL（高优先级）
- **现象与根因**：种子数据中财务角色 `data_scope=SELF`，导致 finance01 无法为非本人创建的合同添加回款（业务上财务应能处理全公司回款）。
- **修复**：
  - `init_sqlite.php`/`init_mysql.php`/`init.sql` 三脚本财务角色 data_scope 从 SELF 改为 ALL；注释同步「财务(SELF)→财务(ALL)」。
  - 新增 `database/migration_v2.38.1_finance_scope.sql`（MySQL 迁移，`UPDATE role SET data_scope='ALL' WHERE code='finance'`），生产需执行。
  - dev SQLite 库直接 UPDATE。
- **验证**：schema parity(29/282)+种子 1:1 ✓；db comments(282/0) ✓；PHPUnit 43/89 ✓。

### P1-1 合同保存越权校验前置（中优先级）
- **现象与根因**：员工 POST 修改他人合同时，先返回「请输入合同概要」「请上传附件」等业务校验错误，而非 403。数据未被篡改，但存在规则探测与 UX 误导。
- **修复**：ContractController::save 在 `requirePermission` 之后、业务校验之前增加早期所有权检查：`AuthLogic::canAccessRecord($existing['creator_id'], $existing['dept_id'] ?? null)`，不通过直接返回 `json_error('无权修改该合同', 403)`。
- **验证**：curl 员工越权修改 admin 合同→403「无权修改该合同」；员工创建合同→正常进入业务校验「请选择签约主体」；php -l 全绿。

### P1-2 统一权限门面 — financialGate（中优先级）
- **现象与根因**：PC 端 ReportController 自建 `financeGate`、MobileController 散落 `requireAnyPermission(['payment:view','invoice:view'])`，同一概念多处重复定义。
- **修复**：
  - `BaseController` 新增 `protected function financialGate()`，统一入口 `requireAnyPermission(['payment:view','invoice:view'])`。
  - `ReportController::financeGate` 委托至父类；`MobileController` 的 `finance/reports/reportsSummary/financeSummary` 调用点全部统一为 `$this->financialGate()`。
  - 未来新增财务/报表相关接口无需自行组合权限数组，调用 `financialGate()` 即可。
- **验证**：php -l 全绿；schema parity ✓；view-globals ✓。

### P1-3 拆分 admin/index.php 内联脚本（God File）（中优先级）
- **现象与根因**：`app/view/admin/index.php` 1119 行，含 7+ 个 tab 的 HTML 与大段内联 JS（审批流编辑器 ~260 行、用户+角色选择弹窗 ~132 行），回归测试成本高、代码冲突概率大。
- **修复**：
  - 审批流编辑器脚本（新/编辑/保存/删除/抄送面板）提取至 `/public/static/js/admin/flow-editor.js`（264 行），原 `<script>...</script>` 替换为 `<script src="/static/js/admin/flow-editor.js?v=2">`。
  - 通用选人弹窗 + 角色多选弹窗脚本提取至 `/public/static/js/admin/pickers.js`（137 行），同上替换。
  - 依赖不变：公共脚本块（allUsers/allRoles/esc 等）+ 页脚 app.js 仍提供全局上下文；外部脚本仅在相应 tab 内加载（受 PHP 条件分支控制）。
  - 文件行数：1119 → 728（-35%）。
- **验证**：php -l 全绿；view-globals 通过（公共符号白名单不变）。

### P2-1 移动端财务页 fetch → $ajax 统一（低优先级）
- **修复**：`app/view/mobile/finance.php` 中 4 处原生 `fetch()` 调用统一改为 `$ajax()`，利用全局 CSRF 自动附加 + loading 兜底 + toast 错误提示，消除手动 header 拼接。
- **验证**：php -l 全绿。

### P2-2 /m/finance XHR 优化（低优先级）
- **修复**：`MobileController::finance` 增加 `X-Requested-With: XMLHttpRequest` 检测 → 直接返回 JSON 摘要（`FinanceLogic::getSummary()`），避免 XHR 调用时渲染完整 HTML 页面。
- **验证**：php -l 全绿。

### PC 端审批页「同意」确认弹窗 + 颜色协调（用户报）
- **现象与根因**：PC 审批详情页点击「同意」按钮：① 无二次确认弹窗直接提交（误触风险高）；② 结果用原生 `alert()` 弹出，在暗色模式系统/DingTalk PC webview 中显示为黑色对话框，与系统白色主题极不协调。
- **修复**：
  - `app/view/approval/detail.php`：新增白色 Bootstrap Modal（`id="approveModal"`），与驳回弹窗风格一致，含「确认同意 / 取消」按钮；「同意」按钮改为 `data-bs-toggle="modal" data-bs-target="#approveModal"`，不再直接提交。
  - 结果反馈从 `alert(res.msg)` 改为 `showToast(res.msg, 'success'/'error')`（统一 toast 提示，已由公共 app.js 提供）。
  - PC 通用布局 `app/view/layout/header.php` 补 `<meta name="color-scheme" content="light">`，通知 DingTalk PC webview / Chromium 会话强制浅色模式，杜绝 webview 级别暗色反色把白底弹窗/toast 翻成黑色（与移动端 v2.37.5 修复同原理）。
- **验证**：php -l 两文件全绿；check_view_globals(64) 通过。零后端/DB 变更。

### 深度重构：ApprovalLogic 按职责拆分为 4 个服务（中优先级）
- **现状**：ApprovalLogic.php 1010 行 / 27 个方法，集提交流程、操作推进、超时处理、通知队列、列表查询于一体，职责混杂。
- **拆分方案**：
  - `ApprovalSubmitService`（312 行）：`matchFlow`, `submit`, `collectFlowApprovers`, `fireCc` — 流程匹配与提交
  - `ApprovalActionService`（429 行）：`action`, `recall`, `advanceAfterNode`, `processOverdueApprovals`, `andTimeoutHours`, `resolveApprovers` — 审批操作与超时处理
  - `ApprovalNotifyService`（71 行）：`queueNotify`, `flushNotify`, `sendNotifyWithRetry` — 钉钉通知+站内信队列
  - `ApprovalQueryService`（323 行）：`getDetail`, `getPendingList`, `getProcessedList`, `isParticipant`, `getFlowById` 等 14 个查询方法 — 审批数据读取
- **交叉引用**：跨服务调用已通过 FQN 引用重写，PHP 编译器天然兜底。
- **外部调用方更新**：6 个文件（ApprovalController, MobileController, ContractController, BaseController, AdminLogic, ApprovalEscalate），每个调用点已指向对应新服务类。
- **原 ApprovalLogic**：精简为仅保留 5 个常量（NODE_START/END/CC, MODE_AND/OR），无业务方法。
- **验证**：php -l 全量 ✓（含 4 个新服务）；PHPUnit 43/89 ✓；schema parity ✓；view-globals ✓。

### 审批抄送通知时机修正 + PC/移动提交页抄送显示（用户报）
- **问题 1：抄送通知在提交时即发送**：v2.38.0 中 fireCc 在 submit() 阶段触发，抄送人审批刚提交就收到钉钉通知——但用户期望抄送应知会审批**结果**而非审批启动。
- **修复 1**：`fireCc()` 从 `ApprovalSubmitService::submit()` 中移除，改为在 `ApprovalActionService::advanceAfterNode()` 的「全部审批节点通过」分支调用——仅审批链全部完成才触发抄送。
- **问题 2：PC/移动审批提交页未显示抄送人**：`ApprovalController::create` 与 `MobileController::approvalCreate` 仅解析 `flow.nodes`（审批节点），完全忽略 `flow.cc_list`。
- **修复 2**：
  - 两控制器增加 `cc_list` 解析：按角色+用户去重映射姓名 → 传入视图 `cc_names`/`cc_roles`/`has_cc`。
  - PC `approval/create.php` 在审批节点列表下方新增「抄送知会」行。
  - 移动端 `mobile/approval_create.php` 在审批节点列表下方新增浅蓝底「抄送知会」面板。
- **验证**：php -l(6文件) ✓；view-globals(64) ✓；PHPUnit 43/89 ✓。

## v2.38.0（2026-07-31）— 抄送从审批节点链独立为流程级配置 + 审批节点删除按钮修复（MINOR，方案 A）

> 设计目标：从架构上根除「纯抄送人被当审批节点 / CC 与审批节点共用一套引擎导致身份泄漏」类耦合缺陷（v2.37.5 仅做了文案与去重补丁，未从根上解耦）。本迭代为独立 MINOR，不与紧急修复混批。

### 核心改动（方案 A：流程级独立抄送列表）
- **数据模型解耦**：`approval_flow.nodes` 仅保留审批节点（`ROLE`/`DEPT_LEADER`/`SPECIFIC_USER`），抄送不再作为 `type=CC` 节点入链；新增流程级 `cc_list`（`TEXT`，`{role_codes:[], cc_user_ids:[]}`，与 `nodes` 平级）；新增 `approval_cc_log` 表（抄送轨迹，含 `instance_id/user_id/node_order/role_codes/cc_user_ids/created_at`，索引 `idx_cc_inst`）。
- **引擎重构**（`app/common/logic/ApprovalLogic.php`）：
  - `submit()`：防御性剔除遗留 `type=CC` 节点（存量库未跑迁移时兜底）；改调 `fireCc()` 在**提交时一次性触发**流程级抄送知会，**不再占用节点序号、不写 `approval_record`、不参与节点推进判断**。
  - 删除 `autoProcessCc()`；`advanceAfterNode()`/`processOverdueApprovals()`/`collectFlowApprovers()` 去除全部 CC 特判（CC 已不进节点链，无需再"消费连续 CC 节点"）。
  - `fireCc()`：读 `cc_list` → `ApproverResolver::resolveRoleCodes()` 解析角色码用户 + `cc_user_ids` 求并集 → 与 `collectFlowApprovers()` 差集得纯抄送人 → 批量写 `approval_cc_log` + `queueNotify(..., TYPE_APPROVAL_CC)`。
  - 去重语义（同 v2.37.5）：同一人既是审批节点审批人又命中 `cc_list` → 仅收审批催办，抄送知会从 `cc_log` 剔除。
  - `getDetail()` 双数据源拼装时间轴（`approval_record` + `approval_cc_log`）；`isParticipant()` 纳入 `approval_cc_log`，保障纯抄送人详情查看权。
- **新增 `ApproverResolver::resolveRoleCodes(array)`**：按角色码经 `user_role` JOIN `role` 解析用户 ID 并集，供 `fireCc` 复用。
- **三脚本 1:1 同步**（`init.sql`/`init_mysql.php`/`init_sqlite.php`）：`approval_flow` 加 `cc_list` 列（中文注释）、新增 `approval_cc_log` 表；种子 `LARGE` 改为 2 审批节点 + `cc_list={"role_codes":["finance"],"cc_user_ids":[]}`，标准/简易 `cc_list=''`；表/字段中文注释 + `check_schema_parity`/`check_db_comments` 校验通过（29 表 / 282 字段）。
- **迁移脚本 `database/migrate_cc_independent.php`**：经 ThinkPHP `Db` 门面（DB 类型探测改走 PDO `ATTR_DRIVER_NAME`，兼容 MySQL/SQLite）；`ALTER` 加 `cc_list` + 抽 CC→`cc_list` + `nodes` 重排 + 建 `approval_cc_log`；幂等 + `--dry-run`。**生产部署须先执行此脚本做存量迁移**（本地 dev DB 已执行，LARGE id=2 的 nodes 已由 3 节点→2 审批节点、cc_list 已填充）。

### UI 改动
- **编辑器**（`app/view/admin/index.php`）：节点类型 select 删除「抄送节点(CC)」选项；`nodeApproverArea` 删除 CC 分支；`getNodesData` 删除 CC 分支；新增流程级抄送面板（`#ccRoleSel` / `#ccUsers` / `#ccUsersView` +「选择抄送人」按钮 `openCcPicker`）、`fillCcRoleOptions()`/`getCcListData()`；`newFlow`/`editFlow` 填充与重置流程级抄送；`saveFlow` 追加 `fd.append('cc_list', JSON.stringify(getCcListData()))`；流程列表节点数展示改为「N 审批节点 + M 抄送」。
- **控制器**（`app/controller/AdminController.php`）：`saveFlow()` 增 `'cc_list' => $this->getPost('cc_list', '[]')`，落库前确保合法 JSON（否则 `'[]'`）。
- **详情页**：`app/view/approval/detail.php` 与 `app/view/mobile/approval_detail.php` 在审批记录后追加「抄送知会」项（`approval_cc_log` 遍历）。
- **附：审批节点删除按钮缺失修复（用户报，同版带出）**：`addNode()` 渲染节点卡片的 `node-actions` 区原仅有「上移 / 下移」，缺失删除入口（`removeNode(i)` 早已定义却从未被任何 UI 调用）；现新增「删除节点」按钮（红色 `bi-trash`，`onclick="removeNode(i)"`），`removeNode(i)` 加 `confirm` 二次确认后移除卡片并重排 `renumberNodes()`；删至 0 节点时 `saveFlow()` 已有「至少需要1个审批节点」守卫。验证：jsdom 实跑（`runScripts:'dangerously'`）—`addNode×2`→2 卡、删除按钮 `onclick="removeNode(2)"`、真实点击后剩 1 卡且 node_1 序号重排「1」、删空后 `saveFlow` 弹「至少需要1个审批节点」、零 JS 错。零 DB / 后端逻辑变更，纯前端 UI 修复。

### 验证
- `php -l` 全绿；三脚本 1:1（29 表 / 282 字段，中文注释 0 缺）；`check_view_globals`（64 视图）通过。
- PHPUnit 43 tests / 89 assertions OK。
- E2E `e2e_cc_independent.py` 21 断言全绿：① 流程级抄送（208 纯抄送仅 cc_log / PENDING 归 203 / 审批被拒 403 / 收 APPROVAL_CC / PC+移动端无审批按钮）；② 去重（205 既是审批人又是 finance 抄送→仅 PENDING、不在 cc_log）；③ 编辑器 `/ajax/admin/flow/save` 携带 cc_list 落库（nodes 无 CC）；④ 存量 LARGE 迁移后提交正常推进 + 抄送去重正确。
- jsdom 实跑编辑器脚本（`/tmp/jsdom_cc_test.js`）：`editFlow` 正确回显 `finance` 角色 + 用户 208；`getCcListData()` 返回 `{role_codes:["finance"],cc_user_ids:[208]}`；`saveFlow()` 提交 `cc_list` 且 `nodes` 不含 `type=CC`；零 ReferenceError/TypeError。

### 部署注意（生产）
1. 部署新代码前，先对生产库执行 `php database/migrate_cc_independent.php`（MySQL）；本机无 MySQL，须在目标服务器执行。
2. 本地 SQLite 由 `init_sqlite.php` 建表时直接含 `cc_list` + `approval_cc_log`，无需单独迁移（dev 库已执行迁移脚本补齐存量）。
3. 存量流程若曾在节点链里配过 CC，`migrate_cc_independent.php` 会将其抽到 `cc_list` 并重建 `nodes`（剔 CC）。

## v2.37.5（2026-07-30）— 审批抄送隔离修复 + 钉钉消息落地页错跳 + 站内信兜底 + 多项 UI 修复（PATCH）

### 选人弹窗部门树鼠标样式（1 项）
- **UI-2 审批流选人弹窗（#userPickerModal）部门树悬停显示默认箭头而非手型**：审批流「指定用户」(`openApproverPicker`) 与「抄送节点」(`openCcUserPicker`) 共用 `openUserPicker` 打开的选人弹窗，其左侧部门树由 `.up-dept-link` 构成的 `<span>` 渲染（JS 绑定 onclick 切换部门），但项目内**从未定义 `.up-dept-link` 的 CSS**——可点击却无 `cursor:pointer`，浏览器回退默认箭头，用户无法从光标感知「部门可点」。用户管理 tab 的部门树用的是另一套 `.dept-node`（已有 `cursor:pointer`，故不报错）。现于 `app/view/admin/index.php` 顶部 `<style>` 补 `.up-dept-link{display:block;padding:4px 8px;border-radius:6px;cursor:pointer;user-select:none;font-size:14px;line-height:1.3}` + `.up-dept-link:hover{background:#f1f3f5}`，与 `.dept-node` 视觉对齐（悬停浅灰底 + 手型）。jsdom 实跑确认 computed `cursor=pointer` / `display=block` 生效。零业务逻辑/DB 变更。

### 钉钉 webview 强制深色模式把白底弹窗/toast 反色成黑底（1 项）
- **UI-3 钉钉里审批「通过」确认弹窗显示为黑色**：移动端审批详情页点击「通过」调用 `mConfirm()`（自定义确认弹窗，`.m-modal-box` 本为白底圆角卡片），在钉钉 webview（尤其 Android 端）开启深色/夜间模式时，宿主强制深色模式（force dark）把未声明 `color-scheme` 的浅色页面整体反色，白底卡片被反成接近黑色，呈「黑色弹窗」。代码层原无 bug（无裸 `confirm()`、`esc` 已定义、弹窗 CSS 为白底；jsdom 实跑确认 `.m-modal-box` background=`rgb(255,255,255)`）。现于 `app/view/mobile/_head.php` 加 `<meta name="color-scheme" content="light">`+`<meta name="theme-color" content="#ffffff">`，并在 `public/static/css/mobile.css` 的 `:root` 及全部浮层类（`.m-modal-box`/`.m-sheet`/`.m-toast`）声明 `color-scheme: light`，通知 webview 本页面仅浅色、跳过自动反色。该修复全局覆盖所有使用 `mConfirm` 的移动端视图（approval_detail/approval_create/archive/customer_detail/contract_form）。jsdom 实跑确认 box 背景白 + `color-scheme=light` + 零 JS 错误。待用户在钉钉内复测（若钉钉用非标准「智能反色」仍黑，需进一步钉钉专用 meta 方案）。

### 钉钉审批消息落地页错跳 PC 端 + 抄送文案/按钮语义（1 项，含 UI-4 补强）
- **UI-4（补强）钉钉里点审批消息在手机上仍打开 PC 审批页（已登录用户尤甚）**：上一轮仅在 `app/view/dingtalk/entry.php` 免登成功后按 UA 重写深链（`/approval/{id}`→`/m/approval/{id}`），但 `DingTalkController::entry()` 对**已登录用户**（`$this->userId>0`）直接 `redirect($to)` 跳 PC 页，**完全绕过视图内的 UA 判断**——手机钉钉已登录用户点消息直接落到 PC 页（正是用户复现的「错误跳转 PC 端审批页」）。修复：将移动端重写**前置到控制器**（`is_mobile_request()` 命中且 `to` 形如 `/approval/\d+` 时改写为 `/m/approval/...`），登录态 redirect 与未登录免登视图两条路径**统一生效**；视图内的重写因 `to` 已是 `/m/...` 而不再二次改写（`TO.indexOf('/approval/')=0` 不成立），保留作防御。`is_mobile_request()` 刻意不把 `DingTalk` 列入移动判定（钉钉 PC 客户端 UA 含 DingTalk 但属桌面，避免误判进移动版；手机钉钉 UA 含 Android/iPhone 仍命中）。验证：登录态 HTTP 冒烟——手机 UA → 302 `Location:/m/approval/5`、桌面 UA → 302 `Location:/approval/5`；未登录+手机 UA 渲染视图 `var TO="/m/approval/5"`、桌面 UA `var TO="/approval/5"`、非审批深链（如 `/dashboard`）不误改。
- **抄送节点的人收到「请尽快审批」等要求处理的文案（根因 = 设计缺陷，已修复）**：上一轮用「仅抄送 A / 仅审批 B」两个不同人的流程模型做 E2E，误判为"无泄漏"，得出"配置重叠"的结论是**错的**。正确根因：**`approval_flow.nodes` 把抄送存成 `type=CC` 的节点，与 ROLE 审批节点同在 `nodes` 数组、走同一套节点引擎，引擎对 CC 与审批节点完全无隔离/去重**——一旦某角色同时被写进 ROLE 节点和 CC 节点（种子流程 `LARGE` 本身就是：finance 既在「财务会签」审批节点又在「抄送财务」CC 节点），同一人就会**同时收到「请尽快审批」与「抄送知会」两条矛盾消息**。这是引擎设计缺陷，并非用户配错。修复：新增 `ApprovalLogic::collectFlowApprovers()` 收集流程内全部 ROLE 节点审批人，`autoProcessCc()` 发抄送知会前剔除同时也是审批人的收件人——"既是审批人又是抄送"的人只收审批催办（其审批人身份仍需催办，不能禁发），不再重复收抄送知会；纯抄送人不受误伤（仍只收抄送知会）。E2E 用真实 `LARGE` 流程跑通主链路验证：finance(205) 终态「请尽快审批」=1、「抄送知会」=0；另造纯抄送流程验证 205 仅收「抄送知会」=1、「尽快」=0。此外修复两处真实文案缺陷：① 抄送文案原写「该合同**审批结果**已抄送知会给你」——在流程**开头**的抄送节点（提交即触发）说「审批结果」属事实错误（此时尚未审批），改为中性的「该合同**审批流转**已抄送知会给你」；② 钉钉 action_card 按钮**写死「点击处理」**，抄送人点进去却显示「你不是当前节点审批人，无需操作」，语义误导——现按消息类型区分：`APPROVAL_CC` 用「点击查看」、其余用「点击处理」（`$type` 已从 `queueNotify` 经 `sendNotifyWithRetry`→`sendToLocalUsers`→`sendWorkNotice` 透传，Mock 同步）。

### 审批列表三 Tab 点击无反应（1 项）
- **UI-5 PC 端「合同审批」页（待审批/已审批/我提交的）三个标签点击全部无反应**：根因=`app/view/approval/index.php` 的 `<ul id="atabs">` 与 `public/static/js/approval_index.js` 绑定事件用的选择器 `#approvalTabs` **id 不一致**（HTML 是 `atabs`、JS 选 `approvalTabs`），`document.querySelectorAll('#approvalTabs a')` 返回空 NodeList，点击事件从未绑定，三标签切换全部失效（默认仅首屏 `load()` 渲染了"待审批"列表，故看起来像"有数据但点了没反应"）。修复：将 JS 选择器统一改为 `#atabs`（与 HTML 一致）。jsdom 实跑确认：默认触发 `pending-list` 请求，点击"已审批"触发 `processed-list`、点击"我提交的"触发 `submitted-list`，active 高亮正确切换。零业务逻辑/DB 变更。

### 审批结果站内信兜底 + 与「今日提醒」统一入口（Bug2 修复 + 增强，1 项）
- **Bug2「合同被驳回也没有提醒」根因**：系统审批事件**只发钉钉工作通知**（`ApprovalLogic::queueNotify`→`DingTalkService::sendToLocalUsers`），而 `sendToLocalUsers` 对**未绑定钉钉 `userid` 的本地用户直接 `error_log` 静默跳过**、钉钉 API 失败也仅重试一次后告警——即接收人没绑钉钉或推送失败就**彻底收不到**，应用内无任何兜底。代码本身在驳回时确实调用了通知（PHPUnit mock 实跑确认 `action(REJECTED)` 返回 true 且 mock 日志 recorded `title=审批被驳回, user_ids=[提交人]`），问题在「唯一通道=钉钉」这一架构缺陷。
- **方案（站内信兜底）**：新增 `notification` 表（三脚本 `init.sql`/`init_sqlite.php`/`init_mysql.php` + 生产迁移 `migration_v2.37.5_notification.sql`，表/字段中文注释 + 三脚本 1:1 校验均通过）+ `app/common/service/InternalNotify.php`（本地 DB `insertAll`，无网络 I/O，可在事务内直接调用；`send/unreadCount/markRead/markAllRead` 均按 `user_id` 作用域，跨用户标记被拦截）；`ApprovalLogic::queueNotify` 签名增 `type` 参数并在**写库的同时**调用 `InternalNotify::send`（8 个调用点补齐 `TYPE_APPROVAL_*`：驳回/通过/提交/转交/抄送/催办），即「钉钉 + 站内信」并行落库。新增 `NotificationController` 仅提供 AJAX 接口：`/ajax/notification/list`、`/unread-count`、`/mark-read`、`/mark-all-read`。
- **UI 入口统一（用户决策：复用「今日提醒」，不另起铃铛）**：经评估，现有「今日提醒」(`RemindService`) 是**时间驱动**的合同到期/回款提醒（扫描式、无持久消息、无已读态），与审批事件（**事件驱动**、需持久化未读/已读）在触发时机、数据模型、内容域三层都不同，**不能复用其存储/触发**，但**应复用其 UI 入口**避免两个铃铛。故：① 撤销初版独立的顶栏 `#notifBadge` 铃铛、移动端「消息」入口、全局轮询脚本、独立 `/notification` 页与路由、控制器 `index()`、视图；② 审批消息作为新板块并入现有 `app/view/remind/index.php`（PC）与 `app/view/mobile/reminders.php`（移动），复用 `public/static/js/notification.js`（改更新 `#msgUnread` 板块红点）；③ 侧边栏红点 `remind_count`（`BaseController`）合并 `InternalNotify::unreadCount`，与今日提醒共用一个数字；④ 仪表盘「今日提醒」卡片在有待读审批消息时显示引导条。生产部署须先执行 `migration_v2.37.5_notification.sql` 建表（本地 SQLite 已由 `init_sqlite.php` 建表；调试期已在 dev DB 建表并清理测试数据）。
- **验证**：`php -l` 全绿；`check_schema_parity`/`check_db_comments`/`check_view_globals` 通过；jsdom 实跑 `/remind` 页（列表渲染驳回/通过两条、板块红点=2 可见、分页渲染、零 JS 错误）+ 服务层 E2E（`InternalNotify::send` 落库、`unreadCount`/`markRead`(跨用户拦截)/`markAllRead` 行为正确）；登录态 HTTP 冒烟 `/remind` 200 且含审批消息板块、`/notification` 404、四个 AJAX 接口 200 返回正确 JSON、移动端 `/m/remind` 200 且底部导航修复（原缺失 `_foot.php`，顺带补回）。

### 提交审批忽略前端 flow_id → 流程错配，纯抄送人被当审批人（根因修复，用户纠正）
- **现象（用户纠正）**：一个「只是抄送节点」的纯抄送用户，收到的消息提示点击进去审批，且点进去后**确实有审批功能**（能真审批）。注意：这不是钉钉文案问题，是功能层把纯抄送人变成了审批人。
- **根因**：`app/controller/ApprovalController.php::submit()` 原硬编码 `ApprovalLogic::submit($contractId, $this->userId)`，**完全忽略前端传入的 `flow_id`**，引擎第三参永远默认 `0` → 永远走 `matchFlow` 自动匹配。即便合同已通过模板默认流配好「含抄送节点」的流程，`submit` 也会无视它、按金额/类别重新自动匹配到**另一个把同一人设为审批人的流程**——于是该纯抄送人变成审批节点的审批人，收到审批催办且能真审批。代码层 CC 隔离本身是正常的（用「对的流程」时，纯抄送人记录 `action=CC`、审批被拒、消息为 `APPROVAL_CC`），问题出在「提交时用了错误的流程」。与上一节 UI-4 的「同流双身份去重」是**两个独立问题**：UI-4 解决「同一人在同一流里既是审批人又是抄送」的重复消息；本节解决「提交无视 flow_id 把合同错配到把纯抄送人当审批人的另一流」。
- **修复**：`submit()` 改读 `flow_id = (int)$this->getPost('flow_id', 0)` 并透传第三参；`flow_id=0` 时回退 `matchFlow`（兼容未配置默认流的老合同）。PC 提交页 `app/view/approval/create.php` 表单补 `<input type="hidden" name="flow_id" value="<?=intval($contract['flow_id'] ?? 0)?>">`；移动端 `app/view/mobile/approval_create.php` JS 补 `fd.append('flow_id', <?=intval($contract['flow_id'] ?? 0)?>)`。`ApprovalLogic::submit` 签名本就支持第三参 `$flowId`，无需改动。
- **验证**：`php -l` 通过；`e2e_regression_flowid.py` 三场景全绿（11 项）：A) `flow_id=0` → matchFlow 兜底成功（实例 `flow_id=1`，确认此前「标准提交返回未匹配流程」是 dev 库 flow 1 被误禁用的假失败）；B) `flow_id=1` 显式 → 实例确用 `flow_id=1`（前端传入被尊重）；C) `flow_id=99002`（CC 流：208 纯抄送 + 203 经理审批）→ 208 记录 `action=CC`（非 `PENDING`）、208 审批返回 **HTTP 403「权限不足」**（无审批权限）、208 收 `APPROVAL_CC` 抄送知会（非审批催办）、203 经理审批成功。dev DB 中保留测试流 `E2E纯抄送流(E2E_CC, id=99002)` 供回归重跑。

### 抄送节点在审批节点之后：纯抄送人被当审批人（用户复现诉求，当前代码已隔离）
- **用户复现场景**：流程节点1=审批(审批人 A)、节点2=抄送(抄送人 B，B 仅抄送)。A 审批通过后，B 收到「提醒审批」钉钉消息，点进去有通过/驳回按钮且能真审批。
- **复现结论（当前 dev 代码已正确隔离，非 BUG）**：`e2e_repro_cc_after_approval.py` 复现两种配置共 12 项断言全绿——`[审批203 → 抄送208]` 与 `[审批203 → 抄送208 → 审批204]`（抄送在中间）。两种下 B 均：仅 `CC` 记录、无 `PENDING` 审批记录、审批返回 HTTP 403、移动端详情页不渲染 `btnApprove/btnReject/btnTransfer`、收 `APPROVAL_CC` 抄送知会（非审批催办）。根因已修复点：引擎 `advanceAfterNode()` 在节点推进时调用 `autoProcessCc()`，自动流转连续抄送节点并跳过（不再生成审批记录），纯抄送人无审批权限。流程编辑器存 CC 节点为 `type:'CC'`（`app/view/admin/index.php:672` `getNodesData`），与引擎期望一致，无 type 字符串错配。
- **用户所见症状对应旧构建**：旧版 `advanceAfterNode()` 在 A 通过节点1后未调用 `autoProcessCc`，把节点2(抄送)当「下一个审批节点」→ 给 B 插 `PENDING` 记录 + 发「请尽快审批」+ 渲染审批按钮，与用户描述完全一致。故判断其生产环境跑的是修复前旧代码；请确认部署版本并重部署最新代码。本修复为纯逻辑修复，MySQL 生产库无需改表（但 v2.37.5 的 `notification` 表迁移仍须执行，否则 B 收不到应用内抄送知会）。若用户确信生产已最新仍复现，需其提供真实合同审批流 `nodes` JSON 继续排查。

## v2.37.4（2026-07-30）— think-template 编译器修复 + 视图语法防御（PATCH）

> 修复 v2.37.3 引入的 think-template v2.0.10 编译器在 PHP 8.4 下导致「客户管理」「合同管理」「admin?tab=dingtalk」等多页面 500 的问题。零 DB 结构变更。

### 编译器合并正则丢分号 + 单行注释陷阱（根治 2 层）

**根因链**：v2.37.2 使用 think-view 自带旧引擎（直接 include 原始视图、无合并）；v2.37.3 E2E 时 `composer install` 拉起 think-template v2.0.10 替换旧引擎，其 `compiler()` 方法有两处问题：

1. **第 426 行合并正则 `''` 丢失分号**：`preg_replace('/\?>\s*<\?php\s(?!echo\b|\bend)/s', '', $content)` 将相邻 PHP 块间的 `?>` `<php` 边界删除以减少标签切换，但**丢失了 `?>` 隐含的 `;`**。当视图有 `<?= func() ?>` 紧跟 `<?php if/foreach/include` 时，合并成 `<?= func()if(...)` 即非法语法。共 52 处视图模式受此影响，其中 `contract/detail.php:188`（`<?=approval_action_label()?><?php if`）和 `contract/index.php`（`<?=mobile_tabbar()?><?php include`）分别导致合同管理和客户管理崩溃。

2. **单行注释 `//` 吞后续语句**：编译器合并三个 `<?php ?>` 块为同一行后，`//` 单行注释吞掉同一行所有后续 PHP 代码。具体案例 `admin?tab=dingtalk`：`$ddMock` 赋值被 `// REV-11...` 注释掉 → `Undefined variable $ddMock` → 500。

**两级修复**：

- **编译器层（1 行）**：Template.php:426 合并替换 `''` → `";\n"`。① `;` 补回语句分隔符，`<?=x;?><?php if` → `<?=x;if` 合法；② `\n`（换行）阻断 `//` 单行注释吞后续语句——三个 PHP 块合并后不在同一行，注释只到行尾不跨越换行。安全性论证：`\s*` 仅匹配空白字符，无 HTML 夹层时才触发，`;\n` 语义「语句结束 + 下一语句」永远合法。

- **视图防御层（3 处）**：
  - `contract/detail.php:188`：`<?=approval_action_label($n['action']); ?>`（显式 `;` 防编译器漏修）
  - `layout/sidebar.php:87`：`<?=app_version(); ?>`（同上）
  - `contract/create.php:67`：`{ $pc=$p; break; }`（空格阻断 `{...}` 被模板引擎当标签解析）
  - `admin/index.php:282`：`$ddMock` 赋值移到 `/* REV-11... */` 块注释前（不再被单行注释吞掉）

### 验证

- 改动文件 `php -l` 全绿；无 DDL 改动，三脚本 schema parity（27 表/266 字段）/ db 中文注释（266 字段 0 缺）零回归；视图全局变量检查（63 视图）通过；`php phpunit.phar` 43 tests / 89 assertions 全绿。
- **全量视图编译验证**：60 视图经由修复后编译器静默编译 → `php -l runtime/temp/*.php` 60/60 通过，0 语法错误。
- 清空模板缓存后访问 14 个主要页面重编译，确认 `compile error` 日志清零。

## v2.37.3（2026-07-30）— 钉钉消息免登直达 + 移动端成功提示 UI 对齐 + 审批参与者可见性（PATCH）

> 包含：① 修复「钉钉内审批消息点击后只跳登录页、需先经工作台进入应用才能正常免登」；② 移动端成功/操作提示从纯黑半透明方块改为与系统移动端 UI 对齐的浅色卡片（白底圆角 + 品牌色图标）；③ 审批参与者（提交人 / 任一节点审批人 / 抄送人）默认可查看本审批实例及其合同详情，修复「普通用户被抄送后点开消息提示无权限查看该审批」。零 DB 结构变更。本次一并修了一个会导致「干净 `composer install` 后应用启动失败」的引导文件依赖 API 不匹配（详见 PERM-1 顺带修复）。

### 钉钉消息免登直达（1 项）
- **SSO-1 消息卡片(single_url)免登被 `dd.ready` 阻塞**：`app/view/dingtalk/entry.php` 与 `app/view/auth/login.php` 原把 `dd.getAuthCode`(免登) 包在 `dd.ready(callback)` 内。钉钉 JSAPI 2.0（本系统引入的 3.1.0 SDK）下 `dd.getAuthCode` 可独立调用、并不依赖 `dd.ready`；而**消息卡片 `single_url` 打开的 webview 中 `dd.ready` 常不触发**，导致免登永远不执行 → 8s 看门狗回退 `/login`（即用户报的「先点消息只跳登录页」根因）。工作台打开的是微应用容器、`dd.ready` 正常触发，故工作台进入后免登成功、会话 Cookie 与消息 webview 共享，之后再点消息时 `entry()` 命中 `$this->userId > 0` 直接跳转才「正常」。现改为：best-effort `dd.config`（失败仅告警不阻断）+ 直接调用 `dd.getAuthCode`，不再等待 `dd.ready`；`getAuthCode` 失败/超时仍回退 `/login`。两视图均经 jsdom/vm 前端实跑验证（非钉钉UA→/login、钉钉UA+getAuthCode成功→直达目标、getAuthCode失败→/login）。

### 移动端成功/操作提示 UI 对齐（1 项）
- **UI-1 移动端 toast 由黑色方块改为浅色卡片**：原 `.m-toast` 为 `rgba(0,0,0,.82)` 纯黑半透明方块，与移动端整套浅色卡片设计（`--m-card` 白底、`--m-success` 微信绿、`--m-brand` 品牌蓝）割裂，在钉钉 webview 中尤显「外来黑框」。现 `public/static/css/mobile.css` 的 `.m-toast` 改为白底圆角卡片（12px 圆角、柔阴影、`--m-text` 深字、居中缩放淡入），并新增 `.m-toast-ic` 圆形彩色图标（success=绿勾 / error=红叉 / info=蓝 / warning=橙）；`public/static/js/mobile-common.js` 的 `toast(msg, type, duration)` 增加可选 `type` 参数（向后兼容），文本走 `textContent` 防 XSS。`app/view/mobile/approval_detail.php` 审批同意→`toast('已通过','success')`（绿勾）、驳回/转交→`info`（蓝标），与系统移动端 UI 及钉钉原生成功提示风格对齐。jsdom 实跑 12 项断言全绿（图标渲染、文本、XSS 防护、无 type 向后兼容、无 JS 错误）。

### 审批参与者可见性（1 项）
- **PERM-1 审批参与者默认可查看本审批实例及其合同**：原 `ApprovalController::detail`(PC) 与 `MobileController::approvalDetail`(移动) 的查看门禁仅认「角色权限(`approval:view`) + 数据范围(本人/本部门)」，完全不认「用户是否是该审批的参与者」。导致普通用户被抄送(CC)或被设为某审批节点后，点开钉钉消息链接(经 `/dingtalk/entry?to=/approval/{id}`)仍被拒绝、报「无权限查看该审批」。现新增 `ApprovalLogic::isParticipant($instanceId, $userId)`（提交人 `submitted_by` 或任一 `approval_record.approver_id`，覆盖 CC/已审批/当前待审/转交），并在两处详情门禁中加入「参与者免拦截」短路：参与者即使无 `approval:view` 或超出数据范围也能查看本审批实例（含合同标题/金额/提交人/节点记录），但**只读、不能审批**（`can_act` 仍只认当前待审节点，`action` 端点另有 `approval:approve` 门禁）。非参与者的角色/数据范围校验保持不变，未扩大任何通用权限。
  - **验证**：已通过**真实 Web 整链 E2E**（PHP 内置服务器 + 真实登录态 curl，非模拟）完整验证。构造演示数据：实例 5001=c9908 抄送 employee01(角色5/SELF 范围，含 `approval:view`)、实例 5002=c9914 抄送 ccnoperm(无角色、无 `approval:view`)、sales02 为无关人。结果：admin(所有者)/employee01(抄送)/ccnoperm(抄送) 访问各自被抄送的审批详情均 HTTP 200 且渲染真实详情页（含「审批详情/提交人/合同/抄送知会」等，employee01 页面 10KB 正文）；sales02(无关人，即便角色5含 `approval:view`) 访问他人审批仍 HTTP 200 + 正文含「无权限查看该审批」——证明参与者放行、通用数据范围校验未被绕过。PC `/approval/<id>` 与移动 `/m/approval/<id>` 结论一致。单测边界脚本（内存 SQLite 9 项断言）亦全绿。
  - **顺带修复（引导文件，应保留）**：`think` 与 `public/index.php` 原用 `new \Dotenv\Dotenv($path)`（phpdotenv v3 API），与 `composer.json` 约束 `^5.0` 不符——干净 `composer install` 拉到 v5.5.0+ 后构造函数签名收紧，应用**根本无法启动**。改为跨版本稳定的 `Dotenv::createImmutable($path)`，在 v5.5.0 / v5.6.1 下均正常启动（发布包 v2.37.2 实测携带 phpdotenv 5.5.0 + 旧 `new Dotenv` 调用，纯属侥幸能跑）。

### 验证
- 改动文件 `php -l` 全绿；无 DDL 改动，三脚本 schema parity（27 表/266 字段）/ db 中文注释（266 字段 0 缺）零回归；前端 SSO/UI 改动经 jsdom 实跑验证；PERM-1 经真实 Web 整链 E2E 验证（见上）；`php phpunit.phar` PHPUnit 43 tests / 89 assertions 全绿。

## 待排期根治 · 发布 / 环境风险（搭建真实 E2E 时发现，v2.37.3 未动）
- **RISK-1 发布包无 `composer.lock`，干净 `composer install` 非确定且可拉到破坏性版本**：`release.sh` 打包含 vendor，但发布物与仓库均无 `composer.lock`。本机实测 `composer install`（无 lock）会拉到与发布快照不一致的依赖，导致两类致命问题：① phpdotenv 从发布时的 5.5.0 升到 5.6.1（构造函数收紧，配合旧 `new Dotenv` 调用直接启动失败，已由上述 `createImmutable` 修复规避）；② `topthink/think-view` 从「自带 `src/Template.php`（直接 `include` 原始视图文件、`__DIR__` 正确）」升到新版（模板编译缓存进 `runtime/temp` 再 `include`，导致视图内 `include __DIR__.'/../layout/...'` 布局包含全部失效、所有用布局的页面 500）。**根治**：提交一份锁定「当前能跑版本」的 `composer.lock`（或显式 pin 关键依赖），使 `composer install` 可复现；并在 CI/出包前加「干净安装 + 冒烟」卡点。
- **RISK-2 PHP 8.4 下 topthink/framework 6.1.5 大量「隐式可空参数」废弃被当异常**：框架 `Error::init()` 硬性 `error_reporting(E_ALL)`，PHP 8.4 新增的 `Implicitly marking parameter as nullable is deprecated`（`think\Request::env()` 等）被转成 `ErrorException` → 每次请求 500。本地 E2E 临时将 `vendor/topthink/framework/.../initializer/Error.php` 的 `error_reporting(E_ALL)` 改为排除废弃级（仅本地验证用，未提交）；生产若运行 PHP>=8.4 需正视：**要么把 PHP 降到 <8.4，要么升级 ThinkPHP 框架到兼容 8.4 的版本**（该 vendor 补丁不随 `composer install` 留存，不能作为永久方案）。
- **已落地本地临时补丁（仅本机验证用，不视为正式修复）**：`vendor/topthink/framework/.../Error.php` 的 `error_reporting` 排除 `E_DEPRECATED|E_USER_DEPRECATED`；`vendor/topthink/think-template/.../Template.php` 的 `compiler()` 前补回 `__DIR__`/`__FILE__` 魔术常量→源路径字符串的替换（新版缺失、旧版曾有，恢复后即使编译进缓存文件 `include` 布局也能正确解析）。两补丁使本机 PHP 8.4 + 全新 vendor 能完整渲染页面，但**均不随 `composer install`/出包留存**，根治仍须 RISK-1/RISK-2 的正式方案。

## v2.37.2（2026-07-30）— 审批提交页节点统一展示（PATCH）

> 审批提交页节点展示进一步简化：无论 ROLE / DEPT_LEADER / SPECIFIC_USER / CC 何种节点类型，均只显示节点名 + 具体审批人/抄送人姓名，去除原先按类型区分的「审批/抄送/角色/指定用户/部门负责人/会签」徽标。零 DB 结构变更。

### 审批提交页节点展示（1 项）
- **APP-4 节点统一只显示具体人员**：`app/view/approval/create.php`(PC) 与 `app/view/mobile/approval_create.php`(移动) 的节点 `<li>` 渲染由「按节点类型分支显示不同徽标」改为统一单分支——仅输出节点名 + `resolved_names`（具体审批人/抄送人姓名）；CC 节点不再单独显示「抄送」徽标与角色徽标，会签节点不再显示「会签」徽标。验证：临时启用含 CC/会签节点的大额审批流（flow#2），PC 渲染「部门经理审批（张经理）/ 财务会签（王财务）/ 抄送财务（王财务）」、移动端同名无徽标；已还原 flow#2 禁用状态与临时合同。

### 验证
- 改动文件 `php -l` 全绿；无 DDL 改动，三脚本 schema parity（27 表/266 字段）/ db 中文注释（266 字段 0 缺）零回归；`php phpunit.phar` PHPUnit 43 tests / 89 assertions 全绿。

## v2.37.1（2026-07-29）— 审批提交页展示 + 钉钉审批通知修复 + 数据回收站 UI + 中文化与提交页精简

> 本批次为 v2.37.0 之后用户提出的「审批流提交页展示」「钉钉审批通知」「数据回收站 UI」与「页面中文化 + 审批提交页精简」四类改动，均经实跑验证。零 DB 结构变更（无迁移脚本），属 PATCH。

### 审批提交页展示（3 项）
- **APP-1 提交审批页未显示具体审批人/抄送人**：`ApprovalController::create`（PC）与 `MobileController::approvalCreate`（移动）原仅按节点类型显示「指定用户/角色/部门负责人」类别，不显示具体是谁。现后端经 `ApproverResolver::resolve($node, $userId)` 解析真实用户并注入 `flow_nodes`（含 `resolved_names`），前端循环节点时追加「（具体用户名）」；指定用户节点若未配置具体人显示红字「（未指定具体用户）」，角色节点显示该角色下成员姓名。
  - **验证**：开测试服务器提交页，PC 显示「法务（系统管理员）」「部门经理（张经理）」；移动端同步显示具体用户名。
- **APP-2 隐藏金额条件时仍显示「适用 ¥x~¥y」介绍文字**：提交页顶部金额区间介绍仅在流程 `use_amount=1` 时渲染；`use_amount=0`（隐藏金额条件）时只显示「（流程code）」与审批节点/审批人，不再显示金额区间。
  - **验证**：临时将 STANDARD 流程 `use_amount` 置 0 → 介绍文字消失、仅显示（STANDARD）；还原后恢复。
- **APP-3 提交页合同分类显示英文（如 SALES）**：改为 `contract_category_name($code)` 字典映射中文（SALES→销售合同 等）；PC 与移动端同步。
  - **验证**：PC/移动端提交页分类均显示中文（服务合同/采购合同等）。

### 钉钉审批通知（2 项）
- **DD-1 钉钉审批通知不应显示合同编号**：所有面向用户的审批通知文案（提交新审批/抄送知会/驳回/转交/下一节点/通过/会签催办）移除「合同编号/合同编号：xxx」与「（xxx）」合同编号括号内容，仅保留合同标题与金额。服务端诊断日志（`Log::warning` 会签超时）保留 `contract_no` 仅作排查、不面向用户。
  - **验证**：mock 模式提交审批，检查钉钉 mock 通知日志（macOS 实际位于 `$TMPDIR/dingtalk_mock.log`，非 `/tmp`）content 不含「合同编号」与「HT-」编号。
- **DD-2 钉钉点击审批请求应在钉钉内打开应用（而非外部浏览器）**：原 markdown 内联链接被钉钉当外部浏览器打开。`config/dingtalk.php` 的 `msg_type` 改为 `action_card`（单按钮卡片），携带跳转链接的通知改走 action_card，`single_url` 指向经 `/dingtalk/entry?to=` 免登入口生成的**应用内深链**（点击在钉钉微应用 WebView 内打开），无链接通知（如到期提醒）仍回退 markdown。所有通知调用点将深链作为第 4 参数透传。
  - **关键修复**：`ApprovalLogic::flushNotify()` 经队列发送时原只传 3 个参数、**丢弃了第 4 个 `$url` 深链**，导致 action_card 永远收不到链接、退化为纯文本。已修正为透传 `$task[3] ?? null`。该 bug 经 mock 模式实跑捕获（修复前日志无 url、修复后日志含 `dingtalk/entry?to=/approval/xxx`）。
  - **验证**：mock 模式提交 → 通知日志含 `dingtalk/entry?to=/approval/106` 深链（修复前同类日志无 url）；`config/dingtalk.php:msg_type=action_card` 确认链接承载于 action_card。

### 数据回收站（1 项，已实现）
- **REC-1 合同/客户/供应商回收站功能（防误删/恶意删除）**：合同/客户/供应商**已具备软删除**（`is_deleted` + `softDelete()` + `deleteBlockers()`，仅 DRAFT/REJECTED 可删且有关联阻断），此前缺 UI 供恢复。现新增完整回收站：
  - **入口**：仅超级管理员可见——侧边栏「数据回收站」（`layout/sidebar.php` 内 `$__admin` 守卫）、路由 `GET /recycle`（页面）+ `ajax` 组 `recycle/list` / `recycle/restore` / `recycle/purge`，全部经 `RecycleBinController` 的 `is_admin` 守卫拒绝越权（非超管 GET 渲染 403 页、AJAX 返回 403 JSON）。
  - **列表**：`RecycleBinLogic::listDeleted()` 按类型筛 `is_deleted=1`，带归属人名称、删除时间、阻塞项（`blockersFor()` 复用各实体 `deleteBlockers()`）与「能否彻底删除」标记；前端 `recycle/index.php` 类型切换标签（合同/客户/供应商）+ 关键词搜索 + 分页 + 二次确认弹窗。
  - **恢复**：`restore()` 将 `is_deleted` 置 0（仅对当前已删除记录生效）。
  - **彻底删除（物理删除，首处真物理删）**：`purge()` 先复用 `deleteBlockers()` 校验，有阻塞项（关联审批/回款/发票/子合同）**拒绝并回显原因**；无阻塞项才事务内删除，合同物理删除时**级联清理**其 `approval_instance` + `approval_record`（与合同强绑定）。
  - **关键修复（实现期实跑捕获）**：① `RecycleBinController::index()` 原 `View::fetch()` 被框架把 CamelCase 控制器 `RecycleBin` 解析为视图目录 `recycle_bin/`，导致页面 500「模板文件不存在」；改为显式 `View::fetch('recycle/index')` 与路由 `/recycle` 对齐。② 页面内联脚本在 `footer.php` 的 `app.js`（`$ajax`/`esc`/`showToast`）**之前**执行并同步调用 `loadList()`，会 `ReferenceError: $ajax is not defined` 导致列表永不渲染；按 `admin/index.php` 既有约定改为 DOMContentLoaded 内触发（所有同步脚本执行完后才跑，此时全局已就绪）。
  - **验证**：开测试服务器 E2E——超管登录 `GET /recycle` 200 渲染、列表返回软删除记录；`restore` 后 DB 断言 `is_deleted` 回 0；`purge` 后 DB 断言记录物理消失（合同级联 approval 记录清零）；对「存在进行中审批」的合同 `purge` 被拒并回显「存在进行中的审批流程」、记录保留；非超管 `purchase02` 访问 `recycle/list` 返回 HTTP 403「权限不足」。jsdom 实跑 `/recycle`：内联**真实** `app.js` 保持真实脚本顺序、mock 仅 CDN `bootstrap`+`fetch`，列表渲染 2 行（含阻塞项行「彻底删除」按钮按 `can_purge` 置灰）、总计「共 2 条」、`openPurge` 点击不抛错、**0 运行时错误**。

### 中文化 + 提交页精简（2 类）
- **L10N-1 PC/移动端页面英文原始码外露排查与中文化**：用户反馈提交审批后 PC 端「操作记录」出现「修改 status PENDING_APPROVAL」、回款记录「已收」后括号显示英文 `BANK`。全量排查后修复：
  - **操作记录（时间线）** `ContractTimelineService::getTimeline()`：原 `修改 {$field_name}` 直接输出字段英文键、`detail` 直接输出 `new_value` 原始码。现新增字段中文名映射（`status→状态`、`amount→金额`、`title→标题`、`category→分类`、`direction→方向`、`party_a_name→甲方`、`party_b_name→乙方`、`effective_date→生效日期`、`expiry_date→到期日期`、`content→内容`、`remark→备注`、`owner_id→负责人`、`trade_attr→交易属性`）；状态字段的 `old_value/new_value` 经 `ContractLogic::STATUS_LABELS` 本地化（如 `PENDING_APPROVAL→待审批`），有旧值且新旧不同时展示「旧 → 新」过渡。验证：contract 107 操作记录由「修改 status / PENDING_APPROVAL」变为「修改状态 / 草稿 → 待审批」。
  - **回款列表 `已收` 后括号英文 `BANK`**：`PaymentLogic::getListByContract()` 新增 `payment_method_label`（从系统字典 `dict_payment_method` 读取中文，如 `BANK→银行转账`），PC `contract/detail.php` 回款徽标改用该标签；时间线「确认收款」明细的 `（{$payment_method}）` 同步走 `dict()` 本地化；`party/360.php` 回款表「方式」列 `htmlspecialchars($p['payment_method'])` 改为 `dict('payment_method', ...)`。验证：contract 114 回款 AJAX 返回 `payment_method_label='银行转账'`，页面「已收（银行转账）」、相对方 360 方式列显示「银行转账」，均无裸 `BANK`。
  - **附带修复（排查中发现）**：`PartyController::view()` 返回 `View::fetch()` 被框架把方法名 `view` 解析为 `party/view.php`，但实际视图文件为 `party/360.php`，导致相对方 360 页**长期 500「模板文件不存在」**（与本次中文化无关，但使上面的 `BANK` 修复不可达）。改为显式 `View::fetch('party/360')` 后页面正常（HTTP 200）。
- **L10N-2 审批提交页只展示审批节点、不再展示「这是什么审批流程」**：用户要求提交审批时只显示各节点信息，不需要显示这是什么审批流程。`app/view/approval/create.php`（PC）与 `app/view/mobile/approval_create.php`（移动）删除「将使用流程：<流程名>（<流程code>，适用 ¥x~¥y）」引导块；卡片标题由「审批流程（系统自动匹配）」改为「审批节点」，节点 `<ol>` 列表（含审批人/抄送人/会签等具体信息）保留。验证：PC `/approval/create/107` 与移动端 `/m/contract/107/approval` 均仅渲染节点列表，无「将使用流程 / 适用 ¥ / 自动匹配」字样。

### 验证
- 改动文件 `php -l` 全绿；三脚本 schema parity（27 表/266 字段 + 种子 INSERT 列 1:1）/ db 中文注释（266 字段 0 缺）零回归（无 DDL 改动）；`php phpunit.phar` PHPUnit 43 tests / 89 assertions 全绿。演示库经 E2E 后已还原（contract 121 已恢复、contract 108 回 `is_deleted=0`、无孤儿 `approval_record`、测试服务器已停）。

## v2.37.0 (2026-07-29) — 数据权限引擎重构：DEPT_AND_CHILD + CUSTOM 两档（MINOR）
- **可见性谓词引擎（核心重构）**：将角色级单一标量 `data_scope`（仅 SELF/DEPT/ALL）升级为多档位 + 自定义部门集合的「可见性谓词」。谓词结构 `{has_all, owner_self, dept_ids}`，多角色按 OR 合并（ALL 短路），统一经 `AuthLogic::buildVisibility()` / `expandDeptDescendants()`（PHP 递归展开子孙，DB 引擎无关、可单测）/ `scopeOrConditions()` / `visibility()` 裁决；`SupplierLogic`/`CustomerLogic` 搜索改用 `scopeOrConditions()`，新档位不再退化为全量。
- **新增两档数据范围**：
  - `DEPT_AND_CHILD`：本部门 **及子部门**（部门经理可看下属部门数据，原痛点①）。
  - `CUSTOM`：角色绑定固定部门集合（财务/审计只看指定部门，原痛点②），存于新表 `role_dept(role_id, dept_id)`。
- **DDL / 种子（三脚本 1:1）**：`init_sqlite.php` / `init_mysql.php` / `init.sql` 新增 `role_dept` 表（表+字段中文注释），`role.data_scope` 注释补全新档位，`dict_data_scope` 系统配置补全新标签；生产 MySQL 迁移 `database/migration_v2.37.0_role_dept.sql`（幂等）。
- **UI（角色 tab）**：`data_scope` 下拉扩为 5 项（ALL/DEPT/DEPT_AND_CHILD/CUSTOM/SELF）；选 CUSTOM 时显示部门树多选（`roleDeptBox`/`roleDeptChecks`，复用 `allDepts` 渲染缩进 checkbox）；`saveRole` 提交 `role_dept_ids[]`，`RbacService::saveRoleDepts()` 全量替换 + `perm_version` 失效已登录会话。
- **缺陷修复（Phase 1 闭环）**：
  - 角色 tab `renderRoleDeptChecks()` 守卫误用 `window.allDepts`（`allDepts` 为顶层 `let`、不挂 `window`），导致 CUSTOM 部门树在真实浏览器中**永不渲染**；改为 bare `allDepts` 引用（jsdom 实跑捕获）。
  - `init_sqlite.php` 的 `department` / `resource_library` 建表末列带尾逗号，SQLite 3.50 拒收 `near )`，**全新本地初始化半失败**（其余表/种子正常）；移除尾逗号，现已 28 张表全建、种子完整。
- **验证**：PHPUnit `DataScopeTest` 15 tests/25 assertions（expandDeptDescendants / buildVisibility 各档位 / 多角色 OR / CUSTOM 并集 / 未知档位降级全绿）；双角色 E2E 实跑（DEPT_AND_CHILD 角色返回 dept 4+子孙 10/11 合同、CUSTOM 角色返回 dept 2/3 合同，均未越权）；jsdom 实跑角色 tab（5 函数定义、CUSTOM 渲染 5 个部门 checkbox 且按 `_deptIds` 预勾选、saveRole 正确 POST `role_dept_ids[]`、DEPT_AND_CHILD 正确隐藏部门框、0 运行时错误）；`init_sqlite.php` 全新初始化 28 表 + `role_dept` 齐全。发布门禁（parity/中文注释/php -l/PHPUnit）全绿。

## v2.36.0 (2026-07-29) — 系统配置备份/恢复（新增功能）+ 用户管理页版权错位修复（MINOR）
- **系统配置备份 / 恢复（新增功能）**：后台「系统设置 → 系统配置」页新增「系统配置备份 / 恢复」卡片。
  - **导出**：`GET /ajax/admin/config/backup` 下载 JSON 快照，覆盖 `role` / `permission` / `role_permission` / `user_role` / `department` / `company_profile` / `approval_flow` / `contract_template` / `resource_library` / `system_config` 共 10 张配置表；**不含 user 表**（避免密码哈希出域，按用户明确要求）。
  - **恢复**：`POST /ajax/admin/config/restore`，支持 `mode=preview`（解析返回各表行数 + 风险告警，不改库）与 `mode=commit`（事务内 DELETE + 批量 INSERT 保留原 id，失败整体回滚）。含应用版本差异告警、孤立 user 引用告警。
  - **DB 无关**：MySQL 走 `SHOW COLUMNS`、SQLite 走 `PRAGMA` 取列；联合主键表（`user_role` / `role_permission`）按首列排序与导入，不依赖 `id`。仅超管（`system:user` 权限）可操作。
  - **验证**：E2E 实跑导出→篡改→预览→提交恢复→DB 断言全通过（含联合主键表正确恢复、孤儿/版本告警、幂等）；jsdom 实跑前端预览渲染/确认禁用态/提交调用/文件守卫 11 项全通过。

- **布局修复：系统设置-用户管理页版权信息错位到右上角**：`app/view/admin/index.php` 用户管理 tab（177–281 行）第 178 行开了两层 div（`<div id="activePane">` + 内层 `<div class="d-flex ...">` 行容器），但第 215 行只闭合了 `activePane`、内层 `d-flex` 始终未闭合。导致 `footer.php` 末尾的两个 `</div>` 提前闭合「内层 d-flex」和「main-content」，最外层 `d-flex`（sidebar+main 横向容器）保持打开，`<footer class="site-footer text-center">` 被渲染成该 flex 容器的子项、排到右上角而非底部居中。其余 tab（dingtalk/role/flow/dict/config）div 嵌套均平衡，故仅用户管理页复现。
  - **修复**：第 215 行补一个 `</div>`，与 178 行两层 div 配对闭合（`<div id="activePane">` 与内层 `.d-flex`）；其余 tab 未改动。
  - **验证**：登录后渲染 `/admin`（默认用户管理 tab），用 `html.parser` 栈校验整页 div 嵌套完全平衡、无未闭合标签；`<footer>` 渲染时父容器栈已空（即 `<body>` 直接子元素），回到 flex 容器之外、底部居中。原「右上角错位」特征消失。

## v2.35.14 (2026-07-29) — 数据库种子/移动端交互/字典缓存/提醒标题 6 项修复（PATCH）

> 本批次为 v2.35.13 之后的攒批修复（用户报 bug + 发布门禁盲区补强），均经实跑验证，不升号不单独出包规则下随本次发布。

### 数据初始化（1 项）
- **DB-1 `init.sql` 的 `company_profile` 种子 INSERT 引用已删除列**：该表在 v2.35.9 已精简为 7 列（`id/name/short_name/unified_social_credit_code/is_default/created_at/updated_at`），但 `init.sql:627` 的种子 INSERT 仍是精简前的 13 列旧写法，引用 `tax_no/bank_name/bank_account/address/tel/legal_rep` 等 6 个已删除列。DBA 直接导入 `init.sql` 时 CREATE 成功、INSERT 在 `tax_no` 处报 `Unknown column 'tax_no'` 中止（表建成但为空）。`init_mysql.php`/`init_sqlite.php` 的对应 INSERT 早已改对，唯独 `init.sql` 漏改。
  - **修复**：`init.sql:627-629` 的 INSERT 改为 7 列，与表结构及另两份脚本 1:1 对齐。`init_mysql.php:598`/`init_sqlite.php:595` 同步核对无误。
  - **验证**：临时把 INSERT 改回 13 列跑 `check_schema_parity.sh`（新扩展校验）可捕获并报 EXIT=1；改回 7 列后复跑 EXIT=0。

### 发布门禁补强（1 项）
- **GATE-1 `check_schema_parity.sh` 扩展校验种子 INSERT 列**：原脚本只比对三脚本 CREATE TABLE 列、不校验 INSERT 语句列，故 DB-1 未被发布门禁捕获。新增规则 3（每个种子 INSERT 的列必须存在于其对应表本文件 CREATE TABLE 字段中）+ 规则 4（同一张表种子 INSERT 列集合在三份脚本间 1:1 一致），新增 `INSERT_RE`/`parse_inserts()` 复用原 CREATE 解析结果比对。
  - **验证**：现有代码（含 DB-1 修复）实跑 parity 全绿；失效性测试（init.sql 改回 13 列）准确报两类问题并以 EXIT=1 拒绝，恢复后 EXIT=0。证明新校验非空转，不误伤发布门禁。

### 移动端交互（1 项）
- **MOB-1 移动端资料库点击分类标签报「网络异常」**：`app/view/mobile/resource.php:74` 的 `fetchList()` 请求路径写成 `/resource/list`，但资料库 AJAX 路由在 `Route::group('ajax')` 内，真实路径为 `/ajax/resource/list`（缺 `/ajax` 前缀）。本地 `url_route_must=false` 时 `/resource/list` 经 PATH_INFO 回退仍能解析故本地不报错；生产常开强制路由时该路径未匹配任何路由 → 返回 HTML 错误页而非 JSON，前端 `res.json()` 抛错被 `.catch` 捕获显示「网络异常」。PC 端 `resource.js` 用正确路径故一直正常。
  - **修复**：`mobile/resource.php:74` 路径改为 `/ajax/resource/list`。
  - **验证**：临时开 `url_route_must=true` 模拟生产 → 登录后 `curl /resource/list` 返回 HTML 错误页（复现），`curl /ajax/resource/list?category=TEMPLATE` 返回 JSON(code:0, 含真实资料)（验证修复）；验证后还原配置并清缓存。横向排查：移动端 approvals/contracts/customers 等列表用 `/m/xxx` 页面二合一路由（isAjax 返回 JSON），非同类 bug。

### 字典与提醒（3 项）
- **DICT-1 后台删除字典里的合同分类后，创建合同时删除的分类还在**：后台「字典」页的「合同分类」项 `dict_contract_category` 删除写该键；但创建页 `contract_categories()`（`app/common.php:244`）读它时有 300s `Cache::remember` 缓存，且 `saveConfig` 只清了无关的 `syscfg_` 缓存 → 删除后最长 5 分钟创建页仍读旧缓存。附带隐患：`saveContractCategories` 只写被遮蔽的 `contract_categories` 键（写而不读）。
  - **修复**（`app/common/logic/AdminLogic.php`）：新增 `clearCategoryCaches()` 同时清 `contract_categories`+`dict_contract_category` 两处缓存键；在 `saveConfig` 的字典项增/删/整删/普通保存分支中当键为 `dict_contract_category` 时调用；`saveContractCategories` 改为双键同写并清缓存，消除读/写键不一致。
  - **验证**：经真实前端端点 `/ajax/admin/config/save` 删除 NDA 后，GET `/contract/create` 的 `<select>` 选项立即不再含 NDA（缓存失效生效）。演示数据已还原。

- **REM-1 移动端工作台「今日提醒」回款逾期显示编号而非标题**：`RemindService::getTodayAlerts`/`scanAlerts` 回款逾期/即将到期、合同到期类提醒文案误用 `{$p['contract_no']}`（查询已 join 取到 `c.title`，只用错字段）。
  - **修复**：4 处提醒文案统一改为合同标题（`《标题》` 包裹）；`scanAlerts` 回款查询补 `c.title` 字段。覆盖工作台（`getTodayAlerts`）与提醒详情页（`scanAlerts`）。
  - **验证**：登录后 GET `/m`，「回款逾期」alert 显示「天府软件-外包人力服务合同」（标题，非 HT 编号）。

- **REM-2 钉钉主动推送通道回款提醒仅含编号**：`RemindService::dispatch`（钉钉主动推送）逾期回款/即将到期回款文案原本仅含 `contract_no`，与工作台通道不一致。
  - **修复**：两处回款查询 `field` 补 `c.title`，文案改为 `编号《标题》` 格式（与已有合同到期类一致）。至此 `getTodayAlerts`/`scanAlerts`/`dispatch` 三条提醒通道全部统一显示标题。
  - **验证**：`php -l` 通过；字段/文案一致性核对确认 `$p['title']` 已可取。

### 验证
- 改动文件 `php -l` 全绿；三脚本 schema parity（含新增 INSERT 校验）/ db 注释 / 视图全局变量卡点无表结构变更零回归；`bash scripts/test.sh` PHPUnit 用例通过。`release.sh` 门禁全绿后出包。

## v2.35.13 (2026-07-29) — 导出 CSV/XLSX 500 真实根因修复（PATCH）

> 本批次修复合同导出（CSV / XLSX）必然 500 的真实代码缺陷。该问题在 v2.35.11 全量审计时被草率归因为「服务器单线程脆弱」，经补强验证读 ThinkPHP error.log 拿到真实异常栈后定位为代码 bug，现修复并随包发布。

### P0 阻塞级（1 项）
- **P0-1 合同导出 chunk 游标缺主键导致 500**：`ContractLogic::eachExportRow` 用 `Db::name('contract')->chunk(500, ...)` 分批导出，但 `field()` 显式排除了主键 `id`。ThinkPHP `chunk()` 内部按 `id` 游标分页（取末批末行主键续查），引用未定义键 → `Undefined array key "id"` 抛异常 → 导出接口（CSV / XLSX）双双 500，全量合同导出不可用。
  - **修复**：`field()` 首项加回 `id` 供 chunk 游标分页使用；在回调内 `unset($row['id'])` 后再交给导出 handler，保持导出数据 10 列与表头顺序严格对齐（否则首列会变成 id、整表错位）。
  - **验证**：临时开 `APP_DEBUG=true` + 读 `runtime/log/20260729_error.log` 复现 `Undefined array key "id"` @ `think-orm/Query.php`；修复后 CSV→200 合法（表头与数据均 10 列、合同编号=HT-2026-07-0001）、XLSX→200 且 `file` 确认为 Microsoft Excel 2007+ 合法 zip；error.log 无新 Undefined id。全代码仅此一处 `chunk(`，无同类隐患。

### 验证
- `php -l` 改动文件全绿；三脚本 schema parity / db 注释 / 视图全局变量卡点无表结构变更、零回归；`bash scripts/test.sh` PHPUnit 用例通过。

## v2.35.12 (2026-07-28) — 全量审计 13 项安全与完整性修复（PATCH）

> 本批次基于 v2.35.11 全模块功能可用性审计（`AUDIT_v2.35.11.html`）发现的 13 项问题，全部修复并完成第二轮代码+API 双验证。覆盖 CSRF、XSS、回款状态完整性、JS 拼写、异常处理、权限门旁路。

### P0 阻塞级（2 项）
- **P0-1 移动端客户操作 CSRF 缺失**：`mobile/customer_detail.php` 认领/释放/转移三处 `fetch POST` 未携带 `X-CSRF-TOKEN` 头，运行时全部返回 403。已加 `'X-CSRF-TOKEN': csrfToken()`。
- **P0-2 admin JSON 注入 XSS**：`admin/index.php` 的 `allUsers/allRoles/allDepts` 的 `json_encode` 缺 `JSON_HEX_TAG`，用户/角色/部门名称含 `</script>` 可突破闭合。已加 `JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT`。

### P1 高危（3 项）
- **P1-3 confirm() 对已 PAID 返回虚假成功**：`PaymentController::confirm()` 对已确认记录无状态检查，`PaymentLogic::confirm()` 静默返回后控制器仍返回"确认收款成功"。已加 `if ($record['status'] === 'PAID') return json_error('该回款已确认收款，无需重复操作')`。
- **P1-4 overdue() 可将 PAID 标记逾期**：`overdue()` 无状态校验，可将已收款项改标逾期、或反复标记。已加 PAID+OVERDUE 双重检查。
- **P1-5 delete() 可删除已收款项**：`delete()` 无状态检查可直接删除 PAID 记录，抹除资金数据且无审计日志。已加 `if ($record['status'] === 'PAID') return json_error('已收款项不可直接删除，请先撤销收款')`。

### P2 中等（8 项）
- **P2-6** `contracts.php:232` `_showLoading` → `showLoading` 拼写修复（网络异常时 loading 卡死）。
- **P2-7** `PaymentController` 的 `add/confirm/revoke` 三方法包 try-catch + `Log::error`（防 DB 异常冒泡 500）。
- **P2-8** `MobileController::reports()` 补 `requirePermission('report:view')`（原与 `reportsSummary()` 不一致，权限门旁路）。
- **P2-9** `contract/detail.php:67` 生效/到期日期 echo 包 `htmlspecialchars()`。
- **P2-10** `contract/create.php:114-115` 日期 input value 包 `htmlspecialchars()`。
- **P2-11** `contract/detail.php:216` 回款日期 innerHTML 包 `esc()`。
- **P2-12** `admin/index.php:566` 审批节点名称 insertAdjacentHTML 包 `esc()`。
- **P2-13** `admin/index.php:52` 同步错误消息 innerHTML 包 `esc()`。

### 验证
- `php -l` 7 改动文件全绿；grep 确认 13 项修复代码落地；API 端点测试（43 端点）admin/employee01 双角色通过；权限隔离正确（employee01→admin/audit/dispatch 均 403）。

## v2.35.11 (2026-07-28) — 移动端体验修复 + 演示数据规范化（PATCH）

> 本批次为 v2.35.10 之后的累积 PATCH 修复（共 6 项：PC 合同详情 500、移动端更多页冗余标题、今日提醒角标/类别底色变暗、首页版权弱化、合同列表收付金额红绿区分、演示数据非法状态 EFFECTIVE），经 `php -l`、三脚本 schema parity / db 注释 / 视图全局变量卡点验证，发布门禁通过后正式发布 v2.35.11。

### 改动 PC 合同详情页 500 修复：移除已精简的 company_profile 列引用（2026-07-28）

- **问题**：v2.35.9 精简 `company_profile` 表（删 `bank_name`/`bank_account`/`tax_no` 等六列）后，`app/view/contract/detail.php` 开票资料区仍访问已删除列，渲染抛 `Undefined array key "bank_name"` → 全局异常 → "服务器开小差了" 500。
- **改动**：`app/view/contract/detail.php` 开票资料区改为仅引用尚存列 `unified_social_credit_code`(统一信用代码) / `short_name`(简称)。
- **验证**：`php -l` 过；PC 合同详情页正常渲染，全项目 grep `bank_name|bank_account|tax_no` 仅文档文字残留（无代码残留）。

### 改动 移动端「更多」页冗余标题删除（2026-07-28）

- **诉求**：更多页顶部"全部模块"标题栏 + 图标多余，宫格图标已足够表达。
- **改动**：`app/view/mobile/more.php` 删除带"全部模块"的 `m-card-hd` 标题栏，保留纯宫格。

### 改动 移动端今日提醒数字角标 / 类别底色变暗修复（2026-07-28）

- **问题**：首页加载完整 Bootstrap CSS，`.badge` 默认暗底（`#212529`）因加载顺序覆盖 `badge-soft-*` 浅色，导致提醒角标/类别底色很暗。用户误以为"点击导致变暗"。
- **改动**：`public/static/css/mobile.css` 提升 `badge-soft-*` 特异性为 `.badge.badge-soft-warn/info/danger`（双类 0,2,0）压过 Bootstrap 默认暗底；**明确告知用户非点击导致**（跳 reminders 页不加载 Bootstrap 显浅色，返回又被覆盖造成错觉）。

### 改动 移动端首页底部版权信息弱化（2026-07-28）

- **改动**：`app/view/mobile/_foot.php` 去 Bootstrap `text-muted(!important #6c757d)` 覆盖，改显式浅色小字 `font-size:11px;color:#a8aeb7;opacity:.9`。

### 改动 移动端合同列表收付金额红绿区分 + 方向标签（2026-07-28）

- **诉求**：合同列表应收(红)/应付(绿)无区分；非交易合同如何展示。
- **改动**：`public/static/css/mobile.css` 新增 `.m-tag-recv`(应收红)/`.m-tag-pay`(应付绿)/`.m-tag-muted`(非交易灰) 方向标签类；`app/view/mobile/contracts.php` PHP 与 JS 双路渲染：交易合同按 `direction` 输出应收红 / 应付绿标签 + `.pay-amt` 红绿金额，非交易合同输出灰色"非交易"标签。
- **演示数据**：`database/seed_demo.php` 新增两条演示合同（应付采购 ¥268,000 / 非交易框架协议 ¥2,000,000），供红绿与灰标效果预览。不升号不打包，随 v2.35.11 出包。

### 改动 演示数据非法状态 EFFECTIVE 修复（2026-07-28）

- **问题**：上轮临时向演示库插入两条合同时误写 `status='EFFECTIVE'`（系统无此合法状态，`STATUS_LABELS` 未收录），移动端列表右上角显示原始码 `EFFECTIVE`。
- **改动**：两条演示合同正式补入 `database/seed_demo.php`，状态改合法 `EXECUTING` + 完整字段；重跑幂等种子脚本重建运行时库（现 18 份合同）。
- **验证**：登录抓取移动端列表，EFFECTIVE 字样 0；应收红 / 应付绿 / 非交易灰标签正确；两条新合同显示"执行中"。

## v2.35.10 (2026-07-28) — 移动端 UI 一致性收尾（M9–M12）+ 品牌色全站统一（PATCH）

> 本批次在 v2.35.9 基础上完成 UI 一致性路线图阶段4 收尾：品牌色统一为 `#0b5ed7`、移动端金额 `.pay-amt` 强化（M9）、标签去硬编码归色板（M10）、关联记录 `.pay-row` 对齐（M11）、重复内联结构抽类（M12：搜索条 `.m-search-bar` / 资料 KV `.m-kv`）；并修复 `.amt` 基类默认灰字色覆盖 `.amt-in/.amt-out` 导致「应收红/应付绿」失效的问题。经 `php -l`、`check_view_globals.sh`、三脚本 schema parity / db 注释卡点验证，发布门禁通过后正式发布 v2.35.10。

### 改动 品牌色全站统一 #0b5ed7（阶段4 P5）

- **改动**：活动项目内全部 `#0d6efd`（Bootstrap 默认蓝）及 `rgba(13,110,253,…)` 收敛为 `#0b5ed7`（与移动端 `--m-brand` 一致），覆盖 dashboard/admin/header/contract 创建与列表、ContractTimelineService 时间轴、app.css 侧栏、app.js toast、contract.js 关键词 chip，共 9 文件；`UI_AUDIT_AND_PLAN.md` 同步标记 P5 落地。
- **验证**：`php -l` 6 文件过；grep 活动项目 `#0d6efd|13,110,253` 仅文档文字残留（无代码残留）。

### 改动 移动端一致性收尾 M9–M12（金额/标签/结构抽类）

- **M9 金额强化**：customer_detail/projects/project_detail/contracts/archive/approvals/approval_create 共 8 视图金额统一加 `.pay-amt`（17px/700，新增独立工具类跨结构生效）。
- **M10 标签去硬编码**：财务/合同详情「剩余回款」小标归 `.m-tag-rest`；资料详情分类/公司标签归 `.m-tag-info`/`.m-tag-muted`；客户详情回款图标底色抽 `.pay-recv`/`.pay-pay` 类替代内联 background/color。
- **M11 关联记录对齐**：customer_detail 回款记录行、project_detail 关联合同行加 `.pay-row`（顶对齐 + 右侧金额/标签纵向堆叠）。
- **M12 重复结构抽类**：6 处搜索条内联 box-shadow 抽 `.m-search-bar`；资料详情结构化字段改 `.m-kv`。
- **附带修复**：移除 `.amt` 基类默认灰字色，使 `.amt-in`/`.amt-out` 红绿在 `class="amt amt-in"` 元素上真正生效（应收红/应付绿）。
- **验证**：`php -l` 13 视图全过；grep 确认硬编码色值（`#fff3e0/#e65100/#e8f1ff;color:#0b5ed7/#f2f3f5;color:#646a73`）与内联搜索条 box-shadow 0 残留；服务重启生效。

## v2.35.9 (2026-07-28) — 移动端体验深化 + 回款/状态/筛选交互修复 + UI 一致性基线（PATCH）

> 本批次为 v2.35.8 之后的累积 PATCH 改进（共 28 项：移动端导航/工作台优化、回款确认交互、合同状态机撤销、筛选卡死修复、详情页布局优化、.fin-card 设计语言落地、阶段0 UI 一致性 P0 修复），经 `php -l`、`check_view_globals.sh`、三脚本 schema parity / db 注释卡点验证，全部发布门禁通过后正式发布 v2.35.9。UI 一致性对齐（阶段1–4）作为后续批次推进。

### 改动 合同详情页回款行对齐修复：pay-row flex-start + aside flex column（2026-07-28）

- **问题**：`.m-row` 默认 `align-items: center`，回款行 `.main`（含按钮）与 `.aside`（金额+标签）被垂直居中，当左侧有按钮时右侧金额标签漂在中间，与 `.fin-card` 的 `flex-start` 对齐不一致。内联 `border-bottom` 覆盖 CSS `:last-child` 导致最后一行也有分割线。
- **改动**：
  1. `mobile.css`：新增 `.pay-row`（`align-items: flex-start !important`）和 `.pay-row .aside`（`display:flex; flex-direction:column; align-items:flex-end; gap:4px`），使回款行金额/标签顶对齐、纵向紧凑排列。
  2. `mobile/contract_detail.php`：4 条回款行 `.m-row` → `.m-row pay-row`，去掉冗余内联 `border-bottom`，按钮统一加 `m-btn-sm` 类。
- **验证**：`php -l` 2 文件过；重启 8901 服务；curl 合同 78 确认 4 行全带 `pay-row` + `pay-amt amt-in` + `m-btn-sm`。不升号不打包，随 v2.35.9 出包。

### 改动 移动端设计语言落地：.fin-card 三段式 + .pay-amt 金额强化（2026-07-28）

- **诉求**：财务页回款卡片预览效果（三段式纯平卡片、18px 金额焦点、无图标占位、留白呼吸）落地到真实页面，并延伸至合同详情回款行和报表统计行，三页金额视觉统一。
- **改动**：
  1. `mobile/finance.php` `loadPayment()` JS 拼串重写：`.m-card > .m-card-bd > a > .m-row(.pic+.main+.aside)` 三层嵌套 → 纯平 `<a class="fin-card">` + `.fin-top`/`.fin-mid`/`.fin-act` 三段。金额 15px→18px 700 bold，去 42px 硬币图标占位，阅读更顺畅。
  2. `mobile.css`：新增 `.fin-card`/`.fin-top`/`.fin-t`/`.fin-tags`/`.fin-mid`/`.fin-amt`/`.fin-meta`/`.fin-act` 共 8 个 class。新增 `.m-row .aside .pay-amt { font-size:17px;font-weight:700; }` 用于合同详情/报表行金额强化。
  3. `mobile/contract_detail.php`：回款计划行金额加 `.pay-amt`（15px 600→17px 700），与 `.fin-amt` 视觉对齐。
  4. `mobile/reports.php`：回款概览 + 收支方向共 6 处金额加 `.pay-amt`（15px 600→17px 700）。
- **验证**：`php -l` 3 文件过；curl 合同详情 4 条回款行全带 `pay-amt amt-in` ✓、报表 6 处金额全带 `pay-amt` ✓；jsdom 验证财务页 13 条卡片拼串无旧结构残留。不升号不打包，随 v2.35.9 出包。

### 改动 移动端回款卡片：「剩余回款」小标 + 逾期确认按钮标红 + 演示数据补测试记录（2026-07-28）

- **诉求**：① 部分确认拆出的剩余待收子记录（`parent_id>0`）在回款卡片上无标识，用户无法分辨哪条是拆分后的剩余款；② 逾期回款的确认按钮与待收按钮颜色一致（都绿色），警示性不足；③ 缺演示数据供预览。
- **改动**：
  1. `mobile/contract_detail.php`：回款卡片对 `parent_id>0` 的记录在状态标签旁加橙色「剩余回款」小标（`background:#fff3e0;color:#e65100`）。
  2. `mobile/finance.php`：回款卡片 JS 渲染对 `r.parent_id>0` 加同款「剩余回款」小标；OVERDUE 状态确认按钮改为红色（`background:#d63031`，文字「确认到账（逾期）」），与待收绿色按钮区分。
  3. `database/seed_demo.php`：新增部分确认测试回款——合同9（智联科技-CRM）第二期 30万 模拟部分确认 12万，母记录 PAID/120000，剩余 18万 拆为 PENDING 子记录（`parent_id` 关联母记录），供合同详情页/财务页预览「剩余回款」小标。
- **验证**：`php -l` 3 文件过；数据库确认母记录(id=41 PAID/120000)+子记录(id=52 PENDING/180000 parent_id=41)；合同详情页 curl 验证「剩余回款」小标渲染 + 逾期红色按钮(`background:#d63031`/`确认到账（逾期）`)；jsdom 实跑财务页 4 张卡片渲染正确（剩余回款小标✓ / 逾期红按钮✓ / 待收绿按钮✓ / PAID无按钮✓）。不升号不打包，随 v2.35.9 出包。

### 改动 移动端财务回款卡片落地 .fin-card 三段式设计（2026-07-28）

- **诉求**：`preview_payments.html` 的模拟渲染效果落地到真实页面——旧 `.m-card` 三层嵌套 + 42px 图标占位 + 金额 15px 被挤压，阅读效率低。
- **改动**：
  1. `mobile/finance.php`：`loadPayment()` 卡片拼串从 `.m-card > .m-card-bd > a > .m-row(.pic+.main+.aside)` 改为纯平 `<a class="fin-card">` + `.fin-top`/`.fin-mid`/`.fin-act` 三段式；去除 `.pic` 图标占位；计数器 `querySelectorAll` 同步改 `.fin-card`。
  2. `static/css/mobile.css`：新增 `.fin-card` 卡片样式（纯白圆角 10px/内边距 14px 16px/微阴影）、`.fin-top` 标题行（合同名 14px bold + 状态标签列）、`.fin-mid` 金额行（金额 18px 700 bold 视觉焦点 + 日期 12px 灰色）、`.fin-act` 操作按钮行右对齐。
- **验证**：`php -l` 过；AJAX 13 条回款数据模板拼串验证全部通过（无旧 `.m-card`/`.pic`/`.m-card-bd` 残留，5 带按钮 1 剩余回款小标 8 PAID 无按钮）。不升号不打包，随 v2.35.9 出包。

### 改动 移动端合同详情页底部操作栏按钮样式修正（2026-07-28）

- **诉求**：执行中合同的底部「已完成」按钮用了 `m-btn-brand` 实心底色，看起来像已点击/已激活，四个状态变更按钮风格不统一。
- **改动**：`mobile/contract_detail.php` L311——将 `COMPLETED` 从 `in_array(..., ['SIGNED','APPROVED','EXECUTING','COMPLETED'])` 的品牌色组移除，四按钮全部统一 `m-btn-ghost` 灰色未点击样式。
- **验证**：`php -l` 过；curl 合同 id=78 底部四按钮全部 `class="m-btn m-btn-ghost"`。不升号不打包，随 v2.35.9 出包。

### 改动 移动端执行中合同底部按钮去品牌色（2026-07-28）

- **诉求**：执行中合同详情页底部四按钮，「已完成」用 `m-btn-brand` 实心底色，看起来像已点击/已激活，用户易误触。
- **改动**：`mobile/contract_detail.php` 按钮样式分派逻辑，COMPLETED 从 brand 组(SIGNED/APPROVED/EXECUTING)移除，四个按钮(已完成/已到期/已终止/已归档)统一 `m-btn-ghost`（灰色未点击样式）。
- **验证**：`php -l` 过；curl 合同 id=78（EXECUTING）底部四按钮全部 `class="m-btn m-btn-ghost"`，`m-btn-brand` 仅出现在确认收款弹窗按钮。不升号不打包。

### 改动 公司主体(company_profile)字段精简：仅保留 全称 / 简称 / 代码（2026-07-27）

- **诉求**：本公司主体维护页字段过多（税号、开户行、账号、地址、电话、法定代表人），而这些开票/账户信息实际已由「资料库 → 开票资料」承载，重复维护无意义。
- **方案**：`company_profile` 仅保留 `name`(全称)、`short_name`(简称)、`unified_social_credit_code`(统一社会信用代码 = 公司代码)，并保留功能开关 `is_default`(默认主体)——它支撑「新建合同自动带出签约主体」与「本公司」快捷按钮，删除会回归该能力。
- **改动清单**：
  1. 三脚本 1:1 同步：删除 `tax_no`/`bank_name`/`bank_account`/`address`/`tel`/`legal_rep` 六列；`init.sql`/`init_mysql.php`/`init_sqlite.php` 的 CREATE TABLE 与种子 INSERT 一并精简（注释同步）。
  2. `CompanyLogic::getList` / `getManageOptions` 的 `field()` 缩窄为 `id, name, short_name, unified_social_credit_code, is_default`。
  3. `CompanyController::save` 移除 6 个冗余入参；`app/view/company/index.php` 表格与表单删除对应列 / 输入框；`public/static/js/company.js` 回填逻辑同步移除。
  4. 新增 `database/migration_v2.35.9_company_profile_trim.sql`：MySQL 8.0 逐列 `information_schema` 判重 + 动态 SQL 幂等 DROP（MySQL 不支持 `DROP COLUMN IF EXISTS`）。
  5. 本地演示库 `runtime/data/contract.db` 已同步 `DROP COLUMN`，保证演示包一致。
- **验证**：`check_schema_parity.sh`(26 表 / 264 字段)、`check_db_comments.sh`(0 缺失)、`php -l`、`check_view_globals.sh`(59 视图) 全部通过。不升号不打包。

### 改动 移动端工作台「数据看板」新增「资料库」入口（查看型 MVP，2026-07-27）

- **诉求**：移动端需在显眼位置提供资料库入口；产品评估后采纳"放数据看板（模块浏览区）而非快捷操作（动作型入口）"的建议，置于该区最右侧。
- **方案（仅查看，上传/编辑仍走 PC）**：
  1. `route/app.php` 新增 `/m/resource`(列表) 与 `/m/resource/<id>`(详情) 两条移动端路由。
  2. `MobileController::resource()`：复用 `ResourceLogic::getList` 取首屏列表；`resourceDetail($id)`：取单条 + `decodedContent` 解析开票资料结构化字段；两方法均 `requirePermission('library:view')` 兜底。
  3. 新建 `app/view/mobile/resource.php`(列表：搜索 + 分类 chips，前端复用 PC 端 `/resource/list` AJAX 重渲染) 与 `app/view/mobile/resource_detail.php`(详情：标题/分类/主体/说明 + 开票资料字段卡 + 附件内嵌预览)。
  4. 附件预览复用既有 `/m/doc-preview`(PDF.js 内嵌，钉钉 WebView 不跳出)：登录态拼 `/preview?p=<file_url>` 直接跳预览页，无需免登 token。
  5. 工作台 `index.php`「数据看板」区末尾(最右侧)加「资料库」入口，按 `library:view` 权限显隐(同 `$can_pay` 模式)。
- **验证**：`php -l`、`check_view_globals.sh`(61 视图) 通过。不升号不打包。

### 改动 移动端导航优化 Phase1+2（消除二级页全灰 + 数据看板按角色排序 + 去冗余，2026-07-28）

- **诉求（基于 MOBILE_UX_EVALUATION 实测）**：移动端浏览型页（财务/报表/项目/归档/资料库）进入后底部 Tab 全灰迷路；数据看板模块硬编码顺序未按角色频率；财务双入口（快捷操作"登记回款"与数据看板"财务统计"同页）；管理员全公司今日提醒首屏铺满。注：管理员移动管理入口经产品决策确认不做（PC 端处理）；财务/报表已按 data_scope 收敛，非 admin 不见全公司数字，故"信息最小化"保持现状。
- **方案**：
  1. **导航锚点（A）**：`mobile_tabbar()` 新增第 5 个 `更多` Tab；新增 `MobileController::more()` + `app/view/mobile/more.php` 聚合页（按权限裁剪 + 角色频率排序）；`route/app.php` 增加 `/m/more`。次级页 `$tab` 修正消除全灰：`finance/reports/projects/project_detail/resource/resource_detail → more`；`archive → contract`（归档属合同子集，兼消冗余）；`supplier_detail/supplier_form/suppliers → customer`。
  2. **数据看板排序（B）**：`MobileController::index()` 组装 `$databoard_modules`（管理员 财务概览→报表概览→项目列表→资料库→归档合同；普通用户 财务概览→归档合同→报表概览→项目列表→资料库，按 `project:view`/`library:view`/`contract:view` 裁剪），`index.php` 数据看板区改 `foreach` 渲染。
  3. **去冗余（C）**：数据看板财务入口标签由"财务统计"改为"财务概览"，与快捷操作"登记回款"（动作）语义区分，消除同页双入口混淆；管理员今日提醒 >5 条默认折叠"展开全部"（非 admin 保持）。
- **验证**：全部改动 `php -l` 通过；`check_view_globals.sh`(62 视图) 通过；`$tab=''` 全灰残留 0 处。不升号不打包。

### 改动 移动端工作台布局二次优化（审批入口归位 + 更多页宫格化，2026-07-28）

- **诉求**：① 底部菜单「审批」Tab 与工作台顶部「待我审批」概览功能重叠，应移除菜单入口；② 审批改为放「快捷操作」；③ 「今日提醒」上移到「快捷操作」上方；④ 「更多」页改为图标 + 文字宫格展示。
- **方案**：
  1. **移除菜单审批 Tab**：`mobile_tabbar()` 删除 `todo(审批)` Tab，底部导航变为 `工作台/合同/客户/更多` 4 项；审批入口统一由工作台「快捷操作 → 审批」与「更多」页进入。审批页 `approvals/approval_detail/approval_create` 的 `$tab` 由 `todo` 改为 `''`（无高亮）。
  2. **审批进快捷操作**：`index.php` 快捷操作新增「审批」卡片（`/m/approvals`，图标 `bi-list-check`），按 `can_approve`（管理员或具 `approval:view` 权限）显隐——与原菜单 Tab 的权限裁剪一致；`MobileController::index()` 将 `can_approve` 由写死 `true` 改为按权限计算。
  3. **今日提醒上移**：`index.php` 将「今日提醒」卡片从「数据看板」之后移到「快捷操作」之前（折叠/展开逻辑不变）。
  4. **更多页宫格化**：`mobile.css` 新增 `.palace-grid`/`.palace-item`（4 列图标 + 文字宫格），`more.php` 改用该样式（原 `.quick-grid`/`.quick-item` 仅定义于 index.php 内联样式，更多页此前未正确渲染为宫格）；`MobileController::more()` 财务标签由「财务统计」统一为「财务概览」，与数据看板一致。
- **验证**：`php -l`（9 个改动文件全过）、`check_view_globals.sh`(62 视图) 通过；移动端 `$tab='todo'` 残留 0 处；`more.php` 已无 `quick-grid`/`quick-item` 残留。不升号不打包。

### 改动 移动端工作台「数据看板」与「更多」重叠治理（方案A：删除数据看板，2026-07-28）

- **问题（产品评估）**：工作台「数据看板」卡片与底部「更多」页呈现**完全相同**的 5 个模块入口（财务概览/报表概览/项目列表/资料库/归档合同，且同为角色频率排序），属纯冗余——两个入口同一份内容，仅形态不同（卡片 vs 整页宫格）。
- **方案（采纳 方案A：删除工作台数据看板，保留更多为唯一模块浏览入口；远期规划 方案B）**：
  1. `index.php` 删除「数据看板」卡片；`MobileController::index()` 移除 `$databoard_modules` 组装逻辑与仅该卡使用的 `$can_view_project`/`$can_resource` 赋值（保留 `is_admin` 供今日提醒折叠）。
  2. 工作台回归「概览数字（待我审批/今日提醒/我的合同）+ 快捷操作 + 今日提醒」的聚焦定位；模块浏览统一收口到「更多」（底部 Tab 第 4 项，图标 + 文字宫格），导航高亮修复（`finance/reports/projects/resource → more`）不受影响。
  3. **远期规划（方案B，暂不实施）**：未来若需让工作台更有信息密度，可将「更多」保留为纯导航，而把工作台原数据看板区升级为**真实指标卡**（财务概览→应收/应付金额、项目列表→进行中数、报表概览→本月合同额…），与「更多」的导航角色彻底区分。指标已受 `data_scope` 约束，安全。该增强独立排期，不阻塞本次。
- **验证**：`php -l`(index.php/MobileController.php 全过)、`check_view_globals.sh`(62 视图) 通过；`index.php` 已无 `databoard`/`can_view_project`/`can_resource` 引用残留。不升号不打包。

### 改动 审批流程管理：停用流程可见并提供恢复入口（2026-07-28）

- **问题（用户反馈）**：PC 端审批流程管理列表仅展示启用中流程（`getEnabledFlows`），停用（软删除 status=0）的流程从列表消失且无法恢复，成为"孤儿"数据。


- **方案**：① 新增 `ApprovalLogic::getAllFlows()`（含 status=0，停用项排后）+ `AdminLogic::getAllFlows/enableFlow`；② `AdminController::flow()` 改用 `getAllFlows`，列表展示全部流程并带「启用/停用」徽标；③ 新增 `restoreFlow` AJAX（`/ajax/admin/flow/restore`）+ `AdminLogic::enableFlow(status=1)`，停用流程行显示「恢复」按钮；④ `admin/index.php` 流程表格加恢复按钮 + `restoreFlow()` JS（编辑弹窗本就可在状态选「启用」，恢复按钮为更直观点入口）。
- **验证**：`php -l`(ApprovalLogic/AdminLogic/AdminController/route/app.php) 全过；`check_view_globals.sh`(62 视图) 通过。不升号不打包。

### 改动 回款管理列表：合同标题为主、编号为辅（2026-07-28）

- **诉求（用户反馈）**：PC 与移动端回款管理列表应以合同标题为主、合同编号为辅展示。
- **方案**：`app/view/finance/index.php` 回款列表行改为「标题（主）+ 编号（次，muted）」；`app/view/mobile/finance.php` 回款卡片 `.t`/`.s` 互换为「标题 / 编号」。
- **验证**：`check_view_globals.sh`(62 视图) 通过。不升号不打包。

### 改动 回款管理列表：行内「确认收款」操作（2026-07-28）
- **问题（用户反馈）**：逾期/待收回款在回款列表无直接操作入口，须钻进合同详情才能确认收款；若误用「登记回款」新建一条，旧的逾期记录不会消失，列表仍显示逾期，造成困惑。
- **根因**：「逾期」是**存储状态**（`markOverdue` 写入），确认收款（`PaymentLogic::confirm`）才翻 `PAID`；列表仅展示、无操作。
- **方案**：PC 回款管理列表（`finance/index.php`）与移动端回款列表（`mobile/finance.php`）对 PENDING/OVERDUE 行增加「确认收款」按钮，弹层录入金额（默认全额、支持部分确认，剩余自动拆为待收子记录）/方式/日期，调用既有 `/ajax/payment/confirm`；成功后刷新列表，原记录翻 PAID、逾期消失。
- **说明**：正确记录逾期回款到账的方式是「在逾期记录上点确认收款」，不要对同笔款项另建「登记回款」，否则旧逾期记录会残留。
- **验证**：`php -l` + `check_view_globals.sh`(62 视图) 通过；本地端到端验证「标记逾期 → 确认收款(部分 1500/2000)」状态流转正确（母记录 PAID，剩余 500 自动拆 PENDING 子记录）。不升号不打包。

### 改动 移动端财务/报表：月/季/年周期筛选（2026-07-28）
- **诉求（用户反馈）**：移动端财务统计与报表概览仅展示累计值，需增加本月/本季/本年筛选显示能力。
- **方案**：① `common.php` 新增 `db_quarter_expr`/`db_year_expr`/`period_range` 跨库辅助；② `FinanceLogic::getSummaryByPeriod`、`ReportLogic::getMobileSummaryByPeriod` 按周期收敛（回款按 planned/actual_date、合同按 created_at，数据权限与累计版一致）；③ 新增 AJAX `/ajax/mobile/finance-summary`、`/ajax/mobile/reports-summary`；④ 移动端 `finance.php`/`reports.php` 加周期 chips（本月/本季/本年/累计），切换时 fetch 重渲染数字（合同总数/经营总额标签随周期变为「X新增」，回款概览/收支方向按周期收敛）。
- **验证**：`php -l`(common.php/ReportLogic/FinanceLogic/MobileController/两视图) + `check_view_globals.sh`(62 视图) 通过；本地三周期 AJAX 返回正确（本月应收 12万 vs 累计 132.4万，证明周期收敛生效）。演示库 16 份合同均建于 2026-07，故合同类指标各周期同值、仅回款类随计划/实际日期分化，属数据特征非缺陷。不升号不打包。

### 改动 移动端合同详情页：逾期回款确认按钮缺失修复（2026-07-28）
- **问题（用户反馈）**：从「今日提醒」点逾期回款提醒跳转到移动端合同详情页（`/m/contract/:id#payments`），逾期回款记录**没有确认收款按钮**，无法操作。PC 详情页 `contract/detail.php` 对 OVERDUE 本就有按钮，但用户走的是移动端路径（回款列表页 `/m/finance` 的确认入口不在该路径上）。
- **根因**：移动端详情页 `app/view/mobile/contract_detail.php` 回款区块的确认按钮 `if` 条件仅判 `PENDING`，**漏了 `OVERDUE` 分支**——逾期记录既不在 PENDING 也不在 PAID 分支，故无任何按钮。
- **方案**：判断条件改为「`PENDING` 或 `OVERDUE` 均显示确认到账按钮」，逾期按钮用红色警示样式 +「确认到账（逾期）」文案突出；确认逻辑复用既有 `confirmPayment()`（调 `/ajax/payment/confirm`，成功后 `location.reload()` 刷新，状态可见）。复杂条件提前算成 `$confirmable`/`$isOverdue`/`$isPaid` 变量，模板判断行保持极简（规避 `($p['status'] ?? '') === ... || ...` 组合在部分 PHP 解析路径下的括号计数歧义——直接写会触发 `Unmatched ')'` 解析错误）。
- **说明**：至此 PC 详情页、移动端详情页、移动端回款列表页三处均可确认逾期/待收回款。
- **验证**：`php -l` + `check_view_globals.sh`(62 视图) 通过；本地端到端验证——给合同造 OVERDUE 记录后访问 `/m/contract/:id` 返回 200 且渲染出 1 个「确认到账（逾期）」按钮，测试数据已清理。不升号不打包。

### 改动 移动端财务页：周期筛选全选 + 回款面板隐藏 + 列表样式修复（2026-07-28）
- **问题（用户反馈）**：点击财务统计页周期筛选（本月/本季/本年/累计）任意项后，4 个筛选项被全部选中；同时下方回款列表样式异常（面板被隐藏、列表错位）。
- **根因**：周期筛选 chips 与维度切换 chips 共用 `.m-chip` 类，tab 切换事件 `document.querySelectorAll('.m-chip')` 把周期 chips 也绑上了 `switchTab`；周期 chip 无 `data-tab` → `this.dataset.tab` 为 `undefined`，`switchTab(undefined)` 内 `c.dataset.tab === tab` 对周期 chips 恒 `true`(undefined===undefined) → 4 项全标 active（"全选"）；且 `tab!=='payment'` 使 `panel-payment` 被设 `display:none` → 回款面板隐藏（样式异常根因）。
- **方案**：① tab 切换选择器与 `switchTab` 内部收窄为 `.m-chip[data-tab]`，周期 chips 只由独立 `data-finperiod` 监听处理（单选 + 拉取 `finance-summary`），不再参与 tab 切换；② 补移动端 CSS 缺失类：`.m-btn-sm`(确认收款按钮此前撑满整行 48px 高)、`.amt`(回款金额此前无加粗)，并移除回款卡片金额冗余的 `in` 类。
- **验证**：`php -l`(finance.php) + `check_view_globals.sh`(62 视图) 通过；用 jsdom 实跑渲染页、mock fetch 模拟点击周期 chip——修复后"仅单选、panel 未被隐藏"（修复前为"全选 + 隐藏"）。不升号不打包。

### 改动 移动端财务页回款列表卡片**嵌套底图**修复（2026-07-28）
- **问题（用户反馈）**：财务统计→回款管理列表，从上往下**第二条开始每条都被重新套一层白底（底图）**，越往下越显示不全。
- **根因**：`app/view/mobile/finance.php` `loadPayment()` 拼接每张回款卡片时，`<div class="m-card">` 只开了未闭合——字符串末尾仅一个 `</div>`（只关掉 `m-card-bd`）。下一张卡片经 `insertAdjacentHTML('beforeend', h)` 插入时，落入上一张未闭合的 `m-card` 内部，形成**层层嵌套**；叠加 `.m-card{overflow:hidden; margin:var(--m-gap)}`，越深的卡片被多套一层白底并不断内缩裁剪，即"套底图、显示不全"。
- **方案**：卡片字符串末尾补一个 `</div>` 正确闭合 `m-card`（`...</div></div>`）。逐标签配平核对：13 个开标签(m-card/m-card-bd/a/m-row/pic/main/t/s/aside/flex/amt/desc/act)均有对应闭合，卡片变为同级兄弟节点。
- **验证**：`php -l`(finance.php) 通过；标签结构静态配平确认无嵌套。本机 bash/jsdom 实跑验证因工具临时故障未能执行，待恢复后补跑（预期：插入 3 张卡片，`#list-payment` 的直接子元素均为 `m-card` 且彼此不嵌套）。不升号不打包。

### 改动 移动端合同详情页：确认收款改为弹窗录入（不再"直接完成"，2026-07-28）
- **问题（用户反馈）**：合同详情页（今日提醒点进去的回款/逾期回款）点「确认到账」直接完成了，没有弹窗录入回款信息。
- **根因**：`app/view/mobile/contract_detail.php` 的 `confirmPayment(pid)` 走 `confirmAndPost` → `mConfirm`（仅"是否确认"的 Yes/No 对话框），直接 `POST /ajax/payment/confirm` 只带 `{id}`，未传金额/方式/日期 → 后端默认全额确认，表现为"直接完成"。而回款列表页 `finance.php` 用的是 `payConfirmMask` 底部弹层（有金额/方式/日期输入）——详情页缺这个 modal。
- **方案**：① 两个确认按钮 `onclick` 改为 `confirmPayment(id, amount)`（带入应收金额，弹层默认填满、支持部分确认）；② 在详情页补 `payConfirmMask` 弹层 HTML（与列表页同款：收款金额/收款方式/实际日期），并改写 `confirmPayment` 为打开该弹层，提交逻辑复用 `/ajax/payment/confirm`（`confirm_amount`/`payment_method`/`actual_date`），成功 `toast` + 关闭弹层 + 刷新；③ 撤销回款仍走 `confirmAndPost`（破坏性操作，Yes/No 合理）。
- **验证**：`php -l`(contract_detail.php) + `check_view_globals.sh`(62 视图) 通过；本地端到端——造 OVERDUE 记录访问 `/m/contract/:id` 返回 200，渲染出 `payConfirmMask` 弹层、`onclick="confirmPayment(34, 7777)"`（带金额）；页内仅 1 个确认收款按钮走弹层，其余 2 处 `confirmAndPost` 为合同状态变更与撤销回款（合理的 Yes/No），测试数据已清理。不升号不打包。

### 改动 移动端合同详情页：确认到账点击无反应修复（script 标签未闭合，2026-07-28）
- **问题（用户反馈）**：执行中合同的回款计划点「确认到账」无反应（弹窗不弹出）。
- **根因**：上一条改动在 `contract_detail.php` 补 `payConfirmMask` 弹层时，第一个 inline `<script>` 块（含 `toggleContent`/`statusTransition`，L318 开）**漏写 `</script>` 闭合**，导致弹层 `<div class="m-sheet-mask">` 及第二个 `<script>` 被吞进第一段脚本内容 → 浏览器解析 `Unexpected token '<'` → 整段脚本（含 `confirmPayment`）执行失败 → 点击按钮 `confirmPayment is not defined` → 无反应。源码 `<script>` 开 2 次、闭仅 1 次（L408）。
- **方案**：在 `statusTransition` 函数后、弹层 HTML 注释前补 `</script>` 闭合第一段脚本（L335）。修复后 `<script>` 开闭配平：L318→L335、L357→L409。
- **验证**：`php -l` 通过；用 jsdom 实跑渲染页（stub `csrfToken`/`toast`/`confirmAndPost`，模拟点击按钮）——修复前 `SyntaxError: Unexpected token '<'` + `confirmPayment is not defined` + 弹层不打开；修复后**无 JS 错误、`payConfirmMask` 节点存在、点击后加 `show` 类弹层打开、金额自动填入**。测试 PENDING 记录已清理。不升号不打包。

### 改动 移动端合同详情页回款计划标题栏视觉统一（2026-07-28）

- **问题**：回款计划标题栏 `.m-card-hd` 带 `border-bottom` 分割线 + 15px/600 字重，与 `.fin-card` 设计语言（无边线、14px/500 扁平）不一致。
- **方案**：该标题栏加 `border-bottom:none; padding-bottom:8px`，文字改 14px/500（对齐 `.fin-t`）；`.m-card-bd` 补 `padding-top:0` 补偿间距。其余卡片标题栏保持原 `.m-card-hd` 不变。
- **验证**：`php -l` 通过；服务重启。

### 改动 合同状态机撤销 + 已归档按钮误显修复（2026-07-28）

- **问题**：① 已完成/已到期/已终止后无撤销入口（只能前进到已归档）；② 已归档后底部显示品牌色「执行中」按钮，像已点击。
- **根因**：状态机 `COMPLETED`/`EXPIRED`/`TERMINATED` 只能前进到 `ARCHIVED`，无反向跃迁；前端 `EXECUTING` 目标按钮统一品牌色未区分来源。
- **方案**：① `ContractLogic::TRANSITIONS` 为 `COMPLETED`/`EXPIRED`/`TERMINATED` 增加 `STATUS_EXECUTING` 反向跃迁；② `contract_detail.php` 状态变更按钮按来源状态区分——`ARCHIVED→取消归档`、`COMPLETED→取消完成`、`EXPIRED→取消到期`、`TERMINATED→取消终止`，均为 `m-btn-ghost`，正向推进仍 `m-btn-brand`。
- **验证**：`php -l` 两文件过；服务重启。

### 改动 移动端合同列表筛选卡「处理中」弹窗卡死修复（2026-07-28）

- **问题**：合同列表点击「草稿」「待审批」等筛选 chip，页面永远卡在 loading 弹窗。
- **根因**：`_foot.php` 末尾加载的 `app.js` 用计数器版 `showLoading(text)` 覆盖了 `mobile-common.js` 的布尔版 `showLoading(boolean)`。点击 chip → `showLoading(true)` 被截胡（计数器+1 仅显示）→ 回调 `showLoading(false)` 再+1 → loading 永不消失。
- **方案**：① `mobile-common.js` 定义 `showLoading` 后设 `window.__mobileShowLoading = true`；② `app.js` 的 `showLoading`/`hideLoading` 整段包 `if (!window.__mobileShowLoading)`，移动端跳过覆盖。
- **验证**：`node -c` 两 JS 过；服务重启。`confirmAndPost` 等内部调用同样受益。

### 改动 移动端合同详情页布局优化：基本信息压缩 + 概要/附件上移（2026-07-28）

- **问题**：基本信息卡片占用面积过大，合同概要和附件被压在页面最底部，进入即看不到重点。
- **方案**：① 新增 `.info-compact` CSS Grid 两列布局（标签 12px 上置、值 14px/500 下置）；② 基本信息由 8 行 `.m-kv` 改为紧凑网格，去掉导航栏已显示的状态行，7 字段→约 4 行，省 ~50% 垂直空间；③ 合同概要 + 附件区块从页底上移到基本信息下方、甲乙方上方。
- **最终区块顺序**：hero → 基本信息(compact) → 合同概要 → 附件 → 甲乙方 → 回款计划 → 审批记录 → 自定义字段。
- **验证**：`php -l` 通过；区块顺序 grep 确认。

### 改动 移动端状态变更按钮统一 ghost 风格（2026-07-28）

- **问题**：已通过/已签署合同详情页底部状态变更按钮带 `m-btn-brand` 实心底色，易被误解为已点击。
- **方案**：`contract_detail.php` 状态变更按钮组全部改为 `m-btn-ghost`（灰框未点击样式），不再区分正向/反向。
- **验证**：`php -l` 通过；服务重启。

### 改动 移除 SIGNED 签署状态相关按钮露出（2026-07-28）

- **背景**：合同签署功能已软移除。
- **方案**：① `ContractLogic::TRANSITIONS` 移除 `APPROVED→SIGNED` 跃迁（`APPROVED` 现直接到 `EXECUTING`/`TERMINATED`/`ARCHIVED`）；② `contracts.php` 状态筛选 chip 跳过 `SIGNED`（`if ($k === 'SIGNED') continue`）；③ `STATUS_LABELS` 保留 `SIGNED`（已有 SIGNED 合同状态标签正常显示），SIGNED 自身跃迁保留（已有合同可继续推进）。
- **验证**：`php -l` 两文件过；`getAvailableActions` 直接读 `TRANSITIONS`，改一处即生效。

### 改动 移动端新建合同金额框默认值 0.00 易误操作修复（2026-07-28）

- **问题**：新建合同时金额输入框预填 `0.00`，点进去输入易误叠加为 `0.005000` 之类。
- **根因**：`contract_form.php` 第 111 行 `value="<?=...$contract['amount'] ?? '0.00'?>"`，新建时 `$contract` 为空触发 `?? '0.00'`。
- **方案**：改为 `value="<?=...$contract['amount'] ?? ''?>"`（新建留空）+ `placeholder="请输入金额"`（编辑时仍回显已有金额）；JS 提交逻辑（非交易时 `amount=0`）不依赖默认 `0.00`。
- **验证**：`php -l` 通过；服务重启。

### 改动 移动端 UI 一致性审计 + 阶段0 P0 类未定义修复（2026-07-28）

- **背景**：全面审计移动端 24 视图 + PC 端 35 视图渲染风格（交付 `UI_AUDIT_AND_PLAN.md`），对齐 `.fin-card` 设计语言；用户拍板 3 决策：金额=应收红/应付绿、PC 端仅做标签+按钮 ghost 化、品牌色统一 `#0b5ed7`。
- **阶段0 P0 修复（5 个类未定义，grep 核实后落地）**：
  1. `customer_detail.php` 认领/释放/转移按钮 `m-btn-primary`→`m-btn-brand`、`m-btn-outline`→`m-btn-ghost`（原无底色）。
  2. `approval_create.php` 审批标签 `m-tag-secondary`→`m-tag-info`（原无底色）。
  3. `mobile.css` 新增 `.m-btn-block{width:100%}`（审批确认提交原不撑满）。
  4. `project_detail.php` footer `m-card-ft`→`.m-loadmore`（原无样式）。
  5. `mobile.css` 上提 `index.php` 私有的 `.m-item`/`.empty`/`.badge-soft-*` 供 `reminders.php` 等全局复用，并清理 `index.php` 冗余私有定义（遵循「组件 CSS 应收归 mobile.css」）。
- **验证**：`php -l` 4 文件过；grep 确认 mobile.css 9 个新类、4 视图类替换、服务重启。阶段1–4（标题栏扁平化/金额 token/PC ghost/收尾）作为后续批次。

## v2.35.8 (2026-07-27) — 移动端 PDF 钉钉内预览 + 版本号固化 + 零停机部署体系 + 交付文档精简中文化（PATCH）

> 本批次为 v2.35.7 之后的累积 PATCH 改进（改动1-4 共 4 项），经 `php -l`、`check_view_globals.sh`、三脚本 schema parity / db 注释卡点、shell 语法检查验证，全部发布门禁通过后正式发布 v2.35.8。  
> **（打包优化，同版本重打）**：发布包仅随附 5 份**中文化**交付文档，剔除 28 份内部研发/设计/审查/审计报告，并将演示文档合并入部署说明。详见下文「打包优化」。

### 打包优化 交付文档精简 + 标题中文化（2026-07-27）

- **问题**：原发布包把根目录全部 ~35 个 `.md` 一股脑塞入（含大量英文标题的内部研发/设计/审查/审计报告），交付文档散乱、标题未中文化。
- **修复（`scripts/release.sh`）**：
  1. 新增 `DOC_EXCLUDES`：剔除 `DESIGN_*`/`DEV_PLAN_*`/`FIX_PLAN_*`/`MOBILE_*`/`PRODUCT_*`/`REVIEW_*`/`TEST_REPORT_*`/`ROADMAP`/`AUDIT_*`/`PM审查报告_*`/`MIGRATION_SQLITE_TO_MYSQL` 等内部报告（仅开发期沉淀，非客户交付物）。
  2. 新增 `DELIVER_DOCS` 映射 + `build_staging()`：包内交付文档改名中文化——`CHANGELOG.md→迭代日志.md`、`DEPLOY.md→部署说明.md`、`DEPLOY_ZERO_DOWNTIME.md→零停机部署说明.md`、`DINGTALK_SSO_GUIDE.md→钉钉免登配置说明.md`、`VERSION.md→版本记录.md`；改名后自动重写文档内/ `deploy.sh` 内交叉引用，避免悬空。
  3. 合并精简：删除冗余的 `DEPLOY_DEMO.md`、`README_DEMO.txt`（内容已并入 `部署说明.md` §四/§十），演示注入仅保留 `demo.env.example`。
  4. 桌面交付目录改为每次重建（`rm -rf` 后 `mkdir`），并直接从已构建包内提取中文化文档，保证桌面与包内完全一致。
- **交付文档集（最终）**：`部署说明.md` / `零停机部署说明.md` / `钉钉免登配置说明.md` / `迭代日志.md` / `版本记录.md` + `demo.env.example` + `MANIFEST.txt`。

### 改动1 权限会话刷新迁移脚本改为幂等写法（2026-07-27）

- **背景**：`database/migration_v2.35.4_perm_version.sql`（RV-01 给 `user` 加 `perm_version` 列）原为普通 `ALTER TABLE ... ADD COLUMN`，重复执行会报“重复列”错误，存量库二次补列不安全。
- **陷阱**：用户要求改为 `ADD COLUMN IF NOT EXISTS`，但该语法为 **MariaDB 扩展，MySQL 8.0 原生不支持**（会报 1064 语法错误），本项目生产为 MySQL，直接用反而跑不起来。
- **修复**：改为项目约定标准的 `information_schema` 判重 + 动态 SQL（`PREPARE/EXECUTE/DEALLOCATE`）幂等写法——列不存在才 `ALTER`，已存在则 `SELECT ... 跳过`，兼容 MySQL 8.0、可重复执行；`SET @db = DATABASE()` 自动取当前库名，执行时需 `USE` 目标库或 `mysql -h<H> -u<U> -p <DB> < 本文件`。三脚本未动，schema parity 26表/270字段、中文注释 0 缺失均通过，无 DB 结构变更、无新迁移脚本。
- **状态**：未升号未打包，源文件已改；v2.35.7 已发布包内仍是旧非幂等版，如需包内同步幂等需重打包（会变更包 SHA）。



### 改动2 移动端合同 PDF 附件钉钉内预览（PDF.js canvas 渲染，不跳出）（2026-07-27）

- **现象**：移动端合同详情页点 PDF 附件 → iframe 加载 `/preview` 代理流 → 钉钉 WebView 不支持 iframe 内嵌 PDF → 触发"在外部浏览器打开/下载"。图片已用 Lightbox 内嵌正常，Office 保持现状��WebView 无法渲染，下载行为不可避免）。
- **方案**：PDF.js canvas 渲染（纯前端，零服务端依赖）。钉钉 WebView 支持 Canvas，PDF.js 将 PDF 逐页渲染到 canvas，**不跳出钉钉**。
- **修复清单**：
  1. 引入 pdfjs-dist v3.11.174：`public/static/lib/pdfjs/build/pdf.js` (555K)、`pdf.worker.js` (1.9M)、`cmaps/` (169 文件用于中文 PDF)
  2. 新建自定义轻量 viewer `public/static/lib/pdfjs/viewer.js`（核心 API：`pdfjsLib.getDocument` → canvas 逐页渲染 + 上一页/下一页/页码 + 加载中动画）
  3. 新建预览页 `app/view/mobile/doc_preview.php`（顶栏返回+下载按钮 + 深色背景容器 + 底部工具栏 + 错误兜底）
  4. 控制器 `MobileController::docPreview()` 接收 `f`(预览代理 URL) 和 `name`(文件名) 参数
  5. 路由 `Route::get('/m/doc-preview','Mobile/docPreview')`
  6. 改造 `public/static/js/mobile/lightbox.js`：`openPreview()` 对 PDF 扩展名分流到 `/m/doc-preview`（`window.location.href`）、图片保持 Lightbox、Office/其他保持原有 iframe
- **验证**：`php -l` 三文件（doc_preview.php/MobileController/route）全过；`check_view_globals.sh` 59 视图全过（未引入新公共全局变量）；静态文件（pdf.js/pdf.worker.js/viewer.js）均返回 200；路由 `/m/doc-preview` 返回 302（登录网关拦截，非 404，证明路由已注册）；登录后 URL 正确传递 file/name 参数。
- **体积**：PDF.js 核心库总计约 4MB（长期缓存，首次加载后后续即时命中），进包时确认不影响 release.sh 打包（不含 node_modules）。
- **状态**：未升号未打包；VERSION.md 仍 v2.35.7。待用户"出包"升 v2.35.8 并 `bash scripts/release.sh`（包内需包含 `public/static/lib/pdfjs/`）。

### 改动3 系统版本号显示 unknown → 写入 PHP 配置文件（根治部署后版本丢失）（2026-07-27）

- **现象**：生产部署后侧栏底部和管理后台「当前版本」显示 `unknown`。`app_version()` 仅依赖运行时 `VERSION.md` 解包存在，一旦部署路径或文件权限不对就回退 `unknown`，且 UI 上只读无法编辑。
- **策略**：用户明确指出"没有编辑的必要，每次更新系统时直接更新版本即可"——版本应由构建时写入代码，不依赖运行时文件。
- **修复**：
  1. 新增 `config/version.php`（`return 'vX.Y.Z';`），`release.sh` 版本校验通过后自动写入当前版本号 → 包内始终携带
  2. `app_version()` 改为双通道：优先 `config/version.php`（部署后始终可读）→ 回退 `VERSION.md`（本地开发）
  3. `config/version.php` 加入 `.gitignore`（自动生成不入库）
- **验证**：`php -l config/version.php` + `php -l app/common.php` 均通过；`bash -n scripts/release.sh` shell 语法通过；`check_view_globals.sh` 59 视图全过。
- **影响**：`release.sh` 打包行为变更（多了写入 `config/version.php` 一步），门禁流程与产物不变。

### 改动4 新增零停机部署脚本（symlink 原子替换）（2026-07-27）

- **背景**：用户提出"后续版本如果是部分功能优化与修复，怎么在不影响线上版本的情况下完成更新"。传统停服覆盖部署会导致中断，不适合频繁小版本迭代。
- **方案**：Capistrano 风格 symlink 原子替换——`scripts/deploy.sh`
  - **架构**：`current → releases/时间戳/` 符号链接，Web 服务器 DocumentRoot 指向 `current/public/`
  - **跨版本共享**：`shared/.env` / `shared/runtime/` / `shared/public/uploads/`（首次从包迁移，后续符号链接复用，部署不丢失数据）
  - **子命令**：`deploy`（解包→链接 shared→自动跑 migration\_*.sql→原子切换 current→清理旧版）、`rollback`（秒级回滚到上一版）、`status`（版本列表/当前版本/共享文件状态）、`clean`（保留最近 N 个旧版）
  - **迁移自动化**：自动执行 `database/migration_*.sql`，MySQL 连接信息从 `shared/.env` 自动解析，SQLite 从 `shared/runtime/data/contract.db` 执行
- **配套更新**：`DEPLOY.md` §11 重写为"零停机部署（推荐）" + "传统方式（备选）"双方案。
- **状态**：未升号未打包；`bash -n scripts/deploy.sh` 通过，`deploy.sh status` 空目录初始化通过；`.gitignore` 已补充 `config/version.php`。

> **出包后补（文档配套）**：v2.35.8 发布后新增 `DEPLOY_ZERO_DOWNTIME.md` 零停机部署独立使用说明（随 tar 包与桌面交付目录提供），`deploy.sh` 头部注释与 `DEPLOY.md` §11 交叉引用；因属交付文档补充、未改功能，重打 v2.35.8 包内即含，版本号不变。

## v2.35.7 (2026-07-27) — 移动工作台今日提醒跳转 + 合同未保存返回弹窗修复（PATCH）

> 本批次为 v2.35.6 之后的累积 PATCH 修复（问题13-14 共 2 项：移动工作台顶部「今日提醒」跳转、移动端合同「未保存返回」弹窗点「确定」不返回），经 `php -l`、`check_view_globals.sh`、路由可达性与框架引导渲染验证，全部发布门禁通过后正式发布 v2.35.7。

### 问题13 移动工作台顶部「今日提醒」点击无反应（2026-07-27）

- **现象**：移动工作台（/m）顶部概览「今日提醒」卡片点击后无任何跳转/反馈，被感知为"不能点击"。
- **根因**：该卡片原是页内锚点 `<a href="#alerts">`（仅为滚动到下方提醒卡片），因目标卡片本就在页面下方、视觉上"点了没反应"；其余两个卡片（待我审批→/m/approvals、我的合同→/m/contracts）是真实路由跳转，形成体验不一致。CSS 层排查已排除覆盖层拦截（`.m-sheet-mask` 隐藏态 `pointer-events:none`、`.m-nav`/`.m-tabbar`/`.m-actionbar` 均为底部或底部固定，不覆盖顶部）。
- **修复**：新增移动端提醒列表页作为真实目的地——① 新增视图 `app/view/mobile/reminders.php`（`.m-nav` 头部 + 提醒列表，复用工作台同款 `$alerts` 渲染与跳转逻辑：合同/回款提醒跳 `/m/contract/{id}`，回款跳 `#payments`，无关联 id 的提醒 `pointer-events:none` 防误触）；② `MobileController::reminders()` 复用 `RemindService::getTodayAlerts` 构造数据；③ 路由 `route/app.php` 注册 `Route::get('/m/remind', 'Mobile/reminders')`；④ 工作台顶部「今日提醒」卡片 `href` 由 `#alerts` 改为 `/m/remind`。
- **验证**：`php -l` 四文件（MobileController/route/app.php/reminders.php/index.php）全过；`check_view_globals.sh` 通过（58 视图，白名单全过）；路由可达性 302（登录网关，非 404，证明已注册）；用演示账号登录后 `/m/remind` 返回 200 并正确渲染提醒条目（如 `href="/m/contract/64#payments"`），工作台顶部入口已为 `/m/remind`，两页均无致命错误。

### 问题14 移动端新建/编辑合同「未保存返回」弹窗点「确定」不返回（2026-07-27）

- **现象**：移动端新建（或编辑）合同页，未提交时触发系统「返回」手势/底部 Tab 跳转，弹出中文确认框「当前合同尚未提交，离开后已填写的内容将丢失。确定要离开吗？」，但点击「确定」后弹窗关闭却停留在当前页、未离开。
- **根因**：统一离页确认 `_confirmLeave(go)` 把回调写成 `mConfirm(msg, function(ok){ if(ok){ contractFormDirty=false; go(); } })`；而 `mConfirm` 的「确定」按钮处理器是 `onOk()`（**不传参**），因此 `ok` 恒为 `undefined`，`if(ok)` 恒为 false，`go()` 永不执行 —— 点「确定」只关闭弹窗、不跳转。同时顶部「返回」箭头 `<a href="/m/contracts" class="back">` 不在拦截选择器 `.m-tabbar a` 内，点击会被浏览器直接跳转、静默丢失未保存数据（无确认）。
- **修复**：① `_confirmLeave` 去掉错误的 `if(ok)` 判断，回调改为 `function(){ contractFormDirty=false; go(); }`（「确定」即确认离开，语义与 `mConfirm` 一致）；② 点击拦截选择器由 `.m-tabbar a` 扩展为 `.m-tabbar a, .m-nav a.back`，使顶部「返回」箭头也走同一确认流程（点确定→`window.location.href=href` 离开；点取消→停留）。
- **验证**：`check_view_globals.sh` 通过（58 视图，白名单全过，未引入新公共全局变量）；逻辑复核确认 `mConfirm` 的「确定」「取消」回调均不传参，修复后「确定」路径必执行 `go()`。

## v2.35.6 (2026-07-26) — 移动端合同详情审批记录状态中文化（PATCH）

> 本批次为 v2.35.5 之后的累积修复（问题12 共 1 项：移动端合同详情审批记录状态中文化），经 `php -l` 与真实库数据映射验证，全部门禁通过后正式发布 v2.35.6。

### 问题12 移动端合同详情「审批记录」状态原样输出英文 PENDING/RECALLED（2026-07-25）

- **现象**：移动端合同详情页的「审批记录」区块，审批实例状态显示为英文 `PENDING` / `RECALLED`（大写）而非中文。
- **根因**：`app/view/mobile/contract_detail.php` 的审批记录循环（原第 203 行）误用**合同状态映射表 `$statusMap`**（键为 `DRAFT` / `PENDING_APPROVAL` / `APPROVED` / …，**不含**审批实例状态的 `PENDING` 与 `RECALLED`）去渲染审批实例的 `$a['status']`（值实为 `PENDING` / `APPROVED` / `REJECTED` / `RECALLED`）。`PENDING`、`RECALLED` 查不到键 → 回退原样输出英文。
- **修复**：新增审批实例状态映射 `$apprStatusMap`（文本与 `approval_status_label` 一致：审批中 / 已通过 / 已驳回 / 已撤回），审批记录循环改用 `$apprStatusMap[strtoupper($a['status'])]`；全项目扫描确认仅此一处误用（PC 端 `contract/detail.php`、`approval/detail.php` 及移动端 `approval_detail.php` 均正确走 `approval_status_label` / 各自的审批状态映射）。
- **验证**：`php -l` 通过；用真实库 `approval_instance` 全部去重状态值跑新映射，PENDING→审批中、APPROVED→已通过、REJECTED→已驳回、RECALLED→已撤回，全部翻译为中文、无原样输出。

## v2.35.5 (2026-07-25) — 部门经理审批定位 / 回收站 / 移动端上传与预览 / 合同编号 缺陷修复（PATCH）

> 本批次为 v2.35.4 之后的累积修复（问题2-11 共 10 项：部门经理审批定位、回收站恢复、钉钉 PC 误判移动、移动端甲方乙方标注、部门树加载顺序回归、离页中文拦截、回收站显示、上传选择器、附件预览免登录、合同编号归一化），经全量测试（`bash scripts/test.sh`）与三发布卡点（schema parity / db comments / view globals）全部通过后正式发布。

### 问题2 部门经理审批节点定位到真实部门负责人（2026-07-25）

- **根因**：`department` 表无负责人字段，原 `DEPT_LEADER` 审批节点用 `is_admin=1 AND dept_id=提交人部门` 近似部门经理；当部门经理并非系统管理员时解析失败，回退到超级管理员，导致"审批人不是自己部门的部门经理"。
- **修复**：
  - `department` 表新增 `leader_user_id`（三脚本 `init.sql` / `init_mysql.php` / `init_sqlite.php` 1:1 对照 + 生产迁移 `database/migration_v2.35.5_department_leader.sql`）。
  - `ApproverResolver` 的 `DEPT_LEADER` 节点**优先取 `department.leader_user_id`**（真实部门经理）；该用户存在则取之，否则回退本部门 `is_admin=1`，再回退超级管理员。
  - `AdminController::saveUser` 新增 `is_leader` 入参（**仅超级管理员可设置**，非管理员强制为 0）；`AdminLogic::saveUser` 据此控制 `department.leader_user_id`（变更部门时清理旧部门负责人、设置新部门负责人；新增用户同步设定）；`AdminController::index` 计算 `_is_leader` 标记注入视图。
  - 用户编辑弹窗新增「部门负责人」下拉（`name=is_leader`，`uLeader`），`showAddUser()` 重置为「否」、`editUser(u)` 按 `u._is_leader` 回填；`saveUser()` JS 经 `FormData` 自动提交，无需改提交逻辑。

### 问题3 禁用用户移出部门列表 + 回收站恢复（2026-07-25）

- **根因**：`RbacService::getUserList` 不过滤 `status`，被禁用（`status=2`）的用户仍显示在部门成员列表中。
- **修复**：`getUserList` 新增 `$status` 参数（`'active'` → `where('u.status',1)`；指定值 → 按值过滤；`null` → 不过滤，兼容旧调用）。`AdminController::index` 分别注入在职 `$users`（status=active）与回收站 `$disabledUsers`（status=2）并计算 `_is_leader`；视图新增回收站面板（`showUserMode('recycle')` 切换 + 列表 + `restoreUser(id)` 恢复按钮）。新增 `AdminController::restoreUser` AJAX 接口（`requirePermission('system:user')` → `AdminLogic::restoreUser` 置 `status=1`），`route/app.php` 注册 `admin/user/restore`；`AdminLogic::disableUser` 同步补 `updated_at`。

### 问题4 钉钉 PC 客户端不再误判为移动版（2026-07-25）

- **根因**：`app/common.php` 的 `is_mobile_request()` 把 `'DingTalk'` 列入移动端 UA 命中模式；钉钉 PC 工作台（Windows / Mac）UA 含 `DingTalk` 但属桌面环境，被误判移动 → 在电脑钉钉里打开应用进入移动版页面。
- **修复**：`is_mobile_request()` 移除 `'DingTalk'` 移动判定；钉钉 PC 客户端走电脑版，手机钉钉仍靠 `Android` / `iPhone` 命中移动端。

### 问题5 移动端新建合同甲方/乙方误显「我方」标注（2026-07-25）

- **根因**：`app/view/mobile/contract_form.php` 的 `updateLabels()` 给甲方 / 乙方追加「（我方）」括号标注，多余且误导（实际应以「本公司」按钮选择填入）。
- **修复**：`updateLabels()` 去掉括号标注，甲方固定为 `甲方`、乙方固定为 `乙方`；「本公司」按钮选择填入逻辑保持不变。

### 问题1 系统设置用户管理按部门结构显示（问题4 衍生，无需单独改代码）

- **现象**：部署后系统设置用户管理未像钉钉那样按部门结构显示用户。
- **结论**：PC 版 `app/view/admin/index.php` 本就含钉钉风格部门树（左侧可折叠部门结构 + 点选高亮过滤成员）。部署后看不到是因问题4 的钉钉 PC 误判移动版 → 进入移动版页面（移动版无部门树）。修复问题4（移除 DingTalk 移动判定）后，钉钉 PC 打开即进入 PC 版，部门结构树自然恢复，**无需额外改代码**。

### 问题6（用户复查）PC/移动端部门树显示空 — 加载顺序回归（2026-07-25 晚）

- **现象**：用户质疑"部门树是否被某次开发改没了"。复检结论：**代码完整存在、从未被删**（容器 `#deptTree`、递归 `buildDeptTree()`、数据 `UserLogic::getDeptTree()`、初始化调用均在位）。
- **根因**：`buildDeptTree()` 同步调用全局 `esc(d.name)`，而 `esc` 定义于 `public/static/js/app.js`（页脚 body 末尾加载）。admin 视图初始化脚本在 body 中段、先于 `app.js` 执行 → 抛 `esc is not defined` → `#deptTree` 为空 → PC/移动均不显示部门树。此回归源于 P3-2 把 `esc()` 从视图内联下沉到 `app.js` 时未同步调整初始化时机。
- **修复**：`app/view/admin/index.php` 把部门树初始化包进 `document.addEventListener('DOMContentLoaded', …)`（`esc` 就绪后再构建），与历史"列表转圈"同款模式一致。`php -l` 通过；jsdom 实证：同步 init（`esc` 未定义）→ `err="esc is not defined"`、树空；`DOMContentLoaded` 包裹 → 树正常填充。

### 问题7（用户复查）移动端新建合同离页保护英文弹窗 → 中文 mConfirm 拦截（2026-07-25 晚）

- **现象**：移动端新建/编辑合同后"返回上一页"时弹出的确认框为英文（原生 `beforeunload`，浏览器出于安全忽略自定义文案、且无法中文化）。
- **修复**：移除原生 `beforeunload` 监听，改为中文 `mConfirm` 拦截：
  - 跟踪 `contractFormDirty`（表单 `input`/`change` 即置脏、`contractFormSubmitted` 提交成功后放行）。
  - **浏览器/系统"返回"手势**：`history.pushState` 哨兵 + `popstate` 拦截，弹出中文 `mConfirm('当前合同尚未提交，离开后已填写的内容将丢失。确定要离开吗？')`，确认则跳转 `document.referrer`、取消则停留。
  - **底部 Tab 跳转**：捕获阶段拦截 `.m-tabbar a` 导航，脏数据时才弹中文确认，确认则前往目标 href。
  - 提交成功回调已置 `contractFormSubmitted=true`，拦截自动放行。
- **说明**：原生 `beforeunload` 仅在关闭/刷新标签页时由浏览器接管（文案不可控），移除后关闭标签页不再提示；应用内返回与 Tab 切换均已中文拦截覆盖。`php -l` 通过；jsdom 实证：加载无异常、输入后置脏、Tab 点击被拦截（弹中文）、确认后放行、提交后不再拦截。

### 问题8（用户复查）禁用用户后回收站不显示（2026-07-25 晚）

- **根因**：用户编辑弹窗的"状态"下拉"禁用"选项是 `value=0`，但系统禁用/回收站语义为 `status=2`（`disableUser`/`deleteUser` 置 2、`getUserList` 回收站查 2）。在弹窗里点"禁用"保存的用户被写成 `status=0`，既不在职（查=1）也不在回收站（查=2）→ 被孤立，回收站显示为空。
- **修复**：弹窗"禁用"选项 `value=0`→`2`；`getUserList` 新增 `recycle` 模式（`where status in (0,2)`，兼收锁定/禁用）；`AdminController::index` 回收站注入改用 `'recycle'`。现无论经弹窗"禁用"还是快捷"禁用"按钮，用户都进入回收站、可恢复。`php -l` 通过。

### 问题9（用户复查）移动端上传文档/图片不弹出文件选择（2026-07-25 晚）

- **根因**：隐藏文件 `<input type=file>` 用 `style="display:none"`，钉钉/微信等移动 WebView 下**无法通过 JS `.click()` 触发**（已知兼容性限制），导致点"上传文档/图片"无反应。
- **修复**：改为视觉隐藏但仍在 DOM 的可点击方式（`position:absolute;left:-9999px;width:1px;height:1px;opacity:0;overflow:hidden`）；并补 MIME 类型到 `accept`（文档含 `application/pdf`/`msword`/`vnd.openxmlformats...`/`text/plain`，图片 `image/*`），让移动端过滤器更可靠地直开对应选择器。`php -l` 通过。

### 问题10（用户复查）移动端预览合同附件跳浏览器并要求登录（2026-07-25 晚）

- **根因**：文档预览走 `/preview` iframe 且需全局 Auth 鉴权；但 iframe 是顶层导航、带不上 SPA 的 JWT 头，部署环境若登录以 JWT 为主、未落会话 Cookie，则 `/preview` 被判未登录→跳登录页；且 PDF 在 WebView 内无法内联渲染会甩给外部浏览器/查看器，外部上下文无会话→登录墙。
- **修复（签名 URL 免会话鉴权）**：
  - `common.php` 新增 `preview_token($path)` / `validate_preview_token($path,$token)`（路径绑定 + 30 分钟有效，HMAC-SHA256，复用 `AuthLogic::jwtSecret()`）。
  - `Auth` 中间件：对 `/preview` 且携带有效 `t` 令牌的请求直接放行（不再要求会话/JWT 头）。
  - `PreviewController::index`：有会话走原数据权限校验；无会话但令牌有效则免会话放行（令牌本身即授权，且路径绑定不可越权），并再次校验令牌（纵深防御）。
  - `contract_detail`/`approval_detail` 为每个附件生成令牌传入 `openPreview`；`lightbox.js::openPreview` 接收令牌并拼到 `/preview?p=..&t=token`。
  - 效果：文档被甩到外部浏览器/查看器时，二次请求带令牌→直接内联预览，**不再要求登录**；应用内 iframe 仍走会话通道，行为不变。`php -l` 通过；令牌算法单测：同路径有效、篡改路径/错误密钥/过期均拒绝、URL 编码往返正常。

### 问题11（用户复查）真实部署合同编号显示为 PREFIX-DATE-SEQ（2026-07-25 晚）

- **根因**：`generate_contract_no` 的 `contract_no_format` 默认值 `'PREFIX-DATE-SEQ'` 无花括号，而替换映射用 `{PREFIX}`/`{DATE}`/`{SEQ}`，导致 `str_replace` 不匹配、原样输出字面量；已部署环境 `system_config.contract_no_format` 多为该旧值或为空→默认旧值。
- **修复**：默认值改 `'{PREFIX}-{DATE}-{SEQ}'`；并增加**旧格式自动归一化**（若不含 `{PREFIX}`，将 `PREFIX/CATEGORY/DATE/SEQ` 自动补花括号），使已部署的历史配置也能正确生成编号（如 `HT-20260725-0001`），无需手动改配置。`php -l` 通过。

## v2.35.4 (2026-07-25) — 权限会话实时刷新 + 部署健壮性加固（PATCH，缺陷修复 + 发布流程增强）

> 本批次为 v2.35.3 之后的累积修复（含 RV-01 权限会话实时刷新、#177 幂等部署根治、repair_rbac 按 code 关联），经全量测试（`bash scripts/test.sh`）与三发布卡点（schema parity / db comments / view globals）全部通过后正式发布；`release.sh` 已内置自动化测试门禁，后续发布将自动回归。

### 部署健壮性 / 校验脚本（2026-07-25 下午）

- **#177 根治：init_mysql.php 建表/种子解耦幂等**：移除"表已存在即中止"守卫；26 条 `CREATE TABLE` → `CREATE TABLE IF NOT EXISTS`；全部种子 `INSERT INTO` → `INSERT IGNORE`。重复执行安全、自动补齐缺失表与种子，真实部署漏种子（全员报"权限不足"）不再复现。
- **check_schema_parity.sh 健壮性**：`parse()` 跳过 `--` / `//` / `#` 注释，避免注释内 DDL 关键字被误判为表（此前头部 `//` 注释写"CREATE TABLE IF NOT EXISTS"导致对照误报缺表 IF / 多表 department）。
- **repair_rbac.sql 改为「按 code 关联、不依赖自增 id」（修复脚本本身 bug）**：原脚本把 `role_permission` 写死 `role_id=1..5`/`perm_id=1..38`，但应用加载权限是纯 ID join（`user_role.role_id → role_permission.perm_id → permission.id`）。若生产库因旧"防覆盖"逻辑只建了部分表、自增计数器已推进，或角色/权限后来手动/UI 建立，role/permission 的 id 与种子假设(1..5/1..38)不一致 → 种子挂错 id → 有角色的账号仍拿不到权限。现改用 `INSERT ... SELECT WHERE NOT EXISTS`（角色/权限按 `code` 判重）+ UNION ALL 派生表提供 (角色code,权限code) 种子对再 JOIN 成真实 id 写入，**完全不依赖任何自增 id**，MySQL/SQLite 通吃且幂等。已构造 ID 漂移测试库（角色 10-14、权限 50-87）端到端验证：修复前 boss 权限码=0，修复后 admin=38/普通员工=18、role_permission=110，重复执行仍为 110。

### 权限会话实时刷新（RV-01，2026-07-25 晚）

- **根因**：登录时把 `user_permissions` 固化进 Session；Cookie 通道只在登录时算一次、登录后永不刷新，JWT(钉钉)通道虽每次请求重算但角色/权限变更完全不通知已登录会话。且 `RbacService::assignRoles/saveRolePerms/updateRole/deleteRole` 与 `is_admin` 变更后未失效缓存 —— 导致真实场景中"账号改角色/权限后，已登录用户（尤其手机钉钉端，JWT 存 localStorage 长期不过期、无重拉权限入口）仍拿不到新权限，需重新登录才生效"。
- **机制**：`user` 表新增 `perm_version` 字段（三脚本 init.sql/init_mysql.php/init_sqlite.php 1:1 对照 + 生产迁移 `database/migration_v2.35.4_perm_version.sql`）。登录时把该版本写入会话；`Auth` 中间件在 Cookie/JWT **双通道**每次请求鉴权成功后调用 `AuthLogic::refreshSessionPermissionsIfStale()`，比对会话版本与 DB 最新值，不一致则重算 `user_permissions` 与 `data_scope` 写回会话（不轮换会话 ID，避免抖动）。
- **失效触发点（写操作后自增受影响用户 perm_version）**：`RbacService::assignRoles`（该用户）、`saveRolePerms`/`updateRole`（拥有该角色的全部用户）、`deleteRole`（受影响用户）、`AdminLogic::saveUser`（仅当 `is_admin` 实际变更时）。修复了 `saveUser` 先 `update` 再读旧值导致自增永不触发的 bug。
- **效果**：角色/权限/`is_admin` 变更后，已登录用户**下一次任意请求**即自动拿到最新权限，无需重登；钉钉端用户刷新页面或发起新请求即生效。临时用户端到端验证：改角色权限 18→38、版本 0→1 自动刷新；is_admin 变更版本自增；版本一致时幂等不重复刷新。
- 校验：`php -l` 全绿；`check_schema_parity.sh` 三脚本 1:1（26 表/269 字段）；本地 SQLite 已补列并跑通刷新链路。

## v2.35.3 (2026-07-25) — 技术债清理批次（PATCH，累积修复 + 发布测试门禁）

> 本批次为 v2.35.2 之后的累积修复，经全量测试（`bash scripts/test.sh` 28 例/64 断言全绿）与三发布卡点（schema parity / db comments / view globals）全部通过后正式发布；`release.sh` 已内置自动化测试门禁，后续发布将自动回归。

### 本次落地项（技术债清理，2026-07-25）

- **移动端 custTransfer 原生 prompt() 改造（闭环 P3-4 已知项）**：`mobile-common.js` 新增自定义输入弹窗 `mPrompt`（复用 `mConfirm` 的防叠加锁 `_mModalBusy`、点遮罩=取消不关闭网页、支持回车提交），`mobile.css` 增加 `.m-modal-input` 样式；`app/view/mobile/customer_detail.php` 的 `custTransfer` 改用 `mPrompt` 替代原生 `prompt()`，并校验接收人 ID 为数字，消除移动端 webview 原生 prompt 的体验/宿主手势隐患。
- **P2-1 God Object 安全拆分（启动）**：测试基线已就绪，将 `ApprovalLogic` / `ContractLogic` 的内聚逻辑下沉为独立类，调用点零改动（保留委托桩）：
  - `ApproverResolver`：原 `ApprovalLogic::resolveApprovers` 审批人解析逻辑（SPECIFIC_USER / DEPT_LEADER / ROLE / CC 四类，含部门主管回退超级管理员）。
  - `ApprovalNodeExecutor`：抽出超时自动通过规则 `shouldAutoApproveOnTimeout()`，固化 REV-01 会签(AND)超时仅催办、或签(OR)超时自动通过的合规红线。
  - `ContractQuery`：8 个只读聚合查询（getMyCount / getRelatedList / getRelatedCount / search / getFrameworkOptions / findByAttachmentPath / getArchivedList / getHotKeywords）下沉，并抽出纯函数 `tokenizeKeywords()`（关键词分词，便于单测）。
  - 新增 3 个单元测试类 `ApproverResolverTest` / `ApprovalNodeExecutorTest` / `ContractQueryTest`（覆盖纯逻辑分支 + API 冒烟），测试基线由 13 例 → **28 例全绿（64 断言）**。
- **P3-5 JS escH 收敛（与 P3-2 形成闭环）**：`customer_pool.js` / `approval_index.js` / `audit.js` / `customer.js` / `project.js` / `contract.js` / `resource.js` 七处列表 JS 的本地 `escH` / `escHtml` / `esc` 副本统一改为 `app.js` 全局 `esc`；`contract.js` 两处 `title="..."` 属性注入因全局 `esc` 额外转义 `"`/`'` 而更安全。
- **测试卡点接入 release.sh（固化 P1-1 成果）**：`scripts/release.sh` 在版本校验 / `php -l` / 三发布卡点之后新增「自动化测试门禁」，发布前运行 `bash scripts/test.sh`，用例未全绿即中止打包；可用 `--skip-tests` 跳过（不推荐，会失去发布前回归保障）。
- **仓库根历史审查文档清理**：删除 10 个 `CODE_REVIEW_*` / `AUDIT_v2.35.1` 历史文档（保留最新 `AUDIT_v2.35.2.html`），减少新接手者甄别负担。
- **继续暂缓项（按用户要求未改动）**：电子签章模块（`/sign`）、跨部门协作（CR-10）维持现状。
- 验证：`php -l` 全绿；`bash scripts/test.sh` 28 例全绿；`check_schema_parity` / `check_db_comments` / `check_view_globals` 三卡点通过；jsdom 实跑确认 `mPrompt` 弹窗可用、全局 `esc` 可用且列表渲染无回归。

## v2.35.2 (2026-07-25) — 全量审计后修复与优化（PATCH，缺陷修复 + 测试基线）

### 全量审计（v2.35.1）后修复与优化（PATCH，2026-07-25）

- **P2-2 修复 contract_no / parent_no 存储型 XSS 残面**：`contract/detail.php`、`dashboard/index.php`、`sign/index.php` 中裸输出的合同编号/框架合同编号统一套 `htmlspecialchars`（其余用户字段本已转义），补齐防御纵深。
- **P2-3 对齐 login.php 钉钉免登触发策略**：`auth/login.php` 免登触发由「UA 且 typeof dd 已定义」改为**以 UA 为主 + dd 就绪看门狗（最多 8s 轮询）**，与 `dingtalk/entry.php` 策略一致；`dd` 未就绪时静默降级为账号密码登录（本页即登录页，不强制跳转），避免极少数 WebView 时序下漏触发免登。
- **P3-1 文档字段数同步**：`DEVELOPMENT_GUIDE.md` §7 表结构字段数由过时的「262」更正为实测「268」（以 `scripts/check_schema_parity.sh` 为准）。
- **P3-2 esc() 去重下沉至公共 JS**：`esc()` 原在 `contract/detail.php` / `admin/index.php` / `finance/index.php` 三处各定义一份，现统一下沉到 `public/static/js/app.js`（全局 `window.esc`）；三视图移除重复定义，`loadPayments()` / `load(true)` 等立即调用点用 `DOMContentLoaded` 包裹（与 v2.35.0 列表转圈修复同款模式），消除多份重复定义且防脚本顺序回归。
- **P3-3 去除硬编码 + 增强随机源**：JWT 有效期由硬编码 `86400` 改为读取 `JWT_TTL` 配置（默认 86400），钉钉免登会话有效期同步对齐；钉钉 JSAPI 签名 `nonce` 由可被预测的 `md5(uniqid)` 改为密码学安全的 `bin2hex(random_bytes(16))`。
- **P1-1 建立 PHPUnit 测试基线**：新增 `phpunit.xml.dist` + `tests/bootstrap.php` + `tests/unit/`（JwtHelper 签发/验证/篡改/过期、开放重定向防护 `safe_redirect_url`、手机号/邮箱/信用代码校验器、金额格式化，共 13 例 30 断言全绿）；`composer.json` 增 `require-dev: phpunit/phpunit`；`scripts/test.sh` 提供一键运行（优先 `phpunit.phar`，其次 `vendor/bin/phpunit`）；`phpunit.phar` 已下载并加入 `.gitignore`，发布包已排除。后续可将 `bash scripts/test.sh` 接入 `release.sh` 作为发布前巡检卡点。
- **继续暂缓项（按用户要求未改动）**：电子签章模块（`/sign`，REV-08/09/10）、跨部门协作（CR-10）等已知暂缓项维持现状，本次未触碰。
- 验证：全部修改文件 `php -l` 通过；`bash scripts/test.sh` 全绿；jsdom 实跑确认 `app.js` 全局 `esc` 可用且转义正确、`login.php` 新免登块无运行时报错、DOMContentLoaded 包裹下初始加载无 `ReferenceError`（旧同步写法在 jsdom 下确会抛错，反向印证修复必要）。

## v2.35.1 (2026-07-25) — 修复钉钉无感登录「dd is not defined」（PATCH，缺陷修复）

- 现象：部署到钉钉后，无感登录报错 `Uncaught ReferenceError: dd is not defined`；`dd` 全局对象不存在，无感登录完全失效。
- 根因：钉钉 JSAPI 的全局对象 `dd` 由官方 SDK 脚本（`https://g.alicdn.com/dingding/dingtalk-jsapi/3.1.0/dingtalk.open.js`）定义；新版钉钉/工作台 H5 客户端**不会自动注入** `dd`，而项目此前从未引入该 SDK，导致 `dd` 恒为 `undefined`。`app/view/dingtalk/entry.php` 还以 `typeof dd === 'undefined'` 判定"非钉钉环境"，进一步使回退逻辑走偏。
- 修复：
  1. `app/view/auth/login.php`、`app/view/dingtalk/entry.php` 的 `<head>` 显式引入官方 JSAPI SDK（加载后定义全局 `dd`）。
  2. 钉钉环境判定改为**以 UA 为主**（不再依赖 `dd` 是否定义）；`entry.php` 增加 **8s 看门狗**：SDK 加载失败 / 用户拒绝授权 / 权限不足时自动回退 `/login?redirect=...`，不再永久转圈。
  3. 免登调用兼容新旧 SDK：优先一段式 `dd.getAuthCode`，否则三段式 `dd.runtime.permission.requestAuthCode`；`dd.config` 包 try/catch。
- 验证：SDK CDN 实测返回 HTTP 200；两页面内联 JS 通过 `node --check`；dev 渲染页已含 SDK 脚本与新逻辑。
- 部署注意：本修复仅改 2 个视图文件；确认打包版本 ≥ v2.35.1 即已含此修复。另需确认钉钉后台「应用首页地址」指向 `/dingtalk/entry?to=/dashboard`、`.env` 配齐 `DINGTALK_*` 且 `DINGTALK_MOCK_MODE=false`、开通免登权限并加可信域名（详见 `DINGTALK_SSO_GUIDE.md`）。

## v2.35.0 (2026-07-25) — 资料库开票字段录入 + 今日提醒直跳 + 新手引导开关 + 列表转圈修复（MINOR，功能增强）

### 资料库「开票资料」支持结构化字段录入（PATCH，功能增强，2026-07-25）

- 资料库「开票资料」分类新增**结构化字段录入**：除上传文件外，可直接填写单位名称/税号/地址电话/开户行账号/发票类型/备注，存 JSON 到 `resource_library.content`；文件可空（即"纯字段"资料，无需传 PDF）。
- 后端：`ResourceController::save()` 放开文件必填（仅 INVOICE 类允许纯字段），校验 content JSON 合法性；`ResourceLogic` 新增 `INVOICE_FIELDS` 常量（key→中文标签）与 `decodedContent()` 辅助。
- 前端：选「开票资料」时上传弹窗动态显示字段表单并放宽文件必填；列表对结构化资料显示字段摘要 +「查看字段」按钮（字段表格弹窗 + 一键复制全部，便于开票时粘贴）；文件型资料（含现有示例）展示完全不变。
- 数据层：`resource_library` 新增 `content` 列（三份 init 脚本 1:1 对齐 + 新增迁移 `migration_resource_content.sql` + 运行库已 ALTER）。
- 本期范围仅「开票资料」、仅在资料库内可复制，**未打通合同/回款开票流程自动带出**（用户选择，留待验证录入体验后再演进）。

### PC 端今日提醒标题直跳详情（PATCH，体验优化，2026-07-25）

- 提醒列表页移除「查看合同 / 查看回款」独立按钮，整行标题改为可点击链接，直接跳转 `/contract/{id}`（合同/回款提醒均跳合同详情），行尾加 `›` 箭头提示。
- 首页仪表盘「今日提醒」卡片每条提醒标题由纯文本改为可点击链接，跳转 `/contract/{id}`。
- 移动端本就支持标题跳转，本次仅调整 PC 端，行为对齐。

### PC 端新手引导改为系统配置可开关（PATCH，体验优化，2026-07-25）

- 将"新手引导"由先前软移除改为**系统设置「系统配置」页里的可勾选开关**（`guide_enabled`），无需改代码即可启用/关闭。
- 侧栏入口（`app/view/layout/sidebar.php`）按 `sys_config('guide_enabled','0')` 控制显隐；并在侧栏内联注入 `window.guideEnabled`（早于 footer 的 app.js），`app.js` 首次访问 `/dashboard` 的自动弹出改受该变量门控。
- 「系统配置」选项卡新增「启用 PC 端新手引导」switch，保存复用 `/ajax/admin/config/save`，与版权信息一并即时保存生效（保存清除缓存）。
- 三份初始化脚本（`init_sqlite.php`/`init_mysql.php`/`init.sql`）新增 `guide_enabled='0'` 默认种子（默认关闭，与用户偏好一致）；当前演示实例已显式落为 0。
- 移动端本无此入口，不受影响。

### 修复合同/审计列表「无数据时加载图标一直转」的缺陷（PATCH，缺陷修复，2026-07-25）

- 现象：打开合同列表、审计列表时，表格区初始占位 spinner（`#tableBody` 内的 `spinner-border`）永久转圈不消失；无论有无数据都可能出现（用户恰在空列表上观察到）。
- **真正的根因（脚本加载顺序）**：`app.js`（定义 `$ajax` / `emptyState` / `showToast` / `showLoading` 等全局）在 `app/view/layout/footer.php` 引入，排在页面脚本 `contract.js` / `audit.js`（视图内 `asset_url('js/xxx.js')`）**之后**。列表脚本末尾同步调用 `load(1)`，此时 `$ajax` 与 `emptyState` 尚未定义 → `load(1)` 第一行 `$ajax(...)` 同步抛 `ReferenceError`，`then` 链根本没建立、`tb.innerHTML` 永不执行 → 首屏 spinner 永久残留。（注：此根因此前误判过两轮——第一轮以为"仅 .catch 未清 spinner"；第二轮用"布局重排把 app.js 前置"修复，但该布局文件在独立部署的测试系统难以同步生效，用户仍看到转圈。）
- **修复（列表 JS 内部等待全局就绪，不依赖布局改动）**：撤销上轮的布局重排（`header.php`/`footer.php` 已恢复为原始脚本顺序），改为在 `contract.js` / `audit.js` 末尾把初始 `load(1)` 用 `if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function(){ load(1); });` 包裹。`DOMContentLoaded` 在所有同步脚本（含 footer 的 `app.js`）执行完后才触发，届时 `$ajax`/`emptyState` 必然已定义，`load(1)` 正常执行——**从根消除脚本顺序依赖，且只改 2 个列表 JS 文件，部署只需覆盖这 2 个文件，无需同步布局**（彻底解决测试系统"改了布局仍转圈"的部署摩擦）。
- **防御性兜底（保留）**：`contract.js` / `audit.js` 的 `.catch` 在**真实网络失败/非 JSON 响应**时把 `#tableBody` 替换为「加载失败 + 重新加载」操作点（带重试按钮，点击重跑 `load(p)`），覆盖接口真出错的场景，避免任何情况下永久转圈。
- 顺带修正：合同空状态 `emptyState` 的 `colspan` 由 10 改为 11，与表头 11 列及 `.catch` 行对齐。
- 验证：jsdom 构造"列表脚本在 `app.js` 之前"的真实逆序内联 HTML，按真实顺序执行两脚本并触发 `DOMContentLoaded`，mock fetch 模拟空数据/有数据/请求出错三模式——合同与审计列表均 `HAS_SPINNER=false`（空→暂无、有数据→行、出错→加载失败+重试）；`node --check` 两 JS 语法通过；布局 `header.php`/`footer.php` 已 `php -l` 通过且恢复为原始脚本顺序。

## v2.34.0 (2026-07-24) — 页面页脚版权信息可配置 + 精简侧栏版本展示（MINOR，功能增强）

- PC 端侧栏左下角移除「更新日志」链接，仅保留版本号（动态读取 `VERSION.md` 当前版本，新增 `app_version()` 助手，避免硬编码漂移）。
- PC 与移动端页面底部新增版权信息展示：PC 端 `app/view/layout/footer.php`、移动端 `app/view/mobile/_foot.php` 均读取全局变量 `$site_copyright`。
- 系统设置新增「系统配置」页（`/admin/config`）：提供「版权信息」设置项，保存至 `system_config.copyright`（复用 `/ajax/admin/config/save`），保存后页脚即时生效；侧栏新增「系统配置」子入口。
- 新增 `sys_config()` 助手（带 300s 短缓存），`BaseController` 全局注入 `site_copyright`/`app_version`；三份初始化脚本（`init_sqlite.php`/`init_mysql.php`/`init.sql`）新增 `copyright` 默认种子。

## v2.33.2 (2026-07-24) — 随包交付钉钉免登排查配置文档（PATCH，发布内容）

- 新增 `DINGTALK_SSO_GUIDE.md`：覆盖同步≠免登、免登原理、v2.33.1 修复说明、P0→P4 排查清单、配置步骤、验证法与 FAQ；已接入 `release.sh` 桌面交付随包。

## v2.33.1 (2026-07-24) — 修复登录页钉钉免登 corpId 为空导致无法自动登录（PATCH，缺陷修复）

- 登录页钉钉免登：`requestAuthCode` 的 `corpId` 由写死空串改为从 `/dingtalk/jsapi-config` 取真实值，并补 `dd.config` 权限注入；成功后尊重 `redirect` 深链（原写死 `/dashboard`）。修复经登录页进入时免登必失败、退回输密码的问题。
- 关键配置提醒：钉钉工作台「应用首页地址」须指向免登入口 `/dingtalk/entry?to=/dashboard`（移动端 `?to=/m`）；且 `.env` 须配齐 `DINGTALK_APP_KEY/SECRET/CORP_ID/AGENT_ID`、`DINGTALK_MOCK_MODE=false`、钉钉后台开通免登权限并加可信域名。

## v2.33.0 (2026-07-24) — 用户管理改为钉钉后台风格部门结构展示（MINOR，功能增强）

- 用户管理布局改为钉钉后台风格：左侧可折叠层级部门树（顶部「全部成员」+ 各部门展开/折叠箭头），右侧成员列表；点击部门高亮并过滤成员（含「包含子部门」开关）。移除原顶部部门筛选下拉。新增 `allDepts` 全局与部门树 JS 函数（`buildDeptTree/selectDept/toggleDept/renderUserList` 等），折叠展开由 JS 控制子树 display。
- 修复用户管理页「同步钉钉」按钮：原因缺 `#syncLog` 面板误报且无法同步，现无面板时直接同步并提示、成功后刷新。

## v2.32.0 (2026-07-24) — 用户管理增加部门列、按部门筛选、修复部门下拉（MINOR，功能增强）

- 用户列表新增「部门」列（取 `getUserList` 已 LEFT JOIN 的 `dept_name`）；表头新增「按部门筛选」下拉（选项来自 `getDeptTree()` 扁平部门列表），客户端按 `data-dept-id` 隐藏非匹配行、不重载，无匹配显示「该部门暂无用户」。
- 修复编辑弹窗「部门」下拉 `uDept`：原只渲染 `-` 且无 JS 填充，部门不可选；现由控制器注入 `depts` 服务端渲染全部部门，且 `editUser` 预选所属 `dept_id`。

## v2.31.6 (2026-07-24) — 修复审批节点上下箭头失效/错位（PATCH，缺陷修复）

- 审批节点卡片上移/下移（`moveNode`）原基于「箭头是卡片间独立兄弟节点」用 `insertBefore` 交换：①上移全部失效——`target` 取到前面的 `.node-arrow`，触发 `!target.classList.contains('node-card')` 提前 return；②下移箭头错位、视觉乱跳。重写为：移动前移除全部箭头→按新顺序重挂卡片 DOM（保留表单状态）→重新生成箭头分隔；越界（顶/底）仅重排编号。箭头恒在相邻卡片间、首卡片前无箭头，编号自动校正。

## v2.31.5 (2026-07-24) — 抄送节点角色选择对齐 ROLE 节点下拉（PATCH，缺陷修复）

- 抄送（CC）节点角色选择：由 v2.31.4 的「原生多选列表框（`<select multiple size=4>`）」改为与**审批节点类型为「角色(ROLE)」完全一致**的单个原生 `<select>` 下拉（点开才展开、仅单选），控件形态等同 ROLE 节点的 `roleCode_i`。首行保留「- 不指定角色 -」空选项，CC 角色可选可空。
- `getNodesData` 的 CC 分支改为读取单个 `ccRole_i` 并包成单元素 `role_codes` 数组，保持后端与已存流程兼容，自此 CC 抄送角色仅支持单选。

## v2.31.4 (2026-07-24) — 抄送节点角色选择改回下拉（PATCH，缺陷修复）

- 抄送（CC）节点角色选择：撤回 v2.31.3 的「角色多选弹窗」，改回与「节点类型为角色(ROLE)」一致的处理——直接使用原生 `<select multiple>` 下拉框（与 ROLE 节点同形态，仅单选/多选之差），不再弹窗。保留多角色语义（`role_codes` 数组），`getNodesData()` 从多选下拉 `selectedOptions` 读取已选 code。
- 清理抄送专属 JS（`openCcRolePicker` / `renderCcRoleView` / `_rpCodes` / `_rpCcIndex`），角色弹窗现仅服务于编辑用户分配多角色。

## v2.31.3 (2026-07-24) — 审批节点卡片 UI 修复（PATCH，缺陷修复）

- 审批节点卡片：上/下箭头与删除按钮改为头部行正常流布局，不再与右侧审批人/抄送区重叠；按需求移除删除按钮。

## v2.31.2 (2026-07-24) — 发布卡点新增视图公共变量全局声明检查（PATCH，发布流程增强）

新增 `scripts/check_view_globals.sh` 发布前卡点，扫描视图文件检测被误声明在 `$tab` 分支内、但应在全局声明的公共 JS 符号（allRoles/allUsers/flowCats/esc），防止跨 tab 缺失型运行时回归（v2.31.0 那类）。已接入 `release.sh`。

## v2.31.1 (2026-07-24) — 修复用户管理 tab 角色选择/编辑弹窗打不开（PATCH，缺陷修复）

回归修复（v2.31.0 引入）：将 `allRoles` / `allUsers` / `flowCats` / `esc` 等跨 tab 公共变量与工具函数上提到所有 tab 之前声明的公共脚本块，修复「用户管理」tab 下因 `allRoles` / `esc` 未定义导致编辑用户、选择角色弹窗打不开的问题。

## v2.31.0 (2026-07-23) — 审批流编辑器 + 用户角色选择器优化（MINOR，新功能）

五大产品需求：

1. 审批流「指定用户」节点弹出选人窗口（部门树+搜索+分页多选），覆盖全量用户，取代平铺下拉。新增后端 `UserLogic::searchPicker/getDeptTree` + 路由 `admin/dept-tree`、`admin/user-picker`，及视图通用选人弹窗组件。
2. 抄送节点新增「指定用户」选项（复用选人弹窗）；`ApprovalLogic::resolveApprovers` NODE_CC 分支合并 `cc_user_ids`，与角色收件人去重并集。
3. 钉钉组织架构同步链路打通（用户管理「同步钉钉」→ `sync-org` → `syncOrganization`）。修复 `syncOrganization` 将本地 `department.parent_id` 误写为钉钉 ID 的 bug：改为两遍处理，父级统一修正为本地部门 ID，保证同步后部门树（及选人弹窗）正确。
4. 编辑用户「角色」改为多角色友好选择器（弹层+搜索+已选回显），修复原生 multi-select 点选不收起；`saveUser` 改读 `_rpSelected`。
5. 审批流「适用分类」多选（`category_list`）+「金额条件」开关（`use_amount`）；`matchFlow` 适配分类多选交集匹配与金额开关，金额未启用的流程在匹配排序中视为最宽。

DB 变更：`approval_flow` 新增 `category_list`、`use_amount`（三份 init 脚本同步）；存量库升级见 `migration_v2.31_add_flow_fields.sql`。涉及文件：ApprovalLogic.php、AdminController.php、DingTalkService.php、admin/index.php（视图）、三份 init 脚本、迁移 SQL。php -l 全绿；schema parity（26 表/267 字段）与中文注释卡点通过。

## v2.30.3 (2026-07-20) — 全站状态标签中文化 + 移动端审批提醒修复（PATCH，缺陷修复）

全站状态/动作标签中文化：修复审批记录、审计中心、相对方360「最近动态」等处露出 `recalled`/`AUTO_APPROVED`/`approve_recall`/`status_change`/`auto_expire`/`invoice_red` 等英文枚举码的问题。新增 `app/common.php` 集中式审计中文映射（`audit_action_labels()/audit_action_label()/audit_type_labels()/audit_type_label()`），`approval_action_label()` 补 `AUTO_APPROVED→自动通过`；`AuditController` 与 `party/360.php` 改用集中映射，供应商类型经 `dict('supplier_type')` 本地化；移动端审批详情节点补 `AUTO_APPROVED`。全库复核视图层无裸英文码输出。同时收口上一轮遗留的移动端审批提醒修复。零业务/DB 变更，4 改动文件 `php -l` 全绿。

## v2.30.2 (2026-07-18) — 登录 CSRF 校验失败 / 会话无法保持修复（PATCH，缺陷修复）

HTTP（非 HTTPS）部署下无法登录、写操作报「CSRF 校验失败」。根因：`config/cookie.php` 默认 `secure=(bool) env('COOKIE_SECURE', true)` 且 `.env` 未设该项，会话 Cookie（CONTRACT_SID）被强制 `Secure`，浏览器在 HTTP 下拒绝存储致会话无法保持、`Session::get('csrf_token')` 恒空 → CSRF 中间件 403。新增全局中间件 `app/middleware/CookiePolicy.php`（最外层、SessionInit 之前），按请求协议动态修正全局 Cookie `secure`（HTTPS 安全 / HTTP 可登录），`.env` 显式 `COOKIE_SECURE` 仍优先；同步更新 `config/cookie.php` 注释。零业务/DB 变更。本地 HTTP 复现修复后 `CONTRACT_SID` 不再带 `Secure`，端到端 `POST /login` 返回 `code:0` 登录成功。

## v2.30.1 (2026-07-18) — 移动端原生 confirm 关闭网页 bug 修复（PATCH，缺陷修复）

移动端全量替换原生 `confirm()`/`alert()` 为 `mobile-common.js` 新增的 `mConfirm` 自定义居中弹窗（DOM 实现，取消/确定为普通按钮不触发 webview 关闭；带防叠加锁），覆盖合同状态变更/回款确认/审批提交·通过·撤回/取消归档/客户认领·释放·转移等全部确认场景；`confirmAndPost` 改用 `mConfirm`；`alert` 反馈改 `toast`。新增 `.m-modal-*` 样式。零业务/DB 变更，16 移动页 `<script>` 经 `node --check` 全过、移动端零原生 confirm/alert 残留。`prompt()`（custTransfer 输入型）保留为已知项。

## v2.30.0 (2026-07-18) — P3-3 阶段B1：移动端公共 helper 去重（MINOR，前端重构）

移动端 11 视图删除残留本地 toast/showLoading/esc/csrfToken 共 32 处重复定义，统一复用 mobile-common.js 公共版（更健壮：DOM 自动创建兜底 + csrfToken decode 版）。其余同名异实现的页面专属函数（cardHtml/loadList/stTxt/stCls/openSheet/closeSheet）保留本地，不抽离。零业务/DB 变更。18 视图 php -l 全过，4 公共函数无残留、页面专属函数完好。

## v2.29.0 (2026-07-18) — P3-3 阶段A：移动页 \_foot 统一（MINOR，前端重构）

移动端 18 视图尾部从 3 种混杂模式统一为单一 `_foot.php` 收尾。纯视图重构，零业务逻辑/DB 变更。

- 14 个页去手写 `mobile_tabbar` + 手写闭合标签，改 `include _foot.php`（设 `$tab`）。
- `customer_detail`/`index` 去内联 tabbar 调用与 `index` 手动 `app.js`（防重复加载），保留内联业务脚本。
- `login.php` 保持特殊尾部；`customers`/`suppliers` 此前已用 `_foot` 不动。
- 复检：移动页零 `$ajax` 调用，`app.js` fetch 猴补丁仅追加 CSRF 头不重定向 → 无桌面登录跳转回归；18 视图 `php -l` 全过。

## v2.28.4 (2026-07-18) — Backlog 清零：复审新发现 + 部署收口（PATCH）

承接 `DEV_PLAN_2026-07-18.md` §3 Backlog，一次性落地 5 项复审新发现 + 1 项部署侧收口。零架构改动、零 DB 迁移。

### 修复

- **产品/业务**：N-M1 超时审批（`processOverdueApprovals`）事务内加锁 + `current_node_order` 复核，幂等防并发重复推进（镜像 M-P1）。
- **前端**：N-m1 移动三表单（合同/客户/供应商）保存裸 fetch → 统一 `apiPost`（CSRF + toast 兜底，不误跳桌面 /login）；N-m2 admin `loadMockLogs` 裸 fetch → `$ajax`。
- **安全**：N-m3 Auth `except` 前缀匹配收紧为「完全相等或 prefix+'/' 下级」，杜绝前缀误豁免。
- **移动端**：N-m4 客户详情关联合同分页——`getRelatedList` 加 limit(20) + `getRelatedCount`，`getList` 支持 customer_id 筛选，超限显示"查看全部"（对称 M-Pf5）。
- **部署**：C3 `.env.example` `DINGTALK_MOCK_MODE` 默认 `false`，防生产误开 Mock。

### 校验

- `php -l` 全绿（9 文件）；`check_schema_parity.sh`=0（26 表/265 字段）；`check_db_comments.sh`=0（81 表表级+字段级注释全带）。

## v2.28.3 (2026-07-18) — CODE_REVIEW_v283 全量修复 + 复审闭环（PATCH，缺陷修复）

基于 CODE_REVIEW_v283.md（严重 3 + 中等 18 + 轻微 25+）的全量缺陷修复，并经 CODE_REVIEW_v284.md 五维并行复审确认全部闭环。零架构改动、零 DB 迁移。

### 修复（按维度）

- **产品/业务**：C1 驳回重提状态机卡死、M-P1 或签并发重复审批、M-P2 框架合同子合同校验、M-P3 已收口径统一 paid_amount；复审修复 statusTransition 自批漏洞（requirePermission + 三态拦截）、get360 余额失真（receivedPaid 限交易合同）。
- **架构**：M-A1 审批流双实现合并、M-A2 客户/供应商镜像合并、M-A3 TemplateController 死代码删除、M-A4 jsapiConfig try/catch 兜底、BaseController 未用 Db import 清理。
- **性能**：M-Pf1 审批历史 N+1、M-Pf2 重复拉取、M-Pf3/4 缺失索引、M-Pf5 项目合同 LIMIT、P3-7 缓存死代码（ReportLogic::getMobileSummary 缓存真正生效）。
- **安全**：M-S1 密钥明文回显掩码、m-S2 登录弱口令清理、m-S3 Auth 精确匹配、m-S4/m-S5 视图转义+路由注释、.env.bak 清理+打包排除；复审再补 approval/detail:5 与 admin/index:186,204 转义。
- **前端**：C2 移动表单死页防御、M-F1/F2 静默失败转 $ajax、M-F3 按钮文案还原、M-F5 底部 Tab 权限裁剪、M-F6 反馈风格统一；复审修复客户/供应商表单 catch 文案、batchDelete/batchArchive 改 $ajax。

### 校验

- `php -l` 全绿；`check_schema_parity.sh`=0（26 表/265 字段）；`check_db_comments.sh`=0（81 表全带表级+字段级中文注释）；5 维并行复审（CODE_REVIEW_v284.md）确认 v283 全部问题闭环、零严重残留。

## v2.28.2 (2026-07-18) — 分层铁律巩固 + 内联 JS 抽离 + 静态资源指纹（PATCH，架构评估建议落地）

承接 v2.28.1 架构评估"暂不分离"结论后的三项近期优化，零架构改动、零 DB 迁移。

### 优化

- **视图层 4 处残留 Db::name 下沉 Logic**：`contract/detail.php`（approval_flow 名称 + company_profile 签约主体）改由 `ContractController::detail()` 调 `ApprovalLogic::getFlowById()` / `CompanyLogic::getById()`（新增）注入；`template/index.php`（审批流下拉）改由 `TemplateController::index()` 调 `ApprovalLogic::getEnabledFlows()` 注入；`admin/index.php`（字典配置）改由 `AdminController::index()` 调 `AdminLogic::getDicts()`（新增）注入。视图层 Db::name 残留清零。
- **内联 JS 抽离到独立文件**：`template/index.php`（22 行）→ `static/js/template.js`；`company/index.php`（36 行）→ `static/js/company.js`；`resource/index.php`（43 行）→ `static/js/resource.js`（含 `window.__RES_CAN_MANAGE` 桥接 + `#uploadForm` 防御性空引用检查）。共抽离约 101 行内联 JS，降低视图耦合。
- **静态资源加版本号指纹**：`app/common.php` 新增 `asset_url(string $path): string` 辅助函数，以 `filemtime` 作为文件级指纹（改一个文件只刷新该文件缓存）；18 个视图共 19 处 `<script src>` / `<link href>` 统一改为 `<?=asset_url('...')?>`，旧的零散 `?v=2.23.1`/`?v=2.25.0`/`?v=2.27.0` 全部收敛为 filemtime 指纹。

### 校验

- `php -l` 全绿；schema parity / 中文注释双校验 0；登录后冒烟 `/company` `/customer` `/audit` `/resource` `/admin/dict` `/contract/1` 均 200，JS 引用带 `?v=<filemtime>` 指纹生效；`/resource` 桥接 `window.__RES_CAN_MANAGE` 注入正常。

## v2.28.1 (2026-07-18) — 移动端新建合同表单体验优化（PATCH，8 处小修）

聚焦「新建合同」表单在移动端的交互体验与正确性修复，无后端架构变更、无 DB 迁移。

### 修复

- **非交易合同甲方误标"我方"**：移动端 `contract_form.php` `updateLabels()`/`syncTrade()` 增 `nonTrade` 分支；`contract_detail.php` 甲方标签按 `trade_attr` 动态输出。

### 优化

- **签约主体方案A（移动端对齐桌面端）**：移动端甲/乙方「本公司」按钮改为弹层选主体（从 `/ajax/company/options` 拉取），填入对应方名称 + 同步 `our_company_id`，保留多主体切换填入能力。
- **关键词归一化**：后端 `common.php` 新增 `normalize_keywords()`；`ContractController::save()` 入库前调用；`ContractLogic::search()` 口径由 `title|contract_no` 扩为 `title|contract_no|keywords`，与 `getList()` 一致。
- **关键词 chip 标签输入**：桌面端 `contract.js` 把 `input[name=keywords]` 降级为隐藏值载体，插入 `.kw-chips` + 迷你输入框，回车/逗号/顿号/分号即生成标签；移动端 `contract_form.php` 同源实现，提交前 `kwFlush()` 收未回车残留。
- **移动端隐藏「签约主体」下拉**：原可见 `<select>` 改为 `<input type="hidden">`；`companyName()` 用注入的 `COMPANY_MAP` 查名，`recomputeOurSide()` 反推我方侧照常工作。
- **关键词顶部弹层 + 高频标签快速选择（移动端）**：关键词字段改为只读展示区 + 顶部固定弹层 `#kwSheet`（`position:fixed;top:0;z-index:951`，浮于输入法之上），含输入框 + 右侧「添加」按钮 +「常用标签」区 +「已选」区，彻底解决输入法遮挡。
- **新建合同必填项（除关键词、联系人外）**：后端 `save()` 在 `if($id===0)` 块校验 our_company_id/party_a_name/party_b_name/effective_date/expiry_date 非空（编辑旧数据不追溯）；桌面端 `create.php` 顶部加 `$isNew` + `$reqMark`/`$reqAttr` 宏，给 8 个字段加红星+required，既有 `wizardValidate()` 自动接管分步校验；移动端 `contract_form.php` 同样字段加红星+required，提交前 JS 遍历校验。甲/乙方联系人/电话为选填。

### 新增

- **高频关键词接口**：`ContractLogic::getHotKeywords($userId,$limit)` 按 `creator_id=本人 AND keywords<>''` 统计词频取 TopN；`ContractController::hotKeywords()` 权限 `contract:create/edit`，limit 钳制 1–50；`route/app.php` ajax 分组加 `Route::get('keyword/hot','Contract/hotKeywords')`。零新表零迁移。
- **桌面端常用标签快速选择**：`create.php` 关键词输入下方加「常用：」标签行；`contract.js` 暴露 `add(text)` 接口供点击调用，无历史则自动隐藏。

### 校验

- `php -l` 全绿；schema parity / 中文注释双校验 0；两端新建页冒烟 200，控件/必填/弹层就位；高频关键词接口隔离验证（employee01 仅见自己创建的词）。

## v2.28.0 (2026-07-18) — P1–P3 全量修复 + 桌面端分层铁律回归（MINOR，CODE_REVIEW_v270 收官）

收官审查报告全部 P1–P3 修复，并完成桌面端「控制器零直查」分层铁律系统性回归（GOLF）。

- **审批引擎（P1-1/2/5 + P2-16）**：审批通过全路径追加 `EXECUTING` 跃迁；`submit()` 仅 `DRAFT`/`REJECTED` 可提交；`recall()` 包事务回滚 DRAFT；超时扫描/外呼补日志与重试。
- **安全/配置/异常（P1-4/6 + P3-3）**：`.gitignore` 忽略 .env 备份；cookie `secure` 默认开启；新增全局异常处理器 `ExceptionHandle` + `error/500.html`/`error/403.php`（修复权限拒绝 500）；JWT 通道装载 `data_scope`；导出文件名清洗；`PreviewController` 路径穿越改 `realpath()`；`DingTalkController` 写 .env 转义；`TemplateController` content 转义防 Self-XSS。
- **上传/回款/发票（P1-3 + P2-4）**：`ResourceController` 上传改服务端 MIME 白名单；新增 `InvoiceLogic::canRegister()` 状态白名单拦截。
- **聚合去重与缓存（P1-7 + P2-12）**：`dashboardSummary()` 统一仪表盘口径 + 缓存；`FinanceLogic` 列表/税汇总下沉；`RemindService` 扫描加缓存。
- **桌面端分层回归（GOLF·核心）**：13 个控制器 `Db::name` 直查清零下沉 Logic；新建 `TemplateLogic`/`ResourceLogic`/`SignLogic`/`AdminLogic`；数据范围统一收敛 Logic（业务实体加 `appendDataScope`，全局配置不加）；修复 `CompanyLogic::getList()` 字段收窄致视图 500。
- **校验**：`php -l` 全绿；schema parity / 中文注释双校验 0；admin + SELF 范围账号冒烟零 500，关键 AJAX `code:0`。

## v2.27.1 (2026-07-17) — P0 严重问题修复（PATCH，CODE_REVIEW_v270 六项 P0 落地）

**本轮聚焦**：基于全量代码审查报告 `CODE_REVIEW_v270.md`（严重 6 / 中等 36 / 轻微 28），优先收敛 6 项 P0 严重问题。全部为 bug 修复，故为 PATCH 级。P1–P3（约 32 人日）作为后续迭代。

- **P0-1 回款资金虚增 + 聚合口径修正**：`PaymentController::confirm()` 部分确认时母记录 `amount` 同步调减为确认额（全额确认保持原额），剩余应收拆 `PENDING` 子记录承载，杜绝翻倍虚增；`revoke()` 把拆出子记录金额加回母记录恢复应收；`ReportLogic`/`ProjectLogic`/`DashboardController`/`PaymentLogic` 已收类统计由 `sum(p.amount)` 改为 `sum(p.paid_amount`；新增幂等校准脚本 `scripts/calibrate_payment_amounts.php`（dev 库 0 行命中·no-op）。
- **P0-2 钉钉 Mock 认证绕过硬阻断**：`DingTalkLogic::ssoLogin()` 生产环境（`app.debug` 空）启用 Mock 直接抛 `RuntimeException` 拦截并 `Log::critical`；开发 Mock 仅放行白名单/已存在 userid，杜绝任意 `code` 造号。
- **P0-3 供应商删除关联校验**：新增 `SupplierLogic::deleteBlockers()`（参照 `CustomerLogic::deleteBlockers`）校验关联采购合同；`SupplierController::delete()` 前置拦截返回中文阻塞原因。
- **P0-4 提交审批去全表扫描 + status 索引**：`ApprovalLogic::processOverdueApprovals()` 由 `where('status','PENDING')->select()` 全表载入改为「id 游标 + LIMIT(200) 分批」+ 预载 `approval_flow` 消除 N+1，并从 `submit()` 移除（改 `approval:escalate` 定时触发）；`approval_instance` 新增 `idx_apv_status(status)`，存量迁移 `database/migration_v2.27_add_approval_status_index.sql`（dev 库已建）。
- **P0-5 钉钉外呼移出 DB 事务**：`ApprovalLogic` 新增 `queueNotify()`/`flushNotify()` 通知队列，`submit()`/`action()`/`autoProcessCc()`/`advanceAfterNode()`/`processOverdueApprovals()` 钉钉外呼全部改为队列，事务提交后发送（失败 `Log::warning` 不影响主流程），消除事务内网络 I/O 持锁。
- **P0-6 分层回归急性子集清零**：新增 `InvoiceLogic`（`getList`/`sumCommitted`/`create`/`find`/`update`/`delete`/`createRed`），`InvoiceController` 12 处 `Db::name` 直查全部下沉；其余控制器下沉留待 v2.28 独立迭代。
- **校验**：`php -l` 全绿；回款部分确认/撤销端到端实测（合同4：确认60→应收100/已收60不翻倍；撤销→应收恢复100）；`approval:escalate` 无错；`idx_apv_status` 已建；`check_schema_parity.sh`=0、`check_db_comments.sh`=0（无表结构变更）。

## v2.27.0 (2026-07-17) — 移动端 Phase 2 功能补齐（MINOR：甲乙方解耦 + 高级筛选 + 状态变更 + 回款操作 + 归档取消 + 项目详情 + JS 模块化 + 转交分页）

**本轮聚焦**：移动端工作台 Phase 2 功能补齐，覆盖合同全生命周期操作（新建→筛选→状态变更→回款→归档/取消），新建项目详情页，JS 模块化消除重复代码，转交用户列表支持 AJAX 搜索+分页。

- **2.0 甲乙方身份与收付款方向解耦**：新建合同页甲乙方自由选侧（「本公司」按钮双侧均有），收付款方向（sales/purchase）改为独立控件，后端不强行推导 direction；解决技术服务合同（我方乙方+收款）被误判的问题。
- **2.2 合同高级筛选**：移动端合同列表增加底部抽屉筛选（方向/类别/性质/类型/签约主体/归属人/相对方/金额区间），复用 `ContractLogic::getList` filter 字段；新增 `UserLogic::getOptions()` 供归属人下拉（零 Db 直查）。
- **2.3 合同状态手动变更**：合同详情底部操作栏增加状态变更按钮组，复用 `ContractLogic::TRANSITIONS` 状态机 + 桌面端 `statusTransition` AJAX 端点；权限与桌面端一致（归属人/创建人/管理员）。
- **2.4 回款确认/撤销**：合同详情回款计划每行增加「确认到账」/「撤销」按钮，调用 `payment/confirm` / `payment/revoke` AJAX 端点；权限 `payment:create`。
- **2.5 归档执行/取消归档**：移动端归档列表每行增加「取消归档」按钮，补 `archive/<id>/undo` 路由（ArchiveController::undo 方法已存在但缺路由）；权限 `contract:edit`。
- **2.6 项目详情页**：新建 `/m/project/<id>` 移动端项目详情页，复用 `ProjectLogic::getDetail/aggregate/getContracts`；展示项目概览 + 经营聚合（合同数/销售/采购/应收/已收/回款率）+ 关联合同列表；项目列表链接改为移动端详情页。
- **2.7 JS 模块化**：提取共享 Lightbox 代码到 `public/static/js/mobile/lightbox.js`，消除合同详情/审批详情两份重复副本；合同详情 JS 改用 `mobile-common.js` 的 `confirmAndPost` 替代手写 fetch+toast+loading；审批详情移除重复 `toast/showLoading/csrfToken` 定义（已由 `mobile-common.js` 提供）。
- **2.8 转交列表分页 + 权限范围**：新增 `UserLogic::getTransferTargetsPaged`（分页版）+ AJAX 端点 `/ajax/approval/transfer-targets`；移动端审批详情转交搜索改为 AJAX 搜索（300ms 防抖）+ 分页「加载更多」，替代原客户端过滤（limit 200）。
- **校验**：`php -l` 全绿；移动端 8 页 + 合同详情 + 审批详情 + 项目详情 + 归档 + AJAX 端点全量 HTTP 200 / `code:0`；匿名访问拦截 302；`check_schema_parity.sh`=0（26 表/265 字段）、`check_db_comments.sh`=0（81 表全表级注释 + 全字段 `-- 注释`，无表结构变更）。

## v2.26.0 (2026-07-17) — 移动端架构重构（MINOR：Logic 层下沉 + 共享布局 + 去模板收口）

**本轮聚焦**：移动端工作台（MobileController）架构重构，消除控制器内 Db 直查、统一共享布局、收口审批流去模板落地，为后续 Phase 2/3 功能补齐与工程收尾打底。

- **Logic 层下沉**：MobileController 内 `Db::name` 直查由 16 处降至 0；新增 `UserLogic`/`CompanyLogic`/`RoleLogic`，扩展 `ContractLogic`(`getMyCount`/`getById`/`getRelatedList`)、`ApprovalLogic`(`getPendingAction`)、`PaymentLogic`(`getListByContractIds`)、`SupplierLogic`(`getList` 复用)；数据权限作用域随 Logic 内聚（SELF/DEPT/ALL）。
- **共享布局**：17 个移动端视图（login 除外）统一引入 `_head.php`/`_foot.php` 片段，消除 17 份完整 HTML 外壳副本；支持 `$pageStyle` 内联样式与 `$extraCss`（Bootstrap）页级注入。
- **去模板落地收口**：审批流匹配仅按 分类+金额（`ApprovalLogic::matchFlow`），移除全部 `contract_template` 直查依赖；合同创建/详情不再依赖模板默认流。
- **校验**：`php -l` 全绿；移动端 15 页 + 核心 AJAX 全量回归 HTTP 200 / `code:0`、0 PHP 错误；`check_schema_parity.sh`=0、`check_db_comments.sh`=0（无表结构变更）。

## v2.25.1 (2026-07-17) — v2.25.0 修复版（PATCH：仪表盘缓存修复 + 模板/发票前端软移除）

**本轮聚焦**：基于 v2.25.0 的稳定修复版，作为独立部署基线；不改变后端数据结构、路由与权限模型。

- **BUG 修复**：`DashboardController` 两处 `Cache::remember()` 参数顺序修正（Closure 与 TTL 位置互换），修复仪表盘页面 HTTP 500（Closure 无法转为 int）。
- **前端软移除（后端预留）**：隐藏「合同模板」Tab/子菜单与「发票管理」入口；移除合同创建/详情页模板选择器与结构化字段面板、合同详情与移动端发票记录及发票 Modal、财务中心发票/税务 Tab；后端 Controller/Logic/路由/数据表/权限全部保留，供后续功能恢复。
- **校验**：`php -l` 全绿；`check_schema_parity.sh`=0（26 表/265 字段）、`check_db_comments.sh`=0（81 表全表级注释 + 全字段 `-- 注释`）；无表结构变更。

## v2.25.0 (2026-07-16) — P0/P1/P2/P3 全量代码审查修复（MINOR，60 项落地 43 项）

**本轮聚焦**：合同内部审批流程管理 + 合同执行进度跟踪管理 + 合同基本信息录入与维护。签署/电子签章暂缓，跨部门协作暂缓，移动端重构推迟。

- **P0 紧急**：附件预览认证校验、空审批人阻断、会签超时升级机制、会签节点推进修正。
- **P1 安全/功能**：Session 加固、CSRF 全量落地、越权门禁 ×4、已办含全状态、交易内审批审计补全、全抄送免签路径修复（DRAFT→PENDING_APPROVAL→APPROVED）、回款撤销/发票作废红冲补 UI+路由×4、合同编号事务+前缀匹配。
- **P2 性能/权限**：Dashboard/字典 Cache::remember 缓存 ×4、统计口径收口 statusCountMap、趋势 (clone) 修复叠加 WHERE、供应商安全上限、登录 computeScope 写入会话、上传 finfo 白名单、父合同下拉权限收敛、appendDataScope 无 user 永假。
- **P3 体验/整洁**：XLSX 导出（无依赖 ZipArchive）×3、批量操作（归档/删除+权限+状态校验）、高级筛选（金额区间/相对方/主体/归属人）、提醒 GET 移命令行、移动端客户认领/转移/释放、static 常量 STATUS_LABELS 消除重复、流式导出 StreamedFileResponse、移动端分页统一/角标缓存、字典跨请求 Cache L2。
- **架构决策**：状态机放宽 APPROVED→EXECUTING（签署可选）；remind:check 命令；Project 魔数常量化。
- 校验：`php -l` 全绿；`check_schema_parity.sh`=0（26 表/265 字段）；`check_db_comments.sh`=0（81 表全表级注释+全字段 `-- 注释`）。

## v2.24.0 (2026-07-16) — 移动端深化：数据看板 / 归档合同 / 项目列表（MINOR，P2-C CR-17）

- **移动端数据看板（/m/reports）**：聚合合同总数、经营总额（仅 `trade_attr=1` 计入收支）、10 态分布、回款概览、收支方向（销售应收/采购应付）、客户/供应商/即将到期；按 `project:view` 门控项目入口，状态分布可点击筛选。
- **移动端归档合同（/m/archive）**：独立归档列表（`status='ARCHIVED'` + 数据权限），关键词搜索 + AJAX 分页，链接桌面合同详情。
- **移动端项目列表（/m/projects）**：`ProjectLogic::getList()` + 状态字典，AJAX 分页，链接桌面项目详情。
- **路由与权限**：`route/app.php` 注册 `/m/reports|archive|projects` 并加 `requirePermission('*::view')`；首页「数据看板」卡片入口对齐；状态映射统一走 `contract_status_map()` / `contract_status_badge()`（CR-57）。
- XL/L 重构项 CR-10/CR-27/CR-38 标注留待独立技术方案，本次不混入破坏性重构。
- 验证：`php -l` 全绿；`check_schema_parity.sh`=0、`check_db_comments.sh`=0；临时 `mobile:check` 命令验证 reports/archive/projects 三页与 AJAX 渲染正常且自清理。

## v2.24.0 (P3 整洁) — 文档/细节优化（CR-40/41/43/44/45/47/51/52/53/54/56/57，随本版一并交付）

- **CR-40**：`AuthLogic::appendDataScope()` 补「多角色取最高范围 ALL>DEPT>SELF」语义详注。
- **CR-41**：`RemindService` 三方法（check 写触发 / getTodayAlerts·scan 纯读 / dispatch 钉钉推送）职责边界与调用方注释化。
- **CR-43**：未登录重定向携带 `?redirect=<原URL>`，登录后安全回跳；新增 `safe_redirect_url()` 仅允许站内相对路径，杜绝开放重定向。
- **CR-44**：`Auth` 中间件 `dingtalk_session` 查找加 60s 缓存（仅命中缓存，保证吊销即时）。
- **CR-45**：`Csrf` 中间件注释定稿「会话级固定 token、不轮转」的并发兼容性结论。
- **CR-47**：新增 `error/403.html` 友好页，`BaseController::deny()` 非 AJAX 渲染之。
- **CR-51**：`ApprovalLogic` 抽取 `NODE_*/MODE_*` 常量替换魔术字符串。
- **CR-52**：`BaseController` 角标/待办红点加 60s 缓存。
- **CR-53**：`contract_categories()` 加静态缓存。
- **CR-54**：`exportExcel` 改 `chunk(500)` 分批导出，降内存峰值。
- **CR-56**：`FinanceController` 按月格式化抽为 `db_month_expr()` helper，控制器去方言判断。
- **CR-57**：新增 `contract_status_map()` / `contract_status_badge()` 公共 helper，移动端三处重复收敛。
- P3 为向后兼容整洁项，无 Schema 变更；`check_schema_parity.sh`/`check_db_comments.sh` 均 0。

## v2.23.1 (2026-07-16) — 移动端「新增」悬浮按钮配色异常修复（PATCH）

- 修复移动端客户/供应商等「新增」悬浮按钮（`+` 图标）被全局 `a:-webkit-any-link` 规则压成深色、蓝底叠深灰的配色异常；根因为全局链接规则的 `color:inherit` 特异性高于组件类，已将 `color` 拆到低特异性 `a` 规则，并显式锁定 `.m-fab i { color:#fff }`。
- 顺带修复所有 `<a class="m-btn-*">` 按钮（通过/驳回/提交等）文字色被同一规则压成深色的同类问题。
- 移动端合同列表页补「新建合同」悬浮 FAB（权限门控 `contract:create`），与客户的/供应商新增入口样式统一。
- 全部移动端视图 `mobile.css?=v2.23.1` 缓存刷新。

## v2.23.0 (2026-07-16) — P0 + P1 安全 / 数据一致性 / 性能 / 可靠性批量修复（MINOR）

- **P0（上轮交付并验证）**：CR-01 匿名/越权访问控制（匿名→302、越权→403、自有→200）；CR-02 空审批人提交返回明确中文错误；CR-03 超时会签节点自动审批(AUTO_APPROVED)并推进。
- **P1 数据一致性（CR-06/15/16/30/31）**：DRAFT 不可直跳 ARCHIVED；合同/客户删除前校验关联审批/回款/发票/合同；审批 `action()` 与合同 `create/update` 事务包裹防孤儿数据。
- **P1 安全（CR-22/33/50）**：`partySearch` 客户/供应商搜索补行级数据权限收敛；JWT 密钥废弃可预测兜底，签发与验证统一走 `AuthLogic::jwtSecret()`（未配置则运行时随机持久化并告警）；钉钉免登入口加固，禁止 `//evil.com` 协议相对与 `\` 反斜杠开放重定向。
- **P1 性能（CR-29/04/05/60）**：三核心表补 6 个索引（contract 的 dept_id/expiry_date/created_at + 复合(trade_attr,direction,status)、payment_record 的(status,planned_date)、approval_record 的(approver_id,action)）；`ContractLogic::getList` 子合同计数与 `RbacService::getUserList` 角色查询由 N+1 改为一次性预聚合；审批待办/已办列表改用子查询分页消除无上限 `column('instance_id')`；`ApprovalLogic::submit()` 异常补 `Log::error`。
- **P1 可靠性（CR-07/19/25/32/11）**：归档可逆（`ARCHIVED→EXECUTING` 取消归档 + 审计日志，新增 `ArchiveController::undo`）；`AuditService` 失败由静默吞没改为 `Log::error` 带上下文；`RemindService` 财务角色由硬编码 `role_id=4` 改为按 `code='finance'` 关联查询；钉钉提醒推送失败重试一次并写 `failed` 计数与告警日志。
- 验证：P0 3/3、P1 数据一致性 17/17、P1 安全 11/11、P1 性能 7/7、P1 可靠性 5/5 通过；`check_schema_parity.sh`=0、`check_db_comments.sh`=0。

## v2.22.4 (2026-07-16) — 全量表级 + 字段级中文注释（含 SQLite 与迁移脚本）

- **表级中文注释（强制补全）**：此前仅 `init_mysql.php` / `init.sql` 的 26 张表带 `COMMENT='...'` 表注释；`init_sqlite.php` 因 SQLite 不支持表级 `COMMENT` 而缺表名注释。现统一补齐：
  - `init_sqlite.php` 26 张表在 `CREATE TABLE` 内首行写入 `-- 表注释：中文名——说明`。
  - `migration_v2.2.sql` 的 `supplier` / `contract_invoice` / `remind_log` 三张表补齐表级 `-- 表注释：` 与字段 `-- 中文` 注释（此前零注释）。
- **校验升级**：`scripts/check_db_comments.sh` 由「仅字段注释」升级为「表级 + 字段级」双校验，文件列表纳入 `migration_v2.2.sql`，缺表注释或字段注释均非零退出（`release.sh` 已内置为发布卡点）。
- **规范固化**：`DEVELOPMENT_GUIDE.md` §7 升级为「表 + 字段中文注释约定」，明确 MySQL 用 `COMMENT='...'`、SQLite 用 `CREATE TABLE` 内首行 `-- 表注释：`，并将迁移脚本纳入适用范围；§9 清单同步更新。
- 现状：四份脚本 **81 张表均带表级注释、所有字段带 `-- 中文注释`、0 缺失**，三份 init 脚本「表+字段」与基准 `init_mysql.php` 完全一致（26 表 / 262 字段）。

## v2.22.3 (2026-07-16) — init.sql 字段补全 + 三份初始化脚本对照卡点

**数据库初始化脚本修复**：

- 以 `database/init_mysql.php` 为唯一基准，全量重写严重过时的 `database/init.sql`：此前缺 7 张表（company_profile、contract_invoice、payment_record、project、remind_log、resource_library、supplier）+ 18 个字段，且残留废弃表 `contract_field_value`、旧 17 权限种子与旧审批 JSON 格式，以其建库会导致部署报错。
- 重写后 `init.sql` 为基准的 1:1 SQL 镜像：**26 张表 / 262 字段**，每字段带 `-- 中文注释`，含完整种子数据（5 角色含 data_scope + 38 权限 + 角色权限映射 + admin/3 演示用户 + 3 审批流「新版节点 JSON」+ 3 模板 + 7 示例合同 + 2 公司档案 + 3 资料库项 + 2 项目 + system_config/字典）。

**字段对照检查机制（防复现）**：

- 新增 `scripts/check_schema_parity.sh`：以 `init_mysql.php` 为基准，对 `init_sqlite.php` 与 `init.sql` 做「表集合 + 每表字段集合」1:1 一致性校验，缺表/多表/缺字段/多字段均打印明细并非零退出。解析复用 `check_db_comments.sh` 的逐行状态机词法（正确处理 SQLite `datetime('now','localtime')` 括号默认值）。
- 已接入 `scripts/release.sh` 发布卡点（`php -l` 之后、打包之前，`--force` 可跳过）；`check_db_comments.sh` 扩展覆盖三份文件。
- 文档：`DEVELOPMENT_GUIDE.md` §7 适用范围扩至三文件、新增 §7.1「三份初始化脚本对照机制」、§9 检查清单新增对照校验项。
- 校验结果：三份脚本均 **26 表 / 262 字段 / 0 差异**，注释 262/262 完整；负向测试（人为删字段）能被正确拦截并定位到表.字段。

---

## v2.22.2 (2026-07-16) — 补齐数据库字段中文注释 + 固化开发规范

**规范与文档**：

- 复核并补齐 `database/init_mysql.php` 中遗漏 `-- 中文注释` 的 5 个字段（status ×3、data_scope、nodes），此前误用 MySQL 原生 `COMMENT '...'` 替代；现 `init_sqlite.php` 与 `init_mysql.php` 均为 **262/262 字段带 `-- 中文注释`、0 缺失**，两份脚本 1:1 对齐（26 张表）。
- `DEVELOPMENT_GUIDE.md` §7 升级为「强制」约定：明确两份初始化脚本每个字段须在行尾附 `-- 中文注释`，禁止用 MySQL `COMMENT` 替代；新增发布前校验脚本 `scripts/check_db_comments.sh`（缺注释非零退出），并加入 §9 发布前检查清单。

## v2.22.1 (2026-07-16) — 修复移动端底部 Tab 已访问链接下划线

**问题修复**：

- 修复 iOS Safari 下点击底部 Tab（合同/客户/审批）后 **所有 Tab 及合同列表卡片** 被强制加下划线的问题：根因为 iOS 对所有 `<a href>` 默认施加 `a:-webkit-any-link { text-decoration: underline }`，且手机浏览器对 `mobile.css` 缓存顽固，导致旧样式长期不更新。
  - 全局链接重置显式覆盖 `a:-webkit-any-link`（含 `:link` / `:visited` / `:hover` / `:focus` / `:active` 态），并清理 `html,body` 块外的游离 `-webkit-font-smoothing` 语法错误；
  - 为全部 14 个移动视图的 `mobile.css` 引用追加版本查询 `?v=2.22.1`，**强制手机端重新拉取最新样式**，彻底破除 iOS CSS 缓存；
  - 工作台页（index.php）补充同等全局链接重置。

## v2.22.0 (2026-07-15) — 移动端体验深化 + 设备自动判断发布版

**移动端深化**：

- **设备自动判断**：新增 `is_mobile_request()` 函数（基于 `HTTP_USER_AGENT` 识别手机/平板/微信/钉钉 WebView）；`AuthController` 登录分流、`DashboardController` 根路径分流、`Auth` 中间件未登录重定向分流，4 个入口全覆盖；新增 `/m/login` 路由与移动端登录页（含 `force_reset` 强制改密支持）。
- **审批附件独立卡片**：附件从「合同正文」卡内移出为独立醒目卡片（带文件类型图标 + 大小 + 打开箭头），紧贴审批概要卡之后、审批进度之前；数据契约 `[{url,name,size}]` 已用真实库匹配确认。
- **回款登记迁移**：工作台不再铺大表单，快捷操作跳 `/m/finance#add`；财务页新增 FAB 悬浮按钮 + 底部弹层（`m-sheet`），含合同搜索、金额、日期、备注、CSRF token 提交 `POST /ajax/payment/add`。
- **搜索框统一**：供应商页顶部补「客户/供应商」分段切换，两页结构对齐为「切换 → 搜索框 → 筛选 → 列表」。
- **按钮配色对齐**：登记回款提交按钮由 `m-btn-ok`（绿）改为 `m-btn-brand`（蓝），与全系统主操作配色一致。
- **跳电脑页修复**：移动首页「今日提醒」合同链接拼为 `/contract/<id>`（桌面路径）→ 修正为 `/m/contract/<id>`，移动端 3 处桌面跳转全部闭环。

**全量代码审查**：SQL 注入 / 权限覆盖 / CSRF / XSS / 文件上传安全 / 状态码语义 / 设备判断死循环 / 回款 POST 路径 / 元素 ID 完整性，结论健康无阻断性 bug、发布就绪。详见 `CODE_REVIEW_v222.md`。

## v2.21.1 (2026-07-15) — 全量代码审查修复（安全性 / 性能 / 鉴权一致性 / HTTP 状态码语义）

**审查范围**：功能完整性 / 代码质量 / 性能 / 安全性 / 边界条件（详见 `CODE_REVIEW_2026-07-15.md`）。

**修复项（F1–F4）**：

- **F1 安全加固**：`config/app.php` 的 `app_debug` 默认值由 `env('APP_DEBUG', true)` 改为 `env('APP_DEBUG', false)`，避免缺少 `.env` 时泄露调试信息与 SQL 日志（双重保险，`.env` 已设 `APP_DEBUG=false`）。
- **F2 性能**：`FinanceController` 回款列表 / 发票列表原为无 LIMIT 全表 `->select()`，改为服务端 `page()` 分页并随响应返回 `total`；移动端 `app/view/mobile/finance.php` 与桌面端 `app/view/finance/index.php` 同步接入"加载更多"前端分页。
- **F3 鉴权对齐**：`MobileController` 6 个方法（contracts / contractDetail / customers / customerDetail / suppliers / supplierDetail）补 `requirePermission('*::view')`，与桌面端权限门保持一致，消除移动端越权查看缺口。
- **F4 HTTP 状态码语义修正**：`app/middleware/Auth.php` 未登录返回 `json([...], 401)`、强制改密（已登录被禁）返回 `json([...], 403)`；修正此前 `json()` 默认 200 导致匿名写请求返回 HTTP 200 的语义错误（请求实际已被拦截，仅状态码错误），与 `Csrf`(403) / `BaseController::deny()`(403) 保持一致。

**验证**：全量 `php -l` 通过；回归覆盖匿名态（401 / 302 跳登录）、登录态（缺 CSRF 返回 403、带 CSRF 返回 200）、全部 AJAX 接口 `code:0`；修正前 `POST /ajax/contract/save` 匿名返回 HTTP 200，修正后返回 **HTTP 401**。

## v2.21 (2026-07-15) — 审批流程自动化：自动匹配 + 角色绑定 + 抄送节点

**审批引擎**：

- 提交审批改为**自动匹配流程**：按「分类+金额」`matchFlow` 匹配，匹配不到回退合同模板 `default_flow_id`，再无则报错；前端去掉人工选流程下拉，改为只读展示将使用的流程预览。
- 节点**角色绑定**：审批人由节点绑定的角色（`role_code`）自动解析，无需指定具体人；`resolveApprovers()` 增强 `CC` 分支。
- 新增**抄送节点（CC）**：流程可配抄送节点（多角色），审批走到该节点时自动写抄送记录并钉钉知会，继续流转；置于末尾即实现「审批完成后抄送财务」。
- 新增**会签/或签（mode）**：审批节点支持 AND（会签）/ OR（或签）。
- `matchFlow` 多命中时取\*\*范围最窄（最具体）\*\*流程，修复小额误匹配缺陷。

**后台配置**：审批流程编辑器新增「抄送节点」类型（多角色选择）、审批节点新增「会签/或签」选择器。

**演示数据**：3 条流程改为角色绑定（manager/legal/finance）+ 大额流程加财务抄送；admin 兼任 legal 角色。

**验证**：合同（15万）提交自动匹配大额审批→经理→财务会签→抄送财务 全链路通过；匹配优先级（简易/标准/大额）正确。详见 `DESIGN_approval_v221.md`。

## v2.20 (2026-07-14) — 数据库字段中文注释全量补齐 + MySQL 兼容加固

- **字段中文注释**：对全部 20 张表 262 个字段补齐中文注释，`CREATE TABLE` 每个字段行尾标注 `-- 中文注释` 说明含义（约定：新建表 / 修改表新增字段均须遵守，详见 `database/init_sqlite.php` 顶部规则）。
- **MySQL 兼容**：`app/middleware/SqliteGuard.php` 在 `config('database.default') !== 'sqlite'` 时直接 no-op，避免迁移 MySQL 后对非 SQLite 连接执行 `PRAGMA` 专有指令报错，为后续迁移 MySQL 铺路。

## v2.19.2 (2026-07-14) — 产品全量审查 + 交付部署包

**审查与交付**：从产品视角全量审查功能与代码，输出 `PRODUCT_AUDIT_v2.19.2.md`；重写 `DEPLOY.md`（v2.19.2 详细部署文档，含功能地图/环境/生产 Nginx 部署/配置/备份/定时任务/钉钉/权限/升级回滚/安全清单/FAQ/目录结构）。

**修复项（审查驱动）**：

- 统计口径一致性：`DashboardController` 回款聚合与收支方向概览、`FinanceController` 财务汇总统一补 `->where('trade_attr', 1)`，与 v2.18「非交易不计入收支」设计及 `totalAmount`/`ProjectLogic` 口径对齐。
- 生产安全：`config/database.php` 的 `debug` 由硬编码 `true` 改为 `env('APP_DEBUG', false)`，避免生产记录全部 SQL。
- 可移植性：`database/verify_*.sh` 与 `tests/*.sh` 中硬编码 `/Users/fengjian/bin/php` 改为 `${PHP_BIN:-php}`。

**审查结论**：功能闭环完整，安全基线（认证/CSRF/IDOR/XSS/并发/审计）已具备，达到交付标准。自动化审查标记的 SQLite 并发、弱密钥、XSS、开放重定向等项经人工复核均已在 v2.19.1 及历史迭代中解决或为误报，未重复修复。

## v2.19.1 (2026-07-14) — 热修复：快速点击菜单并发写库导致页面出错

**问题**：连续快速点击菜单图标（尤其驾驶舱）出现 500 错误。  
**根因**：`RemindService::check()` 每次访问驾驶舱向 `remind_log` 写库（`shouldRemind` 先查后插无竞态保护），并发请求下 SQLite 单写锁 `database is locked`，而 `DashboardController` 未捕获异常冒泡成 500。  
**修复**：

- `remind_log` 加唯一索引 `uk_remind_dedup`（`target_type, target_id, remind_type, remind_at`）
- `shouldRemind` 改 `INSERT OR IGNORE`（原子去重，消除 check-then-act 竞态与重复记录）
- 新增 `app/middleware/SqliteGuard.php`：每请求 `PRAGMA busy_timeout=5000` + `journal_mode=WAL`（WAL 已固化进 `init_sqlite.php`，读写并发、写等待而非立即失败）
- `DashboardController::index()` 的 `check()` 调用加 try-catch 兜底防 500
- `footer.php` 前端导航防抖（拦截 500ms 内同链接重复点击）  
  **验证**：CLI 12 进程并发写零 locked、去重仅 1 条；HTTP 30 并发点驾驶舱 0 错误。

## v2.19 (2026-07-13) — 合同→项目关联（P2-5）

> 经营深化第二批首项：把分散的合同按「项目」维度归集，支持项目级经营聚合（应收/已收/回款率）与驾驶舱项目 TOP N 视图。

### 项目管理模块

- 新增 `project` 表（name/code/customer_id/owner_id/dept_id/status/budget/起止日期/remark，软删+时间戳+owner 索引）；`contract` 加 `project_id` + `idx_contract_project` 索引。
- 新增 4 个权限码（`project:view/create/edit/delete`，id 34-37）；角色授权：Admin 全量、Manager 含增删改查、User 含查看/创建/编辑；`dict_project_status` 字典（进行中/已完成/已归档）。
- 种子：项目 PRJ-2026-001（上海科技-年度技术服务）、PRJ-2026-002（智能制造设备采购），并将种子合同 #1/#2 归入对应项目。

### 后端与视图

- `ProjectLogic`：create/update/softDelete/getDetail/getList（带 contract_count 聚合，走数据权限）；`aggregate()` 返回合同数/销售额/采购额/应收/已收/回款率（口径沿用 v2.18：仅 `trade_attr=1` 且 `direction in sales/purchase`，回款用 payment_record RECEIVABLE/PAID）；`options()` 下拉；`topProjects()` 驾驶舱 TOP N（走数据权限）。
- `ProjectController`：列表/新建/编辑/详情/软删（关联合同 `project_id` 解绑置 0 防悬空）/options；分场景权限 + 越权防护 + 审计（target_type=project）。
- 项目列表/新建/详情三视图 + `project.js`；侧边栏「项目管理」菜单（`project:view` 守卫）。

### 合同关联

- 合同新建页加「关联项目」下拉；`ContractLogic` getList/getDetail leftJoin project 返回 `project_name`；合同列表加「按项目筛选」下拉 + 项目列，详情页展示关联项目（可跳转）。
- 驾驶舱新增「按项目 TOP N」卡片（应收/已收/回款率进度条，走数据权限）。

### 验收

- `tests/acceptance_p2_5.sh` 17/17 全绿（项目 CRUD / 列表 layui / 详情聚合 / 合同关联 project_name / 按项目筛选口径隔离 / options / 驾驶舱 TOP N / 软删解绑 / 审计 9 条）。

## v2.18 (2026-07-14) — 合同交易属性 / 非交易合同建模（P2-9）

> 经营深化批次首批：修正统计口径地基，让 NDA/意向书/合作协议等非交易合同从收付款统计中干净排除，并改善创建引导。

### 合同交易属性建模

- 新增 `contract.trade_attr`（1=交易 / 0=非交易）、`contract_template.default_trade_attr`；非交易合同 `amount` 强制 0、`direction` 置空。
- 创建/编辑页加「合同性质」单选 + 动态显隐；模板预设 `applyTemplate` 联动（选 NDA 类模板自动切非交易）。
- 统计口径修正：仪表盘 `dir_summary`、财务中心收支概览改为 `WHERE direction IN ('sales','purchase')`，修复"空 direction 归入 sales"污染计数 bug；经营总额收敛为仅交易合同。
- 后端拦截：非交易合同禁止开票/回款（友好提示）。
- 列表「合同性质」筛选 + 非交易徽标；移动端「我的合同」标注 + 回款搜索排除。
- 验收：`verify_v218.sh` 16/16 全绿（非交易落库 / 统计排除 / 开票回款拦截 / 列表筛选 / 交易合同回归）。

### 发票税务视图（P2-8）

- `contract_invoice` 补 `tax_rate`、`tax_amount`；新增 `dict_tax_rate` 税率字典（13/9/6/3/0%）。
- `InvoiceController::add` 按含税价价税分离算税额（税额=金额/(1+税率)×税率）；开票弹窗加税率下拉 + 税额实时预览。
- 新增税务汇总页 `/finance/tax` + `FinanceController::tax/taxData`（`GET /ajax/finance/tax-data`）：按月分销项(销售)/进项(采购)汇总金额与税额，算「应纳税额=销项税额−进项税额」，合计行 + CSV 导出；财务中心加入口。
- 合同详情「发票记录」加开票进度条（已开/合同/未开）。
- 验收 `verify_v218_p28.sh` 6/6（税务页 200 / 聚合接口 / 含税率开票 / 税额=600 / 非交易拦截 / 月度聚合正确）。

### 备份策略与高危留痕（P3-4）

- 新增 CLI `php think db:backup [--keep=N]`（`app/command/DbBackup.php`）：优先 SQLite `VACUUM INTO` 一致性快照、回退文件拷贝，输出 `runtime/backup/`，保留最近 N 份（默认 14）自动清理；`config/console.php` 注册。
- `ContractController::exportExcel` 导出加审计留痕（`AuditService::log` action=export，记录条数/范围）；合同删除既有留痕保留。
- `DEPLOY.md` 补 crontab 备份示例与恢复说明。

### v2.19 排期设计

- 新增 `DESIGN_v219_plan.md`：P2-5 合同→项目、P2-6 相对方360、P2-7 条款库、P3-2 报表导出的范围与验收；P3-1 移动端（前提待确认）、P2-3 电子签章（暂缓·按需）标注。

## v2.17 (2026-07-14) — 小互联网公司功能路线图 P0 + P1 落地

> 承接上轮产品路线图：P0（现金流安全 + 日常可用）落地提醒主动推送与经营驾驶舱；P1（提效 + 结构化录入）落地模板结构化字段驱动表单与轻量移动端工作台。

### P0-A 提醒自动触发 + 钉钉主动推送

- 新增 `RemindService::dispatch()`：扫描①到期预警(30/15/7/3/1天)②已到期(抄送 admin)③逾期回款(抄送财务 role_id=4)④即将到期回款(7/3/1天,抄送财务)；按用户聚合 markdown，`DingTalkService::sendToLocalUsers()` 推送工作通知；`push_<type>` 前缀写 `remind_log` 全局去重。
- 新增 CLI `php think remind:dispatch`（`app/command/RemindDispatch.php` + 新建 `config/console.php`），供 crontab 定时（如 `0 9 * * *`）。
- 新增 `RemindController::dispatch()` / `pushLog()`（`remind:manage`）+ 路由 `POST /ajax/remind/dispatch`、`GET /ajax/remind/push-log`；提醒页加「立即推送到钉钉」「推送记录」按钮与弹窗。

### P0-B 经营驾驶舱增强

- `DashboardController::index()` 新增 `month_received`（`actual_date` 本月内 PAID 回款 SUM）与 `dir_summary`（按 `direction` GROUP BY 汇总 sales/purchase 的 total/cnt，复用 `AuthLogic::appendDataScope`）。
- 仪表盘视图 KPI 行下插入「本月经营 + 收支方向概览」卡片：本月已收 / 本月预计回款 / 待我审批（点击→/approval）/ 销售应收（点击→/contract?direction=sales）/ 采购应付（点击→/contract?direction=purchase）。

### P1-C 结构化字段模板驱动表单

- `contract` 表新增 `custom_fields TEXT DEFAULT '{}'`；运行库经 `/tmp/migrate_v217.php` ALTER 迁移（Schema 源 `database/init_sqlite.php` 同步）。
- `TemplateController::getPreset` 解析并返回 `fields_schema`（JSON 数组 {key,label,type,required,options}）。
- `create.php`：`applyTemplate()` 按 schema 动态渲染字段（text/number/date/select/textarea），提交前 `collectCustomFields()` 收集+必填校验写入隐藏 `custom_fields`；切模板保值、编辑场景 DOMContentLoaded 回填。
- `ContractController::save` 接收 `custom_fields` 并 JSON 校验落库；`detail()` 加载模板 schema，详情页展示结构化字段（select 值映射回 label）。
- 种子模板 1（媒体投放：platform/period/kpi/settlement[select]/account_period）、模板 2（采购：deliverables/acceptance/warranty/quote_no）。

### P1-D 轻量移动端工作台

- 新增 `MobileController::index()` + `app/view/mobile/index.php` + 路由 `GET /mobile`（走 Auth 中间件，钉钉免登入口 `/dingtalk/entry?to=/mobile`）。
- 移动端自适应三板块：我的待办（`ApprovalLogic::getPendingList` + `RemindService::getTodayAlerts`）、我的合同（近 8 条走数据权限）、快速登记回款（`/ajax/contract/search` 选合同 + `/ajax/payment/add` 登记）。含底部 tabbar、CSRF 自动携带（复用 `app.js`）。

### 验收（动态）

- 脚本 `/tmp/verify_v217.sh`：登录 admin → 18 项断言（P0-A 推送/去重/推送记录、P0-B 本月经营+收支方向+待审批入口、P1-C preset schema+创建落库+详情展示、P1-D 移动端可达+三板块+免登入口）。**结论：18/18 全绿。**
- P0-A 引擎实测：构造 3 天后到期合同 → CLI `remind:dispatch` 推送「合同提醒 1 条 / 通知 1 人」，二次执行去重为 0；`push-log` 接口可见 sendWorkNotice(user_ids[1], 到期 markdown)。

## v2.16 (2026-07-14) — 本公司主体自动识别（P0 深化）+ 资料库（P1 优化 + 新功能）

> 源自 PM 视角追问：小互联网公司用本系统时，还需补齐「多主体签约」「合同拟定参考素材」能力。深化 P0（签约主体 + 甲/乙立场），并以资料库取代原文本模板诉求（P1 优化）。

### 本公司主体 `company_profile`（深化 P0）

- 新增 `company_profile` 表（name/short_name/unified_social_credit_code/tax_no/bank_name/bank_account/address/tel/legal_rep/is_default）；种子 2 个主体（运营默认、技术）。
- `contract` 表新增 `our_company_id`；新建合同「签约主体」下拉默认带出默认主体（`ContractController::create` 传 `companies`/`default_company_id`）。
- 合同创建「本公司」快捷按钮改调 `GET /ajax/company/options`（原取自客户 `is_self`），自动填名并联动 `companySelect`。
- 详情页新增「签约主体」行与「开票资料」行（统一信用代码/开户行/账号/地址/电话）；方向行深化为「我方为甲方（收款）/ 我方为乙方（付款）」。`contract.js` 列表徽标同步。
- 新增 `CompanyController`（index/options/save/delete）+ 路由；`/company` 页面（系统设置内，管理员）。

### 资料库 `resource_library`（P1 优化 + 新功能）

- 新增 `resource_library` 表（category/title/file_url/file_name/file_size/description/company_id/owner_id）；分类 TEMPLATE/INVOICE/CLAUSE/OTHER。
- 新增 `ResourceController`（index/list/save/delete）+ 路由 `/resource`、`/ajax/resource/*`；页面按分类筛选、上传、删除（复用格式/MIME/20MB 安全校验，存 `public/uploads/library`）。
- 合同拟定页「参考资料库」按钮（`openResourceModal`）弹窗列出范本/开票资料，可预览下载，仅作参考不强制灌入。
- 侧边栏新增「资料库」菜单（`library:view`）；权限 `library:view` 全员、`library:manage` 管理员+经理、`company:manage` 系统设置。
- 种子资料：媒体投放服务合同范本、主体1 开票资料、保密与竞业限制标准条款。

### 验收（动态）

- 脚本 `/tmp/verify_v216.sh`：登录(admin/password，登录前临时关 force_reset) → 24 项断言（主体默认带出/本公司按钮/方向推导/详情签约主体与甲乙方/资料库上传列表参考/权限）。**结论：24/24 全绿，无回归。**

## v2.15 (2026-07-14) — 合同方向（P0）+ 模板重构为合同类型预设（P2）

> 源自 PM 视角审查：合同以附件上传、仅填关键信息，故「模板」价值不在正文，而在「帮用户表达我方立场」。落地 P0（合同方向字段）与 P2（模板→类型预设），P1（砍文本模板）在 P2 重构中自然完成（不再灌文本）。

### P0 合同方向 `direction`

- 新建合同 Step1 新增「合同方向」下拉（销售·我方收款 / 采购·我方付款）；留空时按对方身份自动推断：`supplier_id>0 → purchase`，`party_b_customer_id>0 → sales`，兜底 `sales`（`ContractController::save`）。
- `contract` 表新增 `direction`(默认 sales) / `flow_id`(默认 0) 两列；列表 `ContractLogic::getList` 支持 `direction` 过滤；前端列表加方向徽标列 `dirBadge()`（采购=黄「我方付款」/销售=绿「我方收款」）。
- 合同详情页基本信息新增「合同方向」行（徽标 + 建议审批流名）；财务中心按 `direction` 分组 SUM 生成「销售合同(应收)/采购合同(应付)」概览卡片。

### P2 模板重构为「合同类型预设」

- `contract_template` 表新增 `default_direction` / `default_flow_id` / `tips` 三列；种子预置 3 类：媒体投放服务合同(sales,flow1)、供应商采购合同(purchase,flow1)、年度框架协议(sales,flow2)。
- 新增 `TemplateController::getPreset`（权限 `contract:create|contract:edit|template:manage`）与路由 `GET template/:id/preset`，返回 `id/name/category/direction/flow_id/tips`。
- 新建合同「关联合同类型预设」改为 `onchange=applyTemplate(id)`：调 preset 接口带出 `category / direction / flow_id / tips` 并展示必填提醒，不再把范本文本灌入概要（P1 自然落地）。
- 模板编辑器由「区块正文编辑器」改为「预设表单」（默认分类/默认方向/建议审批流/提醒文本域）；列表卡片预览显示方向与提醒。
- 提交审批页 `flow_id` 按合同 `flow_id` 预选并提示「已按合同类型预设的建议审批流预选」。

### 验收（动态）

- 脚本 `/tmp/verify_v215.sh`：登录(admin/password，临时关 force_reset) → 12 项断言。**结论：12/12 全绿，无回归。**
  - P0-1 显式采购→purchase；P0-2 客户→sales；P0-3 供应商→purchase；P0-4 方向过滤无杂质；P0-5 财务页含收支概览。
  - P2-1 预设接口返回 direction=Sales/flow_id=1/category=SERVICE；P2-2 套用预设保存 direction=Sales\&flow_id=1；P2-3 模板保存预设并持久化 default_direction=purchase。

## v2.14 (2026-07-14) — 落地 v2.13 审查建议（菜单权限隐藏 + 列表排序白名单）

### 审查建议落地

- **菜单按权限隐藏（建议1）**：`app/view/layout/sidebar.php` 依据 `user_permissions`/`is_admin` 包裹各功能入口；无权限角色自动隐藏对应菜单（如普通员工看不到「审计中心 / 系统设置」），与后端 `requirePermission` 守卫一致，纵深防御。
- **列表排序参数白名单（建议2）**：新增 `BaseController::getSortParams()`，对排序字段名（前端 key→数据库表达式映射）与方向（仅 asc/desc）做白名单校验，非法字段名/方向安全回退默认值；应用于合同、归档、审批(待办/已办/我提交)、客户、公海、供应商、审计、财务(回款/发票) 共 12 个分页列表接口。
- **前端联动**：合同列表表头支持点击排序（`data-sort` + 后端白名单生效）。

### 验收（动态）

- 菜单权限：普通员工登录后页面不含「审计中心 / 系统设置」；管理员含全部菜单。
- 排序白名单：asc/desc 真实生效（id 首末顺序反转）；注入字段名 `id;DROP TABLE contract--` 被白名单拦截（返回 200，无 500）；非法方向 `DROP TABLE` 回退默认 desc；多列表带 sort 参数返回 200。
- 回归：v2.13 核心安全机制（Auth/CSRF/RBAC/IDOR/XSS/数据范围）未被破坏；4 类角色仪表盘均可访问（200）。**结论：23/23 全绿，无回归。**

## P2 验收闭环 (2026-07-14) — P2 批次（R2/R3/R4/R10/R13/R14/R17/R19）端到端验证

> 版本保持 v2.14（P2 功能代码已于 v2.7 落地，本轮为纯验收闭环，无代码变更）。

### 验收结论

- 对 P2 八项优化建议做端到端回归验证（HTTP 冒烟 + 会签引擎 CLI 端到端），**27/27 全绿，无回归**。
- R2 会签（AND）：flow2 节点2 一人通过实例保持 PENDING，两人通过变 APPROVED，逻辑正确。
- R3 审计中心：有权用户 200 + 列表 code=0；无权限用户页面与 AJAX 均 403。
- R4 模板下放 / R10 设置独立路由（6 条均 200）/ R13 引导 / R14 loading 钩子均在。
- R17 `contract_field_value` 死表已移除；R19 数据范围 SELF(1) < ALL(7) 隔离生效。
- 任务 #17（P2 批次）标记 completed；PM 审查报告 R1–R19 至此全部落地验收。
- 验证脚本：`/tmp/verify_p2.sh`、`/tmp/p2_countersign.php`；报告：`P2_VERIFICATION.md`。

## v2.13 (2026-07-13) — 产品审查交付流程（安全审查 + 安全加固）

### 安全审查结论

- 全量代码审查：Auth 双通道(JWT+Session)、CSRF 双提交 Cookie+hash_equals、强制改密服务端守卫、RBAC 数据范围(SELF/DEPT/ALL) 隔离、审批操作按 `approver_id` 强校验等核心安全机制**均正确有效**。
- 全流程回归 **45/45**、越权安全 **12/12** 全绿，无回归。

### 修复项

- **存储型 XSS（合同详情页回款/发票列表）**：`description`/`payment_method`/`invoice_title`/`tax_no`/`invoice_no` 改为经 `esc()`（textContent）转义后渲染，杜绝脚本注入影响查看合同的高权限用户。
- **`is_admin` 提权纵深防御**：`AdminController::saveUser` 仅超级管理员可改 `is_admin`，防止 `system:user` 权限外溢导致越权提权。

### 审查建议（已记录，后续可择机处理）

- 菜单尚未按权限完全隐藏（差异主要体现在数据范围与接口层）；如需更强隔离可加菜单级权限渲染。
- 列表搜索/排序等少量接口建议补充参数化与边界校验（当前均走查询构造器，无注入风险）。

## v2.12 (2026-07-13) — 演示账号权限对比 + 用户/角色接口 CSRF 一致性

### 演示账号与权限差异对比（A）

| 项            | 说明                                                                                                                                      | 验证                                                                                   |
| ------------ | --------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------ |
| 内置演示账号       | 种子新增 3 个可登录演示账号，密码均为 `password`：`manager01` 张经理（部门经理/DEPT）、`employee01` 李员工（普通用户/SELF）、`finance01` 王财务（财务/SELF）；外加原有 `admin` 超级管理员（ALL） | 4 个账号均可登录                                                                            |
| 演示合同直观体现数据范围 | 种子新增 3 条 `dept_id=1` 演示合同，分别归属员工/财务/经理，使差异一眼可见                                                                                          | 实测：admin 可见 7 条（全部）；manager01 可见 3 条（本部门）；employee01 可见 1 条（自己）；finance01 可见 1 条（自己） |
| 数据范围机制       | Auth 中间件按 `user_role→role.data_scope` 在查询时行级过滤：ALL=不过滤、DEPT=`dept_id` 相等、SELF=`owner_id` 相等；多角色取最高范围；`is_admin` 直通全部                    | 见上实测；非管理员调用 `/ajax/admin/user/save` 返回 403（权限守卫）                                     |

### 用户/角色接口 CSRF 一致性（B）

| 项                                          | 说明                                                                                                                                                                                | 验证                                                   |
| ------------------------------------------ | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------- |
| saveUser/saveRole/delUser/delRole 改用 $ajax | 后台「用户管理 / 角色管理」的保存与删除原用裸 `fetch`（仅 `X-Requested-With`），依赖全局 fetch 包装器自动补 CSRF；现统一改为全局 `$ajax`（与 v2.10 `saveDDConfig`、v2.11 `saveFlow` 一致），调用处改为 `.then(res=>{...}).catch(()=>{})` | 带 token 保存返回 code:0「保存成功」；不带 token 返回 403「CSRF 校验失败」 |

## v2.11 (2026-07-13) — 审批流程可新建 + 预设可删除 + 审批详情修复

### 审批流程新建 / 删除

| 项                          | 说明                                                                                                                              | 验证                                          |
| -------------------------- | ------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------- |
| 审批流程「新建」按钮                 | 管理后台「系统管理 → 审批流程」标签页新增「新建流程」按钮，打开编辑器可配置名称/编码/分类/金额区间/多节点（部门负责人/指定用户/角色，支持会签 AND 与或签 OR）                                         | 点击按钮弹出编辑器；保存后新流程出现在列表                       |
| 修复新建/删除流程被 CSRF 拦截（影响所有用户） | 原 `saveFlow`/`delFlow` 用裸 `fetch` 未带 `X-CSRF-TOKEN`，被 CSRF 中间件 403；改为走全局 `$ajax`（自动附加 CSRF Token，与 v2.10 修复 `saveDDConfig` 同方案） | 新建/删除流程返回「保存成功 / 已删除」（code:0）；缺 token 时 403 |
| 预设流程可删除                    | 删除按钮由「停用」升级为真正从列表移除：列表仅取 `status=1`，被删流程不再显示、不再参与新合同审批匹配                                                                        | 删除预设后列表不含该流程；匹配接口不再返回它                      |
| 删除采用软删除（保留关联）              | 后端 `deleteFlow` 置 `status=0` 而非物理删除，保留历史/进行中审批实例对流程数据的关联                                                                        | 流程被删后，已发起实例仍可查看详情并审批（节点快照不依赖流程行状态）          |

### 修复审批详情页 500（预存 bug）

| 项      | 说明                                                                                                         | 验证                                              |
| ------ | ---------------------------------------------------------------------------------------------------------- | ----------------------------------------------- |
| 节点序号渲染 | 详情页按 `$node['order']` 取序号，但预设流程 `nodes` 不含 `order` 字段，查看任一预设流程发起的审批实例均报 `Undefined array key "order"`（500） | 改为按节点下标 `$i+1` 渲染；用预设流程（大额审批/简易审批）发起的实例详情页均 200 |

## v2.10 (2026-07-13) — 钉钉审批消息通知 + 点击进入审批闭环

### 钉钉审批通知闭环

| 项                 | 说明                                                                                                       | 验证                                        |
| ----------------- | -------------------------------------------------------------------------------------------------------- | ----------------------------------------- |
| 审批消息通知            | 审批提交/通过/驳回/转交时向审批人(及提交人)推送钉钉工作通知                                                                         | Mock 日志确认通知已发出，文案含合同编号/标题/金额              |
| 收件人映射修复（关键 bug）   | 原 `sendWorkNotice` 误传本地 `user.id`；新增 `sendToLocalUsers`：真实模式映射为钉钉 `userid` 并跳过未绑定用户（记录日志），Mock 模式保持本地 ID | 真实模式不再发错人；未绑定用户被跳过且有日志                    |
| 消息深链              | 通知文案附可点击链接 `{APP_URL}/dingtalk/entry?to=/approval/{实例ID}`，点击进入系统审批                                       | Mock 日志确认链接已生成（含 entry 路径与编码后的目标）         |
| 钉钉免登入口页           | 新增 `GET /dingtalk/entry`：已登录直跳；钉钉内 `requestAuthCode` 免登后跳转；浏览器内走 `/login?redirect=` 透传深链                 | 已登录 302→/approval/{id}；未登录渲染免登引导页（不误跳登录页） |
| 钉钉配置落盘            | 后台「钉钉应用配置」新增 `APP_URL`；`saveDDConfig` 改为真实写入 `.env`（AppKey/Secret/CorpId/AgentId/APP_URL/Mock）           | 保存后 `.env` 的 `DINGTALK_APP_URL` 等被正确写入    |
| 修复审批 CSRF（影响所有用户） | 审批详情页「同意/驳回」裸 `fetch` 缺 `X-CSRF-TOKEN` 被拦截；改为读取 cookie 的 csrf_token 并带上                                  | 审批 `act()` 返回「操作成功」（code:0），不再 403        |

### 产品视角验收

| 项      | 说明                           | 结果                        |
| ------ | ---------------------------- | ------------------------- |
| 审批通知闭环 | 提交→钉钉消息→点击→免登→直达审批→处理 全链路可走通 | 通过（Mock 模式验证链路，真实钉钉需配置凭据） |
| 配置可用性  | 钉钉配置表单从「仅弹窗」升级为真实落盘          | 通过                        |
| 版本一致性  | 侧栏版本号 v2.9→v2.10             | 通过                        |

## v2.9 (2026-07-13) — 全量代码审查与安全加固（IDOR 全面加固 + 强制改密服务端守卫）

### 全量代码审查（反复全量检查与测试）

| 项              | 说明                                                                                                                        | 验证                                                             |
| -------------- | ------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------- |
| 越权访问(IDOR)全面加固 | 审查 11 个控制器 + 2 个逻辑类，补充行级数据权限校验：列表/搜索 `appendDataScope`，单条读 `ContractLogic::accessible`，写操作 `canAccessRecord`/`accessible` | 越权安全测试 12/12 全绿；SELF 用户即便临时获 create/manage 权限仍被数据范围拦截，自身数据正常   |
| 强制改密服务端守卫      | `Auth` 中间件新增 `checkForceReset()`：`force_reset=1` 用户除改密页/改密接口外，普通请求重定向、AJAX/POST 返回 JSON `code:430`（Session 与 JWT 双通道均生效）  | 未改密 admin 访问任意页/AJAX 均被拦截导向改密；改密后正常                            |
| 修复强制改密循环重定向    | `AdminController::changePassword` 改密后同步刷新会话 `force_reset=0`，避免中间件仍判定 force_reset=1 无限跳转改密页                                | 改密前 /dashboard→302 改密页、AJAX→430；改密后 /dashboard→200、AJAX 正常，无循环 |
| 测试脚本自包含化       | 全量回归脚本适配强制改密守卫（登录后先改密）并自创建 fixture 用户(selfu/tester)，可重复运行                                                                 | 全量回归 45/45 全绿                                                  |

### 产品视角验收

| 项      | 说明                                                                       | 结果  |
| ------ | ------------------------------------------------------------------------ | --- |
| 向导提交闭环 | 创建向导四步 → 提交 → 详情页渲染标题（创建→详情可达）                                           | 通过  |
| 页面可达性  | 22 个导航路由 admin 视角全部 200                                                  | 通过  |
| 角色落地页  | 各角色 /dashboard 正常渲染：admin(force_reset)→改密页、SELF(selfu)→200、改密后 admin→200 | 通过  |
| 响应式    | viewport meta + 侧栏折叠 + 移动端底部导航(<767px)                                   | 通过  |
| 权限前置控制 | 侧栏按 `can_create_*`/`is_admin`/`can_view_audit` 显隐菜单与按钮                   | 通过  |
| 错误提示友好 | 中文具体提示 + 前端非 JSON 响应 toast 兜底                                            | 通过  |
| 版本号一致性 | 修复侧栏 stale `v2.6` → `v2.9`                                               | 已修复 |

## v2.8 (2026-07-13) — P1 遗留项 R7 + R18（创建向导 + 安全加固）

### R7 — 合同创建向导化 / 分区

| 项    | 说明                                                               | 验证                                   |
| ---- | ---------------------------------------------------------------- | ------------------------------------ |
| 四步向导 | 合同创建/编辑页拆为「基础信息 → 对方信息 → 条款金额 → 附件」，顶部步骤进度条 + 上一步/下一步导航 + 分步必填校验 | /contract/create 200；向导提交创建成功（id=19） |
| 后端兼容 | `contract/save` 接口不变，向导仅前端分步收集后一次性提交                             | 兼容性确认通过                              |

### R18 — 安全加固

| 项       | 说明                                                                                                                            | 验证                                                            |
| ------- | ----------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------- |
| CSRF 防护 | 新增 `app/middleware/Csrf.php`（double-submit cookie），写操作校验 `X-CSRF-TOKEN`；`app.js` 全局 monkey-patch `fetch` 自动携带 token；钉钉回调白名单放行 | 缺/错 token 写请求 403；正确 token 放行；登录/改密/用户保存均验证                   |
| 弱口令治理   | 新建用户默认 `123456` 改为必填且 ≥8 位；种子 admin 置 `force_reset=1`，首次登录强制改密（`/profile/change-password`）                                    | 空/弱密码被拒；admin 首登返回 force_reset=true 并跳转改密页；改密后 force_reset 清零 |
| 弱密钥治理   | 新增 `database/generate_secrets.php` 生成随机 `APP_KEY`/`JWT_SECRET`；JWT 派生回退 md5 → `hash_hmac('sha256', ...)`                      | 脚本 lint 通过；派生逻辑已加固                                            |

### 安全补丁（再次全面测试运行发现并修复）

- **越权搜索修复**：`ContractController::search` 原直接 `Db::name('contract')` 查询未附加行级数据权限，导致 SELF 数据权限用户可越权检索他人合同。已补 `AuthLogic::appendDataScope($query, 'owner_id', 'dept_id')`，SELF 仅见本人 / DEPT 仅见本部门 / ALL 全部。
- 全面测试套件（7 大类 32 项）全绿，含本修复的回归验证；另修正 2 处测试脚本误判（B1 缺 AJAX 头、D 块 pending-list 字段名）。

## v2.7 (2026-07-13) — PM审查排期开发 P2（8项规划优化）

### R2 — 审批会签

| 项       | 说明                                                                             | 验证                                                                         |
| ------- | ------------------------------------------------------------------------------ | -------------------------------------------------------------------------- |
| 会签/或签模式 | `ApprovalLogic::action()` APPROVED 分支读取节点 `mode`：OR 任一通过即推进，AND 需本节点全部审批人通过才推进 | flow 2 节点2(AND, approvers:[1,2])：uid=1 同意后实例仍 PENDING，uid=2 同意后实例 APPROVED |
| 流程配置    | flow 2（大额审批）节点改为 `[直属审批 OR, 会签审批 AND]`                                         | 开发库 nodes 已同步                                                              |

### R3 — 审计中心

| 项    | 说明                                                                           | 验证                                  |
| ---- | ---------------------------------------------------------------------------- | ----------------------------------- |
| 审计中心 | 新增权限 `audit:view`(perm 30)，admin/manager 授权；`/audit` 页面 + `/ajax/audit/list` | /audit 200；列表 JSON 200；无权限 403/JSON |
| 审计服务 | `AuditService::getList` 接入，AdminController 移除未用 import                       | lint 通过                             |

### R4 — 模板能力下放

| 项      | 说明                       | 验证                           |
| ------ | ------------------------ | ---------------------------- |
| 关联模板启用 | 合同创建页「关联模板」选择器启用，按模板预填内容 | 选择器渲染，loadTemplateContent 生效 |

### R10 — 系统设置拆独立路由

| 项    | 说明                                                                                                     | 验证                              |
| ---- | ------------------------------------------------------------------------------------------------------ | ------------------------------- |
| 路由拆分 | `/admin` 拆 `/admin/user`、`/admin/role`、`/admin/flow`、`/admin/template`、`/admin/dict`、`/admin/dingtalk` | 6 条路由均 200；legacy `?tab=` 仍 200 |
| 视图修正 | `index()` 显式 `View::fetch('index')`，避免薄方法解析到不存在的 admin/user.php 报 500                                  | 修复后正常                           |

### R13 — 新手引导 + 空状态行动点

| 项      | 说明                                                                           | 验证               |
| ------ | ---------------------------------------------------------------------------- | ---------------- |
| 空状态行动点 | `emptyState()` 带新建 CTA，按 `canCreate` 显隐；合同/客户列表接入                            | 无数据时显示新建按钮（有权限时） |
| 新手引导   | 首次访问 `/dashboard` 弹 `showGuide()`，localStorage `cm_guide_seen`；侧栏「🧭 新手引导」入口 | 首次访问弹窗，二次不弹      |

### R14 — 长任务 loading / 进度

| 项          | 说明                                                                             | 验证            |
| ---------- | ------------------------------------------------------------------------------ | ------------- |
| 全局 loading | `showLoading()/hideLoading()` 固定遮罩 `#globalLoading`，接入 `$ajax`（`loading` 选项可控） | 请求期间遮罩显示，结束隐藏 |

### R17 — 清理死表 / 统一字段模型

| 项    | 说明                                           | 验证                       |
| ---- | -------------------------------------------- | ------------------------ |
| 删除死表 | 移除无读写引用的 `contract_field_value` 表 DDL + 唯一索引 | grep 确认 app/ 无引用；lint 通过 |

### R19 — 明确 data_scope 与协作边界

| 项      | 说明                                                                                                                | 验证                                          |
| ------ | ----------------------------------------------------------------------------------------------------------------- | ------------------------------------------- |
| 数据权限统一 | `SupplierController`/`FinanceController`/`DashboardController` 接入 `AuthLogic::appendDataScope()`，尊重 SELF/DEPT/ALL | admin 见全部；SELF 用户供应商列表为空、回款为 0（修复供应商越权查询漏洞） |

### 测试运行修复（v2.7 交付前回归）

| 项                   | 说明                                                                                                                                                            | 验证                                                                                                                         |
| ------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------- |
| DEPT_LEADER 解析兜底    | `ApprovalLogic::resolveApprovers` 的 DEPT_LEADER 分支：提交人 `dept_id=0` 或无本部门主管时原逻辑返回空数组，导致标准审批(flow 1)提交后**无审批记录、实例卡死、审批动作报"操作失败"**。现改为回退超级管理员(is_admin=1)审批，杜绝卡死 | 修复前 flow 1 提交后 0 条记录、action 失败；修复后 admin 同意→APPROVED、驳回→REJECTED 均正常                                                       |
| 编辑合同 updater_id 列缺失 | `ContractController::save()` 编辑分支写入 `updater_id`，但 `contract` 表无该列，导致编辑合同报 `fields not exists:[updater_id]`、编辑功能完全不可用（P0 遗留，首次全面测试暴露）                         | 为 `contract` 表新增 `updater_id INTEGER NOT NULL DEFAULT 0`（init DDL 同步 + 开发库 ALTER）；修复后编辑成功并记录修改人，软删除验证 `is_deleted=1` 列表已过滤 |

### 涉及文件

| 文件                                                                                              | 变更                                                                                  |
| ----------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------- |
| `app/common/logic/ApprovalLogic.php`                                                            | action() 支持 mode 会签/或签；resolveApprovers DEPT_LEADER 回退超级管理员                         |
| `database/init_sqlite.php`                                                                      | flow 2 节点含 AND 会签；perm 30 审计查看；删除 contract_field_value 死表；contract 表新增 updater_id 列 |
| `app/controller/AuditController.php` + `app/view/audit/index.php` + `public/static/js/audit.js` | 审计中心                                                                                |
| `app/BaseController.php`                                                                        | 注入 can_view_audit                                                                   |
| `app/view/contract/create.php`                                                                  | 启用关联模板选择器                                                                           |
| `app/controller/AdminController.php`                                                            | 拆 6 个薄方法 + fetch('index') 修正                                                        |
| `route/app.php`                                                                                 | 新增 /admin/* 与 /audit 路由                                                             |
| `app/view/layout/sidebar.php`                                                                   | 审计中心入口 + admin 子菜单直链 + 新手引导入口                                                       |
| `public/static/js/app.js`                                                                       | emptyState/showGuide/showLoading + $ajax loading                                    |
| `public/static/js/contract.js` / `public/static/js/customer.js`                                 | 空状态行动点                                                                              |
| `app/controller/SupplierController.php` / `FinanceController.php` / `DashboardController.php`   | appendDataScope 数据权限                                                                |

## v2.6 (2026-07-13) — PM审查排期开发 P0/P1

### P0 — 核心闭环

| 项          | 说明                                                                                 | 验证                                              |
| ---------- | ---------------------------------------------------------------------------------- | ----------------------------------------------- |
| 到期提醒引擎 MVP | RemindService 实时扫描（到期预警/已到期/逾期回款/即将到期回款），仅读不写；新增 `/remind` 提醒页 + 仪表盘「今日提醒」卡 + 侧栏角标 | /remind 200；/dashboard 含「今日提醒」                  |
| 导航补入口      | 侧栏新增「提醒」角标入口 + 财务中心子菜单（回款/发票 `/finance?tab=...`）；审批红点 badge                        | /finance 200；sidebar 渲染角标                       |
| 归档导出按状态过滤  | `exportExcel()` 支持 `status` 参数，归档导出文件名 `archived_contracts_YYYYMMDD.csv`           | `/ajax/export/contracts?status=ARCHIVED` 仅返回已归档 |
| 前端错误兜底     | `$ajax` 非 JSON 响应按 HTTP 状态 toast（403/404/500）+ 网络错误提示，支持 `silent`                  | 非 JSON 500 不再静默失败                               |

### P1 — 体验与健壮性

| 项           | 说明                                                                                                                             | 验证                        |
| ----------- | ------------------------------------------------------------------------------------------------------------------------------ | ------------------------- |
| 金额上限校验      | `PaymentController::add` / `InvoiceController::add` 校验累计不超过合同金额                                                                | 超额回款/发票均拒并提示「已登记 ¥X」；小额成功 |
| 权限前置灰化      | 无 `contract:create` 角色隐藏合同/客户「新建」按钮；无 `payment:view`/`invoice:view` 隐藏财务中心；BaseController 注入 `can_create_*`/`approval_pending` | legal/finance 角色验证隐藏生效    |
| 改密 Modal    | `changePassword()` 改用 Bootstrap Modal 替代 `prompt`，`submitPasswordChange()` 反馈                                                  | Modal 弹出 + toast 反馈       |
| 全局 toast 统一 | `detail.php` / `contract.js` 原生 `alert` 改 `showToast`                                                                          | 操作反馈统一                    |
| 草稿可删除       | 合同详情页删除按钮：草稿状态可删，其他状态 disabled + tooltip                                                                                       | 草稿可删，其他禁用                 |

### 验证方式

- 内置服务器冒烟测试（admin 登录）：`/dashboard`、`/remind`、`/finance`、两个 AJAX 列表均 200；CSV 导出按状态过滤；金额超额双向拦截；未登录访问 `/finance` 302 跳转 `/login`。
- PHP 语法检查：全部改动文件 0 错误。

### 涉及文件

| 文件                                       | 变更                                                          |
| ---------------------------------------- | ----------------------------------------------------------- |
| `app/common/service/RemindService.php`   | 新增 getTodayAlerts / getOutstandingCount / scopeOwner / scan |
| `app/controller/RemindController.php`    | 新增 index() 提醒页；check() 调用 RemindService                     |
| `app/controller/DashboardController.php` | 触发提醒扫描 + 注入 remind_alerts                                   |
| `app/controller/FinanceController.php`   | 新建：财务中心 + paymentList/invoiceList（按权限+数据范围）                 |
| `app/controller/ContractController.php`  | exportExcel 增加 status 过滤                                    |
| `app/controller/PaymentController.php`   | add() 金额上限校验                                                |
| `app/controller/InvoiceController.php`   | add() 金额上限校验 + 合同存在校验                                       |
| `app/BaseController.php`                 | 注入 can_create\_* / approval_pending / remind_count          |
| `app/view/remind/index.php`              | 新建提醒页                                                       |
| `app/view/finance/index.php`             | 新建财务中心页                                                     |
| `app/view/dashboard/index.php`           | 今日提醒卡                                                       |
| `app/view/layout/sidebar.php`            | 提醒入口角标 + 财务中心子菜单 + 权限灰化 + 版本号                               |
| `app/view/contract/detail.php`           | toast 统一 + 草稿删除按钮                                           |
| `public/static/js/app.js`                | 错误兜底 + 改密 Modal                                             |
| `public/static/js/contract.js`           | 创建提交 alert→toast                                            |
| `route/app.php`                          | /remind、/finance 及 AJAX 路由                                  |

---

## v2.5 (2026-07-10) — PM审查全面优化

### 代码审计

| 指标       | 结果                                             |
| -------- | ---------------------------------------------- |
| PHP 语法检查 | 70 文件 / 0 错误                                   |
| JS 语法检查  | 5 文件 / 0 错误                                    |
| 路由-控制器匹配 | 83 路由 / 100%                                   |
| 安全审计     | 0 Critical / 0 High / XSS onclick 整数 ID 10处低风险 |

### 新功能

| 功能      | 说明                                             |
| ------- | ---------------------------------------------- |
| 合同附件上传  | 拖拽上传，PDF/Word/Excel/图片/TXT，单个≤20MB，详情页带图标展示+下载 |
| 附件必填    | 前后端双重校验，创建合同时必须上传至少一个附件                        |
| 发票管理 UI | 合同详情页新增发票记录卡片，支持申请开票（专票/普票/电子票）                |
| 框架合同体系  | 列表页关联列（框架标签/子合同归属）+ 详情页关联信息 + 筛选器              |
| 导出时段筛选  | 列表页开始/结束日期输入框，导出时携带日期参数，CSV 带 UTF-8 BOM        |

### 交互优化

| 变更          | 说明                                           |
| ----------- | -------------------------------------------- |
| 合同正文→合同概要   | 必填 textarea，输入核心条款摘要                         |
| 父合同→关联框架合同  | 搜索型输入框 + ?悬浮提示 + 键盘导航                        |
| 乙方选择器       | 支持搜索客户+供应商，自动识别类型                            |
| 仪表盘 KPI 卡片  | 4 个卡片可点击跳转对应页面                               |
| 侧边栏子菜单      | 脱离 Bootstrap collapse，纯 CSS toggle，点击导航后保持展开 |
| 客户等级        | 全部改用 dict('customer_level') 中文显示             |
| 空状态文案       | 统一为具体说明（"暂无已归档合同"/"暂无审批记录"等）                 |
| 合同列表 hover  | 最近合同表格行 hover 蓝色高亮                           |
| 关联列 tooltip | "框架合同（N个订单）"/"归属于框架合同"/"独立合同"                |
| 供应商搜索       | 新增关键词+类型筛选表单                                 |
| 移动端导航       | 底部新增"归档"按钮（6项）                               |
| 归档页         | 新增导出按钮                                       |
| 模板关联        | 暂时隐藏，后续可恢复                                   |

### PM 审查修复

| 问题          | 修复                                |
| ----------- | --------------------------------- |
| 审批流程无节点数据   | approval_flow 种子数据写入节点 + 实时 DB 更新 |
| 发票功能无 UI 入口 | 合同详情页新增发票卡片 + Modal + 路由注册        |
| 种子数据无内容/附件  | 4 份合同填充概要 + 附件 + 回款记录             |

### 涉及文件

| 文件                                      | 变更                                           |
| --------------------------------------- | -------------------------------------------- |
| `app/controller/ContractController.php` | upload/exportExcel/save/partySearch/index 增强 |
| `app/controller/SupplierController.php` | index 增加 keyword 过滤                          |
| `app/controller/TemplateController.php` | getContent AJAX 接口                           |
| `app/controller/CustomerController.php` | levels 字典注入                                  |
| `app/common/logic/ContractLogic.php`    | getList/getDetail 增加框架合同字段                   |
| `route/app.php`                         | 上传/发票/模板路由                                   |
| `app/view/contract/create.php`          | 概要+附件+父合同搜索+乙方供应商+sender_id                  |
| `app/view/contract/detail.php`          | 7大区块重建：概要+附件+框架+回款+发票+时间线+审批                 |
| `app/view/contract/index.php`           | 框架筛选+日期筛选+关联列+导出JS                           |
| `app/view/dashboard/index.php`          | KPI可点击+hover高亮                               |
| `app/view/layout/header.php`            | 搜索面板CSS+sidebar-sub样式                        |
| `app/view/layout/footer.php`            | 移动端+归档                                       |
| `app/view/layout/sidebar.php`           | collapse→sidebar-sub + 版本号                   |
| `app/view/archive/index.php`            | 导出按钮+空状态文案                                   |
| `app/view/approval/detail.php`          | 空状态文案                                        |
| `app/view/customer/detail.php`          | 等级字典中文                                       |
| `app/view/supplier/index.php`           | 搜索表单                                         |
| `public/static/js/contract.js`          | 框架列+导出函数+supplier_id+escH                    |
| `public/static/js/customer.js`          | 等级字典                                         |
| `public/uploads/contracts/`             | 按月分目录存储                                      |
| `database/init_sqlite.php`              | 合同种子+审批节点+权限汉化                               |

---

## v2.4 (2026-07-10) — 系统设置页修复 + 侧边栏加固

### 紧急修复

| 问题                    | 严重程度     | 修复                        |
| --------------------- | -------- | ------------------------- |
| 系统设置页缺少 footer.php 引入 | CRITICAL | app.js/Bootstrap JS 完全未加载 |
| 共享 JS 被条件分支吞没         | HIGH     | 函数仅在 dingtalk tab 定义      |
| Auth except 控制器匹配失效   | HIGH     | 改用 pathinfo() URL 前缀匹配    |
| 审批流程 tab 缺外层 div 包装   | MEDIUM   | 补齐 card stat-card wrapper |

### 安全加固

- PHP 54 文件 / JS 5 文件全量通过
- 权限分组汉化（6 组）+ 种子数据同步
- XSS onclick 出口 htmlspecialchars 转义
- PHP 8.4 兼容

---

## v2.3 (2026-07-10) — 安全加固 + 全文汉化

- Auth 中间件全局注册（JWT+Session 双通道）
- XSS 修复、甲乙方搜索选择器、本公司快捷
- 全文汉化、字典行内编辑、移动端菜单修复

## v2.2 (2026-07-10) — 广告公司适配

- 供应商管理、回款/开票、到期提醒、框架合同
- 仪表盘增强、操作时间线、系统字典

## v2.1 (2026-07-09) — 初始版本

- 合同 CRUD + 10 状态机、客户管理+公海池
- 审批引擎、合同模板、签署、归档、钉钉集成
