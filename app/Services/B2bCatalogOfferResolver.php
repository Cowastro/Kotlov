<?php

namespace App\Services;

use App\Models\IntegrationProduct;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Collection;

class B2bCatalogOfferResolver
{
    /** @var Collection<int, IntegrationProduct>|null */
    private ?Collection $offers = null;

    public function forProduct(Product $product, ?User $user = null): ?IntegrationProduct
    {
        if (! $user?->isB2B()) {
            return null;
        }

        return $this->offers()
            ->where('product_id', $product->id)
            ->sortBy(fn (IntegrationProduct $offer): float => (float) $offer->price)
            ->first();
    }

    public function priceWithTax(IntegrationProduct $offer): float
    {
        $price = (float) $offer->price;
        $taxMode = data_get($offer->source?->settings, 'price_tax_mode', 'exclusive');

        if ($taxMode === 'inclusive') {
            return round($price, 2);
        }

        $taxRate = max(0, (float) data_get($offer->source?->settings, 'vat_rate', 20));

        return round($price * (1 + $taxRate / 100), 2);
    }

    /** @return array{offer: IntegrationProduct, wholesale_price: float, retail_price: float, difference: float, difference_percent: float|null, vat_rate: float}|null */
    public function comparison(Product $product, ?User $user = null): ?array
    {
        $offer = $this->forProduct($product, $user);

        if (! $offer) {
            return null;
        }

        $wholesalePrice = $this->priceWithTax($offer);
        $retailPrice = round((float) $product->price, 2);
        $difference = max(0, round($retailPrice - $wholesalePrice, 2));

        return [
            'offer' => $offer,
            'wholesale_price' => $wholesalePrice,
            'retail_price' => $retailPrice,
            'difference' => $difference,
            'difference_percent' => $retailPrice > 0 && $difference > 0
                ? round($difference / $retailPrice * 100, 1)
                : null,
            'vat_rate' => max(0, (float) data_get($offer->source?->settings, 'vat_rate', 20)),
        ];
    }

    /** @return Collection<int, IntegrationProduct> */
    private function offers(): Collection
    {
        return $this->offers ??= IntegrationProduct::query()
            ->with('source')
            ->whereNotNull('product_id')
            ->where('match_status', 'matched')
            ->where('price', '>', 0)
            ->where('stock_quantity', '>', 0)
            ->whereHas('source', fn ($query) => $query->where('is_active', true))
            ->get();
    }
}
