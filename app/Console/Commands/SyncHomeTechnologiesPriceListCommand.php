<?php

namespace App\Console\Commands;

use App\Models\CrmContactPoint;
use App\Models\CrmCounterparty;
use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\SupplierSource;
use App\Models\SupplierSync;
use App\Services\SupplierProductAvailabilityService;
use App\Services\Suppliers\HomeTechnologiesPriceList;
use App\Services\Telephony\PhoneNumber;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class SyncHomeTechnologiesPriceListCommand extends Command
{
    protected $signature = 'supplier:sync-home-technologies
        {file=resources/data/suppliers/home-technologies-2026-09.json : XLSX или нормализованный JSON}
        {--apply : Записать поставщика, закупочные цены и безопасные розничные цены}
        {--dry-run : Только предпросмотр}
        {--export-snapshot= : Сохранить нормализованный JSON}
        {--report= : CSV-отчёт сопоставления}';

    protected $description = 'Домашние технологии: закупочный прайс, РРЦ и связь со складом 1С без подмены остатков.';

    public function handle(HomeTechnologiesPriceList $parser, SupplierProductAvailabilityService $availability): int
    {
        $lock = Cache::lock('supplier:sync-home-technologies', 900);
        if (! $lock->get()) {
            $this->error('Синхронизация Домашних технологий уже выполняется.');

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

            $supplier = Supplier::query()->where('code', 'home-technologies')->first();
            $classified = $this->classify($payload['rows'], $parser, $supplier);
            $this->writeReport($classified);
            $this->metrics($payload, $classified);

            if (! $this->option('apply') || $this->option('dry-run')) {
                $this->warn('Предпросмотр: база не изменена. Для записи добавьте --apply.');

                return self::SUCCESS;
            }

            $stats = DB::transaction(function () use ($payload, $classified, &$supplier): array {
                [$supplier, $source, $sync, $integrationSource] = $this->ensureSupplier($supplier, $payload);
                $stats = ['offers' => 0, 'linked' => 0, 'unmatched' => 0, 'ambiguous' => 0, 'retail_updated' => 0, 'retail_preserved' => 0];
                $touched = [];

                foreach ($classified as $row) {
                    $product = $row['product_id'] ? Product::query()->find($row['product_id']) : null;
                    $retail = $this->retailPlan($row, $product);
                    $status = $product ? 'matched' : ($row['match_reason'] === 'ambiguous' ? 'ambiguous' : 'unmatched');
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
                        'source_url' => HomeTechnologiesPriceList::WEBSITE,
                        'price' => $row['wholesale'],
                        'price_byn' => $row['wholesale'],
                        'currency' => 'BYN',
                        'currency_rate' => 1,
                        // В прайсе нет склада. Наличие берётся исключительно из 1С KOTLOV.
                        'in_stock' => false,
                        'stock_quantity' => null,
                        'stock_status' => $row['discontinued'] ? 'discontinued' : 'procurement_price_only',
                        'stock_text' => $row['discontinued'] ? 'Вывод из ассортимента' : 'Закупочный прайс; остаток — наш склад 1С',
                        'warehouse_name' => 'Закупка · ООО «Домашние технологии»',
                        'match_status' => $status,
                        'match_confidence' => $product ? $row['match_reason'] : null,
                        'b2b_excluded' => true,
                        'b2b_exclusion_reason' => 'Закупочный источник: B2B-доступность и остаток публикует склад KOTLOV из 1С',
                        'raw' => array_merge($row, [
                            'source_date' => $payload['source_date'],
                            'source_sha256' => $payload['source_sha256'],
                            'retail_plan' => $retail,
                            'stock_source' => 'onec',
                        ]),
                        'last_synced_at' => now(),
                    ]);

                    IntegrationProduct::query()->updateOrCreate([
                        'integration_source_id' => $integrationSource->id,
                        'external_id' => $row['article_key'],
                    ], [
                        'product_id' => $product?->id,
                        'target_category_id' => $product?->category_id,
                        'external_code' => $row['article'],
                        'external_sku' => $row['article'],
                        'name' => $row['name'],
                        'price' => $row['wholesale'],
                        'price_currency' => 'BYN',
                        'price_currency_rate' => 1,
                        'price_tax_mode' => IntegrationSource::PRICE_TAX_INCLUSIVE,
                        'price_vat_rate' => 20,
                        'price_byn' => $row['wholesale'],
                        'stock_quantity' => null,
                        'match_status' => $status,
                        'b2b_enabled' => false,
                        'match_method' => $row['match_reason'],
                        'match_confidence' => $product ? 1 : null,
                        'payload' => array_merge($row, ['retail_plan' => $retail, 'stock_source' => 'onec']),
                        'matched_at' => $product ? now() : null,
                        'last_seen_at' => now(),
                        'last_offer_seen_at' => now(),
                        'stock_confirmed_at' => null,
                    ]);

                    $stats['offers']++;
                    $stats[$product ? 'linked' : $status]++;
                    if (! $product) {
                        continue;
                    }
                    if ($retail['update'] && abs((float) $product->price - $retail['price']) > 0.005) {
                        $product->forceFill(['price' => $retail['price'], 'requires_price_confirmation' => false])->save();
                        $stats['retail_updated']++;
                    } else {
                        $stats['retail_preserved']++;
                    }
                    if ($product->brand_id) {
                        $supplier->brands()->syncWithoutDetaching([$product->brand_id]);
                    }
                    $touched[] = (int) $product->id;
                }

                app(SupplierProductAvailabilityService::class)->refreshMany(array_values(array_unique($touched)));
                $source->forceFill(['last_run_at' => now()])->save();
                $sync->forceFill(['last_run_at' => now(), 'last_status' => 'updated', 'last_exit_code' => 0])->save();

                return $stats;
            });

            foreach ($stats as $key => $value) {
                $this->line("metric.home_technologies.{$key}={$value}");
            }

            return self::SUCCESS;
        } finally {
            $lock->release();
        }
    }

    /** @param array<int,array<string,mixed>> $rows @return array<int,array<string,mixed>> */
    private function classify(array $rows, HomeTechnologiesPriceList $parser, ?Supplier $supplier): array
    {
        $products = Product::query()->notArchived()->get(['id', 'sku', 'name', 'specs', 'price', 'brand_id', 'category_id', 'requires_price_confirmation']);
        $byName = $products->groupBy(fn (Product $product): string => $parser->key($product->name));
        $byModel = [];
        foreach ($products as $product) {
            foreach ($parser->modelKeys($product->name) as $model) {
                $byModel[$model][] = (int) $product->id;
            }
        }
        $existing = $supplier
            ? $supplier->supplierProducts()->whereNotNull('product_id')->get()->keyBy('supplier_article_normalized')
            : collect();

        foreach ($rows as &$row) {
            $ids = [];
            $reason = 'unmatched';
            if ($link = $existing->get($row['article_key'])) {
                $ids = [(int) $link->product_id];
                $reason = 'existing_supplier_link';
            } elseif (($exact = $byName->get($row['name_key'])) && $exact->count() === 1) {
                $ids = [(int) $exact->first()->id];
                $reason = 'exact_name';
            } else {
                $modelIds = collect($parser->modelKeys($row['name']))
                    ->flatMap(fn (string $model): array => $byModel[$model] ?? [])
                    ->countBy()->sortDesc();
                if ($modelIds->isNotEmpty()) {
                    $top = (int) $modelIds->max();
                    $ids = $modelIds->filter(fn (int $count): bool => $count === $top)->keys()->map('intval')->all();
                    $reason = count($ids) === 1 ? 'unique_model' : 'ambiguous';
                }
            }
            $row['product_id'] = count($ids) === 1 ? $ids[0] : null;
            $row['match_reason'] = count($ids) > 1 ? 'ambiguous' : $reason;
        }
        unset($row);

        return $rows;
    }

    /** @return array{price:float,method:string,update:bool} */
    private function retailPlan(array $row, ?Product $product): array
    {
        $public = [
            'smm9050' => 68.0,
            'mst92a' => 342.0,
            'wsm13' => 378.0,
            'fsm12' => 398.0,
            'mst91aiwifi' => 700.0,
            'phc16' => 10.0,
            'phc30' => 10.0,
            'uhc25' => 10.0,
            'uhc40' => 10.0,
            'ltc16' => 7.0,
        ];
        $key = $row['name_key'];
        $market = collect($public)->first(fn (float $price, string $model): bool => str_contains($key, $model));
        $price = $row['retail'] ?? $market ?? $this->roundRetail($row['wholesale'] / 0.80);
        $method = $row['retail'] !== null ? 'supplier_rrp' : ($market !== null ? 'verified_market_price' : 'standard_target_margin_20');
        $existing = (float) ($product?->price ?? 0);
        $update = $row['retail'] !== null || $existing <= 0 || (bool) ($product?->requires_price_confirmation ?? false);

        // Непроверенная расчётная цена никогда не понижает действующую розницу.
        if ($method !== 'supplier_rrp' && $existing > $price) {
            $price = $existing;
            $method = 'existing_retail_preserved';
            $update = false;
        }

        return ['price' => round(max((float) $row['wholesale'], $price), 2), 'method' => $method, 'update' => $update];
    }

    /** @return array{0:Supplier,1:SupplierSource,2:SupplierSync,3:IntegrationSource} */
    private function ensureSupplier(?Supplier $supplier, array $payload): array
    {
        $data = $payload['supplier'];
        $company = $supplier?->crmCounterparty
            ?: CrmCounterparty::query()->where('unp', $data['unp'])->first()
            ?: new CrmCounterparty;
        $company->forceFill([
            'type' => 'organization', 'classification' => 'supplier', 'business_unit' => 'wholesale',
            'status' => 'active', 'name' => 'Домашние технологии', 'legal_name' => $data['name'],
            'unp' => $data['unp'], 'source' => 'manual', 'website' => $data['website'],
            'legal_address' => $data['address'], 'actual_address' => $data['address'],
            'authorized_representative' => 'Захаро Константин Николаевич',
            'representative_position' => 'Директор', 'authority_basis' => 'Устав',
            'bank_name' => 'ЗАО «Альфа-Банк»', 'bank_bic' => 'ALFABY2X',
            'bank_iban' => 'BY26ALFA30122E35190010270000',
        ])->save();

        $contacts = [['type' => 'email', 'value' => $data['email'], 'label' => 'Общий']];
        foreach ($data['phones'] as $phone) {
            $contacts[] = ['type' => 'phone', 'value' => $phone['value'], 'label' => $phone['label']];
        }
        foreach ($contacts as $index => $contact) {
            CrmContactPoint::query()->updateOrCreate([
                'counterparty_id' => $company->id,
                'type' => $contact['type'],
                'normalized_value' => $contact['type'] === 'phone' ? PhoneNumber::normalize($contact['value']) : mb_strtolower($contact['value']),
            ], ['value' => $contact['value'], 'label' => $contact['label'], 'is_primary' => $index === 0, 'is_active' => true]);
        }

        $supplier ??= new Supplier;
        $supplier->forceFill([
            'crm_counterparty_id' => $company->id, 'code' => 'home-technologies', 'name' => $data['name'],
            'currency' => 'BYN', 'currency_rate' => 1,
            'contact' => $data['website'].' · '.$data['email'].' · +375 (44) 750-90-81',
            'communication_channels' => [
                ['type' => 'website', 'value' => $data['website']],
                ['type' => 'email', 'value' => $data['email']],
                ...array_map(fn (array $phone): array => ['type' => 'phone', 'value' => $phone['value'], 'label' => $phone['label']], $data['phones']),
            ],
            'notes' => 'Закупочный прайс. Остатки поставщика не импортируются: наличие берётся с собственного склада KOTLOV из 1С.',
            'is_active' => true, 'b2b_partner_enabled' => false,
        ])->save();

        $source = SupplierSource::query()->updateOrCreate(['supplier_id' => $supplier->id, 'name' => 'Оптовый прайс Домашние технологии 09.2026'], [
            'type' => 'excel',
            'config' => [
                'url' => $data['website'],
                'original_filename' => '!Прайс_Опт_Домашние_технологии_09_2026.xlsx',
                'snapshot' => 'resources/data/suppliers/home-technologies-2026-09.json',
                'source_date' => $payload['source_date'],
                'source_sha256' => $payload['source_sha256'],
                'stock_source' => 'onec',
                'price_tax_mode' => 'inclusive',
            ],
            'column_mappings' => ['name' => 'C', 'unit' => 'D', 'retail_or_wholesale_ex_vat' => 'E', 'wholesale_with_vat' => 'F'],
            'schedule_cron' => 'После получения нового прайса', 'is_active' => true,
        ]);
        $sync = SupplierSync::query()->updateOrCreate(['key' => 'home_technologies_price'], [
            'supplier_id' => $supplier->id, 'name' => 'Домашние технологии', 'code' => 'home-technologies',
            'title' => 'Домашние технологии: закупочный прайс',
            'description' => 'Опт и РРЦ из Excel; наличие только со склада KOTLOV из 1С.',
            'command' => 'supplier:sync-home-technologies', 'source_url' => $data['website'], 'is_active' => true,
        ]);
        $integration = IntegrationSource::query()->updateOrCreate(['code' => 'home_technologies_pricelist'], [
            'supplier_id' => $supplier->id, 'name' => 'Домашние технологии · закупочный прайс',
            'driver' => 'static_pricelist', 'price_currency' => 'BYN', 'price_currency_rate' => 1,
            'is_active' => true, 'create_products' => false, 'update_prices' => true, 'update_stock' => false,
            'settings' => ['price_tax_mode' => 'inclusive', 'vat_rate' => 20, 'procurement_mode' => true,
                'b2b_enabled' => false, 'b2b_price_role' => IntegrationSource::B2B_PRICE_ROLE_PURCHASE,
                'partner_name' => $data['name'], 'warehouse_label' => 'Закупочный прайс; склад — 1С KOTLOV'],
        ]);

        return [$supplier, $source, $sync, $integration];
    }

    private function metrics(array $payload, array $rows): void
    {
        $this->table(['Показатель', 'Значение'], [
            ['Дата прайса', $payload['source_date']],
            ['Товарных строк', count($rows)],
            ['С РРЦ поставщика', collect($rows)->whereNotNull('retail')->count()],
            ['Без РРЦ — стандарт/рынок', collect($rows)->whereNull('retail')->count()],
            ['Однозначно сопоставлено', collect($rows)->whereNotNull('product_id')->count()],
            ['Не сопоставлено', collect($rows)->whereNull('product_id')->where('match_reason', '!=', 'ambiguous')->count()],
            ['Неоднозначно', collect($rows)->where('match_reason', 'ambiguous')->count()],
            ['Вывод из ассортимента', collect($rows)->where('discontinued', true)->count()],
        ]);
    }

    private function writeReport(array $rows): void
    {
        $path = trim((string) $this->option('report')) ?: storage_path('app/reports/suppliers/home-technologies/current-audit.csv');
        File::ensureDirectoryExists(dirname($path));
        $handle = fopen($path, 'wb');
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, ['status', 'section', 'article', 'name', 'wholesale_byn', 'source_rrp_byn', 'product_id', 'match_reason', 'planned_retail_byn', 'retail_method'], ';');
        foreach ($rows as $row) {
            $product = $row['product_id'] ? Product::query()->find($row['product_id']) : null;
            $retail = $this->retailPlan($row, $product);
            fputcsv($handle, [$product ? 'matched' : $row['match_reason'], $row['section'], $row['article'], $row['name'], $row['wholesale'], $row['retail'], $row['product_id'], $row['match_reason'], $retail['price'], $retail['method']], ';');
        }
        fclose($handle);
        $this->line('metric.home_technologies.report='.$path);
    }

    private function roundRetail(float $price): float
    {
        return round($price * 10) / 10;
    }

    private function absolutePath(string $path): string
    {
        return preg_match('~^(?:[A-Za-z]:[\\\\/]|/)~', $path) ? $path : base_path($path);
    }
}
