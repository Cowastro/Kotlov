<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketPriceObservation extends Model
{
    public const AVAILABILITY_STATUSES = [
        'in_stock' => 'В наличии',
        'order' => 'Под заказ',
        'out_of_stock' => 'Нет в наличии',
        'unknown' => 'Неизвестно',
    ];

    public const MATCH_METHODS = [
        'manual' => 'Подтверждено вручную',
        'exact_sku' => 'Точный артикул',
        'exact_model' => 'Точная модель',
        'automatic' => 'Автоматическое предложение',
    ];

    protected $fillable = [
        'product_id', 'market_price_source_id', 'fingerprint', 'url', 'url_hash',
        'external_name', 'external_sku', 'model', 'package', 'unit',
        'observed_price', 'currency', 'exchange_rate_to_byn', 'price_byn',
        'price_includes_vat', 'vat_rate', 'delivery_price_byn', 'region',
        'availability_status', 'match_method', 'match_confidence',
        'is_confirmed', 'is_comparable', 'validation_flags', 'observed_at',
    ];

    protected $casts = [
        'observed_price' => 'decimal:2',
        'exchange_rate_to_byn' => 'decimal:6',
        'price_byn' => 'decimal:2',
        'price_includes_vat' => 'boolean',
        'vat_rate' => 'decimal:2',
        'delivery_price_byn' => 'decimal:2',
        'match_confidence' => 'decimal:4',
        'is_confirmed' => 'boolean',
        'is_comparable' => 'boolean',
        'validation_flags' => 'array',
        'observed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (MarketPriceObservation $observation): void {
            $url = trim((string) $observation->url);
            $price = (float) $observation->observed_price;
            $rate = (float) $observation->exchange_rate_to_byn;

            if ($url === '' || $price <= 0 || $rate <= 0) {
                throw new \InvalidArgumentException('URL, цена и положительный курс к BYN обязательны.');
            }

            $observation->currency = strtoupper(trim((string) $observation->currency));
            $observation->url_hash = hash('sha256', self::canonicalUrl($url));
            $observation->price_byn = round($price * $rate, 2);
            $observation->observed_at = CarbonImmutable::parse($observation->observed_at)->startOfSecond();
            $observation->fingerprint = self::fingerprintFor(
                (int) $observation->market_price_source_id,
                (int) $observation->product_id,
                $observation->url_hash,
                $observation->observed_at->toIso8601String(),
            );
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(MarketPriceSource::class, 'market_price_source_id');
    }

    public function availabilityLabel(): string
    {
        return self::AVAILABILITY_STATUSES[$this->availability_status] ?? $this->availability_status;
    }

    public static function fingerprintFor(int $sourceId, int $productId, string $urlHash, string $observedAt): string
    {
        return hash('sha256', implode('|', [$sourceId, $productId, $urlHash, $observedAt]));
    }

    public static function canonicalUrl(string $url): string
    {
        $parts = parse_url($url);
        if ($parts === false || empty($parts['host'])) {
            return trim($url);
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? 'https'));
        $host = strtolower((string) $parts['host']);
        $path = '/'.ltrim((string) ($parts['path'] ?? ''), '/');

        return rtrim($scheme.'://'.$host.$path, '/');
    }
}
