[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'
$repo = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$adminPath = Join-Path $repo 'admin'
$allowedRootDuplicates = @('config.php', 'gemini.php', 'monitor_lib.php', '_menu.php', 'aovivo.php', 'index.php', 'noticias.php', 'videos.php')
$legacyRootFiles = @('cron_monitor.php', 'cron_radar.php')
$requiredBootstrap = @('config/runtime.php', 'config.php', 'admin/config.php', 'bootstrap/admin.php')

$adminNames = @(Get-ChildItem -LiteralPath $adminPath -File -Filter '*.php' | Select-Object -ExpandProperty Name)
$rootDuplicates = @(Get-ChildItem -LiteralPath $repo -File -Filter '*.php' |
    Where-Object { $adminNames -contains $_.Name -and $allowedRootDuplicates -notcontains $_.Name })
$legacyFiles = @($legacyRootFiles | ForEach-Object {
    $path = Join-Path $repo $_
    if (Test-Path -LiteralPath $path) { Get-Item -LiteralPath $path }
})

if ($rootDuplicates.Count -gt 0) {
    $names = $rootDuplicates.Name -join ', '
    throw "Módulos administrativos duplicados na raiz: $names"
}
$missingBootstrap = @($requiredBootstrap | Where-Object {
    -not (Test-Path -LiteralPath (Join-Path $repo $_))
})
if ($missingBootstrap.Count -gt 0) {
    throw "Bootstrap ausente: $($missingBootstrap -join ', ')"
}
if ($legacyFiles.Count -gt 0) {
    $names = $legacyFiles.Name -join ', '
    throw "Cron legada na raiz: $names"
}

$php = Get-Command php -ErrorAction SilentlyContinue
$docker = Get-Command docker -ErrorAction SilentlyContinue
Write-Output "STRUCTURE=PASS"
Write-Output "ROOT_ADMIN_DUPLICATES=0"
Write-Output "LEGACY_ROOT_CRONS=0"
Write-Output "BOOTSTRAP_CONFIG=PASS"
Write-Output ("PHP_AVAILABLE=" + [bool]$php)
Write-Output ("DOCKER_AVAILABLE=" + [bool]$docker)

if ($php) {
    $failures = @()
    Get-ChildItem -LiteralPath $repo -Recurse -File -Filter '*.php' |
        Where-Object { $_.FullName -notmatch '[\\/]vendor[\\/]' } |
        ForEach-Object {
            & $php.Source -l $_.FullName *> $null
            if ($LASTEXITCODE -ne 0) { $failures += $_.FullName }
        }
    if ($failures.Count -gt 0) {
        throw "PHP_LINT=FAIL: $($failures -join ', ')"
    }
    Write-Output 'PHP_LINT=PASS'
} else {
    Write-Output 'PHP_LINT=SKIP (php não instalado)'
}
