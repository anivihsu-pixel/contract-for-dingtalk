<?php $title='经营月报'; $menu_active = $menu_active ?? 'finance'; include __DIR__.'/../layout/header.php'; ?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0"><i class="bi bi-bar-chart-line"></i> 经营月报</h4>
  <a href="/finance/tax" class="btn btn-outline-secondary btn-sm"><i class="bi bi-receipt"></i> 税务汇总</a>
</div>

<div class="card stat-card mb-3">
  <div class="card-body">
    <div class="row g-3 align-items-end">
      <div class="col-md-4">
        <label class="form-label small text-muted" for="monthInput">统计月份</label>
        <input type="month" id="monthInput" class="form-control" value="<?=htmlspecialchars($default_month)?>">
        <div class="form-text">口径：仅交易合同；回款指标基于回款计划(RECEIVABLE)；收支方向=当月新增合同。</div>
      </div>
      <div class="col-md-8 d-flex gap-2 flex-wrap">
        <button class="btn btn-primary" id="genBtn"><i class="bi bi-arrow-clockwise"></i> 生成月报</button>
        <a class="btn btn-outline-success" id="exportMonthlyBtn" href="#" onclick="return exportMonthly()"><i class="bi bi-download"></i> 导出本月报 CSV</a>
        <a class="btn btn-outline-primary" href="/ajax/report/dashboard-export"><i class="bi bi-download"></i> 导出驾驶舱数据</a>
      </div>
    </div>
  </div>
</div>

<div class="card stat-card mb-3" id="resultCard" style="display:none">
  <div class="card-header bg-white d-flex justify-content-between align-items-center">
    <span><i class="bi bi-calendar-check me-1 text-primary"></i><span id="resultMonth"></span> 经营月报</span>
  </div>
  <div class="card-body">
    <div class="row g-3 mb-3">
      <div class="col-6 col-md-3"><div class="small text-muted">本月计划应收</div><div class="fs-5 fw-bold text-success" id="mReceivable">-</div></div>
      <div class="col-6 col-md-3"><div class="small text-muted">本月实际回款</div><div class="fs-5 fw-bold text-primary" id="mCollected">-</div></div>
      <div class="col-6 col-md-3"><div class="small text-muted">本月逾期</div><div class="fs-5 fw-bold text-danger" id="mOverdue">-</div></div>
      <div class="col-6 col-md-3"><div class="small text-muted">回款率</div><div class="fs-5 fw-bold" id="mRate">-</div></div>
    </div>
    <div class="row g-3 mb-3">
      <div class="col-6 col-md-3"><div class="small text-muted">应收未收余额</div><div class="fs-6 fw-bold text-warning" id="mUncollected">-</div></div>
      <div class="col-6 col-md-3"><div class="small text-muted">上月计划应收</div><div class="fs-6 fw-bold" id="mPrevRecv">-</div></div>
      <div class="col-6 col-md-3"><div class="small text-muted">上月实际回款</div><div class="fs-6 fw-bold" id="mPrevColl">-</div></div>
      <div class="col-6 col-md-3"><div class="small text-muted">环比(应收)</div><div class="fs-6 fw-bold" id="mMoM">-</div></div>
    </div>
    <div class="table-responsive">
      <table class="table table-sm table-bordered mb-0">
        <thead class="table-light"><tr><th>收支方向（本月新增合同）</th><th>金额</th><th>笔数</th></tr></thead>
        <tbody>
          <tr><td>销售（我方收款 / 应收）</td><td id="dirSalesAmt">-</td><td id="dirSalesCnt">-</td></tr>
          <tr><td>采购（我方付款 / 应付）</td><td id="dirPurchaseAmt">-</td><td id="dirPurchaseCnt">-</td></tr>
        </tbody>
      </table>
    </div>
    <div class="text-muted small mt-2">说明：回款率 = 本月实际回款 ÷ 本月计划应收；环比 = 本月计划应收 − 上月计划应收。</div>
  </div>
</div>

<div class="alert alert-info small">
  <i class="bi bi-info-circle"></i> 经营月报与驾驶舱数据均按您的数据权限范围统计；导出动作将写入操作审计日志。
</div>

<script>
function fmt(n){ return '¥' + (parseFloat(n||0)).toLocaleString('zh-CN', {minimumFractionDigits:2, maximumFractionDigits:2}); }
function pct(n){ return (parseFloat(n||0)).toFixed(1) + '%'; }

function genReport(){
  var month = document.getElementById('monthInput').value;
  if(!month){ showToast('请选择统计月份', 'warning'); return; }
  document.getElementById('exportMonthlyBtn').href = '/ajax/report/monthly-export?month=' + encodeURIComponent(month);
  $ajax('/ajax/report/monthly-data?month=' + encodeURIComponent(month)).then(function(res){
    var d = res.data || {};
    document.getElementById('resultMonth').textContent = month;
    document.getElementById('mReceivable').textContent = fmt(d.receivable);
    document.getElementById('mCollected').textContent = fmt(d.collected);
    document.getElementById('mOverdue').textContent = fmt(d.overdue);
    document.getElementById('mRate').textContent = pct(d.recovery_rate);
    document.getElementById('mUncollected').textContent = fmt(d.uncollected);
    document.getElementById('mPrevRecv').textContent = fmt(d.prev_receivable);
    document.getElementById('mPrevColl').textContent = fmt(d.prev_collected);
    var mom = (d.prev_receivable > 0) ? ((d.receivable - d.prev_receivable) / d.prev_receivable * 100) : 0;
    document.getElementById('mMoM').textContent = (mom>=0?'+':'') + mom.toFixed(1) + '%';
    var dir = d.dir || {};
    document.getElementById('dirSalesAmt').textContent = fmt(dir.sales ? dir.sales.total : 0);
    document.getElementById('dirSalesCnt').textContent = (dir.sales ? dir.sales.cnt : 0) + ' 笔';
    document.getElementById('dirPurchaseAmt').textContent = fmt(dir.purchase ? dir.purchase.total : 0);
    document.getElementById('dirPurchaseCnt').textContent = (dir.purchase ? dir.purchase.cnt : 0) + ' 笔';
    document.getElementById('resultCard').style.display = 'block';
  }).catch(function(){ showToast('生成失败，请重试', 'error'); });
}

function exportMonthly(){
  if(window.__monthlyExporting){ showToast('导出生成中，请稍候…', 'warning'); return false; }
  var month = document.getElementById('monthInput').value;
  if(!month){ showToast('请先选择统计月份', 'warning'); return false; }
  window.__monthlyExporting = true;
  showToast('正在生成导出文件…', 'info');
  setTimeout(function(){ window.__monthlyExporting = false; }, 5000);
  window.location.href = '/ajax/report/monthly-export?month=' + encodeURIComponent(month);
  return false;
}

document.getElementById('genBtn').addEventListener('click', genReport);
// 进入页面默认生成当月
// 2026-08-03 修复：内联脚本在 footer 的 app.js 之前执行，顶层立即 genReport() 时 $ajax 未定义
// → ReferenceError 导致默认统计加载失败。对齐 contract.js 模式：DOMContentLoaded 触发时全局已就绪。
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', function(){ genReport(); });
} else {
  genReport();
}
</script>
<?php include __DIR__.'/../layout/footer.php'; ?>
