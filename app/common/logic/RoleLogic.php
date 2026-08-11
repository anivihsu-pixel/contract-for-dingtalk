<?php
// +----------------------------------------------------------------------
// | 角色业务逻辑（Phase 1.9：从 MobileController 提取 role 表直查）
// +----------------------------------------------------------------------

namespace app\common\logic;

use think\facade\Db;

class RoleLogic
{
    /** 角色映射（code → name），供审批节点角色展示 */
    public static function getMap(): array
    {
        return Db::name('role')->column('name', 'code') ?: [];
    }
}
