<?php
namespace app\common\service;

use think\facade\Db;
use think\facade\Cache;
use think\facade\Log;

class RemindService
{
    /**
     * 提醒触发引擎（写入型）：扫描合同到期、回款到期/逾期及客户计划跟进，
     * 经 shouldRemind() 以「非 push_ 前缀」的 remind_type 原子写入 remind_log 全局去重，
     * 同时把本次触发的提醒文本累积进 $alerts（引用传参），并返回 {contracts,payments} 计数。
     *
     * 调用方：
     *  - DashboardController::index()：渲染驾驶舱时触发，填充「今日提醒」卡；
     *  - RemindController::index()：提醒页触发，确保提醒状态被「访问即确认」。
     *
     * 职责边界（与另两个方法分离，避免重复提醒）：
     *  - getTodayAlerts()/scan()：纯读、不写日志，供提醒角标与提醒页列表展示（当前用户视角）；
     *  - dispatch()：钉钉主动推送，用 push_ 前缀 remind_type 去重，供 CLI 与「立即推送」共用；
     *  - 本方法 check()：写库触发（非 push_ 前缀），仅用于「访问即确认」的站内提醒计数。
     */
    public static function check(array &$alerts = []): array
    {
        // S-03：引擎写入 60s 全局节流——check() 由页面/多用户触发且会批量写 remind_log，
        // 高频调用（刷接口）会造成 DB 写放大与锁竞争；同一时间窗内只真正执行一次引擎扫描。
        $throttleKey = 'remind_check_throttle';
        if (\think\facade\Cache::get($throttleKey)) {
            return ['contracts' => 0, 'payments' => 0, 'followups' => 0, 'throttled' => true];
        }
        \think\facade\Cache::set($throttleKey, 1, 60);

        $today = date('Y-m-d');
        $results = ['contracts' => 0, 'payments' => 0, 'followups' => 0];

        // 2026-08-01：提醒提前天数由 PC 后台「系统设置→系统配置→业务规则」配置（逗号分隔），
        // 缺省保持原 30/15/7/3/1 与 7/3/1。非法输入（空/非数字）自动过滤，避免异常。
        // P1-6（deep review）：统一走 remindDays() 单源读取，check/dispatch/scanAlerts 三处口径一致。
        $expireDays = self::remindDays('rule_expire_remind_days', [30, 15, 7, 3, 1]);
        $payDays    = self::remindDays('rule_payment_remind_days', [7, 3, 1]);

        // 1. Contracts expiring in N days (configurable via rule_expire_remind_days)
        foreach (($expireDays ?: [30, 15, 7, 3, 1]) as $days) {
            $date = date('Y-m-d', strtotime("+{$days} days"));
            $contracts = Db::name('contract')
                ->where('is_deleted', 0)
                ->where('status', 'EXECUTING')
                ->where('expiry_date', $date)
                ->select()->toArray();

            foreach ($contracts as $c) {
                if (self::shouldRemind('contract', $c['id'], "expiry_{$days}d", $today)) {
                    $alerts[] = "合同《{$c['title']}》将在 {$days} 天后到期";
                    $results['contracts']++;
                }
            }
        }

        // 2. Contracts already expired but status not updated
        $expired = Db::name('contract')
            ->where('is_deleted', 0)
            ->where('status', 'EXECUTING')
            ->where('expiry_date', '<', $today)
            ->select()->toArray();
        foreach ($expired as $c) {
            if (self::shouldRemind('contract', $c['id'], 'expired', $today)) {
                $alerts[] = "合同《{$c['title']}》已到期，请处理";
                $results['contracts']++;
            }
        }

        // 3. Overdue payments (past planned_date, still uncollected: PENDING/OVERDUE; 仅应收 RECEIVABLE，应付不进回款提醒)
        $overdue = Db::name('payment_record')->alias('p')
            ->join('contract c', 'p.contract_id = c.id')
            ->field('p.*, c.contract_no, c.title')
            ->where('p.status', 'in', ['PENDING', 'OVERDUE'])
            ->where('p.payment_type', 'RECEIVABLE')
            ->where('p.planned_date', '<', $today)
            ->where('c.is_deleted', 0)
            ->select()->toArray();
        foreach ($overdue as $p) {
            if (self::shouldRemind('payment', $p['id'], 'overdue', $today)) {
                $alerts[] = "回款逾期：{$p['title']} ¥{$p['amount']}（{$p['description']}）";
                $results['payments']++;
            }
        }

        // 4. Payments due in N days (configurable via rule_payment_remind_days)
        foreach (($payDays ?: [7, 3, 1]) as $days) {
            $date = date('Y-m-d', strtotime("+{$days} days"));
            $due = Db::name('payment_record')->alias('p')
                ->join('contract c', 'p.contract_id = c.id')
                ->field('p.*, c.contract_no, c.title')
                ->where('p.status', 'PENDING')
                ->where('p.payment_type', 'RECEIVABLE')
                ->where('p.planned_date', $date)
                ->where('c.is_deleted', 0)
                ->select()->toArray();
            foreach ($due as $p) {
                if (self::shouldRemind('payment', $p['id'], "due_{$days}d", $today)) {
                    $alerts[] = "回款提醒：{$p['title']} ¥{$p['amount']} 将于 {$days} 天后到期";
                    $results['payments']++;
                }
            }
        }

        // 5. 已到跟进时间的客户（仅每个客户最新一条跟进计划有效；新跟进会自然关闭旧计划）
        foreach (self::dueCustomerFollowups() as $follow) {
            if (self::shouldRemind('customer_activity', (int)$follow['id'], 'follow_due', $today)) {
                $alerts[] = "客户跟进：{$follow['customer_name']} 已到计划跟进时间";
                $results['followups']++;
            }
        }

        return $results;
    }

