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

            $item->update([
                'product_id' => $product->id,
                'match_status' => 'matched',
                'match_method' => 'manual_candidate',
                'match_confidence' => 1,
                'matched_at' => now(),
            ]);
            $this->recorder->record($item);

            return $product;
        });
    }
}
