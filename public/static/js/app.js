/**
 * 合同管理系统 — 共享 JavaScript
 */

// 读取 cookie（CSRF token 下发在名为 csrf_token 的 cookie 中）
window.getCookie = function(name) {
    var m = document.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]*)'));
    return m ? decodeURIComponent(m[1]) : '';
};

// 全局 fetch 包装：为写操作自动附加 X-CSRF-TOKEN 头（避免逐个接口改漏）
(function() {
    var _fetch = window.fetch;
    if (!_fetch) return;
    window.fetch = function(url, opts) {
        opts = opts || {};
        var method = (opts.method || 'GET').toUpperCase();
        if (['POST', 'PUT', 'DELETE', 'PATCH'].indexOf(method) !== -1) {
            opts.headers = opts.headers || {};
            var t = window.getCookie('csrf_token');
            if (t && !opts.headers['X-CSRF-TOKEN'] && !opts.headers['x-csrf-token']) {
                opts.headers['X-CSRF-TOKEN'] = t;
            }
            if (!opts.headers['X-Requested-With']) {
                opts.headers['X-Requested-With'] = 'XMLHttpRequest';
            }
        }
        return _fetch.apply(this, arguments);
    };
})();

// 全局 AJAX 封装（含错误兜底：非 JSON 响应 / 网络异常统一 toast，避免静默失败）
window.$ajax = function(url, options) {
    options = options || {};
    var headers = options.headers || {};
    headers['X-Requested-With'] = 'XMLHttpRequest';

    // JWT Token (钉钉 WebView)
    var token = localStorage.getItem('token');
    if (token) {
        headers['Authorization'] = 'Bearer ' + token;
    }

    var body = options.body;
    if (body && typeof body === 'object' && !(body instanceof FormData) && !(body instanceof URLSearchParams)) {
        body = JSON.stringify(body);
        headers['Content-Type'] = 'application/json';
    }

    if (options.loading !== false) showLoading(options.loadingText);
    return fetch(url, {
        method: options.method || 'GET',
        headers: headers,
        body: body || undefined
    }).then(function(r) {
        if (r.status === 401) {
            localStorage.removeItem('token');
            location.href = '/login';
            return Promise.reject(new Error('未登录'));
        }
        var ct = r.headers.get('Content-Type') || '';
        // 成功且为 JSON：正常返回解析后的对象
        if (r.ok && ct.indexOf('application/json') !== -1) {
            return r.json();
        }
        // 非 2xx：若为 JSON 响应则透出后端业务 msg；否则按 HTTP 状态给通用提示（杜绝静默失败）
        return r.text().then(function(text) {
            var msg = '';
            if (ct.indexOf('application/json') !== -1) {
                try { var j = JSON.parse(text); if (j && j.msg) msg = j.msg; } catch (e) {}
            }
            if (!msg) {
                if (r.status === 403) msg = '权限不足，请联系管理员';
                else if (r.status === 404) msg = '请求的资源不存在';
                else if (r.status === 500) msg = '服务器内部错误，请稍后重试';
                else msg = '操作失败（HTTP ' + r.status + '）';
            }
            if (options.silent !== true) showToast(msg, 'error');
            return Promise.reject(new Error(msg));
        });
    }).catch(function(err) {
        // 网络错误 / 解析失败（断网、弱网）
        if (err && err.message && err.message.indexOf('Failed to fetch') !== -1) {
            if (options.silent !== true) showToast('网络异常，请检查网络连接', 'error');
        }
        throw err;
    }).finally(function() {
        if (options.loading !== false) hideLoading();
    });
};

// Toast 提示
window.showToast = function(msg, type) {
    type = type || 'info';
    var container = document.querySelector('.toast-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'toast-container';
        document.body.appendChild(container);
    }

    var colors = { success: '#198754', error: '#dc3545', info: '#0b5ed7', warning: '#ffc107' };
    var el = document.createElement('div');
    el.style.cssText = 'background:' + (colors[type] || colors.info) + ';color:#fff;padding:10px 20px;border-radius:8px;margin-bottom:8px;box-shadow:0 4px 12px rgba(0,0,0,.2);font-size:14px;animation:fadeIn .3s ease;';
    el.textContent = msg;
    container.appendChild(el);
    setTimeout(function() { el.remove(); }, 3000);
};

