<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class B2bOrder extends Model
{
    public const STATUSES = [
        'new' => 'Новый заказ',
        'assigned' => 'Назначен менеджеру',
        'checking' => 'Проверка наличия',
        'awaiting_supplier' => 'Ожидаем поставщика',
        'proposal_ready' => 'Условия подготовлены',
        'awaiting_client' => 'Ожидаем подтверждения клиента',
        'client_confirmed' => 'Подтверждён клиентом',
        'invoice_issued' => 'Счёт выставлен',
        'awaiting_payment' => 'Ожидаем оплату',
        'credit_approved' => 'Отгрузка по лимиту',
        'paid' => 'Оплачен',
        'assembling' => 'Комплектуется',
        'ready_to_ship' => 'Готов к отгрузке',
        'shipped' => 'Отгружен',
        'completed' => 'Завершён',
        'cancelled' => 'Отменён',
    ];

    public const PAYMENT_STATUSES = [
        'pending' => 'Не оплачен',
        'invoiced' => 'Счёт выставлен',
        'credit' => 'Кредитный лимит',
        'paid' => 'Оплачен',
        'overdue' => 'Просрочен',
        'refunded' => 'Возврат',
    ];

    protected $fillable = [
        'b2b_client_id', 'created_by', 'assigned_manager_id', 'accepted_by',
        'commercial_document_id', 'legacy_order_id', 'number', 'status',
        'payment_status', 'payment_type', 'delivery_type', 'delivery_region',
        'delivery_city', 'delivery_address', 'delivery_price', 'contact_name',
        'contact_phone', 'contact_email', 'company_name', 'company_unp',
        'company_address', 'company_email', 'subtotal', 'discount', 'total',
        'commercial_terms_snapshot', 'client_comment', 'manager_note',
        'accepted_at', 'availability_confirmed_at', 'client_confirmed_at',
        'invoice_notified_at', 'completed_at', 'cancelled_at',
    ];

    protected $casts = [
        'delivery_price' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',
        'commercial_terms_snapshot' => 'array',
        'accepted_at' => 'datetime',
        'availability_confirmed_at' => 'datetime',
        'client_confirmed_at' => 'datetime',
        'invoice_notified_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function client(): BelongsTo { return $this->belongsTo(B2bClient::class, 'b2b_client_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function manager(): BelongsTo { return $this->belongsTo(User::class, 'assigned_manager_id'); }
    public function acceptedBy(): BelongsTo { return $this->belongsTo(User::class, 'accepted_by'); }
    public function commercialDocument(): BelongsTo { return $this->belongsTo(CommercialDocument::class); }
    public function legacyOrder(): BelongsTo { return $this->belongsTo(Order::class, 'legacy_order_id'); }
    public function items(): HasMany { return $this->hasMany(B2bOrderItem::class); }
    public function events(): HasMany { return $this->hasMany(B2bOrderEvent::class)->latest(); }
    public function comments(): HasMany { return $this->hasMany(B2bOrderComment::class)->latest(); }
    public function reservations(): HasMany { return $this->hasMany(B2bOrderReservation::class); }

    /** Every line is confirmed or removed with a reason, and something is left to sell. */
    public function stockIsSettled(): bool
    {
        return $this->items->isNotEmpty()
            && $this->items->every(fn (B2bOrderItem $item): bool => $item->isConfirmed() || $item->isExcluded())
            && $this->items->contains(fn (B2bOrderItem $item): bool => $item->isConfirmed());
    }

    public static function generateNumber(): string
    {
        return 'B2B-'.now()->format('Ymd-His').'-'.strtoupper(str()->random(4));
    }

    public function statusLabel(): string { return self::STATUSES[$this->status] ?? $this->status; }
    public function paymentStatusLabel(): string { return self::PAYMENT_STATUSES[$this->payment_status] ?? $this->payment_status; }
}
