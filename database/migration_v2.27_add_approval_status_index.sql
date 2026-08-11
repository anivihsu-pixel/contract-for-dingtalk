-- +----------------------------------------------------------------------
-- | P0-4 迁移：为 approval_instance.status 补单列索引
-- | 背景：提交审批时 processOverdueApprovals 按 status='PENDING' 过滤审批实例，
-- |       原表仅 contract_id/submitted_by 索引，该过滤退化为全表扫描；高并发下雪崩。
-- | 适用：v2.27.0 之前已建库、未重跑初始化的存量数据库（SQLite / MySQL 均适用）。
-- | 用法：
-- |   SQLite：sqlite3 runtime/data/contract.db < database/migration_v2.27_add_approval_status_index.sql
-- |   MySQL ：mysql -u<user> -p<pass> <dbname> < database/migration_v2.27_add_approval_status_index.sql
-- +----------------------------------------------------------------------

-- SQLite 语法
CREATE INDEX IF NOT EXISTS idx_apv_status ON approval_instance(status);

-- MySQL 语法（执行时若报 "KEY 已存在" 可忽略；下方为幂等写法，主键冲突不致命）
-- 注意：MySQL 不支持 IF NOT EXISTS 于 CREATE INDEX（8.0.29+ 支持），
-- 为兼容旧版，建议直接用下方 ALTER 的在管理端执行前先确认索引不存在。
-- ALTER TABLE approval_instance ADD INDEX idx_apv_status (status);
