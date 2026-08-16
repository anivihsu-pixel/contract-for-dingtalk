-- v2.49.0 合同提前执行总经理特批（MySQL 8，幂等）
CREATE TABLE IF NOT EXISTS `contract_early_execution` (
  `id` BIGINT AUTO_INCREMENT COMMENT '主键ID', -- 主键ID
  `contract_id` BIGINT NOT NULL DEFAULT 0 COMMENT '合同ID', -- 合同ID
  `risk_description` TEXT NOT NULL COMMENT '提前执行风险说明', -- 提前执行风险说明
  `status` VARCHAR(24) NOT NULL DEFAULT 'PENDING' COMMENT '状态(PENDING/APPROVED/REJECTED)', -- 特批状态
  `applicant_id` BIGINT NOT NULL DEFAULT 0 COMMENT '申请人ID', -- 申请人ID
  `reviewer_id` BIGINT NOT NULL DEFAULT 0 COMMENT '总经理审批人ID', -- 总经理审批人ID
  `review_comment` TEXT COMMENT '审批意见', -- 审批意见
  `reviewed_at` DATETIME DEFAULT NULL COMMENT '审批时间', -- 审批时间
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间', -- 创建时间
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间', -- 更新时间
  PRIMARY KEY (`id`), KEY `idx_early_contract_status` (`contract_id`,`status`), KEY `idx_early_reviewer` (`reviewer_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='合同提前执行总经理特批记录';
