<?php $title='税务汇总'; $menu_active='finance'; include __DIR__.'/../layout/header.php'; ?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0"><i class="bi bi-receipt"></i> 税务汇总</h4>
  <div>
    <a href="/finance" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> 返回财务中心</a>
    <button class="btn btn-outline-success btn-sm" onclick="exportCsv()"><i class="bi bi-download"></i> 导出</button>
  </div>
</div>
<div class="alert alert-light border small text-muted">
  按发票开具月份汇总：<b>销项税额</b>（销售合同发票）− <b>进项税额</b>（采购合同发票）= <b>当月应纳税额</b>。金额按含税价价税分离计算，仅供报税参考。
</div>
<div class="card stat-card"><div class="table-responsive">
  <table class="table table-hover mb-0" id="taxTable">
    <thead class="table-light">
      <tr>
        <th rowspan="2" class="align-middle">月份</th>
        <th colspan="2" class="text-center border-start">销项（销售）</th>
        <th colspan="2" class="text-center border-start">进项（采购）</th>
        <th rowspan="2" class="align-middle text-center border-start">应纳税额</th>
      </tr>
      <tr>
        <th class="border-start small text-muted">开票金额</th><th class="small text-muted">销项税额</th>
        <th class="border-start small text-muted">开票金额</th><th class="small text-muted">进项税额</th>
      </tr>
    </thead>
    <tbody id="tb"></tbody>
    <tfoot class="table-light fw-bold" id="tf"></tfoot>
  </table>
  <div class="card-footer bg-white text-muted small" id="empty" style="display:none">暂无发票数据</div>
</div></div>
<script>
var taxRows = [];
function fmt(n){ return '¥' + (parseFloat(n||0)).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2}); }
function load(){
  $ajax('/ajax/finance/tax-data', {silent:true}).then(function(res){
    taxRows = res.data || [];
    render();
  }).catch(function(){
    // 加载失败（silent 不 toast）：表格内展示重试入口，避免"加载中"永驻
    var tb = document.getElementById('tb');
    if(tb) tb.innerHTML = '<tr><td colspan="6" class="text-center py-5 text-muted"><i class="bi bi-exclamation-triangle" style="font-size:2rem"></i><div class="mt-2">加载失败，点击重试</div><button type="button" class="btn btn-sm btn-outline-secondary mt-2" onclick="load()"><i class="bi bi-arrow-clockwise"></i> 重新加载</button></td></tr>';
    var em = document.getElementById('empty'); if(em) em.style.display = 'none';
    var tf = document.getElementById('tf'); if(tf) tf.innerHTML = '';
  });
}
function render(){
  var tb = document.getElementById('tb'), tf = document.getElementById('tf');
  if(!taxRows.length){
    // 空态：表格内展示 emptyState 样式（原 empty footer 纯文字隐藏）
    tb.innerHTML = emptyState({colspan:6, icon:'bi-receipt', title:'暂无发票数据'});
    tf.innerHTML = '';
    var em = document.getElementById('empty'); if(em) em.style.display = 'none';
    return;
  }
  document.getElementById('empty').style.display='none';
  var h='', to={oa:0,ot:0,ia:0,it:0,p:0};
  taxRows.forEach(function(r){
    to.oa+=r.output.amount; to.ot+=r.output.tax; to.ia+=r.input.amount; to.it+=r.input.tax; to.p+=r.payable;
    var pc = r.payable>=0 ? 'text-danger' : 'text-success';
    h += '<tr><td>'+r.ym+'</td>'
       + '<td class="border-start">'+fmt(r.output.amount)+'</td><td>'+fmt(r.output.tax)+'</td>'
       + '<td class="border-start">'+fmt(r.input.amount)+'</td><td>'+fmt(r.input.tax)+'</td>'
       + '<td class="border-start '+pc+' fw-bold">'+fmt(r.payable)+'</td></tr>';
  });
  tb.innerHTML=h;
  tf.innerHTML = '<tr><td>合计</td><td class="border-start">'+fmt(to.oa)+'</td><td>'+fmt(to.ot)+'</td>'
       + '<td class="border-start">'+fmt(to.ia)+'</td><td>'+fmt(to.it)+'</td>'
       + '<td class="border-start">'+fmt(to.p)+'</td></tr>';
}
function exportCsv(){
  if(!taxRows.length){ showToast('暂无数据','error'); return; }
  var lines = ['月份,销项开票金额,销项税额,进项开票金额,进项税额,应纳税额'];
  taxRows.forEach(function(r){
    lines.push([r.ym, r.output.amount, r.output.tax, r.input.amount, r.input.tax, r.payable].join(','));
  });
  var blob = new Blob(["\ufeff"+lines.join('\n')], {type:'text/csv;charset=utf-8'});
  var a = document.createElement('a'); a.href = URL.createObjectURL(blob);
  a.download = '税务汇总_'+new Date().toISOString().slice(0,10)+'.csv'; a.click();
}
// 2026-08-03 修复：内联脚本在 footer 的 app.js 之前执行，顶层立即 load() 时 $ajax 未定义
// → ReferenceError 导致税务汇总加载失败。对齐 contract.js 模式：DOMContentLoaded 触发时全局已就绪。
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', function(){ load(); });
} else {
  load();
}
</script>
<?php include __DIR__.'/../layout/footer.php'; ?>
