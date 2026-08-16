#!/usr/bin/env bash
# 生产定时任务统一入口：记录最近状态，并在失败时通知管理员。
set -u
cd "$(dirname "$0")/.."

if [ "$#" -lt 1 ]; then
  echo "用法: bash scripts/run_task.sh <think-command> [arguments...]" >&2
  exit 2
fi

task="$1"
status_dir="runtime/task-status"
mkdir -p "$status_dir"
safe_task="$(printf '%s' "$task" | tr -c 'A-Za-z0-9_.-' '_')"
status_file="$status_dir/$safe_task.json"
log_file="$status_dir/$safe_task.log"
started_at="$(date -u +%Y-%m-%dT%H:%M:%SZ)"
previous_failures="$(STATUS_FILE="$status_file" php -r '$f=getenv("STATUS_FILE");$d=is_file($f)?json_decode(file_get_contents($f),true):[];echo (int)($d["consecutive_failures"]??0);')"

php think "$@" >"$log_file" 2>&1
exit_code=$?
finished_at="$(date -u +%Y-%m-%dT%H:%M:%SZ)"
if [ "$exit_code" -eq 0 ]; then consecutive_failures=0; else consecutive_failures=$((previous_failures + 1)); fi

TASK_NAME="$task" STARTED_AT="$started_at" FINISHED_AT="$finished_at" EXIT_CODE="$exit_code" FAILURES="$consecutive_failures" STATUS_FILE="$status_file" php -r '
$data = ["task" => getenv("TASK_NAME"), "started_at" => getenv("STARTED_AT"), "finished_at" => getenv("FINISHED_AT"), "exit_code" => (int)getenv("EXIT_CODE"), "ok" => getenv("EXIT_CODE") === "0", "consecutive_failures" => (int)getenv("FAILURES")];
file_put_contents(getenv("STATUS_FILE") . ".tmp", json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
rename(getenv("STATUS_FILE") . ".tmp", getenv("STATUS_FILE"));
'

cat "$log_file"
if [ "$exit_code" -ne 0 ]; then
  summary="$(tail -n 5 "$log_file" | tr '\n' ' ' | cut -c1-500)"
  php think ops:alert "$task" "$summary" "$consecutive_failures" || true
fi
exit "$exit_code"
