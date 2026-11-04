<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;

class CatalogIndexController extends Controller
{
    public function index(Request $request)
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
            $category->catalogThumb = $category->catalogImageUrl($descendantIds((int) $category->id));
            $category->setRelation('catalogTop', $category->catalogChildren->sortByDesc('products_count')->take(6)->values());
        });

        $popularCategories = $rootCategories
            ->sortByDesc('products_count')
            ->take(3)
            ->values();

        $currentCity = view()->shared('currentCity');
        $isCityStorefront = $currentCity?->exists && $currentCity->slug !== 'minsk';
        $cityIn = $isCityStorefront ? $currentCity->name_in : 'в Беларуси';
        $cityTitle = $isCityStorefront ? $currentCity->name_title : 'Беларуси';

        $title = $isCityStorefront
            ? 'Каталог отопительного оборудования в '.$cityTitle.' | KOTLOV'
            : 'Каталог товаров — котлы, печи, камины, дымоходы и отопление в Беларуси';
        $description = $isCityStorefront
            ? 'Котлы, печи, камины, дымоходы, тепловые насосы и водонагреватели '.$cityIn.'. Заказ через местный филиал KOTLOV, помощь с подбором и комплектацией.'
            : 'Более 7 000 товаров для отопления: котлы, печи, камины, дымоходы, тепловые насосы, водонагреватели. Доставка по Беларуси. Маркетплейс KOTLOV.';
        $keywords = $isCityStorefront
            ? 'каталог отопления '.$cityTitle.', котлы, печи, камины, дымоходы, тепловые насосы'
            : 'каталог котлов, купить печь, камины беларусь, дымоходы, отопление, тепловые насосы, водонагреватели';
        $ogImage = asset('img/popular/boiler_img.jpg');
        $canonical = 'https://'.$request->getHost().'/catalog';

        return view('pages.catalog-index', compact(
            'rootCategories', 'popularCategories', 'title', 'description', 'keywords', 'ogImage',
            'canonical', 'currentCity', 'isCityStorefront', 'cityIn', 'cityTitle'
        ));
    }
}
