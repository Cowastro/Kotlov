<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItemFulfillmentHistory extends Model
{
    protected $fillable = [
        'order_item_id', 'user_id', 'previous_route', 'previous_supplier_id',
        'previous_supplier_name', 'previous_purchase_price', 'route', 'supplier_id',
        'supplier_name', 'supplier_contact', 'purchase_price', 'note',
    ];

    protected $casts = [
        'previous_purchase_price' => 'decimal:2',
        'purchase_price' => 'decimal:2',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function previousSupplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'previous_supplier_id');
    }
}
