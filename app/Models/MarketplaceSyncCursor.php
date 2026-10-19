<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketplaceSyncCursor extends Model
{
    protected $fillable = [
        'channel', 'stream', 'cursor_at', 'last_started_at', 'last_succeeded_at',
        'last_failed_at', 'last_error', 'metadata',
    ];

    protected $casts = [
        'cursor_at' => 'datetime',
        'last_started_at' => 'datetime',
        'last_succeeded_at' => 'datetime',
        'last_failed_at' => 'datetime',
        'metadata' => 'array',
    ];
}
