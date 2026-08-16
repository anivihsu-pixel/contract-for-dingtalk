<?php
// +----------------------------------------------------------------------
// | 钉钉控制器 — SSO 登入 / JSAPI 配置 / 组织同步
// +----------------------------------------------------------------------

namespace app\controller;

use app\BaseController;
use app\common\logic\DingTalkLogic;
use app\common\service\dd\DingTalkMock;
use think\facade\View;
use think\facade\Log;
use think\facade\Session;

class DingTalkController extends BaseController
{
    /**
     * 钉钉免登 (AJAX)
     * POST /dingtalk/sso-login
     */
    public function ssoLogin()
    {
        $code  = request()->post('code', '');
        $state = request()->post('state', '');

        if (empty($code)) {
            return json_error('缺少授权码');
        }
        // P1（2026-08-09）：OAuth state 校验——必须与 entry 页签发的一次性 nonce 一致且消费后即失效，
        // 防止 code 交换被跨站伪造；无 state（旧客户端/直接调用）一律拒绝。
        $expected = (string)Session::get('dingtalk_oauth_state', '');
        Session::delete('dingtalk_oauth_state');   // 一次性：无论成败均消费
        if ($expected === '' || $state === '' || !hash_equals($expected, $state)) {
            Log::warning('钉钉免登 state 校验失败（疑似 CSRF/过期）', ['ip' => request()->ip()]);
            return json_error('登录状态校验失败，请重新打开链接登录', 403);
        }

        try {
            $result = DingTalkLogic::ssoLogin($code);
            return json_success($result, '登录成功');
        } catch (\Throwable $e) {
            // REV-14：免登异常写入日志，对外仅返回友好提示（避免钉钉内部错误细节泄露）
            Log::error('钉钉免登失败', ['error' => $e->getMessage()]);
            return json_error('钉钉登录失败，请稍后重试');
        }
    }

    /**
     * JSAPI 配置签名 (AJAX)
     * GET /dingtalk/jsapi-config
     */
    public function jsapiConfig()
    {
        $url = request()->param('url', '');
        if (empty($url)) {
            $url = request()->server('HTTP_REFERER', '');
        }
        // 匿名接口只允许为当前系统配置域名签名，防止第三方站点借用企业应用的 JSAPI ticket。
        // 生产以 DINGTALK_APP_URL 为信任根；未配置时仅开发环境回退当前请求域名。
        $appUrl = (string)config('dingtalk.app_url');
        if ($appUrl === '' && !empty(config('app.debug'))) {
            $appUrl = (string)(request()->domain() ?? '');
        }
        if (!self::isAllowedJsapiUrl((string)$url, $appUrl)) {
            Log::warning('拒绝为非本系统来源签发钉钉 JSAPI 配置', [
                'url' => (string)$url,
                'ip'  => request()->ip(),
            ]);
            return json_error('签名网址不属于当前系统', 403);
        }
        // P2-10【M-A4】包裹 try/catch：钉钉接口抖动时降级返回，避免直 500（前端对 res.data 缺失已做容错）
        try {
            $config = DingTalkLogic::getJsApiConfig($url);
            return json_success($config);
        } catch (\Throwable $e) {
            Log::error('钉钉 JSAPI 配置获取失败', ['url' => $url, 'error' => $e->getMessage()]);
            return json_error('钉钉配置获取失败，请刷新重试');
        }
    }

    /** URL 与配置应用地址必须具有完全相同的 scheme/host/port，且不得携带用户凭据。 */
    public static function isAllowedJsapiUrl(string $url, string $appUrl): bool
    {
        $target = parse_url($url);
        $allowed = parse_url($appUrl);
        if (!is_array($target) || !is_array($allowed)) return false;
        if (!empty($target['user']) || !empty($target['pass'])) return false;
        foreach (['scheme', 'host'] as $key) {
            if (empty($target[$key]) || empty($allowed[$key])) return false;
            if (strtolower((string)$target[$key]) !== strtolower((string)$allowed[$key])) return false;
        }
        $port = static function (array $parts): int {
            if (isset($parts['port'])) return (int)$parts['port'];
            return strtolower((string)($parts['scheme'] ?? '')) === 'https' ? 443 : 80;
        };
        return $port($target) === $port($allowed);
    }

    /**
     * 同步钉钉组织架构 (AJAX)
     * POST /ajax/dingtalk/sync-org
     */
    public function syncOrg()
    {
        $this->requirePermission('dingtalk:sync');
        try {
            $result = DingTalkLogic::syncOrganization();
            return json_success($result, '同步完成');
        } catch (\Throwable $e) {
            // REV-14：同步异常写入日志，对外仅返回友好提示
            Log::error('钉钉组织同步失败', ['error' => $e->getMessage()]);
            return json_error('同步失败，请查看日志或联系管理员');
        }
    }

    /**
     * Mock 模式调用日志
     * GET /ajax/dingtalk/mock-logs
     */
    public function mockLogs()
    {
        $this->requirePermission('dingtalk:sync');
        if (!config('dingtalk.mock_mode')) {
            return json_error('非 Mock 模式');
        }
        return json_success(DingTalkMock::getLogs());
    }

