param([int]$Port = 8099, [switch]$NoScheduler)
$ErrorActionPreference = 'Stop'
$taskRoot = Split-Path $PSScriptRoot -Parent
$taskPhp = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe'
$taskRouter = Join-Path $taskRoot 'application/vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php'
$taskApplication = Join-Path $taskRoot 'application'
$taskScheduler = $null
if (-not $NoScheduler) {
    Push-Location $taskApplication
    try {
        & $taskPhp artisan isms:remind-treatments --no-interaction
        if ($LASTEXITCODE -ne 0) { throw 'Peringatan gagal. Semak MySQL dan jalankan migrasi sebelum memulakan sistem.' }
        $taskScheduler = Start-Process -FilePath $taskPhp -ArgumentList 'artisan','schedule:work','--no-interaction' -WorkingDirectory $taskApplication -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $taskApplication 'storage/logs/scheduler-output.log') -RedirectStandardError (Join-Path $taskApplication 'storage/logs/scheduler-error.log')
    }
    finally { Pop-Location }
}
Push-Location (Join-Path $taskRoot 'application/public')
try { & $taskPhp -d upload_max_filesize=10M -d post_max_size=12M -S "127.0.0.1:$Port" $taskRouter }
finally {
    Pop-Location
    if ($taskScheduler -and -not $taskScheduler.HasExited) { Stop-Process -Id $taskScheduler.Id -ErrorAction SilentlyContinue }
}