    /**
     * 每日提醒调度：扫描到期/逾期项，按合同负责人 + 财务分组，通过钉钉工作通知主动推送。
     * - 复用 remind_log 去重（remind_type 前缀 push_，与仪表盘 check() 独立），保证同一项每天只推一次。
     * - 供 CLI 命令 `php think remind:dispatch`（crontab 每日调用）与管理员「立即触发推送」共用。
     * @return array ['contracts'=>int,'payments'=>int,'followups'=>int,'notified'=>int]
     */
    public static function dispatch(): array
    {
        $today   = date('Y-m-d');
        $results = ['contracts' => 0, 'payments' => 0, 'followups' => 0, 'notified' => 0, 'failed' => 0];

        // 财务人员（按 role.code='finance' 关联查询，避免硬编码 role_id，CR-25）
        $financeRoleId = Db::name('role')->where('code', 'finance')->value('id');
        $financeIds    = $financeRoleId ? Db::name('user_role')->where('role_id', $financeRoleId)->column('user_id') : [];
        // 管理员接收人（is_admin=1 ∪ admin 角色，钉钉部署 is_admin=0 同效；仅在职）
        $adminIds      = \app\common\logic\AuthLogic::getAdminUserIds(true);

        // 在职用户集合（status=1）：方案D——推送前统一过滤禁用/离职用户，
        // 避免离职创建人/负责人/财务持续收到提醒并产生无效推送失败告警
        $activeIds = array_map('intval', Db::name('user')->where('status', 1)->column('id'));
        $activeSet = array_flip($activeIds);

        // 收集每个用户待推送的文本行
        $byUser = [];
        $enqueue = function (array $userIds, string $line) use (&$byUser, $activeSet) {
            foreach (array_unique(array_filter(array_map('intval', $userIds))) as $uid) {
                if ($uid > 0 && isset($activeSet[$uid])) $byUser[$uid][] = $line;
            }
        };

        // 各业务扫描拆分到独立方法，主方法聚焦编排（推送去重 + 钉钉分发）
        // v2.51.11：pmtCache 缓存「流程→回款通知人」解析结果（同一流程多笔回款只解析一次）
        $pmtCache = [];
        self::scanContractExpiry($enqueue, $results, $today);
        self::scanExpiredContracts($enqueue, $results, $adminIds, $today);
        self::scanOverduePayments($enqueue, $results, $financeIds, $today, $pmtCache);
        self::scanUpcomingPayments($enqueue, $results, $financeIds, $today, $pmtCache);
        self::scanDueCustomerFollowups($enqueue, $results, $today);

        // 分用户推送钉钉工作通知；失败重试一次并告警（CR-11）
        self::pushToUsers($byUser, $results, $today);

        return $results;
    }

