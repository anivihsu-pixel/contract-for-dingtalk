<?php $tab = $tab ?? ''; ?>
<?php
// 菜单按权限隐藏（建议1：纵深防御，与后端 requirePermission 守卫保持一致）
// user_permissions 由 BaseController 注入；管理员(is_admin)拥有全部权限。
$__perms = $user_permissions ?? [];
$__admin = !empty($is_admin);
$__can   = function (string $code) use ($__perms, $__admin) {
    return $__admin || in_array($code, $__perms, true);
};

// P1：角色画像——通过权限码组合判断（避免依赖 role.code，与移动端 more() 同口径）
// 财务画像：有 payment:create 且无 supplier:create
// 部门经理画像：有 approval:approve 且有 supplier:create
// （v2.40.2：contract:create 已为全员默认基础权限，不再能区分画像，改用 supplier:create 标记业务经理）
$__isFinance = !$__admin && $__can('payment:create') && !$__can('supplier:create');
$__isManager = !$__admin && $__can('approval:approve') && $__can('supplier:create');

// P2：普通员工低频菜单隐藏——完整财务中心与发票模块仍只对具备财务写权限者显示；
// 仅有 payment:view 的业务人员另提供「我的回款」只读入口，与后端按数据范围放行保持一致。
$__canFinanceCreate = $__can('payment:create') || $__can('invoice:create') || $__can('invoice:apply');
$__hideFinanceForUser = !$__admin && !$__isManager && !$__canFinanceCreate;

// P2：审批菜单文案个性化——无 approval:approve 的用户看到「我的审批」，有审批权限的看「合同审批」
$__approvalLabel = $__can('approval:approve') ? '合同审批' : '我的审批';
?>
<a href="/dashboard" class="nav-link <?=$menu_active=='dashboard'?'active':''?>"><i class="bi bi-speedometer2"></i> 仪表盘</a>
<?php
// P1：按角色排序输出业务菜单块
// 收集各菜单块 HTML，再按角色顺序拼接
$__blocks = [];

// 审批块
if ($__can('approval:view')) {
    ob_start();
    $approvalActive = $menu_active == 'approval' ? 'active' : '';
?>
<a href="/approval" class="nav-link <?=$approvalActive?>"><i class="bi bi-check2-circle"></i> <?=$__approvalLabel?><?php if(!empty($approval_pending)): ?><span class="pc-tag pc-tag-danger ms-auto"><?=$approval_pending?></span><?php endif; ?></a>
<?php
    $__blocks['approval'] = ob_get_clean();
}

// 合同管理块
if ($__can('contract:view')) {
    ob_start();
?>
<!-- 合同管理（2026-08-03 修复：归档管理属于本分组，激活时父菜单保持展开） -->
<a href="javascript:void(0)" class="nav-link <?=in_array($menu_active,['contract','archive'])?'active':''?>" onclick="toggleSub('contractSub',this)" aria-expanded="<?=in_array($menu_active,['contract','archive'])?'true':'false'?>">
  <i class="bi bi-file-text"></i> 合同管理 <i class="bi bi-chevron-down small ms-auto"></i></a>
<div class="sidebar-sub <?=in_array($menu_active,['contract','archive'])?'show':''?>" id="contractSub">
  <a href="/contract" class="nav-link sub <?=$menu_active=='contract'&&($tab??'')==''?'active':''?>"><i class="bi bi-list-ul"></i> 合同列表</a>
  <?php if($__can('contract:create')): ?><a href="/contract/create" class="nav-link sub"><i class="bi bi-plus-lg"></i> 新建合同</a><?php endif; ?>
  <a href="/archive" class="nav-link sub <?=$menu_active=='archive'?'active':''?>"><i class="bi bi-archive"></i> 归档管理</a>
</div>
<?php
    $__blocks['contract'] = ob_get_clean();
}

// 客户管理块
if ($__can('customer:view')) {
    ob_start();
?>
<!-- 客户管理（2026-08-03 修复：供应商属于本分组，激活时父菜单保持展开） -->
<a href="javascript:void(0)" class="nav-link <?=in_array($menu_active,['customer','customer_create','supplier','party'])?'active':''?>" onclick="toggleSub('customerSub',this)" aria-expanded="<?=in_array($menu_active,['customer','customer_create','supplier','party'])?'true':'false'?>">
  <i class="bi bi-people"></i> 客户管理 <i class="bi bi-chevron-down small ms-auto"></i></a>
<div class="sidebar-sub <?=in_array($menu_active,['customer','customer_create','supplier','party'])?'show':''?>" id="customerSub">
  <a href="/customer" class="nav-link sub <?=$menu_active=='customer'&&($tab??'')==''?'active':''?>"><i class="bi bi-list-ul"></i> 客户列表</a>
  <?php if($__can('customer:create')): ?><a href="/customer/create" class="nav-link sub <?=$menu_active=='customer_create'?'active':''?>"><i class="bi bi-plus-lg"></i> 新增客户</a><?php endif; ?>
  <?php if($__can('supplier:view')): ?><a href="/supplier" class="nav-link sub <?=$menu_active=='supplier'?'active':''?>"><i class="bi bi-truck"></i> 供应商</a><?php endif; ?>
  <!-- v2.38.9 修复：往来档案此前无任何入口；v2.38.14 更名+资金台账定位；P0 修复图标与财务中心重复 -->
  <?php if($__can('party:view')): ?><a href="/party" class="nav-link sub <?=$menu_active=='party'?'active':''?>"><i class="bi bi-arrow-left-right"></i> 往来档案</a><?php endif; ?>
</div>
<?php
    $__blocks['customer'] = ob_get_clean();
}

