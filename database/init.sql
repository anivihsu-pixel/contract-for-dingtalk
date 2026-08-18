-- ============================================================
-- 合同管理系统 — 数据库初始化 (MySQL 5.7+)
-- ------------------------------------------------------------
-- 【重要】本文件为 database/init_mysql.php 的纯 SQL 1:1 镜像，
--   两者结构（26 张表 / 字段 / 索引）与种子数据必须保持一致。
--   基准文件（唯一事实来源）：database/init_mysql.php
--   修改表结构时，须同时同步 init_mysql.php 与 init_sqlite.php，
--   并运行 scripts/check_schema_parity.sh 校验三方字段对齐。
-- 用途：供 DBA 直接导入 MySQL 建库（纯 SQL，无需 PHP 运行环境）。
--   适用于全新部署；若库中已有同名表，请先手动清库再导入。
-- 注意：admin 与演示账号口令哈希已内置，首登强制改密（force_reset=1）。
--       生产环境导入后请立即改密；推荐改用 init_mysql.php（随机口令+打印，更安全）。
-- ============================================================

CREATE DATABASE IF NOT EXISTS contract_dingtalk
  DEFAULT CHARSET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE contract_dingtalk;

-- ============================================================
-- 1. 部门
-- ============================================================
CREATE TABLE `department` (
    `id` BIGINT AUTO_INCREMENT COMMENT '主键ID',    -- 主键ID
    `name` VARCHAR(128) NOT NULL DEFAULT '' COMMENT '名称',    -- 名称
    `parent_id` BIGINT DEFAULT 0 COMMENT '上级ID',    -- 上级ID
    `dingtalk_dept_id` BIGINT DEFAULT 0 COMMENT '钉钉部门ID',    -- 钉钉部门ID
    `sort_order` INT DEFAULT 0 COMMENT '排序号',    -- 排序号
    `leader_user_id` BIGINT DEFAULT 0 COMMENT '部门负责人用户ID',    -- 部门负责人用户ID
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='部门';

-- ============================================================
-- 2. 用户
-- ============================================================
CREATE TABLE `user` (
    `id` BIGINT AUTO_INCREMENT COMMENT '主键ID',    -- 主键ID
    `username` VARCHAR(64) DEFAULT '' COMMENT '登录用户名',    -- 登录用户名
    `password` VARCHAR(255) DEFAULT '' COMMENT '密码哈希',    -- 密码哈希
    `name` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '名称',    -- 名称
    `email` VARCHAR(128) DEFAULT '' COMMENT '邮箱',    -- 邮箱
    `mobile` VARCHAR(32) DEFAULT '' COMMENT '手机号',    -- 手机号
    `avatar` VARCHAR(512) DEFAULT '' COMMENT '头像URL',    -- 头像URL
    `dept_id` BIGINT DEFAULT 0 COMMENT '部门ID',    -- 部门ID
    `is_admin` TINYINT DEFAULT 0 COMMENT '管理员标记(1=是)',    -- 管理员标记(1=是)
    `perm_version` INT NOT NULL DEFAULT 0 COMMENT '权限版本号(角色/权限变更自增,用于失效已登录会话缓存)',    -- 权限版本号(角色/权限变更自增,用于失效已登录会话缓存)
    `status` TINYINT DEFAULT 1 COMMENT '状态(1正常/0禁用/2锁定)',    -- 状态(1正常/0禁用/2锁定)
    `dingtalk_userid` VARCHAR(128) DEFAULT '' COMMENT '钉钉用户ID',    -- 钉钉用户ID
    `dingtalk_unionid` VARCHAR(128) DEFAULT '' COMMENT '钉钉UnionID',    -- 钉钉UnionID
    `last_login_at` DATETIME DEFAULT NULL COMMENT '最后登录时间',    -- 最后登录时间
    `force_reset` TINYINT DEFAULT 0 COMMENT '强制改密(1=是)',    -- 强制改密(1=是)
    `need_handover` TINYINT DEFAULT 0 COMMENT '待交接标记(1=钉钉同步检测疑似离职,待办理离职交接;交接/恢复后清零)',    -- 待交接标记(1=钉钉同步检测疑似离职,待办理离职交接;交接/恢复后清零)
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',    -- 创建时间
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',    -- 更新时间
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_username` (`username`),
    KEY `idx_dingtalk_userid` (`dingtalk_userid`),
    KEY `idx_user_dept` (`dept_id`)   -- P2-5：数据可见性高频 user WHERE dept_id IN 查询
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户';

-- ============================================================
-- 3. 角色
-- ============================================================
CREATE TABLE `role` (
    `id` BIGINT AUTO_INCREMENT COMMENT '主键ID',    -- 主键ID
    `name` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '名称',    -- 名称
    `code` VARCHAR(32) NOT NULL DEFAULT '' COMMENT '编码',    -- 编码
    `description` VARCHAR(255) DEFAULT '' COMMENT '描述',    -- 描述
    `data_scope` VARCHAR(16) DEFAULT 'SELF' COMMENT '数据范围(SELF=仅自己/DEPT=本部门/DEPT_AND_CHILD=本部门及子部门/CUSTOM=自定义部门/ALL=全部)',    -- 数据范围(SELF=仅自己/DEPT=本部门/DEPT_AND_CHILD=本部门及子部门/CUSTOM=自定义部门/ALL=全部)
    `is_system` TINYINT DEFAULT 0 COMMENT '系统内置(1=是)',    -- 系统内置(1=是)
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_role_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='角色';

-- ============================================================
-- 4. 权限
-- ============================================================
CREATE TABLE `permission` (
    `id` BIGINT AUTO_INCREMENT COMMENT '主键ID',    -- 主键ID
    `name` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '名称',    -- 名称
    `code` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '编码',    -- 编码
    `group_name` VARCHAR(64) DEFAULT '' COMMENT '权限分组',    -- 权限分组
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_perm_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='权限';

-- ============================================================
-- 5. 用户角色关联
-- ============================================================
CREATE TABLE `user_role` (
    `user_id` BIGINT NOT NULL COMMENT '用户ID',    -- 用户ID
    `role_id` BIGINT NOT NULL COMMENT '角色ID',    -- 角色ID
    PRIMARY KEY (`user_id`, `role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户角色关联';

-- ============================================================
-- 6. 角色权限关联
-- ============================================================
CREATE TABLE `role_permission` (
    `role_id` BIGINT NOT NULL COMMENT '角色ID',    -- 角色ID
    `perm_id` BIGINT NOT NULL COMMENT '权限ID',    -- 权限ID
    PRIMARY KEY (`role_id`, `perm_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='角色权限关联';

-- ============================================================
-- 6.1 角色部门关联（v2.37.0 新增：自定义数据范围 CUSTOM 的部门白名单）
-- ============================================================
CREATE TABLE `role_dept` (
    `role_id` BIGINT NOT NULL COMMENT '角色ID',    -- 角色ID
    `dept_id` BIGINT NOT NULL COMMENT '部门ID',    -- 部门ID
    PRIMARY KEY (`role_id`, `dept_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='角色部门关联';

-- ============================================================
-- 7. 操作审计日志
-- ============================================================
CREATE TABLE `audit_log` (
    `id` BIGINT AUTO_INCREMENT COMMENT '主键ID',    -- 主键ID
    `user_id` BIGINT NOT NULL DEFAULT 0 COMMENT '用户ID',    -- 用户ID
    `action` VARCHAR(64) DEFAULT '' COMMENT '操作动作',    -- 操作动作
    `target_type` VARCHAR(32) DEFAULT '' COMMENT '目标类型(contract/customer/project等)',    -- 目标类型(contract/customer/project等)
    `target_id` BIGINT DEFAULT 0 COMMENT '目标ID',    -- 目标ID
    `target_title` VARCHAR(255) DEFAULT '' COMMENT '目标标题快照(对象删除后仍可追溯定位)',    -- 目标标题快照
    `content` TEXT COMMENT '内容',    -- 内容
    `ip_address` VARCHAR(64) DEFAULT '' COMMENT '操作IP',    -- 操作IP
    `user_agent` VARCHAR(512) DEFAULT '' COMMENT '浏览器UA',    -- 浏览器UA
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',    -- 创建时间
    PRIMARY KEY (`id`),
    KEY `idx_audit_user` (`user_id`),
    KEY `idx_audit_target` (`target_type`, `target_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='操作审计日志';

-- ============================================================
-- 8. 客户
-- ============================================================
CREATE TABLE `customer` (
    `id` BIGINT AUTO_INCREMENT COMMENT '主键ID',    -- 主键ID
    `name` VARCHAR(128) NOT NULL DEFAULT '' COMMENT '名称',    -- 名称
    `credit_code` VARCHAR(64) DEFAULT '' COMMENT '统一社会信用代码',    -- 统一社会信用代码
    `legal_person` VARCHAR(64) DEFAULT '' COMMENT '法定代表人',    -- 法定代表人
    `contact_name` VARCHAR(64) DEFAULT '' COMMENT '联系人姓名',    -- 联系人姓名
    `contact_mobile` VARCHAR(32) DEFAULT '' COMMENT '联系人手机',    -- 联系人手机
    `contact_email` VARCHAR(128) DEFAULT '' COMMENT '联系人邮箱',    -- 联系人邮箱
    `address` VARCHAR(255) DEFAULT '' COMMENT '地址',    -- 地址
    `remark` VARCHAR(255) DEFAULT '' COMMENT '客户备注',    -- 客户备注
    `level` TINYINT DEFAULT 0 COMMENT '客户等级(已废弃)',    -- 客户等级(已废弃)
    `source` VARCHAR(32) DEFAULT 'MANUAL' COMMENT '来源(MANUAL/IMPORT)',    -- 来源(MANUAL/IMPORT)
    `status` TINYINT DEFAULT 1 COMMENT '状态(1正常/0禁用)',    -- 状态(1正常/0禁用)
    `is_self` TINYINT DEFAULT 0 COMMENT '是否本公司(1=是)',    -- 是否本公司(1=是)
    `lifecycle_status` VARCHAR(16) DEFAULT 'ACTIVE' COMMENT '生命周期(POTENTIAL/ACTIVE)(v2.38.3)',    -- 生命周期(POTENTIAL/ACTIVE)(v2.38.3)
    `industry` VARCHAR(32) DEFAULT '' COMMENT '行业(GOV/REAL_ESTATE/FOOD_TOURISM/OTHER)(v2.40.0)',    -- 行业(GOV/REAL_ESTATE/FOOD_TOURISM/OTHER)(v2.40.0)
    `owner_id` BIGINT DEFAULT 0 COMMENT '归属人ID',    -- 归属人ID
    `dept_id` BIGINT DEFAULT 0 COMMENT '部门ID',    -- 部门ID
    `parent_id` BIGINT DEFAULT 0 COMMENT '父客户ID(集团层级,0=顶层)(v2.45.0)',    -- 父客户ID(集团层级,0=顶层)(v2.45.0)
    `is_deleted` TINYINT DEFAULT 0 COMMENT '软删除(1=已删除)',    -- 软删除(1=已删除)
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',    -- 创建时间
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',    -- 更新时间
    PRIMARY KEY (`id`),
    KEY `idx_customer_owner` (`owner_id`),
    KEY `idx_customer_parent` (`parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='客户';

-- ============================================================
-- 9.5 客户共享记录（v2.45.0：客户协作共享——白名单共享给用户/部门）
-- ============================================================
CREATE TABLE `customer_share` (
    `id` BIGINT AUTO_INCREMENT COMMENT '主键ID',    -- 主键ID
    `customer_id` BIGINT NOT NULL DEFAULT 0 COMMENT '客户ID',    -- 客户ID
    `target_type` VARCHAR(16) NOT NULL DEFAULT 'USER' COMMENT '共享对象类型(USER=用户/DEPT=部门)',    -- 共享对象类型(USER=用户/DEPT=部门)
    `target_id` BIGINT NOT NULL DEFAULT 0 COMMENT '共享对象ID(用户ID或部门ID)',    -- 共享对象ID(用户ID或部门ID)
    `share_level` VARCHAR(16) NOT NULL DEFAULT 'VIEW' COMMENT '共享级别(VIEW=只读,可查看+可关联合同)',    -- 共享级别(VIEW=只读,可查看+可关联合同)
    `created_by` BIGINT NOT NULL DEFAULT 0 COMMENT '共享操作人ID',    -- 共享操作人ID
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',    -- 创建时间
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_share_customer_target` (`customer_id`, `target_type`, `target_id`),
    KEY `idx_share_customer` (`customer_id`),
    KEY `idx_share_target` (`target_type`, `target_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='客户共享';

-- ============================================================
-- 10. 客户交接记录
-- ============================================================
CREATE TABLE `customer_transfer_record` (
    `id` BIGINT AUTO_INCREMENT COMMENT '主键ID',    -- 主键ID
    `customer_id` BIGINT NOT NULL DEFAULT 0 COMMENT '客户ID',    -- 客户ID
    `from_user_id` BIGINT NOT NULL DEFAULT 0 COMMENT '转出人ID',    -- 转出人ID
    `to_user_id` BIGINT NOT NULL DEFAULT 0 COMMENT '转入人ID',    -- 转入人ID
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',    -- 创建时间
    PRIMARY KEY (`id`),
    KEY `idx_transfer_customer` (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='客户交接记录';

-- ============================================================
-- 10.5 客户跟进记录（v2.38.2）
-- ============================================================
CREATE TABLE `customer_activity` (
    `id` BIGINT AUTO_INCREMENT COMMENT '主键ID',    -- 主键ID
    `customer_id` BIGINT NOT NULL DEFAULT 0 COMMENT '客户ID',    -- 客户ID
    `user_id` BIGINT NOT NULL DEFAULT 0 COMMENT '跟进人ID',    -- 跟进人ID
    `type` VARCHAR(32) NOT NULL DEFAULT 'NOTE' COMMENT '类型(CALL/MEETING/VISIT/EMAIL/NOTE/TRANSFER/phone/visit/meeting/wechat)',    -- 类型(CALL/MEETING/VISIT/EMAIL/NOTE/TRANSFER/phone/visit/meeting/wechat)
    `content` TEXT COMMENT '跟进内容',    -- 跟进内容
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',    -- 创建时间
    `next_follow_at` DATETIME DEFAULT NULL COMMENT '下次跟进时间(v2.40.0 手动跟进录入)',    -- 下次跟进时间(v2.40.0 手动跟进录入)
    PRIMARY KEY (`id`),
    KEY `idx_activity_customer` (`customer_id`),
    KEY `idx_activity_user` (`user_id`),
    KEY `idx_activity_time` (`created_at`),
    KEY `idx_activity_follow` (`next_follow_at`, `customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='客户跟进记录';

-- ============================================================
-- 10.6 客户联系人（v2.38.3）
-- ============================================================
-- 表注释：客户联系人——独立联系人表
CREATE TABLE `customer_contact` (
    `id` BIGINT AUTO_INCREMENT COMMENT '主键ID',    -- 主键ID
    `customer_id` BIGINT NOT NULL DEFAULT 0 COMMENT '客户ID',    -- 客户ID
    `name` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '姓名',    -- 姓名
    `phone` VARCHAR(32) DEFAULT '' COMMENT '电话',    -- 电话
    `email` VARCHAR(128) DEFAULT '' COMMENT '邮箱',    -- 邮箱
    `is_primary` TINYINT DEFAULT 0 COMMENT '是否主联系人',    -- 是否主联系人
    `remark` VARCHAR(255) DEFAULT '' COMMENT '备注/更多信息(微信号等)',    -- 备注/更多信息(微信号等)
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',    -- 创建时间
    PRIMARY KEY (`id`),
    KEY `idx_cc_customer` (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='客户联系人';

CREATE TABLE `project` (
    `id` BIGINT AUTO_INCREMENT COMMENT '主键ID',    -- 主键ID
    `name` VARCHAR(128) NOT NULL DEFAULT '' COMMENT '名称',    -- 名称
    `code` VARCHAR(64) DEFAULT '' COMMENT '编码',    -- 编码
    `customer_id` BIGINT DEFAULT 0 COMMENT '客户ID',    -- 客户ID
    `owner_id` BIGINT DEFAULT 0 COMMENT '归属人ID',    -- 归属人ID
    `dept_id` BIGINT DEFAULT 0 COMMENT '部门ID',    -- 部门ID
    `business_type` VARCHAR(32) NOT NULL DEFAULT 'OTHER' COMMENT '业务类型',    -- 业务类型
    `status` VARCHAR(16) DEFAULT 'ACTIVE' COMMENT '状态',    -- 状态
    `budget` DECIMAL(15,2) DEFAULT 0 COMMENT '预算金额',    -- 预算金额
    `start_date` DATE DEFAULT NULL COMMENT '开始日期',    -- 开始日期
    `end_date` DATE DEFAULT NULL COMMENT '结束日期',    -- 结束日期
    `stage` VARCHAR(32) DEFAULT 'PLANNING' COMMENT '执行阶段(PLANNING/EXECUTING/ACCEPTANCE/COMPLETED)(v2.40.0)',    -- 执行阶段(PLANNING/EXECUTING/ACCEPTANCE/COMPLETED)(v2.40.0)
    `progress` INT DEFAULT 0 COMMENT '执行进度(%)(v2.40.0)',    -- 执行进度(%)(v2.40.0)
    `remark` TEXT COMMENT '备注',    -- 备注
    `is_deleted` TINYINT DEFAULT 0 COMMENT '软删除(1=已删除)',    -- 软删除(1=已删除)
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',    -- 创建时间
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',    -- 更新时间
    PRIMARY KEY (`id`),
    KEY `idx_project_owner` (`owner_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='项目';

-- ============================================================
-- 12. 合同主表
-- ============================================================
CREATE TABLE `contract` (
    `id` BIGINT AUTO_INCREMENT COMMENT '主键ID',    -- 主键ID
    `contract_no` VARCHAR(32) NOT NULL DEFAULT '' COMMENT '合同编号',    -- 合同编号
    `title` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '标题',    -- 标题
    `business_type` VARCHAR(32) NOT NULL DEFAULT 'OTHER' COMMENT '业务类型',    -- 业务类型
    `status` VARCHAR(32) DEFAULT 'DRAFT' COMMENT '状态',    -- 状态
    `amount` DECIMAL(15,2) DEFAULT 0.00 COMMENT '金额',    -- 金额
    `party_a_name` VARCHAR(128) DEFAULT '' COMMENT '甲方名称',    -- 甲方名称
    `party_a_contact` VARCHAR(64) DEFAULT '' COMMENT '甲方联系人',    -- 甲方联系人
    `party_a_phone` VARCHAR(32) DEFAULT '' COMMENT '甲方电话',    -- 甲方电话
    `party_a_customer_id` BIGINT DEFAULT 0 COMMENT '甲方客户ID(v2.40.0：我方=乙方时对方为甲方)',    -- 甲方客户ID(v2.40.0：我方=乙方时对方为甲方)
    `party_a_supplier_id` BIGINT DEFAULT 0 COMMENT '甲方供应商ID(v2.46.0：签约方强制关联档案)',    -- 甲方供应商ID(v2.46.0：签约方强制关联档案)
    `party_b_customer_id` BIGINT DEFAULT 0 COMMENT '乙方客户ID',    -- 乙方客户ID
    `party_b_name` VARCHAR(128) DEFAULT '' COMMENT '乙方名称',    -- 乙方名称
    `party_b_contact` VARCHAR(64) DEFAULT '' COMMENT '乙方联系人',    -- 乙方联系人
    `party_b_phone` VARCHAR(32) DEFAULT '' COMMENT '乙方电话',    -- 乙方电话(v2.47.3：联系人/电话拆分填写)
    `party_b_credit_code` VARCHAR(64) DEFAULT '' COMMENT '乙方信用代码',    -- 乙方信用代码
    `effective_date` DATE DEFAULT NULL COMMENT '生效日期',    -- 生效日期
    `expiry_date` DATE DEFAULT NULL COMMENT '到期日期',    -- 到期日期
    `content` LONGTEXT COMMENT '内容',    -- 内容
    `content_plain` LONGTEXT COMMENT '内容(纯文本)',    -- 内容(纯文本)
    `file_url` TEXT COMMENT '文件URL',    -- 文件URL
    `keywords` VARCHAR(512) DEFAULT '' COMMENT '关键词',    -- 关键词
    `owner_id` BIGINT DEFAULT 0 COMMENT '归属人ID',    -- 归属人ID
    `dept_id` BIGINT DEFAULT 0 COMMENT '部门ID',    -- 部门ID
    `parent_id` BIGINT DEFAULT 0 COMMENT '上级ID',    -- 上级ID
    `supplier_id` BIGINT DEFAULT 0 COMMENT '关联供应商ID',    -- 关联供应商ID
    `direction` VARCHAR(16) DEFAULT 'sales' COMMENT '合同方向(sales/purchase)',    -- 合同方向(sales/purchase)
    `trade_attr` TINYINT NOT NULL DEFAULT 1 COMMENT '交易属性(1=交易/计入收支,0=非交易)',    -- 交易属性(1=交易/计入收支,0=非交易)
    `project_id` BIGINT DEFAULT 0 COMMENT '关联项目ID',    -- 关联项目ID
    `flow_id` BIGINT DEFAULT 0 COMMENT '审批流ID',    -- 审批流ID
    `our_company_id` BIGINT DEFAULT 0 COMMENT '本方主体ID',    -- 本方主体ID
    `custom_fields` TEXT COMMENT '自定义字段JSON',    -- 自定义字段JSON
    `creator_id` BIGINT NOT NULL DEFAULT 0 COMMENT '创建人ID',    -- 创建人ID
    `updater_id` BIGINT NOT NULL DEFAULT 0 COMMENT '最后更新人ID',    -- 最后更新人ID
    `is_deleted` TINYINT DEFAULT 0 COMMENT '软删除(1=已删除)',    -- 软删除(1=已删除)
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',    -- 创建时间
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',    -- 更新时间
    `renewed_to` BIGINT DEFAULT 0 COMMENT '续约至新合同ID(v2.38.3)',    -- 续约至新合同ID(v2.38.3)
    `renewed_from` BIGINT DEFAULT 0 COMMENT '续约自原合同ID(v2.38.3)',    -- 续约自原合同ID(v2.38.3)
    `invoice_intent` TEXT DEFAULT NULL COMMENT '随合同申请开票意图JSON(v2.51.10)：{"apply":1,"our_company_id":..,"invoice_type":..,"content_desc":..,"amount":..,"invoice_title":..,"tax_no":..,"remark":..}；合同过审后自动生成待开票发票并清空',    -- 随合同申请开票意图JSON(v2.51.10)
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_contract_no` (`contract_no`),
    KEY `idx_contract_owner` (`owner_id`),
    KEY `idx_contract_status` (`status`),
    KEY `idx_contract_project` (`project_id`),
    KEY `idx_contract_dept` (`dept_id`),
    KEY `idx_contract_expiry` (`expiry_date`),
        KEY `idx_contract_created` (`created_at`),
        -- P1-5 / P2-2：高频过滤/关联字段补索引，消除 OR 条件与列过滤全表扫描
        KEY `idx_contract_creator` (`creator_id`),
        KEY `idx_contract_party_b` (`party_b_customer_id`),
        KEY `idx_contract_party_a` (`party_a_customer_id`),
        KEY `idx_contract_party_a_supplier` (`party_a_supplier_id`),
        KEY `idx_contract_supplier` (`supplier_id`),
        KEY `idx_contract_parent` (`parent_id`),
        KEY `idx_contract_business_type` (`business_type`),
        KEY `idx_contract_ourco` (`our_company_id`),
        KEY `idx_contract_trade_dir_status` (`trade_attr`, `direction`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='合同主表';

CREATE TABLE `contract_execution_cc` (
    `id` BIGINT AUTO_INCREMENT COMMENT '主键ID',    -- 主键ID
    `contract_id` BIGINT NOT NULL DEFAULT 0 COMMENT '合同ID',    -- 合同ID
    `user_id` BIGINT NOT NULL DEFAULT 0 COMMENT '被抄送用户ID',    -- 被抄送用户ID
    `needs_ack` TINYINT NOT NULL DEFAULT 0 COMMENT '是否需要确认知悉(1=是)',    -- 是否需要确认知悉
    `acknowledged_at` DATETIME DEFAULT NULL COMMENT '确认知悉时间',    -- 确认知悉时间
    `created_by` BIGINT NOT NULL DEFAULT 0 COMMENT '触发执行的操作人ID',    -- 触发执行的操作人ID
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '抄送时间',    -- 抄送时间
    PRIMARY KEY (`id`), UNIQUE KEY `uk_execution_cc` (`contract_id`,`user_id`),
    KEY `idx_execution_cc_user` (`user_id`,`needs_ack`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='合同执行抄送知悉轨迹';


-- ============================================================
-- 15. 审批流程定义
-- ============================================================
CREATE TABLE `approval_flow` (
    `id` BIGINT AUTO_INCREMENT COMMENT '主键ID',    -- 主键ID
    `name` VARCHAR(128) NOT NULL DEFAULT '' COMMENT '名称',    -- 名称
    `code` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '编码',    -- 编码
    `business_type_list` TEXT COMMENT '适用业务类型列表(JSON数组，空=全部)', -- 适用业务类型
    `direction` VARCHAR(16) NOT NULL DEFAULT 'ALL' COMMENT '收付款方向(sales/purchase/ALL)', -- 收付款方向
    `trade_attr_condition` VARCHAR(8) NOT NULL DEFAULT 'ALL' COMMENT '交易属性(1/0/ALL)', -- 交易属性
    `min_amount` DECIMAL(15,2) DEFAULT 0.00 COMMENT '最低金额',    -- 最低金额
    `max_amount` DECIMAL(15,2) DEFAULT 99999999.99 COMMENT '最高金额',    -- 最高金额
    `use_amount` TINYINT DEFAULT 1 COMMENT '是否启用金额条件(1=启用/0=不启用)',    -- 是否启用金额条件
    `nodes` TEXT COMMENT '审批流节点定义(JSON数组，仅含审批节点，抄送已独立为cc_list)',    -- 审批流节点定义(JSON数组，仅含审批节点，抄送已独立为cc_list)
    `cc_list` TEXT COMMENT '抄送配置(JSON：{role_codes:[],cc_user_ids:[]})',    -- 抄送配置(JSON：流程级知会，与审批节点平级)
    `biz_type` VARCHAR(16) DEFAULT 'contract' COMMENT '业务类型(contract=合同审批/invoice=发票审批；发票专用流程按此过滤)',    -- 业务类型(contract=合同审批/invoice=发票审批；发票专用流程按此过滤)
    `form_condition` TEXT COMMENT '表单条件(JSON：[{field,value}]，非空=仅表单该字段值命中时匹配；空=默认兜底流程)',    -- 表单条件(JSON：发票按开票公司分支路由)
    `invoice_notify` TEXT COMMENT '随合同申请开票通知确认人(JSON：{role_codes:[],user_ids:[]}；空=默认财务角色，v2.51.10)',    -- 随合同申请开票通知确认人(v2.51.10)
    `sort_order` INT DEFAULT 0 COMMENT '同类型流程内优先级(越小越靠前，审批匹配优先取小；0=未手动排序)',    -- 同类型内排序优先级
    `status` TINYINT DEFAULT 1 COMMENT '状态',    -- 状态
    `creator_id` BIGINT NOT NULL DEFAULT 0 COMMENT '创建人ID',    -- 创建人ID
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',    -- 创建时间
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',    -- 更新时间
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_flow_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='审批流程定义';

-- ============================================================
-- 15.5 审批流抄送知会轨迹（v2.38.0：抄送从节点链独立为流程级cc_list后的轨迹表）
-- ============================================================
CREATE TABLE `approval_cc_log` (
    `id` BIGINT AUTO_INCREMENT COMMENT '主键ID',    -- 主键ID
    `instance_id` BIGINT NOT NULL DEFAULT 0 COMMENT '审批实例ID',    -- 审批实例ID
    `user_id` BIGINT NOT NULL DEFAULT 0 COMMENT '被抄送用户ID',    -- 被抄送用户ID
    `node_order` INT DEFAULT 0 COMMENT '触发时审批节点序号(流程级提交即触发记0)',    -- 触发时审批节点序号(流程级提交即触发记0)
    `role_codes` TEXT COMMENT '命中抄送角色码(JSON数组)',    -- 命中抄送角色码(JSON数组)
    `cc_user_ids` TEXT COMMENT '命中抄送指定用户ID(JSON数组)',    -- 命中抄送指定用户ID(JSON数组)
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '抄送时间',    -- 抄送时间
    PRIMARY KEY (`id`),
    KEY `idx_cc_inst` (`instance_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='审批流抄送知会轨迹';

-- ============================================================
-- 16. 审批实例
-- ============================================================
CREATE TABLE `approval_instance` (
    `id` BIGINT AUTO_INCREMENT COMMENT '主键ID',    -- 主键ID
    `contract_id` BIGINT NOT NULL DEFAULT 0 COMMENT '合同ID(发票审批时为0或关联合同)',    -- 合同ID(发票审批时为0或关联合同)
    `biz_type` VARCHAR(16) DEFAULT 'contract' COMMENT '业务类型(contract=合同/invoice=发票)',    -- 业务类型(contract=合同/invoice=发票)
    `target_id` BIGINT DEFAULT 0 COMMENT '业务目标ID(发票审批=发票id；合同审批与contract_id相同)',    -- 业务目标ID(发票审批=发票id；合同审批与contract_id相同)
    `flow_id` BIGINT NOT NULL DEFAULT 0 COMMENT '审批流ID',    -- 审批流ID
    `status` VARCHAR(16) DEFAULT 'PENDING' COMMENT '状态',    -- 状态
    `current_node_order` INT DEFAULT 1 COMMENT '当前节点序号',    -- 当前节点序号
    `submitted_by` BIGINT NOT NULL DEFAULT 0 COMMENT '提交人ID',    -- 提交人ID
    `submitted_at` DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '提交时间',    -- 提交时间
    `finished_at` DATETIME DEFAULT NULL COMMENT '完成时间',    -- 完成时间
    PRIMARY KEY (`id`),
    KEY `idx_apv_contract` (`contract_id`),
    KEY `idx_apv_submitter` (`submitted_by`),
    KEY `idx_apv_status` (`status`),  -- P0-4：提交扫描按 status 过滤，补单列索引避免全表扫描（v2.44.1：init.sql 补齐与 init_mysql/init_sqlite 对齐）
    KEY `idx_apv_biz_target_status` (`biz_type`,`target_id`,`status`)  -- v2.48.0：业务对象待审批实例防重/查询
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='审批实例';

-- ============================================================
-- 17. 审批记录
-- ============================================================
CREATE TABLE `approval_record` (
    `id` BIGINT AUTO_INCREMENT COMMENT '主键ID',    -- 主键ID
    `instance_id` BIGINT NOT NULL DEFAULT 0 COMMENT '审批实例ID',    -- 审批实例ID
    `node_order` INT DEFAULT 1 COMMENT '节点序号',    -- 节点序号
    `node_name` VARCHAR(64) DEFAULT '' COMMENT '节点名称',    -- 节点名称
    `approver_id` BIGINT NOT NULL DEFAULT 0 COMMENT '审批人ID',    -- 审批人ID
    `action` VARCHAR(16) DEFAULT 'PENDING' COMMENT '操作动作',    -- 操作动作
    `comment` VARCHAR(512) DEFAULT '' COMMENT '审批意见',    -- 审批意见
    `acted_at` DATETIME DEFAULT NULL COMMENT '审批时间',    -- 审批时间
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',    -- 创建时间
    PRIMARY KEY (`id`),
    KEY `idx_apv_rec_instance` (`instance_id`),
    KEY `idx_apv_rec_approver` (`approver_id`),
    KEY `idx_apv_rec_approver_action` (`approver_id`, `action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='审批记录';

-- ============================================================
-- 19. 供应商
-- ============================================================
CREATE TABLE `supplier` (
    `id` BIGINT AUTO_INCREMENT COMMENT '主键ID',    -- 主键ID
    `name` VARCHAR(128) NOT NULL DEFAULT '' COMMENT '名称',    -- 名称
    `type` VARCHAR(32) DEFAULT 'MEDIA' COMMENT '供应商类型(MEDIA/MATERIAL等)',    -- 供应商类型(MEDIA/MATERIAL等)
    `contact_name` VARCHAR(64) DEFAULT '' COMMENT '联系人姓名',    -- 联系人姓名
    `contact_mobile` VARCHAR(32) DEFAULT '' COMMENT '联系人手机',    -- 联系人手机
    `contact_email` VARCHAR(128) DEFAULT '' COMMENT '联系人邮箱',    -- 联系人邮箱
    `address` VARCHAR(255) DEFAULT '' COMMENT '地址',    -- 地址
    `status` TINYINT DEFAULT 1 COMMENT '状态',    -- 状态
    `owner_id` BIGINT DEFAULT 0 COMMENT '归属人ID',    -- 归属人ID
    `dept_id` BIGINT DEFAULT 0 COMMENT '部门ID',    -- 部门ID
    `is_deleted` TINYINT DEFAULT 0 COMMENT '软删除(1=已删除)',    -- 软删除(1=已删除)
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',    -- 创建时间
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',    -- 更新时间
    PRIMARY KEY (`id`),
    KEY `idx_supplier_owner` (`owner_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='供应商';

-- ============================================================
-- 20. 回款记录
-- ============================================================
CREATE TABLE `payment_record` (
    `id` BIGINT AUTO_INCREMENT COMMENT '主键ID',    -- 主键ID
    `contract_id` BIGINT NOT NULL DEFAULT 0 COMMENT '合同ID',    -- 合同ID
    `payment_type` VARCHAR(16) DEFAULT 'RECEIVABLE' COMMENT '回款类型(RECEIVABLE=应收/PAYABLE=应付)',    -- 回款类型(RECEIVABLE=应收/PAYABLE=应付)
    `amount` DECIMAL(15,2) DEFAULT 0.00 COMMENT '金额',    -- 金额
    `planned_date` DATE DEFAULT NULL COMMENT '计划日期',    -- 计划日期
    `actual_date` DATE DEFAULT NULL COMMENT '实际日期',    -- 实际日期
    `payment_method` VARCHAR(32) DEFAULT '' COMMENT '回款方式',    -- 回款方式
    `status` VARCHAR(16) DEFAULT 'PENDING' COMMENT '状态',    -- 状态
    `description` VARCHAR(255) DEFAULT '' COMMENT '描述',    -- 描述
    `operator_id` BIGINT DEFAULT 0 COMMENT '操作人ID',    -- 操作人ID
    `paid_amount` DECIMAL(15,2) DEFAULT 0.00 COMMENT '已确认收款金额（部分确认时使用）',    -- 已确认收款金额（部分确认时使用）
    `parent_id` BIGINT DEFAULT 0 COMMENT '拆分来源记录ID（部分确认生成的剩余待收）',    -- 拆分来源记录ID（部分确认生成的剩余待收）
    `invoice_no` VARCHAR(64) DEFAULT '' COMMENT '关联发票号(v2.38.3)',    -- 关联发票号(v2.38.3)
    `milestone` VARCHAR(128) DEFAULT '' COMMENT '里程碑名称(v2.38.3)',    -- 里程碑名称(v2.38.3)
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',    -- 创建时间
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',    -- 更新时间
    PRIMARY KEY (`id`),
    KEY `idx_payment_contract` (`contract_id`),
    KEY `idx_payment_status_plan` (`status`, `planned_date`),
    KEY `idx_pr_type_status` (`payment_type`, `status`)  -- P2-14：payment_record 无 trade_attr 列(该列在 contract 表)，按回款类型+状态过滤分组（v2.44.1：init.sql 补齐与 init_mysql/init_sqlite 对齐）
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='回款记录';

-- ============================================================
-- 21. 发票
-- ============================================================
CREATE TABLE `payment_collection_follow` (
  `id` BIGINT AUTO_INCREMENT PRIMARY KEY COMMENT '主键ID',  -- 主键ID
  `payment_id` BIGINT NOT NULL COMMENT '应收计划ID',  -- 应收计划ID
  `contract_id` BIGINT NOT NULL COMMENT '合同ID',  -- 合同ID
  `user_id` BIGINT NOT NULL COMMENT '跟进人ID',  -- 跟进人ID
  `content` TEXT NOT NULL COMMENT '催收内容',  -- 催收内容
  `customer_promise` VARCHAR(500) DEFAULT '' COMMENT '客户承诺',  -- 客户承诺
  `reason` VARCHAR(500) DEFAULT '' COMMENT '未付款原因',  -- 未付款原因
  `promise_date` DATE DEFAULT NULL COMMENT '承诺付款日',  -- 承诺付款日
  `next_follow_at` DATETIME DEFAULT NULL COMMENT '下次跟进时间',  -- 下次跟进时间
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',  -- 创建时间
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',  -- 更新时间
  KEY `idx_collection_payment` (`payment_id`,`created_at`), KEY `idx_collection_contract_next` (`contract_id`,`next_follow_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='回款催收跟进';

CREATE TABLE `contract_invoice` (
    `id` BIGINT AUTO_INCREMENT COMMENT '主键ID',    -- 主键ID
    `contract_id` BIGINT NOT NULL DEFAULT 0 COMMENT '合同ID',    -- 合同ID
    `amount` DECIMAL(15,2) DEFAULT 0.00 COMMENT '金额',    -- 金额
    `tax_rate` DECIMAL(6,4) DEFAULT 0.0600 COMMENT '税率',    -- 税率
    `tax_amount` DECIMAL(15,2) DEFAULT 0.00 COMMENT '税额',    -- 税额
    `invoice_type` VARCHAR(32) DEFAULT 'VAT_SPECIAL' COMMENT '发票类型(VAT_SPECIAL=专票/VAT_NORMAL=普票/E_INVOICE=电子票)',    -- 发票类型(VAT_SPECIAL=专票/VAT_NORMAL=普票/E_INVOICE=电子票)
    `invoice_title` VARCHAR(255) DEFAULT '' COMMENT '发票抬头',    -- 发票抬头
    `tax_no` VARCHAR(64) DEFAULT '' COMMENT '税号',    -- 税号
    `invoice_no` VARCHAR(64) DEFAULT '' COMMENT '发票号码',    -- 发票号码
    `remark` VARCHAR(255) DEFAULT '' COMMENT '备注',    -- 备注
    `status` VARCHAR(16) DEFAULT 'PENDING_APPROVAL' COMMENT '状态(PENDING_APPROVAL=待审批/APPROVED=待开票/REJECTED=已驳回/ISSUED=已开票/VOID=已作废/RED=已红冲/CANCELLED=已撤回；APPLIED=历史申请态只读)',    -- 状态(PENDING_APPROVAL=待审批/APPROVED=待开票/REJECTED=已驳回/ISSUED=已开票/VOID=已作废/RED=已红冲/CANCELLED=已撤回；APPLIED=历史申请态只读)
    `issued_date` DATE DEFAULT NULL COMMENT '开票日期',    -- 开票日期
    `operator_id` BIGINT DEFAULT 0 COMMENT '操作人ID',    -- 操作人ID
    `related_id` BIGINT DEFAULT 0 COMMENT '关联发票ID（红冲时指向被红冲的原发票）',    -- 关联发票ID（红冲时指向被红冲的原发票）
    `our_company_id` BIGINT DEFAULT 0 COMMENT '开票主体（我方公司ID，company表）',    -- 开票主体（我方公司ID，company表）
    `content_desc` VARCHAR(255) DEFAULT '' COMMENT '开票内容/品目',    -- 开票内容/品目
    `customer_id` BIGINT DEFAULT 0 COMMENT '开票客户ID（customer 表，复用客户开票信息）',    -- 开票客户ID（customer 表，复用客户开票信息）
    `applicant_id` BIGINT DEFAULT 0 COMMENT '申请人ID',    -- 申请人ID
    `approval_instance_id` BIGINT DEFAULT 0 COMMENT '关联审批实例ID',    -- 关联审批实例ID
    `issued_by` BIGINT DEFAULT 0 COMMENT '开票人ID',    -- 开票人ID
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',    -- 创建时间
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',    -- 更新时间
    PRIMARY KEY (`id`),
    KEY `idx_invoice_contract` (`contract_id`),
    KEY `idx_invoice_status` (`status`)  -- F1：财务中心待开票列表按状态筛选
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='发票';

-- ============================================================
-- 21.5 发票申请表单字段配置（F1/F2：后台可启停/排序/新增自定义字段，钉钉表单式）
-- ============================================================
CREATE TABLE `invoice_form_field` (
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

-- ============================================================
-- 21.6 表单字段联动规则（F9：通用组件——触发字段值变化联动目标字段显隐/选项，发票申请/未来审批表单共用）
-- ============================================================
CREATE TABLE `form_field_linkage` (
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

-- ============================================================
-- 22. 提醒日志
-- ============================================================
CREATE TABLE `remind_log` (
    `id` BIGINT AUTO_INCREMENT COMMENT '主键ID',    -- 主键ID
    `target_type` VARCHAR(32) DEFAULT '' COMMENT '目标类型(contract/customer/project等)',    -- 目标类型(contract/customer/project等)
    `target_id` BIGINT DEFAULT 0 COMMENT '目标ID',    -- 目标ID
    `remind_type` VARCHAR(32) DEFAULT '' COMMENT '提醒类型(expire/payment等)',    -- 提醒类型(expire/payment等)
    `remind_at` VARCHAR(32) DEFAULT '' COMMENT '提醒日期',    -- 提醒日期
    `sent` TINYINT DEFAULT 0 COMMENT '推送状态(1=已推送)',    -- 推送状态(1=已推送)
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',    -- 创建时间
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_remind_dedup` (`target_type`, `target_id`, `remind_type`, `remind_at`),
    KEY `idx_remind_target` (`target_type`, `target_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='提醒日志';

-- ============================================================
-- 23. 钉钉会话
-- ============================================================
CREATE TABLE `dingtalk_session` (
    `id` BIGINT AUTO_INCREMENT COMMENT '主键ID',    -- 主键ID
    `user_id` BIGINT NOT NULL DEFAULT 0 COMMENT '用户ID',    -- 用户ID
    `token` VARCHAR(512) NOT NULL DEFAULT '' COMMENT '会话Token',    -- 会话Token
    `expires_at` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00' COMMENT '过期时间',    -- 过期时间
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',    -- 创建时间
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_token` (`token`),
    KEY `idx_dingtalk_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='钉钉会话';

-- ============================================================
-- 24. 系统配置
-- ============================================================
CREATE TABLE `system_config` (
    `id` BIGINT AUTO_INCREMENT COMMENT '主键ID',    -- 主键ID
    `config_key` VARCHAR(128) NOT NULL DEFAULT '' COMMENT '配置键',    -- 配置键
    `config_value` TEXT COMMENT '配置值',    -- 配置值
    `config_type` VARCHAR(16) DEFAULT 'STRING' COMMENT '配置值类型(STRING/INT/JSON)',    -- 配置值类型(STRING/INT/JSON)
    `group_name` VARCHAR(32) DEFAULT '' COMMENT '权限分组',    -- 权限分组
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',    -- 创建时间
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',    -- 更新时间
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_config_key` (`config_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='系统配置';

-- ============================================================
-- 25. 本公司主体
-- ============================================================
CREATE TABLE `company_profile` (
    `id` BIGINT AUTO_INCREMENT COMMENT '主键ID',    -- 主键ID
    `name` VARCHAR(128) NOT NULL DEFAULT '' COMMENT '名称',    -- 名称
    `short_name` VARCHAR(64) DEFAULT '' COMMENT '简称',    -- 简称
    `unified_social_credit_code` VARCHAR(64) DEFAULT '' COMMENT '统一社会信用代码（公司代码）',    -- 统一社会信用代码（公司代码）
    `invoice_tax_rate` DECIMAL(6,4) DEFAULT 0.0600 COMMENT '开票税率(0.06=6%；0=免税；开票申请按主体自动带出，不再单独选择)',    -- 开票税率(0.06=6%；0=免税；开票申请按主体自动带出，不再单独选择)
    `is_default` TINYINT DEFAULT 0 COMMENT '默认标记(1=是)',    -- 默认标记(1=是)
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',    -- 创建时间
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',    -- 更新时间
    PRIMARY KEY (`id`),
    KEY `idx_company_default` (`is_default`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='本公司主体';

-- ============================================================
-- 26. 资料库
-- ============================================================
CREATE TABLE `resource_library` (
    `id` BIGINT AUTO_INCREMENT COMMENT '主键ID',    -- 主键ID
    `category` VARCHAR(32) DEFAULT 'TEMPLATE' COMMENT '合同分类(SERVICE/PURCHASE/LEASE/NDA等)',    -- 合同分类(SERVICE/PURCHASE/LEASE/NDA等)
    `title` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '标题',    -- 标题
    `file_url` VARCHAR(512) DEFAULT '' COMMENT '文件URL',    -- 文件URL
    `file_name` VARCHAR(255) DEFAULT '' COMMENT '文件名',    -- 文件名
    `file_size` BIGINT DEFAULT 0 COMMENT '文件大小(字节)',    -- 文件大小(字节)
    `description` TEXT COMMENT '描述',    -- 描述
    `company_id` BIGINT DEFAULT 0 COMMENT '关联主体ID',    -- 关联主体ID
    `owner_id` BIGINT DEFAULT 0 COMMENT '归属人ID',    -- 归属人ID
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',    -- 创建时间
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',    -- 更新时间
    `content` TEXT COMMENT '结构化字段JSON(开票资料等，空串表示纯文件资料)',    -- 结构化字段JSON(开票资料等，空串表示纯文件资料)
    PRIMARY KEY (`id`),
    KEY `idx_resource_cat` (`category`),
    KEY `idx_resource_company` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='资料库';

CREATE TABLE `notification` (
    `id` BIGINT AUTO_INCREMENT COMMENT '主键ID',    -- 主键ID
    `user_id` BIGINT NOT NULL DEFAULT 0 COMMENT '接收用户ID',    -- 接收用户ID
    `type` VARCHAR(32) DEFAULT '' COMMENT '通知类型(APPROVAL_REJECTED/APPROVED/TRANSFERRED/SUBMITTED/CC/OVERDUE)',    -- 通知类型(APPROVAL_REJECTED/APPROVED/TRANSFERRED/SUBMITTED/CC/OVERDUE)
    `title` VARCHAR(128) DEFAULT '' COMMENT '标题',    -- 标题
    `content` TEXT COMMENT '内容(markdown)',    -- 内容(markdown)
    `url` VARCHAR(255) DEFAULT '' COMMENT '点击跳转链接',    -- 点击跳转链接
    `is_read` TINYINT NOT NULL DEFAULT 0 COMMENT '是否已读(0否1是)',    -- 是否已读(0否1是)
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',    -- 创建时间
    PRIMARY KEY (`id`),
    KEY `idx_notif_user` (`user_id`, `is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='站内消息通知';

-- ============================================================
-- 种子数据（与 init_mysql.php 一致）
-- 说明：admin 与演示账号口令哈希内置，首登强制改密（force_reset=1）。
-- ============================================================

-- 角色（6 个，含数据范围 data_scope）
INSERT INTO `role` (`id`, `name`, `code`, `description`, `data_scope`, `is_system`) VALUES
(1, '超级管理员', 'admin', '系统超级管理员', 'ALL', 1),
(2, '部门经理', 'manager', '部门经理', 'DEPT', 1),
(3, '法务', 'legal', '法务人员', 'SELF', 1),
(4, '财务', 'finance', '财务人员', 'ALL', 1),
(5, '普通用户', 'user', '普通用户', 'SELF', 1),
(12, '总经理', 'gm', '总经理（管理层：看全公司经营概览）', 'ALL', 1);

-- 权限（41 项）
INSERT INTO `permission` (`id`, `name`, `code`, `group_name`) VALUES
(1, '查看合同', 'contract:view', '合同管理'),
(2, '创建合同', 'contract:create', '合同管理'),
(3, '编辑合同', 'contract:edit', '合同管理'),
(4, '删除合同', 'contract:delete', '合同管理'),
(5, '导出合同', 'contract:export', '合同管理'),
(7, '查看审批', 'approval:view', '审批管理'),
(8, '提交审批', 'approval:submit', '审批管理'),
(9, '审批操作', 'approval:approve', '审批管理'),
(10, '查看客户', 'customer:view', '客户管理'),
(11, '创建客户', 'customer:create', '客户管理'),
(12, '编辑客户', 'customer:edit', '客户管理'),
(13, '删除客户', 'customer:delete', '客户管理'),
(14, '用户管理', 'system:user', '系统设置'),
(15, '角色管理', 'system:role', '系统设置'),
(16, '系统配置', 'system:config', '系统设置'),
(17, '同步钉钉', 'dingtalk:sync', '钉钉设置'),
(18, '查看供应商', 'supplier:view', '供应商管理'),
(19, '创建供应商', 'supplier:create', '供应商管理'),
(20, '编辑供应商', 'supplier:edit', '供应商管理'),
(21, '删除供应商', 'supplier:delete', '供应商管理'),
(22, '查看回款', 'payment:view', '回款管理'),
(23, '录入回款', 'payment:create', '回款管理'),
(24, '查看发票', 'invoice:view', '发票管理'),
(25, '开具发票', 'invoice:create', '发票管理'),
(39, '申请开票', 'invoice:apply', '发票管理'),
(28, '查看提醒', 'remind:view', '提醒管理'),
(29, '提醒管理', 'remind:manage', '提醒管理'),
(30, '查看审计', 'audit:view', '系统设置'),
(31, '本公司主体管理', 'company:manage', '系统设置'),
(32, '查看资料库', 'library:view', '资料库'),
(44, '上传资料库', 'library:upload', '资料库'),
(45, '编辑资料库', 'library:edit', '资料库'),
(46, '删除资料库', 'library:delete', '资料库'),
(34, '查看项目', 'project:view', '项目管理'),
(35, '创建项目', 'project:create', '项目管理'),
(36, '编辑项目', 'project:edit', '项目管理'),
(37, '删除项目', 'project:delete', '项目管理'),
(38, '查看相对方', 'party:view', '相对方管理'),
(40, '离职交接', 'system:handover', '系统设置'),
(41, '全公司经营', 'dashboard:company', '经营看板'),
(42, '我的业绩', 'dashboard:stats', '经营看板'),
(43, '部门经营', 'dashboard:dept', '经营看板'),
(49, '导出财务敏感字段', 'export:sensitive', '数据导出');

-- 角色权限映射
-- 角色1 超级管理员：全部 41 项（1-25, 28-43；26/27 为历史空洞不存在）
INSERT INTO `role_permission` (`role_id`, `perm_id`) VALUES
(1,1),(1,2),(1,3),(1,4),(1,5),(1,6),(1,7),(1,8),(1,9),(1,10),
(1,11),(1,12),(1,13),(1,14),(1,15),(1,16),(1,17),(1,18),(1,19),(1,20),
(1,21),(1,22),(1,23),(1,24),(1,25),(1,28),(1,29),(1,30),
(1,31),(1,32),(1,44),(1,45),(1,46),(1,34),(1,35),(1,36),(1,37),(1,38),(1,39),(1,40),(1,41),(1,42),(1,43),(1,49);
-- 角色2 部门经理
INSERT INTO `role_permission` (`role_id`, `perm_id`) VALUES
(2,1),(2,2),(2,3),(2,5),(2,6),(2,7),(2,8),(2,9),(2,10),(2,11),
(2,12),(2,18),(2,19),(2,20),(2,21),(2,22),(2,23),(2,24),(2,25),(2,28),(2,29),(2,30),(2,32),(2,44),(2,45),(2,46),(2,34),(2,35),(2,36),(2,37),(2,38),(2,39),(2,42),(2,43);
-- 角色3 法务
INSERT INTO `role_permission` (`role_id`, `perm_id`) VALUES
(3,1),(3,7),(3,9),(3,18),(3,22),(3,24),(3,28),(3,32),(3,38),(3,39),(3,42);
-- 角色4 财务
INSERT INTO `role_permission` (`role_id`, `perm_id`) VALUES
(4,1),(4,7),(4,9),(4,18),(4,22),(4,23),(4,24),(4,25),(4,28),(4,29),(4,32),(4,38),(4,39),(4,41),(4,42),(4,49);
-- 角色5 普通用户
INSERT INTO `role_permission` (`role_id`, `perm_id`) VALUES
(5,1),(5,2),(5,3),(5,7),(5,8),(5,10),(5,11),(5,12),(5,18),(5,22),
(5,24),(5,28),(5,32),(5,34),(5,35),(5,36),(5,38),(5,39),(5,42);
-- 角色12 总经理：管理层业务全量权限（不含系统管理：system:user/role/config、dingtalk:sync、system:handover），data_scope=ALL 看全公司经营概览
INSERT INTO `role_permission` (`role_id`, `perm_id`) VALUES
(12,1),(12,2),(12,3),(12,4),(12,5),(12,6),(12,7),(12,8),(12,9),(12,10),
(12,11),(12,12),(12,13),(12,18),(12,19),(12,20),
(12,21),(12,22),(12,23),(12,24),(12,25),(12,28),(12,29),(12,30),(12,31),(12,32),
(12,44),(12,45),(12,46),(12,34),(12,35),(12,36),(12,37),(12,38),(12,39),(12,41),(12,42),(12,43),(12,49);

-- 管理员用户（admin，首登强制改密）
-- 注意：init.sql 为 DBA 直接导入的纯 SQL 镜像，无法生成随机口令；
--       生产环境导入后请立即执行 UPDATE 重置 admin 口令，或改用 init_mysql.php（随机口令+打印）。
INSERT INTO `user` (`id`, `username`, `password`, `name`, `is_admin`, `status`, `force_reset`) VALUES
(1, 'admin', '$2y$12$lVYiUG9PWoMXgLGCx4b2O.RyW.3eSEvgojnuzNDNm0DHaaBD7MKIy', '系统管理员', 1, 1, 1);
INSERT INTO `user_role` (`user_id`, `role_id`) VALUES (1, 1);
INSERT INTO `user_role` (`user_id`, `role_id`) VALUES (1, 3);

-- 演示用户（force_reset=1 强制首次登录改密；生产环境请删除这些演示账号）
INSERT INTO `user` (`username`, `password`, `name`, `dept_id`, `status`, `force_reset`) VALUES
('manager01', '$2y$12$hS0rHezNqvVzzgIkvRzM5.NGjNkArBzh1sJlSGwRIqMBdqf15jqU6', '张经理', 1, 1, 1),
('employee01', '$2y$12$hS0rHezNqvVzzgIkvRzM5.NGjNkArBzh1sJlSGwRIqMBdqf15jqU6', '李员工', 1, 1, 1),
('finance01', '$2y$12$hS0rHezNqvVzzgIkvRzM5.NGjNkArBzh1sJlSGwRIqMBdqf15jqU6', '王财务', 1, 1, 1);
INSERT INTO `user_role` (`user_id`, `role_id`) VALUES ((SELECT id FROM (SELECT id FROM `user` WHERE username='manager01') AS t), 2);
INSERT INTO `user_role` (`user_id`, `role_id`) VALUES ((SELECT id FROM (SELECT id FROM `user` WHERE username='employee01') AS t), 5);
INSERT INTO `user_role` (`user_id`, `role_id`) VALUES ((SELECT id FROM (SELECT id FROM `user` WHERE username='finance01') AS t), 4);

-- 审批流不预置。管理员须在后台配置适用流程、审批人及抄送对象；未命中时合同保留草稿。

-- 发票申请表单预置字段池（F1/F2：系统字段禁止删除、仅可停用/排序；后台可新增自定义字段）
INSERT INTO `invoice_form_field` (`id`, `field_key`, `field_label`, `field_type`, `field_options`, `required`, `enabled`, `sort_order`, `is_system`) VALUES
(1, 'our_company_id', '开票主体', 'company', '[]', 1, 1, 10, 1),
(2, 'content_desc', '开票内容', 'select', '[{"value":"软件开发服务费","label":"软件开发服务费"},{"value":"咨询服务费","label":"咨询服务费"},{"value":"运维服务费","label":"运维服务费"},{"value":"硬件销售费","label":"硬件销售费"},{"value":"其他","label":"其他"}]', 1, 1, 35, 1),
(3, 'invoice_type', '开票类型', 'select', '[{"value":"VAT_SPECIAL","label":"我要开增值税专用发票"},{"value":"VAT_NORMAL","label":"我要开普通发票"}]', 1, 1, 30, 1),
(4, 'amount', '含税金额（元）', 'number', '[]', 1, 1, 72, 1),
(5, 'tax_rate', '税率', 'select', '[{"value":"0.01","label":"1%"},{"value":"0.03","label":"3%"},{"value":"0.05","label":"5%"},{"value":"0.06","label":"6%"},{"value":"0.09","label":"9%"},{"value":"0.13","label":"13%"}]', 0, 0, 50, 1),
(6, 'invoice_title', '发票抬头', 'text', '[]', 0, 1, 60, 1),
(7, 'tax_no', '税号', 'text', '[]', 0, 1, 70, 1),
(8, 'remark', '申请说明', 'textarea', '[]', 0, 1, 90, 1),
(9, 'customer_id', '开票客户', 'customer', '[]', 0, 1, 55, 1);

/* 示例合同已移除：全新部署不预置业务数据，避免旧法律类别口径进入新库。
INSERT INTO `contract` (`id`, `contract_no`, `title`, `category`, `status`, `amount`, `party_a_name`, `party_b_name`, `effective_date`, `expiry_date`, `content`, `file_url`, `keywords`, `creator_id`, `created_at`, `updated_at`) VALUES
(1, 'HT-20260709-0001', '软件技术服务合同', 'SERVICE', 'DRAFT', 150000, '我方公司', '上海科技有限公司', '2026-07-01', '2027-07-01', '双方签署软件技术服务协议。', '[{"url":"/uploads/demo/service-contract.pdf","name":"软件技术服务协议.pdf","size":245760}]', '软件,技术服务,含税', 1, NOW(), NOW()),
(2, 'HT-20260709-0002', '设备采购合同', 'PURCHASE', 'PENDING_APPROVAL', 88000, '我方公司', '深圳智能制造有限公司', '2026-08-01', '2027-08-01', '采购智能制造设备一批。', '[{"url":"/uploads/demo/equipment-order.pdf","name":"设备采购订单.pdf","size":358400}]', '设备,采购,制造', 1, NOW(), NOW());
INSERT INTO `contract` (`id`, `contract_no`, `title`, `category`, `status`, `amount`, `direction`, `trade_attr`, `party_a_name`, `party_b_name`, `effective_date`, `expiry_date`, `content`, `file_url`, `keywords`, `creator_id`, `created_at`, `updated_at`) VALUES
(3, 'HT-20260709-0003', 'NDA保密协议', 'NDA', 'ARCHIVED', 0, '', 0, '我方公司', '北京创新信息技术有限公司', '2026-07-01', '2029-07-01', '双方就业务合作涉及的商业秘密签署保密协议。', '[{"url":"/uploads/demo/nda-agreement.pdf","name":"保密协议盖章版.pdf","size":122880}]', '保密,NDA,协议', 1, NOW(), NOW());
INSERT INTO `contract` (`id`, `contract_no`, `title`, `category`, `status`, `amount`, `party_a_name`, `party_b_name`, `effective_date`, `expiry_date`, `content`, `file_url`, `keywords`, `creator_id`, `created_at`, `updated_at`) VALUES
(4, 'HT-20260709-0004', '办公室租赁合同', 'LEASE', 'EXECUTING', 240000, '我方公司', '杭州电商科技公司', '2026-06-01', '2028-06-01', '租赁办公场地。', '[{"url":"/uploads/demo/lease-contract.pdf","name":"租赁合同扫描件.pdf","size":491520}]', '租赁,办公,场地', 1, NOW(), NOW());
INSERT INTO `contract` (`contract_no`, `title`, `category`, `status`, `amount`, `party_a_name`, `party_b_name`, `owner_id`, `dept_id`, `creator_id`, `content`, `file_url`, `keywords`, `created_at`, `updated_at`) VALUES
('HT-DEMO-0001', '员工甲-采购合同', 'PURCHASE', 'DRAFT', 12000, '我方公司', '演示供应商A', (SELECT id FROM (SELECT id FROM `user` WHERE username='employee01') AS t1), 1, (SELECT id FROM (SELECT id FROM `user` WHERE username='employee01') AS t2), '员工甲提交的采购合同演示数据。', '[]', '演示,采购', NOW(), NOW()),
('HT-DEMO-0002', '财务-服务合同', 'SERVICE', 'DRAFT', 30000, '我方公司', '演示供应商B', (SELECT id FROM (SELECT id FROM `user` WHERE username='finance01') AS t1), 1, (SELECT id FROM (SELECT id FROM `user` WHERE username='finance01') AS t2), '财务提交的对外服务合同演示数据。', '[]', '演示,服务', NOW(), NOW()),
('HT-DEMO-0003', '经理-框架协议', 'SERVICE', 'DRAFT', 50000, '我方公司', '演示供应商C', (SELECT id FROM (SELECT id FROM `user` WHERE username='manager01') AS t1), 1, (SELECT id FROM (SELECT id FROM `user` WHERE username='manager01') AS t2), '部门经理提交的框架协议演示数据。', '[]', '演示,框架', NOW(), NOW());

*/
-- 本公司主体（v2.35.9 起精简为 7 列：移除 税号/开户行/账号/地址/电话/法定代表人；v2.38.7 增开票税率列）
INSERT INTO `company_profile` (`id`, `name`, `short_name`, `unified_social_credit_code`, `invoice_tax_rate`, `is_default`, `created_at`, `updated_at`) VALUES
(1, '义乌十八腔网络科技有限公司', '十八腔', '91330782MADEMO0001', 0.06, 1, NOW(), NOW()),
(2, '义乌十八腔文化传媒有限公司', '十八腔传媒', '91330782MADEMO0002', 0.13, 0, NOW(), NOW());
UPDATE `contract` SET `our_company_id` = 1 WHERE `our_company_id` = 0;

-- 资料库
INSERT INTO `resource_library` (`id`, `category`, `title`, `file_url`, `file_name`, `file_size`, `description`, `company_id`, `owner_id`, `created_at`, `updated_at`) VALUES
(1, 'TEMPLATE', '媒体投放服务合同范本', '/uploads/library/demo/media-template.docx', '媒体投放服务合同范本.docx', 102400, '标准媒体投放服务合同范本。', 0, 1, NOW(), NOW()),
(2, 'INVOICE', '十八腔（主体1）开票资料', '/uploads/library/demo/invoice-profile1.pdf', '十八腔开票资料.pdf', 81920, '主体1 增值税开票资料。', 1, 1, NOW(), NOW()),
(3, 'CLAUSE', '保密与竞业限制标准条款', '/uploads/library/demo/clause-nda.docx', '保密与竞业限制标准条款.docx', 61440, '通用保密、知识产权与竞业限制条款范本。', 0, 1, NOW(), NOW());

-- 演示项目 + 关联合同
INSERT INTO `project` (`id`, `name`, `code`, `customer_id`, `owner_id`, `dept_id`, `status`, `budget`, `start_date`, `end_date`, `remark`, `created_at`, `updated_at`) VALUES
(1, '上海科技-年度技术服务', 'PRJ-2026-001', 0, 1, 0, 'ACTIVE', 200000, '2026-07-01', '2027-07-01', '上海科技有限公司年度软件技术服务项目。', NOW(), NOW()),
(2, '智能制造设备采购', 'PRJ-2026-002', 0, 1, 0, 'ACTIVE', 100000, '2026-08-01', '2027-08-01', '深圳智能制造设备采购项目。', NOW(), NOW());
UPDATE `contract` SET `project_id` = 1 WHERE `id` = 1;
UPDATE `contract` SET `project_id` = 2 WHERE `id` = 2;

-- 系统配置 + 字典
INSERT INTO `system_config` (`config_key`, `config_value`, `group_name`) VALUES
('contract_categories', '{"SALES":"销售合同","PURCHASE":"采购合同","LABOR":"劳动合同","LEASE":"租赁合同","NDA":"保密协议","SERVICE":"服务合同","OTHER":"其他"}', 'contract'),
('site_name', '合同管理系统', 'system'),
-- 页脚版权信息（v2.34.0：系统设置「系统配置」页可维护；缺失时 BaseController 回退默认文案）
('copyright', '© 2026 合同管理系统 版权所有', 'system'),
-- 业务规则（2026-08-01：系统设置「系统配置」页可维护；定时任务读取，缺省用下方默认值）
('rule_expire_remind_days', '30,15,7,3,1', 'rule'),
('rule_payment_remind_days', '7,3,1', 'rule'),
('dict_contract_status', '{"DRAFT":"草稿","PENDING_APPROVAL":"待审批","REJECTED":"已驳回","EXECUTING":"执行中","COMPLETED":"已完成","TERMINATED":"已终止","EXPIRED":"已到期","ARCHIVED":"已归档"}', 'dict'),
('dict_supplier_type', '{"MEDIA":"媒体渠道","PRODUCTION":"制作方","FREELANCER":"自由职业者","MATERIAL":"物料供应商","SERVICE":"服务商","OTHER":"其他"}', 'dict'),
('dict_invoice_type', '{"VAT_SPECIAL":"我要开增值税专用发票","VAT_NORMAL":"我要开普通发票","E_INVOICE":"电子发票","OTHER":"其他"}', 'dict'),
('dict_payment_method', '{"BANK":"银行转账","CASH":"现金","CHECK":"支票","ALIPAY":"支付宝","WECHAT":"微信支付"}', 'dict'),
('dict_customer_lifecycle', '{"POTENTIAL":"客户","ACTIVE":"成交"}', 'dict'),
('dict_customer_industry', '{"GOV":"政府单位","REAL_ESTATE":"房地产","FOOD_TOURISM":"餐饮旅游","OTHER":"其他"}', 'dict'),
('dict_customer_source', '{"MANUAL":"手动录入","IMPORT":"批量导入","DINGTALK":"钉钉同步","RECOMMEND":"客户推荐","AD":"广告获客","OTHER":"其他"}', 'dict'),
('dict_project_status', '{"ACTIVE":"进行中","DONE":"已完成","ARCHIVED":"已归档"}', 'dict'),
('dict_business_type', '{"EVENT":"活动服务","AD_CONTENT":"广告与内容服务","GOODS":"商品销售","OTHER":"其他"}', 'dict'),
('dict_payment_milestone', '{"DOWN_PAYMENT":"预付款","MID_TERM":"中期款","FINAL_PAYMENT":"尾款","RETENTION":"质保金"}', 'dict');
