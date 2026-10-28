<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\Category;
use App\Models\CrmContactPoint;
use App\Models\CrmCounterparty;
use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\SupplierSource;
use App\Models\SupplierSync;
use App\Services\Integrations\IntegrationProductCreator;
use App\Services\SupplierProductAvailabilityService;
use App\Services\Suppliers\HomeTechnologiesPriceList;
use App\Services\Telephony\PhoneNumber;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class SyncHomeTechnologiesPriceListCommand extends Command
{
    protected $signature = 'supplier:sync-home-technologies
        {file=resources/data/suppliers/home-technologies-2026-09.json : XLSX или нормализованный JSON}
        {--apply : Записать поставщика, закупочные цены и безопасные розничные цены}
        {--create-missing : Создать полноценные карточки для однозначных строк, которых ещё нет на сайте}
        {--dry-run : Только предпросмотр}
        {--export-snapshot= : Сохранить нормализованный JSON}
        {--report= : CSV-отчёт сопоставления}';

    protected $description = 'Домашние технологии: закупочный прайс, РРЦ и связь со складом 1С без подмены остатков.';

    public function handle(HomeTechnologiesPriceList $parser, SupplierProductAvailabilityService $availability, IntegrationProductCreator $creator): int
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

            $stats = DB::transaction(function () use ($payload, $classified, &$supplier, $creator): array {
                [$supplier, $source, $sync, $integrationSource] = $this->ensureSupplier($supplier, $payload);
                $stats = ['offers' => 0, 'linked' => 0, 'created_cards' => 0, 'onec_rows_linked' => 0, 'unmatched' => 0, 'ambiguous' => 0, 'retail_updated' => 0, 'retail_preserved' => 0, 'cards_enriched' => 0];
                $touched = [];

                foreach ($classified as $row) {
                    $product = $row['product_id'] ? Product::query()->find($row['product_id']) : null;
                    $retail = $this->retailPlan($row, $product);
                    $status = $product ? 'matched' : ($row['match_reason'] === 'ambiguous' ? 'ambiguous' : 'unmatched');
                    $offer = SupplierProduct::query()->updateOrCreate([
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

                    $priceItem = IntegrationProduct::query()->updateOrCreate([
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

                    if (! $product
                        && $this->option('create-missing')
                        && ! $row['discontinued']
                        && $row['match_reason'] !== 'ambiguous') {
                        $category = $this->categoryForRow($row);
                        $brand = $this->brandForRow($row);
                        $description = trim((string) ($row['description'] ?? ''));
                        $product = $creator->createAndLink($priceItem->fresh(), [
                            'name' => $row['name'],
                            'h1' => $row['name'],
                            'category_id' => $category->id,
                            'brand_id' => $brand?->id,
                            'retail_price' => $retail['price'],
                            'short_description' => $description !== '' ? Str::limit($description, 240) : null,
                            'content' => $this->descriptionHtml($description),
                            'images' => array_values(array_filter((array) ($row['images'] ?? []))),
                            'specs' => $this->specsForRow($row),
                            'content_source_url' => HomeTechnologiesPriceList::WEBSITE,
                            'meta_title' => Str::limit($row['name'].' — купить в Беларуси | KOTLOV', 255, ''),
                            'meta_description' => Str::limit('Купить '.$row['name'].' в KOTLOV. Характеристики, цена и доставка по Беларуси.', 255, ''),
                        ]);
                        if ($product) {
                            $stats['created_cards']++;
                            $offer->forceFill([
                                'product_id' => $product->id,
                                'product_sku' => $product->sku,
                                'match_status' => 'matched',
                                'match_confidence' => 'created_from_supplier_price',
                            ])->save();
                            if ($inventoryId = (int) ($row['inventory_candidate_id'] ?? 0)) {
                                $linked = IntegrationProduct::query()->whereKey($inventoryId)->whereNull('product_id')->update([
                                    'product_id' => $product->id,
                                    'target_category_id' => $category->id,
                                    'match_status' => 'matched',
                                    'match_method' => 'home_technologies_exact_match',
                                    'match_confidence' => 1,
                                    'matched_at' => now(),
                                ]);
                                $stats['onec_rows_linked'] += $linked;
                            }
                        }
                    }

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
                    if ($this->enrichCard($product, $row)) {
                        $stats['cards_enriched']++;
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
        $inventory = DB::table('integration_products as ip')
            ->join('integration_sources as source', 'source.id', '=', 'ip.integration_source_id')
            ->leftJoin('suppliers as supplier', 'supplier.id', '=', 'source.supplier_id')
            ->whereNotNull('ip.name')
            ->where('ip.match_status', '!=', 'ignored')
            ->where('source.update_stock', true)
            ->where(fn ($query) => $query
                ->where('source.code', 'onec')
                ->orWhere('supplier.code', 'sanbusinessgroup'))
            ->get(['ip.id', 'ip.product_id', 'ip.name']);
        $linkedInventory = $inventory->whereNotNull('product_id');
        $inventoryByName = $linkedInventory->groupBy(fn (object $offer): string => $parser->key((string) $offer->name));
        $inventoryByModel = [];
        foreach ($linkedInventory as $offer) {
            foreach ($parser->modelKeys((string) $offer->name) as $model) {
                $inventoryByModel[$model][] = (int) $offer->product_id;
            }
        }
        $unlinkedInventory = $inventory->whereNull('product_id');
        $unlinkedByName = $unlinkedInventory->groupBy(fn (object $offer): string => $parser->key((string) $offer->name));
        $unlinkedByModel = [];
        foreach ($unlinkedInventory as $offer) {
            foreach ($parser->modelKeys((string) $offer->name) as $model) {
                $unlinkedByModel[$model][] = (int) $offer->id;
            }
        }
        $sourceModelCounts = collect($rows)
            ->flatMap(fn (array $row): array => $parser->modelKeys($row['name']))
            ->countBy();
        $existing = $supplier
            ? $supplier->supplierProducts()->whereNotNull('product_id')->get()->keyBy('supplier_article_normalized')
            : collect();

        foreach ($rows as &$row) {
            $ids = [];
            $models = null;
            $reason = 'unmatched';
            if ($link = $existing->get($row['article_key'])) {
                $ids = [(int) $link->product_id];
                $reason = 'existing_supplier_link';
            } elseif (($exact = $byName->get($row['name_key'])) && $exact->count() === 1) {
                $ids = [(int) $exact->first()->id];
                $reason = 'exact_name';
            } elseif (($exact = $inventoryByName->get($row['name_key'])) && $exact->pluck('product_id')->unique()->count() === 1) {
                $ids = [(int) $exact->first()->product_id];
                $reason = 'exact_onec_name';
            } else {
                $models = collect($parser->modelKeys($row['name']))
                    ->filter(fn (string $model): bool => (int) $sourceModelCounts->get($model, 0) === 1)
                    ->values();
                $modelIds = $models
                    ->flatMap(fn (string $model): array => $byModel[$model] ?? [])
                    ->countBy()->sortDesc();
                if ($modelIds->isNotEmpty()) {
                    $top = (int) $modelIds->max();
                    $ids = $modelIds->filter(fn (int $count): bool => $count === $top)->keys()->map('intval')->all();
                    $reason = count($ids) === 1 ? 'unique_model' : 'ambiguous';
                }
                if ($ids === []) {
                    $modelIds = $models
                        ->flatMap(fn (string $model): array => $inventoryByModel[$model] ?? [])
                        ->countBy()->sortDesc();
                    if ($modelIds->isNotEmpty()) {
                        $top = (int) $modelIds->max();
                        $ids = $modelIds->filter(fn (int $count): bool => $count === $top)->keys()->map('intval')->all();
                        $reason = count($ids) === 1 ? 'unique_onec_model' : 'ambiguous';
                    }
                }
            }
            $row['product_id'] = count($ids) === 1 ? $ids[0] : null;
            $row['match_reason'] = count($ids) > 1 ? 'ambiguous' : $reason;
            $row['inventory_candidate_id'] = null;
            if (! $row['product_id'] && $row['match_reason'] !== 'ambiguous') {
                $inventoryIds = [];
                if (($exact = $unlinkedByName->get($row['name_key'])) && $exact->count() === 1) {
                    $inventoryIds = [(int) $exact->first()->id];
                    $row['match_reason'] = 'create_from_exact_onec_name';
                } else {
                    $models ??= collect($parser->modelKeys($row['name']))
                        ->filter(fn (string $model): bool => (int) $sourceModelCounts->get($model, 0) === 1)
                        ->values();
                    $candidateCounts = $models
                        ->flatMap(fn (string $model): array => $unlinkedByModel[$model] ?? [])
                        ->countBy()->sortDesc();
                    if ($candidateCounts->isNotEmpty()) {
                        $top = (int) $candidateCounts->max();
                        $inventoryIds = $candidateCounts->filter(fn (int $count): bool => $count === $top)->keys()->map('intval')->all();
                        $row['match_reason'] = count($inventoryIds) === 1 ? 'create_from_unique_onec_model' : 'ambiguous';
                    }
                }
                $row['inventory_candidate_id'] = count($inventoryIds) === 1 ? $inventoryIds[0] : null;
            }
        }
        unset($row);

        return $rows;
    }

    private function enrichCard(Product $product, array $row): bool
    {
        $updates = [];
        $images = array_values(array_filter((array) ($row['images'] ?? [])));
        if (empty($product->images) && $images !== []) {
            $updates['images'] = $images;
        }

        $description = trim((string) ($row['description'] ?? ''));
        if ($description !== '' && blank($product->short_description)) {
            $updates['short_description'] = Str::limit($description, 240);
        }
        if ($description !== '' && blank($product->content)) {
            $paragraphs = preg_split('/\r?\n+/u', $description) ?: [$description];
            $updates['content'] = collect($paragraphs)
                ->map(fn (string $paragraph): string => '<p>'.e(trim($paragraph)).'</p>')
                ->filter(fn (string $paragraph): bool => $paragraph !== '<p></p>')
                ->implode('');
        }
        if (empty($product->specs)) {
            $updates['specs'] = $this->specsForRow($row);
        }
        if (blank($product->content_source_url)) {
            $updates['content_source_url'] = HomeTechnologiesPriceList::WEBSITE;
        }
        if ($updates === []) {
            return false;
        }

        $product->forceFill($updates)->save();

        return true;
    }

    private function categoryForRow(array $row): Category
    {
        $name = mb_strtolower((string) $row['name']);
        [$parentSlug, $parentName, $slug, $categoryName] = match ($row['section']) {
            'Электрический теплый пол' => str_contains($name, 'мат нагрев')
                ? ['teplyj-pol', 'Тёплый пол', 'nagrevatelynye-maty', 'Нагревательные маты']
                : ['teplyj-pol', 'Тёплый пол', 'nagrevatelynye-kabeli', 'Нагревательные кабели'],
            'Терморегуляторы' => ['teplyj-pol', 'Тёплый пол', 'termoregulyatory-dlya-teplogo-pola', 'Терморегуляторы для тёплого пола'],
            'Труба Stahlmann' => str_contains($name, 'труба гофр')
                ? ['truby-i-fitingi', 'Трубы и фитинги', 'gofrirovannye-truby-iz-nerzhaveyushchej-stali', 'Гофрированные трубы из нержавеющей стали']
                : (preg_match('/муфт|тройник|угольник|гайк/u', $name)
                    ? ['truby-i-fitingi', 'Трубы и фитинги', 'fitingi-dlya-gofrirovannoj-truby', 'Фитинги для гофрированной трубы']
                    : ['truby-i-fitingi', 'Трубы и фитинги', 'komplektuyushchie-dlya-gofrirovannoj-truby', 'Комплектующие для гофрированной трубы']),
            'Системы Neptun' => match (true) {
                str_contains($name, 'датчик') => ['vodosnabzhenie', 'Водоснабжение', 'datchiki-protechki-vody', 'Датчики протечки воды'],
                str_contains($name, 'кран с электроприводом') => ['vodosnabzhenie', 'Водоснабжение', 'krany-s-elektroprivodom', 'Краны с электроприводом'],
                preg_match('/модуль|источник бесперебойного/u', $name) === 1 => ['vodosnabzhenie', 'Водоснабжение', 'moduli-upravleniya-zashchitoj-ot-protechek', 'Модули управления защитой от протечек'],
                default => ['vodosnabzhenie', 'Водоснабжение', 'sistemy-zashchity-ot-protechek', 'Системы защиты от протечек'],
            },
            'Арх. обогрев' => match (true) {
                preg_match('/терморегулятор|контроллер|регулятор|метеостанция/u', $name) === 1 => ['klimat', 'Климат', 'upravlenie-naruzhnym-obogrevom', 'Управление наружным обогревом'],
                str_contains($name, 'датчик') => ['klimat', 'Климат', 'datchiki-naruzhnogo-obogreva', 'Датчики наружного обогрева'],
                str_contains($name, 'комплект') || str_contains($name, 'блок питания') => ['klimat', 'Климат', 'komplektuyushchie-naruzhnogo-obogreva', 'Комплектующие наружного обогрева'],
                default => ['klimat', 'Климат', 'kabeli-naruzhnogo-obogreva', 'Кабели наружного обогрева'],
            },
            default => ['komplektuyushchie', 'Комплектующие', 'domashnie-tehnologii', 'Домашние технологии'],
        };

        $parent = Category::query()->where('slug', $parentSlug)->first()
            ?? Category::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($parentName)])->first()
            ?? Category::query()->create([
                'parent_id' => 0, 'name' => $parentName, 'slug' => $parentSlug, 'h1' => $parentName,
                'type' => 'main', 'sort_order' => 0, 'is_active' => true,
            ]);

        return Category::query()->firstOrCreate(['slug' => $slug], [
            'parent_id' => $parent->id,
            'name' => $categoryName,
            'h1' => $categoryName,
            'type' => 'main-child',
            'sort_order' => 0,
            'is_active' => true,
            'meta_title' => $categoryName.' — купить в Беларуси | KOTLOV',
            'meta_description' => $categoryName.': каталог, характеристики, цены и доставка по Беларуси.',
        ]);
    }

    private function brandForRow(array $row): ?Brand
    {
        $name = mb_strtolower((string) $row['name']);
        $brand = match (true) {
            str_contains($name, 'onekeyelectro') => ['OneKeyElectro', 'onekeyelectro'],
            str_contains($name, 'grand meyer') => ['Grand Meyer', 'grand-meyer'],
            str_contains($name, 'теплолюкс') => ['Теплолюкс', 'teplolyuks'],
            str_contains($name, 'stahlmann') => ['Stahlmann', 'stahlmann'],
            str_contains($name, 'neptun') => ['Neptun', 'neptun'],
            default => null,
        };
        if (! $brand) {
            return null;
        }

        return Brand::query()->where('slug', $brand[1])->first()
            ?? Brand::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($brand[0])])->first()
            ?? Brand::query()->create([
                'name' => $brand[0], 'slug' => $brand[1], 'h1' => $brand[0],
                'is_active' => true, 'is_featured' => false, 'sort_order' => 0,
                'meta_title' => $brand[0].' — каталог бренда | KOTLOV',
                'meta_description' => 'Товары '.$brand[0].': характеристики, цены и доставка по Беларуси.',
            ]);
    }

    private function descriptionHtml(string $description): ?string
    {
        if ($description === '') {
            return null;
        }

        return collect(preg_split('/\r?\n+/u', $description) ?: [$description])
            ->map(fn (string $paragraph): string => '<p>'.e(trim($paragraph)).'</p>')
            ->filter(fn (string $paragraph): bool => $paragraph !== '<p></p>')
            ->implode('');
    }

    /** @return list<array{key:string,value:string}> */
    private function specsForRow(array $row): array
    {
        $text = trim($row['name'].' '.($row['description'] ?? ''));
        $specs = [
            ['key' => 'Раздел', 'value' => $row['section']],
            ['key' => 'Артикул', 'value' => $row['article']],
            ['key' => 'Единица измерения', 'value' => $row['unit']],
        ];
        $patterns = [
            'Мощность' => '/\b(\d+(?:[\.,]\d+)?)\s*(к?Вт)\b/ui',
            'Площадь обогрева' => '/\b(\d+(?:[\.,]\d+)?)\s*м[²2]\b/ui',
            'Напряжение питания' => '/\b(12|220|230)\s*В\b/ui',
            'Диаметр условного прохода' => '/\bDN\s*(\d+)\b/ui',
        ];
        foreach ($patterns as $key => $pattern) {
            if (! preg_match($pattern, $text, $match)) {
                continue;
            }
            $value = str_replace('.', ',', $match[1]);
            $value .= match ($key) {
                'Мощность' => ' '.$match[2],
                'Площадь обогрева' => ' м²',
                'Напряжение питания' => ' В',
                default => '',
            };
            $specs[] = ['key' => $key, 'value' => $value];
        }
        if (str_contains(mb_strtolower($text), 'ss304')) {
            $specs[] = ['key' => 'Материал', 'value' => 'Нержавеющая сталь AISI 304'];
        }
        if (str_contains(mb_strtolower($text), 'wi-fi')) {
            $specs[] = ['key' => 'Wi-Fi', 'value' => 'Есть'];
        }

        return $specs;
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
        $metrics = [
            'rows' => count($rows),
            'with_supplier_rrp' => collect($rows)->whereNotNull('retail')->count(),
            'without_supplier_rrp' => collect($rows)->whereNull('retail')->count(),
            'matched' => collect($rows)->whereNotNull('product_id')->count(),
            'unmatched' => collect($rows)->whereNull('product_id')->where('match_reason', '!=', 'ambiguous')->count(),
            'ambiguous' => collect($rows)->where('match_reason', 'ambiguous')->count(),
            'discontinued' => collect($rows)->where('discontinued', true)->count(),
            'ready_to_create' => collect($rows)->filter(fn (array $row): bool => ! $row['product_id'] && $row['match_reason'] !== 'ambiguous' && ! $row['discontinued'])->count(),
            'onec_ready_to_link' => collect($rows)->whereNotNull('inventory_candidate_id')->count(),
        ];
        $this->table(['Показатель', 'Значение'], [
            ['Дата прайса', $payload['source_date']],
            ['Товарных строк', $metrics['rows']],
            ['С РРЦ поставщика', $metrics['with_supplier_rrp']],
            ['Без РРЦ — стандарт/рынок', $metrics['without_supplier_rrp']],
            ['Однозначно сопоставлено', $metrics['matched']],
            ['Не сопоставлено', $metrics['unmatched']],
            ['Неоднозначно', $metrics['ambiguous']],
            ['Вывод из ассортимента', $metrics['discontinued']],
            ['Готово к созданию карточек', $metrics['ready_to_create']],
            ['Из них найдена строка склада 1С', $metrics['onec_ready_to_link']],
        ]);
        foreach ($metrics as $key => $value) {
            $this->line("metric.home_technologies.{$key}={$value}");
        }
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
