<?php
// 移动端共享布局（Phase 1 重构 1.7）：头部与尾部由 _head.php / _foot.php 统一输出
$title = '报表概览';   // 页面标题，自动追加「 · 合同管理」
$tab = 'more';     // 底部导航高亮：home/contract/customer/todo/more
include __DIR__ . '/_head.php';
?>


<div class="m-nav">
  <a href="/m" class="back" aria-label="返回"><i class="bi bi-chevron-left"></i></a>
  <div class="title">报表概览</div>
  <div class="right"></div>
</div>

<!-- 周期筛选：本月 / 本季 / 本年 / 累计 -->
<div style="display:flex;gap:8px;overflow-x:auto;padding:var(--m-gap) var(--m-gap) 4px;-webkit-overflow-scrolling:touch;">
  <a href="javascript:;" class="m-chip" data-period="month">本月</a>
  <a href="javascript:;" class="m-chip" data-period="quarter">本季</a>
  <a href="javascript:;" class="m-chip" data-period="year">本年</a>
  <a href="javascript:;" class="m-chip active" data-period="all">累计</a>
</div>

<div class="m-page" id="page">

  <!-- 核心数字 -->
  <div class="m-stat-row" style="margin-top:var(--m-gap)">
    <div class="m-stat">
      <div class="n" id="rTotalContracts"><?=intval($total_contracts)?></div>
      <div class="l">合同总数</div>
    </div>
    <div class="m-stat">
      <div class="n" id="rTotalAmount">¥<?=number_format((float)$total_amount, 0)?></div>
      <div class="l" id="rTotalAmountLabel">经营总额</div>
    </div>
  </div>
  <div class="m-stat-row">
    <div class="m-stat">
      <div class="n" style="color:#e08600"><?=intval($pending_approval)?></div>
      <div class="l">待审批</div>
    </div>
    <div class="m-stat">
      <div class="n" style="color:var(--m-success)"><?=intval($signed_contracts)?></div>
      <div class="l">执行中合同</div>
    </div>
  </div>

  <!-- 回款概览 -->
  <div class="m-card">
    <div class="m-card-hd"><span><i class="bi bi-cash-coin me-1 text-primary"></i>回款概览</span>
      <span class="m-tag m-tag-info">回款率 <span id="rRecoveryRate"><?=$recovery_rate?></span>%</span></div>
    <div class="m-card-bd">
      <div class="m-row"><div class="main"><div class="t">应收总额</div></div><div class="aside amt pay-amt in" id="rReceivable">¥<?=number_format((float)$total_receivable, 0)?></div></div>
      <div class="m-row"><div class="main"><div class="t">已收金额</div></div><div class="aside amt pay-amt in" id="rReceived">¥<?=number_format((float)$received_amount, 0)?></div></div>
      <div class="m-row"><div class="main"><div class="t">待收金额</div></div><div class="aside amt pay-amt in" id="rPending">¥<?=number_format((float)$pending_amount, 0)?></div></div>
      <div class="m-row" style="border-bottom:none"><div class="main"><div class="t">逾期（<span id="rOverdueCount"><?=intval($overdue_count)?></span> 笔）</div></div><div class="aside amt pay-amt" style="color:var(--m-danger)" id="rOverdue">¥<?=number_format((float)$overdue_amount, 0)?></div></div>
    </div>
  </div>

  <!-- 收支方向 -->
  <div class="m-card">
    <div class="m-card-hd"><span><i class="bi bi-bar-chart me-1 text-primary"></i>收支方向<span class="m-tag m-tag-info ms-1" id="rDirLabel">累计</span></span></div>
    <div class="m-card-bd">
      <div class="m-row"><div class="main"><div class="t">销售合同（应收）</div><div class="s"><span id="rSalesCnt"><?=intval($dir_summary['sales']['cnt'])?></span> 份</div></div><div class="aside amt pay-amt in" id="rSalesTotal">¥<?=number_format((float)$dir_summary['sales']['total'], 0)?></div></div>
      <div class="m-row" style="border-bottom:none"><div class="main"><div class="t">采购合同（应付）</div><div class="s"><span id="rPurchaseCnt"><?=intval($dir_summary['purchase']['cnt'])?></span> 份</div></div><div class="aside amt pay-amt out" id="rPurchaseTotal">¥<?=number_format((float)$dir_summary['purchase']['total'], 0)?></div></div>
    </div>
  </div>

  <!-- 合同状态分布 -->
  <div class="m-card">
    <div class="m-card-hd"><span><i class="bi bi-diagram-3 me-1 text-primary"></i>合同状态分布</span></div>
    <div class="m-card-bd">
      <?php
        $statusMap = contract_status_map();   // CR-57：复用公共 helper
        $statusBadge = contract_status_badge();
      ?>
      <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:8px">
        <?php foreach($statusMap as $k=>$v):
          $cnt = $status_counts[$k] ?? 0;
          if($cnt == 0) continue; // 仅展示有数据的状态，保持精炼
        ?>
        <a href="/m/contracts?status=<?=htmlspecialchars($k)?>" style="text-decoration:none">
          <div class="m-row" style="background:#fafbfc;border-radius:10px;padding:10px 12px">
            <div class="main"><div class="t"><?=htmlspecialchars($v)?></div></div>
            <div class="aside"><span class="m-tag <?=$statusBadge[$k]?>"><?=$cnt?></span></div>
          </div>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- 其他概览 -->
  <div class="m-card">
    <div class="m-card-hd"><span><i class="bi bi-grid me-1 text-primary"></i>基础数据</span></div>
    <div class="m-card-bd">
      <div class="m-row"><div class="main"><div class="t">客户总数</div></div><div class="aside"><?=intval($total_customers)?></div></div>
      <div class="m-row"><div class="main"><div class="t">供应商总数</div></div><div class="aside"><?=intval($total_suppliers)?></div></div>
      <div class="m-row" style="border-bottom:none"><div class="main"><div class="t">30 天内即将到期</div></div><div class="aside" style="color:#e08600"><?=intval($expiring_soon)?></div></div>
    </div>
  </div>

  <!-- 模块导航 -->
  <div class="m-card">
    <div class="m-card-hd"><span><i class="bi bi-compass me-1 text-primary"></i>更多模块</span></div>
    <div class="m-card-bd">
      <a href="/m/finance" class="m-row" style="text-decoration:none"><div class="main"><div class="t"><i class="bi bi-cash-coin me-1"></i>财务统计</div></div><div class="aside"><i class="bi bi-chevron-right"></i></div></a>
      <?php if(!empty($can_view_project)): ?><a href="/m/projects" class="m-row" style="text-decoration:none"><div class="main"><div class="t"><i class="bi bi-folder2 me-1"></i>项目列表</div></div><div class="aside"><i class="bi bi-chevron-right"></i></div></a><?php endif; ?>
      <a href="/m/archive" class="m-row" style="text-decoration:none;border-bottom:none"><div class="main"><div class="t"><i class="bi bi-archive me-1"></i>归档合同</div></div><div class="aside"><i class="bi bi-chevron-right"></i></div></a>
    </div>
  </div>

