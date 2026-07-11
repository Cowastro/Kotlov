<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class AuditCatalogMediaCommand extends Command
{
    protected $signature = 'catalog:audit-media
        {--type=all : all, categories or brands}
        {--only-with-products : Only rows with orderable products}
        {--missing-only : Show only missing or broken media}
        {--limit=200 : Max rows per section, 0 means no limit}';

    protected $description = 'Audit category images and brand logos used by the public catalog.';

    public function handle(): int
    {
        $type = (string) $this->option('type');
        $limit = max(0, (int) $this->option('limit'));

        if (! in_array($type, ['all', 'categories', 'brands'], true)) {
            $this->error('--type must be all, categories or brands.');

            return self::FAILURE;
        }

        if ($type !== 'brands') {
            $this->auditCategories($limit);
        }

        if ($type !== 'categories') {
            $this->auditBrands($limit);
        }

        return self::SUCCESS;
    }

    private function auditCategories(int $limit): void
    {
        $rows = [];
        $summary = ['checked' => 0, 'missing' => 0, 'broken' => 0, 'fallback' => 0, 'ok' => 0];

        $counts = Product::query()
            ->orderable()
            ->whereNotNull('category_id')
            ->selectRaw('category_id, COUNT(*) as aggregate')
            ->groupBy('category_id')
            ->pluck('aggregate', 'category_id');

        $query = Category::query()
            ->where('is_active', true)
            ->orderBy('parent_id')
            ->orderBy('sort_order');

        foreach ($query->get(['id', 'parent_id', 'name', 'slug', 'image']) as $category) {
            $productsCount = (int) ($counts[$category->id] ?? 0);
            if ((bool) $this->option('only-with-products') && $productsCount === 0) {
                continue;
            }

            $summary['checked']++;
            [$status, $path] = $this->categoryMediaStatus($category->slug, $category->image);
            $summary[$status]++;

            if ((bool) $this->option('missing-only') && in_array($status, ['ok', 'fallback'], true)) {
                continue;
            }

            if ($limit === 0 || count($rows) < $limit) {
                $rows[] = [
                    $category->id,
                    $category->parent_id,
                    $category->slug,
                    mb_strimwidth((string) $category->name, 0, 42, '...'),
                    $productsCount,
                    $status,
                    $path,
                ];
            }
        }

        $this->line('Categories');
        $this->table(['metric', 'count'], collect($summary)->map(fn ($count, $metric) => [$metric, $count])->values()->all());
        if ($rows !== []) {
            $this->table(['id', 'parent', 'slug', 'name', 'products', 'media', 'path'], $rows);
        }
    }

    private function auditBrands(int $limit): void
    {
        $rows = [];
        $summary = ['checked' => 0, 'missing' => 0, 'broken' => 0, 'fallback' => 0, 'ok' => 0];

        $query = Brand::query()
            ->where('is_active', true)
            ->withCount(['products' => fn ($q) => $q->orderable()])
            ->orderBy('name');

        foreach ($query->get(['id', 'name', 'slug', 'logo']) as $brand) {
            $productsCount = (int) $brand->products_count;
            if ((bool) $this->option('only-with-products') && $productsCount === 0) {
                continue;
            }

            $summary['checked']++;
            [$status, $path] = $this->mediaStatus($brand->logo, true);
            $summary[$status]++;

            if ((bool) $this->option('missing-only') && $status === 'ok') {
                continue;
            }

            if ($limit === 0 || count($rows) < $limit) {
                $rows[] = [
                    $brand->id,
                    $brand->slug,
                    mb_strimwidth((string) $brand->name, 0, 42, '...'),
                    $productsCount,
                    $status,
                    $path,
                ];
            }
        }

        $this->line('Brands');
        $this->table(['metric', 'count'], collect($summary)->map(fn ($count, $metric) => [$metric, $count])->values()->all());
        if ($rows !== []) {
            $this->table(['id', 'slug', 'name', 'products', 'media', 'path'], $rows);
        }
    }

    private function mediaStatus(?string $path, bool $allowLegacyProxy = false): array
    {
        $path = trim((string) $path);
        if ($path === '') {
            return ['missing', '-'];
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return ['ok', $path];
        }

        if (Storage::disk('public')->exists($path)) {
            return ['ok', $path];
        }

        if ($allowLegacyProxy && file_exists(public_path('images/' . ltrim($path, '/')))) {
            return ['ok', 'legacy:' . $path];
        }

        return ['broken', $path];
    }

    private function categoryMediaStatus(?string $slug, ?string $path): array
    {
        $fallback = Category::fallbackImagePath((string) $slug);
        if ($fallback && file_exists(public_path($fallback))) {
            return ['fallback', $fallback];
        }

        [$status, $resolvedPath] = $this->mediaStatus($path);
        if ($status !== 'missing') {
            return [$status, $resolvedPath];
        }

        return [$status, $resolvedPath];
    }
}
