<?php
// 移动端共享布局（Phase 1 重构 1.7）：头部与尾部由 _head.php / _foot.php 统一输出
$title = '供应商';      // 页面标题，自动追加「 · 合同管理」
$tab = 'customer';     // 导航优化 Phase1：供应商经客户 Tab 内切换进入，高亮"客户"
$show_add_tab = !empty($can_create_supplier); $render_add_menu_here = false;
include __DIR__ . '/_head.php';
?>

<div class="m-nav">
  <a href="/m" class="back" aria-label="返回"><i class="bi bi-chevron-left"></i></a>
  <div class="title">供应商</div>
  <div class="right"><?=intval($total)?> 个</div>
</div>

<div class="m-page" id="page">
  <!-- 客户 / 供应商 两栏切换（v2.51.5：与「客户」页同款等宽分段，直角；供应商高亮） -->
  <div style="display:flex;background:#f2f3f5;padding:3px;margin:var(--m-gap) var(--m-gap) 4px;">
    <a href="/m/customers" style="flex:1;text-align:center;padding:8px 0;font-size:14px;color:var(--m-text-3)">客户</a>
    <a href="/m/suppliers" style="flex:1;text-align:center;padding:8px 0;font-size:14px;font-weight:600;background:#fff;color:var(--primary);box-shadow:0 1px 3px rgba(0,0,0,.08)">供应商</a>
  </div>

  <!-- 搜索 -->
  <div style="margin:var(--m-gap);">
    <div class="m-search-bar">
      <i class="bi bi-search" style="color:var(--m-text-3);font-size:16px"></i>
      <input id="kw" type="search" placeholder="搜索供应商名称 / 联系人 / 手机" value="<?=htmlspecialchars($keyword)?>" style="flex:1;border:none;outline:none;padding:12px 8px;font-size:15px;background:transparent;">
    </div>
  </div>

  <!-- 类型筛选（v2.52.2：行首新增「查看范围」切换，默认我的供应商、记忆上次选择） -->
  <div class="m-hide-scrollbar" style="display:flex;gap:8px;overflow-x:auto;padding:0 var(--m-gap) 4px;-webkit-overflow-scrolling:touch;">
    <?php if(!empty($can_scope_toggle)): ?>
    <a href="javascript:;" class="m-chip scope-chip <?=$scope==='me'?'active':''?>" data-scope="me">我的供应商</a>
    <a href="javascript:;" class="m-chip scope-chip <?=$scope==='all'?'active':''?>" data-scope="all">全部供应商</a>
    <?php endif; ?>
    <a href="javascript:;" class="m-chip <?=$type===''?'active':''?>" data-type="">全部</a>
    <?php foreach($types as $k=>$v): ?>
    <a href="javascript:;" class="m-chip <?=$type===$k?'active':''?>" data-type="<?=htmlspecialchars($k)?>"><?=htmlspecialchars($v)?></a>
    <?php endforeach; ?>
  </div>

  <!-- 列表 -->
  <div id="list">
    <?php if(empty($list)): ?>
      <div class="m-empty"><i class="bi bi-building"></i>暂无供应商</div>
    <?php else: foreach($list as $s):
        $st = $s['status'] ?? 1;
        $stCls = $st == 1 ? 'm-tag-ok' : 'm-tag-muted';
        $tp = $types[$s['type']] ?? $s['type'];
    ?>
      <a class="m-card" href="/m/supplier/<?=$s['id']?>" style="display:block">
        <div class="m-card-bd" style="padding-top:14px;padding-bottom:14px">
          <div class="m-row" style="border-bottom:none;padding:0">
            <div class="pic"><i class="bi bi-building"></i></div>
            <div class="main">
              <div class="t"><?=htmlspecialchars($s['name'])?></div>
              <div class="s"><?=htmlspecialchars($s['contact_name'] ?? '')?><?=!empty($s['contact_mobile'])?' · '.htmlspecialchars($s['contact_mobile']):''?></div>
            </div>
            <div class="aside"><span class="m-tag m-tag-info"><?=htmlspecialchars($tp)?></span></div>
          </div>
          <div style="display:flex;align-items:center;justify-content:space-between;margin-top:10px">
            <span class="m-tag <?=$stCls?>"><?=htmlspecialchars($statusMap[$st] ?? $st)?></span>
            <span style="font-size:12px;color:var(--m-text-3)">ID <?=intval($s['id'])?></span>
          </div>
        </div>
      </a>
    <?php endforeach; endif; ?>
  </div>

  <?php if(count($list) < intval($total)): ?>
    <div class="m-loadmore" id="loadmore">加载更多</div>
  <?php endif; ?>
</div>

<div class="m-toast" id="toast"></div>
<div class="m-loading" id="loading" style="display:none"><div class="m-spinner"></div></div>

