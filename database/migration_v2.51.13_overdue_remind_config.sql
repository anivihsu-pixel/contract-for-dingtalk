-- ============================================================
-- migration v2.51.13：到期/逾期提醒封顶天数配置种子
-- 幂等：INSERT IGNORE / INSERT OR IGNORE，重复执行安全。
-- 适用：MySQL 与 SQLite 均可用（MySQL 用 INSERT IGNORE，SQLite 用 INSERT OR IGNORE）。
-- 说明：RemindService 读取（overdueRemindDays()），合同已到期提醒与回款逾期提醒共用此配置，
-- 到期/逾期超过该天数即静默不再每天推送（防止钉钉通知接口调用无限浪费），0=到期/逾期后不提醒。
-- 可在 PC 后台「系统设置→系统配置→业务规则」修改。
-- ============================================================

-- 到期/逾期提醒封顶天数（默认 30 天）
INSERT IGNORE INTO `system_config` (`config_key`, `config_value`, `group_name`)
VALUES ('rule_overdue_remind_days', '30', 'rule');

-- SQLite 版（在 sqlite3 CLI 执行本文件时，直接执行以下等价语句）
-- INSERT OR IGNORE INTO system_config (config_key, config_value, group_name) VALUES ('rule_overdue_remind_days', '30', 'rule');
