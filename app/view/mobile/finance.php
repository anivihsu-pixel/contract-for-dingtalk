<?php
// 移动端共享布局（Phase 1 重构 1.7）：头部与尾部由 _head.php / _foot.php 统一输出
$title = '财务统计';   // 页面标题，自动追加「 · 合同管理」
$tab = 'more';     // 底部导航高亮：home/contract/customer/todo/more
include __DIR__ . '/_head.php';
?>

<div class="m-nav">
  <a href="/m" class="back" aria-label="返回"><i class="bi bi-chevron-left"></i></a>
  <div class="title">财务统计</div>
  <div class="right"></div>
</div>

<div class="m-page" id="page">

  <!-- 回款预测：横排紧凑卡片（2026-08-01：原竖排 hero+sub+sub2 三行与下方收支概览叠在一起显得拥挤，改为左右两栏） -->
  <div class="m-forecast-card" id="finForecast">
    <div class="hero">¥<?=number_format((float)($fin_summary['forecast60'] ?? 0), 0)?></div>
    <div class="info">
      <div class="sub">预计未来60天回款</div>
      <div class="sub2">30天内 ¥<?=number_format((float)($fin_summary['forecast30'] ?? 0), 0)?></div>
    </div>
  </div>

  <!-- 收支概览：销售应收 / 采购应付 两列 -->
  <div class="m-stat-row">
    <div class="m-stat">
      <div class="n in" id="finSalesTotal">¥<?=number_format((float)($fin_summary['sales']['total'] ?? 0), 0)?></div>
      <div class="l"><span id="finSalesLabel">销售合同（应收）</span></div>
      <div class="c" id="finSalesCnt"><?=intval($fin_summary['sales']['cnt'] ?? 0)?> 份</div>
    </div>
    <div class="m-stat">
      <div class="n out" id="finPurchaseTotal">¥<?=number_format((float)($fin_summary['purchase']['total'] ?? 0), 0)?></div>
      <div class="l"><span id="finPurchaseLabel">采购合同（应付）</span></div>
      <div class="c" id="finPurchaseCnt"><?=intval($fin_summary['purchase']['cnt'] ?? 0)?> 份</div>
    </div>
  </div>

  <!-- 维度切换 -->
  <div style="display:flex;gap:8px;overflow-x:auto;padding:var(--m-gap) var(--m-gap) 4px;-webkit-overflow-scrolling:touch;">
    <a href="javascript:;" class="m-chip active" data-mtab="payment" onclick="switchMTab('payment')">回款管理</a>
    <a href="javascript:;" class="m-chip" data-mtab="invoice" onclick="switchMTab('invoice')">发票</a>
  </div>

  <!-- 周期筛选：本月 / 本季 / 本年 / 累计（仅回款面板） -->
  <div id="finPeriodRow" style="display:flex;gap:8px;overflow-x:auto;padding:0 var(--m-gap) 4px;-webkit-overflow-scrolling:touch;">
    <a href="javascript:;" class="m-chip" data-finperiod="month">本月</a>
    <a href="javascript:;" class="m-chip" data-finperiod="quarter">本季</a>
    <a href="javascript:;" class="m-chip" data-finperiod="year">本年</a>
    <a href="javascript:;" class="m-chip active" data-finperiod="all">累计</a>
  </div>

  <!-- 回款 -->
  <div id="panel-payment" class="fin-panel">
    <div id="list-payment">
      <div class="m-empty"><i class="bi bi-arrow-repeat"></i>加载中…</div>
    </div>
    <div class="m-loadmore" id="lm-payment" style="display:none">加载更多</div>
  </div>

  <!-- F7：发票面板（我的申请 + 待我审批 + 待开票，申请表单字段后台可配） -->
  <div id="panel-invoice" class="fin-panel" style="display:none">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:4px var(--m-gap) 8px">
      <div style="display:flex;gap:6px;overflow-x:auto">
        <a href="javascript:;" class="m-chip active" data-invtab="mine" onclick="switchInvTab('mine')">我的申请</a>
        <a href="javascript:;" class="m-chip" data-invtab="pending" onclick="switchInvTab('pending')">待我审批</a>
        <?php if(!empty($m_can_create_invoice)): ?><a href="javascript:;" class="m-chip" data-invtab="issue" onclick="switchInvTab('issue')">待开票</a><?php endif; ?>
      </div>
      <?php if(!empty($m_can_apply_invoice)): ?><a href="javascript:;" class="m-chip m-chip-primary" onclick="showMInvApply()"><i class="bi bi-plus-lg"></i> 申请</a><?php endif; ?>
    </div>
    <div id="list-inv-mine" style="display:block"><div class="m-empty">加载中…</div></div>
    <div id="list-inv-pending" style="display:none"><div class="m-empty">加载中…</div></div>
    <div id="list-inv-issue" style="display:none"><div class="m-empty">加载中…</div></div>
    <div class="m-loadmore" id="lm-inv" style="display:none">加载更多</div>
  </div>

</div>

<!-- F7：移动端申请开票弹层（字段由 InvoiceFormConfig::mobileRender 渲染） -->
<div class="m-sheet-mask" id="invApplyMask">
  <div class="m-sheet" style="max-height:82%;overflow-y:auto">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px">
      <span style="font-size:17px;font-weight:600">申请开票</span>
      <i class="bi bi-x-lg" id="invApplyClose" style="font-size:20px;color:var(--m-text-3)"></i>
    </div>
    <div class="m-field" style="position:relative">
      <label for="mInvContractSearch">关联合同 <span style="color:var(--m-text-3);font-size:12px">（选填）</span></label>
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
    <button class="m-btn m-btn-primary" onclick="submitMInvApply()" style="width:100%"><i class="bi bi-send"></i> 提交申请</button>
  </div>
