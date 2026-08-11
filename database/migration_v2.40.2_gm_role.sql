-- ============================================================
-- v2.40.2 · 新增总经理角色（gm）
-- ------------------------------------------------------------
-- 适用：已部署 v2.40.1 及更早版本的存量库（新部署库由三份初始化
--       脚本 init_sqlite.php / init_mysql.php / init.sql 自动带出，
--       无需执行本迁移）。
-- 兼容：MySQL / SQLite 双引擎（标准 SQL，非 OR IGNORE 语法）。
-- 说明：总经理（gm）为管理层角色，data_scope=ALL 看全公司经营概览，
--       权限与超级管理员同集（绑定全部权限，随权限表自动扩展）。
-- 幂等：可重复执行，不重复插入。
-- ============================================================

-- 1) 角色（按 code='gm' 判断，已存在则不插入）
INSERT INTO `role` (`id`, `name`, `code`, `description`, `data_scope`, `is_system`)
SELECT 12, '总经理', 'gm', '总经理（管理层：看全公司经营概览）', 'ALL', 1
WHERE NOT EXISTS (SELECT 1 FROM `role` WHERE `code` = 'gm');

-- 2) 角色权限绑定（code='gm' 的角色绑定全部权限，与超级管理员同集）
INSERT INTO `role_permission` (`role_id`, `perm_id`)
SELECT r.id, p.id
FROM `role` r
CROSS JOIN `permission` p
WHERE r.code = 'gm'
  AND NOT EXISTS (
    SELECT 1 FROM `role_permission` rp
    WHERE rp.role_id = r.id AND rp.perm_id = p.id
  );

-- 3) 为指定账号授予总经理角色（将 UID 替换为目标用户的 id）
-- INSERT INTO `user_role` (`user_id`, `role_id`)
-- SELECT <用户ID>, r.id FROM `role` r WHERE r.code = 'gm'
-- WHERE NOT EXISTS (SELECT 1 FROM `user_role` ur WHERE ur.user_id = <用户ID> AND ur.role_id = (SELECT id FROM `role` WHERE `code` = 'gm'));
