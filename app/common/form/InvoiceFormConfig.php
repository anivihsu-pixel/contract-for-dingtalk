<?php
// +----------------------------------------------------------------------
// | 发票申请表单配置（F1/F2，v2.38.7）
// | 钉钉表单式：预置字段池（系统字段禁删可停用）+ 后台可新增自定义字段，
// | 运行时按 invoice_form_field 配置表（enabled + sort_order）合并输出，
// | PC / 移动端申请表单共用本配置渲染，字段改后台配置即双端生效。
// +----------------------------------------------------------------------

namespace app\common\form;

use think\facade\Db;

class InvoiceFormConfig
{
    /** 允许的字段类型（后台新增自定义字段白名单；发票表单专用，合同业务控件已随合同表单取消） */
    public static function types(): array
    {
        return [
            'text'         => '单行输入框',
            'textarea'     => '多行输入框',
            'number'       => '数字输入框',
            'radio'        => '单选框',
            'checkbox'     => '多选框',
            'date'         => '日期',
            'daterange'    => '日期区间',
            'description'  => '说明文字',
            'phone'        => '电话',
            'select'       => '下拉选择',
            'company'      => '公司主体（我方公司）',
            'customer'     => '客户（复用开票信息）',
            'attachment'   => '附件上传',
        ];
    }

    /**
     * 预置字段池（与 invoice_form_field 种子 1:1；配置表为空时兜底使用，
     * 保证升级中途/表缺失场景申请表单仍可用）
     * @return array<int,array> 字段定义（name/label/type/required/options/default）
     */
    public static function presetFields(): array
    {
        return [
            ['name' => 'our_company_id', 'label' => '开票主体', 'type' => 'company', 'required' => true],
            ['name' => 'invoice_type', 'label' => '开票类型', 'type' => 'select', 'required' => true,
             'options' => ['VAT_SPECIAL' => '我要开增值税专用发票', 'VAT_NORMAL' => '我要开普通发票']],
            // 开票内容（v2.38.22：由多行文本改为下拉选择——Step1 设计器仅此字段选项可配置；
            // 选项值=中文显示名，保证审批/通知等既有展示逻辑直接输出无需映射）
            ['name' => 'content_desc', 'label' => '开票内容', 'type' => 'select', 'required' => true,
             'options' => ['软件开发服务费' => '软件开发服务费', '咨询服务费' => '咨询服务费', '运维服务费' => '运维服务费', '硬件销售费' => '硬件销售费', '其他' => '其他']],
            // v2.51.4：字段顺序调整——客户信息（客户/抬头/税号）、金额与上方主体/类型等基本信息分区分组
            ['name' => 'customer_id', 'label' => '开票客户', 'type' => 'customer', 'required' => false, 'placeholder' => '选择客户自动带出抬头/税号'],
            ['name' => 'invoice_title', 'label' => '发票抬头', 'type' => 'text', 'placeholder' => '对方公司名称（可选客户自动带出）'],
            ['name' => 'tax_no', 'label' => '税号', 'type' => 'text', 'placeholder' => '对方纳税人识别号（可选客户自动带出）'],
            ['name' => 'amount', 'label' => '含税金额（元）', 'type' => 'number', 'required' => true, 'step' => '0.01'],
            // tax_rate：开票税率已绑定开票主体（company_profile.invoice_tax_rate，后台公司管理配置），
            // 2026-08-02 起不再作为独立表单组件（enabled=false 兜底不渲染；配置表种子亦 enabled=0）；
            // options 与公司管理下拉同步（含 1%/5% 常用档）
            ['name' => 'tax_rate', 'label' => '税率', 'type' => 'select', 'enabled' => false, 'default' => '0.06',
             'options' => ['0.01' => '1%', '0.03' => '3%', '0.05' => '5%', '0.06' => '6%', '0.09' => '9%', '0.13' => '13%']],
            ['name' => 'remark', 'label' => '申请说明', 'type' => 'textarea', 'rows' => 3, 'placeholder' => '选填，说明开票事由/合同关联'],
        ];
    }

