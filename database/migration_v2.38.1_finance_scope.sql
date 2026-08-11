-- migration_v2.38.1_finance_scope.sql
-- 财务角色数据范围调整：SELF → ALL
-- 解决财务人员无法为他人创建的合同登记回款的问题（P0-3）
-- 说明：
--   - 财务角色需要处理全公司回款，原 data_scope=SELF 仅能操作本人合同；
--   - 调整为 ALL 后，财务可查看并为所有合同添加/确认回款，不影响其他模块的数据范围。
-- 执行前建议备份。

UPDATE `role` SET `data_scope` = 'ALL' WHERE `code` = 'finance' AND `data_scope` = 'SELF';

-- 检查执行结果
SELECT `id`, `code`, `name`, `data_scope` FROM `role` WHERE `code` = 'finance';
