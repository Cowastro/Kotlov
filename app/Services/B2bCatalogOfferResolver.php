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
        if (! $this->canViewPartnerPrices($user)) {
            return null;
        }

        return $this->offers()
            ->where('product_id', $product->id)
            ->sortBy(fn (IntegrationProduct $offer): float => (float) $offer->price)
            ->first();
    }

    public function canViewPartnerPrices(?User $user): bool
    {
        return (bool) ($user?->isB2B()
            || ($user?->isAdmin() && request()->boolean('b2b-preview')));
    }

    /** @return Collection<int, array{name:string,slug:string,count:int}> */
    public function catalogGroups(?User $user): Collection
    {
        if (! $this->canViewPartnerPrices($user)) {
            return collect();
        }

        return $this->offers()
            ->filter(fn (IntegrationProduct $offer): bool => filled($offer->product?->category?->slug))
            ->groupBy(fn (IntegrationProduct $offer): int => (int) $offer->product->category_id)
            ->map(fn (Collection $offers): array => [
                'name' => (string) $offers->first()->product->category->name,
                'slug' => (string) $offers->first()->product->category->slug,
                'count' => $offers->pluck('product_id')->unique()->count(),
            ])
            ->sortBy('name')
            ->values();
    }

    /** @return Collection<int, string> */
    public function partnerNames(?User $user): Collection
    {
        if (! $this->canViewPartnerPrices($user)) {
            return collect();
        }

        return $this->offers()
            ->map(fn (IntegrationProduct $offer): string => $offer->source->partnerName())
            ->unique()
            ->values();
    }

    public function priceWithTax(IntegrationProduct $offer): float
    {
        $price = (float) $offer->price;

        return $offer->source?->priceIncludingTax($price) ?? round($price, 2);
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
            'vat_rate' => $offer->source?->vatRate() ?? 0,
        ];
    }

    /** @return Collection<int, IntegrationProduct> */
    private function offers(): Collection
    {
        return $this->offers ??= IntegrationProduct::query()
            ->with(['source', 'product.category'])
            ->whereNotNull('product_id')
            ->where('match_status', 'matched')
            ->where('price', '>', 0)
            ->where('stock_quantity', '>', 0)
            ->whereHas('source', fn ($query) => $query->where('is_active', true))
            ->get()
            ->filter(fn (IntegrationProduct $offer): bool => $offer->source?->isB2bEnabled() === true)
            ->values();
    }
}
