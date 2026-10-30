<?php

namespace App\Services;

use App\Models\B2bPriceLevel;
use App\Models\IntegrationProduct;
use App\Models\Product;
use App\Models\ProductWarehouseStock;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\User;
use App\Services\Pricing\B2bPartnerPricingEngine;
use Illuminate\Support\Collection;

class B2bSupplyOptionResolver
{
    /** @var array<string, Collection<int, array<string, mixed>>> */
    private array $optionsCache = [];

    public function __construct(
        private readonly B2bCatalogOfferResolver $integrationOffers,
        private readonly B2bPartnerPricingEngine $partnerPricing,
    ) {}

    /** @return Collection<int, array<string, mixed>> */
    public function optionsForUser(?User $user): Collection
    {
        $cacheKey = $user ? 'user:'.$user->id : 'guest';
        if (isset($this->optionsCache[$cacheKey])) {
            return $this->optionsCache[$cacheKey];
        }

        if (! $this->integrationOffers->canViewPartnerPrices($user)) {
            return collect();
        }

        $priceLevel = $this->priceLevelFor($user);
        $integration = $this->integrationOffers->offersForUser($user)
            ->map(fn (IntegrationProduct $offer): ?array => $this->integrationOption($offer, $user))
            ->filter();

        $supplierIds = $this->allowedSupplierIds($user);
        if ($supplierIds === []) {
            return $this->optionsCache[$cacheKey] = $this->sortOptions($integration);
        }

        $external = SupplierProduct::query()
            ->with(['supplier.b2bPricingRules', 'product.brand', 'product.category'])
            ->whereNotNull('product_id')
            ->where('match_status', 'matched')
            ->whereIn('supplier_id', $supplierIds)
            ->whereHas('supplier', fn ($query) => $query
                ->where('is_active', true)
                ->where('b2b_partner_enabled', true))
            ->get()
            ->map(fn (SupplierProduct $offer): ?array => $this->supplierOption($offer, $priceLevel))
            ->filter();

        return $this->optionsCache[$cacheKey] = $this->sortOptions($integration->concat($external));
    }

    /** @return Collection<int, array<string, mixed>> */
    public function optionsForProduct(Product $product, ?User $user): Collection
    {
        return $this->optionsForUser($user)
            ->where('product_id', (int) $product->id)
            ->values();
    }

    /** @return array<string, mixed>|null */
    public function preferredForProduct(Product $product, ?User $user): ?array
    {
        return $this->optionsForProduct($product, $user)->first();
    }

    /** @return array<string, mixed>|null */
    public function optionForKey(Product $product, ?User $user, ?string $key): ?array
    {
        $options = $this->optionsForProduct($product, $user);
        if (filled($key)) {
            return $options->firstWhere('key', $key);
        }

        return $options->first();
    }

    /** @param array<string, mixed> $option */
    public function canOrder(array $option, int $quantity): bool
    {
        if ($quantity < 1 || $quantity > 999) {
            return false;
        }

        return ($option['stock'] ?? null) === null
            ? (bool) ($option['procurement'] ?? false)
            : (float) $option['stock'] >= $quantity;
    }

    /** @return array<string, mixed>|null */
    private function integrationOption(IntegrationProduct $offer, ?User $user): ?array
    {
        $quote = $this->integrationOffers->priceQuote($offer, $user);
        $source = $offer->source;
        $supplierId = (int) ($source?->supplier_id ?? 0);
        $warehouseStock = null;

        if ($supplierId > 0 && ($source?->code === EcokaminB2bOfferPublisher::SOURCE_CODE || ! $offer->hasFreshPositiveStock())) {
            $warehouseStock = collect($offer->product?->warehouseStocks)
                ->first(fn (ProductWarehouseStock $stock): bool => (int) $stock->supplier_id === $supplierId
                    && (float) $stock->available_quantity > 0);
        }

        $procurement = $source?->isProcurementMode() === true;
        if (! $offer->hasFreshPositiveStock() && ! $warehouseStock && ! $procurement) {
            return null;
        }

        $stock = $offer->hasFreshPositiveStock()
            ? (float) $offer->stock_quantity
            : ($warehouseStock ? (float) $warehouseStock->available_quantity : null);
        $warehouse = trim((string) ($warehouseStock?->warehouse_name
            ?: data_get($source?->settings, 'warehouse_label', $procurement ? 'Под заказ' : 'Основной склад')));
        $deliveryDays = $warehouseStock?->delivery_days;
        $isOwnStock = $source?->code === 'onec' || $source?->supplier?->code === 'sanbusinessgroup';

        return $this->publicOption([
            'key' => 'integration:'.$offer->id,
            'kind' => 'integration',
            'product_id' => (int) $offer->product_id,
            'product' => $offer->product,
            'integration_product_id' => (int) $offer->id,
            'supplier_product_id' => null,
            'warehouse_stock_id' => $warehouseStock?->id,
            'source_key' => 'integration:'.$source?->id,
            'source_label' => $source?->partnerName() ?: 'Наш склад',
            'warehouse_name' => $warehouse !== '' ? $warehouse : 'Основной склад',
            'delivery_days' => $deliveryDays,
            'delivery_label' => $this->deliveryLabel($deliveryDays, $deliveryDays),
            'stock' => $stock,
            'stock_label' => $stock !== null
                ? ($warehouse !== '' ? $warehouse : 'Склад').': '.$this->formatStock($stock)
                : ($warehouse !== '' ? $warehouse : 'Под заказ'),
            'procurement' => $procurement,
            'is_own_stock' => $isOwnStock,
            'priority' => $isOwnStock ? 0 : 100,
        ] + $quote);
    }

