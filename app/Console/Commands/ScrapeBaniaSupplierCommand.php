<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\AiContentEnricher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ScrapeBaniaSupplierCommand extends Command
{
    protected $signature = 'supplier:scrape-bania
        {--dry-run : Preview without database writes}
        {--apply : Write supplier data and optionally create products}
        {--category-url=https://bania.by/vse-dlia-bani/drovjanye-pechi-dlja-bani : BANIA category URL}
        {--brands= : Comma-separated supplier/KOTLOV brand names to process}
        {--limit= : Process only the first N product cards}
        {--page= : Process only one discovered category page number}
        {--download-images : Download images for newly created products, or empty existing product galleries}
        {--replace-empty-images-only : Add BANIA images only when the matched product has no images}
        {--sync-characteristics : Save normalized BANIA technical characteristics when product fields are empty}
        {--sync-prices : Deprecated: BANIA prices are synced from the Google supplier price list}
        {--generate-descriptions : Generate unique descriptions for products without content}
        {--update-existing : Update matched products base price/content/images when safe}
        {--update-only : Do not create new products; send unmatched rows to manual review}
        {--create-unmatched : Create new products for unmatched rows even when a similar catalog title exists}
        {--only-in-stock : Skip out-of-stock BANIA rows}
        {--report : Deprecated; reports are written for every run}
        {--sleep=200 : Delay between detail requests in milliseconds}';

    protected $description = 'Scrape BANIA.by wood-fired sauna stoves and sync supplier prices, stock, mappings, images and reports.';

    private const SUPPLIER_CODE = 'bania';
    private const SITE_URL = 'https://bania.by';
    private const IMAGE_DIR = 'img/products/bania';
    private const DEFAULT_CATEGORY_URL = 'https://bania.by/vse-dlia-bani/drovjanye-pechi-dlja-bani';
    private const PRODUCTION_BRANDS = [
        'Везувий',
        'Теплодар',
        'TMF',
        'Термофор',
        'Everest',
        'Эверест',
        'Этна',
        'ЭТНА',
    ];
    private const CATEGORY_PROFILES = [
        'wood' => [
            'source_path' => 'drovjanye-pechi-dlja-bani',
            'sync_key' => 'bania_wood_sauna_stoves',
            'title' => 'BANIA.by: wood-fired sauna stoves',
            'description' => 'Scrapes BANIA.by wood-fired sauna stoves, prices, stock, photos and attributes.',
            'category_slugs' => [
                'drovyanye-pechi-dlya-bani',
                'pechi-dlya-bani',
                'dlya-bani',
                'bani-i-sauny',
            ],
        ],
        'electric' => [
            'source_path' => 'elektricheskie-pechi-dlja-bani',
            'sync_key' => 'bania_electric_sauna_heaters',
            'title' => 'BANIA.by: electric sauna heaters',
            'description' => 'Scrapes BANIA.by electric sauna heaters, prices, stock, photos and attributes.',
            'category_slugs' => [
                'elektrokamenki',
                'pechi-sauna',
                'pechi-kamenka',
                'pechi-dlya-bani',
                'bani-i-sauny',
            ],
        ],
    ];
    private const MODEL_TOKENS = [
        'aston',
        'black',
        'case',
        'cilindro',
        'classic',
        'classik',
        'crystal',
        'eco',
        'elite',
        'forta',
        'galaxy',
        'lava',
        'legend',
        'lite',
        'nova',
        'optima',
        'quadro',
        'rusic',
        'russich',
        'skif',
        'slim',
        'steam',
        'tetra',
        'trend',
        'vitruviya',
    ];
    private const SUPPLIER_BRAND_ALIASES = [
        'HARBIN' => ['harbin'],
        'ASTON' => ['aston'],
        'Эверест' => ['everest'],
        'ЭТНА' => ['ehtna', 'etna'],
        'Harvia' => ['harvia'],
        'KARINA' => ['karina'],
        'Ижкомцентр' => ['izhkomcentr', 'izkomcentr', 'egpp', 'parogenerator'],
        'TMF' => ['tmf', 'termo-for', 'termofor', 'termofor-tmf'],
        'Факел' => ['fakel'],
        'PROmetall' => ['prometall', 'pro-metall'],
        'Meta-Bel' => ['meta-bel'],
        'NMK' => ['nmk'],
        'Везувий' => ['vezuvij', 'vezuviy', 'vezuvii'],
        'Теплодар' => ['teplodar', 'siesta', 'bylina', 'sibirskij-utes', 'sibirskii-utes'],
    ];

    private array $brandCache = [];
    private array $brandNames = [];
    private array $reportRows = [];
    private array $manualRows = [];
    private array $priceRows = [];
    private array $runStats = [];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $dryRun = (bool) $this->option('dry-run') || ! $apply;
        $categoryUrl = $this->absoluteUrl((string) $this->option('category-url'));
        $allowedBrands = $this->parseBrandFilter((string) ($this->option('brands') ?? ''));
        $limit = $this->option('limit') !== null ? max(0, (int) $this->option('limit')) : null;
        $onlyPage = $this->option('page') !== null ? max(1, (int) $this->option('page')) : null;
        $onlyInStock = (bool) $this->option('only-in-stock');
        $downloadImages = (bool) $this->option('download-images');
        $syncCharacteristics = (bool) $this->option('sync-characteristics');
        $syncPrices = (bool) $this->option('sync-prices');
        $generateDescriptions = (bool) $this->option('generate-descriptions');
        $updateExisting = (bool) $this->option('update-existing');
        $this->runStats = [
            'images_downloaded' => 0,
            'image_products_updated' => 0,
            'image_products_skipped_existing' => 0,
            'image_download_errors' => 0,
            'characteristics_products_updated' => 0,
            'characteristics_values_saved' => 0,
            'products_without_characteristics' => 0,
            'price_synced' => 0,
            'price_skipped_out_of_stock' => 0,
            'price_skipped_empty_price' => 0,
            'price_skipped_manual_review' => 0,
            'price_skipped_no_product' => 0,
            'price_unchanged' => 0,
        ];

        $this->line($dryRun
            ? '<fg=yellow;options=bold>DRY RUN: database will not be changed.</>'
            : '<fg=red;options=bold>APPLY: database can be updated.</>');

        if ($syncPrices) {
            $this->warn('--sync-prices is disabled for supplier:scrape-bania: BANIA supplier cost and stock must come from the Google price list.');
            $this->warn('Use supplier:sync-bania-pricelist instead. Product retail prices are not updated by the scraper.');
            $syncPrices = false;
        }

        $enricher = new AiContentEnricher();
        if ($generateDescriptions && ! $enricher->isAvailable()) {
            $this->warn('--generate-descriptions: no AI provider configured, descriptions skipped.');
            $generateDescriptions = false;
        }

        try {
            $categoryPages = $this->discoverCategoryPages($categoryUrl);
            if ($onlyPage !== null) {
                $categoryPages = array_values(array_filter(
                    $categoryPages,
                    fn (array $page) => (int) $page['page'] === $onlyPage
                ));
            }

            $items = $this->scrapeCatalog($categoryPages);
        } catch (\Throwable $e) {
            $this->error('BANIA scrape failed: ' . $e->getMessage());
            return self::FAILURE;
        }

        if ($onlyInStock) {
            $items = array_values(array_filter($items, fn (array $item) => $item['in_stock']));
        }

        if ($limit !== null && $limit > 0) {
            $items = array_slice($items, 0, $limit);
        }

        $this->info(sprintf('Pages: %d, products collected: %d', count($categoryPages), count($items)));

        $this->loadBrands();
        $categoryId = $this->resolveCategoryId();
        if ($categoryId === null) {
            $this->error('Could not resolve a target category for BANIA products.');
            return self::FAILURE;
        }

        $now = now();
        $supplierId = $apply ? $this->ensureSupplier($now) : $this->previewSupplierId();
        $syncId = $apply ? $this->ensureSupplierSync($now) : $this->previewSyncId();

        $stats = [
            'matched_updated' => 0,
            'created' => 0,
            'manual_review' => 0,
            'skipped_duplicate' => 0,
            'skipped_out_of_stock' => 0,
            'skipped_brand' => 0,
            'create_candidate' => 0,
            'error' => 0,
        ];

        foreach ($items as $index => $listItem) {
            $this->line(sprintf('[%d/%d] %s', $index + 1, count($items), $listItem['url']));

            try {
                $detail = $this->scrapeProduct($listItem['url']);
                $item = $this->mergeItem($listItem, $detail);

                if ($onlyInStock && ! $item['in_stock']) {
                    $stats['skipped_out_of_stock']++;
                    $this->addReportRow($item, null, 'skipped_out_of_stock', 'filtered by --only-in-stock');
                    continue;
                }

                $item['brand_id'] = $this->resolveBrandId($item);
                $match = $this->matchProduct($item, $supplierId);
                if (! $this->brandAllowed($item, $match, $allowedBrands)) {
                    $stats['skipped_brand']++;
                    continue;
                }

                $action = $this->decideAction($item, $match);
                if ((bool) $this->option('update-only') && in_array($action, ['created', 'create_candidate'], true)) {
                    $action = 'manual_review';
                    $match['reason'] = 'creation disabled by --update-only';
                }
                if ((bool) $this->option('create-unmatched') && $action === 'manual_review' && ($match['product'] ?? null) === null) {
                    $action = $apply ? 'created' : 'create_candidate';
                    $match['reason'] = 'unmatched row allowed by --create-unmatched';
                }
                if (in_array($action, ['created', 'create_candidate'], true) && $item['brand_id'] === null) {
                    $action = 'manual_review';
                    $match['reason'] = 'missing brand for new product';
                }

                if ($action === 'manual_review') {
                    $stats['manual_review']++;
                    $reason = $match['reason'] ?? 'possible duplicate';
                    $this->addReportRow($item, $match, 'manual_review', $reason);
                    if ($syncPrices) {
                        $this->recordPriceSync($item, $match, $supplierId, $dryRun, $now, 'manual_review');
                    }
                    $this->addManualRow($item, $match, $reason);
                    $this->warn('  manual review: ' . $reason);
                    continue;
                }

                if ($dryRun) {
                    $stats[$action] = ($stats[$action] ?? 0) + 1;
                    $this->addReportRow($item, $match, $action, '');
                    if ($syncPrices) {
                        $this->recordPriceSync($item, $match, $supplierId, true, $now, $action);
                    }
                    $this->line('  action: ' . $action);
                    continue;
                }

                if ($action === 'matched_updated') {
                    $productId = (int) $match['product']->id;
                    $this->upsertSupplierProduct($item, $productId, (string) $match['product']->sku, $supplierId, $syncId, $now);
                    if ($updateExisting) {
                        $this->updateExistingProduct($item, $match['product'], $downloadImages, $generateDescriptions, $enricher, $now);
                    } elseif ($downloadImages && (bool) $this->option('replace-empty-images-only')) {
                        $this->fillEmptyImages($item, $match['product'], $now);
                    }
                    if ($syncCharacteristics) {
                        $this->syncProductCharacteristics($productId, $item, $now);
                    }
                    $this->refreshProductAvailability($productId, $now);
                    if ($syncPrices) {
                        $this->recordPriceSync($item, $match, $supplierId, false, $now, $action);
                    }
                    $stats['matched_updated']++;
                } elseif ($action === 'created') {
                    $productId = $this->createProduct($item, $categoryId, $downloadImages, $generateDescriptions, $enricher, $now);
                    $productSku = (string) DB::table('products')->where('id', $productId)->value('sku');
                    $this->upsertSupplierProduct($item, $productId, $productSku, $supplierId, $syncId, $now);
                    if ($syncCharacteristics) {
                        $this->syncProductCharacteristics($productId, $item, $now);
                    }
                    $this->refreshProductAvailability($productId, $now);
                    if ($syncPrices) {
                        $createdProduct = DB::table('products')->where('id', $productId)->first();
                        $this->recordPriceSync($item, ['product' => $createdProduct, 'type' => 'created', 'confidence' => 100], $supplierId, false, $now, $action);
                    }
                    $stats['created']++;
                } elseif ($action === 'skipped_duplicate') {
                    $stats['skipped_duplicate']++;
                } elseif ($action === 'skipped_out_of_stock') {
                    $stats['skipped_out_of_stock']++;
                } else {
                    $stats['create_candidate']++;
                }

                $this->addReportRow($item, $match, $action, '');
            } catch (\Throwable $e) {
                $stats['error']++;
                $this->warn('  error: ' . $e->getMessage());
                $this->addReportRow($listItem, null, 'error', $e->getMessage());
            }

            usleep(max(0, (int) $this->option('sleep')) * 1000);
        }

        $this->writeReports();

        $this->table(['metric', 'count'], array_map(
            fn (string $key, int $value) => [$key, $value],
            array_keys($stats + $this->runStats),
            array_values($stats + $this->runStats)
        ));

        return $stats['error'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function discoverCategoryPages(string $categoryUrl): array
    {
        $firstHtml = $this->fetch($categoryUrl);
        $pages = [1 => ['page' => 1, 'url' => $categoryUrl, 'html' => $firstHtml]];

        foreach ($this->extractLinks($firstHtml) as $url) {
            if (! str_starts_with($url, $categoryUrl) && ! str_contains($url, parse_url($categoryUrl, PHP_URL_PATH) ?: '')) {
                continue;
            }

            $pageNumber = $this->pageNumberFromUrl($url);
            if ($pageNumber !== null) {
                $pages[$pageNumber] = ['page' => $pageNumber, 'url' => $url, 'html' => null];
            }
        }

        for ($page = 2; $page <= 30; $page++) {
            if (isset($pages[$page])) {
                continue;
            }

            $guess = $categoryUrl . (str_contains($categoryUrl, '?') ? '&' : '?') . 'page=' . $page;
            try {
                $html = $this->fetch($guess);
            } catch (\Throwable) {
                break;
            }

            $links = $this->productLinksFromHtml($html, $categoryUrl);
            if ($links === []) {
                break;
            }

            $pages[$page] = ['page' => $page, 'url' => $guess, 'html' => $html];
        }

        ksort($pages);

        return array_values($pages);
    }

    private function scrapeCatalog(array $categoryPages): array
    {
        $items = [];
        $seen = [];

        foreach ($categoryPages as $page) {
            $html = $page['html'] ?? $this->fetch($page['url']);
            foreach ($this->productLinksFromHtml($html, $this->absoluteUrl((string) $this->option('category-url'))) as $url => $title) {
                if (isset($seen[$url])) {
                    continue;
                }

                $seen[$url] = true;
                $node = $this->nearLinkHtml($html, $url);
                $items[] = [
                    'title' => $title,
                    'url' => $url,
                    'price' => $this->parseCatalogPrice($node, $title),
                    'stock_text' => $this->parseStockText($node),
                    'in_stock' => $this->parseStockStatus($node),
                    'preview_image' => $this->firstImageFromHtml($node),
                    'page' => (int) $page['page'],
                    'source_category' => (string) $this->option('category-url'),
                ];
            }
        }

        return $items;
    }

    private function scrapeProduct(string $url): array
    {
        $html = $this->fetch($url);
        $title = $this->cleanText($this->firstXPathText($html, '//h1') ?: '');
        $metaTitle = $this->cleanText($this->match('/<title[^>]*>([\s\S]*?)<\/title>/iu', $html) ?? '');
        $metaDescription = $this->cleanText($this->match('/<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']*)/iu', $html) ?? '');

        $attributes = $this->parseAttributes($html);
        $description = $this->extractDescriptionHtml($html);
        $images = $this->extractImages($html, $url);

        $sku = $this->extractSku($html, $attributes);
        $brand = $this->extractBrand($html, $attributes, $title);
        $stockText = $this->parseStockText($html);

        return [
            'title' => $title,
            'brand' => $brand,
            'sku' => $sku,
            'price' => $this->parsePrice($html),
            'stock_text' => $stockText,
            'in_stock' => $this->parseStockStatus($stockText . ' ' . $html),
            'description' => $description,
            'attributes' => $attributes,
            'images' => $images,
            'breadcrumbs' => $this->parseBreadcrumbs($html),
            'meta_title' => $metaTitle,
            'meta_description' => $metaDescription,
        ];
    }

    private function mergeItem(array $listItem, array $detail): array
    {
        $title = $detail['title'] ?: $listItem['title'];
        $price = $detail['price'] ?? $listItem['price'];
        $stockText = $detail['stock_text'] ?: $listItem['stock_text'];
        $stockStatus = $this->stockStatusFromText($stockText);
        $inStock = $detail['in_stock'] || $listItem['in_stock'];
        if ($stockStatus === 'in_stock') {
            $inStock = true;
        }
        if ($stockStatus === 'out_of_stock') {
            $inStock = false;
        }
        if ($stockStatus === 'unknown' && $inStock) {
            $stockStatus = 'in_stock';
        }
        $images = $detail['images'] ?: array_values(array_filter([$listItem['preview_image'] ?? null]));

        return [
            'title' => $this->cleanText($title),
            'normalized_title' => $this->normalizeTitle($title),
            'brand' => $this->canonicalBrand((string) ($detail['brand'] ?? ''), $title, (string) $listItem['url']),
            'brand_id' => null,
            'sku' => $this->normalizeArticle($detail['sku'] ?? ''),
            'url' => $listItem['url'],
            'price' => $price,
            'currency' => 'BYN',
            'price_byn' => $price,
            'stock_text' => $stockText,
            'stock_status' => $stockStatus,
            'in_stock' => $inStock,
            'description' => $detail['description'] ?? null,
            'attributes' => $detail['attributes'] ?? [],
            'images' => $images,
            'breadcrumbs' => $detail['breadcrumbs'] ?? [],
            'page' => $listItem['page'] ?? null,
            'source_category' => $listItem['source_category'] ?? (string) $this->option('category-url'),
            'meta_title' => $detail['meta_title'] ?? '',
            'meta_description' => $detail['meta_description'] ?? '',
        ];
    }

    private function matchProduct(array $item, ?int $supplierId): array
    {
        if ($supplierId) {
            $supplierProduct = DB::table('supplier_products')
                ->where('supplier_id', $supplierId)
                ->where('source_url', $item['url'])
                ->whereNotNull('product_id')
                ->first();

            if ($supplierProduct) {
                $product = DB::table('products')->where('id', $supplierProduct->product_id)->first();
                if ($product) {
                    if (($supplierProduct->source_url ?? null) !== ($item['url'] ?? null)) {
                        return [
                            'product' => $product,
                            'type' => 'supplier_product_conflict',
                            'confidence' => 89.0,
                            'reason' => 'same supplier article is already mapped to another source',
                        ];
                    }

                    return ['product' => $product, 'type' => 'supplier_product', 'confidence' => 100, 'reason' => 'same supplier_product'];
                }
            }

            if ($this->isReliableSupplierArticle($item['sku'])) {
                $supplierProduct = DB::table('supplier_products')
                    ->where('supplier_id', $supplierId)
                    ->where(function ($query) use ($item) {
                        $query->where('supplier_article', $item['sku'])
                            ->orWhere('supplier_article_normalized', $item['sku']);
                    })
                    ->whereNotNull('product_id')
                    ->first();

                if ($supplierProduct) {
                    $product = DB::table('products')->where('id', $supplierProduct->product_id)->first();
                    if ($product) {
                        if (($supplierProduct->source_url ?? null) !== ($item['url'] ?? null)) {
                            return [
                                'product' => $product,
                                'type' => 'supplier_product_conflict',
                                'confidence' => 89.0,
                                'reason' => 'same supplier article is already mapped to another source',
                            ];
                        }

                        return ['product' => $product, 'type' => 'supplier_product', 'confidence' => 100, 'reason' => 'same supplier_product'];
                    }
                }
            }
        }

        if ($this->isReliableSupplierArticle($item['sku'])) {
            $product = DB::table('products')
                ->where(function ($query) use ($item) {
                    $query->where('sku', $item['sku'])
                        ->orWhere('sku', 'like', '%' . $item['sku'] . '%');
                })
                ->first();
            if ($product) {
                if ($this->hasDifferentSupplierProductMapping($product, $item, $supplierId)) {
                    return [
                        'product' => $product,
                        'type' => 'supplier_product_conflict',
                        'confidence' => 89.0,
                        'reason' => 'same supplier already mapped this product to another source',
                    ];
                }

                return ['product' => $product, 'type' => 'sku', 'confidence' => 95, 'reason' => 'same sku/article'];
            }
        }

        $query = DB::table('products')->where('is_archived', false);
        if ($item['brand_id'] !== null) {
            $query->where('brand_id', $item['brand_id']);
        }

        $candidates = $query
            ->whereIn('category_id', $this->candidateCategoryIds())
            ->get(['id', 'sku', 'name', 'brand_id', 'price', 'images', 'content', 'short_description']);

        $best = null;
        $bestScore = 0.0;
        foreach ($candidates as $candidate) {
            $candidateTitle = $this->normalizeTitle($candidate->name);
            if (! $this->titlesAreCompatible($item['normalized_title'], $candidateTitle)) {
                continue;
            }

            $score = $this->similarity($item['normalized_title'], $candidateTitle);
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $candidate;
            }
        }

        if ($best && $bestScore >= 92.0) {
            if ($this->isDistinctKarinaVariant($item, $best)) {
                return ['product' => null, 'type' => 'none', 'confidence' => 0, 'reason' => 'distinct KARINA variant'];
            }

            if ($this->hasDifferentSupplierProductMapping($best, $item, $supplierId)) {
                return [
                    'product' => $best,
                    'type' => 'supplier_product_conflict',
                    'confidence' => min($bestScore, 89.0),
                    'reason' => 'same supplier already mapped this product to another source',
                ];
            }

            return ['product' => $best, 'type' => 'name_brand', 'confidence' => $bestScore, 'reason' => 'high title similarity'];
        }

        if ($best && $bestScore >= 78.0) {
            if ($this->isApprovedEquivalentMatch($item, $best)) {
                return ['product' => $best, 'type' => 'approved_equivalent', 'confidence' => 95, 'reason' => 'approved equivalent'];
            }

            if ($this->isDistinctKarinaVariant($item, $best)) {
                return ['product' => null, 'type' => 'none', 'confidence' => 0, 'reason' => 'distinct KARINA variant'];
            }

            if ($this->hasDifferentSupplierProductMapping($best, $item, $supplierId)) {
                return [
                    'product' => $best,
                    'type' => 'supplier_product_conflict',
                    'confidence' => min($bestScore, 89.0),
                    'reason' => 'same supplier already mapped this product to another source',
                ];
            }

            return ['product' => $best, 'type' => 'fuzzy', 'confidence' => $bestScore, 'reason' => 'similar title'];
        }

        return ['product' => null, 'type' => 'none', 'confidence' => 0, 'reason' => 'no match'];
    }

    private function isApprovedEquivalentMatch(array $item, object $product): bool
    {
        $supplierTitle = $this->normalizeTitle((string) ($item['title'] ?? ''));
        $productTitle = $this->normalizeTitle((string) ($product->name ?? ''));

        if (! str_contains($supplierTitle, 'harvia') || ! str_contains($productTitle, 'harvia')) {
            return false;
        }

        foreach (['pc70', 'pc90'] as $model) {
            if (str_contains($supplierTitle, $model) && str_contains($productTitle, $model)) {
                return true;
            }
        }

        return false;
    }

    private function isDistinctKarinaVariant(array $item, object $product): bool
    {
        $supplierTitle = $this->normalizeTitle((string) ($item['title'] ?? ''));
        $productTitle = $this->normalizeTitle((string) ($product->name ?? ''));

        if (! str_contains($supplierTitle, 'karina') || ! str_contains($productTitle, 'karina')) {
            return false;
        }

        $supplierVariant = $this->karinaVariantTokens($supplierTitle);
        $productVariant = $this->karinaVariantTokens($productTitle);

        foreach (array_unique(array_merge(array_keys($supplierVariant), array_keys($productVariant))) as $variant) {
            if (($supplierVariant[$variant] ?? false) !== ($productVariant[$variant] ?? false)) {
                return true;
            }
        }

        return false;
    }

    private function karinaVariantTokens(string $title): array
    {
        return [
            'snake' => str_contains($title, 'zmeevik') || str_contains($title, 'змеевик'),
            'soapstone' => str_contains($title, 'talkohlorit') || str_contains($title, 'талькохлорит'),
            'mini' => str_contains($title, 'mini') || str_contains($title, 'мини'),
            'wood' => str_contains($title, 'wood') || str_contains($title, 'дерево'),
            'white' => str_contains($title, 'white') || str_contains($title, 'бел'),
        ];
    }

    private function hasDifferentSupplierProductMapping(object $product, array $item, ?int $supplierId): bool
    {
        if (! $supplierId || ! isset($product->id)) {
            return false;
        }

        return DB::table('supplier_products')
            ->where('supplier_id', $supplierId)
            ->where('product_id', $product->id)
            ->where(function ($query) use ($item) {
                $query->where('source_url', '<>', $item['url']);
                if ($item['sku'] !== '') {
                    $query->orWhere('supplier_article', '<>', $item['sku']);
                }
            })
            ->exists();
    }

    private function decideAction(array $item, array $match): string
    {
        if (! $item['in_stock'] && (bool) $this->option('only-in-stock')) {
            return 'skipped_out_of_stock';
        }

        if ($match['product'] !== null && $match['confidence'] >= 90) {
            return 'matched_updated';
        }

        if ($match['product'] !== null) {
            return 'manual_review';
        }

        if ($this->looksLikeDuplicate($item)) {
            return 'manual_review';
        }

        return (bool) $this->option('apply') ? 'created' : 'create_candidate';
    }

    private function looksLikeDuplicate(array $item): bool
    {
        $needle = $item['normalized_title'];
        if ($needle === '') {
            return false;
        }

        $firstWords = implode(' ', array_slice(explode(' ', $needle), 0, 4));
        if ($firstWords === '') {
            return false;
        }

        return DB::table('products')
            ->where('is_archived', false)
            ->where('name', 'like', '%' . $firstWords . '%')
            ->exists();
    }

    private function upsertSupplierProduct(array $item, int $productId, string $productSku, int $supplierId, ?int $syncId, $now): void
    {
        $supplierArticle = $this->uniqueSupplierArticle($item, $supplierId);
        DB::table('supplier_products')->updateOrInsert(
            [
                'supplier_id' => $supplierId,
                'source_url' => $item['url'],
            ],
            [
                'supplier_article' => $supplierArticle,
                'supplier_article_normalized' => $supplierArticle,
                'supplier_sync_id' => $syncId,
                'product_id' => $productId,
                'product_sku' => $productSku,
                'supplier_name' => $item['title'],
                'source_url' => $item['url'],
                'price' => $item['price'],
                'currency' => 'BYN',
                'currency_rate' => 1,
                'price_byn' => $item['price_byn'],
                'in_stock' => $item['in_stock'],
                'stock_quantity' => $item['in_stock'] ? 1 : 0,
                'stock_status' => $item['stock_status'],
                'stock_text' => $item['stock_text'] ?: null,
                'last_stock_synced_at' => $now,
                'match_status' => 'matched',
                'match_confidence' => 'auto',
                'raw' => json_encode([
                    'brand' => $item['brand'],
                    'breadcrumbs' => $item['breadcrumbs'],
                    'attributes' => $item['attributes'],
                    'images_remote' => $item['images'],
                ], JSON_UNESCAPED_UNICODE),
                'last_synced_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }

    private function uniqueSupplierArticle(array $item, int $supplierId): string
    {
        $article = $item['sku'] !== '' ? $item['sku'] : sha1($item['url']);
        $existsForOtherUrl = DB::table('supplier_products')
            ->where('supplier_id', $supplierId)
            ->where('supplier_article', $article)
            ->where('source_url', '<>', $item['url'])
            ->exists();

        if (! $existsForOtherUrl) {
            return $article;
        }

        return $article . '-' . substr(sha1($item['url']), 0, 8);
    }

    private function createProduct(array $item, int $categoryId, bool $downloadImages, bool $generateDescriptions, AiContentEnricher $enricher, $now): int
    {
        $images = $downloadImages ? $this->downloadImages($item) : [];
        if ($images !== []) {
            $this->runStats['image_products_updated']++;
        }
        $content = null;
        $shortDescription = null;

        if ($generateDescriptions) {
            $content = $enricher->enrich($item['title'], $item['brand'] ?: '', $item['description'], $item['attributes']);
            $shortDescription = $enricher->shortDescription($item['title'], $item['brand'] ?: '', $item['attributes']);
        }

        $content = $content ?: $this->fallbackDescription($item);
        $shortDescription = $shortDescription ?: $this->fallbackShortDescription($item);

        $name = $this->cleanText($item['title']);
        $price = $item['price_byn'] ?? 0;

        return (int) DB::table('products')->insertGetId([
            'category_id' => $categoryId,
            'brand_id' => $item['brand_id'],
            'supplier_id' => null,
            'name' => $name,
            'slug' => $this->uniqueSlug($name),
            'h1' => $name,
            'sku' => $this->nextKotlovSku(),
            'price' => $price ?: 0,
            'price_old' => null,
            'currency' => 'BYN',
            'content' => $content,
            'short_description' => $shortDescription,
            'images' => json_encode($images, JSON_UNESCAPED_UNICODE),
            'specs' => json_encode($this->normalizedCharacteristics($item), JSON_UNESCAPED_UNICODE),
            'unit' => 'шт',
            'warranty' => $item['attributes']['Гарантия'] ?? null,
            'is_active' => true,
            'is_archived' => false,
            'in_stock' => $item['in_stock'],
            'availability_status' => $item['in_stock'] ? Product::AVAILABILITY_IN_STOCK : Product::AVAILABILITY_CHECK,
            'stock_qty' => $item['in_stock'] ? 1 : 0,
            'is_featured' => false,
            'is_new' => true,
            'is_sale' => false,
            'sort_order' => 0,
            'meta_title' => ($item['meta_title'] ?: $name . ' купить в %city%'),
            'meta_keywords' => trim(($item['brand'] ? $item['brand'] . ', ' : '') . $name),
            'meta_description' => $item['meta_description'] ?: Str::limit(strip_tags($shortDescription ?: $name), 250, ''),
            'rating' => 0,
            'reviews_count' => 0,
            'views_count' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function updateExistingProduct(array $item, object $product, bool $downloadImages, bool $generateDescriptions, AiContentEnricher $enricher, $now): void
    {
        $payload = [
            'updated_at' => $now,
        ];

        $hasContent = trim((string) ($product->content ?? '')) !== '';
        if ($generateDescriptions && ! $hasContent) {
            $payload['content'] = $enricher->enrich($item['title'], $item['brand'] ?: '', $item['description'], $item['attributes'])
                ?: $this->fallbackDescription($item);
        }

        $images = $this->decodeJsonArray($product->images ?? null);
        if ($downloadImages && $images === []) {
            $downloadedImages = $this->downloadImages($item);
            if ($downloadedImages !== []) {
                $payload['images'] = json_encode($downloadedImages, JSON_UNESCAPED_UNICODE);
                $this->runStats['image_products_updated']++;
            }
        }

        DB::table('products')->where('id', $product->id)->update($payload);
    }

    private function fillEmptyImages(array $item, object $product, $now): void
    {
        $images = $this->decodeJsonArray($product->images ?? null);
        if ($images !== []) {
            $this->runStats['image_products_skipped_existing']++;
            return;
        }

        $downloadedImages = $this->downloadImages($item);
        if ($downloadedImages === []) {
            return;
        }

        DB::table('products')->where('id', $product->id)->update([
            'images' => json_encode($downloadedImages, JSON_UNESCAPED_UNICODE),
            'updated_at' => $now,
        ]);
        $this->runStats['image_products_updated']++;
    }

    private function syncProductCharacteristics(int $productId, array $item, $now): int
    {
        $attributes = $this->normalizedCharacteristics($item);
        if ($attributes === []) {
            $this->runStats['products_without_characteristics']++;
            return 0;
        }

        $product = DB::table('products')->where('id', $productId)->first(['id', 'category_id', 'specs']);
        if (! $product) {
            return 0;
        }

        $saved = 0;
        $categoryId = (int) $product->category_id;

        foreach ($attributes as $name => $value) {
            $attributeId = $this->ensureCharacteristicAttribute($categoryId, $name, $now);
            $exists = DB::table('product_attribute_values')
                ->where('product_id', $productId)
                ->where('attribute_id', $attributeId)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('product_attribute_values')->insert([
                'product_id' => $productId,
                'attribute_id' => $attributeId,
                'option_id' => null,
                'is_checked' => null,
                'value' => (string) $value,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $saved++;
        }

        $existingSpecs = $this->decodeJsonArray($product->specs ?? null);
        $specsUpdated = false;
        if ($existingSpecs === []) {
            DB::table('products')->where('id', $productId)->update([
                'specs' => json_encode($attributes, JSON_UNESCAPED_UNICODE),
                'updated_at' => $now,
            ]);
            $specsUpdated = true;
        }

        if ($saved > 0 || $specsUpdated) {
            $this->runStats['characteristics_products_updated']++;
            $this->runStats['characteristics_values_saved'] += $saved;
        }

        return $saved;
    }

    private function recordPriceSync(array $item, ?array $match, ?int $supplierId, bool $dryRun, $now, string $sourceAction): void
    {
        $product = $match['product'] ?? null;
        $action = null;

        if ($sourceAction === 'manual_review') {
            $action = 'skipped_manual_review';
        } elseif (! $product || empty($product->id) || ! $supplierId) {
            $action = 'skipped_no_product';
        } else {
            $newPrice = (float) ($item['price_byn'] ?? 0);
            $inStock = (bool) ($item['in_stock'] ?? false);

            if ($newPrice <= 0) {
                $action = 'skipped_empty_price';
            } elseif (! $inStock) {
                $action = 'skipped_out_of_stock';
            } else {
                $action = 'price_synced';
            }
        }

        $oldPrice = $product && isset($product->price) ? (float) $product->price : null;
        $newPrice = (float) ($item['price_byn'] ?? 0);
        $difference = $oldPrice !== null && $newPrice > 0 ? $newPrice - $oldPrice : null;

        $this->priceRows[] = [
            'product_id' => $product->id ?? '',
            'title' => $product->name ?? ($item['title'] ?? ''),
            'old_product_price' => $oldPrice !== null ? $this->formatDecimal($oldPrice) : '',
            'new_bania_price' => $newPrice > 0 ? $this->formatDecimal($newPrice) : '',
            'difference' => $difference !== null ? $this->formatDecimal($difference) : '',
            'supplier_stock_status' => $item['stock_status'] ?? '',
            'action' => $action,
        ];

        match ($action) {
            'price_synced' => $this->runStats['price_synced']++,
            'skipped_out_of_stock' => $this->runStats['price_skipped_out_of_stock']++,
            'skipped_empty_price' => $this->runStats['price_skipped_empty_price']++,
            'skipped_manual_review' => $this->runStats['price_skipped_manual_review']++,
            'skipped_no_product' => $this->runStats['price_skipped_no_product']++,
            default => null,
        };

        if ($dryRun || $action !== 'price_synced' || ! $product || empty($product->id)) {
            return;
        }

        if ($oldPrice !== null && abs($newPrice - $oldPrice) < 0.01) {
            $this->runStats['price_unchanged']++;
            return;
        }

        DB::table('products')->where('id', $product->id)->update([
            'price' => $newPrice,
            'updated_at' => $now,
        ]);
    }

    private function formatDecimal(float $value): string
    {
        return number_format($value, 2, '.', '');
    }

    private function ensureCharacteristicAttribute(int $categoryId, string $name, $now): int
    {
        $existing = DB::table('attributes')
            ->where('category_id', $categoryId)
            ->where('name', $name)
            ->where('type', 'value')
            ->first(['id', 'in_product']);

        if ($existing) {
            if (! (bool) $existing->in_product) {
                DB::table('attributes')->where('id', $existing->id)->update([
                    'in_product' => true,
                    'updated_at' => $now,
                ]);
            }

            return (int) $existing->id;
        }

        return (int) DB::table('attributes')->insertGetId([
            'category_id' => $categoryId,
            'group_id' => 0,
            'sort_order' => 500,
            'type' => 'value',
            'name' => $name,
            'suffix' => null,
            'in_filter' => false,
            'in_sort' => false,
            'in_product' => true,
            'in_brief' => in_array($name, ['Производитель', 'Модель', 'Объём парной', 'Мощность', 'Материал'], true),
            'is_comparable' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function normalizedCharacteristics(array $item): array
    {
        $result = [];
        $steamMin = null;
        $steamMax = null;
        $height = null;
        $width = null;
        $depth = null;

        if (! empty($item['brand'])) {
            $result['Производитель'] = $this->cleanText((string) $item['brand']);
        }

        foreach ($item['attributes'] ?? [] as $name => $value) {
            $rawName = $this->normalizeTitle((string) $name);
            $value = $this->cleanText((string) $value);

            if ($value === '' || $value === '-') {
                continue;
            }

            if (str_contains($rawName, 'минимальный') && (str_contains($rawName, 'объем парной') || str_contains($rawName, 'объём парной'))) {
                $steamMin = $value;
                continue;
            }

            if (str_contains($rawName, 'максимальный') && (str_contains($rawName, 'объем парной') || str_contains($rawName, 'объём парной'))) {
                $steamMax = $value;
                continue;
            }

            if (str_contains($rawName, 'высота')) {
                $height = $value;
                continue;
            }

            if (str_contains($rawName, 'ширина')) {
                $width = $value;
                continue;
            }

            if (str_contains($rawName, 'глубина')) {
                $depth = $value;
                continue;
            }

            $normalizedName = $this->normalizeCharacteristicName((string) $name);

            if ($normalizedName === null) {
                continue;
            }

            $result[$normalizedName] = $value;
        }

        if ($steamMin !== null || $steamMax !== null) {
            $result['Объём парной'] = $this->formatSteamVolumeRange($steamMin, $steamMax);
        }

        if ($height !== null || $width !== null || $depth !== null) {
            $result['Габариты'] = $this->formatDimensions($height, $width, $depth);
        }

        $order = [
            'Производитель',
            'Модель',
            'Тип печи',
            'Материал',
            'Объём парной',
            'Мощность',
            'Масса',
            'Диаметр дымохода',
            'Длина дров',
            'Тип каменки',
            'Закладка камней',
            'Габариты',
            'Страна производства',
        ];

        $sorted = [];
        foreach ($order as $key) {
            if (isset($result[$key])) {
                $sorted[$key] = $result[$key];
            }
        }

        foreach ($result as $key => $value) {
            if (! isset($sorted[$key])) {
                $sorted[$key] = $value;
            }
        }

        return $sorted;
    }

    private function formatSteamVolumeRange(?string $min, ?string $max): string
    {
        $min = $min !== null ? trim($min) : null;
        $max = $max !== null ? trim($max) : null;

        if ($min !== null && $max !== null && $min !== '' && $max !== '') {
            return $this->appendCubicMeters($min) . '-' . $this->appendCubicMeters($max);
        }

        return $this->appendCubicMeters($max ?: (string) $min);
    }

    private function appendCubicMeters(string $value): string
    {
        $value = trim($value);

        return preg_match('/м(?:3|³|\.?куб)/iu', $value) ? $value : $value . ' м³';
    }

    private function formatDimensions(?string $height, ?string $width, ?string $depth): string
    {
        $parts = array_filter([
            $height !== null ? trim($height) : null,
            $width !== null ? trim($width) : null,
            $depth !== null ? trim($depth) : null,
        ], fn (?string $value): bool => $value !== null && $value !== '');

        $value = implode('×', $parts);

        return preg_match('/мм|см|м$/iu', $value) ? $value : $value . ' мм';
    }

    private function normalizeCharacteristicName(string $name): ?string
    {
        $normalized = $this->normalizeTitle($name);

        return match (true) {
            str_contains($normalized, 'производитель') || str_contains($normalized, 'бренд') => 'Производитель',
            str_contains($normalized, 'модель') => 'Модель',
            str_contains($normalized, 'тип печи') || $normalized === 'тип' => 'Тип печи',
            str_contains($normalized, 'материал') => 'Материал',
            ((str_contains($normalized, 'объем') || str_contains($normalized, 'объём')) && (str_contains($normalized, 'парн') || str_contains($normalized, 'парил')))
                || str_contains($normalized, 'для парной') => 'Объём парной',
            str_contains($normalized, 'мощность') => 'Мощность',
            str_contains($normalized, 'масса камней') || str_contains($normalized, 'вес камней') || str_contains($normalized, 'закладка камней') => 'Закладка камней',
            str_contains($normalized, 'масса') || str_contains($normalized, 'вес') => 'Масса',
            str_contains($normalized, 'дымоход') => 'Диаметр дымохода',
            str_contains($normalized, 'полен') || str_contains($normalized, 'дров') => 'Длина дров',
            str_contains($normalized, 'каменк') => 'Тип каменки',
            str_contains($normalized, 'габарит') || str_contains($normalized, 'размер') => 'Габариты',
            str_contains($normalized, 'страна производства') || $normalized === 'страна' => 'Страна производства',
            default => null,
        };
    }

    private function refreshProductAvailability(int $productId, $now): void
    {
        $inStock = DB::table('supplier_products as sp')
            ->join('suppliers as s', 's.id', '=', 'sp.supplier_id')
            ->where('sp.product_id', $productId)
            ->where('s.is_active', true)
            ->where('sp.in_stock', true)
            ->exists();

        DB::table('products')->where('id', $productId)->update([
            'in_stock' => $inStock,
            'availability_status' => $inStock ? Product::AVAILABILITY_IN_STOCK : Product::AVAILABILITY_CHECK,
            'updated_at' => $now,
        ]);
    }

    private function ensureSupplier($now): int
    {
        DB::table('suppliers')->updateOrInsert(
            ['code' => self::SUPPLIER_CODE],
            [
                'name' => 'BANIA.by',
                'currency' => 'BYN',
                'currency_rate' => 1,
                'contact' => self::SITE_URL,
                'notes' => 'BANIA.by supplier sync for sauna and bath products.',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        return (int) DB::table('suppliers')->where('code', self::SUPPLIER_CODE)->value('id');
    }

    private function ensureSupplierSync($now): ?int
    {
        if (! Schema::hasTable('supplier_syncs')) {
            return null;
        }

        $profile = $this->categoryProfile();

        DB::table('supplier_syncs')->updateOrInsert(
            ['key' => $this->syncKey()],
            [
                'name' => 'BANIA.by',
                'code' => self::SUPPLIER_CODE,
                'title' => $profile['title'],
                'description' => $profile['description'],
                'command' => 'supplier:scrape-bania',
                'source_url' => (string) $this->option('category-url'),
                'image_disk_path' => self::IMAGE_DIR,
                'is_active' => true,
                'last_run_at' => $now,
                'last_status' => 'running',
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        return (int) DB::table('supplier_syncs')->where('key', $this->syncKey())->value('id');
    }

    private function previewSupplierId(): ?int
    {
        $id = DB::table('suppliers')->where('code', self::SUPPLIER_CODE)->value('id');
        return $id !== null ? (int) $id : null;
    }

    private function previewSyncId(): ?int
    {
        if (! Schema::hasTable('supplier_syncs')) {
            return null;
        }

        $id = DB::table('supplier_syncs')->where('key', $this->syncKey())->value('id');
        return $id !== null ? (int) $id : null;
    }

    private function loadBrands(): void
    {
        $this->brandCache = [];
        $this->brandNames = [];

        DB::table('brands')->where('is_active', true)->get(['id', 'name'])->each(function ($brand): void {
            $normalized = $this->normalizeBrand((string) $brand->name);
            if ($normalized !== '') {
                $this->brandCache[$normalized] = (int) $brand->id;
                $this->brandNames[(int) $brand->id] = (string) $brand->name;
            }
        });
    }

    private function resolveBrandId(array $item): ?int
    {
        $brand = $this->normalizeBrand((string) ($item['brand'] ?? ''));
        if ($brand !== '' && isset($this->brandCache[$brand])) {
            return $this->brandCache[$brand];
        }

        foreach ($this->brandCache as $name => $id) {
            if ($name !== '' && str_contains($item['normalized_title'], $name)) {
                return $id;
            }
        }

        return null;
    }

    private function parseBrandFilter(string $brands): array
    {
        $productionBrands = $this->productionBrandFilter();
        if (trim($brands) === '') {
            return $productionBrands;
        }

        $allowed = [];
        foreach (explode(',', $brands) as $brand) {
            $normalized = $this->normalizeBrand($brand);
            if ($normalized !== '' && isset($productionBrands[$normalized])) {
                $allowed[$normalized] = true;
            }
        }

        return $allowed;
    }

    private function brandAllowed(array $item, array $match, array $allowedBrands): bool
    {
        if ($allowedBrands === []) {
            return true;
        }

        $candidates = [
            (string) ($item['brand'] ?? ''),
        ];

        $product = $match['product'] ?? null;
        if ($product && isset($product->brand_id)) {
            $candidates[] = (string) ($this->brandNames[(int) $product->brand_id] ?? '');
        }

        foreach ($candidates as $brand) {
            $normalized = $this->normalizeBrand($brand);
            if ($normalized !== '' && isset($allowedBrands[$normalized])) {
                return true;
            }
        }

        return false;
    }

    private function productionBrandFilter(): array
    {
        $allowed = [];
        foreach (self::PRODUCTION_BRANDS as $brand) {
            $normalized = $this->normalizeBrand($brand);
            if ($normalized !== '') {
                $allowed[$normalized] = true;
            }
        }

        return $allowed;
    }

    private function resolveCategoryId(): ?int
    {
        foreach ($this->categorySlugs() as $slug) {
            $id = DB::table('categories')->where('slug', $slug)->value('id');
            if ($id !== null) {
                return (int) $id;
            }
        }

        return DB::table('categories')
            ->where('name', 'like', '%печ%')
            ->where('name', 'like', '%бан%')
            ->value('id');
    }

    private function candidateCategoryIds(): array
    {
        $profileIds = DB::table('categories')
            ->whereIn('slug', $this->categorySlugs())
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($profileIds !== []) {
            return $profileIds;
        }

        $ids = DB::table('categories')
            ->whereIn('slug', $this->categorySlugs())
            ->orWhere('name', 'like', '%бан%')
            ->orWhere('name', 'like', '%печ%')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return $ids ?: array_filter([$this->resolveCategoryId()]);
    }

    private function categoryProfile(): array
    {
        $categoryUrl = (string) $this->option('category-url');
        foreach (self::CATEGORY_PROFILES as $profile) {
            if (str_contains($categoryUrl, $profile['source_path'])) {
                return $profile;
            }
        }

        return self::CATEGORY_PROFILES['wood'];
    }

    private function categorySlugs(): array
    {
        return $this->categoryProfile()['category_slugs'];
    }

    private function syncKey(): string
    {
        return $this->categoryProfile()['sync_key'];
    }

    private function productLinksFromHtml(string $html, string $categoryUrl): array
    {
        $links = [];
        $categoryPath = trim((string) parse_url($categoryUrl, PHP_URL_PATH), '/');

        foreach ($this->extractAnchors($html) as $anchor) {
            $url = $this->absoluteUrl($anchor['href']);
            $path = trim((string) parse_url($url, PHP_URL_PATH), '/');

            if ($path === $categoryPath || ! str_starts_with($path, $categoryPath . '/')) {
                continue;
            }

            if (preg_match('~/(cart|compare|wishlist|login|register|search)(/|$)~i', $url)) {
                continue;
            }

            $title = $this->cleanText($anchor['text']);
            if ($title === '' || mb_strlen($title) < 5) {
                continue;
            }

            if ($this->productBlockForUrl($html, $url) === '') {
                continue;
            }

            $links[$url] = $title;
        }

        return $links;
    }

    private function extractAnchors(string $html): array
    {
        $anchors = [];
        $dom = $this->dom($html);
        $xpath = new \DOMXPath($dom);

        foreach ($xpath->query('//a[@href]') ?: [] as $node) {
            if (! $node instanceof \DOMElement) {
                continue;
            }

            $anchors[] = [
                'href' => $node->getAttribute('href'),
                'text' => $node->textContent,
            ];
        }

        return $anchors;
    }

    private function extractLinks(string $html): array
    {
        return array_map(
            fn (array $anchor) => $this->absoluteUrl($anchor['href']),
            $this->extractAnchors($html)
        );
    }

    private function nearLinkHtml(string $html, string $url): string
    {
        $block = $this->productBlockForUrl($html, $url);
        if ($block !== '') {
            return $block;
        }

        $relative = parse_url($url, PHP_URL_PATH) ?: $url;
        $escaped = preg_quote($relative, '~');

        if (preg_match('~<([a-z0-9]+)[^>]*(?:product|item|catalog|card)[^>]*>[\s\S]{0,6000}' . $escaped . '[\s\S]{0,6000}</\1>~iu', $html, $match)) {
            return $match[0];
        }

        $pos = strpos($html, $relative);
        if ($pos === false) {
            return '';
        }

        return substr($html, max(0, $pos - 2500), 5000);
    }

    private function productBlockForUrl(string $html, string $url): string
    {
        $dom = $this->dom($html);
        $xpath = new \DOMXPath($dom);
        $target = strtok($this->absoluteUrl($url), '#') ?: $this->absoluteUrl($url);

        foreach ($xpath->query('//a[@href]') ?: [] as $anchor) {
            if (! $anchor instanceof \DOMElement) {
                continue;
            }

            $href = strtok($this->absoluteUrl($anchor->getAttribute('href')), '#') ?: '';
            if ($href !== $target) {
                continue;
            }

            $node = $anchor;
            for ($depth = 0; $depth < 8 && $node instanceof \DOMElement; $depth++) {
                $class = ' ' . $node->getAttribute('class') . ' ';
                $isProductBlock = str_contains($class, ' product-thumb ')
                    || str_contains($class, ' product-layout ');
                $text = $this->cleanText($node->textContent);
                if ($isProductBlock
                    && mb_strlen($text) > 40
                    && mb_strlen($text) < 1800
                    && ($this->parsePrice($text) !== null || $this->isPriceOnRequest($text))
                    && preg_match('/(В наличии|Нет в наличии|Под заказ|Купить)/u', $text)
                ) {
                    return $dom->saveHTML($node) ?: '';
                }

                $node = $node->parentNode;
            }
        }

        return '';
    }

    private function parseAttributes(string $html): array
    {
        $attributes = [];
        $dom = $this->dom($html);
        $xpath = new \DOMXPath($dom);

        foreach ($xpath->query('//tr') ?: [] as $row) {
            $cells = [];
            foreach ($row->childNodes as $cell) {
                if ($cell instanceof \DOMElement && in_array(strtolower($cell->tagName), ['td', 'th'], true)) {
                    $cells[] = $this->cleanText($cell->textContent);
                }
            }
            if (count($cells) >= 2 && $cells[0] !== '' && $cells[1] !== '') {
                $attributes[$cells[0]] = $cells[1];
            }
        }

        foreach ($xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " attribute-item-premium ")]') ?: [] as $node) {
            if (! $node instanceof \DOMElement) {
                continue;
            }

            $nameNode = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " attribute-name-premium ")]', $node)->item(0);
            $valueNode = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " attribute-value-premium ")]', $node)->item(0);
            $name = $nameNode ? $this->cleanText($nameNode->textContent) : '';
            $value = $valueNode ? $this->cleanText($valueNode->textContent) : '';

            if ($name !== '' && $value !== '') {
                $attributes[$name] = $value;
            }
        }

        foreach ($xpath->query('//*[contains(@class,"character") or contains(@class,"attribute") or contains(@class,"param") or contains(@class,"spec")]') ?: [] as $node) {
            $text = $this->cleanText($node->textContent);
            if (preg_match('/^([^:]{2,80}):\s*(.{1,200})$/u', $text, $match)) {
                $attributes[$this->cleanText($match[1])] = $this->cleanText($match[2]);
            }
        }

        return $attributes;
    }

    private function extractDescriptionHtml(string $html): ?string
    {
        $patterns = [
            '~<div[^>]+class=["\'][^"\']*(?:description|desc|tab-description|product-description)[^"\']*["\'][^>]*>([\s\S]*?)</div>~iu',
            '~<section[^>]+class=["\'][^"\']*(?:description|desc)[^"\']*["\'][^>]*>([\s\S]*?)</section>~iu',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $match)) {
                return $this->cleanDescriptionHtml($match[1]);
            }
        }

        return null;
    }

    private function extractImages(string $html, string $pageUrl): array
    {
        $images = [];
        if (preg_match_all('~(?:href|src|data-src|data-large|data-image)=["\']([^"\']+\.(?:jpg|jpeg|png|webp)(?:\?[^"\']*)?)["\']~iu', $html, $matches)) {
            foreach ($matches[1] as $src) {
                $url = $this->normalizeBaniaImageUrl($this->absoluteUrl($src, $pageUrl));
                if (! $this->isProductImageCandidate($url)) {
                    continue;
                }
                $images[] = $url;
            }
        }

        return array_values(array_unique(array_slice($images, 0, 12)));
    }

    private function firstImageFromHtml(string $html): ?string
    {
        if (preg_match_all('~(?:src|data-src)=["\']([^"\']+\.(?:jpg|jpeg|png|webp)(?:\?[^"\']*)?)["\']~iu', $html, $matches)) {
            foreach ($matches[1] as $src) {
                $url = $this->normalizeBaniaImageUrl($this->absoluteUrl($src));
                if ($this->isProductImageCandidate($url)) {
                    return $url;
                }
            }
        }

        return null;
    }

    private function normalizeBaniaImageUrl(string $url): string
    {
        $url = strtok($url, '?') ?: $url;

        if (str_contains($url, '/image/cache/catalog/')) {
            $url = str_replace('/image/cache/catalog/', '/image/catalog/', $url);
            $url = preg_replace('~-\d+x\d+(\.(?:jpg|jpeg|png|webp))$~i', '$1', $url) ?? $url;
        }

        return $url;
    }

    private function isProductImageCandidate(string $url): bool
    {
        if (! str_contains($url, '/image/catalog/')) {
            return false;
        }

        if (preg_match('~/(?:logo|icon|icons|payment|social|banner|manufacturer)/|(?:logo|icon|sprite|placeholder|telegram|viber|whatsapp|email|tel)~i', $url)) {
            return false;
        }

        if (preg_match('~-(\d+)x(\d+)\.(?:jpg|jpeg|png|webp)$~i', $url, $size)) {
            return (int) $size[1] >= 100 && (int) $size[2] >= 100;
        }

        return true;
    }

    private function extractSku(string $html, array $attributes): string
    {
        foreach (['Артикул', 'SKU', 'Код товара', 'Модель'] as $key) {
            if (! empty($attributes[$key])) {
                return $this->normalizeArticle((string) $attributes[$key]);
            }
        }

        $patterns = [
            '/(?:Артикул|SKU|Код товара|Модель)\s*[:#]?\s*([A-Za-zА-Яа-я0-9_.\-\/]+)/u',
            '/itemprop=["\']sku["\'][^>]*>([^<]+)/iu',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $this->cleanText($html), $match)) {
                return $this->normalizeArticle($match[1]);
            }
        }

        return '';
    }

    private function extractBrand(string $html, array $attributes, string $title): string
    {
        foreach (['Бренд', 'Производитель', 'Торговая марка'] as $key) {
            if (! empty($attributes[$key])) {
                return $this->cleanText((string) $attributes[$key]);
            }
        }

        if (preg_match('/(?:Бренд|Производитель)\s*[:#]?\s*([A-Za-zА-Яа-я0-9_.\- ]{2,80})/u', $this->cleanText($html), $match)) {
            return $this->cleanText($match[1]);
        }

        return $this->detectBrandFromTitle($title);
    }

    private function detectBrandFromTitle(string $title): string
    {
        return $this->canonicalBrand('', $title, '');
    }

    private function canonicalBrand(string $brand, string $title, string $url): string
    {
        $normalizedTitle = $this->normalizeTitle($title);
        $urlishTitle = Str::of($title . ' ' . $url)->lower()->ascii()->replace([' ', '_'], '-')->toString();

        foreach (self::SUPPLIER_BRAND_ALIASES as $brand => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($urlishTitle, $needle) || str_contains($normalizedTitle, $needle)) {
                    return $brand;
                }
            }
        }

        foreach ($this->brandCache as $normalizedBrand => $id) {
            if ($normalizedBrand !== '' && str_contains($normalizedTitle, $normalizedBrand)) {
                return $this->brandNames[$id] ?? $normalizedBrand;
            }
        }

        return '';
    }

    private function parseBreadcrumbs(string $html): array
    {
        $crumbs = [];
        $dom = $this->dom($html);
        $xpath = new \DOMXPath($dom);
        foreach ($xpath->query('//*[contains(@class,"breadcrumb")]//a | //*[contains(@class,"breadcrumb")]//span') ?: [] as $node) {
            $text = $this->cleanText($node->textContent);
            if ($text !== '') {
                $crumbs[] = $text;
            }
        }

        return array_values(array_unique($crumbs));
    }

    private function parsePrice(string $text): ?float
    {
        if ($this->isPriceOnRequest($text)) {
            return null;
        }

        $plain = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $plain = str_replace(["\u{A0}", ' '], '', $plain);

        if (! preg_match('/([0-9]+(?:[.,][0-9]{1,2})?)(?:BYN|руб|р\.|$)/ui', $plain, $match)) {
            if (! preg_match('/([0-9][0-9\s.,]{1,12})/u', $plain, $match)) {
                return null;
            }
        }

        $value = str_replace(',', '.', preg_replace('/\s+/u', '', $match[1]) ?? $match[1]);

        return is_numeric($value) ? round((float) $value, 2) : null;
    }

    private function parseCatalogPrice(string $nodeHtml, string $title): ?float
    {
        if ($this->isPriceOnRequest($nodeHtml)) {
            return null;
        }

        return $this->parsePrice($nodeHtml) ?? $this->parsePrice($title);
    }

    private function isPriceOnRequest(string $text): bool
    {
        $raw = mb_strtolower(html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $clean = mb_strtolower($this->cleanText($text));

        return str_contains($raw, 'цена по запросу')
            || str_contains($clean, 'цена по запросу')
            || str_contains($raw, 'price-request');
    }

    private function parseStockText(string $html): string
    {
        $text = $this->cleanText($html);
        foreach (['Нет в наличии', 'Под заказ', 'В наличии', 'Есть', 'Нет'] as $needle) {
            if (mb_stripos($text, $needle) !== false) {
                return $needle;
            }
        }
        foreach (['В наличии', 'Есть', 'Под заказ', 'Нет в наличии', 'Нет'] as $needle) {
            if (mb_stripos($text, $needle) !== false) {
                return $needle;
            }
        }

        return '';
    }

    private function parseStockStatus(string $html): bool
    {
        return $this->stockStatusFromText($html) === 'in_stock';
    }

    private function stockStatusFromText(string $text): string
    {
        $text = mb_strtolower($this->cleanText($text));
        if ($text === '') {
            return 'unknown';
        }

        if (str_contains($text, 'нет в наличии') || str_contains($text, 'под заказ') || preg_match('/(^|\s)нет(\s|$)/u', $text)) {
            return 'out_of_stock';
        }

        if (str_contains($text, 'в наличии') || str_contains($text, 'есть')) {
            return 'in_stock';
        }

        if (str_contains($text, 'нет в наличии') || str_contains($text, 'под заказ') || preg_match('/(^|\s)нет(\s|$)/u', $text)) {
            return 'out_of_stock';
        }

        if (str_contains($text, 'в наличии') || str_contains($text, 'есть')) {
            return 'in_stock';
        }

        return 'unknown';
    }

    private function pageNumberFromUrl(string $url): ?int
    {
        $query = [];
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        foreach (['page', 'p', 'PAGEN_1', 'PAGEN_2'] as $key) {
            if (isset($query[$key]) && is_numeric($query[$key])) {
                return (int) $query[$key];
            }
        }

        if (preg_match('~/page[-/]?(\d+)~i', $url, $match)) {
            return (int) $match[1];
        }

        return null;
    }

    private function downloadImages(array $item): array
    {
        if (empty($item['images'])) {
            return [];
        }

        $dir = public_path(self::IMAGE_DIR);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $paths = [];
        $base = Str::slug($item['sku'] ?: $item['title']) ?: 'bania-product';
        foreach (array_values(array_unique($item['images'])) as $index => $url) {
            try {
                $path = parse_url($url, PHP_URL_PATH) ?: '';
                $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION)) ?: 'jpg';
                if (! in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                    $ext = 'jpg';
                }

                $filename = $base . '-' . ($index + 1) . '.' . $ext;
                $target = $dir . DIRECTORY_SEPARATOR . $filename;
                if (! file_exists($target)) {
                    file_put_contents($target, $this->fetch($url));
                    if (! $this->isUsableDownloadedImage($target)) {
                        @unlink($target);
                        $this->runStats['image_download_errors']++;
                        $this->warn('  image skipped: too small ' . $url);
                        continue;
                    }
                    $this->runStats['images_downloaded']++;
                }
                if (! $this->isUsableDownloadedImage($target)) {
                    @unlink($target);
                    $this->runStats['image_download_errors']++;
                    $this->warn('  image skipped: too small ' . $url);
                    continue;
                }
                $paths[] = self::IMAGE_DIR . '/' . $filename;
            } catch (\Throwable $e) {
                $this->runStats['image_download_errors']++;
                $this->warn('  image skipped: ' . $url);
            }
        }

        return array_values(array_unique($paths));
    }

    private function isUsableDownloadedImage(string $path): bool
    {
        $size = @getimagesize($path);

        return $size !== false && ($size[0] ?? 0) >= 100 && ($size[1] ?? 0) >= 100;
    }

    private function addReportRow(array $item, ?array $match, string $action, string $error): void
    {
        $product = $match['product'] ?? null;
        $this->reportRows[] = [
            'supplier' => self::SUPPLIER_CODE,
            'source_category' => $item['source_category'] ?? '',
            'source_url' => $item['url'] ?? '',
            'page' => $item['page'] ?? '',
            'brand' => $item['brand'] ?? '',
            'supplier_sku' => $item['sku'] ?? '',
            'supplier_title' => $item['title'] ?? '',
            'normalized_title' => $item['normalized_title'] ?? $this->normalizeTitle((string) ($item['title'] ?? '')),
            'supplier_price' => $item['price'] ?? '',
            'supplier_stock_status' => $item['stock_status'] ?? '',
            'has_price' => ($item['price'] ?? null) !== null ? 1 : 0,
            'has_stock' => ($item['stock_status'] ?? 'unknown') !== 'unknown' ? 1 : 0,
            'has_brand' => ($item['brand'] ?? '') !== '' ? 1 : 0,
            'has_sku' => ($item['sku'] ?? '') !== '' ? 1 : 0,
            'attributes_count' => count($item['attributes'] ?? []),
            'images_count' => count($item['images'] ?? []),
            'description_length' => mb_strlen(strip_tags((string) ($item['description'] ?? ''))),
            'matched_product_id' => $product?->id ?? '',
            'matched_product_title' => $product?->name ?? '',
            'match_type' => $match['type'] ?? '',
            'confidence' => $match['confidence'] ?? '',
            'action' => $action,
            'error' => $error,
        ];
    }

    private function addManualRow(array $item, ?array $match, string $reason): void
    {
        $product = $match['product'] ?? null;
        $this->manualRows[] = [
            'reason' => $reason,
            'supplier_title' => $item['title'] ?? '',
            'supplier_sku' => $item['sku'] ?? '',
            'supplier_url' => $item['url'] ?? '',
            'supplier_price' => $item['price'] ?? '',
            'matched_product_id' => $product?->id ?? '',
            'matched_product_title' => $product?->name ?? '',
            'confidence' => $match['confidence'] ?? '',
        ];
    }

    private function writeReports(): void
    {
        $dir = storage_path('app/reports/bania');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $stamp = now()->format('Y-m-d-H-i');
        $reportPath = $dir . '/bania-import-' . $stamp . '.csv';
        $this->writeCsv($reportPath, $this->reportRows);
        $this->info('Report written: ' . $reportPath);

        if ($this->manualRows !== []) {
            $manualPath = $dir . '/manual-review-' . $stamp . '.csv';
            $this->writeCsv($manualPath, $this->manualRows);
            $this->info('Manual review written: ' . $manualPath);
        }

        if ($this->priceRows !== []) {
            $pricePath = $dir . '/price-sync-' . $stamp . '.csv';
            $this->writeCsv($pricePath, $this->priceRows);
            $this->info('Price sync written: ' . $pricePath);
        }
    }

    private function writeCsv(string $path, array $rows): void
    {
        if ($rows === []) {
            return;
        }

        $handle = fopen($path, 'wb');
        fputcsv($handle, array_keys($rows[0]));
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);
    }

    private function fallbackDescription(array $item): ?string
    {
        if (! empty($item['description'])) {
            return $this->cleanDescriptionHtml($item['description']);
        }

        return '<p>' . e($item['title']) . ' - товар для бани и сауны. Характеристики и наличие обновляются по данным поставщика BANIA.by.</p>';
    }

    private function fallbackShortDescription(array $item): string
    {
        return $item['brand']
            ? $item['brand'] . ' ' . $item['title']
            : $item['title'];
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'bania-product';
        $slug = $base;
        $i = 2;
        while (DB::table('products')->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }

    private function nextKotlovSku(): string
    {
        $max = DB::table('products')
            ->where('sku', 'like', 'KOTLOV-%')
            ->pluck('sku')
            ->map(fn ($sku) => preg_match('/^KOTLOV-(\d+)$/', (string) $sku, $match) ? (int) $match[1] : 0)
            ->max() ?? 0;

        $next = max(0, (int) $max) + 1;
        do {
            $sku = sprintf('KOTLOV-%06d', $next++);
        } while (DB::table('products')->where('sku', $sku)->exists());

        return $sku;
    }

    private function normalizeTitle(string $title): string
    {
        $title = mb_strtolower($this->cleanText($title));
        $title = str_replace(['"', "'", '«', '»', '“', '”', 'печь для бани', 'дровяная', 'дровяные', 'купить'], ' ', $title);
        $title = preg_replace('/[^a-zа-яё0-9]+/u', ' ', $title) ?? $title;
        return trim(preg_replace('/\s+/u', ' ', $title) ?? $title);
    }

    private function normalizeBrand(string $brand): string
    {
        $brand = mb_strtolower($this->cleanText($brand));
        $brand = preg_replace('/[^a-zа-яё0-9]+/u', ' ', $brand) ?? $brand;
        return trim(preg_replace('/\s+/u', ' ', $brand) ?? $brand);
    }

    private function normalizeArticle(string $article): string
    {
        $article = html_entity_decode($article, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $article = str_replace(['–', '—', '−'], '-', $article);
        $article = preg_replace('/\s+/u', '', $article) ?? $article;

        return mb_strtoupper(trim($article));
    }

    private function isReliableSupplierArticle(?string $article): bool
    {
        $article = $this->normalizeArticle((string) $article);

        return mb_strlen($article) >= 6 && preg_match('/\d/u', $article) === 1;
    }

    private function titlesAreCompatible(string $supplierTitle, string $productTitle): bool
    {
        $supplierTokens = $this->titleTokens($supplierTitle);
        $productTokens = $this->titleTokens($productTitle);

        if (in_array('karina', $supplierTokens, true) && ! in_array('karina', $productTokens, true)) {
            return false;
        }

        $supplierModels = array_values(array_intersect($supplierTokens, self::MODEL_TOKENS));
        $productModels = array_values(array_intersect($productTokens, self::MODEL_TOKENS));
        if ($supplierModels !== [] && $productModels !== [] && array_intersect($supplierModels, $productModels) === []) {
            return false;
        }

        if (in_array('steam', $supplierTokens, true) !== in_array('steam', $productTokens, true)) {
            return false;
        }

        $supplierNumbers = $this->numericTokens($supplierTokens);
        $productNumbers = $this->numericTokens($productTokens);
        if (in_array('karina', $supplierTokens, true) && $supplierNumbers !== [] && $productNumbers !== [] && $supplierNumbers !== $productNumbers) {
            return false;
        }

        foreach ($this->exactCodeTokens($supplierTokens) as $code) {
            if (! in_array($code, $productTokens, true)) {
                return false;
            }
        }

        return true;
    }

    private function titleTokens(string $title): array
    {
        $tokens = preg_split('/\s+/u', $title, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique($tokens));
    }

    private function numericTokens(array $tokens): array
    {
        return array_values(array_filter(
            $tokens,
            fn (string $token): bool => preg_match('/^[0-9]+$/u', $token) === 1
        ));
    }

    private function exactCodeTokens(array $tokens): array
    {
        return array_values(array_filter(
            $tokens,
            fn (string $token): bool => preg_match('/^(?:[a-z]+[0-9]+[a-z0-9]*|[0-9]+[a-z]+[a-z0-9]*)$/u', $token) === 1
        ));
    }

    private function similarity(string $a, string $b): float
    {
        if ($a === '' || $b === '') {
            return 0.0;
        }

        similar_text($a, $b, $percent);
        return (float) $percent;
    }

    private function decodeJsonArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        $decoded = json_decode((string) $value, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function absoluteUrl(string $url, ?string $base = null): string
    {
        $url = html_entity_decode(trim($url), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($url === '') {
            return '';
        }

        if (str_starts_with($url, '//')) {
            return 'https:' . $url;
        }

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        $root = $base
            ? ((string) parse_url($base, PHP_URL_SCHEME) . '://' . (string) parse_url($base, PHP_URL_HOST))
            : self::SITE_URL;

        return rtrim($root, '/') . '/' . ltrim($url, '/');
    }

    private function fetch(string $url): string
    {
        $contextOptions = [
            'http' => [
                'method' => 'GET',
                'header' => "User-Agent: Mozilla/5.0 (compatible; KotlovBot/1.0)\r\nAccept-Language: ru,en;q=0.8\r\nConnection: close\r\n",
                'timeout' => 45,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ];

        $lastError = null;
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                $context = stream_context_create($contextOptions);
                $body = file_get_contents(str_replace(' ', '%20', $url), false, $context);
                if ($body !== false) {
                    return $body;
                }

                $lastError = 'empty response';
            } catch (\Throwable $e) {
                $lastError = $e->getMessage();
            }

            usleep(250000 * $attempt);
        }

        throw new \RuntimeException('Could not fetch ' . $url . ': ' . $lastError);
    }

    private function dom(string $html): \DOMDocument
    {
        $dom = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $dom;
    }

    private function firstXPathText(string $html, string $query): ?string
    {
        $xpath = new \DOMXPath($this->dom($html));
        $node = $xpath->query($query)?->item(0);

        return $node ? $node->textContent : null;
    }

    private function match(string $pattern, string $subject): ?string
    {
        return preg_match($pattern, $subject, $match) ? html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8') : null;
    }

    private function cleanText(string $value): string
    {
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = str_replace("\u{A0}", ' ', $value);

        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }

    private function cleanDescriptionHtml(string $value): ?string
    {
        $value = preg_replace('/<(script|style|svg|form|button|iframe)\b[\s\S]*?<\/\1>/iu', '', $value) ?? $value;
        $value = preg_replace('/<a\b[^>]*>([\s\S]*?)<\/a>/iu', '$1', $value) ?? $value;
        $value = strip_tags($value, '<p><ul><ol><li><strong><b><em><i><br>');
        $value = preg_replace('/<([a-z0-9]+)\b[^>]*>/iu', '<$1>', $value) ?? $value;
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = trim(preg_replace("/\n{3,}/u", "\n\n", $value) ?? $value);

        return $value !== '' ? $value : null;
    }
}
