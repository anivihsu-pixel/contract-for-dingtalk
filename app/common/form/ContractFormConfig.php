<?php
namespace app\common\form;

/**
 * 合同表单字段配置（v2.38.3+）
 *
 * PC / 移动端共享的【单源】字段定义。新增 / 修改 / 调整顺序字段，只改此处，
 * 不再改 contract/create.php 与 mobile/contract_form.php 两套 HTML（两视图均按本配置 foreach 渲染）。
 *
 * 字段元数据：
 *  - name        表单字段名（提交 key）
 *  - label       显示标签
 *  - type        text|number|date|textarea|select|switch|hidden|keywords|upload|party_name|party_search|company
 *  - group       逻辑分组（basic/party/terms/attach/aux），用于选项与渲染归类
 *  - required    新建时是否必填（编辑态不追溯）
 *  - options_key 选项来源（controller 注入的 maps 中的 key，如 categories/projects）
 *  - options     内联选项数组（如 direction）
 *  - default     默认值
 *  - show_when   "field=value" 条件显示（如 trade_attr=1 才显示方向/金额）
 *  - hide        "field1,field2" 当本字段为「非交易」时隐藏这些字段（仅 trade_attr 用）
 *  - col_pc      Bootstrap 栅格列宽（12/6/3）
 *  - pc_step     所属向导步骤（1 / 2）
 *  - m_sec       移动端所属分区标题（空串表示不输出分区标题）
 *  - pc_id       指定 PC 端元素 id（兼容既有 JS）
 *  - m_id        指定移动端元素 id（兼容既有 JS）
 *  - placeholder / help / switch_label 等展示辅助
 *  - skip_mobile 移动端不渲染（PC 专属隐藏字段）
 */
