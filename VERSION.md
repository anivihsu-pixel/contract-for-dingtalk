# 合同管理系统 — 版本说明

## 当前版本：v2.47.1（2026-08-11）

### v2.47.1 发布内容（PATCH · 经营统计口径对齐：trend 趋势 + 回款口径排除非生效合同 + 框架合同）
- **三处口径偏差修复**：经营统计已建立的统一口径为「交易合同 `trade_attr=1` + 排除非生效状态（DRAFT/REJECTED/PENDING_APPROVAL）+ 排除框架合同预算上限」，但 trend 近6季度趋势与项目/公司级回款口径未对齐，本次统一收口
- **trend 近6季度合同趋势**（`ReportLogic::dashboardSummary`）：合同额与同期已收两条序列此前仅过滤 `trade_attr=1` + `effective_date`/`actual_date` 区间，未排除非生效状态与框架合同——草稿/驳回/审批中合同金额被计入季度合同额，框架合同预算上限也被计入；现追加 `status not in [DRAFT,REJECTED,PENDING_APPROVAL]` + `exclude_framework_contracts`，与同方法 `dir` 收支方向口径完全对齐
- **公司级驾驶舱回款口径**（`ReportLogic::payScope`）：回款基础查询未限定合同状态，非生效合同关联的回款记录可能计入应收/已收/待收/逾期；现追加同口径状态过滤 + 排除框架合同
- **项目经营聚合付款侧**（`ProjectLogic::aggregate` payBase）：同一方法内合同额侧（dirQuery）已排除非生效状态 + 排除框架合同，但付款侧 payBase 未排除，导致项目经营页应收/已收/回款率与合同额/毛利口径不对齐；现追加同口径过滤，与 dirQuery 完全对齐
- **验证**：php -l 全绿；SQL 对照——构造 DRAFT 合同 #9（9.6 万）effective_date=2026-05-15 落入 26Q2，修复前 trend 26Q2=2,197,000，修复后=2,101,000，差异恰好 96,000 = DRAFT 合同金额；浏览器 E2E——驾驶舱趋势图 26Q2 显示 2,101,000（修复后值），未出现 2,197,000；演示库已恢复原状，临时脚本/备份已清理
- **补录 migration**：补上 v2.47.0 遗漏的 `database/migration_v2.47.0_weekly_report_config.sql`（`weekly_report_dd_enabled` 配置项），deploy.sh 自动执行（MySQL 幂等 `INSERT IGNORE`），存量库升级无需手动 SQL
- **无 DB 结构变更**

### v2.47.0 发布内容（MINOR · 总经理经营周报 + 双端入口 + 权限收紧）
- **经营周报聚合与页面**：新增 `WeeklyReportLogic`（口径与驾驶舱/月报一致——仅交易合同、排除草稿/驳回/审批中与框架合同）；上周新增合同（数量+金额）、上周实际回款（`actual_date`/`paid_amount` 实收口径）、当前逾期快照（金额+笔数）、待审批实例数；按部门聚合+明细；新增 PC `/report/weekly` 与移动 `/m/report/weekly` 落地页（4 指标卡 + 各部门经营 + 新增合同表 + 逾期合同卡，合同可点开详情，`?week=周一` 周切换回溯）
- **每周一自动推送**：命令 `report:weekly`（crontab `0 8 * * 1`）按 `role.code='gm'` 定位总经理；站内信始终发送（带摘要）+ 钉钉工作通知仅发极简提示（不携带摘要，省钉钉接口额度），`weekly_report_dd_enabled` 开关控制（系统配置页可切，默认开）；通知点击直达移动周报页
- **双端入口（方案 C）**：移动工作台「全公司经营」卡片头部「经营周报」胶囊按钮（仅 `dashboard:company` 可见）+ PC 侧边栏「财务中心 → 经营周报」二级菜单（仅 `dashboard:company` 角色可见）；不动经营卡片数据与渲染逻辑
- **权限收紧**：周报为全公司口径，页面权限由 `financialGate`（payment:view 全员默认，越权风险）收紧为 `requirePermission('dashboard:company')`，与「全公司经营」卡片同权限，仅总经理/超管可见
- **修复**：`bi-calendar-week` 图标不在项目 bootstrap-icons v2.43.2 中致侧边栏/页头/按钮图标空白——统一换为 `bi-calendar-check`（已入白名单）
- **验证**：php -l 全绿；浏览器 E2E——GM 正向 5 项（PC/移动周报页、工作台按钮、侧边栏菜单）全绿，普通用户负向 3 项（PC/移动 403、无按钮）全红，周切换回溯正常；钉钉 mock 确认极简提示；无 GM 用户命令优雅降级；测试数据已清理
- **DB 变更**：无（仅系统配置表新增 `weekly_report_dd_enabled` 一项，含于 init_sqlite/init_mysql 初始化）

### v2.46.0 发布内容（MINOR · 签约方强制关联档案 + 甲方供应商独立字段 + 我方身份切换修复）
- **签约方强制关联档案（仅新建生效，编辑旧数据不追溯）**：新建合同时对方侧（非我方侧）必须从已登记客户/供应商中选择，名称框常驻只读（PC）或隐藏字段只读（移动），搜索框始终可编辑可重新搜索；后端按 `our_side` 判定对方侧强制校验——对方侧客户/供应商 ID>0 且名称 trim 与档案一致，否则拦截「请选择已登记的甲方/乙方客户或供应商（未登记可点「快速新建」，勿手输名称）」「名称与所选档案不一致，请重新选择」；杜绝自由输入任意名称绕过 v2.45.0 客户查重/共享/集团治理
- **甲方供应商独立落字段**：新增 `contract.party_a_supplier_id`（甲方为供应商的采购合同不再误落乙方 `supplier_id`）；`PartyLogic::linkFieldOf` 改数组返回（客户双字段 / 供应商双字段）、`getContractIds` 双字段 whereOr；`ContractController::save` FK 校验数组、`detail`/`party360`（PC+移动）甲方往来摘要对称展示；`SupplierLogic::deleteBlockersMap` 双字段防误删
- **表单内快速新建客户/供应商**：搜索无匹配时渲染「快速新建」入口（PC modal / 移动底部弹层），复用 `/ajax/customer/save`、`/ajax/supplier/save`（自带查重 409 + 数据权限，新建归本人），成功回填该侧；共享体系天然覆盖——相对方搜索 `PartyLogic::searchParty/getPartyRows` 已有 `appendCustomerShare`（v2.45.0），FK 校验走 `canAccessCustomer`
- **我方身份切换修复（移动端实测 bug）**：我方=乙方填写对方甲方信息后切我方=甲方，原甲方信息会被错误默认填入——切换时清空两侧名称/搜索框/客户ID/供应商ID/关联锁定，再 `applyOurSide()` 给新我方侧带出签约主体名，新对方侧等待重新搜索选择；PC 端 `create.php` 同步修复
- **修复**：移动端 `selectParty` 供应商分支清空列表含 sidEl 自身（side=B 时 `f_supplier_id` 刚设置又被清 0，甲方供应商同理）——清空排除 sidEl；快速新建成功回调误读 `res.id` 得 undefined（接口返回 `{code:0,data:{id}}`）——改读 `res.data.id`；`our_side` 非交易合同也提交（后端依赖其判定对方侧）
- **验证**：php -l 全绿；移动端浏览器 E2E——切换清空（选甲方 cid=4 → 切我方=甲方 → 两侧清空 + 新我方带主体名 + 新对方为空）、供应商按侧回填（A→`party_a_supplier_id`=6 / B→`supplier_id`=6）、客户回填、搜索框选中后可重新搜索、空结果快速新建 + id 回填（cid=21）、后端强制校验 403（名称有 ID=0 → 拦截文案）、正向链路放行；PC E2E（控件齐全、切换修复、自由输入拦截、选择锁定回填、快速新建回填）前次已验证；测试数据（3 条 E2E 客户）已清理、临时脚本已删除
- **DB 变更**：执行 `database/migration_v2.46.0_party_a_supplier.sql`（MySQL 幂等 ALTER + 索引 `idx_contract_party_a_supplier`；SQLite 注释段）；三份 init（`init_mysql.php`/`init_sqlite.php`/`init.sql`）已同步字段与索引

### v2.45.1 发布内容（PATCH · 系统配置备份/恢复自选表 + 中文表名）
- **表中文名**：备份/恢复 UI 表名对应各表注释中文名（角色/权限/角色权限关系/用户角色关系/部门/本公司主体/审批流程/合同模板/资料库/系统配置，内置映射 `AdminLogic::CONFIG_TABLE_LABELS`，英文表名小字附注）
- **导出可自选表**：备份区「选择导出表」折叠块（默认全选 + 全选切换）；`exportConfigArray` 仅导出勾选表（空选择回退全量防误导出），meta.tables 记录实际导出表
- **恢复可自选表**：预览结果每表「恢复」勾选框（默认全选，表头全选/取消）；`configRestore` 按勾选过滤后再预览/提交，未勾选表保持现状不覆盖；部分恢复不涉及权限表时 `restorePreservesAdmin` 直接放行（无自锁风险）
- **配套修复**：`BaseController::getPost/getParam` 改用 `request()->post/param($key,$default)`，支持 ThinkPHP `name/type` 语法（`tables/a` 强制数组）——原 `$data[$key]` 索引方式不支持该语法导致勾选参数恒空；移动端草稿卡片钉钉灰底修复——`.m-card.is-draft` 移除 `color-mix` 依赖（钉钉旧 WebView 不支持致 background 失效），改设计规范琥珀纯色 #fff4e5 底 + #d4860b 竖条（与草稿徽标同色系）
- **验证**：php -l 全绿；PHPUnit 65 tests / 172 assertions 全绿；浏览器 E2E——导出勾选 UI 默认全选、子集导出 JSON 仅含勾选表、预览中文表名 + 每表勾选、UI 取消勾选 2 表提交后未勾选表数据原值不变
- **无 DB 变更**

### v2.44.4 发布内容（PATCH · 附件上传体验修复 + 移动端 Excel 直传）
- **PC 新建合同：拖拽上传后 dropzone 高亮不复位修复**（附件双端全体验 QA 实测发现）：上传成功/失败后背景色永久停留蓝色高亮——`contract/create.php` `uploadFile` 完成/失败分支补背景复位
- **移动端合同附件：文档入口支持 Excel 直传**（用户需求）：`#fileDocInput` accept 扩为 PDF/Word/Excel（`.xls,.xlsx` + Excel MIME），「上传文档」入口文案同步 `PDF/Word/Excel`；JS 格式白名单本就含 xls/xlsx
- **PC 新建合同：参考资料库按钮缩小并移至合同概要区**（用户需求）：从「辅助信息」分组移除，改放 Step2「合同概要」分区标题行右侧右对齐小按钮（起草概要时参考范本随手可取）
- **合同列表：草稿置顶 + 浅琥珀底区分**（用户需求，方案 A）：`ContractLogic::getList()` 新增 `draft_first` 排序分支——PC 端默认视图草稿置顶（点列排序遵循所选列）、移动端始终草稿置顶，分页由同一排序查询切片延续；移动端草稿卡片 `is-draft` 浅琥珀底 + 左侧 3px 琥珀竖条，草稿徽标统一改琥珀（移动 `m-tag-warn` / PC `pc-tag-warn`，#fff4e5/#d4860b）
- **移动端新建/编辑表单：底部「创建合同」按钮遮挡输入字段**（用户反馈）：`.m-submitbar` 固定底栏被软键盘顶到键盘上方遮挡正在编辑的字段——输入控件聚焦即隐藏提交栏、失焦恢复（A→B 切换不闪烁），覆盖合同/客户/供应商三个移动表单（`mobile-common.js` 全局处理）
- **移动端新建/编辑表单：「创建合同」按钮收缩为居左窄块**（部署实测反馈）：`.m-submitbar` 缺 `display:flex` 致子按钮 `flex:1` 失效收缩（390px 实测 98px 居左）——补 `display:flex; gap:12px`，按钮撑满全宽蓝底白字
- **移动端草稿详情页徽标一致性**（全量 QA 回归发现）：`contract_detail.php` / `party_360.php` 本地状态色映射 DRAFT 仍灰（`m-tag-muted`）——统一改琥珀 `m-tag-warn`，与列表一致
- **验证**：php -l 全绿；浏览器 E2E——PC 1440 默认视图草稿置顶 + 琥珀徽标计算样式 #fff4e5/#d4860b、点「金额」列排序草稿不置顶；移动端 390 草稿置顶 + 浅琥珀底 + 3px 琥珀竖条 + 琥珀徽标、状态筛选（JS 路径）卡片同样带 `is-draft`；移动端 390 聚焦「合同标题」提交栏隐藏、失焦恢复、A→B 切换不闪烁；dropzone 上传高亮复位、xlsx 直传、参考资料库按钮三处此前修复回归正常
- **无 DB 变更**；纯生产包

### v2.44.3 发布内容（PATCH · 移动端预览修复 + PC 附件预览分离）
- **PC 附件预览分离**（部署环境反馈：图片预览时能切换到文档，跳出打开浏览器）：合同详情统一预览弹窗改为**图片画廊专用**——`imgList` 仅含图片附件，上一个/下一个/索引/键盘左右键只在图片间切换；文档附件点击直接 `openDocPreview()`（PDF/DOCX/XLSX → office-preview 新标签页；doc/xls 等不可内嵌格式 → 直接下载兜底），不再进入图片弹窗
- **移动端 Word 预览内容两侧遮挡**（部署环境反馈：预览内容两侧被遮挡）：docx-preview 以容器宽度排版，窄屏下 A4 内容被压缩变形且居中溢出左侧不可达——渲染前撑宽容器（≥850px）以桌面宽度排版，完成后固化每页宽度并恢复容器，页面保持原宽 + 横向滚动可达（与 PDF 预览交互一致）
- **移动端图片预览加载慢**（部署环境反馈：图片预览加载很慢）：`preview_token()` 窗口化——exp 对齐「下下个」TTL 窗口边界，窗口内签发令牌完全一致 → 预览 URL 稳定 → 浏览器缓存（max-age=3600）命中，不再每次全量重新下载；令牌仍为路径绑定 + HMAC 签名，最短有效期 ≥ ttl
- **验证**：php -l 全绿；浏览器 E2E——PC 混合附件（2 图+1 PDF）图片弹窗 1/2→2/2 零跳出、PDF 预览直接新标签页且弹窗未打开；移动端 390px docx section 330→790px 原宽横滚可达；两次进详情页 token 一致、同 URL 缓存命中（only-if-cached 200）；演示库已还原
- **无 DB 变更**；纯生产包

### v2.44.2 发布内容（PATCH · 积攒收口：项目管理终止 / 归档删除 / 移动端上传修复 / 生命周期对称 / PC 关键词弹层）
- **PC 合同新建：关键词推荐对齐移动端弹层**：关键词字段改为「只读展示区 + 点击弹层」（输入框 + 常用标签推荐 + 已选区），推荐不再平铺在输入框下方；交互与移动端同构（contract.js 关键词控件重写，/ajax/keyword/hot 常用标签缓存复用）
- **项目管理：终止/撤销终止**：`ProjectController::terminate()`（仅进行中 ACTIVE 且未完结项目可终止，联动终止项目下执行中/已通过/历史已签销售合同；存在逾期未结回款的合同跳过并返回清单不阻塞）与 `restore()`（TERMINATED→ACTIVE，合同状态不联动）；PC/移动端详情按钮按状态/权限渲染；`ProjectLogic::options()/search()` 排除 TERMINATED 项目；字典种子补 TERMINATED
- **归档/已完成/已到期/已终止合同可删除**（测试数据清理出口）：`ContractLogic::softDelete()` 可删状态扩为 DRAFT/REJECTED/ARCHIVED/COMPLETED/EXPIRED/TERMINATED（同步 batchDelete 与详情页删除按钮门控）；有回款/有效发票/进行中审批/子合同的合同仍被 `deleteBlockers` 拦截；删除→回收站→彻底清除链路复用
- **移动端合同附件上传修复**：上传成功回调漏删进度条目（8-05 renderUploads 改只重建成功项后暴露），残留「上传中… 99%」幽灵条目与成功项重复显示（文档/图片/拍照共用同一 uploadFile 全部复现）；成功回调补 `removeUploadItem(itemId)`
- **客户生命周期对称修复**：`ContractController::save()` 对 `party_a_customer_id`/`party_b_customer_id` 去重统一 `promoteToActive`（原仅乙方客户触发，PC 甲方选客户/移动端「我方=乙方」时客户不升成交）；PC 新建向导 Step2 增「我方身份」分段引导（与移动端 my|our 语义对齐，切换时 JS 带出签约主体名）
- **验证**：php -l 全绿；浏览器 E2E——移动端连续上传 2 文件 done=2/progress=0 无幽灵条目；生命周期（客户 12 预置 LEAD → 编辑草稿合同甲方选客户保存 → LEAD→ACTIVE）；归档合同删除（有回款+发票被拦截提示 → 清理关联后删除成功 → 项目统计立即减少 → 回收站可见可 purge）；项目终止→联动合同终止→撤销恢复；PC 关键词弹层（展示区/常用标签/添加/删除/值同步）；演示库均还原原状
- **无 DB 变更**；纯生产包（不含演示库/seed_demo/demo.env.example）

