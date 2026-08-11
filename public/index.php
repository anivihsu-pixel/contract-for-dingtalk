<?php
// +----------------------------------------------------------------------
// | Web 入口文件
// +----------------------------------------------------------------------

namespace think;

// 抑制 PHP 8.4 废弃警告，防止输出在 DOCTYPE 之前破坏前端
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

require __DIR__ . '/../vendor/autoload.php';

// 环境变量
if (is_file(__DIR__ . '/../.env')) {
    \Dotenv\Dotenv::createImmutable(__DIR__ . '/../')->load();
}

$http = (new App(dirname(__DIR__) . '/'))->http;
$response = $http->run();
$response->send();
$http->end($response);