class ContractFormConfig
{
    public static function fields(): array
    {
        // 数组顺序即【移动端】视觉顺序；pc_step 控制【PC 端】向导步骤归属。
        // 2026-08-05 重排：移动端新建合同「合同概要 / 合同附件」下移（先填核心必填信息），
        // 关键词 / 关联项目收进「更多选项」折叠面板（m_fold）。
        return [
            // ───────── 顶部核心（移动端首屏仅标题） ─────────
            ['name'=>'title','label'=>'合同标题','type'=>'text','group'=>'basic','required'=>true,
             'placeholder'=>'请输入合同标题','col_pc'=>12,'pc_step'=>1,'m_sec'=>'','m_id'=>'f_title'],

            // ───────── 对方信息 ─────────
            ['name'=>'party_a_name','label'=>'甲方名称','type'=>'party_name','group'=>'party','required'=>true,
             'side'=>'A','placeholder'=>'甲方名称','col_pc'=>6,'pc_step'=>2,'m_sec'=>'对方信息',
             'pc_id'=>'partyAName','m_id'=>'f_party_a'],
            ['name'=>'party_a_contact','label'=>'甲方联系人','type'=>'text','group'=>'party',
             'placeholder'=>'选填','col_pc'=>3,'pc_step'=>2,'m_sec'=>'对方信息','pc_id'=>'partyAContact','m_id'=>'f_party_a_contact'],
            // v2.47.x：甲方电话独立字段（原与联系人合并填写，对齐移动端拆分）
            ['name'=>'party_a_phone','label'=>'甲方电话','type'=>'text','group'=>'party',
             'placeholder'=>'选填','col_pc'=>3,'pc_step'=>2,'m_sec'=>'对方信息','pc_id'=>'partyAPhone','m_id'=>'f_party_a_phone'],
            // v2.40.0：甲方客户ID——移动端「我方=乙方」时对方为甲方，客户关联落此字段（原 skip_mobile 移除）；
            // m_sec 保持「对方信息」防止移动端分区标题断档重复输出
            ['name'=>'party_a_customer_id','label'=>'甲方客户ID','type'=>'hidden','group'=>'party','pc_step'=>2,'m_sec'=>'对方信息','m_id'=>'f_party_a_cid','pc_id'=>'partyACustId'],
            // v2.46.0：甲方供应商（我方=乙方、对方=甲方为供应商的采购合同；签约方强制关联档案）
            ['name'=>'party_a_supplier_id','label'=>'甲方供应商ID','type'=>'hidden','group'=>'party','pc_step'=>2,'m_sec'=>'对方信息','m_id'=>'f_party_a_supid','pc_id'=>'partyASupplierId'],
            ['name'=>'party_a_type','label'=>'甲方类型','type'=>'hidden','group'=>'party','pc_step'=>2,'m_sec'=>'','skip_mobile'=>true,'pc_id'=>'partyAType'],
            ['name'=>'party_b_name','label'=>'乙方名称','type'=>'party_name','group'=>'party','required'=>true,
             'side'=>'B','placeholder'=>'乙方名称','col_pc'=>6,'pc_step'=>2,'m_sec'=>'对方信息',
             'pc_id'=>'partyBName','m_id'=>'f_party_b_name'],
            ['name'=>'party_b_contact','label'=>'乙方联系人','type'=>'text','group'=>'party',
             'placeholder'=>'选填','col_pc'=>3,'pc_step'=>2,'m_sec'=>'对方信息','pc_id'=>'partyBContact','m_id'=>'mPartyBContact'],
            // v2.47.x：乙方电话独立字段（原与联系人合并填写，对齐移动端拆分）
            ['name'=>'party_b_phone','label'=>'乙方电话','type'=>'text','group'=>'party',
             'placeholder'=>'选填','col_pc'=>3,'pc_step'=>2,'m_sec'=>'对方信息','pc_id'=>'partyBPhone','m_id'=>'f_party_b_phone'],
            ['name'=>'party_b_customer_id','label'=>'搜索对方','type'=>'party_search','group'=>'party',
             'party_type'=>'customer','col_pc'=>6,'pc_step'=>2,'m_sec'=>'对方信息',
             'pc_id'=>'partyBCustId','m_id'=>'f_party_b_cid'],
            ['name'=>'supplier_id','label'=>'供应商','type'=>'hidden','group'=>'party','pc_step'=>2,'m_sec'=>'对方信息',
             'pc_id'=>'partyBSupplierId','m_id'=>'f_supplier_id'],
            ['name'=>'our_side','label'=>'我方身份','type'=>'hidden','group'=>'party','pc_step'=>2,'m_sec'=>'对方信息',
             'm_id'=>'f_our_side','pc_id'=>'ourSideField'],

            // ───────── 更多信息（交易属性） ─────────
            ['name'=>'business_type','label'=>'业务类型','type'=>'select','group'=>'basic','required'=>true,
             'options_key'=>'business_types','default'=>'','col_pc'=>4,'pc_step'=>1,'m_sec'=>'更多信息',
             'help'=>'关联项目时自动带入；提交审批后锁定',
             'pc_id'=>'businessTypeSelect','m_id'=>'f_business_type'],
            ['name'=>'trade_attr','label'=>'合同性质','type'=>'trade_attr','group'=>'basic',
             'switch_label'=>'交易合同（计入收支）','default'=>1,'pc_step'=>1,'m_sec'=>'更多信息',
             'pc_id'=>'tradeAttr','m_id'=>'f_trade_attr','hide'=>'direction,amount',
             'help'=>'交易合同计入财务收支统计，非交易合同仅存档'],
            ['name'=>'direction','label'=>'收付款方向','type'=>'select','group'=>'basic','required'=>true,
             'options'=>['sales'=>'销售（我方收款）','purchase'=>'采购（我方付款）'],'default'=>'sales',
             'show_when'=>'trade_attr=1','col_pc'=>4,'pc_step'=>1,'m_sec'=>'更多信息',  // 2026-08-03: 3->4 长文本不截断
             'pc_id'=>'directionSelect','row_id'=>'directionRow','m_row_id'=>'directionField'],
            ['name'=>'our_company_id','label'=>'签约主体','type'=>'company','group'=>'basic','required'=>true,
             'col_pc'=>4,'pc_step'=>1,'m_sec'=>'更多信息','pc_id'=>'companySelect','m_id'=>'f_company'],
            ['name'=>'amount','label'=>'金额（元）','type'=>'number','group'=>'basic','required'=>true,
             'step'=>'0.01','show_when'=>'trade_attr=1','col_pc'=>3,'pc_step'=>1,'m_sec'=>'金额与期限',
             'm_id'=>'f_amount','row_id'=>'amountField','m_row_id'=>'amountField'],
            // ───────── 条款与期限（v2.40.0：移动端移出到「金额与期限」独立区，日期必填不设默认值） ─────────
            ['name'=>'effective_date','label'=>'生效日期','type'=>'date','group'=>'terms','required'=>true,
             'col_pc'=>6,'pc_step'=>2,'m_sec'=>'金额与期限', 'm_id'=>'effective_date'],
            ['name'=>'expiry_date','label'=>'到期日期','type'=>'date','group'=>'terms','required'=>true,
             'col_pc'=>6,'pc_step'=>2,'m_sec'=>'金额与期限', 'm_id'=>'expiry_date'],

            // ───────── 合同概要 / 附件（移动端：概要在前、附件在后，紧跟标题形成「合同主体」区） ─────────
            ['name'=>'content','label'=>'合同概要','type'=>'textarea','group'=>'terms','required'=>true,
             'rows'=>6,'m_rows'=>3,
             'placeholder'=>'请输入合同核心条款摘要、关键事项及特殊约定…',
             'm_placeholder'=>'合同具体内容以附件为准，可简要说明或填"详见附件"',
             'col_pc'=>12,'pc_step'=>2,'m_sec'=>'合同概要','m_id'=>'f_content',
             'help'=>'简要描述合同的核心内容，例如：金额条款、付款方式、交付时间、违约责任等关键约定'],
            ['name'=>'file_url','label'=>'合同附件','type'=>'upload','group'=>'attach','required'=>true,
             'col_pc'=>12,'pc_step'=>2,'m_sec'=>'合同附件'],

            // ───────── 备注（v2.40.0：移动端移除，PC 端保留） ─────────
            ['name'=>'remark','label'=>'备注','type'=>'textarea','group'=>'attach','skip_mobile'=>true,
             'rows'=>3,'placeholder'=>'选填，内部备注','col_pc'=>12,'pc_step'=>2,'m_sec'=>''],

            // ───────── 更多选项（移动端默认折叠：不常用字段） ─────────
            ['name'=>'keywords','label'=>'关键词','type'=>'keywords','group'=>'aux',
             'placeholder'=>'输入关键词后回车添加','col_pc'=>6,'pc_step'=>1,'m_sec'=>'','m_fold'=>true],
            ['name'=>'project_id','label'=>'关联项目','type'=>'project_search','group'=>'aux',
             'col_pc'=>3,'pc_step'=>1,'m_sec'=>'','m_fold'=>true],

            // ───────── 系统隐藏字段 ─────────
            ['name'=>'contract_no','label'=>'合同编号','type'=>'hidden','group'=>'basic','pc_step'=>1,'m_sec'=>''],
            ['name'=>'status','label'=>'状态','type'=>'hidden','group'=>'basic','default'=>'DRAFT','pc_step'=>1,'m_sec'=>''],
            ['name'=>'owner_id','label'=>'负责人','type'=>'hidden','group'=>'basic','pc_step'=>1,'m_sec'=>''],
            ['name'=>'dept_id','label'=>'部门','type'=>'hidden','group'=>'basic','pc_step'=>1,'m_sec'=>''],
        ];
    }

