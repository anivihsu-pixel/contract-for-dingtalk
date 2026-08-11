<?php
// +----------------------------------------------------------------------
// | 本地开发环境仿真数据填充脚本
// | 仅作用于本地 SQLite dev 库（runtime/data/contract.db），不入库、不改业务代码。
// | 用途：给客户预览时，系统中已铺满覆盖全部流程/全部审批状态的拟真数据。
// | 运行：php database/seed_demo.php
// | 说明：可重复执行（先清空业务表再重灌）。
// | 口令：演示账号口令由本脚本随机生成并打印，请查阅运行输出。如需固定口令
// |       可手动修改下方 $pwdAdmin / $pwd 变量。
// +----------------------------------------------------------------------

define('ROOT_PATH', __DIR__ . '/../');
$dbFile = ROOT_PATH . 'runtime/data/contract.db';
if (!is_file($dbFile)) {
    die("数据库不存在: $dbFile\n请先执行 php database/init_sqlite.php 初始化。\n");
}
$db = new SQLite3($dbFile);
$db->exec('PRAGMA foreign_keys=OFF');

// ---------- 通用插入助手 ----------
function insertRow($db, string $table, array $data): int
{
    $cols = array_keys($data);
    $ph   = array_map(fn($c) => ':' . $c, $cols);
    $sql  = "INSERT INTO $table (" . implode(',', $cols) . ") VALUES (" . implode(',', $ph) . ")";
    $stmt = $db->prepare($sql);
    foreach ($data as $c => $v) {
        $stmt->bindValue(':' . $c, $v);
    }
    $stmt->execute();
    return $db->lastInsertRowID();
}
function now(): string { return date('Y-m-d H:i:s'); }
function day(int $offset): string { return date('Y-m-d', strtotime("$offset days")); }

echo "开始清空业务表...\n";
// 清空业务表（保留 角色/权限/用户角色权限/审批流/系统配置/本公司主体 等基础配置）
foreach ([
    'approval_record', 'approval_instance', 'contract_invoice',
    'payment_record', 'contract_revision', 'remind_log', 'customer_claim_record',
    'customer_transfer_record', 'project', 'customer', 'supplier',
    'resource_library', 'audit_log', 'department', 'user', 'user_role', 'contract'
] as $t) {
    $db->exec("DELETE FROM $t");
}

// ================= 部门 =================
echo "写入部门...\n";
$depts = [
    [1, '总经办', 0],
    [2, '法务部', 1],
    [3, '财务部', 1],
    [4, '商务部', 1],
    [5, '采购部', 1],
];
foreach ($depts as [$id, $name, $pid]) {
    insertRow($db, 'department', [
        'id' => $id, 'name' => $name, 'parent_id' => $pid,
        'dingtalk_dept_id' => 0, 'sort_order' => $id
    ]);
}

// ================= 用户（演示账号） =================
echo "写入用户...\n";
// 演示口令随机生成（每次 seed 不同），运行末尾会打印
$demoPwdAdmin  = bin2hex(random_bytes(6));  // admin 口令（随机 12 位十六进制）
$demoPwdUser   = bin2hex(random_bytes(6));  // 普通账号口令（随机 12 位十六进制）
$pwd      = password_hash($demoPwdUser, PASSWORD_BCRYPT);    // 普通演示账号统一口令
$pwdAdmin = password_hash($demoPwdAdmin, PASSWORD_BCRYPT);   // admin 专用口令
$usersSeed = [
    // username, name, dept_id, is_admin, roles[]
    ['admin',     '系统管理员', 1, 1, [1, 3]],   // 兼任法务，保证法务节点有审批人
    ['manager01', '张经理',     4, 0, [2]],
    ['employee01','李员工',     4, 0, [5]],
    ['finance01', '王财务',     3, 0, [4]],
    ['sales02',   '赵销售',     4, 0, [5]],
    ['purchase02', '钱采购',    5, 0, [5]],
];
$uid = [];
foreach ($usersSeed as [$username, $name, $deptId, $isAdmin, $roles]) {
    $id = insertRow($db, 'user', [
        'username'   => $username,
        'password'   => ($username === 'admin') ? $pwdAdmin : $pwd,
        'name'       => $name,
        'email'      => $username . '@demo.com',
        'mobile'     => '138' . str_pad((string)mt_rand(0, 99999999), 8, '0', STR_PAD_LEFT),
        'dept_id'    => $deptId,
        'is_admin'   => $isAdmin,
        'status'     => 1,
        'force_reset'=> 0,   // 演示环境关闭强制改密，便于直接登录预览
    ]);
    $uid[$username] = $id;
    foreach ($roles as $rid) {
        $db->exec("INSERT INTO user_role (user_id, role_id) VALUES ($id, $rid)");
    }
}
// 角色 -> 审批人映射（用于审批节点）
$approverByRole = [
    'legal'   => $uid['admin'],     // admin 兼任法务
    'manager' => $uid['manager01'],
    'finance' => $uid['finance01'],
];

