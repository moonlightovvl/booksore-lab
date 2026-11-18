. "$PSScriptRoot\common.ps1"
$settings = Load-LabSettings
$dump = Find-LabBinary (Join-Path $settings.openServerRoot 'modules\MySQL-8.4') 'mysqldump.exe'
$folder = Join-Path $ProjectRoot 'runtime\backups'
New-Item -ItemType Directory -Force -Path $folder | Out-Null
$output = Join-Path $folder ('cicada_lab4_' + (Get-Date -Format 'yyyyMMdd_HHmmss') + '.sql')
& $dump "--defaults-extra-file=$($settings.state)\mysql-root.cnf" --single-transaction --default-character-set=utf8mb4 --databases cicada_lab4 "--result-file=$output"
if ($LASTEXITCODE -ne 0) { throw 'Database backup failed.' }
Write-Host "Saved database backup: $output"
