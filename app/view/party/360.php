<?php $title='往来全景'; $menu_active='party'; include __DIR__.'/../layout/header.php'; ?>
<?php
$base = $base ?? [];
$stats = $stats ?? [];
$role = $stats['role'] ?? '应收';                 // 应收 / 应付
$isCustomer = $type === 'customer';
$isTradeRole = $role === '应收' ? '收' : '付';
$directionMap = [
    'sales'    => '<span class="pc-tag pc-tag-ok">销售(应收)</span>',
    'purchase' => '<span class="pc-tag pc-tag-info">采购(应付)</span>',
    ''         => '<span class="pc-tag pc-tag-muted">非交易</span>',
];
// 合同状态标签统一复用 contract_status_label()（单一事实来源，避免状态机漂移导致英文原始码外泄）
// 状态标签统一使用公共合同状态映射。
$payStatusMap = [
    'PAID'     => '<span class="pc-tag pc-tag-ok">已' . $isTradeRole . '</span>',
    'PENDING'  => '<span class="pc-tag pc-tag-warn">待' . $isTradeRole . '</span>',
    'OVERDUE'  => '<span class="pc-tag pc-tag-danger">逾期</span>',
];
$invTypeMap = ['VAT_SPECIAL' => '专票', 'VAT_NORMAL' => '普票', 'E_INVOICE' => '电子票'];
// 发票状态：实际写入值为 ISSUED（已开票）/ VOID（已作废）/ RED（已红冲），旧映射误用 APPLIED/REJECTED/CANCELLED 会导致 VOID/RED 以英文原始码外露
$invStatusMap = [
    'APPLIED'   => '<span class="pc-tag pc-tag-warn">已申请</span>',
    'ISSUED'    => '<span class="pc-tag pc-tag-ok">已开票</span>',
    'VOID'      => '<span class="pc-tag pc-tag-muted">已作废</span>',
    'RED'       => '<span class="pc-tag pc-tag-danger">已红冲</span>',
    'REJECTED'  => '<span class="pc-tag pc-tag-danger">已退回</span>',
    'CANCELLED' => '<span class="pc-tag pc-tag-muted">已作废</span>',
];
$invDirLabel = $isCustomer ? '销项' : '进项';
$typeLabel = $isCustomer ? '客户' : '供应商';
?>
<nav aria-label="breadcrumb" class="mb-2">
  <ol class="breadcrumb mb-0">
    <li class="breadcrumb-item"><a href="/party">往来档案</a></li>
    <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($base['name'] ?? '') ?></li>
  </ol>
</nav>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <h4 class="mb-0"><i class="bi bi-people"></i> <?= htmlspecialchars($base['name'] ?? '') ?>
    <span class="pc-tag pc-tag-<?= $isCustomer ? 'info' : 'warn' ?>"><?= $typeLabel ?></span>
  </h4>
  <a href="/party" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> 返回列表</a>
</div>

<!-- 基本信息 -->
<div class="card stat-card mb-3">
  <div class="card-header bg-white small text-muted">基本信息</div>
  <div class="card-body py-3">
    <div class="row g-2 small">
      <div class="col-md-3 col-6"><span class="text-muted">名称：</span><strong><?= htmlspecialchars($base['name'] ?? '') ?></strong></div>
      <div class="col-md-3 col-6"><span class="text-muted">联系人：</span><?= htmlspecialchars($base['contact_name'] ?? '-') ?></div>
      <div class="col-md-3 col-6"><span class="text-muted">电话：</span><?= phone_link($base['contact_mobile'] ?? '') ?></div>
      <div class="col-md-3 col-6"><span class="text-muted">邮箱：</span><?= htmlspecialchars($base['contact_email'] ?? '-') ?></div>
      <?php if ($isCustomer): ?>
        <div class="col-md-3 col-6"><span class="text-muted">统一信用代码：</span><?= htmlspecialchars($base['credit_code'] ?? '-') ?></div>
        <div class="col-md-3 col-6"><span class="text-muted">法人：</span><?= htmlspecialchars($base['legal_person'] ?? '-') ?></div>
      <?php else: ?>
        <div class="col-md-3 col-6"><span class="text-muted">供应商类型：</span><?= htmlspecialchars(!empty($base['type']) ? dict('supplier_type', $base['type']) : '-') ?></div>
      <?php endif; ?>
      <div class="col-md-6 col-12"><span class="text-muted">地址：</span><?= htmlspecialchars($base['address'] ?? '-') ?></div>
    </div>
  </div>
</div>

<!-- 统计卡 -->
<div class="row g-3 mb-3">
  <div class="col-md-3 col-6">
    <div class="card stat-card h-100"><div class="card-body">
      <div class="text-muted small">关联合同</div>
      <div class="fs-4 fw-bold"><?= $stats['contract_count'] ?? 0 ?></div>
    </div></div>
  </div>
  <div class="col-md-3 col-6">
    <div class="card stat-card h-100"><div class="card-body">
      <div class="text-muted small"><?= $role ?>总额（交易合同）</div>
      <div class="fs-4 fw-bold text-<?= $isCustomer ? 'success' : 'primary' ?>">¥<?= format_money($stats['total_amount'] ?? 0) ?></div>
    </div></div>
  </div>
  <div class="col-md-3 col-6">
    <div class="card stat-card h-100"><div class="card-body">
      <div class="text-muted small">已<?= $isTradeRole ?></div>
      <div class="fs-4 fw-bold">¥<?= format_money($stats['received_paid'] ?? 0) ?></div>
    </div></div>
  </div>
  <div class="col-md-3 col-6">
    <div class="card stat-card h-100"><div class="card-body">
      <div class="text-muted small">余额（未<?= $isTradeRole ?>）</div>
      <div class="fs-4 fw-bold text-danger">¥<?= format_money($stats['balance'] ?? 0) ?></div>
    </div></div>
  </div>
