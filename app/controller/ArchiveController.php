<?php
// +----------------------------------------------------------------------
// | 归档控制器
// +----------------------------------------------------------------------

namespace app\controller;

use think\facade\View;
use app\BaseController;
use app\common\logic\ContractLogic;
use app\common\logic\AuthLogic;
use app\common\service\AuditService;

class ArchiveController extends BaseController
{
    /**
     * 归档合同列表 — 仅展示已归档(status=ARCHIVED)合同
     * 走数据权限（SELF/DEPT/ALL），防越权查看他人归档合同
     */
    public function index()
    {
        $this->requirePermission('contract:view');
        $keyword = $this->getParam('keyword', '');
        list($page, $pageSize) = $this->getPageParams();

        // P3-4（m23）：归档列表直查下沉至 ContractLogic::getArchivedList
        // 复用其内置的 appendDataScope 数据权限 + 分页 + 标题/合同号模糊搜索，控制器仅做编排（保持 v2.26 零直查铁律）
        $res   = ContractLogic::getArchivedList($page, $pageSize, $keyword);
        $list  = $res['list'];
        $total = $res['total'];

        if (request()->isAjax()) {
            return layui_table($list, $total);
        }

        View::assign('contracts', $list);
        View::assign('keyword', $keyword);
        return View::fetch();
    }

    /**
     * 执行归档 — 将合同状态流转至 ARCHIVED
     * 需 contract:edit 权限 + 数据范围校验
     * 写来源态→目标态审计（audit_log 由 AuditService 记录）
     */
    public function do($contractId)
    {
        $this->requirePermission('contract:edit');
        // 越权防护：仅可归档自己数据范围内的合同；同时取出来源态用于审计
        $contract = ContractLogic::accessible((int)$contractId);
        if (!$contract) {
            return json_error('无权限归档该合同');
        }
        $fromStatus = $contract['status']; // 来源态：归档前的当前状态
        $toStatus   = ContractLogic::STATUS_ARCHIVED;          // 目标态

        if (ContractLogic::transitionStatus((int)$contractId, $toStatus, $this->userId)) {
            // m7：来源态→目标态审计留痕（审计日志便于审计中心检索）
            AuditService::log($this->userId, 'archive', 'contract', (int)$contractId, [
                'from'       => $fromStatus,
                'to'         => $toStatus,
                'transition' => $fromStatus . '->' . $toStatus,
            ]);
            return json_success(null, '已归档');
        }
        return json_error('归档失败');
    }

    /**
     * 取消归档 — 将合同状态从 ARCHIVED 回退至 EXECUTING（CR-07 归档可逆）
     * 需 contract:edit 权限 + 数据范围校验 + 来源态审计
     */
    public function undo($contractId)
    {
        $this->requirePermission('contract:edit');
        // 越权防护：仅可操作自己数据范围内的合同；同时取出来源态用于审计
        $contract = ContractLogic::accessible((int)$contractId);
        if (!$contract) {
            return json_error('无权限操作该合同');
        }
        $fromStatus = $contract['status']; // 来源态：取消归档前的状态（应为 ARCHIVED）
        $toStatus   = ContractLogic::STATUS_EXECUTING;         // 目标态

        if (ContractLogic::transitionStatus((int)$contractId, $toStatus, $this->userId)) {
            // m7：来源态→目标态审计留痕
            AuditService::log($this->userId, 'unarchive', 'contract', (int)$contractId, [
                'from'       => $fromStatus,
                'to'         => $toStatus,
                'transition' => $fromStatus . '->' . $toStatus,
            ]);
            return json_success(null, '已取消归档');
        }
        return json_error('取消归档失败（状态不允许或合同不存在）');
    }
}
