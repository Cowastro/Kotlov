<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Category;
use App\Models\ProductAttributeValue;
use Illuminate\Support\Facades\Log;

class ProductController extends Controller
{
    public function show(string $category, string $productOrSubcategory, string $product = null)
    {
        $productSlug = $product ?? $productOrSubcategory;

        // Проверяем не является ли последний сегмент URL слагом категории
        $lastSegment = $product ?? $productOrSubcategory;
        $maybeCategory = \App\Models\Category::where('slug', $lastSegment)->first();
        if ($maybeCategory) {
            if ($maybeCategory->is_active) {
                return app(CatalogController::class)->show($maybeCategory->slug);
            }
            $parentSlug = $maybeCategory->parent?->slug ?? null;
            return redirect($parentSlug ? '/' . $parentSlug : '/', 301);
        }

        $product = Product::where('slug', $productSlug)
            ->where(fn($q) => $q->where('is_active', true)->orWhere('is_archived', true))
            ->with([
                'category.parent',
                'brand',
                'reviews' => fn($q) => $q->where('is_approved', true)->latest()->limit(10),
            ])
            ->firstOrFail();

        $productCategory = $product->category;

        if (! $productCategory) {
            Log::warning('Product page requested for product without category', [
                'product_id' => $product->id,
                'product_slug' => $product->slug,
                'requested_category' => $category,
                'requested_product' => $productSlug,
                'url' => request()->fullUrl(),
            ]);

            abort(404);
        }

        $canonicalPath = '/' . $productCategory->slug . '/' . $product->slug;
        $currentPath = '/' . trim(request()->path(), '/');

        if ($currentPath !== $canonicalPath) {
            return redirect($canonicalPath, 301);
        }

        // Атрибуты товара для вкладки "Характеристики"
        $attributeValues = ProductAttributeValue::where('product_id', $product->id)
            ->with(['attribute', 'option'])
            ->whereHas('attribute', fn($q) => $q
                ->where('in_product', true)
                ->whereNotIn('name', Product::supplierTechnicalAttributeNames()))
            ->orderBy('attribute_id')
            ->get();

        // Похожие товары
        $relatedProducts = Product::where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->orderable()
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

        $autoTitle = $nameFull . ' — купить ' . $cityIn . ' | KOTLOV';
        if (mb_strlen($autoTitle) > 70) {
            $autoTitle = mb_substr($product->name, 0, 40) . ' — купить | KOTLOV';
        }
        $title = $replaceCityIn($product->meta_title) ?: $autoTitle;

        $description = $replaceCityIn($product->meta_description)
            ?: ('Купить ' . $nameFull . ' ' . $cityIn
                . ($product->price ? '. Цена ' . number_format($product->price, 0, '.', ' ') . ' руб.' : '')
                . ' Доставка по всей Беларуси, гарантия качества.');

        $keywords = $replaceCityIn($product->meta_keywords)
            ?: ($nameFull . ', купить ' . mb_strtolower($nameFull) . ', цена, ' . $cityIn);

        // Replace %city% placeholders in product body content too
        if ($product->content) {
            $product->content = $replaceCityIn($product->content);
        }

        $canonical = 'https://kotlov.by/' . $productCategory->slug . '/' . $product->slug;

        $firstImage = $product->imageUrl(0);
        $ogImageRaw = $firstImage ?: asset('img/og-default.jpg');
        // og:image и Schema.org требуют абсолютный URL
        $ogImage = str_starts_with($ogImageRaw, '/') ? 'https://kotlov.by' . $ogImageRaw : $ogImageRaw;

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
            $availability = match ($product->effectiveAvailabilityStatus()) {
                Product::AVAILABILITY_IN_STOCK => 'https://schema.org/InStock',
                Product::AVAILABILITY_CHECK => 'https://schema.org/LimitedAvailability',
                default => 'https://schema.org/OutOfStock',
            };

            $schema['offers'] = [
                '@type'         => 'Offer',
                'price'         => (string) $product->price,
                'priceCurrency' => 'BYN',
                'availability'  => $availability,
                'url'           => $canonical,
            ];
        }

        // BreadcrumbList
        $breadcrumbs = [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Главная',      'item' => 'https://kotlov.by/'],
            ['@type' => 'ListItem', 'position' => 2, 'name' => $productCategory->name, 'item' => 'https://kotlov.by/' . $productCategory->slug],
            ['@type' => 'ListItem', 'position' => 3, 'name' => $nameFull,      'item' => $canonical],
        ];
        $breadcrumbSchema = [
            '@context'        => 'https://schema.org',
            '@type'           => 'BreadcrumbList',
            'itemListElement' => $breadcrumbs,
        ];

        $schemaJson = json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $breadcrumbJson = json_encode($breadcrumbSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $robots = $product->is_archived ? 'noindex, follow' : null;

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
            'schemaJson',
            'breadcrumbJson',
            'robots'
        ));
    }
}
