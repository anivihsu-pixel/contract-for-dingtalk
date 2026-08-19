<?php $title='开票申请详情'; $menu_active='invoice_apply'; include __DIR__.'/../layout/header.php'; ?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <h4 class="mb-0"><i class="bi bi-receipt-cutoff"></i> 开票申请详情</h4>
  <div class="d-flex gap-2 align-items-center">
    <?php if(!empty($detail)): ?><span class="badge bg-light text-dark border fs-6"><?=htmlspecialchars($detail['status_label'])?></span><?php endif; ?>
    <?php if(!empty($detail['can_recall'])): ?><button type="button" class="btn btn-outline-secondary btn-sm" onclick="recallInvoiceApply(<?=(int)$detail['inst_id']?>)"><i class="bi bi-arrow-counterclockwise"></i> 撤回</button><?php endif; ?>
    <a href="/invoice-apply" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> 返回发票申请</a>
  </div>
</div>
<?php if(!empty($error)): ?>
<div class="card stat-card"><div class="card-body text-center py-5 text-muted">
  <i class="bi bi-shield-exclamation" style="font-size:2rem"></i>
  <div class="mt-2"><?=htmlspecialchars($error)?></div>
  <a href="/invoice-apply" class="btn btn-sm btn-outline-secondary mt-3">返回发票申请</a>
</div></div>
<?php else: $d=$detail; ?>
<!-- 开票对象信息 / 我方开票信息：两块分区展示，便于财务复制与核对 -->
<div class="row g-3 mb-3">
  <div class="col-md-6">
    <div class="card stat-card h-100"><div class="card-body">
      <h5 class="mb-3"><i class="bi bi-person-bounding-box text-primary"></i> 开票对象信息</h5>
      <dl class="row mb-0" style="--bs-gutter-x:0">
        <dt class="col-sm-4 text-muted small">发票抬头</dt>
        <dd class="col-sm-8 mb-2 fw-medium" style="user-select:all"><?=htmlspecialchars($d['invoice_title'] ?: '—')?></dd>
        <dt class="col-sm-4 text-muted small">税号</dt>
        <dd class="col-sm-8 mb-2" style="user-select:all;font-family:var(--bs-font-monospace)"><?=htmlspecialchars($d['tax_no'] ?: '—')?></dd>
        <dt class="col-sm-4 text-muted small">开票内容</dt>
        <dd class="col-sm-8 mb-0"><?=htmlspecialchars($d['content_desc'] ?: '—')?></dd>
      </dl>
    </div></div>
  </div>
  <div class="col-md-6">
    <div class="card stat-card h-100"><div class="card-body">
      <h5 class="mb-3"><i class="bi bi-building text-success"></i> 我方开票信息</h5>
      <dl class="row mb-0" style="--bs-gutter-x:0">
        <dt class="col-sm-4 text-muted small">开票主体</dt>
        <dd class="col-sm-8 mb-2 fw-medium" style="user-select:all"><?=htmlspecialchars($d['our_company_name'] ?: '—')?></dd>
        <dt class="col-sm-4 text-muted small">开票类型</dt>
        <dd class="col-sm-8 mb-0"><?=htmlspecialchars($d['invoice_type_label'] ?: ($d['invoice_type'] ?: '—'))?></dd>
      </dl>
    </div></div>
  </div>
</div>

<div class="card stat-card mb-3"><div class="card-body">
  <h5 class="mb-3"><i class="bi bi-info-circle"></i> 金额与进度</h5>
  <table class="table table-bordered align-middle mb-0" style="max-width:760px">
    <?php if(!empty($d['contract_title'])): ?>
    <tr><th class="table-light" style="width:140px">关联合同</th><td><?=htmlspecialchars($d['contract_no'] ? ($d['contract_no'] . ' · ' . $d['contract_title']) : $d['contract_title'])?></td></tr>
    <?php endif; ?>
    <tr><th class="table-light">金额（含税）</th><td>¥<?=format_money($d['amount'])?></td></tr>
    <tr><th class="table-light">税率 / 税额</th><td><?=htmlspecialchars(((float)$d['tax_rate']) * 100 . '%')?> / ¥<?=format_money($d['tax_amount'])?></td></tr>
    <?php if(!empty($d['invoice_no'])): ?>
    <tr><th class="table-light">发票号码</th><td><?=htmlspecialchars($d['invoice_no'])?></td></tr>
    <?php endif; ?>
    <?php if(!empty($d['issued_date'])): ?>
    <tr><th class="table-light">开票日期</th><td><?=htmlspecialchars($d['issued_date'])?></td></tr>
    <?php endif; ?>
    <?php if(!empty($d['remark'])): ?>
    <tr><th class="table-light">申请说明</th><td><?=htmlspecialchars($d['remark'])?></td></tr>
    <?php endif; ?>
    <tr><th class="table-light">申请人</th><td><?=htmlspecialchars($d['applicant_name'] ?: '—')?></td></tr>
    <tr><th class="table-light">提交时间</th><td><?=htmlspecialchars($d['created_at'] ?: '—')?></td></tr>
  </table>
</div></div>

<div class="card stat-card"><div class="card-body">
  <h5 class="mb-3"><i class="bi bi-list-check"></i> 审批流水</h5>
  <?php if(empty($d['records'])): ?>
  <div class="text-muted small">暂无审批记录</div>
  <?php else: ?>
  <div class="table-responsive"><table class="table table-sm table-bordered align-middle mb-0" style="max-width:760px">
    <thead class="table-light"><tr><th style="width:180px">处理人 · 节点</th><th style="width:110px">动作</th><th>意见</th><th style="width:170px">时间</th></tr></thead>
    <tbody>
    <?php foreach($d['records'] as $r): ?>
      <tr>
        <td><?=htmlspecialchars(($r['approver_name'] ?: '—') . ' · ' . ($r['node_name'] ?? ''))?></td>
        <td><span class="badge bg-light text-dark border"><?=htmlspecialchars($r['action_label'] ?? $r['action'])?></span></td>
        <td class="small"><?=htmlspecialchars($r['comment'] ?: '—')?></td>
        <td class="small text-muted"><?=htmlspecialchars($r['acted_at'] ?: '')?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</div></div>
<?php endif; ?>
<script>
// 撤回开票申请（仅待审批、申请人本人可见入口；后端 recall 二次校验 submitted_by）
function recallInvoiceApply(instId) {
    pcConfirm({ message: '确认撤回该开票申请？撤回后需重新提交才能再次进入审批。', danger: true }).then(function (ok) {
        if (!ok) return;
        $ajax('/ajax/approval/' + instId + '/recall', { method: 'POST', body: new URLSearchParams({}), loading: true, loadingText: '提交中…' })
            .then(function (res) {
                showToast(res.msg || '已撤回', res.code === 0 ? 'success' : 'error');
                if (res.code === 0) setTimeout(function () { location.reload(); }, 600);
            }).catch(function () {});
    });
}
</script>
<?php include __DIR__.'/../layout/footer.php'; ?>
