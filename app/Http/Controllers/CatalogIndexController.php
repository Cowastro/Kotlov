<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;

class CatalogIndexController extends Controller
{
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

        $title       = 'Каталог товаров — котлы, печи, камины, дымоходы и отопление в Беларуси';
        $description = 'Более 7 000 товаров для отопления: котлы, печи, камины, дымоходы, тепловые насосы, водонагреватели. Доставка по Беларуси. Маркетплейс KOTLOV.';
        $keywords    = 'каталог котлов, купить печь, камины беларусь, дымоходы, отопление, тепловые насосы, водонагреватели';
        $ogImage     = asset('img/popular/boiler_img.jpg');

        return view('pages.catalog-index', compact('rootCategories', 'title', 'description', 'keywords', 'ogImage'));
    }
}
