-- v2.51.9：移除无业务使用的字典（存量库升级执行本文件，幂等可重复执行）
-- 依据：全项目检索确认以下字典已无任何 dict()/dict_options()/dict_enabled() 消费——
--   dict_tax_rate        税率：发票申请税率已绑定开票主体 company_profile.invoice_tax_rate（2026-08-02 起表单税率字段停用）
--   dict_invoice_status  发票状态：label 由视图硬编码状态映射渲染（invoice_apply/approval_invoice_detail 等）
--   dict_payment_status  回款状态：label 由视图硬编码状态映射渲染（customer/detail、mobile/contract_detail 等）
--   dict_data_scope      数据权限范围：label 由角色管理页硬编码 $scopes 渲染，业务读 role.data_scope 字段
-- 同时清理测试遗留的 dict_disabled_tax_rate 停用元数据行。
DELETE FROM system_config WHERE config_key IN (
  'dict_tax_rate',
  'dict_invoice_status',
  'dict_payment_status',
  'dict_data_scope',
  'dict_disabled_tax_rate'
);