// 全局加载遮罩（长任务反馈，避免重复提交 / 无响应焦虑）
// 移动端页面（mobile-common.js）已定义 boolean 版 showLoading，不覆盖
if (!window.__mobileShowLoading) {
window._loadingCount = 0;
window.showLoading = function(text) {
    window._loadingCount++;
    if (window._loadingCount > 1) {
        var ex = document.getElementById('globalLoading');
        if (ex) ex.querySelector('.global-loading-text').textContent = text || '处理中…';
        return;
    }
    var el = document.getElementById('globalLoading');
    if (!el) {
        el = document.createElement('div');
        el.id = 'globalLoading';
        el.className = 'global-loading-mask';
        el.innerHTML = '<div class="global-loading-box"><div class="spinner-border text-light" role="status"></div><div class="global-loading-text">处理中…</div></div>';
        document.body.appendChild(el);
    }
    el.querySelector('.global-loading-text').textContent = text || '处理中…';
    el.style.display = 'flex';
};
window.hideLoading = function() {
    window._loadingCount = Math.max(0, window._loadingCount - 1);
    if (window._loadingCount > 0) return;
    var el = document.getElementById('globalLoading');
    if (el) el.style.display = 'none';
};
} // end if (!window.__mobileShowLoading)

// 空状态行动点（带新建 CTA，按权限显隐）
window.emptyState = function(opts) {
    opts = opts || {};
    // 仅显式 canCreate=true 显示新建按钮、显式 false 显示「无新建权限」；
    // 未传（纯信息空态，如归档/回收站/审批/财务列表）不显示任何行动点——
    // 旧实现把「未传」当 falsy 落入「无新建权限」分支，admin 在无新建入口的
    // 页面空态也会误显示「无新建权限，请联系管理员」（v2.44.1 后测试发现）。
    var btn = '';
    if (opts.canCreate) {
        btn = '<a href="' + opts.href + '" class="btn btn-primary btn-sm mt-2"><i class="bi ' + (opts.iconBtn || 'bi-plus-lg') + '"></i> ' + opts.btn + '</a>';
    } else if (opts.canCreate === false) {
        btn = '<div class="small text-muted mt-2">无新建权限，请联系管理员</div>';
    }
    return '<tr><td colspan="' + (opts.colspan || 8) + '" class="text-center py-5">'
        + '<div class="text-muted">'
        + '<i class="bi ' + (opts.icon || 'bi-inbox') + '" style="font-size:2rem"></i>'
        + '<div class="mt-2 fw-semibold">' + (opts.title || '暂无数据') + '</div>'
        + '<div class="small">' + (opts.desc || '') + '</div>'
        + btn
        + '</div></td></tr>';
};

// 全局 HTML 转义 helper（v2.35.x：原分散在 contract/detail、admin/index、finance/index 三处视图内重复定义，
// 现统一下沉至此，避免多份重复定义；视图层输出用户可填内容建议统一经此转义，防御存储型 XSS）
window.esc = function(s) {
    return String(s == null ? '' : s)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
};

