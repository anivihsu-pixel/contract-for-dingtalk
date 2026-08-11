<?php
// 仪表盘局部刷新模板（v2.39.0）：周期筛选 + KPI 区 + 本月经营/收支方向 + 近6季度趋势
// 供周期切换 AJAX 局部刷新（/dashboard?period=xx&ajax=1），与整页 index.php 共享变量
$sc=['DRAFT'=>'muted','PENDING_APPROVAL'=>'warn','APPROVED'=>'info','SIGNED'=>'info','EXECUTING'=>'ok','COMPLETED'=>'muted','EXPIRED'=>'danger'];
?>
<!-- 时间周期筛选（与数据卡片同区，随 AJAX 刷新；chips 高亮由后端 period 决定；位置：左侧） -->
<div class="d-flex justify-content-start mb-3">
  <div class="period-chips" id="periodChips">
    <a href="javascript:;" data-period="month" class="<?=$period==='month'?'active':''?>">本月</a>
    <a href="javascript:;" data-period="quarter" class="<?=$period==='quarter'?'active':''?>">本季</a>
    <a href="javascript:;" data-period="year" class="<?=$period==='year'?'active':''?>">本年</a>
    <a href="javascript:;" data-period="all" class="<?=$period==='all'?'active':''?>">累计</a>
  </div>
</div>

<!-- KPI：窄视口(768-1199,侧边栏在)降级 2x2 布局见 app.css .kpi-row -->
<div class="row g-3 mb-3 kpi-row">
<?php if(!empty($is_admin)): ?>
  <!-- 管理员：生效合同总额 / 待回款 / 回款率 / 今日待办 -->
  <div class="col-md-3 col-6"><div class="card stat-card" style="cursor:pointer" onclick="location.href='/contract'"><div class="card-body p-3"><div class="d-flex justify-content-between align-items-center"><div><h6 class="text-muted mb-1">生效合同总额 <small class="badge bg-light text-secondary"><?=htmlspecialchars($period_label)?></small></h6><h3 class="mb-0">&yen;<?=number_format($total_amount)?></h3><small class="text-muted"><?=$total_contracts?> 份</small></div><div class="stat-icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-cash-stack fs-4"></i></div></div></div></div></div>
  <div class="col-md-3 col-6"><div class="card stat-card" style="cursor:pointer" onclick="location.href='/contract?status=EXECUTING'"><div class="card-body p-3"><div class="d-flex justify-content-between align-items-center"><div><h6 class="text-muted mb-1">待回款 <small class="badge bg-light text-secondary"><?=htmlspecialchars($period_label)?></small></h6><h3 class="mb-0 <?=$overdue_amount>0?'text-danger':''?>">&yen;<?=number_format($pending_amount+$overdue_amount)?></h3><small class="<?=$overdue_amount>0?'text-danger':'text-muted'?>">逾期 <?=$overdue_count?> 笔</small></div><div class="stat-icon <?=$overdue_amount>0?'bg-danger':'bg-warning'?> bg-opacity-10 <?=$overdue_amount>0?'text-danger':'text-warning'?>"><i class="bi bi-clock-history fs-4"></i></div></div></div></div></div>
  <div class="col-md-3 col-6"><div class="card stat-card" style="cursor:pointer" onclick="location.href='/finance'"><div class="card-body p-3"><div class="d-flex justify-content-between align-items-center"><div><h6 class="text-muted mb-1">回款率 <small class="badge bg-light text-secondary"><?=htmlspecialchars($period_label)?></small></h6><h3 class="mb-0"><?=$recovery_rate?>%</h3><small class="text-muted">已收 &yen;<?=number_format($received_amount)?></small></div><div class="stat-icon bg-success bg-opacity-10 text-success"><i class="bi bi-check-circle fs-4"></i></div></div></div></div></div>
  <div class="col-md-3 col-6"><div class="card stat-card" style="cursor:pointer" onclick="location.href='/remind'"><div class="card-body p-3"><div class="d-flex justify-content-between align-items-center"><div><h6 class="text-muted mb-1">今日待办</h6><h3 class="mb-0"><?=$pending_count+count($remind_alerts)+$msg_unread?></h3><small class="text-muted">审批 <?=$pending_count?> | 提醒 <?=count($remind_alerts)?></small></div><div class="stat-icon bg-info bg-opacity-10 text-info"><i class="bi bi-bell fs-4"></i></div></div></div></div></div>
