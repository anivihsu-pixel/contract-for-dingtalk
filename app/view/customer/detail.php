<?php $title='客户详情'; $menu_active='customer'; include __DIR__.'/../layout/header.php'; ?>
<div class="d-flex justify-content-between align-items-center mb-3"><h4><?=htmlspecialchars($customer['name']??'')?></h4>
<div>
  <!-- 2026-08-03：PC 端客户操作（与移动端 REV-31 对齐）。公海客户可认领；本人客户可释放/转移 -->
  <?php if(!empty($can_edit) && !empty($is_public_pool)): ?>
  <button type="button" class="btn btn-success btn-sm" onclick="custClaim(<?=$customer['id']?>,this)"><i class="bi bi-person-check"></i> 认领</button>
  <?php elseif(!empty($can_edit) && !empty($is_owner)): ?>
  <button type="button" class="btn btn-warning btn-sm" onclick="custRelease(<?=$customer['id']?>,this)"><i class="bi bi-box-arrow-up"></i> 释放到公海</button>
  <button type="button" class="btn btn-outline-primary btn-sm" onclick="openTransferModal(<?=$customer['id']?>)"><i class="bi bi-arrow-left-right"></i> 转移</button>
  <?php endif; ?>
  <a href="/customer/<?=$customer['id']?>/edit" class="btn btn-primary btn-sm"><i class="bi bi-pencil"></i> 编辑</a> <a href="/customer" class="btn btn-outline-secondary btn-sm">返回</a>
</div></div>

<ul class="nav nav-tabs mb-3" id="custTabs" role="tablist">
  <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#t-info">基本信息</button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#t-contract">关联合同 (<?=$contract_total?>)</button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#t-pay">回款记录 (<?=count($payments)?>)</button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#t-act">跟进 (<?=$stats['activity_count']?>)</button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#t-stat">统计</button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#t-contacts">联系人 (<?=count($contacts)?>)</button></li>
  <!-- v2.45.0：共享 / 集团 Tab -->
  <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#t-share">共享</button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#t-group" data-group-tab>集团</button></li>
</ul>

