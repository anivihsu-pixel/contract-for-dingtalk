<?php
// +----------------------------------------------------------------------
// | 数据库自动备份命令 — 供 crontab 调用：php think db:backup
// | 根据当前数据库类型自动选择备份方式：
// |  - SQLite：VACUUM INTO 在线快照（3.27+），回退到文件拷贝
// |  - MySQL ：mysqldump 逻辑备份（--single-transaction，热备不锁表）
// | 统一保留最近 N 份，按类型分别清理过期备份。
// +----------------------------------------------------------------------

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\console\input\Option;

class DbBackup extends Command
{
    protected function configure()
    {
        $this->setName('db:backup')
            ->addOption('keep', 'k', Option::VALUE_OPTIONAL, '保留最近的备份份数', 14)
            ->setDescription('备份数据库（SQLite 文件 / MySQL 逻辑备份）到 runtime/backup，并保留最近 N 份');
    }

    protected function execute(Input $input, Output $output)
    {
        $keep = (int)$input->getOption('keep');
        if ($keep < 1) { $keep = 14; }

        $dbType = config('database.default');
        $backupDir = root_path() . 'runtime/backup';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $stamp  = date('Ymd_His');
        $ext    = $dbType === 'mysql' ? '.sql' : '.db';
        $target = $backupDir . '/contract_' . $stamp . $ext;

        $backedUp = $dbType === 'mysql'
            ? $this->backupMysql($output, $target)
            : $this->backupSqlite($output, $target);

        if (!$backedUp) {
            $output->writeln('<error>备份失败</error>');
            return 1;
        }

        $size = number_format(filesize($target) / 1024, 1);
        $output->writeln(sprintf('[%s] 备份完成：%s (%s KB)', date('Y-m-d H:i:s'), basename($target), $size));

        // 清理：仅保留最近 $keep 份（按数据库类型分别清理）
        $files = glob($backupDir . '/contract_*' . $ext);
        if ($files === false) { $files = []; }
        rsort($files); // 文件名含时间戳，倒序即由新到旧
        $removed = 0;
        foreach (array_slice($files, $keep) as $old) {
            if (@unlink($old)) { $removed++; }
        }
        if ($removed > 0) {
            $output->writeln(sprintf('已清理过期备份 %d 份（保留最近 %d 份）', $removed, $keep));
        }
        $output->writeln('当前备份总数：' . min(count($files), $keep) . ' 份，目录：runtime/backup');

        return 0;
    }

    /** SQLite 在线快照（回退文件拷贝） */
    protected function backupSqlite(Output $output, $target)
    {
        $dbFile = root_path() . 'runtime/data/contract.db';
        if (!is_file($dbFile)) {
            $output->writeln('<error>数据库文件不存在: ' . $dbFile . '</error>');
            return false;
        }
        try {
            $src = new \PDO('sqlite:' . $dbFile);
            // 通过 VACUUM INTO 生成一致性快照（SQLite 3.27+）
            $src->exec("VACUUM INTO '" . str_replace("'", "''", $target) . "'");
            return is_file($target);
        } catch (\Throwable $e) {
            // 回退到文件拷贝
            return @copy($dbFile, $target);
        }
    }

    /** MySQL 逻辑热备：mysqldump，密码经环境变量传递避免出现在进程列表 */
    protected function backupMysql(Output $output, $target)
    {
        $cfg = config('database.connections.mysql');
        if (empty($cfg['database'])) {
            $output->writeln('<error>未配置 MySQL 数据库连接</error>');
            return false;
        }
        $cmd = 'MYSQL_PWD=' . escapeshellarg((string)$cfg['password'])
            . ' mysqldump --single-transaction --routines --events --no-tablespaces'
            . ' -h' . escapeshellarg((string)$cfg['hostname'])
            . ' -P' . escapeshellarg((string)($cfg['port'] ?? '3306'))
            . ' -u' . escapeshellarg((string)$cfg['username'])
            . ' ' . escapeshellarg((string)$cfg['database'])
            . ' > ' . escapeshellarg($target) . ' 2>/dev/null';
        $rc = null;
        @system($cmd, $rc);
        if ($rc !== 0 || !is_file($target) || filesize($target) === 0) {
            $output->writeln('<error>MySQL 备份失败（请确认 mysqldump 已安装且凭据正确）</error>');
            return false;
        }
        return true;
    }
}
