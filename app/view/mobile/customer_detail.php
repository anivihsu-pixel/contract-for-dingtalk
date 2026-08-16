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
        <!-- 生命周期标签（与漏斗同色） -->
        <?php $lc = $c['lifecycle_status'] ?? 'ACTIVE'; $lcCls = ['POTENTIAL'=>'m-tag-info','ACTIVE'=>'m-tag-ok'][$lc] ?? 'm-tag-muted'; ?>
        <span class="m-tag <?=$lcCls?>"><?=htmlspecialchars($lifecycle_dict[$lc] ?? $lc)?></span>
        <!-- v2.40.0 P1-7：客户行业标签 -->
        <?php if(!empty($c['industry'])): ?><span class="m-tag m-tag-muted"><?=htmlspecialchars($industry_dict[$c['industry']] ?? $c['industry'])?></span><?php endif; ?>
        <span class="m-tag m-tag-muted"><?=htmlspecialchars($srcMap[$c['source']] ?? $c['source'])?></span>
        <?php if(!empty($c['is_self'])): ?><span class="m-tag m-tag-ok">我方主体</span><?php endif; ?>
      </div>
      <!-- v2.51.3：操作区并入概要卡（编辑资料=归属人/部门/管理员；转移=本人客户）
           v2.51.4：改紧凑胶囊（.m-act），左对齐，替代 48px 大按钮 -->
      <?php if(!empty($can_edit_record) || $is_owner): ?>
      <div style="margin-top:14px;padding-top:12px;border-top:1px solid #f2f3f5;display:flex;justify-content:flex-start;gap:8px;flex-wrap:wrap">
        <?php if(!empty($can_edit_record)): ?>
        <a class="m-act" href="/m/customer/<?=$c['id']?>/edit" style="margin-left:0"><i class="bi bi-pencil"></i> 编辑资料</a>
        <?php endif; ?>
        <?php if($is_owner): ?>
        <a href="javascript:;" class="m-act" id="transferBtn" onclick="custTransfer(<?=$c['id']?>,this)" style="margin-left:0"><i class="bi bi-arrow-left-right"></i> 转移</a>
        <?php endif; ?>
      </div>
      <?php endif; ?>
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
      <div class="m-kv"><div class="k">归属人</div><div class="v"><?=htmlspecialchars($owner_name ?: '未分配')?></div></div>
      <?php if(!empty($c['created_at'])): ?><div class="m-kv"><div class="k">创建时间</div><div class="v"><?=htmlspecialchars(substr($c['created_at'],0,10))?></div></div><?php endif; ?>
    </div>
  </div>

  <!-- 联系人 (M9)（v2.51.3：上移至基本信息之后，联系方式紧跟资料区） -->
  <div class="m-card">
    <div class="m-card-hd"><span><i class="bi bi-person-lines-fill me-1 text-primary"></i>联系人</span><span class="m-tag m-tag-muted"><?=count($contacts)?></span>
      <?php if($is_owner): ?><span class="m-act" onclick="mToggleContactForm(0)"><i class="bi bi-person-plus"></i>添加</span><?php endif; ?>
    </div>
    <div class="m-card-bd">
      <?php if(empty($contacts)): ?>
        <div class="m-empty m-empty-sm"><i class="bi bi-person"></i>暂无联系人</div>
      <?php else: foreach($contacts as $c): ?>
        <div class="m-kv">
          <div class="k"><?=htmlspecialchars($c['name'])?><?=!empty($c['is_primary'])?' <span class="m-tag m-tag-ok" style="font-size:10px">主</span>':''?></div>
          <div class="v">
            <?=phone_link($c['phone'], false)?>
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
      <div class="mb-2"><label style="font-size:13px"><input type="checkbox" id="mContactPrimary"> 设为主联系人</label></div>
      <div style="display:flex;gap:8px">
        <button class="m-btn m-btn-brand" style="flex:1" onclick="mSaveContact()">保存</button>
        <button class="m-btn m-btn-ghost" style="flex:1" onclick="document.getElementById('mContactFormBox').style.display='none'">取消</button>
      </div>
    </div>
  </div>

  <!-- 关联合同 -->
  <div class="m-card">
    <div class="m-card-hd"><span><i class="bi bi-file-earmark-text me-1 text-primary"></i>关联合同</span><span class="m-tag m-tag-muted"><?=$contract_total ?? count($contracts)?></span></div>
    <div class="m-card-bd">
      <?php $ctShown = 0; ?>
      <?php if(empty($contracts)): ?>
        <div class="m-empty m-empty-sm"><i class="bi bi-folder2-open"></i>暂无关联合同</div>
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
        <div class="m-empty m-empty-sm"><i class="bi bi-receipt"></i>暂无回款记录</div>
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
    <div class="m-card-hd"><span><i class="bi bi-clock-history me-1 text-primary"></i>跟进记录</span><span class="m-tag m-tag-muted" id="actCount"><?=(int)($stats['activity_count'] ?? count($activities))?></span>
      <?php if(!empty($can_edit) && (!empty($is_owner) || !empty($is_super_admin))): ?><span class="m-act" onclick="openActSheet()"><i class="bi bi-pencil"></i>记录跟进</span><?php endif; ?>
    </div>
    <div class="m-card-bd" id="actList">
      <?php $actShown = 0; ?>
      <?php if(empty($activities)): ?>
        <div class="m-empty m-empty-sm" id="actEmpty"><i class="bi bi-clock-history"></i>暂无跟进记录</div>
      <?php else:
        // v2.48.0：按日期分组（今天/昨天/更早）；总经理等有权限用户可见该客户全量跟进记录（不按人过滤）
        $actGroups = ['today' => [], 'yesterday' => [], 'earlier' => []];
        foreach($activities as $a){
            $d = substr($a['created_at'] ?? '', 0, 10);
            if($d === date('Y-m-d')) $actGroups['today'][] = $a;
            elseif($d === date('Y-m-d', strtotime('-1 day'))) $actGroups['yesterday'][] = $a;
            else $actGroups['earlier'][] = $a;
        }
        $actGroupLabels = ['today' => '今天', 'yesterday' => '昨天', 'earlier' => '更早'];
        $actTypeChar = ['phone' => '电', 'visit' => '拜', 'meeting' => '会', 'wechat' => '微'];
        foreach($actGroupLabels as $gk => $gl):
          if(empty($actGroups[$gk])) continue; ?>
        <div class="m-act-grp"><?=$gl?></div>
          <?php foreach($actGroups[$gk] as $a):
            $actShown++;
            $hide = $actShown > 3;
            $type = $a['type'] ?? '';
            $tLabel = activity_type_label($type);
            $tChar = $actTypeChar[$type] ?? '记';
            $next = !empty($a['next_follow_at']) ? htmlspecialchars(substr($a['next_follow_at'], 0, 16)) : ''; ?>
        <div class="m-act-item<?=$hide?' m-lst-more':''?>"<?=$hide?' style="display:none"':''?>>
          <span class="m-act-ic m-act-ic-<?=htmlspecialchars($type)?>" title="<?=htmlspecialchars($tLabel)?>"><?=$tChar?></span>
          <div class="m-act-bd">
            <div class="m-act-txt"><?=htmlspecialchars($a['content'] ?? '')?></div>
            <div class="m-act-meta"><?=htmlspecialchars($a['user_name'] ?? '')?> · <?=htmlspecialchars(substr($a['created_at'] ?? '', 5, 11))?><?php if($next !== ''): ?><span class="m-act-next">下次 <?=$next?></span><?php endif; ?></div>
          </div>
        </div>
          <?php endforeach;
        endforeach; ?>
      <?php endif; ?>
      <?php if($actShown>3): ?><div class="m-row m-lst-more-btn" onclick="mShowMore(this)" style="justify-content:center;color:var(--m-brand);font-weight:600;padding:10px 0">展开全部 <?=$actShown?> 条记录 <i class="bi bi-chevron-down"></i></div><?php endif; ?>
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
            <div class="s" style="font-size:12px"><span class="m-tag m-tag-info" style="font-size:11px"><?=htmlspecialchars(audit_action_label($ac['action'] ?? ''))?></span> 合同 <?=htmlspecialchars($ac['contract_title'] ?? ('#'.(int)($ac['target_id'] ?? 0)))?></div>
            <div class="s" style="font-size:11px;color:var(--m-text-3)"><?=htmlspecialchars($ac['created_at'] ?? '')?></div>
          </div>
        <?php endforeach; ?>
        <?php if($actShown2 > 3): ?><div class="m-row m-lst-more-btn" onclick="mShowMore(this)" style="justify-content:center;color:var(--m-brand);padding:6px 0;min-height:0">展开全部 <?=$actShown2?> 条动态 <i class="bi bi-chevron-down"></i></div><?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- v2.45.0：共享与集团（共享成员只读展示；集团汇总懒加载；2026-08-11 起负责人/超管可在移动端直接设置共享与集团归属） -->
  <div class="m-card">
    <div class="m-card-hd"><span><i class="bi bi-people me-1 text-primary"></i>共享与集团</span>
      <?php if(!empty($share_can_manage)): ?><span class="m-act" onclick="mOpenShareSheet()"><i class="bi bi-people-fill"></i>添加共享</span><?php endif; ?>
    </div>
    <div class="m-card-bd">
      <div class="m-kv"><div class="k">共享成员</div><div class="v" id="mShareNames">
        <?php if(empty($share_list)): ?><span class="text-muted">暂无（负责人可见；签过合同的同事自动可见）</span>
        <?php else: foreach($share_list as $s): $sKey=htmlspecialchars($s['target_type']).':'.(int)$s['target_id']; ?>
          <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;padding:2px 0">
            <span><?=htmlspecialchars(($s['target_type']==='DEPT'?'[部门]':'').$s['target_name'])?></span>
            <?php if(!empty($share_can_manage)): ?><a href="javascript:;" style="font-size:12px;color:#fa5151" data-share-key="<?=$sKey?>" onclick="mRemoveShare(this)">撤销</a><?php endif; ?>
          </div>
        <?php endforeach; endif; ?>
      </div></div>
      <div class="m-kv"><div class="k">集团归属</div><div class="v">
        <?=htmlspecialchars($parent_name ?: '独立客户（非集团成员）')?>
        <?php if(!empty($share_can_manage)): ?><a href="javascript:;" class="m-act" onclick="mOpenGroupSheet()"><i class="bi bi-diagram-3"></i>设置</a><?php endif; ?>
      </div></div>
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
</div></div>

