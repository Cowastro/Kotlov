<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class B2bSavedCart extends Model
{
    protected $fillable = [
        'user_id', 'name', 'items', 'items_count', 'total',
    ];

    protected $casts = [
        'items' => 'array',
        'items_count' => 'integer',
        'total' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
