<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierProduct extends Model
{
    protected $fillable = [
        'supplier_id',
        'supplier_sync_id',
        'product_id',
        'product_sku',
        'supplier_article',
        'supplier_article_normalized',
        'supplier_name',
        'source_url',
        'source_wp_id',
        'price',
        'currency',
        'in_stock',
        'match_status',
        'match_confidence',
        'raw',
        'last_synced_at',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'in_stock' => 'boolean',
        'raw' => 'array',
        'last_synced_at' => 'datetime',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function supplierSync(): BelongsTo
    {
        return $this->belongsTo(SupplierSync::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
