<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BrandController extends Controller
{
    public function index(Request $request)
    {
        // Все буквы для фильтра — только бренды с товарами
        $letters = Brand::whereHas('products', fn($q) => $q->orderable())
            ->selectRaw('UPPER(LEFT(name, 1)) as letter')
            ->distinct()
            ->orderBy('letter')
            ->pluck('letter');

        $totalCount = Brand::whereHas('products', fn($q) => $q->orderable())->count();

        $query = Brand::withCount(['products' => fn($q) => $q->orderable()])
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
        // Редирект uppercase → lowercase
        if ($slug !== strtolower($slug)) {
            return redirect('/brands/' . strtolower($slug), 301);
        }

        $brand = Brand::whereRaw('LOWER(slug) = ?', [strtolower($slug)])->firstOrFail();

        $products = $brand->products()
            ->orderable()
            ->with(['category'])
            ->orderByDesc('is_featured')
            ->orderByDesc('rating')
            ->paginate(24);

        $brandCategories = DB::table('products as p')
            ->join('categories as c', 'c.id', '=', 'p.category_id')
            ->where('p.brand_id', $brand->id)
            ->where('p.is_active', true)
            ->where('p.is_archived', false)
            ->where('p.price', '>', 0)
            ->where(function ($query) {
                $query->where('p.availability_status', 'check')
                    ->orWhere(function ($query) {
                        $query->where('p.availability_status', 'in_stock')
                            ->where('p.in_stock', true);
                    });
            })
            ->where('c.is_active', true)
            ->select(
                'c.name',
                'c.slug',
                DB::raw('COUNT(*) as products_count')
            )
            ->groupBy('c.id', 'c.name', 'c.slug')
            ->orderByDesc('products_count')
            ->orderBy('c.name')
            ->limit(8)
            ->get();

        $brandName = trim((string) ($brand->name ?: $brand->slug));
        $h1        = $brand->h1 ?: "Каталог {$brandName}";
        $title     = $brand->meta_title    ?: "{$brandName} — купить в Беларуси | KOTLOV";
        $description = $brand->meta_description ?: "Каталог товаров бренда {$brandName}. Купить {$brandName} в Беларуси с доставкой. Гарантия, монтаж.";
        $canonicalBase = 'https://' . request()->getHost();
        $canonical = $canonicalBase . '/brands/' . strtolower($brand->slug);

        $schemaJson = json_encode([
            '@context'        => 'https://schema.org',
            '@type'           => 'BreadcrumbList',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'Главная', 'item' => $canonicalBase . '/'],
                ['@type' => 'ListItem', 'position' => 2, 'name' => 'Бренды',  'item' => $canonicalBase . '/brands'],
                ['@type' => 'ListItem', 'position' => 3, 'name' => $brandName, 'item' => $canonical],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return view('pages.brand', compact('brand', 'products', 'brandCategories', 'h1', 'title', 'description', 'canonical', 'schemaJson'));
    }
}
