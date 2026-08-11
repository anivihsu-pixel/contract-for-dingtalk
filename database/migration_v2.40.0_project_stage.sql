-- migration_v2.40.0_project_stage.sql
-- 项目执行阶段 + 进度 + 验收联动（P1-6）
-- 说明：
--   - project 增加 stage（PLANNING/EXECUTING/ACCEPTANCE/COMPLETED）与 progress（0-100）；
--   - 「标记验收完成」（/ajax/project/accept）联动：项目下执行中/已通过的销售合同置 COMPLETED，
--     并提示待收尾款金额。

ALTER TABLE `project`
    ADD COLUMN `stage` VARCHAR(32) DEFAULT 'PLANNING' COMMENT '执行阶段(PLANNING/EXECUTING/ACCEPTANCE/COMPLETED)' AFTER `status`,  -- 执行阶段(PLANNING/EXECUTING/ACCEPTANCE/COMPLETED)
    ADD COLUMN `progress` INT DEFAULT 0 COMMENT '执行进度(%)' AFTER `stage`;  -- 执行进度(%)

-- 检查
SELECT id, name, stage, progress FROM `project` ORDER BY id DESC LIMIT 5;
