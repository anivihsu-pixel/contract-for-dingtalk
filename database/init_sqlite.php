<?php
// +----------------------------------------------------------------------
// | SQLite 数据库初始化脚本
// +----------------------------------------------------------------------

define('ROOT_PATH', __DIR__ . '/../');
require ROOT_PATH . 'vendor/autoload.php';

// Load .env（phpdotenv v5+：构造函数已废弃，须用 createImmutable/createMutable）
if (is_file(ROOT_PATH . '.env')) {
    $dotenv = \Dotenv\Dotenv::createImmutable(ROOT_PATH);
    $dotenv->load();
}

$app = new \think\App(ROOT_PATH);
$app->initialize();

use think\facade\Db;

// Create runtime data directory
$dataDir = $app->getRuntimePath() . 'data';
if (!is_dir($dataDir)) mkdir($dataDir, 0755, true);

$dbFile = $dataDir . '/contract.db';
if (file_exists($dbFile)) {
    echo "Database already exists at $dbFile\n";
    echo "Delete it first to re-initialize: rm $dbFile\n";
    exit(0);
}

echo "Creating SQLite database at $dbFile...\n";

// Create tables (SQLite syntax)
$tables = [
    // department
    "CREATE TABLE department (
        -- 表注释：部门——组织架构中的部门节点
        id INTEGER PRIMARY KEY AUTOINCREMENT,  -- 主键ID
        name TEXT NOT NULL DEFAULT '',  -- 名称
        parent_id INTEGER DEFAULT 0,  -- 上级ID
        dingtalk_dept_id INTEGER DEFAULT 0,  -- 钉钉部门ID
        sort_order INTEGER DEFAULT 0,  -- 排序号
        leader_user_id INTEGER DEFAULT 0  -- 部门负责人用户ID
    )",

    // user
    "CREATE TABLE user (
        -- 表注释：用户——系统登录与操作人员
        id INTEGER PRIMARY KEY AUTOINCREMENT,  -- 主键ID
        username TEXT DEFAULT '',  -- 登录用户名
        password TEXT DEFAULT '',  -- 密码哈希
        name TEXT NOT NULL DEFAULT '',  -- 名称
        email TEXT DEFAULT '',  -- 邮箱
        mobile TEXT DEFAULT '',  -- 手机号
        avatar TEXT DEFAULT '',  -- 头像URL
        dept_id INTEGER DEFAULT 0,  -- 部门ID
        is_admin INTEGER DEFAULT 0,  -- 管理员标记(1=是)
        perm_version INTEGER NOT NULL DEFAULT 0,  -- 权限版本号(角色/权限变更自增,用于失效已登录会话缓存)
        status INTEGER DEFAULT 1,  -- 状态
        dingtalk_userid TEXT DEFAULT '',  -- 钉钉用户ID
        dingtalk_unionid TEXT DEFAULT '',  -- 钉钉UnionID
        last_login_at TEXT DEFAULT NULL,  -- 最后登录时间
        force_reset INTEGER DEFAULT 0,  -- 强制改密(1=是)
        need_handover INTEGER DEFAULT 0,  -- 待交接标记(1=钉钉同步检测疑似离职,待管理员办理离职交接;交接/恢复后清零)
        created_at TEXT DEFAULT (datetime('now','localtime')),  -- 创建时间
        updated_at TEXT DEFAULT (datetime('now','localtime'))  -- 更新时间
    )",
    "CREATE UNIQUE INDEX uk_username ON user(username)",
    "CREATE INDEX idx_dingtalk_userid ON user(dingtalk_userid)",
    "CREATE INDEX idx_user_dept ON user(dept_id)",  // P2-5：数据可见性高频 user WHERE dept_id IN 查询

    // role
    "CREATE TABLE role (
        -- 表注释：角色——权限分组
        id INTEGER PRIMARY KEY AUTOINCREMENT,  -- 主键ID
        name TEXT NOT NULL DEFAULT '',  -- 名称
        code TEXT NOT NULL DEFAULT '',  -- 编码
        description TEXT DEFAULT '',  -- 描述
        data_scope TEXT DEFAULT 'SELF',  -- 数据范围(SELF=仅自己/DEPT=本部门/DEPT_AND_CHILD=本部门及子部门/CUSTOM=自定义部门/ALL=全部)
        is_system INTEGER DEFAULT 0  -- 系统内置(1=是)
    )",
    "CREATE UNIQUE INDEX uk_role_code ON role(code)",

    // permission
    "CREATE TABLE permission (
        -- 表注释：权限——可访问的菜单与操作点
        id INTEGER PRIMARY KEY AUTOINCREMENT,  -- 主键ID
        name TEXT NOT NULL DEFAULT '',  -- 名称
        code TEXT NOT NULL DEFAULT '',  -- 编码
        group_name TEXT DEFAULT ''  -- 权限分组
    )",
    "CREATE UNIQUE INDEX uk_perm_code ON permission(code)",

    // user_role
    "CREATE TABLE user_role (
        -- 表注释：用户角色关联——用户与角色多对多映射
        user_id INTEGER NOT NULL,  -- 用户ID
        role_id INTEGER NOT NULL,  -- 角色ID
        PRIMARY KEY (user_id, role_id)
    )",

    // role_permission
    "CREATE TABLE role_permission (
        -- 表注释：角色权限关联——角色与权限多对多映射
        role_id INTEGER NOT NULL,  -- 角色ID
        perm_id INTEGER NOT NULL,  -- 权限ID
        PRIMARY KEY (role_id, perm_id)
    )",

    // role_dept（v2.37.0 新增：自定义数据范围 CUSTOM 的部门白名单）
    "CREATE TABLE role_dept (
        -- 表注释：角色部门关联——当角色数据范围为 CUSTOM(自定义部门)时,此表记录该角色可访问的部门集合
        role_id INTEGER NOT NULL,  -- 角色ID
        dept_id INTEGER NOT NULL,  -- 部门ID
        PRIMARY KEY (role_id, dept_id)
    )",

    // audit_log
    "CREATE TABLE audit_log (
        -- 表注释：操作审计日志——记录关键操作留痕
        id INTEGER PRIMARY KEY AUTOINCREMENT,  -- 主键ID
        user_id INTEGER NOT NULL DEFAULT 0,  -- 用户ID
        action TEXT DEFAULT '',  -- 操作动作
        target_type TEXT DEFAULT '',  -- 目标类型(contract/customer/project等)
        target_id INTEGER DEFAULT 0,  -- 目标ID
        content TEXT DEFAULT '',  -- 内容
        ip_address TEXT DEFAULT '',  -- 操作IP
        user_agent TEXT DEFAULT '',  -- 浏览器UA
        created_at TEXT DEFAULT (datetime('now','localtime'))  -- 创建时间
    )",
    "CREATE INDEX idx_audit_user ON audit_log(user_id)",
    "CREATE INDEX idx_audit_target ON audit_log(target_type, target_id)",

    // customer
    "CREATE TABLE customer (
        -- 表注释：客户——合同主体方
        id INTEGER PRIMARY KEY AUTOINCREMENT,  -- 主键ID
        name TEXT NOT NULL DEFAULT '',  -- 名称
        credit_code TEXT DEFAULT '',  -- 统一社会信用代码
        legal_person TEXT DEFAULT '',  -- 法定代表人
        contact_name TEXT DEFAULT '',  -- 联系人姓名
        contact_mobile TEXT DEFAULT '',  -- 联系人手机
        contact_email TEXT DEFAULT '',  -- 联系人邮箱
        address TEXT DEFAULT '',  -- 地址
        level INTEGER DEFAULT 0,  -- 客户等级(已废弃，所有客户一视同仁)
        source TEXT DEFAULT 'MANUAL',  -- 来源(MANUAL/IMPORT)
        status INTEGER DEFAULT 1,  -- 状态
        is_self INTEGER DEFAULT 0,  -- 是否本公司(1=是)
        credit_score INTEGER DEFAULT 100,  -- 信用评分(满分100)(v2.38.3)
        high_risk INTEGER DEFAULT 0,  -- 高风险标记(1=高风险)(v2.38.3)
        credit_manual INTEGER NOT NULL DEFAULT 0,  -- 信用评分人工锁定(1=人工维护过，自动重算跳过评分/等级)(v2.38.6)
        lifecycle_status TEXT DEFAULT 'ACTIVE',  -- 生命周期(POTENTIAL/ACTIVE/INACTIVE)(v2.38.3)
        industry TEXT DEFAULT '',  -- 行业(GOV/REAL_ESTATE/FOOD_TOURISM/OTHER)(v2.40.0)
        owner_id INTEGER DEFAULT 0,  -- 归属人ID
        dept_id INTEGER DEFAULT 0,  -- 部门ID
        parent_id INTEGER DEFAULT 0,  -- 父客户ID(集团层级,0=顶层)(v2.45.0)
        is_deleted INTEGER DEFAULT 0,  -- 软删除(1=已删除)
        created_at TEXT DEFAULT (datetime('now','localtime')),  -- 创建时间
        updated_at TEXT DEFAULT (datetime('now','localtime'))  -- 更新时间
    )",
    "CREATE INDEX idx_customer_owner ON customer(owner_id)",
    "CREATE INDEX idx_customer_parent ON customer(parent_id)",  // 集团层级查询索引(v2.45.0)

    // customer_claim_record
    "CREATE TABLE customer_claim_record (
        -- 表注释：客户认领记录——公海客户认领流水
        id INTEGER PRIMARY KEY AUTOINCREMENT,  -- 主键ID
        customer_id INTEGER NOT NULL DEFAULT 0,  -- 客户ID
        user_id INTEGER NOT NULL DEFAULT 0,  -- 用户ID
        created_at TEXT DEFAULT (datetime('now','localtime'))  -- 创建时间
    )",
    "CREATE INDEX idx_claim_user_created ON customer_claim_record(user_id, created_at)",  // P2-5：认领每日限额查询

    // customer_share — 客户共享记录（v2.45.0：客户协作共享——白名单共享给用户/部门）
    "CREATE TABLE customer_share (
        -- 表注释：客户共享——客户白名单共享给指定用户/部门（v2.45.0 客户协作共享）
        id INTEGER PRIMARY KEY AUTOINCREMENT,  -- 主键ID
        customer_id INTEGER NOT NULL DEFAULT 0,  -- 客户ID
        target_type TEXT NOT NULL DEFAULT 'USER',  -- 共享对象类型(USER=用户/DEPT=部门)
        target_id INTEGER NOT NULL DEFAULT 0,  -- 共享对象ID(用户ID或部门ID)
        share_level TEXT NOT NULL DEFAULT 'VIEW',  -- 共享级别(VIEW=只读,可查看+可关联合同)
        created_by INTEGER NOT NULL DEFAULT 0,  -- 共享操作人ID
        created_at TEXT DEFAULT (datetime('now','localtime'))  -- 创建时间
    )",
    "CREATE UNIQUE INDEX uk_share_customer_target ON customer_share(customer_id, target_type, target_id)",
    "CREATE INDEX idx_share_customer ON customer_share(customer_id)",

    // customer_transfer_record
    "CREATE TABLE customer_transfer_record (
        -- 表注释：客户交接记录——负责人变更流水
        id INTEGER PRIMARY KEY AUTOINCREMENT,  -- 主键ID
        customer_id INTEGER NOT NULL DEFAULT 0,  -- 客户ID
        from_user_id INTEGER NOT NULL DEFAULT 0,  -- 转出人ID
        to_user_id INTEGER NOT NULL DEFAULT 0,  -- 转入人ID
        created_at TEXT DEFAULT (datetime('now','localtime'))  -- 创建时间
    )",

    // customer_activity — 客户跟进记录（v2.38.2：支撑公海自动回落规则）
    "CREATE TABLE customer_activity (
        -- 表注释：客户跟进记录——跟进类型/时间/备注，支撑公海回落
        id INTEGER PRIMARY KEY AUTOINCREMENT,  -- 主键ID
        customer_id INTEGER NOT NULL DEFAULT 0,  -- 客户ID
        user_id INTEGER NOT NULL DEFAULT 0,  -- 跟进人ID
        type TEXT NOT NULL DEFAULT 'NOTE',  -- 类型(CALL/MEETING/VISIT/EMAIL/NOTE/CLAIM/RELEASE/TRANSFER)
        content TEXT,  -- 跟进内容
        next_follow_at TEXT DEFAULT NULL,  -- 下次跟进时间(v2.40.0 手动跟进录入)
        created_at TEXT DEFAULT (datetime('now','localtime'))  -- 创建时间
    )",
    "CREATE INDEX idx_activity_customer ON customer_activity(customer_id)",  // P2-5：客户详情跟进记录查询

    // customer_contact — 客户联系人（v2.38.3）
    "CREATE TABLE customer_contact (
        -- 表注释：客户联系人——独立联系人表，支持多角色
        id INTEGER PRIMARY KEY AUTOINCREMENT,  -- 主键ID
        customer_id INTEGER NOT NULL DEFAULT 0,  -- 客户ID
        name TEXT NOT NULL DEFAULT '',  -- 姓名
        phone TEXT DEFAULT '',  -- 电话
        email TEXT DEFAULT '',  -- 邮箱
        role TEXT DEFAULT '商务负责人',  -- 角色(商务负责人/技术对接人/法务对接人/财务对接人)
        is_primary INTEGER DEFAULT 0,  -- 是否主联系人
        remark TEXT DEFAULT '',  -- 备注/更多信息(微信号等)
        created_at TEXT DEFAULT (datetime('now','localtime'))  -- 创建时间
    )",

    // project (P2-5 合同→项目关联)
    "CREATE TABLE project (
        -- 表注释：项目——合同归属项目
        id INTEGER PRIMARY KEY AUTOINCREMENT,  -- 主键ID
        name TEXT NOT NULL DEFAULT '',  -- 名称
        code TEXT DEFAULT '',  -- 编码
        customer_id INTEGER DEFAULT 0,  -- 客户ID
        owner_id INTEGER DEFAULT 0,  -- 归属人ID
        dept_id INTEGER DEFAULT 0,  -- 部门ID
        status TEXT DEFAULT 'ACTIVE',  -- 状态
        budget REAL DEFAULT 0,  -- 预算金额
        start_date TEXT DEFAULT NULL,  -- 开始日期
        end_date TEXT DEFAULT NULL,  -- 结束日期
        stage TEXT DEFAULT 'PLANNING',  -- 执行阶段(v2.40.0: PLANNING/EXECUTING/ACCEPTANCE/COMPLETED)
        progress INTEGER DEFAULT 0,  -- 执行进度(v2.40.0: 0-100)
        remark TEXT DEFAULT '',  -- 备注
        is_deleted INTEGER DEFAULT 0,  -- 软删除(1=已删除)
        created_at TEXT DEFAULT (datetime('now','localtime')),  -- 创建时间
        updated_at TEXT DEFAULT (datetime('now','localtime'))  -- 更新时间
    )",
    "CREATE INDEX idx_project_owner ON project(owner_id)",

    // contract_template
    "CREATE TABLE contract_template (
        -- 表注释：合同模板——生成合同的基础模板
        id INTEGER PRIMARY KEY AUTOINCREMENT,  -- 主键ID
        name TEXT NOT NULL DEFAULT '',  -- 名称
        code TEXT NOT NULL DEFAULT '',  -- 编码
        category TEXT DEFAULT '',  -- 合同分类(SERVICE/PURCHASE/LEASE/NDA等)
        status TEXT DEFAULT 'DRAFT',  -- 状态
        current_version INTEGER DEFAULT 1,  -- 当前版本号
        content TEXT DEFAULT '',  -- 内容
        fields_schema TEXT DEFAULT '',  -- 自定义字段JSON
        default_direction TEXT DEFAULT '',  -- 默认方向(sales/purchase)
        default_trade_attr TINYINT NOT NULL DEFAULT 1,  -- 默认交易属性(1=交易)
        default_flow_id INTEGER DEFAULT 0,  -- 默认审批流ID
        tips TEXT DEFAULT '',  -- 提示说明
        creator_id INTEGER NOT NULL DEFAULT 0,  -- 创建人ID
        created_at TEXT DEFAULT (datetime('now','localtime')),  -- 创建时间
        updated_at TEXT DEFAULT (datetime('now','localtime'))  -- 更新时间
    )",
    "CREATE UNIQUE INDEX uk_tpl_code ON contract_template(code)",

    // contract
    "CREATE TABLE contract (
        -- 表注释：合同主表——合同核心信息
        id INTEGER PRIMARY KEY AUTOINCREMENT,  -- 主键ID
        contract_no TEXT NOT NULL DEFAULT '',  -- 合同编号
        title TEXT NOT NULL DEFAULT '',  -- 标题
        category TEXT DEFAULT 'SERVICE',  -- 合同分类(SERVICE/PURCHASE/LEASE/NDA等)
        template_id INTEGER DEFAULT 0,  -- 合同模板ID
        status TEXT DEFAULT 'DRAFT',  -- 状态
        amount REAL DEFAULT 0.00,  -- 金额
        party_a_name TEXT DEFAULT '',  -- 甲方名称
        party_a_contact TEXT DEFAULT '',  -- 甲方联系人
        party_a_phone TEXT DEFAULT '',  -- 甲方电话
        party_a_customer_id INTEGER DEFAULT 0,  -- 甲方客户ID(v2.40.0：我方=乙方时对方为甲方)
        party_a_supplier_id INTEGER DEFAULT 0,  -- 甲方供应商ID(v2.46.0：签约方强制关联档案)
        party_b_customer_id INTEGER DEFAULT 0,  -- 乙方客户ID
        party_b_name TEXT DEFAULT '',  -- 乙方名称
        party_b_contact TEXT DEFAULT '',  -- 乙方联系人
        party_b_credit_code TEXT DEFAULT '',  -- 乙方信用代码
        effective_date TEXT DEFAULT NULL,  -- 生效日期
        expiry_date TEXT DEFAULT NULL,  -- 到期日期
        content TEXT DEFAULT '',  -- 内容
        content_plain TEXT DEFAULT '',  -- 内容(纯文本)
        file_url TEXT DEFAULT '',  -- 文件URL
        keywords TEXT DEFAULT '',  -- 关键词
        owner_id INTEGER DEFAULT 0,  -- 归属人ID
        dept_id INTEGER DEFAULT 0,  -- 部门ID
        parent_id INTEGER DEFAULT 0,  -- 上级ID
        supplier_id INTEGER DEFAULT 0,  -- 关联供应商ID
        direction TEXT DEFAULT 'sales',  -- 合同方向(sales/purchase)
        trade_attr TINYINT NOT NULL DEFAULT 1,  -- 交易属性(1=交易/计入收支,0=非交易)
        project_id INTEGER DEFAULT 0,  -- 关联项目ID
        flow_id INTEGER DEFAULT 0,  -- 审批流ID
        our_company_id INTEGER DEFAULT 0,  -- 本方主体ID
        custom_fields TEXT DEFAULT '{}',  -- 自定义字段JSON
        creator_id INTEGER NOT NULL DEFAULT 0,  -- 创建人ID
        updater_id INTEGER NOT NULL DEFAULT 0,  -- 最后更新人ID
        is_deleted INTEGER DEFAULT 0,  -- 软删除(1=已删除)
        created_at TEXT DEFAULT (datetime('now','localtime')),  -- 创建时间
        updated_at TEXT DEFAULT (datetime('now','localtime')),  -- 更新时间
        renewed_to INTEGER DEFAULT 0,  -- 续约至新合同ID(v2.38.3)
        renewed_from INTEGER DEFAULT 0  -- 续约自原合同ID(v2.38.3)
    )",
    "CREATE UNIQUE INDEX uk_contract_no ON contract(contract_no)",
    "CREATE INDEX idx_contract_owner ON contract(owner_id)",
    "CREATE INDEX idx_contract_status ON contract(status)",
    "CREATE INDEX idx_contract_project ON contract(project_id)",
    "CREATE INDEX idx_contract_dept ON contract(dept_id)",
    "CREATE INDEX idx_contract_expiry ON contract(expiry_date)",
    "CREATE INDEX idx_contract_created ON contract(created_at)",
    // P1-5 / P2-2：高频过滤/关联字段补索引，消除 OR 条件与列过滤全表扫描
    "CREATE INDEX idx_contract_creator ON contract(creator_id)",
    "CREATE INDEX idx_contract_party_b ON contract(party_b_customer_id)",
    "CREATE INDEX idx_contract_party_a ON contract(party_a_customer_id)",
    "CREATE INDEX idx_contract_party_a_supplier ON contract(party_a_supplier_id)",
    "CREATE INDEX idx_contract_supplier ON contract(supplier_id)",
    "CREATE INDEX idx_contract_parent ON contract(parent_id)",
    "CREATE INDEX idx_contract_category ON contract(category)",
    "CREATE INDEX idx_contract_ourco ON contract(our_company_id)",
    "CREATE INDEX idx_contract_trade_dir_status ON contract(trade_attr, direction, status)",

    // contract_revision
    "CREATE TABLE contract_revision (
        -- 表注释：合同变更历史——版本留痕
        id INTEGER PRIMARY KEY AUTOINCREMENT,  -- 主键ID
        contract_id INTEGER NOT NULL DEFAULT 0,  -- 合同ID
        field_name TEXT DEFAULT '',  -- 变更字段名
        old_value TEXT DEFAULT '',  -- 旧值
        new_value TEXT DEFAULT '',  -- 新值
        operator_id INTEGER NOT NULL DEFAULT 0,  -- 操作人ID
        created_at TEXT DEFAULT (datetime('now','localtime'))  -- 创建时间
    )",
    "CREATE INDEX idx_contract_rev ON contract_revision(contract_id)",

    // approval_flow
    "CREATE TABLE approval_flow (
        -- 表注释：审批流程定义——节点与路由配置
        id INTEGER PRIMARY KEY AUTOINCREMENT,  -- 主键ID
        name TEXT NOT NULL DEFAULT '',  -- 名称
        code TEXT NOT NULL DEFAULT '',  -- 编码
        category TEXT DEFAULT '',  -- 合同分类(SERVICE/PURCHASE/LEASE/NDA等，遗留单值字段)
        category_list TEXT DEFAULT '[]',  -- 适用分类列表(JSON数组，空=适用全部分类)
        min_amount REAL DEFAULT 0.00,  -- 最低金额
        max_amount REAL DEFAULT 99999999.99,  -- 最高金额
        use_amount INTEGER DEFAULT 1,  -- 是否启用金额条件(1=启用按金额区间匹配/0=不启用不限金额)
        nodes TEXT DEFAULT '[]',  -- 审批节点JSON(仅审批节点，抄送已独立为cc_list)
        cc_list TEXT DEFAULT '',  -- 抄送配置(JSON：流程级知会，与审批节点平级)
        biz_type TEXT DEFAULT 'contract',  -- 业务类型(contract=合同审批/invoice=发票审批；发票专用流程按此过滤)
        form_condition TEXT DEFAULT '',  -- 表单条件(JSON：[{field,value}]，非空=仅表单该字段值命中时匹配；空=默认兜底流程)
        sort_order INTEGER DEFAULT 0,  -- 同类型流程内优先级(越小越靠前，审批匹配优先取小；0=未手动排序)
        status INTEGER DEFAULT 1,  -- 状态
        creator_id INTEGER NOT NULL DEFAULT 0,  -- 创建人ID
        created_at TEXT DEFAULT (datetime('now','localtime')),  -- 创建时间
        updated_at TEXT DEFAULT (datetime('now','localtime'))  -- 更新时间
    )",
    "CREATE UNIQUE INDEX uk_flow_code ON approval_flow(code)",

    // approval_cc_log（v2.38.0：抄送从节点链独立为流程级cc_list后的轨迹表）
    "CREATE TABLE approval_cc_log (
        -- 表注释：审批流抄送知会轨迹——流程级抄送记录，与审批表决分开存放
        id INTEGER PRIMARY KEY AUTOINCREMENT,  -- 主键ID
        instance_id INTEGER NOT NULL DEFAULT 0,  -- 审批实例ID
        user_id INTEGER NOT NULL DEFAULT 0,  -- 被抄送用户ID
        node_order INTEGER DEFAULT 0,  -- 触发时审批节点序号(流程级提交即触发记0)
        role_codes TEXT DEFAULT '',  -- 命中抄送角色码(JSON数组)
        cc_user_ids TEXT DEFAULT '',  -- 命中抄送指定用户ID(JSON数组)
        created_at TEXT DEFAULT (datetime('now','localtime'))  -- 抄送时间
    )",

    // approval_instance
    "CREATE TABLE approval_instance (
        -- 表注释：审批实例——一次具体审批任务
        id INTEGER PRIMARY KEY AUTOINCREMENT,  -- 主键ID
        contract_id INTEGER NOT NULL DEFAULT 0,  -- 合同ID(发票审批时为0或关联合同)
        biz_type TEXT DEFAULT 'contract',  -- 业务类型(contract=合同/invoice=发票)
        target_id INTEGER DEFAULT 0,  -- 业务目标ID(发票审批=发票id；合同审批与contract_id相同)
        flow_id INTEGER NOT NULL DEFAULT 0,  -- 审批流ID
        status TEXT DEFAULT 'PENDING',  -- 状态
        current_node_order INTEGER DEFAULT 1,  -- 当前节点序号
        submitted_by INTEGER NOT NULL DEFAULT 0,  -- 提交人ID
        submitted_at TEXT DEFAULT (datetime('now','localtime')),  -- 提交时间
        finished_at TEXT DEFAULT NULL  -- 完成时间
    )",
    "CREATE INDEX idx_apv_contract ON approval_instance(contract_id)",
    "CREATE INDEX idx_apv_submitter ON approval_instance(submitted_by)",
    "CREATE INDEX idx_apv_status ON approval_instance(status)",  // P0-4：提交扫描按 status 过滤，补单列索引避免全表扫描

    // approval_record
    "CREATE TABLE approval_record (
        -- 表注释：审批记录——单节点审批结果
        id INTEGER PRIMARY KEY AUTOINCREMENT,  -- 主键ID
        instance_id INTEGER NOT NULL DEFAULT 0,  -- 审批实例ID
        node_order INTEGER DEFAULT 1,  -- 节点序号
        node_name TEXT DEFAULT '',  -- 节点名称
        approver_id INTEGER NOT NULL DEFAULT 0,  -- 审批人ID
        action TEXT DEFAULT 'PENDING',  -- 操作动作
        comment TEXT DEFAULT '',  -- 审批意见
        acted_at TEXT DEFAULT NULL,  -- 审批时间
        created_at TEXT DEFAULT (datetime('now','localtime'))  -- 创建时间
    )",
    "CREATE INDEX idx_apv_rec_instance ON approval_record(instance_id)",
    "CREATE INDEX idx_apv_rec_approver ON approval_record(approver_id)",
    "CREATE INDEX idx_apv_rec_approver_action ON approval_record(approver_id, action)",

    // supplier
    "CREATE TABLE supplier (
        -- 表注释：供应商——采购与服务方
        id INTEGER PRIMARY KEY AUTOINCREMENT,  -- 主键ID
        name TEXT NOT NULL DEFAULT '',  -- 名称
        type TEXT DEFAULT 'MEDIA',  -- 供应商类型(MEDIA/MATERIAL等)
        contact_name TEXT DEFAULT '',  -- 联系人姓名
        contact_mobile TEXT DEFAULT '',  -- 联系人手机
        contact_email TEXT DEFAULT '',  -- 联系人邮箱
        address TEXT DEFAULT '',  -- 地址
        status INTEGER DEFAULT 1,  -- 状态
        owner_id INTEGER DEFAULT 0,  -- 归属人ID
        dept_id INTEGER DEFAULT 0,  -- 部门ID
        is_deleted INTEGER DEFAULT 0,  -- 软删除(1=已删除)
        created_at TEXT DEFAULT (datetime('now','localtime')),  -- 创建时间
        updated_at TEXT DEFAULT (datetime('now','localtime'))  -- 更新时间
    )",
    "CREATE INDEX idx_supplier_owner ON supplier(owner_id)",

    // payment_record
    "CREATE TABLE payment_record (
        -- 表注释：回款记录——收款流水
        id INTEGER PRIMARY KEY AUTOINCREMENT,  -- 主键ID
        contract_id INTEGER NOT NULL DEFAULT 0,  -- 合同ID
        payment_type TEXT DEFAULT 'RECEIVABLE',  -- 回款类型(RECEIVABLE=应收/PAYABLE=应付)
        amount REAL DEFAULT 0.00,  -- 金额
        planned_date TEXT DEFAULT NULL,  -- 计划日期
        actual_date TEXT DEFAULT NULL,  -- 实际日期
        payment_method TEXT DEFAULT '',  -- 回款方式
        status TEXT DEFAULT 'PENDING',  -- 状态
        description TEXT DEFAULT '',  -- 描述
        operator_id INTEGER DEFAULT 0,  -- 操作人ID
        paid_amount REAL DEFAULT 0.00,  -- 已确认收款金额（部分确认时使用）
        parent_id INTEGER DEFAULT 0,  -- 拆分来源记录ID（部分确认生成的剩余待收）
        invoice_no TEXT DEFAULT '',  -- 关联发票号(v2.38.3)
        milestone TEXT DEFAULT '',  -- 里程碑名称(v2.38.3)
        created_at TEXT DEFAULT (datetime('now','localtime')),  -- 创建时间
        updated_at TEXT DEFAULT (datetime('now','localtime'))  -- 更新时间
    )",
    "CREATE INDEX idx_payment_contract ON payment_record(contract_id)",
    "CREATE INDEX idx_payment_status_plan ON payment_record(status, planned_date)",
    "CREATE INDEX idx_pr_type_status ON payment_record(payment_type, status)",  // P2-14：payment_record 无 trade_attr 列(该列在 contract 表)，按回款类型+状态过滤分组，覆盖驾驶舱/项目聚合

    // contract_invoice
    "CREATE TABLE contract_invoice (
        -- 表注释：发票——开票信息
        id INTEGER PRIMARY KEY AUTOINCREMENT,  -- 主键ID
        contract_id INTEGER NOT NULL DEFAULT 0,  -- 合同ID
        amount REAL DEFAULT 0.00,  -- 金额
        tax_rate REAL DEFAULT 0.06,  -- 税率
        tax_amount REAL DEFAULT 0.00,  -- 税额
        invoice_type TEXT DEFAULT 'VAT_SPECIAL',  -- 发票类型(VAT_SPECIAL=专票/VAT_NORMAL=普票/E_INVOICE=电子票)
        invoice_title TEXT DEFAULT '',  -- 发票抬头
        tax_no TEXT DEFAULT '',  -- 税号
        invoice_no TEXT DEFAULT '',  -- 发票号码
        remark TEXT DEFAULT '',  -- 备注
        status TEXT DEFAULT 'PENDING_APPROVAL',  -- 状态(PENDING_APPROVAL=待审批/APPROVED=待开票/REJECTED=已驳回/ISSUED=已开票/VOID=已作废/RED=已红冲/CANCELLED=已撤回；APPLIED=历史申请态只读)
        issued_date TEXT DEFAULT NULL,  -- 开票日期
        operator_id INTEGER DEFAULT 0,  -- 操作人ID
        related_id INTEGER DEFAULT 0,  -- 关联发票ID（红冲时指向被红冲的原发票）
        our_company_id INTEGER DEFAULT 0,  -- 开票主体（我方公司ID，company表）
        content_desc TEXT DEFAULT '',  -- 开票内容/品目
        customer_id INTEGER DEFAULT 0,  -- 开票客户ID（customer 表，复用客户开票信息）
        applicant_id INTEGER DEFAULT 0,  -- 申请人ID
        approval_instance_id INTEGER DEFAULT 0,  -- 关联审批实例ID
        issued_by INTEGER DEFAULT 0,  -- 开票人ID
        created_at TEXT DEFAULT (datetime('now','localtime')),  -- 创建时间
        updated_at TEXT DEFAULT (datetime('now','localtime'))  -- 更新时间
    )",
    "CREATE INDEX idx_invoice_contract ON contract_invoice(contract_id)",
    "CREATE INDEX idx_invoice_status ON contract_invoice(status)",  // F1：财务中心待开票列表按状态筛选

    // invoice_form_field（F1/F2：发票申请表单字段配置，后台可启停/排序/新增自定义字段，钉钉表单式）
    "CREATE TABLE invoice_form_field (
        -- 表注释：发票申请表单字段配置——预置字段池+自定义字段，驱动PC/移动端申请表单渲染
        id INTEGER PRIMARY KEY AUTOINCREMENT,  -- 主键ID
        field_key TEXT NOT NULL DEFAULT '',  -- 字段唯一键(预置字段=固定key；自定义字段=field_custom_序号)
        field_label TEXT NOT NULL DEFAULT '',  -- 显示标签
        field_type TEXT DEFAULT 'text',  -- 字段类型(text/number/textarea/select/company)
        field_options TEXT DEFAULT '[]',  -- 选项JSON（select 用）
        option_layout TEXT DEFAULT 'column',  -- 单选/多选选项排列方式(column=纵向,tile=横向平铺)
        required INTEGER DEFAULT 0,  -- 是否必填(1=必填)
        enabled INTEGER DEFAULT 1,  -- 是否启用(0=停用不渲染)
        sort_order INTEGER DEFAULT 0,  -- 排序（小在前）
        is_system INTEGER DEFAULT 0,  -- 系统预置字段(1=禁止删除，仅可停用)
        created_at TEXT DEFAULT (datetime('now','localtime')),  -- 创建时间
        updated_at TEXT DEFAULT (datetime('now','localtime'))  -- 更新时间
    )",
    "CREATE UNIQUE INDEX uk_inv_form_key ON invoice_form_field(field_key)",

    // form_field_linkage（F9：通用表单字段联动规则——发票申请/未来审批表单共用；触发字段值变化→目标字段显隐/替换选项）
    "CREATE TABLE form_field_linkage (
        -- 表注释：表单字段联动规则——通用组件，触发字段值变化联动目标字段显隐或选项
        id INTEGER PRIMARY KEY AUTOINCREMENT,  -- 主键ID
        form_key TEXT NOT NULL DEFAULT '',  -- 表单标识(invoice_apply=发票申请；未来其他审批表单扩展)
        trigger_field TEXT NOT NULL DEFAULT '',  -- 触发字段(name)
        trigger_value TEXT NOT NULL DEFAULT '',  -- 触发值(触发字段等于该值时生效)
        target_field TEXT NOT NULL DEFAULT '',  -- 目标字段(name)
        action TEXT DEFAULT 'options',  -- 联动动作(show=显示/hide=隐藏/options=替换选项)
        options TEXT DEFAULT '[]',  -- action=options 时目标字段的新选项 JSON
        sort_order INTEGER DEFAULT 0,  -- 排序（小在前）
        created_at TEXT DEFAULT (datetime('now','localtime')),  -- 创建时间
        updated_at TEXT DEFAULT (datetime('now','localtime'))  -- 更新时间
    )",
    "CREATE INDEX idx_link_form ON form_field_linkage(form_key)",

    // remind_log
    "CREATE TABLE remind_log (
        -- 表注释：提醒日志——到期与待办提醒
        id INTEGER PRIMARY KEY AUTOINCREMENT,  -- 主键ID
        target_type TEXT DEFAULT '',  -- 目标类型(contract/customer/project等)
        target_id INTEGER DEFAULT 0,  -- 目标ID
        remind_type TEXT DEFAULT '',  -- 提醒类型(expire/payment等)
        remind_at TEXT DEFAULT '',  -- 提醒日期
        sent INTEGER DEFAULT 0,  -- 推送状态(1=已推送)
        created_at TEXT DEFAULT (datetime('now','localtime'))  -- 创建时间
    )",
    "CREATE UNIQUE INDEX uk_remind_dedup ON remind_log(target_type, target_id, remind_type, remind_at)",
    "CREATE INDEX idx_remind_target ON remind_log(target_type, target_id)",

    // dingtalk_session
    "CREATE TABLE dingtalk_session (
        -- 表注释：钉钉会话——免登与 token 缓存
        id INTEGER PRIMARY KEY AUTOINCREMENT,  -- 主键ID
        user_id INTEGER NOT NULL DEFAULT 0,  -- 用户ID
        token TEXT NOT NULL DEFAULT '',  -- 会话Token
        expires_at TEXT NOT NULL DEFAULT '',  -- 过期时间
        created_at TEXT DEFAULT (datetime('now','localtime'))  -- 创建时间
    )",

    // system_config
    "CREATE TABLE system_config (
        -- 表注释：系统配置——键值配置项
        id INTEGER PRIMARY KEY AUTOINCREMENT,  -- 主键ID
        config_key TEXT NOT NULL DEFAULT '',  -- 配置键
        config_value TEXT DEFAULT '',  -- 配置值
        config_type TEXT DEFAULT 'STRING',  -- 配置值类型(STRING/INT/JSON)
        group_name TEXT DEFAULT '',  -- 权限分组
        created_at TEXT DEFAULT (datetime('now','localtime')),  -- 创建时间
        updated_at TEXT DEFAULT (datetime('now','localtime'))  -- 更新时间
    )",
    "CREATE UNIQUE INDEX uk_config_key ON system_config(config_key)",

    // company_profile（本公司主体）
    "CREATE TABLE company_profile (
        -- 表注释：本公司主体——开票与签章主体
        id INTEGER PRIMARY KEY AUTOINCREMENT,  -- 主键ID
        name TEXT NOT NULL DEFAULT '',  -- 名称
        short_name TEXT DEFAULT '',  -- 简称
        unified_social_credit_code TEXT DEFAULT '',  -- 统一社会信用代码（公司代码）
        invoice_tax_rate REAL DEFAULT 0.06,  -- 开票税率(0.06=6%；0=免税；开票申请按主体自动带出，不再单独选择)
        is_default INTEGER DEFAULT 0,  -- 默认标记(1=是)
        created_at TEXT DEFAULT (datetime('now','localtime')),  -- 创建时间
        updated_at TEXT DEFAULT (datetime('now','localtime'))  -- 更新时间
    )",
    "CREATE INDEX idx_company_default ON company_profile(is_default)",

    // resource_library（资料库：合同范本/开票资料/标准条款/其他）
    "CREATE TABLE resource_library (
        -- 表注释：资料库——共享文件资料
        id INTEGER PRIMARY KEY AUTOINCREMENT,  -- 主键ID
        category TEXT DEFAULT 'TEMPLATE',  -- 合同分类(SERVICE/PURCHASE/LEASE/NDA等)
        title TEXT NOT NULL DEFAULT '',  -- 标题
        file_url TEXT DEFAULT '',  -- 文件URL
        file_name TEXT DEFAULT '',  -- 文件名
        file_size INTEGER DEFAULT 0,  -- 文件大小(字节)
        description TEXT DEFAULT '',  -- 描述
        company_id INTEGER DEFAULT 0,  -- 关联主体ID
        owner_id INTEGER DEFAULT 0,  -- 归属人ID
        created_at TEXT DEFAULT (datetime('now','localtime')),  -- 创建时间
        updated_at TEXT DEFAULT (datetime('now','localtime')),  -- 更新时间
        content TEXT DEFAULT ''  -- 结构化字段JSON(开票资料等，空串表示纯文件资料)
    )",
    "CREATE INDEX idx_resource_cat ON resource_library(category)",
    "CREATE INDEX idx_resource_company ON resource_library(company_id)",

    // notification
    "CREATE TABLE notification (
        -- 表注释：站内消息通知——审批结果等事件的站内信，弥补仅钉钉通知、未绑定钉钉时收不到的缺陷
        id INTEGER PRIMARY KEY AUTOINCREMENT,  -- 主键ID
        user_id INTEGER NOT NULL DEFAULT 0,  -- 接收用户ID
        type TEXT DEFAULT '',  -- 通知类型(APPROVAL_REJECTED/APPROVED/TRANSFERRED/SUBMITTED/CC/OVERDUE)
        title TEXT DEFAULT '',  -- 标题
        content TEXT,  -- 内容(markdown)
        url TEXT DEFAULT '',  -- 点击跳转链接
        is_read INTEGER NOT NULL DEFAULT 0,  -- 是否已读(0否1是)
        created_at TEXT DEFAULT (datetime('now','localtime'))  -- 创建时间
    )",
    "CREATE INDEX idx_notif_user ON notification(user_id, is_read)",
];

