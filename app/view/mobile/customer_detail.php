<?php
// 移动端共享布局（Phase 1 重构 1.7）：头部与尾部由 _head.php / _foot.php 统一输出
$title = '客户详情';   // 页面标题，自动追加「 · 合同管理」
$tab = 'customer';     // 底部导航高亮：home/contract/customer/todo
include __DIR__ . '/_head.php';
?>


<?php
  $c = $customer;
  $st = $c['status'] ?? 1;
  $stCls = $st == 1 ? 'm-tag-ok' : 'm-tag-muted';
  $srcMap = ['MANUAL'=>'手动录入','IMPORT'=>'导入','DINGTALK'=>'钉钉'];
  $ctStMap = contract_status_map();     // CR-57：复用公共 helper
  $ctStCls = contract_status_badge();
?>
<div class="m-nav">
  <a href="/m/customers" class="back" aria-label="返回"><i class="bi bi-chevron-left"></i></a>
  <div class="title"><?=htmlspecialchars($c['name'])?></div>
  <div class="right"><span class="m-tag <?=$stCls?>"><?=htmlspecialchars($statusMap[$st] ?? $st)?></span></div>
</div>

<div class="m-page detail" id="page">

  <!-- 概要 -->
  <div class="m-card">
    <div class="m-card-bd" style="padding-top:16px;padding-bottom:16px">
      <div style="font-size:18px;font-weight:600"><?=htmlspecialchars($c['name'])?></div>
      <?php if(!empty($c['credit_code'])): ?><div style="font-size:13px;color:var(--m-text-3);margin-top:4px">统一信用代码：<?=htmlspecialchars($c['credit_code'])?></div><?php endif; ?>
      <div style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap">
        <!-- v2.38.13：信用评级并入概要（高风险红标 / 评分梯度标签），原独立卡移除；v2.38.14：灰标改五档梯度（分高色深） -->
        <?php if(!empty($c['high_risk'])): ?>
          <span class="m-tag m-tag-danger"><i class="bi bi-shield-exclamation"></i> 高风险</span>
        <?php else: $cScore=(int)($c['credit_score']??100); $cCls=$cScore>=90?'m-tag-credit-a':($cScore>=80?'m-tag-credit-b':($cScore>=60?'m-tag-credit-c':($cScore>=40?'m-tag-credit-d':'m-tag-credit-e'))); ?>
          <span class="m-tag <?=$cCls?>">信用 <?=$cScore?></span>
        <?php endif; ?>
        <!-- v2.38.9：生命周期标签（与漏斗同色） -->
        <?php $lc = $c['lifecycle_status'] ?? 'ACTIVE'; $lcCls = ['POTENTIAL'=>'m-tag-info','ACTIVE'=>'m-tag-ok','INACTIVE'=>'m-tag-warn'][$lc] ?? 'm-tag-muted'; ?>
        <span class="m-tag <?=$lcCls?>"><?=htmlspecialchars($lifecycle_dict[$lc] ?? $lc)?></span>
        <!-- v2.40.0 P1-7：客户行业标签 -->
        <?php if(!empty($c['industry'])): ?><span class="m-tag m-tag-muted"><?=htmlspecialchars($industry_dict[$c['industry']] ?? $c['industry'])?></span><?php endif; ?>
        <span class="m-tag m-tag-muted"><?=htmlspecialchars($srcMap[$c['source']] ?? $c['source'])?></span>
        <?php if(!empty($c['is_self'])): ?><span class="m-tag m-tag-ok">我方主体</span><?php endif; ?>
      </div>
    </div>
  </div>

  <!-- 基本信息（v2.38.13：信用评级已并入概要卡，此卡前置） -->
  <div class="m-card">
    <div class="m-card-hd"><span><i class="bi bi-info-circle me-1 text-primary"></i>基本信息</span></div>
    <div class="m-card-bd">
      <?php if(!empty($c['legal_person'])): ?><div class="m-kv"><div class="k">法人</div><div class="v"><?=htmlspecialchars($c['legal_person'])?></div></div><?php endif; ?>
      <?php if(!empty($c['contact_name'])): ?><div class="m-kv"><div class="k">联系人</div><div class="v"><?=htmlspecialchars($c['contact_name'])?></div></div><?php endif; ?>
      <?php if(!empty($c['contact_mobile'])): ?><div class="m-kv"><div class="k">手机</div><div class="v"><?=phone_link($c['contact_mobile'], false)?></div></div><?php endif; ?>
      <?php if(!empty($c['contact_email'])): ?><div class="m-kv"><div class="k">邮箱</div><div class="v"><?=htmlspecialchars($c['contact_email'])?></div></div><?php endif; ?>
      <?php if(!empty($c['address'])): ?><div class="m-kv"><div class="k">地址</div><div class="v"><?=htmlspecialchars($c['address'])?></div></div><?php endif; ?>
      <div class="m-kv"><div class="k">归属人</div><div class="v"><?=htmlspecialchars($owner_name ?: '公海')?></div></div>
      <?php if(!empty($c['created_at'])): ?><div class="m-kv"><div class="k">创建时间</div><div class="v"><?=htmlspecialchars(substr($c['created_at'],0,10))?></div></div><?php endif; ?>
    </div>
  </div>

  <!-- REV-31：客户操作（认领/转移/释放）加载状态标记 -->
  <?php $actionBusy = false; ?>
  <?php if($is_public_pool): ?>
  <!-- 公海客户：显示认领按钮 -->
  <div class="m-card"><div class="m-card-bd" style="padding-top:16px;padding-bottom:16px">
    <button class="m-btn m-btn-brand m-btn-block" onclick="custClaim(<?=$c['id']?>,this)" id="claimBtn">
      <i class="bi bi-person-check"></i> 认领此客户
    </button>
  </div></div>
  <?php elseif($is_owner): ?>
  <!-- 本人客户：显示释放与转移按钮 -->
  <div class="m-card"><div class="m-card-bd" style="display:flex;gap:8px;padding-top:16px;padding-bottom:16px">
    <button class="m-btn m-btn-ghost" style="flex:1" onclick="custRelease(<?=$c['id']?>,this)" id="releaseBtn">
      <i class="bi bi-box-arrow-up"></i> 释放到公海
    </button>
    <button class="m-btn m-btn-ghost" style="flex:1" onclick="custTransfer(<?=$c['id']?>,this)" id="transferBtn">
      <i class="bi bi-arrow-left-right"></i> 转移
    </button>
  </div></div>
  <?php endif; ?>

  <!-- 关联合同 -->
  <div class="m-card">
    <div class="m-card-hd"><span><i class="bi bi-file-earmark-text me-1 text-primary"></i>关联合同</span><span class="m-tag m-tag-muted"><?=$contract_total ?? count($contracts)?></span></div>
    <div class="m-card-bd">
      <?php $ctShown = 0; ?>
      <?php if(empty($contracts)): ?>
        <div class="m-empty" style="padding:28px 0"><i class="bi bi-folder2-open"></i>暂无关联合同</div>
      <?php else: foreach($contracts as $ct):
          $ctShown++;
          $isNon = ($ct['trade_attr'] ?? 1) == 0;
          $isIn = !$isNon && ($ct['direction'] ?? 'sales') === 'sales';
      ?>
        <a class="m-row<?=$ctShown>3?' m-lst-more':''?>" href="/m/contract/<?=$ct['id']?>" style="text-decoration:none<?=$ctShown>3?';display:none':''?>">
          <div class="pic"><i class="bi bi-file-earmark-text"></i></div>
          <div class="main">
            <div class="t"><?=htmlspecialchars($ct['title'])?></div>
            <div class="s"><?=htmlspecialchars($ct['contract_no'])?></div>
          </div>
          <div class="aside">
            <?php if($isNon): ?><span class="m-tag m-tag-muted">非交易</span>
            <?php else: ?><div class="amt pay-amt amt-<?=$isIn?'in':'out'?>">¥<?=number_format((float)($ct['amount'] ?? 0),0)?></div><?php endif; ?>
            <div style="margin-top:4px"><span class="m-tag <?=$ctStCls[$ct['status']] ?? 'm-tag-muted'?>"><?=htmlspecialchars($ctStMap[$ct['status']] ?? $ct['status'])?></span></div>
          </div>
        </a>
      <?php endforeach; endif; ?>
      <?php /* v2.38.13：默认折叠前 3 条，展开按钮显示全部 */ ?>
      <?php if($ctShown>3): ?><div class="m-row m-lst-more-btn" onclick="mShowMore(this)" style="justify-content:center;color:var(--m-brand);font-weight:600;padding:10px 0">展开全部 <?=$ctShown?> 条合同 <i class="bi bi-chevron-down"></i></div><?php endif; ?>
      <?php /* N-m4：关联合同超过首屏上限时，提供「查看全部」入口，跳转到按客户筛选的合同列表 */ ?>
      <?php if(($contract_total ?? 0) > ($contract_limit ?? 20)): ?>
        <a class="m-row" href="/m/contracts?customer_id=<?=$customer['id']?>" style="text-decoration:none;justify-content:center;color:var(--m-brand);font-weight:600">
          查看全部 <?=$contract_total?> 条合同 <i class="bi bi-chevron-right"></i>
        </a>
      <?php endif; ?>
    </div>
  </div>

  <!-- 回款 / 付款记录 -->
  <div class="m-card">
    <div class="m-card-hd"><span><i class="bi bi-cash-coin me-1 text-success"></i>回款 / 付款记录</span><span class="m-tag m-tag-muted"><?=count($payments)?></span></div>
    <div class="m-card-bd">
      <?php $payShown = 0; ?>
      <?php if(empty($payments)): ?>
        <div class="m-empty" style="padding:28px 0"><i class="bi bi-receipt"></i>暂无回款记录</div>
      <?php else: foreach($payments as $p):
          $payShown++;
          $isRecv = ($p['payment_type'] ?? 'RECEIVABLE') === 'RECEIVABLE';
          $pStCls = ($p['status'] ?? '') === 'PAID' ? 'm-tag-ok' : 'm-tag-warn';
          $pStTxt = ($p['status'] ?? '') === 'PAID' ? '已'.($isRecv?'收':'付') : '待'.($isRecv?'收':'付');
      ?>
        <div class="m-row pay-row<?=$payShown>3?' m-lst-more':''?>"<?=$payShown>3?' style="display:none"':''?>>
          <div class="pic <?=$isRecv?'pay-recv':'pay-pay'?>"><i class="bi bi-<?=$isRecv?'arrow-down-left':'arrow-up-right'?>"></i></div>
          <div class="main">
            <div class="t"><?=htmlspecialchars($p['contract_title'] ?? '')?></div>
            <div class="s"><?=htmlspecialchars($p['planned_date'] ?? '')?><?=!empty($p['description'])?' · '.htmlspecialchars($p['description']):''?></div>
          </div>
          <div class="aside">
            <div class="amt pay-amt amt-<?=$isRecv?'in':'out'?>">¥<?=number_format((float)($p['amount'] ?? 0),0)?></div>
            <div style="margin-top:4px"><span class="m-tag <?=$pStCls?>"><?=$pStTxt?></span></div>
          </div>
        </div>
      <?php endforeach; endif; ?>
      <?php if($payShown>3): ?><div class="m-row m-lst-more-btn" onclick="mShowMore(this)" style="justify-content:center;color:var(--m-brand);font-weight:600;padding:10px 0">展开全部 <?=$payShown?> 条记录 <i class="bi bi-chevron-down"></i></div><?php endif; ?>
    </div>
  </div>

  <!-- 跟进时间轴 -->
  <div class="m-card">
    <div class="m-card-hd"><span><i class="bi bi-clock-history me-1 text-primary"></i>跟进记录</span><span class="m-tag m-tag-muted"><?=(int)($stats['activity_count'] ?? count($activities))?></span>
      <?php if(!empty($can_edit) && !empty($is_owner)): ?><span class="m-link" style="margin-left:auto;font-size:13px" onclick="openActSheet()">+ 记录跟进</span><?php endif; ?>
    </div>
    <div class="m-card-bd">
      <?php $actShown = 0; ?>
      <?php if(empty($activities)): ?>
        <div class="m-empty" style="padding:24px 0"><i class="bi bi-clock-history"></i>暂无跟进记录</div>
      <?php else: foreach($activities as $a):
          $actShown++; ?>
        <div class="m-kv<?=$actShown>3?' m-lst-more':''?>"<?=$actShown>3?' style="display:none"':''?>><div class="k"><?=htmlspecialchars(activity_type_label($a['type'] ?? ''))?></div><div class="v"><?=htmlspecialchars($a['content'] ?? '')?><div style="color:var(--m-text-3);font-size:12px"><?=htmlspecialchars($a['user_name'] ?? '')?> · <?=htmlspecialchars(substr($a['created_at'] ?? '', 0, 16))?><?php if(!empty($a['next_follow_at'])): ?> · 下次跟进 <?=htmlspecialchars(substr($a['next_follow_at'], 0, 16))?><?php endif; ?></div></div></div>
      <?php endforeach; endif; ?>
      <?php if($actShown>3): ?><div class="m-row m-lst-more-btn" onclick="mShowMore(this)" style="justify-content:center;color:var(--m-brand);font-weight:600;padding:10px 0">展开全部 <?=$actShown?> 条记录 <i class="bi bi-chevron-down"></i></div><?php endif; ?>
    </div>
  </div>

  <!-- 联系人 (M9) -->
  <div class="m-card">
    <div class="m-card-hd"><span><i class="bi bi-person-lines-fill me-1 text-primary"></i>联系人</span><span class="m-tag m-tag-muted"><?=count($contacts)?></span>
      <?php if($is_owner): ?><span class="m-link" style="margin-left:auto;font-size:13px" onclick="mToggleContactForm(0)">+ 添加</span><?php endif; ?>
    </div>
    <div class="m-card-bd">
      <?php if(empty($contacts)): ?>
        <div class="m-empty" style="padding:24px 0"><i class="bi bi-person"></i>暂无联系人</div>
      <?php else: foreach($contacts as $c): ?>
        <div class="m-kv">
          <div class="k"><?=htmlspecialchars($c['name'])?><?=!empty($c['is_primary'])?' <span class="m-tag m-tag-ok" style="font-size:10px">主</span>':''?><br><span style="font-size:12px;color:var(--m-text-3)"><?=htmlspecialchars($c['role'])?></span></div>
          <div class="v">
            <?=phone_link($c['phone'], false)?><br>
            <?php if(!empty($c['email'])): ?><span style="font-size:12px;color:var(--m-text-3)"><?=htmlspecialchars($c['email'])?></span><?php endif; ?>
            <?php if(!empty($c['remark'])): ?><span style="font-size:12px;color:var(--m-text-3);display:block;margin-top:2px"><?=htmlspecialchars($c['remark'])?></span><?php endif; ?>
            <?php if($is_owner && empty($c['from_primary'])): ?><div style="margin-top:4px">
              <span class="m-link" style="font-size:12px" onclick="mToggleContactForm(<?=$c['id']?>)">编辑</span>
              <span class="m-link" style="font-size:12px;color:var(--m-danger)" onclick="mDeleteContact(<?=$c['id']?>)">删除</span>
            </div><?php endif; ?>
          </div>
        </div>
      <?php endforeach; endif; ?>
    </div>
    <div class="m-card-bd" id="mContactFormBox" style="display:none;border-top:1px dashed #eee">
      <input type="hidden" id="mContactId" value="0">
      <input type="hidden" id="mContactCustId" value="<?=$customer['id']?>">
      <div class="mb-2"><input type="text" id="mContactName" class="m-input" placeholder="姓名 *"></div>
      <div class="mb-2"><input type="text" id="mContactPhone" class="m-input" placeholder="电话"></div>
      <div class="mb-2"><input type="text" id="mContactEmail" class="m-input" placeholder="邮箱"></div>
      <!-- v2.38.12：更多信息（微信号等） -->
      <div class="mb-2"><textarea id="mContactRemark" class="m-input" rows="2" placeholder="更多信息（微信号、钉钉号等）" style="padding:10px;font-size:14px"></textarea></div>
      <div class="mb-2">
        <select id="mContactRole" class="m-input">
          <?php foreach($contact_roles as $r): ?><option value="<?=htmlspecialchars($r)?>"><?=htmlspecialchars($r)?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="mb-2"><label style="font-size:13px"><input type="checkbox" id="mContactPrimary"> 设为主联系人</label></div>
      <div style="display:flex;gap:8px">
        <button class="m-btn m-btn-brand" style="flex:1" onclick="mSaveContact()">保存</button>
        <button class="m-btn m-btn-ghost" style="flex:1" onclick="document.getElementById('mContactFormBox').style.display='none'">取消</button>
      </div>
    </div>
  </div>

  <!-- 往来汇总（v2.38.14：统计卡升级为 360 交易合同口径 + 最近动态内嵌，模块数不变） -->
  <div class="m-card">
    <div class="m-card-hd"><span><i class="bi bi-cash-coin me-1 text-primary"></i>往来汇总</span><?php if(!empty($g360['stats'])): ?><a href="/m/party/customer/<?=$customer['id']?>" style="font-size:12px;color:var(--m-brand)">往来全景 <i class="bi bi-chevron-right"></i></a><?php endif; ?></div>
    <div class="m-card-bd">
      <?php $gS = $g360['stats'] ?? null; if($gS): ?>
      <div style="display:flex;flex-wrap:wrap;text-align:center">
        <div style="width:50%;padding:6px 0"><div class="m-stat-n" style="font-size:20px;font-weight:600;color:var(--m-brand)">¥<?=number_format((float)$gS['total_amount'],0)?></div><div class="m-stat-l" style="font-size:12px;color:var(--m-text-3)">往来总额</div></div>
        <div style="width:50%;padding:6px 0"><div class="m-stat-n" style="font-size:20px;font-weight:600;color:var(--m-success)">¥<?=number_format((float)$gS['received_paid'],0)?></div><div class="m-stat-l" style="font-size:12px;color:var(--m-text-3)">已收</div></div>
        <div style="width:50%;padding:6px 0"><div class="m-stat-n" style="font-size:20px;font-weight:600;color:<?=($gS['balance']??0)>0?'var(--m-warn)':'var(--m-text-1)'?>">¥<?=number_format((float)$gS['balance'],0)?></div><div class="m-stat-l" style="font-size:12px;color:var(--m-text-3)">待收余额</div></div>
        <div style="width:50%;padding:6px 0"><div class="m-stat-n" style="font-size:20px;font-weight:600;color:var(--m-danger)">¥<?=number_format((float)($stats['overdue_amount']??0),0)?></div><div class="m-stat-l" style="font-size:12px;color:var(--m-text-3)">逾期金额</div></div>
      </div>
      <?php else: ?>
      <div class="m-kv"><div class="k">关联合同</div><div class="v"><?=(int)($stats['contract_total'] ?? 0)?> 笔 / ¥<?=number_format((float)($stats['contract_amount'] ?? 0), 0)?></div></div>
      <div class="m-kv"><div class="k">已回款</div><div class="v" style="color:var(--m-success)">¥<?=number_format((float)($stats['paid_amount'] ?? 0), 0)?></div></div>
      <div class="m-kv"><div class="k">逾期金额</div><div class="v" style="color:var(--m-danger)">¥<?=number_format((float)($stats['overdue_amount'] ?? 0), 0)?></div></div>
      <?php endif; ?>
      <?php if(!empty($g360['activity'])): ?>
      <div style="margin-top:10px;border-top:1px solid #f2f3f5;padding-top:8px">
        <div style="font-size:12px;color:var(--m-text-3);margin-bottom:4px">最近动态</div>
        <?php $actShown2 = min(count($g360['activity']), 5); // 首屏最多渲染 5 条（与下方 break 一致） ?>
        <?php foreach($g360['activity'] as $ai => $ac): if($ai >= 5) break; ?>
          <div class="m-row m-lst-more"<?=($ai >= 3)?' style="display:none"':''?> style="padding:6px 0;border-bottom:none;min-height:0">
            <div class="s" style="font-size:12px"><span class="m-tag m-tag-info" style="font-size:11px"><?=htmlspecialchars(audit_action_label($ac['action'] ?? ''))?></span> 合同 #<?=(int)($ac['target_id'] ?? 0)?></div>
            <div class="s" style="font-size:11px;color:var(--m-text-3)"><?=htmlspecialchars($ac['created_at'] ?? '')?></div>
          </div>
        <?php endforeach; ?>
        <?php if($actShown2 > 3): ?><div class="m-row m-lst-more-btn" onclick="mShowMore(this)" style="justify-content:center;color:var(--m-brand);padding:6px 0;min-height:0">展开全部 <?=$actShown2?> 条动态 <i class="bi bi-chevron-down"></i></div><?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- v2.45.0：共享与集团（共享成员只读展示；集团汇总懒加载；管理动作在 PC 端） -->
  <div class="m-card">
    <div class="m-card-hd"><span><i class="bi bi-people me-1 text-primary"></i>共享与集团</span></div>
    <div class="m-card-bd">
      <div class="m-kv"><div class="k">共享成员</div><div class="v" id="mShareNames">
        <?php if(empty($share_list)): ?><span class="text-muted">暂无（负责人可见；签过合同的同事自动可见）</span>
        <?php else: $mNames=array(); foreach($share_list as $s){ $mNames[]=($s['target_type']==='DEPT'?'[部门]':'').$s['target_name']; } echo htmlspecialchars(implode('、', $mNames)); endif; ?>
      </div></div>
      <div class="m-kv"><div class="k">集团归属</div><div class="v"><?=htmlspecialchars($parent_name ?: '独立客户（非集团成员）')?></div></div>
      <div class="m-kv"><div class="k">集团合同</div><div class="v" id="mGroupSummary"><span class="text-muted">加载中…</span></div></div>
    </div>
  </div>

