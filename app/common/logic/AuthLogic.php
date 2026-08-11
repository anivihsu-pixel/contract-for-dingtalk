<?php
// +----------------------------------------------------------------------
// | 认证逻辑
// +----------------------------------------------------------------------

namespace app\common\logic;

use think\facade\Db;
use think\facade\Session;
use think\facade\Cache;
use app\common\service\JwtHelper;

class AuthLogic
{
    /** v2.40.2：全员默认的基础权限（普通员工能力集）——合同/客户/审批/资料库等查看+新建+编辑+提交，
     *  所有登录用户默认拥有，不再依赖角色绑定；角色配置界面不显示这些选项（见 AdminController::index）。 */
    public const DEFAULT_PERMISSION_CODES = [
        'contract:view', 'contract:create', 'contract:edit',
        'approval:view', 'approval:submit',
        'customer:view', 'customer:create', 'customer:edit',
        'supplier:view',
        'payment:view',
        'invoice:view', 'invoice:apply',
        'remind:view',
        'library:view',
        'project:view', 'project:create', 'project:edit',
        'party:view',
    ];

    /** 是否为超级管理员（v2.40.5）
     *  ① user.is_admin=1（预置/本地超管账号）；
     *  ② 或拥有「超级管理员」角色（role.code='admin'）——钉钉真实部署 is_admin=0 + admin 角色同效，
     *     修复 AdminController::saveUser 等「仅超管可操作」守卫在钉钉部署下 is_admin 判定失效的问题。
     */
    public static function isSuperAdmin(int $userId, array $user = []): bool
    {
        if (!empty($user['is_admin'])) return true;
        if ($userId <= 0) return false;
        $roleCodes = Db::name('user_role')->alias('ur')
            ->join('role r', 'r.id = ur.role_id')
            ->where('ur.user_id', $userId)
            ->column('r.code');
        return in_array('admin', $roleCodes, true);
    }

    /**
     * 获取管理员用户 ID 列表：is_admin=1（预置/本地超管）∪ 拥有「超级管理员」角色（role.code='admin'）的用户。
     * 统一替代散落各处的 `where('is_admin', 1)` 查询——钉钉真实部署 is_admin=0 + admin 角色同效，
     * 否则审批回退/SLA 升级/提醒推送等「找管理员」路径在钉钉场景恒空（v2.40.5 修复）。
     * @param bool $activeOnly 是否仅在职（status=1）；审批回退等需避开禁用用户时传 true
     */
    public static function getAdminUserIds(bool $activeOnly = true): array
    {
        $roleUserIds = Db::name('user_role')->alias('ur')
            ->join('role r', 'ur.role_id = r.id')
            ->where('r.code', 'admin')
            ->column('ur.user_id');
        $query = Db::name('user')->where(function ($q) use ($roleUserIds) {
            $q->where('is_admin', 1);
            if (!empty($roleUserIds)) {
                $q->whereOr('id', 'in', $roleUserIds);
            }
        });
        if ($activeOnly) {
            $query->where('status', 1);
        }
        return array_values(array_unique(array_map('intval', $query->column('id'))));
    }

    /** 密码登录 */
    public static function login(string $username, string $password): ?array
    {
        $user = Db::name('user')->where('username', $username)->find();
        if (!$user || $user['status'] != 1) return null;

        if (!password_verify($password, $user['password'])) return null;

        // 更新登录时间
        Db::name('user')->where('id', $user['id'])->update(['last_login_at' => date('Y-m-d H:i:s')]);

        self::loadSession($user);
        return $user;
    }

