<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class OrderSettlement extends Model
{
    protected $fillable = [
        'order_id', 'version', 'route_signature', 'basis_signature', 'currency',
        'goods_sale_total', 'delivery_revenue', 'discount_total', 'order_total', 'cost_of_goods_total',
        'supplier_payable_total', 'marketplace_commission_total', 'reseller_margin_total',
        'platform_gross_margin_total', 'delivery_cost', 'payment_fee', 'refund_total',
        'net_profit', 'confirmed_by', 'confirmed_at', 'note', 'calculation_basis',
    ];

    protected $casts = [
        'version' => 'integer',
        'goods_sale_total' => 'decimal:2',
        'delivery_revenue' => 'decimal:2',
        'discount_total' => 'decimal:2',
        'order_total' => 'decimal:2',
        'cost_of_goods_total' => 'decimal:2',
        'supplier_payable_total' => 'decimal:2',
        'marketplace_commission_total' => 'decimal:2',
        'reseller_margin_total' => 'decimal:2',
        'platform_gross_margin_total' => 'decimal:2',
        'delivery_cost' => 'decimal:2',
        'payment_fee' => 'decimal:2',
        'refund_total' => 'decimal:2',
        'net_profit' => 'decimal:2',
        'confirmed_at' => 'datetime',
        'calculation_basis' => 'array',
    ];

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Подтверждённый взаиморасчёт нельзя изменять. Создайте новую версию.'));
        static::deleting(fn (): never => throw new LogicException('Подтверждённый взаиморасчёт нельзя удалять отдельно от заказа.'));
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(OrderSettlementLine::class);
    }
}
