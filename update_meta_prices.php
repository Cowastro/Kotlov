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
    ->orWhere('name', 'like', '%Мета-бел%')
    ->orWhere('name', 'like', '%МЕТА-БЕЛ%')
    ->get(['sku', 'name', 'price']);

// Ручной маппинг: ключевое слово из прайса → SKU сайта
$manualFix = [
    'ПБМ 16 (в модификации ПС)'  => 'PS-006.589',
    'ПБМ 20 (в модификации ПС)'  => 'PS-012.050',
    'ПБМ 20В'                     => 'PS-009.545',
    'ПБМ 20 (без стекла)'         => 'PS-012.093',
    'Ритм L+'                     => 'PS-007.488',
    'Ритм L'                      => 'PS-007.487',
    'Севан 7В'                    => 'PS-011.502',
    'Севан 7'                     => 'PS-011.688',
    'ЛеМан'                       => 'PS-011.503',
    'Байкал'                      => 'PS-012.053',
    'Енисей с плитой'             => 'PS-005.449',
    'Ангара 12'                   => 'PS-012.094',
    'Селена Т'                    => 'PS-012.095',
    'Аврора С2'                   => 'PS-011.630',
    'Аврора С'                    => 'PS-011.628',
    'Аврора М'                    => 'PS-011.560',
    'SKADI'                       => 'PS-011.445',
    'Нарочь В'                    => 'PS-011.915',
    'Нарочь'                      => 'PS-011.914',
];

$updated = 0;
$skipped = 0;

foreach ($priceItems as $item) {
    $found = null;

    // 1. Ручной маппинг (приоритет, ищем по порядку — более длинные ключи первые)
    $keys = array_keys($manualFix);
    usort($keys, fn($a, $b) => strlen($b) - strlen($a));
    foreach ($keys as $kw) {
        if (stripos($item['name'], $kw) !== false) {
            $found = \App\Models\Product::where('sku', $manualFix[$kw])->first();
            break;
        }
    }

    // 2. Авто по модели в кавычках
    if (!$found && $item['model']) {
        $found = $metaBel->filter(fn($p) => stripos($p->name, $item['model']) !== false)->first();
    }

    // 3. Авто по последним словам названия
    if (!$found) {
        $parts = explode(' ', $item['name']);
        for ($len = 3; $len >= 2; $len--) {
            $kw    = implode(' ', array_slice($parts, -$len));
            $found = $metaBel->filter(fn($p) => stripos($p->name, $kw) !== false)->first();
            if ($found) break;
        }
    }

    if (!$found) {
        $skipped++;
        continue;
    }

    $found->price     = $item['price'];
    $found->price_old = round($item['price'] * 1.15, 2);
    $found->save();

    echo "✅ {$found->sku} | {$found->name} → {$item['price']} BYN (price_old: " . round($item['price'] * 1.15, 2) . ")\n";
    $updated++;
}

echo "\nОбновлено: $updated | Пропущено: $skipped\n";
