<?php $title='审批详情'; $menu_active='approval'; include __DIR__.'/../layout/header.php'; ?>
<?php
$nodes = json_decode($detail['nodes'] ?? '[]', true) ?: [];
$curOrderP = (int)($detail['current_node_order'] ?? 0);
// v2.40.1：按 node_order 聚合审批记录，供步骤条（节点状态/审批人）与记录时间线复用
$recByNodeP = [];
$doneCountP = 0;
foreach (($detail['records'] ?? []) as $rp) {
    $recByNodeP[(int)($rp['node_order'] ?? 1)][] = $rp;
    if (in_array($rp['action'] ?? '', ['APPROVED', 'AUTO_APPROVED'], true)) { $doneCountP++; }
}
$totalNodesP = count($nodes);
?>
<style>.apv-current{box-shadow:0 0 0 4px rgba(13,110,253,.18)}</style>
<h4 class="mb-3"><i class="bi bi-check2-circle"></i> 审批详情</h4>
<div class="card stat-card mb-3"><div class="card-header bg-white"><h5 class="mb-0"><?=htmlspecialchars($detail['flow_name'])?> — <?=htmlspecialchars($detail['contract_title'])?><?php if($totalNodesP > 0): ?> <small class="text-muted ms-2" style="font-size:13px">进度 <?=$doneCountP?>/<?=$totalNodesP?></small><?php endif; ?></h5></div><div class="card-body">
<p>合同编号: <strong><?=htmlspecialchars($detail['contract_no'])?></strong> | 金额: <strong>¥<?=format_money($detail['amount'])?></strong></p>
<p>提交人: <?=htmlspecialchars($detail['submitter_name'])?> | 状态: <?=approval_status_label($detail['status'])?></p></div></div>
<?php if(!empty($nodes)): ?>
<div class="card stat-card mb-3"><div class="card-body">
  <div class="d-flex align-items-start">
  <?php $prevDoneP = false; foreach($nodes as $iP => $nP):
    $orderP = $iP + 1;
    $lastP  = ($recByNodeP[$orderP] ?? []) ? end($recByNodeP[$orderP]) : null;
    $actP   = $lastP['action'] ?? 'PENDING';
    $isDoneP = in_array($actP, ['APPROVED', 'AUTO_APPROVED'], true);
    $isRejP  = $actP === 'REJECTED';
    $isCurP  = ($orderP === $curOrderP) && ($detail['status'] ?? '') === 'PENDING';
    $nodeCls  = $isRejP ? 'bg-danger' : ($isDoneP ? 'bg-success' : ($isCurP ? 'bg-primary apv-current' : 'bg-secondary'));
    $nodeIcon = $isRejP ? '✗' : ($isDoneP ? '✓' : $orderP);
    $whoP     = $lastP ? ($lastP['approver_name'] ?? '待审批') : ($isCurP ? '审批中' : '待审批');
    // v2.40.1：节点状态标签（对齐移动端时间线语义）
    $tagClsP = 'pc-tag-muted'; $tagTxtP = '待审批';
    if ($isRejP)      { $tagClsP = 'pc-tag-danger'; $tagTxtP = '已驳回'; }
    elseif ($isDoneP) { $tagClsP = 'pc-tag-ok';     $tagTxtP = $actP === 'AUTO_APPROVED' ? '自动通过' : '已同意'; }
    elseif ($actP === 'TRANSFERRED') { $tagClsP = 'pc-tag-warn'; $tagTxtP = '已转交'; }
    elseif ($isCurP)  { $tagClsP = 'pc-tag-info';   $tagTxtP = '审批中'; }
  ?>
    <?php if($iP > 0): ?><div class="flex-grow-1 align-self-center" style="height:3px;border-radius:2px;margin:0 6px;background:<?=$prevDoneP?'#198754':'#dee2e6'?>"></div><?php endif; ?>
    <div class="text-center flex-fill" style="min-width:0">
      <div class="rounded-circle <?=$nodeCls?> text-white mx-auto mb-1 d-flex align-items-center justify-content-center" style="width:36px;height:36px;font-size:14px;font-weight:600"><?=$nodeIcon?></div>
      <small class="d-block text-truncate"><?=htmlspecialchars($nP['name'])?></small>
      <small class="text-muted d-block"><?=htmlspecialchars($whoP)?></small>
      <small class="d-block mt-1"><span class="pc-tag <?=$tagClsP?>"><?=$tagTxtP?></span></small>
    </div>
  <?php $prevDoneP = $isDoneP; endforeach; ?>
  </div>
