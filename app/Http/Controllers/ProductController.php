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

        // SEO
        $sharedCityIn = view()->shared('cityIn');
        $cityIn       = $sharedCityIn ?: 'в Беларуси';

        $cityName = preg_replace('/^в\s+/u', '', $cityIn);
        $replaceCityIn = function (?string $text) use ($cityIn, $cityName): ?string {
            if (!$text) return null;
            $text = str_replace('в %city%', $cityIn, $text);
            $text = str_replace('%city%', $cityName, $text);
            return $text;
        };

        $brandName = $product->brand?->name ?? '';
        $nameFull  = trim($brandName . ' ' . $product->name);

        $title = $replaceCityIn($product->meta_title)
            ?: ($nameFull . ' — купить ' . $cityIn . ' | KOTLOV');

        $description = $replaceCityIn($product->meta_description)
            ?: ('Купить ' . $nameFull . ' ' . $cityIn
                . ($product->price ? '. Цена ' . number_format($product->price, 0, '.', ' ') . ' руб.' : '')
                . ' Доставка по всей Беларуси, гарантия качества.');

        $keywords = $replaceCityIn($product->meta_keywords)
            ?: ($nameFull . ', купить ' . mb_strtolower($nameFull) . ', цена, ' . $cityIn);

        $canonical = 'https://kotlov.by/' . $product->category->slug . '/' . $product->slug;

        $firstImage = $product->imageUrl(0);
        $ogImage = $firstImage ?: asset('img/og-default.jpg');

        // Schema.org Product
        $schema = [
            '@context' => 'https://schema.org',
            '@type'    => 'Product',
            'name'     => $nameFull,
            'sku'      => $product->sku ?? $product->id,
            'url'      => $canonical,
        ];
        if ($product->description) {
            $schema['description'] = strip_tags($product->description);
        }
        if ($firstImage) {
            $schema['image'] = $ogImage;
        }
        if ($brandName) {
            $schema['brand'] = ['@type' => 'Brand', 'name' => $brandName];
        }
        if ($product->price) {
            $schema['offers'] = [
                '@type'         => 'Offer',
                'price'         => (string) $product->price,
                'priceCurrency' => 'BYN',
                'availability'  => $product->in_stock
                    ? 'https://schema.org/InStock'
                    : 'https://schema.org/OutOfStock',
                'url'           => $canonical,
            ];
        }
        $schemaJson = json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return view('pages.product', compact(
            'product',
            'attributeValues',
            'relatedProducts',
            'reviews',
            'title',
            'description',
            'keywords',
            'canonical',
            'ogImage',
            'schemaJson'
        ));
    }
}