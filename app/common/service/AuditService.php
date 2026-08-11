<?php
// +----------------------------------------------------------------------
// | 操作审计服务
// +----------------------------------------------------------------------

namespace app\common\service;

use think\facade\Db;
use think\facade\Log;

class AuditService
{
    /**
     * 记录操作日志
     * @param int    $userId     操作人 ID
     * @param string $action     操作类型 (create/update/delete/approve 等)
     * @param string $targetType 目标类型 (contract/customer/user 等)
     * @param int    $targetId   目标 ID
     * @param mixed  $content    操作详情 (数组自动转 JSON)
     */
    public static function log(int $userId, string $action, string $targetType, int $targetId, $content = ''): void
    {
        try {
            Db::name('audit_log')->insert([
                'user_id'     => $userId,
                'action'      => $action,
                'target_type' => $targetType,
                'target_id'   => $targetId,
                'content'     => is_array($content) ? json_encode($content, JSON_UNESCAPED_UNICODE) : (string)$content,
                'ip_address'  => request()->ip() ?: '',
                'user_agent'  => request()->header('User-Agent', ''),
                'created_at'  => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            // 审计日志写入失败不阻塞业务，但必须记录以便日志中心察觉（CR-32）
            Log::error('审计日志写入失败', [
                'user_id'     => $userId,
                'action'      => $action,
                'target_type' => $targetType,
                'target_id'   => $targetId,
                'error'       => $e->getMessage(),
            ]);
        }
    }

    /** 查询审计日志 */
    public static function getList(int $page, int $pageSize, array $filter = [], array $sort = ['a.id', 'desc']): array
    {
        $query = Db::name('audit_log')->alias('a')
            ->leftJoin('user u', 'a.user_id = u.id')
            ->field('a.*, u.name as user_name');

        if (!empty($filter['user_id'])) {
            $query->where('a.user_id', $filter['user_id']);
        }
        if (!empty($filter['action'])) {
            $query->where('a.action', $filter['action']);
        }
        if (!empty($filter['target_type'])) {
            $query->where('a.target_type', $filter['target_type']);
        }
        if (!empty($filter['date_start'])) {
            $query->where('a.created_at', '>=', $filter['date_start']);
        }

        $total = $query->count();
        $list  = $query->order($sort[0], $sort[1])->page($page, $pageSize)->select()->toArray();

        return ['list' => $list, 'total' => $total];
    }
}
