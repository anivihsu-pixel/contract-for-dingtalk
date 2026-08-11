<?php
// +----------------------------------------------------------------------
// | 通用表单设计器控制器（G1，v2.38.7）
// | 钉钉式两阶段配置：Step1 所见即所得字段画布（字段+联动）→ Step2 审批与抄送设置。
// | 通用组件：form_key 驱动（当前 invoice_apply=发票申请；未来审批表单扩展 FORM_TABLES 映射即可复用）。
// +----------------------------------------------------------------------

namespace app\controller;

use app\BaseController;
use think\facade\View;
use app\common\service\RbacService;
use app\common\logic\UserLogic;
use app\common\logic\InvoiceLogic;
use app\common\form\InvoiceFormConfig;

class FormBuilderController extends BaseController
{
    /** form_key → 字段配置表 映射（通用组件扩展点：未来审批表单在此登记） */
    private const FORM_TABLES = [
        'invoice_apply' => 'invoice_form_field',
    ];

    /** 表单标题 */
    private const FORM_TITLES = [
        'invoice_apply' => '发票申请表单',
    ];

    /** 设计器页面：表单字段画布 + 审批抄送设置 两阶段 */
    public function index()
    {
        $this->requirePermission('system:user');
        $form = (string)$this->getParam('form', 'invoice_apply');
        if (!isset(self::FORM_TABLES[$form])) {
            // 未知表单类型：页面级错误走 404（此前 $this->error() 方法不存在，非法 form 会 500）
            throw new \think\exception\HttpException(404, '未知表单类型');
        }

        View::assign('form_key', $form);
        View::assign('form_title', self::FORM_TITLES[$form] ?? $form);
        View::assign('field_types', InvoiceFormConfig::types());
        // Step2：审批角色/用户下拉数据
        View::assign('builder_roles', RbacService::getRoles());
        View::assign('builder_users', UserLogic::getOptions());
        // 发票表单：审批流（biz_type=invoice 专用流，供 Step2 编辑）
        View::assign('builder_flow', self::loadFlow($form));
        // Step2 条件分支「公司」字段选择器：公司主体下拉选项
        View::assign('builder_companies', \app\common\logic\CompanyLogic::getListWithDefault());
        // Step2 激活条件选择器：合同分类字典（节点级激活条件按分类匹配，与原审批流一致）
        View::assign('builder_categories', contract_categories());
        return View::fetch();
    }

    /**
     * 设计器数据接口：返回 {fields, linkage, flow}（画布初始化/回填）
     * @return \think\response\Json
     */
    public function formData()
    {
        $this->requirePermission('system:user');
        $form = (string)$this->getParam('form', 'invoice_apply');
        $table = self::FORM_TABLES[$form] ?? '';
        if ($table === '') return json_error('未知表单类型');

        $fields = \app\common\logic\AdminLogic::getFormFields($table);
        // options 归一化为列表数组（画布 select 选项编辑）
        foreach ($fields as &$f) {
            $decoded = json_decode((string)($f['field_options'] ?? ''), true);
            $f['options'] = is_array($decoded) ? array_values($decoded) : [];
        }
        $linkage = InvoiceFormConfig::rules($form);
        $flow = self::loadFlow($form);
        return json_success([
            'fields'   => $fields,
            'linkage'  => $linkage,
            'flow'     => $flow,
            'types'    => InvoiceFormConfig::types(),
        ]);
    }

    /**
     * Step1 保存：字段 + 联动（通用 form_key 全量重存）
     * 请求：fields=[{id,key,label,type,options,required,enabled,sort_order}]&linkage=[...]
     */
    public function saveForm()
    {
        $this->requirePermission('system:user');
        $form = (string)$this->getPost('form', 'invoice_apply');
        $table = self::FORM_TABLES[$form] ?? '';
        if ($table === '') return json_error('未知表单类型');

        $fields = json_decode((string)$this->getPost('fields', '[]'), true) ?: [];
        $linkage = json_decode((string)$this->getPost('linkage', '[]'), true) ?: [];
        // P2-10【M-A1】分层铁律下沉：字段+联动全量重存逻辑移至 AdminLogic，控制器零 Db 直查
        $res = \app\common\logic\AdminLogic::saveFormFields($form, $table, $fields, $linkage);
        return $res['ok'] ? json_success(null, $res['msg']) : json_error($res['msg']);
    }