</div>

<!-- 2026-08-03：客户转移选人弹窗（复用审批转交选人组件：m-sheet + m-user-list + 搜索/加载更多，替代输入用户 ID） -->
<div class="m-sheet-mask" id="transferMask">
  <div class="m-sheet">
    <h3>转移客户</h3>
    <p>选择接收人，客户归属将转移给对方。</p>
    <input class="m-input" id="transferSearch" placeholder="搜索姓名…" style="margin-bottom:10px">
    <div class="m-user-list" id="transferList">
      <?php if(empty($transfer_users)): ?>
        <div class="m-user-empty">暂无可用接收人</div>
      <?php else: foreach($transfer_users as $u): ?>
      <label class="m-user-opt">
        <input type="radio" name="transferTo" value="<?=intval($u['id'])?>">
        <span><?=htmlspecialchars($u['name'])?></span>
      </label>
      <?php endforeach; endif; ?>
    </div>
    <div id="transferMore" style="text-align:center;padding:10px;color:var(--m-brand);font-size:14px;display:none">加载更多…</div>
    <div class="m-sheet-actions">
      <button class="m-btn m-btn-ghost" id="transferCancel">取消</button>
      <button class="m-btn m-btn-brand" id="transferConfirm">确认转移</button>
    </div>
  </div>
