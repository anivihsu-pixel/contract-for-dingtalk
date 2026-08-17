-- =====================================================================
-- 客户表缺失列补齐迁移（合并版，一次执行）
-- ------------------------------------------------------------
-- 背景：customer 表在 v2.38.3~v2.40.0 陆续新增 5 个列：
--     credit_score / lifecycle_status / high_risk / credit_manual / industry
--   早期版本的迁移脚本只覆盖了部分列（high_risk / credit_manual / industry
--   各自有独立脚本，credit_score / lifecycle_status 缺失），
--   老库升级后缺列会导致新建/编辑客户报
--   「数据表字段不存在:[credit_score]」（ThinkPHP 10500）→ 500。
-- 本文件把 5 个列全部合并，逐列幂等补全（已存在的列自动跳过），
--   一份文件即可修复部署库，无需逐个执行历史迁移脚本。
-- ------------------------------------------------------------
-- 用法：
--   mysql --host=... --user=... -p --database=你的库名 < migration_v2.38.3_customer_credit_columns.sql
-- （或 mysql 客户端内先 USE 库名; 再 source 本文件）
-- 迁移幂等，可重复执行。
-- =====================================================================

SET @db = DATABASE();

-- 1) credit_score（v2.38.3 信用评分）
SET @has = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'customer' AND COLUMN_NAME = 'credit_score');
SET @sql = IF(@has = 0,
    'ALTER TABLE `customer` ADD COLUMN `credit_score` INT DEFAULT 100 COMMENT ''信用评分(满分100)(v2.38.3)''',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2) lifecycle_status（v2.38.3 生命周期）
SET @has = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'customer' AND COLUMN_NAME = 'lifecycle_status');
SET @sql = IF(@has = 0,
    'ALTER TABLE `customer` ADD COLUMN `lifecycle_status` VARCHAR(16) DEFAULT ''ACTIVE'' COMMENT ''生命周期(POTENTIAL/ACTIVE)(v2.38.3)''',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3) high_risk（v2.38.3 高风险标记）
SET @has = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'customer' AND COLUMN_NAME = 'high_risk');
SET @sql = IF(@has = 0,
    'ALTER TABLE `customer` ADD COLUMN `high_risk` TINYINT NOT NULL DEFAULT 0 COMMENT ''高风险标记(1=高风险)(v2.38.3)''',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 4) credit_manual（v2.38.6 信用评分人工锁定）
SET @has = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'customer' AND COLUMN_NAME = 'credit_manual');
SET @sql = IF(@has = 0,
    'ALTER TABLE `customer` ADD COLUMN `credit_manual` TINYINT NOT NULL DEFAULT 0 COMMENT ''信用评分人工锁定(1=人工维护过，自动重算跳过评分/等级)(v2.38.6)''',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 5) industry（v2.40.0 客户行业）
SET @has = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'customer' AND COLUMN_NAME = 'industry');
SET @sql = IF(@has = 0,
    'ALTER TABLE `customer` ADD COLUMN `industry` VARCHAR(32) DEFAULT '''' COMMENT ''行业(GOV/REAL_ESTATE/FOOD_TOURISM/OTHER)(v2.40.0)''',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- =====================================================================
-- SQLite（演示/单机库）：SQLite 不支持 ADD COLUMN IF NOT EXISTS，
-- 已执行过会报 "duplicate column"，可忽略。
-- ALTER TABLE customer ADD COLUMN credit_score INTEGER DEFAULT 100;
-- ALTER TABLE customer ADD COLUMN lifecycle_status TEXT DEFAULT 'ACTIVE';
-- ALTER TABLE customer ADD COLUMN high_risk INTEGER NOT NULL DEFAULT 0;
-- ALTER TABLE customer ADD COLUMN credit_manual INTEGER NOT NULL DEFAULT 0;
-- ALTER TABLE customer ADD COLUMN industry TEXT DEFAULT '';
-- =====================================================================
