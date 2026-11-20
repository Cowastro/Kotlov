# KOTLOV agent setup on the 1C server: asks for the site key and the password of the 1C user «kotlov-site»
# and keeps both encrypted with Windows DPAPI (this computer only) in agent.config next to agent.ps1.
#   setup.ps1                 write agent.config
#   setup.ps1 -InstallTask    also start agent.ps1 every 5 minutes from the Task Scheduler (as SYSTEM)
param([switch]$InstallTask)

$ErrorActionPreference = 'Stop'
$Root = Split-Path -Parent $MyInvocation.MyCommand.Path
$Utf8 = New-Object System.Text.UTF8Encoding($false)
Add-Type -AssemblyName System.Security

function Protect-Secret([string]$prompt) {
    $secure = Read-Host -AsSecureString $prompt
    $plain = [Runtime.InteropServices.Marshal]::PtrToStringAuto([Runtime.InteropServices.Marshal]::SecureStringToBSTR($secure))
    if (-not $plain) { throw ('Пусто: ' + $prompt) }
    $bytes = [Security.Cryptography.ProtectedData]::Protect($Utf8.GetBytes($plain), $null,
        [Security.Cryptography.DataProtectionScope]::LocalMachine)
    return [Convert]::ToBase64String($bytes)
}

if (-not (Test-Path (Join-Path $Root 'curl\bin\curl.exe'))) { throw 'Нет curl\bin\curl.exe рядом со скриптом' }

$siteKey = Protect-Secret 'Ключ агента с сайта (Цены для 1С -> Ключ агента 1С)'
$odataPassword = Protect-Secret 'Пароль пользователя 1С kotlov-site'
$config = @(
    'site=https://kotlov.by',
    'odata=http://127.0.0.1/ut_odata/odata/standard.odata/',
    'odata_user=kotlov-site',
    ('site_key=' + $siteKey),
    ('odata_password=' + $odataPassword)
)
[IO.File]::WriteAllText((Join-Path $Root 'agent.config'), ($config -join "`r`n") + "`r`n", $Utf8)
Write-Host 'agent.config записан. Проверка: powershell -ExecutionPolicy Bypass -File agent.ps1 -Check'

if ($InstallTask) {
    $script = Join-Path $Root 'agent.ps1'
    & schtasks.exe /Create /F /TN 'KOTLOV 1C agent' /SC MINUTE /MO 5 /RU SYSTEM /RL HIGHEST `
        /TR ('powershell.exe -NoProfile -ExecutionPolicy Bypass -File ' + $script)
    Write-Host 'Задача «KOTLOV 1C agent» создана: запуск каждые 5 минут.'
}
