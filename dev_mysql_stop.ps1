# 本地开发 MySQL 一键停止（3307 端口）
# 用法：powershell -ExecutionPolicy Bypass -File dev_mysql_stop.ps1
$ErrorActionPreference = 'SilentlyContinue'
$bin  = 'C:\Users\wow\.local\tools\mysql-8.4.9-winx64\bin'

$conn = Get-NetTCPConnection -LocalPort 3307 -State Listen | Select-Object -First 1
if (-not $conn) {
    Write-Host "[dev-mysql] 端口 3307 未监听，无需停止。" -ForegroundColor Yellow
    exit 0
}

# 优雅停机（root 空密码）
& "$bin\mysqladmin.exe" --host=127.0.0.1 --port=3307 --user=root shutdown | Out-Null
Start-Sleep -Milliseconds 800

# 兜底：仍存活则按进程终止（仅 3307 对应的 mysqld）
if (Get-NetTCPConnection -LocalPort 3307 -State Listen -ErrorAction SilentlyContinue) {
    Get-NetTCPConnection -LocalPort 3307 -State Listen | ForEach-Object {
        Stop-Process -Id $_.OwningProcess -Force
    }
}
Write-Host "[dev-mysql] 已停止（3307 端口已释放）。" -ForegroundColor Green
