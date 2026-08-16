<?php
// +----------------------------------------------------------------------
// | 客户联系人逻辑（M9 独立联系人模块，v2.38.3）
// | 客户可拥有多名联系人，支持主联系人标记与 CRUD。
// +----------------------------------------------------------------------

namespace app\common\logic;

use think\facade\Db;

class CustomerContactLogic
{
    /**
     * 取某客户的全部联系人（主联系人优先，其次按 id 升序）。
     * @param int $customerId
     * @return array
     */
    public static function getListByCustomer(int $customerId): array
    {
        if ($customerId <= 0) {
            return [];
        }
        return Db::name('customer_contact')
            ->where('customer_id', $customerId)
            ->order('is_primary', 'desc')
            ->order('id', 'asc')
            ->select()
            ->toArray();
    }

    /**
     * 取客户联系人（详情页展示用）：customer_contact 表 + 主联系人字段兜底。
     * v2.38.11 修复：客户表单仅维护 customer.contact_name（主联系人），M9 独立联系人表
     * customer_contact 对存量客户为空 → 详情页联系人栏目显示"暂无联系人"。
     * 兜底：联系人表为空但有主联系人字段时，构造一条 is_primary=1 的记录展示。
     * @param int $customerId
     * @param array $customer 客户记录（含 contact_name/contact_mobile/contact_email）
     * @return array
     */
    public static function getListForDisplay(int $customerId, array $customer): array
    {
        $list = self::getListByCustomer($customerId);
        if (!empty($list)) {
            return $list;
        }
        if (!empty($customer['contact_name'])) {
            return [[
                'id'           => 0,
                'customer_id'  => $customerId,
                'name'         => $customer['contact_name'],
                'phone'        => $customer['contact_mobile'] ?? '',
                'email'        => $customer['contact_email'] ?? '',
                'is_primary'   => 1,
                'from_primary' => true,   // 主联系人字段兜底（非 customer_contact 表记录）
            ]];
        }
        return [];
    }

    /**
     * 按 id 取单条联系人。
     * @param int $id
     * @return array|null
     */
    public static function getById(int $id): ?array
    {
        $row = Db::name('customer_contact')->where('id', $id)->find();
        return $row ?: null;
    }

    /**
     * 新增/更新联系人。
     * @param array $data 含 customer_id/name/phone/email/is_primary/remark，可选 id
     * @return int 新/更新后的 id
     * @throws \InvalidArgumentException
     */
    public static function save(array $data): int
    {
        $customerId = (int)($data['customer_id'] ?? 0);
        if ($customerId <= 0) {
            throw new \InvalidArgumentException('联系人必须归属客户');
        }
        $name = trim((string)($data['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('联系人姓名不能为空');
        }
        $phone = trim((string)($data['phone'] ?? ''));
        if ($phone !== '' && !preg_match('/^[\d\-+\s()]{5,20}$/', $phone)) {
            throw new \InvalidArgumentException('联系电话格式不正确');
        }
        $email = trim((string)($data['email'] ?? ''));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('邮箱格式不正确');
        }
        $isPrimary = !empty($data['is_primary']) ? 1 : 0;
        // v2.38.12: 备注/更多信息（微信号等）
        $remark = trim((string)($data['remark'] ?? ''));

        $row = [
            'customer_id' => $customerId,
            'name'        => $name,
            'phone'       => $phone,
            'email'       => $email,
            'is_primary'  => $isPrimary,
            'remark'      => $remark,
        ];

        $id = (int)($data['id'] ?? 0);
        if ($id > 0) {
            Db::name('customer_contact')->where('id', $id)->update($row);
            if ($isPrimary) {
                self::unsetOtherPrimary($id, $customerId);
            }
            return $id;
        }
        $newId = (int)Db::name('customer_contact')->insertGetId($row);
        if ($isPrimary) {
            self::unsetOtherPrimary($newId, $customerId);
        }
        return $newId;
    }

    /**
     * 删除联系人。
     * @param int $id
     * @return void
     */
    public static function delete(int $id): void
    {
        Db::name('customer_contact')->where('id', $id)->delete();
    }

    /**
     * 设为某客户的主联系人（取消同客户其他主联系人）。
     * @param int $id
     * @param int $customerId
     * @return void
     */
    public static function setPrimary(int $id, int $customerId): void
    {
        self::unsetOtherPrimary($id, $customerId);
        Db::name('customer_contact')->where('id', $id)->update(['is_primary' => 1]);
    }

    /**
     * 取消同客户下除 keepId 外的所有主联系人标记。
     * @param int $keepId
     * @param int $customerId
     * @return void
     */
    private static function unsetOtherPrimary(int $keepId, int $customerId): void
    {
        Db::name('customer_contact')
            ->where('customer_id', $customerId)
            ->where('id', '<>', $keepId)
            ->update(['is_primary' => 0]);
    }
}
