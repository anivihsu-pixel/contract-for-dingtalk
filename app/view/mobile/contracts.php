<?php
// 移动端共享布局（Phase 1 重构 1.7）：头部与尾部由 _head.php / _foot.php 统一输出
$title = '合同';   // 页面标题，自动追加「 · 合同管理」
$tab = 'contract';     // 底部导航高亮：home/contract/customer/todo
$show_add_tab = !empty($can_create_contract); $render_add_menu_here = false;
include __DIR__ . '/_head.php';
?>

<div class="m-nav">
  <a href="/m" class="back" aria-label="返回"><i class="bi bi-chevron-left"></i></a>
  <div class="title">合同</div>
  <div class="right"><?=intval($total)?> 份</div>
</div>

<div class="m-page" id="page">
  <!-- 搜索 + 筛选入口（v2.40.1：筛选按钮改为「图标+文字」，带已选角标；关键词输入可一键清除） -->
  <div style="margin:var(--m-gap);">
    <div class="m-search-bar" style="gap:8px">
      <i class="bi bi-search" style="color:var(--m-text-3);font-size:16px"></i>
      <input id="kw" type="search" placeholder="搜索合同标题 / 编号 / 关键词" value="<?=htmlspecialchars($keyword)?>" style="flex:1;border:none;outline:none;padding:12px 8px;font-size:15px;background:transparent;">
      <button id="kwClear" type="button" style="display:none;border:none;background:transparent;color:var(--m-text-3);font-size:16px;padding:4px 2px;" aria-label="清除搜索">
        <i class="bi bi-x-circle"></i>
      </button>
      <button id="openFilter" type="button" class="m-filter-btn" style="position:relative;">
        <i class="bi bi-funnel"></i>筛选
        <span id="filterBadge" style="display:none;position:absolute;top:-6px;right:-6px;min-width:16px;height:16px;line-height:16px;padding:0 4px;background:var(--m-danger);color:#fff;border-radius:8px;font-size:11px;text-align:center;"></span>
      </button>
    </div>
  </div>

  <!-- v2.40.1：状态筛选收敛为高频 4 项（全部/草稿/待审批/执行中），低频状态移入高级筛选抽屉 -->
  <!-- v2.52.1：行首新增「查看范围」切换（我的合同/全部合同），默认我的合同、记忆上次选择；scope=me 时归属人筛选禁用 -->
  <div class="m-status-chips" style="display:flex;gap:8px;overflow-x:auto;padding:0 var(--m-gap) 4px;-webkit-overflow-scrolling:touch;">
    <?php if(!empty($can_scope_toggle)): ?>
    <a href="javascript:;" class="m-chip scope-chip <?=$scope==='me'?'active':''?>" data-scope="me">我的合同</a>
    <a href="javascript:;" class="m-chip scope-chip <?=$scope==='all'?'active':''?>" data-scope="all">全部合同</a>
    <?php endif; ?>
    <a href="javascript:;" class="m-chip <?=$status===''?'active':''?>" data-status="">全部</a>
    <?php foreach(['DRAFT','PENDING_APPROVAL','EXECUTING'] as $k): if (!isset($statusMap[$k])) continue; ?>
    <a href="javascript:;" class="m-chip <?=$status===$k?'active':''?>" data-status="<?=htmlspecialchars($k)?>"><?=htmlspecialchars($statusMap[$k])?></a>
    <?php endforeach; ?>
  </div>

  <!-- v2.40.1（方案 A）：已选筛选条件标签行，可单条删除 -->
  <div id="filterTags" style="display:none;gap:8px;flex-wrap:wrap;padding:0 var(--m-gap) 4px;"></div>

  <!-- 列表 -->
  <div id="list">
    <?php if(empty($list)): ?>
      <div class="m-empty"><i class="bi bi-file-earmark-text"></i>暂无合同</div>
    <?php else: foreach($list as $c):
        $st = $c['status'] ?? 'DRAFT';
        $stCls = $statusBadge[$st] ?? 'm-tag-muted';
        $isNonTrade = (($c['trade_attr'] ?? 1) == 0);
        $isIn = !$isNonTrade && ($c['direction'] ?? 'sales') === 'sales';
        $dirCls = $isNonTrade ? 'm-tag-muted' : ($isIn ? 'm-tag-recv' : 'm-tag-pay');
        $dirTxt = $isNonTrade ? '非交易' : ($isIn ? '应收' : '应付');
        $amtCls = $isNonTrade ? 'text-muted' : ($isIn ? 'in' : 'out');
    ?>
      <a class="m-card<?=$st==='DRAFT'?' is-draft':''?>" href="/m/contract/<?=$c['id']?>" style="display:block">
        <div class="m-card-bd" style="padding-top:14px;padding-bottom:14px">
          <div class="m-row" style="border-bottom:none;padding:0">
            <div class="pic"><i class="bi bi-file-earmark-text"></i></div>
            <div class="main">
              <div class="t"><?=htmlspecialchars($c['title'] ?? '')?></div>
              <div class="s"><?=htmlspecialchars($c['contract_no'] ?? '')?><?=!empty($c['owner_name'])?' · '.htmlspecialchars($c['owner_name']):''?></div>
            </div>
            <div class="aside"><span class="m-tag <?=$stCls?>"><?=htmlspecialchars($statusMap[$st] ?? $st)?></span></div>
          </div>
          <div style="display:flex;align-items:center;justify-content:space-between;margin-top:10px">
            <span style="display:flex;align-items:center;gap:6px">
              <span class="m-tag <?=$dirCls?>"><?=$dirTxt?></span>
              <span class="amt pay-amt <?=$amtCls?>">¥<?=number_format((float)($c['amount'] ?? 0), 0)?></span>
            </span>
            <span style="font-size:12px;color:var(--m-text-3)"><?=!empty($c['expiry_date'])?'到期 '.htmlspecialchars($c['expiry_date']):''?></span>
          </div>
        </div>
      </a>
    <?php endforeach; endif; ?>
  </div>

  <?php if(count($list) < intval($total)): ?>
    <div class="m-loadmore" id="loadmore">加载更多</div>
  <?php endif; ?>
