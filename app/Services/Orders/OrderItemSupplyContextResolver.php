<?php

namespace App\Services\Orders;

use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\SupplierProduct;
use Illuminate\Support\Collection;

class OrderItemSupplyContextResolver
{
    /**
     * Resolve a current operational recommendation without changing the order.
     * Historical orders do not contain a supplier snapshot yet, so every value
     * returned here is explicitly a current recommendation.
     *
     * @return array<string, mixed>
     */
    public function resolve(OrderItem $item): array
    {
        if ($item->supply_captured_at) {
            return $this->fromSnapshot($item);
        }

        $item->loadMissing([
            'integrationProduct.source.supplier',
            'product.integrationProducts.source.supplier',
            'product.supplierProducts.supplier',
        ]);

        if ($item->integrationProduct) {
            return $this->fromIntegrationProduct($item, $item->integrationProduct, true);
        }

        $integrationOffers = $this->integrationOffers($item);
        $legacyOffers = $this->legacyOffers($item);
        $candidateCount = $integrationOffers->count() + $legacyOffers->count();

        $recommendedIntegration = $integrationOffers->first();
        if ($recommendedIntegration) {
            return $this->fromIntegrationProduct($item, $recommendedIntegration, false, $candidateCount);
        }

        $recommendedLegacy = $legacyOffers->first();
        if ($recommendedLegacy) {
            return $this->fromSupplierProduct($item, $recommendedLegacy, $candidateCount);
        }

        return [
            'status' => 'unresolved',
            'route_label' => 'Поставщик не определён',
            'supplier_id' => null,
            'supplier_name' => null,
            'supplier_contact' => null,
            'source_label' => null,
            'integration_source_id' => null,
            'channel' => 'unresolved',
            'wholesale_price' => null,
            'wholesale_price_label' => 'Нет закупочной цены',
            'price_tax_mode' => null,
            'vat_rate' => null,
            'stock_quantity' => null,
            'margin_unit' => null,
            'margin_total' => null,
            'margin_percent' => null,
            'stock_label' => 'Наличие неизвестно',
            'is_available' => null,
            'has_purchase_price' => false,
            'quantity' => (int) $item->quantity,
            'sale_total' => round((float) $item->price * (int) $item->quantity, 2),
            'candidate_count' => 0,
            'is_explicit' => false,
            'is_current_recommendation' => true,
            'is_snapshot' => false,
        ];
    }

    /**
     * @return array{
     *     supplier_names: Collection<int, string>, unresolved_count: int,
     *     missing_price_count: int, unavailable_count: int,
     *     negative_margin_count: int, low_margin_count: int,
     *     minimum_margin_percent: float, sale_total: float, purchase_total: float,
     *     priced_sale_total: float, margin_total: float, margin_percent: float|null,
     *     items_count: int, priced_items_count: int
     * }
     */
    public function summarize(Order $order): array
    {
        $order->loadMissing([
            'items.integrationProduct.source.supplier',
            'items.product.integrationProducts.source.supplier',
            'items.product.supplierProducts.supplier',
        ]);

        $contexts = $order->items->map(fn (OrderItem $item): array => $this->resolve($item));

        $pricedContexts = $contexts->where('has_purchase_price', true);
        $saleTotal = round((float) $order->items->sum(
            fn (OrderItem $item): float => (float) ($item->total ?? ((float) $item->price * (int) $item->quantity)),
        ), 2);
        $purchaseTotal = round((float) $pricedContexts->sum(
            fn (array $context): float => (float) $context['wholesale_price'] * (int) $context['quantity'],
        ), 2);
        $pricedSaleTotal = round((float) $pricedContexts->sum('sale_total'), 2);
        $marginTotal = round((float) $pricedContexts->sum('margin_total'), 2);
        $minimumMarginPercent = (float) config('shop.order_management.minimum_margin_percent', 10);

        return [
            'supplier_names' => $contexts->pluck('supplier_name')->filter()->unique()->values(),
            'unresolved_count' => $contexts->where('status', 'unresolved')->count(),
            'missing_price_count' => $contexts->where('has_purchase_price', false)->count(),
            'unavailable_count' => $contexts->where('is_available', false)->count(),
            'negative_margin_count' => $pricedContexts->filter(
                fn (array $context): bool => (float) $context['margin_total'] < 0,
            )->count(),
            'low_margin_count' => $pricedContexts->filter(
                fn (array $context): bool => (float) $context['margin_total'] >= 0
                    && $context['margin_percent'] !== null
                    && (float) $context['margin_percent'] < $minimumMarginPercent,
            )->count(),
            'minimum_margin_percent' => $minimumMarginPercent,
            'sale_total' => $saleTotal,
            'purchase_total' => $purchaseTotal,
            'priced_sale_total' => $pricedSaleTotal,
            'margin_total' => $marginTotal,
            'margin_percent' => $pricedSaleTotal > 0
                ? round($marginTotal / $pricedSaleTotal * 100, 1)
                : null,
            'items_count' => $contexts->count(),
            'priced_items_count' => $pricedContexts->count(),
        ];
    }

