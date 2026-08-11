-- =====================================================================
-- v2.37.0 数据库迁移：新增 role_dept 表（自定义数据范围 CUSTOM 的部门白名单）
-- 用途：支撑角色数据范围新增「自定义部门(CUSTOM)」档位——
--       当角色 data_scope=CUSTOM 时，该角色可访问 role_dept 中列出的部门集合。
--       配合 AuthLogic::computeVisibility 的可见性谓词引擎生效。
-- 说明：role.data_scope 列在 v2.35 起即为 VARCHAR(16) 默认值 'SELF'，
--       新增的 DEPT_AND_CHILD / CUSTOM 取值无需改列结构，本迁移仅建新表。
-- 执行：按目标引擎执行对应段。迁移幂等，可重复执行。
-- =====================================================================

-- ===================== MySQL =====================
CREATE TABLE IF NOT EXISTS `role_dept` (
    -- 表注释：角色部门关联——角色数据范围=自定义部门(CUSTOM)时的部门白名单
    `role_id` BIGINT NOT NULL COMMENT '角色ID',    -- 角色ID
    `dept_id` BIGINT NOT NULL COMMENT '部门ID',    -- 部门ID
    PRIMARY KEY (`role_id`, `dept_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='角色部门关联';

-- ===================== SQLite =====================
-- CREATE TABLE IF NOT EXISTS role_dept (
--     role_id INTEGER NOT NULL,  -- 角色ID
--     dept_id INTEGER NOT NULL,  -- 部门ID
--     PRIMARY KEY (role_id, dept_id)
-- );
