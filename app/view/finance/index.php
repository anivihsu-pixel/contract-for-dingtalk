<?php $title='财务中心'; $menu_active='finance'; include __DIR__.'/../layout/header.php'; ?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0"><i class="bi bi-cash-coin"></i> 财务中心</h4>
  <!-- 税务汇总已隐藏，后端保留 -->
</div>
<?php $fs = $fin_summary ?? ['sales'=>['total'=>0,'cnt'=>0],'purchase'=>['total'=>0,'cnt'=>0]]; ?>
<div class="row g-3 mb-3">
  <div class="col-md-6"><div class="card stat-card border-start border-success border-4"><div class="card-body py-2">
    <div class="small text-muted">销售合同（我方收款 / 应收）</div>
    <div class="fs-5 fw-bold text-success">¥<?=number_format($fs['sales']['total'],0)?></div>
    <div class="small text-muted"><?=$fs['sales']['cnt']?> 份合同</div>
  </div></div></div>
  <div class="col-md-6"><div class="card stat-card border-start border-warning border-4"><div class="card-body py-2">
    <div class="small text-muted">采购合同（我方付款 / 应付）</div>
    <div class="fs-5 fw-bold text-warning">¥<?=number_format($fs['purchase']['total'],0)?></div>
    <div class="small text-muted"><?=$fs['purchase']['cnt']?> 份合同</div>
  </div></div></div>
</div>
<div class="row g-3 mb-3">
  <div class="col-12"><a href="/report/monthly" class="btn btn-outline-primary btn-sm"><i class="bi bi-bar-chart-line"></i> 经营月报（按月汇总应收/回款/收支方向，支持导出）</a></div>
</div>
<ul class="nav nav-tabs mb-3" id="finTabs">
  <li class="nav-item"><a class="nav-link" href="/finance?tab=payment" data-fintab="payment">回款管理</a></li>
  <li class="nav-item"><a class="nav-link" href="/finance?tab=payable" data-fintab="payable">付款管理</a></li>
  <li class="nav-item"><a class="nav-link" href="/finance?tab=invoice" data-fintab="invoice">发票管理</a></li>
</ul>
<div id="paymentPanel">
<div class="mb-3">
  <input type="text" id="paymentKw" class="form-control form-control-sm" placeholder="按发票号搜索回款…" oninput="load(true)">
</div>
<div class="card stat-card"><div class="table-responsive"><table class="table table-hover mb-0"><thead class="table-light" id="th"></thead><tbody id="tb"></tbody></table>
<div class="card-footer bg-white text-muted small" id="empty" style="display:none">暂无数据</div>
<div class="card-footer bg-white text-center" id="loadmore" style="display:none"><button class="btn btn-sm btn-outline-secondary" onclick="loadMore()">加载更多</button></div></div></div>
</div>

<!-- v2.40.0 P1-4：付款管理面板（复用 payment-list 接口按 payment_type=PAYABLE 过滤） -->
<div id="payablePanel" style="display:none">
<div class="mb-3">
  <input type="text" id="payableKw" class="form-control form-control-sm" placeholder="按发票号搜索付款…" oninput="load(true)">
</div>
<div class="card stat-card"><div class="table-responsive"><table class="table table-hover mb-0"><thead class="table-light" id="payableTh"></thead><tbody id="payableTb"></tbody></table>
<div class="card-footer bg-white text-muted small" id="payableEmpty" style="display:none">暂无数据</div>
<div class="card-footer bg-white text-center" id="payableLoadmore" style="display:none"><button class="btn btn-sm btn-outline-secondary" onclick="loadMore()">加载更多</button></div></div></div>
</div>

<!-- 发票管理面板（P0 恢复 2026-08-02：跨合同发票查询 + 开票/红冲/作废操作；申请开票在合同详情页） -->
<div id="invoicePanel" style="display:none">
  <div class="d-flex justify-content-between align-items-center mb-2">
    <input type="text" id="invKw" class="form-control form-control-sm" style="max-width:260px" placeholder="按发票号/合同搜索…" oninput="loadInv(true)">
    <a href="/finance/tax" class="btn btn-outline-secondary btn-sm"><i class="bi bi-file-earmark-bar-graph"></i> 税务汇总</a>
  </div>
  <div class="card stat-card"><div class="table-responsive"><table class="table table-hover mb-0"><thead class="table-light"><tr><th>合同</th><th>发票号</th><th>类型</th><th>金额</th><th>税额</th><th>开票日期</th><th>状态</th><th>操作</th></tr></thead><tbody id="invTb"></tbody></table>
  <div class="card-footer bg-white text-muted small" id="invEmpty" style="display:none">暂无发票</div>
  <div class="card-footer bg-white text-center" id="invLoadmore" style="display:none"><button class="btn btn-sm btn-outline-secondary" onclick="loadInvMore()">加载更多</button></div></div></div>
