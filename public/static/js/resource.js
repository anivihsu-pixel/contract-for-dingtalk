/* ========================================================================
 * 资料库列表页交互（v2.28.2 抽离；v2.34.x 增加开票资料结构化字段录入/查看/复制）
 * 依赖：全局 fetch / bootstrap.Modal / showToast（由 layout/footer.php 的 app.js 提供）
 * 桥接：视图通过 <script>window.__RES_CAN_UPLOAD / __RES_CAN_EDIT / __RES_CAN_DELETE</script> 注入权限标志
 * ====================================================================== */

// 当前选中的分类筛选（空串=全部）
var __resCat = '';
// v2.43.6：上传/编辑/删除独立权限标志（由视图注入，取代原 __RES_CAN_MANAGE）
var __canUpload = window.__RES_CAN_UPLOAD || false;
var __canEdit   = window.__RES_CAN_EDIT || false;
var __canDelete = window.__RES_CAN_DELETE || false;
// id => 整条资料（含 content），供查看字段弹窗按 id 读取
var __resData = {};

// 开票资料结构化字段中文标签（与后端 ResourceLogic::$INVOICE_FIELDS 保持一致）
var __INVOICE_LABELS = {
    unit_name: '单位名称',
    tax_no: '纳税人识别号',
    bank_name: '开户行',
    account_no: '账号',
    address: '地址',
    tel: '电话'
};

// 按分类筛选资料
function filterRes(cat){
  __resCat = cat;
  document.querySelectorAll('#resFilterBar .btn').forEach(function(b){ b.classList.toggle('active', b.dataset.cat===cat); });
  loadRes();
}

// ===== 分页状态（v2.39.x：后端分页 + 「加载更多」） =====
var __resPage = 1;          // 当前已加载到的页码
var __resTotal = 0;         // 后端返回的总条数
var __resPageSize = 20;     // 每页条数（与后端 page_size 对齐）
var __resLoading = false;   // 防重复请求标志

// 刷新「加载更多」按钮显隐与文案：已加载条数 < 总数 时显示
function updateLoadMore(){
  var wrap = document.getElementById('resLoadMoreWrap');
  if (!wrap) return;
  var loaded = __resPage * __resPageSize;
  wrap.classList.toggle('d-none', __resTotal <= loaded);
  var btn = document.getElementById('resLoadMore');
  if (btn) {
    btn.disabled = __resLoading;
    btn.innerHTML = __resLoading ? '加载中...' : '加载更多';
  }
}

// 单张卡片 HTML（loadRes / loadMoreRes 共用）
function cardHtml(r){
  var icons = {pdf:'bi-file-pdf text-danger', doc:'bi-file-word text-primary', docx:'bi-file-word text-primary', xls:'bi-file-excel text-success', xlsx:'bi-file-excel text-success', jpg:'bi-file-image text-warning', png:'bi-file-image text-warning', gif:'bi-file-image text-warning', webp:'bi-file-image text-warning'};
  var ext = (r.file_name ? r.file_name.split('.').pop() : '').toLowerCase();
  var icon = icons[ext] || 'bi-file-earmark text-secondary';
  var isInvoice = r.category === 'INVOICE';
  var contentObj = (isInvoice && r.content) ? safeParse(r.content) : null;
  // 卡片整体可点击打开详情（v2.43.5 补丁：此前仅开票资料卡片可点弹字段、普通资料无反应）
  var h = '<div class="col-md-6 col-lg-4"><div class="card h-100" data-id="'+r.id+'" style="cursor:pointer" onclick="location.href=\'/resource/'+r.id+'\'">';
  h += '<div class="card-body" style="position:relative">';
  if(__canDelete) h += '<button type="button" class="res-del-btn" title="删除" aria-label="删除" onclick="event.stopPropagation();delRes('+r.id+')"><i class="bi bi-trash"></i></button>';
  h += '<div class="d-flex align-items-center gap-2 mb-2" style="padding-right:22px"><i class="bi '+icon+' fs-3"></i><div><div class="fw-bold small">'+esc(r.title)+'</div><span class="pc-tag pc-tag-muted" style="font-size:10px">'+esc(r.category_name)+'</span>'+(r.company_name?' <span class="pc-tag pc-tag-info" style="font-size:10px">'+esc(r.company_name)+'</span>':'')+'</div></div>';
  // 结构化字段摘要优先于 description 展示
  if (contentObj) {
    h += '<div class="small text-muted mb-2" style="min-height:38px"><b>'+esc(contentObj.unit_name||'')+'</b>'+(contentObj.tax_no?'<br>税号：'+esc(contentObj.tax_no):'')+'</div>';
  } else if(r.description) {
    h += '<div class="small text-muted mb-2" style="min-height:38px">'+esc(r.description)+'</div>';
  }
  h += '</div></div></div>';
  return h;
}

