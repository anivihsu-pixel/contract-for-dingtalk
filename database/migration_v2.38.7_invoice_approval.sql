-- ============================================================
-- v2.38.7 发票申请审批化迁移（F1/F2：申请→审批→财务开票三段式）
-- 生产 MySQL 执行：mysql -h<H> -u<U> -p <DB> < database/migration_v2.38.7_invoice_approval.sql
-- 本地 SQLite 由 init_sqlite.php 建表时直接包含 + ALTER 补列（同下）
-- ============================================================

-- 1) 审批流程表：加业务类型（contract=合同审批 / invoice=发票审批）
ALTER TABLE `approval_flow` ADD COLUMN `biz_type` VARCHAR(16) NOT NULL DEFAULT 'contract' COMMENT '业务类型(contract=合同审批/invoice=发票审批)' AFTER `cc_list`;  -- 业务类型(contract=合同审批/invoice=发票审批)

-- 2) 审批实例表：加业务类型与业务目标ID（发票审批=发票id；合同审批 target_id 与 contract_id 相同）
ALTER TABLE `approval_instance` ADD COLUMN `biz_type` VARCHAR(16) NOT NULL DEFAULT 'contract' COMMENT '业务类型(contract=合同/invoice=发票)' AFTER `contract_id`,  -- 业务类型(contract=合同/invoice=发票)
    ADD COLUMN `target_id` BIGINT NOT NULL DEFAULT 0 COMMENT '业务目标ID(发票审批=发票id；合同审批与contract_id相同)' AFTER `biz_type`;  -- 业务目标ID(发票审批=发票id；合同审批与contract_id相同)

-- 3) 发票表：申请/审批/开票主体/内容字段（status 语义扩展在代码层，旧 APPLIED 保留为只读历史态）
ALTER TABLE `contract_invoice`
    ADD COLUMN `our_company_id` BIGINT NOT NULL DEFAULT 0 COMMENT '开票主体（我方公司ID，company表）' AFTER `related_id`,  -- 开票主体(我方公司ID，company表)
    ADD COLUMN `content_desc` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '开票内容/品目' AFTER `our_company_id`,  -- 开票内容/品目
    ADD COLUMN `applicant_id` BIGINT NOT NULL DEFAULT 0 COMMENT '申请人ID' AFTER `content_desc`,  -- 申请人ID
    ADD COLUMN `approval_instance_id` BIGINT NOT NULL DEFAULT 0 COMMENT '关联审批实例ID' AFTER `applicant_id`,  -- 关联审批实例ID
    ADD COLUMN `issued_by` BIGINT NOT NULL DEFAULT 0 COMMENT '开票人ID' AFTER `approval_instance_id`,  -- 开票人ID
    ADD KEY `idx_invoice_status` (`status`);

