<?php
// 移动端"更多"聚合页（导航优化 Phase1：浏览型模块收进固定入口，消除二级页无高亮）
$title = '更多';   // 页面标题，自动追加「 · 合同管理」
$tab = 'more';     // 底部导航高亮：home/contract/customer/more
$show_add_tab = !empty($can_create_contract) || !empty($can_create_customer) || !empty($can_create_supplier);
include __DIR__ . '/_head.php';
?>
<style>
  .more-fab-mask{position:fixed;inset:0;background:rgba(15,23,42,.52);opacity:0;visibility:hidden;z-index:70;transition:opacity .2s}
  .more-fab-menu{position:fixed;left:50%;bottom:calc(var(--m-tabbar-h) + var(--safe-bottom) + 12px);z-index:72;display:flex;gap:8px;width:min(360px,calc(100vw - 32px));padding:12px 10px;background:#fff;border-radius:18px;box-shadow:0 8px 28px rgba(0,0,0,.2);opacity:0;visibility:hidden;transform:translate(-50%,8px);transition:opacity .2s,transform .2s}
  .more-fab-menu a{flex:1;display:flex;flex-direction:column;align-items:center;gap:5px;padding:10px 4px;background:#f7f9fc;border-radius:12px;color:#1f2329;font-size:12px}.more-fab-menu i{font-size:22px;color:var(--m-brand)}
  body.fab-open .more-fab-mask{opacity:1;visibility:visible} body.fab-open .more-fab-menu{opacity:1;visibility:visible;transform:translate(-50%,0)}
</style>
<?php if($show_add_tab): ?><div class="more-fab-mask" id="moreFabMask"></div><div class="more-fab-menu" id="moreFabMenu">
  <?php if(!empty($can_create_contract)): ?><a href="/m/contract/create"><i class="bi bi-file-earmark-plus"></i><span>新建合同</span></a><?php endif; ?>
  <?php if(!empty($can_create_customer)): ?><a href="/m/customer/create"><i class="bi bi-person-plus"></i><span>新建客户</span></a><?php endif; ?>
  <?php if(!empty($can_create_supplier)): ?><a href="/m/supplier/create"><i class="bi bi-building-add"></i><span>新建供应商</span></a><?php endif; ?>
</div><script>(function(){var m=document.getElementById('moreFabMask'),n=document.getElementById('moreFabMenu');if(m)m.onclick=function(){document.body.classList.remove('fab-open')};if(n)n.onclick=function(e){if(e.target.closest('a'))document.body.classList.remove('fab-open')};})();</script><?php endif; ?>

<div class="m-nav">
  <a href="/m" class="back" aria-label="返回"><i class="bi bi-chevron-left"></i></a>
  <div class="title">更多</div>
  <div class="right"></div>
</div>

<div class="m-page" id="page">
  <div class="m-card">
    <div class="m-card-bd">
      <div class="palace-grid">
        <?php foreach ($modules as $m): ?>
        <a href="<?=$m[0]?>" class="palace-item"><?php if($m[0]==='/m/handover' && !empty($handover_count)): ?><span class="palace-dot" title="<?=(int)$handover_count?> 人待交接"></span><?php endif; ?><i class="bi <?=$m[1]?>"></i><span><?=htmlspecialchars($m[2], ENT_QUOTES)?></span></a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <div class="m-footer-copyright text-center" style="font-size:11px;color:#a8aeb7;padding:16px 16px 12px;opacity:.9">
    <?=htmlspecialchars($site_copyright ?? '', ENT_QUOTES) ?>
  </div>
</div>

<?php $tab = 'more'; include __DIR__ . '/_foot.php'; ?>
