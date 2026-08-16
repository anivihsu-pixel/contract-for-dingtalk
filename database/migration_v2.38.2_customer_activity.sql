-- migration_v2.38.2_customer_activity.sql
-- 客户跟进记录表（v2.38.2）
-- 说明：
--   - 记录客户跟进活动（电话/拜访/邮件/备注等）；
--   - 转移操作自动写入活动记录。

CREATE TABLE IF NOT EXISTS `customer_activity` (
    -- 表注释：客户跟进记录
    `id` BIGINT AUTO_INCREMENT COMMENT '主键ID',    -- 主键ID
    `customer_id` BIGINT NOT NULL DEFAULT 0 COMMENT '客户ID',    -- 客户ID
    `user_id` BIGINT NOT NULL DEFAULT 0 COMMENT '跟进人用户ID',    -- 跟进人用户ID
    `type` VARCHAR(32) NOT NULL DEFAULT 'NOTE' COMMENT '活动类型(NOTE=备注/CALL=电话/VISIT=拜访/EMAIL=邮件等)',    -- 活动类型(NOTE=备注/CALL=电话/VISIT=拜访/EMAIL=邮件等)
    `content` TEXT COMMENT '跟进内容',    -- 跟进内容
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',    -- 创建时间
    PRIMARY KEY (`id`),
    KEY `idx_activity_customer` (`customer_id`),
    KEY `idx_activity_user` (`user_id`),
    KEY `idx_activity_time` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='客户跟进记录';

-- 检查
SELECT COUNT(*) AS activity_count FROM `customer_activity`;
