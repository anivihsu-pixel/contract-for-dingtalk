<?php
// +----------------------------------------------------------------------
// | 系统管理业务逻辑（GOLF 分层铁律下沉：从 AdminController 提取 Db 直查）
// | 系统配置/用户/角色/审批流均为全局配置实体，按铁律不附加行级数据范围。
// +----------------------------------------------------------------------

namespace app\common\logic;

use think\facade\Db;

class AdminLogic
{
    /** 批量预载：用户 → 角色 id 列表映射（P2-11【M-A2】消除系统管理页逐用户 getUserRoleIds N+1） */
    public static function getUserRoleIdsMap(array $userIds): array
    {
        $map = [];
        if (!$userIds) return $map;
        foreach (Db::name('user_role')->whereIn('user_id', $userIds)->select()->toArray() as $r) {
            $map[(int)$r['user_id']][] = (int)$r['role_id'];
        }
        return $map;
    }

    /** 批量预载：角色 → 权限 id 列表映射（P2-11【M-A2】消除系统管理页逐角色 getRolePermIds N+1） */
    public static function getRolePermIdsMap(array $roleIds): array
    {
        $map = [];
        if (!$roleIds) return $map;
        foreach (Db::name('role_permission')->whereIn('role_id', $roleIds)->select()->toArray() as $r) {
            $map[(int)$r['role_id']][] = (int)$r['perm_id'];
        }
        return $map;
    }

    /** 批量预载：角色 → 部门 id 列表映射（P2-11【M-A2】消除系统管理页逐角色 RbacService::getRoleDeptIds N+1） */
    public static function getRoleDeptIdsMap(array $roleIds): array
    {
        $map = [];
        if (!$roleIds) return $map;
        foreach (Db::name('role_dept')->whereIn('role_id', $roleIds)->select()->toArray() as $r) {
            $map[(int)$r['role_id']][] = (int)$r['dept_id'];
        }
        return $map;
    }

    /** 超级管理员角色（code=admin）id 集合（提权防护：非超管不得分配/编辑该角色） */
    public static function adminRoleIds(): array
    {
        return Db::name('role')->where('code', 'admin')->column('id');
    }

    /** 用户是否已绑定指定 code 的角色（超管判定/越权防护） */
    public static function userHasRoleCode(int $userId, string $code): bool
    {
        return Db::name('user_role')->alias('ur')
            ->join('role r', 'ur.role_id = r.id')
            ->where('ur.user_id', $userId)
            ->where('r.code', $code)
            ->count() > 0;
    }

    /** 角色编码（编辑时保留原 code） */
    public static function getRoleCode(int $roleId): string
    {
        return (string)Db::name('role')->where('id', $roleId)->value('code');
    }

    /** 生成唯一角色编码（r + 10 位随机小写；审批节点按 code 匹配角色，须全局唯一） */
    public static function generateRoleCode(): string
    {
        do {
            $code = 'r' . strtolower(substr(md5(uniqid((string)mt_rand(), true)), 0, 10));
        } while (Db::name('role')->where('code', $code)->find());
        return $code;
    }

    /** 角色编码是否已被占用（新建/编辑统一校验，$excludeId 排除自身） */
    public static function roleCodeExists(string $code, int $excludeId = 0): bool
    {
        $query = Db::name('role')->where('code', $code);
        if ($excludeId > 0) {
            $query->where('id', '<>', $excludeId);
        }
        return (bool)$query->find();
    }

    /** 启用中的审批流程列表（P2-8【M-A1】收敛重复逻辑：委托 ApprovalLogic 唯一实现，避免两处定义漂移） */
    public static function getEnabledFlows(): array
    {
        return \app\common\logic\ApprovalQueryService::getEnabledFlows();
    }

    /** 全部审批流程（含已停用）：管理列表展示用 */
    public static function getAllFlows(): array
    {
        return \app\common\logic\ApprovalQueryService::getAllFlows();
    }

    /**
     * 字典配置列表（group_name='dict' 全量；v2.28.2：从视图层 Db::name 下沉到 Logic）
     * 返回 system_config 表中 group_name='dict' 的所有行，由调用方解析 config_value JSON。
     */
    public static function getDicts(): array
    {
        $dicts = Db::name('system_config')
            ->where('group_name', 'dict')
            ->where('config_key', 'not like', 'dict_disabled_%') // v2.40.7：停用集合为元数据行，不视作字典
            ->select()->toArray();
        // v2.40.7：系统枚举字典标记（前端据此隐藏字典项删除按钮；后端删除保护见 saveConfig）
        foreach ($dicts as &$d) {
            $d['system'] = in_array($d['config_key'], self::SYSTEM_DICT_KEYS, true);
            $name        = str_starts_with($d['config_key'], 'dict_') ? substr($d['config_key'], 5) : $d['config_key'];
            $d['disabled'] = self::getDictDisabled($name);
        }
        return $dicts;
    }

    /**
     * v2.40.7：系统枚举字典（代码按 key 字符串做逻辑判断，删除项会导致显示退化或统计/流转失效）。
     * 保护粒度：禁止删除字典项、禁止整字典删除；允许修改显示名称、允许新增自定义项。
     * 注：customer_source / supplier_type / payment_method / invoice_type / tax_rate / industry 等
     *     业务数据型字典不在保护清单，保留自由增删（删除后历史数据仅显示回退编码，可加回恢复）。
     */
    public const SYSTEM_DICT_KEYS = [
        'dict_contract_status',
        'dict_payment_status',
        'dict_invoice_status',
        'dict_payment_milestone',
        'dict_customer_lifecycle',
        'dict_project_status',
        'dict_data_scope',
        'dict_contract_category',
    ];

    /** F6：发票申请表单字段配置全量（含停用项，按 sort_order 升序，供后台设计器编辑） */
    public static function getInvoiceFormFields(): array
    {
        return Db::name('invoice_form_field')
            ->order('sort_order', 'asc')->order('id', 'asc')
            ->select()->toArray();
    }

