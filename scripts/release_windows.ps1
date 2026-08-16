param(
    [switch]$Force,
    [string]$OutputDir = (Join-Path (Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)) 'releases')
)

$ErrorActionPreference = 'Stop'

$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$ProjectRoot = Split-Path -Parent $ScriptDir
Set-Location $ProjectRoot

$versionMatch = Select-String -Path 'VERSION.md' -Pattern '当前版本：(?<v>v[0-9]+\.[0-9]+(\.[0-9]+)?)' | Select-Object -First 1
if (-not $versionMatch) {
    throw '无法从 VERSION.md 识别版本号'
}

$version = $versionMatch.Matches[0].Groups['v'].Value
$releaseDir = $OutputDir
$packageName = "contract-dingtalk-$version.tar.gz"
$packagePath = Join-Path $releaseDir $packageName
$manifestPath = Join-Path $releaseDir 'MANIFEST.txt'

New-Item -ItemType Directory -Force -Path $releaseDir | Out-Null

# 发布包运行时读取的版本号，必须随发布一起写入。
$versionFilePath = Join-Path $ProjectRoot 'config\version.php'
$versionFileContent = "<?php`nreturn '$version';`n"
$utf8NoBom = New-Object System.Text.UTF8Encoding($false)
[System.IO.File]::WriteAllText($versionFilePath, $versionFileContent, $utf8NoBom)

try {
    $gitCommit = (git rev-parse --short HEAD 2>$null)
} catch {
    $gitCommit = $null
}
if (-not $gitCommit) {
    $gitCommit = 'N/A'
}

if (Test-Path $packagePath) {
    Remove-Item $packagePath -Force
}

$tarArgs = @(
    '-czf', $packagePath,
    '--exclude=.git',
    '--exclude=.env',
    '--exclude=.env.bak',
    '--exclude=.env.bak2',
    '--exclude=.mysql-dev',
    '--exclude=runtime',
    '--exclude=releases',
    '--exclude=backups',
    '--exclude=node_modules',
    '--exclude=outputs',
    '--exclude=tests',
    '--exclude=e2e_*.py',
    '--exclude=e2e_*.js',
    '--exclude=watchdog.ps1',
    '--exclude=setup-firewall.bat',
    '--exclude=loginpage.html',
    '--exclude=package.json',
    '--exclude=package-lock.json',
    '--exclude=phpunit.xml.dist',
    '--exclude=demo.env.example',
    '--exclude=CHANGELOG.md',
    '--exclude=VERSION.md',
    '--exclude=DEPLOY.md',
    '--exclude=DEPLOY_ZERO_DOWNTIME.md',
    '--exclude=DINGTALK_SSO_GUIDE.md',
    '--exclude=AGENTS.md',
    '--exclude=UPGRADE_*.md',
    '--exclude=PM审查报告_*.md',
    '--exclude=TEST_REPORT_*.md',
    '--exclude=REVIEW_*.md',
    '--exclude=FIX_PLAN_*.md',
    '--exclude=DEV_PLAN_*.md',
    '--exclude=DESIGN_*.md',
    '--exclude=PRODUCT_REVIEW_*.md',
    '--exclude=PRODUCT_AUDIT_*.md',
    '--exclude=AUDIT_*.md',
    '--exclude=AUDIT_*.html',
    '--exclude=P2_VERIFICATION.md',
    '--exclude=DEPLOY_DEMO.md',
    '--exclude=README_DEMO.txt',
    '--exclude=合同类型',
    '--exclude=.phpunit.cache',
    '--exclude=phpunit.phar',
    '.'
)

& tar @tarArgs
if ($LASTEXITCODE -ne 0) {
    throw "tar 打包失败，退出码 $LASTEXITCODE"
}

$sha = (Get-FileHash -Algorithm SHA256 -LiteralPath $packagePath).Hash
$size = (Get-Item $packagePath).Length

$manifest = @(
    '----------------------------------------',
    "版本      : $version",
    ("打包时间  : {0}" -f (Get-Date -Format 'yyyy-MM-dd HH:mm:ss')),
    "文件      : $packageName",
    ("大小      : {0} bytes" -f $size),
    "SHA256    : $sha",
    "git commit: $gitCommit",
    '演示数据  : 未包含'
)

Set-Content -Encoding UTF8 -LiteralPath $manifestPath -Value $manifest

# 桌面交付目录：与 release.sh 保持同一规范（文件夹交付，不打 ZIP）。
$desktopDir = [Environment]::GetFolderPath('Desktop')
$deliveryDir = $null
if ($desktopDir -and (Test-Path -LiteralPath $desktopDir)) {
    $deliveryName = "合同管理系统_$version"
    $deliveryDir = Join-Path $desktopDir $deliveryName

    # 仅允许重建桌面下精确命名的本系统版本目录，避免误删其他路径。
    $desktopFull = [System.IO.Path]::GetFullPath($desktopDir).TrimEnd('\') + '\'
    $deliveryFull = [System.IO.Path]::GetFullPath($deliveryDir)
    if (-not $deliveryFull.StartsWith($desktopFull, [System.StringComparison]::OrdinalIgnoreCase) -or
        [System.IO.Path]::GetFileName($deliveryFull) -ne $deliveryName) {
        throw "桌面交付目录校验失败: $deliveryFull"
    }

    if (Test-Path -LiteralPath $deliveryDir) {
        Get-ChildItem -LiteralPath $deliveryDir -Force | Remove-Item -Recurse -Force
    } else {
        New-Item -ItemType Directory -Path $deliveryDir | Out-Null
    }

    Copy-Item -LiteralPath $packagePath -Destination $deliveryDir
    Copy-Item -LiteralPath $manifestPath -Destination $deliveryDir
    Copy-Item -LiteralPath (Join-Path $ProjectRoot 'CHANGELOG.md') -Destination (Join-Path $deliveryDir '迭代日志.md')
    Copy-Item -LiteralPath (Join-Path $ProjectRoot 'docs\PRODUCTION_OPERATIONS.md') -Destination (Join-Path $deliveryDir '生产运维说明.md')
    Copy-Item -LiteralPath (Join-Path $ProjectRoot 'VERSION.md') -Destination (Join-Path $deliveryDir '版本记录.md')
}

Write-Host "✓ 发布完成"
Write-Host "  包路径   : $packagePath"
Write-Host "  SHA256   : $sha"
Write-Host "  清单     : $manifestPath"
if ($deliveryDir) {
    Write-Host "  桌面交付 : $deliveryDir"
}
