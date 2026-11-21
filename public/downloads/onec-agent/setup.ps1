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
    # run.cmd: PowerShell 2.0 started by the scheduler as SYSTEM without a console may wait for input at the end
    # and block every next run, so it gets -NonInteractive and an empty input; task.log shows each start and exit.
    $script = Join-Path $Root 'agent.ps1'
    $log = Join-Path $Root 'task.log'
    $run = Join-Path $Root 'run.cmd'
    $cmd = @(
        ('@echo %date% %time% start>>"' + $log + '"'),
        ('@powershell.exe -NoProfile -NonInteractive -ExecutionPolicy Bypass -File "' + $script + '" >>"' + $log + '" 2>&1 <NUL'),
        ('@echo %date% %time% exit %errorlevel%>>"' + $log + '"')
    )
    [IO.File]::WriteAllText($run, ($cmd -join "`r`n") + "`r`n", (New-Object System.Text.ASCIIEncoding))

    & schtasks.exe /Create /F /TN 'KOTLOV 1C agent' /SC MINUTE /MO 5 /RU SYSTEM /RL HIGHEST /TR ('"' + $run + '"')
    # Run also on a UPS seen as a battery, catch up a missed start, never two at once, cut a hung run after 15 minutes.
    $s = New-Object -ComObject Schedule.Service
    $s.Connect()
    $folder = $s.GetFolder('\')
    $d = $folder.GetTask('KOTLOV 1C agent').Definition
    $d.Settings.DisallowStartIfOnBatteries = $false
    $d.Settings.StopIfGoingOnBatteries = $false
    $d.Settings.StartWhenAvailable = $true
    $d.Settings.ExecutionTimeLimit = 'PT15M'
    $d.Settings.MultipleInstances = 2
    [void]$folder.RegisterTaskDefinition('KOTLOV 1C agent', $d, 6, 'SYSTEM', $null, 5)
    Write-Host 'Задача «KOTLOV 1C agent» создана: запуск каждые 5 минут, журнал запусков task.log.'
}
