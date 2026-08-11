<?php
// +----------------------------------------------------------------------
// | 数据库配置（默认 MySQL；本地开发可显式设 DB_TYPE=sqlite 兼容）
// +----------------------------------------------------------------------

return [
    // 原生默认 MySQL：生产/部署环境直接用 MySQL，无需改代码；
    // 本地开发如暂无 MySQL，可在 .env 显式设 DB_TYPE=sqlite 回落 SQLite。
    'default'     => env('DB_TYPE', 'mysql'),

    'connections' => [
        'sqlite' => [
            'type'            => 'sqlite',
            'database'        => app()->getRuntimePath() . 'data/contract.db',
            'prefix'          => '',
            'debug'           => env('APP_DEBUG', false),
            'resultset_type'  => 'array',
            'auto_timestamp'  => false,
            'datetime_format' => 'Y-m-d H:i:s',
            'sql_explain'     => false,
            'cache_store'     => 'file',
        ],
        'mysql' => [
            'type'            => 'mysql',
            'hostname'        => env('DB_HOST', '127.0.0.1'),
            'port'            => env('DB_PORT', '3306'),
            'database'        => env('DB_NAME', 'contract_dingtalk'),
            'username'        => env('DB_USER', 'root'),
            // P2：不内置默认口令——DB 口令必须在部署环境的 .env 显式配置（DB_PASS），
            // 缺省空串时连接失败，避免沿用代码内弱口令 root/root 上线。
            'password'        => env('DB_PASS', ''),
            'prefix'          => '',
            'charset'         => 'utf8mb4',
            'collation'       => 'utf8mb4_unicode_ci',
            'debug'           => env('APP_DEBUG', false),
            'resultset_type'  => 'array',
            'auto_timestamp'  => false,
            'datetime_format' => 'Y-m-d H:i:s',
            'sql_explain'     => false,
            'break_reconnect' => true,
            // 时区对齐：强制 MySQL 会话时区为 +08:00，与 config/app.php 的
            // default_timezone（Asia/Shanghai）保持一致，避免 DB 层 NOW()/CURRENT_TIMESTAMP
            // 与 PHP date() 因 MySQL 服务器全局时区不同而错位（迁移 SQLite→MySQL 必设）。
            'params'          => [
                \PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+08:00'",
                // 启用 SSL 传输加密时取消注释并配置 CA 证书（要求应用账户 REQUIRE SSL，
                // 见 MIGRATION_SQLITE_TO_MYSQL.md §5.2）：
                // \PDO::MYSQL_ATTR_SSL_CA => '/path/ca.pem',
                // \PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => true,
            ],
            'cache_store'     => 'file',
        ],
    ],
];
