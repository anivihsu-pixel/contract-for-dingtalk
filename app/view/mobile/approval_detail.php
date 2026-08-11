<?php
// 移动端共享布局（Phase 1 重构 1.7）：头部与尾部由 _head.php / _foot.php 统一输出
$title = '审批详情';   // 页面标题，自动追加「 · 合同管理」
$tab = '';     // 审批 Tab 已从底部菜单移除（与顶部「待我审批」重叠），本页不高亮
$pageStyle = <<<'CSS'

/* 附件预览 Lightbox */
.lb-overlay{display:none;position:fixed;inset:0;z-index:999;background:rgba(0,0,0,.92);flex-direction:column}
.lb-overlay.show{display:flex}
.lb-bar{display:flex;justify-content:space-between;align-items:center;padding:12px 16px;color:#fff;font-size:14px;flex:none}
.lb-bar .lb-close{font-size:28px;color:#fff;background:none;border:none;padding:4px 8px;cursor:pointer}
.lb-body{flex:1;display:flex;align-items:center;justify-content:center;overflow:auto;padding:16px}
.lb-body img{max-width:100%;max-height:80vh;border-radius:8px}
.lb-body iframe{width:100%;height:80vh;border:none;border-radius:8px;background:#fff}
.lb-fallback{text-align:center;color:#ccc;max-width:280px}
.lb-fallback p{margin:12px 0;line-height:1.6}
.lb-fallback a{color:var(--m-brand);text-decoration:underline}
CSS;
include __DIR__ . '/_head.php';
?>


<!-- 顶部导航 -->
<div class="m-nav">
  <!-- 2026-08-04：返回改 history.back()，与安卓虚拟键一致（从待办中心审批消息进入时返回待办中心而非固定审批中心） -->
  <a href="javascript:history.back()" class="back" aria-label="返回"><i class="bi bi-chevron-left"></i></a>
  <div class="title">审批详情</div>
  <a href="/m/contract/<?=$detail['contract_id']?>" class="right" aria-label="查看合同"><i class="bi bi-box-arrow-up-right"></i></a>
</div>

<div class="m-page detail">
  <!-- 合同摘要 -->
  <div class="m-card">
    <div class="m-card-hd"><span><i class="bi bi-file-earmark-text me-1"></i>合同摘要</span>
      <?php
        $st = $detail['status'] ?? 'PENDING';
        $stMap = ['PENDING'=>'待审批','APPROVED'=>'已通过','REJECTED'=>'已驳回','RECALLED'=>'已撤回'];
        $stCls = ['PENDING'=>'m-tag-info','APPROVED'=>'m-tag-ok','REJECTED'=>'m-tag-danger','RECALLED'=>'m-tag-muted'];
        $isNonTrade = ($contract['trade_attr'] ?? 1) == 0;
        $isIn = !$isNonTrade && ($contract['direction'] ?? 'sales') === 'sales';
        // v2.40.1：审批进度——按流程定义渲染完整时间线（含未来节点占位），并统计已完成节点数
        $flowNodesM = json_decode($detail['nodes'] ?? '[]', true) ?: [];
        $curOrderM  = (int)($detail['current_node_order'] ?? 0);
        $totalNodesM = count($flowNodesM);
        $doneNodesM  = 0;
        $recByNodeM  = [];
        foreach (($detail['records'] ?? []) as $rM) {
            $recByNodeM[(int)($rM['node_order'] ?? 1)][] = $rM;
            if (in_array($rM['action'] ?? '', ['APPROVED', 'AUTO_APPROVED'], true)) { $doneNodesM++; }
        }
      ?>
      <span class="m-tag <?=$stCls[$st] ?? 'm-tag-muted'?>"><?=$stMap[$st] ?? $st?></span>
      <?php if($totalNodesM > 0): ?><span style="font-size:12px;color:var(--m-text-3);margin-left:6px">进度 <?=$doneNodesM?>/<?=$totalNodesM?></span><?php endif; ?>
    </div>
    <div class="m-card-bd">
      <div class="m-kv"><div class="k">合同</div><div class="v"><?=htmlspecialchars($detail['contract_title'] ?? '')?></div></div>
      <div class="m-kv"><div class="k">编号</div><div class="v"><?=htmlspecialchars($detail['contract_no'] ?? '')?></div></div>
      <div class="m-kv"><div class="k">金额</div><div class="v">
        <?php if($isNonTrade): ?><span class="m-tag m-tag-muted">非交易合同</span>
        <?php else: ?><span class="amt amt-<?=$isIn?'in':'out'?>">¥<?=number_format((float)($contract['amount'] ?? 0), 0)?></span>
          <span class="m-tag m-tag-muted" style="margin-left:6px"><?=$isIn?'应收':'应付'?></span>
        <?php endif; ?>
      </div></div>
      <div class="m-kv"><div class="k">提交人</div><div class="v"><?=htmlspecialchars($detail['submitter_name'] ?? '-')?></div></div>
      <div class="m-kv"><div class="k">审批流</div><div class="v"><?=htmlspecialchars($detail['flow_name'] ?? '-')?></div></div>
      <div class="m-kv"><div class="k">提交时间</div><div class="v"><?=htmlspecialchars($detail['submitted_at'] ?? '-')?></div></div>
    </div>
  </div>

  <!-- 合同附件（审批主要查看对象，独立突出展示，可点开预览/下载） -->
  <?php if(!empty($attachments)): ?>
  <div class="m-card">
    <div class="m-card-hd"><span><i class="bi bi-paperclip me-1 text-primary"></i>合同附件 (<?=count($attachments)?>)</span></div>
    <div class="m-card-bd">
      <div class="m-attach">
        <?php foreach($attachments as $att):
          $url = $att['url'] ?? ($att['file_url'] ?? '#');
          $name = $att['name'] ?? ($att['file_name'] ?? basename($url));
          $ext = strtoupper(pathinfo($name, PATHINFO_EXTENSION));
          $ptoken = preview_token($url); // #3 预览签名令牌，免外部浏览器登录
          $icon = in_array($ext, ['PDF']) ? 'bi-file-earmark-pdf' : (in_array($ext, ['JPG','JPEG','PNG','GIF','WEBP','BMP']) ? 'bi-file-earmark-image' : 'bi-file-earmark-text');
          $b = (int)($att['size'] ?? 0);
          $sz = $b >= 1048576 ? round($b/1048576,1).' MB' : ($b >= 1024 ? round($b/1024).' KB' : ($b>0?$b.' B':''));
          // v2.38.14：缺失附件（测试残留/已删文件）不可点开，toast 提示
          $attExists = attachment_exists((string)$url);
          if($attExists): ?>
        <a class="m-attach-row" href="javascript:void(0)" onclick="openPreview('<?=htmlspecialchars($url, ENT_QUOTES)?>','<?=$ext?>','<?=htmlspecialchars($name, ENT_QUOTES)?>','<?=$ptoken?>')" rel="noopener">
          <i class="bi <?=$icon?> m-attach-ic"></i>
          <span class="m-attach-nm"><?=htmlspecialchars($name)?></span>
          <?php if($sz): ?><span class="m-attach-sz"><?=$sz?></span><?php endif; ?>
          <i class="bi bi-box-arrow-up-right m-attach-go"></i>
        </a>
        <?php else: ?>
        <a class="m-attach-row" href="javascript:void(0)" onclick="toast('文件缺失或已被删除')" style="opacity:.55" rel="noopener">
          <i class="bi bi-file-earmark-text m-attach-ic"></i>
          <span class="m-attach-nm"><?=htmlspecialchars($name)?> <span style="color:var(--m-danger)">（文件缺失）</span></span>
        </a>
        <?php endif; ?>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- 合同正文（内联全文查看，免跳出即可决策） -->
  <div class="m-card">
    <div class="m-card-hd"><span><i class="bi bi-file-text me-1"></i>合同正文</span></div>
    <div class="m-card-bd">
      <div class="m-kv"><div class="k">甲方</div><div class="v"><?=htmlspecialchars(($our_company ?: ($contract['party_a_name'] ?? '')) ?: '-')?></div></div>
      <div class="m-kv"><div class="k">乙方</div><div class="v"><?=htmlspecialchars($contract['party_b_name'] ?? '-')?></div></div>
      <div class="m-kv"><div class="k">期限</div><div class="v"><?=htmlspecialchars((($contract['effective_date'] ?? '') . ' ~ ' . ($contract['expiry_date'] ?? '')) ?: '-')?></div></div>
      <?php if(!empty($contract['content'])): ?>
      <div class="m-kv" style="display:block">
        <div class="k" style="margin-bottom:6px">正文概要</div>
        <div class="m-content"><?=nl2br(htmlspecialchars($contract['content']))?></div>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- 审批进度（v2.40.1：按流程定义渲染完整时间线——已完成/当前/未来节点占位，驳回节点红色高亮） -->
  <div class="m-card">
    <div class="m-card-hd"><span><i class="bi bi-list-check me-1"></i>审批进度</span></div>
    <div class="m-card-bd">
      <div class="m-timeline">
        <?php foreach($flowNodesM as $iM => $fnM):
          $orderM = $iM + 1;
          $recsM  = $recByNodeM[$orderM] ?? [];
          $lastM  = $recsM ? end($recsM) : null;
          $actM   = $lastM['action'] ?? 'PENDING';
          $clsM   = 'm-step ';
          $badgeM = ['待审批', 'm-tag-muted'];
          if ($actM === 'REJECTED') {
              $clsM  .= 'reject';
              $badgeM = ['已驳回', 'm-tag-danger'];
          } elseif (in_array($actM, ['APPROVED', 'AUTO_APPROVED'], true)) {
              $clsM  .= 'done';
              $badgeM = [$actM === 'AUTO_APPROVED' ? '自动通过' : '已同意', 'm-tag-ok'];
          } elseif ($actM === 'TRANSFERRED') {
              $badgeM = ['已转交', 'm-tag-warn'];
          } elseif ($orderM === $curOrderM && $st === 'PENDING') {
              $clsM  .= 'current';
              $badgeM = ['审批中', 'm-tag-info'];
          }
        ?>
        <div class="<?=$clsM?>">
          <div class="dot"></div>
          <div class="node"><?=htmlspecialchars($fnM['name'] ?? ('节点' . $orderM))?></div>
          <div class="who"><?php if($lastM): ?><?=htmlspecialchars($lastM['approver_name'] ?? '待审批人')?>
            <span class="m-tag <?=$badgeM[1]?>" style="margin-left:6px"><?=$badgeM[0]?></span>
          <?php else: ?><span class="m-tag <?=$badgeM[1]?>"><?=$badgeM[0]?></span><?php endif; ?></div>
          <?php if(!empty($lastM['comment'])): ?><div class="cmt">意见：<?=htmlspecialchars($lastM['comment'])?></div><?php endif; ?>
          <?php if(!empty($lastM['acted_at'])): ?><div class="time"><?=htmlspecialchars($lastM['acted_at'])?></div><?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php if(!empty($detail['cc_log'])): foreach($detail['cc_log'] as $c): ?>
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

  <!-- 操作区（仅当前节点待审批人可见） -->
  <?php if($can_act): ?>
  <div style="height:8px"></div>
  <?php elseif($can_recall): ?>
  <div class="m-card"><div class="m-card-bd" style="text-align:center;color:var(--m-text-3);font-size:14px;padding:18px 0">
    ↩️ 这是你提交的审批，如需修改可撤回
  </div></div>
  <?php else: ?>
  <div class="m-card"><div class="m-card-bd" style="text-align:center;color:var(--m-text-3);font-size:14px;padding:18px 0">
    <?php if($st === 'APPROVED'): ?>✅ 该合同审批已全部通过
    <?php elseif($st === 'REJECTED'): ?>⛔ 该合同审批已被驳回
    <?php elseif($st === 'RECALLED'): ?>↩️ 提交人已撤回该审批
    <?php else: ?>你不是当前节点审批人，无需操作<?php endif; ?>
  </div></div>
  <?php endif; ?>
</div>

<!-- 底部操作栏 -->
<?php if($can_act): ?>
<div class="m-actionbar">
  <button class="m-btn m-btn-danger" id="btnReject"><i class="bi bi-x-lg"></i>驳回</button>
  <button class="m-btn m-btn-brand" id="btnTransfer"><i class="bi bi-arrow-right-circle"></i>转交</button>
  <button class="m-btn m-btn-ok" id="btnApprove"><i class="bi bi-check-lg"></i>通过</button>
</div>
<?php elseif($can_recall): ?>
<div class="m-actionbar">
  <button class="m-btn m-btn-ghost" id="btnRecall"><i class="bi bi-arrow-counterclockwise"></i>撤回审批</button>
</div>
<?php endif; ?>

<!-- 驳回确认弹窗 -->
<div class="m-sheet-mask" id="rejectMask">
  <div class="m-sheet">
    <h3>驳回审批</h3>
    <p>提交后合同将退回提交人，可填写驳回意见（选填）。</p>
    <textarea class="m-textarea" id="rejectComment" placeholder="驳回意见（选填）"></textarea>
    <?php $mNodes = json_decode($detail['nodes'] ?? '[]', true) ?: []; ?>
    <select class="m-input" id="rejectTo" style="margin-top:10px">
      <option value="0">驳回回起点（重新提交）</option>
      <?php for($ro=1;$ro<(int)$detail['current_node_order'];$ro++): ?><option value="<?=$ro?>">驳回到节点<?=$ro?>：<?=htmlspecialchars($mNodes[$ro-1]['name']??('节点'.$ro))?></option><?php endfor; ?>
    </select>
    <div class="m-sheet-actions">
      <button class="m-btn m-btn-ghost" id="rejectCancel">取消</button>
      <button class="m-btn m-btn-danger" id="rejectConfirm">确认驳回</button>
    </div>
  </div>
</div>

<!-- 转交确认弹窗 -->
<div class="m-sheet-mask" id="transferMask">
  <div class="m-sheet">
    <h3>转交审批</h3>
    <p>选择转交人，提交后审批将转交给对方处理。</p>
    <input class="m-input" id="transferSearch" placeholder="搜索姓名…" style="margin-bottom:10px">
    <div class="m-user-list" id="transferList">
      <?php foreach($transfer_users as $u): ?>
      <label class="m-user-opt">
        <input type="radio" name="transferTo" value="<?=intval($u['id'])?>">
        <span><?=htmlspecialchars($u['name'])?></span>
      </label>
      <?php endforeach; ?>
    </div>
    <!-- Phase 2.8：加载更多转交人（AJAX 分页） -->
    <div id="transferMore" style="text-align:center;padding:10px;color:var(--m-brand);font-size:14px;display:none">加载更多…</div>
    <textarea class="m-textarea" id="transferComment" placeholder="转交说明（选填）"></textarea>
    <div class="m-sheet-actions">
      <button class="m-btn m-btn-ghost" id="transferCancel">取消</button>
      <button class="m-btn m-btn-brand" id="transferConfirm">确认转交</button>
    </div>
  </div>
</div>

<div class="m-toast" id="toast"></div>
<div class="m-loading" id="loading" style="display:none"><div class="m-spinner"></div></div>

<script>
// Phase 2.7：toast/showLoading/csrfToken 已由 mobile-common.js 提供，此处不再重复定义
(function(){
  var instanceId = <?=intval($detail['id'])?>;

  function doAction(action, comment, btn, transferTo, rejectTo){
    showLoading(true);
    if(btn){ btn.disabled = true; }
    var fd = new FormData();
    fd.append('action', action);
    fd.append('comment', comment || '');
    if(transferTo){ fd.append('transfer_to', transferTo); }
    if(action === 'REJECTED' && rejectTo){ fd.append('reject_to_order', rejectTo); }
    fetch('/ajax/approval/' + instanceId + '/action', {
      method:'POST', body:fd,
      headers:{'X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':csrfToken()}
    })
      .then(function(r){ return r.json(); })
      .then(function(res){
        showLoading(false);
        if(res.code === 0){
          toast(action === 'APPROVED' ? '已通过' : (action === 'TRANSFERRED' ? '已转交' : '已驳回'), action === 'APPROVED' ? 'success' : 'info');
          setTimeout(function(){ location.href = '/m/approvals?type=todo'; }, 800);
        } else {
          if(btn) btn.disabled = false;
          toast(res.msg || '操作失败');
        }
      })
      .catch(function(){
        showLoading(false);
        if(btn) btn.disabled = false;
        toast('网络异常，请重试');
      });
  }

  // 通过（直接提交）
  var btnApprove = document.getElementById('btnApprove');
  if(btnApprove){
    btnApprove.addEventListener('click', function(){
      var self = this;
      mConfirm('确认通过该审批？', function(){ doAction('APPROVED', '', self); });
    });
  }

  // 驳回（弹窗确认 + 选填意见，v2.38.17 意见改选填）
  var mask = document.getElementById('rejectMask');
  var btnReject = document.getElementById('btnReject');
  var btnCancel = document.getElementById('rejectCancel');
  var btnConfirm = document.getElementById('rejectConfirm');
  var rejectComment = document.getElementById('rejectComment');

  if(btnReject){
    btnReject.addEventListener('click', function(){ mask.classList.add('show'); });
    btnCancel.addEventListener('click', function(){ mask.classList.remove('show'); });
    mask.addEventListener('click', function(e){ if(e.target === mask) mask.classList.remove('show'); });
    btnConfirm.addEventListener('click', function(){
      var c = rejectComment.value.trim();
      var rt = document.getElementById('rejectTo');
      var rejectTo = rt ? rt.value : '0';
      var self = this;
      // 驳回二次确认：与「通过」操作的 mConfirm 交互对齐（用户取消时保留意见弹层，可修改后重试）
      mConfirm('确认驳回该审批？', function(){
        mask.classList.remove('show');
        doAction('REJECTED', c, self, null, rejectTo);
      });
    });
  }

  // 转交（选择目标人 + 选填说明）
  var tMask = document.getElementById('transferMask');
  var btnTransfer = document.getElementById('btnTransfer');
  var btnTConfirm = document.getElementById('transferConfirm');
  var btnTCancel = document.getElementById('transferCancel');
  var transferComment = document.getElementById('transferComment');

  if(btnTransfer){
    btnTransfer.addEventListener('click', function(){ tMask.classList.add('show'); });
    btnTCancel.addEventListener('click', function(){ tMask.classList.remove('show'); });
    tMask.addEventListener('click', function(e){ if(e.target === tMask) tMask.classList.remove('show'); });
    // Phase 2.8：转交人 AJAX 搜索 + 分页（替代 CR-37 客户端过滤，支持大组织）
    var tSearch = document.getElementById('transferSearch');
    var tList = document.getElementById('transferList');
    var tMore = document.getElementById('transferMore');
    var tPage = 1, tKeyword = '', tTimer = null, tLoading = false;
    var tOriginalHTML = tList.innerHTML;  // 保留服务端渲染的初始列表

    function renderTransferUsers(users, append){
      var html = '';
      users.forEach(function(u){
        html += '<label class="m-user-opt"><input type="radio" name="transferTo" value="'+u.id+'"><span>'+esc(u.name)+'</span></label>';
      });
      if(append) tList.insertAdjacentHTML('beforeend', html);
      else tList.innerHTML = html;
    }

    function searchTransferUsers(page, append){
      tLoading = true;
      tMore.textContent = '加载中…';
      fetch('/ajax/approval/transfer-targets?keyword='+encodeURIComponent(tKeyword)+'&page='+page, {
        headers:{'X-Requested-With':'XMLHttpRequest'}
      })
        .then(function(r){ return r.json(); })
        .then(function(res){
          tLoading = false;
          if(res.code !== 0){ toast('搜索失败'); return; }
          var data = res.data || {};
          renderTransferUsers(data.list || [], append);
          tPage = page;
          tMore.style.display = data.has_more ? 'block' : 'none';
          tMore.textContent = '加载更多…';
        })
        .catch(function(){ tLoading = false; toast('网络异常'); });
    }

    if(tSearch){
      tSearch.addEventListener('input', function(){
        var q = this.value.trim();
        clearTimeout(tTimer);
        tTimer = setTimeout(function(){
          tKeyword = q;
          if(q === ''){
            // 清空搜索：恢复服务端渲染的初始列表
            tList.innerHTML = tOriginalHTML;
            tMore.style.display = 'none';
          } else {
            searchTransferUsers(1, false);
          }
        }, 300);
      });
    }

    if(tMore){
      tMore.addEventListener('click', function(){
        if(tLoading) return;
        searchTransferUsers(tPage + 1, true);
      });
    }
    btnTConfirm.addEventListener('click', function(){
      var sel = tMask.querySelector('input[name="transferTo"]:checked');
      if(!sel){ toast('请选择转交人'); return; }
      tMask.classList.remove('show');
      doAction('TRANSFERRED', transferComment.value.trim(), this, sel.value);
    });
  }

  // 撤回（仅提交人，且仍待审批）
  var btnRecall = document.getElementById('btnRecall');
  if(btnRecall){
    btnRecall.addEventListener('click', function(){
      var self = this;
      mConfirm('确认撤回该审批？撤回后合同将退回草稿状态。', function(){
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
<!-- Phase 2.7：附件预览 Lightbox 提取为独立 JS（消除与合同详情页的重复代码） -->
<script src="<?=asset_url('js/mobile/lightbox.js')?>"></script>
<!-- Lightbox 遮罩 -->
<div class="lb-overlay" id="lbOverlay">
  <div class="lb-bar">
    <span id="lbTitle">附件预览</span>
    <button class="lb-close" onclick="closePreview()">&times;</button>
  </div>
  <div class="lb-body" id="lbBody"></div>
</div>
<?php $tab = ''; include __DIR__ . '/_foot.php'; ?>
