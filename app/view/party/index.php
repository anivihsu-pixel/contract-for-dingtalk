<?php $title='往来档案'; $menu_active='party'; include __DIR__.'/../layout/header.php'; ?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <h4 class="mb-0"><i class="bi bi-cash-coin"></i> 往来档案</h4>
  <span class="text-muted small">客户与供应商资金往来台账（余额仅统计交易合同）</span>
</div>

<div class="card stat-card mb-3"><div class="card-body py-2">
  <form method="get" class="row g-2 align-items-end">
    <div class="col-md-4 col-6"><label class="form-label small mb-1" for="fPartyKeyword">关键词</label><input type="text" name="keyword" id="fPartyKeyword" class="form-control form-control-sm" value="<?= htmlspecialchars($keyword ?? '') ?>" placeholder="名称 / 联系人 / 电话"></div>
    <div class="col-md-3 col-6"><label class="form-label small mb-1" for="fPartyType">类型</label><select name="type" id="fPartyType" class="form-select form-select-sm">
      <option value="">全部</option>
      <option value="customer" <?= ($type ?? '') === 'customer' ? 'selected' : '' ?>>客户</option>
      <option value="supplier" <?= ($type ?? '') === 'supplier' ? 'selected' : '' ?>>供应商</option>
    </select></div>
    <div class="col-md-3 col-12"><button type="submit" class="btn btn-outline-primary btn-sm"><i class="bi bi-search"></i> 查询</button>
      <a href="/party" class="btn btn-outline-secondary btn-sm">重置</a></div>
  </form>
</div></div>

<?php if (!empty($truncated)): ?>
<div class="alert alert-warning py-1 px-2 mb-2 small">结果过多，仅显示前 200 条，请使用关键词搜索缩小范围</div>
<?php endif; ?>
<div class="card stat-card"><div class="table-responsive"><table class="table table-hover mb-0 align-middle">
  <thead class="table-light"><tr><th>类型</th><th>名称</th><th>联系人</th><th>标签</th><th>往来</th><th>状态</th><th class="text-end">操作</th></tr></thead>
  <tbody>
  <?php if (empty($parties)): ?><tr><td colspan="7" class="text-center py-4 text-muted">暂无往来档案，或您无权限查看</td></tr>
  <?php else: foreach ($parties as $p): $__s = $p['_sum'] ?? null; ?>
    <tr>
      <td><span class="pc-tag pc-tag-<?= $p['type'] === 'customer' ? 'info' : 'warn' ?>"><?= htmlspecialchars($p['type_label']) ?></span></td>
      <td><?= htmlspecialchars($p['name']) ?></td>
      <td><?= htmlspecialchars($p['contact_name'] ?? '-') ?></td>
      <td><?= htmlspecialchars($p['tag'] ?? '-') ?></td>
      <td><?php // v2.38.14 往来列：总额 + 余额（待收/待付红色警示，已清绿色，无往来灰）
        if ($__s && $__s['total'] > 0): ?>
        <div>总额 <strong>¥<?=number_format((float)$__s['total'],0)?></strong></div>
        <div class="small <?= $__s['balance'] > 0 ? 'text-danger' : 'text-success' ?>">余额 ¥<?=number_format((float)$__s['balance'],0)?><?= $__s['balance'] > 0 ? ' · '.$__s['pending_word'] : ($__s['total'] > 0 ? ' · 已清' : '') ?></div>
        <?php else: ?><span class="text-muted">-</span><?php endif; ?></td>
      <td><?= !empty($p['status']) ? '<span class="pc-tag pc-tag-ok">启用</span>' : '<span class="pc-tag pc-tag-muted">停用</span>' ?></td>
      <td class="text-end"><a href="/party/<?= $p['type'] ?>/<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i> 往来全景</a></td>
    </tr>
  <?php endforeach; endif; ?>
  </tbody>
</table></div></div>
<?php include __DIR__.'/../layout/footer.php'; ?>
