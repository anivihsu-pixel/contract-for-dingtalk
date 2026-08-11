<?php
// +----------------------------------------------------------------------
// | 移动端资料库详情（查看型：仅阅读 + 开票资料一键复制；无编辑/删除入口——v2.43.6 起移动端纯只读）
// | INVOICE 类展示 content 结构化字段卡；有 file_url 提供内嵌预览（复用 /m/office-preview，钉钉不跳出）
// +----------------------------------------------------------------------
$title = '资料详情';
$tab = 'more';
include __DIR__ . '/_head.php';

$item = $item ?? null;
?>
<div class="m-nav">
  <a href="/m/resource" class="back" aria-label="返回"><i class="bi bi-chevron-left"></i></a>
  <div class="title">资料详情</div>
</div>

<div class="m-page" id="page">
<?php if(empty($item)): ?>
  <div class="m-empty" style="margin-top:48px"><i class="bi bi-exclamation-circle"></i>资料不存在或已删除</div>
<?php else: ?>
  <!-- 标题与分类 -->
  <div class="m-card">
    <div class="m-card-bd">
      <div style="font-size:17px;font-weight:600;color:#1f2329"><?=htmlspecialchars($item['title'])?></div>
      <div style="margin-top:8px">
        <span class="m-tag m-tag-info"><?=htmlspecialchars($item['category_name'])?></span>
        <?php if(!empty($item['company_name'])): ?><span class="m-tag m-tag-muted" style="margin-left:6px"><?=htmlspecialchars($item['company_name'])?></span><?php endif; ?>
      </div>
    </div>
  </div>

  <!-- 说明 -->
  <?php if(!empty($item['description'])): ?>
  <div class="m-card">
    <div class="m-card-hd">说明</div>
    <div class="m-card-bd" style="font-size:14px;color:#444;line-height:1.6"><?=nl2br(htmlspecialchars($item['description']))?></div>
  </div>
  <?php endif; ?>

  <!-- 结构化字段（开票资料等）：查看 + 一键复制 -->
  <?php if(!empty($item['content_arr'])): ?>
  <div class="m-card">
    <div class="m-card-hd" style="display:flex;justify-content:space-between;align-items:center">
      <span><?=htmlspecialchars($item['category_name'])?>信息</span>
      <button type="button" onclick="copyInvoiceFields()" style="font-size:12px;color:var(--m-brand);background:none;border:none;padding:4px 0"><i class="bi bi-clipboard"></i> 一键复制</button>
    </div>
    <div class="m-card-bd">
      <?php foreach($invoice_fields as $k=>$label): ?>
        <?php if(isset($item['content_arr'][$k]) && $item['content_arr'][$k] !== ''): ?>
        <div class="m-kv"><div class="k"><?=htmlspecialchars($label)?></div><div class="v"><?=htmlspecialchars($item['content_arr'][$k])?></div></div>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- 附件预览（内嵌，钉钉不跳出） -->
  <?php if(!empty($item['file_url'])): ?>
  <div class="m-card">
    <div class="m-card-hd">附件</div>
    <div class="m-card-bd">
      <div style="display:flex;align-items:center;justify-content:space-between;gap:10px">
        <div style="flex:1;min-width:0">
          <div style="font-size:14px;color:#1f2329;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?=htmlspecialchars($item['file_name'] ?: $item['file_url'])?></div>
          <?php if(!empty($item['file_size'])): ?><div style="font-size:12px;color:#8a9099;margin-top:2px"><?=number_format($item['file_size']/1024, 1)?> KB</div><?php endif; ?>
        </div>
        <div style="display:flex;gap:8px;flex:none">
          <button id="downloadBtn" type="button" class="m-btn m-btn-ghost" style="flex:none"><i class="bi bi-download"></i> 下载</button>
          <button id="previewBtn" type="button" class="m-btn m-btn-brand" style="flex:none">预览</button>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <div style="padding:4px 16px 8px;font-size:12px;color:#b0b5bd">
    创建时间：<?=htmlspecialchars($item['created_at'] ?? '')?>
  </div>
<?php endif; ?>
</div>

<script>
<?php if(!empty($item['content_arr'])): ?>
(function(){
  var FIELDS = <?=json_encode($invoice_fields ?? [], JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;
  var DATA = <?=json_encode($item['content_arr'] ?? [], JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;
  function fallbackCopyInvoice(text){
    var ta = document.createElement('textarea'); ta.value = text; document.body.appendChild(ta); ta.select();
    try { document.execCommand('copy'); showToast('已复制全部字段', 'success'); } catch(e){ showToast('复制失败，请手动选择', 'error'); }
    document.body.removeChild(ta);
  }
  // 一键复制全部结构化字段（优先 clipboard API，失败回退 execCommand）
  window.copyInvoiceFields = function(){
    var lines = [];
    Object.keys(FIELDS).forEach(function(k){
      var v = DATA[k];
      if (v !== undefined && v !== '') lines.push(FIELDS[k] + '：' + v);
    });
    var text = lines.join('\n');
    if (!text) { showToast('暂无字段内容可复制', 'error'); return; }
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(function(){ showToast('已复制全部字段', 'success'); }, function(){ fallbackCopyInvoice(text); });
    } else { fallbackCopyInvoice(text); }
  };
})();
<?php endif; ?>
<?php if(!empty($item['file_url'])): ?>
(function(){
  var fileUrl = <?=json_encode($item['file_url'], JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;
  var fileName = <?=json_encode($item['file_name'] ?: '资料预览', JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;
  // v2.43.5 补丁：预览令牌——被甩到外部浏览器（无会话 Cookie）时 /preview 与 /m/office-preview 均凭令牌免登录
  var ptoken = <?=json_encode(preview_token($item['file_url']), JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;
  var ext = (fileName.split('.').pop() || '').toUpperCase();
  // v2.43.7：下载与合同附件同链路——带令牌 /preview 代理 + fetch blob + 业务原始文件名
  var downloadBtn = document.getElementById('downloadBtn');
  if (downloadBtn) {
    downloadBtn.addEventListener('click', function(){
      var dlUrl = window.Attachments
        ? window.Attachments.normalizeUrl(fileUrl, ptoken)
        : ('/preview?p=' + encodeURIComponent(fileUrl) + (ptoken ? '&t=' + encodeURIComponent(ptoken) : ''));
      if (window.Attachments && window.Attachments.download) { window.Attachments.download(dlUrl, fileName); }
      else { window.open(dlUrl, '_blank', 'noopener'); }
    });
  }
  document.getElementById('previewBtn').addEventListener('click', function(){
    // v2.43.6 按类型分流（复用 lightbox.js openPreview）——PDF/DOCX/XLSX→office-preview 整页渲染、
    // 图片→lightbox、DOC/XLS→下载兜底；此前 docx 直跳 PDF.js 会「文档加载失败」
    window.openPreview(fileUrl, ext, fileName, ptoken);
  });
})();
<?php endif; ?>
</script>
<?php // 移动端共享尾部：底部导航栏（与合同/客户等详情页一致，避免进入详情后底部菜单消失）
// v2.43.5 补丁：引入 lightbox.js 供 openPreview 按类型分流预览（PDF/图片/Office）
?><script src="<?=asset_url('js/mobile/lightbox.js')?>"></script>
<?php include __DIR__ . '/_foot.php'; ?>