### v2.44.1 发布内容（PATCH · 安全批量修复：P0/P1/P2 全批次，依据 workbuddy 全面审查 contract_audit_full_v2.44.0.md）
- **P0-1 存储型 XSS**：合同/资料库上传文件名净化（移除 `<>"` 与控制字符，空名回退 `attachment.<ext>`）；21 个视图 `<script>` 内 `json_encode` 统一补 `JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT`；`PreviewController::resolveDisplayName` 净化 CRLF/控制字符/引号
- **P0-2 跨部门数据泄露**：`PartyLogic` 的 get360/getSummary/summarizeBatch 补数据范围过滤（360 详情联动回款/发票/动态均经范围过滤）
- **P1 认证/审批/合同/路由**：禁用/锁定用户实时吊销（JWT + Cookie 双通道，修复 Cookie 通道 status 缓存类型回归——所有已登录用户被误判禁用强制登出）；登录失败锁定键加 IP；钉钉 SSO 防重放 state；审批解析兜底/指定流程校验/驳回重建兜底；trade_attr 白名单保留旧值、归属字段不可改、外键 canAccessRecord、附件 URL 归属校验；`url_route_must=true` 写方法 GET 不可达；审计补齐；查询索引；安全 404 页
- **P2「建议现在做」批次（9 项）**：CSV/XLSX 公式注入中和（`= + - @` 前缀前置 `'`）；JSON_HEX 纵深补齐；contract 表 party_a_customer_id/supplier_id 索引（三份 init 脚本同步）；回收站彻底删除孤儿附件物理清理（引用检查 + realpath 边界）；导出 set_time_limit(0)；DB 默认口令置空（强制 .env 显式配置 DB_PASS）；cache_page 死配置清理；重复合同检测金额改字符串归一比对；里程碑合计校验评估不做（现有约束已正确）
- **验证**：php -l 全绿；phpunit 65 tests / 172 assertions 全绿；浏览器 E2E（导出 CSV 公式注入中和实测、回收站 purge 附件清理+共享引用保护实测、13 路由 200、写方法 GET 404、404 安全页）全部通过
- **部署提示（重要）**：
  - **v2.44.1 本身无 DB 变更**；但**存量库升级需执行 `database/migration_v2.43.6_library_perms.sql`**（资料库权限拆分 library:manage 33 → library:upload 44 / library:edit 45 / library:delete 46；deploy.sh 升级自动执行，幂等；不执行则 manager/gm 在 PC 资料库无上传/编辑/删除，查看/预览/下载正常）
  - **`.env` 必须显式配置 `DB_PASS`**（v2.44.1 起代码内不再内置默认口令 root/root，缺省空串连接失败）
  - P2 索引仅进初始化脚本，存量库不强制（不影响功能，仅查询性能）
- **出包**：纯生产包（无演示库/seed_demo/demo.env.example）

### v2.44.0 发布内容（MINOR · Word/Excel 在线预览 + 资料库权限拆分与移动端只读 + 附件全链路修复，积攒收口）
- **功能升级（Word/Excel 在线预览）**：新增通用预览页 `/m/office-preview?p=&t=&name=`（由 doc-preview 泛化）——pdf→PDF.js canvas（容器自适应 + DPR 高清）、docx→docx-preview 0.3.7 + JSZip 3.10.1、xlsx→SheetJS 0.20.3 多 sheet 分块渲染；三渲染库自托管 `public/static/vendor/office-preview/`（不依赖外部 CDN，钉钉内网可用）；Auth 中间件放行路径同步为 `m/office-preview`（旧 `/m/doc-preview` f 格式兼容）
- **资料库权限拆分**：`library:manage`（id=33）拆为 `library:upload`（44）/`library:edit`（45）/`library:delete`（46），角色权限配置页可逐角色勾选；迁移 `database/migration_v2.43.6_library_perms.sql`（存量库需执行，新部署由初始化脚本带出）
- **资料库收窄与移动端纯只读**：格式收窄七类（移除 gif/webp，与合同附件一致）；移动端资料库彻底移除上传/编辑入口与表单，仅保留阅读 + 开票资料一键复制（上传/编辑/删除仅 PC 端，受新权限码门控）
- **附件白名单扩容 xls/xlsx**：合同附件与资料库统一七类（pdf/doc/docx/xls/xlsx/jpg/png），PC/移动双端 accept 与提示同步
- **修复（第五~八轮）**：docx 下载变 preview.htm（`/preview` 返回业务原始文件名 + 移动端 doc/docx 改下载兜底不再 iframe 内嵌）；移动端 PDF 模糊（canvas 按 devicePixelRatio 渲染）；移动端资料库同步合同附件「下载 + 预览」（下载走带令牌 `/preview` 代理 + 业务名）；图片下载格式/预览没反应（移动端图片下载改新窗口打开大图、预览走令牌代理、PC 改 Modal 内嵌）；`Attachments.normalizeUrl()` 令牌点号判断缺陷（非空即拼 t）；资料库 OLE 回退缺失（`resolve_library_attachment_ext()` 补 x-ole-storage/vnd.ms-office 按扩展名回退 doc/xls，与合同附件口径一致，octet-stream 一律拒绝）；移动端资料库上传保存 403 与双同名 file 字段提交修复
- **验证**：PC 1280px + 移动 390px（钉钉 UA）七类全格式双端回归（资料库 + 合同附件）全部通过；office-preview 三渲染器实测渲染成功；curl 无 Cookie 令牌 200/302 闭环；php -l 全绿；phpunit 65 tests / 172 assertions 全绿；测试数据已清理
- **部署提示**：存量库需执行 `database/migration_v2.43.6_library_perms.sql`；包内新增静态资源 `public/static/vendor/office-preview/` 需随包部署；无其他数据库变更，可零停机部署

### v2.43.5 发布内容（PATCH · 附件预览/下载跳出钉钉免登录全链路 + PDF 移动端缩放修复）
- **用户报告（两轮）**：附件预览跳出钉钉（外部浏览器）提示账号密码登录；复测（已部署首版）仍出现移动端下载、PC 预览/手动打开跳外部浏览器停登录页，且移动端 PDF 左侧内容被遮挡
- **根因（5 层）**：① PDF 预览页 `/m/doc-preview` 本身需登录，令牌只在 `/preview` 流上；② `f` 嵌套参数（`?f=/preview?p=...&t=...`）在钉钉甩外部浏览器 URL 规范化/二次解码下被拆散，令牌丢失；③ 移动端下载走原始 `/uploads` 静态路径无令牌；④ `AuthLogic` fallback 密钥 `runtime_path('jwt_secret.txt')` 被当目录名（尾随分隔符）持久化失败、每次请求随机密钥；⑤ PDF.js 固定 scale=1.5 + 容器居中溢出导致移动端 PDF 左侧不可达
- **修复（5 处）**：① Auth 中间件 `/m/doc-preview` 令牌放行；② 令牌改**顶层 p/t 参数**（`?p=...&t=...`，f 旧格式兼容）；③ 下载统一走带令牌的 `/preview` 代理（PC 详情 + 移动 lightbox 全部调用点）；④ fallback 密钥路径改 `rtrim(runtime_path(), '/\\')` 拼接；⑤ PDF.js 缩放按容器宽度自适应 + 容器 `flex-start`
- **验证**：无 Cookie curl——顶层/旧 f 格式有效令牌均 200、无令牌 302；移动端 390px 整页可见（canvas 382px 全在视口内）；php -l 全绿；版本号保持 v2.43.5（用户要求修复不升版本号）重新出包

### v2.43.4 发布内容（PATCH · 合同详情删除口径统一 + 状态操作入口补齐）
- **删除入口与后端口径一致**：后端软删除允许草稿/已驳回，但详情页删除按钮此前仅草稿可点 → 改为 DRAFT/REJECTED 均可删除（与后端 softDelete 一致），非可删状态显示禁用态 + 提示文案
- **状态操作入口补齐**：合同详情页此前缺失三类操作入口——①「撤回审批」（审批中合同直达，调 `/ajax/approval/<id>/recall`，撤回后回草稿可编辑/删除）；②「终止」（执行中/历史已签合同可终止，调 `/ajax/contract/status-transition`→TERMINATED，带逾期未结回款校验 + 审计留痕）；③「作废」经用户确认**不新增**——合同终态即为「已终止」，终止已覆盖作废语义
- **验证**：浏览器端到端——待审批合同显示撤回审批/删除禁用；已驳回可删除；执行中点击终止→确认→状态变已终止；php -l 全绿；演示数据已恢复

### v2.43.3 发布内容（PATCH · 合同列表编辑入口状态门控 + 403 友好错误页）
- **用户报告**：合同管理列表点击「编辑」报「当前状态不可编辑」，且显示 ThinkPHP 框架默认错误页
- **根因**：① 后端仅允许草稿/已驳回状态编辑（业务拦截正确），但 PC 列表页对**所有状态**无条件渲染编辑按钮（详情页有门控、列表页缺失）；② ExceptionHandle 对 403 页面请求交回框架父类渲染 → debug 模式显示 ThinkPHP 默认错误页
- **修复**：① 列表页编辑按钮仅 DRAFT/REJECTED 状态渲染（与后端口径一致）；② ExceptionHandle 403 页面请求渲染友好 403 页（含业务消息 + 返回按钮，替代框架默认错误页）；③ 403 模板支持业务消息展示
- **验证**：列表页不可编辑状态无编辑按钮、可编辑状态有；直接访问不可编辑合同编辑 URL 显示友好「当前状态不可编辑」页，无 ThinkPHP 品牌错误页；php -l 全绿

### v2.43.2 发布内容（PATCH · 钉钉 WebView 字体加载兼容：@font-face 双格式 + woff base64 内嵌）
- **用户报告**：部署 v2.43.1（文件名版本化）并清除缓存后，钉钉内移动端图标仍显示「长方形对角线」方框；**微信内打开图标正常**——同一份文件微信 WebView 能加载字体、钉钉不能，排除缓存/文件/服务器因素
- **根因**：v2.43.0 子集化时 @font-face 只声明 woff2 并删除 woff 格式（官方原版为 woff2+woff 双格式声明）；钉钉 WebView 对 woff2 字体加载失败（不认格式/拦截字体请求）→ PUA 码点 fallback 系统字体渲染缺字形方框；清缓存无效（非缓存问题）
- **修复**：@font-face 改为**双格式回退**——`url("fonts/bootstrap-icons.v2.43.2.woff2") format("woff2")` 外链（现代浏览器优先）+ `url(data:font/woff;base64,...) format("woff")` **base64 内嵌**（钉钉/旧 WebView 回退，无需字体网络请求，CSS 能加载即能渲染）；woff 子集由 fontTools 从子集 woff2 转换（15.4KB，base64 后 CSS 总量 6.7→27.4KB，移动端可接受）；产物保持文件名版本化
- **验证**：@font-face 双格式正确、158 规则完整、PC/移动模拟浏览器实测渲染正常、php -l 全绿

### v2.43.1 发布内容（PATCH · 修复部署升级后移动端图标显示为缺字形方框）
- **图标子集产物文件名版本化**：v2.43.0 子集化保持同名文件（bootstrap-icons.min.css + fonts/bootstrap-icons.woff2），生产升级后钉钉移动端 WebView 缓存旧完整 CSS + 命中新子集字体 → 数百图标码点缺失显示「长方形对角线」方框（PC 正常）。改为文件名版本化（bootstrap-icons.v2.43.1.min.css + bootstrap-icons.v2.43.1.woff2），@font-face 指向版本化字体名，新旧 URL 彻底分离杜绝混用；6 处引用、check_icons.sh、generate_icons_subset.php 同步版本化
- **验证**：门禁双侧通过、PC/移动模拟浏览器实测图标正常渲染、php -l 全绿、包内无旧同名文件残留

### v2.43.0 发布内容（MINOR · 档 3 前端瘦身：Chart.js 按需加载 + 图标子集）
- **Chart.js 视口懒加载（档 3-C）**：dashboard 趋势图改为 IntersectionObserver 视口可见才注入 chart.umd.min.js + datalabels（`rootMargin:200px`），首屏省约 213KB 脚本下载与解析；无 IO 环境降级为 DOMContentLoaded 立即加载
- **bootstrap-icons 图标子集瘦身（档 3-A）**：全站实际引用 158 个图标 → 子集 CSS 83.9KB→6.7KB、子集 woff2 127.3KB→12.8KB（fontTools 从全量字体子集化，删除旧 woff 格式）；新增 `scripts/icons_whitelist.txt` 白名单 + `scripts/check_icons.sh` 门禁（静态引用扫描 + 子集完整性校验）接入 release.sh——新增图标未补白名单即发布失败
- **子集化暴露并修复 3 处真实图标 bug**：mobile/resource.php `bi-fileearmark-text` 缺连字符（图标一直不显示）、admin/index.php 离职交接弹窗 `bi-person-arrows`（图标不存在）改 `bi-arrow-left-right`、mobile/customer_detail.php 联系人标题 `bi-person-lines`（图标不存在）改 `bi-person-lines-fill`
- **验证**：字体 cmap 全覆盖 158 码点；PC 1280px（admin 用户/钉钉/字典 tab）+ 移动 375px（/m、/m/resource、/m/customer/102）浏览器实测可见图标零缺失，隐藏容器内图标宽度 0 属正常；php -l 全绿；门禁模拟 PASS
- **收尾**：卡片标题文案口径对齐「仅已生效交易合同」（dashboard/_partial.php 4 处 + customer/detail.php fallback 1 处），全站 33 处「总额」文案盘点其余均正确
- **收尾**：仪表盘 KPI 分支角色画像判定修复——approval:view/payment:view 纳入基础权限后原分支判定失效，改用与 sidebar/MobileController 同口径画像（is_manager=approval:approve+supplier:create，is_finance=payment:create+无 supplier:create），四类角色分支全部可达

### v2.42.0 发布内容（MINOR · 钉钉内网可用性：静态资源本地化 + 品牌蓝统一 + 移动工作台瘦身）
- **CDN 静态资源本地化（P0 钉钉内网可用性）**：全站 19 处 jsDelivr CDN 引用（bootstrap css/js、bootstrap-icons、chart.js、datalabels）改为随包自托管 `public/static/vendor/` + `asset_url()` 本地路径——钉钉企业内网 CDN 不可达时不再布局全崩/图标全无/图表不渲染；钉钉 JSAPI（g.alicdn.com）保留
- **移动工作台去 Bootstrap**：移除完整 bootstrap.min.css（约 200KB）与内联重复组件定义，`.m-tabbar` 收敛回 mobile.css 新版（safe-area + 44px 触控）；mobile.css 补齐用到的 Bootstrap 工具类子集，视觉不变
- **登录/entry 品牌蓝统一**：PC 登录页紫色渐变改品牌蓝渐变（对齐移动登录页）；钉钉免登 entry 页灰底改品牌蓝 + 白字白 spinner
- **验证**：浏览器实测 PC dashboard 图表/移动工作台/PC 合同列表 800px/移动列表 375px 全通过，零 CDN 请求，改动 10 文件 php -l 全绿

