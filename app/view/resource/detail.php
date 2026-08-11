<?php
// +----------------------------------------------------------------------
// | PC 资料详情页（v2.43.5 补丁：列表卡片点击打开；此前仅移动端有详情）
// | 展示：基本信息 / 开票资料字段 / 附件（预览+下载，令牌免登录）/ 说明 / 创建信息
// +----------------------------------------------------------------------
$title='资料详情'; $menu_active='resource';
include __DIR__.'/../layout/header.php';
$item = $item ?? null;
$invoice_fields = $invoice_fields ?? [];
// v2.43.6：编辑/删除独立权限码（library:edit / library:delete），取代原 can_manage
$canEdit   = !empty($can_edit);
$canDelete = !empty($can_delete);
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
<h4><i class="bi bi-folder2-open"></i> 资料详情</h4>
<div class="d-flex gap-1 flex-wrap">
  <a href="/resource" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> 返回资料库</a>
  <?php if($canEdit): ?>
  <button type="button" class="btn btn-outline-primary btn-sm" onclick="openEditModal()"><i class="bi bi-pencil"></i> 编辑</button>
  <?php endif; ?>
  <?php if($canDelete): ?>
  <button type="button" class="btn btn-outline-danger btn-sm" onclick="delRes(<?=(int)$item['id']?>)"><i class="bi bi-trash"></i> 删除</button>
  <?php endif; ?>
</div></div>

<?php if(!empty($item)): $__fileUrl = $item['file_url'] ?? ''; $__fileName = $item['file_name'] ?? '';
$__ext = strtolower(pathinfo($__fileName, PATHINFO_EXTENSION));
$__isPdf = $__ext === 'pdf';
$__isImg = in_array($__ext, ['jpg','jpeg','png','gif','webp'], true);
// v2.43.6：docx/xlsx 前端渲染在线预览（docx-preview/SheetJS），与 pdf 同走 /m/office-preview
$__isOfficePreview = in_array($__ext, ['docx', 'xlsx'], true);
$__ptoken = $__fileUrl ? preview_token($__fileUrl) : '';
?>
<div class="row"><div class="col-lg-8">

<!-- 基本信息 -->
<div class="card stat-card mb-3"><div class="card-header bg-white"><h5 class="mb-0"><i class="bi bi-card-text me-1"></i>基本信息</h5></div><div class="card-body">
<table class="table table-sm mb-0"><tbody>
<tr><td class="text-muted" width="90">资料标题</td><td colspan="3"><strong><?=htmlspecialchars($item['title'])?></strong></td></tr>
<tr><td class="text-muted">分类</td><td><span class="pc-tag pc-tag-info"><?=htmlspecialchars($item['category_name'])?></span></td>
<td class="text-muted" width="90">关联主体</td><td><?=htmlspecialchars($item['company_name'] ?: '-')?></td></tr>
<?php if(!empty($item['description'])): ?>
<tr><td class="text-muted">说明</td><td colspan="3" style="white-space:pre-wrap"><?=nl2br(htmlspecialchars($item['description']))?></td></tr>
<?php endif; ?>
<tr><td class="text-muted">上传人</td><td><?=htmlspecialchars($item['creator_name'] ?? '-')?></td>
<td class="text-muted">创建时间</td><td><?=htmlspecialchars($item['created_at'] ?? '-')?></td></tr>
</tbody></table></div></div>

<!-- 开票资料结构化字段 -->
<?php if(!empty($item['content_arr'])): ?>
<div class="card stat-card mb-3"><div class="card-header bg-white d-flex justify-content-between align-items-center">
<h5 class="mb-0"><i class="bi bi-card-list me-1"></i><?=htmlspecialchars($item['category_name'])?>信息</h5>
<button type="button" class="btn btn-outline-primary btn-sm" onclick="copyInvoiceFields()"><i class="bi bi-clipboard"></i> 一键复制</button></div>
<div class="card-body">
<?php $__hasRow = false; foreach($invoice_fields as $k=>$label): if(isset($item['content_arr'][$k]) && $item['content_arr'][$k] !== ''): $__hasRow = true; ?>
<div class="row border-bottom py-1"><div class="col-4 text-muted small"><?=htmlspecialchars($label)?></div><div class="col-8"><?=htmlspecialchars($item['content_arr'][$k])?></div></div>
<?php endif; endforeach; if(!$__hasRow): ?><div class="text-muted small">暂无结构化字段</div><?php endif; ?>
</div></div>
<?php endif; ?>

