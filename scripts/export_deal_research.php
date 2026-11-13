<?php

// Read-only product research export. Run from the production application root.
require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$dir = storage_path('app/private/deal-by/research');
if (! is_dir($dir)) {
    mkdir($dir, 0700, true);
}
$local = fopen($dir.'/local.ndjson', 'wb');
\App\Models\Product::query()->with(['brand:id,name', 'category:id,name,slug',
    'supplierProducts' => fn ($q) => $q->select(['id', 'product_id', 'supplier_id', 'supplier_article', 'supplier_name', 'source_url', 'match_status', 'match_confidence'])->with('supplier:id,code,is_active'),
    'integrationProducts' => fn ($q) => $q->select(['id', 'product_id', 'integration_source_id', 'external_sku', 'external_code', 'barcode', 'name', 'match_status', 'match_confidence'])->with('source:id,code,is_active'),
])->select(['id', 'brand_id', 'category_id', 'sku', 'slug', 'name', 'price', 'currency', 'is_active', 'is_archived', 'requires_price_confirmation', 'specs', 'content_source_url'])->chunkById(300, function ($products) use ($local): void {
    foreach ($products as $product) {
        fwrite($local, json_encode($product->toArray(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n");
    }
});
fclose($local);
$remote = fopen($dir.'/remote.ndjson', 'wb');
$client = $app->make(\App\Services\Marketplaces\DealByClient::class);
$last = null;
$seen = [];
for ($page = 0; $page < 2000; $page++) {
    $items = $client->listProducts(100, $last)['products'] ?? null;
    if (! is_array($items)) {
        throw new \RuntimeException('Invalid product response');
    }
    foreach ($items as $item) {
        $id = (int) ($item['id'] ?? 0);
        if ($id <= 0 || isset($seen[$id])) {
            throw new \RuntimeException('Invalid product cursor');
        }
        $seen[$id] = true;
        $item = array_intersect_key($item, array_flip(['id', 'sku', 'name', 'external_id', 'price', 'currency', 'discount', 'status', 'presence', 'group', 'category', 'properties', 'product_attributes', 'description']));
        $item['description'] = mb_substr(strip_tags((string) ($item['description'] ?? '')), 0, 16000);
        fwrite($remote, json_encode($item, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n");
    }
    if (count($items) < 100) {
        break;
    }
    $last = (int) $items[array_key_last($items)]['id'];
}
fclose($remote);
$plans = glob(storage_path('app/private/deal-by/price-plans/*.json')) ?: [];
$plans = array_values(array_filter($plans, fn ($path) => ! str_ends_with($path, '-links-before.json')));
rsort($plans);
if (! $plans) {
    throw new \RuntimeException('No previous price plan');
}
copy($plans[0], $dir.'/baseline-plan.json');
echo 'exported_product_records='.count($seen)."\n";

// Numeric-only on-server research. Catalog records do not leave the host in default mode.
$baseline = json_decode(file_get_contents($dir.'/baseline-plan.json'), true, 512, JSON_THROW_ON_ERROR);
$wanted = array_fill_keys(array_column(array_filter($baseline, fn ($row) => $row['reason'] === 'unmatched'), 'deal_id'), true);
$articleIndex = [];
$nameIndex = [];
$compact = fn ($value) => preg_replace('/[\s\p{P}]+/u', '', mb_strtolower(str_replace('ё', 'е', trim((string) $value))));
$names = fn ($value) => trim(preg_replace('/[\s\p{P}]+/u', ' ', mb_strtolower(str_replace('ё', 'е', (string) $value))));
$input = fopen($dir.'/local.ndjson', 'rb');
while (($line = fgets($input)) !== false) {
    $item = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
    $keys = [$item['sku'], $item['slug']];
    foreach ($item['supplier_products'] ?? [] as $offer) {
        $keys[] = $offer['supplier_article'];
    }
    foreach ($item['integration_products'] ?? [] as $offer) {
        $keys[] = $offer['external_sku'];
        $keys[] = $offer['external_code'];
        $keys[] = $offer['barcode'];
    }
    foreach ($keys as $key) {
        if ($key = $compact($key)) {
            $articleIndex[$key][$item['id']] = true;
        }
    }
    $nameIndex[$names($item['name'])][$item['id']] = true;
}
fclose($input);
$counts = ['remaining' => count($wanted), 'compact_article_unique' => 0, 'compact_article_multiple' => 0, 'punctuation_name_unique' => 0, 'no_normalized_candidate' => 0];
$input = fopen($dir.'/remote.ndjson', 'rb');
while (($line = fgets($input)) !== false) {
    $item = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
    if (! isset($wanted[$item['id']])) {
        continue;
    }
    $candidates = $articleIndex[$compact($item['sku'] ?? '')] ?? [];
    if (count($candidates) === 1) {
        $counts['compact_article_unique']++;
    } elseif (count($candidates) > 1) {
        $counts['compact_article_multiple']++;
    } elseif (count($nameIndex[$names($item['name'])] ?? []) === 1) {
        $counts['punctuation_name_unique']++;
    } else {
        $counts['no_normalized_candidate']++;
    }
}
fclose($input);
foreach ($counts as $key => $value) {
    echo "metric.deal_research.$key=$value\n";
}
