-- v2.38.25：离职交接自动化 —— user 表增加待交接标记
-- 钉钉组织同步时检测「本地在职但钉钉侧已消失」的疑似离职员工，自动标记 need_handover=1，
-- 由管理员/有权账号（system:user）在用户管理页查看待交接列表并办理离职交接；交接/恢复后清零。
ALTER TABLE user ADD COLUMN need_handover INTEGER DEFAULT 0;  -- 待交接标记(1=疑似离职待交接；交接/恢复后清零)
