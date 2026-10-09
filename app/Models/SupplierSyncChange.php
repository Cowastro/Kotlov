<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierSyncChange extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'supplier_sync_run_id', 'supplier_id', 'supplier_product_id',
        'product_id', 'supplier_name', 'supplier_article', 'product_sku',
        'product_name', 'change_flags', 'supplier_price_before',
        'supplier_price_after', 'retail_price_before', 'retail_price_after',
        'in_stock_before', 'in_stock_after', 'stock_quantity_before',
        'stock_quantity_after', 'stock_status_before', 'stock_status_after',
        'availability_before', 'availability_after', 'created_at',
    ];

    protected $casts = [
        'change_flags' => 'array',
        'in_stock_before' => 'boolean',
        'in_stock_after' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(SupplierSyncRun::class, 'supplier_sync_run_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function supplierProduct(): BelongsTo
    {
        return $this->belongsTo(SupplierProduct::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
