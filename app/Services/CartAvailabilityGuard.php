<?php

namespace App\Services;

use App\Models\Product;

class CartAvailabilityGuard
{
    /**
     * Keep active retail products orderable even while their balance is being
     * confirmed. The order carries an explicit manager-attention marker. B2B
     * items are validated separately against their exact 1C offer.
     *
     * @return array{cart: array, removed_ids: array<int, int>, price_changed_ids: array<int, int>}
     */
    public function reconcileRetailItems(array $cart): array
    {
        $retailIds = collect($cart)
            ->filter(fn (array $item): bool => ($item['pricing_type'] ?? 'retail') !== 'b2b')
            ->map(fn (array $item, $key): int => (int) ($item['id'] ?? $key))
            ->filter()
            ->values();

        if ($retailIds->isEmpty()) {
            return ['cart' => $cart, 'removed_ids' => [], 'price_changed_ids' => []];
        }

        $products = Product::query()
            ->withSupplierChannels()
            ->whereIn('id', $retailIds)
            ->get()
            ->keyBy('id');
        $removedIds = [];
        $priceChangedIds = [];

        foreach ($cart as $key => $item) {
            if (($item['pricing_type'] ?? 'retail') === 'b2b') {
                continue;
            }

            $productId = (int) ($item['id'] ?? $key);
            $product = $products->get($productId);

            if (! $product || ! $product->canBeAddedToCart()) {
                unset($cart[$key]);
                $removedIds[] = $productId;

                continue;
            }

            $cart[$key]['requires_availability_confirmation'] = $product->requiresAvailabilityConfirmation();

            $currentPrice = round((float) $product->price, 2);
            if (abs((float) ($item['price'] ?? 0) - $currentPrice) >= 0.01) {
                $cart[$key]['price'] = $currentPrice;
                $priceChangedIds[] = $productId;
            }
        }

        return [
            'cart' => $cart,
            'removed_ids' => $removedIds,
            'price_changed_ids' => $priceChangedIds,
        ];
    }
}
