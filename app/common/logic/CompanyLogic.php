<?php
// +----------------------------------------------------------------------
// | 公司主体（签约主体）业务逻辑（Phase 1.9：从 MobileController 提取 company_profile 直查）
// +----------------------------------------------------------------------

namespace app\common\logic;

use think\facade\Db;

class CompanyLogic
{
    /** 公司主体名称（id → name；id 非法或不存在返回空串） */
    public static function getName(int $id): string
    {
        if ($id <= 0) return '';
        return Db::name('company_profile')->where('id', $id)->value('name') ?: '';
    }

    /**
     * 取单条公司主体全字段（id 非法或不存在返回 null）
     * 用于合同详情页展示签约主体 + 开票资料（v2.28.2：从视图层 Db::name 下沉到 Logic）
     */
    public static function getById(int $id): ?array
    {
        if ($id <= 0) return null;
        return Db::name('company_profile')->find($id) ?: null;
    }

    /** 公司主体列表（index 视图所需字段；默认主体排前）
     * 仅返回 全称/简称/代码/开票税率/默认标记；视图不再依赖开票类冗余字段。
     */
    public static function getList(): array
    {
        return Db::name('company_profile')
            ->field('id, name, short_name, unified_social_credit_code, invoice_tax_rate, is_default')
            ->order('is_default', 'desc')
            ->order('id')
            ->select()
            ->toArray();
    }

    /** 默认公司主体 id（无默认返回 0） */
    public static function getDefaultId(): int
    {
        return (int)(Db::name('company_profile')->where('is_default', 1)->value('id') ?: 0);
    }

    /** 公司主体简表（id,name,short_name；默认主体排前，供合同列表/资料库映射用） */
    public static function getBriefList(): array
    {
        return Db::name('company_profile')
            ->field('id, name, short_name')
            ->order('is_default', 'desc')
            ->order('id')
            ->select()
            ->toArray();
    }

    /**
     * 开票税率（开票申请按主体自动带出，后台公司管理配置）
     * 未配置/公司不存在时返回默认 6%（与 company_profile 列默认值一致，兼容旧库未跑迁移场景）
     */
    public static function getInvoiceTaxRate(int $id): float
    {
        if ($id <= 0) return 0.06;
        $rate = Db::name('company_profile')->where('id', $id)->value('invoice_tax_rate');
        if ($rate === null || $rate === '') return 0.06;
        $rate = (float)$rate;
        return ($rate >= 0 && $rate < 1) ? $rate : 0.06;
    }

    /** 公司主体名称映射用（id,name） */
    public static function getSelectNames(): array
    {
        return Db::name('company_profile')
            ->field('id, name')
            ->order('is_default', 'desc')
            ->order('id')
            ->select()
            ->toArray();
    }

    /** 含默认标记的主体列表（id,name,short_name,is_default,invoice_tax_rate；供合同创建页默认主体带出/发票申请主体下拉带税率） */
    public static function getListWithDefault(): array
    {
        return Db::name('company_profile')
            ->field('id, name, short_name, is_default, invoice_tax_rate')
            ->order('is_default', 'desc')
            ->order('id')
            ->select()
            ->toArray();
    }

    /** 系统管理-公司主体下拉（id/全称/简称/代码/默认标记） */
    public static function getManageOptions(): array
    {
        return Db::name('company_profile')
            ->field('id, name, short_name, unified_social_credit_code, is_default')
            ->order('is_default', 'desc')
            ->order('id')
            ->select()
            ->toArray();
    }

    /**
     * 新增/编辑公司主体，返回 id
     * 唯一默认主体校验 + 自动默认逻辑由原控制器内联逻辑整段下沉，行为等价。
     * 开票税率（invoice_tax_rate）随 $data 一并入库（控制器已做 0≤rate<1 白名单校验）。
     */
    public static function save(array $data, int $id): int
    {
        // 唯一默认主体校验：若本次设为默认，先将其他默认主体置为非默认
        if (!empty($data['is_default'])) {
            $other = Db::name('company_profile')->where('is_default', 1);
            if ($id > 0) {
                $other->where('id', '<>', $id);
            }
            if ($other->count() > 0) {
                Db::name('company_profile')->where('is_default', 1)->where('id', '<>', $id)->update(['is_default' => 0]);
            }
        }
        if ($id > 0) {
            $data['updated_at'] = date('Y-m-d H:i:s');
            Db::name('company_profile')->where('id', $id)->update($data);
        } else {
            $data['created_at'] = date('Y-m-d H:i:s');
            $data['updated_at'] = $data['created_at'];
            $id = Db::name('company_profile')->insertGetId($data);
            // 若没有任何默认主体，自动设为默认
            if (Db::name('company_profile')->where('is_default', 1)->count() === 0) {
                Db::name('company_profile')->where('id', $id)->update(['is_default' => 1]);
            }
        }
        return $id;
    }

    /**
     * 删除公司主体（事务保护）
     * 默认主体拒绝删除；删除前解除关联合同的签约主体(our_company_id 置 0)。
     * @return array ['ok'=>bool, 'msg'=>string]
     */
    public static function delete(int $id): array
    {
        if ($id <= 0) {
            return ['ok' => false, 'msg' => '参数错误'];
        }
        $isDefault = Db::name('company_profile')->where('id', $id)->value('is_default');
        if ($isDefault) {
            return ['ok' => false, 'msg' => '默认主体不可删除，请先设置其他主体为默认'];
        }
        Db::transaction(function () use ($id) {
            // 解除关联合同的签约主体，避免悬空引用
            Db::name('contract')->where('our_company_id', $id)->update(['our_company_id' => 0]);
            Db::name('company_profile')->where('id', $id)->delete();
        });
        return ['ok' => true, 'msg' => '已删除'];
    }
}
