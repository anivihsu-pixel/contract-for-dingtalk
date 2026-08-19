<?php $title='合同管理'; $menu_active='contract'; include __DIR__.'/../layout/header.php'; ?>
<style>
th.sortable{cursor:pointer;user-select:none}
th.sortable:hover{background:#eef2ff}
th.sorted-asc::after{content:" ▲";color:var(--primary);font-size:.75em}
th.sorted-desc::after{content:" ▼";color:var(--primary);font-size:.75em}
/* 移动端合同列表卡片（仅窄屏生效，桌面端保持原表格） */
@media (max-width: 768px){
  #contractTable thead{display:none;}
  #contractTable{font-size:14px;}
  .m-ccard{background:#fff;border:1px solid var(--line);border-radius:12px;padding:12px 14px;box-shadow:0 1px 3px rgba(0,0,0,.05);}
  .m-ccard:active{background:#f7f8fa;}
  .m-ccard-top{display:flex;align-items:center;justify-content:space-between;gap:8px;}
  .m-ccard-t{font-size:15px;font-weight:600;color:var(--text-main);flex:1;min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
  .m-ccard-no{font-size:12px;color:var(--text-3);margin-top:4px;}
  .m-ccard-meta{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-top:8px;}
  .m-ccard-party{font-size:13px;color:#646a73;margin-top:6px;}
  .m-ctag{display:inline-block;font-size:12px;padding:2px 7px;border-radius:6px;line-height:1.5;}
  .m-ctag-muted{background:var(--bg-page);color:var(--text-3);}
  .m-camt{font-size:15px;font-weight:700;margin-left:auto;}
  .m-amt-in{color:var(--danger);}
  .m-amt-out{color:var(--success);}
}
/* P1-7：列表首屏骨架屏（替换 spinner，消除空白感） */
.sk-row{display:flex;align-items:center;gap:14px;padding:14px 10px;border-bottom:1px solid #f0f1f3}
.sk{background:linear-gradient(90deg,var(--line) 25%,#e3e6ea 37%,var(--line) 63%);background-size:400% 100%;animation:sk-load 1.4s ease infinite;border-radius:4px}
.sk-w1{width:7%;height:14px}.sk-w2{width:24%;height:14px}.sk-w3{width:10%;height:14px}
.sk-w4{width:12%;height:14px}.sk-w5{width:16%;height:14px}.sk-w6{width:13%;height:14px}
@keyframes sk-load{0%{background-position:100% 0}100%{background-position:0 0}}
@media (prefers-reduced-motion:reduce){.sk{animation:none}}
</style>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
<h4><i class="bi bi-file-text"></i> 合同管理</h4>
<div class="d-flex gap-2">
<?php if(!empty($can_create_contract)): ?><a href="/contract/create" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> 新建合同</a><?php endif; ?>
<?php if(!empty($can_export)): ?><a href="javascript:void(0)" class="btn btn-outline-secondary btn-sm" onclick="exportContracts()"><i class="bi bi-download"></i> 导出</a><?php endif; ?></div></div>

<!-- REV-28：批量操作栏（默认隐藏，勾选后显示） -->
<div class="card stat-card mb-3" id="batchBar" style="display:none">
  <div class="card-body d-flex align-items-center gap-2 flex-wrap" style="padding:8px 14px">
    <span class="fw-bold me-2"><span id="batchCount">0</span> 项已选</span>
    <?php if(!empty($can_batch)): ?>
    <button class="btn btn-outline-danger btn-sm" onclick="batchArchive()"><i class="bi bi-archive"></i> 批量归档</button>
    <?php endif; ?>
    <?php if(!empty($can_delete)): ?>
    <button class="btn btn-outline-danger btn-sm" onclick="batchDelete()"><i class="bi bi-trash"></i> 批量删除</button>
    <?php endif; ?>
    <button class="btn btn-outline-secondary btn-sm" onclick="clearBatch()">取消选择</button>
  </div>
</div>

<div class="card stat-card mb-3"><div class="card-body"><form id="searchForm" class="row g-2">
<!-- P1-7：主行收敛为「关键词 + 状态 + 高级筛选 + 搜索」，其余 9 个条件移入下方抽屉，对齐移动端交互 -->
<div class="col-md-4 col-8"><input type="text" name="keyword" class="form-control form-control-sm" placeholder="搜索标题 / 合同号 / 关键词 / 概要" value="<?=htmlspecialchars($filter['keyword']??'')?>"></div>
<div class="col-md-3 col-4"><select name="status" class="form-select form-select-sm"><option value="">全部状态</option>
<?php foreach($status_labels as $code=>$name): ?><option value="<?=$code?>" <?=($filter['status']??'')==$code?'selected':''?>><?=$name?></option><?php endforeach; ?></select></div>
<div class="col-md-3 col-6">
<button type="button" class="btn btn-outline-secondary btn-sm w-100" data-bs-toggle="offcanvas" data-bs-target="#advFilter" aria-controls="advFilter"><i class="bi bi-sliders"></i> 高级筛选</button></div>
<div class="col-md-2 col-6"><button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-search"></i> 搜索</button></div>
<!-- v2.40.0：草稿快捷筛选（全部 / 草稿 / 我的草稿）——避免每次从状态下拉手动找；
     v2.52.1：行首新增「查看范围」切换（我的合同/全部合同），默认我的合同、记忆上次选择；
     scope=me 时归属人筛选禁用（JS 联动，见 contract.js） -->
<div class="col-12">
  <div class="d-flex gap-1 flex-wrap align-items-center mt-1">
    <?php if(!empty($can_scope_toggle)): ?>
    <span class="text-muted small me-1">查看范围：</span>
    <button type="button" class="btn btn-sm scope-chip btn-primary" data-scope="me">我的合同</button>
    <button type="button" class="btn btn-sm scope-chip btn-outline-primary" data-scope="all">全部合同</button>
    <span class="text-muted mx-2" style="opacity:.5">|</span>
    <?php endif; ?>
    <span class="text-muted small me-1">快捷筛选：</span>
    <button type="button" class="btn btn-sm draft-chip" data-status="" data-owner="">全部合同</button>
    <button type="button" class="btn btn-sm draft-chip" data-status="DRAFT" data-owner="">草稿</button>
    <button type="button" class="btn btn-sm draft-chip" data-status="DRAFT" data-owner="me">我的草稿</button>
  </div>
</div>

<!-- P1-7：高级筛选抽屉（Bootstrap offcanvas；字段仍位于 #searchForm 内，JS FormData 自动收集，无需改动加载逻辑） -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="advFilter" aria-labelledby="advFilterLabel" style="width:340px">
  <div class="offcanvas-header">
    <h6 class="offcanvas-title" id="advFilterLabel"><i class="bi bi-sliders"></i> 高级筛选</h6>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="关闭"></button>
  </div>
  <div class="offcanvas-body">
    <div class="row g-2">
      <div class="col-6"><label class="form-label small text-muted mb-1" for="fBusinessType">业务类型</label><select name="business_type" id="fBusinessType" class="form-select form-select-sm"><option value="">全部业务类型</option>
      <?php foreach($business_types as $code=>$name): ?><option value="<?=$code?>" <?=($filter['business_type']??'')==$code?'selected':''?>><?=$name?></option><?php endforeach; ?></select></div>
      <div class="col-6"><label class="form-label small text-muted mb-1" for="fDirection">收付方向</label><select name="direction" id="fDirection" class="form-select form-select-sm"><option value="">全部方向</option>
      <option value="sales" <?=($filter['direction']??'')=='sales'?'selected':''?>>销售（我方收款）</option>
      <option value="purchase" <?=($filter['direction']??'')=='purchase'?'selected':''?>>采购（我方付款）</option></select></div>
      <div class="col-6"><label class="form-label small text-muted mb-1" for="fTradeAttr">合同性质</label><select name="trade_attr" id="fTradeAttr" class="form-select form-select-sm"><option value="">全部性质</option>
      <option value="1" <?=($filter['trade_attr']??'')=='1'?'selected':''?>>交易合同</option>
      <option value="0" <?=($filter['trade_attr']??'')=='0'?'selected':''?>>非交易合同</option></select></div>
      <div class="col-12"><label class="form-label small text-muted mb-1" for="fProjectId">关联项目</label><select name="project_id" id="fProjectId" class="form-select form-select-sm"><option value="">全部项目</option>
      <?php foreach(($projects??[]) as $__p): ?><option value="<?=$__p['id']?>" <?=(string)($filter['project_id']??'')===(string)$__p['id']?'selected':''?>><?=htmlspecialchars($__p['name'])?></option><?php endforeach; ?></select></div>
      <div class="col-6"><label class="form-label small text-muted mb-1" for="fAmountMin">最低金额</label><input type="number" name="amount_min" id="fAmountMin" class="form-control form-control-sm" placeholder="0.00" step="0.01" value="<?=htmlspecialchars($filter['amount_min']??'')?>"></div>
      <div class="col-6"><label class="form-label small text-muted mb-1" for="fAmountMax">最高金额</label><input type="number" name="amount_max" id="fAmountMax" class="form-control form-control-sm" placeholder="0.00" step="0.01" value="<?=htmlspecialchars($filter['amount_max']??'')?>"></div>
      <div class="col-12"><label class="form-label small text-muted mb-1" for="fPartyName">相对方名称</label><input type="text" name="party_name" id="fPartyName" class="form-control form-control-sm" placeholder="甲方或乙方名称模糊匹配" value="<?=htmlspecialchars($filter['party_name']??'')?>"></div>
      <div class="col-12"><label class="form-label small text-muted mb-1" for="fCompanyId">签约主体</label><select name="our_company_id" id="fCompanyId" class="form-select form-select-sm"><option value="">全部签约主体</option>
      <?php foreach(($companies??[]) as $__co): ?><option value="<?=$__co['id']?>" <?=(string)($filter['our_company_id']??'')===(string)$__co['id']?'selected':''?>><?=htmlspecialchars($__co['name'])?></option><?php endforeach; ?></select></div>
      <div class="col-12"><label class="form-label small text-muted mb-1" for="fOwner">合同归属人</label>
      <?php
      // v2.41.0：归属人由下拉改为搜索选择器（涉及账号的一律不用下拉菜单；数据源 window._contractOwners 内存过滤）
      $__ownerName = '';
      if (($filter['owner_id'] ?? '') === 'me') {
          $__ownerName = '我';
      } else {
          foreach (($owners ?? []) as $__usr) {
              if ((string)($filter['owner_id'] ?? '') !== '' && (string)($filter['owner_id'] ?? '') === (string)$__usr['id']) {
                  $__ownerName = $__usr['name']; break;
              }
          }
      }
      ?>
      <div class="cs-wrap" data-cs-src="window._contractOwners">
        <input type="text" class="form-control form-control-sm cs-input" id="fOwner" placeholder="搜索归属人姓名" autocomplete="off" value="<?=htmlspecialchars($__ownerName)?>">
        <div class="cs-suggestions"></div>
        <input type="hidden" name="owner_id" class="cs-id" value="<?=htmlspecialchars($filter['owner_id'] ?? '')?>">
      </div>
      </div>
      <div class="col-6"><label class="form-label small text-muted mb-1" for="fDateStart">开始日期</label><input type="date" name="date_start" id="fDateStart" class="form-control form-control-sm" value="<?=htmlspecialchars($filter['date_start']??'')?>"></div>
      <div class="col-6"><label class="form-label small text-muted mb-1" for="fDateEnd">结束日期</label><input type="date" name="date_end" id="fDateEnd" class="form-control form-control-sm" value="<?=htmlspecialchars($filter['date_end']??'')?>"></div>
    </div>
    <div class="d-flex gap-2 mt-3">
      <button type="button" class="btn btn-outline-secondary btn-sm flex-fill" onclick="resetFilters()"><i class="bi bi-x-circle"></i> 重置</button>
      <button type="submit" class="btn btn-primary btn-sm flex-fill" data-bs-dismiss="offcanvas"><i class="bi bi-check-lg"></i> 应用筛选</button>
    </div>
  </div>
</div>
</form></div></div>
<!-- REV-28：表格增加全选与行勾选列 -->
<div class="card stat-card"><div class="table-responsive"><table class="table table-hover mb-0" id="contractTable"><thead class="table-light"><tr>
<th style="width:36px"><input type="checkbox" id="selectAll" onchange="toggleAll(this)" title="全选"></th>
<th data-sort="id" class="sortable">编号</th><th data-sort="title" class="sortable">标题</th><th>分类</th><th>方向</th><th data-sort="amount" class="sortable">金额</th><th data-sort="status" class="sortable">状态</th><th>乙方</th><th>项目</th><th>关联</th><th>操作</th></tr></thead><tbody id="tableBody"><!-- P1-7：首屏骨架屏（JS 加载完成/失败后自动替换，消除空白感与永久转圈） -->
<tr><td colspan="11" style="padding:0 12px">
<?php for($__i=0;$__i<5;$__i++): ?><div class="sk-row"><div class="sk sk-w1"></div><div class="sk sk-w2"></div><div class="sk sk-w3"></div><div class="sk sk-w4"></div><div class="sk sk-w5"></div><div class="sk sk-w6"></div></div><?php endfor; ?>
</td></tr></tbody></table></div><div class="card-footer bg-white" id="pagination"></div></div>
<script>window._businessTypes=<?=json_encode($business_types,JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;window._canCreateContract=<?=!empty($can_create_contract)?'true':'false'?>;window._contractOwners=<?=json_encode($owners ?? [],JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;</script>
<script src="<?=asset_url('js/contract.js')?>"></script>
<script src="<?=asset_url('js/search-picker.js')?>"></script>
<style>
@media (max-width:768px){
  body{padding-bottom:calc(56px + env(safe-area-inset-bottom,0px));}
  .m-tabbar{position:fixed;left:0;right:0;bottom:0;height:calc(56px + env(safe-area-inset-bottom,0px));padding-bottom:env(safe-area-inset-bottom,0px);background:#fff;border-top:1px solid var(--line);display:flex;z-index:60;}
  .m-tabbar a{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:2px;color:var(--text-3);font-size:11px;text-decoration:none;min-height:44px;}
  .m-tabbar a.active{color:var(--primary);}
  .m-tabbar a i{font-size:21px;}
}
@media (min-width:769px){ .m-tabbar{display:none!important;} }
</style>
<?=mobile_tabbar('contract')?>
<?php include __DIR__.'/../layout/footer.php'; ?>
