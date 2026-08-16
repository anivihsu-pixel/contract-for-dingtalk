<?php
// 系统管理闭环测试（admin 视角）
$base = 'http://127.0.0.1:8099';
$jars = []; $csrfs = [];
function refresh($user, $head) {
    global $jars, $csrfs;
    if (preg_match_all('/Set-Cookie:\s*([^=;\s]+)=([^;]+)/i', $head, $m)) {
        foreach ($m[1] as $i => $k) {
            $v = trim($m[2][$i]);
            if ($k === 'csrf_token') $csrfs[$user] = $v;
            $jars[$user] = preg_replace('/(^|;\s*)' . preg_quote($k, '/') . '=[^;]*/', '', (string)($jars[$user] ?? ''));
            $jars[$user] = trim($jars[$user], '; ');
            $jars[$user] = ($jars[$user] ? $jars[$user] . '; ' : '') . $k . '=' . $v;
        }
    }
}
function req($user, $method, $url, $post = []) {
    global $jars, $csrfs;
    $h = ['X-Requested-With: XMLHttpRequest'];
    if (!empty($jars[$user])) $h[] = 'Cookie: ' . $jars[$user];
    if (!empty($csrfs[$user])) $h[] = 'X-CSRF-TOKEN: ' . $csrfs[$user];
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
    refresh($user, $head);
    return [$code, $body];
}
function login($user, $pwd) {
    global $base;
    $ch = curl_init($base . '/login');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    $resp = curl_exec($ch);
    refresh($user, substr($resp, 0, curl_getinfo($ch, CURLINFO_HEADER_SIZE)));
    curl_close($ch);
    list($c, $b) = req($user, 'POST', $base . '/login', ['username' => $user, 'password' => $pwd]);
    return strpos($b, '登录成功') !== false;
}

$PASS = 0; $FAIL = 0;
function check($name, $cond, $extra = '') {
    global $PASS, $FAIL;
    if ($cond) { $PASS++; echo "[PASS] $name\n"; }
    else { $FAIL++; echo "[FAIL] $name $extra\n"; }
}

// 幂等清理（重跑安全）：删除历史测试用户及其关联
require __DIR__ . '/vendor/autoload.php';
use think\App;
$app = new App(__DIR__); $app->initialize();
foreach (['testuser01', 'testleave02'] as $un) {
    $old = \think\facade\Db::name('user')->where('username', $un)->find();
    if ($old) {
        $oid = (int)$old['id'];
        \think\facade\Db::name('user_role')->where('user_id', $oid)->delete();
        \think\facade\Db::name('user')->where('id', $oid)->delete();
    }
}
// 清理历史测试客户
\think\facade\Db::name('customer')->where('name', '离职交接测试客户')->delete();

check('admin 登录', login('admin', '85151818'));

// 1. 页面可达性
$pages = [
    '/admin' => '系统管理首页', '/admin/user' => '用户管理', '/admin/role' => '角色管理',
    '/admin/flow' => '审批流', '/admin/dict' => '字典管理', '/admin/config' => '系统配置',
    '/admin/dingtalk' => '钉钉配置', '/admin/invoice-form' => '发票表单设计', '/admin/form-builder' => '通用表单设计',
    '/audit' => '审计中心', '/company' => '本公司主体',
];
foreach ($pages as $p => $name) {
    list($c, $b) = req('admin', 'GET', $base . $p);
    $hasErr = strpos($b, 'Fatal error') !== false || strpos($b, 'think\\exception') !== false;
    check("$name [$p]", $c == 200 && !$hasErr, "code=$c");
}

