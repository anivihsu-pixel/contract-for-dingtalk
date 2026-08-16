<?php
// PC 统一待办中心（v2.38.17 与移动端同口径）：Tab1 待办（审批+审批消息+提醒合并流）/ Tab2 提醒 / Tab3 审批消息
$title='待办中心'; $menu_active='remind';
include __DIR__.'/../layout/header.php';
$__canManage = !empty($is_admin) || in_array('remind:manage', $user_permissions ?? [], true);
?>
<h4 class="mb-3"><i class="bi bi-bell"></i> 待办中心</h4>
<div class="d-flex justify-content-between align-items-center mb-3">
  <div class="text-muted small">待办中心：<strong>待办</strong>（待我审批）· <strong>提醒</strong>（合同/回款/客户跟进）· <strong>审批消息</strong>（站内信）</div>
  <div class="d-flex gap-2">
    <button class="btn btn-outline-secondary btn-sm" onclick="checkRemind()"><i class="bi bi-arrow-clockwise"></i> 检查提醒</button>
    <?php if($__canManage): ?>
    <button class="btn btn-primary btn-sm" onclick="dispatchRemind()"><i class="bi bi-send"></i> 立即推送到钉钉</button>
    <button class="btn btn-outline-info btn-sm" onclick="showPushLog()"><i class="bi bi-clock-history"></i> 推送记录</button>
    <?php endif; ?>
  </div>
</div>
<?php if($__canManage): ?>
<div class="alert alert-light border small text-muted mb-3 py-2">
  <i class="bi bi-info-circle"></i> 提醒已支持每日自动推送（crontab 调用 <code>php think remind:dispatch</code>），合同、回款及客户跟进事项会自动通知对应负责人；上方「立即推送到钉钉」仅用于手动触发或测试。
</div>
<?php endif; ?>

<!-- Tab 切换（v2.38.17：待办 / 提醒 / 审批消息，与移动端一致） -->
<ul class="nav nav-tabs mb-3" id="todoTabs">
  <li class="nav-item"><a class="nav-link active" href="javascript:;" data-tab="todo">待办 <span class="badge bg-danger rounded-pill ms-1"><?=$todo_total?></span></a></li>
  <li class="nav-item"><a class="nav-link" href="javascript:;" data-tab="remind">提醒 <span class="badge bg-secondary rounded-pill ms-1"><?=$total?></span></a></li>
  <li class="nav-item"><a class="nav-link" href="javascript:;" data-tab="notif">审批消息</a></li>
</ul>

<!-- Tab1 待办（v2.38.18 去重后：仅待我审批动作待办；提醒/审批消息由 Tab2/Tab3 独立展示） -->
<div id="tab-todo">
  <div class="card stat-card"><div class="card-body p-0">
    <?php if(empty($todo_list)): ?>
      <div class="text-center py-5 text-muted">暂无待我审批的流程 🎉</div>
    <?php else: foreach($todo_list as $t): ?>
      <a href="<?=$t['link'] ?: '#'?>" class="p-3 border-bottom d-flex align-items-center text-decoration-none text-reset<?=$t['link']==='#'?' disabled':''?>">
        <span class="badge text-bg-danger me-3" style="min-width:44px">审批</span>
        <div class="flex-grow-1">
          <div><?=htmlspecialchars($t['text'] ?? '')?></div>
          <?php if(!empty($t['sub'])): ?><div class="small text-muted"><?=htmlspecialchars($t['sub'])?></div><?php endif; ?>
        </div>
        <i class="bi bi-chevron-right text-muted ms-2"></i>
      </a>
    <?php endforeach; endif; ?>
  </div></div>
</div>

<!-- Tab2 提醒（到期/回款，独立查看，含续约操作） -->
<div id="tab-remind" style="display:none">
  <div class="card stat-card"><div class="card-body p-0">
    <?php if(empty($alerts)): ?>
      <div class="text-center py-5 text-muted">今日暂无提醒 🎉</div>
    <?php else: foreach($alerts as $a):
      $alertLink = ($a['type'] ?? '') === 'customer' ? '/customer/'.(int)$a['id'] : '/contract/'.(int)$a['id'];
    ?>
      <div class="p-3 border-bottom d-flex align-items-center">
        <a href="<?=$alertLink?>" class="flex-grow-1 d-flex align-items-center text-decoration-none text-reset">
          <i class="bi bi-<?=$a['level']=='danger'?'exclamation-triangle-fill text-danger':($a['level']=='warning'?'exclamation-circle text-warning':'info-circle text-info')?> fs-5 me-3"></i>
          <div class="flex-grow-1"><?=htmlspecialchars($a['text'])?></div>
          <i class="bi bi-chevron-right text-muted ms-2"></i>
        </a>
        <?php if(($a['type']??'')==='contract'): ?>
        <button class="btn btn-sm btn-outline-info ms-2 flex-shrink-0" onclick="doRenew(<?=$a['id']?>)"><i class="bi bi-recycle"></i> 续约</button>
        <?php endif; ?>
      </div>
    <?php endforeach; endif; ?>
  </div></div>
