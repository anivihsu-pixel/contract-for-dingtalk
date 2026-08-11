<?php
// +----------------------------------------------------------------------
// | RBAC 角色权限服务
// +----------------------------------------------------------------------

namespace app\common\service;

use think\facade\Db;

class RbacService
{
    /** 所有角色 */
    public static function getRoles(): array
    {
        return Db::name('role')->order('id')->select()->toArray();
    }

    /** 所有权限 */
    public static function getPermissions(): array
    {
        return Db::name('permission')->order('group_name, id')->select()->toArray();
    }

    /** 保存角色权限（v2.40.2：全量替换前强制合并「全员默认基础权限」——角色配置界面已隐藏这些选项，
     *  若直接按勾选项替换会误删基础权限绑定；判定层 getUserPermissions 已默认并集，此处同步保证数据一致） */
    public static function saveRolePerms(int $roleId, array $permIds): void
    {
        $baseIds = Db::name('permission')
            ->whereIn('code', \app\common\logic\AuthLogic::DEFAULT_PERMISSION_CODES)
            ->column('id');
        $permIds = array_values(array_unique(array_merge(array_map('intval', $permIds), array_map('intval', $baseIds))));

        Db::name('role_permission')->where('role_id', $roleId)->delete();
        foreach ($permIds as $pid) {
            Db::name('role_permission')->insert(['role_id' => $roleId, 'perm_id' => $pid]);
        }
        // RV-01：角色权限变更，自增所有拥有该角色的用户的权限版本
        $userIds = Db::name('user_role')->where('role_id', $roleId)->column('user_id');
        if (!empty($userIds)) {
            Db::name('user')->where('id', 'in', $userIds)->inc('perm_version')->update();
        }
    }

    /** 保存角色自定义部门（CUSTOM 数据范围用）：全量替换该角色的可访问部门集合 */
    public static function saveRoleDepts(int $roleId, array $deptIds): void
    {
        Db::name('role_dept')->where('role_id', $roleId)->delete();
        foreach (array_unique(array_map('intval', $deptIds)) as $did) {
            if ($did > 0) {
                Db::name('role_dept')->insert(['role_id' => $roleId, 'dept_id' => $did]);
            }
        }
        // RV-01：部门范围变更同样需要失效受影响用户的会话缓存
        $userIds = Db::name('user_role')->where('role_id', $roleId)->column('user_id');
        if (!empty($userIds)) {
            Db::name('user')->where('id', 'in', $userIds)->inc('perm_version')->update();
        }
    }

    /** 创建角色 */
    public static function createRole(array $data): int
    {
        $data['is_system'] = 0;
        return Db::name('role')->insertGetId($data);
    }

    /** 更新角色 */
    public static function updateRole(int $id, array $data): bool
    {
        // RV-01：角色本身（如 data_scope）变更，自增拥有该角色的用户的权限版本
        $userIds = Db::name('user_role')->where('role_id', $id)->column('user_id');
        $ok = Db::name('role')->where('id', $id)->update($data) !== false;
        if (!empty($userIds)) {
            Db::name('user')->where('id', 'in', $userIds)->inc('perm_version')->update();
        }
        return $ok;
    }

    /** 删除角色 (系统角色不可删) */
    public static function deleteRole(int $id): bool
    {
        $role = Db::name('role')->find($id);
        if ($role && !empty($role['is_system'])) return false;

        // RV-01：删除角色前收集受影响用户，删除后自增其权限版本
        $userIds = Db::name('user_role')->where('role_id', $id)->column('user_id');
        Db::name('role')->where('id', $id)->delete();
        Db::name('role_permission')->where('role_id', $id)->delete();
        Db::name('role_dept')->where('role_id', $id)->delete();
        Db::name('user_role')->where('role_id', $id)->delete();
        if (!empty($userIds)) {
            Db::name('user')->where('id', 'in', $userIds)->inc('perm_version')->update();
        }
        return true;
    }

    /** 分配用户角色 */
    public static function assignRoles(int $userId, array $roleIds): void
    {
        Db::name('user_role')->where('user_id', $userId)->delete();
        foreach ($roleIds as $rid) {
            Db::name('user_role')->insert(['user_id' => $userId, 'role_id' => $rid]);
        }
        // RV-01：用户角色变更后自增权限版本，使已登录会话下次请求自动刷新权限
        Db::name('user')->where('id', $userId)->inc('perm_version')->update();
    }

    /** 用户列表 */
    public static function getUserList(int $page, int $pageSize, string $keyword = '', $status = null): array
    {
        $query = Db::name('user')->alias('u')
            ->leftJoin('department d', 'u.dept_id = d.id')
            ->field('u.*, d.name as dept_name');

        // 状态过滤：'active' = 仅在职(status=1)；'recycle' = 回收站(status 0 锁定/2 禁用)；
        // 指定值 = 按值过滤；null = 不过滤（兼容其他调用方）
        if ($status === 'active') {
            $query->where('u.status', 1);
        } elseif ($status === 'recycle') {
            $query->where('u.status', 'in', [0, 2]);
        } elseif ($status !== null) {
            $query->where('u.status', $status);
        }

        if ($keyword) {
            // P2-12【S-A6】LIKE 通配符转义：%/_ 作为用户输入须按字面匹配（用 ESCAPE '\' 声明转义符），
            // 否则搜索 "%" 会命中全部用户、"_" 会误配任意单字符
            $kw = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $keyword);
            $query->whereRaw(
                "(u.name LIKE ? ESCAPE '\\' OR u.username LIKE ? ESCAPE '\\' OR u.mobile LIKE ? ESCAPE '\\')",
                ["%{$kw}%", "%{$kw}%", "%{$kw}%"]
            );
        }

        $total = $query->count();
        $list  = $query->order('u.id')->page($page, $pageSize)->select()->toArray();

        // 附加角色信息——预加载 user_id=>[角色名] 映射，避免循环内 N+1（CR-05）
        $userIds = array_column($list, 'id');
        $roleMap = [];
        if (!empty($userIds)) {
            $roleRows = Db::name('user_role')->alias('ur')
                ->join('role r', 'ur.role_id = r.id')
                ->where('ur.user_id', 'in', $userIds)
                ->field('ur.user_id, r.name')
                ->select()->toArray();
            foreach ($roleRows as $rr) {
                $roleMap[$rr['user_id']][] = $rr['name'];
            }
        }
        foreach ($list as &$u) {
            $u['roles'] = $roleMap[$u['id']] ?? [];
        }

        return ['list' => $list, 'total' => $total];
    }
}
