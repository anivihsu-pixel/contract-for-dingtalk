<?php
// +----------------------------------------------------------------------
// | 钉钉 Mock 模式 — 无钉钉环境时的本地模拟
// +----------------------------------------------------------------------

namespace app\common\service\dd;

class DingTalkMock
{
    protected static array $logs = [];

    public function getUserIdByAuthCode(string $code): array
    {
        self::log('getUserIdByAuthCode', ['code' => $code]);
        return [
            'errcode' => 0,
            'errmsg'  => 'ok',
            'userid'  => 'mock_user_' . substr($code, 0, 8),
            'unionid' => 'mock_union_' . substr($code, 0, 8),
            'name'    => 'Mock 钉钉用户',
        ];
    }

    public function getUserDetail(string $userId): array
    {
        self::log('getUserDetail', ['userid' => $userId]);
        return [
            'errcode' => 0,
            'userid'  => $userId,
            'name'    => 'Mock 用户',
            'mobile'  => '13800138000',
            'email'   => 'mock@company.com',
            'avatar'  => '',
        ];
    }

    public function getDepartmentList(?int $parentId = null): array
    {
        self::log('getDepartmentList', ['parent_id' => $parentId]);
        return ['errcode' => 0, 'department' => [
            ['id' => 1, 'name' => 'Mock 公司', 'parentid' => 0],
            ['id' => 2, 'name' => '技术部', 'parentid' => 1],
            ['id' => 3, 'name' => '销售部', 'parentid' => 1],
            ['id' => 4, 'name' => '财务部', 'parentid' => 1],
        ]];
    }

    public function getDepartmentUsers(int $deptId): array
    {
        self::log('getDepartmentUsers', ['dept_id' => $deptId]);
        return ['errcode' => 0, 'userlist' => [
            ['userid' => 'mock_user_001', 'name' => '张三', 'unionid' => 'union_001'],
            ['userid' => 'mock_user_002', 'name' => '李四', 'unionid' => 'union_002'],
            ['userid' => 'mock_user_003', 'name' => '王五', 'unionid' => 'union_003'],
        ]];
    }

    public function sendWorkNotice(array $userIds, string $title, string $markdown, ?string $url = null, string $type = ''): array
    {
        $payload = ['user_ids' => $userIds, 'title' => $title, 'content' => $markdown, 'type' => $type];
        if ($url !== null) {
            $payload['url'] = $url;
        }
        self::log('sendWorkNotice', $payload);
        return ['errcode' => 0, 'task_id' => time()];
    }

    /** 获取调用日志（含跨请求持久化记录） */
    public static function getLogs(): array
    {
        $file = self::logFile();
        if (is_file($file)) {
            $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $fromFile = array_reverse(array_map(function ($l) {
                return json_decode($l, true) ?: [];
            }, $lines));
            return array_merge(self::$logs, $fromFile);
        }
        return self::$logs;
    }

    /** mock 日志持久化文件（跨请求，便于演示/排查实际会发出的通知） */
    protected static function logFile(): string
    {
        return sys_get_temp_dir() . '/dingtalk_mock.log';
    }

    protected static function log(string $method, array $params): void
    {
        self::$logs[] = [
            'method'    => $method,
            'params'    => $params,
            'timestamp' => date('Y-m-d H:i:s'),
        ];
        // 持久化到文件，避免每次 HTTP 请求独立进程导致日志丢失
        $line = json_encode([
            'method'    => $method,
            'params'    => $params,
            'timestamp' => date('Y-m-d H:i:s'),
        ], JSON_UNESCAPED_UNICODE);
        @file_put_contents(self::logFile(), $line . "\n", FILE_APPEND);
    }
}
