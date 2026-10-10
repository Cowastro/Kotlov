<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderIntegrationDelivery extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_SENT = 'sent';

    public const STATUS_ACKNOWLEDGED = 'acknowledged';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'order_id', 'integration_source_id', 'status', 'external_id', 'remote_status',
        'last_attempted_at', 'exported_at', 'status_received_at', 'last_error', 'metadata',
    ];

    protected $casts = [
        'last_attempted_at' => 'datetime',
        'exported_at' => 'datetime',
        'status_received_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(IntegrationSource::class, 'integration_source_id');
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_SENT => 'Передан',
            self::STATUS_ACKNOWLEDGED => 'Ответ получен',
            self::STATUS_FAILED => 'Ошибка',
            default => 'Ожидает передачи',
        };
    }
}