</div>

<!-- 开票 Modal（财务中心复用：申请→填发票号置已开票） -->
<div class="modal fade" id="invIssueModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
<div class="modal-header"><h5 class="modal-title">确认开票</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
<div class="mb-2"><label class="form-label" for="invIssueNo">发票号码 <span class="text-danger">*</span></label><input type="text" id="invIssueNo" class="form-control" placeholder="如 FP2026080001"></div>
<div class="mb-2"><label class="form-label" for="invIssueDate">开票日期</label><input type="date" id="invIssueDate" class="form-control"></div>
</div>
<div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">取消</button><button class="btn btn-success" onclick="submitInvIssue()">确认开票</button></div>
</div></div></div>

<!-- 确认收款弹层（回款列表行内操作，部分确认金额 ≤ 应收，剩余自动拆为待收） -->
<div class="modal fade" id="payConfirmModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
<div class="modal-header"><h5 class="modal-title" id="finConfirmTitle">确认收款</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
  <div class="mb-2"><label class="form-label" for="payConfirmAmt">收款金额（元）<span class="text-danger">*</span></label><input type="number" step="0.01" min="0.01" id="payConfirmAmt" class="form-control"></div>
  <div class="mb-2"><label class="form-label" for="payConfirmMethod">收款方式 <span class="text-danger">*</span></label><select id="payConfirmMethod" class="form-select">
  <option value="">- 请选择 -</option>
  <?php foreach (dict_options('payment_method') as $code => $label): ?><option value="<?=htmlspecialchars($code)?>"><?=htmlspecialchars($label)?></option><?php endforeach; ?>
  </select></div>
  <div class="mb-2"><label class="form-label" for="payConfirmDate">实际收款日期</label><input type="date" id="payConfirmDate" class="form-control"></div>
  <div class="mb-2"><label class="form-label" for="payConfirmInvoice">发票号码</label><input type="text" id="payConfirmInvoice" class="form-control" placeholder="选填，如 FP2026070001"></div>
  <div class="text-danger small" id="payConfirmErr"></div>