<!-- 2026-08-11：添加共享弹层（负责人/超管；用户/部门二选一，复用 /ajax/customer/{id}/share） -->
<div class="m-sheet-mask" id="shareSheetMask">
  <div class="m-sheet">
    <h3>添加共享</h3>
    <p>共享成员可查看本客户并关联合同（只读，不可编辑档案）</p>
    <div style="display:flex;gap:10px;margin-bottom:12px">
      <label style="flex:1;text-align:center;padding:8px 0;border:1px solid var(--m-line);border-radius:8px;font-size:14px;cursor:pointer">
        <input type="radio" name="shareTypeRad" value="USER" checked onchange="mSetShareType(this.value)"> 用户
      </label>
      <label style="flex:1;text-align:center;padding:8px 0;border:1px solid var(--m-line);border-radius:8px;font-size:14px;cursor:pointer">
        <input type="radio" name="shareTypeRad" value="DEPT" onchange="mSetShareType(this.value)"> 部门
      </label>
    </div>
    <!-- v2.47.8 后：共享用户选择支持搜索框（本地过滤全公司用户），与客户转移选人同交互 -->
    <input class="m-input" id="shareUserSearch" placeholder="搜索姓名…" style="margin-bottom:10px">
    <div class="m-user-list" id="shareUserList">
      <?php if(empty($share_target_options)): ?><div class="m-user-empty">暂无可用用户</div>
      <?php else: foreach($share_target_options as $u): ?>
      <label class="m-user-opt"><input type="radio" name="shareTo" value="<?=intval($u['id'])?>"><span><?=htmlspecialchars($u['name'])?></span></label>
      <?php endforeach; endif; ?>
    </div>
    <div class="m-user-list" id="shareDeptList" style="display:none">
      <?php if(empty($share_departments)): ?><div class="m-user-empty">暂无部门</div>
      <?php else: foreach($share_departments as $d): ?>
      <label class="m-user-opt"><input type="radio" name="shareTo" value="<?=intval($d['id'])?>"><span><?=htmlspecialchars($d['name'])?></span></label>
      <?php endforeach; endif; ?>
    </div>
    <div class="m-sheet-actions">
      <button class="m-btn m-btn-ghost" id="shareCancel">取消</button>
      <button class="m-btn m-btn-brand" id="shareConfirm">确认共享</button>
    </div>
  </div>