    /** 分组顺序（用于渲染归类） */
    public static function groups(): array
    {
        return [
            'basic'  => '基本信息',
            'party'  => '签约方',
            'terms'  => '条款与期限',
            'attach' => '合同附件',
            'aux'    => '辅助信息',
        ];
    }

    /** 按分组聚合字段 */
    public static function grouped(): array
    {
        $groups = [];
        foreach (self::groups() as $key => $label) {
            $groups[$key] = ['label' => $label, 'fields' => []];
        }
        foreach (self::fields() as $f) {
            $g = $f['group'] ?? 'basic';
            $groups[$g]['fields'][] = $f;
        }
        return $groups;
    }

    /** 解析选项：options_key 从注入的 maps 取，否则用内联 options */
    public static function optionList(array $f, array $maps): array
    {
        if (isset($f['options']) && is_array($f['options'])) {
            return self::normalizeOptions($f['options']);
        }
        $key = $f['options_key'] ?? '';
        if ($key && isset($maps[$key]) && is_array($maps[$key])) {
            return self::normalizeOptions($maps[$key]);
        }
        return [];
    }

    /**
     * 选项归一化：
     *  - 关联数组（code/value => label，如 categories、内联 options）原样返回；
     *  - 顺序数组的行列表（如 ProjectLogic::options 返回的 [['id','name','code']]）转为 id => name，
     *    避免把数组直接喂给 htmlspecialchars 触发 "Array to string conversion"。
     */
    private static function normalizeOptions(array $opts): array
    {
        if (!array_is_list($opts)) {
            return $opts; // 已是 code=>label 映射
        }
        $out = [];
        foreach ($opts as $row) {
            if (is_array($row)) {
                $v = $row['id'] ?? $row['code'] ?? $row['value'] ?? null;
                $l = $row['name'] ?? $row['title'] ?? $row['label'] ?? $row['text'] ?? null;
                if ($v !== null) {
                    $out[$v] = ($l !== null) ? $l : $v;
                }
            } else {
                // M13 修复：纯标量列表用标量自身作 value（此前 $out[]=$row 生成 0,1… 下标作 value，
                // select 选中/提交错乱），value=label=标量自身
                $out[$row] = $row;
            }
        }
        return $out;
    }

    /** 移动端分区标题集合（用于去重输出） */
    public static function mobileSections(): array
    {
        $secs = [];
        foreach (self::fields() as $f) {
            if (!empty($f['skip_mobile'])) continue;
            $s = $f['m_sec'] ?? '';
            if ($s !== '' && !in_array($s, $secs, true)) $secs[] = $s;
        }
        return $secs;
    }

    // =================== 渲染器 ===================

    private static function h($s): string
    {
        return htmlspecialchars((string)($s ?? ''), ENT_QUOTES);
    }

    /**
     * PC 端字段 HTML 生成入口：按 config 顺序遍历，自动处理向导步骤(step1/step2)与分区分隔。
     * @param array $contract 编辑态字段值
     * @param bool  $isNew    是否新建（控制 required）
     * @param array $maps     ['categories'=>code=>name,'companies'=>[id=>name],'projects'=>[id=>name]]
     * @param int   $defaultCompanyId
     */
    public static function pcRenderAll(array $contract, bool $isNew, array $maps, int $defaultCompanyId = 0): string
    {
        $out = '';
        foreach ([1, 2] as $step) {
            $out .= '<div id="step' . $step . '" class="wizard-step row g-3"' . ($step > 1 ? ' style="display:none"' : '') . '>';
            $out .= self::pcFieldsHtml($step, $contract, $isNew, $maps, $defaultCompanyId);
            $out .= '</div>';
        }
        return $out;
    }

    /**
     * 仅渲染指定向导步骤内的字段 + 分区分隔（不含步骤外层 div，由视图控制，便于保留既有脚本位置）。
     */
    public static function pcFieldsHtml(int $step, array $contract, bool $isNew, array $maps, int $defaultCompanyId = 0): string
    {
        $reqMark = $isNew ? ' <span class="text-danger">*</span>' : '';
        $reqAttr = $isNew ? ' required' : '';
        $out = '';
        $lastGroup = '';
        foreach (self::fields() as $f) {
            if ((int)($f['pc_step'] ?? 1) !== $step) continue;
            $group = $f['group'] ?? 'basic';
            if ($group !== $lastGroup) {
                $out .= self::pcGroupDivider($group, $contract, $maps);
                $lastGroup = $group;
            }
            // PC 端「合同概要」独立小分区（2026-08-05：概要/附件从 step2 首屏下移后的排版优化）
            // v2.44.4：参考资料库按钮从「辅助信息」分组移到此处——起草合同概要时参考范本随手可取（右对齐小按钮）
            if (($f['name'] ?? '') === 'content' && $step === 2) {
                $out .= '<div class="col-12"><div class="border-top pt-2 mt-1 mb-1 d-flex justify-content-between align-items-center text-muted small">'
                    . '<span>合同概要</span>'
                    . '<button type="button" class="btn btn-sm btn-outline-info" onclick="openResourceModal()" title="参考合同范本/开票资料拟定概要">'
                    . '<i class="bi bi-folder2-open me-1"></i> 参考资料库</button></div></div>';
            }
            $out .= self::pcFieldHtml($f, $contract, $isNew, $reqMark, $reqAttr, $maps, $defaultCompanyId);
        }
        return $out;
    }

