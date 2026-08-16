# 生产运行基线

## 上线前门禁

部署脚本会在切换流量前自动执行 `php think system:check`。任何数据库、密钥、HTTPS Cookie、钉钉配置或最近备份检查失败，部署都会停止，旧版本继续运行。

数据库迁移由 `schema_migration` 台账记录，已执行脚本不会重复运行，且历史脚本内容发生变化时会阻止部署。首次使用新部署脚本接管已有旧库、但服务器没有 `current` 版本链接时，必须先设置 `MIGRATION_BASELINE_VERSION=v旧版本号`；全新数据库应先用发布包内 `database/init_mysql.php` 初始化，脚本会按目标版本建立基线。

监控系统应每分钟访问 `GET /health`：HTTP 200 表示数据库和运行目录正常，HTTP 503 表示服务降级。接口不会返回数据库地址、错误堆栈或密钥。

## 定时任务

所有 crontab 任务必须经统一包装器运行，例如：

```cron
0 2 * * * cd /srv/contract/current && bash scripts/run_task.sh db:backup --keep=30
5 0 * * * cd /srv/contract/current && bash scripts/run_task.sh payment:mark-overdue
10 0 * * * cd /srv/contract/current && bash scripts/run_task.sh contract:expire
20 0 * * * cd /srv/contract/current && bash scripts/run_task.sh customer:credit-check
0 3 * * * cd /srv/contract/current && bash scripts/run_task.sh customer:pool-release
*/30 * * * * cd /srv/contract/current && bash scripts/run_task.sh approval:sla-check
*/10 * * * * cd /srv/contract/current && bash scripts/run_task.sh remind:dispatch
0 8 * * 1 cd /srv/contract/current && bash scripts/run_task.sh report:weekly
```

运行结果和连续失败次数保存在 `runtime/task-status/`。首次失败即向启用状态的管理员发送站内信；连续失败达到 3 次后追加钉钉工作通知；任务成功后连续失败次数清零。

`remind:dispatch` 同时处理合同到期、回款到期/逾期和客户计划跟进。客户跟进按填写的精确时间生效，因此建议保持每 10 分钟调度；每个客户仅最新一条跟进计划有效，避免旧计划重复提醒。

`payment:mark-overdue`、`contract:expire`、`customer:credit-check`、`customer:pool-release` 分别维护回款逾期、合同到期、客户信用和公海候选扫描（须经理确认，不自动释放）；`approval:sla-check` 每 30 分钟处理审批超时提醒与升级。以上任务不可省略，否则页面数据会逐步偏离实际业务状态。

数据库每日备份保留 30 份。异地加密备份需在云存储凭据确认后接入；每季度应在隔离环境恢复最新备份并记录演练结果。
