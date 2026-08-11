<?php
// +----------------------------------------------------------------------
// | 资料库业务逻辑（GOLF 分层铁律下沉：从 ResourceController 提取 Db 直查）
// | 资料库为共享参考资料，按铁律不附加行级数据范围（保持原有可见性）。
// +----------------------------------------------------------------------

namespace app\common\logic;

use think\facade\Db;

class ResourceLogic
{
    /** 资料分类中文名映射 */
    private static array $CATEGORIES = [
        'TEMPLATE' => '合同范本',
        'INVOICE'  => '开票资料',
        'CLAUSE'   => '标准条款',
        'OTHER'    => '其他',
    ];

    /** 开票资料结构化字段（key => 中文标签）；仅 INVOICE 分类使用，前端按此渲染录入/展示
     *  顺序：单位名称/税号 → 开户行/账号 → 地址/电话（开户行与账号为独立字段，v2.40.1 拆分） */
    public static array $INVOICE_FIELDS = [
        'unit_name' => '单位名称',
        'tax_no'    => '纳税人识别号',
        'bank_name' => '开户行',
        'account_no'=> '账号',
        'address'   => '地址',
        'tel'       => '电话',
    ];

    /** 分类中文名（供控制器视图使用） */
    public static function categories(): array
    {
        return self::$CATEGORIES;
    }

    /** 解析资料 content（JSON 字符串）为关联数组；非法/空返回空数组 */
    public static function decodedContent(?string $content): array
    {
        if (!$content) { return []; }
        $arr = json_decode($content, true);
        return is_array($arr) ? $arr : [];
    }

    /**
     * 分页参数规范化（2026-08-05 自 ResourceController::list 提取，供单元测试覆盖）
     * page < 1 → 1；pageSize 越界（<1 或 >100）→ 默认 20。
     * @return array [page, pageSize]
     */
    public static function normalizePage(int $page, int $pageSize): array
    {
        if ($page < 1) { $page = 1; }
        if ($pageSize < 1 || $pageSize > 100) { $pageSize = 20; }
        return [$page, $pageSize];
    }

    /** 资料库下拉用公司主体（id,name） */
    public static function getCompanies(): array
    {
        return CompanyLogic::getSelectNames();
    }

    /**
     * 资料库列表（按分类/主体/关键词筛选 + 分页 + 主体名称回填）
     * @return array ['list'=>array, 'total'=>int]
     */
    public static function getList(string $category, int $companyId, string $keyword, int $page, int $pageSize): array
    {
        $query = Db::name('resource_library');
        if ($category !== '' && isset(self::$CATEGORIES[$category])) {
            $query->where('category', $category);
        }
        if ($companyId > 0) {
            $query->where('company_id', $companyId);
        }
        if ($keyword !== '') {
            $query->where('title', 'like', '%' . $keyword . '%');
        }
        $total = $query->count();
        $list  = $query->order('id', 'desc')->page($page, $pageSize)->select()->toArray();

        $companyMap = array_column(CompanyLogic::getSelectNames(), 'name', 'id');
        foreach ($list as &$r) {
            $r['category_name'] = self::$CATEGORIES[$r['category']] ?? $r['category'];
            $r['company_name']  = $r['company_id'] > 0 ? ($companyMap[$r['company_id']] ?? '') : '';
        }
        unset($r);

        return ['list' => $list, 'total' => $total];
    }

    /** 新增资料，返回 id（owner_id 由调用方提供） */
    public static function create(array $data, int $ownerId): int
    {
        $data['owner_id']   = $ownerId;
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = $data['created_at'];
        return Db::name('resource_library')->insertGetId($data);
    }

    /** 取单条资料（用于删除前取文件路径） */
    public static function findRaw(int $id): ?array
    {
        return Db::name('resource_library')->where('id', $id)->find() ?: null;
    }

    /** 更新资料记录（updated_at 自动覆盖为当前时间） */
    public static function update(int $id, array $data): void
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        Db::name('resource_library')->where('id', $id)->update($data);
    }

    /** 删除资料记录 */
    public static function delete(int $id): void
    {
        Db::name('resource_library')->where('id', $id)->delete();
    }

    /** 按文件 URL 精确匹配资料（预览鉴权用，共享资料库任意登录用户可访问） */
    public static function findByFileUrl(string $path): ?array
    {
        return Db::name('resource_library')->where('file_url', $path)->find() ?: null;
    }
}