// 加载第一页（筛选/上传成功后重置页码）
function loadRes(){
  __resPage = 1;
  __resLoading = true;
  updateLoadMore();
  var url = '/ajax/resource/list?page=1&page_size=' + __resPageSize + (__resCat ? '&category='+__resCat : '');
  $ajax(url, {loading:false}).then(function(res){
    var box = document.getElementById('resGrid');
    __resData = {};
    __resLoading = false;
    // /ajax/resource/list 返回 data: {list, total, page, page_size}，需取 data.list（历史 bug：直接遍历 data 导致永远「暂无资料」）
    var arr = (res.data && res.data.list) || [];
    __resTotal = (res.data && res.data.total) || 0;
    if(res.code !== 0 || !arr.length){
      // 空态：网格容器使用 emptyState 同款视觉（col-12 版，CTA 按管理权限显隐）
      var cta = __canUpload
        ? '<a href="javascript:void(0)" onclick="openUploadModal()" class="btn btn-primary btn-sm mt-2"><i class="bi bi-cloud-upload"></i> 上传资料</a>'
        : '<div class="small text-muted mt-2">无上传权限，请联系管理员</div>';
      box.innerHTML = '<div class="col-12 text-center py-5 text-muted">'
        + '<i class="bi bi-folder2-open" style="font-size:2rem"></i>'
        + '<div class="mt-2 fw-semibold">暂无资料</div>'
        + '<div class="small">合同范本 / 开票资料 / 标准条款等参考资料集中管理</div>'
        + cta + '</div>';
      updateLoadMore(); return;
    }
    var h = '';
    arr.forEach(function(r){ __resData[r.id] = r; h += cardHtml(r); });
    box.innerHTML = h;
    updateLoadMore();
  }).catch(function(){
    // 加载失败：错误已由 $ajax 统一 toast，网格内展示重试入口（避免"加载中"永驻）
    __resLoading = false;
    updateLoadMore();
    var box = document.getElementById('resGrid');
    if(box) box.innerHTML = '<div class="col-12 text-center py-5 text-muted"><i class="bi bi-exclamation-triangle" style="font-size:2rem"></i><div class="mt-2">加载失败，请检查网络后重试</div><button type="button" class="btn btn-sm btn-outline-secondary mt-2" id="resRetryBtn"><i class="bi bi-arrow-clockwise"></i> 重新加载</button></div>';
    var rb = document.getElementById('resRetryBtn');
    if(rb) rb.addEventListener('click', function(){ loadRes(); });
  });
}

// 「加载更多」：请求下一页并追加到网格尾部
function loadMoreRes(){
  if (__resLoading) return;
  var next = __resPage + 1;
  __resLoading = true;
  updateLoadMore();
  var url = '/ajax/resource/list?page=' + next + '&page_size=' + __resPageSize + (__resCat ? '&category='+__resCat : '');
  $ajax(url, {loading:false}).then(function(res){
    __resLoading = false;
    var arr = (res.data && res.data.list) || [];
    if (res.code !== 0 || !arr.length) { updateLoadMore(); return; }
    __resPage = next;
    __resTotal = (res.data && res.data.total) || __resTotal;
    var box = document.getElementById('resGrid');
    var h = '';
    arr.forEach(function(r){ __resData[r.id] = r; h += cardHtml(r); });
    box.insertAdjacentHTML('beforeend', h);
    updateLoadMore();
  }).catch(function(){
    // 加载失败：释放防重复请求标志（错误已由 $ajax 统一 toast）
    __resLoading = false;
    updateLoadMore();
  });
}

// HTML 转义：统一使用 app.js / mobile-common.js 全局 esc（P3-5：移除本地重复副本）
// 安全解析 JSON 字符串为对象（失败返回 null）
function safeParse(s){ try { var o = JSON.parse(s); return (o && typeof o==='object') ? o : null; } catch(e){ return null; } }

// 上传弹窗：重置表单并联动分类字段
function openUploadModal(){ document.getElementById('uploadForm').reset(); toggleCategoryFields(); new bootstrap.Modal('#uploadModal').show(); }
// 分类联动：开票资料显示「关联主体」与「结构化字段」，并放宽文件必填
function toggleCategoryFields(){
  var c = document.getElementById('upCategory').value;
  var isInvoice = (c === 'INVOICE');
  document.getElementById('companyField').style.display = isInvoice ? 'block' : 'none';
  document.getElementById('invoiceFields').style.display = isInvoice ? 'block' : 'none';
  var fileInput = document.getElementById('upFile');
  var fileReq = document.getElementById('fileReq');
  if (isInvoice) {
    fileInput.removeAttribute('required');
    if (fileReq) fileReq.style.display = 'none';
  } else {
    fileInput.setAttribute('required', 'required');
    if (fileReq) fileReq.style.display = '';
  }
}

// 上传表单提交：仅当表单存在（即有管理权限）时绑定
var __uploadForm = document.getElementById('uploadForm');
if(__uploadForm){
  __uploadForm.addEventListener('submit', function(e){
    e.preventDefault();
    var fd = new FormData(this);
    // 开票资料：将结构化字段收集为 JSON 放入 content（仅收集非空项）
    if (document.getElementById('upCategory').value === 'INVOICE') {
      var fields = {};
      Object.keys(__INVOICE_LABELS).forEach(function(k){
        var el = document.querySelector('[name=f_'+k+']');
        var v = el ? el.value.trim() : '';
        if (v !== '') fields[k] = v;
      });
      fd.append('content', JSON.stringify(fields));
      var hasFile = document.getElementById('upFile').files.length > 0;
      if (!hasFile && Object.keys(fields).length === 0) {
        showToast('请上传文件或填写开票资料字段', 'error');
        return;
      }
    }
    $ajax('/ajax/resource/save', {method:'POST', body:fd, loading:false}).then(function(res){
      showToast(res.msg || '操作完成', res.code===0?'success':'error');
      if(res.code===0){ bootstrap.Modal.getInstance('#uploadModal').hide(); loadRes(); }
    }).catch(function(){});
  });
}

function delRes(id){ pcConfirm({ message: '确定删除该资料？', danger: true }).then(function(ok){ if(!ok) return; $ajax('/ajax/resource/delete', {method:'POST', body:new URLSearchParams({id:id}), loading:false}).then(function(res){ showToast(res.msg||'操作完成', res.code===0?'success':'error'); if(res.code===0) loadRes(); }).catch(function(){}); }); }

// 首次加载资料列表（DCL 防护：确保 app.js 全局 $ajax/esc 就绪）
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', loadRes);
} else {
    loadRes();
}
