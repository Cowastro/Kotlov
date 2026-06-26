<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierSource extends Model
{
    protected $fillable = [
        'supplier_id',
        'name',
        'type',
        'config',
        'column_mappings',
        'schedule_cron',
        'is_active',
        'last_run_at',
    ];

    protected $casts = [
        'config' => 'array',
        'column_mappings' => 'array',
        'is_active' => 'boolean',
        'last_run_at' => 'datetime',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function importRuns(): HasMany
    {
        return $this->hasMany(SupplierImportRun::class, 'source_id');
    }
}
