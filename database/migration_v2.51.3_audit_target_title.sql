-- v2.51.3：审计日志目标标题快照（对象删除后仍可在审计中心定位）
-- init 脚本建表已含 audit_log.target_title，仅老库（≤2.50.x）升级缺失，此迁移补齐。
-- 幂等：information_schema 判列存在，不存在才 ALTER。
SET @db = DATABASE();
SET @has_target_title = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'audit_log' AND COLUMN_NAME = 'target_title'
);
SET @sql = IF(@has_target_title = 0,
    'ALTER TABLE `audit_log` ADD COLUMN `target_title` VARCHAR(255) NOT NULL DEFAULT '''' COMMENT ''目标标题快照(对象删除后仍可追溯定位)'' AFTER `target_id`',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
