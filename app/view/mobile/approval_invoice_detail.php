<?php
// 移动端开票审批详情（2026-08-15：biz_type=invoice 审批实例的独立详情页）
// 审批实例不关联合同，展示开票申请概要 + 审批进度；仅提交人可撤回
$title = '审批详情';   // 页面标题，自动追加「 · 合同管理」
$tab = '';     // 与合同审批详情一致，底部导航不高亮
include __DIR__ . '/_head.php';
?>

<!-- 顶部导航 -->
<div class="m-nav">
  <!-- 与合同审批详情一致：返回 history.back()，从待办中心进入时回待办中心 -->
  <a href="javascript:history.back()" class="back" aria-label="返回"><i class="bi bi-chevron-left"></i></a>
  <div class="title">审批详情</div>
  <div class="right"></div>
</div>

<?php
  $d  = $detail;
  $iv = $invoice ?? [];
  $st = $d['status'] ?? 'PENDING';
  $stMap = ['PENDING'=>'待审批','APPROVED'=>'已通过','REJECTED'=>'已驳回','RECALLED'=>'已撤回'];
  $stCls = ['PENDING'=>'m-tag-info','APPROVED'=>'m-tag-ok','REJECTED'=>'m-tag-danger','RECALLED'=>'m-tag-muted'];
  // 审批进度：按流程定义渲染完整时间线（含未来节点占位），与合同审批详情同逻辑
  $flowNodes = json_decode($d['nodes'] ?? '[]', true) ?: [];
  $curOrder  = (int)($d['current_node_order'] ?? 0);
  $totalNodes = count($flowNodes);
  $doneNodes  = 0;
  $recByNode  = [];
  foreach (($d['records'] ?? []) as $r) {
      $recByNode[(int)($r['node_order'] ?? 1)][] = $r;
      if (in_array($r['action'] ?? '', ['APPROVED', 'AUTO_APPROVED'], true)) { $doneNodes++; }
  }
  // 发票类型/状态中文
  $invTypeMap = ['VAT_SPECIAL'=>'增值税专用发票','VAT_NORMAL'=>'增值税普通发票','E_INVOICE'=>'电子发票','OTHER'=>'其他'];
  $invStMap = ['PENDING_APPROVAL'=>'待审批','APPROVED'=>'待开票','REJECTED'=>'已驳回','ISSUED'=>'已开票','VOID'=>'已作废','RED'=>'已红冲','CANCELLED'=>'已撤回','APPLIED'=>'申请中（旧）'];
?>

