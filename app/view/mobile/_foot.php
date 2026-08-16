<?php
// +----------------------------------------------------------------------
// | 移动端共享尾部（B1 重构：使用 mobile_tabbar() 统一底部导航）
// | 使用方式：include _head.php 前设置 $title，include _foot.php 前设置 $tab
// |   $tab — 对齐 mobile_tabbar() 的 key：home/contract/customer/more
// |   设为空字符串或不设则底部无导航栏
// +----------------------------------------------------------------------

$activeTab = $tab ?? '';
$showAddTab = !empty($show_add_tab);
$addUrl = (string)($add_url ?? '');
echo mobile_tabbar($activeTab, $showAddTab, $addUrl);
if (!empty($showAddTab) && empty($render_add_menu_here)): ?>
<style>
  .m-global-fab-mask{position:fixed;inset:0;background:rgba(15,23,42,.52);opacity:0;visibility:hidden;z-index:70;transition:opacity .2s}
  .m-global-fab-menu{position:fixed;left:50%;bottom:calc(var(--m-tabbar-h) + var(--safe-bottom) + 12px);z-index:72;display:flex;gap:8px;width:min(360px,calc(100vw - 32px));padding:12px 10px;background:#fff;border-radius:18px;box-shadow:0 8px 28px rgba(0,0,0,.2);opacity:0;visibility:hidden;transform:translate(-50%,8px);transition:opacity .2s,transform .2s}
  .m-global-fab-menu a{flex:1;display:flex;flex-direction:column;align-items:center;gap:5px;padding:10px 4px;background:#f7f9fc;border-radius:12px;color:#1f2329;font-size:12px}.m-global-fab-menu i{font-size:22px;color:var(--m-brand)}
  body.fab-open .m-global-fab-mask{opacity:1;visibility:visible} body.fab-open .m-global-fab-menu{opacity:1;visibility:visible;transform:translate(-50%,0)}
</style>
<div class="m-global-fab-mask" id="globalFabMask"></div><div class="m-global-fab-menu" id="globalFabMenu">
  <?php if(!empty($can_create_contract)): ?><a href="/m/contract/create"><i class="bi bi-file-earmark-plus"></i><span>新建合同</span></a><?php endif; ?>
  <?php if(!empty($can_create_customer)): ?><a href="/m/customer/create"><i class="bi bi-person-plus"></i><span>新建客户</span></a><?php endif; ?>
  <?php if(!empty($can_create_supplier)): ?><a href="/m/supplier/create"><i class="bi bi-building-add"></i><span>新建供应商</span></a><?php endif; ?>
</div><script>(function(){var m=document.getElementById('globalFabMask'),n=document.getElementById('globalFabMenu');if(m)m.onclick=function(){document.body.classList.remove('fab-open')};if(n)n.onclick=function(e){if(e.target.closest('a'))document.body.classList.remove('fab-open')};})();</script>
<?php endif; ?>

<script src="<?=asset_url('js/app.js')?>"></script>
</body>
</html>
