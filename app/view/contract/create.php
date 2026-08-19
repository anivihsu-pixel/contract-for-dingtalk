<?php $title='新建合同'; $menu_active='contract'; include __DIR__.'/../layout/header.php'; ?>
<style>
.wizard-progress{display:flex;align-items:center;justify-content:center}
.wz-dot{display:flex;flex-direction:column;align-items:center;gap:4px;color:var(--text-3);min-width:64px}
.wz-num{width:34px;height:34px;border-radius:50%;background:#e9ecef;color:#666;display:flex;align-items:center;justify-content:center;font-weight:600;transition:.2s}
.wz-label{font-size:12px}
.wz-dot.active .wz-num{background:var(--primary);color:#fff}
.wz-dot.current .wz-num{border:2px solid var(--primary);box-shadow:0 0 0 3px rgba(11,94,215,.15)}
.wz-line{flex:0 0 44px;height:2px;background:#e9ecef;margin:0 4px;margin-bottom:18px}
/* 2026-08-05：向导步骤卡片化，字段分组更清晰 */
.wizard-step{
  background:#fff;
  border:1px solid #e9ecef;
  border-radius:10px;
  padding:20px 20px 8px;
  box-shadow:0 1px 4px rgba(0,0,0,.04);
}
.wizard-step .form-label{font-weight:600}
/* 2026-08-05：PC 向导页布局优化（限宽/分组标题条/Step1 两列 dense 网格/步骤强调/上传区居中） */
.wizard-progress, .wizard-step, .wizard-nav{ max-width:1120px; margin-left:auto; margin-right:auto; }
.wizard-step{ border-color:#e2e8f0; box-shadow:0 1px 6px rgba(15,23,42,.05); }
.wizard-step .border-top{
  background:#f1f5f9; border-top:0 !important; border-radius:8px;
  padding:7px 12px !important; margin-top:6px !important; margin-bottom:10px !important;
  font-weight:600; color:#475569;
}
@media (min-width:992px){
  #step1{
    display:grid; grid-template-columns:1fr 1fr; grid-auto-flow:dense;
    column-gap:1.5rem; row-gap:.6rem; align-items:start;
  }
  #step1 > div{ width:auto !important; flex:0 0 auto; max-width:none !important; }
  /* 仅纯 col-12（标题/交易性质/分组标题）占满两列；col-12 col-md-4 等混用类不受影响 */
  #step1 > div[class="col-12"]{ grid-column:1 / -1; }
}
.wz-dot.current .wz-num{ transform:scale(1.12); box-shadow:0 0 0 4px rgba(11,94,215,.15); }
#uploadDropzone{ display:flex; flex-direction:column; align-items:center; justify-content:center; min-height:130px; }
/* 与移动端 selfSheetMask 保持同一交互：不依赖页面是否加载 Bootstrap 的 Modal 组件。 */
.pc-company-sheet-mask{position:fixed;inset:0;z-index:1080;display:none;align-items:center;justify-content:center;padding:16px;background:rgba(15,23,42,.45)}
.pc-company-sheet-mask.show{display:flex}
.pc-company-sheet{width:min(480px,100%);max-height:calc(100vh - 32px);overflow:auto;background:#fff;border-radius:12px;box-shadow:0 20px 50px rgba(15,23,42,.28)}
.pc-company-sheet-head{display:flex;align-items:center;justify-content:space-between;padding:16px 18px;border-bottom:1px solid #e9ecef;font-weight:600}
.pc-company-sheet-item{display:flex;align-items:center;gap:10px;width:100%;padding:14px 18px;border:0;border-bottom:1px solid #f1f5f9;background:#fff;text-align:left}
.pc-company-sheet-item:hover{background:#f8fafc}
.pc-company-sheet-item:last-child{border-bottom:0}
</style>
<h4 class="mb-3"><i class="bi bi-file-text"></i> <?=$contract?'编辑合同':'新建合同'?> <small class="text-muted fs-6">（向导）</small></h4>

<!-- 步骤进度条 -->
<div class="wizard-progress mb-4">
  <div class="wz-dot active" data-step="1"><span class="wz-num">1</span><span class="wz-label">基础信息</span></div>
  <div class="wz-line"></div>
  <div class="wz-dot" data-step="2"><span class="wz-num">2</span><span class="wz-label">详情与附件</span></div>
</div>

<?php $isNew = empty($contract['id'] ?? ''); // 新建=必填校验生效；编辑旧数据不追溯（避免卡住历史合同） ?>
<form id="contractForm">
<input type="hidden" name="id" value="<?=htmlspecialchars($contract['id']??'')?>">
<!-- 必填标记/属性宏：新建时输出红星*与 required，编辑时不输出 -->
<?php
    $reqMark = $isNew ? ' <span class="text-danger">*</span>' : '';  // 字段 label 后红星
    $reqAttr = $isNew ? ' required' : '';                            // input/select 的 required 属性
?>

<?php
use app\common\form\ContractFormConfig;
$__maps = [
    'business_types'   => $business_types ?? [],
    'companies'        => $companies ?? [],
    'projects'         => $projects ?? [],
];
$__dcid = $default_company_id ?? 0;
?>
<!-- ========== Step 1 基础信息（由 ContractFormConfig 配置驱动渲染） ========== -->
<div id="step1" class="wizard-step row g-3">
<input type="hidden" name="flow_id" id="presetFlowId" value="<?=htmlspecialchars($contract['flow_id'] ?? 0)?>">
<?= ContractFormConfig::pcFieldsHtml(1, $contract ?? [], $isNew, $__maps, $__dcid) ?>
</div>

<!-- ========== Step 2 对方信息（由 ContractFormConfig 配置驱动渲染） ========== -->
<div id="step2" class="wizard-step row g-3" style="display:none">
<?= ContractFormConfig::pcFieldsHtml(2, $contract ?? [], $isNew, $__maps, $__dcid) ?>

<!-- M9：乙方选择客户后，加载该客户联系人供下拉选择，选中后回填乙方联系人/电话（DOM 由 ContractFormConfig 渲染） -->
<script>
(function(){
  function loadPartyBContacts(custId){
    var wrap = document.getElementById('partyBContactWrap');
    var sel = document.getElementById('partyBContactSelect');
    if(!wrap || !sel) return;
    if(!custId || custId <= 0){ wrap.style.display='none'; sel.innerHTML='<option value="">— 手动输入 —</option>'; return; }
    fetch('/ajax/customer/'+custId+'/contacts', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function(r){ return r.json(); }).then(function(res){
        var list = (res.data)||[];
        sel.innerHTML = '<option value="">— 手动输入 —</option>' + list.map(function(c){
          var label = c.name + (c.phone?(' / '+c.phone):'') + (c.is_primary==1?'(主)':'');
          return '<option value="'+encodeURIComponent(JSON.stringify(c))+'">'+label.replace(/</g,'&lt;')+'</option>';
        }).join('');
        wrap.style.display = list.length ? 'block' : 'none';
      }).catch(function(){ wrap.style.display='none'; });
  }
  window.onPartyBContactPick = function(){
    var sel = document.getElementById('partyBContactSelect');
    var txt = document.getElementById('partyBContact');
    var ph = document.getElementById('partyBPhone');
    if(sel && sel.value){ try{
      var c = JSON.parse(decodeURIComponent(sel.value));
      if(txt) txt.value = c.name || '';
      if(ph) ph.value = c.phone || '';   // v2.47.x：联系人/电话拆分填写
    }catch(e){} }
  };
  // M9 修复：selectParty 用 JS 赋值设置 partyBCustId 不触发下方 attributes 观察器，
  // 暴露本函数供 contract.js 选中后手动调用（客户→加载联系人；非客户/清空→隐藏下拉）
  window.loadPartyBContacts = loadPartyBContacts;
  var obs = new MutationObserver(function(){
    var custId = parseInt(document.getElementById('partyBCustId').value, 10) || 0;
    var type = document.getElementById('partyBType').value;
    loadPartyBContacts(type === 'customer' ? custId : 0);
  });
  var target = document.getElementById('partyBCustId');
  if(target){ obs.observe(target, { attributes:true, attributeFilter:['value'] }); }
  var initCust = parseInt(target ? target.value : 0, 10) || 0;
  var initType = document.getElementById('partyBType') ? document.getElementById('partyBType').value : '';
  if(initCust > 0 && initType === 'customer') loadPartyBContacts(initCust);
})();
</script>

<!-- v2.51.x：随合同申请开票——入口由提交审批页迁移至合同编辑页；字段复用申请开票表单（InvoiceFormConfig::pcRender），
     开票主体/开票内容与后台「发票表单」配置一致，提交字段带 inv_ 前缀避免与合同表单 our_company_id/amount 同名冲突 -->
<div class="card border mt-3">
  <div class="card-header bg-white py-2">
    <div class="form-check form-switch mb-0">
      <input class="form-check-input" type="checkbox" id="withInvoice" name="with_invoice" value="1" <?=!empty($inv_intent['apply'])?'checked':''?>>
      <label class="form-check-label fw-semibold" for="withInvoice"><i class="bi bi-receipt-cutoff me-1"></i>随合同申请开票</label>
      <span class="text-muted small ms-2">合同过审后自动生成待开票发票并通知财务确认</span>
    </div>
  </div>
  <div class="card-body" id="invIntentBox" <?=empty($inv_intent['apply'])?'style="display:none"':''?>>
    <?= \app\common\form\InvoiceFormConfig::pcRender($inv_intent ?: [], ['companies' => $companies], 'inv_') ?>
    <div class="text-muted small mt-2"><i class="bi bi-info-circle"></i> 金额不可超过合同金额；过审后自动开票，后续仍可在合同金额内单独申请。</div>
  </div>
</div>
<script>
// v2.51.x：随合同申请开票——勾选后展开开票字段（display:none 的 required 字段不参与浏览器约束验证，未勾选不拦截提交）
(function(){
  var wchk = document.getElementById('withInvoice');
  var ibox = document.getElementById('invIntentBox');
  if(wchk && ibox){ wchk.addEventListener('change', function(){ ibox.style.display = this.checked ? '' : 'none'; }); }
})();
</script>
</div>

<!-- 本公司主体选择器：复用移动端遮罩层交互，不依赖 Bootstrap Modal。 -->
<div class="pc-company-sheet-mask" id="companyPickerMask" aria-hidden="true">
  <div class="pc-company-sheet" role="dialog" aria-modal="true" aria-labelledby="companyPickerTitle">
    <div class="pc-company-sheet-head"><span id="companyPickerTitle">选择本公司主体</span><button type="button" class="btn-close" id="companyPickerCancel" aria-label="关闭"></button></div>
    <div id="companyPickerList"></div>
  </div>
</div>

<!-- 向导导航 -->
<div class="wizard-nav mt-4 d-flex gap-2 align-items-center">
  <button type="button" class="btn btn-outline-secondary" id="btnPrev" style="display:none" onclick="wizardPrev()"><i class="bi bi-arrow-left"></i> 上一步</button>
  <button type="button" class="btn btn-primary" id="btnNext" onclick="wizardNext()">下一步</button>
  <button type="submit" class="btn btn-success" id="btnSave" style="display:none"><i class="bi bi-save"></i> 保存合同</button>
  <a href="/contract" class="btn btn-outline-secondary ms-auto">取消</a>
  <span class="text-muted small ms-2" id="wizardHint"></span>
</div>
</form>


<script>
// ========== 向导步骤控制 ==========
var WIZARD_TOTAL = 2;
var wizardCurrent = 1;

function showStep(n){
  wizardCurrent = n;
  for(var i=1;i<=WIZARD_TOTAL;i++){
    var el=document.getElementById('step'+i);
    // 2026-08-05：用 d-none class 控制显隐，清空内联 display，避免覆盖 Step1 的 CSS grid 布局
    if(el){ el.style.display=''; el.classList.toggle('d-none', i!==n); }
  }
  document.querySelectorAll('.wz-dot').forEach(function(d){
    var s=parseInt(d.dataset.step,10);
    d.classList.toggle('active', s<=n);
    d.classList.toggle('current', s===n);
  });
  document.getElementById('btnPrev').style.display = (n>1)?'inline-block':'none';
  document.getElementById('btnNext').style.display = (n<WIZARD_TOTAL)?'inline-block':'none';
  document.getElementById('btnSave').style.display = (n===WIZARD_TOTAL)?'inline-block':'none';
  document.getElementById('wizardHint').textContent = (n===WIZARD_TOTAL)?'请上传至少一个附件后保存' : ('第 '+n+' / '+WIZARD_TOTAL+' 步');
  window.scrollTo({top:0,behavior:'smooth'});
}

function wizardValidate(n){
  var el=document.getElementById('step'+n);
  if(!el) return true;
  var reqs=el.querySelectorAll('[required]');
  // P1-7：校验前先清除本步上次留下的错误样式
  for(var j=0;j<reqs.length;j++){ reqs[j].classList.remove('is-invalid'); }
  for(var i=0;i<reqs.length;i++){
    if(!reqs[i].value || !reqs[i].value.trim()){
      var label='必填项';
      var prev=reqs[i].previousElementSibling;
      if(prev && (prev.tagName==='LABEL')) label=(prev.textContent||'').replace('*','').replace('（向导）','').trim()||label;
      // P1-7：校验失败的必填字段标红（Bootstrap5 is-invalid）并滚动到可见位置
      reqs[i].classList.add('is-invalid');
      try{ reqs[i].focus(); }catch(e){}
      try{ reqs[i].scrollIntoView({behavior:'smooth', block:'center'}); }catch(e){}
      if(typeof showToast==='function') showToast('请填写：'+label,'warning');
      return false;
    }
  }
  // v2.46.0：新建合同 Step2 对方侧（非我方侧）必须已关联客户/供应商档案，防自由输入绕过查重/共享治理
  var idEl=document.querySelector('#contractForm input[name=id]');
  if(n===2 && idEl && !idEl.value && typeof window.__partySideLinked==='function'){
    var osEl=document.getElementById('ourSideField');
    var our=osEl && osEl.value ? osEl.value : 'B';   // 我方身份（默认我方=乙方，与移动端一致）
    var oppo=(our==='A')?'B':'A';
    if(!window.__partySideLinked(oppo)){
      var sideName=(oppo==='A')?'甲方':'乙方';
      if(typeof showToast==='function') showToast(sideName+'请从已登记客户/供应商中选择（未登记可点「快速新建」）','error');
      var sEl=document.getElementById('party'+oppo+'Search');
      if(sEl){ sEl.classList.add('is-invalid'); try{ sEl.focus(); }catch(e){} try{ sEl.scrollIntoView({behavior:'smooth', block:'center'}); }catch(e){} }
      return false;
    }
  }
  return true;
}

function wizardNext(){ if(wizardValidate(wizardCurrent)) showStep(wizardCurrent+1); }
function wizardPrev(){ showStep(wizardCurrent-1); }

// P1-7：重新输入时移除该字段的错误样式（事件委托，覆盖动态字段）
(function(){
  var f=document.getElementById('contractForm');
  if(!f) return;
  f.addEventListener('input', function(e){
    if(e.target && e.target.classList) e.target.classList.remove('is-invalid');
  });
})();

// P2-17【F-A2】移除重复调用：顶层 showStep(1) 与下方 DOMContentLoaded 重复初始化，仅保留 DCL 版本
document.addEventListener('DOMContentLoaded', function(){ showStep(1); });
</script>


<script>
// ========== 附件上传 ==========
var uploadedFiles = [];

// 初始化已有附件（编辑时）
(function(){
    var existing = document.getElementById('fileUrlField').value;
    if(existing){
        try { uploadedFiles = JSON.parse(existing); } catch(e){ uploadedFiles = []; }
        renderUploadList();
    }
})();

function handleDrop(e){
    e.preventDefault();
    e.currentTarget.style.background = '';
    handleFiles(e.dataTransfer.files);
}

function handleFiles(files){
    for(var i=0; i<files.length; i++){
        uploadFile(files[i]);
    }
    document.getElementById('fileInput').value = '';
}

function uploadFile(file){
    var ext = file.name.split('.').pop().toLowerCase();
    var allowed = ['pdf','doc','docx','xls','xlsx','jpg','jpeg','png'];
    if(allowed.indexOf(ext) === -1){
        showToast('不支持的文件格式：.' + ext + '（仅支持 PDF/Word/Excel/JPG/PNG）', 'error');
        return;
    }
    if(file.size > 20*1024*1024){
        showToast('文件过大（最大20MB）：' + file.name, 'error');
        return;
    }

    var itemId = 'up_' + Date.now();
    var list = document.getElementById('uploadList');
    var item = document.createElement('div');
    item.id = itemId;
    item.className = 'd-flex align-items-center gap-2 py-1 px-2 border rounded mb-1 bg-white';
    item.innerHTML = '<div class="spinner-border spinner-border-sm text-primary"></div><small class="flex-grow-1 text-muted">' + escHtmlUpload(file.name) + ' 上传中...</small>';
    list.appendChild(item);

    var dz = document.getElementById('uploadDropzone');
    dz.style.background = '#e8f0fe';

    var fd = new FormData();
    fd.append('file', file);

    fetch('/ajax/upload/contract', {
        method: 'POST',
        body: fd
    })
    .then(function(r){ return r.json(); })
    .then(function(res){
        var el = document.getElementById(itemId);
        if(res.code === 0){
            uploadedFiles.push(res.data);
            updateFileUrlField();
            renderUploadList();
        } else {
            if(el) el.innerHTML = '<i class="bi bi-exclamation-circle text-danger"></i><small class="text-danger">' + escHtmlUpload(file.name) + '：' + (res.msg||'失败') + '</small>';
        }
        dz.style.background = '';
    })
    .catch(function(){
        var el = document.getElementById(itemId);
        if(el) el.innerHTML = '<i class="bi bi-exclamation-circle text-danger"></i><small class="text-danger">' + escHtmlUpload(file.name) + '：网络错误</small>';
        setTimeout(function(){ if(el) el.remove(); }, 3000);
        dz.style.background = '';
    });
}

function renderUploadList(){
    var list = document.getElementById('uploadList');
    if(uploadedFiles.length === 0){
        list.innerHTML = '';
        return;
    }
    var icons = {pdf:'bi-file-pdf text-danger', doc:'bi-file-word text-primary', docx:'bi-file-word text-primary',
                 xls:'bi-file-excel text-success', xlsx:'bi-file-excel text-success',
                 jpg:'bi-file-image text-warning', jpeg:'bi-file-image text-warning',
                 png:'bi-file-image text-warning', gif:'bi-file-image text-warning', webp:'bi-file-image text-warning'};
    var h = '';
    uploadedFiles.forEach(function(f, i){
        var ext = (f.name.split('.').pop()||'').toLowerCase();
        var icon = icons[ext] || 'bi-file-earmark text-secondary';
        var size = f.size ? (f.size<1024?f.size+'B':f.size<1048576?(f.size/1024).toFixed(1)+'KB':(f.size/1048576).toFixed(1)+'MB') : '';
        h += '<div class="d-flex align-items-center gap-2 py-1 px-2 border rounded mb-1 bg-white">';
        h += '<i class="bi ' + icon + ' fs-5"></i>';
        h += '<a href="' + escHtmlUpload(f.url) + '" target="_blank" class="flex-grow-1 small text-truncate" style="max-width:300px" title="' + escHtmlUpload(f.name) + '">' + escHtmlUpload(f.name) + '</a>';
        h += '<small class="text-muted">' + size + '</small>';
        h += '<button type="button" class="btn btn-sm btn-outline-danger" style="padding:0 4px;font-size:11px" onclick="removeFile(' + i + ')" title="移除">&times;</button>';
        h += '</div>';
    });
    list.innerHTML = h;
}

function removeFile(idx){
    uploadedFiles.splice(idx, 1);
    updateFileUrlField();
    renderUploadList();
}

function updateFileUrlField(){
    document.getElementById('fileUrlField').value = JSON.stringify(uploadedFiles);
}

function escHtmlUpload(s){
    var d = document.createElement('div');
    d.textContent = s || '';
    return d.innerHTML;
}
</script>


<script>
// 当前模板结构化字段 schema（P1-C）
window.__customSchema = [];

// 选择合同类型预设：套用默认分类/方向/建议审批流，并展示必填提醒（不再把范本文本灌入合同概要）
// 合同性质切换：非交易时隐藏金额/方向行（方案 A，与 directionRow 行为一致）；手动"合同性质"可覆盖模板预设（R1/R5/R7）
function syncTradeAttr(){
    var non = document.getElementById('taNon').checked;
    document.getElementById('tradeAttr').value = non ? '0' : '1';
    var amt = document.querySelector('input[name="amount"]');
    var dirRow = document.getElementById('directionRow');
    var amtRow = document.getElementById('amountField');
    if(non){
        if(amt){ amt.value = '0'; }
        if(dirRow) dirRow.style.display = 'none';
        if(amtRow) amtRow.style.display = 'none';
        document.getElementById('taHint').textContent = '非交易合同不计入收支，无需填写金额/方向，也无需开票或登记回款。';
    } else {
        if(dirRow) dirRow.style.display = '';
        if(amtRow) amtRow.style.display = '';
        document.getElementById('taHint').textContent = '';
    }
}
document.getElementById('taTrade').addEventListener('change', syncTradeAttr);
document.getElementById('taNon').addEventListener('change', syncTradeAttr);
syncTradeAttr();  // 初始化（编辑页按 $contract['trade_attr'] 回填）

// 模板 JavaScript 函数已隐藏，后端保留
</script>

<script>
// v2.45：PC 我方身份引导（与移动端 my|our 语义对齐）——切换「我是合同乙方/甲方」时，
// 自动把签约主体名带出到我方侧名称，并清空对方侧误放的同一主体名，降低甲乙方填反概率。
// v2.47.x：对齐移动端双形态——我方侧=主体名+切换按钮，对方侧=搜索框，按我方身份切换显隐。
(function(){
    var labels = document.querySelectorAll('label[data-action="pc-our-side"]');
    // v2.47.x：当前登录用户（我方侧联系人/电话按登录用户带出）
    var CURRENT_USER = <?=json_encode(isset($current_user) ? $current_user : ['name'=>'','mobile'=>''], JSON_UNESCAPED_UNICODE)?>;
    var COMPANY_MAP = <?=json_encode(array_column($companies ?? [], 'name', 'id'), JSON_UNESCAPED_UNICODE)?>;
    function companyName(){
        var cs = document.getElementById('companySelect');
        return cs ? (COMPANY_MAP[String(cs.value)] || '') : '';
    }
    function getOurSide(){
        var os = document.getElementById('ourSideField');
        return (os && os.value) ? os.value : 'B';
    }
    // 对齐移动端 recomputeOurSide：编辑态 contract 无 our_side 列（ourSideField 为空），
    // 依据两侧名称中与签约主体名完全匹配的一侧反推我方身份（先判甲方后判乙方，与移动端同口径）
    function recomputeOurSidePC(){
        var cn = companyName();
        var osEl = document.getElementById('ourSideField');
        if(!cn || !osEl) return;
        var aEl = document.getElementById('partyAName');
        var bEl = document.getElementById('partyBName');
        if(aEl && aEl.value.trim() === cn) osEl.value = 'A';
        else if(bEl && bEl.value.trim() === cn) osEl.value = 'B';
    }
    // 我方侧兜底带出签约主体名 + 登录用户联系人/电话（空则填，不覆盖已有回显）
    function ensureMineFilledPC(){
        var our = getOurSide();
        var nm = document.getElementById('party' + our + 'Name');
        var cn = companyName();
        if(nm && !nm.value.trim() && cn) nm.value = cn;
        var mn = document.querySelector('[data-mine-name="' + our + '"]');
        if(mn) mn.textContent = nm ? (nm.value || cn || '') : (cn || '');
        var ct = document.getElementById('party' + our + 'Contact');
        var ph = document.getElementById('party' + our + 'Phone');
        if(ct && !ct.value.trim() && CURRENT_USER.name) ct.value = CURRENT_USER.name;
        if(ph && !ph.value.trim() && CURRENT_USER.mobile) ph.value = CURRENT_USER.mobile;
    }
    // 我方/对方形态切换（对齐移动端 applyOurSide）
    function applyOurSidePC(){
        var our = getOurSide();
        ['A','B'].forEach(function(s){
            var mine = document.querySelector('[data-mine="' + s + '"]');
            var other = document.querySelector('[data-other="' + s + '"]');
            var isMine = s === our;
            if(mine) mine.classList.toggle('d-none', !isMine);
            if(other) other.classList.toggle('d-none', isMine);
        });
        var rb = document.getElementById('pcOurSide' + (our === 'A' ? 'A' : 'B'));
        if(rb) rb.checked = true;
        ensureMineFilledPC();
    }
    // 与移动端 fillSelf/openSelfSheet 同源：选定主体后写入我方侧、同步主体 ID 与我方身份。
    var companyPickerMask = document.getElementById('companyPickerMask');
    var companyPickerList = document.getElementById('companyPickerList');
    function closeCompanyPicker(){
        if(companyPickerMask) companyPickerMask.classList.remove('show');
    }
    function fillSelf(side, item){
        var name = item && item.name ? item.name : '';
        if(!name) return;
        var mineName = document.getElementById('party' + side + 'Name');
        var otherSide = side === 'A' ? 'B' : 'A';
        var otherName = document.getElementById('party' + otherSide + 'Name');
        if(otherName && otherName.value.trim() === name) otherName.value = '';
        if(mineName) mineName.value = name;
        var companyId = document.getElementById('companySelect');
        if(companyId && item.id != null) companyId.value = String(item.id);
        var ourSide = document.getElementById('ourSideField');
        if(ourSide) ourSide.value = side;
        applyOurSidePC();
        closeCompanyPicker();
        if(typeof showToast === 'function') showToast('已填入我方主体到' + (side === 'A' ? '甲方' : '乙方'), 'info');
    }
    // 与移动端一致：每次点击均请求当前可用主体，避免页面缓存的主体列表失效。
    function showCompanyPicker(side, list){
        if(!list.length){
            if(typeof showToast === 'function') showToast('尚未配置本公司主体，请前往系统设置添加。', 'error');
            return;
        }
        if(list.length === 1){ fillSelf(side, list[0]); return; }
        if(!companyPickerMask || !companyPickerList) return;
        companyPickerList.innerHTML = '';
        list.forEach(function(item){
            var button = document.createElement('button');
            button.type = 'button'; button.className = 'pc-company-sheet-item';
            button.innerHTML = '<i class="bi bi-building text-primary"></i><span class="flex-grow-1"></span>';
            button.querySelector('span').textContent = item.name || '';
            if(item.is_default){ var tag=document.createElement('span'); tag.className='pc-tag pc-tag-muted'; tag.style.fontSize='10px'; tag.textContent='默认'; button.appendChild(tag); }
            button.addEventListener('click', function(){ fillSelf(side, item); });
            companyPickerList.appendChild(button);
        });
        companyPickerMask.classList.add('show');
    }
    function openCompanyPicker(side){
        fetch('/ajax/company/options', {headers:{'X-Requested-With':'XMLHttpRequest'}})
        .then(function(response){ return response.json(); })
        .then(function(result){
            showCompanyPicker(side, result && result.code === 0 && Array.isArray(result.data) ? result.data : []);
        })
        .catch(function(){
            if(typeof showToast === 'function') showToast('加载本公司主体失败，请重试。', 'error');
        });
    }
    document.querySelectorAll('.pc-party-switch').forEach(function(btn){
        btn.addEventListener('click', function(event){ event.preventDefault(); openCompanyPicker(this.dataset.side); });
    });
    var companyPickerCancel = document.getElementById('companyPickerCancel');
    if(companyPickerCancel) companyPickerCancel.addEventListener('click', closeCompanyPicker);
    if(companyPickerMask) companyPickerMask.addEventListener('click', function(event){
        if(event.target === companyPickerMask) closeCompanyPicker();
    });
    labels.forEach(function(lb){
        lb.addEventListener('click', function(){
            var side = this.dataset.side; // 'A'|'B'
            // v2.46.0：我方身份同步到 hidden（后端据此判定对方侧做强制关联校验）
            var osEl = document.getElementById('ourSideField');
            if(osEl) osEl.value = side;
            // v2.46.0 修复：切换我方身份清空两侧名称/关联档案——原「对方」侧变「我方」后
            // 若沿用上一步填写的对方信息会被误当本方；新我方侧随后带出签约主体名。
            // 对齐移动端：联系人/类型一并清空，避免我方侧残留对方客户信息。
            ['A','B'].forEach(function(s){
                var sEl = document.getElementById('party' + s + 'Search');
                var nEl = document.getElementById('party' + s + 'Name');
                if(nEl) nEl.value = '';
                if(sEl){ sEl.value = ''; sEl.readOnly = false; }
                var cidEl = document.getElementById('party' + s + 'CustId'); if(cidEl) cidEl.value = 0;
                var sidEl = document.getElementById('party' + s + 'SupplierId'); if(sidEl) sidEl.value = 0;
                var ccEl = document.getElementById('party' + s + 'CreditCode'); if(ccEl) ccEl.value = '';
                var ctEl = document.getElementById('party' + s + 'Contact'); if(ctEl) ctEl.value = '';
                var phEl = document.getElementById('party' + s + 'Phone'); if(phEl) phEl.value = '';   // v2.47.3：联系人/电话拆分后一并清空
                var tpEl = document.getElementById('party' + s + 'Type'); if(tpEl) tpEl.value = '';
            });
            // M9 联系人下拉只监听 value 属性，JS 清空 CustId 不触发观察器，须手动隐藏
            var bw = document.getElementById('partyBContactWrap');
            if(bw){ bw.style.display = 'none'; }
            var bsel = document.getElementById('partyBContactSelect');
            if(bsel){ bsel.innerHTML = '<option value="">— 手动输入 —</option>'; }
            applyOurSidePC();   // 切换形态 + 新我方侧带出签约主体名
            if (typeof showToast === 'function') showToast('已设为：我是合同' + (side === 'A' ? '甲方' : '乙方'), 'info');
        });
    });
    recomputeOurSidePC();  // 编辑态先按签约主体与两侧名称匹配反推我方身份（新建时 ourSideField 为空且两侧无匹配，保持默认乙方）
    applyOurSidePC();   // 页面初始化按我方身份应用形态（编辑态回显 ourSideField）
})();
</script>

<script>
// ========== 关联项目搜索选择器 ==========
// 2026-08-05：替代原下拉，输入关键字搜索未归档项目；空输入展示「与我有关」推荐
(function(){
    var input = document.getElementById('projectSearch');
    var sugg = document.getElementById('projectSuggestions');
    var hidden = document.getElementById('projectIdField');
    var timer = null, activeIdx = -1, activeList = [];

    if(!input || !sugg) return;

    function doSearch(q){
        fetch('/ajax/project/search?q=' + encodeURIComponent(q))
        .then(function(r){ return r.json(); })
        .then(function(res){
            if(res.code !== 0 || !res.data || !res.data.length){
                hideSuggestions(); return;
            }
            activeList = res.data;
            activeIdx = -1;
            renderSuggestions();
        });
    }

    input.addEventListener('input', function(){
        var q = this.value.trim();
        clearTimeout(timer);
        if(q.length < 1){
            timer = setTimeout(function(){ doSearch(''); }, 120);
            return;
        }
        timer = setTimeout(function(){ doSearch(q); }, 200);
    });

    input.addEventListener('focus', function(){
        if(!sugg.style.display || sugg.style.display === 'none') doSearch(input.value.trim());
    });

    input.addEventListener('keydown', function(e){
        var items = sugg.querySelectorAll('.party-item');
        if(e.key === 'ArrowDown'){
            e.preventDefault(); activeIdx = Math.min(activeIdx + 1, items.length - 1); highlight(items);
        } else if(e.key === 'ArrowUp'){
            e.preventDefault(); activeIdx = Math.max(activeIdx - 1, 0); highlight(items);
        } else if(e.key === 'Enter'){
            e.preventDefault();
            if(activeIdx >= 0 && activeIdx < activeList.length) selectItem(activeList[activeIdx]);
        } else if(e.key === 'Escape'){
            hideSuggestions();
        }
    });

    input.addEventListener('blur', function(){
        setTimeout(hideSuggestions, 150);
    });

    function renderSuggestions(){
        var h = '';
        activeList.forEach(function(c, i){
            var myBadge = c.my ? '<span class="pc-tag pc-tag-info" style="font-size:10px">与我有关</span>' : '';
            h += '<div class="party-item" data-idx="' + i + '">';
            h += '<i class="bi bi-folder2 me-2 text-muted"></i>';
            h += '<span class="flex-grow-1">' + esc(c.name) + (c.code ? ' <small class="text-muted">' + esc(c.code) + '</small>' : '') + '</span>';
            h += myBadge;
            h += '</div>';
        });
        sugg.innerHTML = h;
        sugg.style.display = 'block';
        sugg.querySelectorAll('.party-item').forEach(function(el){
            el.addEventListener('mousedown', function(e){
                e.preventDefault();
                var idx = parseInt(this.dataset.idx);
                if(idx >= 0 && idx < activeList.length) selectItem(activeList[idx]);
            });
        });
    }

    function highlight(items){
        items.forEach(function(el, i){ el.classList.toggle('active', i === activeIdx); });
    }

    function hideSuggestions(){
        sugg.style.display = 'none';
        sugg.innerHTML = '';
        activeList = [];
        activeIdx = -1;
    }

    function selectItem(c){
        input.value = c.name;
        hidden.value = c.id;
        var businessLine = document.getElementById('businessLineSelect');
        if(businessLine && c.business_type && businessLine.querySelector('option[value="' + c.business_type + '"]')){
            businessLine.value = c.business_type;
            businessLine.dispatchEvent(new Event('change', {bubbles:true}));
        }
        hideSuggestions();
    }

    var clearBtn = document.createElement('span');
    clearBtn.innerHTML = '&times;';
    clearBtn.style.cssText = 'position:absolute;right:8px;top:50%;transform:translateY(-50%);cursor:pointer;color:#999;font-size:18px;z-index:2;display:' + (hidden.value > 0 ? 'block' : 'none');
    clearBtn.onclick = function(){
        input.value = ''; hidden.value = '0'; clearBtn.style.display = 'none'; input.focus(); doSearch('');
    };
    input.parentElement.appendChild(clearBtn);
    input.addEventListener('input', function(){
        clearBtn.style.display = 'none';
    });
    if(hidden.value > 0) clearBtn.style.display = 'block';
})();
</script>

<script>
// ========== 附件必填校验 ==========
(function(){
    var form = document.getElementById('contractForm');
    if(!form) return;
    form.addEventListener('submit', function(e){
        if(typeof uploadedFiles !== 'undefined' && uploadedFiles.length === 0){
            e.preventDefault();
            e.stopImmediatePropagation();
            if(typeof showToast==='function') showToast('请上传至少一个合同附件','warning');
            return;
        }
        // 模板结构化字段收集已移除（前端隐藏，后端预留）
    }, {capture: true});
})();
</script>

<script>
// ========== 离页保护 ==========
// P1-4：表单有未保存修改时，刷新/关闭/跳转给出浏览器确认提示；保存成功回调（contract.js）里清除 dirty
(function(){
    var form = document.getElementById('contractForm');
    if(!form) return;
    form.addEventListener('input', function(){ window.__formDirty = true; });
    form.addEventListener('change', function(){ window.__formDirty = true; });
    window.addEventListener('beforeunload', function(e){
        if(!window.__formDirty) return;
        e.preventDefault();
        e.returnValue = '有未保存的修改，确定离开吗？';
        return '有未保存的修改，确定离开吗？';
    });
})();
</script>
<script src="<?=asset_url('js/contract.js')?>"></script>

<!-- 参考资料库弹窗（仅作合同拟定参考，不强制灌入） -->
<div class="modal fade" id="resourceModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
<div class="modal-header"><h5 class="modal-title"><i class="bi bi-folder2-open me-1"></i> 参考资料库</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
  <div class="btn-group btn-group-sm mb-3" role="group" id="resCatBar">
    <button type="button" class="btn btn-outline-secondary active" data-cat="" onclick="loadResource('')">全部</button>
    <button type="button" class="btn btn-outline-secondary" data-cat="TEMPLATE" onclick="loadResource('TEMPLATE')">合同范本</button>
    <button type="button" class="btn btn-outline-secondary" data-cat="INVOICE" onclick="loadResource('INVOICE')">开票资料</button>
    <button type="button" class="btn btn-outline-secondary" data-cat="CLAUSE" onclick="loadResource('CLAUSE')">标准条款</button>
    <button type="button" class="btn btn-outline-secondary" data-cat="OTHER" onclick="loadResource('OTHER')">其他</button>
  </div>
  <div id="resourceList"><div class="text-center py-4 text-muted small">加载中...</div></div>
  <p class="text-muted small mt-2 mb-0"><i class="bi bi-info-circle"></i> 资料库内容仅供参考，可在新标签页打开/下载后自行摘抄到合同概要，系统不会自动填入。</p>
</div></div></div></div>

<!-- v2.46.0：快速新建客户/供应商（签约方强制关联档案——搜索无匹配时表单内快速建档，复用查重与数据权限） -->
<div class="modal fade" id="partyQuickModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><div class="modal-content">
<div class="modal-header"><h5 class="modal-title"><i class="bi bi-person-plus me-1"></i> 快速新建客户/供应商</h5><button class="btn-close" data-bs-dismiss="modal" aria-label="关闭"></button></div>
<div class="modal-body">
  <div class="mb-2"><label class="form-label" for="quickType">类型</label>
    <select id="quickType" class="form-select">
      <option value="customer">客户</option>
      <option value="supplier">供应商</option>
    </select></div>
  <div class="mb-2"><label class="form-label" for="quickName">名称 <span class="text-danger">*</span></label>
    <input type="text" id="quickName" class="form-control" placeholder="请输入客户/供应商名称"></div>
  <div class="mb-2"><label class="form-label" for="quickContact">联系人</label>
    <input type="text" id="quickContact" class="form-control" placeholder="选填"></div>
  <div class="mb-2"><label class="form-label" for="quickMobile">联系电话</label>
    <input type="text" id="quickMobile" class="form-control" placeholder="选填"></div>
</div>
<div class="modal-footer">
  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
  <button type="button" class="btn btn-primary" onclick="savePartyQuick()"><i class="bi bi-check-lg me-1"></i>保存并选择</button>
</div>
</div></div></div>

<script>
function openResourceModal(){ document.querySelectorAll('#resCatBar .btn').forEach(function(b){b.classList.toggle('active', b.dataset.cat==='');}); loadResource(''); new bootstrap.Modal('#resourceModal').show(); }
function loadResource(cat){
  var url = '/ajax/resource/list' + (cat ? '?category=' + cat : '');
  fetch(url, {headers:{'X-Requested-With':'XMLHttpRequest'}}).then(function(r){return r.json();}).then(function(res){
    var box = document.getElementById('resourceList');
    if(res.code !== 0 || !res.data || !res.data.length){ box.innerHTML = '<div class="text-center py-4 text-muted small">暂无资料</div>'; return; }
    var h = '';
    var icons = {pdf:'bi-file-pdf text-danger', doc:'bi-file-word text-primary', docx:'bi-file-word text-primary', xls:'bi-file-excel text-success', xlsx:'bi-file-excel text-success', jpg:'bi-file-image text-warning', png:'bi-file-image text-warning', gif:'bi-file-image text-warning', webp:'bi-file-image text-warning', txt:'bi-file-earmark-text text-secondary'};
    res.data.forEach(function(r){
      var ext = ((r.file_name||'').split('.').pop()||'').toLowerCase();
      var icon = icons[ext] || 'bi-file-earmark text-secondary';
      h += '<div class="d-flex align-items-start gap-2 p-2 border rounded mb-2 bg-white">';
      h += '<i class="bi '+icon+' fs-4 mt-1"></i>';
      h += '<div class="flex-grow-1"><div class="fw-bold small">'+escHtml(r.title)+' <span class="pc-tag pc-tag-muted" style="font-size:10px">'+escHtml(r.category_name)+'</span>'+(r.company_name?' <span class="pc-tag pc-tag-info" style="font-size:10px">'+escHtml(r.company_name)+'</span>':'')+'</div>';
      if(r.description) h += '<div class="small text-muted">'+escHtml(r.description)+'</div>';
      h += '<a href="'+escHtml(r.file_url)+'" target="_blank" class="small text-primary"><i class="bi bi-box-arrow-up-right"></i> 打开/下载</a>';
      h += '</div></div>';
    });
    box.innerHTML = h;
  });
}
function escHtml(s){var d=document.createElement('div');d.textContent=s||'';return d.innerHTML;}
</script>

<?php include __DIR__.'/../layout/footer.php'; ?>
