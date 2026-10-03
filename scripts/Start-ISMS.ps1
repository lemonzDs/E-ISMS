param([int]$Port = 8099)
$ErrorActionPreference = 'Stop'
$taskRoot = Split-Path $PSScriptRoot -Parent
$taskPhp = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe'
$taskRouter = Join-Path $taskRoot 'application/vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php'
Push-Location (Join-Path $taskRoot 'application/public')
try { & $taskPhp -d upload_max_filesize=10M -d post_max_size=12M -S "127.0.0.1:$Port" $taskRouter }
finally { Pop-Location }
