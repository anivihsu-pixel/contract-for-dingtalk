<?php $title='仪表盘'; $menu_active='dashboard';
$sc=['DRAFT'=>'muted','PENDING_APPROVAL'=>'warn','APPROVED'=>'info','SIGNED'=>'info','EXECUTING'=>'ok','COMPLETED'=>'muted','EXPIRED'=>'danger'];
include __DIR__.'/../layout/header.php'; ?>
<style>
.trend-bars{display:flex;align-items:flex-end;gap:12px;height:100px;padding:0 4px}
.trend-bar{flex:1;background:linear-gradient(180deg,var(--primary) 0%,#d4e4fb 100%);border-radius:4px 4px 0 0;min-width:20px;position:relative}
.trend-bar .bar-label{position:absolute;bottom:-18px;left:50%;transform:translateX(-50%);font-size:10px;color:#6c757d}
.trend-bar .bar-value{position:absolute;top:-16px;left:50%;transform:translateX(-50%);font-size:10px;font-weight:600}
.status-strip{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:16px}
.payment-row{cursor:pointer;transition:background .15s}.payment-row:hover{background:#f0f4ff}.payment-row.overdue{background:#fff5f5}.payment-row.paid{background:#f5fff5}.payment-row.overdue:hover{background:#ffe8e8}.payment-row.paid:hover{background:#e8f5e8}
.table-hover tbody tr:hover{background:#f0f4ff!important;cursor:pointer}
/* v2.38.17：周期筛选 chips（与移动端报表页同风格） */
.period-chips{display:inline-flex;gap:4px;background:#f1f3f5;border-radius:8px;padding:3px}
.period-chips a{display:inline-block;padding:4px 14px;font-size:13px;border-radius:6px;color:#495057;text-decoration:none}
.period-chips a.active{background:#fff;color:var(--primary);font-weight:600;box-shadow:0 1px 3px rgba(0,0,0,.12)}
</style>

<!-- 快捷操作（动作型入口，对齐移动端工作台；按权限裁剪；周期筛选随数据区刷新见 _partial.php） -->
<div class="card stat-card mb-4">
  <div class="card-header bg-white"><h6 class="mb-0"><i class="bi bi-grid-3x3-gap text-primary"></i> 快捷操作</h6></div>
  <div class="card-body py-2">
    <div class="d-flex flex-wrap gap-2">
      <?php if(!empty($can_create)): ?><a href="/contract/create" class="btn btn-primary btn-sm"><i class="bi bi-file-earmark-plus"></i> 新建合同</a><?php endif; ?>
      <?php if(!empty($can_create_customer)): ?><a href="/customer/create" class="btn btn-outline-primary btn-sm"><i class="bi bi-person-plus"></i> 新建客户</a><?php endif; ?>
      <?php if(!empty($can_approve)): ?><a href="/approval" class="btn btn-outline-info btn-sm"><i class="bi bi-list-check"></i> 审批</a><?php endif; ?>
      <?php if(!empty($can_pay)): ?><a href="/finance#add" class="btn btn-outline-success btn-sm"><i class="bi bi-cash-coin"></i> 登记回款</a><?php endif; ?>
      <?php if(!empty($is_admin) || in_array('invoice:apply', $user_permissions ?? [], true)): ?><a href="/invoice-apply" class="btn btn-outline-warning btn-sm"><i class="bi bi-receipt-cutoff"></i> 申请开票</a><?php endif; ?>
    </div>
  </div>
</div>

<!-- 今日提醒 + 近期回款：宽屏两列并排（避免提醒单卡过宽空旷），窄屏自动堆叠 -->
<div class="row g-3 mb-4">
<div class="col-lg-6">
<div class="card stat-card h-100">
  <div class="card-header bg-white d-flex justify-content-between align-items-center">
    <h6 class="mb-0"><i class="bi bi-bell"></i> 今日提醒 <span class="badge bg-danger rounded-pill ms-1"><?=count($remind_alerts)?></span></h6>
    <a href="/remind" class="btn btn-sm btn-outline-secondary">查看全部</a>
  </div>
  <?php if(!empty($msg_unread)): ?>
  <div class="card-body py-2 px-3 small d-flex justify-content-between align-items-center" style="background:#fff8e1">
    <span><i class="bi bi-chat-left-text text-primary"></i> 你有 <?=$msg_unread?> 条审批消息未读（驳回/通过/转交等）</span>
    <a href="/remind" class="fw-bold text-decoration-none">前往查看 <i class="bi bi-chevron-right"></i></a>
  </div>
  <?php endif; ?>
  <div class="card-body p-0" style="max-height:220px;overflow-y:auto">
  <?php if(!empty($remind_alerts)): foreach($remind_alerts as $a): ?>
    <a href="/contract/<?=$a['id']?>" class="p-2 border-bottom d-block text-decoration-none text-reset"><i class="bi bi-<?=$a['level']=='danger'?'exclamation-triangle-fill text-danger':($a['level']=='warning'?'exclamation-circle text-warning':'info-circle text-info')?>"></i> <?=htmlspecialchars($a['text'])?></a>
  <?php endforeach; else: ?>
    <div class="text-center py-3 text-muted small">今日暂无提醒 🎉</div>
  <?php endif; ?>
  </div>
</div>
</div>
<div class="col-lg-6">
<div class="card stat-card h-100"><div class="card-header bg-white"><h6 class="mb-0"><i class="bi bi-cash-coin"></i> 近期回款（未来30天）</h6></div><div class="card-body p-0" style="max-height:220px;overflow-y:auto">
<?php if(!empty($upcoming_payments)): foreach($upcoming_payments as $p): $pt=trim((string)($p['contract_title'] ?? ''))!==''?$p['contract_title']:($p['contract_no']??''); ?>
<div class="p-2 border-bottom payment-row <?=$p['status']=='OVERDUE'?'overdue':($p['status']=='PAID'?'paid':'')?>" onclick="location.href='/contract/<?=intval($p['contract_id'])?>'"><div class="d-flex justify-content-between align-items-center"><div class="text-truncate"><div class="fw-bold text-truncate" title="<?=htmlspecialchars($pt)?>"><?=htmlspecialchars($pt)?></div><small class="text-muted">&yen;<?=number_format($p['amount'])?> · <?=htmlspecialchars($p['contract_no']??'')?></small></div><div class="text-end ms-2 flex-shrink-0"><small class="text-muted"><?=$p['planned_date']?></small> <?php if($p['status']=='PAID'): ?><span class="pc-tag pc-tag-ok">已收</span><?php elseif($p['status']=='OVERDUE'): ?><span class="pc-tag pc-tag-danger">逾期</span><?php else: ?><span class="pc-tag pc-tag-warn">待收</span><?php endif;?></div></div></div>
<?php endforeach; else: ?><div class="text-center py-3 text-muted small">暂无近期回款计划</div><?php endif; ?>
</div></div>
</div>
</div>

<!-- v2.43.0：Chart.js 改视口懒加载（趋势图可见才注入，省首屏 ~213KB 脚本解析），见本页底部 loadChartLibs -->
<!-- v2.40.0：草稿待处理（数据权限范围内最新 5 条，点击直达详情；「查看全部」跳列表草稿筛选） -->
<?php if(!empty($draft_contracts['list'])): ?>
<div class="card stat-card mb-3">
  <div class="card-header bg-white d-flex justify-content-between align-items-center">
    <h6 class="mb-0"><i class="bi bi-pencil-square text-warning"></i> 草稿待处理
      <span class="badge bg-warning text-dark rounded-pill ms-1"><?=(int)($draft_contracts['total'])?></span></h6>
    <a href="/contract?status=DRAFT" class="btn btn-sm btn-outline-secondary"><i class="bi bi-list-ul"></i> 查看全部草稿</a>
  </div>
  <div class="table-responsive"><table class="table table-hover mb-0"><thead class="table-light"><tr><th>编号</th><th>标题</th><th>归属人</th><th class="text-end">金额</th><th>更新时间</th></tr></thead><tbody>
  <?php foreach($draft_contracts['list'] as $d): ?>
  <tr role="link" tabindex="0" onclick="location.href='/contract/<?=$d['id']?>'" style="cursor:pointer" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();location.href='/contract/<?=$d['id']?>';}">
    <td><small><?=htmlspecialchars($d['contract_no'])?></small></td>
    <td><?=htmlspecialchars($d['title'])?></td>
    <td><?=htmlspecialchars($d['owner_name'] ?: '—')?></td>
    <td class="text-end">&yen;<?=format_money($d['amount'])?></td>
    <td><small><?=htmlspecialchars(substr($d['updated_at'] ?? '', 0, 16))?></small></td>
  </tr>
  <?php endforeach; ?>
  </tbody></table></div>
</div>
<?php endif; ?>
<!-- 合同状态分布（上移至 KPI 上方，更醒目） -->
<div class="card stat-card mb-3">
  <div class="card-body py-2">
    <div class="d-flex align-items-center flex-wrap gap-2">
      <span class="text-muted small fw-bold me-2">合同状态</span>
      <?php foreach($status_counts as $s=>$c): if($c>0): ?>
      <span class="pc-tag pc-tag-<?=$sc[$s]??'muted'?>"><?=dict('contract_status',$s)?> <strong><?=$c?></strong></span>
      <?php endif; endforeach; ?>
    </div>
  </div>
</div>

<!-- 动态区：KPI + 经营/收支 + 趋势（周期切换时 AJAX 局部刷新） -->
<div id="dashPartial">
<?php include __DIR__.'/_partial.php'; ?>
</div>

<!-- 按部门经营（v2.39.0：管理层概览，累计口径，非排行榜；仅管理员可见） -->
<?php if(!empty($dept_summary)): ?>
<div class="card stat-card mb-4"><div class="card-header bg-white d-flex justify-content-between align-items-center"><h6 class="mb-0"><i class="bi bi-diagram-3"></i> 按部门经营 <small class="text-muted">（累计）</small></h6><small class="text-muted">交易合同口径 · 仅供管理层概览</small></div>
<div class="table-responsive"><table class="table table-hover mb-0"><thead class="table-light"><tr><th>部门</th><th class="text-end">合同数</th><th class="text-end">合同额</th><th class="text-end">已收回款</th><th style="width:180px">回款率</th></tr></thead><tbody>
<?php foreach($dept_summary as $d): $bar=($d['recovery_rate']>=80?'bg-success':($d['recovery_rate']>=50?'bg-warning':'bg-danger')); ?>
<tr>
<td><span class="pc-tag pc-tag-info"><i class="bi bi-people me-1"></i><?=htmlspecialchars($d['dept_name'])?></span></td>
<td class="text-end"><?=intval($d['cnt'])?> 份</td>
<td class="text-end">&yen;<?=number_format($d['total_amount'])?></td>
<td class="text-end text-success">&yen;<?=number_format($d['paid_amount'])?></td>
<td><div class="d-flex align-items-center gap-2"><div class="progress flex-grow-1" style="height:8px"><div class="progress-bar <?=$bar?>" style="width:<?=min($d['recovery_rate'],100)?>%"></div></div><small class="text-muted" style="min-width:38px"><?=$d['recovery_rate']?>%</small></div></td>
</tr>
<?php endforeach; ?>
</tbody></table></div></div>
<?php endif; ?>

<!-- 按项目 TOP N -->
<?php if(!empty($top_projects)): ?>
<div class="card stat-card mb-4"><div class="card-header bg-white d-flex justify-content-between align-items-center"><h6 class="mb-0"><i class="bi bi-folder2-open"></i> 按项目 TOP <?=count($top_projects)?>（应收/回款率）</h6><a href="/project" class="btn btn-outline-secondary btn-sm">全部项目</a></div>
<div class="table-responsive"><table class="table table-hover mb-0"><thead class="table-light"><tr><th>项目</th><th class="text-end">应收</th><th class="text-end">已收</th><th style="width:180px">回款率</th></tr></thead><tbody>
<?php foreach($top_projects as $tp): $rate=$tp['recovery_rate']; $bar=($rate>=80?'bg-success':($rate>=50?'bg-warning':'bg-danger')); ?>
<tr role="link" tabindex="0" onclick="location.href='/project/<?=$tp['id']?>'" style="cursor:pointer" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();location.href='/project/<?=$tp['id']?>';}">
<td><span class="pc-tag pc-tag-info"><i class="bi bi-folder2 me-1"></i><?=htmlspecialchars($tp['name'])?></span></td>
<td class="text-end">&yen;<?=number_format($tp['receivable'])?></td>
<td class="text-end text-success">&yen;<?=number_format($tp['received'])?></td>
<td><div class="d-flex align-items-center gap-2"><div class="progress flex-grow-1" style="height:8px"><div class="progress-bar <?=$bar?>" style="width:<?=min($rate,100)?>%"></div></div><small class="text-muted" style="min-width:38px"><?=$rate?>%</small></div></td>
</tr>
<?php endforeach; ?>
</tbody></table></div></div>
<?php endif; ?>

<!-- 最近合同（快捷操作已上移至页面顶部，此处保留查看入口） -->
<div class="card stat-card"><div class="card-header bg-white d-flex justify-content-between align-items-center"><h5 class="mb-0">最近合同</h5>
<a href="/contract" class="btn btn-outline-secondary btn-sm"><i class="bi bi-list-ul"></i> 全部合同</a>
</div>
<div class="table-responsive"><table class="table table-hover mb-0"><thead class="table-light"><tr><th>编号</th><th>标题</th><th>分类</th><th>金额</th><th>状态</th><th>乙方</th><th>日期</th></tr></thead><tbody>
<?php if(!empty($recent_contracts)): foreach($recent_contracts as $c): ?>
<tr role="link" tabindex="0" onclick="location.href='/contract/<?=$c['id']?>'" style="cursor:pointer" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();location.href='/contract/<?=$c['id']?>';}">
<td><small><?=htmlspecialchars($c['contract_no'])?></small></td><td><?=htmlspecialchars($c['title'])?></td><td><?=contract_category_name($c['category'])?></td>
<td class="text-end">&yen;<?=format_money($c['amount'])?></td><td><?=contract_status_label($c['status'])?></td>
<td><?=htmlspecialchars($c['party_b_name'])?></td><td><small><?=$c['created_at']?></small></td></tr>
<?php endforeach; else: ?><tr><td colspan="7" class="text-center py-4 text-muted">暂无合同数据</td></tr><?php endif; ?>
</tbody></table></div></div>
<script>
// 周期筛选 AJAX 局部刷新（本月/本季/本年/累计），复用 dashboard/_partial.php
// 使用通用 bindPartialRefresh（事件委托 + 防重复 + 加载状态 + script 重建）
// DOMContentLoaded 确保 app.js（定义 bindPartialRefresh）已加载完毕
document.addEventListener('DOMContentLoaded', function() {
  bindPartialRefresh('#dashPartial', '#periodChips a[data-period]', function(el) {
    return '/dashboard?period=' + encodeURIComponent(el.getAttribute('data-period'));
  });
});

// v2.43.0【C-档3】Chart.js 视口懒加载：趋势图位于 #dashPartial 动态区（周期刷新仅替换其内部、容器节点不变），
// 容器进入视口才串行注入 chart.umd.min.js → datalabels；未进入视口不下载（省首屏 ~213KB 脚本解析）。
// 库就绪后调用 window.renderTrendChart（_partial.php 定义的可重入初始化，首次整页加载时补渲染趋势图）。
function loadChartLibs() {
  if (window.__chartLoading || (typeof Chart !== 'undefined' && typeof ChartDataLabels !== 'undefined')) return;
  window.__chartLoading = true;
  var s1 = document.createElement('script');
  s1.src = '<?=asset_url('vendor/chart.js/chart.umd.min.js')?>';
  var s2 = document.createElement('script');
  s2.src = '<?=asset_url('vendor/chart.js/chartjs-plugin-datalabels.min.js')?>';
  s1.onload = function() {
    s2.onload = function() {
      window.__chartLoading = false;
      if (typeof window.renderTrendChart === 'function') window.renderTrendChart();   // 库就绪后补渲染趋势图
    };
    s2.onerror = function() { window.__chartLoading = false; };   // 加载失败释放锁，允许再次触发重试
    document.head.appendChild(s2);   // datalabels 依赖 Chart 定义，串行加载
  };
  s1.onerror = function() { window.__chartLoading = false; };     // 加载失败释放锁，允许再次触发重试
  document.head.appendChild(s1);
}
document.addEventListener('DOMContentLoaded', function() {
  var box = document.getElementById('dashPartial');
  if (box && 'IntersectionObserver' in window) {
    var io = new IntersectionObserver(function(entries) {
      entries.forEach(function(en) { if (en.isIntersecting) { loadChartLibs(); io.disconnect(); } });
    }, { rootMargin: '200px' });
    io.observe(box);
  } else {
    loadChartLibs();
  }
});
</script>
<?php include __DIR__.'/../layout/footer.php'; ?>
