<?php
// +----------------------------------------------------------------------
// | 应用配置
// +----------------------------------------------------------------------

return [
    'app_debug'              => env('APP_DEBUG', false),
    'auto_multi_app'         => false,
    'default_app'            => '',
    'default_controller'     => 'Auth',
    'controller_suffix'      => true,
    'default_timezone'       => 'Asia/Shanghai',
    'default_lang'           => 'zh-cn',
    'default_filter'         => 'trim,strip_tags',

    // 错误提示
    'show_error_msg'         => true,
    'error_message'          => '页面错误！请稍后再试～',

    // URL 配置
    'url_convert'            => false,
];
