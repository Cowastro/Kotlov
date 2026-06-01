<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Category;
use App\Models\ProductAttributeValue;

class ProductController extends Controller
{
    public function show(string $category, string $productOrSubcategory, string $product = null)
    {
        $productSlug = $product ?? $productOrSubcategory;

        $product = Product::where('slug', $productSlug)
            ->where('is_active', true)
            ->with([
                'category.parent',
                'brand',
                'reviews' => fn($q) => $q->where('is_approved', true)->latest()->limit(10),
            ])
            ->firstOrFail();

        // Атрибуты товара для вкладки "Характеристики"
        $attributeValues = ProductAttributeValue::where('product_id', $product->id)
            ->with(['attribute', 'option'])
            ->whereHas('attribute', fn($q) => $q->where('in_product', true))
            ->orderBy('attribute_id')
            ->get();

        // Похожие товары
        $relatedProducts = Product::where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->where('is_active', true)
            ->with(['category', 'brand'])
            ->inRandomOrder()
            ->limit(8)
            ->get();

        $product->increment('views_count');

        $reviews = $product->reviews;

        return view('pages.product', compact(
            'product',
            'attributeValues',
            'relatedProducts',
            'reviews'
        ));
    }
}