    /**
     * 扫描即将到期合同（P3b-10 从 dispatch 拆分）
     * 天数读 sys_config rule_expire_remind_days，P1-6 统一口径；提醒归属人 + 创建人。
     */
    private static function scanContractExpiry(callable $enqueue, array &$results, string $today): void
    {
        $expireDays = self::remindDays('rule_expire_remind_days', [30, 15, 7, 3, 1]);
        foreach ($expireDays as $days) {
            $date = date('Y-m-d', strtotime("+{$days} days"));
            $rows = Db::name('contract')->where('is_deleted', 0)
                ->where('status', 'EXECUTING')->where('expiry_date', $date)
                ->select()->toArray();
            foreach ($rows as $c) {
                if (self::shouldRemind('contract', $c['id'], "push_expiry_{$days}d", $today)) {
                    $enqueue([$c['owner_id'], $c['creator_id']],
                        "⏰ 合同 {$c['contract_no']}《{$c['title']}》将在 {$days} 天后到期");
                    $results['contracts']++;
                }
            }
        }
    }

    /**
     * 扫描已到期未处理合同（P3b-10 从 dispatch 拆分）
     * 状态仍为 EXECUTING 但 expiry_date < today，提醒归属人 + 创建人，并抄送管理员。
     */
    private static function scanExpiredContracts(callable $enqueue, array &$results, array $adminIds, string $today): void
    {
        $rows = Db::name('contract')->where('is_deleted', 0)
            ->where('status', 'EXECUTING')->where('expiry_date', '<', $today)
            ->select()->toArray();
        foreach ($rows as $c) {
            if (self::shouldRemind('contract', $c['id'], 'push_expired', $today)) {
                $enqueue(array_merge([$c['owner_id'], $c['creator_id']], $adminIds),
                    "❗ 合同 {$c['contract_no']}《{$c['title']}》已到期，请尽快处理");
                $results['contracts']++;
            }
        }
    }

    /**
     * 扫描逾期回款（P3b-10 从 dispatch 拆分）
     * status ∈ {PENDING, OVERDUE} 且 planned_date < today，仅应收（RECEIVABLE），提醒归属人 + 创建人，
     * 并抄送流程配置的「回款通知人」（未配置回退财务角色，v2.51.11）。
     */
    private static function scanOverduePayments(callable $enqueue, array &$results, array $financeIds, string $today, array &$pmtCache): void
    {
        $rows = Db::name('payment_record')->alias('p')->join('contract c', 'p.contract_id = c.id')
            ->field('p.*, c.contract_no, c.title, c.owner_id, c.creator_id, c.flow_id')
            ->where('p.status', 'in', ['PENDING', 'OVERDUE'])
            ->where('p.payment_type', 'RECEIVABLE')
            ->where('p.planned_date', '<', $today)->where('c.is_deleted', 0)
            ->select()->toArray();
        foreach ($rows as $p) {
            if (self::shouldRemind('payment', $p['id'], 'push_overdue', $today)) {
                $fid    = (int)($p['flow_id'] ?? 0);
                $notify = $pmtCache[$fid] ?? ($pmtCache[$fid] = self::resolvePaymentNotify($fid, $financeIds));
                $enqueue(array_merge([$p['owner_id'], $p['creator_id']], $notify),
                    "💰 回款逾期：{$p['contract_no']}《{$p['title']}》 ¥" . number_format((float)$p['amount']) . "（{$p['description']}）");
                $results['payments']++;
            }
        }
    }

    /**
     * 扫描即将到期回款（P3b-10 从 dispatch 拆分）
     * 天数读 sys_config rule_payment_remind_days，P1-6 统一口径；status=PENDING、仅应收（RECEIVABLE）
     * 且 planned_date 命中 N 天后日期；抄送流程配置的「回款通知人」（未配置回退财务角色，v2.51.11）。
     */
    private static function scanUpcomingPayments(callable $enqueue, array &$results, array $financeIds, string $today, array &$pmtCache): void
    {
        $payDays = self::remindDays('rule_payment_remind_days', [7, 3, 1]);
        foreach ($payDays as $days) {
            $date = date('Y-m-d', strtotime("+{$days} days"));
            $rows = Db::name('payment_record')->alias('p')->join('contract c', 'p.contract_id = c.id')
                ->field('p.*, c.contract_no, c.title, c.owner_id, c.creator_id, c.flow_id')
                ->where('p.status', 'PENDING')->where('p.payment_type', 'RECEIVABLE')
                ->where('p.planned_date', $date)->where('c.is_deleted', 0)
                ->select()->toArray();
            foreach ($rows as $p) {
                if (self::shouldRemind('payment', $p['id'], "push_due_{$days}d", $today)) {
                    $fid    = (int)($p['flow_id'] ?? 0);
                    $notify = $pmtCache[$fid] ?? ($pmtCache[$fid] = self::resolvePaymentNotify($fid, $financeIds));
                    $enqueue(array_merge([$p['owner_id'], $p['creator_id']], $notify),
                        "📅 回款提醒：{$p['contract_no']}《{$p['title']}》 ¥" . number_format((float)$p['amount']) . " 将于 {$days} 天后到期");
                    $results['payments']++;
                }
            }
        }
    }

