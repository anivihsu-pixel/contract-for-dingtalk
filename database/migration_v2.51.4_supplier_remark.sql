-- v2.51.4：供应商邮箱字段改备注
-- init 脚本建表已含 supplier.remark（原 contact_email 字段下架），老库（≤2.51.3）升级补列。
-- 幂等：information_schema 判列存在，不存在才 ALTER。
-- 说明：老库原 contact_email 列保留不删（避免升级时丢失既有邮箱历史数据），新写入/展示均走 remark。
SET @db = DATABASE();
SET @has_remark = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'supplier' AND COLUMN_NAME = 'remark'
);
SET @sql = IF(@has_remark = 0,
    'ALTER TABLE `supplier` ADD COLUMN `remark` VARCHAR(255) NOT NULL DEFAULT '''' COMMENT ''备注(原contact_email改备注)'' AFTER `contact_mobile`',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
