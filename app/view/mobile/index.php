<?php
// 移动端共享布局（Phase 1 重构 1.7）：头部与尾部由 _head.php / _foot.php 统一输出
$title = '移动工作台';   // 页面标题，自动追加「 · 合同管理」
$tab = 'home';     // 底部导航高亮：home/contract/customer/more
$pageStyle = <<<'CSS'

  :root{ --brand:#0b5ed7; }
  body{ background:#f2f3f5; -webkit-font-smoothing:antialiased; padding-bottom:76px; }
  .m-header{ background:linear-gradient(135deg,#0b5ed7,#3d8bfd); color:#fff; padding:16px 16px 20px; display:flex; align-items:flex-start; justify-content:space-between; }
  .m-header-left{ flex:1; min-width:0; }
  .m-header-bell{ position:relative; color:#fff; font-size:22px; padding:2px 0 0 8px; flex-shrink:0; text-decoration:none; }
  .m-header-bell .bell-badge{ position:absolute; top:-4px; right:-10px; background:#ff4d4f; color:#fff; font-size:10px; font-weight:600; min-width:18px; height:18px; line-height:18px; border-radius:9px; text-align:center; padding:0 5px; border:2px solid #0b5ed7; }
  .m-header h1{ font-size:18px; font-weight:600; margin:0; }
  .m-header .sub{ font-size:12px; opacity:.85; margin-top:2px; }
  .m-card-hd{ padding:12px 14px; font-weight:600; font-size:15px; display:flex; align-items:center; justify-content:space-between; border-bottom:1px solid #f0f0f0; }
  .m-card-bd{ padding:6px 14px 12px; }
  .amt{ color:var(--m-danger); font-weight:600; }
  .amt.in{ color:var(--m-danger); } /* 应收=红 */
  .amt.out{ color:var(--m-success); } /* 应付=绿 */
  .stat-row{ display:flex; gap:10px; padding:0 12px; }
  .stat-box{ flex:1; background:#fff; border-radius:14px; padding:14px; text-align:center; box-shadow:0 1px 3px rgba(0,0,0,.06); }
  .stat-box .n{ font-size:24px; font-weight:700; color:#0b5ed7; }
  .stat-box .l{ font-size:12px; color:#8a9099; margin-top:2px; }
  .quick-grid{ display:flex; flex-wrap:nowrap; gap:8px; overflow-x:auto; -webkit-overflow-scrolling:touch; scrollbar-width:none; }
  .quick-grid::-webkit-scrollbar{ display:none; }
  .quick-item{ flex:0 0 calc(25% - 6px); min-width:0; display:flex; flex-direction:column; align-items:center; gap:6px; padding:10px 0; color:#1f2329; text-decoration:none; }
  .quick-item i{ font-size:24px; color:var(--brand); }
  .quick-item span{ font-size:12px; color:#646a73; }
  /* v2.38.26：快捷操作溢出提示——右侧渐隐 + 标题「左右滑动」小字（仅当图标超过一屏时显示） */
  #quick{ position:relative; }
  #quick .quick-tip{ font-size:11px; color:#8a9099; font-weight:400; display:none; align-items:center; gap:3px; }
  #quick.has-more .quick-tip{ display:inline-flex; }
  #quick.has-more::after{ content:''; position:absolute; top:42px; right:0; bottom:0; width:26px; background:linear-gradient(to left, rgba(255,255,255,.95), rgba(255,255,255,0)); pointer-events:none; }
  /* v2.40.0 P1-7：快捷操作下方分段式指示器——2 段小横线，第 1 段高亮=当前屏，提示右侧还有图标（一屏 4 个） */
  .quick-scrollbar{ display:none; justify-content:center; align-items:center; gap:4px; margin:4px 12px 10px; }
  #quick.has-more .quick-scrollbar{ display:flex; }
  .quick-scrollbar i{ display:block; width:12px; height:3px; border-radius:2px; background:#dde0e3; transition:background-color .2s; }
  .quick-scrollbar i.cur{ background:var(--brand); opacity:.6; }
  /* v2.40.1：新建 FAB 展开菜单——按钮本体复用 mobile.css .m-fab（与合同列表「新增」同款固定定位），
     仅补充展开遮罩/菜单/菜单项样式；展开态用 body.fab-open 驱动 */
  .m-fab-mask{ position:fixed; inset:0; background:rgba(0,0,0,.45); opacity:0; visibility:hidden; transition:opacity .2s; z-index:54; }
  .m-fab i{ transition:transform .2s; }
  body.fab-open .m-fab i{ transform:rotate(45deg); }
  .m-fab-menu{ position:fixed; right:16px; bottom:calc(var(--m-tabbar-h) + var(--safe-bottom) + 80px); z-index:56; display:flex; flex-direction:column; gap:10px; align-items:flex-end; opacity:0; visibility:hidden; transform:translateY(8px); transition:opacity .2s, transform .2s; }
  .m-fab-item{ display:flex; align-items:center; gap:8px; background:#fff; color:#1f2329; border-radius:22px; padding:10px 16px; font-size:14px; font-weight:500; white-space:nowrap; width:max-content; box-shadow:0 2px 10px rgba(0,0,0,.12); }
  .m-fab-item span{ white-space:nowrap; flex-shrink:0; }
  .m-fab-item i{ display:block; flex-shrink:0; font-size:18px; color:var(--brand); }
  .m-fab-item:active{ background:#f2f3f5; }
  body.fab-open .m-fab-mask{ opacity:1; visibility:visible; }
  body.fab-open .m-fab-menu{ opacity:1; visibility:visible; transform:translateY(0); }
  .sug{ position:absolute; left:0; right:0; top:100%; background:#fff; border:1px solid #eee; border-radius:8px; max-height:210px; overflow:auto; z-index:30; box-shadow:0 4px 12px rgba(0,0,0,.08); display:none; }
  .sug div{ padding:9px 12px; font-size:13px; border-bottom:1px solid #f5f5f5; }
  .sug div:active{ background:#f2f7ff; }
CSS;
include __DIR__ . '/_head.php';
?>


<div class="m-header">
  <div class="m-header-left">
    <h1>移动工作台</h1>
    <div class="sub"><?=htmlspecialchars($user['name'] ?? '我')?>，<?=date('n月j日')?> · 合同管理</div>
  </div>
  <!-- 2026-08-05 去重：站内信并入待办中心 Tab3，铃铛直达 /m/remind?tab=notif -->
  <a href="/m/remind?tab=notif" class="m-header-bell" title="待办中心">
    <i class="bi bi-bell"></i>
    <?php if(($notif_unread ?? 0) > 0): ?>
    <span class="bell-badge"><?=$notif_unread > 99 ? '99+' : $notif_unread?></span>
    <?php endif; ?>
  </a>
</div>

<!-- 概览（可点击跳转对应模块，作为导航枢纽；v2.38.15：无审批权限者不显示「待我审批」数字卡） -->
<div class="stat-row" style="margin-top:-8px; position:relative;">
  <?php if(!empty($can_approve)): ?><a href="/m/approvals" class="stat-box" style="text-decoration:none;color:inherit"><div class="n"><?=$pending_total?></div><div class="l">待我审批</div></a><?php endif; ?>
  <a href="/m/remind" class="stat-box" style="text-decoration:none;color:inherit"><div class="n"><?=$todo_total ?? ($total_remind ?? count($alerts))?></div><div class="l">今日提醒</div></a>
  <a href="/m/contracts" class="stat-box" style="text-decoration:none;color:inherit"><div class="n"><?=$my_contracts_total?></div><div class="l">我的合同</div></a>
</div>

<!-- v2.40.0：管理层差异化卡片——总经理看全公司部门排名，部门经理看本部门汇总+成员排名
     v2.40.8：渲染条件由「有权限且有数据」放宽为「有权限」——全新部署/暂无生效合同时，
     卡片外壳照常渲染并显示空态提示，避免有权限但无数据时入口整体消失（误以为配置失败） -->
<?php if(!empty($dept_title)):
  $wan = function($n) { return $n >= 10000 ? round($n / 10000, 1) . '万' : (string)(int)$n; };
  $deptCnt = count($dept_overview);
  $showCollapse = $is_general_manager && $deptCnt > 3;  // 总经理 + 部门>3 才折叠
?>
<style>
  .dept-card{ margin:12px; background:#fff; border-radius:14px; box-shadow:0 1px 3px rgba(0,0,0,.06); overflow:hidden; }
  .dept-card-hd{ display:flex; align-items:center; gap:8px; padding:12px 14px; background:linear-gradient(135deg,#1f2329,#3a3f45); color:#fff; }
  .dept-card-hd .t{ font-weight:600; font-size:15px; flex:1; }
  .dept-card-hd .ic{ width:28px;height:28px;border-radius:8px;background:rgba(255,255,255,.15);display:flex;align-items:center;justify-content:center; }
  .dept-card-hd .ic i{ font-size:16px; }
  .dept-row{ display:flex; align-items:center; gap:8px; padding:11px 14px; border-bottom:1px solid #f5f5f5; }
  .dept-row:last-child{ border-bottom:none; }
  .dept-row .rank{ width:20px; height:20px; border-radius:50%; background:#f2f3f5; color:#8a9099; font-size:11px; font-weight:600; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
  .dept-row .rank.top1{ background:#fef0e6; color:#fa8c16; }
  .dept-row .rank.top2{ background:#f5f5f5; color:#8a9099; }
  .dept-row .rank.top3{ background:#fff3e0; color:#d46b08; }
  .dept-row .name{ flex:1; min-width:0; font-size:14px; color:#1f2329; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  .dept-row .meta{ font-size:11px; color:#8a9099; margin-top:2px; }
  .dept-row .amt{ font-size:14px; font-weight:600; color:var(--brand); flex-shrink:0; }
  .dept-row .rate{ font-size:11px; color:#18a058; flex-shrink:0; min-width:42px; text-align:right; }
  .dept-row .rate.low{ color:#d03050; }
  .dept-card-sub{ padding:8px 14px 10px; font-size:11px; color:#8a9099; background:#fafbfc; border-top:1px solid #f0f0f0; }
  /* v2.40.0：部门>3 时折叠第 4 项起，点「展开全部」显示 */
  .dept-card.collapsed .dept-row:nth-child(n+5){ display:none; }
  .dept-toggle{ padding:10px 14px; text-align:center; font-size:13px; color:var(--brand); border-top:1px solid #f0f0f0; cursor:pointer; }
  .dept-toggle:active{ background:#f5f7fa; }
  /* v2.40.8：有权限但暂无生效合同数据时的空态提示 */
  .dept-empty{ padding:22px 14px; text-align:center; font-size:13px; color:#8a9099; }
</style>
<div class="dept-card<?=$showCollapse ? ' collapsed' : ''?>" id="deptCard">
  <div class="dept-card-hd">
    <div class="ic"><i class="bi bi-<?= $is_general_manager ? 'building' : 'people-fill' ?>"></i></div>
    <div class="t"><?=htmlspecialchars($dept_title)?></div>
    <?php if($is_general_manager && $deptCnt > 0): ?><span style="font-size:11px;opacity:.8"><?=$deptCnt?> 个部门</span><?php endif; ?>
    <!-- v2.47.0：总经理经营周报入口（全公司口径，dashboard:company；点进 /m/report/weekly 看上周增量与逾期） -->
    <?php if($is_general_manager): ?><a href="/m/report/weekly" style="font-size:11px;color:#fff;opacity:.9;text-decoration:none;display:flex;align-items:center;gap:2px;white-space:nowrap;padding:3px 8px;border-radius:999px;background:rgba(255,255,255,.18)"><i class="bi bi-calendar-check"></i>经营周报</a><?php endif; ?>
  </div>
  <?php if(empty($dept_overview) && empty($dept_members)): ?>
    <div class="dept-empty">暂无生效合同数据</div>
  <?php endif; ?>
  <?php if(!empty($dept_overview)): foreach($dept_overview as $i => $d):
    $rk = $i + 1;
    $rkCls = $rk <= 3 ? ' top' . $rk : '';
  ?>
    <div class="dept-row">
      <div class="rank<?=$rkCls?>"><?=$rk?></div>
      <div style="flex:1;min-width:0">
        <div class="name"><?=htmlspecialchars($d['dept_name'])?></div>
        <div class="meta"><?=$d['cnt']?> 份 · 回款 <?=$wan($d['paid_amount'])?></div>
      </div>
      <div class="amt"><?=$wan($d['total_amount'])?></div>
      <div class="rate<?=$d['recovery_rate'] < 50 ? ' low' : ''?>"><?=$d['recovery_rate']?>%</div>
    </div>
  <?php endforeach; endif; ?>
  <?php if(!empty($dept_members)): ?>
    <div class="dept-card-sub">本部门成员排名（按合同额）</div>
    <?php foreach($dept_members as $i => $m):
      $rk = $i + 1;
      $rkCls = $rk <= 3 ? ' top' . $rk : '';
    ?>
    <div class="dept-row">
      <div class="rank<?=$rkCls?>"><?=$rk?></div>
      <div style="flex:1;min-width:0">
        <div class="name"><?=htmlspecialchars($m['user_name'])?></div>
        <div class="meta"><?=$m['cnt']?> 份 · 回款 <?=$wan($m['paid_amount'])?></div>
      </div>
      <div class="amt"><?=$wan($m['total_amount'])?></div>
      <div class="rate<?=$m['recovery_rate'] < 50 ? ' low' : ''?>"><?=$m['recovery_rate']?>%</div>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>
  <?php if($showCollapse): ?>
    <div class="dept-toggle" id="deptToggle" data-count="<?=count($dept_overview)?>"><i class="bi bi-chevron-down"></i> 展开全部 <?=count($dept_overview)?> 个部门</div>
  <?php endif; ?>
</div>
<?php endif; ?>

<!-- v2.40.0：我的业绩图形化概览（有权账号可见，点击进 /m/my-stats 看详情） -->
<?php if(!empty($can_my_stats) && !empty($my_stats)):
  $ms = $my_stats;
  // 环比徽章：升绿降红，0 记持平灰
  $amtBadge  = $ms['amt_chg']  > 0 ? ['up',   '+' . $ms['amt_chg']  . '%']
              : ($ms['amt_chg']  < 0 ? ['down', $ms['amt_chg']  . '%'] : ['flat', '持平']);
  $paidBadge = $ms['paid_chg'] > 0 ? ['up',   '+' . $ms['paid_chg'] . '%']
              : ($ms['paid_chg'] < 0 ? ['down', $ms['paid_chg'] . '%'] : ['flat', '持平']);
  // 万元换算（<1 万保留元）
  $wan = function($n) { return $n >= 10000 ? round($n / 10000, 1) . '万' : (string)(int)$n; };
?>
<style>
  .my-stats-card{ display:block; text-decoration:none; color:inherit; margin:12px; background:#fff; border-radius:14px; box-shadow:0 1px 3px rgba(0,0,0,.06); overflow:hidden; }
  .my-stats-hd{ display:flex; align-items:center; justify-content:space-between; padding:12px 14px; background:linear-gradient(135deg,#0b5ed7,#3d8bfd); color:#fff; }
  .my-stats-hd .t{ font-weight:600; font-size:15px; display:flex; align-items:center; gap:6px; }
  .my-stats-hd .more{ font-size:12px; opacity:.85; display:flex; align-items:center; gap:2px; }
  .my-stats-grid{ display:grid; grid-template-columns:1fr 1fr; gap:1px; background:#f0f0f0; }
  .my-stats-cell{ background:#fff; padding:12px 14px; }
  .my-stats-cell .lab{ font-size:11px; color:#8a9099; display:flex; align-items:center; gap:4px; }
  .my-stats-cell .val{ font-size:20px; font-weight:700; color:#1f2329; margin-top:4px; line-height:1.1; }
  .my-stats-cell .sub{ font-size:11px; color:#8a9099; margin-top:2px; }
  .ms-badge{ display:inline-block; font-size:12px; font-weight:600; padding:1px 5px; border-radius:8px; line-height:1.4; }
  .ms-badge.up{ color:#18a058; background:rgba(24,160,88,.12); }
  .ms-badge.down{ color:#d03050; background:rgba(208,48,80,.12); }
  .ms-badge.flat{ color:#8a9099; background:rgba(138,144,153,.12); }
  .ms-cell-amt .val{ color:var(--brand); }
  .ms-cell-paid .val{ color:#18a058; }
</style>
<a href="/m/my-stats" class="my-stats-card">
  <div class="my-stats-hd">
    <span class="t"><i class="bi bi-bar-chart-line"></i>我的业绩</span>
    <span class="more">查看详情<i class="bi bi-chevron-right"></i></span>
  </div>
  <div class="my-stats-grid">
    <div class="my-stats-cell ms-cell-cust">
      <div class="lab"><i class="bi bi-people" style="color:#0b5ed7"></i>我的客户</div>
      <div class="val"><?=$ms['cust_total']?></div>
      <div class="sub">本月新增 <?=$ms['cust_month']?></div>
    </div>
    <div class="my-stats-cell ms-cell-contract">
      <div class="lab"><i class="bi bi-file-earmark-text" style="color:#0b5ed7"></i>我的合同</div>
      <div class="val"><?=$ms['total_cnt']?></div>
      <div class="sub">本月新增 <?=$ms['month_cnt']?></div>
    </div>
    <div class="my-stats-cell ms-cell-amt">
      <div class="lab"><i class="bi bi-currency-yen" style="color:#0b5ed7"></i>本月成交额
        <span class="ms-badge <?=$amtBadge[0]?>"><?=$amtBadge[1]?></span>
      </div>
      <div class="val"><?=$wan($ms['month_amt'])?></div>
      <div class="sub">累计 <?=$wan($ms['total_amt'])?></div>
    </div>
    <div class="my-stats-cell ms-cell-paid">
      <div class="lab"><i class="bi bi-cash-coin" style="color:#18a058"></i>本月回款
        <span class="ms-badge <?=$paidBadge[0]?>"><?=$paidBadge[1]?></span>
      </div>
      <div class="val"><?=$wan($ms['paid_month'])?></div>
      <div class="sub">累计 <?=$wan($ms['paid_total'])?> · 回收率 <?=$ms['recovery_rate']?>%</div>
    </div>
  </div>
</a>
<?php endif; ?>

<!-- 统一待办中心（v2.38.15 方案A）：待我审批 > 审批消息 > 到期/回款提醒，按优先级排序，最多 5 条 -->
<?php
  $todoMax = 5;
  $todoShown = array_slice($todo_list ?? [], 0, $todoMax);
  $todoMore = count($todo_list ?? []) > $todoMax;
  // 待办项标签样式：approval=红色审批 / notif=蓝色消息 / remind 按 level
  $todoBadge = function ($t) {
      if ($t['kind'] === 'approval') return ['badge-soft-danger', '审批'];
      if ($t['kind'] === 'notif')     return ['badge-soft-info', '消息'];
      $lv = $t['level'] ?? 'info';
      $map = ['danger' => 'badge-soft-danger', 'warning' => 'badge-soft-warn', 'info' => 'badge-soft-info'];
      $lab = ['danger' => '逾期', 'warning' => '紧急', 'info' => '提醒'];
      return [$map[$lv] ?? 'badge-soft-info', $lab[$lv] ?? '提醒'];
  };
?>
<div class="m-card" id="alerts">
  <div class="m-card-hd"><span><i class="bi bi-bell me-1 text-warning"></i>今日提醒</span><span class="badge badge-soft-warn rounded-pill"><?=$todo_total ?? count($todo_list ?? [])?></span></div>
  <div class="m-card-bd">
    <?php if(empty($todo_list)): ?>
      <div class="empty"><i class="bi bi-emoji-smile"></i> 今日无到期/逾期提醒</div>
    <?php else: foreach($todoShown as $t):
        [$bdCls, $bdTxt] = $todoBadge($t);
    ?>
      <a href="<?=$t['link'] ?: '#'?>" class="m-item d-block text-decoration-none"<?=$t['link']==='#'?' style="pointer-events:none"':''?>>
        <div class="t"><span class="badge <?=$bdCls?> me-1"><?=$bdTxt?></span><?=htmlspecialchars($t['text'] ?? '')?></div>
        <?php if(!empty($t['sub'])): ?><div class="s text-muted" style="font-size:12px"><?=htmlspecialchars($t['sub'])?></div><?php endif; ?>
      </a>
    <?php endforeach; endif; ?>
    <?php if($todoMore): ?>
      <a href="/m/remind" class="m-btn m-btn-ghost w-100 mt-2" style="height:38px;font-size:14px">查看全部（<?=count($todo_list)?> 条）</a>
    <?php endif; ?>
  </div>
</div>

<!-- 快捷操作（动作型入口：审批/登记/资料库等常用功能；新建统一收敛到右下角 FAB，模块浏览交给底部 Tab 与「更多」） -->
<?php if($can_approve || $can_pay || !empty($can_invoice) || !empty($can_library) || !empty($can_customer_pool) || !empty($can_report) || !empty($can_follow)): ?>
<div class="m-card" id="quick">
  <div class="m-card-hd"><span><i class="bi bi-grid-3x3-gap me-1 text-primary"></i>快捷操作</span><span class="quick-tip" id="quickTip"><i class="bi bi-arrow-left-right"></i>左右滑动</span></div>
  <div class="m-card-bd">
    <div class="quick-grid">
      <?php if($can_approve): ?><a href="/m/approvals" class="quick-item"><i class="bi bi-list-check"></i><span>审批</span></a><?php endif; ?>
      <?php if($can_pay): ?><a href="/m/finance#add" class="quick-item"><i class="bi bi-cash-coin"></i><span>登记回款</span></a><?php endif; ?>
      <?php if(!empty($can_follow)): ?><a href="javascript:;" class="quick-item" onclick="mOpenQuickFollow()"><i class="bi bi-pencil"></i><span>记录跟进</span></a><?php endif; ?>
      <?php if(!empty($can_invoice)): ?><a href="/m/invoice-apply" class="quick-item"><i class="bi bi-receipt-cutoff"></i><span>申请开票</span></a><?php endif; ?>
      <?php if(!empty($can_library)): ?><a href="/m/resource" class="quick-item"><i class="bi bi-folder2-open"></i><span>资料库</span></a><?php endif; ?>
      <?php if(!empty($can_customer_pool)): ?><a href="/m/customers/pool" class="quick-item"><i class="bi bi-people"></i><span>客户池</span></a><?php endif; ?>
      <?php if(!empty($can_report)): ?><a href="/m/reports" class="quick-item"><i class="bi bi-bar-chart-line"></i><span>报表</span></a><?php endif; ?>
    </div>
    <!-- v2.40.0 P1-7：分段式指示器（2 段，第 1 段高亮=当前屏，仅图标超一屏时显示） -->
    <div class="quick-scrollbar"><i class="cur"></i><i></i></div>
  </div>
</div>
<?php endif; ?>

<!-- 模块浏览入口统一收口到「更多」（底部 Tab 第 4 项），工作台聚焦：概览数字 + 快捷操作 + 今日提醒 -->
<!-- 登记回款已移至财务页（/m/finance），通过下方快捷操作「登记回款」跳转，不再占用工作台大页面 -->

<!-- 新建 FAB：右下角常驻「新建」悬浮按钮（复用 mobile.css .m-fab 样式），点击展开新建菜单（合同/客户/供应商，按权限显示）；无任何新建权限时不渲染 -->
<?php if(!empty($can_create_contract) || !empty($can_create_customer) || !empty($can_create_supplier)): ?>
<div class="m-fab-mask" id="fabMask"></div>
<div class="m-fab-menu" id="fabMenu">
  <?php if(!empty($can_create_contract)): ?><a href="/m/contract/create" class="m-fab-item"><i class="bi bi-file-earmark-plus"></i><span>新建合同</span></a><?php endif; ?>
  <?php if(!empty($can_create_customer)): ?><a href="/m/customer/create" class="m-fab-item"><i class="bi bi-person-plus"></i><span>新建客户</span></a><?php endif; ?>
  <?php if(!empty($can_create_supplier)): ?><a href="/m/supplier/create" class="m-fab-item"><i class="bi bi-building-add"></i><span>新建供应商</span></a><?php endif; ?>
</div>
<button type="button" class="m-fab" id="fabBtn" aria-label="新建"><i class="bi bi-plus-lg"></i></button>
<?php endif; ?>

<!-- 底部全局导航 -->

<script>
(function(){
  // 底部 tab 激活态
  document.querySelectorAll('.m-tabbar a[href^="#"]').forEach(function(a){
    a.addEventListener('click', function(){
      document.querySelectorAll('.m-tabbar a').forEach(function(x){x.classList.remove('active');});
      this.classList.add('active');
    });
  });

  // 新建 FAB：点击展开/收起新建菜单（合同/客户/供应商），点遮罩或选中菜单项后收起（body.fab-open 驱动展开态）
  var fabBtn = document.getElementById('fabBtn');
  if (fabBtn) {
    fabBtn.addEventListener('click', function(){ document.body.classList.toggle('fab-open'); });
    var fabMask = document.getElementById('fabMask');
    if (fabMask) fabMask.addEventListener('click', function(){ document.body.classList.remove('fab-open'); });
    var fabMenu = document.getElementById('fabMenu');
    if (fabMenu) fabMenu.addEventListener('click', function(e){
      if (e.target.closest('a')) document.body.classList.remove('fab-open');
    });
  }

  // v2.38.26 + v2.40.0 P1-7：快捷操作图标超过一屏（>4 项）→ 显示「左右滑动」提示 + 右侧渐隐 + 分段式指示器
  var quickCard = document.getElementById('quick');
  var quickGrid = quickCard ? quickCard.querySelector('.quick-grid') : null;
  if (quickCard && quickGrid) {
    var updateQuickBar = function(){
      var hasMore = quickGrid.scrollWidth > quickGrid.clientWidth + 4;
      quickCard.classList.toggle('has-more', hasMore);
      var bars = quickCard.querySelectorAll('.quick-scrollbar i');
      var max = quickGrid.scrollWidth - quickGrid.clientWidth;
      var seg = 0;
      if (hasMore && max > 0 && bars.length > 0) {
        seg = Math.round(quickGrid.scrollLeft / max * (bars.length - 1));
      }
      bars.forEach(function(b, i){
        b.classList.toggle('cur', i === seg);
      });
    };
    updateQuickBar();
    quickGrid.addEventListener('scroll', updateQuickBar);
    window.addEventListener('resize', updateQuickBar);
  }

  // v2.40.0：全公司经营卡片折叠/展开（部门>3 时，默认显示前 3 名）
  var deptCard = document.getElementById('deptCard');
  var deptToggle = document.getElementById('deptToggle');
  if (deptCard && deptToggle) {
    deptToggle.addEventListener('click', function(){
      var collapsed = deptCard.classList.toggle('collapsed');
      deptToggle.innerHTML = collapsed
        ? '<i class="bi bi-chevron-down"></i> 展开全部 ' + deptToggle.dataset.count + ' 个部门'
        : '<i class="bi bi-chevron-up"></i> 收起';
    });
  }
})();
</script>

<?php if(!empty($can_follow)): ?>
<!-- v2.48.0：工作台「记录跟进」——选客户（cs-wrap 搜索，无快速新建）+ 快捷录入弹层 -->
<script>window.__mQuickCust = <?=json_encode(array_map(function($c){return ['id'=>(int)$c['id'],'name'=>(string)$c['name']];}, $quick_follow_customers ?? []), JSON_UNESCAPED_UNICODE)?>;</script>
<div class="m-sheet-mask" id="qFollowCustMask">
  <div class="m-sheet">
    <h3>选择客户</h3>
    <p>选择要记录跟进的客户</p>
    <div class="cs-wrap" data-cs-src="window.__mQuickCust" style="margin-bottom:14px">
      <input type="text" class="cs-input m-input" placeholder="搜索客户…" autocomplete="off">
      <div class="cs-suggestions"></div>
      <input type="hidden" class="cs-id" id="qFollowCustId" value="0">
    </div>
    <div class="m-sheet-actions">
      <button class="m-btn m-btn-ghost" id="qCustCancel">取消</button>
      <button class="m-btn m-btn-brand" id="qCustNext">下一步</button>
    </div>
  </div>
</div>
<div class="m-sheet-mask" id="qFollowActMask">
  <div class="m-sheet">
    <h3>记录跟进</h3>
    <p id="qFollowCustLabel">客户</p>
    <div class="m-act-grid" id="qActTypeGrid">
      <label class="m-act-btn"><input type="radio" name="actType" value="phone" checked><span>电</span><em>电话</em></label>
      <label class="m-act-btn"><input type="radio" name="actType" value="visit"><span>拜</span><em>拜访</em></label>
      <label class="m-act-btn"><input type="radio" name="actType" value="meeting"><span>会</span><em>会议</em></label>
      <label class="m-act-btn"><input type="radio" name="actType" value="wechat"><span>微</span><em>微信</em></label>
    </div>
    <p>快捷短语</p>
    <div class="m-phrase-row" id="qActPhraseRow">
      <span class="m-phrase" data-phrase="已电话沟通">已电话沟通</span>
      <span class="m-phrase" data-phrase="确认意向">确认意向</span>
      <span class="m-phrase" data-phrase="已发资料">已发资料</span>
      <span class="m-phrase" data-phrase="约定下次">约定下次</span>
    </div>
    <p>跟进内容</p>
    <textarea class="m-input" id="qActContent" rows="3" maxlength="500" placeholder="本次沟通要点、客户意向等" style="resize:vertical;height:auto"></textarea>
    <p>下次跟进（可选）</p>
    <div class="m-phrase-row" id="qActNextRow">
      <span class="m-phrase" data-next="1">明天 09:00</span>
      <span class="m-phrase" data-next="3">3 天后</span>
      <span class="m-phrase" data-next="7">一周后</span>
      <span class="m-phrase" data-next="0">自定义</span>
    </div>
    <input class="m-input" type="datetime-local" id="qActNextFollow" style="margin-top:8px">
    <div class="m-sheet-actions">
      <button class="m-btn m-btn-ghost" id="qActCancel">取消</button>
      <button class="m-btn m-btn-brand" id="qActSave">保存</button>
    </div>
  </div>
</div>
<script>
/* v2.48.0：工作台「记录跟进」快捷录入（选客户→录入→保存即完成，不跳详情页） */
(function(){
  var custId = 0;
  function pickType(gridId, last){
    var rb = document.querySelector('#' + gridId + ' input[value="' + last + '"]');
    document.querySelectorAll('#' + gridId + ' input[name="actType"]').forEach(function(i){ i.checked = (i === rb); });
  }
  function clearPhrases(rowId){
    document.getElementById(rowId).querySelectorAll('.m-phrase').forEach(function(p){ p.classList.remove('on'); });
  }
  function insertPhrase(taId, phrase){
    var ta = document.getElementById(taId);
    var v = ta.value.trim();
    ta.value = v === '' ? phrase : v + '\n' + phrase;
    ta.focus();
  }
  function setActNext(days, elId, rowId){
    var el = document.getElementById(elId);
    document.getElementById(rowId).querySelectorAll('.m-phrase').forEach(function(p){
      p.classList.toggle('on', String(p.getAttribute('data-next')) === String(days));
    });
    if (String(days) === '0') { el.focus(); return; }
    var d = new Date();
    d.setDate(d.getDate() + parseInt(days, 10));
    if (parseInt(days, 10) === 1) d.setHours(9, 0, 0, 0);
    function pad(n){ return String(n).padStart(2, '0'); }
    el.value = d.getFullYear() + '-' + pad(d.getMonth()+1) + '-' + pad(d.getDate()) + 'T' + pad(d.getHours()) + ':' + pad(d.getMinutes());
  }
  window.mOpenQuickFollow = function(){
    custId = 0;
    document.getElementById('qFollowCustId').value = '0';
    var wrap = document.querySelector('#qFollowCustMask .cs-wrap');
    var inp = wrap ? wrap.querySelector('.cs-input') : null;
    if (inp) inp.value = '';
    document.getElementById('qFollowCustMask').classList.add('show');
    setTimeout(function(){ if (inp) inp.focus(); }, 80);
  };
  document.getElementById('qCustCancel').addEventListener('click', function(){
    document.getElementById('qFollowCustMask').classList.remove('show');
  });
  document.getElementById('qCustNext').addEventListener('click', function(){
    var id = parseInt(document.getElementById('qFollowCustId').value, 10);
    var wrap = document.querySelector('#qFollowCustMask .cs-wrap');
    var name = wrap ? (wrap.querySelector('.cs-input').value || '') : '';
    if (!id) { toast('请选择客户'); return; }
    custId = id;
    document.getElementById('qFollowCustLabel').textContent = '客户：' + name;
    document.getElementById('qFollowCustMask').classList.remove('show');
    document.getElementById('qActContent').value = '';
    document.getElementById('qActNextFollow').value = '';
    clearPhrases('qActPhraseRow');
    clearPhrases('qActNextRow');
    var last = 'phone';
    try { last = localStorage.getItem('mActType') || 'phone'; } catch(e) {}
    pickType('qActTypeGrid', last);
    document.getElementById('qFollowActMask').classList.add('show');
  });
  document.getElementById('qActCancel').addEventListener('click', function(){
    document.getElementById('qFollowActMask').classList.remove('show');
  });
  document.getElementById('qActSave').addEventListener('click', function(){
    var type = document.querySelector('#qActTypeGrid input[name="actType"]:checked');
    var content = document.getElementById('qActContent').value.trim();
    var next = document.getElementById('qActNextFollow').value;
    if (!content) { toast('请填写跟进内容'); return; }
    var t = type ? type.value : 'phone';
    try { localStorage.setItem('mActType', t); } catch(e) {}
    var mask = document.getElementById('qFollowActMask');
    mask.classList.remove('show');
    var fd = new URLSearchParams();
    fd.append('type', t);
    fd.append('content', content);
    fd.append('next_follow_at', next);
    fetch('/ajax/customer/' + custId + '/activity', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken() },
      body: fd.toString()
    }).then(function(r){ return r.json(); }).then(function(d){
      if (d.code === 0) { toast('已记录跟进'); document.getElementById('qFollowCustMask').classList.remove('show'); }
      else { toast(d.msg || '记录失败'); mask.classList.add('show'); }
    }).catch(function(){ toast('网络错误'); mask.classList.add('show'); });
  });
  document.getElementById('qActPhraseRow').addEventListener('click', function(e){
    var p = e.target.closest ? e.target.closest('.m-phrase') : null;
    if (p) { p.classList.add('on'); insertPhrase('qActContent', p.getAttribute('data-phrase')); }
  });
  document.getElementById('qActNextRow').addEventListener('click', function(e){
    var p = e.target.closest ? e.target.closest('.m-phrase') : null;
    if (p) setActNext(p.getAttribute('data-next'), 'qActNextFollow', 'qActNextRow');
  });
})();
</script>
<script src="<?=asset_url('js/search-picker.js')?>"></script>
<?php endif; ?>
<?php $tab = 'home'; include __DIR__ . '/_foot.php'; ?>
