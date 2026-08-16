// 移动端审批消息列表（2026-08-05 自原消息中心 mobile/notifications.php 提取为公共脚本）
// 供「待办中心」/m/remind Tab3 复用：卡片样式 + 加载更多 + 单条/全部标记已读 + 审批目标存在性检查
// 依赖 mobile-common.js（csrfToken/toast/confirmAndPost）；容器需提供 #notifList / #loadingTip / #loadMoreWrap / #loadMoreBtn / #markAllReadBtn
(function () {
  var page = 1;
  var loading = false;
  var hasMore = true;

  // 类型标签/图标映射
  var TAG_LABEL = {
    'APPROVAL_SUBMITTED': '待审批',
    'APPROVAL_REJECTED': '已驳回',
    'APPROVAL_APPROVED': '已通过',
    'APPROVAL_TRANSFERRED': '已转交',
    'APPROVAL_CC': '抄送',
    'APPROVAL_OVERDUE': '超时',
    'CONTRACT_EXECUTION_CC': '执行抄送'
  };
  var TAG_ICON = {
    'APPROVAL_SUBMITTED': 'bi-clipboard-check',
    'APPROVAL_REJECTED': 'bi-x-circle',
    'APPROVAL_APPROVED': 'bi-check-circle',
    'APPROVAL_TRANSFERRED': 'bi-forward',
    'APPROVAL_CC': 'bi-people',
    'APPROVAL_OVERDUE': 'bi-exclamation-triangle',
    'CONTRACT_EXECUTION_CC': 'bi-bell'
  };

  function fmtTime(t) {
    if (!t) return '';
    var d = new Date(t.replace(/-/g, '/'));
    var now = new Date();
    if (d.toDateString() === now.toDateString()) return (d.getHours() < 10 ? '0' : '') + d.getHours() + ':' + (d.getMinutes() < 10 ? '0' : '') + d.getMinutes();
    return (d.getMonth() + 1) + '/' + d.getDate();
  }

  function esc(s) {
    if (!s) return '';
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  function render(item) {
    var unread = !item.is_read;
    var tag = TAG_LABEL[item.type] || '消息';
    var icon = TAG_ICON[item.type] || 'bi-bell';
    var url = item.url || '';
    // 钉钉深链重映射
    if (url && /\/dingtalk\/entry\?to=/.test(url)) {
      var m = url.match(/[?&]to=([^&]+)/);
      if (m) { url = decodeURIComponent(m[1]).replace(/^\/approval\/(\d+)$/, '/m/approval/$1'); }
    }
    // 提取审批 ID（用于点击前检查目标是否存在）
    var apprId = '';
    var am = url.match(/\/(?:m\/)?approval\/(\d+)/);
    if (am) { apprId = am[1]; }
    if (!url) { url = 'javascript:void(0)'; }
    return '<a href="' + url + '" class="notif-item ' + (unread ? 'unread' : 'read') + '" data-id="' + item.id + '" data-unread="' + (unread ? '1' : '0') + '" data-appr-id="' + apprId + '" style="display:block;text-decoration:none;color:inherit">'
      + '<div class="notif-row">'
        + '<div class="notif-icon"><i class="bi ' + icon + '"></i></div>'
        + '<div class="notif-body">'
          + '<div class="notif-title"><span class="notif-tag notif-tag-' + item.type + '">' + tag + '</span> ' + esc(item.title || '系统消息') + '</div>'
          + '<div class="notif-desc">' + esc(item.content || '').replace(/\n/g, '<br>') + '</div>'
          + '<div class="notif-meta">'
            + '<span>' + fmtTime(item.created_at) + '</span>'
            + (unread ? '<span class="notif-action" onclick="event.preventDefault();event.stopPropagation();markReadOne(' + item.id + ',this)">标记已读</span>' : '')
          + '</div>'
        + '</div>'
      + '</div>'
      + '</a>';
  }

  function load(p) {
    if (loading || (p > 1 && !hasMore)) return;
    loading = true;
    if (p === 1) { document.getElementById('loadingTip').innerHTML = '<i class="bi bi-arrow-clockwise"></i> 加载中…'; }
    fetch('/ajax/notification/list?page=' + p + '&page_size=20', {
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        loading = false;
        var box = document.getElementById('notifList');
        if (p === 1) { box.innerHTML = ''; }
        if (res.code !== 0 || !res.data || !Array.isArray(res.data)) {
          if (p === 1) { box.innerHTML = '<div class="empty"><i class="bi bi-bell-slash"></i> 暂无消息</div>'; }
          hasMore = false;
          document.getElementById('loadMoreWrap').style.display = 'none';
          return;
        }
        var list = res.data;
        var html = '';
        for (var i = 0; i < list.length; i++) { html += render(list[i]); }
        if (p === 1) { box.innerHTML = html; } else { box.insertAdjacentHTML('beforeend', html); }
        hasMore = list.length >= 20;
        document.getElementById('loadMoreWrap').style.display = hasMore ? 'block' : 'none';
        if (p === 1 && list.length === 0) { box.innerHTML = '<div class="empty"><i class="bi bi-bell-slash"></i> 暂无消息</div>'; }
        bindClickCheck();
      })
      .catch(function () {
        loading = false;
        if (p === 1) { document.getElementById('loadingTip').innerHTML = '<div class="empty"><i class="bi bi-exclamation-circle"></i> 加载失败，请重试</div>'; }
      });
  }

  // 单条标记已读（列表内按钮）
  window.markReadOne = function (id, el) {
    fetch('/ajax/notification/mark-read', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-TOKEN': csrfToken(), 'X-Requested-With': 'XMLHttpRequest' },
      body: 'id=' + id
    })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (res.code === 0) {
          var item = el.closest('.notif-item');
          item.classList.remove('unread'); item.classList.add('read');
          el.style.display = 'none';
        } else { toast(res.msg || '操作失败'); }
      });
  };

  // 点击拦截：未读消息立即标记已读；审批类消息检查目标是否存在
  function bindClickCheck() {
    var items = document.querySelectorAll('.notif-item:not([data-checked])');
    items.forEach(function (a) {
      a.setAttribute('data-checked', '1');
      var apprId = a.getAttribute('data-appr-id');
      var notifId = a.getAttribute('data-id');
      a.addEventListener('click', function (e) {
        if (a.getAttribute('data-verified') === '1') return; // 已验证过，放行跳转

        // 未读消息：调用 mark-read（不立即灰化 UI，跳走后重新加载自然为已读样式）
        if (a.getAttribute('data-unread') === '1') {
          fetch('/ajax/notification/mark-read', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-TOKEN': csrfToken(), 'X-Requested-With': 'XMLHttpRequest' },
            body: 'id=' + notifId
          }).catch(function () {});
          a.setAttribute('data-unread', '0');
          var action = a.querySelector('.notif-action');
          if (action) { action.style.display = 'none'; }
        }

        if (!apprId) return; // 无审批 ID，不拦截，直接跳转

        e.preventDefault();
        // 调后端接口检查审批存在性
        fetch('/ajax/notification/check-target?id=' + notifId, {
          credentials: 'same-origin',
          headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
          .then(function (r) { return r.json(); })
          .then(function (res) {
            if (res.code === 0 && res.data && res.data.exists) {
              a.setAttribute('data-verified', '1');
              window.location.href = a.href;
            } else {
              toast('原审批已删除');
              a.style.opacity = '.5';
              a.style.pointerEvents = 'none';
            }
          })
          .catch(function () {
            a.setAttribute('data-verified', '1');
            window.location.href = a.href;
          });
      });
    });
  }

  var markAllBtn = document.getElementById('markAllReadBtn');
  if (markAllBtn) {
    markAllBtn.onclick = function () {
      confirmAndPost('确认将所有消息标记为已读？', '/ajax/notification/mark-all-read', {}, 0, function (res) {
        if (res.code === 0) { setTimeout(function () { page = 1; load(1); }, 400); }
      });
    };
  }

  var loadMore = document.getElementById('loadMoreBtn');
  if (loadMore) {
    loadMore.onclick = function () { page++; load(page); };
  }

  load(1);
})();
