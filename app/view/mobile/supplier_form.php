<?php
// 移动端共享布局（Phase 1 重构 1.7）：头部与尾部由 _head.php / _foot.php 统一输出
$title = !empty($is_edit)?'编辑供应商':'新建供应商';   // 动态标题（直接保留 PHP 表达式）
$tab = 'customer';     // 导航优化 Phase1：供应商由客户 Tab 内进入，高亮"客户"
include __DIR__ . '/_head.php';
?>

<div class="m-nav">
  <a href="/m/suppliers" class="back" aria-label="返回"><i class="bi bi-chevron-left"></i></a>
  <div class="title"><?=!empty($is_edit)?'编辑供应商':'新建供应商'?></div>
  <div class="right"></div>
</div>

<div class="m-page" id="page">
  <form class="m-form" id="form" onsubmit="return false">
    <?php if(!empty($supplier)): ?><input type="hidden" name="id" value="<?=$supplier['id']?>"><?php endif; ?>

    <div class="m-field">
      <label for="f_name">供应商名称<span class="req">*</span></label>
      <input class="m-input" name="name" id="f_name" placeholder="请输入供应商名称" value="<?=htmlspecialchars($supplier['name'] ?? '')?>">
    </div>

    <div class="m-field">
      <label for="fType">供应商类型</label>
      <select class="m-select" name="type" id="fType">
        <?php foreach($types as $k=>$v): ?>
        <option value="<?=htmlspecialchars($k)?>" <?=((($supplier['type'] ?? 'MEDIA')) === $k)?'selected':''?>><?=htmlspecialchars($v)?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="m-field">
      <label for="fContactName">联系人</label>
      <input class="m-input" name="contact_name" id="fContactName" placeholder="联系人姓名" value="<?=htmlspecialchars($supplier['contact_name'] ?? '')?>">
    </div>

    <div class="m-field">
      <label for="fContactMobile">联系电话</label>
      <input class="m-input" name="contact_mobile" type="tel" id="fContactMobile" placeholder="联系人手机" value="<?=htmlspecialchars($supplier['contact_mobile'] ?? '')?>">
    </div>

    <div class="m-field">
      <label for="fAddress">地址</label>
      <textarea class="m-textarea" name="address" id="fAddress" placeholder="供应商联系地址"><?=htmlspecialchars($supplier['address'] ?? '')?></textarea>
    </div>

    <!-- v2.51.3：原「联系邮箱」改为「备注」置底（供应商不维护邮箱，语义统一为客户口径） -->
    <div class="m-field">
      <label for="fRemark">备注</label>
      <textarea class="m-textarea" name="remark" id="fRemark" rows="2" placeholder="简短备注（如结算要求、资质说明等）"><?=htmlspecialchars($supplier['remark'] ?? '')?></textarea>
    </div>
    <!-- v2.51.4：提交按钮由固定悬浮栏改为放在页面内容末尾（随内容滚动，不悬浮遮挡）；居中自适应宽度 -->
    <div style="padding: 6px var(--m-pad) calc(18px + var(--safe-bottom)); display:flex; justify-content:center;">
      <button type="button" class="m-btn m-btn-brand" id="submitBtn" style="flex:none; min-width:160px; padding:0 32px;"><?=!empty($is_edit)?'保存修改':'创建供应商'?></button>
    </div>
  </form>
</div>

<div class="m-toast" id="toast"></div>

<script>
(function(){
  var draft=mobileFormDraft(document.getElementById('form'),'supplier:<?=intval($supplier['id']??0)?>');
  

  document.getElementById('submitBtn').addEventListener('click', function(){
    var name = document.getElementById('f_name').value.trim();
    if(!name){ toast('请输入供应商名称'); return; }
    var form = document.getElementById('form');
    var params = new URLSearchParams(new FormData(form));
    params.set('idempotency_key',mobileIdempotencyKey('supplier-save'));
    var btn = this; btn.disabled = true; btn.innerHTML = '提交中…';
    // N-m1：改用 mobile-common.js 的 apiPost 统一兜底（自动带 CSRF；返回码≠0 / 网络异常统一走 onError）
    apiPost('/ajax/supplier/save', params.toString(),
      function(){ draft.clear(); toast('保存成功'); setTimeout(function(){ location.href = '/m/suppliers'; }, 700); },
      function(err){ btn.disabled=false; btn.innerHTML='<?=!empty($is_edit)?'保存修改':'创建供应商'?>'; toast(err || '保存失败'); }
    );
  });
})();
</script>
<?php $tab = 'customer'; include __DIR__ . '/_foot.php'; ?>
