<?php

namespace App\Console\Commands;

use App\Models\CrmContactPoint;
use App\Models\CrmCounterparty;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\SupplierSource;
use App\Models\SupplierSync;
use App\Services\SupplierProductAvailabilityService;
use App\Services\Suppliers\EurotermPriceList;
use App\Services\Telephony\PhoneNumber;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;

class SyncEurotermPriceListCommand extends Command
{
    protected $signature = 'supplier:sync-euroterm
        {file=resources/data/suppliers/euroterm-2026-10-20.json : XLSX or normalized JSON snapshot}
        {--apply : Save wholesale prices, RRP and warehouse balances}
        {--dry-run : Preview only}
        {--export-snapshot= : Normalize an XLSX into a deployable JSON snapshot}';

    protected $description = 'Евротерм: обновить опт, РРЦ и остаток склада по полному прайсу.';

    public function handle(EurotermPriceList $parser): int
    {
        $lock = Cache::lock('supplier:sync-euroterm', 900);
        if (! $lock->get()) {
            $this->error('Синхронизация Евротерм уже выполняется.');

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
                ->where('code', 'euroterm')
                ->orWhereRaw('LOWER(name) LIKE ?', ['%евротерм%'])
                ->first();
            $classified = $this->classify($payload['rows'], $parser, $supplier);
            $this->metrics($payload, $classified);

            if (! $this->option('apply') || $this->option('dry-run')) {
                $this->warn('Предпросмотр: база не изменена. Для записи добавьте --apply.');

                return self::SUCCESS;
            }

            $stats = DB::transaction(function () use ($payload, $classified, &$supplier): array {
                [$supplier, $source, $sync] = $this->ensureSupplier($supplier, $payload);
                $stats = [
                    'offers' => 0,
                    'linked' => 0,
                    'unlinked' => 0,
                    'retail_updated' => 0,
                    'price_confirmation_cleared' => 0,
                    'missing_zeroed' => 0,
                    'supplier_email_contacts' => 0,
                    'supplier_phone_contacts' => 0,
                ];
                $touched = [];
                $articleKeys = collect($classified)->pluck('article_key')->all();

                foreach ($classified as $row) {
                    $product = $row['product_id'] ? Product::query()->find($row['product_id']) : null;
                    $existing = SupplierProduct::query()
                        ->where('supplier_id', $supplier->id)
                        ->where('supplier_article_normalized', $row['article_key'])
                        ->first();
                    if ($existing?->product_id && $product && (int) $existing->product_id !== (int) $product->id) {
                        throw new RuntimeException("Артикул {$row['article']} уже связан с другой карточкой Евротерм.");
                    }

                    SupplierProduct::query()->updateOrCreate([
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
                        'stock_quantity' => $row['stock_quantity'],
                        'stock_status' => $row['stock_status'],
                        'stock_text' => $row['stock_text'],
                        'warehouse_name' => $row['warehouse_name'],
                        'delivery_days' => 0,
                        'last_stock_synced_at' => now(),
                        'match_status' => $product ? 'matched' : ($row['match_reason'] === 'ambiguous' ? 'ambiguous' : 'unmatched'),
                        'match_confidence' => $product ? $row['match_reason'] : null,
                        'raw' => array_merge($row, [
                            'source_date' => $payload['source_date'],
                            'source_sha256' => $payload['source_sha256'],
                            'wholesale_column' => 'Прайс-лист в BYN',
                            'retail_column' => 'РРЦ, руб с НДС',
                        ]),
                        'last_synced_at' => now(),
                    ]);

                    $stats['offers']++;
                    $stats[$product ? 'linked' : 'unlinked']++;
                    if (! $product) {
                        continue;
                    }

                    if (abs((float) $product->price - (float) $row['retail']) > 0.005) {
                        $product->price = $row['retail'];
                        $stats['retail_updated']++;
                    }
                    if ($product->requires_price_confirmation) {
                        $product->requires_price_confirmation = false;
                        $stats['price_confirmation_cleared']++;
                    }
                    $product->save();
                    $touched[] = (int) $product->id;
                    if ($product->brand_id) {
                        $supplier->brands()->syncWithoutDetaching([$product->brand_id]);
                    }
                }

                $missing = SupplierProduct::query()
                    ->where('supplier_id', $supplier->id)
                    ->whereNotIn('supplier_article_normalized', $articleKeys)
                    ->get();
                foreach ($missing as $offer) {
                    $offer->forceFill([
                        'in_stock' => false,
                        'stock_quantity' => 0,
                        'stock_status' => 'missing_from_feed',
                        'stock_text' => 'Нет в полном прайсе Евротерм от '.$payload['source_date'],
                        'last_stock_synced_at' => now(),
                        'last_synced_at' => now(),
                    ])->save();
                    if ($offer->product_id) {
                        $touched[] = (int) $offer->product_id;
                    }
                    $stats['missing_zeroed']++;
                }

                app(SupplierProductAvailabilityService::class)->refreshMany(array_values(array_unique($touched)));
                $source->forceFill(['last_run_at' => now()])->save();
                $sync->forceFill([
                    'last_run_at' => now(),
                    'last_status' => 'updated',
                    'last_exit_code' => 0,
                ])->save();
                $supplier->load('crmCounterparty.contactPoints');
                $stats['supplier_email_contacts'] = $supplier->crmCounterparty?->contactPoints
                    ->where('is_active', true)->where('type', 'email')->count() ?? 0;
                $stats['supplier_phone_contacts'] = $supplier->crmCounterparty?->contactPoints
                    ->where('is_active', true)->where('type', 'phone')->count() ?? 0;

                return $stats;
            });

            foreach ($stats as $key => $value) {
                $this->line("metric.euroterm.{$key}={$value}");
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
    private function classify(array $rows, EurotermPriceList $parser, ?Supplier $supplier): array
    {
        $products = Product::query()
            ->notArchived()
            ->get(['id', 'sku', 'name', 'specs', 'price', 'brand_id', 'requires_price_confirmation']);
        $bySku = [];
        $byName = [];
        $bySpecArticle = [];
        foreach ($products as $product) {
            if ($key = $parser->articleKey($product->sku)) {
                $bySku[$key][] = (int) $product->id;
            }
            if ($key = $parser->nameKey($product->name)) {
                $byName[$key][] = (int) $product->id;
            }
            foreach ($product->specs ?? [] as $spec) {
                if (! is_array($spec)) {
                    continue;
                }
                $label = mb_strtolower(trim((string) ($spec['key'] ?? $spec['name'] ?? '')));
                if (preg_match('/(?:артикул|код(?: товара| производителя)?)/u', $label)
                    && ($key = $parser->articleKey((string) ($spec['value'] ?? '')))) {
                    $bySpecArticle[$key][] = (int) $product->id;
                }
            }
        }
        $targetKeys = collect($rows)->pluck('article_key')->flip();
        $globalLinks = SupplierProduct::query()
            ->whereNotNull('product_id')
            ->get(['product_id', 'supplier_article', 'supplier_article_normalized', 'supplier_article_compact'])
            ->flatMap(function (SupplierProduct $offer) use ($parser, $targetKeys): array {
                $keys = collect([
                    $offer->supplier_article,
                    $offer->supplier_article_normalized,
                    $offer->supplier_article_compact,
                ])->map(fn ($value): string => $parser->articleKey((string) $value))
                    ->filter(fn (string $key): bool => $key !== '' && $targetKeys->has($key))
                    ->unique();

                return $keys->map(fn (string $key): array => [
                    'key' => $key,
                    'product_id' => (int) $offer->product_id,
                ])->all();
            })
            ->groupBy('key')
            ->map(fn ($items) => $items->pluck('product_id')->unique()->values()->all());
        $existing = $supplier
            ? $supplier->supplierProducts()->whereNotNull('product_id')->get()->keyBy('supplier_article_normalized')
            : collect();

        foreach ($rows as &$row) {
            $candidates = [];
            $reason = 'unmatched';
            if ($linked = $existing->get($row['article_key'])) {
                $candidates = [(int) $linked->product_id];
                $reason = 'existing_supplier_link';
            } else {
                foreach ([
                    'exact_supplier_article' => $globalLinks->get($row['article_key'], []),
                    'exact_sku' => $bySku[$row['article_key']] ?? [],
                    'exact_spec_article' => $bySpecArticle[$row['article_key']] ?? [],
                    'exact_name' => $byName[$row['name_key']] ?? [],
                ] as $candidateReason => $ids) {
                    $ids = array_values(array_unique(array_map('intval', $ids)));
                    if ($ids !== []) {
                        $candidates = $ids;
                        $reason = count($ids) === 1 ? $candidateReason : 'ambiguous';
                        break;
                    }
                }
                if ($candidates === [] && mb_strlen($row['article_key']) >= 4) {
                    $ids = $products->filter(function (Product $product) use ($row, $parser): bool {
                        $nameKey = $parser->nameKey($product->name);

                        return $nameKey !== '' && str_contains($nameKey, $row['article_key']);
                    })->pluck('id')->map(fn ($id): int => (int) $id)->unique()->values()->all();
                    if (count($ids) === 1) {
                        $candidates = $ids;
                        $reason = 'unique_model_in_name';
                    } elseif (count($ids) > 1) {
                        $reason = 'ambiguous';
                    }
                }
            }
            $row['product_id'] = count($candidates) === 1 ? $candidates[0] : null;
            $row['match_reason'] = $reason;
        }
        unset($row);

        return $rows;
    }

    /** @return array{0:Supplier,1:SupplierSource,2:SupplierSync} */
    private function ensureSupplier(?Supplier $supplier, array $payload): array
    {
        $email = 'tce@euroterm.by';
        $phone = '+375 (17) 241-11-42';
        $supplier ??= new Supplier;
        $company = $supplier->crmCounterparty
            ?: CrmCounterparty::query()
                ->whereHas('contactPoints', fn ($query) => $query
                    ->where('type', 'email')
                    ->where('normalized_value', $email))
                ->where('type', 'organization')
                ->first()
            ?: CrmCounterparty::query()->firstOrCreate([
                'type' => 'organization',
                'legal_name' => 'ООО «Евротерм»',
            ], [
                'name' => 'Евротерм',
                'classification' => 'supplier',
                'business_unit' => 'wholesale',
                'status' => 'active',
                'source' => 'manual',
                'website' => EurotermPriceList::WEBSITE,
            ]);
        $company->forceFill([
            'classification' => 'supplier',
            'status' => 'active',
            'website' => $company->website ?: EurotermPriceList::WEBSITE,
        ])->save();

        foreach ([
            ['type' => 'email', 'value' => $email, 'normalized' => $email, 'label' => 'Отдел продаж'],
            ['type' => 'phone', 'value' => $phone, 'normalized' => PhoneNumber::normalize($phone), 'label' => 'Офис'],
        ] as $contact) {
            CrmContactPoint::query()->updateOrCreate([
                'counterparty_id' => $company->id,
                'type' => $contact['type'],
                'normalized_value' => $contact['normalized'],
            ], [
                'value' => $contact['value'],
                'label' => $contact['label'],
                'is_primary' => true,
                'is_active' => true,
            ]);
        }

        $supplier->forceFill([
            'crm_counterparty_id' => $company->id,
            'code' => 'euroterm',
            'name' => 'ООО «Евротерм»',
            'currency' => 'BYN',
            'currency_rate' => 1,
            'contact' => EurotermPriceList::WEBSITE.' · '.$phone.' · '.$email,
            'communication_channels' => [
                'website' => EurotermPriceList::WEBSITE,
                'phone' => $phone,
                'email' => $email,
            ],
            'notes' => 'Оптовая цена, РРЦ с НДС и фактический остаток обновляются по полному прайсу Евротерм.',
            'is_active' => true,
        ])->save();

        $source = SupplierSource::query()->updateOrCreate([
            'supplier_id' => $supplier->id,
            'name' => 'Прайс и остатки Евротерм',
        ], [
            'type' => 'excel',
            'config' => [
                'url' => EurotermPriceList::WEBSITE,
                'price_page' => EurotermPriceList::WEBSITE.'prajs-list.html',
                'source_date' => $payload['source_date'],
                'source_sha256' => $payload['source_sha256'],
                'warehouse' => $payload['warehouse'],
                'columns' => ['wholesale' => 'N', 'retail' => 'O', 'stock' => 'P'],
            ],
            'schedule_cron' => 'После получения нового прайса',
            'is_active' => true,
        ]);
        $sync = SupplierSync::query()->updateOrCreate(['key' => 'euroterm_price'], [
            'supplier_id' => $supplier->id,
            'name' => 'Евротерм',
            'code' => 'euroterm',
            'title' => 'Евротерм: опт, РРЦ и остатки',
            'description' => 'Обновляет закупочные цены, розницу и склад Евротерм из полного Excel-прайса.',
            'command' => 'supplier:sync-euroterm',
            'source_url' => EurotermPriceList::WEBSITE,
            'is_active' => true,
        ]);

        return [$supplier, $source, $sync];
    }

    private function metrics(array $payload, array $classified): void
    {
        $this->table(['Показатель', 'Значение'], [
            ['Дата прайса', $payload['source_date']],
            ['Товарных строк', count($classified)],
            ['Однозначно сопоставлено', collect($classified)->whereNotNull('product_id')->count()],
            ['Не сопоставлено', collect($classified)->whereNull('product_id')->where('match_reason', '!=', 'ambiguous')->count()],
            ['Неоднозначно', collect($classified)->where('match_reason', 'ambiguous')->count()],
            ['Остаток, шт.', collect($classified)->sum('stock_quantity')],
            ['Котлы', collect($classified)->filter(fn ($row) => str_contains(mb_strtolower($row['category']), 'котлы'))->count()],
            ['Термостаты и автоматика', collect($classified)->filter(fn ($row) => preg_match('/термо|автомат|smart home/ui', implode(' ', $row['category_path'])))->count()],
            ['Сигнализаторы/детекторы', collect($classified)->filter(fn ($row) => preg_match('/детектор|сигнализатор/ui', implode(' ', $row['category_path']).' '.$row['name']))->count()],
        ]);
    }

    private function absolutePath(string $path): string
    {
        return preg_match('~^[A-Za-z]:[\\\\/]~', $path) ? $path : base_path($path);
    }
}
