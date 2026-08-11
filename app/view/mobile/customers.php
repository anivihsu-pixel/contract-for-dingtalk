<?php
// 移动端共享布局（Phase 1 重构 1.7）：头部与尾部由 _head.php / _foot.php 统一输出
$title = '客户';        // 页面标题，自动追加「 · 合同管理」
$tab = 'customer';      // 底部导航高亮 key：home/contract/customer/todo
include __DIR__ . '/_head.php';
?>

<div class="m-nav">
  <a href="/m" class="back" aria-label="返回"><i class="bi bi-chevron-left"></i></a>
  <div class="title">客户</div>
  <div class="right"><?=intval($total)?> 个</div>
</div>

<div class="m-page" id="page">
  <!-- M10 客户生命周期漏斗看板 -->
  <?php
  $mFunnelStages = ['POTENTIAL','ACTIVE','INACTIVE'];
  $mFunnelData = $funnel['stages'] ?? ['POTENTIAL'=>0,'ACTIVE'=>0,'INACTIVE'=>0];
  $mFunnelAmts = $funnel['amounts'] ?? ['POTENTIAL'=>0,'ACTIVE'=>0,'INACTIVE'=>0];
  $mFunnelTotal = (int)($funnel['total'] ?? 0);
  $mFunnelMax = max(1, max(array_values($mFunnelData)));
  $mStageColors = ['POTENTIAL'=>'#0b5ed7','ACTIVE'=>'#07c160','INACTIVE'=>'#ff9f43'];
  ?>
  <div class="m-card" style="margin:var(--m-gap)" id="mLifecycleFunnel">
    <!-- 2026-08-03：移除「客户生命周期漏斗」标题——三阶段卡片自带阶段标签（客户/成交/公海），标题冗余 -->
    <div style="display:flex;gap:6px;overflow-x:auto;-webkit-overflow-scrolling:touch">
      <?php foreach($mFunnelStages as $st):
        $mCnt = (int)($mFunnelData[$st] ?? 0);
        $mAmt = (float)($mFunnelAmts[$st] ?? 0);
        $mPct = $mFunnelMax > 0 ? round($mCnt / $mFunnelMax * 100) : 0;
        $mLabel = ($lifecycle_dict[$st] ?? $st);
      ?>
      <!-- v2.38.11：漏斗阶段可点击筛选（与下方 lcChips 联动，对齐 PC 端交互） -->
      <div class="lc-funnel-stage" data-lc="<?=$st?>" style="flex:1;min-width:64px;text-align:center;padding:6px 4px;border-radius:10px;cursor:pointer;transition:background .15s">
        <div style="font-size:18px;font-weight:700;color:<?=$mStageColors[$st]?>"><?=$mCnt?></div>
        <div style="font-size:11px;color:var(--m-text-3);margin:2px 0 4px"><?=htmlspecialchars($mLabel)?></div>
        <!-- v2.40.0 P1-7：漏斗金额维度——该阶段客户销售合同额合计 -->
        <div style="font-size:10px;color:var(--m-text-3);margin-bottom:4px"><?=$mAmt > 0 ? '¥'.number_format($mAmt,0) : '—'?></div>
        <div style="height:6px;background:#f2f3f5;border-radius:3px;overflow:hidden"><div style="height:100%;width:<?=$mPct?>%;background:<?=$mStageColors[$st]?>"></div></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- 主数据切换：客户 / 供应商（供应商入口由原工作台快捷操作迁移至此，避免丢失） -->
  <div style="display:flex;gap:8px;overflow-x:auto;padding:var(--m-gap) var(--m-gap) 4px;-webkit-overflow-scrolling:touch;">
    <a href="/m/customers" class="m-chip active">客户</a>
    <a href="/m/suppliers" class="m-chip">供应商</a>
    <a href="/m/customers/pool" class="m-chip">公海池</a>
  </div>

  <!-- v2.38.9：生命周期筛选 chips（全部/客户/成交/公海） -->
  <div style="display:flex;gap:6px;overflow-x:auto;padding:0 var(--m-gap) 4px;-webkit-overflow-scrolling:touch;" id="lcChips">
    <a href="javascript:;" class="m-chip active" data-lc="">全部</a>
    <a href="javascript:;" class="m-chip" data-lc="POTENTIAL">客户</a>
    <a href="javascript:;" class="m-chip" data-lc="ACTIVE">成交</a>
    <a href="javascript:;" class="m-chip" data-lc="INACTIVE">公海</a>
  </div>

  <!-- 搜索 -->
  <div style="margin:var(--m-gap);">
    <div class="m-search-bar">
      <i class="bi bi-search" style="color:var(--m-text-3);font-size:16px"></i>
      <input id="kw" type="search" placeholder="搜索客户名称 / 联系人 / 手机" value="<?=htmlspecialchars($keyword)?>" style="flex:1;border:none;outline:none;padding:12px 8px;font-size:15px;background:transparent;">
    </div>
  </div>

  <!-- 列表 -->
  <div id="list">
    <?php if(empty($list)): ?>
      <div class="m-empty"><i class="bi bi-people"></i>暂无客户</div>
    <?php else: foreach($list as $c):
        $st = $c['status'] ?? 1;
        $stCls = $st == 1 ? 'm-tag-ok' : 'm-tag-muted';
        // v2.38.9：生命周期标签（与漏斗同色）
        $lc = $c['lifecycle_status'] ?? 'ACTIVE';
        $lcCls = ['POTENTIAL'=>'m-tag-info','ACTIVE'=>'m-tag-ok','INACTIVE'=>'m-tag-warn'][$lc] ?? 'm-tag-muted';
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
          <div style="display:flex;align-items:center;justify-content:space-between;margin-top:10px">
            <?php if(!empty($c['industry'])): ?><span class="m-tag m-tag-muted"><?=htmlspecialchars($industry_dict[$c['industry']] ?? $c['industry'])?></span><?php endif; ?>
            <span class="m-tag <?=$stCls?>"><?=htmlspecialchars($statusMap[$st] ?? $st)?></span>
            <span style="font-size:12px;color:var(--m-text-3)">归属：<?=htmlspecialchars($owners[$c['owner_id']] ?? '公海')?></span>
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

  function owner(c){ return (window._owners && window._owners[c.owner_id]!=null)? window._owners[c.owner_id] : '公海'; }
  function stCls(s){ return s==1?'m-tag-ok':'m-tag-muted'; }
  function stTxt(s){ return (window._status && window._status[s]!=null)? window._status[s] : s; }
  function lcCls(lc){ return {POTENTIAL:'m-tag-info',ACTIVE:'m-tag-ok',INACTIVE:'m-tag-warn'}[lc]||'m-tag-muted'; }
  function lcTxt(lc){ return (window._lifecycleDict && window._lifecycleDict[lc])? window._lifecycleDict[lc] : lc; }
  // v2.40.0 P1-7：客户行业标签
  function indTxt(ind){ return (window._industryDict && window._industryDict[ind])? window._industryDict[ind] : ind; }
  function cardHtml(c){
    return '<a class="m-card" href="/m/customer/'+c.id+'" style="display:block"><div class="m-card-bd" style="padding-top:14px;padding-bottom:14px">'
      + '<div class="m-row" style="border-bottom:none;padding:0"><div class="pic"><i class="bi bi-people"></i></div>'
      + '<div class="main"><div class="t">'+esc(c.name)+'</div><div class="s">'+esc(c.contact_name||'')+(c.contact_mobile?(' · '+esc(c.contact_mobile)):'')+'</div></div>'
      + '<div class="aside"><span class="m-tag '+lcCls(c.lifecycle_status||'ACTIVE')+'">'+esc(lcTxt(c.lifecycle_status||'ACTIVE'))+'</span></div></div>'
      + '<div style="display:flex;align-items:center;justify-content:space-between;margin-top:10px">'
      + (c.industry ? '<span class="m-tag m-tag-muted">'+esc(indTxt(c.industry))+'</span>' : '')
      + '<span class="m-tag '+stCls(c.status)+'">'+esc(stTxt(c.status))+'</span>'
      + '<span style="font-size:12px;color:var(--m-text-3)">归属：'+esc(owner(c))+'</span></div></div></a>';
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
  // v2.38.9：生命周期筛选 chips（v2.38.11：漏斗阶段点击与 chips 联动，统一走 setLcFilter）
  function setLcFilter(lc){
    document.querySelectorAll('#lcChips .m-chip').forEach(function(c){ c.classList.toggle('active', c.getAttribute('data-lc') === lc); });
    document.querySelectorAll('.lc-funnel-stage').forEach(function(s){ s.classList.toggle('active', s.getAttribute('data-lc') === lc); });
    lifecycle = lc; page = 1; finished = false;
    loadList(true);
  }
  document.querySelectorAll('#lcChips .m-chip').forEach(function(chip){
    chip.addEventListener('click', function(){ setLcFilter(chip.getAttribute('data-lc') || ''); });
  });
  document.querySelectorAll('.lc-funnel-stage').forEach(function(s){
    s.addEventListener('click', function(){ setLcFilter(s.getAttribute('data-lc')); });
  });
})();
</script>
<?php if(!empty($can_create_customer)): ?>
<a href="/m/customer/create" class="m-fab" aria-label="新建客户"><i class="bi bi-plus-lg"></i></a>
<?php endif; ?>
<?php include __DIR__ . '/_foot.php'; ?>
