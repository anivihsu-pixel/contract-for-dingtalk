<?php
// +----------------------------------------------------------------------
// | 全局异常处理器（P1-6）：统一 500 错误输出为 JSON 或友好错误页
// +----------------------------------------------------------------------

namespace app\exception;

use think\exception\Handle;
use think\exception\HttpException;
use think\exception\ClassNotFoundException;
use think\Request;
use think\Response;
use think\facade\View;
use Throwable;

/**
 * 重写 think\exception\Handle::render()：
 *  - 服务器内部错误（非 HttpException）统一归类为 500；
 *  - 对 AJAX / JSON 请求返回标准化 JSON（生产环境不泄露堆栈与内部路径）；
 *  - 对普通页面请求渲染友好 500 错误页（app/view/error/500.html）；
 *  - 403/404 等 HttpException 交回父类渲染，保持既有错误页与 BaseController::deny() 行为不变。
 *  - v2.38.14：/uploads/ 下静态文件缺失被路由解析出「控制器不存在」时（如测试残留附件
 *    /uploads/reg/t.pdf 无实际文件），返回 404 文本而非框架 debug 错误页——附件缺失属于
 *    资源不存在（404），不应呈现为应用内部错误。
 */
class ExceptionHandle extends Handle
{
    /**
     * 渲染异常为 HTTP 响应
     * @param Request   $request 当前请求
     * @param Throwable $e       捕获的异常
     * @return Response
     */
    public function render($request, Throwable $e): Response
    {
        // v2.38.14：/uploads/ 下静态文件缺失（框架路由解析为 404 controller not exists，
        // 如测试残留附件 /uploads/reg/t.pdf 无实际文件）→ 友好 404 文本，避免 debug 错误页
        // 呈现「控制器不存在:UploadsController」；附件缺失属于资源不存在（404）而非应用内部错误
        if ($e instanceof HttpException
            && $e->getStatusCode() === 404
            && str_starts_with($request->pathinfo(), 'uploads')) {
            return Response::create('文件不存在或已被删除', 'html', 404);
        }

        // 判定是否为 JSON / AJAX 请求：Accept 含 application/json、AJAX 标识、或显式 ?format=json
        $accept        = $request->header('Accept', '');
        $isJsonRequest = $request->isJson()
            || $request->isAjax()
            || stripos($accept, 'application/json') !== false
            || $request->param('format') === 'json';

        // 403/404 等 HTTP 异常：JSON/AJAX 请求返回标准化 JSON（前端 $ajax 打到不存在路由/资源时
        // 应拿到 {code,msg} 而非 HTML 错误页，与 BaseController::deny() 的 JSON 分拣行为对齐）；
        // 普通页面请求维持框架既有渲染（含 403.html）
        if ($e instanceof HttpException) {
            if ($isJsonRequest) {
                $code = $e->getStatusCode();
                $msg  = $code === 404 ? '请求的资源不存在' : ($code === 403 ? '权限不足，请联系管理员' : '请求失败');
                return Response::create(['code' => $code, 'msg' => $msg, 'data' => null], 'json', $code);
            }
            // v2.43.3：403 页面请求渲染友好 403 页（与 BaseController::deny() 行为一致），
            // 替代 ThinkPHP debug 模式下的框架默认错误页；业务拦截消息（如「当前状态不可编辑」）
            // 通过 $err_msg 传入模板展示，避免用户看到框架品牌错误页。
            if ($e->getStatusCode() === 403) {
                $isMobile = function_exists('is_mobile_request') && is_mobile_request();
                View::assign('back_url', $isMobile ? '/m' : '/dashboard');
                View::assign('home_text', $isMobile ? '返回工作台' : '返回驾驶舱');
                View::assign('err_msg', $e->getMessage());
                return Response::create((string) View::fetch('error/403'), 'html', 403);
            }
            // v2.44.1 P1：404 页面请求不再回退 parent::render()——ThinkPHP 父类在 debug 模式下
            // 会输出堆栈/SQL/服务器路径（生产 APP_DEBUG 误开即泄露）；自渲染友好 404 页与 403/500 口径一致。
            $isMobile = function_exists('is_mobile_request') && is_mobile_request();
            View::assign('back_url', $isMobile ? '/m' : '/dashboard');
            View::assign('home_text', $isMobile ? '返回工作台' : '返回驾驶舱');
            View::assign('err_msg', '您访问的页面不存在或已被移除');
            $file = $this->app->getRootPath() . 'app/view/error/404.php';
            if (is_file($file)) {
                // PHP 原生视图驱动下错误页不依赖控制器视图路径，直接按绝对路径渲染。
                $back_url = $isMobile ? '/m' : '/dashboard';
                $home_text = $isMobile ? '返回工作台' : '返回驾驶舱';
                $err_msg = '您访问的页面不存在或已被移除';
                ob_start();
                include $file;
                return Response::create((string) ob_get_clean(), 'html', 404);
            }
            return Response::create('<h1 style="text-align:center;padding-top:80px;color:#666">404 页面不存在</h1>', 'html', 404);
        }

        if ($isJsonRequest) {
            // 生产环境（app_debug 为空）：统一返回友好提示，不泄露异常堆栈/内部路径/SQL 等细节。
            // 业务码固定 500，HTTP 状态码 500，与前端统一错误拦截约定一致。
            return Response::create(
                ['code' => 500, 'msg' => '服务器内部错误，请稍后重试', 'data' => null],
                'json',
                500
            );
        }

        // 非 AJAX：渲染友好 500 错误页（文件缺失时降级为内联提示，避免二次异常）
        $file = $this->app->getRootPath() . 'app/view/error/500.html';
        $html = is_file($file) ? (string) file_get_contents($file) : '<h1>500 服务器内部错误</h1>';
        return Response::create($html, 'html', 500);
    }
}
