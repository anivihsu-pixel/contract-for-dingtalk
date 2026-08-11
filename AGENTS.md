# AGENTS.md — 合同管理系统（Contract Management System）

> 本文件是给 AI 编程助手（Trae / WorkBuddy 等）的项目上下文约定。任何 AI 在本仓库工作时，请**先读此文件**，并以其中信息为准，避免误用旧副本或猜版本。

## 项目概览
- 合同管理系统（Contract Management System）：基于 ThinkPHP 的 PHP 单体应用。
- 入口：`think`；依赖管理：`composer`；无 git 仓库（版本靠 `VERSION.md` + 打包产物追踪）。
- 技术栈：PHP / ThinkPHP、MySQL 或 SQLite、前端原生 JS（含 jsdom 测试）、PHPUnit。

## 当前打包版本（权威）
- **v2.44.0**（2026-08-08 确认）
- 定义位置：`config/version.php` 第 5 行 `return 'v2.44.0';`
- 由 `scripts/release.sh` 打包时自动写入，优先级高于根目录 `VERSION.md`。
- 发布产物：`releases/contract-dingtalk-v2.44.0.tar.gz`（钉钉版）+ `releases/MANIFEST.txt`。

## 活动开发环境（唯一）
- **Trae（TRAE SOLO CN）** 是当前唯一活跃开发环境，工程路径：
  ```
  C:\Users\wow\AppData\Roaming\TRAE SOLO CN\ModularData\ai-agent\work-mode-projects\6a71e12c2400457f8e662997\contract-review-system-export\contract-review-system\contract-review
  ```
- 所有代码改动、打包、产物都在此路径进行。WorkBuddy 等外部工具如需操作，也**直接读写此路径**，不要碰下面的旧副本。

## 旧副本 / 交付包 —— 勿作事实来源（极易误用）
- ❌ `C:\Users\wow\WorkBuddy\contract-review-system\contract-review` → **v2.38.26**（落后约 6 个版本，已过时）
- ❌ `C:\Users\wow\WorkBuddy\十八腔合同管理系统\contract-review` → **v2.38.26**（同上，疑为同一份复制）
- ❌ 桌面 `合同管理系统_v2.4x.x`（v2.40.7 ~ v2.44.0）是独立交付/导出包，非开发源码；其中的 v2.44.0 仅版本号与 Trae 源码吻合，但不在源码树内。

## 两边分工建议（协同工作）
- **Trae**：功能开发、迭代、改代码。
- **WorkBuddy**：打包校验、版本/差异核对、报告与跨文件分析、脚本自动化。
- 共同前提：都操作上面的 Trae 路径；确认版本一律读 `config/version.php`。

## 常用操作
- 确认版本：读 `config/version.php`（不要凭桌面文件夹名或旧副本推断）。
- 重新打包：`./scripts/release.sh` → 产出 `releases/*.tar.gz` + 更新 `MANIFEST.txt`。
- 测试：PHPUnit（`phpunit.xml.dist`，`vendor/bin/phpunit`）；前端门禁 `scripts/check_*.sh`；jsdom / e2e 见根目录 `e2e_*.js`、`e2e_*.py`。

## 关键约定
- 改版本号只通过 `scripts/release.sh`，不要手改 `version.php` / `VERSION.md` 的版本行。
- 数据库迁移放 `database/migration_vX.Y.Z_*.sql`，与版本号对应。
- 桌面交付包与源码非同一分支，迁移/对齐改动请以 Trae 源码树为准。
