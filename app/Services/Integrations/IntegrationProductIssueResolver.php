<?php

namespace App\Services\Integrations;

use App\Models\IntegrationIssue;
use App\Models\IntegrationProduct;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class IntegrationProductIssueResolver
{
    public function __construct(private readonly IntegrationProductMatchDecision $decision) {}

    public function linkExistingProduct(IntegrationIssue $issue, int $productId, ?int $resolvedByUserId = null): ?Product
    {
        return DB::transaction(function () use ($issue, $productId, $resolvedByUserId): ?Product {
            $issue = IntegrationIssue::query()->lockForUpdate()->find($issue->id);
            if (! $issue
                || $issue->status !== 'open'
                || ! in_array($issue->type, IntegrationIssue::PRODUCT_TYPES, true)
                || data_get($issue->context, 'unmatched') !== true
                || ! $issue->integration_product_id) {
                return null;
            }

            $item = IntegrationProduct::query()->find($issue->integration_product_id);
            if (! $item) {
                return null;
            }

            $product = $this->decision->linkSelectedProduct($item, $productId);
            if (! $product) {
                return null;
            }

            $context = $issue->context ?? [];
            $context['resolution'] = [
                'type' => 'linked_product',
                'product_id' => (int) $product->id,
                'product_name' => $product->name,
                'product_sku' => $product->sku,
                'resolved_by_user_id' => $resolvedByUserId,
                'linked_at' => now()->toIso8601String(),
            ];

            $issue->update([
                'status' => 'resolved',
                'resolved_at' => now(),
                'context' => $context,
            ]);

            return $product;
        });
    }
}
