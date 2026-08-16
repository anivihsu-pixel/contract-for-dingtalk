-- v2.50.5：合同流程简化为 草稿 -> 待审批 -> 执行中
-- 历史“已通过/已签署”合同统一并入执行中，移除签署字段与提前执行特批表。

UPDATE `contract` SET `status` = 'EXECUTING' WHERE `status` IN ('APPROVED', 'SIGNED');

UPDATE `system_config`
SET `config_value` = '{"DRAFT":"草稿","PENDING_APPROVAL":"待审批","REJECTED":"已驳回","EXECUTING":"执行中","COMPLETED":"已完成","TERMINATED":"已终止","EXPIRED":"已到期","ARCHIVED":"已归档"}'
WHERE `config_key` = 'dict_contract_status';

DROP TABLE IF EXISTS `contract_early_execution`;

SET @db := DATABASE();
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='contract' AND column_name='sign_status')>0,
  'ALTER TABLE `contract` DROP COLUMN `sign_status`', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='contract' AND column_name='our_signed_date')>0,
  'ALTER TABLE `contract` DROP COLUMN `our_signed_date`', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='contract' AND column_name='counterpart_signed_date')>0,
  'ALTER TABLE `contract` DROP COLUMN `counterpart_signed_date`', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='contract' AND column_name='signed_completed_date')>0,
  'ALTER TABLE `contract` DROP COLUMN `signed_completed_date`', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='contract' AND column_name='original_copy_count')>0,
  'ALTER TABLE `contract` DROP COLUMN `original_copy_count`', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='contract' AND column_name='original_received')>0,
  'ALTER TABLE `contract` DROP COLUMN `original_received`', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='contract' AND column_name='original_storage')>0,
  'ALTER TABLE `contract` DROP COLUMN `original_storage`', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='contract' AND column_name='original_keeper_id')>0,
  'ALTER TABLE `contract` DROP COLUMN `original_keeper_id`', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
