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
