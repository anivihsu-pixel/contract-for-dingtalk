<?php
// 移动端共享布局（Phase 1 重构 1.7）：头部与尾部由 _head.php / _foot.php 统一输出
$title = '提交审批';   // 页面标题，自动追加「 · 合同管理」
$tab = '';     // 审批 Tab 已从底部菜单移除（与顶部「待我审批」重叠），本页不高亮
include __DIR__ . '/_head.php';
?>

<!-- 顶部导航 -->
<div class="m-nav">
  <a href="/m/contract/<?=intval($contract['id'])?>" class="back" aria-label="返回"><i class="bi bi-chevron-left"></i></a>
  <div class="title">提交审批</div>
  <span class="right"></span>
</div>

<div class="m-page detail">
  <!-- 合同摘要 -->
  <div class="m-card">
    <div class="m-card-hd"><span><i class="bi bi-file-earmark-text me-1"></i>合同摘要</span></div>
    <div class="m-card-bd">
      <div class="m-kv"><div class="k">合同</div><div class="v"><?=htmlspecialchars($contract['title'] ?? '')?></div></div>
      <div class="m-kv"><div class="k">编号</div><div class="v"><?=htmlspecialchars($contract['contract_no'] ?? '')?></div></div>
      <div class="m-kv"><div class="k">业务类型</div><div class="v"><?=htmlspecialchars(dict_enabled('business_type')[$contract['business_type'] ?? ''] ?? ($contract['business_type'] ?? ''))?></div></div>
      <div class="m-kv"><div class="k">金额</div><div class="v"><span class="amt pay-amt">¥<?=number_format((float)($contract['amount'] ?? 0), 0)?></span></div></div>
    </div>
  </div>

  <!-- 审批节点 -->
  <div class="m-card">
    <div class="m-card-hd"><span><i class="bi bi-diagram-3 me-1"></i>审批节点</span></div>
    <div class="m-card-bd">
      <?php if($matched_flow): ?>
        <?php $nodes = json_decode($matched_flow['nodes'] ?? '[]', true) ?: []; ?>
        <ol class="m-flow">
          <?php foreach($flow_nodes as $n): ?>
          <li>
            <span class="m-flow-name"><?=htmlspecialchars($n['name'] ?? '节点')?></span>
            <?php if(!empty($n['resolved_names'])): ?>
              <!-- v2.51.10：审批人标签由灰底 m-tag-muted 统一为蓝底 m-tag-info（#e8f1ff），与抄送标签一致 -->
              <span class="m-tag m-tag-info" style="margin-left:4px"><?=htmlspecialchars(implode('、', $n['resolved_names']))?></span>
            <?php else: ?>
              <span style="margin-left:4px;color:#dc3545"><?=htmlspecialchars($n['resolve_warning'] ?? '未指定具体人员')?></span>
            <?php endif; ?>
          </li>
          <?php endforeach; ?>
          <?php if(!empty($has_cc)): ?>
          <li>
            <span class="m-flow-name">抄送知会</span>
            <?php foreach($cc_names as $ccn): ?><span class="m-tag m-tag-info"><?=htmlspecialchars($ccn)?></span><?php endforeach; ?>
          </li>
          <?php endif; ?>
        </ol>
        <!-- v2.51.10：随合同申请开票——合同过审后自动生成待开票发票并通知财务，无需再单独申请 -->
        <div style="border-top:1px solid var(--m-border);margin:12px 0;padding-top:12px">
          <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px">
            <input type="checkbox" id="withInvoice" name="with_invoice" value="1" style="width:18px;height:18px">
            <label for="withInvoice" style="font-size:14px;font-weight:500;margin:0"><i class="bi bi-receipt-cutoff me-1"></i>随合同申请开票</label>
          </div>
          <div id="invIntentBox" style="display:none">
            <div class="m-field"><label class="m-field-label">开票主体 <span style="color:#fa5151">*</span></label>
              <select class="m-input" name="invoice_our_company_id" style="appearance:auto">
                <option value="">请选择开票主体</option>
                <?php foreach(($companies ?? []) as $__c): ?>
                <option value="<?=(int)$__c['id']?>"><?=htmlspecialchars($__c['name'])?><?=isset($__c['invoice_tax_rate']) && $__c['invoice_tax_rate'] !== '' ? '（' . (float)$__c['invoice_tax_rate'] * 100 . '%）' : ''?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="m-field"><label class="m-field-label">开票类型 <span style="color:#fa5151">*</span></label>
              <select class="m-input" name="invoice_type" style="appearance:auto">
                <option value="VAT_SPECIAL">增值税专用发票</option>
                <option value="VAT_NORMAL">普通发票</option>
              </select>
            </div>
            <div class="m-field"><label class="m-field-label">含税金额（元） <span style="color:#fa5151">*</span></label>
              <input type="number" class="m-input" name="invoice_amount" step="0.01" min="0.01" placeholder="≤ ¥<?=number_format((float)($contract['amount'] ?? 0),0)?>">
            </div>
            <div class="m-field"><label class="m-field-label">开票内容 <span style="color:#fa5151">*</span></label>
              <select class="m-input" name="invoice_content_desc" style="appearance:auto">
                <option value="">请选择开票内容</option>
                <option>软件开发服务费</option>
                <option>咨询服务费</option>
                <option>运维服务费</option>
                <option>硬件销售费</option>
                <option>其他</option>
              </select>
            </div>
            <div class="m-field"><label class="m-field-label">发票抬头</label>
              <input type="text" class="m-input" name="invoice_title" value="<?=htmlspecialchars($default_inv_title ?? '')?>" placeholder="默认带出合同乙方">
            </div>
            <div class="m-field"><label class="m-field-label">税号</label>
              <input type="text" class="m-input" name="invoice_tax_no" value="<?=htmlspecialchars($default_inv_tax_no ?? '')?>">
            </div>
            <div class="m-field"><label class="m-field-label">申请说明</label>
              <input type="text" class="m-input" name="invoice_remark" placeholder="选填">
            </div>
            <div class="m-float-tip" style="margin-bottom:12px">合同过审后自动生成「待开票」发票并通知财务确认开票，金额不可超过合同金额。</div>
          </div>
        </div>
        <button class="m-btn m-btn-ok m-btn-block" id="btnSubmit"><i class="bi bi-send"></i> 确认提交</button>
      <?php else: ?>
        <div class="m-float-tip danger"><i class="bi bi-exclamation-triangle me-1"></i>未匹配到适用的审批流程，请联系管理员在「系统设置 → 审批流程」中配置。</div>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="m-toast" id="toast"></div>