</div>

<!-- Tab3 审批消息（站内信兜底，异步加载；标题与 Tab 标签重复已去除，角标统一由侧边栏/工作台承担） -->
<div id="tab-notif" style="display:none">
  <div class="card stat-card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
      <button class="btn btn-sm btn-outline-secondary ms-auto" id="markAll">全部标为已读</button>
    </div>
    <div class="card-body p-0">
      <div class="list-group list-group-flush" id="notifList"></div>
      <div class="card-footer bg-white" id="pg"></div>
    </div>
  </div>
</div>

<!-- 推送记录弹窗 -->
<div class="modal fade" id="pushLogModal" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title"><i class="bi bi-clock-history"></i> 钉钉推送记录</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <div id="pushLogHint" class="small text-muted mb-2"></div>
    <div id="pushLogList"></div>
  </div>
</div></div></div>
<script>
function checkRemind(){
  $ajax('/ajax/remind/check', {silent:true}).then(function(res){
    showToast('检查完成，共 ' + (res.data.total || 0) + ' 条提醒', 'success');
    setTimeout(function(){ location.reload(); }, 800);
  }).catch(function(){});
}
function dispatchRemind(){
  // P1-8：按业务结果分流提示，推送失败不再误报成功
  $ajax('/ajax/remind/dispatch', {method:'POST'}).then(function(res){
    if(res.code === 0){
      var d = res.data || {};
      showToast((d.mock?'[Mock] ':'') + (d.msg || '推送完成'), 'success');
    } else {
      showToast(res.msg || '推送失败', 'error');
    }
  }).catch(function(){});
}
function showPushLog(){
  $ajax('/ajax/remind/push-log', {silent:true}).then(function(res){
    var d = res.data || {}; var logs = d.logs || [];
    document.getElementById('pushLogHint').innerHTML = (d.mock?'<span class="pc-tag pc-tag-warn">Mock 模式</span> 以下为本地模拟发送记录，接入真实钉钉后即为实际工作通知。':'真实钉钉发送记录') + ' 共 ' + (d.total||0) + ' 条';
    var h = '';
    if(!logs.length){ h = '<div class="text-center text-muted py-4">暂无推送记录，点击「立即推送到钉钉」试试</div>'; }
    logs.forEach(function(l){
      var p = l.params || {};
      h += '<div class="border rounded p-2 mb-2"><div class="d-flex justify-content-between"><strong class="small">'+escHtml(p.title||'')+'</strong><small class="text-muted">'+escHtml(l.timestamp||'')+'</small></div>';
      h += '<div class="small text-muted">接收用户ID：'+escHtml((p.user_ids||[]).join(', '))+'</div>';
      h += '<pre class="small mb-0 mt-1" style="white-space:pre-wrap;background:#f8f9fa;padding:6px;border-radius:4px">'+escHtml(p.content||'')+'</pre></div>';
    });
    document.getElementById('pushLogList').innerHTML = h;
    new bootstrap.Modal(document.getElementById('pushLogModal')).show();
  }).catch(function(){});
}
function escHtml(s){ if(s==null) return ''; var d=document.createElement('div'); d.textContent=String(s); return d.innerHTML; }
function doRenew(id){
  pcConfirm({message:'确定基于当前合同生成续约草案？生成后可编辑并走审批流程。'}).then(function(ok){if(!ok)return;
  $ajax('/ajax/contract/'+id+'/renew', {method:'POST'}).then(function(res){
    if(res.code===0){ showToast('续约草案已生成', 'success'); setTimeout(function(){ location.href=res.data.url; }, 600); }
    else { showToast(res.msg||'续约失败', 'error'); }
  }).catch(function(){});
  });
}
// v2.38.17：Tab 切换（待办/提醒/审批消息）；支持 ?tab= 直达（如旧消息中心 /notification 重定向过来）
(function(){
  var tabs = document.querySelectorAll('#todoTabs a[data-tab]');
  if(!tabs.length) return;
  function show(tab){
    ['todo','remind','notif'].forEach(function(k){
      var el = document.getElementById('tab-'+k);
      if(el) el.style.display = (k===tab) ? '' : 'none';
      var link = document.querySelector('#todoTabs a[data-tab="'+k+'"]');
      if(link) link.classList.toggle('active', k===tab);
    });
  }
  var init = new URLSearchParams(location.search).get('tab');
  if (init && ['todo','remind','notif'].indexOf(init) >= 0) show(init);
  tabs.forEach(function(a){
    a.addEventListener('click', function(){ show(a.getAttribute('data-tab')); });
  });
})();
</script>
<script src="<?=asset_url('js/notification.js')?>"></script>
<?php include __DIR__.'/../layout/footer.php'; ?>
