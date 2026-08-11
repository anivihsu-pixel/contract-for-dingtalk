<?php
// +----------------------------------------------------------------------
// | 审批节点执行器 — 节点推进相关的纯业务规则
// | 从 ApprovalLogic 安全拆分（P2-1，v2.35.3）
// +----------------------------------------------------------------------

namespace app\common\logic;

class ApprovalNodeExecutor
{
    /** 节点会签 / 或签模式常量（与 ApprovalLogic 保持一致；改动须三处同步） */
    public const MODE_AND = 'AND';
    public const MODE_OR  = 'OR';

    /**
     * 节点超时是否自动通过（REV-01 审批合规红线）
     *
     *  - 或签(OR)：超时自动通过（或签语义即「任一审批人通过即推进」，自动通过合规）
     *  - 会签(AND)：超时不自动放行（会签本意「全部审批人通过才放行」，自动通过会让部分
     *    审批人未实质审核即放行合同，属审批合规红线），改发催办通知等待人工处理。
     *
     * @param string|null $mode 节点 mode（缺省视为或签 OR）
     * @return bool true = 超时自动通过；false = 超时仅催办（不自动放行）
     */
    public static function shouldAutoApproveOnTimeout(?string $mode): bool
    {
        $mode = strtoupper((string)($mode ?? self::MODE_OR));
        return $mode !== self::MODE_AND;
    }
}