<div class="tab-content">
  <!-- 基本信息 -->
  <div class="tab-pane fade show active" id="t-info">
    <div class="card stat-card"><div class="card-body"><table class="table table-sm"><tbody>
      <tr><td class="text-muted" width="100">名称</td><td><strong><?=htmlspecialchars($customer['name']??'')?></strong></td><td class="text-muted" width="100">风险</td><td><?=!empty($customer['high_risk'])?' <span class="pc-tag pc-tag-danger">高风险</span>':'<span class="text-muted">正常</span>'?></td></tr>
      <!-- v2.38.9：生命周期展示（与漏斗同色） -->
      <?php $lc = $customer['lifecycle_status'] ?? 'ACTIVE'; $lcCls = ['POTENTIAL'=>'pc-tag-info','ACTIVE'=>'pc-tag-ok','INACTIVE'=>'pc-tag-warn'][$lc] ?? 'pc-tag-muted'; ?>
      <tr><td class="text-muted">生命周期</td><td><span class="pc-tag <?=$lcCls?>"><?=htmlspecialchars($lifecycle_dict[$lc] ?? $lc)?></span></td>
      <!-- v2.40.0 P1-7：客户行业展示 -->
      <td class="text-muted">行业</td><td><?php $ind=$customer['industry']??''; echo $ind?htmlspecialchars($industry_dict[$ind]??$ind):'<span class="text-muted">—</span>'; ?></td></tr>
      <tr><td class="text-muted">信用代码</td><td><?=htmlspecialchars($customer['credit_code']??'-')?></td><td class="text-muted">法人</td><td><?=htmlspecialchars($customer['legal_person']??'-')?></td></tr>
      <?php $cScore=(int)($customer['credit_score']??100); $cCls=$cScore>=90?'credit-a':($cScore>=80?'credit-b':($cScore>=60?'credit-c':($cScore>=40?'credit-d':'credit-e'))); ?>
      <tr><td class="text-muted">信用评分</td><td colspan="3"><strong class="<?=!empty($customer['high_risk'])?'text-danger':$cCls?>"><?=$cScore?></strong> / 100</td></tr>
      <tr><td class="text-muted">联系人</td><td><?=htmlspecialchars($customer['contact_name']??'-')?></td><td class="text-muted">手机</td><td><?=phone_link($customer['contact_mobile']??'')?></td></tr>
      <tr><td class="text-muted">邮箱</td><td><?=htmlspecialchars($customer['contact_email']??'-')?></td><td class="text-muted">地址</td><td><?=htmlspecialchars($customer['address']??'-')?></td></tr>
      <tr><td class="text-muted">归属人</td><td><?=htmlspecialchars($owner_name?:'公海')?></td><td class="text-muted">状态</td><td><?=(($customer['status']??1)==1)?'正常':'禁用'?></td></tr>
    </tbody></table></div></div>
  </div>

  <!-- 关联合同 -->
  <div class="tab-pane fade" id="t-contract">
    <div class="card"><div class="card-body p-0"><table class="table table-sm mb-0"><thead><tr><th>合同编号</th><th>标题</th><th>金额</th><th>状态</th></tr></thead><tbody>
      <?php if(empty($contracts)): ?><tr><td colspan="4" class="text-center text-muted py-3">暂无关联合同</td></tr>
      <?php else: foreach($contracts as $ct):
        $stMap = ['DRAFT'=>'草稿','PENDING'=>'审批中','APPROVED'=>'已通过','EXECUTING'=>'执行中','DONE'=>'已完成','REJECTED'=>'已驳回','EXPIRED'=>'已到期','ARCHIVED'=>'已归档'];
        $stTxt = $stMap[$ct['status']] ?? $ct['status'];
      ?>
      <tr><td><?=htmlspecialchars($ct['contract_no']??'')?></td><td><a href="/contract/<?=$ct['id']?>"><?=htmlspecialchars($ct['title'])?></a></td><td class="text-end">¥<?=number_format((float)($ct['amount']??0),0)?></td><td><?=htmlspecialchars($stTxt)?></td></tr>
      <?php endforeach; endif; ?>
    </tbody></table></div></div>
    <?php if($contract_total > $contract_limit): ?><div class="mt-2"><a href="/contract?customer_id=<?=$customer['id']?>" class="btn btn-sm btn-link">查看全部 <?=$contract_total?> 条合同 →</a></div><?php endif; ?>
  </div>

  <!-- 回款记录 -->
  <div class="tab-pane fade" id="t-pay">
    <div class="card"><div class="card-body p-0"><table class="table table-sm mb-0"><thead><tr><th>合同</th><th>计划日</th><th>金额</th><th>状态</th></tr></thead><tbody>
      <?php if(empty($payments)): ?><tr><td colspan="4" class="text-center text-muted py-3">暂无回款记录</td></tr>
      <?php else: foreach($payments as $p):
        $isRecv = ($p['payment_type']??'RECEIVABLE')==='RECEIVABLE';
        $pSt = ['PENDING'=>'待收','PAID'=>'已收','OVERDUE'=>'逾期'];
        $pTxt = $pSt[$p['status']] ?? $p['status'];
      ?>
      <tr><td><?=htmlspecialchars($p['contract_title']??'')?></td><td><?=htmlspecialchars($p['planned_date']??'')?></td><td class="text-end">¥<?=number_format((float)($p['amount']??0),0)?></td><td><?=htmlspecialchars($pTxt)?></td></tr>
      <?php endforeach; endif; ?>
    </tbody></table></div></div>
  </div>

  <!-- 跟进时间轴 -->
  <div class="tab-pane fade" id="t-act">
    <div class="card"><div class="card-body">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <div class="text-muted small">记录电话/拜访/会议/微信等跟进，可填下次跟进时间</div>
        <?php if(!empty($can_edit) && !empty($is_owner)): ?>
        <button type="button" class="btn btn-primary btn-sm" onclick="openActivityModal()"><i class="bi bi-plus-lg"></i> 记录跟进</button>
        <?php endif; ?>
      </div>
      <?php if(empty($activities)): ?><div class="text-muted text-center py-3">暂无跟进记录</div>
      <?php else: foreach($activities as $a): ?>
        <div class="d-flex mb-2"><div class="me-2"><span class="pc-tag pc-tag-muted border"><?=htmlspecialchars(activity_type_label($a['type']??''))?></span></div>
          <div><div><?=htmlspecialchars($a['content']??'')?></div>
            <div class="small text-muted"><?=htmlspecialchars($a['user_name']??'')?> · <?=htmlspecialchars(substr($a['created_at']??'',0,16))?>
            <?php if(!empty($a['next_follow_at'])): ?> · <span class="text-warning">下次跟进：<?=htmlspecialchars(substr($a['next_follow_at'],0,16))?></span><?php endif; ?>
            </div></div></div>
      <?php endforeach; endif; ?>
    </div></div>
  </div>

  <!-- 统计 / 往来汇总（v2.38.14：升级为 360 交易合同口径 + 最近动态，与移动端同源） -->
  <div class="tab-pane fade" id="t-stat">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <span class="text-muted small">往来统计（仅交易合同计入收支）</span>
      <?php if(!empty($g360['stats'])): ?><a href="/party/customer/<?=$customer['id']?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-compass"></i> 往来全景</a><?php endif; ?>
    </div>
    <?php $gS = $g360['stats'] ?? null; if($gS): ?>
    <div class="row g-3">
      <div class="col-md-3 col-6"><div class="card text-center"><div class="card-body"><div class="h4 mb-0 text-primary">¥<?=number_format((float)$gS['total_amount'],0)?></div><div class="text-muted small">往来总额</div></div></div></div>
      <div class="col-md-3 col-6"><div class="card text-center"><div class="card-body"><div class="h4 mb-0 text-success">¥<?=number_format((float)$gS['received_paid'],0)?></div><div class="text-muted small">已收</div></div></div></div>
      <div class="col-md-3 col-6"><div class="card text-center"><div class="card-body"><div class="h4 mb-0 <?=(($gS['balance']??0)>0)?'text-warning':''?>">¥<?=number_format((float)$gS['balance'],0)?></div><div class="text-muted small">待收余额</div></div></div></div>
      <div class="col-md-3 col-6"><div class="card text-center"><div class="card-body"><div class="h4 mb-0 text-danger">¥<?=number_format((float)($stats['overdue_amount']??0),0)?></div><div class="text-muted small">逾期金额</div></div></div></div>
    </div>
    <?php else: ?>
    <div class="row g-3">
      <div class="col-md-3 col-6"><div class="card text-center"><div class="card-body"><div class="h4 mb-0"><?=$stats['contract_total']?></div><div class="text-muted small">关联合同数</div></div></div></div>
      <div class="col-md-3 col-6"><div class="card text-center"><div class="card-body"><div class="h4 mb-0">¥<?=number_format($stats['contract_amount'],0)?></div><div class="text-muted small">关联合同金额</div></div></div></div>
      <div class="col-md-3 col-6"><div class="card text-center"><div class="card-body"><div class="h4 mb-0 text-success">¥<?=number_format($stats['paid_amount'],0)?></div><div class="text-muted small">已回款</div></div></div></div>
      <div class="col-md-3 col-6"><div class="card text-center"><div class="card-body"><div class="h4 mb-0 text-danger">¥<?=number_format($stats['overdue_amount'],0)?></div><div class="text-muted small">逾期金额</div></div></div></div>
    </div>
    <?php endif; ?>
    <?php if(!empty($g360['activity'])): ?>
    <div class="card mt-3"><div class="card-body py-2">
      <div class="text-muted small mb-2">最近动态</div>
      <?php foreach(array_slice($g360['activity'], 0, 5) as $ac): ?>
        <div class="d-flex align-items-center justify-content-between py-1 border-bottom" style="border-color:var(--line)!important">
          <span><span class="pc-tag pc-tag-info"><?=htmlspecialchars(audit_action_label($ac['action'] ?? ''))?></span> 合同 #<?=(int)($ac['target_id'] ?? 0)?></span>
          <span class="small text-muted"><?=htmlspecialchars($ac['created_at'] ?? '')?></span>
        </div>
      <?php endforeach; ?>
    </div></div>
    <?php endif; ?>
  </div>
  <!-- 联系人 (M9) -->
  <div class="tab-pane fade" id="t-contacts">
    <div class="card"><div class="card-body">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <div class="text-muted small">客户的多角色联系人，可标记主联系人</div>
        <button type="button" class="btn btn-primary btn-sm" onclick="openContactModal(0)"><i class="bi bi-plus-lg"></i> 添加联系人</button>
      </div>
      <table class="table table-sm mb-0"><thead><tr><th>姓名</th><th>角色</th><th>电话</th><th>邮箱</th><th>备注</th><th>主联系人</th><th>操作</th></tr></thead><tbody>
        <?php if(empty($contacts)): ?><tr><td colspan="7" class="text-center text-muted py-3">暂无联系人</td></tr>
        <?php else: foreach($contacts as $c): ?>
        <tr>
          <td><strong><?=htmlspecialchars($c['name'])?></strong></td>
          <td><?=htmlspecialchars($c['role'])?></td>
          <td><?=phone_link($c['phone']??'')?></td>
          <td><?=htmlspecialchars($c['email']?:'-')?></td>
          <td class="text-muted small"><?=htmlspecialchars($c['remark']??'')?:'-'?></td>
          <td><?=!empty($c['is_primary'])?'<span class="pc-tag pc-tag-ok">主联系人</span>':'<a href="javascript:;" onclick="setPrimaryContact('.$c['id'].','.$customer['id'].')" class="small text-primary">设为主</a>'?></td>
          <td>
            <?php if(!empty($c['from_primary'])): ?>
              <span class="small text-muted">主联系人（随客户资料维护）</span>
            <?php else: ?>
              <a href="javascript:;" class="small text-primary me-2" onclick="openContactModal(<?=$c['id']?>)">编辑</a>
              <a href="javascript:;" class="small text-danger" onclick="deleteContact(<?=$c['id']?>)">删除</a>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody></table>
    </div></div>
  </div>

  <!-- v2.45.0：共享设置（VIEW 只读：可查看+可关联合同） -->
  <div class="tab-pane fade" id="t-share">
    <div class="card"><div class="card-body">
      <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
        <div class="text-muted small">共享成员可查看本客户并关联合同（VIEW 只读），不可编辑档案；撤销后不再可见</div>
        <?php if(!empty($share_can_manage)): ?>
        <div class="d-flex gap-2">
          <select id="shareTargetType" class="form-select form-select-sm" style="width:100px" onchange="fillShareTargets()">
            <option value="USER">用户</option><option value="DEPT">部门</option>
          </select>
          <select id="shareTargetId" class="form-select form-select-sm" style="width:220px"><option value="0">选择共享对象…</option></select>
          <button type="button" class="btn btn-primary btn-sm" onclick="addShare()"><i class="bi bi-plus-lg"></i> 添加共享</button>
        </div>
        <?php endif; ?>
      </div>
      <table class="table table-sm mb-0"><thead><tr><th>共享对象</th><th>类型</th><th>级别</th><th>操作</th></tr></thead><tbody>
        <?php if(empty($share_list)): ?><tr id="shareEmpty"><td colspan="4" class="text-center text-muted py-3">暂无共享成员</td></tr>
        <?php else: foreach($share_list as $s): ?>
        <tr data-share-row="<?=htmlspecialchars($s['target_type'])?>:<?=(int)$s['target_id']?>">
          <td><strong><?=htmlspecialchars($s['target_name'])?></strong></td>
          <td><?=$s['target_type']==='DEPT'?'部门':'用户'?></td>
          <td><span class="pc-tag pc-tag-info">VIEW 只读</span></td>
          <td><?php if(!empty($share_can_manage)): ?><a href="javascript:;" class="small text-danger" onclick="removeShare(this)">撤销</a><?php else: ?><span class="small text-muted">—</span><?php endif; ?></td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody></table>
      <div class="text-muted small mt-2">提示：曾为该客户签过合同的同事自动可见；跨部门协作请客户负责人添加共享。</div>
    </div></div>
  </div>

  <!-- v2.45.0：集团视图（树 + 合同汇总，懒加载） -->
  <div class="tab-pane fade" id="t-group">
    <div class="card"><div class="card-body" id="groupBody">
      <div class="text-center text-muted py-4" id="groupLoading">点击已加载…</div>
    </div></div>
  </div>