// ================= 客户（12 家，覆盖各等级/来源/归属） =================
echo "写入客户...\n";
$customers = [
    ['上海智联科技有限公司',     '91310115MA1K3X1A2B', '陈晓东', '王磊', '13800001111', 'chen@zhilian.com',   '上海市浦东新区张江高科技园区博云路2号',   2, 'MANUAL',   $uid['sales02']],
    ['深圳前海云创信息技术有限公司','91440300MA5DA1B7X9', '刘伟',   '赵敏', '13800002222', 'liu@yunchuang.cn',  '深圳市南山区粤海街道科技中一路8号',       3, 'DINGTALK', $uid['sales02']],
    ['杭州数云数据服务有限公司',  '91330108MA27X3C4D5', '孙强',   '周婷', '13800003333', 'sun@shuyun.com',     '杭州市滨江区江南大道388号',              1, 'IMPORT',   $uid['employee01']],
    ['北京华夏文创传媒有限公司',  '91110105MA01C7E9F2', '李明',   '吴芳', '13800004444', 'li@huaxia.com',      '北京市朝阳区建国路88号SOHO现代城',        'RECOMMEND',$uid['sales02']],
    ['广州盈通供应链有限公司',    '91440101MA5CL2H6K3', '黄蓉',   '郑凯', '13800005555', 'huang@yingtong.com', '广州市天河区珠江新城华夏路16号',         'AD',       $uid['employee01']],
    ['成都天府软件外包有限公司',  '91510100MA6CXG9P0Q', '周杰',   '冯雪', '13800006666', 'zhou@tianfu.com',    '成都市高新区天府大道中段666号',          'MANUAL',   $uid['admin']],
    ['武汉光谷智能装备有限公司',  '91420100MA4KX8L2N7', '吴昊',   '蒋琳', '13800007777', 'wu@guanggu.com',     '武汉市东湖新技术开发区光谷大道77号',     'IMPORT',   $uid['sales02']],
    ['南京金陵文化影视有限公司',  '91320100MA1MX9R4T8', '徐峰',   '韩雪', '13800008888', 'xu@jinling.com',     '南京市鼓楼区中山路18号',                'RECOMMEND',$uid['employee01']],
    ['苏州工业园区精密制造有限公司','91320594MA1MQQ3B5C','何静',   '曹斌', '13800009999', 'he@jingmi.com',     '苏州工业园区苏虹中路200号',              'DINGTALK', $uid['sales02']],
    ['重庆两江数字科技有限责任公司','91500000MA5UQ6D2X1', '罗刚',   '袁媛', '13800010001', 'luo@liangjiang.com','重庆市渝北区黄山大道中段66号',         'AD',       $uid['admin']],
    ['西安丝路会展服务有限公司',  '91610131MA6U8F3K9Y', '高翔',   '邓娜', '13800010002', 'gao@silu.com',       '西安市高新区科技路33号',                'MANUAL',   $uid['employee01']],
    ['青岛海蓝生物科技有限公司',  '91370212MA3MXP7H4Z', '宋涛',   '叶倩', '13800010003', 'song@hailan.com',    '青岛市崂山区松岭路169号',               'IMPORT',   $uid['sales02']],
];
$cid = [];
foreach ($customers as $i => [$name, $code, $legal, $contact, $mobile, $email, $addr, $source, $owner]) {
    $id = insertRow($db, 'customer', [
        'name' => $name, 'credit_code' => $code, 'legal_person' => $legal,
        'contact_name' => $contact, 'contact_mobile' => $mobile, 'contact_email' => $email,
        'address' => $addr, 'source' => $source, 'status' => 1,
        'is_self' => 0, 'owner_id' => $owner, 'dept_id' => 4,
        'is_deleted' => 0,
    ]);
    $cid[$i] = $id;
}
// 客户认领 / 交接流水
insertRow($db, 'customer_claim_record',  ['customer_id' => $cid[0], 'user_id' => $uid['sales02']]);
insertRow($db, 'customer_claim_record',  ['customer_id' => $cid[4], 'user_id' => $uid['employee01']]);
insertRow($db, 'customer_transfer_record', ['customer_id' => $cid[2], 'from_user_id' => $uid['admin'], 'to_user_id' => $uid['employee01']]);
insertRow($db, 'customer_transfer_record', ['customer_id' => $cid[6], 'from_user_id' => $uid['sales02'], 'to_user_id' => $uid['employee01']]);

