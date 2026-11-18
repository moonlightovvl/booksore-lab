param([string]$OpenServerRoot = 'C:\OSPanel')
. "$PSScriptRoot\common.ps1"
if (!(Test-Path -LiteralPath (Join-Path $OpenServerRoot 'modules'))) {
    throw 'OpenServer is missing. Install Apache, PHP 8.4 and MySQL 8.4 into C:\OSPanel first.'
}
$php = Find-LabBinary (Join-Path $OpenServerRoot 'modules\PHP-8.4') 'php.exe'
$phpCgi = Find-LabBinary (Join-Path $OpenServerRoot 'modules\PHP-8.4') 'php-cgi.exe'
$apache = Find-LabBinary (Join-Path $OpenServerRoot 'modules\Apache') 'httpd.exe'
$mysql = Find-LabBinary (Join-Path $OpenServerRoot 'modules\MySQL-8.4') 'mysqld.exe'
$mysqlClient = Find-LabBinary (Join-Path $OpenServerRoot 'modules\MySQL-8.4') 'mysql.exe'
$mysqlAdmin = Find-LabBinary (Join-Path $OpenServerRoot 'modules\MySQL-8.4') 'mysqladmin.exe'
$state = Join-Path $OpenServerRoot 'cicada-lab4-runtime'
foreach ($dir in @($state, "$state\logs", "$state\sessions", "$ProjectRoot\runtime", "$ProjectRoot\runtime\logs")) {
    New-Item -ItemType Directory -Force -Path $dir | Out-Null
}
# ASCII-only junction avoids Windows server tools having problems with Cyrillic paths.
$site = Join-Path $OpenServerRoot 'home\cicada-lab4'
if (!(Test-Path -LiteralPath $site)) {
    New-Item -ItemType Junction -Path $site -Target (Join-Path $ProjectRoot 'app') | Out-Null
} elseif ((Get-Item -LiteralPath $site).Target -ne (Join-Path $ProjectRoot 'app')) {
    throw "Another project already uses $site. It was not overwritten."
}
$secretsFile = Join-Path $state 'secrets.json'
if (Test-Path -LiteralPath $secretsFile) {
    $secrets = Get-Content -LiteralPath $secretsFile -Raw -Encoding UTF8 | ConvertFrom-Json
} else {
    $secrets = @{ root = [Guid]::NewGuid().ToString('N'); app = [Guid]::NewGuid().ToString('N') }
    Write-Utf8 $secretsFile ($secrets | ConvertTo-Json)
}
$phpDir = Split-Path -Parent $php
$apacheRoot = Split-Path -Parent (Split-Path -Parent $apache)
$mysqlRoot = Split-Path -Parent (Split-Path -Parent $mysql)
$settings = @{ openServerRoot = $OpenServerRoot; state = $state; php = $php; phpCgi = $phpCgi; apache = $apache; mysql = $mysql; mysqlClient = $mysqlClient; mysqlAdmin = $mysqlAdmin; phpIni = "$state\php.ini"; apacheConfig = "$state\httpd.conf"; mysqlConfig = "$state\my.ini"; site = $site; project = $ProjectRoot }
Write-Utf8 "$ProjectRoot\runtime\settings.json" ($settings | ConvertTo-Json)
function Forward([string]$Path) { return $Path.Replace('\', '/') }
$s = Forward $state
$p = Forward $phpDir
$a = Forward $apacheRoot
$m = Forward $mysqlRoot
$doc = (Forward $site) + '/public'
Write-Utf8 "$state\php.ini" @"
[PHP]
extension_dir="$p/ext"
extension=pdo_mysql
extension=mbstring
date.timezone=Europe/Astrakhan
default_charset=UTF-8
display_errors=Off
log_errors=On
error_log="$s/logs/php-error.log"
session.save_path="$s/sessions"
session.use_only_cookies=1
session.cookie_httponly=1
session.cookie_samesite=Lax
expose_php=Off
cgi.fix_pathinfo=0
opcache.enable=0
"@
Write-Utf8 "$state\httpd.conf" @"
ServerRoot "$a"
Listen 127.0.0.1:8080
ServerName 127.0.0.1:8080
PidFile "$s/apache.pid"
LoadModule authz_core_module modules/mod_authz_core.so
LoadModule authz_host_module modules/mod_authz_host.so
LoadModule dir_module modules/mod_dir.so
LoadModule mime_module modules/mod_mime.so
LoadModule log_config_module modules/mod_log_config.so
LoadModule proxy_module modules/mod_proxy.so
LoadModule proxy_fcgi_module modules/mod_proxy_fcgi.so
DocumentRoot "$doc"
<Directory />
    AllowOverride None
    Require all denied
</Directory>
<Directory "$doc">
    Options -Indexes
    AllowOverride None
    Require local
    DirectoryIndex index.php
</Directory>
<FilesMatch "\.php$">
    SetHandler "proxy:fcgi://127.0.0.1:9000/"
    ProxyFCGIBackendType GENERIC
    ProxyFCGISetEnvIf "true" SCRIPT_FILENAME "$doc%{reqenv:SCRIPT_NAME}"
</FilesMatch>
TypesConfig "$a/conf/mime.types"
ErrorLog "$s/logs/apache-error.log"
LogFormat "%h %l %u %t %r %>s %b" common
CustomLog "$s/logs/apache-access.log" common
ServerTokens Prod
ServerSignature Off
"@
Write-Utf8 "$state\my.ini" @"
[mysqld]
basedir="$m"
datadir="$s/mysql-data"
port=3307
bind-address=127.0.0.1
mysqlx=0
skip-name-resolve=ON
default-time-zone=+04:00
character-set-server=utf8mb4
collation-server=utf8mb4_unicode_ci
log-error="$s/logs/mysql-error.log"
pid-file="$s/mysql.pid"
innodb-buffer-pool-size=128M
[client]
host=127.0.0.1
port=3307
default-character-set=utf8mb4
"@
Write-Utf8 "$state\mysql-root.cnf" @"
[client]
host=127.0.0.1
port=3307
user=root
password=$($secrets.root)
default-character-set=utf8mb4
"@
Write-Utf8 "$ProjectRoot\app\config.local.php" @"
<?php
return ['host'=>'127.0.0.1','port'=>3307,'database'=>'cicada_lab4','user'=>'cicada','password'=>'$($secrets.app)'];
"@
$env:PATH = "$phpDir;$(Split-Path -Parent $mysql);$(Split-Path -Parent $apache);$env:PATH"
$bootstrapSql = Join-Path $state 'bootstrap-root.sql'
if (!(Test-Path -LiteralPath "$state\mysql-data\auto.cnf")) {
    if (Test-LabPort 3307) { throw 'Port 3307 is already occupied. Existing server was not changed.' }
    & $mysql "--defaults-file=$state\my.ini" --initialize-insecure --console
    if ($LASTEXITCODE -ne 0) { throw 'MySQL initialization failed.' }
    # With skip-name-resolve enabled, TCP 127.0.0.1 needs its own root account.
    # MySQL reads this private file once, before accepting client connections.
    Write-Utf8 $bootstrapSql @"
ALTER USER 'root'@'localhost' IDENTIFIED BY '$($secrets.root)';
CREATE USER IF NOT EXISTS 'root'@'127.0.0.1' IDENTIFIED BY '$($secrets.root)';
ALTER USER 'root'@'127.0.0.1' IDENTIFIED BY '$($secrets.root)';
GRANT ALL PRIVILEGES ON *.* TO 'root'@'127.0.0.1' WITH GRANT OPTION;
"@
}
if (!(Test-LabPort 3307)) {
    $mysqlArguments = @(('"--defaults-file=' + "$state\my.ini" + '"'), '--console')
    if (Test-Path -LiteralPath $bootstrapSql) { $mysqlArguments += ('"--init-file=' + $bootstrapSql + '"') }
    $process = Start-LabProcess 'mysql' $mysql $mysqlArguments (Split-Path -Parent $mysql)
    Wait-LabPort 3307
}
$env:CICADA_MYSQL_ROOT_PASSWORD = $secrets.root
try {
    & $php -c "$state\php.ini" "$ProjectRoot\scripts\init.php"
    if ($LASTEXITCODE -ne 0) { throw 'Database setup failed.' }
    if (Test-Path -LiteralPath $bootstrapSql) { Remove-Item -LiteralPath $bootstrapSql }
} finally { Remove-Item Env:CICADA_MYSQL_ROOT_PASSWORD -ErrorAction SilentlyContinue }
Write-Host 'Setup complete. Run START-LAB.cmd.'
