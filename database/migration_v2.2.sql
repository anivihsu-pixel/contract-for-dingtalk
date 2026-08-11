-- v2.2 迁移脚本：供应商 / 发票 / 合同父子关系 / 自动提醒
-- 运行：sqlite3 runtime/data/contract.db < database/migration_v2.2.sql
-- 说明：本文件为历史迁移，表结构定义同样须带表级与字段级中文注释（与 init 脚本一致）。

-- 1. 供应商表
CREATE TABLE IF NOT EXISTS supplier (
    -- 表注释：供应商——采购与服务方
    id INTEGER PRIMARY KEY AUTOINCREMENT COMMENT '主键ID',    -- 主键ID
    name TEXT NOT NULL DEFAULT '' COMMENT '名称',    -- 名称
    type TEXT DEFAULT 'MEDIA' COMMENT '类型：MEDIA/PRODUCTION/FREELANCER',    -- 类型：MEDIA/PRODUCTION/FREELANCER
    contact_name TEXT DEFAULT '' COMMENT '联系人',    -- 联系人
    contact_mobile TEXT DEFAULT '' COMMENT '联系人手机',    -- 联系人手机
    contact_email TEXT DEFAULT '' COMMENT '联系人邮箱',    -- 联系人邮箱
    address TEXT DEFAULT '' COMMENT '地址',    -- 地址
    status INTEGER DEFAULT 1 COMMENT '状态：1=启用 0=停用',    -- 状态：1=启用 0=停用
    owner_id INTEGER DEFAULT 0 COMMENT '归属人ID',    -- 归属人ID
    dept_id INTEGER DEFAULT 0 COMMENT '部门ID',    -- 部门ID
    is_deleted INTEGER DEFAULT 0 COMMENT '软删标记(1=已删)',    -- 软删标记(1=已删)
    created_at TEXT DEFAULT (datetime('now','localtime')) COMMENT '创建时间',    -- 创建时间
    updated_at TEXT DEFAULT (datetime('now','localtime')) COMMENT '更新时间'    -- 更新时间
);

-- 2. 合同：新增 parent_id（框架合同）/ supplier_id（供应商）以支持父子与执行单模式
ALTER TABLE contract ADD COLUMN parent_id INTEGER DEFAULT 0;   -- 框架合同父ID(0=无)
ALTER TABLE contract ADD COLUMN supplier_id INTEGER DEFAULT 0; -- 关联供应商ID

-- 2.5 客户：新增 is_self 标记，用于「本公司」快捷选择
ALTER TABLE customer ADD COLUMN is_self INTEGER DEFAULT 0;     -- 是否本公司(1=是)

-- 3. 发票表
CREATE TABLE IF NOT EXISTS contract_invoice (
    -- 表注释：发票——开票信息
    id INTEGER PRIMARY KEY AUTOINCREMENT COMMENT '主键ID',    -- 主键ID
    contract_id INTEGER NOT NULL DEFAULT 0 COMMENT '合同ID',    -- 合同ID
    invoice_no TEXT DEFAULT '' COMMENT '发票号码',    -- 发票号码
    amount REAL DEFAULT 0.00 COMMENT '金额',    -- 金额
    invoice_type TEXT DEFAULT 'VAT_SPECIAL' COMMENT '发票类型：VAT_SPECIAL/VAT_NORMAL',    -- 发票类型：VAT_SPECIAL/VAT_NORMAL
    invoice_title TEXT DEFAULT '' COMMENT '发票抬头',    -- 发票抬头
    tax_no TEXT DEFAULT '' COMMENT '税号',    -- 税号
    status TEXT DEFAULT 'APPLIED' COMMENT '状态：APPLIED/ISSUED/INVALID',    -- 状态：APPLIED/ISSUED/INVALID
    issued_date TEXT DEFAULT NULL COMMENT '开票日期',    -- 开票日期
    remark TEXT DEFAULT '' COMMENT '备注',    -- 备注
    operator_id INTEGER DEFAULT 0 COMMENT '操作人ID',    -- 操作人ID
    created_at TEXT DEFAULT (datetime('now','localtime')) COMMENT '创建时间',    -- 创建时间
    updated_at TEXT DEFAULT (datetime('now','localtime')) COMMENT '更新时间'    -- 更新时间
);

-- 4. 提醒日志表
CREATE TABLE IF NOT EXISTS remind_log (
    -- 表注释：提醒日志——到期与待办提醒
    id INTEGER PRIMARY KEY AUTOINCREMENT COMMENT '主键ID',    -- 主键ID
    target_type TEXT NOT NULL DEFAULT '' COMMENT '目标类型：CONTRACT/APPROVAL/PAYMENT',    -- 目标类型：CONTRACT/APPROVAL/PAYMENT
    target_id INTEGER NOT NULL DEFAULT 0 COMMENT '目标ID',    -- 目标ID
    remind_type TEXT DEFAULT '' COMMENT '提醒类型',    -- 提醒类型
    remind_at TEXT DEFAULT (datetime('now','localtime')) COMMENT '提醒时间',    -- 提醒时间
    sent INTEGER DEFAULT 0 COMMENT '是否已发送(1=是)',    -- 是否已发送(1=是)
    message TEXT DEFAULT '' COMMENT '提醒内容'    -- 提醒内容
);
CREATE INDEX IF NOT EXISTS idx_remind ON remind_log(target_type, target_id);

-- 5. 种子数据：供应商
INSERT INTO supplier (name, type, contact_name, contact_mobile, owner_id) VALUES
('字节跳动巨量引擎', 'MEDIA', '赵运营', '13800000101', 1),
('腾讯广告平台', 'MEDIA', '钱经理', '13800000102', 1),
('杭州视觉工作室', 'PRODUCTION', '孙导演', '13700000201', 1),
('上海文案策划', 'FREELANCER', '李老师', '13600000301', 1);
