// +----------------------------------------------------------------------
// | 移动端附件预览 Lightbox（附件预览大优化）
// | 依赖：mobile-common.js（esc / toast / Attachments.attachments.js 已先行加载时使用）
// | 使用：<script src="/static/js/mobile/lightbox.js"></script>
// | 需要页面中存在 #lbOverlay / #lbTitle / #lbBody 元素（没有则自动创建）
// +----------------------------------------------------------------------

(function () {
  'use strict';

  var IMG_EXTS = window.Attachments ? window.Attachments.IMG_EXTS : ['JPG', 'JPEG', 'PNG', 'GIF', 'WEBP', 'BMP', 'SVG'];
  var PDF_EXTS = ['PDF'];

  // 初始化：确保 DOM 元素存在（附件预览优化：消除各页面重复写 #lbOverlay 结构）
  function ensureDom() {
    var overlay = document.getElementById('lbOverlay');
    // 样式注入放在最前：无论 overlay 是自动创建还是页面手写，都保证增强样式可用（幂等）
    if (!document.getElementById('lb-overlay-css')) {
      var style = document.createElement('style');
      style.id = 'lb-overlay-css';
      style.innerHTML = [
        '.lb-bar-actions { display:flex; align-items:center; gap:8px; }',
        '.lb-btn { background:rgba(255,255,255,.15); border:1px solid rgba(255,255,255,.25); color:#fff;',
        '  font-size:17px; padding:4px 10px; border-radius:8px; cursor:pointer; }',
        '.lb-btn:active { background:rgba(255,255,255,.3); }',
        '.lb-body { position:relative; }',
        '.lb-loading { text-align:center; color:#ccc; padding:40px 20px; }',
        '.lb-spinner { width:36px; height:36px; border:3px solid rgba(255,255,255,.2); border-top-color:#fff;',
        '  border-radius:50%; animation:lb-spin 0.9s linear infinite; margin:0 auto 16px; }',
        '@keyframes lb-spin { to { transform:rotate(360deg); } }',
        '.lb-error { text-align:center; color:#ccc; max-width:300px; margin:0 auto; padding:24px 16px; }',
        '.lb-error .ic { font-size:48px; color:#e67e22; margin-bottom:12px; }',
        '.lb-error p { margin:8px 0; line-height:1.6; font-size:14px; }',
        '.lb-error a { color:#ffb952; text-decoration:underline; }',
        '.lb-body img { user-select:none; -webkit-user-drag:none; touch-action:pan-y; transition:opacity .2s; opacity:0; }',
        '.lb-body img.loaded { opacity:1; }',
      ].join('\n');
      document.head.appendChild(style);
    }
    if (overlay) {
      // 页面已手写 #lbOverlay：兼容补齐下载按钮（旧结构仅 lbTitle + lb-close，无下载入口）
      if (!document.getElementById('lbDownloadBtn')) {
        var bar = overlay.querySelector('.lb-bar');
        if (bar) {
          var dlBtn = document.createElement('button');
          dlBtn.id = 'lbDownloadBtn';
          dlBtn.className = 'lb-btn';
          dlBtn.title = '下载';
          dlBtn.style.display = 'none';
          dlBtn.innerHTML = '<i class="bi bi-download"></i>';
          dlBtn.addEventListener('click', function () {
            if (window._lbDownloadInfo) {
              downloadAttachment(window._lbDownloadInfo.url, window._lbDownloadInfo.name);
            }
          });
          bar.appendChild(dlBtn);
        }
      }
      return;
    }
    overlay = document.createElement('div');
    overlay.id = 'lbOverlay';
    overlay.className = 'lb-overlay';
    overlay.innerHTML =
      '<div class="lb-bar">' +
        '<span id="lbTitle">附件预览</span>' +
        '<div class="lb-bar-actions">' +
          '<button class="lb-btn" id="lbDownloadBtn" title="下载" style="display:none">' +
            '<i class="bi bi-download"></i>' +
          '</button>' +
          '<button class="lb-close" id="lbCloseBtn">&times;</button>' +
        '</div>' +
      '</div>' +
      '<div class="lb-body" id="lbBody"></div>';
    document.body.appendChild(overlay);
    document.getElementById('lbCloseBtn').addEventListener('click', closePreview);
    overlay.addEventListener('click', function (e) {
      if (e.target === overlay) closePreview();
    });
    document.getElementById('lbDownloadBtn').addEventListener('click', function () {
      if (window._lbDownloadInfo) {
        downloadAttachment(window._lbDownloadInfo.url, window._lbDownloadInfo.name);
      }
    });
  }

  // 统一的下载入口（Attachments 已加载则调用，否则兜底）
  function downloadAttachment(url, name) {
    if (window.Attachments && typeof window.Attachments.download === 'function') {
      window.Attachments.download(url, name);
    } else {
      window.open(url, '_blank', 'noopener');
    }
  }

  // 图片预览（状态机：loading -> loaded/error）
  // v2.43.7 修复：img.src 统一走带令牌的 /preview 代理（与下载/office 预览同链路）——
  // 原直连 /uploads 在无会话（被甩外部浏览器 / WebView 无 Cookie）时 401 → 弹窗空白，观感「预览没反应」
  function renderImage(url, name, token) {
    var body = document.getElementById('lbBody');
    body.innerHTML = '';
    var imgUrl = window.Attachments ? window.Attachments.normalizeUrl(url, token) : url;
    // loading 状态
    var loading = document.createElement('div');
    loading.className = 'lb-loading';
    loading.id = 'lbImgLoading';
    loading.innerHTML = '<div class="lb-spinner"></div><div style="font-size:13px">图片加载中…</div>';
    body.appendChild(loading);

    var timeoutTimer = setTimeout(function () {
      if (document.getElementById('lbImgLoading')) {
        // 10s 超时
        showImageError(url, name, '加载超时，请检查网络后重试', token);
      }
    }, 10000);

    var img = new Image();
    img.alt = name;
    img.addEventListener('load', function () {
      clearTimeout(timeoutTimer);
      body.innerHTML = '';
      img.classList.add('loaded');
      body.appendChild(img);
    });
    img.addEventListener('error', function () {
      clearTimeout(timeoutTimer);
      showImageError(url, name, '图片加载失败，可能已被删除或链接无效', token);
    });
    img.src = imgUrl;
  }

  function showImageError(url, name, msg, token) {
    var body = document.getElementById('lbBody');
    if (!body) return;
    var imgUrl = window.Attachments ? window.Attachments.normalizeUrl(url, token) : url;
    body.innerHTML =
      '<div class="lb-error">' +
        '<div class="ic"><i class="bi bi-exclamation-triangle-fill"></i></div>' +
        '<p>' + (msg || '图片加载失败') + '</p>' +
        '<p style="margin-top:16px;">' +
          '<a href="' + esc(imgUrl) + '" target="_blank" rel="noopener" style="margin-right:12px">新窗口打开</a>' +
          '<a href="javascript:;" onclick="window.Attachments && window.Attachments.download(&quot;' + esc(imgUrl) + '&quot;,&quot;' + esc(name) + '&quot;)">下载原图</a>' +
        '</p>' +
      '</div>';
  }

  // Office/其他：iframe 内嵌预览 + 失败兜底
  function renderOfficePreview(url, name, token) {
    var body = document.getElementById('lbBody');
    body.innerHTML = '';
    // loading 状态
    var loading = document.createElement('div');
    loading.className = 'lb-loading';
    loading.id = 'lbOfficeLoading';
    loading.innerHTML = '<div class="lb-spinner"></div><div style="font-size:13px">文档加载中…</div><div style="font-size:11px;color:#888;margin-top:6px">若浏览器不支持预览，将自动给出下载入口</div>';
    body.appendChild(loading);

    var timeoutTimer = setTimeout(function () {
      if (document.getElementById('lbOfficeLoading')) {
        showOfficeFallback(url, name, token);
      }
    }, 12000);

    var previewUrl = window.Attachments
      ? window.Attachments.normalizeUrl(url, token)
      : ('/preview?p=' + encodeURIComponent(url) + (token ? '&t=' + encodeURIComponent(token) : ''));

    var iframe = document.createElement('iframe');
    iframe.src = previewUrl;
    iframe.addEventListener('load', function () {
      clearTimeout(timeoutTimer);
      var loader = document.getElementById('lbOfficeLoading');
      if (loader) loader.remove();
      // 成功加载不自动移除 loading，避免闪烁——但如果是空响应还应兜底
      try {
        // 跨域无法读取 iframe.contentDocument，只能信任 load 事件；兜底超时已处理
      } catch (e) { /* ignore */ }
    });
    iframe.addEventListener('error', function () {
      clearTimeout(timeoutTimer);
      showOfficeFallback(url, name, token);
    });
    body.appendChild(iframe);
  }

  function showOfficeFallback(url, name, token) {
    var body = document.getElementById('lbBody');
    if (!body) return;
    var dlUrl = window.Attachments ? window.Attachments.normalizeUrl(url, token) : url;
    body.innerHTML =
      '<div class="lb-error">' +
        '<div class="ic"><i class="bi bi-file-earmark-break-fill"></i></div>' +
        '<p>当前环境不支持在线预览该文件</p>' +
        '<p>您可以下载原文件后使用对应软件查看：</p>' +
        '<p style="margin-top:16px;">' +
          '<button class="lb-btn" onclick="window.Attachments && window.Attachments.download(&quot;' + esc(dlUrl) + '&quot;,&quot;' + esc(name) + '&quot;)">' +
          '<i class="bi bi-download"></i> 下载原文件</button>' +
        '</p>' +
      '</div>';
  }

  // esc helper（mobile-common.js 不一定第一时间 ready，兜底实现）
  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  /**
   * 打开附件预览（统一入口，按类型分流）
   * @param {string} url    文件 URL
   * @param {string} ext    文件扩展名（大写，可选——不传则从文件名解析）
   * @param {string} name   文件名（显示在标题栏/下载提示）
   * @param {string} [token] 预览签名令牌
   */
  window.openPreview = function (url, ext, name, token) {
    ensureDom();
    url = url || '';
    name = name || '附件';
    ext = (ext || '').toUpperCase();
    if (!ext) {
      var dot = name.lastIndexOf('.');
      if (dot > 0) ext = name.substring(dot + 1).toUpperCase();
    }
    document.getElementById('lbTitle').textContent = name;
    document.body.style.overflow = 'hidden';
    document.getElementById('lbOverlay').classList.add('show');

    // 下载按钮：图片/PDF/Office 都显示
    // v2.43.5 补丁③：下载走「带令牌的 /preview 代理 URL」——被甩到外部浏览器（无会话 Cookie）时
    // 也能凭令牌免登录下载（原始 /uploads 静态路径在无会话/URL 规范化下可能被鉴权拦截跳登录）。
    var dlBtn = document.getElementById('lbDownloadBtn');
    if (dlBtn) {
      dlBtn.style.display = 'inline-flex';
      var dlUrl = window.Attachments ? window.Attachments.normalizeUrl(url, token) : url;
      window._lbDownloadInfo = { url: dlUrl, name: name };
    }

    if (IMG_EXTS.indexOf(ext) !== -1) {
      renderImage(url, name, token);
    } else if (PDF_EXTS.indexOf(ext) !== -1 || ext === 'DOCX' || ext === 'XLSX') {
      // PDF/DOCX/XLSX：跳转通用文档预览页 /m/office-preview（pdf→PDF.js / docx→docx-preview / xlsx→SheetJS，
      // 钉钉 WebView 内渲染不跳出外部浏览器）
      // v2.43.5 补丁②：令牌改为顶层 p/t 参数，避免 f 嵌套参数在外部浏览器被 URL 规范化拆散
      var qs = '/m/office-preview?p=' + encodeURIComponent(url)
             + (token ? '&t=' + encodeURIComponent(token) : '')
             + '&name=' + encodeURIComponent(name);
      window.location.href = qs;
    } else if (ext === 'DOC' || ext === 'XLS') {
      // 老格式 Word/Excel（.doc/.xls）：前端库无法渲染——docx 塞 iframe 会被 WebView 触发原生下载，
      // 且下载器按 /preview 路径段命名错存为 preview.htm；Excel /preview 回 octet-stream 也无法内嵌。
      // 统一给「不支持在线预览 + 下载原文件」兜底（下载走带令牌的 /preview 代理 + 业务原始文件名）。
      showOfficeFallback(url, name, token);
    } else {
      renderOfficePreview(url, name, token);
    }
  };

  /** 关闭 Lightbox */
  window.closePreview = function () {
    var lb = document.getElementById('lbOverlay');
    if (!lb) return;
    lb.classList.remove('show');
    document.body.style.overflow = '';
    var body = document.getElementById('lbBody');
    if (body) body.innerHTML = '';
    window._lbDownloadInfo = null;
  };

})();
