<?php

namespace App\Services\B2bOrders;

use App\Models\B2bOrderItem;
use App\Models\B2bOrderReservation;
use App\Models\IntegrationProduct;
use App\Models\Product;
use App\Models\ProductWarehouseStock;
use App\Models\SupplierProduct;
use Illuminate\Support\Collection;

/**
 * Every stock pool a product can be served from, built only from the data the
 * existing syncs already keep: integration offers (1С and partner feeds),
 * imported warehouse balances and supplier price-list stock. Active B2B
 * reservations are subtracted, so two orders never promise the same unit.
 *
 * Pool keys: integration:{integration_products.id} | warehouse:{product_warehouse_stocks.id}
 * | supplier:{supplier_products.id}. A null stock means the source does not
 * report a balance (procurement mode, stale or unknown) and needs a manager.
 */
class B2bStockLocator
{
    /** @return Collection<int, array<string, mixed>> */
    public function poolsForProduct(Product $product, ?int $exceptOrderId = null): Collection
    {
        $product->loadMissing([
            'integrationProducts.source.supplier',
            'warehouseStocks.supplier',
            'supplierProducts.supplier',
        ]);

        $integration = collect($product->integrationProducts)
            ->filter(fn (IntegrationProduct $offer): bool => $offer->match_status === 'matched'
                && $offer->source?->is_active === true)
            ->map(fn (IntegrationProduct $offer): array => $this->integrationPool($offer));

        $warehouses = collect($product->warehouseStocks)
            ->map(fn (ProductWarehouseStock $stock): array => $this->warehousePool($stock, $product));

        $suppliers = collect($product->supplierProducts)
            ->filter(fn (SupplierProduct $offer): bool => $offer->match_status === 'matched'
                && $offer->supplier?->is_active === true)
            ->map(fn (SupplierProduct $offer): array => $this->supplierPool($offer));

        $pools = $integration->concat($warehouses)->concat($suppliers)->values();
        $reserved = $this->reservedByKey($pools->pluck('key')->all(), $exceptOrderId);

        return $pools
            ->map(function (array $pool) use ($reserved): array {
                $pool['reserved'] = $reserved[$pool['key']] ?? 0.0;
                $pool['free'] = $pool['stock'] === null ? null : max(0.0, round($pool['stock'] - $pool['reserved'], 3));
                $pool['label'] = $this->label($pool);

                return $pool;
            })
            ->sortBy(fn (array $pool): array => [
                $pool['is_own_stock'] ? 0 : 1,
                $pool['always_available'] || (float) $pool['free'] > 0 ? 0 : 1,
                $pool['stock'] === null && ! $pool['always_available'] ? 1 : 0,
                (int) ($pool['delivery_days'] ?? 999),
                (float) ($pool['purchase_price'] ?? PHP_INT_MAX),
            ])
            ->values();
    }

    /**
     * The pool a placed line points at. A fresh integration balance wins over
     * the imported warehouse file, exactly as the B2B catalog shows it.
     */
    public function keyForItem(B2bOrderItem $item): ?string
    {
        if ($item->stock_key) {
            return $item->stock_key;
        }

        $item->loadMissing('integrationProduct.source');
        if ($item->integrationProduct?->hasFreshPositiveStock()) {
            return 'integration:'.$item->integration_product_id;
        }
        if ($item->warehouse_stock_id) {
            return 'warehouse:'.$item->warehouse_stock_id;
        }
        if ($item->integration_product_id) {
            return 'integration:'.$item->integration_product_id;
        }

        return $item->supplier_product_id ? 'supplier:'.$item->supplier_product_id : null;
    }

    /**
     * @param list<string> $keys
     * @return array<string, float>
     */
    public function reservedByKey(array $keys, ?int $exceptOrderId = null): array
    {
        if ($keys === []) {
            return [];
        }

        return B2bOrderReservation::query()
            ->active()
            ->whereIn('stock_key', array_values(array_unique($keys)))
            ->when($exceptOrderId, fn ($query) => $query->where('b2b_order_id', '!=', $exceptOrderId))
            ->selectRaw('stock_key, SUM(quantity) as reserved')
            ->groupBy('stock_key')
            ->pluck('reserved', 'stock_key')
            ->map(fn ($quantity): float => (float) $quantity)
            ->all();
    }

