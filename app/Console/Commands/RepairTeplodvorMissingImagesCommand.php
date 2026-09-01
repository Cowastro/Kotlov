<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RepairTeplodvorMissingImagesCommand extends Command
{
    protected $signature = 'supplier:repair-teplodvor-missing-images
        {--apply : Download images and update products}
        {--brand= : Optional local brand name filter}
        {--source-url=* : Optional Teplodvor category/brand URL; repeatable}
        {--limit=0 : Max products to update, 0 means no limit}
        {--max-pages=8 : Max Teplodvor pages per source URL}
        {--max-brands=0 : Max brands to scan, 0 means no limit}
        {--force : Update even when a local image exists}';

    protected $description = 'Repair empty or broken product images from Teplodvor catalog/brand pages.';

    private const BASE = 'https://www.teplodvor.by';
    private const DISCOVERY_URLS = [
        'https://www.teplodvor.by/shop/',
        'https://www.teplodvor.by/brands/',
    ];
    private const IMAGE_DIR = 'img/products/teplodvor-restored';

    private array $stats = [
        'brands' => 0,
        'source_urls' => 0,
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
        $apply = (bool) $this->option('apply');
        $force = (bool) $this->option('force');
        $limit = max(0, (int) $this->option('limit'));

        $this->line($apply
            ? '<fg=red;options=bold>APPLY - product images will be updated.</>'
            : '<fg=yellow;options=bold>DRY RUN - no product images will be changed.</>');

        $brands = $this->brandsToScan();
        if ($brands->isEmpty()) {
            $this->error('No matching local brands found.');
            return self::FAILURE;
        }

        $allLinks = $this->option('source-url')
            ? $this->manualLinks((array) $this->option('source-url'))
            : $this->discoverTeplodvorLinks();

        if ($allLinks === []) {
            $this->error('No Teplodvor source links discovered.');
            return self::FAILURE;
        }

        $updatedTotal = 0;
        $maxBrands = max(0, (int) $this->option('max-brands'));

        foreach ($brands as $brand) {
            if ($maxBrands > 0 && $this->stats['brands'] >= $maxBrands) {
                break;
            }
            if ($limit > 0 && $updatedTotal >= $limit) {
                break;
            }

            $products = Product::query()
                ->where('brand_id', $brand->id)
                ->where('is_archived', false)
                ->orderBy('id')
                ->get(['id', 'brand_id', 'name', 'slug', 'sku', 'images']);

            if (! $force) {
                $products = $products
                    ->filter(fn (Product $product) => ! $this->hasUsableProductImage($product))
                    ->values();
            }

            if ($products->isEmpty()) {
                continue;
            }

            $sourceUrls = $this->sourceUrlsForBrand($brand, $allLinks);
            if ($sourceUrls === []) {
                continue;
            }

            $this->newLine();
            $this->info(sprintf('%s: %d source URL(s)', $brand->name, count($sourceUrls)));

            $this->stats['brands']++;
            $this->stats['source_urls'] += count($sourceUrls);

            $items = $this->loadSourceItems($sourceUrls, (string) $brand->name);
            $this->stats['source_items'] += count($items);
            if ($items === []) {
                $this->warn('  no product cards parsed');
                continue;
            }

            foreach ($products as $product) {
                if ($limit > 0 && $updatedTotal >= $limit) {
                    break 2;
                }

                $this->stats['products']++;

                $key = $this->modelKey((string) $product->name, (string) $brand->name);
                $item = $items[$key] ?? null;

                if ($item === null) {
                    $this->stats['no_match']++;
                    continue;
                }

                $this->stats['matched']++;
                $this->line(sprintf('  #%d %s -> %s', $product->id, mb_substr($product->name, 0, 64), $item['image_url']));

                if (! $apply) {
                    $this->stats['would_update']++;
                    $updatedTotal++;
                    continue;
                }

                $saved = $this->downloadForProduct($product, $item['image_url'], (string) $brand->name);
                if ($saved === null) {
                    $this->stats['download_errors']++;
                    continue;
                }

                DB::table('products')->where('id', $product->id)->update([
                    'images' => json_encode([$saved], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'updated_at' => now(),
                ]);

                $this->stats['updated']++;
                $updatedTotal++;
            }
        }

        $this->newLine();
        $this->table(
            ['metric', 'count'],
            collect($this->stats)->map(fn ($value, $key) => [$key, $value])->values()->all()
        );

        return self::SUCCESS;
    }

    private function brandsToScan()
    {
        $query = DB::table('brands')
            ->where('is_active', true)
            ->orderBy('name')
            ->select(['id', 'name']);

        $brandFilter = trim((string) $this->option('brand'));
        if ($brandFilter !== '') {
            $query->where('name', 'like', '%' . $brandFilter . '%');
        }

        return $query->get();
    }

    private function manualLinks(array $urls): array
    {
        $links = [];
        foreach ($urls as $url) {
            $absolute = $this->absoluteUrl((string) $url);
            $links[$absolute] = [
                'url' => $absolute,
                'text' => '',
                'segments' => $this->pathSegments($absolute),
            ];
        }

        return $links;
    }

    private function discoverTeplodvorLinks(): array
    {
        $links = [];

        foreach (self::DISCOVERY_URLS as $url) {
            $html = $this->fetch($url);
            if ($html === null) {
                $this->warn('Failed to fetch ' . $url);
                continue;
            }

            preg_match_all('/<a\b[^>]*href=["\']([^"\']*\/shop\/[^"\']*)["\'][^>]*>([\s\S]*?)<\/a>/iu', $html, $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                $absolute = $this->absoluteUrl($match[1]);
                if (! str_starts_with($absolute, self::BASE . '/shop/')) {
                    continue;
                }

                $absolute = rtrim(strtok($absolute, '#') ?: $absolute, '/') . '/';
                $links[$absolute] = [
                    'url' => $absolute,
                    'text' => $this->cleanText($match[2]),
                    'segments' => $this->pathSegments($absolute),
                ];
            }
        }

        $this->info(sprintf('Discovered Teplodvor shop links: %d', count($links)));

        return $links;
    }

    private function sourceUrlsForBrand(object $brand, array $links): array
    {
        $brandName = (string) $brand->name;
        $brandText = $this->compactKey($brandName);
        $brandSlug = $this->looseSlug(Str::slug($brandName));
        $urls = [];

        foreach ($links as $link) {
            $textKey = $this->compactKey((string) $link['text']);
            $segments = array_map(fn ($segment) => $this->looseSlug($segment), $link['segments']);
            $last = end($segments) ?: '';

            $textMatch = $textKey !== '' && ($textKey === $brandText || str_contains($textKey, $brandText));
            $segmentMatch = $brandSlug !== '' && (
                $last === $brandSlug
                || str_contains($last, $brandSlug)
                || str_contains($brandSlug, $last)
                || ($last !== '' && levenshtein($last, $brandSlug) <= 2)
            );

            if ($textMatch || $segmentMatch) {
                $urls[] = $link['url'];
            }
        }

        return array_values(array_unique($urls));
    }

    private function loadSourceItems(array $sourceUrls, string $brandName): array
    {
        $items = [];
        $maxPages = max(1, (int) $this->option('max-pages'));

        foreach ($sourceUrls as $sourceUrl) {
            foreach ($this->expandPages($sourceUrl, $maxPages) as $url) {
                $html = $this->fetch($url);
                if ($html === null) {
                    continue;
                }

                foreach ($this->parseProductCards($html) as $card) {
                    if (! $this->titleLooksLikeBrand($card['title'], $brandName)) {
                        continue;
                    }

                    $key = $this->modelKey($card['title'], $brandName);
                    if ($key === '') {
                        continue;
                    }

                    $items[$key] ??= $card;
                }
            }
        }

        return $items;
    }

    private function expandPages(string $sourceUrl, int $maxPages): array
    {
        $urls = [rtrim($sourceUrl, '/') . '/'];
        $firstHtml = $this->fetch($sourceUrl);
        $pageMax = 1;

        if ($firstHtml !== null && preg_match_all('/\/page(\d+)\/["\']/iu', $firstHtml, $matches)) {
            $pageMax = max(array_map('intval', $matches[1]));
        }

        $pageMax = min(max(1, $pageMax), $maxPages);
        for ($page = 2; $page <= $pageMax; $page++) {
            $urls[] = rtrim($sourceUrl, '/') . '/page' . $page . '/';
        }

        return array_values(array_unique($urls));
    }

    private function parseProductCards(string $html): array
    {
        $cards = [];

        preg_match_all(
            '/<img\b[^>]*src=["\']([^"\']+)["\'][^>]*>[\s\S]{0,1600}?<a\b[^>]*href=["\']([^"\']+)["\'][^>]*class=["\'][^"\']*shop-item-link[^"\']*["\'][^>]*>([\s\S]*?)<\/a>/iu',
            $html,
            $matches,
            PREG_SET_ORDER
        );

        foreach ($matches as $match) {
            $title = $this->cleanText($match[3]);
            $imageUrl = $this->largeImageUrl($match[1]);
            if ($title === '' || $imageUrl === '') {
                continue;
            }

            $cards[] = [
                'title' => $title,
                'image_url' => $imageUrl,
                'product_url' => $this->absoluteUrl($match[2]),
            ];
        }

        return $cards;
    }

    private function titleLooksLikeBrand(string $title, string $brandName): bool
    {
        $titleKey = $this->compactKey($title);
        $brandKey = $this->compactKey($brandName);

        return $brandKey !== '' && str_contains($titleKey, $brandKey);
    }

    private function modelKey(string $productName, string $brandName): string
    {
        if ($this->compactKey($brandName) === 'kermi') {
            $key = $this->kermiModelKey($productName);
            if ($key !== '') {
                return $key;
            }
        }

        $name = html_entity_decode(strip_tags($productName), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $name = preg_replace('/\b' . preg_quote($brandName, '/') . '\b/ui', '', $name) ?? $name;
        $name = preg_replace('/\([^)]*\)/u', '', $name) ?? $name;
        $name = preg_replace('/\b(водонагреватель|кот[её]л|радиатор|конвектор|насос|кондиционер|обогреватель|электрический|газовый|накопительный|настенный|напольный|проточный|твердотопливный|печь|камин|дымоход)\b/ui', ' ', trim($name)) ?? $name;

        return $this->normalizeKey($name);
    }

    private function kermiModelKey(string $productName): string
    {
        $name = mb_strtolower(html_entity_decode(strip_tags($productName), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $name = str_replace(['ё', '×', 'х', 'x', '/', '\\', '-', '_', ','], ['е', 'x', 'x', 'x', ' ', ' ', ' ', ' ', '.'], $name);

        $line = match (true) {
            str_contains($name, 'ventil'),
            str_contains($name, 'profil ventil'),
            preg_match('/\bf[kt]v\b/u', $name) === 1,
            preg_match('/\bf[kt]v[0-9]/u', $name) === 1 => 'ventil',
            str_contains($name, 'kompakt'),
            str_contains($name, 'profil kompakt'),
            preg_match('/\bfko\b/u', $name) === 1,
            preg_match('/\bfko[0-9]/u', $name) === 1 => 'kompakt',
            default => '',
        };

        $type = null;
        $height = null;
        $length = null;

        if (preg_match('/\bf(?<line>ko|[kt]v)\s*(?<type>10|11|12|20|21|22|30|33)(?<height>0[234569]0)(?<length>0[4-9]0|1[0-9]0|2[0-9]0|300)/u', $name, $match)) {
            $line = $line !== '' ? $line : ($match['line'] === 'ko' ? 'kompakt' : 'ventil');
            $type = $match['type'];
            $height = (string) ((int) $match['height'] * 10);
            $length = (string) ((int) $match['length'] * 10);
        } elseif (preg_match('/\b(10|11|12|20|21|22|30|33)\s*[x\s]+([23456]00|900)\s*[x\s]+([4-9]00|1[0-9]00|2[0-9]00|3000)\b/u', $name, $match)) {
            $type = $match[1];
            $height = $match[2];
            $length = $match[3];
        } elseif (preg_match('/\b(10|11|12|20|21|22|30|33)([23456]00|900)([4-9]00|1[0-9]00|2[0-9]00|3000)\b/u', $name, $match)) {
            $type = $match[1];
            $height = $match[2];
            $length = $match[3];
        }

        if ($type === null || $height === null || $length === null) {
            return '';
        }

        return trim(implode(' ', array_filter([$line, $type, (string) (int) $height, (string) (int) $length])));
    }

    private function normalizeKey(string $value): string
    {
        $value = mb_strtolower($value);
        $value = str_replace(['ё', '/', '\\', '-', '_', ','], ['е', ' ', ' ', ' ', ' ', '.'], $value);
        $value = preg_replace('/[^a-z0-9а-я.]+/u', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?? $value;
        $value = preg_replace('/\b0+(\d)\b/u', '$1', $value) ?? $value;
        $value = preg_replace('/\br\b/u', '', $value) ?? $value;

        return preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);
    }

    private function hasUsableProductImage(Product $product): bool
    {
        $images = $product->images;
        if (is_string($images)) {
            $images = json_decode($images, true);
        }
        if (! is_array($images) || $images === []) {
            return false;
        }

        $raw = trim((string) ($images[0] ?? ''));
        if ($raw === '' || str_contains($raw, 'product-placeholder')) {
            return false;
        }

        if (str_starts_with($raw, 'http://') || str_starts_with($raw, 'https://')) {
            return true;
        }

        $raw = ltrim($raw, '/');
        if (str_starts_with($raw, 'img/')) {
            return file_exists(public_path($raw));
        }
        if (str_starts_with($raw, 'products/')) {
            return Storage::disk('public')->exists($raw);
        }
        if (str_starts_with($raw, 'product/')) {
            return file_exists(public_path('images/' . $raw));
        }
        if (substr_count($raw, '/') >= 2) {
            return file_exists(public_path('images/product/' . $raw));
        }

        $skuPath = $this->legacySkuPath($product, $raw);
        if ($skuPath !== null && file_exists(public_path('images/' . $skuPath))) {
            return true;
        }

        return file_exists(public_path('images/' . $this->legacyIdPath($product, $raw)));
    }

    private function downloadForProduct(Product $product, string $imageUrl, string $brandName): ?string
    {
        try {
            $response = Http::withHeaders(['User-Agent' => 'Mozilla/5.0'])
                ->timeout(35)
                ->withOptions(['verify' => false])
                ->get($imageUrl);

            $body = $response->body();
            if (! $response->successful() || strlen($body) < 1024) {
                return null;
            }

            $size = @getimagesizefromstring($body);
            if ($size === false || $size[0] < 120 || $size[1] < 120) {
                return null;
            }

            $ext = match ($size['mime'] ?? '') {
                'image/png' => 'png',
                'image/webp' => 'webp',
                default => 'jpg',
            };
            $dir = self::IMAGE_DIR . '/' . Str::slug($brandName);
            if (! is_dir(public_path($dir))) {
                mkdir(public_path($dir), 0755, true);
            }

            $file = Str::slug($product->slug ?: $product->name) . '.' . $ext;
            file_put_contents(public_path($dir . '/' . $file), $body);

            return $dir . '/' . $file;
        } catch (\Throwable) {
            return null;
        }
    }

    private function fetch(string $url): ?string
    {
        try {
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'Accept-Language' => 'ru-RU,ru;q=0.9',
                'Referer' => self::BASE . '/',
            ])->timeout(40)->withOptions(['verify' => false])->get($this->absoluteUrl($url));

            return $response->successful() && strlen($response->body()) > 500 ? $response->body() : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function absoluteUrl(string $url): string
    {
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }
        if (str_starts_with($url, '//')) {
            return 'https:' . $url;
        }

        return self::BASE . '/' . ltrim($url, '/');
    }

    private function largeImageUrl(string $url): string
    {
        $url = $this->absoluteUrl($url);

        return str_replace('/userfls/shop/small/', '/userfls/shop/large/', $url);
    }

    private function cleanText(string $value): string
    {
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    private function compactKey(string $value): string
    {
        $value = mb_strtolower($value);
        $value = str_replace('ё', 'е', $value);

        return preg_replace('/[^a-z0-9а-я]+/u', '', $value) ?? '';
    }

    private function looseSlug(string $value): string
    {
        $value = strtolower($value);
        $value = str_replace(['j', 'y'], 'i', $value);

        return preg_replace('/[^a-z0-9]+/', '', $value) ?? '';
    }

    private function pathSegments(string $url): array
    {
        $path = parse_url($url, PHP_URL_PATH) ?: '';

        return array_values(array_filter(explode('/', trim($path, '/'))));
    }

    private function legacySkuPath(Product $product, string $file): ?string
    {
        $skuParts = explode('.', (string) $product->sku);
        $firstRaw = explode('-', $skuParts[0] ?? '')[1] ?? null;
        $secondRaw = $skuParts[1] ?? null;

        if ($firstRaw === null || $secondRaw === null || ! is_numeric($firstRaw) || ! is_numeric($secondRaw)) {
            return null;
        }

        $n1 = (int) $firstRaw;
        $dir1 = sprintf('00%d', $n1);
        $dir2 = sprintf('%s%03d', str_pad((string) $n1, 3, '0', STR_PAD_LEFT), (int) $secondRaw);

        return 'product/' . $dir1 . '/' . $dir2 . '/' . $file;
    }

    private function legacyIdPath(Product $product, string $file): string
    {
        $n1 = (int) floor(((int) $product->id) / 1000);
        $dir1 = sprintf('00%d', $n1);
        $dir2 = str_pad((string) $product->id, 6, '0', STR_PAD_LEFT);

        return 'product/' . $dir1 . '/' . $dir2 . '/' . $file;
    }
}
