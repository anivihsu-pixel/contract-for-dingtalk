-- ============================================================================
-- migration_v2.47.2_remove_contract_template.sql — 彻底移除合同模板功能
-- 背景：合同模板自 v2.40 起已「功能性移除」（前端隐藏），本次彻底清除残留——
--       contract_template 表 / contract.template_id 列 / template:manage 权限 /
--       role_permission 关联 / 模板相关 system_config 字典。
-- 适用：MySQL 8.0+（ALTER TABLE ... DROP COLUMN 需 MySQL 8.0.29+；旧版本请先备份表后手动处理）
-- 执行：mysql -h<H> -u<U> -p <DB> < database/migration_v2.47.2_remove_contract_template.sql
-- 幂等：可重复执行（DELETE/DROP 均幂等；DROP COLUMN 若列已删会报错，属预期可忽略）
-- 与三脚本（init_mysql.php/init_sqlite.php/init.sql）同步：均移除建表/列/权限/种子
-- ============================================================================

-- 1. 先删角色-权限关联（按 code 关联，不写死自增 id）
DELETE FROM `role_permission`
WHERE `perm_id` IN (SELECT `id` FROM `permission` WHERE `code` = 'template:manage');

-- 2. 删模板管理权限
DELETE FROM `permission` WHERE `code` = 'template:manage';

-- 3. 删合同模板字典（若有存量）
DELETE FROM `system_config` WHERE `config_key` IN ('dict_template_type', 'dict_contract_template');

-- 4. 删合同模板表
DROP TABLE IF EXISTS `contract_template`;

-- 5. 删合同模板 ID 列（模板功能已移除，无消费方）
ALTER TABLE `contract` DROP COLUMN `template_id`;
