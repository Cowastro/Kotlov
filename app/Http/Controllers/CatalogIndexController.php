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

        return view('pages.catalog-index', compact('rootCategories'));
    }
}