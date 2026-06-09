<?php

namespace App\Http\Controllers;

use App\Models\Category;

class CatalogIndexController extends Controller
{
    public function index()
    {
        $rootCategories = Category::query()
            ->where('parent_id', 0)
            ->where('is_active', true)
            ->withCount(['products' => fn($q) => $q->where('is_active', true)])
            ->orderBy('sort_order')
            ->get();

        $title       = 'Каталог товаров — котлы, печи, камины, дымоходы и отопление в Беларуси';
        $description = 'Более 7 000 товаров для отопления: котлы, печи, камины, дымоходы, тепловые насосы, водонагреватели. Доставка по Беларуси. Маркетплейс KOTLOV.';
        $keywords    = 'каталог котлов, купить печь, камины беларусь, дымоходы, отопление, тепловые насосы, водонагреватели';
        $ogImage     = asset('img/popular/boiler_img.jpg');

        return view('pages.catalog-index', compact('rootCategories', 'title', 'description', 'keywords', 'ogImage'));
    }
}