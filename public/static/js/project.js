/**
 * 项目管理 - 列表分页与渲染
 * 依赖：页面需提供 <table id="tableBody"> + <div id="pagination"> + <form id="filterForm">
 */
(function(){
// ---- 状态变量 ----
var p=1,tb=document.getElementById('tableBody'),pg=document.getElementById('pagination'),ff=document.getElementById('filterForm');

// HTML 转义：统一使用 app.js 全局 esc（P3-5：移除本地重复副本）

if(!tb)return;

// 项目状态 → pc-tag 浅底标签映射（2026-08-03 复查：实底 badge 已全站收敛，JS 动态渲染同步对齐）
var badgeMap={ACTIVE:'ok',DONE:'muted',ARCHIVED:'muted'};

/**
 * 加载项目列表（指定页码）
 * @param {number} n 页码
 */
function load(n){
    p=n;
    // 读取筛选条件：关键词 + 状态
    var kw=ff.querySelector('[name="keyword"]'),st=ff.querySelector('[name="status"]');
    var pr=new URLSearchParams({keyword:kw?kw.value:'',status:st?st.value:'',page:p,limit:15});
    // 2026-08-03 复查修复：chip 高亮与筛选条件同步（数据已按 status 加载，但 active 类未跟随）
    document.querySelectorAll('.pc-chips a[data-st]').forEach(function(a){
        a.classList.toggle('active', (a.dataset.st||'') === (pr.get('status')||''));
    });
    // AJAX 请求（统一走 $ajax 封装，X-Requested-With 触发 JSON 返回）
    $ajax('/project?'+pr,{loading:false})
    .then(function(res){
        var h='';
        // ---- 空列表状态 ----
        if(!res.data||!res.data.length){
            h=emptyState({colspan:7,icon:'bi-kanban',title:'暂无项目',btn:'新建项目',href:'/project/create',canCreate:window._canCreateProject});
        }else{
            // ---- 逐行渲染 ----
            res.data.forEach(function(pr){
                var stName=(window._projStatus&&window._projStatus[pr.status])?window._projStatus[pr.status]:pr.status;
                var bg=badgeMap[pr.status]||'secondary';
                var period=((pr.start_date||'')||'-')+' ~ '+((pr.end_date||'')||'-');
                h+='<tr><td><a href="/project/'+pr.id+'">'+esc(pr.name)+'</a></td>';
                h+='<td>'+(esc(pr.code)||'-')+'</td>';
                h+='<td><span class="pc-tag pc-tag-'+bg+'">'+stName+'</span></td>';
                h+='<td>'+(pr.contract_count||0)+'</td>';
                h+='<td>'+(pr.budget>0?'¥'+parseFloat(pr.budget).toLocaleString():'-')+'</td>';
                h+='<td class="small text-muted">'+esc(period)+'</td>';
                h+='<td><a href="/project/'+pr.id+'" class="btn btn-sm btn-outline-secondary" aria-label="查看"><i class="bi bi-eye"></i></a> <a href="/project/'+pr.id+'/edit" class="btn btn-sm btn-outline-secondary" aria-label="编辑"><i class="bi bi-pencil"></i></a></td>';
                h+='</tr>';
            });
        }
        tb.innerHTML=h;
        // ---- 分页控件 ----
        var tp=Math.ceil(res.count/15),ph='';
        for(var i=1;i<=tp;i++){
            ph+='<li class="page-item '+(i===p?'active':'')+'"><a class="page-link" href="#" data-p="'+i+'">'+i+'</a></li>';
        }
        pg.innerHTML=tp>1?'<nav><ul class="pagination pagination-sm justify-content-end mb-0">'+ph+'</ul></nav>':'';
        // 分页点击重新加载
        pg.querySelectorAll('a').forEach(function(a){
            a.addEventListener('click',function(e){e.preventDefault();load(parseInt(this.dataset.p));});
        });
    }).catch(function(){
        // 加载失败：错误已由 $ajax 统一 toast，列表内展示重试入口（避免"加载中"永驻）
        if(tb){
            tb.innerHTML='<tr><td colspan="7" class="text-center py-5 text-muted">'
                +'<i class="bi bi-exclamation-triangle" style="font-size:2rem"></i>'
                +'<div class="mt-2">列表加载失败，请检查网络后重试</div>'
                +'<button type="button" class="btn btn-sm btn-outline-secondary mt-2" id="listRetryBtn"><i class="bi bi-arrow-clockwise"></i> 重新加载</button>'
                +'</td></tr>';
            var rb=document.getElementById('listRetryBtn');
            if(rb) rb.addEventListener('click', function(){ load(p); });
        }
        if(pg) pg.innerHTML='';
    });
}
// 筛选表单提交 → 重新从第 1 页加载
if(ff){ff.addEventListener('submit',function(e){e.preventDefault();load(1);});}
// 初始加载（DCL 防护：确保 app.js 全局 $ajax/esc 就绪，避免 ReferenceError）
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function(){ load(1); });
} else {
    load(1);
}
})();
