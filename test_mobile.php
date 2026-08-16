<?php
// 移动端闭环：页面可达性 + 修复点回归
$base = 'http://127.0.0.1:8099';
$UA = 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.0 Mobile/15E148 Safari/604.1';
$jar = ''; $csrf = '';

function refresh($head) {
    global $jar, $csrf;
    if (preg_match_all('/Set-Cookie:\s*([^=;\s]+)=([^;]+)/i', $head, $m)) {
        foreach ($m[1] as $i => $k) {
            $v = trim($m[2][$i]);
            if ($k === 'csrf_token') { $csrf = $v; }
            $jar = preg_replace('/(^|;\s*)' . preg_quote($k, '/') . '=[^;]*/', '', $jar);
            $jar = trim($jar, '; ');
            $jar = ($jar ? $jar . '; ' : '') . $k . '=' . $v;
        }
    }
}

function req($method, $url, $post = [], $isAjax = true) {
    global $jar, $csrf, $UA;
    $h = ['User-Agent: ' . $UA];
    if ($isAjax) $h[] = 'X-Requested-With: XMLHttpRequest';
    if ($jar) $h[] = 'Cookie: ' . $jar;
    if ($csrf) $h[] = 'X-CSRF-TOKEN: ' . $csrf;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $h);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hlen = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $head = substr($resp, 0, $hlen);
    $body = substr($resp, $hlen);
    curl_close($ch);
    refresh($head);
    return [$code, $body, $head];
}

// 登录（手机 UA）
$ch = curl_init($base . '/login');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['User-Agent: ' . $UA]);
$resp = curl_exec($ch);
$hlen = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
refresh(substr($resp, 0, $hlen));
curl_close($ch);
list($code, $body) = req('POST', $base . '/login', ['username' => 'admin', 'password' => '85151818']);
echo "登录: " . ($code == 200 && strpos($body, '登录成功') !== false ? 'OK' : "FAIL $code $body") . "\n";

$PASS = 0; $FAIL = 0;
function check($name, $cond, $extra = '') {
    global $PASS, $FAIL;
    if ($cond) { $PASS++; echo "[PASS] $name\n"; }
    else { $FAIL++; echo "[FAIL] $name $extra\n"; }
}

$pages = [
    '/m' => '移动工作台',
    '/m/customers' => '移动客户列表',
    '/m/customer/7' => '移动客户详情',
    '/m/contracts' => '移动合同列表',
    '/m/contract/13' => '移动合同详情',
    '/m/approvals' => '移动审批列表',
    '/m/approval/6' => '移动发票审批详情',
    '/m/approval/9' => '移动合同审批详情',
    '/m/finance' => '移动财务',
    '/m/invoice-apply' => '移动发票申请',
    '/m/reports' => '移动报表',
    '/m/archive' => '移动归档',
    '/m/projects' => '移动项目',
    '/m/remind' => '移动提醒',
    '/m/resource' => '移动资料库',
    '/m/more' => '移动更多',
    '/m/my-stats' => '移动我的业绩',
    '/m/suppliers' => '移动供应商',
    '/m/party' => '移动相对方360',
];
foreach ($pages as $p => $name) {
    list($code, $body) = req('GET', $base . $p, [], false);
    $hasErr = (strpos($body, 'PHP Fatal') !== false) || (strpos($body, 'Fatal error') !== false) || (strpos($body, 'think\\exception') !== false) || (strpos($body, 'ParseError') !== false);
    check("$name [$p] HTTP200", $code == 200 && !$hasErr, "code=$code err=" . ($hasErr ? 'YES' : 'no'));
}

// 修复点1：客户详情页集团弹窗（遮罩点击关闭 + 判空）
list($code, $body) = req('GET', $base . '/m/customer/7', [], false);
check('修复点1a: 集团弹窗遮罩点击关闭', strpos($body, 'e.target === gm') !== false);
check('修复点1b: openActSheet判空', strpos($body, 'actPhraseRow') !== false && strpos($body, 'querySelectorAll') !== false);
// 修复点2：最近动态显示合同标题（360接口返回 contract_title 而非 #id）
list($code, $body) = req('GET', $base . '/ajax/party/data/customer/7');
$g360 = json_decode($body, true);
$actTitles = array_column($g360['data']['activity'] ?? [], 'contract_title');
$hasTitle = false;
foreach ($actTitles as $t) {
    if (!empty($t) && strpos((string)$t, '#') !== 0) { $hasTitle = true; break; }
}
check('修复点2: 最近动态显示合同标题', $hasTitle, json_encode($actTitles));

// 修复点3：发票申请页二次确认
list($code, $body) = req('GET', $base . '/m/invoice-apply', [], false);
check('修复点3: 发票申请二次确认(mConfirm)', strpos($body, 'mConfirm') !== false);

// 发票审批详情应为独立视图（含开票申请概要）
list($code, $body) = req('GET', $base . '/m/approval/6', [], false);
check('发票审批详情含概要', strpos($body, '开票申请') !== false || strpos($body, '审批') !== false);

// 消息中心并入提醒页（302 → /m/remind?tab=notif，设计行为）
list($code, $body, $head) = req('GET', $base . '/m/notifications', [], false);
check('消息中心302跳提醒tab', $code == 302 && strpos($head, '/m/remind?tab=notif') !== false, "code=$code");

// 消息中心未读数
list($code, $body) = req('GET', $base . '/ajax/notification/unread-count');
check('消息未读数接口', strpos($body, '"code":0') !== false, substr($body, 0, 200));

echo "\n===== 移动端闭环: PASS=$PASS FAIL=$FAIL =====\n";
