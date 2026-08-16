-- ============================================================
-- migration v2.38.5：业务规则配置种子（PC 后台「系统设置→系统配置→业务规则」可维护）
-- 幂等：INSERT IGNORE / INSERT OR IGNORE，重复执行安全。
-- 适用：MySQL 与 SQLite 均可用（MySQL 用 INSERT IGNORE，SQLite 用 INSERT OR IGNORE）。
-- ============================================================

-- 合同到期提醒提前天数（remind:check 定时任务读取，逗号分隔，按序触发）
INSERT IGNORE INTO `system_config` (`config_key`, `config_value`, `group_name`)
VALUES ('rule_expire_remind_days', '30,15,7,3,1', 'rule');

-- 回款到期提醒提前天数（remind:check 定时任务读取，逗号分隔）
INSERT IGNORE INTO `system_config` (`config_key`, `config_value`, `group_name`)
VALUES ('rule_payment_remind_days', '7,3,1', 'rule');

-- SQLite 版（在 sqlite3 CLI 执行本文件时，去掉上面反引号即可；或直接执行以下等价语句）
-- INSERT OR IGNORE INTO system_config (config_key, config_value, group_name) VALUES ('rule_expire_remind_days', '30,15,7,3,1', 'rule');
-- INSERT OR IGNORE INTO system_config (config_key, config_value, group_name) VALUES ('rule_payment_remind_days', '7,3,1', 'rule');