</div>

<!-- 联系人编辑弹窗 (M9) -->
<div class="modal fade" id="contactModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <div class="modal-header"><h6 class="modal-title">联系人</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <input type="hidden" id="custContactId" value="0">
    <input type="hidden" id="custContactCustId" value="<?=$customer['id']?>">
    <div class="row g-2">
      <div class="col-6"><label class="form-label small" for="custContactName">姓名 <span class="text-danger">*</span></label><input type="text" id="custContactName" class="form-control form-control-sm"></div>
      <div class="col-6"><label class="form-label small" for="custContactRole">角色</label><select id="custContactRole" class="form-select form-select-sm">
        <?php foreach($contact_roles as $r): ?><option value="<?=htmlspecialchars($r)?>"><?=htmlspecialchars($r)?></option><?php endforeach; ?>
      </select></div>
      <div class="col-6"><label class="form-label small" for="custContactPhone">电话</label><input type="text" id="custContactPhone" class="form-control form-control-sm"></div>
      <div class="col-6"><label class="form-label small" for="custContactEmail">邮箱</label><input type="text" id="custContactEmail" class="form-control form-control-sm"></div>
      <div class="col-12"><label class="form-label small" for="custContactRemark">更多信息（微信号等）</label><textarea id="custContactRemark" class="form-control form-control-sm" rows="2" placeholder="如微信号、钉钉号等补充联系方式"></textarea></div>
      <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" id="custContactPrimary"><label class="form-check-label small" for="custContactPrimary">设为主联系人</label></div></div>
    </div>
  </div>
  <div class="modal-footer"><button class="btn btn-sm btn-secondary" data-bs-dismiss="modal">取消</button><button class="btn btn-sm btn-primary" onclick="saveContact()">保存</button></div>
