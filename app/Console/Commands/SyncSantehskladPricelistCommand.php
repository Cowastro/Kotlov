<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\SupplierSource;
use App\Models\SupplierSync;
use App\Services\SupplierProductAvailabilityService;
use App\Services\Suppliers\SantehskladCatalogContent;
use App\Services\Suppliers\SantehskladPriceList;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

class SyncSantehskladPricelistCommand extends Command
{
    protected $signature = 'supplier:sync-santehsklad-pricelist
        {file=resources/data/suppliers/santehsklad-sakovich-2026-09-17.json : XLSX or normalized JSON snapshot}
        {--apply : Save supplier offers, exact links and prices}
        {--dry-run : Preview only}
        {--create-missing-products : Create exact catalogue cards for unresolved current price-list rows}
        {--enrich-content : Add SEO copy, catalogue characteristics and verified supplier media to weak linked cards}
        {--sync-retail-prices : Set source RRP on every exact catalogue duplicate}
        {--export-snapshot= : Normalize the XLSX into a deployable JSON snapshot}
        {--report= : Write the complete classification CSV}';

    protected $description = 'Сантехсклад: reconcile SAKOVICH/PRAMEN wholesale and retail prices with exact catalogue cards.';

    public function handle(SantehskladPriceList $parser, SantehskladCatalogContent $catalogContent): int
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