</div>

<!-- 高级筛选底部抽屉（Phase 2.2）：复用 mobile.css 的 .m-sheet-mask / .m-sheet 体系（遮罩包面板，.show 控制滑入） -->
<div class="m-sheet-mask" id="filterMask">
  <div class="m-sheet" id="filterSheet">
    <div class="m-sheet-hd" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
      <span style="font-size:17px;font-weight:600;">高级筛选</span>
      <button type="button" id="filterReset" style="border:none;background:transparent;color:var(--m-danger);font-size:14px;">重置</button>
    </div>
    <div class="m-sheet-bd">
      <!-- v2.40.1：合同状态（低频状态：已驳回/已终止/已到期；高频状态由顶部 chips 承担） -->
      <div class="m-field">
        <label class="m-field-label" for="f_status">合同状态</label>
        <select id="f_status" class="m-select">
          <option value="">全部状态</option>
          <?php foreach(['REJECTED','TERMINATED','EXPIRED'] as $k): if (!isset($statusMap[$k])) continue; ?>
          <option value="<?=htmlspecialchars($k)?>"><?=htmlspecialchars($statusMap[$k])?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <!-- 收付款方向（单选 chips） -->
      <div class="m-field">
        <label class="m-field-label">收付款方向</label>
        <div style="display:flex;gap:8px">
          <a href="javascript:;" class="m-chip m-dir-chip" data-dir="">全部</a>
          <a href="javascript:;" class="m-chip m-dir-chip" data-dir="sales">销售（我方收款）</a>
          <a href="javascript:;" class="m-chip m-dir-chip" data-dir="purchase">采购（我方付款）</a>
        </div>
      </div>
      <!-- 业务类型 -->
      <div class="m-field">
        <label class="m-field-label" for="f_business_type">业务类型</label>
        <select id="f_business_type" class="m-select">
          <option value="">全部业务类型</option>
          <?php foreach($business_types as $code=>$name): ?>
          <option value="<?=htmlspecialchars($code)?>"><?=htmlspecialchars($name)?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <!-- 合同性质 -->
      <div class="m-field">
        <label class="m-field-label" for="f_trade">合同性质</label>
        <select id="f_trade" class="m-select">
          <option value="">全部性质</option>
          <option value="1">交易合同</option>
          <option value="0">非交易合同</option>
        </select>
      </div>
      <!-- 签约主体 -->
      <div class="m-field">
        <label class="m-field-label" for="f_company">签约主体</label>
        <select id="f_company" class="m-select">
          <option value="">全部签约主体</option>
          <?php foreach($companies as $co): ?>
          <option value="<?=intval($co['id'])?>"><?=htmlspecialchars($co['name'])?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <!-- v2.40.1：归属人改为搜索式选择（本地过滤数据范围内的用户；未选择默认全部归属人） -->
      <div class="m-field">
        <label class="m-field-label" for="f_owner">归属人</label>
        <div class="m-party-search">
          <input id="f_owner" type="text" class="m-input" placeholder="输入姓名搜索，留空为全部归属人" autocomplete="off">
          <div class="m-party-suggest" id="ownerSuggest"></div>
        </div>
      </div>
      <!-- v2.40.1：相对方改为搜索式选择（复用 /ajax/party/search 客户+供应商候选；未选择默认全部） -->
      <div class="m-field">
        <label class="m-field-label" for="f_party">相对方名称</label>
        <div class="m-party-search">
          <input id="f_party" type="text" class="m-input" placeholder="输入名称搜索，留空为全部" autocomplete="off">
          <div class="m-party-suggest" id="partySuggest"></div>
        </div>
      </div>
      <!-- 金额区间 -->
      <div class="m-field">
        <label class="m-field-label" for="f_amt_min">合同金额（元）</label>
        <div style="display:flex;align-items:center;gap:8px">
          <input id="f_amt_min" type="number" class="m-input" placeholder="最小" style="flex:1">
          <span style="color:var(--m-text-3)">—</span>
          <input id="f_amt_max" type="number" class="m-input" placeholder="最大" style="flex:1">
        </div>
      </div>
    </div>
    <div class="m-sheet-actions">
      <button type="button" id="filterCancel" class="m-btn m-btn-ghost">取消</button>
      <button type="button" id="filterApply" class="m-btn m-btn-brand">确定</button>
    </div>
  </div>
