<?php
// +----------------------------------------------------------------------
// | 审批控制器
// +----------------------------------------------------------------------

namespace app\controller;

use think\facade\View;
use app\BaseController;
use app\common\logic\ApprovalLogic;
use app\common\logic\ApproverResolver;
use app\common\logic\ContractLogic;
use app\common\logic\RoleLogic;
use app\common\logic\TemplateLogic;
use app\common\logic\UserLogic;
use app\common\service\AuditService;

class ApprovalController extends BaseController
{
    /** 审批列表 */
    public function index()
    {
        $this->requirePermission('approval:view');
        View::assign('tab', $this->getParam('tab', 'pending'));
        return View::fetch();
    }

    /** AJAX: 待审批列表 */
    public function pendingList()
    {
        $this->requirePermission('approval:view');
        list($page, $pageSize) = $this->getPageParams();
        [$sortField, $sortOrder] = $this->getSortParams([
            'id'           => 'ai.id',
            'submitted_at' => 'ai.submitted_at',
            'contract_no'  => 'c.contract_no',
            'amount'       => 'c.amount',
        ], 'ai.id', 'desc');
        $result = \app\common\logic\ApprovalQueryService::getPendingList($this->userId, $page, $pageSize, [$sortField, $sortOrder]);
        return layui_table($result['list'], $result['total']);
    }

    /** AJAX: 已审批列表 */
    public function processedList()
    {
        $this->requirePermission('approval:view');
        list($page, $pageSize) = $this->getPageParams();
        [$sortField, $sortOrder] = $this->getSortParams([
            'id'           => 'ai.id',
            'submitted_at' => 'ai.submitted_at',
            'contract_no'  => 'c.contract_no',
            'amount'       => 'c.amount',
        ], 'ai.id', 'desc');
        $result = \app\common\logic\ApprovalQueryService::getProcessedList($this->userId, $page, $pageSize, [$sortField, $sortOrder]);
        return layui_table($result['list'], $result['total']);
    }

    /** AJAX: 我提交的审批 */
    public function submittedList()
    {
        $this->requirePermission('approval:view');
        list($page, $pageSize) = $this->getPageParams();
        [$sortField, $sortOrder] = $this->getSortParams([
            'id'           => 'ai.id',
            'submitted_at' => 'ai.submitted_at',
            'contract_no'  => 'c.contract_no',
            'amount'       => 'c.amount',
        ], 'ai.id', 'desc');
        $result = \app\common\logic\ApprovalQueryService::getMySubmittedList($this->userId, $page, $pageSize, [$sortField, $sortOrder]);
        return layui_table($result['list'], $result['total']);
    }

    /** 提交审批页 */
    public function create($contractId)
    {
        $this->requirePermission('approval:submit');
        $contract = ContractLogic::accessible((int)$contractId);
        if (!$contract) return '无权限或无此合同';

        if ($contract['status'] !== ContractLogic::STATUS_DRAFT && $contract['status'] !== ContractLogic::STATUS_REJECTED) {
            return '当前合同状态不可提交审批';
        }

        // 自动匹配将使用的审批流程（预览，无需用户手动选择）
        $flow = \app\common\logic\ApprovalSubmitService::matchFlow($contract['category'] ?? '', (float)($contract['amount'] ?? 0), (int)($contract['trade_attr'] ?? 1));
        if (!$flow && !empty($contract['template_id'])) {
            $tpl = TemplateLogic::getById((int)$contract['template_id']);
            if ($tpl && !empty($tpl['default_flow_id'])) {
                $flow = \app\common\logic\ApprovalQueryService::getFlowById((int)$tpl['default_flow_id']);
            }
        }

        // 解析每个审批节点的实际用户，便于提交页直接展示具体人员（含角色下的成员）
        $rawNodes = json_decode($flow['nodes'] ?? '[]', true) ?: [];
        $allUserIds = [];
        $resolvedByNode = [];
        foreach ($rawNodes as $idx => $n) {
            $resolvedByNode[$idx] = ApproverResolver::resolve($n, $this->userId);
            $allUserIds = array_merge($allUserIds, $resolvedByNode[$idx]);
        }
        // v2.38.1：同步解析流程级抄送列表（cc_list），提交页展示抄送人
        $ccList  = json_decode($flow['cc_list'] ?? '{}', true) ?: [];
        $ccRoles = $ccList['role_codes'] ?? [];
        $ccUsers = $ccList['cc_user_ids'] ?? [];
        $ccRoleNames   = [];
        $ccResolvedIds = [];
        if (!empty($ccRoles)) {
            $roleMap  = RoleLogic::getMap(); // code→name
            foreach ($ccRoles as $rc) {
                $ccRoleNames[] = $roleMap[$rc] ?? $rc;
                $ccResolvedIds = array_merge($ccResolvedIds, ApproverResolver::resolveRoleCodes([$rc]));
            }
        }
        if (!empty($ccUsers)) {
            $ccResolvedIds = array_merge($ccResolvedIds, $ccUsers);
        }
        $ccResolvedIds = array_unique(array_map('intval', $ccResolvedIds));
        $allUserIds    = array_merge($allUserIds, $ccResolvedIds);
        $userMap = UserLogic::getNamesByIds($allUserIds);
        $flowNodes = [];
        foreach ($rawNodes as $idx => $n) {
            $ids = $resolvedByNode[$idx];
            $n['resolved_names'] = array_values(array_filter(array_map(function ($id) use ($userMap) {
                return $userMap[$id] ?? null;
            }, $ids)));
            $flowNodes[] = $n;
        }
        // 抄送用户姓名（去重后映射）
        $ccNames = array_values(array_filter(array_map(function ($id) use ($userMap) {
            return $userMap[$id] ?? null;
        }, $ccResolvedIds)));

        View::assign('contract', $contract);
        View::assign('matched_flow', $flow);
        View::assign('role_map', RoleLogic::getMap());
        View::assign('flow_nodes', $flowNodes);
        View::assign('cc_names', $ccNames);
        View::assign('cc_roles', $ccRoleNames);
        View::assign('has_cc', !empty($ccNames) || !empty($ccRoles));
        return View::fetch();
    }

