<?php $title=$project?'编辑项目':'新建项目'; $menu_active='project'; include __DIR__.'/../layout/header.php'; ?>
<h4 class="mb-3"><i class="bi bi-kanban"></i> <?=$project?'编辑项目':'新建项目'?></h4>
<form id="projectForm" class="row g-3">
<input type="hidden" name="id" value="<?=htmlspecialchars($project['id']??'')?>">
<div class="col-md-6"><label class="form-label" for="fProjName">项目名称 <span class="text-danger">*</span></label><input type="text" name="name" id="fProjName" class="form-control" required value="<?=htmlspecialchars($project['name']??'')?>"></div>
<div class="col-md-3"><label class="form-label" for="fProjCode">项目编号</label><input type="text" name="code" id="fProjCode" class="form-control" placeholder="如 PRJ-2026-001" value="<?=htmlspecialchars($project['code']??'')?>"></div>
<div class="col-md-3"><label class="form-label" for="fProjStatus">状态</label><select name="status" id="fProjStatus" class="form-select"><?php foreach(dict_options('project_status', $project['status']??'ACTIVE') as $k=>$v): ?><option value="<?=$k?>" <?=($project['status']??'ACTIVE')==$k?'selected':''?>><?=$v?></option><?php endforeach; ?></select></div>
<div class="col-md-3"><label class="form-label" for="fProjBusinessType">业务类型 *</label><select name="business_type" id="fProjBusinessType" class="form-select" required><?php foreach($business_types as $k=>$v): ?><option value="<?=$k?>" <?=($project['business_type']??'OTHER')===$k?'selected':''?>><?=htmlspecialchars($v)?></option><?php endforeach; ?></select></div>
<div class="col-md-6"><label class="form-label" for="fProjCustomer">主客户</label>
<?php $__custName = ''; foreach(($customers ?? []) as $__c){ if((string)($project['customer_id'] ?? 0) !== '0' && (string)($project['customer_id'] ?? 0) === (string)$__c['id']){ $__custName = $__c['name']; break; } } ?>
<div class="cs-wrap" data-cs-url="/ajax/customer/search?q=">
  <input type="text" class="form-control cs-input" id="fProjCustomer" placeholder="搜索客户名称" autocomplete="off" value="<?=htmlspecialchars($__custName)?>">
  <div class="cs-suggestions"></div>
  <input type="hidden" name="customer_id" class="cs-id" value="<?=htmlspecialchars($project['customer_id'] ?? '0')?>">
</div>
</div>
<div class="col-md-3"><label class="form-label" for="fProjBudget">项目预算</label><input type="number" step="0.01" name="budget" id="fProjBudget" class="form-control" value="<?=htmlspecialchars($project['budget']??'')?>"><div class="form-text small">仅登记，不参与强核算</div></div>
<div class="col-md-3"></div>
<div class="col-md-3"><label class="form-label" for="fProjStart">开始日期</label><input type="date" name="start_date" id="fProjStart" class="form-control" value="<?=htmlspecialchars($project['start_date']??'')?>"></div>
<div class="col-md-3"><label class="form-label" for="fProjEnd">结束日期</label><input type="date" name="end_date" id="fProjEnd" class="form-control" value="<?=htmlspecialchars($project['end_date']??'')?>"></div>
<?php $__stageDict = ['PLANNING'=>'筹备','EXECUTING'=>'执行中','ACCEPTANCE'=>'验收中','COMPLETED'=>'已完结']; ?>
<div class="col-md-3"><label class="form-label" for="fProjStage">执行阶段</label><select name="stage" id="fProjStage" class="form-select"><?php foreach($__stageDict as $k=>$v): ?><option value="<?=$k?>" <?=($project['stage']??'PLANNING')==$k?'selected':''?>><?=$v?></option><?php endforeach; ?></select></div>
<div class="col-md-3"><label class="form-label" for="fProjProgress">执行进度</label><input type="number" min="0" max="100" name="progress" id="fProjProgress" class="form-control" value="<?=htmlspecialchars($project['progress']??0)?>"><div class="form-text small">0-100 %</div></div>
<div class="col-12"><label class="form-label" for="fProjRemark">备注</label><textarea name="remark" id="fProjRemark" class="form-control" rows="3"><?=htmlspecialchars($project['remark']??'')?></textarea></div>
<div class="col-12"><button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> 保存</button> <a href="/project" class="btn btn-outline-secondary ms-2">取消</a></div>
</form>
<script>
// P1-4 离页保护：表单有未保存修改时，刷新/关闭/跳转给出浏览器确认提示；提交成功回调里清除 dirty
(function(){
var form=document.getElementById('projectForm');if(!form)return;
form.addEventListener('input',function(){window.__formDirty=true;});
form.addEventListener('change',function(){window.__formDirty=true;});
window.addEventListener('beforeunload',function(e){
  if(!window.__formDirty)return;
  e.preventDefault();e.returnValue='有未保存的修改，确定离开吗？';return '有未保存的修改，确定离开吗？';
});
})();
</script>
<script>document.getElementById('projectForm').addEventListener('submit',function(e){e.preventDefault();var fd=new FormData(this);fetch('/ajax/project/save',{method:'POST',body:new URLSearchParams(fd),headers:{'X-Requested-With':'XMLHttpRequest'}}).then(r=>r.json()).then(res=>{if(res.code===0){window.__formDirty=false;location.href='/project/'+res.data.id;}else showToast(res.msg,'error');});});</script>
<script src="<?=asset_url('js/search-picker.js')?>"></script>
<?php include __DIR__.'/../layout/footer.php'; ?>
