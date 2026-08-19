<?php
// ===========================================================================
// 合同详情页 — v2.4.1
// ===========================================================================
$title='合同详情'; $menu_active='contract';
include __DIR__.'/../layout/header.php'; ?>
<?php $attachments = json_decode($contract['file_url']??'[]', true) ?: []; ?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
<h4><i class="bi bi-file-text"></i> 合同详情</h4>
<div class="d-flex gap-1 flex-wrap">
<?php if(!empty($execution_cc) && !empty($execution_cc['needs_ack']) && empty($execution_cc['acknowledged_at'])): ?>
<button class="btn btn-warning btn-sm" onclick="ackExecution()"><i class="bi bi-check2-square"></i> 确认知悉</button>
<?php endif; ?>
<?php if(in_array($contract['status'],['DRAFT','REJECTED']) && !empty($can_edit)): ?>
<a href="/contract/<?=$contract['id']?>/edit" class="btn btn-primary btn-sm"><i class="bi bi-pencil"></i> 编辑</a>
<?php endif; ?>
<?php if(in_array($contract['status'],['DRAFT','REJECTED']) && !empty($can_submit_approval)): ?>
<a href="/approval/create/<?=$contract['id']?>" class="btn btn-warning btn-sm"><i class="bi bi-send"></i> 提交审批</a>
<?php endif; ?>
<?php if(in_array('ARCHIVED', \app\common\logic\ContractLogic::getAvailableActions($contract['status'])) && !empty($can_edit)): ?>
<button class="btn btn-outline-secondary btn-sm" onclick="doArchive()"><i class="bi bi-archive"></i> 归档</button>
<?php endif; ?>
<?php if(in_array($contract['status'],['EXECUTING','ARCHIVED','EXPIRED']) && !empty($can_renew)): ?>
<button class="btn btn-info btn-sm" onclick="doRenew(<?=$contract['id']?>)"><i class="bi bi-recycle"></i> 续约</button>
<?php endif; ?>
<?php if(!empty($can_delete)): ?>
<?php if(in_array($contract['status'],['DRAFT','REJECTED','ARCHIVED','COMPLETED','EXPIRED','TERMINATED'])): ?>
<button class="btn btn-outline-danger btn-sm" onclick="delContract(<?=$contract['id']?>)"><i class="bi bi-trash"></i> 删除</button>
<?php elseif(!empty($is_super_admin) && $contract['status']==='PENDING_APPROVAL'): ?>
<!-- v2.47.2：超管强制删除审批中合同（审批人/提交人已失效的僵尸审批清理出口，后端终结审批实例后删除） -->
<button class="btn btn-outline-danger btn-sm" onclick="delContract(<?=$contract['id']?>, true)" title="超管强制删除：将终结进行中的审批流程并删除该合同"><i class="bi bi-trash"></i> 强制删除</button>
<?php else: ?>
<button class="btn btn-outline-danger btn-sm" disabled title="仅草稿/已驳回/已归档/已完成/已到期/已终止状态可删除"><i class="bi bi-trash"></i> 删除</button>
<?php endif; ?>
<?php endif; ?>
<?php if($contract['status']==='EXECUTING' && !empty($can_edit)): ?>
<button class="btn btn-outline-danger btn-sm" onclick="doTerminate()"><i class="bi bi-stop-circle"></i> 终止</button>
<?php endif; ?>
<?php
// v2.43.4：审批中合同补「撤回审批」直达入口（审批页已有，此处合同页直达；撤回后合同回草稿可编辑/删除）
$__pendingApprovalId = 0;
if (!empty($approvals)) {
    foreach ($approvals as $__a) {
        if (($__a['status'] ?? '') === 'PENDING') { $__pendingApprovalId = (int)($__a['id'] ?? 0); break; }
    }
}
?>
<?php if($__pendingApprovalId > 0 && !empty($can_submit_approval)): ?>
<button class="btn btn-outline-warning btn-sm" onclick="doRecallApproval(<?=$__pendingApprovalId?>)"><i class="bi bi-arrow-counterclockwise"></i> 撤回审批</button>
<?php endif; ?>
<a href="/contract" class="btn btn-outline-secondary btn-sm">返回</a>
</div></div>

<div class="row"><div class="col-lg-8">

<!-- 基本信息 -->
<div class="card stat-card mb-3"><div class="card-header bg-white"><h5 class="mb-0">基本信息</h5></div><div class="card-body">
<table class="table table-sm"><tbody>
<tr><td class="text-muted" width="80">合同编号</td><td><strong><?=htmlspecialchars($contract['contract_no'])?></strong></td><td class="text-muted" width="80">状态</td><td><?=contract_status_label($contract['status'])?></td></tr>
<tr><td class="text-muted">标题</td><td colspan="3"><strong><?=htmlspecialchars($contract['title'])?></strong></td></tr>
<tr><td class="text-muted">业务类型</td><td><?=htmlspecialchars(dict_enabled('business_type')[$contract['business_type'] ?? ''] ?? ($contract['business_type'] ?? ''))?></td><td class="text-muted">金额</td><td><strong>¥<?=format_money($contract['amount'])?></strong></td></tr>
<?php
// v2.28.2：签约主体与建议审批流名由 ContractController::detail() 注入（$company / $flowName），视图层不再 Db::name
$__isNonTrade = ($contract['trade_attr'] ?? 1) == 0;
$__dir = $contract['direction'] ?? '';
$__dirBadge = $__isNonTrade
    ? '<span class="pc-tag pc-tag-muted">非交易 · 不计入收支</span>'
    : ($__dir === 'purchase'
        ? '<span class="pc-tag pc-tag-warn">采购 · 我方付款</span>'
        : ($__dir === 'sales' ? '<span class="pc-tag pc-tag-ok">销售 · 我方收款</span>' : '<span class="pc-tag pc-tag-muted">未定</span>'));
// 深化：销售=我方为甲方（收款），采购=我方为乙方（付款）
$__position = $__isNonTrade ? '非交易（不计入收支）' : ($__dir === 'purchase' ? '乙方（我方付款）' : ($__dir === 'sales' ? '甲方（我方收款）' : '未定'));
// $__flowName / $__company 由 Controller 注入（v2.28.2 下沉）
$__flowName = $flowName ?? '';
$__company  = $company ?? null;
?>
<tr><td class="text-muted">合同方向</td><td colspan="3"><?=$__dirBadge?> <span class="text-muted small">我方为：<strong><?=$__position?></strong></span><?php if($__flowName): ?> · 建议审批流：<?=htmlspecialchars($__flowName)?><?php endif; ?></td></tr>
<tr><td class="text-muted">关联项目</td><td colspan="3"><?php if(!empty($contract['project_id']) && !empty($contract['project_name'])): ?><a href="/project/<?=$contract['project_id']?>" class="pc-tag pc-tag-info text-decoration-none"><i class="bi bi-folder2-open me-1"></i><?=htmlspecialchars($contract['project_name'])?></a><?php else: ?><span class="text-muted">-</span><?php endif; ?></td></tr>
<?php if($__company): ?>
<tr><td class="text-muted">签约主体</td><td colspan="3"><span class="pc-tag pc-tag-info"><i class="bi bi-building me-1"></i><?=htmlspecialchars($__company['name'])?></span><?php if($__company['is_default']): ?> <span class="pc-tag pc-tag-muted" style="font-size:10px">默认主体</span><?php endif; ?></td></tr>
<tr><td class="text-muted">开票资料</td><td colspan="3"><div class="small text-muted lh-sm">
  统一信用代码：<?=htmlspecialchars($__company['unified_social_credit_code']?:'-')?><br>
  <?php if(!empty($__company['short_name'])): ?>简称：<?=htmlspecialchars($__company['short_name'])?><br><?php endif; ?>
</div></td></tr>
<?php endif; ?>
<?php
// v2.46.0：甲乙方类型标签（客户/供应商/外部）——与移动端同源
// v2.51.x：我方身份反推（与移动端同口径）——仅一侧关联档案时我方在另一侧，我方侧类型显示「我方」而非「外部」
$__aRel = !empty($contract['party_a_customer_id']) || !empty($contract['party_a_supplier_id']);
$__bRel = !empty($contract['party_b_customer_id']) || !empty($contract['supplier_id']);
$__aType = (!$__aRel && $__bRel) ? '我方' : (!empty($contract['party_a_customer_id']) ? '客户' : (!empty($contract['party_a_supplier_id']) ? '供应商' : '外部'));
$__bType = (!$__bRel && $__aRel) ? '我方' : (!empty($contract['party_b_customer_id']) ? '客户' : (!empty($contract['supplier_id']) ? '供应商' : '外部'));
?>
<tr><td class="text-muted">甲方</td><td><?=htmlspecialchars($contract['party_a_name']??'-')?> <span class="pc-tag pc-tag-muted" style="font-size:10px"><?=$__aType?></span></td><td class="text-muted">联系人</td><td><?=htmlspecialchars($contract['party_a_contact']??'-')?><?php if(!empty($contract['party_a_phone'])): ?> · <?=phone_link($contract['party_a_phone'], false)?><?php endif; ?></td></tr>
<tr><td class="text-muted">乙方</td><td><?=htmlspecialchars($contract['party_b_name']??'-')?> <span class="pc-tag pc-tag-muted" style="font-size:10px"><?=$__bType?></span></td><td class="text-muted">联系人</td><td><?=htmlspecialchars($contract['party_b_contact']??'-')?><?php if(!empty($contract['party_b_phone'])): ?> · <?=phone_link($contract['party_b_phone'], false)?><?php endif; ?></td></tr>
<?php // v2.38.14：乙方往来摘要（360 能力内嵌 PC 详情，与移动端同源）——乙方客户/关联供应商时显示余额+往来全景入口
if(!empty($party360['customer']) || !empty($party360['supplier'])):
    $__p = $party360['customer'] ?? $party360['supplier'];
    $__pt = !empty($party360['customer']) ? 'customer' : 'supplier';
