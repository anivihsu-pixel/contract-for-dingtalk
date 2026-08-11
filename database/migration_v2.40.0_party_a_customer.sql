-- =====================================================================
-- v2.40.0 数据库迁移：contract 表新增 party_a_customer_id（甲方客户ID）
-- 用途：移动端新建合同「我方=乙方」时对方为甲方，客户关联需落甲方侧（此前仅支持乙方客户）。
-- 执行：按目标引擎执行对应段。迁移幂等，可重复执行。
-- =====================================================================

-- ===================== MySQL =====================
SET @db = DATABASE();
SET @has = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'contract' AND COLUMN_NAME = 'party_a_customer_id'
);
SET @sql = IF(@has = 0,
    'ALTER TABLE `contract` ADD COLUMN `party_a_customer_id` BIGINT DEFAULT 0 COMMENT ''甲方客户ID(我方=乙方时对方为甲方)'',  -- 甲方客户ID(我方=乙方时对方为甲方)
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ===================== SQLite =====================
-- SQLite 不支持 ADD COLUMN IF NOT EXISTS；若已执行过会报 "duplicate column"，可忽略。
-- ALTER TABLE contract ADD COLUMN party_a_customer_id INTEGER DEFAULT 0;
