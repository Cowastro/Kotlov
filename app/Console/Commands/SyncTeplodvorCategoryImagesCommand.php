<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SyncTeplodvorCategoryImagesCommand extends Command
{
    protected $signature = 'supplier:sync-teplodvor-category-images
        {--brand= : Brand name to match in local catalog}
        {--category-url=* : Teplodvor category URL to scan}
        {--apply : Download images and update products}
        {--force : Update even when the first local image exists}
        {--limit=0 : Max products to check, 0 means no limit}
        {--offset=0 : Skip products after sorting by id}';

    protected $description = 'Restore product images from a Teplodvor category page by exact title/model matching.';

    private const IMAGE_DIR = 'img/products/teplodvor-restored';

    private array $stats = [
        'source_items' => 0,
        'products' => 0,
        'matched' => 0,
        'already_ok' => 0,
        'would_update' => 0,
        'updated' => 0,
        'download_errors' => 0,
        'no_match' => 0,
    ];

    public function handle(): int
    {
        $brandFilter = trim((string) $this->option('brand'));
        $categoryUrls = array_values(array_filter((array) $this->option('category-url')));

        if ($brandFilter === '' || empty($categoryUrls)) {
            $this->error('--brand and at least one --category-url are required.');
            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');
        $force = (bool) $this->option('force');

        if (! $apply) {
            $this->warn('DRY RUN - no product images will be changed.');
        }

        $brand = DB::table('brands')
            ->whereRaw('LOWER(name) LIKE ?', ['%' . mb_strtolower($brandFilter) . '%'])
            ->first(['id', 'name']);

        if (! $brand) {
            $this->error("Brand matching {$brandFilter} not found.");
            return self::FAILURE;
        }

        $items = $this->loadCategoryItems($categoryUrls, $brand->name);
        $this->stats['source_items'] = count($items);
        $this->info('Teplodvor source items: ' . count($items));

        if (empty($items)) {
            return self::FAILURE;
        }

        $query = DB::table('products')
            ->where('brand_id', $brand->id)
            ->where('is_archived', false)
            ->select('id', 'name', 'slug', 'images')
            ->orderBy('id');

        $offset = (int) $this->option('offset');
        if ($offset > 0) {
            $query->offset($offset);
        }

        $limit = (int) $this->option('limit');
        if ($limit > 0) {
            $query->limit($limit);
        }

        $products = $query->get();
        $this->stats['products'] = $products->count();
        $this->info("{$brand->name} products to check: " . $products->count());

        $brandDir = self::IMAGE_DIR . '/' . Str::slug($brand->name);
        if ($apply && ! is_dir(public_path($brandDir))) {
            mkdir(public_path($brandDir), 0755, true);
        }

        foreach ($products as $product) {
            $key = $this->modelKey($product->name, $brand->name);
            $item = $items[$key] ?? null;

            if (! $item) {
                $this->stats['no_match']++;
                continue;
            }

            $this->stats['matched']++;

            if (! $force && $this->hasUsableLocalImage($product->images)) {
                $this->stats['already_ok']++;
                continue;
            }

            $imageUrl = $this->bestImageUrl($item);
            if ($imageUrl === null) {
                $this->stats['download_errors']++;
                $this->warn("No image URL for #{$product->id} {$product->name}");
                continue;
            }

            $this->line(sprintf('#%d %s -> %s', $product->id, $product->name, $imageUrl));

            $localPath = $brandDir . '/' . Str::slug($product->slug ?: $product->name) . '.' . $this->guessExt($imageUrl);

            if (! $apply) {
                $this->stats['would_update']++;
                continue;
            }

            if (! $this->downloadImage($imageUrl, public_path($localPath))) {
                $this->stats['download_errors']++;
                continue;
            }

            DB::table('products')->where('id', $product->id)->update([
                'images' => json_encode([$localPath], JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);

            $this->stats['updated']++;
        }

        $this->newLine();
        $this->table(
            ['metric', 'count'],
            collect($this->stats)->map(fn ($v, $k) => [$k, $v])->values()->all()
        );

        return self::SUCCESS;
    }

    private function loadCategoryItems(array $urls, string $brandName): array
    {
        $items = [];

        foreach ($urls as $url) {
            $html = $this->fetch($url);
            if ($html === null) {
                $this->warn('Failed to fetch ' . $url);
                continue;
            }

            if (! preg_match_all('/<img[^>]+src=["\']([^"\']+)["\'][^>]+alt=["\']([^"\']*' . preg_quote($brandName, '/') . '[^"\']*)["\'][^>]*>.*?<a[^>]+href=["\']([^"\']+)["\'][^>]*class=["\'][^"\']*shop-item-link[^"\']*["\'][^>]*>(.*?)<\/a>/siu', $html, $matches, PREG_SET_ORDER)) {
                continue;
            }

            foreach ($matches as $match) {
                $title = trim(html_entity_decode(strip_tags($match[4] ?: $match[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                $img = $this->absoluteTeplodvorUrl($match[1]);
                $productUrl = $this->absoluteTeplodvorUrl($match[3]);

                if ($title === '' || $img === '') {
                    continue;
                }

                $items[$this->modelKey($title, $brandName)] = [
                    'title' => $title,
                    'image_url' => $img,
                    'product_url' => $productUrl,
                ];
            }
        }

        return $items;
    }

    private function bestImageUrl(array $item): ?string
    {
        $detailHtml = $this->fetch($item['product_url']);
        if ($detailHtml !== null) {
            if (preg_match('/<a[^>]+href=["\']([^"\']*userfls\/shop\/large[^"\']*\.(?:jpg|jpeg|png|webp))["\']/i', $detailHtml, $large)) {
                return $this->absoluteTeplodvorUrl($large[1]);
            }
            if (preg_match('/<img[^>]+src=["\']([^"\']*userfls\/shop\/(?:large|medium|small)[^"\']*\.(?:jpg|jpeg|png|webp))["\']/i', $detailHtml, $image)) {
                return $this->absoluteTeplodvorUrl($image[1]);
            }
        }

        return $item['image_url'] ?? null;
    }

    private function modelKey(string $productName, string $brandName): string
    {
        $name = html_entity_decode(strip_tags($productName), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $name = preg_replace('/\b' . preg_quote($brandName, '/') . '\b/ui', '', $name);
        $name = preg_replace('/\([^)]*\)/u', '', $name);
        $name = preg_replace('/\b(водонагреватель|кот[её]л|радиатор|конвектор|насос|кондиционер|обогреватель|электрический|газовый|накопительный|настенный|напольный|проточный|твердотопливный)\b/ui', ' ', trim($name));

        return $this->normalizeKey($name);
    }

    private function normalizeKey(string $value): string
    {
        $value = mb_strtolower($value);
        $value = str_replace(['ё', '/', '\\', '-', '_', ','], ['е', ' ', ' ', ' ', ' ', '.'], $value);
        $value = preg_replace('/[^a-z0-9а-я.]+/u', ' ', $value);
        $value = preg_replace('/\s+/u', ' ', trim($value));
        $value = preg_replace('/\beps2\b/u', 'eps', $value);
        $value = preg_replace('/\b0+(\d)\b/u', '$1', $value);
        $value = preg_replace('/\br\b/u', '', $value);
        $value = preg_replace('/\s+/u', ' ', trim($value));

        return $value;
    }

    private function hasUsableLocalImage(?string $imagesJson): bool
    {
        $images = json_decode($imagesJson ?: '[]', true);
        if (! is_array($images)) {
            return false;
        }

        foreach ($images as $path) {
            if (! is_string($path) || $path === '' || str_starts_with($path, 'http')) {
                continue;
            }
            if (is_file(public_path(ltrim($path, '/')))) {
                return true;
            }
        }

        return false;
    }

    private function fetch(string $url): ?string
    {
        try {
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'Accept-Language' => 'ru-RU,ru;q=0.9',
            ])->timeout(40)->get($url);

            return $response->successful() ? $response->body() : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function downloadImage(string $url, string $path): bool
    {
        try {
            $response = Http::withHeaders(['User-Agent' => 'Mozilla/5.0'])
                ->timeout(30)
                ->get($url);

            if (! $response->successful() || strlen($response->body()) < 1024) {
                return false;
            }

            file_put_contents($path, $response->body());
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function absoluteTeplodvorUrl(string $url): string
    {
        if (str_starts_with($url, 'http')) {
            return $url;
        }
        if (str_starts_with($url, '//')) {
            return 'https:' . $url;
        }

        return 'https://www.teplodvor.by/' . ltrim($url, '/');
    }

    private function guessExt(string $url): string
    {
        $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION));

        return in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true) ? $ext : 'jpg';
    }
}
