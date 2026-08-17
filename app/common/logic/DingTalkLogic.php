<?php
// +----------------------------------------------------------------------
// | 钉钉业务逻辑 — SSO / JSAPI / 通知 / 组织同步
// +----------------------------------------------------------------------

namespace app\common\logic;

use think\facade\Db;
use think\facade\Session;
use app\common\service\DingTalkService;

class DingTalkLogic
{
    /**
     * 免登处理
     * @param string $authCode  前端 dd.requestAuthCode 获得的 code
     * @return array  ['token' => xxx, 'user' => [...]]
     */
    public static function ssoLogin(string $authCode): array
    {
        // P0-2【严重·认证绕过】修复：生产环境严禁使用 Mock 认证（可被任意伪造）
        // 钉钉 Mock 模式下免登身份由前端可控的 code 直接派生（mock_user_ + 前 8 位），本质可被伪造。
        // 生产（APP_DEBUG=false）下若仍处于 Mock 模式，直接硬阻断登录，杜绝冒充登录/任意造号。
        if (DingTalkService::isMock()) {
            if (empty(config('app.debug'))) {
                \think\facade\Log::critical('[安全·P0-2] 生产环境启用了钉钉 MOCK 模式，免登身份可被伪造，已拦截登录', ['code' => $authCode]);
                throw new \RuntimeException('系统当前处于演示(Mock)认证模式，生产环境禁止使用，请联系管理员关闭 DINGTALK_MOCK_MODE');
            }
            // 开发环境：Mock 仅用于本地演示。即便 auto_create_user 开启，也仅允许命中白名单的账号自动开户：
            // 未命中白名单且系统不存在该 userid 的用户一律拒绝，避免任意 code 造号。
            $whitelist = (array) config('dingtalk.allowed_userids', []);
            $resolvedUserId = 'mock_user_' . substr($authCode, 0, 8);
            $exists = Db::name('user')->where('dingtalk_userid', $resolvedUserId)->find();
            $allowAuto = config('dingtalk.auto_create_user', false);
            if (!$exists && (!$allowAuto || !in_array($resolvedUserId, $whitelist, true))) {
                throw new \RuntimeException('该钉钉账号未在本系统预置，请联系管理员绑定后重试');
            }
        }

        // 1. 交换用户信息（REV-03：TLS 已开启，响应来源可信；否则中间人可伪造）
        $userInfo = DingTalkService::getUserByAuthCode($authCode);

        if (isset($userInfo['errcode']) && $userInfo['errcode'] != 0) {
            throw new \RuntimeException('钉钉认证失败: ' . ($userInfo['errmsg'] ?? 'unknown'));
        }

        $dingtalkUserId = $userInfo['userid'] ?? '';
        $unionId        = $userInfo['unionid'] ?? '';
        $name           = $userInfo['name'] ?? '钉钉用户';

        if (!$dingtalkUserId) {
            throw new \RuntimeException('无法获取钉钉用户 ID');
        }

        // 2. 查找或创建本地用户
        $user = Db::name('user')->where('dingtalk_userid', $dingtalkUserId)->find();
        if (!$user && $unionId) {
            $user = Db::name('user')->where('dingtalk_unionid', $unionId)->find();
        }
        if (!$user) {
            // REV-03：禁止匿名自动建号。仅在 auto_create_user 显式开启且 userid 命中白名单时才允许开户，
            // 否则拒绝免登，要求管理员先在系统中预置并绑定钉钉账号，杜绝中间人伪造钉钉响应即可开户。
            $allowAuto = config('dingtalk.auto_create_user', false);
            $whitelist = (array) config('dingtalk.allowed_userids', []);
            if (!$allowAuto || !in_array($dingtalkUserId, $whitelist, true)) {
                throw new \RuntimeException('该钉钉账号未在本系统预置，请联系管理员绑定后重试');
            }
            $userId = Db::name('user')->insertGetId([
                'username'        => 'dd_' . $dingtalkUserId,
                'password'        => '',
                'name'            => $name,
                'dingtalk_userid' => $dingtalkUserId,
                'dingtalk_unionid'=> $unionId,
                'status'          => 1,
                'is_admin'        => 0,
                'created_at'      => date('Y-m-d H:i:s'),
            ]);
            // 钉钉同步的新用户默认授予「普通用户」角色（role.code='user'）
            DingTalkService::grantDefaultRole((int)$userId);
            $user = Db::name('user')->find($userId);
        }

        if (!$user || $user['status'] != 1) {
            throw new \RuntimeException('用户已被禁用');
        }

        // 3. 加载 Session
        AuthLogic::loadSession($user);

        // 4. 生成 JWT
        $token = AuthLogic::issueJwt($user['id']);

        // 5. 记录会话
        Db::name('dingtalk_session')->insert([
            'user_id'    => $user['id'],
            'token'      => $token,
            // P3-3：会话有效期与 JWT_TTL 保持一致（默认 86400），不再硬编码
            'expires_at' => date('Y-m-d H:i:s', time() + (int)env('JWT_TTL', 86400)),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return [
            'token' => $token,
            'user'  => [
                'id'       => $user['id'],
                'name'     => $user['name'],
                'is_admin' => $user['is_admin'],
            ],
        ];
    }

    /**
     * JSAPI 配置签名
     */
    public static function getJsApiConfig(string $url): array
    {
        // P3-3：nonce 改用密码学安全随机源（替代可被预测的 md5(uniqid)）
        $nonceStr  = bin2hex(random_bytes(16));
        $timestamp = (string)time();
        $ticket    = DingTalkService::getJsApiTicket();

        $signStr = sprintf('jsapi_ticket=%s&noncestr=%s&timestamp=%s&url=%s',
            $ticket, $nonceStr, $timestamp, $url);
        $signature = sha1($signStr);

        return [
            'corpId'    => config('dingtalk.corp_id'),
            'agentId'   => config('dingtalk.agent_id'),
            'nonceStr'  => $nonceStr,
            'timestamp' => $timestamp,
            'signature' => $signature,
        ];
    }

    /**
     * 同步钉钉组织架构
     * v2.38.16（P2）：返回疑似离职员工列表（本地在职但钉钉侧已消失），供前端提示交接
     */
    public static function syncOrganization(): array
    {
        $result = DingTalkService::syncOrganization();
        return [
            'synced_depts' => $result['depts'] ?? 0,
            'synced_users' => $result['users'] ?? 0,
            'departed'     => $result['departed'] ?? [],
        ];
    }
}
