-- =====================================================================
-- v2.38.3 数据库迁移：customer 表新增 high_risk（高风险标记）
-- 用途：支撑客户信用评级——逾期 3 笔或存在 90 天以上逾期时自动标记高风险，
--       并联动客户等级降级。配合 CustomerLogic::recalcCreditScore 与
--       app\command\CustomerCreditCheck 命令（每日跑）使用。
-- 执行：按目标引擎执行对应段。迁移幂等，可重复执行。
-- =====================================================================

-- ===================== MySQL =====================
-- 幂等：仅当列不存在时才 ALTER（MySQL 不支持 ADD COLUMN IF NOT EXISTS）
SET @db = DATABASE();
SET @has = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'customer' AND COLUMN_NAME = 'high_risk'
);
SET @sql = IF(@has = 0,
    'ALTER TABLE `customer` ADD COLUMN `high_risk` TINYINT NOT NULL DEFAULT 0 COMMENT ''高风险标记(1=高风险)'',  -- 高风险标记(1=高风险)
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ===================== SQLite =====================
-- SQLite 不支持 ADD COLUMN IF NOT EXISTS；若已执行过会报 "duplicate column"，可忽略。
-- ALTER TABLE customer ADD COLUMN high_risk INTEGER NOT NULL DEFAULT 0;
