<?php
// 移动端共享布局（Phase 1 重构 1.7）：头部与尾部由 _head.php / _foot.php 统一输出
$title = '供应商详情';   // 页面标题，自动追加「 · 合同管理」
$tab = 'customer';     // 导航优化 Phase1：供应商由客户 Tab 内进入，高亮"客户"
include __DIR__ . '/_head.php';
?>


<?php
  $s = $supplier;
  $st = $s['status'] ?? 1;
  $stCls = $st == 1 ? 'm-tag-ok' : 'm-tag-muted';
?>
<div class="m-nav">
  <a href="/m/suppliers" class="back" aria-label="返回"><i class="bi bi-chevron-left"></i></a>
  <div class="title"><?=htmlspecialchars($s['name'])?></div>
  <div class="right"><span class="m-tag <?=$stCls?>"><?=htmlspecialchars($statusMap[$st] ?? $st)?></span></div>
</div>

<div class="m-page detail" id="page">

  <!-- 概要 -->
  <div class="m-card">
    <div class="m-card-bd" style="padding-top:16px;padding-bottom:16px">
      <div style="font-size:18px;font-weight:600"><?=htmlspecialchars($s['name'])?></div>
      <div style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap">
        <span class="m-tag m-tag-info"><?=htmlspecialchars($type_text)?></span>
      </div>
    </div>
  </div>

  <!-- 基本信息 -->
  <div class="m-card">
    <div class="m-card-hd"><span><i class="bi bi-info-circle me-1 text-primary"></i>基本信息</span></div>
    <div class="m-card-bd">
      <?php if(!empty($s['contact_name'])): ?><div class="m-kv"><div class="k">联系人</div><div class="v"><?=htmlspecialchars($s['contact_name'])?></div></div><?php endif; ?>
      <?php if(!empty($s['contact_mobile'])): ?><div class="m-kv"><div class="k">手机</div><div class="v"><?=phone_link($s['contact_mobile'], false)?></div></div><?php endif; ?>
      <?php if(!empty($s['contact_email'])): ?><div class="m-kv"><div class="k">邮箱</div><div class="v"><?=htmlspecialchars($s['contact_email'])?></div></div><?php endif; ?>
      <?php if(!empty($s['address'])): ?><div class="m-kv"><div class="k">地址</div><div class="v"><?=htmlspecialchars($s['address'])?></div></div><?php endif; ?>
      <div class="m-kv"><div class="k">归属人</div><div class="v"><?=htmlspecialchars($owner_name ?: '公海')?></div></div>
      <?php if(!empty($s['created_at'])): ?><div class="m-kv"><div class="k">创建时间</div><div class="v"><?=htmlspecialchars(substr($s['created_at'],0,10))?></div></div><?php endif; ?>
    </div>
  </div>

  <!-- v2.38.14：往来汇总（360 应付口径内嵌） -->
  <?php $gS = $g360['stats'] ?? null; if($gS): ?>
  <div class="m-card">
    <div class="m-card-hd"><span><i class="bi bi-cash-coin me-1 text-primary"></i>往来汇总</span><a href="/m/party/supplier/<?=$s['id']?>" style="font-size:12px;color:var(--m-brand)">往来全景 <i class="bi bi-chevron-right"></i></a></div>
    <div class="m-card-bd">
      <div style="display:flex;flex-wrap:wrap;text-align:center">
        <div style="width:50%;padding:6px 0"><div class="m-stat-n" style="font-size:20px;font-weight:600;color:var(--m-text-1)"><?=(int)($gS['contract_count'] ?? 0)?></div><div class="m-stat-l" style="font-size:12px;color:var(--m-text-3)">关联合同</div></div>
        <div style="width:50%;padding:6px 0"><div class="m-stat-n" style="font-size:20px;font-weight:600;color:var(--m-brand)">¥<?=number_format((float)$gS['total_amount'],0)?></div><div class="m-stat-l" style="font-size:12px;color:var(--m-text-3)">应付总额</div></div>
        <div style="width:50%;padding:6px 0"><div class="m-stat-n" style="font-size:20px;font-weight:600;color:var(--m-success)">¥<?=number_format((float)$gS['received_paid'],0)?></div><div class="m-stat-l" style="font-size:12px;color:var(--m-text-3)">已付</div></div>
        <div style="width:50%;padding:6px 0"><div class="m-stat-n" style="font-size:20px;font-weight:600;color:<?=($gS['balance']??0)>0?'var(--m-warn)':'var(--m-text-1)'?>">¥<?=number_format((float)$gS['balance'],0)?></div><div class="m-stat-l" style="font-size:12px;color:var(--m-text-3)">待付余额</div></div>
      </div>
    </div>
  </div>

  <!-- v2.38.14：关联合同（默认 3 条 + 展开） -->
  <div class="m-card">
    <div class="m-card-hd"><span><i class="bi bi-file-text me-1 text-primary"></i>关联合同</span><span class="m-tag m-tag-muted"><?=count($g360['contracts'] ?? [])?></span></div>
    <div class="m-card-bd" style="padding:0">
      <?php if(empty($g360['contracts'])): ?>
        <div class="m-empty" style="padding:20px 0"><i class="bi bi-file-text"></i>暂无关联合同</div>
      <?php else:
        $ctShown = count($g360['contracts']);
        $stMap = ['DRAFT'=>'m-tag-muted','PENDING_APPROVAL'=>'m-tag-warn','APPROVED'=>'m-tag-info','REJECTED'=>'m-tag-danger','SIGNED'=>'m-tag-info','EXECUTING'=>'m-tag-ok','COMPLETED'=>'m-tag-ok','TERMINATED'=>'m-tag-muted','EXPIRED'=>'m-tag-warn','ARCHIVED'=>'m-tag-muted'];
        $stText = ['DRAFT'=>'草稿','PENDING_APPROVAL'=>'待审批','APPROVED'=>'已通过','REJECTED'=>'已驳回','SIGNED'=>'历史已签','EXECUTING'=>'执行中','COMPLETED'=>'已完成','TERMINATED'=>'已终止','EXPIRED'=>'已到期','ARCHIVED'=>'已归档'];
        foreach($g360['contracts'] as $ci => $c): ?>
        <a href="/m/contract/<?=$c['id']?>" class="m-row m-lst-more"<?=($ci >= 3)?' style="display:none"':''?> style="padding:12px var(--m-pad);border-bottom:1px solid #f2f3f5">
          <div class="main">
            <div class="t" style="font-size:14px"><?=htmlspecialchars($c['title'])?></div>
            <div class="s"><?=!empty($c['project_name'])?htmlspecialchars($c['project_name']).' · ':''?><?=htmlspecialchars($c['contract_no'] ?? '')?></div>
          </div>
          <div class="aside" style="text-align:right">
            <div style="font-weight:600;color:var(--m-text-1)">¥<?=number_format((float)$c['amount'],0)?></div>
            <span class="m-tag <?=$stMap[$c['status']] ?? 'm-tag-muted'?>" style="font-size:11px"><?=$stText[$c['status']] ?? $c['status']?></span>
          </div>
        </a>
        <?php endforeach;
        if($ctShown > 3): ?><div class="m-row m-lst-more-btn" onclick="mShowMore(this)" style="justify-content:center;color:var(--m-brand);padding:10px var(--m-pad)">展开全部 <?=$ctShown?> 条合同 <i class="bi bi-chevron-down"></i></div><?php endif; endif; ?>
    </div>
  </div>

  <!-- v2.38.14：最近动态 -->
  <?php if(!empty($g360['activity'])): ?>
  <div class="m-card">
    <div class="m-card-hd"><span><i class="bi bi-clock-history me-1 text-primary"></i>最近动态</span></div>
    <div class="m-card-bd" style="padding:0">
      <?php foreach(array_slice($g360['activity'], 0, 5) as $ac): ?>
        <div class="m-row" style="padding:10px var(--m-pad);border-bottom:1px solid #f2f3f5">
          <div class="s"><span class="m-tag m-tag-info" style="font-size:11px"><?=htmlspecialchars(audit_action_label($ac['action'] ?? ''))?></span> 合同 #<?=(int)($ac['target_id'] ?? 0)?></div>
          <div class="s" style="font-size:11px;color:var(--m-text-3)"><?=htmlspecialchars($ac['created_at'] ?? '')?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; endif; ?>

</div>

<?php $tab = 'customer'; include __DIR__ . '/_foot.php'; ?>
