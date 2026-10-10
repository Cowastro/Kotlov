<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class OrderSettlementLine extends Model
{
    protected $fillable = [
        'order_settlement_id', 'order_item_id', 'supplier_id', 'supplier_name',
        'supplier_contact', 'route', 'product_name', 'product_sku', 'quantity',
        'sale_total', 'purchase_price', 'cost_of_goods', 'supplier_payable',
        'commission_rate', 'commission_amount', 'platform_margin',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'sale_total' => 'decimal:2',
        'purchase_price' => 'decimal:2',
        'cost_of_goods' => 'decimal:2',
        'supplier_payable' => 'decimal:2',
        'commission_rate' => 'decimal:4',
        'commission_amount' => 'decimal:2',
        'platform_margin' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Строку подтверждённого взаиморасчёта нельзя изменять.'));
        static::deleting(fn (): never => throw new LogicException('Строку подтверждённого взаиморасчёта нельзя удалять отдельно.'));
    }

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(OrderSettlement::class, 'order_settlement_id');
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