foreach ($tables as $sql) {
    try {
        Db::execute($sql);
    } catch (\Exception $e) {
        echo "Warning: " . $e->getMessage() . "\n";
    }
}

// Seed data
echo "Seeding data...\n";

// Roles
Db::execute("INSERT INTO role (id, name, code, description, data_scope, is_system) VALUES (1, '超级管理员', 'admin', '系统超级管理员', 'ALL', 1)");
Db::execute("INSERT INTO role (id, name, code, description, data_scope, is_system) VALUES (2, '部门经理', 'manager', '部门经理', 'DEPT', 1)");
Db::execute("INSERT INTO role (id, name, code, description, data_scope, is_system) VALUES (3, '法务', 'legal', '法务人员', 'SELF', 1)");
Db::execute("INSERT INTO role (id, name, code, description, data_scope, is_system) VALUES (4, '财务', 'finance', '财务人员', 'ALL', 1)");
Db::execute("INSERT INTO role (id, name, code, description, data_scope, is_system) VALUES (5, '普通用户', 'user', '普通用户', 'SELF', 1)");
// v2.40.2：总经理角色（管理层看全公司经营概览，data_scope=ALL；工作台经营卡片/PC部门经营按 code='gm' 判定）
Db::execute("INSERT INTO role (id, name, code, description, data_scope, is_system) VALUES (12, '总经理', 'gm', '总经理（管理层：看全公司经营概览）', 'ALL', 1)");