</div>

<div class="m-toast" id="toast"></div>
<div class="m-loading" id="loading" style="display:none"><div class="m-spinner"></div></div>

<script>
window._status = <?=json_encode($statusMap, JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;
window._statusBadge = <?=json_encode($statusBadge, JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;
window._filter = <?=json_encode($filter, JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;
// v2.52.1：服务端渲染首屏所用查看范围（me/all），供前端 localStorage 记忆覆盖后判断是否需要重拉
window._serverScope = <?=json_encode($scope, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;
// v2.40.1：字典供已选标签文本映射（类别/签约主体/归属人）
window._filterDict = <?=json_encode([
    'business_types' => $business_types,
    'companies'  => array_map(fn($co) => ['id' => intval($co['id']), 'name' => (string)($co['name'] ?? '')], $companies),
    'owners'     => array_map(fn($u) => ['id' => intval($u['id']), 'name' => (string)($u['name'] ?? '')], $owners),
], JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;
(function(){
  var page = 1, loading = false, finished = <?=count($list) >= intval($total) ? 'true' : 'false';?>;
  var keyword = <?=json_encode($keyword, JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;
  var filter  = window._filter || {};   // 高级筛选条件集合（含统一 status）
  var curDir  = filter.direction || ''; // 当前选中的收付款方向（单选）
  // v2.52.1：查看范围（我的合同/全部合同）——对象入口（项目/客户/部门）或显式指定归属人（owner_id）恒为全部；
  // 否则取 localStorage 记忆的上次选择，首次保持服务端默认（我的合同）
  var SCOPE_KEY = 'contract_list_scope';
  var scope = window._serverScope || 'all';
  var savedScope = null;
  try { savedScope = localStorage.getItem(SCOPE_KEY); } catch(e) {}
  if (!(filter.project_id || filter.customer_id || filter.dept_id) && !filter.owner_id && savedScope) {
    scope = savedScope;
  }
  // v2.40.1：高频状态由顶部 chips 表达，不重复计入角标/标签行
  var HIGH_STATUS = ['DRAFT', 'PENDING_APPROVAL', 'EXECUTING'];

  
  
  
  function stTxt(s){ return (window._status && window._status[s]!=null)? window._status[s] : s; }
  function stCls(s){ return (window._statusBadge && window._statusBadge[s]!=null)? window._statusBadge[s] : 'm-tag-muted'; }
  function dirCls(c){ if((c.trade_attr||1)==0) return 'm-tag-muted'; return (c.direction||'sales')==='sales'?'m-tag-recv':'m-tag-pay'; }
  function dirTxt(c){ if((c.trade_attr||1)==0) return '非交易'; return (c.direction||'sales')==='sales'?'应收':'应付'; }
  function amtCls(c){ if((c.trade_attr||1)==0) return 'text-muted'; return (c.direction||'sales')==='sales'?'in':'out'; }
  function cardHtml(c){
    return '<a class="m-card'+(c.status==='DRAFT'?' is-draft':'')+'" href="/m/contract/'+c.id+'" style="display:block"><div class="m-card-bd" style="padding-top:14px;padding-bottom:14px">'
      + '<div class="m-row" style="border-bottom:none;padding:0"><div class="pic"><i class="bi bi-file-earmark-text"></i></div>'
      + '<div class="main"><div class="t">'+esc(c.title||'')+'</div><div class="s">'+esc(c.contract_no||'')+(c.owner_name?(' · '+esc(c.owner_name)):'')+'</div></div>'
      + '<div class="aside"><span class="m-tag '+stCls(c.status)+'">'+esc(stTxt(c.status))+'</span></div></div>'
      + '<div style="display:flex;align-items:center;justify-content:space-between;margin-top:10px">'
      + '<span style="display:flex;align-items:center;gap:6px"><span class="m-tag '+dirCls(c)+'">'+dirTxt(c)+'</span>'
      + '<span class="amt pay-amt '+amtCls(c)+'">¥'+Number(c.amount||0).toLocaleString('zh-CN')+'</span></span>'
      + '<span style="font-size:12px;color:var(--m-text-3)">'+(c.expiry_date?('到期 '+esc(c.expiry_date)):'')+'</span></div></div></a>';
  }

  // 拼接筛选参数（keyword 必带，高级筛选非空才带；status 已统一并入 filter）
  function buildParams(replace){
    var p = new URLSearchParams();
    p.set('keyword', keyword);
    for (var k in filter){ if(filter.hasOwnProperty(k) && filter[k] !== '') p.set(k, filter[k]); }
    // v2.52.1：查看范围——「我的合同」时归属人固定为本人（覆盖 filter 残留）；「全部合同」且无对象入口/归属人
    // 时显式带 scope=all，避免服务端按「独立进入默认我的合同」把重拉请求再判定为我的合同
    if(scope === 'me') p.set('owner_id', 'me');
    else if(!(filter.project_id || filter.customer_id || filter.dept_id || filter.owner_id)) p.set('scope', 'all');
    if(!replace) p.set('page', page + 1);
    return p.toString();
  }

  function loadList(replace){
    showLoading(true);
    return fetch('/m/contracts?' + buildParams(replace), {headers:{'X-Requested-With':'XMLHttpRequest'}})
      .then(function(r){ return r.json(); })
      .then(function(res){
        showLoading(false);
        if(res.code !== 0){ toast('加载失败'); return; }
        if(res.statusMap) window._status = res.statusMap;
        if(res.statusBadge) window._statusBadge = res.statusBadge;
        if(!replace) page++;
        var box = document.getElementById('list');
        if(replace) box.innerHTML = '';
        if(!res.data.length){
          finished = true;
          if(replace) box.innerHTML = '<div class="m-empty"><i class="bi bi-file-earmark-text"></i>暂无合同</div>';
          var lm = document.getElementById('loadmore'); if(lm) lm.style.display='none';
          return;
        }
        res.data.forEach(function(c){ box.insertAdjacentHTML('beforeend', cardHtml(c)); });
        // v2.52.1：查看范围切换后重拉会改变列表口径，同步更新导航栏合同总数（首屏服务端渲染的计数不再可信）
        var nc = document.querySelector('.m-nav .right');
        if(nc) nc.textContent = res.total + ' 份';
        if(res.total && page * 20 >= res.total){ finished = true; var lm=document.getElementById('loadmore'); if(lm) lm.style.display='none'; }
        else { var lm=document.getElementById('loadmore'); if(lm) lm.style.display='block'; }
      })
      .catch(function(){ showLoading(false); toast('网络异常'); });
  }

  /* ===== 高级筛选抽屉交互 ===== */
  var mask = document.getElementById('filterMask');
  var sheet = document.getElementById('filterSheet');
  var badge = document.getElementById('filterBadge');
  var tagsBox = document.getElementById('filterTags');
  // v2.40.1：搜索式选择器的当前选中态（未选择=null，默认全部归属人/相对方）
  var ownerPick = null, partyPick = null;

  function setDir(d){ curDir = d; document.querySelectorAll('.m-dir-chip').forEach(function(x){ x.classList.toggle('active', x.dataset.dir === d); }); }
  // v2.40.1：抽屉「合同状态」与顶部高频 chips 共用 filter.status，打开时回填
  function syncStatusSelect(){
    var s = filter.status || '';
    document.getElementById('f_status').value = (HIGH_STATUS.indexOf(s) >= 0) ? '' : s;
  }
  // 依据当前 filter 回填归属人/相对方搜索框（显示名称）
  function syncSearchInputs(){
    var ownerInput = document.getElementById('f_owner');
    var partyInput = document.getElementById('f_party');
    if(ownerInput){
      var uid = filter.owner_id != null ? String(filter.owner_id) : '';
      var u = (window._filterDict.owners || []).find(function(x){ return String(x.id) === uid; });
      ownerPick = u ? {id: u.id, name: u.name} : null;
      ownerInput.value = ownerPick ? ownerPick.name : '';
    }
    if(partyInput){
      partyPick = filter.party_name || null;
      partyInput.value = partyPick || '';
    }
  }
  function openSheet(){
    // 回填当前筛选值到表单
    setDir(filter.direction || '');
    syncStatusSelect();
    syncSearchInputs();
    document.getElementById('f_business_type').value = filter.business_type || '';
    document.getElementById('f_trade').value   = (filter.trade_attr != null) ? String(filter.trade_attr) : '';
    document.getElementById('f_company').value = (filter.our_company_id != null) ? String(filter.our_company_id) : '';
    document.getElementById('f_amt_min').value = filter.amount_min || '';
    document.getElementById('f_amt_max').value = filter.amount_max || '';
    mask.classList.add('show');
  }
  function closeSheet(){ mask.classList.remove('show'); }
  function collectFilter(){
    filter.direction      = curDir;
    // v2.40.1：低频状态由抽屉选择；抽屉未选时保留顶部 chips 的高频状态，两者皆无才删除
    var st = document.getElementById('f_status').value;
    if(st) filter.status = st;
    else if(HIGH_STATUS.indexOf(filter.status || '') < 0) delete filter.status;
    filter.business_type  = document.getElementById('f_business_type').value;
    filter.trade_attr     = document.getElementById('f_trade').value;
    filter.our_company_id = document.getElementById('f_company').value;
    // v2.40.1：归属人从搜索式选择器的选中态取值；输入框被清空/改动时视为未选择（默认全部）
    // v2.52.1：查看范围「我的合同」时归属人固定为本人，跳过归属人收集（保留原值，切回全部后原筛选仍生效）
    if(scope !== 'me'){
      var ownerInputVal = document.getElementById('f_owner').value.trim();
      if(ownerPick && ownerInputVal === ownerPick.name) filter.owner_id = ownerPick.id;
      else { delete filter.owner_id; ownerPick = null; }
    }
    var pv = document.getElementById('f_party').value.trim();
    if(pv) filter.party_name = pv; else delete filter.party_name;
    filter.amount_min     = document.getElementById('f_amt_min').value.trim();
    filter.amount_max     = document.getElementById('f_amt_max').value.trim();
    // 清理空值，保持 filter 干净
    Object.keys(filter).forEach(function(k){ if(filter[k] === '' || filter[k] == null) delete filter[k]; });
  }
  // v2.40.1：角标统计已选条件数（keyword 由搜索框独立表达，不计入）
  function updateBadge(){
    var n = 0;
    for(var k in filter){
      if(!filter.hasOwnProperty(k) || k === 'keyword' || filter[k] === '') continue;
      if(k === 'owner_id' && scope === 'me') continue;  // v2.52.1：我的合同视图下归属人由查看范围表达，不重复计数
      n++;
    }
    if(n > 0){ badge.style.display = 'block'; badge.textContent = n; } else { badge.style.display = 'none'; }
  }
  // v2.40.1（方案 A）：渲染已选条件标签行；keyword/高频状态不重复展示（chips 已表达）
  function filterTagText(k, v){
    var d = window._filterDict || {};
    switch(k){
      case 'direction': return v === 'sales' ? '方向:销售' : (v === 'purchase' ? '方向:采购' : v);
      case 'status':    return '状态:' + ((window._status && window._status[v]) ? window._status[v] : v);
      case 'business_type': return '业务类型:' + ((d.business_types && d.business_types[v]) ? d.business_types[v] : v);
      case 'trade_attr':return v === '1' ? '性质:交易' : (v === '0' ? '性质:非交易' : v);
      case 'our_company_id': { var co = (d.companies || []).find(function(x){ return String(x.id) === String(v); }); return '主体:' + (co ? co.name : v); }
      case 'owner_id':  { if(String(v) === 'me') return '归属:我'; var u = (d.owners || []).find(function(x){ return String(x.id) === String(v); }); return '归属:' + (u ? u.name : v); }
      case 'party_name':return '相对方:' + v;
      case 'amount_min':return '金额≥' + v;
      case 'amount_max':return '金额≤' + v;
      default: return k + ':' + v;
    }
  }
  function renderTags(){
    var html = '';
    for (var k in filter){
      if(!filter.hasOwnProperty(k)) continue;
      if(k === 'keyword' || filter[k] === '' || filter[k] == null) continue;
      if(k === 'status' && HIGH_STATUS.indexOf(filter[k]) >= 0) continue; // 高频状态由顶部 chips 表达
      if(k === 'owner_id' && scope === 'me') continue;  // v2.52.1：我的合同视图下归属人由查看范围表达，不展示重复标签
      html += '<span class="m-filter-tag" data-fk="' + k + '"><span class="m-filter-tag-txt">' + esc(filterTagText(k, filter[k])) + '</span><i class="bi bi-x"></i></span>';
    }
    tagsBox.style.display = html ? 'flex' : 'none';
    tagsBox.innerHTML = html;
  }
  // 标签行删除：事件委托，避免 innerHTML 替换后事件丢失
  tagsBox.addEventListener('click', function(e){
    var tag = e.target.closest('.m-filter-tag');
    if(!tag) return;
    var k = tag.dataset.fk;
    delete filter[k];
    // v2.40.1：删除归属人/相对方标签时同步清空搜索式选择器
    if(k === 'owner_id'){ ownerPick = null; document.getElementById('f_owner').value = ''; }
    if(k === 'party_name'){ partyPick = null; document.getElementById('f_party').value = ''; }
    updateBadge(); renderTags();
    if(k === 'status') syncStatusSelect();
    page = 1; finished = false; loadList(true);
  });
  function applyFilter(){
    collectFilter(); updateBadge(); renderTags(); closeSheet();
    syncTopChips();
    page = 1; finished = false; loadList(true);
  }
  function resetFilter(){
    filter = {}; curDir = ''; ownerPick = null; partyPick = null;
    updateBadge(); renderTags(); syncStatusSelect(); syncTopChips(); syncSearchInputs(); closeSheet();
    page = 1; finished = false; loadList(true);
  }

  document.getElementById('openFilter').addEventListener('click', openSheet);
  // v2.40.1：修复「点选筛选项即关闭弹窗」——仅点击遮罩本体才关闭，sheet 内控件不冒泡关闭
  mask.addEventListener('click', function(e){ if(e.target === mask) closeSheet(); });
  document.getElementById('filterCancel').addEventListener('click', closeSheet);
  document.getElementById('filterApply').addEventListener('click', applyFilter);
  document.getElementById('filterReset').addEventListener('click', resetFilter);
  document.querySelectorAll('.m-dir-chip').forEach(function(chip){
    chip.addEventListener('click', function(){ setDir(this.dataset.dir); });
  });
  // v2.40.1：归属人搜索式选择（本地过滤数据范围内的用户；未选择默认全部）
  (function(){
    var input = document.getElementById('f_owner');
    var sugg  = document.getElementById('ownerSuggest');
    if(!input || !sugg) return;
    var owners = (window._filterDict.owners || []);
    input.addEventListener('input', function(){
      var q = this.value.trim().toLowerCase();
      var h = '';
      if(q){
        owners.forEach(function(u){
          if((u.name || '').toLowerCase().indexOf(q) >= 0){
            h += '<div class="m-party-item" data-uid="' + u.id + '" data-uname="' + esc(u.name) + '">' + esc(u.name) + '</div>';
          }
        });
      }
      sugg.innerHTML = h;
      sugg.style.display = h ? 'block' : 'none';
    });
    // 候选点击（mousedown 防止 input blur 抢先关闭）
    sugg.addEventListener('mousedown', function(e){
      var item = e.target.closest('.m-party-item');
      if(!item) return;
      e.preventDefault();
      ownerPick = {id: parseInt(item.dataset.uid, 10), name: item.dataset.uname};
      input.value = ownerPick.name;
      sugg.style.display = 'none';
    });
    // 失焦：若输入与已选项不符视为未选择（默认全部）
    input.addEventListener('blur', function(){
      if(!ownerPick || this.value.trim() !== ownerPick.name){ ownerPick = null; }
      setTimeout(function(){ sugg.style.display = 'none'; }, 120);
    });
  })();
  // v2.40.1：相对方搜索式选择（复用 /ajax/party/search 客户+供应商候选；未选择默认全部）
  (function(){
    var input = document.getElementById('f_party');
    var sugg  = document.getElementById('partySuggest');
    if(!input || !sugg) return;
    var timer = null;
    input.addEventListener('input', function(){
      var q = this.value.trim();
      if(q.length < 1){ sugg.style.display = 'none'; sugg.innerHTML = ''; partyPick = null; return; }
      clearTimeout(timer);
      timer = setTimeout(function(){
        fetch('/ajax/party/search?q=' + encodeURIComponent(q), {headers:{'X-Requested-With':'XMLHttpRequest'}})
        .then(function(r){ return r.json(); })
        .then(function(res){
          if(res.code !== 0 || !res.data || !res.data.length){ sugg.style.display = 'none'; sugg.innerHTML = ''; return; }
          var h = '';
          res.data.forEach(function(p, i){
            var tag = p.type_name ? '<span class="m-party-tag">' + esc(p.type_name) + '</span>' : '';
            h += '<div class="m-party-item" data-idx="' + i + '">' + esc(p.name) + tag + '</div>';
          });
          sugg.innerHTML = h;
          sugg.style.display = 'block';
          sugg._list = res.data;
        })
        .catch(function(){ sugg.style.display = 'none'; });
      }, 200);
    });
    sugg.addEventListener('mousedown', function(e){
      var item = e.target.closest('.m-party-item');
      if(!item) return;
      e.preventDefault();
      var p = sugg._list[parseInt(item.dataset.idx, 10)];
      partyPick = p.name;
      input.value = p.name;
      sugg.style.display = 'none';
    });
    input.addEventListener('blur', function(){
      // 保留手输文本作为模糊筛选，无需强制匹配候选
      partyPick = this.value.trim() || null;
      setTimeout(function(){ sugg.style.display = 'none'; }, 120);
    });
  })();
  /* ===== v2.52.1：查看范围切换（我的合同/全部合同） ===== */
  function syncScopeChips(){
    document.querySelectorAll('.scope-chip').forEach(function(x){
      x.classList.toggle('active', x.dataset.scope === scope);
    });
  }
  // scope=me 时归属人选择器禁用（归属固定为本人），切回全部时恢复可用
  function syncOwnerDisabled(){
    var o = document.getElementById('f_owner');
    if(!o) return;
    o.disabled = (scope === 'me');
    o.placeholder = (scope === 'me') ? '查看范围为「我的合同」，归属人不可用' : '输入姓名搜索，留空为全部归属人';
  }
  document.querySelectorAll('.scope-chip').forEach(function(chip){
    chip.addEventListener('click', function(){
      var v = this.dataset.scope;
      if(v === scope) return;
      scope = v;
      try{ localStorage.setItem(SCOPE_KEY, v); }catch(e){}
      syncScopeChips(); syncOwnerDisabled(); updateBadge(); renderTags();
      page = 1; finished = false; loadList(true);
    });
  });
  syncScopeChips(); syncOwnerDisabled();
  // 记忆的查看范围与服务端渲染首屏不一致时，按记忆范围重拉（对象入口不适用记忆，后端恒为全部）
  if(scope !== (window._serverScope || 'all')){
    page = 1; finished = false; loadList(true);
  }
  updateBadge(); renderTags(); // 初始根据已回显的筛选渲染角标与标签行

  /* ===== 已有交互：加载更多 / 关键词 / 状态 chips ===== */
  var lm = document.getElementById('loadmore');
  if(lm){ lm.addEventListener('click', function(){
    if(loading||finished) return;
    loading=true; lm.textContent='加载中…';
    // 2026-08-07：loading 复位与按钮文案恢复延后到请求结束（then/catch），
    // 避免异步 loadList 返回前同步复位导致防重锁失效、连续点击重复请求
    loadList(false).then(function(){ loading=false; lm.textContent='加载更多'; }).catch(function(){ loading=false; lm.textContent='加载更多'; });
  }); }

  var timer=null;
  var kwInput = document.getElementById('kw');
  var kwClear = document.getElementById('kwClear');
  // v2.40.1：关键词输入时联动显示「清除」按钮
  function syncKwClear(){ kwClear.style.display = kwInput.value ? 'inline-flex' : 'none'; }
  kwInput.addEventListener('input', function(){
    keyword = this.value.trim(); page = 1; finished = false; syncKwClear();
    clearTimeout(timer);
    timer = setTimeout(function(){ loadList(true); }, 300);
  });
  kwClear.addEventListener('click', function(){
    kwInput.value = ''; keyword = ''; page = 1; finished = false; syncKwClear();
    loadList(true);
  });

  // v2.40.1：顶部高频状态 chips 与抽屉 f_status 共用 filter.status；点击后同步高亮并清除抽屉选择
  function syncTopChips(){
    var s = filter.status || '';
    // v2.52.1：排除 scope-chip（查看范围切换），避免其 active 高亮被状态 chips 逻辑清除
    document.querySelectorAll('.m-status-chips .m-chip:not(.scope-chip)').forEach(function(x){
      x.classList.toggle('active', x.dataset.status === s);
    });
  }
  // 仅绑定顶部状态 chips（避免与抽屉内方向 chips 冲突，方向 chips 用 .m-dir-chip 单独处理；scope-chip 单独绑定）
  document.querySelectorAll('.m-status-chips .m-chip:not(.scope-chip)').forEach(function(chip){
    chip.addEventListener('click', function(){
      var s = this.dataset.status || '';
      if(s) filter.status = s; else delete filter.status;
      syncTopChips(); syncStatusSelect();
      page = 1; finished = false;
      updateBadge(); renderTags();
      loadList(true);
    });
  });
  syncTopChips(); // 初始按已回显状态高亮顶部 chips
})();
</script>
<?php $tab = 'contract'; include __DIR__ . '/_foot.php'; ?>
