<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SelectionStockAlert extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['items' => 'array', 'sequence' => 'integer', 'read_at' => 'datetime'];

    public function watch(): BelongsTo
    {
        return $this->belongsTo(SelectionStockWatch::class, 'selection_stock_watch_id');
    }
}
