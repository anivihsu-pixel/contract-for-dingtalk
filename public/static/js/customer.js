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
        // v2.47.8：列表徽标升级——集团（根/成员）+ 共享（双向）。集团成员 tooltip 带出父客户名
        function custBadges(c){
            var b='';
            // 集团根：有子客户（child_count>0）
            if(Number(c.child_count||0)>0) b+=' <a class="pc-tag pc-tag-info" style="font-size:10px;text-decoration:none;cursor:pointer" href="/customer/'+c.id+'#group" title="该客户为集团根，含 '+c.child_count+' 个成员客户">集团</a>';
            // 集团成员：有父客户
            if(Number(c.parent_id||0)>0) b+=' <a class="pc-tag pc-tag-warn" style="font-size:10px;text-decoration:none;cursor:pointer" href="/customer/'+c.id+'#group" title="集团成员 · 所属：'+(c.parent_name?esc(c.parent_name):'#'+c.parent_id)+'">集团成员</a>';
            // 共享给我且非本人
            if(window._mySharedIds && window._mySharedIds.indexOf(Number(c.id))>-1
                && Number(c.owner_id)!==Number(window._myUserId))
                b+=' <span class="pc-tag pc-tag-info" style="font-size:10px" title="他人共享给我，可查看并关联合同">共享给我</span>';
            // 我共享出去（负责人主动共享）
            if(window._mySharedOutIds && window._mySharedOutIds.indexOf(Number(c.id))>-1)
                b+=' <span class="pc-tag pc-tag-ok" style="font-size:10px" title="我共享给了他人，可在详情页撤销">我共享</span>';
            return b;
        }
        // ---- 空列表状态 ----
        if(!res.data||!res.data.length){
            if(isMobile){
                h='<tr><td colspan="8"><div class="c-empty">暂无客户<br><small>录入客户信息</small></div></td></tr>';
            }else{
                h=emptyState({colspan:8,icon:'bi-people',title:'暂无客户',desc:'录入客户信息',btn:'新增客户',href:'/customer/create',canCreate:window._canCreateCustomer});
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
                    // 生命周期标签
                    var lc=c.lifecycle_status||'ACTIVE';
                    var lcCls={POTENTIAL:'c-tag-info',ACTIVE:'c-tag-ok'}[lc]||'c-tag-muted';
                    var lcLabel=window._lifecycleDict&&window._lifecycleDict[lc]?window._lifecycleDict[lc]:lc;
                    h+='<span class="c-tag '+lcCls+'">'+esc(lcLabel)+'</span>';
                    // v2.40.0 P1-7：移动卡片行业标签
                    var ind=c.industry||'';
                    if(ind){ var indLabel=window._industryDict&&window._industryDict[ind]?window._industryDict[ind]:ind; h+='<span class="c-tag c-tag-muted">'+esc(indLabel)+'</span>'; }
                    h+='<span class="c-tag c-tag-info">'+(esc(c.owner_name)||'未分配')+'</span>';
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
                    // 生命周期列（pc-tag 风格，与漏斗同色系）
                    var lc=c.lifecycle_status||'ACTIVE';
                    var lcCls={POTENTIAL:'pc-tag-info',ACTIVE:'pc-tag-ok'}[lc]||'pc-tag-muted';
                    var lcLabel=window._lifecycleDict&&window._lifecycleDict[lc]?window._lifecycleDict[lc]:lc;
                    h+='<td><span class="pc-tag '+lcCls+'">'+esc(lcLabel)+'</span></td>';
                    h+='<td>'+(esc(c.owner_name)||'未分配')+'</td>';
                    h+='<td>'+(c.status==1?'<span class="badge bg-success">正常</span>':'<span class="badge bg-secondary">禁用</span>')+'</td>';
                    // 操作列——编辑 + 快捷共享（负责人/超管）
                    var canShare = (window._isAdmin || Number(c.owner_id)===Number(window._myUserId));
                    h+='<td>';
                    h+='<a href="/customer/'+c.id+'/edit" class="btn btn-sm btn-outline-secondary" aria-label="编辑" title="编辑"><i class="bi bi-pencil"></i></a>';
                    if(canShare) h+=' <button type="button" class="btn btn-sm btn-outline-primary" aria-label="共享" title="共享设置" onclick="openListShare('+c.id+',this)"><i class="bi bi-people"></i></button>';
                    h+='</td>';
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

// ===== v2.47.8：列表快捷共享弹层（负责人/超管；复用 /ajax/customer/{id}/share-list、share、unshare） =====
var _listShareId = 0, _listShareCan = false, _listShareSel = 0;
window.openListShare = function(id, btn){
    _listShareId = id;
    _listShareSel = 0;
    var modal = document.getElementById('listShareModal');
    if(!modal) return;
    document.getElementById('listShareTitle').textContent = '共享设置';
    // 重置添加区
    document.getElementById('listShareType').value = 'USER';
    document.getElementById('listShareDeptWrap').style.display = 'none';
    document.getElementById('listShareUserWrap').style.display = '';
    document.getElementById('listShareSearch').value = '';
    document.getElementById('listShareSearch').removeAttribute('data-sel');
    // 用户建议列表仅搜索时显示，打开时隐藏（避免误列全公司用户）
    var uBox = document.getElementById('listShareUserList');
    if (uBox) { uBox.style.display = 'none'; uBox.innerHTML = ''; }
    // 加载当前共享列表
    document.getElementById('listShareList').innerHTML = '<tr><td colspan="4" class="text-center text-muted py-3">加载中…</td></tr>';
    $ajax('/ajax/customer/'+id+'/share-list', {loading:false}).then(function(res){
        if(res.code!==0){ showToast(res.msg||'加载失败','error'); return; }
        var d = res.data || {};
        _listShareCan = !!d.can_manage;
        var shares = d.shares || [];
        var h = '';
        if(!shares.length){
            h = '<tr><td colspan="4" class="text-center text-muted py-3">暂无共享成员</td></tr>';
        }else{
            shares.forEach(function(s){
                h += '<tr data-share="'+esc(s.target_type)+':'+s.target_id+'">'
                    + '<td><strong>'+esc(s.target_name)+'</strong></td>'
                    + '<td>'+(s.target_type==='DEPT'?'部门':'用户')+'</td>'
                    + '<td><span class="pc-tag pc-tag-info">只读</span></td>'
                    + '<td>'+( _listShareCan
                        ? '<a href="javascript:;" class="small text-danger" onclick="removeListShare(this)">撤销</a>'
                        : '<span class="small text-muted">—</span>')+'</td></tr>';
            });
        }
        document.getElementById('listShareList').innerHTML = h;
        var addBox = document.getElementById('listShareAddBox');
        if(addBox) addBox.style.display = _listShareCan ? '' : 'none';
    }).catch(function(){ showToast('加载失败','error'); });
    new bootstrap.Modal(modal).show();
};
function renderListShareUsers(list){
    var box = document.getElementById('listShareUserList');
    if(!box) return;
    if(!list || !list.length){ box.style.display='none'; box.innerHTML=''; return; }
    var h = '';
    list.forEach(function(u){
        h += '<a href="javascript:;" class="dropdown-item" data-sid="'+u.id+'" data-sname="'+esc(u.name)+'">'+esc(u.name)+'</a>';
    });
    box.innerHTML = h;
    box.style.display = 'block';
}
function initListShare(){
    var modal = document.getElementById('listShareModal');
    if(!modal) return;
    // 类型切换
    document.getElementById('listShareType').addEventListener('change', function(){
        var t = this.value;
        document.getElementById('listShareUserWrap').style.display = (t==='USER') ? '' : 'none';
        document.getElementById('listShareDeptWrap').style.display = (t==='DEPT') ? '' : 'none';
        if(t==='DEPT'){
            var sel = document.getElementById('listShareDept');
            var h = '<option value="0">选择部门…</option>';
            (window._shareDepartments||[]).forEach(function(d){ h += '<option value="'+d.id+'">'+esc(d.name)+'</option>'; });
            sel.innerHTML = h;
        }
    });
    // 用户搜索（本地过滤）
    var timer = null;
    document.getElementById('listShareSearch').addEventListener('input', function(){
        var kw = this.value.trim();
        clearTimeout(timer);
        timer = setTimeout(function(){
            if(kw===''){ var ub=document.getElementById('listShareUserList'); if(ub){ ub.style.display='none'; ub.innerHTML=''; } return; }
            renderListShareUsers((window._shareTargetOptions||[]).filter(function(u){ return u.name.indexOf(kw)>=0; }));
        }, 150);
    });
    // 建议点击选中
    document.getElementById('listShareUserList').addEventListener('mousedown', function(e){
        var it = e.target.closest ? e.target.closest('[data-sid]') : null;
        if(!it) return;
        e.preventDefault();
        _listShareSel = parseInt(it.getAttribute('data-sid'),10);
        document.getElementById('listShareSearch').value = it.getAttribute('data-sname');
        document.getElementById('listShareSearch').setAttribute('data-sel','1');
        document.getElementById('listShareUserList').style.display = 'none';
    });
    // 添加
    document.getElementById('listShareAddBtn').addEventListener('click', function(){
        var type = document.getElementById('listShareType').value;
        var id;
        if(type==='DEPT'){ id = parseInt(document.getElementById('listShareDept').value||'0',10); }
        else{ id = _listShareSel; }
        if(!id){ showToast('请选择共享对象','error'); return; }
        var fd = new FormData();
        fd.append('target_type', type);
        fd.append('target_id', id);
        $ajax('/ajax/customer/'+_listShareId+'/share', {method:'POST', body:fd, loading:false}).then(function(res){
            if(res.code===0){
                showToast(res.msg||'共享成功','success');
                openListShare(_listShareId, null); // 刷新共享列表
            } else showToast(res.msg||'共享失败','error');
        }).catch(function(){ showToast('共享失败','error'); });
    });
    modal.addEventListener('shown.bs.modal', function(){ document.getElementById('listShareSearch').focus(); });
}
window.removeListShare = function(link){
    var tr = link.closest('tr');
    var key = tr.getAttribute('data-share');
    var parts = key.split(':');
    pcConfirm({message:'确定撤销该共享？撤销后对方不再可见此客户。', danger:true}).then(function(ok){
        if(!ok) return;
        var fd = new FormData(); fd.append('target_type', parts[0]); fd.append('target_id', parts[1]);
        $ajax('/ajax/customer/'+_listShareId+'/unshare', {method:'POST', body:fd, loading:false}).then(function(res){
            if(res.code===0){ showToast(res.msg||'已撤销','success'); openListShare(_listShareId, null); }
            else showToast(res.msg||'撤销失败','error');
        }).catch(function(){ showToast('撤销失败','error'); });
    });
};
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initListShare);
} else {
    initListShare();
}
})();