</div>

<!-- 标签页 -->
<ul class="nav nav-tabs mb-2" id="partyTabs" role="tablist">
  <li class="nav-item" role="presentation"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-contract" type="button">关联合同</button></li>
  <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-payment" type="button">回款记录</button></li>
  <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-invoice" type="button">发票记录</button></li>
  <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-activity" type="button">最近动态</button></li>
</ul>

<div class="tab-content">
  <!-- 关联合同 -->
  <div class="tab-pane fade show active" id="tab-contract" role="tabpanel">
    <div class="card stat-card"><div class="table-responsive"><table class="table table-hover mb-0 align-middle">
      <thead class="table-light"><tr><th>合同号</th><th>标题</th><th>方向</th><th>金额</th><th>状态</th><th>项目</th></tr></thead>
      <tbody>
      <?php if (empty($contracts)): ?><tr><td colspan="6" class="text-center py-4 text-muted">暂无关联合同</td></tr>
      <?php else: foreach ($contracts as $c): ?>
        <tr>
          <td><a href="/contract/<?= $c['id'] ?>"><?= htmlspecialchars($c['contract_no'] ?? '') ?></a></td>
          <td><?= htmlspecialchars($c['title'] ?? '') ?></td>
          <td><?= $directionMap[$c['direction']] ?? $c['direction'] ?></td>
          <td><?= !empty($c['trade_attr']) ? '¥' . format_money($c['amount'] ?? 0) : '<span class="text-muted">非交易</span>' ?></td>
          <td><?= contract_status_label($c['status']) ?></td>
          <td><?= htmlspecialchars($c['project_name'] ?? '-') ?></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table></div></div>
  </div>

  <!-- 回款记录 -->
  <div class="tab-pane fade" id="tab-payment" role="tabpanel">
    <div class="card stat-card"><div class="table-responsive"><table class="table table-hover mb-0 align-middle">
      <thead class="table-light"><tr><th>关联合同</th><th>金额</th><th>计划日期</th><th>实际日期</th><th>状态</th><th>方式</th></tr></thead>
      <tbody>
      <?php if (empty($payments)): ?><tr><td colspan="6" class="text-center py-4 text-muted">暂无回款记录</td></tr>
      <?php else: foreach ($payments as $p): ?>
        <tr>
          <td><a href="/contract/<?= $p['contract_id'] ?>"><?= htmlspecialchars($p['contract_title'] ?? '') ?></a></td>
          <td>¥<?= format_money($p['amount'] ?? 0) ?></td>
          <td><?= htmlspecialchars($p['planned_date'] ?? '-') ?></td>
          <td><?= htmlspecialchars($p['actual_date'] ?? '-') ?></td>
          <td><?= $payStatusMap[$p['status']] ?? $p['status'] ?></td>
          <td><?= htmlspecialchars(dict('payment_method', $p['payment_method'] ?? '') ?: '-') ?></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table></div></div>
  </div>

  <!-- 发票记录 -->
  <div class="tab-pane fade" id="tab-invoice" role="tabpanel">
    <div class="card stat-card"><div class="table-responsive"><table class="table table-hover mb-0 align-middle">
      <thead class="table-light"><tr><th>关联合同</th><th>类型</th><th>方向</th><th>金额</th><th>税额</th><th>状态</th><th>开票日期</th></tr></thead>
      <tbody>
      <?php if (empty($invoices)): ?><tr><td colspan="7" class="text-center py-4 text-muted">暂无发票记录</td></tr>
      <?php else: foreach ($invoices as $inv): ?>
        <tr>
          <td><a href="/contract/<?= $inv['contract_id'] ?>"><?= htmlspecialchars($inv['contract_title'] ?? '') ?></a></td>
          <td><?= $invTypeMap[$inv['invoice_type']] ?? $inv['invoice_type'] ?></td>
          <td><span class="pc-tag pc-tag-<?= $isCustomer ? 'ok' : 'info' ?>"><?= $invDirLabel ?></span></td>
          <td>¥<?= format_money($inv['amount'] ?? 0) ?></td>
          <td>¥<?= format_money($inv['tax_amount'] ?? 0) ?></td>
          <td><?= $invStatusMap[$inv['status']] ?? $inv['status'] ?></td>
          <td><?= htmlspecialchars($inv['issued_date'] ?? '-') ?></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table></div></div>
  </div>

  <!-- 最近动态 -->
  <div class="tab-pane fade" id="tab-activity" role="tabpanel">
    <div class="card stat-card"><div class="card-body py-2">
      <?php if (empty($activity)): ?><div class="text-center py-4 text-muted">暂无动态</div>
      <?php else: foreach ($activity as $a): ?>
        <div class="d-flex justify-content-between border-bottom py-2">
          <div>
            <span class="pc-tag pc-tag-muted"><?= htmlspecialchars(audit_action_label($a['action'] ?? '')) ?></span>
            <span class="text-muted small">合同 #<?= $a['target_id'] ?></span>
            <?php if (!empty($a['content'])): ?><span class="ms-2 small"><?= htmlspecialchars($a['content']) ?></span><?php endif; ?>
          </div>
          <div class="text-muted small"><?= htmlspecialchars($a['created_at'] ?? '') ?></div>
        </div>
      <?php endforeach; endif; ?>
    </div></div>
  </div>
</div>
<?php include __DIR__.'/../layout/footer.php'; ?>
