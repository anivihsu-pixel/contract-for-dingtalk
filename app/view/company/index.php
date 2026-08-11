<?php
$title='本公司主体'; $menu_active='company';
include __DIR__.'/../layout/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4><i class="bi bi-buildings"></i> 本公司主体</h4>
  <button class="btn btn-primary btn-sm" onclick="openForm(0)"><i class="bi bi-plus-lg"></i> 新增主体</button>
</div>
<div class="text-muted small mb-2">维护本公司名下各签约主体（运营/技术/文化等）。新建合同时将自动带出默认主体，"本公司"快捷按钮从中取数。<br>开票税率：开票申请选择主体后自动带出（税率不单独选择），请在此为每个主体配置正确的增值税税率。</div>

<div class="card"><div class="card-body p-0">
<div class="table-responsive"><table class="table table-sm mb-0"><thead><tr>
  <th>公司全称</th><th>简称</th><th>统一社会信用代码</th><th>开票税率</th><th>默认</th><th class="text-end">操作</th>
</tr></thead><tbody>
<?php if(empty($list)): ?><tr><td colspan="6" class="text-center text-muted py-3">暂无主体，请新增</td></tr>
<?php else: foreach($list as $c): ?>
<tr data-id="<?=$c['id']?>" data-name="<?=htmlspecialchars($c['name'],ENT_QUOTES)?>" data-short="<?=htmlspecialchars($c['short_name'],ENT_QUOTES)?>" data-code="<?=htmlspecialchars($c['unified_social_credit_code'],ENT_QUOTES)?>" data-rate="<?=htmlspecialchars((string)($c['invoice_tax_rate'] ?? 0.06),ENT_QUOTES)?>" data-default="<?=$c['is_default']?>">
  <td class="fw-bold"><?=htmlspecialchars($c['name'])?></td>
  <td><?=htmlspecialchars($c['short_name']?:'-')?></td>
  <td><small class="text-muted"><?=htmlspecialchars($c['unified_social_credit_code']?:'-')?></small></td>
  <td><?php $__r=(float)($c['invoice_tax_rate']??0.06); echo $__r<=0?'<span class="pc-tag pc-tag-muted">免税</span>':'<span class="pc-tag pc-tag-info">'.($__r*100).'%</span>'; ?></td>
  <td><?=$c['is_default']?'<span class="pc-tag pc-tag-ok">默认</span>':'<span class="pc-tag pc-tag-muted">否</span>'?></td>
  <td class="text-end">
    <button class="btn btn-sm btn-outline-primary" aria-label="编辑" onclick="openForm(<?=$c['id']?>)"><i class="bi bi-pencil"></i></button>
    <button class="btn btn-sm btn-outline-danger" aria-label="删除" onclick="del(<?=$c['id']?>, <?=$c['is_default']?>)"><i class="bi bi-trash"></i></button>
  </td>
</tr>
<?php endforeach; endif; ?>
</tbody></table></div>
</div></div>

<!-- 表单弹窗 -->
<div class="modal fade" id="formModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
<div class="modal-header"><h5 class="modal-title" id="formTitle">新增主体</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
<form id="companyForm">
<input type="hidden" name="id" id="f_id" value="0">
<div class="modal-body row g-3">
  <div class="col-md-8"><label class="form-label" for="f_name">公司全称 <span class="text-danger">*</span></label><input type="text" name="name" id="f_name" class="form-control" required></div>
  <div class="col-md-4"><label class="form-label" for="f_short">简称</label><input type="text" name="short_name" id="f_short" class="form-control"></div>
  <div class="col-md-8"><label class="form-label" for="f_code">统一社会信用代码</label><input type="text" name="unified_social_credit_code" id="f_code" class="form-control"></div>
  <div class="col-md-4">
    <label class="form-label" for="f_rate">开票税率 <span class="text-muted small">（开票申请按主体自动带出）</span></label>
    <select name="invoice_tax_rate" id="f_rate" class="form-select">
      <option value="0">0% 免税</option>
      <option value="0.01">1%</option>
      <option value="0.03">3%</option>
      <option value="0.05">5%</option>
      <option value="0.06" selected>6%</option>
      <option value="0.09">9%</option>
      <option value="0.13">13%</option>
    </select>
  </div>
  <div class="col-12 form-check"><input type="checkbox" class="form-check-input" name="is_default" id="f_default" value="1"><label class="form-check-label" for="f_default">设为默认主体（新建合同自动带出）</label></div>
</div>
<div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">取消</button><button class="btn btn-primary" type="submit">保存</button></div>
</form></div></div></div>

<script src="<?=asset_url('js/company.js')?>"></script>
<?php include __DIR__.'/../layout/footer.php'; ?>
