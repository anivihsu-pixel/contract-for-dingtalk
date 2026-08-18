<?php
// +----------------------------------------------------------------------
// | 演示数据脚本 —— 为预览/演示环境补入业务演示数据
// | 与 init_sqlite.php 配套：init 只初始化基础数据（角色/权限/用户/字典/主体），
// | 本脚本补入业务演示数据（部门/客户/供应商/项目/合同/回款/发票/审批流/跟进）。
// | 幂等：已存在对应数据时自动跳过，可重复执行。
// +----------------------------------------------------------------------

define('ROOT_PATH', __DIR__ . '/../');
require ROOT_PATH . 'vendor/autoload.php';

if (is_file(ROOT_PATH . '.env')) {
    $dotenv = \Dotenv\Dotenv::createImmutable(ROOT_PATH);
    $dotenv->load();
}

$app = new \think\App(ROOT_PATH);
$app->initialize();

use think\facade\Db;

$now = date('Y-m-d H:i:s');
$J = fn($v) => json_encode($v, JSON_UNESCAPED_UNICODE);

// ---------- 幂等标记：已有业务数据则跳过 ----------
if (Db::name('contract')->where('contract_no', 'HT-2026-0001')->count() > 0) {
    echo "演示数据已存在（HT-2026-0001），跳过。\n";
    exit(0);
}

// ---------- 部门 ----------
if (Db::name('department')->count() === 0) {
    Db::name('department')->insertAll([
        ['id' => 1, 'name' => '市场部', 'parent_id' => 0, 'sort_order' => 1, 'leader_user_id' => 2],
        ['id' => 2, 'name' => '技术部', 'parent_id' => 0, 'sort_order' => 2, 'leader_user_id' => 0],
    ]);
    echo "部门: 2 条\n";
}

// ---------- 客户 ----------
$customers = [
    [1, '上海云启科技有限公司', '91310000MA1FL00001', '陈志远', '王建国', '13800001111', 'wangjg@yunqi.example.com', '上海市浦东新区张江路88号', 'OTHER', 'ACTIVE', 2, 1, 'MANUAL', '2026-01-05 10:00:00'],
    [2, '杭州星野文化传媒有限公司', '91330100MA2GK00002', '林雅', '陈晓梅', '13800002222', 'chenxm@xingye.example.com', '杭州市西湖区文三路100号', 'FOOD_TOURISM', 'ACTIVE', 3, 1, 'RECOMMEND', '2026-01-12 14:30:00'],
    [3, '宁波智联设备制造有限公司', '91330200MA2HQ00003', '周正', '周正', '13800003333', 'zhouzheng@zhilian.example.com', '宁波市北仑区工业大道66号', 'OTHER', 'ACTIVE', 2, 1, 'MANUAL', '2026-02-03 09:20:00'],
    [4, '北京数科信息技术有限公司', '91110100MA01800004', '孙伟', '孙伟', '13800004444', 'sunwei@shuke.example.com', '北京市海淀区中关村大街1号', 'OTHER', 'POTENTIAL', 3, 1, 'AD', '2026-07-15 16:00:00'],
    [5, '深圳海岸线贸易有限公司', '91440300MA5FP00005', '刘志强', '刘志强', '13800005555', 'liuzq@haianxian.example.com', '深圳市南山区科技园南区', 'OTHER', 'ACTIVE', 3, 1, 'MANUAL', '2026-02-20 11:10:00'],
    [6, '义乌小商品城运营有限公司', '91330782MA2ED00006', '吴国华', '吴国华', '13800006666', 'wugh@yiwucheng.example.com', '义乌市国际商贸城一区', 'OTHER', 'ACTIVE', 2, 1, 'MANUAL', '2026-03-08 15:40:00'],
    [7, '苏州蓝海物流有限公司', '91320500MA1YG00007', '沈峰', '沈峰', '13800007777', 'shenfeng@lanhai.example.com', '苏州市工业园区物流园', 'OTHER', 'ACTIVE', 3, 1, 'DINGTALK', '2026-04-01 10:30:00'],
    [8, '某市政务服务中心', '11330000MB0000088', '赵建国', '赵处长', '057900000000', 'zhaochu@gov.example.com', '某市行政服务中心大楼', 'GOV', 'ACTIVE', 2, 1, 'MANUAL', '2026-05-10 09:00:00'],
];
$rows = array_map(fn($c) => [
    'id' => $c[0], 'name' => $c[1], 'credit_code' => $c[2], 'legal_person' => $c[3],
    'contact_name' => $c[4], 'contact_mobile' => $c[5], 'contact_email' => $c[6], 'address' => $c[7],
    'industry' => $c[8], 'lifecycle_status' => $c[9], 'owner_id' => $c[10], 'dept_id' => $c[11],
    'source' => $c[12], 'status' => 1,
    'is_self' => 0, 'created_at' => $c[13], 'updated_at' => $c[13],
], $customers);
Db::name('customer')->insertAll($rows);
echo "客户: " . count($rows) . " 条\n";

