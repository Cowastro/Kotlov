<?php

namespace App\Services\Integrations;

use App\Models\IntegrationProduct;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class IntegrationProductMatchDecision
{
    public function __construct(
        private readonly IntegrationProductMatchAdvisor $advisor,
        private readonly IntegrationManualMatchRecorder $recorder,
    ) {}

    public function confirmCandidate(IntegrationProduct $item, int $productId): ?Product
    {
        return DB::transaction(function () use ($item, $productId): ?Product {
            $item = IntegrationProduct::query()->lockForUpdate()->find($item->id);
            if (! $item) {
                return null;
            }

            $candidate = collect($this->advisor->candidates($item))
                ->firstWhere('product_id', $productId);
            if (! $candidate) {
                return null;
            }

            $product = Product::query()->find($productId);
            if (! $product) {
                return null;
            }

            $this->persist($item, $product, 'manual_candidate');

            return $product;
        });
    }

    public function confirmSuggestion(IntegrationProduct $item): ?Product
    {
        return DB::transaction(function () use ($item): ?Product {
            $item = IntegrationProduct::query()->lockForUpdate()->find($item->id);
            if (! $item) {
                return null;
            }

            $advice = $this->advisor->explain($item);
            if (! $advice) {
                return null;
            }

            $product = Product::query()->find((int) $advice['product_id']);
            if (! $product) {
                return null;
            }

            $this->persist($item, $product, 'manual_suggestion');

            return $product;
        });
    }

    public function linkSelectedProduct(IntegrationProduct $item, int $productId): ?Product
    {
        return DB::transaction(function () use ($item, $productId): ?Product {
            $item = IntegrationProduct::query()->lockForUpdate()->find($item->id);
            if (! $item || $item->product_id || $item->match_status === 'ignored') {
                return null;
            }

            $product = Product::query()->find($productId);
            if (! $product) {
                return null;
            }

            $this->persist($item, $product, 'manual_search');

            return $product;
        });
    }

    private function persist(IntegrationProduct $item, Product $product, string $method): void
    {
        $item->update([
            'product_id' => $product->id,
            'match_status' => 'matched',
            'match_method' => $method,
            'match_confidence' => 1,
            'matched_at' => now(),
        ]);
        $this->recorder->record($item);
    }
}