    /**
     * Step1 轻量保存：仅更新「开票内容」下拉选项（v2.38.22——Step1 复用申请开票表单，
     * 只有 content_desc 的选项需要编辑，全量重存会误伤其它字段/排序，故独立轻量接口）
     * 请求：form=invoice_apply&options=[{"value","label"}]
     */
    public function saveContentOptions()
    {
        $this->requirePermission('system:user');
        $form = (string)$this->getPost('form', 'invoice_apply');
        $table = self::FORM_TABLES[$form] ?? '';
        if ($table === '') return json_error('未知表单类型');
        $options = normalize_options($this->getPost('options', '[]'));
        \app\common\logic\AdminLogic::updateFormFieldOptions($table, 'content_desc', $options);
        return json_success(null, '开票内容选项已保存');
    }

    /**
     * Step2 保存：多流程条件分组（H4：默认流程兜底 + 按表单字段条件分支——如不同开票公司走不同审批人/抄送）
     * 请求：groups=[{condition:{field,value}|null, nodes:[{name,type,role_code,approvers,mode}], cc:{role_codes:[],cc_user_ids:[]}}]
     * 兼容旧请求：nodes=...&cc=... 视为单个默认组。
     */
    public function saveFlow()
    {
        $this->requirePermission('system:user');
        $form = (string)$this->getPost('form', 'invoice_apply');
        // biz_type 映射：invoice_apply→invoice（仅发票；合同审批走 AdminController 原流程编辑器）
        if ($form !== 'invoice_apply') return json_error('未知表单类型');
        $bizType = 'invoice';
        $groups = json_decode((string)$this->getPost('groups', '[]'), true) ?: [];
        if (empty($groups)) {
            // 兼容旧客户端：单默认组
            $groups = [[
                'condition' => null,
                'nodes' => json_decode((string)$this->getPost('nodes', '[]'), true) ?: [],
                'cc'    => json_decode((string)$this->getPost('cc', '{}'), true) ?: [],
            ]];
        }

        $errs = [];
        $cleanGroups = [];
        $hasDefault = false;
        foreach ($groups as $gi => $g) {
            // 条件归一化（默认组 condition=null；条件组须字段+值齐全）
            $cond = null;
            $condRaw = $g['condition'] ?? null;
            if (is_array($condRaw) && !empty($condRaw['field']) && $condRaw['value'] !== '' && $condRaw['value'] !== null) {
                $cond = ['field' => (string)$condRaw['field'], 'value' => (string)$condRaw['value']];
            }
            if ($cond === null && $gi > 0) {
                // 非首组且无有效条件：视为默认组（允许多个默认无意义，但保留首组为默认）
            }
            if ($cond === null) $hasDefault = true;

            // v2.38.22：流程级金额条件（use_amount/min/max，对齐原审批流）
            $amt = $g['amount'] ?? [];
            $useAmount = isset($amt['use']) && (string)$amt['use'] === '1' ? 1 : 0;
            $minAmount = isset($amt['min']) && $amt['min'] !== '' ? (float)$amt['min'] : 0;
            $maxAmount = isset($amt['max']) && $amt['max'] !== '' ? (float)$amt['max'] : 99999999.99;
            if ($minAmount < 0) $minAmount = 0;
            if ($maxAmount < $minAmount) $maxAmount = 99999999.99;

            // 节点校验 + 归一化
            $clean = [];
            foreach (($g['nodes'] ?? []) as $n) {
                $name = trim((string)($n['name'] ?? ''));
                $type = (string)($n['type'] ?? 'ROLE');
                $mode = strtoupper((string)($n['mode'] ?? 'OR'));
                if ($name === '') { $errs[] = "存在未命名的审批节点"; continue; }
                if (!in_array($type, ['ROLE', 'SPECIFIC_USER', 'DEPT_LEADER'], true)) { $errs[] = "非法审批人类型：{$type}"; continue; }
                if (!in_array($mode, ['OR', 'AND'], true)) $mode = 'OR';
                $node = ['name' => $name, 'type' => $type, 'mode' => $mode];
                if ($type === 'ROLE') {
                    $roleCode = (string)($n['role_code'] ?? '');
                    if ($roleCode === '') { $errs[] = "节点「{$name}」未选择审批角色"; continue; }
                    $node['role_code'] = $roleCode;
                } elseif ($type === 'SPECIFIC_USER') {
                    $approvers = array_values(array_filter(array_map('intval', $n['approvers'] ?? [])));
                    if (empty($approvers)) { $errs[] = "节点「{$name}」未选择审批用户"; continue; }
                    $node['approvers'] = $approvers;
                }
                // v2.38.22：节点级金额条件（amount_min/amount_max，低于/高于则跳过节点）
                if (isset($n['amount_min']) && $n['amount_min'] !== '' && is_numeric($n['amount_min'])) {
                    $node['amount_min'] = (float)$n['amount_min'];
                }
                if (isset($n['amount_max']) && $n['amount_max'] !== '' && is_numeric($n['amount_max'])) {
                    $node['amount_max'] = (float)$n['amount_max'];
                }
                // v2.38.25：节点级激活条件功能已移除（发票流程不需要）
                $clean[] = $node;
            }
            if (empty($clean)) { $errs[] = "流程组（" . ($cond ? "条件 {$cond['field']}={$cond['value']}" : '默认') . "）至少配置一个审批节点"; }

            // 抄送归一化
            $ccRaw = $g['cc'] ?? [];
            $ccClean = [
                'role_codes'  => array_values(array_filter((array)($ccRaw['role_codes'] ?? []), fn($v) => $v !== '')),
                'cc_user_ids' => array_values(array_filter(array_map('intval', (array)($ccRaw['cc_user_ids'] ?? [])))),
            ];
            $cleanGroups[] = ['condition' => $cond, 'amount' => ['use' => $useAmount, 'min' => $minAmount, 'max' => $maxAmount], 'nodes' => $clean, 'cc' => $ccClean];
        }
        if (empty($cleanGroups)) { $errs[] = '请至少保留一个流程组'; }
        if (!empty($errs)) return json_error(implode('；', array_unique($errs)));

        // P2-10【M-A1】分层铁律下沉：全量重存（更新本次提交 + 停用未提交旧流）逻辑移至 AdminLogic
        \app\common\logic\AdminLogic::saveFlowGroups($cleanGroups, $bizType, 'INVOICE', $this->userId);
        return json_success(null, '审批与抄送设置已保存');
    }