</div>

<!-- F7：移动端开票弹层（财务） -->
<div class="m-sheet-mask" id="mInvIssueMask">
  <div class="m-sheet">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px">
      <span style="font-size:17px;font-weight:600">确认开票</span>
      <i class="bi bi-x-lg" id="mInvIssueClose" style="font-size:20px;color:var(--m-text-3)"></i>
    </div>
    <div class="m-field"><label for="mInvIssueNo">发票号码 <span style="color:#fa5151">*</span></label><input type="text" class="m-input" id="mInvIssueNo" placeholder="如 FP2026080001"></div>
    <div class="m-field"><label for="mInvIssueDate">开票日期</label><input type="date" class="m-input" id="mInvIssueDate"></div>
    <div style="color:#fa5151;font-size:13px;min-height:18px" id="mInvIssueErr"></div>
    <button class="m-btn m-btn-success" onclick="submitMInvIssue()" style="width:100%">确认开票</button>
  </div>
</div>

<!-- 登记回款：悬浮按钮 + 底部弹层（从工作台快捷操作「登记回款」跳转 /m/finance#add 进入，避免占用工作台大页面） -->
<?php if(!empty($m_can_pay)): ?>
<a href="javascript:;" class="m-fab" id="fabAdd" aria-label="登记回款" style="display:none"><i class="bi bi-plus-lg"></i></a>
<div class="m-sheet-mask" id="payMask">
  <div class="m-sheet">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px">
      <span style="font-size:17px;font-weight:600">登记回款</span>
      <i class="bi bi-x-lg" id="payClose" style="font-size:20px;color:var(--m-text-3)"></i>
    </div>
    <div class="m-field">
      <label for="payContractSearch">选择合同</label>
      <div style="position:relative">
        <input type="text" class="m-input" id="payContractSearch" placeholder="输入合同编号或标题搜索…" autocomplete="off">
        <div class="sug" id="paySug" style="position:absolute;left:0;right:0;top:100%;background:#fff;border:1px solid var(--m-line);border-radius:8px;max-height:200px;overflow:auto;z-index:120;display:none;box-shadow:0 4px 12px rgba(0,0,0,.08)"></div>
        <input type="hidden" id="payContractId" value="0">
      </div>
    </div>
    <div style="display:flex;gap:10px">
      <div class="m-field" style="flex:1;margin-bottom:0"><label for="payAmount">回款金额</label><input type="number" class="m-input" id="payAmount" placeholder="¥" min="0" step="0.01"></div>
      <div class="m-field" style="flex:1;margin-bottom:0"><label for="payDate">计划日期</label><input type="date" class="m-input" id="payDate"></div>
    </div>
    <div class="m-field" style="margin-top:14px;margin-bottom:0"><label for="payMethod">付款方式</label>
      <select class="m-input" id="payMethod">
        <option value="">- 请选择 -</option>
        <?php foreach (dict_options('payment_method') as $__pc => $__pl): ?><option value="<?=htmlspecialchars($__pc)?>"><?=htmlspecialchars($__pl)?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="m-field" style="margin-top:14px;margin-bottom:0"><label for="payMilestone">里程碑（可选）</label>
      <select class="m-input" id="payMilestone">
        <option value="">- 请选择 -</option>
        <?php foreach (dict_options('payment_milestone') as $__mc => $__ml): ?><option value="<?=htmlspecialchars($__mc)?>"><?=htmlspecialchars($__ml)?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="m-field" style="margin-top:14px;margin-bottom:0"><label for="payDesc">备注（可选）</label><input type="text" class="m-input" id="payDesc" placeholder="备注"></div>
    <button class="m-btn m-btn-ghost" id="copyPrevBtn" style="width:100%;margin-top:8px"><i class="bi bi-arrow-repeat"></i> 复制自上期（需先选择合同）</button>
    <button class="m-btn m-btn-brand" id="payBtn" style="width:100%;margin-top:8px"><i class="bi bi-plus-lg"></i> 登记回款</button>
  </div>
</div>
<?php endif; ?>

<!-- 确认收款：底部弹层（回款列表行内操作，部分确认金额 ≤ 应收，剩余自动拆为待收） -->
<?php if(!empty($m_can_pay)): ?>
<div class="m-sheet-mask" id="payConfirmMask">
  <div class="m-sheet">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px">
      <span style="font-size:17px;font-weight:600">确认收款</span>
      <i class="bi bi-x-lg" id="payConfirmClose" style="font-size:20px;color:var(--m-text-3)"></i>
    </div>
    <div class="m-field">
      <label for="payConfirmAmt">收款金额（元）</label><input type="number" class="m-input" id="payConfirmAmt" min="0.01" step="0.01">
    </div>
    <div style="display:flex;gap:10px">
      <div class="m-field" style="flex:1;margin-bottom:0"><label for="payConfirmMethod">收款方式</label>
        <select class="m-input" id="payConfirmMethod">
          <option value="">- 请选择 -</option>
          <?php foreach (dict_options('payment_method') as $__cc => $__cl): ?><option value="<?=htmlspecialchars($__cc)?>"><?=htmlspecialchars($__cl)?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="m-field" style="flex:1;margin-bottom:0"><label for="payConfirmDate">实际日期</label><input type="date" class="m-input" id="payConfirmDate"></div>
    </div>
    <div class="m-field" style="margin-top:10px;margin-bottom:0"><label for="payConfirmInvoice">发票号码（选填）</label><input type="text" class="m-input" id="payConfirmInvoice" placeholder="如 FP2026070001"></div>
    <input type="hidden" id="payConfirmId" value="0">
    <div class="text-danger small" id="payConfirmErr" style="min-height:18px"></div>
    <button class="m-btn m-btn-brand" id="payConfirmBtn" style="width:100%;margin-top:4px">确认收款</button>
  </div>
