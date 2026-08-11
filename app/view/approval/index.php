<?php $title='合同审批'; $menu_active='approval'; include __DIR__.'/../layout/header.php'; ?>
<h4 class="mb-3"><i class="bi bi-check2-circle"></i> 合同审批</h4>
<ul class="nav nav-tabs mb-3" id="atabs"><li class="nav-item"><a class="nav-link active" href="#" data-tab="pending">待审批</a></li><li class="nav-item"><a class="nav-link" href="#" data-tab="processed">已审批</a></li><li class="nav-item"><a class="nav-link" href="#" data-tab="submitted">我提交的</a></li></ul>
<div class="card stat-card"><div class="table-responsive"><table class="table table-hover mb-0"><thead class="table-light"><tr><th>合同编号</th><th>标题</th><th>金额</th><th>提交人</th><th>流程</th><th>状态</th><th>时间</th><th>操作</th></tr></thead><tbody id="tb"></tbody></table></div><div class="card-footer bg-white" id="pg"></div></div>
<script src="<?=asset_url('js/approval_index.js')?>"></script>
<?php include __DIR__.'/../layout/footer.php'; ?>

