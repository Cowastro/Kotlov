<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\Cache;

class CategoryMetadataImage
{
    public function resolve(Category $category, ?array $categoryIds = null, bool $fresh = false): ?array
    {
        $categoryIds ??= $this->descendantIds($category);
        sort($categoryIds);
        $key = 'seo:category-photo:v1:'.$category->id.':'.md5(json_encode([$category->image, $categoryIds]));
        if ($fresh) {
            Cache::forget($key);
        }

        // Cache paths, not absolute URLs: the same category is used on city domains.
        $image = Cache::remember($key, 3600, fn () => $this->select($category, $categoryIds) ?? []);

        return $image ? [...$image, 'url' => url($image['path'])] : null;
    }

    private function select(Category $category, array $categoryIds): ?array
    {
        if (filled($category->image) && ($image = $this->localImage($category->image_url))) {
            return [...$image, 'source' => 'category'];
        }

        // Prefer a real product from this exact category over a broad stock picture.
        $products = Product::query()->active()->notArchived()
            ->whereIn('category_id', $categoryIds)
            ->whereNotNull('images')->where('images', '!=', '[]')
            ->orderByRaw('CASE WHEN category_id = ? THEN 0 ELSE 1 END', [$category->id])
            ->orderByDesc('in_stock')->orderByDesc('is_featured')->orderBy('id')
            ->select(['id', 'category_id', 'sku', 'images']);

        foreach ($products->cursor() as $product) {
            foreach (array_keys($product->imagePaths()) as $index) {
                if ($image = $this->localImage($product->imageUrl($index))) {
                    return [...$image, 'source' => 'product:'.$product->id];
                }
            }
        }

        foreach (['img/popular/'.$category->slug.'.jpg', Category::fallbackImagePath($category->slug)] as $path) {
            if ($path && ($image = $this->localImage('/'.$path))) {
                return [...$image, 'source' => 'category-stock'];
            }
        }

        return null;
    }

    private function localImage(string $url): ?array
    {
        $host = parse_url($url, PHP_URL_HOST);
        if ($host && $host !== 'kotlov.by' && ! str_ends_with($host, '.kotlov.by') && $host !== request()->getHost()) {
            return null;
        }
        $path = rawurldecode((string) parse_url($url, PHP_URL_PATH));
        if ($path === '' || str_contains($path, '..') || preg_match('~placeholder|no[-_]?image|kotlov-default|/logo[./]~i', $path)) {
            return null;
        }
        $filePath = str_starts_with($path, '/proxy-image/')
            ? 'images/'.substr($path, strlen('/proxy-image/')) : ltrim($path, '/');
        $file = public_path($filePath);
        if (! is_file($file)) {
            return null;
        }
        $size = @getimagesize($file);
        if (! $size || min($size[0], $size[1]) < 100 || ! in_array($size['mime'], ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/avif'], true)) {
            return null;
        }

        return ['path' => $path, 'width' => $size[0], 'height' => $size[1], 'type' => $size['mime']];
    }

    public function descendantIds(Category $category): array
    {
        $children = Category::active()->get(['id', 'parent_id'])->groupBy('parent_id');
        $ids = [(int) $category->id];
        for ($i = 0; $i < count($ids); $i++) {
            foreach ($children->get($ids[$i], collect()) as $child) {
                if (! in_array((int) $child->id, $ids, true)) {
                    $ids[] = (int) $child->id;
                }
            }
        }

        return $ids;
    }
}