    /** 加载用户信息到 Session
     * REV-12：登录/免登成功后在写入会话数据前轮换会话 ID（Session::regenerate），
     * 防范会话固定攻击（攻击者预埋会话 ID 诱导受害者登录后接管账号）。CLI 环境无会话，跳过轮换。
     */
    public static function loadSession(array $user): void
    {
        if (PHP_SAPI !== 'cli') {
            Session::regenerate(true);
        }

        Session::set('user_id', $user['id']);

        // REV-23：登录时归一计算并缓存数据范围(ALL/DEPT/SELF)到会话，避免每次请求重复查库
        $user['data_scope'] = self::computeScope((int)$user['id']);
        // v2.37.0：同时归一计算「可见性谓词」（含部门层级/CUSTOM），供 appendDataScope/canAccessRecord 使用
        $user['data_visibility'] = self::computeVisibility((int)$user['id']);
        Session::set('user', $user);

        $perms = self::getUserPermissions($user['id']);
        Session::set('user_permissions', $perms);
    }

    /** 获取用户权限码 */
    public static function getUserPermissions(int $userId): array
    {
        $user = Db::name('user')->find($userId);
        if (!$user) return [];

        if (!empty($user['is_admin'])) {
            return Db::name('permission')->column('code');
        }

        $permIds = Db::name('role_permission')->alias('rp')
            ->join('user_role ur', 'rp.role_id = ur.role_id')
            ->where('ur.user_id', $userId)
            ->column('rp.perm_id');

        $rolePerms = empty($permIds) ? [] : Db::name('permission')->whereIn('id', $permIds)->column('code');
        // v2.40.2：全员默认基础权限（普通员工能力集）与角色权限并集——合同/客户/审批/资料库等基础能力不再依赖角色绑定
        return array_values(array_unique(array_merge($rolePerms, self::DEFAULT_PERMISSION_CODES)));
    }

    /**
     * 刷新已登录会话中的权限缓存（若权限版本已变更） — RV-01
     *
     * 角色/权限变更后，已登录用户的会话权限须自动失效，而不是等其重新登录才生效。
     * 机制：user 表 perm_version 在 assignRoles / saveRolePerms / updateRole / deleteRole / is_admin 变更时自增；
     * 本方法对比会话内缓存的 perm_version 与 DB 最新值，不一致则重算 user_permissions 与 data_scope 写回会话。
     * 不调用 Session::regenerate，避免每次刷新都轮换会话 ID。
     * 由 Auth 中间件在每次请求鉴权成功后调用，使 Cookie 与 JWT 双通道权限均实时对齐。
     */
    public static function refreshSessionPermissionsIfStale(): void
    {
        $userId = Session::get('user_id');
        if (empty($userId)) return;

        $cached = Session::get('user', []);
        $cachedVersion = isset($cached['perm_version']) ? (int)$cached['perm_version'] : 0;

        $latestVersion = Db::name('user')->where('id', $userId)->value('perm_version');
        if ($latestVersion === null) return;

        if ((int)$latestVersion !== $cachedVersion) {
            self::refreshPermissionsInSession((int)$userId);
        }
    }

    /**
     * 仅重算并写回会话中的权限与数据范围（不轮换会话 ID） — RV-01
     */
    private static function refreshPermissionsInSession(int $userId): void
    {
        $user = Db::name('user')->find($userId);
        if (!$user) return;

        $perms = self::getUserPermissions($userId);
        Session::set('user_permissions', $perms);

        // 重新归一计算数据范围并随 user 写回，保持与 loadSession 行为一致
        $user['data_scope'] = self::computeScope($userId);
        $user['data_visibility'] = self::computeVisibility($userId);
        Session::set('user', $user);
    }

    /**
     * 签发 JWT
     * 安全说明：签名密钥统一走 jwtSecret()（优先 .env 显式配置，未配置则回退运行时随机持久化密钥），
     * 不再使用可预测的硬编码字符串，且签发/验证两侧共用同一入口保证一致。
     */
    public static function issueJwt(int $userId): string
    {
        // P3-3：JWT 有效期接入 JWT_TTL 配置（默认 86400），不再硬编码
        return JwtHelper::encode(['user_id' => $userId], self::jwtSecret(), (int)env('JWT_TTL', 86400));
    }