</div>

<!-- 2026-08-11：设置集团归属弹层（负责人/超管；父客户可多级；v2.47.8 搜索框带出 + 未搜到可快速新建客户） -->
<div class="m-sheet-mask" id="groupSheetMask">
  <div class="m-sheet">
    <h3>设置集团归属</h3>
    <p>搜索并选择父客户（可多级）；不选则为独立客户</p>
    <div class="cs-wrap" data-cs-src="window.__groupOptions" data-quick="customer" data-quick-url="/ajax/customer/save" style="margin-bottom:14px">
      <input type="text" class="cs-input m-input" placeholder="搜索集团客户…未搜到可快速新建" autocomplete="off" value="<?=htmlspecialchars($parent_name ?: '')?>">
      <div class="cs-suggestions"></div>
      <input type="hidden" class="cs-id" id="mGroupParentId" value="<?=(int)($customer['parent_id'] ?? 0)?>">
    </div>
    <div class="m-sheet-actions">
      <button class="m-btn m-btn-ghost" id="groupCancel">取消</button>
      <button class="m-btn m-btn-brand" id="groupConfirm">保存</button>
    </div>
    <?php // v2.47.8：已设置集团归属时提供「取消集团归属」一键移除（确认+提交+刷新）
    if(!empty($customer['parent_id'])): ?>
    <button class="m-btn m-btn-danger" id="groupRemove" style="width:100%;margin-top:10px">取消集团归属</button>
    <?php endif; ?>
  </div>
