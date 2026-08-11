-- =====================================================================
-- v2.46.0 数据库迁移：签约方强制关联档案（contract.party_a_supplier_id）
-- 用途：
--   新建合同甲方/乙方必须关联已登记客户/供应商档案（防自由输入绕过客户查重/共享治理）。
--   contract 表已有乙方供应商字段 supplier_id，本迁移新增「甲方供应商」字段，
--   支撑「我方=乙方（付款方）、对方=甲方=供应商」的采购合同场景。
-- 说明：
--   - 甲方供应商字段与乙方供应商 supplier_id 语义对称；甲方可关联客户(party_a_customer_id)或供应商(本字段)。
--   - 校验见 ContractController::save（新建时对方侧强制关联 + 名称一致性）。
--   - 新增字段带中文注释（行尾 -- 注释 + MySQL COMMENT 子句），check_db_comments.sh 卡点覆盖。
-- 执行：按目标引擎执行对应段。迁移幂等，可重复执行。
-- =====================================================================

-- ===================== MySQL =====================
-- contract 表新增 party_a_supplier_id（甲方供应商 ID，v2.46.0）
SET @db = DATABASE();
SET @has = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'contract' AND COLUMN_NAME = 'party_a_supplier_id'
);
SET @sql = IF(@has = 0,
    'ALTER TABLE `contract` ADD COLUMN `party_a_supplier_id` BIGINT DEFAULT 0 COMMENT ''甲方供应商ID(v2.46.0：签约方强制关联档案，我方=乙方时对方甲方可为供应商)'',  -- 甲方供应商ID(v2.46.0：签约方强制关联档案，我方=乙方时对方甲方可为供应商)
',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @hasIdx = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'contract' AND INDEX_NAME = 'idx_contract_party_a_supplier'
);
SET @sql2 = IF(@hasIdx = 0,
    'ALTER TABLE `contract` ADD INDEX `idx_contract_party_a_supplier` (`party_a_supplier_id`),  -- 甲方供应商关联合同索引(v2.46.0)
',
    'SELECT 1'
);
PREPARE stmt FROM @sql2;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ===================== SQLite =====================
-- SQLite 不支持 ADD COLUMN IF NOT EXISTS；若已执行过会报 "duplicate column"，可忽略。
-- ALTER TABLE contract ADD COLUMN party_a_supplier_id INTEGER DEFAULT 0;  -- 甲方供应商ID(v2.46.0：签约方强制关联档案，我方=乙方时对方甲方可为供应商)
-- CREATE INDEX idx_contract_party_a_supplier ON contract(party_a_supplier_id);  -- 甲方供应商关联合同索引(v2.46.0)
