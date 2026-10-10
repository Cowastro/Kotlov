<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class Order extends Model
{
    public const ONEC_SYNC_STATES = [
        'conflict' => 'Конфликт статусов',
        'unknown' => 'Неизвестный статус',
        'no_response' => 'Нет ответа 1С',
        'delayed' => 'Передача задержана',
        'confirmed' => 'Ответ получен',
        'sent' => 'Передан',
        'waiting' => 'Ожидает передачи',
    ];

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
        'assigned_to', 'telegram_message_id', 'manager_id', 'onec_exported_at',
        'onec_external_id', 'onec_status', 'onec_status_received_at',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'total' => 'decimal:2',
        'delivery_price' => 'decimal:2',
        'discount' => 'decimal:2',
        'onec_exported_at' => 'datetime',
        'onec_status_received_at' => 'datetime',
    ];

    // Статусы для отображения
    public const STATUSES = [
        'new' => 'Новый',
        'confirmed' => 'Подтверждён',
        'processing' => 'В обработке',
        'shipped' => 'Отправлен',
        'delivered' => 'Доставлен',
        'cancelled' => 'Отменён',
    ];

    public const PAYMENT_TYPES = [
        'cash' => 'Наличными',
        'card' => 'Банковской платежной карточкой',
        'bank_transfer' => 'Безналичный расчёт',
        'currency_transfer' => 'Оплата для РФ и Казахстана',
        'installment_6' => 'Онлайн-рассрочка на 6 месяцев',
        'credit_3_years' => 'Кредит до 3 лет',
        'online_card' => 'Онлайн-оплата картой',
        'halva' => 'Карта «Халва»',
        'belgazprombank' => 'Белгазпромбанк',
        'belarusbank_magnit' => 'Беларусбанк «Магнит Green»',
        'cherepaha' => 'Карта «Черепаха» от ВТБ',
        'webpay' => 'WEBPAY',
        'invoice' => 'По счёту (для организаций)',
    ];

    public const DELIVERY_TYPES = [
        'pickup' => 'Самовывоз',
        'courier' => 'Доставка курьером по г. Минску',
        'transport' => 'Транспортная компания по Беларуси',
        'kit' => 'ТК КИТ — Россия, Казахстан, Армения, Киргизия',
    ];

    protected static function booted(): void
    {
        static::updating(function (Order $order) {
            $userId = Auth::id();

            // Автоназначение ответственного при изменении статуса из CRM
            // Не перезаписываем если уже назначен
            if ($order->isDirty('status') && $userId && ! $order->manager_id) {
                $order->manager_id = $userId;
                $order->assigned_to = '@'.(Auth::user()->telegram_username ?? Auth::user()->name);
            }

            // История изменений статуса
            if ($order->isDirty('status')) {
                OrderStatusHistory::create([
                    'order_id' => $order->id,
                    'user_id' => $userId,
                    'status_from' => $order->getOriginal('status'),
                    'status_to' => $order->status,
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

    public function integrationIssues(): HasMany
    {
        return $this->hasMany(IntegrationIssue::class);
    }

    public function integrationDeliveries(): HasMany
    {
        return $this->hasMany(OrderIntegrationDelivery::class);
    }

    public function onecSyncState(): string
    {
        $issueType = $this->activeOnecSyncIssue()?->type;

        return match ($issueType) {
            'order_status_conflict' => 'conflict',
            'order_status_unknown' => 'unknown',
            'order_no_1c_response' => 'no_response',
            'order_not_exported' => 'delayed',
            default => match (true) {
                filled($this->onec_status_received_at) => 'confirmed',
                filled($this->onec_exported_at) => 'sent',
                default => 'waiting',
            },
        };
    }

    public function onecSyncLabel(): string
    {
        return self::ONEC_SYNC_STATES[$this->onecSyncState()] ?? 'Состояние неизвестно';
    }

    public function onecSyncDescription(): ?string
    {
        $issue = $this->activeOnecSyncIssue();
        if ($issue) {
            return $issue->message ?: $issue->title;
        }

        if ($this->onec_status_received_at) {
            return collect([
                $this->onec_status,
                $this->onec_status_received_at->timezone('Europe/Minsk')->format('d.m.Y H:i'),
            ])->filter()->implode(' · ');
        }

        return $this->onec_exported_at?->timezone('Europe/Minsk')->format('d.m.Y H:i');
    }

    public function activeOnecSyncIssue(): ?IntegrationIssue
    {
        $issues = $this->relationLoaded('integrationIssues')
            ? $this->integrationIssues
            : $this->integrationIssues()->open()->orders()->get();

        $priorities = [
            'order_status_conflict' => 1,
            'order_status_unknown' => 2,
            'order_no_1c_response' => 3,
            'order_not_exported' => 4,
        ];

        return $issues
            ->where('status', 'open')
            ->filter(fn (IntegrationIssue $issue): bool => match ($issue->type) {
                'order_status_conflict', 'order_status_unknown' => true,
                'order_no_1c_response' => filled($this->onec_exported_at)
                    && blank($this->onec_status_received_at),
                'order_not_exported' => blank($this->onec_exported_at),
                default => false,
            })
            ->sortBy(fn (IntegrationIssue $issue): int => $priorities[$issue->type] ?? 99)
            ->first();
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    // Генерация номера заказа
    public static function generateNumber(): string
    {
        $year = date('Y');
        $prefix = 'ORD-'.$year.'-';

        $last = self::where('number', 'like', $prefix.'%')
            ->lockForUpdate()
            ->max(DB::raw('CAST(SUBSTRING(number, '.(strlen($prefix) + 1).') AS UNSIGNED)'));

        $next = (int) $last + 1;

        return $prefix.str_pad($next, 4, '0', STR_PAD_LEFT);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }
}