</div></div></div>

<!-- 2026-08-03：转移选人弹窗（单选，复用 /ajax/customer/transfer-targets 搜索+分页；与移动端同接口） -->
<div class="modal fade" id="transferModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <div class="modal-header"><h6 class="modal-title">转移客户</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <p class="small text-muted mb-2">选择接收人，客户归属将转移给对方。</p>
    <div class="input-group input-group-sm mb-2">
      <input type="text" id="transferKeyword" class="form-control" placeholder="搜索姓名…">
      <button class="btn btn-outline-secondary" type="button" id="transferSearchBtn" aria-label="搜索"><i class="bi bi-search"></i></button>
    </div>
    <div id="transferUserList" style="max-height:300px;overflow:auto;border:1px solid #dee2e6;border-radius:6px">
      <?php if(empty($transfer_users)): ?>
        <div class="text-center text-muted py-3">暂无可用接收人</div>
      <?php else: foreach($transfer_users as $u): ?>
      <label class="d-flex align-items-center gap-2 px-3 py-2 border-bottom mb-0" style="cursor:pointer;font-size:14px">
        <input type="radio" name="transferTo" value="<?=intval($u['id'])?>" class="form-check-input mt-0">
        <span><?=htmlspecialchars($u['name'])?></span>
      </label>
      <?php endforeach; endif; ?>
    </div>
    <div class="text-center py-2"><button class="btn btn-sm btn-outline-primary" id="transferLoadMore" style="display:none">加载更多</button></div>
  </div>
  <div class="modal-footer"><button class="btn btn-sm btn-secondary" data-bs-dismiss="modal">取消</button><button class="btn btn-sm btn-primary" id="transferConfirmBtn">确认转移</button></div>
</div></div></div>