<?php elseif(!empty($is_manager)): ?>
  <!-- 审批人（非管理员，部门经理画像：approval:approve + supplier:create）：待我审批 / 本范围生效合同总额 / 待回款 / 回款率（数据范围已由 appendDataScope 按角色收敛） -->
  <div class="col-md-3 col-6"><div class="card stat-card" style="cursor:pointer" onclick="location.href='/approval'"><div class="card-body p-3"><div class="d-flex justify-content-between align-items-center"><div><h6 class="text-muted mb-1">待我审批</h6><h3 class="mb-0 <?=$pending_count>0?'text-danger':''?>"><?=$pending_count?></h3><small class="text-muted">到期 <?=$expiring_soon?> 份</small></div><div class="stat-icon bg-info bg-opacity-10 text-info"><i class="bi bi-list-check fs-4"></i></div></div></div></div></div>
  <div class="col-md-3 col-6"><div class="card stat-card" style="cursor:pointer" onclick="location.href='/contract'"><div class="card-body p-3"><div class="d-flex justify-content-between align-items-center"><div><h6 class="text-muted mb-1">本范围生效合同总额 <small class="badge bg-light text-secondary"><?=htmlspecialchars($period_label)?></small></h6><h3 class="mb-0">&yen;<?=number_format($total_amount)?></h3><small class="text-muted"><?=$total_contracts?> 份</small></div><div class="stat-icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-cash-stack fs-4"></i></div></div></div></div></div>
  <div class="col-md-3 col-6"><div class="card stat-card" style="cursor:pointer" onclick="location.href='/contract?status=EXECUTING'"><div class="card-body p-3"><div class="d-flex justify-content-between align-items-center"><div><h6 class="text-muted mb-1">本范围待回款 <small class="badge bg-light text-secondary"><?=htmlspecialchars($period_label)?></small></h6><h3 class="mb-0 <?=$overdue_amount>0?'text-danger':''?>">&yen;<?=number_format($pending_amount+$overdue_amount)?></h3><small class="<?=$overdue_amount>0?'text-danger':'text-muted'?>">逾期 <?=$overdue_count?> 笔</small></div><div class="stat-icon <?=$overdue_amount>0?'bg-danger':'bg-warning'?> bg-opacity-10 <?=$overdue_amount>0?'text-danger':'text-warning'?>"><i class="bi bi-clock-history fs-4"></i></div></div></div></div></div>
  <div class="col-md-3 col-6"><div class="card stat-card" style="cursor:pointer" onclick="location.href='/finance'"><div class="card-body p-3"><div class="d-flex justify-content-between align-items-center"><div><h6 class="text-muted mb-1">回款率 <small class="badge bg-light text-secondary"><?=htmlspecialchars($period_label)?></small></h6><h3 class="mb-0"><?=$recovery_rate?>%</h3><small class="text-muted">已收 &yen;<?=number_format($received_amount)?></small></div><div class="stat-icon bg-success bg-opacity-10 text-success"><i class="bi bi-check-circle fs-4"></i></div></div></div></div></div>
