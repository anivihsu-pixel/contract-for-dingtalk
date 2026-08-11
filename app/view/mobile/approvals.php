<?php
// 移动端共享布局（Phase 1 重构 1.7）：头部与尾部由 _head.php / _foot.php 统一输出
$title = '审批';   // 页面标题，自动追加「 · 合同管理」
$tab = '';     // 审批 Tab 已从底部菜单移除（与顶部「待我审批」重叠），本页不高亮
// v2.38.18：.m-tabs/.m-tab 样式已公共化到 mobile.css（审批中心/待办中心共用），此处不再内联
include __DIR__ . '/_head.php';
?>

<!-- 顶部导航 -->
<div class="m-nav">
  <a href="/m" class="back" aria-label="返回"><i class="bi bi-chevron-left"></i></a>
  <div class="title">审批中心</div>
  <div class="right"></div>
</div>

<!-- 三 Tab -->
<div class="m-tabs">
  <a class="m-tab <?=$type==='todo'?'active':''?>" href="/m/approvals?type=todo">待办<?php if(($counts['todo']??0)>0):?><span class="badge"><?=$counts['todo']?></span><?php endif;?></a>
  <a class="m-tab <?=$type==='done'?'active':''?>" href="/m/approvals?type=done">已办<?php if(($counts['done']??0)>0):?><span class="badge"><?=$counts['done']?></span><?php endif;?></a>
  <a class="m-tab <?=$type==='submitted'?'active':''?>" href="/m/approvals?type=submitted">我提交<?php if(($counts['submitted']??0)>0):?><span class="badge"><?=$counts['submitted']?></span><?php endif;?></a>
</div>

<div class="m-page" id="page">
  <!-- 列表 -->
  <div id="list">
    <?php if(empty($list)): ?>
      <div class="m-empty"><i class="bi bi-check2-circle"></i><?=$type==='todo'?'暂无待审批事项，去喝杯茶吧':'暂无相关审批'?></div>
    <?php else: foreach($list as $p):
        $isNonTrade = ($p['trade_attr'] ?? 1) == 0;
        $isIn = !$isNonTrade && ($p['direction'] ?? 'sales') === 'sales';
        $st = $p['status'] ?? 'PENDING';
        $stMap = ['PENDING'=>'待审批','APPROVED'=>'已通过','REJECTED'=>'已驳回','RECALLED'=>'已撤回'];
        $stCls = ['PENDING'=>'m-tag-info','APPROVED'=>'m-tag-ok','REJECTED'=>'m-tag-danger','RECALLED'=>'m-tag-muted'];
        $who = $type === 'submitted' ? '我' : (htmlspecialchars($p['submitter_name'] ?? '-'));
        $hasAmt = isset($p['amount']) && (float)($p['amount'] ?? 0) > 0;
    ?>
      <a class="m-card" href="/m/approval/<?=$p['id']?>" style="display:block">
        <div class="m-card-bd" style="padding-top:14px;padding-bottom:14px">
          <div class="m-row" style="border-bottom:none;padding:0">
            <div class="pic"><i class="bi bi-file-earmark-text"></i></div>
            <div class="main">
              <div class="t"><?=htmlspecialchars($p['contract_title'] ?? '合同')?></div>
              <div class="s"><?=htmlspecialchars($p['contract_no'] ?? '')?> · <?=$who?></div>
            </div>
            <div class="aside">
              <?php if($isNonTrade): ?>
                <div class="m-tag m-tag-muted">非交易</div>
              <?php elseif($hasAmt): ?>
                <div class="amt pay-amt amt-<?=$isIn?'in':'out'?>">¥<?=number_format((float)($p['amount'] ?? 0), 0)?></div>
              <?php endif; ?>
            </div>
          </div>
          <div style="display:flex;align-items:center;justify-content:space-between;margin-top:10px">
            <span class="m-tag m-tag-info"><?=htmlspecialchars($p['flow_name'] ?? '审批流')?></span>
            <span class="m-tag <?=$stCls[$st] ?? 'm-tag-muted'?>"><?=$stMap[$st] ?? $st?></span>
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
  var type = '<?=$type?>';
  var page = 1, loading = false, finished = <?=count($list) >= intval($total) ? 'true' : 'false';?>;

  
  function statusTag(st){
    var map = {PENDING:'待审批',APPROVED:'已通过',REJECTED:'已驳回',RECALLED:'已撤回'};
    var cls = {PENDING:'m-tag-info',APPROVED:'m-tag-ok',REJECTED:'m-tag-danger',RECALLED:'m-tag-muted'};
    var label = map[st]||st, c = cls[st]||'m-tag-muted';
    return '<span class="m-tag '+c+'">'+esc(label)+'</span>';
  }
  function cardHtml(p){
    var isNon = (p.trade_attr == 0);
    var isIn = !isNon && (p.direction == 'sales');
    var hasAmt = (typeof p.amount !== 'undefined') && parseFloat(p.amount||0) > 0;
    var who = (type === 'submitted') ? '我' : esc(p.submitter_name||'-');
    var right = '';
    if (isNon) right = '<div class="m-tag m-tag-muted">非交易</div>';
    else if (hasAmt) right = '<div class="amt pay-amt amt-'+(isIn?'in':'out')+'">¥'+(Number(p.amount||0)).toLocaleString('zh-CN')+'</div>';
    return '<a class="m-card" href="/m/approval/'+p.id+'" style="display:block">'
      + '<div class="m-card-bd" style="padding-top:14px;padding-bottom:14px">'
      + '<div class="m-row" style="border-bottom:none;padding:0">'
      + '<div class="pic"><i class="bi bi-file-earmark-text"></i></div>'
      + '<div class="main"><div class="t">'+esc(p.contract_title||'合同')+'</div>'
      + '<div class="s">'+esc(p.contract_no||'')+' · '+who+'</div></div>'
      + '<div class="aside">'+right+'</div></div>'
      + '<div style="display:flex;align-items:center;justify-content:space-between;margin-top:10px">'
      + '<span class="m-tag m-tag-info">'+esc(p.flow_name||'审批流')+'</span>'
      + statusTag(p.status)
      + '</div></div></a>';
  }
  function appendList(arr){
    var box = document.getElementById('list');
    arr.forEach(function(p){ box.insertAdjacentHTML('beforeend', cardHtml(p)); });
  }

  

  var loadmore = document.getElementById('loadmore');
  if (loadmore){
    loadmore.addEventListener('click', function(){
      if (loading || finished) return;
      loading = true; loadmore.textContent = '加载中…';
      fetch('/m/approvals?type=' + type + '&page=' + (page + 1), {headers:{'X-Requested-With':'XMLHttpRequest'}})
        .then(function(r){ return r.json(); })
        .then(function(res){
          loading = false;
          if (res.code !== 0 || !res.data.length){ finished = true; loadmore.style.display='none'; return; }
          page++;
          appendList(res.data);
          if (res.total && page * 20 >= res.total){ finished = true; loadmore.style.display='none'; }
          else loadmore.textContent = '加载更多';
        })
        .catch(function(){ loading=false; loadmore.textContent='加载更多'; toast('网络异常'); });
    });
  }
})();
</script>
<?php $tab = ''; include __DIR__ . '/_foot.php'; ?>
