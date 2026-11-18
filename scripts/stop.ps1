. "$PSScriptRoot\common.ps1"
$settings = Load-LabSettings
Stop-LabProcess 'apache'
if (Test-LabPort 3307) { & $settings.mysqlAdmin "--defaults-extra-file=$($settings.state)\mysql-root.cnf" shutdown }
Stop-LabProcess 'php'
Write-Host 'Local laboratory servers stopped. Database files were preserved.'