// 2. 用户闭环：创建 testuser01
list($c, $b) = req('admin', 'POST', $base . '/ajax/admin/user/save', [
    'username' => 'testuser01', 'name' => '测试用户甲', 'password' => 'test123456',
    'dept_id' => 1, 'status' => 1, 'role_ids' => [5], 'email' => '', 'mobile' => '',
]);
check('创建用户 testuser01', strpos($b, '保存成功') !== false, "$c $b");
// 登录成功（新用户无 force_reset）
check('testuser01 登录成功', login('testuser01', 'test123456'));
// 编辑改名
$uid = 0;
$tu = \think\facade\Db::name('user')->where('username', 'testuser01')->find();
$uid = (int)($tu['id'] ?? 0);
check('testuser01 id 获取', $uid > 0);
list($c, $b) = req('admin', 'POST', $base . '/ajax/admin/user/save', [
    'id' => $uid, 'username' => 'testuser01', 'name' => '测试用户甲改', 'dept_id' => 1, 'status' => 1,
]);
check('编辑用户改名', strpos($b, '保存成功') !== false, "$c $b");
// 禁用
list($c, $b) = req('admin', 'POST', $base . '/ajax/admin/user/delete', ['id' => $uid]);
check('禁用用户', strpos($b, '已禁用') !== false, "$c $b");
// 禁用后登录失败
check('禁用用户登录被拒', !login('testuser01', 'test123456'));
// 恢复
list($c, $b) = req('admin', 'POST', $base . '/ajax/admin/user/restore', ['id' => $uid]);
check('恢复用户', strpos($b, '已恢复') !== false, "$c $b");
check('恢复后登录成功', login('testuser01', 'test123456'));

// 3. 离职交接闭环：testleave02 自建客户 → admin 交接给 employee01
list($c, $b) = req('admin', 'POST', $base . '/ajax/admin/user/save', [
    'username' => 'testleave02', 'name' => '离职测试乙', 'password' => 'test123456',
    'dept_id' => 1, 'status' => 1, 'role_ids' => [5], 'email' => '', 'mobile' => '',
]);
check('创建用户 testleave02', strpos($b, '保存成功') !== false, "$c $b");
check('testleave02 登录', login('testleave02', 'test123456'));
$tu2 = \think\facade\Db::name('user')->where('username', 'testleave02')->find();
$uid2 = (int)($tu2['id'] ?? 0);
// testleave02 创建客户（不传信用码避免格式校验）
list($c, $b) = req('testleave02', 'POST', $base . '/ajax/customer/save', [
    'name' => '离职交接测试客户', 'credit_code' => '', 'source' => 'MANUAL', 'industry' => 'OTHER',
]);
$custId = 0;
if (preg_match('/"id":(\d+)/', $b, $m)) $custId = (int)$m[1];
check('testleave02 创建客户', $custId > 0, "$c $b");
// 验证客户 owner=uid2
$cu = \think\facade\Db::name('customer')->where('id', $custId)->find();
check("客户归属 testleave02(owner=$uid2)", (int)($cu['owner_id'] ?? 0) === $uid2, json_encode($cu));
// admin 离职交接 testleave02 → employee01 (用户3)
list($c, $b) = req('admin', 'POST', $base . '/ajax/admin/user/handover', [
    'from_user_id' => $uid2, 'to_user_id' => 3,
    'scope_customer' => 1, 'scope_contract' => 1, 'scope_approval' => 1, 'disable_from' => 1,
]);
check('离职交接 testleave02→employee01', strpos($b, '交接完成') !== false, "$c $b");
// 验证客户 owner=3、testleave02 已禁用
$cu = \think\facade\Db::name('customer')->where('id', $custId)->find();
check('交接后客户归属 employee01', (int)($cu['owner_id'] ?? 0) === 3, json_encode($cu));
$tu2b = \think\facade\Db::name('user')->where('id', $uid2)->find();
check('交接后 testleave02 已禁用', (int)($tu2b['status'] ?? 1) === 2);
check('testleave02 登录被拒', !login('testleave02', 'test123456'));

// 4. 清理：删除临时客户
list($c, $b) = req('admin', 'POST', $base . '/ajax/customer/delete', ['id' => $custId]);
check('清理临时客户', strpos($b, '"code":0') !== false, "$c $b");

echo "\n===== 系统管理闭环: PASS=$PASS FAIL=$FAIL =====\n";
