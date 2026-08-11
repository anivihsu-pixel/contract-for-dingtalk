<?php $title='审计中心'; $menu_active='audit'; include __DIR__.'/../layout/header.php'; ?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
<h4><i class="bi bi-shield-check"></i> 审计中心</h4>
<a href="/dashboard" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> 返回仪表盘</a>
</div>
<div class="card stat-card mb-3"><div class="card-body"><form id="searchForm" class="row g-2">
<div class="col-md-3 col-6"><select name="action" class="form-select form-select-sm"><option value="">全部操作</option>
<?php foreach($actions as $code=>$name): ?><option value="<?=$code?>"><?=$name?></option><?php endforeach; ?></select></div>
<div class="col-md-3 col-6"><select name="target_type" class="form-select form-select-sm"><option value="">全部对象</option>
<?php foreach($types as $code=>$name): ?><option value="<?=$code?>"><?=$name?></option><?php endforeach; ?></select></div>
<div class="col-md-3 col-6"><input type="date" name="date_start" class="form-control form-control-sm" placeholder="起始日期"></div>
<div class="col-md-3 col-6"><button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-search"></i> 筛选</button></div>
</form></div></div>
<div class="card stat-card"><div class="table-responsive"><table class="table table-hover mb-0 align-middle"><thead class="table-light"><tr><th>时间</th><th>操作人</th><th>操作</th><th>对象</th><th>详情</th><th>IP</th></tr></thead><tbody id="tableBody"><tr><td colspan="6" class="text-center py-4"><div class="spinner-border spinner-border-sm text-muted"></div></td></tr></tbody></table></div><div class="card-footer bg-white" id="pagination"></div></div>
<script>window._auditActions=<?=json_encode($actions,JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;window._auditTypes=<?=json_encode($types,JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;</script>
<script src="<?=asset_url('js/audit.js')?>"></script>
<?php include __DIR__.'/../layout/footer.php'; ?>
