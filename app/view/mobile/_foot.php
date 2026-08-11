<?php
// +----------------------------------------------------------------------
// | 移动端共享尾部（B1 重构：使用 mobile_tabbar() 统一底部导航）
// | 使用方式：include _head.php 前设置 $title，include _foot.php 前设置 $tab
// |   $tab — 对齐 mobile_tabbar() 的 key：home/contract/customer/more
// |   设为空字符串或不设则底部无导航栏
// +----------------------------------------------------------------------

$activeTab = $tab ?? '';
// 页脚版权信息（与 PC 端统一；系统设置「系统配置」页可维护，v2.34.0）
?><div class="m-footer-copyright text-center" style="font-size:11px;color:#a8aeb7;padding:8px 16px 4px;opacity:.9">
  <?=htmlspecialchars($site_copyright ?? '', ENT_QUOTES) ?>
</div><?php
echo mobile_tabbar($activeTab);
?>

<script src="<?=asset_url('js/app.js')?>"></script>
</body>
</html>
