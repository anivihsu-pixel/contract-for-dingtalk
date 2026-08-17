<?php
// +----------------------------------------------------------------------
// | 操作审计中心
// +----------------------------------------------------------------------

namespace app\controller;

use app\BaseController;
use app\common\service\AuditService;
use think\facade\View;

class AuditController extends BaseController
{
    /** 审计中心页面 */
    public function index()
    {
        // 审计中心权限收敛至管理员（is_admin=1 或 admin 角色），不再由 audit:view 权限码开放
        $this->requireSuperAdmin();

        // 审计操作类型 / 目标类型中文映射统一复用 common.php 的单一事实来源，
        // 确保审计中心筛选下拉、前端 window._auditActions / window._auditTypes 与
        // 相对方360「最近动态」三处口径一致，杜绝英文原始码外泄。
        $actions = audit_action_labels();
        $types   = audit_type_labels();

        View::assign('actions', $actions);
        View::assign('types', $types);
        return View::fetch();
    }

    /** 审计日志列表（AJAX） */
    public function list()
    {
        $this->requireSuperAdmin();

        [$page, $pageSize] = $this->getPageParams();

        $filter = [];
        $userId = (int)$this->getParam('user_id', 0);
        if ($userId > 0) {
            $filter['user_id'] = $userId;
        }
        $action = $this->getParam('action', '');
        if ($action !== '') {
            $filter['action'] = $action;
        }
        $type = $this->getParam('target_type', '');
        if ($type !== '') {
            $filter['target_type'] = $type;
        }
        $dateStart = $this->getParam('date_start', '');
        if ($dateStart !== '') {
            $filter['date_start'] = $dateStart . ' 00:00:00';
        }

        [$sortField, $sortOrder] = $this->getSortParams([
            'id'         => 'a.id',
            'created_at' => 'a.created_at',
            'user_id'    => 'a.user_id',
        ], 'a.id', 'desc');
        $res = AuditService::getList($page, $pageSize, $filter, [$sortField, $sortOrder]);
        return json_success(['list' => $res['list'], 'count' => $res['total']]);
    }
}
