<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\AiContentEnricher;
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
                $parsedForStorage = $this->cleanParsedForStorage($parsed);

                $result = $enricher->enrichFromParsed($model, $match['url'], $parsedForStorage, [
                    'update_images' => true,
                    'replace_images' => (bool) $this->option('replace-images') || $this->imagesEmpty($model->images),
                    'update_specs' => true,
                    'replace_specs' => true,
                    'min_specs_to_replace' => 2,
                    'update_content' => false,
                    'source_content' => false,
                    'update_documents' => false,
                    'update_video' => false,
                ]);

                $updates = [
                    'short_description' => $this->safeShortDescription($model, (string) $product->brand_name),
                    'meta_description' => $model->name . ' — характеристики, консультация и поставка в %city%.',
                    'updated_at' => now(),
                ];

                if ((bool) $this->option('content')) {
                    $seo = $this->generateSeoContent($model, (string) $product->brand_name, $match['url'], $parsedForStorage, $enricher);
                    if ($seo !== null) {
                        $updates = array_merge($updates, $seo);
                        $stats['content_found']++;
                    }
                }

                $model->forceFill($updates)->save();

                $stats['images_saved'] += (int) ($result['images_saved'] ?? 0);
                $stats['specs_found'] += (int) ($result['specs_found'] ?? 0);
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

    /**
     * TMarket often stores a full "Технические характеристики: ..." paragraph
     * inside the description. The storefront has a dedicated specifications tab,
     * so keep specs as specs and leave the description readable.
     *
     * @param array<string,mixed> $parsed
     * @return array<string,mixed>
     */
    private function cleanParsedForStorage(array $parsed): array
    {
        $description = trim((string) ($parsed['description'] ?? ''));
        if ($description === '') {
            return $parsed;
        }

        $cleaned = preg_replace(
            '/^\s*Технические\s+характеристики\s*:\s*.*?(?=(?:Насос|Установка|Станция|Блок|Мембрана|Бак|Кот[её]л|Горелка|Дымоход|Система)\b)/isu',
            '',
            $description,
            1
        );

        if (! is_string($cleaned) || trim($cleaned) === '') {
            return $parsed;
        }

        $parsed['description'] = trim($cleaned);

        return $parsed;
    }

    /**
     * Generate the final KOTLOV.BY product copy from TMarket facts.
     * Source text is used only as context; it is not stored verbatim.
     *
     * @param array<string,mixed> $parsed
     * @return array<string,string>|null
     */
    private function generateSeoContent(
        Product $product,
        string $brand,
        string $sourceUrl,
        array $parsed,
        ProductSourceEnricher $enricher
    ): ?array {
        $ai = app(AiContentEnricher::class);
        if (! $ai->isAvailable()) {
            $this->warn('AI content skipped for #' . $product->id . ': provider is not configured.');
            return null;
        }

        $seo = $ai->generateSeo(
            (string) $product->name,
            $brand,
            (string) ($product->category?->name ?? ''),
            (array) ($parsed['specs'] ?? []),
            [
                'source_url' => $sourceUrl,
                'source_title' => (string) ($parsed['title'] ?? ''),
                'source_short_description' => (string) ($parsed['short_description'] ?? ''),
                'source_description' => (string) ($parsed['description'] ?? ''),
            ],
        );

        $content = trim((string) ($seo['content'] ?? ''));
        $short = trim(strip_tags((string) ($seo['short'] ?? $seo['short_description'] ?? '')));

        if ($content === '' && $short === '') {
            $this->warn('AI content skipped for #' . $product->id . ': empty response.');
            return null;
        }

        $updates = [];

        if ($content !== '') {
            $content = $this->cleanSeoContent($content);
            $content = $enricher->sanitizeDescriptionHtml($content);
            if ($content !== '') {
                $updates['content'] = $content;
            }
        }

        if ($short !== '') {
            $updates['short_description'] = $this->cleanSeoShortDescription($short);
        }

        $updates['meta_description'] = $this->metaDescription($product, (string) ($updates['short_description'] ?? ''));

        return $updates === ['meta_description' => $updates['meta_description']] ? null : $updates;
    }

    private function cleanSeoShortDescription(string $text): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags($text)) ?? $text);
        $text = str_replace(['в %city%', '%city%'], 'по Беларуси', $text);

        return Str::limit($text, 240, '');
    }

    private function cleanSeoContent(string $html): string
    {
        $html = preg_replace('~с\s+доставк(?:ой|у)\s+в\s+%city%~iu', 'в %city% с доставкой по Беларуси', $html) ?? $html;
        $html = preg_replace('~доставка\s+в\s+%city%~iu', 'доставка по Беларуси', $html) ?? $html;
        $html = preg_replace('~\bв\s+%city%\s+по\s+Беларуси\b~iu', 'в %city% с доставкой по Беларуси', $html) ?? $html;

        if (! str_contains($html, '%city%')) {
            $html .= '<p>На KOTLOV.BY можно подобрать и заказать эту позицию в %city% с доставкой по Беларуси.</p>';
        }

        return trim($html);
    }

    private function metaDescription(Product $product, string $shortDescription): string
    {
        $base = $shortDescription !== ''
            ? $shortDescription
            : $product->name . ' — характеристики и подбор на KOTLOV.BY.';

        if (! str_contains($base, '%city%')) {
            $base .= ' Купить или заказать в %city% с доставкой по Беларуси.';
        }

        return Str::limit(trim($base), 250, '');
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

        if ((str_contains($left, $right) || str_contains($right, $left))
            && $this->numericTokensCompatible($left, $right)
            && $this->distinctiveTokensCompatible($left, $right)
            && min(mb_strlen($left), mb_strlen($right)) >= 6) {
            return 0.95;
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
        $text = preg_replace('/\([^)]*\)/u', ' ', $text) ?? $text;
        $text = preg_replace('/\b(л|литр|литров|мм|см|квт|вт|бар)\b/u', '', $text) ?? $text;
        $text = preg_replace(
            '/\b(канализационный|дренажный|насос|насосная|станция|современная|шиберная|задвижка|аварийная|сигнализация|для|только|общественных|профессионального|использования|помещений|с|измельчителем|комплекте|сифоном|трапа|высотой|плоским|поддон|от)\b/u',
            ' ',
            $text
        ) ?? $text;
        $text = preg_replace('/[^a-zа-я0-9]+/u', ' ', $text) ?? $text;
        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    private function numericTokensCompatible(string $left, string $right): bool
    {
        preg_match_all('/\d+/u', $left, $leftMatches);
        preg_match_all('/\d+/u', $right, $rightMatches);

        $leftNumbers = array_values(array_unique($leftMatches[0] ?? []));
        $rightNumbers = array_values(array_unique($rightMatches[0] ?? []));

        if ($leftNumbers === [] || $rightNumbers === []) {
            return true;
        }

        $shorter = mb_strlen($left) <= mb_strlen($right) ? $leftNumbers : $rightNumbers;
        $longer = mb_strlen($left) <= mb_strlen($right) ? $rightNumbers : $leftNumbers;

        return count(array_diff($shorter, $longer)) === 0;
    }

    private function distinctiveTokensCompatible(string $left, string $right): bool
    {
        $tokens = ['flat', 'pro', 'best', 'clim', 'mini', 'vx', 'gr', 'wp', 'ip', 'nm', 'dn', 'tri', 'smart'];

        foreach ($tokens as $token) {
            $leftHas = preg_match('/\b' . preg_quote($token, '/') . '\b/u', $left) === 1;
            $rightHas = preg_match('/\b' . preg_quote($token, '/') . '\b/u', $right) === 1;

            if ($leftHas !== $rightHas) {
                return false;
            }
        }

        return true;
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
