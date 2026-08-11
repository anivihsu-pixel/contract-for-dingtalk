/**
 * 通用表单字段联动引擎（F9，v2.38.7）
 * 表单无关组件：任何配置化表单（发票申请 / 未来审批表单）渲染时注入
 * `window.__formRules = [{trigger_field, trigger_value, target_field, action, options}]`，
 * 本引擎监听触发字段 change，按规则联动目标字段：
 *   - action=options：替换目标 select 的选项（触发值命中时），失配时恢复字段自身配置选项
 *   - action=show / hide：显隐目标字段所在行（PC: .col-* / 移动端: .m-field）
 *   - action=fill（H3）：将触发字段关联数据行的指定字段值填入目标字段（客户复用开票信息），
 *     数据源 `window.__formData = {trigger_field: [{id, name, ...}]}`；trigger_value='*' 表示任意值命中；
 *     失配时不清空（保留用户手动输入）。
 * 用法：window.FormLinkage.init(containerSelector 可选, rules 可选)；页面加载后自动初始化。
 */
(function () {
    function escHtml(s) {
        return (window.esc ? window.esc(s) : String(s == null ? '' : s));
    }

    /** 字段行容器：PC 栅格 .col-* 或移动端 .m-field */
    function rowOf(el) {
        return el.closest ? (el.closest('.col-12, .col-md-6, .col-md-3, .m-field') || null) : null;
    }

    /** 将 [{"value","label"}] 渲染为 <option> HTML（value 转义） */
    function optionsHtml(opts) {
        var h = '<option value="">请选择</option>';
        (opts || []).forEach(function (o) {
            h += '<option value="' + escHtml(o.value) + '">' + escHtml(o.label) + '</option>';
        });
        return h;
    }

    /** 执行全部规则：先按目标字段汇总（最后命中者胜），再统一应用——避免多条规则同 target 时
     *  失配规则的「恢复选项」覆盖命中规则的替换结果 */
    function apply(rules, root) {
        var byTarget = {};
        rules.forEach(function (r) {
            var t = root.querySelector('[name="' + r.trigger_field + '"]');
            var target = root.querySelector('[name="' + r.target_field + '"]');
            if (!t || !target) return;
            var tv = String(t.value === null ? '' : t.value);
            // trigger_value='*' 表示任意非空值命中（如：选任何客户都带出抬头/税号）
            var match = r.trigger_value === '*' ? (tv !== '' && tv !== '0') : tv === String(r.trigger_value);
            var entry = byTarget[r.target_field] || (byTarget[r.target_field] = { el: target, matched: false, action: r.action, options: r.options || [] });
            if (match) { entry.matched = true; entry.action = r.action; entry.options = r.options || []; entry.triggerVal = tv; entry.triggerField = r.trigger_field; }
        });

        Object.keys(byTarget).forEach(function (key) {
            var e = byTarget[key], el = e.el;
            if (e.action === 'show' || e.action === 'hide') {
                var row = rowOf(el);
                if (row) row.style.display = e.matched ? (e.action === 'show' ? '' : 'none') : (e.action === 'show' ? 'none' : '');
            } else if (e.action === 'options' && el.tagName === 'SELECT') {
                if (e.matched) {
                    // 命中：替换为联动选项，用户已选值若仍存在则保留
                    var cur = el.value;
                    el.innerHTML = optionsHtml(e.options);
                    var keep = e.options.some(function (o) { return String(o.value) === String(cur); });
                    if (keep) el.value = cur;
                } else if (el.dataset.allOpts) {
                    // 失配：恢复字段自身配置选项（保持用户已选值）
                    var prev = el.value;
                    el.innerHTML = el.dataset.allOpts;
                    var still = Array.from(el.options).some(function (o) { return String(o.value) === String(prev); });
                    if (still) el.value = prev;
                }
            } else if (e.action === 'fill' && e.matched) {
                // H3：填充值——从触发字段数据行取 source_field 填入目标字段（如客户名→抬头、信用代码→税号）
                var src = (e.options && e.options[0] && e.options[0].source_field) || '';
                if (!src) return;
                var rows = (window.__formData && window.__formData[e.triggerField]) || [];
                var found = null;
                for (var i = 0; i < rows.length; i++) {
                    if (String(rows[i].id) === e.triggerVal) { found = rows[i]; break; }
                }
                if (found && found[src] !== undefined && found[src] !== null && String(found[src]) !== '') {
                    el.value = String(found[src]);
                }
            }
        });
    }

    /** 初始化：备份目标字段原始选项 → 绑定触发字段 change → 应用一次 */
    function init(rootSel, rules) {
        var root = (rootSel && typeof rootSel === 'string') ? document.querySelector(rootSel) : document;
        if (!root) root = document;
        rules = rules || (window.__formRules || []);
        if (!rules.length) return;

        // 备份目标字段原始选项（含 option 全部 HTML），供 options 联动失配恢复
        rules.forEach(function (r) {
            if (r.action !== 'options') return;
            var target = root.querySelector('[name="' + r.target_field + '"]');
            if (target && target.tagName === 'SELECT' && !target.dataset.allOpts) {
                target.dataset.allOpts = target.innerHTML;
            }
        });

        // 为每个触发字段绑定 change（含初始化立即应用一次，保证回填值场景正确）
        var triggers = {};
        rules.forEach(function (r) {
            (triggers[r.trigger_field] = triggers[r.trigger_field] || []).push(r);
        });
        Object.keys(triggers).forEach(function (name) {
            var el = root.querySelector('[name="' + name + '"]');
            if (!el) return;
            el.addEventListener('change', function () { apply(rules, root); });
        });
        apply(rules, root);
    }

    window.FormLinkage = { init: init, apply: apply };

    // 自动初始化：DOMContentLoaded 时扫描 window.__formRules（footer app.js 执行完后触发，esc 已就绪）
    function autoInit() {
        if (window.__formRules && window.__formRules.length) init(document, window.__formRules);
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', autoInit);
    } else {
        autoInit();
    }
})();
