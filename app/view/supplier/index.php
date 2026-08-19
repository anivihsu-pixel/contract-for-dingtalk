<?php $title='供应商管理'; $menu_active='supplier'; include __DIR__.'/../layout/header.php'; ?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2"><h4><i class="bi bi-truck"></i> 供应商管理</h4><?php if(!empty($can_create_supplier)): ?><a href="/supplier/create" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> 新增供应商</a><?php endif; ?></div>
<div class="card stat-card mb-3"><div class="card-body">
<form id="supplierSearchForm" class="row g-2">
<div class="col-md-8"><input type="text" name="keyword" class="form-control form-control-sm" placeholder="搜索供应商名称、联系人、手机..." value="<?=htmlspecialchars($keyword??'')?>" onchange="this.form.submit()" onkeydown="if(event.key==='Enter'){event.preventDefault();this.form.submit();}"></div>
<?php if(!empty($can_scope_toggle)): ?><input type="hidden" name="scope" value="<?=htmlspecialchars($scope??'me')?>"><?php endif; ?>
<div class="col-md-2"><button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-search"></i> 搜索</button></div>
</form>
<?php $curScope = ($scope ?? 'me') === 'all' ? 'all' : 'me'; $scopeQ = $curScope === 'all' ? '&scope=all' : '&scope=me'; ?>
<!-- v2.52.2：查看范围切换（我的供应商/全部供应商）——URL 驱动，默认「我的供应商」、刷新保持；仅能查看他人供应商的账号显示 -->
<?php if(!empty($can_scope_toggle)): ?>
<div class="d-flex gap-1 flex-wrap align-items-center mt-2 mb-1">
  <span class="text-muted small me-1">查看范围：</span>
  <a href="/supplier?scope=me" data-scope="me" class="btn btn-sm scope-chip <?=$curScope==='me'?'btn-primary':'btn-outline-primary'?>">我的供应商</a>
  <a href="/supplier?scope=all" data-scope="all" class="btn btn-sm scope-chip <?=$curScope==='all'?'btn-primary':'btn-outline-primary'?>">全部供应商</a>
</div>
<?php endif; ?>
<!-- P1-1：类型筛选芯片化（对齐移动端 .m-chip；GET 链接保留关键词与查看范围） -->
<div class="pc-chips mt-2">
  <a href="/supplier?<?=!empty($keyword)?'keyword='.urlencode($keyword).'&':''?><?=$curScope==='all'?'scope=all&':''?>" class="<?=($type??'')===''?'active':''?>">全部类型</a>
  <?php foreach(dict('supplier_type') as $__k=>$__v): ?><a href="/supplier?type=<?=urlencode($__k)?><?=!empty($keyword)?'&keyword='.urlencode($keyword):''?><?=$scopeQ?>" class="<?=($type??'')===$__k?'active':''?>"><?=htmlspecialchars($__v)?></a><?php endforeach; ?>
</div>
</div></div>
<div class="card stat-card"><div class="table-responsive"><table class="table table-hover mb-0"><thead class="table-light"><tr><th>名称</th><th>类型</th><th>联系人</th><th>手机</th><th>归属</th><th>状态</th><th>操作</th></tr></thead><tbody>
<?php if(!empty($suppliers)): foreach($suppliers as $s): ?>
<tr><td><a href="/supplier/<?=$s['id']?>"><?=htmlspecialchars($s['name'])?></a></td>
<?php $sType = dict('supplier_type', $s['type']); ?><td><?=$sType !== $s['type'] ? '<span class="pc-tag pc-tag-info">'.$sType.'</span>' : '<span class="pc-tag pc-tag-muted">其他</span>'?></td>
<td><?=htmlspecialchars($s['contact_name']?:'-')?></td>
<td><?=phone_link($s['contact_mobile']??'', false)?></td>
<td><?=htmlspecialchars($owner_names[$s['owner_id']] ?? '未分配')?></td>
<td><?=$s['status']==1?'<span class="pc-tag pc-tag-ok">正常</span>':'<span class="pc-tag pc-tag-danger">禁用</span>'?></td>
<td><a href="/supplier/<?=$s['id']?>/edit" class="btn btn-sm btn-primary" aria-label="编辑"><i class="bi bi-pencil"></i></a><?php if(!empty($can_delete)): ?><button type="button" class="btn btn-sm btn-outline-danger ms-1" onclick="delSupplier(<?=$s['id']?>)" aria-label="删除"><i class="bi bi-trash"></i></button><?php endif; ?></td></tr>
<?php endforeach; else: ?><tr><td colspan="7" class="text-center py-5"><div class="text-muted"><i class="bi bi-truck" style="font-size:2rem"></i><div class="mt-2 fw-semibold">暂无供应商</div><div class="small">新增供应商后可在合同中选择使用</div></div></td></tr><?php endif; ?>
</tbody></table></div></div>
<script>
// v2.52.2：查看范围记忆——无显式 scope 参数时按 localStorage 上次选择跳转（默认/记忆「我的」与服务端默认一致，无需跳转）
(function(){
  if(new URLSearchParams(location.search).has('scope')) return;
  var saved = null; try{ saved = localStorage.getItem('supplier_list_scope'); }catch(e){}
  if(!saved || saved === 'me') return;
  var p = new URLSearchParams(location.search);
  p.set('scope', 'all');
  location.replace('/supplier?' + p.toString());
})();
// 切换时写入记忆（下次从无参入口进入保持上次选择）
document.querySelectorAll('.scope-chip[data-scope]').forEach(function(a){
  a.addEventListener('click', function(){
    try{ localStorage.setItem('supplier_list_scope', a.getAttribute('data-scope')); }catch(e){}
  });
});
// v2.52.x：删除供应商（软删除；后端校验关联采购合同，删除后进入回收站可恢复或彻底清除）
window.delSupplier = function(id){
  pcConfirm({ message: '确定删除该供应商？删除后进入回收站，可在数据回收站恢复或彻底清除', danger: true }).then(function(ok){
    if(!ok) return;
    $ajax('/ajax/supplier/delete', { method: 'POST', body: new URLSearchParams({id: id}), loading: false }).then(function(res){
      showToast(res.msg || '操作完成', res.code === 0 ? 'success' : 'error');
      if(res.code === 0) location.reload();
    }).catch(function(){});
  });
};
</script>
<?php include __DIR__.'/../layout/footer.php'; ?>
