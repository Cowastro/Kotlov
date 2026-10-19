<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderPortalEvent extends Model
{
    protected $fillable = [
        'order_id', 'portal_link_id', 'portal_session_id', 'type',
        'ip_hash', 'user_agent', 'metadata', 'occurred_at',
    ];

    protected $casts = ['metadata' => 'array', 'occurred_at' => 'datetime'];

    public function session(): BelongsTo
    {
        return $this->belongsTo(OrderPortalSession::class, 'portal_session_id');
    }
}
