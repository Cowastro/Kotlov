<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Product;
use App\Services\ProductSourceEnricher;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class RepairRusklimatB2bCategoryImagesCommand extends Command
{
    protected $signature = 'supplier:repair-rusklimat-b2b-category-images
        {--category-url= : Rusklimat B2B category URL}
        {--category= : Kotlov category slug; includes descendants}
        {--max-pages=30 : Max B2B category pages to scan}
        {--limit=0 : Max matched products to process, 0 means no limit}
        {--offset=0 : Skip matched repair candidates}
        {--apply : Download and write images; default is dry-run}
        {--force : Process even products whose main image currently looks OK}
        {--active-only : Restrict to active, non-archived products}
        {--sleep=500 : Delay between product requests, ms}';

    protected $description = 'Repair Rusklimat product photos from a B2B category by matching supplier_article (НС code).';

    private const SUPPLIER_CODE = 'rusklimat';

    public function handle(ProductSourceEnricher $enricher): int
    {
        $categoryUrl = trim((string) $this->option('category-url'));
        if ($categoryUrl === '' || ! filter_var($categoryUrl, FILTER_VALIDATE_URL)) {
            $this->error('--category-url must be a valid URL.');

            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');
        $force = (bool) $this->option('force');
        $activeOnly = (bool) $this->option('active-only');
        $limit = max(0, (int) $this->option('limit'));
        $offset = max(0, (int) $this->option('offset'));
        $sleep = max(300, (int) $this->option('sleep'));
        $maxPages = max(1, (int) $this->option('max-pages'));

        $items = $this->loadCategoryItems($categoryUrl, $maxPages);
        if ($items === []) {
            $this->warn('No B2B product items found in category pages.');

            return self::SUCCESS;
        }

        $supplierId = (int) DB::table('suppliers')->where('code', self::SUPPLIER_CODE)->value('id');
        if ($supplierId <= 0) {
            $this->error('Supplier not found: ' . self::SUPPLIER_CODE);

            return self::FAILURE;
        }

        $categoryIds = $this->categoryIds();
        $sourceByArticle = collect($items)->mapWithKeys(fn (array $item) => [$item['code'] => $item['url']]);

        $products = Product::query()
            ->select('products.*', 'supplier_products.supplier_article')
            ->join('supplier_products', 'products.id', '=', 'supplier_products.product_id')
            ->where('supplier_products.supplier_id', $supplierId)
            ->whereIn('supplier_products.supplier_article', $sourceByArticle->keys()->all())
            ->when($categoryIds->isNotEmpty(), fn ($query) => $query->whereIn('products.category_id', $categoryIds))
            ->when($activeOnly, fn ($query) => $query->where('products.is_archived', false)->where('products.is_active', true))
            ->orderBy('products.id')
            ->get()
            ->filter(fn (Product $product) => $force || $this->imageStatus($product)[0] !== 'ok')
            ->values();

        $total = $products->count();
        if ($offset > 0) {
            $products = $products->slice($offset)->values();
        }
        if ($limit > 0) {
            $products = $products->take($limit)->values();
        }

        $this->line($apply
            ? '<fg=red;options=bold>APPLY: matching Rusklimat B2B images will be written.</>'
            : '<fg=yellow;options=bold>DRY RUN: no database/filesystem changes.</>');
        $this->info(sprintf(
            'B2B items: %d; matched repair candidates: %d (processing %d, offset %d)',
            count($items),
            $total,
            $products->count(),
            $offset
        ));

        $stats = [
            'processed' => 0,
            'would_repair' => 0,
            'repaired' => 0,
            'images_found' => 0,
            'images_saved' => 0,
            'no_source_images' => 0,
            'errors' => 0,
        ];

        foreach ($products as $product) {
            $stats['processed']++;
            $article = (string) $product->supplier_article;
            $sourceUrl = (string) $sourceByArticle->get($article);
            [$status, $detail] = $this->imageStatus($product);

            $this->line(sprintf(
                '[%d/%d] #%d %s %s (%s)',
                $stats['processed'],
                $products->count(),
                $product->id,
                $article,
                mb_strimwidth($product->name, 0, 62, '...'),
                $status
            ));
            $this->line('  current: ' . $detail);
            $this->line('  source: ' . $sourceUrl);

            try {
                $result = $enricher->enrich($product, $sourceUrl, [
                    'preview_only' => ! $apply,
                    'replace_images' => true,
                    'update_images' => true,
                    'update_specs' => false,
                    'update_service' => false,
                    'update_documents' => false,
                    'update_video' => false,
                    'update_content' => false,
                ]);

                $imagesFound = (int) ($result['images_found'] ?? 0);
                $imagesSaved = (int) ($result['images_saved'] ?? 0);
                $stats['images_found'] += $imagesFound;
                $stats['images_saved'] += $imagesSaved;

                if ($imagesFound === 0) {
                    $stats['no_source_images']++;
                } elseif ($apply && in_array('images', $result['updated_fields'] ?? [], true)) {
                    $stats['repaired']++;
                    DB::table('supplier_products')
                        ->where('supplier_id', $supplierId)
                        ->where('supplier_article', $article)
                        ->update(['source_url' => $sourceUrl, 'updated_at' => now()]);
                } elseif (! $apply) {
                    $stats['would_repair']++;
                }

                $this->line(sprintf('  found=%d saved=%d', $imagesFound, $imagesSaved));
            } catch (\Throwable $e) {
                $stats['errors']++;
                $this->warn('  ERROR: ' . $e->getMessage());
            }

            usleep($sleep * 1000);
        }

        $this->newLine();
        $this->table(['metric', 'count'], collect($stats)->map(fn ($count, $metric) => [$metric, $count])->values()->all());

        return $stats['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array<int, array{code:string,name:string,url:string,image:string}>
     */
    private function loadCategoryItems(string $baseUrl, int $maxPages): array
    {
        $items = [];
        $seen = [];

        for ($page = 1; $page <= $maxPages; $page++) {
            $url = $this->pageUrl($baseUrl, $page);
            $this->line('Loading B2B page ' . $page . ': ' . $url);

            $response = Http::timeout(30)
                ->retry(2, 500)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    'Accept' => 'text/html,application/xhtml+xml',
                ])
                ->get($url);

            if (! $response->ok()) {
                $this->warn('  skipped: HTTP ' . $response->status());
                break;
            }

            $pageItems = $this->extractItems($response->body());
            $newOnPage = 0;
            foreach ($pageItems as $item) {
                if (isset($seen[$item['code']])) {
                    continue;
                }
                $seen[$item['code']] = true;
                $items[] = $item;
                $newOnPage++;
            }

            $this->line(sprintf('  found=%d new=%d', count($pageItems), $newOnPage));
            if ($pageItems === [] || $newOnPage === 0) {
                break;
            }
        }

        return $items;
    }

    /**
     * @return array<int, array{code:string,name:string,url:string,image:string}>
     */
    private function extractItems(string $html): array
    {
        preg_match_all(
            '#"([^"]+)","([^"]+)","(/catalog/product/[^"]+)","(https://rkcdn\.ru/products/[^"]+)","(НС-[^"]+)"#u',
            $html,
            $matches,
            PREG_SET_ORDER
        );

        $items = [];
        foreach ($matches as $match) {
            $items[] = [
                'name' => html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                'url' => 'https://b2b.rusklimat.com' . $match[3],
                'image' => $match[4],
                'code' => $match[5],
            ];
        }

        return $items;
    }

    private function pageUrl(string $baseUrl, int $page): string
    {
        if ($page === 1) {
            return $baseUrl;
        }

        $parts = parse_url($baseUrl);
        parse_str($parts['query'] ?? '', $query);
        $query['page'] = $page;

        $path = $parts['path'] ?? '/';

        return ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? 'b2b.rusklimat.com')
            . $path . '?' . http_build_query($query);
    }

    private function categoryIds(): Collection
    {
        $slug = trim((string) $this->option('category'));
        if ($slug === '') {
            return collect();
        }

        $category = Category::query()->where('slug', $slug)->first(['id']);
        if (! $category) {
            $this->error('Category not found: ' . $slug);

            return collect();
        }

        $categoriesByParent = Category::query()
            ->get(['id', 'parent_id'])
            ->groupBy('parent_id');

        $ids = collect([(int) $category->id]);
        $stack = [(int) $category->id];

        while ($stack !== []) {
            $currentId = array_pop($stack);
            foreach ($categoriesByParent->get($currentId, collect()) as $child) {
                $childId = (int) $child->id;
                $ids->push($childId);
                $stack[] = $childId;
            }
        }

        return $ids->unique()->values();
    }

    private function imageStatus(Product $product): array
    {
        $images = $product->images;
        if (is_string($images)) {
            $images = json_decode($images, true);
        }

        if (! is_array($images) || $images === [] || trim((string) ($images[0] ?? '')) === '') {
            return ['empty', 'images is empty'];
        }

        $raw = ltrim((string) $images[0], '/');
        if (str_starts_with($raw, 'http://') || str_starts_with($raw, 'https://')) {
            return ['ok', $raw];
        }

        if (str_starts_with($raw, 'img/')) {
            return file_exists(public_path($raw)) ? ['ok', $raw] : ['broken', $raw];
        }

        if (str_starts_with($raw, 'products/')) {
            return Storage::disk('public')->exists($raw) ? ['ok', $raw] : ['broken', 'storage:' . $raw];
        }

        if (str_starts_with($raw, 'product/')) {
            return file_exists(public_path('images/' . $raw)) ? ['ok', 'legacy:' . $raw] : ['broken', 'legacy:' . $raw];
        }

        if (substr_count($raw, '/') >= 2) {
            return file_exists(public_path('images/product/' . $raw)) ? ['ok', 'legacy:' . $raw] : ['broken', 'legacy:' . $raw];
        }

        return ['broken', $raw];
    }
}
