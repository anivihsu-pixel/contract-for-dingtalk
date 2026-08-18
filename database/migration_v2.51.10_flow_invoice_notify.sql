-- v2.51.10：approval_flow 新增 invoice_notify 字段（随合同申请开票通知确认人，存量库升级执行本文件，幂等可重复执行）
-- 用途：审批流程各自配置「随合同申请开票」的确认人（角色/用户），合同过审后按该流程配置通知；空=默认财务角色。
-- 结构：{"role_codes":["finance"],"user_ids":[1,5]}
-- 无 DB 逆操作（回滚：DROP COLUMN invoice_notify）
ALTER TABLE approval_flow
  ADD COLUMN `invoice_notify` TEXT DEFAULT NULL COMMENT '随合同申请开票通知确认人(JSON：{role_codes:[],user_ids:[]}；空=默认财务角色，v2.51.10)'
  AFTER `form_condition`;
