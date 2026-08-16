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
              <span class="m-tag m-tag-muted" style="margin-left:4px"><?=htmlspecialchars(implode('、', $n['resolved_names']))?></span>
            <?php else: ?>
              <span style="margin-left:4px;color:#dc3545"><?=htmlspecialchars($n['resolve_warning'] ?? '未指定具体人员')?></span>
            <?php endif; ?>
          </li>
          <?php endforeach; ?>
        </ol>
        <?php if(!empty($has_cc)): ?>
        <div style="margin:8px 0;padding:10px 12px;background:#f0f7ff;border-radius:8px;font-size:14px">
          <strong>抄送知会：</strong>
          <?php if(!empty($cc_roles)): ?><span style="margin-right:6px">角色：<?=htmlspecialchars(implode('、', $cc_roles))?></span><?php endif; ?>
          <?php if(!empty($cc_names)): ?><span style="color:var(--m-text-2)"><?=htmlspecialchars(implode('、', $cc_names))?></span><?php endif; ?>
        </div>
        <?php endif; ?>
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
  if(btn){
    btn.addEventListener('click', function(){
      var self = this;
      mConfirm('确认提交该合同的审批？', function(){
        self.disabled = true; showLoading(true);
      var fd = new FormData();
      fd.append('contract_id', <?=intval($contract['id'])?>);
      fd.append('flow_id', <?=intval($contract['flow_id'] ?? 0)?>);
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
