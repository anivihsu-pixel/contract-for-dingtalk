// +----------------------------------------------------------------------
// | 移动端共享 JS 模块（Phase 3：从各视图提取公共函数，消除 18 份重复副本）
// | 使用方式：在 _head.php 的 <head> 中 <script src="/static/js/mobile-common.js"></script>
// +----------------------------------------------------------------------

/**
 * 获取 CSRF Token（从 cookie 中读取，后端在登录后写入同名 cookie）
 * @returns {string} CSRF token 值，未找到返回空字符串
 */
function csrfToken() {
  var m = document.cookie.match(/(?:^|;\s*)csrf_token=([^;]+)/);
  return m ? decodeURIComponent(m[1]) : '';
}

/**
 * Toast 消息提示（自动消失）
 * @param {string} msg  消息文本
 * @param {string} [type]  可选类型：success / error / info / warning，渲染对应圆形彩色图标
 * @param {number} [duration=1800]  显示毫秒数
 */
function toast(msg, type, duration) {
  duration = duration || 1800;
  var el = document.getElementById('toast');
  if (!el) {
    el = document.createElement('div');
    el.id = 'toast';
    el.className = 'm-toast';
    document.body.appendChild(el);
  }
  // 类型徽标（可选）：圆形彩色图标，对齐系统移动端 UI（成功=绿勾等）
  var iconMap = { success: '✓', error: '✕', info: 'i', warning: '!' };
  if (type && iconMap[type]) {
    el.innerHTML = '<span class="m-toast-ic ' + type + '">' + iconMap[type] + '</span><span class="m-toast-txt"></span>';
    el.querySelector('.m-toast-txt').textContent = msg;   // 文本走 textContent，防 XSS
  } else {
    el.textContent = msg;
  }
  el.classList.add('show');
  setTimeout(function () { el.classList.remove('show'); }, duration);
}

/**
 * 全屏 loading 开关
 * @param {boolean} show  true=显示，false=隐藏
 */
function showLoading(show) {
  var el = document.getElementById('loading');
  if (!el) {
    el = document.createElement('div');
    el.id = 'loading';
    el.className = 'm-loading';
    el.innerHTML = '<div class="m-spinner"></div>';
    document.body.appendChild(el);
  }
  el.style.display = show ? 'flex' : 'none';
}
// 标记移动端 showLoading 已定义，阻止 _foot.php 加载的 app.js 覆盖为计数器版本
// （app.js 的 showLoading(text) 只增不减，会导致 loading 弹出后永不消失）
window.__mobileShowLoading = true;
// P0-3：app.js 的 $ajax 在 finally 调用 hideLoading()，但该函数被上方 __mobileShowLoading 分支排除未定义，
// 移动端不补定义会导致全屏 loading 遮罩永不消失（ReferenceError）；此处复用 boolean 版开关
function hideLoading(){ showLoading(false); }

/**
 * HTML 实体转义（防 XSS）
 * @param {*} s  任意值，会转为字符串后转义
 * @returns {string} 安全 HTML 字符串
 */
function esc(s) {
  var d = document.createElement('div');
  d.textContent = s == null ? '' : String(s);
  return d.innerHTML;
}

/**
 * 发送带 CSRF 的 AJAX POST 请求
 * @param {string} url  请求地址
 * @param {object|string} body  请求体（对象→URLSearchParams，字符串→直接发送）
 * @param {function} [onSuccess]  成功回调 fn(res)  其中 res 应含 {code:0, msg, data}
 * @param {function} [onError]  失败（网络/返回 code≠0）回调 fn(errMsg)
 */
function apiPost(url, body, onSuccess, onError) {
  if (typeof body === 'object' && body !== null && !(body instanceof URLSearchParams)) {
    body = new URLSearchParams(body).toString();
  }
  fetch(url, {
    method: 'POST',
    headers: {
      'X-Requested-With': 'XMLHttpRequest',
      'X-CSRF-TOKEN': csrfToken(),
      'Content-Type': 'application/x-www-form-urlencoded'
    },
    body: body
  })
    .then(function (r) { return r.json(); })
    .then(function (res) {
      if (res.code === 0) {
        if (onSuccess) onSuccess(res);
      } else {
        if (onError) onError(res.msg || '操作失败');
        else toast(res.msg || '操作失败');
      }
    })
    .catch(function () {
      if (onError) onError('网络异常');
      else toast('网络异常');
    });
}