</div>
<?php endif; ?>

<div class="m-toast" id="toast"></div>

<script>
window.__mCanPay = <?= !empty($m_can_pay) ? 'true' : 'false' ?>;
(function(){

  // P2：弹层内输入聚焦时滚动到可视区，防止移动端键盘弹起遮挡底部字段/提交按钮
  // 仅作用于 .m-sheet 弹层内的焦点元素（scrollIntoView 只滚动其可滚动的弹层容器），不影响整页滚动
  document.addEventListener('focusin', function(e){
    var t = e.target;
    if(!t || !t.closest) return;
    if(t.closest('.m-sheet') && typeof t.scrollIntoView === 'function'){ t.scrollIntoView({block:'center'}); }
  });

  function money(n){ return '¥' + (parseFloat(n||0)).toLocaleString('zh-CN',{minimumFractionDigits:2,maximumFractionDigits:2}); }
  function money0(n){ return '¥' + (parseFloat(n||0)).toLocaleString('zh-CN',{maximumFractionDigits:0}); }

  // 周期筛选（本月/本季/本年/累计）：拉取 /ajax/mobile/finance-summary 更新收支概览
  function loadFinSummary(period){
    $ajax('/ajax/mobile/finance-summary?period=' + period, {loading:false}).then(function(res){
        if(res.code !== 0 || !res.data) return;
        var d = res.data;
        var label = d.period_label || '累计';
        var by = (period === 'all') ? '' : '（' + label + '新增）';
        document.getElementById('finSalesTotal').textContent = money0(d.sales.total);
        document.getElementById('finSalesCnt').textContent = parseInt(d.sales.cnt||0) + ' 份';
        document.getElementById('finSalesLabel').textContent = '销售合同（应收）' + by;
        document.getElementById('finPurchaseTotal').textContent = money0(d.purchase.total);
        document.getElementById('finPurchaseCnt').textContent = parseInt(d.purchase.cnt||0) + ' 份';
        document.getElementById('finPurchaseLabel').textContent = '采购合同（应付）' + by;
        // v2.38.3 回款预测
        var fEl = document.getElementById('finForecast');
        // 质量修复：预测为 0 时也刷新（原 && d.forecast30 在 0 时不更新，残留旧值）
        if(fEl){
          // 与顶部横排布局一致（左大数字 + 右标签列）
          fEl.innerHTML = '<div class="hero">'+money0(d.forecast60)+'</div><div class="info"><div class="sub">预计未来60天回款</div><div class="sub2">30天内 '+money0(d.forecast30)+'</div></div>';
        }
      })
      .catch(function(){});
  }
  document.querySelectorAll('.m-chip[data-finperiod]').forEach(function(c){
    c.addEventListener('click', function(){
      document.querySelectorAll('.m-chip[data-finperiod]').forEach(function(x){ x.classList.toggle('active', x===c); });
      loadFinSummary(this.dataset.finperiod);
    });
  });

  // ---- 回款（分页加载） ----
  var payPage = 1, payFinished = false, payLoading = false;
  function loadPayment(reset){
    if(payLoading) return;
    if(reset){ payPage = 1; payFinished = false; }
    if(payFinished) return;
    payLoading = true;
    var box = document.getElementById('list-payment');
    var lm = document.getElementById('lm-payment');
    if(reset) box.innerHTML = '<div class="m-empty"><i class="bi bi-arrow-repeat"></i>加载中…</div>';
    $ajax('/ajax/finance/payment-list?page=' + payPage, {loading:false}).then(function(res){
        payLoading = false;
        var list = res.data || [];
        if(reset) box.innerHTML = '';
        if(!list.length){
          payFinished = true;
          if(reset) box.innerHTML = '<div class="m-empty"><i class="bi bi-cash-coin"></i>暂无回款计划</div>';
          lm.style.display = 'none';
          return;
        }
        var h = '';
        list.forEach(function(r){
          var st = r.status === 'PAID' ? '<span class="m-tag m-tag-ok">已收</span>'
                 : (r.status === 'OVERDUE' ? '<span class="m-tag m-tag-danger">逾期</span>' : '<span class="m-tag m-tag-warn">待收</span>');
          // 部分确认拆出的剩余待收子记录（parent_id>0）标「剩余回款」小标
          var remainTag = (parseInt(r.parent_id||0) > 0) ? '<span class="m-tag m-tag-rest">剩余回款</span>' : '';
          // 逾期回款确认按钮标红警示，待收用常规绿色
          var act = (r.status === 'PAID' || !window.__mCanPay) ? ''
            : (r.status === 'OVERDUE'
              ? '<button class="m-btn m-btn-sm m-btn-danger" data-pid="'+r.id+'" data-amt="'+parseFloat(r.amount||0)+'" style="flex:none">确认到账（逾期）</button>'
              : '<button class="m-btn m-btn-sm m-btn-ok" data-pid="'+r.id+'" data-amt="'+parseFloat(r.amount||0)+'" style="flex:none">确认收款</button>');
          h += '<a href="/m/contract/'+r.contract_id+'" class="fin-card">'
             + '<div class="fin-top"><div class="fin-t">'+esc(r.contract_title||'(未命名合同)')+'</div><div class="fin-tags">'+st+remainTag+'</div></div>'
             + '<div class="fin-mid"><span class="fin-amt amt-in">'+money(r.amount)+'</span><span class="fin-meta">'+(r.planned_date||'-')+(r.description?(' · '+esc(r.description)):'')+(r.invoice_no?(' · 发票 '+esc(r.invoice_no)):'')+'</span></div>'
             + (act ? '<div class="fin-act">'+act+'</div>' : '') + '</a>';
        });
        box.insertAdjacentHTML('beforeend', h);
        var rendered = box.querySelectorAll('.fin-card').length;
        if(res.total && rendered >= res.total){ payFinished = true; lm.style.display = 'none'; }
        else { lm.style.display = 'block'; }
        payPage++;
      })
      .catch(function(){ payLoading = false; if(reset) box.innerHTML = '<div class="m-empty"><i class="bi bi-exclamation-triangle"></i>加载失败</div>'; });
  }

  // Tab 切换
  var loaded = {};
  var fabAdd = null;
  function switchTab(tab){
    document.querySelectorAll('.m-chip[data-tab]').forEach(function(c){ c.classList.toggle('active', c.dataset.tab === tab); });
    document.getElementById('panel-payment').style.display = tab === 'payment' ? '' : 'none';
    if(fabAdd) fabAdd.style.display = (tab === 'payment') ? 'flex' : 'none';
    if(!loaded[tab]){
      if(tab === 'payment') loadPayment(true);
      loaded[tab] = true;
    }
  }
  document.querySelectorAll('.m-chip[data-tab]').forEach(function(c){
    c.addEventListener('click', function(){ switchTab(this.dataset.tab); });
  });
  document.getElementById('lm-payment').addEventListener('click', function(){ loadPayment(false); });

  // 登记回款：FAB + 底部弹层（无 payment:create 权限时弹层不渲染，跳过整块事件绑定）
  fabAdd = document.getElementById('fabAdd');
  var payMask = document.getElementById('payMask');
  if(payMask){
  var paySearch = document.getElementById('payContractSearch');
  var paySug = document.getElementById('paySug');
  var payHid = document.getElementById('payContractId');
  var payTimer = null;
  function openPaySheet(){ payMask.classList.add('show'); }
  function closePaySheet(){ payMask.classList.remove('show'); if(paySug) paySug.style.display='none'; }
  if(fabAdd){ fabAdd.addEventListener('click', openPaySheet); }
  document.getElementById('payClose').addEventListener('click', closePaySheet);
  payMask.addEventListener('click', function(e){ if(e.target === payMask) closePaySheet(); });
  paySearch.addEventListener('input', function(){
    var q = this.value.trim(); payHid.value = '0'; clearTimeout(payTimer);
    if(q.length < 1){ paySug.style.display='none'; return; }
    payTimer = setTimeout(function(){
      $ajax('/ajax/contract/search?q=' + encodeURIComponent(q), {loading:false}).then(function(res){
        var list = res.data || [];
        var h = '';
        list.forEach(function(c){
          if(c.trade_attr == 0) return; // 非交易合同不计入收支
          h += '<div data-id="'+c.id+'" data-label="'+esc(c.contract_no+' '+c.title)+'"><strong>'+esc(c.title)+'</strong><br><span class="text-muted" style="font-size:12px">'+esc(c.contract_no)+'</span></div>';
        });
        if(!h){ paySug.innerHTML = '<div style="padding:9px 12px;font-size:13px;color:#b0b5bd">无匹配合同</div>'; }
        else { paySug.innerHTML = h; }
        paySug.style.display = 'block';
        paySug.querySelectorAll('div[data-id]').forEach(function(el){
          el.addEventListener('click', function(){ payHid.value = this.dataset.id; paySearch.value = this.dataset.label; paySug.style.display='none'; });
        });
      });
    }, 250);
  });
  document.addEventListener('click', function(e){ if(paySug && !paySug.contains(e.target) && e.target !== paySearch){ paySug.style.display='none'; } });
  document.getElementById('payBtn').addEventListener('click', function(){
    var cid = parseInt(payHid.value || '0', 10);
    var amt = parseFloat(document.getElementById('payAmount').value || '0');
    var date = document.getElementById('payDate').value;
    var desc = document.getElementById('payDesc').value;
    if(!cid){ toast('请先选择合同'); return; }
    if(!(amt > 0)){ toast('请输入正确的回款金额'); return; }
    var payMethod = document.getElementById('payMethod').value;
    if(!payMethod){ toast('请选择付款方式'); return; }
    var btn = this; btn.disabled = true; btn.textContent = '提交中…';
    var fd = new FormData();
    fd.append('contract_id', cid);
    fd.append('amount', amt);
    fd.append('planned_date', date);
    fd.append('description', desc);
    fd.append('payment_type', 'RECEIVABLE');
    fd.append('payment_method', payMethod);
    fd.append('milestone', document.getElementById('payMilestone').value);
    $ajax('/ajax/payment/add', {method:'POST', body:fd, loading:false}).then(function(res){
      btn.disabled = false; btn.innerHTML = '<i class="bi bi-plus-lg"></i> 登记回款';
      if(res.code === 0){
        toast('回款登记成功');
        document.getElementById('payAmount').value=''; document.getElementById('payDate').value='';
        document.getElementById('payMethod').value=''; document.getElementById('payMilestone').value='';
        document.getElementById('payDesc').value=''; paySearch.value=''; payHid.value='0';
        closePaySheet(); payFinished=false; payPage=1; loadPayment(true);
      } else {
        toast(res.msg || '登记失败');
      }
    })
    .catch(function(){ btn.disabled=false; btn.innerHTML='<i class="bi bi-plus-lg"></i> 登记回款'; toast('网络异常，请重试'); });
  });
  // M14：复制自上期 —— 调 copy-prev 预填登记弹层，生成新一期回款计划
  var copyPrevBtn = document.getElementById('copyPrevBtn');
  if(copyPrevBtn){
    copyPrevBtn.addEventListener('click', function(){
      var cid = parseInt(payHid.value || '0', 10);
      if(!cid){ toast('请先选择合同'); return; }
      var b = this; b.disabled = true; b.innerHTML = '<i class="bi bi-arrow-repeat"></i> 载入中…';
      var fdc = new FormData(); fdc.append('contract_id', cid);
      $ajax('/ajax/payment/copy-prev', {method:'POST', body:fdc, loading:false}).then(function(res){
        b.disabled = false; b.innerHTML = '<i class="bi bi-arrow-repeat"></i> 复制自上期（需先选择合同）';
        if(res.code === 0){
          var d = res.data || {};
          document.getElementById('payAmount').value = d.amount || '';
          document.getElementById('payDate').value = d.planned_date || '';
          // 付款方式下拉：历史值不在字典时动态补一个选项，避免复制上期丢失原值
          var $pmEl = document.getElementById('payMethod'), pmv = d.payment_method || '';
          if (pmv && !Array.prototype.some.call($pmEl.options, function(o){ return o.value === pmv; })) {
            var $po = document.createElement('option'); $po.value = pmv; $po.textContent = pmv; $pmEl.appendChild($po);
          }
          $pmEl.value = pmv;
          // 里程碑下拉：历史值不在字典时动态补一个选项，避免复制上期丢失原值
          var $ms = document.getElementById('payMilestone'), mv = d.milestone || '';
          if (mv && !Array.prototype.some.call($ms.options, function(o){ return o.value === mv; })) {
            var $o = document.createElement('option'); $o.value = mv; $o.textContent = mv; $ms.appendChild($o);
          }
          $ms.value = mv;
          document.getElementById('payDesc').value = (d.description || '') + ((d.description) ? '（复制自上期）' : '复制自上期');
          toast('已载入上期回款计划，请核对后登记');
        } else {
          toast(res.msg || '无上期回款计划可复制');
        }
      }).catch(function(){ b.disabled=false; b.innerHTML='<i class="bi bi-arrow-repeat"></i> 复制自上期（需先选择合同）'; toast('网络异常，请重试'); });
    });
  }
  // 从工作台快捷操作带 #add 进入时自动打开回款登记弹层（#apply 开票弹窗见脚本末尾 v2.38.18）
  if(location.hash === '#add'){ openPaySheet(); }
  }

  // 确认收款（回款列表行内操作，部分确认金额 ≤ 应收，剩余自动拆为待收）
  var payConfirmMask = document.getElementById('payConfirmMask');
  var payConfirmId = document.getElementById('payConfirmId');
  var payConfirmAmt = document.getElementById('payConfirmAmt');
  var payConfirmErr = document.getElementById('payConfirmErr');
  function openPayConfirm(id, amount){
    payConfirmId.value = id;
    payConfirmAmt.value = amount || 0;
    payConfirmAmt.max = amount || 0;
    document.getElementById('payConfirmDate').value = new Date().toISOString().slice(0,10);
    document.getElementById('payConfirmInvoice').value = '';
    payConfirmErr.textContent = '';
    payConfirmMask.classList.add('show');
  }
  function closePayConfirm(){ payConfirmMask.classList.remove('show'); }
  if(payConfirmMask){
    payConfirmMask.addEventListener('click', function(e){ if(e.target === payConfirmMask) closePayConfirm(); });
    document.getElementById('payConfirmClose').addEventListener('click', closePayConfirm);
    document.getElementById('payConfirmBtn').addEventListener('click', function(){
      var id = parseInt(payConfirmId.value || '0', 10);
      var amt = parseFloat(payConfirmAmt.value || '0');
      if(!(amt > 0)){ payConfirmErr.textContent = '请输入正确的收款金额'; return; }
      var pm = document.getElementById('payConfirmMethod').value;
      if(!pm){ payConfirmErr.textContent = '请选择收款方式'; return; }
      var btn = this; btn.disabled = true; btn.textContent = '提交中…';
      var params = new URLSearchParams();
      params.append('id', id);
      params.append('confirm_amount', amt);
      params.append('payment_method', pm);
      params.append('actual_date', document.getElementById('payConfirmDate').value);
      params.append('invoice_no', (document.getElementById('payConfirmInvoice').value || '').trim());
      $ajax('/ajax/payment/confirm', {method:'POST', body:params, loading:false})
      .then(function(res){
        btn.disabled = false; btn.textContent = '确认收款';
        if(res.code === 0){
          toast('确认收款成功');
          closePayConfirm();
          payFinished = false; payPage = 1; loadPayment(true);
        } else {
          payConfirmErr.textContent = res.msg || '操作失败';
        }
      })
      .catch(function(){ btn.disabled = false; btn.textContent = '确认收款'; payConfirmErr.textContent = '网络异常，请重试'; });
    });
    // 事件委托：回款卡片「确认收款」按钮
    document.getElementById('list-payment').addEventListener('click', function(e){
      var b = e.target.closest('[data-pid]');
      if(b){ e.preventDefault(); openPayConfirm(b.dataset.pid, b.dataset.amt); }
    });
  }

  // 首屏（v2.38.2：内联脚本在 app.js 之前执行，$ajax 尚未定义 → 延迟到 DOMContentLoaded 后初始化，避免 ReferenceError 导致"加载中…"卡死）
  document.addEventListener('DOMContentLoaded', function(){
    loadPayment(true); loaded.payment = true;
  });
})();