    private static function pcGroupDivider(string $group, array $contract, array $maps): string
    {
        if ($group === 'aux') {
            $h = '<div class="col-12"><div class="border-top pt-2 mt-1 mb-2 text-muted small">辅助信息</div></div>';
            return $h;
        }
        if ($group === 'party') {
            // 2026-08-05：step2 首屏分组标题（原无标题，字段直接平铺）
            // v2.45：增加「我方身份」分段引导（与移动端 my|our 语义对齐，默认我方=乙方，切换时由 create.php JS 带出主体名）
            return '<div class="col-12"><div class="border-top pt-2 mt-1 mb-1 text-muted small">对方信息</div>'
                . '<div class="d-flex align-items-center gap-2 mb-3"><span class="text-muted small">我方身份</span>'
                . '<div class="btn-group btn-group-sm" role="group" aria-label="我方身份">'
                . '<input type="radio" class="btn-check" name="pc_our_side_radio" id="pcOurSideB" value="B" autocomplete="off" checked>'
                . '<label class="btn btn-outline-primary" for="pcOurSideB" data-action="pc-our-side" data-side="B">我是合同乙方</label>'
                . '<input type="radio" class="btn-check" name="pc_our_side_radio" id="pcOurSideA" value="A" autocomplete="off">'
                . '<label class="btn btn-outline-primary" for="pcOurSideA" data-action="pc-our-side" data-side="A">我是合同甲方</label>'
                . '</div></div></div>';
        }
        if ($group === 'terms') {
            return '<div class="col-12"><div class="border-top pt-2 mt-1 mb-1 text-muted small">条款与日期</div></div>';
        }
        if ($group === 'attach') {
            return '<div class="col-12"><div class="border-top pt-2 mt-1 mb-1 text-muted small">合同附件</div></div>';
        }
        return '';
    }

