<?php

namespace App\Console\Commands;

use App\Services\AiContentEnricher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class EnrichBaniaPriceListProductsCommand extends Command
{
    protected $signature = 'supplier:enrich-bania-price-products
        {--dry-run : Preview without database writes}
        {--apply : Update products and supplier_products}
        {--limit=50 : Max products to process}
        {--offset=0 : Skip first N products}
        {--sku= : Process one product SKU}
        {--category= : Filter by category id}
        {--force-images : Replace existing images}
        {--force-content : Replace existing content}
        {--skip-images : Do not download images}
        {--skip-content : Do not update content}
        {--sleep=300 : Delay between products in milliseconds}';

    protected $description = 'Enrich BANIA products created from price-list rows by finding source pages via Serper.';

    private const SUPPLIER_CODE = 'bania';
    private const IMAGE_DIR = 'img/products/bania';
    private const SERPER_URL = 'https://google.serper.dev/search';

    private AiContentEnricher $ai;

    private array $stats = [
        'processed' => 0,
        'matched_page' => 0,
        'images_updated' => 0,
        'images_existing' => 0,
        'images_missing' => 0,
        'content_updated' => 0,
        'content_existing' => 0,
        'content_missing' => 0,
        'source_url_updated' => 0,
        'errors' => 0,
    ];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $dryRun = (bool) $this->option('dry-run') || ! $apply;
        $apiKey = (string) env('SERPER_API_KEY', '');

        $this->line($dryRun
            ? '<fg=yellow;options=bold>DRY RUN: database will not be changed.</>'
            : '<fg=red;options=bold>APPLY: products can be updated.</>');

        if ($apiKey === '') {
            $this->error('SERPER_API_KEY is not configured in .env.');
            return self::FAILURE;
        }

        $this->ai = new AiContentEnricher();
        if (! $this->option('skip-content') && ! $this->ai->isAvailable()) {
            $this->warn('AI provider is not configured; descriptions will use scraped text only.');
        }

        $products = $this->productsToProcess();
        $this->info('Products to enrich: ' . $products->count());

        foreach ($products as $product) {
            $this->stats['processed']++;
            $this->line(sprintf('[%d/%d] %s %s', $this->stats['processed'], $products->count(), $product->sku, Str::limit($product->name, 90)));

            try {
                $result = $this->findSourcePage($apiKey, $product);
                if (! $result) {
                    $this->warn('  no source page found');
                    $this->stats['images_missing']++;
                    $this->stats['content_missing']++;
                    $this->sleep();
                    continue;
                }

                $this->stats['matched_page']++;
                $this->line('  source: ' . $result['url']);

                $updates = ['updated_at' => now()];
                $supplierUpdates = [
                    'source_url' => $result['url'],
                    'updated_at' => now(),
                ];

                $raw = json_decode((string) ($product->supplier_raw ?? ''), true);
                if (! is_array($raw)) {
                    $raw = [];
                }
                $raw['enrichment'] = [
                    'provider' => 'serper',
                    'source_url' => $result['url'],
                    'matched_at' => now()->toDateTimeString(),
                ];
                unset($raw['needs_enrichment']);
                $supplierUpdates['raw'] = json_encode($raw, JSON_UNESCAPED_UNICODE);

                $existingImages = $this->decodeArray($product->images);
                if ($this->option('skip-images')) {
                    // no-op
                } elseif ($existingImages !== [] && ! $this->option('force-images')) {
                    $this->stats['images_existing']++;
                } else {
                    $images = $this->downloadImages($result['images'], $product);
                    if ($images !== []) {
                        $updates['images'] = json_encode($images, JSON_UNESCAPED_UNICODE);
                        $this->stats['images_updated']++;
                    } else {
                        $this->stats['images_missing']++;
                    }
                }

                $hasContent = trim(strip_tags((string) $product->content)) !== '';
                if ($this->option('skip-content')) {
                    // no-op
                } elseif ($hasContent && ! $this->option('force-content') && ! $this->looksLikePriceListContent((string) $product->content)) {
                    $this->stats['content_existing']++;
                } else {
                    $content = $this->buildContent($product, $result);
                    if ($content !== '') {
                        $updates['content'] = $content;
                        $updates['short_description'] = $this->shortDescription($product, $result);
                        $updates['meta_description'] = Str::limit(strip_tags($updates['short_description']), 250, '');
                        $this->stats['content_updated']++;
                    } else {
                        $this->stats['content_missing']++;
                    }
                }

                if (! $dryRun) {
                    if (count($updates) > 1) {
                        DB::table('products')->where('id', $product->id)->update($updates);
                    }
                    DB::table('supplier_products')->where('id', $product->supplier_product_id)->update($supplierUpdates);
                }

                $this->stats['source_url_updated']++;
            } catch (\Throwable $e) {
                $this->stats['errors']++;
                $this->warn('  error: ' . $e->getMessage());
            }

            $this->sleep();
        }

        $this->table(['metric', 'count'], array_map(
            fn ($key, $value) => [$key, $value],
            array_keys($this->stats),
            array_values($this->stats)
        ));

        return $this->stats['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function productsToProcess()
    {
        $supplierId = (int) DB::table('suppliers')->where('code', self::SUPPLIER_CODE)->value('id');
        if (! $supplierId) {
            return collect();
        }

        $query = DB::table('supplier_products as sp')
            ->join('products as p', 'p.id', '=', 'sp.product_id')
            ->leftJoin('brands as b', 'b.id', '=', 'p.brand_id')
            ->where('sp.supplier_id', $supplierId)
            ->where('p.is_archived', false)
            ->where(function ($q) {
                $q->where('sp.match_status', 'created_from_price_list')
                    ->orWhere('sp.raw', 'like', '%needs_enrichment%')
                    ->orWhereNull('sp.source_url');
            })
            ->select(
                'p.id',
                'p.sku',
                'p.name',
                'p.content',
                'p.short_description',
                'p.images',
                'p.category_id',
                'b.name as brand_name',
                'sp.id as supplier_product_id',
                'sp.supplier_article',
                'sp.supplier_name',
                'sp.raw as supplier_raw'
            )
            ->orderBy('p.id');

        if ($sku = $this->option('sku')) {
            $query->where('p.sku', $sku);
        }

        if ($categoryId = $this->option('category')) {
            $query->where('p.category_id', (int) $categoryId);
        }

        $limit = max(1, (int) $this->option('limit'));
        $offset = max(0, (int) $this->option('offset'));

        return $query->offset($offset)->limit($limit)->get();
    }

    private function findSourcePage(string $apiKey, object $product): ?array
    {
        $query = 'site:bania.by ' . $this->searchText($product);
        $response = Http::withHeaders([
            'X-API-KEY' => $apiKey,
            'Content-Type' => 'application/json',
        ])->timeout(30)->post(self::SERPER_URL, [
            'q' => $query,
            'gl' => 'by',
            'hl' => 'ru',
            'num' => 10,
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Serper error: HTTP ' . $response->status());
        }

        $links = collect($response->json('organic') ?? [])
            ->pluck('link')
            ->filter(fn ($url) => is_string($url) && str_contains($url, 'bania.by'))
            ->unique()
            ->values()
            ->all();

        foreach ($links as $url) {
            $html = $this->fetch($url);
            $pageTitle = $this->extractTitle($html);

            if (! $this->isLikelyProductPage($html, $url, $pageTitle, $product)) {
                continue;
            }

            return [
                'url' => $url,
                'title' => $pageTitle,
                'description' => $this->extractDescription($html),
                'images' => $this->extractImages($html, $url),
            ];
        }

        return null;
    }

    private function searchText(object $product): string
    {
        $parts = array_filter([
            (string) ($product->supplier_article ?? ''),
            (string) ($product->supplier_name ?: $product->name),
            (string) ($product->brand_name ?? ''),
        ]);

        return trim(implode(' ', $parts));
    }

    private function isLikelyProductPage(string $html, string $url, string $pageTitle, object $product): bool
    {
        if (str_contains($url, '/blog') || str_contains($url, '/category')) {
            return false;
        }

        if ($this->extractImages($html, $url) === []) {
            return false;
        }

        $left = $this->normalize($pageTitle);
        $right = $this->normalize((string) ($product->supplier_name ?: $product->name));
        similar_text($left, $right, $percent);

        return $percent >= 45 || $this->tokenOverlap($left, $right) >= 2;
    }

    private function buildContent(object $product, array $result): string
    {
        $scraped = trim(strip_tags((string) ($result['description'] ?? '')));

        if ($this->ai->isAvailable()) {
            $text = $this->ai->enrich(
                (string) $product->name,
                (string) ($product->brand_name ?? ''),
                $scraped,
                ['source' => $result['url']]
            );

            if ($text) {
                return $text;
            }
        }

        if ($scraped === '') {
            return '';
        }

        return '<p>' . e(Str::limit($scraped, 900, '')) . '</p>';
    }

    private function shortDescription(object $product, array $result): string
    {
        $scraped = trim(strip_tags((string) ($result['description'] ?? '')));
        if ($this->ai->isAvailable()) {
            $text = $this->ai->shortDescription(
                (string) $product->name,
                (string) ($product->brand_name ?? ''),
                ['source' => $result['url']]
            );
            if ($text) {
                return $text;
            }
        }

        return $scraped !== ''
            ? Str::limit($scraped, 220, '')
            : 'Product is available to order. Current price and stock are shown on the page.';
    }

    private function looksLikePriceListContent(string $content): bool
    {
        $text = mb_strtolower(strip_tags($content));

        return str_contains($text, 'available to order')
            || str_contains($text, 'current price')
            || str_contains($text, 'Đ´ĐľŃŃ‚ŃĐżĐµĐ˝ Đş Đ·Đ°ĐşĐ°Đ·Ń')
            || mb_strlen(trim($text)) < 180;
    }

    private function extractTitle(string $html): string
    {
        if (preg_match('~<h1[^>]*>(.*?)</h1>~isu', $html, $match)) {
            return $this->cleanText(strip_tags($match[1]));
        }

        if (preg_match('~<title[^>]*>(.*?)</title>~isu', $html, $match)) {
            return $this->cleanText(strip_tags($match[1]));
        }

        return '';
    }

    private function extractDescription(string $html): string
    {
        $patterns = [
            '~<div[^>]+class=["\'][^"\']*(?:description|desc|tab-description|product-description)[^"\']*["\'][^>]*>([\s\S]*?)</div>~iu',
            '~<section[^>]+class=["\'][^"\']*(?:description|desc)[^"\']*["\'][^>]*>([\s\S]*?)</section>~iu',
            '~<meta[^>]+name=["\']description["\'][^>]+content=["\'](.*?)["\'][^>]*>~iu',
            '~<meta[^>]+property=["\']og:description["\'][^>]+content=["\'](.*?)["\'][^>]*>~iu',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $match)) {
                return $this->cleanDescription($match[1]);
            }
        }

        return '';
    }

    private function extractImages(string $html, string $pageUrl): array
    {
        $images = [];
        if (preg_match_all('~(?:href|src|data-src|data-large|data-image|data-image-large|data-image-thumb|data-zoom-image)=["\']([^"\']+\.(?:jpg|jpeg|png|webp)(?:\?[^"\']*)?)["\']~iu', $html, $matches)) {
            foreach ($matches[1] as $src) {
                $url = $this->normalizeImageUrl($this->absoluteUrl($src, $pageUrl));
                if (! $this->isProductImage($url)) {
                    continue;
                }
                $images[] = $url;
            }
        }

        return array_values(array_unique(array_slice($images, 0, 8)));
    }

    private function downloadImages(array $urls, object $product): array
    {
        if ($urls === []) {
            return [];
        }

        $dir = public_path(self::IMAGE_DIR);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $paths = [];
        $base = Str::slug((string) ($product->supplier_article ?: $product->name)) ?: 'bania-product';

        foreach (array_values(array_unique($urls)) as $index => $url) {
            try {
                $ext = strtolower(pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION)) ?: 'jpg';
                if (! in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                    $ext = 'jpg';
                }

                $filename = $base . '-' . ($index + 1) . '.' . $ext;
                $target = $dir . DIRECTORY_SEPARATOR . $filename;
                if (! file_exists($target)) {
                    file_put_contents($target, $this->fetch($url));
                }

                if ($this->isUsableImage($target)) {
                    $paths[] = self::IMAGE_DIR . '/' . $filename;
                } else {
                    @unlink($target);
                }
            } catch (\Throwable) {
                // Keep processing the rest of the gallery.
            }
        }

        return array_values(array_unique($paths));
    }

    private function isProductImage(string $url): bool
    {
        if (! str_contains($url, 'bania.by') || ! str_contains($url, '/image/catalog/')) {
            return false;
        }

        $path = strtolower((string) parse_url($url, PHP_URL_PATH));

        return ! preg_match('~/(?:logo|icon|icons|payment|social|banner|manufacturer)/|(?:sprite|placeholder|telegram|viber|whatsapp|email|tel)~i', $path);
    }

    private function normalizeImageUrl(string $url): string
    {
        $url = strtok($url, '?') ?: $url;
        if (str_contains($url, '/image/cache/catalog/')) {
            $url = str_replace('/image/cache/catalog/', '/image/catalog/', $url);
            $url = preg_replace('~-\d+x\d+(\.(?:jpg|jpeg|png|webp))$~i', '$1', $url) ?? $url;
        }

        return $url;
    }

    private function absoluteUrl(string $url, string $base): string
    {
        if (str_starts_with($url, '//')) {
            return 'https:' . $url;
        }

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        $root = (string) parse_url($base, PHP_URL_SCHEME) . '://' . (string) parse_url($base, PHP_URL_HOST);

        return rtrim($root, '/') . '/' . ltrim($url, '/');
    }

    private function fetch(string $url): string
    {
        $response = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (compatible; KotlovBot/1.0)',
            'Accept-Language' => 'ru,en;q=0.8',
        ])->timeout(45)->get(str_replace(' ', '%20', $url));

        if (! $response->successful()) {
            throw new \RuntimeException('Fetch failed: HTTP ' . $response->status());
        }

        return $response->body();
    }

    private function isUsableImage(string $path): bool
    {
        $size = @getimagesize($path);

        return $size !== false && ($size[0] ?? 0) >= 100 && ($size[1] ?? 0) >= 100;
    }

    private function decodeArray(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter($value));
        }

        if (is_string($value) && trim($value) !== '') {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? array_values(array_filter($decoded)) : [];
        }

        return [];
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower($value);
        $value = preg_replace('/[^\pL\pN]+/u', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }

    private function tokenOverlap(string $left, string $right): int
    {
        $leftTokens = array_filter(explode(' ', $left), fn ($token) => mb_strlen($token) >= 3);
        $rightTokens = array_filter(explode(' ', $right), fn ($token) => mb_strlen($token) >= 3);

        return count(array_intersect(array_unique($leftTokens), array_unique($rightTokens)));
    }

    private function cleanDescription(string $html): string
    {
        $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $html = strip_tags($html, '<p><ul><ol><li><strong><b><br>');
        $html = preg_replace('~<(script|style)[^>]*>.*?</\1>~is', '', $html) ?? $html;

        return trim($html);
    }

    private function cleanText(string $value): string
    {
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    private function sleep(): void
    {
        $sleepMs = max(0, (int) $this->option('sleep'));
        if ($sleepMs > 0) {
            usleep($sleepMs * 1000);
        }
    }
}