    /** AJAX: 提交审批（自动匹配流程） */
    public function submit()
    {
        $this->requirePermission('approval:submit');
        $contractId = (int)$this->getPost('contract_id', 0);
        // 尊重前端传入的流程 ID（来自合同模板默认流 / 流程选择）。
        // 修复（v2.37.5）：此前此处硬编码只传 $contractId，导致 \app\common\logic\ApprovalSubmitService::submit 的 flowId 默认 0，
        // 永远走 matchFlow 自动匹配——即便合同已配置好「含抄送节点」的流程，也会被自动匹配成
        // 另一个把同一人设为审批人的流程，造成「纯抄送人收到审批催办且能审批」的错配。
        // 传入 flow_id 后，引擎优先使用该流程；为 0 时回退 matchFlow（兼容未配置默认流的老合同）。
        $flowId = (int)$this->getPost('flow_id', 0);

        if (!ContractLogic::accessible($contractId)) {
            return json_error('无权限提交该合同审批');
        }

        try {
            $result = \app\common\logic\ApprovalSubmitService::submit($contractId, $this->userId, $flowId);
        } catch (\RuntimeException $e) {
            // 校验类错误（合同不存在 / 未匹配流程 / 审批人为空等）给出明确提示
            return json_error($e->getMessage());
        }
        if ($result) {
            // REV-15：审批提交动作写入中央审计日志，便于合规追溯（谁在何时提交哪个合同审批）
            AuditService::log($this->userId, 'approve_submit', 'contract', $contractId, ['instance_id' => $result]);
            return json_success(['instance_id' => $result], '审批已提交');
        }
        return json_error('提交审批失败：未匹配到适用的审批流程，请联系管理员配置');
    }

    /** 审批详情 */
    public function detail($id)
    {
        $id = (int)$id;
        $detail = \app\common\logic\ApprovalQueryService::getDetail($id);
        if (!$detail) return '审批不存在';

        // PERM-1：审批参与者（提交人 / 任一节点审批人 / 抄送人）默认可查看本审批实例及其合同，
        // 即使无 approval:view 权限或超出数据范围——其拥有合法的知悉权，不应被拦截。
        $isParticipant = \app\common\logic\ApprovalQueryService::isParticipant($id, $this->userId);
        if (!$isParticipant) {
            $this->requirePermission('approval:view');
            if (!ContractLogic::accessible((int)$detail['contract_id'])) return '无权限查看该审批';
        }

        // 检查当前用户是否是正在处理的审批人
        // v2.40.1：叠加 approval:approve 权限判断——防止角色调整/历史数据导致
        // 无审批权限的用户（如普通用户）成为节点审批人时仍看到「同意/驳回」按钮
        $canAct = \app\common\logic\ApprovalQueryService::getPendingAction((int)$id, $this->userId)
            && $this->hasPermission('approval:approve');

        // PC 端补齐转交/撤回入口（与移动端同口径）：转交目标用户 + 提交人可撤回
        $transferUsers = UserLogic::getTransferTargets(
            $this->userId,
            !empty($this->user['is_admin']),
            (int)($this->user['dept_id'] ?? 0),
            ''
        );
        $canRecall = ((int)($detail['submitted_by'] ?? 0) === $this->userId)
            && ($detail['status'] ?? '') === 'PENDING';

        View::assign('detail', $detail);
        View::assign('can_act', !empty($canAct));
        View::assign('can_recall', $canRecall);
        View::assign('transfer_users', $transferUsers);
        return View::fetch();
    }

