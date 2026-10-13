<?php

namespace App\Services;

use App\Models\Product;

class CartAvailabilityGuard
{
    /**
     * Remove retail items that no longer have a confirmed positive supplier
     * balance. B2B items are validated separately against their exact 1C offer.
     *
     * @return array{cart: array, removed_ids: array<int, int>}
     */
    public function pruneUnavailableRetailItems(array $cart): array
    {
        $retailIds = collect($cart)
            ->filter(fn (array $item): bool => ($item['pricing_type'] ?? 'retail') !== 'b2b')
            ->map(fn (array $item, $key): int => (int) ($item['id'] ?? $key))
            ->filter()
            ->values();

        if ($retailIds->isEmpty()) {
            return ['cart' => $cart, 'removed_ids' => []];
        }

        $products = Product::query()
            ->withSupplierChannels()
            ->whereIn('id', $retailIds)
            ->get()
            ->keyBy('id');
        $removedIds = [];

        foreach ($cart as $key => $item) {
            if (($item['pricing_type'] ?? 'retail') === 'b2b') {
                continue;
            }

            $productId = (int) ($item['id'] ?? $key);
            $product = $products->get($productId);

            if (! $product || ! $product->canBePurchased()) {
                unset($cart[$key]);
                $removedIds[] = $productId;
            }
        }

        return ['cart' => $cart, 'removed_ids' => $removedIds];
    }
}
