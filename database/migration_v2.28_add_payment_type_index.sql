-- +----------------------------------------------------------------------
-- | 迁移脚本 v2.28：payment_record 新增复合索引 idx_pr_type_status
-- | 关联任务：P2-14（M32）
-- | 目的：覆盖驾驶舱 / ProjectLogic::aggregate 频繁按
-- |       payment_type + status 过滤分组的查询，新增复合索引提速。
-- | 说明：payment_record 表本身无 trade_attr 列（该列在 contract 表，
-- |       聚合查询通过 JOIN contract 后按 c.trade_attr 过滤），故索引
-- |       只能落在 payment_record 的 (payment_type, status) 上。
-- | 范围：仅「新增索引」，不改动、不删除任何既有索引。
-- |       idx_apv_status（P0-4）与 (status, planned_date) 索引保持原状。
-- | 执行：按目标库类型二选一（SQLite 用上方语句，MySQL 用下方注释语句）。
-- +----------------------------------------------------------------------

-- ---------- SQLite 执行 ----------
CREATE INDEX IF NOT EXISTS idx_pr_type_status ON payment_record(payment_type, status);

-- ---------- MySQL 执行（去掉上方 SQLite 语句，改用下方） ----------
-- ALTER TABLE payment_record ADD INDEX idx_pr_type_status (payment_type, status);
