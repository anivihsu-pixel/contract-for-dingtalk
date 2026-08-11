<?php
// 移动端交接办理页（v2.38.25/26）：有权账号（system:user / system:handover）在手机上直接办理
// Tab1 待交接：钉钉同步自动标记的疑似离职员工；Tab2 在职交接：任意在职员工间批量移交数据（默认不禁用）
$title = '数据交接';   // 页面标题，自动追加「 · 合同管理」
$tab = 'more';         // 底部导航高亮：来自「更多」
include __DIR__ . '/_head.php';
?>

<div class="m-nav">
  <a href="/m/more" class="back" aria-label="返回"><i class="bi bi-chevron-left"></i></a>
  <div class="title">数据交接</div>
  <div class="right"></div>
</div>

<!-- 顶部 Tab：待交接 / 在职交接 -->
<div class="m-tabs" id="tabBar">
  <a class="m-tab active" href="javascript:;" data-tab="pending">待交接<?php if(!empty($handoverUsers)):?><span class="badge"><?=count($handoverUsers)?></span><?php endif;?></a>
  <a class="m-tab" href="javascript:;" data-tab="active">在职交接</a>
</div>

<div class="m-page" id="page">
  <!-- Tab1 待交接（疑似离职员工队列） -->
  <div id="tab-pending">
    <div style="padding:12px 16px 0;font-size:13px;color:var(--m-text-2);line-height:1.6">
      <i class="bi bi-info-circle" style="color:var(--m-brand)"></i> 钉钉同步自动检测疑似离职员工进入此队列。「办理交接」将名下客户/合同/待审批移交给指定账号（可同时禁用）；「未离职」仅清除待交接标记。
    </div>
    <?php if(empty($handoverUsers)): ?>
      <div class="m-card"><div class="m-card-bd"><div class="empty"><i class="bi bi-emoji-smile"></i> 暂无待交接员工</div></div></div>
    <?php else: ?>
      <div class="notif-list">
      <?php foreach($handoverUsers as $u): ?>
        <div class="notif-item" style="display:block">
          <div class="notif-row">
            <div class="notif-icon notif-icon-danger"><i class="bi bi-person-x"></i></div>
            <div class="notif-body">
              <div class="notif-title"><span class="notif-tag notif-tag-danger">待交接</span><?=htmlspecialchars($u['name'])?> <small style="color:var(--m-text-3);font-size:12px"><?=htmlspecialchars($u['username'])?></small></div>
              <div class="notif-desc">部门：<?=htmlspecialchars($u['dept_name']?:'-')?> ｜ 客户 <?=(int)$u['customer_count']?> ｜ 合同 <?=(int)$u['contract_count']?> ｜ 待审批 <?=(int)$u['approval_count']?></div>
              <div class="notif-meta" style="justify-content:flex-start;gap:10px;margin-top:10px;border:none">
                <button class="m-btn m-btn-brand m-btn-sm" onclick="openHo(<?=$u['id']?>, <?=htmlspecialchars(json_encode($u['name'],JSON_UNESCAPED_UNICODE),ENT_QUOTES)?>, false)"><i class="bi bi-arrow-repeat"></i> 办理交接</button>
                <button class="m-btn m-btn-ghost m-btn-sm" onclick="clearHo(<?=$u['id']?>)">未离职</button>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- Tab2 在职交接（任意在职员工间批量移交，默认不禁用） -->
  <div id="tab-active" style="display:none">
    <div style="padding:12px 16px 0;font-size:13px;color:var(--m-text-2);line-height:1.6">
      <i class="bi bi-arrow-left-right" style="color:var(--m-brand)"></i> 选择一名在职员工，将其名下客户/合同/待审批批量移交给另一名在职员工（接收人可跨部门），双方均保持在职。
    </div>
    <div style="padding:10px 16px 0">
      <input type="search" class="m-input" id="activeSearch" placeholder="搜索姓名…" style="padding:9px 12px;font-size:14px">
    </div>
    <div class="notif-list" id="activeList">
    <?php if(empty($ho_users)): ?>
      <div class="m-card"><div class="m-card-bd"><div class="empty"><i class="bi bi-emoji-smile"></i> 暂无在职员工</div></div></div>
    <?php else: foreach($ho_users as $u): ?>
      <div class="notif-item" style="display:block" data-name="<?=htmlspecialchars($u['name'], ENT_QUOTES)?>" onclick="openHo(<?=$u['id']?>, <?=htmlspecialchars(json_encode($u['name'],JSON_UNESCAPED_UNICODE),ENT_QUOTES)?>, true)">
        <div class="notif-row">
          <div class="notif-icon notif-icon-approval"><i class="bi bi-person-check"></i></div>
          <div class="notif-body">
            <div class="notif-title"><?=htmlspecialchars($u['name'])?> <small style="color:var(--m-text-3);font-size:12px">#<?=$u['id']?></small></div>
            <div class="notif-meta"><span>部门：<?=htmlspecialchars($u['dept_name']?:'-')?></span><i class="bi bi-chevron-right"></i></div>
          </div>
        </div>
      </div>
    <?php endforeach; endif; ?>
      <div class="empty" id="activeEmpty" style="display:none"><i class="bi bi-search"></i> 未找到匹配员工</div>
    </div>
  </div>
