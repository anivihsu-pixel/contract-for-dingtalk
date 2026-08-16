-- v2.50.3：客户备注
-- 客户新增/编辑表单移除邮箱输入，增加客户备注字段；保留 contact_email 列兼容历史数据。
SET @db = DATABASE();
SET @has_remark = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'customer' AND COLUMN_NAME = 'remark'
);
SET @sql = IF(@has_remark = 0,
    'ALTER TABLE `customer` ADD COLUMN `remark` VARCHAR(255) DEFAULT '''' COMMENT ''客户备注'' AFTER `address`', -- 客户备注字段
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