    /** @return Collection<int, IntegrationProduct> */
    private function integrationOffers(OrderItem $item): Collection
    {
        return collect($item->product?->integrationProducts)
            ->filter(fn (IntegrationProduct $offer): bool => $offer->match_status === 'matched'
                && (float) $offer->price > 0
                && $offer->source?->is_active === true)
            ->sortBy([
                fn (IntegrationProduct $offer): int => (float) $offer->stock_quantity > 0 ? 0 : 1,
                fn (IntegrationProduct $offer): int => $offer->source?->code === 'onec' ? 0 : 1,
                fn (IntegrationProduct $offer): float => $this->integrationWholesalePrice($offer),
            ])
            ->values();
    }

    /** @return Collection<int, SupplierProduct> */
    private function legacyOffers(OrderItem $item): Collection
    {
        return collect($item->product?->supplierProducts)
            ->filter(fn (SupplierProduct $offer): bool => (float) $offer->price_byn > 0
                && $offer->supplier?->is_active === true)
            ->sortBy([
                fn (SupplierProduct $offer): int => $this->legacyOfferAvailable($offer) ? 0 : 1,
                fn (SupplierProduct $offer): float => (float) $offer->price_byn,
            ])
            ->values();
    }

    /** @return array<string, mixed> */
    private function fromIntegrationProduct(
        OrderItem $item,
        IntegrationProduct $offer,
        bool $explicit,
        int $candidateCount = 1,
    ): array {
        $source = $offer->source;
        $supplier = $source?->supplier;
        $wholesalePrice = $this->integrationWholesalePrice($offer);
        $isOwnStock = $source?->code === 'onec' || $supplier?->code === 'sanbusinessgroup';

        return $this->pricedContext($item, $wholesalePrice, [
            'status' => $isOwnStock ? 'own_stock' : 'supplier_purchase',
            'route_label' => $isOwnStock ? 'Наш склад / 1С' : 'Закупка у поставщика',
            'supplier_id' => $supplier?->id,
            'supplier_name' => $supplier?->name ?? $source?->partnerName(),
            'supplier_contact' => $supplier?->contact,
            'source_label' => $source?->name,
            'integration_source_id' => $source?->id,
            'channel' => 'integration',
            'wholesale_price_label' => 'Оптовая цена с НДС',
            'price_tax_mode' => $source?->priceTaxMode(),
            'vat_rate' => $source?->vatRate(),
            'stock_quantity' => (float) $offer->stock_quantity,
            'stock_label' => (float) $offer->stock_quantity > 0
                ? $offer->formattedStockQuantity()
                : 'Сейчас нет в наличии',
            'is_available' => (float) $offer->stock_quantity > 0,
            'candidate_count' => $candidateCount,
            'is_explicit' => $explicit,
            'is_current_recommendation' => ! $explicit,
            'is_snapshot' => false,
        ]);
    }

    /** @return array<string, mixed> */
    private function fromSupplierProduct(OrderItem $item, SupplierProduct $offer, int $candidateCount): array
    {
        return $this->pricedContext($item, (float) $offer->price_byn, [
            'status' => 'supplier_purchase',
            'route_label' => 'Закупка у поставщика',
            'supplier_id' => $offer->supplier?->id,
            'supplier_name' => $offer->supplier?->name,
            'supplier_contact' => $offer->supplier?->contact,
            'source_label' => 'Старый канал поставщика',
            'integration_source_id' => null,
            'channel' => 'legacy',
            'wholesale_price_label' => 'Закупочная цена · НДС не указан',
            'price_tax_mode' => 'unknown',
            'vat_rate' => null,
            'stock_quantity' => $offer->stock_quantity !== null ? (float) $offer->stock_quantity : null,
            'stock_label' => $this->legacyOfferAvailable($offer)
                ? ($offer->stock_quantity !== null ? number_format((int) $offer->stock_quantity, 0, '.', ' ').' шт.' : 'Есть в наличии')
                : 'Сейчас нет в наличии',
            'is_available' => $this->legacyOfferAvailable($offer),
            'candidate_count' => $candidateCount,
            'is_explicit' => false,
            'is_current_recommendation' => true,
            'is_snapshot' => false,
        ]);
    }