</div>

<!-- v2.40.0 P0-2 记录跟进底部弹层（v2.48.0 快捷录入：方式图标+快捷短语+下次跟进可选） -->
<div class="m-sheet-mask" id="actSheetMask">
  <div class="m-sheet">
    <h3>记录跟进</h3>
    <p>跟进方式</p>
    <div class="m-act-grid" id="actTypeGrid">
      <label class="m-act-btn"><input type="radio" name="actType" value="phone" checked><span>电</span><em>电话</em></label>
      <label class="m-act-btn"><input type="radio" name="actType" value="visit"><span>拜</span><em>拜访</em></label>
      <label class="m-act-btn"><input type="radio" name="actType" value="meeting"><span>会</span><em>会议</em></label>
      <label class="m-act-btn"><input type="radio" name="actType" value="wechat"><span>微</span><em>微信</em></label>
    </div>
    <p>跟进内容</p>
    <textarea class="m-input" id="actContent" rows="3" maxlength="500" placeholder="本次沟通要点、客户意向等" style="resize:vertical;height:auto"></textarea>
    <p>下次跟进（可选）</p>
    <input class="m-input" type="datetime-local" id="actNextFollow" style="margin-top:8px">
    <div class="m-sheet-actions">
      <button class="m-btn m-btn-ghost" id="actCancel">取消</button>
      <button class="m-btn m-btn-brand" id="actConfirm">保存</button>
    </div>
  </div>
</div>