// Permissions
$perms = [
    [1, '查看合同', 'contract:view', '合同管理'],
    [2, '创建合同', 'contract:create', '合同管理'],
    [3, '编辑合同', 'contract:edit', '合同管理'],
    [4, '删除合同', 'contract:delete', '合同管理'],
    [5, '导出合同', 'contract:export', '合同管理'],
    [6, '模板管理', 'template:manage', '合同模板'],
    [7, '查看审批', 'approval:view', '审批管理'],
    [8, '提交审批', 'approval:submit', '审批管理'],
    [9, '审批操作', 'approval:approve', '审批管理'],
    [10, '查看客户', 'customer:view', '客户管理'],
    [11, '创建客户', 'customer:create', '客户管理'],
    [12, '编辑客户', 'customer:edit', '客户管理'],
    [13, '删除客户', 'customer:delete', '客户管理'],
    [14, '用户管理', 'system:user', '系统设置'],
    [15, '角色管理', 'system:role', '系统设置'],
    [16, '系统配置', 'system:config', '系统设置'],
    [17, '同步钉钉', 'dingtalk:sync', '钉钉设置'],
    // 供应商管理
    [18, '查看供应商', 'supplier:view', '供应商管理'],
    [19, '创建供应商', 'supplier:create', '供应商管理'],
    [20, '编辑供应商', 'supplier:edit', '供应商管理'],
    [21, '删除供应商', 'supplier:delete', '供应商管理'],
    // 回款管理
    [22, '查看回款', 'payment:view', '回款管理'],
    [23, '录入回款', 'payment:create', '回款管理'],
    // 发票管理
    [24, '查看发票', 'invoice:view', '发票管理'],
    [25, '开具发票', 'invoice:create', '发票管理'],
    // F1（v2.38.7）：申请开票——普通用户可提交申请，审批通过后由财务(invoice:create)开票
    [39, '申请开票', 'invoice:apply', '发票管理'],
    // 签署管理
    // 提醒管理
    [28, '查看提醒', 'remind:view', '提醒管理'],
    [29, '提醒管理', 'remind:manage', '提醒管理'],
    [30, '查看审计', 'audit:view', '系统设置'],
    // 本公司主体
    [31, '本公司主体管理', 'company:manage', '系统设置'],
    // 资料库
    [32, '查看资料库', 'library:view', '资料库'],
    [44, '上传资料库', 'library:upload', '资料库'],
    [45, '编辑资料库', 'library:edit', '资料库'],
    [46, '删除资料库', 'library:delete', '资料库'],
    // 项目管理 (P2-5)
    [34, '查看项目', 'project:view', '项目管理'],
    [35, '创建项目', 'project:create', '项目管理'],
    [36, '编辑项目', 'project:edit', '项目管理'],
    [37, '删除项目', 'project:delete', '项目管理'],
    [38, '查看相对方', 'party:view', '相对方管理'],
    // v2.38.26：离职交接——独立权限码，可单独授予角色（移动端待交接办理 + 交接/清除接口）
    [40, '离职交接', 'system:handover', '系统设置'],
    // v2.40.3：经营看板——工作台卡片显示由角色配置控制（角色权限可勾选；is_admin 自动拥有全部）
    // v2.40.4：+43 部门经营（dashboard:dept）——部门经营卡片（移动端本部门经营 / PC 按部门经营）统一由该权限码控制
    [41, '全公司经营', 'dashboard:company', '经营看板'],
    [42, '我的业绩', 'dashboard:stats', '经营看板'],
    [43, '部门经营', 'dashboard:dept', '经营看板'],
];
foreach ($perms as $p) {
    Db::execute("INSERT INTO permission (id, name, code, group_name) VALUES (?, ?, ?, ?)", $p);
}

