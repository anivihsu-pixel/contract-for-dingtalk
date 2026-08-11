-- =====================================================================
-- v2.45.0 数据库迁移：客户协作共享（customer_share 表）+ 集团层级（customer.parent_id）
-- 用途：
--   ① customer_share：客户可共享给指定用户/部门（白名单放行，解决「客户归A但B也要关联签合同」）；
--   ② customer.parent_id：集团-分公司层级（多级树，解决「同一集团多分公司分属不同用户」的集团聚合）。
-- 说明：
--   - 共享为显式白名单，不改变全局数据范围；权限判定见 CustomerLogic::canAccessCustomer。
--   - parent_id 与既有「重复客户合并」(merge) 语义并存：合并=去重，parent_id=层级关系。
--   - 新增字段/表均带中文注释（行尾 -- 注释 + MySQL COMMENT 子句），check_db_comments.sh 卡点覆盖。
-- 执行：按目标引擎执行对应段。迁移幂等，可重复执行。
-- =====================================================================

-- ===================== MySQL =====================
-- 1) customer 表新增 parent_id（集团层级，0=顶层）
SET @db = DATABASE();
SET @has = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'customer' AND COLUMN_NAME = 'parent_id'
);
SET @sql = IF(@has = 0,
    'ALTER TABLE `customer` ADD COLUMN `parent_id` BIGINT DEFAULT 0 COMMENT ''父客户ID(集团层级,0=顶层)(v2.45.0)'',  -- 父客户ID(集团层级,0=顶层)(v2.45.0)
',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @hasIdx = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'customer' AND INDEX_NAME = 'idx_customer_parent'
);
SET @sql2 = IF(@hasIdx = 0,
    'ALTER TABLE `customer` ADD INDEX `idx_customer_parent` (`parent_id`),  -- 集团层级查询索引(v2.45.0)
',
    'SELECT 1'
);
PREPARE stmt FROM @sql2;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2) 客户共享表
CREATE TABLE IF NOT EXISTS `customer_share` (
    -- 表注释：客户共享——客户白名单共享给指定用户/部门（v2.45.0 客户协作共享）
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

-- ===================== SQLite =====================
-- SQLite 不支持 ADD COLUMN IF NOT EXISTS；若已执行过会报 "duplicate column"，可忽略。
-- ALTER TABLE customer ADD COLUMN parent_id INTEGER DEFAULT 0;  -- 父客户ID(集团层级,0=顶层)(v2.45.0)
-- CREATE INDEX idx_customer_parent ON customer(parent_id);
--
-- CREATE TABLE IF NOT EXISTS customer_share (
--     -- 表注释：客户共享——客户白名单共享给指定用户/部门（v2.45.0 客户协作共享）
--     id INTEGER PRIMARY KEY AUTOINCREMENT,  -- 主键ID
--     customer_id INTEGER NOT NULL DEFAULT 0,  -- 客户ID
--     target_type TEXT NOT NULL DEFAULT 'USER',  -- 共享对象类型(USER=用户/DEPT=部门)
--     target_id INTEGER NOT NULL DEFAULT 0,  -- 共享对象ID(用户ID或部门ID)
--     share_level TEXT NOT NULL DEFAULT 'VIEW',  -- 共享级别(VIEW=只读,可查看+可关联合同)
--     created_by INTEGER NOT NULL DEFAULT 0,  -- 共享操作人ID
--     created_at TEXT DEFAULT (datetime('now','localtime'))  -- 创建时间
-- );
-- CREATE UNIQUE INDEX uk_share_customer_target ON customer_share(customer_id, target_type, target_id);
-- CREATE INDEX idx_share_customer ON customer_share(customer_id);
