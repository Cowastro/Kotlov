<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class B2bOrderReservation extends Model
{
    public const STATUSES = [
        'active' => 'В резерве',
        'shipped' => 'Отгружено',
        'released' => 'Снят',
    ];

    protected $fillable = [
        'b2b_order_id', 'b2b_order_item_id', 'product_id', 'stock_key', 'integration_product_id',
        'warehouse_stock_id', 'supplier_product_id', 'supplier_id', 'warehouse_name', 'quantity',
        'status', 'reserved_by', 'released_at',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'released_at' => 'datetime',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function order(): BelongsTo { return $this->belongsTo(B2bOrder::class, 'b2b_order_id'); }
    public function item(): BelongsTo { return $this->belongsTo(B2bOrderItem::class, 'b2b_order_item_id'); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function supplier(): BelongsTo { return $this->belongsTo(Supplier::class); }
}
