<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\Cache;

class CatalogIndexController extends Controller
{
    /** Stock pictures of sections whose slug names no file of its own. */
    private const STOCK_IMAGES = [
        'vodosnabzhenie' => 'img/popular/nasosy.jpg',
        'klimat' => 'img/popular/air.jpg',
    ];

    public function index()
    {
        $categories = Category::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $categoriesByParent = $categories->groupBy('parent_id');
        $directProductCounts = Product::query()
            ->orderable()
            ->whereNotNull('category_id')
            ->selectRaw('category_id, COUNT(*) as aggregate')
            ->groupBy('category_id')
            ->pluck('aggregate', 'category_id');

        $branchCounts = [];
        $countBranchProducts = function (int $categoryId) use (&$countBranchProducts, &$branchCounts, $categoriesByParent, $directProductCounts): int {
            if (array_key_exists($categoryId, $branchCounts)) {
                return $branchCounts[$categoryId];
            }

            $count = (int) ($directProductCounts[$categoryId] ?? 0);
            foreach ($categoriesByParent->get($categoryId, collect()) as $child) {
                $count += $countBranchProducts((int) $child->id);
            }

            return $branchCounts[$categoryId] = $count;
        };

        $rootCategories = $categoriesByParent
            ->get(0, collect())
            ->sortBy('sort_order')
            ->values()
            ->each(fn (Category $category) => $category->products_count = $countBranchProducts((int) $category->id))
            ->filter(fn (Category $category) => $category->products_count > 0)
            ->values();

        $attachCatalogChildren = function (Category $category) use (&$attachCatalogChildren, $categoriesByParent, $countBranchProducts): Category {
            $children = $categoriesByParent
                ->get((int) $category->id, collect())
                ->each(fn (Category $child) => $child->products_count = $countBranchProducts((int) $child->id))
                ->filter(fn (Category $child) => $child->products_count > 0)
                ->sortBy(fn (Category $child) => mb_strtolower($child->name))
                ->values()
                ->map(fn (Category $child) => $attachCatalogChildren($child));

            $category->setRelation('catalogChildren', $children);

            return $category;
        };

        $rootCategories = $rootCategories
            ->map(fn (Category $category) => $attachCatalogChildren($category))
            ->values();

        // A compact card per section: its picture (never empty) and its largest subcategories.
        $descendantIds = function (int $categoryId) use (&$descendantIds, $categoriesByParent): array {
            return array_merge([$categoryId], ...$categoriesByParent->get($categoryId, collect())
                ->map(fn (Category $child) => $descendantIds((int) $child->id))->all());
        };
        $rootCategories->each(function (Category $category) use ($descendantIds): void {
            $category->catalogThumb = $this->thumb($category, $descendantIds((int) $category->id));
            $category->setRelation('catalogTop', $category->catalogChildren->sortByDesc('products_count')->take(6)->values());
        });

        $popularCategories = $rootCategories
            ->sortByDesc('products_count')
            ->take(3)
            ->values();

        $title       = 'Каталог товаров — котлы, печи, камины, дымоходы и отопление в Беларуси';
        $description = 'Более 7 000 товаров для отопления: котлы, печи, камины, дымоходы, тепловые насосы, водонагреватели. Доставка по Беларуси. Маркетплейс KOTLOV.';
        $keywords    = 'каталог котлов, купить печь, камины беларусь, дымоходы, отопление, тепловые насосы, водонагреватели';
        $ogImage     = asset('img/popular/boiler_img.jpg');

        return view('pages.catalog-index', compact('rootCategories', 'popularCategories', 'title', 'description', 'keywords', 'ogImage'));
    }

    /** The section picture: its own, a stock one, else a photo of an orderable product of the section. */
    private function thumb(Category $category, array $categoryIds): string
    {
        if (trim((string) $category->image) !== '') {
            return $category->imageUrl();
        }
        $stock = Category::fallbackImagePath($category->slug)
            ?? (is_file(public_path('img/popular/'.$category->slug.'.jpg')) ? 'img/popular/'.$category->slug.'.jpg' : null)
            ?? self::STOCK_IMAGES[$category->slug] ?? null;
        if ($stock !== null) {
            return asset($stock);
        }

        return Cache::remember('catalog-index:thumb:'.$category->id, 86400, function () use ($categoryIds): string {
            $product = Product::query()->orderable()->whereIn('category_id', $categoryIds)
                ->whereNotNull('images')->where('images', '!=', '[]')
                ->orderByDesc('is_featured')->orderBy('id')
                ->first(['id', 'images']);

            return $product?->imageUrl(0) ?? asset('img/popular/catalog.jpg');
        });
    }
}
