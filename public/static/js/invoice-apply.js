/**
 * 发票申请页（F5）：我的申请 / 待我审批 / 快捷申请开票
 * 依赖：window.esc（app.js 全局）、$ajax、showToast、statusLabels（服务端注入 window.__invStatusLabels）
 * 审批动作复用 /ajax/approval/<id>/action（引擎按 biz_type 自动分流合同/发票）
 */
(function () {
    var minePage = 1, mineDone = false, mineLoading = false;
    var pendingPage = 1, pendingDone = false, pendingLoading = false;
    var issuePage = 1, issueDone = false, issueLoading = false;
    var searchTimer = null;
    var issueId = 0;
    var __invActing = false; // P2-09：开票/提交申请防重复连点锁

    function escHtml(s) { return esc(s == null ? '' : String(s)); }
    function money(n) { return '¥' + parseFloat(n || 0).toLocaleString('zh-CN', { minimumFractionDigits: 2 }); }
    function badge(s) {
        var m = {
            PENDING_APPROVAL: '<span class="badge bg-warning">待审批</span>',
            APPROVED: '<span class="badge bg-primary">待开票</span>',
            REJECTED: '<span class="badge bg-danger">已驳回</span>',
            ISSUED: '<span class="badge bg-success">已开票</span>',
            VOID: '<span class="badge bg-secondary">已作废</span>',
            RED: '<span class="badge bg-danger">已红冲</span>',
            CANCELLED: '<span class="badge bg-secondary">已撤回</span>',
            APPLIED: '<span class="badge bg-info">申请中（旧）</span>'
        };
        return m[s] || escHtml(s);
    }

    // ===== Tab 切换 =====
    window.switchTab = function (tab) {
        document.getElementById('panelMine').style.display = tab === 'mine' ? '' : 'none';
        document.getElementById('panelPending').style.display = tab === 'pending' ? '' : 'none';
        var issuePanel = document.getElementById('panelIssue');
        if (issuePanel) issuePanel.style.display = tab === 'issue' ? '' : 'none';
        document.querySelectorAll('#invApplyTabs .nav-link').forEach(function (a) {
            a.classList.toggle('active', a.dataset.tab === tab);
        });
        if (tab === 'mine') loadMine(true);
        else if (tab === 'pending') loadPending(true);
        else loadIssue(true);
    };

    // ===== 我的申请 =====
    window.loadMine = function (reset) {
        if (mineLoading) return;
        if (reset) { minePage = 1; mineDone = false; }
        if (mineDone) return;
        mineLoading = true;
        var status = document.getElementById('mineStatus').value;
        var url = '/ajax/invoice/my-list?page=' + minePage + (status ? '&status=' + encodeURIComponent(status) : '');
        $ajax(url, { silent: true }).then(function (res) {
            mineLoading = false;
            var list = (res && res.data) || [], total = (res && res.count) || 0;
            var tb = document.getElementById('mineTb');
            if (reset) tb.innerHTML = '';
            list.forEach(function (v) {
                var act = '';
                if (v.status === 'REJECTED') {
                    act = '<button class="btn btn-sm btn-outline-primary" onclick="resubmitInv(' + v.id + ')">重新提交</button>';
                } else if (v.status === 'PENDING_APPROVAL' && v.inst_id) {
                    act = '<button class="btn btn-sm btn-outline-secondary" onclick="recallInv(' + v.inst_id + ')">撤回</button>';
                } else if (v.status === 'CANCELLED' || v.status === 'REJECTED') {
                    act = '<button class="btn btn-sm btn-outline-danger" aria-label="删除" onclick="delInv(' + v.id + ')"><i class="bi bi-trash"></i></button>';
                }
                tb.insertAdjacentHTML('beforeend',
                    '<tr><td>' + escHtml(v.content_desc || '—') + '<div class="small text-muted">' + escHtml(v.invoice_title || '') + '</div></td>'
                    + '<td>' + escHtml(v.our_company_name || '—') + '</td><td>' + money(v.amount) + '</td>'
                    + '<td>' + escHtml(v.invoice_type || '') + '</td><td>' + badge(v.status) + '</td>'
                    + '<td class="small text-muted">' + escHtml(v.created_at || '') + '</td><td>' + act + '</td></tr>');
            });
            document.getElementById('mineEmpty').style.display = (tb.innerHTML === '') ? '' : 'none';
            document.getElementById('mineMore').style.display = (tb.innerHTML !== '' && tb.rows.length < total) ? '' : 'none';
            if (tb.rows.length >= total && total > 0) mineDone = true;
        }).catch(function () {
            // 加载失败（silent 不 toast）：面板内展示重试入口，避免"加载中"永驻
            mineLoading = false;
            var tb = document.getElementById('mineTb');
            if (tb) tb.innerHTML = '<tr><td colspan="7" class="text-center py-5 text-muted"><i class="bi bi-exclamation-triangle" style="font-size:2rem"></i><div class="mt-2">加载失败，点击重试</div><button type="button" class="btn btn-sm btn-outline-secondary mt-2" onclick="loadMine(true)"><i class="bi bi-arrow-clockwise"></i> 重新加载</button></td></tr>';
            var em = document.getElementById('mineEmpty'); if (em) em.style.display = 'none';
            var mo = document.getElementById('mineMore'); if (mo) mo.style.display = 'none';
        });
    };
    window.loadMineMore = function () { if (!mineDone && !mineLoading) { minePage++; loadMine(false); } };

    // ===== 待我审批 =====
    window.loadPending = function (reset) {
        if (pendingLoading) return;
        if (reset) { pendingPage = 1; pendingDone = false; }
        if (pendingDone) return;
        pendingLoading = true;
        $ajax('/ajax/invoice/pending-approval?page=' + pendingPage, { silent: true }).then(function (res) {
            pendingLoading = false;
            var list = (res && res.data) || [], total = (res && res.count) || 0;
            var tb = document.getElementById('pendingTb');
            if (reset) tb.innerHTML = '';
            list.forEach(function (v) {
                var act = '<button class="btn btn-sm btn-outline-success" onclick="approveInv(' + v.inst_id + ',1)">通过</button> '
                    + '<button class="btn btn-sm btn-outline-danger" onclick="approveInv(' + v.inst_id + ',0)">驳回</button>';
                tb.insertAdjacentHTML('beforeend',
                    '<tr><td>' + escHtml(v.content_desc || '—') + '<div class="small text-muted">' + escHtml(v.invoice_title || '') + '</div></td>'
                    + '<td>' + escHtml(v.applicant_name || '—') + '</td><td>' + escHtml(v.our_company_name || '—') + '</td>'
                    + '<td>' + money(v.amount) + '</td><td>' + escHtml(v.node_name || '') + '</td>'
                    + '<td class="small text-muted">' + escHtml(v.submitted_at || '') + '</td><td>' + act + '</td></tr>');
            });
            document.getElementById('pendingEmpty').style.display = (tb.innerHTML === '') ? '' : 'none';
            document.getElementById('pendingMore').style.display = (tb.innerHTML !== '' && tb.rows.length < total) ? '' : 'none';
            if (tb.rows.length >= total && total > 0) pendingDone = true;
        }).catch(function () {
            // 加载失败（silent 不 toast）：面板内展示重试入口，避免"加载中"永驻
            pendingLoading = false;
            var tb = document.getElementById('pendingTb');
            if (tb) tb.innerHTML = '<tr><td colspan="7" class="text-center py-5 text-muted"><i class="bi bi-exclamation-triangle" style="font-size:2rem"></i><div class="mt-2">加载失败，点击重试</div><button type="button" class="btn btn-sm btn-outline-secondary mt-2" onclick="loadPending(true)"><i class="bi bi-arrow-clockwise"></i> 重新加载</button></td></tr>';
            var em = document.getElementById('pendingEmpty'); if (em) em.style.display = 'none';
            var mo = document.getElementById('pendingMore'); if (mo) mo.style.display = 'none';
        });
    };
    window.loadPendingMore = function () { if (!pendingDone && !pendingLoading) { pendingPage++; loadPending(false); } };

    // ===== 待开票（财务） =====
    window.loadIssue = function (reset) {
        if (issueLoading) return;
        if (reset) { issuePage = 1; issueDone = false; }
        if (issueDone) return;
        issueLoading = true;
        $ajax('/ajax/invoice/pending-issue?page=' + issuePage, { silent: true }).then(function (res) {
            issueLoading = false;
            var list = (res && res.data) || [], total = (res && res.count) || 0;
            var tb = document.getElementById('issueTb');
            if (reset) tb.innerHTML = '';
            list.forEach(function (v) {
                var act = '<button class="btn btn-sm btn-outline-success" onclick="openIssue(' + v.id + ')"><i class="bi bi-check2-circle"></i> 开票</button>';
                tb.insertAdjacentHTML('beforeend',
                    '<tr><td>' + escHtml(v.content_desc || '—') + '<div class="small text-muted">' + escHtml(v.invoice_title || '') + '</div></td>'
                    + '<td>' + escHtml(v.applicant_name || '—') + '</td><td>' + escHtml(v.our_company_name || '—') + '</td>'
                    + '<td>' + money(v.amount) + '</td><td>' + escHtml(v.invoice_type || '') + '</td><td>' + badge(v.status) + '</td>'
                    + '<td>' + act + '</td></tr>');
            });
            document.getElementById('issueEmpty').style.display = (tb.innerHTML === '') ? '' : 'none';
            document.getElementById('issueMore').style.display = (tb.innerHTML !== '' && tb.rows.length < total) ? '' : 'none';
            if (tb.rows.length >= total && total > 0) issueDone = true;
        }).catch(function () {
            // 加载失败（silent 不 toast）：面板内展示重试入口，避免"加载中"永驻
            issueLoading = false;
            var tb = document.getElementById('issueTb');
            if (tb) tb.innerHTML = '<tr><td colspan="7" class="text-center py-5 text-muted"><i class="bi bi-exclamation-triangle" style="font-size:2rem"></i><div class="mt-2">加载失败，点击重试</div><button type="button" class="btn btn-sm btn-outline-secondary mt-2" onclick="loadIssue(true)"><i class="bi bi-arrow-clockwise"></i> 重新加载</button></td></tr>';
            var em = document.getElementById('issueEmpty'); if (em) em.style.display = 'none';
            var mo = document.getElementById('issueMore'); if (mo) mo.style.display = 'none';
        });
    };
    window.loadIssueMore = function () { if (!issueDone && !issueLoading) { issuePage++; loadIssue(false); } };

    window.openIssue = function (id) {
        issueId = id;
        document.getElementById('issueNo').value = '';
        document.getElementById('issueDate').value = new Date().toISOString().slice(0, 10);
        document.getElementById('issueErr').textContent = '';
        new bootstrap.Modal('#issueModal').show();
    };
    window.submitIssue = function () {
        if (__invActing) return; __invActing = true; // P2-09：提交期间防重复连点
        var no = document.getElementById('issueNo').value.trim();
        if (!no) { document.getElementById('issueErr').textContent = '请填写发票号码'; __invActing = false; return; }
        document.getElementById('issueErr').textContent = '提交中…';
        var body = new URLSearchParams({
            id: issueId, invoice_no: no,
            issued_date: document.getElementById('issueDate').value
        });
        $ajax('/ajax/invoice/update', { method: 'POST', body: body, loading: false }).then(function (res) {
            __invActing = false;
            document.getElementById('issueErr').textContent = '';
            showToast(res.msg || '开票成功', res.code === 0 ? 'success' : 'error');
            if (res.code === 0) { bootstrap.Modal.getInstance('#issueModal').hide(); loadIssue(true); loadMine(true); }
        }).catch(function () { __invActing = false; document.getElementById('issueErr').textContent = '网络异常，请重试'; });
    };

    // ===== 审批动作（复用合同审批引擎，引擎按 biz_type 分流） =====
    window.approveInv = function (instId, pass) {
        var msg = pass ? '确认通过该开票申请？通过后由财务开票。' : '确认驳回该开票申请？';
        pcConfirm({ message: msg, danger: !pass }).then(function (ok) {
            if (!ok) return;
            var body = new URLSearchParams({ action: pass ? 'APPROVED' : 'REJECTED' });
            var submit = function () {
                $ajax('/ajax/approval/' + instId + '/action', { method: 'POST', body: body, loading: true, loadingText: '提交中…' })
                    .then(function (res) {
                        showToast(res.msg || '操作完成', res.code === 0 ? 'success' : 'error');
                        if (res.code === 0) loadPending(true);
                    }).catch(function () {});
            };
            if (!pass) {
                // 2026-08-07：驳回意见改为选填（与移动端 finance.php 一致），取消/关闭时中止操作
                pcPrompt({ title: '驳回意见', placeholder: '请输入驳回意见（选填）' }).then(function (c) {
                    if (c === null) return;
                    if (c.trim()) body.append('comment', c.trim());
                    submit();
                });
            } else {
                submit();
            }
        });
    };

    // ===== 撤回 / 删除 / 重新提交 =====
    window.recallInv = function (instId) {
        pcConfirm({ message: '确认撤回该开票申请？', danger: true }).then(function (ok) {
            if (!ok) return;
            $ajax('/ajax/approval/' + instId + '/recall', { method: 'POST', body: new URLSearchParams({}), loading: true })
                .then(function (res) { showToast(res.msg || '已撤回', res.code === 0 ? 'success' : 'error'); if (res.code === 0) { loadMine(true); loadPending(true); } })
                .catch(function () {});
        });
    };
    window.delInv = function (id) {
        pcConfirm({ message: '确认删除该开票申请？', danger: true }).then(function (ok) {
            if (!ok) return;
            $ajax('/ajax/invoice/delete', { method: 'POST', body: new URLSearchParams({ id: id }), loading: true })
                .then(function (res) { showToast(res.msg || '已删除', res.code === 0 ? 'success' : 'error'); if (res.code === 0) loadMine(true); })
                .catch(function () {});
        });
    };
    window.resubmitInv = function (id) {
        pcConfirm({ message: '确认重新提交该申请进入审批？', danger: false }).then(function (ok) {
            if (!ok) return;
            $ajax('/ajax/invoice/resubmit', { method: 'POST', body: new URLSearchParams({ id: id }), loading: true })
                .then(function (res) { showToast(res.msg || '已提交', res.code === 0 ? 'success' : 'error'); if (res.code === 0) loadMine(true); })
                .catch(function () {});
        });
    };

    // ===== 关联合同搜索（可选） =====
    var contractInput = document.getElementById('contractSearch');
    if (contractInput) {
        contractInput.addEventListener('input', function () {
            var q = this.value.trim();
            clearTimeout(searchTimer);
            if (q.length < 2) { hideSug(); return; }
            searchTimer = setTimeout(function () {
                $ajax('/ajax/contract/search?keyword=' + encodeURIComponent(q), { silent: true }).then(function (res) {
                    var list = (res && res.data) || [];
                    renderSug(list);
                }).catch(function () {});
            }, 250);
        });
        contractInput.addEventListener('focus', function () { if (this.value.trim().length >= 2) { this.dispatchEvent(new Event('input')); } });
    }
    function renderSug(list) {
        var box = document.getElementById('contractSuggestions');
        if (!list.length) { box.style.display = 'none'; return; }
        var h = '';
        list.forEach(function (c) {
            h += '<div class="party-item" data-id="' + c.id + '"><span class="flex-grow-1">' + escHtml(c.title || c.contract_no) + '</span><small class="text-muted ms-2">' + escHtml(c.contract_no || '') + '</small></div>';
        });
        box.innerHTML = h;
        box.style.display = 'block';
        box.querySelectorAll('.party-item').forEach(function (el) {
            el.addEventListener('mousedown', function (e) {
                e.preventDefault();
                document.getElementById('contractId').value = el.dataset.id;
                contractInput.value = el.querySelector('.flex-grow-1').textContent;
                hideSug();
            });
        });
    }
    function hideSug() { var b = document.getElementById('contractSuggestions'); if (b) { b.style.display = 'none'; b.innerHTML = ''; } }
    document.addEventListener('click', function (e) {
        var w = document.getElementById('contractSuggestions');
        if (w && w.style.display !== 'none' && !w.parentElement.contains(e.target)) hideSug();
    });

    // ===== 申请弹窗与提交 =====
    window.showApplyModal = function () {
        document.getElementById('applyErr').textContent = '';
        document.getElementById('contractId').value = 0;
        if (contractInput) contractInput.value = '';
        // v2.41.0：开票客户搜索选择器重置（隐藏 id 归 0，输入框清空）
        var f = document.getElementById('applyFields');
        if (f) {
            f.querySelectorAll('.cs-wrap').forEach(function (w) {
                var ci = w.querySelector('.cs-input'); if (ci) ci.value = '';
                var hid = w.querySelector('.cs-id'); if (hid) hid.value = '0';
            });
            var co = f.querySelector('select[name="our_company_id"]');
            if (co && co.options.length > 1) co.selectedIndex = 1;
            var amt = f.querySelector('input[name="amount"]'); if (amt) amt.value = '';
        }
        refreshApplyCompanyRate();
        new bootstrap.Modal('#applyModal').show();
    };

    /** 2026-08-02：开票税率随主体带出——选择开票主体后从 option data-rate 读取并写入隐藏税率字段，刷新价税拆分 */
    function refreshApplyCompanyRate() {
        var f = document.getElementById('applyFields');
        if (!f) return;
        var co = f.querySelector('select[name="our_company_id"]');
        var rate = f.querySelector('input[name="tax_rate"]');
        if (!co || !rate) return;
        var opt = co.options[co.selectedIndex];
        var r = opt ? (opt.getAttribute('data-rate') || '') : '';
        if (r !== '') rate.value = r;
        calcApplyTax();
    }
    // 绑定：切换开票主体即时带出税率
    (function () {
        var f = document.getElementById('applyFields');
        if (!f) return;
        var co = f.querySelector('select[name="our_company_id"]');
        if (co) co.addEventListener('change', refreshApplyCompanyRate);
    })();
    // P1-7：重新输入即清除字段错误样式
    (function () {
        var f = document.getElementById('applyFields');
        if (!f) return;
        f.addEventListener('input', function (e) {
            if (e.target && e.target.classList) e.target.classList.remove('is-invalid');
        });
    })();

    window.submitApply = function () {
        if (__invActing) return; __invActing = true; // P2-09：提交期间防重复连点
        var err = document.getElementById('applyErr');
        var fd = new FormData();
        fd.append('contract_id', document.getElementById('contractId').value || 0);
        document.querySelectorAll('#applyFields [name]').forEach(function (el) { fd.append(el.name, el.value); });
        // 必填校验（服务端渲染带 required 属性；P1-7：失败字段标红 + 滚动定位，输入即清除）
        var firstBad = null;
        var miss = [];
        document.querySelectorAll('#applyFields [required]').forEach(function (el) {
            el.classList.remove('is-invalid');
            if (!el.value.trim()) {
                el.classList.add('is-invalid');
                if (!firstBad) firstBad = el;
                var lb = el.closest('div').querySelector('.form-label');
                miss.push(lb ? lb.textContent.replace('*', '').trim() : el.name);
            }
        });
        if (miss.length) {
            err.textContent = '请填写：' + miss.join('、');
            if (firstBad) { try { firstBad.focus(); } catch (e) {} try { firstBad.scrollIntoView({ behavior: 'smooth', block: 'center' }); } catch (e) {} }
            __invActing = false;
            return;
        }
        err.textContent = '提交中…';
        $ajax('/ajax/invoice/add', { method: 'POST', body: fd, loading: false }).then(function (res) {
            __invActing = false;
            err.textContent = '';
            showToast(res.msg || '提交成功', res.code === 0 ? 'success' : 'error');
            if (res.code === 0) {
                bootstrap.Modal.getInstance('#applyModal').hide();
                loadMine(true);
            }
        }).catch(function () { __invActing = false; err.textContent = '网络异常，请重试'; });
    };

    // ===== 初始加载 =====
    function init() {
        loadMine(true);
        var more = document.querySelector('#panelMine #mineMore button');
        if (more) more.addEventListener('click', function () { loadMine(false); });
        var pmore = document.querySelector('#panelPending #pendingMore button');
        if (pmore) pmore.addEventListener('click', function () { loadPending(false); });
        var imore = document.querySelector('#panelIssue #issueMore button');
        if (imore) imore.addEventListener('click', function () { loadIssue(false); });
        // H2：含税金额价税实时展示（含税 = 不含税 + 税额；金额变化即时刷新；税率随主体带出由 refreshApplyCompanyRate 维护）
        var af = document.getElementById('applyFields');
        if (af) {
            var amtEl = af.querySelector('input[name="amount"]');
            if (amtEl) {
                var refresh = function () { calcApplyTax(); };
                amtEl.addEventListener('input', refresh);
                amtEl.addEventListener('change', refresh);
            }
            // 2026-08-04：价税拆分展示移到「含税金额」输入所在行的下方（独立整行，不再置于整表末尾）
            var tcBox = document.getElementById('applyTaxCalc');
            var amtCol = amtEl ? amtEl.closest('.col-12, .col-md-6, .col-6, [class*="col"]') : null;
            if (tcBox && amtCol && amtCol.parentNode === af) {
                tcBox.classList.add('col-12');
                amtCol.after(tcBox);
            }
        }
        // 2026-08-02：初始按默认主体带出税率并展示价税（弹窗打开时也会再次刷新）
        refreshApplyCompanyRate();
    }
    /** 含税金额价税拆分展示（含税 ¥A = 不含税 ¥B + 税额 ¥C，税率 X%） */
    window.calcApplyTax = function () {
        var f = document.getElementById('applyFields'), box = document.getElementById('applyTaxCalc');
        if (!f || !box) return;
        var amt = f.querySelector('[name="amount"]'), rate = f.querySelector('[name="tax_rate"]');
        if (!amt || !rate) { box.style.display = 'none'; return; }
        var a = parseFloat(amt.value), r = parseFloat(rate.value);
        if (!(a > 0) || !(r > 0) || r >= 1) { box.style.display = 'none'; return; }
        var tax = Math.round(a / (1 + r) * r * 100) / 100;
        var net = Math.round((a - tax) * 100) / 100;
        var fmt = function (n) { return n.toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); };
        box.style.display = '';
        box.innerHTML = '含税 <b>' + fmt(a) + '</b> 元 = 不含税 <b>' + fmt(net) + '</b> 元 + 税额 <b>' + fmt(tax) + '</b> 元（税率 ' + (r * 100).toLocaleString('zh-CN') + '%）';
    };
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