</div>

<!-- v2.40.0 P0-2：记录跟进底部弹层 -->
<div class="m-sheet-mask" id="actSheetMask">
  <div class="m-sheet">
    <h3>记录跟进</h3>
    <p>跟进方式</p>
    <div style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:12px">
      <label class="m-user-opt"><input type="radio" name="actType" value="phone" checked><span>电话</span></label>
      <label class="m-user-opt"><input type="radio" name="actType" value="visit"><span>拜访</span></label>
      <label class="m-user-opt"><input type="radio" name="actType" value="meeting"><span>会议</span></label>
      <label class="m-user-opt"><input type="radio" name="actType" value="wechat"><span>微信</span></label>
    </div>
    <p>跟进内容</p>
    <textarea class="m-input" id="actContent" rows="3" maxlength="500" placeholder="本次沟通要点、客户意向等" style="resize:vertical;height:auto"></textarea>
    <p>下次跟进时间（可不填）</p>
    <input class="m-input" type="datetime-local" id="actNextFollow">
    <div class="m-sheet-actions">
      <button class="m-btn m-btn-ghost" id="actCancel">取消</button>
      <button class="m-btn m-btn-brand" id="actConfirm">保存</button>
    </div>
  </div>
</div>

<!-- REV-31：客户操作 JS（认领/释放/转移） -->
<script>
/* ===== v2.40.0 P0-2：记录跟进 ===== */
function openActSheet() {
  document.getElementById('actContent').value = '';
  document.getElementById('actNextFollow').value = '';
  var r = document.querySelector('#actSheetMask input[name="actType"]:checked');
  if (r) r.checked = false;
  document.querySelector('#actSheetMask input[name="actType"][value="phone"]').checked = true;
  document.getElementById('actSheetMask').classList.add('show');
}
function doSaveAct() {
  var type = document.querySelector('#actSheetMask input[name="actType"]:checked');
  var content = document.getElementById('actContent').value.trim();
  var next = document.getElementById('actNextFollow').value;
  if (!content) { toast('请填写跟进内容'); return; }
  document.getElementById('actSheetMask').classList.remove('show');
  var fd = new URLSearchParams();
  fd.append('type', type ? type.value : 'phone');
  fd.append('content', content);
  fd.append('next_follow_at', next);
  fetch('/ajax/customer/<?=$customer['id']?>/activity', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken() },
    body: fd.toString()
  }).then(r => r.json()).then(d => {
    if (d.code === 0) { toast('已记录跟进'); location.reload(); }
    else { toast(d.msg || '记录失败'); }
  }).catch(() => { toast('网络错误'); });
}
function initActSheet() {
  var mask = document.getElementById('actSheetMask');
  if (!mask) return;
  document.getElementById('actCancel').addEventListener('click', function() { mask.classList.remove('show'); });
  document.getElementById('actConfirm').addEventListener('click', doSaveAct);
}
initActSheet();

