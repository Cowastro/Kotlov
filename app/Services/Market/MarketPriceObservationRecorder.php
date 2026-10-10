<?php

namespace App\Services\Market;

use App\Models\MarketPriceObservation;
use App\Models\MarketPriceSource;
use App\Models\Product;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;

class MarketPriceObservationRecorder
{
    public function record(MarketPriceSource $source, Product $product, array $data): MarketPriceObservation
    {
        $url = trim((string) Arr::get($data, 'url'));
        $observedAt = CarbonImmutable::parse(Arr::get($data, 'observed_at', now()))->startOfSecond();
        $currency = strtoupper(trim((string) Arr::get($data, 'currency', $source->currency ?: 'BYN')));
        $rate = (float) Arr::get($data, 'exchange_rate_to_byn', $currency === 'BYN' ? 1 : 0);
        $observedPrice = round((float) Arr::get($data, 'observed_price'), 2);

        if ($url === '') {
            throw new \InvalidArgumentException('Для рыночного наблюдения требуется URL предложения.');
        }
        if ($observedPrice <= 0) {
            throw new \InvalidArgumentException('Цена предложения должна быть больше нуля.');
        }
        if ($rate <= 0) {
            throw new \InvalidArgumentException('Для валюты предложения требуется положительный курс к BYN.');
        }

        $urlHash = hash('sha256', MarketPriceObservation::canonicalUrl($url));
        $fingerprint = MarketPriceObservation::fingerprintFor(
            (int) $source->getKey(),
            (int) $product->getKey(),
            $urlHash,
            $observedAt->toIso8601String(),
        );

        $attributes = [
            ...Arr::only($data, [
                'external_name', 'external_sku', 'model', 'package', 'unit',
                'price_includes_vat', 'vat_rate', 'delivery_price_byn', 'delivery_terms', 'region',
                'availability_status', 'match_method', 'match_confidence',
                'is_confirmed', 'is_comparable', 'validation_flags',
            ]),
            'product_id' => $product->getKey(),
            'market_price_source_id' => $source->getKey(),
            'fingerprint' => $fingerprint,
            'url' => $url,
            'url_hash' => $urlHash,
            'observed_price' => $observedPrice,
            'currency' => $currency,
            'exchange_rate_to_byn' => $rate,
            'price_byn' => round($observedPrice * $rate, 2),
            'region' => Arr::get($data, 'region', $source->region ?: 'Беларусь'),
            'availability_status' => Arr::get($data, 'availability_status', 'unknown'),
            'match_method' => Arr::get($data, 'match_method', 'manual'),
            'match_confidence' => Arr::get($data, 'match_confidence', 0),
            'is_confirmed' => (bool) Arr::get($data, 'is_confirmed', false),
            'is_comparable' => (bool) Arr::get($data, 'is_comparable', false),
            'observed_at' => $observedAt,
        ];

        return MarketPriceObservation::query()->updateOrCreate(
            ['fingerprint' => $fingerprint],
            $attributes,
        );
    }
}
