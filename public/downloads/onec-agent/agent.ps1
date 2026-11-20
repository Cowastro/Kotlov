# KOTLOV agent for the 1C server (PowerShell 2.0, curl.exe next to it).
# Takes approved price batches from kotlov.by and creates an UNPOSTED «Установка цен номенклатуры» in 1C
# through the local OData publication; reports the 1C document number back to the site.
#   agent.ps1 -Check   read-only check: the site key and the 1C OData user work
#   agent.ps1          one run (the scheduler starts it every 5 minutes)
param([switch]$Check)

$ErrorActionPreference = 'Stop'
$Root = Split-Path -Parent $MyInvocation.MyCommand.Path
$Curl = Join-Path $Root 'curl\bin\curl.exe'
$ConfigPath = Join-Path $Root 'agent.config'
$LogPath = Join-Path $Root 'agent.log'
$Utf8 = New-Object System.Text.UTF8Encoding($false)
Add-Type -AssemblyName System.Security

function Log([string]$text) {
    $line = (Get-Date -Format 'yyyy-MM-dd HH:mm:ss') + ' ' + $text
    [IO.File]::AppendAllText($LogPath, $line + "`r`n", $Utf8)
    Write-Host $line
}

function Unprotect([string]$b64) {
    $bytes = [Security.Cryptography.ProtectedData]::Unprotect([Convert]::FromBase64String($b64), $null,
        [Security.Cryptography.DataProtectionScope]::LocalMachine)
    return $Utf8.GetString($bytes)
}

function Read-Config {
    if (-not (Test-Path $ConfigPath)) { throw 'Нет agent.config: сначала запустите setup.ps1' }
    $c = @{}
    foreach ($l in [IO.File]::ReadAllLines($ConfigPath, $Utf8)) {
        $i = $l.IndexOf('=')
        if ($i -gt 0) { $c[$l.Substring(0, $i).Trim()] = $l.Substring($i + 1).Trim() }
    }
    return $c
}