// Admin role gets all permissions（显式列表与 init.sql 一致：1-25, 28-43 共 41 项；26/27 为历史空洞不存在）
foreach ([1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25,28,29,30,31,32,44,45,46,34,35,36,37,38,39,40,41,42,43] as $pid) {
    Db::execute("INSERT INTO role_permission (role_id, perm_id) VALUES (1, ?)", [$pid]);
}
// Manager: contract+customer+approval+template + 供应商/回款/发票/签署/提醒 全量 + 审计 + 资料库管理 + 项目全量 + 相对方
foreach ([1,2,3,5,6,7,8,9,10,11,12,18,19,20,21,22,23,24,25,28,29,30,32,44,45,46,34,35,36,37,38,39,42,43] as $pid) {
    Db::execute("INSERT INTO role_permission (role_id, perm_id) VALUES (2, ?)", [$pid]);
}
// Legal: view contract + approval + 只读供应商/回款/发票/签署/提醒 + 资料库查看 + 相对方
foreach ([1,7,9,18,22,24,28,32,38,39,42] as $pid) {
    Db::execute("INSERT INTO role_permission (role_id, perm_id) VALUES (3, ?)", [$pid]);
}
// Finance: view contract + approval + 回款/发票录入 + 只读供应商/签署 + 提醒管理 + 资料库查看 + 相对方
foreach ([1,7,9,18,22,23,24,25,28,29,32,38,39,41,42] as $pid) {
    Db::execute("INSERT INTO role_permission (role_id, perm_id) VALUES (4, ?)", [$pid]);
}
// User: basic contract + customer + submit approval + 只读供应商/回款/发票/签署/提醒 + 资料库查看 + 项目查看/创建/编辑 + 相对方
foreach ([1,2,3,7,8,10,11,12,18,22,24,28,32,34,35,36,38,39,42] as $pid) {
    Db::execute("INSERT INTO role_permission (role_id, perm_id) VALUES (5, ?)", [$pid]);
}
// v2.40.2：总经理（gm）——管理层业务全量权限（不含系统管理：system:user/role/config、dingtalk:sync、system:handover），data_scope=ALL 看全公司数据
foreach ([1,2,3,4,5,6,7,8,9,10,11,12,13,18,19,20,21,22,23,24,25,28,29,30,31,32,44,45,46,34,35,36,37,38,39,41,42,43] as $pid) {
    Db::execute("INSERT INTO role_permission (role_id, perm_id) VALUES (12, ?)", [$pid]);
}

