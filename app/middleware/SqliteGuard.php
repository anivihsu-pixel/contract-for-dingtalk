<?php
// +----------------------------------------------------------------------
// | SQLite 并发加固中间件
// | 每个请求连接建立后执行：
// |  - PRAGMA busy_timeout=5000  写锁冲突时等待而非立即报 database is locked
// |  - PRAGMA journal_mode=WAL   读写并发（多读者+单写者），大幅降低锁冲突概率
// | 针对「快速连点菜单触发并发写 remind_log / session」导致页面 500 的根因加固。
// +----------------------------------------------------------------------

namespace app\middleware;

use think\facade\Db;

class SqliteGuard
{
    public function handle($request, \Closure $next)
    {
        // 仅对 SQLite 执行并发加固；切换到 MySQL 后这些 PRAGMA 无意义，直接跳过
        if (config('database.default') === 'sqlite') {
            try {
                Db::execute("PRAGMA busy_timeout=5000");
                Db::execute("PRAGMA journal_mode=WAL");
            } catch (\Throwable $e) {
                // 忽略：不支持时不影响主流程
            }
        }
        return $next($request);
    }
}
