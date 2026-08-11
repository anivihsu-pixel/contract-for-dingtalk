<?php $title='公海池'; $menu_active='customer'; include __DIR__.'/../layout/header.php'; ?>
<h4 class="mb-3"><i class="bi bi-water"></i> 公海池</h4>
<div class="card stat-card"><div class="table-responsive"><table class="table table-hover mb-0"><thead class="table-light"><tr><th>名称</th><th>信用代码</th><th>联系人</th><th>手机</th><th>操作</th></tr></thead><tbody id="tb"></tbody></table></div><div class="card-footer bg-white" id="pg"></div></div>
<script src="<?=asset_url('js/customer_pool.js')?>"></script>
<?php include __DIR__.'/../layout/footer.php'; ?>