    public static function pcFieldHtml(array $f, array $contract, bool $isNew, string $reqMark, string $reqAttr, array $maps, int $defaultCompanyId = 0): string
    {
        $name = $f['name'];
        $val = $contract[$name] ?? ($f['default'] ?? '');
        $label = $f['label'] ?? $name;
        $req = !empty($f['required']) ? $reqMark : '';
        // 2026-08-05 修复：required 属性必须按字段判定（此前全局拼 $reqAttr，导致选填字段
        // party_a_contact/remark 等被误标 required，新建提交校验会错误拦截快速保存）。
        $reqAttr = (!empty($f['required']) && $isNew) ? ' required' : '';
        $col = (int)($f['col_pc'] ?? 12);
        $colCls = 'col-12' . ($col < 12 ? ' col-md-' . $col : '');
        $id = $f['pc_id'] ?? $name;
        $ph = $f['placeholder'] ?? '';
        $rowIdAttr = !empty($f['row_id']) ? ' id="' . self::h($f['row_id']) . '"' : '';

        switch ($f['type']) {
            case 'hidden':
                return '<input type="hidden" name="' . $name . '" id="' . $id . '" value="' . self::h($val) . '">';

            case 'text':
            case 'number':
            case 'date':
                $t = $f['type'] === 'number' ? 'number' : ($f['type'] === 'date' ? 'date' : 'text');
                $step = $f['type'] === 'number' ? ' step="' . ($f['step'] ?? '0.01') . '"' : '';
                $pre = $f['type'] === 'number' ? '<div class="input-group"><span class="input-group-text">¥</span>' : '';
                $post = $f['type'] === 'number' ? '</div>' : '';
                return '<div class="' . $colCls . '"' . $rowIdAttr . '><label class="form-label">' . self::h($label) . $req . '</label>'
                    . $pre . '<input type="' . $t . '" name="' . $name . '" class="form-control" id="' . $id . '"'
                    . $reqAttr . $step . ' value="' . self::h($val) . '" placeholder="' . self::h($ph) . '">' . $post
                    . '</div>';

            case 'textarea':
                $rows = (int)($f['rows'] ?? 4);
                $help = !empty($f['help']) ? '<div class="form-text">' . self::h($f['help']) . '</div>' : '';
                return '<div class="' . $colCls . '"><label class="form-label">' . self::h($label) . $req . '</label>'
                    . '<textarea name="' . $name . '" class="form-control" id="' . $id . '" rows="' . $rows . '"'
                    . $reqAttr . ' placeholder="' . self::h($ph) . '">' . self::h($val) . '</textarea>' . $help . '</div>';

            case 'select':
                $opts = self::optionList($f, $maps);
                // 2026-08-05：无默认值的 select 若不带占位项，浏览器会默认选中第一项（如项目误关联）。
                // 先探测是否有匹配值，无匹配时输出 value="" 的占位项并选中。
                $hasMatch = false;
                foreach ($opts as $code => $n) {
                    if ($val == $code) { $hasMatch = true; break; }
                }
                $html = '<div class="' . $colCls . '"' . $rowIdAttr . '><label class="form-label">' . self::h($label) . $req . '</label>'
                    . '<select name="' . $name . '" class="form-select" id="' . $id . '"' . $reqAttr . '>';
                if (!$hasMatch) {
                    $placeholder = '- 请选择 -';
                    $html .= '<option value="" selected>' . $placeholder . '</option>';
                }
                foreach ($opts as $code => $n) {
                    $html .= '<option value="' . self::h($code) . '"' . ($val == $code ? ' selected' : '') . '>' . self::h($n) . '</option>';
                }
                $help = !empty($f['help']) ? '<div class="form-text">' . self::h($f['help']) . '</div>' : '';
                $html .= '</select>' . $help . '</div>';
                return $html;

            case 'trade_attr':
                $trade = ($val == '' ? ($f['default'] ?? 1) : $val);
                $checkedTrade = $trade == 1 ? ' checked' : '';
                $checkedNon = $trade == 0 ? ' checked' : '';
                return '<div class="col-12"><label class="form-label">' . self::h($label) . '</label>'
                    . '<div class="btn-group w-100" role="group">'
                    . '<input type="radio" class="btn-check" name="trade_attr_radio" id="taTrade" value="1" autocomplete="off"' . $checkedTrade . '>'
                    . '<label class="btn btn-outline-primary" for="taTrade">交易合同（计入收支）</label>'
                    . '<input type="radio" class="btn-check" name="trade_attr_radio" id="taNon" value="0" autocomplete="off"' . $checkedNon . '>'
                    . '<label class="btn btn-outline-secondary" for="taNon">非交易合同（不计入收支）</label>'
                    . '</div>'
                    . '<input type="hidden" name="trade_attr" id="tradeAttr" value="' . self::h($trade) . '">'
                    . '<div class="form-text text-muted" id="taHint"></div></div>';

            case 'boolean':
                $checked = (int)$val === 1 ? ' checked' : '';
                $help = !empty($f['help']) ? '<div class="form-text">' . self::h($f['help']) . '</div>' : '';
                return '<div class="' . $colCls . '"><label class="form-label d-block">' . self::h($label) . '</label>'
                    . '<div class="form-check form-switch">'
                    . '<input type="hidden" name="' . $name . '" value="0">'
                    . '<input class="form-check-input" type="checkbox" role="switch" name="' . $name . '" id="' . $id . '" value="1"' . $checked . '>'
                    . '<label class="form-check-label" for="' . $id . '">' . self::h($f['switch_label'] ?? '启用') . '</label>'
                    . '</div>' . $help . '</div>';

            case 'company':
                $sel = $val ?: $defaultCompanyId;
                // PC 端主体仅通过第二步甲乙方的「切换」入口选择，避免重复输入。
                return '<input type="hidden" name="' . $name . '" id="' . $id . '" value="' . self::h($sel) . '">';

            case 'project_search':
                // 2026-08-05：关联项目由下拉改为「搜索选择器」，输入关键字搜索未归档项目，
                // 空输入时展示「与我有关」推荐（owner_id=当前用户优先，由 /ajax/project/search 返回）。
                $pid = (int)($contract['project_id'] ?? 0);
                $display = '';
                foreach (($maps['projects'] ?? []) as $p) {
                    $rowId = is_array($p) ? ($p['id'] ?? 0) : $p;
                    $rowName = is_array($p) ? ($p['name'] ?? '') : (string)$p;
                    if ((int)$rowId == $pid) { $display = $rowName; break; }
                }
                return '<div class="col-6 col-md-3"><label class="form-label">关联项目'
                    . ' <i class="bi bi-question-circle text-muted" style="font-size:12px;cursor:help" title="选择本项目执行所属的项目。\n输入关键字搜索，或直接点选推荐项目。"></i></label>'
                    . '<div class="position-relative">'
                    . '<input type="text" class="form-control project-search" id="projectSearch" placeholder="搜索项目名称或编号..." autocomplete="off" value="' . self::h($display) . '">'
                    . '<div class="party-suggestions" id="projectSuggestions"></div></div>'
                    . '<input type="hidden" name="project_id" id="projectIdField" value="' . $pid . '"></div>';

            case 'keywords':
                // v2.44.2：PC 端对齐移动端——只读展示区 + 点击弹出弹层（输入 + 常用标签推荐 + 已选），
                // 推荐不再平铺在输入框下方；hidden 承载 name=keywords 提交值。
                return '<div class="col-6 col-md-3"><label class="form-label">关键词'
                    . ' <i class="bi bi-question-circle text-muted" style="font-size:12px;cursor:help" title="点击输入关键词，回车即生成标签，点标签 × 可删除。"></i></label>'
                    . '<input type="hidden" name="keywords" value="' . self::h($val) . '">'
                    . '<div class="kw-display" id="kwDisplay" tabindex="0"><span class="kw-empty">点击添加关键词</span><span class="kw-add-hint">添加</span></div>'
                    . '<div class="kw-pc-mask" id="kwMask" style="display:none"></div>'
                    . '<div class="kw-pc-sheet" id="kwSheet" style="display:none">'
                    . '<div class="kw-pc-sheet-hd"><b>关键词</b><button type="button" class="kw-pc-close" id="kwClose">&times;</button></div>'
                    . '<div class="kw-pc-sheet-input-row">'
                    . '<input type="text" id="kwInput" placeholder="输入关键词后点添加或回车" autocomplete="off">'
                    . '<button type="button" id="kwAddBtn" disabled>添加</button>'
                    . '</div>'
                    . '<div class="kw-pc-sheet-sec"><div class="kw-sec-t">常用标签</div>'
                    . '<div class="kw-pc-hot" id="kwHot"><div class="kw-sheet-none">暂无常用标签</div></div></div>'
                    . '<div class="kw-pc-sheet-sec" id="kwCurSec" style="display:none"><div class="kw-sec-t">已选</div>'
                    . '<div class="kw-pc-cur" id="kwCur"></div></div>'
                    . '</div></div>';

            case 'party_name':
                $side = $f['side'] ?? 'A';
                $searchId = 'party' . $side . 'Search';
                $suggId = 'party' . $side . 'Suggestions';
                $nameId = $id;
                $sideName = $side == 'A' ? '甲方' : '乙方';
                // v2.47.x：对齐移动端——双形态：我方=主体名+切换按钮（只读展示，可切换签约主体）、
                // 对方=搜索式输入（搜索客户/供应商）；显隐由 create.php applyOurSidePC 按我方身份切换
                $mine = '<div class="pc-party-mine d-none" data-mine="' . $side . '">'
                    . '<span class="pc-party-mine-name" data-mine-name="' . $side . '">' . self::h($val) . '</span>'
                    . '<button type="button" class="btn btn-outline-primary btn-sm pc-party-switch" data-side="' . $side . '" title="选择签约主体"><i class="bi bi-arrow-repeat"></i> 切换</button></div>';
                $other = '<div class="pc-party-other" data-other="' . $side . '"><div class="input-group">'
                    . '<input type="text" class="form-control party-search" id="' . $searchId . '" placeholder="搜索客户/供应商…" autocomplete="off" data-side="' . $side . '" value="' . self::h($contract[($side == 'A' ? 'party_a_name' : 'party_b_name')] ?? '') . '">'
                    . '</div><div class="party-suggestions" id="' . $suggId . '"></div></div>';
                $row = '<div class="col-12 col-md-6"><label class="form-label">' . $sideName . '</label>'
                    . '<div class="position-relative">' . $mine . $other . '</div></div>';
                // v2.47.x：对齐移动端——「甲方名称/乙方名称」独立栏移除，名称改为 hidden 提交
                // （我方侧由签约主体带出，对方侧选择客户/供应商后回显）
                $row .= '<input type="hidden" name="' . $name . '" id="' . $nameId . '" value="' . self::h($val) . '"' . $reqAttr . '>';
                if ($side == 'B') {
                    $row .= '<div class="col-6 col-md-3" id="partyBContactWrap" style="display:none"><label class="form-label">从客户联系人选择</label>'
                        . '<select class="form-select" id="partyBContactSelect" onchange="onPartyBContactPick()"><option value="">— 手动输入 —</option></select></div>';
                }
                return $row;

            case 'party_search':
                $custId = (int)($val);
                $type = $contract[($name === 'party_b_customer_id' ? 'party_b_type' : 'party_a_type')] ?? '';
                $html = '<input type="hidden" name="' . $name . '" id="' . $id . '" value="' . $custId . '">';
                if ($name === 'party_b_customer_id') {
                    $html .= '<input type="hidden" name="party_b_type" id="partyBType" value="' . self::h($type) . '">';
                }
                return $html;

            case 'upload':
                return '<div class="col-12"><label class="form-label"><i class="bi bi-paperclip"></i> 合同附件</label>'
                    . '<div class="upload-dropzone border rounded-3 p-3 text-center bg-light" id="uploadDropzone"'
                    . ' style="border-style:dashed!important;cursor:pointer;transition:background .2s"'
                    . ' onclick="document.getElementById(\'fileInput\').click()"'
                    . ' ondragover="event.preventDefault();this.style.background=\'#e8f0fe\'"'
                    . ' ondragleave="this.style.background=\'\'" ondrop="handleDrop(event)">'
                    . '<input type="file" id="fileInput" multiple accept="image/*,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,.pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png" style="display:none" onchange="handleFiles(this.files)">'
                    . '<i class="bi bi-cloud-upload fs-3 text-muted"></i>'
                    . '<p class="text-muted mb-1 mt-1">拖拽文件到此处，或 <span class="text-primary">点击选择</span></p>'
                    . '<small class="text-muted">支持 PDF、Word、Excel、图片（JPG/PNG），单个最大 20MB</small></div>'
                    . '<div class="upload-list mt-2" id="uploadList"></div>'
                    . '<input type="hidden" name="file_url" id="fileUrlField" value="' . self::h($val) . '"></div>';

            default:
                // M13 修复：未知字段类型不静默降级 text（此前丢 required/placeholder，配置错误不暴露），
                // 配置错误立即抛异常让开发/管理员可见
                throw new \InvalidArgumentException("ContractFormConfig: 未知字段类型 '{$f['type']}'（字段：{$name}），请检查 config 定义");
        }
    }