<?php elseif(!empty($is_finance)): ?>
  <!-- 财务（非管理员，财务画像：payment:create + 无 supplier:create）：待回款 / 已收 / 回款率 / 生效合同总额 -->
  <div class="col-md-3 col-6"><div class="card stat-card" style="cursor:pointer" onclick="location.href='/contract?status=EXECUTING'"><div class="card-body p-3"><div class="d-flex justify-content-between align-items-center"><div><h6 class="text-muted mb-1">待回款 <small class="badge bg-light text-secondary"><?=htmlspecialchars($period_label)?></small></h6><h3 class="mb-0 <?=$overdue_amount>0?'text-danger':''?>">&yen;<?=number_format($pending_amount+$overdue_amount)?></h3><small class="<?=$overdue_amount>0?'text-danger':'text-muted'?>">逾期 <?=$overdue_count?> 笔</small></div><div class="stat-icon <?=$overdue_amount>0?'bg-danger':'bg-warning'?> bg-opacity-10 <?=$overdue_amount>0?'text-danger':'text-warning'?>"><i class="bi bi-clock-history fs-4"></i></div></div></div></div></div>
  <div class="col-md-3 col-6"><div class="card stat-card" style="cursor:pointer" onclick="location.href='/finance'"><div class="card-body p-3"><div class="d-flex justify-content-between align-items-center"><div><h6 class="text-muted mb-1">已收金额 <small class="badge bg-light text-secondary"><?=htmlspecialchars($period_label)?></small></h6><h3 class="mb-0 text-success">&yen;<?=number_format($received_amount)?></h3><small class="text-muted">应收 &yen;<?=number_format($total_receivable)?></small></div><div class="stat-icon bg-success bg-opacity-10 text-success"><i class="bi bi-cash-coin fs-4"></i></div></div></div></div></div>
  <div class="col-md-3 col-6"><div class="card stat-card" style="cursor:pointer" onclick="location.href='/finance'"><div class="card-body p-3"><div class="d-flex justify-content-between align-items-center"><div><h6 class="text-muted mb-1">回款率 <small class="badge bg-light text-secondary"><?=htmlspecialchars($period_label)?></small></h6><h3 class="mb-0"><?=$recovery_rate?>%</h3><small class="text-muted">本月预计 &yen;<?=number_format($month_expected)?></small></div><div class="stat-icon bg-success bg-opacity-10 text-success"><i class="bi bi-check-circle fs-4"></i></div></div></div></div></div>
  <div class="col-md-3 col-6"><div class="card stat-card" style="cursor:pointer" onclick="location.href='/contract'"><div class="card-body p-3"><div class="d-flex justify-content-between align-items-center"><div><h6 class="text-muted mb-1">生效合同总额 <small class="badge bg-light text-secondary"><?=htmlspecialchars($period_label)?></small></h6><h3 class="mb-0">&yen;<?=number_format($total_amount)?></h3><small class="text-muted"><?=$total_contracts?> 份</small></div><div class="stat-icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-cash-stack fs-4"></i></div></div></div></div></div>