function custClaim(id, btn) {
  mConfirm('确认认领此客户？', function() {
    btn.disabled = true; btn.innerHTML = '<i class="bi bi-hourglass-split"></i> 认领中…';
    fetch('/ajax/customer/' + id + '/claim', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken() } })
      .then(r => r.json()).then(d => {
        if (d.code === 0) { toast('认领成功'); location.reload(); }
        else { toast(d.msg || '认领失败'); btn.disabled = false; btn.innerHTML = '<i class="bi bi-person-check"></i> 认领此客户'; }
      }).catch(() => { toast('网络错误'); btn.disabled = false; btn.innerHTML = '<i class="bi bi-person-check"></i> 认领此客户'; });
  });
}
function custRelease(id, btn) {
  mConfirm('确认将此客户释放到公海？', function() {
    btn.disabled = true; btn.innerHTML = '<i class="bi bi-hourglass-split"></i> 释放中…';
    fetch('/ajax/customer/' + id + '/release', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken() } })
      .then(r => r.json()).then(d => {
        if (d.code === 0) { toast('已释放到公海'); location.reload(); }
        else { toast(d.msg || '释放失败'); btn.disabled = false; btn.innerHTML = '<i class="bi bi-box-arrow-up"></i> 释放到公海'; }
      }).catch(() => { toast('网络错误'); btn.disabled = false; btn.innerHTML = '<i class="bi bi-box-arrow-up"></i> 释放到公海'; });
  });
}
// 2026-08-03：转移改为选人弹窗（不再输入用户 ID）；弹窗交互初始化见下方 initTransferModal()
var _custTransferId = 0;
function custTransfer(id, btn) {
  _custTransferId = id;
  document.getElementById('transferMask').classList.add('show');
}
function doCustTransfer() {
  var sel = document.querySelector('#transferMask input[name="transferTo"]:checked');
  if (!sel) { toast('请选择接收人'); return; }
  var btn = document.getElementById('transferBtn');
  document.getElementById('transferMask').classList.remove('show');
  btn.disabled = true; btn.innerHTML = '<i class="bi bi-hourglass-split"></i> 转移中…';
  fetch('/ajax/customer/' + _custTransferId + '/transfer', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken() },
    body: 'to_user_id=' + encodeURIComponent(sel.value)
  }).then(r => r.json()).then(d => {
    if (d.code === 0) { toast('转移成功'); location.reload(); }
    else { toast(d.msg || '转移失败'); btn.disabled = false; btn.innerHTML = '<i class="bi bi-arrow-left-right"></i> 转移'; }
  }).catch(() => { toast('网络错误'); btn.disabled = false; btn.innerHTML = '<i class="bi bi-arrow-left-right"></i> 转移'; });
}
// 转移弹窗交互：开关 / 姓名搜索（AJAX）/ 加载更多（与审批转交同模式）
function initTransferModal() {
  var tMask = document.getElementById('transferMask');
  if (!tMask) return;
  var tSearch = document.getElementById('transferSearch');
  var tList = document.getElementById('transferList');
  var tMore = document.getElementById('transferMore');
  var tPage = 1, tKeyword = '', tTimer = null, tLoading = false;
  var tOriginalHTML = tList.innerHTML;  // 保留服务端渲染的初始列表

  function renderTransferUsers(users, append) {
    var html = '';
    users.forEach(function(u) {
      html += '<label class="m-user-opt"><input type="radio" name="transferTo" value="' + u.id + '"><span>' + esc(u.name) + '</span></label>';
    });
    if (append) tList.insertAdjacentHTML('beforeend', html);
    else tList.innerHTML = html;
  }
  function searchTransferUsers(page, append) {
    tLoading = true;
    tMore.textContent = '加载中…';
    fetch('/ajax/customer/transfer-targets?keyword=' + encodeURIComponent(tKeyword) + '&page=' + page, {
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    }).then(function(r) { return r.json(); }).then(function(res) {
      tLoading = false;
      if (res.code !== 0) { toast('搜索失败'); return; }
      var data = res.data || {};
      if (!data.list || !data.list.length) {
        if (!append) tList.innerHTML = '<div class="m-user-empty">未找到匹配用户</div>';
        tMore.style.display = 'none';
        return;
      }
      renderTransferUsers(data.list, append);
      tPage = page;
      tMore.style.display = data.has_more ? 'block' : 'none';
      tMore.textContent = '加载更多…';
    }).catch(function() { tLoading = false; toast('网络异常'); });
  }

  document.getElementById('transferCancel').addEventListener('click', function() { tMask.classList.remove('show'); });
  document.getElementById('transferConfirm').addEventListener('click', doCustTransfer);
  tMask.addEventListener('click', function(e) { if (e.target === tMask) tMask.classList.remove('show'); });
  if (tSearch) {
    tSearch.addEventListener('input', function() {
      var q = this.value.trim();
      clearTimeout(tTimer);
      tTimer = setTimeout(function() {
        tKeyword = q;
        if (q === '') {
          // 清空搜索：恢复服务端渲染的初始列表
          tList.innerHTML = tOriginalHTML;
          tMore.style.display = 'none';
        } else {
          searchTransferUsers(1, false);
        }
      }, 300);
    });
  }
  if (tMore) {
    tMore.addEventListener('click', function() {
      if (tLoading) return;
      searchTransferUsers(tPage + 1, true);
    });
  }
}
initTransferModal();

