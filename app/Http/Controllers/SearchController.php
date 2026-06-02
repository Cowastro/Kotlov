<?php

namespace App\Http\Controllers;

use App\Models\Brand;
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

        if (mb_strlen($q) >= 2) {
            $query = Product::where('is_active', true)
                ->where(function ($qb) use ($q) {
                    $qb->where('name', 'like', "%{$q}%")
                       ->orWhere('sku', 'like', "%{$q}%")
                       ->orWhere('short_description', 'like', "%{$q}%")
                       ->orWhereHas('brand', fn($b) => $b->where('name', 'like', "%{$q}%"));
                })
                ->with(['category', 'brand']);

            // Фильтр по категории
            if ($category) {
                $cat = Category::where('slug', $category)->first();
                if ($cat) {
                    $childIds = Category::where('parent_id', $cat->id)->pluck('id');
                    $allIds   = $childIds->prepend($cat->id);
                    $query->whereIn('category_id', $allIds);
                }
            }

            // Сортировка
            switch ($sort) {
                case 'price_asc':  $query->orderBy('price'); break;
                case 'price_desc': $query->orderByDesc('price'); break;
                case 'new':        $query->orderByDesc('id'); break;
                default:           $query->orderByDesc('is_featured')->orderByDesc('rating');
            }

            $total    = $query->count();
            $products = $query->paginate(24)->withQueryString();
        }

        // Быстрые ссылки — популярные категории
        $quickLinks = Category::whereIn('slug', [
            'kotly', 'teplovye-nasosy', 'pechki', 'kaminy', 'dymohody', 'vodonagrevateli',
        ])->where('is_active', true)->get();

        return view('pages.search', compact('products', 'q', 'total', 'quickLinks', 'sort', 'category'));
    }
}
