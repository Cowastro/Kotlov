<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelephonyEmployee extends Model
{
    protected $fillable = [
        'user_id', 'provider', 'external_id', 'name', 'email', 'group_name',
        'full_number', 'short_number', 'is_active', 'last_synced_at',
    ];

    protected $casts = ['is_active' => 'boolean', 'last_synced_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
