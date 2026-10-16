<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\Category;
use App\Models\CrmContactPoint;
use App\Models\CrmCounterparty;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\SupplierSource;
use App\Models\SupplierSync;
use App\Services\SupplierProductAvailabilityService;
use App\Services\Suppliers\OmenergoCatalog;
use App\Services\Suppliers\OmenergoPriceList;
use App\Services\Telephony\PhoneNumber;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class SyncOmenergoCommand extends Command
{
    protected $signature = 'supplier:sync-omenergo
        {--apply : Apply prices and availability to existing links}
        {--dry-run : Preview only}
        {--create-new : Create missing catalog products after review}
        {--match-existing : Bind exact existing catalog identities during onboarding only}
        {--confirm-pricing : Confirm D2 as BYN purchase incl VAT and MRC as retail}
        {--update-content : Populate verified sheet content, independent of daily price sync}
        {--csv-file= : Read a saved source CSV instead of downloading}
        {--review-two : Export only the two explicitly authorized unresolved identity rows; dry-run only}
        {--report= : Write a complete review CSV to this path}';

    protected $description = 'Omenergo: dealer Google Sheet, exact catalog matching, CRM contacts and safe daily updates.';

    public function handle(OmenergoPriceList $parser): int
    {
        $lock = Cache::lock('supplier:sync-omenergo', 1200);
        if (! $lock->get()) {
            $this->error('Omenergo sync is already running.');

            return self::FAILURE;
        }
        $sync = null;
        try {
            if ($this->option('review-two') && (! $this->option('dry-run') || $this->option('apply'))) {
                throw new RuntimeException('The limited identity report requires --dry-run without --apply.');
            }
            $csv = $this->option('csv-file')
                ? file_get_contents($this->option('csv-file'))
                : Http::timeout(60)->retry(2, 1000)->get(OmenergoPriceList::CSV_URL)->throw()->body();
            $rows = $parser->parse($csv);
            if (count($rows) < 150) {
                throw new RuntimeException('Omenergo: unexpectedly short feed; import stopped before any writes.');
            }
            $supplier = Supplier::where('code', 'omenergo')->first();
            if ($supplier && ! $supplier->is_active) {
                throw new RuntimeException('Omenergo supplier is disabled.');
            }
            $classified = $this->classify($rows, $parser, $supplier);
            if ($this->option('review-two')) {
                $review = array_values(array_filter($classified, fn ($row) => $row['action'] === 'review_similar_model'));
                if (count($review) !== 2) {
                    throw new RuntimeException('The authorized report must contain exactly two unresolved rows.');
                }
                $path = storage_path('app/reports/omenergo/two-identities.csv');
                if (! is_dir(dirname($path))) {
                    mkdir(dirname($path), 0775, true);
                }
                $file = fopen($path, 'wb');
                fputcsv($file, ['supplier_article', 'name', 'product_id', 'candidate'], ',', '"', '');
                foreach ($review as $row) {
                    fputcsv($file, [$row['article'], $row['name'], $row['product_id'], implode(' | ', $row['suggestions'])], ',', '"', '');
                }
                fclose($file);
                $this->line('metric.omenergo.limited_review_rows=2');

                return self::SUCCESS;
            }
            $this->report($classified);
            $this->auditCurrentState($supplier, $rows);
            $apply = $this->option('apply') && ! $this->option('dry-run');
            if (! $apply) {
                return self::SUCCESS;
            }
            $source = $supplier?->sources()->where('name', 'Дилерский онлайн-прайс ОмЭнерго')->first();
            if ($source && ! $source->is_active) {
                throw new RuntimeException('Omenergo price source is disabled.');
            }
            if (! $this->option('confirm-pricing') && ! data_get($source?->config, 'pricing_confirmed')) {
                throw new RuntimeException('Confirm D2/MRC currency and VAT using --confirm-pricing before applying.');
            }
            $archive = 'supplier-sync-sources/omenergo/'.now()->format('Ymd_His_u').'.csv';
            Storage::disk('local')->put($archive, $csv);
            $this->saveSnapshot($supplier, $classified);
            $stats = DB::transaction(function () use ($classified, $parser, &$supplier, &$sync, $archive): array {
                [$supplier, $source, $sync] = $this->ensureSupplier();
                $stats = ['linked' => 0, 'created' => 0, 'review' => 0, 'content' => 0];
                $touched = [];
                foreach ($classified as $row) {
                    $product = $row['product_id'] ? Product::findOrFail($row['product_id']) : null;
                    $linked = $supplier->supplierProducts()->where('supplier_article_normalized', $row['article_key'])->whereNotNull('product_id')->exists();
                    if (! $linked && ! $this->option('match-existing') && ! $this->option('create-new')) {
                        $stats['review']++;

                        continue;
                    }
                    if ($product === null && $row['action'] === 'create_candidate' && $this->option('create-new')) {
                        $product = $this->createProduct($row, $parser, $supplier);
                        $stats['created']++;
                    }
                    if ($product === null) {
                        $stats['review']++;

                        continue;
                    }
                    $existing = SupplierProduct::where('supplier_id', $supplier->id)->where('supplier_article_normalized', $row['article_key'])->first();
                    if ($existing && $existing->product_id && (int) $existing->product_id !== $product->id) {
                        throw new RuntimeException('Existing supplier article binding changed during sync.');
                    }
                    SupplierProduct::updateOrCreate([
                        'supplier_id' => $supplier->id, 'supplier_article_normalized' => $row['article_key'],
                    ], [
                        'supplier_sync_id' => $sync->id, 'product_id' => $product->id, 'product_sku' => $product->sku,
                        'supplier_article' => $row['article'], 'supplier_article_compact' => $row['article_key'],
                        'supplier_name' => $row['name'], 'source_url' => $existing?->source_url ?: OmenergoPriceList::SHEET_URL,
                        'price' => $row['dealer'], 'price_byn' => $row['dealer'], 'currency' => 'BYN', 'currency_rate' => 1,
                        'in_stock' => $row['in_stock'], 'stock_quantity' => $row['quantity'],
                        'stock_status' => $row['stock_status'], 'stock_text' => $row['stock_text'],
                        'match_status' => 'matched', 'match_confidence' => $row['action'] === 'matched' ? 'exact_identity' : 'created_from_price',
                        'raw' => $row, 'last_synced_at' => now(), 'last_stock_synced_at' => now(),
                    ]);
                    $product->price = $row['retail'];
                    if ($this->option('update-content')) {
                        $product->short_description = $row['description'];
                        $product->content = $parser->content($row);
                        $specs = collect($product->specs ?? [])->keyBy(fn ($spec) => is_array($spec) ? ($spec['key'] ?? '') : '');
                        foreach ($parser->specs($row) as $spec) {
                            $specs->put($spec['key'], $spec);
                        }
                        $product->specs = $specs->values()->all();
                        $product->weight = $row['weight_kg'];
                        $product->warranty = $row['warranty_months'].' мес.';
                        $product->meta_title = $parser->productName($row).' — купить в %city% | KOTLOV';
                        $product->meta_description = Str::limit($row['description'].' Подбор и заказ в KOTLOV.', 240, '');
                        $stats['content']++;
                    }
                    $product->save();
                    $supplier->brands()->syncWithoutDetaching([$product->brand_id]);
                    $touched[] = $product->id;
                    $stats['linked']++;
                }
                // A vanished article may no longer confirm supplier stock. Keep
                // its price/history and don't touch other suppliers' balances.
                $missing = $supplier->supplierProducts()->whereNotIn('supplier_article_normalized', array_column($classified, 'article_key'));
                $touched = array_merge($touched, $missing->pluck('product_id')->filter()->all());
                $stats['missing'] = $missing->update(['in_stock' => false, 'stock_quantity' => null, 'stock_status' => 'missing_from_feed']);
                app(SupplierProductAvailabilityService::class)->refreshMany($touched);
                $source->forceFill(['last_run_at' => now()])->save();
                $sync->forceFill(['last_run_at' => now(), 'last_status' => 'updated', 'last_exit_code' => 0])->save();
                $source->importRuns()->create([
                    'status' => 'completed', 'file_path' => $archive, 'file_hash' => hash('sha256', Storage::disk('local')->get($archive)),
                    'stats' => $stats, 'started_at' => now(), 'finished_at' => now(),
                ]);

                return $stats;
            });
            foreach ($stats as $key => $value) {
                $this->line("metric.omenergo.{$key}={$value}");
            }
            Cache::forget('catalog:global-price-range');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $sync?->forceFill(['last_run_at' => now(), 'last_status' => 'failed', 'last_exit_code' => 1])->save();
            $this->error($e->getMessage());

            return self::FAILURE;
        } finally {
            $lock->release();
        }
    }

    private function classify(array $rows, OmenergoPriceList $parser, ?Supplier $supplier): array
    {
        $products = Product::with('brand')->get();
        $names = [];
        $modelAliases = [];
        $catalog = new OmenergoCatalog;
        $articles = [];
        foreach ($products as $product) {
            $names[$parser->nameKey($product->name)][] = $product->id;
            if ($product->brand) {
                $modelAliases[$parser->nameKey($product->brand->name).'|'.$catalog->key($product->name)][] = $product->id;
            }
            if ($product->sku) {
                $articles[$parser->articleKey($product->sku)][] = $product->id;
            }
        }
        foreach ($supplier?->supplierProducts ?? [] as $link) {
            if ($link->product_id && $products->contains('id', $link->product_id)) {
                $articles[$parser->articleKey($link->supplier_article)][] = $link->product_id;
            }
        }
        foreach ($rows as &$row) {
            $matches = $articles[$row['article_key']] ?? $articles['OMENERGO-'.$row['article_key']] ?? [];
            if ($matches === []) {
                foreach ([$row['name'], $row['invoice_name'], $parser->productName($row)] as $name) {
                    $matches = array_merge($matches, $names[$parser->nameKey($name)] ?? []);
                }
                $matches = array_merge($matches, $modelAliases[$parser->nameKey($row['brand']).'|'.$catalog->key($row['name'])] ?? []);
            }
            $matches = array_values(array_unique($matches));
            $row['product_id'] = count($matches) === 1 ? $matches[0] : null;
            $row['action'] = count($matches) > 1 ? 'ambiguous' : ($matches ? 'matched' : 'create_candidate');
            if ($row['product_id']) {
                $matchedProduct = $products->firstWhere('id', $row['product_id']);
                if ($matchedProduct->is_archived || ($matchedProduct->brand && $parser->nameKey($matchedProduct->brand->name) !== $parser->nameKey($row['brand']))) {
                    $row['product_id'] = null;
                    $row['action'] = 'review_archived_or_brand';
                }
            }
            $row['suggestions'] = [];
            if ($matches === []) {
                $key = $parser->nameKey($row['name']);
                foreach ($products as $product) {
                    if ($product->brand && $parser->nameKey($product->brand->name) === $parser->nameKey($row['brand'])) {
                        if ($catalog->distinctFamilies($row['name'], $product->name)) {
                            continue;
                        }
                        preg_match_all('/\d+(?:[.,]\d+)?/u', $row['name'], $sourceNumbers);
                        preg_match_all('/\d+(?:[.,]\d+)?/u', $product->name, $productNumbers);
                        if ($sourceNumbers[0] !== $productNumbers[0]) {
                            continue;
                        }
                        similar_text($key, $parser->nameKey($product->name), $score);
                        if ($score >= 88) {
                            $row['suggestions'][] = $product->id.':'.$product->name;
                        }
                    }
                }
                if ($row['suggestions'] !== []) {
                    $row['action'] = 'review_similar_model';
                }
            }
        }
        unset($row);
        $counts = array_count_values(array_filter(array_column($rows, 'product_id')));
        foreach ($rows as &$row) {
            if ($row['product_id'] && $counts[$row['product_id']] > 1) {
                $row['product_id'] = null;
                $row['action'] = 'duplicate_product_match';
            }
        }

        return $rows;
    }

    private function ensureSupplier(): array
    {
        $supplier = Supplier::firstOrCreate(['code' => 'omenergo'], ['name' => 'ООО «ОмЭнерго»', 'is_active' => true, 'currency' => 'BYN', 'currency_rate' => 1]);
        $company = $supplier->crmCounterparty
            ?: CrmCounterparty::whereHas('contactPoints', fn ($q) => $q->where('type', 'email')->where('normalized_value', 'info@omenergo.by'))->where('type', 'organization')->first()
            ?: CrmCounterparty::firstOrCreate(['type' => 'organization', 'legal_name' => 'ООО «ОмЭнерго»'], [
                'name' => 'ОмЭнерго', 'classification' => 'supplier', 'status' => 'active', 'source' => 'manual',
                'website' => 'https://omenergo.by/', 'actual_address' => 'г. Минск, ул. Каменногорская, д. 47, оф. 120',
            ]);
        foreach (['+375293413135' => 'A1', '+375295624925' => 'МТС', '+375172487745' => 'Офис', 'info@omenergo.by' => 'Email'] as $value => $label) {
            $type = str_contains($value, '@') ? 'email' : 'phone';
            CrmContactPoint::firstOrCreate(['counterparty_id' => $company->id, 'type' => $type, 'normalized_value' => $type === 'phone' ? PhoneNumber::normalize($value) : $value], [
                'value' => $value, 'label' => $label, 'is_active' => true, 'is_primary' => in_array($label, ['A1', 'Email'], true),
            ]);
        }
        $supplier->forceFill([
            'crm_counterparty_id' => $company->id,
            'contact' => '+375293413135; +375295624925; +375172487745; info@omenergo.by',
        ])->save();
        $source = SupplierSource::firstOrCreate(['supplier_id' => $supplier->id, 'name' => 'Дилерский онлайн-прайс ОмЭнерго']);
        $source->fill([
            'type' => 'google_sheet', 'config' => array_merge($source->config ?? [], [
                'url' => OmenergoPriceList::SHEET_URL, 'csv_url' => OmenergoPriceList::CSV_URL,
                'pricing_confirmed' => true, 'currency' => 'BYN', 'vat' => 'included',
                'purchase_column' => 'Д2', 'retail_column' => 'МРЦ',
                'contacts_source' => 'https://omenergo.by/', 'hours' => 'Пн–Пт 10:00–16:00',
                'photo_archive' => 'https://disk.yandex.ru/d/r0_vZo_LCZiLqg',
                'command' => 'supplier:sync-omenergo',
            ]),
            'column_mappings' => ['article' => 'A', 'name' => 'C', 'brand' => 'E', 'stock' => 'K', 'retail' => 'L', 'purchase' => 'M'],
            'schedule_cron' => 'Ежедневно 07:45 (Europe/Minsk); только связанные товары', 'is_active' => true,
        ])->save();
        $sync = SupplierSync::updateOrCreate(['key' => 'omenergo_price'], [
            'supplier_id' => $supplier->id, 'name' => 'ОмЭнерго', 'code' => 'omenergo', 'title' => 'ОмЭнерго: цены и наличие',
            'command' => 'supplier:sync-omenergo', 'source_url' => OmenergoPriceList::SHEET_URL,
            'description' => 'Д2: закупка BYN с НДС; МРЦ: розница. Звёздочки не конвертируются в выдуманные остатки. Пн–Пт 10:00–16:00.',
            'is_active' => true, 'image_disk_path' => 'img/products/omenergo',
        ]);

        return [$supplier, $source, $sync];
    }

    private function auditCurrentState(?Supplier $supplier, array $rows): void
    {
        $links = $supplier?->supplierProducts()->with('product')->get() ?? collect();
        $products = $links->pluck('product')->filter();
        $feed = collect($rows)->keyBy('article_key');
        $stats = [
            'supplier_exists' => $supplier ? 1 : 0,
            'crm_linked' => $supplier?->crm_counterparty_id ? 1 : 0,
            'contact_points' => $supplier?->crmCounterparty?->contactPoints()->where('is_active', true)->count() ?? 0,
            'source_enabled' => $supplier?->sources()->where('is_active', true)->count() ?? 0,
            'current_links' => $links->count(),
            'current_active_cards' => $products->where('is_active', true)->count(),
            'current_cards_with_images' => $products->filter(fn ($p) => ! empty($p->images))->count(),
            'current_cards_with_specs' => $products->filter(fn ($p) => count($p->specs ?? []) >= 9)->count(),
            'current_purchase_mismatches' => $links->filter(fn ($link) => $feed->has($link->supplier_article_normalized) && abs((float) $link->price_byn - (float) $feed[$link->supplier_article_normalized]['dealer']) > 0.005)->count(),
            'current_retail_mismatches' => $links->filter(fn ($link) => $link->product && $feed->has($link->supplier_article_normalized) && abs((float) $link->product->price - (float) $feed[$link->supplier_article_normalized]['retail']) > 0.005)->count(),
            'current_stale_links' => $links->filter(fn ($link) => ! $link->last_synced_at || $link->last_synced_at->lt(now()->subHours(24)))->count(),
            'current_invented_quantities' => $links->filter(fn ($link) => in_array($link->raw['stock_text'] ?? '', ['*', '**', '***'], true) && $link->stock_quantity !== null)->count(),
            'current_active_without_local_photos' => $products->filter(fn ($p) => $p->is_active && ! collect($p->images ?? [])->contains(fn ($path) => is_string($path) && str_starts_with($path, 'img/products/omenergo/') && is_file(public_path($path))))->count(),
            'current_inactive_with_images' => $products->filter(fn ($p) => ! $p->is_active && ! empty($p->images))->count(),
            'current_cards_with_local_documents' => $products->filter(fn ($p) => collect($p->documents ?? [])->contains(fn ($doc) => is_array($doc) && str_starts_with($doc['url'] ?? '', '/docs/omenergo/') && is_file(public_path(ltrim($doc['url'], '/')))))->count(),
            'current_local_image_files' => $products->sum(fn ($p) => count(array_filter($p->images ?? [], fn ($path) => is_string($path) && str_starts_with($path, 'img/products/omenergo/') && is_file(public_path($path))))),
        ];
        foreach ($stats as $key => $value) {
            $this->line("metric.omenergo.{$key}={$value}");
        }
    }

    private function createProduct(array $row, OmenergoPriceList $parser, Supplier $supplier): Product
    {
        $type = mb_strtolower($row['category']);
        [$slug, $categoryName] = match (true) {
            str_contains($type, 'стабилизаторы напряжения') => ['stabilizatory-napryazheniya', 'Стабилизаторы напряжения'],
            str_contains($type, 'бесперебойного') => ['istochniki-bespereboynogo-pitaniya', 'Источники бесперебойного питания'],
            str_contains($type, 'латр') => ['avtotransformatory-latr', 'Автотрансформаторы ЛАТР'],
            str_contains($type, 'дополнительные') => ['komplektuyushchie-stabilizatorov', 'Комплектующие для стабилизаторов'],
            default => throw new RuntimeException('Unknown Omenergo category.'),
        };
        $category = Category::where('slug', $slug)->first() ?: Category::where('name', $categoryName)->first();
        if (! $category) {
            $category = Category::create(['name' => $categoryName, 'slug' => $slug, 'h1' => $categoryName, 'is_active' => true]);
        }
        $brand = Brand::firstOrCreate(['name' => $row['brand']], ['slug' => Str::slug($row['brand']), 'is_active' => true]);
        $name = $parser->productName($row);
        $slug = Str::slug($name).'-'.Str::lower($row['article_key']);
        if (Product::where('slug', $slug)->exists()) {
            throw new RuntimeException('Slug collision: review archived catalog card before creating a duplicate.');
        }

        return Product::create([
            'category_id' => $category->id, 'brand_id' => $brand->id, 'supplier_id' => $supplier->id,
            'name' => $name, 'h1' => $name, 'slug' => $slug, 'sku' => 'OMENERGO-'.$row['article_key'],
            'price' => $row['retail'], 'currency' => 'BYN', 'unit' => 'шт',
            'is_active' => false, 'is_archived' => false, 'in_stock' => false, 'availability_status' => Product::AVAILABILITY_CHECK,
            'images' => [], 'specs' => $parser->specs($row), 'content' => $parser->content($row),
            'short_description' => $row['description'], 'weight' => $row['weight_kg'], 'warranty' => $row['warranty_months'].' мес.',
        ]);
    }

    private function report(array $rows): void
    {
        $stats = array_count_values(array_column($rows, 'action'));
        $stats['rows'] = count($rows);
        $stats['technical_conflicts'] = count(array_filter($rows, fn ($r) => $r['technical_conflict']));
        foreach ($stats as $key => $value) {
            $this->line("metric.omenergo.{$key}={$value}");
        }
        $path = $this->option('report') ?: storage_path('app/reports/omenergo/omenergo_'.now()->format('Ymd_His').'.csv');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }
        $file = fopen($path, 'wb');
        fputcsv($file, ['sheet_row', 'supplier_article', 'supplier_name', 'action', 'product_id', 'new_supplier_cost', 'product_retail_price', 'stock_status', 'technical_conflict', 'reason'], ',', '"', '');
        foreach ($rows as $row) {
            fputcsv($file, [$row['sheet_row'], $row['article'], $row['name'], $row['action'], $row['product_id'], $row['dealer'], $row['retail'], $row['stock_status'], (int) $row['technical_conflict'], implode(' | ', $row['suggestions'])], ',', '"', '');
        }
        fclose($file);
        $this->line('Review report saved: '.$path);
    }

    private function saveSnapshot(?Supplier $supplier, array $rows): void
    {
        Storage::disk('local')->put('supplier-sync-sources/omenergo/before_'.now()->format('Ymd_His_u').'.json', json_encode([
            'products' => Product::whereIn('id', array_filter(array_column($rows, 'product_id')))->get()->toArray(),
            'supplier_products' => $supplier?->supplierProducts()->get()->toArray() ?? [],
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }
}