</div>

<script>
(function(){
  function money(n){ return '¥' + (parseFloat(n||0)).toLocaleString('zh-CN',{maximumFractionDigits:0}); }
  function loadReportsSummary(period){
    fetch('/ajax/mobile/reports-summary?period=' + period, {headers:{'X-Requested-With':'XMLHttpRequest'}})
      .then(function(r){ return r.json(); })
      .then(function(res){
        if(res.code !== 0 || !res.data) return;
        var d = res.data;
        var label = d.period_label || '累计';
        document.getElementById('rTotalContracts').textContent = parseInt(d.total_contracts||0);
        document.getElementById('rTotalAmount').textContent = money(d.total_amount);
        document.getElementById('rTotalAmountLabel').textContent = label + '新增';
        document.getElementById('rReceivable').textContent = money(d.total_receivable);
        document.getElementById('rReceived').textContent = money(d.received_amount);
        document.getElementById('rPending').textContent = money(d.pending_amount);
        document.getElementById('rOverdue').textContent = money(d.overdue_amount);
        document.getElementById('rOverdueCount').textContent = parseInt(d.overdue_count||0);
        document.getElementById('rRecoveryRate').textContent = (d.recovery_rate||0);
        document.getElementById('rDirLabel').textContent = label;
        document.getElementById('rSalesTotal').textContent = money(d.dir_summary.sales.total);
        document.getElementById('rSalesCnt').textContent = parseInt(d.dir_summary.sales.cnt||0);
        document.getElementById('rPurchaseTotal').textContent = money(d.dir_summary.purchase.total);
        document.getElementById('rPurchaseCnt').textContent = parseInt(d.dir_summary.purchase.cnt||0);
      })
      .catch(function(){});
  }
  document.querySelectorAll('.m-chip[data-period]').forEach(function(c){
    c.addEventListener('click', function(){
      document.querySelectorAll('.m-chip[data-period]').forEach(function(x){ x.classList.toggle('active', x===c); });
      loadReportsSummary(this.dataset.period);
    });
  });
})();
</script>

<?php $tab = 'more'; include __DIR__ . '/_foot.php'; ?>
