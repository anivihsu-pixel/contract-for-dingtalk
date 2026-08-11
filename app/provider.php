<?php
// +----------------------------------------------------------------------
// | 容器绑定
// +----------------------------------------------------------------------

use app\Request;

return [
    'think\Request' => Request::class,

    // P1-6：全局异常处理器覆盖。
    // 框架通过 $app->make(think\exception\Handle::class) 解析异常处理器，
    // 故必须将抽象类 think\exception\Handle 绑定到本项目实现，render() 才会被正确加载。
    // （下方 exception_handle 别名仅作语义说明，框架实际以 think\exception\Handle 解析）
    'think\exception\Handle' => \app\exception\ExceptionHandle::class,
    'exception_handle'       => \app\exception\ExceptionHandle::class,
];
