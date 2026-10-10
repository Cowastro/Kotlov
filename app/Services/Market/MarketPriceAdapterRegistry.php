<?php

namespace App\Services\Market;

use App\Services\Market\Adapters\JsonFeedMarketPriceAdapter;
use App\Services\Market\Contracts\MarketPriceSourceAdapter;

class MarketPriceAdapterRegistry
{
    public const LABELS = [
        'json_feed_v1' => 'Универсальный JSON v1 (точный SKU)',
    ];

    public function resolve(?string $key): ?MarketPriceSourceAdapter
    {
        return match ($key) {
            'json_feed_v1' => app(JsonFeedMarketPriceAdapter::class),
            default => null,
        };
    }
}
