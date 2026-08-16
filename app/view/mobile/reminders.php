<?php
// 移动端统一待办中心（v2.38.15 方案A）：Tab1 待办（审批+审批消息+提醒合并流）/ Tab2 提醒 / Tab3 审批消息
$title = '待办中心';   // 页面标题，自动追加「 · 合同管理」
$tab = 'home';     // 底部导航高亮：来自工作台
include __DIR__ . '/_head.php';
?>

<div class="m-nav">
  <a href="/m" class="back" aria-label="返回"><i class="bi bi-chevron-left"></i></a>
  <div class="title">待办中心</div>
  <div class="right"></div>
</div>

<!-- 顶部 Tab（v2.38.18 参照审批中心改版：m-tabs 下划线式 + 计数 badge） -->
<div class="m-tabs" id="tabBar">
  <a class="m-tab active" href="javascript:;" data-tab="todo">待办<?php if(count($todo_list ?? [])>0):?><span class="badge"><?=count($todo_list)?></span><?php endif;?></a>
  <a class="m-tab" href="javascript:;" data-tab="remind">提醒<?php if(count($alerts)>0):?><span class="badge"><?=count($alerts)?></span><?php endif;?></a>
  <a class="m-tab" href="javascript:;" data-tab="notif">站内消息</a>
</div>

<div class="m-page" id="page">
  <!-- Tab1 待办（2026-08-05 与审批消息同风格：独立白卡列表，去掉外层卡片避免嵌套底图） -->
  <div id="tab-todo">
    <div class="notif-list">
      <?php if(empty($todo_list)): ?>
        <div class="empty"><i class="bi bi-emoji-smile"></i> 暂无待处理事项</div>
      <?php else:
          foreach($todo_list as $t):
            $isNotif = ($t['kind'] ?? '') === 'notif';
      ?>
        <a href="<?=$t['link'] ?: '#'?>" class="notif-item" style="display:block;text-decoration:none;color:inherit"<?=$t['link']==='#'?' style="pointer-events:none"':''?>>
          <div class="notif-row">
            <div class="notif-icon <?=$isNotif ? 'notif-icon-info' : 'notif-icon-approval'?>"><i class="bi <?=$isNotif ? 'bi-bell' : 'bi-clipboard-check'?>"></i></div>
            <div class="notif-body">
              <div class="notif-title"><span class="notif-tag <?=$isNotif ? 'notif-tag-info' : 'notif-tag-approval'?>"><?=$isNotif ? '消息' : '审批'?></span><?=htmlspecialchars($t['text'] ?? '')?></div>
              <?php if(!empty($t['sub'])): ?><div class="notif-desc"><?=htmlspecialchars($t['sub'])?></div><?php endif; ?>
              <div class="notif-meta"><span><?=$isNotif ? '站内消息' : '待我审批'?></span><i class="bi bi-chevron-right"></i></div>
            </div>
          </div>
        </a>
      <?php endforeach; endif; ?>
    </div>
    <!-- P2：Tab1 待办加载更多（复用 /ajax/approval/pending-list 分页接口，按钮风格对齐 Tab3；首屏 10 条为服务端渲染，总数超过则显示） -->
    <div id="todoMoreWrap" style="text-align:center;padding:12px;<?= !empty($pending_total) && (int)$pending_total > count($todo_list ?? []) ? '' : 'display:none;' ?>">
      <button class="m-btn m-btn-ghost" id="todoMoreBtn" style="font-size:13px;">加载更多</button>
    </div>
  </div>

  <!-- Tab2 提醒（2026-08-05 与审批消息同风格：独立白卡列表，去掉外层卡片避免嵌套底图） -->
  <div id="tab-remind" style="display:none">
    <div class="notif-list">
      <?php if(empty($alerts)): ?>
        <div class="empty"><i class="bi bi-emoji-smile"></i> 今日无合同、回款或客户跟进提醒</div>
      <?php else: $__ai = 0; foreach($alerts as $a):
          $lv = $a['level'] ?? 'info';
          $iconCls = $lv==='danger' ? 'notif-icon-danger bi-exclamation-triangle-fill' : ($lv==='warning' ? 'notif-icon-warn bi-exclamation-circle-fill' : 'notif-icon-info bi-info-circle-fill');
          $tagCls  = $lv==='danger' ? 'notif-tag-danger' : ($lv==='warning' ? 'notif-tag-warn' : 'notif-tag-info');
          $tagTxt  = $lv==='danger' ? '逾期' : ($lv==='warning' ? '紧急' : '提醒');
          $type = $a['type'] ?? '';
          // 合同/回款提醒均可跳转：contract 跳合同详情；payment 跳合同详情并定位到回款区块（对齐 PC 端 remind/index.php 的 payment 分支）
          $link = $type === 'customer' && !empty($a['id'])
              ? '/m/customer/'.$a['id']
              : (in_array($type, ['contract', 'payment'], true) && !empty($a['id'])
                  ? '/m/contract/'.$a['id'].($type === 'payment' ? '#payments' : '') : '#');
          $typeTxt = $type === 'payment' ? '回款' : ($type === 'customer' ? '客户跟进' : '合同');
      ?>
        <a href="<?=$link?>" class="notif-item" data-idx="<?=$__ai++?>" style="display:block;text-decoration:none;color:inherit"<?=$link==='#'?' style="pointer-events:none"':''?>>
          <div class="notif-row">
            <div class="notif-icon <?=$iconCls?>"></div>
            <div class="notif-body">
              <div class="notif-title"><span class="notif-tag <?=$tagCls?>"><?=$tagTxt?></span><?=htmlspecialchars($a['text'] ?? '')?></div>
              <div class="notif-meta"><span><?=$typeTxt?></span><i class="bi bi-chevron-right"></i></div>
            </div>
          </div>
        </a>
      <?php endforeach; endif; ?>
    </div>
    <!-- P2：Tab2 提醒客户端分批展示（后端 getTodayAlerts 全量返回、无分页接口；首屏每批 10 条，超过则显示按钮逐批展开） -->
    <div id="remindMoreWrap" style="text-align:center;padding:12px;display:none;">
      <button class="m-btn m-btn-ghost" id="remindMoreBtn" style="font-size:13px;">加载更多</button>
    </div>
  </div>

  <!-- Tab3 审批消息（2026-08-05 按原消息中心样式：独立白卡列表+头部行，去掉外层卡片避免嵌套底图） -->
  <div id="tab-notif" style="display:none">
    <div class="d-flex justify-content-between align-items-center" style="padding:12px 16px 0;">
      <span style="font-size:14px;color:var(--m-text-2)">审批消息</span>
      <a href="javascript:void(0)" id="markAllReadBtn" style="font-size:13px;color:var(--m-brand)">全部已读</a>
    </div>
    <div class="notif-list" id="notifList">
      <div class="empty" id="loadingTip"><i class="bi bi-arrow-clockwise"></i> 加载中…</div>
    </div>
    <div id="loadMoreWrap" style="text-align:center;padding:12px;display:none;">
      <button class="m-btn m-btn-ghost" id="loadMoreBtn" style="font-size:13px;">加载更多</button>
    </div>
  </div>
