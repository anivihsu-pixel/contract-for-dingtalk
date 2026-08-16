<?php
// 移动端项目详情（Phase 2.6：复用 ProjectLogic，零 Db 直查）
$project = $project ?? [];
$aggregate = $aggregate ?? [];
$contracts = $contracts ?? [];
$statusDict = $statusDict ?? [];
// 合同状态标签（格式与 contract_detail.php 一致：['t'=>标签, 'c'=>样式]）
$contractStatusMap = [
    'DRAFT' => ['t' => '草稿', 'c' => 'muted'],
    'PENDING_APPROVAL' => ['t' => '待审批', 'c' => 'warn'],
    'REJECTED' => ['t' => '已驳回', 'c' => 'danger'],
    'EXECUTING' => ['t' => '执行中', 'c' => 'ok'],
    'COMPLETED' => ['t' => '已完成', 'c' => 'muted'],
    'EXPIRED' => ['t' => '已到期', 'c' => 'muted'],
    'ARCHIVED' => ['t' => '已归档', 'c' => 'muted'],
];

// 项目状态标签
$projStatus = $project['status'] ?? 'ACTIVE';
$projStatusText = $statusDict[$projStatus] ?? $projStatus;
$projStatusCls = ['ACTIVE' => 'm-tag-info', 'DONE' => 'm-tag-ok', 'ARCHIVED' => 'm-tag-muted'][$projStatus] ?? 'm-tag-muted';
?>
<?php
$title = '项目详情';
$tab = 'more';
$pageStyle = <<<'CSS'

  .hero { margin: var(--m-gap); background: var(--m-card); border-radius: var(--m-radius); padding: 18px var(--m-pad); box-shadow: 0 1px 3px rgba(0,0,0,.05); }
  .hero .title { font-size: 18px; font-weight: 600; line-height: 1.4; }
  .hero .no { font-size: 13px; color: var(--m-text-3); margin-top: 6px; }
  .hero .tags { margin-top: 12px; display: flex; gap: 8px; flex-wrap: wrap; }

  .stat-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
  .stat-item { background: var(--m-bg); border-radius: var(--m-radius-sm); padding: 14px; text-align: center; }
  .stat-item .label { font-size: 12px; color: var(--m-text-3); margin-bottom: 6px; }
  .stat-item .value { font-size: 20px; font-weight: 700; }
  .stat-item .value.amt-in { color: var(--m-danger); }
  .stat-item .value.amt-out { color: var(--m-success); }
  .stat-item .unit { font-size: 13px; font-weight: 500; }

  .pay-progress { height: 8px; border-radius: 999px; background: #eef0f3; overflow: hidden; margin: 4px 0 10px; }
  .pay-progress > span { display: block; height: 100%; background: linear-gradient(90deg, #07c160, #34d27e); border-radius: 999px; }
CSS;
include __DIR__ . '/_head.php';
?>

<!-- 顶部导航 -->
<div class="m-nav">
  <a href="javascript:history.back()" class="back" aria-label="返回"><i class="bi bi-chevron-left"></i></a>
  <div class="title">项目详情</div>
  <div class="right"></div>
</div>

<div class="m-page detail">

  <!-- 项目概览 -->
  <div class="hero">
    <div class="title"><?=htmlspecialchars($project['name'] ?? '未命名项目')?></div>
    <div class="no"><?=htmlspecialchars($project['code'] ?? '')?></div>
    <div class="tags">
      <span class="m-tag <?=$projStatusCls?>"><?=$projStatusText?></span>
      <?php if (!empty($project['owner_name'])): ?><span class="m-tag m-tag-muted"><?=htmlspecialchars($project['owner_name'])?></span><?php endif; ?>
    </div>
  </div>

  <!-- 基本信息 -->
  <div class="m-card">
    <div class="m-card-hd"><span><i class="bi bi-info-circle me-1 text-primary"></i>基本信息</span></div>
    <div class="m-card-bd">
      <?php if (!empty($project['start_date'])): ?><div class="m-kv"><div class="k">开始日期</div><div class="v"><?=htmlspecialchars($project['start_date'])?></div></div><?php endif; ?>
      <?php if (!empty($project['end_date'])): ?><div class="m-kv"><div class="k">结束日期</div><div class="v"><?=htmlspecialchars($project['end_date'])?></div></div><?php endif; ?>
      <?php if (!empty($project['remark'])): ?><div class="m-kv" style="display:block"><div class="k" style="margin-bottom:6px">备注</div><div class="v" style="white-space:pre-wrap"><?=htmlspecialchars($project['remark'])?></div></div><?php endif; ?>
      <div class="m-kv"><div class="k">创建时间</div><div class="v"><?=htmlspecialchars($project['created_at'] ?? '-')?></div></div>
    </div>
  </div>

  <!-- 经营概要（Phase 2.6：复用 ProjectLogic::aggregate） -->
  <div class="m-card">
    <div class="m-card-hd"><span><i class="bi bi-bar-chart me-1 text-primary"></i>经营概要</span></div>
    <div class="m-card-bd">
      <div class="stat-grid">
        <div class="stat-item">
          <div class="label">关联合同</div>
          <div class="value"><?=intval($aggregate['contract_count'] ?? 0)?></div>
        </div>
        <div class="stat-item">
          <div class="label">回款率</div>
          <div class="value"><?=number_format($aggregate['recovery_rate'] ?? 0, 1)?><span class="unit">%</span></div>
        </div>
        <div class="stat-item">
          <div class="label">销售总额</div>
          <div class="value amt-in"><span class="unit">¥</span><?=number_format($aggregate['sales_amount'] ?? 0, 0)?></div>
        </div>
        <div class="stat-item">
          <div class="label">采购总额</div>
          <div class="value amt-out"><span class="unit">¥</span><?=number_format($aggregate['purchase_amount'] ?? 0, 0)?></div>
        </div>
      </div>
      <?php if (($aggregate['receivable'] ?? 0) > 0): ?>
        <div style="margin-top:16px">
          <div style="display:flex;justify-content:space-between;font-size:13px;color:var(--m-text-2);margin-bottom:4px">
            <span>已收 <b class="amt-in">¥<?=number_format($aggregate['received'] ?? 0, 0)?></b></span>
            <span>应收 <b>¥<?=number_format($aggregate['receivable'] ?? 0, 0)?></b></span>
          </div>
          <div class="pay-progress"><span style="width:<?=min(100, $aggregate['recovery_rate'] ?? 0)?>%"></span></div>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- 关联合同列表 -->
  <?php if (!empty($contracts)): ?>
  <div class="m-card">
    <div class="m-card-hd"><span><i class="bi bi-file-earmark-text me-1 text-primary"></i>关联合同 (<?=($contract_total ?? count($contracts))?>)</span></div>
    <div class="m-card-bd">
      <?php foreach ($contracts as $c):
        $isNonTrade = (($c['trade_attr'] ?? 1) == 0);
        $isIn = !$isNonTrade && ($c['direction'] ?? 'sales') === 'sales';
        $amtCls = $isNonTrade ? 'text-muted' : ($isIn ? 'amt-in' : 'amt-out');
        $cStatus = $c['status'] ?? '';
        $stInfo = $contractStatusMap[$cStatus] ?? ['t' => $cStatus, 'c' => 'muted'];
      ?>
        <a class="m-row pay-row" href="/m/contract/<?=intval($c['id'])?>" style="text-decoration:none;color:inherit">
          <div class="main">
            <div class="t"><?=htmlspecialchars($c['title'] ?? '')?></div>
            <div class="s"><?=htmlspecialchars($c['contract_no'] ?? '')?></div>
          </div>
          <div class="aside">
            <?php if ($isNonTrade): ?>
              <span class="m-tag m-tag-muted">非交易</span>
            <?php else: ?>
              <div class="amt pay-amt <?=$amtCls?>">¥<?=number_format((float)($c['amount'] ?? 0), 0)?></div>
            <?php endif; ?>
            <span class="m-tag m-tag-<?=$stInfo['c']?>"><?=$stInfo['t']?></span>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
    <?php if (($contract_total ?? 0) > ($contract_limit ?? 0) && ($contract_limit ?? 0) > 0): ?>
    <div class="m-loadmore text-muted small"><a href="/m/contract?project_id=<?=intval($project['id'] ?? 0)?>">仅显示前 <?=$contract_limit?> 条，查看全部 <?=$contract_total?> 条 →</a></div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

</div>
<?php $tab = 'more'; include __DIR__ . '/_foot.php'; ?>
