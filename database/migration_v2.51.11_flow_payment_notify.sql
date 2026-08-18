-- v2.51.11：approval_flow 新增 payment_notify 字段（回款到期/逾期提醒通知人，存量库升级执行本文件，幂等可重复执行）
-- 用途：审批流程各自配置「回款提醒」的通知人（角色/用户），提醒引擎按合同所属流程推送时抄送；空=默认财务角色。
-- 结构：{"role_codes":["finance"],"user_ids":[1,5]}
-- 无 DB 逆操作（回滚：DROP COLUMN payment_notify）
ALTER TABLE approval_flow
  ADD COLUMN `payment_notify` TEXT DEFAULT NULL COMMENT '回款提醒通知人(JSON：{role_codes:[],user_ids:[]}；空=默认财务角色，v2.51.11)'
  AFTER `invoice_notify`;