</div>

<script>
(function(){
  var tabBar = document.getElementById('tabBar');
  if (!tabBar) return;
  function show(tab){
    ['todo','remind','notif'].forEach(function(k){
      var el = document.getElementById('tab-'+k);
      if (el) el.style.display = (k === tab) ? '' : 'none';
      var chip = tabBar.querySelector('[data-tab="'+k+'"]');
      if (chip) chip.classList.toggle('active', k === tab);
    });
  }
  // 支持 ?tab=notif 直达（旧消息中心 /m/notifications 重定向过来）
  var init = new URLSearchParams(location.search).get('tab');
  if (init && ['todo','remind','notif'].indexOf(init) >= 0) show(init);
  tabBar.querySelectorAll('[data-tab]').forEach(function(chip){
    chip.addEventListener('click', function(){ show(chip.getAttribute('data-tab')); });
  });

  // P2：Tab1 待办「加载更多」——复用 /ajax/approval/pending-list 分页接口（page/limit），
  // 渲染口径与 PHP 首屏 buildTodoStream 一致；已渲染条数 ≥ 总数则隐藏按钮（风格对齐 Tab3）
  var todoMoreWrap = document.getElementById('todoMoreWrap');
  var todoMoreBtn = document.getElementById('todoMoreBtn');
  if(todoMoreWrap && todoMoreBtn){
    var todoPage = 2, todoLoading = false;
    todoMoreBtn.addEventListener('click', function(){
      if(todoLoading) return;
      todoLoading = true;
      fetch('/ajax/approval/pending-list?page=' + todoPage + '&limit=10', {
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      })
      .then(function(r){ return r.json(); })
      .then(function(res){
        todoLoading = false;
        if(res.code !== 0 || !res.data || !res.data.length){ todoMoreWrap.style.display = 'none'; return; }
        var box = document.querySelector('#tab-todo .notif-list');
        var h = '';
        res.data.forEach(function(p){
          h += '<a href="/m/approval/' + p.id + '" class="notif-item" style="display:block;text-decoration:none;color:inherit">'
            + '<div class="notif-row">'
            + '<div class="notif-icon notif-icon-approval"><i class="bi bi-clipboard-check"></i></div>'
            + '<div class="notif-body">'
            + '<div class="notif-title"><span class="notif-tag notif-tag-approval">审批</span>' + esc('待我审批：《' + (p.contract_title || '合同') + '》') + '</div>'
            + '<div class="notif-desc">' + esc((p.submitter_name || '') + ' 提交 · ' + (p.flow_name || '审批')) + '</div>'
            + '<div class="notif-meta"><span>待我审批</span><i class="bi bi-chevron-right"></i></div>'
            + '</div></div></a>';
        });
        box.insertAdjacentHTML('beforeend', h);
        todoPage++;
        if(document.querySelectorAll('#tab-todo .notif-item').length >= (res.count || 0)){ todoMoreWrap.style.display = 'none'; }
      })
      .catch(function(){ todoLoading = false; });
    });
  }

  // P2：Tab2 提醒「加载更多」——后端 getTodayAlerts 全量返回、无分页接口，
  // 改为客户端分批展示（每批 10 条），点击逐批展开；全部展开后隐藏按钮
  var remindMoreWrap = document.getElementById('remindMoreWrap');
  var remindMoreBtn = document.getElementById('remindMoreBtn');
  if(remindMoreWrap && remindMoreBtn){
    var remindItems = document.querySelectorAll('#tab-remind .notif-item');
    var REMIND_PAGE = 10;
    if(remindItems.length > REMIND_PAGE){
      var remindShown = REMIND_PAGE;
      remindItems.forEach(function(el, i){ if(i >= REMIND_PAGE) el.style.display = 'none'; });
      remindMoreWrap.style.display = 'block';
      remindMoreBtn.addEventListener('click', function(){
        var next = Math.min(remindShown + REMIND_PAGE, remindItems.length);
        for(var i = remindShown; i < next; i++){ remindItems[i].style.display = ''; }
        remindShown = next;
        if(remindShown >= remindItems.length){ remindMoreWrap.style.display = 'none'; }
      });
    }
  }
})();
</script>
<script src="<?=asset_url('js/mobile-notifications.js')?>"></script>
<?php include __DIR__ . '/_foot.php'; ?>
