<?php
// 权限/数据范围验证（PC 视角）
$base = 'http://127.0.0.1:8099';
$cookies = []; $csrf = [];

function refreshCsrf($user, $head) {
    global $cookies, $csrf;
    if (preg_match_all('/Set-Cookie:\s*([^=;\s]+)=([^;]+)/i', $head, $m)) {
        foreach ($m[1] as $i => $k) {
            $v = trim($m[2][$i]);
            if ($k === 'csrf_token') $csrf[$user] = $v;
            $cookies[$user] = preg_replace('/(^|;\s*)' . preg_quote($k, '/') . '=[^;]*/', '', (string)($cookies[$user] ?? ''));
            $cookies[$user] = trim($cookies[$user], '; ');
            $cookies[$user] = ($cookies[$user] ? $cookies[$user] . '; ' : '') . $k . '=' . $v;
        }
    }
}
function http($method, $url, $user = '', $post = []) {
    global $cookies, $csrf;
    $h = ['X-Requested-With: XMLHttpRequest'];
    if ($user) {
        if (!empty($cookies[$user])) $h[] = 'Cookie: ' . $cookies[$user];
        if (!empty($csrf[$user])) $h[] = 'X-CSRF-TOKEN: ' . $csrf[$user];
    }
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
    return [$code, $body, $head];
}
function login($user, $pwd = 'password') {
    global $base;
    $ch = curl_init($base . '/login');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    $resp = curl_exec($ch);
    refreshCsrf($user, substr($resp, 0, curl_getinfo($ch, CURLINFO_HEADER_SIZE)));
    curl_close($ch);
    list($code, $body) = http('POST', $base . '/login', $user, ['username' => $user, 'password' => $pwd]);
    return strpos($body, '登录成功') !== false;
}

$PASS = 0; $FAIL = 0;
function check($name, $cond, $extra = '') {
    global $PASS, $FAIL;
    if ($cond) { $PASS++; echo "[PASS] $name\n"; }
    else { $FAIL++; echo "[FAIL] $name $extra\n"; }
}

// 登录四账号
foreach (['admin' => '85151818', 'manager01' => 'password', 'employee01' => 'password', 'finance01' => 'password'] as $u => $p) {
    check("$u 登录", login($u, $p));
}

// 1. 数据范围：合同搜索（接口参数为 q）
// employee01 (SELF) 应只见 owner=3 合同，不见 owner=2 合同1(上海云启)
list($c, $b) = http('GET', $base . '/ajax/contract/search?q=%E4%B8%8A%E6%B5%B7%E4%BA%91%E5%90%AF', 'employee01');
check('employee01 搜索不到他人合同(上海云启)', strpos($b, '上海云启') === false, substr($b, 0, 200));
list($c, $b) = http('GET', $base . '/ajax/contract/search?q=%E6%9D%8E%E5%91%98%E5%B7%A5', 'employee01');
check('employee01 能搜到本人合同(李员工-测试)', strpos($b, '李员工-测试服务合同') !== false, substr($b, 0, 200));

// manager01 (DEPT) 应见部门内全部（含 owner=2/3）
list($c, $b) = http('GET', $base . '/ajax/contract/search?q=%E4%B8%8A%E6%B5%B7%E4%BA%91%E5%90%AF', 'manager01');
check('manager01 可见部门内合同(上海云启)', strpos($b, '上海云启') !== false, substr($b, 0, 200));

// admin (ALL) 全可见
list($c, $b) = http('GET', $base . '/ajax/contract/search?q=%E4%B8%8A%E6%B5%B7%E4%BA%91%E5%90%AF', 'admin');
check('admin 可见全部合同(上海云启)', strpos($b, '上海云启') !== false, substr($b, 0, 200));

// 2. employee01 打开他人合同1 详情 → 404 隐藏存在性（设计）
list($c, $b) = http('GET', $base . '/contract/1', 'employee01');
check('employee01 打开他人合同被拒(404隐藏)', $c == 404, "code=$c " . substr($b, 0, 120));
// employee01 打开本人合同13 → 200
list($c, $b) = http('GET', $base . '/contract/13', 'employee01');
check('employee01 打开本人合同正常', $c == 200, "code=$c");

// 3. 数据回收站仅超管
list($c, $b) = http('GET', $base . '/recycle', 'employee01');
check('employee01 访问回收站被拒', $c == 403 || strpos($b, '无权限') !== false || strpos($b, '无权') !== false, "code=$c");
list($c, $b) = http('GET', $base . '/recycle', 'admin');
check('admin 访问回收站正常', $c == 200, "code=$c");

// 4. 财务中心：普通用户只读可见（有 payment:view/invoice:view），写操作被拒
list($c, $b) = http('GET', $base . '/finance', 'employee01');
check('employee01 财务中心只读可见(设计)', $c == 200, "code=$c");
list($c, $b) = http('POST', $base . '/ajax/payment/confirm', 'employee01', ['id' => 14]);
check('employee01 确认回款被拒(无payment:create)', $c == 403 || strpos($b, '无权限') !== false || strpos($b, '无权') !== false, "$c " . substr($b, 0, 120));
list($c, $b) = http('GET', $base . '/finance', 'finance01');
check('finance01 访问财务中心正常', $c == 200, "code=$c");

// 5. 系统管理仅超管
list($c, $b) = http('GET', $base . '/admin/user', 'manager01');
check('manager01 访问系统管理被拒', $c == 403 || strpos($b, '无权限') !== false || strpos($b, '无权') !== false, "code=$c");

// 6. 系统管理仅超管（补充）
list($c, $b) = http('GET', $base . '/admin/role', 'manager01');
check('manager01 访问角色管理被拒', $c == 403 || strpos($b, '无权限') !== false || strpos($b, '无权') !== false, "code=$c");
list($c, $b) = http('GET', $base . '/admin/user', 'admin');
check('admin 访问用户管理正常', $c == 200, "code=$c");

echo "\n===== 权限/数据范围: PASS=$PASS FAIL=$FAIL =====\n";
