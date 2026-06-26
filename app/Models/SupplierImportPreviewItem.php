<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierImportPreviewItem extends Model
{
    protected $fillable = [
        'run_id',
        'row_number',
        'raw_data',
        'parsed_article',
        'parsed_article_strict',
        'parsed_article_compact',
        'parsed_name',
        'parsed_price_byn',
        'parsed_in_stock',
        'match_method',
        'matched_product_id',
        'matched_supplier_product_id',
        'status',
        'status_detail',
        'action',
        'override_action',
        'applied_at',
        'error_message',
    ];

    protected $casts = [
        'row_number' => 'integer',
        'raw_data' => 'array',
        'parsed_price_byn' => 'decimal:2',
        'parsed_in_stock' => 'boolean',
        'applied_at' => 'datetime',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(SupplierImportRun::class, 'run_id');
    }

    public function matchedProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'matched_product_id');
    }

    public function matchedSupplierProduct(): BelongsTo
    {
        return $this->belongsTo(SupplierProduct::class, 'matched_supplier_product_id');
    }
}
