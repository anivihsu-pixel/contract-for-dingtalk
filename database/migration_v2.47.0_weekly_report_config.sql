-- =====================================================================
-- v2.47.0 数据库迁移：经营周报钉钉推送开关（system_config.weekly_report_dd_enabled）
-- 用途：
--   v2.47.0 新增「总经理经营周报」功能，crontab 触发 report:weekly 命令后，
--   站内信始终发送（落库无额度成本），钉钉工作通知仅发极简提示（省接口额度），
--   受此开关控制（系统配置页可切，默认开）。
-- 说明：
--   - 仅一条 system_config 插入，幂等（INSERT IGNORE / INSERT OR IGNORE）。
--   - 三份 init 脚本（init_sqlite/init_mysql/init.sql）已同步该配置项；
--     本迁移面向存量库升级，由 deploy.sh 自动执行（MySQL 段）。
-- 执行：按目标引擎执行对应段。迁移幂等，可重复执行。
-- =====================================================================

-- ===================== MySQL =====================
-- system_config 新增 weekly_report_dd_enabled 配置项（v2.47.0 经营周报钉钉推送开关，默认开）
INSERT IGNORE INTO `system_config` (`config_key`, `config_value`, `group_name`)
VALUES ('weekly_report_dd_enabled', '1', 'rule');  -- 经营周报钉钉推送开关(1=开/0=关,默认开)(v2.47.0)

-- ===================== SQLite =====================
-- SQLite 不支持 INSERT IGNORE，使用 INSERT OR IGNORE 等价语义；deploy.sh 对 SQLite 段注释不自动执行，需手动运行。
-- INSERT OR IGNORE INTO system_config (config_key, config_value, group_name)
-- VALUES ('weekly_report_dd_enabled', '1', 'rule');  -- 经营周报钉钉推送开关(1=开/0=关,默认开)(v2.47.0)
