<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

class Order extends Model
{
    protected $fillable = [
        'user_id', 'number', 'status',
        'customer_name', 'customer_phone', 'customer_email',
        'company_name', 'company_unp', 'company_address', 'company_email',
        'delivery_type', 'delivery_region', 'delivery_city',
        'delivery_address', 'delivery_price',
        'payment_type', 'payment_status',
        'coupon_code', 'discount',
        'subtotal', 'total',
        'comment', 'admin_comment',
        'assigned_to', 'telegram_message_id', 'manager_id',
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
        'cash'               => 'Наличными',
        'card'               => 'Банковской платежной карточкой',
        'bank_transfer'      => 'Безналичный расчёт',
        'currency_transfer'  => 'Оплата для РФ и Казахстана',
        'installment_6'      => 'Онлайн-рассрочка на 6 месяцев',
        'credit_3_years'     => 'Кредит до 3 лет',
        'online_card'        => 'Онлайн-оплата картой',
        'halva'              => 'Карта «Халва»',
        'belgazprombank'     => 'Белгазпромбанк',
        'belarusbank_magnit' => 'Беларусбанк «Магнит Green»',
        'cherepaha'          => 'Карта «Черепаха» от ВТБ',
        'webpay'             => 'WEBPAY',
        'invoice'            => 'По счёту (для организаций)',
    ];

    public const DELIVERY_TYPES = [
        'pickup'    => 'Самовывоз',
        'courier'   => 'Доставка курьером по г. Минску',
        'transport' => 'Транспортная компания по Беларуси',
        'kit'       => 'ТК КИТ — Россия, Казахстан, Армения, Киргизия',
    ];

    protected static function booted(): void
    {
        static::updating(function (Order $order) {
            $userId = Auth::id();

            // Автоназначение ответственного при изменении статуса из CRM
            // Не перезаписываем если уже назначен
            if ($order->isDirty('status') && $userId && !$order->manager_id) {
                $order->manager_id   = $userId;
                $order->assigned_to  = '@' . (Auth::user()->telegram_username ?? Auth::user()->name);
            }

            // История изменений статуса
            if ($order->isDirty('status')) {
                OrderStatusHistory::create([
                    'order_id'    => $order->id,
                    'user_id'     => $userId,
                    'status_from' => $order->getOriginal('status'),
                    'status_to'   => $order->status,
                ]);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Ответственный CRM-пользователь (admin / будущий manager).
     * assigned_to хранит Telegram @username как лог — не удалять.
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
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
        $year   = date('Y');
        $prefix = 'ORD-' . $year . '-';

        $last = self::where('number', 'like', $prefix . '%')
            ->lockForUpdate()
            ->max(\Illuminate\Support\Facades\DB::raw('CAST(SUBSTRING(number, ' . (strlen($prefix) + 1) . ') AS UNSIGNED)'));

        $next = (int) $last + 1;

        return $prefix . str_pad($next, 4, '0', STR_PAD_LEFT);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }
}