<!-- REV-31：客户操作 JS（认领/释放/转移） -->
<script>
/* ===== v2.40.0 P0-2 记录跟进（v2.48.0 快捷录入：记住方式/快捷短语/下次跟进可选/局部插入） ===== */
var ACT_ICON  = {phone:'电', visit:'拜', meeting:'会', wechat:'微'};
var ACT_LABEL = {phone:'电话', visit:'拜访', meeting:'会议', wechat:'微信'};
var ME_NAME = <?=json_encode($me_name ?? '我', JSON_UNESCAPED_UNICODE)?>;
// v2.47.9 后：跟进方式选中态由 JS 控制（.on 类 + radio.checked 同步），
// 不再依赖 :has(input:checked)——钉钉老 WebView 不支持该选择器，点击无反馈被误认为「无法选择」
function setActType(input) {
  document.querySelectorAll('#actTypeGrid .m-act-btn').forEach(function(b){
    var i = b.querySelector('input');
    var on = (i === input);
    b.classList.toggle('on', on);
    i.checked = on;
  });
}
function openActSheet() {
  var last = 'phone';
  try { last = localStorage.getItem('mActType') || 'phone'; } catch(e) {}
  var rb = document.querySelector('#actTypeGrid input[value="'+last+'"]');
  setActType(rb);
  document.getElementById('actContent').value = '';
  document.getElementById('actNextFollow').value = '';
  // 快捷短语区（actPhraseRow/actNextRow）已随 v2.48.0 精简下架，防空指针
  var pr = document.getElementById('actPhraseRow');
  if (pr) pr.querySelectorAll('.m-phrase').forEach(function(p){ p.classList.remove('on'); });
  var nr = document.getElementById('actNextRow');
  if (nr) nr.querySelectorAll('.m-phrase').forEach(function(p){ p.classList.remove('on'); });
  document.getElementById('actSheetMask').classList.add('show');
}
function insertPhrase(phrase) {
  var ta = document.getElementById('actContent');
  var v = ta.value.trim();
  ta.value = v === '' ? phrase : v + '\n' + phrase;
  ta.focus();
}
function setActNext(days) {
  var el = document.getElementById('actNextFollow');
  var chips = document.getElementById('actNextRow').querySelectorAll('.m-phrase');
  var wasOn = document.querySelector('#actNextRow .m-phrase.on[data-next="' + days + '"]');
  if (wasOn) {           // v2.47.9：再点已选中的快捷选项 → 取消下次跟进
    chips.forEach(function(p){ p.classList.remove('on'); });
    el.value = '';
    return;
  }
  chips.forEach(function(p){ p.classList.remove('on'); });
  var self = document.querySelector('#actNextRow .m-phrase[data-next="' + days + '"]');
  if (self) self.classList.add('on');
  if (String(days) === '0') { el.focus(); return; }
  var d = new Date();
  d.setDate(d.getDate() + parseInt(days, 10));
  if (parseInt(days, 10) === 1) d.setHours(9, 0, 0, 0);
  function pad(n){ return String(n).padStart(2, '0'); }
  el.value = d.getFullYear() + '-' + pad(d.getMonth()+1) + '-' + pad(d.getDate()) + 'T' + pad(d.getHours()) + ':' + pad(d.getMinutes());
}
function doSaveAct() {
  var type = document.querySelector('#actTypeGrid input[name="actType"]:checked');
  var content = document.getElementById('actContent').value.trim();
  var next = document.getElementById('actNextFollow').value;
  if (!content) { toast('请填写跟进内容'); return; }
  var t = type ? type.value : 'phone';
  try { localStorage.setItem('mActType', t); } catch(e) {}
  var mask = document.getElementById('actSheetMask');
  mask.classList.remove('show');
  var fd = new URLSearchParams();
  fd.append('type', t);
  fd.append('content', content);
  fd.append('next_follow_at', next);
  fetch('/ajax/customer/' + mCustId + '/activity', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken() },
    body: fd.toString()
  }).then(r => r.json()).then(d => {
    if (d.code === 0) { toast('已记录跟进'); insertActRow(t, content, next); }
    else { toast(d.msg || '记录失败'); mask.classList.add('show'); }
  }).catch(() => { toast('网络错误'); mask.classList.add('show'); });
}
/* v2.48.0：保存成功后局部插入「今天」分组顶部，不整页刷新 */
function insertActRow(type, content, next) {
  var list = document.getElementById('actList');
  if (!list) return;
  var empty = document.getElementById('actEmpty');
  if (empty) empty.remove();
  var now = new Date();
  function pad(n){ return String(n).padStart(2, '0'); }
  var hm = pad(now.getHours()) + ':' + pad(now.getMinutes());
  var nextHtml = next ? '<span class="m-act-next">下次 ' + esc(next.replace('T', ' ').substring(0, 16)) + '</span>' : '';
  var row = '<div class="m-act-item">'
    + '<span class="m-act-ic m-act-ic-' + type + '" title="' + esc(ACT_LABEL[type] || '') + '">' + (ACT_ICON[type] || '记') + '</span>'
    + '<div class="m-act-bd"><div class="m-act-txt">' + esc(content) + '</div>'
    + '<div class="m-act-meta">' + esc(ME_NAME) + ' · ' + hm + nextHtml + '</div></div></div>';
  function el(html){ var d = document.createElement('div'); d.innerHTML = html; return d.firstChild; }
  var nodes = list.children, todayGrp = null, todayFirst = null;
  for (var i = 0; i < nodes.length; i++) {
    if (nodes[i].className === 'm-act-grp' && nodes[i].textContent.trim() === '今天') { todayGrp = nodes[i]; todayFirst = nodes[i + 1] || null; break; }
  }
  if (todayGrp) {
    list.insertBefore(el(row), todayFirst);
  } else {
    list.insertBefore(el('<div class="m-act-grp">今天</div>'), list.firstChild);
    list.insertBefore(el(row), list.firstChild.nextSibling);
  }
  var cnt = document.getElementById('actCount');
  if (cnt) cnt.textContent = parseInt(cnt.textContent, 10) + 1;
}
function initActSheet() {
  var mask = document.getElementById('actSheetMask');
  if (!mask) return;
  document.getElementById('actCancel').addEventListener('click', function() { mask.classList.remove('show'); });
  document.getElementById('actConfirm').addEventListener('click', doSaveAct);
  // v2.47.9：跟进方式改 JS 事件委托（不依赖 label 隐式激活隐藏 radio——钉钉 WebView 中不可靠）；
  // preventDefault 阻止 label 激活产生第二次合成 click（否则选中后立即被翻转取消）；
  // 点已选中的方式可取消选择，保存时回退 phone
  document.getElementById('actTypeGrid').addEventListener('click', function(e) {
    var label = e.target.closest ? e.target.closest('.m-act-btn') : null;
    if (!label) return;
    e.preventDefault();
    var input = label.querySelector('input');
    setActType(label.classList.contains('on') ? null : input);
  });
  // v2.48.0 后快捷短语区已下架：元素可能不存在，绑定前判空（否则空指针中断后续弹窗初始化）
  var apr = document.getElementById('actPhraseRow');
  if (apr) apr.addEventListener('click', function(e) {
    var p = e.target.closest ? e.target.closest('.m-phrase') : null;
    if (!p) return;
    if (p.classList.contains('on')) { p.classList.remove('on'); return; }  // 再点取消高亮
    p.classList.add('on');
    insertPhrase(p.getAttribute('data-phrase'));
  });
  var anr = document.getElementById('actNextRow');
  if (anr) anr.addEventListener('click', function(e) {
    var p = e.target.closest ? e.target.closest('.m-phrase') : null;
    if (p) setActNext(p.getAttribute('data-next'));
  });
}
initActSheet();

