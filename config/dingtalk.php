<?php
// +----------------------------------------------------------------------
// | 钉钉配置
// +----------------------------------------------------------------------

return [
    'app_key'    => env('DINGTALK_APP_KEY', ''),
    'app_secret' => env('DINGTALK_APP_SECRET', ''),
    'corp_id'    => env('DINGTALK_CORP_ID', ''),
    'agent_id'   => env('DINGTALK_AGENT_ID', ''),
    'app_url'    => env('DINGTALK_APP_URL', ''),
    // P0-2【严重·C3】默认关闭 Mock 模式：env 未显式配置时回退到 false（生产安全），
    // 杜绝「env 漏配 → 默认开启 Mock → 可被伪造登录」的风险。生产部署须置 false 并配置真实钉钉凭据。
    'mock_mode'  => env('DINGTALK_MOCK_MODE', false),

    // API 地址
    'base_url'   => 'https://oapi.dingtalk.com',
    'timeout'    => 10,

    // REV-03：TLS 证书校验（默认开启，仅本地自签名调试可临时关闭）
    'ssl_verify' => true,
    // 自定义 CA 证书包路径（可选，留空则用 PHP/curl 内置 CA 库）
    'cafile'     => env('DINGTALK_CAFILE', ''),

    // REV-03：免登自动建号白名单（安全边界）
    // 默认关闭自动建号——钉钉用户必须先由管理员在系统中预置（绑定 dingtalk_userid/unionid）方可免登；
    // 仅当 auto_create_user=true 且 userid 命中 allowed_userids 白名单时才允许自动开户，杜绝匿名伪造建号。
    'auto_create_user' => false,
    'allowed_userids'  => [],

    // 工作通知消息配置
    // action_card：携带跳转链接的通知改用「单按钮卡片」，点击按钮在钉钉内（微应用 WebView）打开，
    // 避免 markdown 内联链接被钉钉当外部浏览器打开（需求：审批通知点击应在钉钉内打开合同管理应用）。
    // 无跳转链接的通知（如到期提醒）仍回退为 markdown。
    'msg_type'   => 'action_card',
];