// ================= 供应商（8 家，覆盖各类型） =================
echo "写入供应商...\n";
$suppliers = [
    ['字节跳动巨量引擎（代理）', 'MEDIA',       '媒体渠道', '林涛', '13900001111', 'lin@media.com',   '北京市海淀区知春路甲48号', $uid['purchase02']],
    ['分众传媒股份有限公司',     'MEDIA',       '媒体渠道', '杨柳', '13900002222', 'yang@media.com',  '上海市黄浦区延安东路618号', $uid['purchase02']],
    ['宁波模具精密制造厂',       'MATERIAL',    '物料供应商','徐刚', '13900003333', 'xu@mucai.com',   '宁波市北仑区大碶街道',     $uid['purchase02']],
    ['杭州影视制作工作室',       'PRODUCTION',  '制作方',   '何丽', '13900004444', 'he@film.com',     '杭州市西湖区文三路',       $uid['purchase02']],
    ['自由插画师·李默',          'FREELANCER',  '自由职业者','李默', '13900005555', 'limo@free.com',   '远程协作',               $uid['purchase02']],
    ['顺丰供应链服务有限公司',   'SERVICE',     '服务商',   '吴琼', '13900006666', 'wu@sf.com',       '深圳市福田区新洲路',     $uid['purchase02']],
    ['广州印刷包装材料商行',     'MATERIAL',    '物料供应商','郑伟', '13900007777', 'zheng@print.com', '广州市白云区嘉禾街',     $uid['purchase02']],
    ['上海云算力服务商',         'SERVICE',     '服务商',   '冯磊', '13900008888', 'feng@cloud.com',  '上海市徐汇区漕河泾',     $uid['purchase02']],
];
$sid = [];
foreach ($suppliers as $i => [$name, $type, $typeName, $contact, $mobile, $email, $addr, $owner]) {
    $id = insertRow($db, 'supplier', [
        'name' => $name, 'type' => $type, 'contact_name' => $contact,
        'contact_mobile' => $mobile, 'contact_email' => $email, 'address' => $addr,
        'status' => 1, 'owner_id' => $owner, 'dept_id' => 5, 'is_deleted' => 0,
    ]);
    $sid[$i] = $id;
}

// ================= 项目（4 个） =================
echo "写入项目...\n";
$pid = [];
$pid[0] = insertRow($db, 'project', ['name'=>'智联科技-年度数字化服务','code'=>'PRJ-2026-001','customer_id'=>$cid[0],'owner_id'=>$uid['sales02'],'dept_id'=>4,'status'=>'ACTIVE','budget'=>800000,'start_date'=>day(-120),'end_date'=>day(245),'remark'=>'智联科技年度数字化服务总包项目。']);
$pid[1] = insertRow($db, 'project', ['name'=>'前海云创-媒体投放专项','code'=>'PRJ-2026-002','customer_id'=>$cid[1],'owner_id'=>$uid['sales02'],'dept_id'=>4,'status'=>'ACTIVE','budget'=>500000,'start_date'=>day(-90),'end_date'=>day(180),'remark'=>'前海云创 Q3 媒体投放专项。']);
$pid[2] = insertRow($db, 'project', ['name'=>'精密制造-设备采购项目','code'=>'PRJ-2026-003','customer_id'=>$cid[8],'owner_id'=>$uid['purchase02'],'dept_id'=>5,'status'=>'ACTIVE','budget'=>300000,'start_date'=>day(-60),'end_date'=>day(120),'remark'=>'精密制造设备采购与安装项目。']);
$pid[3] = insertRow($db, 'project', ['name'=>'数云数据-平台运维框架','code'=>'PRJ-2026-004','customer_id'=>$cid[2],'owner_id'=>$uid['employee01'],'dept_id'=>4,'status'=>'DONE','budget'=>200000,'start_date'=>day(-200),'end_date'=>day(-20),'remark'=>'数云数据平台运维年度框架（已完成）。']);

// ================= 合同（16 份，覆盖全部状态 + 撤回 + 多节点进度 + 逾期） =================
echo "写入合同及关联审批/回款/发票/签署...\n";
$ourCo = '义乌十八腔网络科技有限公司';

// 审批流节点定义（与 init_sqlite.php 中 approval_flow.nodes 一致）
$flowNodes = [
    1 => [ // STANDARD
        ['name'=>'法务审批','role'=>'legal'],
        ['name'=>'部门经理审批','role'=>'manager'],
    ],
    2 => [ // LARGE
        ['name'=>'部门经理审批','role'=>'manager'],
        ['name'=>'财务会签','role'=>'finance'],
    ],
    3 => [ // QUICK
        ['name'=>'部门经理审批','role'=>'manager'],
    ],
];