    /**
     * F6：保存发票申请表单字段配置（启停/排序/标签/必填 + 新增自定义字段 + F9 联动规则全量重存）。
     * 由 AdminController::saveInvoiceForm 下沉（P2-10【M-A1】控制器 Db 直查清零）。
     * 安全：字段类型白名单；系统字段仅可改 enabled/排序；联动动作白名单 + 字段存在性校验。
     * @param array $rows      既有字段行 [{id,field_label,field_type,field_options,required,enabled}]
     * @param array $newFields 新增自定义字段 [{field_label,field_type,field_options,required}]
     * @param array $linkage   联动规则 [{trigger_field,trigger_value,target_field,action,options}]
     * @return array ['ok'=>bool,'msg'=>string]
     */
    public static function saveInvoiceFormFields(array $rows, array $newFields, array $linkage): array
    {
        $db = Db::name('invoice_form_field');
        $types = \app\common\form\InvoiceFormConfig::types();

        // 排序从当前最大 sort_order 起累加，新增字段追加到末尾且不与既有字段冲突
        $sort = (int)$db->max('sort_order') + 10;
        $errs = [];
        foreach ($rows as $r) {
            $id = (int)($r['id'] ?? 0);
            $field = $db->find($id);
            if (!$field) { $errs[] = '字段不存在：#' . $id; continue; }
            $label = trim((string)($r['field_label'] ?? ''));
            if ($label === '') { $errs[] = '标签不能为空：' . ($field['field_key'] ?? $id); continue; }
            $type = (string)($r['field_type'] ?? $field['field_type']);
            if (!isset($types[$type])) { $errs[] = '非法字段类型：' . $type; continue; }
            $db->where('id', $id)->update([
                'field_label'   => $label,
                'field_type'    => $type,
                'field_options' => normalize_options($r['field_options'] ?? $field['field_options'] ?? ''),
                'required'      => !empty($r['required']) ? 1 : 0,
                'enabled'       => !empty($r['enabled']) ? 1 : 0,
                'sort_order'    => $sort,
                'updated_at'    => date('Y-m-d H:i:s'),
            ]);
            $sort += 10;
        }

        // 新增自定义字段（key 自动生成 field_custom_<时间戳>；is_system=0）
        foreach ($newFields as $nf) {
            $label = trim((string)($nf['field_label'] ?? ''));
            if ($label === '') continue;
            $type = (string)($nf['field_type'] ?? 'text');
            if (!isset($types[$type])) $type = 'text';
            $db->insert([
                'field_key'     => 'field_custom_' . time() . '_' . random_int(100, 999),
                'field_label'   => $label,
                'field_type'    => $type,
                'field_options' => normalize_options($nf['field_options'] ?? ''),
                'required'      => !empty($nf['required']) ? 1 : 0,
                'enabled'       => 1,
                'sort_order'    => $sort,
                'is_system'     => 0,
                'created_at'    => date('Y-m-d H:i:s'),
                'updated_at'    => date('Y-m-d H:i:s'),
            ]);
            $sort += 10;
        }

        // 联动规则全量重存（form_key=invoice_apply）——校验先行收集错误，再删除旧规则按序重插
        $linkDb = Db::name('form_field_linkage');
        // 独立新查询取字段键（避免复用 $db 被 max/where 链污染导致 column 异常）
        $allKeys = Db::name('invoice_form_field')->column('field_key');
        $linkErrors = [];
        $rules = [];
        $sort2 = 10;
        foreach ($linkage as $rule) {
            $trigger = (string)($rule['trigger_field'] ?? '');
            $target  = (string)($rule['target_field'] ?? '');
            $action  = (string)($rule['action'] ?? 'options');
            $triggerVal = (string)($rule['trigger_value'] ?? '');
            if ($trigger === '' || $target === '' || $trigger === $target) {
                $linkErrors[] = '联动规则触发/目标字段无效';
                continue;
            }
            if (!in_array($trigger, $allKeys, true) || !in_array($target, $allKeys, true)) {
                $linkErrors[] = "联动字段不存在：{$trigger}→{$target}";
                continue;
            }
            if (!in_array($action, ['show', 'hide', 'options'], true)) {
                $linkErrors[] = "非法联动动作：{$action}";
                continue;
            }
            $rules[] = [
                'form_key'      => 'invoice_apply',
                'trigger_field' => $trigger,
                'trigger_value' => $triggerVal,
                'target_field'  => $target,
                'action'        => $action,
                'options'       => $action === 'options' ? normalize_options($rule['options'] ?? '') : '[]',
                'sort_order'    => $sort2,
                'created_at'    => date('Y-m-d H:i:s'),
                'updated_at'    => date('Y-m-d H:i:s'),
            ];
            $sort2 += 10;
        }
        // 全量重存：删除旧规则后重插（保证与前端编辑结果一致，含已删除的规则）
        $linkDb->where('form_key', 'invoice_apply')->delete();
        if (!empty($rules)) {
            $linkDb->insertAll($rules);
        }

        if (!empty($errs) || !empty($linkErrors)) {
            return ['ok' => false, 'msg' => implode('；', array_merge($errs, $linkErrors))];
        }
        return ['ok' => true, 'msg' => '发票表单配置已保存'];
    }

    /** 通用表单字段全量（FormBuilder 设计器画布回填；options 归一化由调用方处理） */
    public static function getFormFields(string $table): array
    {
        return Db::name($table)
            ->order('sort_order', 'asc')->order('id', 'asc')
            ->select()->toArray();
    }

    /**
     * 通用表单 Step1 保存：字段 + 联动全量重存（FormBuilderController::saveForm 下沉）。
     * 画布删除的非系统字段物理删除；系统字段保留但可由 enabled 控制停用。
     * @return array ['ok'=>bool,'msg'=>string]
     */
    public static function saveFormFields(string $form, string $table, array $fields, array $linkage): array
    {
        $types = \app\common\form\InvoiceFormConfig::types();
        $db = Db::name($table);
        $errs = [];

        // 1) 字段全量重存：保留既有记录更新，新增插入（画布删除的字段物理删除，非系统字段）
        $existingIds = $db->column('id');
        $keepIds = [];
        $sort = 10;
        foreach ($fields as $f) {
            $key = (string)($f['key'] ?? '');
            $label = trim((string)($f['label'] ?? ''));
            $type = (string)($f['type'] ?? 'text');
            if ($key === '' || $label === '') { $errs[] = '存在未命名的字段'; continue; }
            if (!isset($types[$type])) { $errs[] = "非法字段类型：{$type}"; continue; }
            $optionsJson = in_array($type, ['select', 'radio', 'checkbox']) ? normalize_options($f['options'] ?? '') : '[]';
            $optionLayout = in_array($type, ['radio', 'checkbox']) ? (string)($f['option_layout'] ?? 'column') : 'column';
            if (!in_array($optionLayout, ['column', 'tile'], true)) $optionLayout = 'column';
            $id = (int)($f['id'] ?? 0);
            $isSystem = !empty($f['is_system']) ? 1 : 0;
            $data = [
                'field_label'   => $label,
                'field_type'    => $type,
                'field_options' => $optionsJson,
                'option_layout' => $optionLayout,
                'required'      => !empty($f['required']) ? 1 : 0,
                'enabled'       => !empty($f['enabled']) ? 1 : 0,
                'sort_order'    => $sort,
                'is_system'     => $isSystem,
                'updated_at'    => date('Y-m-d H:i:s'),
            ];
            if ($id && in_array($id, $existingIds, true)) {
                $db->where('id', $id)->update($data);
                $keepIds[] = $id;
            } else {
                $newId = $db->insertGetId(array_merge($data, [
                    'field_key'  => $key,
                    'created_at' => date('Y-m-d H:i:s'),
                ]));
                $keepIds[] = $newId;
            }
            $sort += 10;
        }
        // 物理删除画布移除的非系统字段（系统字段保留但可停用——由 enabled 控制）
        if (!empty($existingIds)) {
            $delIds = array_values(array_diff($existingIds, $keepIds));
            if (!empty($delIds)) {
                $db->whereIn('id', $delIds)->where('is_system', 0)->delete();
            }
        }

        // 2) 联动全量重存（form_key 维度）
        $linkDb = Db::name('form_field_linkage');
        $allKeys = Db::name($table)->column('field_key');
        // 字段类型映射（独立查询：options 动作目标必须为下拉类，否则联动选项无法渲染）
        $fieldTypes = Db::name($table)->column('field_type', 'field_key');
        $rules = [];
        $sort2 = 10;
        foreach ($linkage as $rule) {
            $trigger = (string)($rule['trigger_field'] ?? '');
            $target  = (string)($rule['target_field'] ?? '');
            $action  = (string)($rule['action'] ?? 'options');
            if ($trigger === '' || $target === '' || $trigger === $target) { $errs[] = '联动规则触发/目标字段无效'; continue; }
            if (!in_array($trigger, $allKeys, true) || !in_array($target, $allKeys, true)) { $errs[] = "联动字段不存在：{$trigger}→{$target}"; continue; }
            if (!in_array($action, ['show', 'hide', 'options', 'fill'], true)) { $errs[] = "非法联动动作：{$action}"; continue; }
            // 多下拉联动：替换选项动作的目标字段必须是下拉类（select/company），否则联动选项无处渲染
            if ($action === 'options' && !in_array($fieldTypes[$target] ?? '', ['select', 'company', 'radio', 'checkbox'], true)) {
                $errs[] = "「替换选项」目标字段「{$target}」需为下拉选择类型（请在字段画布中把该字段类型改为下拉选择）";
                continue;
            }
            // H3：填充值动作须带来源字段（触发数据行字段，如客户复用的 name/credit_code）
            if ($action === 'fill') {
                $src = trim((string)($rule['options'][0]['source_field'] ?? ''));
                if ($src === '') { $errs[] = "「填充值」目标字段「{$target}」未配置来源字段"; continue; }
                $rule['options'] = json_encode([['source_field' => $src]], JSON_UNESCAPED_UNICODE);
            }
            $rules[] = [
                'form_key'      => $form,
                'trigger_field' => $trigger,
                'trigger_value' => (string)($rule['trigger_value'] ?? ''),
                'target_field'  => $target,
                'action'        => $action,
                'options'       => $action === 'options' ? normalize_options($rule['options'] ?? '')
                    : ($action === 'fill' ? (string)($rule['options'] ?? '[]') : '[]'),
                'sort_order'    => $sort2,
                'created_at'    => date('Y-m-d H:i:s'),
                'updated_at'    => date('Y-m-d H:i:s'),
            ];
            $sort2 += 10;
        }
        $linkDb->where('form_key', $form)->delete();
        if (!empty($rules)) $linkDb->insertAll($rules);

        if (!empty($errs)) return ['ok' => false, 'msg' => implode('；', $errs)];
        return ['ok' => true, 'msg' => '表单配置已保存'];
    }

