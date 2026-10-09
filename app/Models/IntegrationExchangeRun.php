<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationExchangeRun extends Model
{
    protected $fillable = [
        'integration_source_id', 'direction', 'operation', 'status', 'session_key',
        'started_at', 'finished_at', 'duration_ms', 'files_count', 'bytes_received',
        'items_received', 'items_created', 'items_updated', 'items_skipped',
        'orders_count', 'summary', 'error_message',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'summary' => 'array',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(IntegrationSource::class, 'integration_source_id');
    }
}
