<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderPortalSession extends Model
{
    protected $fillable = [
        'portal_link_id', 'token_hash', 'device_label', 'ip_hash', 'user_agent',
        'authorized_at', 'last_seen_at', 'expires_at', 'revoked_at',
    ];

    protected $hidden = ['token_hash', 'ip_hash'];

    protected $casts = [
        'authorized_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function link(): BelongsTo
    {
        return $this->belongsTo(OrderPortalLink::class, 'portal_link_id');
    }
}
