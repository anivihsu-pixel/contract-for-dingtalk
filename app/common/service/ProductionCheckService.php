<?php

namespace app\common\service;

use think\facade\Db;

/** 生产环境自检：检查结果不得包含密码、密钥等敏感值。 */
class ProductionCheckService
{
    public static function run(bool $includeProductionRules = true): array
    {
        $checks = [];
        $add = static function (string $name, bool $ok, string $message) use (&$checks): void {
            $checks[] = ['name' => $name, 'ok' => $ok, 'message' => $message];
        };

        try {
            Db::query('SELECT 1');
            $add('database', true, '数据库连接正常');
        } catch (\Throwable $e) {
            $add('database', false, '数据库连接失败');
        }

        try {
            // 防止“数据库能连接但迁移漏执行”的版本切流事故。
            Db::query('SELECT id, status FROM contract LIMIT 0');
            Db::query('SELECT id, biz_type, target_id FROM approval_instance LIMIT 0');
            Db::query('SELECT id FROM contract_execution_cc LIMIT 0');
            Db::query('SELECT id FROM payment_collection_follow LIMIT 0');
            Db::query('SELECT business_type FROM contract LIMIT 0');
            Db::query('SELECT business_type FROM project LIMIT 0');
            $add('database_schema', true, '核心表结构与当前版本一致');
        } catch (\Throwable $e) {
            $add('database_schema', false, '核心表结构缺失或迁移未完成');
        }

        $runtime = function_exists('runtime_path')
            ? runtime_path()
            : dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR;
        $add('runtime', is_dir($runtime) && is_writable($runtime), '运行目录必须存在且可写');

        if ($includeProductionRules) {
            $dbType = (string)config('database.default');
            $add('database_type', $dbType === 'mysql', '生产环境必须使用 MySQL');
            $add('app_debug', !filter_var(config('app.app_debug', false), FILTER_VALIDATE_BOOLEAN), '生产环境必须关闭调试模式');
            $add('cookie_secure', filter_var(env('COOKIE_SECURE', false), FILTER_VALIDATE_BOOLEAN), 'HTTPS 生产环境必须启用安全 Cookie');
            $add('dingtalk_mock', !DingTalkService::isMock(), '生产环境必须关闭钉钉模拟模式');

            foreach (['APP_KEY', 'JWT_SECRET'] as $key) {
                $value = (string)env($key, '');
                $valid = strlen($value) >= 32 && stripos($value, 'please_change_me') === false;
                $add(strtolower($key), $valid, $key . ' 必须是至少 32 位的独立随机值');
            }
            foreach (['DINGTALK_APP_KEY', 'DINGTALK_APP_SECRET', 'DINGTALK_CORP_ID', 'DINGTALK_AGENT_ID'] as $key) {
                $add(strtolower($key), trim((string)env($key, '')) !== '', $key . ' 必须配置');
            }
            $appUrl = (string)env('DINGTALK_APP_URL', '');
            $add('dingtalk_app_url', str_starts_with(strtolower($appUrl), 'https://'), '钉钉应用地址必须使用 HTTPS');

            $backupFiles = glob(rtrim($runtime, '/\\') . DIRECTORY_SEPARATOR . 'backup' . DIRECTORY_SEPARATOR . 'contract_*');
            $latestBackup = $backupFiles ? max(array_map('filemtime', $backupFiles)) : 0;
            $add('database_backup', $latestBackup > 0 && $latestBackup >= time() - 36 * 3600, '必须存在 36 小时内的数据库备份');
        }

        $failed = count(array_filter($checks, static fn(array $check): bool => !$check['ok']));
        return ['ok' => $failed === 0, 'failed' => $failed, 'checks' => $checks];
    }
}
