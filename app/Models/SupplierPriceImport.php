<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierPriceImport extends Model
{
    protected $fillable = [
        'supplier_id', 'filename', 'total_rows', 'matched',
        'unmatched', 'updated', 'skipped', 'status', 'error', 'imported_at',
    ];

    protected $casts = [
        'imported_at' => 'datetime',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SupplierPriceItem::class, 'import_id');
    }
}
