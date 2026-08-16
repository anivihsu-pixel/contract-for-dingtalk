<?php
namespace app\controller;

use app\BaseController;
use app\common\logic\GlobalSearchLogic;
use think\facade\View;

class SearchController extends BaseController
{
    public function index()
    {
        $this->requireAnyPermission(['contract:view','customer:view','project:view','supplier:view']);
        $keyword = trim((string)$this->getParam('q',''));
        $result = ['keyword'=>$keyword,'total'=>0,'groups'=>[]];
        $error = '';
        if ($keyword !== '') {
            try { $result = GlobalSearchLogic::search($keyword); }
            catch (\RuntimeException $e) { $error = $e->getMessage(); }
        }
        if (request()->isAjax()) return $error ? json_error($error) : json_success($result);
        View::assign('result',$result); View::assign('keyword',$keyword); View::assign('error',$error);
        return View::fetch();
    }
}
