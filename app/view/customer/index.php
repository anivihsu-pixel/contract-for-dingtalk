<?php $title='客户管理'; $menu_active='customer'; include __DIR__.'/../layout/header.php'; ?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2"><h4><i class="bi bi-people"></i> 客户管理</h4><div class="d-flex gap-2"><?php if(!empty($user['is_admin'])): ?><button class="btn btn-outline-warning btn-sm" onclick="showDuplicates()"><i class="bi bi-exclamation-triangle"></i> 查重</button><?php endif; ?><?php if(!empty($can_create_customer)): ?><a href="/customer/create" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> 新增客户</a><?php endif; ?></div></div>
<!-- M10 客户生命周期漏斗看板（v2.38.11：移动到客户列表上方——漏斗是全局概览，先看分布再看明细） -->
<?php
$funnelStages = ['POTENTIAL','ACTIVE'];
$funnelData = $funnel['stages'] ?? ['POTENTIAL'=>0,'ACTIVE'=>0];
$funnelAmts = $funnel['amounts'] ?? ['POTENTIAL'=>0,'ACTIVE'=>0];
$funnelMax = max(1, max(array_values($funnelData)));
$stageColors = ['POTENTIAL'=>'#0b5ed7','ACTIVE'=>'#07c160'];
?>
<div class="card mb-3" id="lifecycleFunnel">
  <!-- 漏斗卡片自带阶段标签与数字（客户/成交） -->
  <div class="card-body">
    <div class="d-flex flex-wrap gap-3">
      <?php foreach($funnelStages as $st):
        $cnt = (int)($funnelData[$st] ?? 0);
        $amt = (float)($funnelAmts[$st] ?? 0);
        $pct = $funnelMax > 0 ? round($cnt / $funnelMax * 100) : 0;
        $label = ($lifecycle_dict[$st] ?? $st);
      ?>
      <!-- v2.38.9：漏斗阶段可点击筛选（data-lifecycle 供 customer.js 绑定） -->
      <div class="flex-fill lc-stage" style="min-width:130px;cursor:pointer" data-lifecycle="<?=$st?>" title="点击筛选「<?=htmlspecialchars($label)?>」客户">
        <div class="small text-muted mb-1"><?=htmlspecialchars($label)?> <span class="text-muted small">(<?=$cnt?>)</span></div>
        <div class="fw-bold" style="font-size:22px;color:<?=$stageColors[$st]?>"><?=$cnt?></div>
        <!-- v2.40.0 P1-7：漏斗金额维度——该阶段客户销售合同额合计 -->
        <div class="small text-muted mt-1"><?=$amt > 0 ? '¥'.number_format($amt,0) : '—'?></div>
        <div class="progress mt-1" style="height:8px;background:var(--bg-page)">
          <div class="progress-bar" style="width:<?=$pct?>%;background:<?=$stageColors[$st]?>" role="progressbar"></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <!-- v2.38.11：去除「当前筛选」栏——选中态（浅蓝底）即筛选指示，再次点击已选中阶段取消筛选（见 customer.js bindLcFilter） -->
  </div>
