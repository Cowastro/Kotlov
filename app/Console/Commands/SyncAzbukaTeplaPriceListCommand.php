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
use App\Services\Suppliers\AzbukaTeplaPriceList;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SyncAzbukaTeplaPriceListCommand extends Command
{
    protected $signature = 'supplier:sync-azbuka-tepla
        {file=resources/data/suppliers/azbuka-tepla-2026-10-21.json : Нормализованный снимок розницы и Опт VIP}
        {--apply : Записать изменения}
        {--dry-run : Только показать результат}
        {--only-protherm-skat : Обработать только линейку Protherm Скат}
        {--only-viessmann : Обработать только позиции Viessmann}
        {--skip-create : Не создавать отсутствующие карточки}';

    protected $description = 'Азбука Тепла: связать РРЦ и Опт VIP, оставив остаток на уточнение.';

    public function handle(AzbukaTeplaPriceList $parser): int
    {
        $lock = Cache::lock('supplier:sync-azbuka-tepla', 900);
        if (! $lock->get()) {
            $this->error('Синхронизация Азбуки Тепла уже выполняется.');

            return self::FAILURE;
        }

        try {
            $path = $this->absolutePath((string) $this->argument('file'));
            $payload = $parser->load($path);
            $rows = $payload['rows'];
            if ($this->option('only-protherm-skat') && $this->option('only-viessmann')) {
                $this->error('Выберите только один узкий режим синхронизации.');

                return self::FAILURE;
            }
            if ($this->option('only-protherm-skat')) {
                $rows = collect($rows)->filter(fn (array $row): bool => $this->isProthermSkatRow($row, $parser))
                    ->values()->all();
            }
            if ($this->option('only-viessmann')) {
                $rows = collect($rows)->filter(fn (array $row): bool => $this->isViessmannRow($row, $parser))
                    ->values()->all();
            }
            $supplier = Supplier::query()->where('code', 'azbuka-tepla')
                ->orWhereRaw('LOWER(name) LIKE ?', ['%азбука тепла%'])
                ->first();
            $classified = $this->classify($rows, $parser, $supplier);
            $this->metrics($payload, $classified);

            if (! $this->option('apply') || $this->option('dry-run')) {
                $this->warn('Предпросмотр: база не изменена. Для записи добавьте --apply.');

                return self::SUCCESS;
            }

            $stats = DB::transaction(function () use ($payload, $classified, &$supplier): array {
                [$supplier, $sync] = $this->ensureSupplier($supplier, $payload);
                $stats = [
                    'offers' => 0,
                    'linked_existing' => 0,
                    'created' => 0,
                    'ambiguous' => 0,
                    'category_missing' => 0,
                    'retail_updated' => 0,
                    'descriptions_filled' => 0,
                    'specs_filled' => 0,
                    'unblocked' => 0,
                    'duplicates_archived' => 0,
                    'stock_to_confirm' => 0,
                ];
                $touched = [];

                foreach ($classified as $row) {
                    $product = $row['product_id'] ? Product::query()->find($row['product_id']) : null;
                    if (! $product && $row['match_reason'] === 'ambiguous') {
                        $stats['ambiguous']++;
                        continue;
                    }
                    if (! $product && ! $this->option('skip-create')) {
                        $product = $this->createProduct($row, $supplier);
                        if (! $product) {
                            $stats['category_missing']++;
                            continue;
                        }
                        $stats['created']++;
                    }
                    if (! $product) {
                        continue;
                    }

                    $previousLink = SupplierProduct::query()
                        ->where('supplier_id', $supplier->id)
                        ->where('supplier_article_normalized', $row['article_key'])
                        ->first();
                    if ($previousLink?->product_id && (int) $previousLink->product_id !== (int) $product->id) {
                        $duplicate = Product::query()->find($previousLink->product_id);
                        if ($duplicate
                            && (int) $duplicate->supplier_id === (int) $supplier->id
                            && Str::startsWith((string) $duplicate->sku, 'KOTLOV-')
                            && $duplicate->name === $this->productName($row)) {
                            $duplicate->forceFill([
                                'is_active' => false,
                                'is_archived' => true,
                                'requires_price_confirmation' => true,
                            ])->save();
                            $stats['duplicates_archived']++;
                        }
                    }

                    $wasBlocked = ! $product->is_active || $product->is_archived || $product->requires_price_confirmation;
                    if (abs((float) $product->price - (float) $row['retail_byn']) > 0.005) {
                        $product->price = $row['retail_byn'];
                        $stats['retail_updated']++;
                    }
                    if (blank($product->short_description) && filled($row['partner_description'])) {
                        $product->short_description = $row['partner_description'];
                        $stats['descriptions_filled']++;
                    }
                    if (blank($product->specs)) {
                        $product->specs = [
                            ['key' => 'Бренд', 'value' => $row['brand']],
                            ['key' => 'Модель', 'value' => $row['model']],
                        ];
                        $stats['specs_filled']++;
                    }
                    $product->forceFill([
                        'is_active' => true,
                        'is_archived' => false,
                        'requires_price_confirmation' => false,
                        'manual_stock_management' => false,
                    ])->save();
                    if ($wasBlocked) {
                        $stats['unblocked']++;
                    }

                    SupplierProduct::query()->updateOrCreate([
                        'supplier_id' => $supplier->id,
                        'supplier_article_normalized' => $row['article_key'],
                    ], [
                        'supplier_sync_id' => $sync->id,
                        'product_id' => $product->id,
                        'product_sku' => $product->sku,
                        'supplier_article' => $row['article'],
                        'supplier_article_compact' => $row['article_key'],
                        'supplier_name' => $row['partner_name'] ?: $product->name,
                        'source_url' => AzbukaTeplaPriceList::RETAIL_SOURCE_URL,
                        'price' => $row['purchase_byn'],
                        'currency' => 'BYN',
                        'currency_rate' => 1,
                        'price_byn' => $row['purchase_byn'],
                        'in_stock' => false,
                        'stock_quantity' => null,
                        'stock_status' => 'check_availability',
                        'stock_text' => 'Уточнить наличие',
                        'warehouse_name' => null,
                        'delivery_days' => null,
                        'last_stock_synced_at' => null,
                        'match_status' => 'matched',
                        'match_confidence' => 'manual_price_list',
                        'raw' => array_merge($row, [
                            'public_status' => 'Доступно к заказу',
                            'manager_status' => 'Уточнить наличие',
                            'availability_source' => 'not_provided',
                        ]),
                        'last_synced_at' => now(),
                    ]);
                    if ($product->brand_id) {
                        $supplier->brands()->syncWithoutDetaching([$product->brand_id]);
                    }
                    $stats['offers']++;
                    $stats['stock_to_confirm']++;
                    $stats['linked_existing'] += $row['product_id'] ? 1 : 0;
                    $touched[] = (int) $product->id;
                }

                app(SupplierProductAvailabilityService::class)->refreshMany(array_values(array_unique($touched)));
                $sync->forceFill([
                    'last_run_at' => now(),
                    'last_status' => 'updated',
                    'last_exit_code' => 0,
                ])->save();

                return $stats;
            });

            foreach ($stats as $key => $value) {
                $this->line("metric.azbuka_tepla.{$key}={$value}");
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
    private function classify(array $rows, AzbukaTeplaPriceList $parser, ?Supplier $supplier): array
    {
        $products = Product::query()->with('brand:id,name')->get([
            'id', 'brand_id', 'supplier_id', 'name', 'sku', 'images', 'content', 'specs', 'is_archived', 'requires_price_confirmation', 'price',
        ]);
        $existing = $supplier
            ? $supplier->supplierProducts()->whereNotNull('product_id')->get()->keyBy('supplier_article_normalized')
            : collect();
        $globalLinks = SupplierProduct::query()->whereNotNull('product_id')->get([
            'product_id', 'supplier_article', 'supplier_article_normalized',
        ])->groupBy(fn (SupplierProduct $link): string => $parser->key(
            $link->supplier_article_normalized ?: $link->supplier_article
        ));

        foreach ($rows as &$row) {
            $ids = [];
            $reason = 'unmatched';
            $familyMatches = $this->knownFamilyMatches($products, $row, $parser, $supplier);
            if (count($familyMatches) === 1) {
                $ids = $familyMatches;
                $reason = 'exact_brand_family_model';
            } elseif (count($familyMatches) > 1) {
                $ids = $familyMatches;
                $reason = 'ambiguous';
            } elseif ($link = $existing->get($row['article_key'])) {
                $ids = [(int) $link->product_id];
                $reason = 'existing_supplier_link';
            } else {
                $brandKey = $parser->key($row['brand']);
                $brandProducts = $products->filter(fn (Product $product): bool =>
                    $parser->key($product->brand?->name) === $brandKey
                );
                $exactSku = $brandProducts->filter(fn (Product $product): bool =>
                    $parser->key($product->sku) === $row['model_key']
                    || $parser->key($product->sku) === $row['article_key']
                )->pluck('id')->map(fn ($id): int => (int) $id)->all();
                $ids = array_values(array_unique($exactSku));
                $reason = count($ids) === 1 ? 'exact_brand_sku' : (count($ids) > 1 ? 'ambiguous' : 'unmatched');

                if ($ids === []) {
                    $linked = collect($globalLinks->get($row['model_key'], []))
                        ->merge($globalLinks->get($row['article_key'], []))
                        ->pluck('product_id')->map(fn ($id): int => (int) $id)->unique()->values()->all();
                    if ($linked !== []) {
                        $ids = $linked;
                        $reason = count($ids) === 1 ? 'exact_supplier_article' : 'ambiguous';
                    }
                }
                if ($ids === [] && mb_strlen($row['model_key']) >= 4) {
                    $ids = $brandProducts->filter(function (Product $product) use ($parser, $row): bool {
                        $haystack = $parser->key($product->name.' '.$product->sku.' '.json_encode($product->specs, JSON_UNESCAPED_UNICODE));

                        return str_contains($haystack, $row['model_key']);
                    })->pluck('id')->map(fn ($id): int => (int) $id)->unique()->values()->all();
                    $reason = count($ids) === 1 ? 'unique_brand_model' : (count($ids) > 1 ? 'ambiguous' : 'unmatched');
                }
            }
            $row['product_id'] = count($ids) === 1 ? $ids[0] : null;
            $row['match_reason'] = $reason;
        }
        unset($row);

        return $rows;
    }

    /** @return array<int,int> */
    private function knownFamilyMatches($products, array $row, AzbukaTeplaPriceList $parser, ?Supplier $supplier): array
    {
        if ($this->isViessmannRow($row, $parser)) {
            return $this->knownViessmannMatches($products, $row, $parser, $supplier);
        }

        if (! $this->isProthermSkatRow($row, $parser)
            || ! preg_match('/^(\d+)\s*скат$/ui', trim((string) $row['model']), $match)) {
            return [];
        }

        $power = preg_quote($match[1], '/');
        $matches = $products->filter(function (Product $product) use ($parser, $power): bool {
            if ($parser->key($product->brand?->name) !== 'protherm') {
                return false;
            }
            $haystack = mb_strtolower($product->name.' '.json_encode($product->specs, JSON_UNESCAPED_UNICODE));
            if (! str_contains($haystack, 'скат') && ! str_contains($haystack, 'ray')) {
                return false;
            }

            return (bool) preg_match(
                '/(?:скат|ray)[^\d]{0,12}'.$power.'(?!\d)|(?<!\d)'.$power.'[^\d]{0,8}(?:скат|ray)/ui',
                $haystack
            );
        });

        if ($supplier) {
            $canonical = $matches->filter(fn (Product $product): bool => (int) $product->supplier_id !== (int) $supplier->id);
            if ($canonical->isNotEmpty()) {
                $matches = $canonical;
            }
        }

        return $matches->pluck('id')->map(fn ($id): int => (int) $id)->unique()->values()->all();
    }

    /** @return array<int,int> */
    private function knownViessmannMatches($products, array $row, AzbukaTeplaPriceList $parser, ?Supplier $supplier): array
    {
        $source = trim(($row['partner_model'] ?? '').' '.($row['model'] ?? ''));
        if (! preg_match('/\b(A1HB|A1JB|B1HF)\D{0,8}(\d{2})\b/ui', $source, $match)) {
            return [];
        }

        $family = mb_strtoupper($match[1]);
        $power = preg_quote($match[2], '/');
        $matches = $products->filter(function (Product $product) use ($parser, $family, $power): bool {
            if ($parser->key($product->brand?->name) !== 'viessmann') {
                return false;
            }
            $haystack = mb_strtoupper($product->name.' '.json_encode($product->specs, JSON_UNESCAPED_UNICODE));

            return (bool) preg_match('/\b'.preg_quote($family, '/').'\D{0,8}'.$power.'(?!\d)/u', $haystack);
        });

        if ($supplier) {
            $canonical = $matches->filter(fn (Product $product): bool => (int) $product->supplier_id !== (int) $supplier->id);
            if ($canonical->isNotEmpty()) {
                $matches = $canonical;
            }
        }
        if ($matches->count() <= 1) {
            return $matches->pluck('id')->map(fn ($id): int => (int) $id)->values()->all();
        }

        $scored = $matches->mapWithKeys(fn (Product $product): array => [
            $product->id => $this->viessmannCardScore($product),
        ]);
        $best = $scored->max();
        $ids = $scored->filter(fn (int $score): bool => $score === $best)->keys();

        return $ids->map(fn ($id): int => (int) $id)->values()->all();
    }

    private function viessmannCardScore(Product $product): int
    {
        $images = is_array($product->images) ? $product->images : [];
        $firstImage = trim((string) ($images[0] ?? ''));
        $localImage = $firstImage !== ''
            && ! str_starts_with($firstImage, 'http://')
            && ! str_starts_with($firstImage, 'https://');
        $specCount = is_array($product->specs) ? count($product->specs) : 0;
        $contentLength = mb_strlen(trim(strip_tags((string) $product->content)));

        return ($localImage ? 100000 : ($firstImage !== '' ? 50000 : 0))
            + min($specCount, 100) * 100
            + min($contentLength, 5000);
    }

    private function isProthermSkatRow(array $row, AzbukaTeplaPriceList $parser): bool
    {
        return $parser->key($row['brand'] ?? null) === 'protherm'
            && preg_match('/^\d+\s*скат$/ui', trim((string) ($row['model'] ?? ''))) === 1;
    }

    private function isViessmannRow(array $row, AzbukaTeplaPriceList $parser): bool
    {
        return $parser->key($row['brand'] ?? null) === 'viessmann';
    }

    private function createProduct(array $row, Supplier $supplier): ?Product
    {
        $category = $this->categoryFor($row);
        if (! $category) {
            return null;
        }
        $brand = Brand::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($row['brand'])])->first();
        $brand ??= Brand::query()->create([
            'name' => $row['brand'],
            'slug' => $this->uniqueBrandSlug($row['brand']),
            'is_active' => true,
        ]);
        $name = $this->productName($row);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'supplier_id' => $supplier->id,
            'name' => $name,
            'slug' => $this->uniqueProductSlug($name),
            'sku' => $this->nextKotlovSku(),
            'price' => $row['retail_byn'],
            'currency' => 'BYN',
            'short_description' => $row['partner_description'] ?: null,
            'specs' => [
                ['key' => 'Бренд', 'value' => $row['brand']],
                ['key' => 'Модель', 'value' => $row['model']],
            ],
            'unit' => str_contains(mb_strtolower($row['description']), 'труба') ? 'м' : 'шт',
            'is_active' => true,
            'is_archived' => false,
            'in_stock' => false,
            'availability_status' => Product::AVAILABILITY_CHECK,
            'stock_qty' => null,
            'requires_price_confirmation' => false,
            'manual_stock_management' => false,
        ]);
        $supplier->brands()->syncWithoutDetaching([$brand->id]);

        return $product;
    }

    /** @param array<string,mixed> $row */
    private function categoryFor(array $row): ?Category
    {
        $description = (string) ($row['description'] ?? '');
        $value = mb_strtolower($description);
        $productText = mb_strtolower(trim(implode(' ', [
            $description,
            (string) ($row['partner_name'] ?? ''),
            (string) ($row['partner_description'] ?? ''),
        ])));
        $slugs = match (true) {
            str_contains($productText, 'термостатический элемент') => ['komplektuyushhie-dlya-otopleniya'],
            str_contains($value, 'трехход'), str_contains($value, 'трёхход') => ['smesitelnye-klapany-i-uzly', 'smesitelnaya-armatura'],
            str_contains($value, 'котел газ') => ['gazovye', 'gazovye-kotly'],
            str_contains($value, 'котел электр') => ['elektricheskie', 'elektricheskie-kotly'],
            str_contains($value, 'бойлер косвен') => ['kosvennye', 'vodonagrevateli-kosvennogo-nagreva'],
            str_contains($value, 'водонагревател') => ['vodonagrevateli', 'vodogreynaya-kolonka'],
            str_contains($value, 'термостат'), str_contains($value, 'автомат') => ['avtomatika-i-termoregulyatory', 'avtomatika'],
            str_contains($value, 'насос') => ['tsirkulyatsionnyie', 'nasosy', 'nasosyi'],
            str_contains($value, 'радиатор') => ['radiatory'],
            str_contains($value, 'труба') => ['truby', 'truby-dlya-otopleniya'],
            str_contains($value, 'клапан') => ['klapany', 'zaporno-reguliruyushchaya-armatura'],
            default => ['otoplenie'],
        };
        $category = Category::query()->whereIn('slug', $slugs)->orderByRaw(
            'CASE slug '.collect($slugs)->values()->map(fn ($slug, $index): string => "WHEN '".str_replace("'", "''", $slug)."' THEN {$index}")->implode(' ').' ELSE 999 END'
        )->first();
        if ($category) {
            return $category;
        }

        $needle = match (true) {
            str_contains($value, 'котел газ') => 'газов',
            str_contains($value, 'котел электр') => 'электр',
            str_contains($value, 'бойлер'), str_contains($value, 'водонагревател') => 'водонагрев',
            str_contains($value, 'термостат'), str_contains($value, 'автомат') => 'автомат',
            str_contains($value, 'насос') => 'насос',
            str_contains($value, 'радиатор') => 'радиатор',
            str_contains($value, 'труба') => 'труб',
            str_contains($value, 'клапан') => 'клапан',
            default => 'отоплен',
        };

        return Category::query()->whereRaw('LOWER(name) LIKE ?', ['%'.$needle.'%'])->first();
    }

    /** @return array{0:Supplier,1:SupplierSync} */
    private function ensureSupplier(?Supplier $supplier, array $payload): array
    {
        $supplier ??= new Supplier;
        $supplier->forceFill([
            'code' => 'azbuka-tepla',
            'name' => $supplier->exists ? $supplier->name : 'Азбука Тепла',
            'currency' => 'BYN',
            'currency_rate' => 1,
            'contact' => AzbukaTeplaPriceList::RETAIL_SOURCE_URL,
            'notes' => 'Розничные цены — Google Таблица; закупочная цена — колонка «Опт VIP» партнерского прайса. Остатки не передаются: менеджер уточняет наличие.',
            'is_active' => true,
        ])->save();

        SupplierSource::query()->updateOrCreate([
            'supplier_id' => $supplier->id,
            'name' => 'Азбука Тепла — розничные цены',
        ], [
            'type' => 'google_sheet',
            'config' => [
                'url' => AzbukaTeplaPriceList::RETAIL_SOURCE_URL,
                'sheet' => 'Лист1',
                'source_date' => $payload['source_date'],
                'pricing' => 'Розничная цена BYN',
                'availability' => 'Не указано',
            ],
            'schedule_cron' => 'По запросу',
            'is_active' => true,
        ]);
        SupplierSource::query()->updateOrCreate([
            'supplier_id' => $supplier->id,
            'name' => 'Азбука Тепла — партнерский прайс Опт VIP',
        ], [
            'type' => 'excel',
            'config' => [
                'file' => $payload['partner_source_file'],
                'source_date' => $payload['source_date'],
                'pricing' => 'Опт VIP — закупочная цена BYN',
                'availability' => 'Не указано',
            ],
            'schedule_cron' => 'После получения нового файла',
            'is_active' => true,
        ]);
        $sync = SupplierSync::query()->updateOrCreate(['key' => 'azbuka_tepla_price'], [
            'supplier_id' => $supplier->id,
            'name' => 'Азбука Тепла',
            'code' => 'azbuka-tepla',
            'title' => 'Азбука Тепла: РРЦ и Опт VIP',
            'description' => 'Обновляет розницу и закупку; клиенту показывает «Доступно к заказу», менеджеру — «Уточнить наличие».',
            'command' => 'supplier:sync-azbuka-tepla',
            'source_url' => AzbukaTeplaPriceList::RETAIL_SOURCE_URL,
            'is_active' => true,
        ]);

        return [$supplier, $sync];
    }

    private function productName(array $row): string
    {
        $productText = mb_strtolower(trim(implode(' ', [
            (string) ($row['description'] ?? ''),
            (string) ($row['partner_name'] ?? ''),
            (string) ($row['partner_description'] ?? ''),
        ])));
        $prefix = match (true) {
            str_contains($productText, 'термостатический элемент'), str_contains($productText, 'термоэлемент') => 'Термостатический элемент',
            mb_strtolower($row['description']) === 'котел газовый' => 'Газовый котел',
            mb_strtolower($row['description']) === 'котел электрический' => 'Электрический котел',
            mb_strtolower($row['description']) === 'бойлер косвенного нагрева' => 'Бойлер косвенного нагрева',
            mb_strtolower($row['description']) === 'термостат комнатный' => 'Комнатный термостат',
            mb_strtolower($row['description']) === 'насос' => 'Насос',
            mb_strtolower($row['description']) === 'труба' => 'Труба',
            default => Str::ucfirst($row['description']),
        };

        return trim($prefix.' '.$row['brand'].' '.$row['model']);
    }

    private function nextKotlovSku(): string
    {
        $max = Product::query()->where('sku', 'like', 'KOTLOV-%')->pluck('sku')
            ->map(fn ($sku): int => preg_match('/^KOTLOV-(\d+)$/', (string) $sku, $match) ? (int) $match[1] : 0)
            ->max() ?? 0;

        do {
            $sku = sprintf('KOTLOV-%06d', ++$max);
        } while (Product::query()->where('sku', $sku)->exists());

        return $sku;
    }

    private function uniqueProductSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'azbuka-tepla-product';
        $slug = $base;
        $suffix = 2;
        while (Product::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    private function uniqueBrandSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'brand';
        $slug = $base;
        $suffix = 2;
        while (Brand::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    private function metrics(array $payload, array $rows): void
    {
        $counts = collect($rows)->countBy('match_reason');
        foreach ([
            'source_rows' => $payload['retail_rows'],
            'price_pairs' => count($rows),
            'source_review' => $payload['review_rows'],
            'selected_rows' => count($rows),
            'matched_existing' => collect($rows)->whereNotNull('product_id')->count(),
            'known_family_matches' => $counts->get('exact_brand_family_model', 0),
            'would_create' => collect($rows)->whereNull('product_id')->where('match_reason', 'unmatched')->count(),
            'ambiguous_catalog' => $counts->get('ambiguous', 0),
            'stock_to_confirm' => count($rows),
        ] as $key => $value) {
            $this->line("metric.azbuka_tepla.{$key}={$value}");
        }
    }

    private function absolutePath(string $path): string
    {
        return preg_match('~^(?:[A-Za-z]:[\\\\/]|/)~', $path) ? $path : base_path($path);
    }
}