    /**
     * 钉钉免登入口 — 供审批等消息点击后进入系统
     * GET /dingtalk/entry?to=/approval/{id}
     */
    public function entry()
    {
        $to = request()->get('to', '/dashboard');
        // 仅允许站内相对路径：以单斜杠开头且第二个字符非斜杠/反斜杠，
        // 禁止协议相对(//evil.com)与反斜杠(\evil.com)逃逸造成的开放重定向
        if (!preg_match('#^/[^/\\\\]#', $to)) {
            $to = '/dashboard';
        }
        // 移动端（手机钉钉/手机浏览器）访问审批深链时，落地移动端详情页，避免 PC 布局在手机上错位、
        // 抄送人误显审批按钮等问题。
        // 修复（v2.37.5）：此前已登录用户被下方 redirect() 直接跳 PC 页，绕过了视图内（dingtalk/entry.php）
        // 的 UA 判断，导致手机钉钉已登录用户点消息打开的是 PC 审批页。此处前置重写，登录/免登两条路径统一生效。
        if (is_mobile_request() && preg_match('#^/approval/\d+$#', $to)) {
            $to = '/m' . $to;
        }
        // 已登录：直接跳转目标页
        if ($this->userId > 0) {
            return redirect($to);
        }
        // 未登录：渲染免登引导页（钉钉内自动免登，浏览器内走普通登录并透传深链）
        // P1（2026-08-09）：OAuth state 防护——生成一次性 nonce 存会话并注入页面，
        // ssoLogin 回传校验，防止 code 交换被跨站请求伪造（纵深：钉钉 WebView 内 code 与受害者绑定风险低，但合规要求）。
        $oauthState = bin2hex(random_bytes(16));
        Session::set('dingtalk_oauth_state', $oauthState);
        View::assign('to', $to);
        View::assign('oauth_state', $oauthState);
        return View::fetch('dingtalk/entry');
    }

    /**
     * 保存钉钉应用配置（写入 .env）
     * POST /ajax/dingtalk/save-config
     */
    public function saveConfig()
    {
        $this->requirePermission('dingtalk:sync');

        $map = [
            'app_key'    => $this->getPost('app_key', ''),
            'corp_id'    => $this->getPost('corp_id', ''),
            'agent_id'   => $this->getPost('agent_id', ''),
            'app_url'    => $this->getPost('app_url', ''),
        ];
        // P1-3【M-S1】AppSecret 安全：视图不回显明文；用户留空表示不修改，
        // 故仅当提交非空密钥时才纳入写入，避免空值覆盖已保存的真实密钥。
        $appSecret = $this->getPost('app_secret', '');
        if ($appSecret !== '') {
            $map['app_secret'] = $appSecret;
        }
        $mock = (string)$this->getPost('mock_mode', '1');
        $map['mock_mode'] = in_array($mock, ['1', 'true'], true) ? 'true' : 'false';

        if ($this->writeEnv($map)) {
            return json_success(null, '配置已保存（已写入 .env）');
        }
        return json_error('配置保存失败，请检查 .env 文件可写权限');
    }

    /**
     * 将钉钉配置写入 .env（按 KEY=VALUE 更新或追加）
     */
    private function writeEnv(array $map): bool
    {
        $file = root_path() . '.env';
        if (!is_file($file)) {
            return false;
        }
        $content = file_get_contents($file);
        $lines = explode("\n", $content);
        $found = [];
        foreach ($lines as &$ln) {
            foreach ($map as $k => $v) {
                $envKey = 'DINGTALK_' . strtoupper($k);
                if (strpos($ln, $envKey . '=') === 0) {
                    $ln = $envKey . '=' . $this->envQuote($v);
                    $found[$k] = true;
                    break;
                }
            }
        }
        foreach ($map as $k => $v) {
            if (empty($found[$k])) {
                $lines[] = 'DINGTALK_' . strtoupper($k) . '=' . $this->envQuote($v);
            }
        }
        $content = implode("\n", $lines);
        return file_put_contents($file, $content) !== false;
    }

    /**
     * m17：.env 值安全转义
     * - 先去除换行、回车、Tab 及 ASCII 控制字符，防止写入被拆成多行或注入新配置行；
     * - 若值含空格/#/引号/反斜杠/等号等可能影响 KEY=VALUE 单值解析的字符，则以双引号包裹并转义内部引号。
     *   等号(=)置于双引号内由 Dotenv 按单值解析，避免被误判为额外的键值对。
     */
    private function envQuote(string $v): string
    {
        // 去除换行、回车、Tab 与 ASCII 控制字符（0x00-0x1F 及 0x7F）
        $v = (string) preg_replace('/[\r\n\t\x00-\x1F\x7F]/', '', $v);
        if (preg_match('/[\s#"\'\\\\=]/', $v)) {
            return '"' . str_replace('"', '\\"', $v) . '"';
        }
        return $v;
    }
}
