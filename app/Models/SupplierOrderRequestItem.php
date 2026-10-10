<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierOrderRequestItem extends Model
{
    protected $fillable = [
        'supplier_order_request_id', 'order_item_id', 'product_name',
        'product_sku', 'quantity', 'purchase_price', 'purchase_total',
    ];

    protected $casts = [
        'purchase_price' => 'decimal:2',
        'purchase_total' => 'decimal:2',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(SupplierOrderRequest::class, 'supplier_order_request_id');
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
