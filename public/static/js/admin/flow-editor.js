// 审批流编辑器（v2.38.22 恢复：左侧图形化画布 + 右侧流程配置侧边栏；「添加审批节点」按钮在画布内）
// 依赖：公共脚本块已声明 allUsers/allRoles/flowCats/esc（window.esc fallback），
//       页脚 app.js 提供 $ajax/showToast，Bootstrap CDN 提供 bootstrap.Modal。
// 仅在 admin?tab=flow 下加载。
// 中文注释：审批节点/抄送面板编辑 → 前端渲染与提交
// === 审批流编辑器 ===
let nodeCounter = 0;  // 仅审批流 tab 使用（allUsers/allRoles/flowCats 已上提到公共脚本块）

function newFlow(){
    document.getElementById('flowForm').reset();
    document.getElementById('flowId').value='';
    document.getElementById('flowCatsVal').value='[]';
    renderFlowCats();
    document.getElementById('flowUseAmount').value='1';
    toggleAmountFields();
    renderFlowCanvasFrame();   // 图形化画布骨架（发起人 → 添加节点 → 抄送 → 结束）
    nodeCounter = 0;
    addNode();
    new bootstrap.Modal('#flowModal').show();
}

/** 渲染画布固定骨架（发起人 → 添加节点按钮 → 抄送节点 → 结束）；审批节点插入抄送之前、添加按钮之后 */
function renderFlowCanvasFrame(){
    let editor = document.getElementById('nodeEditor');
    editor.innerHTML =
        '<div class="flow-cv-starter"><i class="bi bi-person"></i> 发起人</div>'
        + '<div class="flow-cv-gap"></div>'  // v2.38.26：去除发起人与节点链之间的连接线视觉（保留高度作为间隔，避免上下贴得太近）；仅去除连线，不影响其余连接线
        + '<div class="flow-cv-add" id="flowAddNodeBtn">'
        +   '<button type="button" class="btn btn-primary btn-sm" onclick="addNode()"><i class="bi bi-plus-lg"></i> 添加审批节点</button>'
        + '</div>'
        + '<div class="flow-cv-conn"></div>'
        + '<div class="flow-cv-cc" id="flowCcNode">'
        +   '<div class="cc-title"><i class="bi bi-eye"></i> 抄送（知会，不参与审批）</div>'
        +   '<div class="row g-2">'
        +     '<div class="col-md-5"><label class="form-label small" for="ccRoleSel">抄送角色（可多选）</label>'
        +     '<div class="d-flex flex-wrap gap-1 mb-1" id="ccRolesView"><span class="text-muted small">未选择</span></div>'
        +     '<input type="hidden" id="ccRoles" value="[]">'
        +     '<select class="form-select form-select-sm" id="ccRoleSel" onchange="addCcRole(this)"><option value="">选择角色…</option></select>'
        +     '<div class="form-text small">从下拉选择角色，可多次选择添加</div></div>'
        +     '<div class="col-md-7"><label class="form-label small">抄送指定用户</label>'
        +     '<div class="mb-2" id="ccUsersView"><span class="text-muted small">未选择</span></div>'
        +     '<input type="hidden" id="ccUsers" value="[]">'
        +     '<button type="button" class="btn btn-sm btn-outline-primary" onclick="openCcPicker()"><i class="bi bi-person-plus"></i> 选择用户</button></div>'
        +   '</div>'
        + '</div>'
        + '<div class="flow-cv-conn"></div>'
        + '<div class="flow-cv-end"><i class="bi bi-check2-circle"></i> 结束</div>';
    fillCcRoleOptions();
}