    /**
     * 解析流程级「回款提醒通知人」（v2.51.11）：读 approval_flow.payment_notify（{role_codes:[],user_ids:[]}），
     * 角色码展开为该角色成员，指定用户直接采用；配置为空（或流程不存在）时回退默认收件人（财务角色成员）。
     * @return int[] 用户 id 列表（正数、去重）
     */
    private static function resolvePaymentNotify(int $flowId, array $fallback): array
    {
        $notify = [];
        if ($flowId > 0) {
            $raw = Db::name('approval_flow')->where('id', $flowId)->value('payment_notify');
            if ($raw) {
                $parsed = json_decode((string)$raw, true);
                if (is_array($parsed)) $notify = $parsed;
            }
        }
        $ids = array_map('intval', $notify['user_ids'] ?? []);
        foreach (($notify['role_codes'] ?? []) as $code) {
            $code = trim((string)$code);
            if ($code === '') continue;
            $rid = Db::name('role')->where('code', $code)->value('id');
            if ($rid) {
                $ids = array_merge($ids, Db::name('user_role')->where('role_id', (int)$rid)->column('user_id'));
            }
        }
        $ids = array_values(array_unique(array_filter($ids, fn($v) => $v > 0)));
        if (!empty($ids)) return $ids;
        // 默认兜底：财务角色成员
        return array_values(array_unique(array_filter(array_map('intval', $fallback), fn($v) => $v > 0)));
    }

    /**
     * 扫描已到期的客户跟进计划。只有客户最新一条活动中的计划有效，接收人为当前客户负责人；
     * 新增跟进或发生认领、转移、释放后旧计划自动关闭，避免把历史承诺错误推给新负责人。
     */
    private static function scanDueCustomerFollowups(callable $enqueue, array &$results, string $today): void
    {
        foreach (self::dueCustomerFollowups() as $follow) {
            if (self::shouldRemind('customer_activity', (int)$follow['id'], 'push_follow_due', $today)) {
                $time = date('m-d H:i', strtotime((string)$follow['next_follow_at']));
                $enqueue([(int)$follow['owner_id']],
                    "📞 客户跟进：{$follow['customer_name']}（计划 {$time}）已到时间，请及时联系");
                $results['followups']++;
            }
        }
    }

    /** 查询每个有效客户最新且已到期的跟进计划，供站内提醒、检查和钉钉推送共用。 */
    private static function dueCustomerFollowups(?int $ownerId = null, int $limit = 200): array
    {
        $q = Db::name('customer_activity')->alias('a')
            ->join('customer c', 'a.customer_id = c.id')
            ->field('a.id,a.customer_id,a.next_follow_at,c.name AS customer_name,c.owner_id')
            ->whereRaw('a.id = (SELECT MAX(latest.id) FROM customer_activity latest WHERE latest.customer_id = a.customer_id)')
            ->whereNotNull('a.next_follow_at')
            ->where('a.next_follow_at', '<=', date('Y-m-d H:i:s'))
            ->where('c.is_deleted', 0)
            ->where('c.owner_id', '>', 0)
            ->order('a.next_follow_at', 'asc')
            ->limit($limit);
        if ($ownerId !== null) {
            if ($ownerId <= 0) return [];
            $q->where('c.owner_id', $ownerId);
        }
        return $q->select()->toArray();
    }

    /**
     * 按用户分组推送钉钉工作通知（P3b-10 从 dispatch 拆分）
     * 单次失败重试一次（CR-11 钉钉偶发网络抖动），仍失败计入 failed 并 Log::warning 告警。
     */
    private static function pushToUsers(array $byUser, array &$results, string $today): void
    {
        foreach ($byUser as $uid => $lines) {
            $title = "合同待办提醒（{$today}）";
            $md    = "### 📋 合同待办提醒（{$today}）\n\n- " . implode("\n- ", $lines)
                . "\n\n> 请及时登录合同系统处理相关事项。";

            $resp = DingTalkService::sendToLocalUsers([$uid], $title, $md);
            // 失败则重试一次（钉钉偶发网络抖动），仍失败计入 failed 并告警
            if (($resp['errcode'] ?? -1) != 0) {
                $resp = DingTalkService::sendToLocalUsers([$uid], $title, $md);
            }
            if (($resp['errcode'] ?? -1) == 0) {
                $results['notified']++;
            } else {
                $results['failed']++;
                Log::warning('钉钉提醒推送失败（已重试一次）', [
                    'user_id' => $uid,
                    'resp'    => $resp,
                ]);
            }
        }
    }

