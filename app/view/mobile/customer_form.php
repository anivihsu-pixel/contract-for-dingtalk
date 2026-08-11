<?php
// 移动端共享布局（Phase 1 重构 1.7）：头部与尾部由 _head.php / _foot.php 统一输出
$title = !empty($is_edit)?'编辑客户':'新建客户';   // 动态标题（直接保留 PHP 表达式）
$tab = 'customer';     // 底部导航高亮：home/contract/customer/todo
include __DIR__ . '/_head.php';
?>

<div class="m-nav">
  <a href="/m/customers" class="back" aria-label="返回"><i class="bi bi-chevron-left"></i></a>
  <div class="title"><?=!empty($is_edit)?'编辑客户':'新建客户'?></div>
  <div class="right"></div>
</div>

<div class="m-page" id="page">
  <form class="m-form" id="form" onsubmit="return false">
    <?php if(!empty($customer)): ?><input type="hidden" name="id" value="<?=$customer['id']?>"><?php endif; ?>

    <div class="m-field">
      <label for="f_name">客户名称<span class="req">*</span></label>
      <input class="m-input" name="name" id="f_name" placeholder="请输入客户名称" value="<?=htmlspecialchars($customer['name'] ?? '')?>">
    </div>

    <div class="m-field">
      <label for="fContactName">联系人</label>
      <input class="m-input" name="contact_name" id="fContactName" placeholder="联系人姓名" value="<?=htmlspecialchars($customer['contact_name'] ?? '')?>">
    </div>

    <div class="m-field">
      <label for="fContactMobile">联系电话</label>
      <input class="m-input" name="contact_mobile" type="tel" id="fContactMobile" placeholder="联系人手机" value="<?=htmlspecialchars($customer['contact_mobile'] ?? '')?>">
    </div>

    <div class="m-field">
      <label for="fContactEmail">联系邮箱</label>
      <input class="m-input" name="contact_email" type="email" id="fContactEmail" placeholder="联系人邮箱" value="<?=htmlspecialchars($customer['contact_email'] ?? '')?>">
    </div>

    <div class="m-field">
      <label for="fCreditCode">统一信用代码</label>
      <input class="m-input" name="credit_code" id="fCreditCode" placeholder="营业执照统一社会信用代码" value="<?=htmlspecialchars($customer['credit_code'] ?? '')?>">
    </div>

    <div class="m-field">
      <label for="fLegalPerson">法定代表人</label>
      <input class="m-input" name="legal_person" id="fLegalPerson" placeholder="法人姓名" value="<?=htmlspecialchars($customer['legal_person'] ?? '')?>">
    </div>

    <div class="m-field">
      <label for="fSource">客户来源</label>
      <select class="m-select" name="source" id="fSource">
        <option value="MANUAL" <?=($customer['source']??'MANUAL')=='MANUAL'?'selected':''?>>手动录入</option>
        <option value="WEBSITE" <?=($customer['source']??'')=='WEBSITE'?'selected':''?>>官网</option>
        <option value="EXHIBITION" <?=($customer['source']??'')=='EXHIBITION'?'selected':''?>>展会</option>
        <option value="REFERRAL" <?=($customer['source']??'')=='REFERRAL'?'selected':''?>>转介绍</option>
        <option value="PHONE" <?=($customer['source']??'')=='PHONE'?'selected':''?>>电话</option>
        <option value="AD" <?=($customer['source']??'')=='AD'?'selected':''?>>广告</option>
        <option value="OTHER" <?=($customer['source']??'')=='OTHER'?'selected':''?>>其他</option>
      </select>
    </div>

    <!-- v2.38.9：客户生命周期（补全 M10 漏斗编辑入口）——客户/成交/公海 -->
    <div class="m-field">
      <label for="fLifecycle">生命周期</label>
      <select class="m-select" name="lifecycle_status" id="fLifecycle">
        <option value="POTENTIAL" <?=($customer['lifecycle_status'] ?? 'ACTIVE')=='POTENTIAL'?'selected':''?>>客户</option>
        <option value="ACTIVE" <?=($customer['lifecycle_status'] ?? 'ACTIVE')=='ACTIVE'?'selected':''?>>成交</option>
        <option value="INACTIVE" <?=($customer['lifecycle_status'] ?? '')=='INACTIVE'?'selected':''?>>公海</option>
      </select>
    </div>

    <!-- v2.40.0 P1-7：客户行业（政府单位/房地产/餐饮旅游/其他） -->
    <div class="m-field">
      <label for="fIndustry">行业</label>
      <select class="m-select" name="industry" id="fIndustry">
        <option value="" <?=empty($customer['industry'] ?? '')?'selected':''?>>未设置</option>
        <option value="GOV" <?=($customer['industry'] ?? '')=='GOV'?'selected':''?>>政府单位</option>
        <option value="REAL_ESTATE" <?=($customer['industry'] ?? '')=='REAL_ESTATE'?'selected':''?>>房地产</option>
        <option value="FOOD_TOURISM" <?=($customer['industry'] ?? '')=='FOOD_TOURISM'?'selected':''?>>餐饮旅游</option>
        <option value="OTHER" <?=($customer['industry'] ?? '')=='OTHER'?'selected':''?>>其他</option>
      </select>
    </div>

    <div class="m-field">
      <label for="fAddress">地址</label>
      <textarea class="m-textarea" name="address" id="fAddress" placeholder="客户联系地址"><?=htmlspecialchars($customer['address'] ?? '')?></textarea>
    </div>
  </form>
</div>

<div class="m-submitbar">
  <button class="m-btn m-btn-brand" id="submitBtn"><i class="bi bi-check-lg"></i> <?=!empty($is_edit)?'保存修改':'创建客户'?></button>
</div>

<div class="m-toast" id="toast"></div>

<script>
(function(){
  
  
  

  document.getElementById('submitBtn').addEventListener('click', function(){
    var name = document.getElementById('f_name').value.trim();
    if(!name){ toast('请输入客户名称'); return; }
    var form = document.getElementById('form');
    var params = new URLSearchParams(new FormData(form));
    var btn = this; btn.disabled = true; btn.innerHTML = '提交中…';
    // N-m1：改用 mobile-common.js 的 apiPost 统一兜底（自动带 CSRF；返回码≠0 / 网络异常统一走 onError）
    apiPost('/ajax/customer/save', params.toString(),
      function(){ toast('保存成功'); setTimeout(function(){ location.href = '/m/customers'; }, 700); },
      function(err){ btn.disabled=false; btn.innerHTML='<i class="bi bi-check-lg"></i> <?=!empty($is_edit)?'保存修改':'创建客户'?>'; toast(err || '保存失败'); }
    );
  });
})();
</script>
<?php $tab = 'customer'; include __DIR__ . '/_foot.php'; ?>
