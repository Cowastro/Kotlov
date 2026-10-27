<?php

namespace App\Services\Pricing;

use App\Models\IntegrationProduct;
use App\Models\Product;

class B2bBrandRetailPricing
{
    public function priceFor(Product $product, ?IntegrationProduct $offer = null): ?float
    {
        $discount = $this->discountPercent($product, $offer);
        $retailPrice = (float) ($product->price ?? 0);

        if ($discount === null || $retailPrice <= 0) {
            return null;
        }

        return round($retailPrice * (1 - $discount / 100), 2);
    }

    public function discountPercent(Product $product, ?IntegrationProduct $offer = null): ?float
    {
        $supplierCategoryDiscount = $this->supplierCategoryDiscount($product, $offer);
        if ($supplierCategoryDiscount !== null) {
            return $supplierCategoryDiscount;
        }

        $brandSlug = trim((string) ($product->brand?->slug ?? ''));
        $brandName = mb_strtolower(trim((string) ($product->brand?->name ?? '')));
        $brandName = preg_replace('/[^a-zа-яё0-9]+/ui', '', $brandName) ?? '';
        $discounts = (array) config('shop.b2b_brand_retail_discounts', []);
        $discount = (float) ($discounts[$brandSlug] ?? $discounts[$brandName] ?? 0);

        return $discount > 0 && $discount < 100 ? $discount : null;
    }

    private function supplierCategoryDiscount(Product $product, ?IntegrationProduct $offer): ?float
    {
        if (! $offer) {
            return null;
        }

        $supplierCode = trim((string) ($offer->source?->supplier?->code ?? ''));
        if ($supplierCode === '' && $offer->source?->code === 'onec') {
            $supplierCode = 'sanbusinessgroup';
        }

        $categorySlug = trim((string) ($product->category?->slug ?? ''));
        $discount = (float) data_get(
            config('shop.b2b_supplier_category_retail_discounts', []),
            $supplierCode.'.'.$categorySlug,
            0,
        );

        return $discount > 0 && $discount < 100 ? $discount : null;
    }
}
