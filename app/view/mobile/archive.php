<?php
// 移动端共享布局（Phase 1 重构 1.7）：头部与尾部由 _head.php / _foot.php 统一输出
$title = '归档合同';   // 页面标题，自动追加「 · 合同管理」
$tab = 'contract';     // 导航优化 Phase1：归档合同属合同子集，高亮"合同"（兼消冗余）
include __DIR__ . '/_head.php';
?>

<div class="m-nav">
  <a href="/m" class="back" aria-label="返回"><i class="bi bi-chevron-left"></i></a>
  <div class="title">归档合同</div>
  <div class="right"><?=intval($total)?> 份</div>
</div>

<div class="m-page" id="page">
  <!-- 搜索 -->
  <div style="margin:var(--m-gap);">
    <div class="m-search-bar">
      <i class="bi bi-search" style="color:var(--m-text-3);font-size:16px"></i>
      <input id="kw" type="search" placeholder="搜索合同标题 / 编号" value="<?=htmlspecialchars($keyword)?>" style="flex:1;border:none;outline:none;padding:12px 8px;font-size:15px;background:transparent;">
    </div>
  </div>

  <!-- 列表 -->
  <div id="list">
    <?php if(empty($list)): ?>
      <div class="m-empty"><i class="bi bi-archive"></i>暂无归档合同</div>
    <?php else: foreach($list as $c):
        $isNonTrade = (($c['trade_attr'] ?? 1) == 0);
        $isIn = !$isNonTrade && ($c['direction'] ?? 'sales') === 'sales';
        $amtCls = $isNonTrade ? 'text-muted' : ($isIn ? 'in' : 'out');
        $amtTxt = $isNonTrade ? '非交易' : ($isIn ? '应收' : '应付');
    ?>
      <a class="m-card" href="/m/contract/<?=$c['id']?>" style="display:block">
        <div class="m-card-bd" style="padding-top:14px;padding-bottom:14px">
          <div class="m-row" style="border-bottom:none;padding:0">
            <div class="pic"><i class="bi bi-archive"></i></div>
            <div class="main">
              <div class="t"><?=htmlspecialchars($c['title'] ?? '')?></div>
              <div class="s"><?=htmlspecialchars($c['contract_no'] ?? '')?><?=!empty($c['owner_name'])?' · '.htmlspecialchars($c['owner_name']):''?></div>
            </div>
            <div class="aside"><span class="m-tag m-tag-muted">已归档</span></div>
          </div>
          <div style="display:flex;align-items:center;justify-content:space-between;margin-top:10px">
            <span style="display:flex;align-items:center;gap:6px">
              <span class="m-tag <?=$isNonTrade?'m-tag-muted':($isIn?'m-tag-recv':'m-tag-pay')?>"><?=$amtTxt?></span>
              <span class="amt pay-amt <?=$amtCls?>">¥<?=number_format((float)($c['amount'] ?? 0), 0)?></span>
            </span>
            <span style="display:flex;align-items:center;gap:10px">
              <?php if(!empty($c['expiry_date'])): ?><span style="font-size:12px;color:var(--m-text-3)">到期 <?=htmlspecialchars($c['expiry_date'])?></span><?php endif; ?>
              <?php if ($can_archive ?? false): ?>
              <span role="button" style="font-size:12px;color:var(--m-brand);cursor:pointer" onclick="event.preventDefault();event.stopPropagation();undoArchive(<?=intval($c['id'])?>)">取消归档</span>
              <?php endif; ?>
            </span>
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
(function(){
  var page = 1, loading = false, finished = <?=count($list) >= intval($total) ? 'true' : 'false';?>;
  var keyword = <?=json_encode($keyword, JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;
  var canArchive = <?=($can_archive ?? false) ? 'true' : 'false'?>;  // Phase 2.5：归档操作权限

  
  
  
  
  function amtCls(c){ if((c.trade_attr||1)==0) return 'text-muted'; return (c.direction||'sales')==='sales'?'in':'out'; }
  function amtTxt(c){ if((c.trade_attr||1)==0) return '非交易'; return (c.direction||'sales')==='sales'?'应收':'应付'; }
  // v2.38.14：方向标签（对齐合同列表 dirCls/dirTxt）
  function dirCls(c){ if((c.trade_attr||1)==0) return 'm-tag-muted'; return (c.direction||'sales')==='sales'?'m-tag-recv':'m-tag-pay'; }
  function dirTxt(c){ if((c.trade_attr||1)==0) return '非交易'; return (c.direction||'sales')==='sales'?'应收':'应付'; }
  function cardHtml(c){
    var act = '<span style="display:flex;align-items:center;gap:10px">'
      + (c.expiry_date ? ('<span style="font-size:12px;color:var(--m-text-3)">到期 '+esc(c.expiry_date)+'</span>') : '')
      + (canArchive ? '<span role="button" style="font-size:12px;color:var(--m-brand);cursor:pointer" onclick="event.preventDefault();event.stopPropagation();undoArchive('+c.id+')">取消归档</span>' : '')
      + '</span>';
    return '<a class="m-card" href="/m/contract/'+c.id+'" style="display:block"><div class="m-card-bd" style="padding-top:14px;padding-bottom:14px">'
      + '<div class="m-row" style="border-bottom:none;padding:0"><div class="pic"><i class="bi bi-archive"></i></div>'
      + '<div class="main"><div class="t">'+esc(c.title||'')+'</div><div class="s">'+esc(c.contract_no||'')+(c.owner_name?(' · '+esc(c.owner_name)):'')+'</div></div>'
      + '<div class="aside"><span class="m-tag m-tag-muted">已归档</span></div></div>'
      + '<div style="display:flex;align-items:center;justify-content:space-between;margin-top:10px">'
      + '<span style="display:flex;align-items:center;gap:6px"><span class="m-tag '+dirCls(c)+'">'+dirTxt(c)+'</span>'
      + '<span class="amt pay-amt '+amtCls(c)+'">¥'+Number(c.amount||0).toLocaleString('zh-CN')+'</span></span>'
      + act + '</div></div></a>';
  }

  // Phase 2.5：取消归档
  window.undoArchive = function(id){
    mConfirm('确认取消归档？合同将恢复为「执行中」状态。', function(){
      showLoading(true);
    fetch('/ajax/archive/'+id+'/undo', {
      method:'POST',
      headers:{'X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':csrfToken()}
    })
      .then(function(r){ return r.json(); })
      .then(function(res){
        showLoading(false);
        if(res.code === 0){ toast('已取消归档'); setTimeout(function(){ location.reload(); }, 800); }
        else { toast(res.msg || '操作失败'); }
      })
      .catch(function(){ showLoading(false); toast('网络异常'); });
    });
  };

  function loadList(replace){
    showLoading(true);
    var url = '/m/archive?keyword=' + encodeURIComponent(keyword) + (replace ? '' : '&page=' + (page+1));
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
          if(replace) box.innerHTML = '<div class="m-empty"><i class="bi bi-archive"></i>暂无归档合同</div>';
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
})();
</script>
<?php $tab = 'contract'; include __DIR__ . '/_foot.php'; ?>
