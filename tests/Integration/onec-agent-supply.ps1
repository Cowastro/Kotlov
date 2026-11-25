# Run with Windows PowerShell (.NET Framework), matching the installed agent's JSON runtime.
$ErrorActionPreference = 'Stop'
$repo = Split-Path -Parent (Split-Path -Parent $PSScriptRoot)
$agent = Join-Path $repo 'public/downloads/onec-agent/agent.ps1'
$tokens = $null; $errors = $null
$ast = [Management.Automation.Language.Parser]::ParseFile($agent, [ref]$tokens, [ref]$errors)
if ($errors.Count) { throw ($errors | Out-String) }
foreach ($f in $ast.FindAll({ param($n) $n -is [Management.Automation.Language.FunctionDefinitionAst] }, $false)) {
    Invoke-Expression $f.Extent.Text
}
$script:checks = 0
function Assert-Equal($expected, $actual, $message) {
    $script:checks++
    if ($expected -ne $actual) { throw ($message + ': expected ' + $expected + ', got ' + $actual) }
}
function Assert-Fails($action, $text) {
    $script:checks++
    try { & $action } catch { if ([string]$_ -like ('*' + $text + '*')) { return }; throw }
    throw ('Expected failure: ' + $text)
}
$zero = '00000000-0000-0000-0000-000000000000'
$wh = 'aaaaaaaa-0000-0000-0000-000000000001'
$item = 'bbbbbbbb-0000-0000-0000-000000000001'
$key = 'cccccccc-0000-0000-0000-000000000001'
$other = 'dddddddd-0000-0000-0000-000000000001'
function Row([decimal]$quantity, [string]$action = 'КОбеспечению') {
    return @{ 'Номенклатура_Key' = $item; 'Характеристика_Key' = $zero; 'Количество' = $quantity;
        'Цена' = 24.99; 'Сумма' = 49.98; 'ВариантОбеспечения' = $action; 'Отменено' = $false }
}
function Doc($rows) {
    return @{ Ref_Key = $key; Number = 'TEST-1C'; DataVersion = 'v1'; Posted = $true; DeletionMark = $false;
        'Статус' = 'КВыполнению'; 'Склад_Key' = $wh; 'Товары' = @($rows) }
}
function Stock([decimal]$quantity, [decimal]$shipping = 0) {
    return @{ 'Склад_Key' = $wh; 'Номенклатура_Key' = $item; 'Характеристика_Key' = $zero;
        'Назначение_Key' = $zero; 'ВНаличииBalance' = $quantity; 'КОтгрузкеBalance' = $shipping }
}
function Hold([decimal]$quantity, [string]$owner) {
    return @{ 'Склад_Key' = $wh; 'Номенклатура_Key' = $item; 'Характеристика_Key' = $zero;
        'Назначение_Key' = $zero; 'ВРезервеBalance' = $quantity; 'КОтгрузкеBalance' = 0;
        'ДокументОтгрузки' = $owner; 'ДокументОтгрузки_Type' = 'StandardODATA.Document_ЗаказКлиента' }
}

$d = Doc @((Row 2), (Row 2))
Assert-Equal 1 (Set-SupplyFromBalances $d '' $true @((Stock 3)) @()) 'one physical stock budget across duplicate lines'
Assert-Equal 'Отгрузить' $d['Товары'][0]['ВариантОбеспечения'] 'available line ships'
Assert-Equal 'КОбеспечению' $d['Товары'][1]['ВариантОбеспечения'] 'short line is retained'
Assert-Equal 49.98 $d['Товары'][1]['Сумма'] 'money preserved'

