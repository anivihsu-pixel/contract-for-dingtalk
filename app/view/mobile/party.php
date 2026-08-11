<?php
// 往来档案移动列表（v2.38.11 原生移动版，v2.38.14 更名）：客户 + 供应商，搜索/类型筛选
$title = '往来档案';
$tab = '';
include __DIR__ . '/_head.php';
?>

<div class="m-nav">
  <a href="/m/more" class="back" aria-label="返回"><i class="bi bi-chevron-left"></i></a>
  <div class="title">往来档案</div>
  <div class="right"><?=count($parties)?> 个</div>
</div>

<!-- 搜索 -->
<div style="margin:var(--m-gap);">
  <form method="get" action="/m/party" class="m-search-bar">
    <i class="bi bi-search" style="color:var(--m-text-3);font-size:16px"></i>
    <input name="keyword" type="search" placeholder="搜索名称 / 联系人 / 电话" value="<?=htmlspecialchars($keyword)?>" style="flex:1;border:none;outline:none;padding:12px 8px;font-size:15px;background:transparent;">
    <button type="submit" style="border:none;background:none;color:var(--m-brand);font-size:14px;padding:0 4px;">搜索</button>
  </form>
  <!-- 类型筛选 -->
  <div style="display:flex;gap:8px;margin-top:10px">
    <a href="/m/party" class="m-chip <?=$type===''?'active':''?>">全部</a>
    <a href="/m/party?type=customer" class="m-chip <?=$type==='customer'?'active':''?>">客户</a>
    <a href="/m/party?type=supplier" class="m-chip <?=$type==='supplier'?'active':''?>">供应商</a>
  </div>
</div>

<?php if(!empty($truncated)): ?>
<div style="margin:0 var(--m-gap) calc(var(--m-gap) * 0.5);padding:8px 12px;background:#fff8e6;border:1px solid #ffe1a6;border-radius:8px;font-size:12px;color:#8a6d3b">结果过多，仅显示前 200 条，请使用关键词搜索缩小范围</div>
<?php endif; ?>

<!-- 往来档案列表 -->
<div id="list" style="padding:0 var(--m-gap) calc(20px + var(--safe-bottom))">
  <?php if(empty($parties)): ?>
    <div class="m-empty"><i class="bi bi-cash-coin"></i>暂无往来档案</div>
  <?php else: foreach($parties as $p): $__s = $p['_sum'] ?? null; ?>
    <a class="m-card" href="/m/party/<?=$p['type']?>/<?=$p['id']?>" style="display:block">
      <div class="m-card-bd" style="padding-top:14px;padding-bottom:14px">
        <div class="m-row" style="border-bottom:none;padding:0">
          <!-- v2.38.11：去掉左侧图标（相对方类型已有标签，图标冗余）；标题允许换行完整显示 -->
          <div class="main" style="flex:1;min-width:0">
            <div class="t" style="white-space:normal;overflow:visible;text-overflow:clip;line-height:1.4"><?=htmlspecialchars($p['name'])?></div>
            <div class="s"><?=$p['type_label']?><?=!empty($p['contact_name'])?' · '.htmlspecialchars($p['contact_name']):''?></div>
          </div>
          <div class="aside" style="flex:none;margin-left:8px;display:flex;flex-direction:column;gap:4px;align-items:flex-end">
            <span class="m-tag <?=$p['type']==='supplier'?'m-tag-warn':'m-tag-info'?>"><?=$p['type_label']?></span>
            <?php if(!empty($p['tag'])): ?><span class="m-tag m-tag-muted"><?=htmlspecialchars($p['tag'])?></span><?php endif; ?>
          </div>
        </div>
        <?php // v2.38.14：往来行（资金台账，与 PC 同源批量汇总）——待收/待付橙色、已清绿色、无往来不显示
        if($__s && $__s['total'] > 0): ?>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:10px;padding-top:10px;border-top:1px solid var(--m-line)">
          <span style="font-size:12px;color:var(--m-text-3)">往来总额 <strong style="color:var(--m-text-1)">¥<?=number_format((float)$__s['total'],0)?></strong></span>
          <span class="m-tag <?= $__s['balance'] > 0 ? 'm-tag-warn' : 'm-tag-ok' ?>" style="font-size:11px"><?= $__s['balance'] > 0 ? $__s['pending_word'].' ¥'.number_format((float)$__s['balance'],0) : '已清' ?></span>
        </div>
        <?php endif; ?>
      </div>
    </a>
  <?php endforeach; endif; ?>
</div>

<?php include __DIR__ . '/_foot.php'; ?>
