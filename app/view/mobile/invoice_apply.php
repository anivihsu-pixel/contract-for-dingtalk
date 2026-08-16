<?php
// 移动端发票申请独立页（v2.38.18）：与 PC /invoice-apply 同源——我的申请列表 + 申请开票表单
// 表单字段由 InvoiceFormConfig::mobileRender 渲染（后台「系统设置→发票表单」可配），
// 联动走 form-linkage.js，提交复用 POST /ajax/invoice/add，列表走 /ajax/invoice/my-list
$title = '申请开票';   // 页面标题，自动追加「 · 合同管理」
$tab = 'more';     // 底部导航高亮：财务模块（与财务页一致）
include __DIR__ . '/_head.php';
?>

<div class="m-nav">
  <a href="/m/finance" class="back" aria-label="返回"><i class="bi bi-chevron-left"></i></a>
  <div class="title">申请开票</div>
  <div class="right"></div>
</div>

<div class="m-tabs" id="invPageTabs">
  <a href="javascript:;" class="m-tab active" data-tab="apply" onclick="switchInvPage('apply')">申请开票</a>
  <a href="javascript:;" class="m-tab" data-tab="mine" onclick="switchInvPage('mine')">我的申请</a>
</div>

<div class="m-page" id="page">
  <!-- 申请开票表单（卡片：关联合同 + 配置化字段 + 价税拆分） -->
  <div id="panel-apply">
    <div class="m-card">
      <div class="m-card-bd">
        <div class="m-field" style="position:relative">
          <label for="mInvContractSearch">关联合同 <span style="color:var(--m-text-3);font-size:12px">（选填，可不选直接快捷申请；选择后自动带出开票抬头/税号）</span></label>
          <input type="text" class="m-input" id="mInvContractSearch" placeholder="搜索合同编号 / 标题" autocomplete="off">
          <input type="hidden" id="mInvContractId" value="0">
          <div class="m-party-suggest" id="mInvSuggest"></div>
        </div>
        <div id="mInvFields">
          <!-- 2026-08-02：税率绑定开票主体，表单不渲染税率组件；隐藏字段承接主体税率供价税拆分与提交（后端强制从公司读取，防篡改） -->
          <input type="hidden" name="tax_rate" id="mInvTaxRate" value="0.06">
          <?= $m_invoice_fields ?>
        </div>
        <!-- H2：含税金额价税拆分实时展示（含税 = 不含税 + 税额） -->
        <div style="color:var(--m-text-3);font-size:13px;margin-top:6px;display:none" id="mInvTaxCalc"></div>
        <div style="color:#fa5151;font-size:13px;min-height:18px;margin-top:8px" id="mInvErr"></div>
        <button class="m-btn m-btn-primary" onclick="submitMInvApply()" style="width:100%;margin-top:4px"><i class="bi bi-send"></i> 提交申请</button>
      </div>
    </div>
  </div>

  <!-- 我的申请列表（异步加载 /ajax/invoice/my-list；v2.51.4 改为标签页展示） -->
  <div id="panel-mine" style="display:none">
    <div class="m-card">
      <div class="m-card-bd">
        <div id="invMineList"><div class="m-empty"><i class="bi bi-arrow-repeat"></i> 加载中…</div></div>
        <div class="text-center py-2" id="invMineMore" style="display:none"><button class="m-btn m-btn-ghost" onclick="loadMineInv(false)">加载更多</button></div>
      </div>
    </div>
  </div>
</div>

