<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\CrmContactPoint;
use App\Models\CrmCounterparty;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\SupplierSource;
use App\Models\SupplierSync;
use App\Services\SupplierProductAvailabilityService;
use App\Services\Suppliers\TeplouchetPriceList;
use App\Services\Telephony\PhoneNumber;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;

class SyncTeplouchetCommand extends Command
{
    protected $signature = 'supplier:sync-teplouchet
        {file=resources/data/suppliers/teplouchet-dab-2026-10-12.json : XLS or normalized JSON snapshot}
        {--apply : Save supplier offers, links, purchase prices and availability}
        {--dry-run : Preview only}
        {--match-existing : Link only unique exact DAB identities}
        {--sync-retail-prices : Set verified RRP on exactly matched products}
        {--export-snapshot= : Normalize the XLS into a deployable JSON snapshot}
        {--report= : Write the complete classification CSV}';

    protected $description = 'ТЕПЛОУЧЁТ: DAB purchase/RRP, stock snapshot and Grundfos/Wilo analogs.';

    public function handle(TeplouchetPriceList $parser): int
    {
        $lock = Cache::lock('supplier:sync-teplouchet', 900);
        if (! $lock->get()) {
            $this->error('Синхронизация ТЕПЛОУЧЁТ уже выполняется.');

            return self::FAILURE;
        }

        try {
            $path = $this->absolutePath((string) $this->argument('file'));
            $payload = $parser->load($path);
            if ($target = $this->option('export-snapshot')) {
                $target = $this->absolutePath((string) $target);
                File::ensureDirectoryExists(dirname($target));
                File::put($target, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL);
                $this->info("Снимок записан: {$target}");

                return self::SUCCESS;
            }

            $supplier = Supplier::where('code', 'teplouchet')->first();
            $classified = $this->classify($payload['rows'], $parser, $supplier);
            $references = $this->classify($payload['references'] ?? [], $parser, $supplier);
            $this->metrics($payload, $classified, $references);
            $this->writeReport($classified);

            if (! $this->option('apply') || $this->option('dry-run')) {
                return self::SUCCESS;
            }

            $stats = DB::transaction(function () use ($classified, $references, $payload, &$supplier): array {
                [$supplier, $source, $sync] = $this->ensureSupplier($payload['supplier']);
                $stats = ['offers' => 0, 'linked' => 0, 'unlinked' => 0, 'duplicate_rows_skipped' => 0, 'retail_updated' => 0, 'retail_review' => 0, 'analog_specs_updated' => 0, 'analog_product_links' => 0, 'analog_product_ambiguous' => 0, 'analog_product_unmatched' => 0, 'reference_rows' => count($references), 'reference_matched' => 0];
                $touched = [];
                $offerArticles = collect($classified)->pluck('article_key')->flip();
                $stockObservedAt = CarbonImmutable::parse($payload['source_date'], 'Europe/Minsk')->endOfDay();
                $analogCatalog = $this->analogCatalog();

                foreach ($classified as $row) {
                    if ($row['duplicate_article']) {
                        $stats['duplicate_rows_skipped']++;

                        continue;
                    }
                    $product = $row['product_id'] ? Product::find($row['product_id']) : null;
                    $existing = SupplierProduct::where('supplier_id', $supplier->id)
                        ->where('supplier_article_normalized', $row['article_key'])
                        ->first();
                    if ($existing?->product_id && $product && (int) $existing->product_id !== (int) $product->id) {
                        throw new RuntimeException("Артикул {$row['article']} уже связан с другим товаром.");
                    }

                    SupplierProduct::updateOrCreate([
                        'supplier_id' => $supplier->id,
                        'supplier_article_normalized' => $row['article_key'],
                    ], [
                        'supplier_sync_id' => $sync->id,
                        'product_id' => $product?->id,
                        'product_sku' => $product?->sku,
                        'supplier_article' => $row['article'],
                        'supplier_article_compact' => $row['article_key'],
                        'supplier_name' => $row['name'],
                        'source_url' => $row['source_url'],
                        'price' => $row['wholesale'],
                        'price_byn' => $row['wholesale'],
                        'currency' => 'BYN',
                        'currency_rate' => 1,
                        'in_stock' => $row['in_stock'],
                        'stock_quantity' => null,
                        'stock_status' => $row['stock_status'],
                        'stock_text' => $row['stock_text'],
                        'warehouse_name' => 'ТЕПЛОУЧЁТ · '.$row['warehouse_name'],
                        'delivery_days' => $row['delivery_days'],
                        'match_status' => $product ? 'matched' : 'unmatched',
                        'match_confidence' => $product ? $row['match_reason'] : null,
                        'raw' => array_merge($row, ['source_date' => $payload['source_date']]),
                        'last_synced_at' => now(),
                        // Availability is a dated snapshot, not a live balance.
                        'last_stock_synced_at' => $stockObservedAt,
                    ]);

                    $stats['offers']++;
                    $stats[$product ? 'linked' : 'unlinked']++;
                    if (! $product) {
                        continue;
                    }

                    if ($this->option('sync-retail-prices') && $row['retail'] > $row['wholesale'] && abs((float) $product->price - $row['retail']) > 0.005) {
                        $currentRetail = (float) $product->price;
                        $change = $currentRetail > 0 ? abs($row['retail'] - $currentRetail) / $currentRetail : 0;
                        if ($change <= 0.30) {
                            $product->price = $row['retail'];
                            $stats['retail_updated']++;
                        } else {
                            $stats['retail_review']++;
                        }
                    }
                    if ($this->mergeAnalogSpecs($product, $row)) {
                        $stats['analog_specs_updated']++;
                    }
                    $this->syncAnalogProducts($product, $row, $analogCatalog, $stats);
                    $product->save();
                    $touched[] = $product->id;
                    if ($product->brand_id) {
                        $supplier->brands()->syncWithoutDetaching([$product->brand_id]);
                    }
                }

                foreach ($references as $row) {
                    if ($row['duplicate_article'] || ! $row['product_id'] || $offerArticles->has($row['article_key'])) {
                        continue;
                    }
                    $product = Product::find($row['product_id']);
                    if (! $product) {
                        continue;
                    }
                    $stats['reference_matched']++;
                    if ($this->mergeAnalogSpecs($product, $row)) {
                        $stats['analog_specs_updated']++;
                    }
                    $this->syncAnalogProducts($product, $row, $analogCatalog, $stats);
                    $product->save();
                    $touched[] = $product->id;
                }

                app(SupplierProductAvailabilityService::class)->refreshMany(array_values(array_unique($touched)));
                $source->forceFill(['last_run_at' => now()])->save();
                $sync->forceFill(['last_run_at' => now(), 'last_status' => 'updated', 'last_exit_code' => 0])->save();

                return $stats;
            });

            foreach ($stats as $key => $value) {
                $this->line("metric.teplouchet.{$key}={$value}");
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } finally {
            $lock->release();
        }
    }

    /** @param array<int,array<string,mixed>> $rows */
    private function classify(array $rows, TeplouchetPriceList $parser, ?Supplier $supplier): array
    {
        $articleCounts = collect($rows)->countBy('article_key');
        $dabBrandIds = Brand::query()->whereRaw('LOWER(name) = ?', ['dab'])->pluck('id')->map(fn ($id) => (int) $id)->all();
        $dabProducts = Product::with('brand')
            ->where(function ($query) use ($dabBrandIds): void {
                $query->whereIn('brand_id', $dabBrandIds)
                    ->orWhereRaw('LOWER(name) LIKE ?', ['%dab%']);
            })
            ->get()
            ->filter(fn (Product $product) => in_array((int) $product->brand_id, $dabBrandIds, true)
                || preg_match('/\bdab\b/ui', $product->name));
        $identityProducts = Product::query()
            ->where(function ($query) use ($rows): void {
                $query->whereIn('sku', collect($rows)->pluck('article')->filter()->unique())
                    ->orWhereIn('content_source_url', collect($rows)->pluck('source_url')->filter()->unique());
            })
            ->get();
        $exactIdentityProducts = $dabProducts->merge($identityProducts)->unique('id');
        $bySku = [];
        $byModel = [];
        $byCoreModel = [];
        $bySourceUrl = [];
        $bySpecArticle = [];
        foreach ($exactIdentityProducts as $product) {
            if ($product->sku) {
                $bySku[$parser->article($product->sku)][] = $product->id;
            }
            if ($key = $parser->sourceUrlKey($product->content_source_url)) {
                $bySourceUrl[$key][] = $product->id;
            }
        }
        foreach ($dabProducts as $product) {
            if ($key = $parser->modelKey($product->name)) {
                $byModel[$key][] = $product->id;
            }
            if ($key = $parser->coreModelKey($product->name)) {
                $byCoreModel[$key][] = $product->id;
            }
            foreach ($this->productArticleKeys($product, $parser) as $key) {
                $bySpecArticle[$key][] = $product->id;
            }
        }

        $bySupplierArticle = SupplierProduct::query()
            ->whereNotNull('product_id')
            ->whereIn('supplier_article_normalized', array_keys($articleCounts->all()))
            ->get()
            ->groupBy(fn ($link) => $parser->article($link->supplier_article))
            ->map(fn ($links) => $links->pluck('product_id')->unique()->values()->all())
            ->all();
        $existing = $supplier?->supplierProducts()->whereNotNull('product_id')->get()
            ->keyBy('supplier_article_normalized') ?? collect();

        foreach ($rows as &$row) {
            $row['duplicate_article'] = ($articleCounts[$row['article_key']] ?? 0) > 1;
            if ($row['duplicate_article']) {
                $row['product_id'] = null;
                $row['match_reason'] = 'duplicate_supplier_article';

                continue;
            }
            $matches = [];
            $reason = null;
            if ($link = $existing->get($row['article_key'])) {
                $matches = [(int) $link->product_id];
                $reason = 'existing_supplier_link';
            } elseif ($this->option('match-existing')) {
                foreach ([
                    'exact_supplier_article' => $bySupplierArticle[$row['article_key']] ?? [],
                    'exact_sku' => $bySku[$row['article_key']] ?? [],
                    'exact_spec_article' => $bySpecArticle[$row['article_key']] ?? [],
                    'exact_source_url' => $bySourceUrl[$parser->sourceUrlKey($row['source_url'])] ?? [],
                    'exact_dab_model' => $byModel[$parser->modelKey($row['model'])] ?? [],
                    'exact_dab_core_model' => $byCoreModel[$parser->coreModelKey($row['model'])] ?? [],
                ] as $candidateReason => $candidates) {
                    $candidates = array_values(array_unique(array_map('intval', $candidates)));
                    if (count($candidates) === 1) {
                        $matches = $candidates;
                        $reason = $candidateReason;
                        break;
                    }
                    if (count($candidates) > 1) {
                        $matches = $candidates;
                        $reason = 'ambiguous_'.$candidateReason;
                        break;
                    }
                }
            }
            $row['product_id'] = count($matches) === 1 ? $matches[0] : null;
            $row['match_reason'] = $reason ?: 'unmatched';
        }
        unset($row);

        return $rows;
    }

    /** @return array<int,string> */
    private function productArticleKeys(Product $product, TeplouchetPriceList $parser): array
    {
        $keys = [];
        foreach ($product->specs ?? [] as $spec) {
            if (! is_array($spec)) {
                continue;
            }
            $name = mb_strtolower(trim((string) ($spec['key'] ?? $spec['name'] ?? '')));
            if (! preg_match('/^(?:артикул(?: производителя)?|код(?: производителя| товара)?)$/u', $name)) {
                continue;
            }
            if ($key = $parser->article((string) ($spec['value'] ?? ''))) {
                $keys[] = $key;
            }
        }

        return array_values(array_unique($keys));
    }

    private function ensureSupplier(array $details): array
    {
        $company = CrmCounterparty::query()
            ->whereHas('contactPoints', fn ($query) => $query->where('type', 'email')->where('normalized_value', $details['email']))
            ->first()
            ?: CrmCounterparty::firstOrCreate(['type' => 'organization', 'legal_name' => $details['name']], [
                'name' => 'ТЕПЛОУЧЁТ', 'classification' => 'supplier', 'status' => 'active', 'source' => 'manual',
                'website' => $details['website'], 'actual_address' => $details['address'],
            ]);
        foreach ([[$details['phone'], 'phone', 'Мобильный / Viber'], [$details['email'], 'email', 'Email']] as [$value, $type, $label]) {
            CrmContactPoint::firstOrCreate([
                'counterparty_id' => $company->id,
                'type' => $type,
                'normalized_value' => $type === 'phone' ? PhoneNumber::normalize($value) : mb_strtolower($value),
            ], ['value' => $value, 'label' => $label, 'is_primary' => true, 'is_active' => true]);
        }

        $supplier = Supplier::updateOrCreate(['code' => 'teplouchet'], [
            'crm_counterparty_id' => $company->id,
            'name' => $details['name'], 'currency' => 'BYN', 'currency_rate' => 1, 'is_active' => true,
            'contact' => implode('; ', [$details['phone'], $details['email'], $details['website'], $details['address']]),
            'communication_channels' => ['phone' => $details['phone'], 'viber' => $details['phone'], 'email' => $details['email'], 'website' => $details['website']],
            'notes' => 'Крупный поставщик насосов DAB. Прайс содержит опт, РРЦ, склад и аналоги Grundfos/Wilo.',
        ]);
        $source = SupplierSource::updateOrCreate([
            'supplier_id' => $supplier->id, 'name' => 'Прайс-склад ТЕПЛОУЧЁТ от 12.10.2026',
        ], [
            'type' => 'excel', 'config' => ['source_date' => TeplouchetPriceList::SOURCE_DATE, 'website' => $details['website'], 'pricing' => 'ОПТ и РРЦ с НДС'],
            'column_mappings' => ['article' => 'B', 'name' => 'C', 'analogs' => 'D', 'purchase' => 'E', 'retail' => 'F'],
            'schedule_cron' => 'По новому файлу поставщика', 'is_active' => true,
        ]);
        $sync = SupplierSync::updateOrCreate(['key' => 'teplouchet_dab_price'], [
            'supplier_id' => $supplier->id, 'name' => 'ТЕПЛОУЧЁТ', 'code' => 'teplouchet',
            'title' => 'ТЕПЛОУЧЁТ: DAB — цены, склад и аналоги',
            'description' => 'ОПТ и РРЦ с НДС, наличие из складского файла, аналоги Grundfos/Wilo. Обновление при получении нового прайса.',
            'command' => 'supplier:sync-teplouchet', 'source_url' => $details['website'], 'is_active' => true,
        ]);

        return [$supplier, $source, $sync];
    }

    private function mergeAnalogSpecs(Product $product, array $row): bool
    {
        $values = array_filter([
            'Аналоги Grundfos' => implode('; ', $row['analogs']['grundfos'] ?? []),
            'Аналоги Wilo' => implode('; ', $row['analogs']['wilo'] ?? []),
        ]);
        if ($values === []) {
            return false;
        }
        $specs = collect($product->specs ?? [])->keyBy(fn ($spec) => is_array($spec) ? ($spec['key'] ?? '') : '');
        $changed = false;
        foreach ($values as $key => $value) {
            if (($specs->get($key)['value'] ?? null) !== $value) {
                $specs->put($key, ['key' => $key, 'value' => $value, 'unit' => null]);
                $changed = true;
            }
        }
        if ($changed) {
            $product->specs = $specs->values()->all();
        }

        return $changed;
    }

    /** @return array<string,array{full:array<string,array<int,int>>,base:array<string,array<int,int>>}> */
    private function analogCatalog(): array
    {
        $catalog = [];
        foreach (['wilo', 'grundfos'] as $brand) {
            $brandIds = Brand::query()->whereRaw('LOWER(name) = ?', [$brand])->pluck('id');
            $products = Product::query()
                ->where(function ($query) use ($brand, $brandIds): void {
                    $query->whereIn('brand_id', $brandIds)
                        ->orWhereRaw('LOWER(name) LIKE ?', ['%'.$brand.'%']);
                })
                ->get(['id', 'brand_id', 'name']);

            $catalog[$brand] = ['full' => [], 'base' => []];
            foreach ($products as $product) {
                $keys = $this->analogModelKeys($product->name, $brand);
                foreach (['full', 'base'] as $type) {
                    if ($keys[$type] !== '') {
                        $catalog[$brand][$type][$keys[$type]][] = (int) $product->id;
                    }
                }
            }
        }

        return $catalog;
    }

    /** @param array<string,mixed> $row @param array<string,mixed> $catalog @param array<string,int> $stats */
    private function syncAnalogProducts(Product $product, array $row, array $catalog, array &$stats): void
    {
        DB::table('product_analogs')
            ->where('product_id', $product->id)
            ->where('source', 'teplouchet')
            ->delete();

        $links = [];
        foreach (['wilo', 'grundfos'] as $brand) {
            foreach ($row['analogs'][$brand] ?? [] as $sourceModel) {
                $keys = $this->analogModelKeys((string) $sourceModel, $brand);
                $candidates = array_values(array_unique(array_map('intval', $catalog[$brand]['full'][$keys['full']] ?? [])));
                $method = 'exact_model';
                if (count($candidates) !== 1) {
                    $candidates = array_values(array_unique(array_map('intval', $catalog[$brand]['base'][$keys['base']] ?? [])));
                    $method = 'unique_base_model';
                }
                if (count($candidates) !== 1) {
                    $stats[count($candidates) > 1 ? 'analog_product_ambiguous' : 'analog_product_unmatched']++;

                    continue;
                }
                $links[$candidates[0]] = ['source_model' => (string) $sourceModel, 'match_method' => $method];
            }
        }

        foreach ($links as $analogProductId => $match) {
            if ((int) $analogProductId === (int) $product->id) {
                continue;
            }
            DB::table('product_analogs')->updateOrInsert([
                'product_id' => $product->id,
                'analog_product_id' => $analogProductId,
            ], [
                'source' => 'teplouchet',
                'match_method' => $match['match_method'],
                'source_model' => $match['source_model'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $stats['analog_product_links']++;
        }
    }

    /** @return array{full:string,base:string} */
    private function analogModelKeys(string $value, string $brand): array
    {
        $value = mb_strtoupper(trim($value));
        $brandPattern = $brand === 'wilo' ? 'WILO(?:-NATIVE)?' : 'GRUNDFOS';
        $value = (string) preg_replace('/^.*?\b'.$brandPattern.'\b\s*/ui', '', $value);
        $value = (string) preg_replace('/\s+PN\s*\d.*$/ui', '', $value);
        $value = (string) preg_replace('/\s*\(\s*\d{5,}\s*\)\s*$/u', '', $value);
        $value = trim($value);
        $full = (string) preg_replace('/[^\p{L}\p{N}]+/u', '', $value);
        $baseValue = (string) preg_replace('/(?:\s|[-\/])+(?:EM|DM)\s*$/ui', '', $value);
        $base = (string) preg_replace('/[^\p{L}\p{N}]+/u', '', $baseValue);

        return ['full' => $full, 'base' => $base];
    }

    private function metrics(array $payload, array $rows, array $references = []): void
    {
        $metrics = [
            'source_date' => $payload['source_date'],
            'rows' => count($rows),
            'with_stock' => collect($rows)->where('in_stock', true)->count(),
            'delivery_up_to_10_workdays' => collect($rows)->where('stock_status', 'preorder')->count(),
            'with_analogs' => collect($rows)->filter(fn ($row) => ! empty($row['analogs']['grundfos']) || ! empty($row['analogs']['wilo']))->count(),
            'matched' => collect($rows)->whereNotNull('product_id')->count(),
            'unmatched' => collect($rows)->whereNull('product_id')->count(),
            'duplicate_rows' => collect($rows)->where('duplicate_article', true)->count(),
            'safe_unique_offers' => collect($rows)->where('duplicate_article', false)->count(),
            'retail_below_purchase' => collect($rows)->filter(fn ($row) => $row['retail'] <= $row['wholesale'])->count(),
            'reference_rows' => count($references),
            'reference_matched' => collect($references)->whereNotNull('product_id')->count(),
            'reference_unmatched' => collect($references)->whereNull('product_id')->count(),
        ];
        foreach ($metrics as $key => $value) {
            $this->line("metric.teplouchet.{$key}={$value}");
        }
        collect($rows)
            ->countBy('match_reason')
            ->sortKeys()
            ->each(fn ($count, $reason) => $this->line("metric.teplouchet.match_{$reason}={$count}"));
    }

    private function writeReport(array $rows): void
    {
        if (! $path = $this->option('report')) {
            return;
        }
        $path = $this->absolutePath((string) $path);
        File::ensureDirectoryExists(dirname($path));
        $handle = fopen($path, 'wb');
        fputcsv($handle, ['article', 'model', 'wholesale', 'retail', 'margin_percent', 'stock', 'product_id', 'match_reason', 'duplicate_article', 'grundfos', 'wilo'], ',', '"', '');
        foreach ($rows as $row) {
            fputcsv($handle, [$row['article'], $row['model'], $row['wholesale'], $row['retail'], $row['margin_percent'], $row['stock_text'], $row['product_id'], $row['match_reason'], $row['duplicate_article'] ? 'yes' : 'no', implode(' | ', $row['analogs']['grundfos']), implode(' | ', $row['analogs']['wilo'])], ',', '"', '');
        }
        fclose($handle);
    }

    private function absolutePath(string $path): string
    {
        return preg_match('/^[A-Za-z]:[\\\\\/]/', $path) ? $path : base_path($path);
    }
}