    /** @return array<string, mixed>|null */
    private function supplierOption(SupplierProduct $offer, ?B2bPriceLevel $priceLevel): ?array
    {
        $quote = $this->partnerPricing->clientQuote($offer, $priceLevel);
        if (! $quote) {
            return null;
        }

        $supplier = $offer->supplier;
        $stock = $supplier?->b2b_require_positive_stock
            ? (float) $offer->stock_quantity
            : null;
        $deliveryMin = max(0, (int) ($offer->delivery_days ?? $supplier?->b2b_delivery_days_min));
        $deliveryMax = max($deliveryMin, (int) ($offer->delivery_days ?? $supplier?->b2b_delivery_days_max));
        $warehouse = trim((string) ($supplier?->b2b_client_warehouse_label ?: 'Партнёрский склад'));

        return $this->publicOption([
            'key' => 'supplier:'.$offer->id,
            'kind' => 'supplier',
            'product_id' => (int) $offer->product_id,
            'product' => $offer->product,
            'integration_product_id' => null,
            'supplier_product_id' => (int) $offer->id,
            'warehouse_stock_id' => null,
            'source_key' => 'supplier:'.$supplier?->id,
            'source_label' => $supplier?->name ?: 'Партнёрский склад',
            'warehouse_name' => $warehouse,
            'delivery_days' => $deliveryMax,
            'delivery_label' => $this->deliveryLabel($deliveryMin, $deliveryMax),
            'stock' => $stock,
            'stock_label' => $stock !== null
                ? $warehouse.': '.$this->formatStock($stock)
                : $warehouse.' · наличие подтверждается при заказе',
            'procurement' => $stock === null,
            'is_own_stock' => false,
            'priority' => 1000 + (int) ($supplier?->b2b_priority ?? 100),
        ] + $quote);
    }

    /** @param array<string, mixed> $option
     * @return array<string, mixed>
     */
    private function publicOption(array $option): array
    {
        return collect($option)->only([
            'key', 'kind', 'product_id', 'product', 'integration_product_id', 'supplier_product_id',
            'warehouse_stock_id', 'source_key', 'source_label', 'warehouse_name', 'delivery_days',
            'delivery_label', 'stock', 'stock_label', 'procurement', 'is_own_stock', 'priority',
            'base_price', 'price', 'price_level_id', 'price_level_code', 'price_level_name',
            'discount_percent',
        ])->all();
    }

    /** @param Collection<int, array<string, mixed>> $options
     * @return Collection<int, array<string, mixed>>
     */
    private function sortOptions(Collection $options): Collection
    {
        return $options->sortBy(fn (array $option): array => [
            (int) $option['priority'],
            ($option['stock'] ?? null) === null ? 1 : 0,
            (int) ($option['delivery_days'] ?? 999),
            (float) $option['price'],
        ])->values();
    }

    /** @return list<int> */
    private function allowedSupplierIds(?User $user): array
    {
        if (! $user) {
            return [];
        }
        if ($user->isAdmin()) {
            return Supplier::query()
                ->where('is_active', true)
                ->where('b2b_partner_enabled', true)
                ->pluck('id')->map(fn ($id): int => (int) $id)->all();
        }

        $client = $user->relationLoaded('b2bClient') ? $user->b2bClient : $user->b2bClient()->first();
        $allowed = $client?->allowed_supplier_ids;

        return is_array($allowed)
            ? collect($allowed)->map(fn ($id): int => (int) $id)->filter()->unique()->values()->all()
            : [];
    }

    private function priceLevelFor(?User $user): ?B2bPriceLevel
    {
        if (! $user || $user->isAdmin()) {
            return null;
        }

        $client = $user->relationLoaded('b2bClient') ? $user->b2bClient : $user->b2bClient()->with('priceLevel')->first();
        if (! $client) {
            return null;
        }
        $client->loadMissing('priceLevel');

        return $client->priceLevel?->is_active ? $client->priceLevel : null;
    }

    private function formatStock(float $stock): string
    {
        $formatted = abs($stock - round($stock)) < 0.0005
            ? number_format($stock, 0, ',', ' ')
            : rtrim(rtrim(number_format($stock, 3, ',', ' '), '0'), ',');

        return $formatted.' шт.';
    }

    private function deliveryLabel(?int $minimum, ?int $maximum): string
    {
        if ($minimum === null && $maximum === null) {
            return 'Срок уточняется';
        }
        $minimum ??= $maximum;
        $maximum ??= $minimum;
        if ($minimum === 0 && $maximum === 0) {
            return 'Можно отгрузить сегодня';
        }

        return $minimum === $maximum
            ? $minimum.' дн.'
            : $minimum.'–'.$maximum.' дн.';
    }
}