    /**
     * 获取 JWT 签名密钥（签发与验证的唯一入口）
     * ① 优先使用 .env 显式配置的 JWT_SECRET；
     * ② 未配置时回退为运行时随机生成并持久化的密钥（不可预测，且重启后保持稳定），
     *    同时输出告警日志，提示运维在 .env 显式设置 JWT_SECRET。
     * 注：旧版兜底值 hash_hmac('sha256','jwt_signing_key',APP_KEY) 可被推导，已废弃。
     */
    public static function jwtSecret(): string
    {
        $secret = trim((string) env('JWT_SECRET', ''));
        // REV-02：拒绝使用弱密钥或缺失密钥——已知的弱默认值（含仓库历史明文弱值）、空值、
        // 以及长度不足 32 字符的密钥一律不可用于签发/验证，防止攻击者用可预测密钥伪造任意用户令牌。
        // S-05：演示环境 .env 随仓库分发的固定 JWT_SECRET 已公开，一并列入黑名单强制回退运行时随机密钥。
        $weakSecrets = ['please_change_me', 'contract_dingtalk_jwt_secret_2026', '715d43a7f7a9a25d2d4457745987af9f6c2d42bfe5d93d92f8b034c2d900e1e6'];
        if ($secret !== '' && !in_array($secret, $weakSecrets, true) && strlen($secret) >= 32) {
            return $secret;
        }
        // 弱密钥/缺失：回退至运行时随机持久化密钥（不可被预测），并以 CRITICAL 告警提示运维立即修正。
        \think\facade\Log::critical('[安全·REV-02] JWT_SECRET 缺失或为弱密钥（长度<32 或已知弱值），已拒绝使用并回退至运行时随机持久化密钥；请运行 php database/generate_secrets.php 生成强密钥写入 .env，避免 JWT 令牌可被伪造');
        return self::resolveFallbackJwtSecret();
    }

    /**
     * 解析回退密钥：优先读取运行时持久化文件，不存在则生成 32 字节随机并持久化
     */
    private static function resolveFallbackJwtSecret(): string
    {
        // 注意：runtime_path('jwt_secret.txt') 会把参数当「目录名」处理（恒追加尾随分隔符，
        // 返回 runtime\jwt_secret.txt\），is_file/写入必然失败 → 每次请求随机密钥、预览令牌/会话全部失效；
        // 须用 rtrim(runtime_path(), '/\\') 拼文件路径（v2.43.5 修复 Windows/部分环境持久化失效）。
        $file = function_exists('runtime_path')
            ? rtrim(runtime_path(), '/\\') . DIRECTORY_SEPARATOR . 'jwt_secret.txt'
            : (sys_get_temp_dir() . '/contract_review_jwt_secret.txt');
        if (is_file($file)) {
            $cached = trim((string) @file_get_contents($file));
            if ($cached !== '') {
                return $cached;
            }
        }
        $secret = bin2hex(random_bytes(32));
        try {
            @file_put_contents($file, $secret);
        } catch (\Throwable $e) {
            // 持久化失败：本次随机即可，重启后旧 token 失效（可接受）
        }
        return $secret;
    }

    /**
     * 退出
     * REV-13：注销时同步失效该用户经 JWT(Bearer) 通道赖以通过鉴权的 dingtalk_session 行，
     * 并清除对应缓存键，确保退出后令牌立即失效（而非等待 24h 自然过期），满足会话吊销/离职即失效。
     */
    public static function logout(): void
    {
        $userId = Session::get('user_id');
        Session::clear();
        if ($userId) {
            $tokens = Db::name('dingtalk_session')->where('user_id', $userId)->column('token');
            if ($tokens) {
                foreach ($tokens as $t) {
                    Cache::delete('jwt_session_' . $t);
                }
                Db::name('dingtalk_session')->where('user_id', $userId)->delete();
            }
        }
    }

