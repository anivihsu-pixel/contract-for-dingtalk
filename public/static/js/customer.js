/**
 * 客户管理 - 列表分页渲染（桌面 + 移动端卡片自适应）
 * 依赖：<table id="tableBody"> + <div id="pagination">
 */
(function(){
// ---- 状态变量 ----
var p=1,tb=document.getElementById('tableBody'),pg=document.getElementById('pagination');

// HTML 转义：统一使用 app.js 全局 esc（P3-5：移除本地重复副本）

if(!tb)return;

/**
 * 加载客户列表
 * @param {number} n 页码
 */
function load(n){
    p=n;
    var kw=document.querySelector('[name="keyword"]');
    // v2.38.9：生命周期筛选（漏斗点击设置 window._lifecycleActive，清除用 clearLcFilter）
    var pr=new URLSearchParams({keyword:kw?(kw.value||''):'',page:p,limit:15});
    if(window._lifecycleActive) pr.set('lifecycle_status', window._lifecycleActive);
    // AJAX 请求（统一走 $ajax 封装，X-Requested-With 头触发服务端返回 JSON 而非完整页面）
    $ajax('/customer?'+pr,{loading:false})
    .then(function(res){
        var h='';
        // 判断当前是否移动端视口（≤768px）
        var isMobile = window.matchMedia('(max-width: 767.98px)').matches;
        // v2.45.0：列表徽标（共享 / 分公司）——共享徽标仅标记「共享给我且非本人/非公海」的客户
        function custBadges(c){
            var b='';
            if(Number(c.parent_id||0)>0) b+=' <span class="pc-tag pc-tag-warn" style="font-size:10px">分公司</span>';
            if(window._mySharedIds && window._mySharedIds.indexOf(Number(c.id))>-1
                && Number(c.owner_id)!==0 && Number(c.owner_id)!==Number(window._myUserId))
                b+=' <span class="pc-tag pc-tag-info" style="font-size:10px">共享</span>';
            return b;
        }
        // ---- 空列表状态 ----
        if(!res.data||!res.data.length){
            if(isMobile){
                h='<tr><td colspan="8"><div class="c-empty">暂无客户<br><small>录入客户信息，或认领公海池中的客户</small></div></td></tr>';
            }else{
                h=emptyState({colspan:8,icon:'bi-people',title:'暂无客户',desc:'录入客户信息，或认领公海池中的客户',btn:'新增客户',href:'/customer/create',canCreate:window._canCreateCustomer});
            }
        }else{
            // ---- 逐行渲染 ----
            res.data.forEach(function(c){
                if(isMobile){
                    // 移动端卡片（点击进入移动端原生详情页 /m/customer/<id>）
                    var stCls=c.status==1?'c-tag-ok':'c-tag-muted';
                    var stLabel=c.status==1?'正常':'禁用';
                    h+='<tr><td colspan="7" style="padding:6px 8px;border:none;">';
                    h+='<div class="c-card" onclick="location.href=\'/m/customer/'+c.id+'\'">';
                    h+='<div class="c-card-top"><span class="c-card-t">'+esc(c.name)+custBadges(c)+'</span><span class="c-tag '+stCls+'">'+stLabel+'</span></div>';
                    h+='<div class="c-card-meta">';
                    if(c.contact_name) h+='<span class="c-card-contact"><i class="bi bi-person"></i>'+esc(c.contact_name)+'</span>';
                    if(c.contact_mobile) h+='<span class="c-card-contact"><i class="bi bi-telephone"></i>'+esc(c.contact_mobile)+'</span>';
                    // v2.38.9：移动卡片生命周期标签
                    var lc=c.lifecycle_status||'ACTIVE';
                    var lcCls={POTENTIAL:'c-tag-info',ACTIVE:'c-tag-ok',INACTIVE:'c-tag-warn'}[lc]||'c-tag-muted';
                    var lcLabel=window._lifecycleDict&&window._lifecycleDict[lc]?window._lifecycleDict[lc]:lc;
                    h+='<span class="c-tag '+lcCls+'">'+esc(lcLabel)+'</span>';
                    // v2.40.0 P1-7：移动卡片行业标签
                    var ind=c.industry||'';
                    if(ind){ var indLabel=window._industryDict&&window._industryDict[ind]?window._industryDict[ind]:ind; h+='<span class="c-tag c-tag-muted">'+esc(indLabel)+'</span>'; }
                    h+='<span class="c-tag '+(c.owner_id===0?'c-tag-muted':'c-tag-info')+'">'+(c.owner_id===0?'公海':(esc(c.owner_name)||'用户#'+(c.owner_id||'?')))+'</span>';
                    h+='</div>';
                    h+='</div>';
                    h+='</td></tr>';
                }else{
                    // 桌面端表格行
                    h+='<tr><td><a href="/customer/'+c.id+'">'+esc(c.name)+'</a>'+custBadges(c)+'</td>';
                    h+='<td>'+(esc(c.contact_name)||'-')+'</td>';
                    h+='<td>'+(esc(c.contact_mobile)||'-')+'</td>';
                    // v2.40.0 P1-7：行业列（空值显示 —）
                    var ind=c.industry||'';
                    var indLabel=window._industryDict&&window._industryDict[ind]?window._industryDict[ind]:ind;
                    h+='<td>'+(ind?(esc(indLabel)||esc(ind)):'<span class="text-muted">—</span>')+'</td>';
                    // v2.38.9：生命周期列（pc-tag 风格，与漏斗同色系）
                    var lc=c.lifecycle_status||'ACTIVE';
                    var lcCls={POTENTIAL:'pc-tag-info',ACTIVE:'pc-tag-ok',INACTIVE:'pc-tag-warn'}[lc]||'pc-tag-muted';
                    var lcLabel=window._lifecycleDict&&window._lifecycleDict[lc]?window._lifecycleDict[lc]:lc;
                    h+='<td><span class="pc-tag '+lcCls+'">'+esc(lcLabel)+'</span></td>';
                    h+='<td>'+(c.owner_id===0?'<span class="text-muted">公海</span>':(esc(c.owner_name)||'用户#'+(c.owner_id||'?')))+'</td>';
                    h+='<td>'+(c.status==1?'<span class="badge bg-success">正常</span>':'<span class="badge bg-secondary">禁用</span>')+'</td>';
                    h+='<td><a href="/customer/'+c.id+'/edit" class="btn btn-sm btn-outline-secondary" aria-label="编辑"><i class="bi bi-pencil"></i></a></td>';
                    h+='</tr>';
                }
            });
        }
        tb.innerHTML=h;
        // ---- 分页控件 ----
        var tp=Math.ceil(res.count/15),ph='';
        for(var i=1;i<=tp;i++){
            ph+='<li class="page-item '+(i===p?'active':'')+'"><a class="page-link" href="#" data-p="'+i+'">'+i+'</a></li>';
        }
        pg.innerHTML='<nav><ul class="pagination pagination-sm justify-content-end mb-0">'+ph+'</ul></nav>';
        // 分页点击事件
        pg.querySelectorAll('a').forEach(function(a){
            a.addEventListener('click',function(e){e.preventDefault();load(parseInt(this.dataset.p));});
        });
    }).catch(function(){
        // 加载失败：错误已由 $ajax 统一 toast，列表内展示重试入口（避免"加载中"永驻）
        if(tb){
            tb.innerHTML='<tr><td colspan="8" class="text-center py-5 text-muted">'
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
// 初始加载（DCL 防护：customer.js 在 body 中先于 app.js 加载，顶层直接调用 $ajax 会 ReferenceError 导致列表卡死）
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function(){ load(1); });
} else {
    load(1);
}

// v2.38.9：漏斗阶段点击筛选（v2.38.11：再次点击已选中阶段取消筛选——替代已移除的「当前筛选」栏）
function bindLcFilter(){
  document.querySelectorAll('.lc-stage').forEach(function(el){
    el.addEventListener('click', function(){
      var lc = el.getAttribute('data-lifecycle');
      if(window._lifecycleActive === lc){
        // 再次点击已选中的阶段 → 取消筛选
        window._lifecycleActive = '';
        document.querySelectorAll('.lc-stage').forEach(function(s){ s.classList.remove('lc-active'); });
        load(1);
        return;
      }
      window._lifecycleActive = lc;
      // 选中态用 lc-active 类（浅品牌蓝底）——即筛选状态指示
      document.querySelectorAll('.lc-stage').forEach(function(s){ s.classList.toggle('lc-active', s===el); });
      load(1);
    });
  });
}
window.clearLcFilter = function(){
  window._lifecycleActive = '';
  document.querySelectorAll('.lc-stage').forEach(function(s){ s.classList.remove('lc-active'); });
  load(1);
};
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bindLcFilter);
} else {
    bindLcFilter();
}
})();
