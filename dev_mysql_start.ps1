# 本地开发 MySQL 一键启动（3307 端口 + 独立数据目录 data_dev）
# 与机器上其他工具隔离：不占用 3306、不注册服务、用完 stop 即释放。
# 用法：powershell -ExecutionPolicy Bypass -File dev_mysql_start.ps1
$ErrorActionPreference = 'Stop'
$bin   = 'C:\Users\wow\.local\tools\mysql-8.4.9-winx64\bin'
$data  = 'C:\Users\wow\.local\tools\mysql-8.4.9-winx64\data_dev'
$port  = 3307

# 已监听则直接提示退出（幂等）
if (Get-NetTCPConnection -LocalPort $port -State Listen -ErrorAction SilentlyContinue) {
    Write-Host "[dev-mysql] 端口 $port 已在监听，无需重复启动。" -ForegroundColor Yellow
    exit 0
}

# 数据目录未初始化则初始化（root 空密码，仅本机开发）
if (-not (Test-Path "$data\mysql")) {
    Write-Host "[dev-mysql] 初始化数据目录 ..." -ForegroundColor Cyan
    & "$bin\mysqld.exe" --initialize-insecure --datadir="$data" --console | Out-Null
    if ($LASTEXITCODE -ne 0) { Write-Host "[dev-mysql] 初始化失败" -ForegroundColor Red; exit 1 }
}

# 后台启动 mysqld（日志写入数据目录的 *.err）
Write-Host "[dev-mysql] 启动 mysqld (port=$port) ..." -ForegroundColor Cyan
Start-Process -FilePath "$bin\mysqld.exe" -ArgumentList "--datadir=$data","--port=$port" -WindowStyle Hidden

# 等待端口就绪（最长 30s）
$ok = $false
for ($i = 0; $i -lt 30; $i++) {
    Start-Sleep -Milliseconds 1000
    if (Get-NetTCPConnection -LocalPort $port -State Listen -ErrorAction SilentlyContinue) { $ok = $true; break }
}
if (-not $ok) {
    Write-Host "[dev-mysql] 启动超时，请查看 $data\*.err" -ForegroundColor Red
    exit 1
}
Write-Host "[dev-mysql] 已就绪：host=127.0.0.1 port=$port user=root pass=(空) db=contract_dingtalk" -ForegroundColor Green
