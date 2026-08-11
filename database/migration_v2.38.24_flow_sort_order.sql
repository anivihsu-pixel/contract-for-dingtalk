-- +----------------------------------------------------------------------
-- | 审批流程「拖动排序」字段升级（v2.38.24）
-- | 对应需求：审批流程列表支持拖动排序，同类流程（合同/发票）内越靠前优先级越高。
-- | 适用：对升级前已存在的 approval_flow 表补加 sort_order 列。
-- | 注意：init_sqlite.php / init_mysql.php / init.sql 三份初始化脚本已直接包含
-- |       新字段，全新安装的库无需本迁移；本文件仅用于存量库升级。
-- +----------------------------------------------------------------------

-- ============ MySQL ============
ALTER TABLE `approval_flow`
    ADD COLUMN `sort_order` INT DEFAULT 0 COMMENT '同类型流程内优先级(越小越靠前，审批匹配优先取小；0=未手动排序)'  -- 同类型流程内优先级(越小越靠前，审批匹配优先取小；0=未手动排序)
    AFTER `form_condition`;

-- ============ SQLite ============
-- SQLite 不支持 AFTER，直接追加列即可（顺序无关）：
-- ALTER TABLE approval_flow ADD COLUMN sort_order INTEGER DEFAULT 0;
