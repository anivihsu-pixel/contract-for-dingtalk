/**
 * 通用搜索选择器（v2.41.0）
 * 替代"客户/账号"下拉菜单：任何需要从候选列表（客户、用户等）单选一个 ID 的表单/筛选，
 * 改为「输入关键词 → 浮层建议列表 → 点选」交互。空号清空即还原为"未选"。
 *
 * 渲染约定（服务端输出，本组件负责交互）：
 *   <div class="cs-wrap" data-cs-src="window.__formData.customer_id" data-cs-url="/ajax/customer/search?q=">
 *     <input type="text" class="cs-input" placeholder="搜索…" autocomplete="off" value="已选显示名">
 *     <div class="cs-suggestions"></div>
 *     <input type="hidden" class="cs-id" name="customer_id" value="已选ID">
 *   </div>
 *
 * 数据源（二选一，优先级 src > url）：
 *   - data-cs-src：全局变量路径（内存数组 [{id,name,...}]，如发票申请的 window.__formData.customer_id），前端过滤
 *   - data-cs-url：AJAX 搜索 URL（keyword 直接拼接，如 /ajax/customer/search?q=）
 *
 * 联动：选中后设置隐藏 cs-id 值并派发 change 事件（form-linkage.js 的 fill 动作依赖
 * `[name=customer_id]` 的 change 带出抬头/税号）；手动清空关键词时 cs-id 归 0。
 * 事件委托绑定（document 级），innerHTML 重绘后仍生效。
 */
(function () {
    var timers = {};

    function escHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function qs(root, sel) { return root.querySelector ? root.querySelector(sel) : null; }

    /** 解析内存数据源：data-cs-src 为全局变量路径，如 window.__formData.customer_id */
    function memSource(wrap, srcPath) {
        if (!srcPath) return [];
        var obj = window, parts = srcPath.replace(/^window\./, '').split('.');
        for (var i = 0; i < parts.length; i++) {
            if (obj == null) return [];
            obj = obj[parts[i]];
        }
        return Array.isArray(obj) ? obj : [];
    }

    /** 渲染建议列表 */
    function renderSug(wrap, list, keyword) {
        var box = qs(wrap, '.cs-suggestions');
        if (!box) return;
        if (!list.length) { box.style.display = 'none'; box.innerHTML = ''; return; }
        var h = '';
        list.forEach(function (it) {
            var name = it.name || it.title || ('#' + it.id);
            var sub = (it.credit_code || it.contact_name || it.dept_name || '') !== ''
                ? '<small>' + escHtml(it.credit_code || it.contact_name || it.dept_name) + '</small>' : '';
            h += '<div class="cs-item" data-id="' + it.id + '" data-name="' + escHtml(name) + '">'
                + '<span class="flex-grow-1">' + escHtml(name) + '</span>' + sub + '</div>';
        });
        box.innerHTML = h;
        box.style.display = 'block';
    }

    /** 按关键词过滤内存列表（名称/信用代码/联系人 模糊匹配） */
    function filterMem(list, kw) {
        if (!kw) return list.slice(0, 20);
        var q = kw.toLowerCase();
        return list.filter(function (it) {
            return (it.name || '').toLowerCase().indexOf(q) >= 0
                || (it.credit_code || '').toLowerCase().indexOf(q) >= 0
                || (it.contact_name || '').toLowerCase().indexOf(q) >= 0
                || (it.dept_name || '').toLowerCase().indexOf(q) >= 0;
        }).slice(0, 20);
    }

    function search(wrap, kw) {
        var input = qs(wrap, '.cs-input');
        var box = qs(wrap, '.cs-suggestions');
        if (!input || !box) return;
        var srcPath = wrap.getAttribute('data-cs-src') || '';
        var urlTpl = wrap.getAttribute('data-cs-url') || '';
        if (srcPath) {
            renderSug(wrap, filterMem(memSource(wrap, srcPath), kw), kw);
            return;
        }
        if (!urlTpl) return;
        if (!kw) { box.style.display = 'none'; box.innerHTML = ''; return; }
        var url = urlTpl + encodeURIComponent(kw);
        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                renderSug(wrap, (res && res.data) || [], kw);
            })
            .catch(function () {});
    }

    /** 选中一条：写隐藏 ID、回填显示名、触发 change（联动）、收起浮层 */
    function pick(wrap, id, name) {
        var input = qs(wrap, '.cs-input');
        var hidden = qs(wrap, '.cs-id');
        var box = qs(wrap, '.cs-suggestions');
        if (input) input.value = name;
        if (hidden) {
            var changed = String(hidden.value) !== String(id);
            hidden.value = id;
            if (changed) hidden.dispatchEvent(new Event('change'));
        }
        if (box) { box.style.display = 'none'; box.innerHTML = ''; }
    }

    // ===== 事件委托（document 级） =====
    // 输入搜索（防抖）
    document.addEventListener('input', function (e) {
        var input = e.target;
        if (!input || !input.classList || !input.classList.contains('cs-input')) return;
        var wrap = input.closest('.cs-wrap');
        if (!wrap) return;
        var kw = input.value.trim();
        clearTimeout(timers[wrap.getAttribute('data-cs-wrap') || '']);
        var key = String(Math.random());
        wrap.setAttribute('data-cs-wrap', key);
        timers[key] = setTimeout(function () { search(wrap, kw); }, 250);
        // 手动清空关键词（用户删除已选项文本）→ 视为未选
        if (kw === '' ) {
            var hidden = qs(wrap, '.cs-id');
            if (hidden && String(hidden.value) !== '0') {
                hidden.value = '0';
                hidden.dispatchEvent(new Event('change'));
            }
            var box = qs(wrap, '.cs-suggestions');
            if (box) { box.style.display = 'none'; box.innerHTML = ''; }
        }
    }, true);

    // 点选建议项（mousedown 先行，避免 input 失焦导致浮层先收起）
    document.addEventListener('mousedown', function (e) {
        var item = e.target.closest ? e.target.closest('.cs-item') : null;
        if (!item) return;
        e.preventDefault();
        var wrap = item.closest('.cs-wrap');
        if (!wrap) return;
        pick(wrap, item.getAttribute('data-id'), item.getAttribute('data-name') || item.textContent.trim());
    }, true);

    // 点击浮层外部收起
    document.addEventListener('click', function (e) {
        var wrap = e.target.closest ? e.target.closest('.cs-wrap') : null;
        if (wrap) return;
        document.querySelectorAll('.cs-suggestions').forEach(function (box) {
            if (box.style.display !== 'none') { box.style.display = 'none'; box.innerHTML = ''; }
        });
    }, true);
})();