</div>
<!-- 客户列表（v2.38.11：移动到漏斗下方——漏斗提供全局概览与筛选入口，列表承载明细） -->
<div class="card stat-card"><div class="table-responsive"><table class="table table-hover mb-0" id="customerTable"><thead class="table-light"><tr><th>名称</th><th>联系人</th><th>手机</th><th>行业</th><th>生命周期</th><th>归属</th><th>状态</th><th>操作</th></tr></thead><tbody id="tableBody"><tr><td colspan="8" class="text-center py-4"><div class="spinner-border spinner-border-sm text-muted"></div> <span class="text-muted small">加载中...</span></td></tr></tbody></table></div><div class="card-footer bg-white" id="pagination"></div></div>
<script>window._canCreateCustomer=<?=!empty($can_create_customer)?'true':'false'?>;window._lifecycleDict=<?=json_encode($lifecycle_dict??[],JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;window._industryDict=<?=json_encode(dict('customer_industry'),JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;window._lifecycleActive='';window._mySharedIds=<?=json_encode(array_map('intval',$my_shared_ids??[]),JSON_UNESCAPED_UNICODE)?>;window._mySharedOutIds=<?=json_encode(array_map('intval',$my_shared_out_ids??[]),JSON_UNESCAPED_UNICODE)?>;window._myUserId=<?=(int)($user['id']??0)?>;window._isAdmin=<?=!empty($is_super_admin)?'true':'false'?>;window._shareTargetOptions=<?=json_encode(array_map(function($u){return ['id'=>(int)$u['id'],'name'=>$u['name']];},$share_target_options??[]),JSON_UNESCAPED_UNICODE)?>;window._shareDepartments=<?=json_encode(array_map(function($d){return ['id'=>(int)$d['id'],'name'=>$d['name']];},$share_departments??[]),JSON_UNESCAPED_UNICODE)?>;</script>
<script src="<?=asset_url('js/customer.js')?>"></script>
<style>
@media (max-width:768px){
  body{padding-bottom:calc(56px + env(safe-area-inset-bottom,0px));}
  .m-tabbar{position:fixed;left:0;right:0;bottom:0;height:calc(56px + env(safe-area-inset-bottom,0px));padding-bottom:env(safe-area-inset-bottom,0px);background:#fff;border-top:1px solid var(--line);display:flex;z-index:60;}
  .m-tabbar a{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:2px;color:var(--text-3);font-size:11px;text-decoration:none;min-height:44px;}
  .m-tabbar a.active{color:var(--primary);}
  .m-tabbar a i{font-size:21px;}
  #customerTable thead{display:none}
  .c-card{background:#fff;border:1px solid var(--line);border-radius:12px;padding:12px 14px;box-shadow:0 1px 3px rgba(0,0,0,.05);}
  .c-card:active{background:#f7f8fa;}
  .c-card-top{display:flex;align-items:center;justify-content:space-between;gap:8px;}
  .c-card-t{font-size:15px;font-weight:600;color:var(--text-main);flex:1;min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
  .c-card-meta{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-top:8px;}
  .c-card-contact{font-size:13px;color:#646a73;display:flex;align-items:center;gap:3px;}
  .c-card-contact i{font-size:12px;}
  .c-tag{display:inline-block;font-size:12px;padding:2px 7px;border-radius:6px;line-height:1.5;}
  .c-tag-ok{background:#e6f7e6;color:var(--success);}
  .c-tag-muted{background:var(--bg-page);color:var(--text-3);}
  .c-tag-info{background:var(--brand-light);color:var(--primary);}
  .c-empty{padding:48px 0;text-align:center;color:var(--text-3);font-size:14px;}
  .c-empty small{font-size:12px;}
}
@media (min-width:769px){ .m-tabbar{display:none!important;} }
</style>
<?=mobile_tabbar('customer')?>
<?php include __DIR__.'/../layout/footer.php'; ?>

<!-- v2.47.8：列表快捷共享弹层（负责人/超管；复用 /ajax/customer/{id}/share-list、share、unshare） -->
<div class="modal fade" id="listShareModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <div class="modal-header"><h6 class="modal-title" id="listShareTitle">共享设置</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <div class="text-muted small mb-2">共享成员可查看本客户并关联合同（只读），不可编辑档案；撤销后不再可见。</div>
    <table class="table table-sm mb-2"><thead><tr><th>共享对象</th><th>类型</th><th>级别</th><th>操作</th></tr></thead><tbody id="listShareList"><tr><td colspan="4" class="text-center text-muted py-3">加载中…</td></tr></tbody></table>
    <div id="listShareAddBox" style="display:none;border-top:1px dashed #e8eaed;padding-top:10px">
      <div class="d-flex gap-2 mb-2">
        <select id="listShareType" class="form-select form-select-sm" style="width:90px">
          <option value="USER">用户</option><option value="DEPT">部门</option>
        </select>
        <div class="position-relative" id="listShareUserWrap" style="flex:1">
          <input type="text" id="listShareSearch" class="form-control form-control-sm" placeholder="搜索姓名（全公司）…" autocomplete="off">
          <div id="listShareUserList" class="position-absolute list-group" style="z-index:20;width:100%;max-height:180px;overflow:auto;display:none"></div>
        </div>
        <div id="listShareDeptWrap" style="flex:1;display:none">
          <select id="listShareDept" class="form-select form-select-sm" style="width:100%"></select>
        </div>
      </div>
      <button type="button" class="btn btn-sm btn-primary" id="listShareAddBtn"><i class="bi bi-plus-lg"></i> 添加共享</button>
    </div>
  </div>
</div></div></div>

<!-- 查重/合并弹窗（v2.38.2，仅管理员可见） -->
<?php if(!empty($user['is_admin'])): ?>
<div class="modal fade" id="dupModal" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title"><i class="bi bi-exclamation-triangle text-warning"></i> 可能重复的客户</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body" id="dupBody"></div>
</div></div></div>
<script>
function showDuplicates(){
  var body = document.getElementById('dupBody');
  body.innerHTML = '<div class="text-center py-4"><div class="spinner-border spinner-border-sm text-muted"></div> 扫描中…</div>';
  new bootstrap.Modal('#dupModal').show();
  $ajax('/ajax/customer/duplicates', {loading:false}).then(function(res){
    var pairs = res.data || [];
    if(!pairs.length){
      body.innerHTML = '<div class="text-center py-4 text-muted"><i class="bi bi-check-circle text-success fs-3"></i><p class="mt-2">未发现重复客户</p></div>';
      return;
    }
    var h = '<div class="small text-muted mb-3">共发现 <strong>' + pairs.length + '</strong> 对可能重复的客户</div>';
    pairs.forEach(function(p, i){
      h += '<div class="border rounded p-3 mb-2"><div class="d-flex justify-content-between align-items-start mb-2">'
        + '<div><span class="pc-tag pc-tag-warn me-2">' + escHtml(p.reason) + '</span></div></div>'
        + '<div class="row g-2 align-items-center"><div class="col-5"><strong>' + escHtml(p.a.name) + '</strong><br><small class="text-muted">#' + p.a.id + ' | ' + (escHtml(p.a.contact_name)||'-') + ' | 归属用户' + (escHtml(p.a.owner_id)||'-') + '</small></div>'
        + '<div class="col-2 text-center"><i class="bi bi-arrow-left-right"></i><br><span class="pc-tag pc-tag-muted">合并</span></div>'
        + '<div class="col-5"><strong>' + escHtml(p.b.name) + '</strong><br><small class="text-muted">#' + p.b.id + ' | ' + (escHtml(p.b.contact_name)||'-') + ' | 归属用户' + (escHtml(p.b.owner_id)||'-') + '</small></div></div>'
        + '<div class="mt-2"><button type="button" class="btn btn-sm btn-outline-danger" data-mid="' + p.a.id + '" data-tid="' + p.b.id + '" data-ma="' + escHtml(p.a.name) + '" data-tb="' + escHtml(p.b.name) + '" onclick="doMerge(this)">合并：' + escHtml(p.b.name) + ' → ' + escHtml(p.a.name) + '</button></div></div>';
    });
    body.innerHTML = h;
  });
}
// 客户名经 data 属性传递（escHtml 转义），杜绝 onclick 字符串内插注入 JS（H1 修复：审计发现存储型 XSS）
function doMerge(btn){
  var mid = parseInt(btn.getAttribute('data-mid'), 10);
  var tid = parseInt(btn.getAttribute('data-tid'), 10);
  var ma = btn.getAttribute('data-ma') || '';
  var tb = btn.getAttribute('data-tb') || '';
  if(!mid || !tid){ showToast('参数错误', 'error'); return; }
  pcConfirm({title:'确认合并客户', message:'确认将客户「' + tb + '」合并到「' + ma + '」？\n\n合并后：\n• ' + tb + ' 的合同、跟进记录将归入 ' + ma + '\n• ' + tb + ' 将标记为已删除\n• 合同归属不变，仍可正常查看\n\n此操作不可撤销！', danger:true}).then(function(ok){ if(!ok) return;
  $ajax('/ajax/customer/merge', {method:'POST', body:'master_id=' + mid + '&target_id=' + tid, loadingText:'合并中…'}).then(function(res){
    showToast(res.msg || '操作完成', res.code===0 ? 'success' : 'error');
    if(res.code === 0) setTimeout(function(){ location.reload(); }, 1200);
  });
  });
}
</script>
<?php endif; ?>

