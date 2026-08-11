<?php
// +----------------------------------------------------------------------
// | 合同聚合查询 — 只读查询（计数 / 列表 / 搜索 / 归档 / 高频词）下沉
// | 从 ContractLogic 安全拆分（P2-1，v2.35.3）；ContractLogic 保留委托桩，调用点不变
// +----------------------------------------------------------------------

namespace app\common\logic;

use think\facade\Db;

class ContractQuery
{
    /** 当前登录用户合同总数（带数据范围） */
    public static function getMyCount(): int
    {
        $q = Db::name('contract')->where('is_deleted', 0);
        AuthLogic::appendDataScope($q, 'owner_id', 'dept_id');
        return $q->count();
    }

    /** 客户关联合同列表（乙方 party_b_customer_id；非管理员追加数据权限） */
    public static function getRelatedList(int $customerId, bool $isAdmin, int $limit = 20): array
    {
        $q = Db::name('contract')
            ->where('party_b_customer_id', $customerId)
            ->where('is_deleted', 0)
            ->field('id, contract_no, title, status, amount, direction, trade_attr')
            ->order('id', 'desc')
            ->limit($limit);
        if (!$isAdmin) {
            AuthLogic::appendDataScope($q, 'owner_id', 'dept_id');
        }
        return $q->select()->toArray();
    }

    /** 客户关联合同总数（N-m4：与 getRelatedList 同口径） */
    public static function getRelatedCount(int $customerId, bool $isAdmin): int
    {
        $q = Db::name('contract')
            ->where('party_b_customer_id', $customerId)
            ->where('is_deleted', 0);
        if (!$isAdmin) {
            AuthLogic::appendDataScope($q, 'owner_id', 'dept_id');
        }
        return $q->count();
    }

    /**
     * AJAX 合同搜索（带数据范围：SELF / DEPT / ALL）。
     * @param string $keyword 关键字
     * @param string $scope   默认 '' 全量；'framework' 仅框架合同（parent_id=0，供「关联框架合同」搜索选择器用）
     * @return array 每项含 id / contract_no / title / status / my（owner_id=当前用户=1，推荐置顶）
     */
    public static function search(string $keyword, string $scope = ''): array
    {
        $uid = (int)\think\facade\Session::get('user_id', 0);
        $query = Db::name('contract')->where('is_deleted', 0);
        AuthLogic::appendDataScope($query, 'owner_id', 'dept_id');
        if ($scope === 'framework') {
            $query->where('parent_id', 0);   // 仅框架合同可被关联
        }
        if ($keyword !== '') {
            $query->where('title|contract_no|keywords', 'like', '%' . $keyword . '%');
        }
        $list = $query->field('id, contract_no, title, status, owner_id')
            ->order('id', 'desc')
            ->limit(20)
            ->select()
            ->toArray();
        // 「与我有关」置顶（owner_id=当前用户优先，作为推荐备选）
        usort($list, function ($a, $b) use ($uid) {
            $am = ((int)$a['owner_id'] === $uid) ? 0 : 1;
            $bm = ((int)$b['owner_id'] === $uid) ? 0 : 1;
            return $am <=> $bm;
        });
        foreach ($list as &$c) {
            $c['my'] = ((int)($c['owner_id'] ?? 0) === $uid) ? 1 : 0;
        }
        unset($c);
        return $list;
    }

    /** 合同创建页「关联框架合同」下拉（仅框架合同 parent_id=0，带数据范围） */
    public static function getFrameworkOptions(int $limit = 500): array
    {
        $query = Db::name('contract')
            ->where('is_deleted', 0)
            ->where('parent_id', 0)
            ->field('id, contract_no, title, status')
            ->order('id', 'desc');
        // 管理员(ALL)不加过滤；非管理员按数据范围收敛
        AuthLogic::appendDataScope($query, 'owner_id', 'dept_id');
        return $query->limit($limit)->select()->toArray();
    }

    /** 附件预览鉴权：按文件名模糊预筛候选合同（含 owner_id / dept_id / file_url） */
    public static function findByAttachmentPath(string $like): array
    {
        return Db::name('contract')
            ->field('id, owner_id, dept_id, file_url')
            ->where('file_url', 'like', '%' . $like . '%')
            ->where('is_deleted', 0)
            ->select()
            ->toArray();
    }

    /** 归档合同列表（status=ARCHIVED 且未删除，带行级数据权限，支持标题 / 合同号模糊搜索） */
    public static function getArchivedList(int $page, int $pageSize, string $keyword = ''): array
    {
        $query = Db::name('contract')->alias('c')
            ->leftJoin('user u', 'c.owner_id = u.id')
            ->field('c.*, u.name as owner_name')
            ->where('c.is_deleted', 0)
            ->where('c.status', 'ARCHIVED');
        // 行级数据权限：归档合同同样按 SELF/DEPT/ALL 过滤，避免越权查看
        AuthLogic::appendDataScope($query, 'c.owner_id', 'c.dept_id');
        if ($keyword !== '') {
            $query->where('c.title|c.contract_no', 'like', '%' . $keyword . '%');
        }
        $total = $query->count();
        $list  = $query->order('c.id', 'desc')->page($page, $pageSize)->select()->toArray();
        return ['list' => $list, 'total' => $total];
    }

    /**
     * 高频关键词（仅统计当前登录用户自己创建的合同的 keywords 字段）
     * 按词频降序返回 TopN，用于新建合同时快速选填。
     * @param int $userId 当前登录用户 ID
     * @param int $limit  返回条数上限，默认 10
     * @return string[] 关键词数组（按词频降序）
     */
    public static function getHotKeywords(int $userId, int $limit = 10): array
    {
        if ($userId <= 0 || $limit <= 0) {
            return [];
        }
        // 仅取当前用户自己创建、未删除且 keywords 非空的合同
        $rows = Db::name('contract')
            ->field('keywords')
            ->where('creator_id', $userId)
            ->where('is_deleted', 0)
            ->where('keywords', '<>', '')
            ->select()
            ->toArray();

        // 词频统计：兼容英文逗号 / 中文逗号 / 顿号 / 分号 / 空白
        $count = [];
        foreach ($rows as $row) {
            foreach (self::tokenizeKeywords((string)($row['keywords'] ?? '')) as $p) {
                $count[$p] = ($count[$p] ?? 0) + 1;
            }
        }
        arsort($count);                       // 按词频降序
        return array_slice(array_keys($count), 0, $limit);
    }

    /**
     * 关键词分词（纯函数，便于单元测试）
     * 与 normalize_keywords() 入库前归一化口径一致的分隔符：英文逗号 / 中文逗号 / 顿号 / 分号 / 空白。
     * 保留同一关键词串内的重复词（供 getHotKeywords 计频），空串返回空数组。
     * @param string $raw 原始 keywords 字符串
     * @return string[] 分词结果
     */
    public static function tokenizeKeywords(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }
        $parts = preg_split('/[，、；;,\s]+/u', $raw);
        $out = [];
        foreach ($parts as $p) {
            $p = trim($p);
            if ($p === '') {
                continue;
            }
            $out[] = $p;
        }
        return $out;
    }
}