### v2.41.0 发布内容（MINOR · 审查修复 P0/P1/P2 全批次 + 存量门禁修复 + 真实环境回归）
- **P0 数据风险修复**：审批提交防重复锁（`__approvalSubmitting` + 按钮禁用 spinner + catch 兜底）；财务「确认开票」防连点锁（`__invActing`）；移动端 Loading 卡死修复（mobile-common.js 补 `hideLoading` 定义，boolean 版 showLoading 设 `__mobileShowLoading` 阻止 app.js 覆盖）
- **P1 全站状态/异常一致性**：裸 fetch 全站收敛 `$ajax`（合同详情 14 处 + 列表页）；列表加载失败重试；空态组件化 `emptyState()`（无权限时显示「无新建权限，请联系管理员」）；导出防重复（`__contractExporting`/`__backupExporting` 5s 释放）；离页保护（beforeunload + `__formDirty`）；字段级校验（is-invalid + 滚动定位 + 输入清除）；移动端触控目标 38-44px、弱网重试、驳回二次确认
- **P2 交互与反馈**：`$ajax` 非 2xx JSON 透出后端 msg；审批撤回/逾期标记补 pcConfirm 二次确认；移动端待办分页（Tab1/Tab2 加载更多）、键盘遮挡滚动（focusin）、必填红框
- **产品决策：移动端底部导航固定 4 Tab**（工作台/合同/客户/更多），移除角色替换逻辑（原 `$tab3` 因页面只传固定 key 永不高亮）
- **存量门禁修复**：schema_parity 补齐 init_mysql/init_sqlite 缺失字段（customer_activity.next_follow_at / customer.industry / user.need_handover / project.stage / project.progress）；dead_entry 删除 /notification 死功能（路由 + 控制器 index + 视图，AJAX 接口保留）
- **真实环境回归新发现并修复（本版本关键）**：合同详情页模板编译 500——`loadPayments(){$ajax('/ajax/payment/list/<?=$contract['id']?>'...)` 中 JS 对象字面量 `{$` 紧邻被 ThinkPHP 模板引擎误解析为变量标签，编译产物 PHP 语法错误，静态门禁无法捕获；改为 URL 变量提前拼接消除 `{$` 紧邻
- **验证**：6 道发布门禁全绿（schema_parity/db_comments/view_globals/frontend/dead_entry/PHPUnit）；真实环境浏览器 E2E 闭环通过（登录→建合同 10083→附件上传→提交审批 5101→法务+部门经理两节点审批通过→登记回款 ¥5000→申请开票 ¥10000 待审批，JS 错误 0）；回归数据与临时脚本已清理

### v2.40.8 发布内容（PATCH · 移动工作台经营看板空态渲染修复）
- **根因**：经营看板卡片渲染条件为「有权限且有数据」（`!empty($dept_title) && (!empty($dept_overview) || !empty($dept_members))`），而 `ReportLogic::deptSummary` 仅统计「生效状态 + dept_id>0 + 交易属性」的合同——全新部署/暂无生效合同时返回空数组，卡片整体不渲染，即使总经理（gm）角色权限配置正确也看不到入口，误以为配置失败
- **修复**：渲染条件放宽为「有权限」（`!empty($dept_title)`）即渲染卡片外壳；数据为空时显示空态提示「暂无生效合同数据」，替代整体消失；卡片头部「N 个部门」徽章在有数据时才显示
- **验证**：浏览器端到端两场景——有数据（演示库 3 个部门排名正常显示）+ 无数据（dept_id 全置 0 模拟全新部署，卡片外壳 + 空态提示正常渲染）；php -l 全绿；演示库已恢复原状，临时脚本/备份已清理

### v2.40.7 发布内容（PATCH · 字典增删安全性保护 + 字典项停用/启用 + 项目状态白名单去字典化）
- **字典删除保护（用户决策：方案1 实施）**：8 个系统枚举字典（合同状态/回款状态/发票状态/回款里程碑/客户生命周期/项目状态/数据范围/合同分类）在「设置→字典」中**禁止删除字典项、禁止整字典删除**（后端 `AdminLogic::saveConfig` 双重拦截 + 前端按 `system` 标记隐藏删除按钮）；仍允许修改显示名称、允许新增自定义项。业务数据型字典（客户来源/供应商类型/付款方式/发票类型/税率/行业）保持自由增删，删除后历史记录仅显示回退编码，可加回恢复
- **字典项停用/启用（用户决策：本次交付加入）**：每个字典项支持「停用/启用」切换（系统字典替代删除位）——停用仅从「新建/编辑选项下拉」隐藏（合同表单分类、回款付款方式/里程碑、供应商类型、项目状态已接入），浏览/筛选/统计与历史 label 解析不受影响；停用项收进「已停用 N 项」折叠区，主列表仅展示启用项保持干净；切换成功后局部 DOM 更新，不整页刷新、不折叠当前字典
- **项目状态白名单去字典化（方案3）**：`ProjectController` 保存项目状态不再以 `dict('project_status')` 为校验白名单（字典任意增项会放行未识别状态入库导致列表/统计不认识），改为代码常量枚举 ACTIVE/DONE/ARCHIVED，字典仅用于显示 label
- **验证**：7 个改动文件 php -l 全绿；浏览器端到端：停用「劳动合同」后合同表单分类下拉消失、字典页灰线+启用按钮、`dict()` 历史 label 仍显示「劳动合同」、编辑补回、启用后下拉恢复；测试数据与临时脚本已清理

### v2.40.6 发布内容（PATCH · 审查问题全量修复：无障碍 + 前端交互 + 数据/安全/性能/架构）
- **无障碍（P2-06/P2-07/P2-08）**：全站 27 个视图 + 4 个 JS 文件补齐 label for/id 关联（PC 124 处 + 移动 54 处 + 动态模板）；icon-only 按钮补 aria-label（视图+JS 共 50 余处）；tr/div 整行点击加 tabindex+role=link+Enter 键盘可达；viewport 移除 maximum-scale/user-scalable（WCAG 1.4.4）
- **前端交互**：移动端「选择合同」由 mPrompt 输序号改为搜索建议列表直选（finance/invoice_apply，复用 m-party-suggest 模式）；supplier 关键词 oninput 改 onchange+Enter 防抖；admin syncDingTalk 显式传参
- **数据一致性（P2）**：撤销回款拦截已确认子记录、终态合同禁止手动终结（有逾期未结回款）、释放公海拦截有效合同、回款统计 pending 负数口径、日志级别按 APP_DEBUG 收敛
- **安全（P1/S）**：登录失败锁定（900s 锁窗）、JWT 弱密钥强制回退运行时随机、提醒引擎权限守卫+节流、CSRF 白名单精确匹配
- **性能（P1/P2）**：相对方列表安全上限 200 条+truncated 提示、搜索建议 limit、scanAlerts 四类扫描 limit、字典缓存主动清理、审批/回款角标主动失效
- **架构治理（P1-1）**：控制器 34 处低风险 Db 直查下沉 Logic（合同/移动/发票/项目/联系人/通知）
- **验证**：162 个 PHP 文件 php -l 全绿；浏览器端到端：登录/仪表盘/合同列表与详情/审批流程设计器/移动端 finance 选合同建议列表全部通过；无新增测试数据残留

### v2.40.5 发布内容（PATCH · 全量审查回归修复：周期统计 P1 BUG + 收支口径统一 + 存量库权限对齐）
- **周期统计修复（P1）**：`period_range()` 周期 start 由带时分秒改为纯日期，修复合同/回款「周期首日生效」记录全部漏计（曾致仪表盘「本月合同总额恒为 ¥0」）；影响驾驶舱/移动端报表/财务周期汇总三处调用方，浏览器回归本月/本季/本年均正确
- **收支口径统一（P1-3）**：经营月报收支方向、移动端本期新增合同、按部门经营、财务中心收支概览四处补齐「排除草稿/驳回/审批中」，与驾驶舱/项目/往来既有口径一致（草稿采购合同不再计入应付）
- **审批转交错误提示**：转交无效目标从笼统「操作失败」改为回显具体原因（如「转交目标用户无效」），原记录保持 PENDING
- **存量库权限对齐**：admin +invoice:apply、finance +dashboard:company、user +dashboard:stats、gm 移除 5 项系统管理权限；三份 init 脚本角色映射逐项一致（admin=41 / manager=32 / legal=11 / finance=15 / user=19 / gm=36）
- **验证**：162 个 PHP 文件 php -l 全绿；仪表盘/月报/财务中心/发票申请/字典/审批端到端回归通过；测试数据清理完毕

### v2.40.4 发布内容（PATCH · 经营看板权限码细分 + 部门经营门控）
- 新增 `dashboard:company` / `dashboard:dept` 权限码，与 `dashboard:stats` 并列；PC「按部门经营」与移动端「本部门经营」卡片统一由权限码控制，消除部门经理 PC 端越权看全公司排名；三份 init 脚本角色绑定对齐

### v2.40.3 发布内容（PATCH · 经营看板卡片权限配置化）
- 移动工作台「经营看板」卡片显示由 `dashboard:stats` 权限码控制（is_admin 自动拥有全部）

### v2.40.2 发布内容（PATCH · 新增总经理角色 + 权限矩阵评审）
- 新增 role「总经理（gm）」（data_scope=ALL），管理层业务全量权限（36 项，不含系统管理）
- 权限矩阵评审对齐：admin +invoice:apply（41 项）、finance +dashboard:company（15 项）、gm 移除 5 项系统管理、user 保持 19 项；三份 init 脚本角色绑定显式化对齐

### v2.40.1 发布内容（PATCH · 审批详情 UI 优化 + 权限一致性 + 开票资料字段拆分 + 移动工作台权限门控）
- **审批详情页 UI 优化（PC + 移动端对齐同一原型）**：移动端合同摘要卡新增「进度 x/y」徽章、审批进度改为按流程定义渲染完整时间线（已完成绿点「已同意」/当前蓝点「审批中」/未来灰点「待审批」占位/驳回红色高亮/抄送知会）；PC 端信息卡新增「进度 x/y」徽章、步骤条每节点新增彩色状态标签（已同意/审批中/待审批/已驳回/已转交）；修复 PC 审批记录行状态圆点与文本重叠（`.p-2 !important` 覆盖行内 padding）
- **开票资料字段拆分**：`ResourceLogic::$INVOICE_FIELDS` 拆为独立「开户行」「账号」两字段（原合并），顺序 单位名称/税号 → 开户行/账号 → 地址/电话，PC 上传弹窗与移动端表单同步
- **移动端待办中心「全部已读」黑边修复**：Bootstrap `btn btn-link` 在无 Bootstrap CSS 的移动端显示默认黑边框，改为 `<a>` 品牌蓝文字
- **移动工作台快捷操作权限门控**：快捷操作区图标按权限显示（审批/登记回款/申请开票/资料库/客户池/报表），FAB 新建菜单按 create 权限门控；「我的业绩」图形化卡片（2×2 网格 + 环比徽章）
- **审批权限一致性**：`ApprovalController::detail()` 的 `can_act` 叠加 `approval:approve`，无审批权限用户不再看到「同意/驳回」按钮
- **归属人筛选数据范围收敛**：PC/移动端归属人下拉按 ALL/DEPT/SELF 收敛可选项
- **验证**：浏览器实测审批详情 5042/5043（进度徽章 + 三级状态标签）、开票资料两端字段顺序、全部已读边框、快捷操作权限（employee01/finance01 各 5 项）均通过；php -l 全绿；无 DB 变更

### v2.40.0 发布内容（MINOR · 业绩看板 + 项目毛利 + 跟进/付款闭环 + 项目验收联动 + 客户行业）
- **业绩看板（P0-3）**：移动端「我的业绩」页 `/m/my-stats`（个人客户/合同/成交额/回款 + 本月/累计双口径 + 环比徽章）；PC 仪表盘「按部门经营」卡片（管理员可见）——商务分属不同部门、直接比较无意义，故采用「个人自视 + 部门归集」双视角而非个人排名
- **项目毛利（P0-1）**：项目详情经营聚合新增「项目毛利（毛利率）」卡（毛利=销售−采购，正绿负红），`ProjectLogic::aggregate` 新增 gross_margin/gross_margin_rate
- **客户跟进手动录入（P0-2）**：PC/移动端客户详情「记录跟进」弹窗（电话/拜访/会议/微信 + 内容 + 下次跟进时间），`/ajax/customer/add-activity` 带权限/白名单/长度/时间校验
- **应付付款闭环（P1-4）**：合同详情付款方向联动 + 「添加付款」「确认收款/付款」弹窗；财务中心新增「付款管理」Tab（payment_type 过滤）
- **收款计划模板（P1-5）**：里程碑字典化（dict_payment_milestone）+ 一键模板生成（30/50/20 等）批量建批次（`/ajax/payment/batch-add` 事务写入、合计≤余额、上限 10 期）
- **项目执行进度 + 验收联动（P1-6）**：project.stage/progress 双端表单与详情展示；「标记验收完成」联动销售合同置已完成 + 待收尾款提示
- **客户行业 + 漏斗金额（P1-7）**：customer.industry（政府单位/房地产/餐饮旅游/其他）+ 表单/列表/详情全链路；生命周期漏斗新增各阶段销售合同金额维度
- **验证**：验收联动、客户行业全链路、漏斗金额均浏览器实测通过；php -l 全绿；测试数据已清理
- **DB**：`migration_v2.40.0_customer_followup.sql` / `migration_v2.40.0_payment_milestone.sql` / `migration_v2.40.0_project_stage.sql` / `migration_v2.40.0_customer_industry.sql`；init.sql 同步种子

