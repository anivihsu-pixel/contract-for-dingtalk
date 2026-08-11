<?php
// 移动端公海池（v2.38.2）：无人认领客户列表 + 认领操作
$title = '公海池';
$tab = 'customer';
include __DIR__ . '/_head.php';
?>

<div class="m-nav">
  <a href="/m" class="back" aria-label="返回"><i class="bi bi-chevron-left"></i></a>
  <div class="title">公海池</div>
  <div class="right"><?=intval($total)?> 个</div>
</div>

<div class="m-page" id="page">
  <!-- 主数据切换：客户 / 供应商 / 公海池 -->
  <div style="display:flex;gap:8px;overflow-x:auto;padding:var(--m-gap) var(--m-gap) 4px;-webkit-overflow-scrolling:touch;">
    <a href="/m/customers" class="m-chip">客户</a>
    <a href="/m/suppliers" class="m-chip">供应商</a>
    <a href="/m/customers/pool" class="m-chip active">公海池</a>
  </div>
  <div style="margin:var(--m-gap);">
    <div class="m-search-bar">
      <i class="bi bi-search" style="color:var(--m-text-3);font-size:16px"></i>
      <input id="kw" type="search" placeholder="搜索公海客户名称/联系人" value="<?=htmlspecialchars($keyword)?>" style="flex:1;border:none;outline:none;padding:12px 8px;font-size:15px;background:transparent;">
    </div>
  </div>

  <div id="list">
    <?php if(empty($list)): ?>
      <div class="m-empty"><i class="bi bi-water"></i>公海池暂无客户</div>
    <?php else: foreach($list as $c): ?>
      <div class="m-card" style="margin:0 var(--m-gap) var(--m-gap)">
        <div class="m-card-bd">
          <div class="m-row" style="border-bottom:none;padding:0">
            <div class="pic"><i class="bi bi-building"></i></div>
            <div class="main">
              <div class="t"><?=htmlspecialchars($c['name'])?></div>
              <div class="s"><?=htmlspecialchars($c['contact_name'] ?? '-')?> · <?=htmlspecialchars($c['contact_mobile'] ?? '-')?></div>
            </div>
            <div class="aside">
              <button class="m-btn m-btn-sm m-btn-ok" data-cid="<?=$c['id']?>" onclick="claimPool(<?=$c['id']?>)">认领</button>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; endif; ?>
  </div>

  <?php if(count($list) < intval($total)): ?>
    <div class="m-loadmore" id="loadmore">加载更多</div>
  <?php endif; ?>
</div>

<div class="m-toast" id="toast"></div>

<script>
(function(){
  var page = 1, loading = false, finished = <?=count($list) >= intval($total) ? 'true' : 'false';?>;
  var keyword = <?=json_encode($keyword, JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;
  var lm = document.getElementById('loadmore');

  var timer = null;
  document.getElementById('kw').addEventListener('input', function(){
    keyword = this.value.trim(); page = 1; finished = false;
    clearTimeout(timer);
    timer = setTimeout(function(){ loadPool(true); }, 300);
  });

  if(lm){ lm.addEventListener('click', function(){ if(!loading && !finished){ loading = true; lm.textContent = '加载中…'; loadPool(false); } }); }

  function loadPool(replace){
    fetch('/m/customers/pool?keyword=' + encodeURIComponent(keyword) + '&page=' + (replace ? 1 : page + 1), {headers:{'X-Requested-With':'XMLHttpRequest'}})
      .then(function(r){ return r.json(); })
      .then(function(res){
        loading = false;
        if(res.code !== 0 || !res.data.length){ finished = true; if(lm) lm.style.display = 'none'; return; }
        if(!replace) page++;
        var box = document.getElementById('list');
        if(replace) box.innerHTML = '';
        var h = '';
        res.data.forEach(function(c){
          h += '<div class="m-card" style="margin:0 var(--m-gap) var(--m-gap)">'
            + '<div class="m-card-bd"><div class="m-row" style="border-bottom:none;padding:0">'
            + '<div class="pic"><i class="bi bi-building"></i></div>'
            + '<div class="main"><div class="t">'+esc(c.name)+'</div>'
            + '<div class="s">'+(esc(c.contact_name)||'-')+' · '+(esc(c.contact_mobile)||'-')+'</div></div>'
            + '<div class="aside"><button class="m-btn m-btn-sm m-btn-ok" data-cid="'+c.id+'" onclick="claimPool('+c.id+')">认领</button></div>'
            + '</div></div></div>';
        });
        box.insertAdjacentHTML('beforeend', h);
        if(res.total && (replace ? res.data.length : page * 20) >= res.total){ finished = true; if(lm) lm.style.display = 'none'; }
        else if(lm) { lm.style.display = 'block'; lm.textContent = '加载更多'; }
      })
      .catch(function(){
        // 弱网/断网失败整改：恢复加载态并展示「加载失败，点击重试」（此前 fetch 无 catch，静默无提示）
        loading = false;
        if(lm){ lm.style.display = 'block'; lm.textContent = '加载更多'; }
        var box = document.getElementById('list');
        if(replace && box) box.innerHTML = '<div class="m-empty" style="cursor:pointer" onclick="loadPool(true)"><i class="bi bi-exclamation-triangle"></i>加载失败，点击重试</div>';
      });
  }
  window.loadPool = loadPool; // 供内联 onclick 重试调用（与 window.claimPool 同一暴露模式）

  window.claimPool = function(id){
    var t = document.cookie.match(/(?:^|; )csrf_token=([^;]*)/);
    var csrf = (t||[])[1]||'';
    fetch('/ajax/customer/' + id + '/claim', {method:'POST', headers:{'X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':csrf}})
      .then(function(r){ return r.json(); })
      .then(function(res){
        if(res.code === 0){ showToast ? showToast('认领成功','success') : toast('认领成功'); page = 1; finished = false; loadPool(true); }
        else { showToast ? showToast(res.msg,'error') : toast(res.msg||'认领失败'); }
      });
  };
})();
</script>
<?php include __DIR__ . '/_foot.php'; ?>
