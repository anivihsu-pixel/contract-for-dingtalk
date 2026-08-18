-- v2.51.10：contract 表新增 invoice_intent 字段（随合同申请开票意图，存量库升级执行本文件，幂等可重复执行）
-- 用途：提交合同审批时可勾选「随合同申请开票」，信息以 JSON 存于该字段；合同过审进入执行后，
--       自动生成一张「待开票(APPROVED)」发票并通知配置的开票确认人（财务），随后清空该字段。
--       结构：{"apply":1,"our_company_id":1,"invoice_type":"VAT_SPECIAL","content_desc":"软件开发服务费",
--              "amount":100000,"invoice_title":"对方公司","tax_no":"91330...","remark":""}
-- 无 DB 逆操作（回滚：DROP COLUMN invoice_intent）
ALTER TABLE contract
  ADD COLUMN `invoice_intent` TEXT DEFAULT NULL COMMENT '随合同申请开票意图JSON(v2.51.10)'
  AFTER `renewed_from`;