### v2.39.0 发布内容（MINOR · 离职/在职数据交接 + 消息中心优化 + 仪表盘升级）
- **离职交接自动化**：钉钉同步检测疑似离职员工自动标记 `user.need_handover=1` 进入待交接队列；管理员/有权账号（`system:handover` 权限码，新增独立权限种子+迁移）办理数据移交——将名下客户/合同/待审批批量转移给指定账号（可勾选禁用进入回收站）；「未离职」仅清除标记；交接/恢复/清除后标记统一清零
- **在职员工间交接**：PC 用户管理新增「数据交接」按钮 + 移动端「在职交接」Tab，在职员工间批量移交（接收人可跨部门），双方保持在职、默认不禁用；与离职交接共用 `AdminLogic::handoverUser` 公共逻辑
- **移动端交接页 /m/handover**：待交接/在职交接双 Tab + 顶部搜索；接收人改为搜索选择器（radio 单选，弹窗默认不列出全部用户、输入关键词后渲染匹配项，显示部门），替换原下拉菜单
- **消息中心优化**：PC/移动端数据流统一（复用 /ajax/notification/*）、工作台铃铛入口+红点计数、点击即标记已读并刷新角标、移动端「全部已读」按钮、check-target 校验被删审批目标并友好提示、read/unread 视觉区分卡片化
- **仪表盘升级**：趋势图升级 Chart.js 4.4.1（hover tooltip/动画/Y 轴自适应）、「最近合同」固定返回最新 8 条、状态栏上移卡片化、月/季/年/累计周期筛选左对齐、切换周期加载中遮罩（spinner+文案）、近期回款行可点击跳详情
- **响应式修复**：800px 宽度（钉钉 PC 内嵌）无横向滚动/无内容遮挡，600px 自动切移动布局；body overflow-x:hidden + .main-content min-width:0、窄屏侧栏 180px、多列表格 .table-responsive、canvas 容器防溢出
- **附件 MIME 白名单统一**：ContractController/PreviewController/前端 create.php/contract_form.php 四端一致（仅 PDF/Word/JPG/PNG，拒绝 TXT/XLS）；PreviewController 改用 `force(false)` 实现浏览器内联预览
- **权限审计**：侧边栏入口权限与后端守卫对齐（system:user 一致）；全站权限码扫描确认无遗漏
- **P3 重构**：`ContractLogic::getList` 拆分为 applyListFilters（12 项筛选）+ countChildrenPerFramework（GROUP BY 消除 N+1）；`RemindService::dispatch` 拆分为 4 个扫描方法 + pushToUsers（钉钉推送带重试与告警日志）
- **验证**：离职交接（204→203 转移 4 客户 5 合同 + 禁用 + 标记清零 + 审计留痕）与在职交接（双向转移、双方保持在职、数据还原）全流程实测通过；交接弹窗搜索选择器交互实测通过；php -l 全绿
- **DB**：`migration_v2.38.25_user_need_handover.sql`（user.need_handover）、`migration_v2.38.26_permission_system_handover.sql`（system:handover 权限码 + admin 角色绑定）；三份初始化脚本与 repair_rbac.sql 同步种子

### v2.38.26 发布内容（PATCH · 审批/发票流程编辑器全面对齐 + 移动端两处修复）
- **审批流程与发票流程合并单一入口**：侧边栏统一为「审批流程」，列表类型列分流；右上角「新建发票流程」+ 列表行「编辑」均打开弹窗编辑器（左分支画布 + 右流程配置面板），独立页保留作深层链接；弹窗标题动态区分「新建/配置」（无存量流程时显示新建）
- **审批流程拖动排序**（v2.38.24）：approval_flow 加 sort_order，同类（合同/发票）内拖拽排序越靠前优先级越高；手动排序覆盖金额区间自动选择（提示文案明示）；含新迁移 `migration_v2.38.24_flow_sort_order.sql`
- **发票流程编辑器系列优化**（v2.38.25）：取消 Step1 表单设计、开票内容选项入配置侧边栏、金额条件移入右侧面板、条件分支固定「开票主体」、激活条件功能下线（发票+合同）、抄送角色下拉多选 + chips、含税价税拆分移位、下拉拉长/去连接线/删说明文字；修复条件分支下拉打不开、`__formBuilder` 缺失致下拉空、saveAllFlows where 累积
- **移动端修复**（v2.38.26）：①财务统计「申请开票」弹层透明不可点——发票申请/开票两弹层由 display 直改对齐全站 `.show` 类模式；②首页快捷操作 5 项 3+2 换行难看 → 单行 4 图标横向滑动 + 溢出提示（「左右滑动」小字 + 右侧渐隐）
- **验证**：jsdom 实跑（弹窗打开/下拉/chips/保存/标题区分/移动端点击/溢出提示双场景）+ curl 断言 + 6 门禁全绿（PHPUnit 43/43）；测试数据已恢复
- 无新增表结构（sort_order 迁移见上）

### v2.38.11 发布内容（MINOR · 仪表盘/表单响应式修复 + 签署残留清理 + 三维度审查 + 门禁增强 + 客户生命周期补全）
- **仪表盘 KPI 卡窄屏修复**：①四模块 2×2 降级布局（侧边栏存在时视口 768-1199 四卡两列等高，金额/图标不溢出）；②KPI 高度不齐修复（height:100% + 标题精简）
- **新建/编辑合同表单响应式列宽**：侧边栏存在时窄视口字段降级 50% 两列，长文本 select（签约主体/关联项目）不再截断
- PC 端三处 UI/文案优化：提醒页说明文字精简、KPI 标题精简、表单长文本字段加宽
- **签署功能残留清理**：存量 SIGNED 合同软删归零、6 处"已签署"标签→"历史已签"（三份字典脚本 1:1）、移动报表"已签约"→"生效合同"（统计口径修正 SIGNED+EXECUTING）
- **产品·前端·后端三维度综合审查**（outputs/review_20260803.md）：四大业务闭环/安全防护/权限可见性全通过；修复经营月报、税务汇总 2 个"脚本先于 app.js + 顶层调用 $ajax"隐藏 bug；**check_frontend.sh 门禁扩展覆盖视图内联脚本**（同类问题第 4、5 次根治）
- 客户生命周期功能补全（承接 v2.38.10）：表单/筛选/详情三层入口 + 白名单校验 + 移动漏斗去标题
- 半成品/死功能排查与门禁、VI 补全、侧边栏排序（承接 v2.38.10 批次同批发布）

### v2.38.10 发布内容（MINOR · 客户生命周期补全 + 死功能排查修复 + 门禁增强 + VI 补全）
- 客户生命周期功能补全（用户反馈"线索/商机无入口"）：PC+移动表单加生命周期下拉（白名单校验）、列表漏斗点击筛选/筛选 chips + 生命周期列、详情展示、移动漏斗去冗余标题——打通"编辑/筛选/展示"三层入口
- 半成品/死功能全站排查（outputs/dead_feature_audit_20260803.md）：六维度扫描 → 修复 2 个"完整实现但无入口"死功能——相对方 360（/party，侧边栏客户管理分组加入口）、应收账龄分析（/report/aging，侧边栏财务中心分组加入口）
- **新增 scripts/check_dead_entry.sh 页面入口可达性门禁**（接入 release.sh）：拦截"路由已注册但全站无入口"死功能回归；发布门禁现共 6 道（schema parity / db comments / view globals / frontend 加载顺序 / dead entry / PHPUnit）
- 统一 VI 视觉系统补全（outputs/vi_audit_20260803.md）：PC 交互控件（按钮/表单）从 Bootstrap 默认样式对齐品牌 VI——.btn-primary/.btn-success 全指品牌蓝（保存/提交主操作语义统一）、表单 10px 圆角+品牌边框+40px 高、表头 token 化
- PC 侧边栏二级菜单排序整理：系统设置「系统配置」垫底（业务工具前移），其余分组排序核对合理
- 验证：真实浏览器全链路实测（生命周期建/筛/展、死功能入口可达、VI 计算样式、菜单排序）+ 六门禁全绿（PHPUnit 43·89）
- 无数据库结构变更，生产沿用 v2.38.7 迁移链

### v2.38.9 发布内容（MINOR · 移动端交互优化 + 前端系统性巡检修复 + 脚本加载门禁）
- 移动端修复：原生弹窗清零 8 处（mConfirm/mPrompt 替代，钉钉 webview 无反应问题）、续约跳移动端编辑页、操作栏重设计（续约主操作 + 更多动作面板，破坏性红色区分）、PC 侧边栏二级菜单折叠修复
- 前端系统性巡检（outputs/frontend_audit_20260803.md）：静态扫描（onclick/href/fetch 引用全量核实）+ 动态验证（jsdom 25 页 + 真实浏览器 24 页）→ 修复 2 个隐藏 bug（通知列表/公海池列表加载不出，根因=脚本先于 app.js 加载 + 顶层调用 $ajax）
- **新增 scripts/check_frontend.sh 脚本加载顺序门禁**（接入 release.sh）：根治"依赖 $ajax 未 DCL 防护"类回归
- 随包验证：四门禁全绿（PHPUnit 43·89）+ 新前端门禁 + 真实浏览器回归（通知/公海池/提醒正常）
- 无数据库结构变更，生产沿用 v2.38.7 迁移链

### v2.38.8 发布内容（MINOR · PC 端 UI 视觉体系重构 + 两轮质量复查修复）
- PC 端 UI 视觉底座（P0，方案 outputs/pc_ui_style_plan.md，以移动端设计体系为基准）：app.css token 7→15+ 与移动端同源、品牌顶栏 .navbar-app（#0b5ed7 复刻 m-nav）、全站实底 badge→浅底 pc-tag（对齐 m-tag）、卡片/模态圆角 14px、视图层 60+ 处硬编码色 token 化
- PC 端组件与交互（P1/P2）：筛选芯片化（.pc-chips 对齐 m-chip，project/supplier）、表格密度与 .text-num 金额对齐、驾驶舱趋势 token 化；新增 window.pcConfirm/pcPrompt（Promise 弹层对齐 m-sheet）**全站 44 处原生 confirm/prompt 清零**、删除类确认 danger 红按钮；表单 focus 品牌蓝描边、.pc-empty 空态；PC 端暗色模式（@media prefers-color-scheme 全量 token，移动端 webview 保持 light）
- 质量复查两轮修复（outputs/p012_quality_review.md）：JS 动态 badge 残留收敛（project/resource/form-builder.js）；破坏性操作补 danger 红按钮 11 处；暗色覆盖补全（pc-tag 全色系/Bootstrap 组件/bg-white 21 页 56 处/回款语义行）；project chip 高亮同步；**归档页 JS 崩溃修复**（archive/index.php `\\'` 转义渲染后非法 → 双引号）；**原生 alert 清零**（8 处 → showToast/登录轻量 toast）
- 修复：header.php 补引 app.css（P0-P2 样式此前未加载致主题色丢失）
- 随包验证：四门禁全绿（parity/comments/view_globals/PHPUnit 43·89）+ jsdom 16/16 + 真实浏览器（删除弹窗 btn-danger、chip 高亮、暗色规则命中、移动端 24 页走查）+ E2E 双链路（撤回→删除 5/5、发票冒烟 7/7）
- 无数据库结构变更（纯前端/视图层），生产无需执行新迁移；沿用 v2.38.7 迁移链

### v2.38.7 发布内容（MINOR · 发票三段式审批 + 配置化表单 + 开票税率绑定主体）
- 发票三段式重构：申请→审批→财务开票（invoice:apply / invoice:create 分离、审批引擎 biz_type 分流、状态机 PENDING_APPROVAL→APPROVED→ISSUED→RED/VOID）、独立入口 /invoice-apply（我的申请/待我审批/待开票）+ 工作台快捷按钮
- 配置化表单：invoice_form_field 字段池 + InvoiceFormConfig 双端渲染 + 后台「发票表单」设计器（启停/排序/自定义字段）+ 通用表单设计器 /admin/form-builder + 字段联动组件（form_field_linkage + form-linkage.js：options/show/hide/fill）
- 公司开票流程：金额=含税价（价税实时拆分）、开票类型两选项、开票客户复用（fill 联动带出抬头/税号）、审批按开票公司分支（form_condition 多流程条件分组）
- 开票税率绑定主体（H6/H6b/H6c）：税率单源 company_profile.invoice_tax_rate（后台公司管理配置 0/1/3/5/6/9/13%）、申请表单无税率组件、选主体自动带出+价税刷新、后端强制从公司读税率（防篡改）、合同详情申请开票复用配置化表单
- P1 修复：PC 状态筛选 10 态、全文检索 content_plain、重复合同检测、逾期自动置 OVERDUE 命令、SLA 双超时单源、提醒天数 dispatch 读 sys_config、高级筛选抽屉+骨架屏；init_mysql.php 补齐 invoice_form_field 种子
- 数据库迁移（按序执行）：migration_v2.38.4_remove_sign.sql → v2.38.5_rules.sql → v2.38.6_credit_manual.sql → **migration_v2.38.7_invoice_approval.sql**
- 随包验证：四门禁全绿（parity/comments/view_globals/PHPUnit 43·89）+ E2E（发票三段式 12/12、公司流程 10/10、税率 H6 15/15、H6b 16/16、H6c 18/18）+ jsdom（18+10+12 零错误）

### v2.38.6 发布内容（MINOR · 交付级审查达标）
- 功能补全：M13 字段配置化（PC+移动双端配置驱动渲染）、M14 里程碑回款（PC+移动「复制自上期」）、签署功能彻底移除、后台「业务规则」配置项（公海释放/到期提醒提前天数）、移动端财务统计布局优化、PC 仪表盘近期回款标题优先、移动端资料库底栏修复
- 安全与修复：续约越权/防重、联系人越权、发票状态机、审批节点索引统一、站内提醒引擎落库修复（insertOrIgnore）、信用评级人工锁定（credit_manual）、非交易流程匹配、SLA 双超时收敛、表单配置健壮性 + 四轮审查累计 30+ 项修复
- 数据库迁移（按序执行）：migration_v2.38.4_remove_sign.sql → v2.38.5_rules.sql → v2.38.6_credit_manual.sql
- 随包验证：三门禁全绿 + 交付级全量审查（页面/AJAX/E2E/jsdom/移动端）达标

### v2.35.10 落地项 · 移动端 UI 一致性收尾（M9–M12 + 品牌色统一）（PATCH，发布内容）

- 品牌色统一：PC 端 `#0d6efd` 及 `rgba(13,110,253,…)` 全站收敛为 `#0b5ed7`（与移动端 `--m-brand` 一致），覆盖视图/CSS/JS/时间轴共 9 文件。
- M9 金额强化：customer_detail/projects/project_detail/contracts/archive/approvals/approval_create 共 8 视图金额统一加 `.pay-amt`（17px/700，跨结构独立类）。
- M10 标签去硬编码：财务/合同详情「剩余回款」小标归 `.m-tag-rest`；资料详情分类/公司标签归 `.m-tag-info`/`.m-tag-muted`；客户详情回款图标底色抽 `.pay-recv`/`.pay-pay` 类。
- M11 关联记录对齐：customer_detail 回款记录行、project_detail 关联合同行加 `.pay-row`（顶对齐 + 右侧金额/标签纵向堆叠）。
- M12 重复结构抽类：6 处搜索条内联 box-shadow 抽 `.m-search-bar`；资料详情结构化字段改 `.m-kv`。
- 附带修复：移除 `.amt` 基类默认灰字色，使 `.amt-in`/`.amt-out` 红绿在 `class="amt amt-in"` 元素上真正生效（应收红/应付绿）。

### v2.35.9 落地项 · 移动端体验深化 + 回款/状态/筛选交互修复 + UI 一致性基线（PATCH，发布内容）

- 移动端导航/工作台优化：新增「更多」Tab 聚合页、次级页 `$tab` 高亮修正、审批入口归位、数据看板冗余治理、资料库入口。
- 回款交互：列表行内「确认收款」、移动端周期（月/季/年）筛选、逾期按钮缺失/样式/卡片嵌套/script 未闭合等修复。
- 合同状态机：新增已完成/已到期/已终止→执行中反向撤销跃迁；已归档按钮按来源区分标签与 ghost 样式；移除已软移除的 SIGNED 签署状态按钮露出。
- 筛选卡死：修复 `app.js` 覆盖 `mobile-common.js` 的 `showLoading` 导致合同列表 chip 永远 loading。
- 详情页布局：基本信息压缩为两列网格、合同概要/附件上移；状态变更按钮统一 ghost；回款计划标题栏扁平化对齐 `.fin-card`。
- UI 一致性：全面审计移动/PC 端（交付 `UI_AUDIT_AND_PLAN.md`），落地阶段0 P0 修复（5 个类未定义：customer_detail/approval_create/project_detail/reminders/index）。阶段1–4 后续批次。

### v2.34.0 落地项 · 页面页脚版权信息可配置 + 精简侧栏版本展示（MINOR，功能增强）

- PC 端侧栏左下角移除「更新日志」链接，仅保留版本号（动态读取 `VERSION.md` 当前版本，新增 `app_version()` 助手，避免硬编码漂移）。
- PC 与移动端页面底部新增版权信息展示：PC 端 `app/view/layout/footer.php`、移动端 `app/view/mobile/_foot.php` 均读取全局变量 `$site_copyright`。
- 系统设置新增「系统配置」页（`/admin/config`）：提供「版权信息」设置项，保存至 `system_config.copyright`（复用 `/ajax/admin/config/save`），保存后页脚即时生效；侧栏新增「系统配置」子入口。
- 新增 `sys_config()` 助手（带 300s 短缓存），`BaseController` 全局注入 `site_copyright`/`app_version`；三份初始化脚本（`init_sqlite.php`/`init_mysql.php`/`init.sql`）新增 `copyright` 默认种子。

### v2.33.2 落地项 · 随包交付钉钉免登排查配置文档（PATCH，发布内容）

- 新增 `DINGTALK_SSO_GUIDE.md`（项目根目录），系统化说明「已同步钉钉用户、工作台打开应用仍要输密码」的排查与配置：厘清同步≠免登两条链路、免登工作原理、v2.33.1 已修复的 `login.php` 的 `corpId` 空串 BUG、按 P0→P4 优先级排查清单（P0 应用首页地址须配 `/dingtalk/entry?to=/dashboard`、P1 `.env` 凭据+`MOCK_MODE=false`、P2 免登权限+可信域名、P3 `dingtalk_userid` 非空、P4 版本≥v2.33.1）、标准配置步骤、四类验证法、FAQ 与代码位置索引。该文档已接入 `release.sh` 桌面交付清单，随发布包交付。

### v2.33.1 落地项 · 修复登录页钉钉免登 corpId 为空导致无法自动登录（PATCH，缺陷修复）

- 登录页 `app/view/auth/login.php` 内嵌的钉钉免登逻辑存在缺陷：`requestAuthCode` 的 `corpId` 被写死为空字符串，且缺少 `dd.config` 权限注入，导致任何经登录页进入的钉钉免登必然失败、退回账号密码输入。已改为先请求 `/dingtalk/jsapi-config` 获取真实 `corpId` 并完成 `dd.config`，再发起免登；成功后尊重 `redirect` 深链参数跳转（原写死跳 `/dashboard`）。
- 注：钉钉工作台「应用首页地址」须配置为免登入口 `/dingtalk/entry?to=/dashboard`（移动端 `?to=/m`），才能从打开即自动登录；单纯把首页地址配成 `/login` 或 `/dashboard` 不会自动登录。详见本条发布说明。

### v2.33.0 落地项 · 用户管理改为钉钉后台风格部门结构展示（MINOR，功能增强）

- 用户管理布局由「全宽表格 + 顶部部门筛选下拉」改为**钉钉后台风格**：左侧「部门」面板为**可折叠层级部门树**（顶部「全部成员」根节点 + 各部门的展开/折叠箭头），右侧为成员列表。点击部门节点即高亮并过滤右侧成员（客户端显示/隐藏，不重载）；提供「包含子部门」开关（默认开启，点父部门可显示其下所有子部门成员）。
- 全局注入 `allDepts`（部门扁平列表 `[id,name,parent_id]`），新增 `buildDeptTree / selectDept / selectAllMembers / toggleDept / renderUserList / collectDeptIds` 等函数；折叠/展开由 JS 直接控制子树 `display`（双保险，不依赖 CSS 渲染）。原顶部「按部门筛选」下拉移除。
- 顺手修复已有缺陷：用户管理页「同步钉钉」按钮原因缺少 `#syncLog` 面板而误报「请在钉钉设置页面操作」且无法同步；现改为无面板时直接同步并 `toast` 结果、成功后刷新页面更新部门树与成员（钉钉设置页保持原日志面板行为）。

### v2.32.0 落地项 · 用户管理增加部门列、按部门筛选、修复部门下拉（MINOR，功能增强）

- 用户列表新增「部门」列（直接取 `RbacService::getUserList` 已 LEFT JOIN 的 `dept_name`，无需额外查询）；表头新增「按部门筛选」下拉（选项来自 `UserLogic::getDeptTree()` 扁平部门列表），客户端按 `data-dept-id` 隐藏非匹配行、不重载页面，无匹配时显示「该部门暂无用户」提示行。
- 修复编辑用户弹窗的「部门」下拉（`uDept`）：原仅渲染 `<option value="0">-</option>` 且无任何 JS 填充，导致部门不可选；现由控制器注入 `depts` 后在服务端循环渲染全部部门选项，且 `editUser` 现会预选该用户所属 `dept_id`。
- 控制器 `AdminController::index` 注入 `depts`（部门扁平列表）供上述三处复用。

### v2.31.6 落地项 · 修复审批节点上下箭头失效/错位（PATCH，缺陷修复）

- 审批节点卡片的上移/下移箭头（`moveNode`）原实现基于「箭头是卡片间独立兄弟节点」用 `insertBefore` 交换，存在两个缺陷：①**上移全部失效**——`dir<0` 时 `target` 取到的是前面的 `.node-arrow` 分隔，触发 `!target.classList.contains('node-card')` 提前 `return`，任何节点上移都不生效；②**下移箭头错位**——`insertBefore(target, arrow||node)` 把目标卡片插到当前卡片前，导致卡片间箭头位置错乱、视觉"卡顿/乱跳"。
- 重写为稳健实现：移动前先移除全部 `.node-arrow` 分隔，按新顺序重挂已存在的卡片 DOM 节点（保留内部表单状态），再在每个卡片前（除第一个）重新生成箭头分隔；越界（已在顶/底）时仅重排编号后返回。箭头始终位于相邻卡片之间、首卡片前无箭头，编号由 `renumberNodes` 自动校正。

### v2.31.5 落地项 · 抄送节点角色选择对齐 ROLE 节点下拉（PATCH，缺陷修复）

- 抄送（CC）节点角色选择：按需求由 v2.31.4 的「原生多选列表框（`<select multiple size=4>`）」改为与**审批节点类型为「角色(ROLE)」完全一致**的单个原生 `<select>` 下拉（点开才展开、仅可选一个角色），控件形态与 ROLE 节点（`roleCode_i`）相同。首行保留「- 不指定角色 -」空选项，使 CC 角色可选可空。
- `getNodesData` 的 CC 分支：由读取多选 `selectedOptions` 改为读取单个 `ccRole_i` 的值，并包成单元素 `role_codes` 数组，保持后端（`ApprovalLogic::resolveApprovers` 按 `role_codes` 数组消费）与已存流程兼容，无需改后端。自此 CC 抄送角色仅支持单选。

### v2.31.4 落地项 · 抄送节点角色选择改回下拉（PATCH，缺陷修复）

- 抄送（CC）节点角色选择：撤回 v2.31.3 引入的「角色多选弹窗」，改回与「节点类型为角色(ROLE)」一致的处理方式——直接使用原生 `<select multiple>` 下拉框（ROLE 节点本身也是 `<select>` 下拉，仅单选），不再弹窗。保留多角色语义（`role_codes` 数组），`getNodesData()` 改为从多选下拉的 `selectedOptions` 读取已选角色 code。
- 清理随弹窗引入的抄送专属 JS（`openCcRolePicker` / `renderCcRoleView` / `_rpCodes` / `_rpCcIndex` 及 `rpRender` 的 CC 分支），角色弹窗 `#rolePickerModal` 现仅服务于编辑用户分配多角色。

### v2.31.3 落地项 · 审批节点卡片 UI 修复（PATCH，缺陷修复）

- 审批节点卡片：上/下箭头与删除按钮原用 `position:absolute` 绝对定位，压在右侧审批人/抄送区上造成重叠；改为卡片头部行（徽标+标题在左、箭头在右）的正常流布局，移除删除按钮。

### v2.31.2 落地项 · 发布卡点新增视图公共变量全局声明检查（PATCH，发布流程增强）

- 新增 `scripts/check_view_globals.sh` 发布前卡点：扫描 `app/view/**` 所有 `.php`，用状态机追踪 `$tab` 分支层数，检测被声明在 `$tab` 条件分支 `<script>` 内但属于白名单的全局公共符号（`allRoles` / `allUsers` / `flowCats` / `esc`），从根上防止 v2.31.0 那类"跨 tab 公共变量缺失导致弹窗打不开"的回归再次发生。
- 白名单内置默认，并支持从 `scripts/public_view_globals.txt` 扩展（每行一个符号，`#` 开头为注释）。
- 已接入 `release.sh`（与数据库校验并列，受 `--force` 控制）；负向测试已验证可准确捕获违规。

### v2.31.1 落地项 · 修复用户管理 tab 下角色选择/编辑弹窗打不开（PATCH，缺陷修复）

回归修复（v2.31.0 引入）：把 `allRoles` / `allUsers` / `flowCats` / `esc` 等**跨 tab 公共变量与工具函数**从「审批流 tab 脚本块」上提到所有 tab 之前声明的公共脚本块。
- 根因：这些变量原本在仅审批流 tab 渲染的脚本块内用 `let` 声明；访问「用户管理」tab 时该段不输出，导致 `editUser` / `renderRoleView` / `openRolePicker` / `rpRender` 调用 `allRoles` / `esc` 时抛 `ReferenceError`，弹窗无法打开（表现为"角色选择无法点击、用户列表编辑无法点击"）。
- 影响面：仅「用户管理」tab 的编辑用户 / 选择角色交互；审批流 tab 功能本身正常。
- 验证：用 jsdom 实跑用户管理 / 审批流两 tab 页面，确认 `allRoles` 等已定义、无重复 `let` 声明报错、`editUser()` / `openRolePicker()` 不再抛错且弹窗打开逻辑执行成功。

### v2.31.0 落地项 · 审批流编辑器 + 用户角色选择器优化（MINOR，新功能）

五大产品需求落地：
1. **审批流「指定用户」节点改为弹出选人窗口**：部门树 + 姓名/用户名搜索 + 分页多选，取代原平铺 `select multiple`；选人范围覆盖全量用户（不再受旧接口 100 人截断限制），已选用户以标签回显。
2. **抄送节点支持「指定用户」**：复用同一选人弹窗，抄送收件人 = 角色（多选）∪ 指定用户（多选）的并集并去重；`ApprovalLogic::resolveApprovers` NODE_CC 分支并入 `cc_user_ids`。
3. **钉钉组织架构同步链路打通**：用户管理「同步钉钉」按钮 → `/ajax/dingtalk/sync-org` → `DingTalkLogic::syncOrganization` → `DingTalkService::syncOrganization`，部门与用户 upsert 入库。**修复 parent_id 误用钉钉 ID 的 bug**——改为两遍处理，本地部门树 `parent_id` 正确映射为本地部门 ID（选人弹窗部门树依赖此字段）。
4. **编辑用户「角色」改为多角色友好选择器**：弹层 + 搜索 + 已选标签回显，取代原生 `select multiple`（修复点选角色后页面不收起的交互硬伤）；`saveUser` 改读 `_rpSelected` 全局。
5. **审批流「适用分类」改为多选 + 「金额条件」开关**：`category_list`（JSON 数组）取代单值 `category`，空=适用全部分类；新增 `use_amount` 开关，关闭后无需填金额上下限且匹配时跳过金额区间。`matchFlow` 适配分类多选交集匹配与金额开关。

**DB 变更**：`approval_flow` 新增 `category_list`、`use_amount` 两列（init_sqlite.php / init_mysql.php / init.sql 三份脚本同步，含中文注释）；存量库升级见 `database/migration_v2.31_add_flow_fields.sql`（MySQL + SQLite 双引擎 ALTER）。

### v2.30.3 落地项 · 全站状态标签中文化 + 移动端审批提醒修复（PATCH，缺陷修复）

**一、全站状态/动作标签中文化（用户反馈：审批记录里还有 recalled 这类英文标签）**
- 根因两类：①`approval_action_label()` 缺 `AUTO_APPROVED`（超时自动通过）映射，审批记录直接露原始码；②审计 action 码（`approve_recall`/`status_change`/`auto_expire`/`invoice_red`/`invoice_void` 等）在审计中心与相对方360「最近动态」未本地化——这才是 "recalled" 类英文的真正来源；且审计筛选下拉原键码（`approve`/`reject`/`status`）与实际写入码不匹配。
- `app/common.php`：`approval_action_label()` 补 `AUTO_APPROVED→自动通过`；新增集中式 `audit_action_labels()/audit_action_label()/audit_type_labels()/audit_type_label()` 四函数（审计操作/类型中文映射，未知码回退原值不报错）。
- `AuditController::index()`：弃用键码不匹配的硬编码数组，改用集中映射。
- `app/view/party/360.php`（4处）：合同状态改 `contract_status_label()`；发票状态映射对齐真实值 ISSUED/VOID/RED；「最近动态」用 `audit_action_label()`；供应商类型用 `dict('supplier_type')`。
- `app/view/mobile/approval_detail.php`：审批进度节点补 `AUTO_APPROVED`。
- 全库复核：视图层 grep 英文枚举码命中均在映射表键位（英文码→中文值），无裸英文码输出。零业务/DB 变更。

**二、移动端审批提醒修复（上一轮遗留，随本版一并进包）**
- 移动端审批待办/抄送/转交等提醒场景的展示与跳转已修复（具体修复随 v2.30.3 基线收口）。

**验证**：4 改动文件 `php -l` 全绿；helper smoke test 全返回中文。

### v2.30.2 落地项 · 登录 CSRF 校验失败 / 会话无法保持修复（PATCH，缺陷修复）

**问题**：HTTP（非 HTTPS）部署（本地开发 / 内网 HTTP 反代）下无法登录，写操作报「CSRF 校验失败，请刷新页面后重试」。根因为 `config/cookie.php` 默认 `secure=(bool) env('COOKIE_SECURE', true)` 且 `.env` 未显式设置该项，会话 Cookie（CONTRACT_SID）被强制标记 `Secure`；浏览器在 HTTP 下拒绝存储带 `Secure` 的 Cookie，会话无法跨请求保持，`Session::get('csrf_token')` 恒空 → CSRF 中间件判定失败返回 403。

**修复**：新增全局中间件 `app/middleware/CookiePolicy.php`（注册于 `app/middleware.php` 最外层、SessionInit 之前），在任意 `Cookie::set` 前将全局 Cookie 单例 `secure` 按请求协议动态修正——未设 `COOKIE_SECURE` 时跟随 `isSsl()`（HTTPS 安全 / HTTP 可登录），显式设置时优先（保留手动覆盖）。`config/cookie.php` 注释同步说明。零业务/DB 变更。

**验证**：本地 HTTP 复现，修复前 `CONTRACT_SID` 响应头带 `Secure`（HTTP 下浏览器拒存），修复后不带；端到端 `POST /login` 返回 `code:0` 登录成功，CSRF 校验通过。

### v2.30.1 落地项 · 移动端原生 confirm 关闭网页 bug 修复（PATCH，缺陷修复）

**问题**：移动端多处操作（合同状态变更、回款确认/撤销、审批提交/通过/撤回、取消归档、客户认领/释放/转移）使用原生 `confirm()`/`alert()`。在微信 webview / iOS Safari 等宿主中，原生 `confirm` 的「取消」按钮常被识别为「关闭当前网页」手势；且 `confirmAndPost` 无防重复点击锁，多次点击会连续叠加原生 confirm，放大误触关闭整页的概率。用户实测：合同状态「已到期/已终止」等多次点击即弹原生 confirm 并关闭网页。

**修复**（纯前端，零业务逻辑/DB 变更）：
- 新增 `mobile-common.js` 的 `mConfirm(msg, onOk, onCancel)` 自定义居中确认弹窗（DOM 实现，取消/确定均为普通按钮，不触发宿主关闭；自带 `window._mModalBusy` 防叠加锁）。
- `confirmAndPost` 改用 `mConfirm` 替代原生 `confirm`（contract_detail.php 状态变更/回款确认/撤销回款经此间接覆盖）。
- 移动端 6 处直接调用原生 `confirm` 的代码改为 `mConfirm`：approval_create 提交审批、archive 取消归档、customer_detail 认领/释放、approval_detail 通过/撤回。
- 移动端原生 `alert` 反馈（customer_detail 认领/释放/转移成功失败）改为 `toast`（统一反馈，规避 webview alert 风险）。
- `mobile.css` 新增 `.m-modal-*` 弹窗样式（z-index 1000 高于 loading 200）。

**未改**：customer_detail 的 `custTransfer` 使用 `prompt()`（输入型，需自定义输入框），属已知项，留独立批次处理。

---

## 当前版本：v2.30.0（2026-07-18）

### v2.30.0 落地项 · P3-3 阶段B1：移动端公共 helper 去重（MINOR，前端重构）

承接 P3-3 阶段A（v2.29.0）之后，将移动端 11 个视图中残留的本地 `toast`/`showLoading`/`esc`/`csrfToken` 共 32 处重复定义删除，统一复用 `public/static/js/mobile-common.js` 已有的更健壮公共版（DOM 不存在时自动创建兜底、csrfToken 走 decodeURIComponent 健壮版）。其余 7 个为同名异实现的页面专属函数（cardHtml/loadList/stTxt/stCls/openSheet/closeSheet 等）保留本地定义，不抽离。零业务逻辑改动、零 DB 迁移。

**重构内容（仅删重复定义）**
- 删除 11 页本地 `function toast/showLoading/esc/csrfToken` 声明，调用点自动命中 mobile-common.js 全局定义。
- `mobile-common.js` 的 `toast` 默认时长对齐本地原值 1800ms，确保行为零变化。
- 复检：18 移动视图 php -l 全过；4 公共函数无残留声明；页面专属函数（cardHtml×6/loadList×5/stCls×4/stTxt×4）完好；所有移动页经 _head 加载 mobile-common.js，无未定义引用。

### v2.29.0 落地项 · P3-3 阶段A：移动页 _foot 统一（MINOR，前端重构）

承接 `DEV_PLAN_2026-07-18.md` §3.2 暂缓项 **P3-3（前半）**：移动端 18 个视图尾部从「3 种混杂模式」统一为单一 `_foot.php` 收尾（tabbar + 统一加载 `app.js` + 闭合标签）。零业务逻辑改动、零 DB 迁移。

**重构内容（仅视图尾部，业务逻辑未动）**
- 14 个页（approval_create/approval_detail/approvals/archive/contract_detail/contract_form/contracts/customer_form/finance/project_detail/projects/reports/supplier_detail/supplier_form）去除手写 `<?=mobile_tabbar('x')?>` + 手写 `</body></html>`，改为 `<?php $tab='x'; include __DIR__ . '/_foot.php'; ?>`。
- `customer_detail.php` / `index.php`：去除内联 `mobile_tabbar` 调用与 `index` 手动引 `app.js`（防重复加载），保留各自内联业务脚本，尾部改 `_foot`。
- `login.php` 按计划保持特殊尾部（无 tabbar、不引 app.js）。
- `customers.php` / `suppliers.php` 此前已用 `_foot`，不动。

**风险结论（已复检）**
- `app.js` 全局 `fetch` 猴补丁仅追加 CSRF 头、不重定向；移动页零 `$ajax` 调用 → 全页加载 `app.js` 无桌面 `/login` 跳转回归。
- 18 个移动视图 `php -l` 全过；尾部模式与已在产线正常运行的 `customers/suppliers` 完全等价。

### v2.28.4 落地项 · Backlog 清零（DEV_PLAN_2026-07-18 复审新发现 + 部署收口，PATCH）

承接 `DEV_PLAN_2026-07-18.md` §3 Backlog，将 5 项复审新发现 + 1 项部署侧收口全部落地。零架构改动、零 DB 迁移。

**产品 / 业务（状态机健壮性）**
- **N-M1 超时审批并发复核**：`ApprovalLogic::processOverdueApprovals()` 事务内对 `approval_instance` 加锁（MySQL `lock(true)`）并复核 `status===PENDING` 且 `current_node_order` 未变，被并发事务推进/结束则回滚跳过，幂等防重复推进。镜像 M-P1 `action()` 加锁模式。

**前端一致性（统一 AJAX 封装）**
- **N-m1 移动三表单裸 fetch 收敛**：`mobile/contract_form.php`/`customer_form.php`/`supplier_form.php` 保存提交由裸 `fetch` 改移动端统一 `apiPost()`（自动 CSRF + toast 兜底）。这三个表单仅 include `_head.php` 不载 `app.js`，故用移动原生 `apiPost` 而非桌面 `$ajax`（避免 401 误跳桌面 `/login`）。
- **N-m2 admin 日志裸 fetch 收敛**：`admin/index.php` 的 `loadMockLogs()` 改 `$ajax('/ajax/dingtalk/mock-logs',{loading:false,silent:true})`，错误仍就地渲染。

**安全**
- **N-m3 Auth 免认证前缀边界收紧**：`middleware/Auth.php` 的 `except` 匹配由 `strpos($path,$prefix)===0` 改为 `$path===$prefix || strpos($path,$prefix.'/')===0`，仅豁免完全相等或真下级路径，杜绝 `dingtalk-evil` 类前缀误豁免。

**移动端体验**
- **N-m4 客户详情关联合同"查看全部"**：`ContractLogic::getRelatedList()` 加 `$limit`（默认 20）+ 新增 `getRelatedCount()`；`getList()` 支持 `customer_id` 筛选；`MobileController::customerDetail()` 注入 `contract_total`/`contract_limit`，`contracts()` 支持 `customer_id` 入参；`customer_detail.php` 卡片头显示真实总数，超限追加"查看全部 N 条合同"入口（对称 M-Pf5 项目详情）。

**部署 / 配置**
- **C3 钉钉 Mock 出厂默认关闭**：`.env.example` 的 `DINGTALK_MOCK_MODE` 由 `true` 改 `false`，防生产复制示例误开 Mock（`config/dingtalk.php` 已 `env(...,false)` 兜底）。

**校验**
- `php -l` 全绿（9 改动文件）；`check_schema_parity.sh`=0（26 表/265 字段）；`check_db_comments.sh`=0（81 表表级+字段级中文注释全带）。

### v2.28.3 落地项 · CODE_REVIEW_v283 全量修复 + 复审闭环（PATCH，缺陷修复）

基于 `CODE_REVIEW_v283.md`（严重 3 + 中等 18 + 轻微 25+）的全量缺陷修复，并经 `CODE_REVIEW_v284.md` 五维并行复审确认全部闭环。零架构改动、零 DB 迁移。

**产品 / 业务（状态机 & 数据完整性）**
- **C1 驳回合同重提状态卡死修复**：`ContractLogic::TRANSITIONS` 开放 `REJECTED→PENDING_APPROVAL`；`ApprovalLogic::submit()` 校验 `transitionStatus()` 返回值，失败回滚。
- **M-P1 或签并发重复审批修复**：`advanceAfterNode()` 事务内 `lock(true)`（MySQL）+ `current_node_order` 复核幂等。
- **M-P2 框架合同删子合同校验**：`ContractLogic::deleteBlockers()` 校验 `parent_id` 子合同并阻塞。
- **M-P3 已收聚合口径统一**：`ProjectLogic`/`PartyLogic` 已收/已付统一用 `paid_amount WHERE PAID`。
- **statusTransition 自批漏洞修复（复审发现）**：`ContractController::statusTransition` 加 `requirePermission('contract:edit')`，`DRAFT/REJECTED/PENDING_APPROVAL` 三态禁止手动改状态，须走审批流。
- **get360 余额失真修复（复审发现）**：`PartyLogic::get360` 的 `receivedPaid` 仅统计交易合同，与 `totalAmount` 口径一致。

**架构 / 可维护性**
- **M-A1 审批流双实现合并**：`ApprovalLogic::getEnabledFlows()` 唯一实现，`AdminLogic` 委托。
- **M-A2 客户/供应商镜像合并**：`PartyLogic` 共用 `getPartyRows/searchParty`，`CustomerLogic`/`SupplierLogic` 委托。
- **M-A3 TemplateController 死代码删除**：删 `index/create/edit` 及 `app/view/template/` 视图目录。
- **M-A4 jsapiConfig 兜底**：`DingTalkController::jsapiConfig` 包 `try/catch`，异常 `Log::error` + `json_error`。
- **BaseController 未用 import 清理（复审发现）**：删除 `use think\facade\Db;`。

**性能 / 数据层**
- **M-Pf1 审批历史 N+1 修复**：`getPendingApprovalId` 加 `$approvals` 复用参数，消除冗余逐实例查询。
- **M-Pf2 同请求重复拉取修复**：`MobileController` 传 `$approvals` 避免 2 次全量拉取。
- **M-Pf3/4 缺失索引补齐**：`creator_id`/`party_b_customer_id`/`parent_id`/`category`/`our_company_id` 等高频过滤索引（init 三脚本 + migration_v2.29）。
- **M-Pf5 项目合同无 LIMIT 修复**：`ProjectLogic::getContracts` 加 LIMIT(200) + `getContractsCount`，视图超限显示"查看全部"。
- **P3-7 缓存死代码修复（复审发现）**：`ReportLogic::getMobileSummary` 内部由 `return [` 改为 `$result = [`，`Cache::set` 真正可达（120s）。

**安全**
- **M-S1 密钥明文回显修复**：`admin/index.php` 钉钉 AppSecret 改 `type=password` 且不回显，空值不覆盖。
- **m-S2 登录页弱口令清理**：`auth/login.php` 移除 `value="admin"/"password"` 及「默认账号: admin / password」提示。
- **m-S3 Auth 中间件精确匹配**：`except` 改为含'/'前缀匹配 + 纯词精确匹配，防 `loginXxx` 误豁免。
- **m-S4/m-S5 视图转义 & 路由注释**：审批详情/创建页字段补 `htmlspecialchars`；`preview` 路由注释改"需登录+数据权限+防穿越"；复审再补 `approval/detail.php:5`、`admin/index.php:186,204` 转义。
- **密钥备份文件清理**：仓库根删 `.env.bak`/`.env.bak2`，`release.sh` 打包排除 `.env.*`。

**前端 / UX**
- **C2 移动表单死页防御**：`contract_form.php` 的 `COMPANY_MAP` 加 `isset` 防御。
- **M-F1/F2 静默失败修复**：桌面合同列表/保存提交改 `$ajax` 包装 + `.catch` 兜底。
- **M-F3 按钮文案还原**：移动端合同表单编辑态 `.catch` 按 `$is_edit` 还原。
- **M-F5 底部 Tab 权限裁剪**：`mobile_tabbar()` 按 `approval:view` 裁剪"审批" Tab。
- **M-F6 反馈风格统一**：`admin/index.php` 裸 `alert`→`showToast`，删重复 `delUser`。
- **客户/供应商表单 catch 文案（复审发现）**：`mobile/customer_form.php`/`supplier_form.php` `.catch` 按 `$is_edit` 还原。
- **batchDelete/batchArchive 改 $ajax（复审发现）**：`contract.js` 批量操作改 `$ajax` + `showToast`，去掉裸 `alert`。

**校验**：`php -l` 全绿；`check_schema_parity.sh`=0（26 表/265 字段）；`check_db_comments.sh`=0（81 表全带表级+字段级中文注释）；5 维并行复审（`CODE_REVIEW_v284.md`）确认 v283 全部问题闭环、零严重残留。

### v2.28.2 落地项 · 分层铁律巩固 + 内联 JS 抽离 + 静态资源指纹（PATCH，架构评估建议落地）

承接 v2.28.1 架构评估"暂不分离"结论后的三项近期优化，零架构改动、零 DB 迁移。

- **视图层 4 处残留 Db::name 下沉 Logic**：`contract/detail.php` 的 approval_flow 名称 + company_profile 签约主体信息改由 `ContractController::detail()` 调用 `ApprovalLogic::getFlowById()` / `CompanyLogic::getById()`（新增）注入视图；`template/index.php` 的审批流下拉改由 `TemplateController::index()` 调用 `ApprovalLogic::getEnabledFlows()` 注入；`admin/index.php` 的字典配置改由 `AdminController::index()` 调用 `AdminLogic::getDicts()`（新增）注入。视图层 Db::name 残留清零。
- **内联 JS 抽离到独立文件**：`template/index.php`（22 行）→ `static/js/template.js`；`company/index.php`（36 行）→ `static/js/company.js`；`resource/index.php`（43 行）→ `static/js/resource.js`（含 `window.__RES_CAN_MANAGE` 桥接注入 + `#uploadForm` 防御性空引用检查）。共抽离约 101 行内联 JS 到 3 个独立文件，降低视图耦合。
- **静态资源加版本号指纹**：`app/common.php` 新增 `asset_url(string $path): string` 辅助函数，以 `filemtime` 作为文件级指纹（改一个文件只刷新该文件缓存，精准高效）；18 个视图共 19 处 `<script src>` / `<link href>` 引用统一改为 `<?=asset_url('...')?>`，旧的零散 `?v=2.23.1`/`?v=2.25.0`/`?v=2.27.0` 全部收敛为 filemtime 指纹。

### v2.28.1 落地项 · 移动端新建合同表单体验优化（PATCH，8 处小修）

聚焦「新建合同」表单在移动端的交互体验与正确性修复，无后端架构变更、无 DB 迁移。

- **非交易合同甲方误标"我方"修复**：移动端 `contract_form.php` `updateLabels()`/`syncTrade()` 增 `nonTrade` 分支；`contract_detail.php` 甲方标签按 `trade_attr` 动态输出「甲方（我方）」或纯「甲方」。
- **签约主体方案A（移动端对齐桌面端）**：移动端甲/乙方「本公司」按钮改为弹层选主体（从 `/ajax/company/options` 拉取），填入对应方名称 + 同步 `our_company_id`；保留多主体切换填入能力。
- **关键词归一化**：后端 `common.php` 新增 `normalize_keywords()`（中英文逗号/顿号/分号/空格统一为英文逗号，去重去空保序）；`ContractController::save()` 入库前调用；`ContractLogic::search()` 口径由 `title|contract_no` 扩为 `title|contract_no|keywords`，与 `getList()` 一致。
- **关键词 chip 标签输入**：桌面端 `contract.js` 把 `input[name=keywords]` 降级为隐藏值载体，插入 `.kw-chips` + 迷你输入框，回车/逗号/顿号/分号即生成标签，退格或点 × 删除；移动端 `contract_form.php` 同源实现，提交前 `kwFlush()` 收未回车残留。
- **移动端隐藏「签约主体」下拉**：原可见 `<select>` 改为 `<input type="hidden">`（仍承载 `our_company_id` 提交）；`companyName()` 用注入的 `COMPANY_MAP` 查名，`recomputeOurSide()` 反推我方侧照常工作。
- **关键词顶部弹层 + 高频标签快速选择（移动端）**：关键词字段改为只读展示区 + 顶部固定弹层 `#kwSheet`（`position:fixed;top:0;z-index:951`，浮于输入法之上），层内含输入框 + 右侧「添加」按钮 +「常用标签」区 +「已选」区，彻底解决输入法遮挡；常用标签从新增端点 `/ajax/keyword/hot` 拉取，仅返回当前登录账号自己创建的合同关键词 TopN。
- **后端新增高频关键词接口**：`ContractLogic::getHotKeywords($userId,$limit)` 按 `creator_id=本人 AND keywords<>''` 统计词频取 TopN；`ContractController::hotKeywords()` 权限 `contract:create/edit`，limit 钳制 1–50；`route/app.php` ajax 分组加 `Route::get('keyword/hot','Contract/hotKeywords')`。零新表零迁移。
- **桌面端常用标签快速选择**：`create.php` 关键词输入下方加「常用：」标签行；`contract.js` 暴露 `add(text)` 接口供点击调用，无历史则自动隐藏。
- **新建合同必填项（除关键词、联系人外）**：后端 `save()` 在 `if($id===0)` 块校验 our_company_id>0、party_a_name、party_b_name、effective_date、expiry_date 非空（编辑旧数据不追溯）；桌面端 `create.php` 顶部加 `$isNew` + `$reqMark`/`$reqAttr` 宏，给合同分类/合同方向/签约主体/金额/甲方名称/乙方名称/生效日期/到期日期加红星+required，既有 `wizardValidate()` 自动接管分步校验；移动端 `contract_form.php` 同样九字段加红星+required，提交前 JS 遍历 `#form [required]` 空则 toast 阻止。甲/乙方联系人/电话为选填。

**校验**：`php -l` 全绿；schema parity / 中文注释双校验 0；两端新建页冒烟 200，控件/必填/弹层就位；高频关键词接口隔离验证（employee01 仅见自己创建的词）。

## v2.28.0（2026-07-18）— P1–P3 全量修复 + 桌面端分层铁律回归（MINOR，CODE_REVIEW_v270 收官）

**本轮目标**：收官 `CODE_REVIEW_v270.md` 全部 P1–P3（中等 36 + 轻微 28）修复，并完成桌面端「控制器零直查」分层铁律系统性回归（GOLF）。含 P0 修复后的功能/安全/架构补齐，以及桌面端 13 个控制器 Db 直查清零。

**审批引擎（P1-1/2/5 + P2-16）**
- 审批通过全路径（含全抄送免签）在 `APPROVED` 后追加 `EXECUTING` 跃迁，状态机闭合。
- `submit()` 前置校验：仅 `DRAFT`/`REJECTED` 可提交，非法状态抛异常不创建实例。
- `recall()` 包事务，调 `ContractLogic::transitionStatus(->DRAFT)`，失败回滚 + `Log::error`。
- `processOverdueApprovals()` catch 补 `Log::error`；钉钉外呼失败重试一次后 `Log::warning`。

**安全 / 配置 / 异常处理（P1-4/6 + P3-3）**
- `.gitignore` 忽略 `.env.bak*`/`*.bak`；`.env.example` 提示生产用 `generate_secrets.php` 生成密钥；`config/cookie.php` `secure` 默认 `env('COOKIE_SECURE', true)`。
- 新增全局异常处理器 `ExceptionHandle`（绑定 `provider.php`），统一 500 为 JSON/友好页；新建 `error/500.html`、`error/403.php`（修复权限拒绝页缺失导致的 500）。
- JWT 通道装载 `data_scope` 与 Cookie 通道一致；导出文件名清洗防响应头注入；`PreviewController` 路径穿越改 `realpath()` 边界比较；`DingTalkController` 写 .env 转义；`TemplateController` content 输出转义防 Self-XSS。

**上传 / 回款 / 发票状态（P1-3 + P2-4）**
- `ResourceController::save()` 上传改服务端真实 MIME 正向白名单（pdf/doc/docx/xls/xlsx/jpg/png/gif/webp/txt），不再信客户端声明。
- 新增 `InvoiceLogic::canRegister()`：默认拒绝，白名单 `APPROVED`/`SIGNED`/`EXECUTING` 允许登记开票；`InvoiceController::add`/`PaymentController::add` 拦截非允许状态。

**聚合去重与缓存（P1-7 + P2-12）**
- `ReportLogic::dashboardSummary()` 扩展为仪表盘单一口径来源，`DashboardController` 删除 8 处直查并整体 `Cache::remember(120)` 包裹。
- `FinanceLogic` 新增 `getPaymentList/getInvoiceList/getTaxSummary`，`FinanceController` 残留直查下沉。
- `RemindService::scan` 结果加短缓存。

**桌面端分层铁律回归（GOLF · 核心）**
- 13 个桌面控制器（Customer/Supplier/Company/Party/Project/Contract/Approval/Template/Payment/Resource/Sign/Admin/Preview）`Db::name` 直查清零，改为调用 Logic 层；新建 `TemplateLogic`/`ResourceLogic`/`SignLogic`/`AdminLogic`，复用既有 `ContractLogic`/`CustomerLogic`/`SupplierLogic`/`CompanyLogic`/`ProjectLogic`/`PartyLogic`/`PaymentLogic`/`InvoiceLogic`/`FinanceLogic`/`ApprovalLogic`/`UserLogic`/`RoleLogic`。
- 数据范围统一收敛 Logic：`contract`/`customer`/`supplier`/`project` 本体 + `payment_record`/`invoice` 经父合同（`AuthLogic::appendDataScope`）加范围；`system_config`/`approval_flow`/`role`/`user`/`company_profile`/`contract_template`/`approval_record`/`sign_task` 等全局配置不加范围（避免静默丢数据）。
- 修复 `CompanyLogic::getList()` 字段收窄导致 `company/index` 视图访问未定义键 500（补回完整列）。
- `php -l` 全绿；`check_schema_parity.sh`=0、`check_db_comments.sh`=0（无表结构变更）；登录态与 SELF 范围账号冒烟零 500。

**校验**：全部改动 PHP `php -l` 通过；26 表/265 字段 schema parity 与中文注释双校验通过；admin + employee01（SELF 范围）全页面冒烟无致命错误（权限拒绝正确渲染 403 友好页）；关键 AJAX `code:0`。

### v2.27.1 落地项 · P0 严重问题修复（PATCH，CODE_REVIEW_v270 6 项 P0 落地）

**本轮目标**：基于全量代码审查报告 `CODE_REVIEW_v270.md`（70 项问题：严重 6 / 中等 36 / 轻微 28），优先修复 6 项 P0 严重问题（数据错乱 / 认证绕过 / 孤儿数据 / 性能雪崩 / 长事务 / 分层回归）。P1–P3（约 32 人日）作为后续迭代。

**P0-1 回款资金虚增 + 聚合口径修正**
- `PaymentController::confirm()`：部分确认时母记录 `amount` 同步调减为确认额（全额确认保持原额），剩余应收拆为 `PENDING` 子记录承载，杜绝「母记录原额 + 子记录剩余」翻倍虚增。
- `PaymentController::revoke()`：撤销时把部分确认拆出的 `PENDING` 子记录金额加回母记录 `amount`，恢复原始应收。
- 聚合口径统一：`ReportLogic` / `ProjectLogic` / `DashboardController` / `PaymentLogic` 中「已收」类统计由 `sum(p.amount)` 改为 `sum(p.paid_amount)`，真实反映已确认回款。
- 新增幂等历史校准脚本 `scripts/calibrate_payment_amounts.php`（支持 `--dry-run`），dev 库运行结果为「无需校准」（0 行命中）。

**P0-2 钉钉 Mock 认证绕过硬阻断**
- `DingTalkLogic::ssoLogin()`：生产环境（`app.debug` 为空）启用 Mock 模式直接抛出 `RuntimeException` 拦截登录并 `Log::critical` 告警；开发环境 Mock 仅允许命中白名单或已存在 userid 的用户，杜绝任意 `code` 造号。

**P0-3 供应商删除关联校验**
- 新增 `SupplierLogic::deleteBlockers()`（参照 `CustomerLogic::deleteBlockers`），校验关联采购合同（未软删）；`SupplierController::delete()` 前置拦截并返回中文阻塞原因。

**P0-4 提交审批去全表扫描 + status 索引**
- `ApprovalLogic::processOverdueApprovals()`：原 `where('status','PENDING')->select()` 全表载入改为「id 游标 + LIMIT(200) 分批」+ 预载 `approval_flow` 消除 N+1；其调用从 `submit()` 内移除（改由 `approval:escalate` 定时命令触发）。
- `approval_instance` 新增索引 `idx_apv_status(status)`，存量库迁移脚本 `database/migration_v2.27_add_approval_status_index.sql`（SQLite/MySQL 兼容）；dev 库已建索引。

**P0-5 钉钉外呼移出 DB 事务**
- `ApprovalLogic` 新增通知队列机制：`queueNotify()` 入队 + `flushNotify()` 事务提交后发送（失败 `Log::warning` 不影响主流程）；`submit()` / `action()` / `autoProcessCc()` / `advanceAfterNode()` / `processOverdueApprovals()` 内钉钉外呼全部改为队列，消除事务内网络 I/O 持锁。

**P0-6 分层回归急性子集清零**
- 新增 `InvoiceLogic`（从 `InvoiceController` 提取 `getList` / `sumCommitted` / `create` / `find` / `update` / `delete` / `createRed`）；`InvoiceController` 12 处 `Db::name` 直查全部下沉为 `InvoiceLogic`，权限校验仍由控制器经 `ContractLogic::accessible` 把关。其余控制器下沉留待 v2.28 独立迭代（与报告附录建议一致）。

**校验**：`php -l` 全绿；回款部分确认/撤销端到端实测（合同4：确认60→应收100/已收60不翻倍；撤销→应收恢复100）；`approval:escalate` 命令运行无错；`idx_apv_status` 索引已建；`scripts/check_schema_parity.sh`=0、`scripts/check_db_comments.sh`=0（无表结构变更）。

### v2.25.0 落地项 · P0/P1/P2/P3 全量代码审查修复（MINOR，CODE_REVIEW_v240 60 项落地）

**本轮目标**：围绕合同内部审批流程管理、合同执行进度跟踪管理、合同基本信息录入与维护三大核心能力，完成全量代码审查 43 项修复（P0 4 项 + P1 16 项 + P2 9 项 + P3 14 项，剔除签署/跨部门协作/移动端重构暂缓项）。

**P0 紧急修复（4 项）**
- **REV-01**：附件预览加认证与所有权校验，非登录/非归属人拒绝访问。
- **REV-02**：审批提交空审批人数组阻断，前端默认值 + 后端 validateApprovers 提前校验。
- **REV-03**：会签超时/升级机制（48h 自动升级），`ApprovalLogic::processOverdueApprovals()` + 命令 `approval:escalate`。
- **REV-04**：会签节点推进逻辑修正 `advanceAfterNode()` 在 AND 模式下当前节点全部完成才推进。

**P1 修复（16 项）**
- **会话安全/异常/CSRF/Auth 边界（6 项）**：REV-12 Session 写入加固、REV-13 统一异常模板 JSON/HTML 分拣、REV-16 全量 CSRF 中间件 + header 自动注入、REV-14 短信/密码强度加固、REV-37 角色编辑越权门、REV-38 用户编辑越权门。
- **审批审计/已办/全抄送（4 项）**：REV-05 已办列表去掉 STATUS=PENDING 限制含全部状态实例、REV-15 审批关键操作审计补全、REV-25 三 Tab 口径对齐、REV-33 全抄送免签路径修复（DRAFT→PENDING_APPROVAL→APPROVED 合法双跳，状态机放宽 APPROVED→EXECUTING）。
- **功能不可达/财务权限/编号竞态（6 项）**：REV-06 回款撤销补按钮+JS+路由、REV-07 发票作废/红冲补按钮+JS+路由、REV-10 签署页标注「电子签章待接入」、REV-11 钉钉 Mock/生产状态横幅、REV-17 Dashboard 客户/供应商列表加数据权限、REV-18 移动端客户详情关联合同加数据权限、REV-19 合同编号生成加事务+LIKE 前缀匹配。

**P2 修复（9 项）**
- **REV-20**：ReportLogic::statusCountMap 收口统一 10 态计数。
- **REV-21**：Dashboard 趋势循环 (clone $baseQuery) 修复可变查询叠加 WHERE。
- **REV-22**：供应商首屏列表加 limit(200) 安全上限。
- **REV-23**：登录时 computeScope 写入会话 data_scope 缓存。
- **REV-24**：Dashboard 趋势/topProjects 包 Cache::remember 120s 缓存。
- **REV-34**：父合同下拉加数据权限+limit(500)。
- **REV-35**：文件上传 finfo 真实类型正向白名单（pdf/doc/docx/xls/xlsx/jpg/png/gif/webp/txt）。
- **REV-42**：AuthLogic::appendDataScope 无 user 时 where('id',0) 永假返回空集。
- **REV-43**：FinanceController paymentList/invoiceList 改用 layui_table 统一返回。

**P3 整洁/体验（14 项）**
- **REV-25**：审批三 Tab 口径对齐（随 P1 完成）。
- **REV-26**：角色管理界面 data_scope 下拉加「多角色取最高范围 ALL>DEPT>SELF」提示。
- **REV-27**：新增无依赖 XLSX 导出（XlsxHelper + ZipArchive），合同清单/经营月报/驾驶舱均支持 xlsx 格式。
- **REV-28**：合同列表批量操作（固定批量栏 + 全选/行勾选 + 批量归档/删除，后端逐条权限与状态校验）。
- **REV-29**：合同高级筛选（金额区间/相对方名称/签约主体/归属人），后端 getList 新增过滤 + 前端搜索表单扩展。
- **REV-30**：提醒写库从 Dashboard GET 移至命令行 `remind:check`（RemindCheck 命令）。
- **REV-31**：移动端客户详情补充认领/转移/释放按钮（公海认领/本人释放或转移）。
- **REV-32**：ContractLogic::create 中 trade_attr 未传时默认 1（交易合同），系统统计口径兜底。
- **REV-36**：ContractLogic 新增 STATUS_LABELS 静态常量，contract_status_map() 改为引用常量去重复定义。
- **REV-39**：移动端审批角标计数加 Cache::remember 60s 缓存并减查询。
- **REV-40**：dict()/contract_categories() 改用 Cache::remember 跨请求缓存（static 进程内 L1 + Cache L2）。
- **REV-41**：MobileController 新增 getMobilePageParams() 统一分页，替换 7 处硬编码 20。
- **REV-44**：ProjectController 客户下拉魔数改为常量 MAX_CUSTOMER_DROPDOWN=500。
- **REV-45**：新增 StreamedFileResponse 流式响应，ContractController::exportCsv 改边 chunk 边写入临时流输出。

**架构决策**
- 签署/电子签章暂缓（REV-08/09 + 签署功能），本轮明确不开发。
- 状态机放宽：APPROVED→EXECUTING 直连（签署可选），支持审批通过即可进入执行进度管理。
- 跨部门协作（CR-10）暂缓、移动端全面重构（CR-27/CR-38）推迟到 v2.25.0 测试无误后。

**校验**：`php -l` 全绿；`check_schema_parity.sh`=0（26 表/265 字段 1:1）；`check_db_comments.sh`=0（81 表均带表级注释，所有字段带 `-- 中文注释`）。

### v2.24.0 落地项 · 移动端深化：数据看板 / 归档合同 / 项目列表（MINOR，P2-C CR-17）
- **移动端数据看板（/m/reports）**：复用 Dashboard 统计口径，聚合合同总数、经营总额（仅 `trade_attr=1` 计入收支）、10 态分布、回款概览、收支方向（销售应收/采购应付）、客户/供应商/即将到期；按 `can_view_project`（`project:view`）门控项目入口，状态分布链接 `/m/contracts?status=`。
- **移动端归档合同（/m/archive）**：独立归档列表（`status='ARCHIVED'` + 数据权限 `appendDataScope`），支持关键词搜索与 AJAX 分页，卡片链接桌面合同详情 `/contract/<id>`，标注「已归档」徽标。
- **移动端项目列表（/m/projects）**：`ProjectLogic::getList()` + `dict('project_status')` 状态字典，AJAX 分页，卡片链接桌面项目详情 `/project/<id>`。
- **路由与权限**：`route/app.php` 注册 `/m/reports|archive|projects` 并加 `requirePermission('*::view')`；移动端首页「数据看板」卡片新增 报表概览/项目列表/归档合同/财务统计 四个 `quick-item`，与桌面端权限门一致。
- **状态映射统一（CR-57 共效）**：`contracts()` 与 `archive()` 的 `$statusMap`/`$statusBadge` 改为调用公共 `contract_status_map()` / `contract_status_badge()`，消除移动端重复定义。
- 说明：CR-17 落地其「移动端补齐」安全子集；XL/L 级重构项 CR-10（跨部门协作）/CR-27（MobileController 重构）/CR-38（控制器抽 Logic）按 FIX_PLAN §七需独立技术方案，本次仅标注留待后续迭代，不实施破坏性重构。

### v2.24.0 整洁项 · P3 文档/细节（CR-40/41/43/44/45/47/51/52/53/54/56/57，随本版一并交付）
- **CR-40 数据权限语义注释**：`AuthLogic::appendDataScope()` 补充「多角色取最高范围 ALL>DEPT>SELF、无意扩权」详注，指向 `dataScope()`。
- **CR-41 提醒引擎职责注释**：`RemindService::check()`（写入触发）/ `getTodayAlerts()`·`scan()`（纯读）/ `dispatch()`（钉钉推送，push_ 前缀去重）三方法职责边界与调用方注释化，消除重复提醒歧义。
- **CR-43 登录回跳原 URL**：未登录重定向 `/login`（/`/m/login`）时携带 `?redirect=<原始地址>`；`AuthController::login` 校验后回跳；新增 `safe_redirect_url()` 全局 helper 仅允许站内相对路径，**杜绝开放重定向**。两端登录页已读取 `redirect` 参数。
- **CR-44 JWT 会话短缓存**：`Auth` 中间件 `dingtalk_session` 查找加 60s `Cache` 短缓存（仅命中缓存，未命中不缓存，保证吊销即时生效）。
- **CR-45 CSRF 令牌策略定稿**：采用「会话级固定 token」（double-submit cookie 天然容忍并发写请求，避免一次性 token 竞态）；于 `Csrf` 中间件注释化「不轮转」的并发兼容性结论。
- **CR-47 友好 403 页**：新增 `app/view/error/403.html`，`BaseController::deny()` 非 AJAX 分支渲染友好页（含返回驾驶舱/工作台与联系管理员提示），替代框架默认错误页。
- **CR-51 审批引擎常量化**：`ApprovalLogic` 抽取 `NODE_START/NODE_END/NODE_CC/MODE_AND/MODE_OR` 常量，替换散落的 `'CC'`/`'AND'`/`'OR'` 字面量，消除魔术字符串。
- **CR-52 角标短缓存**：`BaseController` 提醒角标与审批待办红点加 60s `Cache` 短缓存，减少重复聚合查询。
- **CR-53 字典静态缓存**：`contract_categories()` 加 `static` 缓存，与 `dict()` 对齐避免重复查库。
- **CR-54 导出分批**：`ContractController::exportExcel` 改用 `chunk(500)` 分批构建 CSV，避免大批量一次性载入内存（峰值优化）。
- **CR-56 DB 方言封装**：`FinanceController` 税务汇总「按月」格式化由内联 `config('database.default')==='mysql'` 判断抽为全局 `db_month_expr()` helper，控制器不再感知 DB 方言。
- **CR-57 状态映射 helper**：新增 `contract_status_map()` / `contract_status_badge()` 公共 helper，移动端 `contracts/archive/customer_detail` 三处重复定义统一收敛。
- 注：P3 项为向后兼容的整洁/注释/细节优化，无 Schema 变更；发布卡点 `check_schema_parity.sh`/`check_db_comments.sh` 均 0。

**技术栈**：PHP 8.4 + ThinkPHP 6.1 + SQLite + Bootstrap 5

### v2.23.1 修复项 · 移动端「新增」悬浮按钮配色异常（PATCH）
- **根因**：`public/static/css/mobile.css` 全局链接规则 `a:-webkit-any-link { color: inherit }` 特异性 (0,1,1) 高于组件类 `.m-fab` 的 `color:#fff` (0,1,0)，把客户/供应商等悬浮「新增」按钮内的 `+` 图标强制继承成深色（`#1f2329`），蓝底叠深灰 `+` 即用户所见「配色异常」。同一高特异性规则还会压制所有 `<a class="m-btn-*">` 按钮文字色。
- **修复**：将 `color: inherit` 从高特异性的「去下划线」规则中拆出，单独放到低特异性 `a { color: inherit }`（0,0,1），既保留 iOS `a:-webkit-any-link` 去下划线，又不再压制组件自身配色；`.m-fab` 额外显式锁定 `i { color:#fff }` 兜底。
- **一致性补全**：移动端合同列表页（`/m/contracts`）此前无「新增」入口，补与客户的/供应商一致的悬浮 FAB（权限门控 `contract:create`），三模块新增按钮样式/配色统一。
- **缓存刷新**：所有移动端视图 `mobile.css` 引用 `?v=` 由 2.22.1 升至 2.23.1，强制客户端拉取修正后 CSS。

**技术栈**：PHP 8.4 + ThinkPHP 6.1 + SQLite + Bootstrap 5

### v2.23.0 修复项 · P0 + P1 安全 / 数据一致性 / 性能 / 可靠性批量修复（MINOR）
- **P0（上轮已交付并验证）**：CR-01 匿名/越权访问控制（匿名→302、越权→403、自有→200）；CR-02 空审批人提交返回明确中文错误；CR-03 超时会签节点自动审批(AUTO_APPROVED)并推进。
- **P1 数据一致性（CR-06/15/16/30/31）**：DRAFT 不可直跳 ARCHIVED；合同/客户删除前校验关联审批/回款/发票/合同；审批 action() 与合同 create/update 事务包裹防孤儿数据。
- **P1 安全（CR-22/33/50）**：`partySearch` 客户/供应商搜索补行级数据权限收敛；JWT 密钥废弃可预测兜底，统一为 `jwtSecret()`（未配置则运行时随机持久化并告警）；钉钉免登入口加固防 `//evil.com` 协议相对开放重定向。
- **P1 性能（CR-29/04/05/60）**：三核心表补 6 个索引（contract 的 dept_id/expiry_date/created_at + 复合(trade_attr,direction,status)、payment_record 的(status,planned_date)、approval_record 的(approver_id,action)）；`getList` 子合同计数与 `getUserList` 角色查询由 N+1 改为一次性预聚合；审批待办列表改用子查询分页消除无上限 `column('instance_id')`；`submit()` 异常补日志。
- **P1 可靠性（CR-07/19/25/32/11）**：归档可逆（ARCHIVED→EXECUTING 取消归档 + 审计）；`AuditService` 失败由静默吞没改为 `Log::error`；`RemindService` 财务角色由硬编码 `role_id=4` 改为按 `code='finance'` 关联；钉钉提醒推送失败重试一次并写 `failed` 计数与告警日志。
- 全量功能验证：P0 3/3、P1 数据一致性 17/17、P1 安全 11/11、P1 性能 7/7、P1 可靠性 5/5 通过；发布卡点 parity=0 / comments=0。

### v2.22.3 修复项 · 数据库初始化脚本字段对齐 + 三方对照卡点
- **根因**：`database/init.sql`（纯 SQL 版 MySQL 镜像）严重过时——相对基准 `init_mysql.php` 缺 7 张表（company_profile、contract_invoice、payment_record、project、remind_log、resource_library、supplier）+ 18 个字段，并残留已废弃的 `contract_field_value` 表、旧版 17 权限种子与旧审批 JSON 格式。以该文件建库时部署报错。
- **修复**：将 `init.sql` **全量重写为 `init_mysql.php` 的 1:1 SQL 镜像**（26 张表 / 262 字段，每字段带 `-- 中文注释`，含完整种子数据：5 角色 + 38 权限 + 审批流/模板/演示数据，审批节点采用新版 JSON 格式）。校验：三份脚本 262/262 字段、0 差异。
- **对照机制（防复现）**：新增 `scripts/check_schema_parity.sh`，以 `init_mysql.php` 为基准对 `init_sqlite.php` / `init.sql` 做「表+字段」1:1 一致性校验（缺表/多表/缺字段/多字段均非零退出）；已接入 `release.sh` 发布卡点，并写入 `DEVELOPMENT_GUIDE.md` §7.1 与 §9 检查清单。`check_db_comments.sh` 同步扩展覆盖三份文件。

**本轮交付性质**：移动端体验深化 + 设备自动判断发布版。包含：① 移动/电脑端访问自动识别并分流（新增移动登录页）；② 移动审批详情合同附件独立置顶卡片；③ 工作台回款登记收敛为快捷入口、表单迁移至财务页底部弹层；④ 客户/供应商页搜索框位置统一；⑤ 移动端主操作按钮配色与系统对齐。全量代码审查报告见 `CODE_REVIEW_v222.md`；v2.20（字段注释 + MySQL 兼容）、v2.21（审批流程自动化）、v2.21.1（审查修复 F1–F4）详见 `CHANGELOG.md`。

### v2.21.1 修复项 · 全量代码审查（F1–F4）
- **F1 安全加固**：`config/app.php` 的 `app_debug` 默认值由 `env('APP_DEBUG', true)` 改为 `env('APP_DEBUG', false)`（避免无 `.env` 时泄露调试信息，双保险）。
- **F2 性能**：`FinanceController` 回款 / 发票列表由无 LIMIT 全表扫描改为服务端 `page()` 分页 + 返回 `total`，移动端与桌面端同步接入"加载更多"。
- **F3 鉴权对齐**：`MobileController` 6 个列表 / 详情方法补 `requirePermission('*::view')`，与桌面端权限门一致。
- **F4 状态码语义**：`app/middleware/Auth.php` 未登录返回 `401`、强制改密返回 `403`（此前 `json()` 默认 200，匿名写请求误返 HTTP 200，请求实际已被拦截）。

### 版本追踪说明
- 此前 `VERSION.md` 停留在 v2.19.2，未随 v2.20 / v2.21 迭代同步；现已补齐至 v2.21.1（含本次审查修复 F1–F4）。后续每次迭代**须同步更新 `VERSION.md` 与 `CHANGELOG.md`**，避免再次出现代码已 v2.21、版本文件仍 v2.19.2 的错位。

### v2.19.1 修复项 · 快速点击菜单并发写库（热修复）
- **根因**：`DashboardController` 每次访问驾驶舱触发 `RemindService::check()` 向 `remind_log` 写库（`shouldRemind` 为「先查后插」check-then-act，无竞态保护）；多进程/并发请求下 SQLite 单写锁报 `database is locked`，且异常未被捕获冒泡成 500。
- **修复**：① `remind_log` 加唯一索引 `uk_remind_dedup`；② `shouldRemind` 改原子 `INSERT OR IGNORE`（并发仅一个成功，无重复无竞态）；③ 新增 `SqliteGuard` 中间件执行 `PRAGMA busy_timeout=5000` + `journal_mode=WAL`（写等待而非立即失败，读写并发）；④ `DashboardController::check()` 调用加 try-catch 兜底防 500；⑤ 前端 `footer.php` 加导航防抖（500ms 内同链接重复点击拦截）。
- **验证**：CLI 12 进程并发写同一记录零 `database is locked`、去重后仅 1 条；HTTP 30 并发点驾驶舱 0 错误。

### v2.19 落地项 · P2-5 合同→项目关联（详见 CHANGELOG.md）
- 新增 `project` 表与 `contract.project_id` 关联；项目管理模块（CRUD + 经营聚合 + 驾驶舱 TOP N）；4 个权限码；验收 17/17。

### v2.18 落地项 · P2-8 发票税务视图（ROADMAP P2-8）
- **新增字段**：`contract_invoice` 补 `tax_rate`、`tax_amount`；新增 `dict_tax_rate` 税率字典（13%/9%/6%/3%/0%）。
- **税额计算**：开票录入选税率，按含税价**价税分离**自动算税额（税额 = 金额 /(1+税率)× 税率），前端弹窗实时预览、后端 `InvoiceController::add` 落库。
- **税务汇总页**：新增 `/finance/tax` + `GET /ajax/finance/tax-data`，按月分「销项（销售）/ 进项（采购）」汇总开票金额与税额，计算「当月应纳税额 = 销项税额 − 进项税额」，含合计行与 CSV 导出；财务中心加入口按钮。
- **开票进度**：合同详情「发票记录」区展示进度条（已开 ¥ / 合同 ¥ / 未开 ¥）。
- 验收：6/6 通过（税务页、聚合接口、含税率开票、税额校验=600、非交易拦截、月度聚合 `2026-07 销项¥10600/税额¥600/应纳¥600`）。

### v2.18 落地项 · P3-4 备份策略与高危留痕（ROADMAP P3-4）
- **自动备份 CLI**：新增 `php think db:backup [--keep=N]`（`app/command/DbBackup.php`），优先 SQLite `VACUUM INTO` 一致性快照、失败回退文件拷贝，输出 `runtime/backup/contract_YYYYMMDD_HHMMSS.db`，自动保留最近 N 份（默认 14）并清理过期；`config/console.php` 注册命令。
- **高危操作留痕**：合同**导出** CSV 新增审计记录（`AuditService::log` action=export，content 记录条数 / 状态 / 日期范围）；合同删除既有留痕保留。
- **部署文档**：`DEPLOY.md` 补 crontab 备份示例（`30 2 * * * php think db:backup`）与恢复说明。
- 验收：备份快照生成 + `--keep=2` 保留策略正确；导出后 `audit_log` 正确写入。

### v2.18 落地项 · P2-9 合同「交易属性 / 非交易合同」建模（ROADMAP P2-9）
- **新增字段 `contract.trade_attr`**（1=交易计入收支 / 0=非交易不计入收支），`contract_template.default_trade_attr` 模板预设。
- **创建流程**：新建/编辑页加「合同性质」单选（交易/非交易）；选非交易时金额置灰为 0、方向行隐藏、并展示引导文案；选 NDA 等 `default_trade_attr=0` 模板时 `applyTemplate` 自动联动切换（R1/R5）。
- **统计数据口径修正（修复 bug）**：仪表盘 `dir_summary` 与财务中心收支概览改为仅统计 `direction IN ('sales','purchase')`，彻底修复"空 direction 归入 sales"导致非交易合同污染"销售应收"计数的问题；经营总额口径收敛为仅交易合同（R2/R4）。
- **后端双重拦截**：`InvoiceController::add` / `PaymentController::add` 对非交易合同返回友好提示并拒绝开票/回款（R8）。
- **列表与移动端**：合同列表加「合同性质」筛选（提交 `trade_attr`，R6），方向徽标对非交易合同显示"非交易·不计入收支"；移动端「我的合同」非交易合同标注"非交易"，快速登记回款搜索排除非交易合同。
- **种子**：NDA 保密协议种子置 `direction='' AND trade_attr=0`（框架协议保持交易，R3）。
- 配套：`DESIGN_v218_trade_attr.md`（代码级方案）、`REVIEW_v218_trade_attr.md`（评审 R1–R4 必须 + R5–R9 建议）、`verify_v218.sh`（16/16 验收全绿）。

### v2.17 落地项（小互联网公司功能路线图 P0 + P1）
- **P0-A 提醒自动触发 + 钉钉主动推送**：
  - 新增 `RemindService::dispatch()`：扫描到期预警（30/15/7/3/1 天）、已到期（抄送管理员）、逾期回款（抄送财务 role_id=4）、即将到期回款（7/3/1 天，抄送财务），按用户聚合为一条 markdown，经 `DingTalkService::sendToLocalUsers()` 主动推送工作通知。以 `push_<type>` 前缀写 `remind_log` 全局去重，避免重复打扰。
  - 新增 CLI 命令 `php think remind:dispatch`（`app/command/RemindDispatch.php` + `config/console.php`），供 crontab 定时触发（如每日 9:00）。
  - 提醒页新增「立即推送到钉钉」「推送记录」按钮（`remind:manage` 可见）；新增 `POST /ajax/remind/dispatch`、`GET /ajax/remind/push-log`。
- **P0-B 经营驾驶舱增强**：仪表盘在 KPI 行下新增「本月经营 + 收支方向概览」卡片：本月已收（绿）、本月预计回款、待我审批（可点击进入审批）、销售应收（点击筛选 sales）、采购应付（点击筛选 purchase）。`DashboardController` 补 `month_received`（本月 PAID 回款额）与 `dir_summary`（按 direction 汇总，走数据权限）。
- **P1-C 结构化字段模板驱动表单**：`contract` 表新增 `custom_fields`（JSON）；模板 `fields_schema` 真正驱动表单——`TemplateController::getPreset` 返回 schema，创建页按 schema 动态渲染字段（text/number/date/select/textarea，含必填校验），收集为 JSON 随合同保存；`ContractController::save` 接收并 JSON 校验落库；详情页展示结构化字段（select 值回显为 label）。种子模板 1（媒体投放：平台/周期/KPI/结算方式/账期）、模板 2（采购：交付物/验收标准/质保/报价单号）。
- **P1-D 轻量移动端工作台**：新增 `/mobile`（`MobileController` + `app/view/mobile/index.php`），移动端自适应，复用钉钉免登入口（`/dingtalk/entry?to=/mobile`）。三大板块：我的待办（待我审批 + 今日提醒）、我的合同（近 8 条，按数据权限）、快速登记回款（搜索合同 + 登记待收款）。

### v2.16 落地项

### v2.16 落地项
- **本公司主体（`company_profile`）—— 深化 P0 合同方向**：
  - 新增 `company_profile` 表（公司全称/简称/统一信用代码/税号/开户行/账号/地址/电话/法人/是否默认）。种子预置 2 个主体（运营主体默认、技术主体）。
  - `contract` 表新增 `our_company_id`；新建合同「签约主体」下拉默认带出默认主体；「本公司」快捷按钮改从 `company_profile` 取数，自动填入我方名称并联动签约主体。
  - 详情页新增「签约主体」行（含默认标记）与「开票资料」行（统一信用代码/开户行/账号/地址/电话），合同拟定与开票时直接参考。
  - **甲/乙视角深化**：合同方向（销售/采购）显式标注「我方为甲方（收款）/ 我方为乙方（付款）」，列表徽标与详情页同步展示，把「我方是乙方」的立场真正产品化。
- **资料库（`resource_library`）—— 优化 P1 + 新功能**：
  - 新增 `resource_library` 表（分类/标题/文件/说明/关联主体/上传人）；分类：合同范本 / 开票资料（可关联主体）/ 标准条款 / 其他。
  - 一级菜单「资料库」（权限 `library:view` 全员可见），管理页按分类筛选、上传、删除（上传复用既有安全校验：格式/MIME/20MB，存 `public/uploads/library`）。
  - 合同拟定页「参考资料库」按钮弹窗列出范本/开票资料，可预览下载（**仅作参考，不强制灌入**，正是对原 P1 文本模板痛点的更好解法）。
  - 权限：`library:view`（全员）/ `library:manage`（管理员·经理）/ `company:manage`（系统设置）。
- **权限与菜单**：侧边栏新增「资料库」入口；「本公司主体」置于系统设置下（管理员）。

### v2.15 落地项
- **P0 合同方向字段 `direction`（销售/采购）**：新建合同时可由用户显式选择「销售（我方收款）/ 采购（我方付款）」；留空时按对方身份自动推断（对方为供应商→采购，对方为客户→销售，兜底销售）。字段落库 `contract.direction`，打通财务收支概览（按方向 SUM 区分应收/应付）与列表方向筛选/徽标，真正把「我方是乙方」的视角产品化。
- **P2 模板重构为「合同类型预设」**：原文本模板（把范本正文灌入合同概要）降级为类型预设。`contract_template` 新增 `default_direction`/`default_flow_id`/`tips`；新建合同选择模板时调 `/ajax/template/:id/preset` 带出「默认分类 / 默认方向 / 建议审批流 / 必填提醒」，不再灌入大段文本。模板编辑器改为预设表单，列表卡片预览显示方向与提醒。
- **审批流预选联动**：提交审批页 `flow_id` 按合同 `flow_id`（来自模板预设或显式选择）预选，并提示「已按合同类型预设的建议审批流预选」。
- **财务收支概览**：财务中心新增「销售合同（我方收款/应收）」「采购合同（我方付款/应付）」两张卡片，按 `direction` 汇总金额与份数。

### v2.14 落地项（源自 v2.13 审查建议）
- **菜单按权限隐藏**：`sidebar.php` 依据当前用户 `user_permissions` 与 `is_admin` 过滤功能入口，无权限者（如普通员工）看不到「审计中心 / 系统设置」等入口，与后端 `requirePermission` 守卫形成纵深防御。
- **列表排序参数白名单**：新增 `BaseController::getSortParams()`，对合同/归档/审批/客户/公海/供应商/审计/财务等 12 个分页列表接口的排序字段名做白名单校验（方向仅允许 asc/desc），杜绝排序字段名注入。

### v2.13 安全审查修复项
- **修复存储型 XSS（合同详情页回款/发票列表）**：回款 `description`/`payment_method`、发票 `invoice_title`/`tax_no`/`invoice_no` 原未转义直接 `innerHTML` 渲染，恶意用户可注入脚本影响查看该合同的高权限用户；已统一经 `esc()`（textContent）转义。
- **加固 `is_admin` 提权防护**：`AdminController::saveUser` 仅允许超级管理员修改 `is_admin` 字段，防止未来将 `system:user` 赋给非管理员角色时的越权提权（当前种子下仅 admin 持有 `system:user`，本项属纵深防御）。

### 历史版本
- v2.12 — 演示账号权限对比 + 用户/角色接口 CSRF 一致性（`$ajax`）
- v2.11 — 审批流程可新建 + 预设可删除 + 审批详情页 500 修复
- v2.10 — 钉钉审批消息通知 + 点击消息进入系统审批闭环
- v2.9 — 全量代码审查 + 越权访问(IDOR)全面加固 + 强制改密服务端守卫
- v2.8 — P1 遗留项 R7 + R18 落地
