-- v2.50.4：移除框架合同/执行订单层级
-- 轻量合同管理仅保留合同与项目的关联关系。
-- 本迁移保留 contract.parent_id 列以兼容历史库结构，但清除既有父合同关联；
-- 应用层不再读取或写入该列。

UPDATE `contract`
SET `parent_id` = 0
WHERE `parent_id` <> 0;