// 客户联系人
Db::name('customer_contact')->insertAll([
    ['customer_id' => 1, 'name' => '王建国', 'phone' => '13800001111', 'email' => 'wangjg@yunqi.example.com', 'is_primary' => 1, 'remark' => '项目总监'],
    ['customer_id' => 2, 'name' => '陈晓梅', 'phone' => '13800002222', 'email' => 'chenxm@xingye.example.com', 'is_primary' => 1, 'remark' => '市场总监'],
    ['customer_id' => 5, 'name' => '刘志强', 'phone' => '13800005555', 'email' => 'liuzq@haianxian.example.com', 'is_primary' => 1, 'remark' => '总经理'],
    ['customer_id' => 8, 'name' => '赵处长', 'phone' => '057900000000', 'email' => '', 'is_primary' => 1, 'remark' => '信息化处'],
]);
echo "客户联系人: 4 条\n";

// 客户跟进记录
Db::name('customer_activity')->insertAll([
    ['customer_id' => 1, 'user_id' => 2, 'type' => 'MEETING', 'content' => '客户现场拜访，确认下半年续约意向', 'created_at' => '2026-07-20 14:00:00'],
    ['customer_id' => 1, 'user_id' => 2, 'type' => 'NOTE', 'content' => '发送八月对账单，客户确认无误', 'created_at' => '2026-08-01 10:30:00'],
    ['customer_id' => 2, 'user_id' => 3, 'type' => 'CALL', 'content' => '电话沟通全案进度，客户对中期方案满意', 'created_at' => '2026-08-05 15:20:00'],
    ['customer_id' => 5, 'user_id' => 3, 'type' => 'CALL', 'content' => '电话催收逾期尾款 15.4 万元，客户承诺月底支付', 'created_at' => '2026-07-10 11:00:00'],
    ['customer_id' => 5, 'user_id' => 3, 'type' => 'NOTE', 'content' => '客户财务反馈付款流程已启动，预计两周内到账', 'created_at' => '2026-07-25 16:40:00'],
]);
echo "客户跟进: 5 条\n";

// ---------- 供应商 ----------
Db::name('supplier')->insertAll([
    ['id' => 1, 'name' => '广州媒介推广有限公司', 'type' => 'MEDIA', 'contact_name' => '黄伟', 'contact_mobile' => '13900001111', 'remark' => 'huangwei@gzmedia.example.com', 'address' => '广州市天河区珠江新城', 'status' => 1, 'owner_id' => 2, 'dept_id' => 1, 'created_at' => $now, 'updated_at' => $now],
    ['id' => 2, 'name' => '深圳文创制作有限公司', 'type' => 'PRODUCTION', 'contact_name' => '邓丽', 'contact_mobile' => '13900002222', 'remark' => 'dengli@szwc.example.com', 'address' => '深圳市福田区创意园', 'status' => 1, 'owner_id' => 3, 'dept_id' => 1, 'created_at' => $now, 'updated_at' => $now],
    ['id' => 3, 'name' => '杭州物料供应链有限公司', 'type' => 'MATERIAL', 'contact_name' => '马超', 'contact_mobile' => '13900003333', 'remark' => 'machao@hzml.example.com', 'address' => '杭州市萧山区物流园区', 'status' => 1, 'owner_id' => 2, 'dept_id' => 1, 'created_at' => $now, 'updated_at' => $now],
    ['id' => 4, 'name' => '上海自由设计师工作室', 'type' => 'FREELANCER', 'contact_name' => '唐宁', 'contact_mobile' => '13900004444', 'remark' => 'tangning@studio.example.com', 'address' => '上海市静安区', 'status' => 1, 'owner_id' => 3, 'dept_id' => 1, 'created_at' => $now, 'updated_at' => $now],
]);
echo "供应商: 4 条\n";