?>
<tr><td class="text-muted">乙方往来</td><td colspan="3">
  <span class="ms-3">往来余额 <strong>¥<?=number_format((float)$__p['balance'],0)?></strong> · 待<?=$__p['role']==='应收'?'收':'付'?></span>
  <a href="/party/<?=$__pt?>/<?=$__p['id']?>" class="btn btn-sm btn-outline-primary ms-3"><i class="bi bi-compass"></i> 往来全景</a>
</td></tr>
<?php endif; ?>
<?php // v2.46.0：甲方往来摘要（甲方客户/甲方供应商）——对称展示
if(!empty($party360['customer_a']) || !empty($party360['supplier_a'])):
    $__pa = $party360['customer_a'] ?? $party360['supplier_a'];
    $__pta = !empty($party360['customer_a']) ? 'customer' : 'supplier';
?>
<tr><td class="text-muted">甲方往来</td><td colspan="3">
  <span class="ms-3">往来余额 <strong>¥<?=number_format((float)$__pa['balance'],0)?></strong> · 待<?=$__pa['role']==='应收'?'收':'付'?></span>
  <a href="/party/<?=$__pta?>/<?=$__pa['id']?>" class="btn btn-sm btn-outline-primary ms-3"><i class="bi bi-compass"></i> 往来全景</a>
</td></tr>
<?php endif; ?>
<tr><td class="text-muted">生效</td><td><?=htmlspecialchars($contract['effective_date']??'-')?></td><td class="text-muted">到期</td><td><?=htmlspecialchars($contract['expiry_date']??'-')?></td></tr>
</tbody></table></div></div>

<!-- 合同概要 -->
<?php if(!empty($contract['content'])): ?>
<div class="card stat-card mb-3"><div class="card-header bg-white"><h5 class="mb-0">合同概要</h5></div>
<div class="card-body"><div style="max-height:300px;overflow-y:auto;white-space:pre-wrap"><?=htmlspecialchars($contract['content'])?></div></div></div>
<?php endif; ?>

<!-- P1-C 模板结构化字段 -->
<?php if(!empty($custom_schema) && !empty($custom_values)): ?>
<div class="card stat-card mb-3"><div class="card-header bg-white"><h5 class="mb-0"><i class="bi bi-ui-checks-grid me-1"></i>结构化字段</h5></div>
<div class="card-body"><table class="table table-sm mb-0"><tbody>
<?php foreach($custom_schema as $__f):
    $__k = $__f['key'] ?? '';
    if($__k === '' || !isset($custom_values[$__k]) || $custom_values[$__k] === '') continue;
    $__v = $custom_values[$__k];
    // select 类型：把 value 映射回 label
    if(($__f['type'] ?? '')==='select' && !empty($__f['options']) && is_array($__f['options'])){
        foreach($__f['options'] as $__op){
            if(is_array($__op)){ if((string)($__op['value']??$__op['label']??'')===(string)$__v){ $__v=$__op['label']??$__v; break; } }
        }
    }
?>
<tr><td class="text-muted" style="width:180px"><?=htmlspecialchars($__f['label'] ?? $__k)?></td><td><?=htmlspecialchars((string)$__v)?></td></tr>
<?php endforeach; ?>
</tbody></table></div></div>
<?php endif; ?>

<!-- 合同附件（附件预览优化：图片缩略图网格 + 统一预览 Modal + 下载按钮） -->
<?php if(!empty($attachments)):
$imgExts = ['jpg','jpeg','png','gif','webp','bmp','svg'];
$docExts = ['pdf','doc','docx','xls','xlsx','ppt','pptx','txt','md','csv'];
$extIcons = ['pdf'=>'bi-filetype-pdf text-danger','doc'=>'bi-filetype-doc text-primary','docx'=>'bi-filetype-docx text-primary',
    'xls'=>'bi-filetype-xls text-success','xlsx'=>'bi-filetype-xlsx text-success',
    'ppt'=>'bi-filetype-ppt text-warning','pptx'=>'bi-filetype-pptx text-warning',
    'txt'=>'bi-filetype-txt text-secondary','md'=>'bi-filetype-md text-secondary','csv'=>'bi-filetype-csv text-success',
    'zip'=>'bi-file-zip text-muted','rar'=>'bi-file-zip text-muted','7z'=>'bi-file-zip text-muted'];
$thumbs = []; $files = [];
foreach($attachments as $idx => $a){
  $name = $a['name'] ?? ($a['file_name'] ?? '未知文件');
  $url  = $a['url']  ?? ($a['file_url']  ?? '');
  $size = (int)($a['size'] ?? 0);
  $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
  $isImg = in_array($ext, $imgExts, true);
  $exists = $url ? attachment_exists($url) : false;
  $item = ['idx'=>$idx,'name'=>$name,'url'=>$url,'ext'=>$ext,'size'=>$size,'exists'=>$exists,
    'sizeText'=>$size ? ($size<1024?$size.'B':($size<1048576?round($size/1024,1).'KB':round($size/1048576,1).'MB')) : '',
    'icon'=>$extIcons[$ext] ?? 'bi-file-earmark text-secondary',
    'isImg'=>$isImg,'isDoc'=>in_array($ext, $docExts, true),'ptoken'=>$exists?preview_token($url):''];
  if($isImg && $exists) $thumbs[] = $item; else $files[] = $item;
}
?>
<div class="card stat-card mb-3" id="pcAttachCard">
<div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
  <h5 class="mb-0"><i class="bi bi-paperclip"></i> 合同附件 <span class="badge bg-secondary ms-1"><?=count($attachments)?></span></h5>
  <div class="small text-muted"><?=count($thumbs)?> 张图片 · <?=count($files)?> 个文件</div>
</div>
<div class="card-body p-0">
  <!-- 图片缩略图网格（PC 端大优化：先看缩略图，点击放大） -->
  <?php if(!empty($thumbs)): ?>
  <div class="attach-thumbs">
    <?php foreach($thumbs as $t): ?>
    <div class="attach-thumb-item" data-idx="<?=$t['idx']?>" onclick="openAttachPreview(<?=$t['idx']?>)">
      <div class="thumb-wrap">
        <img src="<?=htmlspecialchars($t['url'])?>" alt="<?=htmlspecialchars($t['name'])?>" loading="lazy"
          onload="this.style.opacity=1" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
        <div class="thumb-fallback" style="display:none"><i class="bi bi-file-image"></i></div>
      </div>
      <div class="thumb-name" title="<?=htmlspecialchars($t['name'])?>"><?=htmlspecialchars($t['name'])?></div>
      <div class="thumb-meta">
        <span><?=$t['sizeText']?></span>
        <div class="thumb-actions">
          <button type="button" class="btn btn-sm btn-outline-primary" onclick="event.stopPropagation();openAttachPreview(<?=$t['idx']?>)"><i class="bi bi-eye"></i> 预览</button>
          <button type="button" class="btn btn-sm btn-outline-secondary" onclick="event.stopPropagation();Attachments.download(Attachments.normalizeUrl('<?=htmlspecialchars($t['url'])?>','<?=htmlspecialchars($t['ptoken'])?>'),'<?=htmlspecialchars($t['name'])?>')" aria-label="下载"><i class="bi bi-download"></i></button>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- 文档/其他列表 -->
  <?php if(!empty($files)): ?>
  <div class="attach-files-list<?=!empty($thumbs)?' border-top':''?>">
    <?php foreach($files as $f): ?>
    <div class="attach-file-row">
      <div class="attach-file-icon">
        <?php if($f['exists']): ?>
          <i class="bi <?=$f['icon']?>"></i>
        <?php else: ?>
          <i class="bi bi-file-earmark-excel text-danger"></i>
        <?php endif; ?>
      </div>
      <div class="attach-file-main">
        <?php if($f['exists']): ?>
        <div class="attach-file-name" onclick="openAttachPreview(<?=$f['idx']?>)" title="<?=htmlspecialchars($f['name'])?>"><?=htmlspecialchars($f['name'])?></div>
        <?php else: ?>
        <div class="attach-file-name text-muted" title="<?=htmlspecialchars($f['name'])?>"><?=htmlspecialchars($f['name'])?> <span class="text-danger small">（文件缺失）</span></div>
        <?php endif; ?>
        <div class="attach-file-meta">
          <?php if($f['sizeText']): ?><span><?=$f['sizeText']?></span><?php endif; ?>
          <?php if($f['ext']): ?><span class="text-uppercase">· <?=$f['ext']?></span><?php endif; ?>
        </div>
      </div>
      <div class="attach-file-actions">
        <?php if($f['exists']): ?>
        <button type="button" class="btn btn-sm btn-outline-primary" onclick="openAttachPreview(<?=$f['idx']?>)"><i class="bi bi-eye"></i> 预览</button>
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="Attachments.download(Attachments.normalizeUrl('<?=htmlspecialchars($f['url'])?>','<?=htmlspecialchars($f['ptoken'])?>'),'<?=htmlspecialchars($f['name'])?>')"><i class="bi bi-download"></i> 下载</button>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
</div>

<!-- 附件预览 Modal（v2.44.3 起改为图片画廊专用：prev/next 只在图片附件间切换；
     文档预览走 office-preview 新标签页 / 不可预览格式直接下载兜底，不再进入本弹窗混用） -->
