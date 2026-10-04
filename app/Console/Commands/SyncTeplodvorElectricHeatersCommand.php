<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Services\TeplodvorElectricHeaterScraper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SyncTeplodvorElectricHeatersCommand extends Command
{
    protected $signature = 'supplier:sync-teplodvor-electric-heaters
        {--apply : Restore/create and update exact in-stock matches; default is dry-run}
        {--limit=0 : Limit source cards for a controlled test, 0 means all}
        {--sleep=120 : Milliseconds between source requests}
        {--include-on-order : Include source cards marked as available on order}
        {--no-images : Do not download product images}';

    protected $description = 'Safely sync current Teplodvor electric sauna heaters without archiving unmatched products';

    public function handle(TeplodvorElectricHeaterScraper $scraper): int
    {
        $category = Category::query()->where('slug', 'elektrokamenki')->first();
        if (! $category) {
            $this->error('Category elektrokamenki was not found.');

            return self::FAILURE;
        }

        $urls = $scraper->discoverUrls();
        $limit = max(0, (int) $this->option('limit'));
        if ($limit > 0) {
            $urls = array_slice($urls, 0, $limit, true);
        }

        $this->line($this->option('apply') ? 'APPLY MODE' : 'DRY RUN — no database or file writes');
        $this->info('Discovered source product pages: '.count($urls));

        $existing = Product::query()
            ->where('category_id', $category->id)
            ->with('brand:id,name')
            ->get();
        $existingByKey = $existing->groupBy(fn (Product $product) => $this->matchKey(
            $scraper,
            $product->name,
            $product->brand?->name,
        ));

        $stats = [
            'scanned' => 0,
            'in_stock' => 0,
            'on_order' => 0,
            'skipped_unavailable' => 0,
            'exact_existing' => 0,
            'would_restore' => 0,
            'would_create' => 0,
            'ambiguous' => 0,
            'invalid' => 0,
            'applied' => 0,
        ];
        $actions = [];

        foreach ($urls as $url) {
            $card = $scraper->scrape($url);
            $stats['scanned']++;
            if (! $card || ! $card['brand'] || $card['price'] <= 0) {
                $stats['invalid']++;

                continue;
            }

            if ($card['status'] === 'in_stock') {
                $stats['in_stock']++;
            } elseif ($card['status'] === 'on_order') {
                $stats['on_order']++;
                if (! $this->option('include-on-order')) {
                    $stats['skipped_unavailable']++;

                    continue;
                }
            } else {
                $stats['skipped_unavailable']++;

                continue;
            }

            $key = $this->matchKey($scraper, $card['name'], $card['brand']);
            $matches = $existingByKey->get($key, collect());
            if ($matches->count() > 1) {
                $stats['ambiguous']++;
                $actions[] = ['AMBIGUOUS', '—', $card['brand'], $card['name'], $card['price']];

                continue;
            }

            $product = $matches->first();
            if ($product) {
                $stats['exact_existing']++;
                if ($product->is_archived || ! $product->is_active) {
                    $stats['would_restore']++;
                    $action = 'RESTORE';
                } else {
                    $action = 'UPDATE';
                }
            } else {
                $stats['would_create']++;
                $action = 'CREATE';
            }

            $actions[] = [$action, $product?->id ?? '—', $card['brand'], $card['name'], number_format($card['price'], 2, '.', '')];

            if ($this->option('apply')) {
                $this->applyCard($category, $card, $product);
                $stats['applied']++;
            }

            usleep(max(0, (int) $this->option('sleep')) * 1000);
        }

        $this->table(['Metric', 'Count'], collect($stats)->map(fn ($count, $metric) => [$metric, $count])->values());
        $this->table(['Action', 'Product ID', 'Brand', 'Product', 'Price BYN'], array_slice($actions, 0, 80));
        $this->line('No source-missing product was archived or deactivated.');

        return self::SUCCESS;
    }

    private function applyCard(
        Category $category,
        array $card,
        ?Product $product,
    ): void {
        $product = DB::transaction(function () use ($category, $card, $product) {
            $brand = Brand::query()->firstOrCreate(
                ['name' => $card['brand']],
                ['slug' => Str::slug($card['brand']), 'is_active' => true],
            );
            $supplier = Supplier::query()->firstOrCreate(
                ['code' => 'teplodvor-site'],
                [
                    'name' => 'Теплодвор (публичный каталог)',
                    'currency' => 'BYN',
                    'currency_rate' => 1,
                    'contact' => TeplodvorElectricHeaterScraper::BASE_URL,
                    'notes' => 'Контрольный источник ассортимента электрокаменок; импорт не архивирует отсутствующие карточки.',
                    'is_active' => true,
                ],
            );

            $availability = $card['status'] === 'in_stock'
                ? Product::AVAILABILITY_IN_STOCK
                : Product::AVAILABILITY_CHECK;
            $attributes = [
                'category_id' => $category->id,
                'brand_id' => $brand->id,
                'name' => $card['name'],
                'price' => $card['price'],
                'currency' => 'BYN',
                'specs' => $card['specs'],
                'is_active' => true,
                'is_archived' => false,
                'in_stock' => $card['status'] === 'in_stock',
                'availability_status' => $availability,
            ];

            if ($product) {
                $product->fill($attributes)->save();
            } else {
                $product = Product::query()->create($attributes + [
                    'slug' => $this->uniqueSlug($card['name']),
                    'sku' => null,
                    'images' => [],
                    'short_description' => 'Электрическая печь для бани и сауны. Актуальные параметры приведены в характеристиках.',
                    'is_new' => true,
                ]);
                $product->update(['sku' => sprintf('KOTLOV-%06d', $product->id)]);
            }

            SupplierProduct::query()->updateOrCreate(
                ['supplier_id' => $supplier->id, 'supplier_article' => $card['article']],
                [
                    'product_id' => $product->id,
                    'product_sku' => $product->sku,
                    'supplier_article_normalized' => mb_strtolower($card['article']),
                    'supplier_article_compact' => preg_replace('/[^a-z0-9]+/i', '', mb_strtolower($card['article'])),
                    'supplier_name' => $card['name'],
                    'source_url' => $card['url'],
                    'price' => $card['price'],
                    'currency' => 'BYN',
                    'currency_rate' => 1,
                    'price_byn' => $card['price'],
                    'in_stock' => $card['status'] === 'in_stock',
                    'stock_status' => $card['status'],
                    'stock_text' => $card['status'] === 'in_stock' ? 'Есть в наличии' : 'Под заказ',
                    'match_status' => 'matched',
                    'match_confidence' => 'exact_model',
                    'raw' => $card,
                    'last_synced_at' => now(),
                    'last_stock_synced_at' => now(),
                ],
            );

            return $product;
        });

        // Keep remote image downloads outside the database transaction.
        if (! $this->option('no-images') && $card['images'] !== []) {
            $this->downloadImages($product, $card['images']);
        }
    }

    private function matchKey(TeplodvorElectricHeaterScraper $scraper, string $name, ?string $brand): string
    {
        return mb_strtolower((string) $brand).'|'.$scraper->canonicalModel($name, $brand);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 2;
        while (Product::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    private function downloadImages(Product $product, array $urls): void
    {
        $saved = [];
        $directory = public_path('img/products/teplodvor-electric');
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        foreach (array_slice($urls, 0, 4) as $index => $url) {
            try {
                $response = Http::timeout(25)->retry(2, 300)->get($url);
                if (! $response->successful() || strlen($response->body()) < 2500) {
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
                $saved[] = 'img/products/teplodvor-electric/'.$filename;
            } catch (\Throwable) {
                continue;
            }
        }

        if ($saved !== []) {
            $product->update(['images' => $saved]);
        }
    }
}
