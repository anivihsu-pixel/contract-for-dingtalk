<?php
// 移动端共享布局（Phase 1 重构 1.7）：头部与尾部由 _head.php / _foot.php 统一输出
$title = !empty($is_edit)?'编辑合同':'新建合同';   // 动态标题（直接保留 PHP 表达式）
$tab = 'contract';     // 底部导航高亮：home/contract/customer/todo
include __DIR__ . '/_head.php';
?>

<style>
/* 本公司主体选择器（多主体时从底部弹出，复用 /ajax/company/options） */
.m-sheet-mask{position:fixed;inset:0;background:rgba(0,0,0,.4);display:none;z-index:900;align-items:flex-end}
.m-sheet-mask.show{display:flex}
.m-sheet{width:100%;background:#fff;border-radius:16px 16px 0 0;padding:0 0 12px;max-height:70vh;overflow:auto;animation:m-sheet-up .2s ease}
@keyframes m-sheet-up{from{transform:translateY(100%)}to{transform:translateY(0)}}
.m-sheet-hd{font-size:15px;font-weight:600;text-align:center;padding:14px;color:#333;position:sticky;top:0;background:#fff}
.m-sheet-item{display:flex;align-items:center;gap:8px;padding:14px 18px;font-size:15px;border-top:1px solid #f0f0f0;color:#333}
.m-sheet-item:active{background:#f5f7fa}
.m-sheet-item .m-sheet-def{margin-left:auto;font-size:11px;color:#888;background:#f0f0f0;border-radius:4px;padding:2px 6px}
.m-sheet-cancel{margin:10px 18px 0;width:calc(100% - 36px);padding:12px;border:none;background:#f0f0f0;border-radius:10px;font-size:15px;color:#333}
/* 关键词：表单内只读展示区 + 顶部固定弹层（避开输入法遮挡） */
.kw-display{display:flex;flex-wrap:wrap;align-items:center;gap:6px;min-height:44px;padding:7px 10px;border:1px solid #dcdfe6;border-radius:8px;background:#fff;cursor:pointer}
.kw-display:active{border-color:#3b82f6}
.kw-chip{display:inline-flex;align-items:center;gap:4px;background:#e7f1ff;color:#2563eb;border-radius:14px;padding:3px 10px;font-size:13px;line-height:1.5;max-width:100%}
.kw-chip span.tx{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:60vw}
.kw-chip b{cursor:pointer;font-weight:700;opacity:.6;font-size:15px;padding:0 2px}
.kw-display .kw-empty{color:#9aa3af;font-size:14px}
.kw-display .kw-add-hint{margin-left:auto;color:#3b82f6;font-size:13px;white-space:nowrap}
/* 顶部固定关键词弹层：position:fixed top:0 保证浮于输入法之上 */
.kw-mask{position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:950;display:none}
.kw-mask.show{display:block}
.kw-sheet{position:fixed;top:0;left:0;right:0;z-index:951;background:#fff;border-radius:0 0 16px 16px;max-height:78vh;overflow:auto;display:none;animation:kw-down .18s ease}
.kw-sheet.show{display:block}
@keyframes kw-down{from{transform:translateY(-100%)}to{transform:translateY(0)}}
.kw-sheet-hd{display:flex;align-items:center;justify-content:space-between;padding:12px 16px;border-bottom:1px solid #f0f0f0}
.kw-sheet-hd b{font-size:15px;color:#333}
.kw-sheet-hd .kw-close{font-size:22px;color:#999;line-height:1;background:none;border:none;padding:4px}
.kw-sheet-input-row{display:flex;gap:8px;padding:12px 16px 4px}
.kw-sheet-input-row input{flex:1;border:1px solid #dcdfe6;border-radius:8px;padding:10px 12px;font-size:15px;outline:none}
.kw-sheet-input-row input:focus{border-color:#3b82f6}
.kw-sheet-input-row button{border:none;background:#3b82f6;color:#fff;border-radius:8px;padding:0 18px;font-size:14px;font-weight:600;white-space:nowrap}
.kw-sheet-input-row button:disabled{background:#c4d4f6}
.kw-sheet-sec{padding:8px 16px 4px}
.kw-sheet-sec .kw-sec-t{font-size:12px;color:#888;margin-bottom:6px}
.kw-hot{display:flex;flex-wrap:wrap;gap:8px}
.kw-hot-item{background:#f4f6fa;color:#374151;border-radius:14px;padding:5px 12px;font-size:13px;border:1px solid #e5e7eb}
.kw-hot-item:active{background:#e7f1ff;color:#2563eb;border-color:#bfdbfe}
.kw-hot-item.dis{color:#bbb;background:#fafafa}
.kw-cur{display:flex;flex-wrap:wrap;gap:6px;min-height:10px}
.kw-cur .kw-chip b{opacity:.7}
.kw-sheet-none{font-size:12px;color:#bbb;padding:2px 0 10px}
/* v2.40.0：签约方重构——我方身份切换 + 我方主体块 + 交易/非交易 radio + 更多选项徽章 */
.m-seg{display:flex;border:1px solid #dcdfe6;border-radius:8px;overflow:hidden}
.m-seg-btn{flex:1;border:none;background:#f5f7fa;padding:10px 4px;font-size:14px;color:#666;-webkit-appearance:none;appearance:none}
.m-seg-btn.on{background:#3b82f6;color:#fff}
.m-mine{display:flex;align-items:center;justify-content:space-between;gap:8px;padding:9px 12px;border:1px solid #3b82f6;background:#e7f1ff;color:#2563eb;border-radius:8px;font-size:14px;font-weight:500}
.m-mine-name{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex:1}
.m-radio-row{display:flex;flex-direction:column;gap:8px}
.m-radio{display:flex;align-items:center;gap:8px;padding:10px 12px;border:1px solid #dcdfe6;border-radius:8px;font-size:14px;color:#333}
.m-radio input{width:18px;height:18px;flex:none}
.m-radio:has(input:checked){border-color:#3b82f6;background:#f5f9ff}
.m-fold-sub{color:#9aa3af;font-size:12px;font-weight:400;margin-left:4px}
.m-fold-badge{background:#3b82f6;color:#fff;border-radius:12px;padding:1px 8px;font-size:12px;font-weight:500;margin-left:auto}
</style>

<div class="m-nav">
  <a href="/m/contracts" class="back" aria-label="返回"><i class="bi bi-chevron-left"></i></a>
  <div class="title"><?=!empty($is_edit)?'编辑合同':'新建合同'?></div>
  <div class="right"></div>
</div>

<?php // 新建=必填校验生效（红星+required+JS提交前校验）；编辑旧数据不追溯，避免卡住历史合同
      $isNew = empty($is_edit);
      $reqMark = $isNew ? '<span class="req">*</span>' : '';  // label 后红星
      $reqAttr = $isNew ? ' required' : '';                    // input/select 的 required 属性（供 JS 提交前遍历校验）
?>
<div class="m-page has-submitbar" id="page">
  <form class="m-form" id="form" onsubmit="return false">
<?php if(!empty($contract)): ?><input type="hidden" name="id" value="<?=htmlspecialchars($contract['id'] ?? 0)?>"><?php endif; ?>
<input type="hidden" name="custom_fields" value="{}">
<input type="hidden" name="flow_id" id="f_flow_id" value="<?=htmlspecialchars($contract['flow_id'] ?? 0)?>">
<?php
use app\common\form\ContractFormConfig;
$__mmaps = [
    'categories'       => $categories ?? [],
    'companies'        => $companies ?? [],
    'projects'         => $projects ?? [],
    'parent_contracts' => $parent_contracts ?? [],
];
echo ContractFormConfig::mobileRenderAll($contract ?? [], $isNew, $__mmaps, $default_company_id ?? 0);
?>
  </form>
</div>

<div class="m-submitbar">
  <button class="m-btn m-btn-brand" id="submitBtn"><i class="bi bi-check-lg"></i> <?=!empty($is_edit)?'保存修改':'创建合同'?></button>
</div>

<div class="m-toast" id="toast"></div>

<!-- 本公司主体选择器：点甲/乙方「本公司」按钮时，若配置了多个主体则从底部弹出供选择 -->
<div class="m-sheet-mask" id="selfSheetMask">
  <div class="m-sheet">
    <div class="m-sheet-hd">选择本公司主体</div>
    <div class="m-sheet-list" id="selfSheetList"></div>
    <button type="button" class="m-sheet-cancel" id="selfSheetCancel">取消</button>
  </div>
</div>

<!-- v2.46.0：快速新建客户/供应商（签约方强制关联档案——搜索无匹配时表单内快速建档，复用查重与数据权限） -->
<div class="m-sheet-mask" id="quickSheetMask">
  <div class="m-sheet">
    <div class="m-sheet-hd">快速新建客户/供应商</div>
    <div class="m-sheet-bd">
      <div class="m-field"><label class="m-field-label" for="mQuickType">类型</label>
        <select id="mQuickType" class="m-select">
          <option value="customer">客户</option>
          <option value="supplier">供应商</option>
        </select></div>
      <div class="m-field"><label class="m-field-label" for="mQuickName">名称 <span class="m-required">*</span></label>
        <input type="text" id="mQuickName" class="m-input" placeholder="请输入客户/供应商名称"></div>
      <div class="m-field"><label class="m-field-label" for="mQuickContact">联系人</label>
        <input type="text" id="mQuickContact" class="m-input" placeholder="选填"></div>
      <div class="m-field"><label class="m-field-label" for="mQuickMobile">联系电话</label>
        <input type="text" id="mQuickMobile" class="m-input" placeholder="选填"></div>
      <button type="button" class="m-btn m-btn-brand" style="width:100%" onclick="mSaveQuick()"><i class="bi bi-check-lg me-1"></i>保存并选择</button>
    </div>
  </div>
</div>

<!-- 关键词输入弹层（顶部固定，浮于输入法之上）：输入框 + 右侧「添加」按钮 + 当前账号高频标签快速选填 -->
<div class="kw-mask" id="kwMask"></div>
<div class="kw-sheet" id="kwSheet">
  <div class="kw-sheet-hd"><b>关键词</b><button type="button" class="kw-close" id="kwClose">&times;</button></div>
  <div class="kw-sheet-input-row">
    <input type="text" id="kwInput" placeholder="输入关键词后点添加或回车" autocomplete="off">
    <button type="button" id="kwAddBtn" disabled>添加</button>
  </div>
  <div class="kw-sheet-sec">
    <div class="kw-sec-t">常用标签（仅你自己创建过的）</div>
    <div class="kw-hot" id="kwHot"><div class="kw-sheet-none">暂无常用标签</div></div>
  </div>
  <div class="kw-sheet-sec" id="kwCurSec" style="display:none">
    <div class="kw-sec-t">已选（点 × 移除）</div>
    <div class="kw-cur" id="kwCur"></div>
  </div>
</div>

<script>
(function(){
  
  
  

  // v2.40.0：合同性质——交易/非交易 radio 无默认必选（避免默认值错填）；非交易时隐藏金额、方向
  var amountF = document.getElementById('amountField');
  var directionF = document.getElementById('directionField');
  function currentTrade(){ var r = document.querySelector('input[name="trade_attr"]:checked'); return r ? r.value : ''; }
  function syncTrade(){
    var non = currentTrade() !== '1';
    if(non){ if(amountF) amountF.style.display='none'; if(directionF) directionF.style.display='none'; }
    else { if(amountF) amountF.style.display=''; if(directionF) directionF.style.display=''; }
    updateLabels();   // 同步我方立场标注：非交易合同无收付款方向，不应标注「我方」
  }
  document.querySelectorAll('input[name="trade_attr"]').forEach(function(r){ r.addEventListener('change', syncTrade); });
  syncTrade();

  // 合同模板 JS 已隐藏，后端保留

  // ===== 甲乙方身份（法律地位）与收付款方向（资金）解耦 =====
  // ourSide：我方在法律上的哪一侧（A=甲方 / B=乙方），由「本公司」按钮显式指定，
  // 与收付款方向（direction）完全独立。技术服务合同可「我方=乙方 + 收款」。
  var ourSide = 'B';  // v2.40.0：我方默认乙方（业务多为服务方），编辑态由 recomputeOurSide 反推覆盖
  // 签约主体名称映射（id→name），隐藏字段模式下用于 companyName() 查名
  // P0-3【严重·C2】防御：$companies 未注入时降级为 {}，避免输出 "var COMPANY_MAP = ;" 造成 JS 语法错误、
  // 整段 IIFE 无法解析、表单全部交互失效（等效死页）。缺失时仅在「本公司」名称解析上降级，不影响提交。
  var COMPANY_MAP = <?=json_encode(isset($companies) ? array_column($companies, 'name', 'id') : [])?>;
  function companyName(){
    var el = document.getElementById('f_company');
    var id = el ? String(el.value) : '0';
    if(!id || id === '0') return '';
    return COMPANY_MAP[id] || '';
  }
  // 依据两侧名称中是否含签约主体，反推我方侧（编辑态/自动补名后调用）
  function recomputeOurSide(){
    var cn = companyName();
    if(cn){
      if(document.getElementById('f_party_a').value.trim() === cn) ourSide = 'A';
      else if(document.getElementById('f_party_b_name').value.trim() === cn) ourSide = 'B';
    }
  }
  // ===== 关键词：顶部固定弹层输入（避开输入法遮挡）+ 右侧「添加」按钮 + 当前账号高频标签快速选填 =====
  // #f_keywords 为隐藏值载体（name=keywords 参与 FormData）；表单内 .kw-display 只读展示；
  // 点展示区 → 打开 #kwSheet（fixed top:0，浮于输入法上方）→ 输入框 + 添加按钮 + 常用标签；
  // 提交前 kwFlush() 把未点添加的残留文本也收进标签。分隔符兼容中英文逗号/顿号/分号/空白，去重去空。
  var KW_SEP = /[，、；;,\s]+/;
  var kwFlush = function(){};   // 供提交前调用，默认空实现
  (function(){
    var hidden = document.getElementById('f_keywords');
    if(!hidden) return;
    var display  = document.getElementById('kwDisplay');
    var mask     = document.getElementById('kwMask');
    var sheet    = document.getElementById('kwSheet');
    var input    = document.getElementById('kwInput');
    var addBtn   = document.getElementById('kwAddBtn');
    var hot      = document.getElementById('kwHot');
    var cur      = document.getElementById('kwCur');
    var curSec   = document.getElementById('kwCurSec');
    var closeBtn = document.getElementById('kwClose');

    var tags = [];
    function sync(){ hidden.value = tags.join(','); }
    function addRaw(raw){
      var added = false;
      String(raw == null ? '' : raw).split(KW_SEP).forEach(function(p){
        p = p.trim();
        if(p && tags.indexOf(p) === -1){ tags.push(p); added = true; }
      });
      return added;
    }

    // 渲染表单内只读展示区（标签 + 右侧「添加」提示）
    function renderDisplay(){
      display.innerHTML = '';
      if(tags.length === 0){
        var e = document.createElement('span'); e.className = 'kw-empty'; e.textContent = '点击添加关键词';
        display.appendChild(e);
      } else {
        tags.forEach(function(t){
          var chip = document.createElement('span'); chip.className = 'kw-chip';
          var tx = document.createElement('span'); tx.className = 'tx'; tx.textContent = t; tx.title = t;
          chip.appendChild(tx);
          display.appendChild(chip);
        });
      }
      var hint = document.createElement('span'); hint.className = 'kw-add-hint'; hint.textContent = '添加';
      display.appendChild(hint);
      sync();
    }
    // 渲染弹层内「已选」区（可点 × 移除）
    function renderCur(){
      cur.innerHTML = '';
      curSec.style.display = tags.length ? '' : 'none';
      tags.forEach(function(t, i){
        var chip = document.createElement('span'); chip.className = 'kw-chip';
        var tx = document.createElement('span'); tx.className = 'tx'; tx.textContent = t;
        var x = document.createElement('b'); x.textContent = '\u00d7';
        (function(idx){ x.addEventListener('click', function(e){ e.preventDefault(); tags.splice(idx, 1); renderCur(); renderDisplay(); refreshHot(); }); })(i);
        chip.appendChild(tx); chip.appendChild(x);
        cur.appendChild(chip);
      });
    }
    // 渲染高频标签：已选的置灰
    function renderHot(list){
      hot.innerHTML = '';
      if(!list || !list.length){
        var n = document.createElement('div'); n.className = 'kw-sheet-none'; n.textContent = '暂无常用标签';
        hot.appendChild(n); return;
      }
      list.forEach(function(kw){
        var item = document.createElement('div'); item.className = 'kw-hot-item'; item.textContent = kw;
        if(tags.indexOf(kw) !== -1){ item.classList.add('dis'); }
        item.addEventListener('click', function(){
          if(tags.indexOf(kw) === -1){ addRaw(kw); renderCur(); renderDisplay(); refreshHot(list); input.focus(); }
        });
        hot.appendChild(item);
      });
      hot._list = list;
    }
    function refreshHot(list){ renderHot(list || hot._list || []); }

    function openSheet(){
      mask.classList.add('show'); sheet.classList.add('show');
      renderCur(); refreshHot();
      input.value = ''; addBtn.disabled = true;
      // 拉取当前账号高频关键词（仅自己创建过的），只拉一次缓存复用
      if(!hot._loaded){
        hot._loaded = true;
        fetch('/ajax/keyword/hot?limit=12', {headers:{'X-Requested-With':'XMLHttpRequest'}})
          .then(function(r){ return r.json(); })
          .then(function(res){
            if(res && res.code === 0 && Array.isArray(res.data)){ renderHot(res.data); }
          }).catch(function(){});
      }
      // 延迟聚焦：等弹层动画就绪，触发输入法时输入框已在顶部可见（不会被遮挡）
      setTimeout(function(){ input.focus(); }, 60);
    }
    function closeSheet(){ mask.classList.remove('show'); sheet.classList.remove('show'); }

    // 右侧「添加」按钮：把当前输入收为一个标签
    addBtn.addEventListener('click', function(){
      if(input.value.trim()){ addRaw(input.value); input.value = ''; addBtn.disabled = true; renderCur(); renderDisplay(); refreshHot(); }
      input.focus();
    });
    input.addEventListener('input', function(){ addBtn.disabled = !input.value.trim(); });
    input.addEventListener('keydown', function(e){
      if(e.key === 'Enter'){ e.preventDefault(); addBtn.click(); }
      else if(e.key === ',' || e.key === '，' || e.key === '、' || e.key === ';' || e.key === '；'){
        e.preventDefault();
        if(input.value.trim()){ addRaw(input.value); input.value = ''; addBtn.disabled = true; renderCur(); renderDisplay(); refreshHot(); }
      } else if(e.key === 'Backspace' && !input.value && tags.length){
        tags.pop(); renderCur(); renderDisplay(); refreshHot();
      }
    });

    // 打开 / 关闭
    display.addEventListener('click', openSheet);
    display.addEventListener('keydown', function(e){ if(e.key === 'Enter' || e.key === ' '){ e.preventDefault(); openSheet(); } });
    closeBtn.addEventListener('click', closeSheet);
    mask.addEventListener('click', closeSheet);

    addRaw(hidden.value);   // 编辑态回填已有关键词
    renderDisplay();
    kwFlush = function(){ if(input.value.trim()){ addRaw(input.value); input.value = ''; renderDisplay(); } };
  })();

  function updateLabels(){
    var non = currentTrade() !== '1';   // 非交易合同不计入收支，无收付款方向语义，不标注「我方」立场
    var la = document.getElementById('labelA'), lb = document.getElementById('labelB');
    la.textContent = '甲方';
    lb.textContent = '乙方';
    la.classList.toggle('m-our', !non && ourSide === 'A');
    lb.classList.toggle('m-our', !non && ourSide === 'B');
  }
  // 「本公司」按钮：点击弹出我方主体选择器（复用桌面端 /ajax/company/options），
  // 支持小概率下切换不同主体分别填入甲/乙方；选定后写入该侧 + 自动同步签约主体下拉与
  // our_company_id + 标记我方侧。不改动收付款方向（甲乙法律地位与资金方向相互独立）。
  var selfSheetMask = document.getElementById('selfSheetMask');
  var selfSheetList = document.getElementById('selfSheetList');

  // 将选定主体填入指定侧：写名称 → 同步下拉/our_company_id → 标记我方侧；
  // 若对方侧误放了本公司同名，先清空避免两侧同名。
  function fillSelf(side, item){
    var name = (item && item.name) ? item.name : '';
    if(!name) return;
    var aEl = document.getElementById('f_party_a'), bEl = document.getElementById('f_party_b_name');
    var meEl = (side === 'A') ? aEl : bEl;         // 我方侧名称 hidden
    var otherEl = (side === 'A') ? bEl : aEl;      // 对方侧名称 hidden
    if(otherEl.value.trim() === name){ otherEl.value = ''; }  // 对方侧误放我方名 → 清空
    meEl.value = name;
    // 同步签约主体下拉与 our_company_id（下拉降级为回显/兜底）
    var sel = document.getElementById('f_company');
    if(sel && item.id != null){ sel.value = String(item.id); }
    ourSide = side;
    recomputeOurSide(); updateLabels(); applyOurSide();
    toast('已填入我方主体到' + (side === 'A' ? '甲方' : '乙方'));
  }

  // 多主体时弹出底部选择器
  function openSelfSheet(side, list){
    var h = '';
    list.forEach(function(item, i){
      var def = item.is_default ? '<span class="m-sheet-def">默认</span>' : '';
      h += '<div class="m-sheet-item" data-idx="'+i+'"><i class="bi bi-building" style="color:#3b6cff"></i><span>'+esc(item.name)+'</span>'+def+'</div>';
    });
    selfSheetList.innerHTML = h;
    selfSheetList._list = list; selfSheetList._side = side;
    selfSheetMask.classList.add('show');
    selfSheetList.querySelectorAll('.m-sheet-item').forEach(function(el){
      el.addEventListener('click', function(){
        var item = selfSheetList._list[parseInt(this.dataset.idx, 10)];
        selfSheetMask.classList.remove('show');
        fillSelf(selfSheetList._side, item);
      });
    });
  }
  // 关闭选择器：点取消或点遮罩空白处
  document.getElementById('selfSheetCancel').addEventListener('click', function(){ selfSheetMask.classList.remove('show'); });
  selfSheetMask.addEventListener('click', function(e){ if(e.target === selfSheetMask){ selfSheetMask.classList.remove('show'); } });

  // 按钮点击 → 拉取我方主体：0 个提示去配置，1 个直接填，多个弹选择器
  document.querySelectorAll('.m-party-self').forEach(function(btn){
    btn.addEventListener('click', function(){
      var side = btn.getAttribute('data-side');
      fetch('/ajax/company/options', {headers:{'X-Requested-With':'XMLHttpRequest'}})
      .then(function(r){ return r.json(); })
      .then(function(res){
        var list = (res && res.code === 0 && res.data) ? res.data : [];
        if(!list.length){ toast('尚未配置本公司主体，请前往 系统设置 → 本公司主体 添加'); return; }
        if(list.length === 1){ fillSelf(side, list[0]); }
        else { openSelfSheet(side, list); }
      })
      .catch(function(){ toast('加载本公司主体失败，请重试'); });
    });
  });
  // 收付款方向切换：仅更新方向，不联动甲乙方（反之亦然）—— 两者相互独立
  document.querySelector('select[name="direction"]').addEventListener('change', function(){
    /* 方向独立，无需改动甲乙方 */
  });
  // 初始标注（编辑态依据已有名称推断我方侧）+ v2.40.0：应用我方/对方形态与默认主体带出
  recomputeOurSide(); updateLabels(); applyOurSide(); ensureMineFilled();

  // ===== v2.40.0：对方行搜索式输入（甲方/乙方各一个搜索框，按我方身份显示为「对方」侧） =====
  // v2.46.0：签约方强制关联档案——搜索框始终可编辑（对齐 PC：名称仅可通过选择/快速新建填充，
  //          名称 hidden 只读，防自由输入绕过客户查重/共享治理）；空结果提供「快速新建」；
  //          移除「失焦手填名称」路径。
  var mPartyLinked = {A: false, B: false};
  // 编辑态已有关联档案（客户/供应商 ID>0）的侧标记 mPartyLinked=true——
  // 用户一旦手动修改该侧搜索框文本即解除关联并清空 ID（mUnlockParty），名称仅由重新选择覆盖
  ['A','B'].forEach(function(s){
    var cidEl = document.getElementById('f_party_a_cid'), sidEl = null;
    if(s === 'A'){ sidEl = document.getElementById('f_party_a_supid'); }
    else { cidEl = document.getElementById('f_party_b_cid'); sidEl = document.getElementById('f_supplier_id'); }
    if(cidEl && (parseInt(cidEl.value,10) > 0 || (sidEl && parseInt(sidEl.value,10) > 0))){
      mPartyLinked[s] = true;
    }
  });
  function mUnlockParty(side){
    mPartyLinked[side] = false;
    var ipt = document.getElementById('partySearch' + side);
    if(ipt) ipt.readOnly = false;
    var aCid = document.getElementById('f_party_a_cid'), bCid = document.getElementById('f_party_b_cid');
    var aSid = document.getElementById('f_party_a_supid'), bSid = document.getElementById('f_supplier_id');
    if(aCid) aCid.value = 0; if(bCid) bCid.value = 0;
    if(aSid) aSid.value = 0; if(bSid) bSid.value = 0;
  }
  function mPartyLinkedOk(side){
    var cid = side === 'A' ? document.getElementById('f_party_a_cid') : document.getElementById('f_party_b_cid');
    var sid = side === 'A' ? document.getElementById('f_party_a_supid') : document.getElementById('f_supplier_id');
    return !!(cid && (parseInt(cid.value,10) > 0 || (sid && parseInt(sid.value,10) > 0)));
  }
  function bindPartySearch(side){
    var input = document.getElementById('partySearch' + side);
    if(!input) return;
    var suggest = document.getElementById('partySuggest' + side);
    var nameInput = document.getElementById(side === 'A' ? 'f_party_a' : 'f_party_b_name');
    if(nameInput && nameInput.value.trim()){ input.value = nameInput.value; }  // 编辑态回显已选名称
    var timer = null;
    input.addEventListener('input', function(){
      var q = this.value.trim();
      // v2.46.0：已锁定档案后手动改输入 → 解锁并清除关联
      if(mPartyLinked[side]){ mUnlockParty(side); }
      clearTimeout(timer);
      if(q.length < 1){ hidePartySuggest(side); return; }
      timer = setTimeout(function(){
        fetch('/ajax/party/search?q=' + encodeURIComponent(q), {headers:{'X-Requested-With':'XMLHttpRequest'}})
        .then(function(r){return r.json();}).then(function(res){
          if(res.code!==0 || !res.data || !res.data.length){
            // v2.46.0：空结果 → 快速新建入口
            if(suggest){
              suggest.innerHTML = '<div class="m-party-empty">未找到匹配的客户/供应商</div>'
                + '<div class="m-party-quick" data-side="'+side+'">'
                + '<button type="button" class="m-btn m-btn-ghost" style="width:100%;justify-content:center" onclick="mOpenQuick(\''+side+'\')">'
                + '<i class="bi bi-person-plus me-1"></i>快速新建客户/供应商</button></div>';
              suggest.style.display='block';
            }
            return;
          }
          var h='';
          res.data.forEach(function(p, i){
            var tag = p.type_name ? '<span class="m-party-tag">'+esc(p.type_name)+'</span>' : '';
            h += '<div class="m-party-item" data-idx="'+i+'">'+esc(p.name)+' '+tag+'</div>';
          });
          suggest.innerHTML = h;
          suggest.style.display='block';
          suggest._list = res.data;
          suggest.querySelectorAll('.m-party-item').forEach(function(el){
            el.addEventListener('mousedown', function(e){
              e.preventDefault();
              var p = suggest._list[parseInt(this.dataset.idx,10)];
              selectParty(p, side);
            });
          });
        });
      }, 200);
    });
  }
  function hidePartySuggest(side){
    var s = document.getElementById('partySuggest' + side);
    if(s){ s.style.display='none'; s.innerHTML=''; }
  }
  // 对方选中：回填该侧名称/客户ID/供应商ID/联系人；我方侧为空自动补签约主体名
  // v2.46.0：供应商按侧落字段（甲方→party_a_supplier_id / 乙方→supplier_id），修正原甲方供应商误落乙方字段
  function selectParty(p, side){
    var nameInput = document.getElementById(side === 'A' ? 'f_party_a' : 'f_party_b_name');
    var contactInput = document.querySelector(side === 'A' ? 'input[name="party_a_contact"]' : 'input[name="party_b_contact"]');
    var aCid = document.getElementById('f_party_a_cid');
    var bCid = document.getElementById('f_party_b_cid');
    var aSid = document.getElementById('f_party_a_supid');
    var bSid = document.getElementById('f_supplier_id');
    if(nameInput) nameInput.value = p.name;
    if(contactInput) contactInput.value = p.contact_name || '';
    var cidEl = (side === 'A') ? aCid : bCid;
    var sidEl = (side === 'A') ? aSid : bSid;
    if(p.party_type === 'supplier'){
      if(sidEl) sidEl.value = p.id;
      if(aCid) aCid.value = 0;
      if(bCid) bCid.value = 0;
      // v2.46.0 修复：清空列表须排除 sidEl 自身（side=B 时 sidEl===bSid，原逻辑刚设置又被清 0）
      if(aSid && aSid !== sidEl) aSid.value = 0;
      if(bSid && bSid !== sidEl) bSid.value = 0;
    } else {
      if(cidEl) cidEl.value = p.id;
      if(sidEl) sidEl.value = 0;
      if(aCid && aCid !== cidEl) aCid.value = 0;
      if(bCid && bCid !== cidEl) bCid.value = 0;
      if(aSid) aSid.value = 0;
      if(bSid) bSid.value = 0;
    }
    var input = document.getElementById('partySearch' + side);
    if(input){ input.value = p.name; }   // v2.46.0：名称仅回显到搜索框，搜索框保持可编辑（可随时重新搜索）
    mPartyLinked[side] = true;
    hidePartySuggest(side);
    ensureMineFilled();
    toast('已选' + (p.party_type === 'customer' ? '客户' : '供应商') + '（对方）');
  }
  bindPartySearch('A'); bindPartySearch('B');
  document.addEventListener('click', function(e){
    if(!e.target.closest('.m-party-search')){ hidePartySuggest('A'); hidePartySuggest('B'); }
  });

  // ===== v2.46.0：快速新建客户/供应商（搜索无匹配时表单内快速建档） =====
  var mQuickSide = null;
  window.mOpenQuick = function(side){
    mQuickSide = side;
    document.getElementById('mQuickName').value = '';
    document.getElementById('mQuickType').value = 'customer';
    document.getElementById('mQuickContact').value = '';
    document.getElementById('mQuickMobile').value = '';
    hidePartySuggest('A'); hidePartySuggest('B');
    document.getElementById('quickSheetMask').classList.add('show');
    setTimeout(function(){ var el = document.getElementById('mQuickName'); if(el) el.focus(); }, 120);
  };
  window.mSaveQuick = function(){
    var name = (document.getElementById('mQuickName').value || '').trim();
    if(!name){ toast('请输入名称'); return; }
    var type = document.getElementById('mQuickType').value;
    var contact = (document.getElementById('mQuickContact').value || '').trim();
    var mobile = (document.getElementById('mQuickMobile').value || '').trim();
    var params = new URLSearchParams();
    params.set('name', name);
    if(contact) params.set('contact_name', contact);
    if(mobile) params.set('contact_mobile', mobile);
    apiPost(type === 'supplier' ? '/ajax/supplier/save' : '/ajax/customer/save', params.toString(),
      function(res){
        document.getElementById('quickSheetMask').classList.remove('show');
        // v2.46.0 修复：新建接口返回 {code:0,data:{id}}，须读 res.data.id（此前误读 res.id 得 undefined）
        var nid = res && res.data ? res.data.id : (res ? res.id : 0);
        selectParty({id: nid, name: name, party_type: type, contact_name: contact}, mQuickSide);
      },
      function(err){
        // 409 查重：提示去列表选择已有（含共享的）客户
        toast(err || '新建失败');
      }
    );
  };
  document.getElementById('quickSheetMask').addEventListener('click', function(e){
    if(e.target === this){ this.classList.remove('show'); }
  });

  // ===== v2.40.0：我方身份切换（默认我方=乙方）——切换「我方/对方」形态显示 =====
  function applyOurSide(){
    ['A','B'].forEach(function(side){
      var mine = document.querySelector('[data-mine="' + side + '"]');
      var other = document.querySelector('[data-other="' + side + '"]');
      var isMine = side === ourSide;
      if(mine) mine.style.display = isMine ? '' : 'none';
      if(other) other.style.display = isMine ? 'none' : '';
    });
    document.querySelectorAll('.m-seg-btn').forEach(function(b){
      b.classList.toggle('on', b.dataset.side === ourSide);
    });
    updateLabels();
    ensureMineFilled();
  }
  // 我方侧自动带出签约主体名（新建默认主体 / 编辑已有值）
  function ensureMineFilled(){
    var meEl = document.getElementById(ourSide === 'A' ? 'f_party_a' : 'f_party_b_name');
    if(!meEl) return;
    var cn = companyName();
    if(!meEl.value.trim() && cn){ meEl.value = cn; }
    var mn = document.querySelector('[data-mine-name="' + ourSide + '"]');
    if(mn) mn.textContent = meEl.value || cn || '';
  }
  // 我方身份切换控件绑定
  // v2.46.0 修复：切换我方身份时清空两侧名称/关联档案——原「对方」侧变「我方」后若沿用上一步
  // 填写的对方信息会被误当本方（用户实测）；新我方侧由 ensureMineFilled 重新带出签约主体名，
  // 新对方侧等待重新搜索选择。另修正：切换前我方侧原填的主体名不能留作对方侧。
  document.querySelectorAll('[data-action="our-side"]').forEach(function(btn){
    btn.addEventListener('click', function(){
      var newSide = btn.dataset.side;
      if(newSide === ourSide) return;
      ['A','B'].forEach(function(s){
        var nmEl = document.getElementById(s === 'A' ? 'f_party_a' : 'f_party_b_name');
        var schEl = document.getElementById('partySearch' + s);
        var aCid = document.getElementById('f_party_a_cid'), bCid = document.getElementById('f_party_b_cid');
        var aSid = document.getElementById('f_party_a_supid'), bSid = document.getElementById('f_supplier_id');
        if(nmEl) nmEl.value = '';
        if(schEl){ schEl.value = ''; schEl.readOnly = false; }
        mPartyLinked[s] = false;
        if(aCid) aCid.value = 0; if(bCid) bCid.value = 0;
        if(aSid) aSid.value = 0; if(bSid) bSid.value = 0;
      });
      ourSide = newSide;
      applyOurSide();   // 内部 ensureMineFilled 给新我方侧带出签约主体名
      toast('我方已设为' + (newSide === 'A' ? '甲方' : '乙方'));
    });
  });

  // ===== v2.40.0：更多选项折叠——已选徽章（关键词/关联项目/关联框架合同实时统计） =====
  function refreshFoldBadge(){
    var n = 0;
    var p = document.getElementById('parentIdFieldM');  if(p && parseInt(p.value,10) > 0) n++;
    var pr = document.getElementById('projectIdFieldM'); if(pr && parseInt(pr.value,10) > 0) n++;
    var kw = document.getElementById('f_keywords');      if(kw && kw.value.trim()) n++;
    var b = document.getElementById('foldBadge'), c = document.getElementById('foldCount');
    if(b && c){ b.style.display = n > 0 ? '' : 'none'; c.textContent = n; }
  }
  ['parentIdFieldM','projectIdFieldM','f_keywords'].forEach(function(fid){
    var el = document.getElementById(fid);
    if(el) new MutationObserver(refreshFoldBadge).observe(el, {attributes:true, attributeFilter:['value']});
  });
  refreshFoldBadge();

  // ===== 关联框架合同 / 关联项目：搜索选择器（2026-08-05 替代原下拉）=====
  // 输入关键字走 /ajax/contract/search?scope=framework、/ajax/project/search；
  // 输入为空时自动展示「与我有关」推荐备选；点击选中写入隐藏字段。
  function bindSearchSuggest(inputId, suggId, hiddenId, url){
    var input = document.getElementById(inputId);
    var sugg  = document.getElementById(suggId);
    var hidden= document.getElementById(hiddenId);
    if(!input || !sugg || !hidden) return;
    var timer = null;
    function doSearch(q){
      fetch(url + encodeURIComponent(q), {headers:{'X-Requested-With':'XMLHttpRequest'}})
        .then(function(r){return r.json();}).then(function(res){
          if(res.code!==0 || !res.data || !res.data.length){ hide(); return; }
          var h = '';
          res.data.forEach(function(item, i){
            var label = (item.contract_no ? item.contract_no + ' ' : '') + (item.title || item.name || '');
            var sub = item.code ? ' <small style="color:var(--m-text-3)">'+esc(item.code)+'</small>' : '';
            var my = item.my ? '<span class="m-party-tag">与我有关</span>' : '';
            h += '<div class="m-party-item" data-idx="'+i+'"><i class="bi '+(item.contract_no?'bi-file-text':'bi-folder2')+'"></i><span class="flex-grow-1">'+esc(label)+sub+'</span>'+my+'</div>';
          });
          sugg.innerHTML = h;
          sugg.style.display = 'block';
          sugg._list = res.data;
          sugg.querySelectorAll('.m-party-item').forEach(function(el){
            el.addEventListener('mousedown', function(e){
              e.preventDefault();
              var it = sugg._list[parseInt(this.dataset.idx,10)];
              input.value = (it.contract_no ? it.contract_no + ' ' : '') + (it.title || it.name || '');
              hidden.value = it.id;
              hide();
            });
          });
        }).catch(function(){ hide(); });
    }
    function hide(){ sugg.style.display='none'; sugg.innerHTML=''; }
    input.addEventListener('input', function(){
      var q = this.value.trim();
      clearTimeout(timer);
      if(q.length < 1){ timer = setTimeout(function(){ doSearch(''); }, 120); return; }
      timer = setTimeout(function(){ doSearch(q); }, 200);
    });
    input.addEventListener('focus', function(){
      if(!sugg.style.display || sugg.style.display === 'none') doSearch(input.value.trim());
    });
    document.addEventListener('click', function(e){
      if(!e.target.closest('.m-party-search')) hide();
    });
    if(hidden.value > 0) doSearch('');
  }
  bindSearchSuggest('parentSearchM', 'parentSuggestM', 'parentIdFieldM', '/ajax/contract/search?scope=framework&q=');
  bindSearchSuggest('projectSearchM', 'projectSuggestM', 'projectIdFieldM', '/ajax/project/search?q=');

  // ===== 附件上传（附件预览优化：进度条 + 重试 + 友好错误提示 + 大小/数量上限） =====
  var uploaded = [];
  var UPLOAD_MAX_TOTAL = 30;    // 单次最多 30 个附件
  var UPLOAD_MAX_SIZE = 20 * 1024 * 1024;  // 单个 20MB
  (function(){
    var existing = document.getElementById('f_file_url').value;
    if(existing){ try{ uploaded = JSON.parse(existing); }catch(e){ uploaded=[]; } renderUploads(); }
  })();
  function renderUploads(){
    var box = document.getElementById('uploadList');
    // 2026-08-05 修复：只重建成功项、保留错误项（此前 box.innerHTML 整体重建会清掉「上传失败」提示）
    box.querySelectorAll('.m-up-done').forEach(function(el){ el.remove(); });
    updateFileField();
    if(!uploaded.length) return;
    var ic = {pdf:'bi-file-pdf text-danger', doc:'bi-file-word text-primary', docx:'bi-file-word text-primary',
      xls:'bi-file-excel text-success', xlsx:'bi-file-excel text-success',
      jpg:'bi-file-image text-warning', jpeg:'bi-file-image text-warning', png:'bi-file-image text-warning',
      gif:'bi-file-image text-warning', webp:'bi-file-image text-warning', txt:'bi-file-text text-secondary'};
    var h='';
    uploaded.forEach(function(f,i){
      var ext=(f.name.split('.').pop()||'').toLowerCase();
      var icon=ic[ext]||'bi-file-earmark text-secondary';
      var sz = f.size ? (f.size<1024?f.size+'B':(f.size<1048576?Math.round(f.size/1024)+'KB':(f.size/1048576).toFixed(1)+'MB')) : '';
      h += '<div class="m-upload-item m-up-done">'
          +'<i class="bi '+icon+'"></i>'
          +'<span class="m-up-name">'+esc(f.name)+(sz?' <small style="color:var(--m-text-3);font-weight:400">· '+sz+'</small>':'')+'</span>'
          +'<button type="button" class="m-up-del" data-i="'+i+'" title="删除">&times;</button>'
        +'</div>';
    });
    box.insertAdjacentHTML('afterbegin', h);
    box.querySelectorAll('.m-up-del').forEach(function(b){
      b.addEventListener('click', function(){ uploaded.splice(parseInt(this.dataset.i,10),1); renderUploads(); });
    });
  }
  function updateFileField(){ document.getElementById('f_file_url').value = JSON.stringify(uploaded); }
  function handleFiles(files, inputEl){
    if(!files || !files.length){ inputEl.value=''; return; }
    // 数量上限
    if(uploaded.length + files.length > UPLOAD_MAX_TOTAL){
      toast('附件最多 '+UPLOAD_MAX_TOTAL+' 个，当前已有 '+uploaded.length+' 个', 'warning');
      inputEl.value=''; return;
    }
    for(var i=0;i<files.length;i++) uploadFile(files[i], 0);
    inputEl.value=''; // 清空以便相同文件可再次选择
  }
  /** 单文件上传（附带回退重试 1 次） */
  function uploadFile(file, retryCnt){
    retryCnt = retryCnt || 0;
    var ext=(file.name.split('.').pop()||'').toLowerCase();
    var allowed=['pdf','doc','docx','xls','xlsx','jpg','jpeg','png'];
    if(allowed.indexOf(ext)===-1){ toast('不支持的格式：.'+ext+'（仅 PDF/Word/Excel/JPG/PNG）'); return; }
    if(file.size > UPLOAD_MAX_SIZE){
      var szMB = (file.size/1048576).toFixed(1);
      toast('「'+file.name+'」（'+szMB+'MB）超过 20MB 上限，请压缩后再上传', 'warning');
      return;
    }
    var fd=new FormData(); fd.append('file', file);
    var itemId='up_'+Math.random().toString(36).slice(2,9);
    var box=document.getElementById('uploadList');
    var item=document.createElement('div');
    item.id=itemId; item.className='m-upload-item m-up-progress';
    var szText = file.size ? (file.size<1024?file.size+'B':(file.size<1048576?Math.round(file.size/1024)+'KB':(file.size/1048576).toFixed(1)+'MB')) : '';
    item.innerHTML =
      '<div class="m-up-head">' +
        '<i class="bi bi-cloud-arrow-up text-primary"></i>' +
        '<span class="m-up-name">'+esc(file.name)+(szText?' <small style="color:var(--m-text-3);font-weight:400">· '+szText+'</small>':'')+'</span>' +
        '<button type="button" class="m-up-del" title="取消上传" data-cancel="1">&times;</button>' +
      '</div>' +
      '<div class="m-up-bar"><div class="m-up-bar-fill" style="width:0%"></div><span class="m-up-bar-text">0%</span></div>' +
      '<div class="m-up-status">准备上传…</div>';
    box.appendChild(item);
    var cancelBtn = item.querySelector('[data-cancel]');
    var aborted = {flag:false};

    // XHR 支持 progress（fetch 不支持）
    var xhr = new XMLHttpRequest();
    if(cancelBtn){ cancelBtn.addEventListener('click', function(){ aborted.flag = true; try{xhr.abort();}catch(e){} removeUploadItem(itemId); }); }
    xhr.open('POST', '/ajax/upload/contract');
    xhr.setRequestHeader('X-CSRF-TOKEN', csrfToken());
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    xhr.upload.addEventListener('progress', function(e){
      if(aborted.flag) return;
      if(e.lengthComputable){
        var pct = Math.min(99, Math.round(e.loaded*100/e.total));
        var bar = item.querySelector('.m-up-bar-fill');
        var txt = item.querySelector('.m-up-bar-text');
        var st  = item.querySelector('.m-up-status');
        if(bar) bar.style.width = pct + '%';
        if(txt) txt.textContent = pct + '%';
        if(st)  st.textContent = '上传中… ' + pct + '%';
      }
    });
    xhr.addEventListener('load', function(){
      if(aborted.flag) return;
      var res = null;
      try { res = JSON.parse(xhr.responseText); } catch(e){ res = {code:500,msg:'服务器返回格式异常'}; }
      if(res && res.code===0){
        uploaded.push(res.data);
        // 2026-08-10 修复：上传成功须先移除进度条目（此前漏删，renderUploads 只重建 .m-up-done 成功项，
        // 导致每条上传都残留一条卡在「上传中… 99%」的幽灵条目，与成功项重复显示）
        removeUploadItem(itemId);
        renderUploads();
      } else {
        // 回退重试 1 次
        if(retryCnt < 1){
          var st2 = item.querySelector('.m-up-status');
          if(st2) st2.textContent = '上传失败，自动重试…';
          setTimeout(function(){ if(!aborted.flag) uploadFile(file, retryCnt+1); removeUploadItem(itemId); }, 800);
        } else {
          item.className = 'm-upload-item m-up-error';
          var msg = res && res.msg ? res.msg : '上传失败';
          item.innerHTML =
            '<div class="m-up-head">' +
              '<i class="bi bi-exclamation-triangle text-danger"></i>' +
              '<span class="m-up-name text-danger">'+esc(file.name)+'：'+esc(msg)+'</span>' +
              '<button type="button" class="m-up-del" title="删除">&times;</button>' +
            '</div>' +
            '<div class="m-up-error-actions">' +
              '<button type="button" class="m-btn m-btn-ghost m-btn-sm" onclick="var f=\''+btoa(unescape(encodeURIComponent(file.name))).replace(/=/g,'')+'\'; /* placeholder */">重试</button>' +
              '<button type="button" class="m-btn m-btn-ghost m-btn-sm" onclick="this.closest(\'.m-upload-item\').remove()">移除</button>' +
            '</div>';
          var retryBtn = item.querySelector('.m-up-error-actions button');
          if(retryBtn){
            (function(f){ retryBtn.onclick = function(){ removeUploadItem(itemId); uploadFile(f, 0); }; })(file);
          }
          var delBtn = item.querySelector('.m-up-head .m-up-del');
          if(delBtn){ delBtn.addEventListener('click', function(){ item.remove(); }); }
        }
      }
    });
    xhr.addEventListener('error', function(){
      if(aborted.flag) return;
      if(retryCnt < 1){
        var st3 = item.querySelector('.m-up-status');
        if(st3) st3.textContent = '网络异常，自动重试…';
        setTimeout(function(){ if(!aborted.flag) uploadFile(file, retryCnt+1); removeUploadItem(itemId); }, 800);
      } else {
        item.className = 'm-upload-item m-up-error';
        item.innerHTML =
          '<div class="m-up-head">' +
            '<i class="bi bi-wifi-off text-danger"></i>' +
            '<span class="m-up-name text-danger">'+esc(file.name)+'：网络错误</span>' +
            '<button type="button" class="m-up-del">&times;</button>' +
          '</div>' +
          '<div class="m-up-error-actions">' +
            '<button type="button" class="m-btn m-btn-ghost m-btn-sm">重试</button>' +
            '<button type="button" class="m-btn m-btn-ghost m-btn-sm" onclick="this.closest(\'.m-upload-item\').remove()">移除</button>' +
          '</div>';
        var rbt = item.querySelector('.m-up-error-actions button');
        if(rbt){ (function(f){ rbt.onclick = function(){ removeUploadItem(itemId); uploadFile(f, 0); }; })(file); }
        var db = item.querySelector('.m-up-head .m-up-del');
        if(db){ db.addEventListener('click', function(){ item.remove(); }); }
      }
    });
    xhr.addEventListener('loadstart', function(){
      var st = item.querySelector('.m-up-status');
      if(st) st.textContent = '上传中…';
    });
    try { xhr.send(fd); }
    catch(err){
      if(retryCnt < 1){ setTimeout(function(){ uploadFile(file, retryCnt+1); removeUploadItem(itemId); }, 600); }
    }
  }
  function removeUploadItem(id){
    var el = document.getElementById(id);
    if(el) el.remove();
  }
  // 绑定文件选择入口（文档 + 图片 + 拍照直传，共用同一个上传处理）
  document.getElementById('fileDocInput').addEventListener('change', function(){ handleFiles(this.files, this); });
  document.getElementById('fileImgInput').addEventListener('change', function(){ handleFiles(this.files, this); });
  // P1 补齐：拍照直传（capture=environment）—— 复用同一上传处理
  var camInput = document.getElementById('fileCameraInput');
  if(camInput){ camInput.addEventListener('change', function(){ handleFiles(this.files, this); }); }

  // ===== 提交 =====
  // P3-8【m-F3】离页保护（中文 mConfirm 拦截，替代原生 beforeunload 英文弹窗）
  var contractFormSubmitted = false;
  var contractFormDirty = false;
  // 表单任意输入/选择即标记“未提交脏数据”
  var _cfForm = document.getElementById('form');
  if(_cfForm){
    _cfForm.addEventListener('input', function(){ contractFormDirty = true; });
    _cfForm.addEventListener('change', function(){ contractFormDirty = true; });
  }
  // 统一离页确认：确定则放行，取消则停留（替代无法中文化的原生 beforeunload 弹窗）
  function _confirmLeave(go){
    // mConfirm 的“确定”回调不传参（onOk()），故不可依赖 ok 判断；点确定即“确认离开”
    mConfirm('当前合同尚未提交，离开后已填写的内容将丢失。确定要离开吗？', function(){
      contractFormDirty = false; go();
    });
  }
  // 1) 浏览器/系统“返回”手势：pushState 哨兵 + popstate 拦截，弹出中文确认框
  history.pushState({_cfGuard:true}, '');
  window.addEventListener('popstate', function(){
    if(contractFormSubmitted || !contractFormDirty){ return; }
    history.pushState({_cfGuard:true}, ''); // 取消返回，停留在当前页
    _confirmLeave(function(){ window.location.href = document.referrer || '/m/contracts'; });
  });
  // 2) 底部 Tab 跳转：拦截 .m-tabbar 内的导航链接
  document.addEventListener('click', function(e){
    if(contractFormSubmitted || !contractFormDirty){ return; }
    // 拦截两类“离开”入口：底部 Tab 栏链接 + 顶部“返回”箭头（否则直接跳转会静默丢失未保存数据）
    var a = e.target.closest ? e.target.closest('.m-tabbar a, .m-nav a.back') : null;
    if(!a){ return; }
    var href = a.getAttribute('href');
    if(!href || href.charAt(0) !== '/'){ return; }
    e.preventDefault();
    e.stopPropagation();
    _confirmLeave(function(){ window.location.href = href; });
  }, true);
  // P2：必填校验失败——字段标红 + 滚动到可视区（保留原有 toast 提示；输入/选择时自动清除红框）
  function mFlagReq(el){
    if(!el) return;
    try{ el.style.borderColor = '#fa5151'; el.scrollIntoView({block:'center'}); el.focus(); }catch(e){}
  }
  // 重新输入/选择时清除标红（表单级事件委托，覆盖全部输入控件）
  (function(){
    var f = document.getElementById('form');
    if(!f) return;
    ['input','change'].forEach(function(ev){
      f.addEventListener(ev, function(e){ if(e.target && e.target.style) e.target.style.borderColor = ''; });
    });
  })();

  document.getElementById('submitBtn').addEventListener('click', function(){
    var title = document.getElementById('f_title').value.trim();
    var content = document.getElementById('f_content').value.trim();
    if(!title){ mFlagReq(document.getElementById('f_title')); toast('请输入合同标题'); return; }
    if(!content){ mFlagReq(document.getElementById('f_content')); toast('请输入合同概要'); return; }
    if(uploaded.length === 0){ toast('请上传至少一个合同附件'); return; }
    // v2.40.0：合同性质 radio 无默认，必须显式选择（避免默认值错填）
    var tv = currentTrade();
    if(tv === ''){ toast('请选择合同性质（交易/非交易）'); return; }
    // 我方侧兜底带出签约主体名（必填保障）
    ensureMineFilled();

    // 新建必填校验：遍历带 required 的字段，空则提示并阻止提交（编辑态无 required 属性，自动跳过）
    var reqs = document.querySelectorAll('#form [required]');
    for(var ri=0; ri<reqs.length; ri++){
      var nm = reqs[ri].getAttribute('name');
      if(tv === '0' && (nm === 'direction' || nm === 'amount')) continue;  // 非交易：方向/金额不参与必填校验
      var rv = (reqs[ri].value || '').trim();
      if(!rv){
        var lbl = '必填项';
        var fld = reqs[ri].closest('.m-field');
        if(fld){ var lb = fld.querySelector('label'); if(lb) lbl = (lb.textContent||'').replace('*','').trim() || lbl; }
        mFlagReq(reqs[ri]);
        toast('请填写：' + lbl);
        return;
      }
    }

    kwFlush();   // 关键词：把未回车的残留文本也收进标签，确保写入 name=keywords
    // v2.46.0：新建时对方侧（非我方侧）必须已关联客户/供应商档案（编辑旧数据不追溯）
    var fId = document.querySelector('#form input[name=id]');
    if((!fId || !fId.value) && typeof mPartyLinkedOk === 'function'){
      var oppo = (ourSide === 'A') ? 'B' : 'A';
      if(!mPartyLinkedOk(oppo)){
        toast((oppo === 'A' ? '甲方' : '乙方') + '请从已登记客户/供应商中选择（未登记可点「快速新建」）');
        var si = document.getElementById('partySearch' + oppo);
        if(si){ try{ si.focus(); }catch(e){} }
        return;
      }
    }
    var form = document.getElementById('form');
    var params = new URLSearchParams(new FormData(form));
    if(tv === '0'){ params.set('trade_attr', '0'); params.set('amount', '0'); params.set('direction', ''); }
    else { params.set('trade_attr', '1'); }
    // v2.46.0：我方身份始终提交（非交易合同也需判定对方侧做强制关联校验）
    params.set('our_side', ourSide);
    var btn = this; btn.disabled = true; btn.innerHTML = '提交中…';
    // N-m1：改用 mobile-common.js 的 apiPost 统一兜底（自动带 CSRF；返回码≠0 / 网络异常统一走 onError）
    apiPost('/ajax/contract/save', params.toString(),
      function(){ contractFormSubmitted = true; toast('保存成功'); setTimeout(function(){ location.href = '/m/contracts'; }, 700); },
      // P2-5【M-F3】失败（码≠0/网络异常）时按 $is_edit 还原正确文案，不误显"创建合同"
      function(err){ btn.disabled=false; btn.innerHTML='<i class="bi bi-check-lg"></i> <?=!empty($is_edit)?'保存修改':'创建合同'?>'; toast(err || '保存失败'); }
    );
  });

  /* M9：乙方选择客户后，加载该客户联系人供下拉选择，选中后回填乙方联系人/电话 */
  function mOnPartyBContactPick(){
    var sel = document.getElementById('mPartyBContactSelect');
    var txt = document.getElementById('mPartyBContact');
    if(sel && sel.value){ try{ var c = JSON.parse(decodeURIComponent(sel.value)); txt.value = c.name + (c.phone?(' / '+c.phone):''); }catch(e){} }
  }
  (function(){
    function loadMPartyBContacts(custId){
      var sel = document.getElementById('mPartyBContactSelect');
      if(!sel) return;
      if(!custId || custId <= 0){ sel.style.display='none'; sel.innerHTML='<option value="">— 手动输入 —</option>'; return; }
      fetch('/ajax/customer/'+custId+'/contacts', { headers: { 'X-Requested-With':'XMLHttpRequest' } })
        .then(function(r){ return r.json(); }).then(function(res){
          var list = (res.data)||[];
          sel.innerHTML = '<option value="">— 手动输入 —</option>' + list.map(function(c){
            var label = c.name + (c.phone?(' / '+c.phone):'') + (c.is_primary==1?'(主)':'');
            return '<option value="'+encodeURIComponent(JSON.stringify(c))+'">'+label.replace(/</g,'&lt;')+'</option>';
          }).join('');
          sel.style.display = list.length ? 'block' : 'none';
        }).catch(function(){ sel.style.display='none'; });
    }
    var cidEl = document.getElementById('f_party_b_cid');
    if(cidEl){
      new MutationObserver(function(){ loadMPartyBContacts(parseInt(cidEl.value,10)||0); }).observe(cidEl, { attributes:true, attributeFilter:['value'] });
      var init = parseInt(cidEl.value,10)||0;
      if(init > 0) loadMPartyBContacts(init);
    }
  })();
})();
</script>
<?php $tab = 'contract'; include __DIR__ . '/_foot.php'; ?>
