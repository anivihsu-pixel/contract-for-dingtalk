<?php $title='项目详情'; $menu_active='project'; include __DIR__.'/../layout/header.php'; ?>
<?php
$badgeMap=['ACTIVE'=>'ok','DONE'=>'muted','ARCHIVED'=>'muted','TERMINATED'=>'danger'];
$stBg=$badgeMap[$project['status']]??'muted';
$dirName=['sales'=>'销售(收款)','purchase'=>'采购(付款)','' => '非交易'];
// v2.40.0 P1-6：执行阶段标签
$__stageDict=['PLANNING'=>'筹备','EXECUTING'=>'执行中','ACCEPTANCE'=>'验收中','COMPLETED'=>'已完结'];
$__stageCls=['PLANNING'=>'pc-tag-muted','EXECUTING'=>'pc-tag-info','ACCEPTANCE'=>'pc-tag-warn','COMPLETED'=>'pc-tag-ok'];
$__stage=$project['stage']??'PLANNING';
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <h4 class="mb-0"><i class="bi bi-kanban"></i> <?=htmlspecialchars($project['name']??'')?>
    <span class="pc-tag pc-tag-<?=$stBg?> ms-2"><?=htmlspecialchars($statusDict[$project['status']]??$project['status'])?></span>
    <span class="pc-tag <?=$__stageCls[$__stage]??'pc-tag-muted'?> ms-1"><?=$__stageDict[$__stage]??$__stage?></span></h4>
  <div><?php if(!empty($can_edit) && $__stage !== 'COMPLETED'): ?><button type="button" class="btn btn-success btn-sm me-1" onclick="acceptProject()"><i class="bi bi-check2-circle"></i> 标记验收完成</button><?php endif; ?>
<?php if(!empty($can_edit) && $project['status'] === 'ACTIVE' && $__stage !== 'COMPLETED'): ?><button type="button" class="btn btn-outline-danger btn-sm me-1" onclick="terminateProject()"><i class="bi bi-x-circle"></i> 终止项目</button><?php endif; ?>
<?php if(!empty($can_edit) && $project['status'] === 'TERMINATED'): ?><button type="button" class="btn btn-outline-success btn-sm me-1" onclick="restoreProject()"><i class="bi bi-arrow-counterclockwise"></i> 撤销终止</button><?php endif; ?>
<a href="/project/<?=$project['id']?>/edit" class="btn btn-primary btn-sm"><i class="bi bi-pencil"></i> 编辑</a> <a href="/project" class="btn btn-outline-secondary btn-sm">返回</a></div>
</div>

<!-- 经营聚合卡片（仅统计交易合同 trade_attr=1） -->
<div class="row g-3 mb-3 row-cols-2 row-cols-md-3 row-cols-xl-5">
  <div class="col"><div class="card stat-card h-100"><div class="card-body text-center"><div class="text-muted small">交易合同数</div><div class="fs-4 fw-bold"><?=$stat['contract_count']?></div></div></div></div>
  <div class="col"><div class="card stat-card h-100"><div class="card-body text-center"><div class="text-muted small">销售合同额</div><div class="fs-5 fw-bold text-danger">¥<?=number_format($stat['sales_amount'],0)?></div></div></div></div>
  <div class="col"><div class="card stat-card h-100"><div class="card-body text-center"><div class="text-muted small">项目毛利（毛利率）</div><div class="fs-5 fw-bold <?=$stat['gross_margin']>=0?'text-success':'text-danger'?>">¥<?=number_format($stat['gross_margin'],0)?> <span class="fs-6 text-muted">(<?=$stat['gross_margin_rate']?>%)</span></div></div></div></div>
  <div class="col"><div class="card stat-card h-100"><div class="card-body text-center"><div class="text-muted small">应收 / 已收</div><div class="fs-6 fw-bold">¥<?=number_format($stat['receivable'],0)?> <span class="text-muted">/ ¥<?=number_format($stat['received'],0)?></span></div></div></div></div>
  <div class="col"><div class="card stat-card h-100"><div class="card-body text-center"><div class="text-muted small">回款率</div><div class="fs-4 fw-bold text-success"><?=$stat['recovery_rate']?>%</div></div></div></div>
</div>

<div class="card stat-card mb-3"><div class="card-body"><table class="table table-sm mb-0"><tbody>
<tr><td class="text-muted" width="110">项目编号</td><td><?=htmlspecialchars($project['code']?:'-')?></td><td class="text-muted" width="110">项目预算</td><td><?=$project['budget']>0?'¥'.number_format($project['budget'],0):'-'?></td></tr>
<tr><td class="text-muted">开始日期</td><td><?=htmlspecialchars($project['start_date']?:'-')?></td><td class="text-muted">结束日期</td><td><?=htmlspecialchars($project['end_date']?:'-')?></td></tr>
<tr><td class="text-muted">执行进度</td><td colspan="3"><div class="d-flex align-items-center gap-2"><div class="progress flex-grow-1" style="height:8px;max-width:320px"><div class="progress-bar" role="progressbar" style="width:<?=max(0,min(100,(int)($project['progress']??0)))?>%"></div></div><span class="small text-muted"><?=(int)($project['progress']??0)?>%</span></div></td></tr>
<tr><td class="text-muted">采购合同额</td><td>¥<?=number_format($stat['purchase_amount'],0)?></td><td class="text-muted">备注</td><td><?=nl2br(htmlspecialchars($project['remark']?:'-'))?></td></tr>
</tbody></table></div></div>

