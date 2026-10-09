<?php

namespace App\Services\Integrations;

use App\Models\IntegrationProduct;
use App\Models\SupplierProductMapping;

class IntegrationManualMatchRecorder
{
    public function record(IntegrationProduct $item): ?SupplierProductMapping
    {
        $item->load(['source', 'product']);
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