// ===== F7：移动端发票（申请→审批→开票）=====
(function(){
  var invTab = 'mine', invPage = 1, invDone = false, invLoading = false;
  var mInvContractId = 0, mInvIssueId = 0;

  function mBadge(s){
    var m = {PENDING_APPROVAL:'<span class="m-tag m-tag-warn">待审批</span>',APPROVED:'<span class="m-tag" style="background:#e6f1fb;color:#185fa5">待开票</span>',REJECTED:'<span class="m-tag m-tag-danger">已驳回</span>',ISSUED:'<span class="m-tag m-tag-ok">已开票</span>',VOID:'<span class="m-tag">已作废</span>',RED:'<span class="m-tag m-tag-danger">已红冲</span>',CANCELLED:'<span class="m-tag">已撤回</span>',APPLIED:'<span class="m-tag">申请中（旧）</span>'};
    return m[s] || esc(s);
  }
  function mInvCard(v, act){
    return '<div class="m-card" style="padding:12px 14px;border:1px solid var(--m-border);border-radius:12px;margin-bottom:10px;background:#fff">'
      + '<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px">'
      + '<div style="flex:1;min-width:0"><div style="font-size:15px;font-weight:500;color:var(--m-text-1)">'+esc(v.content_desc||'—')+'</div>'
      + '<div style="font-size:12px;color:var(--m-text-3);margin-top:4px">'+(v.invoice_title?esc(v.invoice_title):'')+(v.our_company_name?(' · '+esc(v.our_company_name)):'')+(v.applicant_name?(' · '+esc(v.applicant_name)):'')+'</div></div>'
      + mBadge(v.status)+'</div>'
      + '<div style="display:flex;justify-content:space-between;align-items:center;margin-top:8px">'
      + '<span style="font-size:16px;font-weight:600;color:var(--m-text-1)">¥'+parseFloat(v.amount||0).toLocaleString()+'</span>'
      + '<span style="font-size:12px;color:var(--m-text-3)">'+(v.created_at||v.submitted_at||'')+'</span></div>'
      + (act ? '<div style="margin-top:8px;display:flex;gap:8px">'+act+'</div>' : '') + '</div>';
  }

  window.switchMTab = function(tab){
    document.getElementById('panel-payment').style.display = tab === 'payment' ? '' : 'none';
    document.getElementById('panel-invoice').style.display = tab === 'invoice' ? '' : 'none';
    document.getElementById('finPeriodRow').style.display = tab === 'payment' ? 'flex' : 'none';
    var fab = document.getElementById('fabAdd'); if(fab) fab.style.display = tab === 'payment' ? '' : 'none';
    document.querySelectorAll('[data-mtab]').forEach(function(c){ c.classList.toggle('active', c.dataset.mtab === tab); });
    if(tab === 'invoice'){ loadInv(true); }
  };

  window.switchInvTab = function(tab){
    invTab = tab;
    ['mine','pending','issue'].forEach(function(t){
      document.getElementById('list-inv-' + t).style.display = t === tab ? 'block' : 'none';
    });
    document.querySelectorAll('[data-invtab]').forEach(function(c){ c.classList.toggle('active', c.dataset.invtab === tab); });
    loadInv(true);
  };

  window.loadInv = function(reset){
    if(invLoading) return;
    if(reset){ invPage = 1; invDone = false; }
    if(invDone) return;
    invLoading = true;
    var url = '/ajax/invoice/' + (invTab === 'mine' ? 'my-list' : invTab === 'pending' ? 'pending-approval' : 'pending-issue') + '?page=' + invPage;
    $ajax(url, {loading:false}).then(function(res){
      invLoading = false;
      var list = (res && res.data) || [], total = (res && res.count) || 0;
      var box = document.getElementById('list-inv-' + invTab);
      if(reset) box.innerHTML = '';
      if(!list.length && reset){ box.innerHTML = '<div class="m-empty">暂无相关记录</div>'; }
      list.forEach(function(v){
        var act = '';
        if(invTab === 'mine'){
          if(v.status === 'REJECTED'){ act = '<a href="javascript:;" class="m-chip" onclick="mResubmit('+v.id+')">重新提交</a>'; }
          else if(v.status === 'PENDING_APPROVAL' && v.inst_id){ act = '<a href="javascript:;" class="m-chip" onclick="mRecall('+v.inst_id+')">撤回</a>'; }
          else if(v.status === 'REJECTED' || v.status === 'CANCELLED'){ act = '<a href="javascript:;" class="m-chip" onclick="mDelInv('+v.id+')">删除</a>'; }
        } else if(invTab === 'pending'){
          act = '<a href="javascript:;" class="m-chip m-chip-primary" onclick="mApprove('+v.inst_id+',1)">通过</a>'
            + '<a href="javascript:;" class="m-chip" onclick="mApprove('+v.inst_id+',0)">驳回</a>';
        } else if(invTab === 'issue'){
          act = '<a href="javascript:;" class="m-chip m-chip-primary" onclick="mOpenIssue('+v.id+')">开票</a>';
        }
        box.insertAdjacentHTML('beforeend', mInvCard(v, act));
      });
      // 加载更多按累计卡片数判定（移动端无 table.rows）
      var lm = document.getElementById('lm-inv');
      var cardCnt = box.querySelectorAll('.m-card').length;
      if(total > 0 && cardCnt >= total){ invDone = true; lm.style.display = 'none'; }
      else if(total > cardCnt){ lm.style.display = 'block'; }
      else { lm.style.display = 'none'; invDone = true; }
    }).catch(function(){
      invLoading = false;
      // 弱网/断网静默失败整改：面板内展示「加载失败，点击重试」，点击重新拉取
      var box = document.getElementById('list-inv-' + invTab);
      if(reset && box) box.innerHTML = '<div class="m-empty" style="cursor:pointer" onclick="loadInv(true)"><i class="bi bi-exclamation-triangle"></i>加载失败，点击重试</div>';
    });
  };

  var __mApprActing = false; // P2-09：审批动作防重复连点锁
  window.mApprove = function(instId, pass){
    if(__mApprActing) return; __mApprActing = true; // P2-09：mConfirm 确认后到请求结束期间防重复触发
    // 2026-08-03 修复：原生 prompt/confirm 在钉钉 webview 无反应 → 改用 mPrompt/mConfirm（mobile-common.js 自定义弹窗）
    var doAction = function(c){
      var body = new URLSearchParams({action: pass ? 'APPROVED' : 'REJECTED'});
      if(!pass) body.append('comment', c.trim());
      $ajax('/ajax/approval/' + instId + '/action', {method:'POST', body: body, loading:true}).then(function(res){
        __mApprActing = false;
        toast(res.msg || '操作完成', res.code === 0 ? 'success' : 'error');
        if(res.code === 0) loadInv(true);
      }).catch(function(){ __mApprActing = false; });
    };
    if(!pass){
      mPrompt('驳回意见（选填）：', '', function(c){
        mConfirm('确认驳回该开票申请？', function(){ doAction(c); });
      });
      return;
    }
    mConfirm('确认通过该开票申请？', function(){ doAction(''); });
  };
  window.mRecall = function(instId){
    mConfirm('确认撤回该申请？', function(){
      $ajax('/ajax/approval/' + instId + '/recall', {method:'POST', body:new URLSearchParams({}), loading:true}).then(function(res){
        toast(res.msg || '已撤回', res.code === 0 ? 'success' : 'error'); if(res.code === 0) loadInv(true);
      }).catch(function(){});
    });
  };
  window.mResubmit = function(id){
    mConfirm('确认重新提交审批？', function(){
      $ajax('/ajax/invoice/resubmit', {method:'POST', body:new URLSearchParams({id:id}), loading:true}).then(function(res){
        toast(res.msg || '已提交', res.code === 0 ? 'success' : 'error'); if(res.code === 0) loadInv(true);
      }).catch(function(){});
    });
  };
  window.mDelInv = function(id){
    mConfirm('确认删除该申请？', function(){
      $ajax('/ajax/invoice/delete', {method:'POST', body:new URLSearchParams({id:id}), loading:true}).then(function(res){
        toast(res.msg || '已删除', res.code === 0 ? 'success' : 'error'); if(res.code === 0) loadInv(true);
      }).catch(function(){});
    });
  };

  // 申请弹层
  window.showMInvApply = function(){
    document.getElementById('mInvErr').textContent = '';
    document.getElementById('mInvContractId').value = 0;
    document.getElementById('mInvContractSearch').value = '';
    var sg0 = document.getElementById('mInvSuggest'); if(sg0) sg0.style.display = 'none';
    var f = document.getElementById('mInvFields');
    // v2.41.0：开票客户搜索选择器重置（隐藏 id 归 0，输入框清空）
    f.querySelectorAll('.cs-wrap').forEach(function(w){
      var ci = w.querySelector('.cs-input'); if(ci) ci.value = '';
      var hid = w.querySelector('.cs-id'); if(hid) hid.value = '0';
    });
    var co = f.querySelector('select[name="our_company_id"]');
    if(co && co.options.length > 1) co.selectedIndex = 1;
    var amt = f.querySelector('input[name="amount"]'); if(amt) amt.value = '';
    var calc = document.getElementById('mInvTaxCalc'); if(calc) calc.style.display = 'none';
    document.getElementById('invApplyMask').classList.add('show');
    // H2：含税金额价税实时展示绑定（金额输入即时刷新；税率随主体带出由 refreshMInvRate 维护）
    var af = document.getElementById('mInvFields');
    if(af){
      var amtEl = af.querySelector('input[name="amount"]');
      if(amtEl){
        var refresh = function(){ mCalcInvTax(); };
        amtEl.addEventListener('input', refresh);
        amtEl.addEventListener('change', refresh);
      }
    }
    // 2026-08-02：初始按默认主体带出税率并展示价税
    refreshMInvRate();
  };
  /** 2026-08-02：移动端开票税率随主体带出——选择开票主体后从 option data-rate 读取并写入隐藏税率字段 */
  function refreshMInvRate(){
    var f = document.getElementById('mInvFields');
    if(!f) return;
    var co = f.querySelector('select[name="our_company_id"]');
    var rate = f.querySelector('input[name="tax_rate"]');
    if(!co || !rate) return;
    var opt = co.options[co.selectedIndex];
    var r = opt ? (opt.getAttribute('data-rate') || '') : '';
    if(r !== '') rate.value = r;
    mCalcInvTax();
  }
  // 绑定：切换开票主体即时带出税率
  (function(){
    var f = document.getElementById('mInvFields');
    if(!f) return;
    var co = f.querySelector('select[name="our_company_id"]');
    if(co) co.addEventListener('change', refreshMInvRate);
  })();
  /** 移动端含税金额价税拆分展示 */
  function mCalcInvTax(){
    var f = document.getElementById('mInvFields'), box = document.getElementById('mInvTaxCalc');
    if(!f || !box) return;
    var amt = f.querySelector('[name="amount"]'), rate = f.querySelector('[name="tax_rate"]');
    if(!amt || !rate){ box.style.display = 'none'; return; }
    var a = parseFloat(amt.value), r = parseFloat(rate.value);
    if(!(a > 0) || !(r > 0) || r >= 1){ box.style.display = 'none'; return; }
    var tax = Math.round(a / (1 + r) * r * 100) / 100;
    var net = Math.round((a - tax) * 100) / 100;
    box.textContent = '含税 ¥' + a.toFixed(2) + ' = 不含税 ¥' + net.toFixed(2) + ' + 税额 ¥' + tax.toFixed(2) + '（' + (r * 100) + '%）';
    box.style.display = '';
  }
  function hideMInvApply(){ document.getElementById('invApplyMask').classList.remove('show'); }
  window.submitMInvApply = function(){
    var err = document.getElementById('mInvErr');
    var fd = new FormData();
    fd.append('contract_id', document.getElementById('mInvContractId').value || 0);
    document.querySelectorAll('#mInvFields [name]').forEach(function(el){ fd.append(el.name, el.value); });
    var miss = [];
    document.querySelectorAll('#mInvFields [required]').forEach(function(el){
      if(!el.value.trim()){ var lb = el.closest('.m-field').querySelector('label'); miss.push(lb ? lb.textContent.replace('*','').trim() : el.name); }
    });
    if(miss.length){ err.textContent = '请填写：' + miss.join('、'); return; }
    err.textContent = '提交中…';
    $ajax('/ajax/invoice/add', {method:'POST', body:fd, loading:false}).then(function(res){
      err.textContent = '';
      toast(res.msg || '提交成功', res.code === 0 ? 'success' : 'error');
      if(res.code === 0){ hideMInvApply(); switchInvTab('mine'); }
    }).catch(function(){ err.textContent = '网络异常，请重试'; });
  };

  // 开票弹层（财务）
  window.mOpenIssue = function(id){
    mInvIssueId = id;
    document.getElementById('mInvIssueNo').value = '';
    document.getElementById('mInvIssueDate').value = new Date().toISOString().slice(0,10);
    document.getElementById('mInvIssueErr').textContent = '';
    document.getElementById('mInvIssueMask').classList.add('show');
  };
  window.submitMInvIssue = function(){
    var no = document.getElementById('mInvIssueNo').value.trim();
    var err = document.getElementById('mInvIssueErr');
    if(!no){ err.textContent = '请填写发票号码'; return; }
    err.textContent = '提交中…';
    var body = new URLSearchParams({id:mInvIssueId, invoice_no:no, issued_date:document.getElementById('mInvIssueDate').value});
    $ajax('/ajax/invoice/update', {method:'POST', body:body, loading:false}).then(function(res){
      err.textContent = '';
      toast(res.msg || '开票成功', res.code === 0 ? 'success' : 'error');
      if(res.code === 0){ document.getElementById('mInvIssueMask').classList.remove('show'); switchInvTab('issue'); }
    }).catch(function(){ err.textContent = '网络异常，请重试'; });
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
              if(c){ mInvContractId = c.id; mSearch.value = c.title; mSuggest.style.display = 'none'; }
            });
          });
        }).catch(function(){});
      }, 300);
    });
    document.addEventListener('click', function(e){
      if(mSuggest && e.target !== mSearch && !mSuggest.contains(e.target)) mSuggest.style.display = 'none';
    });
  }

  document.addEventListener('click', function(e){
    if(e.target.id === 'invApplyClose') hideMInvApply();
    if(e.target.id === 'mInvIssueClose') document.getElementById('mInvIssueMask').classList.remove('show');
  });
  var mInvMask = document.getElementById('invApplyMask');
  if(mInvMask){ mInvMask.addEventListener('click', function(e){ if(e.target === mInvMask) hideMInvApply(); }); }
  var mIssueMask = document.getElementById('mInvIssueMask');
  if(mIssueMask){ mIssueMask.addEventListener('click', function(e){ if(e.target === mIssueMask) mIssueMask.classList.remove('show'); }); }
  var mInvLm = document.getElementById('lm-inv');
  if(mInvLm){ mInvLm.addEventListener('click', function(){ invPage++; loadInv(false); }); }
  // v2.38.18：工作台快捷操作「申请开票」带 #apply 进入 → 自动打开开票申请弹窗（须在 showMInvApply 定义之后执行）
  if(location.hash === '#apply' && typeof window.showMInvApply === 'function'){ showMInvApply(); }
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
<?php $tab = 'more'; include __DIR__ . '/_foot.php'; ?>