/**
 * 确认弹窗 + 异步操作（显示 loading → 调用 apiPost → 成功后刷新/回调）
 * @param {string} msg    确认提示文案
 * @param {string} url    POST 地址
 * @param {object|string} body    请求体
 * @param {number} [reloadMs=600]  成功后延迟刷新毫秒数（0=不刷新）
 * @param {function} [cb]  成功回调（替代默认刷新）
 */
/**
 * 自定义确认弹窗（替代原生 confirm，避免移动端 webview 误关闭网页）
 * @param {string} msg  提示文案
 * @param {function} [onOk]  确定回调
 * @param {function} [onCancel]  取消回调
 * @returns {boolean} 是否成功弹出（busy 时返回 false，忽略重复调用）
 */
function mConfirm(msg, onOk, onCancel) {
  if (window._mModalBusy) return false;   // 防重复叠加：已有弹窗时不重复弹出
  window._mModalBusy = true;
  var mask = document.createElement('div');
  mask.className = 'm-modal-mask';
  mask.innerHTML =
    '<div class="m-modal-box">' +
      '<div class="m-modal-msg">' + esc(msg) + '</div>' +
      '<div class="m-modal-actions">' +
        '<button type="button" class="m-modal-cancel">取消</button>' +
        '<button type="button" class="m-modal-ok">确定</button>' +
      '</div>' +
    '</div>';
  function close() {
    window._mModalBusy = false;
    if (mask.parentNode) mask.parentNode.removeChild(mask);
  }
  // 点遮罩空白处 = 取消（不关闭网页，仅关弹窗）
  mask.addEventListener('click', function (e) {
    if (e.target === mask) { close(); if (onCancel) onCancel(); }
  });
  mask.querySelector('.m-modal-cancel').addEventListener('click', function () { close(); if (onCancel) onCancel(); });
  mask.querySelector('.m-modal-ok').addEventListener('click', function () { close(); if (onOk) onOk(); });
        document.body.appendChild(mask);
        return true;
    }

    /**
     * 自定义输入弹窗（替代原生 prompt，避免移动端 webview 兼容问题 / 宿主手势误关闭网页）
     * @param {string} msg           提示文案
     * @param {string} [defaultValue='']  输入框默认值
     * @param {function} [onOk]       确定回调 fn(value)
     * @param {function} [onCancel]   取消回调
     * @returns {boolean} 是否成功弹出（busy 时返回 false，忽略重复调用）
     */
    function mPrompt(msg, defaultValue, onOk, onCancel) {
        if (window._mModalBusy) return false;   // 防重复叠加：已有弹窗时不重复弹出
        window._mModalBusy = true;
        defaultValue = defaultValue == null ? '' : String(defaultValue);
        var mask = document.createElement('div');
        mask.className = 'm-modal-mask';
        mask.innerHTML =
            '<div class="m-modal-box">' +
            '<div class="m-modal-msg">' + esc(msg) + '</div>' +
            '<input type="text" class="m-modal-input" value="' + esc(defaultValue) + '">' +
            '<div class="m-modal-actions">' +
            '<button type="button" class="m-modal-cancel">取消</button>' +
            '<button type="button" class="m-modal-ok">确定</button>' +
            '</div>' +
            '</div>';
        var input = mask.querySelector('.m-modal-input');
        function close() {
            window._mModalBusy = false;
            if (mask.parentNode) mask.parentNode.removeChild(mask);
        }
        function ok() {
            var v = input.value.trim();
            close();
            if (onOk) onOk(v);
        }
        // 点遮罩空白处 = 取消（不关闭网页，仅关弹窗）
        mask.addEventListener('click', function (e) {
            if (e.target === mask) { close(); if (onCancel) onCancel(); }
        });
        mask.querySelector('.m-modal-cancel').addEventListener('click', function () { close(); if (onCancel) onCancel(); });
        mask.querySelector('.m-modal-ok').addEventListener('click', ok);
        // 支持回车提交
        input.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); ok(); } });
        document.body.appendChild(mask);
        setTimeout(function () { input.focus(); }, 50);
        return true;
    }

    /**
     * 确认弹窗 + 异步操作（显示 loading → 调用 apiPost → 成功后刷新/回调）
 * 修复：使用 mConfirm 自定义弹窗替代原生 confirm，避免移动端 webview 误关闭网页；
 *       原生 confirm 的「取消」在微信/iOS 等宿主中常被识别为关闭当前网页手势。
 * @param {string} msg    确认提示文案
 * @param {string} url    POST 地址
 * @param {object|string} body    请求体
 * @param {number} [reloadMs=600]  成功后延迟刷新毫秒数（0=不刷新）
 * @param {function} [cb]  成功回调（替代默认刷新）
 */
