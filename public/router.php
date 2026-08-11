<?php
/**
 * 开发服务器路由脚本（php -S 内置服务器专用）
 *
 * 作用：
 * 1. 静态资源（js/css/img/font 等真实存在的文件）由内置服务器直接返回，无需经 PHP 路由；
 * 2. 其余请求（如 /login、/project 等）统一转发到 index.php（ThinkPHP 单一入口）。
 *
 * 注意：本文件仅用于本地 `php -S` 开发服务器，不能作为生产 Web 服务器入口。
 */

// 仅在内置开发服务器环境下执行静态文件判断
if (PHP_SAPI === 'cli-server') {
    // 将请求 URI 映射到 public 目录下的真实文件路径
    $requestPath = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
    $realFile    = __DIR__ . $requestPath;

    // 真实存在的静态文件：返回 false 交由内置服务器直接输出，不走 index.php
    if (is_file($realFile)) {
        return false;
    }
}

// 非静态文件：转发到前端控制器 index.php
require __DIR__ . '/index.php';
