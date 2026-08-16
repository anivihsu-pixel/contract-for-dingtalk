-- v2.50.1 敏感导出独立授权（MySQL 8）
INSERT INTO `permission` (`id`,`name`,`code`,`group_name`) VALUES
(49,'导出财务敏感字段','export:sensitive','数据导出')
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`),`group_name`=VALUES(`group_name`); -- 权限主数据

INSERT IGNORE INTO `role_permission` (`role_id`,`perm_id`) VALUES
(1,49),(4,49),(12,49); -- 默认授权