// Admin user: admin/随机口令（force_reset=1 首登强制改密）
$initPwdAdmin = bin2hex(random_bytes(6));  // 随机 12 位十六进制口令
Db::execute("INSERT INTO user (id, username, password, name, is_admin, status, force_reset) VALUES (1, 'admin', ?, '系统管理员', 1, 1, 1)", [
    password_hash($initPwdAdmin, PASSWORD_BCRYPT)
]);
Db::execute("INSERT INTO user_role (user_id, role_id) VALUES (1, 1)");
Db::execute("INSERT INTO user_role (user_id, role_id) VALUES (1, 3)");   // 系统管理员兼任法务(演示用，保证法务节点有审批人)

// 演示账号：用于直观对比「管理员 / 部门经理 / 普通员工 / 财务」的权限与数据范围差异
// 除 admin 外密码统一为 password，force_reset=1 强制首次登录改密（CR-59：生产部署务必修改或删除演示账号）
Db::execute("INSERT INTO user (username, password, name, dept_id, status, force_reset) VALUES ('manager01', ?, '张经理', 1, 1, 1)", [password_hash('password', PASSWORD_BCRYPT)]);
Db::execute("INSERT INTO user (username, password, name, dept_id, status, force_reset) VALUES ('employee01', ?, '李员工', 1, 1, 1)", [password_hash('password', PASSWORD_BCRYPT)]);
Db::execute("INSERT INTO user (username, password, name, dept_id, status, force_reset) VALUES ('finance01', ?, '王财务', 1, 1, 1)", [password_hash('password', PASSWORD_BCRYPT)]);
Db::execute("INSERT INTO user_role (user_id, role_id) VALUES ((SELECT id FROM user WHERE username='manager01'), 2)");   // 部门经理(DEPT)
Db::execute("INSERT INTO user_role (user_id, role_id) VALUES ((SELECT id FROM user WHERE username='employee01'), 5)");  // 普通用户(SELF)
Db::execute("INSERT INTO user_role (user_id, role_id) VALUES ((SELECT id FROM user WHERE username='finance01'), 4)");   // 财务(ALL)

