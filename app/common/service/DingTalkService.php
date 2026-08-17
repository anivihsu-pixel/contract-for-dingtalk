<?php
// +----------------------------------------------------------------------
// | 钉钉服务门面 — 统一对外接口
// +----------------------------------------------------------------------

namespace app\common\service;

use think\facade\Db;
use think\facade\Log;
use app\common\service\dd\DingTalkClient;
use app\common\service\dd\DingTalkMock;
use app\common\service\InternalNotify;

class DingTalkService
{
    /** 当前是否为 Mock 模式 */
    public static function isMock(): bool
    {
        // P0-2【严重·C3】必须用 filter_var 解析布尔：env 注入值为字符串 "false"，
        // 若直接 (bool)config(...) 则非空字符串恒为真，导致 .env=DINGTALK_MOCK_MODE=false 形同虚设、Mock 仍开启。
        return filter_var(config('dingtalk.mock_mode', false), FILTER_VALIDATE_BOOLEAN);
    }

    /** 根据 authCode 获取用户身份 */
    public static function getUserByAuthCode(string $code): array
    {
        if (self::isMock()) {
            return (new DingTalkMock())->getUserIdByAuthCode($code);
        }
        $client = new DingTalkClient();
        $token  = $client->getAccessToken();
        return $client->get('/user/getuserinfo', ['access_token' => $token, 'code' => $code]);
    }

    /** 获取用户详情 */
    public static function getUserDetail(string $userId): array
    {
        if (self::isMock()) {
            return (new DingTalkMock())->getUserDetail($userId);
        }
        $client = new DingTalkClient();
        $token  = $client->getAccessToken();
        return $client->get('/user/get', ['access_token' => $token, 'userid' => $userId]);
    }

    /** 获取部门列表 */
    public static function getDepartmentList(?int $parentId = null): array
    {
        if (self::isMock()) {
            return (new DingTalkMock())->getDepartmentList($parentId);
        }
        $client = new DingTalkClient();
        $token  = $client->getAccessToken();
        $params = ['access_token' => $token];
        if ($parentId !== null) $params['id'] = $parentId;
        return $client->get('/department/list', $params);
    }

    /** 获取部门用户 */
    public static function getDepartmentUsers(int $deptId): array
    {
        if (self::isMock()) {
            return (new DingTalkMock())->getDepartmentUsers($deptId);
        }
        $client = new DingTalkClient();
        $token  = $client->getAccessToken();
        return $client->get('/user/listbypage', [
            'access_token' => $token,
            'department_id' => $deptId,
            'offset' => 0,
            'size' => 100,
        ]);
    }

    /** 发送工作通知
     * @param string|null $url 跳转链接（可选）。提供时若 dingtalk.msg_type=action_card，则改用 action_card 单按钮消息，
     *                          点击按钮在钉钉内（微应用 WebView）打开链接，避免 markdown 内联链接被钉钉当外部浏览器打开。
     */
    public static function sendWorkNotice(array $userIds, string $title, string $markdown, ?string $url = null, string $type = ''): array
    {
        if (empty($userIds)) return ['errcode' => 0];

        if (self::isMock()) {
            return (new DingTalkMock())->sendWorkNotice($userIds, $title, $markdown, $url, $type);
        }

        $client = new DingTalkClient();
        $token  = $client->getAccessToken();
        $agentId = config('dingtalk.agent_id');

        // 按钮语义按消息类型区分（v2.37.5）：
        //  - 抄送知会(APPROVAL_CC)：接收人无需处理，按钮应为“点击查看”，避免误导其以为要审批；
        //  - 其余（提交/通过/驳回/转交/催办等）：接收人确有动作（审批/查看结果），按钮“点击处理”。
        $btnTitle = ($type === InternalNotify::TYPE_APPROVAL_CC) ? '点击查看' : '点击处理';

        // 提供跳转链接且配置为 action_card：点击按钮在钉钉内打开（需求：审批通知应在钉钉内打开合同管理应用，而非外部浏览器）
        if ($url !== null && config('dingtalk.msg_type') === 'action_card') {
            $data = [
                'agent_id'    => $agentId,
                'userid_list' => implode(',', $userIds),
                'msg' => [
                    'msgtype'    => 'action_card',
                    'action_card' => [
                        'title'        => $title,
                        'markdown'     => $markdown,
                        'single_title' => $btnTitle,
                        'single_url'   => $url,
                    ],
                ],
            ];
        } else {
            $data = [
                'agent_id'    => $agentId,
                'userid_list' => implode(',', $userIds),
                'msg' => [
                    'msgtype'  => 'markdown',
                    'markdown' => [
                        'title' => $title,
                        'text'  => $markdown,
                    ],
                ],
            ];
        }

        return $client->post('/topapi/message/corpconversation/asyncsend_v2?access_token=' . $token, $data);
    }

