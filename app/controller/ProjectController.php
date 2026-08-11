<?php
// +----------------------------------------------------------------------
// | 项目管理控制器 (P2-5 合同→项目关联)
// +----------------------------------------------------------------------

namespace app\controller;

use think\facade\View;
use think\facade\Db;
use app\BaseController;
use app\common\logic\ProjectLogic;
use app\common\logic\AuthLogic;
use app\common\logic\CustomerLogic;
use app\common\logic\ContractLogic;
use app\common\logic\PaymentLogic;
use app\common\service\AuditService;

class ProjectController extends BaseController
{
    /** 创建/编辑页客户下拉最大加载条数（REV-44：收敛魔法数字 500 为具名常量） */
    const CUSTOMER_SELECT_LIMIT = 500;

    /** 项目列表 */
    public function index()
    {
        $this->requirePermission('project:view');
        $filter = [
            'keyword' => $this->getParam('keyword', ''),
            'status'  => $this->getParam('status', ''),
        ];

        list($page, $pageSize) = $this->getPageParams();
        [$sortField, $sortOrder] = $this->getSortParams([
            'id'         => 'id',
            'name'       => 'name',
            'created_at' => 'created_at',
        ], 'id', 'desc');
        $result = ProjectLogic::getList($page, $pageSize, $filter, [$sortField, $sortOrder]);

        if (request()->isAjax()) {
            return layui_table($result['list'], $result['total']);
        }

        View::assign('projects', $result['list']);
        View::assign('filter', $filter);
        View::assign('statusDict', dict('project_status'));
        return View::fetch();
    }

    /** 新建/编辑页 */
    public function create($id = 0, $template = '')
    {
        $id = (int)($this->getParam('id', $id));
        // UX 门控：新建需 project:create，编辑需 project:edit（与 save() 口径一致，原 :view 过宽）
        $this->requirePermission($id ? 'project:edit' : 'project:create');
        $project = $id ? ProjectLogic::getDetail($id) : null;
        if ($project && $project['owner_id'] != 0
            && !AuthLogic::canAccessRecord($project['owner_id'], $project['dept_id'] ?? 0)) {
            return '无权查看该项目';
        }
        View::assign('project', $project);
        View::assign('statusDict', dict('project_status'));
        // REV-44：客户下拉加载上限使用具名常量（如需随数据量增长，调整此处一处即可）
        View::assign('customers', CustomerLogic::getOptionsForSelect(self::CUSTOMER_SELECT_LIMIT));
        return $template ? View::fetch($template) : View::fetch();
    }

    /** 编辑页 */
    public function edit($id)
    {
        return $this->create($id, 'project/create');
    }

    /** AJAX: 保存项目 */
    public function save()
    {
        $id = (int)$this->getPost('id', 0);
        $this->requirePermission($id ? 'project:edit' : 'project:create');

        if ($id) {
            $existing = ProjectLogic::findRaw($id);
            if (!$existing || ($existing['owner_id'] != 0
                    && !AuthLogic::canAccessRecord($existing['owner_id'], $existing['dept_id'] ?? 0))) {
                return json_error('无权限编辑该项目');
            }
        }

        $data = [
            'name'        => trim($this->getPost('name', '')),
            'code'        => trim($this->getPost('code', '')),
            'customer_id' => (int)$this->getPost('customer_id', 0),
            'status'      => $this->getPost('status', 'ACTIVE'),
            // v2.40.0 P1-6：执行阶段 + 进度
            'stage'       => $this->getPost('stage', 'PLANNING'),
            'progress'    => max(0, min(100, (int)$this->getPost('progress', 0))),
            'budget'      => (float)$this->getPost('budget', 0),
            'start_date'  => $this->getPost('start_date', '') ?: null,
            'end_date'    => $this->getPost('end_date', '') ?: null,
            'remark'      => $this->getPost('remark', ''),
        ];

        if ($data['name'] === '') {
            return json_error('请输入项目名称');
        }
        // v2.40.7：项目状态保存白名单去字典化——此前 array_keys(dict('project_status')) 直接把字典当校验白名单，
        // 字典任意增项会放行未识别状态入库（列表/统计不认识）；改为代码常量枚举，字典仅用于显示 label。
        $allowStatus = ['ACTIVE', 'DONE', 'ARCHIVED'];
        if (!in_array($data['status'], $allowStatus, true)) {
            $data['status'] = 'ACTIVE';
        }
        $allowStage = ['PLANNING', 'EXECUTING', 'ACCEPTANCE', 'COMPLETED'];
        if (!in_array($data['stage'], $allowStage, true)) {
            $data['stage'] = 'PLANNING';
        }

        if ($id) {
            ProjectLogic::update($id, $data);
            AuditService::log($this->userId, 'update', 'project', $id);
        } else {
            $data['owner_id'] = $this->userId;
            $data['dept_id']  = $this->user['dept_id'] ?? 0;
            $id = ProjectLogic::create($data);
            AuditService::log($this->userId, 'create', 'project', $id);
        }

        return json_success(['id' => $id], '保存成功');
    }