// Sample contracts
Db::execute("INSERT INTO contract (id, contract_no, title, category, status, amount, party_a_name, party_b_name, effective_date, expiry_date, content, file_url, keywords, creator_id, created_at, updated_at) VALUES (1, 'HT-20260709-0001', '软件技术服务合同', 'SERVICE', 'DRAFT', 150000, '我方公司', '上海科技有限公司', '2026-07-01', '2027-07-01', '双方签署软件技术服务协议。\n\n合同金额：15万元（含税）\n付款方式：签约后付30%，验收后付60%，质保期满付10%\n服务期限：1年，含3个月免费运维\n知识产权：项目源码及文档归甲方所有', '[{\"url\":\"/uploads/demo/service-contract.pdf\",\"name\":\"软件技术服务协议.pdf\",\"size\":245760}]', '软件,技术服务,含税', 1, datetime('now','localtime'), datetime('now','localtime'))");
Db::execute("INSERT INTO contract (id, contract_no, title, category, status, amount, party_a_name, party_b_name, effective_date, expiry_date, content, file_url, keywords, creator_id, created_at, updated_at) VALUES (2, 'HT-20260709-0002', '设备采购合同', 'PURCHASE', 'PENDING_APPROVAL', 88000, '我方公司', '深圳智能制造有限公司', '2026-08-01', '2027-08-01', '采购智能制造设备一批。\n\n合同金额：8.8万元（含13%增值税）\n付款方式：预付30%，发货前付50%，验收后付20%\n交货期：合同生效后45日内\n质保期：2年', '[{\"url\":\"/uploads/demo/equipment-order.pdf\",\"name\":\"设备采购订单.pdf\",\"size\":358400}]', '设备,采购,制造', 1, datetime('now','localtime'), datetime('now','localtime'))");
Db::execute("INSERT INTO contract (id, contract_no, title, category, status, amount, direction, trade_attr, party_a_name, party_b_name, effective_date, expiry_date, content, file_url, keywords, creator_id, created_at, updated_at) VALUES (3, 'HT-20260709-0003', 'NDA保密协议', 'NDA', 'ARCHIVED', 0, '', 0, '我方公司', '北京创新信息技术有限公司', '2026-07-01', '2029-07-01', '双方就业务合作涉及的商业秘密签署保密协议。\n\n保密范围：技术资料、客户信息、财务数据、商业计划\n保密期限：协议终止后3年\n违约责任：赔偿全部损失并支付违约金', '[{\"url\":\"/uploads/demo/nda-agreement.pdf\",\"name\":\"保密协议盖章版.pdf\",\"size\":122880}]', '保密,NDA,协议', 1, datetime('now','localtime'), datetime('now','localtime'))");
Db::execute("INSERT INTO contract (id, contract_no, title, category, status, amount, party_a_name, party_b_name, effective_date, expiry_date, content, file_url, keywords, creator_id, created_at, updated_at) VALUES (4, 'HT-20260709-0004', '办公室租赁合同', 'LEASE', 'EXECUTING', 240000, '我方公司', '杭州电商科技公司', '2026-06-01', '2028-06-01', '租赁办公场地。\n\n面积：500㎡\n月租金：2万元（含物业费）\n付款方式：押三付一，月结\n地址：杭州市滨江区XX路XX号\n用途：日常办公', '[{\"url\":\"/uploads/demo/lease-contract.pdf\",\"name\":\"租赁合同扫描件.pdf\",\"size\":491520}]', '租赁,办公,场地', 1, datetime('now','localtime'), datetime('now','localtime'))");

// 演示合同：用于直观对比数据范围（管理员看全部 / 部门经理看本部门 dept_id=1 / 普通员工·财务看自己 owner_id）
Db::execute("INSERT INTO contract (contract_no, title, category, status, amount, party_a_name, party_b_name, owner_id, dept_id, creator_id, content, file_url, keywords, created_at, updated_at) VALUES ('HT-DEMO-0001', '员工甲-采购合同', 'PURCHASE', 'DRAFT', 12000, '我方公司', '演示供应商A', (SELECT id FROM user WHERE username='employee01'), 1, (SELECT id FROM user WHERE username='employee01'), '员工甲提交的采购合同演示数据。', '[]', '演示,采购', datetime('now','localtime'), datetime('now','localtime'))");
Db::execute("INSERT INTO contract (contract_no, title, category, status, amount, party_a_name, party_b_name, owner_id, dept_id, creator_id, content, file_url, keywords, created_at, updated_at) VALUES ('HT-DEMO-0002', '财务-服务合同', 'SERVICE', 'DRAFT', 30000, '我方公司', '演示供应商B', (SELECT id FROM user WHERE username='finance01'), 1, (SELECT id FROM user WHERE username='finance01'), '财务提交的對外服务合同演示数据。', '[]', '演示,服务', datetime('now','localtime'), datetime('now','localtime'))");
Db::execute("INSERT INTO contract (contract_no, title, category, status, amount, party_a_name, party_b_name, owner_id, dept_id, creator_id, content, file_url, keywords, created_at, updated_at) VALUES ('HT-DEMO-0003', '经理-框架协议', 'SERVICE', 'DRAFT', 50000, '我方公司', '演示供应商C', (SELECT id FROM user WHERE username='manager01'), 1, (SELECT id FROM user WHERE username='manager01'), '部门经理提交的框架协议演示数据。', '[]', '演示,框架', datetime('now','localtime'), datetime('now','localtime'))");