/**
 * 写入一条审批实例 + 节点记录
 * @param string $instStatus 实例终态 PENDING/APPROVED/REJECTED/RECALLED
 * @param array  $nodeActions 各节点动作，如 ['APPROVED','PENDING'] 表示第1节点已同意、第2节点待审
 * @param int    $currentNode 当前节点序号
 */
function seedApproval($db, $contractId, $flowId, $instStatus, array $nodeActions, int $currentNode, $submitter, $finishedAt)
{
    global $flowNodes, $approverByRole;
    $instId = insertRow($db, 'approval_instance', [
        'contract_id' => $contractId,
        'flow_id' => $flowId,
        'status' => $instStatus,
        'current_node_order' => $currentNode,
        'submitted_by' => $submitter,
        'submitted_at' => day(-30),
        'finished_at' => $finishedAt,
    ]);
    $nodes = $flowNodes[$flowId];
    foreach ($nodes as $idx => $node) {
        $action = $nodeActions[$idx] ?? 'PENDING';
        if ($action === 'SKIP') continue; // 未到达的节点不生成记录
        $comment = '';
        if ($action === 'APPROVED') $comment = '同意，条款无误，按流程推进。';
        if ($action === 'REJECTED') $comment = '第'.($idx+1).'节点：风险条款需修订后重新提交。';
        insertRow($db, 'approval_record', [
            'instance_id' => $instId,
            'node_order' => $idx + 1,
            'node_name' => $node['name'],
            'approver_id' => $approverByRole[$node['role']],
            'action' => $action,
            'comment' => $comment,
            'acted_at' => $action === 'PENDING' ? null : day(-28 + $idx),
        ]);
    }
    return $instId;
}

function seedPayments($db, $contractId, $owner, array $plans)
{
    foreach ($plans as $p) {
        // $p = [type, amount, status, plannedOffset, actualOffset|null, method, desc]
        [$type, $amount, $status, $plannedOff, $actualOff, $method, $desc] = $p;
        insertRow($db, 'payment_record', [
            'contract_id' => $contractId,
            'payment_type' => $type,
            'amount' => $amount,
            'planned_date' => day($plannedOff),
            'actual_date' => ($actualOff === null || $actualOff === '') ? null : day($actualOff),
            'payment_method' => $method,
            'status' => $status,
            'description' => $desc,
            'operator_id' => $owner,
            'paid_amount' => $status === 'PAID' ? $amount : 0,
        ]);
    }
}

function seedInvoices($db, $contractId, $owner, array $inv)
{
    foreach ($inv as $p) {
        // $p = [amount, taxRate, type, status, issuedOffset|null, title, no]
        [$status, $amount, $taxRate, $type, $issuedOff, $title, $no] = $p;
        $amount  = (float) $amount;
        $taxRate = (float) $taxRate;
        $tax = round($amount * $taxRate / (1 + $taxRate), 2);
        insertRow($db, 'contract_invoice', [
            'contract_id' => $contractId,
            'amount' => $amount,
            'tax_rate' => $taxRate,
            'tax_amount' => $tax,
            'invoice_type' => $type,
            'invoice_title' => $title,
            'tax_no' => '91330782MADEMO0001',
            'invoice_no' => $no,
            'status' => $status,
            'issued_date' => $issuedOff === null ? null : $issuedOff,
            'operator_id' => $owner,
        ]);
    }
}

$noSeq = 1;
function nextNo(): string { global $noSeq; return 'HT-2026-07-' . str_pad($noSeq++, 4, '0', STR_PAD_LEFT); }

