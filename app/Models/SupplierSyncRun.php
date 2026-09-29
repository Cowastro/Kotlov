<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierSyncRun extends Model
{
    protected $fillable = [
        'command', 'status', 'started_at', 'finished_at', 'duration_ms',
        'exit_code', 'changes_count', 'price_changes_count',
        'stock_changes_count', 'supplier_names', 'context',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'context' => 'array',
    ];

    public function changes(): HasMany
    {
        return $this->hasMany(SupplierSyncChange::class);
    }
}
