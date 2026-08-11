-- v2.37.5：新增站内消息通知表（站内信兜底）
-- 背景：审批结果（驳回/通过/转交/提交/抄送/超时）此前仅发钉钉工作通知，
--       若接收人未绑定钉钉 userid 或钉钉 API 失败，通知被静默丢弃，应用内无任何痕迹。
--       新增 notification 表作为站内信兜底，确保关键审批事件在应用内「消息中心」始终可见。
-- 执行（生产 MySQL）：mysql -h<HOST> -u<USER> -p <DB> < database/migration_v2.37.5_notification.sql
-- 本地 SQLite 已随 init_sqlite.php 直接建表，无需此脚本。

CREATE TABLE IF NOT EXISTS `notification` (
    -- 表注释：站内消息通知——审批结果等关键事件的站内信兜底（钉钉推送失败/未绑定时不丢消息）
    `id` BIGINT AUTO_INCREMENT COMMENT '主键ID',    -- 主键ID
    `user_id` BIGINT NOT NULL DEFAULT 0 COMMENT '接收用户ID',    -- 接收用户ID
    `type` VARCHAR(32) DEFAULT '' COMMENT '通知类型(APPROVAL_REJECTED/APPROVED/TRANSFERRED/SUBMITTED/CC/OVERDUE)',    -- 通知类型(APPROVAL_REJECTED/APPROVED/TRANSFERRED/SUBMITTED/CC/OVERDUE)
    `title` VARCHAR(128) DEFAULT '' COMMENT '标题',    -- 标题
    `content` TEXT COMMENT '内容(markdown)',    -- 内容(markdown)
    `url` VARCHAR(255) DEFAULT '' COMMENT '点击跳转链接',    -- 点击跳转链接
    `is_read` TINYINT NOT NULL DEFAULT 0 COMMENT '是否已读(0否1是)',    -- 是否已读(0否1是)
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',    -- 创建时间
    PRIMARY KEY (`id`),
    KEY `idx_notif_user` (`user_id`, `is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='站内消息通知';