<!-- 附件 -->
<?php if($__fileUrl): ?>
<div class="card stat-card mb-3"><div class="card-header bg-white"><h5 class="mb-0"><i class="bi bi-paperclip me-1"></i>附件</h5></div><div class="card-body">
<div class="d-flex align-items-center gap-2 flex-wrap">
  <i class="bi bi-file-earmark-<?=$__isPdf?'pdf':($__isImg?'image':'text')?> fs-3 <?=$__isPdf?'text-danger':($__isImg?'text-warning':'text-primary')?>"></i>
  <div>
    <div class="fw-semibold small"><?=htmlspecialchars($__fileName)?></div>
    <?php if(!empty($item['file_size'])): ?><div class="text-muted small"><?=round($item['file_size']/1024,1)?> KB</div><?php endif; ?>
  </div>
  <div class="ms-auto d-flex gap-1">
    <?php if($__isPdf || $__isOfficePreview): ?>
    <a class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener" href="/m/office-preview?p=<?=urlencode($__fileUrl)?>&t=<?=urlencode($__ptoken)?>&name=<?=urlencode($__fileName)?>"><i class="bi bi-eye"></i> 预览</a>
    <?php elseif($__isImg): ?>
    <!-- v2.43.7 修复：图片预览改 Bootstrap Modal 内嵌（原 target=_blank 新标签在钉钉 PC 内嵌浏览器被拦截 → 「点击预览没反应」；与合同详情 PC 端 Modal 预览口径一致） -->
    <button type="button" class="btn btn-sm btn-outline-primary" onclick="openImgPreview()"><i class="bi bi-eye"></i> 预览</button>
    <?php else: ?>
    <span class="text-muted small align-self-center">该格式浏览器不支持在线预览，请下载后查看</span>
    <?php endif; ?>
    <a class="btn btn-sm btn-outline-secondary" href="/preview?p=<?=urlencode($__fileUrl)?>&t=<?=urlencode($__ptoken)?>" download="<?=htmlspecialchars($__fileName)?>"><i class="bi bi-download"></i> 下载</a>
  </div>
</div>
</div></div>
<?php endif; ?>

</div></div>

<!-- v2.43.6：编辑弹窗（上传后二次编辑；文件可选替换，替换时后端删除旧物理文件） -->
<?php if($canEdit): $__cats = $categories ?? []; $__comps = $companies ?? []; ?>
<div class="modal fade" id="editModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
<div class="modal-header"><h5 class="modal-title"><i class="bi bi-pencil me-1"></i> 编辑资料</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
<form id="editForm">
<div class="modal-body">
  <div class="mb-2"><label class="form-label" for="eResTitle">资料标题 <span class="text-danger">*</span></label><input type="text" name="title" id="eResTitle" class="form-control" required placeholder="如：媒体投放服务合同范本"></div>
  <div class="mb-2"><label class="form-label" for="eCategory">分类</label><select name="category" class="form-select" id="eCategory" onchange="toggleEditCategory()">
    <?php foreach($__cats as $k=>$n): ?><option value="<?=$k?>"><?=$n?></option><?php endforeach; ?>
  </select></div>
  <div class="mb-2" id="eCompanyField" style="display:none"><label class="form-label" for="eCompany">关联主体（开票资料归属）</label><select name="company_id" id="eCompany" class="form-select">
    <option value="0">- 不关联 -</option>
    <?php foreach($__comps as $c): ?><option value="<?=$c['id']?>"><?=htmlspecialchars($c['name'])?></option><?php endforeach; ?>
  </select></div>
  <div class="mb-2" id="eInvoiceFields" style="display:none">
    <label class="form-label">开票资料字段（结构化录入）</label>
    <div class="border rounded p-2 bg-light">
      <div class="row g-2">
        <div class="col-6"><input class="form-control form-control-sm" name="f_unit_name" placeholder="单位名称 *"></div>
        <div class="col-6"><input class="form-control form-control-sm" name="f_tax_no" placeholder="纳税人识别号 *"></div>
        <div class="col-6"><input class="form-control form-control-sm" name="f_bank_name" placeholder="开户行"></div>
        <div class="col-6"><input class="form-control form-control-sm" name="f_account_no" placeholder="账号"></div>
        <div class="col-12"><input class="form-control form-control-sm" name="f_address" placeholder="地址"></div>
        <div class="col-12"><input class="form-control form-control-sm" name="f_tel" placeholder="电话"></div>
      </div>
    </div>
  </div>
  <div class="mb-2"><label class="form-label" for="eFile">替换文件（选填，不选保留原文件）</label><input type="file" id="eFile" name="file" class="form-control" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png"><div class="form-text">当前文件：<?=htmlspecialchars($__fileName ?: '（无）')?>；支持 PDF/Word/Excel/图片，单个最大 20MB</div></div>
  <div class="mb-2"><label class="form-label" for="eDesc">说明</label><textarea name="description" id="eDesc" class="form-control" rows="2" placeholder="用途、注意事项等"></textarea></div>
