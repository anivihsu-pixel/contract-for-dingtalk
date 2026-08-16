<?php
// +----------------------------------------------------------------------
// | 操作审计服务
// +----------------------------------------------------------------------

namespace app\common\service;

use think\facade\Db;
use think\facade\Log;

class AuditService
{
    /**
     * 记录操作日志
     * @param int    $userId     操作人 ID
     * @param string $action     操作类型 (create/update/delete/approve 等)
     * @param string $targetType 目标类型 (contract/customer/user 等)
     * @param int    $targetId   目标 ID
     * @param mixed  $content    操作详情 (数组自动转 JSON)
     */
    public static function log(int $userId, string $action, string $targetType, int $targetId, $content = ''): void
    {
        try {
            Db::name('audit_log')->insert([
                'user_id'      => $userId,
                'action'       => $action,
                'target_type'  => $targetType,
                'target_id'    => $targetId,
                // 目标标题快照：对象之后被删除/清理也能在审计中心定位当时的对象
                'target_title' => self::resolveTitle($targetType, $targetId),
                'content'      => is_array($content) ? json_encode($content, JSON_UNESCAPED_UNICODE) : (string)$content,
                'ip_address'   => request()->ip() ?: '',
                'user_agent'   => request()->header('User-Agent', ''),
                'created_at'   => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            // 审计日志写入失败不阻塞业务，但必须记录以便日志中心察觉（CR-32）
            Log::error('审计日志写入失败', [
                'user_id'     => $userId,
                'action'      => $action,
                'target_type' => $targetType,
                'target_id'   => $targetId,
                'error'       => $e->getMessage(),
            ]);
        }
    }

    /** 查询审计日志 */
    public static function getList(int $page, int $pageSize, array $filter = [], array $sort = ['a.id', 'desc']): array
    {
        $query = Db::name('audit_log')->alias('a')
            ->leftJoin('user u', 'a.user_id = u.id')
            ->field('a.*, u.name as user_name');

        if (!empty($filter['user_id'])) {
            $query->where('a.user_id', $filter['user_id']);
        }
        if (!empty($filter['action'])) {
            $query->where('a.action', $filter['action']);
        }
        if (!empty($filter['target_type'])) {
            $query->where('a.target_type', $filter['target_type']);
        }
        if (!empty($filter['date_start'])) {
            $query->where('a.created_at', '>=', $filter['date_start']);
        }

        $total = $query->count();
        $list  = $query->order($sort[0], $sort[1])->page($page, $pageSize)->select()->toArray();

        // 补充展示字段：对象标题（合同标题/客户名称等）+ 详情可读文本（原始 JSON 中文化）
        $titles = self::resolveTitles($list);
        foreach ($list as &$r) {
            $t = $r['target_type'];
            $id = (int)$r['target_id'];
            // 快照优先（反映操作当时的对象名）；快照为空的历史记录回退现查
            $r['target_title'] = trim((string)($r['target_title'] ?? '')) ?: ($titles[$t][$id] ?? '');
            $r['detail_text']  = self::humanizeContent((string)($r['content'] ?? ''), (string)($r['action'] ?? ''));
        }
        unset($r);

        return ['list' => $list, 'total' => $total];
    }

    /**
     * 单条解析目标对象标题（写入审计快照用）。
     * 与 resolveTitles 的展示口径一致；无标题表/查不到返回空串。
     */
    private static function resolveTitle(string $type, int $id): string
    {
        if ($id <= 0) return '';
        $map = [
            'contract' => ['contract', 'title'],
            'customer' => ['customer', 'name'],
            'supplier' => ['supplier', 'name'],
            'user'     => ['user', 'name'],
            'project'  => ['project', 'name'],
        ];
        if (isset($map[$type])) {
            [$table, $nameField] = $map[$type];
            return (string)Db::name($table)->where('id', $id)->value($nameField);
        }
        if ($type === 'invoice') {
            $no = Db::name('contract_invoice')->where('id', $id)->value('invoice_no');
            return $no ? ('发票 ' . $no) : '';
        }
        if ($type === 'approval' || $type === 'payment') {
            $table = $type === 'approval' ? 'approval_instance' : 'payment_record';
            return (string)Db::name($table)->alias('x')->join('contract c', 'x.contract_id = c.id', 'left')
                ->where('x.id', $id)->value('c.title');
        }
        return '';
    }

    /**
     * 批量解析审计记录的目标对象标题（合同标题/客户名称等），
     * 供审计中心「对象」列展示，避免只显示「合同 #12」这类无信息量的编号。
     * 目标已被删除时查不到则回退为空，由前端降级为「类型 #ID」。
     */
    private static function resolveTitles(array $list): array
    {
        $groups = [];
        foreach ($list as $r) {
            $t  = $r['target_type'];
            $id = (int)$r['target_id'];
            if ($t === '' || $id <= 0) continue;
            $groups[$t][$id] = true;
        }
        if (!$groups) return [];

        $titles = [];
        $map = [
            'contract' => ['contract', 'title'],
            'customer' => ['customer', 'name'],
            'supplier' => ['supplier', 'name'],
            'user'     => ['user', 'name'],
            'project'  => ['project', 'name'],
        ];
        foreach ($map as $type => [$table, $nameField]) {
            if (empty($groups[$type])) continue;
            foreach (Db::name($table)->where('id', 'in', array_keys($groups[$type]))
                ->field("id,$nameField")->select()->toArray() as $x) {
                $titles[$type][(int)$x['id']] = $x[$nameField];
            }
        }
        if (!empty($groups['invoice'])) {
            foreach (Db::name('contract_invoice')->where('id', 'in', array_keys($groups['invoice']))
                ->field('id,invoice_no')->select()->toArray() as $x) {
                $titles['invoice'][(int)$x['id']] = '发票 ' . $x['invoice_no'];
            }
        }
        // 审批 / 回款：对象标题取关联合同标题
        foreach (['approval' => 'approval_instance', 'payment' => 'payment_record'] as $type => $table) {
            if (empty($groups[$type])) continue;
            foreach (Db::name($table)->alias('x')->join('contract c', 'x.contract_id = c.id', 'left')
                ->where('x.id', 'in', array_keys($groups[$type]))
                ->field('x.id,c.title')->select()->toArray() as $x) {
                $titles[$type][(int)$x['id']] = $x['title'];
            }
        }
        return $titles;
    }

    /** 各业务状态码 -> 中文（覆盖合同/发票/审批，用于状态流转文案） */
    private static function statusLabels(): array
    {
        return [
            'DRAFT'            => '草稿',
            'PENDING_APPROVAL' => '待审批',
            'REJECTED'         => '已驳回',
            'EXECUTING'        => '执行中',
            'COMPLETED'        => '已完成',
            'TERMINATED'       => '已终止',
            'EXPIRED'          => '已到期',
            'ARCHIVED'         => '已归档',
            'PENDING'          => '审批中',
            'APPROVED'         => '已通过',
            'ISSUED'           => '已开票',
            'VOID'             => '已作废',
            'RED'              => '已红冲',
            'PAID'             => '已收',
        ];
    }

    /** 字段名 -> 中文（未知 JSON 结构兜底展示用） */
    private static function fieldLabels(): array
    {
        return [
            'instance_id'       => '审批单',
            'comment'           => '意见',
            'transfer_to'       => '转交',
            'role_ids'          => '角色',
            'action'            => '操作',
            'to_user_id'        => '接收人',
            'scope'             => '交接范围',
            'disable_from'      => '禁用原用户',
            'counts'            => '交接数量',
            'affected_contracts'=> '受影响合同',
            'skipped'           => '跳过',
            'from'              => '原状态',
            'to'                => '新状态',
            'transition'        => '流转',
            'parent_id'         => '上级分组',
            'target_type'       => '对象类型',
            'target_id'         => '对象',
        ];
    }

    private static function statusToCn(string $s): string
    {
        $s = trim($s);
        return self::statusLabels()[$s] ?? $s;
    }

    /** 状态流转文本：REJECTED -> PENDING_APPROVAL → 已驳回 → 待审批 */
    private static function humanizeTransition(string $text): string
    {
        $parts = array_map('trim', explode('->', $text));
        return implode(' → ', array_map([self::class, 'statusToCn'], $parts));
    }

    private static function roleNames($ids): array
    {
        $ids = array_values(array_filter(array_map('intval', (array)$ids)));
        if (!$ids) return [];
        return Db::name('role')->where('id', 'in', $ids)->column('name');
    }

    private static function userName(int $id): string
    {
        if ($id <= 0) return '';
        return (string)Db::name('user')->where('id', $id)->value('name');
    }

    /** 未知 JSON 结构兜底：键值对形式输出，比原始 JSON 更易读 */
    private static function kvPairs(array $data): string
    {
        $labels = self::fieldLabels();
        $parts  = [];
        foreach ($data as $k => $v) {
            $label = $labels[$k] ?? $k;
            if (is_array($v)) {
                $v = json_encode($v, JSON_UNESCAPED_UNICODE);
            }
            $parts[] = $label . '：' . $v;
        }
        return implode('；', $parts);
    }

    /**
     * 将审计日志 content 转成中文可读文本。
     * 覆盖当前系统实际写入的结构（审批/用户/交接/状态流转/客户共享等）；
     * 未知结构回退到键值对形式，非 JSON 原样返回。
     */
    public static function humanizeContent(string $content, string $action): string
    {
        $content = trim($content);
        if ($content === '') return '';

        // 纯文本状态流转（status_change 直接存 "A -> B"）
        if ($content[0] !== '{' && strpos($content, '->') !== false) {
            return self::humanizeTransition($content);
        }

        $data = json_decode($content, true);
        if (!is_array($data)) return $content;

        switch ($action) {
            case 'save_user':
                $op = ($data['action'] ?? '') === 'create' ? '创建' : '更新';
                $roles = self::roleNames($data['role_ids'] ?? []);
                return $op . '用户' . ($roles ? '；角色：' . implode('、', $roles) : '');
            case 'handover':
                $to = self::userName((int)($data['to_user_id'] ?? 0));
                $parts = ['交接给 ' . ($to ?: '用户#' . ($data['to_user_id'] ?? ''))];
                $scope = [];
                foreach ((array)($data['scope'] ?? []) as $k => $on) {
                    if ($on) $scope[] = ['customer' => '客户', 'contract' => '合同', 'approval' => '审批'][$k] ?? $k;
                }
                if ($scope) $parts[] = '范围：' . implode('、', $scope);
                $counts = $data['counts'] ?? [];
                if ($counts) {
                    $cs = [];
                    foreach ($counts as $k => $c) {
                        $cs[] = (['customer' => '客户', 'contract' => '合同', 'approval' => '审批'][$k] ?? $k) . $c . '个';
                    }
                    $parts[] = '交接：' . implode('、', $cs);
                }
                if (!empty($data['disable_from'])) $parts[] = '原用户已禁用';
                return implode('；', $parts);
            case 'approve_submit':
            case 'approve_recall':
                return '审批单 #' . ($data['instance_id'] ?? '');
            case 'approve_approved':
            case 'approve_rejected':
            case 'approve_transferred':
                $parts = [];
                if (!empty($data['comment'])) $parts[] = '意见：' . $data['comment'];
                if (!empty($data['transfer_to'])) {
                    $name = self::userName((int)$data['transfer_to']);
                    $parts[] = '转交：' . ($name ?: '用户#' . $data['transfer_to']);
                }
                return $parts ? implode('；', $parts) : ('审批单 #' . ($data['instance_id'] ?? ''));
            case 'archive':
            case 'unarchive':
                return self::humanizeTransition(($data['from'] ?? '') . ' -> ' . ($data['to'] ?? ''));
            case 'terminate':
                return '关联合同 ' . (int)($data['affected_contracts'] ?? 0) . ' 份被终止';
            case 'customer_share':
                $who = self::userName((int)($data['target_id'] ?? 0)) ?: ('用户#' . ($data['target_id'] ?? ''));
                return ($data['action'] ?? '') === 'remove' ? '取消共享给 ' . $who : '共享给 ' . $who;
            case 'customer_join_group':
                $g = Db::name('customer')->where('id', (int)($data['parent_id'] ?? 0))->value('name');
                return '加入客户分组：' . ($g ?: ('分组#' . ($data['parent_id'] ?? '')));
        }
        return self::kvPairs($data);
    }
}
