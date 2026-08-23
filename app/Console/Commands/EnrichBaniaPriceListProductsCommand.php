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
        {--brand= : Filter by brand name fragment}
        {--supplier=bania : supplier_products.supplier_id code to scope the product query to, e.g. maitek-group}
        {--source-domain=bania.by : Allowed source domain to search, e.g. pech-aston.ru}
        {--source-url= : Start from one or more comma-separated allowed catalog URLs instead of Serper}
        {--crawl-limit=160 : Max source pages to crawl when --source-url is used}
        {--force-images : Replace existing images}
        {--force-content : Replace existing content}
        {--skip-images : Do not download images}
        {--skip-content : Do not update content}
        {--missing-images-only : Process only products without any gallery images}
        {--debug-images : Print candidate image URLs for every matched page}
        {--sleep=300 : Delay between products in milliseconds}';

    protected $description = 'Enrich BANIA products created from price-list rows by finding source pages via Serper.';

    private const SUPPLIER_CODE = 'bania';
    private const IMAGE_DIR = 'img/products/bania';
    private const SERPER_URL = 'https://google.serper.dev/search';
    private const ALLOWED_SOURCE_DOMAINS = [
        'bania.by',
        'pech-aston.ru',
        'aston-pech.ru',
        'pechi.by',
        'fornaks.ru',
        'derdomus.com',
        'doorwood.ru',
        'doorwood.net',
        'nefrit.by',
        'novmk.ru',
        'tmf-shop.ru',
        'vezuviy.su',
        'teplodar.ru',
        'prosept.ru',
        'harvia.com',
        'termofor.com',
        'ermak-pech.ru',
        'sten.ru',
    ];

    private AiContentEnricher $ai;
    private string $sourceDomain = 'bania.by';
    private string $sourceStartUrl = '';
    private array $sourceStartUrls = [];
    private array $sourceStartPaths = [];
    private ?array $sourceCatalogLinks = null;

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
        $this->sourceDomain = $this->normalizeSourceDomain((string) $this->option('source-domain'));
        try {
            $this->sourceStartUrls = $this->normalizeSourceUrls((string) $this->option('source-url'));
            $this->sourceStartUrl = $this->sourceStartUrls[0] ?? '';
            $this->sourceStartPaths = array_values(array_unique(array_map(
                fn ($url) => trim((string) parse_url($url, PHP_URL_PATH), '/'),
                $this->sourceStartUrls
            )));
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->line($dryRun
            ? '<fg=yellow;options=bold>DRY RUN: database will not be changed.</>'
            : '<fg=red;options=bold>APPLY: products can be updated.</>');

        if (! in_array($this->sourceDomain, self::ALLOWED_SOURCE_DOMAINS, true)) {
            $this->error('Source domain is not allowed: ' . $this->sourceDomain);
            $this->line('Allowed: ' . implode(', ', self::ALLOWED_SOURCE_DOMAINS));
            return self::FAILURE;
        }

        $this->info('Source domain: ' . $this->sourceDomain);
        if ($this->sourceStartUrl !== '') {
            foreach ($this->sourceStartUrls as $sourceUrl) {
                $this->info('Source URL: ' . $sourceUrl);
            }
        } elseif ($apiKey === '') {
            $this->error('SERPER_API_KEY is not configured in .env. Pass --source-url to crawl a known catalog directly.');
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
                if ($result === null) {
                    $this->warn('  no source page found');
                    $this->stats['images_missing']++;
                    $this->stats['content_missing']++;
                    $this->sleep();
                    continue;
                }

                $this->stats['matched_page']++;
                $this->line('  source: ' . $result['url']);
                if ($this->option('debug-images')) {
                    foreach (($result['images'] ?? []) as $imageUrl) {
                        $this->line('  image: ' . $imageUrl);
                    }
                }

                $updates = ['updated_at' => now()];
                $supplierUpdates = ['updated_at' => now()];
                $supplierUpdates['source_url'] = $result['url'];

                $raw = json_decode((string) ($product->supplier_raw ?? ''), true);
                if (! is_array($raw)) {
                    $raw = [];
                }
                $raw['enrichment'] = [
                    'provider' => $this->sourceStartUrl !== '' ? 'catalog' : 'serper',
                    'source_url' => $result['url'],
                    'images_found' => count($result['images'] ?? []),
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
        $supplierCode = trim((string) $this->option('supplier')) ?: self::SUPPLIER_CODE;
        $supplierId   = (int) DB::table('suppliers')->where('code', $supplierCode)->value('id');
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

        if ($brand = trim((string) $this->option('brand'))) {
            $needle = '%' . mb_strtolower($brand) . '%';
            $query->where(function ($q) use ($needle) {
                $q->whereRaw('LOWER(b.name) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(p.name) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(sp.supplier_name) LIKE ?', [$needle]);
            });
        }

        if ($this->option('missing-images-only')) {
            $query->where(function ($q) {
                $q->whereNull('p.images')
                    ->orWhere('p.images', '')
                    ->orWhere('p.images', '[]')
                    ->orWhereRaw('JSON_LENGTH(p.images) = 0');
            });
        }

        $limit = max(1, (int) $this->option('limit'));
        $offset = max(0, (int) $this->option('offset'));

        return $query->offset($offset)->limit($limit)->get();
    }

    private function findSourcePage(string $apiKey, object $product): ?array
    {
        foreach ($this->sourceOverrides($product) as $override) {
            $result = $this->sourceResultFromUrl($override, $product);
            if ($result !== null) {
                return $result;
            }
        }

        if ($this->sourceStartUrl !== '') {
            return $this->findSourcePageInCatalog($product);
        }

        $query = 'site:' . $this->sourceDomain . ' ' . $this->searchText($product);
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
            ->filter(fn ($url) => is_string($url) && $this->urlMatchesSourceDomain($url))
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
                'images' => $this->extractImages($html, $url, $product, $this->canUseGenericProductImages($pageTitle, $product)),
            ];
        }

        return $this->findSourcePageInCatalog($product);
    }

    private function sourceOverrides(object $product): array
    {
        $sku = (string) ($product->sku ?? '');
        $sibirUrlsBySku = [
            'KOTLOV-004651' => 'https://novmk.ru/product/bannie-pechi/pech-bannaya-chugunnaya-sibir-15-chugunnaya-dvertsa-s-vtk-setka/',
            'KOTLOV-004652' => 'https://novmk.ru/product/bannie-pechi/pech-bannaya-chugunnaya-sibir-20-panoramnaya-dvertsa-setka/',
            'KOTLOV-004653' => 'https://novmk.ru/product/bannie-pechi/pech-bannaya-chugunnaya-sibir-20-universalnaya-chugunnaya-dvertsa-setka/',
            'KOTLOV-004654' => 'https://novmk.ru/product/bannie-pechi/pech-bannaya-chugunnaya-sibir-22-pro-panoramnaya-dvertsa-setka/',
            'KOTLOV-004655' => 'https://novmk.ru/product/bannie-pechi/pech-bannaya-chugunnaya-sibir-24-pro-panoramnaya-dvertsa-setka/',
            'KOTLOV-004656' => 'https://novmk.ru/product/bannie-pechi/pech-bannaya-chugunnaya-sibir-24-panoramnaya-dvertsa-setka/',
            'KOTLOV-004657' => 'https://novmk.ru/product/bannie-pechi/pech-bannaya-chugunnaya-sibir-24-universalnaya-chugunnaya-dvertsa-setka/',
        ];

        if (isset($sibirUrlsBySku[$sku])) {
            return [$sibirUrlsBySku[$sku]];
        }

        $name = $this->normalize((string) ($product->supplier_name ?: $product->name));
        if (! str_contains($name, 'aston')) {
            return [];
        }

        $rules = [
            [
                'tokens' => ['стекло', '170', '220'],
                'url' => 'https://vezuviy.su/pechnye-dveri-i-aksessuary/zharoprochnye-stekla-dlya-dveri-pechi/steklo-aston-170220/',
            ],
            [
                'tokens' => ['24', 'inox', '310', 'long'],
                'url' => 'https://fornaks.ru/catalog/pechi-dlya-ban-i-saun/drovyanaya-pech-dlya-bani-aston-24-inox-310m-long/',
            ],
            [
                'tokens' => ['20', 'стекло'],
                'without' => ['шторм', 'long', 'дт'],
                'urls' => [
                    'https://pech-aston.ru/pech-dlya-bani-aston-20-inox-steklo',
                    'https://derdomus.com/product/pech-aston/',
                ],
            ],
            [
                'tokens' => ['шторм', '20', 'long', '350'],
                'url' => 'https://pech-aston.ru/pech-dlya-bani-aston-shtorm-20-long-350',
            ],
            [
                'tokens' => ['шторм', '20', '350'],
                'without' => ['long'],
                'url' => 'https://pechi.by/katalog/aston-pech-cast/pech-dlya-bani-aston-shtorm-chugun',
            ],
            [
                'tokens' => ['шторм', '16', 'дт', '4'],
                'url' => 'https://pech-aston.ru/pech-dlya-bani-aston-shtorm-16-dt-4s',
            ],
        ];

        foreach ($rules as $rule) {
            if (! $this->containsAllTokens($name, $rule['tokens'] ?? [])) {
                continue;
            }

            if ($this->containsAnyToken($name, $rule['without'] ?? [])) {
                continue;
            }

            return $rule['urls'] ?? [$rule['url']];
        }

        return [];
    }

    private function sourceResultFromUrl(string $url, object $product): ?array
    {
        $originalDomain = $this->sourceDomain;
        $this->sourceDomain = $this->normalizeSourceDomain((string) parse_url($url, PHP_URL_HOST));

        try {
            if (! in_array($this->sourceDomain, self::ALLOWED_SOURCE_DOMAINS, true)) {
                return null;
            }

            $html = $this->fetch($url);
            $pageTitle = $this->extractTitle($html);
            $images = $this->extractImages($html, $url, $product, $this->canUseGenericProductImages($pageTitle, $product));
            if ($images === []) {
                return null;
            }

            return [
                'url' => $url,
                'title' => $pageTitle,
                'description' => $this->extractDescription($html),
                'images' => $images,
            ];
        } finally {
            $this->sourceDomain = $originalDomain;
        }
    }

    private function containsAllTokens(string $value, array $tokens): bool
    {
        foreach ($tokens as $token) {
            if (! str_contains($value, $this->normalize($token))) {
                return false;
            }
        }

        return true;
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

        $pageText = $this->normalize(strip_tags($html));
        if (str_contains($pageText, 'фильтры товаров') && (str_contains($pageText, 'сортировать') || str_contains($pageText, 'на страницу'))) {
            return false;
        }

        if ($this->extractImages($html, $url, $product, $this->canUseGenericProductImages($pageTitle, $product)) === []) {
            return false;
        }

        $left = $this->normalize($pageTitle);
        $right = $this->normalize((string) ($product->supplier_name ?: $product->name));
        similar_text($left, $right, $percent);

        return $percent >= 70 || $this->isLikelyTitleMatch($pageTitle, $product);
    }

    private function isLikelyTitleMatch(string $candidateTitle, object $product): bool
    {
        $candidate = $this->normalize($candidateTitle);
        $productName = $this->normalize((string) ($product->supplier_name ?: $product->name));

        if ($candidate === '' || $productName === '') {
            return false;
        }

        if ($this->isDoorWoodProduct($productName)) {
            return $this->isLikelyDoorWoodTitleMatch($candidate, $productName);
        }

        if ($this->isSibirProduct($productName)) {
            return $this->isLikelySibirTitleMatch($candidate, $productName);
        }

        if ($this->hasVariantConflict($candidate, $productName)) {
            return false;
        }

        $productTokens = $this->specificTokens($productName);
        $candidateTokens = $this->specificTokens($candidate);
        if ($productTokens === [] || $candidateTokens === []) {
            return false;
        }

        $shared = array_values(array_intersect($productTokens, $candidateTokens));
        $productNumbers = array_values(array_filter($productTokens, fn ($token) => preg_match('/^\d+$/', $token) === 1));
        $candidateNumbers = array_values(array_filter($candidateTokens, fn ($token) => preg_match('/^\d+$/', $token) === 1));

        foreach ($productNumbers as $number) {
            if (! in_array($number, $candidateNumbers, true)) {
                return false;
            }
        }

        if (count($productTokens) <= 2) {
            return count($shared) === count($productTokens)
                && count(array_diff($candidateTokens, $productTokens)) === 0;
        }

        return count($shared) >= max(2, (int) ceil(count($productTokens) * 0.65));
    }

    private function isDoorWoodProduct(string $productName): bool
    {
        return str_contains($productName, 'doorwood');
    }

    private function canUseGenericProductImages(string $pageTitle, object $product): bool
    {
        $productName = $this->normalize((string) ($product->supplier_name ?: $product->name));
        $candidate = $this->normalize($pageTitle);

        return ($this->isDoorWoodProduct($productName) && $this->isLikelyDoorWoodTitleMatch($candidate, $productName))
            || ($this->isSibirProduct($productName) && $this->isLikelySibirTitleMatch($candidate, $productName));
    }

    private function isSibirProduct(string $productName): bool
    {
        return str_contains($productName, 'сибирь');
    }

    private function isLikelySibirTitleMatch(string $candidate, string $productName): bool
    {
        if (! str_contains($candidate, 'сибирь') || ! str_contains($productName, 'сибирь')) {
            return false;
        }

        $productModel = $this->firstModelNumber($productName);
        $candidateModel = $this->firstModelNumber($candidate);

        if ($productModel !== null) {
            return $candidateModel === $productModel;
        }

        return $candidateModel === null;
    }

    private function firstModelNumber(string $value): ?string
    {
        if (preg_match('/(?:сибирь|sibir)[^\d]*(\d{2})/iu', $value, $match)) {
            return $match[1];
        }

        return null;
    }

    private function isLikelyDoorWoodTitleMatch(string $candidate, string $productName): bool
    {
        if ($this->hasDoorWoodColorConflict($candidate, $productName)) {
            return false;
        }

        $productTokens = $this->doorWoodTokens($productName);
        $candidateTokens = $this->doorWoodTokens($candidate);
        if ($productTokens === [] || $candidateTokens === []) {
            return false;
        }

        $shared = array_values(array_intersect($productTokens, $candidateTokens));

        return count($shared) >= min(2, count($productTokens));
    }

    private function hasDoorWoodColorConflict(string $candidate, string $productName): bool
    {
        foreach ([
            ['бронза', 'bronz'],
            ['графит', 'grafit'],
            ['сатин', 'satin'],
            ['прозрач', 'prozrach'],
        ] as $group) {
            $candidateHas = $this->containsAnyToken($candidate, $group);
            $productHas = $this->containsAnyToken($productName, $group);

            if ($candidateHas && ! $productHas) {
                return true;
            }
        }

        return false;
    }

    private function doorWoodTokens(string $value): array
    {
        $tokens = $this->specificTokens($value);
        $stop = [
            'doorwood', 'door', 'wood', 'дверь', 'двери', 'двер', 'для', 'бани', 'сауны', 'сауна',
            'стекло', 'стеклянная', 'стеклянные', 'коробка', 'левая', 'лева', 'правая', 'права',
            'осина', 'липа', 'листва', 'мм', 'бронза', 'матовая', 'матовое', 'матовый',
        ];

        return array_values(array_filter($tokens, function ($token) use ($stop) {
            if (preg_match('/^\d+$/', $token) === 1 || in_array($token, $stop, true)) {
                return false;
            }

            return mb_strlen($token) >= 3;
        }));
    }

    private function hasVariantConflict(string $candidate, string $productName): bool
    {
        foreach ($this->variantGroups() as $group) {
            $candidateHas = $this->containsAnyToken($candidate, $group);
            $productHas = $this->containsAnyToken($productName, $group);

            if ($candidateHas && ! $productHas) {
                return true;
            }

            if ($productHas && ! $candidateHas) {
                return true;
            }
        }

        return false;
    }

    private function containsAnyToken(string $value, array $tokens): bool
    {
        foreach ($tokens as $token) {
            if (str_contains($value, $token)) {
                return true;
            }
        }

        return false;
    }

    private function variantGroups(): array
    {
        return [
            ['inox', 'инокс', 'нерж', 'нержав'],
            ['стекло', 'steklo'],
            ['шторм', 'shtorm'],
            ['дт', 'dt'],
            ['аква', 'akva'],
            ['long', 'лонг'],
        ];
    }

    private function specificTokens(string $value): array
    {
        $tokens = preg_split('/\s+/u', $this->normalize($value)) ?: [];
        $stop = [
            'для', 'бани', 'баня', 'сауны', 'сауна', 'печь', 'печи', 'печ', 'камин', 'камина',
            'aston', 'астон', 'шт', 'мм', 'см', 'м3', 'квт', 'диаметр', 'круглый',
            'dlya', 'bani', 'sauny', 'pech', 'kamin',
        ];

        $result = [];
        foreach ($tokens as $token) {
            $token = trim($token);
            if ($token === '' || in_array($token, $stop, true)) {
                continue;
            }
            if (preg_match('/^\d+$/', $token) === 1 || mb_strlen($token) >= 3) {
                $result[] = $token;
            }
        }

        return array_values(array_unique($result));
    }

    private function findSourcePageInCatalog(object $product): ?array
    {
        if ($this->sourceDomain === 'bania.by' && $this->sourceStartUrl === '') {
            return null;
        }

        $links = $this->sourceCatalogLinks();

        foreach ($links as $link) {
            $title = (string) ($link['title'] ?? '');
            if ($title === '' || ! $this->isLikelyTitleMatch($title, $product)) {
                continue;
            }

            try {
                $url = (string) $link['url'];
                $html = $this->fetch($url);
                $pageTitle = $this->extractTitle($html) ?: (string) $link['title'];

                if (! $this->isLikelyProductPage($html, $url, $pageTitle, $product)) {
                    continue;
                }

                return [
                    'url' => $url,
                    'title' => $pageTitle,
                    'description' => $this->extractDescription($html),
                    'images' => $this->extractImages($html, $url, $product, $this->canUseGenericProductImages($pageTitle, $product)),
                ];
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }

    private function sourceCatalogLinks(): array
    {
        if ($this->sourceCatalogLinks !== null) {
            return $this->sourceCatalogLinks;
        }

        $roots = $this->sourceStartUrls !== [] ? $this->sourceStartUrls : ['https://' . $this->sourceDomain . '/'];
        $queue = array_map(fn ($root) => [$root, 0], $roots);
        $seen = [];
        $links = [];

        $crawlLimit = max(20, (int) $this->option('crawl-limit'));

        while ($queue !== [] && count($seen) < $crawlLimit) {
            [$url, $depth] = array_shift($queue);
            $url = strtok((string) $url, '#') ?: (string) $url;
            if (isset($seen[$url]) || ! $this->urlMatchesSourceDomain($url)) {
                continue;
            }

            $seen[$url] = true;

            try {
                $html = $this->fetch($url);
            } catch (\Throwable) {
                continue;
            }

            $pageTitle = $this->extractTitle($html);
            if ($pageTitle !== '' && $this->isCatalogCandidate($url, $pageTitle)) {
                $links[$url] = [
                    'url' => $url,
                    'title' => $pageTitle,
                ];
            }

            foreach ($this->extractAnchors($html, $url) as $anchor) {
                $anchorUrl = (string) $anchor['url'];
                $anchorTitle = trim((string) $anchor['title']);
                if (! $this->urlMatchesSourceDomain($anchorUrl)) {
                    continue;
                }
                if (! $this->isInsideSourceStartPath($anchorUrl)) {
                    continue;
                }

                // Some catalog grids wrap product links around only an <img>
                // (no anchor text) — the real title lives on the product page
                // itself, extracted from <title> once it's fetched below. Don't
                // drop these links before they even get a chance to be queued;
                // isCatalogNavigationLink's path-based keyword match still
                // filters out irrelevant (nav/footer/social) links.
                if ($anchorTitle !== '' && $this->isCatalogCandidate($anchorUrl, $anchorTitle)) {
                    $links[$anchorUrl] = [
                        'url' => $anchorUrl,
                        'title' => $anchorTitle,
                    ];
                }

                if ($depth < 2 && $this->isCatalogNavigationLink($anchorUrl, $anchorTitle)) {
                    $queue[] = [$anchorUrl, $depth + 1];
                }
            }
        }

        $this->sourceCatalogLinks = array_values($links);

        return $this->sourceCatalogLinks;
    }

    private function extractAnchors(string $html, string $baseUrl): array
    {
        if (! preg_match_all('~<a\b[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)</a>~isu', $html, $matches, PREG_SET_ORDER)) {
            return [];
        }

        $anchors = [];
        foreach ($matches as $match) {
            $href = html_entity_decode((string) $match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($href === '' || str_starts_with($href, 'mailto:') || str_starts_with($href, 'tel:') || str_starts_with($href, 'javascript:')) {
                continue;
            }

            $title = $this->cleanText(strip_tags((string) $match[2]));
            $url = $this->absoluteUrl($href, $baseUrl);

            $anchors[] = [
                'url' => strtok($url, '#') ?: $url,
                'title' => $title,
            ];
        }

        return $anchors;
    }

    private function isInsideSourceStartPath(string $url): bool
    {
        if ($this->sourceStartPaths === [] || $this->sourceStartPaths === ['']) {
            return true;
        }

        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');

        if ($this->sourceDomain === 'doorwood.net' && str_starts_with($path, 'catalog/')) {
            return true;
        }

        // teplodar.ru's Bitrix catalog splits listing pages (catalog/section/...)
        // and product detail pages (catalog/detail/...) into disjoint URL
        // subtrees — a --source-url scoped to catalog/section/* would otherwise
        // exclude every real product page discovered while crawling it.
        if ($this->sourceDomain === 'teplodar.ru' && str_starts_with($path, 'catalog/detail/')) {
            return true;
        }

        foreach ($this->sourceStartPaths as $sourceStartPath) {
            if ($sourceStartPath === '' || $path === $sourceStartPath || str_starts_with($path, $sourceStartPath . '/')) {
                return true;
            }
        }

        return false;
    }

    private function isCatalogNavigationLink(string $url, string $title): bool
    {
        $text = $this->normalize($title . ' ' . (string) parse_url($url, PHP_URL_PATH));

        if (preg_match('~(?:contact|kontakty|diler|dealer|about|company|video|faq|privacy|politika|soglashenie|wishlist|compare|cart|login|search|articles|blog|news)~i', $text)) {
            return false;
        }

        // Bitrix-style filter-permutation URLs (/filter/attr-is-value/apply/) match
        // catalog keywords via their parent category path but explode the crawl
        // queue with dozens of near-duplicate junk pages — exclude them explicitly.
        if (str_contains((string) parse_url($url, PHP_URL_PATH), '/filter/')) {
            return false;
        }

        return preg_match('~(?:catalog|katalog|pech|bann|kamn|kamin|aston|doorwood|dver|aksess|prinad|tmf|termofor|vezuv|teplodar|prosept|harvia)~iu', $text) === 1;
    }

    private function isCatalogCandidate(string $url, string $title): bool
    {
        $text = $this->normalize($title . ' ' . (string) parse_url($url, PHP_URL_PATH));

        if (mb_strlen($title) < 8 || preg_match('~(?:главная|каталог|сбросить|сортировать|на страницу|фильтр|без сортировки|новинки выше|сначала)~iu', $title)) {
            return false;
        }

        // teplodar.ru accessory pages (баки, парогенераторы, задвижки, ...) live
        // under catalog/detail/* but their titles rarely contain a pech/kamin-type
        // keyword — isInsideSourceStartPath already scoped these correctly, so
        // skip the keyword filter for them rather than trying to keyword-list
        // every accessory category.
        $path = (string) parse_url($url, PHP_URL_PATH);
        if ($this->sourceDomain === 'teplodar.ru' && str_contains($path, '/catalog/detail/')) {
            return true;
        }

        return preg_match('~(?:pech|печ|kamin|камин|dver|двер|setka|сетка|stekl|стекл|tmf|aston|doorwood|vezuv|teplodar|prosept|harvia|ermak|aksess|kamenk|каменк|sten|kotl|котл)~iu', $text) === 1;
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

    private function extractImages(string $html, string $pageUrl, ?object $product = null, bool $allowGenericProductImages = false): array
    {
        $images = [];
        if (preg_match_all('~<img\b[^>]+>~iu', $html, $imageTags)) {
            foreach ($imageTags[0] as $tag) {
                $src = $this->tagAttribute($tag, 'data-large')
                    ?: $this->tagAttribute($tag, 'data-image-large')
                    ?: $this->tagAttribute($tag, 'data-zoom-image')
                    ?: $this->tagAttribute($tag, 'data-src')
                    ?: $this->tagAttribute($tag, 'src');

                if ($src === '' || ! preg_match('~\.(?:jpg|jpeg|png|webp)(?:\?[^"\']*)?$~i', $src)) {
                    continue;
                }

                $url = $this->normalizeImageUrl($this->absoluteUrl($src, $pageUrl));
                $context = trim($this->tagAttribute($tag, 'alt') . ' ' . $this->tagAttribute($tag, 'title') . ' ' . $url);
                if (! $this->isProductImage($url)) {
                    continue;
                }

                if (! $allowGenericProductImages && ! $this->imageMatchesProduct($url, $context, $product)) {
                    continue;
                }

                $images[] = $url;
            }
        }

        if (preg_match_all('~(?:href|src|data-src|data-large|data-image|data-image-large|data-image-thumb|data-zoom-image)=["\']([^"\']+\.(?:jpg|jpeg|png|webp)(?:\?[^"\']*)?)["\']~iu', $html, $matches)) {
            foreach ($matches[1] as $src) {
                $url = $this->normalizeImageUrl($this->absoluteUrl($src, $pageUrl));
                if (! $this->isProductImage($url)) {
                    continue;
                }

                if (! $allowGenericProductImages && ! $this->imageMatchesProduct($url, $url, $product)) {
                    continue;
                }
                $images[] = $url;
            }
        }

        return array_values(array_unique(array_slice($images, 0, 8)));
    }

    private function tagAttribute(string $tag, string $name): string
    {
        if (preg_match('~\b' . preg_quote($name, '~') . '=["\']([^"\']+)["\']~iu', $tag, $match)) {
            return html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return '';
    }

    private function imageMatchesProduct(string $url, string $context, ?object $product): bool
    {
        if ($product === null || $this->sourceDomain === 'bania.by') {
            return true;
        }

        $context = $this->normalize($context);
        $productName = $this->normalize((string) ($product->supplier_name ?: $product->name));
        if ($context === '' || $productName === '') {
            return false;
        }

        if ($this->isDoorWoodProduct($productName)) {
            return $this->isLikelyDoorWoodTitleMatch($context, $productName);
        }

        if ($this->isSibirProduct($productName)) {
            return $this->isLikelySibirTitleMatch($context, $productName);
        }

        if ($this->hasVariantConflict($context, $productName)) {
            return false;
        }

        $productTokens = $this->specificTokens($productName);
        $contextTokens = $this->specificTokens($context);
        $shared = array_intersect($productTokens, $contextTokens);

        return count($shared) >= max(1, min(2, count($productTokens)));
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
        if (! $this->urlMatchesSourceDomain($url)) {
            return false;
        }

        $path = strtolower((string) parse_url($url, PHP_URL_PATH));

        if (preg_match('~/(?:logo|icon|icons|payment|social|banner|manufacturer|brand|advantage|advantages|delivery|callback|review|reviews)/|(?:sprite|placeholder|telegram|viber|whatsapp|email|tel|stc|noimage|nophoto|watermark)~i', $path)) {
            return false;
        }

        if ($this->sourceDomain === 'bania.by') {
            return str_contains($url, '/image/catalog/');
        }

        return preg_match('~/(?:wa-data|upload|uploads|images|image|catalog|products|product|goods|items|photo|photos|userfls|iblock|resize_cache)/~i', $path) === 1;
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
        if ($size === false) {
            return false;
        }

        $width = (int) ($size[0] ?? 0);
        $height = (int) ($size[1] ?? 0);
        if ($width < 100 || $height < 100) {
            return false;
        }

        $ratio = $width / max(1, $height);

        return $ratio >= 0.45 && $ratio <= 2.2;
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

    private function normalizeSourceDomain(string $domain): string
    {
        $domain = trim(mb_strtolower($domain));
        if ($domain === '') {
            return 'bania.by';
        }

        if (str_starts_with($domain, 'http://') || str_starts_with($domain, 'https://')) {
            $domain = (string) parse_url($domain, PHP_URL_HOST);
        }

        return preg_replace('/^www\./', '', trim($domain, " \t\n\r\0\x0B/")) ?: 'bania.by';
    }

    private function normalizeSourceUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        if (! str_starts_with($url, 'http://') && ! str_starts_with($url, 'https://')) {
            $url = 'https://' . ltrim($url, '/');
        }

        $host = $this->normalizeSourceDomain((string) parse_url($url, PHP_URL_HOST));
        if ($host !== $this->sourceDomain) {
            throw new \InvalidArgumentException('Source URL host must match --source-domain.');
        }

        if (! $this->urlMatchesSourceDomain($url)) {
            throw new \InvalidArgumentException('Source URL is outside allowed source domain.');
        }

        // Some sites (sten.ru confirmed; doorwood.net suspected) 404 a category
        // path without its trailing slash — stripping it unconditionally caused
        // the very first fetch() to fail and the whole crawl to silently return
        // zero candidates near-instantly. Preserve whatever the caller passed.
        return $url === '/' ? $url : rtrim($url, '/') . (str_ends_with($url, '/') ? '/' : '');
    }

    private function normalizeSourceUrls(string $urls): array
    {
        $parts = preg_split('/[\s,]+/', trim($urls)) ?: [];
        $normalized = [];

        foreach ($parts as $url) {
            $url = trim($url);
            if ($url === '') {
                continue;
            }

            $normalized[] = $this->normalizeSourceUrl($url);
        }

        return array_values(array_unique($normalized));
    }

    private function urlMatchesSourceDomain(string $url): bool
    {
        $host = mb_strtolower((string) parse_url($url, PHP_URL_HOST));
        $host = preg_replace('/^www\./', '', $host) ?: '';

        return $host === $this->sourceDomain || str_ends_with($host, '.' . $this->sourceDomain);
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
