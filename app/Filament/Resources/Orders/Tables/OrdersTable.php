<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Filament\Exports\OrderExporter;
use App\Filament\Resources\Orders\Actions\FindOrderItemAnalogAction;
use App\Filament\Resources\Orders\Actions\RequestOrderSupplierReserveAction;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Orders\Pages\ListOrders;
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
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\IconSize;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
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
            'courier' => 'Наш транспорт в пределах МКАД-2',
            'courier_minsk' => 'Наш транспорт в пределах МКАД-2',
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
            ->searchable()
            ->searchPlaceholder('№ заказа, клиент, телефон или артикул')
            ->searchUsing(fn (Builder $query, string $search): Builder => self::searchOrders($query, $search))
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->withSum('items', 'quantity')
                ->with([
                    'partnerBranch:id,name,city_id',
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
                    ->sortable()
                    ->url(fn (Order $record): string => OrderResource::getUrl('view', ['record' => $record]))
                    ->description(function (Order $record) use ($statusNames): HtmlString {
                        $date = $record->created_at?->format('d.m.Y H:i') ?? 'Дата не указана';
                        $status = $record->isArchivedAsIrrelevant() ? 'Архив' : ($statusNames[$record->status] ?? $record->status);

                        return new HtmlString(e($date).'<span class="order-summary-status">'.e($status).'</span>'
                            .'<span class="order-summary-total">'.e(self::formatMoney((float) $record->total)).'</span>');
                    })
                    ->width('114px')
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
                    ->description(function (Order $record): HtmlString {
                        $phone = preg_replace('/[^+0-9]/', '', (string) $record->customer_phone);
                        $contact = $phone
                            ? '<a href="tel:'.e($phone).'">'.e($record->customer_phone).'</a>'
                            : e($record->customer_phone);
                        $location = collect([$record->delivery_city, $record->partnerBranch?->name])->filter()->unique()->implode(' · ');
                        $locationHint = collect([$location, Order::PARTNER_STATUSES[$record->partner_status] ?? null])->filter()->implode(' · ');

                        return new HtmlString(($contact ?: 'Телефон не указан')
                            .($location !== '' ? '<span class="order-cell-line" title="'.e($locationHint).'">'.e($location).'</span>' : ''));
                    })
                    ->disabledClick()
                    ->limit(35)
                    ->width('135px')
                    ->grow(false)
                    ->wrap(),

                TextColumn::make('customer_phone')
                    ->label('Телефон')
                    ->formatStateUsing(fn (?string $state): ?HtmlString => AdminPhoneLinks::render($state))
                    ->html()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('customer_email')
                    ->label('Email')
                    ->copyable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('delivery_city')
                    ->label('Город')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('delivery_address')
                    ->label('Адрес')
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
                    ->state(fn (Order $record): string => 'Заказ '.self::formatMoney((float) $record->total))
                    ->description(fn (Order $record): HtmlString => new HtmlString(
                        '<details class="order-cell-details"><summary>'.e(self::economicSummary($record))
                        .'</summary><div class="order-cell-details__body">'.e(self::economicDetails($record)).'</div></details>'
                    ))
                    ->disabledClick()
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
                    ->width('135px')
                    ->visibleFrom('xl')
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

                        $payment = $record->payment_status === 'paid'
                            ? '<span class="order-payment-confirmed">✓ Оплачен</span>'
                            : e($record->paymentStatusLabel());
                        $problem = $record->managementSummary()['problem_count'] > 0
                            ? '<span class="order-control-indicator" title="'.e(self::attentionTooltip($record)).'">'
                                .e(self::compactAttentionLabel($record)).'</span>'
                            : '';

                        return new HtmlString($payment.$problem);
                    })
                    ->tooltip(fn (Order $record): ?string => $record->isArchivedAsIrrelevant()
                        ? $record->archive_reason
                        : null)
                    ->width('130px')
                    ->visibleFrom('md')
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
                    ->width('94px')
                    ->visibleFrom('lg')
                    ->grow(false)
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('supply_route')
                    ->label('Поставка')
                    ->state(function (Order $record): string {
                        $names = $record->supplySummary()['supplier_names'];

                        return $names->isNotEmpty()
                            ? $names->first().($names->count() > 1 ? ' +'.($names->count() - 1) : '')
                            : 'Поставщик не определён';
                    })
                    ->description(function (Order $record): HtmlString {
                        $summary = $record->supplySummary();
                        $status = 'Резерв: '.self::supplierReserveStatus($record);
                        $details = collect([
                            e($summary['supplier_names']->implode(', ')),
                            self::orderSupplierContacts($record),
                            $summary['unresolved_count'] > 0 ? 'Без маршрута: '.$summary['unresolved_count'] : null,
                        ])->filter()->implode('<br>');

                        return new HtmlString($details !== ''
                            ? '<details class="order-cell-details"><summary title="'.e($status).'">'.e($status)
                                .'</summary><div class="order-cell-details__body">'.$details.'</div></details>'
                            : e($status));
                    })
                    ->disabledClick()
                    ->limit(28)
                    ->tooltip(fn (Order $record): string => $record->supplySummary()['supplier_names']->implode(', '))
                    ->color(fn (Order $record): string => match (true) {
                        $record->supplierOrderRequests->contains('status', 'rejected') => 'danger',
                        $record->supplySummary()['unresolved_count'] > 0 => 'danger',
                        default => 'success',
                    })
                    ->width('133px')
                    ->visibleFrom('lg')
                    ->grow(false)
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),

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
                    ->width('90px')
                    ->visibleFrom('xl')
                    ->grow(false)
                    ->wrap()
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query
                        ->orderByRaw("CASE WHEN manager_id IS NULL AND NULLIF(TRIM(assigned_to), '') IS NULL THEN 1 ELSE 0 END")
                        ->orderByRaw("COALESCE((SELECT name FROM users WHERE users.id = orders.manager_id), NULLIF(TRIM(assigned_to), '')) "
                            .($direction === 'desc' ? 'DESC' : 'ASC'))),

                TextColumn::make('updated_at')
                    ->label('Обновлен')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('items_list')
                    ->label('Товары и поставка')
                    ->state(fn (Order $record): HtmlString => self::compactProductList($record))
                    ->html()
                    ->disabledClick()
                    ->visibleFrom('md')
                    ->wrap(),
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

                SelectFilter::make('partner_branch_id')
                    ->label('Филиал партнёра')
                    ->relationship('partnerBranch', 'name')
                    ->searchable(),

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
                        true: fn (Builder $query) => $query->where(fn (Builder $owner): Builder => $owner
                            ->whereNotNull('manager_id')->orWhereRaw("NULLIF(TRIM(assigned_to), '') IS NOT NULL")),
                        false: fn (Builder $query) => $query->withoutResponsible(),
                        blank: fn (Builder $query) => $query,
                    ),

                SelectFilter::make('manager_id')
                    ->label('Ответственный')
                    ->options(fn (): array => ['mine' => 'Мои заказы', 'unassigned' => 'Без ответственного']
                        + User::whereIn('role', ['admin', 'manager', 'sales_manager'])->orderBy('name')->pluck('name', 'id')->all())
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'mine' => $query->where('manager_id', auth()->id()),
                        'unassigned' => $query->withoutResponsible(),
                        null, '' => $query,
                        default => $query->where('manager_id', (int) $data['value']),
                    })
                    ->searchable()
                    ->placeholder('Все ответственные'),

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
            ], layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(['md' => 2, 'xl' => 4])
            ->filtersFormSchema(fn (array $filters): array => [
                $filters['status'],
                $filters['manager_id'],
                $filters['operational_problem'],
                $filters['payment_status'],
                Section::make('Дополнительные фильтры')->compact()->collapsible()->collapsed()->columnSpanFull()
                    ->columns(['md' => 2, 'xl' => 4])
                    ->schema(array_values(array_diff_key($filters, array_flip(['status', 'manager_id', 'operational_problem', 'payment_status'])))),
            ])
            ->deferFilters(false)
            ->emptyStateHeading('Заказы не найдены')
            ->emptyStateDescription('Проверьте выбранную очередь и фильтры.')
            ->emptyStateActions([
                Action::make('searchAllOrders')->label('Искать во всех заказах')->icon('heroicon-o-magnifying-glass')
                    ->visible(fn (ListOrders $livewire): bool => $livewire->activeTab !== 'all' && filled($livewire->getTableSearch()))
                    ->action(fn (ListOrders $livewire) => $livewire->selectOrderTab('all')),
            ])
            ->recordActions([
                Action::make('advanceWorkflow')
                    ->label(fn (Order $record): string => self::compactWorkflowLabel($record))
                    ->icon(fn (Order $record): string => app(OrderWorkflow::class)->step($record)['icon'] ?? 'heroicon-o-arrow-right')
                    ->color(fn (Order $record): string => app(OrderWorkflow::class)->step($record)['color'] ?? 'gray')
                    ->iconButton()
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
                Action::make('markOrderReason')
                    ->label('Отметить причину')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->iconButton()
                    ->visible(fn (Order $record): bool => $record->canBeClosedAsIrrelevant())
                    ->tooltip('Отметить причину отказа и закрыть заказ')
                    ->modalHeading('Отметить заказ с причиной')
                    ->modalDescription('Заказ не удаляется: он получит статус «Отменён» и попадёт в архив. Причина, автор и время сохранятся в истории.')
                    ->schema([
                        Select::make('reason_type')
                            ->label('Причина')
                            ->options(Order::CLOSURE_REASONS)
                            ->native(false)
                            ->required(),
                        Textarea::make('reason')
                            ->label('Комментарий')
                            ->placeholder('Необязательно. Добавьте детали, если они нужны менеджеру.')
                            ->helperText('Например: цена в карточке устарела или банк не одобрил заявку клиента.')
                            ->rows(3)
                            ->maxLength(1000),
                    ])
                    ->modalSubmitActionLabel('Отметить и закрыть заказ')
                    ->action(function (Order $record, array $data): void {
                        if (! $record->markIrrelevant($data['reason'] ?? '', $data['reason_type'])) {
                            Notification::make()
                                ->danger()
                                ->title('Заказ нельзя закрыть')
                                ->body('Проверьте статус заказа и состояние оплаты.')
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->success()
                            ->title('Причина сохранена')
                            ->body('Заказ закрыт и перемещён в архив.')
                            ->send();
                    }),
                ViewAction::make()
                    ->label('Открыть заказ')
                    ->iconButton()
                    ->tooltip('Открыть заказ'),
                ActionGroup::make([
                    FindOrderItemAnalogAction::make(),
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
                    \App\Filament\Resources\Orders\Actions\AssignOrderManagerAction::make(),
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

    private static function searchOrders(Builder $query, string $search): Builder
    {
        $phone = preg_match('/^[+0-9().\s-]+$/u', $search) ? preg_replace('/\D/', '', $search) : '';
        $terms = strlen($phone) >= 5 ? [$search] : (preg_split('/\s+/u', trim($search), -1, PREG_SPLIT_NO_EMPTY) ?: []);

        foreach ($terms as $term) {
            $pattern = '%'.$term.'%';
            $query->where(fn (Builder $orders): Builder => $orders
                ->whereAny(['number', 'customer_name', 'customer_phone', 'customer_email', 'company_name', 'company_unp',
                    'delivery_city', 'delivery_address', 'assigned_to'], 'like', $pattern)
                ->orWhereHas('items', fn (Builder $items): Builder => $items->whereAny(['product_sku', 'product_name'], 'like', $pattern))
                ->orWhereHas('manager', fn (Builder $manager): Builder => $manager->where('name', 'like', $pattern))
                ->orWhereHas('partnerBranch', fn (Builder $branch): Builder => $branch->where('name', 'like', $pattern))
                ->when(strlen($phone) >= 5, fn (Builder $orders): Builder => $orders->orWhereRaw(
                    "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(customer_phone, ' ', ''), '(', ''), ')', ''), '-', ''), '.', '') LIKE ?",
                    ['%'.$phone.'%'],
                )));
        }

        return $query;
    }

    private static function formatMoney(float $amount): string
    {
        return number_format($amount, 2, '.', ' ').' BYN';
    }

    private static function economicSummary(Order $record): string
    {
        if ($settlement = $record->latestSettlement) {
            return app(OrderSettlementManager::class)->isCurrent($settlement, $record)
                ? 'Прибыль '.self::formatMoney((float) $settlement->net_profit)
                : 'Пересчитать прибыль';
        }

        $snapshot = $record->placedEconomicSnapshot;
        $summary = $snapshot ? null : $record->managementSummary();
        $missing = $snapshot?->missing_purchase_price_count ?? $summary['missing_price_count'] ?? 0;
        if ($missing > 0) {
            return 'Без закупочной: '.$missing;
        }

        $margin = $snapshot?->goods_margin_percent ?? $summary['margin_percent'] ?? null;

        return $margin === null ? 'Маржа не рассчитана' : 'Маржа '.number_format((float) $margin, 1, '.', ' ').'%';
    }

    private static function economicDetails(Order $record): string
    {
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

    private static function compactProductList(Order $order): HtmlString
    {
        $items = $order->items;
        if ($items->isEmpty()) {
            return new HtmlString('Нет позиций');
        }

        $name = e($items->first()->product_name);
        $more = $items->count() - 1;
        $quantity = self::formatStock((float) $items->sum('quantity'));
        $suppliers = $order->supplySummary()['supplier_names'];
        $supply = $suppliers->isEmpty() ? 'Поставщик не определён' : $suppliers->first();
        if ($suppliers->count() > 1) {
            $supply .= ' +'.($suppliers->count() - 1);
        }
        $reserve = 'Резерв: '.self::supplierReserveStatus($order);
        $supplyDetails = collect([
            e($suppliers->implode(', ')),
            self::orderSupplierContacts($order),
            e($reserve),
        ])->filter()->implode('<br>');

        return new HtmlString('<details class="order-cell-details order-item-preview">'
            .'<summary title="'.$name.'"><span class="order-cell-line">'.$name.'</span>'
            .'<span class="order-item-preview__count">'.($more > 0 ? '+'.$more.' · ' : '').$quantity.' шт. · '
                .'<span title="'.e($supply.' · '.$reserve).'">'.e($supply).'</span></span></summary>'
            .'<div class="order-cell-details__body">'.$items->map(fn (OrderItem $item): string => self::productLinks($item))->implode('<hr>')
                .'<hr><strong>Поставка</strong><br>'.$supplyDetails.'</div>'
            .'</details>');
    }

    private static function productLinks(OrderItem $item): string
    {
        $product = $item->product;
        $name = e($item->product_name.($item->product_sku ? ' ['.$item->product_sku.']' : '')
            .' × '.self::formatStock((float) $item->quantity));

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