$d = Doc @((Row 2))
Assert-Equal 0 (Set-SupplyFromBalances $d '' $true @((Stock 3 3)) @((Hold 3 $other))) 'another order owns the stock'
$d = Doc @((Row 2 'РезервироватьПоМереПоступления'))
Assert-Equal 1 (Set-SupplyFromBalances $d $key $false @((Stock 3 3)) @((Hold 2 $key), (Hold 1 $other))) 'own reserve counts once'
Assert-Equal 'Отгрузить' $d['Товары'][0]['ВариантОбеспечения'] 'own reserve promoted'
Assert-Equal 0 (Set-SupplyFromBalances $d $key $false @((Stock 3 3)) @((Hold 2 $key), (Hold 1 $other))) 'repeat is idempotent'

$s = Stock 10; $s['Склад_Key'] = $other
$d = Doc @((Row 2))
Assert-Equal 0 (Set-SupplyFromBalances $d '' $true @($s) @()) 'other warehouse not borrowed'
$d['Товары'][0]['Склад_Key'] = $zero
Assert-Equal 1 (Set-SupplyFromBalances $d '' $true @((Stock 2)) @()) 'empty line warehouse uses document warehouse'
$d = Doc @((Row 2))
$s = Stock 10; $s['Характеристика_Key'] = $other
Assert-Equal 0 (Set-SupplyFromBalances $d '' $true @($s) @()) 'other characteristic not borrowed'
$s = Stock 10; $s['Назначение_Key'] = $other
Assert-Equal 0 (Set-SupplyFromBalances $d '' $true @($s) @()) 'segregated stock not borrowed'

$cancelled = Row 2 'РезервироватьПоМереПоступления'; $cancelled['Отменено'] = $true
$d = Doc @($cancelled, (Row 2 'НеОбеспечивать'), (Row 2 'Отгрузить'), (Row 2))
Assert-Equal 0 (Set-SupplyFromBalances $d $key $false @((Stock 3)) @()) 'manual and cancelled actions retained; dispatch consumes budget'
Assert-Equal 'РезервироватьПоМереПоступления' $cancelled['ВариантОбеспечения'] 'cancelled line unchanged'
$d = Doc @((Row 2))
Assert-Equal 0 (Set-SupplyFromBalances $d '' $true @((Stock 1)) @()) 'part of a line does not imply full coverage'
Assert-Equal 1 (Set-SupplyFromBalances $d $key $false @((Stock 2)) @()) 'receipt promotes waiting line'

