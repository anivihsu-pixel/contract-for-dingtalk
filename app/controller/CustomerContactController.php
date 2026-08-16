<?php
// +----------------------------------------------------------------------
// | 客户联系人控制器（M9 独立联系人模块，v2.38.3）
// | AJAX CRUD：列表 / 新增编辑 / 删除 / 设主联系人。
// +----------------------------------------------------------------------

namespace app\controller;

use app\BaseController;
use app\common\logic\CustomerContactLogic;
use app\common\logic\CustomerLogic;

class CustomerContactController extends BaseController
{
    /**
     * 校验目标客户存在且在当前用户数据范围内（H2 修复：联系人子接口此前缺失客户归属校验，成为越权旁路）
     * 供写操作（新增/编辑/删除/设主）使用。
     * @return bool true=可访问；false=不存在/无权限
     */
    private function canAccessCustomer(int $customerId): bool
    {
        if ($customerId <= 0) {
            return false;
        }
        $cust = CustomerLogic::findActive($customerId);
        if (!$cust) {
            return false;
        }
        return \app\common\logic\AuthLogic::canAccessRecord((int)$cust['owner_id'], $cust['dept_id'] ?? 0);
    }

    /**
     * S-12：只读可见性校验——与客户详情页一致；
     * 写操作仍走 canAccessCustomer。
     */
    private function canViewCustomer(int $customerId): bool
    {
        if ($customerId <= 0) {
            return false;
        }
        $cust = CustomerLogic::findActive($customerId);
        if (!$cust) {
            return false;
        }
        return \app\common\logic\AuthLogic::canAccessRecord((int)$cust['owner_id'], $cust['dept_id'] ?? 0);
    }

    /**
     * AJAX: 取某客户联系人列表（详情页渲染 + 合同创建页下拉共用）。
     * GET /ajax/customer/<customerId>/contacts
     */
    public function list($customerId = 0)
    {
        $this->requirePermission('customer:view');
        $customerId = (int)$customerId;
        // S-12：列表读取用只读可见性，与客户详情页语义一致
        if (!$this->canViewCustomer($customerId)) {
            return json_error('无权限查看该客户联系人', 403);
        }
        $list = CustomerContactLogic::getListByCustomer($customerId);
        return json_success($list);
    }

    /**
     * AJAX: 新增/编辑联系人。
     * POST /ajax/customer/contact/save  body: id?(0=新增) customer_id name phone email is_primary remark?
     */
    public function save()
    {
        $this->requirePermission('customer:edit');
        $id = (int)$this->getPost('id', 0);
        $data = [
            'id'         => $id,
            'customer_id'=> (int)$this->getPost('customer_id', 0),
            'name'       => $this->getPost('name', ''),
            'phone'      => $this->getPost('phone', ''),
            'email'      => $this->getPost('email', ''),
            'is_primary' => (int)$this->getPost('is_primary', 0),
            'remark'     => $this->getPost('remark', ''),   // v2.38.12: 备注/更多信息（微信号等）
        ];
        // 编辑时若未传 customer_id，沿用原记录归属
        if ($id > 0 && $data['customer_id'] <= 0) {
            $old = CustomerContactLogic::getById($id);
            if ($old) {
                $data['customer_id'] = (int)$old['customer_id'];
            }
        }
        // H2：目标客户必须存在且在当前数据范围内，否则拒绝写入
        if (!$this->canAccessCustomer($data['customer_id'])) {
            return json_error('无权限操作该客户联系人', 403);
        }
        try {
            $savedId = CustomerContactLogic::save($data);
        } catch (\InvalidArgumentException $e) {
            return json_error($e->getMessage());
        }
        return json_success(['id' => $savedId], '保存成功');
    }

    /**
     * AJAX: 删除联系人。
     * POST /ajax/customer/contact/delete  body: id
     */
    public function delete()
    {
        $this->requirePermission('customer:edit');
        $id = (int)$this->getPost('id', 0);
        if ($id <= 0) {
            return json_error('参数错误');
        }
        $old = CustomerContactLogic::getById($id);
        if (!$old) {
            return json_error('联系人不存在');
        }
        // H2：联系人归属客户须在当前数据范围内
        if (!$this->canAccessCustomer((int)$old['customer_id'])) {
            return json_error('无权限删除该联系人', 403);
        }
        CustomerContactLogic::delete($id);
        return json_success(null, '已删除');
    }

    /**
     * AJAX: 设为主联系人。
     * POST /ajax/customer/contact/primary  body: id customer_id
     */
    public function setPrimary()
    {
        $this->requirePermission('customer:edit');
        $id = (int)$this->getPost('id', 0);
        $customerId = (int)$this->getPost('customer_id', 0);
        if ($id <= 0 || $customerId <= 0) {
            return json_error('参数错误');
        }
        // H2：目标客户数据范围 + 联系人必须属于该客户（防跨客户置主）
        if (!$this->canAccessCustomer($customerId)) {
            return json_error('无权限操作该客户联系人', 403);
        }
        $rec = CustomerContactLogic::getById($id);
        if (!$rec || (int)$rec['customer_id'] !== $customerId) {
            return json_error('联系人不存在或不属于该客户');
        }
        CustomerContactLogic::setPrimary($id, $customerId);
        return json_success(null, '已设为主联系人');
    }
}
