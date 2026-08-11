<?php
return [
    'type'               => 'Think',
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