</div>

<!-- 办理交接底部 sheet（接收人下拉 + 范围勾选 + 禁用开关） -->
<div class="m-sheet-mask" id="hoSheet" onclick="if(event.target===this)closeHoSheet()">
  <div class="m-sheet">
    <h3 id="hoSheetTitle">离职交接<span class="ho-from" style="color:var(--m-brand)"></span></h3>
    <div class="m-field">
      <label for="hoToSearch">接收人（移交对象）</label>
      <input type="search" class="m-input" id="hoToSearch" placeholder="搜索姓名…" style="margin-bottom:10px">
      <div class="m-user-list" id="hoToList"></div>
    </div>
    <div class="m-field">
      <label for="hoCust">交接范围</label>
      <div class="m-switch" style="margin-bottom:10px"><input type="checkbox" id="hoCust" checked><span>客户</span></div>
      <div class="m-switch" style="margin-bottom:10px"><input type="checkbox" id="hoCont" checked><span>合同</span></div>
      <div class="m-switch"><input type="checkbox" id="hoAppr" checked><span>待审批</span></div>
    </div>
    <div class="m-switch" style="margin-bottom:16px"><input type="checkbox" id="hoDisable"><span>交接完成后禁用该用户（进入回收站）</span></div>
    <div class="m-sheet-actions">
      <button class="m-btn m-btn-ghost" onclick="closeHoSheet()">取消</button>
      <button class="m-btn m-btn-brand" onclick="submitHo()">确认交接</button>
    </div>
  </div>
</div>

