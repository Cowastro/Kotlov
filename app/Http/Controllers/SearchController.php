<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function index(Request $request)
    {
        $q        = trim($request->get('q', ''));
        $category = $request->get('category', '');
        $sort     = $request->get('sort', '');

        $products = collect();
        $total    = 0;

        if (mb_strlen($q) >= 1) {
            // Разбиваем на слова — каждое слово должно встречаться в названии
            $words = array_filter(array_map('trim', explode(' ', $q)), fn($w) => mb_strlen($w) >= 1);

            $query = Product::active()
                ->notArchived()
                ->where(function ($qb) use ($words, $q) {
                    // Сначала пробуем полное совпадение
                    $qb->where('name', 'like', "%{$q}%")
                       ->orWhere('sku', 'like', "%{$q}%")
                       ->orWhere(function ($qb2) use ($words) {
                           // Затем все слова должны быть в названии (AND)
                           foreach ($words as $word) {
                               $qb2->where('name', 'like', "%{$word}%");
                           }
                       })
                       ->orWhereHas('brand', fn($b) => $b->where('name', 'like', "%{$q}%"));
                })
                ->with(['category', 'brand']);

            if ($category) {
                $cat = Category::where('slug', $category)->first();
                if ($cat) {
                    $childIds = Category::where('parent_id', $cat->id)->pluck('id');
                    $allIds   = $childIds->prepend($cat->id);
                    $query->whereIn('category_id', $allIds);
                }
            }

            switch ($sort) {
                case 'price_asc':  $query->orderBy('price'); break;
                case 'price_desc': $query->orderByDesc('price'); break;
                case 'new':        $query->orderByDesc('id'); break;
                default:           $query->orderByDesc('is_featured')->orderByDesc('rating');
            }

            $total    = $query->count();
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
        $q = trim($request->get('q', ''));

        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        // Товары
        $words = array_filter(array_map('trim', explode(' ', $q)), fn($w) => mb_strlen($w) >= 1);

        $products = Product::active()
            ->notArchived()
            ->where(function ($qb) use ($q, $words) {
                $qb->where('name', 'like', "%{$q}%")
                   ->orWhere('sku', 'like', "{$q}%")
                   ->orWhere(function ($qb2) use ($words) {
                       foreach ($words as $word) {
                           $qb2->where('name', 'like', "%{$word}%");
                       }
                   });
            })
            ->select('id', 'name', 'slug', 'price', 'category_id')
            ->with('category:id,slug')
            ->orderByDesc('rating')
            ->limit(6)
            ->get()
            ->map(fn($p) => [
                'type'  => 'product',
                'name'  => $p->name,
                'url'   => '/' . ($p->category->slug ?? 'catalog') . '/' . $p->slug,
                'price' => $p->price > 0
                    ? number_format($p->price, 2, '.', ' ') . ' BYN'
                    : 'Цена по запросу',
            ]);

        // Категории
        $categories = Category::where('is_active', true)
            ->where('name', 'like', "%{$q}%")
            ->whereHas('products', fn($products) => $products->active()->notArchived())
            ->select('id', 'name', 'slug')
            ->limit(3)
            ->get()
            ->map(fn($c) => [
                'type' => 'category',
                'name' => $c->name,
                'url'  => '/' . $c->slug,
            ]);

        return response()->json($categories->concat($products)->values());
    }
}