// 新手引导（首次访问仪表盘自动弹出，可反复从侧栏「新手引导」进入）
window.showGuide = function() {
    if (document.getElementById('guideModal')) return;
    var html = ''
        + '<div class="modal fade" id="guideModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">'
        + '<div class="modal-header"><h5 class="modal-title"><i class="bi bi-compass"></i> 新手引导 · 5 步上手</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>'
        + '<div class="modal-body"><div class="row g-3">'
        + '<div class="col-6"><div class="border rounded p-3 h-100"><div class="fw-semibold"><i class="bi bi-file-text text-primary"></i> 1. 创建合同</div><div class="small text-muted mt-1">填写核心条款、上传附件、选择模板，提交后进入审批流。</div></div></div>'
        + '<div class="col-6"><div class="border rounded p-3 h-100"><div class="fw-semibold"><i class="bi bi-check2-square text-success"></i> 2. 审批流转</div><div class="small text-muted mt-1">在「合同审批」中处理待办，支持同意/驳回/转交/撤回。</div></div></div>'
        + '<div class="col-6"><div class="border rounded p-3 h-100"><div class="fw-semibold"><i class="bi bi-cash-coin text-warning"></i> 3. 回款与发票</div><div class="small text-muted mt-1">合同详情页登记回款计划与开票申请，金额受合同总额约束。</div></div></div>'
        + '<div class="col-6"><div class="border rounded p-3 h-100"><div class="fw-semibold"><i class="bi bi-bell text-danger"></i> 4. 到期提醒</div><div class="small text-muted mt-1">仪表盘「今日提醒」与侧栏角标汇总合同到期/回款逾期。</div></div></div>'
        + '<div class="col-12"><div class="border rounded p-3"><div class="fw-semibold"><i class="bi bi-shield-lock text-info"></i> 5. 权限与安全</div><div class="small text-muted mt-1">登录后请修改密码；数据按角色范围（本人/部门/全部）可见；操作全程留痕于「审计中心」。</div></div></div>'
        + '</div></div>'
        + '<div class="modal-footer"><a href="/audit" class="btn btn-outline-secondary btn-sm">查看审计中心</a><button class="btn btn-primary" data-bs-dismiss="modal">开始使用</button></div>'
        + '</div></div></div>';
    document.body.insertAdjacentHTML('beforeend', html);
    new bootstrap.Modal('#guideModal').show();
    try { localStorage.setItem('cm_guide_seen', '1'); } catch (e) {}
};

// 首次访问仪表盘自动弹出新手引导（2026-07-25：受系统配置 guide_enabled 控制，window.guideEnabled 由 sidebar 注入；默认关闭）
if (window.guideEnabled && location.pathname === '/dashboard' && !localStorage.getItem('cm_guide_seen')) {
    setTimeout(function() { if (typeof showGuide === 'function') showGuide(); }, 800);
}

// 修改密码（Modal 表单，替代原生 prompt/alert）
window.changePassword = function() {
    var html = ''
        + '<div class="modal fade" id="pwdModal" tabindex="-1">'
        + '<div class="modal-dialog modal-sm"><div class="modal-content">'
        + '<div class="modal-header"><h5 class="modal-title">修改密码</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>'
        + '<div class="modal-body">'
        + '<div class="mb-2"><label class="form-label" for="pwdOld">旧密码</label><input type="password" id="pwdOld" class="form-control"></div>'
        + '<div class="mb-2"><label class="form-label" for="pwdNew">新密码（至少8位）</label><input type="password" id="pwdNew" class="form-control"></div>'
        + '</div>'
        + '<div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">取消</button>'
        + '<button class="btn btn-primary" onclick="submitPasswordChange()">确定</button></div>'
        + '</div></div></div>';
    var old = document.getElementById('pwdModal');
    if (old) old.remove();
    document.body.insertAdjacentHTML('beforeend', html);
    new bootstrap.Modal('#pwdModal').show();
};

window.submitPasswordChange = function() {
    var oldPwd = document.getElementById('pwdOld').value;
    var newPwd = document.getElementById('pwdNew').value;
    if (!oldPwd) { showToast('请输入旧密码', 'warning'); return; }
    if (!newPwd || newPwd.length < 8) { showToast('新密码至少8位', 'warning'); return; }
    $ajax('/ajax/admin/change-password', {
        method: 'POST',
        body: new URLSearchParams({ old_password: oldPwd, new_password: newPwd })
    }).then(function(res) {
        showToast(res.msg || '操作完成', res.code === 0 ? 'success' : 'error');
        if (res.code === 0) {
            bootstrap.Modal.getInstance('#pwdModal').hide();
            setTimeout(function(){ doLogout(); }, 1000);
        }
    });
};