-- 4) 新表：发票申请表单字段配置（后台可启停/排序/新增自定义字段，钉钉表单式）
CREATE TABLE IF NOT EXISTS `invoice_form_field` (
    -- 表注释：发票申请表单字段配置——后台可启停/排序/新增自定义字段（钉钉表单式）
    `id` BIGINT AUTO_INCREMENT COMMENT '主键ID',    -- 主键ID
    `field_key` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '字段唯一键(预置字段=固定key；自定义字段=field_custom_序号)',    -- 字段唯一键(预置字段=固定key；自定义字段=field_custom_序号)
    `field_label` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '显示标签',    -- 显示标签
    `field_type` VARCHAR(24) DEFAULT 'text' COMMENT '字段类型(text/number/textarea/select/company)',    -- 字段类型(text/number/textarea/select/company)
    `field_options` TEXT COMMENT '选项JSON（select 用，[{"value":"..","label":".."}]）',    -- 选项JSON（select 用，[{"value":"..","label":".."}]）
    `option_layout` VARCHAR(16) DEFAULT 'column' COMMENT '单选/多选选项排列方式(column=纵向,tile=横向平铺)',    -- 单选/多选选项排列方式(column=纵向,tile=横向平铺)
    `required` TINYINT DEFAULT 0 COMMENT '是否必填(1=必填)',    -- 是否必填(1=必填)
    `enabled` TINYINT DEFAULT 1 COMMENT '是否启用(0=停用不渲染)',    -- 是否启用(0=停用不渲染)
    `sort_order` INT DEFAULT 0 COMMENT '排序（小在前）',    -- 排序（小在前）
    `is_system` TINYINT DEFAULT 0 COMMENT '系统预置字段(1=禁止删除，仅可停用)',    -- 系统预置字段(1=禁止删除，仅可停用)
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',    -- 创建时间
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',    -- 更新时间
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_inv_form_key` (`field_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='发票申请表单字段配置';

-- 5) 预置字段种子（系统字段禁止删除、仅可停用/排序）
INSERT INTO `invoice_form_field` (`field_key`, `field_label`, `field_type`, `field_options`, `required`, `enabled`, `sort_order`, `is_system`) VALUES
('our_company_id', '开票主体', 'company', '[]', 1, 1, 10, 1),
('content_desc', '开票内容', 'select', '[{"value":"软件开发服务费","label":"软件开发服务费"},{"value":"咨询服务费","label":"咨询服务费"},{"value":"运维服务费","label":"运维服务费"},{"value":"硬件销售费","label":"硬件销售费"},{"value":"其他","label":"其他"}]', 1, 1, 80, 1),
('invoice_type', '开票类型', 'select', '[{"value":"VAT_SPECIAL","label":"我要开增值税专用发票"},{"value":"VAT_NORMAL","label":"我要开普通发票"}]', 1, 1, 30, 1),
('amount', '含税金额（元）', 'number', '[]', 1, 1, 40, 1),
('tax_rate', '税率', 'select', '[{"value":"0.03","label":"3%"},{"value":"0.06","label":"6%"},{"value":"0.09","label":"9%"},{"value":"0.13","label":"13%"}]', 0, 1, 50, 1),
('invoice_title', '发票抬头', 'text', '[]', 0, 1, 60, 1),
('tax_no', '税号', 'text', '[]', 0, 1, 70, 1),
('remark', '申请说明', 'textarea', '[]', 0, 1, 90, 1)
ON DUPLICATE KEY UPDATE `field_label` = VALUES(`field_label`);

-- 6) 默认发票审批流（财务审批单节点；管理员可在后台审批流程编辑/停用）
INSERT INTO `approval_flow` (`name`, `code`, `biz_type`, `min_amount`, `max_amount`, `use_amount`, `nodes`, `cc_list`, `status`, `creator_id`) VALUES
('发票审批', 'INVOICE', 'invoice', 0, 99999999.99, 0,
 '[{"name":"财务审批","type":"ROLE","role_code":"finance","mode":"OR"}]', '{"role_codes":[],"cc_user_ids":[]}', 1, 1)
ON DUPLICATE KEY UPDATE `biz_type` = 'invoice';

-- 7) 新权限：申请开票（普通用户可提交申请；审批通过后由财务 invoice:create 开票）
INSERT INTO `permission` (`id`, `name`, `code`, `group_name`) VALUES (39, '申请开票', 'invoice:apply', '发票管理')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);
-- 角色 2/3/4/5 授「申请开票」（角色权限按 code 关联，幂等；角色 id 按 code 定位避免自增漂移）
INSERT INTO `role_permission` (`role_id`, `perm_id`)
SELECT r.id, p.id FROM `role` r CROSS JOIN `permission` p
WHERE p.`code` = 'invoice:apply' AND r.`code` IN ('manager','legal','finance','user')
ON DUPLICATE KEY UPDATE `role_id` = `role_id`;

