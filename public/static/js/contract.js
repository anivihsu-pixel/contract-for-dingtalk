/**
 * 合同管理 - 列表/排序/导出 + 甲乙方搜索型选择器
 * 依赖：<form id="searchForm"> + <table id="tableBody"> + <div id="pagination"> + <form id="contractForm">
 */

// ========== 关键词标签(chip)输入：免手动敲逗号，回车/逗号/顿号/分号即生成标签 ==========
// 用户只需逐个输入关键词后回车，无需自己管理分隔符；提交时自动拼成英文逗号分隔串，
// 与后端 normalize_keywords() 口径一致（去重、去空）。彻底规避「输错逗号」。
(function(){
    // 兼容旧调用：仍保留字符串归一化函数供他处复用
    function normalizeKeywords(v){
        var s = (v == null ? '' : String(v)).replace(/[，、；\s]+/g, ',').trim();
        var seen = {}, out = [];
        s.split(',').forEach(function(p){
            p = p.trim();
            if(p && !seen[p]){ seen[p] = 1; out.push(p); }
        });
        return out.join(',');
    }
    window.normalizeKeywords = normalizeKeywords;

    // 一次性注入关键词展示区 + 弹层所需样式
    function injectStyle(){
        if(document.getElementById('kwChipStyle')) return;
        var st = document.createElement('style');
        st.id = 'kwChipStyle';
        st.textContent =
            '.kw-display{display:flex;align-items:center;gap:4px;flex-wrap:wrap;min-height:38px;padding:4px 8px;border:1px solid #ced4da;border-radius:.375rem;background:#fff;cursor:pointer}'+
            '.kw-display:hover{border-color:#86b7fe}'+
            '.kw-display .kw-empty{color:#9aa3af;font-size:13px}'+
            '.kw-display .kw-add-hint{margin-left:auto;color:#0b5ed7;font-size:12px;white-space:nowrap}'+
            '.kw-chip{display:inline-flex;align-items:center;gap:4px;background:#e7f1ff;color:#0b5ed7;border-radius:12px;padding:2px 8px;font-size:13px;line-height:1.5;max-width:100%}'+
            '.kw-chip span{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:160px}'+
            '.kw-chip b{cursor:pointer;font-weight:700;opacity:.55;font-size:14px}'+
            '.kw-chip b:hover{opacity:1}'+
            '.kw-pc-mask{position:fixed;inset:0;background:rgba(15,23,42,.45);z-index:1040}'+
            '.kw-pc-sheet{position:fixed;top:0;left:0;right:0;z-index:1050;background:#fff;border-radius:0 0 14px 14px;box-shadow:0 10px 40px rgba(0,0,0,.18);max-height:78vh;overflow:auto}'+
            '.kw-pc-sheet-hd{display:flex;align-items:center;justify-content:space-between;padding:12px 16px;border-bottom:1px solid #f0f0f0}'+
            '.kw-pc-sheet-hd b{font-size:15px;color:#333}'+
            '.kw-pc-close{font-size:22px;color:#999;line-height:1;background:none;border:none;padding:4px}'+
            '.kw-pc-sheet-input-row{display:flex;gap:8px;padding:12px 16px 4px}'+
            '.kw-pc-sheet-input-row input{flex:1;border:1px solid #dcdfe6;border-radius:8px;padding:8px 12px;font-size:14px;outline:none}'+
            '.kw-pc-sheet-input-row input:focus{border-color:#0b5ed7}'+
            '.kw-pc-sheet-input-row button{border:none;background:#0b5ed7;color:#fff;border-radius:8px;padding:0 18px;font-size:14px;font-weight:600;white-space:nowrap}'+
            '.kw-pc-sheet-input-row button:disabled{background:#b8d1f5}'+
            '.kw-pc-sheet-sec{padding:8px 16px 4px}'+
            '.kw-pc-sheet-sec .kw-sec-t{font-size:12px;color:#888;margin-bottom:6px}'+
            '.kw-pc-hot{display:flex;flex-wrap:wrap;gap:6px;padding-bottom:8px}'+
            '.kw-pc-hot .kw-hot-item{display:inline-block;background:#f4f6fa;color:#374151;border:1px solid #e5e7eb;border-radius:12px;padding:2px 10px;font-size:13px;cursor:pointer;user-select:none}'+
            '.kw-pc-hot .kw-hot-item:hover{background:#e7f1ff;color:#2563eb;border-color:#bfdbfe}'+
            '.kw-pc-hot .kw-hot-item.dis{background:#f1f2f4;color:#b0b6bf;cursor:not-allowed}'+
            '.kw-pc-cur{display:flex;flex-wrap:wrap;gap:6px;padding-bottom:8px}'+
            '.kw-sheet-none{font-size:12px;color:#bbb;padding:2px 0 10px}';
        document.head.appendChild(st);
    }

    var SEP = /[，、；;,\s]+/; // 分隔符：中英文逗号、顿号、分号、空白

    // v2.44.2：PC 关键词控件对齐移动端——hidden 承载值，只读展示区点击弹出顶部弹层
    // （输入框 + 添加按钮 + 常用标签推荐 + 已选区），推荐收纳在弹层内而非平铺在输入框下方。
    function initChips(hidden){
        if(!hidden || hidden.dataset.chipInit) return;
        hidden.dataset.chipInit = '1';
        injectStyle();
        var display = document.getElementById('kwDisplay');
        var mask    = document.getElementById('kwMask');
        var sheet   = document.getElementById('kwSheet');
        var input   = document.getElementById('kwInput');
        var addBtn  = document.getElementById('kwAddBtn');
        var hot     = document.getElementById('kwHot');
        var cur     = document.getElementById('kwCur');
        var curSec  = document.getElementById('kwCurSec');
        var closeBtn = document.getElementById('kwClose');
        if(!display || !sheet || !input || !hot || !cur){ return; } // 无弹层结构（非 PC 新建/编辑页）则跳过

        var tags = [];
        function sync(){ hidden.value = tags.join(','); }
        function addRaw(raw){
            String(raw == null ? '' : raw).split(SEP).forEach(function(p){
                p = p.trim();
                if(p && tags.indexOf(p) === -1) tags.push(p);
            });
        }
        // 展示区：已选 chips + 「添加」提示
        function renderDisplay(){
            display.innerHTML = '';
            if(tags.length === 0){
                var e = document.createElement('span'); e.className = 'kw-empty'; e.textContent = '点击添加关键词';
                display.appendChild(e);
            } else {
                tags.forEach(function(t){
                    var chip = document.createElement('span'); chip.className = 'kw-chip';
                    var tx = document.createElement('span'); tx.className = 'tx'; tx.textContent = t; tx.title = t;
                    chip.appendChild(tx);
                    display.appendChild(chip);
                });
            }
            var hint = document.createElement('span'); hint.className = 'kw-add-hint'; hint.textContent = '添加';
            display.appendChild(hint);
            sync();
        }
        // 弹层「已选」区（可点 × 移除）
        function renderCur(){
            cur.innerHTML = '';
            curSec.style.display = tags.length ? '' : 'none';
            tags.forEach(function(t, i){
                var chip = document.createElement('span'); chip.className = 'kw-chip';
                var tx = document.createElement('span'); tx.className = 'tx'; tx.textContent = t;
                var x = document.createElement('b'); x.textContent = '\u00d7';
                (function(idx){ x.addEventListener('click', function(e){ e.preventDefault(); tags.splice(idx, 1); renderCur(); renderDisplay(); refreshHot(); }); })(i);
                chip.appendChild(tx); chip.appendChild(x);
                cur.appendChild(chip);
            });
        }
        // 常用标签（/ajax/keyword/hot）：已选置灰，点击即加入
        function renderHot(list){
            hot.innerHTML = '';
            if(!list || !list.length){
                var n = document.createElement('div'); n.className = 'kw-sheet-none'; n.textContent = '暂无常用标签';
                hot.appendChild(n); return;
            }
            list.forEach(function(kw){
                var item = document.createElement('span'); item.className = 'kw-hot-item'; item.textContent = kw;
                if(tags.indexOf(kw) !== -1){ item.classList.add('dis'); }
                item.addEventListener('click', function(){
                    if(tags.indexOf(kw) === -1){ addRaw(kw); renderCur(); renderDisplay(); refreshHot(list); input.focus(); }
                });
                hot.appendChild(item);
            });
            hot._list = list;
        }
        function refreshHot(list){ renderHot(list || hot._list || []); }

        function openSheet(){
            mask.style.display = ''; sheet.style.display = '';
            renderCur(); refreshHot();
            input.value = ''; addBtn.disabled = true;
            // 高频关键词只拉一次缓存复用
            if(!hot._loaded){
                hot._loaded = true;
                fetch('/ajax/keyword/hot?limit=12', {headers:{'X-Requested-With':'XMLHttpRequest'}})
                    .then(function(r){ return r.json(); })
                    .then(function(res){
                        if(res && res.code === 0 && Array.isArray(res.data)){ renderHot(res.data); }
                    }).catch(function(){});
            }
            setTimeout(function(){ input.focus(); }, 60);
        }
        function closeSheet(){ mask.style.display = 'none'; sheet.style.display = 'none'; }

        // 右侧「添加」按钮：把当前输入收为一个标签
        addBtn.addEventListener('click', function(){
            if(input.value.trim()){ addRaw(input.value); input.value = ''; addBtn.disabled = true; renderCur(); renderDisplay(); refreshHot(); }
            input.focus();
        });
        input.addEventListener('input', function(){ addBtn.disabled = !input.value.trim(); });
        input.addEventListener('keydown', function(e){
            if(e.key === 'Enter'){ e.preventDefault(); addBtn.click(); }
            else if(e.key === ',' || e.key === '，' || e.key === '、' || e.key === ';' || e.key === '；'){
                e.preventDefault();
                if(input.value.trim()){ addRaw(input.value); input.value = ''; addBtn.disabled = true; renderCur(); renderDisplay(); refreshHot(); }
            } else if(e.key === 'Backspace' && !input.value && tags.length){
                tags.pop(); renderCur(); renderDisplay(); refreshHot();
            } else if(e.key === 'Escape'){ closeSheet(); }
        });

        // 打开 / 关闭
        display.addEventListener('click', openSheet);
        display.addEventListener('keydown', function(e){ if(e.key === 'Enter' || e.key === ' '){ e.preventDefault(); openSheet(); } });
        closeBtn.addEventListener('click', closeSheet);
        mask.addEventListener('click', closeSheet);

        addRaw(hidden.value);   // 编辑态回填已有关键词
        renderDisplay();
        // 提交前把弹层输入框残留内容也收进标签
        var form = hidden.closest('form');
        if(form){ form.addEventListener('submit', function(){ if(input.value.trim()){ addRaw(input.value); input.value = ''; renderDisplay(); } }, { capture: true }); }
    }
    window.initKeywordChips = initChips;

    document.addEventListener('DOMContentLoaded', function(){
        document.querySelectorAll('input[name="keywords"]').forEach(initChips);
    });
})();

