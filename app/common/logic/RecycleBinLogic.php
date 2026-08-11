<?php
// +----------------------------------------------------------------------
// | 数据回收站逻辑（合同 / 客户 / 供应商 软删除记录的恢复与彻底删除）
// +----------------------------------------------------------------------

namespace app\common\logic;

use think\facade\Db;
use think\facade\Log;

/**
 * 回收站：业务数据（合同/客户/供应商）已具软删除（is_deleted=1），
 * 但此前缺 UI 供恢复。本类提供「列出已删除 / 恢复 / 彻底删除」三类操作。
 *
 * 安全约束：
 * - 仅超级管理员（is_admin）可调（控制器层守卫），避免越权恢复/销毁他人数据。
 * - 彻底删除（物理删除）前复用各实体的 deleteBlockers 校验；有阻塞项（如关联合同/回款/子合同）
 *   则拒绝，防止产生孤儿引用。合同物理删除时级联清理其审批实例/记录（与合同强绑定）。
 */
class RecycleBinLogic
{
    /** 受支持的回收站类型：表名 / 名称字段 / 中文标签 / 副信息字段 */
    const TYPES = [
        'contract' => ['table' => 'contract', 'name_field' => 'title', 'label' => '合同', 'sub_field' => 'contract_no'],
        'customer' => ['table' => 'customer', 'name_field' => 'name',  'label' => '客户', 'sub_field' => 'contact_name'],
        'supplier' => ['table' => 'supplier', 'name_field' => 'name',  'label' => '供应商', 'sub_field' => 'contact_name'],
    ];

    public static function isValidType(string $type): bool
    {
        return isset(self::TYPES[$type]);
    }

    /**
     * 列出某类型的已软删除记录（回收站列表）
     * @return array ['list'=>[], 'total'=>int, 'type'=>string]
     */
    public static function listDeleted(string $type, int $page, int $pageSize, string $keyword = ''): array
    {
        if (!self::isValidType($type)) {
            return ['list' => [], 'total' => 0, 'type' => $type];
        }
        $cfg   = self::TYPES[$type];
        $table = $cfg['table'];

        $q = Db::name($table)->alias('t')->where('t.is_deleted', 1);
        if ($keyword !== '') {
            $q->where($cfg['name_field'], 'like', '%' . $keyword . '%');
        }
        $total = $q->count();

        $rows = $q->order('t.updated_at', 'desc')
            ->page($page, $pageSize)
            ->field('t.id, t.' . $cfg['name_field'] . ' as name, t.' . $cfg['sub_field'] . ' as sub, t.updated_at as deleted_at, t.owner_id')
            ->select()->toArray();

        // 批量取归属人名称（上下文展示）
        $ownerIds = array_unique(array_filter(array_column($rows, 'owner_id')));
        $ownerMap = [];
        if (!empty($ownerIds)) {
            $ownerMap = Db::name('user')->whereIn('id', $ownerIds)->column('name', 'id');
        }

        // P2-16【M-A2】N+1 消除：一次性聚合本页全部记录的删除阻塞项（原逐行 blockersFor 触发 4N+ 查询）
        $idList = array_values(array_filter(array_map('intval', array_column($rows, 'id'))));
        $blockersMap = [];
        if ($idList) {
            switch ($type) {
                case 'contract':  $blockersMap = ContractLogic::deleteBlockersMap($idList); break;
                case 'customer':  $blockersMap = CustomerLogic::deleteBlockersMap($idList); break;
                case 'supplier':  $blockersMap = SupplierLogic::deleteBlockersMap($idList); break;
            }
        }

        foreach ($rows as &$r) {
            $r['type']        = $type;
            $r['type_label']  = $cfg['label'];
            $r['owner_name']  = $ownerMap[$r['owner_id']] ?? '';
            $blockers         = $blockersMap[(int)$r['id']] ?? [];
            $r['blockers']    = $blockers;
            $r['can_purge']   = empty($blockers); // 无阻塞项才允许彻底删除
        }
        unset($r);

        return ['list' => $rows, 'total' => $total, 'type' => $type];
    }

