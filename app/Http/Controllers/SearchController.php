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
            $includeInternal = auth()->user()?->is_active === true && auth()->user()?->isManager();
            $query = Product::active()
                ->notArchived()
                ->withSupplierChannels()
                ->with(['category', 'brand']);
            app(StorefrontProductSearch::class)->apply($query, $q, $includeInternal);

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
        $includeInternal = auth()->user()?->is_active === true && auth()->user()?->isManager();
        $products = Product::active()
            ->notArchived();
        $search->apply($products, $q, $includeInternal);
        $products = $products
            ->select('id', 'name', 'slug', 'sku', 'images', 'price', 'requires_price_confirmation', 'category_id')
            ->with('category:id,slug')
            ->orderByDesc('rating')
            ->limit(6)
            ->get()
            ->map(fn ($p) => [
                'type' => 'product',
                'name' => $p->name,
                'url' => '/'.($p->category->slug ?? 'catalog').'/'.$p->slug,
                'image' => $p->image_url,
                'price' => $p->hasVerifiedPublicPrice()
                    ? number_format($p->price, 2, '.', ' ').' BYN'
                    : 'Цену уточняйте',
            ]);

        // Категории
        $categories = Category::where('is_active', true);
        $search->applyCategory($categories, $q);
        $categories = $categories
            ->whereHas('products', fn ($products) => $products->active()->notArchived())
            ->select('id', 'name', 'slug', 'parent_id')
            ->with('parent:id,name')
            ->limit(3)
            ->get()
            ->map(fn ($c) => [
                'type' => 'category',
                'name' => $c->name,
                'context' => $c->parent?->name,
                'url' => '/'.$c->slug,
            ]);

        return response()->json($categories->concat($products)->values());
    }
}
