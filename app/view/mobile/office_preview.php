<?php
// +----------------------------------------------------------------------
// | 通用文档预览页（v2.43.6 起 doc-preview 升级为 office-preview，按扩展名选渲染器）
// | pdf  → PDF.js canvas 渲染（分页工具栏）
// | docx → docx-preview 纯前端渲染（HTML 还原，样式近似 Word）
// | xlsx → SheetJS 解析 + sheet_to_html 表格渲染（多 sheet 分块）
// | doc/xls 老格式前端库不支持 → 前端调用方已给下载兜底，不会跳到此页
// | 调用方：lightbox.js openPreview() / PC contract&resource detail 按扩展名分流
// | 令牌免登录：Auth 中间件对 /m/office-preview 解析顶层 p/t 参数放行（同 /preview）
// +----------------------------------------------------------------------
$fileUrl  = $fileUrl ?? '';
$fileName = $fileName ?? '文档预览';
$ext      = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
$isPdf    = $ext === 'pdf';
$isDocx   = $ext === 'docx';
$isXlsx   = $ext === 'xlsx';
?><!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title><?=htmlspecialchars($fileName)?></title>
  <style>
    * { margin:0; padding:0; box-sizing:border-box; }
    body { display:flex; flex-direction:column; height:100vh; height:100dvh; overflow:hidden; background:#222; }
    /* 顶部导航栏 */
    .m-nav { display:flex; align-items:center; height:44px; background:#1677ff; color:#fff; padding:0 12px; flex-shrink:0; }
    .m-nav .back { color:#fff; text-decoration:none; font-size:14px; line-height:44px; margin-right:8px; }
    .m-nav .title { flex:1; font-size:15px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .m-nav .dl { color:#fff; font-size:13px; text-decoration:none; margin-left:8px; }
    .m-nav .zoom-btns { display:flex; align-items:center; gap:2px; margin-left:6px; }
    .m-nav .zoom-btns button { width:28px; height:28px; border:none; border-radius:4px; background:rgba(255,255,255,.2); color:#fff; font-size:16px; line-height:1; cursor:pointer; display:flex; align-items:center; justify-content:center; }
    .m-nav .zoom-btns button:active { background:rgba(255,255,255,.35); }
    .m-nav .zoom-btns .zoom-val { color:rgba(255,255,255,.8); font-size:11px; min-width:30px; text-align:center; }
    /* 加载/错误层（公共） */
    .doc-area { position:relative; flex:1; overflow:auto; background:#525659; }
    .doc-loading, .doc-error { position:absolute; inset:0; display:flex; align-items:center; justify-content:center; font-size:14px; z-index:5; }
    .doc-loading { color:#ccc; }
    .doc-loading::before { content:''; display:inline-block; width:18px; height:18px; border:2px solid rgba(255,255,255,.25); border-top-color:#fff; border-radius:50%; animation:spin .7s linear infinite; margin-right:8px; }
    @keyframes spin { to { transform:rotate(360deg); } }
    .doc-error { display:none; color:#ff6b6b; }
    /* PDF：Canvas 容器（v2.43.5 补丁④：flex-start——居中 + overflow 时超宽内容左侧溢出不可达） */
    .pdf-container { flex:1; overflow:auto; display:flex; align-items:flex-start; justify-content:flex-start; background:#525659; position:relative; }
    .pdf-container canvas { display:block; margin:0 auto; }
    .pdf-loading, .pdf-error { position:absolute; inset:0; display:flex; align-items:center; justify-content:center; font-size:14px; }
    .pdf-loading { color:#ccc; }
    .pdf-loading::before { content:''; display:inline-block; width:18px; height:18px; border:2px solid rgba(255,255,255,.25); border-top-color:#fff; border-radius:50%; animation:spin .7s linear infinite; margin-right:8px; }
    .pdf-error { display:none; color:#ff6b6b; }
    /* docx 渲染容器：统一深灰背景 + 白色页面阴影，docx-preview 按 Word 原始页面宽度渲染后
       JS 用 CSS zoom 等比缩小到屏幕宽度，无需横向滚动 */
    #officeContainer .docx-wrapper { background:transparent !important; padding:12px 0 !important; align-items:center !important; }
    #officeContainer section.docx { box-shadow:0 1px 6px rgba(0,0,0,.35); margin:0 12px 12px; }
    /* docx 表格兜底边框：仅对原文档无边框的表格补浅灰边框（有边框的不覆盖，保原样式） */
    #officeContainer .docx-table-no-border td, #officeContainer .docx-table-no-border th { border:1px solid #d9d9d9 !important; padding:4px 8px; }
    /* xlsx 表格容器（SheetJS sheet_to_html 生成的 table 富样式，加壳防溢出） */
    .xlsx-wrap { overflow:auto; background:#fff; margin:0 auto; max-width:100%; }
    .xlsx-wrap table { border-collapse:collapse; }
    .xlsx-wrap td, .xlsx-wrap th { border:1px solid #d9d9d9; padding:6px 10px; font-size:12px; color:#333; }
    .xlsx-wrap th { background:#f5f7fa; font-weight:600; }
    .xlsx-sheet-title { background:#fff; color:#333; font-size:13px; font-weight:600; padding:10px 14px 4px; }
    /* 底部工具栏（仅 PDF） */
    .toolbar { display:flex; align-items:center; justify-content:center; height:48px; background:#fff; border-top:1px solid #e5e5e5; gap:16px; flex-shrink:0; user-select:none; }
    .toolbar button { padding:6px 20px; border:1px solid #d9d9d9; border-radius:4px; background:#fff; font-size:14px; color:#333; cursor:pointer; }
    .toolbar button:disabled { opacity:.35; cursor:default; }
    .toolbar .page-info { font-size:14px; color:#666; min-width:60px; text-align:center; }
  </style>
</head>
<body>
  <div class="m-nav">
    <a class="back" href="javascript:history.back()">← 返回</a>
    <span class="title"><?=htmlspecialchars($fileName)?></span>
    <a class="dl" href="<?=htmlspecialchars($fileUrl)?>" download="<?=htmlspecialchars($fileName)?>">下载</a>
    <?php if ($isDocx || $isPdf || $isXlsx): ?>
    <div class="zoom-btns">
      <button type="button" id="zoomOut" aria-label="缩小">−</button>
      <span class="zoom-val" id="zoomVal">100%</span>
      <button type="button" id="zoomIn" aria-label="放大">+</button>
    </div>
    <?php endif; ?>
  </div>

  <?php if ($fileUrl): ?>
    <?php if ($isPdf): ?>
    <!-- PDF.js canvas -->
    <div class="pdf-container" id="pdfContainer" data-file="<?=htmlspecialchars($fileUrl)?>">
      <canvas id="pdfCanvas"></canvas>
      <div class="pdf-loading" id="pdfLoading">加载中...</div>
      <div class="pdf-error" id="pdfError">文档加载失败，请返回重试</div>
    </div>
    <div class="toolbar">
      <button id="btnPrev" disabled>上一页</button>
      <span class="page-info"><span id="pageCur">0</span> / <span id="pageTotal">0</span></span>
      <button id="btnNext" disabled>下一页</button>
    </div>
    <?php elseif ($isDocx || $isXlsx): ?>
    <!-- docx / xlsx 渲染区 -->
    <div class="doc-area" id="docArea">
      <div class="doc-loading" id="docLoading">文档加载中…</div>
      <div class="doc-error" id="docError">文档加载失败，请返回重试或下载查看</div>
      <div id="officeContainer"></div>
    </div>
    <?php else: ?>
    <div style="flex:1;display:flex;align-items:center;justify-content:center;color:#999;font-size:14px;">该格式不支持在线预览，请下载后查看</div>
    <?php endif; ?>
  <?php else: ?>
  <div style="flex:1;display:flex;align-items:center;justify-content:center;color:#999;font-size:14px;">缺少文件地址，请返回重试</div>
  <?php endif; ?>

  <?php if ($isPdf): ?>
  <script src="<?=asset_url('lib/pdfjs/build/pdf.js')?>"></script>
  <script src="<?=asset_url('lib/pdfjs/viewer.js')?>"></script>
  <?php elseif ($isDocx): ?>
  <!-- docx-preview 0.3.7 (Apache-2.0) + jszip 3.10.1，自托管（v2.42.0 禁 CDN） -->
  <script src="<?=asset_url('vendor/office-preview/jszip.min.js')?>"></script>
  <script src="<?=asset_url('vendor/office-preview/docx-preview.min.js')?>"></script>
  <?php elseif ($isXlsx): ?>
  <!-- SheetJS xlsx 0.20.3 (Apache-2.0)，自托管 -->
  <script src="<?=asset_url('vendor/office-preview/xlsx.full.min.js')?>"></script>
  <?php endif; ?>

  <?php if (!$isPdf && ($isDocx || $isXlsx)): ?>
  <script>
  (function () {
    'use strict';
    var FILE_URL = <?=json_encode($fileUrl, JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;
    var EXT = <?=json_encode($ext, JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;
    var loadingEl = document.getElementById('docLoading');
    var errorEl = document.getElementById('docError');
    var box = document.getElementById('officeContainer');

    function showError(msg) {
      if (loadingEl) loadingEl.style.display = 'none';
      errorEl.style.display = 'flex';
      errorEl.textContent = msg || '文档加载失败，请返回重试或下载查看';
    }

    function fetchFile() {
      return fetch(FILE_URL, { credentials: 'same-origin' }).then(function (r) {
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r;
      });
    }

    if (EXT === 'docx') {
      // docx-preview：Blob 直接渲染（HTML 语义还原，样式近似 Word）
      // docx-preview 按容器宽度排版，窄屏下 A4 内容被压缩变形。先撑宽容器以 A4
      // 原始宽度（~850px）渲染，恢复容器后用 CSS zoom 等比缩小到屏幕宽度。
      // 钉钉 WebView 是 Chromium 内核，原生支持 zoom（影响布局流，非 transform:scale）。
      if (!window.docx || !window.JSZip) { showError('预览组件加载失败，请下载查看'); return; }
      var renderWidth = 850;
      box.style.width = renderWidth + 'px';
      fetchFile().then(function (r) { return r.blob(); }).then(function (blob) {
        return window.docx.renderAsync(blob, box, null, { className: 'docx', inWrapper: true });
      }).then(function () {
        // 在恢复容器宽度前，先测量并固定 section 宽度（否则容器收窄后 section 会被重新压缩）
        var secs = box.querySelectorAll('section.docx');
        var pageW = 0;
        for (var i = 0; i < secs.length; i++) {
          pageW = Math.max(pageW, secs[i].offsetWidth || secs[i].getBoundingClientRect().width);
        }
        if (pageW > 0) {
          for (var j = 0; j < secs.length; j++) {
            secs[j].style.width = pageW + 'px';
            secs[j].style.flex = 'none';
          }
        }
        // 表格兜底边框：原文档（如 python-docx 生成）无边框样式时补浅灰边框，方便辨认单元格
        var docxTables = box.querySelectorAll('section.docx table');
        for (var ti = 0; ti < docxTables.length; ti++) {
          var firstCell = docxTables[ti].querySelector('td, th');
          if (firstCell && getComputedStyle(firstCell).borderTopStyle === 'none') {
            docxTables[ti].classList.add('docx-table-no-border');
          }
        }
        box.style.width = '';
        // CSS zoom 等比缩小到屏幕宽度（减去两侧 12px 留白，Chromium 原生支持）
        var wrapper = box.querySelector('.docx-wrapper');
        var baseZoom = 1;
        if (pageW > 0) {
          var containerW = box.clientWidth || window.innerWidth;
          var availW = containerW - 24; // 两侧各 12px margin
          if (pageW > availW) {
            baseZoom = availW / pageW;
          }
        }

        // ===== 缩放控制（按钮 + 双指 pinch + 双击切换） =====
        var currentZoom = baseZoom;
        var zoomMin = baseZoom * 0.8;
        var zoomMax = 3;
        var zoomValEl = document.getElementById('zoomVal');

        function applyZoom(z) {
          currentZoom = Math.min(Math.max(z, zoomMin), zoomMax);
          if (wrapper) { wrapper.style.zoom = currentZoom; }
          else { for (var i = 0; i < secs.length; i++) { secs[i].style.zoom = currentZoom; } }
          if (zoomValEl) { zoomValEl.textContent = Math.round(currentZoom / baseZoom * 100) + '%'; }
        }
        applyZoom(baseZoom);

        // 按钮：每次 ±20%（相对 baseZoom）
        var btnIn = document.getElementById('zoomIn');
        var btnOut = document.getElementById('zoomOut');
        if (btnIn) btnIn.addEventListener('click', function () { applyZoom(currentZoom + baseZoom * 0.2); });
        if (btnOut) btnOut.addEventListener('click', function () { applyZoom(currentZoom - baseZoom * 0.2); });

        // 双指 pinch 缩放
        var docArea = document.getElementById('docArea');
        var pinchDist = 0;
        var pinchStartZoom = 1;
        if (docArea) {
          docArea.addEventListener('touchstart', function (e) {
            if (e.touches.length === 2) {
              pinchDist = Math.hypot(
                e.touches[0].clientX - e.touches[1].clientX,
                e.touches[0].clientY - e.touches[1].clientY
              );
              pinchStartZoom = currentZoom;
            }
          }, { passive: true });
          docArea.addEventListener('touchmove', function (e) {
            if (e.touches.length === 2 && pinchDist > 0) {
              e.preventDefault();
              var d = Math.hypot(
                e.touches[0].clientX - e.touches[1].clientX,
                e.touches[0].clientY - e.touches[1].clientY
              );
              applyZoom(pinchStartZoom * d / pinchDist);
            }
          }, { passive: false });
          docArea.addEventListener('touchend', function (e) {
            if (e.touches.length < 2) pinchDist = 0;
          }, { passive: true });

          // 双击切换：适配屏幕 / 原始大小(100%)
          var lastTap = 0;
          docArea.addEventListener('touchend', function () {
            var now = Date.now();
            if (now - lastTap < 300) {
              if (currentZoom < baseZoom * 1.1) { applyZoom(1); }
              else { applyZoom(baseZoom); }
            }
            lastTap = now;
          }, { passive: true });
        }

        if (loadingEl) loadingEl.style.display = 'none';
      }).catch(function () { box.style.width = ''; showError('文档解析失败，请下载后用 Word 查看'); });
    } else if (EXT === 'xlsx') {
      // SheetJS：解析 workbook → 每个 sheet 用 sheet_to_html 渲染表格
      if (!window.XLSX) { showError('预览组件加载失败，请下载查看'); return; }
      fetchFile().then(function (r) { return r.arrayBuffer(); }).then(function (buf) {
        var wb;
        try { wb = window.XLSX.read(new Uint8Array(buf), { type: 'array' }); }
        catch (e) { throw new Error('parse'); }
        if (!wb || !wb.SheetNames || !wb.SheetNames.length) throw new Error('empty');
        var frag = document.createDocumentFragment();
        wb.SheetNames.forEach(function (name) {
          var ws = wb.Sheets[name];
          var title = document.createElement('div');
          title.className = 'xlsx-sheet-title';
          title.textContent = '工作表：' + name;
          frag.appendChild(title);
          var wrap = document.createElement('div');
          wrap.className = 'xlsx-wrap';
          wrap.innerHTML = window.XLSX.utils.sheet_to_html(ws, { editable: false });
          frag.appendChild(wrap);
        });
        box.innerHTML = '';
        box.appendChild(frag);
        if (loadingEl) loadingEl.style.display = 'none';

        // ===== xlsx 缩放控制（按钮 + 双指 pinch + 双击切换，同 docx） =====
        // 表格按原始宽度渲染（可能远超屏宽），CSS zoom 等比缩小到屏幕宽度适配
        var xlsxWraps = box.querySelectorAll('.xlsx-wrap');
        var tblW = 0;
        for (var i = 0; i < xlsxWraps.length; i++) {
          var w = xlsxWraps[i].scrollWidth || xlsxWraps[i].offsetWidth;
          if (w > tblW) tblW = w;
        }
        var baseZoom = 1;
        if (tblW > 0) {
          var containerW = box.clientWidth || window.innerWidth;
          var availW = containerW - 24; // 两侧各 12px 留白
          if (tblW > availW) baseZoom = availW / tblW;
        }
        var currentZoom = baseZoom;
        var zoomMin = baseZoom * 0.8;
        var zoomMax = 3;
        var zoomValEl = document.getElementById('zoomVal');

        function applyZoom(z) {
          currentZoom = Math.min(Math.max(z, zoomMin), zoomMax);
          box.style.zoom = currentZoom;
          if (zoomValEl) { zoomValEl.textContent = Math.round(currentZoom / baseZoom * 100) + '%'; }
        }
        applyZoom(baseZoom);

        // 按钮：每次 ±20%（相对 baseZoom）
        var btnIn = document.getElementById('zoomIn');
        var btnOut = document.getElementById('zoomOut');
        if (btnIn) btnIn.addEventListener('click', function () { applyZoom(currentZoom + baseZoom * 0.2); });
        if (btnOut) btnOut.addEventListener('click', function () { applyZoom(currentZoom - baseZoom * 0.2); });

        // 双指 pinch 缩放
        var docArea = document.getElementById('docArea');
        var pinchDist = 0;
        var pinchStartZoom = 1;
        if (docArea) {
          docArea.addEventListener('touchstart', function (e) {
            if (e.touches.length === 2) {
              pinchDist = Math.hypot(
                e.touches[0].clientX - e.touches[1].clientX,
                e.touches[0].clientY - e.touches[1].clientY
              );
              pinchStartZoom = currentZoom;
            }
          }, { passive: true });
          docArea.addEventListener('touchmove', function (e) {
            if (e.touches.length === 2 && pinchDist > 0) {
              e.preventDefault();
              var d = Math.hypot(
                e.touches[0].clientX - e.touches[1].clientX,
                e.touches[0].clientY - e.touches[1].clientY
              );
              applyZoom(pinchStartZoom * d / pinchDist);
            }
          }, { passive: false });
          docArea.addEventListener('touchend', function (e) {
            if (e.touches.length < 2) pinchDist = 0;
          }, { passive: true });

          // 双击切换：适配屏幕 / 原始大小(100%)
          var lastTap = 0;
          docArea.addEventListener('touchend', function () {
            var now = Date.now();
            if (now - lastTap < 300) {
              if (currentZoom < baseZoom * 1.1) { applyZoom(1); }
              else { applyZoom(baseZoom); }
            }
            lastTap = now;
          }, { passive: true });
        }
      }).catch(function () { showError('表格解析失败，请下载后用 Excel 查看'); });
    }
  })();
  </script>
  <?php endif; ?>
</body>
</html>