// ---------- 项目（更新现有 + 新增） ----------
// 现有 id=1/2（init 建）与 id=5（冒烟残留）统一更新为演示项目
Db::name('project')->where('id', 1)->update([
    'customer_id' => 1, 'owner_id' => 2, 'dept_id' => 1, 'status' => 'ACTIVE', 'budget' => 600000,
    'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'stage' => 'EXECUTING', 'progress' => 65,
    'remark' => '上海云启年度软件技术服务项目，正在执行中。', 'updated_at' => $now,
]);
Db::name('project')->where('id', 2)->update([
    'name' => '杭州物料-年度物料采购', 'code' => 'PRJ-2026-002', 'customer_id' => 0, 'owner_id' => 2, 'dept_id' => 1,
    'status' => 'ACTIVE', 'budget' => 180000, 'start_date' => '2026-03-01', 'end_date' => '2026-12-31',
    'stage' => 'EXECUTING', 'progress' => 70, 'remark' => '杭州物料供应链年度物料采购项目。', 'updated_at' => $now,
]);
Db::name('project')->where('id', 5)->update([
    'name' => '海岸线贸易-年度营销推广', 'code' => 'PRJ-2026-005', 'customer_id' => 5, 'owner_id' => 3, 'dept_id' => 1,
    'status' => 'ACTIVE', 'budget' => 220000, 'start_date' => '2026-03-01', 'end_date' => '2026-12-31',
    'stage' => 'EXECUTING', 'progress' => 40, 'remark' => '深圳海岸线贸易年度营销推广项目。', 'updated_at' => $now,
]);
Db::name('project')->insertAll([
    ['id' => 3, 'name' => '星野文化-品牌全案服务', 'code' => 'PRJ-2026-003', 'customer_id' => 2, 'owner_id' => 3, 'dept_id' => 1,
     'business_type' => 'EVENT', 'status' => 'ACTIVE', 'budget' => 300000, 'start_date' => '2026-02-01', 'end_date' => '2026-11-30',
     'stage' => 'EXECUTING', 'progress' => 60, 'remark' => '星野文化品牌全案服务项目。', 'created_at' => $now, 'updated_at' => $now],
    ['id' => 4, 'name' => '数科信息-数据平台建设', 'code' => 'PRJ-2026-004', 'customer_id' => 4, 'owner_id' => 3, 'dept_id' => 1,
     'business_type' => 'OTHER', 'status' => 'ACTIVE', 'budget' => 450000, 'start_date' => '2026-09-01', 'end_date' => '2027-08-31',
     'stage' => 'PLANNING', 'progress' => 10, 'remark' => '北京数科数据平台建设项目，筹备阶段。', 'created_at' => $now, 'updated_at' => $now],
]);
echo "项目: 更新 3 + 新增 2 = 5 条\n";

// ---------- 合同 ----------
$mkContract = function (array $c) use ($now) {
    return array_merge([
        'content_plain' => '', 'file_url' => '[]', 'keywords' => '', 'custom_fields' => '{}',
        'creator_id' => $c['owner_id'], 'updater_id' => $c['owner_id'], 'is_deleted' => 0,
        'parent_id' => 0, 'supplier_id' => 0, 'flow_id' => 0, 'party_a_supplier_id' => 0,
        'party_a_customer_id' => 0, 'party_b_customer_id' => 0, 'party_a_contact' => '', 'party_a_phone' => '',
        'party_b_contact' => '', 'party_b_phone' => '', 'party_b_credit_code' => '',
        'renewed_to' => 0, 'renewed_from' => 0,
        'our_company_id' => 1, 'created_at' => $now, 'updated_at' => $now,
    ], $c);
};

