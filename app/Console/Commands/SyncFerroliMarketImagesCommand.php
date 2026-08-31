<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SyncFerroliMarketImagesCommand extends Command
{
    protected $signature = 'supplier:sync-ferroli-market-images
        {--apply : Download images and update products}
        {--force : Update even when an existing local image file is present}
        {--limit=0 : Max products to update/check, 0 means no limit}
        {--offset=0 : Skip products after sorting by id}
        {--category-url=* : Ferroli market category URL to scan}';

    protected $description = 'Restore Ferroli product photos from market.ferroli.ru by exact model-title matching.';

    private const DEFAULT_CATEGORY_URLS = [
        'https://market.ferroli.ru/catalogs/market?category=146',
    ];

    private const IMAGE_DIR = 'img/products/manufacturer/ferroli';

    private array $stats = [
        'market_items' => 0,
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
        $apply = (bool) $this->option('apply');
        $force = (bool) $this->option('force');

        if (! $apply) {
            $this->warn('DRY RUN - no product images will be changed.');
        }

        $marketItems = $this->loadMarketItems();
        $this->stats['market_items'] = count($marketItems);
        $this->info('Market image items: ' . count($marketItems));

        if (empty($marketItems)) {
            $this->error('No Ferroli market items parsed.');
            return self::FAILURE;
        }

        $brand = DB::table('brands')
            ->whereRaw('LOWER(name) = ?', ['ferroli'])
            ->first(['id', 'name']);

        if (! $brand) {
            $this->error('Brand Ferroli not found.');
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
        $this->info('Ferroli products to check: ' . $products->count());

        if ($apply && ! is_dir(public_path(self::IMAGE_DIR))) {
            mkdir(public_path(self::IMAGE_DIR), 0755, true);
        }

        foreach ($products as $product) {
            $key = $this->modelKey($product->name);
            $item = $marketItems[$key] ?? null;

            if (! $item) {
                $this->stats['no_match']++;
                continue;
            }

            $this->stats['matched']++;

            if (! $force && $this->hasUsableLocalImage($product->images)) {
                $this->stats['already_ok']++;
                continue;
            }

            $this->line(sprintf(
                '#%d %s -> %s',
                $product->id,
                $product->name,
                $item['image_url']
            ));

            $localPath = self::IMAGE_DIR . '/' . Str::slug($product->slug ?: $product->name) . '.' . $this->guessExt($item['image_url']);

            if (! $apply) {
                $this->stats['would_update']++;
                continue;
            }

            if (! $this->downloadImage($item['image_url'], public_path($localPath))) {
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

    private function loadMarketItems(): array
    {
        $items = [];
        $urls = $this->option('category-url') ?: self::DEFAULT_CATEGORY_URLS;

        foreach ($urls as $url) {
            $html = $this->fetch($url);
            if ($html === null) {
                $this->warn('Failed to fetch ' . $url);
                continue;
            }

            if (! preg_match_all('/<div[^>]+class=["\'][^"\']*product-modal[^"\']*["\'][^>]*>.*?<\/form>\s*<\/div>/si', $html, $modals)) {
                continue;
            }

            foreach ($modals[0] as $modalHtml) {
                if (! preg_match('/<h2[^>]+class=["\'][^"\']*product-modal__title[^"\']*["\'][^>]*>(.*?)<\/h2>/si', $modalHtml, $titleMatch)) {
                    continue;
                }
                if (! preg_match('/<div[^>]+class=["\'][^"\']*product-modal__media[^"\']*["\'][^>]*>.*?<img[^>]+src=["\']([^"\']+)["\']/si', $modalHtml, $imgMatch)) {
                    continue;
                }

                $title = trim(html_entity_decode(strip_tags($titleMatch[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                $imageUrl = html_entity_decode($imgMatch[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');

                if ($title === '' || ! str_starts_with($imageUrl, 'http')) {
                    continue;
                }

                $items[$this->normalizeKey($title)] = [
                    'title' => $title,
                    'image_url' => $imageUrl,
                ];
            }
        }

        return $items;
    }

    private function modelKey(string $productName): string
    {
        $name = preg_replace('/^(водонагреватель|кот[её]л|радиатор|конвектор|насос|кондиционер|обогреватель|электрический|газовый|накопительный|настенный|напольный|конденсационный|твердотопливный|чугунный)\s+/ui', '', trim($productName));
        $name = preg_replace('/\bferroli\b/ui', '', $name);
        $name = preg_replace('/\([^)]*\)/u', '', $name);
        $name = str_replace(['котел', 'котёл'], '', $name);

        return $this->normalizeKey($name);
    }

    private function normalizeKey(string $value): string
    {
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = mb_strtolower($value);
        $value = str_replace(['ё', '/', '\\', '-', '_'], ['е', ' ', ' ', ' ', ' '], $value);
        $value = preg_replace('/[^a-z0-9а-я]+/u', ' ', $value);
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

    private function guessExt(string $url): string
    {
        $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION));

        return in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true) ? $ext : 'jpg';
    }
}