// P2-12【S-A3】登出改 POST（GET 登出可被图片/预加载触发）：走全局 fetch 包装自动携带 CSRF 头
window.doLogout = function() {
    fetch('/logout', { method: 'POST' })
        .catch(function(){})
        .then(function(){ location.href = '/login'; });
};

// 钉钉 JSAPI 初始化 (在钉钉 WebView 中自动调用)
(function() {
    if (!/DingTalk/i.test(navigator.userAgent)) return;

    var url = location.href.split('#')[0];
    fetch('/dingtalk/jsapi-config?url=' + encodeURIComponent(url))
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (!res.data) return;
            var d = res.data;
            if (typeof dd !== 'undefined') {
                dd.config({
                    agentId: d.agentId,
                    corpId: d.corpId,
                    timeStamp: d.timestamp,
                    nonceStr: d.nonceStr,
                    signature: d.signature,
                    jsApiList: [
                        'runtime.permission.requestAuthCode',
                        'biz.contact.choose',
                        'biz.navigation.setTitle',
                        'device.notification.alert'
                    ]
                });
                dd.ready(function() {
                    dd.biz.navigation.setTitle({title: '合同管理系统'});
                });
                dd.error(function(err) {
                    console.warn('DingTalk JSAPI error:', err);
                });
            }
        });
})();

// 侧边栏子菜单折叠切换
window.toggleSub = function(id, el) {
    var t = document.getElementById(id);
    if (t) {
        t.classList.toggle('show');
        el.setAttribute('aria-expanded', t.classList.contains('show'));
    }
};

// CSS animation
var style = document.createElement('style');
style.textContent = '@keyframes fadeIn { from { opacity:0;transform:translateY(-10px); } to { opacity:1;transform:translateY(0); } }'
    + '.global-loading-mask{position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:9999;display:none;align-items:center;justify-content:center;}'
    + '.global-loading-box{background:rgba(0,0,0,.6);padding:20px 28px;border-radius:12px;color:#fff;text-align:center;}'
    + '.global-loading-text{margin-top:10px;font-size:14px;}';
document.head.appendChild(style);

// ===== 通用确认/输入弹窗（P2-1：替代原生 confirm/prompt，对齐移动端 m-sheet/m-modal 质感） =====
// pcConfirm({title, message, okText, danger}) -> Promise<boolean>
// pcPrompt({title, placeholder, value, okText}) -> Promise<string|null>（取消/关闭为 null）
window.__pcDialogEl = null;
function pcDialog() {
    if (window.__pcDialogEl) return window.__pcDialogEl;
    var el = document.createElement('div');
    el.innerHTML = '<div class="modal fade" id="pcDialog" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content">'
        + '<div class="modal-header"><h5 class="modal-title" id="pcDialogTitle"></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>'
        + '<div class="modal-body" id="pcDialogBody"></div>'
        + '<div class="modal-footer"><button type="button" class="btn btn-secondary" id="pcDialogCancel">取消</button>'
        + '<button type="button" class="btn btn-primary" id="pcDialogOk">确定</button></div>'
        + '</div></div></div>';
    document.body.appendChild(el);
    window.__pcDialogEl = el;
    return el;
}
window.pcConfirm = function(opts) {
    opts = opts || {};
    var el = pcDialog();
    el.querySelector('#pcDialogTitle').textContent = opts.title || '确认操作';
    el.querySelector('#pcDialogBody').innerHTML = '<div class="py-2">' + window.esc(opts.message || '确定继续？') + '</div>';
    var okBtn = el.querySelector('#pcDialogOk');
    okBtn.textContent = opts.okText || '确定';
    okBtn.className = 'btn ' + (opts.danger ? 'btn-danger' : 'btn-primary');
    return new Promise(function(resolve) {
        var modal = new bootstrap.Modal(el.querySelector('#pcDialog'));
        modal.show();
        var done = function(v) { modal.hide(); resolve(v); };
        okBtn.onclick = function() { done(true); };
        el.querySelector('#pcDialogCancel').onclick = function() { done(false); };
        el.querySelector('#pcDialog .btn-close').onclick = function() { done(false); };
    });
};
window.pcPrompt = function(opts) {
    opts = opts || {};
    var el = pcDialog();
    el.querySelector('#pcDialogTitle').textContent = opts.title || '请输入';
    el.querySelector('#pcDialogBody').innerHTML = '<div class="py-2"><input type="text" class="form-control" id="pcDialogInput" placeholder="'
        + (opts.placeholder ? window.esc(opts.placeholder) : '') + '"></div>';
    var okBtn = el.querySelector('#pcDialogOk');
    okBtn.textContent = opts.okText || '确定';
    okBtn.className = 'btn btn-primary';
    return new Promise(function(resolve) {
        var modal = new bootstrap.Modal(el.querySelector('#pcDialog'));
        modal.show();
        var input = el.querySelector('#pcDialogInput');
        if (opts.value) input.value = opts.value;
        setTimeout(function() { input.focus(); }, 80);
        var done = function(v) { modal.hide(); resolve(v); };
        okBtn.onclick = function() { done(input.value.trim()); };
        el.querySelector('#pcDialogCancel').onclick = function() { done(null); };
        el.querySelector('#pcDialog .btn-close').onclick = function() { done(null); };
        input.addEventListener('keydown', function(e) { if (e.key === 'Enter') done(input.value.trim()); });
    });
};