$contracts = [
    $mkContract([
        'contract_no' => 'HT-2026-0001', 'title' => '上海云启-年度软件服务合同', 'business_type' => 'OTHER',
        'status' => 'EXECUTING', 'amount' => 600000, 'direction' => 'sales', 'trade_attr' => 1,
        'party_a_customer_id' => 1, 'party_a_name' => '上海云启科技有限公司', 'party_a_contact' => '王建国', 'party_a_phone' => '13800001111',
        'party_b_name' => '义乌十八腔网络科技有限公司', 'party_b_credit_code' => '91330782MADEMO0001',
        'effective_date' => '2026-01-01', 'expiry_date' => '2026-12-31', 'project_id' => 1, 'owner_id' => 2, 'dept_id' => 1,
        'content' => "双方签署年度软件技术服务协议。\n\n合同金额：60 万元（含税）\n付款方式：签约后付30%，中期验收后付40%，年度验收后付30%\n服务期限：2026-01-01 至 2026-12-31，含日常运维\n知识产权：项目源码及文档归甲方所有",
        'content_plain' => "双方签署年度软件技术服务协议。合同金额60万元（含税）。付款方式：签约后付30%，中期验收后付40%，年度验收后付30%。服务期限2026全年，含日常运维。知识产权归甲方所有。",
        'keywords' => '软件,技术服务,年度',
    ]),
    $mkContract([
        'contract_no' => 'HT-2026-0002', 'title' => '星野文化-品牌全案服务合同', 'business_type' => 'EVENT',
        'status' => 'EXECUTING', 'amount' => 300000, 'direction' => 'sales', 'trade_attr' => 1,
        'party_a_customer_id' => 2, 'party_a_name' => '杭州星野文化传媒有限公司', 'party_a_contact' => '陈晓梅', 'party_a_phone' => '13800002222',
        'party_b_name' => '义乌十八腔网络科技有限公司', 'party_b_credit_code' => '91330782MADEMO0001',
        'effective_date' => '2026-02-01', 'expiry_date' => '2026-11-30', 'project_id' => 3, 'owner_id' => 3, 'dept_id' => 1,
        'content' => "品牌全案服务合同。\n\n合同金额：30 万元（含税）\n付款方式：签约后付30%，中期交付付30%，项目验收后付40%\n服务内容：品牌定位、视觉设计、营销活动策划与执行",
        'content_plain' => "品牌全案服务合同。合同金额30万元。付款：签约30%，中期30%，验收40%。服务内容：品牌定位、视觉设计、营销活动策划与执行。",
        'keywords' => '品牌,全案,活动',
    ]),
    $mkContract([
        'contract_no' => 'HT-2026-0003', 'title' => '杭州物料-年度物料采购合同', 'business_type' => 'OTHER',
        'status' => 'EXECUTING', 'amount' => 180000, 'direction' => 'purchase', 'trade_attr' => 1,
        'party_a_name' => '义乌十八腔网络科技有限公司', 'party_b_name' => '杭州物料供应链有限公司', 'party_b_contact' => '马超', 'party_b_phone' => '13900003333',
        'supplier_id' => 3, 'effective_date' => '2026-03-01', 'expiry_date' => '2026-12-31', 'project_id' => 2, 'owner_id' => 2, 'dept_id' => 1,
        'content' => "年度物料采购合同。\n\n合同金额：18 万元（含13%增值税）\n付款方式：预付30%，季度结算尾款\n交货方式：按采购订单分批供货",
        'content_plain' => "年度物料采购合同。合同金额18万元（含13%增值税）。付款：预付30%，季度结算尾款。按采购订单分批供货。",
        'keywords' => '物料,采购,年度',
    ]),
    $mkContract([
        'contract_no' => 'HT-2026-0004', 'title' => '北京数科-数据平台建设合同', 'business_type' => 'OTHER',
        'status' => 'PENDING_APPROVAL', 'amount' => 450000, 'direction' => 'sales', 'trade_attr' => 1,
        'party_a_customer_id' => 4, 'party_a_name' => '北京数科信息技术有限公司', 'party_a_contact' => '孙伟', 'party_a_phone' => '13800004444',
        'party_b_name' => '义乌十八腔网络科技有限公司', 'party_b_credit_code' => '91330782MADEMO0001',
        'effective_date' => '2026-09-01', 'expiry_date' => '2027-08-31', 'project_id' => 4, 'owner_id' => 3, 'dept_id' => 1,
        'content' => "数据平台建设项目合同（待审批）。\n\n合同金额：45 万元（含税）\n付款方式：签约后付30%，上线验收后付40%，稳定运行3个月后付30%",
        'content_plain' => "数据平台建设项目合同。合同金额45万元。付款：签约30%，上线40%，稳定运行后30%。待审批。",
        'keywords' => '数据,平台,建设',
    ]),
    $mkContract([
        'contract_no' => 'HT-2026-0005', 'title' => '海岸线贸易-年度营销推广合同', 'business_type' => 'AD_CONTENT',
        'status' => 'EXECUTING', 'amount' => 220000, 'direction' => 'sales', 'trade_attr' => 1,
        'party_a_customer_id' => 5, 'party_a_name' => '深圳海岸线贸易有限公司', 'party_a_contact' => '刘志强', 'party_a_phone' => '13800005555',
        'party_b_name' => '义乌十八腔网络科技有限公司', 'party_b_credit_code' => '91330782MADEMO0001',
        'effective_date' => '2026-03-01', 'expiry_date' => '2026-12-31', 'project_id' => 5, 'owner_id' => 3, 'dept_id' => 1,
        'content' => "年度营销推广合同。\n\n合同金额：22 万元（含税）\n付款方式：签约后付30%，尾款按季度结算\n推广渠道：本地媒体+线上广告投放",
        'content_plain' => "年度营销推广合同。合同金额22万元。付款：签约30%，尾款按季度结算。渠道：本地媒体+线上广告投放。",
        'keywords' => '营销,推广,广告',
    ]),
    $mkContract([
        'contract_no' => 'HT-2026-0006', 'title' => '义乌商城-年度广告投放合同', 'business_type' => 'AD_CONTENT',
        'status' => 'COMPLETED', 'amount' => 150000, 'direction' => 'sales', 'trade_attr' => 1,
        'party_a_customer_id' => 6, 'party_a_name' => '义乌小商品城运营有限公司', 'party_a_contact' => '吴国华', 'party_a_phone' => '13800006666',
        'party_b_name' => '义乌十八腔网络科技有限公司', 'party_b_credit_code' => '91330782MADEMO0001',
        'effective_date' => '2025-07-01', 'expiry_date' => '2026-06-30', 'project_id' => 0, 'owner_id' => 2, 'dept_id' => 1,
        'content' => "年度广告投放合同。\n\n合同金额：15 万元（含税）\n投放周期：2025-07-01 至 2026-06-30\n双方已履行完毕，合同完成归档",
        'content_plain' => "年度广告投放合同。合同金额15万元。投放周期一年，已履行完毕，合同完成归档。",
        'keywords' => '广告,投放,年度',
    ]),
    $mkContract([
        'contract_no' => 'HT-2026-0007', 'title' => '苏州蓝海-运输服务框架合同', 'business_type' => 'OTHER',
        'status' => 'DRAFT', 'amount' => 80000, 'direction' => 'sales', 'trade_attr' => 1,
        'party_a_customer_id' => 7, 'party_a_name' => '苏州蓝海物流有限公司', 'party_a_contact' => '沈峰', 'party_a_phone' => '13800007777',
        'party_b_name' => '义乌十八腔网络科技有限公司', 'party_b_credit_code' => '91330782MADEMO0001',
        'effective_date' => '2026-08-01', 'expiry_date' => '2027-07-31', 'project_id' => 0, 'owner_id' => 3, 'dept_id' => 1,
        'content' => "运输服务框架合同（草稿）。\n\n框架金额：8 万元（含税）\n按实际运输量按月结算",
        'content_plain' => "运输服务框架合同草稿。框架金额8万元，按实际运输量按月结算。",
        'keywords' => '运输,框架,服务',
    ]),
    $mkContract([
        'contract_no' => 'HT-2026-0008', 'title' => '某政务中心-智慧政务服务合同', 'business_type' => 'OTHER',
        'status' => 'EXECUTING', 'amount' => 1200000, 'direction' => 'sales', 'trade_attr' => 1,
        'party_a_customer_id' => 8, 'party_a_name' => '某市政务服务中心', 'party_a_contact' => '赵处长', 'party_a_phone' => '057900000000',
        'party_b_name' => '义乌十八腔网络科技有限公司', 'party_b_credit_code' => '91330782MADEMO0001',
        'effective_date' => '2026-05-01', 'expiry_date' => '2027-04-30', 'project_id' => 0, 'owner_id' => 2, 'dept_id' => 1,
        'content' => "智慧政务服务项目合同。\n\n合同金额：120 万元（含税）\n付款方式：验收合格后分期支付\n服务内容：政务服务平台建设与运维",
        'content_plain' => "智慧政务服务项目合同。合同金额120万元。验收后分期付款。服务内容：政务服务平台建设与运维。",
        'keywords' => '政务,智慧,服务',
    ]),
    $mkContract([
        'contract_no' => 'HT-2026-0009', 'title' => '深圳文创-宣传片制作采购合同', 'business_type' => 'EVENT',
        'status' => 'TERMINATED', 'amount' => 60000, 'direction' => 'purchase', 'trade_attr' => 1,
        'party_a_name' => '义乌十八腔网络科技有限公司', 'party_b_name' => '深圳文创制作有限公司', 'party_b_contact' => '邓丽', 'party_b_phone' => '13900002222',
        'supplier_id' => 2, 'effective_date' => '2026-02-01', 'expiry_date' => '2026-08-31', 'project_id' => 0, 'owner_id' => 3, 'dept_id' => 1,
        'content' => "宣传片制作采购合同。\n\n合同金额：6 万元（含税）\n因拍摄档期无法协调，双方协商终止合同",
        'content_plain' => "宣传片制作采购合同。合同金额6万元。因档期无法协调，双方协商终止合同。",
        'keywords' => '宣传片,制作,采购',
    ]),
    $mkContract([
        'contract_no' => 'HT-2026-0010', 'title' => '广州媒介-年度媒体投放采购合同', 'business_type' => 'AD_CONTENT',
        'status' => 'EXPIRED', 'amount' => 90000, 'direction' => 'purchase', 'trade_attr' => 1,
        'party_a_name' => '义乌十八腔网络科技有限公司', 'party_b_name' => '广州媒介推广有限公司', 'party_b_contact' => '黄伟', 'party_b_phone' => '13900001111',
        'supplier_id' => 1, 'effective_date' => '2025-08-01', 'expiry_date' => '2026-07-31', 'project_id' => 0, 'owner_id' => 2, 'dept_id' => 1,
        'content' => "年度媒体投放采购合同。\n\n合同金额：9 万元（含税）\n投放周期已到期，未续约",
        'content_plain' => "年度媒体投放采购合同。合同金额9万元。投放周期已到期，未续约。",
        'keywords' => '媒体,投放,采购',
    ]),
    $mkContract([
        'contract_no' => 'HT-2026-0011', 'title' => '上海云启-数据保密协议', 'business_type' => 'OTHER',
        'status' => 'ARCHIVED', 'amount' => 0, 'direction' => 'sales', 'trade_attr' => 0,
        'party_a_customer_id' => 1, 'party_a_name' => '上海云启科技有限公司', 'party_a_contact' => '王建国', 'party_a_phone' => '13800001111',
        'party_b_name' => '义乌十八腔网络科技有限公司', 'party_b_credit_code' => '91330782MADEMO0001',
        'effective_date' => '2026-01-01', 'expiry_date' => '2028-12-31', 'project_id' => 0, 'owner_id' => 2, 'dept_id' => 1,
        'content' => "数据保密协议（非交易合同）。\n\n保密范围：技术资料、客户信息、财务数据\n保密期限：协议终止后3年",
        'content_plain' => "数据保密协议。保密范围：技术资料、客户信息、财务数据。保密期限：协议终止后3年。非交易合同。",
        'keywords' => '保密,NDA,协议',
    ]),
    $mkContract([
        'contract_no' => 'HT-DEMO-0001', 'title' => '李员工-测试服务合同', 'business_type' => 'OTHER',
        'status' => 'DRAFT', 'amount' => 12000, 'direction' => 'sales', 'trade_attr' => 1,
        'party_a_customer_id' => 2, 'party_a_name' => '杭州星野文化传媒有限公司', 'party_a_contact' => '陈晓梅', 'party_a_phone' => '13800002222',
        'party_b_name' => '义乌十八腔网络科技有限公司', 'party_b_credit_code' => '91330782MADEMO0001',
        'effective_date' => '2026-08-01', 'expiry_date' => '2026-12-31', 'project_id' => 0, 'owner_id' => 3, 'dept_id' => 1,
        'content' => "小额测试服务合同（演示数据范围：普通用户仅可见本人数据）。\n\n合同金额：1.2 万元（含税）",
        'content_plain' => "小额测试服务合同，演示数据范围。合同金额1.2万元。",
        'keywords' => '测试,服务,演示',
    ]),
];
Db::name('contract')->insertAll($contracts);
echo "合同: " . count($contracts) . " 条\n";