// 合同规格：[title, category, direction, trade_attr, status, amount, partyBName, partyBCustId, supplierId, ownerId, flowId, projectId, content, keywords, approval(nodeActions,currentNode,instStatus,finishedAt), payments, invoices]
$contracts = [
    // 1 DRAFT 销售
    ['智联科技-小程序开发服务合同','SERVICE','sales',1,'DRAFT',186000,'上海智联科技有限公司',$cid[0],0,$uid['admin'],1,$pid[0],
     "乙方为甲方提供微信小程序定制开发服务，含需求调研、UI设计、前后端开发、上线部署与一年运维。","小程序,开发,服务",
     null, [], [], null],
    // 2 DRAFT 采购
    ['精密制造-注塑模具采购合同','PURCHASE','purchase',1,'DRAFT',96000,'宁波模具精密制造厂',0,$sid[2],$uid['purchase02'],2,$pid[2],
     "甲方向乙方采购注塑模具一套，含设计、加工、试模与交付。","模具,采购,制造",
     null, [], [], null],
    // 3 PENDING_APPROVAL 节点1（法务待审）销售
    ['前海云创-信息流投放服务合同','SERVICE','sales',1,'PENDING_APPROVAL',420000,'深圳前海云创信息技术有限公司',$cid[1],0,$uid['sales02'],2,$pid[1],
     "乙方为甲方提供抖音/小红书信息流投放服务，含策略、素材、投放与数据复盘。","投放,信息流,服务",
     [['PENDING','SKIP'],1,'PENDING',null],
     [], [], null],
    // 4 PENDING_APPROVAL 节点2（法务已过、经理待审）采购——展示多节点进度
    ['云算力服务年度采购合同','SERVICE','purchase',1,'PENDING_APPROVAL',260000,'上海云算力服务商',0,$sid[7],$uid['purchase02'],2,$pid[2],
     "甲方向乙方采购年度云算力资源，按用量结算。","云算力,采购,服务",
     [['APPROVED','PENDING'],2,'PENDING',null],
     [], [], null],
    // 5 APPROVED 待签署 销售
    ['数云数据-数据平台运维合同','SERVICE','sales',1,'APPROVED',158000,'杭州数云数据服务有限公司',$cid[2],0,$uid['employee01'],1,$pid[3],
     "乙方为甲方提供数据平台日常运维与故障响应服务。","运维,数据,服务",
     [['APPROVED','APPROVED'],2,'APPROVED',day(-25)],
     [], [], ['PENDING', null]],
    // 6 REJECTED 销售（法务驳回）
    ['华夏文创-品牌全案代理合同','SERVICE','sales',1,'REJECTED',320000,'北京华夏文创传媒有限公司',$cid[3],0,$uid['sales02'],2,$pid[1],
     "乙方代理甲方品牌全案策划与执行。","品牌,全案,代理",
     [['REJECTED','SKIP'],1,'REJECTED',day(-20)],
     [], [], null],
    // 7 SIGNED 销售
    ['盈通供应链-仓储系统实施合同','SERVICE','sales',1,'SIGNED',240000,'广州盈通供应链有限公司',$cid[4],0,$uid['admin'],2,$pid[0],
     "乙方为甲方实施仓储管理系统（WMS）并培训。","WMS,实施,系统",
     [['APPROVED','APPROVED'],2,'APPROVED',day(-40)],
     [], [['ISSUED',240000,0.06,'VAT_SPECIAL',day(-38),'广州盈通供应链有限公司','244108761']],
     ['SIGNED',day(-36)]],
    // 8 SIGNED 采购（应付）
    ['分众传媒-电梯广告发布合同','PURCHASE','purchase',1,'SIGNED',180000,'分众传媒股份有限公司',0,$sid[1],$uid['purchase02'],1,$pid[1],
     "甲方向乙方采购电梯电视广告发布资源。","广告,分众,采购",
     [['APPROVED','APPROVED'],2,'APPROVED',day(-35)],
     [], [['ISSUED',180000,0.06,'VAT_SPECIAL',day(-33),'义乌十八腔网络科技有限公司','244108762']],
     ['SIGNED',day(-31)]],
    // 9 EXECUTING 销售（多笔回款，部分已收/待收）
    ['智联科技-CRM系统定制合同','SERVICE','sales',1,'EXECUTING',600000,'上海智联科技有限公司',$cid[0],0,$uid['sales02'],2,$pid[0],
     "乙方为甲方定制 CRM 系统，分阶段交付。","CRM,定制,系统",
     [['APPROVED','APPROVED'],2,'APPROVED',day(-50)],
     [['RECEIVABLE',180000,'PAID',-48,-46,'BANK','首付款30%'],
      ['RECEIVABLE',300000,'PENDING',20,'','BANK','第二期进度款50%'],
      ['RECEIVABLE',120000,'PENDING',120,'','BANK','尾款20%']],
     [['ISSUED',180000,0.06,'VAT_SPECIAL',day(-46),'上海智联科技有限公司','244108763'],
      ['APPLIED',300000,0.06,'VAT_SPECIAL',null,'上海智联科技有限公司','']],
     ['SIGNED',day(-44)]],
    // 10 EXECUTING 采购（应付，部分已付）
    ['影视制作-宣传片拍摄服务合同','PURCHASE','purchase',1,'EXECUTING',95000,'杭州影视制作工作室',0,$sid[3],$uid['purchase02'],1,$pid[1],
     "甲方向乙方采购企业宣传片拍摄与后期制作服务。","宣传片,拍摄,采购",
     [['APPROVED','APPROVED'],2,'APPROVED',day(-30)],
     [['PAYABLE',47500,'PAID',-28,-27,'BANK','预付款50%'],
      ['PAYABLE',47500,'PENDING',15,'','BANK','成片交付尾款50%']],
     [['ISSUED',47500,0.06,'VAT_SPECIAL',day(-27),'义乌十八腔网络科技有限公司','244108764'],
      ['CANCELLED',47500,0.06,'VAT_SPECIAL',null,'义乌十八腔网络科技有限公司','244108764-R']],
     ['SIGNED',day(-26)]],
    // 11 EXECUTING 销售（含逾期回款）
    ['天府软件-外包人力服务合同','SERVICE','sales',1,'EXECUTING',360000,'成都天府软件外包有限公司',$cid[5],0,$uid['employee01'],2,$pid[3],
     "乙方向甲方派驻软件开发人员提供外包服务。","外包,人力,服务",
     [['APPROVED','APPROVED'],2,'APPROVED',day(-70)],
     [['RECEIVABLE',120000,'PAID',-68,-66,'BANK','首期'],
      ['RECEIVABLE',120000,'OVERDUE',-10,'','BANK','第二期已逾期'],
      ['RECEIVABLE',120000,'PENDING',40,'','BANK','第三期']],
     [['ISSUED',120000,0.06,'VAT_SPECIAL',day(-66),'成都天府软件外包有限公司','244108765'],
      ['REJECTED',120000,0.06,'VAT_SPECIAL',null,'成都天府软件外包有限公司','']],
     ['SIGNED',day(-64)]],
    // 12 COMPLETED 销售（全部已收）
    ['光谷智能-设备运维年度合同','SERVICE','sales',1,'COMPLETED',200000,'武汉光谷智能装备有限公司',$cid[6],0,$uid['sales02'],1,$pid[0],
     "乙方为甲方提供智能装备年度运维服务。","运维,设备,年度",
     [['APPROVED','APPROVED'],2,'APPROVED',day(-200)],
     [['RECEIVABLE',100000,'PAID',-190,-188,'BANK','上半年'],
      ['RECEIVABLE',100000,'PAID',-30,-28,'BANK','下半年']],
     [['ISSUED',100000,0.06,'VAT_SPECIAL',day(-188),'武汉光谷智能装备有限公司','244108766'],
      ['ISSUED',100000,0.06,'VAT_SPECIAL',day(-28),'武汉光谷智能装备有限公司','244108767']],
     ['SIGNED',day(-196)]],
    // 13 TERMINATED 采购（已签后终止）
    ['自由插画-插画采购合同','PURCHASE','purchase',1,'TERMINATED',28000,'自由插画师·李默',0,$sid[4],$uid['purchase02'],3,$pid[1],
     "甲方向乙方采购系列插画素材。","插画,素材,采购",
     [['APPROVED','SKIP'],1,'APPROVED',day(-45)],
     [], [], ['SIGNED',day(-43)]],
    // 14 EXPIRED 销售（到期）
    ['金陵文化-活动策划合同','SERVICE','sales',1,'EXPIRED',88000,'南京金陵文化影视有限公司',$cid[7],0,$uid['sales02'],1,$pid[1],
     "乙方为甲方提供线下活动策划与执行。","活动,策划,执行",
     [['APPROVED','APPROVED'],2,'APPROVED',day(-180)],
     [['RECEIVABLE',88000,'PAID',-170,-168,'BANK','全款']],
     [['ISSUED',88000,0.06,'VAT_SPECIAL',day(-168),'南京金陵文化影视有限公司','244108768']],
     ['SIGNED',day(-176)]],
    // 15 ARCHIVED 销售
    ['海蓝生物-官网建设合同','SERVICE','sales',1,'ARCHIVED',76000,'青岛海蓝生物科技有限公司',$cid[11],0,$uid['admin'],1,$pid[0],
     "乙方为甲方建设企业官网并交付源码。","官网,建设,交付",
     [['APPROVED','APPROVED'],2,'APPROVED',day(-300)],
     [['RECEIVABLE',76000,'PAID',-290,-288,'BANK','全款']],
     [['ISSUED',76000,0.06,'VAT_SPECIAL',day(-288),'青岛海蓝生物科技有限公司','244108769']],
     ['SIGNED',day(-296)]],
    // 16 DRAFT + 已撤回审批历史（展示 RECALLED 状态）
    ['两江数字-云迁移服务合同','SERVICE','sales',1,'DRAFT',210000,'重庆两江数字科技有限责任公司',$cid[9],0,$uid['employee01'],2,$pid[3],
     "乙方为甲方提供业务系统云迁移服务。","云迁移,服务,数字",
     [['PENDING','SKIP'],1,'RECALLED',null],  // 提交后由提交人撤回
     [], [], null],
    // 17 EXECUTING 采购（应付，演示「应付/绿」方向标签）
    ['云服务器及带宽采购合同','PURCHASE','purchase',1,'EXECUTING',268000,'阿里云(中国)有限公司',0,$sid[7],$uid['purchase02'],1,$pid[2],
     "甲方向乙方采购云服务器实例及公网带宽资源，按量计费、按月结算。","云服务器,带宽,采购",
     [['APPROVED','APPROVED'],2,'APPROVED',day(-40)],
     [['PAYABLE',134000,'PAID',-38,-36,'BANK','预付50%'],
      ['PAYABLE',134000,'PENDING',20,'','BANK','尾款50%']],
     [['ISSUED',134000,0.06,'VAT_SPECIAL',day(-36),'阿里云(中国)有限公司','244108770']],
     ['SIGNED',day(-42)]],
    // 18 EXECUTING 非交易（演示「非交易/灰」方向标签，trade_attr=0 不参与收付款）
    ['生态战略合作框架协议','SERVICE','sales',0,'EXECUTING',2000000,'某战略生态合作伙伴',0,0,$uid['admin'],1,$pid[0],
     "双方就生态合作达成战略框架协议，约定合作原则、资源互换与年度复盘机制，不涉及具体收付款。","战略合作,框架,非交易",
     [['APPROVED','APPROVED'],2,'APPROVED',day(-60)],
     [], [], ['SIGNED',day(-58)]],
];