<!-- v2.40.0 P0-2：记录跟进弹窗 -->
<div class="modal fade" id="activityModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <div class="modal-header"><h6 class="modal-title">记录跟进</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <label class="form-label small mb-1" for="fActTypePhone">跟进方式 <span class="text-danger">*</span></label>
    <div class="d-flex flex-wrap gap-3 mb-2">
      <label class="form-check"><input type="radio" name="actType" value="phone" id="fActTypePhone" class="form-check-input" checked><span class="form-check-label small">电话</span></label>
      <label class="form-check"><input type="radio" name="actType" value="visit" class="form-check-input"><span class="form-check-label small">拜访</span></label>
      <label class="form-check"><input type="radio" name="actType" value="meeting" class="form-check-input"><span class="form-check-label small">会议</span></label>
      <label class="form-check"><input type="radio" name="actType" value="wechat" class="form-check-input"><span class="form-check-label small">微信</span></label>
    </div>
    <label class="form-label small" for="actContent">跟进内容 <span class="text-danger">*</span></label>
    <textarea id="actContent" class="form-control form-control-sm mb-2" rows="3" maxlength="500" placeholder="本次沟通要点、客户意向等"></textarea>
    <label class="form-label small" for="actNextFollow">下次跟进时间</label>
    <input type="datetime-local" id="actNextFollow" class="form-control form-control-sm">
    <div class="form-text small">可不填；填写后将在跟进记录中提示下次跟进时间。</div>
  </div>
  <div class="modal-footer"><button class="btn btn-sm btn-secondary" data-bs-dismiss="modal">取消</button><button class="btn btn-sm btn-primary" onclick="saveActivity()">保存</button></div>
</div></div></div>

