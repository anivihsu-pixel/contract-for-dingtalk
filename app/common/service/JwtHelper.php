<?php
// +----------------------------------------------------------------------
// | JWT 工具类 (HS256) — 替代 firebase/php-jwt
// +----------------------------------------------------------------------

namespace app\common\service;

class JwtHelper
{
    /**
     * 签发 JWT Token
     * @param array $payload  payload 数据 (自动添加 iat/exp)
     * @param string $secret  签名密钥
     * @param int    $ttl     有效期(秒)，默认 86400 (24h)
     * @return string
     */
    public static function encode(array $payload, string $secret, int $ttl = 86400): string
    {
        $header = ['alg' => 'HS256', 'typ' => 'JWT'];

        $payload['iat'] = time();
        $payload['exp'] = time() + $ttl;

        $segments = [];
        $segments[] = self::base64UrlEncode(json_encode($header, JSON_UNESCAPED_UNICODE));
        $segments[] = self::base64UrlEncode(json_encode($payload, JSON_UNESCAPED_UNICODE));

        $signingInput = implode('.', $segments);
        $signature = hash_hmac('sha256', $signingInput, $secret, true);
        $segments[] = self::base64UrlEncode($signature);

        return implode('.', $segments);
    }

    /**
     * 解码并验证 JWT Token
     * @param string $token
     * @param string $secret
     * @return array|null  验证成功返回 payload，失败返回 null
     */
    public static function decode(string $token, string $secret): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$headerB64, $payloadB64, $signatureB64] = $parts;

        // 验证签名
        $signingInput = $headerB64 . '.' . $payloadB64;
        $signature = self::base64UrlDecode($signatureB64);
        $expected = hash_hmac('sha256', $signingInput, $secret, true);

        if (!hash_equals($expected, $signature)) {
            return null;
        }

        $payload = json_decode(self::base64UrlDecode($payloadB64), true);
        if (!$payload) {
            return null;
        }

        // 检查过期
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return null;
        }

        return $payload;
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
