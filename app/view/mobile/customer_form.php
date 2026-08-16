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
      <input class="m-input" name="name" id="f_name" placeholder="请输入客户名称" value="<?=htmlspecialchars($customer['name'] ?? $prefill_customer_name ?? '')?>">
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
      <label for="fCreditCode">统一信用代码</label>
      <input class="m-input" name="credit_code" id="fCreditCode" placeholder="营业执照统一社会信用代码" value="<?=htmlspecialchars($customer['credit_code'] ?? '')?>">
    </div>

    <div class="m-field">
      <label for="fLegalPerson">法定代表人</label>
      <input class="m-input" name="legal_person" id="fLegalPerson" placeholder="法人姓名" value="<?=htmlspecialchars($customer['legal_person'] ?? '')?>">
    </div>

    <!-- v2.51.4：来源必填，新建不默认选项 -->
    <div class="m-field">
      <label for="fSource">客户来源<span class="req">*</span></label>
      <select class="m-select" name="source" id="fSource" <?=empty($is_edit)?'required':''?>>
        <option value="" <?=empty($customer['source'] ?? '')?'selected':''?>>请选择客户来源</option>
        <option value="MANUAL" <?=($customer['source']??'')=='MANUAL'?'selected':''?>>手动录入</option>
        <option value="WEBSITE" <?=($customer['source']??'')=='WEBSITE'?'selected':''?>>官网</option>
        <option value="EXHIBITION" <?=($customer['source']??'')=='EXHIBITION'?'selected':''?>>展会</option>
        <option value="REFERRAL" <?=($customer['source']??'')=='REFERRAL'?'selected':''?>>转介绍</option>
        <option value="PHONE" <?=($customer['source']??'')=='PHONE'?'selected':''?>>电话</option>
        <option value="AD" <?=($customer['source']??'')=='AD'?'selected':''?>>广告</option>
        <option value="OTHER" <?=($customer['source']??'')=='OTHER'?'selected':''?>>其他</option>
      </select>
    </div>

    <!-- 客户生命周期（客户/成交） -->
    <div class="m-field">
      <label for="fLifecycle">生命周期</label>
      <select class="m-select" name="lifecycle_status" id="fLifecycle">
        <?php foreach(($lifecycle_dict ?? ['POTENTIAL'=>'客户','ACTIVE'=>'成交']) as $code=>$label): ?><option value="<?=htmlspecialchars($code)?>" <?=($customer['lifecycle_status'] ?? 'POTENTIAL')===$code?'selected':''?>><?=htmlspecialchars($label)?></option><?php endforeach; ?>
      </select>
    </div>

    <!-- v2.40.0 P1-7：客户行业（政府单位/房地产/餐饮旅游/其他）；v2.51.4：新建必填、不默认选项 -->
    <div class="m-field">
      <label for="fIndustry">行业<span class="req">*</span></label>
      <select class="m-select" name="industry" id="fIndustry" <?=empty($is_edit)?'required':''?>>
        <option value="" <?=empty($customer['industry'] ?? '')?'selected':''?>>请选择客户行业</option>
        <?php foreach(($industry_dict ?? []) as $code=>$label): ?><option value="<?=htmlspecialchars($code)?>" <?=($customer['industry'] ?? '')===$code?'selected':''?>><?=htmlspecialchars($label)?></option><?php endforeach; ?>
      </select>
    </div>

    <div class="m-field">
      <label for="fAddress">地址</label>
      <textarea class="m-textarea" name="address" id="fAddress" placeholder="客户联系地址"><?=htmlspecialchars($customer['address'] ?? '')?></textarea>
    </div>

    <div class="m-field">
      <label for="fRemark">备注</label>
      <input class="m-input" name="remark" id="fRemark" maxlength="255" placeholder="简短备注" value="<?=htmlspecialchars($customer['remark'] ?? '')?>">
    </div>
    <!-- v2.51.4：提交按钮由固定悬浮栏改为放在页面内容末尾（随内容滚动，不悬浮遮挡）；居中自适应宽度 -->
    <div style="padding: 6px var(--m-pad) calc(18px + var(--safe-bottom)); display:flex; justify-content:center;">
      <button type="button" class="m-btn m-btn-brand" id="submitBtn" style="flex:none; min-width:160px; padding:0 32px;"><?=!empty($is_edit)?'保存修改':'创建客户'?></button>
    </div>
  </form>
</div>

<div class="m-toast" id="toast"></div>

<script>
(function(){
  var draft=mobileFormDraft(document.getElementById('form'),'customer:<?=intval($customer['id']??0)?>');
  // v2.51.4：新建客户时来源/行业必填，编辑不强制（旧数据可能为空）
  var isEdit = !!document.querySelector('input[name="id"]');

  document.getElementById('submitBtn').addEventListener('click', function(){
    var name = document.getElementById('f_name').value.trim();
    if(!name){ toast('请输入客户名称'); return; }
    if(!isEdit){
      var source = document.getElementById('fSource').value;
      if(!source){ toast('请选择客户来源'); return; }
      var industry = document.getElementById('fIndustry').value;
      if(!industry){ toast('请选择客户行业'); return; }
    }
    var form = document.getElementById('form');
    var params = new URLSearchParams(new FormData(form));
    params.set('idempotency_key',mobileIdempotencyKey('customer-save'));
    var btn = this; btn.disabled = true; btn.innerHTML = '提交中…';
    // N-m1：改用 mobile-common.js 的 apiPost 统一兜底（自动带 CSRF；返回码≠0 / 网络异常统一走 onError）
    apiPost('/ajax/customer/save', params.toString(),
      function(res){ draft.clear(); toast('保存成功'); setTimeout(function(){ location.href = '/m/customers'; }, 700); },
      function(err){ btn.disabled=false; btn.innerHTML='<?=!empty($is_edit)?'保存修改':'创建客户'?>'; toast(err || '保存失败'); }
    );
  });
})();
</script>
<?php $tab = 'customer'; include __DIR__ . '/_foot.php'; ?>
