<?php
// +----------------------------------------------------------------------
// | 合同模板业务逻辑（GOLF 分层铁律下沉：从 TemplateController 提取 Db 直查）
// | 权限校验由控制器把关，本类仅承载模板数据的读写与状态变更。
// | contract_template 为全局配置实体，按铁律不附加行级数据范围。
// +----------------------------------------------------------------------

namespace app\common\logic;

use think\facade\Db;

class TemplateLogic
{
    /** 模板列表（按状态筛选：active=非禁用，disabled=禁用，其余=全部） */
    public static function getList(string $status = ''): array
    {
        $query = Db::name('contract_template')->order('id', 'desc');
        if ($status === 'active') {
            $query->where('status', '<>', 'DISABLED');
        } elseif ($status === 'disabled') {
            $query->where('status', 'DISABLED');
        }
        return $query->select()->toArray();
    }

    /** 取单条模板（id 非法返回 null） */
    public static function getById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        return Db::name('contract_template')->find($id) ?: null;
    }

    /**
     * 新增/编辑模板，返回模板 id
     * creator_id 由调用方在 $data 中提供（新建时）；时间戳由本方法统一兜底。
     */
    public static function save(int $id, array $data): int
    {
        if ($id) {
            $data['updated_at'] = date('Y-m-d H:i:s');
            Db::name('contract_template')->where('id', $id)->update($data);
        } else {
            if (!isset($data['creator_id'])) {
                $data['creator_id'] = 0;
            }
            $data['created_at'] = date('Y-m-d H:i:s');
            $data['updated_at'] = $data['created_at'];
            $id = Db::name('contract_template')->insertGetId($data);
        }
        return $id;
    }

    /** 设置模板状态（发布/禁用/恢复草稿） */
    public static function setStatus(int $id, string $status): void
    {
        $data = ['status' => $status];
        if ($status !== 'DISABLED') {
            $data['updated_at'] = date('Y-m-d H:i:s');
        }
        Db::name('contract_template')->where('id', $id)->update($data);
    }

    /** 已发布模板下拉选项（id,name,code） */
    public static function getPublishedOptions(): array
    {
        return Db::name('contract_template')
            ->where('status', 'PUBLISHED')
            ->field('id, name, code')
            ->order('id')
            ->select()
            ->toArray();
    }
}
