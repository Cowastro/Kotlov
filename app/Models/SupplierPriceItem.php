<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierPriceItem extends Model
{
    protected $fillable = [
        'supplier_id', 'import_id', 'article', 'name',
        'price', 'price_byn', 'in_stock', 'stock_qty', 'raw',
        'product_id', 'product_sku', 'match_status', 'match_method',
    ];

    protected $casts = [
        'in_stock' => 'boolean',
        'raw'      => 'array',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(SupplierPriceImport::class, 'import_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