// ---------- 回款计划 ----------
$payments = [
    // 合同1：3期（已收2期，尾款待收）
    ['contract_id' => 1, 'payment_type' => 'RECEIVABLE', 'amount' => 180000, 'planned_date' => '2026-01-15', 'actual_date' => '2026-01-15', 'payment_method' => 'BANK', 'status' => 'PAID', 'description' => '首期预付款30%', 'operator_id' => 2, 'milestone' => 'DOWN_PAYMENT', 'paid_amount' => 180000],
    ['contract_id' => 1, 'payment_type' => 'RECEIVABLE', 'amount' => 240000, 'planned_date' => '2026-06-30', 'actual_date' => '2026-06-30', 'payment_method' => 'BANK', 'status' => 'PAID', 'description' => '中期验收款40%', 'operator_id' => 2, 'milestone' => 'MID_TERM', 'paid_amount' => 240000],
    ['contract_id' => 1, 'payment_type' => 'RECEIVABLE', 'amount' => 180000, 'planned_date' => '2026-12-31', 'actual_date' => null, 'payment_method' => 'BANK', 'status' => 'PENDING', 'description' => '年度尾款30%', 'operator_id' => 2, 'milestone' => 'FINAL_PAYMENT', 'paid_amount' => 0],
    // 合同2：2期（已收1期，尾款待收）
    ['contract_id' => 2, 'payment_type' => 'RECEIVABLE', 'amount' => 90000, 'planned_date' => '2026-02-15', 'actual_date' => '2026-02-15', 'payment_method' => 'BANK', 'status' => 'PAID', 'description' => '首期30%', 'operator_id' => 3, 'milestone' => 'DOWN_PAYMENT', 'paid_amount' => 90000],
    ['contract_id' => 2, 'payment_type' => 'RECEIVABLE', 'amount' => 210000, 'planned_date' => '2026-10-31', 'actual_date' => null, 'payment_method' => 'BANK', 'status' => 'PENDING', 'description' => '项目尾款70%', 'operator_id' => 3, 'milestone' => 'FINAL_PAYMENT', 'paid_amount' => 0],
    // 合同3（采购/应付）：预付已付，尾款待付
    ['contract_id' => 3, 'payment_type' => 'PAYABLE', 'amount' => 54000, 'planned_date' => '2026-03-20', 'actual_date' => '2026-03-20', 'payment_method' => 'BANK', 'status' => 'PAID', 'description' => '预付30%', 'operator_id' => 2, 'milestone' => 'DOWN_PAYMENT', 'paid_amount' => 54000],
    ['contract_id' => 3, 'payment_type' => 'PAYABLE', 'amount' => 126000, 'planned_date' => '2026-11-30', 'actual_date' => null, 'payment_method' => 'BANK', 'status' => 'PENDING', 'description' => '季度结算尾款', 'operator_id' => 2, 'milestone' => 'FINAL_PAYMENT', 'paid_amount' => 0],
    // 合同5：尾款逾期（高信用风险客户）
    ['contract_id' => 5, 'payment_type' => 'RECEIVABLE', 'amount' => 66000, 'planned_date' => '2026-03-20', 'actual_date' => '2026-03-20', 'payment_method' => 'BANK', 'status' => 'PAID', 'description' => '首期30%', 'operator_id' => 3, 'milestone' => 'DOWN_PAYMENT', 'paid_amount' => 66000],
    ['contract_id' => 5, 'payment_type' => 'RECEIVABLE', 'amount' => 154000, 'planned_date' => '2026-05-31', 'actual_date' => null, 'payment_method' => 'BANK', 'status' => 'OVERDUE', 'description' => '尾款70%（逾期）', 'operator_id' => 3, 'milestone' => 'FINAL_PAYMENT', 'paid_amount' => 0],
    // 合同6：已结清
    ['contract_id' => 6, 'payment_type' => 'RECEIVABLE', 'amount' => 150000, 'planned_date' => '2026-04-30', 'actual_date' => '2026-04-30', 'payment_method' => 'BANK', 'status' => 'PAID', 'description' => '全款', 'operator_id' => 2, 'milestone' => 'FINAL_PAYMENT', 'paid_amount' => 150000],
    // 合同8：预付款待收
    ['contract_id' => 8, 'payment_type' => 'RECEIVABLE', 'amount' => 360000, 'planned_date' => '2026-09-15', 'actual_date' => null, 'payment_method' => 'BANK', 'status' => 'PENDING', 'description' => '首期30%', 'operator_id' => 2, 'milestone' => 'DOWN_PAYMENT', 'paid_amount' => 0],
    // 合同9（终止）：已付部分
    ['contract_id' => 9, 'payment_type' => 'PAYABLE', 'amount' => 30000, 'planned_date' => '2026-03-01', 'actual_date' => '2026-03-01', 'payment_method' => 'BANK', 'status' => 'PAID', 'description' => '前期制作费', 'operator_id' => 3, 'milestone' => 'DOWN_PAYMENT', 'paid_amount' => 30000],
    // 合同10（到期/采购）：应付逾期
    ['contract_id' => 10, 'payment_type' => 'PAYABLE', 'amount' => 90000, 'planned_date' => '2026-06-30', 'actual_date' => null, 'payment_method' => 'BANK', 'status' => 'OVERDUE', 'description' => '媒体投放尾款（逾期）', 'operator_id' => 2, 'milestone' => 'FINAL_PAYMENT', 'paid_amount' => 0],
];
foreach ($payments as &$p) { $p['created_at'] = $now; $p['updated_at'] = $now; }
unset($p);
Db::name('payment_record')->insertAll($payments);
echo "回款计划: " . count($payments) . " 条\n";

