<?php

namespace App\Services\CommercialDocuments;

use App\Models\OrderItem;
use App\Models\Product;
use App\Services\Market\MarketPriceSummary;
use App\Services\Orders\OrderItemSupplyContextResolver;

class CommercialOfferCopilotService
{
    public function __construct(
        private readonly QuickCommercialOfferService $offers,
        private readonly MarketPriceSummary $market,
        private readonly OrderItemSupplyContextResolver $supplyContexts,
    ) {}

    /** @return array<string, mixed> */
    public function analyze(string $text, ?bool $useAi = null): array
    {
        return $this->offers->prepareText($text, $useAi);
    }

    /** @param array<int|string> $productIds
     * @param  array<int|string, float|int|string>  $quantities
     * @return list<array<string, mixed>>
     */
    public function itemRows(array $productIds, array $quantities = []): array
    {
        $products = Product::query()->whereIn('id', $productIds)->get()->keyBy('id');

        return collect($productIds)->map(function ($id) use ($products, $quantities): ?array {
            $product = $products->get((int) $id);
            if (! $product) {
                return null;
            }

            $quantity = max(0.001, (float) ($quantities[$product->id] ?? 1));

            return [
                'product_id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'unit' => $product->unit ?: 'шт.',
                'quantity' => $quantity,
                'unit_price' => (float) $product->price,
                'discount_percent' => 0,
                'vat_rate' => 20,
                'line_total' => round($quantity * (float) $product->price, 2),
            ];
        })->filter()->values()->all();
    }

    /** @return array<string, mixed> */
    public function pricing(int $productId, float $quotePrice): array
    {
        $product = Product::query()->find($productId);
        if (! $product) {
            return ['status' => 'missing_product', 'label' => 'Товар не выбран'];
        }

        $product->loadMissing([
            'marketPriceObservations.source',
            'integrationProducts.source.supplier.latestChannelTransition',
            'supplierProducts.supplier.latestChannelTransition',
        ]);
        $market = $this->market->forProduct($product);
        $item = new OrderItem(['product_id' => $product->id, 'price' => $quotePrice, 'quantity' => 1]);
        $item->setRelation('product', $product);
        $supply = $this->supplyContexts->resolve($item);
        $purchasePrice = $supply['has_purchase_price'] ? (float) $supply['wholesale_price'] : null;
        $margin = $purchasePrice !== null ? round($quotePrice - $purchasePrice, 2) : null;
        $marginPercent = $margin !== null && $quotePrice > 0 ? round($margin / $quotePrice * 100, 1) : null;
        $minimumMargin = (float) config('shop.order_management.minimum_margin_percent', 10);
        $minimumSafePrice = $purchasePrice !== null && $minimumMargin < 100
            ? round($purchasePrice / (1 - $minimumMargin / 100), 2)
            : null;
        $quoteDeltaPercent = null;
        $quotePosition = null;
        if ($market['status'] === 'ready' && (float) $market['median'] > 0) {
            $quoteDeltaPercent = round(($quotePrice - (float) $market['median']) / (float) $market['median'] * 100, 1);
            $quotePosition = match (true) {
                $quoteDeltaPercent < -5 => 'below',
                $quoteDeltaPercent > 5 => 'above',
                default => 'market',
            };
        }
        $recommendedPrice = null;
        $recommendationStatus = 'insufficient';
        if ($market['status'] === 'ready') {
            $recommendedPrice = round(max((float) $market['median'], (float) ($minimumSafePrice ?? 0)), 2);
            $recommendationStatus = $recommendedPrice <= (float) $market['maximum'] ? 'ready' : 'margin_conflict';
            if ($recommendationStatus === 'margin_conflict') {
                $recommendedPrice = null;
            }
        }

        return [
            'status' => 'ready',
            'label' => $this->label($market, $quotePosition, $marginPercent, $minimumMargin),
            'quote_price' => $quotePrice,
            'quote_market_position' => $quotePosition,
            'quote_market_delta_percent' => $quoteDeltaPercent,
            'supplier_name' => $supply['supplier_name'],
            'purchase_price_source' => $supply['source_label'],
            'purchase_price_label' => $supply['wholesale_price_label'],
            'stock_label' => $supply['stock_label'],
            'purchase_price' => $purchasePrice,
            'margin_byn' => $margin,
            'margin_percent' => $marginPercent,
            'minimum_margin_percent' => $minimumMargin,
            'minimum_safe_price' => $minimumSafePrice,
            'recommended_price' => $recommendedPrice,
            'recommendation_status' => $recommendationStatus,
            'market' => $market,
        ];
    }

    /** @param array<string, mixed> $market */
    private function label(array $market, ?string $quotePosition, ?float $marginPercent, float $minimumMargin): string
    {
        if ($marginPercent !== null && $marginPercent < $minimumMargin) {
            return 'Маржа ниже допустимой';
        }

        if ($market['status'] !== 'ready') {
            return 'Недостаточно данных рынка';
        }

        return match ($quotePosition) {
            'below' => 'Цена КП ниже рынка',
            'above' => 'Цена КП выше рынка',
            default => 'Цена КП в рынке',
        };
    }
}
