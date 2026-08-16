<?php
// +----------------------------------------------------------------------
// | 登录认证中间件 — JWT + Session 双通道
// +----------------------------------------------------------------------

namespace app\middleware;

use think\facade\Db;
use think\facade\Session;
use think\facade\Cache;
use app\common\service\JwtHelper;

class Auth
{
    /** 需跳过认证的 URL 路径前缀（仅真正免认证的端点；其余统一经 Auth 后由权限守门，详见 REV-38） */
    protected array $except = [
        'login',
        'logout',
        'm/login',
        'dingtalk/sso-login',   // 钉钉免登入口（匿名换取身份）
        'dingtalk/jsapi-config',// JSAPI 签名（前端页面拉取，匿名）
        'dingtalk/entry',       // 免登回调落地页（匿名重定向）
        'health',               // 负载均衡健康探针（仅返回通用状态，不泄露检查详情）
    ];

    public function handle($request, \Closure $next)
    {
        $path = $request->pathinfo();

        // 跳过免认证路径（N-m3【安全·精确边界】豁免匹配收紧为「完全相等」或「prefix + '/' 的下级路径」，
        // 取代原 strpos===0 前缀匹配——后者会把 'dingtalk/sso-login-evil'、'loginXxx' 等相似路由误豁免。
        // 例：prefix='dingtalk/sso-login' 仅豁免自身或 'dingtalk/sso-login/...'，不再命中 '...-evil'。）
        foreach ($this->except as $prefix) {
            if ($path === $prefix || strpos($path, $prefix . '/') === 0) {
                return $next($request);
            }
        }

        // 附件预览签名令牌（#3 修复）：移动端文档预览被 WebView 甩到外部浏览器/查看器时，
        // 顶层导航无法携带 JWT 头、外部上下文也无会话 Cookie；以路径绑定的短期令牌免会话放行，
        // 避免外部浏览器要求重新登录合同管理。PreviewController 将再次校验令牌（纵深防御）。
        // v2.43.5 补丁①：/m/doc-preview（预览页）同样接受预览令牌——外部浏览器打开预览页时
        // 页面本身也能免登录（页面不含业务数据，数据经 /preview 流再次鉴权）。
        // v2.43.5 补丁②：令牌改为「顶层 p/t 参数」传递——f 嵌套参数（?f=/preview?p=...&t=...）
        // 在部分环境（钉钉甩外部浏览器时的 URL 规范化/二次解码）会把 f 内的 ?p&t 拆散导致令牌丢失
        // → 跳登录；顶层参数无嵌套无双重编码，最鲁棒。旧 f 参数链接保留解析兼容。
        // v2.43.6：/m/doc-preview 升级为 /m/office-preview（通用文档预览页，支持 pdf/docx/xlsx）。
        if ($path === 'preview' || $path === 'm/office-preview') {
            $pt = $request->param('t', '');
            $pp = $request->param('p', '');
            if ($path === 'm/office-preview' && ($pp === '' || $pt === '')) {
                // 兼容旧格式：f=/preview?p=...&t=...
                $f = $request->param('f', '');
                if ($f !== '' && strpos($f, '/preview') === 0) {
                    $qp = [];
                    parse_str((string)parse_url($f, PHP_URL_QUERY), $qp);
                    $pp = (string)($qp['p'] ?? '');
                    $pt = (string)($qp['t'] ?? '');
                }
            }
            if ($pt !== '' && function_exists('validate_preview_token') && validate_preview_token($pp, $pt)) {
                return $next($request);
            }
        }

        // 通道1: JWT Bearer Token (钉钉 WebView)
        $authHeader = $request->header('Authorization', '');
        if ($authHeader && strpos($authHeader, 'Bearer ') === 0) {
            $token = substr($authHeader, 7);
            // 与签发侧共用同一密钥解析入口，确保验证密钥一致（CR-33 安全修复）
            $secret = \app\common\logic\AuthLogic::jwtSecret();
            $payload = JwtHelper::decode($token, $secret);

            if ($payload && !empty($payload['user_id'])) {
                // CR-44：dingtalk_session 查找加 60s 短缓存，降低高频 JWT 请求下的 DB 压力；
                // 仅缓存命中结果，未命中（无效/过期/已吊销）不缓存，保证会话吊销即时生效。
                $cacheKey = 'jwt_session_' . $token;
                $session = Cache::get($cacheKey);
                if ($session === null) {
                    $session = Db::name('dingtalk_session')
                        ->where('token', $token)
                        ->where('expires_at', '>', date('Y-m-d H:i:s'))
                        ->find();
                    if ($session) {
                        Cache::set($cacheKey, $session, 60);
                    }
                }

                if ($session) {
                    // P2-12【S-A5】令牌归属绑定：JWT payload 的 user_id 必须与会话记录绑定的 user_id 一致，
                    // 防止「有效 token 字符串 + 篡改 payload」换身份（纵深防御：token 本身已受签名保护，此处再校一遍）
                    if ((int)$session['user_id'] !== (int)$payload['user_id']) {
                        return json(['code' => 401, 'msg' => '登录态异常，请重新登录', 'data' => null], 401);
                    }
                    $user = Db::name('user')->find($payload['user_id']);
                    // P1（2026-08-09）：禁用/锁定用户实时吊销——离职交接场景下
                    // 管理员禁用（status=2）或锁定（status=0）后既有 JWT 立即失效，而非等自然过期。
                    if ($user && (int)$user['status'] !== 1) {
                        Db::name('dingtalk_session')->where('token', $token)->delete();
                        Cache::delete($cacheKey);
                        return json(['code' => 401, 'msg' => '账号已被禁用或锁定，请联系管理员', 'data' => null], 401);
                    }
                if ($user) {
                    $this->setSession($user);
                    // RV-01：JWT 通道每次重算权限；再比对 perm_version，确保角色/权限变更后即时对齐
                    \app\common\logic\AuthLogic::refreshSessionPermissionsIfStale();
                    $fr = $this->checkForceReset($request);
                    if ($fr !== null) return $fr;
                    $rootTarget = $this->authenticatedRootTarget($request);
                    if ($rootTarget !== null) return redirect($rootTarget);
                    return $next($request);
                }
                }
            }
        }

        // 通道2: Cookie Session
        if (Session::has('user_id')) {
            // P1（2026-08-09）：Cookie 通道同样实时校验用户状态——禁用/锁定后既有会话立即吊销。
            // 查库结果带 30s 短缓存，避免高频页面每请求都打 DB；吊销延迟上限 30s（可接受）。
            $cookieUid = (int)Session::get('user_id');
            $statusKey = 'user_status_' . $cookieUid;
            $status    = Cache::get($statusKey);
            if ($status === null) {
                $status = (int)Db::name('user')->where('id', $cookieUid)->value('status');
                Cache::set($statusKey, $status, 30);
            }
            // 注意：ThinkPHP File 缓存对数字值字符串化（serialize 里 is_numeric → (string)$data），
            // Cache::get 返回的是 string '1' 而非 int 1，必须 (int) 强转后比较，否则 '1' !== 1 恒真误判禁用
            if ((int)$status !== 1) {
                \app\common\logic\AuthLogic::logout();
                Cache::delete($statusKey);
                if ($request->isAjax() || $request->isPost()) {
                    return json(['code' => 401, 'msg' => '账号已被禁用或锁定，请联系管理员', 'data' => null], 401);
                }
                return redirect(is_mobile_request() ? '/m/login' : '/login');
            }
            // RV-01：Cookie 通道登录时只固化一次权限，此处比对 perm_version 自动刷新，避免改角色后不生效
            \app\common\logic\AuthLogic::refreshSessionPermissionsIfStale();
            $fr = $this->checkForceReset($request);
            if ($fr !== null) return $fr;
            $rootTarget = $this->authenticatedRootTarget($request);
            if ($rootTarget !== null) return redirect($rootTarget);
            return $next($request);
        }

        // 未登录
        if ($request->isAjax() || $request->isPost()) {
            // 注意：json() 第二参数为 HTTP 状态码，缺省为 200；
            // 匿名写/异步请求必须返回真正的 401，避免客户端按 HTTP 200 误判为成功。
            return json(['code' => 401, 'msg' => '未登录', 'data' => null], 401);
        }
        // 未登录：按设备分流登录入口，并携带原始请求地址以便登录后回跳（CR-43）
        $loginUrl = is_mobile_request() ? '/m/login' : '/login';
        $target   = '/' . ltrim($request->pathinfo(), '/');
        $qs = $this->buildRedirectQueryString($request);
        if ($qs !== '') {
            $target .= '?' . $qs;
        }
        return redirect($loginUrl . '?redirect=' . urlencode($target));
    }