    /** 轻量更新表单字段选项（FormBuilder 开票内容下拉等，避免全量重存误伤其它字段/排序） */
    public static function updateFormFieldOptions(string $table, string $fieldKey, string $options): void
    {
        Db::name($table)->where('field_key', $fieldKey)->update([
            'field_options' => $options,
            'updated_at'    => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * 部门负责人映射（S-08：自 AdminController::index 下沉，消除控制器 Db 直查）
     * 返回 [dept_id => leader_user_id]，仅含已设置负责人的部门。
     */
    public static function getLeaderMap(): array
    {
        return Db::name('department')->where('leader_user_id', '>', 0)->column('leader_user_id', 'id');
    }

    /** 新增/编辑用户，返回用户 id（密码哈希等由调用方在 $data 中提供） */
    public static function saveUser(int $id, array $data): int
    {
        // 部门负责人标记不落 user 表，仅用于控制 department.leader_user_id
        $isLeader = null;
        if (array_key_exists('is_leader', $data)) {
            $isLeader = (int)$data['is_leader'];
            unset($data['is_leader']);
        }
        if ($id) {
            $oldIsAdmin = Db::name('user')->where('id', $id)->value('is_admin');
            $oldDept = Db::name('user')->where('id', $id)->value('dept_id');
            $data['updated_at'] = date('Y-m-d H:i:s');
            Db::name('user')->where('id', $id)->update($data);
            // RV-01：is_admin 变更时自增权限版本，使该用户已登录会话下次请求自动刷新权限
            // 注意：须在 update 之前读取旧值，否则更新后读取到的已是新值，比较恒等、自增失效
            if (array_key_exists('is_admin', $data) && (int)$oldIsAdmin !== (int)$data['is_admin']) {
                Db::name('user')->where('id', $id)->inc('perm_version')->update();
            }
            // 部门负责人变更（控制 department.leader_user_id，支撑部门经理审批节点）
            if ($isLeader !== null) {
                $newDept = (int)($data['dept_id'] ?? $oldDept);
                if ((int)$oldDept !== $newDept && (int)$oldDept > 0) {
                    Db::name('department')->where('id', $oldDept)->where('leader_user_id', $id)->update(['leader_user_id' => 0]);
                }
                if ($isLeader && $newDept > 0) {
                    Db::name('department')->where('id', $newDept)->update(['leader_user_id' => $id]);
                } elseif (!$isLeader && $newDept > 0) {
                    Db::name('department')->where('id', $newDept)->where('leader_user_id', $id)->update(['leader_user_id' => 0]);
                }
            }
        } else {
            $data['created_at'] = date('Y-m-d H:i:s');
            $data['updated_at'] = $data['created_at'];
            $id = Db::name('user')->insertGetId($data);
            if ($isLeader && (int)($data['dept_id'] ?? 0) > 0) {
                Db::name('department')->where('id', (int)$data['dept_id'])->update(['leader_user_id' => $id]);
            }
        }
        return $id;
    }

    /** 禁用用户（status=2，进入回收站） */
    public static function disableUser(int $id): void
    {
        Db::name('user')->where('id', $id)->update(['status' => 2, 'updated_at' => date('Y-m-d H:i:s')]);
    }

    /**
     * 统计用户进行中的审批数（v2.47.2）：作为审批人待处理的记录（PENDING 且实例进行中）
     * + 作为提交人仍审批中的实例。禁用前校验用——有进行中审批即禁用会使其成为
     * 无人能审批/撤回的僵尸审批（对应合同卡死在审批中，普通删除被拦截）。
     * @return int 进行中的审批数（含审批人待办 + 已提交未完结）
     */
    public static function countPendingApprovals(int $userId): int
    {
        $asApprover = Db::name('approval_record')->alias('ar')
            ->join('approval_instance ai', 'ar.instance_id = ai.id')
            ->where('ar.approver_id', $userId)
            ->where('ar.action', 'PENDING')
            ->where('ai.status', 'PENDING')
            ->count();
        $asSubmitter = Db::name('approval_instance')
            ->where('submitted_by', $userId)
            ->where('status', 'PENDING')
            ->count();
        return (int)$asApprover + (int)$asSubmitter;
    }

    /**
     * 离职交接（v2.38.16）：将某用户的客户/合同/待审批批量转移给接收人，并按需禁用离职用户。
     * 事务内执行，任一环节失败整体回滚；审计日志在事务外记录（不随业务回滚）。
     * @param int   $fromUserId 离职用户 id
     * @param int   $toUserId   接收人 id（须在职）
     * @param array $scope      交接范围 ['customer'=>bool, 'contract'=>bool, 'approval'=>bool]
     * @param bool  $disableFrom 交接完成后是否禁用离职用户
     * @return array ['ok'=>bool, 'msg'=>string, 'counts'=>['customer'=>int,'contract'=>int,'approval'=>int]]
     */
    public static function handoverUser(int $fromUserId, int $toUserId, array $scope, bool $disableFrom = true): array
    {
        $counts = ['customer' => 0, 'contract' => 0, 'approval' => 0];
        try {
            // 有效性校验：接收人必须是在职用户（离职/禁用用户不能作为接收人）
            $toUser = Db::name('user')->where('id', $toUserId)->where('status', 1)->find();
            if (!$toUser) {
                return ['ok' => false, 'msg' => '接收人不存在或已被禁用', 'counts' => $counts];
            }
            if ($fromUserId <= 0 || $fromUserId === $toUserId) {
                return ['ok' => false, 'msg' => '交接人无效', 'counts' => $counts];
            }

            Db::transaction(function () use ($fromUserId, $toUserId, $toUser, $scope, $disableFrom, &$counts) {
                $now = date('Y-m-d H:i:s');
                $toDeptId = (int)($toUser['dept_id'] ?? 0);

                // 1) 客户批量转移（owner_id=from → to，部门同步，生命周期保持业务态）
                if (!empty($scope['customer'])) {
                    $custIds = Db::name('customer')->where('owner_id', $fromUserId)->where('is_deleted', 0)->column('id');
                    foreach ($custIds as $cid) {
                        Db::name('customer')->where('id', $cid)->where('owner_id', $fromUserId)->update([
                            'owner_id'   => $toUserId,
                            'dept_id'    => $toDeptId,
                            'updated_at' => $now,
                        ]);
                        Db::name('customer_transfer_record')->insert([
                            'customer_id'  => $cid,
                            'from_user_id' => $fromUserId,
                            'to_user_id'   => $toUserId,
                            'created_at'   => $now,
                        ]);
                        \app\common\logic\CustomerLogic::addActivity($cid, $toUserId, 'TRANSFER', '离职交接：从用户#' . $fromUserId . ' 转入');
                        $counts['customer']++;
                    }
                }

                // 2) 合同批量转移（owner_id=from → to，部门同步，creator_id 保留原值追溯；写变更日志）
                if (!empty($scope['contract'])) {
                    $contractIds = Db::name('contract')->where('owner_id', $fromUserId)->where('is_deleted', 0)->column('id');
                    $revisions = [];
                    foreach ($contractIds as $cid) {
                        Db::name('contract')->where('id', $cid)->where('owner_id', $fromUserId)->update([
                            'owner_id'   => $toUserId,
                            'dept_id'    => $toDeptId,
                            'updated_at' => $now,
                        ]);
                        $revisions[] = [
                            'contract_id' => $cid,
                            'field_name'  => 'owner_id',
                            'old_value'   => $fromUserId,
                            'new_value'   => $toUserId,
                            'operator_id' => 0, // 系统操作，操作人 0
                            'created_at'  => $now,
                        ];
                        $counts['contract']++;
                    }
                    if (!empty($revisions)) {
                        Db::name('contract_revision')->insertAll($revisions);
                    }
                }

                // 3) 待审批转交（approval_record.approver_id=from 且 PENDING → to；同一实例当前节点若已有接收人记录则跳过，避免重复待办）
                if (!empty($scope['approval'])) {
                    $pending = Db::name('approval_record')
                        ->where('approver_id', $fromUserId)
                        ->where('action', 'PENDING')
                        ->select()->toArray();
                    foreach ($pending as $rec) {
                        $dup = Db::name('approval_record')
                            ->where('instance_id', $rec['instance_id'])
                            ->where('node_order', $rec['node_order'])
                            ->where('approver_id', $toUserId)
                            ->where('action', 'PENDING')
                            ->find();
                        if ($dup) {
                            continue; // 接收人已是该节点审批人，跳过避免重复
                        }
                        Db::name('approval_record')->where('id', $rec['id'])->update(['approver_id' => $toUserId]);
                        $counts['approval']++;
                    }
                }

                // 4) 按需禁用离职用户；无论是否禁用，交接后一律清除「待交接」标记（v2.38.25 自动化队列）
                $uData = ['need_handover' => 0, 'updated_at' => $now];
                if ($disableFrom) {
                    $uData['status'] = 2;
                }
                Db::name('user')->where('id', $fromUserId)->update($uData);
            });

            return ['ok' => true, 'msg' => '交接完成', 'counts' => $counts];
        } catch (\Throwable $e) {
            \think\facade\Log::error('离职交接失败，已回滚', [
                'from_user_id' => $fromUserId,
                'to_user_id'   => $toUserId,
                'error'        => $e->getMessage(),
                'trace'        => $e->getTraceAsString(),
            ]);
            return ['ok' => false, 'msg' => '交接失败：' . $e->getMessage(), 'counts' => $counts];
        }
    }

    /** 恢复禁用用户（status=1，从回收站恢复为在职）；恢复即视为非待交接，同步清除待交接标记 */
    public static function restoreUser(int $id): void
    {
        Db::name('user')->where('id', $id)->update(['status' => 1, 'need_handover' => 0, 'updated_at' => date('Y-m-d H:i:s')]);
    }

    /** 清除待交接标记（v2.38.25）：管理员确认该员工并未离职（如误报/已回岗），仅清标记，不改状态不做交接 */
    public static function clearHandover(int $id): void
    {
        Db::name('user')->where('id', $id)->update(['need_handover' => 0, 'updated_at' => date('Y-m-d H:i:s')]);
    }

    /**
     * 待交接用户列表（v2.38.25）：钉钉同步自动标记 need_handover=1 的疑似离职员工（仅在职），
     * 附名下客户/合同/待审批数量供决策。PC 用户管理页与移动端待交接页共用。
     * @return array [['id','username','name','mobile','dept_id','dingtalk_userid','updated_at','dept_name',
     *                'customer_count','contract_count','approval_count'], ...]
     */
    public static function getHandoverUsers(): array
    {
        $list = Db::name('user')->alias('u')
            ->leftJoin('department d', 'u.dept_id = d.id')
            ->field('u.id, u.username, u.name, u.mobile, u.dept_id, u.dingtalk_userid, u.updated_at, d.name as dept_name')
            ->where('u.need_handover', 1)
            ->where('u.status', 1)
            ->order('u.updated_at', 'desc')
            ->select()->toArray();

        // P2-1：消除 N+1——一次性收集 userIds，3 次 whereIn GROUP BY 预聚合后映射回列表
        $userIds = array_values(array_unique(array_map('intval', array_column($list, 'id'))));
        $custCnt = $contCnt = $apprCnt = [];
        if ($userIds) {
            foreach (Db::name('customer')->whereIn('owner_id', $userIds)->where('is_deleted', 0)
                ->field('owner_id, COUNT(*) AS cnt')->group('owner_id')->select()->toArray() as $r) {
                $custCnt[(int)$r['owner_id']] = (int)$r['cnt'];
            }
            foreach (Db::name('contract')->whereIn('owner_id', $userIds)->where('is_deleted', 0)
                ->field('owner_id, COUNT(*) AS cnt')->group('owner_id')->select()->toArray() as $r) {
                $contCnt[(int)$r['owner_id']] = (int)$r['cnt'];
            }
            foreach (Db::name('approval_record')->whereIn('approver_id', $userIds)->where('action', 'PENDING')
                ->field('approver_id, COUNT(*) AS cnt')->group('approver_id')->select()->toArray() as $r) {
                $apprCnt[(int)$r['approver_id']] = (int)$r['cnt'];
            }
        }
        foreach ($list as &$hu) {
            $hu['customer_count'] = $custCnt[(int)$hu['id']] ?? 0;
            $hu['contract_count'] = $contCnt[(int)$hu['id']] ?? 0;
            $hu['approval_count'] = $apprCnt[(int)$hu['id']] ?? 0;
        }
        return $list;
    }

    /** 新增/编辑审批流程，返回流程 id */
    public static function saveFlow(int $id, array $data): int
    {
        if ($id) {
            $data['updated_at'] = date('Y-m-d H:i:s');
            Db::name('approval_flow')->where('id', $id)->update($data);
        } else {
            if (!isset($data['creator_id'])) {
                $data['creator_id'] = 0;
            }
            // v2.38.24：新流程追加到同类型末尾（sort_order = 同类型最大值 + 1）
            $bizType = (string)($data['biz_type'] ?? 'contract');
            if ($bizType === '') $bizType = 'contract';
            $data['sort_order'] = (int)Db::name('approval_flow')->where('biz_type', $bizType)->max('sort_order') + 1;
            $data['created_at'] = date('Y-m-d H:i:s');
            $data['updated_at'] = $data['created_at'];
            $id = Db::name('approval_flow')->insertGetId($data);
        }
        return $id;
    }

    /** 停用审批流程（软删除：status=0） */
    public static function disableFlow(int $id): void
    {
        Db::name('approval_flow')->where('id', $id)->update(['status' => 0]);
    }

    /** 恢复审批流程（status=1，重新参与新合同审批匹配） */
    public static function enableFlow(int $id): void
    {
        Db::name('approval_flow')->where('id', $id)->update(['status' => 1, 'updated_at' => date('Y-m-d H:i:s')]);
    }

    /** 审批流程编码（编辑时保留原 code；合同流程匹配按分类+金额，code 仅作标识） */
    public static function getFlowCode(int $id): string
    {
        return (string)Db::name('approval_flow')->where('id', $id)->value('code');
    }

    /** 生成唯一合同流程编码（CONTRACT + 8 位随机；新建时使用） */
    public static function generateFlowCode(): string
    {
        do {
            $code = 'CONTRACT' . substr(md5(uniqid((string)mt_rand(), true)), 0, 8);
        } while (Db::name('approval_flow')->where('code', $code)->find());
        return $code;
    }

    /** 合同流程编码是否已存在（新建唯一性校验） */
    public static function flowCodeExists(string $code): bool
    {
        return (bool)Db::name('approval_flow')->where('code', $code)->find();
    }

    /**
     * 全量保存合同审批流程（v2.38.22 画布式编辑器：分支卡片并列，一次提交全部流程）。
     * 由 AdminController::saveAllFlows 下沉（P2-10【M-A1】控制器 Db 直查清零）。
     * 语义：全量重存——本次提交的流程更新/新增；原库中本次未提交的流程停用（status=0，保留历史实例关联）。
     * @param array $flows     流程行 [{id,name,code,category_list,use_amount,min_amount,max_amount,status,nodes,cc_list}]
     * @param int   $operatorId 操作人 id（新流程 creator_id）
     * @return array ['ok'=>bool,'msg'=>string]
     */
    public static function saveAllFlowsList(array $flows, int $operatorId): array
    {
        if (empty($flows)) return ['ok' => false, 'msg' => '未提交任何流程'];

        // 收集现有合同流程 id 集合（含停用），用于停用本次未提交的
        $existingIds = Db::name('approval_flow')->where('biz_type', 'contract')->column('id');
        $keepIds = [];
        $errs = [];

        foreach ($flows as $g) {
            $name = trim((string)($g['name'] ?? ''));
            $code = trim((string)($g['code'] ?? ''));
            if ($name === '' || $code === '') { $errs[] = '存在未命名的流程分支'; continue; }

            // 分类多选：优先 category_list(JSON)，回退遗留单值 category
            $catList = [];
            $clRaw = $g['category_list'] ?? '';
            if (is_string($clRaw) && $clRaw !== '') {
                $decoded = json_decode($clRaw, true);
                if (is_array($decoded)) $catList = array_values(array_filter($decoded, fn($v) => $v !== ''));
            }
            if (empty($catList) && !empty($g['category'])) {
                $catList = array_values(array_filter(explode(',', (string)$g['category']), fn($v) => $v !== ''));
            }

            // 节点 JSON 校验（高危路径：非法 nodes 拒绝保存，避免合同免审批直接通过）
            $nodes = $g['nodes'] ?? '[]';
            if (is_array($nodes)) $nodes = json_encode($nodes, JSON_UNESCAPED_UNICODE);
            if (!is_string($nodes) || json_decode($nodes) === null) { $errs[] = "流程「{$name}」节点数据格式不正确"; continue; }

            $cc = $g['cc_list'] ?? '[]';
            if (is_array($cc)) $cc = json_encode($cc, JSON_UNESCAPED_UNICODE);
            if (!is_string($cc) || json_decode($cc) === null) $cc = '[]';

            $useAmount = isset($g['use_amount']) && (string)$g['use_amount'] === '1' ? 1 : 0;
            $minAmount = isset($g['min_amount']) && $g['min_amount'] !== '' ? (float)$g['min_amount'] : 0;
            $maxAmount = isset($g['max_amount']) && $g['max_amount'] !== '' ? (float)$g['max_amount'] : 99999999.99;
            if ($minAmount < 0) $minAmount = 0;
            if ($maxAmount < $minAmount) $maxAmount = 99999999.99;

            $data = [
                'name'          => $name,
                'code'          => $code,
                'category'      => implode(',', $catList), // 遗留单值字段：存逗号分隔（兼容旧匹配逻辑）
                'category_list' => json_encode($catList, JSON_UNESCAPED_UNICODE),
                'use_amount'    => $useAmount,
                'min_amount'    => $minAmount,
                'max_amount'    => $maxAmount,
                'nodes'         => $nodes,
                'cc_list'       => $cc,
                'status'        => isset($g['status']) ? (int)$g['status'] : 1,
                'updated_at'    => date('Y-m-d H:i:s'),
            ];

            $id = (int)($g['id'] ?? 0);
            if ($id && in_array($id, $existingIds, true)) {
                // 独立查询更新：复用同一构建器会累积 where 条件致后续 update 误伤/0 行
                Db::name('approval_flow')->where('id', $id)->update($data);
                $keepIds[] = $id;
            } else {
                // v2.38.24：新流程追加到同类型末尾
                $maxSort = (int)Db::name('approval_flow')->where('biz_type', 'contract')->max('sort_order');
                $newId = Db::name('approval_flow')->insertGetId(array_merge($data, [
                    'biz_type'   => 'contract',
                    'sort_order' => $maxSort + 1,
                    'creator_id' => $operatorId,
                    'created_at' => date('Y-m-d H:i:s'),
                ]));
                $keepIds[] = $newId;
            }
        }

        if (!empty($errs)) return ['ok' => false, 'msg' => implode('；', array_unique($errs))];

        // 停用本次未提交的旧流程（软删除，保留历史审批实例关联）
        // 注意：独立查询避免构建器 where 累积致 update 误伤/0 行
        if (!empty($existingIds)) {
            $staleIds = array_values(array_diff($existingIds, $keepIds));
            if (!empty($staleIds)) {
                Db::name('approval_flow')->where('biz_type', 'contract')->whereIn('id', $staleIds)->update(['status' => 0]);
            }
        }
        return ['ok' => true, 'msg' => '保存成功'];
    }

    /** 审批流程拖动排序（v2.38.24：同类流程内 sort_order=1..N 重新编号，靠前优先级越高） */
    public static function sortFlowsByIds(array $ids): void
    {
        // 校验存在性并按 biz_type 分组（空值兼容旧数据视为 contract）
        $rows = Db::name('approval_flow')->whereIn('id', $ids)->field('id, biz_type')->select()->toArray();
        $bizMap = [];
        foreach ($rows as $r) {
            $biz = (string)($r['biz_type'] ?? 'contract');
            $bizMap[(int)$r['id']] = $biz === '' ? 'contract' : $biz;
        }
        // 按传入顺序分配序号（每组独立 1..N；每次独立查询避免构建器 where 累积）
        $groupCnt = [];
        $now = date('Y-m-d H:i:s');
        foreach ($ids as $id) {
            if (!isset($bizMap[$id])) continue; // 过滤不存在的 id
            $biz = $bizMap[$id];
            $groupCnt[$biz] = ($groupCnt[$biz] ?? 0) + 1;
            Db::name('approval_flow')->where('id', $id)
                ->update(['sort_order' => $groupCnt[$biz], 'updated_at' => $now]);
        }
    }

    /**
     * 彻底删除审批流程（永久删除；仅当无审批实例引用时允许，保护历史关联）
     * @return array ['ok'=>bool,'msg'=>string]
     */
    public static function purgeFlowById(int $id): array
    {
        // 历史审批实例引用保护：已有审批实例的流程不可彻底删除
        $instCnt = Db::name('approval_instance')->where('flow_id', $id)->count();
        if ($instCnt > 0) {
            return ['ok' => false, 'msg' => "该流程已有 {$instCnt} 条审批实例，无法彻底删除（历史审批关联不可破坏）"];
        }
        Db::name('approval_flow')->where('id', $id)->delete();
        return ['ok' => true, 'msg' => '已彻底删除'];
    }

    /** 表单专用审批流原始数据（FormBuilder Step2 回填；按 sort_order,id 升序，JSON 解析由调用方处理） */
    public static function getFlowGroups(string $bizType): array
    {
        return Db::name('approval_flow')
            ->where('biz_type', $bizType)
            ->order('sort_order', 'asc')  // v2.38.24：同类型内手动排序优先（默认流程/条件分支顺序稳定）
            ->order('id', 'asc')
            ->select()->toArray();
    }

    /**
     * 全量保存表单专用审批流（默认组 + 条件分支；本次未提交的旧流停用不物理删除）。
     * 由 FormBuilderController::saveFlow 下沉（P2-10【M-A1】控制器 Db 直查清零）。
     * @param array  $groups     已校验分组 [{condition,amount:{use,min,max},nodes,cc}]
     * @param string $bizType    审批业务类型（invoice）
     * @param string $flowPrefix 流程编码前缀（INVOICE）
     * @param int    $operatorId 操作人 id
     * @return array ['ok'=>bool,'msg'=>string]
     */
    public static function saveFlowGroups(array $groups, string $bizType, string $flowPrefix, int $operatorId): array
    {
        $existing = Db::name('approval_flow')->where('biz_type', $bizType)->where('code', 'like', $flowPrefix . '%')->column('id', 'code');
        $keepCodes = [];
        foreach ($groups as $g) {
            $code = $flowPrefix;
            if (!empty($g['condition'])) {
                // 条件分支 code 唯一：<PREFIX>_<field>_<value>（非字母数字下划线归一化）
                $code = $flowPrefix . '_' . preg_replace('/[^A-Za-z0-9_]/', '_', $g['condition']['field']) . '_' . preg_replace('/[^A-Za-z0-9_]/', '_', $g['condition']['value']);
            }
            $keepCodes[] = $code;
            $data = [
                'name'            => '发票审批',
                'biz_type'        => $bizType,
                'form_condition'  => $g['condition'] ? json_encode([$g['condition']], JSON_UNESCAPED_UNICODE) : '',
                // v2.38.22：流程级金额条件（原审批流 use_amount/min_amount/max_amount）
                'use_amount'      => (int)$g['amount']['use'],
                'min_amount'      => (float)$g['amount']['min'],
                'max_amount'      => (float)$g['amount']['max'],
                'nodes'           => json_encode($g['nodes'], JSON_UNESCAPED_UNICODE),
                'cc_list'         => json_encode($g['cc'], JSON_UNESCAPED_UNICODE),
                'status'          => 1,
                'updated_at'      => date('Y-m-d H:i:s'),
            ];
            if (isset($existing[$code])) {
                // 独立查询避免构建器 where 累积
                Db::name('approval_flow')->where('id', (int)$existing[$code])->update($data);
            } else {
                // v2.38.24：新流程追加到同类型（发票）末尾
                $maxSort = (int)Db::name('approval_flow')->where('biz_type', $bizType)->max('sort_order');
                Db::name('approval_flow')->insert(array_merge($data, [
                    'code'       => $code,
                    'sort_order' => $maxSort + 1,
                    'creator_id' => $operatorId,
                    'created_at' => date('Y-m-d H:i:s'),
                ]));
            }
        }
        if (!empty($existing)) {
            $staleCodes = array_values(array_diff(array_keys($existing), $keepCodes));
            if (!empty($staleCodes)) {
                Db::name('approval_flow')->where('biz_type', $bizType)->whereIn('code', $staleCodes)->update(['status' => 0]);
            }
        }
        return ['ok' => true, 'msg' => '审批与抄送设置已保存'];
    }

    /** 取单条用户（改密鉴权用） */
    public static function getUserById(int $userId): ?array
    {
        return Db::name('user')->find($userId) ?: null;
    }

    /** 更新用户密码哈希（并清除强制改密标记） */
    public static function updateUserPassword(int $userId, string $hash): void
    {
        Db::name('user')->where('id', $userId)->update([
            'password'    => $hash,
            'force_reset' => 0,
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * 清除合同分类相关的两处读取缓存，确保字典/分类变更后创建合同页即时生效。
     * - contract_categories：common.php contract_categories() 的 Cache::remember 键
     * - dict_contract_category：common.php dict() 的 Cache::remember 键
     */
    private static function clearCategoryCaches(): void
    {
        \think\facade\Cache::delete('contract_categories');
        \think\facade\Cache::delete('dict_contract_category');
    }

    /**
     * 清除 dict() 读取缓存（P1-5：字典缓存键与 system_config.config_key 同为 "dict_<name>"）。
     * 非分类字典（supplier_type/project_status/customer_lifecycle 等）变更后即时生效，不再依赖 300s TTL。
     */
    private static function clearDictCache(string $key): void
    {
        if (str_starts_with($key, 'dict_')) {
            \think\facade\Cache::delete($key);
            // v2.40.7：停用集合缓存一并失效（dict_disabled_<name>）
            \think\facade\Cache::delete('dict_disabled_' . substr($key, 5));
        }
    }

    /**
     * v2.40.7：读取字典停用集合（dict_disabled_{name} 配置行，JSON 数组存停用 KEY）。
     * @param string $name 字典名（不带 dict_ 前缀，如 contract_category）
     */
    private static function getDictDisabled(string $name): array
    {
        $json = Db::name('system_config')->where('config_key', 'dict_disabled_' . $name)->value('config_value');
        return $json ? (json_decode($json, true) ?: []) : [];
    }

    /**
     * 根据中文名自动生成字典项编码（拼音首字母大写）
     * 编码冲突时追加数字后缀（如 GGMT / GGMT1 / GGMT2），保证字典内唯一
     * @param string $label 中文名（如"广告媒体"）
     * @param array $existingItems 当前字典已有项 [code=>label]
     */
    private static function generateDictItemKey(string $label, array $existingItems): string
    {
        $base = pinyin_initials($label);
        if ($base === '' || $base === 'ITEM') {
            $base = 'ITEM';
        }
        if (!isset($existingItems[$base])) {
            return $base;
        }
        $i = 1;
        while (isset($existingItems[$base . $i])) {
            $i++;
        }
        return $base . $i;
    }

    public static function saveContractCategories(array $cats): void
    {
        $json = json_encode($cats, JSON_UNESCAPED_UNICODE);
        // REV-51：合同分类存在双键（dict_contract_category 与 contract_categories），
        // contract_categories() 优先读 dict_contract_category，故两键须同步写入，避免"写 contract_categories 被 dict 遮蔽"的死写。
        foreach (['dict_contract_category', 'contract_categories'] as $ck) {
            $exists = Db::name('system_config')->where('config_key', $ck)->find();
            if ($exists) {
                Db::name('system_config')->where('config_key', $ck)->update(['config_value' => $json]);
            } else {
                Db::name('system_config')->insert([
                    'config_key'   => $ck,
                    'config_value' => $json,
                    'group_name'   => $ck === 'dict_contract_category' ? 'dict' : 'contract',
                ]);
            }
        }
        self::clearCategoryCaches();
    }

    /**
     * 保存系统配置（含字典项新增/更新/删除、整字典删除、普通配置保存）
     * @return array ['ok'=>bool, 'msg'=>string]
     */
    public static function saveConfig(string $key, string $value, string $itemKey = '', string $itemValue = '', string $oldKey = ''): array
    {
        // 字典项新增/更新
        if ($value === '__UPDATE_ITEM__') {
            if (!$itemValue) {
                return ['ok' => false, 'msg' => '中文名不能为空'];
            }
            $existing = Db::name('system_config')->where('config_key', $key)->find();
            $items    = $existing ? (json_decode($existing['config_value'], true) ?: []) : [];
            // 编辑：保持原编码不变（业务表引用的是编码）；新增：自动生成拼音首字母编码
            if ($oldKey) {
                $itemKey = $oldKey;
            } else {
                $itemKey = self::generateDictItemKey($itemValue, $items);
            }
            $items[$itemKey] = $itemValue;
            $json = json_encode($items, JSON_UNESCAPED_UNICODE);
            if ($existing) {
                Db::name('system_config')->where('config_key', $key)->update(['config_value' => $json]);
            } else {
                Db::name('system_config')->insert([
                    'config_key'   => $key,
                    'config_value' => $json,
                    'group_name'   => 'dict',
                ]);
            }
            // P1-5：任何字典变更统一清 dict() 读取缓存（含非分类字典），不依赖 300s TTL
            self::clearDictCache($key);
            if ($key === 'dict_contract_category') { self::clearCategoryCaches(); }
            return ['ok' => true, 'msg' => '字典项已保存'];
        }

        // 字典项停用/启用切换（v2.40.7：停用仅影响选项下拉 dict_options/dict_enabled，
        // 浏览/筛选/统计与 label 解析 dict() 全量不受影响，历史数据照常显示）
        if ($value === '__TOGGLE_ITEM__') {
            if (!$itemKey) {
                return ['ok' => false, 'msg' => '缺少字典项编码'];
            }
            $name     = str_starts_with($key, 'dict_') ? substr($key, 5) : $key;
            $disabled = self::getDictDisabled($name);
            $wasOff   = in_array($itemKey, $disabled, true);
            if ($wasOff) {
                $disabled = array_values(array_filter($disabled, fn($k) => $k !== $itemKey));
            } else {
                $disabled[] = $itemKey;
            }
            $metaKey = 'dict_disabled_' . $name;
            $exists  = Db::name('system_config')->where('config_key', $metaKey)->find();
            if ($exists) {
                Db::name('system_config')->where('config_key', $metaKey)->update(['config_value' => json_encode(array_values($disabled), JSON_UNESCAPED_UNICODE)]);
            } else {
                Db::name('system_config')->insert([
                    'config_key'   => $metaKey,
                    'config_value' => json_encode(array_values($disabled), JSON_UNESCAPED_UNICODE),
                    'group_name'   => 'dict_meta',
                ]);
            }
            // P1-5：停用集合与字典缓存统一即时失效
            self::clearDictCache($key);
            if ($key === 'dict_contract_category') { self::clearCategoryCaches(); }
            return ['ok' => true, 'msg' => $wasOff ? '已启用' : '已停用'];
        }

        // 字典项删除
        if ($value === '__DELETE_ITEM__') {
            // v2.40.7：系统枚举字典项禁止删除（防止状态机/统计/流程匹配引用的 key 被删，显示退化英文编码）
            if (in_array($key, self::SYSTEM_DICT_KEYS, true)) {
                return ['ok' => false, 'msg' => '系统枚举字典项不可删除（可修改显示名称或新增自定义项）'];
            }
            $existing = Db::name('system_config')->where('config_key', $key)->find();
            if (!$existing) {
                return ['ok' => false, 'msg' => '字典不存在'];
            }
            $items = json_decode($existing['config_value'], true) ?: [];
            unset($items[$itemKey]);
            Db::name('system_config')->where('config_key', $key)
                ->update(['config_value' => json_encode($items, JSON_UNESCAPED_UNICODE)]);
            // P1-5：字典项删除同样即时清 dict() 读取缓存
            self::clearDictCache($key);
            if ($key === 'dict_contract_category') { self::clearCategoryCaches(); }
            return ['ok' => true, 'msg' => '字典项已删除'];
        }

        // 字典项拖动排序（v2.47.2：重排 config_value 键顺序即全站下拉/筛选显示顺序；
        // item_key 传新顺序的编码列表，逗号分隔，仅含启用项；停用项保持原相对顺序追加末尾）
        if ($value === '__REORDER_ITEMS__') {
            $existing = Db::name('system_config')->where('config_key', $key)->find();
            if (!$existing) {
                return ['ok' => false, 'msg' => '字典不存在'];
            }
            $items = json_decode($existing['config_value'], true) ?: [];
            $order = array_values(array_filter(array_map('trim', explode(',', (string)$itemKey))));
            if (!$order) {
                return ['ok' => false, 'msg' => '缺少排序数据'];
            }
            $newItems = [];
            foreach ($order as $k) {
                if (array_key_exists($k, $items)) { $newItems[$k] = $items[$k]; }
            }
            foreach ($items as $k => $v) {
                if (!array_key_exists($k, $newItems)) { $newItems[$k] = $v; }
            }
            Db::name('system_config')->where('config_key', $key)
                ->update(['config_value' => json_encode($newItems, JSON_UNESCAPED_UNICODE)]);
            self::clearDictCache($key);
            if ($key === 'dict_contract_category') { self::clearCategoryCaches(); }
            return ['ok' => true, 'msg' => '排序已保存'];
        }

        // 删除整个字典
        if ($value === '__DELETE_DICT__') {
            // v2.40.7：系统枚举字典整体禁止删除（删除后 dict() 返回空，全站引用处回退英文编码）
            if (in_array($key, self::SYSTEM_DICT_KEYS, true)) {
                return ['ok' => false, 'msg' => '系统枚举字典不可删除'];
            }
            Db::name('system_config')->where('config_key', $key)->delete();
            if ($key === 'dict_contract_category') { self::clearCategoryCaches(); }
            self::clearDictCache($key);
            return ['ok' => true, 'msg' => '字典已删除'];
        }

        // 普通配置保存
        $exists = Db::name('system_config')->where('config_key', $key)->find();
        if ($exists) {
            Db::name('system_config')->where('config_key', $key)->update(['config_value' => $value]);
        } else {
            Db::name('system_config')->insert(['config_key' => $key, 'config_value' => $value]);
        }
        // v2.34.0：清除 sys_config() 短缓存，保证页脚版权等配置保存后即时生效
        \think\facade\Cache::delete('syscfg_' . $key);
        // P1-5：字典类配置变更统一清 dict() 读取缓存
        self::clearDictCache($key);
        if ($key === 'dict_contract_category') { self::clearCategoryCaches(); }
        return ['ok' => true, 'msg' => '保存成功'];
    }

    // ========================================================================
    // 系统配置备份 / 恢复（v2.36.0）
    // 导出范围：配置/组织/权限/流程/字典/钉钉配置/资料库，**不含 user 表**（避免密码哈希出域，
    //   同时满足用户「导出不含 user 表」的明确要求）。
    // 导入采用整簇「DELETE + 批量 INSERT 保留原 id」事务恢复，保证 role_permission 等
    //   靠 id join 的引用不断链（与 RBAC 教训一致：不可重排号）。
    // 表顺序即依赖顺序（先父后子），便于日志与排错。
    // ========================================================================

    /** 配置备份允许导出的表（不含 user）。dict=字典设置（system_config 中 dict_% 键的独立分区）、
     *  dingtalk=钉钉配置（.env 中 DINGTALK_*，恢复写回 .env）。 */
    public const CONFIG_BACKUP_TABLES = [
        'role', 'permission', 'role_permission', 'user_role',
        'department', 'company_profile', 'approval_flow',
        'resource_library', 'system_config', 'dict', 'dingtalk',
    ];

    /** 配置备份表的中文名（对应各表注释，备份/恢复 UI 展示用） */
    public const CONFIG_TABLE_LABELS = [
        'role'             => '角色',
        'permission'       => '权限',
        'role_permission'  => '角色权限关系',
        'user_role'        => '用户角色关系',
        'department'       => '部门',
        'company_profile'  => '本公司主体',
        'approval_flow'    => '审批流程',
        'resource_library' => '资料库',
        'system_config'    => '系统配置',
        'dict'             => '字典设置',
        'dingtalk'         => '钉钉配置',
    ];

    /** 表中文名（未知表回退原名） */
    public static function tableLabel(string $table): string
    {
        return self::CONFIG_TABLE_LABELS[$table] ?? $table;
    }

    /**
     * 按勾选表过滤恢复 payload（v2.45.1：恢复可自选表——未勾选表保持现状不覆盖）。
     * 只保留 CONFIG_BACKUP_TABLES 内的表；空选择回退全量（防误导出空恢复清空全部）。
     */
    public static function filterPayloadTables(array $payload, array $only): array
    {
        $only = array_values(array_intersect($only, self::CONFIG_BACKUP_TABLES));
        if (!$only) {
            return $payload;
        }
        $payload['tables']      = array_intersect_key($payload['tables'], array_flip($only));
        $payload['meta']['tables'] = $only;
        return $payload;
    }

    /**
     * 导出当前系统配置为结构化数组（含元信息），供下载。
     * @param array|null $selected 可选：仅导出勾选的表（null/空 = 全量导出）
     */
    public static function exportConfigArray(?array $selected = null): array
    {
        $tables = [];
        // 收敛选择集：仅允许 CONFIG_BACKUP_TABLES 内的表，并保持依赖顺序（先父后子）；空选择回退全量
        $allow  = array_flip(self::CONFIG_BACKUP_TABLES);
        $export = array_values(array_filter($selected ?? [], static fn($t) => isset($allow[$t])));
        if (!$export) {
            $export = self::CONFIG_BACKUP_TABLES;
        }
        // ④ 一致性快照（评估优化）：事务内读取——MySQL InnoDB 一致性读保证表处于同一时间点，
        // 避免并发修改导致表间引用（role_permission→role/permission）自相矛盾
        Db::transaction(function () use (&$tables, $export) {
            foreach ($export as $t) {
                // 字典设置：system_config 中 dict_% 键独立成表（dict_disabled_*/dict_meta 随行）
                if ($t === 'dict') {
                    $tables[$t] = Db::name('system_config')->whereLike('config_key', 'dict%')->select()->toArray();
                    continue;
                }
                // 钉钉配置：.env 中 DINGTALK_*（独立于 DB，恢复写回 .env）
                if ($t === 'dingtalk') {
                    $tables[$t] = self::readDingTalkEnv();
                    continue;
                }
                // 排序键：有 id 用 id，联合主键表（user_role/role_permission）用首列，保证稳定顺序且不报错
                $cols     = self::tableColumns($t);
                $orderCol = in_array('id', $cols, true) ? 'id' : ($cols[0] ?? 'id');
                // 全量行（保留所有列与原值），按主键升序
                $tables[$t] = Db::name($t)->order($orderCol, 'asc')->select()->toArray();
                // system_config 排除 dict_% 键（字典设置已独立成 dict 表，避免重复导出）
                if ($t === 'system_config') {
                    $tables[$t] = array_values(array_filter(
                        $tables[$t],
                        static fn($r) => strpos((string)($r['config_key'] ?? ''), 'dict_') !== 0
                    ));
                }
            }
        });
        return [
            'meta' => [
                'format'      => 1,
                'app_version' => app_version(),
                'db_type'     => config('database.default'),
                'exported_at' => date('Y-m-d H:i:s'),
                'tables'      => $export,
                'note'        => '不含 user 表；恢复时将覆盖上述表的全部行并保留原 id。',
            ],
            'tables' => $tables,
        ];
    }

    /**
     * 校验恢复后的权限矩阵是否仍赋予指定用户系统管理权限（防自锁，评估优化①）
     * user 表不参与恢复，故 is_admin=1 的用户恒安全（调用方据此放行）；
     * 此处判定恢复数据中的 admin 角色 / system:user 授权是否仍包含该用户。
     */
    public static function restorePreservesAdmin(int $userId, array $payload): bool
    {
        $t = $payload['tables'] ?? [];
        // v2.45.1：部分恢复（自选表）时不涉及权限矩阵（role/role_permission/user_role/permission），
        // 不会覆盖当前账号的管理授权，无自锁风险，直接放行
        if (empty($t['role']) && empty($t['role_permission']) && empty($t['user_role']) && empty($t['permission'])) {
            return true;
        }
        $permCodes = [];
        foreach (($t['permission'] ?? []) as $p) { $permCodes[(int)($p['id'] ?? 0)] = $p['code'] ?? ''; }
        $adminRoles = [];
        foreach (($t['role'] ?? []) as $r) {
            if (($r['code'] ?? '') === 'admin') { $adminRoles[(int)($r['id'] ?? 0)] = true; }
        }
        foreach (($t['role_permission'] ?? []) as $rp) {
            if (($permCodes[(int)($rp['perm_id'] ?? 0)] ?? '') === 'system:user') {
                $adminRoles[(int)($rp['role_id'] ?? 0)] = true;
            }
        }
        foreach (($t['user_role'] ?? []) as $ur) {
            if ((int)($ur['user_id'] ?? 0) === $userId && isset($adminRoles[(int)($ur['role_id'] ?? 0)])) {
                return true;
            }
        }
        return false;
    }

    /**
     * 预览导入：解析配置数组，返回各表行数与风险告警（不改库）。
     * @return array ['valid'=>bool,'meta'=>array,'tables'=>array,'warnings'=>array]
     */
    public static function previewConfigImport(array $payload): array
    {
        if (empty($payload['tables']) || !is_array($payload['tables'])) {
            return ['valid' => false, 'warnings' => ['文件格式不正确：缺少 tables 数据']];
        }
        $warnings = [];
        $metaInfo = $payload['meta'] ?? [];

        // 应用版本差异告警（不阻断，导入前已在 UI 二次确认）
        if (($metaInfo['app_version'] ?? '') !== app_version()) {
            $warnings[] = '应用版本不一致（文件=' . ($metaInfo['app_version'] ?? '未知')
                . '，当前=' . app_version() . '），表结构可能不同，恢复后请验证功能。';
        }

        // 数据库类型差异告警（P3，评估修复）：SQLite 备份恢复到 MySQL 时字段类型/默认值可能不兼容
        $curDb = config('database.default');
        if (($metaInfo['db_type'] ?? '') !== '' && ($metaInfo['db_type'] ?? '') !== $curDb) {
            $warnings[] = '数据库类型不一致（文件=' . $metaInfo['db_type'] . '，当前=' . $curDb
                . '），跨库恢复时字段类型/默认值可能不兼容，导入失败将整体回滚，请谨慎操作。';
        }

        // 当前库存在的 user id 集合（用于孤立引用检测）
        $userIds = array_flip(Db::name('user')->column('id'));

        $tablePreview = [];
        foreach (self::CONFIG_BACKUP_TABLES as $t) {
            $rows = $payload['tables'][$t] ?? null;
            if ($rows === null) {
                $warnings[] = "配置缺少表 {$t} 的数据，将跳过该表（不覆盖）。";
                continue;
            }
            if (!is_array($rows)) {
                $warnings[] = "表 {$t} 数据格式异常，已跳过。";
                continue;
            }
            $tablePreview[$t] = ['rows' => count($rows), 'label' => self::tableLabel($t)];
            $orphan = self::countOrphanUserRefs($t, $rows, $userIds);
            if ($orphan > 0) {
                $warnings[] = "表 {$t} 存在 {$orphan} 条指向「当前库不存在的用户」的引用"
                    . "（恢复后这些引用将悬空，因导出不含 user 表）。";
            }
        }
        return ['valid' => true, 'meta' => $metaInfo, 'tables' => $tablePreview, 'warnings' => $warnings];
    }

    /** 统计某表行中指向不存在 user 的引用数（仅对引用 user 的表） */
    private static function countOrphanUserRefs(string $table, array $rows, array $userIds): int
    {
        $map = [
            'user_role'         => 'user_id',
            'department'        => 'leader_user_id',
            'resource_library'  => 'owner_id',
        ];
        if (!isset($map[$table])) { return 0; }
        $col = $map[$table];
        $n = 0;
        foreach ($rows as $r) {
            $uid = (int)($r[$col] ?? 0);
            if ($uid > 0 && !isset($userIds[$uid])) { $n++; }
        }
        return $n;
    }

    /**
     * 提交恢复：事务内清空并重新写入允许列表内的表（保留原 id），事务回滚保证原子性。
     * @return array ['ok'=>bool,'msg'=>string,'restored'=>array]
     */
    public static function commitConfigImport(array $payload): array
    {
        $preview = self::previewConfigImport($payload);
        if (!$preview['valid']) {
            return ['ok' => false, 'msg' => $preview['warnings'][0] ?? '文件校验失败'];
        }
        $restored = [];
        Db::transaction(function () use ($payload, &$restored) {
            foreach (self::CONFIG_BACKUP_TABLES as $t) {
                if ($t === 'dingtalk') { continue; } // .env 写入不可回滚，置于事务外（见下方）
                $rows = $payload['tables'][$t] ?? null;
                if (!is_array($rows)) { continue; }
                // 字典设置：仅覆盖 system_config 中 dict_% 键，保护普通配置不被误清
                if ($t === 'dict') {
                    Db::name('system_config')->whereLike('config_key', 'dict%')->delete();
                    foreach (array_chunk($rows, 200) as $chunk) {
                        Db::name('system_config')->insertAll($chunk);
                    }
                    $restored[$t] = count($rows);
                    continue;
                }
                // 仅保留当前表真实存在的列，规避版本间列差异（多出的列丢弃，缺失的列由 DB 默认值补齐）
                $cols = self::tableColumns($t);
                $clean = [];
                foreach ($rows as $row) {
                    $cleanRow = array_intersect_key($row, array_flip($cols));
                    // P1（安全）：资料库恢复时 file_url 必须为站内上传路径，否则丢弃该行——
                    // 否则可注入 ../../config/.env 等路径，配合删除接口形成 public 边界外任意文件删除
                    if ($t === 'resource_library' && isset($cleanRow['file_url'])
                        && strpos((string)$cleanRow['file_url'], '/uploads/') !== 0) {
                        continue;
                    }
                    $clean[] = $cleanRow;
                }
                // system_config：只清非字典键（字典设置独立分区，避免整表清空误伤/重复）
                if ($t === 'system_config') {
                    Db::name('system_config')->whereNotLike('config_key', 'dict%')->delete();
                } else {
                    Db::name($t)->where('1=1')->delete();
                }
                if (!empty($clean)) {
                    // ⑤ 分批写入（评估优化）：防止单条多值 INSERT 超过 MySQL max_allowed_packet
                    //（role_permission 全量 + 大字段资料库时单条 SQL 可能超限）
                    foreach (array_chunk($clean, 200) as $chunk) {
                        Db::name($t)->insertAll($chunk);
                    }
                }
                $restored[$t] = count($clean);
            }
        });
        // 钉钉配置：DB 事务提交成功后写回 .env（文件写不可回滚，须置于事务外）
        $envRows = $payload['tables']['dingtalk'] ?? null;
        if (is_array($envRows)) {
            $restored['dingtalk'] = self::writeDingTalkEnv($envRows) ? count($envRows) : 0;
        }
        // 恢复后清缓存（字典/配置短缓存等），失败不影响已提交的恢复
        try { \think\facade\Cache::clear(); } catch (\Throwable $e) { /* 忽略缓存清理失败 */ }
        return ['ok' => true, 'msg' => '系统配置已恢复', 'restored' => $restored];
    }

    /** 取得表当前真实列名（DB 无关：MySQL 走 SHOW COLUMNS，SQLite 走 PRAGMA） */
    private static function tableColumns(string $table): array
    {
        $dbType = config('database.default');
        if ($dbType === 'mysql') {
            $rows = Db::query("SHOW COLUMNS FROM `{$table}`");
            return array_column($rows, 'Field');
        }
        $rows = Db::query("PRAGMA table_info(`{$table}`)");
        return array_column($rows, 'name');
    }

    /** 钉钉配置备份读取：.env 中 DINGTALK_* 键值对（键名保留 .env 原名） */
    private static function readDingTalkEnv(): array
    {
        $keys = ['DINGTALK_APP_KEY', 'DINGTALK_APP_SECRET', 'DINGTALK_CORP_ID',
                 'DINGTALK_AGENT_ID', 'DINGTALK_APP_URL', 'DINGTALK_MOCK_MODE'];
        $out  = [];
        $file = root_path() . '.env';
        if (is_file($file)) {
            foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $ln) {
                $ln = trim($ln);
                foreach ($keys as $k) {
                    if (strpos($ln, $k . '=') === 0) {
                        $out[$k] = trim(substr($ln, strlen($k) + 1));
                        break;
                    }
                }
            }
        }
        return $out;
    }

    /** 钉钉配置恢复写入：将 DINGTALK_* 键值对写回 .env（按 KEY=VALUE 更新或追加） */
    private static function writeDingTalkEnv(array $map): bool
    {
        $map = array_filter($map, static fn($v, $k) => is_string($v) && strpos($k, 'DINGTALK_') === 0, ARRAY_FILTER_USE_BOTH);
        if (!$map) {
            return false;
        }
        $file = root_path() . '.env';
        if (!is_file($file)) {
            return false;
        }
        $content = file_get_contents($file);
        $lines   = explode("\n", $content);
        foreach ($map as $k => $v) {
            $found = false;
            foreach ($lines as &$ln) {
                if (strpos(trim($ln), $k . '=') === 0) {
                    $ln    = $k . '=' . self::envQuote($v);
                    $found = true;
                    break;
                }
            }
            unset($ln);
            if (!$found) {
                $lines[] = $k . '=' . self::envQuote($v);
            }
        }
        $data = implode("\n", $lines);
        return @file_put_contents($file, $data) !== false;
    }

    /** .env 值引用：含空格/特殊字符时加双引号，防止解析错位 */
    private static function envQuote(string $v): string
    {
        return (preg_match('/[\s#"\']/', $v)) ? '"' . addcslashes($v, '"\\') . '"' : $v;
    }
}
