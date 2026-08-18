<?php $title='提交审批'; $menu_active='approval'; include __DIR__.'/../layout/header.php'; ?>
<style>
/* 移动端适配（A 级）：表单整行、按钮触控友好、流程选择卡片化 */
@media (max-width: 768px) {
  .stat-card { margin-left: 0; margin-right: 0; border-radius: 0; border-left: none; border-right: none; }
  .stat-card .card-body { padding: 14px 16px; }
  #submitForm .form-select { min-height: 48px; font-size: 16px; }
  #submitForm .form-text { font-size: 13px; margin-top: 8px; }
  #submitForm .btn { width: 100%; min-height: 48px; font-size: 16px; }
  #submitForm .btn + .btn { margin: 12px 0 0 !important; }
}
</style>
<h4 class="mb-3"><i class="bi bi-send"></i> 提交审批</h4>
<div class="card stat-card mb-3"><div class="card-header bg-white"><h5 class="mb-0">合同摘要</h5></div><div class="card-body"><p><strong><?=htmlspecialchars($contract['contract_no'])?></strong> — <?=htmlspecialchars($contract['title'])?></p><p>业务类型: <?=htmlspecialchars(dict_enabled('business_type')[$contract['business_type'] ?? ''] ?? ($contract['business_type'] ?? ''))?> | 金额: ¥<?=format_money($contract['amount'])?></p></div></div>
<div class="card stat-card"><div class="card-header bg-white"><h5 class="mb-0">审批节点</h5></div><div class="card-body">
<form id="submitForm"><input type="hidden" name="contract_id" value="<?=$contract['id']?>">
<?php if($matched_flow): ?>
  <ol class="mb-3 ps-3">
  <?php foreach($flow_nodes as $n): ?>
    <li class="mb-1">
      <?=htmlspecialchars($n['name'] ?? '节点')?>
      <?php if(!empty($n['resolved_names'])): ?>
        <!-- v2.51.10：审批人由灰色括号文本统一为蓝底 pc-tag-info（#e8f1ff），与抄送标签一致 -->
        <span class="pc-tag pc-tag-info ms-1"><?=htmlspecialchars(implode('、', $n['resolved_names']))?></span>
      <?php else: ?>
        <span class="text-danger">（<?=htmlspecialchars($n['resolve_warning'] ?? '未指定具体人员')?>）</span>
      <?php endif; ?>
    </li>
  <?php endforeach; ?>
  </ol>
  <?php if(!empty($has_cc)): ?>
  <div class="mb-3">
    <strong class="text-muted">抄送知会：</strong>
    <?php if(!empty($cc_names)): ?><span class="pc-tag pc-tag-info me-1"><?=htmlspecialchars(implode('、', $cc_names))?></span><?php endif; ?>
  </div>
  <?php endif; ?>
  <button type="submit" class="btn btn-warning"><i class="bi bi-send"></i> 确认提交</button>
  <a href="/contract/<?=$contract['id']?>" class="btn btn-outline-secondary ms-2">返回</a>
<?php else: ?>
  <div class="alert alert-danger py-2 mb-3"><i class="bi bi-exclamation-triangle"></i> 未匹配到适用的审批流程，请联系管理员在「系统设置 → 审批流程」中配置。</div>
  <a href="/contract/<?=$contract['id']?>" class="btn btn-outline-secondary">返回</a>
<?php endif; ?>
</form></div></div>
<script>// P0-1：提交防重复锁（双击/连点只创建一次审批实例）+ 网络异常兜底提示
var __approvalSubmitting = false;
document.getElementById('submitForm').addEventListener('submit',function(e){
  e.preventDefault();
  if(__approvalSubmitting) return;
  __approvalSubmitting = true;
  var btn = document.querySelector('#submitForm button[type="submit"]');
  if(btn){ btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>提交中…'; }
  var fd = new FormData(this);
  fetch('/ajax/approval/submit',{method:'POST',body:new URLSearchParams(fd),headers:{'X-Requested-With':'XMLHttpRequest'}})
    .then(function(r){ return r.json(); })
    .then(function(res){
      if(res.code===0){ showToast('提交成功','success'); location.href='/approval/'+res.data.instance_id; return; }
      showToast(res.msg || '提交失败','error');
      __approvalSubmitting = false;
      if(btn){ btn.disabled = false; btn.innerHTML = '<i class="bi bi-send"></i> 确认提交'; }
    })
    .catch(function(){
      showToast('网络异常，请重试','error');
      __approvalSubmitting = false;
      if(btn){ btn.disabled = false; btn.innerHTML = '<i class="bi bi-send"></i> 确认提交'; }
    });
});</script>
<?php include __DIR__.'/../layout/footer.php'; ?>
