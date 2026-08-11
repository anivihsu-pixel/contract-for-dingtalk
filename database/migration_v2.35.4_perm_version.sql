-- 权限版本号字段（v2.35.4 / RV-01）
-- 用途：角色 / 权限变更后自增 perm_version，使已登录会话在下次请求时自动刷新权限，无需重新登录。
-- 适用：生产 MySQL（本地 SQLite 由 init_sqlite.php 在建表时直接包含该列）。
-- 注意事项：
--   1) 该列为新增列，默认值 0，对现有数据无影响；
--   2) 执行前请务必备份数据库；
--   3) 本脚本为【幂等】写法：通过 information_schema 检查列是否已存在，已存在则跳过 ALTER，可重复执行不报"重复列"错误；
--   4) ⚠️ 重要：MySQL 原生【不支持】 ALTER TABLE ... ADD COLUMN IF NOT EXISTS 语法（该语法为 MariaDB 扩展，
--      在 MySQL 8.0 上会报 1064 语法错误），故此处采用 information_schema 判重 + 动态 SQL 实现等价幂等，兼容 MySQL 8.0；
--   5) 执行时需 USE 目标库（如 `mysql -h<H> -u<U> -p <DB> < 本文件`），DATABASE() 自动取当前库名；
--   6) user 表数据量大时 ALTER 锁表时间需评估，建议低峰期执行；首次执行才会触发 ALTER，重复执行仅查询后跳过；
--   7) 字段已同步加入 init.sql / init_mysql.php / init_sqlite.php 三份种子脚本（1:1 对照）。

SET @db = DATABASE();
SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME  = 'user'
    AND COLUMN_NAME = 'perm_version'
);
SET @sql = IF(
  @col_exists = 0,
  'ALTER TABLE `user` ADD COLUMN `perm_version` INT NOT NULL DEFAULT 0 COMMENT ''权限版本号(角色/权限变更自增,用于失效已登录会话缓存)'',  -- 权限版本号(角色/权限变更自增，用于失效已登录会话缓存)
  'SELECT ''perm_version 列已存在，跳过 ALTER'' AS result'
);
PREPARE _pv_stmt FROM @sql;
EXECUTE _pv_stmt;
DEALLOCATE PREPARE _pv_stmt;
