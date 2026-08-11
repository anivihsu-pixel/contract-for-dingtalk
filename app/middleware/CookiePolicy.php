<?php
// +----------------------------------------------------------------------
// | Cookie 安全策略中间件
// | 背景：config/cookie.php 默认 secure=(bool) env('COOKIE_SECURE', true)，
// | 当 .env 未显式设置 COOKIE_SECURE 时，会话 Cookie（CONTRACT_SID）会被打上
// | Secure 标志。在 HTTP（非 HTTPS）部署（本地开发 / 内网 HTTP 反代）下，浏览器
// | 会拒绝存储带 Secure 的 Cookie，导致会话无法保持——每次请求都是新会话，
// | Session::get('csrf_token') 永远为空，登录等写操作全部返回「CSRF 校验失败」。
// | 修复：在任意 Cookie::set 之前，将全局 Cookie 单例的 secure 配置动态修正为
// | 「跟随当前请求协议」(HTTPS=>true / HTTP=>false)，与 Csrf 中间件的 csrf_token
// | 行为保持一致；.env 显式设置 COOKIE_SECURE 时以其为准（保留手动覆盖能力）。
// | 必须注册为最外层全局中间件（位于 SessionInit 之前）。
// +----------------------------------------------------------------------

namespace app\middleware;

class CookiePolicy
{
    public function handle($request, \Closure $next)
    {
        // 最终 secure：.env 显式设置 COOKIE_SECURE 时优先；否则自动跟随请求协议
        $forced = env('COOKIE_SECURE');
        $secure = ($forced === null || $forced === '') ? (bool) $request->isSsl() : (bool) $forced;

        // 在 SessionInit / Csrf 等执行 Cookie::set 之前，修正全局 Cookie 单例的 secure 配置，
        // 使其后续 set 自动采用正确值（反射修改 protected $config，框架 Cookie 类无公开 setter）。
        // 全局 Cookie 单例与 Csrf 的 Cookie 门面、SessionInit 的 $app->cookie 为同一实例。
        $cookie = app('cookie');
        $ref    = new \ReflectionClass($cookie);
        $prop   = $ref->getProperty('config');
        $prop->setAccessible(true);
        $cfg          = $prop->getValue($cookie);
        $cfg['secure'] = $secure;
        $prop->setValue($cookie, $cfg);

        return $next($request);
    }
}
