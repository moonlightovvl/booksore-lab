param([Parameter(Mandatory=$true)][string]$SqlFile)
. "$PSScriptRoot\common.ps1"
$settings = Load-LabSettings
Set-Location -LiteralPath $ProjectRoot
& $settings.php -c $settings.phpIni "$ProjectRoot\scripts\sql.php" $SqlFile
if ($LASTEXITCODE -ne 0) { throw 'SQL failed.' }
