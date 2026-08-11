<?php
// 移动端「我的业绩」个人自视页（v2.39.0）：只看自己的数据，纵向环比不做排行
$title = '我的业绩';
$tab = 'more';
include __DIR__ . '/_head.php';
$s = $stats; // personalStats 结果
function _chgBadge($v) { return $v > 0 ? '<span class="m-tag" style="background:#e8f7ee;color:#0a7a3a">↑' . $v . '%</span>' : ($v < 0 ? '<span class="m-tag" style="background:#fdecec;color:#d22">↓' . abs($v) . '%</span>' : '<span class="m-tag m-tag-muted">→ 0%</span>'); }
?>
<div class="m-nav">
  <a href="/m/more" class="back" aria-label="返回"><i class="bi bi-chevron-left"></i></a>
  <div class="title">我的业绩</div>
  <div class="right"></div>
</div>

<div class="m-page" id="page">
  <!-- 个人信息 -->
  <div class="m-card">
    <div class="m-card-bd" style="display:flex;align-items:center;gap:12px">
      <div class="notif-icon notif-icon-approval" style="flex-shrink:0"><i class="bi bi-person-vcard"></i></div>
      <div>
        <div style="font-size:16px;font-weight:600"><?=htmlspecialchars($s['user_name'] ?: '我')?></div>
        <div class="m-tag m-tag-info" style="margin-top:2px"><?=htmlspecialchars($s['dept_name'] ?: '未分配部门')?></div>
      </div>
      <div style="margin-left:auto;text-align:right">
        <div class="m-tag m-tag-ok">回款率 <?=$s['recovery_rate']?>%</div>
      </div>
    </div>
  </div>

  <!-- 核心数字：本月 vs 上月（纵向） -->
  <div class="m-stat-row" style="margin-top:var(--m-gap)">
    <div class="m-stat">
      <div class="n" style="font-size:15px">&yen;<?=number_format($s['month_amt'], 0)?></div>
      <div class="l">本月合同额 <?=_chgBadge($s['amt_chg'])?></div>
    </div>
    <div class="m-stat">
      <div class="n" style="color:var(--m-success)">&yen;<?=number_format($s['paid_month'], 0)?></div>
      <div class="l">本月已收 <?=_chgBadge($s['paid_chg'])?></div>
    </div>
  </div>

  <!-- 我的合同 -->
  <div class="m-card">
    <div class="m-card-hd"><span><i class="bi bi-file-earmark-text me-1 text-primary"></i>我的合同</span>
      <a href="/m/contracts" class="small text-primary" style="text-decoration:none">查看 <i class="bi bi-chevron-right"></i></a></div>
    <div class="m-card-bd">
      <div class="m-row"><div class="main"><div class="t">累计合同</div></div><div class="aside amt pay-amt in">¥<?=number_format($s['total_amt'], 0)?> <small class="text-muted"><?=$s['total_cnt']?> 份</small></div></div>
      <div class="m-row"><div class="main"><div class="t">本月新增</div></div><div class="aside amt pay-amt in">¥<?=number_format($s['month_amt'], 0)?> <small class="text-muted"><?=$s['month_cnt']?> 份</small></div></div>
      <div class="m-row" style="border-bottom:none"><div class="main"><div class="t">上月新增</div></div><div class="aside amt pay-amt">¥<?=number_format($s['prev_amt'], 0)?></div></div>
    </div>
  </div>

  <!-- 我的回款 -->
  <div class="m-card">
    <div class="m-card-hd"><span><i class="bi bi-cash-coin me-1 text-success"></i>我的回款</span>
      <a href="/m/finance" class="small text-primary" style="text-decoration:none">财务概览 <i class="bi bi-chevron-right"></i></a></div>
    <div class="m-card-bd">
      <div class="m-row"><div class="main"><div class="t">累计已收</div></div><div class="aside amt pay-amt in">¥<?=number_format($s['paid_total'], 0)?></div></div>
      <div class="m-row"><div class="main"><div class="t">本月已收</div></div><div class="aside amt pay-amt in">¥<?=number_format($s['paid_month'], 0)?> <?=_chgBadge($s['paid_chg'])?></div></div>
      <div class="m-row"><div class="main"><div class="t">上月已收</div></div><div class="aside amt pay-amt">¥<?=number_format($s['paid_prev'], 0)?></div></div>
      <div class="m-row"><div class="main"><div class="t">待收计划</div></div><div class="aside amt pay-amt" style="color:#e08600">¥<?=number_format($s['pending_amt'], 0)?></div></div>
      <div class="m-row" style="border-bottom:none"><div class="main"><div class="t">逾期应收（<?=$s['overdue_amt'] > 0 ? '需跟进' : '无'?>）</div></div><div class="aside amt pay-amt" style="color:var(--m-danger)">¥<?=number_format($s['overdue_amt'], 0)?></div></div>
    </div>
  </div>

  <!-- 我的客户 -->
  <div class="m-card">
    <div class="m-card-hd"><span><i class="bi bi-people me-1 text-info"></i>我的客户</span>
      <a href="/m/customers" class="small text-primary" style="text-decoration:none">查看 <i class="bi bi-chevron-right"></i></a></div>
    <div class="m-card-bd">
      <div class="m-row"><div class="main"><div class="t">累计客户</div></div><div class="aside amt"><?=$s['cust_total']?> 家</div></div>
      <div class="m-row" style="border-bottom:none"><div class="main"><div class="t">本月新增</div></div><div class="aside amt" style="color:var(--m-brand)"><?=$s['cust_month']?> 家</div></div>
    </div>
  </div>

  <div style="padding:14px 16px;font-size:12px;color:var(--m-text-3)"><i class="bi bi-info-circle"></i> 数据仅统计你名下（owner）的交易合同；环比为本月与上月对比，供个人纵向参考，不参与部门/他人排行。</div>
</div>

<?php include __DIR__ . '/_foot.php'; ?>
