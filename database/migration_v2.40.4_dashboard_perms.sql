-- ============================================================
-- v2.40.3 · 经营看板权限码（全公司经营 / 部门经营 / 我的业绩）
-- ------------------------------------------------------------
-- 适用：已部署 v2.40.2 及更早版本的存量库（新部署库由三份初始化
--       脚本 init_sqlite.php / init_mysql.php / init.sql 自动带出，
--       无需执行本迁移）。
-- 兼容：MySQL / SQLite 双引擎（标准 SQL，非 OR IGNORE 语法）。
-- 说明：工作台卡片显示由角色配置权限控制——
--         dashboard:company  全公司经营（移动端全公司部门排名）
--         dashboard:dept     部门经营（移动端本部门经营卡片 + PC 按部门经营汇总，两端一致）
--         dashboard:stats    我的业绩（移动端）
--       默认绑定保持 v2.40.0 行为：admin/gm 看全公司经营；admin/gm/manager
--       看部门经营；除总经理外（admin/gm/manager/legal/finance/user 角色）
--       均看我的业绩，gm 虽绑定 42 但受 isGeneralManager 判定约束不显示（总经理设计）。
-- 幂等：可重复执行，不重复插入。
-- ============================================================

-- 1) 权限定义（按 code 判断，已存在则不插入）
INSERT INTO `permission` (`id`, `name`, `code`, `group_name`)
SELECT 41, '全公司经营', 'dashboard:company', '经营看板'
WHERE NOT EXISTS (SELECT 1 FROM `permission` WHERE `code` = 'dashboard:company');

INSERT INTO `permission` (`id`, `name`, `code`, `group_name`)
SELECT 42, '我的业绩', 'dashboard:stats', '经营看板'
WHERE NOT EXISTS (SELECT 1 FROM `permission` WHERE `code` = 'dashboard:stats');

INSERT INTO `permission` (`id`, `name`, `code`, `group_name`)
SELECT 43, '部门经营', 'dashboard:dept', '经营看板'
WHERE NOT EXISTS (SELECT 1 FROM `permission` WHERE `code` = 'dashboard:dept');

-- 2) 全公司经营（dashboard:company）→ admin / gm 角色
INSERT INTO `role_permission` (`role_id`, `perm_id`)
SELECT r.id, p.id
FROM `role` r
CROSS JOIN `permission` p
WHERE r.code IN ('admin', 'gm')
  AND p.code = 'dashboard:company'
  AND NOT EXISTS (
    SELECT 1 FROM `role_permission` rp
    WHERE rp.role_id = r.id AND rp.perm_id = p.id
  );

-- 3) 部门经营（dashboard:dept）→ admin / gm / manager 角色（部门经理默认可见本部门经营）
INSERT INTO `role_permission` (`role_id`, `perm_id`)
SELECT r.id, p.id
FROM `role` r
CROSS JOIN `permission` p
WHERE r.code IN ('admin', 'gm', 'manager')
  AND p.code = 'dashboard:dept'
  AND NOT EXISTS (
    SELECT 1 FROM `role_permission` rp
    WHERE rp.role_id = r.id AND rp.perm_id = p.id
  );

-- 4) 我的业绩（dashboard:stats）→ 既有全部角色（保持 v2.40.0「除总经理外全员可见」默认行为；
--    管理员可在角色配置中取消勾选来关闭某角色的该卡片）
INSERT INTO `role_permission` (`role_id`, `perm_id`)
SELECT r.id, p.id
FROM `role` r
CROSS JOIN `permission` p
WHERE r.code IN ('admin', 'gm', 'manager', 'legal', 'finance', 'user')
  AND p.code = 'dashboard:stats'
  AND NOT EXISTS (
    SELECT 1 FROM `role_permission` rp
    WHERE rp.role_id = r.id AND rp.perm_id = p.id
  );
