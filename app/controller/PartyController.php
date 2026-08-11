<?php
// +----------------------------------------------------------------------
// | 相对方 360 控制器（客户 / 供应商统一往来档案）
// +----------------------------------------------------------------------

namespace app\controller;

use think\facade\View;
use app\BaseController;
use app\common\logic\PartyLogic;
use app\common\logic\AuthLogic;
use app\common\logic\CustomerLogic;
use app\common\logic\SupplierLogic;

class PartyController extends BaseController
{
    /** 相对方合并列表单类型安全上限（arch P1-3，防止客户/供应商全量载入；命中即截断并提示搜索） */
    private const PARTY_LIST_LIMIT = 200;

    /** 相对方总览（客户 + 供应商统一列表） */
    public function index()
    {
        $this->requirePermission('party:view');
        $keyword = $this->getParam('keyword', '');
        $type    = $this->getParam('type', '');   // customer | supplier | ''

        $parties = [];
        $truncated = false;

        if ($type !== 'supplier') {
            $rows = CustomerLogic::getPartyRows($keyword, self::PARTY_LIST_LIMIT);
            if (count($rows) >= self::PARTY_LIST_LIMIT) {
                $truncated = true;
            }
            foreach ($rows as $r) {
                $r['type']        = 'customer';
                $r['type_label']  = '客户';
                $parties[] = $r;
            }
        }

        if ($type !== 'customer') {
            $rows = SupplierLogic::getPartyRows($keyword, self::PARTY_LIST_LIMIT);
            if (count($rows) >= self::PARTY_LIST_LIMIT) {
                $truncated = true;
            }
            foreach ($rows as $r) {
                $supType      = $r['type'] ?? '';              // supplier.type 字典码（MEDIA/SERVICE…）
                $r['type']        = 'supplier';
                $r['type_label']  = '供应商';
                // v2.38.11 修复：tag 显示供应商类型中文（原误显 'supplier'）
                $r['tag']         = $supType ? (dict('supplier_type')[$supType] ?? $supType) : '';
                $parties[] = $r;
            }
        }

        // v2.38.14：往来档案「往来」列——批量汇总（避免逐行 getSummary N+1），挂 _sum
        $sums = PartyLogic::summarizeBatch($parties);
        foreach ($parties as $i => $p) {
            $parties[$i]['_sum'] = $sums[$p['type'] . ':' . $p['id']] ?? null;
        }

        View::assign('parties', $parties);
        View::assign('keyword', $keyword);
        View::assign('type', $type);
        View::assign('truncated', $truncated);
        // v2.38.11: 注入 menu_active 使侧边栏「往来档案」二级菜单正确高亮（此前仅父分组展开条件含 party，子菜单无高亮）
        View::assign('menu_active', 'party');
        return View::fetch();
    }

    /** 相对方 360 视图 */
    public function view($type = '', $id = 0)
    {
        $this->requirePermission('party:view');
        $id = (int)$id;
        if (!in_array($type, PartyLogic::TYPES, true) || $id <= 0) {
            return '参数错误';
        }

        $data = PartyLogic::get360($type, $id);
        if (!$data['ok']) {
            return $data['msg'] ?? '相对方不存在';
        }

        // 越权防护：基础档案按数据权限（owner/dept/admin）
        $base = $data['base'];
        if (!AuthLogic::canAccessRecord($base['owner_id'] ?? 0, $base['dept_id'] ?? 0)) {
            return '无权查看该相对方';
        }

        View::assign('type', $type);
        View::assign('base', $base);
        View::assign('stats', $data['stats']);
        View::assign('contracts', $data['contracts']);
        View::assign('payments', $data['payments']);
        View::assign('invoices', $data['invoices']);
        View::assign('activity', $data['activity']);
        // 显式指定模板：方法名为 view，ThinkPHP 默认解析为 party/view.php，但实际视图文件为 party/360.php（否则 500 模板不存在）
        return View::fetch('party/360');
    }

    /** AJAX: 360 数据（供前端按需刷新） */
    public function data($type = '', $id = 0)
    {
        $this->requirePermission('party:view');
        $id = (int)$id;
        if (!in_array($type, PartyLogic::TYPES, true) || $id <= 0) {
            return json_error('参数错误');
        }
        $data = PartyLogic::get360($type, $id);
        if (!$data['ok']) {
            return json_error($data['msg'] ?? '相对方不存在');
        }
        // 越权防护
        $base = $data['base'];
        if (!AuthLogic::canAccessRecord($base['owner_id'] ?? 0, $base['dept_id'] ?? 0)) {
            return json_error('无权查看该相对方');
        }
        return json_success($data);
    }
}
