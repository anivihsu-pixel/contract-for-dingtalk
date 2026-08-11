<?php
// +----------------------------------------------------------------------
// | Cookie 配置
// +----------------------------------------------------------------------

return [
    'prefix'   => '',
    'expire'   => 0,
    'path'     => '/',
    'domain'   => '',
    // REV-16 / P1-4：会话 Cookie 默认仅 HTTPS 传输（secure=true），避免明文链路被中间人截获。
    // 但「写死 true」会使 HTTP 部署（本地开发 / 内网 HTTP 反代）下会话 Cookie 被浏览器拒绝存储、
    // 会话无法保持、登录报「CSRF 校验失败」。故改由 app\middleware\CookiePolicy 在请求早期按协议动态修正：
    // 未显式设置 COOKIE_SECURE 时跟随请求协议（HTTPS=>true / HTTP=>false），显式设置时以其为准。
    // 通过环境变量 COOKIE_SECURE 强制覆盖（.env 设 COOKIE_SECURE=true/false）。
    'secure'   => (bool) env('COOKIE_SECURE', true),
    'httponly' => true,
    'samesite' => 'Lax',
];
