<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SelectionStockWatch extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['enabled' => 'boolean', 'baseline' => 'array', 'sequence' => 'integer', 'checked_at' => 'datetime'];

    public function selection(): BelongsTo
    {
        return $this->belongsTo(CustomerSelection::class, 'customer_selection_id');
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(SelectionStockAlert::class);
    }
}
