<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id', 'product_id',
        'product_name', 'product_sku',
        'price', 'quantity', 'total',
        'pricing_type', 'price_tax_mode', 'integration_product_id',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function integrationProduct(): BelongsTo
    {
        return $this->belongsTo(IntegrationProduct::class);
    }
}
