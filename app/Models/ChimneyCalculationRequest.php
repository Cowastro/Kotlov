<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChimneyCalculationRequest extends Model
{
    public const STATUSES = [
        'pending' => 'Ожидает подтверждения',
        'converted' => 'Заказ создан',
        'cancelled' => 'Отклонён',
    ];

    protected $fillable = [
        'number', 'status', 'customer_name', 'customer_phone', 'customer_email',
        'source_url', 'configuration', 'items', 'equipment_product_id',
        'subtotal', 'discount_percent', 'discount', 'total', 'promotion_mode',
        'telegram_message_id', 'telegram_notified_at', 'confirmed_at',
        'confirmed_by_name', 'confirmed_by_user_id', 'order_id',
    ];

    protected $casts = [
        'items' => 'array',
        'subtotal' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',
        'telegram_notified_at' => 'datetime',
        'confirmed_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by_user_id');
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'equipment_product_id');
    }
}
