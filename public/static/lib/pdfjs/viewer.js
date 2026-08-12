// +----------------------------------------------------------------------
// | 移动端 PDF 轻量预览 Viewer（钉钉 WebView 内 canvas 渲染，不跳出）
// | 依赖：pdfjs-dist (pdfjsLib)，需先加载 /static/lib/pdfjs/build/pdf.js
// | 使用：页面需包含 #pdfCanvas / #btnPrev / #btnNext / #pageCur / #pageTotal / #pdfError
// +----------------------------------------------------------------------

(function () {
  'use strict';

  var canvas = document.getElementById('pdfCanvas');
  var ctx = canvas.getContext('2d');
  var btnPrev = document.getElementById('btnPrev');
  var btnNext = document.getElementById('btnNext');
  var pageCurEl = document.getElementById('pageCur');
  var pageTotalEl = document.getElementById('pageTotal');
  var errorEl = document.getElementById('pdfError');
  var loadingEl = document.getElementById('pdfLoading');

  // 从页面 data 属性获取 PDF 代理 URL
  var url = document.getElementById('pdfContainer')?.dataset?.file || '';

  if (!url) {
    loadingEl.style.display = 'none';
    errorEl.style.display = 'flex';
    errorEl.textContent = '缺少文件地址';
    return;
  }

  // 工作线程路径
  if (typeof pdfjsLib !== 'undefined') {
    pdfjsLib.GlobalWorkerOptions.workerSrc = '/static/lib/pdfjs/build/pdf.worker.js';
  } else {
    loadingEl.style.display = 'none';
    errorEl.style.display = 'flex';
    errorEl.textContent = 'PDF.js 库加载失败，请刷新重试';
    return;
  }

  var pdfDoc = null;
  var pageNum = 0;
  var totalPages = 0;
  var pageRendering = false;
  var pageNumPending = null;
  // v2.43.5 补丁④：不再固定 scale=1.5——A4 等宽页面 ×1.5 后 canvas 宽度远超手机屏，
  // flex 居中 + overflow 时左侧溢出不可达（用户看到左侧内容被遮挡）；
  // renderPage 改为按容器宽度自适应缩放（整页可见，上限 2.5 保清晰度、下限 0.5）。

  // 加载 PDF
  pdfjsLib.getDocument({
    url: url,
    cMapUrl: '/static/lib/pdfjs/cmaps/',
    cMapPacked: true
  }).promise.then(function (pdf) {
    pdfDoc = pdf;
    totalPages = pdf.numPages;
    pageTotalEl.textContent = totalPages;
    if (totalPages > 0) {
      renderPage(1);
    }
  }).catch(function (err) {
    console.error('PDF 加载失败:', err);
    loadingEl.style.display = 'none';
    errorEl.style.display = 'flex';
    errorEl.textContent = '文档加载失败，请返回重试';
  });

  // 渲染指定页
  function renderPage(num) {
    pageRendering = true;
    loadingEl.style.display = 'flex';
    errorEl.style.display = 'none';
    pdfDoc.getPage(num).then(function (page) {
      var container = document.getElementById('pdfContainer');
      var cw = (container && container.clientWidth) || window.innerWidth || 375;
      var base = page.getViewport({ scale: 1 });
      // 页面宽度适配容器（留 8px 边距），整页可见不遮挡
      var fit = Math.min(2.5, Math.max(0.5, (cw - 8) / base.width));
      lastFit = fit; // 记录当前页适配比例（双击切换「原始大小」用）
      var viewport = page.getViewport({ scale: fit });
      // v2.43.6：高 DPR 屏幕（钉钉移动端 WebView 通常 DPR≥2）按物理像素渲染——
      // canvas 内部像素 × devicePixelRatio 并固定 CSS 尺寸，避免 PDF 被放大显示时文字/线条发虚模糊
      var dpr = window.devicePixelRatio || 1;
      canvas.width = Math.floor(viewport.width * dpr);
      canvas.height = Math.floor(viewport.height * dpr);
      canvas.style.width = Math.floor(viewport.width) + 'px';
      canvas.style.height = Math.floor(viewport.height) + 'px';
      var renderContext = { canvasContext: ctx, viewport: viewport };
      if (dpr !== 1) {
        renderContext.transform = [dpr, 0, 0, dpr, 0, 0];
      }
      var renderTask = page.render(renderContext);
      renderTask.promise.then(function () {
        pageRendering = false;
        loadingEl.style.display = 'none';
        if (pageNumPending !== null) {
          renderPage(pageNumPending);
          pageNumPending = null;
        }
      }).catch(function () {
        pageRendering = false;
        loadingEl.style.display = 'none';
        errorEl.style.display = 'flex';
        errorEl.textContent = '渲染失败';
      });
      pageNum = num;
      pageCurEl.textContent = num;
      updateButtons();
    }).catch(function () {
      pageRendering = false;
      loadingEl.style.display = 'none';
      errorEl.style.display = 'flex';
      errorEl.textContent = '无法加载第 ' + num + ' 页';
    });
  }

  function queueRenderPage(num) {
    if (pageRendering) {
      pageNumPending = num;
    } else {
      renderPage(num);
    }
  }

  function updateButtons() {
    btnPrev.disabled = (pageNum <= 1);
    btnNext.disabled = (pageNum >= totalPages);
  }

  // 按钮事件
  btnPrev.addEventListener('click', function () {
    if (pageNum > 1) { queueRenderPage(pageNum - 1); }
  });
  btnNext.addEventListener('click', function () {
    if (pageNum < totalPages) { queueRenderPage(pageNum + 1); }
  });

  // ===== 缩放控制（按钮 + 双指 pinch + 双击切换，对齐 docx 逻辑） =====
  // 基准=适配状态（canvas CSS 尺寸已按容器宽度 fit 渲染），CSS zoom 叠加放大/缩小。
  // 钉钉 WebView 是 Chromium 内核，原生支持 zoom（影响布局流，非 transform:scale）。
  var lastFit = 1;      // 当前页适配比例（renderPage 内更新）
  var baseZoom = 1;     // 适配状态 = 100%
  var currentZoom = 1;
  var zoomMin = 0.8;
  var zoomMax = 3;
  var zoomValEl = document.getElementById('zoomVal');

  function applyZoom(z) {
    currentZoom = Math.min(Math.max(z, zoomMin), zoomMax);
    canvas.style.zoom = currentZoom;
    // 放大时贴左上（flex 布局下 margin:0 auto 会使超宽 canvas 两侧溢出、左侧不可达），
    // 适配/缩小时保持居中
    canvas.style.margin = currentZoom > baseZoom ? '0' : '0 auto';
    if (zoomValEl) { zoomValEl.textContent = Math.round(currentZoom / baseZoom * 100) + '%'; }
  }
  applyZoom(baseZoom);

  // 按钮：每次 ±20%（相对 baseZoom）
  var btnIn = document.getElementById('zoomIn');
  var btnOut = document.getElementById('zoomOut');
  if (btnIn) btnIn.addEventListener('click', function () { applyZoom(currentZoom + baseZoom * 0.2); });
  if (btnOut) btnOut.addEventListener('click', function () { applyZoom(currentZoom - baseZoom * 0.2); });

  // 双指 pinch 缩放
  var pdfContainer = document.getElementById('pdfContainer');
  var pinchDist = 0;
  var pinchStartZoom = 1;
  if (pdfContainer) {
    pdfContainer.addEventListener('touchstart', function (e) {
      if (e.touches.length === 2) {
        pinchDist = Math.hypot(
          e.touches[0].clientX - e.touches[1].clientX,
          e.touches[0].clientY - e.touches[1].clientY
        );
        pinchStartZoom = currentZoom;
      }
    }, { passive: true });
    pdfContainer.addEventListener('touchmove', function (e) {
      if (e.touches.length === 2 && pinchDist > 0) {
        e.preventDefault();
        var d = Math.hypot(
          e.touches[0].clientX - e.touches[1].clientX,
          e.touches[0].clientY - e.touches[1].clientY
        );
        applyZoom(pinchStartZoom * d / pinchDist);
      }
    }, { passive: false });
    pdfContainer.addEventListener('touchend', function (e) {
      if (e.touches.length < 2) pinchDist = 0;
    }, { passive: true });

    // 双击切换：适配屏幕 / 原始大小(100%)
    var lastTap = 0;
    pdfContainer.addEventListener('touchend', function () {
      var now = Date.now();
      if (now - lastTap < 300) {
        if (currentZoom < baseZoom * 1.1) { applyZoom(lastFit > 0 ? 1 / lastFit : 1); }
        else { applyZoom(baseZoom); }
      }
      lastTap = now;
    }, { passive: true });
  }

  // 手势滑动翻页（可选：简化实现，暂不做）
})();