foreach ($contracts as $c) {
    [$title,$category,$direction,$tradeAttr,$status,$amount,$partyB,$partyBCust,$supplierId,$owner,$flowId,$projectId,$content,$keywords,$approval,$payments,$invoices] = $c;
    $contractId = insertRow($db, 'contract', [
        'contract_no' => nextNo(),
        'title' => $title,
        'category' => $category,
        'status' => $status,
        'amount' => $amount,
        'party_a_name' => $ourCo,
        'party_a_contact' => '合同管理部',
        'party_a_phone' => '0579-85668888',
        'party_b_customer_id' => $partyBCust,
        'party_b_name' => $partyB,
        'party_b_contact' => '',
        'party_b_credit_code' => '',
        'effective_date' => in_array($status,['DRAFT','PENDING_APPROVAL','REJECTED','ARCHIVED','TERMINATED','EXPIRED'])?null:day(-50),
        'expiry_date' => $status==='EXPIRED' ? day(-5) : day(200),
        'content' => $content,
        'content_plain' => $content,
        'file_url' => '[]',
        'keywords' => $keywords,
        'owner_id' => $owner,
        'dept_id' => 4,
        'supplier_id' => $supplierId,
        'direction' => $direction,
        'trade_attr' => $tradeAttr,
        'project_id' => $projectId,
        'flow_id' => $flowId,
        'our_company_id' => 1,
        'custom_fields' => '{}',
        'creator_id' => $owner,
        'updater_id' => $owner,
    ]);
    // 审批
    if ($approval) {
        [$nodeActions, $currentNode, $instStatus, $finishedAt] = $approval;
        seedApproval($db, $contractId, $flowId, $instStatus, $nodeActions, $currentNode, $owner, $finishedAt);
    }
    // 回款
    if ($payments) seedPayments($db, $contractId, $owner, $payments);
    // 发票
    if ($invoices) seedInvoices($db, $contractId, $owner, $invoices);
    // 变更日志（创建）
    insertRow($db, 'contract_revision', ['contract_id'=>$contractId,'field_name'=>'system','new_value'=>'创建合同（仿真数据）','operator_id'=>$owner]);
}