// 项目管理块
if ($__can('project:view')) {
    ob_start();
?>
<!-- 项目管理 (P2-5) -->
<a href="javascript:void(0)" class="nav-link <?=$menu_active=='project'?'active':''?>" onclick="toggleSub('projectSub',this)" aria-expanded="<?=$menu_active=='project'?'true':'false'?>">
  <i class="bi bi-kanban"></i> 项目管理 <i class="bi bi-chevron-down small ms-auto"></i></a>
<div class="sidebar-sub <?=$menu_active=='project'?'show':''?>" id="projectSub">
  <a href="/project" class="nav-link sub <?=$menu_active=='project'&&($tab??'')==''?'active':''?>"><i class="bi bi-list-ul"></i> 项目列表</a>
  <?php if($__can('project:create')): ?><a href="/project/create" class="nav-link sub"><i class="bi bi-plus-lg"></i> 新建项目</a><?php endif; ?>
</div>
<?php
    $__blocks['project'] = ob_get_clean();
}

// 发票申请块（P2：普通员工隐藏）
if ($__can('invoice:view') && !$__hideFinanceForUser) {
    ob_start();
    $invoiceActive = $menu_active == 'invoice_apply' ? 'active' : '';
?>
<a href="/invoice-apply" class="nav-link <?=$invoiceActive?>"><i class="bi bi-receipt-cutoff"></i> 发票申请</a>
<?php
    $__blocks['invoice'] = ob_get_clean();
}

// 财务中心块（P2：普通员工隐藏）
if (($__can('payment:view') || $__can('invoice:view')) && !$__hideFinanceForUser) {
    ob_start();
?>
<!-- 财务中心（v2.38.7：发票前端恢复后补回「发票管理」菜单入口；回款/发票均可见财务中心分组） -->
<a href="javascript:void(0)" class="nav-link <?=$menu_active=='finance'?'active':''?>" onclick="toggleSub('financeSub',this)" aria-expanded="<?=$menu_active=='finance'?'true':'false'?>">
  <i class="bi bi-cash-coin"></i> 财务中心 <i class="bi bi-chevron-down small ms-auto"></i></a>
<div class="sidebar-sub <?=$menu_active=='finance'?'show':''?>" id="financeSub">
  <a href="/finance?tab=payment" class="nav-link sub <?=$menu_active=='finance'&&!in_array($tab??'',['invoice','aging','weekly'])?'active':''?>"><i class="bi bi-arrow-down-left-circle"></i> 回款管理</a>
  <?php if($__can('invoice:view')): ?><a href="/finance?tab=invoice" class="nav-link sub <?=$menu_active=='finance'&&($tab??'')=='invoice'?'active':''?>"><i class="bi bi-receipt"></i> 发票管理</a><?php endif; ?>
  <!-- v2.38.9 修复：应收账龄分析此前无任何入口；v2.38.11 补 active 高亮 -->
  <a href="/report/aging" class="nav-link sub <?=$menu_active=='finance'&&($tab??'')=='aging'?'active':''?>"><i class="bi bi-hourglass-split"></i> 应收账龄</a>
  <!-- v2.47.0：经营周报入口（全公司口径，仅 dashboard:company 角色可见——总经理/超管；权限收紧同页面守卫） -->
  <?php if($__can('dashboard:company')): ?><a href="/report/weekly" class="nav-link sub <?=$menu_active=='finance'&&($tab??'')=='weekly'?'active':''?>"><i class="bi bi-calendar-check"></i> 经营周报</a><?php endif; ?>
</div>
<?php
    $__blocks['finance'] = ob_get_clean();
}

// 普通业务人员：只读查看本人数据范围内的回款，不展示完整财务中心分组。
if (!$__admin && !$__isManager && !$__isFinance) {
    ob_start();
?>
<a href="/finance?tab=payment" class="nav-link <?=$menu_active=='finance'?'active':''?>"><i class="bi bi-cash-coin"></i> 我的回款</a>
<?php
    $__blocks['my_finance'] = ob_get_clean();
}

// 资料库块
if ($__can('library:view')) {
    ob_start();
    $libActive = $menu_active == 'resource' ? 'active' : '';
?>
<a href="/resource" class="nav-link <?=$libActive?>"><i class="bi bi-folder2-open"></i> 资料库</a>
<?php
    $__blocks['library'] = ob_get_clean();
}

