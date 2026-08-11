<?php $title='数据回收站'; $menu_active='recycle'; include __DIR__.'/../layout/header.php'; ?>
<h4 class="mb-3"><i class="bi bi-trash3"></i> 数据回收站</h4>
<p class="text-muted small">已删除的合同 / 客户 / 供应商会进入回收站，可在此恢复或彻底删除。彻底删除将物理清除数据且不可恢复，存在关联阻塞项（如关联合同、回款、子合同）时不可删除。</p>

<!-- 类型切换 -->
<ul class="nav nav-tabs mb-3" id="typeTabs">
  <li class="nav-item"><a class="nav-link <?=$type==='contract'?'active':''?>" href="javascript:void(0)" data-type="contract">合同</a></li>
  <li class="nav-item"><a class="nav-link <?=$type==='customer'?'active':''?>" href="javascript:void(0)" data-type="customer">客户</a></li>
  <li class="nav-item"><a class="nav-link <?=$type==='supplier'?'active':''?>" href="javascript:void(0)" data-type="supplier">供应商</a></li>
</ul>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div class="input-group" style="max-width:320px">
    <input type="text" id="keyword" class="form-control" placeholder="搜索名称…" value="">
    <button class="btn btn-outline-secondary" id="btnSearch" type="button"><i class="bi bi-search"></i> 搜索</button>
  </div>
  <div class="text-muted small" id="totalInfo"></div>
</div>

<div class="table-responsive">
  <table class="table table-hover align-middle">
    <thead>
      <tr>
        <th>类型</th><th>名称</th><th>归属人</th><th>删除时间</th><th>阻塞项</th><th class="text-end">操作</th>
      </tr>
    </thead>
    <tbody id="listBody"></tbody>
  </table>
</div>

<!-- 分页 -->
<div class="d-flex justify-content-between align-items-center">
  <div class="text-muted small" id="pageInfo"></div>
  <div>
    <button class="btn btn-sm btn-outline-secondary" id="btnPrev">上一页</button>
    <button class="btn btn-sm btn-outline-secondary" id="btnNext">下一页</button>
  </div>
</div>

<!-- 彻底删除二次确认 -->
<div class="modal fade" id="purgeModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title text-danger">彻底删除确认</h5></div>
      <div class="modal-body">
        <p>此操作将<strong>物理删除</strong>该数据，<strong class="text-danger">不可恢复</strong>。确定继续？</p>
        <p class="text-muted small mb-0" id="purgeTarget"></p>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
        <button class="btn btn-danger" id="btnPurgeConfirm">确定彻底删除</button>
      </div>
    </div>
  </div>
</div>

<script>
var RB = { type: '<?=htmlspecialchars($type)?>', page: 1, pageSize: 10, total: 0, pendingPurge: null };

function loadList() {
  var kw = document.getElementById('keyword').value.trim();
  $ajax('/ajax/recycle/list?type=' + encodeURIComponent(RB.type) + '&page=' + RB.page + '&page_size=' + RB.pageSize + '&keyword=' + encodeURIComponent(kw), {loading:false})
    .then(function (res) {
      if (res.code !== 0) { showToast(res.msg || '加载失败', 'error'); return; }
      var d = res.data || {};
      RB.total = d.total || 0;
      renderRows(d.list || []);
      renderPager();
      document.getElementById('totalInfo').textContent = '共 ' + RB.total + ' 条';
    })
    .catch(function () { showToast('网络异常', 'error'); });
}