// ================= 部分确认测试数据（展示 parent_id>0 的「剩余回款」子记录） =================
echo "写入部分确认测试回款...\n";
// 合同 9（智联科技-CRM系统定制合同）第二期 300000 模拟部分确认 120000，
// 母记录调减为 PAID/120000，剩余 180000 拆为 PENDING 子记录（parent_id 指向母记录）。
// 这样合同详情页和财务页都能看到「剩余回款」小标。
$pcContractId = (int)$db->querySingle("SELECT id FROM contract WHERE title LIKE '智联科技-CRM系统定制合同%' LIMIT 1");
if ($pcContractId > 0) {
    $pcParentId = (int)$db->querySingle("SELECT id FROM payment_record WHERE contract_id={$pcContractId} AND status='PENDING' ORDER BY id LIMIT 1");
    if ($pcParentId > 0) {
        // 母记录：部分确认 120000，状态 PAID，amount 同步调减为实际确认额（与 PaymentLogic::confirm 一致）
        $db->exec("UPDATE payment_record SET status='PAID', amount=120000, paid_amount=120000, actual_date='" . day(-10) . "', payment_method='BANK', description='第二期进度款（部分确认12万）' WHERE id={$pcParentId}");
        // 子记录：剩余 180000 拆为新的待收，parent_id 关联母记录
        insertRow($db, 'payment_record', [
            'contract_id'    => $pcContractId,
            'payment_type'   => 'RECEIVABLE',
            'amount'         => 180000,
            'planned_date'   => day(20),
            'status'         => 'PENDING',
            'parent_id'      => $pcParentId,
            'payment_method' => 'BANK',
            'description'    => '第二期剩余（部分确认拆分）',
            'operator_id'    => $uid['sales02'],
            'paid_amount'    => 0,
        ]);
    }
}

