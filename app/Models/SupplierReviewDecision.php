<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierReviewDecision extends Model
{
    public const DECISION_LINK = 'link_supplier_product';
    public const DECISION_UNLINK = 'unlink_supplier_product';
    public const DECISION_UPDATE_RETAIL_PRICE = 'update_product_retail_price';
    public const DECISION_UPDATE_PRODUCT_CATALOG = 'update_product_catalog';
    public const DECISION_IGNORE = 'ignore';

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPLIED = 'applied';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'decision_key',
        'supplier_code',
        'report_file',
        'report_row',
        'decision',
        'status',
        'supplier_product_id',
        'product_id',
        'supplier_title',
        'supplier_article',
        'source_url',
        'reason',
        'payload',
        'applied_at',
        'applied_by',
        'error',
    ];

    protected $casts = [
        'payload' => 'array',
        'applied_at' => 'datetime',
    ];

    public function supplierProduct(): BelongsTo
    {
        return $this->belongsTo(SupplierProduct::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