/**
 * AJAX 局部刷新（事件委托模式）
 * 在稳定容器上委托点击事件，fetch HTML 后替换容器内容。
 * 内置防重复点击锁和加载状态反馈，刷新后自动执行内联 script（Chart.js 等）。
 *
 * @param {string|Element} container  稳定容器（事件委托目标，内容被替换）
 * @param {string} selector           点击目标匹配选择器（在容器内匹配）
 * @param {function} urlBuilder       接收被点击元素，返回请求 URL
 * @param {object} [opts]             { onAfter: function(box), loadingText: string }
 */
window.bindPartialRefresh = function(container, selector, urlBuilder, opts) {
    opts = opts || {};
    var box = typeof container === 'string' ? document.querySelector(container) : container;
    if (!box) return;
    var loading = false;

    // 创建加载遮罩元素（绝对定位覆盖在容器上方）
    var overlay = document.createElement('div');
    overlay.className = 'partial-refresh-overlay';
    overlay.style.cssText = 'position:absolute;inset:0;display:none;align-items:center;justify-content:center;background:rgba(255,255,255,.7);z-index:10;border-radius:inherit';
    overlay.innerHTML = '<div class="d-flex align-items-center gap-2 text-primary"><span class="spinner-border spinner-border-sm"></span><span class="small fw-semibold">' + (opts.loadingText || '加载中…') + '</span></div>';
    // 容器需 relative 定位以承载遮罩
    if (getComputedStyle(box).position === 'static') box.style.position = 'relative';
    box.appendChild(overlay);

    function showOverlay() { overlay.style.display = 'flex'; }
    function hideOverlay() { overlay.style.display = 'none'; }

    box.addEventListener('click', function(e) {
        var el = e.target.closest(selector);
        if (!el) return;
        e.preventDefault();
        if (loading) return;
        loading = true;
        // 加载状态：被点击元素变半透明 + 遮罩 spinner
        el.style.opacity = '0.5';
        showOverlay();
        fetch(urlBuilder(el), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function(r) { return r.text(); })
            .then(function(html) {
                box.innerHTML = html;
                // innerHTML 不会执行 script，手动重建以执行（Chart.js 等需重新初始化）
                box.querySelectorAll('script').forEach(function(old) {
                    var s = document.createElement('script');
                    if (old.src) { s.src = old.src; } else { s.textContent = old.textContent; }
                    old.parentNode.replaceChild(s, old);
                });
                // 重新添加遮罩（innerHTML 替换后遮罩被清除）
                box.appendChild(overlay);
                if (typeof opts.onAfter === 'function') opts.onAfter(box);
            })
            .catch(function() {
                if (window.showToast) showToast('加载失败，请重试', 'error');
                el.style.opacity = '';
            })
            .finally(function() {
                loading = false;
                hideOverlay();
            });
    });
};