// ================= 资料库补充 =================
echo "补充资料库...\n";
insertRow($db, 'resource_library', ['category'=>'TEMPLATE','title'=>'采购合同标准范本','file_url'=>'/uploads/library/demo/purchase-tpl.docx','file_name'=>'采购合同标准范本.docx','file_size'=>98304,'description'=>'标准采购合同范本，含交付、验收、质保与违约条款。','company_id'=>0,'owner_id'=>$uid['admin']]);
insertRow($db, 'resource_library', ['category'=>'CLAUSE','title'=>'数据安全与隐私条款','file_url'=>'/uploads/library/demo/clause-privacy.docx','file_name'=>'数据安全与隐私条款.docx','file_size'=>51200,'description'=>'通用数据安全、个人信息保护与合规条款范本。','company_id'=>0,'owner_id'=>$uid['admin']]);

// ================= 提醒日志 =================
echo "写入提醒日志...\n";
insertRow($db, 'remind_log', ['target_type'=>'contract','target_id'=>$contractId,'remind_type'=>'expire','remind_at'=>day(195),'sent'=>0]);
insertRow($db, 'remind_log', ['target_type'=>'payment','target_id'=>0,'remind_type'=>'payment','remind_at'=>day(20),'sent'=>0]);
insertRow($db, 'remind_log', ['target_type'=>'contract','target_id'=>$cid[0],'remind_type'=>'payment','remind_at'=>day(40),'sent'=>0]);

// ================= 审计日志 =================
echo "写入审计日志...\n";
$audits = [
    [$uid['admin'],'login','user',1,'管理员登录系统'],
    [$uid['sales02'],'contract.create','contract',3,'创建合同：前海云创-信息流投放服务合同'],
    [$uid['admin'],'approval.approve','approval',3,'审批通过：前海云创-信息流投放服务合同'],
    [$uid['finance01'],'payment.confirm','payment',9,'确认回款：智联科技-CRM系统定制合同 首付款'],
    [$uid['purchase02'],'supplier.create','supplier',$sid[0],'新增供应商：字节跳动巨量引擎（代理）'],
];
foreach ($audits as [$u,$act,$tt,$ti,$content]) {
    insertRow($db, 'audit_log', ['user_id'=>$u,'action'=>$act,'target_type'=>$tt,'target_id'=>$ti,'content'=>$content,'ip_address'=>'127.0.0.1']);
}

// ================= 统计 =================
function cnt($db,$t){ $r=$db->query("SELECT COUNT(*) n FROM $t"); return $r->fetchArray(SQLITE3_ASSOC)['n']; }
echo "\n========== 仿真数据写入完成 ==========\n";
foreach (['department','user','customer','supplier','project','contract','approval_instance','approval_record','payment_record','contract_invoice','contract_revision','resource_library','remind_log','audit_log'] as $t) {
    printf("  %-18s %4d\n", $t, cnt($db, $t));
}
echo "登录账号与口令（本次随机生成，请妥善保存）：\n";
echo "  admin        —— $demoPwdAdmin\n";
echo "  manager01    —— $demoPwdUser\n";
echo "  employee01   —— $demoPwdUser\n";
echo "  finance01    —— $demoPwdUser\n";
echo "  sales02      —— $demoPwdUser\n";
echo "  purchase02   —— $demoPwdUser\n";
echo "提示：演示环境已关闭强制改密（force_reset=0），可直接登录预览。\n";
echo "安全：如需部署到非本地环境，请务必改用 init 脚本初始化（首登强制改密）。\n";
