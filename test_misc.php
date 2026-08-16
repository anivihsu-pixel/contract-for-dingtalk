<?php
// misc 模块闭环：回收站/归档/审计/资料库/全局搜索/提醒（admin 视角）
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
function req($method, $url, $post = [], $multipart = false) {
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
        curl_setopt($ch, CURLOPT_POSTFIELDS, $multipart ? $post : http_build_query($post));
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

// 1. 页面可达性
$pages = ['/archive' => '归档列表', '/recycle' => '数据回收站', '/audit' => '审计中心', '/resource' => '资料库', '/search' => '全局搜索', '/remind' => '提醒中心'];
foreach ($pages as $p => $name) {
    list($c, $b) = req('GET', $base . $p);
    $hasErr = strpos($b, 'Fatal error') !== false || strpos($b, 'think\\exception') !== false;
    check("$name [$p]", $c == 200 && !$hasErr, "code=$c");
}

// 2. 数据接口
list($c, $b) = req('GET', $base . '/ajax/audit/list?page=1&limit=20');
$d = json_decode($b, true);
check('审计列表接口', ($d['code'] ?? -1) === 0, substr($b, 0, 120));
list($c, $b) = req('GET', $base . '/ajax/resource/list?page=1&limit=20');
$d = json_decode($b, true);
check('资料库列表接口', ($d['code'] ?? -1) === 0, substr($b, 0, 120));
list($c, $b) = req('GET', $base . '/ajax/remind/check');
check('提醒check接口', strpos($b, '"code":0') !== false, substr($b, 0, 120));
list($c, $b) = req('GET', $base . '/ajax/remind/push-log?page=1&limit=20');
check('提醒推送日志接口', strpos($b, '"code":0') !== false, substr($b, 0, 120));

// 3. 全局搜索
list($c, $b) = req('GET', $base . '/search?q=%E5%91%A8%E5%B9%B4');
check('全局搜索页(周年)', $c == 200 && (strpos($b, 'Fatal') === false), "code=$c");
list($c, $b) = req('GET', $base . '/search?q=%E8%8B%8F%E5%B7%9E%E8%93%9D%E6%B5%B7');
check('全局搜索页(苏州蓝海)', $c == 200, "code=$c");

// 4. 回收站闭环：上传附件→创建合同→删除→恢复→再删→彻底删除
// 4.1 上传附件（multipart）
$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
$tmpFile = __DIR__ . '/misc_test.png';
file_put_contents($tmpFile, $png);
list($c, $b) = req('POST', $base . '/ajax/upload/contract', ['file' => new CURLFile($tmpFile, 'image/png', 'misc_test.png')], true);
$url = '';
$up = json_decode($b, true);
if (isset($up['data']['url'])) $url = $up['data']['url'];
check('上传附件返回url', $url !== '', "$c $b");
// 4.2 创建测试合同
list($c, $b) = req('POST', $base . '/ajax/contract/save', [
    'title' => 'misc回收站测试合同', 'business_type' => 'OTHER', 'direction' => 'sales', 'trade_attr' => '1',
    'party_a_customer_id' => '7', 'party_a_name' => '苏州蓝海物流有限公司', 'party_a_contact' => '沈峰', 'party_a_phone' => '13800007777',
    'our_company_id' => '1', 'amount' => '1000', 'effective_date' => '2026-08-01', 'expiry_date' => '2027-07-31',
    'party_b_name' => '义乌十八腔网络科技有限公司', 'party_b_credit_code' => '91330782MADEMO0001',
    'content' => '<p>misc 回收站闭环测试合同正文</p>', 'file_url' => json_encode([['url' => $url, 'name' => 'misc_test.png', 'size' => 68]]),
]);
$cid = 0;
if (preg_match('/"id":(\d+)/', $b, $m)) $cid = (int)$m[1];
check('创建回收站测试合同', $cid > 0, "$c $b");
// 4.3 软删
list($c, $b) = req('POST', $base . '/ajax/contract/delete', ['id' => $cid]);
check('软删合同', strpos($b, '"code":0') !== false, "$c $b");
// 4.4 回收站列表含它
list($c, $b) = req('GET', $base . '/ajax/recycle/list?page=1&limit=50');
check('回收站列表含测试合同', strpos($b, 'misc回收站测试合同') !== false, substr($b, 0, 300));
// 4.5 恢复
list($c, $b) = req('POST', $base . '/ajax/recycle/restore', ['id' => $cid, 'type' => 'contract']);
check('回收站恢复合同', strpos($b, '"code":0') !== false, "$c $b");
// 4.6 恢复后可见
list($c, $b) = req('GET', $base . '/ajax/contract/search?q=misc');
check('恢复后合同可搜到', strpos($b, 'misc回收站测试合同') !== false, substr($b, 0, 200));
// 4.7 再删 → 彻底删除
list($c, $b) = req('POST', $base . '/ajax/contract/delete', ['id' => $cid]);
list($c, $b) = req('POST', $base . '/ajax/recycle/purge', ['id' => $cid, 'type' => 'contract']);
check('彻底删除合同', strpos($b, '"code":0') !== false, "$c $b");
// 4.8 purge 后物理删除验证（直接查库）
require __DIR__ . '/vendor/autoload.php';
use think\App;
$app = new App(__DIR__); $app->initialize();
$gone = \think\facade\Db::name('contract')->where('id', $cid)->find() === null;
check('purge后记录物理消失', $gone);
// 4.9 清理临时文件
@unlink($tmpFile);

echo "\n===== misc 模块闭环: PASS=$PASS FAIL=$FAIL =====\n";
