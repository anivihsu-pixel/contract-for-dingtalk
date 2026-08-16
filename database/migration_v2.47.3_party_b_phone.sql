-- ============================================================================
-- migration_v2.47.3_party_b_phone.sql — 乙方电话独立字段
-- 背景：甲乙方联系人/电话拆分填写（对齐移动端）。甲方电话 party_a_phone 列早已存在，
--       本次仅补乙方侧 party_b_phone 列（此前联系人/电话合并存在 party_b_contact 中）。
-- 适用：MySQL 8.0+
-- 执行：mysql -h<H> -u<U> -p <DB> < database/migration_v2.47.3_party_b_phone.sql
-- 幂等：可重复执行（ADD COLUMN 若列已存在会报错，属预期可忽略）
-- 存量数据：既有合并填写的「姓名 / 电话」保留在 party_b_contact 不动，不自动拆分；
--           如需迁移可用如下 UPDATE 拆分（phone 段格式：联系人名 [ / 电话]）：
--           UPDATE contract SET
--             party_b_phone = TRIM(SUBSTRING_INDEX(party_b_contact, '/', -1)),
--             party_b_contact = TRIM(SUBSTRING_INDEX(party_b_contact, '/', 1))
--           WHERE party_b_contact LIKE '%/%';
-- 与三脚本（init_mysql.php/init_sqlite.php/init.sql）同步：均含 party_b_phone 列
-- ============================================================================

ALTER TABLE `contract` ADD COLUMN `party_b_phone` VARCHAR(32) DEFAULT '' COMMENT '乙方电话' AFTER `party_b_contact`; -- 乙方电话(v2.47.3：联系人/电话拆分填写)
