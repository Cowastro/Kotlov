<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DealByOrderInbox extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSED = 'processed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'remote_order_id', 'remote_status', 'remote_status_name', 'remote_created_at',
        'remote_modified_at', 'first_seen_at', 'last_seen_at', 'payload_hash', 'payload',
        'processing_status', 'order_id', 'processing_attempts', 'processed_at', 'last_error',
    ];

    protected $hidden = ['payload'];

    protected $casts = [
        'remote_created_at' => 'datetime',
        'remote_modified_at' => 'datetime',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'payload' => 'encrypted:array',
        'processed_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
