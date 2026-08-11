
(function(){
    // HTML 转义：统一使用 app.js 全局 esc（P3-5：移除本地重复副本）
    var tab='pending',p=1;
    function load(){
        var url;if(tab==='pending')url='/ajax/approval/pending-list';else if(tab==='processed')url='/ajax/approval/processed-list';else url='/ajax/approval/submitted-list';
        $ajax(url+'?page='+p+'&limit=15',{loading:false}).then(function(res){
            var h='';if(!res.data||!res.data.length){h=emptyState({colspan:8,icon:'bi-clipboard-check',title:'暂无审批数据',desc:'暂无相关审批，可在合同详情中提交审批申请'});}else{
                res.data.forEach(function(a){h+='<tr><td>'+esc(a.contract_no)+'</td><td><a href="/approval/'+a.id+'">'+esc(a.contract_title)+'</a></td><td>'+parseFloat(a.amount||0).toLocaleString()+'</td><td>'+esc(a.submitter_name)+'</td><td>'+esc(a.flow_name)+'</td><td>'+statusBadge(a.status)+'</td><td>'+esc(a.submitted_at)+'</td><td><a href="/approval/'+a.id+'" class="btn btn-sm btn-outline-primary">查看</a></td></tr>';});
            }document.getElementById('tb').innerHTML=h;renderPager(res.count);
        }).catch(function(){
            // 加载失败：错误已由 $ajax 统一 toast，表格内展示重试入口（避免"加载中"永驻）
            var tb=document.getElementById('tb');
            if(tb) tb.innerHTML='<tr><td colspan="8" class="text-center py-5 text-muted"><i class="bi bi-exclamation-triangle" style="font-size:2rem"></i><div class="mt-2">列表加载失败，请检查网络后重试</div><button type="button" class="btn btn-sm btn-outline-secondary mt-2" id="listRetryBtn"><i class="bi bi-arrow-clockwise"></i> 重新加载</button></td></tr>';
            var rb=document.getElementById('listRetryBtn');
            if(rb) rb.addEventListener('click', function(){ load(); });
        });
    }
    function renderPager(total){var tp=Math.ceil(total/15),ph='';for(var i=1;i<=tp;i++)ph+='<li class="page-item '+(i===p?'active':'')+'"><a class="page-link" href="#" data-p="'+i+'">'+i+'</a></li>';document.getElementById('pg').innerHTML='<nav><ul class="pagination pagination-sm justify-content-end mb-0">'+ph+'</ul></nav>';document.querySelectorAll('#pg a').forEach(function(a){a.addEventListener('click',function(e){e.preventDefault();p=parseInt(this.dataset.p);load();});});}
    function statusBadge(s){var m={PENDING:'<span class="badge bg-warning">审批中</span>',APPROVED:'<span class="badge bg-success">已通过</span>',REJECTED:'<span class="badge bg-danger">已驳回</span>',RECALLED:'<span class="badge bg-secondary">已撤回</span>',TRANSFERRED:'<span class="badge bg-info">已转交</span>'};return m[s]||s;}
    document.querySelectorAll('#atabs a').forEach(function(a){a.addEventListener('click',function(e){e.preventDefault();tab=this.dataset.tab;p=1;document.querySelectorAll('#atabs a').forEach(function(l){l.classList.remove('active');});this.classList.add('active');load();});});
    // 初始加载（DCL 防护：确保 app.js 全局 $ajax/esc 就绪，避免 ReferenceError）
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', load);
    } else {
        load();
    }
})();
