<?php
// +----------------------------------------------------------------------
// | CSRF 防护中间件 — Double-Submit Cookie + Session 校验
// | GET/HEAD/OPTIONS 请求下发 csrf_token（写入 session 与同名 cookie）
// | 写操作（POST/PUT/DELETE/PATCH）校验请求头 X-CSRF-TOKEN 与 session 一致
// | 外部系统回调（钉钉）在白名单内放行
// +----------------------------------------------------------------------

namespace app\middleware;

use think\facade\Session;
use think\facade\Cookie;
use app\common\logic\AuthLogic;
use app\common\service\JwtHelper;

class Csrf
{
    /** 免校验路径（外部回调 / 非浏览器来源） */
    protected array $whitelist = [
        'dingtalk/sso-login',
        'dingtalk/jsapi-config',
    ];

    public function handle($request, \Closure $next)
    {
        // REV-37：Bearer（JWT/API）通道本身抗 CSRF，且 Auth 中间件已完成鉴权，
        // 纯 API 客户端写操作无需 CSRF 令牌。
        // P3a-1：必须验证 JWT 真实性后再跳过，避免伪造 Bearer 头绕过 CSRF 校验。
        $authHeader = $request->header('Authorization', '');
        if ($authHeader && strpos($authHeader, 'Bearer ') === 0) {
            $token   = substr($authHeader, 7);
            $secret  = AuthLogic::jwtSecret();
            $payload = JwtHelper::decode($token, $secret);
            if ($payload && !empty($payload['user_id'])) {
                return $next($request);
            }
            // JWT 无效则继续走正常 CSRF 校验流程
        }

        $method = strtoupper($request->method());

        // 只读请求：确保下发 token，不校验
        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            $this->ensureToken();
            return $next($request);
        }

        // 白名单放行（与 Auth 中间件 except 同规则：完全相等或 prefix + '/' 的下级路径，
        // 取代 strpos===0 前缀匹配——后者会把 'dingtalk/sso-login-evil' 等相似路径误豁免 CSRF）
        $path = trim($request->pathinfo(), '/');
        foreach ($this->whitelist as $prefix) {
            if ($path === $prefix || strpos($path, $prefix . '/') === 0) {
                return $next($request);
            }
        }

        $token = Session::get('csrf_token');
        $submitted = $request->header('X-CSRF-TOKEN')
            ?: $request->header('X-XSRF-TOKEN')
            ?: $request->post('__csrf__');

        if (!$token || !$submitted || !hash_equals((string)$token, (string)$submitted)) {
            return json([
                'code' => 403,
                'msg'  => 'CSRF 校验失败，请刷新页面后重试',
                'data' => null,
            ], 403);
        }

        // 会话内固定 token（CR-45 评估结论：不轮转）：
        // double-submit cookie 模式天然容忍同一会话的多次写请求，避免「一次性 token」
        // 在「同页面多表单 / 并行 fetch」场景下「用过即失效」导致的 403 竞态。
        // 若改为「每次请求轮转」，需在每次写请求后回写新 token 并由前端全局刷新，
        // 否则并发/并行写请求会相互失效。鉴于本系统前端大量并行 fetch，
        // 「会话级固定 token」是安全与兼容的最佳折中；吊销场景依赖会话过期/登出。
        return $next($request);
    }

    /** 确保 session 中有 csrf_token，并下发给浏览器（httpOnly=false 供 JS 读取） */
    protected function ensureToken(): void
    {
        $token = Session::get('csrf_token');
        if (!$token) {
            $token = bin2hex(random_bytes(32));
            Session::set('csrf_token', $token);
        }
        // CR-34：HTTPS 部署时 cookie 标记 secure，避免明文链路泄露令牌
        Cookie::set('csrf_token', $token, [
            'expire'   => 0,
            'httponly' => false,
            'secure'   => (bool) request()->isSsl(),
            'samesite' => 'Lax',
        ]);
    }
}
