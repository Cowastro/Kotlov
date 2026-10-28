<?php

namespace App\Models;

use App\Services\Marketplaces\DealByOrderStatusSync;
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
        'not_queued' => 'Не передан в 1С',
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
        'user_id', 'commercial_document_id', 'crm_counterparty_id', 'crm_contact_id', 'number', 'status',
        'customer_name', 'customer_phone', 'customer_email',
        'company_name', 'company_unp', 'company_address', 'company_email',
        'delivery_type', 'delivery_region', 'delivery_city',
        'delivery_address', 'delivery_price', 'planned_delivery_date',
        'payment_type', 'payment_status',
        'availability_confirmed_at', 'availability_confirmed_by', 'customer_notified_at',
        'coupon_code', 'discount', 'promotion_breakdown',
        'subtotal', 'total',
        'comment', 'admin_comment',
        'corrected_at', 'corrected_by', 'procurement_sent_at', 'procurement_sent_by', 'procurement_recipient',
        'assigned_to', 'telegram_message_id', 'manager_id', 'onec_exported_at',
        'onec_external_id', 'onec_order_guid', 'onec_order_number', 'onec_status', 'onec_status_received_at',
        'archived_at', 'archived_by', 'archive_reason',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'total' => 'decimal:2',
        'delivery_price' => 'decimal:2',
        'planned_delivery_date' => 'date',
        'discount' => 'decimal:2',
        'promotion_breakdown' => 'array',
        'onec_exported_at' => 'datetime',
        'onec_status_received_at' => 'datetime',
        'archived_at' => 'datetime',
        'corrected_at' => 'datetime',
        'procurement_sent_at' => 'datetime',
        'availability_confirmed_at' => 'datetime',
        'customer_notified_at' => 'datetime',
    ];

    // Статусы для отображения
    public const STATUSES = [
        'new' => 'Получен',
        'checking' => 'Проверка наличия',
        'confirmed' => 'Наличие подтверждено',
        'processing' => 'В исполнении',
        'shipped' => 'Передан в доставку',
        'delivered' => 'Доставлен',
        'cancelled' => 'Отменён',
    ];

    /** Причины закрытия заказа без удаления истории. */
    public const CLOSURE_REASONS = [
        'incorrect_price' => 'Неверная цена',
        'out_of_stock' => 'Нет в наличии',
        'credit_refused' => 'Отказ в кредите / рассрочке',
        'customer_refusal' => 'Отказ клиента',
        'delivery_refusal' => 'Отказ при доставке',
        'irrelevant' => 'Заявка больше не актуальна',
        'unreachable' => 'Не удалось связаться',
        'duplicate' => 'Дубль заказа',
        'test' => 'Тестовая заявка',
        'other' => 'Другая причина',
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
        'courier' => 'Нашим транспортом в пределах МКАД-2',
        'own_transport' => 'Нашим транспортом в города по графику',
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

    public function scopeOperationallyActive(Builder $query): Builder
    {
        return $query
            ->whereNull('archived_at')
            ->whereNotIn('status', ['delivered', 'completed', 'cancelled']);
    }

    public function accountingRequests(): HasMany
    {
        return $this->hasMany(OrderAccountingRequest::class)->latest('id');
    }

    public function transitionTo(string $status, ?string $historyComment = null, array $attributes = []): bool
    {
        $previousStatus = $this->status;
        $this->pendingStatusHistoryComment = $historyComment;

        try {
            $updated = $this->update([...$attributes, 'status' => $status]);

            if ($updated && $previousStatus !== $status) {
                app(DealByOrderStatusSync::class)->syncSafely($this, $historyComment);
            }

            return $updated;
        } finally {
            $this->pendingStatusHistoryComment = null;
        }
    }

    public function workflowNextStep(): string
    {
        return match ($this->status) {
            'new' => 'Принять заказ и начать проверку наличия.',
            'checking' => 'Проверить наличие и способ исполнения каждой позиции.',
            'confirmed' => 'Передать подтверждённый заказ в исполнение.',
            'processing' => 'Закупить или собрать товар, затем передать в доставку.',
            'shipped' => 'Контролировать доставку и отметить получение клиентом.',
            'delivered' => 'Заказ выполнен. Проверить оплату и взаиморасчёты.',
            'cancelled' => 'Заказ закрыт. Дальнейшая передача не требуется.',
            default => 'Уточнить текущее состояние заказа.',
        };
    }

    public function workflowDestination(): string
    {
        return match ($this->status) {
            'new' => 'На проверку наличия',
            'checking' => 'К подтверждению наличия',
            'confirmed' => 'В исполнение / закупку',
            'processing' => 'В доставку',
            'shipped' => 'На контроль получения',
            'delivered' => 'На финансовое закрытие',
            'cancelled' => 'В архив',
            default => 'Требует разбора',
        };
    }

    public function isRetailCustomer(): bool
    {
        return blank($this->company_unp);
    }

    public function isPaymentOnReceipt(): bool
    {
        return $this->isRetailCustomer()
            && in_array($this->payment_type, ['cash', 'card'], true);
    }

    public function paymentStatusLabel(): string
    {
        if ($this->payment_status === 'pending' && $this->isPaymentOnReceipt()) {
            return 'Оплата при получении';
        }

        return match ($this->payment_status) {
            'paid' => 'Оплачен',
            'pending' => 'Ожидает оплаты',
            'failed' => 'Ошибка оплаты',
            'refunded' => 'Возврат',
            default => $this->payment_status ?: '—',
        };
    }

    public function paymentStatusColor(): string
    {
        return match (true) {
            $this->payment_status === 'paid' => 'success',
            $this->payment_status === 'pending' && $this->isPaymentOnReceipt() => 'info',
            $this->payment_status === 'pending' => 'warning',
            $this->payment_status === 'failed' => 'danger',
            $this->payment_status === 'refunded' => 'info',
            default => 'gray',
        };
    }

    public function canBeClosedAsIrrelevant(): bool
    {
        return in_array($this->status, ['new', 'checking', 'confirmed', 'processing'], true)
            && $this->payment_status !== 'paid'
            && $this->archived_at === null;
    }

    public function canBeClosedAfterDeliveryRefusal(): bool
    {
        return $this->status === 'shipped'
            && $this->payment_status !== 'paid'
            && $this->archived_at === null;
    }

    public function markDeliveryRefused(string $reason): bool
    {
        if (! $this->canBeClosedAfterDeliveryRefusal()) {
            return false;
        }

        $reason = 'Отказ при доставке: '.trim($reason);
        $timezone = config('app.display_timezone', 'Europe/Minsk');
        $note = '['.now()->timezone($timezone)->format('d.m.Y H:i').'] '.$reason;
        $adminComment = filled($this->admin_comment)
            ? rtrim($this->admin_comment)."\n\n".$note
            : $note;

        return $this->transitionTo('cancelled', $reason, [
            'admin_comment' => $adminComment,
            'archived_at' => now(),
            'archived_by' => Auth::id(),
            'archive_reason' => $reason,
        ]);
    }

    public function hasConfirmedFulfillmentForAllItems(): bool
    {
        if ($this->relationLoaded('items')) {
            return $this->items->isNotEmpty()
                && $this->items->every(fn (OrderItem $item): bool => filled($item->fulfillment_confirmed_at));
        }

        return $this->items()->exists()
            && ! $this->items()->whereNull('fulfillment_confirmed_at')->exists();
    }

    public function customerDetailsAreAvailable(): bool
    {
        return $this->availability_confirmed_at !== null
            && in_array($this->status, ['confirmed', 'processing', 'shipped', 'delivered', 'cancelled'], true);
    }

    public function availabilityConfirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'availability_confirmed_by');
    }

    public function markIrrelevant(string $reason, ?string $reasonType = null): bool
    {
        if (! $this->canBeClosedAsIrrelevant()) {
            return false;
        }

        $reason = trim($reason);
        if ($reasonType !== null) {
            $reasonLabel = self::CLOSURE_REASONS[$reasonType] ?? self::CLOSURE_REASONS['other'];
            $reason = $reason !== '' ? $reasonLabel.': '.$reason : $reasonLabel;
        }
        $timezone = config('app.display_timezone', 'Europe/Minsk');
        $note = '['.now()->timezone($timezone)->format('d.m.Y H:i').'] Неактуальная заявка: '.$reason;
        $adminComment = filled($this->admin_comment)
            ? rtrim($this->admin_comment)."\n\n".$note
            : $note;

        return $this->transitionTo('cancelled', $reason, [
            'admin_comment' => $adminComment,
            'archived_at' => now(),
            'archived_by' => Auth::id(),
            'archive_reason' => $reason,
        ]);
    }

    public function scopeArchived(Builder $query): Builder
    {
        return $query->whereNotNull('archived_at');
    }

    public function isArchivedAsIrrelevant(): bool
    {
        return $this->archived_at !== null && filled($this->archive_reason);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function commercialDocument(): BelongsTo
    {
        return $this->belongsTo(CommercialDocument::class);
    }

    public function dealByInbox(): HasOne
    {
        return $this->hasOne(DealByOrderInbox::class);
    }

    public function crmCounterparty(): BelongsTo
    {
        return $this->belongsTo(CrmCounterparty::class);
    }

    public function crmContact(): BelongsTo
    {
        return $this->belongsTo(CrmContact::class);
    }

    /**
     * Ответственный CRM-пользователь (admin / будущий manager).
     * assigned_to хранит Telegram @username как лог — не удалять.
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function correctedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'corrected_by');
    }

    public function procurementSentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'procurement_sent_by');
    }

    public function procurementMailThreads(): HasMany
    {
        return $this->hasMany(CrmMailThread::class);
    }

    public function portalLinks(): HasMany
    {
        return $this->hasMany(OrderPortalLink::class);
    }

    public function portalEvents(): HasMany
    {
        return $this->hasMany(OrderPortalEvent::class)->latest('occurred_at');
    }

    public function webpayPaymentAttempts(): HasMany
    {
        return $this->hasMany(WebpayPaymentAttempt::class)->latest('id');
    }

    public function archivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archived_by');
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

    public function settlements(): HasMany
    {
        return $this->hasMany(OrderSettlement::class);
    }

    public function latestSettlement(): HasOne
    {
        return $this->hasOne(OrderSettlement::class)->ofMany('version', 'max');
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
            'sync_problem' => config('onec.order_queue_enabled', false)
                ? $query->whereHas(
                    'integrationIssues',
                    fn (Builder $issues): Builder => $issues->open()->orders(),
                )
                : $query->whereRaw('1 = 0'),
            'supplier_request_rejected' => $query->whereHas(
                'supplierOrderRequests',
                fn (Builder $requests): Builder => $requests->where('status', 'rejected'),
            ),
            'payment_failed' => $query->where('payment_status', 'failed'),
            'needs_attention' => $query
                ->operationallyActive()
                ->where(function (Builder $attention): void {
                    $ignored = config('onec.order_queue_enabled', false)
                        ? ['needs_attention' => true]
                        : ['needs_attention' => true, 'sync_problem' => true];

                    foreach (array_keys(array_diff_key(self::OPERATIONAL_PROBLEMS, $ignored)) as $problem) {
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

        if (config('onec.order_queue_enabled', false)) {
            $syncState = $this->onecSyncState();
            if (in_array($syncState, ['conflict', 'unknown', 'no_response', 'delayed'], true)) {
                $problems->push([
                    'severity' => $syncState === 'conflict' ? 'critical' : 'warning',
                    'label' => $this->onecSyncLabel(),
                ]);
            }
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
                default => 'not_queued',
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
