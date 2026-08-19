<?php
// 移动端合同详情（S 级独立视图）
$contract = $contract ?? [];
$status = $contract['status'] ?? '';
$statusMap = [
    'DRAFT' => ['t' => '草稿', 'c' => 'warn'],   // v2.44.4：草稿徽标统一琥珀（方案 A，与列表一致）
    'PENDING_APPROVAL' => ['t' => '待审批', 'c' => 'warn'],
    'REJECTED' => ['t' => '已驳回', 'c' => 'danger'],
    'EXECUTING' => ['t' => '执行中', 'c' => 'ok'],
    'COMPLETED' => ['t' => '已完成', 'c' => 'muted'],
    'TERMINATED' => ['t' => '已终止', 'c' => 'danger'],
    'EXPIRED' => ['t' => '已到期', 'c' => 'muted'],
    'ARCHIVED' => ['t' => '已归档', 'c' => 'muted'],
];
$st = $statusMap[$status] ?? ['t' => $status, 'c' => 'muted'];
$dirMap = ['sales' => '销售', 'purchase' => '采购'];
$dirText = $dirMap[$contract['direction'] ?? ''] ?? '';
$businessTypeName = dict_enabled('business_type')[$contract['business_type'] ?? ''] ?? ($contract['business_type'] ?? '');
$payStatusMap = ['PENDING' => ['t' => '待收', 'c' => 'warn'], 'PAID' => ['t' => '已收', 'c' => 'ok'], 'OVERDUE' => ['t' => '逾期', 'c' => 'danger']];
// 审批实例状态映射（与 approval_status_label 文本一致；注意：审批实例状态用 PENDING/APPROVED/REJECTED/RECALLED，
// 与合同状态（PENDING_APPROVAL 等）不同，不能用上面的 $statusMap，否则 PENDING/RECALLED 查不到键会原样输出英文）
$apprStatusMap = ['PENDING' => ['t' => '审批中', 'c' => 'info'], 'APPROVED' => ['t' => '已通过', 'c' => 'ok'], 'REJECTED' => ['t' => '已驳回', 'c' => 'danger'], 'RECALLED' => ['t' => '已撤回', 'c' => 'muted']];
$tradeAttr = (int)($contract['trade_attr'] ?? 1);
$amountIsIn = ($contract['direction'] ?? '') === 'sales'; // 销售=应收(红)，采购=应付(绿)
$amountClass = $tradeAttr === 0 ? '' : ($amountIsIn ? 'amt-in' : 'amt-out');
$planAmount = (float)($plan_amount ?? 0);
$paidAmount = (float)($paid_amount ?? 0);
$pct = $planAmount > 0 ? round($paidAmount / $planAmount * 100) : 0;
$payments = $payments ?? [];
$approvals = $approvals ?? [];
$attachments = $attachments ?? [];
$customSchema = $custom_schema ?? [];
$customValues = $custom_values ?? [];
?>
<?php
// 移动端共享布局（Phase 1 重构 1.7）：头部与尾部由 _head.php / _foot.php 统一输出
$title = '合同详情';   // 页面标题，自动追加「 · 合同管理」
$tab = 'contract';     // 底部导航高亮：home/contract/customer/todo
$pageStyle = <<<'CSS'

  .hero { margin: var(--m-gap); background: var(--m-card); border-radius: var(--m-radius); padding: 18px var(--m-pad); box-shadow: 0 1px 3px rgba(0,0,0,.05); }
  .hero .title { font-size: 18px; font-weight: 600; line-height: 1.4; }
  .hero .no { font-size: 13px; color: var(--m-text-3); margin-top: 6px; }
  .hero .amt { font-size: 30px; font-weight: 700; margin-top: 14px; letter-spacing: .5px; }
  .hero .amt .unit { font-size: 15px; font-weight: 500; margin-right: 2px; }
  .hero .tags { margin-top: 12px; display: flex; gap: 8px; flex-wrap: wrap; }
  .hero .tags .m-tag { font-size: 12px; }

  /* 基本信息两列紧凑网格 */
  .info-compact { display: grid; grid-template-columns: 1fr 1fr; gap: 0 14px; }
  .info-compact .info-item { padding: 9px 0; border-bottom: 1px solid var(--m-line); }
  .info-compact .info-item:nth-last-child(-n+2) { border-bottom: none; }
  .info-compact .info-label { font-size: 12px; color: var(--m-text-3); line-height: 1.4; }
  .info-compact .info-val { font-size: 14px; color: var(--m-text); font-weight: 500; margin-top: 1px; }

  .pay-progress { height: 8px; border-radius: 999px; background: #eef0f3; overflow: hidden; margin: 4px 0 10px; }
  .pay-progress > span { display: block; height: 100%; background: linear-gradient(90deg, #07c160, #34d27e); border-radius: 999px; }
  .pay-summary { display: flex; justify-content: space-between; font-size: 13px; color: var(--m-text-2); }
  .pay-summary b { font-size: 15px; color: var(--m-text); }

  .att-row { display: flex; align-items: center; gap: 12px; padding: 13px 0; border-bottom: 1px solid var(--m-line); min-height: 44px; }
  .att-row:last-child { border-bottom: none; }
  .att-row .ic { width: 36px; height: 36px; flex: none; border-radius: 9px; background: var(--m-brand-light); color: var(--m-brand); display: flex; align-items: center; justify-content: center; font-size: 17px; }
  .att-row .nm { flex: 1; min-width: 0; font-size: 14px; color: var(--m-text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .att-row .dl { flex: none; color: var(--m-brand); font-size: 13px; }

  .party { padding: 12px 0; border-bottom: 1px solid var(--m-line); }
  .party:last-child { border-bottom: none; }
  .party .role { font-size: 12px; color: var(--m-brand); font-weight: 600; margin-bottom: 6px; }
  .party .pn { font-size: 15px; font-weight: 600; color: var(--m-text); }

  .contract-content { font-size: 14px; color: var(--m-text-2); line-height: 1.7; white-space: pre-wrap; word-break: break-all; max-height: 160px; overflow: hidden; position: relative; }
  .contract-content.expanded { max-height: none; }
  .content-toggle { text-align: center; color: var(--m-brand); font-size: 13px; padding: 8px 0 2px; }
  .content-toggle:active { opacity: .7; }

  .approval-row { padding: 12px 0; border-bottom: 1px solid var(--m-line); }
  .approval-row:last-child { border-bottom: none; }
  .approval-row .top { display: flex; justify-content: space-between; align-items: center; }
  .approval-row .fl { font-size: 14px; font-weight: 600; }
  .approval-row .meta { font-size: 12px; color: var(--m-text-3); margin-top: 4px; }

  /* 附件预览 Lightbox */
  .lb-overlay { display: none; position: fixed; inset: 0; z-index: 999; background: rgba(0,0,0,.92); flex-direction: column; }
  .lb-overlay.show { display: flex; }
  .lb-bar { display: flex; justify-content: space-between; align-items: center; padding: 12px 16px; color: #fff; font-size: 14px; flex: none; }
  .lb-bar .lb-close { font-size: 28px; color: #fff; background: none; border: none; padding: 4px 8px; cursor: pointer; }
  .lb-body { flex: 1; display: flex; align-items: center; justify-content: center; overflow: auto; padding: 16px; }
  .lb-body img { max-width: 100%; max-height: 80vh; border-radius: 8px; }
  .lb-body iframe { width: 100%; height: 80vh; border: none; border-radius: 8px; background: #fff; }
  .lb-fallback { text-align: center; color: #ccc; max-width: 280px; }
  .lb-fallback p { margin: 12px 0; line-height: 1.6; }
  .lb-fallback a { color: var(--m-brand); text-decoration: underline; }
CSS;
include __DIR__ . '/_head.php';
?>

<div class="m-nav">
  <a href="javascript:history.back()" class="back" aria-label="返回"><i class="bi bi-chevron-left"></i></a>
  <div class="title">合同详情</div>
  <div class="right"><span class="m-tag m-tag-<?=$st['c']?>"><?=$st['t']?></span></div>
</div>

<div class="m-page detail">

  <!-- 概览 -->
  <div class="hero">
    <div class="title"><?=htmlspecialchars($contract['title'] ?? '未命名合同')?></div>
    <div class="no"><?=htmlspecialchars($contract['contract_no'] ?? '')?></div>
    <?php if ($tradeAttr === 0): ?>
      <div class="amt" style="color:var(--m-text-3);font-size:22px;">非交易合同</div>
    <?php else: ?>
      <div class="amt <?=$amountClass?>"><span class="unit">¥</span><?=number_format((float)($contract['amount'] ?? 0), 0)?></div>
    <?php endif; ?>
    <div class="tags">
      <?php if ($dirText): ?><span class="m-tag m-tag-info"><?=$dirText?></span><?php endif; ?>
      <span class="m-tag m-tag-muted"><?=htmlspecialchars($businessTypeName)?></span>
      <?php if (!empty($contract['project_name'])): ?><span class="m-tag m-tag-muted"><?=htmlspecialchars($contract['project_name'])?></span><?php endif; ?>
    </div>
  </div>

  <!-- 基本信息（两列紧凑布局；状态已在导航栏显示） -->
  <div class="m-card">
    <div class="m-card-hd"><span><i class="bi bi-info-circle me-1 text-primary"></i>基本信息</span></div>
    <div class="m-card-bd info-compact">
      <div class="info-item"><div class="info-label">归属人</div><div class="info-val"><?=htmlspecialchars($contract['owner_name'] ?? $contract['owner_id'] ?? '-')?></div></div>
      <div class="info-item"><div class="info-label">创建人</div><div class="info-val"><?=htmlspecialchars($contract['creator_name'] ?? '-')?></div></div>
      <div class="info-item"><div class="info-label">生效日期</div><div class="info-val"><?=htmlspecialchars($contract['effective_date'] ?? '-')?></div></div>
      <div class="info-item"><div class="info-label">到期日期</div><div class="info-val"><?=htmlspecialchars($contract['expiry_date'] ?? '-')?></div></div>
      <div class="info-item"><div class="info-label">创建时间</div><div class="info-val"><?=htmlspecialchars($contract['created_at'] ?? '-')?></div></div>
    </div>
  </div>

  <!-- 合同概要（提前，便于快速了解合同内容） -->
  <?php if (!empty($contract['content_plain'])): ?>
  <div class="m-card">
    <div class="m-card-hd"><span><i class="bi bi-file-text me-1 text-primary"></i>合同概要</span></div>
    <div class="m-card-bd">
      <div class="contract-content" id="cc"><?=htmlspecialchars($contract['content_plain'])?></div>
      <div class="content-toggle" id="cct" onclick="toggleContent()">展开全文 ▾</div>
    </div>
  </div>
  <?php endif; ?>

  <!-- 附件（提前，便于快速下载/预览） -->
  <?php if (!empty($attachments)): ?>
  <div class="m-card">
    <div class="m-card-hd"><span><i class="bi bi-paperclip me-1 text-primary"></i>合同附件 (<?=count($attachments)?>)</span></div>
    <div class="m-card-bd">
      <?php foreach ($attachments as $att):
        $url = $att['url'] ?? ($att['file_url'] ?? '');
        $name = $att['name'] ?? ($att['file_name'] ?? basename($url));
        $ext = strtoupper(pathinfo($name, PATHINFO_EXTENSION));
        $ptoken = preview_token($url);
        // v2.38.14：缺失附件（测试残留/已删文件）不可点开，toast 提示
        $attExists = attachment_exists((string)$url);
        if($attExists): ?>
        <a class="att-row" href="javascript:void(0)" onclick="openPreview('<?=htmlspecialchars($url, ENT_QUOTES)?>','<?=$ext?>','<?=htmlspecialchars($name, ENT_QUOTES)?>','<?=$ptoken?>')" rel="noopener">
          <div class="ic"><i class="bi bi-file-earmark-<?=in_array($ext, ['PDF']) ? 'pdf' : (in_array($ext, ['JPG','JPEG','PNG','GIF','WEBP']) ? 'image' : 'text')?>"></i></div>
          <div class="nm"><?=htmlspecialchars($name)?></div>
          <div class="dl"><i class="bi bi-download"></i></div>
        </a>
        <?php else: ?>
        <a class="att-row" href="javascript:void(0)" onclick="toast('文件缺失或已被删除')" style="opacity:.55" rel="noopener">
          <div class="ic"><i class="bi bi-file-earmark-text"></i></div>
          <div class="nm"><?=htmlspecialchars($name)?> <span style="color:var(--m-danger)">（文件缺失）</span></div>
        </a>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- 甲乙方 -->
  <div class="m-card">
    <div class="m-card-hd"><span><i class="bi bi-people me-1 text-primary"></i>甲乙方</span></div>
    <div class="m-card-bd">
      <?php
      // 我方身份反推：v2.46.0 起强制「对方侧关联档案」，仅一侧关联时我方必在另一侧；
      // 两侧同有关联/同无关联（历史自由输入数据）不标注，避免误标。
      $__aRel = !empty($contract['party_a_customer_id']) || !empty($contract['party_a_supplier_id']);
      $__bRel = !empty($contract['party_b_customer_id']) || !empty($contract['supplier_id']);
      $__mineA = $__bRel && !$__aRel; // 仅乙方关联档案 → 对方=乙方 → 我方=甲方
      $__mineB = $__aRel && !$__bRel; // 仅甲方关联档案 → 对方=甲方 → 我方=乙方
      $__aText = $__mineA ? '甲方（我方）' : '甲方（' . (!empty($contract['party_a_customer_id']) ? '客户' : (!empty($contract['party_a_supplier_id']) ? '供应商' : '外部')) . '）';
      $__bText = $__mineB ? '乙方（我方）' : '乙方（' . (!empty($contract['party_b_customer_id']) ? '客户' : (!empty($contract['supplier_id']) ? '供应商' : '外部')) . '）';
      ?>
      <div class="party">
        <div class="role"><?=$__aText?></div>
        <div class="pn"><?=htmlspecialchars($contract['party_a_name'] ?: '—')?></div>
        <?php if (!empty($contract['party_a_contact']) || !empty($contract['party_a_phone'])): ?>
          <div style="font-size:13px;color:var(--m-text-3);margin-top:4px;">
            <?=htmlspecialchars($contract['party_a_contact'] ?? '')?><?=!empty($contract['party_a_contact']) && !empty($contract['party_a_phone']) ? ' · ' : ''?><?=!empty($contract['party_a_phone']) ? phone_link($contract['party_a_phone'], false) : ''?>
          </div>
        <?php endif; ?>
        <!-- v2.46.0：甲方往来摘要（甲方客户/甲方供应商，点击跳相对方全景） -->
        <?php if(!empty($party360['customer_a']) || !empty($party360['supplier_a'])):
            $pa = $party360['customer_a'] ?? $party360['supplier_a']; $ptypea = !empty($party360['customer_a']) ? 'customer' : 'supplier';
        ?>
        <a href="/m/party/<?=$ptypea?>/<?=$pa['id']?>" style="display:flex;align-items:center;gap:8px;margin-top:8px;padding:8px 10px;background:var(--m-brand-light);border-radius:10px;text-decoration:none">
          <span style="flex:1;font-size:12px;color:var(--m-text-2)">往来余额 <b style="color:var(--m-text-1)">¥<?=number_format((float)$pa['balance'],0)?></b> · 待<?=$pa['role']==='应收'?'收':'付'?></span>
          <span style="font-size:12px;color:var(--m-brand)">往来全景 <i class="bi bi-chevron-right"></i></span>
        </a>
        <?php endif; ?>
      </div>
      <div class="party">
        <div class="role"><?=$__bText?></div>
        <div class="pn"><?=htmlspecialchars($contract['party_b_name'] ?: '—')?></div>
        <?php if (!empty($contract['party_b_contact']) || !empty($contract['party_b_phone']) || !empty($contract['party_b_credit_code'])): ?>
          <div style="font-size:13px;color:var(--m-text-3);margin-top:4px;">
            <?=htmlspecialchars($contract['party_b_contact'] ?? '')?><?=!empty($contract['party_b_contact']) && (!empty($contract['party_b_phone']) || !empty($contract['party_b_credit_code'])) ? ' · ' : ''?><?=!empty($contract['party_b_phone']) ? phone_link($contract['party_b_phone'], false) : ''?><?=!empty($contract['party_b_phone']) && !empty($contract['party_b_credit_code']) ? ' · ' : ''?><?=htmlspecialchars($contract['party_b_credit_code'] ?? '')?>
          </div>
        <?php endif; ?>
        <!-- v2.38.14：乙方往来摘要（360 能力内嵌，点击跳相对方全景） -->
        <?php if(!empty($party360['customer']) || !empty($party360['supplier'])):
            $p = $party360['customer'] ?? $party360['supplier']; $ptype = !empty($party360['customer']) ? 'customer' : 'supplier';
        ?>
        <a href="/m/party/<?=$ptype?>/<?=$p['id']?>" style="display:flex;align-items:center;gap:8px;margin-top:8px;padding:8px 10px;background:var(--m-brand-light);border-radius:10px;text-decoration:none">
          <span style="flex:1;font-size:12px;color:var(--m-text-2)">往来余额 <b style="color:var(--m-text-1)">¥<?=number_format((float)$p['balance'],0)?></b> · 待<?=$p['role']==='应收'?'收':'付'?></span>
          <span style="font-size:12px;color:var(--m-brand)">往来全景 <i class="bi bi-chevron-right"></i></span>
        </a>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- 回款 / 付款 -->
  <div class="m-card" id="payments">
    <div class="m-card-hd"><span><i class="bi bi-cash-coin me-1 text-primary"></i><?=$dirText === '采购' ? '付款' : '回款'?>计划</span></div>
    <div class="m-card-bd">
      <?php if ($tradeAttr === 0): ?>
        <div class="m-empty" style="padding:24px 0;">非交易合同，不计入收支</div>
      <?php elseif (empty($payments)): ?>
        <div class="m-empty" style="padding:24px 0;">暂无回款计划</div>
      <?php else: ?>
        <div class="pay-summary">
          <span>已<?=$dirText === '采购' ? '付' : '收'?> <b class="amt-in">¥<?=number_format($paidAmount, 0)?></b></span>
          <span>计划总额 <b>¥<?=number_format($planAmount, 0)?></b></span>
          <span>回款率 <b><?=$pct?>%</b></span>
        </div>
        <div class="pay-progress"><span style="width:<?=$pct?>%"></span></div>
        <div style="margin-top:8px;">
          <?php foreach ($payments as $p):
            $ps = $payStatusMap[$p['status'] ?? 'PENDING'] ?? ['t' => $p['status'], 'c' => 'muted'];
            $pin = ($p['payment_type'] ?? 'RECEIVABLE') === 'RECEIVABLE';
            $confirmable = (($p['status'] ?? '') === 'PENDING' || ($p['status'] ?? '') === 'OVERDUE');  // 待收/逾期均可确认
            $isOverdue = (($p['status'] ?? '') === 'OVERDUE');
            $isPaid = (($p['status'] ?? '') === 'PAID');
          ?>
            <div class="m-row pay-row">
              <div class="main">
                <div class="t">计划 <?=htmlspecialchars($p['planned_date'] ?? '-')?></div>
                <div class="s"><?=htmlspecialchars($p['description'] ?: ($pin ? '应收' : '应付'))?><?=!empty($p['actual_date']) ? ' · 实收 ' . htmlspecialchars($p['actual_date']) : ''?></div>
                <?php if (($can_payment ?? false) && $confirmable): ?>
                  <?php if ($isOverdue): ?>
                  <!-- 逾期回款：红色警示样式突出确认入口（从今日提醒进入详情页的刚需路径） -->
                  <button class="m-btn m-btn-sm m-btn-danger" style="margin-top:6px" onclick="confirmPayment(<?=intval($p['id'])?>, <?=floatval($p['amount'] ?? 0)?>)"><i class="bi bi-check-lg"></i> 确认到账（逾期）</button>
                  <?php else: ?>
                  <!-- 待收/待付：确认到账 -->
                  <button class="m-btn m-btn-sm m-btn-ok" style="margin-top:6px;" onclick="confirmPayment(<?=intval($p['id'])?>, <?=floatval($p['amount'] ?? 0)?>)"><i class="bi bi-check-lg"></i> 确认到账</button>
                  <?php endif; ?>
                <?php elseif (($can_payment ?? false) && $isPaid): ?>
                  <!-- Phase 2.4：撤销确认按钮（仅已收/已付状态显示） -->
                  <button class="m-btn m-btn-sm m-btn-ghost" style="margin-top:6px;" onclick="revokePayment(<?=intval($p['id'])?>)"><i class="bi bi-arrow-counterclockwise"></i> 撤销</button>
                <?php endif; ?>
              </div>
              <div class="aside">
                <div class="amt pay-amt <?=$pin ? 'amt-in' : 'amt-out'?>">¥<?=number_format((float)($p['amount'] ?? 0), 0)?></div>
                <span class="m-tag m-tag-<?=$ps['c']?>"><?=$ps['t']?></span>
                <?php if (!empty($p['parent_id'])): ?>
                  <!-- 部分确认拆出的剩余待收子记录：醒目标记，便于辨认这是拆分后的剩余款 -->
                  <span class="m-tag m-tag-rest">剩余回款</span>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- 审批进度 -->
  <?php if (!empty($approvals)): ?>
  <div class="m-card">
    <div class="m-card-hd"><span><i class="bi bi-shield-check me-1 text-primary"></i>审批记录</span></div>
    <div class="m-card-bd">
      <?php foreach ($approvals as $a):
        $ast = $apprStatusMap[strtoupper($a['status'] ?? '')] ?? ['t' => $a['status'], 'c' => 'muted'];
      ?>
        <div class="approval-row">
          <div class="top">
            <span class="fl"><?=htmlspecialchars($a['flow_name'] ?? '审批流')?></span>
            <span class="m-tag m-tag-<?=$ast['c']?>"><?=$ast['t']?></span>
          </div>
          <div class="meta">提交人 <?=htmlspecialchars($a['submitter_name'] ?? '-')?> · <?=htmlspecialchars(($a['submitted_at'] ?? '') ?: '-')?></div>
          <!-- CR-09：展示节点意见（含驳回意见），撤回后历史可查 -->
          <?php if (!empty($a['nodes'])): ?>
          <div class="mt-1 ps-2 border-start">
            <?php foreach ($a['nodes'] as $n): ?>
            <div class="small py-1 text-secondary">
              <?=htmlspecialchars($n['node_name'] ?? '节点')?> · <?=htmlspecialchars($n['approver_name'] ?? '-')?>
              · <?= approval_action_label($n['action'] ?? '') ?>
              <?php if (!empty($n['comment'])): ?><div class="text-muted">意见：<?=htmlspecialchars($n['comment'])?></div><?php endif; ?>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- 自定义字段 -->
  <?php if (!empty($customSchema)): ?>
  <div class="m-card">
    <div class="m-card-hd"><span><i class="bi bi-card-list me-1 text-primary"></i>补充信息</span></div>
    <div class="m-card-bd">
      <?php foreach ($customSchema as $f):
        $key = $f['key'] ?? '';
        $label = $f['label'] ?? $key;
        $val = $customValues[$key] ?? '';
        if ($val === '' || $val === null) continue;
      ?>
        <div class="m-kv"><div class="k"><?=htmlspecialchars($label)?></div><div class="v"><?=htmlspecialchars(is_array($val) ? json_encode($val, JSON_UNESCAPED_UNICODE) : (string)$val)?></div></div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

</div>

<!-- 底部操作栏 -->
<?php if ($pending_approval_id > 0): ?>
  <div class="m-actionbar">
    <a class="m-btn m-btn-brand" href="/m/approval/<?=$pending_approval_id?>"><i class="bi bi-shield-check"></i> 去审批</a>
  </div>
<?php elseif (in_array($status, ['DRAFT', 'REJECTED'])): ?>
  <div class="m-actionbar">
    <?php if ($can_edit): ?><a class="m-btn m-btn-ghost" href="/m/contract/<?=$contract['id']?>/edit"><i class="bi bi-pencil"></i> 编辑</a><?php endif; ?>
    <?php if (!empty($can_submit_approval)): ?><a class="m-btn m-btn-brand" href="/m/contract/<?=$contract['id']?>/approval"><i class="bi bi-send"></i> 提交审批</a><?php endif; ?>
  </div>
<?php elseif ($can_status_change ?? false): ?>
  <!-- Phase 2.3：状态手动变更（与桌面端 statusTransition 端点一致）
       2026-08-03 方案确认：底部仅「续约（主操作）+ 更多」，状态动作收进底部动作面板
       （复用 m-sheet 组件），破坏性操作（终止/归档）红色标出 -->
  <div class="m-actionbar">
    <a class="m-btn m-btn-brand" href="javascript:void(0)" onclick="mRenew(<?=intval($contract['id'] ?? 0)?>)">续约</a>
    <button class="m-btn m-btn-ghost" onclick="openStatusSheet()">更多</button>
  </div>
  <!-- 状态变更动作面板（底部滑出） -->
  <div class="m-sheet-mask" id="statusSheetMask">
    <div class="m-sheet">
      <div class="m-sheet-hd" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
        <span style="font-size:17px;font-weight:600;">状态变更</span>
        <button type="button" id="statusSheetClose" style="border:none;background:transparent;font-size:14px;color:var(--m-text-3);" aria-label="关闭"><i class="bi bi-x-lg"></i></button>
      </div>
      <?php foreach ($actions as $act):
        $actLabel = $statusMap[$act]['t'] ?? $act;
        if ($act === 'EXECUTING' && in_array($status, ['ARCHIVED', 'COMPLETED', 'EXPIRED', 'TERMINATED'])) {
          // 反向操作：取消归档/取消完成/取消到期/取消终止（动作式文案天然无歧义）
          $actLabel = match ($status) {
            'ARCHIVED' => '取消归档',
            'COMPLETED' => '取消完成',
            'EXPIRED' => '取消到期',
            'TERMINATED' => '取消终止',
            default => $actLabel,
          };
        } else {
          // 正向状态变更：状态名 → 动作式文案（已完成→标记完成、已到期→标记到期、已终止→终止合同、已归档→归档）
          $actLabel = match ($act) {
            'COMPLETED' => '标记完成',
            'EXPIRED' => '标记到期',
            'TERMINATED' => '终止合同',
            'ARCHIVED' => '归档合同',
            'EXECUTING' => '恢复执行',
            default => $actLabel,
          };
        }
        // 破坏性操作（终止/归档/取消终止/取消归档）红色标出，普通操作默认色
        $isDestructive = in_array($act, ['TERMINATED', 'ARCHIVED'], true)
          && !($act === 'EXECUTING' && in_array($status, ['ARCHIVED', 'COMPLETED', 'EXPIRED', 'TERMINATED']));
      ?>
      <button type="button" class="m-sheet-item<?= $isDestructive ? ' m-sheet-item-danger' : '' ?>" onclick="statusTransition('<?=htmlspecialchars($act)?>','<?=htmlspecialchars($actLabel)?>');closeStatusSheet()"><?=htmlspecialchars($actLabel)?></button>
      <?php endforeach; ?>
      <button type="button" class="m-sheet-item" onclick="closeStatusSheet()" style="color:var(--m-text-3)">取消</button>
    </div>
  </div>
<?php endif; ?>

<?php if(!empty($execution_cc) && !empty($execution_cc['needs_ack']) && empty($execution_cc['acknowledged_at'])): ?>
<div class="m-actionbar"><button class="m-btn m-btn-brand" onclick="ackExecution()"><i class="bi bi-check2-square"></i> 确认知悉</button></div>
<?php endif; ?>

<!-- Phase 2.7：附件预览 Lightbox 提取为独立 JS（消除与审批详情页的重复代码） -->
<script src="<?=asset_url('js/mobile/lightbox.js')?>"></script>
<script>
// 合同 ID 传给 JS（Phase 2.7：替代内联 PHP echo）
window._contractId = <?=intval($contract['id'] ?? 0)?>;

// 合同概要展开/收起
function toggleContent(){
  var c = document.getElementById('cc'), t = document.getElementById('cct');
  if(c.classList.contains('expanded')){ c.classList.remove('expanded'); t.textContent='展开全文 ▾'; }
  else { c.classList.add('expanded'); t.textContent='收起 ▴'; }
}

/* Phase 2.3：合同状态手动变更（复用 mobile-common.js confirmAndPost） */
function statusTransition(newStatus, label){
  confirmAndPost('确认将合同状态变更为「' + label + '」？',
    '/ajax/contract/status-transition',
    { id: window._contractId, status: newStatus }, 800);
}
// 2026-08-03 方案：状态变更动作面板（m-sheet）开关——点「更多」滑出，遮罩/关闭按钮/取消可关
function openStatusSheet(){
  var m = document.getElementById('statusSheetMask');
  if(m) m.classList.add('show');
}
function closeStatusSheet(){
  var m = document.getElementById('statusSheetMask');
  if(m) m.classList.remove('show');
}
function ackExecution(){confirmAndPost('确认已知悉该合同进入执行？','/ajax/contract/execution-ack',{id:window._contractId},600);}
(function(){
  var m = document.getElementById('statusSheetMask');
  if(!m) return;
  m.addEventListener('click', function(e){ if(e.target === m) closeStatusSheet(); });
  var c = document.getElementById('statusSheetClose');
  if(c) c.addEventListener('click', closeStatusSheet);
})();
// 续约：生成续约草案后跳移动端编辑页（csrfToken 由 mobile-common.js 提供）
// 2026-08-03 修复：①原生 confirm 在钉钉 webview 无反应 → mConfirm；②后端返回 PC 路径 /contract/<id>/edit → 跳移动端 /m/contract/<id>/edit
function mRenew(id){
  mConfirm('确定基于当前合同生成续约草案？生成后可编辑并走审批流程。', function(){
    fetch('/ajax/contract/' + id + '/renew', {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': csrfToken(), 'X-Requested-With': 'XMLHttpRequest' }
    }).then(function(r){ return r.json(); }).then(function(res){
      if(res.code === 0){ toast(res.msg || '续约草案已生成'); setTimeout(function(){ location.href = '/m/contract/' + res.data.id + '/edit'; }, 600); }
      else { toast(res.msg || '续约失败'); }
    });
  });
}
</script>

<!-- 确认收款：底部弹层（合同详情页回款，部分确认金额 ≤ 应收，剩余自动拆为待收） -->
<div class="m-sheet-mask" id="payConfirmMask">
  <div class="m-sheet">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px">
      <span style="font-size:17px;font-weight:600">确认收款</span>
      <i class="bi bi-x-lg" id="payConfirmClose" style="font-size:20px;color:var(--m-text-3)"></i>
    </div>
    <div class="m-field">
      <label for="payConfirmAmt">收款金额（元）</label><input type="number" class="m-input" id="payConfirmAmt" min="0.01" step="0.01">
    </div>
    <div style="display:flex;gap:10px">
      <div class="m-field" style="flex:1;margin-bottom:0"><label for="payConfirmMethod">收款方式</label>
        <select class="m-input" id="payConfirmMethod">
          <option value="">- 请选择 -</option>
          <?php foreach (dict_options('payment_method') as $__mc => $__ml): ?><option value="<?=htmlspecialchars($__mc)?>"><?=htmlspecialchars($__ml)?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="m-field" style="flex:1;margin-bottom:0"><label for="payConfirmDate">实际日期</label><input type="date" class="m-input" id="payConfirmDate"></div>
    </div>
    <input type="hidden" id="payConfirmId" value="0">
    <div class="text-danger small" id="payConfirmErr" style="min-height:18px"></div>
    <button class="m-btn m-btn-brand" id="payConfirmBtn" style="width:100%;margin-top:4px">确认收款</button>
  </div>
</div>

<script>
/* Phase 2.4：确认回款到账 —— 弹窗录入金额/方式/日期，而非直接确认 */
function confirmPayment(pid, amount){
  document.getElementById('payConfirmId').value = pid;
  document.getElementById('payConfirmAmt').value = amount || 0;
  document.getElementById('payConfirmAmt').max = amount || 0;
  document.getElementById('payConfirmDate').value = new Date().toISOString().slice(0,10);
  document.getElementById('payConfirmErr').textContent = '';
  document.getElementById('payConfirmMask').classList.add('show');
}
function closePayConfirm(){ var m = document.getElementById('payConfirmMask'); if(m) m.classList.remove('show'); }

(function(){
  var mask = document.getElementById('payConfirmMask');
  if(!mask) return;
  mask.addEventListener('click', function(e){ if(e.target === mask) closePayConfirm(); });
  var closeBtn = document.getElementById('payConfirmClose');
  if(closeBtn) closeBtn.addEventListener('click', closePayConfirm);
  var btn = document.getElementById('payConfirmBtn');
  if(btn) btn.addEventListener('click', function(){
    var id = parseInt(document.getElementById('payConfirmId').value || '0', 10);
    var amt = parseFloat(document.getElementById('payConfirmAmt').value || '0');
    if(!(amt > 0)){ document.getElementById('payConfirmErr').textContent = '请输入正确的收款金额'; return; }
    if(!document.getElementById('payConfirmMethod').value){ document.getElementById('payConfirmErr').textContent = '请选择收款方式'; return; }
    this.disabled = true; this.textContent = '提交中…';
    var p = new URLSearchParams();
    p.append('id', id);
    p.append('confirm_amount', amt);
    p.append('payment_method', document.getElementById('payConfirmMethod').value);
    p.append('actual_date', document.getElementById('payConfirmDate').value);
    fetch('/ajax/payment/confirm', {method:'POST', body:p, headers:{'X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':csrfToken()}})
      .then(function(r){ return r.json(); })
      .then(function(res){
        btn.disabled = false; btn.textContent = '确认收款';
        if(res.code === 0){
          toast('确认收款成功');
          closePayConfirm();
          setTimeout(function(){ location.reload(); }, 600);
        } else {
          document.getElementById('payConfirmErr').textContent = res.msg || '操作失败';
        }
      })
      .catch(function(){ btn.disabled = false; btn.textContent = '确认收款'; toast('网络异常，请重试'); });
  });
})();

/* Phase 2.4：撤销回款确认 */
function revokePayment(pid){
  confirmAndPost('确认撤销该笔回款记录？撤销后状态将恢复为待收。',
    '/ajax/payment/revoke',
    { id: pid }, 800);
}
</script>

<!-- Phase 2.3/2.4：操作反馈 -->
<div class="m-toast" id="toast"></div>
<div class="m-loading" id="loading" style="display:none"><div class="m-spinner"></div></div>

<!-- Lightbox 遮罩 -->
<div class="lb-overlay" id="lbOverlay">
  <div class="lb-bar">
    <span id="lbTitle">附件预览</span>
    <button class="lb-close" onclick="closePreview()">&times;</button>
  </div>
  <div class="lb-body" id="lbBody"></div>
</div>
<?php $tab = 'contract'; include __DIR__ . '/_foot.php'; ?>
