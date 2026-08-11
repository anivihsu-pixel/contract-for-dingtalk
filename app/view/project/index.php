<?php $title='项目管理'; $menu_active='project'; include __DIR__.'/../layout/header.php'; ?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2"><h4><i class="bi bi-kanban"></i> 项目管理</h4><?php if(!empty($can_create_project)): ?><a href="/project/create" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> 新建项目</a><?php endif; ?></div>
<div class="card stat-card mb-3"><div class="card-body py-2"><form id="filterForm" class="row g-2 align-items-end">
  <div class="col-md-4 col-6"><label class="form-label small mb-1" for="fProjKeyword">关键词</label><input type="text" name="keyword" id="fProjKeyword" class="form-control form-control-sm" placeholder="项目名称 / 编号 / 备注"></div>
  <div class="col-md-3 col-12"><button type="submit" class="btn btn-outline-primary btn-sm"><i class="bi bi-search"></i> 查询</button></div>
  <!-- P1-1：状态筛选芯片化（对齐移动端 .m-chip；点击写入 hidden 后触发表单加载） -->
  <input type="hidden" name="status" value="<?=htmlspecialchars((string)($status ?? ''), ENT_QUOTES)?>">
</form>
<div class="pc-chips mt-2">
  <a href="javascript:;" class="<?=($status ?? '') === '' ? 'active' : ''?>" data-st="">全部</a>
  <?php foreach($statusDict as $__k => $__v): ?><a href="javascript:;" class="<?=($status ?? '') === $__k ? 'active' : ''?>" data-st="<?=htmlspecialchars($__k)?>"><?=htmlspecialchars($__v)?></a><?php endforeach; ?>
</div>
<script>
document.querySelectorAll('.pc-chips a[data-st]').forEach(function(a){ a.addEventListener('click', function(){
  document.querySelector('#filterForm [name="status"]').value = this.dataset.st;
  document.querySelector('#filterForm').dispatchEvent(new Event('submit'));
});});
</script>
</div></div>
<div class="card stat-card"><div class="table-responsive"><table class="table table-hover mb-0"><thead class="table-light"><tr><th>项目名称</th><th>编号</th><th>状态</th><th>合同数</th><th>预算</th><th>起止</th><th>操作</th></tr></thead><tbody id="tableBody"><tr><td colspan="7" class="text-center py-4"><div class="spinner-border spinner-border-sm text-muted"></div> <span class="text-muted small">加载中...</span></td></tr></tbody></table></div><div class="card-footer bg-white" id="pagination"></div></div>
<script>window._projStatus=<?=json_encode($statusDict??[],JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;window._canCreateProject=<?=!empty($can_create_project)?'true':'false'?>;</script>
<script src="<?=asset_url('js/project.js')?>"></script>
<?php include __DIR__.'/../layout/footer.php'; ?>
