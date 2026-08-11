-- ============================================================================
-- migration_v2.38.4_remove_sign.sql — 彻底移除签署功能
-- 背景：REV-33「签署功能已软移除」后残留死代码（SignController/sign_task/签署按钮/
--       sign:view|sign:manage 权限/SIGNED 状态不可达），2026-08-01 产品决策彻底移除。
-- 适用：MySQL 8.0+（ALTER TABLE ... DROP COLUMN 需 MySQL 8.0+；旧版本请先备份表后手动处理）
-- 执行：mysql -h<H> -u<U> -p <DB> < database/migration_v2.38.4_remove_sign.sql
-- 幂等：可重复执行（DELETE/DROP 均幂等；DROP COLUMN 若列已删会报错，属预期可忽略）
-- 与三脚本（init_mysql.php/init_sqlite.php/init.sql）同步：30 表/294 字段
-- ============================================================================

-- 1. 先删角色-权限关联（按 code 关联，不写死自增 id）
DELETE FROM `role_permission`
WHERE `perm_id` IN (SELECT `id` FROM `permission` WHERE `code` IN ('sign:view', 'sign:manage'));

-- 2. 删签署权限
DELETE FROM `permission` WHERE `code` IN ('sign:view', 'sign:manage');

-- 3. 删签署/印章字典
DELETE FROM `system_config` WHERE `config_key` IN ('dict_sign_type', 'dict_seal_type');

-- 4. 删签署任务表
DROP TABLE IF EXISTS `sign_task`;

-- 5. 删合同签署日期列（签署功能已移除，无消费方）
ALTER TABLE `contract` DROP COLUMN `sign_date`;
