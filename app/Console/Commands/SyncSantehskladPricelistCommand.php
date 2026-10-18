<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\SupplierSource;
use App\Models\SupplierSync;
use App\Services\SupplierProductAvailabilityService;
use App\Services\Suppliers\SantehskladPriceList;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;

class SyncSantehskladPricelistCommand extends Command
{
    protected $signature = 'supplier:sync-santehsklad-pricelist
        {file=resources/data/suppliers/santehsklad-sakovich-2026-09-17.json : XLSX or normalized JSON snapshot}
        {--apply : Save supplier offers, exact links and prices}
        {--dry-run : Preview only}
        {--sync-retail-prices : Set source RRP on every exact catalogue duplicate}
        {--export-snapshot= : Normalize the XLSX into a deployable JSON snapshot}
        {--report= : Write the complete classification CSV}';

    protected $description = 'Сантехсклад: reconcile SAKOVICH/PRAMEN wholesale and retail prices with exact catalogue cards.';

    public function handle(SantehskladPriceList $parser): int
    {
        $lock = Cache::lock('supplier:sync-santehsklad-pricelist', 900);
        if (! $lock->get()) {
            $this->error('Синхронизация Сантехсклада уже выполняется.');

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

            $supplier = Supplier::query()
                ->where('code', 'santehsklad')
                ->orWhereRaw('LOWER(name) = ?', ['сантехсклад'])
                ->first();
            $classified = $this->classify($payload['rows'], $parser, $supplier);
            $this->metrics($payload, $classified);
            $this->writeReport($classified);

            if (! $this->option('apply') || $this->option('dry-run')) {
                return self::SUCCESS;
            }

            $stats = DB::transaction(function () use ($payload, $classified, &$supplier): array {
                [$supplier, $source, $sync] = $this->ensureSupplier($payload, $supplier);
                $stats = [
                    'offers' => 0,
                    'linked' => 0,
                    'unlinked' => 0,
                    'retail_updated' => 0,
                    'duplicate_retail_updated' => 0,
                    'price_confirmation_cleared' => 0,
                ];
                $touched = [];

                foreach ($classified as $row) {
                    $product = $row['product_id'] ? Product::query()->find($row['product_id']) : null;
                    $existing = SupplierProduct::query()
                        ->where('supplier_id', $supplier->id)
                        ->where('supplier_article_normalized', $row['article_key'])
                        ->first();
                    if ($existing?->product_id && $product && (int) $existing->product_id !== (int) $product->id) {
                        throw new RuntimeException("Позиция {$row['article']} уже связана с другой карточкой.");
                    }

                    SupplierProduct::query()->updateOrCreate([
                        'supplier_id' => $supplier->id,
                        'supplier_article_normalized' => $row['article_key'],
                    ], [
                        'supplier_sync_id' => $sync->id,
                        'product_id' => $product?->id,
                        'product_sku' => $product?->sku,
                        'supplier_article' => $row['article'],
                        'supplier_article_compact' => str_replace('-', '', $row['article_key']),
                        'supplier_name' => $row['name'],
                        'source_url' => null,
                        'price' => $row['wholesale'],
                        'price_byn' => $row['wholesale'],
                        'currency' => 'BYN',
                        'currency_rate' => 1,
                        'in_stock' => false,
                        'stock_quantity' => null,
                        'stock_status' => 'unknown',
                        'stock_text' => 'Наличие в прайсе не указано — проверить у поставщика',
                        'warehouse_name' => 'Сантехсклад',
                        'delivery_days' => null,
                        'last_stock_synced_at' => null,
                        'match_status' => $product ? 'matched' : 'unmatched',
                        'match_confidence' => $product ? $row['match_reason'] : null,
                        'raw' => array_merge($row, [
                            'source_date' => $payload['source_date'],
                            'manufacturer' => $payload['manufacturer'],
                            'availability_provided' => false,
                        ]),
                        'last_synced_at' => now(),
                    ]);

                    $stats['offers']++;
                    $stats[$product ? 'linked' : 'unlinked']++;
                    if (! $product) {
                        continue;
                    }

                    $priceTargets = $this->option('sync-retail-prices')
                        ? Product::query()->whereIn('id', $row['candidate_ids'])->get()
                        : collect([$product]);
                    foreach ($priceTargets as $index => $target) {
                        if (abs((float) $target->price - (float) $row['retail']) > 0.005) {
                            $target->price = $row['retail'];
                            $stats[$index === 0 ? 'retail_updated' : 'duplicate_retail_updated']++;
                        }
                        if ($target->requires_price_confirmation) {
                            $target->requires_price_confirmation = false;
                            $stats['price_confirmation_cleared']++;
                        }
                        $target->save();
                        $touched[] = $target->id;
                        if ($target->brand_id) {
                            $supplier->brands()->syncWithoutDetaching([$target->brand_id]);
                        }
                    }
                }

                app(SupplierProductAvailabilityService::class)->refreshMany(array_values(array_unique($touched)));
                $source->forceFill(['last_run_at' => now()])->save();
                $sync->forceFill(['last_run_at' => now(), 'last_status' => 'updated', 'last_exit_code' => 0])->save();

                return $stats;
            });

            foreach ($stats as $key => $value) {
                $this->line("metric.santehsklad.{$key}={$value}");
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } finally {
            $lock->release();
        }
    }

    /** @param array<int,array<string,mixed>> $rows @return array<int,array<string,mixed>> */
    private function classify(array $rows, SantehskladPriceList $parser, ?Supplier $supplier): array
    {
        $products = Product::query()
            ->active()
            ->notArchived()
            ->get(['id', 'sku', 'name', 'price', 'content', 'images', 'specs', 'brand_id', 'requires_price_confirmation']);
        $byIdentity = [];
        $byName = [];
        foreach ($products as $product) {
            if ($identity = $parser->productIdentity($product->name)) {
                $byIdentity[$identity][] = $product;
            }
            $byName[$parser->nameKey($product->name)][] = $product;
        }
        $existingLinks = $supplier
            ? $supplier->supplierProducts()->whereNotNull('product_id')->get()->keyBy('supplier_article_normalized')
            : collect();

        foreach ($rows as &$row) {
            $candidates = $byIdentity[$row['identity']] ?? [];
            if ($candidates === [] && str_starts_with($row['identity'], 'accessory_')) {
                $candidates = $byName[$parser->nameKey($row['name'])] ?? [];
            }
            $candidates = collect($candidates)->unique('id')->values();
            $linkedId = (int) ($existingLinks->get($row['article_key'])?->product_id ?? 0);
            $ranked = $candidates->sortByDesc(fn (Product $product): int => $this->completenessScore($product))->values();
            $product = $linkedId > 0
                ? $ranked->firstWhere('id', $linkedId)
                : $ranked->first();
            $row['product_id'] = $product?->id;
            $row['candidate_ids'] = $ranked->pluck('id')->map(fn ($id): int => (int) $id)->all();
            $row['match_reason'] = match (true) {
                ! $product => 'unmatched',
                $linkedId > 0 => 'existing_supplier_link',
                $candidates->count() > 1 => 'exact_identity_ranked_duplicate',
                str_starts_with($row['identity'], 'accessory_') => 'exact_accessory_name',
                default => 'exact_series_power_variant',
            };
            $row['current_retail'] = $product ? (float) $product->price : null;
            $row['retail_change_percent'] = $product && (float) $product->price > 0
                ? round((($row['retail'] - (float) $product->price) / (float) $product->price) * 100, 2)
                : null;
        }
        unset($row);

        return $rows;
    }

    private function completenessScore(Product $product): int
    {
        return mb_strlen(strip_tags((string) $product->content))
            + count($product->images ?? []) * 500
            + count($product->specs ?? []) * 50
            + (str_starts_with((string) $product->sku, 'KOTLOV-') ? 0 : 25);
    }

    /** @return array{0:Supplier,1:SupplierSource,2:SupplierSync} */
    private function ensureSupplier(array $payload, ?Supplier $supplier): array
    {
        $supplier ??= new Supplier;
        $priceListNote = 'Прайс SAKOVICH/PRAMEN: отдельные оптовые цены с НДС и РРЦ. Наличие в файле не указано.';
        $notes = trim((string) $supplier->notes);
        if (! str_contains($notes, $priceListNote)) {
            $notes = trim($notes.PHP_EOL.$priceListNote);
        }
        $supplier->forceFill([
            'code' => 'santehsklad',
            'name' => $supplier->exists ? $supplier->name : 'Сантехсклад',
            'currency' => 'BYN',
            'currency_rate' => 1,
            'notes' => $notes,
            'is_active' => true,
        ])->save();

        $source = SupplierSource::query()->updateOrCreate([
            'supplier_id' => $supplier->id,
            'name' => 'Прайс SAKOVICH/PRAMEN от 17.09.2026',
        ], [
            'type' => 'excel',
            'config' => [
                'source_date' => $payload['source_date'],
                'source_sha256' => $payload['source_sha256'],
                'manufacturer' => $payload['manufacturer'],
                'pricing' => 'Оптовая цена с НДС и РРЦ',
                'availability' => 'Не указано',
            ],
            'column_mappings' => ['name' => 'A', 'power' => 'B', 'retail' => 'C', 'purchase' => 'D', 'discount' => 'E'],
            'schedule_cron' => 'По новому файлу поставщика',
            'is_active' => true,
        ]);
        $sync = SupplierSync::query()->updateOrCreate(['key' => 'santehsklad_sakovich_pricelist'], [
            'supplier_id' => $supplier->id,
            'name' => 'Сантехсклад',
            'code' => 'santehsklad',
            'title' => 'Сантехсклад: SAKOVICH/PRAMEN — опт и РРЦ',
            'description' => 'Сопоставляет котлы, печи и комплектующие по серии, мощности и варианту; опт и РРЦ берёт из отдельных колонок.',
            'command' => 'supplier:sync-santehsklad-pricelist',
            'is_active' => true,
        ]);

        return [$supplier, $source, $sync];
    }

    /** @param array<int,array<string,mixed>> $rows */
    private function metrics(array $payload, array $rows): void
    {
        $metrics = [
            'source_date' => str_replace('-', '', $payload['source_date']),
            'rows' => count($rows),
            'wood_rows' => collect($rows)->where('source_sheet', 'DROVA')->count(),
            'pellet_rows' => collect($rows)->where('source_sheet', 'PELLET')->count(),
            'stove_rows' => collect($rows)->where('source_sheet', 'ПЕЧИ')->count(),
            'matched' => collect($rows)->whereNotNull('product_id')->count(),
            'unmatched' => collect($rows)->whereNull('product_id')->count(),
            'matched_catalog_cards' => collect($rows)->sum(fn ($row): int => count($row['candidate_ids'])),
            'unmatched_core_products' => collect($rows)->filter(fn ($row): bool => ! $row['product_id'] && ! str_starts_with($row['identity'], 'accessory_'))->count(),
            'unmatched_accessories' => collect($rows)->filter(fn ($row): bool => ! $row['product_id'] && str_starts_with($row['identity'], 'accessory_'))->count(),
            'duplicate_card_groups' => collect($rows)->filter(fn ($row): bool => count($row['candidate_ids']) > 1)->count(),
            'retail_changes' => collect($rows)->filter(fn ($row): bool => $row['product_id'] && abs((float) $row['current_retail'] - (float) $row['retail']) > 0.005)->count(),
            'large_retail_changes' => collect($rows)->filter(fn ($row): bool => abs((float) ($row['retail_change_percent'] ?? 0)) > 30)->count(),
            'invalid_price_pairs' => collect($rows)->filter(fn ($row): bool => $row['retail'] <= $row['wholesale'])->count(),
        ];
        foreach ($metrics as $key => $value) {
            $this->line("metric.santehsklad.{$key}={$value}");
        }
        collect($rows)->countBy('match_reason')->sortKeys()->each(
            fn ($count, $reason) => $this->line("metric.santehsklad.match_{$reason}={$count}")
        );
    }

    /** @param array<int,array<string,mixed>> $rows */
    private function writeReport(array $rows): void
    {
        if (! $path = $this->option('report')) {
            return;
        }
        $path = $this->absolutePath((string) $path);
        File::ensureDirectoryExists(dirname($path));
        $handle = fopen($path, 'wb');
        fputcsv($handle, ['article', 'name', 'wholesale', 'rrp', 'product_id', 'candidate_ids', 'match_reason', 'current_retail', 'change_percent', 'sheet', 'row'], ',', '"', '');
        foreach ($rows as $row) {
            fputcsv($handle, [$row['article'], $row['name'], $row['wholesale'], $row['retail'], $row['product_id'], implode('|', $row['candidate_ids']), $row['match_reason'], $row['current_retail'], $row['retail_change_percent'], $row['source_sheet'], $row['source_row']], ',', '"', '');
        }
        fclose($handle);
    }

    private function absolutePath(string $path): string
    {
        return preg_match('/^[A-Za-z]:[\\\\\/]/', $path) ? $path : base_path($path);
    }
}
