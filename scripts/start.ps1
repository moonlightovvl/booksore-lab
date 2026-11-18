param([switch]$NoBrowser, [switch]$KeepAlive)
. "$PSScriptRoot\common.ps1"
$settings = Load-LabSettings
$env:PATH = "$(Split-Path -Parent $settings.php);$(Split-Path -Parent $settings.mysql);$(Split-Path -Parent $settings.apache);$env:PATH"
if (!(Test-LabPort 3307)) {
    $process = Start-LabProcess 'mysql' $settings.mysql @(('"--defaults-file=' + $settings.mysqlConfig + '"'), '--console') (Split-Path -Parent $settings.mysql)
    Wait-LabPort 3307
}
if (!(Test-LabPort 9000)) {
    $env:PHP_FCGI_MAX_REQUESTS = '0'
    $process = Start-LabProcess 'php' $settings.phpCgi @('-c', ('"' + $settings.phpIni + '"'), '-b', '127.0.0.1:9000') (Split-Path -Parent $settings.phpCgi)
    Wait-LabPort 9000
}
if (!(Test-LabPort 8080)) {
    & $settings.apache -t -f $settings.apacheConfig
    if ($LASTEXITCODE -ne 0) { throw 'Apache configuration test failed.' }
    $process = Start-LabProcess 'apache' $settings.apache @('-f', ('"' + $settings.apacheConfig + '"')) (Split-Path -Parent $settings.apache)
    Wait-LabPort 8080
}
$response = Invoke-WebRequest -Uri 'http://127.0.0.1:8080/catalog.php' -UseBasicParsing
if ($response.StatusCode -ne 200 -or $response.Content -notmatch 'data-book-id') { throw 'The catalog database check failed. See logs.' }
Write-Host 'CICADA: http://127.0.0.1:8080/'
Write-Host 'Database: http://127.0.0.1:8080/database.php'
if (!$NoBrowser) { Start-Process 'http://127.0.0.1:8080/' }
if ($KeepAlive) { while ($true) { Start-Sleep -Seconds 30 } }