// ---------- 发票 ----------
$invoices = [
    ['contract_id' => 1, 'amount' => 180000, 'tax_rate' => 0.06, 'tax_amount' => 10188.68, 'invoice_type' => 'VAT_SPECIAL',
     'invoice_title' => '义乌十八腔网络科技有限公司', 'tax_no' => '91330782MADEMO0001', 'invoice_no' => 'INV-2026-0001',
     'status' => 'ISSUED', 'issued_date' => '2026-01-20', 'operator_id' => 4, 'our_company_id' => 1,
     'content_desc' => '软件开发服务费', 'customer_id' => 1, 'applicant_id' => 2, 'issued_by' => 4],
    ['contract_id' => 2, 'amount' => 90000, 'tax_rate' => 0.06, 'tax_amount' => 5094.34, 'invoice_type' => 'VAT_NORMAL',
     'invoice_title' => '杭州星野文化传媒有限公司', 'tax_no' => '91330100MA2GK00002', 'invoice_no' => '',
     'status' => 'APPLIED', 'issued_date' => null, 'operator_id' => 0, 'our_company_id' => 1,
     'content_desc' => '活动服务费', 'customer_id' => 2, 'applicant_id' => 3, 'issued_by' => 0],
    ['contract_id' => 8, 'amount' => 360000, 'tax_rate' => 0.06, 'tax_amount' => 20377.36, 'invoice_type' => 'VAT_SPECIAL',
     'invoice_title' => '义乌十八腔网络科技有限公司', 'tax_no' => '91330782MADEMO0001', 'invoice_no' => 'INV-2026-0002',
     'status' => 'ISSUED', 'issued_date' => '2026-05-20', 'operator_id' => 4, 'our_company_id' => 1,
     'content_desc' => '运维服务费', 'customer_id' => 8, 'applicant_id' => 2, 'issued_by' => 4],
];
foreach ($invoices as &$i) { $i['created_at'] = $now; $i['updated_at'] = $now; }
unset($i);
Db::name('contract_invoice')->insertAll($invoices);
echo "发票: " . count($invoices) . " 条\n";