    /** @return array<string, mixed> */
    private function integrationPool(IntegrationProduct $offer): array
    {
        $source = $offer->source;
        $procurement = $source?->isProcurementMode() === true;
        $warehouse = trim((string) data_get($source?->settings, 'warehouse_label', ''));

        return [
            'key' => 'integration:'.$offer->id,
            'kind' => 'integration',
            'integration_product_id' => (int) $offer->id,
            'warehouse_stock_id' => null,
            'supplier_product_id' => null,
            'supplier_id' => $source?->supplier_id,
            'supplier_name' => $source?->supplier?->name ?? $source?->partnerName(),
            'warehouse_name' => $warehouse !== '' ? $warehouse : ($procurement ? 'Под заказ' : 'Основной склад'),
            'stock' => $offer->hasFreshPositiveStock() ? (float) $offer->stock_quantity : ($procurement ? null : 0.0),
            'always_available' => false,
            'purchase_price' => $offer->normalizedPriceByn(),
            'delivery_days' => null,
            'is_own_stock' => $source?->code === 'onec' || $source?->supplier?->code === 'sanbusinessgroup',
        ];
    }

    /** @return array<string, mixed> */
    private function warehousePool(ProductWarehouseStock $stock, Product $product): array
    {
        $purchase = collect($product->integrationProducts)
            ->first(fn (IntegrationProduct $offer): bool => $stock->supplier_id
                && (int) $offer->source?->supplier_id === (int) $stock->supplier_id)
            ?->normalizedPriceByn()
            ?? collect($product->supplierProducts)
                ->first(fn (SupplierProduct $offer): bool => (int) $offer->supplier_id === (int) $stock->supplier_id
                    && (float) $offer->price_byn > 0)
                ?->price_byn;

        return [
            'key' => 'warehouse:'.$stock->id,
            'kind' => 'warehouse',
            'integration_product_id' => null,
            'warehouse_stock_id' => (int) $stock->id,
            'supplier_product_id' => null,
            'supplier_id' => $stock->supplier_id,
            'supplier_name' => $stock->supplier?->name,
            'warehouse_name' => $stock->warehouse_name,
            'stock' => (float) $stock->available_quantity,
            'always_available' => false,
            'purchase_price' => $purchase !== null ? (float) $purchase : null,
            'delivery_days' => $stock->delivery_days,
            'is_own_stock' => false,
        ];
    }

    /** @return array<string, mixed> */
    private function supplierPool(SupplierProduct $offer): array
    {
        $supplier = $offer->supplier;
        $always = $offer->in_stock && $offer->stock_status === SupplierProduct::STOCK_STATUS_ALWAYS_AVAILABLE;
        $stock = match (true) {
            $always => null,
            $offer->hasFreshPositiveStock() => (float) $offer->stock_quantity,
            $offer->stock_status === 'out_of_stock' => 0.0,
            default => null,
        };

        return [
            'key' => 'supplier:'.$offer->id,
            'kind' => 'supplier',
            'integration_product_id' => null,
            'warehouse_stock_id' => null,
            'supplier_product_id' => (int) $offer->id,
            'supplier_id' => $offer->supplier_id,
            'supplier_name' => $supplier?->name,
            'warehouse_name' => $offer->warehouse_name ?: ($supplier?->b2b_client_warehouse_label ?: 'Склад поставщика'),
            'stock' => $stock,
            'always_available' => $always,
            'purchase_price' => (float) $offer->price_byn > 0 ? (float) $offer->price_byn : null,
            'delivery_days' => $offer->delivery_days ?? $supplier?->b2b_delivery_days_max,
            'is_own_stock' => false,
        ];
    }

    /** @param array<string, mixed> $pool */
    private function label(array $pool): string
    {
        $availability = match (true) {
            $pool['always_available'] => 'всегда в наличии',
            $pool['free'] === null => 'остаток не передаётся — подтвердить',
            default => 'свободно '.$this->quantity((float) $pool['free'])
                .((float) $pool['reserved'] > 0 ? ' (в резерве '.$this->quantity((float) $pool['reserved']).')' : ''),
        };

        return collect([
            $pool['is_own_stock'] ? 'Наш склад' : $pool['supplier_name'],
            $pool['warehouse_name'],
            $availability,
            $pool['purchase_price'] !== null ? 'закупка '.number_format((float) $pool['purchase_price'], 2, ',', ' ') : null,
            $pool['delivery_days'] !== null ? $pool['delivery_days'].' дн.' : null,
        ])->filter()->implode(' · ');
    }

    private function quantity(float $quantity): string
    {
        return (abs($quantity - round($quantity)) < 0.0005
            ? number_format($quantity, 0, ',', ' ')
            : rtrim(rtrim(number_format($quantity, 3, ',', ' '), '0'), ',')).' шт.';
    }
}
