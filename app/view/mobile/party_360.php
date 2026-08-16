<?php
// 相对方往来全景移动详情（v2.38.11 原生移动版，v2.38.14 更名：概要 + 收支汇总 + 关联合同 + 最近动态）
$title = '往来全景';
$tab = '';
include __DIR__ . '/_head.php';

$base   = $d['base'] ?? [];
$stats  = $d['stats'] ?? [];
$contracts = $d['contracts'] ?? [];
$payments  = $d['payments'] ?? [];
$invoices  = $d['invoices'] ?? [];
$activity  = $d['activity'] ?? [];
$role   = $stats['role'] ?? '应收';
$isCust = ($d['type'] ?? '') === 'customer';
$dirWord = $isCust ? '收' : '付';
$dirFull = $isCust ? '应收' : '应付';
// 合同状态标签（复用 mobile contract_detail 的状态色映射）
$stMap = [
    'DRAFT'=>'m-tag-warn','PENDING_APPROVAL'=>'m-tag-warn',
    'REJECTED'=>'m-tag-danger','EXECUTING'=>'m-tag-ok',
    'COMPLETED'=>'m-tag-ok','TERMINATED'=>'m-tag-muted','EXPIRED'=>'m-tag-warn','ARCHIVED'=>'m-tag-muted',
];
$stText = [
    'DRAFT'=>'草稿','PENDING_APPROVAL'=>'待审批','REJECTED'=>'已驳回',
    'EXECUTING'=>'执行中','COMPLETED'=>'已完成','TERMINATED'=>'已终止',
    'EXPIRED'=>'已到期','ARCHIVED'=>'已归档',
];
$fmt = function($v){ return number_format((float)$v, 0); };
?>

<div class="m-nav">
  <a href="/m/party" class="back" aria-label="返回"><i class="bi bi-chevron-left"></i></a>
  <div class="title">往来全景</div>
  <div class="right"><?=htmlspecialchars($base['name'] ?? '')?></div>
</div>

<div style="padding:var(--m-gap);">

  <!-- 概要 -->
  <div class="m-card">
    <div class="m-card-bd">
      <div class="m-row" style="border-bottom:none">
        <div class="pic"><i class="bi bi-<?=$isCust?'people':'truck'?>"></i></div>
        <div class="main">
          <div class="t"><?=htmlspecialchars($base['name'] ?? '')?></div>
          <div class="s"><?=$isCust?'客户':'供应商'?> · <?=$dirFull?>往来</div>
        </div>
      </div>
      <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px">
        <?php if(!empty($base['contact_name'])): ?><span class="m-tag m-tag-muted">联系人 <?=htmlspecialchars($base['contact_name'])?></span><?php endif; ?>
        <?php if(!empty($base['contact_mobile'])): ?><span class="m-tag m-tag-muted"><?=phone_link($base['contact_mobile'], false)?></span><?php endif; ?>
        <?php if(!empty($base['credit_code'])): ?><span class="m-tag m-tag-muted">税号 <?=htmlspecialchars($base['credit_code'])?></span><?php endif; ?>
      </div>
    </div>
  </div>

  <!-- 收支汇总 -->
  <div class="m-card">
    <div class="m-card-hd"><span><i class="bi bi-cash-coin me-1 text-primary"></i><?=$dirFull?>汇总</span></div>
    <div class="m-card-bd">
      <div style="display:flex;flex-wrap:wrap;text-align:center">
        <div style="width:50%;padding:6px 0"><div class="m-stat-n" style="font-size:20px;font-weight:600;color:var(--m-text-1)"><?=$stats['contract_count'] ?? 0?></div><div class="m-stat-l" style="font-size:12px;color:var(--m-text-3)">关联合同</div></div>
        <div style="width:50%;padding:6px 0"><div class="m-stat-n" style="font-size:20px;font-weight:600;color:var(--m-brand)">¥<?=$fmt($stats['total_amount'] ?? 0)?></div><div class="m-stat-l" style="font-size:12px;color:var(--m-text-3)"><?=$dirFull?>总额</div></div>
        <div style="width:50%;padding:6px 0"><div class="m-stat-n" style="font-size:20px;font-weight:600;color:var(--m-ok)">¥<?=$fmt($stats['received_paid'] ?? 0)?></div><div class="m-stat-l" style="font-size:12px;color:var(--m-text-3)">已<?=$dirWord?></div></div>
        <div style="width:50%;padding:6px 0"><div class="m-stat-n" style="font-size:20px;font-weight:600;color:<?=($stats['balance'] ?? 0) > 0 ? 'var(--m-warn)' : 'var(--m-text-1)'?>">¥<?=$fmt($stats['balance'] ?? 0)?></div><div class="m-stat-l" style="font-size:12px;color:var(--m-text-3)">待<?=$dirWord?>余额</div></div>
      </div>
    </div>
  </div>

  <!-- 关联合同 -->
  <div class="m-card">
    <div class="m-card-hd"><span><i class="bi bi-file-text me-1 text-primary"></i>关联合同</span><span class="m-tag m-tag-muted"><?=count($contracts)?></span></div>
    <div class="m-card-bd" style="padding:0">
      <?php if(empty($contracts)): ?>
        <div class="m-empty" style="padding:20px 0"><i class="bi bi-file-text"></i>暂无关联合同</div>
      <?php else: foreach($contracts as $c): ?>
        <a href="/m/contract/<?=$c['id']?>" class="m-row" style="padding:12px var(--m-pad);border-bottom:1px solid #f2f3f5">
          <div class="main">
            <div class="t" style="font-size:14px"><?=htmlspecialchars($c['title'])?></div>
            <div class="s"><?=!empty($c['project_name'])?htmlspecialchars($c['project_name']).' · ':''?><?=htmlspecialchars($c['contract_no'] ?? '')?></div>
          </div>
          <div class="aside" style="text-align:right">
            <div style="font-weight:600;color:var(--m-text-1)">¥<?=$fmt($c['amount'] ?? 0)?></div>
            <span class="m-tag <?=$stMap[$c['status']] ?? 'm-tag-muted'?>" style="font-size:11px"><?=$stText[$c['status']] ?? $c['status']?></span>
          </div>
        </a>
      <?php endforeach; endif; ?>
    </div>
  </div>

  <!-- 最近动态 -->
  <?php if(!empty($activity)): ?>
  <div class="m-card">
    <div class="m-card-hd"><span><i class="bi bi-clock-history me-1 text-primary"></i>最近动态</span></div>
    <div class="m-card-bd" style="padding:0">
      <?php foreach($activity as $a): ?>
        <div class="m-row" style="padding:10px var(--m-pad);border-bottom:1px solid #f2f3f5">
          <div class="main">
            <!-- v2.38.11：动态显示动作中文标签 + 合同号（audit_log.content 是 JSON 详情，直接显示为乱码） -->
            <div class="s"><span class="m-tag m-tag-info" style="font-size:11px"><?=htmlspecialchars(audit_action_label($a['action'] ?? ''))?></span> 合同 <?=htmlspecialchars($a['contract_title'] ?? ('#'.(int)($a['target_id'] ?? 0)))?></div>
            <div class="s" style="font-size:11px;color:var(--m-text-3)"><?=htmlspecialchars($a['created_at'] ?? '')?></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

</div>

<?php include __DIR__ . '/_foot.php'; ?>
