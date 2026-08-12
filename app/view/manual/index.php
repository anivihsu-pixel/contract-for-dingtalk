<?php $title='使用手册'; $menu_active='manual'; include __DIR__.'/../layout/header.php'; ?>
<style>
/* ===== 使用手册页面样式（mn- 前缀避免与 Bootstrap 冲突） ===== */
.manual-wrap{max-width:1024px;margin:0 auto}
.mn-hero{
  background:linear-gradient(180deg,var(--primary),#0a4fc0);color:#fff;
  border-radius:var(--radius-card);padding:26px 30px 22px;margin-bottom:18px;
}
.mn-hero h4{margin:0 0 8px;font-weight:700;display:flex;align-items:center;gap:8px}
.mn-hero .mn-lead{margin:0;font-size:14px;opacity:.92}
.mn-hero .mn-chips{margin-top:12px;display:flex;flex-wrap:wrap;gap:8px}
.mn-hero .mn-chips span{background:rgba(255,255,255,.18);border-radius:999px;padding:2px 12px;font-size:12px}

/* 快速上手 */
.mn-quick{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:18px}
.mn-q{background:var(--card-bg);border:1px solid var(--line);border-radius:var(--radius-card);padding:16px 18px;display:flex;gap:12px;align-items:flex-start}
.mn-q .n{flex:none;width:32px;height:32px;border-radius:10px;background:var(--brand-light);color:var(--primary);font-weight:700;display:flex;align-items:center;justify-content:center}
.mn-q h6{margin:0 0 2px;font-weight:600}
.mn-q p{margin:0;font-size:12.5px;color:var(--text-muted)}

/* 详细目录 */
.mn-dir{background:var(--card-bg);border:1px solid var(--line);border-radius:var(--radius-card);padding:18px 22px;margin-bottom:18px;scroll-margin-top:72px}
.mn-dir-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px}
.mn-dir-head h5{margin:0;font-weight:600;display:flex;align-items:center;gap:8px}
.mn-dir-head h5 i{color:var(--primary)}
.mn-dir-toggle{background:var(--brand-light);color:var(--primary);border:none;border-radius:999px;padding:4px 14px;font-size:12.5px;cursor:pointer}
.mn-dir-body{display:grid;grid-template-columns:repeat(2,1fr);gap:14px 28px}
.mn-dir-group .g-title{font-size:12.5px;font-weight:700;color:var(--text-3);letter-spacing:.05em;margin:6px 0 6px;padding-bottom:4px;border-bottom:1px dashed var(--line)}
.mn-dir-group .g-links{display:flex;flex-direction:column}
.mn-dir-group a{color:var(--text-main);text-decoration:none;font-size:13.5px;padding:4px 8px;border-radius:6px;display:flex;gap:6px;align-items:baseline}
.mn-dir-group a:hover{background:var(--brand-light)}
.mn-dir-group a.active{color:var(--primary);background:var(--brand-light);font-weight:600}
.mn-dir-group a .d{color:var(--text-3);font-size:11.5px}

