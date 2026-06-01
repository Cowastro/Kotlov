<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'user_id', 'number', 'status',
        'customer_name', 'customer_phone', 'customer_email',
        'delivery_type', 'delivery_region', 'delivery_city',
        'delivery_address', 'delivery_price',
        'payment_type', 'payment_status',
        'coupon_code', 'discount',
        'subtotal', 'total',
        'comment', 'admin_comment',
    ];

    protected $casts = [
        'subtotal'       => 'decimal:2',
        'total'          => 'decimal:2',
        'delivery_price' => 'decimal:2',
        'discount'       => 'decimal:2',
    ];

    // Статусы для отображения
    public const STATUSES = [
        'new'        => 'Новый',
        'confirmed'  => 'Подтверждён',
        'processing' => 'В обработке',
        'shipped'    => 'Отправлен',
        'delivered'  => 'Доставлен',
        'cancelled'  => 'Отменён',
    ];

    public const PAYMENT_TYPES = [
        'cash'    => 'Наличными',
        'card'    => 'Картой',
        'invoice' => 'По счёту',
    ];

    public const DELIVERY_TYPES = [
        'pickup'    => 'Самовывоз',
        'courier'   => 'Курьером',
        'transport' => 'Транспортной компанией',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    // Генерация номера заказа
    public static function generateNumber(): string
    {
        $year = date('Y');
        $last = self::whereYear('created_at', $year)->count() + 1;
        return 'ORD-' . $year . '-' . str_pad($last, 4, '0', STR_PAD_LEFT);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }
}
