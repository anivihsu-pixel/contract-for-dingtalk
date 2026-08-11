<?php
// +----------------------------------------------------------------------
// | 控制器基类
// +----------------------------------------------------------------------

namespace app;

use think\facade\Session;
use think\facade\View;
use think\facade\Cache;
use app\common\logic\AuthLogic;

/**
 * @property int   $userId  当前登录用户 ID
 * @property array $user    当前登录用户信息
 */
abstract class BaseController
{
    protected $userId = 0;
    protected $user = [];

    /** v2.44.1 P1：isSuperAdmin 结果属性缓存——视图渲染会多次调用 hasPermission，避免每次查库 */
    private $superAdminCached = null;

    public function __construct()
    {
        $this->userId = Session::get('user_id', 0);
        $this->user   = Session::get('user', []);

        // 注入到模板
        View::assign('user', $this->user);
        View::assign('user_id', $this->userId);
        View::assign('is_admin', $this->user['is_admin'] ?? 0);
        // 注入权限码清单（供视图层按权限隐藏菜单等渲染使用）
        View::assign('user_permissions', Session::get('user_permissions', []));

        // 全局页脚信息：版权信息（系统设置可配置）+ 当前版本号，供 PC 与移动端页脚统一展示
        // v2.34.0：版权信息由系统设置「系统配置」页维护（system_config.copyright），缺失时回退默认文案
        $copyright = sys_config('copyright', '© ' . date('Y') . ' 合同管理系统 版权所有');
        View::assign('site_copyright', $copyright);
        View::assign('app_version', app_version());

        // 全局提醒角标（仅页面渲染时计算，AJAX 请求不计，避免无谓查询）
        if ($this->userId > 0 && !request()->isAjax()) {
            // CR-52：角标计数加 60s 短缓存，避免每次页面渲染重复聚合查询
            $remindKey = 'badge_remind_' . $this->userId;
            $remindCount = Cache::get($remindKey);
            if ($remindCount === null) {
                try {
                    $remindCount = \app\common\service\RemindService::getOutstandingCount(
                        $this->userId, !empty($this->user['is_admin']),
                        $this->hasPermission('payment:view')
                    );
                } catch (\Throwable $e) {
                    $remindCount = 0;
                }
                // 合并站内审批消息未读数：与「今日提醒」共用侧边栏红点，避免两个入口/两个铃铛
                try {
                    $msgUnread = \app\common\service\InternalNotify::unreadCount($this->userId);
                } catch (\Throwable $e) {
                    $msgUnread = 0;
                }
                $remindCount = (int) $remindCount + (int) $msgUnread;
                Cache::set($remindKey, $remindCount, 60);
            }
            View::assign('remind_count', $remindCount);

            // 常用创建权限（供视图按钮灰化，避免无权限者打开表单后提交被拒）
            View::assign('can_create_contract', $this->hasPermission('contract:create'));
            View::assign('can_create_customer', $this->hasPermission('customer:create'));
            View::assign('can_create_supplier', $this->hasPermission('supplier:create'));
            View::assign('can_create_project', $this->hasPermission('project:create'));
            View::assign('can_view_audit', $this->hasPermission('audit:view'));

            // 审批待办红点（同样加短缓存）
            $approvalKey = 'badge_approval_' . $this->userId;
            $approvalPending = Cache::get($approvalKey);
            if ($approvalPending === null) {
                // P3-2【m-A1】计数下沉至 ApprovalLogic，控制器零 Db 直查
                $approvalPending = \app\common\logic\ApprovalQueryService::getPendingCountForUser($this->userId);
                Cache::set($approvalKey, $approvalPending, 60);
            }
            View::assign('approval_pending', $approvalPending);
        } else {
            View::assign('remind_count', 0);
            View::assign('can_create_contract', false);
            View::assign('can_create_customer', false);
            View::assign('can_create_supplier', false);
            View::assign('can_create_project', false);
            View::assign('can_view_audit', false);
            View::assign('approval_pending', 0);
        }
    }