// ---------- 审批流 ----------
$flows = [
    ['name' => '合同默认审批流', 'code' => 'contract_demo_default', 'biz_type' => 'contract', 'business_type_list' => '[]',
     'direction' => 'ALL', 'trade_attr_condition' => 'ALL', 'min_amount' => 0, 'max_amount' => 99999999.99, 'use_amount' => 0,
     'nodes' => $J([
         ['type' => 'DEPT_LEADER', 'name' => '部门负责人审批', 'approvers' => []],
         ['type' => 'ROLE', 'name' => '财务复核', 'role_code' => 'finance', 'approvers' => []],
     ]),
     'cc_list' => $J([['type' => 'CC', 'name' => '抄送法务', 'role_codes' => ['legal'], 'cc_user_ids' => []]]),
     'form_condition' => '', 'sort_order' => 1, 'status' => 1, 'creator_id' => 1],
    ['name' => '发票默认审批流', 'code' => 'invoice_demo_default', 'biz_type' => 'invoice', 'business_type_list' => '[]',
     'direction' => 'ALL', 'trade_attr_condition' => 'ALL', 'min_amount' => 0, 'max_amount' => 99999999.99, 'use_amount' => 0,
     'nodes' => $J([
         ['type' => 'ROLE', 'name' => '财务审核', 'role_code' => 'finance', 'approvers' => []],
     ]),
     'cc_list' => $J([]),
     'form_condition' => '', 'sort_order' => 1, 'status' => 1, 'creator_id' => 1],
];
foreach ($flows as &$f) { $f['created_at'] = $now; $f['updated_at'] = $now; }
unset($f);
Db::name('approval_flow')->insertAll($flows);
echo "审批流: " . count($flows) . " 条\n";

echo "\n演示数据补入完成！\n";