</div></div>
<?php endif; ?>
<div class="card stat-card mb-3"><div class="card-header bg-white"><h5 class="mb-0">审批记录</h5></div><div class="card-body py-2">
<?php if(!empty($detail['records']) || !empty($detail['cc_log'])): foreach($detail['records'] as $r):
  $dotP = ['APPROVED'=>'#198754','AUTO_APPROVED'=>'#198754','REJECTED'=>'#dc3545','TRANSFERRED'=>'#ffc107','PENDING'=>'#0d6efd'][$r['action'] ?? 'PENDING'] ?? '#6c757d';
?>
<div class="d-flex justify-content-between border-bottom align-items-start" style="position:relative;padding:8px 16px 8px 28px">
  <span class="rounded-circle" style="position:absolute;left:8px;top:17px;width:12px;height:12px;background:<?=$dotP?>"></span>
  <div><strong><?=htmlspecialchars($r['approver_name'])?></strong> <small class="text-muted">(<?=htmlspecialchars($r['node_name'])?>)</small><?php if($r['comment']):?><br><small class="text-muted">意见：<?=htmlspecialchars($r['comment'])?></small><?php endif;?></div>
  <div class="text-end"><small class="text-muted d-block"><?=htmlspecialchars($r['acted_at']??'')?></small> <?=approval_action_label($r['action'])?></div>
