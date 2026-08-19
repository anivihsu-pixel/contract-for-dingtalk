<?php $title='供应商详情'; $menu_active='supplier'; include __DIR__.'/../layout/header.php'; ?>
<div class="d-flex justify-content-between align-items-center mb-3"><h4><?=htmlspecialchars($supplier['name'])?></h4><div><a href="/supplier/<?=$supplier['id']?>/edit" class="btn btn-primary btn-sm"><i class="bi bi-pencil"></i> 编辑</a><?php if(!empty($can_delete)): ?> <button type="button" class="btn btn-outline-danger btn-sm" onclick="delSupplier(<?=$supplier['id']?>)"><i class="bi bi-trash"></i> 删除</button><?php endif; ?> <a href="/supplier" class="btn btn-outline-secondary btn-sm">返回</a></div></div>
<div class="card stat-card"><div class="card-body"><table class="table table-sm"><tbody>
<tr><td class="text-muted" width="100">名称</td><td><strong><?=htmlspecialchars($supplier['name'])?></strong></td><td class="text-muted" width="100">类型</td><td><?=dict('supplier_type', $supplier['type'])?></td></tr>
<tr><td class="text-muted">联系人</td><td><?=htmlspecialchars($supplier['contact_name']?:'-')?></td><td class="text-muted">手机</td><td><?=phone_link($supplier['contact_mobile']??'')?></td></tr>
<tr><td class="text-muted">备注</td><td><?=htmlspecialchars($supplier['remark']?:'-')?></td><td class="text-muted">地址</td><td><?=htmlspecialchars($supplier['address']?:'-')?></td></tr>
</tbody></table></div></div>
<script>
// v2.52.x：删除供应商（软删除；后端校验关联采购合同，删除后进入回收站可恢复或彻底清除）
function delSupplier(id){
  pcConfirm({ message: '确定删除该供应商？删除后进入回收站，可在数据回收站恢复或彻底清除', danger: true }).then(function(ok){
    if(!ok) return;
    $ajax('/ajax/supplier/delete', { method: 'POST', body: new URLSearchParams({id: id}), loading: false }).then(function(res){
      showToast(res.msg || '操作完成', res.code === 0 ? 'success' : 'error');
      if(res.code === 0) location.href = '/supplier';
    }).catch(function(){});
  });
}
</script>
<?php include __DIR__.'/../layout/footer.php'; ?>
