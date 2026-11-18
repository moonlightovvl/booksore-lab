. "$PSScriptRoot\common.ps1"
$settings = Load-LabSettings
& $settings.mysqlClient "--defaults-extra-file=$($settings.state)\mysql-root.cnf" cicada_lab4
