<?php
// +----------------------------------------------------------------------
// | 全局路由
// +----------------------------------------------------------------------

use think\facade\Route;

// 钉钉免登 (无Auth中间件)
Route::post('dingtalk/sso-login', 'DingTalk/ssoLogin');
Route::get('dingtalk/jsapi-config', 'DingTalk/jsapiConfig');
Route::get('dingtalk/entry', 'DingTalk/entry');

// 认证
Route::get('/login', 'Auth/index');
Route::post('/login', 'Auth/login');
// P2-12【S-A3】登出改 POST：GET 登出存在 CSRF/会话固定风险（图片/预加载即可触发登出），仅接受写方法
Route::post('/logout', 'Auth/logout');

// 仪表盘
Route::get('/', 'Dashboard/index');
Route::get('/dashboard', 'Dashboard/index');

// 提醒 & 财务中心
Route::get('/remind', 'Remind/index');
Route::get('/finance', 'Finance/index');
Route::get('/finance/tax', 'Finance/tax');
Route::get('/report/monthly', 'Report/monthly');   // P3-2 经营月报（权限同财务中心）
Route::get('/report/weekly', 'Report/weekly');      // v2.47.0 经营周报（总经理例会参考，权限同财务中心）
Route::get('/report/aging', 'Report/aging');       // v2.38.3 应收账龄

// 合同
Route::get('/contract', 'Contract/index');
Route::get('/contract/create', 'Contract/create');
Route::get('/contract/<id>/edit', 'Contract/edit');
Route::get('/contract/<id>', 'Contract/detail');
Route::post('/ajax/contract/<id>/renew', 'Contract/renew');   // v2.38.3 续约（POST：真正生成续约草案）

// 客户
Route::get('/customer', 'Customer/index');
Route::get('/customer/create', 'Customer/create');
Route::get('/customer/pool', 'Customer/pool');

// 供应商
Route::get('/supplier', 'Supplier/index');
Route::get('/supplier/create', 'Supplier/create');
Route::get('/supplier/<id>/edit', 'Supplier/edit');
Route::get('/supplier/<id>', 'Supplier/detail');
Route::get('/customer/<id>/edit', 'Customer/edit');
Route::get('/customer/<id>', 'Customer/detail');

// 客户联系人（M9 独立联系人模块，v2.38.3）
Route::get('/ajax/customer/<customerId>/contacts', 'CustomerContact/list');
Route::post('/ajax/customer/contact/save', 'CustomerContact/save');
Route::post('/ajax/customer/contact/delete', 'CustomerContact/delete');
Route::post('/ajax/customer/contact/primary', 'CustomerContact/setPrimary');

// 项目 (P2-5)
Route::get('/project', 'Project/index');
Route::get('/project/create', 'Project/create');
Route::get('/project/<id>/edit', 'Project/edit');
Route::get('/project/<id>', 'Project/detail');

// 相对方 360 (P2-6)
Route::get('/party', 'Party/index');
Route::get('/party/<type>/<id>', 'Party/view');

// 审批
Route::get('/approval', 'Approval/index');
// F5（v2.38.7）：发票申请独立入口（我的申请/待我审批/快捷申请开票）
Route::get('/invoice-apply', 'InvoiceApply/index');
Route::get('/approval/create/<contractId>', 'Approval/create');
Route::get('/approval/<id>', 'Approval/detail');

// 归档
Route::get('/archive', 'Archive/index');

// 系统管理
Route::get('/admin', 'Admin/index');
Route::get('/admin/user', 'Admin/user');
Route::get('/admin/role', 'Admin/role');
Route::get('/admin/flow', 'Admin/flow');
Route::get('/admin/dict', 'Admin/dict');
Route::get('/admin/dingtalk', 'Admin/dingtalk');
Route::get('/admin/config', 'Admin/config');   // 系统配置（版权信息等基础设置，v2.34.0）
Route::get('/admin/invoice-form', 'Admin/invoiceForm'); // F6：发票申请表单设计器
Route::get('/admin/form-builder', 'FormBuilder/index'); // G1：通用表单设计器（钉钉式两阶段：字段画布+审批抄送）