    /** 读取 form 对应的全部专用审批流 → 多流程条件分组（H4：默认组 + 条件分支组） */
    private static function loadFlow(string $form): array
    {
        // 仅发票表单设计器（合同审批走 AdminController 原流程编辑器）
        if ($form !== 'invoice_apply') return [];
        $bizType = 'invoice';
        $flows = \app\common\logic\AdminLogic::getFlowGroups($bizType);
        $groups = [];
        foreach ($flows as $flow) {
            $cond = json_decode((string)($flow['form_condition'] ?? ''), true) ?: [];
            $nodes = json_decode($flow['nodes'] ?? '[]', true) ?: [];
            // v2.38.22：节点级金额条件透传（设计器回填用）；v2.38.25：激活条件已移除，不再返回
            foreach ($nodes as &$n) {
                if (isset($n['amount_min'])) $n['amount_min'] = (float)$n['amount_min'];
                if (isset($n['amount_max'])) $n['amount_max'] = (float)$n['amount_max'];
                unset($n['activate_when']);
            }
            unset($n);
            $groups[] = [
                'condition' => !empty($cond[0]['field']) ? ['field' => $cond[0]['field'], 'value' => $cond[0]['value'] ?? ''] : null,
                // v2.38.22：流程级金额条件回填
                'use_amount' => (int)($flow['use_amount'] ?? 0),
                'min_amount' => isset($flow['min_amount']) && $flow['min_amount'] !== '' ? (float)$flow['min_amount'] : '',
                'max_amount' => isset($flow['max_amount']) && $flow['max_amount'] !== '' ? (float)$flow['max_amount'] : '',
                'nodes'     => $nodes,
                'cc'        => json_decode($flow['cc_list'] ?? '{}', true) ?: [],
            ];
        }
        return $groups;
    }
}