function renderRows(list) {
  var body = document.getElementById('listBody');
  if (!list.length) { body.innerHTML = emptyState({colspan:6, icon:'bi-trash3', title:'回收站为空', desc:'删除的合同、客户、供应商将出现在这里'}); return; }
  var html = '';
  list.forEach(function (r) {
    var sub = r.sub ? ' <span class="text-muted small">（' + esc(r.sub) + '）</span>' : '';
    var blockers = (r.blockers && r.blockers.length)
      ? '<span class="text-danger small">' + esc(r.blockers.join('；')) + '</span>'
      : '<span class="text-muted">无</span>';
    var canPurge = r.can_purge ? '' : ' disabled title="存在关联阻塞项，需先恢复并处理关联"';
    html += '<tr>'
      + '<td>' + esc(r.type_label) + '</td>'
      + '<td>' + esc(r.name || '—') + sub + '</td>'
      + '<td>' + esc(r.owner_name || '—') + '</td>'
      + '<td>' + esc(r.deleted_at || '—') + '</td>'
      + '<td>' + blockers + '</td>'
      + '<td class="text-end">'
      +   '<button class="btn btn-sm btn-outline-primary me-1" onclick="doRestore(\'' + r.type + '\',' + r.id + ')">恢复</button>'
      +   '<button class="btn btn-sm btn-outline-danger"' + canPurge + ' onclick="openPurge(\'' + r.type + '\',' + r.id + ',\'' + esc(r.name || '') + '\')">彻底删除</button>'
      + '</td></tr>';
  });
  body.innerHTML = html;
}

function renderPager() {
  var pages = Math.max(1, Math.ceil(RB.total / RB.pageSize));
  document.getElementById('pageInfo').textContent = '第 ' + RB.page + ' / ' + pages + ' 页';
  document.getElementById('btnPrev').disabled = RB.page <= 1;
  document.getElementById('btnNext').disabled = RB.page >= pages;
}

function doRestore(type, id) {
  $ajax('/ajax/recycle/restore', { method:'POST', body: new URLSearchParams({ type: type, id: id }), loadingText:'恢复中…' })
    .then(function (res) { showToast(res.msg || '已恢复', res.code === 0 ? 'success' : 'error'); if (res.code === 0) loadList(); })
    .catch(function () { showToast('网络异常', 'error'); });
}

function openPurge(type, id, name) {
  RB.pendingPurge = { type: type, id: id };
  document.getElementById('purgeTarget').textContent = '类型：' + type + '，名称：' + name + '（ID ' + id + '）';
  var m = new bootstrap.Modal(document.getElementById('purgeModal'));
  m.show();
}

document.getElementById('btnPurgeConfirm').addEventListener('click', function () {
  if (!RB.pendingPurge) return;
  var p = RB.pendingPurge;
  var m = bootstrap.Modal.getInstance(document.getElementById('purgeModal'));
  if (m) m.hide();
  $ajax('/ajax/recycle/purge', { method:'POST', body: new URLSearchParams({ type: p.type, id: p.id }), loadingText:'删除中…' })
    .then(function (res) {
      if (res.code !== 0) {
        var extra = (res.data && res.data.blockers) ? '（' + res.data.blockers.join('；') + '）' : '';
        showToast((res.msg || '删除失败') + extra, 'error');
        return;
      }
      showToast(res.msg || '已彻底删除', 'success');
      loadList();
    })
    .catch(function () { showToast('网络异常', 'error'); });
});

// 类型切换
document.querySelectorAll('#typeTabs .nav-link').forEach(function (a) {
  a.addEventListener('click', function () {
    document.querySelectorAll('#typeTabs .nav-link').forEach(function (x) { x.classList.remove('active'); });
    a.classList.add('active');
    RB.type = a.getAttribute('data-type');
    RB.page = 1;
    loadList();
  });
});

document.getElementById('btnSearch').addEventListener('click', function () { RB.page = 1; loadList(); });
document.getElementById('keyword').addEventListener('keydown', function (e) { if (e.key === 'Enter') { RB.page = 1; loadList(); } });
document.getElementById('btnPrev').addEventListener('click', function () { if (RB.page > 1) { RB.page--; loadList(); } });
document.getElementById('btnNext').addEventListener('click', function () { RB.page++; loadList(); });

// 初始加载：footer 的 app.js（定义 $ajax/esc/showToast）在本脚本之后才加载，
// 故必须等 DOMContentLoaded（所有同步脚本执行完）后再触发，避免「$ajax 未定义」同步抛错导致列表永不渲染。
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', function () { loadList(); });
} else {
  loadList();
}
</script>
<?php include __DIR__.'/../layout/footer.php'; ?>