    /** 项目详情（含经营聚合与合同列表） */
    public function detail($id)
    {
        $this->requirePermission('project:view');
        $project = ProjectLogic::getDetail((int)$id);
        if (!$project) return '项目不存在';
        if ($project['owner_id'] != 0
            && !AuthLogic::canAccessRecord($project['owner_id'], $project['dept_id'] ?? 0)) {
            return '无权查看该项目';
        }

        View::assign('project', $project);
        View::assign('stat', ProjectLogic::aggregate((int)$id));
        // P2-3【M-Pf5】合同列表绑定上限（默认 200），并取总数供视图展示"查看全部"
        View::assign('contracts', ProjectLogic::getContracts((int)$id));
        View::assign('contract_total', ProjectLogic::getContractsCount((int)$id));
        View::assign('contract_limit', 200);
        View::assign('statusDict', dict('project_status'));
        View::assign('contractStatusDict', dict('contract_status'));
        // v2.40.0 P1-6：验收按钮权限
        View::assign('can_edit', $this->hasPermission('project:edit'));
        return View::fetch();
    }

    /** AJAX: 删除项目（软删 + 关联合同解绑 + 审计） */
    public function delete()
    {
        $this->requirePermission('project:delete');
        $id = (int)$this->getPost('id', 0);
        $existing = ProjectLogic::findRaw($id);
        if (!$existing || ($existing['owner_id'] != 0
                && !AuthLogic::canAccessRecord($existing['owner_id'], $existing['dept_id'] ?? 0))) {
            return json_error('无权限删除该项目');
        }
        if (ProjectLogic::softDelete($id)) {
            // 关联合同 project_id 解绑，避免悬空
            ProjectLogic::unlinkContracts($id);
            AuditService::log($this->userId, 'delete', 'project', $id);
            return json_success(null, '已删除');
        }
        return json_error('删除失败');
    }

