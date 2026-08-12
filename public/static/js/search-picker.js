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
        if (!list.length) {
            // 2026-08-11：无匹配时若声明了 data-quick（如发票申请「快速新建客户」），
            // 渲染「未找到 + 快速新建」入口——与新建合同一致：搜索无匹配时建档后回填选择。
            var quickType = wrap.getAttribute('data-quick') || '';
            if (quickType) {
                var label = quickType === 'supplier' ? '供应商' : '客户';
                box.innerHTML = '<div class="cs-empty">未找到匹配的' + label + '</div>'
                    + '<div class="cs-quick" data-quick-type="' + quickType + '">'
                    + '<i class="bi bi-person-plus me-1"></i>快速新建' + label + '</div>';
                box.style.display = 'block';
            } else {
                box.style.display = 'none'; box.innerHTML = '';
            }
            return;
        }
        var h = '';
        list.forEach(function (it) {
            var name = it.name || it.title || ('#' + it.id);
            var sub = (it.credit_code || it.contact_name || it.dept_name || '') !== ''
                ? '<small>' + escHtml(it.credit_code || it.contact_name || it.dept_name) + '</small>' : '';
            // data-credit：附带信用代码（发票申请选中后带出税号；其他使用方可忽略）
            h += '<div class="cs-item" data-id="' + it.id + '" data-name="' + escHtml(name) + '" data-credit="' + escHtml(it.credit_code || '') + '">'
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
            // v2.47.8：无输入关键词时不显示建议（原 filterMem 空 kw 返回前 20 条，清空后 250ms 又弹列表）
            if (!kw) { box.style.display = 'none'; box.innerHTML = ''; return; }
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

    /**
     * data-fill 联动填充（2026-08-11：发票申请「选客户自动带出抬头/税号」内建，不再依赖后台联动规则）：
     * cs-wrap 声明 data-fill-name="目标字段名"（填选中名称）与 data-fill-credit="目标字段名"（填信用代码）。
     * id=0（未选/清空）时清空目标字段。目标字段不存在时静默跳过。
     */
    function applyFill(wrap, id, name, credit) {
        var fn = wrap.getAttribute('data-fill-name');
        var fc = wrap.getAttribute('data-fill-credit');
        if (fn) {
            var fe = document.querySelector('[name="' + fn + '"]');
            if (fe) fe.value = (String(id) === '0' || !id) ? '' : name;
        }
        if (fc) {
            var ce = document.querySelector('[name="' + fc + '"]');
            if (ce) ce.value = (String(id) === '0' || !id) ? '' : credit;
        }
    }

    /** 选中一条：写隐藏 ID、回填显示名、触发 change（联动）、收起浮层 */
    function pick(wrap, item) {
        var input = qs(wrap, '.cs-input');
        var hidden = qs(wrap, '.cs-id');
        var box = qs(wrap, '.cs-suggestions');
        var id = item.getAttribute('data-id');
        var name = item.getAttribute('data-name') || item.textContent.trim();
        var credit = item.getAttribute('data-credit') || '';
        if (input) input.value = name;
        if (hidden) {
            var changed = String(hidden.value) !== String(id);
            hidden.value = id;
            hidden.setAttribute('data-credit', credit);
            if (changed) hidden.dispatchEvent(new Event('change'));
        }
        applyFill(wrap, id, name, credit);
        if (box) { box.style.display = 'none'; box.innerHTML = ''; }
    }

    // ===== 快速新建（2026-08-11：开票申请与新建合同一致——搜索无匹配时就地建档后回填） =====
    // cs-wrap 声明 data-quick="customer"（类型）与 data-quick-url="/ajax/customer/save"（提交地址）后，
    // 空结果浮层出现「快速新建客户」入口；弹层（名称*/税号/联系人/电话）由本组件内建挂载到 body，
    // 保存成功将新档案 id/name/credit_code 写回 cs-wrap 并触发 data-fill 带出抬头/税号。
    var activeQuickWrap = null;

    function quickLabel(type) {
        return type === 'supplier' ? '供应商' : '客户';
    }

    function buildQuickLayer() {
        var layer = document.createElement('div');
        layer.id = 'csQuickLayer';
        layer.innerHTML = ''
            + '<div class="cs-q-mask">'
            + '<div class="cs-q-panel" role="dialog" aria-modal="true">'
            + '<div class="cs-q-hd"><span class="cs-q-title">快速新建</span>'
            + '<button type="button" class="cs-q-close" aria-label="关闭">×</button></div>'
            + '<div class="cs-q-bd">'
            + '<div class="cs-q-field"><label>名称 <b>*</b></label>'
            + '<input type="text" class="cs-q-input" data-q="name" placeholder="请输入名称" autocomplete="off"></div>'
            + '<div class="cs-q-field"><label>税号（信用代码）</label>'
            + '<input type="text" class="cs-q-input" data-q="credit" placeholder="选填，开票自动带出税号" autocomplete="off"></div>'
            + '<div class="cs-q-field"><label>联系人</label>'
            + '<input type="text" class="cs-q-input" data-q="contact" placeholder="选填" autocomplete="off"></div>'
            + '<div class="cs-q-field"><label>联系电话</label>'
            + '<input type="text" class="cs-q-input" data-q="mobile" placeholder="选填" autocomplete="off"></div>'
            + '<div class="cs-q-err"></div>'
            + '</div>'
            + '<div class="cs-q-ft">'
            + '<button type="button" class="cs-q-cancel">取消</button>'
            + '<button type="button" class="cs-q-save">保存并选择</button>'
            + '</div>'
            + '</div></div>';
        document.body.appendChild(layer);

        layer.querySelector('.cs-q-close').addEventListener('click', closeQuickLayer);
        layer.querySelector('.cs-q-cancel').addEventListener('click', closeQuickLayer);
        layer.querySelector('.cs-q-save').addEventListener('click', quickSubmit);
        layer.querySelector('.cs-q-mask').addEventListener('mousedown', function (e) {
            if (e.target === this) closeQuickLayer(); // 点遮罩关闭
        });
        layer.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && e.target && e.target.classList && e.target.classList.contains('cs-q-input')) {
                e.preventDefault(); quickSubmit();
            } else if (e.key === 'Escape') {
                closeQuickLayer();
            }
        });
        return layer;
    }

    function openQuickCreate(wrap) {
        var layer = document.getElementById('csQuickLayer') || buildQuickLayer();
        activeQuickWrap = wrap;
        var type = wrap.getAttribute('data-quick') || 'customer';
        layer.querySelector('.cs-q-title').textContent = '快速新建' + quickLabel(type);
        layer.querySelectorAll('.cs-q-input').forEach(function (el) { el.value = ''; });
        layer.querySelector('.cs-q-err').textContent = '';
        var saveBtn = layer.querySelector('.cs-q-save');
        saveBtn.disabled = false;
        layer.querySelector('.cs-q-mask').style.display = 'flex';
        setTimeout(function () { layer.querySelector('[data-q="name"]').focus(); }, 60);
    }

    function closeQuickLayer() {
        var layer = document.getElementById('csQuickLayer');
        if (layer) layer.querySelector('.cs-q-mask').style.display = 'none';
        activeQuickWrap = null;
    }

    function quickErr(msg) {
        var layer = document.getElementById('csQuickLayer');
        if (layer) layer.querySelector('.cs-q-err').textContent = msg || '';
    }

    /** 快速新建成功回填：写隐藏 ID + 显示名，触发 data-fill 带出抬头/税号（与 pick 一致） */
    function selectQuickResult(wrap, id, name, credit) {
        var input = qs(wrap, '.cs-input');
        var hidden = qs(wrap, '.cs-id');
        var box = qs(wrap, '.cs-suggestions');
        if (input) input.value = name;
        if (hidden) {
            hidden.value = id;
            hidden.setAttribute('data-credit', credit);
            hidden.dispatchEvent(new Event('change'));
        }
        applyFill(wrap, id, name, credit);
        if (box) { box.style.display = 'none'; box.innerHTML = ''; }
    }

    function quickSubmit() {
        var layer = document.getElementById('csQuickLayer');
        var wrap = activeQuickWrap;
        if (!layer || !wrap) return;
        var name = layer.querySelector('[data-q="name"]').value.trim();
        if (!name) { quickErr('请输入名称'); return; }
        var url = wrap.getAttribute('data-quick-url') || '';
        if (!url) { quickErr('未配置快速新建地址'); return; }
        var fd = new FormData();
        fd.append('name', name);
        fd.append('credit_code', layer.querySelector('[data-q="credit"]').value.trim());
        fd.append('contact_name', layer.querySelector('[data-q="contact"]').value.trim());
        fd.append('contact_mobile', layer.querySelector('[data-q="mobile"]').value.trim());
        var saveBtn = layer.querySelector('.cs-q-save');
        saveBtn.disabled = true;
        var req = window.$ajax
            ? window.$ajax(url, { method: 'POST', body: fd, loading: false })
            : fetch(url, { method: 'POST', body: fd }).then(function (r) { return r.json(); });
        req.then(function (res) {
            if (!res || res.code !== 0) {
                // 409 查重：提示去列表选择已有（与合同侧快速新建口径一致）
                quickErr((res && res.msg) || '新建失败');
                saveBtn.disabled = false;
                return;
            }
            selectQuickResult(wrap, String(res.data && res.data.id), name,
                layer.querySelector('[data-q="credit"]').value.trim());
            closeQuickLayer();
            var t = window.showToast || window.toast;
            if (t) t('已新建并选中', 'success');
        }).catch(function (err) {
            quickErr((err && err.message) || '新建失败，请重试');
            saveBtn.disabled = false;
        });
    }

    // 弹层样式（组件自包含，双端一致；窄屏贴底、宽屏居中）
    var qStyle = document.createElement('style');
    qStyle.textContent = ''
        + '.cs-q-mask{position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:2000;display:none;align-items:flex-end;justify-content:center}'
        + '@media(min-width:768px){.cs-q-mask{align-items:center}}'
        + '.cs-q-panel{background:#fff;width:100%;max-width:420px;border-radius:16px 16px 0 0;box-shadow:0 -8px 30px rgba(0,0,0,.15);animation:csQUp .25s ease;max-height:92vh;display:flex;flex-direction:column}'
        + '@media(min-width:768px){.cs-q-panel{border-radius:16px;animation:csQIn .2s ease}}'
        + '@keyframes csQUp{from{transform:translateY(48px);opacity:.5}to{transform:none;opacity:1}}'
        + '@keyframes csQIn{from{transform:scale(.95);opacity:.5}to{transform:none;opacity:1}}'
        + '.cs-q-hd{display:flex;align-items:center;justify-content:space-between;padding:14px 16px;border-bottom:1px solid #f0f1f3;font-size:16px;font-weight:600;color:#1f2329}'
        + '.cs-q-close{background:none;border:none;font-size:22px;line-height:1;color:#9aa1ab;padding:0 4px;cursor:pointer}'
        + '.cs-q-bd{padding:14px 16px;overflow-y:auto}'
        + '.cs-q-field{margin-bottom:12px}'
        + '.cs-q-field label{display:block;font-size:13px;color:#646a73;margin-bottom:4px}'
        + '.cs-q-field b{color:#fa5151}'
        + '.cs-q-input{width:100%;border:1px solid #e3e6eb;border-radius:8px;padding:9px 12px;font-size:14px;outline:none;box-sizing:border-box}'
        + '.cs-q-input:focus{border-color:var(--m-brand,var(--brand,#0b6cf6));box-shadow:0 0 0 2px rgba(11,108,246,.12)}'
        + '.cs-q-err{color:#fa5151;font-size:13px;min-height:18px;margin-bottom:4px}'
        + '.cs-q-ft{display:flex;gap:10px;padding:12px 16px;border-top:1px solid #f0f1f3}'
        + '.cs-q-cancel,.cs-q-save{flex:1;border:none;border-radius:8px;padding:10px 0;font-size:14px;cursor:pointer}'
        + '.cs-q-cancel{background:#f2f3f5;color:#333}'
        + '.cs-q-save{background:var(--m-brand,var(--brand,#0b6cf6));color:#fff}'
        + '.cs-q-save:disabled{opacity:.6;cursor:default}'
        + '.cs-empty{display:flex;justify-content:center;padding:10px 12px 4px;color:#9aa1ab;font-size:13px}'
        + '.cs-quick{padding:9px 12px;border-top:1px dashed #e8eaed;font-size:13px;color:var(--m-brand,var(--brand,#0b6cf6));cursor:pointer;display:flex;align-items:center}'
        + '.cs-quick:active{background:rgba(11,108,246,.06)}';
    document.head.appendChild(qStyle);

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
            applyFill(wrap, '0', '', '');
            var box = qs(wrap, '.cs-suggestions');
            if (box) { box.style.display = 'none'; box.innerHTML = ''; }
        }
    }, true);

    // 点选建议项（mousedown 先行，避免 input 失焦导致浮层先收起）
    document.addEventListener('mousedown', function (e) {
        var item = e.target.closest ? e.target.closest('.cs-item') : null;
        if (item) {
            e.preventDefault();
            var wrap = item.closest('.cs-wrap');
            if (!wrap) return;
            pick(wrap, item);
            return;
        }
        // 2026-08-11：快速新建入口（发票申请与新建合同一致——搜索无匹配时就地建档后回填）
        var quick = e.target.closest ? e.target.closest('.cs-quick') : null;
        if (quick) {
            e.preventDefault();
            var qwrap = quick.closest('.cs-wrap');
            if (qwrap) openQuickCreate(qwrap);
            return;
        }
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