    /**
     * 运行时字段列表：读 invoice_form_field 配置表（enabled=1 按 sort_order 升序），
     * 系统字段按配置覆盖预置默认；自定义字段（is_system=0）并入。
     * 配置表为空时回退预置池全量。
     * @return array<int,array>
     */
    public static function fields(): array
    {
        $preset = [];
        foreach (self::presetFields() as $f) {
            $preset[$f['name']] = $f;
        }

        $rows = [];
        try {
            $rows = Db::name('invoice_form_field')
                ->where('enabled', 1)
                ->order('sort_order', 'asc')->order('id', 'asc')
                ->select()->toArray();
        } catch (\Throwable $e) {
            // 表尚未创建（升级中途）：回退预置池
            $rows = [];
        }

        if (empty($rows)) {
            // 配置表为空（升级中途/全新库）：回退预置池并过滤系统停用字段（如 tax_rate——税率已绑定开票主体）
            return array_values(array_filter($preset, function ($f) {
                return !array_key_exists('enabled', $f) || !empty($f['enabled']);
            }));
        }

        $out = [];
        foreach ($rows as $r) {
            $key = (string)($r['field_key'] ?? '');
            if ($key === '') continue;
            // 税率组件已停用（2026-08-02：税率绑定开票主体 company_profile.invoice_tax_rate，
            // 即使旧库种子 enabled=1 未同步，也强制不渲染——双保险）
            if ($key === 'tax_rate') continue;
            $type = in_array($r['field_type'] ?? 'text', array_keys(self::types()), true)
                ? $r['field_type'] : 'text';
            $f = [
                'name'     => $key,
                'label'    => ($r['field_label'] ?? '') !== '' ? $r['field_label'] : ($preset[$key]['label'] ?? $key),
                'type'     => $type,
                'required' => !empty($r['required']),
                'options'  => self::optionList(['options' => $r['field_options'] ?? '']),
            ];
            if (isset($preset[$key])) {
                // 系统字段：继承预置的 placeholder/step/rows 等辅助属性
                foreach (['placeholder', 'step', 'rows', 'default'] as $k) {
                    if (isset($preset[$key][$k])) $f[$k] = $preset[$key][$k];
                }
            }
            $out[] = $f;
        }
        return $out ?: array_values($preset);
    }

    /**
     * 选项归一化（M13 教训）：兼容 [{"value":"..","label":".."}]、["a","b"]、
     * ['a'=>'甲'] 三种形态，统一输出 [value=>label]，杜绝 Array to string conversion。
     * @param array $f 字段（options 可为 JSON 字符串或数组）
     * @return array<string,string>
     */
    public static function optionList(array $f): array
    {
        $raw = $f['options'] ?? [];
        if (is_string($raw)) {
            $raw = trim($raw);
            if ($raw === '' || $raw === '[]' || $raw === 'null') return [];
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }
        $out = [];
        foreach ($raw as $k => $v) {
            if (is_array($v)) {
                // 行列表形态 [{"value":"VAT","label":"专票"}]
                $val = $v['value'] ?? '';
                $lbl = $v['label'] ?? ($v['value'] ?? '');
                if ($val !== '') $out[$val] = $lbl !== '' ? $lbl : $val;
            } elseif (is_string($k) && $k !== '') {
                // 关联数组形态 ['VAT'=>'专票']；注意 numeric 字符串键（如税率 '0.03'=>'3%'）也按关联处理——
                // 否则二次转换时被当简单列表，value 错误变成 label（2026-08-02 价税展示暴露）
                $out[$k] = $v;
            } else {
                // 简单列表形态 ["专票","普票"]：value=label=自身
                $out[$v] = $v;
            }
        }
        return $out;
    }

