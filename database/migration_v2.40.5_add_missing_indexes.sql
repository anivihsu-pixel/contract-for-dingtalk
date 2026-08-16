-- +----------------------------------------------------------------------
-- | 迁移脚本 v2.40.5：补齐缺失索引（P2-5 架构审查）
-- | 关联任务：
-- |   - customer_activity(customer_id)：支撑客户详情跟进记录查询 CustomerLogic::getActivities（MySQL 已有 idx_activity_customer，仅 SQLite 需补建）
-- |   - user(dept_id)：支撑数据可见性高频 user WHERE dept_id IN 查询
-- | 范围：仅「新增索引」，不改动、不删除任何既有索引（与 init 三脚本对齐）。
-- | 执行：按目标库类型二选一（SQLite 用上方语句，MySQL 用下方注释语句）。
-- +----------------------------------------------------------------------

-- ---------- SQLite 执行（init_sqlite.php 已含，此处仅供存量库补建）----------
CREATE INDEX IF NOT EXISTS idx_user_dept            ON user(dept_id);
CREATE INDEX IF NOT EXISTS idx_activity_customer    ON customer_activity(customer_id);

-- ---------- MySQL 执行（去掉上方 SQLite 语句，改用下方）----------
-- 注：customer_activity 的 idx_activity_customer 自建表即存在，MySQL 无需再建，仅补 user 索引
-- ALTER TABLE `user` ADD INDEX idx_user_dept (dept_id);

-- 检查（SQLite 下执行）：
-- SELECT name FROM sqlite_master WHERE type='index' AND name IN ('idx_user_dept','idx_activity_customer');
