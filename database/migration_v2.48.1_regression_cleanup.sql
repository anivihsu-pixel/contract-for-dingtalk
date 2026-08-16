-- v2.48.1 回归清理：移除失效版本配置、统一签署状态文案，并为漏迁移环境补发票审批流。
DELETE FROM `system_config` WHERE `config_key` = 'site_version';

UPDATE `system_config`
SET `config_value` = JSON_SET(`config_value`, '$.SIGNED', '已签署'),
    `updated_at` = NOW()
WHERE `config_key` = 'dict_contract_status' AND JSON_VALID(`config_value`);

INSERT INTO `approval_flow`
(`name`, `code`, `min_amount`, `max_amount`, `use_amount`, `nodes`, `cc_list`, `biz_type`, `status`, `creator_id`)
SELECT '发票审批', 'INVOICE', 0, 99999999.99, 0,
       '[{"name":"财务审批","type":"ROLE","role_code":"finance","mode":"OR"}]',
       '{"role_codes":[],"cc_user_ids":[]}', 'invoice', 1, 1
WHERE NOT EXISTS (SELECT 1 FROM `approval_flow` WHERE `code` = 'INVOICE');

UPDATE `approval_flow` SET `biz_type` = 'invoice' WHERE `code` = 'INVOICE';