function editFlow(f){
    document.getElementById('flowId').value=f.id;
    document.getElementById('flowName').value=f.name;
    document.getElementById('flowCode').value=f.code;
    let cats = [];
    try{ cats = f.category_list ? JSON.parse(f.category_list) : []; }catch(e){ cats = f.category ? [f.category] : []; }
    document.getElementById('flowCatsVal').value = JSON.stringify(cats);
    renderFlowCats();
    document.getElementById('flowUseAmount').value = (f.use_amount!=null && f.use_amount!=='') ? f.use_amount : 1;
    toggleAmountFields();
    document.getElementById('flowMin').value=f.min_amount;
    document.getElementById('flowMax').value=f.max_amount;
    document.getElementById('flowStatus').value=f.status;
    renderFlowCanvasFrame();   // 图形化画布骨架
    let nodes = f._nodes || (typeof f.nodes === 'string' ? JSON.parse(f.nodes) : (f.nodes || []));
    nodeCounter = 0;
    nodes.forEach(function(n){ addNode(n); });
    // 流程级抄送面板（独立填充，不随节点渲染）
    let cc = {}; try{ cc = f.cc_list ? JSON.parse(f.cc_list) : {}; }catch(e){ cc={}; }
    // 质量修复：多角色抄送全量回填（v2.38.25 改为隐藏域 ccRoles + chips 渲染，不再依赖 select selected）
    let ccRoles = (cc.role_codes && cc.role_codes.length) ? cc.role_codes : [];
    let ccUsers = cc.cc_user_ids || [];
    document.getElementById('ccRoles').value = JSON.stringify(ccRoles);
    fillCcRoleOptions();
    document.getElementById('ccUsers').value = JSON.stringify(ccUsers);
    document.getElementById('ccUsersView').innerHTML = ccUsers.length ? ccUsers.map(function(id){return '<span class="badge bg-info me-1 mb-1">'+esc(userNameById(id))+'</span>';}).join('') : '<span class="text-muted small">未选择</span>';
    new bootstrap.Modal('#flowModal').show();
}

// 审批流「适用分类」多选 chips + 「金额条件」开关
function renderFlowCats(){
    let box = document.getElementById('flowCatsBox');
    let sel = [];
    try{ sel = JSON.parse(document.getElementById('flowCatsVal').value||'[]'); }catch(e){ sel=[]; }
    let codes = Object.keys(flowCats);
    if(codes.length===0){ box.innerHTML='<span class="text-muted small">无分类</span>'; return; }
    box.innerHTML = codes.map(function(code){
        let on = sel.includes(code) ? 'btn-primary' : 'btn-outline-secondary';
        return '<button type="button" class="btn btn-sm '+on+'" data-cat="'+code+'" onclick="toggleFlowCat(\''+code+'\')">'+esc(flowCats[code])+'</button>';
    }).join('');
}
function toggleFlowCat(code){
    let sel = [];
    try{ sel = JSON.parse(document.getElementById('flowCatsVal').value||'[]'); }catch(e){ sel=[]; }
    if(sel.includes(code)) sel = sel.filter(function(x){return x!==code;});
    else sel.push(code);
    document.getElementById('flowCatsVal').value = JSON.stringify(sel);
    renderFlowCats();
}
function toggleAmountFields(){
    let on = document.getElementById('flowUseAmount').value === '1';
    document.getElementById('amtMinWrap').style.display = on ? '' : 'none';
    document.getElementById('amtMaxWrap').style.display = on ? '' : 'none';
}

function userNameById(id){
    // 优先用视图注入的用户数组回显姓名；超出 100 条截断范围时回退到弹窗缓存，再退化成“用户#id”
    let found = allUsers.find(function(u){ return u.id===id; });
    if(found) return found.name;
    if(window._up && _up.nameCache && _up.nameCache[id]) return _up.nameCache[id];
    return '用户#'+id;
}
function nodeApproverArea(type, data, i){
    data = data || {};
    if(type === 'SPECIFIC_USER'){
        // 指定用户改为弹出选人窗口（部门树+搜索），不再用平铺下拉
        let sel = data.approvers||[];
        let nameHtml = sel.map(function(id){return '<span class="badge bg-primary me-1 mb-1">'+esc(userNameById(id))+'</span>';}).join('') || '<span class="text-muted small">未选择</span>';
        let mode = '<select class="form-select form-select-sm mt-2" id="mode_'+i+'"><option value="OR" '+(data.mode!=='AND'?'selected':'')+'>或签（任一通过）</option><option value="AND" '+(data.mode==='AND'?'selected':'')+'>会签（全部通过）</option></select>';
        return '<div class="mb-2" id="approversView_'+i+'">'+nameHtml+'</div>'
             + '<input type="hidden" id="approvers_'+i+'" value="'+esc(JSON.stringify(sel))+'">'
             + '<button type="button" class="btn btn-sm btn-outline-primary" onclick="openApproverPicker('+i+')"><i class="bi bi-person-plus"></i> 选择审批人</button>'
             + mode;
    }
    if(type === 'ROLE'){
        let rc = data.role_code || '';
        let opts = allRoles.map(function(r){return '<option value="'+r.code+'" '+(rc==r.code?'selected':'')+'>'+r.name+'</option>';}).join('');
        let mode = '<select class="form-select form-select-sm mt-2" id="mode_'+i+'"><option value="OR" '+(data.mode!=='AND'?'selected':'')+'>或签（任一通过）</option><option value="AND" '+(data.mode==='AND'?'selected':'')+'>会签（全部通过）</option></select>';
        return '<select class="form-select form-select-sm" id="roleCode_'+i+'">'+opts+'</select>'+mode;
    }
    return '<div class="text-muted small">自动匹配提交人部门负责人</div>';
}

