<?php
// 审批中心转交闭环测试（带 CSRF）
$base = 'http://127.0.0.1:8099';
$cookies = [];       // user => cookie jar string
$csrfPerUser = [];   // user => csrf_token

function refreshCsrf($user, $head) {
    global $cookies, $csrfPerUser;
    // 解析所有 Set-Cookie，合并进 jar（处理 session id 重新生成）
    if (preg_match_all('/Set-Cookie:\s*([^=;\s]+)=([^;]+)/i', $head, $m)) {
        foreach ($m[1] as $i => $k) {
            $v = trim($m[2][$i]);
            if ($k === 'csrf_token') { $csrfPerUser[$user] = $v; }
            // 从 jar 移除同名 cookie 再重加
            $cookies[$user] = preg_replace('/(^|;\s*)' . preg_quote($k, '/') . '=[^;]*/', '', $cookies[$user]);
            $cookies[$user] = trim($cookies[$user], '; ');
            $cookies[$user] = ($cookies[$user] ? $cookies[$user] . '; ' : '') . $k . '=' . $v;
        }
    }
}

function http($method, $url, $user = '', $post = [], $headers = []) {
    global $cookies, $csrfPerUser;
    $h = ['X-Requested-With: XMLHttpRequest'];
    if ($user) {
        if (!empty($cookies[$user])) $h[] = 'Cookie: ' . $cookies[$user];
        if (!empty($csrfPerUser[$user])) $h[] = 'X-CSRF-TOKEN: ' . $csrfPerUser[$user];
    }
    foreach ($headers as $x) $h[] = $x;
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
    if ($user) refreshCsrf($user, $head);
    return [$code, $body];
}

function login($base, $user) {
    global $cookies;
    // 1. GET /login 拿 csrf_token + SID
    $ch = curl_init($base . '/login');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    $resp = curl_exec($ch);
    $hlen = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $head = substr($resp, 0, $hlen);
    curl_close($ch);
    $jar = [];
    if (preg_match_all('/Set-Cookie:\s*([^;]+);/i', $head, $m)) $jar = $m[1];
    $cookies[$user] = implode('; ', $jar);
    refreshCsrf($user, $head);
    // 2. POST /login
    list($code, $body) = http('POST', $base . '/login', $user, ['username' => $user, 'password' => 'password']);
    return [$body, $cookies[$user]];
}

$PASS = 0; $FAIL = 0;
function check($name, $cond, $extra = '') {
    global $PASS, $FAIL;
    if ($cond) { $PASS++; echo "[PASS] $name\n"; }
    else { $FAIL++; echo "[FAIL] $name $extra\n"; }
}

// 1. employee01 登录
list($body,) = login($base, 'employee01');
check('employee01 登录', strpos($body, '登录成功') !== false, $body);
if (strpos($body, '登录成功') === false) { echo "resp: $body\n"; exit; }

// 2. 提交合同14 重新审批
list($code, $body) = http('POST', $base . '/ajax/approval/submit', 'employee01', ['contract_id' => 14]);
echo "  submit14 => $code $body\n";
$instId = 0;
if (preg_match('/instance_id.?":(\d+)/', $body, $m)) $instId = (int)$m[1];
check('合同14重新提交生成新实例', $instId > 0, $body);

// 3. manager01 登录，待办列表应含新实例
list($body,) = login($base, 'manager01');
check('manager01 登录', strpos($body, '登录成功') !== false, $body);
list($code, $body) = http('GET', $base . '/ajax/approval/pending-list?page=1&limit=50', 'manager01');
check('manager01 待办列表含新实例', strpos($body, "\"id\":$instId") !== false, substr($body, 0, 300));

// 3.5 转交目标搜索接口
list($code, $body) = http('GET', $base . '/ajax/approval/transfer-targets?keyword=%E8%B4%A2%E5%8A%A1&page=1', 'manager01');
check('转交目标搜索(财务)', strpos($body, '王财务') !== false || strpos($body, 'finance') !== false, substr($body, 0, 300));

// 4. manager01 转交财务 (transfer_to=4)
list($code, $body) = http('POST', $base . "/ajax/approval/$instId/action", 'manager01', ['action' => 'TRANSFERRED', 'transfer_to' => 4, 'comment' => '闭环测试：转交财务']);
check("实例$instId 转交财务", strpos($body, '操作成功') !== false, "$code $body");

// 5. finance01 登录，待办含实例
list($body,) = login($base, 'finance01');
check('finance01 登录', strpos($body, '登录成功') !== false, $body);
list($code, $body) = http('GET', $base . '/ajax/approval/pending-list?page=1&limit=50', 'finance01');
check('finance01 待办列表含被转交实例', strpos($body, "\"id\":$instId") !== false, substr($body, 0, 300));

// 6. finance01 同意
list($code, $body) = http('POST', $base . "/ajax/approval/$instId/action", 'finance01', ['action' => 'APPROVED', 'comment' => '闭环测试：财务同意']);
check("实例$instId 财务同意", strpos($body, '操作成功') !== false, "$code $body");

// 7. 我提交/已办列表验证
list($code, $body) = http('GET', $base . '/ajax/approval/submitted-list?page=1&limit=50', 'employee01');
check('employee01 我提交列表含实例', strpos($body, "\"id\":$instId") !== false, substr($body, 0, 300));
list($code, $body) = http('GET', $base . '/ajax/approval/processed-list?page=1&limit=50', 'finance01');
check('finance01 已办列表含实例', strpos($body, "\"id\":$instId") !== false, substr($body, 0, 300));

// 8. 非当前审批人无权操作（employee01 试图对已完成的实例操作）
list($code, $body) = http('POST', $base . "/ajax/approval/$instId/action", 'employee01', ['action' => 'APPROVED']);
check('非审批人操作被拒', strpos($body, '不是该节点审批人') !== false, "$code $body");

echo "\n===== 审批中心转交闭环: PASS=$PASS FAIL=$FAIL =====\n";