    /**
     * 提醒提前天数单源读取（P1-6）：从 sys_config 读逗号分隔天数列表，非法值过滤；
     * check()/dispatch()/scanAlerts() 三处共用，保证「配置即生效」无硬编码漂移。
     * @param string $key 配置键（rule_expire_remind_days / rule_payment_remind_days）
     * @param array $default 缺省天数
     * @return array<int>
     */
    private static function remindDays(string $key, array $default): array
    {
        $days = array_values(array_filter(
            array_map('intval', explode(',', sys_config($key, implode(',', $default)))),
            fn($d) => $d > 0
        ));
        return $days ?: $default;
    }

    private static function shouldRemind(string $type, int $id, string $remindType, string $date): bool
    {
        // 原子去重写入：依赖 remind_log 唯一索引 uk_remind_dedup。
        // 并发请求下多个进程同时 INSERT 仅一个成功（返回1=需提醒），
        // 其余因唯一约束被 IGNORE（返回0=已提醒过），彻底消除 check-then-act 竞态与重复记录。
        // 修复(2026-08-01)：think-orm v1.x 的 Query 无 insertOrIgnore() 方法，原写法每次抛
        //   BadMethodCallException 并被下方 catch 吞掉 → 返回 false → 提醒引擎从未真正落库
        //   （remind_log 仅剩种子数据）。改用原生 INSERT [OR] IGNORE，SQLite/MySQL 双兼容且保持原子去重。
        try {
            $dbType = strtolower((string)Db::getConfig('default', 'mysql')); // 顶级 default = 连接名 = 驱动名（sqlite/mysql）
            $sql = $dbType === 'sqlite'
                ? 'INSERT OR IGNORE INTO remind_log (target_type, target_id, remind_type, remind_at, sent, created_at) VALUES (?, ?, ?, ?, 1, ?)'
                : 'INSERT IGNORE INTO `remind_log` (`target_type`, `target_id`, `remind_type`, `remind_at`, `sent`, `created_at`) VALUES (?, ?, ?, ?, 1, ?)';
            $n = Db::execute($sql, [$type, $id, $remindType, $date, date('Y-m-d H:i:s')]);
            return $n > 0;
        } catch (\Throwable $e) {
            // 极端并发下若仍抛异常，保守返回 false（不重复提醒），避免冒泡成 500
            return false;
        }
    }

    /**
     * 实时返回当前用户视角下的今日待提醒项（不写日志），用于提醒页/仪表盘/导航角标。
     * 非管理员仅看自己作为归属人/创建人的合同相关提醒（数据范围收敛）。
     * P2-12：扫描结果加 60s 短缓存（按用户隔离，避免串数据）；
     * 「已到期/逾期」类随时间变化的提醒用较短 TTL 即可，且不影响 check/dispatch 写库路径。
     */
    public static function getTodayAlerts(int $userId = 0, bool $isAdmin = false, bool $hasFinancePerm = false): array
    {
        $key = 'remind_scan_' . $userId;
        return Cache::remember($key, function () use ($userId, $isAdmin, $hasFinancePerm) {
            return self::scanAlerts($userId, $isAdmin, $hasFinancePerm);
        }, 60);
    }

    public static function getOutstandingCount(int $userId = 0, bool $isAdmin = false, bool $hasFinancePerm = false): int
    {
        return count(self::getTodayAlerts($userId, $isAdmin, $hasFinancePerm));
    }

    private static function scopeOwner($q, int $userId, bool $isAdmin, bool $isPaymentQuery = false, bool $hasFinancePerm = false): void
    {
        if ($isAdmin) return;
        // v2.38.2：财务人员（拥有 payment:view 权限）可看到全量回款逾期，不受合同归属限制
        if ($isPaymentQuery && $hasFinancePerm) return;
        if ($userId > 0) {
            $q->where('owner_id|creator_id', $userId);
        }
    }

