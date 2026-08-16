<?php
// +----------------------------------------------------------------------
// | 移动端共享头部（Phase 1 重构 1.7：消除 18 个视图各自的完整 HTML 副本）
// | 使用方式：在视图文件最顶部先定义变量，再 include 本文件：
// |   $title     — 页面标题，自动追加「 · 合同管理」
// |   $tab       — 底部导航高亮 key：home/contract/customer/more（_foot.php 使用；留空则无导航）
// |   $pageStyle — 可选，页面级 <style> 内容（置于 <head> 内，保持合法 HTML）
// |   $extraCss  — 可选，额外样式表 URL（如 index.php 依赖 Bootstrap 工具类）
// | 子视图仅需输出 <body> 内的业务内容，无需重复 <!DOCTYPE>/<head>/<body> 外壳。
// +----------------------------------------------------------------------

$pageTitle = isset($title) ? $title . ' · 合同管理' : '合同管理';
$pageStyle = $pageStyle ?? '';
$extraCss  = $extraCss ?? '';
?><!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="color-scheme" content="light">
<meta name="theme-color" content="#ffffff">
<title><?=htmlspecialchars($pageTitle)?></title>
<link rel="stylesheet" href="<?=asset_url('css/mobile.css')?>">
<link rel="stylesheet" href="<?=asset_url('vendor/bootstrap-icons/bootstrap-icons.v2.48.0.min.css')?>">
<?php if($extraCss): ?><link rel="stylesheet" href="<?=htmlspecialchars($extraCss)?>">
<?php endif; ?>
<script src="<?=asset_url('js/mobile-common.js')?>"></script>
<script src="<?=asset_url('js/attachments.js')?>"></script>
<?php if($pageStyle): ?><style><?=$pageStyle?></style>
<?php endif; ?>
</head>
<body>
