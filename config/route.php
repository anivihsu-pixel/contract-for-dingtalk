<?php
// +----------------------------------------------------------------------
// | 路由全局配置
// +----------------------------------------------------------------------

return [
    // v2.44.1 P1：关闭自动路由回退——所有入口均有显式路由（route/app.php），
    // 防止控制器方法被 GET 等非预期方法直接命中（如 GET /admin/saveUser 绕过 CSRF 写库）。
    // 此前 url_route_must=false + 控制器无 isPost 守卫 → GET 请求可命中写操作且全局 Csrf 仅校验 POST/PUT/DELETE。
    'url_route_must'         => true,
    'route_complete_match'   => true,
    'controller_suffix'      => true,
    'var_pathinfo'           => 's',
    'controller_auto_search' => false,
];
