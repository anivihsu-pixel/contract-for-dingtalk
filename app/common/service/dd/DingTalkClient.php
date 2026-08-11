<?php
// +----------------------------------------------------------------------
// | 钉钉 API HTTP 客户端
// +----------------------------------------------------------------------

namespace app\common\service\dd;

class DingTalkClient
{
    protected string $baseUrl;
    protected int    $timeout;

    public function __construct()
    {
        $this->baseUrl = config('dingtalk.base_url', 'https://oapi.dingtalk.com');
        $this->timeout = config('dingtalk.timeout', 10);
    }

    /**
     * 获取 access_token (缓存 7000s)
     */
    public function getAccessToken(): string
    {
        $cache = cache('dingtalk_access_token');
        if ($cache) return $cache;

        $appKey    = config('dingtalk.app_key');
        $appSecret = config('dingtalk.app_secret');

        $url  = $this->baseUrl . '/gettoken?appkey=' . $appKey . '&appsecret=' . $appSecret;
        $resp = $this->httpGet($url);

        $token = $resp['access_token'] ?? '';
        if ($token) {
            cache('dingtalk_access_token', $token, 7000);
        }

        return $token;
    }

    /**
     * GET 请求
     * @param string $path    API 路径 (如 /user/get)
     * @param array  $params  query 参数
     * @return array
     */
    public function get(string $path, array $params = []): array
    {
        $url = $this->baseUrl . $path;
        if ($params) {
            $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($params);
        }
        return $this->httpGet($url);
    }

    /**
     * POST 请求
     * @param string $url  完整 URL 或 path
     * @param array  $data POST body
     * @return array
     */
    public function post(string $url, array $data = []): array
    {
        if (strpos($url, 'http') !== 0) {
            $url = $this->baseUrl . $url;
        }
        return $this->httpPost($url, $data);
    }

    protected function httpGet(string $url): array
    {
        $ch = curl_init();
        curl_setopt_array($ch, $this->sslOpts() + [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
        ]);
        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        curl_close($ch);
        if ($errno !== 0) {
            // REV-03：TLS 校验失败时明确报错，避免静默返回空数组被误判为正常响应
            \think\facade\Log::error('钉钉 HTTP GET 失败', ['url' => $url, 'errno' => $errno]);
        }
        return json_decode($body, true) ?: [];
    }

    protected function httpPost(string $url, array $data = []): array
    {
        $ch = curl_init();
        curl_setopt_array($ch, $this->sslOpts() + [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($data, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
        ]);
        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        curl_close($ch);
        if ($errno !== 0) {
            \think\facade\Log::error('钉钉 HTTP POST 失败', ['url' => $url, 'errno' => $errno]);
        }
        return json_decode($body, true) ?: [];
    }

    /**
     * REV-03：TLS 校验选项。
     * 默认开启证书链校验（CURLOPT_SSL_VERIFYPEER=true / VERIFYHOST=2），杜绝中间人伪造钉钉响应冒充员工；
     * 仅当 config('dingtalk.ssl_verify') 显式置为 false（本地自签名调试）时才关闭。
     * 若配置了 dingtalk.cafile 则使用该 CA 包，否则使用 PHP/curl 内置默认 CA 库。
     * @return array
     */
    protected function sslOpts(): array
    {
        $verify = config('dingtalk.ssl_verify', true);
        if (!$verify) {
            \think\facade\Log::warning('[安全·REV-03] 钉钉 TLS 证书校验已关闭（dingtalk.ssl_verify=false），仅允许本地调试使用');
            return [
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0,
            ];
        }
        $opts = [
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ];
        $caFile = config('dingtalk.cafile', '');
        if ($caFile && is_file($caFile)) {
            $opts[CURLOPT_CAINFO] = $caFile;
        }
        return $opts;
    }
}
