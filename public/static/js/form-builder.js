/**
 * 通用表单设计器（G1/G2，v2.38.7）— 钉钉式所见即所得
 * 两阶段：Step1 字段画布（字段池→画布预览→属性面板 + 联动规则）→ Step2 审批与抄送。
 * 通用组件：window.__formBuilder.form 驱动；未来审批表单复用同一页面/引擎。
 */
(function () {
    var CFG = window.__formBuilder || { form: 'invoice_apply', types: {}, roles: [], users: [], flow: null, companies: [], categories: {}, projects: [] };
    var fields = [];      // [{id,key,label,type,options:[],required,enabled,is_system}]
    var linkRules = [];   // [{trigger_field,trigger_value,target_field,action,options:[]}]
    var flowGroups = [];  // [{condition:{field,value}|null, nodes:[{name,type,role_code,approvers,mode}], cc:{role_codes:[],cc_user_ids:[]}}]
    var selId = -1;       // 画布选中字段索引
    var linkEditIdx = -1;
    var nextCustom = 1;

    // v2.38.20 控件元数据：图标 + 分类（参照钉钉设计器）
    var CTRL_META = {
        text:        {icon: 'bi-fonts',          cat: 'input',   label: '单行输入框'},
        textarea:    {icon: 'bi-text-paragraph', cat: 'input',   label: '多行输入框'},
        number:      {icon: 'bi-123',            cat: 'input',   label: '数字输入框'},
        phone:       {icon: 'bi-telephone',      cat: 'input',   label: '电话'},
        radio:       {icon: 'bi-record-circle',  cat: 'option',  label: '单选框'},
        checkbox:    {icon: 'bi-check-square',   cat: 'option',  label: '多选框'},
        select:      {icon: 'bi-chevron-down',   cat: 'option',  label: '下拉选择'},
        date:        {icon: 'bi-calendar3',      cat: 'date',    label: '日期'},
        daterange:   {icon: 'bi-calendar-range', cat: 'date',    label: '日期区间'},
        description: {icon: 'bi-blockquote-left',cat: 'layout',  label: '说明文字'},
        // 业务控件（发票表单专用；合同业务控件已随合同表单取消）
        company:     {icon: 'bi-building',       cat: 'biz',     label: '公司主体'},
        customer:    {icon: 'bi-person-lines-fill',cat:'biz',    label: '客户'},
        attachment:  {icon: 'bi-paperclip',      cat: 'biz',     label: '附件上传'},
    };
    var CTRL_CATS = {
        input:   {icon: 'bi-cursor-text',  label: '输入控件'},
        option:  {icon: 'bi-ui-radios',    label: '选择控件'},
        date:    {icon: 'bi-calendar3',    label: '日期控件'},
        layout:  {icon: 'bi-layout-text-sidebar', label: '布局控件'},
        biz:     {icon: 'bi-briefcase',    label: '业务控件'},
    };

    function escHtml(s) { return esc(s == null ? '' : String(s)); }
    function rowCls(type) { return type === 'textarea' || type === 'description' ? 'col-12' : 'col-md-6'; }

    // ===== Step1 渲染（v2.38.22：Step1 复用申请开票表单——服务端渲染，仅「开票内容选项」由前端渲染/编辑）=====
    function render() {
        renderContentOpts();
    }
    /** 渲染「开票内容选项」chips（fields 中 content_desc 的 options；v2.38.25 起位于配置侧边栏顶部） */
    function renderContentOpts() {
        var view = document.getElementById('fbContentOptView');
        if (!view) return;
        var f = null;
        fields.forEach(function (x) { if (x.key === 'content_desc') f = x; });
        if (!f || !(f.options || []).length) {
            view.innerHTML = '<span class="text-muted small">暂无选项，点击「编辑」添加</span>';
            return;
        }
        view.innerHTML = (f.options || []).map(function (o) {
            return '<span class="fb-opt-tile" style="cursor:default"><i class="bi bi-check2"></i>' + escHtml(o.label) + '</span>';
        }).join('');
    }
    /** 打开开票内容选项编辑弹窗 */
    window.fbContentOptOpen = function () {
        var f = null;
        fields.forEach(function (x) { if (x.key === 'content_desc') f = x; });
        if (!f) { showToast('未找到开票内容字段', 'error'); return; }
        document.getElementById('fbOptText').value = (f.options || []).map(function (o) { return o.value + '=' + o.label; }).join('\n');
        document.getElementById('fbOptErr').textContent = '';
        new bootstrap.Modal('#fbOptModal').show();
    };
    /** 解析选项文本（每行一个；支持 值=显示名 或 仅显示名） */
    function parseOptions(txt) {
        return String(txt || '').split('\n').map(function (l) { return l.trim(); }).filter(Boolean).map(function (l) {
            var i = l.indexOf('=');
            return i > 0 ? { value: l.slice(0, i).trim(), label: l.slice(i + 1).trim() } : { value: l, label: l };
        });
    }
    /** 保存开票内容选项（轻量接口，只更新 content_desc 的 field_options） */
    window.fbOptSave = function () {
        var f = null;
        fields.forEach(function (x) { if (x.key === 'content_desc') f = x; });
        if (!f) return;
        var opts = parseOptions(document.getElementById('fbOptText').value);
        if (!opts.length) { showToast('至少保留一个选项', 'error'); return; }
        var body = new URLSearchParams({ form: CFG.form, options: JSON.stringify(opts) });
        $ajax('/ajax/form-builder/save-content-options', { method: 'POST', body: body, loading: true, loadingText: '保存中…' }).then(function (res) {
            showToast(res.msg || '已保存', res.code === 0 ? 'success' : 'error');
            if (res.code === 0) {
                f.options = opts;
                bootstrap.Modal.getInstance('#fbOptModal').hide();
                renderContentOpts(); // 刷新侧边栏「开票内容选项」chips
            }
        }).catch(function () {});
    };

    // ===== Step2：钉钉式单一画布多流程（v2.38.19；H4：按表单字段分支——如不同开票公司走不同审批人/抄送；默认组兜底）=====
    // 结构：发起人 → 分支区（默认流程/条件分支 横向并列卡片，每卡独立节点序列+抄送）→ 结束。
    // 相较 v2.38.18「每组一套完整流程图」：发起人/结束只画一次，分支并列一眼看到全部流程路径，配置效率更高。
    // v2.38.25：条件分支字段固定为「开票主体」（our_company_id），不再提供字段下拉。
    var flowGroups = [];  // [{condition:{field,value}|null, nodes:[{name,type,role_code,approvers,mode}], cc:{role_codes:[],cc_user_ids:[]}}]

    /** 审批节点行 HTML（分支卡片内，紧凑横向控件；携带组索引 gi；v2.38.22 增加节点级金额条件 + 激活条件选择器） */
    function nodeHtml(gi, n, i) {
        var typeSel = '<select class="form-select form-select-sm" onchange="fbNodeChange(' + gi + ',' + i + ',\'type\',this.value)">'
            + '<option value="ROLE"' + (n.type === 'ROLE' ? ' selected' : '') + '>审批角色</option>'
            + '<option value="SPECIFIC_USER"' + (n.type === 'SPECIFIC_USER' ? ' selected' : '') + '>指定用户</option>'
            + '<option value="DEPT_LEADER"' + (n.type === 'DEPT_LEADER' ? ' selected' : '') + '>提交人部门负责人</option></select>';
        var approverHtml = '';
        if (n.type === 'ROLE') {
            var rh = '<select class="form-select form-select-sm" onchange="fbNodeChange(' + gi + ',' + i + ',\'role_code\',this.value)"><option value="">选择角色</option>';
            (CFG.roles || []).forEach(function (r) { rh += '<option value="' + escHtml(r.code) + '"' + (n.role_code === r.code ? ' selected' : '') + '>' + escHtml(r.name) + '</option>'; });
            approverHtml = rh + '</select>';
        } else if (n.type === 'SPECIFIC_USER') {
            // v2.38.22：指定用户改为弹窗选人（openUserPicker 部门树+搜索+多选），不再用下拉
            var uh = '<div class="d-flex flex-wrap gap-1 mb-1">';
            (n.approvers || []).forEach(function (uid) {
                var un = userNameById(uid);
                uh += '<span class="fb-chip">' + escHtml(un) + '<b onclick="fbNodeRemoveUser(' + gi + ',' + i + ',' + uid + ')">×</b></span>';
            });
            if (!(n.approvers || []).length) uh += '<span class="text-muted small">未选择</span>';
            uh += '</div><button type="button" class="btn btn-sm btn-outline-primary" onclick="fbNodePickUsers(' + gi + ',' + i + ')"><i class="bi bi-person-plus"></i> 选择审批人</button>';
            approverHtml = uh;
        }
        var modeSel = '<select class="form-select form-select-sm" style="max-width:110px" onchange="fbNodeChange(' + gi + ',' + i + ',\'mode\',this.value)">'
            + '<option value="OR"' + (n.mode === 'OR' ? ' selected' : '') + '>或签（任一通过）</option>'
            + '<option value="AND"' + (n.mode === 'AND' ? ' selected' : '') + '>会签（全部通过）</option></select>';
        // v2.38.22：节点级金额条件（最低/最高金额，满足区间才进入此节点，留空不限）
        var amt = '<div class="row g-1 align-items-center mt-1">'
            + '<div class="col-4"><label class="form-label small mb-0 text-muted" for="fbAmtMin_'+gi+'_'+i+'">最低金额</label><input type="number" step="0.01" id="fbAmtMin_'+gi+'_'+i+'" class="form-control form-control-sm" value="' + escHtml(n.amount_min != null ? n.amount_min : '') + '" onchange="fbNodeChange(' + gi + ',' + i + ',\'amount_min\',this.value)" placeholder="不限"></div>'
            + '<div class="col-4"><label class="form-label small mb-0 text-muted" for="fbAmtMax_'+gi+'_'+i+'">最高金额</label><input type="number" step="0.01" id="fbAmtMax_'+gi+'_'+i+'" class="form-control form-control-sm" value="' + escHtml(n.amount_max != null ? n.amount_max : '') + '" onchange="fbNodeChange(' + gi + ',' + i + ',\'amount_max\',this.value)" placeholder="不限"></div>'
            + '</div>';
        // v2.38.25：节点级激活条件已移除（发票流程不需要；后端 nodeConditionMet 保留供合同流程使用）
        return '<div class="fb-bnode">'
            + '<div class="fb-bnode-head"><span class="idx"><span class="ico"><i class="bi bi-person-check"></i></span>节点 ' + (i + 1) + '</span>'
            + '<span class="node-ops">'
            + '<button class="btn btn-outline-secondary" title="上移" aria-label="上移" onclick="fbNodeMove(' + gi + ',' + i + ',-1)"><i class="bi bi-arrow-up"></i></button>'
            + '<button class="btn btn-outline-secondary" title="下移" aria-label="下移" onclick="fbNodeMove(' + gi + ',' + i + ',1)"><i class="bi bi-arrow-down"></i></button>'
            + '<button class="btn btn-outline-danger" title="删除" aria-label="删除" onclick="fbNodeDel(' + gi + ',' + i + ')"><i class="bi bi-trash"></i></button>'
            + '</span></div>'
            + '<div class="fb-bnode-body">'
            + '<div class="row g-1 align-items-center mb-1">'
            + '<div class="col-12 col-md-5"><input type="text" class="form-control form-control-sm" value="' + escHtml(n.name) + '" onchange="fbNodeChange(' + gi + ',' + i + ',\'name\',this.value)" placeholder="节点名称"></div>'
            + '<div class="col-7 col-md-4">' + typeSel + '</div>'
            + '<div class="col-5 col-md-3">' + modeSel + '</div>'
            + '</div>'
            + '<div>' + approverHtml + '</div>'
            + amt
            + '</div></div>';
    }

    /** 抄送区 HTML（分支卡片内，紧凑；v2.38.25 抄送角色改为「下拉选择 + 已选 chips」多选；指定用户弹窗选择器） */
    function ccHtml(gi) {
        var g = flowGroups[gi] || { cc: { role_codes: [], cc_user_ids: [] } };
        var cc = g.cc = g.cc || { role_codes: [], cc_user_ids: [] };
        // 已选角色 chips（× 移除）
        var rh = '<div class="d-flex flex-wrap gap-1 mb-1">';
        (cc.role_codes || []).forEach(function (code) {
            var rn = (CFG.roles || []).find(function (r) { return r.code === code; });
            rh += '<span class="fb-chip">' + escHtml(rn ? rn.name : code) + '<b onclick="fbCcRoleRemove(' + gi + ',\'' + escHtml(code) + '\')">×</b></span>';
        });
        if (!(cc.role_codes || []).length) rh += '<span class="text-muted small">未选择</span>';
        rh += '</div>';
        // 未选角色下拉（选择即加入，可多次选择）
        var rsel = '<select id="fbCcRoleSel_' + gi + '" class="form-select form-select-sm" onchange="fbCcRoleAdd(' + gi + ',this)"><option value="">选择角色…</option>';
        (CFG.roles || []).forEach(function (r) {
            if ((cc.role_codes || []).indexOf(r.code) !== -1) return;
            rsel += '<option value="' + escHtml(r.code) + '">' + escHtml(r.name) + '</option>';
        });
        rsel += '</select>';
        var uh = '<div class="d-flex flex-wrap gap-1">';
        (cc.cc_user_ids || []).forEach(function (uid) {
            uh += '<span class="fb-chip">' + escHtml(userNameById(uid)) + '<b onclick="fbCcUserDel(' + gi + ',' + uid + ')">×</b></span>';
        });
        if (!(cc.cc_user_ids || []).length) uh += '<span class="text-muted small">未选择</span>';
        uh += '</div><button type="button" class="btn btn-sm btn-outline-primary" onclick="fbCcPickUsers(' + gi + ')"><i class="bi bi-person-plus"></i> 选择抄送用户</button>';
        return '<div class="cc-title"><i class="bi bi-eye"></i> 抄送</div>'
            + '<div class="row g-1">'
            + '<div class="col-12"><label class="form-label small mb-1" for="fbCcRoleSel_' + gi + '">抄送角色</label>' + rh + rsel + '</div>'
            + '<div class="col-12"><label class="form-label small mb-1">指定用户</label>' + uh + '</div>'
            + '</div>';
    }
    /** v2.38.25：抄送角色下拉选择加入（可多次选择） */
    window.fbCcRoleAdd = function (gi, sel) {
        if (!sel || !sel.value) return;
        var cc = flowGroups[gi].cc = flowGroups[gi].cc || { role_codes: [], cc_user_ids: [] };
        if ((cc.role_codes || []).indexOf(sel.value) === -1) (cc.role_codes = cc.role_codes || []).push(sel.value);
        renderFlowGroups();
    };
    /** v2.38.25：移除已选抄送角色 */
    window.fbCcRoleRemove = function (gi, code) {
        var cc = flowGroups[gi].cc = flowGroups[gi].cc || { role_codes: [], cc_user_ids: [] };
        cc.role_codes = (cc.role_codes || []).filter(function (x) { return x !== code; });
        renderFlowGroups();
    };
    /** v2.38.22：抄送指定用户弹窗选人（复用 openUserPicker，不用下拉） */
    window.fbCcPickUsers = function (gi) {
        var cc = flowGroups[gi].cc = flowGroups[gi].cc || { role_codes: [], cc_user_ids: [] };
        openUserPicker({
            multiple: true, selected: cc.cc_user_ids || [],
            onConfirm: function (ids) {
                cc.cc_user_ids = ids;
                renderFlowGroups();
            }
        });
    };

    /** 条件值控件（v2.38.20）：根据字段类型返回文本输入框或下拉选择器
        v2.38.25：发票条件分支固定按「开票主体」分流，our_company_id 恒为公司下拉（不依赖字段启用状态） */
    function condValueHtml(gi, fieldKey, currentValue) {
        var cur = currentValue || '';
        var field = (fields || []).filter(function (f) { return f.key === fieldKey && f.enabled; })[0];
        // 公司型字段（含固定条件字段开票主体）：从 CFG.companies 下拉选择（v2.38.25 加长显示完整公司名）
        if (fieldKey === 'our_company_id' || (field && field.type === 'company')) {
            var h = '<select class="form-select form-select-sm fb-cond-val" style="max-width:260px;width:auto;min-width:200px;display:inline-block" onchange="fbFlowCondChange(' + gi + ',\'value\',this.value)"><option value="">请选择公司</option>';
            (CFG.companies || []).forEach(function (c) {
                h += '<option value="' + escHtml(String(c.id)) + '"' + (String(cur) === String(c.id) ? ' selected' : '') + '>' + escHtml(c.name) + '</option>';
            });
            h += '</select>';
            return h;
        }
        // 多选框字段 → 多选 chips（值逗号分隔，in 语义）
        if (field && field.type === 'checkbox') {
            var list = field.options || [];
            var curArr = String(cur).split(',').map(function (s) { return s.trim(); }).filter(Boolean);
            var h3 = '<span class="fb-cond-multi" data-gi="' + gi + '">';
            list.forEach(function (o) {
                h3 += '<span class="fb-opt-tile' + (curArr.indexOf(String(o.value)) !== -1 ? ' on' : '') + '" data-code="' + escHtml(String(o.value)) + '" onclick="fbCondMultiToggle(' + gi + ',\'' + escHtml(String(o.value)) + '\')"><i class="bi bi-check2"></i>' + escHtml(o.label) + '</span>';
            });
            h3 += '</span><input type="hidden" class="fb-cond-val" value="' + escHtml(cur) + '">';
            return h3;
        }
        // 下拉型字段（有 options 选项列表）：从选项下拉选择
        if (field && field.type === 'select' && (field.options || []).length) {
            var h2 = '<select class="form-select form-select-sm fb-cond-val" style="max-width:180px;width:auto;display:inline-block" onchange="fbFlowCondChange(' + gi + ',\'value\',this.value)"><option value="">请选择</option>';
            field.options.forEach(function (o) {
                h2 += '<option value="' + escHtml(o.value) + '"' + (cur === o.value ? ' selected' : '') + '>' + escHtml(o.label) + '</option>';
            });
            h2 += '</select>';
            return h2;
        }
        // 兜底：文本输入
        return '<input type="text" class="form-control form-control-sm fb-cond-val" style="max-width:150px;width:auto;display:inline-block" placeholder="条件值" value="' + escHtml(cur) + '" onchange="fbFlowCondChange(' + gi + ',\'value\',this.value)">';
    }

    /** 分支卡片头部（v2.38.25：条件分支 = 第一行 badge+删除按钮（右侧）、第二行 开票主体=公司下拉（节点上方）） */
    function branchHeadHtml(gi, g) {
        var isDef = !g.condition;
        if (isDef) {
            return '<span class="fb-branch-badge def"><i class="bi bi-shield-check"></i> 默认流程</span>'
                + '<span class="text-muted small ms-1">未命中条件时使用</span>';
        }
        // 条件字段固定为「开票主体」（our_company_id），值选择器按公司下拉渲染
        var condVal = condValueHtml(gi, 'our_company_id', g.condition ? g.condition.value : '');
        return '<div class="d-flex align-items-center gap-1 flex-wrap">'
            + '<span class="fb-branch-badge cond"><i class="bi bi-funnel"></i> 条件分支</span>'
            + '<button type="button" class="btn btn-sm btn-outline-danger py-0 px-1" title="删除分支" aria-label="删除分支" onclick="event.stopPropagation();fbFlowDelGroup(' + gi + ')"><i class="bi bi-trash"></i></button>'
            + '</div>'
            + '<div class="mt-1 w-100 d-flex align-items-center flex-wrap gap-1"><span class="fb-cond-label text-muted small">开票主体</span> <span class="text-muted small">=</span> ' + condVal + '</div>';
    }

    /** 流程级金额条件区 HTML（分支卡片内，v2.38.22 对齐原审批流 use_amount/min/max） */
    function groupAmountHtml(gi, g) {
        var amt = g.amount = g.amount || { use: 1, min: '', max: '' };
        return '<div class="fb-branch-amt">'
            + '<div class="d-flex align-items-center gap-2">'
            + '<label class="form-label small mb-0" for="fbGrpAmtUse_' + gi + '">金额条件</label>'
            + '<select id="fbGrpAmtUse_' + gi + '" class="form-select form-select-sm" style="max-width:150px" onchange="fbGroupAmountChange(' + gi + ',\'use\',this.value)">'
            + '<option value="1"' + (String(amt.use) === '1' ? ' selected' : '') + '>启用（按金额区间匹配）</option>'
            + '<option value="0"' + (String(amt.use) === '0' ? ' selected' : '') + '>不启用（不限金额）</option>'
            + '</select>'
            + '<input type="number" step="0.01" class="form-control form-control-sm" style="max-width:110px" placeholder="下限¥" value="' + escHtml(amt.min != null ? amt.min : '') + '" onchange="fbGroupAmountChange(' + gi + ',\'min\',this.value)">'
            + '<span class="text-muted small">~</span>'
            + '<input type="number" step="0.01" class="form-control form-control-sm" style="max-width:110px" placeholder="上限¥" value="' + escHtml(amt.max != null ? amt.max : '') + '" onchange="fbGroupAmountChange(' + gi + ',\'max\',this.value)">'
            + '</div>'
            + '<div class="text-muted small mt-1">按合同/申请金额区间匹配本流程（交易合同有效；不启用=任何金额都走本流程）</div>'
            + '</div>';
    }
    /** v2.38.22：流程组金额条件变更（写入选中分支数据；use 切换时联动显示/隐藏下限上限） */
    window.fbGroupAmountChange = function (gi, k, v) {
        var g = flowGroups[gi];
        g.amount = g.amount || { use: 1, min: '', max: '' };
        g.amount[k] = v;
        if (k === 'use') {
            var f = document.getElementById('fbAmtFields');
            if (f) f.style.display = String(v) === '1' ? '' : 'none';
        }
    };

    function renderFlowGroups() {
        var box = document.getElementById('fbFlowGroups');
        if (!box) return;
        if (!flowGroups.length) {
            flowGroups.push({ condition: null, amount: { use: 1, min: '', max: '' }, nodes: [{ name: '财务审批', type: 'ROLE', role_code: 'finance', approvers: [], mode: 'OR' }], cc: { role_codes: [], cc_user_ids: [] } });
        }
        // 清空容器
        box.innerHTML = '';

        // 钉钉式单一画布：发起人 → 分支区（横向并列卡片）→ 结束
        // 使用 DOM API 逐节点构建，避免 innerHTML 字符串拼接导致的浏览器解析嵌套问题（v2.38.19）
        var flow = document.createElement('div');
        flow.className = 'fb-flow';

        // 发起人
        var starter = document.createElement('div');
        starter.className = 'fb-flow-starter';
        starter.innerHTML = '<i class="bi bi-person"></i> 发起人';
        flow.appendChild(starter);

        // 连接线
        var conn = document.createElement('div');
        conn.className = 'fb-flow-conn';
        flow.appendChild(conn);

        // 分支区（flex-wrap 横向并列）
        var zone = document.createElement('div');
        zone.className = 'fb-branch-zone';

        flowGroups.forEach(function (g, gi) {
            // 构建单张分支卡片
            var branch = document.createElement('div');
            branch.className = 'fb-branch' + (gi === fbSelGroup ? ' sel' : '');
            // v2.38.22：点击选中分支 → 右侧流程配置面板显示该分支的流程级设置（金额条件）
            branch.onclick = function () { fbSelectGroup(gi); };

            // 分支头部（v2.38.25：删除按钮已内联到条件分支 badge 右侧，不再单独 append）
            var head = document.createElement('div');
            head.className = 'fb-branch-head';
            head.innerHTML = branchHeadHtml(gi, g);
            branch.appendChild(head);

            // v2.38.22：流程级金额条件已移入右侧流程配置侧边栏（不再渲染在卡片内）

            // 审批节点列表
            var nodesDiv = document.createElement('div');
            nodesDiv.className = 'fb-branch-nodes';
            if ((g.nodes || []).length) {
                nodesDiv.innerHTML = g.nodes.map(function (n, i) { return nodeHtml(gi, n, i); }).join('');
            } else {
                nodesDiv.innerHTML = '<div class="text-muted small text-center py-2">暂无审批节点，点击下方「添加节点」</div>';
            }
            branch.appendChild(nodesDiv);

            // 操作按钮（添加节点）
            var ops = document.createElement('div');
            ops.className = 'fb-branch-ops mt-2 d-flex gap-1';
            var addBtn = document.createElement('button');
            addBtn.className = 'btn btn-outline-primary btn-sm flex-fill';
            addBtn.onclick = function (e) { e.stopPropagation(); fbNodeAdd(gi); };
            addBtn.innerHTML = '<i class="bi bi-plus-lg"></i> 添加节点';
            ops.appendChild(addBtn);
            branch.appendChild(ops);

            // 抄送区
            var ccDiv = document.createElement('div');
            ccDiv.className = 'fb-branch-cc mt-2';
            ccDiv.innerHTML = ccHtml(gi);
            branch.appendChild(ccDiv);

            // 将分支卡片加入分区
            zone.appendChild(branch);
        });

        flow.appendChild(zone);

        // 添加条件分支按钮
        var addGroupBtn = document.createElement('button');
        addGroupBtn.className = 'btn btn-outline-primary btn-sm mt-2';
        addGroupBtn.onclick = fbFlowAddGroup;
        addGroupBtn.innerHTML = '<i class="bi bi-plus-lg"></i> 添加条件分支';
        flow.appendChild(addGroupBtn);

        // 间距
        var gap = document.createElement('div');
        gap.className = 'fb-flow-gap';
        flow.appendChild(gap);

        // 结束
        var endEl = document.createElement('div');
        endEl.className = 'fb-flow-end';
        endEl.innerHTML = '<i class="bi bi-check2-circle"></i> 结束';
        flow.appendChild(endEl);

        box.appendChild(flow);
        renderFlowConfig();
    }

    // ===== v2.38.22：右侧流程配置侧边栏（选中分支的流程级设置：金额条件）=====
    var fbSelGroup = 0;   // 当前选中分支索引（默认第 0 个=默认流程）
    /** 选中分支：仅切换高亮 + 右侧配置面板联动（v2.38.25：不重渲染整棵画布，
        否则分支内 select 点击打开下拉时 click 冒泡触发 renderFlowGroups 重建，下拉被销毁打不开） */
    window.fbSelectGroup = function (gi) {
        fbSelGroup = gi;
        var branches = document.querySelectorAll('#fbFlowGroups .fb-branch');
        branches.forEach(function (b, i) { b.classList.toggle('sel', i === gi); });
        renderFlowConfig();
    };
    /** 渲染右侧流程配置面板（当前选中分支的金额条件 use/min/max） */
    function renderFlowConfig() {
        var body = document.getElementById('fbFlowConfigBody');
        if (!body) return;
        var g = flowGroups[fbSelGroup];
        if (!g) {
            body.innerHTML = '<span class="text-muted small">暂无分支</span>';
            return;
        }
        var amt = g.amount = g.amount || { use: 1, min: '', max: '' };
        // v2.38.25：画布头部已展示「条件分支 + 开票主体」，配置面板不再重复显示该行
        body.innerHTML = ''
            + '<div class="mb-3">'
            + '<label class="form-label" for="fbSelAmtUse">金额条件</label>'
            + '<select id="fbSelAmtUse" class="form-select" onchange="fbGroupAmountChange(' + fbSelGroup + ',\'use\',this.value)">'
            + '<option value="1"' + (String(amt.use) === '1' ? ' selected' : '') + '>启用（按金额区间匹配）</option>'
            + '<option value="0"' + (String(amt.use) === '0' ? ' selected' : '') + '>不启用（不限金额）</option>'
            + '</select>'
            + '</div>'
            + '<div class="row g-2 mb-3" id="fbAmtFields" style="' + (String(amt.use) === '1' ? '' : 'display:none') + '">'
            + '<div class="col-6"><label class="form-label" for="fbSelAmtMin">下限 ¥</label><input type="number" step="0.01" id="fbSelAmtMin" class="form-control" value="' + escHtml(amt.min != null ? amt.min : '') + '" onchange="fbGroupAmountChange(' + fbSelGroup + ',\'min\',this.value)"></div>'
            + '<div class="col-6"><label class="form-label" for="fbSelAmtMax">上限 ¥</label><input type="number" step="0.01" id="fbSelAmtMax" class="form-control" value="' + escHtml(amt.max != null ? amt.max : '') + '" onchange="fbGroupAmountChange(' + fbSelGroup + ',\'max\',this.value)"></div>'
            + '</div>'
            + '<div class="text-muted small">按申请金额区间匹配本流程（交易合同有效；不启用=任何金额都走本流程）。</div>';
    }
    window.fbNodeAdd = function (gi) { (flowGroups[gi].nodes = flowGroups[gi].nodes || []).push({ name: '审批节点' + (flowGroups[gi].nodes.length + 1), type: 'ROLE', role_code: '', approvers: [], mode: 'OR' }); renderFlowGroups(); };    /** v2.38.18：节点上移/下移（图形化流程排序；源/目标位置均校验防越界写坏数组） */
    window.fbNodeMove = function (gi, i, dir) {
        var arr = flowGroups[gi].nodes;
        var j = i + dir;
        if (i < 0 || i >= arr.length || j < 0 || j >= arr.length) return;
        var t = arr[i]; arr[i] = arr[j]; arr[j] = t;
        renderFlowGroups();
    };
    window.fbNodeDel = function (gi, i) {
        pcConfirm({ message: '确认删除该审批节点？', danger: true }).then(function (ok) {
            if (!ok) return;
            flowGroups[gi].nodes.splice(i, 1);
            renderFlowGroups();
        });
    };
    window.fbNodeChange = function (gi, i, k, v) {
        var n = flowGroups[gi].nodes[i];
        n[k] = v;
        // v2.38.22：节点类型切换后重渲染，立即切换审批人选择器（角色下拉/弹窗选人/自动匹配）
        if (k === 'type') renderFlowGroups();
    };
    /** v2.38.22：指定用户节点弹窗选人（部门树+搜索+多选，与原审批流 openApproverPicker 一致） */
    window.fbNodePickUsers = function (gi, i) {
        var n = flowGroups[gi].nodes[i];
        var cur = n.approvers || [];
        openUserPicker({
            multiple: true, selected: cur,
            onConfirm: function (ids) {
                n.approvers = ids;
                renderFlowGroups();
            }
        });
    };
    /** v2.38.22：按用户 ID 回显姓名（优先当前用户列表，其次弹窗缓存） */
    function userNameById(uid) {
        var u = (CFG.users || []).find(function (x) { return String(x.id) === String(uid); });
        if (u) return u.name;
        if (window._up && _up.nameCache && _up.nameCache[uid]) return _up.nameCache[uid];
        return '用户#' + uid;
    }
    window.fbNodeRemoveUser = function (gi, i, uid) {
        flowGroups[gi].nodes[i].approvers = (flowGroups[gi].nodes[i].approvers || []).filter(function (x) { return x !== uid; });
        renderFlowGroups();
    };
    window.fbCcUserDel = function (gi, uid) {
        var cc = flowGroups[gi].cc = flowGroups[gi].cc || { role_codes: [], cc_user_ids: [] };
        cc.cc_user_ids = (cc.cc_user_ids || []).filter(function (x) { return x !== uid; });
        renderFlowGroups();
    };
    window.fbFlowCondChange = function (gi, k, v) {
        flowGroups[gi].condition = flowGroups[gi].condition || { field: 'our_company_id', value: '' };
        // v2.38.25：条件字段固定为「开票主体」，仅值可改
        flowGroups[gi].condition[k] = v;
    };
    /** v2.38.22：条件值多选 chips（多选框字段）：勾选累积为逗号分隔值，写入隐藏域并同步数据 */
    window.fbCondMultiToggle = function (gi, code) {
        var box = document.querySelector('.fb-cond-multi[data-gi="' + gi + '"]');
        if (!box) return;
        var tile = null;
        box.querySelectorAll('.fb-opt-tile').forEach(function (t) {
            if (t.textContent.replace(/^\s+|\s+$/g, '') === code) tile = t;
        });
        var hidden = box.querySelector('.fb-cond-val');
        var arr = String(hidden.value || '').split(',').map(function (s) { return s.trim(); }).filter(Boolean);
        var idx = arr.indexOf(code);
        if (idx >= 0) arr.splice(idx, 1); else arr.push(code);
        var val = arr.join(',');
        hidden.value = val;
        box.querySelectorAll('.fb-opt-tile').forEach(function (t) {
            t.classList.toggle('on', t.dataset.code === code ? arr.indexOf(code) !== -1 : arr.indexOf(t.dataset.code) !== -1);
        });
        flowGroups[gi].condition = flowGroups[gi].condition || { field: '', value: '' };
        flowGroups[gi].condition.value = val;
    };
    window.fbFlowAddGroup = function () {
        // 条件分支：默认按「开票主体」分流（可改字段），预置一个财务审批节点
        flowGroups.push({ condition: { field: 'our_company_id', value: '' }, amount: { use: 1, min: '', max: '' }, nodes: [{ name: '财务审批', type: 'ROLE', role_code: 'finance', approvers: [], mode: 'OR' }], cc: { role_codes: [], cc_user_ids: [] } });
        fbSelGroup = flowGroups.length - 1; // 新增后自动选中新分支，配置面板联动
        renderFlowGroups();
    };
    window.fbFlowDelGroup = function (gi) {
        pcConfirm({ message: '确认删除该条件分支及其审批设置？', danger: true }).then(function (ok) {
            if (!ok) return;
            flowGroups.splice(gi, 1);
            if (fbSelGroup >= flowGroups.length) fbSelGroup = Math.max(0, flowGroups.length - 1); // 删除后校正选中索引
            renderFlowGroups();
        });
    };

    // ===== 保存 =====
    /** 保存审批设置（视图按钮直接调用） */
    window.fbSaveFlow = saveFlow;
    function saveFlow() {
        // H4：多流程条件分组提交（默认组 condition=null；条件组带 field/value）
        // v2.38.22：组级金额条件 amount{use,min,max} + 节点级金额条件透传
        // v2.38.25：条件字段固定为「开票主体」（our_company_id）
        var groups = flowGroups.map(function (g) {
            var cond = null;
            if (g.condition && g.condition.field) cond = { field: 'our_company_id', value: String(g.condition.value || '') };
            return {
                condition: cond,
                amount: g.amount || { use: 1, min: '', max: '' },
                nodes: g.nodes || [],
                cc: g.cc || { role_codes: [], cc_user_ids: [] }
            };
        });
        var body = new URLSearchParams({ form: CFG.form, groups: JSON.stringify(groups) });
        $ajax('/ajax/form-builder/save-flow', { method: 'POST', body: body, loading: true, loadingText: '保存审批设置中…' }).then(function (res) {
            showToast(res.msg || '已保存', res.code === 0 ? 'success' : 'error');
            // 保存成功后停留在当前设计器（不跳转到申请表单），方便继续调整审批流
        }).catch(function () {});
    }

    // ===== 初始化 =====
    function init() {
        // 回填既有配置
        $ajax('/ajax/form-builder/form-data?form=' + CFG.form, { loading: false }).then(function (res) {
            if (res.code !== 0) return;
            var d = res.data || {};
            // v2.38.26：存量流程标记（admin 列表「新建发票流程」弹窗标题区分：无存量=新建，有存量=重新配置）
            window.fbFlowHasSaved = (d.flow || []).length > 0;
            fields = (d.fields || []).map(function (f) {
                var t = f.field_type || 'text';
                return { id: f.id, key: f.field_key, label: f.field_label, type: t, options: f.options || [], required: !!f.required, enabled: !!f.enabled, is_system: !!f.is_system, option_layout: (t === 'radio' || t === 'checkbox') ? (f.option_layout || 'column') : '', description_text: f.description_text || '' };
            });
            linkRules = (d.linkage || []).map(function (r) {
                return { trigger_field: r.trigger_field, trigger_value: r.trigger_value, target_field: r.target_field, action: r.action, options: r.options || [] };
            });
            // H4：多流程条件分组回填（默认组 + 条件分支组）
            flowGroups = (d.flow || []).map(function (g) {
                return {
                    condition: g.condition ? { field: g.condition.field || '', value: String(g.condition.value == null ? '' : g.condition.value) } : null,
                    amount: g.amount || { use: g.use_amount != null ? g.use_amount : 1, min: g.min_amount != null ? g.min_amount : '', max: g.max_amount != null ? g.max_amount : '' },
                    nodes: (g.nodes || []).map(function (n) {
                        // 兼容恢复备份/旧版本指定用户字段；保存时统一使用 approvers。
                        var legacyApprovers = n.approvers || n.approver_ids || n.user_ids || (n.user_id ? [n.user_id] : []);
                        var nd = { name: n.name || '', type: n.type || 'ROLE', role_code: n.role_code || '', approvers: legacyApprovers, mode: n.mode || 'OR' };
                        // v2.38.22：节点级金额条件回填（v2.38.25：激活条件已移除，不再回填）
                        if (n.amount_min !== undefined && n.amount_min !== '') nd.amount_min = n.amount_min;
                        if (n.amount_max !== undefined && n.amount_max !== '') nd.amount_max = n.amount_max;
                        return nd;
                    }),
                    cc: { role_codes: (g.cc && g.cc.role_codes) || [], cc_user_ids: (g.cc && g.cc.cc_user_ids) || [] }
                };
            });
            if (!flowGroups.length) {
                flowGroups.push({ condition: null, amount: { use: 1, min: '', max: '' }, nodes: [{ name: '财务审批', type: 'ROLE', role_code: 'finance', approvers: [], mode: 'OR' }], cc: { role_codes: [], cc_user_ids: [] } });
            }
            nextCustom = fields.length + 1;
            render();
            renderFlowGroups();
        }).catch(function () {});
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
