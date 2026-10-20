<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\SupplierSync;
use App\Services\SupplierProductAvailabilityService;
use App\Services\Suppliers\DomKotlovVenmaScraper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SyncDomKotlovVenmaCommand extends Command
{
    protected $signature = 'supplier:sync-domkotlov-venma
        {--apply : Create or update VENMA cards; default is dry-run}
        {--limit=0 : Limit source cards for a controlled check}
        {--sleep=150 : Delay between source requests in milliseconds}
        {--no-images : Do not download images}';

    protected $description = 'Sync VENMA pellet burners from Dom kotlov: retail price, 15% purchase discount and live availability';

    public function handle(DomKotlovVenmaScraper $scraper): int
    {
        $category = Category::query()->where('slug', 'pelletnye-gorelki')->first();
        if (! $category) {
            $this->error('Категория pelletnye-gorelki не найдена.');

            return self::FAILURE;
        }

        try {
            $urls = $scraper->discoverUrls();
        } catch (\Throwable $e) {
            $this->error('Не удалось открыть каталог Дом котлов: '.$e->getMessage());

            return self::FAILURE;
        }

        $limit = max(0, (int) $this->option('limit'));
        if ($limit > 0) {
            $urls = array_slice($urls, 0, $limit);
        }

        if ($urls === []) {
            $this->error('В каталоге поставщика не найдено ни одной карточки VENMA. Синхронизация остановлена.');

            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');
        $this->line($apply ? 'APPLY MODE' : 'DRY RUN — изменений в базе и файлах не будет');
        $this->info('Найдено ссылок VENMA: '.count($urls));

        $brand = Brand::query()->whereRaw('LOWER(name) = ?', ['venma'])->first();
        $supplier = $this->findSupplier();
        $seenArticles = [];
        $touchedProductIds = [];
        $rows = [];
        $stats = ['scanned' => 0, 'valid' => 0, 'in_stock' => 0, 'created' => 0, 'updated' => 0, 'invalid' => 0, 'errors' => 0, 'missing_marked_unavailable' => 0];

        foreach ($urls as $url) {
            try {
                $card = $scraper->scrape($url);
                $stats['scanned']++;
                if (! $card) {
                    $stats['invalid']++;
                    $rows[] = ['SKIP', '—', '—', '—', 'Некорректная карточка: '.$url];
                    continue;
                }

                $card = $scraper->applyWarehouseAvailabilityPolicy($card);

                $stats['valid']++;
                $stats['in_stock'] += $card['in_stock'] ? 1 : 0;
                $seenArticles[] = $card['article'];
                $product = $this->findProduct($card, $brand, $supplier);
                $action = $product ? 'UPDATE' : 'CREATE';
                $rows[] = [
                    $action,
                    $card['model'],
                    number_format($card['retail_price'], 2, '.', ' '),
                    number_format($card['purchase_price'], 2, '.', ' '),
                    $card['in_stock'] ? 'В наличии' : 'Под заказ',
                ];

                if ($apply) {
                    $brand ??= $this->ensureBrand();
                    $supplier ??= $this->ensureSupplier();
                    $sync = $this->ensureSync($supplier);
                    $product = $this->saveCard($category, $brand, $supplier, $sync, $card, $product);
                    $touchedProductIds[] = $product->id;
                    $stats[$action === 'CREATE' ? 'created' : 'updated']++;

                    if (! $this->option('no-images') && $card['images'] !== [] && empty($product->images)) {
                        $this->downloadImages($product, $card['images']);
                    }
                }

                usleep(max(0, (int) $this->option('sleep')) * 1000);
            } catch (\Throwable $e) {
                $stats['errors']++;
                $rows[] = ['ERROR', '—', '—', '—', Str::limit($e->getMessage(), 90)];
            }
        }

        // Only a complete, error-free catalogue scan may conclude that a
        // previously known card disappeared. A limited/manual check or a
        // partial network failure must never hide the other VENMA offers.
        if ($apply && $supplier && $limit === 0 && $stats['valid'] > 0 && $stats['errors'] === 0) {
            $missing = SupplierProduct::query()
                ->where('supplier_id', $supplier->id)
                ->where('raw->catalog', 'domkotlov_venma')
                ->whereNotIn('supplier_article', $seenArticles)
                ->get();
            foreach ($missing as $offer) {
                $offer->update([
                    'in_stock' => false,
                    'stock_quantity' => 0,
                    'stock_status' => 'out_of_stock',
                    'stock_text' => 'Карточка отсутствует в актуальном разделе VENMA',
                    'last_stock_synced_at' => now(),
                    'last_synced_at' => now(),
                ]);
                if ($offer->product_id) {
                    $touchedProductIds[] = $offer->product_id;
                }
                $stats['missing_marked_unavailable']++;
            }

            app(SupplierProductAvailabilityService::class)->refreshMany(array_values(array_unique($touchedProductIds)));
            $this->ensureSync($supplier)->update([
                'last_run_at' => now(),
                'last_status' => $stats['errors'] === 0 ? 'updated' : 'failed',
                'last_exit_code' => $stats['errors'] === 0 ? self::SUCCESS : self::FAILURE,
            ]);
        }

        $this->table(['Действие', 'Модель', 'Розница BYN', 'Закупка −15% BYN', 'Наличие'], $rows);
        $this->table(['Показатель', 'Количество'], collect($stats)->map(fn ($value, $key) => [$key, $value])->values());

        return $stats['errors'] === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function findSupplier(): ?Supplier
    {
        return Supplier::query()
            ->whereIn('code', ['domkotlov', 'dom-kotlov', 'dom_kotlov'])
            ->orWhereRaw('LOWER(name) LIKE ?', ['%дом%котл%'])
            ->orderBy('id')
            ->first();
    }

    private function ensureBrand(): Brand
    {
        return Brand::query()->firstOrCreate(
            ['slug' => 'venma'],
            ['name' => 'VENMA', 'h1' => 'VENMA', 'country' => 'Польша', 'is_active' => true],
        );
    }

    private function ensureSupplier(): Supplier
    {
        return Supplier::query()->create([
            'code' => 'dom-kotlov',
            'name' => 'Дом котлов',
            'currency' => 'BYN',
            'currency_rate' => 1,
            'contact' => DomKotlovVenmaScraper::BASE_URL,
            'notes' => 'VENMA: закупочная цена рассчитывается от публичной розницы Дом котлов со скидкой 15%.',
            'is_active' => true,
        ]);
    }

    private function ensureSync(Supplier $supplier): SupplierSync
    {
        return SupplierSync::query()->updateOrCreate(
            ['key' => 'domkotlov_venma'],
            [
                'supplier_id' => $supplier->id,
                'name' => 'VENMA — Дом котлов',
                'code' => 'domkotlov-venma',
                'title' => 'Пеллетные горелки VENMA',
                'description' => 'Розница и наличие с domkotlov.by; закупка автоматически рассчитывается со скидкой 15%.',
                'command' => 'supplier:sync-domkotlov-venma',
                'source_url' => DomKotlovVenmaScraper::CATEGORY_URL,
                'image_disk_path' => 'img/products/venma',
                'is_active' => true,
            ],
        );
    }

    private function findProduct(array $card, ?Brand $brand, ?Supplier $supplier): ?Product
    {
        if ($supplier) {
            $offer = SupplierProduct::query()
                ->where('supplier_id', $supplier->id)
                ->where(fn ($query) => $query
                    ->where('supplier_article', $card['article'])
                    ->orWhere('source_url', $card['url']))
                ->whereNotNull('product_id')
                ->first();
            if ($offer?->product) {
                return $offer->product;
            }
        }

        if (! $brand) {
            return null;
        }

        $matches = Product::query()
            ->where('brand_id', $brand->id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($card['name'])])
            ->get();

        return $matches->count() === 1 ? $matches->first() : null;
    }

    private function saveCard(Category $category, Brand $brand, Supplier $supplier, SupplierSync $sync, array $card, ?Product $product): Product
    {
        return DB::transaction(function () use ($category, $brand, $supplier, $sync, $card, $product): Product {
            $attributes = [
                'category_id' => $category->id,
                'brand_id' => $brand->id,
                'supplier_id' => $supplier->id,
                'name' => $card['name'],
                'h1' => $card['name'],
                'price' => $card['retail_price'],
                'currency' => 'BYN',
                'short_description' => $card['short_description'] ?: 'Автоматическая пеллетная горелка VENMA Comfort '.$card['model'].'.',
                'specs' => $card['specs'],
                'content_source_url' => $card['url'],
                'unit' => 'шт.',
                'is_active' => true,
                'is_archived' => false,
                'in_stock' => $card['in_stock'],
                'availability_status' => $card['in_stock'] ? Product::AVAILABILITY_IN_STOCK : Product::AVAILABILITY_CHECK,
                'stock_qty' => null,
                'requires_price_confirmation' => false,
                'meta_title' => $card['name'].' купить в %city%',
                'meta_description' => $card['name'].' — цена, наличие и характеристики. Доставка по Беларуси.',
            ];

            if ($product) {
                $product->fill($attributes);
                if (blank($product->content)) {
                    $product->content = $this->description($card);
                }
                $product->save();
            } else {
                $product = Product::query()->create($attributes + [
                    'slug' => $this->uniqueSlug($card['name']),
                    'sku' => null,
                    'images' => [],
                    'content' => $this->description($card),
                    'is_new' => true,
                ]);
                $product->update(['sku' => sprintf('KOTLOV-%06d', $product->id)]);
            }

            SupplierProduct::query()->updateOrCreate(
                ['supplier_id' => $supplier->id, 'supplier_article' => $card['article']],
                [
                    'supplier_sync_id' => $sync->id,
                    'product_id' => $product->id,
                    'product_sku' => $product->sku,
                    'supplier_article_normalized' => mb_strtolower($card['article']),
                    'supplier_article_compact' => preg_replace('/[^A-Z0-9]+/', '', mb_strtoupper($card['article'])),
                    'supplier_name' => $card['name'],
                    'source_url' => $card['url'],
                    'source_wp_id' => $card['source_id'],
                    'price' => $card['purchase_price'],
                    'currency' => 'BYN',
                    'currency_rate' => 1,
                    'price_byn' => $card['purchase_price'],
                    'in_stock' => $card['in_stock'],
                    'stock_quantity' => null,
                    'stock_status' => $card['stock_status'],
                    'stock_text' => $card['stock_text'],
                    'warehouse_name' => 'Дом котлов',
                    'last_stock_synced_at' => now(),
                    'match_status' => 'matched',
                    'match_confidence' => 'exact_source_card',
                    'raw' => $card + ['catalog' => 'domkotlov_venma', 'partner_discount_percent' => 15],
                    'last_synced_at' => now(),
                ],
            );

            return $product->fresh();
        });
    }

    private function description(array $card): string
    {
        $name = e($card['name']);
        $model = e($card['model']);

        return <<<HTML
<p><strong>{$name}</strong> — автоматическая факельная пеллетная горелка для установки в совместимый твердотопливный котёл. Модель Comfort {$model} помогает автоматизировать розжиг, подачу топлива и поддержание заданной температуры.</p>
<h3>Подбор и комплектация</h3>
<p>Перед покупкой необходимо проверить мощность котла, размеры топочной камеры, посадочный фланец и требуемую комплектацию. Специалисты KOTLOV.BY помогут подобрать горелку, бункер, шнек и автоматику под конкретную котельную.</p>
<h3>Цена и наличие</h3>
<p>Цена и доступность этой модели автоматически обновляются по данным поставщика «Дом котлов». Актуальный статус указан в карточке товара.</p>
HTML;
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'pelletnaya-gorelka-venma';
        $slug = $base;
        $suffix = 2;
        while (Product::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    private function downloadImages(Product $product, array $urls): void
    {
        $directory = public_path('img/products/venma');
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $saved = [];
        foreach (array_slice($urls, 0, 8) as $index => $url) {
            try {
                $response = Http::withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36',
                    'Referer' => DomKotlovVenmaScraper::CATEGORY_URL,
                ])->timeout(30)->retry(2, 300)->get($url);
                if (! $response->successful() || strlen($response->body()) < 1500) {
                    continue;
                }
                $size = @getimagesizefromstring($response->body());
                if (! $size || $size[0] < 200 || $size[1] < 200) {
                    continue;
                }
                $extension = match ($size['mime'] ?? '') {
                    'image/png' => 'png',
                    'image/webp' => 'webp',
                    default => 'jpg',
                };
                $filename = $product->id.'-'.($index + 1).'.'.$extension;
                file_put_contents($directory.DIRECTORY_SEPARATOR.$filename, $response->body());
                $saved[] = 'img/products/venma/'.$filename;
            } catch (\Throwable) {
                continue;
            }
        }

        if ($saved !== []) {
            $product->update(['images' => $saved]);
        }
    }
}
