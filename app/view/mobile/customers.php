<?php
// 移动端共享布局（Phase 1 重构 1.7）：头部与尾部由 _head.php / _foot.php 统一输出
$title = '客户';        // 页面标题，自动追加「 · 合同管理」
$tab = 'customer';      // 底部导航高亮 key：home/contract/customer/todo
$show_add_tab = !empty($can_create_customer); $render_add_menu_here = false;
include __DIR__ . '/_head.php';
?>

<div class="m-nav">
  <a href="/m" class="back" aria-label="返回"><i class="bi bi-chevron-left"></i></a>
  <div class="title">客户</div>
  <div class="right"><?=intval($total)?> 个</div>
</div>

<div class="m-page" id="page">
  <!-- 客户 / 供应商 两栏切换（v2.51.5：等宽分段，直角） -->
  <div style="display:flex;background:#f2f3f5;padding:3px;margin:var(--m-gap) var(--m-gap) 4px;">
    <a href="/m/customers" style="flex:1;text-align:center;padding:8px 0;font-size:14px;font-weight:600;background:#fff;color:var(--primary);box-shadow:0 1px 3px rgba(0,0,0,.08)">客户</a>
    <a href="/m/suppliers" style="flex:1;text-align:center;padding:8px 0;font-size:14px;color:var(--m-text-3)">供应商</a>
  </div>

  <!-- 搜索（与供应商页对齐：切换 → 搜索 → 筛选 → 列表） -->
  <div style="margin:var(--m-gap);">
    <div class="m-search-bar">
      <i class="bi bi-search" style="color:var(--m-text-3);font-size:16px"></i>
      <input id="kw" type="search" placeholder="搜索客户名称 / 联系人 / 手机" value="<?=htmlspecialchars($keyword)?>" style="flex:1;border:none;outline:none;padding:12px 8px;font-size:15px;background:transparent;">
    </div>
  </div>

  <!-- 生命周期筛选 chips（全部/客户/成交；gap 与供应商页类型筛选对齐） -->
  <div class="m-hide-scrollbar" style="display:flex;gap:8px;overflow-x:auto;padding:0 var(--m-gap) 4px;-webkit-overflow-scrolling:touch;" id="lcChips">
    <a href="javascript:;" class="m-chip active" data-lc="">全部</a>
    <a href="javascript:;" class="m-chip" data-lc="POTENTIAL">客户</a>
    <a href="javascript:;" class="m-chip" data-lc="ACTIVE">成交</a>
  </div>

  <!-- 列表 -->
  <div id="list">
    <?php if(empty($list)): ?>
      <div class="m-empty"><i class="bi bi-people"></i>暂无客户</div>
    <?php else: foreach($list as $c):
        $st = $c['status'] ?? 1;
        $stCls = $st == 1 ? 'm-tag-ok' : 'm-tag-muted';
        // 生命周期标签（与漏斗同色）
        $lc = $c['lifecycle_status'] ?? 'ACTIVE';
        $lcCls = ['POTENTIAL'=>'m-tag-info','ACTIVE'=>'m-tag-ok'][$lc] ?? 'm-tag-muted';
        $lcLabel = ($lifecycle_dict[$lc] ?? $lc);
    ?>
      <a class="m-card" href="/m/customer/<?=$c['id']?>" style="display:block">
        <div class="m-card-bd" style="padding-top:14px;padding-bottom:14px">
          <div class="m-row" style="border-bottom:none;padding:0">
            <div class="pic"><i class="bi bi-people"></i></div>
            <div class="main">
              <div class="t"><?=htmlspecialchars($c['name'])?></div>
              <div class="s"><?=htmlspecialchars($c['contact_name'] ?? '')?><?=!empty($c['contact_mobile'])?' · '.htmlspecialchars($c['contact_mobile']):''?></div>
            </div>
            <div class="aside"><span class="m-tag <?=$lcCls?>"><?=htmlspecialchars($lcLabel)?></span></div>
          </div>
          <div style="display:flex;align-items:center;margin-top:10px;gap:8px">
            <span class="m-tag <?=$stCls?>"><?=htmlspecialchars($statusMap[$st] ?? $st)?></span>
            <?php if(!empty($c['industry'])): ?><span class="m-tag m-tag-muted"><?=htmlspecialchars($industry_dict[$c['industry']] ?? $c['industry'])?></span><?php endif; ?>
            <span style="font-size:12px;color:var(--m-text-3);margin-left:auto">归属：<?=htmlspecialchars($owners[$c['owner_id']] ?? '未分配')?></span>
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
window._owners = <?=json_encode($owners, JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;
window._status = <?=json_encode($statusMap, JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;
window._lifecycleDict = <?=json_encode($lifecycle_dict ?? [], JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;
window._industryDict = <?=json_encode($industry_dict ?? [], JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;
(function(){
  var page = 1, loading = false, finished = <?=count($list) >= intval($total) ? 'true' : 'false';?>;
  var keyword = <?=json_encode($keyword, JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;
  // v2.38.9：生命周期筛选
  var lifecycle = '';

  function owner(c){ return (window._owners && window._owners[c.owner_id]!=null)? window._owners[c.owner_id] : '未分配'; }
  function stCls(s){ return s==1?'m-tag-ok':'m-tag-muted'; }
  function stTxt(s){ return (window._status && window._status[s]!=null)? window._status[s] : s; }
  function lcCls(lc){ return {POTENTIAL:'m-tag-info',ACTIVE:'m-tag-ok'}[lc]||'m-tag-muted'; }
  function lcTxt(lc){ return (window._lifecycleDict && window._lifecycleDict[lc])? window._lifecycleDict[lc] : lc; }
  // v2.40.0 P1-7：客户行业标签
  function indTxt(ind){ return (window._industryDict && window._industryDict[ind])? window._industryDict[ind] : ind; }
  function cardHtml(c){
    return '<a class="m-card" href="/m/customer/'+c.id+'" style="display:block"><div class="m-card-bd" style="padding-top:14px;padding-bottom:14px">'
      + '<div class="m-row" style="border-bottom:none;padding:0"><div class="pic"><i class="bi bi-people"></i></div>'
      + '<div class="main"><div class="t">'+esc(c.name)+'</div><div class="s">'+esc(c.contact_name||'')+(c.contact_mobile?(' · '+esc(c.contact_mobile)):'')+'</div></div>'
      + '<div class="aside"><span class="m-tag '+lcCls(c.lifecycle_status||'ACTIVE')+'">'+esc(lcTxt(c.lifecycle_status||'ACTIVE'))+'</span></div></div>'
      + '<div style="display:flex;align-items:center;margin-top:10px;gap:8px">'
      + '<span class="m-tag '+stCls(c.status)+'">'+esc(stTxt(c.status))+'</span>'
      + (c.industry ? '<span class="m-tag m-tag-muted">'+esc(indTxt(c.industry))+'</span>' : '')
      + '<span style="font-size:12px;color:var(--m-text-3);margin-left:auto">归属：'+esc(owner(c))+'</span></div></div></a>';
  }

  function loadList(replace){
    showLoading(true);
    var url = '/m/customers?keyword=' + encodeURIComponent(keyword) + (lifecycle ? '&lifecycle=' + encodeURIComponent(lifecycle) : '') + (replace ? '' : '&page=' + (page+1));
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
          if(replace) box.innerHTML = '<div class="m-empty"><i class="bi bi-people"></i>暂无客户</div>';
          var lm = document.getElementById('loadmore'); if(lm) lm.style.display='none';
          return;
        }
        res.data.forEach(function(c){ box.insertAdjacentHTML('beforeend', cardHtml(c)); });
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
  // v2.38.9：生命周期筛选 chips
  function setLcFilter(lc){
    document.querySelectorAll('#lcChips .m-chip').forEach(function(c){ c.classList.toggle('active', c.getAttribute('data-lc') === lc); });
    lifecycle = lc; page = 1; finished = false;
    loadList(true);
  }
  document.querySelectorAll('#lcChips .m-chip').forEach(function(chip){
    chip.addEventListener('click', function(){ setLcFilter(chip.getAttribute('data-lc') || ''); });
  });
})();
</script>
<?php include __DIR__ . '/_foot.php'; ?>