<div class="m-loading" id="loading" style="display:none"><div class="m-spinner"></div></div>

<script>
(function(){
  
  
  
  var btn = document.getElementById('btnSubmit');
  // v2.51.10：随合同申请开票——勾选后展开开票字段
  var wchk = document.getElementById('withInvoice');
  var ibox = document.getElementById('invIntentBox');
  if(wchk && ibox){ wchk.addEventListener('change', function(){ ibox.style.display = this.checked ? '' : 'none'; }); }
  if(btn){
    btn.addEventListener('click', function(){
      var self = this;
      mConfirm('确认提交该合同的审批？', function(){
        self.disabled = true; showLoading(true);
      var fd = new FormData();
      fd.append('contract_id', <?=intval($contract['id'])?>);
      fd.append('flow_id', <?=intval($contract['flow_id'] ?? 0)?>);
      // v2.51.10：随合同申请开票字段（勾选=1 保存意图；未勾选=0 清除历史意图）
      var wc = document.getElementById('withInvoice');
      fd.append('with_invoice', (wc && wc.checked) ? '1' : '0');
      var ib = document.getElementById('invIntentBox');
      if(ib){ ib.querySelectorAll('[name]').forEach(function(el){ fd.append(el.name, el.value); }); }
      fetch('/ajax/approval/submit', {
        method:'POST', body:fd,
        headers:{'X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':csrfToken()}
      })
        .then(function(r){ return r.json(); })
        .then(function(res){
          showLoading(false);
          if(res.code === 0){
            toast('审批已提交');
            setTimeout(function(){ location.href = '/m/approval/' + (res.data.instance_id || 0); }, 700);
          } else {
            self.disabled = false;
            toast(res.msg || '提交失败');
          }
        })
        .catch(function(){
          showLoading(false); self.disabled = false;
          toast('网络异常，请重试');
        });
      });
    });
  }
})();
</script>
<?php $tab = ''; include __DIR__ . '/_foot.php'; ?>