    /** PC 端表单渲染（Bootstrap 栅格；textarea 占满行，其余各半行） */
    public static function pcRender(array $data = [], array $maps = []): string
    {
        $html = '';
        foreach (self::fields() as $f) {
            $name  = $f['name'];
            $val   = $data[$name] ?? ($f['default'] ?? '');
            $reqMark = !empty($f['required']) ? ' <span class="text-danger">*</span>' : '';
            $reqAttr = !empty($f['required']) ? ' required' : '';
            $col   = $f['type'] === 'textarea' ? 'col-12' : 'col-md-6';
            $ph    = $f['placeholder'] ?? '';
            $label = '<label class="form-label">' . self::h($f['label'] ?? $name) . $reqMark . '</label>';

            switch ($f['type']) {
                case 'textarea':
                    $rows = (int)($f['rows'] ?? 4);
                    $html .= '<div class="' . $col . '">' . $label
                        . '<textarea name="' . $name . '" class="form-control" rows="' . $rows . '"' . $reqAttr
                        . ' placeholder="' . self::h($ph) . '">' . self::h($val) . '</textarea></div>';
                    break;
                case 'select':
                    $opts = self::optionList($f);
                    $html .= '<div class="' . $col . '">' . $label
                        . '<select name="' . $name . '" class="form-select"' . $reqAttr . '><option value="">请选择</option>';
                    foreach ($opts as $code => $n) {
                        $html .= '<option value="' . self::h($code) . '"' . ((string)$val === (string)$code ? ' selected' : '') . '>' . self::h($n) . '</option>';
                    }
                    $html .= '</select></div>';
                    break;
                case 'company':
                    $companies = $maps['companies'] ?? [];
                    $html .= '<div class="' . $col . '">' . $label
                        . '<select name="' . $name . '" class="form-select"' . $reqAttr . '><option value="0">请选择开票主体</option>';
                    foreach ($companies as $c) {
                        $cid = is_array($c) ? ($c['id'] ?? 0) : $c;
                        $cname = is_array($c) ? ($c['name'] ?? '') : $c;
                        // data-rate：开票税率随主体带出（后端 company_profile.invoice_tax_rate，前端联动填税率）
                        $crate = is_array($c) ? (string)($c['invoice_tax_rate'] ?? '') : '';
                        $html .= '<option value="' . self::h($cid) . '" data-rate="' . self::h($crate) . '"' . ((string)$val === (string)$cid ? ' selected' : '') . '>' . self::h($cname) . '</option>';
                    }
                    $html .= '</select></div>';
                    break;
                case 'customer':
                    // 2026-08-11：开票客户数据源统一后端搜索（/ajax/party/search，与新建合同同源，含供应商），
                    // 不再向前端注入全量客户（原 v2.41.0 data-cs-src 内存过滤）；
                    // 选中后由 search-picker.js data-fill-* 内建带出抬头=客户名/税号=信用代码（不再依赖后台联动规则）。
                    // data-quick：搜索无匹配时提供「快速新建客户」入口（与新建合同一致，建档后回填并带出抬头/税号），
                    // 弹层与提交由 search-picker.js 内建（POST data-quick-url，复用 /ajax/customer/save 查重与数据权限）。
                    $selName = '';
                    foreach (($maps['customers'] ?? []) as $__cu) {
                        $__cid = is_array($__cu) ? ($__cu['id'] ?? 0) : $__cu;
                        if ((string)$val !== '' && (string)$__cid === (string)$val) {
                            $selName = is_array($__cu) ? ($__cu['name'] ?? '') : '';
                            break;
                        }
                    }
                    $html .= '<div class="' . $col . '">' . $label
                        . '<div class="cs-wrap" data-cs-url="/ajax/party/search?q=" data-fill-name="invoice_title" data-fill-credit="tax_no"'
                        . ' data-quick="customer" data-quick-url="/ajax/customer/save"'
                        // data-default-*：服务端预填值（合同详情申请开票默认带出合同客户方）；
                        // 页面打开弹窗重置选择器时按此恢复，data=0 时与旧行为一致清空；
                        // data-default-title/credit 承载默认客户抬头/税号，重开弹窗同步恢复（防残留上次改选值）
                        . ' data-default-id="' . self::h($val) . '" data-default-name="' . self::h($selName) . '"'
                        . ' data-default-title="' . self::h($data['invoice_title'] ?? '') . '" data-default-credit="' . self::h($data['tax_no'] ?? '') . '">'
                        . '<input type="text" class="form-control cs-input" placeholder="' . self::h($ph) . '" autocomplete="off" value="' . self::h($selName) . '">'
                        . '<div class="cs-suggestions"></div>'
                        . '<input type="hidden" class="cs-id" name="' . $name . '" value="' . self::h($val) . '"></div></div>';
                    break;
                case 'number':
                    $step = isset($f['step']) ? ' step="' . self::h($f['step']) . '"' : '';
                    $html .= '<div class="' . $col . '">' . $label
                        . '<input type="number" name="' . $name . '" class="form-control"' . $reqAttr . $step
                        . ' value="' . self::h($val) . '" placeholder="' . self::h($ph) . '"></div>';
                    break;
                default:
                    $html .= '<div class="' . $col . '">' . $label
                        . '<input type="text" name="' . $name . '" class="form-control"' . $reqAttr
                        . ' value="' . self::h($val) . '" placeholder="' . self::h($ph) . '"></div>';
            }
        }
        return $html;
    }

