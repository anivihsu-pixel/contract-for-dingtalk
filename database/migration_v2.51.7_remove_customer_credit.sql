-- =====================================================================
-- v2.51.7 数据库迁移：customer 表移除客户信用评级三列
--   credit_score（信用评分）/ high_risk（高风险标记）/ credit_manual（人工锁定）
-- 用途：产品评估（2026-08-17）——轻量化合同客户管理，信用评分无业务消费
--       （不联动赊销限额/发货拦截/审批），人工锁定补丁证明模型水土不服；
--       逾期风险已由回款报表、仪表盘逾期统计、提醒模块独立覆盖。
--       与 v2.38.13 移除 credit_limit 决策一致（同类"有字段无业务逻辑"）。
-- 执行：按目标引擎执行对应段。迁移幂等，可重复执行。
-- =====================================================================

-- ===================== MySQL =====================
-- 幂等：仅当列存在时才 DROP
SET @db = DATABASE();
SET @col = 'credit_score';
SET @has = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'customer' AND COLUMN_NAME = @col
);
SET @sql = IF(@has = 1,
    'ALTER TABLE `customer` DROP COLUMN `credit_score`',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col = 'high_risk';
SET @has = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'customer' AND COLUMN_NAME = @col
);
SET @sql = IF(@has = 1,
    'ALTER TABLE `customer` DROP COLUMN `high_risk`',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col = 'credit_manual';
SET @has = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'customer' AND COLUMN_NAME = @col
);
SET @sql = IF(@has = 1,
    'ALTER TABLE `customer` DROP COLUMN `credit_manual`',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ===================== SQLite =====================
-- SQLite 3.35+ 支持 DROP COLUMN（列无索引/外键约束时）；若版本过低或已删会报错，可忽略。
-- ALTER TABLE customer DROP COLUMN credit_score;
-- ALTER TABLE customer DROP COLUMN high_risk;
-- ALTER TABLE customer DROP COLUMN credit_manual;
