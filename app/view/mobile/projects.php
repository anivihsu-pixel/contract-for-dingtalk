<?php
// 移动端共享布局（Phase 1 重构 1.7）：头部与尾部由 _head.php / _foot.php 统一输出
$title = '项目列表';   // 页面标题，自动追加「 · 合同管理」
$tab = 'more';     // 底部导航高亮：home/contract/customer/todo/more
include __DIR__ . '/_head.php';
?>

<div class="m-nav">
  <a href="/m" class="back" aria-label="返回"><i class="bi bi-chevron-left"></i></a>
  <div class="title">项目列表</div>
  <div class="right"><?=intval($total)?> 个</div>
</div>

<div class="m-page" id="page">
  <!-- 搜索 -->
  <div style="margin:var(--m-gap);">
    <div class="m-search-bar">
      <i class="bi bi-search" style="color:var(--m-text-3);font-size:16px"></i>
      <input id="kw" type="search" placeholder="搜索项目名称 / 编号 / 备注" value="<?=htmlspecialchars($keyword)?>" style="flex:1;border:none;outline:none;padding:12px 8px;font-size:15px;background:transparent;">
    </div>
  </div>

  <!-- 列表 -->
  <div id="list">
    <?php if(empty($list)): ?>
      <div class="m-empty"><i class="bi bi-folder2"></i>暂无项目</div>
    <?php else: foreach($list as $p):
        $st = $p['status'] ?? 'ACTIVE';
        $stTxt = $statusDict[$st] ?? $st;
        $stCls = $st === 'ARCHIVED' ? 'm-tag-muted' : ($st === 'DONE' ? 'm-tag-ok' : 'm-tag-info');
    ?>
      <a class="m-card" href="/m/project/<?=$p['id']?>" style="display:block">
        <div class="m-card-bd" style="padding-top:14px;padding-bottom:14px">
          <div class="m-row" style="border-bottom:none;padding:0">
            <div class="pic"><i class="bi bi-folder2"></i></div>
            <div class="main">
              <div class="t"><?=htmlspecialchars($p['name'] ?? '')?></div>
              <div class="s"><?=htmlspecialchars($p['code'] ?? '')?><?=!empty($p['customer_id'])?' · 关联客户':''?></div>
            </div>
            <div class="aside"><span class="m-tag <?=$stCls?>"><?=htmlspecialchars($stTxt)?></span></div>
          </div>
          <div style="display:flex;align-items:center;justify-content:space-between;margin-top:10px">
            <span class="amt pay-amt in">预算 ¥<?=number_format((float)($p['budget'] ?? 0), 0)?></span>
            <span style="font-size:12px;color:var(--m-text-3)"><?=intval($p['contract_count'] ?? 0)?> 份合同</span>
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
window._statusDict = <?=json_encode($statusDict, JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;
(function(){
  var page = 1, loading = false, finished = <?=count($list) >= intval($total) ? 'true' : 'false';?>;
  var keyword = <?=json_encode($keyword, JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;

  
  
  
  function stTxt(s){ return (window._statusDict && window._statusDict[s]!=null)? window._statusDict[s] : s; }
  function stCls(s){ return s==='ARCHIVED' ? 'm-tag-muted' : (s==='DONE' ? 'm-tag-ok' : 'm-tag-info'); }
  function cardHtml(p){
    return '<a class="m-card" href="/m/project/'+p.id+'" style="display:block"><div class="m-card-bd" style="padding-top:14px;padding-bottom:14px">'
      + '<div class="m-row" style="border-bottom:none;padding:0"><div class="pic"><i class="bi bi-folder2"></i></div>'
      + '<div class="main"><div class="t">'+esc(p.name||'')+'</div><div class="s">'+esc(p.code||'')+(p.customer_id?(' · 关联客户'):'')+'</div></div>'
      + '<div class="aside"><span class="m-tag '+stCls(p.status)+'">'+esc(stTxt(p.status))+'</span></div></div>'
      + '<div style="display:flex;align-items:center;justify-content:space-between;margin-top:10px">'
      + '<span class="amt pay-amt in">预算 ¥'+Number(p.budget||0).toLocaleString('zh-CN')+'</span>'
      + '<span style="font-size:12px;color:var(--m-text-3)">'+intval(p.contract_count||0)+' 份合同</span></div></div></a>';
  }
  function intval(n){ n = parseInt(n||0,10); return isNaN(n)?0:n; }

  function loadList(replace){
    showLoading(true);
    var url = '/m/projects?keyword=' + encodeURIComponent(keyword) + (replace ? '' : '&page=' + (page+1));
    fetch(url, {headers:{'X-Requested-With':'XMLHttpRequest'}})
      .then(function(r){ return r.json(); })
      .then(function(res){
        showLoading(false);
        if(res.code !== 0){ toast('加载失败'); return; }
        if(res.statusDict) window._statusDict = res.statusDict;
        if(!replace) page++;
        var box = document.getElementById('list');
        if(replace) box.innerHTML = '';
        if(!res.data.length){
          finished = true;
          if(replace) box.innerHTML = '<div class="m-empty"><i class="bi bi-folder2"></i>暂无项目</div>';
          var lm = document.getElementById('loadmore'); if(lm) lm.style.display='none';
          return;
        }
        res.data.forEach(function(p){ box.insertAdjacentHTML('beforeend', cardHtml(p)); });
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
})();
</script>
<?php $tab = 'more'; include __DIR__ . '/_foot.php'; ?>
