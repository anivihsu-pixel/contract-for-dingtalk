-- migration_v2.40.0_payment_milestone.sql
-- 收款计划模板 + 里程碑字典（P1-5）
-- 说明：
--   - 新增 dict_payment_milestone 字典（预付款/中期款/尾款/质保金），供「添加回款/付款」弹窗里程碑下拉与
--     模板一键生成（30/50/20 等比例拆分多期，批量接口 /ajax/payment/batch-add）；
--   - 无表结构变更，仅新增字典种子。

INSERT INTO `system_config` (`config_key`, `config_value`, `group_name`)
SELECT 'dict_payment_milestone', '{"DOWN_PAYMENT":"预付款","MID_TERM":"中期款","FINAL_PAYMENT":"尾款","RETENTION":"质保金"}', 'dict'
WHERE NOT EXISTS (SELECT 1 FROM `system_config` WHERE `config_key` = 'dict_payment_milestone');

-- 检查
SELECT config_key, config_value FROM `system_config` WHERE `config_key` = 'dict_payment_milestone';
