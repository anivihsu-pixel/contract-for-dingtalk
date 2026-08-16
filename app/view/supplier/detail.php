<?php $title='供应商详情'; $menu_active='supplier'; include __DIR__.'/../layout/header.php'; ?>
<div class="d-flex justify-content-between align-items-center mb-3"><h4><?=htmlspecialchars($supplier['name'])?></h4><div><a href="/supplier/<?=$supplier['id']?>/edit" class="btn btn-primary btn-sm"><i class="bi bi-pencil"></i> 编辑</a> <a href="/supplier" class="btn btn-outline-secondary btn-sm">返回</a></div></div>
<div class="card stat-card"><div class="card-body"><table class="table table-sm"><tbody>
<tr><td class="text-muted" width="100">名称</td><td><strong><?=htmlspecialchars($supplier['name'])?></strong></td><td class="text-muted" width="100">类型</td><td><?=dict('supplier_type', $supplier['type'])?></td></tr>
<tr><td class="text-muted">联系人</td><td><?=htmlspecialchars($supplier['contact_name']?:'-')?></td><td class="text-muted">手机</td><td><?=phone_link($supplier['contact_mobile']??'')?></td></tr>
<tr><td class="text-muted">备注</td><td><?=htmlspecialchars($supplier['remark']?:'-')?></td><td class="text-muted">地址</td><td><?=htmlspecialchars($supplier['address']?:'-')?></td></tr>
</tbody></table></div></div>
<?php include __DIR__.'/../layout/footer.php'; ?>