</div>
<div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">取消</button><button class="btn btn-primary" onclick="submitConfirmPay()">确认收款</button></div>
</div></div></div>
<script>
// UX 门控：行内操作按钮按后端守卫同口径（payment:create / invoice:create）控制
var __canPay = <?= !empty($can_pay) ? 'true' : 'false' ?>;
var __canIssue = <?= !empty($can_issue) ? 'true' : 'false' ?>;
var page = 1, finished = false, loading = false, curType = 'RECEIVABLE';
// v2.40.0 P1-4：回款/付款两个面板共用一套加载渲染，元素按类型切换
function panelIds(){
  var pay = curType === 'PAYABLE';
  return { th: pay ? 'payableTh' : 'th', tb: pay ? 'payableTb' : 'tb',
           empty: pay ? 'payableEmpty' : 'empty', loadmore: pay ? 'payableLoadmore' : 'loadmore',
           kw: pay ? 'payableKw' : 'paymentKw' };
}
function load(reset){
  if(loading) return;
  if(reset){ page = 1; finished = false; }
  if(finished) return;
  loading = true;
  var ids = panelIds();
  document.getElementById(ids.th).innerHTML = '<tr><th>合同</th><th>金额</th><th>计划日</th><th>状态</th><th>说明</th><th>发票号</th></tr>';
  var kw = document.getElementById(ids.kw).value.trim();
  var url = '/ajax/finance/payment-list?page=' + page + '&payment_type=' + curType + (kw ? '&kw=' + encodeURIComponent(kw) : '');
  $ajax(url, {silent:true}).then(function(res){
    loading = false;
    render(res.data || [], res.count || 0, reset);
  }).catch(function(){
    // 加载失败（silent 不 toast）：面板内展示重试入口，避免"加载中"永驻
    loading = false;
    var ids = panelIds();
    var tb = document.getElementById(ids.tb);
    if(tb) tb.innerHTML = '<tr><td colspan="7" class="text-center py-5 text-muted"><i class="bi bi-exclamation-triangle" style="font-size:2rem"></i><div class="mt-2">加载失败，点击重试</div><button type="button" class="btn btn-sm btn-outline-secondary mt-2" onclick="load(true)"><i class="bi bi-arrow-clockwise"></i> 重新加载</button></td></tr>';
    var em = document.getElementById(ids.empty); if(em) em.style.display = 'none';
    var lm = document.getElementById(ids.loadmore); if(lm) lm.style.display = 'none';
  });
}
function loadMore(){ if(!finished && !loading) load(false); }
function render(list, total, reset){
  list = list || [];
  var ids = panelIds();
  var tb = document.getElementById(ids.tb);
  if(reset) tb.innerHTML = '';
  if(!list.length){
    if(reset){
      // 空态：表格内展示 emptyState 样式（原 empty footer 纯文字隐藏）
      tb.innerHTML = emptyState({colspan:7, icon:'bi-cash-stack', title:'暂无数据'});
      document.getElementById(ids.empty).style.display = 'none';
    }
    document.getElementById(ids.loadmore).style.display = 'none';
    finished = true;
    return;
  }
  document.getElementById(ids.empty).style.display = 'none';
  var h = '';
  list.forEach(function(r){
    var isPay = r.payment_type === 'PAYABLE';
    var st = r.status === 'PAID' ? '<span class="pc-tag pc-tag-ok">'+(isPay?'已付':'已收')+'</span>'
           : (r.status === 'OVERDUE' ? '<span class="pc-tag pc-tag-danger">逾期</span>' : '<span class="pc-tag pc-tag-warn">'+(isPay?'待付':'待收')+'</span>');
    var act = '';
    if(r.status !== 'PAID'){
      act = __canPay ? '<button class="btn btn-sm btn-outline-success" onclick="confirmPay('+r.id+','+parseFloat(r.amount||0)+')">'+(isPay?'确认付款':'确认收款')+'</button>' : '';
    }
    h += '<tr><td><a href="/contract/'+r.contract_id+'">'+esc(r.contract_title||'(未命名合同)')+'</a><div class="small text-muted">'+esc(r.contract_no||'')+'</div></td>'
       + '<td>¥'+fmt(r.amount)+'</td><td>'+(r.planned_date||'-')+'</td><td>'+st+'</td><td>'+esc(r.description||'')+'</td>'
       + '<td>'+(r.invoice_no ? '<span class="pc-tag pc-tag-info">'+esc(r.invoice_no)+'</span>' : '<span class="text-muted small">—</span>')+'</td>'
       + '<td>'+(act || '<span class="text-muted small">'+(isPay?'已付讫':'已收讫')+'</span>')+'</td></tr>';
  });
  tb.insertAdjacentHTML('beforeend', h);
  var rendered = tb.querySelectorAll('tr').length;
  if(total && rendered >= total){ finished = true; document.getElementById(ids.loadmore).style.display = 'none'; }
  else { document.getElementById(ids.loadmore).style.display = 'block'; }
  page++;
}
// esc() 已统一下沉至 public/static/js/app.js（全局 window.esc），此处不再重复定义
function fmt(n){ return (parseFloat(n || 0)).toLocaleString(); }

// 确认收款/付款（回款/付款列表行内操作，避免钻进合同详情）：部分确认金额 ≤ 应收，剩余自动拆为待收
var _payId = 0;
function confirmPay(id, amount){
  _payId = id;
  document.getElementById('finConfirmTitle').textContent = curType === 'PAYABLE' ? '确认付款' : '确认收款';
  document.getElementById('payConfirmAmt').value = amount || 0;
  document.getElementById('payConfirmAmt').max = amount || 0;
  document.getElementById('payConfirmAmt').classList.remove('is-invalid'); // P1-7：打开弹窗清除错误样式
  document.getElementById('payConfirmDate').value = new Date().toISOString().slice(0,10);
  document.getElementById('payConfirmErr').textContent = '';
  new bootstrap.Modal('#payConfirmModal').show();
}
function submitConfirmPay(){
  var amtEl = document.getElementById('payConfirmAmt');
  var amt = parseFloat(amtEl.value || '0');
  if(!(amt > 0)){
    amtEl.classList.add('is-invalid'); // P1-7：金额字段标红
    document.getElementById('payConfirmErr').textContent = '请输入正确的收款金额';
    return;
  }
  amtEl.classList.remove('is-invalid');
  if(!document.getElementById('payConfirmMethod').value){
    document.getElementById('payConfirmErr').textContent = '请选择收款方式';
    return;
  }
  var fd = new FormData();
  fd.append('id', _payId);
  fd.append('confirm_amount', amt);
  fd.append('payment_method', document.getElementById('payConfirmMethod').value);
  fd.append('actual_date', document.getElementById('payConfirmDate').value);
  fd.append('invoice_no', document.getElementById('payConfirmInvoice').value.trim());
  document.getElementById('payConfirmErr').textContent = '提交中…';
  $ajax('/ajax/payment/confirm', {method:'POST', body: fd, loading:false}).then(function(res){
    document.getElementById('payConfirmErr').textContent = '';
    showToast(res.msg || '确认收款成功', res.code === 0 ? 'success' : 'error');
    if(res.code === 0){ bootstrap.Modal.getInstance('#payConfirmModal').hide(); load(true); }
  }).catch(function(){ document.getElementById('payConfirmErr').textContent = '网络异常，请重试'; });
}
// P1-7：金额输入即清除错误样式
(function(){
  var el = document.getElementById('payConfirmAmt');
  if(el) el.addEventListener('input', function(){ el.classList.remove('is-invalid'); });
})();

