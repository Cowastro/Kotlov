<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelephonyCallEvent extends Model
{
    protected $fillable = [
        'telephony_call_id', 'provider', 'external_uuid', 'notification_type',
        'state', 'occurred_at', 'payload_hash', 'payload', 'received_at',
    ];

    protected $casts = ['occurred_at' => 'datetime', 'received_at' => 'datetime', 'payload' => 'array'];

    public function call(): BelongsTo
    {
        return $this->belongsTo(TelephonyCall::class, 'telephony_call_id');
    }
}
