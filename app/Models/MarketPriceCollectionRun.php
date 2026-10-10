<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketPriceCollectionRun extends Model
{
    public const STATUSES = [
        'running' => 'Выполняется',
        'success' => 'Успешно',
        'warning' => 'С предупреждениями',
        'failed' => 'Ошибка',
        'blocked' => 'Заблокировано правилами',
    ];

    public const TRIGGERS = [
        'manual' => 'Ручной запуск',
        'scheduled' => 'По расписанию',
        'import' => 'Импорт',
        'api' => 'API',
    ];

    protected $fillable = [
        'market_price_source_id', 'session_uuid', 'trigger', 'status',
        'requested_count', 'recorded_count', 'skipped_count',
        'warning_count', 'error_count', 'events', 'summary', 'created_by',
        'started_at', 'finished_at',
    ];

    protected $casts = [
        'requested_count' => 'integer',
        'recorded_count' => 'integer',
        'skipped_count' => 'integer',
        'warning_count' => 'integer',
        'error_count' => 'integer',
        'events' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(MarketPriceSource::class, 'market_price_source_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}
