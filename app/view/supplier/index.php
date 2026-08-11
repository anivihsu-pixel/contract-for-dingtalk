<?php $title='供应商管理'; $menu_active='supplier'; include __DIR__.'/../layout/header.php'; ?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2"><h4><i class="bi bi-truck"></i> 供应商管理</h4><?php if(!empty($can_create_supplier)): ?><a href="/supplier/create" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> 新增供应商</a><?php endif; ?></div>
<div class="card stat-card mb-3"><div class="card-body">
<form id="supplierSearchForm" class="row g-2">
<div class="col-md-8"><input type="text" name="keyword" class="form-control form-control-sm" placeholder="搜索供应商名称、联系人、手机..." value="<?=htmlspecialchars($keyword??'')?>" onchange="this.form.submit()" onkeydown="if(event.key==='Enter'){event.preventDefault();this.form.submit();}"></div>
<div class="col-md-2"><button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-search"></i> 搜索</button></div>
</form>
<!-- P1-1：类型筛选芯片化（对齐移动端 .m-chip；GET 链接保留关键词） -->
<div class="pc-chips mt-2">
  <a href="/supplier?<?=!empty($keyword)?'keyword='.urlencode($keyword).'&':''?>" class="<?=($type??'')===''?'active':''?>">全部类型</a>
  <?php foreach(dict('supplier_type') as $__k=>$__v): ?><a href="/supplier?type=<?=urlencode($__k)?><?=!empty($keyword)?'&keyword='.urlencode($keyword):''?>" class="<?=($type??'')===$__k?'active':''?>"><?=htmlspecialchars($__v)?></a><?php endforeach; ?>
</div>
</div></div>
<div class="card stat-card"><div class="table-responsive"><table class="table table-hover mb-0"><thead class="table-light"><tr><th>名称</th><th>类型</th><th>联系人</th><th>手机</th><th>状态</th><th>操作</th></tr></thead><tbody>
<?php if(!empty($suppliers)): foreach($suppliers as $s): ?>
<tr><td><a href="/supplier/<?=$s['id']?>"><?=htmlspecialchars($s['name'])?></a></td>
<?php $sType = dict('supplier_type', $s['type']); ?><td><?=$sType !== $s['type'] ? '<span class="pc-tag pc-tag-info">'.$sType.'</span>' : '<span class="pc-tag pc-tag-muted">其他</span>'?></td>
<td><?=htmlspecialchars($s['contact_name']?:'-')?></td>
<td><?=phone_link($s['contact_mobile']??'', false)?></td>
<td><?=$s['status']==1?'<span class="pc-tag pc-tag-ok">正常</span>':'<span class="pc-tag pc-tag-danger">禁用</span>'?></td>
<td><a href="/supplier/<?=$s['id']?>/edit" class="btn btn-sm btn-primary" aria-label="编辑"><i class="bi bi-pencil"></i></a></td></tr>
<?php endforeach; else: ?><tr><td colspan="6" class="text-center py-5"><div class="text-muted"><i class="bi bi-truck" style="font-size:2rem"></i><div class="mt-2 fw-semibold">暂无供应商</div><div class="small">新增供应商后可在合同中选择使用</div></div></td></tr><?php endif; ?>
</tbody></table></div></div>
<?php include __DIR__.'/../layout/footer.php'; ?>
