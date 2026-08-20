<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\ProductSourceEnricher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class EnrichTmManagementTmarketCommand extends Command
{
    protected $signature = 'supplier:enrich-tm-tmarket
        {--apply : Apply images/specs/content to matched products}
        {--limit= : Limit products to process}
        {--brand= : Process only brand name}
        {--content : Update content from cleaned TMarket description}
        {--replace-images : Replace existing images instead of only filling missing images}';

    protected $description = 'Enrich TM Management products with photos, specs and descriptions from tmarket.by.';

    private const SUPPLIER_CODE = 'tm-management';
    private const BASE_URL = 'https://tmarket.by';
    private const ROOT_URL = 'https://tmarket.by/product/';
    private const BRAND_PREFIXES = [
        'De Dietrich' => '/product/otopitelnoe-oborudovanie-de-dietrich/',
        'Shinhoo' => '/product/nasosy-shinhoo/',
        'SFA' => '/product/nasosy-sfa/',
        'Джилекс' => '/product/oborudovanie-dzhileks/',
        'Watrix' => '/product/watrix/',
    ];

    /** @var array<string,array<int,array{url:string,title:string,brand:string,normalized:string,parsed:array<string,mixed>}>> */
    private array $index = [];

    public function handle(ProductSourceEnricher $enricher): int
    {
        $apply = (bool) $this->option('apply');
        $limit = $this->option('limit') ? max(1, (int) $this->option('limit')) : null;
        $onlyBrand = trim((string) $this->option('brand'));

        $this->info($apply ? 'APPLY: products will be enriched from TMarket.' : 'DRY RUN: no database writes.');

        $products = $this->tmProducts($onlyBrand, $limit);
        if ($products->isEmpty()) {
            $this->warn('No TM Management products found.');
            return self::SUCCESS;
        }

        $this->info('Products to check: ' . $products->count());
        $this->buildIndex($onlyBrand);

        $rows = [];
        $stats = [
            'matched' => 0,
            'skipped' => 0,
            'images_saved' => 0,
            'specs_found' => 0,
            'content_found' => 0,
            'errors' => 0,
        ];

        foreach ($products as $product) {
            $match = $this->matchProduct($product);

            if (! $match) {
                $stats['skipped']++;
                $rows[] = [$product->brand_name, Str::limit($product->name, 42), '—', 'skip', 'no safe match'];
                continue;
            }

            $stats['matched']++;
            $parsed = $match['parsed'];
            $rows[] = [
                $product->brand_name,
                Str::limit($product->name, 42),
                Str::limit($match['title'], 42),
                count($parsed['images'] ?? []) . ' img / ' . count($parsed['specs'] ?? []) . ' specs',
                $match['url'],
            ];

            if (! $apply) {
                continue;
            }

            try {
                $model = Product::query()->findOrFail($product->id);
                $result = $enricher->enrichFromParsed($model, $match['url'], $parsed, [
                    'update_images' => true,
                    'replace_images' => (bool) $this->option('replace-images') || $this->imagesEmpty($model->images),
                    'update_specs' => true,
                    'replace_specs' => true,
                    'min_specs_to_replace' => 2,
                    'update_content' => (bool) $this->option('content'),
                    'source_content' => true,
                    'update_documents' => false,
                    'update_video' => false,
                ]);
                $model->forceFill([
                    'short_description' => $this->safeShortDescription($model, (string) $product->brand_name),
                    'meta_description' => $model->name . ' — характеристики, консультация и поставка в %city%.',
                    'updated_at' => now(),
                ])->save();

                $stats['images_saved'] += (int) ($result['images_saved'] ?? 0);
                $stats['specs_found'] += (int) ($result['specs_found'] ?? 0);
                $stats['content_found'] += (int) ($result['content_found'] ?? 0);
            } catch (\Throwable $e) {
                $stats['errors']++;
                $this->warn('Error product #' . $product->id . ': ' . $e->getMessage());
            }
        }

        $this->table(['brand', 'site product', 'tmarket match', 'found', 'url/status'], array_slice($rows, 0, 60));
        if (count($rows) > 60) {
            $this->line('... ' . (count($rows) - 60) . ' more rows');
        }
        $this->table(['metric', 'count'], array_map(
            fn ($key, $value) => [$key, $value],
            array_keys($stats),
            array_values($stats)
        ));

        return $stats['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function tmProducts(string $onlyBrand, ?int $limit)
    {
        $query = DB::table('products as p')
            ->join('brands as b', 'b.id', '=', 'p.brand_id')
            ->join('supplier_products as sp', 'sp.product_id', '=', 'p.id')
            ->join('suppliers as s', 's.id', '=', 'sp.supplier_id')
            ->where('s.code', self::SUPPLIER_CODE)
            ->where('p.is_archived', false)
            ->select('p.id', 'p.name', 'p.sku', 'p.images', 'b.name as brand_name')
            ->orderBy('b.name')
            ->orderBy('p.name');

        if ($onlyBrand !== '') {
            $query->whereRaw('LOWER(b.name) = ?', [mb_strtolower($onlyBrand)]);
        }

        if ($limit) {
            $query->limit($limit);
        }

        return $query->get();
    }

    private function buildIndex(string $onlyBrand): void
    {
        $categoryUrls = $this->categoryUrls($onlyBrand);
        $this->info('TMarket categories: ' . count($categoryUrls));

        $productUrlsByBrand = [];
        foreach ($categoryUrls as $category) {
            foreach ($this->productUrlsFromCategory($category['url']) as $url) {
                $brand = $category['brand'];
                $productUrlsByBrand[$brand][$url] = true;
            }
        }

        foreach ($productUrlsByBrand as $brand => $urls) {
            $this->line($brand . ': candidate URLs ' . count($urls));
            foreach (array_keys($urls) as $url) {
                try {
                    $parsed = app(ProductSourceEnricher::class)->preview($url);
                    $title = trim((string) ($parsed['title'] ?? ''));
                    if ($title === '') {
                        continue;
                    }
                    $this->index[$brand][] = [
                        'url' => $url,
                        'title' => $title,
                        'brand' => $brand,
                        'normalized' => $this->normalize($title),
                        'parsed' => $parsed,
                    ];
                } catch (\Throwable $e) {
                    $this->warn('TMarket skip ' . $url . ': ' . $e->getMessage());
                }
            }
        }
    }

    /** @return array<int,array{brand:string,url:string}> */
    private function categoryUrls(string $onlyBrand): array
    {
        $root = $this->fetch(self::ROOT_URL);
        $urls = [];

        foreach (self::BRAND_PREFIXES as $brand => $prefix) {
            if ($onlyBrand !== '' && mb_strtolower($brand) !== mb_strtolower($onlyBrand)) {
                continue;
            }

            foreach ($this->extractLinks($root) as $href) {
                $path = $this->pathOnly($href);
                if (! str_starts_with($path, $prefix) || $path === $prefix) {
                    continue;
                }

                $relative = trim(mb_substr($path, mb_strlen($prefix)), '/');
                if ($relative !== '' && ! str_contains($relative, '/')) {
                    $urls[$brand . '|' . $path] = [
                        'brand' => $brand,
                        'url' => $this->absolute($path),
                    ];
                }
            }
        }

        return array_values($urls);
    }

    /** @return array<int,string> */
    private function productUrlsFromCategory(string $categoryUrl): array
    {
        $html = $this->fetch($categoryUrl);
        $categoryPath = rtrim((string) parse_url($categoryUrl, PHP_URL_PATH), '/') . '/';
        $urls = [];

        foreach ($this->extractLinks($html) as $href) {
            $path = $this->pathOnly($href);
            if (! str_starts_with($path, $categoryPath) || $path === $categoryPath) {
                continue;
            }

            $relative = trim(mb_substr($path, mb_strlen($categoryPath)), '/');
            if ($relative !== '' && ! str_contains($relative, '/')) {
                $urls[] = $this->absolute($path);
            }
        }

        return array_values(array_unique($urls));
    }

    /** @return array<int,string> */
    private function extractLinks(string $html): array
    {
        preg_match_all('/href=["\']([^"\']+)["\']/iu', $html, $matches);
        return $matches[1] ?? [];
    }

    private function matchProduct(object $product): ?array
    {
        $brand = (string) $product->brand_name;
        $candidates = $this->index[$brand] ?? [];
        if ($candidates === []) {
            return null;
        }

        $productNorm = $this->normalize((string) $product->name);
        $best = null;
        $bestScore = 0.0;

        foreach ($candidates as $candidate) {
            $candidateNorm = $candidate['normalized'];
            $score = $this->score($productNorm, $candidateNorm);
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $candidate;
            }
        }

        if (! $best || $bestScore < 0.92) {
            return null;
        }

        return $best + ['score' => $bestScore];
    }

    private function score(string $left, string $right): float
    {
        if ($left === '' || $right === '') {
            return 0.0;
        }
        if ($left === $right) {
            return 1.0;
        }

        similar_text($left, $right, $percent);
        $score = $percent / 100;

        if (str_contains($left, $right) || str_contains($right, $left)) {
            $score = max($score, min(mb_strlen($left), mb_strlen($right)) / max(mb_strlen($left), mb_strlen($right)));
        }

        return $score;
    }

    private function normalize(string $text): string
    {
        $text = mb_strtolower($text);
        $text = str_replace(['ё', '×', 'х'], ['е', 'x', 'x'], $text);
        $text = preg_replace('/\b(л|литр|литров|мм|см|квт|вт|бар)\b/u', '', $text) ?? $text;
        $text = preg_replace('/[^a-zа-я0-9]+/u', ' ', $text) ?? $text;
        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    private function imagesEmpty(mixed $images): bool
    {
        if (is_array($images)) {
            return $images === [];
        }

        $decoded = json_decode((string) $images, true);
        return ! is_array($decoded) || $decoded === [];
    }

    private function safeShortDescription(Product $product, string $brand): string
    {
        return trim($brand) . ' — поставка под заказ по Беларуси. Уточняйте наличие, комплектацию и срок поставки.';
    }

    private function fetch(string $url): string
    {
        $response = Http::withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; KotlovBot/1.0)'])
            ->timeout(45)
            ->retry(2, 500)
            ->get($url);

        if (! $response->successful()) {
            throw new \RuntimeException('HTTP ' . $response->status() . ' for ' . $url);
        }

        return $response->body();
    }

    private function pathOnly(string $href): string
    {
        $path = (string) parse_url($href, PHP_URL_PATH);
        return rtrim($path, '/') . '/';
    }

    private function absolute(string $href): string
    {
        if (str_starts_with($href, 'http://') || str_starts_with($href, 'https://')) {
            return $href;
        }

        return rtrim(self::BASE_URL, '/') . '/' . ltrim($href, '/');
    }
}