<script>
/* ===== v2.40.0 P0-2：记录跟进 ===== */
function openActivityModal(){
  document.getElementById('actContent').value = '';
  document.getElementById('actNextFollow').value = '';
  document.querySelector('#activityModal input[name="actType"]:checked').checked = false;
  document.querySelector('#activityModal input[name="actType"][value="phone"]').checked = true;
  new bootstrap.Modal(document.getElementById('activityModal')).show();
}
function saveActivity(){
  var type = document.querySelector('#activityModal input[name="actType"]:checked');
  var content = document.getElementById('actContent').value.trim();
  var next = document.getElementById('actNextFollow').value;
  if(!content){ showToast('请填写跟进内容','error'); return; }
  var fd = new FormData();
  fd.append('type', type ? type.value : 'phone');
  fd.append('content', content);
  fd.append('next_follow_at', next);
  $ajax('/ajax/customer/<?=$customer['id']?>/activity',{method:'POST',body:fd}).then(function(res){
    if(res.code===0){ showToast('已记录跟进','success'); bootstrap.Modal.getInstance(document.getElementById('activityModal')).hide(); setTimeout(function(){location.reload();},600); }
    else showToast(res.msg||'记录失败','error');
  }).catch(function(){ showToast('记录失败','error'); });
}
function openContactModal(id){
  var modal = new bootstrap.Modal(document.getElementById('contactModal'));
  if(id>0){
    // 拉取该联系人数据
    $ajax('/ajax/customer/<?=$customer['id']?>/contacts').then(function(res){
      var item = (res.data||[]).find(function(x){return x.id==id;});
      document.getElementById('custContactId').value = id;
      document.getElementById('custContactName').value = item?item.name:'';
      document.getElementById('custContactPhone').value = item?item.phone:'';
      document.getElementById('custContactEmail').value = item?item.email:'';
      document.getElementById('custContactRole').value = item?item.role:'商务负责人';
      document.getElementById('custContactPrimary').checked = item?(item.is_primary==1):false;
      document.getElementById('custContactRemark').value = item?item.remark:'';
      modal.show();
    }).catch(function(){ showToast('加载失败','error'); });
  } else {
    document.getElementById('custContactId').value = 0;
    document.getElementById('custContactName').value = '';
    document.getElementById('custContactPhone').value = '';
    document.getElementById('custContactEmail').value = '';
    document.getElementById('custContactRole').value = '商务负责人';
    document.getElementById('custContactPrimary').checked = false;
    document.getElementById('custContactRemark').value = '';
    modal.show();
  }
}
function saveContact(){
  var fd = new FormData();
  fd.append('id', document.getElementById('custContactId').value);
  fd.append('customer_id', document.getElementById('custContactCustId').value);
  fd.append('name', document.getElementById('custContactName').value.trim());
  fd.append('phone', document.getElementById('custContactPhone').value.trim());
  fd.append('email', document.getElementById('custContactEmail').value.trim());
  fd.append('role', document.getElementById('custContactRole').value);
  fd.append('is_primary', document.getElementById('custContactPrimary').checked?1:0);
  fd.append('remark', document.getElementById('custContactRemark').value.trim());
  $ajax('/ajax/customer/contact/save',{method:'POST',body:fd}).then(function(res){
    if(res.code===0){ showToast('已保存','success'); bootstrap.Modal.getInstance(document.getElementById('contactModal')).hide(); setTimeout(function(){location.reload();},600); }
    else showToast(res.msg||'保存失败','error');
  }).catch(function(){ showToast('保存失败','error'); });
}
function deleteContact(id){
  pcConfirm({message:'确定删除该联系人？', danger:true}).then(function(ok){ if(!ok) return;
  var fd = new FormData(); fd.append('id', id);
  $ajax('/ajax/customer/contact/delete',{method:'POST',body:fd,loading:false}).then(function(res){
    if(res.code===0){ showToast('已删除','success'); setTimeout(function(){location.reload();},600); }
    else showToast(res.msg||'删除失败','error');
  }).catch(function(){ showToast('删除失败','error'); });
  });
}
function setPrimaryContact(id, customerId){
  pcConfirm({message:'设为主联系人？将取消其他主联系人。'}).then(function(ok){ if(!ok) return;
  var fd = new FormData(); fd.append('id', id); fd.append('customer_id', customerId);
  $ajax('/ajax/customer/contact/primary',{method:'POST',body:fd,loading:false}).then(function(res){
    if(res.code===0){ showToast('已设为主联系人','success'); setTimeout(function(){location.reload();},600); }
    else showToast(res.msg||'操作失败','error');
  }).catch(function(){ showToast('操作失败','error'); });
  });
}
/* ===== 2026-08-03：PC 端客户操作（认领 / 释放到公海 / 转移，与移动端 REV-31 对齐）===== */
function custClaim(id, btn){
  pcConfirm({message:'确认认领此客户？', okText:'认领'}).then(function(ok){ if(!ok) return;
    var fd = new FormData();
    $ajax('/ajax/customer/'+id+'/claim',{method:'POST',body:fd}).then(function(res){
      if(res.code===0){ showToast('认领成功','success'); setTimeout(function(){location.reload();},600); }
      else showToast(res.msg||'认领失败','error');
    }).catch(function(){ showToast('认领失败','error'); });
  });
}
function custRelease(id, btn){
  pcConfirm({message:'确认将此客户释放到公海？客户将进入公海池。', danger:true, okText:'释放'}).then(function(ok){ if(!ok) return;
    var fd = new FormData();
    $ajax('/ajax/customer/'+id+'/release',{method:'POST',body:fd}).then(function(res){
      if(res.code===0){ showToast('已释放到公海','success'); setTimeout(function(){location.reload();},600); }
      else showToast(res.msg||'释放失败','error');
    }).catch(function(){ showToast('释放失败','error'); });
  });
}
/* 转移选人弹窗：搜索 + 分页（单选） */
var _transferCustId = 0, _tPage = 1, _tKeyword = '', _tTimer = null, _tLoading = false;
var _tInitialHTML = document.getElementById('transferUserList').innerHTML;  // 缓存服务端渲染的初始列表
function openTransferModal(id){
  _transferCustId = id; _tPage = 1; _tKeyword = '';
  document.getElementById('transferKeyword').value = '';
  document.getElementById('transferUserList').innerHTML = _tInitialHTML;
  document.getElementById('transferLoadMore').style.display = 'none';
  new bootstrap.Modal(document.getElementById('transferModal')).show();
}
function renderTransferUsers(users, append){
  var box = document.getElementById('transferUserList');
  var html = users.map(function(u){
    return '<label class="d-flex align-items-center gap-2 px-3 py-2 border-bottom mb-0" style="cursor:pointer;font-size:14px">'
      + '<input type="radio" name="transferTo" value="'+u.id+'" class="form-check-input mt-0"><span>'+esc(u.name)+'</span></label>';
  }).join('');
  if(append) box.insertAdjacentHTML('beforeend', html);
  else box.innerHTML = html || '<div class="text-center text-muted py-3">未找到匹配用户</div>';
}
function searchTransferUsers(page, append){
  _tLoading = true;
  document.getElementById('transferLoadMore').textContent = '加载中…';
  $ajax('/ajax/customer/transfer-targets?keyword='+encodeURIComponent(_tKeyword)+'&page='+page, {loading:false}).then(function(res){
    _tLoading = false;
    if(res.code!==0){ showToast(res.msg||'搜索失败','error'); return; }
    var data = res.data || {};
    if(!data.list || !data.list.length){ if(!append) renderTransferUsers([], false); }
    else renderTransferUsers(data.list, append);
    _tPage = page;
    var lm = document.getElementById('transferLoadMore');
    lm.style.display = data.has_more ? 'block' : 'none';
    lm.textContent = '加载更多';
  }).catch(function(){ _tLoading = false; showToast('搜索失败','error'); });
}
document.getElementById('transferSearchBtn').addEventListener('click', function(){
  _tKeyword = document.getElementById('transferKeyword').value.trim();
  if(_tKeyword===''){ openTransferModal(_transferCustId); return; }
  searchTransferUsers(1, false);
});
document.getElementById('transferKeyword').addEventListener('keydown', function(e){
  if(e.key==='Enter'){ e.preventDefault(); document.getElementById('transferSearchBtn').click(); }
});
document.getElementById('transferLoadMore').addEventListener('click', function(){
  if(_tLoading) return;
  searchTransferUsers(_tPage+1, true);
});
document.getElementById('transferConfirmBtn').addEventListener('click', function(){
  var sel = document.querySelector('#transferModal input[name="transferTo"]:checked');
  if(!sel){ showToast('请选择接收人','error'); return; }
  var fd = new FormData(); fd.append('to_user_id', sel.value);
  $ajax('/ajax/customer/'+_transferCustId+'/transfer',{method:'POST',body:fd}).then(function(res){
    if(res.code===0){ showToast('转移成功','success'); bootstrap.Modal.getInstance(document.getElementById('transferModal')).hide(); setTimeout(function(){location.reload();},600); }
    else showToast(res.msg||'转移失败','error');
  }).catch(function(){ showToast('转移失败','error'); });
});
</script>