            $stats = DB::transaction(function () use ($payload, $classified, &$supplier, $parser, $catalogContent): array {
                [$supplier, $source, $sync] = $this->ensureSupplier($payload, $supplier);
                $stats = [
                    'offers' => 0,
                    'linked' => 0,
                    'unlinked' => 0,
                    'retail_updated' => 0,
                    'duplicate_retail_updated' => 0,
                    'price_confirmation_cleared' => 0,
                    'products_created' => 0,
                    'products_enriched' => 0,
                    'enriched_with_source' => 0,
                    'enriched_with_image' => 0,
                ];
                $touched = [];

                foreach ($classified as $row) {
                    $product = $row['product_id'] ? Product::query()->find($row['product_id']) : null;
                    if (! $product && $this->option('create-missing-products')) {
                        $product = $this->createProductForRow($row, $supplier, $payload, $parser);
                        $row['product_id'] = $product->id;
                        $row['candidate_ids'] = [$product->id];
                        $row['match_reason'] = 'created_from_current_supplier_pricelist';
                        $stats['products_created']++;
                    }
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
                    if ($this->option('enrich-content') && $catalogContent->enrich($product, $row, $payload)) {
                        $product->refresh();
                        $stats['products_enriched']++;
                        $stats['enriched_with_source'] += filled($product->content_source_url) ? 1 : 0;
                        $stats['enriched_with_image'] += $product->imagePaths() !== [] ? 1 : 0;
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
            if (app()->runningUnitTests()) {
                throw $e;
            }
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
            $matchIdentity = $parser->rowMatchIdentity($row);
            $candidates = $byIdentity[$matchIdentity] ?? [];
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

    private function createProductForRow(array $row, Supplier $supplier, array $payload, SantehskladPriceList $parser): Product
    {
        $category = Category::query()->where('slug', $this->categorySlug($row))->first();
        if (! $category) {
            throw new RuntimeException('Не найдена категория '.$this->categorySlug($row).' для '.$row['article'].'.');
        }

        $brandName = $this->brandName($row);
        $brand = $brandName
            ? Brand::query()->firstOrCreate(
                ['slug' => Str::slug($brandName)],
                ['name' => $brandName, 'is_active' => true]
            )
            : null;
        $name = $parser->catalogName($row);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $brand?->id,
            'name' => $name,
            'h1' => $name,
            'slug' => 'new-product-'.Str::uuid(),
            'sku' => null,
            'price' => $row['retail'],
            'currency' => 'BYN',
            'short_description' => 'Позиция из актуального прайса Сантехсклада. Наличие и срок поставки подтверждает менеджер.',
            'content' => '<p>Цена привязана к прайсу поставщика «Сантехсклад» от '
                .date('d.m.Y', strtotime((string) $payload['source_date']))
                .'. Наличие и срок поставки необходимо подтвердить у менеджера перед оформлением.</p>',
            'specs' => $this->sourceSpecs($row, $payload),
            'is_active' => true,
            'is_archived' => false,
            'in_stock' => false,
            'availability_status' => Product::AVAILABILITY_CHECK,
            'stock_qty' => null,
            'requires_price_confirmation' => false,
            'meta_title' => $name.' — цена и заказ | KOTLOV.BY',
            'meta_description' => $name.'. Цена по прайсу Сантехсклада; наличие и срок поставки подтверждает менеджер.',
        ]);
        $product->forceFill([
            'sku' => $this->uniqueSku($product->id),
            'slug' => $this->uniqueSlug($name, $product->id),
        ])->save();
        if ($brand) {
            $supplier->brands()->syncWithoutDetaching([$brand->id]);
        }

        return $product;
    }

    private function categorySlug(array $row): string
    {
        $identity = (string) $row['identity'];
        $group = mb_strtolower((string) $row['group']);
        $variant = (string) (($row['variant'] ?? null) ?: $row['name']);

        return match (true) {
            str_starts_with($identity, 'pellet_') => 'kotly-na-pelletah',
            str_starts_with($identity, 'promka_'), str_starts_with($identity, 'madera_') => 'burzhuiki-pechi',
            ! str_starts_with($identity, 'accessory_') => 'tverdotoplivnye',
            str_contains($group, 'rotary') && preg_match('/\d+\s*[-–]\s*\d+\s*(?:квт|kw)/ui', $variant) => 'pelletnye-gorelki',
            default => 'komplektuyushhie-dlya-otopleniya',
        };
    }

    private function brandName(array $row): ?string
    {
        $identity = (string) $row['identity'];
        $value = mb_strtolower(implode(' ', [$row['name'], $row['group'], $row['variant']]));

        return match (true) {
            ! str_starts_with($identity, 'accessory_'), str_contains($value, 'бункер') => 'Sakovich',
            str_contains($value, 'kipi'), str_contains($value, 'rotary') => 'KIPI',
            str_contains($value, 'plum'), str_contains($value, 'ester'), str_contains($value, 'econet') => 'PLUM',
            str_contains($value, 'icma') => 'ICMA',
            default => null,
        };
    }

    private function sourceSpecs(array $row, array $payload): array
    {
        $specs = [
            ['key' => 'Поставщик', 'value' => 'Сантехсклад'],
            ['key' => 'Артикул поставщика', 'value' => $row['article']],
            ['key' => 'Дата прайса', 'value' => date('d.m.Y', strtotime((string) $payload['source_date']))],
        ];
        if ($row['power']) {
            $power = trim((string) $row['power']);
            $specs[] = [
                'key' => 'Мощность',
                'value' => preg_match('/(?:квт|kw)$/ui', $power)
                    ? str_ireplace('kW', 'кВт', $power)
                    : $power.' кВт',
            ];
        }
        if ($row['variant'] && trim((string) $row['variant']) !== trim((string) $row['name'])) {
            $specs[] = ['key' => 'Исполнение', 'value' => trim((string) $row['variant'])];
        }

        return $specs;
    }

    private function uniqueSku(int $productId): string
    {
        $number = $productId;
        do {
            $sku = sprintf('KOTLOV-%06d', $number++);
        } while (Product::query()->where('sku', $sku)->exists());

        return $sku;
    }

    private function uniqueSlug(string $name, int $productId): string
    {
        $base = Str::slug($name) ?: 'product-'.$productId;
        $slug = $base;
        $suffix = 2;
        while (Product::query()->where('slug', $slug)->whereKeyNot($productId)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    /** @return array{0:Supplier,1:SupplierSource,2:SupplierSync} */
    private function ensureSupplier(array $payload, ?Supplier $supplier): array
    {
        $supplier ??= new Supplier;
        $ordersPhone = '+375295155377';
        $channels = collect($supplier->communication_channels ?? [])
            ->filter(fn ($channel): bool => is_array($channel))
            ->reject(fn (array $channel): bool => preg_replace('/\D/', '', (string) ($channel['value'] ?? '')) === '375295155377')
            ->values()
            ->all();
        $channels[] = [
            'type' => 'phone',
            'name' => 'Приём заявок',
            'value' => $ordersPhone,
            'note' => 'Сантехсклад — заказы и наличие',
        ];

        $contact = trim((string) $supplier->contact);
        if (preg_match('/375\D*29\D*515\D*53\D*77/u', $contact) !== 1) {
            $contact = trim($contact.($contact !== '' ? ' · ' : '').'+375 (29) 515-53-77 — приём заявок');
        }

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
            'contact' => $contact,
            'communication_channels' => $channels,
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
