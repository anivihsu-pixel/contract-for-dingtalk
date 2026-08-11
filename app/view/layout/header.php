<!DOCTYPE html><html lang="zh-CN"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light">
<meta name="theme-color" content="#ffffff">
<title><?=$title??'首页'?> - 合同管理系统</title>
<!-- v2.42.0：静态资源本地化（P0 钉钉内网可用性）——弃用 jsDelivr CDN，随包自托管 -->
<link rel="stylesheet" href="<?=asset_url('vendor/bootstrap/css/bootstrap.min.css')?>">
<link rel="stylesheet" href="<?=asset_url('vendor/bootstrap-icons/bootstrap-icons.v2.43.2.min.css')?>">
<link rel="stylesheet" href="<?=asset_url('css/mobile.css')?>">
<link rel="stylesheet" href="<?=asset_url('css/app.css')?>">
<style>
body{padding-top:56px;background:var(--bg-page);overflow-x:hidden}
.sidebar{position:fixed;top:56px;bottom:0;left:0;z-index:90;width:220px;overflow-y:auto;background:#fff;box-shadow:1px 0 4px rgba(0,0,0,.06);padding:12px 0}
.sidebar .nav-link{color:var(--text-main);padding:10px 16px;font-size:14px;border-radius:8px;margin:2px 8px;display:flex;align-items:center;gap:8px}
.sidebar .nav-link:hover{background:var(--brand-light)}
.sidebar .nav-link.active{color:var(--primary);background:var(--brand-light);font-weight:600}
.sidebar .nav-link.sub{padding-left:36px;font-size:13px}
/* 侧边栏子菜单（纯CSS，不依赖Bootstrap collapse） */
.sidebar-sub{display:none}.sidebar-sub.show{display:block}
.sidebar .nav-link[aria-expanded="true"] .bi-chevron-down{transform:rotate(180deg)}
.sidebar .bi-chevron-down{transition:transform .2s}
/* min-width:0 是 flexbox 子项收缩的关键——默认 auto 不允许内容收缩，导致窄屏溢出 */
.main-content{margin-left:220px;padding:20px 24px;min-height:calc(100vh - 56px);min-width:0;overflow-x:hidden}
@media(max-width:767px){
.sidebar{position:relative;top:auto;width:100%;height:auto;display:none}
.sidebar.show{display:block}
.main-content{margin-left:0;padding:12px}
.stat-card{margin-bottom:8px}
.table{font-size:13px}
}
/* 中等屏幕（768-1199px）：钉钉PC内嵌等窄窗场景，收窄侧边栏和内边距 */
@media(min-width:768px) and (max-width:1199.98px){
.sidebar{width:180px}
.main-content{margin-left:180px;padding:16px}
}

/* Mobile bottom nav */
.mobile-nav{position:fixed;bottom:0;left:0;right:0;z-index:99;background:#fff;border-top:1px solid var(--line);display:none;padding:4px 0}
.mobile-nav a{flex:1;text-align:center;color:var(--text-3);font-size:11px;padding:6px 0;text-decoration:none;display:flex;flex-direction:column;align-items:center;gap:2px}
.mobile-nav a.active{color:var(--primary)}
.mobile-nav a i{font-size:18px}
@media(max-width:767px){.mobile-nav{display:flex}}
/* 电话号码交互：tel: 链接 + 复制图标 */
.phone-link{color:var(--primary);text-decoration:none}
.phone-link:hover{text-decoration:underline}
.phone-copy{color:var(--text-muted);cursor:pointer;font-size:12px;margin-left:2px;vertical-align:middle}
.phone-copy:hover{color:var(--primary)}
</style>
</head><body>
<!-- Navbar（P0-1：品牌蓝顶栏 .navbar-app，复刻移动端 m-nav 视觉；替代 Bootstrap bg-primary 默认蓝 #0d6efd） -->
<nav class="navbar navbar-dark navbar-app fixed-top"><div class="container-fluid">
<button class="navbar-toggler d-md-none border-0" onclick="document.querySelector('.sidebar').classList.toggle('show')">
<span class="navbar-toggler-icon"></span></button>
<a class="navbar-brand" href="/dashboard"><i class="bi bi-file-text"></i> 合同管理</a>
<div class="dropdown ms-auto">
<a class="nav-link dropdown-toggle text-white" href="#" data-bs-toggle="dropdown"><i class="bi bi-person-circle"></i> <?=htmlspecialchars($user['name']??'用户')?></a>
<ul class="dropdown-menu dropdown-menu-end">
<li><a class="dropdown-item" href="#" onclick="changePassword()"><i class="bi bi-key"></i> 修改密码</a></li>
<li><hr class="dropdown-divider"></li>
<li><a class="dropdown-item" href="javascript:void(0)" onclick="doLogout()"><i class="bi bi-box-arrow-right"></i> 退出登录</a></li></ul>
</div></div></nav>

<div class="d-flex">
<!-- Sidebar -->
<nav class="sidebar" id="sidebar">
<?php include __DIR__.'/sidebar.php'; ?>
</nav>

<!-- Main -->
<div class="main-content flex-grow-1">
