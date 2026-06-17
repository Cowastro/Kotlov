<?php
// Debug: show Лигмет sheet names vs catalog names for a brand.
// Usage: php artisan tinker --execute="require base_path('ligdebug.php');"
// Edit $targetBrand below.

$targetBrand = 'Kratki'; // change to Nordflam, Invicta, etc.

$stopwords = [
    'ПЕЧЬ','ПЕЧЬ-КАМИН','ПЕЧЬ-КАМИНЫ','КАМИН','КАМИННАЯ','КАМИННЫЙ','ТОПКА',
    'ПЕЧНОЙ','ДРОВЯНАЯ','ДРОВЯНОЙ','БАННАЯ','ОТОПИТЕЛЬНАЯ','ВАРОЧНАЯ',
    'СЕРАЯ','СЕРЫЙ','СЕРОЕ','СЕРЫЕ','ЧЁРНАЯ','ЧЁРНЫЙ','ЧЁРНОЕ','ЧЕРНАЯ','ЧЕРНЫЙ','ЧЕРНОЕ',
    'БЕЛАЯ','БЕЛЫЙ','БЕЛОЕ','БЕЖЕВАЯ','БЕЖЕВЫЙ','КРАСНАЯ','КРАСНЫЙ',
    'КОРИЧНЕВАЯ','КОРИЧНЕВЫЙ','ПАТИНА','АНТРАЦИТ','ГРАФИТ','КРЕМОВАЯ','КРЕМОВЫЙ',
];

function ligModel(string $productName, string $brand, array $stopwords): string {
    $n = mb_strtoupper($productName);
    if ($brand !== '') {
        $n = preg_replace('/' . preg_quote(mb_strtoupper($brand), '/') . '/u', '', $n) ?? $n;
    }
    $n = preg_replace('/[^А-ЯЁA-Z0-9\-\/.]/u', ' ', $n) ?? $n;
    $toks = array_filter(
        preg_split('/\s+/u', trim($n)) ?: [],
        fn($t) => $t !== '' && !in_array($t, $stopwords, true)
    );
    return implode(' ', $toks);
}

// 1) Download xlsx and extract brand names
$id = '1YA5Aq05X2h3i1bRulkzvrgwlQ8JHdBMn';
$ctx = stream_context_create(['http'=>['timeout'=>120,'follow_location'=>1,'max_redirects'=>10,
    'header'=>"User-Agent: Mozilla/5.0\r\n"],'ssl'=>['verify_peer'=>false,'verify_peer_name'=>false]]);
$bin = @file_get_contents("https://docs.google.com/spreadsheets/d/{$id}/export?format=xlsx", false, $ctx);
if (!$bin || strlen($bin)<10000) { echo "FAILED to download xlsx\n"; exit; }
$tmp = tempnam(sys_get_temp_dir(),'lig').'.xlsx';
file_put_contents($tmp, $bin);

$zip = new ZipArchive();
$zip->open($tmp);
$ssXml = $zip->getFromName('xl/sharedStrings.xml');
$shXml = $zip->getFromName('xl/worksheets/sheet1.xml');
$zip->close();

// Parse shared strings
$shared = [];
$r = new XMLReader(); $r->XML($ssXml);
while ($r->read()) {
    if ($r->nodeType===XMLReader::ELEMENT && $r->name==='si') {
        $node = new SimpleXMLElement($r->readOuterXML());
        $t=''; foreach($node->xpath('.//*[local-name()="t"]') as $tn) $t.=(string)$tn;
        $shared[]=$t;
    }
}
$r->close();

function colIdx(string $l): int { $n=0; foreach(str_split($l) as $c) $n=$n*26+(ord($c)-64); return $n-1; }

// Parse sheet rows
$r2 = new XMLReader(); $r2->XML($shXml);
$cells=[]; $curRow=0; $rowsRaw=[];
while ($r2->read()) {
    if ($r2->nodeType===XMLReader::ELEMENT && $r2->name==='row') {
        if ($cells!==[]) { $rowsRaw[$curRow]=$cells; $cells=[]; }
        $curRow=(int)$r2->getAttribute('r');
    }
    if ($r2->nodeType===XMLReader::ELEMENT && $r2->name==='c') {
        $ref=(string)$r2->getAttribute('r'); $type=$r2->getAttribute('t');
        $xml=$r2->readOuterXML(); $val=preg_match('/<v>(.*?)<\/v>/s',$xml,$vm)?$vm[1]:'';
        if ($type==='s') $val=$shared[(int)$val]??'';
        $col=preg_replace('/\d+/','',$ref);
        $cells[colIdx($col)]=trim(html_entity_decode((string)$val,ENT_QUOTES|ENT_HTML5,'UTF-8'));
    }
}
if ($cells!==[]) $rowsRaw[$curRow]=$cells;
$r2->close();

// Walk rows, find brand section and product names
$brandMap=['ермак'=>'Ермак','кпд'=>'КПД','blist'=>'Blist','fireway'=>'FireWay',
    'fergus'=>'Ferguss','invicta'=>'Invicta','kratki'=>'Kratki','mbs'=>'MBS',
    'nordflam'=>'Nordflam','panadero'=>'Panadero'];
$section=''; $sheetRows=[];
foreach($rowsRaw as $row) {
    $article=trim((string)($row[1]??''));
    $name=trim((string)($row[3]??''));
    $priceRaw=trim((string)($row[6]??''));
    if ($article!==''&&$name===''&&$priceRaw==='') { $section=$article; continue; }
    if ($name===''||$priceRaw==='') continue;
    $low=mb_strtolower($section);
    $brand=null;
    foreach($brandMap as $k=>$v) { if(mb_strpos($low,$k)!==false){$brand=$v;break;} }
    if ($brand===null||$brand!==$targetBrand) continue;
    $model=ligModel($name,$brand,$stopwords);
    $sheetRows[]=[$name,$model];
}

echo "\n=== ЛИГМЕТ прайс — $targetBrand (".count($sheetRows)." строк) ===\n";
printf("%-45s  %s\n","NAME (прайс)","model()");
foreach(array_slice($sheetRows,0,20) as [$n,$m]) {
    printf("  %-43s  %s\n",mb_substr($n,0,43),$m);
}

// Catalog products for this brand
$bid=DB::table('brands')->whereRaw('LOWER(name)=?',[mb_strtolower($targetBrand)])->value('id');
echo "\n=== КАТАЛОГ — $targetBrand (is_archived=false) ===\n";
printf("%-45s  %s\n","NAME (каталог)","model()");
$catRows=DB::table('products')->where('brand_id',$bid)->where('is_archived',false)->get(['name']);
foreach(array_slice($catRows->all(),0,20) as $p) {
    $m=ligModel($p->name,$targetBrand,$stopwords);
    printf("  %-43s  %s\n",mb_substr($p->name,0,43),$m);
}

// Find matches
$sheetModels=array_column($sheetRows,1);
$catModels=array_map(fn($p)=>ligModel($p->name,$targetBrand,$stopwords),$catRows->all());
$matches=array_intersect($sheetModels,$catModels);
echo "\n=== СОВПАДЕНИЯ: ".count($matches)." из ".count($sheetModels)." ===\n";
foreach(array_unique($matches) as $m) echo "  $m\n";
unlink($tmp);