// 审计中心
Route::get('/audit', 'Audit/index');

// 本公司主体（系统设置）
Route::get('/company', 'Company/index');

// 资料库
Route::get('/resource', 'Resource/index');
Route::get('/resource/<id>', 'Resource/detail'); // v2.43.5 补丁：PC 资料详情页（列表卡片点击打开）

// 数据回收站（仅超级管理员，业务数据软删除后的恢复/彻底删除）
Route::get('/recycle', 'RecycleBin/index');

// 移动端（v2.20 全流程移动化，钉钉免登入口 /dingtalk/entry?to=/m）
// S 级：独立移动视图（审批闭环）
Route::get('/m', 'Mobile/index');
// 移动端登录页（未登录移动设备自动分流至此；已登录自动跳 /m）
Route::get('/m/login', function () {
    if (\think\facade\Session::has('user_id')) {
        return redirect('/m');
    }
    return \think\facade\View::fetch('mobile/login');
});
// 附件内嵌预览代理（需登录 + 数据权限 + 防路径穿越，强制 Content-Disposition: inline；详见 PreviewController）
Route::get('preview', 'Preview/index');
Route::get('/m/approvals', 'Mobile/approvals');
Route::get('/m/approval/<id>', 'Mobile/approvalDetail');
Route::get('/m/contract/create', 'Mobile/contractForm');
Route::get('/m/contract/<id>', 'Mobile/contractDetail');
Route::get('/m/contract/<id>/edit', 'Mobile/contractForm');
Route::get('/m/contract/<id>/approval', 'Mobile/approvalCreate');
Route::get('/m/contracts', 'Mobile/contracts');
Route::get('/m/customers', 'Mobile/customers');
Route::get('/m/customers/pool', 'Mobile/customerPool'); // v2.38.2 移动公海
Route::get('/m/customer/create', 'Mobile/customerForm');
Route::get('/m/customer/<id>', 'Mobile/customerDetail');
Route::get('/m/customer/<id>/edit', 'Mobile/customerForm');

// 相对方 360 移动模块（v2.38.11：原生移动版，替代跳 PC 视图）
Route::get('/m/party', 'Mobile/partyList');
Route::get('/m/party/<type>/<id>', 'Mobile/partyView');

// 供应商移动模块（P1-b）
Route::get('/m/suppliers', 'Mobile/suppliers');
Route::get('/m/supplier/create', 'Mobile/supplierForm');
Route::get('/m/supplier/<id>', 'Mobile/supplierDetail');
Route::get('/m/supplier/<id>/edit', 'Mobile/supplierForm');

// 移动财务模块（P1-d：补齐工作台「财务统计」原生页，消除桌面回退 /finance）
Route::get('/m/finance', 'Mobile/finance');
// v2.38.18：移动端发票申请独立页（与 PC /invoice-apply 同源：我的申请 + 申请开票表单）
Route::get('/m/invoice-apply', 'Mobile/invoiceApply');