// 指定用户节点触发选人弹窗，选中后写回隐藏域并回显姓名
function openApproverPicker(i){
    let cur = [];
    try{ cur = JSON.parse(document.getElementById('approvers_'+i).value||'[]'); }catch(e){ cur = []; }
    openUserPicker({
        multiple: true,
        selected: cur,
        onConfirm: function(ids){
            document.getElementById('approvers_'+i).value = JSON.stringify(ids);
            let box = document.getElementById('approversView_'+i);
            if(ids.length===0){ box.innerHTML='<span class="text-muted small">未选择</span>'; return; }
            box.innerHTML = ids.map(function(id){return '<span class="badge bg-primary me-1 mb-1">'+esc(userNameById(id))+'</span>';}).join('');
        }
    });
}

// 图形化画布内节点卡片
function addNode(data){
    data = data || {name:'', type:'DEPT_LEADER', approvers:[]};
    let i = ++nodeCounter;
    let approverHTML = nodeApproverArea(data.type, data, i);

    let html = '<div class="flow-cv-node" id="cvNode_'+i+'">'
        + '<div class="node-card" id="node_'+i+'">'+
        '<div class="node-head">'+
        '<span class="node-head-left"><span class="node-badge bg-primary">'+i+'</span><span class="fw-bold">审批节点 '+i+'</span></span>'+
        '<div class="node-actions">'+
        '<button type="button" class="btn btn-sm btn-outline-secondary" onclick="moveNode('+i+',-1)" title="上移" aria-label="上移"><i class="bi bi-arrow-up"></i></button>'+
        '<button type="button" class="btn btn-sm btn-outline-secondary" onclick="moveNode('+i+',1)" title="下移" aria-label="下移"><i class="bi bi-arrow-down"></i></button>'+
        '<button type="button" class="btn btn-sm btn-outline-danger" onclick="removeNode('+i+')" title="删除节点" aria-label="删除节点"><i class="bi bi-trash"></i></button>'+
        '</div></div>'+
        '<div class="row g-2">'+
        '<div class="col-md-4"><label class="form-label small" for="nodeName_'+i+'">节点名称</label><input type="text" class="form-control form-control-sm" value="'+esc(data.name)+'" id="nodeName_'+i+'" onchange="updateNodeData()" placeholder="如：部门审批"></div>'+
        '<div class="col-md-3"><label class="form-label small" for="nodeType_'+i+'">节点类型</label><select class="form-select form-select-sm" id="nodeType_'+i+'" onchange="onTypeChange('+i+')"><option value="DEPT_LEADER" '+(data.type=='DEPT_LEADER'?'selected':'')+'>部门负责人</option><option value="SPECIFIC_USER" '+(data.type=='SPECIFIC_USER'?'selected':'')+'>指定用户</option><option value="ROLE" '+(data.type=='ROLE'?'selected':'')+'>角色</option></select></div>'+
        '<div class="col-md-5" id="approverArea_'+i+'">'+approverHTML+'</div></div>'+
        // 节点级金额条件（低于/高于此范围自动跳过该节点）
        '<div class="row g-2 mt-2" id="amountCond_'+i+'">'+
        '<div class="col-6 col-md-3"><label class="form-label small" for="amountMin_'+i+'">最低金额（¥）</label><input type="number" step="0.01" class="form-control form-control-sm" id="amountMin_'+i+'" value="'+(data.amount_min||'')+'" placeholder="不限" onchange="updateNodeData()"></div>'+
        '<div class="col-6 col-md-3"><label class="form-label small" for="amountMax_'+i+'">最高金额（¥）</label><input type="number" step="0.01" class="form-control form-control-sm" id="amountMax_'+i+'" value="'+(data.amount_max||'')+'" placeholder="不限" onchange="updateNodeData()"></div>'+
        '<div class="col-6 col-md-3"><label class="form-label small" for="timeoutH_'+i+'">审批超时（小时）</label><input type="number" step="0.5" min="0" class="form-control form-control-sm" id="timeoutH_'+i+'" value="'+(data.timeout_hours||'')+'" placeholder="不限时" onchange="updateNodeData()"></div>'+
        '</div></div>'
        + '</div>';

    // 插入到「添加审批节点」按钮之前（按钮固定在节点链末尾、抄送之前；新节点逐个插到按钮前，顺序即添加顺序）
    let addBtn = document.getElementById('flowAddNodeBtn');
    let ccNode = document.getElementById('flowCcNode');
    let editor = document.getElementById('nodeEditor');
    let tmp = document.createElement('div');
    tmp.innerHTML = html;
    if (addBtn) {
        editor.insertBefore(tmp.firstChild, addBtn);
    } else if (ccNode) {
        editor.insertBefore(tmp.firstChild, ccNode);
    } else {
        editor.insertAdjacentHTML('beforeend', html);
    }
    renumberNodes();
}