<div class="modal fade" id="attachPreviewModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-xl">
    <div class="modal-content" style="min-height:60vh">
      <div class="modal-header d-flex align-items-center gap-3">
        <div class="flex-grow-1 text-truncate">
          <span id="apmName" class="fw-semibold">附件预览</span>
          <small id="apmMeta" class="text-muted ms-2"></small>
        </div>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="apmPrevBtn" onclick="apmPrev()" aria-label="上一个"><i class="bi bi-chevron-left"></i></button>
        <span id="apmIndex" class="small text-muted"></span>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="apmNextBtn" onclick="apmNext()" aria-label="下一个"><i class="bi bi-chevron-right"></i></button>
        <button type="button" class="btn btn-sm btn-outline-primary ms-1" id="apmDownloadBtn"><i class="bi bi-download"></i> 下载</button>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-0" id="apmBody" style="min-height:55vh;background:#1a1a1a;display:flex;align-items:center;justify-content:center;position:relative;">
        <div id="apmLoading" class="text-center text-white-50" style="padding:40px"><div class="spinner-border spinner-border-sm mb-2"></div><div class="small">加载中…</div></div>
      </div>
    </div>
  </div>
</div>

<script>
window.__ATTACHMENTS__ = <?php
  $out = [];
  foreach(array_merge($thumbs,$files) as $it){
    $out[] = ['idx'=>count($out),'name'=>$it['name'],'url'=>$it['url'],'ext'=>$it['extUpper'] ?? strtoupper($it['ext']),'size'=>$it['size'],'sizeText'=>$it['sizeText'],'isImg'=>$it['isImg'],'isPdf'=>strtolower($it['ext'])==='pdf','exists'=>$it['exists'],'ptoken'=>$it['ptoken']];
  }
  // 按原始顺序排列
  usort($out, function($a,$b) use ($attachments){ return 0; });
  $ordered = [];
  foreach($attachments as $idx=>$a){
    $name = $a['name'] ?? ($a['file_name'] ?? '未知文件');
    $url  = $a['url']  ?? ($a['file_url']  ?? '');
    $size = (int)($a['size'] ?? 0);
    $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $exists = attachment_exists((string)$url);
    $ordered[] = [
      'idx'=>count($ordered),'name'=>$name,'url'=>$url,'ext'=>$ext,'extUpper'=>strtoupper($ext),
      'size'=>$size,'sizeText'=>$size ? ($size<1024?$size.'B':($size<1048576?round($size/1024,1).'KB':round($size/1048576,1).'MB')) : '',
      'isImg'=>in_array($ext,$imgExts,true),'isPdf'=>$ext==='pdf','isDoc'=>in_array($ext,$docExts,true),
      'exists'=>$exists,'ptoken'=>$exists?preview_token($url):'',
    ];
  }
  // 安全：JSON_HEX_* 防止附件名含 </script> 等载荷闭合 script 块（存储型 XSS 防护，与项目 JS 注入惯例一致）
  echo json_encode($ordered, JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);
?>;
(function(){
  // v2.44.3：图片画廊与文档预览分离——imgList 仅含图片附件，prev/next 只在图片间切换；
  // 文档附件点击直接走 openDocPreview（office-preview 新标签页 / 下载兜底），不再混入图片弹窗
  var all = window.__ATTACHMENTS__ || [];
  var imgList = all.filter(function(a){ return a.isImg; });
  var current = 0;
  var modal = null;
  function getModal(){
    if(!modal){ try{ modal = new bootstrap.Modal(document.getElementById('attachPreviewModal')); }catch(e){} }
    return modal;
  }
  function clearBody(){
    var b = document.getElementById('apmBody');
    b.innerHTML = '<div id="apmLoading" class="text-center text-white-50" style="padding:40px"><div class="spinner-border spinner-border-sm mb-2"></div><div class="small">加载中…</div></div>';
  }
  function showImg(i){
    // 图片画廊：prev/next 只在图片附件列表内切换（v2.44.3 与文档预览分离）
    if(i<0 || i>=imgList.length) return;
    current = i;
    var it = imgList[i];
    document.getElementById('apmName').textContent = it.name;
    document.getElementById('apmMeta').textContent = [it.sizeText, it.extUpper].filter(Boolean).join(' · ');
    document.getElementById('apmIndex').textContent = (i+1) + ' / ' + imgList.length;
    document.getElementById('apmPrevBtn').disabled = i<=0;
    document.getElementById('apmNextBtn').disabled = i>=imgList.length-1;
    var dlBtn = document.getElementById('apmDownloadBtn');
    // v2.43.5 补丁③：下载走带令牌的 /preview 代理（外部浏览器无会话也能免登录下载）
    dlBtn.onclick = function(){ Attachments.download(Attachments.normalizeUrl(it.url, it.ptoken), it.name); };
    clearBody();
    var body = document.getElementById('apmBody');
    var loader = document.getElementById('apmLoading');
    var timeout = setTimeout(function(){
      if(loader && body.contains(loader)){
        loader.innerHTML = '<div class="spinner-border spinner-border-sm mb-2"></div><div class="small text-warning">加载较慢，正在重试…</div>';
      }
    }, 6000);
    setTimeout(function(){
      if(loader && body.contains(loader)){
        body.innerHTML = '<div class="text-white-50 text-center p-4">加载超时，请<a href="'+encodeURI(it.url)+'" target="_blank" class="text-decoration-underline text-white ms-1">新窗口打开</a> 或下载后查看。</div>';
        clearTimeout(timeout);
      }
    }, 15000);
    var img = new Image();
    img.style.maxWidth = '100%'; img.style.maxHeight = '75vh'; img.style.display = 'none';
    img.onload = function(){ clearTimeout(timeout); body.innerHTML = ''; img.style.display='block'; body.appendChild(img); };
    img.onerror = function(){
      clearTimeout(timeout);
      body.innerHTML = '<div class="text-white-50 text-center p-4"><i class="bi bi-exclamation-triangle fs-1"></i><div class="mt-2">图片加载失败，可能已被删除</div><div class="mt-3"><button class="btn btn-sm btn-outline-light me-2" onclick="Attachments.download(Attachments.normalizeUrl(&quot;'+encodeURI(it.url)+'&quot;,&quot;'+encodeURI(it.ptoken||'')+'&quot;),&quot;'+encodeURI(it.name)+'&quot;)">下载原文件</button></div></div>';
    };
    // v2.43.7 修复：与资料库/移动端口径一致——图片预览走带令牌 /preview 代理（原直连 /uploads 无会话时 401 → 弹窗空白）
    img.src = Attachments.normalizeUrl(it.url, it.ptoken);
  }
  // 文档预览：与图片画廊分离——PDF/DOCX/XLSX 走 office-preview 新标签页；
  // 其余不可内嵌格式（doc/xls 等 /preview 回 octet-stream）直接下载兜底（与资料库/移动端口径一致）
  function openDocPreview(it){
    if(it.isPdf || it.extUpper === 'DOCX' || it.extUpper === 'XLSX'){
      // v2.43.5 补丁②：预览页令牌改顶层 p/t 参数（f 嵌套参数在外部浏览器 URL 规范化下可能丢令牌 → 跳登录）
      var docUrl = '/m/office-preview?p='+encodeURIComponent(it.url)+(it.ptoken?'&t='+encodeURIComponent(it.ptoken):'')+'&name='+encodeURIComponent(it.name);
      window.open(docUrl, '_blank', 'noopener');
    } else {
      Attachments.download(Attachments.normalizeUrl(it.url, it.ptoken), it.name);
    }
  }
  window.openAttachPreview = function(idx){
    var it = all[idx];
    if(!it || !it.exists) return;
    if(it.isImg){
      var gi = -1;
      for(var k=0;k<imgList.length;k++){ if(imgList[k].idx===idx){ gi=k; break; } }
      if(gi<0) return;
      var m = getModal();
      if(!m) { window.open(Attachments.normalizeUrl(it.url, it.ptoken), '_blank', 'noopener'); return; }
      m.show();
      // 延迟到 shown.bs.modal 后再渲染，避免弹窗内图片尺寸计算错误
      document.getElementById('attachPreviewModal').addEventListener('shown.bs.modal', function onShown(){
        document.getElementById('attachPreviewModal').removeEventListener('shown.bs.modal', onShown);
        showImg(gi);
      });
    } else {
      openDocPreview(it);
    }
  };
  window.apmPrev = function(){ showImg(current-1); };
  window.apmNext = function(){ showImg(current+1); };
  document.addEventListener('keydown', function(e){
    if(!document.getElementById('attachPreviewModal').classList.contains('show')) return;
    if(e.key === 'ArrowLeft') apmPrev();
    else if(e.key === 'ArrowRight') apmNext();
    else if(e.key === 'Escape') { try{ getModal().hide(); }catch(_){} }
  });
})();
</script>

