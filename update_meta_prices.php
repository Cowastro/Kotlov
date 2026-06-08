<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load(__DIR__ . '/storage/prices/meta_2025.xlsx');
$rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);

$priceItems = [];
foreach ($rows as $row) {
    $num   = trim($row[0] ?? '');
    $name  = trim($row[1] ?? '');
    $price = $row[3] ?? null;
    if (!is_numeric($num) || !$name || !$price) continue;
    preg_match('/[\"«"\'](.*?)[\"»"\']/u', $name, $m);
    $priceItems[] = [
        'name'  => $name,
        'model' => $m[1] ?? null,
        'price' => (float) str_replace(',', '', $price),
    ];
}

$metaBel = \App\Models\Product::where('name', 'like', '%Мета-Бел%')
    ->get(['sku', 'name', 'price']);

$manualFix = [
    'ПБМ 20 (в модификации ПС)' => 'PS-012.050',
    'Ритм L'                     => 'PS-007.487',
    'Севан 7'                    => 'PS-011.688',
];

$badSkus = ['PS-006.589', 'PS-007.488', 'PS-011.502'];

$updated = 0;
$skipped = 0;

foreach ($priceItems as $item) {
    $found = null;

    // Ручной маппинг
    foreach ($manualFix as $kw => $sku) {
        if (stripos($item['name'], $kw) !== false) {
            $found = \App\Models\Product::where('sku', $sku)->first();
            break;
        }
    }

    // Авто по модели в кавычках
    if (!$found && $item['model']) {
        $found = $metaBel->filter(fn($p) => stripos($p->name, $item['model']) !== false)->first();
    }

    // Авто по последним словам
    if (!$found) {
        $parts = explode(' ', $item['name']);
        for ($len = 3; $len >= 2; $len--) {
            $kw = implode(' ', array_slice($parts, -$len));
            $found = $metaBel->filter(fn($p) => stripos($p->name, $kw) !== false)->first();
            if ($found) break;
        }
    }

    if (!$found) { $skipped++; continue; }

    // Исключаем ошибочные SKU (кроме ручного маппинга)
    if (in_array($found->sku, $badSkus)) {
        $isManual = false;
        foreach ($manualFix as $kw => $s) {
            if (stripos($item['name'], $kw) !== false) { $isManual = true; break; }
        }
        if (!$isManual) { $skipped++; continue; }
    }

    $found->price     = $item['price'];
    $found->price_old = round($item['price'] * 1.15, 2);
    $found->save();

    echo "✅ {$found->sku} | {$found->name} → {$item['price']} (price_old: " . round($item['price'] * 1.15, 2) . ")\n";
    $updated++;
}

echo "\nОбновлено: $updated | Пропущено: $skipped\n";