// 本公司主体（company_profile）—— 默认主体用于新建合同自动带出
Db::execute("INSERT INTO company_profile (id, name, short_name, unified_social_credit_code, invoice_tax_rate, is_default, created_at, updated_at) VALUES (1, '义乌十八腔网络科技有限公司', '十八腔', '91330782MADEMO0001', 0.06, 1, datetime('now','localtime'), datetime('now','localtime'))");
Db::execute("INSERT INTO company_profile (id, name, short_name, unified_social_credit_code, invoice_tax_rate, is_default, created_at, updated_at) VALUES (2, '义乌十八腔文化传媒有限公司', '十八腔传媒', '91330782MADEMO0002', 0.13, 0, datetime('now','localtime'), datetime('now','localtime'))");

// 演示合同回填签约主体（默认主体 id=1）
Db::execute("UPDATE contract SET our_company_id = 1 WHERE our_company_id = 0");

// 资料库（resource_library）示例
Db::execute("INSERT INTO resource_library (id, category, title, file_url, file_name, file_size, description, company_id, owner_id, created_at, updated_at) VALUES (1, 'TEMPLATE', '媒体投放服务合同范本', '/uploads/library/demo/media-template.docx', '媒体投放服务合同范本.docx', 102400, '标准媒体投放服务合同范本，含投放条款、KPI考核与结算方式，供拟定时参考。', 0, 1, datetime('now','localtime'), datetime('now','localtime'))");
Db::execute("INSERT INTO resource_library (id, category, title, file_url, file_name, file_size, description, company_id, owner_id, created_at, updated_at) VALUES (2, 'INVOICE', '十八腔（主体1）开票资料', '/uploads/library/demo/invoice-profile1.pdf', '十八腔开票资料.pdf', 81920, '主体1 增值税开票资料：名称/税号/开户行/账号，签订合同开票时参考。', 1, 1, datetime('now','localtime'), datetime('now','localtime'))");
Db::execute("INSERT INTO resource_library (id, category, title, file_url, file_name, file_size, description, company_id, owner_id, created_at, updated_at) VALUES (3, 'CLAUSE', '保密与竞业限制标准条款', '/uploads/library/demo/clause-nda.docx', '保密与竞业限制标准条款.docx', 61440, '通用保密、知识产权与竞业限制条款范本，可摘抄到合同概要。', 0, 1, datetime('now','localtime'), datetime('now','localtime'))");

// Approval flows
Db::execute("INSERT INTO approval_flow (id, name, code, min_amount, max_amount, nodes, cc_list, status, creator_id) VALUES (1, '标准审批', 'STANDARD', 0, 100000, '[{\"name\":\"法务审批\",\"type\":\"ROLE\",\"role_code\":\"legal\",\"mode\":\"OR\"},{\"name\":\"部门经理审批\",\"type\":\"ROLE\",\"role_code\":\"manager\",\"mode\":\"OR\"}]', '', 1, 1)");
Db::execute("INSERT INTO approval_flow (id, name, code, min_amount, max_amount, nodes, cc_list, status, creator_id) VALUES (2, '大额审批', 'LARGE', 100000.01, 99999999.99, '[{\"name\":\"部门经理审批\",\"type\":\"ROLE\",\"role_code\":\"manager\",\"mode\":\"OR\"},{\"name\":\"财务会签\",\"type\":\"ROLE\",\"role_code\":\"finance\",\"mode\":\"AND\"}]', '{\"role_codes\":[\"finance\"],\"cc_user_ids\":[]}', 1, 1)");
Db::execute("INSERT INTO approval_flow (id, name, code, min_amount, max_amount, nodes, cc_list, status, creator_id) VALUES (3, '简易审批', 'QUICK', 0, 10000, '[{\"name\":\"部门经理审批\",\"type\":\"ROLE\",\"role_code\":\"manager\",\"mode\":\"OR\"}]', '', 1, 1)");

// F1/F2：发票申请表单预置字段池（系统字段禁止删除、仅可停用/排序；后台可新增自定义字段）
Db::execute("INSERT INTO invoice_form_field (id, field_key, field_label, field_type, field_options, required, enabled, sort_order, is_system) VALUES (1, 'our_company_id', '开票主体', 'company', '[]', 1, 1, 10, 1)");
Db::execute("INSERT INTO invoice_form_field (id, field_key, field_label, field_type, field_options, required, enabled, sort_order, is_system) VALUES (2, 'content_desc', '开票内容', 'select', '[{\"value\":\"软件开发服务费\",\"label\":\"软件开发服务费\"},{\"value\":\"咨询服务费\",\"label\":\"咨询服务费\"},{\"value\":\"运维服务费\",\"label\":\"运维服务费\"},{\"value\":\"硬件销售费\",\"label\":\"硬件销售费\"},{\"value\":\"其他\",\"label\":\"其他\"}]', 1, 1, 80, 1)");
Db::execute("INSERT INTO invoice_form_field (id, field_key, field_label, field_type, field_options, required, enabled, sort_order, is_system) VALUES (3, 'invoice_type', '开票类型', 'select', '[{\"value\":\"VAT_SPECIAL\",\"label\":\"我要开增值税专用发票\"},{\"value\":\"VAT_NORMAL\",\"label\":\"我要开普通发票\"}]', 1, 1, 30, 1)");
Db::execute("INSERT INTO invoice_form_field (id, field_key, field_label, field_type, field_options, required, enabled, sort_order, is_system) VALUES (4, 'amount', '含税金额（元）', 'number', '[]', 1, 1, 40, 1)");
Db::execute("INSERT INTO invoice_form_field (id, field_key, field_label, field_type, field_options, required, enabled, sort_order, is_system) VALUES (5, 'tax_rate', '税率', 'select', '[{\"value\":\"0.01\",\"label\":\"1%\"},{\"value\":\"0.03\",\"label\":\"3%\"},{\"value\":\"0.05\",\"label\":\"5%\"},{\"value\":\"0.06\",\"label\":\"6%\"},{\"value\":\"0.09\",\"label\":\"9%\"},{\"value\":\"0.13\",\"label\":\"13%\"}]', 0, 0, 50, 1)");
Db::execute("INSERT INTO invoice_form_field (id, field_key, field_label, field_type, field_options, required, enabled, sort_order, is_system) VALUES (6, 'invoice_title', '发票抬头', 'text', '[]', 0, 1, 60, 1)");
Db::execute("INSERT INTO invoice_form_field (id, field_key, field_label, field_type, field_options, required, enabled, sort_order, is_system) VALUES (7, 'tax_no', '税号', 'text', '[]', 0, 1, 70, 1)");
Db::execute("INSERT INTO invoice_form_field (id, field_key, field_label, field_type, field_options, required, enabled, sort_order, is_system) VALUES (8, 'remark', '申请说明', 'textarea', '[]', 0, 1, 90, 1)");
Db::execute("INSERT INTO invoice_form_field (id, field_key, field_label, field_type, field_options, required, enabled, sort_order, is_system) VALUES (9, 'customer_id', '开票客户', 'customer', '[]', 0, 1, 55, 1)");

// 合同类型预设（模板重构为“合同类型预设”，套用即带出默认分类/方向/建议审批流/必填提醒）
Db::execute("INSERT INTO contract_template (id, name, code, category, status, current_version, content, fields_schema, default_direction, default_trade_attr, default_flow_id, tips, creator_id, created_at, updated_at) VALUES (1, '媒体投放服务合同', 'TPL-MEDIA', 'SERVICE', 'PUBLISHED', 1, '', '[{\"key\":\"platform\",\"label\":\"投放平台/渠道\",\"type\":\"text\",\"required\":true},{\"key\":\"period\",\"label\":\"投放周期\",\"type\":\"text\",\"required\":true},{\"key\":\"kpi\",\"label\":\"KPI考核指标\",\"type\":\"textarea\",\"required\":true},{\"key\":\"settlement\",\"label\":\"结算方式\",\"type\":\"select\",\"required\":true,\"options\":[\"预付\",\"月结\",\"季结\",\"CPS分成\"]},{\"key\":\"account_period\",\"label\":\"账期(天)\",\"type\":\"number\",\"required\":false}]', 'sales', 1, 1, '必填：投放平台/渠道、投放周期、KPI考核指标、结算方式与账期。', 1, datetime('now','localtime'), datetime('now','localtime'))");
Db::execute("INSERT INTO contract_template (id, name, code, category, status, current_version, content, fields_schema, default_direction, default_trade_attr, default_flow_id, tips, creator_id, created_at, updated_at) VALUES (2, '供应商采购合同', 'TPL-PURCHASE', 'PURCHASE', 'PUBLISHED', 1, '', '[{\"key\":\"deliverables\",\"label\":\"交付物清单\",\"type\":\"textarea\",\"required\":true},{\"key\":\"acceptance\",\"label\":\"验收标准\",\"type\":\"textarea\",\"required\":true},{\"key\":\"warranty\",\"label\":\"质保期\",\"type\":\"text\",\"required\":false},{\"key\":\"quote_no\",\"label\":\"报价单编号\",\"type\":\"text\",\"required\":false}]', 'purchase', 1, 1, '必填：供应商资质、报价单、交付物清单、验收标准与质保期。', 1, datetime('now','localtime'), datetime('now','localtime'))");
Db::execute("INSERT INTO contract_template (id, name, code, category, status, current_version, content, fields_schema, default_direction, default_trade_attr, default_flow_id, tips, creator_id, created_at, updated_at) VALUES (3, '年度框架协议', 'TPL-FRAMEWORK', 'SERVICE', 'PUBLISHED', 1, '', '[]', 'sales', 1, 2, '注意关联执行订单；约定年度预算上限、结算周期与单价区间。', 1, datetime('now','localtime'), datetime('now','localtime'))");

