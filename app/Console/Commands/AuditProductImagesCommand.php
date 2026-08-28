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
        {--category= : Category slug; includes descendants}
        {--missing-only : Show only empty, placeholder or broken images}
        {--icons : Also audit public/icons SVG files}
        {--limit=100 : Max product rows to show, 0 means no limit}';

    protected $description = 'Audit public product image state and category navigation icons.';

    public function handle(): int
    {
        if ((bool) $this->option('icons')) {
            $this->auditIcons();
        }

        $categorySlug = trim((string) $this->option('category'));
        if ($categorySlug === '') {
            if (! (bool) $this->option('icons')) {
                $this->error('--category or --icons is required.');

                return self::FAILURE;
            }

            return self::SUCCESS;
        }

        $category = Category::query()
            ->where('slug', $categorySlug)
            ->first(['id', 'name', 'slug']);

        if (! $category) {
            $this->error('Category not found: ' . $categorySlug);

            return self::FAILURE;
        }

        $categoryIds = $this->collectCategoryAndDescendantIds((int) $category->id);
        $sourceUrls = DB::table('supplier_products')
            ->whereNotNull('product_id')
            ->whereNotNull('source_url')
            ->where('source_url', 'like', 'http%')
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
        $limit = max(0, (int) $this->option('limit'));
        $missingOnly = (bool) $this->option('missing-only');

        Product::query()
            ->orderable()
            ->whereIn('category_id', $categoryIds)
            ->with(['brand:id,name', 'category:id,name,slug'])
            ->orderBy('sort_order')
            ->orderByDesc('is_featured')
            ->orderByDesc('created_at')
            ->orderBy('name')
            ->get(['id', 'category_id', 'brand_id', 'name', 'slug', 'sku', 'images'])
            ->each(function (Product $product) use (&$summary, &$rows, $sourceUrls, $missingOnly, $limit): void {
                $summary['checked']++;

                $sourceUrl = (string) ($sourceUrls->get($product->id) ?? '');
                if ($sourceUrl !== '') {
                    $summary['with_source_url']++;
                }

                [$status, $detail] = $this->imageStatus($product);
                $summary[$status]++;

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

        $this->line(sprintf('Products in %s (%s)', $category->slug, $category->name));
        $this->table(['metric', 'count'], collect($summary)->map(fn ($count, $metric) => [$metric, $count])->values()->all());

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
