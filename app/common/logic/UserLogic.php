<?php
// +----------------------------------------------------------------------
// | 用户业务逻辑（Phase 1.9：从 MobileController 提取 user 表直查，消除控制器内 Db 直查）
// +----------------------------------------------------------------------

namespace app\common\logic;

use think\facade\Db;

class UserLogic
{
    /** 单个用户姓名（id → name；id 非法或不存在返回空串） */
    public static function getName(int $id): string
    {
        if ($id <= 0) return '';
        return Db::name('user')->where('id', $id)->value('name') ?: '';
    }

    /** 批量用户姓名（[id,...] → [id => name]，自动去重并剔除非法 id） */
    public static function getNamesByIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids, function ($x) {
            return is_numeric($x) && $x > 0;
        })));
        if (empty($ids)) return [];
        return Db::name('user')->where('id', 'in', $ids)->column('name', 'id') ?: [];
    }

    /** 用户下拉选项（id,name；按 id 升序；供移动端筛选器等复用，避免控制器直查 user 表） */
    public static function getOptions(): array
    {
        return Db::name('user')->field('id,name')->order('id')->select()->toArray();
    }

    /**
     * 按部门 id 列表查询用户 id（P1-1：替代控制器直查 user 表做数据范围收敛）
     */
    public static function getIdsByDeptIds(array $deptIds): array
    {
        if (!$deptIds) return [];
        return array_map('intval', Db::name('user')->whereIn('dept_id', array_map('intval', $deptIds))->column('id'));
    }

    /** 部门名称（P1-1：替代控制器直查 department 表） */
    public static function getDeptName(int $deptId): string
    {
        if ($deptId <= 0) return '';
        return (string)Db::name('department')->where('id', $deptId)->value('name');
    }

    /** 待交接人数（P1-1：替代控制器直查 user 表） */
    public static function countPendingHandover(): int
    {
        return (int)Db::name('user')->where('need_handover', 1)->where('status', 1)->count();
    }

    /** 用户 + 部门名单行（P1-1：替代控制器直查 user 表 join department 表） */
    public static function getWithDept(int $userId): ?array
    {
        if ($userId <= 0) return null;
        return Db::name('user')->alias('u')
            ->leftJoin('department d', 'u.dept_id = d.id')
            ->where('u.id', $userId)
            ->field('u.name, d.name as dept_name')
            ->find() ?: null;
    }

    /** 在职用户 + 部门名列表（P1-1：替代控制器直查，供离职交接接收人下拉） */
    public static function getActiveWithDept(): array
    {
        return Db::name('user')->alias('u')
            ->leftJoin('department d', 'u.dept_id = d.id')
            ->where('u.status', 1)
            ->field('u.id, u.name, u.dept_id, d.name as dept_name')
            ->order('u.id')
            ->select()->toArray();
    }

    /**
     * 审批转交可选目标用户（启用且非本人；非管理员仅同部门；支持关键词搜索）
     * @param int $userId 当前用户 id（作为排除项）
     * @param bool $isAdmin 是否管理员（决定是否跨部门的限制）
     * @param int $deptId 当前用户部门（非管理员时约束同部门）
     * @param string $keyword 姓名关键词（可选）
     * @return array [['id'=>int, 'name'=>string], ...] 最多 200 条
     */
    public static function getTransferTargets(int $userId, bool $isAdmin, int $deptId, string $keyword = ''): array
    {
        $q = Db::name('user')
            ->where('status', 1)
            ->where('id', '<>', $userId);
        if (!$isAdmin) {
            $q->where('dept_id', $deptId);
        }
        $kw = trim($keyword);
        if ($kw !== '') {
            $q->where('name', 'like', '%' . $kw . '%');
        }
        return $q->field('id, name')
            ->order('id', 'asc')
            ->limit(200)
            ->select()
            ->toArray();
    }

    /**
     * 审批转交目标用户（分页版，Phase 2.8：支持大组织 AJAX 搜索 + 分页）
     * 权限范围与 getTransferTargets 一致：非管理员仅同部门。
     * @param int $userId 当前用户 id（排除项）
     * @param bool $isAdmin 是否管理员
     * @param int $deptId 当前用户部门
     * @param string $keyword 姓名关键词
     * @param int $page 页码（从 1 开始）
     * @param int $pageSize 每页条数
     * @return array ['list'=>array, 'total'=>int]
     */
    public static function getTransferTargetsPaged(int $userId, bool $isAdmin, int $deptId, string $keyword, int $page, int $pageSize): array
    {
        $q = Db::name('user')
            ->where('status', 1)
            ->where('id', '<>', $userId);
        if (!$isAdmin) {
            $q->where('dept_id', $deptId);
        }
        $kw = trim($keyword);
        if ($kw !== '') {
            $q->where('name', 'like', '%' . $kw . '%');
        }
        $total = $q->count();
        $list  = $q->field('id, name')
            ->order('id', 'asc')
            ->page($page, $pageSize)
            ->select()
            ->toArray();
        return ['list' => $list, 'total' => $total];
    }

    /**
     * 选人弹窗用户搜索（管理员配置审批人/抄送人用）
     * 与转交目标不同：不排除本人、支持按部门过滤（dept_id=0 表示全部部门），
     * 返回 id/name/dept_name 供弹窗树+列表渲染。
     * @param int $deptId 部门过滤（0=全部）
     * @param string $keyword 姓名/用户名关键词
     * @param int $page 页码
     * @param int $pageSize 每页条数
     * @return array ['list'=>array, 'total'=>int]
     */
    public static function searchPicker(int $deptId, string $keyword, int $page, int $pageSize): array
    {
        $q = Db::name('user')->alias('u')
            ->leftJoin('department d', 'u.dept_id = d.id')
            ->field('u.id, u.name, d.name as dept_name');
        if ($deptId > 0) {
            $q->where('u.dept_id', $deptId);
        }
        $kw = trim($keyword);
        if ($kw !== '') {
            $q->where('u.name|u.username', 'like', '%' . $kw . '%');
        }
        $total = $q->count();
        $list  = $q->order('u.id')->page($page, $pageSize)->select()->toArray();
        return ['list' => $list, 'total' => $total];
    }

    /** 部门树（平铺返回 id/name/parent_id，前端构建层级；供选人弹窗左侧部门导航） */
    public static function getDeptTree(): array
    {
        return Db::name('department')
            ->field('id, name, parent_id')
            ->order('sort_order, id')
            ->select()
            ->toArray();
    }
}
