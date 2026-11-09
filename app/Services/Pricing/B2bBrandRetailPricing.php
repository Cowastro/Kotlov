<?php

namespace App\Services\Pricing;

use App\Models\IntegrationProduct;
use App\Models\Product;
use App\Models\SupplierProduct;

/**
 * The B2B price that replaces the 1C sale price: the wholesale price of an uploaded price list (Теплов и Сухов,
 * until 1C has the new prices; it includes VAT), else the retail price less a brand or category discount.
 */
class B2bBrandRetailPricing
{
    public function priceFor(Product $product, ?IntegrationProduct $offer = null): ?float
    {
        $wholesale = $this->priceListWholesale($product);
        if ($wholesale !== null) {
            return $wholesale;
        }

        $discount = $this->discountPercent($product, $offer);
        $retailPrice = (float) ($product->price ?? 0);

        if ($discount === null || $retailPrice <= 0) {
            return null;
        }

        return round($retailPrice * (1 - $discount / 100), 2);
    }

    /** The B2B price is the retail price less a fixed brand or category discount (not a price-list wholesale). */
    public function isFixedRetailDiscount(Product $product, ?IntegrationProduct $offer = null): bool
    {
        return $this->priceListWholesale($product) === null && $this->discountPercent($product, $offer) !== null;
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

    /** The wholesale price (with VAT) of the price list of a supplier set in shop.b2b_price_list_wholesale_suppliers. */
    public function priceListWholesale(Product $product): ?float
    {
        $suppliers = (array) config('shop.b2b_price_list_wholesale_suppliers', []);
        if ($suppliers === [] || ! $product->id) {
            return null;
        }

        $price = SupplierProduct::query()
            ->where('product_id', $product->id)
            ->where('match_status', 'matched')
            ->whereHas('supplier', fn ($query) => $query->whereIn('code', $suppliers))
            ->get(['raw'])
            ->map(fn (SupplierProduct $link): float => (float) data_get($link->raw, 'wholesale', 0))
            ->filter(fn (float $value): bool => $value > 0)
            ->min();

        return $price !== null ? round($price, 2) : null;
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
