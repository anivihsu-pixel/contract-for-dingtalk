<?php
// +----------------------------------------------------------------------
// | 认证控制器 — 登录/退出
// +----------------------------------------------------------------------

namespace app\controller;

use think\facade\View;
use app\BaseController;
use app\common\logic\AuthLogic;

class AuthController extends BaseController
{
    /**
     * 登录页面
     */
    public function index()
    {
        if ($this->userId) {
            return redirect(is_mobile_request() ? '/m' : '/dashboard');
        }
        return View::fetch('auth/login');
    }

    /**
     * 账号密码登录 (AJAX)
     * S-06：登录爆破防护——同一用户名连续失败 5 次锁定 15 分钟（文件缓存，登录成功清零）。
     */
    public function login()
    {
        $username = $this->getPost('username', '');
        $password = $this->getPost('password', '');

        if (empty($username) || empty($password)) {
            return json_error('请输入用户名和密码');
        }

        // S-06：失败计数与锁定（键=账号+IP，15 分钟窗口；v2.44.1 P1：原仅按账号可被用于对任意账号的 DoS，
        // 攻击者提交 5 次错口令即可冻结目标登录；改账号+IP 后仅冻结该来源，不影响其他 IP 正常登录）
        $ip       = (string)request()->ip();
        $failKey  = 'login_fail_' . md5(strtolower(trim($username))) . '_' . md5($ip);
        $failCnt  = (int)\think\facade\Cache::get($failKey, 0);
        if ($failCnt >= 5) {
            return json_error('登录失败次数过多，该账号在当前网络已被临时锁定，请 15 分钟后再试');
        }

        $user = AuthLogic::login($username, $password);
        if (!$user) {
            $failCnt++;
            if ($failCnt >= 5) {
                \think\facade\Cache::set($failKey, $failCnt, 900);        // 900s = 15 分钟锁定
                \think\facade\Cache::set($failKey . '_locked_at', time(), 900);
                return json_error('登录失败次数过多，该账号在当前网络已被临时锁定，请 15 分钟后再试');
            }
            \think\facade\Cache::set($failKey, $failCnt, 900);
            return json_error('用户名或密码错误（剩余尝试 ' . (5 - $failCnt) . ' 次）');
        }
        // 登录成功：清零失败计数
        \think\facade\Cache::delete($failKey);
        \think\facade\Cache::delete($failKey . '_locked_at');

        // force_reset=1 表示需强制改密（首次部署 / 管理员重置）
        $forceReset = !empty($user['force_reset']);
        // CR-43：回跳目标取自登录页传入的 ?redirect=，经安全校验仅允许站内相对路径，杜绝开放重定向；
        // 未传或非法时回退设备默认首页（移动端 /m，桌面端 /dashboard）。
        $redirect = safe_redirect_url(
            $this->getParam('redirect', ''),
            is_mobile_request() ? '/m' : '/dashboard'
        );
        return json_success([
            'redirect'    => $redirect,
            'force_reset' => $forceReset,
        ], $forceReset ? '登录成功，请先修改初始密码' : '登录成功');
    }

    /**
     * 退出登录
     */
    public function logout()
    {
        AuthLogic::logout();
        return redirect('/login');
    }
}