    /** AJAX: 审批操作 (同意/驳回/转交) */
    public function action($id)
    {
        $this->requirePermission('approval:approve');
        $action     = $this->getPost('action', 'APPROVED');
        $comment    = $this->getPost('comment', '');
        $transferTo = (int)$this->getPost('transfer_to', 0);
        $rejectTo   = (int)$this->getPost('reject_to_order', 0); // v2.38.3 驳回到指定节点

        if (!in_array($action, ['APPROVED', 'REJECTED', 'TRANSFERRED'])) {
            return json_error('无效操作');
        }
        // 2026-08-04：驳回意见改为选填（不再强制必填，用户可按需填写）
        if ($action === 'REJECTED' && ($rejectTo < 0 || $rejectTo > 100)) {
            return json_error('驳回到节点参数错误');
        }

        try {
            if (\app\common\logic\ApprovalActionService::action((int)$id, $this->userId, $action, $comment, $transferTo ?: null, ['reject_to_order' => $rejectTo])) {
                // REV-15：审批同意/驳回/转交动作写入中央审计日志
                $inst = \app\common\logic\ApprovalQueryService::findRaw((int)$id);
                AuditService::log($this->userId, 'approve_' . strtolower($action), 'contract', (int)($inst['contract_id'] ?? 0), [
                    'instance_id' => (int)$id,
                    'comment'     => $comment,
                    'transfer_to' => $transferTo ?: null,
                ]);
                return json_success(null, '操作成功');
            }
        } catch (\RuntimeException $e) {
            // 转交目标无效等业务校验异常：回显具体原因（如「转交目标用户无效」），避免笼统的「操作失败」
            \think\facade\Log::error('approval action failed: ' . $e->getMessage() . ' instance=' . $id);
            return json_error($e->getMessage() ?: '操作失败');
        }
        return json_error('操作失败');
    }

    /** AJAX: 撤回审批 */
    public function recall($id)
    {
        $this->requirePermission('approval:submit');
        if (\app\common\logic\ApprovalActionService::recall((int)$id, $this->userId)) {
            // REV-15：撤回动作写入中央审计日志
            $inst = \app\common\logic\ApprovalQueryService::findRaw((int)$id);
            AuditService::log($this->userId, 'approve_recall', 'contract', (int)($inst['contract_id'] ?? 0), ['instance_id' => (int)$id]);
            return json_success(null, '已撤回');
        }
        return json_error('撤回失败，仅提交人可撤回待审中的审批');
    }

    /** AJAX: 获取合同可匹配的审批流程 */
    public function matchedFlows()
    {
        $this->requirePermission('approval:view');
        $category = $this->getParam('category', '');
        $amount   = (float)$this->getParam('amount', 0);
        $tradeAttr = (int)$this->getParam('trade_attr', 1); // M10：非交易合同跳过金额条件匹配

        $flow = \app\common\logic\ApprovalSubmitService::matchFlow($category, $amount, $tradeAttr);

        $allFlows = \app\common\logic\ApprovalQueryService::getEnabledFlows();
        return json_success([
            'matched_flow' => $flow,
            'all_flows'    => $allFlows,
        ]);
    }

    /** AJAX: 转交目标用户搜索（Phase 2.8：分页 + 权限范围） */
    public function transferTargets()
    {
        $this->requirePermission('approval:view');
        $keyword  = trim((string)$this->getParam('keyword', ''));
        $page     = max(1, (int)$this->getParam('page', 1));
        $pageSize = 20;

        $res = \app\common\logic\UserLogic::getTransferTargetsPaged(
            $this->userId,
            !empty($this->user['is_admin']),
            (int)($this->user['dept_id'] ?? 0),
            $keyword,
            $page,
            $pageSize
        );

        return json_success([
            'list'  => $res['list'],
            'total' => $res['total'],
            'page'  => $page,
            'has_more' => ($page * $pageSize) < $res['total'],
        ]);
    }
}
