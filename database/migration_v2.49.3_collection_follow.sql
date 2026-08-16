CREATE TABLE IF NOT EXISTS `payment_collection_follow` (
  `id` BIGINT NOT NULL AUTO_INCREMENT COMMENT '主键ID', -- 主键ID
  `payment_id` BIGINT NOT NULL COMMENT '应收计划ID', -- 应收计划ID
  `contract_id` BIGINT NOT NULL COMMENT '合同ID', -- 合同ID
  `user_id` BIGINT NOT NULL COMMENT '跟进人ID', -- 跟进人ID
  `content` TEXT NOT NULL COMMENT '催收内容', -- 催收内容
  `customer_promise` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '客户承诺', -- 客户承诺
  `reason` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '未付款原因', -- 未付款原因
  `promise_date` DATE DEFAULT NULL COMMENT '承诺付款日', -- 承诺付款日
  `next_follow_at` DATETIME DEFAULT NULL COMMENT '下次跟进时间', -- 下次跟进时间
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间', -- 创建时间
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间', -- 更新时间
  PRIMARY KEY (`id`),
  KEY `idx_collection_payment` (`payment_id`,`created_at`),
  KEY `idx_collection_contract_next` (`contract_id`,`next_follow_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='回款催收跟进';