<script>
let hoUsers = <?=json_encode($ho_users??[], JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;
let _hoFromId = 0, _hoFromName = '';
// sheet 显隐：必须用 .show 类（CSS 默认 opacity:0 + pointer-events:none；只改 display 会"弹出但不可见不可点"）
function closeHoSheet(){
  var m = document.getElementById('hoSheet');
  if(m) m.classList.remove('show');
}
// fromActive=true 表示在职交接：标题改「数据交接」、默认不禁用；false 为离职交接（默认禁用）
// 接收人列表不默认列出：输入关键词后才渲染匹配用户（radio 单选）
function renderHoList(fromId, kw){
  var list = document.getElementById('hoToList');
  if(!list) return;
  list.innerHTML = '';
  kw = (kw || '').trim().toLowerCase();
  if(!kw){
    list.innerHTML = '<div class="m-user-empty">输入姓名搜索接收人</div>';
    return;
  }
  var opts = hoUsers.filter(function(u){
    return parseInt(u.id) !== fromId && (u.name || '').toLowerCase().indexOf(kw) >= 0;
  });
  if(!opts.length){ list.innerHTML = '<div class="m-user-empty">未找到匹配用户</div>'; return; }
  opts.forEach(function(u){
    var label = document.createElement('label');
    label.className = 'm-user-opt';
    label.setAttribute('data-name', u.name);
    var radio = document.createElement('input');
    radio.type = 'radio'; radio.name = 'hoTo'; radio.value = u.id;
    var span = document.createElement('span');
    span.textContent = u.name + '（' + (u.dept_name || '-') + '）';
    label.appendChild(radio); label.appendChild(span);
    list.appendChild(label);
  });
}
function openHo(id, name, fromActive){
  _hoFromId = id; _hoFromName = name;
  document.querySelector('.ho-from').textContent = '：' + name;
  var t = document.getElementById('hoSheetTitle');
  if(t) t.innerHTML = (fromActive ? '数据交接' : '离职交接') + '<span class="ho-from" style="color:var(--m-brand)"></span>';
  document.querySelector('.ho-from').textContent = '：' + name;
  var s = document.getElementById('hoToSearch');
  if(s) s.value = '';
  renderHoList(id);
  document.getElementById('hoDisable').checked = !fromActive;
  document.getElementById('hoSheet').classList.add('show');
}
function submitHo(){
  if(!_hoFromId){ toast('参数错误'); return; }
  var checked = document.querySelector('#hoToList input[name="hoTo"]:checked');
  if(!checked){ toast('请选择接收人'); return; }
  var toId = checked.value;
  var toName = '';
  hoUsers.forEach(function(u){ if(String(u.id) === toId) toName = u.name; });
  var body = {
    from_user_id: _hoFromId,
    to_user_id: toId,
    scope_customer: document.getElementById('hoCust').checked ? 1 : 0,
    scope_contract: document.getElementById('hoCont').checked ? 1 : 0,
    scope_approval: document.getElementById('hoAppr').checked ? 1 : 0,
    disable_from: document.getElementById('hoDisable').checked ? 1 : 0
  };
  closeHoSheet();
  confirmAndPost('确认将「' + _hoFromName + '」的客户/合同/待审批数据交接给「' + toName + '」？该操作不可撤销。', '/ajax/admin/user/handover', body, 800);
}
function clearHo(id){
  confirmAndPost('确认该员工并未离职？将清除其待交接标记，不做数据移交。', '/ajax/admin/user/clear-handover', {id: id}, 800);
}
// Tab 切换
(function(){
  var tabBar = document.getElementById('tabBar');
  if(!tabBar) return;
  function show(tab){
    ['pending','active'].forEach(function(k){
      var el = document.getElementById('tab-'+k);
      if(el) el.style.display = (k === tab) ? '' : 'none';
      var chip = tabBar.querySelector('[data-tab="'+k+'"]');
      if(chip) chip.classList.toggle('active', k === tab);
    });
  }
  tabBar.querySelectorAll('[data-tab]').forEach(function(chip){
    chip.addEventListener('click', function(){ show(chip.getAttribute('data-tab')); });
  });
})();
// 在职交接列表搜索（按姓名前端过滤）
(function(){
  var box = document.getElementById('activeSearch');
  var list = document.getElementById('activeList');
  if(!box || !list) return;
  box.addEventListener('input', function(){
    var kw = box.value.trim().toLowerCase();
    var items = list.querySelectorAll('.notif-item[data-name]');
    var shown = 0;
    items.forEach(function(it){
      var ok = !kw || (it.getAttribute('data-name') || '').toLowerCase().indexOf(kw) >= 0;
      it.style.display = ok ? 'block' : 'none';
      if(ok) shown++;
    });
    var empty = document.getElementById('activeEmpty');
    if(empty) empty.style.display = shown ? 'none' : 'block';
  });
})();
// 接收人选择器搜索（sheet 内输入关键词后渲染匹配用户，radio 单选）
(function(){
  var box = document.getElementById('hoToSearch');
  if(!box) return;
  box.addEventListener('input', function(){
    renderHoList(_hoFromId, box.value);
  });
})();
</script>

<?php include __DIR__ . '/_foot.php'; ?>