/* M9 移动端联系人 CRUD */
// v2.38.14：mShowMore 已提取至 mobile-common.js（多页复用）
function mToggleContactForm(id) {
  var box = document.getElementById('mContactFormBox');
  if (id > 0) {
    fetch('/ajax/customer/<?=$customer['id']?>/contacts', { headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken() } })
      .then(r => r.json()).then(function(res) {
        var item = (res.data || []).find(function(x) { return x.id == id; });
        document.getElementById('mContactId').value = id;
        document.getElementById('mContactName').value = item ? item.name : '';
        document.getElementById('mContactPhone').value = item ? item.phone : '';
        document.getElementById('mContactEmail').value = item ? item.email : '';
        document.getElementById('mContactRole').value = item ? item.role : '商务负责人';
        document.getElementById('mContactPrimary').checked = item ? (item.is_primary == 1) : false;
        document.getElementById('mContactRemark').value = item ? (item.remark || '') : '';
        box.style.display = 'block';
        window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' });
      }).catch(function() { toast('加载失败'); });
  } else {
    document.getElementById('mContactId').value = 0;
    document.getElementById('mContactName').value = '';
    document.getElementById('mContactPhone').value = '';
    document.getElementById('mContactEmail').value = '';
    document.getElementById('mContactRole').value = '商务负责人';
    document.getElementById('mContactPrimary').checked = false;
    document.getElementById('mContactRemark').value = '';
    box.style.display = 'block';
    window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' });
  }
}
function mSaveContact() {
  var name = document.getElementById('mContactName').value.trim();
  if (!name) { toast('请填写姓名'); return; }
  var fd = new URLSearchParams();
  fd.append('id', document.getElementById('mContactId').value);
  fd.append('customer_id', document.getElementById('mContactCustId').value);
  fd.append('name', name);
  fd.append('phone', document.getElementById('mContactPhone').value.trim());
  fd.append('email', document.getElementById('mContactEmail').value.trim());
  fd.append('role', document.getElementById('mContactRole').value);
  fd.append('is_primary', document.getElementById('mContactPrimary').checked ? 1 : 0);
  fd.append('remark', document.getElementById('mContactRemark').value.trim());
  fetch('/ajax/customer/contact/save', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken() },
    body: fd.toString()
  }).then(r => r.json()).then(function(d) {
    if (d.code === 0) { toast('已保存'); location.reload(); }
    else toast(d.msg || '保存失败');
  }).catch(function() { toast('网络错误'); });
}
function mDeleteContact(id) {
  mConfirm('确定删除该联系人？', function() {
    fetch('/ajax/customer/contact/delete', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken() },
      body: 'id=' + id
    }).then(r => r.json()).then(function(d) {
      if (d.code === 0) { toast('已删除'); location.reload(); }
      else toast(d.msg || '删除失败');
    }).catch(function() { toast('网络错误'); });
  });
}
// v2.45.0：集团合同汇总懒加载
(function(){
  var sumEl = document.getElementById('mGroupSummary');
  if (!sumEl) return;
  var cid = <?=(int)$customer['id']?>;
  fetch('/ajax/customer/' + cid + '/group-info', {
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  }).then(r => r.json()).then(function(d) {
    if (d.code !== 0 || !d.data) { sumEl.innerHTML = '<span class="text-muted">—</span>'; return; }
    var s = d.data.summary || {};
    var parent = d.data.current_parent_id ? '（属 ' + (d.data.root_id ? '集团' : '') + '）' : '';
    var h = '<span>' + (s.contract_total || 0) + ' 份 / ¥' + Number(s.contract_amount || 0).toLocaleString('zh-CN', {maximumFractionDigits: 0}) + '</span>';
    h += '<span style="color:var(--m-text-3)"> · 已回款 ¥' + Number(s.paid_amount || 0).toLocaleString('zh-CN', {maximumFractionDigits: 0}) + '</span>';
    if (parent) h += '<span class="m-tag m-tag-info" style="margin-left:6px">集团</span>';
    sumEl.innerHTML = h;
  }).catch(function() { sumEl.innerHTML = '<span class="text-muted">—</span>'; });
})();
</script>
<?php $tab = 'customer'; include __DIR__ . '/_foot.php'; ?>