/* 章节卡片 */
.mn-card{background:var(--card-bg);border:1px solid var(--line);border-radius:var(--radius-card);padding:20px 24px;margin-bottom:16px;scroll-margin-top:72px}
.mn-card h5{margin:0 0 12px;padding-bottom:10px;border-bottom:1px solid var(--line);font-weight:600;display:flex;align-items:center;gap:8px}
.mn-card h5 i{color:var(--primary)}
.mn-card h6{margin:16px 0 8px;font-size:14.5px;font-weight:600}
.mn-card p{margin-bottom:10px}
.mn-card ul,.mn-card ol{margin:0 0 12px 20px}
.mn-card li{margin-bottom:4px}
.mn-sub{background:#fafbfc;border:1px solid var(--line);border-radius:var(--radius-sm);padding:12px 16px;margin-bottom:10px}
.mn-sub h6{margin:0 0 4px;font-weight:600}
.mn-sub p,.mn-sub li{font-size:13.5px;color:var(--text-muted)}
.mn-sub ul{margin:4px 0 0 18px}

/* 表格 */
.mn-tbl-wrap{overflow-x:auto;margin:10px 0 14px}
.mn-tbl{width:100%;border-collapse:collapse;font-size:13.5px;background:#fff}
.mn-tbl th,.mn-tbl td{border:1px solid var(--line);padding:8px 12px;text-align:left;vertical-align:top}
.mn-tbl thead th{background:var(--brand-light);font-weight:600;white-space:nowrap}
.mn-tbl tbody tr:nth-child(even){background:#fafbfc}

/* 步骤卡片 */
.mn-step{background:#fafbfc;border:1px solid var(--line);border-radius:var(--radius-sm);padding:12px 16px 12px 50px;position:relative;margin-bottom:10px}
.mn-step::before{content:attr(data-step);position:absolute;left:15px;top:12px;width:26px;height:26px;border-radius:50%;background:var(--primary);color:#fff;font-weight:600;font-size:13px;display:flex;align-items:center;justify-content:center}
.mn-step h6{margin:0 0 4px;font-weight:600}
.mn-step p,.mn-step li{font-size:13.5px;color:var(--text-muted)}
.mn-step ul{margin:4px 0 0 18px}
.mn-step.mn-ok{background:var(--green-soft);border-color:#cfe9d8}
.mn-step.mn-ok::before{background:var(--green)}
.mn-step.mn-alert{background:var(--red-soft);border-color:#f6caca}
.mn-step.mn-alert::before{background:var(--red);content:"!";font-size:15px}

/* 提示框 */
.mn-callout{border-radius:var(--radius-sm);padding:12px 16px;margin:12px 0;font-size:13.5px;border:1px solid}
.mn-callout .t{font-weight:600;margin-bottom:2px;display:flex;align-items:center;gap:8px}
.mn-callout.mn-note{background:var(--brand-light);border-color:#cdddf5}
.mn-callout.mn-note .t{color:var(--primary)}
.mn-callout.mn-alert{background:var(--red-soft);border-color:#f6caca}
.mn-callout.mn-alert .t{color:var(--red)}
.mn-callout.mn-ok{background:var(--green-soft);border-color:#cfe9d8}
.mn-callout.mn-ok .t{color:var(--green)}

/* 状态徽章 */
.mn-badge{display:inline-block;font-size:12px;border-radius:999px;padding:1px 10px;white-space:nowrap}
.mn-b-draft{background:#f2f3f5;color:#6b7280}
.mn-b-pending{background:var(--brand-light);color:var(--primary)}
.mn-b-active{background:var(--green-soft);color:var(--green)}
.mn-b-terminal{background:#eef0f3;color:#5b6b7d}
.mn-b-archived{background:#fef3c7;color:#b45309}
.mn-b-rejected{background:var(--red-soft);color:var(--red)}

/* 图 */
.mn-figure{background:#fff;border:1px solid var(--line);border-radius:var(--radius-card);padding:22px 18px 12px;margin:14px 0}
.mn-figure svg{display:block;width:100%;height:auto}
.mn-figure figcaption{text-align:center;font-size:12.5px;color:var(--text-3);margin-top:10px;padding-top:8px;border-top:1px dashed var(--line)}
.mn-legend{display:flex;flex-wrap:wrap;gap:6px 16px;justify-content:center;margin-top:12px;font-size:12.5px;color:var(--text-muted)}
.mn-legend .lg{display:inline-flex;align-items:center;gap:6px}
.mn-legend .dot{width:10px;height:10px;border-radius:50%;display:inline-block}

/* 返回顶部 */
.mn-top{position:fixed;right:22px;bottom:26px;z-index:95;width:42px;height:42px;border-radius:50%;border:none;background:var(--primary);color:#fff;font-size:18px;box-shadow:0 2px 10px rgba(0,0,0,.18);cursor:pointer;display:none}
.mn-top.show{display:flex;align-items:center;justify-content:center}

.mn-y{color:var(--green);font-weight:600}
.mn-n{color:var(--text-3)}
@media (max-width:767.98px){
  .mn-hero{padding:20px 18px}
  .mn-card{padding:16px 14px}
  .mn-quick{grid-template-columns:1fr}
  .mn-dir-body{grid-template-columns:1fr}
  .mn-top{right:14px;bottom:70px}
}
</style>

<div class="manual-wrap">

  <!-- 封面 -->
  <div class="mn-hero">
    <h4><i class="bi bi-journal-text"></i> 合同管理 · 使用手册</h4>
    <p class="mn-lead">系统里每一项功能都在这里：照着目录找到你要用的功能，跟着步骤操作即可。电脑端与钉钉手机端通用。</p>
    <div class="mn-chips"><span>面向：全体员工（经理 / 普通员工 / 财务）</span><span>支持：电脑端 与 钉钉手机端</span></div>
  </div>

  <!-- 快速上手 -->
  <div class="mn-quick">
    <div class="mn-q"><div class="n">1</div><div><h6>新建合同</h6><p>「合同管理 → 新建合同」，两步填完保存成草稿</p></div></div>
    <div class="mn-q"><div class="n">2</div><div><h6>提交审批</h6><p>草稿提交后等审批，通过后自动进入执行中</p></div></div>
    <div class="mn-q"><div class="n">3</div><div><h6>归档收尾</h6><p>执行完或到期的合同办归档，随时可查可恢复</p></div></div>
  </div>

  <!-- ===== 详细目录 ===== -->
  <div class="mn-dir" id="mn-dir">
    <div class="mn-dir-head">
      <h5><i class="bi bi-list-ul"></i>详细目录</h5>
      <button type="button" class="mn-dir-toggle" id="mnDirToggle">收起目录</button>
    </div>
    <div class="mn-dir-body" id="mnDirBody">
      <div class="mn-dir-group">
        <div class="g-title">开始使用</div>
        <div class="g-links">
          <a href="#mn-start"><span>认识系统</span></a>
          <a href="#mn-entry"><span>从哪里进入</span></a>
          <a href="#mn-roles"><span>角色与权限</span></a>
          <a href="#mn-scope"><span>数据范围</span></a>
        </div>
        <div class="g-title">合同管理</div>
        <div class="g-links">
          <a href="#mn-lifecycle"><span>合同的一生</span></a>
          <a href="#mn-create"><span>新建合同</span></a>
          <a href="#mn-list"><span>合同列表</span></a>
          <a href="#mn-detail"><span>合同详情</span></a>
          <a href="#mn-edit-del"><span>编辑与删除</span></a>
          <a href="#mn-archive"><span>归档管理</span></a>
          <a href="#mn-renew"><span>续约</span></a>
        </div>
        <div class="g-title">审批中心</div>
        <div class="g-links">
          <a href="#mn-submit"><span>提交审批</span></a>
          <a href="#mn-approve"><span>审批处理</span></a>
          <a href="#mn-recall"><span>撤回与驳回处理</span></a>
        </div>
      </div>
      <div class="mn-dir-group">
        <div class="g-title">客户与供应商</div>
        <div class="g-links">
          <a href="#mn-customer"><span>客户管理</span></a>
          <a href="#mn-supplier"><span>供应商管理</span></a>
          <a href="#mn-party"><span>往来档案（360°）</span></a>
        </div>
        <div class="g-title">项目与资料</div>
        <div class="g-links">
          <a href="#mn-project"><span>项目管理</span></a>
          <a href="#mn-resource"><span>资料库</span></a>
        </div>
        <div class="g-title">财务与发票</div>
        <div class="g-links">
          <a href="#mn-payment"><span>回款管理</span></a>
          <a href="#mn-payout"><span>付款管理</span></a>
          <a href="#mn-invoice-apply"><span>发票申请</span></a>
          <a href="#mn-invoice"><span>发票管理</span></a>
          <a href="#mn-aging"><span>应收账龄</span></a>
          <a href="#mn-report"><span>经营报表</span></a>
        </div>
        <div class="g-title">工作台与提醒</div>
        <div class="g-links">
          <a href="#mn-dashboard"><span>仪表盘（首页）</span></a>
          <a href="#mn-remind"><span>提醒中心</span></a>
        </div>
        <div class="g-title">手机端</div>
        <div class="g-links">
          <a href="#mn-mobile"><span>手机端功能</span></a>
        </div>
      </div>
      <div class="mn-dir-group">
        <div class="g-title">管理员功能</div>
        <div class="g-links">
          <a href="#mn-admin-user"><span>用户管理</span></a>
          <a href="#mn-admin-role"><span>角色权限</span></a>
          <a href="#mn-admin-flow"><span>审批流程</span></a>
          <a href="#mn-admin-dict"><span>字典设置</span></a>
          <a href="#mn-admin-dd"><span>钉钉设置</span></a>
          <a href="#mn-admin-config"><span>系统配置</span></a>
          <a href="#mn-admin-company"><span>本公司主体</span></a>
          <a href="#mn-admin-invoice-form"><span>发票表单</span></a>
          <a href="#mn-admin-recycle"><span>数据回收站</span></a>
          <a href="#mn-admin-audit"><span>审计中心</span></a>
        </div>
        <div class="g-title">遇到问题</div>
        <div class="g-links">
          <a href="#mn-faq"><span>常见问题</span></a>
        </div>
      </div>
    </div>
  </div>

  <!-- ================================================================ -->
  <!-- 开始使用 -->
  <!-- ================================================================ -->

  <section class="mn-card" id="mn-start">
    <h5><i class="bi bi-house-gear"></i>认识系统</h5>
    <p>「合同管理」是系统里<strong>管理公司所有合同</strong>的地方：从起草、审批、执行到归档全程留痕。同时客户、供应商、项目、财务（回款 / 发票）、资料库都围绕合同串起来。</p>
    <div class="mn-tbl-wrap">
    <table class="mn-tbl">
      <thead><tr><th style="width:22%">功能模块</th><th>能做什么</th></tr></thead>
      <tbody>
        <tr><td><strong>合同管理</strong></td><td>新建 / 编辑 / 检索 / 导出合同，审批通过后执行，完结归档，可续约</td></tr>
        <tr><td><strong>审批中心</strong></td><td>提交合同审批、处理待办（通过 / 驳回 / 撤回）、查看审批记录</td></tr>
        <tr><td><strong>客户管理</strong></td><td>客户建档、跟进记录、信用评分、共享、公海池、集团层级、往来汇总</td></tr>
        <tr><td><strong>供应商管理</strong></td><td>供应商建档，供采购合同关联选择</td></tr>
        <tr><td><strong>项目管理</strong></td><td>项目建档，关联合同，汇总收支与毛利，一键验收 / 终止</td></tr>
        <tr><td><strong>财务中心</strong></td><td>回款 / 付款管理、发票申请与开票、应收账龄、经营月报 / 周报</td></tr>
        <tr><td><strong>资料库</strong></td><td>合同范本、开票资料等文件分类存放，上传 / 下载 / 编辑</td></tr>
        <tr><td><strong>提醒中心</strong></td><td>合同到期、回款逾期自动提醒，审批消息站内通知</td></tr>
        <tr><td><strong>仪表盘</strong></td><td>首页一屏看经营：今日提醒、近期回款、草稿待办、KPI、趋势</td></tr>
      </tbody>
    </table>
    </div>
    <div class="mn-callout mn-note">
      <div class="t"><i class="bi bi-info-circle"></i>各模块的关系</div>
      <div>合同要关联已登记的<strong>客户 / 供应商</strong>档案，也可挂在<strong>项目</strong>下；审批通过后自动进入执行中；<strong>回款、发票</strong>挂在合同下；客户签了合同会自动标记为「成交」。<strong>只有交易类合同才计入财务收支</strong>（草稿 / 驳回 / 审批中 / 框架合同不计入）。</div>
    </div>
  </section>

  <section class="mn-card" id="mn-entry">
    <h5><i class="bi bi-compass"></i>从哪里进入</h5>
    <p>登录系统后，所有功能都在<strong>左侧菜单</strong>里。菜单会按你的角色自动显示，点开分组即可进入对应功能。</p>
    <div class="mn-tbl-wrap">
    <table class="mn-tbl">
      <thead><tr><th style="width:24%">菜单</th><th>用途</th></tr></thead>
      <tbody>
        <tr><td><strong>仪表盘</strong></td><td>首页驾驶舱，看提醒、回款、KPI 与趋势</td></tr>
        <tr><td><strong>提醒</strong></td><td>待办、到期 / 逾期提醒、审批消息（带红点角标）</td></tr>
        <tr><td><strong>合同管理</strong></td><td>合同列表 / 新建合同 / 归档管理</td></tr>
        <tr><td><strong>合同审批 / 我的审批</strong></td><td>有审批权限的看「合同审批」（处理待办）；其他人看「我的审批」</td></tr>
        <tr><td><strong>客户管理</strong></td><td>客户列表 / 新增客户 / 公海池 / 供应商 / 往来档案</td></tr>
        <tr><td><strong>项目管理</strong></td><td>项目列表 / 新建项目</td></tr>
        <tr><td><strong>发票申请</strong></td><td>申请开票，走「申请 → 审批 → 开票」</td></tr>
        <tr><td><strong>财务中心</strong></td><td>回款 / 付款 / 发票管理、应收账龄、经营周报</td></tr>
        <tr><td><strong>资料库</strong></td><td>合同范本、开票资料等文件</td></tr>
        <tr><td><strong>系统设置</strong></td><td>管理员使用：用户 / 角色 / 审批流 / 字典 / 钉钉 / 配置 / 回收站 / 审计</td></tr>
        <tr><td><strong>使用手册</strong></td><td>就是你现在看的这份手册</td></tr>
      </tbody>
    </table>
    </div>
    <p><strong>右上角头像菜单</strong>：修改密码、退出登录。</p>
    <div class="mn-callout mn-note">
      <div class="t"><i class="bi bi-lightbulb"></i>小提示</div>
      <div><strong>看不到的菜单说明没有对应权限</strong>，不是系统出问题了。例如普通员工看不到财务中心，管理员才看得到系统设置。</div>
    </div>
  </section>

  <section class="mn-card" id="mn-roles">
    <h5><i class="bi bi-people"></i>角色与权限</h5>
    <p>系统按「角色 + 权限」控制每个人能用什么。下表是典型角色的功能权限（实际以系统「角色权限」配置为准）：</p>
    <div class="mn-tbl-wrap">
    <table class="mn-tbl">
      <thead><tr><th>功能</th><th>普通员工</th><th>部门经理</th><th>财务</th><th>管理员</th></tr></thead>
      <tbody>
        <tr><td>查看合同</td><td class="mn-y">✓ 本人及本部门</td><td class="mn-y">✓ 本部门</td><td class="mn-y">✓</td><td class="mn-y">✓ 全部</td></tr>
        <tr><td>新建 / 编辑合同</td><td class="mn-y">✓</td><td class="mn-y">✓</td><td class="mn-y">✓</td><td class="mn-y">✓</td></tr>
        <tr><td>提交审批</td><td class="mn-y">✓</td><td class="mn-y">✓</td><td class="mn-y">✓</td><td class="mn-y">✓</td></tr>
        <tr><td>审批处理（通过 / 驳回）</td><td class="mn-n">—</td><td class="mn-y">✓</td><td class="mn-n">—</td><td class="mn-y">✓</td></tr>
        <tr><td>登记回款 / 处理发票</td><td class="mn-n">—</td><td class="mn-n">按授权</td><td class="mn-y">✓</td><td class="mn-y">✓</td></tr>
        <tr><td>归档 / 变更状态</td><td class="mn-n">—</td><td class="mn-y">✓</td><td class="mn-n">—</td><td class="mn-y">✓</td></tr>
        <tr><td>删除合同 / 客户</td><td class="mn-n">—</td><td class="mn-n">按授权</td><td class="mn-n">—</td><td class="mn-y">✓</td></tr>
        <tr><td>导出表格</td><td class="mn-n">按授权</td><td class="mn-y">✓</td><td class="mn-y">✓</td><td class="mn-y">✓</td></tr>
        <tr><td>系统设置</td><td class="mn-n">—</td><td class="mn-n">—</td><td class="mn-n">—</td><td class="mn-y">✓</td></tr>
      </tbody>
    </table>
    </div>
  </section>

  <section class="mn-card" id="mn-scope">
    <h5><i class="bi bi-eye"></i>数据范围</h5>
    <p>除了「能做什么」，系统还控制「能看到哪些数据」——即数据范围：</p>
    <div class="mn-tbl-wrap">
    <table class="mn-tbl">
      <thead><tr><th style="width:20%">数据范围</th><th>说明</th></tr></thead>
      <tbody>
        <tr><td><strong>本人（SELF）</strong></td><td>只能看到自己名下（归属人 = 我）的数据</td></tr>
        <tr><td><strong>本部门（DEPT）</strong></td><td>能看到自己及本部门同事的数据</td></tr>
        <tr><td><strong>全部（ALL）</strong></td><td>全公司数据（通常只有管理员）</td></tr>
        <tr><td><strong>自定义部门（CUSTOM）</strong></td><td>按勾选的若干部门查看（仅管理员可配）</td></tr>
      </tbody>
    </table>
    </div>
    <div class="mn-callout mn-note">
      <div class="t"><i class="bi bi-lightbulb"></i>实用提示</div>
      <div>看不到同事的合同 / 客户，通常不是丢了，而是<strong>不在你的数据范围内</strong>。客户还可以通过「共享」给同事访问（见客户管理）。</div>
    </div>
  </section>

  <!-- ================================================================ -->
  <!-- 合同管理 -->
  <!-- ================================================================ -->

  <section class="mn-card" id="mn-lifecycle">
    <h5><i class="bi bi-diagram-3"></i>合同的一生</h5>
    <p>一份合同从创建到归档，状态由系统自动流转：<span class="mn-badge mn-b-draft">草稿</span> → <span class="mn-badge mn-b-pending">待审批</span> → <span class="mn-badge mn-b-active">已通过</span> → <span class="mn-badge mn-b-active">执行中</span> → 终态 → <span class="mn-badge mn-b-archived">已归档</span>。审批被驳回会回到<span class="mn-badge mn-b-rejected">已驳回</span>。</p>

    <figure class="mn-figure">
      <svg viewBox="0 0 720 690" role="img" aria-label="合同生命周期主流程">
        <defs>
          <marker id="mnArr" viewBox="0 0 8 8" refX="7" refY="4" markerWidth="8" markerHeight="8" markerUnits="userSpaceOnUse" orient="auto"><path d="M1 1 L7 4 L1 7 Z" fill="#8a9099"/></marker>
          <marker id="mnArrBlue" viewBox="0 0 8 8" refX="7" refY="4" markerWidth="8" markerHeight="8" markerUnits="userSpaceOnUse" orient="auto"><path d="M1 1 L7 4 L1 7 Z" fill="#0b5ed7"/></marker>
        </defs>
        <g font-family="Helvetica Neue,PingFang SC,Microsoft YaHei,sans-serif" text-anchor="middle">
          <rect x="285" y="70" width="150" height="54" rx="12" fill="#ffffff" stroke="#6b7280" stroke-width="1.5"/>
          <circle cx="308" cy="97" r="4" fill="#8a9099"/>
          <text x="360" y="92" font-size="15" font-weight="600" fill="#1f2329">草稿</text>
          <text x="360" y="112" font-size="11.5" fill="#8a9099">新建后即草稿</text>
          <rect x="285" y="180" width="150" height="54" rx="12" fill="#e8f1ff" stroke="#0b5ed7" stroke-width="1.5"/>
          <circle cx="308" cy="207" r="4" fill="#0b5ed7"/>
          <text x="360" y="202" font-size="15" font-weight="600" fill="#1f2329">待审批</text>
          <text x="360" y="222" font-size="11.5" fill="#8a9099">等待审批人处理</text>
          <rect x="285" y="290" width="150" height="54" rx="12" fill="#e6f4ea" stroke="#16a34a" stroke-width="1.5"/>
          <circle cx="308" cy="317" r="4" fill="#16a34a"/>
          <text x="360" y="312" font-size="15" font-weight="600" fill="#1f2329">已通过</text>
          <text x="360" y="332" font-size="11.5" fill="#8a9099">审批通过即生效</text>
          <rect x="285" y="400" width="150" height="54" rx="12" fill="#e6f4ea" stroke="#16a34a" stroke-width="1.5"/>
          <circle cx="308" cy="427" r="4" fill="#16a34a"/>
          <text x="360" y="422" font-size="15" font-weight="600" fill="#1f2329">执行中</text>
          <text x="360" y="442" font-size="11.5" fill="#8a9099">合同履约期</text>
          <rect x="240" y="510" width="240" height="54" rx="12" fill="#eef0f3" stroke="#5b6b7d" stroke-width="1.5"/>
          <circle cx="263" cy="537" r="4" fill="#5b6b7d"/>
          <text x="360" y="532" font-size="15" font-weight="600" fill="#1f2329">终态</text>
          <text x="360" y="552" font-size="11.5" fill="#8a9099">已完成 · 已到期 · 已终止</text>
          <rect x="285" y="620" width="150" height="54" rx="12" fill="#fef3c7" stroke="#b45309" stroke-width="1.5"/>
          <circle cx="308" cy="647" r="4" fill="#b45309"/>
          <text x="360" y="642" font-size="15" font-weight="600" fill="#1f2329">已归档</text>
          <text x="360" y="662" font-size="11.5" fill="#8a9099">归档管理可查</text>
        </g>
        <g stroke-linecap="round" stroke-linejoin="round">
          <path d="M360 124 L360 180" fill="none" stroke="#0b5ed7" stroke-width="2" marker-end="url(#mnArrBlue)"/>
          <path d="M360 234 L360 290" fill="none" stroke="#0b5ed7" stroke-width="2" marker-end="url(#mnArrBlue)"/>
          <path d="M360 344 L360 400" fill="none" stroke="#0b5ed7" stroke-width="2" marker-end="url(#mnArrBlue)"/>
          <path d="M360 454 L360 510" fill="none" stroke="#0b5ed7" stroke-width="2" marker-end="url(#mnArrBlue)"/>
          <path d="M360 564 L360 620" fill="none" stroke="#0b5ed7" stroke-width="2" marker-end="url(#mnArrBlue)"/>
          <path d="M300 620 L140 620 L140 427 L285 427" fill="none" stroke="#b45309" stroke-width="2" stroke-dasharray="6 5" marker-end="url(#mnArr)"/>
        </g>
        <g font-family="Helvetica Neue,PingFang SC,Microsoft YaHei,sans-serif" font-size="12" text-anchor="middle">
          <rect x="376" y="135" width="64" height="22" rx="11" fill="#ffffff" stroke="#ebedf0"/><text x="408" y="150" fill="#6b7280">提交审批</text>
          <rect x="376" y="245" width="64" height="22" rx="11" fill="#ffffff" stroke="#ebedf0"/><text x="408" y="260" fill="#6b7280">审批通过</text>
          <rect x="376" y="355" width="64" height="22" rx="11" fill="#ffffff" stroke="#ebedf0"/><text x="408" y="370" fill="#6b7280">生效执行</text>
          <rect x="376" y="465" width="64" height="22" rx="11" fill="#ffffff" stroke="#ebedf0"/><text x="408" y="480" fill="#6b7280">执行完结</text>
          <rect x="376" y="575" width="64" height="22" rx="11" fill="#ffffff" stroke="#ebedf0"/><text x="408" y="590" fill="#6b7280">归档</text>
          <rect x="152" y="456" width="128" height="22" rx="11" fill="#fef3c7" stroke="#b45309"/><text x="216" y="471" fill="#b45309">反归档 · 恢复执行</text>
        </g>
      </svg>
      <figcaption>图 1 · 合同生命周期主流程（驳回分支见「审批中心」）</figcaption>
      <div class="mn-legend">
        <span class="lg"><i class="dot" style="background:#8a9099"></i>草稿</span>
        <span class="lg"><i class="dot" style="background:#0b5ed7"></i>待审批</span>
        <span class="lg"><i class="dot" style="background:#16a34a"></i>已通过 / 执行中</span>
        <span class="lg"><i class="dot" style="background:#5b6b7d"></i>终态</span>
        <span class="lg"><i class="dot" style="background:#b45309"></i>已归档</span>
      </div>
    </figure>

    <h6>各状态速查</h6>
    <div class="mn-tbl-wrap">
    <table class="mn-tbl">
      <thead><tr><th style="width:16%">状态</th><th>是什么意思</th><th>你能做什么</th></tr></thead>
      <tbody>
        <tr><td><span class="mn-badge mn-b-draft">草稿</span></td><td>刚保存，还没提交审批</td><td>编辑、提交审批、删除（只有你和本部门同事能看到）</td></tr>
        <tr><td><span class="mn-badge mn-b-pending">待审批</span></td><td>已提交，等审批人处理</td><td>提交人可以撤回；审批人通过或驳回</td></tr>
        <tr><td><span class="mn-badge mn-b-rejected">已驳回</span></td><td>审批未通过</td><td>改完再提交、直接重新提交、或放弃并归档</td></tr>
        <tr><td><span class="mn-badge mn-b-active">已通过</span></td><td>审批通过即生效（不再单独签署）</td><td>转入执行中、归档、终止</td></tr>
        <tr><td><span class="mn-badge mn-b-active">执行中</span></td><td>合同履约阶段</td><td>登记回款、申请发票、完成 / 到期 / 终止、归档、续约</td></tr>
        <tr><td><span class="mn-badge mn-b-terminal">终态</span></td><td>已完成 / 已到期 / 已终止</td><td>归档、恢复执行</td></tr>
        <tr><td><span class="mn-badge mn-b-archived">已归档</span></td><td>历史档案，可搜索</td><td>查看；管理员可恢复执行</td></tr>
      </tbody>
    </table>
    </div>
    <div class="mn-callout mn-note">
      <div class="t"><i class="bi bi-shield-check"></i>为什么不能手动改状态？</div>
      <div>草稿、待审批、已驳回这三种状态<strong>不能手动改</strong>，必须通过「提交 / 撤回 / 审批」推进——防止绕过审批流程，保证每份合同都有人把关。</div>
    </div>
  </section>

  <section class="mn-card" id="mn-create">
    <h5><i class="bi bi-file-earmark-plus"></i>新建合同</h5>
    <p>入口：左侧菜单「合同管理 → <strong>新建合同</strong>」。填写分两步，最后保存成草稿。</p>

    <figure class="mn-figure">
      <svg viewBox="0 0 720 430" role="img" aria-label="新建合同流程">
        <defs>
          <marker id="mnArrB" viewBox="0 0 8 8" refX="7" refY="4" markerWidth="8" markerHeight="8" markerUnits="userSpaceOnUse" orient="auto"><path d="M1 1 L7 4 L1 7 Z" fill="#8a9099"/></marker>
        </defs>
        <g font-family="Helvetica Neue,PingFang SC,Microsoft YaHei,sans-serif" text-anchor="middle">
          <rect x="300" y="40" width="120" height="34" rx="17" fill="#0b5ed7"/>
          <text x="360" y="62" font-size="13.5" font-weight="600" fill="#ffffff">新建合同</text>
          <rect x="285" y="120" width="150" height="54" rx="12" fill="#ffffff" stroke="#6b7280" stroke-width="1.5"/>
          <text x="360" y="142" font-size="15" font-weight="600" fill="#1f2329">第 1 步 基础信息</text>
          <text x="360" y="162" font-size="11.5" fill="#8a9099">标题 · 分类 · 主体 · 金额</text>
          <rect x="285" y="200" width="150" height="54" rx="12" fill="#ffffff" stroke="#6b7280" stroke-width="1.5"/>
          <text x="360" y="222" font-size="15" font-weight="600" fill="#1f2329">第 2 步 详情与附件</text>
          <text x="360" y="242" font-size="11.5" fill="#8a9099">甲乙方 · 日期 · 概要 · 附件</text>
          <rect x="240" y="280" width="240" height="54" rx="12" fill="#e8f1ff" stroke="#0b5ed7" stroke-width="1.5"/>
          <text x="360" y="302" font-size="15" font-weight="600" fill="#1f2329">系统检查</text>
          <text x="360" y="322" font-size="11.5" fill="#8a9099">必填项 · 附件 · 重复检查</text>
          <rect x="285" y="360" width="150" height="54" rx="12" fill="#e6f4ea" stroke="#16a34a" stroke-width="1.5"/>
          <text x="360" y="382" font-size="15" font-weight="600" fill="#1f2329">保存草稿</text>
          <text x="360" y="402" font-size="11.5" fill="#8a9099">可立即提交或稍后</text>
          <path d="M240 307 L192 307" fill="none" stroke="#e23b3b" stroke-width="1.8" stroke-dasharray="5 4" marker-end="url(#mnArrB)"/>
          <rect x="44" y="296" width="148" height="22" rx="11" fill="#ffeaea" stroke="#e23b3b"/>
          <text x="118" y="311" font-size="11.5" fill="#e23b3b">没通过 · 返回修改</text>
        </g>
        <g stroke-linecap="round" stroke-linejoin="round">
          <path d="M360 74 L360 120" fill="none" stroke="#8a9099" stroke-width="2" marker-end="url(#mnArrB)"/>
          <path d="M360 174 L360 200" fill="none" stroke="#8a9099" stroke-width="2" marker-end="url(#mnArrB)"/>
          <path d="M360 254 L360 280" fill="none" stroke="#8a9099" stroke-width="2" marker-end="url(#mnArrB)"/>
          <path d="M360 334 L360 360" fill="none" stroke="#16a34a" stroke-width="2" marker-end="url(#mnArrB)"/>
        </g>
      </svg>
      <figcaption>图 2 · 新建合同流程</figcaption>
    </figure>

    <div class="mn-sub">
      <h6>第 1 步：基础信息</h6>
      <ul>
        <li><strong>合同标题</strong>：必填，写清楚是哪份合同。</li>
        <li><strong>合同分类</strong>：从下拉里选（服务、采购等）。</li>
        <li><strong>合同性质</strong>：勾选「交易合同（计入收支）」。交易合同要填金额、选方向（销售 / 采购）；不勾选则不计入收支、金额不用填。</li>
        <li><strong>签约主体</strong>：我方公司主体，自动带出默认主体。</li>
        <li><strong>关联信息</strong>（选填）：关键词、关联的框架合同、关联项目。</li>
      </ul>
    </div>
    <div class="mn-sub">
      <h6>第 2 步：详情与附件</h6>
      <ul>
        <li><strong>甲 / 乙方</strong>：对方必须<strong>从已登记的客户 / 供应商里选</strong>（没登记就点「快速新建」先建档），不能手输名称。</li>
        <li><strong>生效 / 到期日期</strong>：必填，生效要早于到期。</li>
        <li><strong>合同概要</strong>：必填，简单写清合同内容。</li>
        <li><strong>合同附件</strong>：至少传 1 份，支持 Word / PDF / 图片，单个不超过 20MB。</li>
      </ul>
    </div>
    <div class="mn-callout mn-alert">
      <div class="t"><i class="bi bi-exclamation-triangle"></i>容易卡住的地方</div>
      <ul style="margin:4px 0 0 18px">
        <li><strong>对方没登记</strong>：先点「快速新建」把客户 / 供应商建档，再回来选。</li>
        <li><strong>提示重复合同</strong>：说明有一份标题、对方、金额完全一样的合同，确认是否重复，改一下标题或金额就能继续。</li>
        <li><strong>附件传不上去</strong>：超过 20MB、或文件类型不在白名单（改后缀名伪装也不行）。</li>
        <li><strong>日期不对</strong>：生效日期必须早于到期日期。</li>
      </ul>
    </div>
    <div class="mn-callout mn-ok">
      <div class="t"><i class="bi bi-pencil"></i>保存与编辑</div>
      <div>保存成功后成为<strong>草稿</strong>，系统自动生成合同编号。只有<strong>草稿</strong>和<strong>已驳回</strong>的合同能编辑；已通过、执行中的合同不能再编辑，要调整走「续约」。</div>
    </div>
  </section>

  <section class="mn-card" id="mn-list">
    <h5><i class="bi bi-list-ul"></i>合同列表</h5>
    <p>「合同管理 → 合同列表」是日常用得最多的页面，找合同、导出、批量操作都从这里走。</p>
    <div class="mn-sub">
      <h6>快捷筛选</h6>
      <p>列表顶部「全部 / 草稿 / 我的草稿」一键切换，直达自己的待办草稿。</p>
    </div>
    <div class="mn-sub">
      <h6>条件筛选</h6>
      <p>主行支持<strong>关键词 + 状态</strong>快速搜索；点「高级筛选」可组合：分类、方向、是否框架、日期区间、金额区间、对方名称、签约主体、负责人、关联项目。</p>
    </div>
    <div class="mn-sub">
      <h6>排序</h6>
      <p>点列头按编号 / 标题 / 金额 / 状态 / 创建时间排序；<strong>默认草稿排在最前</strong>，提醒你先处理。</p>
    </div>
    <div class="mn-sub">
      <h6>导出</h6>
      <p>点「导出」把当前筛选结果导成 <strong>Excel</strong> 或 <strong>CSV</strong>（需导出权限），做台账、汇报都方便。</p>
    </div>
    <div class="mn-sub">
      <h6>批量操作</h6>
      <p>勾选多行后出现操作栏：<strong>批量归档</strong>（执行中 / 已完成可归档）、<strong>批量删除</strong>。</p>
    </div>
  </section>

  <section class="mn-card" id="mn-detail">
    <h5><i class="bi bi-file-earmark-text"></i>合同详情</h5>
    <p>点合同标题进入详情页，这里集中了这份合同的所有信息和操作入口。</p>
    <div class="mn-tbl-wrap">
    <table class="mn-tbl">
      <thead><tr><th style="width:24%">内容 / 操作</th><th>说明</th></tr></thead>
      <tbody>
        <tr><td><strong>基本信息</strong></td><td>标题、编号、状态、分类、方向、金额、签约主体、甲乙方、日期、归属人等</td></tr>
        <tr><td><strong>合同附件</strong></td><td>在线查看 / 下载上传的合同文件</td></tr>
        <tr><td><strong>审批记录</strong></td><td>完整审批历史：谁审批、什么意见、抄送给谁，含撤回与驳回</td></tr>
        <tr><td><strong>时间线</strong></td><td>从创建到现在的每一步操作记录</td></tr>
        <tr><td><strong>往来摘要</strong></td><td>对方客户 / 供应商的 360° 汇总：历史合同、回款、发票</td></tr>
        <tr><td><strong>提交审批</strong></td><td>草稿状态点此发起审批</td></tr>
        <tr><td><strong>登记回款 / 申请开票</strong></td><td>执行中的合同可登记回款、申请发票</td></tr>
        <tr><td><strong>状态操作</strong></td><td>执行中可转 完成 / 到期 / 终止 / 归档；已归档可恢复执行</td></tr>
        <tr><td><strong>续约</strong></td><td>生成续约草案（见「续约」）</td></tr>
        <tr><td><strong>删除</strong></td><td>满足条件的合同可删除（见「编辑与删除」）</td></tr>
      </tbody>
    </table>
    </div>
  </section>

  <section class="mn-card" id="mn-edit-del">
    <h5><i class="bi bi-pencil-square"></i>编辑与删除</h5>
    <div class="mn-sub">
      <h6>编辑合同</h6>
      <ul>
        <li>只有<strong>草稿</strong>和<strong>已驳回</strong>的合同能编辑；</li>
        <li>编辑页校验规则与新建一致（对方档案一致、附件来源等）；</li>
        <li>编辑不改变合同归属人。</li>
      </ul>
    </div>
    <div class="mn-sub">
      <h6>删除合同</h6>
      <ul>
        <li>可删状态：草稿、已驳回、已归档、已完成、已到期、已终止（需删除权限）；</li>
        <li>删除为<strong>软删除</strong>，数据进「数据回收站」，管理员可恢复；</li>
        <li><strong>删不掉的情况</strong>：还有进行中的审批、没撤销的回款、发票、关联的子合同——系统会提示先处理。</li>
      </ul>
    </div>
  </section>

  <section class="mn-card" id="mn-archive">
    <h5><i class="bi bi-archive"></i>归档管理</h5>
    <div class="mn-sub">
      <h6>归档</h6>
      <p>合同详情页点「归档」，或在列表勾选多行「批量归档」。只有<strong>执行中 / 已完成</strong>的合同能归档。</p>
    </div>
    <div class="mn-sub">
      <h6>归档后的查找</h6>
      <p>「合同管理 → 归档管理」集中查看已归档合同，支持标题 / 编号搜索。</p>
    </div>
    <div class="mn-sub">
      <h6>恢复执行（反归档）</h6>
      <p>归档管理里点「恢复执行」，合同回到执行中（见图 1 虚线箭头），可用于档案重启。</p>
    </div>
  </section>

  <section class="mn-card" id="mn-renew">
    <h5><i class="bi bi-arrow-repeat"></i>续约</h5>
    <div class="mn-step" data-step="1">
      <h6>一键生成续约草案</h6>
      <p>合同执行中、已归档或已到期，点「<strong>续约</strong>」，系统自动复制原合同内容，生成一份标题带「（续约）」的新草案。</p>
    </div>
    <div class="mn-step" data-step="2">
      <h6>改完重新走审批</h6>
      <p>把日期、金额等改好，按「编辑 → 提交审批」正常走流程。原合同和续约草案自动关联，<strong>一份合同只能续约一次</strong>，防止重复。</p>
    </div>
  </section>

  <!-- ================================================================ -->
  <!-- 审批中心 -->
  <!-- ================================================================ -->

  <section class="mn-card" id="mn-submit">
    <h5><i class="bi bi-send"></i>提交审批</h5>
    <div class="mn-step" data-step="1">
      <h6>发起提交</h6>
      <p>在合同详情页点「<strong>提交审批</strong>」，选一个审批流，提交后合同变成<span class="mn-badge mn-b-pending">待审批</span>，审批人收到待办提醒。</p>
    </div>
    <div class="mn-step" data-step="2">
      <h6>查看进度</h6>
      <p>详情页可随时看<strong>完整审批记录</strong>：谁审批的、什么意见、抄送给了谁，全程留痕。</p>
    </div>
    <div class="mn-callout mn-note">
      <div class="t"><i class="bi bi-info-circle"></i>审批流是什么</div>
      <div>审批流由管理员在「系统设置 → 审批流程」配置（支持按合同分类 + 金额匹配、多级审批、会签、抄送）。提交时选择适用的审批流即可。</div>
    </div>
  </section>

  <section class="mn-card" id="mn-approve">
    <h5><i class="bi bi-check2-circle"></i>审批处理</h5>
    <div class="mn-step" data-step="1">
      <h6>找到待办</h6>
      <p>左侧「合同审批」或顶部铃铛的待办红点进入；合同列表也能看到审批中标记。</p>
    </div>
    <div class="mn-step" data-step="2">
      <h6>通过 / 驳回</h6>
      <p>打开合同，填写审批意见后选<strong>通过</strong>或<strong>驳回</strong>。通过后合同自动进入执行中。</p>
    </div>
    <div class="mn-step" data-step="3">
      <h6>会签节点</h6>
      <p>若审批流配置了会签（AND / OR），需要多个审批人分别处理，按流程设置推进。</p>
    </div>
  </section>

  <section class="mn-card" id="mn-recall">
    <h5><i class="bi bi-arrow-counterclockwise"></i>撤回与驳回处理</h5>
    <figure class="mn-figure">
      <svg viewBox="0 0 720 430" role="img" aria-label="审批结果与驳回处理">
        <defs>
          <marker id="mnArrC" viewBox="0 0 8 8" refX="7" refY="4" markerWidth="8" markerHeight="8" markerUnits="userSpaceOnUse" orient="auto"><path d="M1 1 L7 4 L1 7 Z" fill="#8a9099"/></marker>
          <marker id="mnArrGreen" viewBox="0 0 8 8" refX="7" refY="4" markerWidth="8" markerHeight="8" markerUnits="userSpaceOnUse" orient="auto"><path d="M1 1 L7 4 L1 7 Z" fill="#16a34a"/></marker>
        </defs>
        <g font-family="Helvetica Neue,PingFang SC,Microsoft YaHei,sans-serif" text-anchor="middle">
          <rect x="285" y="50" width="150" height="54" rx="12" fill="#e8f1ff" stroke="#0b5ed7" stroke-width="1.5"/>
          <text x="360" y="72" font-size="15" font-weight="600" fill="#1f2329">待审批</text>
          <text x="360" y="92" font-size="11.5" fill="#8a9099">审批人处理中</text>
          <rect x="40" y="180" width="150" height="54" rx="12" fill="#e6f4ea" stroke="#16a34a" stroke-width="1.5"/>
          <text x="115" y="202" font-size="15" font-weight="600" fill="#1f2329">已通过</text>
          <text x="115" y="222" font-size="11.5" fill="#8a9099">生效执行</text>
          <rect x="285" y="180" width="150" height="54" rx="12" fill="#ffeaea" stroke="#e23b3b" stroke-width="1.5"/>
          <text x="360" y="202" font-size="15" font-weight="600" fill="#1f2329">已驳回</text>
          <text x="360" y="222" font-size="11.5" fill="#8a9099">可重提 / 修改 / 归档</text>
          <rect x="530" y="180" width="150" height="54" rx="12" fill="#ffffff" stroke="#6b7280" stroke-width="1.5"/>
          <text x="605" y="202" font-size="15" font-weight="600" fill="#1f2329">草稿</text>
          <text x="605" y="222" font-size="11.5" fill="#8a9099">撤回 / 修改后重提</text>
          <rect x="530" y="360" width="150" height="54" rx="12" fill="#fef3c7" stroke="#b45309" stroke-width="1.5"/>
          <text x="605" y="382" font-size="15" font-weight="600" fill="#1f2329">已归档</text>
          <text x="605" y="402" font-size="11.5" fill="#8a9099">放弃并归档</text>
        </g>
        <g stroke-linecap="round" stroke-linejoin="round">
          <path d="M285 77 L160 77 L160 180" fill="none" stroke="#16a34a" stroke-width="2" marker-end="url(#mnArrC)"/>
          <path d="M360 104 L360 180" fill="none" stroke="#e23b3b" stroke-width="2" marker-end="url(#mnArrC)"/>
          <path d="M435 77 L560 77 L560 180" fill="none" stroke="#8a9099" stroke-width="2" marker-end="url(#mnArrC)"/>
          <path d="M410 180 L410 104" fill="none" stroke="#16a34a" stroke-width="2" stroke-dasharray="6 5" marker-end="url(#mnArrGreen)"/>
          <path d="M435 207 L530 207" fill="none" stroke="#8a9099" stroke-width="2" marker-end="url(#mnArrC)"/>
          <path d="M360 234 L360 292 L560 292 L560 360" fill="none" stroke="#b45309" stroke-width="2" marker-end="url(#mnArrC)"/>
        </g>
        <g font-family="Helvetica Neue,PingFang SC,Microsoft YaHei,sans-serif" font-size="12" text-anchor="middle">
          <rect x="60" y="93" width="64" height="22" rx="11" fill="#ffffff" stroke="#ebedf0"/><text x="92" y="108" fill="#16a34a">审批通过</text>
          <rect x="288" y="117" width="64" height="22" rx="11" fill="#ffffff" stroke="#ebedf0"/><text x="320" y="132" fill="#e23b3b">审批驳回</text>
          <rect x="472" y="93" width="64" height="22" rx="11" fill="#ffffff" stroke="#ebedf0"/><text x="504" y="108" fill="#6b7280">撤回</text>
          <rect x="424" y="117" width="64" height="22" rx="11" fill="#ffffff" stroke="#ebedf0"/><text x="456" y="132" fill="#16a34a">重新提交</text>
          <rect x="438" y="183" width="64" height="22" rx="11" fill="#ffffff" stroke="#ebedf0"/><text x="470" y="198" fill="#6b7280">修改后重提</text>
          <rect x="376" y="268" width="64" height="22" rx="11" fill="#ffffff" stroke="#ebedf0"/><text x="408" y="283" fill="#b45309">放弃归档</text>
        </g>
      </svg>
      <figcaption>图 3 · 审批结果与驳回处理</figcaption>
    </figure>
    <div class="mn-step" data-step="1">
      <h6>提交人：撤回</h6>
      <p>审批还没结束前，提交人可撤回合同回草稿，改完再提。</p>
    </div>
    <div class="mn-step" data-step="2">
      <h6>被驳回后</h6>
      <ul>
        <li><strong>修改后重提</strong>：驳回 → 草稿，改完再提交；</li>
        <li><strong>直接重新提交</strong>：不改内容，直接再走审批；</li>
        <li><strong>放弃归档</strong>：不再推进，直接把合同归档。</li>
      </ul>
    </div>
  </section>

  <!-- ================================================================ -->
  <!-- 客户与供应商 -->
  <!-- ================================================================ -->

  <section class="mn-card" id="mn-customer">
    <h5><i class="bi bi-people"></i>客户管理</h5>
    <p>入口：「客户管理」分组下含客户列表 / 新增客户 / 公海池。客户是合同签约的对方主体，先建档才能签合同。</p>
    <div class="mn-sub">
      <h6>客户列表与生命周期漏斗</h6>
      <p>列表顶部有<strong>生命周期漏斗</strong>：潜在 / 活跃 / 流失三档客户数与对应销售合同额，点击可联动筛选；列表支持关键词、状态、生命周期筛选，展示「共享」徽标、行业、归属人。管理员有「查重」按钮，可扫描重复客户并一键合并。</p>
    </div>
    <div class="mn-sub">
      <h6>新增 / 编辑客户</h6>
      <p>字段：名称、统一社会信用代码、法人、联系人、手机、邮箱、地址、来源、信用评分（0-100 可手改）、生命周期、行业等。新建时自动查重，信用代码 18 位校验。</p>
    </div>
    <div class="mn-sub">
      <h6>客户详情（360° 聚合，8 个页签）</h6>
      <ul>
        <li><strong>基本信息</strong>：名称、风险标记、生命周期、行业、信用代码、信用评分、联系方式、归属人、状态；</li>
        <li><strong>关联合同</strong>：这家客户的合同列表；</li>
        <li><strong>回款记录</strong>：计划日 / 金额 / 状态（待收 / 已收 / 逾期）；</li>
        <li><strong>跟进</strong>：时间轴记录电话 / 拜访 / 会议 / 微信跟进，可设下次跟进时间；</li>
        <li><strong>统计</strong>：往来总额 / 已收 / 待收余额 / 逾期金额汇总；</li>
        <li><strong>联系人</strong>：多联系人矩阵（角色、主联系人）；</li>
        <li><strong>共享、集团</strong>：协作页签（见下）。</li>
      </ul>
    </div>
    <div class="mn-sub">
      <h6>共享机制</h6>
      <p>客户负责人或管理员可把客户<strong>共享给指定用户或部门</strong>，也可撤销。共享成员只读，负责人 / 管理员可管理。</p>
    </div>
    <div class="mn-sub">
      <h6>公海池</h6>
      <p>无归属人的客户进入<strong>公海池</strong>：可「认领」（每人每日上限 20 个）；归属人可把自己的客户「释放到公海」（释放前校验有效合同）或「转移」给他人；管理员可直接分配。认领后长期无跟进会自动回落公海（天数在系统配置里可调，默认 30 天）。</p>
    </div>
    <div class="mn-sub">
      <h6>集团层级</h6>
      <p>可把客户设为另一客户的<strong>子级</strong>形成集团树，展示集团树与汇总（有防环校验），便于集团化客户统一管理。</p>
    </div>
    <div class="mn-callout mn-ok">
      <div class="t"><i class="bi bi-stars"></i>生命周期自动变化</div>
      <div>客户签了合同会自动标记为「成交 / 活跃」；长期无合同可能标记为「流失」——生命周期与合同、跟进情况联动。</div>
    </div>
  </section>

  <section class="mn-card" id="mn-supplier">
    <h5><i class="bi bi-truck"></i>供应商管理</h5>
    <p>入口：「客户管理 → 供应商」。供应商是采购合同的对方主体，与客户同理先建档再关联合同。</p>
    <ul>
      <li><strong>列表</strong>：关键词 + 类型筛选（类型为字典项，如媒体等）、分页排序；</li>
      <li><strong>新增 / 编辑</strong>：名称、类型、联系人、手机、邮箱、地址、状态，手机 / 邮箱格式校验；</li>
      <li><strong>详情</strong>：基本信息展示；</li>
      <li><strong>删除</strong>：软删除；存在关联采购合同时拒绝删除并提示。</li>
    </ul>
    <div class="mn-callout mn-note">
      <div class="t"><i class="bi bi-lightbulb"></i>和合同的关系</div>
      <div>新建合同选乙方时，供应商按数据范围过滤后出现在选择器里；我方为乙方的采购合同，对方（甲方）可以是供应商。</div>
    </div>
  </section>

  <section class="mn-card" id="mn-party">
    <h5><i class="bi bi-arrow-left-right"></i>往来档案（360°）</h5>
    <p>入口：「客户管理 → 往来档案」。按客户 / 供应商查看其<strong>全景往来视图</strong>：</p>
    <ul>
      <li>该主体下的<strong>全部合同</strong>（销售 / 采购）；</li>
      <li><strong>回款与发票</strong>汇总（往来总额、已收、待收、逾期）；</li>
      <li>从合同详情页也能一键跳到对应往来档案。</li>
    </ul>
    <p>用途：谈合同、核对账目、评估客户质量时，一眼看清和这家主体的全部业务往来。</p>
  </section>

  <!-- ================================================================ -->
  <!-- 项目与资料 -->
  <!-- ================================================================ -->

  <section class="mn-card" id="mn-project">
    <h5><i class="bi bi-kanban"></i>项目管理</h5>
    <p>入口：「项目管理」分组。项目用于把一个客户的多次合同 / 业务组织到一起，看整体经营情况。</p>
    <div class="mn-sub">
      <h6>新建 / 编辑项目</h6>
      <p>项目名称、编号、关联客户、状态（进行中 / 已完成 / 已归档 / 已终止）、执行阶段（筹备 / 执行中 / 验收中 / 已完结）、进度 0-100%、预算、起止日期、备注。</p>
    </div>
    <div class="mn-sub">
      <h6>项目详情</h6>
      <ul>
        <li><strong>经营聚合卡</strong>：交易合同数、销售合同额、毛利与毛利率、应收 / 已收、回款率；</li>
        <li><strong>基本信息</strong>：编号、预算、起止日期、执行进度条、采购合同额；</li>
        <li><strong>关联合同</strong>：项目下所有合同列表。</li>
      </ul>
    </div>
    <div class="mn-sub">
      <h6>一键验收 / 终止</h6>
      <p><strong>标记验收完成</strong>：把项目下执行中 / 已通过的销售合同全部置为已完成，并提示待收尾款；<strong>终止项目</strong>：仅进行中可终止，联动终止销售合同（有逾期回款的自动跳过并列出），支持撤销终止。</p>
    </div>
  </section>

  <section class="mn-card" id="mn-resource">
    <h5><i class="bi bi-folder2-open"></i>资料库</h5>
    <p>入口：「资料库」。公司通用资料的存放地，按分类管理。</p>
    <ul>
      <li><strong>分类</strong>：合同范本 / 开票资料 / 标准条款 / 其他，顶部按分类筛选 + 关键词 + 关联主体筛选；</li>
      <li><strong>上传</strong>：标题、分类、关联主体、说明，单个文件 ≤ 20MB（真实类型白名单校验）；「开票资料」类支持结构化录入（单位名称 / 税号 / 开户行 / 账号等），可不传文件纯字段录入；</li>
      <li><strong>详情</strong>：展示内容、文件、结构化字段，支持编辑、下载、删除；</li>
      <li><strong>权限</strong>：查看 / 上传 / 编辑 / 删除 各自独立授权。</li>
    </ul>
  </section>

  <!-- ================================================================ -->
  <!-- 财务与发票 -->
  <!-- ================================================================ -->

  <section class="mn-card" id="mn-payment">
    <h5><i class="bi bi-cash-coin"></i>回款管理</h5>
    <p>入口：「财务中心 → 回款管理」。把应收款项登记到合同下，自动汇总成台账。</p>
    <ul>
      <li><strong>登记回款计划</strong>：金额、计划日、类型（应收 / 应付）、收款方式、里程碑、说明；仅交易合同且已通过 / 执行中可登记，累计不超过合同金额；</li>
      <li><strong>复制上期计划</strong>：快速延续上期；<strong>批量生成多期</strong>：预付 / 中期 / 尾款一次拆分（单次最多 10 期）；</li>
      <li><strong>确认收款</strong>：可部分确认（剩余自动拆成新的待收记录），填收款方式 / 实际日期 / 发票号；</li>
      <li><strong>撤销收款</strong>（仅已收可撤，回退为待确认）、<strong>标记逾期</strong>、删除记录（已收须先撤销）。</li>
    </ul>
    <div class="mn-callout mn-alert">
      <div class="t"><i class="bi bi-exclamation-triangle"></i>口径说明</div>
      <div>只有<strong>交易类合同</strong>才计入回款与财务统计；草稿 / 驳回 / 审批中 / 框架合同不计入。</div>
    </div>
  </section>

  <section class="mn-card" id="mn-payout">
    <h5><i class="bi bi-cash-stack"></i>付款管理</h5>
    <p>入口：「财务中心 → 付款管理」。和回款同一套面板，只显示「应付」类型的计划：登记应付计划、确认付款、撤销、标记逾期，用于管理我方要付出去的钱。</p>
  </section>

  <section class="mn-card" id="mn-invoice-apply">
    <h5><i class="bi bi-receipt-cutoff"></i>发票申请</h5>
    <p>入口：「发票申请」独立菜单，或从合同详情点「申请开票」。发票走<strong>三段式</strong>：<strong>申请 → 审批 → 财务开票</strong>。</p>
    <div class="mn-step" data-step="1">
      <h6>提交申请</h6>
      <p>选关联合同（自动校验合同状态与开票金额上限）或独立申请；选客户自动带出抬头 / 税号，选开票主体自动带出税率。表单字段由管理员在「发票表单」配置。</p>
    </div>
    <div class="mn-step" data-step="2">
      <h6>审批</h6>
      <p>提交后自动生成审批实例，走审批流（支持按开票公司 / 金额分流、抄送）。</p>
    </div>
    <div class="mn-step" data-step="3">
      <h6>财务开票</h6>
      <p>审批通过后进入「待开票」，财务填写发票号、开票日期完成开票。被驳回或撤回可修改重提。</p>
    </div>
  </section>

  <section class="mn-card" id="mn-invoice">
    <h5><i class="bi bi-receipt"></i>发票管理</h5>
    <p>入口：「财务中心 → 发票管理」（财务角色）。管理已申请发票的后续动作：</p>
    <ul>
      <li><strong>开票</strong>：审批通过的「待开票」状态可开票，填发票号 / 开票日期；</li>
      <li><strong>红冲</strong>：已开票可红冲，自动生成负数红字发票并关联原发票；</li>
      <li><strong>作废</strong>：已开票可作废；</li>
      <li><strong>删除申请</strong>：仅已驳回 / 已撤回 / 历史申请可删；</li>
      <li>支持按发票号 / 合同跨合同搜索。</li>
    </ul>
  </section>

  <section class="mn-card" id="mn-aging">
    <h5><i class="bi bi-hourglass-split"></i>应收账龄</h5>
    <p>入口：「财务中心 → 应收账龄」。把逾期应收按账龄分档展示：<strong>0-30 天 / 31-60 天 / 61-90 天 / 90 天以上</strong>，列出合同、金额、计划日，按数据范围收敛——催款、评估坏账风险用。</p>
  </section>

  <section class="mn-card" id="mn-report">
    <h5><i class="bi bi-bar-chart-line"></i>经营报表</h5>
    <div class="mn-sub">
      <h6>经营月报</h6>
      <p>选月份生成：本月计划应收、实际回款、逾期金额、回款率、应收未收余额，与上月环比；当月新增合同（销售 / 采购）笔数与金额。支持导出 Excel / CSV。</p>
    </div>
    <div class="mn-sub">
      <h6>经营周报（仅总经理 / 超管）</h6>
      <p>按周汇总：全公司上周新增合同、上周回款、当前逾期；各部门卡片（新增合同、回款、逾期、待审批）；上周新增合同明细与逾期清单——周一例会参考。</p>
    </div>
  </section>

  <!-- ================================================================ -->
  <!-- 工作台与提醒 -->
  <!-- ================================================================ -->

  <section class="mn-card" id="mn-dashboard">
    <h5><i class="bi bi-speedometer2"></i>仪表盘（首页）</h5>
    <p>登录后第一屏，一页看经营。内容块自上而下：</p>
    <ul>
      <li><strong>快捷操作</strong>：新建合同、新建客户、审批、登记回款、申请开票（按权限显示）；</li>
      <li><strong>今日提醒 + 近期回款</strong>：未来 30 天回款（已收 / 待收 / 逾期），点击直达合同；</li>
      <li><strong>草稿待处理</strong>：数据范围内最新草稿合同；</li>
      <li><strong>合同状态分布</strong>：草稿 / 审批中 / 已通过 / 执行中等各状态计数；</li>
      <li><strong>KPI 区</strong>：按角色显示（管理员 = 生效合同总额 / 待回款 / 回款率 / 今日待办；经理 = 待我审批 / 合同总额 / 待回款 / 回款率；财务 = 待回款 / 已收 / 回款率 / 合同总额；员工 = 我的合同 / 我的待回款 / 我的回款率 / 今日提醒）；</li>
      <li><strong>本月经营</strong>、<strong>近 6 季度趋势</strong>（合同金额 vs 已收回款）、<strong>按部门经营表</strong>（管理层）、<strong>项目 TOP 5</strong>、<strong>最近合同</strong>；</li>
      <li><strong>周期筛选</strong>：本月 / 本季 / 本年 / 累计，切换时局部刷新。</li>
    </ul>
  </section>

  <section class="mn-card" id="mn-remind">
    <h5><i class="bi bi-bell"></i>提醒中心</h5>
    <p>入口：「提醒」菜单（顶部铃铛红点 = 提醒 + 未读消息数）。统一待办中心三个页签：</p>
    <div class="mn-tbl-wrap">
    <table class="mn-tbl">
      <thead><tr><th style="width:20%">页签</th><th>内容</th></tr></thead>
      <tbody>
        <tr><td><strong>待办</strong></td><td>待我审批的流程动作，点击直达审批详情</td></tr>
        <tr><td><strong>提醒</strong></td><td>自动扫描 4 类：合同 N 天后到期（提前天数可配）、合同已到期、回款逾期、回款 N 天后到期；合同类提醒带「续约」快捷按钮</td></tr>
        <tr><td><strong>审批消息</strong></td><td>站内信（驳回 / 通过 / 转交等），支持全部标为已读</td></tr>
      </tbody>
    </table>
    </div>
    <div class="mn-callout mn-note">
      <div class="t"><i class="bi bi-info-circle"></i>提醒怎么来的</div>
      <div>系统每日自动扫描生成（管理员可手动「检查提醒」并「推送到钉钉」工作通知）；提前天数在「系统配置 → 业务规则」里可调。</div>
    </div>
  </section>

  <!-- ================================================================ -->
  <!-- 手机端 -->
  <!-- ================================================================ -->

  <section class="mn-card" id="mn-mobile">
    <h5><i class="bi bi-phone"></i>手机端功能</h5>
    <p>在<strong>钉钉</strong>里打开系统（免登录），功能和电脑端同源，适合外出时用：</p>
    <ul>
      <li><strong>底部导航</strong>：首页 / 合同 / 客户 / 审批 / 归档 / 菜单；</li>
      <li><strong>合同</strong>：列表、详情、新建 / 编辑（表单分块：对方信息 / 更多信息 / 金额与期限 / 概要 / 附件）；</li>
      <li><strong>审批</strong>：发起审批、处理待办、填审批意见；</li>
      <li><strong>更多页</strong>：待办中心、我的业绩、使用手册、财务概览、报表、项目、资料库、往来档案等聚合入口；</li>
      <li><strong>我的业绩</strong>：个人合同 / 回款 / 客户统计（纵向环比）。</li>
    </ul>
    <div class="mn-callout mn-note">
      <div class="t"><i class="bi bi-lightbulb"></i>小提示</div>
      <div>复杂的新建 / 编辑建议回电脑上做；手机上适合查看和快速处理。</div>
    </div>
  </section>

  <!-- ================================================================ -->
  <!-- 管理员功能 -->
  <!-- ================================================================ -->

  <section class="mn-card" id="mn-admin-user">
    <h5><i class="bi bi-person-gear"></i>用户管理</h5>
    <p>入口：「系统设置 → 用户管理」。管理组织与账号：</p>
    <ul>
      <li>左侧<strong>部门树</strong> + 右侧成员列表；新增 / 编辑用户（用户名、姓名、手机、邮箱、部门、状态、分配角色）；</li>
      <li><strong>禁用用户</strong>：进入用户回收站可恢复；禁用前校验进行中审批（有则先办交接）；</li>
      <li><strong>待交接队列</strong>：钉钉同步自动识别疑似离职员工，办理「离职交接」——名下客户 / 合同 / 待审批批量转移给接收人，可同时禁用；</li>
      <li><strong>强制改密</strong>（首次登录必须改密码）。</li>
    </ul>
  </section>

  <section class="mn-card" id="mn-admin-role">
    <h5><i class="bi bi-shield-check"></i>角色权限</h5>
    <p>入口：「系统设置 → 角色权限」。控制「谁能用什么」：</p>
    <ul>
      <li>角色新增 / 编辑 / 删除（系统内置角色不可删）；</li>
      <li>权限勾选：隐藏「全员默认基础权限」，其余高级权限（财务、审批、客户、发票、系统管理、审计、离职交接等）可勾；</li>
      <li><strong>数据范围</strong>：本人 / 本部门 / 全部 / 自定义部门——仅管理员可设「全部」和「自定义部门」。</li>
    </ul>
  </section>

  <section class="mn-card" id="mn-admin-flow">
    <h5><i class="bi bi-diagram-2"></i>审批流程</h5>
    <p>入口：「系统设置 → 审批流程」。统一管理<strong>合同流程</strong>与<strong>发票流程</strong>：</p>
    <ul>
      <li><strong>画布式编辑器</strong>：发起人 → 审批节点 → 抄送 → 结束，支持分支并列、拖动排序；</li>
      <li><strong>流程匹配</strong>：适用合同分类（多选）+ 金额区间；</li>
      <li><strong>节点设置</strong>：审批人类型（角色 / 指定用户 / 部门负责人）、OR / AND 会签、节点级金额条件（低于 / 高于跳过节点）、流程抄送；</li>
      <li><strong>流程回收站</strong>：停用恢复、彻底删除（有审批实例或模板引用时阻止）。</li>
    </ul>
  </section>

  <section class="mn-card" id="mn-admin-dict">
    <h5><i class="bi bi-book"></i>字典设置</h5>
    <p>入口：「系统设置 → 字典设置」。管理下拉选项（合同状态、支付方式、供应商类型、生命周期等）：新增 / 编辑选项、启停、拖拽排序；停用项会从新建 / 编辑的下拉中隐藏。</p>
  </section>

  <section class="mn-card" id="mn-admin-dd">
    <h5><i class="bi bi-chat-dots"></i>钉钉设置</h5>
    <p>入口：「系统设置 → 钉钉设置」。对接钉钉：</p>
    <ul>
      <li>应用配置：AppKey / AppSecret / CorpId / AgentId；Mock（本地模拟）与生产模式切换（生产必须关闭 Mock）；</li>
      <li><strong>同步钉钉</strong>：一键同步组织架构（同步后自动标记疑似离职员工进待交接队列）；同步日志面板；支持钉钉 SSO 免登。</li>
    </ul>
  </section>

  <section class="mn-card" id="mn-admin-config">
    <h5><i class="bi bi-sliders"></i>系统配置</h5>
    <p>入口：「系统设置 → 系统配置」。基础设置与业务规则：</p>
    <ul>
      <li><strong>基础</strong>：版权信息、操作引导开关；</li>
      <li><strong>业务规则</strong>：公海自动释放天数（默认 30）、合同到期提醒提前天数（默认 30/15/7/3/1）、回款提醒提前天数（默认 7/3/1）、周报钉钉推送开关；</li>
      <li><strong>配置备份 / 恢复</strong>：导出 JSON 快照，恢复前预览风险、事务回滚、防自锁校验。</li>
    </ul>
  </section>

  <section class="mn-card" id="mn-admin-company">
    <h5><i class="bi bi-buildings"></i>本公司主体</h5>
    <p>入口：「系统设置 → 本公司主体」。管理我方签约主体：公司全称 / 简称 / 统一社会信用代码 / <strong>开票税率</strong>（0 = 免税，默认 6%）/ 是否默认主体；合同与发票申请时选择签约 / 开票主体。</p>
  </section>

  <section class="mn-card" id="mn-admin-invoice-form">
    <h5><i class="bi bi-ui-checks"></i>发票表单</h5>
    <p>入口：「系统设置 → 发票表单」及通用表单设计器。配置发票申请表单：字段启停 / 排序 / 标签 / 必填、新增自定义字段、字段联动规则（如选客户带出抬头税号）；Step2 配置审批与抄送——不同开票公司走不同审批人 / 抄送分支 + 金额条件。前端申请表单随之变化。</p>
  </section>

  <section class="mn-card" id="mn-admin-recycle">
    <h5><i class="bi bi-trash3"></i>数据回收站</h5>
    <p>入口：「系统设置 → 数据回收站」（仅管理员）。合同 / 客户 / 供应商软删除记录的分类型列表：支持<strong>恢复</strong>与<strong>彻底删除</strong>（有阻塞关联时阻止并提示），均写审计。</p>
  </section>

  <section class="mn-card" id="mn-admin-audit">
    <h5><i class="bi bi-clipboard-check"></i>审计中心</h5>
    <p>入口：「系统设置 → 审计中心」。全系统操作日志检索：按操作人、操作类型（增删改 / 导出 / 交接 / 回收站操作等）、目标类型、日期范围筛选，分页查看——谁在什么时候做了什么，全程可追溯。</p>
  </section>

  <!-- ================================================================ -->
  <!-- 常见问题 -->
  <!-- ================================================================ -->

  <section class="mn-card" id="mn-faq">
    <h5><i class="bi bi-question-circle"></i>常见问题</h5>
    <div class="mn-step" data-step="?"><h6>保存时提示「检测到重复合同」？</h6><p>说明有一份标题、对方、金额完全一样的合同已存在。确认是新合同就改一下标题或金额再保存；否则去处理原合同，避免重复。</p></div>
    <div class="mn-step" data-step="?"><h6>合同怎么改不了？</h6><p>只有草稿和已驳回的合同能编辑。已生效的合同要调整，用「续约」生成新草案重新走审批，历史不能涂改。</p></div>
    <div class="mn-step" data-step="?"><h6>归档 / 终结时提示有逾期回款？</h6><p>系统不允许带着逾期账款收尾。先去回款管理处理逾期款项，再回来操作。</p></div>
    <div class="mn-step" data-step="?"><h6>附件上传失败？</h6><p>常见原因：超过 20MB、文件真实类型不在白名单（改后缀名无效）、或附件不是你自己上传的（来源校验）。</p></div>
    <div class="mn-step" data-step="?"><h6>为什么看不到别人的合同 / 客户？</h6><p>系统按数据范围控制可见性（本人 / 本部门 / 全部）。看不到说明不在你的数据范围内；客户可联系负责人「共享」给你。</p></div>
    <div class="mn-step" data-step="?"><h6>删了的合同还能找回吗？</h6><p>删除是软删除，数据进「数据回收站」，由管理员恢复。</p></div>
    <div class="mn-step" data-step="?"><h6>为什么有的菜单我看不到？</h6><p>菜单按角色权限自动显示，看不到 = 没有对应权限，可联系管理员在「角色权限」中配置。</p></div>
  </section>

</div>

<!-- 返回顶部 -->
<button type="button" class="mn-top" id="mnTop" aria-label="返回顶部"><i class="bi bi-arrow-up"></i></button>

<script>
(function(){
  // 目录展开 / 收起
  var toggle = document.getElementById('mnDirToggle');
  var body = document.getElementById('mnDirBody');
  if (toggle && body) {
    toggle.addEventListener('click', function () {
      var hidden = body.style.display === 'none';
      body.style.display = hidden ? '' : 'none';
      toggle.textContent = hidden ? '收起目录' : '展开目录';
    });
  }

  // 滚动高亮当前目录项 + 返回顶部
  var links = Array.prototype.slice.call(document.querySelectorAll('.mn-dir-group a[href^="#"]'));
  var sections = links.map(function (a) { return document.querySelector(a.getAttribute('href')); });
  var topBtn = document.getElementById('mnTop');
  var ticking = false;

  function onScroll() {
    if (ticking) return;
    ticking = true;
    requestAnimationFrame(function () {
      try {
        var cur = null;
        for (var i = 0; i < sections.length; i++) {
          if (sections[i] && sections[i].getBoundingClientRect().top <= 140) cur = sections[i];
        }
        var id = cur ? cur.id : (sections[0] ? sections[0].id : '');
        links.forEach(function (l) {
          l.classList.toggle('active', l.getAttribute('href') === '#' + id);
        });
        if (topBtn) topBtn.classList.toggle('show', (window.scrollY || document.documentElement.scrollTop) > 400);
      } finally {
        ticking = false;
      }
    });
  }
  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll();

  if (topBtn) {
    topBtn.addEventListener('click', function () { window.scrollTo({ top: 0, behavior: 'smooth' }); });
  }
})();
</script>

<?php include __DIR__.'/../layout/footer.php'; ?>
