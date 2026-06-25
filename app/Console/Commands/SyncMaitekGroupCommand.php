<?php

namespace App\Console\Commands;

use App\Services\ProductSourceEnricher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SyncMaitekGroupCommand extends Command
{
    protected $signature = 'supplier:sync-maitek-group
        {--dry-run : Preview only}
        {--apply : Write supplier prices, stock and optional products}
        {--sheet-url= : Google Sheets URL}
        {--gid=* : Process only selected Google Sheet gid values}
        {--brand=* : Process only selected brands, repeatable or comma-separated}
        {--available-only : Keep only rows with positive stock}
        {--create-new : Create products that do not match existing catalog items}
        {--enrich-created : Parse source_url after product creation}
        {--sync-retail-prices : Update products.price from retail BYN}
        {--limit= : Process only N parsed rows after filters}
        {--offset=0 : Skip N parsed rows after filters}';

    protected $description = 'Audit and sync Maitek Group Google price list: STEN, Karakan, Greolit prices, stock and source URLs.';

    private const SUPPLIER_CODE = 'maitek-group';
    private const SUPPLIER_NAME = 'Майтек Групп';
    private const SYNC_KEY = 'maitek_group_price';
    private const SOURCE_URL = 'https://docs.google.com/spreadsheets/d/1Cucw7U8f2Qs6U7kcWZvrdwZitXgP17j5W0TcujUFiN8/edit';
    private const DEFAULT_SHEET_URL = 'https://docs.google.com/spreadsheets/d/1Cucw7U8f2Qs6U7kcWZvrdwZitXgP17j5W0TcujUFiN8/edit?pli=1&gid=1879571452#gid=1879571452';

    private const DEFAULT_BRANDS = ['СТЭН', 'КАРАКАН', 'GREOLIT'];

    /** @var array<string,int> */
    private array $brandByKey = [];

    /** @var array<int,string> */
    private array $brandNameById = [];

    /** @var array<string,int> */
    private array $indexBySupplierArticle = [];

    /** @var array<string,int> */
    private array $indexByBrandName = [];

    /** @var array<string,int> */
    private array $indexByProductName = [];

    /** @var array<string,int> */
    private array $indexByModelSignature = [];

    /** @var array<string,int> */
    private array $indexBySourceUrl = [];

    /** @var array<string,int> */
    private array $categoryBySlug = [];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply') && ! (bool) $this->option('dry-run');
        $createNew = (bool) $this->option('create-new');

        $this->line($apply
            ? '<fg=red;options=bold>APPLY: Maitek Group sync will write changes.</>'
            : '<fg=yellow;options=bold>DRY RUN: Maitek Group sync will preview only.</>');

        try {
            $rows = $this->loadRows($this->option('sheet-url') ?: self::DEFAULT_SHEET_URL);
        } catch (\Throwable $e) {
            $this->error('Google Sheet load failed: ' . $e->getMessage());
            return self::FAILURE;
        }

        $brandFilter = $this->brandFilter();
        $rows = array_values(array_filter($rows, function (array $row) use ($brandFilter) {
            if ($row['name'] === '' || ($row['price_byn'] === null && $row['retail_byn'] === null)) {
                return false;
            }

            $brandKey = $this->brandKey($row['brand']);
            if (! isset($brandFilter[$brandKey])) {
                return false;
            }

            if ((bool) $this->option('available-only') && ! $row['in_stock']) {
                return false;
            }

            return true;
        }));

        $offset = max(0, (int) $this->option('offset'));
        if ($offset > 0) {
            $rows = array_slice($rows, $offset);
        }

        $limit = $this->option('limit');
        if ($limit !== null && (int) $limit > 0) {
            $rows = array_slice($rows, 0, (int) $limit);
        }

        $this->buildIndexes();
        $classified = array_map(fn (array $row) => $this->classify($row), $rows);

        return $apply
            ? $this->applyRows($classified, $createNew)
            : $this->report($classified);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function loadRows(string $sheetUrl): array
    {
        $sheetId = $this->sheetId($sheetUrl);
        $gids = $this->selectedGids($sheetId);
        $this->info(sprintf('Google Sheet gids: %d', count($gids)));

        $rows = [];
        $seen = [];

        foreach ($gids as $gid) {
            $csv = $this->fetch("https://docs.google.com/spreadsheets/d/{$sheetId}/export?format=csv&gid={$gid}");
            if ($csv === null || trim($csv) === '' || str_starts_with(ltrim($csv), '<')) {
                continue;
            }

            $linksByRow = $this->loadLinksByRow($sheetId, $gid);
            foreach ($this->csvRows($csv) as $rowIndex => $cells) {
                $sheetRow = $rowIndex + 1;
                $item = $this->normaliseRow($cells, $gid, $sheetRow, $linksByRow[$sheetRow] ?? null);
                if ($item === null) {
                    continue;
                }

                $dedupe = $item['norm_article'] ?: $this->nameKey($item['brand'] . ' ' . $item['name']);
                if ($dedupe === '' || isset($seen[$dedupe])) {
                    continue;
                }

                $seen[$dedupe] = true;
                $rows[] = $item;
            }
        }

        return $rows;
    }

    private function sheetId(string $url): string
    {
        if (! preg_match('#/spreadsheets/d/([a-zA-Z0-9_-]+)#', $url, $match)) {
            throw new \RuntimeException('Invalid Google Sheets URL.');
        }

        return $match[1];
    }

    /**
     * @return string[]
     */
    private function selectedGids(string $sheetId): array
    {
        $selected = $this->csvOption('gid');
        if ($selected !== []) {
            return $selected;
        }

        $html = $this->fetch("https://docs.google.com/spreadsheets/d/{$sheetId}/htmlview");
        if ($html === null) {
            throw new \RuntimeException('Unable to load Google Sheet htmlview.');
        }

        preg_match_all('/gid[=:]\\\\?"?(\d+)/', $html, $matches);
        $gids = array_values(array_unique($matches[1] ?? []));
        sort($gids, SORT_NUMERIC);

        if ($gids === []) {
            throw new \RuntimeException('No sheet gids found.');
        }

        return $gids;
    }

    /**
     * @return array<int,string>
     */
    private function loadLinksByRow(string $sheetId, string $gid): array
    {
        $html = $this->fetch("https://docs.google.com/spreadsheets/d/{$sheetId}/htmlview/sheet?headers=true&gid={$gid}");
        if ($html === null) {
            return [];
        }

        $links = [];
        preg_match_all('/<tr\b[^>]*>(.*?)<\/tr>/is', $html, $rows);
        foreach ($rows[1] ?? [] as $tr) {
            if (! preg_match('/<th\b[^>]*class="row-headers-background"[^>]*>.*?<div\b[^>]*>(\d+)<\/div>/is', $tr, $rowMatch)) {
                continue;
            }

            if (! preg_match('/<a\b[^>]*href="([^"]+)"/i', $tr, $linkMatch)) {
                continue;
            }

            $url = html_entity_decode($linkMatch[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $links[(int) $rowMatch[1]] = $this->unwrapGoogleUrl($url);
        }

        return $links;
    }

    private function unwrapGoogleUrl(string $url): string
    {
        $parts = parse_url($url);
        if (($parts['host'] ?? '') === 'www.google.com' && isset($parts['query'])) {
            parse_str($parts['query'], $query);
            if (! empty($query['q']) && is_string($query['q'])) {
                return $query['q'];
            }
        }

        return $url;
    }

    /**
     * @return array<int,array<int,string>>
     */
    private function csvRows(string $csv): array
    {
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $csv);
        rewind($handle);

        $rows = [];
        while (($row = fgetcsv($handle, 0, ',')) !== false) {
            $rows[] = array_map(fn ($value) => $this->clean((string) $value), $row);
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @param array<int,string> $cells
     * @return array<string,mixed>|null
     */
    private function normaliseRow(array $cells, string $gid, int $sheetRow, ?string $sourceUrl): ?array
    {
        $name = $this->firstNonEmpty($cells[1] ?? '', $cells[0] ?? '');
        if ($name === '' || $this->isHeaderLike($name)) {
            return null;
        }

        $brand = $this->resolveBrandText($cells[8] ?? '', $name);
        if ($brand === '') {
            return null;
        }

        $price = $this->money($cells[4] ?? '') ?? $this->money($cells[2] ?? '');
        $retail = $this->money($cells[5] ?? '') ?? $this->money($cells[3] ?? '');
        $stockText = $this->clean($cells[6] ?? '');
        $stockQuantity = $this->stockQuantity($stockText);
        $article = $this->supplierArticle($brand, $name, $sourceUrl);

        return [
            'gid' => $gid,
            'sheet_row' => $sheetRow,
            'name' => $name,
            'brand' => $brand,
            'country' => $this->clean($cells[9] ?? ''),
            'short_description' => $this->clean($cells[10] ?? ''),
            'warranty' => $this->clean($cells[7] ?? ''),
            'price_byn' => $price,
            'retail_byn' => $retail,
            'stock_text' => $stockText,
            'stock_quantity' => $stockQuantity,
            'in_stock' => $this->inStock($stockText, $stockQuantity),
            'source_url' => $sourceUrl,
            'supplier_article' => $article,
            'norm_article' => $this->normArticle($article),
        ];
    }

    private function isHeaderLike(string $value): bool
    {
        $low = mb_strtolower($value);

        return str_contains($low, 'модель')
            || str_contains($low, 'характеристика')
            || str_contains($low, 'отдел продаж')
            || str_contains($low, 'сервисный центр')
            || in_array($low, ['каракан', 'стэн', 'greolit', 'греолит'], true);
    }

    private function resolveBrandText(string $rawBrand, string $name): string
    {
        $text = mb_strtolower($rawBrand . ' ' . $name);

        if (str_contains($text, 'greolit') || str_contains($text, 'греолит')) {
            return 'Greolit';
        }

        if (str_contains($text, 'каракан')) {
            return 'Каракан';
        }

        if (str_contains($text, 'стэн') || str_contains($text, 'sten')) {
            return 'СТЭН';
        }

        return trim($rawBrand);
    }

    private function supplierArticle(string $brand, string $name, ?string $sourceUrl): string
    {
        if ($sourceUrl) {
            $path = trim((string) parse_url($sourceUrl, PHP_URL_PATH), '/');
            $slug = basename($path);
            if ($slug !== '') {
                return $slug;
            }
        }

        return $brand . ' ' . $name;
    }

    private function money(string $value): ?float
    {
        $value = trim(str_replace(["\xc2\xa0", ' '], '', $value));
        if ($value === '' || ! preg_match('/\d/', $value)) {
            return null;
        }

        $value = preg_replace('/[^\d,.\-]/u', '', $value) ?? '';
        if (str_contains($value, ',') && str_contains($value, '.')) {
            $value = str_replace(',', '', $value);
        } else {
            $value = str_replace(',', '.', $value);
        }

        return is_numeric($value) ? round((float) $value, 2) : null;
    }

    private function stockQuantity(string $value): ?int
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/>\s*(\d+)/u', $value, $match)) {
            return (int) $match[1] + 1;
        }

        if (preg_match('/^\d+$/', $value)) {
            return (int) $value;
        }

        return null;
    }

    private function inStock(string $text, ?int $quantity): bool
    {
        if ($quantity !== null) {
            return $quantity > 0;
        }

        $low = mb_strtolower($text);

        return str_contains($low, 'налич') || str_contains($low, 'склад') || str_contains($low, 'есть');
    }

    private function buildIndexes(): void
    {
        DB::table('brands')->get(['id', 'name'])->each(function ($brand) {
            $this->brandByKey[$this->brandKey((string) $brand->name)] = (int) $brand->id;
            $this->brandNameById[(int) $brand->id] = (string) $brand->name;
        });

        DB::table('categories')->get(['id', 'slug'])->each(function ($category) {
            $this->categoryBySlug[(string) $category->slug] = (int) $category->id;
        });

        $supplierId = $this->supplierId();
        if ($supplierId > 0) {
            DB::table('supplier_products')
                ->where('supplier_id', $supplierId)
                ->whereNotNull('product_id')
                ->get(['supplier_article_normalized', 'supplier_article', 'source_url', 'product_id'])
                ->each(function ($row) {
                    $productId = (int) $row->product_id;
                    foreach ([$row->supplier_article_normalized, $row->supplier_article] as $article) {
                        $key = $this->normArticle((string) $article);
                        if ($key !== '') {
                            $this->indexBySupplierArticle[$key] = $productId;
                        }
                    }

                    $sourceKey = $this->sourceKey((string) $row->source_url);
                    if ($sourceKey !== '') {
                        $this->indexBySourceUrl[$sourceKey] = $productId;
                    }
                });
        }

        DB::table('products')
            ->where('is_archived', false)
            ->get(['id', 'name', 'brand_id'])
            ->each(function ($product) {
                $productId = (int) $product->id;
                $nameKey = $this->nameKey((string) $product->name);
                if ($nameKey !== '') {
                    $this->indexByProductName[$nameKey] = $productId;
                }

                $signature = $this->modelSignature((string) $product->name);
                if ($signature !== '') {
                    $this->indexByModelSignature[$signature] = $productId;
                }

                $brandId = (int) $product->brand_id;
                if ($brandId > 0 && isset($this->brandNameById[$brandId])) {
                    $key = $this->brandKey($this->brandNameById[$brandId]) . '|' . $this->modelKey((string) $product->name, $this->brandNameById[$brandId]);
                    $this->indexByBrandName[$key] = $productId;
                }
            });
    }

    /**
     * @return array<string,mixed>
     */
    private function classify(array $row): array
    {
        $brandId = $this->brandByKey[$this->brandKey($row['brand'])] ?? null;
        $match = $this->match($row, $brandId);
        $categoryId = $this->categoryId($row);

        $action = match (true) {
            $match !== null => 'matched',
            $categoryId === null => 'category_missing',
            default => 'create_candidate',
        };

        return $row + [
            'brand_id' => $brandId,
            'category_id' => $categoryId,
            'matched_product_id' => $match['product_id'] ?? null,
            'match_confidence' => $match['confidence'] ?? null,
            'action' => $action,
        ];
    }

    private function match(array $row, ?int $brandId): ?array
    {
        if (isset($this->indexBySupplierArticle[$row['norm_article']])) {
            return ['product_id' => $this->indexBySupplierArticle[$row['norm_article']], 'confidence' => 'supplier_article'];
        }

        $sourceKey = $this->sourceKey((string) $row['source_url']);
        if ($sourceKey !== '' && isset($this->indexBySourceUrl[$sourceKey])) {
            return ['product_id' => $this->indexBySourceUrl[$sourceKey], 'confidence' => 'source_url'];
        }

        $nameKey = $this->nameKey($row['brand'] . ' ' . $row['name']);
        if (isset($this->indexByProductName[$nameKey])) {
            return ['product_id' => $this->indexByProductName[$nameKey], 'confidence' => 'exact_name'];
        }

        $plainNameKey = $this->nameKey($row['name']);
        if (isset($this->indexByProductName[$plainNameKey])) {
            return ['product_id' => $this->indexByProductName[$plainNameKey], 'confidence' => 'exact_plain_name'];
        }

        $signature = $this->modelSignature($row['brand'] . ' ' . $row['name']);
        if ($signature !== '' && isset($this->indexByModelSignature[$signature])) {
            return ['product_id' => $this->indexByModelSignature[$signature], 'confidence' => 'model_signature'];
        }

        $plainSignature = $this->modelSignature($row['name']);
        if ($plainSignature !== '' && isset($this->indexByModelSignature[$plainSignature])) {
            return ['product_id' => $this->indexByModelSignature[$plainSignature], 'confidence' => 'plain_model_signature'];
        }

        if ($brandId !== null) {
            $brand = $this->brandNameById[$brandId] ?? $row['brand'];
            $modelKey = $this->brandKey($brand) . '|' . $this->modelKey($row['name'], $brand);
            if (isset($this->indexByBrandName[$modelKey])) {
                return ['product_id' => $this->indexByBrandName[$modelKey], 'confidence' => 'brand_model'];
            }
        }

        return null;
    }

    private function categoryId(array $row): ?int
    {
        $name = mb_strtolower($row['name'] . ' ' . $row['short_description']);

        if (str_contains($name, 'дымоход')) {
            return $this->categoryBySlug['dymohody'] ?? $this->categoryBySlug['prochie-dymohod'] ?? null;
        }

        if (str_contains($name, 'клапан') || str_contains($name, 'заглуш') || str_contains($name, 'переходник')) {
            return $this->categoryBySlug['komplektuyushhie-dlya-otopleniya'] ?? null;
        }

        if (str_contains($name, 'тэн') || str_contains($name, 'электр')) {
            return $this->categoryBySlug['elektricheskie'] ?? $this->categoryBySlug['elektricheskie-teny-dlya-otopleniya'] ?? null;
        }

        if (str_contains($name, 'печь')) {
            return $this->categoryBySlug['pechi'] ?? $this->categoryBySlug['pechki'] ?? null;
        }

        if (str_contains($name, 'котел') || str_contains($name, 'котёл') || str_contains($name, 'каракан') || str_contains($name, 'стэн')) {
            return $this->categoryBySlug['tverdotoplivnye'] ?? $this->categoryBySlug['kotly'] ?? null;
        }

        return $this->categoryBySlug['komplektuyushhie-dlya-otopleniya'] ?? null;
    }

    private function report(array $rows): int
    {
        $this->newLine();
        $this->table(['metric', 'count'], [
            ['parsed rows', count($rows)],
            ['with source_url', count(array_filter($rows, fn ($row) => ! empty($row['source_url'])))],
            ['in stock', count(array_filter($rows, fn ($row) => $row['in_stock']))],
        ]);

        $this->info('Actions:');
        $this->table(['action', 'count'], $this->counts($rows, 'action'));

        $this->info('Brands:');
        $this->table(['brand', 'count'], $this->counts($rows, 'brand'));

        $this->info('Match confidence:');
        $this->table(['confidence', 'count'], $this->counts(array_filter($rows, fn ($row) => $row['matched_product_id'] !== null), 'match_confidence'));

        $this->info('Examples:');
        $this->table(
            ['gid', 'row', 'brand', 'name', 'opt', 'retail', 'qty', 'action', 'match', 'source'],
            array_map(fn ($row) => [
                $row['gid'],
                $row['sheet_row'],
                $row['brand'],
                mb_substr($row['name'], 0, 34),
                $row['price_byn'] ?? '-',
                $row['retail_byn'] ?? '-',
                $row['stock_quantity'] ?? $row['stock_text'],
                $row['action'],
                $row['matched_product_id'] ?: '-',
                $row['source_url'] ? mb_substr($row['source_url'], 0, 46) : '-',
            ], array_slice($rows, 0, 15))
        );

        $this->line('Next: run with --apply to update matched supplier links; add --create-new only after reviewing create_candidate rows.');

        return self::SUCCESS;
    }

    private function applyRows(array $rows, bool $createNew): int
    {
        $now = now();
        $supplierId = $this->ensureSupplier($now);
        $syncId = $this->ensureSync($now);
        $stats = array_fill_keys(['matched', 'created', 'updated_retail', 'skipped', 'errors'], 0);

        foreach ($rows as $row) {
            try {
                $productId = (int) ($row['matched_product_id'] ?? 0);
                $created = false;
                if ($productId <= 0 && $createNew && $row['action'] === 'create_candidate') {
                    $productId = $this->createProduct($row, $now);
                    $row['matched_product_id'] = $productId;
                    $row['match_confidence'] = 'created_from_price';
                    $stats['created']++;
                    $created = true;
                }

                if ($productId <= 0) {
                    $stats['skipped']++;
                    continue;
                }

                $this->upsertSupplierProduct($row, $productId, $supplierId, $syncId, $now);
                $stats['matched']++;

                if ((bool) $this->option('sync-retail-prices') && $row['retail_byn'] !== null) {
                    DB::table('products')->where('id', $productId)->update([
                        'price' => $row['retail_byn'],
                        'updated_at' => $now,
                    ]);
                    $stats['updated_retail']++;
                }

                if ($created && (bool) $this->option('enrich-created') && ! empty($row['source_url'])) {
                    $product = \App\Models\Product::find($productId);
                    if ($product) {
                        app(ProductSourceEnricher::class)->enrich($product, (string) $row['source_url'], [
                            'dry_run' => false,
                            'force' => true,
                        ]);
                    }
                }
            } catch (\Throwable $e) {
                $stats['errors']++;
                $this->warn(sprintf('[error] %s: %s', $row['name'], $e->getMessage()));
            }
        }

        $this->table(['metric', 'count'], array_map(fn ($key, $value) => [$key, $value], array_keys($stats), array_values($stats)));

        return $stats['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function upsertSupplierProduct(array $row, int $productId, int $supplierId, ?int $syncId, $now): void
    {
        $productSku = (string) DB::table('products')->where('id', $productId)->value('sku');
        $payload = [
            'supplier_sync_id' => $syncId,
            'product_id' => $productId,
            'product_sku' => $productSku,
            'supplier_article' => $row['supplier_article'],
            'supplier_article_normalized' => $row['norm_article'],
            'supplier_name' => trim($row['brand'] . ' ' . $row['name']),
            'source_url' => $row['source_url'] ?: null,
            'price' => $row['price_byn'],
            'currency' => 'BYN',
            'currency_rate' => 1,
            'price_byn' => $row['price_byn'],
            'in_stock' => $row['in_stock'],
            'stock_quantity' => $row['stock_quantity'],
            'stock_status' => $row['in_stock'] ? 'in_stock' : 'out_of_stock',
            'stock_text' => $row['stock_text'] !== '' ? $row['stock_text'] : null,
            'delivery_days' => $row['in_stock'] ? 0 : null,
            'last_stock_synced_at' => $now,
            'match_status' => 'matched',
            'match_confidence' => $row['match_confidence'],
            'raw' => json_encode([
                'gid' => $row['gid'],
                'sheet_row' => $row['sheet_row'],
                'brand' => $row['brand'],
                'country' => $row['country'],
                'warranty' => $row['warranty'],
                'retail_byn' => $row['retail_byn'],
                'short_description' => $row['short_description'],
            ], JSON_UNESCAPED_UNICODE),
            'last_synced_at' => $now,
            'updated_at' => $now,
        ];

        if (Schema::hasColumn('supplier_products', 'supplier_article_compact')) {
            $payload['supplier_article_compact'] = $this->compactArticle($row['supplier_article']);
        }

        $existingId = DB::table('supplier_products')
            ->where('supplier_id', $supplierId)
            ->where('product_id', $productId)
            ->value('id');

        if ($existingId) {
            DB::table('supplier_products')->where('id', $existingId)->update($payload);
            return;
        }

        DB::table('supplier_products')->updateOrInsert(
            ['supplier_id' => $supplierId, 'supplier_article_normalized' => $row['norm_article']],
            $payload + ['created_at' => $now]
        );
    }

    private function createProduct(array $row, $now): int
    {
        $brandId = $row['brand_id'] ?? $this->findOrCreateBrand($row['brand'], $now);
        $name = trim($row['brand'] . ' ' . $row['name']);

        return (int) DB::table('products')->insertGetId([
            'category_id' => (int) $row['category_id'],
            'brand_id' => $brandId,
            'name' => $name,
            'slug' => $this->uniqueSlug($name),
            'h1' => $name,
            'sku' => $this->nextSku(),
            'price' => $row['retail_byn'] ?? 0,
            'currency' => 'BYN',
            'content' => $row['short_description'],
            'short_description' => $row['short_description'],
            'images' => json_encode([]),
            'specs' => json_encode([]),
            'service_info' => json_encode(array_filter([
                ['name' => 'Гарантия', 'value' => $row['warranty']],
                ['name' => 'Страна', 'value' => $row['country']],
            ], fn ($item) => trim((string) $item['value']) !== ''), JSON_UNESCAPED_UNICODE),
            'unit' => 'шт',
            'warranty' => $row['warranty'] !== '' ? $row['warranty'] : null,
            'is_active' => true,
            'is_archived' => false,
            'in_stock' => $row['in_stock'],
            'stock_qty' => $row['stock_quantity'],
            'availability_status' => $row['in_stock'] ? 'in_stock' : 'out_of_stock',
            'is_new' => true,
            'meta_title' => $name . ' купить в %city%',
            'meta_description' => $name . ' — купить в Беларуси.',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function findOrCreateBrand(string $name, $now): int
    {
        $key = $this->brandKey($name);
        if (isset($this->brandByKey[$key])) {
            return $this->brandByKey[$key];
        }

        $id = (int) DB::table('brands')->insertGetId([
            'name' => $name,
            'slug' => $this->uniqueBrandSlug($name),
            'h1' => $name,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->brandByKey[$key] = $id;
        $this->brandNameById[$id] = $name;

        return $id;
    }

    private function ensureSupplier($now): int
    {
        $existing = DB::table('suppliers')->where('code', self::SUPPLIER_CODE)->first();
        if ($existing) {
            DB::table('suppliers')->where('id', $existing->id)->update([
                'name' => self::SUPPLIER_NAME,
                'currency' => 'BYN',
                'currency_rate' => 1,
                'contact' => self::SOURCE_URL,
                'is_active' => true,
                'updated_at' => $now,
            ]);
            return (int) $existing->id;
        }

        return (int) DB::table('suppliers')->insertGetId([
            'code' => self::SUPPLIER_CODE,
            'name' => self::SUPPLIER_NAME,
            'currency' => 'BYN',
            'currency_rate' => 1,
            'contact' => self::SOURCE_URL,
            'notes' => 'Цены и остатки из Google Sheets. Source URL берется из ссылок в прайсе.',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function ensureSync($now): ?int
    {
        if (! Schema::hasTable('supplier_syncs')) {
            return null;
        }

        DB::table('supplier_syncs')->updateOrInsert(
            ['key' => self::SYNC_KEY],
            [
                'name' => self::SUPPLIER_NAME,
                'code' => self::SUPPLIER_CODE,
                'title' => 'Майтек Групп: цены и остатки',
                'description' => 'Google Sheets прайс: СТЭН, Каракан, Greolit. Обновляет supplier_products и source_url.',
                'command' => 'supplier:sync-maitek-group',
                'source_url' => self::SOURCE_URL,
                'image_disk_path' => 'img/products/maitek-group',
                'is_active' => true,
                'last_run_at' => $now,
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );

        return (int) DB::table('supplier_syncs')->where('key', self::SYNC_KEY)->value('id');
    }

    private function supplierId(): int
    {
        return (int) (DB::table('suppliers')->where('code', self::SUPPLIER_CODE)->value('id') ?? 0);
    }

    /**
     * @return array<string,int>
     */
    private function counts(array $rows, string $key): array
    {
        $counts = [];
        foreach ($rows as $row) {
            $value = (string) ($row[$key] ?? '-');
            $value = $value !== '' ? $value : '-';
            $counts[$value] = ($counts[$value] ?? 0) + 1;
        }
        arsort($counts);

        return array_map(fn ($key, $value) => [$key, $value], array_keys($counts), array_values($counts));
    }

    /**
     * @return array<string,bool>
     */
    private function brandFilter(): array
    {
        $brands = $this->csvOption('brand');
        if ($brands === []) {
            $brands = self::DEFAULT_BRANDS;
        }

        return array_fill_keys(array_map(fn ($brand) => $this->brandKey($brand), $brands), true);
    }

    /**
     * @return string[]
     */
    private function csvOption(string $name): array
    {
        $values = $this->option($name) ?: [];
        if (! is_array($values)) {
            $values = [$values];
        }

        $result = [];
        foreach ($values as $value) {
            foreach (explode(',', (string) $value) as $part) {
                $part = trim($part);
                if ($part !== '') {
                    $result[] = $part;
                }
            }
        }

        return array_values(array_unique($result));
    }

    private function fetch(string $url): ?string
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 60,
                'follow_location' => 1,
                'max_redirects' => 10,
                'header' => "User-Agent: Mozilla/5.0 (compatible; KotlovBot/1.0)\r\nAccept: */*\r\n",
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ]);

        $content = @file_get_contents($url, false, $context);

        return $content === false ? null : $content;
    }

    private function clean(string $value): string
    {
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = str_replace(["\xc2\xa0", "\r"], [' ', ''], $value);
        $value = preg_replace('/[ \t]+/u', ' ', $value) ?? $value;
        $value = preg_replace("/\n{3,}/u", "\n\n", $value) ?? $value;

        return trim($value);
    }

    private function firstNonEmpty(string ...$values): string
    {
        foreach ($values as $value) {
            $value = $this->clean($value);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function normArticle(string $value): string
    {
        $value = mb_strtoupper(trim($value));
        $value = strtr($value, ['А' => 'A', 'В' => 'B', 'Е' => 'E', 'К' => 'K', 'М' => 'M', 'Н' => 'H', 'О' => 'O', 'Р' => 'P', 'С' => 'C', 'Т' => 'T', 'У' => 'Y', 'Х' => 'X']);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    private function compactArticle(string $value): string
    {
        return preg_replace('/[^A-ZА-ЯЁ0-9]+/u', '', $this->normArticle($value)) ?? '';
    }

    private function brandKey(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = str_replace('ё', 'е', $value);

        return $value;
    }

    private function sourceKey(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $parts = parse_url($value);
        $host = mb_strtolower((string) ($parts['host'] ?? ''));
        $path = trim((string) ($parts['path'] ?? ''), '/');

        return $host . '/' . $path;
    }

    private function nameKey(string $value): string
    {
        $value = mb_strtolower($value);
        $value = str_replace(['ё', '"', "'", '«', '»'], ['е', '', '', '', ''], $value);
        $value = preg_replace('/[^a-zа-я0-9]+/u', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }

    private function modelKey(string $name, string $brand): string
    {
        $name = $this->nameKey($name);
        $brand = $this->nameKey($brand);
        if ($brand !== '') {
            $name = trim(preg_replace('/\b' . preg_quote($brand, '/') . '\b/u', '', $name) ?? $name);
        }

        foreach (['котел', 'котёл', 'печь', 'дымоход', 'клапан', 'заглушка', 'переходник'] as $word) {
            $name = trim(preg_replace('/\b' . preg_quote($word, '/') . '\b/u', '', $name) ?? $name);
        }

        return trim(preg_replace('/\s+/u', ' ', $name) ?? $name);
    }

    private function modelSignature(string $value): string
    {
        $value = $this->nameKey($value);
        $words = [
            'котел',
            'котёл',
            'котлы',
            'электрический',
            'твердотопливный',
            'уличный',
            'печь',
            'дымоход',
            'клапан',
            'заглушка',
            'переходник',
            'для',
            'и',
            'с',
            'на',
            'в',
            'стэн',
            'sten',
            'каракан',
            'greolit',
            'греолит',
            'new',
        ];

        foreach ($words as $word) {
            $value = trim(preg_replace('/\b' . preg_quote($word, '/') . '\b/u', ' ', $value) ?? $value);
        }

        $value = preg_replace('/\bквт\b/u', ' ', $value) ?? $value;
        $value = preg_replace('/\bkw\b/u', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return preg_replace('/[^a-zа-яё0-9]+/u', '', $value) ?? '';
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'maitek-product';
        $slug = $base;
        $i = 2;

        while (DB::table('products')->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }

    private function uniqueBrandSlug(string $name): string
    {
        $base = Str::slug($name) ?: Str::random(8);
        $slug = $base;
        $i = 2;

        while (DB::table('brands')->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }

    private function nextSku(): string
    {
        $max = DB::table('products')
            ->where('sku', 'like', 'KOTLOV-%')
            ->pluck('sku')
            ->map(fn ($sku) => preg_match('/^KOTLOV-(\d+)$/', (string) $sku, $match) ? (int) $match[1] : 0)
            ->max() ?? 0;

        $next = max(0, (int) $max) + 1;
        do {
            $sku = sprintf('KOTLOV-%06d', $next++);
        } while (DB::table('products')->where('sku', $sku)->exists());

        return $sku;
    }
}
