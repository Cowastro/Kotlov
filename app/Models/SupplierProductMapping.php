<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierProductMapping extends Model
{
    protected $fillable = [
        'supplier_code',
        'supplier_article',
        'product_id',
        'product_sku',
        'supplier_name',
        'confidence',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
