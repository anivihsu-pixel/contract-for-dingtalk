<?php
// +----------------------------------------------------------------------
// | 全局中间件
// +----------------------------------------------------------------------

return [
    // 必须最前：在 SessionInit 设置会话 Cookie 前，依据请求协议动态修正 Cookie 的 secure 标志，
    // 避免 HTTP 部署下会话 Cookie 被强制 Secure 导致会话无法保持、登录报「CSRF 校验失败」。
    \app\middleware\CookiePolicy::class,
    \app\middleware\SqliteGuard::class,
    \think\middleware\SessionInit::class,
    \app\middleware\Auth::class,
    \app\middleware\Csrf::class,
];
