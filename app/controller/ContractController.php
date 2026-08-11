<?php
// +----------------------------------------------------------------------
// | 合同控制器
// +----------------------------------------------------------------------

namespace app\controller;

use think\facade\View;
use app\BaseController;
use app\common\logic\ContractLogic;
use app\common\logic\ApprovalLogic;
use app\common\logic\AuthLogic;
use app\common\logic\CompanyLogic;
use app\common\logic\UserLogic;
use app\common\logic\CustomerLogic;
use app\common\logic\SupplierLogic;
use app\common\logic\PartyLogic;        // v2.38.14 乙方往来摘要
use app\common\service\AuditService;
use app\common\response\StreamedFileResponse;
use app\common\helper\XlsxHelper;
use think\facade\Db;
use think\facade\Log;
use think\facade\Session;

class ContractController extends BaseController
{
    /** 合同列表 */
    public function index()
    {
        $this->requirePermission('contract:view');
        $filter = [
            'keyword'       => $this->getParam('keyword', ''),
            'status'        => $this->getParam('status', ''),
            'category'      => $this->getParam('category', ''),
            'direction'     => $this->getParam('direction', ''),
            'framework'     => $this->getParam('framework', ''),
            'date_start'    => $this->getParam('date_start', ''),
            'date_end'      => $this->getParam('date_end', ''),
            // REV-29：高级筛选 — 金额区间 / 相对方 / 签约主体 / 归属人
            'amount_min'    => $this->getParam('amount_min', ''),
            'amount_max'    => $this->getParam('amount_max', ''),
            'party_name'    => $this->getParam('party_name', ''),
            'our_company_id'=> $this->getParam('our_company_id', ''),
            'owner_id'      => $this->getParam('owner_id', ''),
        ];
        // 非交易筛选：仅当显式传入 trade_attr（0 或 1）时过滤；空字符串不附加，避免 0 被 empty() 误判
        $tradeAttrParam = $this->getParam('trade_attr', '');
        if ($tradeAttrParam !== '') {
            $filter['trade_attr'] = (int)$tradeAttrParam;
        }
        // v2.40.0：「我的草稿」快捷筛选——owner_id=me 特殊值转当前用户 id
        $ownerIdParam = $this->getParam('owner_id', '');
        $filter['owner_id'] = $ownerIdParam === 'me' ? (string)$this->userId : $ownerIdParam;
        // 按项目筛选（P2-5）
        $projectIdParam = $this->getParam('project_id', '');
        if ($projectIdParam !== '') {
            $filter['project_id'] = (int)$projectIdParam;
        }

        list($page, $pageSize) = $this->getPageParams();
        [$sortField, $sortOrder] = $this->getSortParams([
            'id'         => 'c.id',
            'title'      => 'c.title',
            'amount'     => 'c.amount',
            'status'     => 'c.status',
            'created_at' => 'c.created_at',
        ], 'c.id', 'desc');
        // v2.44.4：默认视图草稿置顶；用户点击列排序时遵循所选排序
        $sort = $this->getParam('sort', '') === '' ? ['draft_first', 'desc'] : [$sortField, $sortOrder];
        $result = ContractLogic::getList($page, $pageSize, $filter, $sort);

        if (request()->isAjax()) {
            return layui_table($result['list'], $result['total']);
        }

        View::assign('contracts', $result['list']);
        View::assign('total', $result['total']);
        View::assign('filter', $filter);
        View::assign('categories', contract_categories());
        // P1-1（deep review）：状态筛选补全 10 态 — 与状态机单一真源 ContractLogic::STATUS_LABELS 同源渲染，
        // 避免视图硬编码 4 态漏掉 REJECTED/COMPLETED/EXPIRED/ARCHIVED 等。
        View::assign('status_labels', ContractLogic::STATUS_LABELS);
        View::assign('projects', \app\common\logic\ProjectLogic::options());
        // REV-29：高级筛选 — 本公司主体下拉与归属人下拉
        View::assign('companies', CompanyLogic::getBriefList());
        // v2.40.1：归属人下拉按数据范围收敛（ALL=全部用户 / DEPT=本部门用户 / SELF=仅本人），
        // 与合同列表数据范围一致，避免下拉出现范围外用户误导筛选
        $owners = UserLogic::getOptions();
        $vis = AuthLogic::visibility();
        if (!$vis['has_all']) {
            $visibleIds = [];
            if (!empty($vis['owner_self'])) {
                $visibleIds[] = (int)$this->userId;
            }
            if (!empty($vis['dept_ids'])) {
                $visibleIds = array_merge($visibleIds, UserLogic::getIdsByDeptIds($vis['dept_ids']));
            }
            $visibleIds = array_unique(array_map('intval', $visibleIds));
            $owners = array_values(array_filter($owners, fn($o) => in_array((int)$o['id'], $visibleIds, true)));
        }
        View::assign('owners', $owners);
        // UX 门控：导出按钮按 contract:export 权限渲染（后端 export 已有守卫，此处仅收敛视图入口）
        View::assign('can_export', $this->hasPermission('contract:export'));
        // UX 门控：批量归档按 contract:edit、批量删除按 contract:delete 权限分别渲染（与后端 batchArchive/batchDelete 守卫同口径）
        View::assign('can_batch', $this->hasPermission('contract:edit'));
        View::assign('can_delete', $this->hasPermission('contract:delete'));
        return View::fetch();
    }

    /** 创建合同页 */
    public function create($id = 0, $template = '')
    {
        $id = (int)($this->getParam('id', $id));
        // UX 门控：新建需 contract:create，编辑需 contract:edit（与 save() 口径一致，原 :view 过宽）
        $this->requirePermission($id ? 'contract:edit' : 'contract:create');
        $contract = $id ? ContractLogic::getDetail($id) : null;
        // 越权防护：编辑页仅允许归属人/创建人或管理员打开
        if ($contract && !\app\common\logic\AuthLogic::canAccessRecord($contract['owner_id'], $contract['dept_id'] ?? 0)) {
            throw new \think\exception\HttpException(403, '无权查看该合同');
        }

        if ($contract && !in_array($contract['status'], [ContractLogic::STATUS_DRAFT, ContractLogic::STATUS_REJECTED])) {
            throw new \think\exception\HttpException(403, '当前状态不可编辑');
        }

        View::assign('contract', $contract);
        View::assign('categories', contract_categories());
        // REV-34：关联框架合同下拉按数据权限收敛（非管理员仅见本人/本部门范围），并加安全上限避免全表加载
        View::assign('parent_contracts', ContractLogic::getFrameworkOptions(500));

        // 本公司主体（默认带出，用于新建合同自动识别签约主体）
        $companies = CompanyLogic::getListWithDefault();
        $defaultCompanyId = CompanyLogic::getDefaultId();
        View::assign('companies', $companies);
        View::assign('default_company_id', $defaultCompanyId);

        // 可关联项目（P2-5，仅未归档，走数据权限）
        View::assign('projects', \app\common\logic\ProjectLogic::options());

        // v2.47.x：当前登录用户（我方侧联系人/电话按登录用户带出）
        View::assign('current_user', [
            'name'   => $this->user['name'] ?? '',
            'mobile' => $this->user['mobile'] ?? '',
        ]);

        return $template ? View::fetch($template) : View::fetch();
    }

