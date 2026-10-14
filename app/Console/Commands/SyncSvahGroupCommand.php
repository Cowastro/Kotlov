<?php

namespace App\Console\Commands;

use App\Services\PriceList\SvahGroupPriceList;
use App\Services\PriceList\SvahGroupCatalogContent;
use App\Services\SupplierProductAvailabilityService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SyncSvahGroupCommand extends Command
{
    protected $signature = 'supplier:sync-svah-group
        {--price-file= : Path to a supplier XLS/XLSX; on apply it becomes the cached source}
        {--apply : Link rows and update supplier prices and stock}
        {--dry-run : Explicitly preview without database writes}
        {--sync-retail-prices : Also update products.price from «Цена отпускная»}
        {--keep-missing-stock : Do not zero SVAH Group links absent from the current full price list}
        {--report= : CSV audit output path}';

    protected $description = 'Sync SVAH Group partner prices and stock; create missing TATRA, SVAH and HOT cards.';

    private const SUPPLIER_CODE = 'svah_group';

    private const SUPPLIER_NAME = 'СВАГ Груп';

    private const SYNC_KEY = 'svah_group_price_stock';

    private const CACHE_DIRECTORY = 'pricelists/svah-group';

    private const SUPPLIER_CONTACT = 'Виталий · +375 29 601-49-89';

    public function handle(
        SvahGroupPriceList $priceList,
        SvahGroupCatalogContent $catalogContent,
        SupplierProductAvailabilityService $availability,
    ): int {
        $apply = (bool) $this->option('apply') && ! (bool) $this->option('dry-run');
        $sourcePath = $this->sourcePath();

        $this->line($apply
            ? '<fg=red;options=bold>APPLY: цены, остатки и связи будут обновлены.</>'
            : '<fg=yellow;options=bold>DRY RUN: база данных не изменяется.</>');

        try {
            $rows = $priceList->parse($sourcePath);
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $supplierId = $this->supplierId();
        $siteProducts = $this->siteProducts($priceList);
        $existingLinks = $this->existingLinks($supplierId);
        $classified = $this->classify($rows, $siteProducts, $existingLinks);
        $reportRows = $this->auditRows($classified, $siteProducts);
        $reportPath = $this->writeReport($reportRows);
        $stats = collect($classified)->countBy('status');
        $contentReady = count(array_filter($classified, function (array $item) use ($catalogContent): bool {
            return $this->catalogProfileIsReady($catalogContent->profile($item['row']));
        }));

        $this->table(['Статус', 'Количество'], [
            ['Совпало', $stats->get('matched', 0)],
            ['Будут созданы', $stats->get('supplier_only', 0)],
            ['Неоднозначно', $stats->get('ambiguous', 0)],
            ['Только на сайте', count(array_filter($reportRows, fn (array $row): bool => $row['status'] === 'site_only'))],
        ]);
        $this->line('Аудит: '.$reportPath);
        $this->line('metric.svah_group.price_rows='.count($classified));
        $this->line('metric.svah_group.matched='.$stats->get('matched', 0));
        $this->line('metric.svah_group.supplier_only='.$stats->get('supplier_only', 0));
        $this->line('metric.svah_group.ambiguous='.$stats->get('ambiguous', 0));
        $this->line('metric.svah_group.site_only='.count(array_filter(
            $reportRows,
            fn (array $row): bool => $row['status'] === 'site_only',
        )));
        $this->line('metric.svah_group.content_ready='.$contentReady);
        $this->line('metric.svah_group.content_missing='.max(0, count($classified) - $contentReady));

        foreach ($classified as $item) {
            $product = $item['product'];
            $this->line(sprintf(
                '  %-13s %-20s %s',
                $item['status'],
                $product ? ($product->sku ?: '#'.$product->id) : '—',
                $item['row']['name'],
            ));
        }

        if (! $apply) {
            return self::SUCCESS;
        }

        $now = now();
        $supplierId = $this->ensureSupplier($now);
        $syncId = $this->ensureSync($now);
        $touchedProductIds = [];
        $presentArticles = [];
        $applied = 0;
        $created = 0;

        DB::transaction(function () use (
            $classified,
            $supplierId,
            $syncId,
            $catalogContent,
            $now,
            &$touchedProductIds,
            &$presentArticles,
            &$applied,
            &$created,
        ): void {
            foreach ($classified as $item) {
                $row = $item['row'];
                $presentArticles[] = $row['article'];
                if ($item['status'] === 'ambiguous') {
                    continue;
                }

                $product = $item['product'];
                $matchMethod = $item['match_method'];
                $profile = $catalogContent->profile($row);
                if ($product === null) {
                    $product = $this->createProduct($row, $profile, $now);
                    $matchMethod = 'created_from_pricelist';
                    $created++;
                } elseif ($profile !== null) {
                    $product = $this->refreshCuratedProduct($product, $row, $profile, $now);
                }

                $this->upsertSupplierProduct($supplierId, $syncId, $product, $row, $matchMethod, $now);
                if ((bool) $this->option('sync-retail-prices') && $item['product'] !== null) {
                    DB::table('products')->where('id', $product->id)->update([
                        'price' => $row['retail_price'],
                        'updated_at' => $now,
                    ]);
                }
                $touchedProductIds[] = (int) $product->id;
                $applied++;
            }

            if (! (bool) $this->option('keep-missing-stock')) {
                $missing = DB::table('supplier_products')
                    ->where('supplier_id', $supplierId)
                    ->whereNotIn('supplier_article', $presentArticles)
                    ->get(['id', 'product_id']);

                if ($missing->isNotEmpty()) {
                    DB::table('supplier_products')->whereIn('id', $missing->pluck('id'))->update([
                        'in_stock' => false,
                        'stock_quantity' => 0,
                        'stock_status' => 'out_of_stock',
                        'stock_text' => 'Нет в актуальном прайсе СВАГ Груп',
                        'last_stock_synced_at' => $now,
                        'updated_at' => $now,
                    ]);
                    $touchedProductIds = array_merge(
                        $touchedProductIds,
                        $missing->pluck('product_id')->filter()->map(fn ($id): int => (int) $id)->all(),
                    );
                }
            }
        });

        $availability->refreshMany($touchedProductIds, $now);
        $this->cacheSource($sourcePath);
        $this->finishSync($syncId, $now);

        $this->info(sprintf(
            'Обновлено связей: %d. Создано черновиков: %d. Розничные цены: %s.',
            $applied,
            $created,
            $this->option('sync-retail-prices') ? 'обновлены' : 'без изменений',
        ));
        $this->line('metric.svah_group.created='.$created);

        if ($stats->get('ambiguous', 0) > 0) {
            $this->warn('Есть неоднозначные совпадения: они не применены, проверьте CSV-аудит.');
        }

        return self::SUCCESS;
    }

    private function sourcePath(): string
    {
        $provided = trim((string) $this->option('price-file'));
        if ($provided !== '') {
            return $provided;
        }

        $cached = glob(storage_path('app/'.self::CACHE_DIRECTORY.'/latest.{xls,xlsx}'), GLOB_BRACE) ?: [];
        usort($cached, fn (string $left, string $right): int => filemtime($right) <=> filemtime($left));

        return $cached[0] ?? storage_path('app/'.self::CACHE_DIRECTORY.'/latest.xlsx');
    }

    /** @return array<int, object> */
    private function siteProducts(SvahGroupPriceList $priceList): array
    {
        $brandIds = DB::table('brands')
            ->where(function ($query): void {
                $query->where('name', 'like', '%Татра%')
                    ->orWhere('name', 'like', '%Tatra%')
                    ->orWhere('name', 'like', '%SVAH%')
                    ->orWhere('name', 'like', '%HOT%');
            })
            ->pluck('id');

        return DB::table('products as p')
            ->leftJoin('brands as b', 'b.id', '=', 'p.brand_id')
            ->where(function ($query) use ($brandIds): void {
                $query->where('p.name', 'like', '%Татра%')
                    ->orWhere('p.name', 'like', '%Tatra%')
                    ->orWhere('p.name', 'like', '%SVAH%')
                    ->orWhere('p.name', 'like', '%HOT%');
                if ($brandIds->isNotEmpty()) {
                    $query->orWhereIn('p.brand_id', $brandIds);
                }
            })
            ->where(function ($query): void {
                $query->where('p.is_archived', false)->orWhereNull('p.is_archived');
            })
            ->get(['p.id', 'p.sku', 'p.name', 'p.price', 'p.in_stock', 'p.stock_qty', 'p.images', 'b.name as brand_name'])
            ->map(function (object $product) use ($priceList): object {
                $product->svah_signature = $priceList->signature(
                    (string) $product->name,
                    $this->brandCode((string) $product->brand_name.' '.(string) $product->name),
                );

                return $product;
            })
            ->filter(fn (object $product): bool => $product->svah_signature !== null)
            ->values()
            ->all();
    }

    /** @return array<string, object> */
    private function existingLinks(int $supplierId): array
    {
        if ($supplierId <= 0) {
            return [];
        }

        return DB::table('supplier_products as sp')
            ->join('products as p', 'p.id', '=', 'sp.product_id')
            ->where('sp.supplier_id', $supplierId)
            ->get(['sp.supplier_article', 'p.id', 'p.sku', 'p.name', 'p.price', 'p.in_stock', 'p.stock_qty', 'p.images'])
            ->keyBy('supplier_article')
            ->all();
    }

    private function classify(array $rows, array $siteProducts, array $existingLinks): array
    {
        $bySignature = collect($siteProducts)->groupBy('svah_signature');

        return array_map(function (array $row) use ($bySignature, $existingLinks): array {
            if (isset($existingLinks[$row['article']])) {
                return [
                    'row' => $row,
                    'status' => 'matched',
                    'match_method' => 'exact_supplier_article',
                    'product' => $existingLinks[$row['article']],
                ];
            }

            $candidates = $bySignature->get($row['signature'], collect());
            if ($candidates->count() === 1) {
                return [
                    'row' => $row,
                    'status' => 'matched',
                    'match_method' => 'exact_model',
                    'product' => $candidates->first(),
                ];
            }

            return [
                'row' => $row,
                'status' => $candidates->count() > 1 ? 'ambiguous' : 'supplier_only',
                'match_method' => null,
                'product' => null,
            ];
        }, $rows);
    }

    private function auditRows(array $classified, array $siteProducts): array
    {
        $rows = [];
        $matchedProductIds = [];

        foreach ($classified as $item) {
            $product = $item['product'];
            if ($product !== null) {
                $matchedProductIds[(int) $product->id] = true;
            }
            $source = $item['row'];
            $rows[] = [
                'status' => $item['status'],
                'supplier_article' => $source['article'],
                'supplier_name' => $source['name'],
                'partner_price_byn' => number_format($source['partner_price'], 2, '.', ''),
                'retail_price_byn' => number_format($source['retail_price'], 2, '.', ''),
                'stock_quantity' => $source['stock_quantity'],
                'product_id' => $product->id ?? '',
                'product_sku' => $product->sku ?? '',
                'product_name' => $product->name ?? '',
                'current_retail_price_byn' => $product ? number_format((float) $product->price, 2, '.', '') : '',
                'match_method' => $item['match_method'] ?? '',
            ];
        }

        foreach ($siteProducts as $product) {
            if (isset($matchedProductIds[(int) $product->id])) {
                continue;
            }
            $rows[] = [
                'status' => 'site_only',
                'supplier_article' => '',
                'supplier_name' => '',
                'partner_price_byn' => '',
                'retail_price_byn' => '',
                'stock_quantity' => '',
                'product_id' => $product->id,
                'product_sku' => $product->sku,
                'product_name' => $product->name,
                'current_retail_price_byn' => number_format((float) $product->price, 2, '.', ''),
                'match_method' => '',
            ];
        }

        return $rows;
    }

    private function writeReport(array $rows): string
    {
        $path = trim((string) $this->option('report'));
        if ($path === '') {
            $path = storage_path('app/reports/suppliers/svah-group/'.now()->format('Y-m-d_His').'.csv');
        }
        File::ensureDirectoryExists(dirname($path));

        $handle = fopen($path, 'wb');
        if ($handle === false) {
            throw new \RuntimeException("Не удалось создать отчёт: {$path}");
        }

        fwrite($handle, "\xEF\xBB\xBF");
        $headers = array_keys($rows[0] ?? [
            'status' => '', 'supplier_article' => '', 'supplier_name' => '',
        ]);
        fputcsv($handle, $headers, ';');
        foreach ($rows as $row) {
            fputcsv($handle, $row, ';');
        }
        fclose($handle);

        return $path;
    }

    private function upsertSupplierProduct(
        int $supplierId,
        ?int $syncId,
        object $product,
        array $row,
        string $matchMethod,
        $now,
    ): void {
        $payload = [
            'supplier_sync_id' => $syncId,
            'product_id' => $product->id,
            'product_sku' => $product->sku,
            'supplier_article_normalized' => mb_strtolower($row['article']),
            'supplier_article_compact' => mb_strtolower(str_replace(['-', '_', ' '], '', $row['article'])),
            'supplier_name' => $row['name'],
            'price' => $row['partner_price'],
            'currency' => 'BYN',
            'currency_rate' => 1,
            'price_byn' => $row['partner_price'],
            'in_stock' => $row['stock_quantity'] > 0,
            'stock_quantity' => $row['stock_quantity'],
            'stock_status' => $row['stock_quantity'] > 0 ? 'in_stock' : 'out_of_stock',
            'stock_text' => $row['stock_quantity'].' шт.',
            'match_status' => 'matched',
            'match_confidence' => '100',
            'raw' => json_encode([
                'section' => $row['section'],
                'source_row' => $row['source_row'],
                'retail_price' => $row['retail_price'],
                'match_method' => $matchMethod,
            ], JSON_UNESCAPED_UNICODE),
            'last_synced_at' => $now,
            'last_stock_synced_at' => $now,
            'updated_at' => $now,
        ];

        DB::table('supplier_products')->updateOrInsert(
            ['supplier_id' => $supplierId, 'supplier_article' => $row['article']],
            $payload + ['created_at' => $now],
        );
    }

    private function createProduct(array $row, ?array $profile, $now): object
    {
        $brandId = $this->ensureBrand($row['brand'], $now);
        $categoryId = $this->categoryId($row['signature']);
        $name = trim((string) ($profile['name'] ?? $row['name']));
        $productId = (int) DB::table('products')->insertGetId([
            'category_id' => $categoryId,
            'brand_id' => $brandId,
            'name' => $name,
            'h1' => $name,
            'sku' => $this->nextSku(),
            'slug' => $this->uniqueSlug($name),
            'price' => $row['retail_price'],
            'currency' => 'BYN',
            'short_description' => $profile['short_description'] ?? null,
            'content' => $profile['content'] ?? null,
            'images' => json_encode($profile['images'] ?? []),
            'specs' => json_encode($profile['specs'] ?? [], JSON_UNESCAPED_UNICODE),
            'content_source_url' => $profile['source_url'] ?? null,
            'unit' => 'шт',
            // The price list has no photos or full descriptions. Keep new
            // cards unpublished until content review, but immediately link
            // supplier price and stock so the operational data stays current.
            'is_active' => $this->catalogProfileIsReady($profile),
            'is_archived' => false,
            'in_stock' => $row['stock_quantity'] > 0,
            'availability_status' => $row['stock_quantity'] > 0 ? 'in_stock' : 'check',
            'stock_qty' => $row['stock_quantity'],
            'is_new' => true,
            'meta_title' => $name.' купить в %city%',
            'meta_description' => $name.' — цена и наличие в каталоге KOTLOV.',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return DB::table('products')->where('id', $productId)->firstOrFail();
    }

    private function refreshCuratedProduct(object $product, array $row, array $profile, $now): object
    {
        $name = trim((string) $profile['name']);
        DB::table('products')->where('id', $product->id)->update([
            'category_id' => $this->categoryId($row['signature']),
            'brand_id' => $this->ensureBrand($row['brand'], $now),
            'name' => $name,
            'h1' => $name,
            'short_description' => $profile['short_description'],
            'content' => $profile['content'],
            'images' => $profile['images'] === null
                ? ($product->images ?? json_encode([]))
                : json_encode($profile['images'], JSON_UNESCAPED_UNICODE),
            'specs' => json_encode($profile['specs'], JSON_UNESCAPED_UNICODE),
            'content_source_url' => $profile['source_url'],
            'requires_price_confirmation' => false,
            'meta_title' => $name.' — купить в Беларуси | KOTLOV',
            'meta_description' => 'Купить '.$name.' в KOTLOV: характеристики, цена, наличие и доставка по Беларуси.',
            'is_active' => $this->catalogProfileIsReady($profile),
            'is_archived' => false,
            'updated_at' => $now,
        ]);

        return DB::table('products')->where('id', $product->id)->firstOrFail();
    }

    private function ensureBrand(string $name, $now): int
    {
        $aliases = match ($name) {
            'Tatra' => ['Tatra', 'Татра'],
            'SVAH' => ['SVAH', 'СВАХ'],
            'HOT' => ['HOT'],
            default => [$name],
        };
        $existing = DB::table('brands')->whereIn('name', $aliases)->value('id');
        if ($existing) {
            return (int) $existing;
        }

        return (int) DB::table('brands')->insertGetId([
            'name' => $name,
            'slug' => $this->uniqueBrandSlug($name),
            'h1' => $name,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function catalogProfileIsReady(?array $profile): bool
    {
        return $profile !== null
            && trim((string) ($profile['short_description'] ?? '')) !== ''
            && trim((string) ($profile['content'] ?? '')) !== ''
            && trim((string) ($profile['source_url'] ?? '')) !== ''
            && count((array) ($profile['images'] ?? [])) > 0
            && count((array) ($profile['specs'] ?? [])) >= 15;
    }

    private function categoryId(string $signature): int
    {
        $slug = str_contains($signature, ':boiler:') ? 'tverdotoplivnye' : 'drovyanye-pechi-dlya-bani';
        $fallbackId = str_contains($signature, ':boiler:') ? 54 : 69;
        $id = DB::table('categories')->where('slug', $slug)->value('id')
            ?? DB::table('categories')->where('id', $fallbackId)->value('id');

        if (! $id) {
            throw new \RuntimeException("Не найдена категория {$slug} для создания карточки.");
        }

        return (int) $id;
    }

    private function brandCode(string $value): ?string
    {
        return match (true) {
            preg_match('/(?:татра|tatra)/iu', $value) === 1 => 'tatra',
            preg_match('/\bsvah\b/iu', $value) === 1 => 'svah',
            preg_match('/\bhot\b/iu', $value) === 1 => 'hot',
            default => null,
        };
    }

    private function nextSku(): string
    {
        $max = DB::table('products')->where('sku', 'like', 'KOTLOV-%')->pluck('sku')
            ->map(fn ($sku): int => preg_match('/^KOTLOV-(\d+)$/', (string) $sku, $match) ? (int) $match[1] : 0)
            ->max() ?? 0;
        $next = max(0, (int) $max) + 1;
        do {
            $sku = sprintf('KOTLOV-%06d', $next++);
        } while (DB::table('products')->where('sku', $sku)->exists());

        return $sku;
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'svah-group-product';
        $slug = $base;
        $suffix = 2;
        while (DB::table('products')->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    private function uniqueBrandSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'svah-brand';
        $slug = $base;
        $suffix = 2;
        while (DB::table('brands')->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    private function supplierId(): int
    {
        return (int) (DB::table('suppliers')->where('code', self::SUPPLIER_CODE)->value('id') ?? 0);
    }

    private function ensureSupplier($now): int
    {
        DB::table('suppliers')->updateOrInsert(
            ['code' => self::SUPPLIER_CODE],
            [
                'name' => self::SUPPLIER_NAME,
                'currency' => 'BYN',
                'currency_rate' => 1,
                'contact' => self::SUPPLIER_CONTACT,
                'notes' => 'Партнёрский прайс TATRA, SVAH и HOT: котлы и банные печи.',
                'is_active' => true,
                'updated_at' => $now,
                'created_at' => $now,
            ],
        );

        return $this->supplierId();
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
                'title' => 'СВАГ Груп: цены и остатки',
                'description' => 'XLS/XLSX-канал партнёрских цен, отпускных цен и остатков TATRA, SVAH и HOT.',
                'command' => 'supplier:sync-svah-group',
                'is_active' => true,
                'updated_at' => $now,
                'created_at' => $now,
            ],
        );

        return (int) DB::table('supplier_syncs')->where('key', self::SYNC_KEY)->value('id');
    }

    private function finishSync(?int $syncId, $now): void
    {
        if (! $syncId) {
            return;
        }

        DB::table('supplier_syncs')->where('id', $syncId)->update([
            'last_run_at' => $now,
            'last_status' => 'updated',
            'last_exit_code' => self::SUCCESS,
            'updated_at' => $now,
        ]);
    }

    private function cacheSource(string $sourcePath): void
    {
        $extension = mb_strtolower((string) pathinfo($sourcePath, PATHINFO_EXTENSION));
        $cachePath = storage_path('app/'.self::CACHE_DIRECTORY.'/latest.'.($extension === 'xls' ? 'xls' : 'xlsx'));
        if (realpath($sourcePath) === realpath($cachePath)) {
            return;
        }

        File::ensureDirectoryExists(dirname($cachePath));
        if (! copy($sourcePath, $cachePath)) {
            throw new \RuntimeException('Не удалось сохранить актуальный прайс в кэш канала.');
        }
    }
}