</div>
<?php endforeach; ?>
<?php if(!empty($detail['cc_log'])): ?>
<div class="p-2 border-bottom small text-muted"><i class="bi bi-envelope me-1"></i>抄送知会（<?=count($detail['cc_log'])?> 人）</div>
<?php foreach($detail['cc_log'] as $c): ?>
<div class="d-flex justify-content-between p-2 border-bottom"><div><strong><?=htmlspecialchars($c['user_name'] ?? '抄送人')?></strong> <small class="text-muted">(抄送知会)</small></div><div><small class="text-muted"><?=htmlspecialchars($c['created_at']??'')?></small> 抄送</div></div>
<?php endforeach; ?>
<?php endif; ?>
<?php else: ?><p class="text-muted mb-0">暂无审批记录</p><?php endif; ?></div></div>
<?php if($can_act && $detail['status']=='PENDING'): ?>
<div class="card stat-card"><div class="card-body d-flex gap-2">
<button class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#approveModal"><i class="bi bi-check-lg"></i> 同意</button>
<button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#rejectModal"><i class="bi bi-x-lg"></i> 驳回</button>
<button class="btn btn-outline-info" data-bs-toggle="modal" data-bs-target="#transferModal"><i class="bi bi-arrow-right-circle"></i> 转交</button></div></div>
<!-- 同意确认弹窗（v2.38.1：白底卡片，消除原生 alert 暗色不协调） -->
<div class="modal fade" id="approveModal"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">确认通过</h5><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><p class="mb-0">确定<strong>同意</strong>该审批吗？通过后将流转至下一个审批节点或完成审批。</p></div><div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">取消</button><button class="btn btn-success" onclick="doApprove()">确认同意</button></div></div></div></div>
<div class="modal fade" id="rejectModal"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">驳回</h5><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><textarea class="form-control" id="rc" rows="3" placeholder="驳回意见（选填）"></textarea><select class="form-select form-select-sm mt-2" id="rejectTo"><option value="0">驳回回起点（重新提交）</option><?php for($ro=1;$ro<(int)$detail['current_node_order'];$ro++): ?><option value="<?=$ro?>">驳回到节点<?=$ro?>：<?=htmlspecialchars($nodes[$ro-1]['name']??('节点'.$ro))?></option><?php endfor; ?></select><small class="text-muted">可选驳回到指定前序节点，重新从该节点审批；意见可留空</small></div><div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">取消</button><button class="btn btn-outline-danger" onclick="act('REJECTED')">确认驳回</button></div></div></div></div>
<!-- 转交弹窗（与移动端同源：transfer-targets 接口搜索 + 选人） -->
<div class="modal fade" id="transferModal"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">转交审批</h5><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><p class="text-muted small mb-2">选择转交人，提交后审批将转交给对方处理。</p><input class="form-control form-control-sm mb-2" id="tkw" placeholder="搜索姓名…" oninput="loadTransferTargets(this.value)"><div id="tlist" style="max-height:220px;overflow:auto"><?php foreach($transfer_users as $u): ?><label class="d-flex align-items-center gap-2 border-bottom py-1 mb-0" style="cursor:pointer"><input type="radio" name="transferTo" value="<?=intval($u['id'])?>"><span><?=htmlspecialchars($u['name'])?></span></label><?php endforeach; ?></div><textarea class="form-control mt-2" id="tc" rows="2" placeholder="转交说明（选填）"></textarea></div><div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">取消</button><button class="btn btn-primary" onclick="doTransfer()">确认转交</button></div></div></div></div>
<?php elseif($can_recall && $detail['status']=='PENDING'): ?>
<div class="card stat-card"><div class="card-body d-flex gap-2">
<button class="btn btn-outline-warning" onclick="doRecall()"><i class="bi bi-arrow-counterclockwise"></i> 撤回审批</button></div></div>
<?php endif; ?>
<script>// 质量修复：__acting 防重复提交（双击同意/驳回只发一次请求），所有出口重置；补 .catch 处理网络异常
var __acting=false;
function act(a){
  if(__acting){return;}
  __acting=true;
  var c=a==='REJECTED'?document.getElementById('rc').value:'';
  var body=new URLSearchParams({action:a,comment:c});
  if(a==='REJECTED'){var rt=document.getElementById('rejectTo');if(rt)body.append('reject_to_order',rt.value);}
  var hd={'X-Requested-With':'XMLHttpRequest'};
  var t=(document.cookie.match(/(?:^|; )csrf_token=([^;]*)/)||[])[1]||'';
  if(t){hd['X-CSRF-TOKEN']=t;}
  fetch('/ajax/approval/<?=$detail['id']?>/action',{method:'POST',body:body,headers:hd})
    .then(r=>r.json())
    .then(res=>{__acting=false;showToast(res.msg,res.code===0?'success':'error');if(res.code===0)setTimeout(function(){location.reload();},800);})
    .catch(function(){__acting=false;showToast('网络异常，请重试','error');});
}
function doApprove(){bootstrap.Modal.getInstance('#approveModal').hide();act('APPROVED');}
function doTransfer(){
  if(__acting){return;}
  var sel=document.querySelector('#transferModal input[name="transferTo"]:checked');
  if(!sel){showToast('请选择转交人','error');return;}
  __acting=true;
  var body=new URLSearchParams({action:'TRANSFERRED',comment:document.getElementById('tc').value,transfer_to:sel.value});
  var hd={'X-Requested-With':'XMLHttpRequest'};
  var t=(document.cookie.match(/(?:^|; )csrf_token=([^;]*)/)||[])[1]||'';
  if(t){hd['X-CSRF-TOKEN']=t;}
  fetch('/ajax/approval/<?=$detail['id']?>/action',{method:'POST',body:body,headers:hd})
    .then(r=>r.json())
    .then(res=>{__acting=false;bootstrap.Modal.getInstance('#transferModal').hide();showToast(res.msg,res.code===0?'success':'error');if(res.code===0)setTimeout(function(){location.reload();},800);})
    .catch(function(){__acting=false;showToast('网络异常，请重试','error');});
}
function doRecall(){
  // P2-3：撤回前二次确认（误触成本高）
  pcConfirm({message:'确认撤回该审批？撤回后需重新提交审批。',danger:true}).then(function(ok){
    if(!ok) return;
    if(__acting){return;}
    __acting=true;
    var hd={'X-Requested-With':'XMLHttpRequest'};
    var t=(document.cookie.match(/(?:^|; )csrf_token=([^;]*)/)||[])[1]||'';
    if(t){hd['X-CSRF-TOKEN']=t;}
    fetch('/ajax/approval/<?=$detail['id']?>/recall',{method:'POST',headers:hd})
      .then(r=>r.json())
      .then(res=>{__acting=false;showToast(res.msg,res.code===0?'success':'error');if(res.code===0)setTimeout(function(){location.reload();},800);})
      .catch(function(){__acting=false;showToast('网络异常，请重试','error');});
  });
}
function loadTransferTargets(kw){
  var list=document.getElementById('tlist');if(!list)return;
  fetch('/ajax/approval/transfer-targets?kw='+encodeURIComponent(kw||'')+'&page=1')
    .then(r=>r.json())
    .then(res=>{
      var arr=(res.data&&res.data.list)||[];
      list.innerHTML=arr.map(function(u){return '<label class="d-flex align-items-center gap-2 border-bottom py-1 mb-0" style="cursor:pointer"><input type="radio" name="transferTo" value="'+u.id+'"><span>'+esc(u.name)+'</span></label>';}).join('')||'<p class="text-muted small mb-0">未找到匹配用户</p>';
    })
    .catch(function(){});
}</script>
<?php include __DIR__.'/../layout/footer.php'; ?>

