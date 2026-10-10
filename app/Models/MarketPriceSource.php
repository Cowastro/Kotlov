<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
        'code', 'name', 'kind', 'collection_method', 'adapter_key', 'collection_settings', 'base_url', 'currency',
        'region', 'freshness_hours', 'collection_interval_minutes',
        'max_requests_per_run', 'max_requests_per_day', 'allowed_path_prefixes',
        'respect_robots_txt', 'collection_authorized', 'last_collection_at',
        'next_collection_at', 'minimum_match_confidence', 'is_active', 'notes',
    ];

    protected $casts = [
        'freshness_hours' => 'integer',
        'collection_interval_minutes' => 'integer',
        'max_requests_per_run' => 'integer',
        'max_requests_per_day' => 'integer',
        'allowed_path_prefixes' => 'array',
        'collection_settings' => 'array',
        'respect_robots_txt' => 'boolean',
        'collection_authorized' => 'boolean',
        'last_collection_at' => 'datetime',
        'next_collection_at' => 'datetime',
        'minimum_match_confidence' => 'decimal:4',
        'is_active' => 'boolean',
    ];

    public function observations(): HasMany
    {
        return $this->hasMany(MarketPriceObservation::class);
    }

    public function collectionRuns(): HasMany
    {
        return $this->hasMany(MarketPriceCollectionRun::class);
    }

    public function latestCollectionRun(): HasOne
    {
        return $this->hasOne(MarketPriceCollectionRun::class)->latestOfMany('started_at');
    }

    public function usesRemoteCollection(): bool
    {
        return in_array($this->collection_method, ['api', 'scrape'], true);
    }

    public function collectionScheduleLabel(): string
    {
        if ($this->collection_method === 'manual') {
            return 'Только вручную';
        }

        return 'Каждые '.$this->collection_interval_minutes.' мин · до '
            .$this->max_requests_per_run.' запросов за запуск';
    }

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind] ?? $this->kind;
    }
}