    /**
     * 数据范围过滤：给查询增加 owner/dept 限制（系统唯一的数据权限裁决点）
     *
     * ⚠️ 多角色「取最高范围」语义（CR-40 明确，v2.37.0 升级为「可见性谓词」）：
     * - 任一角色为 ALL → 可见全部（短路）；
     * - 否则按各角色贡献的「部门集合 + 本人」做 OR 合并（DEPT=本部门、DEPT_AND_CHILD=本部门及子部门、
     *   CUSTOM=自定义部门集合、SELF=仅本人）。
     * 这意味着「给一个高范围角色」会无意放大该用户的数据可见范围；分配角色时需审慎，避免越权扩权。
     * 该方法与 {@see canAccessRecord()} / {@see dataScope()} / {@see visibility()} 保持语义一致。
     *
     * @param string $ownerField 归属人字段名（如带表别名需传 'c.owner_id'）
     * @param string $deptField  归属部门字段名
     */
    public static function appendDataScope(&$query, string $ownerField = 'owner_id', string $deptField = 'dept_id'): void
    {
        $user = Session::get('user');
        // 管理员看全部（is_admin 显式放行）
        if (!empty($user['is_admin'])) return;
        if (!$user) {
            // REV-42：非 Web/无会话上下文拒绝默认全量——避免命令误用或会话缺失时泄露全量数据。
            // 系统级任务（提醒/到期）不应走此作用域方法，而应显式使用无作用域查询。
            // 用 1=0 而非 where('id', 0)：联表查询（如税务汇总 join contract）下裸 id 会产生 ambiguous column 报错
            $query->where('1=0'); // 永假条件：返回空集
            return;
        }

        $vis = self::getVisibility($user);
        if ($vis['has_all']) return; // ALL：不过滤

        $conds = [];
        if ($vis['owner_self']) {
            $conds[] = [$ownerField, '=', $user['id']];
        }
        if (!empty($vis['dept_ids'])) {
            $conds[] = [$deptField, 'in', $vis['dept_ids']];
        }
        if (empty($conds)) {
            // 安全兜底：无任何可见范围 → 仅本人（避免泄露全量）
            $query->where($ownerField, $user['id']);
            return;
        }
        if (count($conds) === 1) {
            [$f, $op, $v] = $conds[0];
            $query->where($f, $op, $v);
        } else {
            $query->where(function ($q) use ($conds) {
                foreach ($conds as [$f, $op, $v]) {
                    $q->whereOr($f, $op, $v);
                }
            });
        }
    }

    /**
     * 当前用户的数据范围代表值：ALL / DEPT / SELF（向后兼容旧调用方；
     * 新代码请使用 {@see visibility()} 或 {@see scopeOrConditions()} 获取完整谓词）。
     * REV-23：优先读会话缓存。
     */
    public static function dataScope(): string
    {
        $user = Session::get('user');
        if (!$user || !empty($user['is_admin'])) return 'ALL';
        $vis = self::getVisibility($user);
        if ($vis['has_all']) return 'ALL';
        if (!empty($vis['dept_ids']) && !$vis['owner_self']) return 'DEPT'; // 纯部门级（含层级/自定义）
        return 'SELF';
    }

    /**
     * 当前用户的「可见性谓词」：{has_all, owner_self, dept_ids[]}
     * 优先读会话缓存（登录/刷新时已算好），缺失时计算并回写，避免重复查库。
     */
    public static function visibility(): array
    {
        $user = Session::get('user');
        if (!$user) return ['has_all' => false, 'owner_self' => true, 'dept_ids' => []];
        if (!empty($user['is_admin'])) return ['has_all' => true, 'owner_self' => true, 'dept_ids' => []];
        return self::getVisibility($user);
    }