    /** @return array<string, mixed> */
    private function fromSnapshot(OrderItem $item): array
    {
        $taxLabel = match ($item->supply_price_tax_mode) {
            IntegrationSource::PRICE_TAX_EXCLUSIVE => 'Источник без НДС → +'.number_format((float) $item->supply_vat_rate, 0).'%',
            IntegrationSource::PRICE_TAX_INCLUSIVE => 'Источник передал цену с НДС',
            'unknown' => 'НДС источника не указан',
            default => 'Правило НДС не зафиксировано',
        };

        return $this->pricedContext($item, (float) $item->supply_purchase_price, [
            'status' => $item->supply_status ?? 'unresolved',
            'route_label' => $item->supply_route_label ?? 'Поставщик не определён',
            'supplier_id' => $item->supply_supplier_id,
            'supplier_name' => $item->supply_supplier_name,
            'supplier_contact' => $item->supply_supplier_contact,
            'source_label' => $item->supply_source_label,
            'integration_source_id' => $item->supply_integration_source_id,
            'channel' => $item->supply_channel ?? 'unresolved',
            'wholesale_price_label' => 'Зафиксировано при заказе · '.$taxLabel,
            'price_tax_mode' => $item->supply_price_tax_mode,
            'vat_rate' => $item->supply_vat_rate !== null ? (float) $item->supply_vat_rate : null,
            'stock_quantity' => $item->supply_stock_quantity !== null ? (float) $item->supply_stock_quantity : null,
            'stock_label' => $this->snapshotStockLabel($item),
            'is_available' => $item->supply_is_available,
            'candidate_count' => (int) $item->supply_candidate_count,
            'is_explicit' => true,
            'is_current_recommendation' => false,
            'is_snapshot' => true,
        ]);
    }

    private function snapshotStockLabel(OrderItem $item): string
    {
        if ($item->supply_is_available === null) {
            return 'Наличие не было известно';
        }

        if (! $item->supply_is_available) {
            return 'Не было в наличии при заказе';
        }

        if ($item->supply_stock_quantity === null) {
            return 'Было в наличии при заказе';
        }

        $quantity = (float) $item->supply_stock_quantity;
        $formatted = abs($quantity - round($quantity)) < 0.0005
            ? number_format($quantity, 0, '.', ' ')
            : rtrim(rtrim(number_format($quantity, 3, '.', ' '), '0'), '.');

        return $formatted.' шт. при заказе';
    }

    /** @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function pricedContext(OrderItem $item, float $wholesalePrice, array $context): array
    {
        $salePrice = (float) $item->price;
        $quantity = (int) $item->quantity;
        $hasPurchasePrice = $wholesalePrice > 0;
        $marginUnit = round($salePrice - $wholesalePrice, 2);

        return $context + [
            'wholesale_price' => $hasPurchasePrice ? round($wholesalePrice, 2) : null,
            'wholesale_price_label' => $hasPurchasePrice
                ? $context['wholesale_price_label']
                : 'Нет закупочной цены',
            'has_purchase_price' => $hasPurchasePrice,
            'quantity' => $quantity,
            'sale_total' => round($salePrice * $quantity, 2),
            'margin_unit' => $hasPurchasePrice ? $marginUnit : null,
            'margin_total' => $hasPurchasePrice ? round($marginUnit * $quantity, 2) : null,
            'margin_percent' => $hasPurchasePrice && $salePrice > 0
                ? round($marginUnit / $salePrice * 100, 1)
                : null,
        ];
    }

    private function integrationWholesalePrice(IntegrationProduct $offer): float
    {
        return $offer->source?->priceIncludingTax((float) $offer->price)
            ?? round((float) $offer->price, 2);
    }

    private function legacyOfferAvailable(SupplierProduct $offer): bool
    {
        return $offer->in_stock === true || (int) $offer->stock_quantity > 0;
    }
}
