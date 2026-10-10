<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketPriceSource extends Model
{
    public const KINDS = [
        'competitor' => 'Конкурент',
        'marketplace' => 'Маркетплейс',
        'manufacturer' => 'Производитель',
        'rrp' => 'Рекомендованная розница',
    ];

    public const COLLECTION_METHODS = [
        'manual' => 'Вручную',
        'import' => 'Импорт',
        'api' => 'API',
        'scrape' => 'Разрешённый сбор',
    ];

    protected $fillable = [
        'code', 'name', 'kind', 'collection_method', 'base_url', 'currency',
        'region', 'freshness_hours', 'minimum_match_confidence', 'is_active', 'notes',
    ];

    protected $casts = [
        'freshness_hours' => 'integer',
        'minimum_match_confidence' => 'decimal:4',
        'is_active' => 'boolean',
    ];

    public function observations(): HasMany
    {
        return $this->hasMany(MarketPriceObservation::class);
    }

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind] ?? $this->kind;
    }
}
