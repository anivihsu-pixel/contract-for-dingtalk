<?php
// +----------------------------------------------------------------------
// | 使用手册控制器
// +----------------------------------------------------------------------

namespace app\controller;

use think\facade\View;
use app\BaseController;

class ManualController extends BaseController
{
    /**
     * 使用手册 — 面向全体登录用户的操作手册（纯静态说明，不含业务数据，无需权限码）
     * PC 与移动端共用同一页面：PC 布局自带响应式（窄屏收起侧边栏）+ 底部移动导航
     */
    public function index()
    {
        return View::fetch();
    }
}
