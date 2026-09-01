<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class RepairOfficialCatalogImagesCommand extends Command
{
    protected $signature = 'catalog:repair-official-catalog-images
        {--brand= : Brand name to repair}
        {--source-url=* : Official catalog URL to crawl}
        {--apply : Download images and update existing products}
        {--force : Process products even when their first image is usable}
        {--limit=0 : Max products to process, 0 means no limit}
        {--max-pages=20 : Max paginated pages to crawl per source URL}
        {--sleep=400 : Delay between HTTP requests, ms}
        {--min-score=0.74 : Minimum match score}
        {--debug : Show skipped low-confidence matches}';

    protected $description = 'Restore broken product images from official catalog pages by matching existing products only.';

    private const IMAGE_DIR = 'img/products/official';

    private const STOPWORDS = [
        'ermak', 'ермак', 'pech', 'pechi', 'печь', 'печи', 'dlya', 'для', 'bani', 'бани',
        'drovyanye', 'drovyanaya', 'drovyanoy', 'дровяная', 'дровяной', 'na', 'на',
        'kupit', 'купить', 'catalog', 'katalog', 'каталог', 'tovar', 'товар',
        'god', 'года', 'seriya', 'серия',
        'smolcom', 'смолком', 'portal', 'портал', 'elektrokamin', 'электрокамин',
        'kaminokomplekt', 'каминокомплект', 'uglovoj', 'uglovoy', 'угловой',
    ];

    /** @var array<int, array{title:string,url:string,image:string,slug:string,tokens:array<int,string>}> */
    private array $sourceItems = [];

    private array $stats = [
        'source_items' => 0,
        'products' => 0,
        'already_ok' => 0,
        'matched' => 0,
        'would_update' => 0,
        'updated' => 0,
        'no_match' => 0,
        'ambiguous' => 0,
        'download_errors' => 0,
    ];

    public function handle(): int
    {
        $brandFilter = trim((string) $this->option('brand'));
        $sourceUrls = array_values(array_filter((array) $this->option('source-url')));

        if ($brandFilter === '' || $sourceUrls === []) {
            $this->error('--brand and at least one --source-url are required.');

            return self::FAILURE;
        }

        $brand = DB::table('brands')
            ->where('is_active', true)
            ->whereRaw('LOWER(name) LIKE ?', ['%' . mb_strtolower($brandFilter) . '%'])
            ->orderByRaw('CASE WHEN name = ? THEN 0 ELSE 1 END', [$brandFilter])
            ->first(['id', 'name']);

        if (! $brand) {
            $this->error('Brand not found: ' . $brandFilter);

            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');
        $force = (bool) $this->option('force');
        $limit = max(0, (int) $this->option('limit'));
        $sleep = max(100, (int) $this->option('sleep'));
        $minScore = max(0.1, min(1.0, (float) $this->option('min-score')));

        $this->line($apply
            ? '<fg=red;options=bold>APPLY: product images will be updated from official catalog.</>'
            : '<fg=yellow;options=bold>DRY RUN: no product images will be changed.</>');

        $this->sourceItems = $this->crawlSourceItems($sourceUrls, $brand->name);
        $this->stats['source_items'] = count($this->sourceItems);
        $this->info(sprintf('Official source items: %d', count($this->sourceItems)));

        if ($this->sourceItems === []) {
            $this->error('No source items found.');

            return self::FAILURE;
        }

        $query = Product::query()
            ->where('brand_id', (int) $brand->id)
            ->where('is_archived', false)
            ->where('is_active', true)
            ->where('price', '>', 0)
            ->orderBy('id');

        if ($limit > 0) {
            $query->limit($limit);
        }

        $products = $query->get(['id', 'name', 'slug', 'sku', 'images']);
        $this->stats['products'] = $products->count();
        $this->info(sprintf('%s products to check: %d', $brand->name, $products->count()));

        $targetDir = self::IMAGE_DIR . '/' . Str::slug($brand->name);
        if ($apply) {
            File::ensureDirectoryExists(public_path($targetDir));
        }

        foreach ($products as $product) {
            if (! $force && $this->hasUsableLocalImage($product)) {
                $this->stats['already_ok']++;
                continue;
            }

            $matches = $this->rankMatches($product, (string) $brand->name);
            $best = $matches[0] ?? null;
            $second = $matches[1] ?? null;

            if (! $best || $best['score'] < $minScore) {
                $this->stats['no_match']++;
                if ((bool) $this->option('debug')) {
                    $this->warn(sprintf(
                        '#%d no match %.2f %s',
                        $product->id,
                        (float) ($best['score'] ?? 0),
                        $product->name
                    ));
                }
                continue;
            }

            if ($second && ($best['score'] - $second['score']) < 0.07 && $second['score'] >= $minScore) {
                $this->stats['ambiguous']++;
                $this->warn(sprintf(
                    '#%d ambiguous: %s => %s (%.2f) / %s (%.2f)',
                    $product->id,
                    $product->name,
                    $best['item']['title'],
                    $best['score'],
                    $second['item']['title'],
                    $second['score']
                ));
                continue;
            }

            $this->stats['matched']++;
            $item = $best['item'];
            $localPath = $targetDir . '/' . $product->id . '-' . Str::slug($product->slug ?: $product->name) . '.' . $this->guessExt($item['image']);

            $this->line(sprintf(
                '#%d %s -> %s (score %.2f)',
                $product->id,
                mb_strimwidth($product->name, 0, 68, '...'),
                $item['image'],
                $best['score']
            ));

            if (! $apply) {
                $this->stats['would_update']++;
                continue;
            }

            $body = $this->downloadImage($item['image'], $item['url']);
            if ($body === null) {
                $this->stats['download_errors']++;
                $this->warn('  download failed');
                continue;
            }

            File::put(public_path($localPath), $body);
            $product->images = [$localPath];
            $product->save();

            $this->stats['updated']++;
            usleep($sleep * 1000);
        }

        $this->newLine();
        $this->table(['metric', 'count'], collect($this->stats)->map(fn ($v, $k) => [$k, $v])->values()->all());

        return $this->stats['download_errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array<int, array{title:string,url:string,image:string,slug:string,tokens:array<int,string>}>
     */
    private function crawlSourceItems(array $sourceUrls, string $brandName): array
    {
        $items = [];
        $seen = [];
        $maxPages = max(1, (int) $this->option('max-pages'));
        $sleep = max(100, (int) $this->option('sleep'));

        foreach ($sourceUrls as $sourceUrl) {
            for ($page = 1; $page <= $maxPages; $page++) {
                $url = $this->pageUrl((string) $sourceUrl, $page);
                $html = $this->fetch($url);
                if ($html === null) {
                    if ($page === 1) {
                        $this->warn('Fetch failed: ' . $url);
                    }
                    break;
                }

                $pageItems = $this->parseCatalogItems($html, $url, $brandName);
                if ($pageItems === []) {
                    break;
                }

                $newItems = 0;
                foreach ($pageItems as $item) {
                    $key = $item['url'];
                    if (isset($seen[$key])) {
                        continue;
                    }
                    $seen[$key] = true;
                    $items[] = $item;
                    $newItems++;
                }

                if ($newItems === 0) {
                    break;
                }

                usleep($sleep * 1000);
            }
        }

        return $items;
    }

    /**
     * @return array<int, array{title:string,url:string,image:string,slug:string,tokens:array<int,string>}>
     */
    private function parseCatalogItems(string $html, string $pageUrl, string $brandName): array
    {
        $items = [];
        if (! preg_match_all('#<article\b[^>]*class=["\'][^"\']*catalog-item[^"\']*["\'][^>]*>(.*?)</article>#siu', $html, $cards)) {
            return $this->parseStenbelCatalogItems($html, $pageUrl, $brandName)
                ?: $this->parseAsproCatalogItems($html, $pageUrl, $brandName);
        }

        foreach ($cards[1] as $card) {
            $title = '';
            if (preg_match('#<span\b[^>]*class=["\'][^"\']*title[^"\']*["\'][^>]*>(.*?)</span>#siu', $card, $titleMatch)) {
                $title = trim(html_entity_decode(strip_tags($titleMatch[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            } elseif (preg_match('#<a\b[^>]*title=["\']([^"\']+)["\']#iu', $card, $titleMatch)) {
                $title = trim(html_entity_decode($titleMatch[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            }

            if ($title === '') {
                continue;
            }

            $url = '';
            if (preg_match('#<a\b[^>]*href=["\']([^"\']*/catalog/[^"\']+)["\']#iu', $card, $urlMatch)) {
                $url = $this->absoluteUrl($urlMatch[1], $pageUrl);
                $url = strtok($url, '?') ?: $url;
            }

            $image = $this->bestCardImage($card, $pageUrl);

            if ($url === '' || $image === '' || $this->isIconImage($image)) {
                continue;
            }

            $items[] = [
                'title' => $title,
                'url' => $url,
                'image' => $image,
                'slug' => $this->sourceSlug($url),
                'tokens' => $this->tokens($title . ' ' . $this->sourceSlug($url), $brandName),
            ];
        }

        return $items;
    }

    /**
     * @return array<int, array{title:string,url:string,image:string,slug:string,tokens:array<int,string>}>
     */
    private function parseStenbelCatalogItems(string $html, string $pageUrl, string $brandName): array
    {
        $items = [];
        $seen = [];

        if (! preg_match_all('#<article\b[^>]*class=["\'][^"\']*\bcard\b[^"\']*["\'][^>]*>(.*?)</article>#siu', $html, $cards)) {
            return [];
        }

        foreach ($cards[1] as $card) {
            if (! preg_match('#<a\b[^>]*class=["\'][^"\']*\bcard__t\b[^"\']*["\'][^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)</a>#siu', $card, $titleMatch)
                && ! preg_match('#<a\b[^>]*href=["\']([^"\']+)["\'][^>]*class=["\'][^"\']*\bcard__t\b[^"\']*["\'][^>]*>(.*?)</a>#siu', $card, $titleMatch)) {
                continue;
            }

            $url = strtok($this->absoluteUrl($titleMatch[1], $pageUrl), '?') ?: '';
            $title = trim(html_entity_decode(strip_tags($titleMatch[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

            if ($url === '' || $title === '' || isset($seen[$url])) {
                continue;
            }

            $image = '';
            if (preg_match('#<a\b[^>]*class=["\'][^"\']*\bcard__img\b[^"\']*["\'][^>]*>.*?</a>#siu', $card, $imageBlock)) {
                $image = $this->bestCardImage($imageBlock[0], $pageUrl);
            }
            if ($image === '') {
                $image = $this->bestCardImage($card, $pageUrl);
            }

            if ($image === '' || $this->isIconImage($image)) {
                continue;
            }

            $seen[$url] = true;
            $items[] = [
                'title' => $title,
                'url' => $url,
                'image' => $image,
                'slug' => $this->sourceSlug($url),
                'tokens' => $this->tokens($title . ' ' . $this->sourceSlug($url), $brandName),
            ];
        }

        return $items;
    }

    /**
     * @return array<int, array{title:string,url:string,image:string,slug:string,tokens:array<int,string>}>
     */
    private function parseAsproCatalogItems(string $html, string $pageUrl, string $brandName): array
    {
        $items = [];
        $seen = [];
        if (! preg_match_all('#<img\b[^>]+(?:src|data-src)=["\']([^"\']+\.(?:jpe?g|png|webp))["\'][^>]*>#iu', $html, $images, PREG_OFFSET_CAPTURE)) {
            return [];
        }

        foreach ($images[0] as $index => $tagMatch) {
            $tag = $tagMatch[0];
            $offset = $tagMatch[1];
            $image = $this->absoluteUrl($images[1][$index][0], $pageUrl);
            if ($this->isIconImage($image)) {
                continue;
            }

            $title = '';
            if (preg_match('#\b(?:alt|title)=["\']([^"\']{3,160})["\']#iu', $tag, $titleMatch)) {
                $title = trim(html_entity_decode($titleMatch[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            }
            if ($title === '' || preg_match('/^(?:подробнее|сравнить|отложить)$/iu', $title)) {
                continue;
            }

            $aroundStart = max(0, $offset - 2500);
            $around = substr($html, $aroundStart, 4500);
            if (! preg_match('#href=["\']([^"\']*/catalog/[^"\']+/\d+/)["\']#iu', $around, $urlMatch)) {
                continue;
            }

            $url = $this->absoluteUrl($urlMatch[1], $pageUrl);
            if (isset($seen[$url])) {
                continue;
            }
            $seen[$url] = true;

            $items[] = [
                'title' => $title,
                'url' => $url,
                'image' => $image,
                'slug' => $this->sourceSlug($url),
                'tokens' => $this->tokens($title . ' ' . $this->sourceSlug($url), $brandName),
            ];
        }

        return $items;
    }

    private function bestCardImage(string $card, string $pageUrl): string
    {
        $candidates = [];
        if (! preg_match_all('#<img\b[^>]+>#iu', $card, $images)) {
            return '';
        }

        foreach ($images[0] as $imgTag) {
            if (! preg_match('#\b(?:src|data-src)=["\']([^"\']+\.(?:jpe?g|png|webp))["\']#iu', $imgTag, $match)) {
                continue;
            }

            $url = $this->absoluteUrl($match[1], $pageUrl);
            if ($this->isIconImage($url)) {
                continue;
            }

            $score = 0;
            if (str_contains($imgTag, 'itemprop="image"') || str_contains($imgTag, "itemprop='image'")) {
                $score += 4;
            }
            if (str_contains($url, '/upload/resize_cache/iblock/') || str_contains($url, '/upload/iblock/')) {
                $score += 2;
            }
            if (preg_match('#/(?:240_250|300_|400_|500_|600_)#', $url)) {
                $score += 2;
            }
            if (str_contains($imgTag, 'hidden_visually')) {
                $score -= 2;
            }

            $candidates[] = ['url' => $url, 'score' => $score];
        }

        if ($candidates === []) {
            return '';
        }

        usort($candidates, fn ($a, $b) => $b['score'] <=> $a['score']);

        return $candidates[0]['url'];
    }

    /**
     * @return array<int, array{item:array,score:float}>
     */
    private function rankMatches(Product $product, string $brandName): array
    {
        $productTokens = $this->tokens($product->name . ' ' . $product->slug, $brandName);
        $productKey = implode(' ', $productTokens);
        $matches = [];

        foreach ($this->sourceItems as $item) {
            $sourceKey = implode(' ', $item['tokens']);
            $overlap = $this->overlapScore($productTokens, $item['tokens']);
            $similarity = 0.0;

            if ($productKey !== '' && $sourceKey !== '') {
                similar_text($productKey, $sourceKey, $percent);
                $similarity = $percent / 100;
            }

            $containsBoost = ($productKey !== '' && $sourceKey !== '' && (str_contains($productKey, $sourceKey) || str_contains($sourceKey, $productKey))) ? 0.12 : 0.0;
            $score = min(1.0, max($overlap, $similarity) + $containsBoost);

            $matches[] = ['item' => $item, 'score' => $score];
        }

        usort($matches, fn ($a, $b) => $b['score'] <=> $a['score']);

        return $matches;
    }

    private function overlapScore(array $left, array $right): float
    {
        $left = array_values(array_unique($left));
        $right = array_values(array_unique($right));
        if ($left === [] || $right === []) {
            return 0.0;
        }

        $intersection = count(array_intersect($left, $right));
        $precision = $intersection / count($left);
        $recall = $intersection / count($right);

        return ($precision * 0.65) + ($recall * 0.35);
    }

    /**
     * @return array<int, string>
     */
    private function tokens(string $value, string $brandName): array
    {
        $slug = Str::slug($value, ' ');
        $brandSlug = Str::slug($brandName, ' ');
        $slug = preg_replace('/\b' . preg_quote($brandSlug, '/') . '\b/u', ' ', $slug) ?? $slug;
        $slug = preg_replace('/\b20(?:2[0-9]|3[0-9])\b/u', ' ', $slug) ?? $slug;
        $slug = str_replace(['aisi ', 'inox ', 'aisi-', 'inox-'], ' ', $slug);
        $slug = preg_replace('/\b0+(\d)\b/u', '$1', $slug) ?? $slug;
        $slug = str_replace(['std asp', 'std-asp'], 'std asp', $slug);

        $tokens = preg_split('/\s+/u', trim($slug)) ?: [];
        $tokens = array_values(array_filter($tokens, function (string $token): bool {
            if (mb_strlen($token) < 2) {
                return false;
            }

            return ! in_array($token, self::STOPWORDS, true);
        }));

        return array_values(array_unique($tokens));
    }

    private function hasUsableLocalImage(Product $product): bool
    {
        $images = $product->images;
        if (! is_array($images) || $images === [] || trim((string) ($images[0] ?? '')) === '') {
            return false;
        }

        $raw = ltrim((string) $images[0], '/');
        if (str_starts_with($raw, 'http://') || str_starts_with($raw, 'https://')) {
            return true;
        }
        if (str_starts_with($raw, 'img/')) {
            return is_file(public_path($raw));
        }
        if (str_starts_with($raw, 'products/')) {
            return is_file(storage_path('app/public/' . $raw));
        }
        if (str_starts_with($raw, 'product/')) {
            return is_file(public_path('images/' . $raw));
        }
        if (substr_count($raw, '/') >= 2) {
            return is_file(public_path('images/product/' . $raw));
        }

        return false;
    }

    private function fetch(string $url): ?string
    {
        try {
            $response = Http::timeout(35)
                ->retry(2, 500)
                ->withHeaders($this->headers($url))
                ->get($url);

            return $response->successful() && strlen($response->body()) > 500 ? $response->body() : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function downloadImage(string $url, string $referer): ?string
    {
        try {
            $response = Http::timeout(30)
                ->retry(2, 500)
                ->withHeaders($this->headers($referer, true))
                ->get($url);

            if (! $response->successful()) {
                return null;
            }

            $body = $response->body();
            $size = @getimagesizefromstring($body);

            if ($size === false || $size[0] < 120 || $size[1] < 120) {
                return null;
            }

            return $body;
        } catch (\Throwable) {
            return null;
        }
    }

    private function headers(string $url, bool $image = false): array
    {
        $origin = parse_url($url, PHP_URL_SCHEME) . '://' . parse_url($url, PHP_URL_HOST) . '/';

        return [
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            'Accept' => $image ? 'image/avif,image/webp,image/apng,image/*,*/*;q=0.8' : 'text/html,application/xhtml+xml,*/*;q=0.9',
            'Accept-Language' => 'ru-RU,ru;q=0.9,en;q=0.8',
            'Referer' => $origin,
        ];
    }

    private function pageUrl(string $url, int $page): string
    {
        if ($page <= 1) {
            return $url;
        }

        $parts = parse_url($url);
        parse_str($parts['query'] ?? '', $query);
        $query['PAGEN_1'] = $page;

        $base = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '') . ($parts['path'] ?? '/');

        return $base . '?' . http_build_query($query);
    }

    private function absoluteUrl(string $url, string $baseUrl): string
    {
        $url = html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }
        if (str_starts_with($url, '//')) {
            return 'https:' . $url;
        }

        $parts = parse_url($baseUrl);
        $origin = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '');

        return $origin . '/' . ltrim($url, '/');
    }

    private function sourceSlug(string $url): string
    {
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
        $parts = explode('/', $path);

        return (string) end($parts);
    }

    private function isIconImage(string $url): bool
    {
        return preg_match('#/(?:icons?|logo|cart|watch|options?)/|(?:logo|icon|cart|drova|gaz|placeholder)\.(?:png|svg|jpe?g|webp)$#iu', $url) === 1;
    }

    private function guessExt(string $url): string
    {
        $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION));

        return in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true) ? $ext : 'jpg';
    }
}
