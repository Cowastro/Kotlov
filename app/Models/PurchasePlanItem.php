<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class PurchasePlanItem extends Model
{
    protected $fillable = [
        'purchase_plan_id', 'product_id', 'integration_product_id', 'product_sku',
        'product_name', 'current_stock', 'target_stock', 'recommended_quantity',
        'planned_quantity', 'unit_purchase_price', 'purchase_total',
        'price_tax_mode', 'vat_rate', 'stock_confirmed_at', 'explanation',
    ];

    protected $casts = [
        'current_stock' => 'decimal:3',
        'target_stock' => 'integer',
        'recommended_quantity' => 'integer',
        'planned_quantity' => 'integer',
        'unit_purchase_price' => 'decimal:2',
        'purchase_total' => 'decimal:2',
        'vat_rate' => 'decimal:2',
        'stock_confirmed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Снимок позиции плана закупки нельзя изменять.'));
        static::deleting(fn (): never => throw new LogicException('Позицию плана закупки нельзя удалить отдельно от плана.'));
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(PurchasePlan::class, 'purchase_plan_id');
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
