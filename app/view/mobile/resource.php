<?php
// +----------------------------------------------------------------------
// | 移动端资料库列表（查看型：仅阅读，上传/编辑/删除走 PC 端——v2.43.6 起移动端纯只读）
// | 复用 _head.php 共享壳；搜索/分类切换复用 PC 端 /resource/list AJAX
// +----------------------------------------------------------------------
$title = '资料库';
$tab = 'more';   // 导航优化 Phase1：资料库收进"更多"，底部高亮"更多"
include __DIR__ . '/_head.php';
?>

<div class="m-nav">
  <a href="/m" class="back" aria-label="返回"><i class="bi bi-chevron-left"></i></a>
  <div class="title">资料库</div>
  <div class="right"><?=intval($total)?> 条</div>
</div>

<div class="m-page" id="page">
  <!-- 搜索 -->
  <div style="margin:var(--m-gap);">
    <div class="m-search-bar" style="gap:8px">
      <i class="bi bi-search" style="color:var(--m-text-3);font-size:16px"></i>
      <input id="kw" type="search" placeholder="搜索资料标题" value="<?=htmlspecialchars($keyword)?>" style="flex:1;border:none;outline:none;padding:12px 8px;font-size:15px;background:transparent;">
    </div>
  </div>

  <!-- 分类 chips -->
  <div class="m-status-chips" style="display:flex;gap:8px;overflow-x:auto;padding:0 var(--m-gap) 4px;-webkit-overflow-scrolling:touch;">
    <a href="javascript:;" class="m-chip <?=$category===''?'active':''?>" data-cat="">全部</a>
    <?php foreach($categories as $code=>$name): ?>
    <a href="javascript:;" class="m-chip <?=$category===$code?'active':''?>" data-cat="<?=htmlspecialchars($code)?>"><?=htmlspecialchars($name)?></a>
    <?php endforeach; ?>
  </div>

  <!-- 列表 -->
  <div id="list">
    <?php if(empty($list)): ?>
      <div class="m-empty"><i class="bi bi-folder2-open"></i>暂无资料</div>
    <?php else: foreach($list as $r): ?>
      <a class="m-card" href="/m/resource/<?=intval($r['id'])?>" style="display:block">
        <div class="m-card-bd" style="padding-top:14px;padding-bottom:14px">
          <div class="m-row" style="border-bottom:none;padding:0">
            <div class="pic"><i class="bi bi-file-earmark-text" style="font-size:22px;color:var(--m-brand)"></i></div>
            <div class="main">
              <div class="t"><?=htmlspecialchars($r['title'] ?? '')?></div>
              <div class="s"><?=htmlspecialchars($r['category_name'] ?? '')?><?=!empty($r['company_name'])?' · '.htmlspecialchars($r['company_name']):''?></div>
            </div>
          </div>
        </div>
      </a>
    <?php endforeach; endif; ?>
  </div>

  <!-- 加载更多（后端分页，v2.39.x）：已加载条数 < 总数 时显示 -->
  <div id="loadMoreWrap" style="text-align:center;padding:12px 0;display:none">
    <button id="loadMoreBtn" type="button" class="m-btn m-btn-ghost" style="font-size:13px" onclick="loadMore()">加载更多</button>
  </div>
</div>

<script>
(function(){
  // esc 兜底：避免 app.js 加载顺序导致 window.esc 未定义
  function esc(s){ if(typeof window.esc === 'function') return window.esc(s); return String(s==null?'':s).replace(/[&<>"]/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]; }); }
  var kw = document.getElementById('kw');
  var box = document.getElementById('list');
  var curCat = <?=json_encode($category, JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;
  var timer = null;
  // ===== 分页状态（v2.39.x：后端分页 + 「加载更多」） =====
  var curPage = 1;
  var pageSize = 20;
  var total = <?=intval($total ?? 0)?>;
  var loading = false;
  var loadWrap = document.getElementById('loadMoreWrap');
  var loadBtn = document.getElementById('loadMoreBtn');
  function cardHtml(r){
    return '<a class="m-card" href="/m/resource/'+Number(r.id)+'" style="display:block"><div class="m-card-bd" style="padding-top:14px;padding-bottom:14px">'
      + '<div class="m-row" style="border-bottom:none;padding:0"><div class="pic"><i class="bi bi-file-earmark-text" style="font-size:22px;color:var(--m-brand)"></i></div>'
      + '<div class="main"><div class="t">'+esc(r.title||'')+'</div><div class="s">'+esc(r.category_name||'')+(r.company_name?(' · '+esc(r.company_name)):'')+'</div></div>'
      + '</div></div></a>';
  }
  // 刷新「加载更多」按钮显隐/文案：已加载条数 < 总数 时显示
  function updateLoadMore(){
    if (!loadWrap) return;
    var loaded = curPage * pageSize;
    var show = total > loaded;
    loadWrap.style.display = show ? 'block' : 'none';
    if (loadBtn) { loadBtn.disabled = loading; loadBtn.textContent = loading ? '加载中...' : '加载更多'; }
  }
  function doFetch(isAppend){
    var p = new URLSearchParams();
    p.set('keyword', kw.value);
    if(curCat) p.set('category', curCat);
    p.set('page', curPage);
    p.set('page_size', pageSize);
    loading = true;
    updateLoadMore();
    fetch('/ajax/resource/list?' + p.toString(), {headers:{'X-Requested-With':'XMLHttpRequest'}})
      .then(function(res){ return res.json(); })
      .then(function(data){
        loading = false;
        if(data.code !== 0){ if(!isAppend) box.innerHTML = '<div class="m-empty"><i class="bi bi-exclamation-circle"></i>加载失败</div>'; updateLoadMore(); return; }
        var list = (data.data && data.data.list) || [];
        total = (data.data && data.data.total) || total;
        if(!list.length){
          if (isAppend) { curPage -= 1; } // 已是最后一页，回退页码
          else { box.innerHTML = '<div class="m-empty"><i class="bi bi-folder2-open"></i>暂无资料</div>'; }
          updateLoadMore();
          return;
        }
        if (isAppend) { box.insertAdjacentHTML('beforeend', list.map(cardHtml).join('')); }
        else { box.innerHTML = list.map(cardHtml).join(''); }
        updateLoadMore();
      })
      .catch(function(){ loading = false; if(!isAppend) box.innerHTML = '<div class="m-empty"><i class="bi bi-wifi-off"></i>网络异常</div>'; updateLoadMore(); });
  }
  // 首次/搜索/筛选：重置到第 1 页并替换列表
  function fetchList(){
    curPage = 1;
    box.innerHTML = '<div class="m-empty"><i class="bi bi-hourglass-split"></i>加载中...</div>';
    doFetch(false);
  }
  // 「加载更多」：下一页追加（暴露到 window 供按钮 inline onclick 调用）
  function loadMore(){
    if (loading) return;
    curPage += 1;
    doFetch(true);
  }
  window.loadMore = loadMore;
  updateLoadMore(); // 初始按服务端渲染的 total 判断按钮显隐
  kw.addEventListener('input', function(){ clearTimeout(timer); timer = setTimeout(fetchList, 350); });
  document.querySelectorAll('.m-chip').forEach(function(chip){
    chip.addEventListener('click', function(){
      curCat = chip.dataset.cat || '';
      document.querySelectorAll('.m-chip').forEach(function(x){ x.classList.remove('active'); });
      chip.classList.add('active');
      fetchList();
    });
  });
})();
</script>
<?php $tab = 'more'; include __DIR__ . '/_foot.php'; ?>