<div class="card stat-card"><div class="card-header bg-white d-flex justify-content-between align-items-center"><h6 class="mb-0"><i class="bi bi-file-text"></i> 关联合同（<?=($contract_total ?? count($contracts))?>）</h6>
<?php if(($contract_total ?? 0) > ($contract_limit ?? 0) && ($contract_limit ?? 0) > 0): ?>
<a class="btn btn-sm btn-outline-primary" href="/contract?project_id=<?=$project['id']?>">查看全部 <?=$contract_total?> 条</a>
<?php endif; ?>
</div>
<div class="table-responsive"><table class="table table-hover mb-0"><thead class="table-light"><tr><th>合同编号</th><th>标题</th><th>方向</th><th>金额</th><th>状态</th></tr></thead><tbody>
<?php if(empty($contracts)): ?>
<tr><td colspan="5" class="text-center py-4 text-muted">该项目暂无关联合同</td></tr>
<?php else: foreach($contracts as $c): ?>
<tr>
  <td><a href="/contract/<?=$c['id']?>"><?=htmlspecialchars($c['contract_no'])?></a></td>
  <td><?=htmlspecialchars($c['title'])?></td>
  <td><?php if((int)$c['trade_attr']===0): ?><span class="pc-tag pc-tag-muted">非交易</span><?php else: ?><?=htmlspecialchars($dirName[$c['direction']]??$c['direction'])?><?php endif; ?></td>
  <td><?=(int)$c['trade_attr']===0?'-':'¥'.number_format($c['amount'],0)?></td>
  <td><span class="pc-tag pc-tag-muted"><?=htmlspecialchars($contractStatusDict[$c['status']]??$c['status'])?></span></td>
</tr>
<?php endforeach; endif; ?>
</tbody></table></div>
<?php if(($contract_total ?? 0) > ($contract_limit ?? 0) && ($contract_limit ?? 0) > 0): ?>
<div class="card-footer bg-white text-muted small">仅显示前 <?=$contract_limit?> 条，<a href="/contract?project_id=<?=$project['id']?>">查看全部 <?=$contract_total?> 条合同</a>。</div>
<?php endif; ?>
</div>
<script>
// v2.40.0 P1-6：验收联动
function acceptProject(){
  pcConfirm({message:'确认项目验收完成？\n将联动将项目下执行中/待签约的销售合同标记为已完成。'}).then(function(ok){
    if(!ok) return;
    $ajax('/ajax/project/accept',{method:'POST',body:new URLSearchParams({id:<?=htmlspecialchars($project['id'])?>})}).then(function(res){
      showToast(res.msg||'操作完成',res.code===0?'success':'error');
      if(res.code===0) setTimeout(function(){location.reload();},800);
    }).catch(function(){showToast('网络错误','error');});
  });
}
// v2.44.1：终止项目（联动终止执行中/已通过/历史已签的销售合同）
function terminateProject(){
  pcConfirm({message:'确认终止该项目？\n将联动终止项目下执行中/已通过/历史已签的销售合同，存在逾期未结回款的合同将跳过。'}).then(function(ok){
    if(!ok) return;
    $ajax('/ajax/project/terminate',{method:'POST',body:new URLSearchParams({id:<?=htmlspecialchars($project['id'])?>})}).then(function(res){
      showToast(res.msg||'操作完成',res.code===0?'success':'error');
      if(res.code===0) setTimeout(function(){location.reload();},800);
    }).catch(function(){showToast('网络错误','error');});
  });
}
// v2.44.1：撤销项目终止（恢复进行中；合同状态不联动恢复）
function restoreProject(){
  pcConfirm({message:'确认撤销终止？\n项目将恢复为进行中状态，合同状态不联动恢复。'}).then(function(ok){
    if(!ok) return;
    $ajax('/ajax/project/restore',{method:'POST',body:new URLSearchParams({id:<?=htmlspecialchars($project['id'])?>})}).then(function(res){
      showToast(res.msg||'操作完成',res.code===0?'success':'error');
      if(res.code===0) setTimeout(function(){location.reload();},800);
    }).catch(function(){showToast('网络错误','error');});
  });
}
</script>
<?php include __DIR__.'/../layout/footer.php'; ?>