// 系统设置块（P3：回收站/审计降级为子菜单，内部分高频/低频两组）
$__canUserManage  = $__can('system:user');
$__canRoleManage  = $__can('system:role');
$__canConfig      = $__can('system:config');
$__canCompany     = $__can('company:manage');
$__canDingTalk    = $__can('dingtalk:sync');
// 审批流程/字典设置页面入口沿用用户管理权限（后端 AdminController::index 各 tab 统一守卫 system:user，
// 入口权限若用 system:config 会出现"显示但点击 403"的不一致，2026-08-05 修正）
$__canFlow        = $__can('system:user');
$__canDict        = $__can('system:user');
$__canAudit       = $__can('audit:view');
$__showAdminGroup = $__admin || $__canUserManage || $__canRoleManage || $__canConfig || $__canCompany || $__canDingTalk || $__canAudit;
if ($__showAdminGroup) {
    ob_start();
?>
<!-- 系统设置（P3：高频组在前，低频组在后；回收站/审计降级为子菜单） -->
<a href="javascript:void(0)" class="nav-link <?=($menu_active=='admin'||$menu_active=='company'||$menu_active=='recycle'||$menu_active=='audit')?'active':''?>" onclick="toggleSub('adminSub',this)" aria-expanded="<?=($menu_active=='admin'||$menu_active=='company'||$menu_active=='recycle'||$menu_active=='audit')?'true':'false'?>">
  <i class="bi bi-gear"></i> 系统设置 <i class="bi bi-chevron-down small ms-auto"></i></a>
<div class="sidebar-sub <?=($menu_active=='admin'||$menu_active=='company'||$menu_active=='recycle'||$menu_active=='audit')?'show':''?>" id="adminSub">
  <?php if($__canUserManage): ?><a href="/admin/user" class="nav-link sub <?=$menu_active=='admin'&&$tab=='user'?'active':''?>"><i class="bi bi-people"></i> 用户管理</a><?php endif; ?>
  <?php if($__canRoleManage): ?><a href="/admin/role" class="nav-link sub <?=$menu_active=='admin'&&$tab=='role'?'active':''?>"><i class="bi bi-shield-check"></i> 角色权限</a><?php endif; ?>
  <?php if($__canFlow): ?><a href="/admin/flow" class="nav-link sub <?=$menu_active=='admin'&&$tab=='flow'?'active':''?>"><i class="bi bi-diagram-3"></i> 审批流程</a><?php endif; ?>
  <?php if($__canCompany): ?><a href="/company" class="nav-link sub <?=$menu_active=='company'?'active':''?>"><i class="bi bi-buildings"></i> 本公司主体</a><?php endif; ?>
  <?php if($__canDict): ?><a href="/admin/dict" class="nav-link sub <?=$menu_active=='admin'&&$tab=='dict'?'active':''?>"><i class="bi bi-book"></i> 字典设置</a><?php endif; ?>
  <?php if($__canDingTalk): ?><a href="/admin/dingtalk" class="nav-link sub <?=$menu_active=='admin'&&$tab=='dingtalk'?'active':''?>"><i class="bi bi-chat-dots"></i> 钉钉设置</a><?php endif; ?>
  <?php if($__canConfig): ?><a href="/admin/config" class="nav-link sub <?=$menu_active=='admin'&&$tab=='config'?'active':''?>"><i class="bi bi-sliders"></i> 系统配置</a><?php endif; ?>
  <?php if($__admin): ?><a href="/recycle" class="nav-link sub <?=$menu_active=='recycle'?'active':''?>"><i class="bi bi-trash3"></i> 数据回收站</a><?php endif; ?>
  <?php if($__admin): ?><a href="/audit" class="nav-link sub <?=$menu_active=='audit'?'active':''?>"><i class="bi bi-shield-check"></i> 审计中心</a><?php endif; ?>
</div>
<?php
    $__blocks['admin'] = ob_get_clean();
}

// P1：按角色排序输出
if ($__isFinance) {
    // 财务：财务→发票→合同→审批→客户→项目→资料库→系统设置
    $__order = ['finance', 'invoice', 'contract', 'approval', 'customer', 'project', 'library', 'admin'];
} elseif ($__isManager) {
    // 部门经理：审批→合同→客户→项目→财务→发票→资料库→系统设置
    $__order = ['approval', 'contract', 'customer', 'project', 'finance', 'invoice', 'library', 'admin'];
} elseif ($__admin) {
    // 管理员：合同→审批→客户→项目→财务→发票→资料库（系统设置固定最下方，见下方输出逻辑）
    $__order = ['contract', 'approval', 'customer', 'project', 'finance', 'invoice', 'library', 'admin'];
} else {
    // 普通员工：合同→客户→审批→项目→我的回款→资料库
    $__order = ['contract', 'customer', 'approval', 'project', 'my_finance', 'library', 'admin'];
}
// 系统设置固定最下方：无论角色排序如何，admin 块一律最后输出
foreach ($__order as $__key) {
    if ($__key === 'admin') {
        continue;
    }
    if (isset($__blocks[$__key])) {
        echo $__blocks[$__key];
    }
}
if (isset($__blocks['admin'])) {
    echo $__blocks['admin'];
}
?>
<hr class="mx-2"><div class="small text-muted px-3 pb-3">合同管理系统 <?=app_version(); ?></div>
