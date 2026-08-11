<?php $title='发票申请'; $menu_active='invoice_apply'; include __DIR__.'/../layout/header.php'; ?>
<!-- 发票申请独立入口（F5）：我的申请 / 待我审批 / 快捷申请开票；申请表单字段由后台「系统设置→发票表单」配置 -->
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <h4 class="mb-0"><i class="bi bi-receipt-cutoff"></i> 发票申请</h4>
  <div class="d-flex gap-2">
    <?php if(!empty($can_apply)): ?><button class="btn btn-primary btn-sm" onclick="showApplyModal()"><i class="bi bi-plus-lg"></i> 申请开票</button><?php endif; ?>
    <a href="/finance?tab=invoice" class="btn btn-outline-secondary btn-sm"><i class="bi bi-cash-coin"></i> 财务中心·发票管理</a>
  </div>
</div>

<ul class="nav nav-tabs mb-3" id="invApplyTabs">
  <li class="nav-item"><a class="nav-link active" href="javascript:;" data-tab="mine" onclick="switchTab('mine')">我的申请</a></li>
  <li class="nav-item"><a class="nav-link" href="javascript:;" data-tab="pending" onclick="switchTab('pending')">待我审批</a></li>
  <?php if(!empty($can_create)): ?><li class="nav-item"><a class="nav-link" href="javascript:;" data-tab="issue" onclick="switchTab('issue')">待开票</a></li><?php endif; ?>
</ul>

<!-- 我的申请 -->
<div id="panelMine">
  <div class="mb-2"><select id="mineStatus" class="form-select form-select-sm" style="max-width:220px" onchange="loadMine(true)">
    <option value="">全部状态</option>
    <?php foreach($status_labels as $code=>$name): ?><option value="<?=$code?>"><?=$name?></option><?php endforeach; ?>
  </select></div>
  <div class="card stat-card"><div class="table-responsive"><table class="table table-hover mb-0"><thead class="table-light"><tr>
    <th>开票内容</th><th>开票主体</th><th>金额</th><th>类型</th><th>状态</th><th>提交时间</th><th>操作</th></tr></thead><tbody id="mineTb"><tr><td colspan="7" class="text-center py-4"><div class="spinner-border spinner-border-sm text-muted"></div></td></tr></tbody></table>
  <div class="card-footer bg-white text-muted small" id="mineEmpty" style="display:none">暂无开票申请</div>
  <div class="card-footer bg-white text-center" id="mineMore" style="display:none"><button class="btn btn-sm btn-outline-secondary" onclick="loadMine(false)">加载更多</button></div></div></div>
</div>

<!-- 待我审批 -->
<div id="panelPending" style="display:none">
  <div class="card stat-card"><div class="table-responsive"><table class="table table-hover mb-0"><thead class="table-light"><tr>
    <th>开票内容</th><th>申请人</th><th>开票主体</th><th>金额</th><th>当前节点</th><th>提交时间</th><th>操作</th></tr></thead><tbody id="pendingTb"><tr><td colspan="7" class="text-center py-4 text-muted">切换后加载</td></tr></tbody></table>
  <div class="card-footer bg-white text-muted small" id="pendingEmpty" style="display:none">暂无待审批的申请</div>
  <div class="card-footer bg-white text-center" id="pendingMore" style="display:none"><button class="btn btn-sm btn-outline-secondary" onclick="loadPending(false)">加载更多</button></div></div></div>
</div>

<!-- 待开票（财务视角：审批通过的申请，填写发票号开票） -->
<div id="panelIssue" style="display:none">
  <div class="card stat-card"><div class="table-responsive"><table class="table table-hover mb-0"><thead class="table-light"><tr>
    <th>开票内容</th><th>申请人</th><th>开票主体</th><th>金额</th><th>类型</th><th>状态</th><th>操作</th></tr></thead><tbody id="issueTb"><tr><td colspan="7" class="text-center py-4 text-muted">切换后加载</td></tr></tbody></table>
  <div class="card-footer bg-white text-muted small" id="issueEmpty" style="display:none">暂无待开票的申请</div>
  <div class="card-footer bg-white text-center" id="issueMore" style="display:none"><button class="btn btn-sm btn-outline-secondary" onclick="loadIssue(false)">加载更多</button></div></div></div>
</div>

<!-- 开票弹窗（财务填写发票号/日期） -->
<div class="modal fade" id="issueModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
<div class="modal-header"><h5 class="modal-title">确认开票</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
  <div class="mb-2"><label class="form-label" for="issueNo">发票号码 <span class="text-danger">*</span></label><input type="text" id="issueNo" class="form-control" placeholder="如 FP2026080001"></div>
  <div class="mb-2"><label class="form-label" for="issueDate">开票日期</label><input type="date" id="issueDate" class="form-control"></div>
  <div class="text-danger small" id="issueErr"></div>
</div>
<div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">取消</button><button class="btn btn-success" onclick="submitIssue()">确认开票</button></div>
</div></div></div>

<!-- 申请开票弹窗（字段由 InvoiceFormConfig 渲染，后台可配） -->
<div class="modal fade" id="applyModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
<div class="modal-header"><h5 class="modal-title"><i class="bi bi-receipt-cutoff"></i> 申请开票</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
  <div class="mb-3">
    <label class="form-label" for="contractSearch">关联合同 <span class="text-muted small">（选填，可不选直接快捷申请）</span></label>
    <div class="position-relative">
      <input type="text" class="form-control" id="contractSearch" placeholder="搜索合同编号 / 标题（选填）" autocomplete="off">
      <div class="party-suggestions" id="contractSuggestions"></div>
      <input type="hidden" id="contractId" name="contract_id" value="0">
    </div>
  </div>
  <div class="row g-2" id="applyFields">
  <!-- 2026-08-02：税率绑定开票主体，表单不渲染税率组件；隐藏字段承接主体税率供价税拆分与提交（后端强制从公司读取，防篡改） -->
  <input type="hidden" name="tax_rate" id="applyTaxRate" value="0.06">
  <?= $apply_fields ?>
  </div>
  <!-- H2：含税金额价税拆分实时展示（含税 = 不含税 + 税额） -->
  <div class="small text-muted mt-1" id="applyTaxCalc" style="display:none"></div>
  <div class="text-danger small mt-2" id="applyErr"></div>
</div>
<div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">取消</button><button class="btn btn-primary" onclick="submitApply()"><i class="bi bi-send"></i> 提交申请</button></div>
</div></div></div>

<script>
// F9：发票申请表单联动规则（通用组件 form-linkage.js 自动消费；后台「系统设置→发票表单」配置）
window.__formRules = <?= json_encode($invoice_form_rules ?? [], JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
// H3：联动 fill 动作数据源（选客户 → 自动带出抬头=客户名/税号=信用代码）
window.__formData = <?= json_encode(['customer_id' => $invoice_customers ?? []], JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
</script>
<script src="<?=asset_url('js/form-linkage.js')?>"></script>
<script src="<?=asset_url('js/search-picker.js')?>"></script>
<script src="<?=asset_url('js/invoice-apply.js')?>"></script>
<?php include __DIR__.'/../layout/footer.php'; ?>