<!-- 附件卡片 CSS（PC 端附件预览大优化专用） -->
<style>
.attach-thumbs{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:12px;padding:12px}
.attach-thumb-item{background:#fff;border:1px solid #eef0f3;border-radius:10px;overflow:hidden;cursor:pointer;transition:all .15s;display:flex;flex-direction:column}
.attach-thumb-item:hover{border-color:#0d6efd;box-shadow:0 2px 8px rgba(13,110,253,.12);transform:translateY(-1px)}
.thumb-wrap{width:100%;aspect-ratio:4/3;background:#f7f8fa;display:flex;align-items:center;justify-content:center;overflow:hidden;position:relative}
.thumb-wrap img{width:100%;height:100%;object-fit:cover;opacity:0;transition:opacity .25s}
.thumb-fallback{position:absolute;inset:0;display:none;align-items:center;justify-content:center;font-size:36px;color:#adb5bd;background:#f7f8fa}
.thumb-name{padding:6px 10px 2px;font-size:12px;color:#212529;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.thumb-meta{padding:4px 10px 10px;display:flex;justify-content:space-between;align-items:center;gap:6px;font-size:11px;color:#adb5bd}
.thumb-actions{display:flex;gap:4px}
.attach-files-list{background:#fff}
.attach-file-row{display:flex;align-items:center;gap:10px;padding:10px 14px;border-bottom:1px solid #eef0f3}
.attach-file-row:last-child{border-bottom:none}
.attach-file-icon{width:40px;height:40px;flex:none;border-radius:8px;background:#f7f8fa;display:flex;align-items:center;justify-content:center;font-size:22px;color:#6c757d}
.attach-file-main{flex:1;min-width:0}
.attach-file-name{font-size:13px;color:#212529;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;cursor:pointer}
.attach-file-name:hover{color:#0d6efd;text-decoration:underline}
.attach-file-meta{font-size:11px;color:#adb5bd;margin-top:2px;display:flex;gap:6px}
.attach-file-actions{display:flex;gap:6px;flex:none}
@media(max-width:575.98px){
  .attach-thumbs{grid-template-columns:repeat(auto-fill,minmax(110px,1fr));gap:8px;padding:8px}
  .thumb-actions{display:none}
  .attach-file-actions .btn{padding:.15rem .3rem;font-size:.75rem}
}
</style>
<?php endif; ?>

<!-- 回款/付款记录（v2.40.0 P1-4：采购合同登记付款，方向联动标题与文案） -->
<?php $__payWord = $__dir === 'purchase' ? '付款' : '回款'; $__payType = $__dir === 'purchase' ? 'PAYABLE' : 'RECEIVABLE'; ?>
<div class="card stat-card mb-3"><div class="card-header bg-white d-flex justify-content-between align-items-center">
<h5 class="mb-0"><?=$__payWord?>记录</h5><?php if(!empty($can_pay)): ?><button class="btn btn-primary btn-sm" <?= $__isNonTrade ? 'disabled title="非交易合同无需登记'. $__payWord .'"' : 'onclick="showAddPayment()"' ?>><i class="bi bi-plus-lg"></i> 添加<?=$__payWord?></button><button class="btn btn-outline-primary btn-sm ms-1" <?= $__isNonTrade ? 'disabled title="非交易合同无需登记'. $__payWord .'"' : 'onclick="copyPrevPayment()"' ?>><i class="bi bi-arrow-repeat"></i> 复制自上期</button><?php endif; ?><?php if($__isNonTrade): ?><span class="text-muted small ms-2">非交易合同不计入收支，无需登记<?=$__payWord?></span><?php endif; ?></div>
<div class="card-body p-0" id="paymentList" style="max-height:300px;overflow-y:auto"><div class="text-center py-3 text-muted small">加载中...</div></div></div>

<!-- 发票记录（P0 修复 2026-08-02：恢复前端入口；F1 2026-08-02：申请→审批→开票，按钮按 invoice:apply 权限显示） -->
<div class="card stat-card mt-3">
  <div class="card-header bg-white d-flex justify-content-between align-items-center">
    <h5 class="mb-0">发票记录</h5>
    <div class="d-flex align-items-center">
      <span class="text-muted small me-2" id="invoiceSum"></span>
      <?php if(!empty($can_apply_invoice) && ($contract['direction'] ?? '') === 'sales' && (int)($contract['trade_attr'] ?? 1) === 1): ?><button class="btn btn-primary btn-sm" onclick="showAddInvoice()"><i class="bi bi-plus-lg"></i> 申请开票</button><?php endif; ?>
    </div>
  </div>
  <div class="card-body p-0" id="invoiceList" style="max-height:260px;overflow-y:auto"><div class="text-center py-3 text-muted small">加载中...</div></div>
</div>

</div><div class="col-lg-4">

<!-- 操作时间线 -->
<div class="card stat-card">
<div class="card-header bg-white"><h5 class="mb-0"><i class="bi bi-clock-history"></i> 操作记录</h5></div>
<div class="card-body p-0" style="max-height:500px;overflow-y:auto">
<?php if(!empty($timeline)): ?>
<div style="position:relative;padding:8px 0 8px 24px">
<div style="position:absolute;left:8px;top:12px;bottom:12px;width:2px;background:#e9ecef"></div>
<?php foreach($timeline as $e): ?>
<div style="position:relative;padding:6px 0 6px 12px;margin-bottom:4px">
<div style="position:absolute;left:-20px;top:10px;width:14px;height:14px;border-radius:50%;background:<?=$e['color']?>;border:2px solid #fff;box-shadow:0 0 0 2px <?=$e['color']?>33"></div>
<div class="small"><strong><?=htmlspecialchars($e['title'])?></strong> <span class="text-muted"><?=$e['time']?></span></div>
<?php if($e['detail']): ?><div class="small text-muted"><?=htmlspecialchars($e['detail'])?></div><?php endif; ?>
</div>
<?php endforeach; ?>
</div>
<?php else: ?><p class="text-center text-muted py-3 small">暂无操作记录</p><?php endif; ?>
</div></div>

<div class="card stat-card mt-3"><div class="card-header bg-white"><h5 class="mb-0">审批记录</h5></div><div class="card-body p-0">
<?php if(!empty($approvals)): foreach($approvals as $a): ?>
<div class="p-2 border-bottom">
  <small class="text-muted"><?=htmlspecialchars($a['flow_name'])?> - <?=htmlspecialchars($a['submitter_name'])?></small>
  <span class="float-end"><?=approval_status_label($a['status'])?></span>
  <!-- CR-09：展示每个审批节点的意见（含驳回意见），撤回后历史不丢失 -->
  <?php if(!empty($a['nodes'])): ?>
  <div class="mt-1 ps-2 border-start">
    <?php foreach($a['nodes'] as $n): ?>
    <div class="small py-1">
      <span class="text-secondary"><?=htmlspecialchars($n['node_name'])?></span>
      · <?=htmlspecialchars($n['approver_name'] ?? '—')?>
      · <?=approval_action_label($n['action']); ?>
      <?php if(!empty($n['comment'])): ?><div class="text-muted">意见：<?=htmlspecialchars($n['comment'])?></div><?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
<?php endforeach; else: ?><p class="text-muted small p-3 mb-0">暂无审批记录</p><?php endif; ?>
</div></div>

</div></div>

<!-- 添加回款/付款 Modal（v2.40.0 P1-4：采购合同为付款） -->
<div class="modal fade" id="payModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
<div class="modal-header"><h5 class="modal-title">添加<?=$__payWord?></h5><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body">
<form id="payForm"><input type="hidden" name="contract_id" value="<?=$contract['id']?>">
<input type="hidden" name="payment_type" value="<?=$__payType?>">
<div class="mb-2"><label class="form-label" for="fPayAmount">金额 <span class="text-danger">*</span></label><input type="number" step="0.01" name="amount" id="fPayAmount" class="form-control" required></div>
<div class="mb-2"><label class="form-label" for="fPayPlannedDate">计划日期</label><input type="date" name="planned_date" id="fPayPlannedDate" class="form-control"></div>
<div class="mb-2"><label class="form-label" for="fPayMethod">付款方式 <span class="text-danger">*</span></label><select name="payment_method" id="fPayMethod" class="form-select">
<option value="">- 请选择 -</option>
<?php foreach (dict_options('payment_method') as $code => $label): ?><option value="<?=htmlspecialchars($code)?>"><?=htmlspecialchars($label)?></option><?php endforeach; ?>
</select></div>
<div class="mb-2"><label class="form-label" for="fPayMilestone">里程碑</label><select name="milestone" id="fPayMilestone" class="form-select">
<option value="">请选择</option>
<?php foreach (dict_options('payment_milestone') as $code => $label): ?><option value="<?=htmlspecialchars($code)?>"><?=htmlspecialchars($label)?></option><?php endforeach; ?>
</select></div>
<div class="mb-2"><label class="form-label" for="fPayDescription">说明</label><input type="text" name="description" id="fPayDescription" class="form-control" placeholder="如：第一期款"></div>
</form>
<!-- v2.41.0：回款/付款分期计划 —— 实际合同分期多样，无预置模板；支持按比例拆分或逐期添加 -->
<div class="mt-2 pt-2 border-top">
  <label class="form-label small text-muted mb-1" for="tplCustomRatio">分期计划（合同金额 ¥<?=number_format((float)($contract['amount']??0),0)?>，期数/比例/金额均可自定义）</label>
  <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
    <span class="small text-muted">按比例拆分</span>
    <input type="text" class="form-control form-control-sm" style="width:190px" id="tplCustomRatio" placeholder="如 10,30,20,40（逗号分隔各期比例）" title="逗号分隔各期比例，将按比例自动拆分合同金额生成各期">
    <button type="button" class="btn btn-sm btn-outline-primary" onclick="genTemplate()"><i class="bi bi-scissors"></i> 拆分</button>
    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="addTplRow()"><i class="bi bi-plus-lg"></i> 添加一期</button>
  </div>
  <div class="small text-muted mb-1">每期可改比例/金额（自动联动）/日期/里程碑/说明；比例合计可为 100 或按实际约定（最多 10 期）</div>
  <div id="tplRows"></div>
  <div class="d-flex justify-content-between align-items-center mt-1" id="tplSaveWrap" style="display:none">
    <span class="small" id="tplTotal"></span>
    <button type="button" class="btn btn-sm btn-primary" onclick="saveTpl()">保存全部</button>
  </div>
</div>
</div><div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">取消</button><button class="btn btn-primary" onclick="addPayment()">添加</button></div>
</div></div></div>

<!-- 确认收款/付款 Modal（对齐财务页字段：金额/方式/日期/备注；方式用下拉，与添加一致） -->
<div class="modal fade" id="payConfirmModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
<div class="modal-header"><h5 class="modal-title" id="payConfirmTitle">确认<?=$__payWord?></h5><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body">
<div class="mb-2"><label class="form-label" for="payConfirmAmt"><?=$__payWord?>金额（元）<span class="text-danger">*</span></label><input type="number" step="0.01" min="0.01" id="payConfirmAmt" class="form-control"></div>
<div class="mb-2"><label class="form-label" for="payConfirmMethod"><?=$__payWord?>方式 <span class="text-danger">*</span></label><select id="payConfirmMethod" class="form-select">
<option value="">- 请选择 -</option>
<?php foreach (dict_options('payment_method') as $code => $label): ?><option value="<?=htmlspecialchars($code)?>"><?=htmlspecialchars($label)?></option><?php endforeach; ?>
</select></div>
<div class="mb-2"><label class="form-label" for="payConfirmDate">实际<?=$__payWord?>日期</label><input type="date" id="payConfirmDate" class="form-control"></div>
<div class="mb-2"><label class="form-label" for="payConfirmDesc">备注</label><input type="text" id="payConfirmDesc" class="form-control" placeholder="选填"></div>
<input type="hidden" id="payConfirmId" value="0">
<div class="text-danger small" id="payConfirmErr" style="min-height:18px"></div>
</div><div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">取消</button><button class="btn btn-primary" onclick="submitConfirmPay()">确认<?=$__payWord?></button></div>
</div></div></div>

<!-- 申请开票 Modal（H6c：复用 InvoiceFormConfig 配置化表单——字段/联动/自定义由后台「系统设置→发票表单」统一维护；主体默认合同主体、关联合同固定当前合同） -->
<div class="modal fade" id="invoiceModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
<div class="modal-header"><h5 class="modal-title">申请开票</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
<div class="mb-2"><label class="form-label" for="fContractInfo">关联合同</label><input type="text" class="form-control" id="fContractInfo" value="<?=htmlspecialchars(($contract['contract_no'] ?? '').' '.($contract['title'] ?? ''))?>" readonly></div>
<div class="row g-2" id="applyFields">
<!-- 2026-08-02：税率绑定开票主体，表单不渲染税率组件；隐藏字段承接主体税率供价税拆分与提交（后端强制从公司读取，防篡改） -->
<input type="hidden" name="tax_rate" id="applyTaxRate" value="0.06">
<?= $apply_fields ?? '' ?>
</div>
<!-- H2：含税金额价税拆分实时展示（含税 = 不含税 + 税额） -->
<div class="small text-muted mt-1" id="applyTaxCalc" style="display:none"></div>
<div class="text-danger small mt-2" id="applyErr"></div>
</div>
<div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">取消</button><button class="btn btn-primary" onclick="submitDetailApply()"><i class="bi bi-send"></i> 提交申请</button></div>
</div></div></div>

<!-- 开票 Modal（申请→填写发票号置已开票，P0 恢复） -->
<div class="modal fade" id="issueModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
<div class="modal-header"><h5 class="modal-title">确认开票</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
<div class="mb-2"><label class="form-label" for="issueNo">发票号码 <span class="text-danger">*</span></label><input type="text" id="issueNo" class="form-control" placeholder="如 FP2026080001"></div>
<div class="mb-2"><label class="form-label" for="issueDate">开票日期</label><input type="date" id="issueDate" class="form-control"></div>
</div>
<div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">取消</button><button class="btn btn-success" onclick="issueInvoice()">确认开票</button></div>
</div></div></div>

<script>
// esc() 已统一下沉至 public/static/js/app.js（全局 window.esc），此处不再重复定义
// UX 门控：回款/发票行内操作按钮按后端守卫同口径（payment:create / invoice:create）控制
var __canPay = <?= !empty($can_pay) ? 'true' : 'false' ?>;
var __canCollectionFollow = <?= !empty($can_collection_follow) ? 'true' : 'false' ?>;
var __canIssue = <?= !empty($can_issue) ? 'true' : 'false' ?>;
function doArchive(){pcConfirm({message:'确定归档？'}).then(function(ok){if(!ok)return;$ajax('/ajax/archive/<?=$contract['id']?>',{method:'POST',loading:false}).then(res=>{showToast(res.msg || '操作完成', res.code === 0 ? 'success' : 'error');if(res.code===0)location.reload();}).catch(function(){});});}
// v2.47.2：超管强制删除审批中合同（force=true 时提示将终结进行中的审批流程）
function delContract(id, force){pcConfirm({message:force ? '确定强制删除该审批中合同？将终结其进行中的审批流程并删除，进入回收站可恢复' : '确定删除该合同？删除后进入回收站，可在数据回收站恢复或彻底清除',danger:true}).then(function(ok){if(!ok)return;$ajax('/ajax/contract/delete',{method:'POST',body:new URLSearchParams({id:id}),loading:false}).then(res=>{showToast(res.msg || '操作完成', res.code === 0 ? 'success' : 'error');if(res.code===0)location.href='/contract';}).catch(function(){});});}
// v2.43.4：执行中合同「终止」入口（后端 statusTransition 已有，补前端按钮；
// 存在逾期未结回款时后端会拦截并提示，前端弹窗预先提醒）
function doTerminate(){pcConfirm({message:'确定终止该合同？终止后需另走新合同，请确认无逾期未结回款。',danger:true}).then(function(ok){if(!ok)return;$ajax('/ajax/contract/status-transition',{method:'POST',body:new URLSearchParams({id:<?=(int)$contract['id']?>,status:'TERMINATED'}),loading:false}).then(res=>{showToast(res.msg || '操作完成', res.code === 0 ? 'success' : 'error');if(res.code===0)location.reload();}).catch(function(){});});}
function ackExecution(){ $ajax('/ajax/contract/execution-ack',{method:'POST',body:new URLSearchParams({id:<?=(int)$contract['id']?>}),loading:false}).then(function(res){showToast(res.msg||'操作完成',res.code===0?'success':'error');if(res.code===0)location.reload();}).catch(function(){});}
var __changePartyTimer=null;
function searchChangeParty(input,side){clearTimeout(__changePartyTimer);var box=document.getElementById(side==='a'?'changePartyAResults':'changePartyBResults');var q=input.value.trim();if(!q){box.innerHTML='';return;}__changePartyTimer=setTimeout(function(){ $ajax('/ajax/party/search?q='+encodeURIComponent(q),{loading:false}).then(function(res){var rows=res.data||[];box.innerHTML=rows.slice(0,10).map(function(p){return '<button type="button" class="list-group-item list-group-item-action py-1" data-id="'+p.id+'" data-type="'+esc(p.party_type||'')+'" data-name="'+esc(p.name||'')+'" onclick="chooseChangeParty(this,\''+side+'\')">'+esc(p.name||'')+' <small class="text-muted">'+esc(p.type_name||'')+'</small></button>';}).join('');}).catch(function(){});},250);}
function chooseChangeParty(btn,side){var f=document.getElementById('changeForm'),type=btn.dataset.type,id=btn.dataset.id;f.elements['party_'+side+'_name'].value=btn.dataset.name;if(side==='a'){f.elements.party_a_customer_id.value=type==='customer'?id:0;f.elements.party_a_supplier_id.value=type==='supplier'?id:0;}else{f.elements.party_b_customer_id.value=type==='customer'?id:0;f.elements.supplier_id.value=type==='supplier'?id:0;}document.getElementById(side==='a'?'changePartyAResults':'changePartyBResults').innerHTML='';}
function addChangePlan(){var box=document.getElementById('changePlanRows');box.insertAdjacentHTML('beforeend','<div class="row g-2 mb-2 change-plan-row"><div class="col-3"><input type="number" min="0.01" step="0.01" class="form-control form-control-sm cp-amount" placeholder="金额"></div><div class="col-3"><input type="date" class="form-control form-control-sm cp-date"></div><div class="col-5"><input class="form-control form-control-sm cp-desc" placeholder="说明/里程碑"></div><div class="col-1"><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest(\'.change-plan-row\').remove()"><i class="bi bi-x"></i></button></div></div>');}
function uploadChangeEvidence(input){if(!input.files||!input.files[0])return;var fd=new FormData();fd.append('file',input.files[0]);$ajax('/ajax/upload/contract',{method:'POST',body:fd,loadingText:'上传依据中…'}).then(function(res){if(res.code===0){document.getElementById('changeEvidenceUrl').value=JSON.stringify([res.data]);document.getElementById('changeEvidenceTip').textContent='已上传：'+res.data.name;showToast('依据附件上传成功','success');}else{showToast(res.msg||'上传失败','error');}}).catch(function(){});}
// v2.43.4：审批中合同「撤回审批」直达（仅提交人可撤回，后端校验兜底；撤回后回草稿）
function doRecallApproval(id){pcConfirm({message:'确定撤回该审批？撤回后合同回到草稿状态，可继续编辑或删除。',danger:true}).then(function(ok){if(!ok)return;$ajax('/ajax/approval/'+id+'/recall',{method:'POST',loading:false}).then(res=>{showToast(res.msg || '操作完成', res.code === 0 ? 'success' : 'error');if(res.code===0)location.reload();}).catch(function(){});});}
function doRenew(id){pcConfirm({message:'确定基于当前合同生成续约草案？生成后可编辑并走审批流程。'}).then(function(ok){if(!ok)return;$ajax('/ajax/contract/'+id+'/renew',{method:'POST',loading:false}).then(res=>{if(res.code===0){showToast('续约草案已生成', 'success');location.href=res.data.url;}else{showToast(res.msg||'续约失败','error');}}).catch(function(){});});}
function loadPayments(){var __payUrl='/ajax/payment/list/'+<?= (int)$contract['id'] ?>;$ajax(__payUrl,{loading:false}).then(res=>{if(res.code===0){var h='';if(!res.data||!res.data.length)h='<div class="text-center py-3 text-muted small">暂无<?=$__payWord?>记录</div>';else{res.data.forEach(function(p){var overdue=p.status=='OVERDUE',paid=p.status=='PAID';var isPay=p.payment_type=='PAYABLE';var stWord=isPay?'已付':'已收';var cfWord=isPay?'确认付款':'确认收款';var ops='';if(__canPay){if(paid){ops='<button class="btn btn-sm btn-outline-secondary ms-1" onclick="revokePayment('+p.id+')">撤销</button>';}else{ops=(overdue?'':'<button class="btn btn-sm btn-outline-success me-1" onclick="confirmPay('+p.id+','+p.amount+')">'+cfWord+'</button>')+'<button class="btn btn-sm btn-outline-warning" onclick="markOverdue('+p.id+')">逾期</button>'+'<button class="btn btn-sm btn-outline-danger ms-1" aria-label="删除" onclick="delPayment('+p.id+')"><i class="bi bi-trash"></i></button>';}}if(__canCollectionFollow&&!isPay&&!paid)ops+='<button class="btn btn-sm btn-outline-primary ms-1" onclick="collectionFollow('+p.id+')">记录催收</button>';h+='<div class="p-2 border-bottom '+(overdue?'bg-light':'')+'"><div class="d-flex justify-content-between align-items-center"><div><strong>¥'+parseFloat(p.amount).toLocaleString()+'</strong> <small class="text-muted">'+esc(p.description||'')+'</small><br><small class="text-muted">计划: '+esc(p.planned_date||'-')+' | 实际: '+esc(p.actual_date||'-')+'</small></div><div class="text-end">'+(paid?'<span class="pc-tag pc-tag-ok">'+stWord+(p.payment_method_label?'（'+esc(p.payment_method_label)+'）':'')+'</span>':overdue?'<span class="pc-tag pc-tag-danger">逾期</span>':'<span class="pc-tag pc-tag-warn">待'+stWord+'</span>')+ops+'</div></div></div>';});}document.getElementById('paymentList').innerHTML=h;}}).catch(function(){var el=document.getElementById('paymentList');if(el)el.innerHTML='<div class="text-center py-4 text-muted">加载失败，点击重试</div>';});}
function collectionFollow(id){var content=prompt('请输入本次催收内容');if(!content)return;var promise=prompt('客户承诺（选填）','')||'';var reason=prompt('未付款原因（选填）','')||'';$ajax('/ajax/payment/collection-add',{method:'POST',body:new URLSearchParams({payment_id:id,content:content,customer_promise:promise,reason:reason})}).then(function(r){showToast(r.msg||'操作完成',r.code===0?'success':'error');});}
function showAddPayment(){
  // v2.41.0：每次打开重置分期计划区（无预置模板，从上一次残留状态清空）
  var tr = document.getElementById('tplRows'); if (tr) tr.innerHTML = '';
  var cr = document.getElementById('tplCustomRatio'); if (cr) cr.value = '';
  var sw = document.getElementById('tplSaveWrap'); if (sw) sw.style.display = 'none';
  new bootstrap.Modal('#payModal').show();
}
// ===== v2.41.0：回款/付款分期计划（无预置模板——按比例拆分或逐期添加，比例/金额联动，可增删行） =====
var __tplAmount = <?= (float)($contract['amount'] ?? 0) ?>;
var __tplMsLabels = <?= json_encode(dict('payment_milestone'), JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?: '{}' ?>;
var __tplMsCodes = Object.keys(__tplMsLabels);   // [DOWN_PAYMENT, MID_TERM, FINAL_PAYMENT, RETENTION, ...]
var __tplDescs = ['预付款','中期款','尾款','质保金'];
function tplDefaultMs(idx){ return __tplMsCodes[Math.min(idx, __tplMsCodes.length - 1)] || ''; }
function tplDefaultDesc(idx){ return __tplDescs[Math.min(idx, __tplDescs.length - 1)] || ('第' + (idx + 1) + '期'); }
function tplRowHtml(idx, ratio){
  var amt = __tplAmount > 0 ? Math.round(__tplAmount * ratio / 100) : 0;
  var msOpts = '';
  __tplMsCodes.forEach(function(k){ msOpts += '<option value="' + k + '"' + (k === tplDefaultMs(idx) ? ' selected' : '') + '>' + esc(__tplMsLabels[k]) + '</option>'; });
  return '<div class="row g-2 align-items-center mb-2 tpl-row">'
    + '<div class="col-1 text-muted small text-end" style="align-self:center">第' + (idx + 1) + '期</div>'
    + '<div class="col-2"><input type="number" min="0" max="100" step="0.1" class="form-control form-control-sm tpl-ratio" value="' + ratio + '" title="比例%" oninput="onTplRatioChange(this)"></div>'
    + '<div class="col-3"><input type="number" step="0.01" class="form-control form-control-sm tpl-amt" value="' + amt + '" title="金额（元）" oninput="onTplAmtChange(this)"></div>'
    + '<div class="col-3"><input type="date" class="form-control form-control-sm tpl-date" title="计划日期"></div>'
    + '<div class="col-3"><div class="d-flex gap-1"><select class="form-select form-select-sm tpl-ms">' + msOpts + '</select>'
    + '<button type="button" class="btn btn-sm btn-outline-danger" title="删除该期" aria-label="删除该期" onclick="delTplRow(this)"><i class="bi bi-x-lg"></i></button></div></div>'
    + '<div class="col-12"><input type="text" class="form-control form-control-sm tpl-desc" value="' + tplDefaultDesc(idx) + '" placeholder="说明"></div>'
    + '</div>';
}
function genTemplate(){
  // v2.41.0：无预置模板，直接按输入的比例拆分（如 10,30,20,40 → 4 期）
  var custom = (document.getElementById('tplCustomRatio') || {}).value || '';
  var ratioStr = custom.trim();
  if (ratioStr === '') { showToast('请输入各期比例，如 10,30,20,40','error'); return; }
  var ratios = ratioStr.split(/[,，\s]+/).map(Number).filter(function(n){ return !isNaN(n) && n >= 0; });
  if (!ratios.length || ratios.some(function(n){ return n > 100; })) { showToast('比例格式不正确，如 10,30,20,40','error'); return; }
  if (ratios.length > 10) { showToast('最多 10 期','error'); return; }
  var html = '';
  ratios.forEach(function(r, i){ html += tplRowHtml(i, r); });
  document.getElementById('tplRows').innerHTML = html;
  document.getElementById('tplSaveWrap').style.display = 'flex';
  refreshTplTotal();
}
function addTplRow(){
  var box = document.getElementById('tplRows');
  if (box.querySelectorAll('.tpl-row').length >= 10) { showToast('最多 10 期','error'); return; }
  var n = box.querySelectorAll('.tpl-row').length;
  box.insertAdjacentHTML('beforeend', tplRowHtml(n, 0));
  document.getElementById('tplSaveWrap').style.display = 'flex';
  refreshTplTotal();
}
function delTplRow(btn){
  var row = btn.closest('.tpl-row'); if (!row) return;
  row.remove();
  refreshTplTotal();
}
function onTplRatioChange(el){
  var r = parseFloat(el.value); if (isNaN(r)) r = 0;
  var amt = __tplAmount > 0 ? Math.round(__tplAmount * r / 100) : 0;
  el.closest('.tpl-row').querySelector('.tpl-amt').value = amt;
  refreshTplTotal();
}
function onTplAmtChange(el){
  var a = parseFloat(el.value); if (isNaN(a)) a = 0;
  var ratio = __tplAmount > 0 ? Math.round(a / __tplAmount * 1000) / 10 : 0;
  el.closest('.tpl-row').querySelector('.tpl-ratio').value = ratio;
  refreshTplTotal();
}
function refreshTplTotal(){
  var rows = document.querySelectorAll('#tplRows .tpl-row');
  var totalAmt = 0, totalRatio = 0;
  rows.forEach(function(r){
    var a = parseFloat(r.querySelector('.tpl-amt').value || '0'); if (!isNaN(a)) totalAmt += a;
    var p = parseFloat(r.querySelector('.tpl-ratio').value || '0'); if (!isNaN(p)) totalRatio += p;
  });
  var el = document.getElementById('tplTotal');
  var diff = Math.abs(totalRatio - 100) > 0.1;
  el.innerHTML = '合计 <b>' + totalRatio.toFixed(1) + '%</b> / ¥' + totalAmt.toLocaleString()
    + (diff ? ' <span class="text-danger">（比例合计≠100%）</span>' : '');
}
function saveTpl(){
  var rows = document.querySelectorAll('#tplRows .tpl-row');
  if(!rows.length){ showToast('请先添加分期','error'); return; }
  var items = [];
  var ok = true;
  rows.forEach(function(r){
    var amt = parseFloat(r.querySelector('.tpl-amt').value || '0');
    if (!(amt > 0)) ok = false;
    items.push({
      amount: amt,
      planned_date: r.querySelector('.tpl-date').value,
      milestone: r.querySelector('.tpl-ms').value,
      description: r.querySelector('.tpl-desc').value.trim()
    });
  });
  if (!ok) { showToast('存在无效金额，请检查各期','error'); return; }
  var fd = new FormData();
  fd.append('contract_id', <?=$contract['id']?>);
  fd.append('payment_type', '<?=$__payType?>');
  fd.append('items', JSON.stringify(items));
  fetch('/ajax/payment/batch-add',{method:'POST',body:new URLSearchParams(fd),headers:{'X-Requested-With':'XMLHttpRequest'}}).then(r=>r.json()).then(res=>{
    showToast(res.msg || '操作完成', res.code === 0 ? 'success' : 'error');
    if(res.code===0){ bootstrap.Modal.getInstance('#payModal').hide(); loadPayments(); }
  }).catch(function(){ showToast('网络错误','error'); });
}
var __payActing=false; // P2-09：添加回款/确认收款防重复连点锁
function addPayment(){if(__payActing)return;if(!document.getElementById('fPayMethod').value){showToast('请选择付款方式','error');return;}__payActing=true;var fd=new FormData(document.getElementById('payForm'));fetch('/ajax/payment/add',{method:'POST',body:new URLSearchParams(fd),headers:{'X-Requested-With':'XMLHttpRequest'}}).then(r=>r.json()).then(res=>{__payActing=false;showToast(res.msg || '操作完成', res.code === 0 ? 'success' : 'error');if(res.code===0){bootstrap.Modal.getInstance('#payModal').hide();loadPayments();}}).catch(function(){__payActing=false;showToast('网络错误','error');});}
// M14：复制上期回款计划 —— 预填「添加回款」弹窗，用户核对计划日期后保存即生成新一期
function copyPrevPayment(){var p=new URLSearchParams({contract_id:<?=$contract['id']?>});$ajax('/ajax/payment/copy-prev',{method:'POST',body:p,loading:false}).then(function(res){if(res.code!==0){showToast(res.msg||'无上期回款计划', 'error');return;}var d=res.data||{};var f=document.getElementById('payForm');f.amount.value=d.amount||'';f.planned_date.value=d.planned_date||'';f.payment_method.value=d.payment_method||'';f.milestone.value=d.milestone||'';f.description.value=(d.description||'')+(d.description?'（复制自上期）':'复制自上期');showAddPayment();}).catch(function(){showToast('网络异常，请重试','error');});}
function confirmPay(id, amount){
  document.getElementById('payConfirmId').value = id;
  document.getElementById('payConfirmAmt').value = amount || 0;
  document.getElementById('payConfirmAmt').max = amount || 0;
  document.getElementById('payConfirmAmt').classList.remove('is-invalid'); // P1-7：打开弹窗清除错误样式
  document.getElementById('payConfirmDate').value = new Date().toISOString().slice(0,10);
  document.getElementById('payConfirmDesc').value = '';
  document.getElementById('payConfirmErr').textContent = '';
  new bootstrap.Modal('#payConfirmModal').show();
}
function submitConfirmPay(){
  if(__payActing)return;__payActing=true; // P2-09：确认收款防重复连点
  var id = parseInt(document.getElementById('payConfirmId').value || '0', 10);
  var amtEl = document.getElementById('payConfirmAmt');
  var amt = parseFloat(amtEl.value || '0');
  if(!(amt > 0)){
    amtEl.classList.add('is-invalid'); // P1-7：金额字段标红
    document.getElementById('payConfirmErr').textContent = '请输入正确的收款金额';
    __payActing=false;
    return;
  }
  amtEl.classList.remove('is-invalid');
  if(!document.getElementById('payConfirmMethod').value){
    document.getElementById('payConfirmErr').textContent = '请选择<?=$__payWord?>方式';
    __payActing=false;
    return;
  }
  var fd = new FormData();
  fd.append('id', id);
  fd.append('confirm_amount', amt);
  fd.append('payment_method', document.getElementById('payConfirmMethod').value);
  fd.append('actual_date', document.getElementById('payConfirmDate').value);
  fd.append('description', document.getElementById('payConfirmDesc').value.trim());
  document.getElementById('payConfirmErr').textContent = '提交中…';
  fetch('/ajax/payment/confirm',{method:'POST',body:new URLSearchParams(fd),headers:{'X-Requested-With':'XMLHttpRequest'}}).then(r=>r.json()).then(res=>{
    __payActing=false;
    document.getElementById('payConfirmErr').textContent = '';
    showToast(res.msg || '操作完成', res.code === 0 ? 'success' : 'error');
    if(res.code===0){ bootstrap.Modal.getInstance('#payConfirmModal').hide(); loadPayments(); }
  }).catch(function(){ __payActing=false; document.getElementById('payConfirmErr').textContent = '网络异常，请重试'; });
}
// P1-7：金额输入即清除错误样式
(function(){
  var el = document.getElementById('payConfirmAmt');
  if(el) el.addEventListener('input', function(){ el.classList.remove('is-invalid'); });
})();
function markOverdue(id){pcConfirm({message:'确认标记该笔回款为逾期？',danger:true}).then(function(ok){if(!ok)return;$ajax('/ajax/payment/overdue',{method:'POST',body:new URLSearchParams({id:id}),loading:false}).then(res=>{showToast(res.msg || '操作完成', res.code === 0 ? 'success' : 'error');if(res.code===0)loadPayments();}).catch(function(){});});}
function delPayment(id){pcConfirm({message:'确定删除？',danger:true}).then(function(ok){if(!ok)return;$ajax('/ajax/payment/delete',{method:'POST',body:new URLSearchParams({id:id}),loading:false}).then(res=>{showToast(res.msg || '操作完成', res.code === 0 ? 'success' : 'error');if(res.code===0)loadPayments();}).catch(function(){});});}
function revokePayment(id){pcConfirm({message:'确定撤销该笔收款？将回退为待确认状态',danger:true}).then(function(ok){if(!ok)return;$ajax('/ajax/payment/revoke',{method:'POST',body:new URLSearchParams({id:id}),loading:false}).then(res=>{showToast(res.msg || '操作完成', res.code === 0 ? 'success' : 'error');if(res.code===0)loadPayments();}).catch(function(){});});}

// ===== 发票管理（P0 恢复 2026-08-02：申请开票→填写发票号置已开票→红冲/作废）=====
var invTypeLabels = <?= json_encode(dict('invoice_type'), JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?: '{}' ?>;
var invIssueId = 0;
function loadInvoices(){
  $ajax('/ajax/invoice/list/<?=$contract['id']?>',{loading:false}).then(res=>{
    if(res.code!==0){document.getElementById('invoiceList').innerHTML='<div class="text-center py-3 text-muted small">'+esc(res.msg||'加载失败')+'</div>';return;}
    var list=res.data||[], h='', sum=0;
    if(!list.length){h='<div class="text-center py-3 text-muted small">暂无发票记录</div>';}
    else{list.forEach(function(v){
      if(v.status!=='VOID') sum+=parseFloat(v.amount||0);
      var st=v.status==='ISSUED'?'<span class="pc-tag pc-tag-ok">已开票</span>':v.status==='VOID'?'<span class="pc-tag pc-tag-muted">已作废</span>':v.status==='RED'?'<span class="pc-tag pc-tag-danger">已红冲</span>':v.status==='PENDING_APPROVAL'?'<span class="pc-tag pc-tag-warn">待审批</span>':v.status==='APPROVED'?'<span class="pc-tag pc-tag-info">待开票</span>':v.status==='REJECTED'?'<span class="pc-tag pc-tag-danger">已驳回</span>':v.status==='CANCELLED'?'<span class="pc-tag pc-tag-muted">已撤回</span>':'<span class="pc-tag pc-tag-info">申请中（旧）</span>';
      var act='';
      if(__canIssue){
      if(v.status==='APPROVED'||v.status==='APPLIED'){act='<button class="btn btn-sm btn-outline-success ms-1" onclick="showIssue('+v.id+')">开票</button>';}
      else if(v.status==='ISSUED'){act='<button class="btn btn-sm btn-outline-danger ms-1" onclick="redInvoice('+v.id+')">红冲</button><button class="btn btn-sm btn-outline-secondary ms-1" onclick="voidInvoice('+v.id+')">作废</button>';}
      else if(v.status==='REJECTED'||v.status==='CANCELLED'){act='<button class="btn btn-sm btn-outline-danger ms-1" aria-label="删除" onclick="delInvoice('+v.id+')"><i class="bi bi-trash"></i></button>';}
      }
      h+='<div class="p-2 border-bottom"><div class="d-flex justify-content-between align-items-center"><div><strong>¥'+parseFloat(v.amount).toLocaleString()+'</strong> <small class="text-muted">'+(v.invoice_no?esc(v.invoice_no):'未开票')+'</small><br><small class="text-muted">'+(invTypeLabels[v.invoice_type]||v.invoice_type||'')+(v.issued_date?' | '+esc(v.issued_date):'')+' | 税额 ¥'+parseFloat(v.tax_amount||0).toFixed(2)+'</small></div><div class="text-end">'+st+act+'</div></div></div>';
    });}
    document.getElementById('invoiceList').innerHTML=h;
    document.getElementById('invoiceSum').textContent = list.length ? ('开票合计 ¥'+sum.toLocaleString(undefined,{maximumFractionDigits:2})) : '';
  }).catch(function(){
    // 加载失败：面板内展示重试入口（避免"加载中"永驻）
    var el=document.getElementById('invoiceList');
    if(el)el.innerHTML='<div class="text-center py-4 text-muted"><i class="bi bi-exclamation-triangle" style="font-size:1.5rem"></i><div class="mt-2">加载失败，点击重试</div><button type="button" class="btn btn-sm btn-outline-secondary mt-2" onclick="loadInvoices()"><i class="bi bi-arrow-clockwise"></i> 重新加载</button></div>';
  });
}
// ===== 申请开票（H6c：复用 InvoiceFormConfig 配置化表单；主体默认合同主体；价税拆分实时展示） =====
function showAddInvoice(){
  document.getElementById('applyErr').textContent = '';
  var f = document.getElementById('applyFields');
  // v2.41.0：开票客户搜索选择器重置——2026-08-11 起按服务端预填恢复
  // （合同详情申请开票默认带出合同客户方 data-default-id>0；独立申请页无预填则清空，行为不变）
  // 2026-08-12：同步恢复 data-fill 的抬头/税号（data-default-title/credit），
  // 防止改选过其他客户后重开弹窗残留上次抬头/税号与选中客户不一致
  f.querySelectorAll('.cs-wrap').forEach(function(w){
    var ci = w.querySelector('.cs-input'); if(!ci) return;
    var hid = w.querySelector('.cs-id');
    var fn = w.getAttribute('data-fill-name');
    var fc = w.getAttribute('data-fill-credit');
    var ft = fn ? f.querySelector('[name="'+fn+'"]') : null;
    var tx = fc ? f.querySelector('[name="'+fc+'"]') : null;
    var defId = w.getAttribute('data-default-id') || '0';
    if(defId !== '0'){
      ci.value = w.getAttribute('data-default-name') || '';
      if(hid) hid.value = defId;
      if(ft) ft.value = w.getAttribute('data-default-title') || '';
      if(tx) tx.value = w.getAttribute('data-default-credit') || '';
    } else {
      ci.value = '';
      if(hid) hid.value = '0';
      if(ft) ft.value = '';
      if(tx) tx.value = '';
    }
  });
  // 金额清空；开票内容改为下拉（v2.38.22）：合同标题命中某选项则预选，否则保持「请选择」；开票主体默认合同主体
  var amt = f.querySelector('input[name="amount"]'); if(amt) amt.value = '';
  var desc = f.querySelector('select[name="content_desc"]');
  if(desc){
    var cTitle = '<?=htmlspecialchars($contract['title'] ?? '', ENT_QUOTES)?>';
    var matched = false;
    for (var oi = 0; oi < desc.options.length; oi++) {
      if (desc.options[oi].value === cTitle) { desc.value = cTitle; matched = true; break; }
    }
    if (!matched) desc.value = '';
  }
  var co = f.querySelector('select[name="our_company_id"]');
  if(co){
    var cid = <?=(int)($contract['our_company_id'] ?? 0)?>;
    co.value = String(cid);
    if(co.value !== String(cid)) co.selectedIndex = 1; // 合同主体缺失时回退第一项
    // 切换开票主体即时带出税率并刷新价税拆分
    co.onchange = function(){ refreshDetailApplyRate(); };
  }
  refreshDetailApplyRate();
  // 金额变化即时刷新价税拆分
  var amtEl = f.querySelector('input[name="amount"]');
  if(amtEl){ amtEl.oninput = amtEl.onchange = function(){ calcDetailTax(); }; }
  // 2026-08-04：价税拆分展示移到「含税金额」输入所在行的下方（独立整行，不再置于整表末尾）
  var tcBox = document.getElementById('applyTaxCalc');
  var amtCol = amtEl ? amtEl.closest('.col-12, .col-md-6, .col-6, [class*="col"]') : null;
  if(tcBox && amtCol && amtCol.parentNode === f){ tcBox.classList.add('col-12'); amtCol.after(tcBox); }
  new bootstrap.Modal('#invoiceModal').show();
}
/** 开票税率随主体带出：读取 option data-rate 写入隐藏税率字段，刷新价税拆分 */
function refreshDetailApplyRate(){
  var f = document.getElementById('applyFields'); if(!f) return;
  var co = f.querySelector('select[name="our_company_id"]');
  var rate = f.querySelector('input[name="tax_rate"]');
  if(!co || !rate) return;
  var opt = co.options[co.selectedIndex];
  var r = opt ? (opt.getAttribute('data-rate') || '') : '';
  if(r !== '') rate.value = r;
  calcDetailTax();
}
/** 含税金额价税拆分展示（含税 ¥A = 不含税 ¥B + 税额 ¥C，税率 X%） */
function calcDetailTax(){
  var f = document.getElementById('applyFields'), box = document.getElementById('applyTaxCalc');
  if(!f || !box) return;
  var amt = f.querySelector('[name="amount"]'), rate = f.querySelector('[name="tax_rate"]');
  if(!amt || !rate){ box.style.display = 'none'; return; }
  var a = parseFloat(amt.value), r = parseFloat(rate.value);
  if(!(a > 0) || !(r > 0) || r >= 1){ box.style.display = 'none'; return; }
  var tax = Math.round(a / (1 + r) * r * 100) / 100;
  var net = Math.round((a - tax) * 100) / 100;
  var fmt = function(n){ return n.toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); };
  box.style.display = '';
  box.innerHTML = '含税 <b>' + fmt(a) + '</b> 元 = 不含税 <b>' + fmt(net) + '</b> 元 + 税额 <b>' + fmt(tax) + '</b> 元（税率 ' + (r * 100).toLocaleString('zh-CN') + '%）';
}
function submitDetailApply(){
  var err = document.getElementById('applyErr');
  var fd = new FormData();
  fd.append('contract_id', <?=$contract['id']?>);
  document.querySelectorAll('#applyFields [name]').forEach(function(el){ fd.append(el.name, el.value); });
  // 必填校验（服务端渲染带 required 属性；P1-7：失败字段标红 + 滚动定位，输入即清除）
  var firstBad = null;
  var miss = [];
  document.querySelectorAll('#applyFields [required]').forEach(function(el){
    el.classList.remove('is-invalid');
    if(!el.value.trim()){
      el.classList.add('is-invalid');
      if(!firstBad) firstBad = el;
      var lb = el.closest('div').querySelector('.form-label');
      miss.push(lb ? lb.textContent.replace('*','').trim() : el.name);
    }
  });
  if(miss.length){
    err.textContent = '请填写：' + miss.join('、');
    if(firstBad){ try{ firstBad.focus(); }catch(e){} try{ firstBad.scrollIntoView({behavior:'smooth', block:'center'}); }catch(e){} }
    return;
  }
  err.textContent = '提交中…';
  $ajax('/ajax/invoice/add',{method:'POST',body:fd,loading:false}).then(res=>{
    err.textContent = '';
    showToast(res.msg||'操作完成',res.code===0?'success':'error');
    if(res.code===0){ bootstrap.Modal.getInstance('#invoiceModal').hide(); loadInvoices(); }
  }).catch(function(){ err.textContent = '网络异常，请重试'; });
}
// P1-7：重新输入即清除字段错误样式
(function(){
  var f = document.getElementById('applyFields');
  if(!f) return;
  f.addEventListener('input', function(e){
    if(e.target && e.target.classList) e.target.classList.remove('is-invalid');
  });
})();
function showIssue(id){invIssueId=id;document.getElementById('issueNo').value='';document.getElementById('issueDate').value=new Date().toISOString().slice(0,10);new bootstrap.Modal('#issueModal').show();}
function issueInvoice(){
  var no=document.getElementById('issueNo').value.trim();
  if(!no){showToast('请填写发票号码','error');return;}
  var body=new URLSearchParams({id:invIssueId,invoice_no:no,issued_date:document.getElementById('issueDate').value,status:'ISSUED'});
  $ajax('/ajax/invoice/update',{method:'POST',body:body,loading:false}).then(res=>{showToast(res.msg||'操作完成',res.code===0?'success':'error');if(res.code===0){bootstrap.Modal.getInstance('#issueModal').hide();loadInvoices();}}).catch(function(){});
}
function redInvoice(id){pcConfirm({message:'确认红冲该发票？将生成负数冲抵开票额度',danger:true}).then(function(ok){if(!ok)return;$ajax('/ajax/invoice/red',{method:'POST',body:new URLSearchParams({id:id}),loading:false}).then(res=>{showToast(res.msg||'操作完成',res.code===0?'success':'error');if(res.code===0)loadInvoices();}).catch(function(){});});}
function voidInvoice(id){pcConfirm({message:'确认作废该发票？',danger:true}).then(function(ok){if(!ok)return;$ajax('/ajax/invoice/void',{method:'POST',body:new URLSearchParams({id:id}),loading:false}).then(res=>{showToast(res.msg||'操作完成',res.code===0?'success':'error');if(res.code===0)loadInvoices();}).catch(function(){});});}
function delInvoice(id){pcConfirm({message:'确认删除该开票申请？',danger:true}).then(function(ok){if(!ok)return;$ajax('/ajax/invoice/delete',{method:'POST',body:new URLSearchParams({id:id}),loading:false}).then(res=>{showToast(res.msg||'操作完成',res.code===0?'success':'error');if(res.code===0)loadInvoices();}).catch(function(){});});}

// 初始加载（DOMContentLoaded 包裹：确保 footer 的 app.js 已定义全局 esc/$ajax，避免脚本顺序导致未定义，回归防护）
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function(){ loadPayments(); loadInvoices(); });
} else {
    loadPayments(); loadInvoices();
}
</script>
<!-- H6c：发票申请表单联动（form-linkage.js 通用组件：选主体→内容/税率、选客户→带出抬头税号；后台「发票表单」设计器配置） -->
<script>
window.__formRules = <?= json_encode($invoice_form_rules ?? [], JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
window.__formData = <?= json_encode(['customer_id' => $invoice_customers ?? []], JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
</script>
<script src="<?=asset_url('js/form-linkage.js')?>"></script>
<script src="<?=asset_url('js/search-picker.js')?>"></script>
<?php include __DIR__.'/../layout/footer.php'; ?>
