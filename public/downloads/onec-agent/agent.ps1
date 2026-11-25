# KOTLOV agent for the 1C server (PowerShell 2.0, curl.exe next to it). Version 17: native rollback-only posting diagnostics and segregated supply guard.
# Takes tasks from kotlov.by one at a time, does them in 1C through the local OData publication and reports back.
# Task kinds: price_batch — an UNPOSTED «Установка цен номенклатуры»; onec_order — an UNPOSTED «Заказ клиента»;
# onec_item — a new «Номенклатура» (only when 1C has no item with that article); onec_supplier_order — an UNPOSTED «Заказ поставщику»;
# odata_read, doc_status — read-only lists of 1C; onec_order_supply — supply actions of a posted open order.
#   agent.ps1 -Check   read-only check: the site key and the 1C OData user work
#   agent.ps1          one run (the scheduler starts it every 5 minutes): does every task waiting in the queue
param([switch]$Check, [string]$NativeProbe)

$ErrorActionPreference = 'Stop'
$AgentVersion = '17'
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
function Invoke-Curl([string]$url, [string]$method, [string[]]$headers, [string]$user, [string]$bodyFile, [string[]]$form, [string]$outFile, [string]$responseHeaders = '') {
    $cfg = [IO.Path]::GetTempFileName()
    $lines = @('silent', 'show-error', 'max-time = 300', ('url = ' + (Q $url)), ('output = ' + (Q $outFile)), ('write-out = "%{http_code}"'))
    if ($method) { $lines += 'request = ' + (Q $method) }
    if ($responseHeaders) { $lines += 'dump-header = ' + (Q $responseHeaders) }
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
        if ($name -notmatch '^[a-z0-9_]+$' -or $path -notmatch '^[A-Za-z0-9%_.~-]+(/Balance|/SliceLast)?\?[A-Za-z0-9%_.~$=&-]+$') { throw ('Недопустимый запрос: ' + $name) }
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
    $find = ''; $b64 = ''; $load = $false; $supply = $false
    foreach ($l in $lines) {
        $i = $l.IndexOf('|')
        if ($i -lt 0) { continue }
        $k = $l.Substring(0, $i); $v = $l.Substring($i + 1)
        if ($k -eq 'entity') { $entity = $v }
        if ($k -eq 'find') { $find = $v }
        if ($k -eq 'body') { $b64 = $v }
        if ($k -eq 'mode' -and $v -eq 'load') { $load = $true }
        if ($k -eq 'supply' -and $v -eq 'live') { $supply = $true }
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

    if ($supply -and $entity -eq 'Document_ЗаказКлиента') {
        $json = New-SupplyJson
        $doc = $json.DeserializeObject($Utf8.GetString([Convert]::FromBase64String($b64)))
        [void](Set-OrderSupply $doc '' $true)
        [IO.File]::WriteAllText($Body, $json.Serialize($doc), $Utf8)
    } else {
        [IO.File]::WriteAllBytes($Body, [Convert]::FromBase64String($b64))
    }
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

# Supply is checked against live 1C registers, on the line's warehouse and characteristic.
# No supplier feeds, old exchange stock, other orders' reserves, or automatic cancellation of a posting.
function New-SupplyJson {
    Add-Type -AssemblyName System.Web.Extensions
    if (-not ('Kotlov.OneCJson' -as [type])) {
        # PowerShell 2 can leave PSObject wrappers inside dictionaries/arrays. JavaScriptSerializer then sees
        # PowerShell's adapted properties (including indexers) instead of JSON data and reports a circular reference.
        # Unwrap recursively in CLR code, not through the PowerShell pipeline, which can wrap the values again.
        $source = @'
using System;
using System.Collections;
using System.Collections.Generic;
using System.Management.Automation;
using System.Web.Script.Serialization;
namespace Kotlov {
    public sealed class OneCJson {
        private readonly JavaScriptSerializer json = new JavaScriptSerializer();
        public OneCJson() { json.MaxJsonLength = 8388608; }
        public object DeserializeObject(string text) { return json.DeserializeObject(text); }
        public string Serialize(object value) { return json.Serialize(Clean(value, 0)); }
        private static object Clean(object value, int depth) {
            if (depth > 64) throw new InvalidOperationException("JSON nesting exceeds 64 levels");
            PSObject wrapper = value as PSObject;
            if (wrapper != null) return Clean(wrapper.BaseObject, depth + 1);
            if (value == null || value is string || value is decimal || value is DateTime || value.GetType().IsPrimitive) return value;
            IDictionary dictionary = value as IDictionary;
            if (dictionary != null) {
                Dictionary<string, object> clean = new Dictionary<string, object>();
                foreach (DictionaryEntry entry in dictionary) clean.Add(Convert.ToString(entry.Key), Clean(entry.Value, depth + 1));
                return clean;
            }
            IEnumerable sequence = value as IEnumerable;
            if (sequence != null) {
                List<object> clean = new List<object>();
                foreach (object item in sequence) clean.Add(Clean(item, depth + 1));
                return clean;
            }
            throw new InvalidOperationException("Unsupported JSON value: " + value.GetType().FullName);
        }
    }
}
'@
        Add-Type -TypeDefinition $source -ReferencedAssemblies @('System.dll', [System.Management.Automation.PSObject].Assembly.Location, [System.Web.Script.Serialization.JavaScriptSerializer].Assembly.Location)
    }
    return New-Object Kotlov.OneCJson
}

function Supply-Guid([string]$v) {
    if ($v -notmatch '^[0-9a-fA-F]{8}(-[0-9a-fA-F]{4}){3}-[0-9a-fA-F]{12}$') { throw 'Некорректный ключ 1С в обеспечении' }
    return $v.ToLowerInvariant()
}

function Supply-Dimension($row, [string]$warehouse) {
    $zero = '00000000-0000-0000-0000-000000000000'
    if ($row['Склад_Key'] -and $row['Склад_Key'] -ne $zero) { $warehouse = [string]$row['Склад_Key'] }
    if ($warehouse -eq $zero) { throw 'В строке заказа не определён склад 1С' }
    $characteristic = $zero
    if ($row['Характеристика_Key']) { $characteristic = [string]$row['Характеристика_Key'] }
    return (Supply-Guid $warehouse) + '|' + (Supply-Guid ([string]$row['Номенклатура_Key'])) + '|' + (Supply-Guid $characteristic)
}

function Read-SupplyJson([string]$url, [string]$headFile = '') {
    $code = Invoke-Curl $url 'GET' @('Accept: application/json') $ODataUser $null @() $Out $headFile
    if ($code -ne '200') { throw ('Не прочитано обеспечение 1С: ' + $code + ' ' + (Read-Answer $Out)) }
    return (New-SupplyJson).DeserializeObject((Read-Answer $Out))
}

function Read-SupplyBalances($doc) {
    $groups = @{}
    foreach ($r in $doc['Товары']) {
        if ($r['Отменено']) { continue }
        $d = (Supply-Dimension $r ([string]$doc['Склад_Key'])).Split('|')
        if (-not $groups.ContainsKey($d[0])) { $groups[$d[0]] = @{} }
        $groups[$d[0]][$d[1]] = $true
    }
    $stock = @(); $holds = @()
    foreach ($warehouse in $groups.Keys) {
        $keys = @($groups[$warehouse].Keys)
        for ($i = 0; $i -lt $keys.Count; $i += 8) {
            $parts = @()
            for ($n = $i; $n -lt [Math]::Min($i + 8, $keys.Count); $n++) { $parts += "Номенклатура_Key eq guid'" + $keys[$n] + "'" }
            $filter = "Склад_Key eq guid'" + $warehouse + "' and (" + ($parts -join ' or ') + ')'
            foreach ($register in @('ТоварыНаСкладах', 'ТоварыКОтгрузке')) {
                $url = $OData + [Uri]::EscapeDataString('AccumulationRegister_' + $register) + '/Balance?$format=json&$top=5001&$filter=' + [Uri]::EscapeDataString($filter)
                $answer = Read-SupplyJson $url
                if (-not $answer.ContainsKey('value') -or $answer['odata.nextLink'] -or $answer['@odata.nextLink'] -or @($answer['value']).Count -gt 5000) { throw 'Неполный ответ регистров 1С: действия строк не изменены' }
                if ($register -eq 'ТоварыНаСкладах') { $stock += @($answer['value']) } else { $holds += @($answer['value']) }
            }
        }
    }
    return @{ stock = $stock; holds = $holds }
}

# Mutates only ВариантОбеспечения. Whole lines wait if they are not fully covered; no partial dispatch is created.
function Set-OrderSupply($doc, [string]$orderKey, [bool]$creating = $false) {
    $balances = Read-SupplyBalances $doc
    return Set-SupplyFromBalances $doc $orderKey $creating $balances['stock'] $balances['holds']
}

function Set-SupplyFromBalances($doc, [string]$orderKey, [bool]$creating, $stock, $holds) {
    $zero = '00000000-0000-0000-0000-000000000000'
    $available = @{}; $shipping = @{}; $held = @{}; $own = @{}
    foreach ($r in $stock) {
        if ($r['Назначение_Key'] -and $r['Назначение_Key'] -ne $zero) { continue }
        $d = Supply-Dimension $r ''
        $available[$d] += [decimal]$r['ВНаличииBalance']
        $shipping[$d] += [decimal]$r['КОтгрузкеBalance']
    }
    foreach ($r in $holds) {
        if ($r['Назначение_Key'] -and $r['Назначение_Key'] -ne $zero) { continue }
        $d = Supply-Dimension $r ''
        $q = [decimal]$r['ВРезервеBalance'] + [decimal]$r['КОтгрузкеBalance']
        $held[$d] += $q
        if ($orderKey -and $r['ДокументОтгрузки'] -eq $orderKey -and $r['ДокументОтгрузки_Type'] -eq 'StandardODATA.Document_ЗаказКлиента') { $own[$d] += $q }
    }
    foreach ($d in @($available.Keys)) {
        $available[$d] = [Math]::Max([decimal]0, [decimal]$available[$d] - [Math]::Max([decimal]0, [Math]::Max([decimal]$held[$d], [decimal]$shipping[$d]) - [decimal]$own[$d]))
    }
    # Existing dispatch decisions consume this order's share before waiting rows are considered.
    if (-not $creating) {
        foreach ($r in $doc['Товары']) {
            if ($r['Обособленно'] -or ($r['Назначение_Key'] -and $r['Назначение_Key'] -ne $zero)) { continue }
            if (-not $r['Отменено'] -and $r['ВариантОбеспечения'] -eq 'Отгрузить') {
                $d = Supply-Dimension $r ([string]$doc['Склад_Key'])
                $available[$d] = [Math]::Max([decimal]0, [decimal]$available[$d] - [decimal]$r['Количество'])
            }
        }
    }
    $changed = 0
    foreach ($r in $doc['Товары']) {
        if ($r['Отменено'] -or [decimal]$r['Количество'] -le 0) { continue }
        if (-not $creating -and @('КОбеспечению', 'РезервироватьПоМереПоступления') -notcontains $r['ВариантОбеспечения']) { continue }
        # Segregated or serial-specific supply needs 1C's own allocation; do not borrow ordinary stock for it.
        if ($r['Обособленно'] -or ($r['Назначение_Key'] -and $r['Назначение_Key'] -ne $zero) -or ($r['Серия_Key'] -and $r['Серия_Key'] -ne $zero)) { continue }
        $d = Supply-Dimension $r ([string]$doc['Склад_Key'])
        $action = 'КОбеспечению'
        if ([decimal]$available[$d] -ge [decimal]$r['Количество']) {
            $action = 'Отгрузить'
            $available[$d] -= [decimal]$r['Количество']
        }
        if ($r['ВариантОбеспечения'] -ne $action) { $r['ВариантОбеспечения'] = $action; $changed++ }
    }
    return $changed
}

function Supply-DocumentUrl([string]$key) {
    return $OData + [Uri]::EscapeDataString('Document_ЗаказКлиента') + "(guid'" + (Supply-Guid $key) + "')"
}

function Read-SupplyDocument([string]$url) {
    $doc = Read-SupplyJson ($url + '?$format=json')
    # 1C's standard OData interface uses the entity's DataVersion directly as If-Match.
    # It need not expose an HTTP ETag. Never replace the token with '*' or omit the condition.
    $version = [string]$doc['DataVersion']
    if (-not $version -or $version -notmatch '^[A-Za-z0-9+/]+={0,2}$') { throw '1С не вернула корректную DataVersion: запись обеспечения остановлена' }
    return @{ doc = $doc; etag = $version }
}

function Write-SupplyRows([string]$url, $rows, [string]$etag) {
    if (-not $etag -or $etag -eq '*') { throw '1С не вернула версию документа: запись обеспечения остановлена' }
    [IO.File]::WriteAllText($Body, (New-SupplyJson).Serialize(@{ 'Товары' = $rows }), $Utf8)
    # This publication refuses ordinary writes of posted customer orders in ПриЗаписи (also why creation needs
    # DataLoadMode). Stage only the goods table as exchange data, then ALWAYS run normal Post below, without this
    # header. Posting validates business rules and updates registers; the original rows are restored if it fails.
    $code = Invoke-Curl ($url + '?$format=json') 'PATCH' @('Content-Type: application/json; charset=utf-8', 'Accept: application/json', '1C_OData-DataLoadMode: true', ('If-Match: ' + $etag)) $ODataUser $Body @() $Out
    if ($code -ne '200' -and $code -ne '204') { throw ('Не записано обеспечение 1С: ' + $code + ' ' + (Read-Answer $Out)) }
}

function Post-SupplyDocument([string]$url) {
    # HTTP.sys/IIS on the 1C server rejects an empty POST without an explicit length (411).
    $code = Invoke-Curl ($url + '/Post?PostingModeOperational=false') 'POST' @('Accept: application/json', 'Content-Length: 0') $ODataUser $null @() $Out
    if ($code -ne '200' -and $code -ne '204') { throw ('Не проведено обеспечение 1С: ' + $code + ' ' + (Read-Answer $Out)) }
}

function Do-OrderSupply([string]$id, [string[]]$lines) {
    if ($id -notmatch '^\d+$') { throw 'Некорректный номер задачи обеспечения' }
    $key = ''; $expected = ''; $preview = $false; $probe = $false; $expectedTotal = ''
    foreach ($l in $lines) {
        if ($l.StartsWith('key|')) { $key = Supply-Guid $l.Substring(4) }
        if ($l.StartsWith('number|')) { $expected = $l.Substring(7) }
        if ($l -eq 'preview|1') { $preview = $true }
        if ($l -eq 'probe|1') { $probe = $true }
        if ($l.StartsWith('expected_total|')) { $expectedTotal = $l.Substring(15) }
    }
    if (-not $key -or -not $expected) { throw 'Нет точной связи заказа для обеспечения' }
    if ($probe) { return Do-NativeSupplyProbe $id $key $expected $expectedTotal }
    $journal = Join-Path $DoneDir ('supply-' + $id + '.json')
    $json = New-SupplyJson
    if (Test-Path $journal) {
        $saved = $json.DeserializeObject((Read-Answer $journal))
        if ($saved['phase'] -eq 'done') { return @(('changed=' + $saved['changed']), ('document_key=' + $key)) }
        if ($saved['phase'] -ne 'restored') { throw 'Предыдущая запись обеспечения прервана: проверьте документ в 1С перед повтором. Исходные строки сохранены в журнале агента.' }
    }
    $url = Supply-DocumentUrl $key
    $before = Read-SupplyDocument $url
    $doc = $before['doc']
    if ($doc['Ref_Key'] -ne $key -or $doc['Number'] -ne $expected) { throw 'Не совпала связь заказа 1С: документ не изменён' }
    if (-not $doc['Posted'] -or $doc['DeletionMark'] -or $doc['Статус'] -eq 'Закрыт') { return @('changed=0', ('document_key=' + $key)) }
    $original = $json.Serialize($doc['Товары'])
    $changed = Set-OrderSupply $doc $key $false
    if ($preview) {
        if (-not $before['etag']) { throw 'Проверка без записи: 1С не вернула DataVersion, автоматическое обновление нельзя включить' }
        $ready = 0; $waiting = 0
        foreach ($r in $doc['Товары']) {
            if ($r['Отменено']) { continue }
            if ($r['ВариантОбеспечения'] -eq 'Отгрузить') { $ready++ }
            if ($r['ВариантОбеспечения'] -eq 'КОбеспечению') { $waiting++ }
        }
        return @(('changed=' + $changed), ('ready_lines=' + $ready), ('waiting_lines=' + $waiting), ('document_key=' + $key))
    }
    if ($changed -eq 0) { return @('changed=0', ('document_key=' + $key)) }
    # Read again after the balance requests; never overwrite an accountant's concurrent edit.
    $current = Read-SupplyDocument $url
    if ($current['doc']['DataVersion'] -ne $before['doc']['DataVersion'] -or $current['etag'] -ne $before['etag']) { throw 'Заказ изменён в 1С во время проверки: обеспечение не записано' }
    $snapshot = @{ phase = 'pending'; key = $key; rows = $json.DeserializeObject($original) }
    [IO.File]::WriteAllText($journal, $json.Serialize($snapshot), $Utf8)
    Write-SupplyRows $url $doc['Товары'] $before['etag']
    $written = Read-SupplyDocument $url
    try {
        Post-SupplyDocument $url
    } catch {
        $failure = [string]$_
        # Capture the FIRST error before any read/write reuses the HTTP response file. A failed recovery must
        # never replace the actual reason why the changed order could not be posted.
        $snapshot['posting_error'] = $failure
        [IO.File]::WriteAllText($journal, $json.Serialize($snapshot), $Utf8)
        try {
            $latest = Read-SupplyDocument $url
            if ($latest['doc']['DataVersion'] -ne $written['doc']['DataVersion']) { throw 'заказ изменён после записи — автоматический откат остановлен' }
            Write-SupplyRows $url $snapshot['rows'] $latest['etag']
            Post-SupplyDocument $url
            $restored = Read-SupplyDocument $url
            if (-not $restored['doc']['Posted'] -or $json.Serialize($restored['doc']['Товары']) -ne $original) { throw '1С не подтвердила восстановление исходных строк' }
        } catch {
            $snapshot['recovery_error'] = [string]$_
            [IO.File]::WriteAllText($journal, $json.Serialize($snapshot), $Utf8)
            throw ($failure + '; восстановление не подтверждено: ' + $snapshot['recovery_error'] + '; требуется проверка документа в 1С')
        }
        $snapshot['phase'] = 'restored'
        [IO.File]::WriteAllText($journal, $json.Serialize($snapshot), $Utf8)
        throw ($failure + '; исходные строки восстановлены, ошибка сохранена в задаче')
    }
    $verified = Read-SupplyDocument $url
    if (-not $verified['doc']['Posted'] -or $json.Serialize($verified['doc']['Товары']) -ne $json.Serialize($doc['Товары'])) { throw '1С не подтвердила записанное обеспечение: требуется проверка документа' }
    [IO.File]::WriteAllText($journal, $json.Serialize(@{ phase = 'done'; changed = $changed }), $Utf8)
    return @(('changed=' + $changed), ('document_key=' + $key))
}

# Find the infobase behind this exact LOCAL IIS publication, never a guessed server/database or another user.
function Find-NativeInfobase([string]$odataUrl, [string]$iisConfig) {
    $uri = New-Object Uri($odataUrl)
    if (@('127.0.0.1', 'localhost', '::1') -notcontains $uri.Host -or $uri.AbsolutePath -notmatch '(?i)^(.*)/odata/standard\.odata/?$') { throw 'Для проверки нужна локальная публикация OData' }
    $publication = [Uri]::UnescapeDataString($matches[1]).TrimEnd('/')
    if ($publication.Contains('..')) { throw 'Недопустимый путь публикации' }
    $paths = @()
    if (Test-Path $iisConfig) {
        $iis = New-Object Xml.XmlDocument; $iis.Load($iisConfig)
        foreach ($vd in $iis.SelectNodes('/configuration/system.applicationHost/sites/site/application/virtualDirectory')) {
            $prefix = ([string]$vd.ParentNode.GetAttribute('path')).TrimEnd('/') + ([string]$vd.GetAttribute('path')).TrimEnd('/')
            if ($publication -ne $prefix -and -not $publication.StartsWith($prefix + '/', [StringComparison]::OrdinalIgnoreCase)) { continue }
            $physical = [Environment]::ExpandEnvironmentVariables($vd.GetAttribute('physicalPath'))
            $suffix = $publication.Substring($prefix.Length).TrimStart('/')
            if ($suffix) { $physical = Join-Path $physical $suffix }
            $paths += Join-Path $physical 'default.vrd'
        }
    }
    $bases = @{}
    foreach ($path in $paths) {
        if (-not (Test-Path $path)) { continue }
        $vrd = New-Object Xml.XmlDocument; $vrd.Load($path)
        $ib = $vrd.DocumentElement.GetAttribute('ib')
        if ($ib) { $bases[$ib] = $true }
    }
    if ($bases.Count -ne 1) { throw 'Не найдена однозначная база локальной публикации IIS; проверка остановлена' }
    $ib = @($bases.Keys)[0]
    if ($ib -match '(?i)(^|;)\s*(Usr|Pwd|UC)\s*=' -or $ib -notmatch '(?i)(^|;)\s*(Srvr|File)\s*=') { throw 'Недопустимая строка базы публикации; проверка остановлена' }
    return $ib.TrimEnd(';') + ';'
}

function Native-ProbeError($record) {
    $messages = @(); $e = $record.Exception
    while ($e) { $messages = @([string]$e.Message) + $messages; $e = $e.InnerException }
    return ($messages -join "`n")
}

function Native-ProbeMessages($connection) {
    $texts = @()
    try {
        $messages = $connection.GetUserMessages()
        for ($i = 0; $i -lt $messages.Count(); $i++) { $texts += [string]$messages.Get($i).Text }
    } catch { } # The exception itself remains available on versions without this method.
    return ,$texts
}

# No field is assigned. Native posting is attempted under the API user inside our own transaction, ALWAYS rolled
# back. This obtains 1C's exception chain and user messages without DataLoadMode or bypassing business validation.
function Invoke-NativePostingProbe($connection, $doc, [string]$expected, [decimal]$total) {
    $report = @{ number = $expected; expected_total = $total.ToString('0.00', [Globalization.CultureInfo]::InvariantCulture); posting_validated = $false; rolled_back = $false; error = ''; messages = @() }
    $started = $false
    try {
        if ([string]$doc.Номер -ne $expected -or [decimal]$doc.СуммаДокумента -ne $total) { throw 'Не совпали номер или сумма контрольного заказа: запись не выполнялась' }
        if (-not $doc.Проведен -or $doc.ПометкаУдаления -or $connection.XMLString($doc.Статус) -eq 'Закрыт') { throw 'Контрольный документ закрыт, удалён или не проведён: запись не выполнялась' }
        $connection.BeginTransaction(); $started = $true
        $doc.ОбменДанными.Загрузка = $false
        $doc.Записать($connection.РежимЗаписиДокумента.Проведение, $connection.РежимПроведенияДокумента.Неоперативный)
        if (-not $connection.TransactionActive()) { throw '1С завершила контрольную транзакцию до отката: требуется проверка документа' }
        $report['posting_validated'] = $true
    } catch {
        $report['error'] = Native-ProbeError $_
        $report['messages'] = Native-ProbeMessages $connection
    } finally {
        if ($started) {
            try {
                if ($connection.TransactionActive()) { $connection.RollbackTransaction() }
                $report['rolled_back'] = -not $connection.TransactionActive()
            } catch { $report['error'] += "`nОшибка отката: " + (Native-ProbeError $_) }
        }
        if ($report['messages'].Count -eq 0) { $report['messages'] = Native-ProbeMessages $connection }
    }
    return $report
}

function Run-NativeProbeWorker([string]$id) {
    if ($id -notmatch '^\d+$') { throw 'Некорректный номер контрольной задачи' }
    $json = New-SupplyJson
    $request = $json.DeserializeObject((Read-Answer (Join-Path $DoneDir ('native-probe-' + $id + '-request.json'))))
    $key = Supply-Guid ([string]$request['key']); $expected = [string]$request['number']
    $total = [decimal]::Parse([string]$request['total'], [Globalization.CultureInfo]::InvariantCulture)
    if (-not $key -or -not $expected -or $total -le 0) { throw 'Нет точных реквизитов контрольного заказа' }
    $report = @{ number = $expected; document_key = $key; posting_validated = $false; rolled_back = $false; error = ''; messages = @() }
    $connector = $null; $connection = $null; $doc = $null; $password = ''
    try {
        $iisConfig = Join-Path $env:windir 'System32/inetsrv/config/applicationHost.config'
        $nativeIis = Join-Path $env:windir 'Sysnative/inetsrv/config/applicationHost.config'
        if (Test-Path $nativeIis) { $iisConfig = $nativeIis }
        $ib = Find-NativeInfobase $OData $iisConfig
        $password = Unprotect $cfg['odata_password']
        $user = ([string]$cfg['odata_user']).Replace('"', '""')
        $connector = New-Object -ComObject 'V83.COMConnector'
        $connection = $connector.Connect($ib + 'Usr="' + $user + '";Pwd="' + $password.Replace('"', '""') + '";')
        $uuid = $connection.NewObject('УникальныйИдентификатор', $key)
        $doc = $connection.Документы.ЗаказКлиента.ПолучитьСсылку($uuid).ПолучитьОбъект()
        $report = Invoke-NativePostingProbe $connection $doc $expected $total
        $report['document_key'] = $key
    } catch { $report['error'] = Native-ProbeError $_ }
    finally {
        foreach ($o in @($doc, $connection, $connector)) {
            if ($o -and [Runtime.InteropServices.Marshal]::IsComObject($o)) {
                try { [void][Runtime.InteropServices.Marshal]::ReleaseComObject($o) } catch { }
            }
        }
    }
    if ($password) {
        $report['error'] = ([string]$report['error']).Replace($password.Replace('"', '""'), '[REDACTED]').Replace($password, '[REDACTED]')
        $safeMessages = @()
        foreach ($m in $report['messages']) { $safeMessages += ([string]$m).Replace($password.Replace('"', '""'), '[REDACTED]').Replace($password, '[REDACTED]') }
        $report['messages'] = $safeMessages
    }
    $text = $json.Serialize($report)
    [IO.File]::WriteAllText((Join-Path $DoneDir ('native-probe-' + $id + '-result.json')), $text, $Utf8)
}

function Do-NativeSupplyProbe([string]$id, [string]$key, [string]$expected, [string]$total) {
    if ($id -notmatch '^\d+$' -or $total -notmatch '^\d+\.\d{2}$') { throw 'Некорректные реквизиты контрольной задачи' }
    $json = New-SupplyJson
    $request = Join-Path $DoneDir ('native-probe-' + $id + '-request.json')
    $result = Join-Path $DoneDir ('native-probe-' + $id + '-result.json')
    if ((Test-Path $request) -and -not (Test-Path $result)) { throw 'Контрольная проверка прервана: проверьте документ в 1С перед новым запуском' }
    if (-not (Test-Path $result)) {
        [IO.File]::WriteAllText($request, $json.Serialize(@{ key = $key; number = $expected; total = $total }), $Utf8)
        $hostPath = Join-Path $PSHOME 'powershell.exe'
        if ([IntPtr]::Size -eq 8 -and -not [Type]::GetTypeFromProgID('V83.COMConnector')) {
            $x86 = Join-Path $env:windir 'SysWOW64/WindowsPowerShell/v1.0/powershell.exe'
            if (Test-Path $x86) { $hostPath = $x86 }
        }
        $scriptPath = Join-Path $Root 'agent.ps1'
        $worker = Start-Process -FilePath $hostPath -ArgumentList ('-NoProfile -NonInteractive -ExecutionPolicy Bypass -File "' + $scriptPath + '" -NativeProbe ' + $id) -WindowStyle Hidden -PassThru
        if (-not $worker.WaitForExit(120000)) { $worker.Kill(); throw 'Истёк срок контрольной проверки: проверьте документ в 1С, повтор остановлен' }
        if (-not (Test-Path $result)) { throw 'Контрольная проверка не вернула отчёт: повтор остановлен' }
    }
    $up = Join-Path $Root 'last-upload.txt'
    $sent = Invoke-Curl ($Site + '/1c/agent/tasks/' + $id + '/part?name=native_probe&code=200') 'POST' ($SiteHeaders + @('Content-Type: application/octet-stream')) $null $result @() $up
    if ($sent -ne '200') { throw 'Сайт не принял отчёт контрольной проверки; отчёт сохранён у агента' }
    $report = $json.DeserializeObject((Read-Answer $result))
    if ($report['document_key'] -ne $key -or $report['number'] -ne $expected -or ($report['posting_validated'] -and $report['expected_total'] -ne $total)) { throw 'Не совпала связь контрольного отчёта 1С' }
    if (-not $report['posting_validated'] -or -not $report['rolled_back']) { throw (($report['messages'] -join ' ') + ' ' + $report['error']) }
    return @('changed=0', 'posting_validated=1', 'rolled_back=1', ('document_key=' + $key))
}

if (-not (Test-Path $DoneDir)) { [void](New-Item -ItemType Directory -Path $DoneDir) }
if ($NativeProbe) { Run-NativeProbeWorker $NativeProbe; exit 0 }

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
            'onec_order_supply' { $data = Do-OrderSupply $id $lines }
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
