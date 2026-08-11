-- migration_v2.40.0_customer_followup.sql
-- 客户跟进手动录入（P0-2）：记录跟进方式 + 下次跟进时间
-- 说明：
--   - customer_activity 增加 next_follow_at（下次跟进时间），供商务记录待办型跟进；
--   - 跟进方式 type 新增手动录入类型：phone（电话）/ visit（拜访）/ meeting（会议）/ wechat（微信），
--     由前端枚举限定，逻辑层做白名单校验（CustomerController::addActivity）。

ALTER TABLE `customer_activity`
    ADD COLUMN `next_follow_at` DATETIME DEFAULT NULL COMMENT '下次跟进时间' AFTER `created_at`;  -- 下次跟进时间

-- 检查
SELECT id, customer_id, type, content, next_follow_at FROM `customer_activity` ORDER BY id DESC LIMIT 5;
