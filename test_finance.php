<?php
// 财务中心 + 报表闭环测试（admin 财务 ALL 视角）
$base = 'http://127.0.0.1:8099';
$jar = ''; $csrf = '';
function refresh($head) {
    global $jar, $csrf;
    if (preg_match_all('/Set-Cookie:\s*([^=;\s]+)=([^;]+)/i', $head, $m)) {
        foreach ($m[1] as $i => $k) {
            $v = trim($m[2][$i]);
            if ($k === 'csrf_token') $csrf = $v;
            $jar = preg_replace('/(^|;\s*)' . preg_quote($k, '/') . '=[^;]*/', '', (string)$jar);
            $jar = trim($jar, '; ');
            $jar = ($jar ? $jar . '; ' : '') . $k . '=' . $v;
        }
    }
}
function req($method, $url, $post = []) {
    global $jar, $csrf;
    $h = ['X-Requested-With: XMLHttpRequest'];
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
    return [$code, $body];
}
// 登录 admin
$ch = curl_init($base . '/login');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
$resp = curl_exec($ch);
refresh(substr($resp, 0, curl_getinfo($ch, CURLINFO_HEADER_SIZE)));
curl_close($ch);
list($c, $b) = req('POST', $base . '/login', ['username' => 'admin', 'password' => '85151818']);
echo "登录: " . (strpos($b, '登录成功') !== false ? 'OK' : "FAIL $b") . "\n";

$PASS = 0; $FAIL = 0;
function check($name, $cond, $extra = '') {
    global $PASS, $FAIL;
    if ($cond) { $PASS++; echo "[PASS] $name\n"; }
    else { $FAIL++; echo "[FAIL] $name $extra\n"; }
}

// 页面可达性
$pages = [
    '/finance' => '财务中心(收支概览)',
    '/finance/tax' => '税务管理',
    '/report/monthly' => '经营月报',
    '/report/weekly' => '经营周报',
    '/report/aging' => '应收账龄',
];
foreach ($pages as $p => $name) {
    list($c, $b) = req('GET', $base . $p);
    $hasErr = strpos($b, 'Fatal error') !== false || strpos($b, 'think\\exception') !== false;
    check("$name [$p]", $c == 200 && !$hasErr, "code=$c");
}

// 数据接口
list($c, $b) = req('GET', $base . '/ajax/finance/payment-list?page=1&limit=50');
$d = json_decode($b, true);
check('回款列表接口', ($d['code'] ?? -1) === 0, substr($b, 0, 150));
list($c, $b) = req('GET', $base . '/ajax/finance/invoice-list?page=1&limit=50');
$d = json_decode($b, true);
check('发票列表接口', ($d['code'] ?? -1) === 0, substr($b, 0, 150));
list($c, $b) = req('GET', $base . '/ajax/finance/tax-data');
$d = json_decode($b, true);
check('税务数据接口', ($d['code'] ?? -1) === 0, substr($b, 0, 150));
list($c, $b) = req('GET', $base . '/ajax/report/monthly-data?year=2026&month=8');
$d = json_decode($b, true);
check('月报数据接口', ($d['code'] ?? -1) === 0, substr($b, 0, 150));

// 账龄页数据（页面内联，验证含账龄统计）
list($c, $b) = req('GET', $base . '/report/aging');
check('账龄页含统计', strpos($b, '账龄') !== false, 'no 账龄 keyword');

echo "\n===== 财务中心+报表: PASS=$PASS FAIL=$FAIL =====\n";
