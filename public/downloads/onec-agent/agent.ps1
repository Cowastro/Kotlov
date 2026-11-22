# KOTLOV agent for the 1C server (PowerShell 2.0, curl.exe next to it). Version 8: «Мост 1С».
# Takes tasks from kotlov.by one at a time, does them in 1C through the local OData publication and reports back.
# Task kinds: price_batch — an UNPOSTED «Установка цен номенклатуры»; onec_order — an UNPOSTED «Заказ клиента»;
# onec_item — a new «Номенклатура» (only when 1C has no item with that article); onec_supplier_order — an UNPOSTED «Заказ поставщику»;
# odata_read, doc_status — read-only lists of 1C.
#   agent.ps1 -Check   read-only check: the site key and the 1C OData user work
#   agent.ps1          one run (the scheduler starts it every 5 minutes): does every task waiting in the queue
param([switch]$Check)

$ErrorActionPreference = 'Stop'
$AgentVersion = '9'
$Root = Split-Path -Parent $MyInvocation.MyCommand.Path
$Curl = Join-Path $Root 'curl\bin\curl.exe'
$ConfigPath = Join-Path $Root 'agent.config'
$LogPath = Join-Path $Root 'agent.log'
$DoneDir = Join-Path $Root 'done'
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

# A value for curl's config file: one line in double quotes, \ and " escaped (a line break would end the value and
# break the config, e.g. a 1C error text of several lines).
function Q([string]$s) { return '"' + ($s -replace '[\r\n\t]+', ' ').Replace('\', '\\').Replace('"', '\"') + '"' }

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
$SiteHeaders = @(('Authorization: Bearer ' + (Unprotect $cfg['site_key'])), ('X-Kotlov-Agent: ' + $AgentVersion))
$ODataUser = $cfg['odata_user'] + ':' + (Unprotect $cfg['odata_password'])
$Out = Join-Path $Root 'last-answer.txt'
$Body = Join-Path $Root 'last-body.json'

if ($Check) {
    Log ('Агент версии ' + $AgentVersion)
    $code = Invoke-Curl ($Site + '/1c/agent/ping') 'GET' $SiteHeaders $null $null @() $Out
    if ($code -eq '200') { Log 'Сайт: ключ агента принят' } else { Log ('Сайт: ошибка ' + $code + ' ' + (Read-Answer $Out)) }

    $types = $OData + [Uri]::EscapeDataString('Catalog_ВидыЦен') + '?$format=json&$select=Description'
    $code = Invoke-Curl $types 'GET' @('Accept: application/json') $ODataUser $null @() $Out
    if ($code -eq '200') { Log ('1С: виды цен прочитаны: ' + ((Read-Answer $Out) -replace '\s+', ' ')) } else { Log ('1С: ошибка ' + $code + ' ' + (Read-Answer $Out)) }
    exit 0
}

# ---- price_batch: «Установка цен номенклатуры» like the ones made by hand, one row per item and price type ----
function Do-PriceBatch([string[]]$lines) {
    $comment = ''; $currency = ''; $types = @(); $rows = @()
    foreach ($l in $lines) {
        $p = $l.Split('|')
        switch ($p[0]) {
            'comment' { $comment = $p[1] }
            'currency' { $currency = $p[1] }
            'type' { $types += $p[1] }
            'row' { $rows += ,$p }
        }
    }
    if ($rows.Count -eq 0) { throw 'В пакете нет строк' }

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
    return @(('document_number=' + $number), ('document_key=' + $key))
}

# ---- odata_read: GET the 1C lists the site asked for and upload each answer as it is (read only) ----
function Do-ODataRead([string]$id, [string[]]$lines) {
    $n = 0
    foreach ($l in $lines) {
        $p = $l.Split('|')
        if ($p[0] -ne 'get') { continue }
        $name = $p[1]; $path = $p[2]
        # Only a path inside the OData publication: no other host, no other folder.
        if ($name -notmatch '^[a-z0-9_]+$' -or $path -notmatch '^[A-Za-z0-9%_.~-]+(/Balance)?\?[A-Za-z0-9%_.~$=&-]+$') { throw ('Недопустимый запрос: ' + $name) }
        $code = Invoke-Curl ($OData + $path) 'GET' @('Accept: application/json') $ODataUser $null @() $Out
        $up = Join-Path $Root 'last-upload.txt'
        $sent = Invoke-Curl ($Site + '/1c/agent/tasks/' + $id + '/part?name=' + $name + '&code=' + $code) 'POST' ($SiteHeaders + @('Content-Type: application/octet-stream')) $null $Out @() $up
        if ($sent -ne '200') { throw ('Сайт не принял «' + $name + '»: ' + $sent + ' ' + (Read-Answer $up)) }
        Log ('  ' + $name + ': 1С ответила ' + $code)
        if ($code -eq '200') { $n++ }
    }
    return @(('read=' + $n))
}

# What the agent may create in 1C. Nothing else, whatever the site asks.
$CreatableEntities = @('Document_ЗаказКлиента', 'Catalog_Номенклатура', 'Document_ЗаказПоставщику')

# The number (document) or code (catalog item) and the key of the first object in a 1C answer.
function Read-Created([string]$answer, [string]$existed) {
    $number = ''; $code = ''; $key = ''
    if ($answer -match '"Number"\s*:\s*"([^"]*)"') { $number = $matches[1] }
    if ($answer -match '"Code"\s*:\s*"([^"]*)"') { $code = $matches[1] }
    if ($answer -match '"Ref_Key"\s*:\s*"([0-9a-fA-F-]{36})"') { $key = $matches[1] }
    return @(('document_number=' + $number), ('document_code=' + $code), ('document_key=' + $key), ('existed=' + $existed))
}

# ---- onec_order / onec_item: an UNPOSTED «Заказ клиента» or a new «Номенклатура». The site prepared the object,
# the agent first looks for it (the same order number, the same article) and creates it only when 1C has none ----
function Do-Create([string[]]$lines, [string]$entity) {
    $find = ''; $b64 = ''; $load = $false
    foreach ($l in $lines) {
        $i = $l.IndexOf('|')
        if ($i -lt 0) { continue }
        $k = $l.Substring(0, $i); $v = $l.Substring($i + 1)
        if ($k -eq 'entity') { $entity = $v }
        if ($k -eq 'find') { $find = $v }
        if ($k -eq 'body') { $b64 = $v }
        if ($k -eq 'mode' -and $v -eq 'load') { $load = $true }
    }
    if (-not $b64) { throw 'В задаче нет объекта для 1С' }
    if ($CreatableEntities -notcontains $entity) { throw ('Агент не создаёт «' + $entity + '»') }

    # Already in 1C (made by hand, or an answer to the site was lost): report it instead of a second one.
    if ($find) {
        if ($find -notmatch '^[A-Za-z0-9%_.~-]+\?[A-Za-z0-9%_.~$=&-]+$') { throw 'Недопустимый поиск в 1С' }
        $code = Invoke-Curl ($OData + $find) 'GET' @('Accept: application/json') $ODataUser $null @() $Out
        $answer = Read-Answer $Out
        if ($code -ne '200') { throw ('1С не ответила на поиск: ' + $code + ' ' + $answer) }
        if ($answer -match '"Ref_Key"\s*:\s*"([0-9a-fA-F-]{36})"') { return Read-Created $answer '1' }
    }

    [IO.File]::WriteAllBytes($Body, [Convert]::FromBase64String($b64))
    $url = $OData + [Uri]::EscapeDataString($entity) + '?$format=json'
    # «mode|load»: 1C writes the object as a draft without its write handlers (1C_OData-DataLoadMode); all its
    # logic and checks run when a manager posts it in 1C.
    $headers = @('Content-Type: application/json; charset=utf-8', 'Accept: application/json')
    if ($load) { $headers += '1C_OData-DataLoadMode: true' }
    $code = Invoke-Curl $url 'POST' $headers $ODataUser $Body @() $Out
    $answer = Read-Answer $Out
    if ($code -ne '201' -and $code -ne '200') { throw ('1С ответила ' + $code + ': ' + $answer) }

    return Read-Created $answer '0'
}

# Sends the result; the «done» file (what was already created in 1C) is kept until the site has accepted it.
function Send-Result([string]$id, [string[]]$form, [string]$doneFile) {
    $code = Invoke-Curl ($Site + '/1c/agent/tasks/' + $id + '/result') 'POST' $SiteHeaders $null $null $form $Out
    if ($code -eq '200' -or $code -eq '409' -or $code -eq '404') {
        if ($doneFile -and (Test-Path $doneFile)) { Remove-Item $doneFile -Force }
        if ($code -ne '200') { Log ('Задача №' + $id + ': сайт ответил ' + $code + ' ' + (Read-Answer $Out)) }
        return $true
    }
    Log ('Задача №' + $id + ': сайт не принял ответ (' + $code + '), повторю позже')
    return $false
}

if (-not (Test-Path $DoneDir)) { [void](New-Item -ItemType Directory -Path $DoneDir) }

for ($round = 0; $round -lt 20; $round++) {
    $code = Invoke-Curl ($Site + '/1c/agent/tasks/next') 'GET' $SiteHeaders $null $null @() $Out
    if ($code -ne '200') { Log ('Сайт не отдал задачу: ' + $code); exit 1 }
    $lines = [IO.File]::ReadAllLines($Out, $Utf8)
    if ($lines.Length -eq 0 -or $lines[0] -eq 'none') { exit 0 }

    $head = $lines[0].Split('|')
    if ($head[0] -ne 'task' -or $lines[$lines.Length - 1] -ne 'end') { Log 'Задача пришла не целиком, попробую в следующий раз'; exit 1 }
    $id = $head[1]; $type = $head[2]
    $doneFile = Join-Path $DoneDir ('task-' + $id + '.txt')

    # Already done in 1C, only the answer to the site was lost: report it again instead of doing it twice.
    if (Test-Path $doneFile) {
        Log ('Задача №' + $id + ': уже выполнена раньше, повторяю ответ сайту')
        if (-not (Send-Result $id ([IO.File]::ReadAllLines($doneFile, $Utf8)) $doneFile)) { exit 1 }
        continue
    }

    Log ('Задача №' + $id + ' (' + $type + '): строк ' + ($lines.Length - 2))
    try {
        switch ($type) {
            'price_batch' { $data = Do-PriceBatch $lines }
            'odata_read' { $data = Do-ODataRead $id $lines }
            'doc_status' { $data = Do-ODataRead $id $lines }
            'onec_order' { $data = Do-Create $lines 'Document_ЗаказКлиента' }
            'onec_item' { $data = Do-Create $lines 'Catalog_Номенклатура' }
            'onec_supplier_order' { $data = Do-Create $lines 'Document_ЗаказПоставщику' }
            default { throw ('Агент версии ' + $AgentVersion + ' не умеет задачи «' + $type + '»: обновите agent.ps1 с сайта') }
        }
        $form = @('ok=1') + $data
        [IO.File]::WriteAllLines($doneFile, $form, $Utf8)
        Log ('Задача №' + $id + ': готово ' + ($data -join ' '))
        if (-not (Send-Result $id $form $doneFile)) { exit 1 }
    } catch {
        $err = ([string]$_) -replace '\s+', ' '
        if ($err.Length -gt 1500) { $err = $err.Substring(0, 1500) }
        Log ('Задача №' + $id + ': ошибка ' + $err)
        # The error goes to the site; if even that fails, the run goes on with the next task.
        try { [void](Send-Result $id @('ok=0', ('error=' + $err)) $null) } catch { Log ('Задача №' + $id + ': ошибка не ушла на сайт: ' + [string]$_) }
    }
}
