<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\ProductSourceEnricher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RepairVarmegaSourceUrlsCommand extends Command
{
    protected $signature = 'supplier:repair-varmega-source-urls
        {--apply : Write source_url changes}
        {--category= : Product category name filter}
        {--sitemap=https://varmega.ru/sitemap-iblock-43.xml : Varmega product sitemap URL}
        {--refresh-index : Rebuild cached article URL index}
        {--rn-profi-fallback : Search rn-profi.by by article when official Varmega URL is missing}
        {--rn-profi-section-index : Crawl RN-Profi Varmega section pages and index visible article tables}
        {--rn-profi-section-url=https://rn-profi.by/varmega/truboprovodnye-sistemy-iz-nerzhaveyuschej-stali-sus-304--profil-v/ : RN-Profi Varmega section URL}
        {--rn-profi-section-pages=80 : Maximum RN-Profi product pages to index from section}
        {--rn-profi-search-limit=0 : Maximum RN-Profi article searches, 0 means all}
        {--rn-profi-candidate-limit=8 : Maximum RN-Profi candidate pages to verify per article}
        {--http-timeout=8 : HTTP timeout for source discovery requests, seconds}
        {--product= : Process one product ID}
        {--article-prefix= : Process only supplier articles with this prefix, e.g. VM7040}
        {--limit=0 : Max supplier links to process, 0 means all}
        {--offset=0 : Skip supplier links}
        {--fix-category : Move products to the category resolved from the official Varmega URL}
        {--category-slug= : Force target category slug when --fix-category is used}
        {--enrich : Enrich products after source_url repair}
        {--replace-specs : Replace product specs during enrichment}
        {--min-specs-to-replace=1 : Skip spec replacement when source has fewer specs}
        {--overwrite-images : Replace existing product images}
        {--skip-documents : Do not copy documents/PDFs}
        {--sleep=1000 : Delay between enrichment requests, ms}';

    protected $description = 'Repair existing RN-Profi Varmega source URLs by exact official Varmega supplier article.';

    private const CACHE_PATH = 'supplier-cache/varmega-official-url-index.json';

    public function handle(ProductSourceEnricher $enricher): int
    {
        $apply = (bool) $this->option('apply');
        $enrich = (bool) $this->option('enrich');
        $limit = max(0, (int) $this->option('limit'));
        $offset = max(0, (int) $this->option('offset'));
        $sleep = max(300, (int) $this->option('sleep'));

        $this->line($apply
            ? '<fg=red;options=bold>APPLY: Varmega official source URLs will be written.</>'
            : '<fg=yellow;options=bold>DRY RUN: Varmega official source URLs will be previewed.</>');

        $index = $this->loadOfficialIndex();
        $this->info(sprintf('Official Varmega article index: %d URLs.', count($index)));
        if ((bool) $this->option('rn-profi-section-index')) {
            $rnProfiIndex = $this->loadRnProfiSectionIndex();
            $this->info(sprintf('RN-Profi section article index: %d URLs.', count($rnProfiIndex)));
            $index = $index + $rnProfiIndex;
        }
        $rnProfiSearchLimit = max(0, (int) $this->option('rn-profi-search-limit'));
        $rnProfiSearches = 0;

        $query = DB::table('supplier_products as sp')
            ->join('suppliers as s', 's.id', '=', 'sp.supplier_id')
            ->join('products as p', 'p.id', '=', 'sp.product_id')
            ->join('brands as b', 'b.id', '=', 'p.brand_id')
            ->leftJoin('categories as c', 'c.id', '=', 'p.category_id')
            ->where('s.code', 'rn-profi')
            ->where('b.name', 'Varmega')
            ->where('p.is_archived', false)
            ->whereNotNull('sp.supplier_article')
            ->where(function ($query): void {
                $query->whereNull('sp.source_url')
                    ->orWhere('sp.source_url', '')
                    ->orWhere('sp.source_url', 'not like', '%varmega.ru/product/%');
            })
            ->select([
                'sp.id as supplier_product_id',
                'sp.product_id',
                'sp.supplier_article',
                'sp.source_url',
                'p.name as product_name',
                'c.name as category_name',
            ])
            ->orderBy('p.id');

        if ($category = trim((string) $this->option('category'))) {
            $query->where('c.name', 'like', '%' . $category . '%');
        }

        if ($productId = (int) $this->option('product')) {
            $query->where('p.id', $productId);
        }

        if ($articlePrefix = trim((string) $this->option('article-prefix'))) {
            $query->where('sp.supplier_article', 'like', $articlePrefix . '%');
        }

        if ($offset > 0) {
            $query->offset($offset);
        }

        if ($limit > 0) {
            $query->limit($limit);
        }

        $rows = $query->get();
        $this->info(sprintf('RN-Profi Varmega links to check: %d.', $rows->count()));

        $stats = [
            'checked' => 0,
            'matched' => 0,
            'written' => 0,
            'enriched' => 0,
            'images_found' => 0,
            'images_saved' => 0,
            'specs_found' => 0,
            'attributes_saved' => 0,
            'category_changed' => 0,
            'missing' => 0,
            'errors' => 0,
        ];

        $examples = [];

        foreach ($rows as $row) {
            $stats['checked']++;
            if ($stats['checked'] === 1 || $stats['checked'] % 10 === 0) {
                $this->line(sprintf(
                    'Progress: checked=%d matched=%d missing=%d current=%s',
                    $stats['checked'],
                    $stats['matched'],
                    $stats['missing'],
                    (string) $row->supplier_article
                ));
            }

            $article = $this->normArticle((string) $row->supplier_article);
            $match = $article !== '' ? ($index[$article] ?? null) : null;
            $match ??= $article !== ''
                ? $this->knownOfficialVarmegaSourceForArticle($article)
                : null;

            if ($match === null
                && (bool) $this->option('rn-profi-fallback')
                && $article !== ''
                && ($rnProfiSearchLimit === 0 || $rnProfiSearches < $rnProfiSearchLimit)
            ) {
                $match = $this->knownRnProfiVarmegaSourceForArticle($article);
            }

            if ($match === null
                && (bool) $this->option('rn-profi-fallback')
                && $article !== ''
                && ($rnProfiSearchLimit === 0 || $rnProfiSearches < $rnProfiSearchLimit)
            ) {
                $rnProfiSearches++;
                $match = $this->findRnProfiSourceByArticle((string) $row->supplier_article, $article);
            }

            if ($match === null) {
                $stats['missing']++;
                if (count($examples) < 20) {
                    $examples[] = [
                        $row->product_id,
                        $row->supplier_article,
                        mb_substr((string) $row->category_name, 0, 22),
                        mb_substr((string) $row->product_name, 0, 44),
                        '-',
                    ];
                }
                continue;
            }

            $stats['matched']++;
            if (count($examples) < 20) {
                $examples[] = [
                    $row->product_id,
                    $row->supplier_article,
                    mb_substr((string) $row->category_name, 0, 22),
                    mb_substr((string) $row->product_name, 0, 44),
                    mb_substr((string) $match['url'], 0, 70),
                ];
            }

            if (! $apply) {
                continue;
            }

            DB::table('supplier_products')->where('id', $row->supplier_product_id)->update([
                'source_url' => $match['url'],
                'updated_at' => now(),
            ]);
            $stats['written']++;

            if ((bool) $this->option('fix-category')) {
                $categoryId = $this->categoryIdForOfficialUrl((string) $match['url'], $article);
                if ($categoryId > 0) {
                    $currentCategoryId = (int) DB::table('products')->where('id', $row->product_id)->value('category_id');
                    if ($currentCategoryId !== $categoryId) {
                        DB::table('products')->where('id', $row->product_id)->update([
                            'category_id' => $categoryId,
                            'updated_at' => now(),
                        ]);
                        $stats['category_changed']++;
                    }
                }
            }

            if (! $enrich) {
                continue;
            }

            $product = Product::find((int) $row->product_id);
            if (! $product) {
                $stats['errors']++;
                continue;
            }

            try {
                $result = $enricher->enrich($product, (string) $match['url'], [
                    'preview_only' => false,
                    'replace_images' => (bool) $this->option('overwrite-images'),
                    'update_images' => true,
                    'update_specs' => true,
                    'replace_specs' => (bool) $this->option('replace-specs'),
                    'min_specs_to_replace' => max(0, (int) $this->option('min-specs-to-replace')),
                    'update_service' => true,
                    'update_documents' => ! (bool) $this->option('skip-documents'),
                    'clear_documents' => false,
                    'update_video' => true,
                    'update_content' => true,
                    'source_content' => true,
                    'min_specs_for_ai' => 999,
                    'require_images_for_ai' => true,
                ]);

                $stats['images_found'] += (int) ($result['images_found'] ?? 0);
                $stats['images_saved'] += (int) ($result['images_saved'] ?? 0);
                $stats['specs_found'] += (int) ($result['specs_found'] ?? 0);
                $stats['attributes_saved'] += (int) ($result['attribute_values_saved'] ?? 0);
                if (($result['updated_fields'] ?? []) !== []) {
                    $stats['enriched']++;
                }
            } catch (\Throwable $e) {
                $stats['errors']++;
                $this->warn(sprintf('  #%d %s ERROR: %s', $row->product_id, $row->supplier_article, $e->getMessage()));
            }

            usleep($sleep * 1000);
        }

        if ($examples !== []) {
            $this->table(['product', 'article', 'category', 'name', 'official_url'], $examples);
        }

        $this->table(['metric', 'count'], array_map(
            fn (string $key, int $value): array => [$key, $value],
            array_keys($stats),
            array_values($stats)
        ));

        return $stats['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function loadOfficialIndex(): array
    {
        $path = storage_path('app/' . self::CACHE_PATH);

        if (! (bool) $this->option('refresh-index') && is_file($path)) {
            $data = json_decode((string) file_get_contents($path), true);
            if (is_array($data)) {
                return array_filter($data['items'] ?? $data, fn ($item): bool => is_array($item) && ! empty($item['url']));
            }
        }

        $sitemap = $this->fetch((string) $this->option('sitemap'));
        if ($sitemap === null) {
            return [];
        }

        preg_match_all('#<loc>\s*([^<]+)\s*</loc>#i', $sitemap, $matches);
        $index = [];

        foreach ($matches[1] ?? [] as $rawUrl) {
            $url = html_entity_decode(trim((string) $rawUrl), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if (! str_starts_with($url, 'https://varmega.ru/product/')) {
                continue;
            }

            foreach ($this->extractArticleTokensFromUrl($url) as $token) {
                if (! str_starts_with($token, 'VM') || mb_strlen($token) < 5) {
                    continue;
                }

                $index[$token] = ['url' => $url];
            }
        }

        ksort($index);
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($path, json_encode([
            'generated_at' => now()->toDateTimeString(),
            'source' => (string) $this->option('sitemap'),
            'items' => $index,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

        return $index;
    }

    private function fetch(string $url): ?string
    {
        return $this->fetchWithEffectiveUrl($url)['body'] ?? null;
    }

    /**
     * @return array{body: ?string, url: string}
     */
    private function fetchWithEffectiveUrl(string $url): array
    {
        $effectiveUrl = $url;

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 5,
                CURLOPT_CONNECTTIMEOUT => min(8, max(3, (int) $this->option('http-timeout'))),
                CURLOPT_TIMEOUT => max(3, (int) $this->option('http-timeout')),
                CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; KotlovBot/1.0)',
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
            ]);
            $body = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $effectiveUrl = (string) (curl_getinfo($ch, CURLINFO_EFFECTIVE_URL) ?: $url);
            curl_close($ch);

            if (is_string($body) && $body !== '' && $status < 400) {
                return ['body' => $body, 'url' => $effectiveUrl];
            }
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => max(3, (int) $this->option('http-timeout')),
                'header' => "User-Agent: Mozilla/5.0 (compatible; KotlovBot/1.0)\r\n",
            ],
            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
        ]);

        $body = @file_get_contents($url, false, $context);

        return [
            'body' => is_string($body) && $body !== '' ? $body : null,
            'url' => $effectiveUrl,
        ];
    }

    private function findRnProfiSourceByArticle(string $article, string $normArticle): ?array
    {
        $searchUrl = 'https://rn-profi.by/index.php?route=product/search&search=' . rawurlencode($article);
        $page = $this->fetchWithEffectiveUrl($searchUrl);
        $html = $page['body'];
        if ($html === null) {
            return null;
        }

        $candidateUrls = $this->extractRnProfiProductUrls($html);
        $effectiveUrl = (string) $page['url'];
        if ($effectiveUrl !== $searchUrl && $this->rnProfiUrlLooksLikeProduct($effectiveUrl)) {
            array_unshift($candidateUrls, $effectiveUrl);
        }

        $candidateLimit = max(1, (int) $this->option('rn-profi-candidate-limit'));
        foreach (array_slice(array_values(array_unique($candidateUrls)), 0, $candidateLimit) as $url) {
            $candidateHtml = $url === $searchUrl ? $html : $this->fetch($url);
            if ($candidateHtml === null) {
                continue;
            }

            if (! $this->pageContainsArticle($candidateHtml, $normArticle)) {
                continue;
            }

            return ['url' => $url];
        }

        return null;
    }

    private function knownRnProfiVarmegaSourceForArticle(string $normArticle): ?array
    {
        $pages = [
            '/^VM700304/u' => 'https://rn-profi.by/truba-iz-nerzhaveyuschej-stali-varmega-inox-press',
            '/^VM7020/u' => 'https://rn-profi.by/index.php?route=product/product&product_id=1452',
            '/^VM7030/u' => 'https://rn-profi.by/index.php?route=product/product&product_id=1031',
            '/^VM7040/u' => 'https://rn-profi.by/mufta-vstavka-odnorastrubnaya-varmega-inox-press-perekhodnaya',
            '/^VM706/u' => 'https://rn-profi.by/mufta-rastrubnaya-varmega-inox-press-s-naruzhnoj-rezboj',
            '/^VM707/u' => 'https://rn-profi.by/mufta-vstavka-varmega-inox-press-s-vnutrennej-rezboj',
            '/^VM708/u' => 'https://rn-profi.by/mufta-vstavka-varmega-inox-press-s-naruzhnoj-rezboj',
            '/^VM709/u' => 'https://rn-profi.by/mufta-rastrubnaya-varmega-inox-press-s-nakidnoj-gajkoj',
            '/^VM710/u' => 'https://rn-profi.by/mufta-dvukhrastrubnaya-varmega-inox-press-nadvizhnaya',
            '/^VM711/u' => 'https://rn-profi.by/ugolnik-90%C2%B0-odnorastrubnyj-varmega-inox-press',
            '/^VM712/u' => 'https://rn-profi.by/ugolnik-45%C2%B0-dvukhrastrubnyj-varmega-inox-press',
            '/^VM713/u' => 'https://rn-profi.by/ugolnik-45%C2%B0-odnorastrubnyj-varmega-inox-press',
            '/^VM714/u' => 'https://rn-profi.by/ugolnik-rastrubnyj-varmega-inox-press-s-vnutrennej-rezboj',
            '/^VM715/u' => 'https://rn-profi.by/ugolnik-rastrubnyj-varmega-inox-press-s-naruzhnoj-rezboj',
            '/^VM716/u' => 'https://rn-profi.by/index.php?route=product/product&product_id=1046',
            '/^VM717/u' => 'https://rn-profi.by/vodorozetka-rastrubnaya-varmega-inox-press-s-vnutrennej-rezboj--prokhodnaya',
            '/^VM718/u' => 'https://rn-profi.by/vodorozetka-rastrubnaya-varmega-inox-press-s-vnutrennej-rezboj',
            '/^VM719/u' => 'https://rn-profi.by/index.php?route=product/product&product_id=1453',
            '/^VM720/u' => 'https://rn-profi.by/index.php?route=product/product&product_id=1049',
            '/^VM721/u' => 'https://rn-profi.by/index.php?route=product/product&product_id=1048',
            '/^VM723/u' => 'https://rn-profi.by/perekhodnik-bezrastrubnyj-pex',
            '/^VM724/u' => 'https://rn-profi.by/krestovina-rastrubnaya-varmega',
        ];

        foreach ($pages as $pattern => $url) {
            if (! preg_match($pattern, $normArticle)) {
                continue;
            }

            $html = $this->fetch($url);
            if ($html !== null && $this->pageContainsArticle($html, $normArticle)) {
                return ['url' => $url];
            }
        }

        return null;
    }

    private function knownOfficialVarmegaSourceForArticle(string $normArticle): ?array
    {
        $url = null;

        $collectorCabinets = [
            'VM35500' => 'https://varmega.ru/product/kollektory-i-komplektuyushchie/kollektornyy-raspredelitelnyy-shkaf-vstraivaemyy-varmega-vm35500-shrv-0-na-1-3-vykhoda-668kh125kh402/',
            'VM35501' => 'https://varmega.ru/product/kollektory-i-komplektuyushchie/kollektornyy-raspredelitelnyy-shkaf-vstraivaemyy-varmega-vm35501-shrv-1-na-4-5-vykhodov-668kh125kh49/',
            'VM35502' => 'https://varmega.ru/product/kollektory-i-komplektuyushchie/kollektornyy-raspredelitelnyy-shkaf-vstraivaemyy-varmega-vm35502-shrv-2-na-6-7-vykhodov-668kh125kh59/',
            'VM35503' => 'https://varmega.ru/product/kollektory-i-komplektuyushchie/kollektornyy-raspredelitelnyy-shkaf-vstraivaemyy-varmega-vm35503-shrv-3-na-8-10-vykhodov-668kh125kh7/',
            'VM35504' => 'https://varmega.ru/product/kollektory-i-komplektuyushchie/kollektornyy-raspredelitelnyy-shkaf-vstraivaemyy-varmega-vm35504-shrv-4-na-11-12-vykhodov-668kh125kh/',
            'VM35505' => 'https://varmega.ru/product/kollektory-i-komplektuyushchie/kollektornyy-raspredelitelnyy-shkaf-vstraivaemyy-varmega-vm35505-shrv-5-na-13-16-vykhodov-668kh125kh/',
            'VM35506' => 'https://varmega.ru/product/kollektory-i-komplektuyushchie/kollektornyy-raspredelitelnyy-shkaf-vstraivaemyy-varmega-vm35506-shrv-6-na-17-18-vykhodov-668kh125kh/',
        ];

        if (isset($collectorCabinets[$normArticle])) {
            return ['url' => $collectorCabinets[$normArticle]];
        }

        if (preg_match('/^VM7220000(\d{2})$/u', $normArticle, $m)) {
            $size = ltrim($m[1], '0');
            if ($size !== '') {
                $url = 'https://varmega.ru/product/truby-i-fitingi/zaglushka-rastrubnaya-varmega-inox-press-'
                    . mb_strtolower($normArticle) . '-' . $size . '-mm/';
            }
        }

        if ($normArticle === 'VM721350735') {
            $url = 'https://termogorod.ru/truby-i-fitingi/truby-i-fitingi-iz-nerzhavejki/truby-i-fitingi-iz-nerzhavejki-varmega/varmega-vm721350735';
        }

        if (preg_match('/^VM70600\d{4}$/u', $normArticle)) {
            $url = 'https://termogorod.ru/truby-i-fitingi/truby-i-fitingi-iz-nerzhavejki/truby-i-fitingi-iz-nerzhavejki-varmega/varmega-'
                . mb_strtolower($normArticle);
        }

        if (preg_match('/^VM70100(\d{2})(\d{2})$/u', $normArticle, $m)) {
            $size = ltrim($m[1], '0') . '-' . ltrim($m[2], '0') . '-mm';
            $url = 'https://varmega.ru/product/truby-i-fitingi/mufta-dvukhrastrubnaya-varmega-inox-press-'
                . mb_strtolower($normArticle) . '-' . $size . '/';
        }

        if (preg_match('/^VM70500(\d{2})(\d{2})$/u', $normArticle, $m)) {
            $thread = [
                '04' => '1-2',
                '05' => '3-4',
                '06' => '1',
                '07' => '1-1-4',
                '08' => '1-1-2',
                '09' => '2',
            ][$m[2]] ?? null;

            if ($thread !== null) {
                $size = ltrim($m[1], '0') . '-' . $thread;
                $url = 'https://varmega.ru/product/truby-i-fitingi/mufta-rastrubnaya-varmega-inox-press-s-vnutrenney-rezboy-'
                    . mb_strtolower($normArticle) . '-' . $size . '/';
            }
        }

        if ($url === null) {
            return null;
        }

        return ['url' => $url];
    }

    private function categoryIdForOfficialUrl(string $url, string $article): int
    {
        $forcedSlug = trim((string) $this->option('category-slug'));
        $slug = $forcedSlug !== '' ? $forcedSlug : $this->categorySlugForOfficialUrl($url, $article);

        if ($slug === '') {
            return 0;
        }

        return $this->ensureCategory($slug);
    }

    private function categorySlugForOfficialUrl(string $url, string $article): string
    {
        if (str_starts_with($article, 'VM355')) {
            return 'kollektornye-shkafy';
        }

        $path = parse_url($url, PHP_URL_PATH);
        $path = is_string($path) ? trim($path, '/') : '';

        if (str_starts_with($path, 'product/kollektory-i-komplektuyushchie')) {
            return 'raspredelitelnye-kollektory';
        }

        return '';
    }

    private function ensureCategory(string $slug): int
    {
        $categoryId = (int) (DB::table('categories')->where('slug', $slug)->value('id') ?? 0);
        if ($categoryId > 0) {
            return $categoryId;
        }

        $definitions = [
            'kollektornye-shkafy' => [
                'name' => 'Коллекторные шкафы',
                'parent_slug' => 'komplektuyushhie-dlya-otopleniya',
                'type' => 'catalog',
                'sort_order' => 185,
            ],
            'raspredelitelnye-kollektory' => [
                'name' => 'Распределительные коллекторы',
                'parent_slug' => 'komplektuyushhie-dlya-otopleniya',
                'type' => 'catalog',
                'sort_order' => 180,
            ],
        ];

        if (! isset($definitions[$slug])) {
            return 0;
        }

        $definition = $definitions[$slug];
        $now = now();
        $parentId = (int) (DB::table('categories')->where('slug', $definition['parent_slug'])->value('id') ?? 0);

        $data = [
            'name' => $definition['name'],
            'slug' => $slug,
            'parent_id' => $parentId,
            'sort_order' => $definition['sort_order'],
            'created_at' => $now,
            'updated_at' => $now,
        ];

        foreach (['h1' => $definition['name'], 'type' => $definition['type'], 'is_active' => true] as $column => $value) {
            if (Schema::hasColumn('categories', $column)) {
                $data[$column] = $value;
            }
        }

        return (int) DB::table('categories')->insertGetId($data);
    }

    private function loadRnProfiSectionIndex(): array
    {
        $sectionUrl = trim((string) $this->option('rn-profi-section-url'));
        if ($sectionUrl === '') {
            return [];
        }

        $html = $this->fetch($sectionUrl);
        if ($html === null) {
            return [];
        }

        $urls = $this->extractRnProfiProductUrls($html);
        $maxPages = max(1, (int) $this->option('rn-profi-section-pages'));
        $index = [];
        $fetched = 0;

        foreach (array_slice($urls, 0, $maxPages) as $url) {
            $page = $this->fetch($url);
            if ($page === null) {
                continue;
            }

            $fetched++;
            foreach ($this->extractVisibleArticleTokens($page) as $token) {
                if (! str_starts_with($token, 'VM') || mb_strlen($token) < 5) {
                    continue;
                }
                $index[$token] = ['url' => $url];
            }

            if ($fetched % 20 === 0) {
                $this->line(sprintf('RN-Profi section index progress: fetched=%d indexed=%d.', $fetched, count($index)));
            }
        }

        $this->line(sprintf('RN-Profi section index fetched=%d pages.', $fetched));

        return $index;
    }

    /**
     * @return string[]
     */
    private function extractRnProfiProductUrls(string $html): array
    {
        preg_match_all('/href=["\']([^"\']+)["\']/i', $html, $matches);
        $urls = [];
        foreach ($matches[1] ?? [] as $href) {
            $url = html_entity_decode((string) $href, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($url === '' || str_starts_with($url, '#') || str_starts_with($url, 'javascript:')) {
                continue;
            }
            if (str_starts_with($url, '/')) {
                $url = 'https://rn-profi.by' . $url;
            }
            if (! str_starts_with($url, 'https://rn-profi.by/')) {
                continue;
            }
            if (! $this->rnProfiUrlLooksLikeProduct($url)) {
                continue;
            }
            $urls[] = strtok($url, '#') ?: $url;
        }

        return array_values(array_unique($urls));
    }

    private function rnProfiUrlLooksLikeProduct(string $url): bool
    {
        if (str_contains($url, 'route=product/product')) {
            return true;
        }

        $path = trim((string) (parse_url($url, PHP_URL_PATH) ?: ''), '/');
        if (str_starts_with($path, 'varmega/') && mb_strlen($path) > mb_strlen('varmega/')) {
            return true;
        }

        return $path !== ''
            && ! str_contains($path, '/')
            && ! in_array($path, [
                'about_us', 'payment', 'delivery', 'contact-us', 'brands',
                'search', 'compare-products', 'wishlist', 'my-account', 'cart', 'checkout',
                'kontakty', 'oplata', 'sitemap', 'proekt', 'servis',
            ], true);
    }

    private function pageContainsArticle(string $html, string $normArticle): bool
    {
        if ($normArticle === '') {
            return false;
        }

        $body = preg_replace('/<head\b[^>]*>.*?<\/head>/is', ' ', $html) ?? $html;
        $body = preg_replace('/<(script|style|noscript)\b[^>]*>.*?<\/\1>/is', ' ', $body) ?? $body;

        return str_contains($this->normArticle(strip_tags($body)), $normArticle);
    }

    /**
     * @return string[]
     */
    private function extractVisibleArticleTokens(string $html): array
    {
        $body = preg_replace('/<head\b[^>]*>.*?<\/head>/is', ' ', $html) ?? $html;
        $body = preg_replace('/<(script|style|noscript)\b[^>]*>.*?<\/\1>/is', ' ', $body) ?? $body;
        $text = html_entity_decode(strip_tags($body), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $tokens = [];

        preg_match_all('/\bVM[A-Z0-9\-\/\.]{3,}\b/iu', $text, $matches);
        foreach ($matches[0] ?? [] as $token) {
            $norm = $this->normArticle((string) $token);
            if (mb_strlen($norm) >= 5) {
                $tokens[$norm] = true;
            }
        }

        return array_values(array_keys($tokens));
    }

    /**
     * @return string[]
     */
    private function extractArticleTokensFromUrl(string $url): array
    {
        $path = (string) (parse_url($url, PHP_URL_PATH) ?: '');
        $slug = mb_strtolower(trim(basename(trim($path, '/'))));
        $tokens = [];

        preg_match_all('/vm[a-z0-9]*\d[a-z0-9]*/iu', $slug, $matches);
        foreach ($matches[0] ?? [] as $token) {
            $norm = $this->normArticle((string) $token);
            if ($norm !== '') {
                $tokens[$norm] = true;
            }
        }

        $parts = array_values(array_filter(preg_split('/[-_]+/u', $slug) ?: []));
        foreach ($parts as $i => $part) {
            if (! preg_match('/^vm[a-z0-9]*\d[a-z0-9]*$/iu', $part)) {
                continue;
            }

            $combined = $part;
            for ($j = $i + 1; $j < min(count($parts), $i + 8); $j++) {
                $next = (string) $parts[$j];
                if ($next === '' || ! preg_match('/^[a-z0-9]+$/iu', $next)) {
                    break;
                }
                $combined .= $next;
                $norm = $this->normArticle($combined);
                if (mb_strlen($norm) >= 5 && preg_match('/\d/', $norm)) {
                    $tokens[$norm] = true;
                }
            }
        }

        return array_values(array_keys($tokens));
    }

    private function normArticle(string $article): string
    {
        $normalized = mb_strtoupper(preg_replace('/[^A-Za-z0-9]+/u', '', $article) ?? '');

        // RN-Profi keeps collector cabinet series in the same cell, e.g. VM35504ШРВ4.
        // For official Varmega matching the real article is the first VM355xx token.
        if (preg_match('/^(VM355\d{2})/u', $normalized, $m)) {
            return $m[1];
        }

        return $normalized;
    }
}
