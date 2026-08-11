<?php
$title='资料库'; $menu_active='resource';
include __DIR__.'/../layout/header.php';
// v2.43.6：上传/编辑/删除拆分独立权限码（library:upload/edit/delete），取代原 library:manage
$__canUpload = !empty($can_upload);
$__canEdit   = !empty($can_edit);
$__canDelete = !empty($can_delete);
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <h4><i class="bi bi-folder2-open"></i> 资料库</h4>
  <div class="d-flex gap-2">
    <div class="btn-group btn-group-sm" role="group" id="resFilterBar">
      <button type="button" class="btn btn-outline-secondary active" data-cat="" onclick="filterRes('')">全部</button>
      <?php foreach($categories as $k=>$n): ?><button type="button" class="btn btn-outline-secondary" data-cat="<?=$k?>" onclick="filterRes('<?=$k?>')"><?=$n?></button><?php endforeach; ?>
    </div>
    <?php if($__canUpload): ?><button class="btn btn-primary btn-sm" onclick="openUploadModal()"><i class="bi bi-cloud-upload"></i> 上传资料</button><?php endif; ?>
  </div>
</div>

<div class="text-muted small mb-2">合同范本 / 开票资料 / 标准条款等参考资料集中管理，供员工在合同拟定时查阅、下载、摘抄。</div>

<div id="resGrid" class="row g-3"><div class="col-12 text-center text-muted py-4">加载中...</div></div>
<!-- 加载更多（后端分页，v2.39.x）：已加载条数 < 总数 时由 resource.js 显示 -->
<div id="resLoadMoreWrap" class="text-center mt-3 d-none">
  <button id="resLoadMore" type="button" class="btn btn-outline-secondary btn-sm px-4" onclick="loadMoreRes()">加载更多</button>
</div>

<!-- 上传弹窗 -->
<?php if($__canUpload): ?>
<div class="modal fade" id="uploadModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
<div class="modal-header"><h5 class="modal-title"><i class="bi bi-cloud-upload me-1"></i> 上传资料</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
<form id="uploadForm">
<div class="modal-body">
  <div class="mb-2"><label class="form-label" for="fResTitle">资料标题 <span class="text-danger">*</span></label><input type="text" name="title" id="fResTitle" class="form-control" required placeholder="如：媒体投放服务合同范本"></div>
  <div class="mb-2"><label class="form-label" for="upCategory">分类</label><select name="category" class="form-select" id="upCategory" onchange="toggleCategoryFields()">
    <?php foreach($categories as $k=>$n): ?><option value="<?=$k?>"><?=$n?></option><?php endforeach; ?>
  </select></div>
  <div class="mb-2" id="companyField" style="display:none"><label class="form-label" for="fResCompany">关联主体（开票资料归属）</label><select name="company_id" id="fResCompany" class="form-select">
    <option value="0">- 不关联 -</option>
    <?php foreach($companies as $c): ?><option value="<?=$c['id']?>"><?=htmlspecialchars($c['name'])?></option><?php endforeach; ?>
  </select></div>
  <!-- 开票资料结构化字段：仅分类为「开票资料」时显示，可替代/补充上传文件 -->
  <div class="mb-2" id="invoiceFields" style="display:none">
    <label class="form-label" for="fResUnitName">开票资料字段（结构化录入）</label>
    <div class="border rounded p-2 bg-light">
      <div class="row g-2">
        <div class="col-6"><input class="form-control form-control-sm" id="fResUnitName" name="f_unit_name" placeholder="单位名称 *"></div>
        <div class="col-6"><input class="form-control form-control-sm" name="f_tax_no" placeholder="纳税人识别号 *"></div>
        <div class="col-6"><input class="form-control form-control-sm" name="f_bank_name" placeholder="开户行"></div>
        <div class="col-6"><input class="form-control form-control-sm" name="f_account_no" placeholder="账号"></div>
        <div class="col-12"><input class="form-control form-control-sm" name="f_address" placeholder="地址"></div>
        <div class="col-12"><input class="form-control form-control-sm" name="f_tel" placeholder="电话"></div>
      </div>
    </div>
  </div>
  <div class="mb-2"><label class="form-label" for="upFile">文件 <span id="fileReq" class="text-danger">*</span></label><input type="file" id="upFile" name="file" class="form-control" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png"><div class="form-text" id="fileHint">支持 PDF/Word/Excel/图片，单个最大 20MB；选「开票资料」时也可不传文件、直接填上方字段</div></div>
  <div class="mb-2"><label class="form-label" for="fResDesc">说明</label><textarea name="description" id="fResDesc" class="form-control" rows="2" placeholder="用途、注意事项等"></textarea></div>
</div>
<div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">取消</button><button class="btn btn-primary" type="submit"><i class="bi bi-upload"></i> 保存</button></div>
</form></div></div></div>
<?php endif; ?>

<script>window.__RES_CAN_UPLOAD = <?= $__canUpload ? 'true' : 'false' ?>; window.__RES_CAN_EDIT = <?= $__canEdit ? 'true' : 'false' ?>; window.__RES_CAN_DELETE = <?= $__canDelete ? 'true' : 'false' ?>;</script>
<script src="<?=asset_url('js/resource.js')?>"></script>
<?php include __DIR__.'/../layout/footer.php'; ?>
