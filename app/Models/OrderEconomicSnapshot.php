<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class OrderEconomicSnapshot extends Model
{
    public const KIND_PLACED = 'placed';

    public const STATUS_COMPLETE = 'goods_complete';

    public const STATUS_MISSING_PRICE = 'missing_price';

    public const STATUS_MISSING_SUPPLIER = 'missing_supplier';

    public const STATUS_INCOMPLETE = 'incomplete';

    protected $fillable = [
        'order_id', 'kind', 'status', 'currency',
        'goods_sale_total', 'delivery_revenue', 'discount_total', 'order_total',
        'known_purchase_total', 'purchase_total', 'known_goods_margin_total',
        'goods_margin_total', 'goods_margin_percent', 'delivery_cost',
        'payment_fee', 'marketplace_commission', 'net_profit',
        'items_count', 'priced_items_count', 'missing_purchase_price_count',
        'missing_supplier_count', 'tax_modes', 'unknown_costs', 'captured_at',
    ];

    protected $casts = [
        'goods_sale_total' => 'decimal:2',
        'delivery_revenue' => 'decimal:2',
        'discount_total' => 'decimal:2',
        'order_total' => 'decimal:2',
        'known_purchase_total' => 'decimal:2',
        'purchase_total' => 'decimal:2',
        'known_goods_margin_total' => 'decimal:2',
        'goods_margin_total' => 'decimal:2',
        'goods_margin_percent' => 'decimal:2',
        'delivery_cost' => 'decimal:2',
        'payment_fee' => 'decimal:2',
        'marketplace_commission' => 'decimal:2',
        'net_profit' => 'decimal:2',
        'items_count' => 'integer',
        'priced_items_count' => 'integer',
        'missing_purchase_price_count' => 'integer',
        'missing_supplier_count' => 'integer',
        'tax_modes' => 'array',
        'unknown_costs' => 'array',
        'captured_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Экономический снимок заказа нельзя изменять.'));
        static::deleting(fn (): never => throw new LogicException('Экономический снимок заказа нельзя удалять отдельно от заказа.'));
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_COMPLETE => 'Входные цены зафиксированы',
            self::STATUS_MISSING_PRICE => 'Есть позиции без входной цены',
            self::STATUS_MISSING_SUPPLIER => 'Есть позиции без поставщика',
            default => 'Экономика заполнена частично',
        };
    }
}
