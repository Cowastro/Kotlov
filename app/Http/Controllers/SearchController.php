<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Services\StorefrontProductSearch;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function index(Request $request)
    {
        $q = mb_substr(trim($request->get('q', '')), 0, 100);
        $category = $request->get('category', '');
        $sort = $request->get('sort', '');

        $products = collect();
        $total = 0;

        if (mb_strlen($q) >= 1) {
            $query = Product::active()
                ->notArchived()
                ->withSupplierChannels()
                ->with(['category', 'brand']);
            app(StorefrontProductSearch::class)->apply($query, $q);

            if ($category) {
                $cat = Category::where('slug', $category)->first();
                if ($cat) {
                    $childIds = Category::where('parent_id', $cat->id)->pluck('id');
                    $allIds = $childIds->prepend($cat->id);
                    $query->whereIn('category_id', $allIds);
                }
            }

            switch ($sort) {
                case 'price_asc':  $query->orderBy('price');
                    break;
                case 'price_desc': $query->orderByDesc('price');
                    break;
                case 'new':        $query->orderByDesc('id');
                    break;
                default:           $query->orderByDesc('is_featured')->orderByDesc('rating');
            }

            $total = $query->count();
            $products = $query->paginate(24)->withQueryString();
        }

        $quickLinks = Category::whereIn('slug', [
            'kotly', 'teplovye-nasosy', 'pechki', 'kaminy', 'dymohody', 'vodonagrevateli',
        ])->where('is_active', true)->get();

        return view('pages.search', compact('products', 'q', 'total', 'quickLinks', 'sort', 'category'));
    }

    /**
     * AJAX автодополнение — возвращает JSON подсказки
     * GET /search/suggest?q=котел
     */
    public function suggest(Request $request)
    {
        $q = mb_substr(trim($request->get('q', '')), 0, 100);

        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        // Товары
        $search = app(StorefrontProductSearch::class);
        $products = Product::active()
            ->notArchived();
        $search->apply($products, $q);
        $products = $products
            ->select('id', 'name', 'slug', 'price', 'category_id')
            ->with('category:id,slug')
            ->orderByDesc('rating')
            ->limit(6)
            ->get()
            ->map(fn ($p) => [
                'type' => 'product',
                'name' => $p->name,
                'url' => '/'.($p->category->slug ?? 'catalog').'/'.$p->slug,
                'price' => $p->price > 0
                    ? number_format($p->price, 2, '.', ' ').' BYN'
                    : 'Цена по запросу',
            ]);

        // Категории
        $categories = Category::where('is_active', true)
            ->where(function ($query) use ($search, $q): void {
                foreach ($search->variants($q) as $variant) {
                    foreach ($search->caseVariants($variant) as $caseVariant) {
                        $query->orWhere('name', 'like', '%'.$caseVariant.'%');
                    }
                }
            })
            ->whereHas('products', fn ($products) => $products->active()->notArchived())
            ->select('id', 'name', 'slug')
            ->limit(3)
            ->get()
            ->map(fn ($c) => [
                'type' => 'category',
                'name' => $c->name,
                'url' => '/'.$c->slug,
            ]);

        return response()->json($categories->concat($products)->values());
    }
}
