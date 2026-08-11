<?php
// 移动端"更多"聚合页（导航优化 Phase1：浏览型模块收进固定入口，消除二级页无高亮）
$title = '更多';   // 页面标题，自动追加「 · 合同管理」
$tab = 'more';     // 底部导航高亮：home/contract/customer/more
include __DIR__ . '/_head.php';
?>

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
</div>

<?php $tab = 'more'; include __DIR__ . '/_foot.php'; ?>
