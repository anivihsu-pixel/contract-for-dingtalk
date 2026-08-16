-- v2.48.0 合同闭环：暂估、签署及纸质原件管理字段（MySQL 8，幂等）
SET @db := DATABASE();

SET @sql := IF((SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=@db AND table_name='approval_instance' AND index_name='idx_apv_biz_target_status')=0,
  "CREATE INDEX `idx_apv_biz_target_status` ON `approval_instance` (`biz_type`,`target_id`,`status`)", "SELECT 1"); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s; -- 业务对象待审批实例防重/查询索引

SET @sql := IF((SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=@db AND table_name='customer_activity' AND index_name='idx_activity_follow')=0,
  "CREATE INDEX `idx_activity_follow` ON `customer_activity` (`next_follow_at`,`customer_id`)", "SELECT 1"); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s; -- 客户跟进提醒扫描索引

SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='contract' AND column_name='sign_status')=0,
  "ALTER TABLE `contract` ADD COLUMN `sign_status` VARCHAR(24) NOT NULL DEFAULT 'WAITING' COMMENT '签署状态(WAITING/OUR_SIGNED/COUNTERPART_SIGNED/COMPLETED/CANCELLED)' AFTER `amount`", "SELECT 1"); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s; -- 签署状态
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='contract' AND column_name='our_signed_date')=0,
  "ALTER TABLE `contract` ADD COLUMN `our_signed_date` DATE DEFAULT NULL COMMENT '我方签署日期' AFTER `sign_status`", "SELECT 1"); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s; -- 我方签署日期
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='contract' AND column_name='counterpart_signed_date')=0,
  "ALTER TABLE `contract` ADD COLUMN `counterpart_signed_date` DATE DEFAULT NULL COMMENT '对方签署日期' AFTER `our_signed_date`", "SELECT 1"); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s; -- 对方签署日期
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='contract' AND column_name='signed_completed_date')=0,
  "ALTER TABLE `contract` ADD COLUMN `signed_completed_date` DATE DEFAULT NULL COMMENT '双方签署完成日期' AFTER `counterpart_signed_date`", "SELECT 1"); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s; -- 双方签署完成日期
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='contract' AND column_name='original_copy_count')=0,
  "ALTER TABLE `contract` ADD COLUMN `original_copy_count` INT DEFAULT NULL COMMENT '纸质原件份数(选填)' AFTER `signed_completed_date`", "SELECT 1"); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s; -- 纸质原件份数
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='contract' AND column_name='original_received')=0,
  "ALTER TABLE `contract` ADD COLUMN `original_received` TINYINT DEFAULT NULL COMMENT '纸质原件是否收回(1是/0否/NULL未填写)' AFTER `original_copy_count`", "SELECT 1"); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s; -- 纸质原件收回状态
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='contract' AND column_name='original_storage')=0,
  "ALTER TABLE `contract` ADD COLUMN `original_storage` VARCHAR(255) DEFAULT '' COMMENT '纸质原件存放位置(选填)' AFTER `original_received`", "SELECT 1"); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s; -- 纸质原件存放位置
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='contract' AND column_name='original_keeper_id')=0,
  "ALTER TABLE `contract` ADD COLUMN `original_keeper_id` BIGINT DEFAULT 0 COMMENT '纸质原件保管人用户ID(选填)' AFTER `original_storage`", "SELECT 1"); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s; -- 纸质原件保管人

-- 存量合同状态已经证明其完成过签署；仅回填仍为默认 WAITING 的历史行，避免执行中/归档合同显示“待签署”。
UPDATE `contract`
SET `sign_status`='COMPLETED',
    `signed_completed_date`=COALESCE(`signed_completed_date`, `effective_date`)
WHERE `status` IN ('SIGNED','EXECUTING','EXPIRED','COMPLETED','ARCHIVED')
  AND `sign_status`='WAITING';

CREATE TABLE IF NOT EXISTS `contract_execution_cc` (
  `id` BIGINT AUTO_INCREMENT COMMENT '主键ID', -- 主键ID
  `contract_id` BIGINT NOT NULL DEFAULT 0 COMMENT '合同ID', -- 合同ID
  `user_id` BIGINT NOT NULL DEFAULT 0 COMMENT '被抄送用户ID', -- 被抄送用户ID
  `needs_ack` TINYINT NOT NULL DEFAULT 0 COMMENT '是否需要确认知悉(1=是)', -- 是否需要确认知悉
  `acknowledged_at` DATETIME DEFAULT NULL COMMENT '确认知悉时间', -- 确认知悉时间
  `created_by` BIGINT NOT NULL DEFAULT 0 COMMENT '触发执行的操作人ID', -- 触发执行的操作人ID
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '抄送时间', -- 抄送时间
  PRIMARY KEY (`id`), UNIQUE KEY `uk_execution_cc` (`contract_id`,`user_id`),
  KEY `idx_execution_cc_user` (`user_id`,`needs_ack`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='合同执行抄送知悉轨迹';
