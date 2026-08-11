<?php
// 移动端经营周报（v2.47.0）：钉钉通知/站内信点击直达页
$title = '经营周报';
$tab = $tab ?? 'more';
include __DIR__ . '/_head.php';
$w = $weekly;
$prev = date('Y-m-d', strtotime($w['start'] . ' -7 days'));
$next = date('Y-m-d', strtotime($w['start'] . ' +7 days'));
?>
<div class="m-nav">
  <a href="/m/reports" class="back" aria-label="返回"><i class="bi bi-chevron-left"></i></a>
  <div class="title">经营周报</div>
  <div class="right"></div>
</div>

<!-- 周切换 -->
<div style="display:flex;align-items:center;justify-content:space-between;gap:8px;padding:var(--m-gap) var(--m-gap) 4px;">
  <a href="/m/report/weekly?week=<?=$prev?>" class="m-chip">‹ 上周</a>
  <span style="font-size:12px;color:var(--m-muted)"><?=$w['start']?> ~ <?=$w['end']?></span>
  <a href="/m/report/weekly?week=<?=$next?>" class="m-chip">下周 ›</a>
</div>

<div class="m-page">
  <!-- 全公司概览 -->
  <div class="m-stat-row" style="margin-top:var(--m-gap)">
    <div class="m-stat">
      <div class="n"><?=$w['summary']['contract_cnt']?></div>
      <div class="l">上周新增合同</div>
    </div>
    <div class="m-stat">
      <div class="n" style="font-size:15px">¥<?=number_format((float)$w['summary']['contract_amount'],0)?></div>
      <div class="l">新增金额</div>
    </div>
  </div>
  <div class="m-stat-row">
    <div class="m-stat">
      <div class="n" style="color:var(--m-success)">¥<?=number_format((float)$w['summary']['received'],0)?></div>
      <div class="l">上周回款</div>
    </div>
    <div class="m-stat">
      <div class="n" style="color:var(--m-danger)">¥<?=number_format((float)$w['summary']['overdue'],0)?></div>
      <div class="l">当前逾期（<?=$w['summary']['overdue_cnt']?> 笔）</div>
    </div>
  </div>

  <!-- 各部门 -->
  <div class="m-card">
    <div class="m-card-hd"><span><i class="bi bi-diagram-3 me-1 text-primary"></i>各部门经营</span>
      <span class="m-tag m-tag-info">待审批 <?=$w['summary']['pending']?> 笔</span></div>
    <div class="m-card-bd">
      <?php if(empty($w['departments'])): ?>
        <div style="text-align:center;padding:16px 0;color:var(--m-muted)">上周无经营数据</div>
      <?php else: foreach($w['departments'] as $d): ?>
        <div style="border-bottom:1px solid var(--m-border);padding:10px 0" class="<?=$d!==end($w['departments'])?'':'last'?>">
          <div style="font-weight:500;font-size:14px;margin-bottom:6px"><?=htmlspecialchars($d['dept_name'])?></div>
          <div class="m-row"><div class="main"><div class="t">新增合同</div></div><div class="aside amt"><?=$d['contract_cnt']?> 份 / ¥<?=number_format((float)$d['contract_amount'],0)?></div></div>
          <div class="m-row"><div class="main"><div class="t">上周回款</div></div><div class="aside amt pay-amt in">¥<?=number_format((float)$d['received'],0)?></div></div>
          <div class="m-row" style="border-bottom:none"><div class="main"><div class="t">当前逾期</div></div><div class="aside amt pay-amt" style="color:var(--m-danger)">¥<?=number_format((float)$d['overdue'],0)?> <?=$d['overdue_cnt']>0?'('.$d['overdue_cnt'].' 笔)':''?></div></div>
        </div>
      <?php endforeach; endif; ?>
    </div>
  </div>

  <!-- 上周新增合同 -->
  <div class="m-card">
    <div class="m-card-hd"><span><i class="bi bi-file-earmark-text me-1 text-primary"></i>上周新增合同</span>
      <span class="m-tag m-tag-info"><?=count($w['new_contracts'])?> 份</span></div>
    <div class="m-card-bd">
      <?php if(empty($w['new_contracts'])): ?>
        <div style="text-align:center;padding:16px 0;color:var(--m-muted)">上周无新增生效合同</div>
      <?php else: foreach($w['new_contracts'] as $c): ?>
        <div class="m-row"><div class="main"><a href="/contract/<?=$c['id']?>" style="color:var(--m-link,#4B3FE3)"><?=htmlspecialchars($c['title'])?></a><div class="s"><?=htmlspecialchars($c['dept_name'])?> · <?=htmlspecialchars((string)($c['effective_date']??''))?></div></div><div class="aside amt">¥<?=number_format((float)$c['amount'],0)?></div></div>
      <?php endforeach; endif; ?>
    </div>
  </div>

  <!-- 逾期合同 -->
  <div class="m-card">
    <div class="m-card-hd"><span><i class="bi bi-exclamation-triangle me-1" style="color:var(--m-danger)"></i>逾期合同</span>
      <span class="m-tag m-tag-info"><?=count($w['overdue_payments'])?> 笔</span></div>
    <div class="m-card-bd">
      <?php if(empty($w['overdue_payments'])): ?>
        <div style="text-align:center;padding:16px 0;color:var(--m-muted)">暂无逾期回款</div>
      <?php else: foreach($w['overdue_payments'] as $o): ?>
        <div class="m-row"><div class="main"><a href="/contract/<?=$o['contract_id']?>" style="color:var(--m-danger)"><?=htmlspecialchars($o['title'])?></a><div class="s"><?=htmlspecialchars($o['dept_name'])?> · 计划 <?=$o['planned_date']?></div></div><div class="aside amt" style="color:var(--m-danger)">¥<?=number_format((float)$o['amount'],0)?></div></div>
      <?php endforeach; endif; ?>
    </div>
  </div>
</div>
<?php include __DIR__ . '/_foot.php'; ?>