    /**
     * 返回适用 OR 组合的可见性条件数组（供 search 类方法直接套用）。
     * 返回空数组表示 has_all（调用方不应再附加范围约束）。
     * @return array<int, array{0:string,1:string,2:mixed}>
     */
    public static function scopeOrConditions(string $ownerField = 'owner_id', string $deptField = 'dept_id'): array
    {
        $vis = self::visibility();
        if ($vis['has_all']) return [];
        $conds = [];
        if ($vis['owner_self']) {
            $conds[] = [$ownerField, '=', Session::get('user.id')];
        }
        if (!empty($vis['dept_ids'])) {
            $conds[] = [$deptField, 'in', $vis['dept_ids']];
        }
        if (empty($conds)) {
            $conds[] = [$ownerField, '=', Session::get('user.id')];
        }
        return $conds;
    }

    /**
     * 判断当前用户能否访问某条带 owner_id/dept_id 的记录
     * @param int $ownerId 记录归属人
     * @param int|null $deptId 记录归属部门
     */
    public static function canAccessRecord(int $ownerId, ?int $deptId = null): bool
    {
        $user = Session::get('user');
        if (!$user || !empty($user['is_admin'])) return true;
        $vis = self::getVisibility($user);
        if ($vis['has_all']) return true;
        if ($vis['owner_self'] && $ownerId == $user['id']) return true;
        if (!empty($vis['dept_ids']) && in_array(($deptId ?? 0), $vis['dept_ids'], true)) return true;
        return false;
    }

    /**
     * 从会话用户取得可见性谓词（内部），缺失时计算并回写会话。
     */
    private static function getVisibility(array $user): array
    {
        if (!empty($user['is_admin'])) return ['has_all' => true, 'owner_self' => true, 'dept_ids' => []];
        if (isset($user['data_visibility']) && is_array($user['data_visibility'])) {
            return $user['data_visibility'];
        }
        $vis = self::computeVisibility((int)($user['id'] ?? 0));
        $user['data_visibility'] = $vis;
        Session::set('user', $user);
        return $vis;
    }

    /**
     * 计算用户可见性谓词（查库 + 角色范围合并）。
     * 规则：ALL 短路；否则收集各角色贡献的部门集合（DEPT/DEPT_AND_CHILD/CUSTOM）与本人可见标记。
     */
    private static function computeVisibility(int $userId): array
    {
        $roleIds = Db::name('user_role')->where('user_id', $userId)->column('role_id');
        if (empty($roleIds)) {
            // 无角色：仅本人（deny-by-default，与历史 SELF 行为一致）
            return ['has_all' => false, 'owner_self' => true, 'dept_ids' => []];
        }
        $roles = Db::name('role')->whereIn('id', $roleIds)->field('id,data_scope')->select()->toArray();
        if (empty($roles)) {
            return ['has_all' => false, 'owner_self' => true, 'dept_ids' => []];
        }
        $userDeptId = (int)(Db::name('user')->where('id', $userId)->value('dept_id') ?? 0);

        $customRoleIds = [];
        foreach ($roles as $r) {
            if (($r['data_scope'] ?? '') === 'CUSTOM') {
                $customRoleIds[] = (int)$r['id'];
            }
        }
        $customDeptIdsByRole = [];
        if ($customRoleIds) {
            $rows = Db::name('role_dept')->whereIn('role_id', $customRoleIds)->field('role_id,dept_id')->select()->toArray();
            foreach ($rows as $row) {
                $customDeptIdsByRole[(int)$row['role_id']][] = (int)$row['dept_id'];
            }
        }

        return self::buildVisibility($roles, $userDeptId, $customDeptIdsByRole);
    }

