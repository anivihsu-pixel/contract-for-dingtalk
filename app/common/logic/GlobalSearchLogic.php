<?php
namespace app\common\logic;

use think\facade\Db;
use think\facade\Session;

/** 全局搜索聚合；各业务域查询均在服务端执行数据范围校验。 */
class GlobalSearchLogic
{
    public static function search(string $keyword, int $limitPerType = 10): array
    {
        $keyword = trim($keyword);
        if (mb_strlen($keyword) < 2) {
            throw new \RuntimeException('请输入至少 2 个字符');
        }
        $limitPerType = max(1, min(20, $limitPerType));
        $uid = (int)Session::get('user_id', 0);
        $user = Session::get('user', []);
        $deptId = (int)($user['dept_id'] ?? 0);
        $groups = [];

        $groups['contracts'] = array_map(static fn($r) => [
            'type'=>'contract','label'=>$r['title'],'meta'=>$r['contract_no'].' · '.$r['status'],'url'=>'/contract/'.$r['id'],
        ], array_slice(ContractLogic::search($keyword), 0, $limitPerType));

        $groups['customers'] = array_map(static fn($r) => [
            'type'=>'customer','label'=>$r['name'],'meta'=>$r['contact_name'] ?? '','url'=>'/customer/'.$r['id'],
        ], array_slice(CustomerLogic::search($keyword, $uid, $deptId), 0, $limitPerType));

        $groups['suppliers'] = array_map(static fn($r) => [
            'type'=>'supplier','label'=>$r['name'],'meta'=>$r['type'] ?? '','url'=>'/supplier/'.$r['id'],
        ], array_slice(SupplierLogic::search($keyword, $uid, $deptId), 0, $limitPerType));

        $groups['projects'] = array_map(static fn($r) => [
            'type'=>'project','label'=>$r['name'],'meta'=>$r['code'] ?? '','url'=>'/project/'.$r['id'],
        ], array_slice(ProjectLogic::search($keyword, $limitPerType), 0, $limitPerType));

        $contactRows = Db::name('customer_contact')->alias('cc')->join('customer c','c.id=cc.customer_id')
            ->where('c.is_deleted',0)->where('cc.name|cc.phone|cc.email','like','%'.$keyword.'%')
            ->field('cc.id,cc.name,cc.phone,cc.email,cc.customer_id,c.name customer_name,c.owner_id,c.dept_id,c.parent_id')
            ->limit(50)->select()->toArray();
        $contacts = [];
        foreach ($contactRows as $row) {
            if (!CustomerLogic::canAccessCustomer($uid, $row, $deptId)) continue;
            $contacts[] = ['type'=>'contact','label'=>$row['name'],'meta'=>$row['customer_name'].' · '.($row['phone'] ?: $row['email']),'url'=>'/customer/'.$row['customer_id']];
            if (count($contacts) >= $limitPerType) break;
        }
        $groups['contacts'] = $contacts;

        $labels = ['contracts'=>'合同','customers'=>'客户','contacts'=>'联系人','projects'=>'项目','suppliers'=>'供应商'];
        $total = 0;
        foreach ($groups as $key => &$group) {
            $total += count($group);
            $group = ['label'=>$labels[$key], 'items'=>$group];
        }
        return ['keyword'=>$keyword,'total'=>$total,'groups'=>$groups];
    }
}
