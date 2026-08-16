<?php $s=$supplier??null; $title=$s?'编辑供应商':'新增供应商'; $menu_active='supplier'; include __DIR__.'/../layout/header.php'; ?>
<h4 class="mb-3"><?=$s?'编辑供应商':'新增供应商'?></h4>
<form id="supplierForm" class="row g-3">
<input type="hidden" name="id" value="<?=htmlspecialchars($s['id']??'')?>">
<div class="col-md-6"><label class="form-label" for="fSupName">名称 <span class="text-danger">*</span></label><input type="text" name="name" id="fSupName" class="form-control" required value="<?=htmlspecialchars($s['name']??'')?>"></div>
<div class="col-md-3"><label class="form-label" for="fSupType">类型</label><select name="type" id="fSupType" class="form-select">
<?php foreach(dict_options('supplier_type', $s['type']??'') as $k=>$v): ?><option value="<?=$k?>" <?=($s['type']??'')==$k?'selected':''?>><?=$v?></option><?php endforeach; ?></select></div>
<div class="col-md-3"><label class="form-label" for="fSupStatus">状态</label><select name="status" id="fSupStatus" class="form-select"><option value="1" <?=($s['status']??1)==1?'selected':''?>>正常</option><option value="0">禁用</option></select></div>
<div class="col-md-4"><label class="form-label" for="fSupContact">联系人</label><input type="text" name="contact_name" id="fSupContact" class="form-control" value="<?=htmlspecialchars($s['contact_name']??'')?>"></div>
<div class="col-md-4"><label class="form-label" for="fSupMobile">联系电话</label><input type="text" name="contact_mobile" id="fSupMobile" class="form-control" value="<?=htmlspecialchars($s['contact_mobile']??'')?>"></div>
<div class="col-12"><label class="form-label" for="fSupAddress">地址</label><input type="text" name="address" id="fSupAddress" class="form-control" value="<?=htmlspecialchars($s['address']??'')?>"></div>
<div class="col-12"><label class="form-label" for="fSupRemark">备注</label><textarea name="remark" id="fSupRemark" class="form-control" rows="2" placeholder="简短备注（如结算要求、资质说明等）"><?=htmlspecialchars($s['remark']??'')?></textarea></div>
<div class="col-12"><button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> 保存</button> <a href="/supplier" class="btn btn-outline-secondary ms-2">取消</a></div>
</form>
<script>
// P1-4 离页保护：表单有未保存修改时，刷新/关闭/跳转给出浏览器确认提示；提交成功回调里清除 dirty
(function(){
var form=document.getElementById('supplierForm');if(!form)return;
form.addEventListener('input',function(){window.__formDirty=true;});
form.addEventListener('change',function(){window.__formDirty=true;});
window.addEventListener('beforeunload',function(e){
  if(!window.__formDirty)return;
  e.preventDefault();e.returnValue='有未保存的修改，确定离开吗？';return '有未保存的修改，确定离开吗？';
});
})();
</script>
<script>document.getElementById('supplierForm').addEventListener('submit',function(e){e.preventDefault();var fd=new FormData(this);fetch('/ajax/supplier/save',{method:'POST',body:new URLSearchParams(fd),headers:{'X-Requested-With':'XMLHttpRequest'}}).then(r=>r.json()).then(res=>{if(res.code===0){window.__formDirty=false;location.href='/supplier/'+res.data.id;}else showToast(res.msg,'error');});});</script>
<?php include __DIR__.'/../layout/footer.php'; ?>