-- 8) F9：通用表单字段联动规则表（发票申请/未来审批表单共用；触发字段值变化→目标字段显隐/替换选项）
CREATE TABLE IF NOT EXISTS `form_field_linkage` (
    -- 表注释：表单字段联动规则——触发字段值变化→目标字段显隐/替换选项（发票申请/未来审批表单共用）
    `id` BIGINT AUTO_INCREMENT COMMENT '主键ID',    -- 主键ID
    `form_key` VARCHAR(32) NOT NULL DEFAULT '' COMMENT '表单标识(invoice_apply=发票申请；未来其他审批表单扩展)',    -- 表单标识(invoice_apply=发票申请；未来其他审批表单扩展)
    `trigger_field` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '触发字段(name)',    -- 触发字段(name)
    `trigger_value` VARCHAR(128) NOT NULL DEFAULT '' COMMENT '触发值(触发字段等于该值时生效)',    -- 触发值(触发字段等于该值时生效)
    `target_field` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '目标字段(name)',    -- 目标字段(name)
    `action` VARCHAR(16) DEFAULT 'options' COMMENT '联动动作(show=显示/hide=隐藏/options=替换选项)',    -- 联动动作(show=显示/hide=隐藏/options=替换选项)
    `options` TEXT COMMENT 'action=options 时目标字段的新选项 JSON（[{"value","label"}]）',    -- action=options 时目标字段的新选项 JSON（[{"value","label"}]）
    `sort_order` INT DEFAULT 0 COMMENT '排序（小在前）',    -- 排序（小在前）
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',    -- 创建时间
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',    -- 更新时间
    PRIMARY KEY (`id`),
    KEY `idx_link_form` (`form_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='表单字段联动规则';

-- 9) 公司开票流程优化（H1）：审批流按开票公司分支 + 客户复用 + 表单文案贴合公司流程
-- 9.1 审批流程表：表单条件列（非空=仅该表单字段值命中时匹配；空=默认兜底流程）
ALTER TABLE `approval_flow` ADD COLUMN `form_condition` TEXT NOT NULL DEFAULT '' COMMENT '表单条件(JSON：[{field,value}]，非空=仅表单该字段值命中时匹配；空=默认兜底流程)' AFTER `biz_type`;  -- 表单条件(JSON：字段值命中时匹配；空=默认兜底流程)
-- 9.2 发票表：开票客户ID（customer 表，复用客户开票信息：抬头=客户名、税号=信用代码）
ALTER TABLE `contract_invoice` ADD COLUMN `customer_id` BIGINT NOT NULL DEFAULT 0 COMMENT '开票客户ID（customer表，复用客户开票信息）' AFTER `content_desc`;  -- 开票客户ID(customer表，复用客户开票信息)
-- 9.3 字段种子文案同步（幂等 UPDATE：开票类型两选项、含税金额）
UPDATE `invoice_form_field` SET `field_label`='开票类型', `field_options`='[{"value":"VAT_SPECIAL","label":"我要开增值税专用发票"},{"value":"VAT_NORMAL","label":"我要开普通发票"}]' WHERE `field_key`='invoice_type';
UPDATE `invoice_form_field` SET `field_label`='含税金额（元）' WHERE `field_key`='amount';
-- 9.4 发票类型字典文案同步（列表/详情展示）
UPDATE `system_config` SET `config_value`='{"VAT_SPECIAL":"我要开增值税专用发票","VAT_NORMAL":"我要开普通发票","E_INVOICE":"电子发票","OTHER":"其他"}' WHERE `config_key`='dict_invoice_type';
-- 9.5 开票客户预置字段（customer 类型，选择后联动带出抬头/税号）
INSERT INTO `invoice_form_field` (`field_key`, `field_label`, `field_type`, `field_options`, `required`, `enabled`, `sort_order`, `is_system`) VALUES
('customer_id', '开票客户', 'customer', '[]', 0, 1, 55, 1)
ON DUPLICATE KEY UPDATE `field_type`='customer';

-- 10) 开票税率绑定开票主体（2026-08-02）：税率由后台「公司主体→开票税率」配置，
--     开票申请表单不再单独选择税率，选主体后自动带出（InvoiceController 后端强制从公司读取）
-- 10.1 公司主体表：开票税率列（0.06=6%；0=免税；默认 6%）
ALTER TABLE `company_profile` ADD COLUMN `invoice_tax_rate` DECIMAL(6,4) NOT NULL DEFAULT 0.0600 COMMENT '开票税率(0.06=6%；0=免税；开票申请按主体自动带出，不再单独选择)' AFTER `unified_social_credit_code`;  -- 开票税率(0.06=6%；0=免税；开票申请按主体自动带出)
-- 10.2 演示主体税率（幂等 UPDATE：主体1 现代服务 6%，主体2 文化传媒 13%）
UPDATE `company_profile` SET `invoice_tax_rate` = 0.06 WHERE `id` = 1 AND `invoice_tax_rate` = 0.0600;
UPDATE `company_profile` SET `invoice_tax_rate` = 0.13 WHERE `id` = 2 AND `invoice_tax_rate` = 0.0600;
-- 10.3 表单税率组件停用（enabled=0 不渲染；系统字段禁删可停用，后台「发票表单」设计器可见为停用态）
UPDATE `invoice_form_field` SET `enabled` = 0 WHERE `field_key` = 'tax_rate';
-- 10.4 税率选项扩充常用档（1%/5%）：与公司管理「开票税率」下拉同步（幂等 UPDATE）
UPDATE `invoice_form_field` SET `field_options`='[{"value":"0.01","label":"1%"},{"value":"0.03","label":"3%"},{"value":"0.05","label":"5%"},{"value":"0.06","label":"6%"},{"value":"0.09","label":"9%"},{"value":"0.13","label":"13%"}]' WHERE `field_key`='tax_rate';
