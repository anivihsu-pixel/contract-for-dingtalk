-- migration_v2.40.0_customer_industry.sql
-- 客户行业字段 + 生命周期漏斗金额维度（P1-7）
-- 说明：
--   - customer 增加 industry（GOV 政府单位/REAL_ESTATE 房地产/FOOD_TOURISM 餐饮旅游/OTHER 其他），
--     供客户档案分类（公司主业：政府/企事业单位活动外包、房地产/餐饮旅游广告策划）；
--   - 新增 dict_customer_industry 字典（表单下拉 + 列表/详情展示）；
--   - 生命周期漏斗（lifecycleFunnel）返回各阶段客户的销售合同金额合计（amounts），
--     前端在漏斗卡片上展示「N 户 · ¥金额」。
--
-- 注意：SQLite（演示库）不支持 COMMENT 子句，此处仅做列新增；字段注释见 init.sql。

ALTER TABLE `customer`
    ADD COLUMN `industry` VARCHAR(32) DEFAULT '' COMMENT '行业(GOV/REAL_ESTATE/FOOD_TOURISM/OTHER)';  -- 行业(GOV/REAL_ESTATE/FOOD_TOURISM/OTHER)

INSERT INTO `system_config` (`config_key`, `config_value`, `group_name`)
SELECT 'dict_customer_industry', '{"GOV":"政府单位","REAL_ESTATE":"房地产","FOOD_TOURISM":"餐饮旅游","OTHER":"其他"}', 'dict'
WHERE NOT EXISTS (SELECT 1 FROM `system_config` WHERE `config_key` = 'dict_customer_industry');

-- 检查
SELECT id, name, industry FROM `customer` ORDER BY id DESC LIMIT 5;
SELECT config_key, config_value FROM `system_config` WHERE `config_key` = 'dict_customer_industry';
