/**
 * 公海池 - 无人认领客户列表 + 认领操作
 * 依赖：<table id="tb"> + <div id="pg">
 */
(function(){
// HTML 转义：统一使用 app.js 全局 esc（P3-5：移除本地重复副本）

// ---- 状态与 DOM 引用 ----
var p=1,tb=document.getElementById('tb'),pg=document.getElementById('pg');
if(!tb)return;

/**
 * 加载公海池列表
 * @param {number} n 页码
 */
function load(n){
    p=n;
    // AJAX 请求公海池数据
    $ajax('/customer/pool?page='+n+'&limit=15', { loading: false }).then(function(res){
        var h='';
        // ---- 空池状态 ----
        if(!res.data||!res.data.length){
            h=emptyState({colspan:5,icon:'bi-person-plus',title:'公海池暂无客户',desc:'录入的客户释放后进入公海池，可在此认领'});
        }else{
            // ---- 逐行渲染 ----
            res.data.forEach(function(c){
                h+='<tr><td>'+esc(c.name)+'</td><td>'+(esc(c.credit_code)||'-')+'</td><td>'+(esc(c.contact_name)||'-')+'</td><td>'+(esc(c.contact_mobile)||'-')+'</td><td><button class="btn btn-sm btn-success" onclick="claim('+c.id+')">认领</button></td></tr>';
            });
        }
        tb.innerHTML=h;
        // ---- 分页控件 ----
        var tp=Math.ceil(res.count/15),ph='';
        for(var i=1;i<=tp;i++)ph+='<li class="page-item '+(i===p?'active':'')+'"><a class="page-link" href="#" data-p="'+i+'">'+i+'</a></li>';
        pg.innerHTML='<nav><ul class="pagination pagination-sm justify-content-end mb-0">'+ph+'</ul></nav>';
        pg.querySelectorAll('a').forEach(function(a){a.addEventListener('click',function(e){e.preventDefault();load(parseInt(this.dataset.p));});});
    }).catch(function(){
        // 加载失败：错误已由 $ajax 统一 toast，表格内展示重试入口（避免"加载中"永驻）
        if(tb){
            tb.innerHTML='<tr><td colspan="5" class="text-center py-5 text-muted">'
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

// 认领客户
window.claim = function(id){
    $ajax('/ajax/customer/' + id + '/claim', { method: 'POST', loading: false }).then(function(res){
        if(res.code === 0){ showToast('认领成功','success'); load(p); }
        else showToast(res.msg || '认领失败','error');
    }).catch(function(){ showToast('网络异常，请重试','error'); });
};

// 初始加载
// 2026-08-03 修复：customer_pool.js 在 app.js 前加载（body 顶部），顶层立即 load(1) 时 $ajax 未定义
// → ReferenceError 导致公海池列表加载不出。对齐 contract.js 既有模式：DOMContentLoaded 触发时
// 所有同步脚本（含 footer 的 app.js）已执行完，届时全局 $ajax 已就绪。
function initPool() { load(1); }
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initPool);
} else {
    initPool();
}
})();