    /**
     * 移动端字段渲染顺序（v2.40.0 重排）：标题 → 概要 → 附件 → 签约方 → 金额与期限 → 更多信息 → 更多选项。
     * 仅影响移动端；PC 端仍按 fields() 原序 + pc_step 分组渲染。
     */
    private static function mobileOrderedFields(): array
    {
        $order = ['title','content','file_url',
            'party_a_name','party_a_contact','party_a_phone','party_a_customer_id','party_a_supplier_id','party_a_type',
            'party_b_name','party_b_contact','party_b_phone','party_b_customer_id','supplier_id','our_side',
            'amount','effective_date','expiry_date',
            'business_type','trade_attr','direction','our_company_id',
            'keywords','project_id'];
        $byName = [];
        foreach (self::fields() as $f) { $byName[$f['name']] = $f; }
        $out = [];
        foreach ($order as $name) {
            if (isset($byName[$name])) { $out[] = $byName[$name]; unset($byName[$name]); }
        }
        foreach ($byName as $f) { $out[] = $f; } // 兜底：未列出的字段保持原序
        return $out;
    }

    /** 移动端「签约方」分区头部：我方身份切换（默认我方=乙方，业务多为服务方） */
    private static function mobilePartyHeader(): string
    {
        return '<div class="m-field"><label>我方身份</label>'
            . '<div class="m-seg" role="group">'
            . '<button type="button" class="m-seg-btn on" data-action="our-side" data-side="B">我是合同乙方</button>'
            . '<button type="button" class="m-seg-btn" data-action="our-side" data-side="A">我是合同甲方</button>'
            . '</div></div>';
    }

    /**
     * 移动端字段 HTML 生成入口：按 config 顺序遍历，自动输出分区标题(m-sec-title)。
     */
    public static function mobileRenderAll(array $contract, bool $isNew, array $maps, int $defaultCompanyId = 0): string
    {
        $reqMark = $isNew ? '<span class="req">*</span>' : '';
        $reqAttr = $isNew ? ' required' : '';
        $out = '';
        $lastSec = '__init__';
        // m_fold=true 的字段（关键词/关联项目）收进「更多选项」折叠面板
        $inFold = false;
        $partyHeader = false;
        foreach (self::mobileOrderedFields() as $f) {
            if (!empty($f['skip_mobile'])) continue;
            $isFold = !empty($f['m_fold']);
            if ($isFold && !$inFold) {
                // v2.51.4：折叠标题仅副标题，移除「已选N项」徽章（数值小看不清，体验差）
                $out .= '<details class="m-fold" id="moreOptionsFold"><summary>更多选项'
                    . '<span class="m-fold-sub">关键词 · 关联项目</span>'
                    . '<i class="bi bi-chevron-down"></i></summary><div class="m-fold-body">';
                $inFold = true;
            }
            if (!$isFold && $inFold) {
                $out .= '</div></details>';
                $inFold = false;
            }
            // v2.40.0：签约方分区首个字段前输出「我方身份」切换控件
            if (($f['group'] ?? '') === 'party' && !$partyHeader) {
                $partyHeader = true;
                $out .= self::mobilePartyHeader();
            }
            $sec = $f['m_sec'] ?? '';
            if ($sec !== $lastSec) {
                if ($sec !== '') $out .= '<div class="m-sec-title">' . self::h($sec) . '</div>';
                $lastSec = $sec;
            }
            $out .= self::mobileFieldHtml($f, $contract, $isNew, $reqMark, $reqAttr, $maps, $defaultCompanyId);
        }
        if ($inFold) {
            $out .= '</div></details>';
        }
        return $out;
    }

