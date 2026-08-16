<?php

namespace app\controller;

use app\common\service\ProductionCheckService;

class HealthController
{
    public function index()
    {
        $result = ProductionCheckService::run(false);
        return json([
            'status' => $result['ok'] ? 'ok' : 'degraded',
            'time' => date(DATE_ATOM),
        ], $result['ok'] ? 200 : 503);
    }
}