    /** 取某记录的删除阻塞项（复用各实体 deleteBlockers） */
    public static function blockersFor(string $type, int $id): array
    {
        switch ($type) {
            case 'contract':
                return ContractLogic::deleteBlockers($id);
            case 'customer':
                return CustomerLogic::deleteBlockers($id);
            case 'supplier':
                return SupplierLogic::deleteBlockers($id);
            default:
                return [];
        }
    }

    /** 恢复：is_deleted 置 0（仅对当前已删除记录生效） */
    public static function restore(string $type, int $id): bool
    {
        if (!self::isValidType($type)) {
            return false;
        }
        $table = self::TYPES[$type]['table'];
        return Db::name($table)->where('id', $id)->where('is_deleted', 1)
            ->update(['is_deleted' => 0, 'updated_at' => date('Y-m-d H:i:s')]) > 0;
    }

    /**
     * 彻底删除（物理删除）。
     * @return array ['ok'=>bool, 'blockers'=>string[]]
     */
    public static function purge(string $type, int $id): array
    {
        if (!self::isValidType($type)) {
            return ['ok' => false, 'blockers' => ['未知类型']];
        }
        // 先校验阻塞项，有则拒绝（防孤儿数据）
        $blockers = self::blockersFor($type, $id);
        if (!empty($blockers)) {
            return ['ok' => false, 'blockers' => $blockers];
        }

        $table = self::TYPES[$type]['table'];
        // P2：彻底删除前取附件清单（事务外先读，事务内删除记录后清理物理文件，避免孤儿文件堆积）
        $attachUrls = [];
        if ($type === 'contract') {
            $contract = Db::name('contract')->where('id', $id)->find();
            if (!empty($contract['file_url'])) {
                $decoded = json_decode((string)$contract['file_url'], true);
                if (is_array($decoded)) {
                    foreach ($decoded as $a) {
                        if (!empty($a['url']) && is_string($a['url'])) {
                            $attachUrls[] = (string)$a['url'];
                        }
                    }
                }
            }
        }
        Db::startTrans();
        try {
            // 合同物理删除时级联清理其审批实例/审批记录/抄送轨迹（与合同强绑定，合同消失后无意义）
            if ($type === 'contract') {
                $instanceIds = Db::name('approval_instance')->where('contract_id', $id)->column('id');
                if (!empty($instanceIds)) {
                    Db::name('approval_record')->whereIn('instance_id', $instanceIds)->delete();
                    // P2-7：审批流抄送轨迹随实例级联清理，避免孤儿抄送记录
                    Db::name('approval_cc_log')->whereIn('instance_id', $instanceIds)->delete();
                    Db::name('approval_instance')->whereIn('id', $instanceIds)->delete();
                }
                // P2-7：合同变更日志（contract_revision）随合同物理删除级联清理，避免孤儿变更记录
                Db::name('contract_revision')->where('contract_id', $id)->delete();
            }
            Db::name($table)->where('id', $id)->where('is_deleted', 1)->delete();
            Db::commit();
            // 记录删除成功后清理附件物理文件：先确认无其他合同（含回收站内）仍引用同一 URL，防共享文件误删
            foreach ($attachUrls as $url) {
                if (strpos($url, '/uploads/contracts/') !== 0) {
                    continue; // 仅清理合同附件目录内的文件（纵深：realpath 边界校验在 remove_upload_file 内）
                }
                $ref = Db::name('contract')->where('file_url', 'like', '%' . $url . '%')->count();
                if ($ref > 0) {
                    continue;
                }
                remove_upload_file($url);
            }
            return ['ok' => true, 'blockers' => []];
        } catch (\Throwable $e) {
            Db::rollback();
            Log::error('回收站彻底删除失败', ['type' => $type, 'id' => $id, 'error' => $e->getMessage()]);
            return ['ok' => false, 'blockers' => ['删除失败，请稍后重试']];
        }
    }
}
