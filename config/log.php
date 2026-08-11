<?php
// +----------------------------------------------------------------------
// | 日志配置
// +----------------------------------------------------------------------

return [
    'default'      => 'file',
    'channels'     => [
        'file' => [
            'type'      => 'File',
            'path'      => app()->getRuntimePath() . 'log',
            'single'    => false,
            // S-07：日志级别由 APP_DEBUG 驱动——生产（APP_DEBUG=false）收敛为 error/warning，
            // 避免 SQL 日志记录业务敏感查询参数；本地演示（APP_DEBUG=true）保留全量便于排查
            'level'     => env('APP_DEBUG', false) ? ['error', 'notice', 'info', 'debug', 'sql'] : ['error', 'warning'],
            'apart_level' => ['error', 'sql'],
            'max_files' => 30,
        ],
    ],
];
