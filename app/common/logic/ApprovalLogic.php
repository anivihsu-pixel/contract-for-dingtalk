<?php
// +----------------------------------------------------------------------
// | 审批节点常量定义（v2.38.1 拆分后保留）
// | 业务方法已移至：ApprovalSubmitService / ApprovalActionService /
// |                 ApprovalNotifyService / ApprovalQueryService
// +----------------------------------------------------------------------

namespace app\common\logic;

class ApprovalLogic
{
    /**
     * 审批节点类型 / 模式常量（CR-51：消除魔术字符串，便于维护与防止拼写错误）
     */
    // 质量修复：NODE_START/NODE_END 为拆分遗留死代码（全库零引用），已删除；保留在用常量
    public const NODE_CC    = 'CC';
    public const MODE_AND   = 'AND';
    public const MODE_OR    = 'OR';
}