</div>
<div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">取消</button><button class="btn btn-primary" type="submit"><i class="bi bi-check2"></i> 保存</button></div>
</form></div></div></div>
<?php endif; ?>

<!-- v2.43.7：图片预览 Modal（PC 端内嵌放大，防钉钉 PC 内嵌浏览器新标签拦截 → 预览没反应） -->
<?php if($__isImg && $__fileUrl): ?>
<div class="modal fade" id="imgPreviewModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-xl"><div class="modal-content">
    <div class="modal-header d-flex align-items-center gap-2">
      <h6 class="modal-title mb-0 text-truncate"><i class="bi bi-image text-primary me-1"></i><?=htmlspecialchars($__fileName)?></h6>
      <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal" aria-label="Close"></button>
    </div>
    <div class="modal-body p-0" id="imgPreviewBody" style="min-height:50vh;max-height:80vh;background:#1a1a1a;display:flex;align-items:center;justify-content:center;position:relative;overflow:auto;"></div>
  </div></div>
</div>
<?php endif; ?>

<script>
<?php if($canEdit): ?>
// v2.43.6：编辑弹窗数据源（PHP 渲染时注入，与 index.php 上传弹窗字段对齐）
var __EDIT_ITEM = <?=json_encode([
    'id'           => (int)$item['id'],
    'title'        => $item['title'] ?? '',
    'category'     => $item['category'] ?? 'OTHER',
    'company_id'   => (int)($item['company_id'] ?? 0),
    'description'  => $item['description'] ?? '',
    'content_arr'  => $item['content_arr'] ?? [],
], JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;
var __INVOICE_KEYS = ['unit_name','tax_no','bank_name','account_no','address','tel'];
function toggleEditCategory(){
  var c = document.getElementById('eCategory').value;
  var isInvoice = (c === 'INVOICE');
  document.getElementById('eCompanyField').style.display = isInvoice ? 'block' : 'none';
  document.getElementById('eInvoiceFields').style.display = isInvoice ? 'block' : 'none';
}
function openEditModal(){
  document.getElementById('eResTitle').value = __EDIT_ITEM.title || '';
  document.getElementById('eCategory').value = __EDIT_ITEM.category || 'OTHER';
  document.getElementById('eCompany').value = __EDIT_ITEM.company_id || 0;
  document.getElementById('eDesc').value = __EDIT_ITEM.description || '';
  document.getElementById('eFile').value = '';
  var arr = __EDIT_ITEM.content_arr || {};
  __INVOICE_KEYS.forEach(function(k){
    var el = document.querySelector('#editForm [name=f_' + k + ']');
    if (el) el.value = arr[k] !== undefined && arr[k] !== null ? arr[k] : '';
  });
  toggleEditCategory();
  new bootstrap.Modal('#editModal').show();
}
(function(){
  var form = document.getElementById('editForm');
  if (!form) return;
  form.addEventListener('submit', function(e){
    e.preventDefault();
    var fd = new FormData();
    fd.append('id', __EDIT_ITEM.id);
    fd.append('title', document.getElementById('eResTitle').value.trim());
    fd.append('category', document.getElementById('eCategory').value);
    fd.append('company_id', document.getElementById('eCompany').value);
    fd.append('description', document.getElementById('eDesc').value.trim());
    // 开票资料结构化字段 → content JSON（仅收集非空项）
    if (document.getElementById('eCategory').value === 'INVOICE') {
      var fields = {};
      __INVOICE_KEYS.forEach(function(k){
        var el = document.querySelector('#editForm [name=f_' + k + ']');
        var v = el ? el.value.trim() : '';
        if (v !== '') fields[k] = v;
      });
      fd.append('content', JSON.stringify(fields));
    }
    var fEl = document.getElementById('eFile');
    if (fEl.files && fEl.files.length) { fd.append('file', fEl.files[0]); }
    if (!fd.get('title')) { showToast('请填写资料标题', 'error'); return; }
    $ajax('/ajax/resource/update', {method:'POST', body:fd, loading:false}).then(function(res){
      showToast(res.msg || '操作完成', res.code === 0 ? 'success' : 'error');
      if (res.code === 0) { setTimeout(function(){ location.reload(); }, 600); }
    }).catch(function(){});
  });
})();
<?php endif; ?>
function copyInvoiceFields(){
  var lines = [];
  var labels = <?=json_encode($invoice_fields, JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;
  var data = <?=json_encode($item['content_arr'] ?? [], JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;
  Object.keys(labels).forEach(function(k){ if(data[k] !== undefined && data[k] !== '') lines.push(labels[k]+'：'+data[k]); });
  var text = lines.join('\n');
  if(!text){ showToast('暂无字段内容可复制', 'error'); return; }
  if(navigator.clipboard && navigator.clipboard.writeText){
    navigator.clipboard.writeText(text).then(function(){ showToast('已复制全部字段', 'success'); }, function(){ fallbackCopy(text); });
  }else{ fallbackCopy(text); }
}
function fallbackCopy(text){
  var ta = document.createElement('textarea'); ta.value = text; document.body.appendChild(ta); ta.select();
  try { document.execCommand('copy'); showToast('已复制全部字段', 'success'); } catch(e){ showToast('复制失败，请手动选择', 'error'); }
  document.body.removeChild(ta);
}
function delRes(id){
  pcConfirm({message:'确定删除该资料？', danger:true}).then(function(ok){
    if(!ok) return;
    $ajax('/ajax/resource/delete', {method:'POST', body:new URLSearchParams({id:id}), loading:false}).then(function(res){
      showToast(res.msg || '操作完成', res.code===0 ? 'success' : 'error');
      if(res.code===0) setTimeout(function(){ location.href = '/resource'; }, 600);
    }).catch(function(){});
  });
}
<?php if($__isImg && $__fileUrl): ?>
// v2.43.7：PC 端图片预览（Bootstrap Modal 内嵌，img 走带令牌 /preview 代理——与下载/office 预览同链路）
function openImgPreview(){
  var modalEl = document.getElementById('imgPreviewModal');
  var body = document.getElementById('imgPreviewBody');
  if(!modalEl || !body) return;
  var imgUrl = window.Attachments
    ? window.Attachments.normalizeUrl(<?=json_encode($__fileUrl, JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>, <?=json_encode($__ptoken, JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>)
    : ('/preview?p=' + encodeURIComponent(<?=json_encode($__fileUrl, JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>) + (<?=json_encode($__ptoken, JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?> ? '&t=' + encodeURIComponent(<?=json_encode($__ptoken, JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>) : ''));
  body.innerHTML = '<div class="text-white-50 text-center p-5"><div class="spinner-border spinner-border-sm mb-2"></div><div class="small">图片加载中…</div></div>';
  new bootstrap.Modal(modalEl).show();
  var img = new Image();
  img.style.maxWidth = '100%';
  img.style.maxHeight = '75vh';
  img.alt = <?=json_encode($__fileName, JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;
  img.onload = function(){ body.innerHTML = ''; body.appendChild(img); };
  img.onerror = function(){
    body.innerHTML = '<div class="text-white-50 text-center p-5"><i class="bi bi-exclamation-triangle fs-1"></i><div class="mt-2">图片加载失败，可能已被删除或链接无效</div><div class="mt-3"><a class="btn btn-sm btn-outline-light" href="' + imgUrl + '" target="_blank" rel="noopener">新窗口打开</a></div></div>';
  };
  img.src = imgUrl;
}
<?php endif; ?>
</script>
<?php else: ?>
<div class="text-center py-5 text-muted"><i class="bi bi-exclamation-circle fs-1"></i><div class="mt-2">资料不存在或已删除</div><a href="/resource" class="btn btn-outline-secondary btn-sm mt-3">返回资料库</a></div>
<?php endif; ?>
<?php include __DIR__.'/../layout/footer.php'; ?>
