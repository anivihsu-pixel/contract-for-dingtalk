param([string]$Gate = "all")

$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$ProjectRoot = Split-Path -Parent $ScriptDir
Set-Location $ProjectRoot

$phpPath = "C:\Users\wow\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.4_Microsoft.Winget.Source_8wekyb3d8bbwe"
$pyPath = "C:\Users\wow\.workbuddy\binaries\python\versions\3.13.12"
if ($env:PATH -notlike "*$phpPath*") { $env:PATH = "$phpPath;$env:PATH" }
if ($env:PATH -notlike "*$pyPath*") { $env:PATH = "$pyPath;$env:PATH" }

$GateScripts = [ordered]@{
    "schema_parity" = "check_schema_parity.sh"
    "db_comments"   = "check_db_comments.sh"
    "view_globals"  = "check_view_globals.sh"
    "frontend"      = "check_frontend.sh"
    "dead_entry"    = "check_dead_entry.sh"
}

function Invoke-GateScript {
    param([string]$ScriptPath)
    $content = Get-Content $ScriptPath -Raw -Encoding UTF8
    $lines = $content -split "`n"
    $inHer = $false
    $marker = ""
    $py = @()
    foreach ($l in $lines) {
        if (-not $inHer) {
            if ($l -match "python3?\s*-\s*<<['""]?(\w+)['""]?") {
                $inHer = $true
                $marker = $Matches[1]
                $py = @()
            }
        } else {
            if ($l.Trim() -eq $marker) {
                $inHer = $false
                break
            } else {
                $py += $l
            }
        }
    }
    $tempFile = [System.IO.Path]::GetTempFileName() + ".py"
    $pyCode = $py -join "`n"
    [System.IO.File]::WriteAllText($tempFile, $pyCode, [System.Text.UTF8Encoding]::new($false))
    $output = & python3 $tempFile 2>&1
    $exitCode = $LASTEXITCODE
    $output | ForEach-Object { Write-Host $_ }
    Remove-Item $tempFile -Force -ErrorAction SilentlyContinue
    return $exitCode
}

function Invoke-PhpUnit {
    Write-Host ""
    Write-Host "== PHPUnit ==" -ForegroundColor Cyan
    if (Test-Path "phpunit.phar") {
        & php phpunit.phar --configuration phpunit.xml.dist 2>&1 | ForEach-Object { Write-Host $_ }
    } elseif (Test-Path "vendor/bin/phpunit") {
        & php vendor/bin/phpunit --configuration phpunit.xml.dist 2>&1 | ForEach-Object { Write-Host $_ }
    } else {
        Write-Host "[ERROR] PHPUnit not found" -ForegroundColor Red
        return 1
    }
    return $LASTEXITCODE
}

$totalFail = 0
$runAll = ($Gate -eq "all")
$runTest = $runAll -or ($Gate -eq "test")

if ($GateScripts.Contains($Gate)) {
    $script = $GateScripts[$Gate]
    $scriptPath = Join-Path $ScriptDir $script
    if (Test-Path $scriptPath) {
        Write-Host "" -ForegroundColor Cyan
        Write-Host "== Gate: $Gate ==" -ForegroundColor Cyan
        $code = Invoke-GateScript -ScriptPath $scriptPath
        if ($code -ne 0) { $totalFail++ }
    }
} elseif ($runAll) {
    foreach ($kv in $GateScripts.GetEnumerator()) {
        $name = $kv.Key
        $script = $kv.Value
        $scriptPath = Join-Path $ScriptDir $script
        if (Test-Path $scriptPath) {
            Write-Host "" -ForegroundColor Cyan
            Write-Host "== Gate: $name ==" -ForegroundColor Cyan
            $code = Invoke-GateScript -ScriptPath $scriptPath
            if ($code -ne 0) { $totalFail++ }
        }
    }
} elseif ($Gate -ne "test") {
    Write-Host "Unknown gate: $Gate" -ForegroundColor Red
    Write-Host "Available: $($GateScripts.Keys -join ', '), test, all"
    exit 1
}

if ($runTest) {
    $code = Invoke-PhpUnit
    if ($code -ne 0) { $totalFail++ }
}

Write-Host ""
if ($totalFail -eq 0) {
    Write-Host "All gates passed" -ForegroundColor Green
} else {
    Write-Host "$totalFail gate(s) failed" -ForegroundColor Red
}
exit $totalFail