    /** 检查权限码 */
    protected function hasPermission(string $code): bool
    {
        // v2.44.1 P1：语义对齐——拥有 admin 角色的用户（isSuperAdmin）视为拥有全部权限，
        // 否则 admin 角色权限被部分勾选后会出现「isSuperAdmin() 放行但 requirePermission() 拒绝」的分裂。
        if ($this->isSuperAdmin()) return true;
        if (!empty($this->user['is_admin'])) return true;
        $perms = Session::get('user_permissions', []);
        return in_array($code, $perms);
    }

    /** 当前用户是否为超级管理员（v2.40.5）
     *  is_admin=1 或 拥有「超级管理员」角色（code='admin'）——钉钉真实部署 is_admin=0 + admin 角色同效 */
    protected function isSuperAdmin(): bool
    {
        if ($this->superAdminCached === null) {
            $this->superAdminCached = AuthLogic::isSuperAdmin($this->userId, $this->user);
        }
        return $this->superAdminCached;
    }

    /**
     * 要求权限，无权限则拒绝
     * - AJAX / POST 请求返回 JSON 403（便于前端统一拦截提示）
     * - 普通页面请求抛出 403 异常（由框架渲染错误页）
     */
    protected function requirePermission(string $code): void
    {
        if ($this->hasPermission($code)) {
            return;
        }
        $this->deny();
    }

    /** 要求满足任一权限码，否则拒绝 */
    protected function requireAnyPermission(array $codes): void
    {
        foreach ($codes as $code) {
            if ($this->hasPermission($code)) {
                return;
            }
        }
        $this->deny();
    }

    /**
     * 财务/报表统一权限门面（v2.38.1）：PC与移动端共享同一权限门槛，
     * 避免 report:view 类权限码不存在导致的跨端不一致。
     * 拥有回款查看或发票查看权限任一即可进入财务/报表模块。
     */
    protected function financialGate(): void
    {
        $this->requireAnyPermission(['payment:view', 'invoice:view']);
    }

    /** 无权限时的拒绝响应 */
    protected function deny(): void
    {
        if (request()->isAjax() || request()->isPost()) {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
                http_response_code(403);
            }
            echo json_encode([
                'code' => 403,
                'msg'  => '权限不足，请联系管理员',
                'data' => null,
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        // CR-47：渲染友好 403 页（含返回导航与联系管理员提示），替代框架默认错误页
        $isMobile = is_mobile_request();
        View::assign('back_url', $isMobile ? '/m' : '/dashboard');
        View::assign('home_text', $isMobile ? '返回工作台' : '返回驾驶舱');
        $html = View::fetch('error/403');
        if (!headers_sent()) {
            http_response_code(403);
        }
        echo $html;
        exit;
    }

    /** 获取 POST 参数（支持 ThinkPHP name/type 语法，如 'tables/a' 强制数组） */
    protected function getPost(string $key = '', $default = null)
    {
        if ($key === '') return request()->post();
        return request()->post($key, $default);
    }

    /** 获取 GET 参数（支持 ThinkPHP name/type 语法，如 'tables/a' 强制数组） */
    protected function getParam(string $key = '', $default = null)
    {
        if ($key === '') return request()->param();
        return request()->param($key, $default);
    }

    /** 分页参数 */
    protected function getPageParams(): array
    {
        $page     = max(1, (int)$this->getParam('page', 1));
        $pageSize = min(100, max(1, (int)$this->getParam('limit', 15)));
        return [$page, $pageSize];
    }

    /**
     * 排序参数白名单校验（建议2：列表排序/搜索参数边界）
     * 仅允许 $allowedFields 中声明的「前端字段名 => 数据库排序表达式」，
     * 排序方向仅允许 asc/desc，其余一律安全回退默认值，杜绝排序字段名注入。
     *
     * @param array  $allowedFields ['frontend_key' => 'db_expr']
     * @param string $defaultField  默认数据库排序表达式
     * @param string $defaultOrder  'desc' | 'asc'
     * @return array [db_expr, order]
     */
    protected function getSortParams(array $allowedFields, string $defaultField, string $defaultOrder = 'desc'): array
    {
        $order = strtolower((string)$this->getParam('order', $defaultOrder));
        if (!in_array($order, ['asc', 'desc'], true)) {
            $order = $defaultOrder;
        }
        $key = $this->getParam('sort', '');
        if ($key === '' || !isset($allowedFields[$key])) {
            return [$defaultField, $order];
        }
        return [$allowedFields[$key], $order];
    }
}
