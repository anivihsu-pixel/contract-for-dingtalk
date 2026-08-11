-- =====================================================================
-- v2.35.5 数据库迁移：department 表新增 leader_user_id（部门负责人用户ID）
-- 用途：支撑「部门经理」审批节点按真实部门负责人解析，
--       不再依赖 is_admin 近似（之前本部门无 is_admin 成员会误回退到超管）。
-- 执行：按目标引擎执行对应段。迁移幂等，可重复执行。
-- =====================================================================

-- ===================== MySQL =====================
-- 幂等：仅当列不存在时才 ALTER（MySQL 不支持 ADD COLUMN IF NOT EXISTS）
SET @db = DATABASE();
SET @has = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'department' AND COLUMN_NAME = 'leader_user_id'
);
SET @sql = IF(@has = 0,
    'ALTER TABLE `department` ADD COLUMN `leader_user_id` BIGINT NOT NULL DEFAULT 0 COMMENT ''部门负责人用户ID''',  -- 部门负责人用户ID
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ===================== SQLite =====================
-- SQLite 不支持 ADD COLUMN IF NOT EXISTS；若已执行过会报 "duplicate column"，可忽略。
-- ALTER TABLE department ADD COLUMN leader_user_id INTEGER NOT NULL DEFAULT 0;
