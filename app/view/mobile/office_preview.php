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
    /* 加载/错误层（公共） */
    .doc-area { position:relative; flex:1; overflow:auto; background:#404040; }
    .doc-loading, .doc-error { position:absolute; inset:0; display:flex; align-items:center; justify-content:center; font-size:14px; z-index:5; }
    .doc-loading { color:#ccc; }
    .doc-loading::before { content:''; display:inline-block; width:18px; height:18px; border:2px solid rgba(255,255,255,.25); border-top-color:#fff; border-radius:50%; animation:spin .7s linear infinite; margin-right:8px; }
    @keyframes spin { to { transform:rotate(360deg); } }
    .doc-error { display:none; color:#ff6b6b; }
    /* PDF：Canvas 容器（v2.43.5 补丁④：flex-start——居中 + overflow 时超宽内容左侧溢出不可达） */
    .pdf-container { flex:1; overflow:auto; display:flex; align-items:flex-start; justify-content:flex-start; background:#404040; position:relative; }
    .pdf-container canvas { display:block; margin:0 auto; }
    .pdf-loading, .pdf-error { position:absolute; inset:0; display:flex; align-items:center; justify-content:center; font-size:14px; }
    .pdf-loading { color:#ccc; }
    .pdf-loading::before { content:''; display:inline-block; width:18px; height:18px; border:2px solid rgba(255,255,255,.25); border-top-color:#fff; border-radius:50%; animation:spin .7s linear infinite; margin-right:8px; }
    .pdf-error { display:none; color:#ff6b6b; }
    /* docx 渲染容器（docx-preview 自带 wrapper 样式，这里只做滚动与留白）
       v2.44.3 移动端修复：docx-preview 默认 wrapper align-items:center 居中，窄屏下超宽页面
       居中溢出且左侧不可达（与 PDF 曾出现的遮挡同因）。强制左对齐 + 页面宽度由 JS 渲染时固化，
       保证 A4 原宽 + 横向滚动可达（与 PDF 预览交互一致）。 */
    .office-container { flex:1; overflow:auto; background:#6b6b6b; padding:16px 0; }
    #officeContainer .docx-wrapper { align-items: flex-start !important; }
    /* xlsx 表格容器（SheetJS sheet_to_html 生成的 table 富样式，加壳防溢出） */
    .xlsx-wrap { overflow:auto; background:#fff; margin:0 auto; max-width:100%; }
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
      // v2.44.3 移动端修复：docx-preview 以容器宽度排版（段落/表格按容器宽计算），
      // 窄屏下 A4 内容被压缩变形（两侧挤压）。先撑宽容器以桌面宽度渲染，完成后
      // 固化每页渲染宽度并恢复容器——页面保持原宽 + 横向滚动查看（与 PDF 预览一致）。
      if (!window.docx || !window.JSZip) { showError('预览组件加载失败，请下载查看'); return; }
      var renderWidth = Math.max(box.offsetWidth || 390, 850);
      box.style.width = renderWidth + 'px';
      fetchFile().then(function (r) { return r.blob(); }).then(function (blob) {
        return window.docx.renderAsync(blob, box, null, { className: 'docx', inWrapper: true });
      }).then(function () {
        var secs = box.querySelectorAll('section.docx');
        var pageW = 0;
        for (var i = 0; i < secs.length; i++) {
          pageW = Math.max(pageW, secs[i].getBoundingClientRect().width);
        }
        if (pageW > 0) {
          // 固化页面宽度，避免恢复容器后被 docx-preview 的 flex 布局重新压缩
          for (var j = 0; j < secs.length; j++) {
            secs[j].style.width = pageW + 'px';
            secs[j].style.flex = 'none';
          }
        }
        box.style.width = '';
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
      }).catch(function () { showError('表格解析失败，请下载后用 Excel 查看'); });
    }
  })();
  </script>
  <?php endif; ?>
</body>
</html>