(function(){
// ---- 状态变量 ----
var p=1,sf=document.getElementById('searchForm'),tb=document.getElementById('tableBody'),pg=document.getElementById('pagination');
if(!tb)return;

// v2.52.1：查看范围（我的合同/全部合同）
// ① URL 显式 scope 参数（入口链接，如仪表盘「查看全部草稿」）优先遵循；
// ② 对象入口（project_id/customer_id）或显式指定归属人（owner_id）恒为全部；
// ③ 否则取 localStorage 记忆的上次选择，首次默认「我的合同」
var SCOPE_KEY='contract_list_scope';
var __urlParams = new URLSearchParams(location.search);
var __urlScope = __urlParams.get('scope');
var scope;
if (__urlScope === 'me' || __urlScope === 'all') {
    scope = __urlScope;
} else if (__urlParams.has('project_id') || __urlParams.has('customer_id') || (__urlParams.get('owner_id')||'') !== '') {
    scope = 'all';
} else {
    scope = localStorage.getItem(SCOPE_KEY) || 'me';
}

// 排序状态（字段名经后端 BaseController::getSortParams 白名单校验，杜绝字段名注入）
var sortKey='', sortOrder='desc';

/**
 * 加载合同列表
 * @param {number} n 页码
 */
function load(n){
    p=n;
    // 收集筛选表单参数
    var fd=new FormData(sf),pr=new URLSearchParams(fd);
    pr.set('page',n);pr.set('limit',15);
    // v2.52.1：查看范围「我的合同」时归属人固定为本人，覆盖表单中可能残留的归属人筛选（联动）
    if(scope==='me') pr.set('owner_id','me');
    var fw=sf.querySelector('[name="framework"]');
    if(fw && fw.value) pr.set('framework', fw.value);
    // 附加上排序参数
    if(sortKey){ pr.set('sort', sortKey); pr.set('order', sortOrder); }
    // P2-4【M-F1】改用全局 $ajax 包装（自动带 X-Requested-With / JWT，网络异常与会话过期统一 toast，避免静默失败）
    $ajax('/contract?'+pr, {loading: false}).then(function(res){
        var h='';
        // 判断当前是否移动端视口（≤768px）
        var isMobile = window.matchMedia('(max-width: 767.98px)').matches;
        // ---- 空列表状态 ----
        if(!res.data||!res.data.length){
            if(isMobile){
                h='<tr><td colspan="10"><div class="m-empty" style="padding:48px 0;">暂无合同<br><span style="font-size:13px;">创建第一份合同，提交后进入审批流</span></div></td></tr>';
            }else{
                h=emptyState({colspan:11,icon:'bi-file-text',title:'暂无合同',desc:'创建第一份合同，提交后进入审批流',btn:'新建合同',href:'/contract/create',canCreate:window._canCreateContract});
            }
        }else{
            // ---- 逐行渲染 ----
            res.data.forEach(function(c){
                if(isMobile){
                    // 移动端卡片行（点击进入移动端原生详情页 /m/contract/<id>）
                    var amt = c.trade_attr == 0 ? '<span class="m-ctag m-ctag-muted">非交易</span>'
                        : '<span class="m-camt '+(c.direction==='sales'?'m-amt-in':'m-amt-out')+'">¥'+parseFloat(c.amount||0).toLocaleString('zh-CN',{minimumFractionDigits:2})+'</span>';
                    h+='<tr><td colspan="10" style="padding:8px 0;border:none;">';
                    h+='<div class="m-ccard" onclick="location.href=\'/m/contract/'+c.id+'\'">';
                    h+='<div class="m-ccard-top"><span class="m-ccard-t">'+esc(c.title)+'</span>'+statusB(c.status)+'</div>';
                    h+='<div class="m-ccard-no">'+esc(c.contract_no||'')+'</div>';
                    h+='<div class="m-ccard-meta">'+dirBadge(c)+(window._businessTypes&&window._businessTypes[c.business_type]?('<span class="m-ctag m-ctag-muted">'+esc(window._businessTypes[c.business_type])+'</span>'):'')+amt+'</div>';
                    if(c.party_b_name){ h+='<div class="m-ccard-party"><i class="bi bi-people me-1"></i>'+esc(c.party_b_name)+'</div>'; }
                    h+='</div>';
                    h+='</td></tr>';
                }else{
                    // 桌面端表格行（整行可点进入详情，REV-28：首列为复选框，点击不触发导航；P2-07：tabindex+Enter 键盘可达）
                    h+='<tr role="link" tabindex="0" aria-label="查看合同详情" onclick="location.href=\'/contract/'+c.id+'\'" style="cursor:pointer" onkeydown="if(event.target.tagName===\'INPUT\'||event.target.tagName===\'SELECT\'||event.target.tagName===\'TEXTAREA\')return;if(event.key===\'Enter\'||event.key===\' \'){event.preventDefault();location.href=\'/contract/'+c.id+'\';}">';
                    h+='<td onclick="event.stopPropagation()"><input type="checkbox" class="batch-cb" value="'+c.id+'" onchange="updateBatchBar()"></td>';
                    h+='<td><small>'+esc(c.contract_no)+'</small></td>';
                    h+='<td>'+esc(c.title)+'</td>';
                    h+='<td>'+(window._businessTypes&&window._businessTypes[c.business_type]?esc(window._businessTypes[c.business_type]):esc(c.business_type))+'</td>';
                    h+='<td>'+dirBadge(c)+'</td>';
                    h+='<td class="text-end">'+parseFloat(c.amount||0).toLocaleString('zh-CN',{minimumFractionDigits:2})+'</td>';
                    h+='<td>'+statusB(c.status)+'</td>';
                    h+='<td>'+(esc(c.party_b_name)||'-')+'</td>';
                    // 关联项目列
                    if(c.project_id && c.project_name){
                        h+='<td><span class="badge bg-info bg-opacity-10 text-info" style="font-size:10px" title="关联项目：'+esc(c.project_name)+'"><i class="bi bi-folder2 me-1"></i>'+esc(c.project_name)+'</span></td>';
                    } else {
                        h+='<td><small class="text-muted">-</small></td>';
                    }
                    // 框架合同/执行订单列
                    if(c.parent_id && c.parent_no){
                        h+='<td><small class="text-muted" title="归属于框架合同：'+esc(c.parent_title||'')+'">📄 '+esc(c.parent_no)+'</small></td>';
                    } else if(!c.parent_id && (c.child_count||0) > 0){
                        h+='<td><span class="badge bg-primary bg-opacity-10 text-primary" style="font-size:10px;cursor:help" title="此合同为框架合同，已有 '+(c.child_count||0)+' 个执行订单">框架 · '+(c.child_count||0)+'单</span></td>';
                    } else {
                        h+='<td><small class="text-muted" title="独立合同，未关联框架">-</small></td>';
                    }
                    // v2.43.3：编辑按钮仅对草稿/驳回状态渲染（与后端 create() 状态校验一致），
                    // 审批中/生效等不可编辑状态的合同不再显示编辑入口，避免点击后报「当前状态不可编辑」
                    h+='<td>'+(c.status==='DRAFT'||c.status==='REJECTED'
                        ?'<a href="/contract/'+c.id+'/edit" class="btn btn-sm btn-outline-secondary" aria-label="编辑" onclick="event.stopPropagation()"><i class="bi bi-pencil"></i></a>'
                        :'')+'</td>';
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
        pg.querySelectorAll('a').forEach(function(a){
            a.addEventListener('click',function(e){e.preventDefault();load(parseInt(this.dataset.p));});
        });
    }).catch(function(){
        // P2-4【M-F1】错误已由 $ajax 统一 toast（网络异常/会话过期/非 JSON 响应）；
        // 列表保持当前内容，分页控件不重算为 NaN
        // 修复（2026-07-25）：请求失败时务必清除初始占位 spinner，否则会永久转圈；
        // 改为展示「加载失败 + 重新加载」操作点，点击重试当前页。
        if(tb){
            tb.innerHTML='<tr><td colspan="11" class="text-center py-5 text-muted">'
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

// ---- 工具函数 ----

// HTML 转义：统一使用 app.js 全局 esc（P3-5：移除本地 escH 重复副本）

/** 合同状态 → Bootstrap 标签 HTML */
function statusB(s){
    var m={DRAFT:'<span class="pc-tag pc-tag-warn">草稿</span>',PENDING_APPROVAL:'<span class="pc-tag pc-tag-warn">待审批</span>',REJECTED:'<span class="pc-tag pc-tag-danger">已驳回</span>',EXECUTING:'<span class="pc-tag pc-tag-ok">执行中</span>',COMPLETED:'<span class="pc-tag pc-tag-muted">已完成</span>',TERMINATED:'<span class="pc-tag pc-tag-danger">已终止</span>',EXPIRED:'<span class="pc-tag pc-tag-muted">已到期</span>',ARCHIVED:'<span class="pc-tag pc-tag-muted">已归档</span>'};
    return m[s]||s;
}

/** 收支方向 + 交易属性 → 标签 HTML */
function dirBadge(c){
    if(c && c.trade_attr == 0) return '<span class="badge bg-secondary">非交易·不计入收支</span>';
    var d = (c && c.direction) ? c.direction : '';
    if(d==='purchase') return '<span class="badge bg-warning text-dark">采购·我方为乙方(付款)</span>';
    if(d==='sales') return '<span class="badge bg-success">销售·我方为甲方(收款)</span>';
    return '<span class="badge bg-secondary">未定</span>';
}

// 筛选表单提交 → 重新从第 1 页加载
if(sf)sf.addEventListener('submit',function(e){e.preventDefault();load(1);});

// P1-7：高级筛选抽屉「重置」— 清空搜索表单内全部筛选控件（排除按钮），回到默认筛选并刷新
window.resetFilters = function(){
    if(!sf) return;
    sf.querySelectorAll('input[name], select[name]').forEach(function(el){ el.value = ''; });
    // v2.41.0：搜索选择器（归属人等）重置——可见输入框一并清空
    sf.querySelectorAll('.cs-wrap .cs-input').forEach(function(el){ el.value = ''; });
    load(1);
    // 关闭抽屉（若有 Bootstrap offcanvas 实例）
    var off = document.getElementById('advFilter');
    if(off && window.bootstrap && bootstrap.Offcanvas && bootstrap.Offcanvas.getInstance(off)){
        bootstrap.Offcanvas.getInstance(off).hide();
    }
};

// ---- 表头排序（字段经后端白名单校验，杜绝字段名注入） ----
document.querySelectorAll('th[data-sort]').forEach(function(th){
    th.addEventListener('click', function(){
        var k = th.getAttribute('data-sort');
        if (sortKey === k) { sortOrder = (sortOrder === 'asc') ? 'desc' : 'asc'; }
        else { sortKey = k; sortOrder = 'asc'; }
        // 清除其他列排序指示器
        document.querySelectorAll('th[data-sort]').forEach(function(t){ t.classList.remove('sorted-asc','sorted-desc'); });
        th.classList.add(sortOrder === 'asc' ? 'sorted-asc' : 'sorted-desc');
        load(1);
    });
});

// 初始加载：等 DOM 与共享脚本（app.js 提供的 $ajax / emptyState 等全局）就绪后再触发。
// 关键修复（2026-07-25）：列表脚本在 footer 的 app.js 之前执行，若此刻直接 load(1)
// 会因 $ajax 未定义而同步抛错、初始 spinner 永久转圈。DOMContentLoaded 在所有同步脚本
// （含 footer 的 app.js）执行完后才触发，届时全局已就绪，彻底消除脚本顺序依赖。
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function(){ load(1); });
} else {
    load(1);
}

// ---- v2.40.0：草稿快捷筛选（全部 / 草稿 / 我的草稿） ----
// 点击 chip → 设置 status/owner_id → 刷新列表，并切换激活态（激活=primary，其余 outline）
(function(){
  var chips = sf ? sf.querySelectorAll('.draft-chip') : [];
  if(!chips.length) return;
  function syncChips(){
    var curStatus = new URLSearchParams(location.search).get('status') || '';
    var curOwner  = new URLSearchParams(location.search).get('owner_id') || '';
    chips.forEach(function(ch){
      var active = (ch.dataset.status||'') === curStatus && (ch.dataset.owner||'') === curOwner;
      ch.classList.toggle('btn-primary', active);
      ch.classList.toggle('btn-outline-primary', !active);
    });
  }
  chips.forEach(function(ch){
    ch.addEventListener('click', function(){
      var s = sf.querySelector('[name="status"]');  if(s) s.value = ch.dataset.status || '';
      var o = sf.querySelector('[name="owner_id"]');
      if(o){
        // v2.41.0：归属人已由下拉改为搜索选择器（隐藏 input 存 id + 可见输入框回显）。
        // 'me' 为「我的草稿」特殊值，回显「我」；其余按名称回显
        o.value = ch.dataset.owner || '';
        var oIn = sf.querySelector('.cs-wrap [name="owner_id"]');
        var oTxt = oIn ? oIn.closest('.cs-wrap').querySelector('.cs-input') : null;
        if(oTxt){
          if(ch.dataset.owner === 'me') oTxt.value = '我';
          else if(ch.dataset.owner === '') oTxt.value = '';
          else {
            var u = (window._contractOwners || []).filter(function(x){ return String(x.id) === String(ch.dataset.owner); })[0];
            oTxt.value = u ? u.name : '';
          }
        }
      }
      load(1);
      chips.forEach(function(x){
        var act = x === ch;
        x.classList.toggle('btn-primary', act);
        x.classList.toggle('btn-outline-primary', !act);
      });
    });
  });
  syncChips();
})();

// ---- v2.52.1：查看范围切换（我的合同/全部合同） ----
// scope=me 时归属人选择器禁用（归属固定为本人），切回全部时恢复可用；
// 归属人选择器中被忽略的已选值保留，切回全部后原筛选仍生效。
(function(){
  var chips = sf ? sf.querySelectorAll('.scope-chip') : [];
  if(!chips.length) return;
  function syncScopeChips(){
    chips.forEach(function(ch){
      var act = ch.dataset.scope === scope;
      ch.classList.toggle('btn-primary', act);
      ch.classList.toggle('btn-outline-primary', !act);
    });
  }
  function syncOwnerDisabled(){
    var oIn = sf.querySelector('.cs-wrap .cs-input');
    if(!oIn) return;
    oIn.disabled = (scope === 'me');
    oIn.placeholder = (scope === 'me') ? '查看范围为「我的合同」，归属人筛选不可用' : '搜索归属人姓名';
  }
  chips.forEach(function(ch){
    ch.addEventListener('click', function(){
      var v = ch.dataset.scope;
      if(v === scope) return;
      scope = v;
      try{ localStorage.setItem(SCOPE_KEY, v); }catch(e){}
      syncScopeChips(); syncOwnerDisabled();
      load(1);
    });
  });
  syncScopeChips(); syncOwnerDisabled();
  // 高级筛选抽屉打开时同步归属人禁用态（避免抽屉内已选归属人与查看范围冲突）
  var off = document.getElementById('advFilter');
  if(off){ off.addEventListener('show.bs.offcanvas', syncOwnerDisabled); }
})();

// ---- 导出合同（携带当前筛选条件；P1-5：防重复连点 + 进度提示） ----
window.exportContracts = function(){
    var sf = document.getElementById('searchForm');
    var params = new URLSearchParams();
    if(sf){
        var fd = new FormData(sf);
        for(var pair of fd.entries()){
            if(pair[1]) params.append(pair[0], pair[1]);
        }
    }
    var url = '/ajax/export/contracts';
    var qs = params.toString();
    if(qs) url += '?' + qs;
    fetch(url,{headers:{'X-Requested-With':'XMLHttpRequest'}}).then(function(resp){
        var ct=resp.headers.get('content-type')||'';
        if(ct.indexOf('application/json')>=0)return resp.json().then(function(r){if(r.data&&r.data.requires_confirmation){return pcConfirm({message:r.msg}).then(function(ok){if(ok){params.set('confirmed','1');location.href='/ajax/export/contracts?'+params.toString();}});}showToast(r.msg||'导出失败','error');});
        return resp.blob().then(function(blob){var a=document.createElement('a');a.href=URL.createObjectURL(blob);a.download='contracts_'+new Date().toISOString().slice(0,10)+'.csv';a.click();setTimeout(function(){URL.revokeObjectURL(a.href)},1000);});
    }).catch(function(){showToast('导出失败，请稍后重试','error');});
};

// ---- REV-28：批量操作（全选/清除/批量归档/批量删除） ----

/** 全选/取消全选 */
window.toggleAll = function(cb){
    document.querySelectorAll('.batch-cb').forEach(function(c){ c.checked = cb.checked; });
    updateBatchBar();
};

/** 更新批量操作栏状态 */
window.updateBatchBar = function(){
    var cbs = document.querySelectorAll('.batch-cb:checked');
    var bar = document.getElementById('batchBar');
    var cnt = document.getElementById('batchCount');
    if(!bar || !cnt) return;
    var n = cbs.length;
    cnt.textContent = n;
    bar.style.display = n > 0 ? '' : 'none';
    // 切换页面时清除全选框（如果有批量选中状态与当前页不一致）
    document.getElementById('selectAll').checked = (n > 0 && n === document.querySelectorAll('.batch-cb').length);
};

/** 获取当前选中的合同 ID 列表（数组） */
function getSelectedIds(){
    var ids = [];
    document.querySelectorAll('.batch-cb:checked').forEach(function(c){ ids.push(parseInt(c.value)); });
    return ids;
}

/** 清除批量选择 */
window.clearBatch = function(){
    document.querySelectorAll('.batch-cb').forEach(function(c){ c.checked = false; });
    document.getElementById('selectAll').checked = false;
    updateBatchBar();
};

/** 批量归档（仅 EXECUTING/COMPLETED 状态可归档，后端二次校验） */
window.batchArchive = function(){
    var ids = getSelectedIds();
    if(!ids.length) return showToast('请先选择合同', 'error');
    pcConfirm({ message: '确认将 ' + ids.length + ' 份合同批量归档？', danger: true, okText: '确认归档' }).then(function(ok){
        if(!ok) return;
        // P3-8【M-F2】改用 $ajax 包装，统一 loading/toast 与错误兜底（401/网络异常）
        $ajax('/ajax/contract/batch-archive', {
            method: 'POST',
            body: new URLSearchParams({ids: ids.join(',')}),
            loading: true, loadingText: '归档中…'
        }).then(function(d){
            if(d.code === 0){ showToast('已归档 ' + (d.data && d.data.count || 0) + ' 份合同', 'success'); clearBatch(); load(p); }
            else showToast(d.msg || '批量归档失败', 'error');
        }).catch(function(){});
    });
};

/** 批量删除（仅 DRAFT/REJECTED 可软删除，后端二次校验） */
window.batchDelete = function(){
    var ids = getSelectedIds();
    if(!ids.length) return showToast('请先选择合同', 'error');
    pcConfirm({ message: '⚠️ 确认删除 ' + ids.length + ' 份合同？此操作不可撤销！', danger: true, okText: '确认删除' }).then(function(ok){
        if(!ok) return;
        // P3-8【M-F2】改用 $ajax 包装，统一 loading/toast 与错误兜底（401/网络异常）
        $ajax('/ajax/contract/batch-delete', {
            method: 'POST',
            body: new URLSearchParams({ids: ids.join(',')}),
            loading: true, loadingText: '删除中…'
        }).then(function(d){
            if(d.code === 0){ showToast('已删除 ' + (d.data && d.data.count || 0) + ' 份合同', 'success'); clearBatch(); load(p); }
            else showToast(d.msg || '批量删除失败', 'error');
        }).catch(function(){});
    });
};

})();

// ---- 合同新建/编辑表单提交 ----
var cf=document.getElementById('contractForm');
if(cf)cf.addEventListener('submit',function(e){
    e.preventDefault();
    // P1：PC 端合同保存防重复连点锁（与审批提交/发票开票/移动端表单既有锁一致）——
    // 双击提交会并发发出两个 save 请求，后端对同一表单重复调用非幂等，可能创建两份合同
    if(window.__cfSaving) return;
    window.__cfSaving=true;
    var btnSave=document.getElementById('btnSave');
    if(btnSave) btnSave.disabled=true;
    var fd=new FormData(this);
    // P2-4【M-F2】改用 $ajax 包装，弱网/会话过期有 toast 反馈，避免"点了没反应"
    $ajax('/ajax/contract/save',{method:'POST',body:new URLSearchParams(fd),loading:true,loadingText:'保存中…'})
    .then(function(res){
        window.__cfSaving=false;
        if(btnSave) btnSave.disabled=false;
        if(res.code===0){ window.__formDirty=false; location.href='/contract/'+res.data.id; }   // P1-4：保存成功，清除离页保护标记后跳转
        else showToast(res.msg||'操作失败','error');
    })
    .catch(function(){
        window.__cfSaving=false;
        if(btnSave) btnSave.disabled=false;
        // 网络异常/非 JSON 响应已由 $ajax 统一 toast，这里无需额外处理
    });
});

// ============================================================
// 甲乙方搜索型选择器 (Search + Autocomplete + 键盘导航)
// 支持客户/供应商混搜，选中后自动填充隐藏字段（名称/ID/联系人/类型/信用代码）
// ============================================================
(function(){
    var searches = document.querySelectorAll('.party-search');
    if (!searches.length) return;

    var activeIdx = -1;       // 键盘导航当前选中索引
    var activeSide = null;    // 当前操作方 'A'（甲方）或 'B'（乙方）
    var activeList = [];      // 当前建议列表缓存
    var searchTimer = null;   // 防抖定时器（200ms）
    // v2.46.0：签约方强制关联档案——每侧是否已选客户/供应商（选中后名称只读锁定，防手输绕过）
    var partyLinked = {A: false, B: false};

    // 初始锁定：编辑态已有关联档案（客户/供应商 ID>0）的侧，名称只读
    ['A', 'B'].forEach(function (s) {
        var cidEl = document.getElementById('party' + s + 'CustId');
        var sidEl = document.getElementById('party' + s + 'SupplierId');
        if (cidEl && (parseInt(cidEl.value, 10) > 0 || (sidEl && parseInt(sidEl.value, 10) > 0))) {
            partyLinked[s] = true;
            var nm = document.getElementById('party' + s + 'Name');
            if (nm) nm.readOnly = true;
        }
    });
    // 解锁：用户手动修改搜索框 → 清除关联档案，允许重新选择
    function unlockParty(side) {
        partyLinked[side] = false;
        var nm = document.getElementById('party' + side + 'Name');
        if (nm) nm.readOnly = false;
        var cidEl = document.getElementById('party' + side + 'CustId');
        if (cidEl) cidEl.value = 0;
        var sidEl = document.getElementById('party' + side + 'SupplierId');
        if (sidEl) sidEl.value = 0;
        var cc = document.getElementById('party' + side + 'CreditCode');
        if (cc) cc.value = '';
    }
    // 空结果：渲染「快速新建客户/供应商」入口（v2.46.0：未登记相对方表单内快速建档）
    function renderEmptySuggest(side) {
        var box = document.getElementById('party' + side + 'Suggestions');
        if (!box) return;
        box.innerHTML = '<div class="party-empty">未找到匹配的客户/供应商</div>'
            + '<div class="party-quick"><button type="button" class="btn btn-sm btn-outline-primary" '
            + 'onclick="openPartyQuick(\'' + side + '\')"><i class="bi bi-plus-lg me-1"></i>快速新建客户/供应商</button></div>';
        box.style.display = 'block';
    }

    searches.forEach(function(input){
        var side = input.dataset.side;  // 'A' 或 'B'
        var sugg = document.getElementById('party' + side + 'Suggestions');

        // ---- 输入事件：边输入边搜索（200ms 防抖） ----
        input.addEventListener('input', function(){
            var q = this.value.trim();
            // v2.46.0：已锁定档案后手动改输入 → 解锁并清除关联
            if (partyLinked[side]) { unlockParty(side); }
            clearTimeout(searchTimer);
            activeSide = side;
            if (q.length < 1) {
                hideSuggestions(side);
                return;
            }
            searchTimer = setTimeout(function(){
                fetch('/ajax/party/search?q=' + encodeURIComponent(q), {
                    headers: {'X-Requested-With': 'XMLHttpRequest'}
                })
                .then(function(r){ return r.json(); })
                .then(function(res){
                    if (res.code !== 0 || !res.data || !res.data.length) {
                        renderEmptySuggest(side);
                        return;
                    }
                    activeList = res.data;
                    activeIdx = -1;
                    renderSuggestions(side, activeList);
                });
            }, 200);
        });

        // ---- 键盘导航：上下箭头选/回车确认/Esc关闭 ----
        input.addEventListener('keydown', function(e){
            var list = document.querySelectorAll('#party' + side + 'Suggestions .party-item');
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                activeIdx = Math.min(activeIdx + 1, list.length - 1);
                highlightItem(list, activeIdx);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                activeIdx = Math.max(activeIdx - 1, 0);
                highlightItem(list, activeIdx);
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (activeIdx >= 0 && activeIdx < activeList.length) {
                    selectParty(side, activeList[activeIdx]);
                }
            } else if (e.key === 'Escape') {
                hideSuggestions(side);
            }
        });

        // ---- 点击外部关闭建议列表 ----
        document.addEventListener('click', function(e){
            var wrap = input.parentElement;
            if (!wrap.contains(e.target)) {
                hideSuggestions(side);
            }
        });

        // ---- 聚焦时重新显示建议（如果输入框中有文本） ----
        input.addEventListener('focus', function(){
            if (this.value.trim().length > 0 && activeSide === side) {
                fetch('/ajax/party/search?q=' + encodeURIComponent(this.value.trim()), {
                    headers: {'X-Requested-With': 'XMLHttpRequest'}
                })
                .then(function(r){ return r.json(); })
                .then(function(res){
                    if (res.code === 0 && res.data && res.data.length) {
                        activeList = res.data;
                        activeIdx = -1;
                        renderSuggestions(side, activeList);
                    }
                });
            }
        });
    });

    /** 渲染建议下拉列表 */
    function renderSuggestions(side, items){
        var box = document.getElementById('party' + side + 'Suggestions');
        var h = '';
        items.forEach(function(item, i){
            var icon = item.party_type === 'customer' ? 'bi-people' : 'bi-truck';
            var badge = item.party_type === 'customer'
                ? '<span class="badge bg-primary bg-opacity-10 text-primary" style="font-size:10px">客户</span>'
                : '<span class="badge bg-warning bg-opacity-10 text-warning" style="font-size:10px">供应商</span>';
            h += '<div class="party-item" data-idx="' + i + '" data-id="' + item.id + '" data-type="' + item.party_type + '">';
            h += '<i class="bi ' + icon + ' me-2 text-muted"></i>';
            h += '<span class="flex-grow-1">' + esc(item.name) + '</span>';
            h += badge;
            h += '<small class="text-muted ms-2">' + esc(item.contact_name || '') + '</small>';
            h += '</div>';
        });
        box.innerHTML = h;
        box.style.display = 'block';

        // 点击项 → 选中
        box.querySelectorAll('.party-item').forEach(function(el){
            el.addEventListener('mousedown', function(e){
                e.preventDefault(); // 阻止 blur 先于 click 触发
                var idx = parseInt(this.dataset.idx);
                selectParty(side, items[idx]);
            });
        });
    }

    /** 高亮键盘导航当前项 */
    function highlightItem(list, idx){
        list.forEach(function(el, i){
            el.classList.toggle('active', i === idx);
        });
    }

    /** 隐藏建议列表 */
    function hideSuggestions(side){
        var box = document.getElementById('party' + side + 'Suggestions');
        box.style.display = 'none';
        box.innerHTML = '';
        activeList = [];
        activeIdx = -1;
    }

    /**
     * 选择对方方（客户或供应商）
     * 自动填充：搜索输入框值、隐藏 name/contact/类型/客户ID/供应商ID/信用代码
     * @param {string} side 'A' 或 'B'
     * @param {object} item 对方对象 {id, name, party_type, contact_name, credit_code}
     */
    function selectParty(side, item){
        document.getElementById('party' + side + 'Search').value = item.name;
        document.getElementById('party' + side + 'Name').value = item.name;
        // v2.46.0：选中档案后名称只读锁定，防手输绕过
        document.getElementById('party' + side + 'Name').readOnly = true;
        partyLinked[side] = true;
        document.getElementById('party' + side + 'Contact').value = item.contact_name || '';
        // v2.47.x：选择客户/供应商带出电话（独立字段）
        var phEl = document.getElementById('party' + side + 'Phone');
        if(phEl) phEl.value = item.contact_mobile || '';
        document.getElementById('party' + side + 'Type').value = item.party_type;
        document.getElementById('party' + side + 'CustId').value = (item.party_type === 'customer') ? item.id : 0;
        // M9：JS 赋值不触发 partyBCustId 的 attributes 观察器，须手动刷新客户联系人下拉（仅 PC 新建页定义了该函数）
        if (typeof window.loadPartyBContacts === 'function') {
            window.loadPartyBContacts((side === 'B' && item.party_type === 'customer') ? item.id : 0);
        }
        var supEl = document.getElementById('party' + side + 'SupplierId');
        if(supEl) supEl.value = (item.party_type === 'supplier') ? item.id : 0;
        // 客户同时填充信用代码
        var cc = document.getElementById('party' + side + 'CreditCode');
        if (cc && item.credit_code) cc.value = item.credit_code;
        hideSuggestions(side);
    }

    // HTML 转义：统一使用 app.js 全局 esc（P3-5：移除本地 escHtml 重复副本）

    // 暴露 selectParty 为全局函数，供「本公司」快捷按钮调用
    window._partySelect = selectParty;
})();

// ============================================================
// v2.46.0：快速新建客户/供应商（签约方强制关联档案——搜索无匹配时表单内快速建档）
// 复用现有 /ajax/customer/save、/ajax/supplier/save（自带查重 409 拦截与数据权限），
// 新建成功后回填到该侧（selectParty 锁定），客户/供应商仍入主库受查重/共享/归属治理。
// ============================================================
var __quickSide = null;
function openPartyQuick(side) {
    __quickSide = side;
    document.getElementById('quickName').value = '';
    document.getElementById('quickType').value = 'customer';
    document.getElementById('quickContact').value = '';
    document.getElementById('quickMobile').value = '';
    var m = document.getElementById('partyQuickModal');
    if (m && typeof bootstrap !== 'undefined') { bootstrap.Modal.getOrCreateInstance(m).show(); }
    setTimeout(function(){ var el = document.getElementById('quickName'); if (el) el.focus(); }, 100);
}
function savePartyQuick() {
    var name = (document.getElementById('quickName').value || '').trim();
    if (!name) { showToast('请输入名称', 'error'); return; }
    var type = document.getElementById('quickType').value;
    var fd = new FormData();
    fd.append('name', name);
    fd.append('contact_name', (document.getElementById('quickContact').value || '').trim());
    fd.append('contact_mobile', (document.getElementById('quickMobile').value || '').trim());
    $ajax(type === 'supplier' ? '/ajax/supplier/save' : '/ajax/customer/save', {method: 'POST', body: fd, loadingText: '新建中…'})
    .then(function (res) {
        if (res.code !== 0) {
            // 409 查重：提示去列表选择已有（含共享的）客户
            showToast(res.msg || '新建失败', 'error');
            return;
        }
        var m = document.getElementById('partyQuickModal');
        if (m && typeof bootstrap !== 'undefined') { bootstrap.Modal.getOrCreateInstance(m).hide(); }
        // v2.47.x：快速新建回填同时带出联系人/电话
        window._partySelect(__quickSide, {id: res.data.id, name: name, party_type: type,
            contact_name: document.getElementById('quickContact').value.trim(),
            contact_mobile: document.getElementById('quickMobile').value.trim()});
    })
    .catch(function () { showToast('新建失败，请重试', 'error'); });
}
// v2.46.0：校验某侧是否已关联客户/供应商档案（保存前对方侧强制）
function partySideLinked(side) {
    var cid = document.getElementById('party' + side + 'CustId');
    var sid = document.getElementById('party' + side + 'SupplierId');
    return !!(cid && (parseInt(cid.value, 10) > 0 || (sid && parseInt(sid.value, 10) > 0)));
}
window.__partySideLinked = partySideLinked;