// 移动端报表概览 / 归档查看 / 项目列表 / 项目详情（CR-17 补齐核心遗漏模块，Phase 2.6 加项目详情）
Route::get('/m/reports', 'Mobile/reports');
Route::get('/m/report/weekly', 'Mobile/weeklyReport'); // v2.47.0：经营周报移动落地页（钉钉通知/站内信点击直达）
Route::get('/m/archive', 'Mobile/archive');
Route::get('/m/projects', 'Mobile/projects');
Route::get('/m/project/<id>', 'Mobile/projectDetail'); // Phase 2.6：移动端项目详情页
Route::get('/m/remind', 'Mobile/reminders'); // v2.35.7：移动端今日提醒列表页（工作台顶部「今日提醒」入口）
Route::get('/m/notifications', 'Mobile/notifications'); // P1：移动端消息中心（站内信独立列表页）
Route::get('/m/office-preview', 'Mobile/officePreview'); // v2.43.6：通用文档预览页（pdf/docx/xlsx 渲染，钉钉 WebView 不跳出）
Route::get('/m/resource', 'Mobile/resource');
Route::get('/m/resource/<id>', 'Mobile/resourceDetail');
Route::get('/m/more', 'Mobile/more'); // 导航优化 Phase1：「更多」聚合页（财务/报表/项目/归档/资料库）
Route::get('/m/my-stats', 'Mobile/myStats'); // v2.39.0：移动端「我的业绩」个人自视页（纵向环比，不做排行）
Route::get('/m/handover', 'Mobile/handover'); // v2.38.25：移动端待交接办理页（system:user 权限）

// 兼容旧移动端入口
Route::get('/mobile', function() { return redirect('/m'); });

// 强制改密页（首次登录 force_reset=1 跳转）
Route::get('/profile/change-password', 'Admin/changePasswordPage');

