-- =====================================================================
-- v2.38.13 数据库迁移：customer 表移除 credit_limit（信用额度）
-- 用途：产品评估——小规模企业非赊销模式，信用额度无业务逻辑（有字段无校验），
--       用户决定直接移除（保留信用评级 credit_score/high_risk 自动风险提示）。
-- 执行：按目标引擎执行对应段。迁移幂等，可重复执行。
-- =====================================================================

-- ===================== MySQL =====================
-- 幂等：仅当列存在时才 DROP（MySQL 8 支持 DROP COLUMN）
SET @db = DATABASE();
SET @has = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'customer' AND COLUMN_NAME = 'credit_limit'
);
SET @sql = IF(@has = 1,
    'ALTER TABLE `customer` DROP COLUMN `credit_limit`',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ===================== SQLite =====================
-- SQLite 3.35+ 支持 DROP COLUMN（列无索引/外键约束时）；若版本过低或已删会报错，可忽略。
-- ALTER TABLE customer DROP COLUMN credit_limit;