    /**
     * 构造登录回跳查询串，并剔除 Web 服务器传给 ThinkPHP 的内部 PATH_INFO 参数。
     * Nginx 常使用 /index.php?s=$uri 重写；s 只用于路由解析，不属于用户 URL。
     */
    protected function buildRedirectQueryString($request): string
    {
        $query = $request->query();
        if (!is_array($query)) {
            $parsed = [];
            parse_str((string)$query, $parsed);
            $query = $parsed;
        }
        unset($query['s']);
        return http_build_query($query);
    }

    /**
     * ThinkPHP 8 强制路由模式不会匹配空 pathinfo 的根地址。
     * 根地址仅作为登录后的入口别名，在认证和账号状态校验完成后转到实际首页。
     */
    protected function authenticatedRootTarget($request): ?string
    {
        if ($request->pathinfo() !== '') {
            return null;
        }
        return is_mobile_request() ? '/m' : '/dashboard';
    }

    /**
     * 强制改密守卫：force_reset=1 的用户除改密页/接口及免认证路径外，其余请求一律拦截
     * @return \think\Response|null 返回 Response 表示拦截，null 表示放行
     */
    private function checkForceReset($request)
    {
        $user = Session::get('user', []);
        if (empty($user['force_reset'])) {
            return null;
        }
        $path = $request->pathinfo();
        // 允许访问：改密页、改密接口
        if ($path === 'profile/change-password' || $path === 'ajax/admin/change-password') {
            return null;
        }
        if ($request->isAjax() || $request->isPost()) {
            // 用户已登录但被强制改密：HTTP 语义为 403（已认证但操作被禁止），
            // 业务码保留 430 以便前端区分「未登录(401)」与「需改密(430)」。
            return json(['code' => 430, 'msg' => '请先修改密码后再操作', 'data' => null], 403);
        }
        return redirect('/profile/change-password');
    }

    private function setSession(array $user): void
    {
        Session::set('user_id', $user['id']);
        Session::set('user', $user);

        // 加载权限：复用 AuthLogic::getUserPermissions——角色权限 ∪ 全员默认基础权限（is_admin 全量短路），
        // 与 Cookie 通道（loadSession）保持双通道语义一致，修复钉钉部署（is_admin=0 + Bearer JWT）缺基础权限被 403 的问题
        $perms = \app\common\logic\AuthLogic::getUserPermissions((int)$user['id']);
        Session::set('user_permissions', $perms);

        // m13：JWT 通道解析后装载 data_scope / data_visibility 到会话，与 Cookie 登录通道（AuthLogic::loadSession）保持一致；
        // 复用公共方法 AuthLogic::dataScope()/visibility()（内部优先读会话缓存），
        // 避免后续 appendDataScope / canAccessRecord 每请求回退 computeVisibility() 重复查库，也保证双通道数据范围语义一致。
        $user['data_scope'] = \app\common\logic\AuthLogic::dataScope();
        $user['data_visibility'] = \app\common\logic\AuthLogic::visibility();
        Session::set('user', $user);
    }
}
