<?php
/**
 * 生成随机强密钥并写入 .env（部署到生产前运行）
 *
 *   php database/generate_secrets.php
 *
 * 目的：避免随仓库分发的弱密钥（APP_KEY / JWT_SECRET 明文）被用于生产环境。
 * 脚本会原地替换 .env 中的 APP_KEY 与 JWT_SECRET 为密码学随机值。
 */

$root = dirname(__DIR__);
$envPath = $root . '/.env';

if (!is_file($envPath)) {
    fwrite(STDERR, ".env 不存在：{$envPath}\n");
    exit(1);
}

$env = file_get_contents($envPath);

$appKey    = bin2hex(random_bytes(24)); // 48 hex chars
$jwtSecret = bin2hex(random_bytes(32)); // 64 hex chars

$env = preg_replace('/^APP_KEY=.*$/m',  'APP_KEY='   . $appKey,    $env);
$env = preg_replace('/^JWT_SECRET=.*$/m', 'JWT_SECRET=' . $jwtSecret, $env);

if (false === file_put_contents($envPath, $env)) {
    fwrite(STDERR, "写入失败：{$envPath}\n");
    exit(1);
}

echo "已生成并写入随机密钥：\n";
echo "APP_KEY    = {$appKey}\n";
echo "JWT_SECRET = {$jwtSecret}\n";
echo "请妥善保管 .env，切勿再次提交到代码仓库。\n";
