<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Filament\Exports\OrderExporter;
use App\Filament\Resources\Orders\Actions\FindOrderItemAnalogAction;
use App\Filament\Resources\Orders\Actions\RequestOrderSupplierReserveAction;
use App\Filament\Resources\Products\ProductResource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPortalLink;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Orders\OrderItemFulfillmentManager;
use App\Services\Orders\OrderPortal;
use App\Services\Orders\OrderSettlementManager;
use App\Services\Orders\OrderWorkflow;
use App\Support\AdminPhoneLinks;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Enums\IconSize;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        $paymentNames = [
            'cash' => 'Наличными',
            'card' => 'Картой',
            'bank_transfer' => 'Безналичный расчет',
            'installment' => 'Рассрочка',
            'installment_6' => 'Рассрочка',
            'credit' => 'Кредит',
            'credit_3_years' => 'Кредит',
        ] + collect(config('shop.payment_methods', []))
            ->mapWithKeys(fn ($m, $k) => [$k => $m['name'] ?? $k])
            ->toArray();

        $deliveryNames = [
            'pickup' => 'Самовывоз',
            'courier' => 'Курьер по Минску',
            'courier_minsk' => 'Курьер по Минску',
            'transport' => 'ТК по Беларуси',
            'transport_company' => 'ТК по Беларуси',
            'kit' => 'ТК КИТ / международная доставка',
        ] + collect(config('shop.delivery_methods', []))
            ->mapWithKeys(fn ($m, $k) => [$k => $m['name'] ?? $k])
            ->toArray();

        $paymentStatuses = [
            'pending' => 'Не оплачено / при получении',
            'paid' => 'Оплачен',
            'failed' => 'Ошибка оплаты',
            'refunded' => 'Возврат',
        ];

        $statusLabelOverrides = [
            'new' => 'Получен',
            'checking' => 'Проверка наличия',
            'confirmed' => 'Наличие подтверждено',
            'processing' => 'В исполнении',
            'shipped' => 'Передан в доставку',
            'delivered' => 'Доставлен',
            'cancelled' => 'Отменен',
        ];

        $statusNames = collect(Order::STATUSES)
            ->mapWithKeys(fn ($label, $status) => [$status => $statusLabelOverrides[$status] ?? $label])
            ->toArray();

        return $table
            ->extraAttributes(['class' => 'orders-workspace'])
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->withSum('items', 'quantity')
                ->with([
                    'items:id,order_id,product_id,integration_product_id,warehouse_stock_id,product_name,product_sku,price,quantity,total,supply_status,supply_route_label,supply_supplier_id,supply_integration_source_id,supply_channel,supply_supplier_name,supply_supplier_contact,supply_source_label,supply_warehouse_name,supply_delivery_days,supply_purchase_price,supply_price_tax_mode,supply_vat_rate,supply_stock_quantity,supply_is_available,supply_candidate_count,supply_captured_at,fulfillment_route,fulfillment_supplier_id,fulfillment_supplier_name,fulfillment_supplier_contact,fulfillment_purchase_price,fulfillment_availability_status,fulfillment_confirmed_at',
                    'items.fulfillmentSupplier:id,crm_counterparty_id,name,contact,marketplace_commission_rate',
                    'items.fulfillmentSupplier.crmCounterparty.contactPoints',
                    'items.supplySupplier:id,crm_counterparty_id,name,contact,marketplace_commission_rate',
                    'items.supplySupplier.crmCounterparty.contactPoints',
                    'items.integrationProduct.source.supplier.crmCounterparty.contactPoints',
                    'items.product.category:id,name,slug',
                    'items.product.integrationProducts.source.supplier.crmCounterparty.contactPoints',
                    'items.product.supplierProducts.supplier.crmCounterparty.contactPoints',
                    'manager:id,name',
                    'dealByInbox:id,order_id,remote_order_id,remote_status,remote_status_name,remote_created_at',
                    'archivedBy:id,name',
                    'placedEconomicSnapshot',
                    'latestSettlement',
                    'supplierOrderRequests:id,order_id,supplier_id,supplier_name,status,sent_at,acknowledged_at',
                    'portalLinks' => fn ($query) => $query
                        ->whereNull('revoked_at')
                        ->where('expires_at', '>', now())
                        ->latest('id'),
                    'integrationIssues' => fn ($query) => $query
                        ->open()
                        ->orders()
                        ->with('source')
                        ->latest('last_detected_at'),
                    'integrationDeliveries',
                ]))
            ->columns([
                TextColumn::make('number')
                    ->label('№ заказа')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Order $record): string => collect([
                        $record->dealByInbox?->sourceLabel(),
                        $record->created_at?->format('d.m.Y H:i') ?? 'Дата не указана',
                    ])->filter()->implode(' · '))
                    ->width('132px')
                    ->grow(false)
                    ->weight('bold'),

                TextColumn::make('created_at')
                    ->label('Дата')
                    ->dateTime('d.m.Y H:i')
                    ->width('132px')
                    ->grow(false)
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('customer_name')
                    ->label('Клиент')
                    ->searchable()
                    ->description(fn (Order $record): string => collect([
                        $record->customer_phone,
                        $record->delivery_city,
                    ])->filter()->implode(' · ') ?: 'Контакты не указаны')
                    ->width('180px')
                    ->grow(false)
                    ->wrap(),

                TextColumn::make('customer_phone')
                    ->label('Телефон')
                    ->searchable()
                    ->formatStateUsing(fn (?string $state): ?HtmlString => AdminPhoneLinks::render($state))
                    ->html()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('customer_email')
                    ->label('Email')
                    ->searchable()
                    ->copyable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('delivery_city')
                    ->label('Город')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('delivery_address')
                    ->label('Адрес')
                    ->searchable()
                    ->limit(32)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('delivery_type')
                    ->label('Доставка')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (?string $state) => $state ? ($deliveryNames[$state] ?? $state) : '—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('payment_type')
                    ->label('Оплата')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (?string $state) => $state ? ($paymentNames[$state] ?? $state) : '—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('payment_status')
                    ->label('Статус оплаты')
                    ->badge()
                    ->color(fn (Order $record): string => $record->paymentStatusColor())
                    ->icon(fn (?string $state, Order $record) => match (true) {
                        $state === 'paid' => 'heroicon-o-check-circle',
                        $state === 'pending' && $record->isPaymentOnReceipt() => 'heroicon-o-hand-raised',
                        $state === 'pending' => 'heroicon-o-clock',
                        $state === 'failed' => 'heroicon-o-x-circle',
                        $state === 'refunded' => 'heroicon-o-arrow-uturn-left',
                        default => null,
                    })
                    ->width('170px')
                    ->grow(false)
                    ->formatStateUsing(fn (?string $state, Order $record): string => $record->paymentStatusLabel())
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('economics')
                    ->label('Экономика')
                    ->state(fn (Order $record): string => 'Заказ '.number_format((float) $record->total, 2, '.', ' ').' BYN')
                    ->description(function (Order $record): string {
                        if ($settlement = $record->latestSettlement) {
                            $current = app(OrderSettlementManager::class)->isCurrent($settlement, $record);

                            return 'Взаиморасчёт №'.(int) $settlement->version
                                .' · прибыль '.number_format((float) $settlement->net_profit, 2, '.', ' ').' BYN'
                                .' · '.($current ? 'актуален' : 'маршрут изменён');
                        }

                        $snapshot = $record->placedEconomicSnapshot;
                        if ($snapshot) {
                            $goods = 'Снимок: товары '.number_format((float) $snapshot->goods_sale_total, 2, '.', ' ').' BYN';

                            if ($snapshot->purchase_total === null) {
                                $known = $snapshot->priced_items_count > 0
                                    ? 'известный вход '.number_format((float) $snapshot->known_purchase_total, 2, '.', ' ').' BYN'
                                    : 'входная стоимость не определена';

                                return $goods.' · '.$known.' · без цены: '.$snapshot->missing_purchase_price_count;
                            }

                            return $goods
                                .' · вход '.number_format((float) $snapshot->purchase_total, 2, '.', ' ').' BYN'
                                .' · валовая маржа '.number_format((float) $snapshot->goods_margin_total, 2, '.', ' ').' BYN'
                                .($snapshot->goods_margin_percent !== null
                                    ? ' / '.number_format((float) $snapshot->goods_margin_percent, 1, '.', ' ').'%'
                                    : '');
                        }

                        $summary = $record->managementSummary();
                        $goods = 'Товары '.number_format($summary['sale_total'], 2, '.', ' ').' BYN';

                        if ($summary['missing_price_count'] > 0) {
                            $known = $summary['priced_items_count'] > 0
                                ? 'Известный вход ≈ '.number_format($summary['purchase_total'], 2, '.', ' ').' BYN'
                                : 'Входная стоимость не рассчитана';

                            return $goods.' · '.$known.' · Без цены: '.$summary['missing_price_count'];
                        }

                        $margin = number_format($summary['margin_total'], 2, '.', ' ').' BYN';
                        $percent = $summary['margin_percent'] !== null
                            ? ' / '.number_format($summary['margin_percent'], 1, '.', ' ').'%'
                            : '';

                        return $goods.' · Вход ≈ '.number_format($summary['purchase_total'], 2, '.', ' ').' BYN · Маржа ≈ '.$margin.$percent;
                    })
                    ->color(fn (Order $record): string => match (true) {
                        $record->latestSettlement !== null
                            && ! app(OrderSettlementManager::class)->isCurrent($record->latestSettlement, $record) => 'warning',
                        $record->latestSettlement !== null
                            && (float) $record->latestSettlement->net_profit < 0 => 'danger',
                        $record->latestSettlement !== null => 'success',
                        $record->placedEconomicSnapshot?->purchase_total === null
                            && $record->placedEconomicSnapshot !== null => 'warning',
                        $record->placedEconomicSnapshot?->goods_margin_total < 0 => 'danger',
                        $record->placedEconomicSnapshot?->goods_margin_percent !== null
                            && (float) $record->placedEconomicSnapshot->goods_margin_percent < (float) config('shop.order_management.minimum_margin_percent', 10) => 'warning',
                        $record->managementSummary()['negative_margin_count'] > 0 => 'danger',
                        $record->managementSummary()['missing_price_count'] > 0,
                        $record->managementSummary()['low_margin_count'] > 0 => 'warning',
                        default => 'success',
                    })
                    ->icon('heroicon-o-calculator')
                    ->tooltip(fn (Order $record): string => $record->placedEconomicSnapshot
                        ? 'Неизменяемый снимок на момент оформления. Неизвестные расходы не считаются нулевыми.'
                        : 'Заказ без снимка: показана текущая оценка. Неизвестная цена не считается нулевой.')
                    ->width('190px')
                    ->grow(false)
                    ->wrap()
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('total', $direction)),

                TextColumn::make('items_sum_quantity')
                    ->label('Товаров')
                    ->state(fn ($record) => (int) ($record->items_sum_quantity ?? 0))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->color(fn (?string $state, Order $record) => $record->isArchivedAsIrrelevant() ? 'gray' : match ($state) {
                        'new' => 'info',
                        'checking' => 'warning',
                        'confirmed' => 'success',
                        'processing' => 'warning',
                        'waiting_payment' => 'warning',
                        'paid' => 'success',
                        'shipped' => 'primary',
                        'delivered' => 'success',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->icon(fn (?string $state, Order $record) => $record->isArchivedAsIrrelevant() ? 'heroicon-o-archive-box' : match ($state) {
                        'new' => 'heroicon-o-sparkles',
                        'checking' => 'heroicon-o-magnifying-glass',
                        'confirmed' => 'heroicon-o-check',
                        'processing' => 'heroicon-o-arrow-path',
                        'waiting_payment' => 'heroicon-o-clock',
                        'paid' => 'heroicon-o-banknotes',
                        'shipped' => 'heroicon-o-truck',
                        'delivered' => 'heroicon-o-check-circle',
                        'completed' => 'heroicon-o-star',
                        'cancelled' => 'heroicon-o-x-circle',
                        default => null,
                    })
                    ->formatStateUsing(fn (?string $state, Order $record) => $record->isArchivedAsIrrelevant()
                        ? 'Неактуален · архив'
                        : ($state ? ($statusNames[$state] ?? $statusLabelOverrides[$state] ?? $state) : '—'))
                    ->description(function (Order $record): HtmlString|string|null {
                        if ($record->isArchivedAsIrrelevant()) {
                            return collect([
                                $record->archived_at?->format('d.m.Y H:i'),
                                $record->archivedBy?->name,
                            ])->filter()->implode(' · ');
                        }

                        if ($record->payment_status === 'paid') {
                            return new HtmlString(
                                '<span style="display:inline-flex;align-items:center;gap:4px;margin-top:4px;padding:3px 8px;border-radius:9999px;background:#dcfce7;color:#166534;font-size:12px;font-weight:700">'
                                .'✓ Оплачен</span>'
                            );
                        }

                        return $record->paymentStatusLabel();
                    })
                    ->tooltip(fn (Order $record): ?string => $record->isArchivedAsIrrelevant()
                        ? $record->archive_reason
                        : null)
                    ->width('150px')
                    ->grow(false),

                IconColumn::make('onec_sync_state')
                    ->label('1С')
                    ->state(fn (Order $record): string => $record->onecSyncState())
                    ->color(fn (string $state): string => match ($state) {
                        'conflict' => 'danger',
                        'unknown', 'no_response', 'delayed' => 'warning',
                        'confirmed' => 'success',
                        'sent' => 'info',
                        default => 'warning',
                    })
                    ->icon(fn (string $state): string => match ($state) {
                        'conflict' => 'heroicon-o-shield-exclamation',
                        'unknown', 'no_response', 'delayed' => 'heroicon-o-exclamation-triangle',
                        'confirmed' => 'heroicon-o-check-circle',
                        'sent' => 'heroicon-o-arrow-up-tray',
                        default => 'heroicon-o-clock',
                    })
                    ->tooltip(function (Order $record): string {
                        $state = $record->onecSyncState();
                        $label = Order::ONEC_SYNC_STATES[$state] ?? $state;

                        return collect([$label, $record->onecSyncDescription()])
                            ->filter()
                            ->implode(' · ');
                    })
                    ->extraCellAttributes(fn (Order $record): array => [
                        'aria-label' => collect([$record->onecSyncLabel(), $record->onecSyncDescription()])
                            ->filter()
                            ->implode(' · '),
                    ])
                    ->size(IconSize::Large)
                    ->alignCenter()
                    ->width('56px')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('management_attention')
                    ->label('Контроль')
                    ->state(fn (Order $record): string => self::compactAttentionLabel($record))
                    ->description(fn (Order $record): ?string => config('onec.order_queue_enabled', false)
                        ? '1С · '.$record->onecSyncLabel()
                        : null)
                    ->tooltip(fn (Order $record): string => self::attentionTooltip($record))
                    ->badge()
                    ->color(fn (Order $record): string => match ($record->managementSummary()['severity']) {
                        'critical' => 'danger',
                        'warning' => 'warning',
                        default => 'success',
                    })
                    ->icon(fn (Order $record): string => match ($record->managementSummary()['severity']) {
                        'critical' => 'heroicon-o-exclamation-triangle',
                        'warning' => 'heroicon-o-eye',
                        default => 'heroicon-o-check-circle',
                    })
                    ->width('140px')
                    ->grow(false)
                    ->wrap(),

                TextColumn::make('supply_route')
                    ->label('Поставка')
                    ->state(function (Order $record): string {
                        $summary = $record->supplySummary();

                        return $summary['supplier_names']->isNotEmpty()
                            ? $summary['supplier_names']->implode(', ')
                            : 'Поставщик не определён';
                    })
                    ->description(function (Order $record): HtmlString|string {
                        $summary = $record->supplySummary();
                        $parts = [];

                        if ($summary['supplier_names']->count() > 1) {
                            $parts[] = 'Смешанная поставка';
                        }
                        if ($summary['unresolved_count'] > 0) {
                            $parts[] = 'Без маршрута: '.$summary['unresolved_count'];
                        }

                        $parts[] = 'Резерв: '.self::supplierReserveStatus($record);
                        $status = implode(' · ', $parts);
                        $contacts = self::orderSupplierContacts($record);

                        if ($contacts && $status) {
                            return new HtmlString($contacts.'<br><span class="text-gray-500">'.e($status).'</span>');
                        }

                        return $contacts ? new HtmlString($contacts) : $status;
                    })
                    ->color(fn (Order $record): string => match (true) {
                        $record->supplierOrderRequests->contains('status', 'rejected') => 'danger',
                        $record->supplySummary()['unresolved_count'] > 0 => 'danger',
                        default => 'success',
                    })
                    ->width('170px')
                    ->grow(false)
                    ->wrap(),

                TextColumn::make('supplier_reserve_status')
                    ->label('Резерв')
                    ->state(fn (Order $record): string => self::supplierReserveStatus($record))
                    ->description(fn (Order $record): ?string => self::supplierReserveDescription($record))
                    ->badge()
                    ->color(fn (Order $record): string => self::supplierReserveColor($record))
                    ->icon(fn (Order $record): string => self::supplierReserveIcon($record))
                    ->width('150px')
                    ->grow(false)
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('responsible')
                    ->label('Менеджер')
                    ->icon('heroicon-o-user-circle')
                    ->placeholder('—')
                    ->getStateUsing(fn (Order $record): ?string => self::compactResponsible($record))
                    ->tooltip(fn (Order $record): ?string => self::responsibleName($record))
                    ->width('110px')
                    ->grow(false)
                    ->wrap()
                    ->searchable(query: fn (Builder $query, string $search) => $query
                        ->where('assigned_to', 'like', "%{$search}%")
                        ->orWhereHas('manager', fn ($q) => $q->where('name', 'like', "%{$search}%"))),

                TextColumn::make('updated_at')
                    ->label('Обновлен')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('items_list')
                    ->label('Товары')
                    ->state(fn (Order $record): string => self::compactProductList($record))
                    ->html()
                    ->tooltip(fn (Order $record): string => $record->items
                        ->map(fn (OrderItem $item): string => collect([
                            $item->product_name,
                            $item->product_sku ? '['.$item->product_sku.']' : null,
                        ])->filter()->implode(' '))
                        ->filter()
                        ->implode("\n"))
                    ->width('220px')
                    ->grow(false)
                    ->wrap()
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->orWhereHas('items', function (Builder $itemsQuery) use ($search): void {
                            $itemsQuery
                                ->where('product_sku', 'like', "%{$search}%")
                                ->orWhere('product_name', 'like', "%{$search}%");
                        });
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('operational_problem')
                    ->label('Проблема / следующий шаг')
                    ->options(fn (): array => config('onec.order_queue_enabled', false)
                        ? Order::OPERATIONAL_PROBLEMS
                        : array_diff_key(Order::OPERATIONAL_PROBLEMS, ['sync_problem' => true]))
                    ->query(function (Builder $query, array $data): Builder {
                        $problem = $data['value'] ?? null;

                        return filled($problem)
                            ? $query->operationallyActive()->withOperationalProblem($problem)
                            : $query;
                    }),

                SelectFilter::make('status')
                    ->label('Статус заказа')
                    ->options($statusNames),

                SelectFilter::make('payment_type')
                    ->label('Способ оплаты')
                    ->options($paymentNames),

                SelectFilter::make('payment_status')
                    ->label('Статус оплаты')
                    ->options($paymentStatuses),

                SelectFilter::make('delivery_type')
                    ->label('Доставка')
                    ->options($deliveryNames),

                SelectFilter::make('onec_sync')
                    ->label('Состояние 1С')
                    ->options([
                        'waiting' => 'Не передан в 1С',
                        'sent' => 'Передан, ответа нет',
                        'confirmed' => 'Ответ 1С получен',
                        'problem' => 'Есть открытая проблема',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'waiting' => $query->whereNull('onec_exported_at'),
                            'sent' => $query->whereNotNull('onec_exported_at')->whereNull('onec_status_received_at'),
                            'confirmed' => $query->whereNotNull('onec_status_received_at'),
                            'problem' => $query->whereHas(
                                'integrationIssues',
                                fn (Builder $query): Builder => $query->open()->orders(),
                            ),
                            default => $query,
                        };
                    }),

                TernaryFilter::make('assigned_to')
                    ->label('Ответственный')
                    ->placeholder('Все заказы')
                    ->trueLabel('Есть ответственный')
                    ->falseLabel('Без ответственного')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('assigned_to'),
                        false: fn (Builder $query) => $query->whereNull('assigned_to'),
                        blank: fn (Builder $query) => $query,
                    ),

                SelectFilter::make('manager_id')
                    ->label('Менеджер')
                    ->options(fn () => User::whereIn('role', ['admin', 'manager', 'sales_manager'])
                        ->pluck('name', 'id')
                        ->toArray())
                    ->placeholder('Все менеджеры'),

                Filter::make('my_orders')
                    ->label('Мои заказы')
                    ->query(fn (Builder $query) => $query->where('manager_id', auth()->id()))
                    ->toggle(),

                Filter::make('created_at')
                    ->label('Дата создания')
                    ->schema([
                        DatePicker::make('created_from')
                            ->label('С даты'),
                        DatePicker::make('created_until')
                            ->label('По дату'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['created_from'] ?? null, fn (Builder $query, $date) => $query->whereDate('created_at', '>=', $date))
                        ->when($data['created_until'] ?? null, fn (Builder $query, $date) => $query->whereDate('created_at', '<=', $date))),
            ])
            ->recordActions([
                Action::make('advanceWorkflow')
                    ->label(fn (Order $record): string => self::compactWorkflowLabel($record))
                    ->icon(fn (Order $record): string => app(OrderWorkflow::class)->step($record)['icon'] ?? 'heroicon-o-arrow-right')
                    ->color(fn (Order $record): string => app(OrderWorkflow::class)->step($record)['color'] ?? 'gray')
                    ->button()
                    ->visible(fn (Order $record): bool => app(OrderWorkflow::class)->step($record) !== null)
                    ->tooltip(fn (Order $record): ?string => app(OrderWorkflow::class)->blocker($record)
                        ? 'Откроется короткое подтверждение наличия и способа исполнения.'
                        : (app(OrderWorkflow::class)->step($record)['label'] ?? null))
                    ->requiresConfirmation()
                    ->modalHeading(fn (Order $record): string => app(OrderWorkflow::class)->blocker($record)
                        ? 'Подтвердить позицию заказа'
                        : (app(OrderWorkflow::class)->step($record)['heading'] ?? 'Перейти к следующему этапу?'))
                    ->modalDescription(fn (Order $record): string => app(OrderWorkflow::class)->blocker($record)
                        ? 'Зафиксируйте фактического поставщика, способ исполнения и наличие. Если это последняя позиция, заказ сразу перейдёт в «Наличие подтверждено».'
                        : (app(OrderWorkflow::class)->step($record)['description'] ?? ''))
                    ->modalSubmitActionLabel(fn (Order $record): string => app(OrderWorkflow::class)->blocker($record)
                        ? 'Сохранить и продолжить'
                        : (app(OrderWorkflow::class)->step($record)['submit'] ?? 'Продолжить'))
                    ->fillForm(function (Order $record): array {
                        $item = $record->items->first(fn (OrderItem $item): bool => blank($item->fulfillment_confirmed_at));
                        if (! $item || $record->status !== 'checking') {
                            return [];
                        }

                        $recommendedRoute = $item->supply_status;

                        return [
                            'order_item_id' => $item->id,
                            'fulfillment_route' => $item->fulfillment_route
                                ?: (array_key_exists($recommendedRoute, OrderItem::FULFILLMENT_ROUTES) ? $recommendedRoute : 'supplier_purchase'),
                            'supplier_id' => $item->fulfillment_supplier_id ?: $item->supply_supplier_id,
                            'availability_status' => $item->fulfillment_availability_status
                                ?: match ($item->supply_is_available) {
                                    true => 'available',
                                    false => 'unavailable',
                                    default => null,
                                },
                            'purchase_price' => $item->fulfillment_purchase_price ?? $item->supply_purchase_price,
                        ];
                    })
                    ->schema(fn (Order $record): array => $record->status === 'checking'
                        && app(OrderWorkflow::class)->blocker($record) !== null
                            ? [
                                Select::make('order_item_id')
                                    ->label('Позиция заказа')
                                    ->options(fn (): array => $record->items
                                        ->filter(fn (OrderItem $item): bool => blank($item->fulfillment_confirmed_at))
                                        ->mapWithKeys(fn (OrderItem $item): array => [
                                            $item->id => $item->product_name.' · '.($item->product_sku ?: 'без артикула'),
                                        ])->all())
                                    ->required(),
                                Select::make('fulfillment_route')
                                    ->label('Как исполняем')
                                    ->options(OrderItem::FULFILLMENT_ROUTES)
                                    ->required(),
                                Select::make('supplier_id')
                                    ->label('Фактический поставщик')
                                    ->options(fn (): array => Supplier::query()
                                        ->where('is_active', true)
                                        ->orderBy('name')
                                        ->pluck('name', 'id')
                                        ->all())
                                    ->searchable()
                                    ->required(),
                                Select::make('availability_status')
                                    ->label('Решение по наличию')
                                    ->options(OrderItem::FULFILLMENT_AVAILABILITY_STATUSES)
                                    ->required(),
                                TextInput::make('purchase_price')
                                    ->label('Входная цена за единицу, BYN')
                                    ->numeric()
                                    ->minValue(0),
                                Textarea::make('note')
                                    ->label('Комментарий менеджера')
                                    ->rows(2)
                                    ->maxLength(2000),
                            ]
                            : [])
                    ->action(function (Order $record, array $data): void {
                        try {
                            if ($record->status === 'checking' && app(OrderWorkflow::class)->blocker($record) !== null) {
                                $item = $record->items()->findOrFail($data['order_item_id']);
                                $supplier = Supplier::query()->findOrFail($data['supplier_id']);

                                app(OrderItemFulfillmentManager::class)->confirm(
                                    $item,
                                    $data['fulfillment_route'],
                                    $supplier,
                                    auth()->user(),
                                    $data['note'] ?? null,
                                    filled($data['purchase_price'] ?? null) ? (float) $data['purchase_price'] : null,
                                    $data['availability_status'],
                                );

                                $record->unsetRelation('items');
                                $record->load('items');
                                if (app(OrderWorkflow::class)->blocker($record) !== null) {
                                    $remaining = $record->items->whereNull('fulfillment_confirmed_at')->count();
                                    Notification::make()
                                        ->success()
                                        ->title('Позиция подтверждена')
                                        ->body('Осталось проверить позиций: '.$remaining.'. Нажмите эту же кнопку ещё раз.')
                                        ->send();

                                    return;
                                }
                            }

                            $result = app(OrderWorkflow::class)->advance($record, auth()->user());

                            Notification::make()
                                ->success()
                                ->title($result['title'])
                                ->body($result['body'])
                                ->send();
                        } catch (\Throwable $exception) {
                            Notification::make()
                                ->danger()
                                ->title('Не удалось перейти к следующему этапу')
                                ->body($exception->getMessage())
                                ->send();
                        }
                    }),
                FindOrderItemAnalogAction::make()
                    ->iconButton()
                    ->tooltip('Подобрать аналог'),
                ViewAction::make()
                    ->label('Открыть заказ')
                    ->iconButton()
                    ->tooltip('Открыть заказ'),
                ActionGroup::make([
                    RequestOrderSupplierReserveAction::make(),
                    Action::make('openOrderOnline')
                        ->label('Открыть онлайн-заказ')
                        ->icon('heroicon-o-globe-alt')
                        ->color('primary')
                        ->visible(fn (Order $record): bool => self::activePortalLink($record) !== null)
                        ->url(fn (Order $record): ?string => ($link = self::activePortalLink($record))
                            ? app(OrderPortal::class)->url($link)
                            : null)
                        ->openUrlInNewTab(),
                    Action::make('markIrrelevant')
                        ->label('Неактуальна')
                        ->icon('heroicon-o-archive-box')
                        ->color('gray')
                        ->visible(fn (Order $record): bool => $record->canBeClosedAsIrrelevant())
                        ->form([
                            Textarea::make('reason')
                                ->label('Почему заявка неактуальна')
                                ->placeholder('Например: клиент отказался, не удалось связаться, тестовая заявка')
                                ->rows(3)
                                ->minLength(3)
                                ->maxLength(1000)
                                ->required(),
                        ])
                        ->modalHeading('Отметить заявку неактуальной')
                        ->modalDescription('Заказ не удаляется: он перейдёт в статус «Отменён», а причина и автор сохранятся в истории.')
                        ->action(fn (Order $record, array $data) => $record->markIrrelevant($data['reason'])),
                    EditAction::make('correctOrder')
                        ->label('Корректировать заказ')
                        ->icon('heroicon-o-pencil-square')
                        ->visible(fn (Order $record): bool => in_array($record->status, ['new', 'checking'], true)),
                    EditAction::make()
                        ->label('Редактировать заказ')
                        ->visible(fn (Order $record): bool => ! in_array($record->status, ['new', 'checking'], true)),
                ]),
            ])
            ->checkIfRecordIsSelectableUsing(fn (Order $record): bool => $record->status === 'new'
                && $record->payment_status !== 'paid'
                && $record->archived_at === null)
            ->toolbarActions([
                ExportAction::make()
                    ->label('Экспорт')
                    ->exporter(OrderExporter::class),
                BulkAction::make('markIrrelevant')
                    ->label('В архив выбранные')
                    ->icon('heroicon-o-archive-box-arrow-down')
                    ->color('warning')
                    ->button()
                    ->tooltip('Сначала отметьте строки. Для всей очереди отметьте страницу и нажмите «Выбрать все» над таблицей.')
                    ->form([
                        Textarea::make('reason')
                            ->label('Общая причина')
                            ->helperText('Вы можете обработать строки на странице или весь отфильтрованный список через ссылку «Выбрать все». Новые, оплаченные и уже обработанные заказы защищены от изменения.')
                            ->default('Архивная заявка до запуска нового рабочего процесса')
                            ->rows(3)
                            ->minLength(3)
                            ->maxLength(1000)
                            ->required(),
                    ])
                    ->requiresConfirmation()
                    ->modalHeading('Архивировать выбранные заявки')
                    ->modalDescription('Заявки не удаляются. Они получат статус «Неактуален · архив», а причина, время и автор сохранятся.')
                    ->modalSubmitActionLabel('Архивировать выбранные')
                    ->action(function (Collection $records, array $data): void {
                        $changed = 0;
                        $skipped = 0;

                        $records->each(function (Order $record) use ($data, &$changed, &$skipped): void {
                            // Массовая архивация нужна только для очистки новых заявок.
                            // Подтверждённые заказы закрываются только штучно с отдельной причиной.
                            if ($record->status !== 'new' || ! $record->markIrrelevant($data['reason'])) {
                                $skipped++;

                                return;
                            }

                            $changed++;
                        });

                        Notification::make()
                            ->title('Заявки перемещены в архив')
                            ->body("В архиве: {$changed}. Защищено и пропущено: {$skipped}.")
                            ->color($skipped > 0 ? 'warning' : 'success')
                            ->send();
                    })
                    ->deselectRecordsAfterCompletion(),
            ]);
    }

    public static function supplierReserveStatus(Order $order): string
    {
        $requests = $order->relationLoaded('supplierOrderRequests')
            ? $order->supplierOrderRequests
            : $order->supplierOrderRequests()->get();

        if ($requests->isEmpty()) {
            return 'Не запрошен';
        }

        $waiting = $requests->where('status', 'sent')->count();
        $confirmed = $requests->whereIn('status', ['acknowledged', 'fulfilled'])->count();
        $rejected = $requests->where('status', 'rejected')->count();

        return match (true) {
            $rejected > 0 => $rejected > 1 ? 'Отказов: '.$rejected : 'Есть отказ',
            $confirmed > 0 && $waiting > 0 => 'Ответ: '.$confirmed.' · ждём: '.$waiting,
            $confirmed > 0 => $confirmed > 1 ? 'Подтверждено: '.$confirmed : 'Резерв подтверждён',
            $waiting > 0 => $waiting > 1 ? 'Ждём: '.$waiting : 'Ждём ответа',
            $requests->contains('status', 'draft') => 'Черновик',
            default => 'Закрыто',
        };
    }

    private static function activePortalLink(Order $order): ?OrderPortalLink
    {
        if ($order->status === 'cancelled'
            || $order->status === 'completed'
            || ($order->status === 'delivered' && $order->payment_status === 'paid')) {
            return null;
        }

        if ($order->relationLoaded('portalLinks')) {
            return $order->portalLinks->first();
        }

        return $order->portalLinks()
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();
    }

    public static function supplierReserveDescription(Order $order): ?string
    {
        $requests = $order->relationLoaded('supplierOrderRequests')
            ? $order->supplierOrderRequests
            : $order->supplierOrderRequests()->get();

        if ($requests->isEmpty()) {
            return null;
        }

        return $requests->pluck('supplier_name')->filter()->unique()->implode(', ');
    }

    public static function supplierReserveColor(Order $order): string
    {
        $statuses = ($order->relationLoaded('supplierOrderRequests')
            ? $order->supplierOrderRequests
            : $order->supplierOrderRequests()->get())->pluck('status');

        return match (true) {
            $statuses->contains('rejected') => 'danger',
            $statuses->contains(fn (string $status): bool => in_array($status, ['acknowledged', 'fulfilled'], true)) => 'success',
            $statuses->contains('sent') => 'warning',
            $statuses->contains('draft') => 'info',
            default => 'gray',
        };
    }

    public static function supplierReserveIcon(Order $order): string
    {
        return match (self::supplierReserveColor($order)) {
            'danger' => 'heroicon-o-x-circle',
            'success' => 'heroicon-o-check-circle',
            'warning' => 'heroicon-o-clock',
            'info' => 'heroicon-o-pencil-square',
            default => 'heroicon-o-minus-circle',
        };
    }

    private static function compactProductList(Order $order): string
    {
        $items = $order->items->values();
        $visibleItems = $items->take(2)
            ->map(fn (OrderItem $item): string => self::productLinks($item))
            ->filter();
        $remainingItems = $items->slice(2)
            ->map(fn (OrderItem $item): string => self::productLinks($item))
            ->filter();

        $html = $visibleItems->join('<div style="height:8px"></div>');

        if ($remainingItems->isNotEmpty()) {
            $count = $remainingItems->count();
            $word = match (true) {
                ($count % 100) >= 11 && ($count % 100) <= 14 => 'товаров',
                ($count % 10) === 1 => 'товар',
                ($count % 10) >= 2 && ($count % 10) <= 4 => 'товара',
                default => 'товаров',
            };
            $html .= '<details style="margin-top:8px">'
                .'<summary style="cursor:pointer;color:#f59e0b;font-weight:600">Показать ещё '.$count.' '.$word.'</summary>'
                .'<div style="height:8px"></div>'
                .$remainingItems->join('<div style="height:8px"></div>')
                .'</details>';
        }

        return '<div style="width:220px;max-width:220px;overflow-wrap:anywhere">'.$html.'</div>';
    }

    private static function productLinks(OrderItem $item): string
    {
        $product = $item->product;
        $name = e($item->product_name.($item->product_sku ? ' ['.$item->product_sku.']' : ''));

        if (! $product) {
            return $name.'<div style="font-size:11px;color:#f59e0b">Карточка товара не связана</div>';
        }

        $adminUrl = e(ProductResource::getUrl('edit', ['record' => $product], panel: 'admin'));
        $publicUrl = self::publicProductUrl($product);
        $availability = e($product->availabilityLabel());
        $stock = self::currentStock($product);
        $stockLabel = $stock === null
            ? 'остаток не указан'
            : 'остаток '.e(self::formatStock($stock)).' шт.';
        $color = match ($product->effectiveAvailabilityStatus()) {
            Product::AVAILABILITY_IN_STOCK => '#22c55e',
            Product::AVAILABILITY_OUT_OF_STOCK => '#ef4444',
            default => '#f59e0b',
        };
        $siteLink = $publicUrl
            ? ' · <a href="'.e($publicUrl).'" target="_blank" rel="noopener" style="color:#60a5fa">На сайте ↗</a>'
            : '';

        return '<div>'
            .'<a href="'.$adminUrl.'" style="font-weight:700;color:#93c5fd">'.$name.'</a>'
            .'<div style="font-size:11px;margin-top:3px;color:'.$color.'">'.$availability.' · '.$stockLabel.$siteLink.'</div>'
            .'</div>';
    }

    private static function publicProductUrl(Product $product): ?string
    {
        if (! $product->category?->slug || ! $product->slug) {
            return null;
        }

        $url = url('/'.$product->category->slug.'/'.$product->slug);

        return ! $product->is_active && ! $product->is_archived
            ? $url.'?admin-preview=1'
            : $url;
    }

    private static function currentStock(Product $product): ?float
    {
        if ($product->stock_qty !== null) {
            return (float) $product->stock_qty;
        }

        $integrationItems = $product->integrationProducts
            ->filter(fn ($item): bool => $item->match_status === 'matched' && $item->source?->is_active === true)
            ->filter(fn ($item): bool => $item->stock_quantity !== null);

        if ($integrationItems->isNotEmpty()) {
            return (float) $integrationItems->sum(fn ($item): float => (float) $item->stock_quantity);
        }

        $supplierItems = $product->supplierProducts
            ->filter(fn ($item): bool => $item->stock_quantity !== null);

        return $supplierItems->isEmpty()
            ? null
            : (float) $supplierItems->sum(fn ($item): float => (float) $item->stock_quantity);
    }

    private static function formatStock(float $stock): string
    {
        return abs($stock - round($stock)) < 0.0005
            ? number_format($stock, 0, ',', ' ')
            : rtrim(rtrim(number_format($stock, 3, ',', ' '), '0'), ',');
    }

    private static function compactAttentionLabel(Order $order): string
    {
        $summary = $order->managementSummary();

        return $summary['problem_count'] > 0
            ? 'Проблем: '.$summary['problem_count']
            : 'Готов';
    }

    private static function attentionTooltip(Order $order): string
    {
        $problems = $order->managementSummary()['problems']
            ->pluck('label')
            ->filter()
            ->implode("\n");

        return collect([
            $problems ?: 'Критичных сигналов нет',
            '1С: '.$order->onecSyncLabel(),
            $order->onecSyncDescription(),
        ])->filter()->implode("\n");
    }

    private static function responsibleName(Order $order): ?string
    {
        $manager = trim((string) $order->manager?->name);
        $legacy = trim((string) $order->assigned_to);

        if ($manager === '') {
            return $legacy !== '' ? $legacy : null;
        }

        $normalizedLegacy = ltrim($legacy, '@');
        if ($normalizedLegacy === '' || mb_strtolower($normalizedLegacy) === mb_strtolower($manager)) {
            return $manager;
        }

        return $manager.' · '.$legacy;
    }

    private static function compactResponsible(Order $order): ?string
    {
        $name = self::responsibleName($order);
        if (! $name) {
            return null;
        }

        $primary = trim(explode('·', $name, 2)[0]);
        $parts = preg_split('/\s+/u', $primary, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (count($parts) < 2) {
            return mb_strimwidth($primary, 0, 16, '…');
        }

        return $parts[0].' '.mb_substr($parts[1], 0, 1).'.';
    }

    private static function compactWorkflowLabel(Order $order): string
    {
        return match ($order->status) {
            'new' => 'Проверить',
            'checking' => 'Подтвердить',
            'confirmed' => 'В работу',
            'processing' => 'В доставку',
            'shipped' => 'Доставлен',
            default => app(OrderWorkflow::class)->step($order)['label'] ?? 'Далее',
        };
    }

    private static function orderSupplierContacts(Order $order): ?string
    {
        $contacts = $order->items->map(function (OrderItem $item): ?array {
            $context = $item->supplyContext();
            $supplier = self::supplierForItem($item, $context);
            $legacyContact = (string) ($item->fulfillment_supplier_contact
                ?: $context['supplier_contact']
                ?: $item->supply_supplier_contact);

            $phone = $supplier?->contactPhoneNumbers()[0] ?? self::phoneFromText($legacyContact);
            $email = $supplier?->contactEmailAddresses()[0] ?? self::emailFromText($legacyContact);
            if (! $phone && ! $email) {
                return null;
            }

            return [
                'key' => $supplier?->id ?: ($context['supplier_name'] ?? $legacyContact),
                'name' => $supplier?->name ?? $context['supplier_name'] ?? $item->fulfillment_supplier_name,
                'phone' => $phone,
                'email' => $email,
            ];
        })->filter()->unique('key')->values();

        $showNames = $contacts->count() > 1;
        $rows = $contacts->map(function (array $contact) use ($showNames): string {
            $phone = $contact['phone'];
            $email = $contact['email'];
            $links = collect([
                $phone ? AdminPhoneLinks::render($phone)?->toHtml() : null,
                $email ? '<a class="text-primary-600 hover:underline" href="mailto:'.e($email).'">✉ '.e($email).'</a>' : null,
            ])->filter()->implode(' · ');

            return ($showNames && filled($contact['name'])
                ? '<strong>'.e($contact['name']).':</strong> '
                : '').$links;
        })->implode('<br>');

        return $rows !== '' ? $rows : null;
    }

    /** @param array<string, mixed> $context */
    private static function supplierForItem(OrderItem $item, array $context): ?Supplier
    {
        $supplierId = $item->fulfillment_supplier_id ?: ($context['supplier_id'] ?? $item->supply_supplier_id);
        $suppliers = collect([
            $item->fulfillmentSupplier,
            $item->supplySupplier,
            $item->integrationProduct?->source?->supplier,
        ]);

        foreach ($item->product?->integrationProducts ?? [] as $offer) {
            $suppliers->push($offer->source?->supplier);
        }
        foreach ($item->product?->supplierProducts ?? [] as $offer) {
            $suppliers->push($offer->supplier);
        }

        $suppliers = $suppliers->filter()->unique('id')->values();

        return $supplierId
            ? $suppliers->first(fn (Supplier $supplier): bool => $supplier->id === (int) $supplierId)
            : $suppliers->first(fn (Supplier $supplier): bool => $supplier->name === ($context['supplier_name'] ?? null));
    }

    private static function phoneFromText(string $value): ?string
    {
        preg_match('/(?<!\w)(?:\+?\d[\d\s().\-]{5,}\d)(?!\w)/u', $value, $match);

        return isset($match[0]) ? trim($match[0]) : null;
    }

    private static function emailFromText(string $value): ?string
    {
        preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/iu', $value, $match);

        return isset($match[0]) ? trim($match[0]) : null;
    }
}
