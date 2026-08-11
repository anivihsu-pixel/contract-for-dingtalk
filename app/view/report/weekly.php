<?php $title='经营周报'; $menu_active = $menu_active ?? 'finance'; include __DIR__.'/../layout/header.php'; ?>
<?php $w=$weekly; $prev=date('Y-m-d',strtotime($w['start'].' -7 days')); $next=date('Y-m-d',strtotime($w['start'].' +7 days')); ?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <h4 class="mb-0"><i class="bi bi-calendar-check"></i> 经营周报</h4>
  <div class="d-flex align-items-center gap-2">
    <a class="btn btn-outline-secondary btn-sm" href="/report/weekly?week=<?=$prev?>"><i class="bi bi-chevron-left"></i> 上周</a>
    <span class="small text-muted"><?=$w['start']?> ~ <?=$w['end']?></span>
    <a class="btn btn-outline-secondary btn-sm" href="/report/weekly?week=<?=$next?>">下周 <i class="bi bi-chevron-right"></i></a>
  </div>
</div>
<p class="text-muted small">按部门汇总上周经营，供周一例会参考。口径与驾驶舱/月报一致：仅交易合同、排除草稿/驳回/审批中与框架合同；逾期/待审批为当前时点。</p>

<!-- 全公司概览 -->
<div class="row g-3 mb-3 row-cols-2 row-cols-md-4">
  <div class="col"><div class="card stat-card h-100"><div class="card-body text-center"><div class="text-muted small">上周新增合同</div><div class="fs-4 fw-bold"><?=$w['summary']['contract_cnt']?> 份</div></div></div></div>
  <div class="col"><div class="card stat-card h-100"><div class="card-body text-center"><div class="text-muted small">新增金额</div><div class="fs-5 fw-bold text-primary">¥<?=number_format((float)$w['summary']['contract_amount'],0)?></div></div></div></div>
  <div class="col"><div class="card stat-card h-100"><div class="card-body text-center"><div class="text-muted small">上周回款</div><div class="fs-5 fw-bold text-success">¥<?=number_format((float)$w['summary']['received'],0)?></div></div></div></div>
  <div class="col"><div class="card stat-card h-100"><div class="card-body text-center"><div class="text-muted small">当前逾期</div><div class="fs-5 fw-bold text-danger">¥<?=number_format((float)$w['summary']['overdue'],0)?> <span class="fs-6 text-muted">(<?=$w['summary']['overdue_cnt']?> 笔)</span></div></div></div></div>
</div>

<!-- 各部门经营 -->
<div class="card stat-card mb-3">
  <div class="card-header bg-white"><h6 class="mb-0"><i class="bi bi-diagram-3 me-1 text-primary"></i> 各部门经营</h6></div>
  <div class="card-body">
    <?php if(empty($w['departments'])): ?>
      <div class="text-center py-4 text-muted">上周无经营数据</div>
    <?php else: ?>
    <div class="row g-3">
      <?php foreach($w['departments'] as $d): ?>
      <div class="col-12 col-md-6 col-xl-4">
        <div class="border rounded p-3 h-100">
          <div class="fw-medium mb-2"><?=htmlspecialchars($d['dept_name'])?></div>
          <table class="table table-sm mb-0">
            <tr><td class="text-muted small">新增合同</td><td class="text-end"><?=$d['contract_cnt']?> 份 / ¥<?=number_format((float)$d['contract_amount'],0)?></td></tr>
            <tr><td class="text-muted small">上周回款</td><td class="text-end text-success">¥<?=number_format((float)$d['received'],0)?></td></tr>
            <tr><td class="text-muted small">当前逾期</td><td class="text-end <?=$d['overdue_cnt']>0?'text-danger':'text-muted'?>">¥<?=number_format((float)$d['overdue'],0)?> <?=$d['overdue_cnt']>0?'('.$d['overdue_cnt'].' 笔)':''?></td></tr>
            <tr><td class="text-muted small">待审批</td><td class="text-end"><?=$d['pending']?> 笔</td></tr>
          </table>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- 上周新增合同 -->
<div class="card stat-card mb-3">
  <div class="card-header bg-white d-flex justify-content-between align-items-center">
    <h6 class="mb-0"><i class="bi bi-file-earmark-text me-1 text-primary"></i> 上周新增合同（<?=count($w['new_contracts'])?>）</h6>
  </div>
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead class="table-light"><tr><th>合同编号</th><th>标题</th><th>金额</th><th>部门</th><th>生效日期</th><th>状态</th></tr></thead>
      <tbody>
      <?php if(empty($w['new_contracts'])): ?>
        <tr><td colspan="6" class="text-center py-4 text-muted">上周无新增生效合同</td></tr>
      <?php else: foreach($w['new_contracts'] as $c): ?>
        <tr>
          <td><a href="/contract/<?=$c['id']?>"><?=htmlspecialchars($c['contract_no'])?></a></td>
          <td><a href="/contract/<?=$c['id']?>"><?=htmlspecialchars($c['title'])?></a></td>
          <td>¥<?=number_format((float)$c['amount'],0)?></td>
          <td><?=htmlspecialchars($c['dept_name'])?></td>
          <td><?=htmlspecialchars((string)($c['effective_date']??''))?></td>
          <td><span class="pc-tag pc-tag-<?=in_array($c['status'],['EXECUTING','SIGNED'],true)?'ok':'muted'?>"><?=htmlspecialchars($contractStatusDict[$c['status']]??$c['status'])?></span></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- 逾期合同 -->
<div class="card stat-card mb-3">
  <div class="card-header bg-white"><h6 class="mb-0"><i class="bi bi-exclamation-triangle me-1 text-danger"></i> 逾期合同（<?=count($w['overdue_payments'])?>）</h6></div>
  <div class="card-body">
    <?php if(empty($w['overdue_payments'])): ?>
      <div class="text-center py-3 text-muted">暂无逾期回款</div>
    <?php else: foreach($w['overdue_payments'] as $o): ?>
      <div class="d-flex justify-content-between align-items-center border-start border-3 border-danger rounded ps-3 py-2 mb-2">
        <div>
          <a href="/contract/<?=$o['contract_id']?>" class="fw-medium"><?=htmlspecialchars($o['title'])?></a>
          <span class="text-muted small ms-2"><?=htmlspecialchars($o['dept_name'])?> · 计划 <?=$o['planned_date']?></span>
        </div>
        <span class="text-danger fw-medium">¥<?=number_format((float)$o['amount'],0)?></span>
      </div>
    <?php endforeach; endif; ?>
  </div>
</div>
<?php include __DIR__.'/../layout/footer.php'; ?>
