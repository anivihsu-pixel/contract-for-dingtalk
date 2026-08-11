-- ============================================================
-- 公司主体(company_profile)字段精简迁移
-- 背景：v2.35.9 起 company_profile 仅保留 全称(name)/简称(short_name)/代码(unified_social_credit_code)
--       外加功能开关 is_default（默认主体，支撑新建合同自动带出与「本公司」快捷按钮）；
--       删除冗余的开票/账户类字段（税号/开户行/账号/地址/电话/法定代表人），
--       这些信息已由「资料库 → 开票资料」承载，避免重复维护。
-- 幂等：MySQL 8.0 不支持 DROP COLUMN IF EXISTS，故逐列用 information_schema 判重，列存在才动态 ALTER。
-- 用法：mysql -h<H> -u<U> -p <DB> < database/migration_v2.35.9_company_profile_trim.sql
--       （须先 USE 目标库，或如上通过 `-p <DB>` 指定库名；脚本内取 DATABASE() 自动定位。）
-- 注意：SQLite 演示库用 init_sqlite.php 已直接建精简表；本地已有演示库可用如下语句同步：
--       ALTER TABLE company_profile DROP COLUMN tax_no;   （SQLite 3.35+ 支持，逐列执行）
-- ============================================================

SET @db = DATABASE();

-- tax_no
SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='company_profile' AND COLUMN_NAME='tax_no');
SET @sql = IF(@c>0, 'ALTER TABLE `company_profile` DROP COLUMN `tax_no`', 'DO 1');
PREPARE _s FROM @sql; EXECUTE _s; DEALLOCATE PREPARE _s;

-- bank_name
SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='company_profile' AND COLUMN_NAME='bank_name');
SET @sql = IF(@c>0, 'ALTER TABLE `company_profile` DROP COLUMN `bank_name`', 'DO 1');
PREPARE _s FROM @sql; EXECUTE _s; DEALLOCATE PREPARE _s;

-- bank_account
SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='company_profile' AND COLUMN_NAME='bank_account');
SET @sql = IF(@c>0, 'ALTER TABLE `company_profile` DROP COLUMN `bank_account`', 'DO 1');
PREPARE _s FROM @sql; EXECUTE _s; DEALLOCATE PREPARE _s;

-- address
SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='company_profile' AND COLUMN_NAME='address');
SET @sql = IF(@c>0, 'ALTER TABLE `company_profile` DROP COLUMN `address`', 'DO 1');
PREPARE _s FROM @sql; EXECUTE _s; DEALLOCATE PREPARE _s;

-- tel
SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='company_profile' AND COLUMN_NAME='tel');
SET @sql = IF(@c>0, 'ALTER TABLE `company_profile` DROP COLUMN `tel`', 'DO 1');
PREPARE _s FROM @sql; EXECUTE _s; DEALLOCATE PREPARE _s;

-- legal_rep
SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='company_profile' AND COLUMN_NAME='legal_rep');
SET @sql = IF(@c>0, 'ALTER TABLE `company_profile` DROP COLUMN `legal_rep`', 'DO 1');
PREPARE _s FROM @sql; EXECUTE _s; DEALLOCATE PREPARE _s;

SELECT 'company_profile 字段精简完成（税号/开户行/账号/地址/电话/法定代表人 已删除或本就不存在）' AS result;
