-- +----------------------------------------------------------------------
-- | 迁移脚本 v2.29：contract 表新增高频过滤/关联字段索引
-- | 关联任务：P1-5（M-Pf3）/ P2-2（M-Pf4）
-- | 目的：消除非管理员工作台/热词（creator_id）、客户详情关联（party_b_customer_id）、
-- |       框架/执行订单筛选（parent_id）、列表分类筛选（category）、
-- |       列表签约主体筛选（our_company_id）等高频查询的全表扫描。
-- | 范围：仅「新增索引」，不改动、不删除任何既有索引（与 init 三脚本对齐）。
-- | 执行：按目标库类型二选一（SQLite 用上方语句，MySQL 用下方注释语句）。
-- +----------------------------------------------------------------------

-- ---------- SQLite 执行（init_sqlite.php 已含，此处仅供存量库补建）----------
CREATE INDEX IF NOT EXISTS idx_contract_creator   ON contract(creator_id);
CREATE INDEX IF NOT EXISTS idx_contract_party_b  ON contract(party_b_customer_id);
CREATE INDEX IF NOT EXISTS idx_contract_parent    ON contract(parent_id);
CREATE INDEX IF NOT EXISTS idx_contract_category  ON contract(category);
CREATE INDEX IF NOT EXISTS idx_contract_ourco     ON contract(our_company_id);

-- ---------- MySQL 执行（去掉上方 SQLite 语句，改用下方）----------
-- ALTER TABLE contract ADD INDEX idx_contract_creator   (creator_id);
-- ALTER TABLE contract ADD INDEX idx_contract_party_b  (party_b_customer_id);
-- ALTER TABLE contract ADD INDEX idx_contract_parent    (parent_id);
-- ALTER TABLE contract ADD INDEX idx_contract_category  (category);
-- ALTER TABLE contract ADD INDEX idx_contract_ourco     (our_company_id);