    /** AJAX: 保存合同 */
    public function save()
    {
        $id = (int)$this->getPost('id', 0);
        // 新建需 contract:create，编辑需 contract:edit
        $this->requirePermission($id ? 'contract:edit' : 'contract:create');

        // P1-1：编辑已有合同时先校验数据范围/所有权，越权直接 403（防止先返回业务校验错误）
        if ($id) {
            $existing = ContractLogic::findEditable($id);
            if (!$existing) return json_error('合同不存在或已被删除');
            if (!\app\common\logic\AuthLogic::canAccessRecord((int)$existing['owner_id'], $existing['dept_id'] ?? null)) {
                return json_error('无权修改该合同', 403);
            }
        }

        // 合同交易属性：1=交易(计入收支) / 0=非交易(不计入收支，金额强制0、方向置空)
        // P2-3（M5）：漏传时默认 0（非交易），杜绝非交易合同静默计入收支；前端创建页已显式提交 trade_attr。
        // v2.44.1 P1：更新路径漏传时保留旧值（不得把交易合同静默转非交易、清零金额），
        // 显式提交时做 0/1 白名单校验（传 2 等非法值直接拒绝，防止"隐身"合同）。
        $postedAttr = request()->post('trade_attr', null);
        if ($postedAttr === null) {
            $tradeAttr = $id ? (int)($existing['trade_attr'] ?? 0) : 0;
        } else {
            $tradeAttr = (int)$postedAttr;
            if (!in_array($tradeAttr, [0, 1], true)) {
                return json_error('交易属性参数非法');
            }
        }
        if ($tradeAttr === 0) {
            $direction = '';               // 非交易无方向
        } else {
            $direction = strtolower($this->getPost('direction', ''));
            // P2-1（M2）：direction 与 our_side 正交解耦，禁止按 supplier/customer 反推；
            // 交易合同必须显式选择方向，否则直接报错拒绝落库，避免方向错乱写入。
            if ($direction !== 'sales' && $direction !== 'purchase') {
                return json_error('请选择合同方向（销售/采购）');
            }
        }

        // 收付款方向与甲乙方法律地位解耦（2.0 修正）：direction 直接采用前端所选，
        // 不再由 our_side 强推；甲方/乙方仅由 party 名称记录，互不影响回款方向。

        $data = [
            'title'               => $this->getPost('title', ''),
            'category'            => $this->getPost('category', 'SERVICE'),
            'direction'           => $direction,
            'trade_attr'          => $tradeAttr,
            'project_id'          => (int)$this->getPost('project_id', 0),
            'flow_id'             => (int)$this->getPost('flow_id', 0),
            'our_company_id'      => (int)$this->getPost('our_company_id', 0),
            'amount'              => (float)$this->getPost('amount', 0),
            'party_a_name'        => $this->getPost('party_a_name', ''),
            'party_a_contact'     => $this->getPost('party_a_contact', ''),
            'party_a_phone'       => $this->getPost('party_a_phone', ''),
            // v2.40.0：我方=乙方时对方为甲方，客户关联需落甲方侧（此前仅支持乙方客户）
            'party_a_customer_id' => (int)$this->getPost('party_a_customer_id', 0),
            // v2.46.0：甲方供应商（我方=乙方、对方=甲方为供应商的采购合同）
            'party_a_supplier_id'=> (int)$this->getPost('party_a_supplier_id', 0),
            'party_b_customer_id' => (int)$this->getPost('party_b_customer_id', 0),
            'party_b_name'        => $this->getPost('party_b_name', ''),
            'party_b_contact'     => $this->getPost('party_b_contact', ''),
            'party_b_phone'       => $this->getPost('party_b_phone', ''),   // v2.47.x：乙方电话独立字段
            'party_b_credit_code' => $this->getPost('party_b_credit_code', ''),
            'effective_date'      => $this->getPost('effective_date', '') ?: null,
            'expiry_date'         => $this->getPost('expiry_date', '') ?: null,
            'content'             => $this->getPost('content', ''),
            'content_plain'       => strip_tags($this->getPost('content', '')),
            'keywords'            => normalize_keywords($this->getPost('keywords', '')), // 保存前归一化：中英文逗号/空格/顿号统一，去重去空
            'file_url'            => $this->getPost('file_url', ''),
            // v2.44.1 P1：归属（owner_id/dept_id）仅新建时写入；更新分支在下文 unset，
            // 防止同部门同事编辑他人合同后归属被改写、原 owner 丢失访问（归属变更走显式"转移"流程）
            'owner_id'            => $this->userId,
            'dept_id'             => $this->user['dept_id'] ?? 0,
            'parent_id'           => (int)$this->getPost('parent_id', 0),
            'supplier_id'         => (int)$this->getPost('supplier_id', 0),
        ];
        // v2.44.1 P1：更新分支移除归属字段——编辑不改写 owner_id/dept_id
        if ($id) {
            unset($data['owner_id'], $data['dept_id']);
        }

        // 非交易合同：金额强制为 0（前端已禁用，后端双保险），确保不计入收支统计
        if ($tradeAttr === 0) {
            $data['amount'] = 0.00;
        }

        // v2.44.1 P1：外键数据范围校验——project/party/supplier/parent 直接来自 POST，
        // 若不校验可见性可把本人合同挂到他人项目/客户名下（间接读他人数据 + 污染对方项目聚合）。
        // 仅当外键非 0 时校验；公海客户（owner_id=0）仍允许（合同关联客户不要求归属本人）。
        foreach (['project_id' => 'project', 'party_a_customer_id' => 'customer', 'party_a_supplier_id' => 'supplier', 'party_b_customer_id' => 'customer', 'supplier_id' => 'supplier'] as $fk => $tbl) {
            $fkVal = (int)($data[$fk] ?? 0);
            if ($fkVal <= 0) {
                continue;
            }
            $row = Db::name($tbl)->where('id', $fkVal)->where('is_deleted', 0)->find();
            if (!$row) {
                return json_error('关联的' . ($tbl === 'project' ? '项目' : ($tbl === 'customer' ? '客户' : '供应商')) . '不存在或已被删除');
            }
            // v2.45.0：客户统一访问判定——公海 / 数据范围 / 显式共享(用户级/部门级) / 集团祖先可见
            // 解决「客户归 A 但 B 也要关联签合同」：共享放行后 B 可关联 A 的客户，不再被迫手输快照
            if ($tbl === 'customer') {
                if (!\app\common\logic\CustomerLogic::canAccessCustomer($this->userId, $row, (int)($this->user['dept_id'] ?? 0))) {
                    return json_error('无权关联该客户（可联系客户负责人申请共享）', 403);
                }
                continue;
            }
            // 公海客户/公海供应商（owner_id=0）放行；其余校验数据范围
            if ((int)($row['owner_id'] ?? 0) !== 0
                && !\app\common\logic\AuthLogic::canAccessRecord((int)($row['owner_id'] ?? 0), $row['dept_id'] ?? null)) {
                return json_error('无权关联该' . ($tbl === 'project' ? '项目' : '供应商'), 403);
            }
        }
        // parent_id：额外校验目标确为框架合同（parent_id=0）且当前用户可访问
        if (!empty($data['parent_id'])) {
            $parent = Db::name('contract')->where('id', (int)$data['parent_id'])->where('is_deleted', 0)->find();
            if (!$parent || (int)($parent['parent_id'] ?? 0) !== 0) {
                return json_error('关联的框架合同不存在或不是框架合同');
            }
            if (!\app\common\logic\AuthLogic::canAccessRecord((int)($parent['owner_id'] ?? 0), $parent['dept_id'] ?? null)) {
                return json_error('无权关联该框架合同', 403);
            }
        }

        // P1-C：结构化字段（由模板 fields_schema 驱动，前端收集为 JSON 提交）
        $rawCustom = trim((string)$this->getPost('custom_fields', ''));
        if ($rawCustom === '' || $rawCustom === 'null') {
            $data['custom_fields'] = '{}';
        } else {
            $decoded = json_decode($rawCustom, true);
            if (!is_array($decoded)) {
                return json_error('结构化字段格式错误');
            }
            $data['custom_fields'] = json_encode($decoded, JSON_UNESCAPED_UNICODE);
        }

        // 新建合同必填校验（除关键词外，关键字段均不可为空；编辑旧数据不追溯，避免卡住历史合同）
        if ($id === 0) {
            if ($data['our_company_id'] <= 0) {
                return json_error('请选择签约主体');
            }
            if ($data['party_a_name'] === '') {
                return json_error('请输入甲方名称');
            }
            if ($data['party_b_name'] === '') {
                return json_error('请输入乙方名称');
            }
            if (empty($data['effective_date'])) {
                return json_error('请选择生效日期');
            }
            if (empty($data['expiry_date'])) {
                return json_error('请选择到期日期');
            }
            // v2.46.0：签约方强制关联档案——对方侧（非我方侧）必须关联已登记客户/供应商，
            // 防自由输入绕过客户查重/共享/集团治理；名称须与档案一致（防伪造）。编辑旧数据不追溯。
            $ourSide  = $this->getPost('our_side', 'B');
            $oppoSide = $ourSide === 'A' ? 'B' : 'A';
            if ($oppoSide === 'A') {
                $oppoCid = (int)$data['party_a_customer_id'];
                $oppoSid = (int)$data['party_a_supplier_id'];
                if ($oppoCid <= 0 && $oppoSid <= 0) {
                    return json_error('请选择已登记的甲方客户或供应商（未登记可点「快速新建」，勿手输名称）');
                }
                if ($oppoCid > 0) {
                    $row = Db::name('customer')->where('id', $oppoCid)->where('is_deleted', 0)->find();
                    if (!$row || trim((string)$row['name']) !== trim((string)$data['party_a_name'])) {
                        return json_error('甲方名称与所选客户档案不一致，请重新选择');
                    }
                } else {
                    $row = Db::name('supplier')->where('id', $oppoSid)->where('is_deleted', 0)->find();
                    if (!$row || trim((string)$row['name']) !== trim((string)$data['party_a_name'])) {
                        return json_error('甲方名称与所选供应商档案不一致，请重新选择');
                    }
                }
            } else {
                $oppoCid = (int)$data['party_b_customer_id'];
                $oppoSid = (int)$data['supplier_id'];
                if ($oppoCid <= 0 && $oppoSid <= 0) {
                    return json_error('请选择已登记的乙方客户或供应商（未登记可点「快速新建」，勿手输名称）');
                }
                if ($oppoCid > 0) {
                    $row = Db::name('customer')->where('id', $oppoCid)->where('is_deleted', 0)->find();
                    if (!$row || trim((string)$row['name']) !== trim((string)$data['party_b_name'])) {
                        return json_error('乙方名称与所选客户档案不一致，请重新选择');
                    }
                } else {
                    $row = Db::name('supplier')->where('id', $oppoSid)->where('is_deleted', 0)->find();
                    if (!$row || trim((string)$row['name']) !== trim((string)$data['party_b_name'])) {
                        return json_error('乙方名称与所选供应商档案不一致，请重新选择');
                    }
                }
            }
        }

        if (empty($data['title'])) {
            return json_error('请输入合同标题');
        }
        if (empty(trim(strip_tags($data['content'])))) {
            return json_error('请输入合同概要');
        }
        $fileUrl = $this->getPost('file_url', '');
        $attachments = json_decode($fileUrl, true) ?: [];
        if (empty($attachments)) {
            return json_error('请上传至少一个合同附件');
        }
        // v2.44.1 P1（file_url IDOR 防护）：每个 url 必须来自本人本会话上传或合同原有附件，
        // 防止把他人文件路径写进自己合同、借 canPreview「命中自己合同即放行」越权读取。
        if (!$this->validateAttachmentUrls($attachments, $id)) {
            return json_error('附件来源校验失败，请重新上传附件后保存', 403);
        }

        // 业务校验（CR-49/20/55/48）：标题长度、日期先后、金额合法性
        if (mb_strlen($data['title']) > 255) {
            return json_error('合同标题过长（最多 255 个字符）');
        }
        if (!empty($data['effective_date']) && !empty($data['expiry_date'])
            && strtotime($data['effective_date']) >= strtotime($data['expiry_date'])) {
            return json_error('生效日期必须早于到期日期');
        }
        // 交易类合同（trade_attr=1）金额须为正；非交易类已在上方强制为 0
        if ($tradeAttr === 1) {
            if ($data['amount'] < 0) {
                return json_error('合同金额不能为负数');
            }
            if ($data['amount'] == 0) {
                return json_error('交易类合同金额不能为 0，请填写正确金额');
            }
            // P2：金额上限校验——超大金额（1e300 级）会致浮点溢出/失真，且回款累计比较失真
            if ($data['amount'] > 999999999.99) {
                return json_error('合同金额超出允许范围（最大 9.99 亿）');
            }
        }

        // P1-3（deep review）：重复合同检测 — 标题 + 甲乙双方 + 金额完全一致的未删除合同视为重复，
        // 提示既存合同号，拦截误重复创建（防呆而非强禁，用户确认确系新合同可改标题/金额后重提）。
        $dup = ContractLogic::findDuplicate($data, $id);
        if ($dup) {
            return json_error('检测到重复合同：与「' . $dup['contract_no'] . '」《' . $dup['title'] . '》的标题、甲乙双方与金额完全一致，请确认是否重复创建');
        }

        try {
            if ($id) {
                $contract = ContractLogic::accessible($id);
                if (!$contract || !in_array($contract['status'], [ContractLogic::STATUS_DRAFT, ContractLogic::STATUS_REJECTED])) {
                    return json_error('当前状态不可编辑或无权限');
                }
                $data['updater_id'] = $this->userId;
                ContractLogic::update($id, $data);
                AuditService::log($this->userId, 'update', 'contract', $id);
            } else {
                $data['creator_id'] = $this->userId;
                $id = ContractLogic::create($data);
                AuditService::log($this->userId, 'create', 'contract', $id);
            }
            // M10 客户生命周期：合同关联到客户（乙方客户，或甲方客户——我方=乙方时对方为客户）
            // → 生命周期升为成交(ACTIVE)；甲方乙方去重后统一提升（v2.45 对称修复：原仅乙方客户触发）
            $lifecycleCustIds = array_values(array_unique(array_filter([
                (int)($data['party_a_customer_id'] ?? 0),
                (int)($data['party_b_customer_id'] ?? 0),
            ])));
            foreach ($lifecycleCustIds as $cid) {
                CustomerLogic::promoteToActive($cid);
            }
            return json_success(['id' => $id], '保存成功');
        } catch (\Throwable $e) {
            // REV-14：异常信息写入日志，对外仅返回友好提示，避免 SQL/路径等敏感信息泄露
            Log::error('合同保存失败', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return json_error('保存失败，请稍后重试或联系管理员');
        }
    }

    /** 合同详情 */
    public function detail($id)
    {
        $this->requirePermission('contract:view');
        $contract = ContractLogic::accessible((int)$id);
        if (!$contract) {
            throw new \think\exception\HttpException(404, '合同不存在或无权限查看');
        }

        // 关联审批记录（CR-09：含已撤回/驳回实例及其节点意见，详情可查看完整历史）
        $approvals = \app\common\logic\ApprovalQueryService::getApprovalHistory((int)$id);

        // 自定义结构化字段 schema（仅由合同自身 custom_fields 决定标签顺序）
        $customSchema = [];
        $customValues = [];
        $rawCv = trim((string)($contract['custom_fields'] ?? ''));
        if ($rawCv !== '' && $rawCv !== '{}') {
            $dv = json_decode($rawCv, true);
            if (is_array($dv)) $customValues = $dv;
        }

        View::assign('contract', $contract);
        View::assign('approvals', $approvals);

        // v2.38.14：甲乙方往来摘要（360 能力内嵌 PC 详情）——乙方客户/关联供应商，与移动端同源
        // v2.46.0：甲方供应商（我方=乙方、对方=甲方为供应商）同样展示往来摘要
        $party360 = [];
        if ((int)($contract['party_a_customer_id'] ?? 0) > 0) {
            $party360['customer_a'] = PartyLogic::getSummary('customer', (int)$contract['party_a_customer_id']);
        }
        if ((int)($contract['party_a_supplier_id'] ?? 0) > 0) {
            $party360['supplier_a'] = PartyLogic::getSummary('supplier', (int)$contract['party_a_supplier_id']);
        }
        if ((int)($contract['party_b_customer_id'] ?? 0) > 0) {
            $party360['customer'] = PartyLogic::getSummary('customer', (int)$contract['party_b_customer_id']);
        }
        if ((int)($contract['supplier_id'] ?? 0) > 0) {
            $party360['supplier'] = PartyLogic::getSummary('supplier', (int)$contract['supplier_id']);
        }
        View::assign('party360', $party360);
        View::assign('custom_schema', $customSchema);
        View::assign('custom_values', $customValues);
        View::assign('actions', ContractLogic::getAvailableActions($contract['status']));

        // v2.28.2：视图层 Db::name 下沉 — 签约主体与建议审批流名由 Logic 取数后注入视图
        $flowName = '';
        if (!empty($contract['flow_id'])) {
            $flow = \app\common\logic\ApprovalQueryService::getFlowById((int)$contract['flow_id']);
            $flowName = $flow['name'] ?? '';
        }
        $company = null;
        if (!empty($contract['our_company_id'])) {
            $company = CompanyLogic::getById((int)$contract['our_company_id']);
        }
        View::assign('flowName', $flowName);
        View::assign('company', $company);
        // F1：发票申请按钮按 invoice:apply/create 权限显示（普通用户可申请，财务/经理可申请+开票）
        View::assign('can_apply_invoice', $this->hasPermission('invoice:apply') || $this->hasPermission('invoice:create'));
        // UX 门控：删除按钮按 contract:delete 权限渲染（后端 delete 已有守卫，此处仅收敛视图入口）
        View::assign('can_delete', $this->hasPermission('contract:delete'));
        // v2.47.2：超管标志注入详情页——审批中合同（僵尸审批）允许超管强制删除，前端放开删除按钮
        View::assign('is_super_admin', $this->isSuperAdmin());
        // UX 门控：提交审批按钮按 approval:submit 权限渲染（后端 Approval::create 已有守卫）
        View::assign('can_submit_approval', $this->hasPermission('approval:submit'));
        // UX 门控：归档/续约按钮按后端守卫同口径渲染（archive→contract:edit，renew→contract:create）
        View::assign('can_edit', $this->hasPermission('contract:edit'));
        View::assign('can_renew', $this->hasPermission('contract:create'));
        // UX 门控：回款登记/确认/撤销/删除按钮按 payment:create 权限渲染（与后端 Payment 守卫、移动端 can_payment 同口径）
        View::assign('can_pay', $this->hasPermission('payment:create'));
        // UX 门控：发票开票/红冲/作废/删除按钮按 invoice:create 权限渲染（与后端 Invoice 守卫同口径）
        View::assign('can_issue', $this->hasPermission('invoice:create'));
        // H6c：合同详情「申请开票」复用 InvoiceFormConfig 配置化表单（字段/联动/自定义由后台「发票表单」设计器统一维护，
        // 与独立入口 /invoice-apply 单源一致；开票主体默认合同主体，关联合同固定为当前合同）
        $invCompanies = \app\common\logic\CompanyLogic::getListWithDefault();
        $invCustomers = \app\common\logic\CustomerLogic::getInvoiceOptions();
        View::assign('apply_fields', \app\common\form\InvoiceFormConfig::pcRender([], ['companies' => $invCompanies, 'customers' => $invCustomers]));
        View::assign('invoice_form_rules', \app\common\form\InvoiceFormConfig::rules());
        View::assign('invoice_customers', $invCustomers);
        // P2-15【M-A5】视图 timeline 注入：原 detail.php 顶层直查 ContractTimelineService（3 条查询）下沉控制器
        View::assign('timeline', \app\common\service\ContractTimelineService::getTimeline((int)$id));
        return View::fetch();
    }

    /** 合同编辑页 */
    public function edit($id)
    {
        return $this->create($id, 'contract/create');
    }

    /** AJAX: 删除合同 */
    public function delete()
    {
        $this->requirePermission('contract:delete');
        $id = (int)$this->getPost('id', 0);
        // 越权防护：仅可删除自己归属/部门、且为草稿状态的合同
        $contract = ContractLogic::findRaw($id);
        if (!$contract || !\app\common\logic\AuthLogic::canAccessRecord($contract['owner_id'], $contract['dept_id'] ?? 0)) {
            return json_error('无权删除该合同');
        }
        // CR-15：删除前关联校验，存在进行中审批/回款/发票则拒绝并提示具体阻塞项
        $blockers = \app\common\logic\ContractLogic::deleteBlockers($id);
        if (!empty($blockers)) {
            // v2.47.2：超管强制删除——审批人/提交人已失效（被禁用/删除）导致无法撤回/审批的
            // 僵尸审批中合同，允许超管终结其审批实例（置 RECALLED、合同回草稿）后删除（测试数据清理出口）；
            // 回款/发票/子合同等业务关联仍拦截，不因超管放行。
            if ($this->isSuperAdmin() && \app\common\logic\ContractLogic::forceTerminateApproval($id)) {
                $blockers = \app\common\logic\ContractLogic::deleteBlockers($id);
            }
            if (!empty($blockers)) {
                return json_error('删除失败：' . implode('；', $blockers) . '。请先处理相关关联数据');
            }
        }
        if (ContractLogic::softDelete($id)) {
            AuditService::log($this->userId, 'delete', 'contract', $id);
            return json_success(null, '已删除');
        }
        return json_error('删除失败，当前状态不可删除');
    }

    /** AJAX: 合同搜索（scope=framework 时仅返回框架合同，供「关联框架合同」搜索选择器使用） */
    public function search()
    {
        $this->requirePermission('contract:view');
        $keyword = $this->getParam('q', '');
        $scope   = $this->getParam('scope', '');
        $list = ContractLogic::search($keyword, $scope);
        return json_success($list);
    }

    /** AJAX: 联合搜索客户+供应商（用于合同创建时选择甲乙方） */
    public function partySearch()
    {
        // 合同创建时选择甲方/乙方，允许合同或供应商模块查看权限
        $this->requireAnyPermission(['contract:view', 'supplier:view']);
        $q = trim($this->getParam('q', ''));
        if (mb_strlen($q) < 1) {
            // 无关键词时返回所有"本公司"客户
            $selfCustomers = CustomerLogic::searchParty('');
            return json_success($selfCustomers);
        }

        $customers = CustomerLogic::searchParty($q);
        $suppliers = SupplierLogic::searchParty($q);

        // 标记类型中文
        foreach ($customers as &$c) { $c['type_name'] = '客户'; }
        foreach ($suppliers as &$s) { $s['type_name'] = '供应商 · ' . ($s['type_name'] ?: '其他'); }

        // 本公司客户排最前
        return json_success(array_merge($customers, $suppliers));
    }

    /** AJAX: 手动状态变更 */
    public function statusTransition()
    {
        $this->requirePermission('contract:edit');
        $id        = (int)$this->getPost('id', 0);
        $newStatus = $this->getPost('status', '');

        // 越权防护：手动状态变更仅允许合同归属人/创建人或管理员
        $contract = ContractLogic::findRaw($id);
        if (!$contract) {
            return json_error('合同不存在');
        }
        // P3 复审加固：草稿/驳回/审批中态不可手动改状态（须由提交/撤回/审批流推进），
        // 防止同部门人员绕过审批自批（如 PENDING_APPROVAL→APPROVED、DRAFT→APPROVED）
        if (in_array($contract['status'], [
            ContractLogic::STATUS_DRAFT, ContractLogic::STATUS_REJECTED, ContractLogic::STATUS_PENDING_APPROVAL,
        ], true)) {
            return json_error('该状态不可手动变更，请通过审批流程操作');
        }
        if (!\app\common\logic\AuthLogic::canAccessRecord($contract['owner_id'], $contract['dept_id'] ?? 0)) {
            return json_error('无权操作该合同状态');
        }

        // P2-10：手动终结（完成/终止/到期/归档）前校验——存在逾期未结回款时禁止，
        // 防止带逾期财务数据直接收尾；质保金等正常 PENDING 待收不受影响
        if (in_array($newStatus, [
            ContractLogic::STATUS_COMPLETED, ContractLogic::STATUS_TERMINATED,
            ContractLogic::STATUS_EXPIRED, ContractLogic::STATUS_ARCHIVED,
        ], true)) {
            if (\app\common\logic\PaymentLogic::hasOverdue($id)) {
                return json_error('该合同存在逾期未结回款，请先处理后手动终结');
            }
        }

        if (ContractLogic::transitionStatus($id, $newStatus, $this->userId)) {
            AuditService::log($this->userId, 'status_change', 'contract', $id, ['to' => $newStatus]);
            return json_success(null, '状态已更新');
        }
        return json_error('状态变更失败');
    }

    /**
     * 导出合同（CSV）
     * CR-42：原 exportExcel 实际输出 CSV，已重命名为 exportCsv；
     * REV-45：改为边 chunk 边写入临时流并流式输出，避免超大导出把全部内容拼接进内存。
     */
    public function exportCsv()
    {
        $this->requirePermission('contract:export');
        // P2：大表导出不限执行时间，防超时中断（CLI/FPM 默认 max_execution_time 可能截断导出）
        @set_time_limit(0);
        $status    = $this->getParam('status', '');
        $dateStart = $this->getParam('date_start', '');
        $dateEnd   = $this->getParam('date_end', '');

        $headers = ["合同编号", "标题", "分类", "状态", "金额", "甲方", "乙方", "生效日期", "到期日期", "创建日期"];
        $total   = 0;

        // REV-45：CSV 写入临时流（内存不足时自动落盘），最终流式吐出，内存峰值恒定
        $tmp = tempnam(sys_get_temp_dir(), 'csv_');
        $fp  = fopen($tmp, 'w');
        fwrite($fp, "\xEF\xBB\xBF" . implode(',', array_map(function ($h) {
            return '"' . str_replace('"', '""', $h) . '"';
        }, $headers)) . "\n");

        $total = ContractLogic::eachExportRow($status, $dateStart, $dateEnd, function ($row) use ($fp) {
            // P2 公式注入中和：fputcsv 前对 = + - @ 开头值前置 '（export_safe_cell，common.php）
            fputcsv($fp, array_map('export_safe_cell', array_values($row)));
        });
        fclose($fp);

        $filename = $status === ContractLogic::STATUS_ARCHIVED
            ? 'archived_contracts_' . date('Ymd') . '.csv'
            : 'contracts_' . date('Ymd') . '.csv';

        // 高危操作留痕：记录导出的范围与条数
        AuditService::log($this->userId, 'export', 'contract', 0, [
            'count'      => $total,
            'status'     => $status ?: 'ALL',
            'date_start' => $dateStart,
            'date_end'   => $dateEnd,
        ]);

        return new StreamedFileResponse($tmp, 200, [
            'Content-Type'        => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * 导出合同（XLSX，REV-27：新增 Excel 格式导出，无第三方依赖）
     */
    public function exportXlsx()
    {
        $this->requirePermission('contract:export');
        // P2：大表导出不限执行时间，防超时中断（与 exportCsv 口径一致）
        @set_time_limit(0);
        $status    = $this->getParam('status', '');
        $dateStart = $this->getParam('date_start', '');
        $dateEnd   = $this->getParam('date_end', '');

        $headers = ["合同编号", "标题", "分类", "状态", "金额", "甲方", "乙方", "生效日期", "到期日期", "创建日期"];
        // P2-14【M-A4】流式导出：回调式生产者（chunk 边查边喂 sink），替代原「全量收集 $rows 数组」驻留，
        // 与 CSV 导出共用 eachExportRow 的查询/分页逻辑，避免重复实现
        $total = 0;
        $producer = function (callable $sink) use ($status, $dateStart, $dateEnd, &$total) {
            $total = ContractLogic::eachExportRow($status, $dateStart, $dateEnd, function ($row) use ($sink) {
                $sink(array_values($row));
            });
        };

        $filename = $status === 'ARCHIVED'
            ? 'archived_contracts_' . date('Ymd') . '.xlsx'
            : 'contracts_' . date('Ymd') . '.xlsx';

        // exportFrom 内部完整消费生产者后返回响应，此时 $total 已收敛，审计日志在文件生成后记录
        $resp = XlsxHelper::exportFrom($headers, $producer, $filename);
        AuditService::log($this->userId, 'export', 'contract', 0, [
            'format'     => 'xlsx',
            'count'      => $total,
            'status'     => $status ?: 'ALL',
            'date_start' => $dateStart,
            'date_end'   => $dateEnd,
        ]);

        return $resp;
    }

    // ====================================================================
    // REV-28：批量操作（批量归档 / 批量删除）
    // ====================================================================

    /**
     * AJAX: 批量归档合同
     * 仅 EXECUTING / COMPLETED 状态可归档，后端逐条校验权限与状态。
     */
    public function batchArchive()
    {
        $this->requirePermission('contract:edit');
        $ids = $this->getPost('ids', '');
        if (empty($ids)) return json_error('请选择合同');
        $idArr = array_map('intval', array_filter(explode(',', $ids)));

        // P3b-6：一次性批量预加载（含数据范围过滤），避免循环内 N+1 查询（P1-1：下沉 ContractLogic::batchLoad）
        $contractMap = ContractLogic::batchLoad($idArr);

        $success = 0;
        $skipped = 0;
        Db::transaction(function () use ($idArr, $contractMap, &$success, &$skipped) {
            foreach ($idArr as $id) {
                $contract = $contractMap[$id] ?? null;
                if (!$contract) { $skipped++; continue; }
                if (!in_array($contract['status'], [ContractLogic::STATUS_EXECUTING, ContractLogic::STATUS_COMPLETED])) { $skipped++; continue; }
                if (ContractLogic::transitionStatus($id, ContractLogic::STATUS_ARCHIVED, $this->userId)) {
                    AuditService::log($this->userId, 'batch_archive', 'contract', $id, ['from' => $contract['status']]);
                    $success++;
                } else {
                    $skipped++;
                }
            }
        });
        return json_success(['count' => $success, 'skipped' => $skipped], "已归档 {$success} 份，跳过 {$skipped} 份");
    }

    /**
     * AJAX: 批量删除合同（软删除）
     * 可删状态与 softDelete 同口径：DRAFT / REJECTED / ARCHIVED / COMPLETED / EXPIRED / TERMINATED，
     * 后端逐条校验权限、状态与关联阻塞项。
     */
    public function batchDelete()
    {
        // 与单条删除 delete() 守卫同口径：批量删除要求 contract:delete（原 contract:edit 过宽，无删除权限角色可绕过）
        $this->requirePermission('contract:delete');
        $ids = $this->getPost('ids', '');
        if (empty($ids)) return json_error('请选择合同');
        $idArr = array_map('intval', array_filter(explode(',', $ids)));

        // P3b-6：一次性批量预加载（含数据范围过滤），避免循环内 N+1 查询（P1-1：下沉 ContractLogic::batchLoad）
        $contractMap = ContractLogic::batchLoad($idArr);

        $success = 0;
        $skipped = 0;
        Db::transaction(function () use ($idArr, $contractMap, &$success, &$skipped) {
            foreach ($idArr as $id) {
                $contract = $contractMap[$id] ?? null;
                if (!$contract) { $skipped++; continue; }
                // v2.47.2：超管批量删除放行 PENDING_APPROVAL（经 forceTerminateApproval 终结审批后删除，测试数据清理出口）
                $statusOk = in_array($contract['status'], ['DRAFT', 'REJECTED', 'ARCHIVED', 'COMPLETED', 'EXPIRED', 'TERMINATED'])
                    || ($this->isSuperAdmin() && $contract['status'] === 'PENDING_APPROVAL');
                if (!$statusOk) { $skipped++; continue; }
                // 关联校验：审批进行中 / 回款未撤销不可删除
                if (!empty(ContractLogic::deleteBlockers($id))) {
                    // v2.47.2：超管强制删除——同单条 delete()，终结僵尸审批实例后放行；其余业务关联仍跳过
                    if (!$this->isSuperAdmin() || !ContractLogic::forceTerminateApproval($id)) {
                        $skipped++; continue;
                    }
                }
                if (ContractLogic::softDelete($id)) {
                    AuditService::log($this->userId, 'batch_delete', 'contract', $id, ['status' => $contract['status']]);
                    $success++;
                } else {
                    $skipped++;
                }
            }
        });
        return json_success(['count' => $success, 'skipped' => $skipped], "已删除 {$success} 份，跳过 {$skipped} 份");
    }

    /** AJAX: 高频关键词——仅取当前登录用户自己创建过的合同关键词，按词频降序返回 TopN，供新建/编辑页快速选填 */
    public function hotKeywords()
    {
        // 有创建/编辑权限即可使用（与关键词输入控件所在页面权限一致）
        $this->requireAnyPermission(['contract:create', 'contract:edit']);
        $limit = (int)request()->get('limit', 10);
        if ($limit <= 0 || $limit > 50) {
            $limit = 10;   // 钳制上限，防止滥用拉全量
        }
        $list = ContractLogic::getHotKeywords($this->userId, $limit);
        return json_success($list);
    }

    /** AJAX: 上传合同附件（Word/PDF/图片） */
    public function upload()
    {
        $this->requireAnyPermission(['contract:create', 'contract:edit']);
        // 2026-08-05：file() 在 $_FILES 异常（超限/部分上传/无临时目录等）时抛 Exception，避免 500「服务器内部错误」。
        // 按错误码区分提示：1/2=大小超限，其余（部分上传/无临时目录等）=通用失败。
        try {
            $file = request()->file('file');
        } catch (\Throwable $e) {
            $code = $e->getCode();
            if (in_array($code, [1, 2], true)) {
                return json_error('文件过大，超出服务器接收上限，请压缩后再上传');
            }
            return json_error('文件上传失败（服务器接收异常），请重试');
        }
        if ($file === null) {
            return json_error('请选择文件');
        }
        // 防御：multiple 多文件时 file() 返回 UploadedFile 数组，对其调用方法会触发 Error → 500
        if (!$file instanceof \think\file\UploadedFile) {
            return json_error('一次仅支持上传一个文件');
        }

        // P1-3（M9）：先取得真实临时路径，供后续 finfo 真实类型校验与大小校验使用；
        // 原代码在 finfo->file($tmpPath) 之后才赋值 $tmpPath，会导致 TypeError / 上传 500。
        $tmpPath = $file->getRealPath() ?: $file->getPathname();

        // 大小限制 20MB（先校验大小，避免超大文件做无谓的 MIME 解析）
        $maxSize = 20 * 1024 * 1024;
        $size    = @filesize($tmpPath);
        if ($size === false) {
            // 临时文件不可读（已被清理/权限异常）
            return json_error('上传临时文件不可读，请重试');
        }
        if ($size > $maxSize) {
            @unlink($tmpPath); // 2026-08-05：超限自动清理 PHP 上传临时文件，避免残留
            return json_error('文件过大，最大 20MB');
        }

        // REV-35：真实文件类型校验——用 finfo 读取文件内容真实 MIME（而非信任客户端扩展名），
        // 仅允许白名单内的真实文档/图片类型；保存时扩展名以真实类型为准，杜绝「改扩展名伪装」上传。
        // 判定逻辑收敛为纯函数 resolve_attachment_ext()（app/common.php），供单元测试直接覆盖。
        try {
            $finfo    = new \finfo(FILEINFO_MIME_TYPE);
            $realMime = $finfo->file($tmpPath); // 此时 $tmpPath 已正确赋值，finfo 真实 MIME→扩展名白名单校验真正生效
        } catch (\Throwable $e) {
            // 2026-08-05：finfo 扩展缺失/异常时 new finfo 抛 Error → 500，需捕获
            @unlink($tmpPath);
            Log::error('finfo 不可用，拒绝上传', ['error' => $e->getMessage()]);
            return json_error('服务器文件校验组件不可用，请联系管理员');
        }
        $origExt = strtolower(pathinfo($file->getOriginalName(), PATHINFO_EXTENSION));
        $ext     = resolve_attachment_ext($realMime, $origExt);
        if ($ext === null) {
            @unlink($tmpPath); // 2026-08-05：类型被拒同样清理临时文件（含 $realMime 为空/不可识别）
            return json_error('文件真实类型不被支持（' . ($realMime ?: '未知') . '），上传被拒绝');
        }

        try {
            $saveName = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $subDir   = date('Ym');                                               // 按月份分目录存储
            $savePath = app()->getRootPath() . 'public/uploads/contracts/' . $subDir;

            // 确保上传目录存在（月初/首次部署时目录尚未创建）
            if (!is_dir($savePath)) {
                mkdir($savePath, 0755, true);
            }

            $file->move($savePath, $saveName);

            $url  = '/uploads/contracts/' . $subDir . '/' . $saveName;
            // v2.44.1 P0-1：存储型 XSS 修复——原始文件名会存入 DB 并回显到视图 <script>，须净化尖括号/引号并去除空字节与控制字符
            $name = preg_replace('/[<>"]/u', '', (string)$file->getOriginalName());
            $name = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $name);
            $name = trim($name);
            if ($name === '') {
                $name = 'attachment.' . $ext; // 净化后为空则回退为安全名
            }
            $size = filesize($savePath . DIRECTORY_SEPARATOR . $saveName);

            // v2.44.1 P1（file_url IDOR 防护）：上传成功后登记到「本用户允许集」，
            // save() 校验 file_url 每项 url 必须命中该集合（或合同原有附件），
            // 杜绝攻击者把他人文件路径写进自己合同的 file_url 再借 canPreview 越权读取。
            $this->rememberUploadedUrl($url);

            return json_success([
                'url'  => $url,
                'name' => $name,
                'size' => $size,
            ], '上传成功');
        } catch (\Throwable $e) {
            @unlink($tmpPath); // 2026-08-05：move 失败时临时文件可能残留，一并清理
            Log::error('合同附件上传失败', ['error' => $e->getMessage()]);
            return json_error('文件上传失败，请稍后重试');
        }
    }

    /**
     * v2.44.1 P1（file_url IDOR 防护）：登记「本用户本次上传的 url」到会话级允许集。
     * 允许集存 Session（按用户会话隔离），上传后 2 小时内有效——覆盖「上传→保存」整条编辑链路。
     */
    private function rememberUploadedUrl(string $url): void
    {
        $key = 'uploaded_urls_' . $this->userId;
        $set = Session::get($key, []);
        if (!is_array($set)) $set = [];
        $set[$url] = time();
        Session::set($key, $set);
    }

    /**
     * v2.44.1 P1（file_url IDOR 防护）：校验 file_url JSON 数组中的每个 url 是否允许写入。
     * 判定：① 命中本用户会话允许集（本人上传）；② 或为编辑时合同原有的附件 url（未替换的旧附件）。
     * 两者皆不命中（含把他人文件路径直接写入）→ 拒绝，防止借 canPreview「命中自己合同即放行」越权读取。
     */
    private function validateAttachmentUrls(array $items, int $contractId): bool
    {
        $allowed = Session::get('uploaded_urls_' . $this->userId, []);
        if (!is_array($allowed)) $allowed = [];
        // 编辑场景：原合同已存在的附件 url 视为允许（未替换的旧附件）
        $legacy = [];
        if ($contractId > 0) {
            $old = Db::name('contract')->where('id', $contractId)->value('file_url');
            foreach (json_decode((string)$old, true) ?: [] as $it) {
                if (!empty($it['url'])) $legacy[$it['url']] = true;
            }
        }
        foreach ($items as $it) {
            $url = (string)($it['url'] ?? '');
            if ($url === '') {
                return false;
            }
            if (isset($allowed[$url]) || isset($legacy[$url])) {
                continue;
            }
            // 未知来源附件路径：拒绝（防 IDOR）
            return false;
        }
        return true;
    }

    /**
     * v2.38.3 合同续约（M11 重做）：真正生成续约草案
     * - 校验原合同状态（已签/执行中/已归档/已到期）方可续约
     * - 复制核心字段经 ContractLogic::create 生成 DRAFT 草案（renewed_from=原合同、parent_id=原合同）
     * - 原合同标记 renewed_to 指向新草案，二者建立续约关联
     * - 返回新草案 id 与编辑链接，由前端跳转走正常「编辑→提交审批」流程
     */
    public function renew($id)
    {
        $this->requirePermission('contract:create');
        $id = (int)$id;
        $contract = ContractLogic::getDetail($id);
        if (!$contract || !in_array($contract['status'], [ContractLogic::STATUS_SIGNED, ContractLogic::STATUS_EXECUTING, ContractLogic::STATUS_ARCHIVED, ContractLogic::STATUS_EXPIRED])) {
            return json_error('当前合同状态不支持续约');
        }
        // 越权防护（全量审查 2026-08-01 发现）：续约会克隆原合同全部字段并覆写原合同 renewed_to，
        // 必须校验数据范围/归属，否则任一 contract:create 用户可续约他人合同。
        if (!AuthLogic::canAccessRecord((int)($contract['owner_id'] ?? 0), $contract['dept_id'] ?? null)) {
            return json_error('无权续约该合同', 403);
        }
        // M5 修复：防重复续约——已生成续约草案则拒绝，避免并发/重复续约覆盖原合同 renewed_to 关联
        if (!empty($contract['renewed_to'])) {
            return json_error('该合同已生成续约草案（#' . (int)$contract['renewed_to'] . '），请勿重复续约');
        }

        // 预填核心字段，分类/对方/金额/主体/概要保持一致，生成可编辑草案
        $data = [
            'title'               => $contract['title'] . '（续约）',
            'category'            => $contract['category'] ?? 'SERVICE',
            'amount'              => (float)($contract['amount'] ?? 0),
            'party_a_name'        => $contract['party_a_name'] ?? '',
            'party_a_contact'     => $contract['party_a_contact'] ?? '',
            'party_a_phone'       => $contract['party_a_phone'] ?? '',
            'party_a_customer_id' => (int)($contract['party_a_customer_id'] ?? 0),
            'party_a_supplier_id'=> (int)($contract['party_a_supplier_id'] ?? 0),
            'party_b_customer_id' => (int)($contract['party_b_customer_id'] ?? 0),
            'party_b_name'        => $contract['party_b_name'] ?? '',
            'party_b_contact'     => $contract['party_b_contact'] ?? '',
            'party_b_phone'       => $contract['party_b_phone'] ?? '',
            'party_b_credit_code' => $contract['party_b_credit_code'] ?? '',
            'supplier_id'         => (int)($contract['supplier_id'] ?? 0),
            'custom_fields'       => $contract['custom_fields'] ?? '{}',
            'flow_id'             => (int)($contract['flow_id'] ?? 0),
            'effective_date'      => $contract['effective_date'] ?? null,
            'expiry_date'         => $contract['expiry_date'] ?? null,
            'content'             => $contract['content'] ?? '',
            'content_plain'       => strip_tags($contract['content'] ?? ''),
            'file_url'            => $contract['file_url'] ?? '',
            'keywords'            => $contract['keywords'] ?? '',
            'our_company_id'      => (int)($contract['our_company_id'] ?? 0),
            'direction'           => $contract['direction'] ?? 'sales',
            'trade_attr'          => (int)($contract['trade_attr'] ?? 1),
            'project_id'          => (int)($contract['project_id'] ?? 0),
            'owner_id'            => $this->userId,
            'dept_id'             => $this->user['dept_id'] ?? 0,
            'parent_id'           => $id,   // 继承原合同层级
            'renewed_from'        => $id,   // 续约来源标记
            'creator_id'          => $this->userId,
        ];

        // M5 修复：草案创建 + 原合同 renewed_to 标记 + 审计日志包裹事务，任一步失败整体回滚，杜绝孤儿续约草案
        $newId = \think\facade\Db::transaction(function () use ($data, $id, $contract) {
            $newId = ContractLogic::create($data);
            ContractLogic::update($id, ['renewed_to' => $newId]);
            AuditService::log($this->userId, 'renew', 'contract', $newId, '由合同#' . $id . '《' . ($contract['title'] ?? '') . '》续约生成');
            return $newId;
        });

        return json_success([
            'id'  => $newId,
            'url' => '/contract/' . $newId . '/edit',
        ], '续约草案已生成，请完善信息后提交审批');
    }
}