function confirmAndPost(msg, url, body, reloadMs, cb) {
  mConfirm(msg,
    function () {   // 确定：执行提交
      showLoading(true);
      apiPost(url, body,
        function (res) {
          showLoading(false);
          toast(res.msg || '操作成功');
          if (cb) { cb(res); }
          else if (reloadMs !== 0) { setTimeout(function () { location.reload(); }, reloadMs); }
        },
        function (err) {
          showLoading(false);
          toast(err);
        }
      );
    },
    function () { /* 取消：仅关闭弹窗，不关闭网页 */ }
  );
}

// ─── Sheet 面板工具 ───

/** 打开/关闭底部 sheet 面板
 * @param {string} overlayId  遮罩层 DOM ID
 * @param {boolean} [show]  强制指定显示状态，不传则 toggle
 */
function toggleSheet(overlayId, show) {
  var ov = document.getElementById(overlayId);
  if (!ov) return;
  if (typeof show === 'boolean') {
    ov.style.display = show ? 'block' : 'none';
  } else {
    ov.style.display = (ov.style.display === 'none' || !ov.style.display) ? 'block' : 'none';
  }
}

/** 金额格式化（千分位 + 2 位小数）
 * @param {number} v
 * @param {number} [decimals=0]  小数位数
 * @returns {string}
 */