// ===== 发票管理（P0 恢复 2026-08-02）=====
var invPage = 1, invFinished = false, invLoading = false, invIssueId = 0;
var invTypeLabels = <?= json_encode(dict('invoice_type'), JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?: '{}' ?>;
function switchFinTab(tab){
  document.getElementById('paymentPanel').style.display = (tab === 'payment') ? '' : 'none';
  document.getElementById('payablePanel').style.display = (tab === 'payable') ? '' : 'none';
  document.getElementById('invoicePanel').style.display = (tab === 'invoice') ? '' : 'none';
  document.querySelectorAll('#finTabs .nav-link').forEach(function(a){ a.classList.toggle('active', a.dataset.fintab === tab); });
  if(tab === 'payment' || tab === 'payable'){
    curType = (tab === 'payable') ? 'PAYABLE' : 'RECEIVABLE';
    load(true);
  }
  if(tab === 'invoice' && invPage === 1 && !invLoading){ loadInv(true); }
}
function loadInv(reset){
  if(invLoading) return;
  if(reset){ invPage = 1; invFinished = false; }
  if(invFinished) return;
  invLoading = true;
  var kw = document.getElementById('invKw').value.trim();
  var url = '/ajax/finance/invoice-list?page=' + invPage + '&pageSize=20' + (kw ? '&kw=' + encodeURIComponent(kw) : '');
  $ajax(url, {silent:true}).then(function(res){
    invLoading = false;
    var list = (res && res.data) || [], count = res && res.count ? res.count : 0;
    var tb = document.getElementById('invTb');
    if(reset) tb.innerHTML = '';
    list.forEach(function(v){
      // F1：发票状态机（申请→审批→开票三段式；APPLIED=历史申请态只读兼容）
      var st = v.status==='ISSUED' ? '<span class="pc-tag pc-tag-ok">已开票</span>'
        : v.status==='VOID' ? '<span class="pc-tag pc-tag-muted">已作废</span>'
        : v.status==='RED' ? '<span class="pc-tag pc-tag-danger">已红冲</span>'
        : v.status==='PENDING_APPROVAL' ? '<span class="pc-tag pc-tag-warn">待审批</span>'
        : v.status==='APPROVED' ? '<span class="pc-tag pc-tag-info">待开票</span>'
        : v.status==='REJECTED' ? '<span class="pc-tag pc-tag-danger">已驳回</span>'
        : v.status==='CANCELLED' ? '<span class="pc-tag pc-tag-muted">已撤回</span>'
        : '<span class="pc-tag pc-tag-info">申请中（旧）</span>';
      var act = '';
      // UX 门控：开票/红冲/作废/删除按 invoice:create 权限渲染（与后端 Invoice 守卫同口径）
      if(__canIssue){
      // 待开票/历史申请 → 开票；已开票 → 红冲/作废；已驳回/已撤回 → 删除
      if(v.status==='APPROVED' || v.status==='APPLIED'){ act = '<button class="btn btn-sm btn-outline-success" onclick="showInvIssue('+v.id+')">开票</button>'; }
      else if(v.status==='ISSUED'){ act = '<button class="btn btn-sm btn-outline-danger" onclick="redFinInvoice('+v.id+')">红冲</button> <button class="btn btn-sm btn-outline-secondary" onclick="voidFinInvoice('+v.id+')">作废</button>'; }
      else if(v.status==='REJECTED' || v.status==='CANCELLED'){ act = '<button class="btn btn-sm btn-outline-danger" aria-label="删除" onclick="delFinInvoice('+v.id+')"><i class="bi bi-trash"></i></button>'; }
      }
      tb.innerHTML += '<tr><td><a href="/contract/'+(v.contract_id||'')+'">'+esc(v.contract_title||'(未命名合同)')+'</a><div class="small text-muted">'+esc(v.contract_no||'')+'</div></td><td>'+(v.invoice_no?esc(v.invoice_no):'<span class="text-muted">—</span>')+'</td><td>'+(invTypeLabels[v.invoice_type]||v.invoice_type||'')+'</td><td>¥'+parseFloat(v.amount||0).toLocaleString()+'</td><td>¥'+parseFloat(v.tax_amount||0).toFixed(2)+'</td><td>'+(v.issued_date?esc(v.issued_date):'—')+'</td><td>'+st+'</td><td>'+act+'</td></tr>';
    });
    if(tb.innerHTML === '') tb.innerHTML = emptyState({colspan:8, icon:'bi-receipt', title:'暂无发票'});
    document.getElementById('invEmpty').style.display = 'none';
    document.getElementById('invLoadmore').style.display = (tb.innerHTML !== '' && tb.rows.length < count) ? '' : 'none';
    if(tb.rows.length >= count && count > 0) invFinished = true;
  }).catch(function(){
    // 加载失败（silent 不 toast）：面板内展示重试入口，避免"加载中"永驻
    invLoading = false;
    var tb = document.getElementById('invTb');
    if(tb) tb.innerHTML = '<tr><td colspan="8" class="text-center py-5 text-muted"><i class="bi bi-exclamation-triangle" style="font-size:2rem"></i><div class="mt-2">加载失败，点击重试</div><button type="button" class="btn btn-sm btn-outline-secondary mt-2" onclick="loadInv(true)"><i class="bi bi-arrow-clockwise"></i> 重新加载</button></td></tr>';
    var em = document.getElementById('invEmpty'); if(em) em.style.display = 'none';
    var lm = document.getElementById('invLoadmore'); if(lm) lm.style.display = 'none';
  });
}
function loadInvMore(){ if(!invFinished && !invLoading){ invPage++; loadInv(false); } }
function showInvIssue(id){ invIssueId = id; document.getElementById('invIssueNo').value=''; document.getElementById('invIssueDate').value=new Date().toISOString().slice(0,10); new bootstrap.Modal('#invIssueModal').show(); }
var __invActing = false; // P0-2：确认开票防重复连点锁（双击只提交一次；失败自动释放）
function submitInvIssue(){
  if(__invActing) return;
  __invActing = true;
  var no = document.getElementById('invIssueNo').value.trim();
  if(!no){ showToast('请填写发票号码','error'); __invActing = false; return; }
  var body = new URLSearchParams({id:invIssueId, invoice_no:no, issued_date:document.getElementById('invIssueDate').value, status:'ISSUED'});
  $ajax('/ajax/invoice/update', {method:'POST', body: body}).then(function(res){
    __invActing = false;
    showToast(res.msg||'操作完成', res.code===0?'success':'error');
    if(res.code===0){ bootstrap.Modal.getInstance('#invIssueModal').hide(); loadInv(true); }
  }).catch(function(){ __invActing = false; });
}
function redFinInvoice(id){ pcConfirm({message:'确认红冲该发票？将生成负数冲抵开票额度',danger:true}).then(function(ok){if(!ok)return; $ajax('/ajax/invoice/red', {method:'POST', body:new URLSearchParams({id:id})}).then(function(res){ showToast(res.msg||'操作完成', res.code===0?'success':'error'); if(res.code===0) loadInv(true); }); });}
function voidFinInvoice(id){ pcConfirm({message:'确认作废该发票？',danger:true}).then(function(ok){if(!ok)return; $ajax('/ajax/invoice/void', {method:'POST', body:new URLSearchParams({id:id})}).then(function(res){ showToast(res.msg||'操作完成', res.code===0?'success':'error'); if(res.code===0) loadInv(true); }); });}
function delFinInvoice(id){ pcConfirm({message:'确认删除该开票申请？',danger:true}).then(function(ok){if(!ok)return; $ajax('/ajax/invoice/delete', {method:'POST', body:new URLSearchParams({id:id})}).then(function(res){ showToast(res.msg||'操作完成', res.code===0?'success':'error'); if(res.code===0) loadInv(true); }); });}

// 初始加载（DOMContentLoaded 包裹：确保 footer 的 app.js 已定义全局 esc/$ajax，避免脚本顺序导致未定义，回归防护）
function finInit(){
  var tab = new URLSearchParams(location.search).get('tab') || 'payment';
  switchFinTab(tab);
  if(tab === 'payment') load(true);
}
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', finInit);
} else {
    finInit();
}
</script>
<?php include __DIR__.'/../layout/footer.php'; ?>
