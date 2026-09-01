<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AuditProductImagesCommand extends Command
{
    protected $signature = 'catalog:audit-product-images
        {--all : Audit all orderable products}
        {--category= : Category slug; includes descendants}
        {--supplier= : Supplier code or id}
        {--missing-only : Show only empty, placeholder or broken images}
        {--summary-by-brand : Show missing image counts grouped by brand}
        {--icons : Also audit public/icons SVG files}
        {--limit=100 : Max product rows to show, 0 means no limit}';

    protected $description = 'Audit public product image state and category navigation icons.';

    public function handle(): int
    {
        if ((bool) $this->option('icons')) {
            $this->auditIcons();
        }

        $categorySlug = trim((string) $this->option('category'));
        $supplierFilter = trim((string) $this->option('supplier'));
        $allProducts = (bool) $this->option('all');
        if (! $allProducts && $categorySlug === '' && $supplierFilter === '') {
            if (! (bool) $this->option('icons')) {
                $this->error('--all, --category, --supplier or --icons is required.');

                return self::FAILURE;
            }

            return self::SUCCESS;
        }

        $category = null;
        $categoryIds = collect();
        if ($categorySlug !== '') {
            $category = Category::query()
                ->where('slug', $categorySlug)
                ->first(['id', 'name', 'slug']);

            if (! $category) {
                $this->error('Category not found: ' . $categorySlug);

                return self::FAILURE;
            }

            $categoryIds = $this->collectCategoryAndDescendantIds((int) $category->id);
        }

        $supplier = null;
        if ($supplierFilter !== '') {
            $supplier = DB::table('suppliers')
                ->where('code', $supplierFilter)
                ->when(is_numeric($supplierFilter), fn ($query) => $query->orWhere('id', (int) $supplierFilter))
                ->first(['id', 'code', 'name']);

            if (! $supplier) {
                $this->error('Supplier not found: ' . $supplierFilter);

                return self::FAILURE;
            }
        }

        $sourceUrlQuery = DB::table('supplier_products')
            ->whereNotNull('product_id')
            ->whereNotNull('source_url')
            ->where('source_url', 'like', 'http%');

        if ($supplier) {
            $sourceUrlQuery->where('supplier_id', (int) $supplier->id);
        }

        $sourceUrls = $sourceUrlQuery
            ->orderByDesc('updated_at')
            ->get(['product_id', 'source_url'])
            ->groupBy('product_id')
            ->map(fn ($rows) => (string) $rows->first()->source_url);

        $summary = [
            'checked' => 0,
            'ok' => 0,
            'empty' => 0,
            'placeholder' => 0,
            'broken' => 0,
            'remote' => 0,
            'with_source_url' => 0,
        ];

        $rows = [];
        $brandSummary = [];
        $limit = max(0, (int) $this->option('limit'));
        $missingOnly = (bool) $this->option('missing-only');
        $summaryByBrand = (bool) $this->option('summary-by-brand');

        Product::query()
            ->orderable()
            ->when($categoryIds->isNotEmpty(), fn ($query) => $query->whereIn('category_id', $categoryIds))
            ->when($supplier, fn ($query) => $query->whereIn('products.id', function ($subquery) use ($supplier): void {
                $subquery->from('supplier_products')
                    ->select('product_id')
                    ->where('supplier_id', (int) $supplier->id)
                    ->whereNotNull('product_id');
            }))
            ->with(['brand:id,name', 'category:id,name,slug'])
            ->orderBy('sort_order')
            ->orderByDesc('is_featured')
            ->orderByDesc('created_at')
            ->orderBy('name')
            ->get(['id', 'category_id', 'brand_id', 'name', 'slug', 'sku', 'images'])
            ->each(function (Product $product) use (&$summary, &$rows, &$brandSummary, $sourceUrls, $missingOnly, $limit, $summaryByBrand): void {
                $summary['checked']++;

                $sourceUrl = (string) ($sourceUrls->get($product->id) ?? '');
                if ($sourceUrl !== '') {
                    $summary['with_source_url']++;
                }

                [$status, $detail] = $this->imageStatus($product);
                $summary[$status]++;
                if ($summaryByBrand) {
                    $brand = (string) ($product->brand?->name ?? 'Без бренда');
                    $brandSummary[$brand] ??= [
                        'brand' => $brand,
                        'checked' => 0,
                        'missing' => 0,
                        'empty' => 0,
                        'placeholder' => 0,
                        'broken' => 0,
                        'remote' => 0,
                        'with_source_url' => 0,
                    ];
                    $brandSummary[$brand]['checked']++;
                    $brandSummary[$brand]['with_source_url'] += $sourceUrl !== '' ? 1 : 0;

                    if (in_array($status, ['empty', 'placeholder', 'broken'], true)) {
                        $brandSummary[$brand]['missing']++;
                        $brandSummary[$brand][$status]++;
                    } elseif ($status === 'remote') {
                        $brandSummary[$brand]['remote']++;
                    }
                }

                if ($missingOnly && $status === 'ok') {
                    return;
                }

                if ($limit !== 0 && count($rows) >= $limit) {
                    return;
                }

                $rows[] = [
                    $product->id,
                    $product->slug,
                    mb_strimwidth((string) ($product->brand?->name ?? ''), 0, 16, '...'),
                    mb_strimwidth($product->name, 0, 44, '...'),
                    $status,
                    $sourceUrl !== '' ? 'yes' : 'no',
                    mb_strimwidth($detail, 0, 72, '...'),
                ];
            });

        $scope = [];
        if ($category) {
            $scope[] = sprintf('%s (%s)', $category->slug, $category->name);
        }
        if ($supplier) {
            $scope[] = sprintf('supplier %s #%d (%s)', $supplier->code, $supplier->id, $supplier->name);
        }
        if ($allProducts) {
            $scope[] = 'all orderable products';
        }

        $this->line('Products in ' . implode(' + ', $scope));
        $this->table(['metric', 'count'], collect($summary)->map(fn ($count, $metric) => [$metric, $count])->values()->all());

        if ($summaryByBrand && $brandSummary !== []) {
            $brandRows = collect($brandSummary)
                ->filter(fn (array $row) => $row['missing'] > 0 || $row['remote'] > 0)
                ->sortByDesc('missing')
                ->values()
                ->map(fn (array $row) => [
                    $row['brand'],
                    $row['checked'],
                    $row['missing'],
                    $row['empty'],
                    $row['placeholder'],
                    $row['broken'],
                    $row['remote'],
                    $row['with_source_url'],
                ])
                ->all();

            $this->table(['brand', 'checked', 'missing', 'empty', 'placeholder', 'broken', 'remote', 'source URLs'], $brandRows);
        }

        if ($rows !== []) {
            $this->table(['id', 'slug', 'brand', 'name', 'image', 'source', 'detail'], $rows);
        }

        return self::SUCCESS;
    }

    private function auditIcons(): void
    {
        $icons = config('navigation.icons', []);
        $rows = [];
        $missing = 0;

        foreach ($icons as $slug => $file) {
            $path = public_path('icons/' . $file);
            $exists = file_exists($path);
            $missing += $exists ? 0 : 1;
            $rows[] = [$slug, $file, $exists ? 'ok' : 'missing', $path];
        }

        $this->line('Navigation icons');
        $this->line('public_path: ' . public_path());
        $this->table(['metric', 'count'], [
            ['configured', count($icons)],
            ['missing', $missing],
        ]);
        $this->table(['slug', 'file', 'status', 'path'], $rows);
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
            return ['remote', $raw];
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
