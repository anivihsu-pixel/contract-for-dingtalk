<?php
// v2.43.0【档3-A】图标子集生成器（正式维护工具，配合 scripts/check_icons.sh 门禁）
// 作用：读取白名单 scripts/icons_whitelist.txt → 解析全量 bootstrap-icons.min.css 提取码点
//       → 生成子集 CSS（仅白名单 ::before 规则）+ 输出字体码点列表；
//       字体子集化由 python -m fontTools.subset 执行（见本文件尾部命令）。
// 何时使用：新增图标 → 补白名单 → 运行本脚本重新生成子集 CSS/字体 → 替换 vendor 产物。
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
$ROOT = dirname(__DIR__);

// 1) 白名单
$wl = array_filter(array_map('trim', file($ROOT . '/scripts/icons_whitelist.txt')), function ($l) {
    return $l !== '' && strpos($l, '#') !== 0;
});
$wl = array_values(array_unique($wl));

// 2) 解析全量 CSS（优先本地全量备份 runtime/_bi_full.css，缺失则从 jsdelivr 下载）：
//    类名 → content 码点。⚠ 不能解析 public/static/vendor/ 下的子集 CSS（已被裁剪）。
$fullCssPath = $ROOT . '/runtime/_bi_full.css';
if (!is_file($fullCssPath)) {
    // 构建工具一次性下载（运行时页面已全站自托管，此处仅为重建子集时的素材源）
    $src = @file_get_contents('https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css');
    if ($src === false) {
        fwrite(STDERR, "无法获取全量 bootstrap-icons CSS（需联网或提供 runtime/_bi_full.css）\n");
        exit(1);
    }
    file_put_contents($fullCssPath, $src);
}
$css = file_get_contents($fullCssPath);
if (!preg_match_all('/\.bi-([a-z0-9-]+)::before\{content:"\\\\([0-9a-f]+)"/', $css, $m, PREG_SET_ORDER)) {
    fwrite(STDERR, "CSS 解析失败\n");
    exit(1);
}
$map = [];
foreach ($m as $row) {
    $map[$row[1]] = $row[2];
}

// 3) 校验白名单全部存在
$missing = array_diff($wl, array_keys($map));
if ($missing) {
    fwrite(STDERR, "白名单存在但 CSS 无此图标: " . implode(', ', $missing) . "\n");
    exit(1);
}

// 4) 生成子集 CSS（通用字体声明 + @font-face 双格式 + 白名单 content 规则）
// 版本参数：读 config/version.php 注入文件名与 @font-face 字体 URL —— 每次重子集化版本号变化，
// CSS 与字体文件名都带版本号（v2.43.1 起文件名版本化），浏览器缓存自动失效，
// 杜绝「旧 CSS + 新字体」混用导致子集码点缺失显示方框（钉钉 WebView 缓存同名文件场景）。
// @font-face 双格式（v2.43.2 起）：woff2 外链 + woff base64 内嵌 —— 钉钉 WebView 对 woff2 加载失败时
// 回退 base64 内嵌 woff（无需字体网络请求，CSS 能加载即能渲染）。woff base64 来自
// runtime/_bi_subset.woff.base64.txt（由 fontTools 从子集 woff2 转换生成，见步骤 5 后注释）。
$ver = '1';
if (is_file($ROOT . '/config/version.php')) {
    $vc = file_get_contents($ROOT . '/config/version.php');
    if (preg_match("/return\s+'v([0-9.]+)'/", $vc, $vm)) $ver = $vm[1];
}
$woffB64 = '';
$b64File = $ROOT . '/runtime/_bi_subset.woff.base64.txt';
if (is_file($b64File)) $woffB64 = trim(file_get_contents($b64File));
$sub = ".bi::before,[class*=\" bi-\"]::before,[class^=bi-]::before{display:inline-block;font-family:bootstrap-icons!important;font-style:normal;font-weight:400!important;font-variant:normal;text-transform:none;line-height:1;vertical-align:-.125em;-webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale}\n";
if ($woffB64 !== '') {
    $sub .= "@font-face{font-display:block;font-family:bootstrap-icons;src:url(\"fonts/bootstrap-icons.v" . $ver . ".woff2\") format(\"woff2\"),url(data:font/woff;base64," . $woffB64 . ") format(\"woff\")}\n";
} else {
    $sub .= "@font-face{font-display:block;font-family:bootstrap-icons;src:url(\"fonts/bootstrap-icons.v" . $ver . ".woff2\") format(\"woff2\")}\n";
}
foreach ($wl as $ic) {
    $sub .= ".bi-" . $ic . "::before{content:\"\\" . $map[$ic] . "\"}\n";
}
file_put_contents($ROOT . '/runtime/_icons_subset.css', $sub);

// 5) 输出码点列表（fontTools --unicodes 用）
$uniList = array_map(fn($ic) => 'U+' . strtoupper($map[$ic]), $wl);
$uniStr = implode(',', $uniList);
file_put_contents($ROOT . '/runtime/_icons_unicodes.txt', $uniStr);

// ⚠ 字体子集化必须从「全量字体」执行（勿用 public/static/vendor/ 下已被覆盖的子集字体，否则加不回新增码点）：
//   curl -o runtime/_bi_full.woff2 https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/fonts/bootstrap-icons.woff2
//   python -m fontTools.subset runtime/_bi_full.woff2 --unicodes=$(cat runtime/_icons_unicodes.txt) \
//     --flavor=woff2 --output-file=public/static/vendor/bootstrap-icons/fonts/bootstrap-icons.v{VER}.woff2
//   python - <<'EOF'   # 生成 woff 子集 + base64（双格式回退用，输出 runtime/_bi_subset.woff.base64.txt）
//   from fontTools.ttLib import TTFont
//   import base64, os
//   f = TTFont('public/static/vendor/bootstrap-icons/fonts/bootstrap-icons.v{VER}.woff2'); f.flavor='woff'
//   f.save('runtime/_bi_subset.woff'); print(base64.b64encode(open('runtime/_bi_subset.woff','rb').read()).decode())
//   EOF
//   cp runtime/_icons_subset.css public/static/vendor/bootstrap-icons/bootstrap-icons.v{VER}.min.css
//   （{VER}=config/version.php 版本号；产物文件名版本化，见上方注释，勿改回无版本名）

echo "whitelist=" . count($wl) . ", subset css=" . round(strlen($sub) / 1024, 1) . "KB, unicodes written\n";
echo "下一步：python -m fontTools.subset runtime/_bi_full.woff2 --unicodes=\"" . $uniStr . "\" --flavor=woff2 --output-file=public/static/vendor/bootstrap-icons/fonts/bootstrap-icons.v" . $ver . ".woff2\n";