<script>
// ===== 移动端发票申请独立页（v2.38.18）=====
(function(){
  var mInvContractId = 0;
  var page = 1, done = false, loading = false;

  function mBadge(s){
    var m = {PENDING_APPROVAL:'<span class="m-tag m-tag-warn">待审批</span>',APPROVED:'<span class="m-tag" style="background:#e6f1fb;color:#185fa5">待开票</span>',REJECTED:'<span class="m-tag m-tag-danger">已驳回</span>',ISSUED:'<span class="m-tag m-tag-ok">已开票</span>',VOID:'<span class="m-tag">已作废</span>',RED:'<span class="m-tag m-tag-danger">已红冲</span>',CANCELLED:'<span class="m-tag">已撤回</span>',APPLIED:'<span class="m-tag">申请中（旧）</span>'};
    return m[s] || esc(s);
  }

  // 价税拆分实时展示（税率随主体带出由 mInvCompanyPicked 维护）
  function refreshTaxCalc(){
    var f = document.getElementById('mInvFields');
    if(!f) return;
    var amtEl = f.querySelector('input[name="amount"]');
    var rateEl = f.querySelector('input[name="tax_rate"]') || document.getElementById('mInvTaxRate');
    var calc = document.getElementById('mInvTaxCalc');
    if(!amtEl || !rateEl || !calc) return;
    var amt = parseFloat(amtEl.value) || 0, rate = parseFloat(rateEl.value) || 0;
    if(amt <= 0){ calc.style.display = 'none'; return; }
    var tax = amt - amt / (1 + rate);
    calc.innerHTML = '含税 ¥' + amt.toLocaleString(2) + ' = 不含税 ¥' + (amt - tax).toFixed(2) + ' + 税额 ¥' + tax.toFixed(2) + '（税率 ' + (rate * 100) + '%）';
    calc.style.display = '';
  }

  // 开票主体选中回调：带出该主体税率并刷新价税拆分（initInvPickers 触发）
  window.mInvCompanyPicked = function(rate){
    var f = document.getElementById('mInvFields');
    if(!f) return;
    var rateEl = f.querySelector('input[name="tax_rate"]') || document.getElementById('mInvTaxRate');
    if(rateEl && rate !== '' && rate !== null && rate !== undefined){ rateEl.value = rate; }
    refreshTaxCalc();
  };
  var f0 = document.getElementById('mInvFields');
  if(f0){
    var amt0 = f0.querySelector('input[name="amount"]');
    if(amt0) amt0.addEventListener('input', refreshTaxCalc);
  }
  // 初始化开票主体/下拉选择器（复用新建合同「我方主体」底部弹层交互）
  if(typeof window.initInvPickers === 'function') window.initInvPickers(document.getElementById('mInvFields'));

  // 申请开票 / 我的申请 标签切换（v2.51.4）
  window.switchInvPage = function(tab){
    document.querySelectorAll('#invPageTabs .m-tab').forEach(function(t){ t.classList.toggle('active', t.dataset.tab === tab); });
    document.getElementById('panel-apply').style.display = tab === 'apply' ? '' : 'none';
    document.getElementById('panel-mine').style.display = tab === 'mine' ? '' : 'none';
    if(tab === 'mine') loadMineInv(true);
  };

  // 提交申请（复用财务页同款逻辑；2026-08-15：加二次确认，与合同审批提交对齐）
  window.submitMInvApply = function(){
    var err = document.getElementById('mInvErr');
    var fd = new FormData();
    fd.append('contract_id', document.getElementById('mInvContractId').value || 0);
    document.querySelectorAll('#mInvFields [name]').forEach(function(el){ fd.append(el.name, el.value); });
    var miss = [];
    document.querySelectorAll('#mInvFields [required]').forEach(function(el){
      if(!el.value.trim()){ var lb = el.closest('.m-field') ? el.closest('.m-field').querySelector('label') : null; miss.push(lb ? lb.textContent.replace('*','').trim() : el.name); }
    });
    if(miss.length){ err.textContent = '请填写：' + miss.join('、'); return; }
    err.textContent = '';
    var amt = parseFloat(fd.get('amount')) || 0;
    mConfirm('确认提交开票申请？金额 ¥' + amt.toLocaleString() + '，提交后将进入审批流程。', function(){
      err.textContent = '提交中…';
      $ajax('/ajax/invoice/add', {method:'POST', body:fd, loading:false}).then(function(res){
        err.textContent = '';
        toast(res.msg || '提交成功', res.code === 0 ? 'success' : 'error');
        if(res.code === 0){ loadMineInv(true); switchInvPage('mine'); }
      }).catch(function(){ err.textContent = '网络异常，请重试'; });
    });
  };

  // 我的申请列表（与财务页 loadInv(mine) 同口径）
  window.loadMineInv = function(reset){
    if(loading) return;
    if(reset){ page = 1; done = false; }
    if(done) return;
    loading = true;
    $ajax('/ajax/invoice/my-list?page=' + page, {loading:false}).then(function(res){
      loading = false;
      var list = (res && res.data) || [], total = (res && res.count) || 0;
      var box = document.getElementById('invMineList');
      if(reset) box.innerHTML = '';
      if(!list.length && reset){ box.innerHTML = '<div class="m-empty"><i class="bi bi-inbox"></i> 暂无开票申请</div>'; }
      list.forEach(function(v){
        var act = '';
        if(v.status === 'REJECTED'){ act = '<a href="javascript:;" class="m-chip" onclick="mResubmit('+v.id+')">重新提交</a>'; }
        else if(v.status === 'PENDING_APPROVAL' && v.inst_id){ act = '<a href="javascript:;" class="m-chip" onclick="mRecallInv('+v.inst_id+')">撤回</a>'; }
        box.insertAdjacentHTML('beforeend',
          '<div class="m-card" style="padding:12px 14px;border:1px solid var(--m-border);border-radius:12px;margin-bottom:10px;background:#fff">'
          + '<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px">'
          + '<div style="flex:1;min-width:0"><div style="font-size:15px;font-weight:500;color:var(--m-text-1)">'+esc(v.content_desc||'—')+'</div>'
          + '<div style="font-size:12px;color:var(--m-text-3);margin-top:4px">'+(v.invoice_title?esc(v.invoice_title):'')+(v.our_company_name?(' · '+esc(v.our_company_name)):'')+'</div></div>'
          + mBadge(v.status)+'</div>'
          + '<div style="display:flex;justify-content:space-between;align-items:center;margin-top:8px">'
          + '<span style="font-size:16px;font-weight:600;color:var(--m-text-1)">¥'+parseFloat(v.amount||0).toLocaleString()+'</span>'
          + '<span style="font-size:12px;color:var(--m-text-3)">'+(v.created_at||v.submitted_at||'')+'</span></div>'
          + (act ? '<div style="margin-top:8px;display:flex;gap:8px">'+act+'</div>' : '') + '</div>');
      });
      var more = document.getElementById('invMineMore');
      if(more){
        var cardCnt = box.querySelectorAll('.m-card').length;
        more.style.display = (total > 0 && cardCnt < total) ? '' : 'none';
        if(cardCnt >= total && total > 0) done = true;
      }
    }).catch(function(){ loading = false; });
  };

  // 撤回（审批中实例，走审批通用撤回接口）
  window.mRecallInv = function(instId){
    mConfirm('确认撤回该申请？', function(){
      $ajax('/ajax/approval/' + instId + '/recall', {method:'POST', body:new URLSearchParams({}), loading:true}).then(function(res){
        toast(res.msg || '已撤回', res.code === 0 ? 'success' : 'error');
        if(res.code === 0) loadMineInv(true);
      }).catch(function(){});
    });
  };
  // 重新提交（驳回后，二次确认）
  window.mResubmit = function(id){
    mConfirm('确认重新提交审批？', function(){
      $ajax('/ajax/invoice/resubmit', {method:'POST', body:new URLSearchParams({id:id}), loading:true}).then(function(res){
        toast(res.msg || '已提交', res.code === 0 ? 'success' : 'error');
        if(res.code === 0) loadMineInv(true);
      }).catch(function(){});
    });
  };

  // 关联合同搜索（选填，搜索建议列表直选；替代 v2.38 的 mPrompt 输序号方式）
  var mSearch = document.getElementById('mInvContractSearch');
  if(mSearch){
    var mSuggest = document.getElementById('mInvSuggest');
    var mTimer = null;
    mSearch.addEventListener('input', function(){
      var q = this.value.trim();
      clearTimeout(mTimer);
      if(q.length < 1){ if(mSuggest) mSuggest.style.display = 'none'; return; }
      mTimer = setTimeout(function(){
        $ajax('/ajax/contract/search?keyword=' + encodeURIComponent(q), {loading:false}).then(function(res){
          var list = (res && res.data) || [];
          if(!mSuggest || !list.length){ if(mSuggest) mSuggest.style.display = 'none'; return; }
          var h = '';
          list.forEach(function(c, i){
            h += '<div class="m-party-item" data-idx="'+i+'">'+esc(c.title)+' <span class="m-party-tag">'+esc(c.contract_no||'')+'</span></div>';
          });
          mSuggest.innerHTML = h;
          mSuggest.style.display = 'block';
          mSuggest._list = list;
          mSuggest.querySelectorAll('.m-party-item').forEach(function(el){
            el.addEventListener('mousedown', function(e){
              e.preventDefault();
              var c = mSuggest._list[parseInt(this.dataset.idx, 10)];
              if(c){
                mInvContractId = c.id;
                var hid0 = document.getElementById('mInvContractId'); if(hid0) hid0.value = c.id; // 同步隐藏字段供提交读取
                mSearch.value = c.title; mSuggest.style.display = 'none';
                // v2.51.4：关联合同 → 自动带出乙方抬头/税号（可再改选开票客户覆盖）
                $ajax('/ajax/contract/invoice-info?id=' + c.id, {loading:false}).then(function(res){
                  if(res && res.code === 0 && typeof window.mInvFillByContract === 'function') window.mInvFillByContract(res.data);
                }).catch(function(){});
              }
            });
          });
        }).catch(function(){});
      }, 300);
    });
    document.addEventListener('click', function(e){
      if(mSuggest && e.target !== mSearch && !mSuggest.contains(e.target)) mSuggest.style.display = 'none';
    });
  }

  // 首屏加载
  document.addEventListener('DOMContentLoaded', function(){ loadMineInv(true); });
})();
</script>
<script>
// F9：发票申请表单联动规则（通用组件 form-linkage.js 自动消费；后台「系统设置→发票表单」配置）
window.__formRules = <?= json_encode($m_invoice_form_rules ?? [], JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
// H3：联动 fill 动作数据源（选客户 → 自动带出抬头=客户名/税号=信用代码）
window.__formData = <?= json_encode(['customer_id' => $m_invoice_customers ?? []], JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
</script>
<script src="<?=asset_url('js/form-linkage.js')?>"></script>
<script src="<?=asset_url('js/search-picker.js')?>"></script>
<?php include __DIR__ . '/_foot.php'; ?>
