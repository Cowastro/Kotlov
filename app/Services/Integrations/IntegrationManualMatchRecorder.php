<?php

namespace App\Services\Integrations;

use App\Models\IntegrationProduct;
use App\Models\SupplierProductMapping;
use App\Services\Integrations\OneCBridge\OneCItemLinks;

class IntegrationManualMatchRecorder
{
    public function record(IntegrationProduct $item): ?SupplierProductMapping
    {
        $item->load(['source', 'product']);
        // A shared article must not teach all colours/sizes to point at one card.
        $identity = OneCItemLinks::identity((string) $item->external_id);
        if ($item->source?->code === 'onec' && $identity && str_contains($identity, '#')) {
            return null;
        }

        $article = trim((string) ($item->external_sku ?: $item->external_code));

        if (! $item->source || ! $item->product || $article === '') {
            return null;
        }

        return SupplierProductMapping::query()->updateOrCreate(
            [
                'supplier_code' => $item->source->matchingSupplierCode(),
                'supplier_article' => $article,
            ],
            [
                'product_id' => $item->product->id,
                'product_sku' => $item->product->sku,
                'supplier_name' => $item->name,
                'confidence' => 'manual',
                'is_active' => true,
                'notes' => 'Подтверждено в привязке товаров; внешний ID: '.$item->external_id,
            ],
        );
    }
}
