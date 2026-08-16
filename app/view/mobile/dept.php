<?php
// 移动端部门经营详情页（v2.51.3）：工作台经营卡片 / 经营周报部门行点击进入
// 数据由 MobileController::dept 装配：dept 汇总 + members 成员排名 + contracts 部门合同
$title = '部门经营';
$tab = $tab ?? 'more';
include __DIR__ . '/_head.php';
$wan = function($n) { return $n >= 10000 ? round($n / 10000, 1) . '万' : (string)(int)$n; };
$statusMap = contract_status_map();
$statusBadge = contract_status_badge();
?>
<div class="m-nav">
  <a href="javascript:history.back()" class="back" aria-label="返回"><i class="bi bi-chevron-left"></i></a>
  <div class="title"><?=htmlspecialchars($dept_name ?? '部门经营')?></div>
  <div class="right"></div>
</div>

<div class="m-page">
  <?php if(!empty($error)): ?>
    <div class="m-empty"><i class="bi bi-building"></i><?=htmlspecialchars($error)?></div>
  <?php elseif(empty($dept)): ?>
    <div class="m-empty"><i class="bi bi-inbox"></i>该部门暂无生效合同数据</div>
  <?php else: ?>
    <?php $d = $dept; ?>
    <!-- 部门汇总 -->
    <div class="m-stat-row" style="margin-top:var(--m-gap)">
      <div class="m-stat">
        <div class="n" style="font-size:18px">¥<?=htmlspecialchars($wan($d['total_amount']))?></div>
        <div class="l">合同金额（生效）</div>
        <div class="c"><?=intval($d['cnt'])?> 份</div>
      </div>
      <div class="m-stat">
        <div class="n" style="font-size:18px;color:var(--m-success)">¥<?=htmlspecialchars($wan($d['paid_amount']))?></div>
        <div class="l">已回款</div>
        <div class="c">回款率 <?=$d['recovery_rate']?>%</div>
      </div>
    </div>

    <!-- 成员排名 -->
    <?php if(!empty($members)): ?>
    <div class="m-card">
      <div class="m-card-hd"><span><i class="bi bi-people me-1 text-primary"></i>部门成员排名（按合同额）</span></div>
      <div class="m-card-bd" style="padding:6px 14px 10px">
        <?php foreach($members as $i => $m):
          $rk = $i + 1;
          $rkCls = $rk <= 3 ? ' top' . $rk : '';
        ?>
        <div style="display:flex;align-items:center;gap:8px;padding:11px 0;border-bottom:1px solid #f5f5f5">
          <div style="width:20px;height:20px;border-radius:50%;background:#f2f3f5;color:#8a9099;font-size:11px;font-weight:600;display:flex;align-items:center;justify-content:center;flex-shrink:0<?= $rkCls===' top1' ? ';background:#fef0e6;color:#fa8c16' : '' ?><?= $rkCls===' top2' ? ';background:#f5f5f5;color:#8a9099' : '' ?><?= $rkCls===' top3' ? ';background:#fff3e0;color:#d46b08' : '' ?>"><?=$rk?></div>
          <div style="flex:1;min-width:0">
            <div style="font-size:14px;color:#1f2329;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?=htmlspecialchars($m['user_name'])?></div>
            <div style="font-size:11px;color:#8a9099;margin-top:2px"><?=intval($m['cnt'])?> 份 · 回款 <?=htmlspecialchars($wan($m['paid_amount']))?></div>
          </div>
          <div style="font-size:14px;font-weight:600;color:var(--brand);flex-shrink:0"><?=htmlspecialchars($wan($m['total_amount']))?></div>
          <div style="font-size:11px;color:#18a058;flex-shrink:0;min-width:42px;text-align:right"><?=$m['recovery_rate']?>%</div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- 部门合同 -->
    <div class="m-card">
      <div class="m-card-hd"><span><i class="bi bi-file-earmark-text me-1 text-primary"></i>部门合同</span>
        <?php if(intval($contract_total) > count($contracts)): ?>
          <a href="/m/contracts?dept_id=<?=intval($dept_id)?>" style="font-size:12px;color:var(--m-brand);text-decoration:none;display:flex;align-items:center;gap:2px">查看全部 <?=intval($contract_total)?> 条 <i class="bi bi-chevron-right"></i></a>
        <?php endif; ?>
      </div>
      <div class="m-card-bd" style="padding:6px 14px 10px">
        <?php if(empty($contracts)): ?>
          <div class="m-empty"><i class="bi bi-inbox"></i>该部门暂无合同</div>
        <?php else: foreach($contracts as $c):
          $st = $c['status'] ?? 'DRAFT';
          $stCls = $statusBadge[$st] ?? 'm-tag-muted';
          $isNonTrade = (($c['trade_attr'] ?? 1) == 0);
          $isIn = !$isNonTrade && ($c['direction'] ?? 'sales') === 'sales';
          $dirCls = $isNonTrade ? 'm-tag-muted' : ($isIn ? 'm-tag-recv' : 'm-tag-pay');
          $dirTxt = $isNonTrade ? '非交易' : ($isIn ? '应收' : '应付');
          $amtCls = $isNonTrade ? 'text-muted' : ($isIn ? 'in' : 'out');
        ?>
        <a class="m-card<?=$st==='DRAFT'?' is-draft':''?>" href="/m/contract/<?=intval($c['id'])?>" style="display:block;border:1px solid var(--m-border);border-radius:12px;margin-bottom:10px;background:#fff">
          <div class="m-card-bd" style="padding-top:12px;padding-bottom:12px">
            <div class="m-row" style="border-bottom:none;padding:0">
              <div class="pic"><i class="bi bi-file-earmark-text"></i></div>
              <div class="main">
                <div class="t"><?=htmlspecialchars($c['title'] ?? '')?></div>
                <div class="s"><?=htmlspecialchars($c['contract_no'] ?? '')?><?=!empty($c['owner_name'])?' · '.htmlspecialchars($c['owner_name']):''?></div>
              </div>
              <div class="aside"><span class="m-tag <?=$stCls?>"><?=htmlspecialchars($statusMap[$st] ?? $st)?></span></div>
            </div>
            <div style="display:flex;align-items:center;justify-content:space-between;margin-top:10px">
              <span style="display:flex;align-items:center;gap:6px">
                <span class="m-tag <?=$dirCls?>"><?=$dirTxt?></span>
                <span class="amt pay-amt <?=$amtCls?>">¥<?=number_format((float)($c['amount'] ?? 0), 0)?></span>
              </span>
              <span style="font-size:12px;color:var(--m-text-3)"><?=!empty($c['expiry_date'])?'到期 '.htmlspecialchars($c['expiry_date']):''?></span>
            </div>
          </div>
        </a>
        <?php endforeach; endif; ?>
      </div>
    </div>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/_foot.php'; ?>
