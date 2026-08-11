<?php $title='归档管理'; $menu_active='archive'; include __DIR__.'/../layout/header.php'; ?>
<h4 class="mb-3"><i class="bi bi-archive"></i> 归档管理</h4>
<div class="card stat-card"><div class="table-responsive"><table class="table table-hover mb-0"><thead class="table-light"><tr><th>编号</th><th>标题</th><th>金额</th><th>乙方</th><th>生效</th><th>到期</th></tr></thead><tbody id="tb"></tbody></table></div><div class="card-footer bg-white" id="pg"></div></div>

<script src="/static/js/archive.js"></script>
<?php include __DIR__.'/../layout/footer.php'; ?>

