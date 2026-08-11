// +----------------------------------------------------------------------
// | 附件处理公共工具（优化：统一附件标准化、预览入口、下载能力）
// | 依赖：无
// | 适用于 PC 端 + 移动端（所有包含附件的页面共用）
// +----------------------------------------------------------------------

(function (global) {
  'use strict';

  // ─── 附件类型判定（与 Bootstrap Icons 图标映射） ───
  var IMG_EXTS = ['JPG', 'JPEG', 'PNG', 'GIF', 'WEBP', 'BMP', 'SVG'];
  var DOC_EXTS = ['PDF', 'DOC', 'DOCX', 'XLS', 'XLSX', 'PPT', 'PPTX', 'TXT', 'MD', 'CSV'];
  var EXT_ICON_CLS = {
    PDF: 'bi-file-pdf text-danger',
    DOC: 'bi-file-word text-primary',
    DOCX: 'bi-file-word text-primary',
    XLS: 'bi-file-excel text-success',
    XLSX: 'bi-file-excel text-success',
    PPT: 'bi-file-ppt text-warning',
    PPTX: 'bi-file-ppt text-warning',
    TXT: 'bi-file-text text-secondary',
    MD: 'bi-file-text text-secondary',
    CSV: 'bi-file-spreadsheet text-success',
    JPG: 'bi-file-image text-warning',
    JPEG: 'bi-file-image text-warning',
    PNG: 'bi-file-image text-warning',
    GIF: 'bi-file-image text-warning',
    WEBP: 'bi-file-image text-warning',
    BMP: 'bi-file-image text-warning',
    SVG: 'bi-file-image text-info',
    ZIP: 'bi-file-zip text-muted',
    RAR: 'bi-file-zip text-muted',
    '7Z': 'bi-file-zip text-muted',
  };

  /**
   * 标准化单个附件对象（兼容各种历史格式：{url,name,size} 或 {file_url,file_name} 或纯 URL 字符串）
   * @param {any} raw  原始附件数据
   * @returns {{url:string, name:string, ext:string, extUpper:string, size:number, sizeText:string, iconCls:string, isImg:boolean, isPdf:boolean, isDoc:boolean, file_url?:string, file_name?:string}}
   */
  function normalizeAttachmentItem(raw) {
    if (raw == null) return null;
    var item = { url: '', name: '', ext: '', extUpper: '', size: 0, sizeText: '', iconCls: 'bi-file-earmark text-secondary', isImg: false, isPdf: false, isDoc: false };
    if (typeof raw === 'string') {
      item.url = raw;
    } else if (typeof raw === 'object') {
      item.url = raw.url || raw.file_url || raw.path || raw.href || '';
      item.name = raw.name || raw.file_name || raw.title || raw.original_name || '';
      item.size = parseInt(raw.size || raw.file_size || raw.length || 0, 10) || 0;
    }
    if (!item.url) return null;
    // 兜底从 URL 提取文件名
    if (!item.name) {
      try {
        var p = new URL(item.url, window.location.origin);
        var pathPart = decodeURIComponent(p.pathname.split('/').pop() || '');
        item.name = pathPart || '未知文件';
      } catch (e) {
        item.name = item.url.split('/').pop() || '未知文件';
      }
    }
    // 扩展名
    var dotIdx = item.name.lastIndexOf('.');
    item.ext = dotIdx > 0 ? item.name.substring(dotIdx + 1).toLowerCase() : '';
    item.extUpper = item.ext.toUpperCase();
    item.iconCls = EXT_ICON_CLS[item.extUpper] || 'bi-file-earmark text-secondary';
    item.isImg = IMG_EXTS.indexOf(item.extUpper) !== -1;
    item.isPdf = item.extUpper === 'PDF';
    item.isDoc = DOC_EXTS.indexOf(item.extUpper) !== -1;
    item.sizeText = formatSize(item.size);
    return item;
  }

  /** 从 JSON 字符串 / 对象数组 提取并标准化附件列表 */
  function collectAttachmentValues(raw) {
    if (!raw) return [];
    var arr = [];
    if (typeof raw === 'string') {
      try { arr = JSON.parse(raw); } catch (e) { arr = []; }
    } else if (Array.isArray(raw)) {
      arr = raw;
    } else {
      arr = [raw];
    }
    var out = [];
    for (var i = 0; i < arr.length; i++) {
      var n = normalizeAttachmentItem(arr[i]);
      if (n) out.push(n);
    }
    return out;
  }

  /** 文件大小格式化（B/KB/MB） */
  function formatSize(bytes) {
    if (!bytes || bytes <= 0) return '';
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1048576) return Math.round(bytes / 1024) + ' KB';
    return (bytes / 1048576).toFixed(1) + ' MB';
  }

  /**
   * 构造预览 URL（拼接签名令牌，图片可省略 token 但统一传也安全）
   * @param {string} url    原始文件 URL（通常是 /static/uploads/xxx）
   * @param {string} [token] 后端签发的 preview_token（可选；移动端钉钉 WebView 打开 PDF/Office 时必传）
   */
  function normalizeAttachmentUrl(url, token) {
    if (!url) return '';
    // 绝对 URL 不处理（外链）
    if (/^https?:\/\//i.test(url) || url.indexOf('//') === 0) return url;
    var base = url.indexOf('/preview') === 0 ? url : ('/preview?p=' + encodeURIComponent(url));
    // 令牌为 base64(exp.signature)，字符集不含点号——只要非空即拼 t 参数（v2.43.5 曾按 indexOf('.') 判断恒失败，导致 Office 预览/下载缺令牌）
    if (token) {
      base += (base.indexOf('?') === -1 ? '?' : '&') + 't=' + encodeURIComponent(token);
    }
    return base;
  }

  /**
   * 触发浏览器下载（优先 fetch + blob，兜底新窗口打开）
   * @param {string} url        文件 URL
   * @param {string} [fileName] 下载文件名（可选）
   */
  function downloadAttachment(url, fileName) {
    if (!url) return;
    // 兜底文件名
    if (!fileName) {
      try {
        var p = new URL(url, window.location.origin);
        fileName = decodeURIComponent(p.pathname.split('/').pop() || '下载文件');
      } catch (e) {
        fileName = url.split('/').pop() || '下载文件';
      }
    }
    // iOS/Safari 直接开新窗口（download 属性不生效）
    var isIOS = /iPhone|iPad|iPod/i.test(navigator.userAgent);
    if (isIOS || typeof fetch !== 'function') {
      window.open(url, '_blank', 'noopener');
      return;
    }
    // 图片类型：不走 blob + a[download]——钉钉/微信 WebView 对该机制支持差，
    // 下载产物可能损坏/被系统误存（v2.43.7 用户实测「下载后不是文件实际格式」）。
    // 统一新窗口打开 /preview 大图（inline image/jpeg），用户长按保存原图（微信/钉钉成熟模式）。
    var isImg = /\.(jpe?g|png|gif|webp|bmp)$/i.test(fileName || url);
    if (isImg) {
      window.open(url, '_blank', 'noopener');
      return;
    }
    // fetch + blob + a[download]
    var toast = global.showToast || global.toast || function () { };
    toast('下载中…', 'info');
    fetch(url, { credentials: 'same-origin', method: 'GET' })
      .then(function (res) {
        if (!res.ok) throw new Error('HTTP ' + res.status);
        return res.blob();
      })
      .then(function (blob) {
        var blobUrl = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = blobUrl;
        a.download = fileName;
        a.rel = 'noopener';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        setTimeout(function () { URL.revokeObjectURL(blobUrl); }, 10000);
        toast('下载已开始', 'success');
      })
      .catch(function () {
        // 降级：新窗口打开
        window.open(url, '_blank', 'noopener');
      });
  }

  // ─── 挂载全局 ───
  var Attachments = {
    IMG_EXTS: IMG_EXTS,
    DOC_EXTS: DOC_EXTS,
    EXT_ICON_CLS: EXT_ICON_CLS,
    normalizeItem: normalizeAttachmentItem,
    collectValues: collectAttachmentValues,
    formatSize: formatSize,
    normalizeUrl: normalizeAttachmentUrl,
    download: downloadAttachment,
  };

  global.Attachments = Attachments;
  // 兼容之前的零散函数名
  global.normalizeAttachmentItem = normalizeAttachmentItem;
  global.collectAttachmentValues = collectAttachmentValues;
  global.downloadAttachment = downloadAttachment;
  global.formatSize = formatSize;

})(typeof window !== 'undefined' ? window : this);
