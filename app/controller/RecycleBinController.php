<?php
// +----------------------------------------------------------------------
// | 数据回收站控制器（合同 / 客户 / 供应商 软删除记录的恢复与彻底删除）
// +----------------------------------------------------------------------

namespace app\controller;

use app\BaseController;
use app\common\logic\RecycleBinLogic;
use think\facade\View;

/**
 * 仅超级管理员可访问：恢复/彻底删除属敏感操作，防越权恢复或销毁他人数据。
 * v2.40.5：判定兼容钉钉真实部署（is_admin=0 + 超级管理员角色 code='admin' 同效）。
 */
class RecycleBinController extends BaseController
{
    /** 回收站页面 */
    public function index()
    {
        if (!$this->isSuperAdmin()) {
            return $this->deny();
        }
        $type = input('type', 'contract');
        if (!RecycleBinLogic::isValidType($type)) {
            $type = 'contract';
        }
        View::assign('type', $type);
        View::assign('types', RecycleBinLogic::TYPES);
        // 显式指定模板：控制器名 RecycleBin 会被框架默认解析为视图目录 recycle_bin/，
        // 但本模块视图目录约定为 recycle/（与路由 /recycle 一致），故显式 fetch 避免「模板文件不存在」。
        return View::fetch('recycle/index');
    }

    /** AJAX：列表（按类型筛 is_deleted=1） */
    public function list()
    {
        if (!$this->isSuperAdmin()) {
            return $this->deny();
        }
        $type     = input('type', 'contract');
        $page     = max(1, (int)input('page', 1));
        $pageSize = min(50, max(1, (int)input('page_size', 10)));
        $keyword  = trim((string)input('keyword', ''));
        if (!RecycleBinLogic::isValidType($type)) {
            return json_error('未知回收站类型');
        }
        $data = RecycleBinLogic::listDeleted($type, $page, $pageSize, $keyword);
        return json_success($data);
    }

    /** AJAX：恢复（is_deleted=0） */
    public function restore()
    {
        if (!$this->isSuperAdmin()) {
            return $this->deny();
        }
        $type = input('type', '');
        $id   = (int)input('id', 0);
        if (!RecycleBinLogic::isValidType($type) || !$id) {
            return json_error('参数错误');
        }
        $ok = RecycleBinLogic::restore($type, $id);
        // v2.44.1 P1：恢复属敏感权限操作，补审计留痕
        if ($ok) {
            \app\common\service\AuditService::log($this->userId, 'recycle_restore', $type, $id);
        }
        return $ok ? json_success([], '已恢复') : json_error('恢复失败或记录不存在');
    }

    /** AJAX：彻底删除（物理删除，需无阻塞项） */
    public function purge()
    {
        if (!$this->isSuperAdmin()) {
            return $this->deny();
        }
        $type = input('type', '');
        $id   = (int)input('id', 0);
        if (!RecycleBinLogic::isValidType($type) || !$id) {
            return json_error('参数错误');
        }
        $res = RecycleBinLogic::purge($type, $id);
        if (!$res['ok']) {
            return json_error('无法彻底删除：' . implode('；', $res['blockers']), 1, ['blockers' => $res['blockers']]);
        }
        // v2.44.1 P1：彻底删除属敏感权限操作，补审计留痕
        \app\common\service\AuditService::log($this->userId, 'recycle_purge', $type, $id);
        return json_success([], '已彻底删除');
    }
}
