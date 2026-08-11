-- ============================================================
-- v2.43.6 · 资料库权限拆分（上传 / 编辑 / 删除 独立授权）
-- ------------------------------------------------------------
-- 适用：已部署 v2.43.5 及更早版本的存量库（新部署库由三份初始化
--       脚本 init_sqlite.php / init_mysql.php / init.sql 自动带出，
--       无需执行本迁移）。
-- 兼容：MySQL / SQLite 双引擎（标准 SQL，非 OR IGNORE 语法）。
-- 说明：旧「管理资料库」library:manage（id=33）拆分为三个细粒度
--       权限码——library:upload 上传资料库（44）/ library:edit
--       编辑资料库（45）/ library:delete 删除资料库（46），均可
--       在「系统配置→角色权限」逐角色勾选。默认绑定保持 v2.43.5
--       行为：admin / manager / gm 三角色全量拥有（admin 由
--       is_admin 全量短路，显式绑定保持一致）。
-- 幂等：可重复执行（先删旧 33 再按 code 判空插入新码）。
-- ============================================================

-- 1) 移除旧「管理资料库」权限及其角色绑定
DELETE FROM `role_permission` WHERE `perm_id` = 33;
DELETE FROM `permission` WHERE `id` = 33;

-- 2) 新增三个细分权限码（按 code 判断，已存在则不插入）
INSERT INTO `permission` (`id`, `name`, `code`, `group_name`)
SELECT 44, '上传资料库', 'library:upload', '资料库'
WHERE NOT EXISTS (SELECT 1 FROM `permission` WHERE `code` = 'library:upload');

INSERT INTO `permission` (`id`, `name`, `code`, `group_name`)
SELECT 45, '编辑资料库', 'library:edit', '资料库'
WHERE NOT EXISTS (SELECT 1 FROM `permission` WHERE `code` = 'library:edit');

INSERT INTO `permission` (`id`, `name`, `code`, `group_name`)
SELECT 46, '删除资料库', 'library:delete', '资料库'
WHERE NOT EXISTS (SELECT 1 FROM `permission` WHERE `code` = 'library:delete');

-- 3) 角色绑定：admin / manager / gm 全量拥有（与原 library:manage 口径一致）
INSERT INTO `role_permission` (`role_id`, `perm_id`)
SELECT r.id, p.id
FROM `role` r
CROSS JOIN `permission` p
WHERE r.code IN ('admin', 'manager', 'gm')
  AND p.code IN ('library:upload', 'library:edit', 'library:delete')
  AND NOT EXISTS (
    SELECT 1 FROM `role_permission` rp
    WHERE rp.role_id = r.id AND rp.perm_id = p.id
  );