    /** 扫描当前需要提醒的项（合同、回款、客户跟进），仅读取不写日志，返回提醒数组 */
    private static function scanAlerts(int $userId = 0, bool $isAdmin = false, bool $hasFinancePerm = false): array
    {
        $alerts = [];
        $today = date('Y-m-d');

        // 1. 到期预警（天数读 sys_config rule_expire_remind_days，P1-6 统一口径）
        $expireDays = self::remindDays('rule_expire_remind_days', [30, 15, 7, 3, 1]);
        foreach ($expireDays as $days) {
            $date = date('Y-m-d', strtotime("+{$days} days"));
            $q = Db::name('contract')->where('is_deleted', 0)
                ->where('status', 'EXECUTING')->where('expiry_date', $date);
            self::scopeOwner($q, $userId, $isAdmin, false, $hasFinancePerm);
            // P2-2（arch）：每类扫描加 limit 上限，防管理员全公司提醒单次全量载入
            foreach ($q->limit(100)->select()->toArray() as $c) {
                $alerts[] = ['type' => 'contract', 'id' => $c['id'],
                    'level' => $days <= 7 ? 'warning' : 'info',
                    'text' => "合同《{$c['title']}》将在 {$days} 天后到期"];
            }
        }

        // 2. 已到期未处理
        $q = Db::name('contract')->where('is_deleted', 0)
            ->where('status', 'EXECUTING')->where('expiry_date', '<', $today);
        self::scopeOwner($q, $userId, $isAdmin, false, $hasFinancePerm);
        foreach ($q->limit(100)->select()->toArray() as $c) {
            $alerts[] = ['type' => 'contract', 'id' => $c['id'], 'level' => 'danger',
                'text' => "合同《{$c['title']}》已到期，请处理"];
        }

        // 3. 逾期回款（口径与 check/dispatch 统一：PENDING/OVERDUE 且已过计划日，仅应收；v2.38.2：财务人员可看全量，不受合同归属限制）
        $q = Db::name('payment_record')->alias('p')->join('contract c', 'p.contract_id = c.id')
            ->field('p.*, c.contract_no, c.title')
            ->where('p.status', 'in', ['PENDING', 'OVERDUE'])
            ->where('p.payment_type', 'RECEIVABLE')
            ->where('p.planned_date', '<', $today)
            ->where('c.is_deleted', 0);
        self::scopeOwner($q, $userId, $isAdmin, true, $hasFinancePerm);
        foreach ($q->limit(100)->select()->toArray() as $p) {
            $alerts[] = ['type' => 'payment', 'id' => $p['contract_id'], 'level' => 'danger',
                'text' => "回款逾期：{$p['title']} ¥{$p['amount']}（{$p['description']}）"];
        }

        // 4. 即将到期回款（天数读 sys_config rule_payment_remind_days，P1-6 统一口径；仅应收 RECEIVABLE）
        $payDays = self::remindDays('rule_payment_remind_days', [7, 3, 1]);
        foreach ($payDays as $days) {
            $date = date('Y-m-d', strtotime("+{$days} days"));
            $q = Db::name('payment_record')->alias('p')->join('contract c', 'p.contract_id = c.id')
                ->field('p.*, c.contract_no, c.title')->where('p.status', 'PENDING')
                ->where('p.payment_type', 'RECEIVABLE')
                ->where('p.planned_date', $date)->where('c.is_deleted', 0);
            self::scopeOwner($q, $userId, $isAdmin, true, $hasFinancePerm);
            foreach ($q->limit(100)->select()->toArray() as $p) {
                $alerts[] = ['type' => 'payment', 'id' => $p['contract_id'],
                    'level' => $days <= 3 ? 'warning' : 'info',
                    'text' => "回款提醒：{$p['title']} ¥{$p['amount']} 将于 {$days} 天后到期"];
            }
        }

        // 5. 客户跟进提醒严格按当前负责人隔离；管理员也不默认查看他人的个人跟进计划
        foreach (self::dueCustomerFollowups($userId, 100) as $follow) {
            $alerts[] = [
                'type'  => 'customer',
                'id'    => (int)$follow['customer_id'],
                'level' => strtotime((string)$follow['next_follow_at']) < strtotime($today) ? 'danger' : 'warning',
                'text'  => '客户《' . $follow['customer_name'] . '》计划跟进时间：'
                    . date('m-d H:i', strtotime((string)$follow['next_follow_at'])),
            ];
        }

        return $alerts;
    }
}