// 删除节点：直接从画布移除（编辑器内的临时操作，保存流程时随 nodes 落库）
function removeNode(i){
    let node = document.getElementById('cvNode_'+i);
    if(node) node.remove();
    renumberNodes();
}

function moveNode(i, dir){
    let editor = document.getElementById('nodeEditor');
    if(!editor) return;
    // 操作图形化节点容器（.flow-cv-node），重挂不丢失内部表单状态
    let cards = Array.from(editor.querySelectorAll('.flow-cv-node'));
    let idx = cards.findIndex(function(c){ return c.id === 'cvNode_'+i; });
    if(idx < 0) return;
    let to = idx + dir;
    if(to < 0 || to >= cards.length){ renumberNodes(); return; } // 已在顶/底：仅恢复编号后返回
    // 交换两个卡片在数组中的顺序
    let tmp = cards[idx]; cards[idx] = cards[to]; cards[to] = tmp;
    // 按新顺序重新挂载到「添加审批节点」按钮之前（按钮固定在节点链末尾、抄送之前）
    let addBtn = document.getElementById('flowAddNodeBtn');
    let anchor = addBtn || document.getElementById('flowCcNode');
    cards.forEach(function(card){ editor.insertBefore(card, anchor); });
    renumberNodes();
}

function renumberNodes(){
    let cards = document.querySelectorAll('#nodeEditor .flow-cv-node');
    cards.forEach(function(card, idx){
        let num = idx + 1;
        let badge = card.querySelector('.node-badge');
        if(badge) badge.textContent = num;
        let nameHead = card.querySelector('.fw-bold');
        if(nameHead) nameHead.textContent = '审批节点 ' + num;
    });
}

function onTypeChange(i){
    let type = document.getElementById('nodeType_'+i).value;
    document.getElementById('approverArea_'+i).innerHTML = nodeApproverArea(type, {approvers:[], role_codes:[], mode:'OR'}, i);
}

// 节点名称/金额/超时输入 onchange 钩子：数据由 getNodesData() 保存时实时读取，无需额外处理
function updateNodeData(){}

function getNodesData(){
    let nodes = [];
    let cards = document.querySelectorAll('#nodeEditor .node-card');
    cards.forEach(function(card, idx){
        let id = card.id.replace('node_','');
        let type = document.getElementById('nodeType_'+id).value;
        let node = {order: idx+1, name: document.getElementById('nodeName_'+id).value, type: type};
        // 节点级金额条件（v2.38.25：激活条件已移除，不再读取 activate_when）
        let minEl = document.getElementById('amountMin_'+id); if(minEl && minEl.value !== '') node.amount_min = parseFloat(minEl.value);
        let maxEl = document.getElementById('amountMax_'+id); if(maxEl && maxEl.value !== '') node.amount_max = parseFloat(maxEl.value);
        let tEl = document.getElementById('timeoutH_'+id); if(tEl && tEl.value !== '') node.timeout_hours = parseFloat(tEl.value);
        if(type === 'SPECIFIC_USER'){
            // approvers 改存隐藏域（JSON 数组），不再依赖 select 多选
            let hidden = document.getElementById('approvers_'+id);
            let raw = hidden ? hidden.value : '[]';
            try{ node.approvers = JSON.parse(raw).map(function(x){return parseInt(x);}); }catch(e){ node.approvers = []; }
            let m = document.getElementById('mode_'+id); if(m) node.mode = m.value;
        } else if(type === 'ROLE'){
            let sel = document.getElementById('roleCode_'+id);
            if(sel) node.role_code = sel.value;
            let m = document.getElementById('mode_'+id); if(m) node.mode = m.value;
        }
        nodes.push(node);
    });
    return nodes;
}