# A value for curl's config file: in double quotes, \ and " escaped.
function Q([string]$s) { return '"' + $s.Replace('\', '\\').Replace('"', '\"') + '"' }

# A JSON string.
function Js([string]$s) { return '"' + $s.Replace('\', '\\').Replace('"', '\"') + '"' }

# One HTTP call through curl. Secrets go through a temporary config file, never the command line.
# Returns the HTTP status; the answer is in $outFile.
function Invoke-Curl([string]$url, [string]$method, [string[]]$headers, [string]$user, [string]$bodyFile, [string[]]$form, [string]$outFile) {
    $cfg = [IO.Path]::GetTempFileName()
    $lines = @('silent', 'show-error', 'max-time = 300', ('url = ' + (Q $url)), ('output = ' + (Q $outFile)), ('write-out = "%{http_code}"'))
    if ($method) { $lines += 'request = ' + (Q $method) }
    foreach ($h in $headers) { $lines += 'header = ' + (Q $h) }
    if ($user) { $lines += 'user = ' + (Q $user) }
    if ($bodyFile) { $lines += 'data-binary = ' + (Q ('@' + $bodyFile)) }
    foreach ($f in $form) { $lines += 'data-urlencode = ' + (Q $f) }
    try {
        [IO.File]::WriteAllText($cfg, ($lines -join "`n") + "`n", $Utf8)
        $code = & $Curl -K $cfg
        if ($LASTEXITCODE -ne 0) { throw ('curl ' + $LASTEXITCODE + ' на ' + $url) }
        return [string]$code
    } finally {
        Remove-Item $cfg -Force -ErrorAction SilentlyContinue
    }
}

function Read-Answer([string]$file) {
    if (Test-Path $file) { return [IO.File]::ReadAllText($file, $Utf8) }
    return ''
}

$cfg = Read-Config
$Site = $cfg['site'].TrimEnd('/')
$OData = $cfg['odata'].TrimEnd('/') + '/'
$SiteAuth = 'Authorization: Bearer ' + (Unprotect $cfg['site_key'])
$ODataUser = $cfg['odata_user'] + ':' + (Unprotect $cfg['odata_password'])
$Out = Join-Path $Root 'last-answer.txt'
$Body = Join-Path $Root 'last-body.json'

if ($Check) {
    $code = Invoke-Curl ($Site + '/1c/agent/ping') 'GET' @($SiteAuth) $null $null @() $Out
    if ($code -eq '200') { Log 'Сайт: ключ агента принят' } else { Log ('Сайт: ошибка ' + $code + ' ' + (Read-Answer $Out)) }

    $types = $OData + [Uri]::EscapeDataString('Catalog_ВидыЦен') + '?$format=json&$select=Description'
    $code = Invoke-Curl $types 'GET' @('Accept: application/json') $ODataUser $null @() $Out
    if ($code -eq '200') { Log ('1С: виды цен прочитаны: ' + ((Read-Answer $Out) -replace '\s+', ' ')) } else { Log ('1С: ошибка ' + $code + ' ' + (Read-Answer $Out)) }
    exit 0
}

# 1. The next approved batch.
$code = Invoke-Curl ($Site + '/1c/agent/price-batches/next?format=lines') 'GET' @($SiteAuth) $null $null @() $Out
if ($code -ne '200') { Log ('Сайт не отдал пакет: ' + $code); exit 1 }
$lines = [IO.File]::ReadAllLines($Out, $Utf8)
if ($lines.Length -eq 0 -or $lines[0] -eq 'none') { exit 0 }

$batch = $null; $comment = ''; $currency = ''; $types = @(); $rows = @()
foreach ($l in $lines) {
    $p = $l.Split('|')
    switch ($p[0]) {
        'batch' { $batch = $p[1] }
        'comment' { $comment = $p[1] }
        'currency' { $currency = $p[1] }
        'type' { $types += $p[1] }
        'row' { $rows += ,$p }
    }
}
if (-not $batch -or $lines[$lines.Length - 1] -ne 'end') { Log 'Пакет пришёл не целиком, попробую в следующий раз'; exit 1 }
Log ('Пакет №' + $batch + ': строк ' + $rows.Count)

try {
    # 2. «Установка цен номенклатуры» like the ones made by hand: one row per item and price type.
    $zero = '00000000-0000-0000-0000-000000000000'
    $sb = New-Object System.Text.StringBuilder
    [void]$sb.Append('{"Date":' + (Js (Get-Date -Format 'yyyy-MM-ddTHH:mm:ss')) + ',"Комментарий":' + (Js $comment) + ',"Товары":[')
    $n = 0
    foreach ($r in $rows) {
        $n++
        if ($n -gt 1) { [void]$sb.Append(',') }
        $manual = 'false'
        if ($r[5] -eq '1') { $manual = 'true' }
        $line = '{"LineNumber":"' + $n + '","Номенклатура_Key":' + (Js $r[1]) + ',"Характеристика_Key":' + (Js $r[2])
        $line += ',"Упаковка_Key":' + (Js $zero) + ',"ВидЦены_Key":' + (Js $r[3]) + ',"Цена":' + $r[4]
        $line += ',"ЦенаИзмененаВручную":' + $manual + ',"Формула":' + (Js $r[6]) + ',"Валюта_Key":' + (Js $currency) + '}'
        [void]$sb.Append($line)
    }
    [void]$sb.Append('],"ВидыЦен":[')
    $n = 0
    foreach ($t in $types) {
        $n++
        if ($n -gt 1) { [void]$sb.Append(',') }
        [void]$sb.Append('{"LineNumber":"' + $n + '","ВидЦены_Key":' + (Js $t) + '}')
    }
    [void]$sb.Append(']}')
    [IO.File]::WriteAllText($Body, $sb.ToString(), $Utf8)

    $url = $OData + [Uri]::EscapeDataString('Document_УстановкаЦенНоменклатуры') + '?$format=json'
    $code = Invoke-Curl $url 'POST' @('Content-Type: application/json; charset=utf-8', 'Accept: application/json') $ODataUser $Body @() $Out
    $answer = Read-Answer $Out
    if ($code -ne '201' -and $code -ne '200') { throw ('1С ответила ' + $code + ': ' + $answer) }

    $number = ''; $key = ''
    if ($answer -match '"Number"\s*:\s*"([^"]*)"') { $number = $matches[1] }
    if ($answer -match '"Ref_Key"\s*:\s*"([0-9a-fA-F-]{36})"') { $key = $matches[1] }
    Log ('Пакет №' + $batch + ': создан документ ' + $number)
    $code = Invoke-Curl ($Site + '/1c/agent/price-batches/' + $batch + '/result') 'POST' @($SiteAuth) $null $null @('ok=1', ('document_number=' + $number), ('document_key=' + $key)) $Out
    if ($code -ne '200') { Log ('Сайт не принял ответ: ' + $code + ' ' + (Read-Answer $Out)) }
} catch {
    $err = [string]$_
    if ($err.Length -gt 1500) { $err = $err.Substring(0, 1500) }
    Log ('Пакет №' + $batch + ': ошибка ' + $err)
    $code = Invoke-Curl ($Site + '/1c/agent/price-batches/' + $batch + '/result') 'POST' @($SiteAuth) $null $null @('ok=0', ('error=' + $err)) $Out
    exit 1
}