<script>
window._types = <?=json_encode($types, JSON_UNESCAPED_UNICODE)?>;
window._status = <?=json_encode($statusMap, JSON_UNESCAPED_UNICODE)?>;
window._serverScope = <?=json_encode($scope, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;
(function(){
  var page = 1, loading = false, finished = <?=count($list) >= intval($total) ? 'true' : 'false';?>;
  var keyword = <?=json_encode($keyword)?>;
  var type = <?=json_encode($type)?>;
  // v2.52.2：查看范围（我的供应商/全部供应商）——取 localStorage 记忆，首次保持服务端默认（我的供应商）
  var SCOPE_KEY = 'supplier_list_scope';
  var scope = window._serverScope || 'me';
  var savedScope = null;
  try { savedScope = localStorage.getItem(SCOPE_KEY); } catch(e) {}
  if (savedScope) scope = savedScope;

  
  
  
  function tpTxt(s){ return (window._types && window._types[s.type]!=null)? window._types[s.type] : s.type; }
  function stCls(s){ return s==1?'m-tag-ok':'m-tag-muted'; }
  function stTxt(s){ return (window._status && window._status[s]!=null)? window._status[s] : s; }
  function cardHtml(s){
    return '<a class="m-card" href="/m/supplier/'+s.id+'" style="display:block"><div class="m-card-bd" style="padding-top:14px;padding-bottom:14px">'
      + '<div class="m-row" style="border-bottom:none;padding:0"><div class="pic"><i class="bi bi-building"></i></div>'
      + '<div class="main"><div class="t">'+esc(s.name)+'</div><div class="s">'+esc(s.contact_name||'')+(s.contact_mobile?(' · '+esc(s.contact_mobile)):'')+'</div></div>'
      + '<div class="aside"><span class="m-tag m-tag-info">'+esc(tpTxt(s))+'</span></div></div>'
      + '<div style="display:flex;align-items:center;justify-content:space-between;margin-top:10px">'
      + '<span class="m-tag '+stCls(s.status)+'">'+esc(stTxt(s.status))+'</span>'
      + '<span style="font-size:12px;color:var(--m-text-3)">ID '+Number(s.id)+'</span></div></div></a>';
  }

  function loadList(replace){
    showLoading(true);
    var url = '/m/suppliers?keyword=' + encodeURIComponent(keyword)
            + (type ? '&type=' + encodeURIComponent(type) : '');
    // v2.52.2：查看范围——「我的供应商」显式 owner_id=me；「全部供应商」显式 scope=all（否则服务端按默认我的判定）
    if(scope === 'me') url += '&owner_id=me';
    else url += '&scope=all';
    url += (replace ? '' : '&page=' + (page+1));
    fetch(url, {headers:{'X-Requested-With':'XMLHttpRequest'}})
      .then(function(r){ return r.json(); })
      .then(function(res){
        showLoading(false);
        if(res.code !== 0){ toast('加载失败'); return; }
        if(!replace) page++;
        var box = document.getElementById('list');
        if(replace) box.innerHTML = '';
        if(!res.data.length){
          finished = true;
          if(replace) box.innerHTML = '<div class="m-empty"><i class="bi bi-building"></i>暂无供应商</div>';
          var lm = document.getElementById('loadmore'); if(lm) lm.style.display='none';
          return;
        }
        res.data.forEach(function(s){ box.insertAdjacentHTML('beforeend', cardHtml(s)); });
        // v2.52.2：查看范围切换后重拉会改变列表口径，同步更新导航栏总数（首屏服务端渲染的计数不再可信）
        var nc = document.querySelector('.m-nav .right');
        if(nc) nc.textContent = res.total + ' 个';
        if(res.total && page * 20 >= res.total){ finished = true; var lm=document.getElementById('loadmore'); if(lm) lm.style.display='none'; }
        else { var lm=document.getElementById('loadmore'); if(lm) lm.style.display='block'; }
      })
      .catch(function(){ showLoading(false); toast('网络异常'); });
  }

  var lm = document.getElementById('loadmore');
  if(lm){ lm.addEventListener('click', function(){ if(loading||finished) return; loading=true; lm.textContent='加载中…'; loadList(false); loading=false; lm.textContent='加载更多'; }); }

  var timer=null;
  document.getElementById('kw').addEventListener('input', function(){
    keyword = this.value.trim(); page = 1; finished = false;
    clearTimeout(timer);
    timer = setTimeout(function(){ loadList(true); }, 300);
  });

  // 类型筛选（v2.52.2：选择器排除 scope-chip，避免查看范围切换被误绑定/误清高亮）
  document.querySelectorAll('.m-chip:not(.scope-chip)').forEach(function(chip){
    chip.addEventListener('click', function(){
      type = this.dataset.type || '';
      page = 1; finished = false;
      document.querySelectorAll('.m-chip:not(.scope-chip)').forEach(function(x){ x.classList.remove('active'); });
      this.classList.add('active');
      loadList(true);
    });
  });

  // ===== v2.52.2：查看范围切换（我的供应商/全部供应商） =====
  function syncScopeChips(){
    document.querySelectorAll('.scope-chip').forEach(function(x){
      x.classList.toggle('active', x.getAttribute('data-scope') === scope);
    });
  }
  document.querySelectorAll('.scope-chip').forEach(function(chip){
    chip.addEventListener('click', function(){
      var v = chip.getAttribute('data-scope');
      if(v === scope) return;
      scope = v;
      try{ localStorage.setItem(SCOPE_KEY, v); }catch(e){}
      syncScopeChips();
      page = 1; finished = false; loadList(true);
    });
  });
  syncScopeChips();
  // 记忆的查看范围与服务端渲染首屏不一致时，按记忆范围重拉
  if(scope !== (window._serverScope || 'me')){
    page = 1; finished = false; loadList(true);
  }
})();
</script>
<?php include __DIR__ . '/_foot.php'; ?>
