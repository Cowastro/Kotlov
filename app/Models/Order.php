<?php

namespace App\Models;

use App\Services\Orders\OrderItemSupplyContextResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class Order extends Model
{
    private ?string $pendingStatusHistoryComment = null;

    /** @var array<string, mixed>|null */
    private ?array $supplySummaryCache = null;

    /** @var array<string, mixed>|null */
    private ?array $managementSummaryCache = null;

    public const ONEC_SYNC_STATES = [
        'conflict' => 'Конфликт статусов',
        'unknown' => 'Неизвестный статус',
        'no_response' => 'Нет ответа 1С',
        'delayed' => 'Передача задержана',
        'confirmed' => 'Ответ получен',
        'sent' => 'Передан',
        'waiting' => 'Ожидает передачи',
    ];

    public const OPERATIONAL_PROBLEMS = [
        'needs_attention' => 'Нужна реакция',
        'missing_supplier' => 'Поставщик не определён',
        'missing_price' => 'Нет входной цены',
        'no_stock' => 'Нет подтверждённого остатка',
        'negative_margin' => 'Отрицательная маржа',
        'low_margin' => 'Низкая маржа',
        'mixed_suppliers' => 'Несколько поставщиков',
        'unassigned' => 'Без ответственного',
        'sync_problem' => 'Проблема обмена',
        'supplier_request_rejected' => 'Поставщик отклонил заявку',
        'payment_failed' => 'Ошибка оплаты',
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
                    'comment' => $order->pendingStatusHistoryComment,
                ]);
            }
        });
    }

    public static function staleLeadDays(): int
    {
        return max(1, (int) config('shop.order_management.stale_lead_days', 30));
    }

    public function scopeStaleUnprocessed(Builder $query, ?int $days = null): Builder
    {
        $days ??= self::staleLeadDays();

        return $query
            ->where('status', 'new')
            ->where(fn (Builder $payment): Builder => $payment
                ->whereNull('payment_status')
                ->orWhere('payment_status', '!=', 'paid'))
            ->where('created_at', '<=', now()->subDays($days));
    }

    public function scopeOperationallyActive(Builder $query): Builder
    {
        return $query->whereNotIn('status', ['delivered', 'completed', 'cancelled']);
    }

    public function isStaleUnprocessed(?int $days = null): bool
    {
        $days ??= self::staleLeadDays();

        return $this->status === 'new'
            && $this->payment_status !== 'paid'
            && $this->created_at?->lte(now()->subDays($days)) === true;
    }

    public function transitionTo(string $status, ?string $historyComment = null, array $attributes = []): bool
    {
        $this->pendingStatusHistoryComment = $historyComment;

        try {
            return $this->update([...$attributes, 'status' => $status]);
        } finally {
            $this->pendingStatusHistoryComment = null;
        }
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

    public function fulfillmentHistory(): HasManyThrough
    {
        return $this->hasManyThrough(
            OrderItemFulfillmentHistory::class,
            OrderItem::class,
            'order_id',
            'order_item_id',
        )->latest('order_item_fulfillment_histories.created_at');
    }

    public function integrationIssues(): HasMany
    {
        return $this->hasMany(IntegrationIssue::class);
    }

    public function integrationDeliveries(): HasMany
    {
        return $this->hasMany(OrderIntegrationDelivery::class);
    }

    public function supplierOrderRequests(): HasMany
    {
        return $this->hasMany(SupplierOrderRequest::class);
    }

    public function economicSnapshots(): HasMany
    {
        return $this->hasMany(OrderEconomicSnapshot::class);
    }

    public function placedEconomicSnapshot(): HasOne
    {
        return $this->hasOne(OrderEconomicSnapshot::class)
            ->where('kind', OrderEconomicSnapshot::KIND_PLACED);
    }

    public function scopeWithOperationalProblem(Builder $query, ?string $problem): Builder
    {
        $eligibleIntegrationOffer = fn (Builder $offers): Builder => $offers
            ->where('match_status', 'matched')
            ->where('price', '>', 0)
            ->whereHas('source', fn (Builder $source): Builder => $source->where('is_active', true));
        $eligibleLegacyOffer = fn (Builder $offers): Builder => $offers
            ->where('price_byn', '>', 0)
            ->whereHas('supplier', fn (Builder $supplier): Builder => $supplier->where('is_active', true));

        return match ($problem) {
            'missing_supplier' => $query->whereHas('items', fn (Builder $items): Builder => $items
                ->where(fn (Builder $problemItems): Builder => $problemItems
                    ->where(fn (Builder $snapshot): Builder => $snapshot
                        ->whereNotNull('supply_captured_at')
                        ->where('supply_status', 'unresolved'))
                    ->orWhere(fn (Builder $legacy): Builder => $legacy
                        ->whereNull('supply_captured_at')
                        ->whereNull('integration_product_id')
                        ->whereDoesntHave('product.integrationProducts', $eligibleIntegrationOffer)
                        ->whereDoesntHave('product.supplierProducts', $eligibleLegacyOffer)))),
            'missing_price' => $query->whereHas('items', fn (Builder $items): Builder => $items
                ->where(fn (Builder $problemItems): Builder => $problemItems
                    ->where(fn (Builder $snapshot): Builder => $snapshot
                        ->whereNotNull('supply_captured_at')
                        ->whereNull('supply_purchase_price'))
                    ->orWhere(fn (Builder $legacy): Builder => $legacy
                        ->whereNull('supply_captured_at')
                        ->where(fn (Builder $source): Builder => $source
                            ->whereHas('integrationProduct', fn (Builder $offer): Builder => $offer->where('price', '<=', 0))
                            ->orWhere(fn (Builder $recommendation): Builder => $recommendation
                                ->whereNull('integration_product_id')
                                ->whereDoesntHave('product.integrationProducts', $eligibleIntegrationOffer)
                                ->whereDoesntHave('product.supplierProducts', $eligibleLegacyOffer)))))),
            'no_stock' => $query->whereHas('items', fn (Builder $items): Builder => $items
                ->whereNotNull('supply_captured_at')
                ->where(fn (Builder $stock): Builder => $stock
                    ->whereNull('supply_is_available')
                    ->orWhere('supply_is_available', false))),
            'negative_margin' => $query->whereHas('items', fn (Builder $items): Builder => $items
                ->whereNotNull('supply_captured_at')
                ->whereNotNull('supply_purchase_price')
                ->whereColumn('supply_purchase_price', '>', 'price')),
            'low_margin' => $query->whereHas('items', function (Builder $items): Builder {
                $factor = 1 - min(100, max(0, (float) config('shop.order_management.minimum_margin_percent', 10))) / 100;

                return $items
                    ->whereNotNull('supply_captured_at')
                    ->whereNotNull('supply_purchase_price')
                    ->whereColumn('supply_purchase_price', '<=', 'price')
                    ->whereRaw('supply_purchase_price > price * ?', [$factor]);
            }),
            'mixed_suppliers' => $query->whereRaw(
                '(select count(distinct snapshot_items.supply_supplier_id) from order_items as snapshot_items where snapshot_items.order_id = orders.id and snapshot_items.supply_captured_at is not null and snapshot_items.supply_supplier_id is not null) > 1',
            ),
            'unassigned' => $query
                ->operationallyActive()
                ->whereNull('manager_id')
                ->where(fn (Builder $owner): Builder => $owner
                    ->whereNull('assigned_to')
                    ->orWhere('assigned_to', '')),
            'sync_problem' => $query->whereHas(
                'integrationIssues',
                fn (Builder $issues): Builder => $issues->open()->orders(),
            ),
            'supplier_request_rejected' => $query->whereHas(
                'supplierOrderRequests',
                fn (Builder $requests): Builder => $requests->where('status', 'rejected'),
            ),
            'payment_failed' => $query->where('payment_status', 'failed'),
            'needs_attention' => $query
                ->operationallyActive()
                ->where(function (Builder $attention): void {
                    foreach (array_keys(array_diff_key(self::OPERATIONAL_PROBLEMS, ['needs_attention' => true])) as $problem) {
                        $attention->orWhere(fn (Builder $part): Builder => $part->withOperationalProblem($problem));
                    }
                }),
            default => $query,
        };
    }

    /** @return array<string, mixed> */
    public function supplySummary(): array
    {
        return $this->supplySummaryCache ??= app(OrderItemSupplyContextResolver::class)->summarize($this);
    }

    /**
     * Read-only operational summary for the manager's order queue.
     * Financial values are current estimates until route snapshots are introduced.
     *
     * @return array<string, mixed>
     */
    public function managementSummary(): array
    {
        if ($this->managementSummaryCache !== null) {
            return $this->managementSummaryCache;
        }

        $supply = $this->supplySummary();
        $problems = collect();

        if ($supply['unresolved_count'] > 0) {
            $problems->push(['severity' => 'critical', 'label' => 'Не определён поставщик: '.$supply['unresolved_count']]);
        }
        if ($supply['missing_price_count'] > 0) {
            $problems->push(['severity' => 'critical', 'label' => 'Нет входной цены: '.$supply['missing_price_count']]);
        }
        if ($supply['negative_margin_count'] > 0) {
            $problems->push(['severity' => 'critical', 'label' => 'Убыточных позиций: '.$supply['negative_margin_count']]);
        }
        if ($supply['low_margin_count'] > 0) {
            $problems->push([
                'severity' => 'warning',
                'label' => 'Маржа ниже '.number_format($supply['minimum_margin_percent'], 0).'%: '.$supply['low_margin_count'],
            ]);
        }
        if ($supply['unavailable_count'] > 0) {
            $problems->push(['severity' => 'warning', 'label' => 'Нет подтверждённого остатка: '.$supply['unavailable_count']]);
        }

        $isActive = ! in_array($this->status, ['delivered', 'completed', 'cancelled'], true);
        if ($isActive && ! $this->manager_id && blank($this->assigned_to)) {
            $problems->push(['severity' => 'warning', 'label' => 'Не назначен менеджер']);
        }

        $syncState = $this->onecSyncState();
        if (in_array($syncState, ['conflict', 'unknown', 'no_response', 'delayed'], true)) {
            $problems->push([
                'severity' => $syncState === 'conflict' ? 'critical' : 'warning',
                'label' => $this->onecSyncLabel(),
            ]);
        }
        if ($this->payment_status === 'failed') {
            $problems->push(['severity' => 'critical', 'label' => 'Ошибка оплаты']);
        }
        $rejectedSupplierRequests = $this->relationLoaded('supplierOrderRequests')
            ? $this->supplierOrderRequests->where('status', 'rejected')->count()
            : $this->supplierOrderRequests()->where('status', 'rejected')->count();
        if ($rejectedSupplierRequests > 0) {
            $problems->push([
                'severity' => 'critical',
                'label' => 'Поставщик отклонил заявок: '.$rejectedSupplierRequests,
            ]);
        }

        $severity = match (true) {
            $problems->contains('severity', 'critical') => 'critical',
            $problems->isNotEmpty() => 'warning',
            default => 'ok',
        };

        return $this->managementSummaryCache = $supply + [
            'problems' => $problems,
            'problem_count' => $problems->count(),
            'severity' => $severity,
            'attention_label' => match ($severity) {
                'critical' => 'Нужна реакция',
                'warning' => 'Проверить',
                default => 'Готов к работе',
            },
        ];
    }

    public function supplierRequestStatusSummary(): ?string
    {
        $requests = $this->relationLoaded('supplierOrderRequests')
            ? $this->supplierOrderRequests
            : $this->supplierOrderRequests()->get();

        if ($requests->isEmpty()) {
            return null;
        }

        return $requests
            ->groupBy('status')
            ->map(fn (Collection $group, string $status): string => (SupplierOrderRequest::STATUSES[$status] ?? $status).': '.$group->count())
            ->values()
            ->implode(' · ');
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
                $this->currentIntegrationDeliveries()->contains(fn (OrderIntegrationDelivery $delivery): bool => blank($delivery->exported_at)) => 'waiting',
                $this->currentIntegrationDeliveries()->contains(fn (OrderIntegrationDelivery $delivery): bool => blank($delivery->status_received_at)) => 'sent',
                $this->currentIntegrationDeliveries()->isNotEmpty() => 'confirmed',
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
            return collect([$issue->source?->partnerName(), $issue->message ?: $issue->title])
                ->filter()
                ->implode(' · ');
        }

        $deliveries = $this->currentIntegrationDeliveries();
        if ($deliveries->isNotEmpty()) {
            $received = $deliveries->whereNotNull('status_received_at')->count();

            return $received.' из '.$deliveries->count().' источников вернули статус';
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
            ->filter(fn (IntegrationIssue $issue): bool => $this->integrationIssueIsCurrent($issue))
            ->sortBy(fn (IntegrationIssue $issue): int => $priorities[$issue->type] ?? 99)
            ->first();
    }

    private function integrationIssueIsCurrent(IntegrationIssue $issue): bool
    {
        if (in_array($issue->type, ['order_status_conflict', 'order_status_unknown'], true)) {
            return true;
        }

        if (! $issue->integration_source_id) {
            return match ($issue->type) {
                'order_no_1c_response' => filled($this->onec_exported_at) && blank($this->onec_status_received_at),
                'order_not_exported' => blank($this->onec_exported_at),
                default => false,
            };
        }

        $delivery = $this->currentIntegrationDeliveries()
            ->firstWhere('integration_source_id', $issue->integration_source_id);

        return match ($issue->type) {
            'order_no_1c_response' => $delivery
                ? filled($delivery->exported_at) && blank($delivery->status_received_at)
                : $issue->source?->code === 'onec'
                    && filled($this->onec_exported_at)
                    && blank($this->onec_status_received_at),
            'order_not_exported' => ! $delivery || blank($delivery->exported_at),
            default => false,
        };
    }

    /** @return Collection<int, OrderIntegrationDelivery> */
    private function currentIntegrationDeliveries(): Collection
    {
        if (! $this->relationLoaded('integrationDeliveries')) {
            $this->setRelation('integrationDeliveries', $this->integrationDeliveries()->get());
        }

        return $this->integrationDeliveries;
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
