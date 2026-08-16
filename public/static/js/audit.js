/**
 * 审计中心 - 操作日志分页渲染
 * 依赖：<form id="searchForm"> + <table id="tableBody"> + <div id="pagination">
 */
(function(){
// ---- 状态与 DOM 引用 ----
var p=1,sf=document.getElementById('searchForm'),tb=document.getElementById('tableBody'),pg=document.getElementById('pagination');
if(!tb)return;

// HTML 转义：统一使用 app.js 全局 esc（P3-5：移除本地重复副本）
// 映射表读取：操作名称与目标类型（由服务端注入 window._auditActions / window._auditTypes）
function actName(a){return window._auditActions&&window._auditActions[a]?window._auditActions[a]:a;}
function typeName(t){return window._auditTypes&&window._auditTypes[t]?window._auditTypes[t]:t;}

/** 只读弹窗展示完整详情（超长内容不被表格截断吞掉） */
function showDetail(title, text){
    var el=document.createElement('div');
    el.innerHTML='<div class="modal fade" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-scrollable">'
        +'<div class="modal-content"><div class="modal-header"><h5 class="modal-title"></h5>'
        +'<button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>'
        +'<div class="modal-body" style="white-space:pre-wrap;word-break:break-word"></div>'
        +'<div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">关闭</button></div>'
        +'</div></div></div>';
    document.body.appendChild(el);
    el.querySelector('.modal-title').textContent=title;
    el.querySelector('.modal-body').textContent=text;
    var modal=new bootstrap.Modal(el.querySelector('.modal'));
    modal.show();
    el.addEventListener('hidden.bs.modal',function(){el.remove();});
}

/**
 * 加载审计日志（指定页码）
 * @param {number} n 页码
 */
function load(n){
    p=n;
    // 组装查询参数：操作类型 / 目标类型 / 日期范围
    var pr=new URLSearchParams({page:p,limit:15});
    var a=sf.querySelector('[name="action"]'); if(a&&a.value)pr.set('action',a.value);
    var t=sf.querySelector('[name="target_type"]'); if(t&&t.value)pr.set('target_type',t.value);
    var d=sf.querySelector('[name="date_start"]'); if(d&&d.value)pr.set('date_start',d.value);
    // AJAX 请求（带 loading 提示）
    $ajax('/ajax/audit/list?'+pr,{loadingText:'加载审计日志…'}).then(function(res){
        var h='';
        if(!res.data||!res.data.list||!res.data.list.length){
            h=emptyState({colspan:6,icon:'bi-shield-check',title:'暂无审计记录',desc:'系统操作（创建/审批/签署/归档等）会自动留痕',canCreate:false});
        }else{
            // ---- 逐行渲染审计记录 ----
            // 详情取服务端已中文化的 detail_text（原始 JSON 兜底），超长提供「查看完整」弹窗
            var details = window.__auditDetails = [];
            res.data.list.forEach(function(r){
                var content = r.detail_text || (r.content ? (typeof r.content==='string'? r.content : JSON.stringify(r.content)) : '') || '-';
                var obj = typeName(r.target_type);
                obj += r.target_title ? '：'+esc(r.target_title) : ' #'+(r.target_id||'');
                var short = content.length>90 ? content.slice(0,90)+'…' : content;
                h+='<tr>';
                h+='<td><small>'+esc(r.created_at)+'</small></td>';
                h+='<td>'+esc(r.user_name||'未知')+'</td>';
                h+='<td><span class="badge bg-light text-dark border">'+esc(actName(r.action))+'</span></td>';
                h+='<td>'+esc(obj)+'</td>';
                h+='<td><small class="text-muted d-inline-block" style="max-width:340px;vertical-align:middle">'+esc(short)+'</small>';
                if(content.length>90){
                    var idx=details.length;
                    details.push(content);
                    h+=' <a href="javascript:;" class="small text-primary" data-idx="'+idx+'">查看完整</a>';
                }
                h+='</td>';
                h+='<td><small class="text-muted">'+esc(r.ip_address||'')+'</small></td>';
                h+='</tr>';
            });
        }
        tb.innerHTML=h;
        // 「查看完整」详情弹窗：按 data-idx 取当前页已存文本
        tb.querySelectorAll('a[data-idx]').forEach(function(a){
            a.addEventListener('click',function(){
                var d=window.__auditDetails||[];
                showDetail('操作详情', d[parseInt(a.dataset.idx)]||'');
            });
        });
        // ---- 分页控件 ----
        var total=res.data?res.data.count:0;
        var tp=Math.ceil(total/15),ph='';
        for(var i=1;i<=tp;i++){ph+='<li class="page-item '+(i===p?'active':'')+'"><a class="page-link" href="#" data-p="'+i+'">'+i+'</a></li>';}
        pg.innerHTML='<nav><ul class="pagination pagination-sm justify-content-end mb-0">'+ph+'</ul></nav>';
        pg.querySelectorAll('a').forEach(function(a){a.addEventListener('click',function(e){e.preventDefault();load(parseInt(this.dataset.p));});});
    }).catch(function(){
        // 错误已由 $ajax 兜底 toast；但必须清除初始占位 spinner，否则会永久转圈。
        // 改为展示「加载失败 + 重新加载」操作点，点击重试当前页。
        if(tb){
            tb.innerHTML='<tr><td colspan="6" class="text-center py-5 text-muted">'
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
if(sf)sf.addEventListener('submit',function(e){e.preventDefault();load(1);});
// 初始加载：等 DOM 与共享脚本（app.js 提供的 $ajax / emptyState 等全局）就绪后再触发。
// 关键修复（2026-07-25）：列表脚本在 footer 的 app.js 之前执行，若此刻直接 load(1)
// 会因 $ajax 未定义而同步抛错、初始 spinner 永久转圈。DOMContentLoaded 在所有同步脚本
// （含 footer 的 app.js）执行完后才触发，届时全局已就绪，彻底消除脚本顺序依赖。
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function(){ load(1); });
} else {
    load(1);
}
})();
