<?php $title='系统设置'; $menu_active='admin'; $tab=$tab??'user'; include __DIR__.'/../layout/header.php'; ?>
<style>
.flow-editor{background:#f8f9fa;padding:20px;border-radius:12px}
.node-card{background:#fff;border:2px solid #dee2e6;border-radius:12px;padding:16px;margin-bottom:16px;transition:all .2s}
.node-card:hover{border-color:var(--primary);box-shadow:0 2px 8px rgba(11,94,215,.15)}
.node-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px}
.node-head-left{display:flex;align-items:center;gap:8px}
.node-badge{width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:13px}
.node-arrow{display:flex;justify-content:center;padding:4px 0;color:var(--primary)}
.node-arrow i{font-size:20px}
.node-actions{display:flex;gap:4px}
/* v2.38.22 恢复：审批流程编辑器——左侧图形化画布 + 右侧流程配置侧边栏；添加审批节点按钮在画布内 */
/* v2.38.24：审批流程列表拖动排序（同类内） */
.flow-drag-handle{cursor:grab;user-select:none}
tr[data-id].flow-dragging{opacity:.45;border:1px dashed var(--primary)}
tr[data-id].flow-drop-target{box-shadow:inset 0 2px 0 0 var(--primary)}
.flow-editor-layout{display:flex;align-items:stretch;min-height:520px;max-height:70vh}
.flow-canvas-panel{flex:1 1 auto;min-width:0;background:#f6f8fa;border-right:1px solid var(--line);display:flex;flex-direction:column}
.flow-canvas-head{display:flex;align-items:center;gap:8px;padding:12px 16px;font-weight:600;font-size:13px;color:var(--text-2);border-bottom:1px solid var(--line);background:#fff}
.flow-canvas-scroll{flex:1 1 auto;overflow-y:auto;padding:18px;scrollbar-width:thin}
.flow-canvas-scroll::-webkit-scrollbar{width:6px}
.flow-canvas-scroll::-webkit-scrollbar-thumb{background:#d1d5db;border-radius:3px}
/* 画布内图形化流：发起人 → 节点卡 → 结束 */
.flow-canvas{display:flex;flex-direction:column;align-items:center;min-width:560px;padding-bottom:24px}
.flow-cv-starter{display:inline-flex;align-items:center;gap:8px;background:#eef4ff;border:1px solid #c9dcf7;color:#0b5ed7;border-radius:20px;padding:7px 18px;font-size:13px;font-weight:600}
.flow-cv-conn{width:2px;height:22px;background:#c9dcf7;position:relative}
.flow-cv-conn::after{content:'';position:absolute;left:50%;bottom:-1px;transform:translateX(-50%);border:5px solid transparent;border-top-color:#c9dcf7}
/* v2.38.26：发起人连接线仅去除视觉（保留原高度作为发起人与节点链之间的间隔，避免上下贴得太近），不影响其余连接线 */
.flow-cv-gap{width:2px;height:22px}
/* v2.38.26：首个审批节点（紧跟发起人间隔之后）向上的连接线即「发起人连接线」，隐藏它；其余节点间/抄送连接线不受影响 */
.flow-cv-gap + .flow-cv-node::before{display:none}
.flow-cv-end{display:inline-flex;align-items:center;gap:8px;background:#f6f8fa;border:1px dashed var(--line);color:#6b7280;border-radius:20px;padding:6px 16px;font-size:12px}
/* 节点卡片（图形化） */
.flow-cv-node{width:100%;max-width:620px;background:#fff;border:1px solid var(--line);border-radius:12px;padding:12px 14px;position:relative;box-shadow:0 1px 3px rgba(0,0,0,.05)}
.flow-cv-node::before{content:'';position:absolute;left:50%;top:-22px;width:2px;height:22px;background:#c9dcf7}
.flow-cv-node .node-card{border:none;border-radius:0;padding:0;margin:0;box-shadow:none}
.flow-cv-node .node-card:hover{border:none;box-shadow:none}
/* 画布内「添加审批节点」按钮（节点链末尾、抄送之前） */
.flow-cv-add{display:flex;justify-content:center;padding:6px 0}
.flow-cv-add::before{content:'';position:absolute;left:50%;top:0;width:2px;height:6px;background:#c9dcf7}
/* 抄送节点卡片（黄色系，置于节点链与结束之间） */
.flow-cv-cc{width:100%;max-width:620px;background:#fffdf3;border:1px solid #f0e3b8;border-radius:12px;padding:12px 14px;position:relative}
.flow-cv-cc::before{content:'';position:absolute;left:50%;top:-22px;width:2px;height:22px;background:#f0e3b8}
.flow-cv-cc .cc-title{font-size:12px;color:#8a6d1a;font-weight:600;margin-bottom:8px}
/* 右侧配置面板 */
.flow-config-panel{flex:0 0 300px;background:#fff;padding:14px 16px;overflow-y:auto;scrollbar-width:thin}
.flow-config-panel::-webkit-scrollbar{width:6px}
.flow-config-panel::-webkit-scrollbar-thumb{background:#d1d5db;border-radius:3px}
.flow-config-title{font-size:13px;color:var(--text-2);margin:0 0 14px;padding-bottom:10px;border-bottom:1px solid var(--line)}
/* 审批流程编辑器弹窗宽度（Bootstrap modal-xxl 在部分 CDN 版本未生效，显式覆写保证双栏不挤压） */
#flowModal .modal-dialog{max-width:1180px;width:calc(100vw - 48px)}
@media (max-width:1024px){#flowModal .modal-dialog{max-width:calc(100vw - 24px)}}
/* v2.38.25：发票流程编辑器弹窗（参照合同审批编辑器；样式对齐 form_builder 页面画布） */
#invoiceModal .modal-dialog{max-width:1180px;width:calc(100vw - 48px)}
#invoiceModal .modal-body{max-height:70vh;overflow:auto}
#invoiceModal .fb2-layout{display:flex;align-items:stretch;gap:0;min-height:520px;max-height:70vh;border:none;border-radius:0}
#invoiceModal .fb2-canvas-panel{flex:1 1 auto;min-width:0;background:#f6f8fa;padding:16px;overflow-y:auto;scrollbar-width:thin}
#invoiceModal .fb2-canvas-panel::-webkit-scrollbar{width:6px}
#invoiceModal .fb2-canvas-panel::-webkit-scrollbar-thumb{background:#d1d5db;border-radius:3px}
#invoiceModal .fb2-canvas-panel #fbFlowGroups{min-width:0}
#invoiceModal .fb2-canvas-panel .fb-branch-zone{justify-content:flex-start}
#invoiceModal .fb2-config-panel{flex:0 0 300px;background:#fff;border-left:1px solid var(--line);padding:14px 16px;overflow-y:auto;scrollbar-width:thin}
#invoiceModal .fb2-config-panel::-webkit-scrollbar{width:6px}
#invoiceModal .fb2-config-panel::-webkit-scrollbar-thumb{background:#d1d5db;border-radius:3px}
#invoiceModal .fb2-config-title{font-size:13px;color:var(--text-2);margin:0 0 14px;padding-bottom:10px;border-bottom:1px solid var(--line)}
#invoiceModal .fb2-config-panel .form-label{font-size:12px;color:var(--text-muted);margin-bottom:3px}
#invoiceModal .fb2-config-panel .form-control,#invoiceModal .fb2-config-panel .form-select{font-size:13px}
/* 发票画布：发起人 → 分支区并列 → 结束 */
#invoiceModal .fb-flow{display:flex;flex-direction:column;align-items:center}
#invoiceModal .fb-flow-starter{display:flex;align-items:center;gap:8px;background:#eef4ff;border:1px solid #c9dcf7;color:#0b5ed7;border-radius:20px;padding:7px 18px;font-size:13px;font-weight:600}
#invoiceModal .fb-flow-conn{height:22px;width:100%;background:transparent}
#invoiceModal .fb-flow-conn::after{display:none}
#invoiceModal .fb-flow-gap{width:100%;height:14px;position:relative}
#invoiceModal .fb-flow-gap::before{content:'';position:absolute;left:50%;top:0;bottom:0;width:2px;background:#c9dcf7}
#invoiceModal .fb-flow-end{display:flex;align-items:center;gap:8px;background:#f6f8fa;border:1px dashed var(--line);color:#6b7280;border-radius:20px;padding:6px 16px;font-size:12px}
#invoiceModal .fb-branch-zone{display:flex;flex-wrap:wrap;gap:14px;justify-content:center;width:100%;max-width:1320px}
#invoiceModal .fb-branch{width:380px;max-width:100%;background:#fff;border:1px solid var(--line);border-radius:12px;padding:12px;position:relative;box-shadow:0 1px 2px rgba(0,0,0,.04);display:flex;flex-direction:column;cursor:pointer}
#invoiceModal .fb-branch::before{display:none}
#invoiceModal .fb-branch.sel{border-color:var(--primary);box-shadow:0 0 0 2px rgba(11,94,215,.15)}
#invoiceModal .fb-branch:hover{border-color:#9db9e8}
#invoiceModal .fb-branch-head{display:flex;justify-content:space-between;align-items:center;gap:6px;margin-bottom:10px;flex-wrap:wrap}
#invoiceModal .fb-branch-badge{display:inline-flex;align-items:center;gap:5px;border-radius:12px;padding:3px 10px;font-size:12px;font-weight:600}
#invoiceModal .fb-branch-badge.def{background:#eef4ff;border:1px solid #c9dcf7;color:#0b5ed7}
#invoiceModal .fb-branch-badge.cond{background:#fff4e5;border:1px solid #f5d9a8;color:#d97706}
#invoiceModal .fb-branch-nodes{flex:1;min-width:0}
#invoiceModal .fb-branch-nodes .fb-bnode{width:100%}
#invoiceModal .fb-bnode{border:1px solid var(--line);border-radius:10px;padding:8px 10px;margin-bottom:8px;background:#fafbfc;position:relative}
#invoiceModal .fb-bnode-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:6px}
#invoiceModal .fb-bnode-head .idx{display:inline-flex;align-items:center;gap:6px;font-weight:600;font-size:13px;color:#1f2329}
#invoiceModal .fb-bnode-head .idx .ico{width:20px;height:20px;border-radius:50%;background:var(--primary);color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:11px}
#invoiceModal .fb-bnode .node-ops{display:flex;gap:3px}
#invoiceModal .fb-bnode .node-ops button{width:24px;height:24px;padding:0;line-height:1;font-size:12px}
#invoiceModal .fb-bnode-body .form-select,#invoiceModal .fb-bnode-body .form-control{font-size:12.5px}
#invoiceModal .fb-branch-cc{background:#fffdf3;border:1px solid #f0e3b8;border-radius:10px;padding:8px 10px}
#invoiceModal .fb-branch-cc .cc-title{font-size:12px;color:#8a6d1a;font-weight:600;margin-bottom:6px}
#invoiceModal .fb-chip{display:inline-flex;align-items:center;gap:4px;background:var(--brand-light);color:var(--primary);border-radius:12px;padding:2px 8px;font-size:12px;margin:2px}
#invoiceModal .fb-chip b{cursor:pointer;opacity:.6}
#invoiceModal .fb-opt-tiles{display:flex;flex-direction:column;gap:4px}
#invoiceModal .fb-opt-tiles.tile{flex-direction:row;flex-wrap:wrap}
#invoiceModal .fb-opt-tile{display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border:1px solid var(--line);border-radius:14px;font-size:12px;cursor:default;background:#fafbfc}
#invoiceModal .fb-opt-tiles.tile .fb-opt-tile{flex:0 1 auto}
#invoiceModal .fb-opt-tile i{font-size:13px;color:var(--primary)}
#invoiceModal .fb-cond-multi{display:inline-flex;flex-wrap:wrap;gap:4px}
#invoiceModal .fb-cond-multi .fb-opt-tile{cursor:pointer;user-select:none}
#invoiceModal .fb-cond-multi .fb-opt-tile.on{background:var(--primary);color:#fff;border-color:var(--primary)}
#invoiceModal .fb-cond-multi .fb-opt-tile.on i{color:#fff}
#invoiceModal .fb-branch-amt{background:#f8fafc;border:1px solid #e5e9f0;border-radius:8px;padding:8px 10px;margin-bottom:8px}
#invoiceModal .fb-branch-amt .form-select,#invoiceModal .fb-branch-amt .form-control{font-size:12.5px}
/* 部门树（钉钉后台风格）：左侧可折叠部门结构 + 右侧成员列表 */
.dept-panel{width:260px;min-width:260px;max-height:72vh;overflow:auto;border-right:1px solid var(--line)}
.dept-tree{list-style:none;padding-left:0;margin:0}
.dept-tree ul{list-style:none;padding-left:18px;margin:0}
.dept-node{display:flex;align-items:center;gap:6px;padding:6px 8px;border-radius:6px;cursor:pointer;user-select:none;font-size:14px;line-height:1.3}
.dept-node:hover{background:#f1f3f5}
.dept-node.active{background:var(--brand-light);color:var(--primary);font-weight:600}
.dept-caret{width:16px;text-align:center;color:#adb5bd;flex:0 0 16px;font-size:12px}
.dept-caret.empty{visibility:hidden}
.dept-node.collapsed+ul{display:none}
.dept-all{font-weight:600}
/* 选人弹窗部门树（.up-dept-link）：可点击但此前缺 cursor:pointer，悬停显示默认箭头而非手型 */
.up-dept-link{display:block;padding:4px 8px;border-radius:6px;cursor:pointer;user-select:none;font-size:14px;line-height:1.3}
.up-dept-link:hover{background:#f1f3f5}
</style>

<h4 class="mb-3"><i class="bi bi-gear"></i> 系统设置</h4>

<script>
// 跨 tab 公共数据：用户/角色/合同分类。必须放在所有 tab 分支之前声明，
// 否则在"用户管理"tab 下编辑用户或选择角色时 allRoles 未定义（原声明在审批流 tab 脚本内，
// 而 user tab 不渲染那段，导致 renderRoleView/rpRender 抛 ReferenceError、弹窗打不开）。
let allUsers = <?=json_encode($users??[], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)?>;
let allRoles = <?=json_encode($roles??[], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)?>;
let flowCats = <?=json_encode(dict_enabled('business_type'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)?>;
// 部门（钉钉后台风格左侧部门树用）：扁平 [id,name,parent_id]，前端构建层级
let allDepts = <?=json_encode($depts??[], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)?>;
// esc() 统一下沉至 public/static/js/app.js（全局 window.esc）。
// 为防 app.js 因加载延迟/网络异常/HTTP2 push 重排等使脚本先于 app.js 执行，
// 本块内置最小化 fallback：若 window.esc 尚未定义，以同等安全转义兜底（必须做 HTML 转义，
// 否则 app.js 未加载时 esc() 原样输出用户数据 → 存储型 XSS，与 app.js 内 esc 行为保持一致）。
if (typeof window.esc !== 'function') window.esc = function(s){
    if (s == null) return '';
    return String(s).replace(/[&<>"']/g, function(c){
        return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
    });
};
function syncDingTalk(btn){
    var logEl=document.getElementById('syncLog');
    // 补充观察 6：显式接收触发按钮（onclick 传 this），不再依赖全局 event
    btn = btn || document.querySelector('button[onclick="syncDingTalk()"]');
    btn.disabled=true;btn.innerHTML='<span class="spinner-border spinner-border-sm"></span> 同步中...';
    if(logEl) logEl.innerHTML='<div class="text-center py-2"><div class="spinner-border spinner-border-sm text-primary me-2"></div>正在连接钉钉...</div>';
    $ajax('/ajax/dingtalk/sync-org',{method:'POST',loading:false}).then(res=>{
        btn.disabled=false;btn.innerHTML='<i class="bi bi-cloud-download"></i> 立即同步';
        if(res.code===0){
            if(logEl) logEl.innerHTML='<div class="text-center py-2"><i class="bi bi-check-circle-fill text-success me-2"></i>同步完成：<strong>'+res.data.synced_depts+'</strong> 个部门，<strong>'+res.data.synced_users+'</strong> 个用户</div>';
            showToast('同步成功：'+res.data.synced_users+' 个用户 / '+res.data.synced_depts+' 个部门','success');
            var dcEl=document.getElementById('deptCount');if(dcEl)dcEl.textContent=res.data.synced_depts||'-';
            var ucEl=document.getElementById('userCount');if(ucEl)ucEl.textContent=res.data.synced_users||'-';
            // v2.38.16（P2）：同步后检测疑似离职员工（本地在职但钉钉侧已消失），提示管理员执行交接
            var departed=(res.data&&res.data.departed)||[];
            if(departed.length>0){ showDepartedPrompt(departed); }
            // 仅当用户管理页（存在部门树）时刷新，使左侧部门树与右侧成员列表更新（钉钉设置页保持原日志面板行为）
            if(document.getElementById('deptTree') && departed.length===0){ setTimeout(function(){location.reload();},1200); }
        }else{
            if(logEl) logEl.innerHTML='<div class="text-center py-2"><i class="bi bi-exclamation-triangle-fill text-danger me-2"></i>同步失败：'+esc(res.msg)+'</div>';
            showToast('同步失败：'+res.msg,'error');
        }
        loadMockLogs();
    });
}
// v2.38.16（P2）+ v2.38.25（自动化）：疑似离职员工提示弹窗——列出名单+名下数据量，
// 系统已自动将其加入「待交接」队列，引导前往 用户管理 → 待交接 办理数据移交
function showDepartedPrompt(list){
  var rows=list.map(function(u){
    return '<tr><td>'+esc(u.name)+'</td><td>'+esc(u.dingtalk_userid)+'</td><td>'+u.customers+' 个</td><td>'+u.contracts+' 个</td><td>'+u.pending_approval+' 条</td></tr>';
  }).join('');
  var html='<div class="modal fade" id="departedModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">'
    +'<div class="modal-header bg-warning text-dark"><h5 class="modal-title"><i class="bi bi-person-x"></i> 检测到 '+list.length+' 名疑似离职员工</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>'
    +'<div class="modal-body">'
    +'<div class="alert alert-warning small"><i class="bi bi-exclamation-triangle"></i> 以下员工在钉钉组织架构中已不存在，系统已自动将其加入<strong>「待交接」队列</strong>（未禁用）。请前往 <strong>用户管理 → 待交接</strong> 为每位员工办理数据移交或确认未离职。</div>'
    +'<table class="table table-sm table-bordered mb-0"><thead><tr><th>姓名</th><th>钉钉ID</th><th>客户</th><th>合同</th><th>待审批</th></tr></thead><tbody>'+rows+'</tbody></table>'
    +'</div>'
    +'<div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">稍后处理</button><button class="btn btn-warning" data-bs-dismiss="modal" onclick="showUserMode(\'handover\')">去待交接</button></div>'
    +'</div></div></div>';
  var old=document.getElementById('departedModal'); if(old) old.remove();
  document.body.insertAdjacentHTML('beforeend', html);
  new bootstrap.Modal('#departedModal').show();
}
function saveDDConfig(){var fd=new FormData(document.getElementById('ddConfigForm'));$ajax('/ajax/dingtalk/save-config',{method:'POST',body:fd,loadingText:'保存中…'}).then(function(res){showToast(res.msg||'已保存',res.code===0?'success':'error');if(res.code===0)setTimeout(function(){location.reload();},800);}).catch(function(){});}
function loadMockLogs(){
    var el=document.getElementById('mockLogText');if(!el)return;
    // N-m2：改用 $ajax 统一兜底（loading:false 不弹全局遮罩；silent 不重复 toast，错误就地渲染到面板）
    $ajax('/ajax/dingtalk/mock-logs',{loading:false,silent:true}).then(function(res){
        if(res.code===0&&res.data&&res.data.length>0){
            var h='';res.data.forEach(function(l){
                var p=JSON.stringify(l.params);if(p.length>80)p=p.substring(0,80)+'...';
                h+='<div class="mb-1 border-bottom pb-1"><i class="bi bi-arrow-right-circle text-primary me-1"></i><strong>'+l.method+'</strong> <small class="text-muted ms-2">'+l.timestamp+'</small><div class="text-muted small ps-4">'+p+'</div></div>';
            });
            el.innerHTML=h;
        }else{el.innerHTML='<div class="text-center py-2 text-muted">暂无调用日志</div>';}
    }).catch(function(){el.innerHTML='<div class="text-center py-2 text-muted">日志加载失败</div>';});
}

function showAddUser(){document.getElementById('userForm').reset();document.getElementById('userId').value='';var ul=document.getElementById('uLeader');if(ul)ul.value='0';document.getElementById('uPassword').required=true;document.getElementById('uPassword').placeholder='请设置密码(至少8位)';document.getElementById('uPassword').value='';var pm=document.getElementById('pwdMark');if(pm)pm.style.display='inline';_rpSelected=[];renderRoleView();new bootstrap.Modal('#userModal').show();}
function editUser(u){document.getElementById('userId').value=u.id;document.getElementById('uUsername').value=u.username||'';document.getElementById('uName').value=u.name||'';document.getElementById('uMobile').value=u.mobile||'';document.getElementById('uEmail').value=u.email||'';document.getElementById('uDept').value=u.dept_id||0;document.getElementById('uStatus').value=u.status||1;var ul=document.getElementById('uLeader');if(ul)ul.value=u._is_leader?1:0;document.getElementById('uDDuid').value=u.dingtalk_userid||'';document.getElementById('uDDunion').value=u.dingtalk_unionid||'';document.getElementById('uPassword').required=false;document.getElementById('uPassword').placeholder='留空不修改';document.getElementById('uPassword').value='';var pm=document.getElementById('pwdMark');if(pm)pm.style.display='none';_rpSelected=u._role_ids||[];renderRoleView();new bootstrap.Modal('#userModal').show();}
function saveUser(){if(!document.getElementById('userId').value && !document.getElementById('uPassword').value){showToast('请设置登录密码（至少8位）','error');return;}var fd=new FormData(document.getElementById('userForm'));var rids=_rpSelected.map(function(x){return parseInt(x);});fd.delete('role_ids[]');rids.forEach(function(v){fd.append('role_ids[]',v);});$ajax('/ajax/admin/user/save',{method:'POST',body:new URLSearchParams(fd)}).then(function(res){showToast(res.msg||'操作成功',res.code===0?'success':'error');if(res.code===0)location.reload();}).catch(function(){});}
function delUser(id){pcConfirm({message:'确定禁用该用户？',danger:true}).then(function(ok){if(!ok)return;$ajax('/ajax/admin/user/delete',{method:'POST',body:new URLSearchParams({id:id})}).then(function(res){showToast(res.msg||'已禁用',res.code===0?'success':'error');if(res.code===0)location.reload();}).catch(function(){});});}
// === 部门树（钉钉后台风格）：左侧可折叠部门结构，点选部门高亮并过滤右侧成员列表 ===
let _curDept = 0;        // 当前选中部门（0=全部成员）
let _incChildren = true; // 是否包含子部门

// 收集部门 id 集合（含/不含子部门），用于过滤右侧成员
function collectDeptIds(id, includeChildren){
  let ids = [id];
  if(includeChildren){
    let changed = true;
    while(changed){
      changed = false;
      allDepts.forEach(function(d){
        if(ids.indexOf(d.parent_id) >= 0 && ids.indexOf(d.id) < 0){ ids.push(d.id); changed = true; }
      });
    }
  }
  return ids;
}

// 构建部门树 DOM（递归渲染父子层级）
function buildDeptTree(){
  let box = document.getElementById('deptTree'); if(!box) return;
  let children = {};
  allDepts.forEach(function(d){ (children[d.parent_id] = children[d.parent_id] || []).push(d); });
  function render(list){
    if(!list || !list.length) return '';
    let html = '<ul class="dept-tree">';
    list.forEach(function(d){
      let kids = children[d.id] || [];
      let hasKids = kids.length > 0;
      html += '<li>'
        + '<div class="dept-node" data-id="'+d.id+'" onclick="selectDept('+d.id+')">'
        + '<span class="dept-caret'+(hasKids?'':' empty')+'" onclick="event.stopPropagation();toggleDept('+d.id+',this)"><i class="bi bi-chevron-down"></i></span>'
        + '<span class="dept-label">'+esc(d.name)+'</span>'
        + '</div>';
      if(hasKids) html += render(kids);
      html += '</li>';
    });
    html += '</ul>';
    return html;
  }
  let roots = children[0] || [];
  // 顶部“全部成员”虚拟节点（data-id=0），点击显示所有用户
  box.innerHTML = '<ul class="dept-tree">'
    + '<li><div class="dept-node dept-all active" data-id="0" onclick="selectAllMembers()"><span class="dept-caret empty"></span><span class="dept-label"><i class="bi bi-people-fill me-1"></i>全部成员</span></div></li>'
    + render(roots)
    + '</ul>';
}

// 展开/折叠子部门（不触发选中）；JS 直接控制子树显示，避免依赖 CSS 渲染
function toggleDept(id, el){
  let node = el.closest('.dept-node');
  if(node){
    node.classList.toggle('collapsed');
    let ul = node.nextElementSibling;
    if(ul && ul.tagName === 'UL'){ ul.style.display = node.classList.contains('collapsed') ? 'none' : ''; }
  }
  let icon = el.querySelector('i');
  if(icon){ icon.classList.toggle('bi-chevron-down'); icon.classList.toggle('bi-chevron-right'); }
}

// 选中某部门 → 高亮 + 过滤右侧列表
function selectDept(id){
  _curDept = id;
  _incChildren = document.getElementById('incChildren') ? document.getElementById('incChildren').checked : true;
  highlightDept(id);
  renderUserList();
}
function selectAllMembers(){ _curDept = 0; highlightDept(0); renderUserList(); }
function highlightDept(id){
  document.querySelectorAll('#deptTree .dept-node').forEach(function(n){
    n.classList.toggle('active', n.getAttribute('data-id') === String(id));
  });
}
// “包含子部门”复选框切换
function toggleIncludeChildren(){
  _incChildren = document.getElementById('incChildren').checked;
  renderUserList();
}
// 根据当前部门过滤右侧用户列表（客户端显示/隐藏，不重载页面）
function renderUserList(){
  let ids = (_curDept === 0) ? null : collectDeptIds(_curDept, _incChildren);
  let rows = document.querySelectorAll('#userTableBody tr[data-dept-id]');
  let shown = 0;
  rows.forEach(function(tr){
    let match = (ids === null) || (ids.indexOf(parseInt(tr.getAttribute('data-dept-id'), 10)) >= 0);
    tr.style.display = match ? '' : 'none';
    if(match) shown++;
  });
  let nm = document.getElementById('userNoMatch'); if(nm) nm.style.display = (shown === 0) ? '' : 'none';
  let cnt = document.getElementById('deptUserCount'); if(cnt) cnt.textContent = shown + ' 人';
  let title = document.getElementById('deptUserTitle');
  if(title){
    if(_curDept === 0){ title.textContent = '全部成员'; }
    else { let d = allDepts.find(function(x){ return x.id === _curDept; }); title.textContent = d ? d.name : ('部门#'+_curDept); }
  }
}
</script>

<?php if($tab=='user'): ?>
<div id="activePane"><div class="d-flex gap-3 align-items-start">
  <!-- 左侧部门树（钉钉后台风格：可折叠层级 + 点击高亮） -->
  <div class="card stat-card dept-panel">
    <div class="card-header bg-white py-2"><h6 class="mb-0"><i class="bi bi-diagram-3"></i> 部门</h6></div>
    <div class="card-body p-2">
      <div id="deptTree"></div>
      <div class="form-check mt-2 pt-2 border-top">
        <input class="form-check-input" type="checkbox" id="incChildren" checked onchange="toggleIncludeChildren()">
        <label class="form-check-label small" for="incChildren">包含子部门</label>
      </div>
    </div>
  </div>
  <!-- 右侧成员列表 -->
  <div class="card stat-card flex-fill">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
      <h5 class="mb-0"><span id="deptUserTitle">全部成员</span> <small class="text-muted ms-1" id="deptUserCount"></small></h5>
      <div class="d-flex gap-2">
        <button class="btn btn-primary btn-sm" onclick="syncDingTalk(this)"><i class="bi bi-cloud-download"></i> 同步钉钉</button>
        <button class="btn btn-primary btn-sm" onclick="showAddUser()"><i class="bi bi-plus-lg"></i> 新增用户</button>
        <button class="btn btn-outline-warning btn-sm" onclick="showUserMode('handover')"><i class="bi bi-person-x"></i> 待交接<span class="pc-tag pc-tag-warn ms-1"><?=count($handoverUsers??[])?></span></button>
        <button class="btn btn-outline-secondary btn-sm" onclick="showUserMode('recycle')"><i class="bi bi-archive"></i> 回收站<span class="pc-tag pc-tag-muted ms-1"><?=count($disabledUsers??[])?></span></button>
      </div>
    </div>
<div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>ID</th><th>用户名</th><th>姓名</th><th>手机</th><th>部门</th><th>钉钉ID</th><th>角色</th><th>状态</th><th>操作</th></tr></thead><tbody id="userTableBody">
<?php if(!empty($users)): foreach($users as $u): ?>
<tr data-dept-id="<?=$u['dept_id']??0?>"><td><?=$u['id']?></td><td><?=htmlspecialchars($u['username'])?></td><td><?=htmlspecialchars($u['name'])?></td><td><?=htmlspecialchars($u['mobile']?:'-')?></td>
<td><?=htmlspecialchars($u['dept_name']?:'-')?></td>
<td><?=$u['dingtalk_userid']?'<i class="bi bi-check-circle-fill text-success"></i> '.htmlspecialchars($u['dingtalk_userid']):'<i class="bi bi-dash-circle text-muted"></i>'?></td>
<td><?=htmlspecialchars(implode(', ',$u['roles']??[]))?></td>
<td><?=$u['status']==1?'<span class="pc-tag pc-tag-ok">正常</span>':'<span class="pc-tag pc-tag-danger">禁用</span>'?></td>
<td>
<button class="btn btn-sm btn-primary" aria-label="编辑" onclick='editUser(<?=htmlspecialchars(json_encode($u,JSON_UNESCAPED_UNICODE),ENT_QUOTES)?>)'><i class="bi bi-pencil"></i></button>
<button class="btn btn-sm btn-outline-primary" title="在职数据交接" aria-label="在职数据交接" onclick='showDataTransfer(<?=$u['id']?>, <?=htmlspecialchars(json_encode($u['name'],JSON_UNESCAPED_UNICODE),ENT_QUOTES)?>)'><i class="bi bi-arrow-left-right"></i></button>
<button class="btn btn-sm btn-outline-warning" title="离职交接" aria-label="离职交接" onclick='showHandover(<?=$u['id']?>, <?=htmlspecialchars(json_encode($u['name'],JSON_UNESCAPED_UNICODE),ENT_QUOTES)?>, false)'><i class="bi bi-person-x"></i></button>
<button class="btn btn-sm btn-outline-danger" aria-label="删除" onclick="delUser('<?=$u['id']?>')"><i class="bi bi-trash"></i></button>
</td></tr>
<?php endforeach; else: ?><tr><td colspan="9" class="text-center py-4 text-muted">暂无用户</td></tr><?php endif; ?>
<tr id="userNoMatch" style="display:none"><td colspan="9" class="text-center py-4 text-muted">该部门暂无用户</td></tr>
</tbody></table></div>
  </div>
</div></div><!-- /activePane（含内层 d-flex 行容器，须与 178 行两层 div 配对闭合） -->
<div id="recyclePane" style="display:none">
  <div class="card stat-card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
      <h5 class="mb-0"><i class="bi bi-archive"></i> 回收站（已禁用用户）</h5>
      <button class="btn btn-sm btn-outline-secondary" onclick="showUserMode('active')"><i class="bi bi-arrow-left"></i> 返回在职成员</button>
    </div>
    <div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>ID</th><th>用户名</th><th>姓名</th><th>手机</th><th>部门</th><th>角色</th><th>操作</th></tr></thead><tbody id="recycleBody">
<?php if(!empty($disabledUsers)): foreach($disabledUsers as $u): ?>
    <tr><td><?=$u['id']?></td><td><?=htmlspecialchars($u['username'])?></td><td><?=htmlspecialchars($u['name'])?></td><td><?=htmlspecialchars($u['mobile']?:'-')?></td>
    <td><?=htmlspecialchars($u['dept_name']?:'-')?></td>
    <td><?=htmlspecialchars(implode(', ',$u['roles']??[]))?></td>
    <td><button class="btn btn-sm btn-success" onclick="restoreUser(<?=$u['id']?>)"><i class="bi bi-arrow-counterclockwise"></i> 恢复</button>
    <button class="btn btn-sm btn-outline-warning" title="离职交接" aria-label="离职交接" onclick='showHandover(<?=$u['id']?>, <?=htmlspecialchars(json_encode($u['name'],JSON_UNESCAPED_UNICODE),ENT_QUOTES)?>, true)'><i class="bi bi-person-x"></i></button></td></tr>
<?php endforeach; else: ?><tr><td colspan="7" class="text-center py-4 text-muted">回收站为空</td></tr><?php endif; ?>
    </tbody></table></div>
  </div>
</div>
<!-- v2.38.25：待交接面板（钉钉同步自动标记的疑似离职员工，管理员在此办理数据移交或清除标记） -->
<div id="handoverPane" style="display:none">
  <div class="card stat-card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
      <h5 class="mb-0"><i class="bi bi-person-x"></i> 待交接（疑似离职员工）</h5>
      <button class="btn btn-sm btn-outline-secondary" onclick="showUserMode('active')"><i class="bi bi-arrow-left"></i> 返回在职成员</button>
    </div>
    <div class="card-body py-2 border-bottom">
      <div class="alert alert-warning small mb-0"><i class="bi bi-info-circle"></i> 钉钉同步自动检测疑似离职员工并加入此队列。<strong>办理交接</strong>：将其名下客户/合同/待审批移交给指定账号（可同时禁用）；<strong>未离职</strong>：误报或已回岗时仅清除待交接标记。已禁用用户的交接请到回收站办理。</div>
    </div>
    <div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>ID</th><th>姓名</th><th>手机</th><th>部门</th><th>钉钉ID</th><th>客户</th><th>合同</th><th>待审批</th><th>标记时间</th><th>操作</th></tr></thead><tbody>
<?php if(!empty($handoverUsers)): foreach($handoverUsers as $u): ?>
    <tr>
      <td><?=$u['id']?></td>
      <td><?=htmlspecialchars($u['name'])?> <small class="text-muted">(<?=htmlspecialchars($u['username'])?>)</small></td>
      <td><?=htmlspecialchars($u['mobile']?:'-')?></td>
      <td><?=htmlspecialchars($u['dept_name']?:'-')?></td>
      <td><?=htmlspecialchars($u['dingtalk_userid']?:'-')?></td>
      <td><?=(int)$u['customer_count']?></td>
      <td><?=(int)$u['contract_count']?></td>
      <td><?=(int)$u['approval_count']?></td>
      <td><?=htmlspecialchars(substr((string)($u['updated_at']??''),0,16))?></td>
      <td class="text-nowrap">
        <button class="btn btn-sm btn-warning" onclick='showHandover(<?=$u['id']?>, <?=htmlspecialchars(json_encode($u['name'],JSON_UNESCAPED_UNICODE),ENT_QUOTES)?>, false)'><i class="bi bi-arrow-repeat"></i> 办理交接</button>
        <button class="btn btn-sm btn-outline-secondary" onclick="clearHandover(<?=$u['id']?>)"><i class="bi bi-check2"></i> 未离职</button>
      </td>
    </tr>
<?php endforeach; else: ?><tr><td colspan="10" class="text-center py-4 text-muted">暂无待交接员工</td></tr><?php endif; ?>
    </tbody></table></div>
  </div>
</div>
<script>
// 部门树初始化：buildDeptTree() 内部调用全局 esc()（定义于 public/static/js/app.js），
// 而 app.js 在页脚（body 末尾）才加载，本内联脚本位于 body 中段、先于 app.js 执行。
// 若同步调用会因「esc is not defined」抛错导致部门树为空（PC/移动均不显示）。
// 包进 DOMContentLoaded：回调在整页解析完成、含 app.js 在内的脚本均执行后才触发，esc 已就绪。
document.addEventListener('DOMContentLoaded', function(){
  if(document.getElementById('deptTree')){ buildDeptTree(); selectAllMembers(); }
});
function showUserMode(mode){
  var a=document.getElementById('activePane');
  if(!a) return; // 仅用户管理 tab 存在这些面板（钉钉 tab 弹窗按钮也可能触发，直接忽略）
  var r=document.getElementById('recyclePane'), h=document.getElementById('handoverPane');
  if(mode==='recycle'){ a.style.display='none'; h.style.display='none'; r.style.display=''; }
  else if(mode==='handover'){ a.style.display='none'; r.style.display='none'; h.style.display=''; }
  else { a.style.display=''; r.style.display='none'; h.style.display='none'; }
}
// v2.38.25：清除待交接标记（管理员确认未离职，不做数据移交）
function clearHandover(id){
  pcConfirm({message:'确认该员工并未离职？将清除其待交接标记，不做数据移交。', danger:false}).then(function(ok){if(!ok)return;
  $ajax('/ajax/admin/user/clear-handover',{method:'POST',body:new URLSearchParams({id:id})}).then(function(res){
    showToast(res.msg||'已清除',res.code===0?'success':'error');
    if(res.code===0) location.reload();
  }).catch(function(){});
  });
}
function restoreUser(id){
  pcConfirm({message:'确定恢复该用户为在职？'}).then(function(ok){if(!ok)return;
  $ajax('/ajax/admin/user/restore',{method:'POST',body:new URLSearchParams({id:id})}).then(function(res){
    showToast(res.msg||'已恢复',res.code===0?'success':'error');
    if(res.code===0) location.reload();
  }).catch(function(){});
  });
}
// v2.38.16：离职交接弹窗（fromRecycle=true 表示该用户已在回收站，默认不重复禁用）
let _hoFromId = 0;
function showHandover(id, name, fromRecycle){
  _hoFromId = id;
  document.getElementById('hoFromName').textContent = name;
  var t = document.getElementById('hoModalTitle'); if(t) t.innerHTML = '<i class="bi bi-person-x"></i> 离职交接';
  // 重置接收人选择（复用系统统一选人组件，非下拉）
  document.getElementById('hoToUserId').value = '';
  document.getElementById('hoToUserName').value = '';
  // 回收站用户已禁用，默认不重复禁用
  document.getElementById('hoDisableFrom').checked = !fromRecycle;
  new bootstrap.Modal('#handoverModal').show();
}
// v2.38.26：在职员工数据交接——任意两名在职员工间批量移交客户/合同/待审批，默认不禁用（保留双方在职）
function showDataTransfer(id, name){
  _hoFromId = id;
  document.getElementById('hoFromName').textContent = name;
  var t = document.getElementById('hoModalTitle'); if(t) t.innerHTML = '<i class="bi bi-arrow-left-right"></i> 数据交接';
  document.getElementById('hoToUserId').value = '';
  document.getElementById('hoToUserName').value = '';
  document.getElementById('hoDisableFrom').checked = false;
  new bootstrap.Modal('#handoverModal').show();
}
document.addEventListener('DOMContentLoaded', function(){
  var pickBtn = document.getElementById('hoPickBtn');
  if (pickBtn) {
    pickBtn.addEventListener('click', function(){
      // 单选模式：multiple=false；exclude 排除交接人本人；onConfirm 回调回填 id + 姓名
      openUserPicker({
        multiple: false,
        exclude: [_hoFromId],
        onConfirm: function(ids){
          if (!ids || !ids.length) return;
          var uid = ids[0];
          if (parseInt(uid) === _hoFromId) { showToast('接收人不能是离职员工本人','error'); return; }
          document.getElementById('hoToUserId').value = uid;
          document.getElementById('hoToUserName').value = _up.nameCache[uid] || ('用户#' + uid);
        }
      });
    });
  }
  var btn = document.getElementById('hoConfirmBtn');
  if(!btn) return;
  btn.addEventListener('click', function(){
    var toId = document.getElementById('hoToUserId').value;
    if(!toId){ showToast('请选择接收人','error'); return; }
    if(parseInt(toId) === _hoFromId){ showToast('接收人不能是本人','error'); return; }
    // v2.38.16：交接为不可逆批量操作（含禁用），提交前须二次确认（pcConfirm 内部会转义，用纯文本）
    var scopeParts = [];
    if(document.getElementById('hoScopeCustomer').checked) scopeParts.push('客户');
    if(document.getElementById('hoScopeContract').checked) scopeParts.push('合同');
    if(document.getElementById('hoScopeApproval').checked) scopeParts.push('待审批');
    var disableTxt = document.getElementById('hoDisableFrom').checked ? '，并将该用户禁用' : '';
    var msg = '确认将「' + document.getElementById('hoFromName').textContent
      + '」的' + (scopeParts.join('、') || '（未勾选任何范围）') + '交接给「'
      + document.getElementById('hoToUserName').value + '」' + disableTxt + '？该操作不可撤销。';
    pcConfirm({message: msg, danger: true}).then(function(ok){
      if(!ok) return;
      var body = new URLSearchParams({
        from_user_id: _hoFromId,
        to_user_id: toId,
        scope_customer: document.getElementById('hoScopeCustomer').checked ? 1 : 0,
        scope_contract: document.getElementById('hoScopeContract').checked ? 1 : 0,
        scope_approval: document.getElementById('hoScopeApproval').checked ? 1 : 0,
        disable_from: document.getElementById('hoDisableFrom').checked ? 1 : 0
      });
      btn.disabled = true; btn.textContent = '交接中…';
      $ajax('/ajax/admin/user/handover',{method:'POST',body:body}).then(function(res){
        showToast(res.msg||'交接完成',res.code===0?'success':'error');
        btn.disabled = false; btn.textContent = '确认交接';
        if(res.code===0) location.reload();
      }).catch(function(){ btn.disabled=false; btn.textContent='确认交接'; showToast('交接失败','error'); });
    });
  });
});
</script>

<!-- User Edit Modal -->
<div class="modal fade" id="userModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
<div class="modal-header bg-primary text-white"><h5 class="modal-title"><i class="bi bi-person-gear"></i> 编辑用户</h5><button class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
<form id="userForm"><input type="hidden" name="id" id="userId">
<div class="row g-3">
<div class="col-md-4"><label class="form-label" for="uUsername">用户名</label><input type="text" name="username" class="form-control" id="uUsername" required></div>
<div class="col-md-4"><label class="form-label" for="uName">姓名</label><input type="text" name="name" class="form-control" id="uName" required></div>
<div class="col-md-4"><label class="form-label" for="uPassword">密码 <span class="text-danger" id="pwdMark">*</span></label><input type="text" name="password" class="form-control" id="uPassword" placeholder="留空不修改"></div>
<div class="col-md-6"><label class="form-label" for="uMobile">手机</label><input type="text" name="mobile" class="form-control" id="uMobile"></div>
<div class="col-md-6"><label class="form-label" for="uEmail">邮箱</label><input type="text" name="email" class="form-control" id="uEmail"></div>
<div class="col-md-4"><label class="form-label" for="uDept">部门</label><select name="dept_id" class="form-select" id="uDept"><option value="0">-</option><?php foreach($depts as $d): ?><option value="<?=$d['id']?>"><?=htmlspecialchars($d['name'])?></option><?php endforeach; ?></select></div>
<div class="col-md-4"><label class="form-label" for="uRolesVal">角色</label>
  <div id="uRolesView" class="border rounded p-2 mb-1" style="min-height:38px"><span class="text-muted small">未选择</span></div>
  <input type="hidden" id="uRolesVal" value="[]">
  <button type="button" class="btn btn-sm btn-outline-primary" onclick="openRolePicker()"><i class="bi bi-tags"></i> 选择角色</button>
</div>
<div class="col-md-2"><label class="form-label" for="uStatus">状态</label><select name="status" class="form-select" id="uStatus"><option value="1">正常</option><option value="2">禁用</option></select></div>
<?php if(!empty($is_super_admin)): ?>
<div class="col-md-2"><label class="form-label" for="uLeader">部门负责人</label><select name="is_leader" class="form-select" id="uLeader"><option value="0">否</option><option value="1">是</option></select></div>
<?php endif; ?>
<div class="col-md-6"><label class="form-label" for="uDDuid">钉钉 UserID</label><input type="text" name="dingtalk_userid" class="form-control" id="uDDuid" placeholder="钉钉同步自动填充"></div>
<div class="col-md-6"><label class="form-label" for="uDDunion">钉钉 UnionID</label><input type="text" name="dingtalk_unionid" class="form-control" id="uDDunion" placeholder="钉钉同步自动填充"></div>
</div></form></div>
<div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">取消</button><button class="btn btn-primary" onclick="saveUser()"><i class="bi bi-save"></i> 保存</button></div>
</div></div></div>

<?php elseif($tab=='dingtalk'): ?>
<?php $ddMock = \app\common\service\DingTalkService::isMock(); /* REV-11：界面明确标注当前钉钉对接模式（Mock/生产），避免误以为已接入真实钉钉 */ ?>
<div class="alert <?= $ddMock ? 'alert-warning' : 'alert-success' ?> mb-3">
  <i class="bi bi-<?= $ddMock ? 'shield' : 'check-circle' ?>"></i>
  <strong>当前钉钉对接模式：<?= $ddMock ? 'Mock（模拟）模式' : '生产（真实钉钉）模式' ?></strong>
  <?php if($ddMock): ?><br><small>当前为本地模拟，不会真正向钉钉发送消息或同步组织。上线生产环境前请在「Mock 模式」选择「关闭（真实钉钉）」并正确配置 AppKey / AppSecret / CorpId。</small><?php else: ?><br><small>已对接真实钉钉，消息与免登将实际生效。请务必通过 <code>.env</code> 文件保护 AppSecret（建议 <code>chmod 600 .env</code> 限制权限）。</small><?php endif; ?>
</div>
<div class="card stat-card mb-3"><div class="card-header bg-white d-flex justify-content-between align-items-center"><h5 class="mb-0"><i class="bi bi-gear"></i> 钉钉应用配置</h5></div>
<div class="card-body">
<form id="ddConfigForm" class="row g-3">
<div class="col-md-4"><label class="form-label" for="fDDAppKey">AppKey</label><input type="text" name="app_key" id="fDDAppKey" class="form-control" value="<?=config('dingtalk.app_key')?>" placeholder="dingxxxxxxxxxxxxxxxx"></div>
<div class="col-md-4"><label class="form-label" for="fDDAppSecret">AppSecret</label><input type="password" name="app_secret" id="fDDAppSecret" class="form-control" value="" placeholder="钉钉应用密钥（留空表示不修改）" autocomplete="new-password"><small class="text-muted">出于安全，密钥不回显明文；留空即保留原值</small></div>
<div class="col-md-4"><label class="form-label" for="fDDCorpId">CorpId</label><input type="text" name="corp_id" id="fDDCorpId" class="form-control" value="<?=config('dingtalk.corp_id')?>" placeholder="dingxxxxxxxxxxxxxxxx"></div>
<div class="col-md-4"><label class="form-label" for="fDDAgentId">AgentId</label><input type="text" name="agent_id" id="fDDAgentId" class="form-control" value="<?=config('dingtalk.agent_id')?>"></div>
<div class="col-md-4"><label class="form-label" for="fDDAppUrl">应用首页地址 (APP_URL)</label><input type="text" name="app_url" id="fDDAppUrl" class="form-control" value="<?=config('dingtalk.app_url')?>" placeholder="https://your-domain.com"><small class="text-muted">用于拼接审批消息点击进入系统的深链</small></div>
<div class="col-md-4"><label class="form-label" for="fDDMockMode">Mock 模式</label><select name="mock_mode" id="fDDMockMode" class="form-select"><option value="1" <?=filter_var(config('dingtalk.mock_mode', false), FILTER_VALIDATE_BOOLEAN)?'selected':''?>>开启（本地测试）</option><option value="0" <?=filter_var(config('dingtalk.mock_mode', false), FILTER_VALIDATE_BOOLEAN)?'':'selected'?>>关闭（真实钉钉）</option></select></div>
<div class="col-md-4 d-flex align-items-end"><button type="button" class="btn btn-primary" onclick="saveDDConfig()"><i class="bi bi-save"></i> 保存配置</button></div>
</form></div></div>

<div class="card stat-card mb-3"><div class="card-header bg-white d-flex justify-content-between align-items-center"><h5 class="mb-0"><i class="bi bi-cloud-download"></i> 组织同步</h5><button class="btn btn-primary btn-sm" onclick="syncDingTalk(this)"><i class="bi bi-cloud-download"></i> 立即同步</button></div>
<div class="card-body">
<div class="row g-3">
<div class="col-md-3 col-6"><div class="card stat-card"><div class="card-body p-3 text-center"><h3 class="mb-0" id="deptCount">-</h3><small class="text-muted">部门数</small></div></div></div>
<div class="col-md-3 col-6"><div class="card stat-card"><div class="card-body p-3 text-center"><h3 class="mb-0" id="userCount">-</h3><small class="text-muted">用户已绑定</small></div></div></div>
<div class="col-md-6"><div class="card stat-card"><div class="card-body p-2" id="syncLog" style="min-height:80px;max-height:140px;overflow-y:auto;font-size:13px"><div class="text-muted text-center py-3">点击同步按钮开始同步钉钉组织架构</div></div></div></div>
</div></div></div>

<div class="card stat-card"><div class="card-header bg-white"><h5 class="mb-0"><i class="bi bi-journal-code"></i> Mock 调用日志</h5></div>
<div class="card-body" id="mockLogs" style="max-height:300px;overflow-y:auto;font-size:13px;background:#f8f9fa;border-radius:8px"><div class="text-muted text-center py-3" id="mockLogText">加载中...</div></div></div>

<script>
// 2026-08-07：Mock 日志仅在 dingtalk tab 渲染后加载（原顶层 loadMockLogs() 在元素渲染前调用恒为空操作）
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function(){ loadMockLogs(); });
} else {
    loadMockLogs();
}
</script>

<?php elseif($tab=='role'): ?>
<div class="card stat-card mb-3"><div class="card-header bg-white d-flex justify-content-between align-items-center"><h5 class="mb-0">角色列表</h5><button class="btn btn-primary btn-sm" onclick="newRole()"><i class="bi bi-plus-lg"></i> 新增角色</button></div>
<div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>名称</th><th>编码</th><th>描述</th><th>数据范围</th><th>权限数</th><th>系统</th><th>操作</th></tr></thead><tbody>
<?php if(!empty($roles)): foreach($roles as $r):
  $scopes=['ALL'=>'全部','DEPT'=>'本部门','DEPT_AND_CHILD'=>'本部门及子部门','CUSTOM'=>'自定义部门','SELF'=>'仅自己']; $ds=$r['data_scope']??'SELF';
?>
<tr><td><?=htmlspecialchars($r['name'])?></td><td><code><?=$r['code']?></code></td><td><?=htmlspecialchars($r['description'])?></td>
<td><span class="pc-tag pc-tag-info"><?=$scopes[$ds]??$ds?></span></td>
<td><?=count($r['_permIds'])?></td>
<td><?=$r['is_system']?'<span class="pc-tag pc-tag-muted">系统</span>':'<span class="pc-tag pc-tag-muted">自定义</span>'?></td>
<td>
<button class="btn btn-sm btn-primary" aria-label="编辑" onclick='editRole(<?=htmlspecialchars(json_encode($r,JSON_UNESCAPED_UNICODE), ENT_QUOTES)?>)'><i class="bi bi-pencil"></i></button>
<?php if(!$r['is_system']): ?>
<button class="btn btn-sm btn-outline-danger" aria-label="删除" onclick="delRole(<?=$r['id']?>)"><i class="bi bi-trash"></i></button>
<?php endif; ?>
</td></tr>
<?php endforeach; else: ?><tr><td colspan="7" class="text-center py-4 text-muted">暂无角色</td></tr><?php endif; ?>
</tbody></table></div></div>

<!-- Role Edit Modal -->
<div class="modal fade" id="roleModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
<div class="modal-header bg-primary text-white"><h5 class="modal-title"><i class="bi bi-shield-lock"></i> 编辑角色</h5><button class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
<form id="roleForm"><input type="hidden" name="id" id="roleId"><input type="hidden" name="code" id="roleCode">
<div class="row g-3 mb-3">
<div class="col-md-3"><label class="form-label" for="roleName">名称 <span class="text-danger">*</span></label><input type="text" name="name" class="form-control" required id="roleName"></div>
<div class="col-md-3"><label class="form-label" for="roleScope">数据范围</label><select name="data_scope" class="form-select" id="roleScope" onchange="toggleRoleDeptBox()">
<option value="ALL">全部数据</option><option value="DEPT">本部门</option><option value="DEPT_AND_CHILD">本部门及子部门</option><option value="CUSTOM">自定义部门</option><option value="SELF">仅自己</option></select>
<!-- REV-26：多角色取最高范围语义提示，避免分配高范围角色无意放大用户数据可见范围 -->
<div class="form-text small text-muted">多角色取最高范围：当用户拥有多个角色时，任一为「全部数据」即视为全部，否则任一为「本部门」即本部门，仅当全部为「仅自己」时才只可见本人数据。分配角色时请注意避免越权扩权。</div></div>
<div class="col-md-3"><label class="form-label" for="roleDesc">描述</label><input type="text" name="description" class="form-control" id="roleDesc"></div>
<div class="col-md-12" id="roleDeptBox" style="display:none">
  <label class="form-label">可访问部门（数据范围=自定义部门时生效）</label>
  <div id="roleDeptChecks" class="border rounded p-2" style="max-height:200px;overflow:auto"></div>
  <div class="form-text small text-muted">勾选该角色可访问的部门；未勾选任何部门时等同于「仅自己」。</div>
</div>
</div>
<h6 class="mb-2">权限分配</h6>
<div class="row g-2" id="permGrid">
<?php $groups=[]; foreach($permissions as $p): $groups[$p['group_name']][]=$p; endforeach; ?>
<?php foreach($groups as $gn=>$gps): ?>
<div class="col-md-6"><div class="border rounded p-2"><strong class="small text-muted"><?=htmlspecialchars($gn)?></strong>
<div class="d-flex flex-wrap gap-1 mt-1">
<?php foreach($gps as $p): ?>
<label class="form-check form-check-inline small m-0"><input class="form-check-input perm-cb" type="checkbox" name="perm_ids[]" value="<?=$p['id']?>"> <?=htmlspecialchars($p['name'])?></label>
<?php endforeach; ?>
</div></div></div>
<?php endforeach; ?>
</div></form></div>
<div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">取消</button><button class="btn btn-primary" onclick="saveRole()"><i class="bi bi-save"></i> 保存</button></div>
</div></div></div>

<script>
function renderRoleDeptChecks(){
  var box=document.getElementById('roleDeptChecks'); if(!box) return;
  if(!allDepts) return;
  var byId={}; allDepts.forEach(function(d){byId[d.id]=d;});
  function depth(d){var n=0;var cur=d;while(cur&&cur.parent_id){n++;cur=byId[cur.parent_id];if(n>20)break;}return n;}
  var html='';
  allDepts.forEach(function(d){
    var pad=depth(d)*16;
    html+='<div class="form-check"><input class="form-check-input role-dept-cb" type="checkbox" name="role_dept_ids[]" value="'+d.id+'" id="rdept'+d.id+'"><label class="form-check-label small" for="rdept'+d.id+'" style="padding-left:'+pad+'px">'+esc(d.name)+'</label></div>';
  });
  box.innerHTML=html;
}
function toggleRoleDeptBox(){
  var sel=document.getElementById('roleScope');
  var box=document.getElementById('roleDeptBox');
  if(!sel||!box) return;
  var show = sel.value==='CUSTOM';
  box.style.display = show ? 'block' : 'none';
  if(show){ if(!document.querySelector('.role-dept-cb')){ renderRoleDeptChecks(); } }
  else { document.querySelectorAll('.role-dept-cb').forEach(function(cb){cb.checked=false;}); }
}
function newRole(){document.getElementById('roleForm').reset();document.getElementById('roleId').value='';document.querySelectorAll('.perm-cb').forEach(function(cb){cb.checked=false;});if(document.getElementById('roleScope'))document.getElementById('roleScope').value='SELF';toggleRoleDeptBox();new bootstrap.Modal('#roleModal').show();}
function editRole(r){document.getElementById('roleId').value=r.id;document.getElementById('roleName').value=r.name;document.getElementById('roleCode').value=r.code;document.getElementById('roleScope').value=r.data_scope||'SELF';document.getElementById('roleDesc').value=r.description||'';var pids=r._permIds||[];document.querySelectorAll('.perm-cb').forEach(function(cb){cb.checked=pids.includes(parseInt(cb.value));});toggleRoleDeptBox();var dids=r._deptIds||[];document.querySelectorAll('.role-dept-cb').forEach(function(cb){cb.checked=dids.includes(parseInt(cb.value));});new bootstrap.Modal('#roleModal').show();}
function saveRole(){var fd=new FormData(document.getElementById('roleForm'));var pids=[];document.querySelectorAll('.perm-cb:checked').forEach(function(cb){pids.push(cb.value);});fd.delete('perm_ids[]');pids.forEach(function(v){fd.append('perm_ids[]',v);});var dids=[];var boxVisible=document.getElementById('roleDeptBox')&&document.getElementById('roleDeptBox').style.display!=='none';if(boxVisible){document.querySelectorAll('.role-dept-cb:checked').forEach(function(cb){dids.push(cb.value);});}fd.delete('role_dept_ids[]');dids.forEach(function(v){fd.append('role_dept_ids[]',v);});$ajax('/ajax/admin/role/save',{method:'POST',body:new URLSearchParams(fd)}).then(function(res){showToast(res.msg||'已保存',res.code===0?'success':'error');if(res.code===0)location.reload();}).catch(function(){});}
function delRole(id){pcConfirm({message:'确定删除此角色？',danger:true}).then(function(ok){if(!ok)return;$ajax('/ajax/admin/role/delete',{method:'POST',body:new URLSearchParams({id:id})}).then(function(res){showToast(res.msg||'已删除',res.code===0?'success':'error');if(res.code===0)location.reload();}).catch(function(){});});}
</script>

<?php elseif($tab=='flow'): ?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <h6 class="mb-0 text-primary"><i class="bi bi-diagram-3"></i> 审批流程 <span class="text-muted small fw-normal">（统一管理合同流程与发票流程；新建时选择类型，打开对应配置页）</span></h6>
  <div class="d-flex gap-2">
    <button class="btn btn-outline-secondary btn-sm" onclick="showFlowMode('recycle')"><i class="bi bi-archive"></i> 回收站<span class="pc-tag pc-tag-muted ms-1" id="flowRecycleCount"><?= count(array_filter($flows ?? [], function($f){ $s = $f['status'] ?? null; return $s === 0 || $s === '0'; })) ?></span></button>
    <a href="/admin/form-builder?form=invoice_apply" class="btn btn-outline-primary btn-sm" onclick="openInvoiceEditor();return false;" title="在弹窗中配置发票审批流程"><i class="bi bi-receipt"></i> 新建发票流程</a>
    <button class="btn btn-primary btn-sm" onclick="newFlow('contract')"><i class="bi bi-file-earmark-text"></i> 新建合同流程</button>
  </div>
</div>
<div class="text-muted small mb-2"><i class="bi bi-grip-vertical"></i> 拖动行可排序：<b>同类流程内越靠前优先级越高</b>。同一优先级若有多个流程同时命中，系统将阻止提交并提示调整，避免误用审批链。</div>
<div id="flowActivePane">
<div class="card stat-card"><div class="table-responsive"><table class="table table-hover mb-0"><thead class="table-light"><tr><th style="width:44px" title="拖动排序">排序</th><th style="width:90px">类型</th><th>名称</th><th>编码</th><th>分类</th><th>金额范围</th><th>节点</th><th>状态</th><th>操作</th></tr></thead><tbody id="flowTb">
<?php if(!empty($flows)): foreach($flows as $f): if(($f['status'] ?? null) === 0 || ($f['status'] ?? null) === '0') continue;  // v2.38.26：仅跳过真正被删除（status=0）的流程；发票流程 status 为空串也照常显示在列表，删除后进入同一回收站
$f['_nodes']=json_decode($f['nodes']??'[]',true)?:[]; $nodes=$f['_nodes'];
$fBiz = (string)($f['biz_type'] ?? 'contract');
$fBiz = $fBiz === '' ? 'contract' : $fBiz;
$fCode = (string)($f['code'] ?? '');
// 类型判定：biz_type=invoice 或 INVOICE 前缀 → 发票流程（发票流由 form-builder 设计器管理，新建/编辑均走弹窗编辑器）
$isInvoice = $fBiz === 'invoice' || strpos($fCode, 'INVOICE') === 0;
?>
<tr data-id="<?=(int)$f['id']?>" data-biz="<?=htmlspecialchars($fBiz)?>" draggable="<?= $isInvoice ? 'false' : 'true' ?>"><td class="text-center text-muted flow-drag-handle"><i class="bi bi-grip-vertical"></i></td><td><?=$isInvoice?'<span class="pc-tag pc-tag-warn">发票</span>':'<span class="pc-tag pc-tag-ok">合同</span>'?></td><td><?=htmlspecialchars($f['name'])?></td><td><code><?=htmlspecialchars($fCode)?></code></td><td><?php
$catNames = [];
if (!empty($f['business_type_list'])) { $cl = json_decode($f['business_type_list'], true) ?: []; foreach ($cl as $c) { $catNames[] = dict_enabled('business_type')[$c] ?? $c; } }
echo $catNames ? htmlspecialchars(implode('、', $catNames)) : '不限';
?></td>
<td><?php if (empty($f['use_amount'])): ?>不限金额<?php else: ?>¥<?=$f['min_amount']?> ~ ¥<?=$f['max_amount']?><?php endif; ?></td>
<?php $ccInfo = json_decode($f['cc_list'] ?? '[]', true) ?: []; $ccCnt = count(array_merge($ccInfo['role_codes'] ?? [], $ccInfo['cc_user_ids'] ?? [])); ?>
<td><span class="pc-tag pc-tag-muted"><?=count($nodes)?> 审批节点<?=$ccCnt ? ' + '.$ccCnt.' 抄送' : ''?></span></td>
<td><?= (($f['status'] ?? null) === 1 || ($f['status'] ?? null) === '1' || $isInvoice) ? '<span class="pc-tag pc-tag-ok">启用</span>' : '<span class="pc-tag pc-tag-muted">停用</span>' ?></td>
<td><?php $f['_nodes'] = json_decode($f['nodes']?:'[]',true)?:[]; ?>
<?php if ($isInvoice): ?>
<button class="btn btn-sm btn-primary" onclick="openInvoiceEditor()"><i class="bi bi-pencil"></i> 编辑</button>
<?php else: ?>
<button class="btn btn-sm btn-primary" onclick='editFlow(<?=htmlspecialchars(json_encode($f,JSON_UNESCAPED_UNICODE),ENT_QUOTES)?>)'><i class="bi bi-pencil"></i> 编辑</button>
<?php endif; ?>
<button class="btn btn-sm btn-outline-danger" aria-label="删除" onclick="delFlow('<?=$f['id']?>')"><i class="bi bi-trash"></i></button></td></tr>
<?php endforeach; unset($f); else: ?><tr><td colspan="9" class="text-center py-4 text-muted">暂无流程</td></tr><?php endif; ?>
</tbody></table></div></div>
</div><!-- /flowActivePane -->

<!-- 审批流程回收站（已删除的合同/发票审批流共用同一回收站，可恢复或彻底删除；镜像用户回收站交互） -->
<div id="flowRecyclePane" style="display:none">
  <div class="card stat-card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
      <h6 class="mb-0"><i class="bi bi-archive"></i> 回收站（已删除的审批流程）</h6>
      <button class="btn btn-sm btn-outline-secondary" onclick="showFlowMode('active')"><i class="bi bi-arrow-left"></i> 返回流程列表</button>
    </div>
    <div class="table-responsive"><table class="table table-hover mb-0"><thead class="table-light"><tr><th>类型</th><th>名称</th><th>编码</th><th>分类</th><th>金额范围</th><th>状态</th><th>操作</th></tr></thead><tbody>
<?php $hasRecycle = false; foreach($flows ?? [] as $f): $s = $f['status'] ?? null; if($s !== 0 && $s !== '0') continue;  // v2.38.26：仅显示真正被删除（status=0）的流程；合同与发票共用同一回收站
$fBiz = (string)($f['biz_type'] ?? 'contract'); $fBiz = $fBiz === '' ? 'contract' : $fBiz;
$fCode = (string)($f['code'] ?? '');
$isInvoice = $fBiz === 'invoice' || strpos($fCode, 'INVOICE') === 0;
$hasRecycle = true;
$catNames = [];
if (!empty($f['business_type_list'])) { $cl = json_decode($f['business_type_list'], true) ?: []; foreach ($cl as $c) { $catNames[] = dict_enabled('business_type')[$c] ?? $c; } }
?>
      <tr><td><?=$isInvoice?'<span class="pc-tag pc-tag-warn">发票</span>':'<span class="pc-tag pc-tag-ok">合同</span>'?></td><td><?=htmlspecialchars($f['name'])?></td><td><code><?=htmlspecialchars($fCode)?></code></td><td><?= $catNames ? htmlspecialchars(implode('、', $catNames)) : '不限' ?></td>
      <td><?php if (empty($f['use_amount'])): ?>不限金额<?php else: ?>¥<?=$f['min_amount']?> ~ ¥<?=$f['max_amount']?><?php endif; ?></td>
      <td><span class="pc-tag pc-tag-muted">停用</span></td>
      <td>
        <button class="btn btn-sm btn-outline-success" onclick="restoreFlow(<?=$f['id']?>)"><i class="bi bi-arrow-counterclockwise"></i> 恢复</button>
        <button class="btn btn-sm btn-outline-danger" onclick="purgeFlow(<?=$f['id']?>)"><i class="bi bi-x-circle"></i> 彻底删除</button>
      </td></tr>
<?php endforeach; if(!$hasRecycle): ?><tr><td colspan="7" class="text-center py-4 text-muted">回收站为空</td></tr><?php endif; ?>
    </tbody></table></div>
  </div>
</div>
<script>
// v2.38.24：审批流程拖动排序（同类流程内拖拽；drop 后自动保存 sort_order）
(function(){
  var tb = document.getElementById('flowTb');
  if (!tb) return;
  var dragRow = null, dragBiz = null, overRow = null;
  tb.addEventListener('dragstart', function(e){
    var tr = e.target.closest('tr[data-id]');
    if (!tr || e.target.closest('button,a,input,select')) { e.preventDefault(); return; } // 行内按钮不触发拖拽
    dragRow = tr; dragBiz = tr.getAttribute('data-biz');
    tr.classList.add('flow-dragging');
    e.dataTransfer.effectAllowed = 'move';
    try { e.dataTransfer.setData('text/plain', tr.getAttribute('data-id')); } catch(_){}
  });
  tb.addEventListener('dragend', function(){ cleanup(); });
  tb.addEventListener('dragover', function(e){
    if (!dragRow) return;
    var tr = e.target.closest('tr[data-id]');
    if (!tr || tr.getAttribute('data-biz') !== dragBiz) { e.dataTransfer.dropEffect = 'none'; return; } // 跨类型禁止
    e.preventDefault(); e.dataTransfer.dropEffect = 'move';
    if (overRow && overRow !== tr) overRow.classList.remove('flow-drop-target');
    overRow = tr; tr.classList.add('flow-drop-target');
  });
  tb.addEventListener('drop', function(e){
    e.preventDefault();
    if (!dragRow || !overRow || overRow === dragRow || overRow.getAttribute('data-biz') !== dragBiz) { cleanup(); return; }
    var rect = overRow.getBoundingClientRect();
    tb.insertBefore(dragRow, e.clientY > rect.top + rect.height / 2 ? overRow.nextSibling : overRow);
    var ids = [];
    tb.querySelectorAll('tr[data-id]').forEach(function(tr){
      if (tr.getAttribute('data-biz') === dragBiz) ids.push(tr.getAttribute('data-id'));
    });
    var biz = dragBiz;
    cleanup();
    // 保存排序（同类内全部行重编号）
    var body = new URLSearchParams({ ids: ids.join(',') });
    $ajax('/ajax/admin/flow/sort', { method: 'POST', body: body, loading: false, loadingText: '保存排序中…' }).then(function(res){
      showToast(res.msg || '排序已保存', res.code === 0 ? 'success' : 'error');
    }).catch(function(){ showToast('排序保存失败', 'error'); });
  });
  function cleanup(){
    if (dragRow) dragRow.classList.remove('flow-dragging');
    if (overRow) overRow.classList.remove('flow-drop-target');
    dragRow = null; dragBiz = null; overRow = null;
  }
})();
// 审批流程回收站：在「流程列表」与「回收站」两个面板间切换（镜像用户回收站交互）
function showFlowMode(mode){
  var a=document.getElementById('flowActivePane'), r=document.getElementById('flowRecyclePane');
  if(!a||!r) return;
  if(mode==='recycle'){ a.style.display='none'; r.style.display=''; }
  else { a.style.display=''; r.style.display='none'; }
}
// 彻底删除审批流程（后端会校验是否存在审批实例/模板引用，避免破坏历史关联）
function purgeFlow(id){
  pcConfirm({message:'确定彻底删除该审批流程？\n此操作不可恢复；若已有审批实例或模板引用该流程将被阻止。', danger:true}).then(function(ok){
    if(!ok) return;
    $ajax('/ajax/admin/flow/purge',{method:'POST',body:new URLSearchParams({id:id}),loading:false}).then(function(res){
      showToast(res.msg||'已删除',res.code===0?'success':'error');
      if(res.code===0) location.reload();
    }).catch(function(){});
  });
}
</script>

<!-- Flow Editor Modal（v2.38.22 恢复：左侧图形化画布 + 右侧流程配置侧边栏；添加审批节点按钮移入左侧画布） -->
<div class="modal fade" id="flowModal" tabindex="-1"><div class="modal-dialog modal-xxl"><div class="modal-content"><div class="modal-header bg-primary text-white"><h5 class="modal-title"><i class="bi bi-diagram-3"></i> 审批流程编辑器</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
<div class="modal-body p-0">
<form id="flowForm"><input type="hidden" name="id" id="flowId"><input type="hidden" name="code" id="flowCode"><input type="hidden" name="biz_type" id="flowBizType" value="contract">
<div class="flow-editor-layout">
  <!-- 左侧：图形化画布（发起人 → 审批节点链 → 抄送 → 结束；添加节点按钮在画布内） -->
  <div class="flow-canvas-panel">
    <div class="flow-canvas-head"><i class="bi bi-diagram-3"></i> 流程画布 <span class="text-muted small">发起人 → 审批节点 → 进入执行并抄送 → 结束</span></div>
    <div class="flow-canvas-scroll">
      <div class="flow-canvas" id="nodeEditor"></div>
    </div>
  </div>
  <!-- 右侧：侧边配置面板（流程设置项） -->
  <div class="flow-config-panel">
    <h6 class="flow-config-title"><i class="bi bi-sliders"></i> 流程设置</h6>
    <div class="mb-3"><label class="form-label" for="flowName">流程名称 <span class="text-danger">*</span></label><input type="text" name="name" class="form-control form-control-sm" required id="flowName" placeholder="如：标准审批"></div>
    <div class="mb-3"><label class="form-label" for="flowCatsVal">适用业务类型 <span class="text-muted small">(可多选，不选=适用全部)</span></label>
      <div id="flowCatsBox" class="d-flex flex-wrap gap-1 border rounded p-2" style="min-height:42px"></div>
      <input type="hidden" id="flowCatsVal" name="business_type_list" value="[]">
    </div>
    <div class="row g-2 mb-3"><div class="col-6"><label class="form-label">收付款方向</label><select class="form-select form-select-sm" name="direction" id="flowDirection"><option value="ALL">全部</option><option value="sales">销售收款</option><option value="purchase">采购付款</option></select></div><div class="col-6"><label class="form-label">交易属性</label><select class="form-select form-select-sm" name="trade_attr_condition" id="flowTradeAttr"><option value="ALL">全部</option><option value="1">交易合同</option><option value="0">非交易合同</option></select></div></div>
    <div class="mb-3">
      <label class="form-label" for="flowUseAmount">金额条件</label>
      <select class="form-select form-select-sm" id="flowUseAmount" name="use_amount" onchange="toggleAmountFields()">
        <option value="1">启用（按金额区间匹配）</option>
        <option value="0">不启用（不限金额）</option>
      </select>
      <div class="row g-2 mt-1" id="amtMinWrap"><div class="col-6"><label class="form-label small" for="flowMin">下限 ¥</label><input type="number" step="0.01" name="min_amount" class="form-control form-control-sm" id="flowMin" value="0"></div>
      <div class="col-6" id="amtMaxWrap"><label class="form-label small" for="flowMax">上限 ¥</label><input type="number" step="0.01" name="max_amount" class="form-control form-control-sm" id="flowMax" value="99999999.99"></div></div>
    </div>
    <div class="mb-3"><label class="form-label" for="flowStatus">状态</label><select name="status" class="form-select form-select-sm" id="flowStatus"><option value="1">启用</option><option value="0">停用</option></select></div>
    <hr>
    <div class="text-muted small">点击左侧画布节点卡片内的「选择审批人」配置审批人；节点可上下移动、删除；「添加审批节点」按钮在画布底部。</div>
  </div>
</div>
</form></div>
<div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">取消</button><button class="btn btn-primary" onclick="saveFlow()"><i class="bi bi-save"></i> 保存流程</button></div></div></div></div>
<script src="/static/js/admin/flow-editor.js?v=8"></script>

<!-- 发票流程编辑器弹窗（v2.38.25：参照合同审批编辑器——左画布分支并列 + 右配置面板；form-builder.js 驱动） -->
<div class="modal fade" id="invoiceModal" tabindex="-1"><div class="modal-dialog modal-xxl"><div class="modal-content"><div class="modal-header bg-primary text-white"><h5 class="modal-title"><i class="bi bi-receipt"></i> 发票流程配置</h5><button class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
<div class="modal-body p-0">
  <div class="fb2-layout">
    <!-- 左侧：图形化画布（发起人 → 分支区并列 → 结束） -->
    <div class="fb2-canvas-panel">
      <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
        <h6 class="mb-0"><i class="bi bi-diagram-3"></i> 审批与抄送</h6>
        <button class="btn btn-primary btn-sm" onclick="fbSaveFlow()"><i class="bi bi-save"></i> 保存审批设置</button>
      </div>
      <div id="fbFlowGroups"></div>
    </div>
    <!-- 右侧：流程配置面板（开票内容选项固定配置项 + 选中分支金额条件） -->
    <div class="fb2-config-panel">
      <h6 class="fb2-config-title"><i class="bi bi-sliders"></i> 流程配置</h6>
      <div class="mb-3 pb-3" style="border-bottom:1px solid var(--line)">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <label class="form-label mb-0"><i class="bi bi-list-ul"></i> 开票内容选项</label>
          <button class="btn btn-sm btn-outline-primary py-0 px-1" onclick="fbContentOptOpen()"><i class="bi bi-gear"></i> 编辑</button>
        </div>
        <div id="fbContentOptView" class="d-flex flex-wrap gap-1"><span class="text-muted small">加载中…</span></div>
      </div>
      <div id="fbFlowConfigBody"><span class="text-muted small">加载中…</span></div>
    </div>
  </div>
</div>
<div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">取消</button><button class="btn btn-primary" onclick="fbSaveFlow()"><i class="bi bi-save"></i> 保存审批设置</button></div></div></div></div>

<!-- 开票内容选项编辑弹窗（发票流程编辑器「编辑」打开） -->
<div class="modal fade" id="fbOptModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
<div class="modal-header"><h6 class="modal-title"><i class="bi bi-list-ul"></i> 编辑开票内容选项</h6><button class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
  <div class="text-muted small mb-2">每行一个选项，填写显示名称即可（保存后申请表单「开票内容」下拉即时生效）</div>
  <textarea class="form-control" id="fbOptText" rows="8" placeholder="软件开发服务费&#10;咨询服务费"></textarea>
  <div class="text-danger small mt-1" id="fbOptErr"></div>
</div>
<div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">取消</button><button class="btn btn-primary" onclick="fbOptSave()">保存</button></div>
</div></div></div>

<script>
// v2.38.25：发票流程编辑器弹窗数据注入（Step2 角色/用户/公司/分类下拉；flow 由 form-data 接口提供）
window.__formBuilder = {
  form: 'invoice_apply',
  types: <?=json_encode($invoice_field_types ?? [], JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>,
  roles: <?=json_encode($inv_builder_roles ?? [], JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>,
  users: <?=json_encode($inv_builder_users ?? [], JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>,
  companies: <?=json_encode($inv_builder_companies ?? [], JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>,
  categories: <?=json_encode($inv_builder_categories ?? (object)null, JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>
};
</script>
<script src="/static/js/form-builder.js?v=<?=time()?>"></script>
<script>
/** v2.38.26：打开发票流程编辑器弹窗（新建/编辑统一弹窗；form-builder.js 页面加载时已初始化画布） */
function openInvoiceEditor(){
  // 标题区分：尚无存量发票流程 →「新建发票流程」；已有 →「发票流程配置」（发票流程为单套多分支模型，新建=重新配置）
  var t = document.querySelector('#invoiceModal .modal-title');
  if (t) t.innerHTML = '<i class="bi bi-receipt"></i> ' + (window.fbFlowHasSaved ? '发票流程配置' : '新建发票流程');
  new bootstrap.Modal('#invoiceModal').show();
}
</script>
<?php elseif($tab=='dict'): ?>
<!-- 业务类型设置 — 行内编辑，无需弹窗 -->
<style>
.dict-row{border-radius:10px;margin-bottom:10px;border:1px solid var(--line);overflow:hidden}
.dict-header{display:flex;align-items:center;gap:8px;padding:10px 16px;cursor:pointer;user-select:none;background:#fff}
.dict-header:hover{background:#f6f8fc}
.dict-header .dict-chev{color:var(--text-3);transition:transform .15s}
.dict-header.active .dict-chev{transform:rotate(90deg)}
.dict-label{min-width:110px;font-weight:600}
.dict-item{display:inline-flex;align-items:center;background:#f0f4ff;color:var(--primary);border-radius:6px;padding:3px 10px;margin:2px 4px;font-size:13px;cursor:grab;transition:all .15s}
.dict-item:hover{background:#dbeafe;transform:translateY(-1px)}
/* v2.47.2：拖动排序视觉反馈 */
.dict-item:active{cursor:grabbing}
.dict-dragging{opacity:.45}
.dict-drag-over{outline:2px dashed var(--primary);outline-offset:1px;background:#dbeafe}
.dict-item .del-x{font-size:12px;margin-left:5px;color:var(--text-3);line-height:1;cursor:pointer}
.dict-item .del-x:hover{color:var(--danger)}
/* v2.40.7：停用项样式（灰底+删除线），点击停用按钮可恢复 */
.dict-item-off{background:#f1f3f5;color:var(--text-3);text-decoration:line-through}
.dict-item-off:hover{background:#e9ecef}
.dict-item .dict-toggle{font-size:12px;margin-left:4px;color:var(--text-3);line-height:1;cursor:pointer;border-left:1px solid rgba(11,94,215,.18);padding-left:5px}
.dict-item .dict-toggle:hover{color:var(--primary)}
/* v2.40.7：停用项收进折叠区——主列表只展示启用项，保持干净；点击「已停用 N 项」展开/收起 */
.dict-off-zone{margin-top:8px;border-top:1px dashed var(--line);padding-top:6px}
.dict-off-toggle{display:inline-flex;align-items:center;gap:4px;font-size:12px;color:var(--text-3);cursor:pointer;user-select:none}
.dict-off-toggle:hover{color:var(--primary)}
.dict-off-toggle i{transition:transform .15s}
.dict-off-toggle.open i{transform:rotate(90deg)}
.dict-off-items{display:none;margin-top:6px}
.dict-inline-form{display:inline-flex;align-items:center;gap:4px;margin:2px 4px}
.dict-inline-form input{padding:2px 6px;font-size:12px;border:1px solid #cfe2ff;border-radius:4px;width:80px}
.dict-inline-form input:first-child{width:70px}
</style>

<?php
// v2.28.2：dicts 由 AdminController::index() 注入（AdminLogic::getDicts()），视图层不再 Db::name
$dicts = $dicts ?? [];
$labels = [
  'dict_contract_status'   => ['title'=>'合同状态',       'icon'=>'flag',       'color'=>'secondary'],
  'dict_supplier_type'     => ['title'=>'供应商类型',     'icon'=>'truck',      'color'=>'info'],
  'dict_invoice_type'      => ['title'=>'发票类型',       'icon'=>'receipt',    'color'=>'warning'],
  'dict_invoice_status'    => ['title'=>'发票状态',       'icon'=>'receipt-cutoff','color'=>'dark'],
  'dict_payment_method'    => ['title'=>'收款方式',       'icon'=>'cash-stack', 'color'=>'success'],
  'dict_payment_milestone' => ['title'=>'回款里程碑',     'icon'=>'signpost-split', 'color'=>'primary'],
  'dict_payment_status'    => ['title'=>'回款状态',       'icon'=>'wallet2',    'color'=>'danger'],
  'dict_customer_source'   => ['title'=>'客户来源',       'icon'=>'link',       'color'=>'success'],
  'dict_customer_industry' => ['title'=>'客户行业',       'icon'=>'building',   'color'=>'info'],
  'dict_data_scope'        => ['title'=>'数据权限范围',   'icon'=>'shield-check','color'=>'secondary'],
  'dict_tax_rate'          => ['title'=>'税率',           'icon'=>'percent',    'color'=>'danger'],
  'dict_project_status'    => ['title'=>'项目状态',       'icon'=>'kanban',     'color'=>'primary'],
  'dict_business_type'     => ['title'=>'业务类型',       'icon'=>'diagram-3',  'color'=>'primary'],
  'dict_customer_lifecycle'=> ['title'=>'客户生命周期',   'icon'=>'arrow-repeat','color'=>'info'],
];
?>
<?php if(!empty($dicts)): ?>
<div class="d-flex justify-content-between align-items-center mb-2">
  <span class="text-muted small">共 <?=count($dicts)?> 个字典，点击标题展开编辑选项；启用项可直接拖动调整排序</span>
  <div>
    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="dictExpandAll()"><i class="bi bi-chevron-double-down"></i> 全部展开</button>
    <button type="button" class="btn btn-outline-secondary btn-sm ms-1" onclick="dictCollapseAll()"><i class="bi bi-chevron-double-up"></i> 全部收起</button>
  </div>
</div>
<?php foreach($dicts as $di):
  $key  = $di['config_key'];
  $meta = $labels[$key] ?? ['title'=>str_replace('dict_','',$key), 'icon'=>'tag', 'color'=>'secondary'];
  $items = json_decode($di['config_value'], true) ?: [];
  $escKey = htmlspecialchars($key);
?>
<div class="card dict-row" data-dict="<?=$escKey?>">
  <div class="dict-header" onclick="toggleDict(this)" title="点击展开/收起">
    <i class="bi bi-chevron-right dict-chev"></i>
    <i class="bi bi-<?=$meta['icon']?> text-<?=$meta['color']?>"></i>
    <span><?=$meta['title']?></span>
    <span class="badge text-bg-light border"><?=count($items)?> 项</span>
    <span class="ms-auto text-muted small"><?=$escKey?></span>
  </div>
  <div class="card-body py-2 px-3" style="display:none">
  <div class="flex-grow-1" style="line-height:2.2">
  <?php
    // v2.40.7：主列表仅展示启用项；停用项收进「已停用 N 项」折叠区，保持列表干净
    $activeItems = [];
    $offItems = [];
    foreach ($items as $code=>$label) {
        if (in_array($code, $di['disabled'] ?? [], true)) $offItems[$code] = $label;
        else $activeItems[$code] = $label;
    }
  ?>
  <div class="dict-items-active" id="dictActive_<?=$escKey?>">
  <?php foreach($activeItems as $code=>$label):
    $escCode = addslashes(htmlspecialchars($code));
  ?>
    <span class="dict-item" draggable="true" data-code="<?=$escCode?>" onclick="dictEdit(this,'<?=$escKey?>','<?=$escCode?>','<?=addslashes(htmlspecialchars($label))?>')" title="拖动排序，点击修改">
      <?=htmlspecialchars($label)?>
      <?php if(empty($di['system'])): ?><span class="del-x" onclick="dictDelItem(event,'<?=$escKey?>','<?=$escCode?>')" title="删除">&times;</span><?php endif; ?>
      <span class="dict-toggle" onclick="dictToggleItem(event,'<?=$escKey?>','<?=$escCode?>')" title="点击停用（从下拉隐藏，历史数据不受影响）">停用</span>
    </span>
  <?php endforeach; ?>
  </div>
  <?php if(empty($items)): ?><span class="text-muted small">暂无选项</span><?php endif; ?>
  <button class="btn btn-outline-primary btn-sm ms-1 dict-add-btn" onclick="dictShowForm(event,'<?=$escKey?>')" title="添加选项" aria-label="添加选项"><i class="bi bi-plus-lg"></i></button>
  <div class="dict-off-zone" id="dictOffZone_<?=$escKey?>" <?=empty($offItems)?'style="display:none"':''?>>
    <span class="dict-off-toggle" id="dictOffToggle_<?=$escKey?>" onclick="toggleOffZone('<?=$escKey?>')"><i class="bi bi-chevron-right"></i> 已停用 <b id="dictOffCount_<?=$escKey?>"><?=count($offItems)?></b> 项</span>
    <div class="dict-off-items" id="dictOffItems_<?=$escKey?>">
    <?php foreach($offItems as $code=>$label):
      $escCode = addslashes(htmlspecialchars($code));
    ?>
      <span class="dict-item dict-item-off" onclick="dictEdit(this,'<?=$escKey?>','<?=$escCode?>','<?=addslashes(htmlspecialchars($label))?>')" title="点击修改">
        <?=htmlspecialchars($label)?>
        <?php if(empty($di['system'])): ?><span class="del-x" onclick="dictDelItem(event,'<?=$escKey?>','<?=$escCode?>')" title="删除">&times;</span><?php endif; ?>
        <span class="dict-toggle" onclick="dictToggleItem(event,'<?=$escKey?>','<?=$escCode?>')" title="点击启用（恢复选项）">启用</span>
      </span>
    <?php endforeach; ?>
    </div>
  </div>
  </div>
</div></div>
<?php endforeach; else: ?>
<div class="text-center py-5 text-muted">暂无字典配置</div><?php endif; ?>

<script>
//=== 字典折叠面板（默认收起，标题行点击展开）===
function setDictCollapsed(row, collapsed) {
    var body = row.querySelector('.card-body');
    var header = row.querySelector('.dict-header');
    body.style.display = collapsed ? 'none' : '';
    header.classList.toggle('active', !collapsed);
}
function toggleDict(header) {
    setDictCollapsed(header.closest('.dict-row'), header.closest('.dict-row').querySelector('.card-body').style.display !== 'none');
}
function dictExpandAll() {
    document.querySelectorAll('.dict-row').forEach(function (r) { setDictCollapsed(r, false); });
}
function dictCollapseAll() {
    document.querySelectorAll('.dict-row').forEach(function (r) { setDictCollapsed(r, true); });
}

//=== 字典行内编辑 ===
function dictShowForm(e, key) {
    e.target.closest('.btn').style.display = 'none';
    var items = e.target.closest('.card-body').querySelector('.flex-grow-1');
    var form = document.createElement('span');
    form.className = 'dict-inline-form';
    form.innerHTML = '<input placeholder="中文名" size="10"> <button class="btn btn-primary btn-sm" style="padding:1px 6px;font-size:12px"><i class="bi bi-check-lg"></i></button> <button class="btn btn-outline-secondary btn-sm" style="padding:1px 6px;font-size:12px"><i class="bi bi-x-lg"></i></button>';
    // v2.40.7：插入到添加按钮前（不再依赖 lastElementChild——停用区已追加到 flex-grow-1 末尾）
    items.insertBefore(form, items.querySelector('.dict-add-btn'));
    form.querySelector('button.btn-primary').onclick = function(){
        var iv = form.querySelector('input').value.trim();
        if(!iv){showToast('请填写名称','error');return;}
        $ajax('/ajax/admin/config/save',{method:'POST',body:new URLSearchParams({key:key,value:'__UPDATE_ITEM__',item_value:iv})}).then(function(res){showToast(res.msg||'已保存',res.code===0?'success':'error');if(res.code===0)location.reload();}).catch(function(){});
    };
    form.querySelector('button.btn-outline-secondary').onclick = function(){
        form.remove();
        items.querySelector('.dict-add-btn').style.display = '';
    };
    form.querySelector('input').focus();
}

function dictEdit(el, key, oldCode, labelStr) {
    var items = el.parentElement;
    var form = document.createElement('span');
    form.className = 'dict-inline-form';
    form.innerHTML = '<input placeholder="中文名" size="10" value="' + esc(labelStr) + '"> <button class="btn btn-primary btn-sm" style="padding:1px 6px;font-size:12px"><i class="bi bi-check-lg"></i></button> <button class="btn btn-outline-secondary btn-sm" style="padding:1px 6px;font-size:12px"><i class="bi bi-x-lg"></i></button>';
    el.style.display = 'none';
    el.insertAdjacentElement('afterend', form);
    form.querySelector('button.btn-primary').onclick = function(){
        var iv = form.querySelector('input').value.trim();
        if(!iv){showToast('请填写名称','error');return;}
        $ajax('/ajax/admin/config/save',{method:'POST',body:new URLSearchParams({key:key,value:'__UPDATE_ITEM__',item_value:iv,old_key:oldCode})}).then(function(res){showToast(res.msg||'已保存',res.code===0?'success':'error');if(res.code===0)location.reload();}).catch(function(){});
    };
    form.querySelector('button.btn-outline-secondary').onclick = function(){
        form.remove();
        el.style.display = '';
    };
    form.querySelector('input').focus();
}

function dictDelItem(e, key, itemKey) {
    e.stopPropagation();
    pcConfirm({message:'确定移除此选项？'}).then(function(ok){
        if(!ok) return;
        $ajax('/ajax/admin/config/save',{method:'POST',body:new URLSearchParams({key:key,value:'__DELETE_ITEM__',item_key:itemKey})}).then(function(res){showToast(res.msg||'已删除',res.code===0?'success':'error');if(res.code===0)location.reload();}).catch(function(){});
    });
}

// v2.40.7：字典项停用/启用切换——停用仅从「新建/编辑选项下拉」隐藏（dict_options/dict_enabled），
// 浏览/筛选/统计与历史 label 解析（dict() 全量）不受影响；系统枚举字典项不可删除，用停用替代删除。
// 成功后局部 DOM 更新（停用项移入「已停用 N 项」折叠区），不整页刷新、不折叠当前字典。
function dictToggleItem(e, key, itemKey) {
    e.stopPropagation();
    var span = e.target.closest('.dict-item');
    $ajax('/ajax/admin/config/save',{method:'POST',body:new URLSearchParams({key:key,value:'__TOGGLE_ITEM__',item_key:itemKey})}).then(function(res){
        showToast(res.msg||'已更新',res.code===0?'success':'error');
        if(res.code===0 && span){
            var wasOff = span.classList.contains('dict-item-off');
            var activeZone = document.getElementById('dictActive_'+key);
            var offItems = document.getElementById('dictOffItems_'+key);
            var offZone = document.getElementById('dictOffZone_'+key);
            var offCount = document.getElementById('dictOffCount_'+key);
            if(wasOff){
                // 启用：移回启用区末尾，更新计数，无停用项则隐藏停用区
                span.classList.remove('dict-item-off');
                setDictToggleLabel(span, '停用', '点击停用（从下拉隐藏，历史数据不受影响）');
                activeZone.appendChild(span);
                var n = parseInt(offCount.textContent || '0', 10) - 1;
                offCount.textContent = Math.max(0, n);
                if(n <= 0) offZone.style.display = 'none';
            } else {
                // 停用：移入停用折叠区，展示停用区并刷新计数
                span.classList.add('dict-item-off');
                setDictToggleLabel(span, '启用', '点击启用（恢复选项）');
                offItems.appendChild(span);
                var n2 = parseInt(offCount.textContent || '0', 10) + 1;
                offCount.textContent = n2;
                offZone.style.display = '';
            }
        }
    }).catch(function(){});
}

function setDictToggleLabel(span, txt, tip) {
    var t = span.querySelector('.dict-toggle');
    if(t){ t.textContent = txt; t.title = tip; }
}

// v2.47.2：字典项拖动排序——HTML5 原生 DnD，事件委托到启用区容器，
// 拖动后收集 data-code 顺序提交 __REORDER_ITEMS__ 保存（后端重排 config_value 键顺序）。
function initDictDrag(zone) {
    zone.addEventListener('dragstart', function(e){
        var item = e.target.closest('.dict-item[data-code]');
        if(!item) return;
        zone._dragEl = item;
        item.classList.add('dict-dragging');
        e.dataTransfer.effectAllowed = 'move';
        try{ e.dataTransfer.setData('text/plain', item.getAttribute('data-code')); }catch(_){}
    });
    zone.addEventListener('dragend', function(){
        zone._dragEl = null;
        zone.querySelectorAll('.dict-item').forEach(function(x){ x.classList.remove('dict-dragging','dict-drag-over'); });
    });
    zone.addEventListener('dragover', function(e){
        var item = e.target.closest('.dict-item[data-code]');
        if(!item || !zone._dragEl || item === zone._dragEl) return;
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        zone.querySelectorAll('.dict-item').forEach(function(x){ x.classList.remove('dict-drag-over'); });
        item.classList.add('dict-drag-over');
    });
    zone.addEventListener('drop', function(e){
        var item = e.target.closest('.dict-item[data-code]');
        if(!item || !zone._dragEl || item === zone._dragEl) return;
        e.preventDefault();
        e.stopPropagation();
        zone.querySelectorAll('.dict-item').forEach(function(x){ x.classList.remove('dict-drag-over'); });
        // 落到目标下半部则插入其后，否则插入其前（按 DOM 顺序重排）
        var rect = item.getBoundingClientRect();
        var after = (e.clientY - rect.top) > rect.height / 2;
        if(after) item.after(zone._dragEl); else item.before(zone._dragEl);
        saveDictOrder(zone);
    });
}
function saveDictOrder(zone) {
    var key = zone.id.replace('dictActive_','');
    var codes = [];
    zone.querySelectorAll('.dict-item[data-code]').forEach(function(x){ codes.push(x.getAttribute('data-code')); });
    if(!codes.length){ return; }
    $ajax('/ajax/admin/config/save',{method:'POST',body:new URLSearchParams({key:key,value:'__REORDER_ITEMS__',item_key:codes.join(',')})}).then(function(res){
        showToast(res.msg||'排序已保存',res.code===0?'success':'error');
    }).catch(function(){});
}
document.querySelectorAll('.dict-items-active').forEach(initDictDrag);

// v2.40.7：展开/收起「已停用 N 项」折叠区（.dict-off-items 由 CSS 类初始隐藏，须用 getComputedStyle 判真实可见性 + 内联 block 覆盖）
function toggleOffZone(key) {
    var t = document.getElementById('dictOffToggle_'+key);
    var items = document.getElementById('dictOffItems_'+key);
    if(!t || !items) return;
    var open = getComputedStyle(items).display !== 'none';
    items.style.display = open ? 'none' : 'block';
    t.classList.toggle('open', !open);
}


</script>

<?php elseif($tab=='config'): ?>
<!-- 系统配置 — 版权信息等基础设置（v2.34.0） -->
<div class="card">
  <div class="card-header bg-light"><i class="bi bi-sliders"></i> 基础设置</div>
  <div class="card-body">
    <div class="mb-3">
      <label class="form-label" for="copyrightInput">版权信息</label>
      <textarea class="form-control" id="copyrightInput" rows="2" placeholder="© 2026 合同管理系统 版权所有"><?=htmlspecialchars($site_copyright ?? '', ENT_QUOTES)?></textarea>
      <small class="text-muted">将展示在 PC 端与移动端页面底部。支持纯文本，保存后即时生效。</small>
    </div>
    <div class="mb-3">
      <label class="form-label" for="fVersion">当前版本</label>
      <input class="form-control" id="fVersion" value="<?=htmlspecialchars(app_version(), ENT_QUOTES)?>" readonly>
    </div>
    <button class="btn btn-primary" onclick="saveSysConfig()"><i class="bi bi-save"></i> 保存</button>
  </div>
</div>

<!-- 业务规则（到期提醒等定时任务参数，存 system_config group=rule） -->
<div class="card mt-3">
  <div class="card-header bg-light"><i class="bi bi-gear"></i> 业务规则</div>
  <div class="card-body">
    <div class="row g-3">
      <div class="col-md-4">
        <label class="form-label" for="ruleExpireDays">合同到期提醒（提前天数）</label>
        <input type="text" class="form-control" id="ruleExpireDays" value="<?=htmlspecialchars(sys_config('rule_expire_remind_days', '30,15,7,3,1'), ENT_QUOTES)?>" placeholder="30,15,7,3,1">
        <small class="text-muted">逗号分隔的倒计时天数，按序触发站内提醒。定时任务 <code>remind:check</code> / <code>remind:dispatch</code> 读取。</small>
      </div>
      <div class="col-md-4">
        <label class="form-label" for="rulePaymentDays">回款到期提醒（提前天数）</label>
        <input type="text" class="form-control" id="rulePaymentDays" value="<?=htmlspecialchars(sys_config('rule_payment_remind_days', '7,3,1'), ENT_QUOTES)?>" placeholder="7,3,1">
        <small class="text-muted">逗号分隔的倒计时天数，按序触发回款到期提醒。</small>
      </div>
    </div>
    <div class="row g-3 mt-1">
      <div class="col-md-4">
        <div class="form-check form-switch pt-2">
          <input class="form-check-input" type="checkbox" id="ruleWeeklyDd" <?=sys_config('weekly_report_dd_enabled', '1') === '1' ? 'checked' : ''?>>
          <label class="form-check-label" for="ruleWeeklyDd">经营周报钉钉推送</label>
        </div>
        <small class="text-muted">每周一 <code>report:weekly</code> 生成周报：开启时推送钉钉工作通知（精简摘要）+ 站内信；关闭时仅站内信与周报页面（<code>/report/weekly</code>）。</small>
      </div>
    </div>
    <div class="mt-3 d-flex gap-2 align-items-center">
      <button class="btn btn-primary" onclick="saveRuleConfig()"><i class="bi bi-save"></i> 保存规则</button>
      <span class="text-muted small">保存后即时生效（清除配置短缓存），无需重启。</span>
    </div>
  </div>
</div>

<!-- 系统配置备份 / 恢复（v2.36.0）：导出不含 user 表；恢复为事务内整簇覆盖并保留原 id -->
<div class="card mt-3">
  <div class="card-header bg-light"><i class="bi bi-arrow-counterclockwise"></i> 系统配置备份 / 恢复</div>
  <div class="card-body">
    <p class="text-muted small mb-3">
      将「角色 / 权限 / 部门 / 本公司主体 / 审批流程 / 资料库 / 系统配置 / 字典设置 / 钉钉配置」等配置整体导出为 JSON 快照；
      需要时可原样恢复（覆盖上述表的全部行并保留原 id）。<b>不含用户账号（user 表）</b>，避免密码出域。
      钉钉配置（.env 中 DINGTALK_*）随备份导出、恢复时写回 .env。恢复会覆盖当前配置，建议在恢复前先手动备份数据库。
    </p>
    <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
      <button class="btn btn-outline-primary" onclick="backupExport()"><i class="bi bi-download"></i> 导出配置（JSON）</button>
      <input type="file" id="backupFile" accept=".json,application/json" class="form-control form-control-sm" style="max-width:320px">
      <button class="btn btn-outline-secondary" onclick="previewRestore()"><i class="bi bi-eye"></i> 解析预览</button>
    </div>
    <!-- v2.45.1：导出/恢复可自选表（默认全选，中文名与各表注释一致） -->
    <details class="mb-2 small" id="cfgExportPickBox">
      <summary class="text-primary user-select-none">选择导出表（默认全选）</summary>
      <div class="border rounded p-2 mt-1 bg-light">
        <label class="form-check form-check-inline mb-1">
          <input class="form-check-input" type="checkbox" id="cfgPickAll" checked onchange="cfgPickAllToggle(this.checked)"> 全选
        </label>
        <div id="cfgPickList" class="d-flex flex-wrap gap-2"></div>
      </div>
    </details>
    <div id="restorePreview"></div>
  </div>
</div>

<script>
// 保存系统配置（版权信息）— 复用 /ajax/admin/config/save 普通配置保存分支（v2.34.0）
function saveSysConfig() {
    var copyright = document.getElementById('copyrightInput').value;
    $ajax('/ajax/admin/config/save', {method: 'POST', body: new URLSearchParams({key: 'copyright', value: copyright})}).then(function (r) {
        if (r.code === 0) { showToast('已保存', 'success'); location.reload(); }
        else { showToast(r.msg || '保存失败', 'error'); }
    }).catch(function () { showToast('保存失败', 'error'); });
}
// 保存业务规则（合同到期提醒/回款提醒提前天数，复用 /ajax/admin/config/save 普通分支）
function saveRuleConfig() {
    var exp = document.getElementById('ruleExpireDays').value.trim();
    var pay = document.getElementById('rulePaymentDays').value.trim();
    function numList(v) {
        var a = v.split(',').map(function (x) { return x.trim(); }).filter(Boolean);
        if (!a.length || a.some(function (x) { return !/^\d+$/.test(x); })) return null;
        return a.join(',');
    }
    var expL = numList(exp), payL = numList(pay);
    if (!expL) { showToast('合同到期提醒天数格式错误（如 30,15,7,3,1）', 'error'); return; }
    if (!payL) { showToast('回款提醒天数格式错误（如 7,3,1）', 'error'); return; }
    // v2.47.0：经营周报钉钉推送开关
    var weeklyDdEl = document.getElementById('ruleWeeklyDd');
    var weeklyDd = weeklyDdEl ? (weeklyDdEl.checked ? '1' : '0') : '1';
    function post(key, value) {
        return $ajax('/ajax/admin/config/save', {method: 'POST', body: new URLSearchParams({key: key, value: value})});
    }
    Promise.all([post('rule_expire_remind_days', expL), post('rule_payment_remind_days', payL), post('weekly_report_dd_enabled', weeklyDd)]).then(function (rs) {
        var bad = rs.find(function (r) { return r.code !== 0; });
        if (!bad) { showToast('规则已保存', 'success'); location.reload(); }
        else { showToast(bad.msg || '保存失败', 'error'); }
    }).catch(function () { showToast('保存失败', 'error'); });
}

// ===== 系统配置备份 / 恢复（v2.36.0；v2.45.1 起支持自选表导出/恢复）=====
// 表名 ↔ 中文名内置映射（与后端 AdminLogic::CONFIG_TABLE_LABELS 一致，顺序即依赖顺序）
var _CFG_TABLES = <?php echo json_encode(\app\common\logic\AdminLogic::CONFIG_TABLE_LABELS, JSON_UNESCAPED_UNICODE); ?>;
var _cfgPickTabs = {};     // 导出勾选状态（默认全选）
var _cfgRestorePick = {};  // 恢复勾选状态（默认全选）
(function () {
  var keys = Object.keys(_CFG_TABLES);
  keys.forEach(function (t) { _cfgPickTabs[t] = true; });
  var box = document.getElementById('cfgPickList');
  var html = '';
  keys.forEach(function (t) {
    html += '<label class="form-check form-check-inline mb-0">'
      + '<input class="form-check-input" type="checkbox" value="' + t + '" checked onchange="cfgPickTab(this)"> '
      + '<span class="form-check-label">' + esc(_CFG_TABLES[t]) + '</span>'
      + ' <span class="text-muted small">(' + t + ')</span></label>';
  });
  box.innerHTML = html;
})();
function cfgPickTab(el) { _cfgPickTabs[el.value] = el.checked; }
function cfgPickAllToggle(checked) {
  document.querySelectorAll('#cfgPickList input[type=checkbox]').forEach(function (el) {
    el.checked = checked; _cfgPickTabs[el.value] = checked;
  });
}
function getPickedTabs(pickObj) {
  return Object.keys(pickObj).filter(function (k) { return pickObj[k]; });
}

// 导出：带勾选表导航到下载接口（会话 cookie 随行，触发浏览器下载）；P1-5：防重复连点 + 进度提示
function backupExport() {
    if(window.__backupExporting){ showToast('导出生成中，请稍候…', 'warning'); return; }
    var tabs = getPickedTabs(_cfgPickTabs);
    if (!tabs.length) { showToast('请至少勾选一个要导出的表', 'error'); return; }
    window.__backupExporting = true;
    showToast('正在生成配置文件…', 'info');
    setTimeout(function(){ window.__backupExporting = false; }, 5000);
    var sp = tabs.map(function (t) { return 'tables[]=' + encodeURIComponent(t); }).join('&');
    window.location.href = '/ajax/admin/config/backup?' + sp;
}

// 预览：上传文件 → 解析返回各表行数 + 风险告警（不改库）
function previewRestore() {
    var f = document.getElementById('backupFile');
    if (!f.files || !f.files.length) { showToast('请先选择配置文件', 'error'); return; }
    var fd = new FormData();
    fd.append('backup_file', f.files[0]);
    fd.append('mode', 'preview');
    $ajax('/ajax/admin/config/restore', {method: 'POST', body: fd}).then(function (res) {
        if (res.code !== 0) { showToast(res.msg || '解析失败', 'error'); return; }
        renderRestorePreview(res.data);
    }).catch(function () { showToast('解析失败', 'error'); });
}

// 渲染预览结果 + 恢复表勾选（默认全选）+ 确认恢复按钮
function renderRestorePreview(d) {
    var meta = d.meta || {};
    _cfgRestorePick = {};
    var html = '';
    html += '<div class="alert alert-secondary small mb-2">导出时间：' + esc(meta.exported_at || '未知')
        + ' ｜ 应用版本：' + esc(meta.app_version || '未知') + ' ｜ 数据库：' + esc(meta.db_type || '未知') + '</div>';
    html += '<table class="table table-sm table-bordered mb-2"><thead><tr>'
        + '<th style="width:36px"><input class="form-check-input" type="checkbox" id="cfgRestoreAll" checked onchange="cfgRestoreAllToggle(this.checked)"></th>'
        + '<th>表</th><th>行数</th></tr></thead><tbody>';
    for (var t in d.tables) {
        _cfgRestorePick[t] = true;
        var lb = d.tables[t].label || t;
        html += '<tr><td><input class="form-check-input" type="checkbox" value="' + t + '" checked onchange="cfgRestorePickTab(this)"></td>'
            + '<td>' + esc(lb) + ' <span class="text-muted small">(' + t + ')</span></td>'
            + '<td>' + esc(String(d.tables[t].rows)) + '</td></tr>';
    }
    html += '</tbody></table>';
    if (d.warnings && d.warnings.length) {
        html += '<div class="alert alert-warning small"><b>风险提示：</b><ul class="mb-0">';
        d.warnings.forEach(function (w) { html += '<li>' + esc(w) + '</li>'; });
        html += '</ul></div>';
    }
    html += '<div class="form-check mb-2">'
        + '<input class="form-check-input" type="checkbox" id="confirmRestoreChk" '
        + 'onchange="document.getElementById(\'btnCommitRestore\').disabled = !this.checked">'
        + '<label class="form-check-label" for="confirmRestoreChk">我已确认将覆盖上方勾选表的全部配置（未勾选表保持现状，建议先手动备份）</label></div>';
    html += '<button class="btn btn-danger" id="btnCommitRestore" disabled onclick="confirmRestore()">'
        + '<i class="bi bi-arrow-counterclockwise"></i> 确认恢复</button>';
    document.getElementById('restorePreview').innerHTML = html;
}
function cfgRestorePickTab(el) { _cfgRestorePick[el.value] = el.checked; }
function cfgRestoreAllToggle(checked) {
    document.querySelectorAll('#restorePreview tbody input[type=checkbox]').forEach(function (el) {
        el.checked = checked; _cfgRestorePick[el.value] = checked;
    });
}

// 提交恢复：上传同一文件 + 勾选表 → 事务内覆盖勾选表（未勾选保持现状）
function confirmRestore() {
    var f = document.getElementById('backupFile');
    if (!f.files || !f.files.length) { showToast('请先选择配置文件', 'error'); return; }
    var chk = document.getElementById('confirmRestoreChk');
    if (!chk || !chk.checked) { showToast('请先勾选确认', 'error'); return; }
    var tabs = getPickedTabs(_cfgRestorePick);
    if (!tabs.length) { showToast('请至少勾选一个要恢复的表', 'error'); return; }
    var fd = new FormData();
    fd.append('backup_file', f.files[0]);
    fd.append('mode', 'commit');
    tabs.forEach(function (t) { fd.append('tables[]', t); });
    $ajax('/ajax/admin/config/restore', {method: 'POST', body: fd, loadingText: '恢复中…'}).then(function (res) {
        if (res.code === 0) { showToast(res.msg || '已恢复', 'success'); setTimeout(function () { location.reload(); }, 900); }
        else { showToast(res.msg || '恢复失败', 'error'); }
    }).catch(function () { showToast('恢复失败', 'error'); });
}
</script>

<?php elseif($tab=='invoice-form'): ?>
<!-- F6：发票申请表单设计器（钉钉表单式：启停/排序/新增自定义字段，保存即双端申请表单生效） -->
<div class="card">
  <div class="card-header bg-light d-flex justify-content-between align-items-center">
    <span><i class="bi bi-ui-checks"></i> 发票申请表单字段</span>
    <button class="btn btn-primary btn-sm" onclick="invFormAddField()"><i class="bi bi-plus-lg"></i> 新增自定义字段</button>
  </div>
  <div class="card-body">
    <div class="text-muted small mb-3">配置发票申请弹窗的字段组合（开票主体/开票内容/金额等为系统预置字段，仅可停用与排序，不可删除）。修改保存后，PC 与移动端申请表单即时生效。</div>
    <div class="table-responsive"><table class="table table-hover align-middle">
      <thead class="table-light"><tr><th style="width:60px">排序</th><th style="width:60px">启用</th><th>字段标签</th><th style="width:140px">类型</th><th style="width:60px">必填</th><th style="width:110px">属性</th><th style="width:90px">操作</th></tr></thead>
      <tbody id="invFormTb">
      <?php foreach($invoice_form_fields as $__f): ?>
        <tr data-id="<?=$__f['id']?>" data-key="<?=htmlspecialchars($__f['field_key'])?>" data-system="<?=(int)($__f['is_system']??0)?>" data-opts="<?=htmlspecialchars($__f['field_options'] ?? '[]')?>">
          <td>
            <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-1" onclick="invFormMove(this,-1)" title="上移" aria-label="上移"><i class="bi bi-arrow-up"></i></button>
            <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-1" onclick="invFormMove(this,1)" title="下移" aria-label="下移"><i class="bi bi-arrow-down"></i></button>
          </td>
          <td><input type="checkbox" class="form-check-input invFormEnabled" <?=($__f['enabled']??1)?'checked':''?>></td>
          <td><input type="text" class="form-control form-control-sm invFormLabel" value="<?=htmlspecialchars($__f['field_label'])?>"></td>
          <td>
            <select class="form-select form-select-sm invFormType">
              <?php foreach($invoice_field_types as $__t=>$__tn): ?><option value="<?=$__t?>" <?=($__f['field_type']??'')==$__t?'selected':''?>><?=$__tn?></option><?php endforeach; ?>
            </select>
          </td>
          <td class="text-center"><input type="checkbox" class="form-check-input invFormRequired" <?=($__f['required']??0)?'checked':''?>></td>
          <td><span class="badge <?=($__f['is_system']??0)?'bg-info':'bg-secondary'?>"><?=($__f['is_system']??0)?'系统':'自定义'?></span>
              <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-1" onclick="invFormEditOptions(this)" title="编辑选项（下拉选择用）" aria-label="编辑选项"><i class="bi bi-list-ul"></i></button></td>
          <td>
            <?php if(empty($__f['is_system'])): ?><button type="button" class="btn btn-sm btn-outline-danger" aria-label="删除" onclick="invFormDel(this)"><i class="bi bi-trash"></i></button><?php else: ?><span class="text-muted small">—</span><?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <div class="mt-3">
      <button class="btn btn-primary" onclick="invFormSave()"><i class="bi bi-save"></i> 保存字段配置</button>
      <button class="btn btn-outline-secondary ms-1" onclick="location.reload()">取消</button>
    </div>
  </div>
</div>

<!-- F9：字段联动规则（通用组件 form-linkage.js 消费：触发字段值变化 → 目标字段显隐/替换选项） -->
<div class="card mt-3">
  <div class="card-header bg-light d-flex justify-content-between align-items-center">
    <span><i class="bi bi-link-45deg"></i> 字段联动规则</span>
    <button class="btn btn-primary btn-sm" onclick="invLinkOpen()"><i class="bi bi-plus-lg"></i> 新增联动</button>
  </div>
  <div class="card-body">
    <div class="text-muted small mb-2">配置字段间联动：触发字段等于指定值时，目标字段显示/隐藏或替换选项（如：选择开票主体 → 联动开票内容选项）。点击「保存字段配置」一并生效。</div>
    <div class="table-responsive"><table class="table table-sm align-middle">
      <thead class="table-light"><tr><th>触发字段</th><th>触发值</th><th>动作</th><th>目标字段</th><th>选项预览</th><th style="width:110px">操作</th></tr></thead>
      <tbody id="invLinkTb"></tbody>
    </table></div>
    <div class="text-muted small" id="invLinkEmpty">暂无联动规则，点击「新增联动」配置</div>
  </div>
</div>

<!-- F9：联动规则配置弹窗 -->
<div class="modal fade" id="invLinkModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
<div class="modal-header"><h6 class="modal-title">新增联动规则</h6><button class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
  <div class="mb-2"><label class="form-label small" for="invLinkTrigger">触发字段（变化时生效）</label><select id="invLinkTrigger" class="form-select form-select-sm"></select></div>
  <div class="mb-2"><label class="form-label small" for="invLinkValue">触发值（等于该值时生效）</label><input type="text" id="invLinkValue" class="form-control form-control-sm" placeholder="如公司主体 id 或选项值"></div>
  <div class="mb-2"><label class="form-label small" for="invLinkAction">联动动作</label>
    <select id="invLinkAction" class="form-select form-select-sm" onchange="invLinkActionChange()">
      <option value="options">替换目标字段选项</option>
      <option value="show">显示目标字段</option>
      <option value="hide">隐藏目标字段</option>
    </select></div>
  <div class="mb-2"><label class="form-label small" for="invLinkTarget">目标字段</label><select id="invLinkTarget" class="form-select form-select-sm"></select></div>
  <div class="mb-2" id="invLinkOptWrap"><label class="form-label small" for="invLinkOpts">联动选项（每行：值=显示名）</label>
    <textarea id="invLinkOpts" class="form-control form-control-sm" rows="4" placeholder="如：SOFTWARE=软件开发服务费&#10;CONSULT=咨询服务费"></textarea></div>
  <div class="text-danger small" id="invLinkErr"></div>
</div>
<div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">取消</button><button class="btn btn-primary" onclick="invLinkSave()">添加规则</button></div>
</div></div></div>
<script>
// ===== 发票表单设计器（F6）=====
var invFormSeq = 0;
function invFormMove(btn, dir){
  var tr = btn.closest('tr'), tb = tr.parentNode, rows = Array.from(tb.rows), idx = rows.indexOf(tr), nidx = idx + dir;
  if(nidx < 0 || nidx >= rows.length) return;
  tb.insertBefore(tr, nidx > idx ? rows[nidx].nextSibling : rows[nidx]);
}
function invFormAddField(){
  pcPrompt({title:'新增字段', placeholder:'字段标签', okText:'添加'}).then(function(label){
  if(label === null || !label.trim()) return;
  var tb = document.getElementById('invFormTb');
  var tr = document.createElement('tr');
  tr.dataset.id = '0'; tr.dataset.key = 'new_' + (++invFormSeq); tr.dataset.system = '0'; tr.dataset.opts = '[]';
  tr.innerHTML =
    '<td><button type="button" class="btn btn-sm btn-outline-secondary py-0 px-1" aria-label="上移" onclick="invFormMove(this,-1)"><i class="bi bi-arrow-up"></i></button>' +
    '<button type="button" class="btn btn-sm btn-outline-secondary py-0 px-1" aria-label="下移" onclick="invFormMove(this,1)"><i class="bi bi-arrow-down"></i></button></td>' +
    '<td><input type="checkbox" class="form-check-input invFormEnabled" checked></td>' +
    '<td><input type="text" class="form-control form-control-sm invFormLabel" value="' + esc(label.trim()) + '"></td>' +
    '<td><select class="form-select form-select-sm invFormType"><?php foreach($invoice_field_types as $__t=>$__tn): ?><option value="<?=$__t?>"><?=$__tn?></option><?php endforeach; ?></select></td>' +
    '<td class="text-center"><input type="checkbox" class="form-check-input invFormRequired"></td>' +
    '<td><span class="pc-tag pc-tag-muted">自定义</span><button type="button" class="btn btn-sm btn-outline-secondary py-0 px-1" onclick="invFormEditOptions(this)" title="编辑选项" aria-label="编辑选项"><i class="bi bi-list-ul"></i></button></td>' +
    '<td><button type="button" class="btn btn-sm btn-outline-danger" aria-label="删除" onclick="invFormDel(this)"><i class="bi bi-trash"></i></button></td>';
  tb.appendChild(tr);
  });
}
function invFormEditOptions(btn){
  var tr = btn.closest('tr'), type = tr.querySelector('.invFormType').value;
  if(type !== 'select'){ showToast('仅「下拉选择」需要配置选项','error'); return; }
  var opts = [];
  try{ opts = JSON.parse(tr.dataset.opts || '[]'); }catch(e){}
  var txt = opts.map(function(o){ return (o.value||'') + '=' + (o.label||o.value||''); }).join('\n');
  pcPrompt({title:'编辑选项', placeholder:'每行一个选项，格式：值=显示名', value:txt}).then(function(input){
  if(input === null) return;
  var lines = input.split('\n').map(function(l){ return l.trim(); }).filter(Boolean);
  var out = lines.map(function(l){
    var i = l.indexOf('=');
    if(i > 0) return {value: l.slice(0,i).trim(), label: l.slice(i+1).trim()};
    return {value: l, label: l};
  });
  tr.dataset.opts = JSON.stringify(out);
  showToast('选项已更新（' + out.length + ' 项）', 'success');
  });
}
function invFormDel(btn){
  var tr = btn.closest('tr');
  if(tr.dataset.system === '1'){ showToast('系统预置字段不可删除','error'); return; }
  pcConfirm({message:'确认删除该自定义字段？', danger:true}).then(function(ok){ if(!ok) return;
  tr.remove(); });
}
function invFormSave(){
  var rows = [], tb = document.getElementById('invFormTb');
  Array.from(tb.rows).forEach(function(tr){
    rows.push({
      id: tr.dataset.id,
      field_label: tr.querySelector('.invFormLabel').value,
      field_type: tr.querySelector('.invFormType').value,
      field_options: tr.dataset.opts || '',
      required: tr.querySelector('.invFormRequired').checked ? 1 : 0,
      enabled: tr.querySelector('.invFormEnabled').checked ? 1 : 0
    });
  });
  // F9：联动规则一并提交（后端按 form_key 全量重存）
  var body = new URLSearchParams({rows: JSON.stringify(rows), linkage: JSON.stringify(window.__invRules || [])});
  $ajax('/ajax/admin/invoice-form/save', {method:'POST', body: body, loading:true, loadingText:'保存中…'}).then(function(res){
    showToast(res.msg || '已保存', res.code === 0 ? 'success' : 'error');
    if(res.code === 0) setTimeout(function(){ location.reload(); }, 900);
  }).catch(function(){});
}

// ===== F9：字段联动规则（通用组件 form-linkage.js 消费）=====
window.__invRules = <?= json_encode($invoice_form_linkage ?? [], JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
var invLinkEditIdx = -1;
function invLinkFields(){ // 当前字段清单（含新增未保存行），供触发/目标下拉
  var out = [];
  document.querySelectorAll('#invFormTb tr').forEach(function(tr){
    var key = tr.dataset.key || '';
    var label = tr.querySelector('.invFormLabel').value;
    if(!key || !label) return;
    out.push({key: key, label: label});
  });
  return out;
}
function invLinkActionName(a){ return a === 'options' ? '替换选项' : (a === 'show' ? '显示' : '隐藏'); }
function invLinkRender(){
  var tb = document.getElementById('invLinkTb'), empty = document.getElementById('invLinkEmpty');
  tb.innerHTML = '';
  var list = window.__invRules || [];
  empty.style.display = list.length ? 'none' : '';
  list.forEach(function(r, i){
    var optsTxt = (r.options && r.options.length) ? r.options.map(function(o){ return o.label || o.value; }).join('、') : (r.action === 'options' ? '（未配置）' : '—');
    tb.insertAdjacentHTML('beforeend',
      '<tr><td>'+esc(r.trigger_field)+'</td><td><code>'+esc(r.trigger_value)+'</code></td>'
      + '<td>'+invLinkActionName(r.action)+'</td><td>'+esc(r.target_field)+'</td>'
      + '<td class="text-muted small" style="max-width:220px">'+esc(optsTxt)+'</td>'
      + '<td><button class="btn btn-sm btn-outline-secondary py-0 px-1" aria-label="编辑" onclick="invLinkEdit('+i+')"><i class="bi bi-pencil"></i></button> '
      + '<button class="btn btn-sm btn-outline-danger py-0 px-1" aria-label="删除" onclick="invLinkDel('+i+')"><i class="bi bi-trash"></i></button></td></tr>');
  });
}
function invLinkOpen(editIdx){
  invLinkEditIdx = (typeof editIdx === 'number') ? editIdx : -1;
  var fields = invLinkFields();
  if(fields.length < 2){ showToast('至少需要两个字段才能配置联动','error'); return; }
  var fill = function(sel, opts, cur){
    var h = '';
    opts.forEach(function(f){ h += '<option value="'+esc(f.key)+'"'+(f.key === cur ? ' selected':'')+'>'+esc(f.label)+'</option>'; });
    sel.innerHTML = h;
  };
  var edit = (invLinkEditIdx >= 0 && window.__invRules[invLinkEditIdx]) ? window.__invRules[invLinkEditIdx] : null;
  fill(document.getElementById('invLinkTrigger'), fields, edit ? edit.trigger_field : '');
  fill(document.getElementById('invLinkTarget'), fields, edit ? edit.target_field : '');
  document.getElementById('invLinkValue').value = edit ? (edit.trigger_value || '') : '';
  document.getElementById('invLinkAction').value = edit ? (edit.action || 'options') : 'options';
  var optsTxt = '';
  if(edit && edit.action === 'options' && edit.options){
    optsTxt = edit.options.map(function(o){ return (o.value||'') + '=' + (o.label||o.value||''); }).join('\n');
  }
  document.getElementById('invLinkOpts').value = optsTxt;
  document.getElementById('invLinkErr').textContent = '';
  invLinkActionChange();
  new bootstrap.Modal('#invLinkModal').show();
}
function invLinkActionChange(){
  document.getElementById('invLinkOptWrap').style.display =
    (document.getElementById('invLinkAction').value === 'options') ? '' : 'none';
}
function invLinkSave(){
  var trigger = document.getElementById('invLinkTrigger').value;
  var target  = document.getElementById('invLinkTarget').value;
  var action  = document.getElementById('invLinkAction').value;
  var err = document.getElementById('invLinkErr');
  if(!trigger || !target){ err.textContent = '请选择触发字段与目标字段'; return; }
  if(trigger === target){ err.textContent = '触发字段与目标字段不能相同'; return; }
  var rule = { trigger_field: trigger, trigger_value: document.getElementById('invLinkValue').value.trim(), target_field: target, action: action, options: [] };
  if(action === 'options'){
    var lines = document.getElementById('invLinkOpts').value.split('\n').map(function(l){ return l.trim(); }).filter(Boolean);
    if(!lines.length){ err.textContent = '「替换选项」动作必须配置至少一个选项'; return; }
    rule.options = lines.map(function(l){
      var i = l.indexOf('=');
      return i > 0 ? {value: l.slice(0,i).trim(), label: l.slice(i+1).trim()} : {value: l, label: l};
    });
  }
  if(invLinkEditIdx >= 0 && window.__invRules[invLinkEditIdx]){ window.__invRules[invLinkEditIdx] = rule; }
  else { window.__invRules.push(rule); }
  bootstrap.Modal.getInstance('#invLinkModal').hide();
  invLinkRender();
  showToast(invLinkEditIdx >= 0 ? '规则已更新' : '规则已添加', 'success');
}
function invLinkEdit(i){ invLinkOpen(i); }
function invLinkDel(i){
  pcConfirm({message:'确认删除该联动规则？', danger:true}).then(function(ok){ if(!ok) return;
  window.__invRules.splice(i, 1);
  invLinkRender(); });
}
invLinkRender();
</script>
<?php endif; ?>

<!-- v2.38.16 离职交接弹窗：选择接收人 + 交接范围（客户/合同/待审批）+ 按需禁用 -->
<div class="modal fade" id="handoverModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
<div class="modal-header bg-warning text-dark"><h5 class="modal-title" id="hoModalTitle"><i class="bi bi-arrow-left-right"></i> 离职交接</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
  <div class="alert alert-warning small mb-3"><i class="bi bi-info-circle"></i> 将把 <strong id="hoFromName"></strong> 名下的数据批量转移给接收人。交接范围可勾选，交接完成后可按需禁用该用户。</div>
<div class="mb-3">
  <label class="form-label d-block" for="hoToUserName">接收人（在职用户）</label>
  <!-- v2.38.16：复用系统统一选人组件（openUserPicker 单选模式，与审批指定用户/抄送同组件） -->
  <div class="input-group">
    <input type="text" class="form-control" id="hoToUserName" placeholder="点击右侧「选择接收人」" readonly style="background:#fff">
    <input type="hidden" id="hoToUserId">
    <button type="button" class="btn btn-outline-primary" id="hoPickBtn"><i class="bi bi-people"></i> 选择接收人</button>
  </div>
  <div class="form-text">支持按部门筛选 / 姓名搜索（与审批选人一致）</div>
</div>
  <div class="mb-3">
    <label class="form-label d-block" for="hoScopeCustomer">交接范围</label>
    <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" id="hoScopeCustomer" checked><label class="form-check-label" for="hoScopeCustomer">客户</label></div>
    <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" id="hoScopeContract" checked><label class="form-check-label" for="hoScopeContract">合同</label></div>
    <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" id="hoScopeApproval" checked><label class="form-check-label" for="hoScopeApproval">待审批</label></div>
  </div>
  <div class="form-check mb-1">
    <input class="form-check-input" type="checkbox" id="hoDisableFrom" checked>
    <label class="form-check-label" for="hoDisableFrom">交接完成后禁用该用户（进入回收站）</label>
  </div>
</div>
<div class="modal-footer">
  <button class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
  <button class="btn btn-warning" id="hoConfirmBtn"><i class="bi bi-arrow-left-right"></i> 确认交接</button>
</div>
</div></div></div>

<!-- 通用选人弹窗（审批「指定用户」与抄送节点复用）：左侧部门树 + 右侧用户分页搜索 + 多选已选区 -->
<div class="modal fade" id="userPickerModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
<div class="modal-header bg-primary text-white"><h5 class="modal-title"><i class="bi bi-people"></i> 选择用户</h5><button class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
<div class="row g-2">
  <div class="col-md-4 border-end" style="max-height:420px;overflow:auto">
    <div class="text-muted small mb-1">部门</div>
    <div id="upDeptTree"></div>
  </div>
  <div class="col-md-8">
    <div class="input-group input-group-sm mb-2">
      <input type="text" class="form-control" id="upKeyword" placeholder="搜索姓名/用户名">
      <button class="btn btn-outline-secondary" id="upSearchBtn" type="button" aria-label="搜索"><i class="bi bi-search"></i></button>
    </div>
    <div id="upUserList" style="max-height:300px;overflow:auto"></div>
    <div class="text-center py-2"><button class="btn btn-sm btn-outline-primary" id="upLoadMore">加载更多</button></div>
    <div class="mt-2 pt-2 border-top"><strong class="small">已选：</strong> <span id="upSelected"></span></div>
  </div>
</div>
</div>
<div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">取消</button><button class="btn btn-primary" id="upConfirmBtn">确定</button></div>
</div></div></div>

<!-- 角色多选弹窗（编辑用户分配多角色，v2.31.0 取代原生 multi-select） -->
<div class="modal fade" id="rolePickerModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
<div class="modal-header bg-primary text-white"><h5 class="modal-title"><i class="bi bi-tags"></i> 选择角色（可多选）</h5><button class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
  <input type="text" class="form-control form-control-sm mb-2" id="rpKeyword" placeholder="搜索角色名称…">
  <div id="rpList" style="max-height:320px;overflow:auto"></div>
</div>
<div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">取消</button><button class="btn btn-primary" data-bs-dismiss="modal">确定</button></div>
</div></div></div>

<script src="/static/js/admin/pickers.js?v=2"></script>

<?php include __DIR__.'/../layout/footer.php'; ?>
