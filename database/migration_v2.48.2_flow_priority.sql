-- v2.48.2 审批流程唯一优先级基线（MySQL 8，幂等）
-- 仅校正系统内置流程；用户自定义流程不擅自改序，冲突由提交门禁明确阻止并提示管理员。
UPDATE `approval_flow` SET `sort_order` = 10
WHERE `code` = 'QUICK' AND (`sort_order` IS NULL OR `sort_order` = 0); -- 简易流程优先级

UPDATE `approval_flow` SET `sort_order` = 20
WHERE `code` IN ('STANDARD', 'LARGE') AND (`sort_order` IS NULL OR `sort_order` = 0); -- 标准及大额流程优先级

UPDATE `approval_flow` SET `sort_order` = 10
WHERE `code` = 'INVOICE' AND (`sort_order` IS NULL OR `sort_order` = 0); -- 内置发票流程优先级