# Exercise the real task orchestration with an in-memory 1C double, never a network request.
$Utf8 = New-Object Text.UTF8Encoding($false)
$Root = Join-Path $repo ('storage/app/supply-agent-test-' + [guid]::NewGuid().ToString('N'))
$DoneDir = Join-Path $Root 'done'
[void](New-Item -ItemType Directory -Path $DoneDir -Force)
$json = New-SupplyJson
# Reproduce the PowerShell 2 transport boundary explicitly: nested PSObjects must not expose adapted properties.
$rawRows = $json.DeserializeObject('[{"Количество":1,"Цена":210,"Отменено":false,"ВариантОбеспечения":"КОбеспечению"}]')
$wrappedRows = [System.Management.Automation.PSObject]::AsPSObject($rawRows)
$journalData = @{ phase = 'pending'; rows = $wrappedRows }
$legacyJson = New-Object System.Web.Script.Serialization.JavaScriptSerializer
Assert-Fails { $legacyJson.Serialize($journalData) } 'PSParameterizedProperty'
$encoded = $json.Serialize($journalData)
$decoded = $json.DeserializeObject($encoded)
Assert-Equal 1 $decoded['rows'].Count 'wrapped singleton remains an array'
Assert-Equal 210 $decoded['rows'][0]['Цена'] 'wrapped decimal is unchanged'
Assert-Equal $false $decoded['rows'][0]['Отменено'] 'wrapped boolean is unchanged'
Assert-Equal $false ($encoded -match 'PSParameterizedProperty|PSObject|Members') 'no PowerShell infrastructure on the wire'
$nested = @{ rows = @([System.Management.Automation.PSObject]::AsPSObject(@{ values = @([decimal]1.25, $null, $true) })) }
$decoded = $json.DeserializeObject($json.Serialize($nested))
Assert-Equal 1.25 $decoded['rows'][0]['values'][0] 'nested decimal is preserved'
Assert-Equal $null $decoded['rows'][0]['values'][1] 'nested null is preserved'
Assert-Equal $true $decoded['rows'][0]['values'][2] 'nested boolean is preserved'
function Copy-Json($v) { return ,(New-SupplyJson).DeserializeObject((New-SupplyJson).Serialize($v)) }
function Reset-Server {
    $script:server = Copy-Json (Doc @((Row 2)))
    $script:reads = 0; $script:writes = 0; $script:posts = 0; $script:failPost = $false; $script:concurrent = $false
}
function Read-SupplyDocument([string]$url) {
    $script:reads++
    if ($script:concurrent -and $script:reads -eq 2) { $script:server['DataVersion'] = 'accountant-edit' }
    return @{ doc = (Copy-Json $script:server); etag = ('"' + $script:server['DataVersion'] + '"') }
}
function Read-SupplyBalances($doc) { return @{ stock = @((Stock 2)); holds = @() } }
function Write-SupplyRows([string]$url, $rows, [string]$etag) {
    Assert-Equal ('"' + $script:server['DataVersion'] + '"') $etag 'optimistic document version'
    $script:writes++
    $script:server['Товары'] = Copy-Json $rows
    $script:server['DataVersion'] = 'v' + ($script:writes + 1)
}
function Post-SupplyDocument([string]$url) {
    $script:posts++
    if ($script:failPost -and $script:posts -eq 1) { throw 'stock conflict' }
}
$lines = @(('key|' + $key), 'number|TEST-1C')
Reset-Server
$script:server['Статус'] = 'Закрыт'
[void](Do-OrderSupply '1' $lines)
Assert-Equal 0 $script:writes 'closed document not written'
Reset-Server
$script:server['Posted'] = $false
[void](Do-OrderSupply '2' $lines)
Assert-Equal 0 $script:writes 'unposted document not written'
Reset-Server
[void](Do-OrderSupply '3' ($lines + @('preview|1')))
Assert-Equal 0 $script:writes 'preview is read only'
Assert-Equal 0 $script:posts 'preview never posts'
Reset-Server
$script:concurrent = $true
Assert-Fails { Do-OrderSupply '4' $lines } 'изменён в 1С'
Assert-Equal 0 $script:writes 'accountant edit not overwritten'
Reset-Server
[void](Do-OrderSupply '5' $lines)
Assert-Equal 1 $script:writes 'one write after receipt'
Assert-Equal 1 $script:posts 'registers reposted after changing actions'
Assert-Equal 'Отгрузить' $script:server['Товары'][0]['ВариантОбеспечения'] '1C line promoted'
[void](Do-OrderSupply '5' $lines)
Assert-Equal 1 $script:writes 'lost result does not duplicate a write'
Reset-Server
$script:failPost = $true
Assert-Fails { Do-OrderSupply '6' $lines } 'исходные строки восстановлены'
Assert-Equal 2 $script:writes 'failed posting restored previous lines'
Assert-Equal 2 $script:posts 'restored registers reposted'
Assert-Equal 'КОбеспечению' $script:server['Товары'][0]['ВариантОбеспечения'] 'original action restored'
$beforeReads = $script:reads
[IO.File]::WriteAllText((Join-Path $DoneDir 'supply-7.json'), '{"phase":"pending"}', $Utf8)
Assert-Fails { Do-OrderSupply '7' $lines } 'прервана'
Assert-Equal $beforeReads $script:reads 'interrupted write requires review, not a blind replay'
# Transport-level contract: take the real HTTP helpers and stub curl, not the implementation under test.
foreach ($f in $ast.FindAll({ param($n) $n -is [Management.Automation.Language.FunctionDefinitionAst] }, $false)) {
    if (@('Read-SupplyDocument', 'Write-SupplyRows', 'Post-SupplyDocument') -contains $f.Name) { Invoke-Expression $f.Extent.Text }
}
$Body = Join-Path $Root 'body.json'; $Out = Join-Path $Root 'answer.json'
$OData = 'http://127.0.0.1/test/'; $ODataUser = 'test'
$CreatableEntities = @('Document_ЗаказКлиента')
$script:exists = $false; $script:lastHeaders = @(); $script:lastMethod = ''; $script:postedBody = $null
function Invoke-Curl([string]$url, [string]$method, [string[]]$headers, [string]$user, [string]$bodyFile, [string[]]$form, [string]$outFile, [string]$responseHeaders = '') {
    $script:lastHeaders = $headers; $script:lastMethod = $method; $script:lastUrl = $url
    if ($responseHeaders) { [IO.File]::WriteAllText($responseHeaders, "HTTP/1.1 200 OK`r`nETag: `"actual-version`"`r`n", $Utf8) }
    if ($method -eq 'POST') {
        if (-not $bodyFile) { return '200' }
        $script:postedBody = $json.DeserializeObject((Read-Answer $bodyFile))
        [IO.File]::WriteAllText($outFile, $json.Serialize(@{ Ref_Key = $key; Number = 'TEST-1C' }), $Utf8)
        return '201'
    }
    if ($method -eq 'PATCH') { return '204' }
    if ($script:exists) {
        [IO.File]::WriteAllText($outFile, $json.Serialize(@{ value = @(@{ Ref_Key = $key; Number = 'TEST-1C' }) }), $Utf8)
    } else {
        $mockDoc = Doc @((Row 2))
        if ($script:missingVersion) { $mockDoc.Remove('DataVersion') }
        [IO.File]::WriteAllText($outFile, $json.Serialize($mockDoc), $Utf8)
    }
    return '200'
}
$http = Read-SupplyDocument (Supply-DocumentUrl $key)
Assert-Equal 'v1' $http['etag'] 'use 1C DataVersion, independent of HTTP ETag'
$script:missingVersion = $true
Assert-Fails { Read-SupplyDocument (Supply-DocumentUrl $key) } 'DataVersion'
$script:missingVersion = $false
Write-SupplyRows (Supply-DocumentUrl $key) @((Row 2)) $http['etag']
Assert-Equal 'PATCH' $script:lastMethod 'only the goods table is patched'
Assert-Equal $true ($script:lastHeaders -contains 'If-Match: v1') 'DataVersion sent as If-Match to 1C'
Assert-Equal $true ($script:lastHeaders -contains '1C_OData-DataLoadMode: true') 'table staged in the supported exchange write mode'
Assert-Fails { Write-SupplyRows (Supply-DocumentUrl $key) @((Row 2)) '' } 'версию документа'
Post-SupplyDocument (Supply-DocumentUrl $key)
Assert-Equal $true ($script:lastUrl.EndsWith('/Post?PostingModeOperational=false')) '1C posting mode is a query parameter'
Assert-Equal $false ($script:lastHeaders -contains '1C_OData-DataLoadMode: true') 'normal posting always performs business validation'
$d = Doc @((Row 2), (Row 2))
$payload = [Convert]::ToBase64String($Utf8.GetBytes($json.Serialize($d)))
[void](Do-Create @('supply|live', ('body|' + $payload)) 'Document_ЗаказКлиента')
Assert-Equal 'Отгрузить' $script:postedBody['Товары'][0]['ВариантОбеспечения'] 'creation checks live stock'
Assert-Equal 'КОбеспечению' $script:postedBody['Товары'][1]['ВариантОбеспечения'] 'creation retains missing line'
$script:exists = $true; $script:postedBody = $null
[void](Do-Create @('supply|live', 'find|Document_Test?$top=1', ('body|' + $payload)) 'Document_ЗаказКлиента')
Assert-Equal $null $script:postedBody 'existing order never created twice'
Write-Output ('PASS: agent supply, ' + $script:checks + ' assertions; network calls: 0')
