<?php

namespace App\Services\Orders;

use App\Models\OneCItem;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\Integrations\OneCBridge\OneCStock;
use App\Services\Integrations\OneCBridge\OneCItemCreator;

final class OrderItemFulfillmentPicker
{
    public function __construct(private readonly OrderItemSupplyContextResolver $resolver) {}

    /** @return array<int, array{label: string, own: bool, quantity: ?float, price: ?float, source: ?string}> */
    public function offers(OrderItem $item): array
    {
        $context = $this->resolver->resolveCurrent($item);
        $item->loadMissing(['supplySupplier', 'fulfillmentSupplier', 'product.warehouseStocks.supplier',
            'product.integrationProducts.source.latestSuccessfulCatalogExchangeRun']);
        $product = $item->product;
        if ($product === null) {
            return Supplier::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code'])
                ->mapWithKeys(fn (Supplier $supplier): array => [$supplier->id => [
                    'label' => $supplier->name,
                    'own' => $supplier->code === 'sanbusinessgroup',
                    'quantity' => null,
                    'price' => $supplier->code !== 'sanbusinessgroup' && (int) $item->supply_supplier_id === $supplier->id && $item->supply_purchase_price !== null
                        ? (float) $item->supply_purchase_price : null,
                    'source' => null,
                ]])->all();
        }

        $integrations = collect($product?->operationalIntegrationProducts())->groupBy(fn ($offer) => $offer->source->supplier_id);
        $legacyOffers = collect($product?->operationalSupplierProducts())->groupBy('supplier_id');
        $warehouses = collect($product?->warehouseStocks)->groupBy('supplier_id');
        $suppliers = collect($product?->operationalSuppliers())
            ->concat(collect($product?->warehouseStocks)->pluck('supplier'))
            ->concat([$item->supplySupplier, $item->fulfillmentSupplier]);

        $own = $product?->manual_stock_management
            ? Supplier::query()->where('code', 'sanbusinessgroup')->where('is_active', true)->first()
            : null;
        $suppliers = $suppliers->push($own)->filter(fn (?Supplier $supplier): bool => $supplier?->is_active === true)->unique('id');

        return $suppliers->mapWithKeys(function (Supplier $supplier) use ($product, $item, $context, $own, $integrations, $legacyOffers, $warehouses): array {
            $integration = collect($integrations->get($supplier->id))
                ->sortByDesc(fn ($offer): array => [$offer->hasFreshPositiveStock(), (float) $offer->stock_quantity])->first();
            $integration = collect($integrations->get($supplier->id))->first(fn ($offer): bool => $offer->source?->code === 'onec') ?? $integration;
            $onecContext = ($context['stock_source'] ?? null) === 'onec' && (int) ($context['stock_supplier_id'] ?? 0) === $supplier->id;
            $legacy = collect($legacyOffers->get($supplier->id))
                ->sortByDesc(fn ($offer): array => [$offer->hasFreshPositiveStock(), (float) $offer->stock_quantity])->first();
            $warehouse = collect($warehouses->get($supplier->id))->filter(fn ($stock): bool => $stock->hasFreshPositiveStock())
                ->sortByDesc('available_quantity')->first();

            $isOwn = $supplier->code === 'sanbusinessgroup' || $integration?->source?->code === 'onec';
            // Our warehouse in 1C: what is free there (in stock − reserve of other orders), when 1C stock is read.
            $linked = $isOwn ? app(OneCItemCreator::class)->linked($product) : null;
            $onecItem = OneCItem::fromExchangeKey($linked?->external_id);
            $onecFree = OneCStock::fresh($onecItem) ? OneCStock::free($onecItem) : null;
            $manual = $own?->id === $supplier->id && $product?->manual_stock_management;
            $quantity = match (true) {
                $onecContext => $context['stock_quantity'],
                $manual => $product->effectiveAvailabilityStatus() === Product::AVAILABILITY_IN_STOCK ? (float) $product->stock_qty : 0.0,
                $onecFree !== null => $onecFree,
                $integration?->hasFreshPositiveStock() === true => (float) $integration->stock_quantity,
                $warehouse !== null => (float) $warehouse->available_quantity,
                $legacy?->hasFreshPositiveStock() === true => (float) $legacy->stock_quantity,
                default => null,
            };
            // Our stock costs what 1C paid for it (cost from the posted purchases), not its selling price.
            $onecCost = $onecItem?->cost_gross;
            $price = $isOwn ? $onecCost : ($integration?->normalizedPriceByn()
                ?? $legacy?->price_byn
                ?? ((int) $context['supplier_id'] === $supplier->id ? $context['wholesale_price'] : null)
                ?? ((int) $item->supply_supplier_id === $supplier->id ? $item->supply_purchase_price : null));
            $source = match (true) {
                $onecContext => $context['stock_source_label'],
                $manual => 'Ручное наличие карточки',
                $onecFree !== null => '1С, склад '.implode(', ', OneCStock::WAREHOUSES).': '.OneCStock::label($onecItem),
                $integration?->hasFreshPositiveStock() === true => $integration->source->name,
                $warehouse !== null => $warehouse->warehouse_name,
                $legacy?->hasFreshPositiveStock() === true => 'Прайс поставщика',
                default => $integration?->source?->name,
            };

            if ($onecCost !== null) {
                $source = $source ? $source.' · себестоимость 1С' : 'себестоимость 1С';
            }

            return [$supplier->id => [
                'label' => $supplier->name,
                'own' => $isOwn,
                'own_cost_label' => $isOwn ? OneCItem::grossCostLabel($onecItem) : null,
                'quantity' => $quantity,
                'price' => (float) $price > 0 ? (float) $price : null,
                'source' => $source,
            ]];
        })->all();
    }

    public function defaults(OrderItem $item, array $offers): array
    {
        $ownInStock = array_filter($offers, fn (array $offer): bool => $offer['own'] && $offer['quantity'] >= $item->quantity);
        $preferred = $item->fulfillment_supplier_id ?: array_key_first($ownInStock) ?: $item->supply_supplier_id;
        $supplierId = isset($offers[$preferred]) ? $preferred : ($item->product_id ? array_key_first($offers) : null);
        if ($supplierId === null && $item->fulfillment_route === 'own_stock') {
            $supplierId = array_key_first(array_filter($offers, fn (array $offer): bool => $offer['own']));
        }
        $offer = $offers[$supplierId] ?? null;
        $route = $item->fulfillment_route ?? ($offer === null ? null : ($offer['own'] ? 'own_stock' : 'supplier_purchase'));
        if ($offer !== null) {
            $route = $offer['own'] ? 'own_stock' : ($route === 'direct_supplier' ? $route : 'supplier_purchase');
        }

        return [
            'fulfillment_route' => $route,
            'supplier_id' => $supplierId,
            'availability_status' => $item->fulfillment_availability_status
                ?? ($offer !== null && $offer['quantity'] >= $item->quantity ? 'available' : null),
            'purchase_price' => $route === 'own_stock' && ! $item->fulfillment_confirmed_at
                ? ($offer['price'] ?? null) : ($item->fulfillment_purchase_price ?? ($offer['price'] ?? null)),
            'note' => $item->fulfillment_note,
        ];
    }
}
