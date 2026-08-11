// 通用选人弹窗 + 角色多选弹窗 — 提取自 app/view/admin/index.php（v2.38.1 拆分 God File）
// 依赖：公共脚本块已声明 allUsers/allRoles/allDepts/esc（window.esc fallback），
//       页脚 app.js 提供 $ajax/showToast，Bootstrap CDN 提供 bootstrap.Modal。
// 仅在 admin?tab=user 下加载（用户管理/编辑用户时触发）。
// 中文注释：用户选择/角色选择交互 → 弹窗渲染与提交
// === 通用选人弹窗 ===
let _up = {selected:{}, deptId:0, keyword:'', page:1, multiple:true, onConfirm:null, depts:[], nameCache:{}, exclude:[]};
function openUserPicker(opts){
  opts = opts || {};
  _up.multiple  = opts.multiple !== false;
  _up.onConfirm = opts.onConfirm || function(){};
  _up.exclude   = opts.exclude || [];
  _up.selected  = {};
  (opts.selected||[]).forEach(function(id){ _up.selected[id] = _up.nameCache[id] ? _up.nameCache[id] : ('用户#'+id); });
  _up.deptId = 0; _up.keyword = ''; _up.page = 1;
  document.getElementById('upKeyword').value = '';
  upRenderSelected();
  upLoadDepts();
  upLoadUsers(true);
  new bootstrap.Modal('#userPickerModal').show();
}
function upLoadDepts(){
  $ajax('/ajax/admin/dept-tree',{loading:false,silent:true}).then(function(res){
    _up.depts = (res.code===0 && res.data) ? res.data : [];
    upRenderDeptTree();
  });
}
function upBuildTree(parentId){ return _up.depts.filter(function(d){return d.parent_id==parentId;}); }
function upRenderDeptTree(){
  let html = '<div><span class="up-dept-link '+(0===_up.deptId?'text-primary fw-bold':'')+'" data-id="0">全部部门</span></div>';
  function walk(parentId, depth){
    upBuildTree(parentId).forEach(function(d){
      let pad = 'margin-left:'+(depth*14)+'px';
      html += '<div><span class="up-dept-link '+(d.id===_up.deptId?'text-primary fw-bold':'')+'" data-id="'+d.id+'" style="'+pad+'">'+esc(d.name)+'</span></div>';
      walk(d.id, depth+1);
    });
  }
  walk(0,0);
  let box = document.getElementById('upDeptTree');
  box.innerHTML = html;
  box.querySelectorAll('.up-dept-link').forEach(function(el){
    el.onclick = function(){ _up.deptId = parseInt(el.dataset.id); upRenderDeptTree(); upLoadUsers(true); };
  });
}
function upLoadUsers(reset){
  if(reset){ _up.page = 1; document.getElementById('upUserList').innerHTML = ''; }
  let url = '/ajax/admin/user-picker?dept_id='+_up.deptId+'&keyword='+encodeURIComponent(_up.keyword)+'&page='+_up.page;
  $ajax(url,{loading:false,silent:true}).then(function(res){
    if(res.code!==0) return;
    let list = res.data.list||[];
    // v2.38.16：支持排除指定用户（离职交接接收人不得为交接人本人）
    if(_up.exclude.length>0){ list = list.filter(function(u){ return _up.exclude.indexOf(u.id) < 0; }); }
    let box = document.getElementById('upUserList');
    if(list.length===0 && _up.page===1){ box.innerHTML='<div class="text-muted text-center py-3 small">无匹配用户</div>'; return; }
    list.forEach(function(u){
      _up.nameCache[u.id] = u.name;
      let checked = _up.selected[u.id]!==undefined;
      let html = '<label class="form-check small py-1 border-bottom '+(checked?'bg-light':'')+'">'
        + '<input class="form-check-input up-user-cb" type="'+( _up.multiple?'checkbox':'radio')+'" name="upUser" value="'+u.id+'" '+(checked?'checked':'')+'> '
        + esc(u.name) + (u.dept_name?' <span class="text-muted">('+esc(u.dept_name)+')</span>':'') + '</label>';
      box.insertAdjacentHTML('beforeend', html);
    });
    box.querySelectorAll('.up-user-cb').forEach(function(cb){
      cb.onchange = function(){
        let id = parseInt(cb.value);
        if(_up.multiple){
          // 多选（checkbox）：勾选加入 / 取消移除
          if(cb.checked){ _up.selected[id] = _up.nameCache[id] || ('用户#'+id); }
          else { delete _up.selected[id]; }
          cb.closest('.form-check').classList.toggle('bg-light', cb.checked);
        } else {
          // 单选（radio）：浏览器切换选中时被取消的旧 radio 不触发 change，
          // 故须整体重置 _up.selected，仅保留当前选中的一项（防"看着多选、实则只取第一项"）
          _up.selected = {};
          if(cb.checked){ _up.selected[id] = _up.nameCache[id] || ('用户#'+id); }
          document.querySelectorAll('.up-user-cb').forEach(function(x){
            x.closest('.form-check').classList.toggle('bg-light', x.checked);
          });
        }
        upRenderSelected();
      };
    });
    _up.page++;
    document.getElementById('upLoadMore').style.display = (list.length<20) ? 'none' : '';
  });
}
function upRenderSelected(){
  let box = document.getElementById('upSelected');
  let ids = Object.keys(_up.selected);
  if(ids.length===0){ box.innerHTML='<span class="text-muted small">未选择</span>'; return; }
  box.innerHTML = ids.map(function(id){ return '<span class="badge bg-primary me-1 mb-1">'+esc(_up.selected[id])+' <span style="cursor:pointer" data-rm="'+id+'">×</span></span>'; }).join('');
  box.querySelectorAll('[data-rm]').forEach(function(el){ el.onclick=function(){ let id=parseInt(el.dataset.rm); delete _up.selected[id]; upRenderSelected(); document.querySelectorAll('.up-user-cb').forEach(function(cb){ if(parseInt(cb.value)===id) cb.checked=false; }); }; });
}
// P2-17【F-A1】顶层绑定 null 防护：脚本仅在 admin?tab=user 加载，弹窗 DOM 缺失时不得抛 ReferenceError 阻断后续
let _upSearchBtn = document.getElementById('upSearchBtn');
if (_upSearchBtn) _upSearchBtn.onclick = function(){ _up.keyword=document.getElementById('upKeyword').value; upLoadUsers(true); };
let _upKeywordEl = document.getElementById('upKeyword');
if (_upKeywordEl) _upKeywordEl.addEventListener('keydown',function(e){ if(e.key==='Enter'){ _up.keyword=this.value; upLoadUsers(true);} });
let _upLoadMoreEl = document.getElementById('upLoadMore');
if (_upLoadMoreEl) _upLoadMoreEl.onclick = function(){ upLoadUsers(false); };
let _upConfirmBtn = document.getElementById('upConfirmBtn');
if (_upConfirmBtn) _upConfirmBtn.onclick = function(){
  let ids = Object.keys(_up.selected).map(function(x){return parseInt(x);});
  if(_up.onConfirm) _up.onConfirm(ids);
  bootstrap.Modal.getInstance('#userPickerModal').hide();
};

