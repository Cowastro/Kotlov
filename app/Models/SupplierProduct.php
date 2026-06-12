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
        'currency_rate',
        'price_byn',
        'in_stock',
        'stock_quantity',
        'stock_status',
        'stock_text',
        'warehouse_name',
        'delivery_days',
        'last_stock_synced_at',
        'match_status',
        'match_confidence',
        'raw',
        'last_synced_at',
    ];

    protected $casts = [
        'price'                => 'decimal:2',
        'currency_rate'        => 'float',
        'price_byn'            => 'decimal:2',
        'in_stock'             => 'boolean',
        'stock_quantity'       => 'integer',
        'delivery_days'        => 'integer',
        'raw'                  => 'array',
        'last_synced_at'       => 'datetime',
        'last_stock_synced_at' => 'datetime',
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
