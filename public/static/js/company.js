/* ========================================================================
 * 公司主体（签约主体）管理页交互（v2.28.2：从 app/view/company/index.php 抽离）
 * 依赖：全局 fetch / bootstrap.Modal / showToast（由 layout/footer.php 的 app.js 提供）
 * ====================================================================== */

// 打开新增/编辑模态框：id 为空=新增，非空=从表格行 data-* 属性回填字段
function openForm(id){
  var f = document.getElementById('companyForm'); f.reset();
  document.getElementById('f_id').value = id || 0;
  document.getElementById('formTitle').textContent = id ? '编辑主体' : '新增主体';
  if(id){
    var tr = document.querySelector('tr[data-id="'+id+'"]');
    if(tr){
      document.getElementById('f_name').value = tr.dataset.name;
      document.getElementById('f_short').value = tr.dataset.short;
      document.getElementById('f_code').value = tr.dataset.code;
      document.getElementById('f_rate').value = tr.dataset.rate || '0.06';
      document.getElementById('f_default').checked = tr.dataset.default === '1';
    }
  }
  new bootstrap.Modal('#formModal').show();
}

// 表单提交：保存公司主体（新增/编辑统一接口）
document.getElementById('companyForm').addEventListener('submit', function(e){
  e.preventDefault();
  var fd = new FormData(this);
  fetch('/ajax/company/save', {method:'POST', body:fd, headers:{'X-Requested-With':'XMLHttpRequest'}}).then(function(r){return r.json();}).then(function(res){
    showToast(res.msg || '操作完成', res.code===0?'success':'error');
    if(res.code===0) location.reload();
  });
});

// 删除公司主体（默认主体禁止删除）
function del(id, isDefault){
  if(isDefault){ showToast('默认主体不可删除，请先设置其他主体为默认。', 'error'); return; }
  pcConfirm({ message: '确定删除该主体？关联合同将解除签约主体。', danger: true }).then(function(ok){
    if(!ok) return;
    fetch('/ajax/company/delete', {method:'POST', body:new URLSearchParams({id:id}), headers:{'X-Requested-With':'XMLHttpRequest'}}).then(function(r){return r.json();}).then(function(res){
      showToast(res.msg||'操作完成', res.code===0?'success':'error'); if(res.code===0) location.reload();
    });
  });
}