    /**
     * 给本地系统用户（user.id）发送钉钉工作通知
     * - Mock 模式：直接用本地 ID 调用 mock（便于本地演示/测试）
     * - 真实模式：将本地 user.id 映射为钉钉 userid 后发送，自动跳过未绑定钉钉的用户
     *
     * @param array $localUserIds 本地用户主键数组
     */
    public static function sendToLocalUsers(array $localUserIds, string $title, string $markdown, ?string $url = null, string $type = ''): array
    {
        $localUserIds = array_values(array_unique(array_filter(array_map('intval', $localUserIds))));
        if (empty($localUserIds)) {
            return ['errcode' => 0];
        }

        if (self::isMock()) {
            // Mock 模式下钉钉 userid 与本地 ID 无强绑定关系，直接以本地 ID 演示发送
            return (new DingTalkMock())->sendWorkNotice($localUserIds, $title, $markdown, $url, $type);
        }

        $rows = \think\facade\Db::name('user')
            ->whereIn('id', $localUserIds)
            ->column('dingtalk_userid', 'id');

        $ddIds   = [];
        $unbound = [];
        foreach ($localUserIds as $id) {
            $dd = $rows[$id] ?? '';
            if (!empty($dd)) {
                $ddIds[] = $dd;
            } else {
                $unbound[] = $id;
            }
        }

        if (!empty($unbound)) {
            // 未绑定钉钉的用户无法接收通知，记录日志便于排查（不影响其余通知发送）
            error_log('[DingTalk] 以下本地用户未绑定钉钉 userid，已跳过工作通知: ' . implode(',', $unbound));
        }

        if (empty($ddIds)) {
            return ['errcode' => 0, 'unbound' => $unbound];
        }

        return self::sendWorkNotice($ddIds, $title, $markdown, $url, $type);
    }

    /**
     * 生成审批消息点击后进入系统的深链（经钉钉免登入口，自动鉴权后直达审批详情）
     */
    public static function approvalEntryUrl(int $instanceId): string
    {
        $base = config('dingtalk.app_url');
        if (empty($base)) {
            $base = (string)(request()->domain() ?? '');
        }
        $to = '/approval/' . $instanceId;
        return rtrim($base, '/') . '/dingtalk/entry?to=' . urlencode($to);
    }

    /** 获取 JSAPI ticket */
    public static function getJsApiTicket(): string
    {
        if (self::isMock()) return 'mock_jsapi_ticket_' . time();

        $cache = cache('dingtalk_jsapi_ticket');
        if ($cache) return $cache;

        $client = new DingTalkClient();
        $token  = $client->getAccessToken();
        $resp   = $client->get('/get_jsapi_ticket', ['access_token' => $token]);

        $ticket = $resp['ticket'] ?? '';
        if ($ticket) {
            cache('dingtalk_jsapi_ticket', $ticket, 7000);
        }
        return $ticket;
    }

    /**
     * 钉钉创建的新用户默认授予「普通用户」角色（role.code='user'）。
     * 角色不存在时仅记日志不中断（管理员可后补授权）。
     */
    public static function grantDefaultRole(int $uid): void
    {
        if ($uid <= 0) return;
        $roleId = Db::name('role')->where('code', 'user')->value('id');
        if (!$roleId) {
            Log::warning('钉钉默认角色缺失：未找到 role.code=user', ['user_id' => $uid]);
            return;
        }
        $exists = Db::name('user_role')->where('user_id', $uid)->where('role_id', $roleId)->find();
        if (!$exists) {
            Db::name('user_role')->insert(['user_id' => $uid, 'role_id' => $roleId]);
        }
    }