// 流程级抄送面板（独立于审批节点链；v2.38.25 抄送角色改为「下拉选择 + 已选 chips」多选）
function fillCcRoleOptions(){
    // 从 ccRoles 隐藏域读取已选，渲染 chips + 填充未选角色下拉
    let codes = [];
    try{ codes = JSON.parse(document.getElementById('ccRoles').value||'[]'); }catch(e){ codes=[]; }
    let box = document.getElementById('ccRolesView');
    if(box){
        box.innerHTML = codes.length
            ? codes.map(function(c){
                let n = (allRoles||[]).find(function(r){ return r.code===c; });
                return '<span class="badge bg-info me-1 mb-1">'+esc(n?n.name:c)+'<b style="cursor:pointer" onclick="removeCcRole(\''+c+'\')"> ×</b></span>';
              }).join('')
            : '<span class="text-muted small">未选择</span>';
    }
    let sel = document.getElementById('ccRoleSel');
    if(!sel) return;
    sel.innerHTML = '<option value="">选择角色…</option>' + (allRoles||[]).filter(function(r){ return codes.indexOf(r.code)===-1; })
        .map(function(r){ return '<option value="'+r.code+'">'+esc(r.name)+'</option>'; }).join('');
}
/** 下拉选中角色 → 加入抄送列表（可多次选择） */
function addCcRole(sel){
    if(!sel || !sel.value) return;
    let codes = [];
    try{ codes = JSON.parse(document.getElementById('ccRoles').value||'[]'); }catch(e){ codes=[]; }
    if(codes.indexOf(sel.value)===-1) codes.push(sel.value);
    document.getElementById('ccRoles').value = JSON.stringify(codes);
    fillCcRoleOptions();
}
/** 移除已选抄送角色 */
function removeCcRole(code){
    let codes = [];
    try{ codes = JSON.parse(document.getElementById('ccRoles').value||'[]'); }catch(e){ codes=[]; }
    codes = codes.filter(function(c){ return c!==code; });
    document.getElementById('ccRoles').value = JSON.stringify(codes);
    fillCcRoleOptions();
}
function getCcListData(){
    let ccRoles = [];
    try{ ccRoles = JSON.parse(document.getElementById('ccRoles').value||'[]'); }catch(e){ ccRoles = []; }
    let ccUsers = [];
    try{ ccUsers = JSON.parse(document.getElementById('ccUsers').value||'[]'); }catch(e){ ccUsers = []; }
    return {role_codes: ccRoles, cc_user_ids: ccUsers.map(function(x){ return parseInt(x); })};
}
function openCcPicker(){
    let cur = [];
    try{ cur = JSON.parse(document.getElementById('ccUsers').value||'[]'); }catch(e){ cur = []; }
    openUserPicker({
        multiple: true, selected: cur,
        onConfirm: function(ids){
            document.getElementById('ccUsers').value = JSON.stringify(ids);
            let box = document.getElementById('ccUsersView');
            if(ids.length===0){ box.innerHTML='<span class="text-muted small">未选择</span>'; return; }
            box.innerHTML = ids.map(function(id){return '<span class="badge bg-info me-1 mb-1">'+esc(userNameById(id))+'</span>';}).join('');
        }
    });
}

function saveFlow(){
    let nodes = getNodesData();
    if(nodes.length === 0){ showToast('至少需要1个审批节点','error'); return; }
    let fd = new FormData(document.getElementById('flowForm'));
    fd.append('nodes', JSON.stringify(nodes));
    fd.append('cc_list', JSON.stringify(getCcListData())); // 流程级抄送
    $ajax('/ajax/admin/flow/save',{method:'POST',body:fd,loadingText:'保存中…'})
        .then(function(res){ showToast(res.msg||'已保存',res.code===0?'success':'error'); if(res.code===0)setTimeout(function(){location.reload();},800); })
        .catch(function(){});
}

function delFlow(id){pcConfirm({message:'确定删除该审批流程？\n删除后进入回收站，可在列表右上角「回收站」中恢复；已在进行中的审批实例不受影响。',danger:true}).then(function(ok){if(!ok)return;let fd=new FormData();fd.append('id',id);$ajax('/ajax/admin/flow/delete',{method:'POST',body:fd,loading:false}).then(function(res){showToast(res.msg||'已删除',res.code===0?'success':'error');if(res.code===0)setTimeout(function(){location.reload();},800);}).catch(function(){});});}
function restoreFlow(id){pcConfirm({message:'确定恢复该审批流程为启用？\n恢复后该流程将重新参与新合同的审批匹配。',danger:true}).then(function(ok){if(!ok)return;let fd=new FormData();fd.append('id',id);$ajax('/ajax/admin/flow/restore',{method:'POST',body:fd,loading:false}).then(function(res){showToast(res.msg||'已恢复',res.code===0?'success':'error');if(res.code===0)setTimeout(function(){location.reload();},800);}).catch(function(){});});}
