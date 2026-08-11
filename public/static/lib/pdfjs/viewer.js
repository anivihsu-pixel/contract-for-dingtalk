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

  // 手势滑动翻页（可选：简化实现，暂不做）
})();