<?php else: ?>
  <!-- 普通员工：我的生效合同 / 我的待回款 / 回款率 / 今日提醒（数据范围已由 appendDataScope 收敛为仅本人） -->
  <div class="col-md-3 col-6"><div class="card stat-card" style="cursor:pointer" onclick="location.href='/contract'"><div class="card-body p-3"><div class="d-flex justify-content-between align-items-center"><div><h6 class="text-muted mb-1">我的生效合同总额 <small class="badge bg-light text-secondary"><?=htmlspecialchars($period_label)?></small></h6><h3 class="mb-0">&yen;<?=number_format($total_amount)?></h3><small class="text-muted"><?=$total_contracts?> 份</small></div><div class="stat-icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-cash-stack fs-4"></i></div></div></div></div></div>
  <div class="col-md-3 col-6"><div class="card stat-card" style="cursor:pointer" onclick="location.href='/contract?status=EXECUTING'"><div class="card-body p-3"><div class="d-flex justify-content-between align-items-center"><div><h6 class="text-muted mb-1">我的待回款 <small class="badge bg-light text-secondary"><?=htmlspecialchars($period_label)?></small></h6><h3 class="mb-0 <?=$overdue_amount>0?'text-danger':''?>">&yen;<?=number_format($pending_amount+$overdue_amount)?></h3><small class="<?=$overdue_amount>0?'text-danger':'text-muted'?>">逾期 <?=$overdue_count?> 笔</small></div><div class="stat-icon <?=$overdue_amount>0?'bg-danger':'bg-warning'?> bg-opacity-10 <?=$overdue_amount>0?'text-danger':'text-warning'?>"><i class="bi bi-clock-history fs-4"></i></div></div></div></div></div>
  <div class="col-md-3 col-6"><div class="card stat-card" style="cursor:pointer" onclick="location.href='/finance'"><div class="card-body p-3"><div class="d-flex justify-content-between align-items-center"><div><h6 class="text-muted mb-1">我的回款率 <small class="badge bg-light text-secondary"><?=htmlspecialchars($period_label)?></small></h6><h3 class="mb-0"><?=$recovery_rate?>%</h3><small class="text-muted">本月预计 &yen;<?=number_format($month_expected)?></small></div><div class="stat-icon bg-success bg-opacity-10 text-success"><i class="bi bi-check-circle fs-4"></i></div></div></div></div></div>
  <div class="col-md-3 col-6"><div class="card stat-card" style="cursor:pointer" onclick="location.href='/remind'"><div class="card-body p-3"><div class="d-flex justify-content-between align-items-center"><div><h6 class="text-muted mb-1">今日提醒</h6><h3 class="mb-0"><?=count($remind_alerts)+$msg_unread?></h3><small class="text-muted">审批消息 <?=$msg_unread?> 未读</small></div><div class="stat-icon bg-info bg-opacity-10 text-info"><i class="bi bi-bell fs-4"></i></div></div></div></div></div>
<?php endif; ?>
</div>

<!-- 本月经营 + 收支方向 -->
<div class="row g-3 mb-3">
  <div class="col-lg-7">
    <div class="card stat-card h-100"><div class="card-header bg-white py-2"><h6 class="mb-0"><i class="bi bi-graph-up-arrow"></i> <?=$period_label=='累计'?'本月':$period_label?>经营 <small class="text-muted">（<?=htmlspecialchars($period_label)?>）</small></h6></div>
    <div class="card-body"><div class="row text-center g-2">
      <div class="col-4"><div class="text-muted small mb-1"><?=$period_label=='累计'?'本月':$period_label?>已收</div><h4 class="mb-0 text-success">&yen;<?=number_format($month_received)?></h4></div>
      <div class="col-4"><div class="text-muted small mb-1"><?=$period_label=='累计'?'本月':$period_label?>预计回款</div><h4 class="mb-0">&yen;<?=number_format($month_expected)?></h4></div>
      <div class="col-4" style="cursor:pointer" onclick="location.href='/approval'"><div class="text-muted small mb-1">待我审批</div><h4 class="mb-0 <?=$pending_count>0?'text-danger':''?>"><?=$pending_count?></h4></div>
    </div></div></div>
  </div>
  <div class="col-lg-5">
    <div class="card stat-card h-100"><div class="card-header bg-white py-2"><h6 class="mb-0"><i class="bi bi-arrow-left-right"></i> 收支方向概览 <small class="text-muted">（<?=htmlspecialchars($period_label)?>）</small></h6></div>
    <div class="card-body"><div class="row text-center g-2">
      <div class="col-6 border-end" style="cursor:pointer" onclick="location.href='/contract?direction=sales'"><div class="text-muted small mb-1"><span class="pc-tag pc-tag-ok">销售</span> 应收</div><h5 class="mb-0 text-success">&yen;<?=number_format($dir_summary['sales']['total'])?></h5><small class="text-muted"><?=$dir_summary['sales']['cnt']?> 份 · 我方收款</small></div>
      <div class="col-6" style="cursor:pointer" onclick="location.href='/contract?direction=purchase'"><div class="text-muted small mb-1"><span class="pc-tag pc-tag-warn">采购</span> 应付</div><h5 class="mb-0 text-warning">&yen;<?=number_format($dir_summary['purchase']['total'])?></h5><small class="text-muted"><?=$dir_summary['purchase']['cnt']?> 份 · 我方付款</small></div>
    </div></div></div>
  </div>