// 转移：选人弹窗（弹窗交互初始化见下方 initTransferModal()）
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
  // v2.51.4：transferBtn 已改 a 标签（紧凑胶囊），用内联样式禁用替代 button.disabled
  btn.style.pointerEvents = 'none'; btn.style.opacity = '.6';
  btn.innerHTML = '<i class="bi bi-hourglass-split"></i> 转移中…';
  fetch('/ajax/customer/' + _custTransferId + '/transfer', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken() },
    body: 'to_user_id=' + encodeURIComponent(sel.value)
  }).then(r => r.json()).then(d => {
    if (d.code === 0) { toast('转移成功'); location.reload(); }
    else { toast(d.msg || '转移失败'); btn.style.pointerEvents = ''; btn.style.opacity = ''; btn.innerHTML = '<i class="bi bi-arrow-left-right"></i> 转移'; }
  }).catch(() => { toast('网络错误'); btn.style.pointerEvents = ''; btn.style.opacity = ''; btn.innerHTML = '<i class="bi bi-arrow-left-right"></i> 转移'; });
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
        document.getElementById('mContactPrimary').checked = item ? (item.is_primary == 1) : false;
        document.getElementById('mContactRemark').value = item ? (item.remark || '') : '';
        box.style.display = 'block';
        box.scrollIntoView({ behavior: 'smooth', block: 'center' });
      }).catch(function() { toast('加载失败'); });
  } else {
    document.getElementById('mContactId').value = 0;
    document.getElementById('mContactName').value = '';
    document.getElementById('mContactPhone').value = '';
    document.getElementById('mContactEmail').value = '';
    document.getElementById('mContactPrimary').checked = false;
    document.getElementById('mContactRemark').value = '';
    box.style.display = 'block';
    box.scrollIntoView({ behavior: 'smooth', block: 'center' });
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
/* ===== 2026-08-11：共享 / 集团管理（移动端，复用 PC 端 AJAX：share/unshare/join-group/group-info） ===== */
var mCustId = <?=(int)$customer['id']?>;
var shareType = 'USER';
// v2.47.8 后：共享用户搜索框——仅搜索后列出匹配结果（无关键词不列用户，与 PC 端共享搜索口径一致）
function filterShareUsers(q){
  var list = document.getElementById('shareUserList');
  if(!list) return;
  var opts = list.querySelectorAll('.m-user-opt');
  var hit = 0;
  q = (q || '').trim();
  opts.forEach(function(l){
    var t = (l.querySelector('span') || l).textContent || '';
    var show = !!q && t.indexOf(q) !== -1;   // 必须有关键词才显示
    l.style.display = show ? '' : 'none';
    if(show) hit++;
  });
  var empty = list.querySelector('.m-share-empty');
  var msg = '';
  if(!q){ msg = '输入姓名搜索用户'; }
  else if(hit === 0 && opts.length){ msg = '未找到匹配用户'; }
  if(msg){
    if(!empty){
      empty = document.createElement('div');
      empty.className = 'm-user-empty m-share-empty';
      list.appendChild(empty);
    }
    empty.textContent = msg;
  } else if(empty){
    empty.remove();
  }
}
function mSetShareType(t){
  shareType = t;
  document.getElementById('shareUserList').style.display = (t === 'USER') ? '' : 'none';
  document.getElementById('shareDeptList').style.display = (t === 'DEPT') ? '' : 'none';
  var s = document.getElementById('shareUserSearch');
  if (s) {
    s.style.display = (t === 'USER') ? '' : 'none';
    if (t === 'USER') filterShareUsers(s.value);   // 切回用户模式时按当前关键词刷新
  }
}
function mOpenShareSheet(){
  var s = document.getElementById('shareUserSearch');
  if (s) { s.value = ''; filterShareUsers(''); }
  document.getElementById('shareSheetMask').classList.add('show');
}
function mConfirmShare(){
  var sel = document.querySelector('#shareSheetMask input[name="shareTo"]:checked');
  if(!sel){ toast('请选择共享对象'); return; }
  document.getElementById('shareSheetMask').classList.remove('show');
  var fd = new URLSearchParams();
  fd.append('target_type', shareType);
  fd.append('target_id', sel.value);
  fetch('/ajax/customer/' + mCustId + '/share', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken() },
    body: fd.toString()
  }).then(r => r.json()).then(function(d) {
    if (d.code === 0) { toast('共享成功'); location.reload(); }
    else toast(d.msg || '共享失败');
  }).catch(function(){ toast('网络错误'); });
}
function mRemoveShare(link){
  var key = link.getAttribute('data-share-key'); // TYPE:id
  if(!key) return;
  var parts = key.split(':');
  mConfirm('确定撤销该共享？撤销后对方不再可见此客户。', function(){
    var fd = new URLSearchParams();
    fd.append('target_type', parts[0]);
    fd.append('target_id', parts[1]);
    fetch('/ajax/customer/' + mCustId + '/unshare', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken() },
      body: fd.toString()
    }).then(r => r.json()).then(function(d) {
      if (d.code === 0) { toast('已撤销共享'); location.reload(); }
      else toast(d.msg || '撤销失败');
    }).catch(function(){ toast('网络错误'); });
  });
}
// v2.47.8：集团归属 options 已由服务端注入，打开弹层即就绪（不再依赖 AJAX 填充）
function mOpenGroupSheet(){
  document.getElementById('groupSheetMask').classList.add('show');
}
function mConfirmGroup(){
  var hid = document.getElementById('mGroupParentId');
  var pid = parseInt(hid ? hid.value : '0', 10) || 0;
  document.getElementById('groupSheetMask').classList.remove('show');
  var fd = new URLSearchParams();
  fd.append('parent_id', pid);
  fetch('/ajax/customer/' + mCustId + '/join-group', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken() },
    body: fd.toString()
  }).then(r => r.json()).then(function(d) {
    if (d.code === 0) { toast(d.msg || '已保存'); location.reload(); }
    else toast(d.msg || '保存失败');
  }).catch(function(){ toast('网络错误'); });
}
// v2.47.8：取消集团归属（已设置时弹层显示该按钮）——确认后直接提交 parent_id=0 移除并刷新
function mRemoveGroup(){
  mConfirm('确定取消该客户的集团归属？取消后将成为独立客户。', function(){
    document.getElementById('groupSheetMask').classList.remove('show');
    var fd = new URLSearchParams();
    fd.append('parent_id', '0');
    fetch('/ajax/customer/' + mCustId + '/join-group', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken() },
      body: fd.toString()
    }).then(r => r.json()).then(function(d) {
      if (d.code === 0) { toast(d.msg || '已取消集团归属'); location.reload(); }
      else toast(d.msg || '操作失败');
    }).catch(function(){ toast('网络错误'); });
  });
}
(function(){
  var sm = document.getElementById('shareSheetMask');
  if (sm) {
    document.getElementById('shareCancel').addEventListener('click', function(){ sm.classList.remove('show'); });
    document.getElementById('shareConfirm').addEventListener('click', mConfirmShare);
    // v2.47.8 后：共享用户搜索框本地过滤
    var su = document.getElementById('shareUserSearch');
    if (su) {
      var suTimer = null;
      su.addEventListener('input', function(){
        clearTimeout(suTimer);
        suTimer = setTimeout(function(){ filterShareUsers(su.value); }, 200);
      });
    }
  }
  var gm = document.getElementById('groupSheetMask');
  if (gm) {
    document.getElementById('groupCancel').addEventListener('click', function(){ gm.classList.remove('show'); });
    document.getElementById('groupConfirm').addEventListener('click', mConfirmGroup);
    // 与转移弹窗一致：点击遮罩空白处关闭
    gm.addEventListener('click', function(e){ if (e.target === gm) gm.classList.remove('show'); });
    // v2.47.8：取消集团归属（仅已设置时存在该按钮）
    var gr = document.getElementById('groupRemove');
    if (gr) gr.addEventListener('click', mRemoveGroup);
  }
})();

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
<?php /* v2.47.8：集团归属搜索选择器数据源（全量客户，排除自身） */ ?>
<script>window.__groupOptions = <?= json_encode(array_values(array_filter(array_map(function($go) use ($customer) {
    return (int)$go['id'] === (int)$customer['id'] ? null : ['id' => (int)$go['id'], 'name' => (string)$go['name']];
}, $group_options ?? []))), JSON_UNESCAPED_UNICODE) ?>;</script>
<script>
// v2.47.8：移动端补 $ajax 兼容（search-picker 快速新建走带 CSRF 的请求，移动端公共 JS 未定义该全局函数）
window.$ajax = function(url, opts) {
  var o = opts || {};
  var init = { method: o.method || 'GET', headers: { 'X-Requested-With': 'XMLHttpRequest' } };
  if (o.body) {
    if (o.body instanceof FormData) { init.body = o.body; }
    else { init.headers['Content-Type'] = 'application/x-www-form-urlencoded'; init.body = o.body; }
    init.headers['X-CSRF-TOKEN'] = csrfToken();
  }
  return fetch(url, init).then(function(r) { return r.json(); });
};
</script>
<script src="<?=asset_url('js/search-picker.js')?>"></script>
<!-- v2.48.0：底部固定「记录跟进」快捷入口（负责人/超管可见），任何位置一键可录 -->
<?php if(!empty($can_edit) && (!empty($is_owner) || !empty($is_super_admin))): ?>
<div class="m-follow-dock" id="mFollowDock"><button type="button" class="m-btn m-btn-brand" onclick="openActSheet()"><i class="bi bi-pencil"></i> 记录跟进</button></div>
<?php endif; ?>
<?php $tab = 'customer'; include __DIR__ . '/_foot.php'; ?>
