<?php
$title = '应收账龄分析';
$menu_active = 'finance';
include __DIR__ . '/../layout/header.php'; ?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4><i class="bi bi-graph-up"></i> 应收账龄分析</h4>
</div>
<?php
// 质量修复：Bootstrap 无 bg-orange 类（61-90 天原 orange 无背景色），改用内联橙色 #fd7e14 保证各版本渲染一致
$colors=['0-30天'=>'secondary','31-60天'=>'warning','61-90天'=>'orange','90天以上'=>'danger'];
$orangeLabel = '61-90天';
?>
<?php foreach($aging as $g): $c=$colors[$g['label']]??'secondary'; $isOrange = ($g['label']===$orangeLabel); ?>
<div class="card mb-3">
  <div class="card-header d-flex justify-content-between">
    <span><span class="badge me-2 <?=$isOrange?'':'bg-'.$c?>" <?=$isOrange?'style="background:#fd7e14;color:#fff"':''?>><?=$g['label']?></span> <?=$g['count']?> 笔</span>
    <strong class="<?=$isOrange?'text-warning':('text-'.$c)?>">¥<?=number_format($g['total'],0)?></strong>
  </div>
  <div class="card-body p-0">
    <table class="table table-sm mb-0"><tbody>
    <?php foreach($g['items'] as $i): ?>
      <tr><td><?=htmlspecialchars($i['title'])?></td><td class="text-end">¥<?=number_format($i['amount'],0)?></td><td class="text-muted small text-end"><?=$i['planned_date']?></td></tr>
    <?php endforeach; ?>
    <?php if(empty($g['items'])): ?><tr><td class="text-muted text-center py-3" colspan="3">无逾期记录</td></tr><?php endif; ?>
    </tbody></table>
  </div>
</div>
<?php endforeach; ?>
<div class="text-muted small mt-2">* 仅显示你有权限查看的合同回款 · 逾期天数按计划回款日期计算</div>
<?php include __DIR__ . '/../layout/footer.php'; ?>