    public static function mobileFieldHtml(array $f, array $contract, bool $isNew, string $reqMark, string $reqAttr, array $maps, int $defaultCompanyId = 0): string
    {
        $name = $f['name'];
        $val = $contract[$name] ?? ($f['default'] ?? '');
        $label = $f['label'] ?? $name;
        $req = !empty($f['required']) ? $reqMark : '';
        // 2026-08-05 修复：同 PC 端——required 属性按字段判定，选填字段不误标 required
        $reqAttr = (!empty($f['required']) && $isNew) ? ' required' : '';
        $id = $f['m_id'] ?? $name;

        if (!empty($f['skip_mobile']) && $f['type'] === 'hidden') {
            return '';
        }

        switch ($f['type']) {
            case 'hidden':
                return '<input type="hidden" name="' . $name . '" id="' . $id . '" value="' . self::h($val) . '">';

            case 'text':
            case 'number':
            case 'date':
                $t = $f['type'] === 'number' ? 'number' : ($f['type'] === 'date' ? 'date' : 'text');
                $step = $f['type'] === 'number' ? ' step="' . ($f['step'] ?? '0.01') . '"' : '';
                $wrapId = !empty($f['m_row_id']) ? ' id="' . $f['m_row_id'] . '"' : '';
                $html = '<div class="m-field"' . $wrapId . '><label>' . self::h($label) . $req . '</label>'
                    . '<input class="m-input" name="' . $name . '" id="' . $id . '" type="' . $t . '"' . $step
                    . ' placeholder="' . self::h($f['placeholder'] ?? '') . '" value="' . self::h($val) . '"' . $reqAttr . '>';
                if ($name === 'party_b_contact') {
                    // v2.40.0：修复「乙方联系人/电话」label 重复渲染——下拉仅作客户联系人辅助入口，不再输出重复 label
                    $html .= '</div><div class="m-field"><select class="m-input" id="mPartyBContactSelect" style="display:none" onchange="mOnPartyBContactPick()"><option value="">— 手动输入 —</option></select>';
                }
                $html .= '</div>';
                return $html;

            case 'textarea':
                // v2.40.0：移动端概要简化——行数与占位符优先取 m_rows/m_placeholder（PC 端不受影响）
                $rows = (int)($f['m_rows'] ?? $f['rows'] ?? 4);
                $ph = $f['m_placeholder'] ?? $f['placeholder'] ?? '';
                return '<div class="m-field"><label>' . self::h($label) . $req . '</label>'
                    . '<textarea class="m-textarea" name="' . $name . '" id="' . $id . '" rows="' . $rows . '"'
                    . ' placeholder="' . self::h($ph) . '"' . $reqAttr . '>' . self::h($val) . '</textarea></div>';

            case 'select':
                $opts = self::optionList($f, $maps);
                // v2.40.0：移动端忽略配置默认值——分类/方向新建时强制「请选择」显式必选，避免默认值错填
                $val = $contract[$name] ?? '';
                // 2026-08-05：同 PC 端——无默认值 select 加占位项，避免浏览器默认选中第一项
                $hasMatch = false;
                foreach ($opts as $code => $n) {
                    if ($val == $code) { $hasMatch = true; break; }
                }
                $wrapId = !empty($f['m_row_id']) ? ' id="' . $f['m_row_id'] . '"' : '';
                $html = '<div class="m-field"' . $wrapId . '><label>' . self::h($label) . $req . '</label>'
                    . '<select class="m-select" name="' . $name . '" id="' . $id . '"' . $reqAttr . '>';
                if (!$hasMatch) {
                    $placeholder = '- 请选择 -';
                    $html .= '<option value="" selected>' . $placeholder . '</option>';
                }
                foreach ($opts as $code => $n) {
                    $html .= '<option value="' . self::h($code) . '"' . ($val == $code ? ' selected' : '') . '>' . self::h($n) . '</option>';
                }
                $help = !empty($f['help']) ? '<div class="m-help">' . self::h($f['help']) . '</div>' : '';
                $html .= '</select>' . $help . '</div>';
                return $html;

            case 'trade_attr':
                // v2.40.0：移动端「合同性质」改为交易/非交易 radio 无默认必选（避免默认值错填；
                // 后端 trade_attr 漏传默认 0=非交易，显式选择最稳妥；PC 端 switch 默认保持）
                $trade = $contract['trade_attr'] ?? '';
                return '<div class="m-field"><label>合同性质<span class="req">*</span></label>'
                    . '<div class="m-radio-row">'
                    . '<label class="m-radio"><input type="radio" name="trade_attr" value="1"' . ($trade == 1 ? ' checked' : '') . '><span>交易合同（计入收支）</span></label>'
                    . '<label class="m-radio"><input type="radio" name="trade_attr" value="0"' . ($trade === 0 || $trade === '0' ? ' checked' : '') . '><span>非交易合同（仅存档）</span></label>'
                    . '</div></div>';

            case 'boolean':
                $checked = (int)$val === 1 ? ' checked' : '';
                $help = !empty($f['help']) ? '<div class="m-help">' . self::h($f['help']) . '</div>' : '';
                return '<div class="m-field"><label>' . self::h($label) . '</label>'
                    . '<input type="hidden" name="' . $name . '" value="0">'
                    . '<label class="m-radio"><input type="checkbox" name="' . $name . '" id="' . $id . '" value="1"' . $checked . '>'
                    . '<span>' . self::h($f['switch_label'] ?? '启用') . '</span></label>' . $help . '</div>';

            case 'company':
                return '<input type="hidden" name="our_company_id" id="f_company" value="' . self::h($val ?: $defaultCompanyId) . '">';

            case 'party_name':
                // v2.40.0：签约方行重构——我方形态（主体名+切换）+ 对方形态（搜索式输入）由 JS 按我方身份切换；
                // 甲方/乙方名称仍为必填 hidden 提交，我方侧由系统带出签约主体名
                $side = $f['side'] ?? 'A';
                $nameId = $id;
                $sideName = $side == 'A' ? '甲方' : '乙方';
                $mine = '<div class="m-mine" data-mine="' . $side . '" style="display:none">'
                    . '<i class="bi bi-building-check"></i><span class="m-mine-name" data-mine-name="' . $side . '">' . self::h($val) . '</span>'
                    . '<button type="button" class="m-btn m-btn-ghost m-party-self" data-side="' . $side . '">切换</button></div>';
                $other = '<div class="m-party-search m-party-other" data-other="' . $side . '">'
                    . '<input class="m-input" id="partySearch' . $side . '" placeholder="搜索客户/供应商…" autocomplete="off">'
                    . '<div class="m-party-suggest" id="partySuggest' . $side . '"></div></div>';
                return '<div class="m-field"><label id="label' . $side . '">' . $sideName . $req . '</label>'
                    . $mine . $other
                    . '<input type="hidden" name="' . $name . '" id="' . $nameId . '" value="' . self::h($val) . '"' . $reqAttr . '>'
                    . '</div>';

            case 'party_search':
                // v2.40.0：搜索式输入已并入对方行（party_name），此处仅保留乙方客户隐藏字段；
                // 甲方客户(party_a_customer_id)/供应商(supplier_id)/我方身份(our_side) 由各自 hidden 字段渲染
                return '<input type="hidden" name="party_b_customer_id" id="f_party_b_cid" value="' . (int)$val . '">';

            case 'keywords':
                return '<input type="hidden" name="keywords" id="f_keywords" value="' . self::h($val) . '">'
                    . '<div class="kw-display" id="kwDisplay" role="button" tabindex="0">'
                    . '<span class="kw-empty">点击添加关键词</span></div>';

            case 'upload':
                // 2026-08-05：文档/图片/拍照三入口改为三等分网格（原 2+1 上下堆叠难看）
                return '<div class="m-field"><label>合同附件<span class="req">*</span></label>'
                    . '<div class="m-upload-row m-upload-grid">'
                    . '<div class="m-upload m-upload-third" id="uploadDoc" onclick="document.getElementById(\'fileDocInput\').click()"><i class="bi bi-file-earmark-text"></i><span>上传文档</span><small>PDF/Word/Excel</small></div>'
                    . '<div class="m-upload m-upload-third" id="uploadImg" onclick="document.getElementById(\'fileImgInput\').click()"><i class="bi bi-image"></i><span>上传图片</span><small>JPG/PNG</small></div>'
                    . '<div class="m-upload m-upload-third" id="uploadCamera" onclick="document.getElementById(\'fileCameraInput\').click()"><i class="bi bi-camera"></i><span>拍照</span><small>即时拍摄</small></div>'
                    . '</div>'
                    . '<div class="m-upload-tip">单个文件最大 20MB，超出将自动拦截</div>'
                    . '<input type="file" id="fileDocInput" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" style="position:absolute;left:-9999px;width:1px;height:1px;opacity:0;overflow:hidden;">'
                    . '<input type="file" id="fileImgInput" multiple accept="image/*,.jpg,.jpeg,.png" style="position:absolute;left:-9999px;width:1px;height:1px;opacity:0;overflow:hidden;">'
                    . '<input type="file" id="fileCameraInput" accept="image/*" capture="environment" style="position:absolute;left:-9999px;width:1px;height:1px;opacity:0;overflow:hidden;">'
                    . '<div class="m-upload-list" id="uploadList"></div>'
                    . '<input type="hidden" name="file_url" id="f_file_url" value="' . self::h($val) . '"></div>';

            case 'project_search':
                // 2026-08-05：移动端「关联项目」由下拉改为搜索选择器
                $pid = (int)($contract['project_id'] ?? 0);
                $display = '';
                foreach (($maps['projects'] ?? []) as $p) {
                    $rowId = is_array($p) ? ($p['id'] ?? 0) : $p;
                    $rowName = is_array($p) ? ($p['name'] ?? '') : (string)$p;
                    if ((int)$rowId == $pid) { $display = $rowName; break; }
                }
                return '<div class="m-field"><label>' . self::h($label) . '</label>'
                    . '<div class="m-party-search">'
                    . '<input class="m-input" id="projectSearchM" placeholder="搜索项目名称或编号…" autocomplete="off" value="' . self::h($display) . '">'
                    . '<div class="m-party-suggest" id="projectSuggestM"></div></div>'
                    . '<input type="hidden" name="' . $name . '" id="projectIdFieldM" value="' . $pid . '"></div>';

            default:
                // M13 修复：未知字段类型不静默降级 text（配置错误立即暴露）
                throw new \InvalidArgumentException("ContractFormConfig: 未知字段类型 '{$f['type']}'（字段：{$name}），请检查 config 定义");
        }
    }
}
