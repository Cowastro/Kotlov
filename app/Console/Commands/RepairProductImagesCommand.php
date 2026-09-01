<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Product;
use App\Services\ProductSourceEnricher;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class RepairProductImagesCommand extends Command
{
    protected $signature = 'catalog:repair-product-images
        {--all : Scan all products with source URLs}
        {--category= : Category slug; includes descendants}
        {--supplier= : Supplier code or id}
        {--brand= : Brand name filter}
        {--products= : Comma-separated product IDs}
        {--source-urls= : Comma-separated product_id=url pairs; overrides supplier_products.source_url for listed products}
        {--limit=30 : Max products per run, 0 means no limit}
        {--offset=0 : Skip repair candidates}
        {--apply : Download and write images; default is dry-run}
        {--force : Process even products whose main image currently looks OK}
        {--fallback-remote-images : Save remote image URLs when downloads fail}
        {--sleep=1200 : Delay between source requests, ms}';

    protected $description = 'Repair empty or broken product images from supplier_products.source_url without touching healthy product cards.';

    public function handle(ProductSourceEnricher $enricher): int
    {
        $apply = (bool) $this->option('apply');
        $limit = max(0, (int) $this->option('limit'));
        $offset = max(0, (int) $this->option('offset'));
        $sleep = max(300, (int) $this->option('sleep'));
        $force = (bool) $this->option('force');
        $all = (bool) $this->option('all');
        $fallbackRemoteImages = (bool) $this->option('fallback-remote-images');

        $categoryIds = $this->categoryIds();
        $supplier = $this->supplier();
        $brandId = $this->brandId();
        $productIds = $this->productIds((string) $this->option('products'));

        if (trim((string) $this->option('supplier')) !== '' && $supplier === null) {
            return self::FAILURE;
        }

        if (! $all && $categoryIds->isEmpty() && $supplier === null && $brandId === null && $productIds === []) {
            $this->error('--all, --category, --supplier, --brand or --products is required.');

            return self::FAILURE;
        }

        $sourceUrlOverrides = $this->sourceUrlOverrides((string) $this->option('source-urls'));
        $sourceUrls = DB::table('supplier_products')
            ->whereNotNull('product_id')
            ->whereNotNull('source_url')
            ->where('source_url', 'like', 'http%')
            ->where('source_url', 'not like', '%docs.google.com/spreadsheets%')
            ->when($supplier, fn ($query) => $query->where('supplier_id', (int) $supplier->id))
            ->orderByDesc('updated_at')
            ->get(['product_id', 'source_url'])
            ->groupBy('product_id')
            ->map(fn ($rows) => (string) $rows->first()->source_url);

        foreach ($sourceUrlOverrides as $productId => $sourceUrl) {
            $sourceUrls->put($productId, $sourceUrl);
        }

        $candidates = Product::query()
            ->orderable()
            ->when($categoryIds->isNotEmpty(), fn ($query) => $query->whereIn('category_id', $categoryIds))
            ->when($supplier, fn ($query) => $query->whereIn('products.id', function ($subquery) use ($supplier): void {
                $subquery->from('supplier_products')
                    ->select('product_id')
                    ->where('supplier_id', (int) $supplier->id)
                    ->whereNotNull('product_id');
            }))
            ->when($brandId !== null, fn ($query) => $query->where('brand_id', $brandId))
            ->when($productIds !== [], fn ($query) => $query->whereIn('id', $productIds))
            ->with(['brand:id,name', 'category:id,name,slug'])
            ->orderBy('sort_order')
            ->orderByDesc('is_featured')
            ->orderByDesc('created_at')
            ->orderBy('name')
            ->get(['id', 'category_id', 'brand_id', 'name', 'slug', 'sku', 'images'])
            ->filter(function (Product $product) use ($force): bool {
                if ($force) {
                    return true;
                }

                return $this->imageStatus($product)[0] !== 'ok';
            })
            ->values();

        $total = $candidates->count();
        if ($offset > 0) {
            $candidates = $candidates->slice($offset)->values();
        }
        if ($limit > 0) {
            $candidates = $candidates->take($limit)->values();
        }

        $this->line($apply
            ? '<fg=red;options=bold>APPLY: broken/empty product images will be replaced from source URLs.</>'
            : '<fg=yellow;options=bold>DRY RUN: source image repair preview only.</>');
        $this->info(sprintf('Repair candidates: %d (processing %d, offset %d)', $total, $candidates->count(), $offset));

        $stats = [
            'processed' => 0,
            'would_repair' => 0,
            'repaired' => 0,
            'images_found' => 0,
            'images_saved' => 0,
            'no_source_url' => 0,
            'no_source_images' => 0,
            'errors' => 0,
        ];

        foreach ($candidates as $product) {
            $stats['processed']++;
            [$status, $detail] = $this->imageStatus($product);
            $sourceUrl = (string) $sourceUrls->get($product->id);

            $this->line(sprintf(
                '[%d/%d] #%d %s %s (%s)',
                $stats['processed'],
                $candidates->count(),
                $product->id,
                $product->slug,
                mb_strimwidth($product->name, 0, 62, '...'),
                $status
            ));
            $this->line('  current: ' . $detail);
            $this->line('  source: ' . $sourceUrl);

            if ($sourceUrl === '') {
                $stats['no_source_url']++;
                $this->warn('  skipped: no source_url found for this product');
                continue;
            }

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
                    'fallback_remote_images' => $fallbackRemoteImages,
                ]);

                $imagesFound = (int) ($result['images_found'] ?? 0);
                $imagesSaved = (int) ($result['images_saved'] ?? 0);
                $stats['images_found'] += $imagesFound;
                $stats['images_saved'] += $imagesSaved;

                if ($imagesFound === 0) {
                    $stats['no_source_images']++;
                } elseif ($apply && in_array('images', $result['updated_fields'] ?? [], true)) {
                    $stats['repaired']++;
                } elseif (! $apply) {
                    $stats['would_repair']++;
                }

                $this->line(sprintf(
                    '  found=%d saved=%d%s',
                    $imagesFound,
                    $imagesSaved,
                    ($result['updated_fields'] ?? []) !== [] ? ' updated=' . implode(',', $result['updated_fields']) : ''
                ));

                foreach (($result['errors'] ?? []) as $error) {
                    $this->warn('  warning: ' . $error);
                }
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

        return $this->collectCategoryAndDescendantIds((int) $category->id);
    }

    private function brandId(): ?int
    {
        $name = trim((string) $this->option('brand'));
        if ($name === '') {
            return null;
        }

        $id = DB::table('brands')
            ->where('is_active', true)
            ->where(function ($query) use ($name): void {
                $query->where('name', $name)
                    ->orWhere('name', 'like', $name . '%')
                    ->orWhere('name', 'like', '%' . $name . '%');
            })
            ->orderByRaw('CASE WHEN name = ? THEN 0 WHEN name LIKE ? THEN 1 ELSE 2 END', [$name, $name . '%'])
            ->value('id');

        if (! $id) {
            $this->error('Brand not found: ' . $name);

            return null;
        }

        return (int) $id;
    }

    private function supplier(): ?object
    {
        $value = trim((string) $this->option('supplier'));
        if ($value === '') {
            return null;
        }

        $supplier = DB::table('suppliers')
            ->where('code', $value)
            ->when(is_numeric($value), fn ($query) => $query->orWhere('id', (int) $value))
            ->first(['id', 'code', 'name']);

        if (! $supplier) {
            $this->error('Supplier not found: ' . $value);
        }

        return $supplier;
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

        $url = $product->imageUrl(0);
        if (str_contains($url, 'product-placeholder')) {
            return ['placeholder', $url];
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

        $skuPath = $this->legacySkuPath($product, $raw);
        if ($skuPath !== null && file_exists(public_path('images/' . $skuPath))) {
            return ['ok', 'legacy:' . $skuPath];
        }

        $idPath = $this->legacyIdPath($product, $raw);

        return file_exists(public_path('images/' . $idPath)) ? ['ok', 'legacy:' . $idPath] : ['broken', 'legacy:' . $idPath];
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

    /**
     * @return int[]
     */
    private function productIds(string $value): array
    {
        if (trim($value) === '') {
            return [];
        }

        $ids = [];
        foreach (explode(',', $value) as $part) {
            $id = (int) trim($part);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return array<int, string>
     */
    private function sourceUrlOverrides(string $value): array
    {
        if (trim($value) === '') {
            return [];
        }

        $pairs = [];
        foreach (explode(',', $value) as $part) {
            [$id, $url] = array_pad(explode('=', trim($part), 2), 2, '');
            $id = (int) trim($id);
            $url = trim($url);

            if ($id > 0 && filter_var($url, FILTER_VALIDATE_URL)) {
                $pairs[$id] = $url;
            }
        }

        return $pairs;
    }

    private function collectCategoryAndDescendantIds(int $categoryId): Collection
    {
        $categoriesByParent = Category::query()
            ->get(['id', 'parent_id'])
            ->groupBy('parent_id');

        $ids = collect([$categoryId]);
        $stack = [$categoryId];

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
}