// AJAX API
Route::group('ajax', function () {
    Route::post('contract/save', 'Contract/save');
    Route::post('contract/update', 'Contract/save');
    Route::post('contract/delete', 'Contract/delete');
    Route::get('contract/search', 'Contract/search');
    Route::get('party/search', 'Contract/partySearch');
    Route::post('contract/status-transition', 'Contract/statusTransition');
    Route::get('export/contracts', 'Contract/exportCsv');
    Route::get('export/contracts-xlsx', 'Contract/exportXlsx'); // REV-27：合同 xlsx 导出
    Route::post('contract/batch-archive', 'Contract/batchArchive'); // REV-28：批量归档
    Route::post('contract/batch-delete', 'Contract/batchDelete');  // REV-28：批量删除

    Route::post('customer/save', 'Customer/save');
    Route::post('customer/delete', 'Customer/delete');
    Route::get('customer/search', 'Customer/search');
    Route::post('customer/<id>/claim', 'Customer/claim');
    Route::post('customer/<id>/transfer', 'Customer/transfer');
    Route::get('customer/transfer-targets', 'Customer/transferTargets'); // 2026-08-03 转移选人弹窗搜索+分页
    Route::post('customer/<id>/release', 'Customer/release');
    Route::post('customer/merge', 'Customer/merge');       // v2.38.2 客户合并
    Route::get('customer/duplicates', 'Customer/duplicates'); // v2.38.2 查重扫描
    Route::post('customer/<id>/activity', 'Customer/addActivity'); // v2.40.0 P0-2 手动录入跟进
    // v2.45.0 客户协作共享 / 集团层级
    Route::get('customer/<id>/share-list', 'Customer/shareList');
    Route::post('customer/<id>/share', 'Customer/share');
    Route::post('customer/<id>/unshare', 'Customer/unshare');
    Route::get('customer/<id>/group-info', 'Customer/groupInfo');
    Route::post('customer/<id>/join-group', 'Customer/joinGroup');

    // 供应商
    Route::post('supplier/save', 'Supplier/save');
    Route::post('supplier/delete', 'Supplier/delete');
    Route::get('supplier/search', 'Supplier/search');

    // 项目 (P2-5)
    Route::post('project/save', 'Project/save');
    Route::post('project/delete', 'Project/delete');
    Route::post('project/accept', 'Project/accept'); // v2.40.0 P1-6 验收联动
    Route::post('project/terminate', 'Project/terminate'); // 2026-08-10 终止项目（联动终止销售合同）
    Route::post('project/restore', 'Project/restore'); // 2026-08-10 撤销项目终止
    Route::get('project/options', 'Project/options');
    Route::get('project/search', 'Project/search'); // 2026-08-05 合同创建页「关联项目」搜索选择器

    // 相对方 360 (P2-6)
    Route::get('party/data/<type>/<id>', 'Party/data');

    // 发票（F1/F4：申请→审批→开票三段式；my-list=我的申请，pending-approval=待我审批，resubmit=驳回重提）
    Route::get('invoice/list/<contractId>', 'Invoice/list');
    Route::post('invoice/add', 'Invoice/add');
    Route::post('invoice/update', 'Invoice/update');
    Route::post('invoice/delete', 'Invoice/delete');
    Route::post('invoice/void', 'Invoice/void'); // REV-07：发票作废入口（已有逻辑，补路由）
    Route::post('invoice/red', 'Invoice/red');   // REV-07：发票红冲入口（已有逻辑，补路由）
    Route::get('invoice/my-list', 'Invoice/myList');
    Route::get('invoice/pending-approval', 'Invoice/pendingApproval');
    Route::get('invoice/pending-issue', 'Invoice/pendingIssue');
    Route::post('invoice/resubmit', 'Invoice/resubmit');

    // 提醒
    Route::get('remind/check', 'Remind/check');
    Route::post('remind/dispatch', 'Remind/dispatch');
    Route::get('remind/push-log', 'Remind/pushLog');

    // 审计中心
    Route::get('audit/list', 'Audit/list');

    // 财务中心（跨合同回款 / 发票列表）
    Route::get('finance/payment-list', 'Finance/paymentList');
    Route::get('finance/invoice-list', 'Finance/invoiceList');
    Route::get('finance/tax-data', 'Finance/taxData');

    // 移动端财务报表「周期筛选」AJAX（本月/本季/本年/累计）
    Route::get('mobile/finance-summary', 'Mobile/financeSummary');
    Route::get('mobile/reports-summary', 'Mobile/reportsSummary');

    // P3-2 报表导出深化：经营月报 + 驾驶舱数据导出
    Route::get('report/monthly-data', 'Report/monthlyData');
    Route::get('report/monthly-export', 'Report/monthlyExport');
    Route::get('report/monthly-export-xlsx', 'Report/monthlyExportXlsx'); // REV-27：经营月报 xlsx 导出
    Route::get('report/dashboard-export', 'Report/dashboardExport');
    Route::get('report/dashboard-export-xlsx', 'Report/dashboardExportXlsx'); // REV-27：驾驶舱 xlsx 导出

    Route::get('payment/list/<contractId>', 'Payment/list');
    Route::post('payment/add', 'Payment/add');
    Route::post('payment/confirm', 'Payment/confirm');
    Route::post('payment/overdue', 'Payment/overdue');
    Route::post('payment/delete', 'Payment/delete');
    Route::post('payment/revoke', 'Payment/revoke'); // REV-06：回款撤销入口（已有逻辑，补路由）
    Route::post('payment/copy-prev', 'Payment/copyPrev'); // M14：复制上期回款计划（返回预填字段）
    Route::post('payment/batch-add', 'Payment/batchAdd'); // v2.40.0 P1-5：收款计划模板一键生成多期

    Route::post('approval/submit', 'Approval/submit');
    Route::post('approval/<id>/action', 'Approval/action');
    Route::post('approval/<id>/recall', 'Approval/recall');
    Route::get('approval/matched-flows', 'Approval/matchedFlows');
    Route::get('approval/transfer-targets', 'Approval/transferTargets'); // Phase 2.8：转交用户 AJAX 搜索 + 分页
    Route::get('approval/pending-list', 'Approval/pendingList');
    Route::get('approval/processed-list', 'Approval/processedList');
    Route::get('approval/submitted-list', 'Approval/submittedList');

    // 站内消息中心（审批等事件的站内信兜底）
    Route::get('notification/list', 'Notification/list');
    Route::get('notification/unread-count', 'Notification/unreadCount');
    Route::post('notification/mark-read', 'Notification/markRead');
    Route::post('notification/mark-all-read', 'Notification/markAllRead');
    Route::get('notification/check-target', 'Notification/checkTarget');

    Route::post('upload/contract', 'Contract/upload');


    // 本公司主体
    Route::get('company/options', 'Company/options');
    Route::post('company/save', 'Company/save');
    Route::post('company/delete', 'Company/delete');

    // 高频关键词（仅当前登录用户自己创建过的合同关键词，按词频降序）
    Route::get('keyword/hot', 'Contract/hotKeywords');

    // 资料库
    Route::get('resource/list', 'Resource/list');
    Route::post('resource/save', 'Resource/save');
    Route::post('resource/update', 'Resource/update'); // v2.43.6：资料库编辑（library:edit）
    Route::post('resource/delete', 'Resource/delete');

    Route::post('archive/<contractId>', 'Archive/do');
    Route::post('archive/<contractId>/undo', 'Archive/undo'); // Phase 2.5：取消归档（移动端归档列表可操作）

    Route::post('admin/user/save', 'Admin/saveUser');
    Route::post('admin/user/delete', 'Admin/deleteUser');
    Route::post('admin/user/handover', 'Admin/handoverUser'); // v2.38.16：离职交接（客户/合同/待审批批量转移）
    Route::post('admin/user/clear-handover', 'Admin/clearHandover'); // v2.38.25：清除待交接标记（误报/已回岗）
    Route::post('admin/user/restore', 'Admin/restoreUser');   // 从回收站恢复禁用用户
    Route::post('admin/role/save', 'Admin/saveRole');
    Route::post('admin/role/delete', 'Admin/deleteRole');
    Route::post('admin/flow/save', 'Admin/saveFlow');
    Route::post('admin/flow/save-all', 'Admin/saveAllFlows'); // v2.38.22：画布式全量保存合同流程
    Route::post('admin/flow/sort', 'Admin/sortFlows'); // v2.38.24：审批流程拖动排序（同类内 sort_order 重编号）
    Route::post('admin/flow/delete', 'Admin/deleteFlow');
    Route::post('admin/flow/restore', 'Admin/restoreFlow');
    Route::post('admin/flow/purge', 'Admin/purgeFlow'); // 审批流程回收站：彻底删除（校验历史审批实例 / 模板引用）
    Route::post('admin/category/save', 'Admin/saveCategory');
    Route::post('admin/category/delete', 'Admin/deleteCategory');
    Route::post('admin/config/save', 'Admin/saveConfig');
    Route::get('admin/config/backup', 'Admin/configBackup');   // 系统配置备份：下载 JSON 快照（v2.36.0）
    Route::post('admin/config/restore', 'Admin/configRestore'); // 系统配置恢复：预览/提交（v2.36.0）
    Route::post('admin/invoice-form/save', 'Admin/saveInvoiceForm'); // F6：发票申请表单字段配置保存
    // G1：通用表单设计器接口（Step1 表单字段+联动 / Step2 审批+抄送）
    Route::get('form-builder/form-data', 'FormBuilder/formData');
    Route::post('form-builder/save-form', 'FormBuilder/saveForm');
    Route::post('form-builder/save-content-options', 'FormBuilder/saveContentOptions');
    Route::post('form-builder/save-flow', 'FormBuilder/saveFlow');
    Route::get('admin/dept-tree', 'Admin/deptTree');       // 选人弹窗：部门树
    Route::get('admin/user-picker', 'Admin/userPicker');   // 选人弹窗：用户分页搜索
    Route::post('admin/change-password', 'Admin/changePassword');

    Route::post('dingtalk/sync-org', 'DingTalk/syncOrg');
    Route::post('dingtalk/save-config', 'DingTalk/saveConfig');
    Route::get('dingtalk/mock-logs', 'DingTalk/mockLogs');

    // 数据回收站（合同/客户/供应商 软删除恢复 + 彻底删除，仅超管）
    Route::get('recycle/list', 'RecycleBin/list');
    Route::post('recycle/restore', 'RecycleBin/restore');
    Route::post('recycle/purge', 'RecycleBin/purge');
});
