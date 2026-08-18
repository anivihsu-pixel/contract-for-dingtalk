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
use app\common\logic\CompanyLogic;
use app\common\logic\RoleLogic;
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
        $flow = \app\common\logic\ApprovalSubmitService::matchFlow($contract['business_type'] ?? '', $contract['direction'] ?? '', (float)($contract['amount'] ?? 0), (int)($contract['trade_attr'] ?? 1));

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
        // v2.51.10：抄送角色不展示角色名，仅展示角色对应的成员用户（$ccNames），故此处只做成员解析
        $ccResolvedIds = [];
        if (!empty($ccRoles)) {
            $ccResolvedIds = ApproverResolver::resolveRoleCodes($ccRoles);
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
            // 指定审批人为提交人时会被自审批防护排除，明确提示配置原因，避免误显示为未配置人员。
            if (empty($ids) && ($n['type'] ?? '') === 'SPECIFIC_USER'
                && in_array($this->userId, ApproverResolver::specificUserIds($n), true)) {
                $n['resolve_warning'] = '指定审批人不能是提交人，请更换审批人';
            }
            $flowNodes[] = $n;
        }
        // 抄送用户姓名（去重后映射）
        $ccNames = array_values(array_filter(array_map(function ($id) use ($userMap) {
            return $userMap[$id] ?? null;
        }, $ccResolvedIds)));

        View::assign('contract', $contract);
        View::assign('matched_flow', $flow);
        View::assign('flow_nodes', $flowNodes);
        View::assign('cc_names', $ccNames);
        // v2.51.10：仅当抄送有实际成员（角色成员或指定用户）时展示抄送行，角色名不再单独列出
        View::assign('has_cc', !empty($ccNames));
        return View::fetch();
    }

    /** AJAX: 提交审批（自动匹配流程） */
    public function submit()
    {
        $this->requirePermission('approval:submit');
        $contractId = (int)$this->getPost('contract_id', 0);

        if (!ContractLogic::accessible($contractId)) {
            return json_error('无权限提交该合同审批');
        }

        try {
            $result = \app\common\logic\ApprovalSubmitService::submit($contractId, $this->userId);
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

        // 2026-08-15：开票审批（biz_type=invoice）不关联合同，PC 端跳转「发票申请」页查看/管理
        if (($detail['biz_type'] ?? '') === 'invoice') {
            return redirect('/invoice-apply');
        }

        // 与移动端审批详情共用合同数据口径：审批页内直接完成合同查阅和决策。
        $contract = ContractLogic::getById((int)$detail['contract_id']);
        if (!$contract) return '关联合同不存在';

        // PERM-1：审批参与者（提交人 / 任一节点审批人 / 抄送人）默认可查看本审批实例及其合同，
        // 即使无 approval:view 权限或超出数据范围——其拥有合法的知悉权，不应被拦截。
        $isParticipant = \app\common\logic\ApprovalQueryService::isParticipant($id, $this->userId);
        if (!$isParticipant) {
            $this->requirePermission('approval:view');
            if (!ContractLogic::accessible((int)$detail['contract_id'])) return '无权限查看该审批';
        }

        // 与移动端统一：当前节点的实际待审批记录就是操作授权依据。
        // action 服务仍会在事务内复核实例状态、节点和审批人，避免越权或重复处理。
        $canAct = \app\common\logic\ApprovalQueryService::getPendingAction((int)$id, $this->userId);

        // PC 端补齐转交/撤回入口（与移动端同口径）：转交目标用户 + 提交人可撤回
        $transferUsers = UserLogic::getTransferTargets(
            $this->userId,
            !empty($this->user['is_admin']),
            (int)($this->user['dept_id'] ?? 0),
            ''
        );
        $canRecall = ((int)($detail['submitted_by'] ?? 0) === $this->userId)
            && ($detail['status'] ?? '') === 'PENDING';

        $attachments = ContractLogic::parseFileUrls((string)($contract['file_url'] ?? ''));
        $ourCompany = CompanyLogic::getName((int)($contract['our_company_id'] ?? 0));

        View::assign('detail', $detail);
        View::assign('contract', $contract);
        View::assign('can_act', !empty($canAct));
        View::assign('can_recall', $canRecall);
        View::assign('transfer_users', $transferUsers);
        View::assign('attachments', $attachments);
        View::assign('our_company', $ourCompany);
        return View::fetch();
    }

    /** AJAX: 审批操作 (同意/驳回/转交) */
    public function action($id)
    {
        // 指定人员审批、角色调整后仍以流程实例中的当前待审批人为准，与移动端按钮口径一致。
        if (!\app\common\logic\ApprovalQueryService::getPendingAction((int)$id, $this->userId)) {
            return json_error('当前用户不是该节点审批人或审批已处理');
        }
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
        $businessType = $this->getParam('business_type', '');
        $direction = $this->getParam('direction', '');
        $amount   = (float)$this->getParam('amount', 0);
        $tradeAttr = (int)$this->getParam('trade_attr', 1); // M10：非交易合同跳过金额条件匹配

        $flow = \app\common\logic\ApprovalSubmitService::matchFlow($businessType, $direction, $amount, $tradeAttr);

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
