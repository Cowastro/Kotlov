<?php

namespace App\Services\Pricing;

use App\Models\Product;

class B2bBrandRetailPricing
{
    public function priceFor(Product $product): ?float
    {
        $discount = $this->discountPercent($product);
        $retailPrice = (float) ($product->price ?? 0);

        if ($discount === null || $retailPrice <= 0) {
            return null;
        }

        return round($retailPrice * (1 - $discount / 100), 2);
    }

    public function discountPercent(Product $product): ?float
    {
        $brandSlug = trim((string) ($product->brand?->slug ?? ''));
        $brandName = mb_strtolower(trim((string) ($product->brand?->name ?? '')));
        $brandName = preg_replace('/[^a-zа-яё0-9]+/ui', '', $brandName) ?? '';
        $discounts = (array) config('shop.b2b_brand_retail_discounts', []);
        $discount = (float) ($discounts[$brandSlug] ?? $discounts[$brandName] ?? 0);

        return $discount > 0 && $discount < 100 ? $discount : null;
    }
}