    /**
     * 字段联动规则（F9 通用组件）：读 form_field_linkage 表（form_key 区分表单），
     * 输出 [{trigger_field, trigger_value, target_field, action, options}] 供前端 form-linkage.js 消费。
     * 未来其他审批表单（如合同申请扩展）复用同一张表 + 引擎，仅换 form_key。
     * @param string $formKey 表单标识
     * @return array
     */
    public static function rules(string $formKey = 'invoice_apply'): array
    {
        try {
            $rows = Db::name('form_field_linkage')
                ->where('form_key', $formKey)
                ->order('sort_order', 'asc')->order('id', 'asc')
                ->select()->toArray();
        } catch (\Throwable $e) {
            return []; // 表尚未创建（升级中途）时安全回退
        }
        // F9：options 归一化为列表数组（表存 [{"value","label"}] JSON 字符串，
        // 前端 form-linkage.js 消费 Array.isArray 的数组——不能用 optionList 的关联数组形态，json_encode 后会是 Object）
        foreach ($rows as &$r) {
            $decoded = json_decode((string)($r['options'] ?? ''), true);
            $r['options'] = is_array($decoded) ? array_values($decoded) : [];
        }
        return $rows;
    }

    /** 移动端表单渲染（.m-field / .m-input / .m-select 与移动端设计体系一致） */
    public static function mobileRender(array $data = [], array $maps = []): string
    {
        $html = '';
        // v2.51.4：申请表单分区——客户信息、开票金额与上方主体/类型等基本信息区分隔。
        // 仅作用于系统预置字段（后台排序仍控制字段顺序），自定义字段按配置位置自然落入所在分区。
        $sectionBefore = ['customer_id' => '客户信息', 'amount' => '开票金额'];
        foreach (self::fields() as $f) {
            if (isset($sectionBefore[$f['name']])) {
                $html .= '<div class="m-field-section">' . self::h($sectionBefore[$f['name']]) . '</div>';
            }
            $name  = $f['name'];
            $val   = $data[$name] ?? ($f['default'] ?? '');
            $reqMark = !empty($f['required']) ? ' <span style="color:#fa5151">*</span>' : '';
            $ph    = $f['placeholder'] ?? '';
            $label = '<label class="m-field-label">' . self::h($f['label'] ?? $name) . $reqMark . '</label>';

            switch ($f['type']) {
                case 'textarea':
                    $html .= '<div class="m-field">' . $label
                        . '<textarea class="m-input" name="' . $name . '" rows="' . (int)($f['rows'] ?? 4) . '"'
                        . (!empty($f['required']) ? ' required' : '') . ' placeholder="' . self::h($ph) . '">' . self::h($val) . '</textarea></div>';
                    break;
                case 'select':
                    // v2.51.4：移动端下拉统一为「展示框 + 底部弹层」选择（与开票主体一致，替代原生 select）
                    $opts = self::optionList($f);
                    $selLabel = '';
                    foreach ($opts as $code => $n) {
                        if ((string)$val === (string)$code) { $selLabel = $n; break; }
                    }
                    $optsJson = json_encode(array_map(function ($k, $v) { return ['value' => (string)$k, 'label' => (string)$v]; }, array_keys($opts), array_values($opts)), JSON_UNESCAPED_UNICODE);
                    $html .= '<div class="m-field">' . $label
                        . '<div class="m-pick-box" data-inv-pick="select" data-pick-name="' . self::h($name) . '" data-options=\'' . self::h($optsJson) . '\'><span class="m-pick-name">' . self::h($selLabel !== '' ? $selLabel : '请选择') . '</span><button type="button" class="m-pick-btn">选择</button></div>'
                        . '<input type="hidden" name="' . $name . '"' . (!empty($f['required']) ? ' required' : '') . ' value="' . self::h($val) . '"></div>';
                    break;
                case 'company':
                    // v2.51.4：开票主体选择复用新建合同「我方主体」交互——只读展示 + 切换按钮 + 底部 sheet 弹层
                    // （mobile-common.js initInvPickers 驱动；数据源 __invCompanies 含税率供联动）
                    $companies = $maps['companies'] ?? [];
                    $selName = '';
                    foreach ($companies as $c) {
                        $cid = is_array($c) ? ($c['id'] ?? 0) : $c;
                        if ((string)$val !== '' && (string)$cid === (string)$val) {
                            $selName = is_array($c) ? ($c['name'] ?? '') : '';
                            break;
                        }
                    }
                    $companyJson = json_encode(array_map(function ($c) {
                        return ['value' => (string)($c['id'] ?? 0), 'label' => (string)($c['name'] ?? ''),
                            'rate' => (string)($c['invoice_tax_rate'] ?? ''), 'default' => (int)($c['is_default'] ?? 0)];
                    }, $companies), JSON_UNESCAPED_UNICODE);
                    $html .= '<div class="m-field">' . $label
                        . '<div class="m-pick-box" data-inv-pick="company" data-pick-name="' . self::h($name) . '" data-options=\'' . self::h($companyJson) . '\'><span class="m-pick-name">' . self::h($selName !== '' ? $selName : '请选择开票主体') . '</span><button type="button" class="m-pick-btn">切换</button></div>'
                        . '<input type="hidden" name="' . $name . '"' . (!empty($f['required']) ? ' required' : '') . ' value="' . self::h($val) . '"></div>';
                    break;
                case 'customer':
                    // 2026-08-11：同 PC 端——后端搜索 + data-fill-* 内建带出抬头/税号；
                    // data-quick：搜索无匹配时可快速新建客户（与新建合同一致，建档后回填带出抬头/税号）
                    $selName = '';
                    foreach (($maps['customers'] ?? []) as $__cu) {
                        $__cid = is_array($__cu) ? ($__cu['id'] ?? 0) : $__cu;
                        if ((string)$val !== '' && (string)$__cid === (string)$val) {
                            $selName = is_array($__cu) ? ($__cu['name'] ?? '') : '';
                            break;
                        }
                    }
                    $html .= '<div class="m-field">' . $label
                        . '<div class="cs-wrap" data-cs-url="/ajax/party/search?q=" data-fill-name="invoice_title" data-fill-credit="tax_no"'
                        . ' data-quick="customer" data-quick-url="/ajax/customer/save"'
                        . ' data-default-id="' . self::h($val) . '" data-default-name="' . self::h($selName) . '"'
                        . ' data-default-title="' . self::h($data['invoice_title'] ?? '') . '" data-default-credit="' . self::h($data['tax_no'] ?? '') . '">'
                        . '<input type="text" class="m-input cs-input" placeholder="' . self::h($ph) . '" autocomplete="off" value="' . self::h($selName) . '">'
                        . '<div class="cs-suggestions"></div>'
                        . '<input type="hidden" class="cs-id" name="' . $name . '" value="' . self::h($val) . '"></div></div>';
                    break;
                case 'number':
                    $html .= '<div class="m-field">' . $label
                        . '<input type="number" class="m-input" name="' . $name . '" step="' . self::h($f['step'] ?? '0.01') . '"'
                        . (!empty($f['required']) ? ' required' : '') . ' value="' . self::h($val) . '" placeholder="' . self::h($ph) . '"></div>';
                    break;
                default:
                    $html .= '<div class="m-field">' . $label
                        . '<input type="text" class="m-input" name="' . $name . '"'
                        . (!empty($f['required']) ? ' required' : '') . ' value="' . self::h($val) . '" placeholder="' . self::h($ph) . '"></div>';
            }
        }
        return $html;
    }

    private static function h($s): string
    {
        return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    }
}
