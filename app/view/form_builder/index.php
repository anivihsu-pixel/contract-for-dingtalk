<?php $title='发票流程'; $menu_active='admin'; include __DIR__.'/../layout/header.php'; ?>
<!-- G1/G2：发票流程配置（v2.38.25：取消 Step1 表单设计环节——申请表单固定不变，仅「开票内容选项」在配置侧边栏可编辑；审批与抄送画布 + 右侧流程配置侧边栏） -->
<style>
/* 三栏布局（v2.38.22）：控件库固定宽贴左、属性面板固定宽贴右、画布预览弹性居中；
   画布与两侧间距随视口缩放（clamp 12~36px），窄屏收窄间距、画布随之收缩 */
.fb-layout{display:flex;align-items:flex-start;gap:clamp(12px,1.6vw,36px);width:100%;min-width:1120px}  /* 最小总宽：不足时由外层横向滚动，画布不塌 */
.fb-layout > .fb-panel:first-child{flex:0 0 210px}   /* 控件库：固定宽，左对齐 */
.fb-layout > .fb-panel:last-child{flex:0 0 280px}    /* 属性面板：固定宽，右对齐 */
.fb-canvas-col{flex:1 1 auto;min-width:0;display:flex;justify-content:center}  /* 中间弹性区：画布水平居中 */
.fb-canvas-col .fb-canvas-wrap{width:100%;max-width:860px}  /* 画布 860 封顶，随视口缩放收缩 */
.fb-panel{background:#fff;border:1px solid var(--line);border-radius:12px;padding:12px}
.fb-panel h6{font-size:13px;margin:0 0 10px;color:var(--text-2)}
.fb-pool-item{display:flex;align-items:center;justify-content:space-between;padding:8px 10px;border:1px solid var(--line);border-radius:8px;margin-bottom:6px;font-size:13px;cursor:pointer}
.fb-pool-item:hover{border-color:var(--primary);background:#f5f9ff}
.fb-pool-item.added{opacity:.55;cursor:default}
.fb-pool-item .tag{font-size:11px;color:var(--text-muted);background:#f3f4f6;padding:1px 6px;border-radius:10px}
.fb-canvas-item{display:flex;align-items:flex-start;gap:10px;padding:10px;border:1px solid var(--line);border-radius:10px;margin-bottom:8px;background:#fff;position:relative}
.fb-canvas-item.sel{border-color:var(--primary);box-shadow:0 0 0 2px rgba(11,94,215,.15)}
.fb-canvas-item .ctrl{flex:1;min-width:0}
.fb-canvas-item .ops{display:flex;flex-direction:column;gap:4px}
.fb-canvas-item .ops button{width:26px;height:26px;padding:0;line-height:1;font-size:13px}
.fb-preview-label{font-size:13px;margin-bottom:4px;color:var(--text-2)}
.fb-preview-label b{color:var(--danger)}
.fb-attr label{font-size:12px;color:var(--text-muted);margin-bottom:2px}
.fb-attr .form-control,.fb-attr .form-select{font-size:13px}
.fb-empty{color:#9ca3af;text-align:center;padding:32px 0;font-size:13px}
/* v2.38.25：取消 Step1，页面直接展示审批与抄送（无步骤切换，无需隐藏规则） */
/* ===== v2.38.19 Step2 钉钉式单一画布（发起人 → 分支区横向并列 → 结束）===== */
.fb-flow{display:flex;flex-direction:column;align-items:center}
.fb-flow-starter{display:flex;align-items:center;gap:8px;background:#eef4ff;border:1px solid #c9dcf7;color:#0b5ed7;border-radius:20px;padding:7px 18px;font-size:13px;font-weight:600}
.fb-flow-conn{width:2px;height:22px;background:#c9dcf7;position:relative}
.fb-flow-conn::after{content:'';position:absolute;left:50%;bottom:-1px;transform:translateX(-50%);border:5px solid transparent;border-top-color:#c9dcf7}
/* v2.38.25：去除发起人与分支区之间的连接线视觉（多分支并列时竖线不汇聚、视觉错位）；
   保留原高度作为发起人与分支区之间的间隔，避免上下贴得太近 */
.fb-flow-conn{height:22px;width:100%;background:transparent}
.fb-flow-conn::after{display:none}
.fb-branch::before{display:none}
.fb-flow-gap{width:100%;height:14px;position:relative}
.fb-flow-gap::before{content:'';position:absolute;left:50%;top:0;bottom:0;width:2px;background:#c9dcf7}
.fb-flow-end{display:flex;align-items:center;gap:8px;background:#f6f8fa;border:1px dashed var(--line);color:#6b7280;border-radius:20px;padding:6px 16px;font-size:12px}
/* 分支区：横向并列卡片（默认流程 + 条件分支），一眼看到全部流程路径 */
.fb-branch-zone{display:flex;flex-wrap:wrap;gap:14px;justify-content:center;width:100%;max-width:1320px}
.fb-branch{width:380px;max-width:100%;background:#fff;border:1px solid var(--line);border-radius:12px;padding:12px;position:relative;box-shadow:0 1px 2px rgba(0,0,0,.04);display:flex;flex-direction:column}
.fb-branch::before{content:'';position:absolute;left:50%;top:-14px;width:2px;height:14px;background:#c9dcf7}
.fb-branch-head{display:flex;justify-content:space-between;align-items:center;gap:6px;margin-bottom:10px;flex-wrap:wrap}
.fb-branch-badge{display:inline-flex;align-items:center;gap:5px;border-radius:12px;padding:3px 10px;font-size:12px;font-weight:600}
.fb-branch-badge.def{background:#eef4ff;border:1px solid #c9dcf7;color:#0b5ed7}
.fb-branch-badge.cond{background:#fff4e5;border:1px solid #f5d9a8;color:#d97706}
.fb-branch-nodes{flex:1;min-width:0}
.fb-branch-nodes .fb-bnode{width:100%}
/* 分支内节点行（紧凑：名称/类型/模式 + 审批人 + 排序/删除） */
.fb-bnode{border:1px solid var(--line);border-radius:10px;padding:8px 10px;margin-bottom:8px;background:#fafbfc;position:relative}
.fb-bnode-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:6px}
.fb-bnode-head .idx{display:inline-flex;align-items:center;gap:6px;font-weight:600;font-size:13px;color:#1f2329}
.fb-bnode-head .idx .ico{width:20px;height:20px;border-radius:50%;background:var(--primary);color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:11px}
.fb-bnode .node-ops{display:flex;gap:3px}
.fb-bnode .node-ops button{width:24px;height:24px;padding:0;line-height:1;font-size:12px}
.fb-bnode-body .form-select,.fb-bnode-body .form-control{font-size:12.5px}
.fb-branch-cc{background:#fffdf3;border:1px solid #f0e3b8;border-radius:10px;padding:8px 10px}
.fb-branch-cc .cc-title{font-size:12px;color:#8a6d1a;font-weight:600;margin-bottom:6px}
.fb-chip{display:inline-flex;align-items:center;gap:4px;background:var(--brand-light);color:var(--primary);border-radius:12px;padding:2px 8px;font-size:12px;margin:2px}
.fb-chip b{cursor:pointer;opacity:.6}
/* 编辑器为桌面设计工具，保持三栏布局不随侧边栏折叠变化；不足时横向滚动 */
.fb-layout-scroll{overflow-x:auto;padding-bottom:4px}
/* 窄屏（侧边栏折叠/小窗口）：间距自动收窄，控件库/属性面板保持固定宽，画布收缩 */
@media (max-width:1200px){.fb-layout{gap:clamp(8px,1.2vw,16px)}}
/* ===== v2.38.20 控件调色板（卡片式，参照钉钉设计器）===== */
.fb-ctrl-card{display:flex;align-items:center;gap:8px;padding:8px 10px;border:1px solid var(--line);border-radius:8px;margin-bottom:6px;font-size:13px;cursor:pointer;background:#fff;transition:all .15s}
.fb-ctrl-card:hover{border-color:var(--primary);background:#f5f9ff;box-shadow:0 1px 3px rgba(11,94,215,.1)}
.fb-ctrl-card.added{opacity:.5;cursor:default;background:#f9fafb}
.fb-ctrl-card .cio{width:28px;height:28px;border-radius:7px;display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0}
.fb-ctrl-card .cio.input{background:#eef4ff;color:#0b5ed7}
.fb-ctrl-card .cio.option{background:#f3f0ff;color:#6f42c1}
.fb-ctrl-card .cio.date{background:#fef3e7;color:#d97706}
.fb-ctrl-card .cio.layout{background:#ecfdf5;color:#10b981}
.fb-ctrl-card .cio.biz{background:#fdf2f8;color:#db2777}
.fb-ctrl-card .cin{font-size:12px;color:var(--text-muted)}
.fb-ctrl-cat{font-size:11px;color:var(--text-muted);font-weight:600;margin:8px 0 4px;display:flex;align-items:center;gap:4px}
.fb-ctrl-cat:first-child{margin-top:0}
/* 画布控件卡片类型图标标记 */
.fb-ci-badge{display:inline-flex;align-items:center;gap:4px;font-size:11px;color:var(--text-muted);background:#f3f4f6;border-radius:10px;padding:2px 8px;margin-bottom:6px}
.fb-ci-badge i{font-size:12px}
/* v2.38.21 选项纵向排列（单选框/多选框默认竖排，勾选平铺开关后变为横向 pills） */
.fb-opt-tiles{display:flex;flex-direction:column;gap:4px}
.fb-opt-tiles.tile{flex-direction:row;flex-wrap:wrap}
.fb-opt-tile{display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border:1px solid var(--line);border-radius:14px;font-size:12px;cursor:default;background:#fafbfc}
.fb-opt-tiles.tile .fb-opt-tile{flex:0 1 auto}
.fb-opt-tile i{font-size:13px;color:var(--primary)}
/* 说明文字 */
.fb-desc{font-size:12px;color:var(--text-muted);line-height:1.6;padding:2px 0}
/* 日期区间 */
.fb-date-range{display:flex;align-items:center;gap:6px}
.fb-date-range .form-control{width:auto;max-width:140px}
.fb-date-range .sep{font-size:12px;color:var(--text-muted)}
/* 画布控件选中态灰色背景 */
.fb-canvas-item.sel{background:#f8faff}
/* 属性面板选项编辑区增大 */
#faOptWrap textarea{min-height:80px}
/* 画布预览区收窄并增加下方留白，避免页面拥挤 */
.fb-canvas-wrap{width:100%;padding-bottom:40px;position:relative;max-height:calc(100vh - 180px);overflow-y:auto;scrollbar-width:thin;display:flex;flex-direction:column}
.fb-canvas-wrap::-webkit-scrollbar{width:6px}
.fb-canvas-wrap::-webkit-scrollbar-thumb{background:#d1d5db;border-radius:3px}
/* 画布上下滚动控制（字段多时快速定位） */
.fb-canvas-scroll{position:sticky;bottom:20px;align-self:flex-end;display:flex;flex-direction:column;gap:6px;z-index:10;margin-right:6px}
.fb-canvas-scroll button{width:32px;height:32px;border:1px solid var(--line);border-radius:50%;background:#fff;color:var(--text-2);display:flex;align-items:center;justify-content:center;font-size:14px;box-shadow:0 2px 6px rgba(0,0,0,.08);cursor:pointer;transition:all .15s}
.fb-canvas-scroll button:hover{border-color:var(--primary);color:var(--primary);background:#f5f9ff}
/* 选项布局切换开关 */
.fb-opt-layout-toggle{display:flex;align-items:center;gap:6px;margin-bottom:4px}
.fb-opt-layout-toggle label{font-size:12px;color:var(--text-muted);margin-bottom:0}
.fb-opt-layout-toggle .form-check-input{width:14px;height:14px;margin-top:0}
/* v2.38.22：流程级金额条件区（已移入右侧流程配置侧边栏；此处样式保留兼容旧画布残留） */
.fb-branch-amt{background:#f8fafc;border:1px solid #e5e9f0;border-radius:8px;padding:8px 10px;margin-bottom:8px}
.fb-branch-amt .form-select,.fb-branch-amt .form-control{font-size:12.5px}
/* v2.38.22：Step2 左右双栏——左侧画布 + 右侧流程配置侧边栏（与合同审批编辑器一致） */
.fb2-layout{display:flex;align-items:stretch;gap:0;min-height:520px;max-height:calc(100vh - 220px);border:1px solid var(--line);border-radius:12px;overflow:hidden}
.fb2-canvas-panel{flex:1 1 auto;min-width:0;background:#f6f8fa;padding:16px;overflow-y:auto;scrollbar-width:thin}
.fb2-canvas-panel::-webkit-scrollbar{width:6px}
.fb2-canvas-panel::-webkit-scrollbar-thumb{background:#d1d5db;border-radius:3px}
.fb2-canvas-panel #fbFlowGroups{min-width:0}
.fb2-canvas-panel .fb-branch-zone{justify-content:flex-start}
.fb2-config-panel{flex:0 0 300px;background:#fff;border-left:1px solid var(--line);padding:14px 16px;overflow-y:auto;scrollbar-width:thin}
.fb2-config-panel::-webkit-scrollbar{width:6px}
.fb2-config-panel::-webkit-scrollbar-thumb{background:#d1d5db;border-radius:3px}
.fb2-config-title{font-size:13px;color:var(--text-2);margin:0 0 14px;padding-bottom:10px;border-bottom:1px solid var(--line)}
.fb2-config-panel .form-label{font-size:12px;color:var(--text-muted);margin-bottom:3px}
.fb2-config-panel .form-control,.fb2-config-panel .form-select{font-size:13px}
/* 分支卡片选中态（点选后在右侧配置面板编辑其流程级设置） */
.fb-branch.sel{border-color:var(--primary);box-shadow:0 0 0 2px rgba(11,94,215,.15)}
.fb-branch{cursor:pointer}
.fb-branch:hover{border-color:#9db9e8}
/* 条件值多选 chips（分类/多选框字段）选中态 */
.fb-cond-multi{display:inline-flex;flex-wrap:wrap;gap:4px}
.fb-cond-multi .fb-opt-tile{cursor:pointer;user-select:none}
.fb-cond-multi .fb-opt-tile.on{background:var(--primary);color:#fff;border-color:var(--primary)}
.fb-cond-multi .fb-opt-tile.on i{color:#fff}
/* v2.38.22：Step1 表格化字段配置（恢复原有发票申请表形态，融入当前样式） */
.fb-field-table td{padding:6px 8px}
.fb-field-table .form-control,.fb-field-table .form-select{font-size:13px;padding:4px 8px}
.fb-field-table .row-btn{width:24px;height:24px;padding:0;line-height:1;font-size:12px}
.fb-field-table .tag-sys{font-size:11px;color:var(--text-muted);background:#f3f4f6;border-radius:10px;padding:2px 8px}
.fb-field-table .tag-custom{font-size:11px;color:#db2777;background:#fdf2f8;border-radius:10px;padding:2px 8px}
</style>

<!-- 审批与抄送（v2.38.19 钉钉式单一画布：发起人 → 分支区横向并列 → 结束；H4 条件分支按表单字段分流；
     v2.38.22 右侧流程配置侧边栏——金额条件等流程级设置移入侧边栏，选中分支后编辑；
     v2.38.25 取消 Step1 表单设计环节，「开票内容选项」作为固定配置项置于侧边栏顶部） -->
<div id="step2">
  <div class="fb2-layout">
    <!-- 左侧：图形化画布（分支并列卡片） -->
    <div class="fb2-canvas-panel">
      <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
        <div>
          <h6 class="mb-0"><i class="bi bi-diagram-3"></i> 审批与抄送</h6>
          <div class="text-muted small">默认流程（无条件）兜底；可添加条件分支——满足条件的申请走对应流程</div>
        </div>
        <button class="btn btn-primary btn-sm" onclick="fbSaveFlow()"><i class="bi bi-save"></i> 保存审批设置</button>
      </div>
      <div id="fbFlowGroups"></div>
    </div>
    <!-- 右侧：流程配置侧边栏（固定配置项「开票内容选项」+ 选中分支的流程级设置） -->
    <div class="fb2-config-panel">
      <h6 class="fb2-config-title"><i class="bi bi-sliders"></i> 流程配置</h6>
      <!-- 固定配置项：开票内容选项（申请时下拉选择的内容，唯一可编辑的表单配置项） -->
      <div class="mb-3 pb-3" style="border-bottom:1px solid var(--line)">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <label class="form-label mb-0"><i class="bi bi-list-ul"></i> 开票内容选项</label>
          <button class="btn btn-sm btn-outline-primary py-0 px-1" onclick="fbContentOptOpen()"><i class="bi bi-gear"></i> 编辑</button>
        </div>
        <div id="fbContentOptView" class="d-flex flex-wrap gap-1"><span class="text-muted small">加载中…</span></div>
      </div>
      <!-- 选中分支的流程级设置（金额条件等） -->
      <div id="fbFlowConfigBody"><span class="text-muted small">加载中…</span></div>
    </div>
  </div>
</div>

<!-- 开票内容选项编辑弹窗（配置侧边栏「编辑」打开；复用 fbOptModal：每行一个选项） -->
<div class="modal fade" id="fbOptModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
<div class="modal-header"><h6 class="modal-title"><i class="bi bi-list-ul"></i> 编辑开票内容选项</h6><button class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
  <div class="text-muted small mb-2">每行一个选项，填写显示名称即可（保存后申请表单「开票内容」下拉即时生效）</div>
  <textarea class="form-control" id="fbOptText" rows="8" placeholder="软件开发服务费&#10;咨询服务费"></textarea>
  <div class="text-danger small mt-1" id="fbOptErr"></div>
</div>
<div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">取消</button><button class="btn btn-primary" onclick="fbOptSave()">保存</button></div>
</div></div></div>

<!-- 通用选人弹窗（审批「指定用户」与抄送指定用户复用，与原审批流 openUserPicker 一致） -->
<div class="modal fade" id="userPickerModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
<div class="modal-header bg-primary text-white"><h5 class="modal-title"><i class="bi bi-people"></i> 选择用户</h5><button class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
<div class="row g-2">
  <div class="col-md-4 border-end" style="max-height:420px;overflow:auto">
    <div class="text-muted small mb-1">部门</div>
    <div id="upDeptTree"></div>
  </div>
  <div class="col-md-8">
    <div class="input-group input-group-sm mb-2">
      <input type="text" class="form-control" id="upKeyword" placeholder="搜索姓名/用户名">
      <button class="btn btn-outline-secondary" id="upSearchBtn" type="button" aria-label="搜索"><i class="bi bi-search"></i></button>
    </div>
    <div id="upUserList" style="max-height:300px;overflow:auto"></div>
    <div class="text-center py-2"><button class="btn btn-sm btn-outline-primary" id="upLoadMore">加载更多</button></div>
    <div class="mt-2 pt-2 border-top"><strong class="small">已选：</strong> <span id="upSelected"></span></div>
  </div>
</div>
</div>
<div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">取消</button><button class="btn btn-primary" id="upConfirmBtn">确定</button></div>
</div></div></div>
<!-- 角色多选弹窗（pickers.js 顶层绑定依赖，占位） -->
<div class="modal fade" id="rolePickerModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
<div class="modal-header bg-primary text-white"><h5 class="modal-title"><i class="bi bi-tags"></i> 选择角色（可多选）</h5><button class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
  <input type="text" class="form-control form-control-sm mb-2" id="rpKeyword" placeholder="搜索角色名称…">
  <div id="rpList" style="max-height:320px;overflow:auto"></div>
</div>
<div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">取消</button><button class="btn btn-primary" data-bs-dismiss="modal">确定</button></div>
</div></div></div>
<script>
// v2.38.25：发票流程配置页数据注入（Step2 审批角色/用户/公司/分类下拉 + 审批流回填）
window.__formBuilder = {
  form: <?=json_encode($form_key, JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>,
  types: <?=json_encode($field_types, JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>,
  roles: <?=json_encode($builder_roles ?? [], JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>,
  users: <?=json_encode($builder_users ?? [], JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>,
  flow: <?=json_encode($builder_flow ?? null, JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>,
  companies: <?=json_encode($builder_companies ?? [], JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>,
  categories: <?=json_encode($builder_categories ?? (object)null, JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>
};
</script>
<script src="<?=asset_url('js/form-linkage.js')?>"></script>
<script src="<?=asset_url('js/admin/pickers.js')?>"></script>
<script src="<?=asset_url('js/form-builder.js')?>"></script>
<?php include __DIR__.'/../layout/footer.php'; ?>
