<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Services\StorefrontProductSearch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

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
            app(StorefrontProductSearch::class)->applyForResults($query, $q, $includeInternal);

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
                default:           $query->orderByDesc('is_featured')
                    ->orderByDesc('rating')
                    ->orderByDesc('id');
            }

            $products = $query->paginate(24)->withQueryString();
            $total = $products->total();
        }

        $quickLinks = Category::whereIn('slug', [
            'kotly', 'teplovyie-nasosyi', 'pechki', 'kaminy', 'dymohody', 'vodonagrevateli',
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

        $search = app(StorefrontProductSearch::class);
        $includeInternal = auth()->user()?->is_active === true && auth()->user()?->isManager();
        $cacheKey = 'storefront-search-suggest:v3:'.($includeInternal ? 'internal:' : 'public:').sha1(mb_strtolower($q));

        $suggestions = Cache::remember(
            $cacheKey,
            now()->addSeconds($includeInternal ? 30 : 180),
            fn (): array => $this->buildSuggestions($search, $q, $includeInternal)
        );

        return response()->json($suggestions);
    }

    /** @return list<array<string, mixed>> */
    private function buildSuggestions(StorefrontProductSearch $search, string $q, bool $includeInternal): array
    {
        $limit = $search->isHeatingElementQuery($q) ? 10 : 8;

        $productQuery = fn () => Product::active()
            ->notArchived()
            ->select('id', 'name', 'slug', 'sku', 'images', 'price', 'requires_price_confirmation', 'category_id', 'rating')
            ->with([
                'category:id,slug',
                'supplierProducts.supplier.latestChannelTransition',
                'integrationProducts.source.supplier',
                'purchasePriceSources.supplier',
            ])
            ->orderByDesc('rating')
            ->orderByDesc('id')
            ->limit($limit);

        $productsQuery = $productQuery();
        $search->applyForSuggestions($productsQuery, $q, $includeInternal);
        $products = $productsQuery->get();

        // Transliteration, a wrong keyboard layout and visual model-code
        // substitutions are uncommon, but remain available as a fallback.
        if ($products->isEmpty()) {
            $productsQuery = $productQuery();
            $search->apply($productsQuery, $q, $includeInternal);
            $products = $productsQuery->get();
        }

        $products = $products
            ->map(fn ($p) => [
                'type' => 'product',
                'name' => $p->name,
                'url' => '/'.($p->category->slug ?? 'catalog').'/'.$p->slug,
                'image' => $p->image_url,
                'price' => $p->hasVerifiedPublicPrice()
                    ? number_format($p->price, 2, '.', ' ').' BYN'
                    : 'Цену уточняйте',
            ]);

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

        $brands = Brand::active();
        $search->applyBrand($brands, $q);
        $brands = $brands
            ->whereHas('products', fn ($products) => $products->active()->notArchived())
            ->select('id', 'name', 'slug', 'logo')
            ->orderBy('name')
            ->limit(3)
            ->get()
            ->map(fn ($brand) => [
                'type' => 'brand',
                'name' => $brand->name,
                'url' => '/brands/'.strtolower($brand->slug),
                'image' => $brand->logo ? $brand->image_url : null,
            ]);

        return $categories->concat($brands)->concat($products)->values()->all();
    }
}