    /** 同步组织架构 */
    /**
     * CR-24：钉钉组织同步（逐条容错）
     * 部门/用户任一失败仅记录日志并继续，不中断整体同步；返回 failures 汇总失败数
     */
    public static function syncOrganization(): array
    {
        $deptCount = 0;
        $userCount = 0;
        $failures  = 0;
        // v2.38.16（P2）：本轮钉钉侧现存员工集合（dingtalk_userid），用于离职检测
        $ddUserIds = [];

        $deptData = self::getDepartmentList();
        $departments = $deptData['department'] ?? [];

        // 第一遍：upsert 部门与用户，建立「钉钉部门ID => 本地部门ID」映射。
        // 注意：钉钉返回的 parentid 是钉钉部门ID，本地 department.parent_id 必须是本地ID，
        // 因此此处先暂置 parent_id=0，待全部部门拿到本地ID后第二遍统一修正。
        $localByDd = [];
        $ddParent   = [];
        foreach ($departments as $dept) {
            try {
                $exists = Db::name('department')->where('dingtalk_dept_id', $dept['id'])->find();
                $data = [
                    'name'             => $dept['name'],
                    'parent_id'        => 0,
                    'dingtalk_dept_id' => $dept['id'],
                ];
                if ($exists) {
                    Db::name('department')->where('id', $exists['id'])->update($data);
                    $deptId = $exists['id'];
                } else {
                    $deptId = Db::name('department')->insertGetId($data);
                    $deptCount++;
                }
                $localByDd[$dept['id']] = $deptId;
                $ddParent[$dept['id']]   = $dept['parentid'] ?? 0;

                // 拉取部门用户，归属到本地部门 ID（不依赖父级修正，先落位）
                $userData = self::getDepartmentUsers($dept['id']);
                $users = $userData['userlist'] ?? [];
                foreach ($users as $u) {
                    try {
                        $ddUserIds[] = (string)$u['userid'];
                        $lu = Db::name('user')->where('dingtalk_userid', $u['userid'])->find();
                        $udata = [
                            'name'             => $u['name'],
                            'dingtalk_userid'  => $u['userid'],
                            'dingtalk_unionid' => $u['unionid'] ?? '',
                            'dept_id'          => $deptId,
                        ];
                        if ($lu) {
                            Db::name('user')->where('id', $lu['id'])->update($udata);
                        } else {
                            $udata['username']   = 'dd_' . $u['userid'];
                            $udata['password']   = '';
                            $udata['status']     = 1;
                            $udata['created_at'] = date('Y-m-d H:i:s');
                            $uid = Db::name('user')->insertGetId($udata);
                            $userCount++;
                            // 钉钉同步的新用户默认授予「普通用户」角色（role.code='user'）
                            self::grantDefaultRole((int)$uid);
                        }
                    } catch (\Throwable $e) {
                        $failures++;
                        Log::error('钉钉同步用户失败', ['userid' => $u['userid'] ?? null, 'err' => $e->getMessage()]);
                    }
                }
            } catch (\Throwable $e) {
                $failures++;
                Log::error('钉钉同步部门失败', ['dept_id' => $dept['id'] ?? null, 'err' => $e->getMessage()]);
            }
        }

        // 第二遍：将 parent_id 由「钉钉部门ID」修正为「本地部门ID」，确保本地部门树（含选人弹窗）正确
        foreach ($localByDd as $ddId => $localId) {
            $ddP = $ddParent[$ddId] ?? 0;
            $localParent = $ddP ? ($localByDd[$ddP] ?? 0) : 0;
            Db::name('department')->where('id', $localId)->update(['parent_id' => $localParent]);
        }

        // v2.38.16（P2）+ v2.38.25（自动化）：离职检测——本地在职且绑定钉钉 userid 的用户，不在本轮钉钉员工集合中 → 疑似已离职。
        // 自动标记 need_handover=1（进入「待交接」队列），不自动禁用（避免误伤）；
        // 由管理员/有权账号（system:user）在用户管理页查看待交接列表并办理离职交接；交接/恢复后清零。
        $departed = [];
        $ddSet = array_flip(array_unique(array_filter($ddUserIds)));
        if (!empty($ddSet)) {
            $localBound = Db::name('user')
                ->where('status', 1)
                ->where('dingtalk_userid', '<>', '')
                ->field('id, name, username, dingtalk_userid')
                ->select()->toArray();
            foreach ($localBound as $lu) {
                if (!isset($ddSet[(string)$lu['dingtalk_userid']])) {
                    Db::name('user')->where('id', $lu['id'])->update([
                        'need_handover' => 1,
                        'updated_at'    => date('Y-m-d H:i:s'),
                    ]);
                    $departed[] = [
                        'id'               => (int)$lu['id'],
                        'name'             => $lu['name'] ?: $lu['username'],
                        'dingtalk_userid'  => $lu['dingtalk_userid'],
                        'customers'        => (int)Db::name('customer')->where('owner_id', $lu['id'])->where('is_deleted', 0)->count(),
                        'contracts'        => (int)Db::name('contract')->where('owner_id', $lu['id'])->where('is_deleted', 0)->count(),
                        'pending_approval' => (int)Db::name('approval_record')->where('approver_id', $lu['id'])->where('action', 'PENDING')->count(),
                    ];
                }
            }
        }

        return [
            'depts'    => $deptCount,
            'users'    => $userCount,
            'failures' => $failures,
            'departed' => $departed,
        ];
    }
}
