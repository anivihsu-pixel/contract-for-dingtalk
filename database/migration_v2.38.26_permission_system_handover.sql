-- v2.38.26：新增「离职交接」权限码 system:handover（独立授权）
-- 用于：移动端待交接办理页 /m/handover + 交接/清除接口（handoverUser / clearHandover）
-- 幂等：可重复执行，不重复插入
INSERT INTO `permission` (`name`, `code`, `group_name`)
SELECT '离职交接', 'system:handover', '系统设置'
WHERE NOT EXISTS (SELECT 1 FROM `permission` WHERE `code` = 'system:handover');

-- 超级管理员角色（code=admin）补绑该权限
INSERT INTO `role_permission` (`role_id`, `perm_id`)
SELECT r.id, p.id
FROM `role` r
JOIN `permission` p ON p.code = 'system:handover'
WHERE r.code = 'admin'
  AND NOT EXISTS (
    SELECT 1 FROM `role_permission` rp
    WHERE rp.role_id = r.id AND rp.perm_id = p.id
  );