    /**
     * v2.40.0 P1-6：标记验收完成（验收联动）
     * - 项目 stage 置 COMPLETED、进度 100、状态 DONE；
     * - 联动项目下执行中/已通过/历史已签的销售合同置 COMPLETED；
     * - 返回待收尾款金额（milestone=FINAL_PAYMENT 或说明含「尾款」的 PENDING 记录），供前端提示。
     */
    public function accept()
    {
        $this->requirePermission('project:edit');
        $id = (int)$this->getPost('id', 0);
        $existing = ProjectLogic::findRaw($id);
        if (!$existing || !empty($existing['is_deleted'])) {
            return json_error('项目不存在');
        }
        if ($existing['owner_id'] != 0
            && !AuthLogic::canAccessRecord($existing['owner_id'], $existing['dept_id'] ?? 0)) {
            return json_error('无权限操作该项目');
        }

        // 联动：项目下销售合同（执行中/已通过/历史已签）置已完成（P1-1：下沉 ProjectLogic::completeSalesContracts）
        $affected = ProjectLogic::completeSalesContracts($id);

        // 待收尾款统计（项目关联合同下的 PENDING 尾款记录）（P1-1：下沉 ProjectLogic::sumPendingTailAmount）
        $pendingTail = ProjectLogic::sumPendingTailAmount($id);

        ProjectLogic::update($id, [
            'stage'      => 'COMPLETED',
            'progress'   => 100,
            'status'     => 'DONE',
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        AuditService::log($this->userId, 'accept', 'project', $id);

        $msg = '验收完成，已联动 ' . $affected . ' 份销售合同标记已完成';
        if ($pendingTail > 0) {
            $msg .= '，待收尾款 ¥' . number_format($pendingTail, 0);
        }
        return json_success(['pending_tail' => $pendingTail, 'affected_contracts' => $affected], $msg);
    }

    /**
     * 终止项目（2026-08-10 新增）：
     * - 仅进行中（ACTIVE）且未完结（stage!=COMPLETED）项目可终止，置 TERMINATED；
     * - 联动：项目下执行中/已通过/历史已签的销售合同一并终止（复用合同状态机，
     *   存在逾期未结回款的合同跳过并返回清单，不阻塞项目终止）。
     * - 可撤销（restore 回 ACTIVE）。
     */
    public function terminate()
    {
        $this->requirePermission('project:edit');
        $id = (int)$this->getPost('id', 0);
        $existing = ProjectLogic::findRaw($id);
        if (!$existing || !empty($existing['is_deleted'])) {
            return json_error('项目不存在');
        }
        if (!AuthLogic::canAccessRecord($existing['owner_id'], $existing['dept_id'] ?? 0)) {
            return json_error('无权限操作该项目');
        }
        if (($existing['status'] ?? '') !== 'ACTIVE') {
            return json_error('仅进行中的项目可终止');
        }
        if (($existing['stage'] ?? '') === 'COMPLETED') {
            return json_error('已完结的项目不可终止，请用「标记验收完成」后的撤销或直接删除');
        }

        // 联动终止：项目下执行中/已通过/历史已签销售合同（trade_attr=1、direction=sales）
        $affected = 0;
        $skipped = []; // 有逾期未结回款被跳过的合同号
        $query = Db::name('contract')->alias('c')
            ->where('c.project_id', $id)
            ->where('c.is_deleted', 0)
            ->where('c.trade_attr', 1)
            ->where('c.direction', 'sales')
            ->where('c.status', 'in', ['EXECUTING', 'APPROVED', 'SIGNED']);
        AuthLogic::appendDataScope($query, 'c.owner_id', 'c.dept_id');
        foreach ($query->field('c.id, c.contract_no')->select() as $row) {
            if (PaymentLogic::hasOverdue((int)$row['id'])) {
                $skipped[] = $row['contract_no'];
                continue;
            }
            if (ContractLogic::transitionStatus((int)$row['id'], ContractLogic::STATUS_TERMINATED, $this->userId)) {
                $affected++;
            }
        }

        ProjectLogic::update($id, [
            'status'     => 'TERMINATED',
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        AuditService::log($this->userId, 'terminate', 'project', $id, ['affected_contracts' => $affected, 'skipped' => $skipped]);

        $msg = '项目已终止，联动终止 ' . $affected . ' 份销售合同';
        if (!empty($skipped)) {
            $msg .= '；' . count($skipped) . ' 份合同存在逾期未结回款已跳过：' . implode('、', $skipped);
        }
        return json_success(['affected_contracts' => $affected, 'skipped' => $skipped], $msg);
    }

    /** 撤销终止：TERMINATED → ACTIVE（项目本身；合同状态不联动恢复） */
    public function restore()
    {
        $this->requirePermission('project:edit');
        $id = (int)$this->getPost('id', 0);
        $existing = ProjectLogic::findRaw($id);
        if (!$existing || !empty($existing['is_deleted'])) {
            return json_error('项目不存在');
        }
        if (!AuthLogic::canAccessRecord($existing['owner_id'], $existing['dept_id'] ?? 0)) {
            return json_error('无权限操作该项目');
        }
        if (($existing['status'] ?? '') !== 'TERMINATED') {
            return json_error('仅已终止的项目可撤销终止');
        }
        ProjectLogic::update($id, [
            'status'     => 'ACTIVE',
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        AuditService::log($this->userId, 'restore', 'project', $id);
        return json_success(null, '已撤销终止，项目恢复进行中');
    }

    /** AJAX: 下拉选项（合同创建页关联项目用） */
    public function options()
    {
        $this->requireAnyPermission(['project:view', 'contract:create', 'contract:edit']);
        return json_success(ProjectLogic::options());
    }

    /**
     * AJAX: 项目搜索（合同创建页「关联项目」搜索选择器，2026-08-05）。
     * q 为空时返回「与我有关」推荐项（owner_id=当前用户优先）。
     */
    public function search()
    {
        $this->requireAnyPermission(['project:view', 'contract:create', 'contract:edit']);
        $q = trim((string)$this->getParam('q', ''));
        // 长度上限（防超长输入），空串表示推荐模式
        if (mb_strlen($q) > 60) $q = mb_substr($q, 0, 60);
        return json_success(ProjectLogic::search($q));
    }
}