function fmtMoney(v, decimals) {
  decimals = decimals === undefined ? 0 : decimals;
  return Number(v || 0).toLocaleString('zh-CN', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
}

/** 日期格式化（YYYY-MM-DD -> YYYY-MM-DD，容错） */
function fmtDate(d) {
  if (!d) return '';
  return String(d).substring(0, 10);
}

/** 展开折叠列表（v2.38.13 客户详情 / v2.38.14 供应商详情等复用）：
 *  折叠项 class 含 .m-lst-more 且用内联 display:none 控制（勿用 CSS 类隐藏——JS 清内联后会回退 CSS 仍隐藏）；
 *  恢复时按元素类型设置：m-row 是 flex，m-kv 是块级 */
function mShowMore(btn) {
  var box = btn.closest('.m-card-bd') || btn.parentNode;
  box.querySelectorAll('.m-lst-more').forEach(function(el) {
    el.style.display = el.classList.contains('m-kv') ? 'block' : 'flex';
  });
  btn.style.display = 'none';
}

// 发票申请「展示框 + 底部弹层」选择器（v2.51.4）：统一处理开票主体(company)与下拉(select)字段，
// 复用新建合同「我方主体」交互视觉（.m-pick-* 弹层）。由 InvoiceFormConfig::mobileRender 渲染
// data-inv-pick 结构；选中后回填同名 hidden、刷新展示名、触发 change；company 额外回调 mInvCompanyPicked(rate)。
window.initInvPickers = function (scope) {
  scope = scope || document;
  var boxes = scope.querySelectorAll('[data-inv-pick]');
  if (!boxes.length) return;

  var mask = document.createElement('div');
  mask.className = 'm-pick-mask';
  var sheet = document.createElement('div');
  sheet.className = 'm-pick-sheet';
  mask.appendChild(sheet);
  document.body.appendChild(mask);

  function parseOptions(json) {
    try { var o = JSON.parse(json); return Array.isArray(o) ? o : []; } catch (e) { return []; }
  }
  function show(box) {
    var opts = parseOptions(box.getAttribute('data-options') || '[]');
    var kind = box.getAttribute('data-inv-pick');
    var title = kind === 'company' ? '选择开票主体' : '请选择';
    var h = '<div class="m-pick-hd">' + title + '</div>';
    opts.forEach(function (item) {
      var defTxt = item.default ? '<span class="m-pick-def">默认</span>' : '';
      h += '<div class="m-pick-item" data-value="' + esc(String(item.value)) + '"><span>' + esc(item.label) + '</span>' + defTxt + '</div>';
    });
    h += '<button type="button" class="m-pick-cancel">取消</button>';
    sheet.innerHTML = h;
    mask._box = box;
    mask.classList.add('show');
    sheet.querySelectorAll('.m-pick-item').forEach(function (it) {
      it.addEventListener('click', function () { pick(box, it.getAttribute('data-value')); });
    });
    sheet.querySelector('.m-pick-cancel').addEventListener('click', function () { mask.classList.remove('show'); });
  }
  function pick(box, value) {
    var opts = parseOptions(box.getAttribute('data-options') || '[]');
    var item = null;
    for (var i = 0; i < opts.length; i++) { if (String(opts[i].value) === String(value)) { item = opts[i]; break; } }
    if (!item) return;
    mask.classList.remove('show');
    var name = box.getAttribute('data-pick-name');
    var hid = scope.querySelector('[name="' + name + '"]');
    if (hid) hid.value = String(item.value);
    var nameEl = box.querySelector('.m-pick-name');
    if (nameEl) nameEl.textContent = item.label;
    if (hid) hid.dispatchEvent(new Event('change', { bubbles: true }));
    if (box.getAttribute('data-inv-pick') === 'company' && typeof window.mInvCompanyPicked === 'function') window.mInvCompanyPicked(item.rate);
  }
  boxes.forEach(function (box) {
    box.addEventListener('click', function () { show(box); });
  });
  mask.addEventListener('click', function (e) { if (e.target === mask) mask.classList.remove('show'); });

  // 默认带出：company 选默认主体（is_default=1，无则第一个）；select 已有服务端回显则不动
  boxes.forEach(function (box) {
    if (box.getAttribute('data-inv-pick') !== 'company') return;
    var name = box.getAttribute('data-pick-name');
    var hid = scope.querySelector('[name="' + name + '"]');
    if (!hid || (hid.value && hid.value !== '0')) return;
    var opts = parseOptions(box.getAttribute('data-options') || '[]');
    var def = null;
    for (var i = 0; i < opts.length; i++) { if (opts[i].default) { def = opts[i]; break; } }
    if (!def && opts.length) def = opts[0];
    if (def) pick(box, def.value);
  });
};

// v2.51.4：关联合同 → 自动带出乙方抬头/税号（申请开票；用户仍可通过「开票客户」选择器改选覆盖）
window.mInvFillByContract = function (d) {
  if (!d || !d.id) return;
  var f = document.getElementById('mInvFields');
  if (!f) return;
  var t = f.querySelector('input[name="invoice_title"]');
  var n = f.querySelector('input[name="tax_no"]');
  if (t) t.value = d.party_b_name || '';
  if (n) n.value = d.party_b_credit_code || '';
};

// 移动端表单本地草稿：仅保存普通字段，不保存密码、文件或令牌；成功提交后页面可显式清除。
window.mobileFormDraft = function(form, key) {
  if (!form || !key || !window.localStorage) return { clear: function(){} };
  var storageKey = 'form_draft:' + key;
  try {
    var saved = JSON.parse(localStorage.getItem(storageKey) || '{}');
    Object.keys(saved).forEach(function(name){var el=form.elements[name];if(el && !el.value && el.type!=='password' && el.type!=='file')el.value=saved[name];});
  } catch(e) {}
  var timer;
  form.addEventListener('input', function(){clearTimeout(timer);timer=setTimeout(function(){var data={};Array.prototype.forEach.call(form.elements,function(el){if(el.name&&el.type!=='password'&&el.type!=='file'&&el.type!=='hidden')data[el.name]=el.value;});try{localStorage.setItem(storageKey,JSON.stringify(data));}catch(e){}},300);});
  return {clear:function(){try{localStorage.removeItem(storageKey);}catch(e){}}};
};

// 单次页面生命周期幂等键，用于弱网安全重试；后端按用户+接口+键去重。
window.mobileIdempotencyKey = function(scope){var k='idem:'+scope;try{var v=sessionStorage.getItem(k);if(v)return v;v=Date.now().toString(36)+Math.random().toString(36).slice(2);sessionStorage.setItem(k,v);return v;}catch(e){return Date.now().toString(36);}};
