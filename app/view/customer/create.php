<?php $title=$customer?'编辑客户':'新增客户'; $menu_active='customer'; include __DIR__.'/../layout/header.php'; ?>
<h4 class="mb-3"><?=$customer?'编辑客户':'新增客户'?></h4>
<form id="customerForm" class="row g-3">
<input type="hidden" name="id" value="<?=htmlspecialchars($customer['id']??'')?>">
<div class="col-md-6"><label class="form-label" for="fCustName">客户名称 <span class="text-danger">*</span></label><input type="text" name="name" id="fCustName" class="form-control" required value="<?=htmlspecialchars($customer['name']??'')?>"></div>
<div class="col-md-3"><label class="form-label" for="fCreditCode">信用代码</label><input type="text" name="credit_code" id="fCreditCode" class="form-control" value="<?=htmlspecialchars($customer['credit_code']??'')?>"></div>
<div class="col-md-3"><label class="form-label" for="fLegalPerson">法定代表人</label><input type="text" name="legal_person" id="fLegalPerson" class="form-control" value="<?=htmlspecialchars($customer['legal_person']??'')?>"></div>
<div class="col-md-4"><label class="form-label" for="fContactName">联系人</label><input type="text" name="contact_name" id="fContactName" class="form-control" value="<?=htmlspecialchars($customer['contact_name']??'')?>"></div>
<div class="col-md-4"><label class="form-label" for="fContactMobile">联系电话</label><input type="text" name="contact_mobile" id="fContactMobile" class="form-control" value="<?=htmlspecialchars($customer['contact_mobile']??'')?>"></div>
<div class="col-md-4"><label class="form-label" for="fContactEmail">邮箱</label><input type="text" name="contact_email" id="fContactEmail" class="form-control" value="<?=htmlspecialchars($customer['contact_email']??'')?>"></div>
<div class="col-12"><label class="form-label" for="fAddress">地址</label><input type="text" name="address" id="fAddress" class="form-control" value="<?=htmlspecialchars($customer['address']??'')?>"></div>
<div class="col-md-3 col-6"><label class="form-label" for="fSource">来源</label><select name="source" id="fSource" class="form-select"><option value="MANUAL" <?=($customer['source']??'MANUAL')=='MANUAL'?'selected':''?>>手动录入</option><option value="WEBSITE" <?=($customer['source']??'')=='WEBSITE'?'selected':''?>>官网</option><option value="EXHIBITION" <?=($customer['source']??'')=='EXHIBITION'?'selected':''?>>展会</option><option value="REFERRAL" <?=($customer['source']??'')=='REFERRAL'?'selected':''?>>转介绍</option><option value="PHONE" <?=($customer['source']??'')=='PHONE'?'selected':''?>>电话</option><option value="AD" <?=($customer['source']??'')=='AD'?'selected':''?>>广告</option><option value="OTHER" <?=($customer['source']??'')=='OTHER'?'selected':''?>>其他</option></select></div>
<div class="col-md-3 col-6"><label class="form-label" for="fStatus">状态</label><select name="status" id="fStatus" class="form-select"><option value="1" <?=($customer['status']??1)==1?'selected':''?>>正常</option><option value="0" <?=($customer['status']??1)==0?'selected':''?>>禁用</option></select></div>
<!-- v2.38.9：客户生命周期（补全 M10 漏斗编辑入口）——客户/成交/公海 -->
<div class="col-md-3 col-6"><label class="form-label" for="fLifecycle">生命周期</label><select name="lifecycle_status" id="fLifecycle" class="form-select"><option value="POTENTIAL" <?=($customer['lifecycle_status']??'ACTIVE')=='POTENTIAL'?'selected':''?>>客户</option><option value="ACTIVE" <?=($customer['lifecycle_status']??'ACTIVE')=='ACTIVE'?'selected':''?>>成交</option><option value="INACTIVE" <?=($customer['lifecycle_status']??'')=='INACTIVE'?'selected':''?>>公海</option></select></div>
<!-- v2.40.0 P1-7：客户行业（政府单位/房地产/餐饮旅游/其他） -->
<div class="col-md-3 col-6"><label class="form-label" for="fIndustry">行业</label><select name="industry" id="fIndustry" class="form-select"><option value="">未设置</option><option value="GOV" <?=($customer['industry']??'')=='GOV'?'selected':''?>>政府单位</option><option value="REAL_ESTATE" <?=($customer['industry']??'')=='REAL_ESTATE'?'selected':''?>>房地产</option><option value="FOOD_TOURISM" <?=($customer['industry']??'')=='FOOD_TOURISM'?'selected':''?>>餐饮旅游</option><option value="OTHER" <?=($customer['industry']??'')=='OTHER'?'selected':''?>>其他</option></select></div>
<div class="col-md-3 col-6"><label class="form-label" for="fCreditScore">信用评分（0-100）</label><input name="credit_score" id="fCreditScore" type="number" min="0" max="100" class="form-control" placeholder="100" value="<?=$customer['credit_score']??''?>"></div>
<div class="col-12"><button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> 保存</button> <a href="/customer" class="btn btn-outline-secondary ms-2">取消</a></div>
</form>
<script>
// P1-4 离页保护：表单有未保存修改时，刷新/关闭/跳转给出浏览器确认提示；提交成功回调里清除 dirty
(function(){
var form=document.getElementById('customerForm');if(!form)return;
form.addEventListener('input',function(){window.__formDirty=true;});
form.addEventListener('change',function(){window.__formDirty=true;});
window.addEventListener('beforeunload',function(e){
  if(!window.__formDirty)return;
  e.preventDefault();e.returnValue='有未保存的修改，确定离开吗？';return '有未保存的修改，确定离开吗？';
});
})();
</script>
<script>document.getElementById('customerForm').addEventListener('submit',function(e){e.preventDefault();var fd=new FormData(this);fetch('/ajax/customer/save',{method:'POST',body:new URLSearchParams(fd),headers:{'X-Requested-With':'XMLHttpRequest'}}).then(r=>r.json()).then(res=>{if(res.code===0){window.__formDirty=false;location.href='/customer/'+res.data.id;}else showToast(res.msg,'error');});});</script>
<?php include __DIR__.'/../layout/footer.php'; ?>

