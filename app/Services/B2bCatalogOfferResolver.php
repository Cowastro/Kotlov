<?php

namespace App\Services;

use App\Models\Category;
use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Models\Product;
use App\Models\User;
use App\Services\Pricing\B2bBrandRetailPricing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class B2bCatalogOfferResolver
{
    /** @var Collection<int, IntegrationProduct>|null */
    private ?Collection $offers = null;

    /** @var Collection<int, IntegrationProduct>|null */
    private ?Collection $stockOffers = null;

    /** @var array<int, int>|null */
    private ?array $categoryParents = null;

    public function forProduct(Product $product, ?User $user = null): ?IntegrationProduct
    {
        if (! $this->canViewPartnerPrices($user)) {
            return null;
        }

        return $this->offers()
            ->filter(fn (IntegrationProduct $offer): bool => $this->userAllowsSource($user, $offer))
            ->where('product_id', $product->id)
            ->sortBy(fn (IntegrationProduct $offer): float => $this->priceWithTax($offer))
            ->first();
    }

    public function canViewPartnerPrices(?User $user): bool
    {
        return (bool) ($user?->isB2B()
            || $user?->isAdmin());
    }

    /** @return Collection<int, array{id:int,name:string,slug:string,count:int}> */
    public function catalogGroups(?User $user): Collection
    {
        if (! $this->canViewPartnerPrices($user)) {
            return collect();
        }

        return $this->offersForUser($user)
            ->filter(fn (IntegrationProduct $offer): bool => filled($offer->product?->category?->slug))
            ->groupBy(fn (IntegrationProduct $offer): int => (int) $offer->product->category_id)
            ->map(fn (Collection $offers): array => [
                'id' => (int) $offers->first()->product->category_id,
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

        return $this->offersForUser($user)
            ->map(fn (IntegrationProduct $offer): string => $offer->source->partnerName())
            ->unique()
            ->values();
    }

    /** @return Collection<int, IntegrationProduct> */
    public function offersForUser(?User $user): Collection
    {
        if (! $this->canViewPartnerPrices($user)) {
            return collect();
        }

        return $this->offers()
            ->filter(fn (IntegrationProduct $offer): bool => $this->userAllowsSource($user, $offer))
            ->values();
    }

    /**
     * Internal audit view of the exact offers that the storefront may publish.
     * Customer authorization is intentionally not checked here; all source,
     * price, category, product and availability safeguards still apply.
     *
     * @return Collection<int, IntegrationProduct>
     */
    public function publishableForSource(IntegrationSource $source): Collection
    {
        return $this->loadPublishableOffers(
            IntegrationProduct::query()->whereBelongsTo($source, 'source')
        );
    }

    public function priceWithTax(IntegrationProduct $offer): float
    {
        $brandRetailPrice = $offer->product
            ? app(B2bBrandRetailPricing::class)->priceFor($offer->product)
            : null;

        if ($brandRetailPrice !== null) {
            return $brandRetailPrice;
        }

        $price = $offer->normalizedPriceByn();

        if ($price === null) {
            throw new \LogicException('Для предложения не рассчитана итоговая цена в BYN.');
        }

        return $price;
    }

    public function hasFreshStock(IntegrationProduct $offer): bool
    {
        return $offer->hasFreshPositiveStock();
    }

    public function isProcurementOffer(IntegrationProduct $offer): bool
    {
        return $offer->source?->isProcurementMode() === true;
    }

    /** @return array{stock: float|null, label: string, procurement_fallback: bool} */
    public function availabilityFor(IntegrationProduct $offer, ?User $user = null): array
    {
        $stockOffer = $this->stockOffers()
            ->filter(fn (IntegrationProduct $candidate): bool => (int) $candidate->product_id === (int) $offer->product_id
                && $this->userAllowsSource($user, $candidate))
            ->sortBy(fn (IntegrationProduct $candidate): array => [
                $this->isProcurementOffer($candidate) ? 1 : 0,
                $candidate->source?->code === 'onec' ? 0 : 1,
                -(float) $candidate->stock_quantity,
            ])
            ->first();

        if ($stockOffer) {
            $warehouse = trim((string) data_get($stockOffer->source?->settings, 'warehouse_label', 'Основной'));

            return [
                'stock' => (float) $stockOffer->stock_quantity,
                'label' => ($warehouse !== '' ? $warehouse : 'Основной').': '.$stockOffer->formattedStockQuantity(),
                'procurement_fallback' => false,
            ];
        }

        $warehouseStock = collect($offer->product?->warehouseStocks)
            ->filter(fn ($stock): bool => (float) $stock->available_quantity > 0)
            ->sortBy(fn ($stock): array => [(int) ($stock->delivery_days ?? 0), -(float) $stock->available_quantity])
            ->first();

        if ($warehouseStock) {
            $warehouse = trim((string) $warehouseStock->warehouse_name);

            return [
                'stock' => (float) $warehouseStock->available_quantity,
                'label' => ($warehouse !== '' ? $warehouse : 'Склад').': '.$warehouseStock->formattedAvailableQuantity(),
                'procurement_fallback' => false,
            ];
        }

        if ($this->isProcurementOffer($offer)) {
            return [
                'stock' => null,
                'label' => (string) data_get($offer->source?->settings, 'warehouse_label', 'Под заказ'),
                'procurement_fallback' => true,
            ];
        }

        return [
            'stock' => null,
            'label' => 'Наличие уточняется',
            'procurement_fallback' => false,
        ];
    }

    public function canOrder(IntegrationProduct $offer, int $quantity = 1): bool
    {
        if ($this->isProcurementOffer($offer)) {
            return $quantity >= 1 && $quantity <= 999;
        }

        if ($this->hasFreshStock($offer) && $quantity <= (float) $offer->stock_quantity) {
            return true;
        }

        $warehouseStocks = $offer->product?->relationLoaded('warehouseStocks')
            ? $offer->product->warehouseStocks
            : $offer->product?->warehouseStocks()->available()->get();

        return collect($warehouseStocks)->contains(
            fn ($stock): bool => (float) $stock->available_quantity >= $quantity,
        );
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
            'vat_rate' => $offer->effectiveVatRate(),
        ];
    }

    /** @return Collection<int, IntegrationProduct> */
    private function offers(): Collection
    {
        return $this->offers ??= $this->loadPublishableOffers(IntegrationProduct::query());
    }

    /** @return Collection<int, IntegrationProduct> */
    private function stockOffers(): Collection
    {
        if ($this->stockOffers !== null) {
            return $this->stockOffers;
        }

        $productIds = $this->offers()->pluck('product_id')->filter()->unique()->values();

        return $this->stockOffers = IntegrationProduct::query()
            ->with('source')
            ->whereIn('product_id', $productIds)
            ->where('match_status', 'matched')
            ->where('stock_quantity', '>', 0)
            ->whereHas('source', fn ($query) => $query->where('is_active', true))
            ->get()
            ->filter(fn (IntegrationProduct $candidate): bool => $candidate->hasFreshPositiveStock())
            ->values();
    }

    /** @return Collection<int, IntegrationProduct> */
    private function loadPublishableOffers(Builder $query): Collection
    {
        return $query
            ->with(['source', 'product.brand', 'product.category', 'product.warehouseStocks'])
            ->whereNotNull('product_id')
            ->where('match_status', 'matched')
            ->withUsablePrice()
            ->whereHas('source', fn ($query) => $query->where('is_active', true))
            ->get()
            ->filter(fn (IntegrationProduct $offer): bool => $offer->source?->isB2bEnabled() === true
                && $offer->source->hasPublishableB2bPrice()
                && $offer->allowsB2bPublication()
                && $offer->normalizedPriceByn() !== null
                && ($this->isProcurementOffer($offer)
                    || $this->hasFreshStock($offer)
                    || $offer->product?->warehouseStocks->contains(
                        fn ($stock): bool => (float) $stock->available_quantity > 0,
                    ))
                && $this->sourceAllowsCategory($offer))
            ->values();
    }

    private function sourceAllowsCategory(IntegrationProduct $offer): bool
    {
        $allowed = $offer->source?->b2bCategoryIds() ?? [];
        $categoryId = (int) ($offer->product?->category_id ?? 0);

        if ($allowed === [] || $categoryId <= 0) {
            return false;
        }

        $allowedLookup = array_fill_keys($allowed, true);
        $parents = $this->categoryParents ??= Category::query()
            ->pluck('parent_id', 'id')
            ->map(fn (mixed $parentId): int => (int) $parentId)
            ->all();

        $visited = [];
        while ($categoryId > 0 && ! isset($visited[$categoryId])) {
            if (isset($allowedLookup[$categoryId])) {
                return true;
            }

            $visited[$categoryId] = true;
            $categoryId = (int) ($parents[$categoryId] ?? 0);
        }

        return false;
    }

    private function userAllowsSource(?User $user, IntegrationProduct $offer): bool
    {
        if (! $user || $user->isAdmin()) {
            return true;
        }

        $client = $user->relationLoaded('b2bClient')
            ? $user->b2bClient
            : $user->b2bClient()->first();

        // Legacy approved accounts without a B2B profile retain their access.
        if (! $client) {
            return true;
        }

        $allowed = $client->allowed_integration_source_ids;

        return is_array($allowed) && in_array((int) $offer->integration_source_id, array_map('intval', $allowed), true);
    }
}