// System config
Db::execute("INSERT INTO system_config (config_key, config_value, group_name) VALUES ('contract_categories', '{\"SALES\":\"销售合同\",\"PURCHASE\":\"采购合同\",\"LABOR\":\"劳动合同\",\"LEASE\":\"租赁合同\",\"NDA\":\"保密协议\",\"SERVICE\":\"服务合同\",\"OTHER\":\"其他\"}', 'contract')");
Db::execute("INSERT INTO system_config (config_key, config_value, group_name) VALUES ('site_name', '合同管理系统', 'system')");
Db::execute("INSERT INTO system_config (config_key, config_value, group_name) VALUES ('site_version', '1.0.0', 'system')");
// 页脚版权信息（v2.34.0：系统设置「系统配置」页可维护；缺失时 BaseController 回退默认文案）
Db::execute("INSERT INTO system_config (config_key, config_value, group_name) VALUES ('copyright', '© 2026 合同管理系统 版权所有', 'system')");
// PC 端新手引导开关（2026-07-25：系统设置「系统配置」页可维护；默认关闭，与用户偏好一致）
Db::execute("INSERT INTO system_config (config_key, config_value, group_name) VALUES ('guide_enabled', '0', 'system')");
// 业务规则（2026-08-01：系统设置「系统配置」页可维护；定时任务读取，缺省用下方默认值）
Db::execute("INSERT INTO system_config (config_key, config_value, group_name) VALUES ('rule_pool_release_days', '30', 'rule')");
Db::execute("INSERT INTO system_config (config_key, config_value, group_name) VALUES ('rule_expire_remind_days', '30,15,7,3,1', 'rule')");
Db::execute("INSERT INTO system_config (config_key, config_value, group_name) VALUES ('rule_payment_remind_days', '7,3,1', 'rule')");
Db::execute("INSERT INTO system_config (config_key, config_value, group_name) VALUES ('weekly_report_dd_enabled', '1', 'rule')");

// Dict 字典配置
Db::execute("INSERT INTO system_config (config_key, config_value, group_name) VALUES ('dict_contract_category', '{\"SALES\":\"销售合同\",\"PURCHASE\":\"采购合同\",\"LABOR\":\"劳动合同\",\"LEASE\":\"租赁合同\",\"NDA\":\"保密协议\",\"SERVICE\":\"服务合同\",\"OTHER\":\"其他\"}', 'dict')");
Db::execute("INSERT INTO system_config (config_key, config_value, group_name) VALUES ('dict_contract_status', '{\"DRAFT\":\"草稿\",\"PENDING_APPROVAL\":\"待审批\",\"APPROVED\":\"已通过\",\"REJECTED\":\"已驳回\",\"SIGNED\":\"历史已签\",\"EXECUTING\":\"执行中\",\"COMPLETED\":\"已完成\",\"TERMINATED\":\"已终止\",\"EXPIRED\":\"已到期\",\"ARCHIVED\":\"已归档\"}', 'dict')");
Db::execute("INSERT INTO system_config (config_key, config_value, group_name) VALUES ('dict_supplier_type', '{\"MEDIA\":\"媒体渠道\",\"PRODUCTION\":\"制作方\",\"FREELANCER\":\"自由职业者\",\"MATERIAL\":\"物料供应商\",\"SERVICE\":\"服务商\",\"OTHER\":\"其他\"}', 'dict')");
Db::execute("INSERT INTO system_config (config_key, config_value, group_name) VALUES ('dict_invoice_type', '{\"VAT_SPECIAL\":\"我要开增值税专用发票\",\"VAT_NORMAL\":\"我要开普通发票\",\"E_INVOICE\":\"电子发票\",\"OTHER\":\"其他\"}', 'dict')");
Db::execute("INSERT INTO system_config (config_key, config_value, group_name) VALUES ('dict_invoice_status', '{\"APPLIED\":\"已申请\",\"ISSUED\":\"已开票\",\"REJECTED\":\"已退回\",\"CANCELLED\":\"已作废\"}', 'dict')");
Db::execute("INSERT INTO system_config (config_key, config_value, group_name) VALUES ('dict_tax_rate', '{\"0.13\":\"13% 货物销售\",\"0.09\":\"9% 交通/建筑\",\"0.06\":\"6% 现代服务\",\"0.03\":\"3% 小规模\",\"0\":\"0% 免税\"}', 'dict')");
Db::execute("INSERT INTO system_config (config_key, config_value, group_name) VALUES ('dict_payment_method', '{\"BANK\":\"银行转账\",\"CASH\":\"现金\",\"CHECK\":\"支票\",\"ALIPAY\":\"支付宝\",\"WECHAT\":\"微信支付\"}', 'dict')");
Db::execute("INSERT INTO system_config (config_key, config_value, group_name) VALUES ('dict_payment_status', '{\"PENDING\":\"待收\",\"PAID\":\"已收\",\"OVERDUE\":\"逾期\"}', 'dict')");
Db::execute("INSERT INTO system_config (config_key, config_value, group_name) VALUES ('dict_customer_lifecycle', '{\"POTENTIAL\":\"客户\",\"ACTIVE\":\"成交\",\"INACTIVE\":\"公海\"}', 'dict')");
Db::execute("INSERT INTO system_config (config_key, config_value, group_name) VALUES ('dict_customer_source', '{\"MANUAL\":\"手动录入\",\"IMPORT\":\"批量导入\",\"DINGTALK\":\"钉钉同步\",\"RECOMMEND\":\"客户推荐\",\"AD\":\"广告获客\",\"OTHER\":\"其他\"}', 'dict')");
Db::execute("INSERT INTO system_config (config_key, config_value, group_name) VALUES ('dict_data_scope', '{\"ALL\":\"全部数据\",\"DEPT\":\"本部门\",\"DEPT_AND_CHILD\":\"本部门及子部门\",\"CUSTOM\":\"自定义部门\",\"SELF\":\"仅自己\"}', 'dict')");
Db::execute("INSERT INTO system_config (config_key, config_value, group_name) VALUES ('dict_project_status', '{\"ACTIVE\":\"进行中\",\"DONE\":\"已完成\",\"ARCHIVED\":\"已归档\",\"TERMINATED\":\"已终止\"}', 'dict')");
// 回款里程碑（P1-5）：支付记录 milestone 字段选项，须带 group_name='dict' 才能出现在系统设置字典 tab
Db::execute("INSERT INTO system_config (config_key, config_value, group_name) VALUES ('dict_payment_milestone', '{\"DOWN_PAYMENT\":\"预付款\",\"MID_TERM\":\"中期款\",\"FINAL_PAYMENT\":\"尾款\",\"RETENTION\":\"质保金\"}', 'dict')");

// 演示项目（P2-5）：把演示合同归集到项目，展示按项目聚合
Db::execute("INSERT INTO project (id, name, code, customer_id, owner_id, dept_id, status, budget, start_date, end_date, remark, created_at, updated_at) VALUES (1, '上海科技-年度技术服务', 'PRJ-2026-001', 0, 1, 0, 'ACTIVE', 200000, '2026-07-01', '2027-07-01', '上海科技有限公司年度软件技术服务项目。', datetime('now','localtime'), datetime('now','localtime'))");
Db::execute("INSERT INTO project (id, name, code, customer_id, owner_id, dept_id, status, budget, start_date, end_date, remark, created_at, updated_at) VALUES (2, '智能制造设备采购', 'PRJ-2026-002', 0, 1, 0, 'ACTIVE', 100000, '2026-08-01', '2027-08-01', '深圳智能制造设备采购项目。', datetime('now','localtime'), datetime('now','localtime'))");
// 合同归集到项目：合同1→项目1，合同2→项目2
Db::execute("UPDATE contract SET project_id = 1 WHERE id = 1");
Db::execute("UPDATE contract SET project_id = 2 WHERE id = 2");

// SQLite 并发加固：WAL 模式（读写并发，多读者+单写者），降低快速点击菜单导致的写锁冲突
Db::execute("PRAGMA journal_mode=WAL");

echo "Database initialized successfully!\n";
echo "Default login: admin / password\n";