// === 角色多选弹窗（v2.31.0）===
// 编辑用户分配多角色：搜索+多选+已选标签回显（抄送节点角色已改为与 ROLE 节点一致的 native select multiple，不再复用此弹窗）
let _rpSelected = [];      // 用户编辑表单选中的角色 id

// 编辑用户角色
function openRolePicker(){
  let kw = document.getElementById('rpKeyword'); if(kw) kw.value='';
  rpRender();
  new bootstrap.Modal('#rolePickerModal').show();
}

function rpRender(){
  let kw = (document.getElementById('rpKeyword').value||'').trim().toLowerCase();
  let box = document.getElementById('rpList');
  let list = allRoles.filter(function(r){ return !kw || (r.name||'').toLowerCase().indexOf(kw)>=0; });
  if(list.length===0){ box.innerHTML='<div class="text-muted text-center py-3 small">无匹配角色</div>'; }
  else {
    box.innerHTML = list.map(function(r){
      let checked = (_rpSelected.includes(r.id) ? 'checked' : '');
      return '<label class="form-check py-1 border-bottom"><input class="form-check-input rp-cb" type="checkbox" value="'+r.id+'" '+checked+'> '+esc(r.name)+'</label>';
    }).join('');
    box.querySelectorAll('.rp-cb').forEach(function(cb){
      cb.onchange = function(){
        let id = parseInt(cb.value);
        if(cb.checked){ if(!_rpSelected.includes(id)) _rpSelected.push(id); }
        else { _rpSelected = _rpSelected.filter(function(x){return x!==id;}); }
        renderRoleView();
      };
    });
  }
  renderRoleView();
}

// 用户编辑表单：回显已选角色
function renderRoleView(){
  let box = document.getElementById('uRolesView');
  if(!box) return;
  if(_rpSelected.length===0){ box.innerHTML='<span class="text-muted small">未选择</span>'; return; }
  box.innerHTML = _rpSelected.map(function(id){
    let r = allRoles.find(function(x){return x.id===id;});
    let name = r ? r.name : ('角色#'+id);
    return '<span class="badge bg-primary me-1 mb-1">'+esc(name)+' <span style="cursor:pointer" data-rmr="'+id+'">×</span></span>';
  }).join('');
  box.querySelectorAll('[data-rmr]').forEach(function(el){
    el.onclick = function(){ let id=parseInt(el.dataset.rmr); _rpSelected=_rpSelected.filter(function(x){return x!==id;}); rpRender(); };
  });
}

let _rpKeywordEl = document.getElementById('rpKeyword');
if (_rpKeywordEl) _rpKeywordEl.addEventListener('input', rpRender);
