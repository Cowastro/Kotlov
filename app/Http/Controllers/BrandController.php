<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use Illuminate\Http\Request;

class BrandController extends Controller
{
    public function index(Request $request)
    {
        // Все буквы для фильтра — только бренды с товарами
        $letters = Brand::whereHas('products', fn($q) => $q->where('is_active', true))
            ->selectRaw('UPPER(LEFT(name, 1)) as letter')
            ->distinct()
            ->orderBy('letter')
            ->pluck('letter');

        $totalCount = Brand::whereHas('products', fn($q) => $q->where('is_active', true))->count();

        $query = Brand::withCount(['products' => fn($q) => $q->where('is_active', true)])
            ->having('products_count', '>', 0)
            ->orderBy('name');

        if ($request->filled('letter')) {
            $query->whereRaw('UPPER(LEFT(name, 1)) = ?', [mb_strtoupper($request->letter)]);
        }

        $brands = $query->paginate(24)->withQueryString();

        return view('pages.brands', compact('brands', 'letters', 'totalCount'));
    }

    public function show(string $slug)
    {
        $brand = Brand::where('slug', $slug)->firstOrFail();

        $products = $brand->products()
            ->where('is_active', true)
            ->with(['category'])
            ->orderByDesc('is_featured')
            ->orderByDesc('rating')
            ->paginate(24);

        return view('pages.brand', compact('brand', 'products'));
    }
}