<div class="m-page detail">
  <!-- 开票申请概要 -->
  <div class="m-card">
    <div class="m-card-hd"><span><i class="bi bi-receipt-cutoff me-1 text-primary"></i>开票申请</span>
      <span class="m-tag <?=$stCls[$st] ?? 'm-tag-muted'?>"><?=$stMap[$st] ?? $st?></span>
      <?php if($totalNodes > 0): ?><span style="font-size:12px;color:var(--m-text-3);margin-left:6px">进度 <?=$doneNodes?>/<?=$totalNodes?></span><?php endif; ?>
    </div>
    <div class="m-card-bd">
      <div class="m-kv"><div class="k">开票内容</div><div class="v"><?=htmlspecialchars($iv['content_desc'] ?? '-')?></div></div>
      <div class="m-kv"><div class="k">含税金额</div><div class="v"><span class="amt amt-in">¥<?=number_format((float)($iv['amount'] ?? 0), 2)?></span>
        <?php if(!empty($iv['tax_rate'])): ?><span class="m-tag m-tag-muted" style="margin-left:6px">税率 <?=((float)$iv['tax_rate']*100)?>%</span><?php endif; ?></div></div>
      <div class="m-kv"><div class="k">开票主体</div><div class="v"><?=htmlspecialchars($our_company ?: '-')?></div></div>
      <div class="m-kv"><div class="k">开票类型</div><div class="v"><?=htmlspecialchars($invTypeMap[$iv['invoice_type'] ?? ''] ?? ($iv['invoice_type'] ?? '-'))?></div></div>
      <?php if(!empty($iv['invoice_title'])): ?><div class="m-kv"><div class="k">发票抬头</div><div class="v"><?=htmlspecialchars($iv['invoice_title'])?></div></div><?php endif; ?>
      <?php if(!empty($iv['tax_no'])): ?><div class="m-kv"><div class="k">税号</div><div class="v"><?=htmlspecialchars($iv['tax_no'])?></div></div><?php endif; ?>
      <?php if(!empty($iv['invoice_no'])): ?><div class="m-kv"><div class="k">发票号码</div><div class="v"><?=htmlspecialchars($iv['invoice_no'])?></div></div><?php endif; ?>
      <?php if(!empty($iv['remark'])): ?><div class="m-kv"><div class="k">申请说明</div><div class="v"><?=htmlspecialchars($iv['remark'])?></div></div><?php endif; ?>
      <div class="m-kv"><div class="k">提交人</div><div class="v"><?=htmlspecialchars($d['submitter_name'] ?? '-')?></div></div>
      <div class="m-kv"><div class="k">审批流</div><div class="v"><?=htmlspecialchars($d['flow_name'] ?? '-')?></div></div>
      <div class="m-kv"><div class="k">提交时间</div><div class="v"><?=htmlspecialchars($d['submitted_at'] ?? '-')?></div></div>
    </div>
  </div>

  <!-- 审批进度（与合同审批详情同逻辑：已完成/当前/未来节点占位，驳回节点红色高亮） -->
  <div class="m-card">
    <div class="m-card-hd"><span><i class="bi bi-list-check me-1"></i>审批进度</span></div>
    <div class="m-card-bd">
      <div class="m-timeline">
        <?php foreach($flowNodes as $i => $fn):
          $order = $i + 1;
          $recs  = $recByNode[$order] ?? [];
          $last  = $recs ? end($recs) : null;
          $act   = $last['action'] ?? 'PENDING';
          $cls   = 'm-step ';
          $badge = ['待审批', 'm-tag-muted'];
          if ($act === 'REJECTED') {
              $cls  .= 'reject';
              $badge = ['已驳回', 'm-tag-danger'];
          } elseif (in_array($act, ['APPROVED', 'AUTO_APPROVED'], true)) {
              $cls  .= 'done';
              $badge = [$act === 'AUTO_APPROVED' ? '自动通过' : '已同意', 'm-tag-ok'];
          } elseif ($act === 'TRANSFERRED') {
              $badge = ['已转交', 'm-tag-warn'];
          } elseif ($order === $curOrder && $st === 'PENDING') {
              $cls  .= 'current';
              $badge = ['审批中', 'm-tag-info'];
          }
        ?>
        <div class="<?=$cls?>">
          <div class="dot"></div>
          <div class="node"><?=htmlspecialchars($fn['name'] ?? ('节点' . $order))?></div>
          <div class="who"><?php if($last): ?><?=htmlspecialchars($last['approver_name'] ?? '待审批人')?>
            <span class="m-tag <?=$badge[1]?>" style="margin-left:6px"><?=$badge[0]?></span>
          <?php else: ?><span class="m-tag <?=$badge[1]?>"><?=$badge[0]?></span><?php endif; ?></div>
          <?php if(!empty($last['comment'])): ?><div class="cmt">意见：<?=htmlspecialchars($last['comment'])?></div><?php endif; ?>
          <?php if(!empty($last['acted_at'])): ?><div class="time"><?=htmlspecialchars($last['acted_at'])?></div><?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php if(!empty($d['cc_log'])): foreach($d['cc_log'] as $c): ?>
        <div class="m-step">
          <div class="dot"></div>
          <div class="node">抄送知会</div>
          <div class="who"><?=htmlspecialchars($c['user_name'] ?? '抄送人')?>
            <span class="m-tag m-tag-info" style="margin-left:6px">抄送</span></div>
          <?php if(!empty($c['created_at'])): ?><div class="time"><?=htmlspecialchars($c['created_at'])?></div><?php endif; ?>
        </div>
        <?php endforeach; endif; ?>
      </div>
    </div>
  </div>

  <!-- 状态提示 -->
  <?php if(!$can_act): ?>
  <div class="m-card"><div class="m-card-bd" style="text-align:center;color:var(--m-text-3);font-size:14px;padding:18px 0">
    <?php if($can_recall): ?>↩️ 这是你提交的审批，如需修改可撤回
    <?php elseif($st === 'APPROVED'): ?>✅ 该开票申请已通过，等待财务开票
    <?php elseif($st === 'REJECTED'): ?>⛔ 该开票申请已被驳回
    <?php elseif($st === 'RECALLED'): ?>↩️ 提交人已撤回该审批
    <?php else: ?>你不是当前节点审批人，无需操作<?php endif; ?>
  </div></div>
  <?php endif; ?>
</div>

<!-- 底部操作栏（仅提交人且仍待审批） -->
<?php if($can_recall): ?>
<div class="m-actionbar">
  <button class="m-btn m-btn-ghost" id="btnRecall"><i class="bi bi-arrow-counterclockwise"></i>撤回审批</button>
</div>
<?php endif; ?>

<div class="m-toast" id="toast"></div>
<div class="m-loading" id="loading" style="display:none"><div class="m-spinner"></div></div>

<script>
(function(){
  var instanceId = <?=intval($d['id'])?>;
  // 撤回（仅提交人，且仍待审批）
  var btnRecall = document.getElementById('btnRecall');
  if(btnRecall){
    btnRecall.addEventListener('click', function(){
      var self = this;
      mConfirm('确认撤回该审批？撤回后开票申请退回，可重新提交。', function(){
        self.disabled = true; showLoading(true);
        fetch('/ajax/approval/' + instanceId + '/recall', {
          method:'POST',
          headers:{'X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':csrfToken()}
        })
          .then(function(r){ return r.json(); })
          .then(function(res){
            showLoading(false);
            if(res.code === 0){
              toast('已撤回');
              setTimeout(function(){ location.href = '/m/approvals?type=submitted'; }, 800);
            } else {
              self.disabled = false;
              toast(res.msg || '撤回失败');
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
