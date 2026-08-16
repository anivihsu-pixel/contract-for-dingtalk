-- +----------------------------------------------------------------------
-- | 审批流「适用分类多选」+「金额条件开关」字段升级（v2.31.0）
-- | 对应需求：审批流程创建时「使用分类」可由单选改为多选；
-- |          无金额的分类可关闭金额条件，不再要求填写上下限。
-- | 适用：对升级前已存在的 approval_flow 表补加两列。
-- | 注意：init_sqlite.php / init_mysql.php / init.sql 三份初始化脚本已直接包含
-- |       新字段，全新安装的库无需本迁移；本文件仅用于存量库升级。
-- +----------------------------------------------------------------------

-- ============ MySQL ============
ALTER TABLE `approval_flow`
    ADD COLUMN `category_list` TEXT COMMENT '适用分类列表(JSON数组，空=适用全部分类)'  -- 适用分类列表(JSON数组，空=适用全部分类)
    AFTER `category`;
ALTER TABLE `approval_flow`
    ADD COLUMN `use_amount` TINYINT DEFAULT 1 COMMENT '是否启用金额条件(1=启用/0=不启用)'  -- 是否启用金额条件(1=启用/0=不启用)
    AFTER `max_amount`;

-- ============ SQLite ============
-- SQLite 不支持 AFTER，直接追加列即可（顺序无关）：
-- ALTER TABLE approval_flow ADD COLUMN category_list TEXT DEFAULT '[]';
-- ALTER TABLE approval_flow ADD COLUMN use_amount INTEGER DEFAULT 1;
