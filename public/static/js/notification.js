// 站内消息中心（审批事件兜底）— v2.38.1 统一 PC+移动，修复 fetch/CSRF/排版/badge 问题
// 2026-08-05 修复：PC 端点击报错（mark-read 接口返回无 data 字段）、字号颜色无重点、消息挤在一起
(function () {
  var isMobile = !!document.querySelector('.m-page'); // 移动端特征容器

  function setBadge(el, c) {
    if (!el) return;
    if (c > 0) { el.textContent = c > 99 ? '99+' : c; el.classList.remove('d-none'); }
    else { el.classList.add('d-none'); }
  }

  function updateBadge() {
    $ajax('/ajax/notification/unread-count', { loading: false, silent: true }).then(function (res) {
      var c = (res && res.count) || 0;
      setBadge(document.getElementById('msgUnread'), c);
      // 2026-08-05 去重：PC 侧边栏已并入「提醒」单入口，红点口径统一由后端合并计算（提醒数+站内信未读），
      // 此处不再覆盖 remindBadge，避免与后端口径冲突；标记已读后跳转/刷新自然更新
    }).catch(function () {});
  }

  // 类型标签映射
  var TAG_LABEL = {
    'APPROVAL_SUBMITTED':'待审批',
    'APPROVAL_REJECTED':'已驳回',
    'APPROVAL_APPROVED':'已通过',
    'APPROVAL_TRANSFERRED':'已转交',
    'APPROVAL_CC':'抄送',
    'APPROVAL_OVERDUE':'超时'
  };
  var TAG_CLASS = {
    'APPROVAL_SUBMITTED':'bg-primary bg-opacity-10 text-primary',
    'APPROVAL_REJECTED':'bg-danger bg-opacity-10 text-danger',
    'APPROVAL_APPROVED':'bg-success bg-opacity-10 text-success',
    'APPROVAL_TRANSFERRED':'bg-warning bg-opacity-10 text-warning',
    'APPROVAL_CC':'bg-info bg-opacity-10 text-info',
    'APPROVAL_OVERDUE':'bg-danger bg-opacity-10 text-danger'
  };

  var page = 1, pageSize = 15;
  function load() {
    $ajax('/ajax/notification/list?page=' + page + '&limit=' + pageSize, { loading: false, silent: true }).then(function (res) {
      var list = res.data || [];
      var h = '';
      if (!list.length) {
        h = isMobile
          ? '<div class="m-empty"><i class="bi bi-bell-slash"></i>暂无审批消息</div>'
          : '<div class="list-group-item text-muted text-center py-5"><i class="bi bi-bell-slash fs-2 d-block mb-2"></i>暂无消息</div>';
      } else {
        list.forEach(function (n) {
          var unread = parseInt(n.is_read || 0) === 0;
          var raw = n.url || (isMobile ? '/m/approvals' : '/approval');
          var url = raw;
          // 钉钉深链重映射（PC+移动端统一处理）：把 /dingtalk/entry?to=%2Fapproval%2Fxxx 解码为 /approval/xxx
          if (/\/dingtalk\/entry\?to=/.test(raw)) {
            var m = raw.match(/[?&]to=([^&]+)/);
            if (m) {
              var to = decodeURIComponent(m[1]);
              if (isMobile) {
                to = to.replace(/^\/approval\/(\d+)$/, '/m/approval/$1');
              }
              url = to;
            }
          }
          if (isMobile) {
            // 移动端由独立 notifications.php 渲染，此分支仅 /m/remind Tab3 回退用
            h += '<a class="m-item d-block text-decoration-none" style="padding-left:8px;padding-right:8px;border-bottom-color:#e0e0e0" href="' + esc(url) + '" data-id="' + n.id + '" data-unread="' + (unread ? '1' : '0') + '">'
              + '<div class="t" style="' + (unread ? 'font-weight:600' : '') + '">' + (unread ? '<span style="display:inline-block;width:6px;height:6px;border-radius:50%;background:var(--m-primary,#0d6efd);margin-right:6px;vertical-align:middle;margin-top:-2px"></span>' : '') + esc(n.title) + '</div>'
              + '<div class="s" style="' + (unread ? '' : 'color:var(--m-text-3)') + '">' + esc(n.content).replace(/\n/g, ' ') + '</div>'
              + '<div class="meta"><span style="font-size:11px;color:var(--m-text-3)">' + (n.created_at || '') + '</span>'
              + (unread ? '<span class="badge bg-danger ms-1" style="font-size:10px">未读</span>' : '<span class="ms-1" style="font-size:10px;color:var(--m-text-3)">已读</span>') + '</div>'
              + '</a>';
          } else {
            // PC 端卡片式风格——未读加粗+蓝色左边框+浅蓝底；已读灰色弱化；消息间距清晰
            var tag = TAG_LABEL[n.type] || '消息';
            var tagCls = TAG_CLASS[n.type] || 'bg-secondary bg-opacity-10 text-secondary';
            h += '<a class="list-group-item list-group-item-action ' + (unread ? 'fw-bold' : 'text-muted') + '" href="' + esc(url) + '" data-id="' + n.id + '" data-unread="' + (unread ? '1' : '0') + '"'
              + ' style="' + (unread ? 'border-left:3px solid #0b5ed7;background:#f5f9ff;' : 'opacity:.75;') + 'padding:14px 16px;margin-bottom:6px;border-radius:8px;"'
              + '>'
              + '<div class="d-flex justify-content-between align-items-start mb-1">'
                + '<div class="d-flex align-items-center gap-2">'
                  + '<span class="badge ' + tagCls + ' rounded-pill">' + tag + '</span>'
                  + '<span style="font-size:14px;' + (unread ? 'color:#1a1a2e;font-weight:600' : 'color:#6b7280;font-weight:400') + '">' + esc(n.title) + '</span>'
                + '</div>'
                + '<small class="text-muted" style="font-size:12px;white-space:nowrap;">' + (n.created_at || '') + '</small>'
              + '</div>'
              + '<div style="font-size:13px;' + (unread ? 'color:#4b5563' : 'color:#9ca3af') + ';line-height:1.5;padding-left:0;">' + esc(n.content).replace(/\n/g, '<br>') + '</div>'
              + '</a>';
          }
        });
      }
      document.getElementById('notifList').innerHTML = h;
      // 点击拦截：通过后端接口检查审批目标是否存在
      document.querySelectorAll('#notifList a[data-id]').forEach(function (a) {
        a.addEventListener('click', function (e) {
          if (a.getAttribute('data-verified') === '1') return; // 已验证，放行
          var href = a.getAttribute('href');
          var notifId = parseInt(a.dataset.id);

          // 未读消息：调用 mark-read + 更新角标（不立即修改 UI，跳走后重新加载自然为已读样式）
          if (a.dataset.unread === '1') {
            console.debug('[PC Notification] 标记已读', { notifId: notifId });
            $ajax('/ajax/notification/mark-read', { method: 'POST', body: 'id=' + notifId, loading: false, silent: true }).catch(function () {});
            updateBadge();
            a.dataset.unread = '0';
          }

          if (!href || href === '#' || href === 'javascript:void(0)') {
            e.preventDefault();
            return;
          }

          // 提取审批 ID（检查是否为审批类消息）
          var am = href.match(/\/(?:m\/)?approval\/(\d+)/);
          if (!am) return; // 非审批链接，不拦截，直接跳转

          e.preventDefault();
          // 调后端接口检查审批存在性（后端同时处理标记已读）
          console.debug('[PC Notification] 开始检查审批目标存在性', {
            notifId: notifId,
            href: href,
            apprId: am[1],
            timestamp: new Date().toISOString()
          });
          fetch('/ajax/notification/check-target?id=' + notifId, {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
          }).then(function (r) {
            console.debug('[PC Notification] 接口响应状态', {
              status: r.status,
              ok: r.ok,
              notifId: notifId,
              timestamp: new Date().toISOString()
            });
            return r.json();
          }).then(function (res) {
            console.debug('[PC Notification] 接口返回数据', {
              res: res,
              notifId: notifId,
              timestamp: new Date().toISOString()
            });
            if (res.code === 0 && res.data && res.data.exists) {
              // 审批存在，放行跳转
              a.setAttribute('data-verified', '1');
              console.debug('[PC Notification] 审批存在，放行跳转', {
                href: href,
                notifId: notifId,
                apprId: am[1]
              });
              window.location.href = href;
            } else {
              // 审批已删除：提示 + 弱化
              console.debug('[PC Notification] 审批不存在或已删除', {
                notifId: notifId,
                apprId: am[1],
                res: res
              });
              a.style.opacity = '.4';
              a.style.pointerEvents = 'none';
              showToast('原审批已删除', 'error');
            }
          }).catch(function (e) {
            // 网络异常，放行跳转（兜底）
            console.debug('[PC Notification] 接口调用异常，兜底放行', {
              error: e && e.message ? e.message : e,
              notifId: notifId,
              href: href,
              timestamp: new Date().toISOString()
            });
            a.setAttribute('data-verified', '1');
            window.location.href = href;
          });
        });
      });
      renderPager(res.count || 0);
      updateBadge();
    }).catch(function () {
      // 加载失败（此处 silent 不 toast，需在列表内自展示重试入口，避免"加载中"永驻）
      var box = document.getElementById('notifList');
      if (box) {
        box.innerHTML = '<div class="list-group-item text-center py-5 text-muted"><i class="bi bi-exclamation-triangle" style="font-size:2rem"></i><div class="mt-2">加载失败，点击重试</div><button type="button" class="btn btn-sm btn-outline-secondary mt-2" id="notifRetryBtn"><i class="bi bi-arrow-clockwise"></i> 重新加载</button></div>';
        var rb = document.getElementById('notifRetryBtn');
        if (rb) rb.addEventListener('click', function () { load(); });
      }
      var pgEl = document.getElementById('pg'); if (pgEl) pgEl.innerHTML = '';
    });
  }

  function renderPager(total) {
    var pg = document.getElementById('pg'); if (!pg) return;
    var tp = Math.ceil(total / pageSize);
    if (tp <= 1) { pg.innerHTML = ''; return; }
    if (isMobile) {
      var ph = '';
      for (var i = 1; i <= tp; i++) {
        ph += '<span style="display:inline-block;min-width:28px;height:28px;line-height:28px;text-align:center;margin:0 2px;border-radius:4px;font-size:12px;cursor:pointer;'
          + (i === page ? 'background:var(--m-primary,#0d6efd);color:#fff' : 'background:#f0f1f3;color:#555') + '" data-p="' + i + '">' + i + '</span>';
      }
      pg.innerHTML = '<div style="text-align:center;padding:8px 0">' + ph + '</div>';
    } else {
      var ph = '';
      for (var i = 1; i <= tp; i++) {
        ph += '<li class="page-item ' + (i === page ? 'active' : '') + '"><a class="page-link" href="#" data-p="' + i + '">' + i + '</a></li>';
      }
      pg.innerHTML = '<nav><ul class="pagination pagination-sm justify-content-end mb-0">' + ph + '</ul></nav>';
    }
    pg.querySelectorAll('[data-p]').forEach(function (a) {
      a.addEventListener('click', function (e) { e.preventDefault(); page = parseInt(this.dataset.p); load(); });
    });
  }

  var markAll = document.getElementById('markAll');
  if (markAll) {
    markAll.addEventListener('click', function () {
      $ajax('/ajax/notification/mark-all-read', { method: 'POST', loading: false }).then(function () { page = 1; load(); }).catch(function () {});
    });
  }

  function initNotif() { load(); }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initNotif);
  } else {
    initNotif();
  }
})();
