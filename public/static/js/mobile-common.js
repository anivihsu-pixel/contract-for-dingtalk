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

// ─── 键盘遮挡处理（v2.44.4）───
// 移动端新建/编辑表单底部固定提交栏（.m-submitbar）：软键盘弹出时 fixed bottom 元素被浏览器
// 顶到键盘上方，悬在正在编辑的字段上造成遮挡。输入控件聚焦即隐藏提交栏，失焦且焦点已离开
// 输入控件后恢复（延迟 0ms 判断 activeElement，避免输入框 A→B 切换时闪烁）。
// 脚本在 <head> 加载时 DOM 未就绪，故在事件回调内动态查询 .m-submitbar。
(function () {
  var isInput = function (el) {
    return el && (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA' || el.tagName === 'SELECT');
  };
  var getSb = function () { return document.querySelector('.m-submitbar'); };
  document.addEventListener('focusin', function (e) {
    if (!isInput(e.target)) return;
    var sb = getSb(); if (sb) sb.style.display = 'none';
  });
  document.addEventListener('focusout', function (e) {
    if (!isInput(e.target)) return;
    setTimeout(function () {
      if (!isInput(document.activeElement)) {
        var sb = getSb(); if (sb) sb.style.display = '';
      }
    }, 0);
  });
})();
