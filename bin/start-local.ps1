$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
$phpExecutable = 'C:\xampp\php\php.exe'
$mysqlExecutable = 'C:\xampp\mysql\bin\mysqld.exe'
if (!(Test-Path -LiteralPath "$projectRoot\config\config.php")) { throw 'Complete README setup first.' }
if (!(Get-NetTCPConnection -LocalPort 3307 -State Listen -ErrorAction SilentlyContinue)) {
    if (!(Test-Path -LiteralPath "$projectRoot\storage\database\my.ini")) { throw 'Local demonstration database is missing. Follow README for your MySQL server.' }
    Start-Process -FilePath $mysqlExecutable -ArgumentList "--defaults-file=`"$projectRoot\storage\database\my.ini`" --bind-address=127.0.0.1 --port=3307 --console" -WindowStyle Hidden -RedirectStandardOutput "$projectRoot\storage\runtime\mysql.out.log" -RedirectStandardError "$projectRoot\storage\runtime\mysql.err.log"
}
if (!(Get-NetTCPConnection -LocalPort 8080 -State Listen -ErrorAction SilentlyContinue)) {
    Start-Process -FilePath $phpExecutable -ArgumentList '-d extension=gd -S 127.0.0.1:8080 -t public public/router.php' -WorkingDirectory $projectRoot -WindowStyle Hidden -RedirectStandardOutput "$projectRoot\storage\runtime\php.out.log" -RedirectStandardError "$projectRoot\storage\runtime\php.err.log"
}
Write-Output 'WIKI local preview: http://127.0.0.1:8080'