</div>

<!-- 近6季度趋势（Chart.js 双色柱状图，支持 hover tooltip / Y轴刻度 / 动画） -->
<div class="card stat-card mb-4"><div class="card-header bg-white d-flex justify-content-between align-items-center"><h6 class="mb-0"><i class="bi bi-bar-chart"></i> 近6季度趋势（合同金额 vs 已收回款）</h6><div class="d-flex gap-3 small text-muted"><span><i class="bi bi-square-fill" style="color:var(--primary)"></i> 合同金额</span><span><i class="bi bi-square-fill" style="color:#07c160"></i> 已收回款</span></div></div>
<div class="card-body">
<div style="position:relative;overflow:hidden;width:100%">
<canvas id="trendChart" height="150"></canvas>
</div>
</div></div>
<script>
// v2.43.0【C-档3】图表初始化改为可重入函数 renderTrendChart：
// 首次整页加载时 Chart.js 为懒加载（未注入），此处仅定义函数不执行；
// Chart 就绪后由 index.php 的 loadChartLibs 回调调用；周期刷新（AJAX 重建本模板）时 Chart 已就绪则立即执行。
window.renderTrendChart = function() {
  if (typeof Chart === 'undefined') return;
  var cv = document.getElementById('trendChart');
  if (!cv || (Chart.getChart && Chart.getChart(cv))) return;
  if (typeof ChartDataLabels !== 'undefined') Chart.register(ChartDataLabels);
  var maxV = Math.max(1, Math.max.apply(null, <?=json_encode(array_column($trend_data,'amount'), JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>.concat(<?=json_encode(array_column($trend_data,'received'), JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>)));
  new Chart(cv.getContext('2d'),{
  type:'bar',
  data:{
    labels:<?=json_encode(array_column($trend_data,'month'), JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>,
    datasets:[
      {label:'合同金额',data:<?=json_encode(array_column($trend_data,'amount'), JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>,backgroundColor:'rgba(13,110,253,.7)',borderColor:'rgba(13,110,253,1)',borderRadius:4,maxBarThickness:24},
      {label:'已收回款',data:<?=json_encode(array_column($trend_data,'received'), JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>,backgroundColor:'rgba(7,193,96,.7)',borderColor:'rgba(7,193,96,1)',borderRadius:4,maxBarThickness:24}
    ]
  },
  options:{
    responsive:true,maintainAspectRatio:false,
    layout:{padding:{top:28}},
    plugins:{
      legend:{display:false},
      tooltip:{callbacks:{label:function(c){return c.dataset.label+': ¥'+c.parsed.y.toLocaleString()}}},
      datalabels:{
        anchor:'end',align:'top',offset:2,
        color:function(ctx){return ctx.datasetIndex===0?'rgba(13,110,253,1)':'rgba(7,193,96,1)'},
        font:{weight:'600',size:10},
        formatter:function(v){if(v<=0)return null;return v>=10000?Math.round(v/10000)+'万':'¥'+v.toLocaleString();}
      }
    },
    scales:{
      y:{beginAtZero:true,max:Math.ceil(maxV*1.18),ticks:{callback:function(v){return v>=10000?Math.round(v/10000)+'万':v},font:{size:10}},grid:{color:'rgba(0,0,0,.06)'}},
      x:{ticks:{font:{size:11}},grid:{display:false}}
    }
  }
});};
if (typeof Chart !== 'undefined') window.renderTrendChart();
</script>
