-- ============================================================
-- migration v2.38.6：customer 表加 credit_manual 列（信用评分人工锁定）
-- 背景：recalcCreditScore 定时任务会覆盖人工维护的 credit_score/level（M8 修复）。
--   加 credit_manual 标记：1=用户手动改过评分，自动重算跳过评分/等级（high_risk 仍客观计算）。
-- 幂等：MySQL 用 INFORMATION_SCHEMA 判断后 ALTER；SQLite 用 ALTER（已存在会报错，可忽略）。
-- ============================================================

-- MySQL
SET @col_exists = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customer' AND COLUMN_NAME = 'credit_manual'
);
SET @ddl = IF(@col_exists = 0,
  'ALTER TABLE `customer` ADD COLUMN `credit_manual` TINYINT NOT NULL DEFAULT 0 COMMENT ''信用评分人工锁定(1=人工维护过，自动重算跳过评分/等级)'',  -- 信用评分人工锁定(1=人工维护过，自动重算跳过评分/等级)
  'SELECT 1');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- SQLite（sqlite3 CLI 执行本文件时去掉上面 MySQL 段，仅执行下面一句；已存在则忽略报错）
-- ALTER TABLE customer ADD COLUMN credit_manual INTEGER NOT NULL DEFAULT 0;
