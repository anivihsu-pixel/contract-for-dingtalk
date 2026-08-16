<?php
return [
    // 视图文件为原生 PHP，并在文件内通过 __DIR__ 引入公共布局。
    // 使用 Think 模板编译器会把文件复制到 runtime/temp，导致 __DIR__ 指向缓存目录。
    'type'               => 'Php',
    'view_suffix'        => 'php',
    'view_path'          => '',
    'tpl_begin'          => '{',
    'tpl_end'            => '}',
    'taglib_begin'       => '{',
    'taglib_end'         => '}',
    'tpl_replace_string' => [
        '__STATIC__' => '/static',
        '__CSS__'    => '/static/css',
        '__JS__'     => '/static/js',
    ],
];
