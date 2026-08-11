<?php $title='归档管理'; $menu_active='archive'; include __DIR__.'/../layout/header.php'; ?>
<h4 class="mb-3"><i class="bi bi-archive"></i> 归档管理</h4>
<div class="d-flex justify-content-between align-items-center mb-3">
<div></div>
<a href="javascript:void(0)" class="btn btn-outline-secondary btn-sm" onclick="exportArchived()"><i class="bi bi-download"></i> 导出已归档</a>
</div>
<div class="card stat-card"><div class="table-responsive"><table class="table table-hover mb-0"><thead class="table-light"><tr><th>编号</th><th>标题</th><th>金额</th><th>乙方</th><th>生效</th><th>到期</th></tr></thead><tbody id="tb"></tbody></table></div><div class="card-footer bg-white" id="pg"></div></div>
<script>
// P1-1/P1-2/P1-3：列表加载收敛 $ajax + 失败重试 + emptyState 空态（内联脚本早于 app.js 执行，首载放 DOMContentLoaded）
(function(){
var p=1;
function load(n){
  p=n||1;
  $ajax('/archive?page='+p+'&limit=15', {loading:false}).then(function(res){
    var h='';
    if(!res.data||!res.data.length){
      h=emptyState({colspan:6, icon:'bi-archive', title:'暂无已归档合同', desc:'合同归档后将在这里显示'});
    } else {
      res.data.forEach(function(c){
        h+='<tr><td>'+esc(c.contract_no)+'</td><td><a href="/contract/'+c.id+'">'+esc(c.title)+'</a></td><td>'+parseFloat(c.amount||0).toLocaleString()+'</td><td>'+esc(c.party_b_name)+'</td><td>'+(c.effective_date||'-')+'</td><td>'+(c.expiry_date||'-')+'</td></tr>';
      });
    }
    document.getElementById('tb').innerHTML=h;
    var tp=Math.ceil(res.count/15),ph='';
    for(var i=1;i<=tp;i++)ph+='<li class="page-item '+(i===p?'active':'')+'"><a class="page-link" href="#" data-p="'+i+'">'+i+'</a></li>';
    document.getElementById('pg').innerHTML='<nav><ul class="pagination pagination-sm justify-content-end mb-0">'+ph+'</ul></nav>';
    document.querySelectorAll('#pg a').forEach(function(a){a.addEventListener('click',function(e){e.preventDefault();load(parseInt(this.dataset.p));});});
  }).catch(function(){
    document.getElementById('tb').innerHTML='<tr><td colspan="6" class="text-center py-4"><i class="bi bi-exclamation-triangle text-danger"></i><div class="mt-2">加载失败，请重试</div><button class="btn btn-sm btn-outline-secondary mt-2" onclick="location.reload()">重新加载</button></td></tr>';
  });
}
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', function(){ load(1); });
} else {
  load(1);
}
})();
// P1-5：导出已归档防重复连点 + 进度提示
function exportArchived(){
  if(window.__archivedExporting){ showToast('导出生成中，请稍候…', 'warning'); return; }
  window.__archivedExporting = true;
  showToast('正在生成导出文件…', 'info');
  setTimeout(function(){ window.__archivedExporting = false; }, 5000);
  window.location.href = '/ajax/export/contracts?status=ARCHIVED';
}
</script>
<?php include __DIR__.'/../layout/footer.php'; ?>
