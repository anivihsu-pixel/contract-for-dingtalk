-- =====================================================================
-- v2.38.12 数据库迁移：customer_contact 表新增 remark（备注/更多信息，微信号等）
-- 用途：独立联系人模块支持记录微信号/其他联系信息（PC+移动端「更多信息」栏）。
-- 执行：按目标引擎执行对应段。迁移幂等，可重复执行。
-- =====================================================================

-- ===================== MySQL =====================
-- 幂等：仅当列不存在时才 ALTER（MySQL 不支持 ADD COLUMN IF NOT EXISTS）
SET @db = DATABASE();
SET @has = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'customer_contact' AND COLUMN_NAME = 'remark'
);
SET @sql = IF(@has = 0,
    'ALTER TABLE `customer_contact` ADD COLUMN `remark` VARCHAR(255) DEFAULT '''' COMMENT ''备注/更多信息(微信号等)'',  -- 备注/更多信息(微信号等)
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ===================== SQLite =====================
-- SQLite 不支持 ADD COLUMN IF NOT EXISTS；若已执行过会报 "duplicate column"，可忽略。
-- ALTER TABLE customer_contact ADD COLUMN remark TEXT DEFAULT '';