<script>
// ============ v2.45.0 客户协作共享 / 集团层级 ============
(function(){
  var custId = <?=(int)$customer['id']?>;
  var canManage = <?= !empty($share_can_manage) ? 'true' : 'false' ?>; // 负责人/超管可管理共享与集团归属
  var shareUsers = <?= json_encode(array_map(function($u){return ['id'=>(int)$u['id'],'name'=>$u['name']];}, $share_target_options ?? []), JSON_UNESCAPED_UNICODE) ?>;
  var shareDepts = <?= json_encode(array_map(function($d){return ['id'=>(int)$d['id'],'name'=>$d['name']];}, $share_departments ?? []), JSON_UNESCAPED_UNICODE) ?>;

  // ---- 共享目标下拉填充（用户/部门切换） ----
  window.fillShareTargets = function(){
    var type = document.getElementById('shareTargetType').value;
    var sel  = document.getElementById('shareTargetId');
    var list = type === 'DEPT' ? shareDepts : shareUsers;
    var h = '<option value="0">选择共享对象…</option>';
    list.forEach(function(it){ h += '<option value="'+it.id+'">'+esc(it.name)+'</option>'; });
    sel.innerHTML = h;
  };
  if (document.getElementById('shareTargetType')) { window.fillShareTargets(); }

  // ---- 添加共享 ----
  window.addShare = function(){
    var type = document.getElementById('shareTargetType').value;
    var id   = parseInt(document.getElementById('shareTargetId').value || '0', 10);
    if(!id){ showToast('请选择共享对象','error'); return; }
    var sel  = document.getElementById('shareTargetId');
    var name = sel.options[sel.selectedIndex] ? sel.options[sel.selectedIndex].text : '';
    var fd = new FormData(); fd.append('target_type', type); fd.append('target_id', id);
    $ajax('/ajax/customer/'+custId+'/share',{method:'POST',body:fd}).then(function(res){
      if(res.code===0){
        showToast(res.msg||'共享成功','success');
        var tbody = document.querySelector('#t-share tbody');
        var empty = document.getElementById('shareEmpty');
        if(empty){ empty.remove(); }
        var tr = document.createElement('tr');
        tr.setAttribute('data-share-row', type+':'+id);
        tr.innerHTML = '<td><strong>'+esc(name)+'</strong></td><td>'+(type==='DEPT'?'部门':'用户')+'</td>'
          + '<td><span class="pc-tag pc-tag-info">VIEW 只读</span></td>'
          + '<td><a href="javascript:;" class="small text-danger" onclick="removeShare(this)">撤销</a></td>';
        tbody.appendChild(tr);
      } else showToast(res.msg||'共享失败','error');
    }).catch(function(){ showToast('共享失败','error'); });
  };

  // ---- 撤销共享（从行 data-share-row 取类型/ID） ----
  window.removeShare = function(link){
    var tr = link.closest('tr');
    var key = tr.getAttribute('data-share-row'); // TYPE:id
    if(!key) return;
    var parts = key.split(':');
    pcConfirm({message:'确定撤销该共享？撤销后对方不再可见此客户。', danger:true}).then(function(ok){
      if(!ok) return;
      var fd = new FormData(); fd.append('target_type', parts[0]); fd.append('target_id', parts[1]);
      $ajax('/ajax/customer/'+custId+'/unshare',{method:'POST',body:fd}).then(function(res){
        if(res.code===0){
          showToast(res.msg||'已撤销共享','success');
          tr.remove();
          // 撤销最后一条共享后补空态行（初始无共享时服务端渲染 shareEmpty；此处动态清空需前端补）
          var tbody = document.querySelector('#t-share tbody');
          if (tbody && !tbody.querySelector('tr')) {
            var empty = document.createElement('tr');
            empty.id = 'shareEmpty';
            empty.innerHTML = '<td colspan="4" class="text-center text-muted py-3">暂无共享成员</td>';
            tbody.appendChild(empty);
          }
        } else showToast(res.msg||'撤销失败','error');
      }).catch(function(){ showToast('撤销失败','error'); });
    });
  };

  // ---- 集团视图懒加载（点击「集团」Tab 首次渲染） ----
  var groupLoaded = false;
  var groupTabBtn = document.querySelector('[data-group-tab]');
  if (groupTabBtn) {
    groupTabBtn.addEventListener('shown.bs.tab', function(){
      if (groupLoaded) return;
      groupLoaded = true;
      var body = document.getElementById('groupBody');
      $ajax('/ajax/customer/'+custId+'/group-info',{method:'GET'}).then(function(res){
        if(res.code===0){ body.innerHTML = renderGroup(res.data); }
        else body.innerHTML = '<div class="text-center text-muted py-3">'+(res.msg||'加载失败')+'</div>';
      }).catch(function(){ body.innerHTML = '<div class="text-center text-muted py-3">加载失败</div>'; });
    });
  }

  function esc(s){ return String(s==null?'':s).replace(/[&<>"']/g, function(m){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]; }); }
  function money(v){ return '¥'+Number(v||0).toLocaleString('zh-CN', {maximumFractionDigits: 0}); }

  function renderGroup(d){
    // 后端 tree 为根的子孙节点；此处包一层根节点展示完整层级
    var rootNode = { name: d.root_name || '集团', children: d.tree || [] };
    var tree = buildTreeHtml([rootNode], d.root_id, true);
    var s = d.summary || {};
    var rootName = '';
    if (d.tree && d.tree.length) rootName = d.tree[0].name || '';
    var h = '<div class="row g-3"><div class="col-md-6">';
    h += '<div class="text-muted small mb-2">集团成员层级'+(d.is_root?'':'（所属集团根：'+esc(rootName)+'）')+'</div>';
    h += tree;
    h += '</div><div class="col-md-6">';
    h += '<div class="text-muted small mb-2">集团合同汇总（含全部子孙客户）</div>';
    h += '<div class="d-flex gap-2 mb-2">';
    h += '<div class="border rounded p-2 flex-fill text-center"><div class="h5 mb-0 text-primary">'+(s.contract_total||0)+'</div><div class="small text-muted">合同数</div></div>';
    h += '<div class="border rounded p-2 flex-fill text-center"><div class="h5 mb-0 text-primary">'+money(s.contract_amount)+'</div><div class="small text-muted">合同总额</div></div>';
    h += '<div class="border rounded p-2 flex-fill text-center"><div class="h5 mb-0 text-primary">'+money(s.paid_amount)+'</div><div class="small text-muted">已回款</div></div>';
    h += '</div>';
    if ((s.children||[]).length) {
      h += '<table class="table table-sm mb-0"><thead><tr><th>子公司</th><th class="text-end">合同</th><th class="text-end">金额</th></tr></thead><tbody>';
      (s.children||[]).forEach(function(c){
        h += '<tr><td>'+esc(c.name)+'</td><td class="text-end">'+c.contract_total+'</td><td class="text-end">'+money(c.contract_amount)+'</td></tr>';
      });
      h += '</tbody></table>';
    }
    h += '</div></div>';
    if (canManage) {
      var opts = '<option value="0">（独立客户，不属于集团）</option>';
      (d.options||[]).forEach(function(o){
        if (o.id === custId) return;
        opts += '<option value="'+o.id+'"'+(d.current_parent_id===o.id?' selected':'')+'>'+esc(o.name)+'</option>';
      });
      h += '<hr><div class="d-flex align-items-center gap-2 flex-wrap">';
      h += '<span class="small text-muted">加入集团（设置父客户，可多级）：</span>';
      h += '<select id="groupParentSel" class="form-select form-select-sm" style="width:260px">'+opts+'</select>';
      h += '<button type="button" class="btn btn-sm btn-outline-primary" onclick="saveGroupParent()">保存集团归属</button>';
      h += '</div>';
    }
    h += '<div class="text-muted small mt-2">集团负责人 / 共享成员可见全集团聚合（只读）；子公司负责人仅见本公司数据。</div>';
    return h;
  }

  // ---- 保存集团归属（加入/取消；仅负责人/超管） ----
  window.saveGroupParent = function(){
    var sel = document.getElementById('groupParentSel');
    var pid = parseInt(sel.value||'0', 10);
    var fd = new FormData(); fd.append('parent_id', pid);
    $ajax('/ajax/customer/'+custId+'/join-group',{method:'POST',body:fd}).then(function(res){
      if(res.code===0){ showToast(res.msg||'已保存','success'); setTimeout(function(){ location.reload(); }, 800); }
      else showToast(res.msg||'保存失败','error');
    }).catch(function(){ showToast('保存失败','error'); });
  };

  function buildTreeHtml(nodes, curId, isRoot){
    var h = '<ul class="list-unstyled mb-0" style="padding-left:'+(isRoot?0:20)+'px">';
    (nodes||[]).forEach(function(n){
      var isCur = n.id === curId;
      h += '<li class="py-1">';
      h += '<span class="'+(isCur?'fw-bold text-primary':'')+'">'+(n.children&&n.children.length?'▾ ':'')+esc(n.name)+'</span>';
      if (isCur) h += ' <span class="pc-tag pc-tag-info">本客户</span>';
      if (n.children && n.children.length) h += buildTreeHtml(n.children, curId, false);
      h += '</li>';
    });
    h += '</ul>';
    return h;
  }
})();
</script>
<?php include __DIR__.'/../layout/footer.php'; ?>