    /**
     * 纯函数：按角色范围集合构建可见性谓词（不依赖会话/连接，便于单元测试）。
     * @param array $roles 角色行集合，每项含 id/data_scope
     * @param int $userDeptId 当前用户所属部门ID
     * @param array $customDeptIdsByRole 角色ID => [部门ID,...]（CUSTOM 用）
     * @param array|null $deptRows 部门行集合（覆盖 DB 读取，便于测试/复用）
     */
    public static function buildVisibility(array $roles, int $userDeptId, array $customDeptIdsByRole = [], ?array $deptRows = null): array
    {
        $hasAll = false;
        $ownerSelf = false;
        $deptIds = [];
        foreach ($roles as $r) {
            $scope = $r['data_scope'] ?? 'SELF';
            switch ($scope) {
                case 'ALL':
                    $hasAll = true;
                    break;
                case 'SELF':
                    $ownerSelf = true;
                    break;
                case 'DEPT':
                    if ($userDeptId) {
                        $deptIds[$userDeptId] = true;
                    }
                    break;
                case 'DEPT_AND_CHILD':
                    // 本部门及所有子孙部门（PHP 递归展开，DB 引擎无关）
                    foreach (self::expandDeptDescendants($userDeptId, $deptRows) as $d) {
                        $deptIds[$d] = true;
                    }
                    break;
                case 'CUSTOM':
                    $rid = (int)($r['id'] ?? 0);
                    foreach (($customDeptIdsByRole[$rid] ?? []) as $d) {
                        $deptIds[$d] = true;
                    }
                    break;
                default:
                    // 未知档位：安全降级为仅本人，避免泄露全量
                    $ownerSelf = true;
                    break;
            }
        }
        return ['has_all' => $hasAll, 'owner_self' => $ownerSelf, 'dept_ids' => array_keys($deptIds)];
    }

    /**
     * 展开某部门的「本部门 + 所有子孙部门」ID 列表（含自身）。
     * 使用 PHP 递归遍历部门树，DB 引擎无关，便于单元测试（可传入 $deptRows 覆盖）。
     * @param array|null $deptRows 部门行集合 [{id,parent_id}]；为空则从库读取（带请求内静态缓存）。
     * @return int[]
     */
    public static function expandDeptDescendants(int $deptId, ?array $deptRows = null): array
    {
        if (!$deptId) return [];
        $depts = $deptRows ?? self::getAllDepartments();
        $children = [];
        foreach ($depts as $d) {
            $pid = (int)($d['parent_id'] ?? 0);
            $children[$pid][] = (int)$d['id'];
        }
        $result = [];
        $stack = [(int)$deptId];
        while ($stack) {
            $cur = array_pop($stack);
            $result[] = $cur;
            foreach ($children[$cur] ?? [] as $c) {
                $stack[] = $c;
            }
        }
        return $result;
    }

    /** 读取全量部门（请求内静态缓存，供子孙展开复用） */
    private static function getAllDepartments(): array
    {
        static $cache = null;
        if ($cache === null) {
            $cache = Db::name('department')->field('id,parent_id')->select()->toArray();
        }
        return $cache;
    }

    /**
     * REV-23：计算用户数据范围代表值(ALL/DEPT/SELF)，取角色最高范围(ALL>DEPT>SELF)
     * 仅在登录时(loadSession)与缓存缺失时调用，日常请求直接读会话缓存。
     * 向后兼容：新逻辑改用 {@see visibility()}，本方法保留供 dataScope()/旧调用方使用。
     */
    private static function computeScope(int $userId): string
    {
        $roleIds = Db::name('user_role')->where('user_id', $userId)->column('role_id');
        if (empty($roleIds)) return 'SELF';
        $scopes = Db::name('role')->whereIn('id', $roleIds)->column('data_scope');
        if (in_array('ALL', $scopes)) return 'ALL';
        if (in_array('DEPT', $scopes)) return 'DEPT';
        return 'SELF';
    }

    /**
     * 确保当前用户已装载到会话（预览等双通道鉴权兜底）
     * 若会话仅有 user_id 而无 user 数据，则查库并装载会话（含数据范围缓存）。
     */
    public static function ensureSession(int $userId): void
    {
        $user = Db::name('user')->find($userId);
        if ($user) {
            self::loadSession($user);
        }
    }
}
