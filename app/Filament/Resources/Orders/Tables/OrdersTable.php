<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Filament\Exports\OrderExporter;
use App\Models\Order;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

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
            'pending' => 'Ожидает оплаты',
            'paid' => 'Оплачен',
            'failed' => 'Ошибка оплаты',
            'refunded' => 'Возврат',
        ];

        $statusLabelOverrides = [
            'new' => 'Новый',
            'confirmed' => 'Подтвержден',
            'processing' => 'В обработке',
            'waiting_payment' => 'Ожидает оплаты',
            'paid' => 'Оплачен',
            'shipped' => 'Отправлен',
            'delivered' => 'Доставлен',
            'completed' => 'Выполнен',
            'cancelled' => 'Отменен',
        ];

        $statusNames = collect(Order::STATUSES)
            ->mapWithKeys(fn ($label, $status) => [$status => $statusLabelOverrides[$status] ?? $label])
            ->toArray();

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->withSum('items', 'quantity')
                ->with([
                    'items:id,order_id,product_id,integration_product_id,product_name,product_sku,price,quantity,total,supply_status,supply_route_label,supply_supplier_id,supply_integration_source_id,supply_channel,supply_supplier_name,supply_supplier_contact,supply_source_label,supply_purchase_price,supply_price_tax_mode,supply_vat_rate,supply_stock_quantity,supply_is_available,supply_candidate_count,supply_captured_at',
                    'items.integrationProduct.source.supplier',
                    'items.product.integrationProducts.source.supplier',
                    'items.product.supplierProducts.supplier',
                    'manager:id,name',
                    'placedEconomicSnapshot',
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
                    ->weight('bold'),

                TextColumn::make('created_at')
                    ->label('Дата')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),

                TextColumn::make('customer_name')
                    ->label('Клиент')
                    ->searchable()
                    ->description(fn (Order $record): string => collect([
                        $record->customer_phone,
                        $record->delivery_city,
                    ])->filter()->implode(' · ') ?: 'Контакты не указаны')
                    ->wrap(),

                TextColumn::make('customer_phone')
                    ->label('Телефон')
                    ->searchable()
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
                    ->color(fn (?string $state) => match ($state) {
                        'paid' => 'success',
                        'pending' => 'warning',
                        'failed' => 'danger',
                        'refunded' => 'info',
                        default => 'gray',
                    })
                    ->icon(fn (?string $state) => match ($state) {
                        'paid' => 'heroicon-o-check-circle',
                        'pending' => 'heroicon-o-clock',
                        'failed' => 'heroicon-o-x-circle',
                        'refunded' => 'heroicon-o-arrow-uturn-left',
                        default => null,
                    })
                    ->formatStateUsing(fn (?string $state) => $state ? ($paymentStatuses[$state] ?? $state) : '—'),

                TextColumn::make('economics')
                    ->label('Экономика')
                    ->state(fn (Order $record): string => 'Заказ '.number_format((float) $record->total, 2, '.', ' ').' BYN')
                    ->description(function (Order $record): string {
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
                        : 'Исторический заказ без снимка: показана текущая оценка. Неизвестная цена не считается нулевой.')
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
                    ->color(fn (?string $state) => match ($state) {
                        'new' => 'info',
                        'confirmed' => 'warning',
                        'processing' => 'warning',
                        'waiting_payment' => 'warning',
                        'paid' => 'success',
                        'shipped' => 'primary',
                        'delivered' => 'success',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->icon(fn (?string $state) => match ($state) {
                        'new' => 'heroicon-o-sparkles',
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
                    ->formatStateUsing(fn (?string $state) => $state ? ($statusNames[$state] ?? $statusLabelOverrides[$state] ?? $state) : '—'),

                TextColumn::make('onec_sync_state')
                    ->label('Обмен')
                    ->state(fn (Order $record): string => $record->onecSyncState())
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Order::ONEC_SYNC_STATES[$state] ?? $state)
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
                    ->description(fn (Order $record): ?string => $record->onecSyncDescription())
                    ->wrap(),

                TextColumn::make('management_attention')
                    ->label('Контроль')
                    ->state(function (Order $record): string {
                        $summary = $record->managementSummary();

                        return $summary['problem_count'] > 0
                            ? $summary['attention_label'].' · '.$summary['problem_count']
                            : $summary['attention_label'];
                    })
                    ->description(fn (Order $record): string => $record->managementSummary()['problems']
                        ->pluck('label')
                        ->take(2)
                        ->implode(' · ') ?: 'Критичных сигналов нет')
                    ->tooltip(fn (Order $record): string => $record->managementSummary()['problems']
                        ->pluck('label')
                        ->implode("\n") ?: 'Критичных сигналов нет')
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
                    ->wrap(),

                TextColumn::make('supply_route')
                    ->label('Поставка')
                    ->state(function (Order $record): string {
                        $summary = $record->supplySummary();

                        return $summary['supplier_names']->isNotEmpty()
                            ? $summary['supplier_names']->implode(', ')
                            : 'Поставщик не определён';
                    })
                    ->description(function (Order $record): string {
                        $summary = $record->supplySummary();
                        $parts = [];

                        if ($summary['supplier_names']->count() > 1) {
                            $parts[] = 'Смешанная поставка';
                        }
                        if ($summary['unresolved_count'] > 0) {
                            $parts[] = 'Без маршрута: '.$summary['unresolved_count'];
                        }

                        return $parts !== [] ? implode(' · ', $parts) : 'Маршрут рассчитан по текущим связям';
                    })
                    ->color(fn (Order $record): string => $record->supplySummary()['unresolved_count'] > 0 ? 'danger' : 'success')
                    ->wrap(),

                TextColumn::make('responsible')
                    ->label('Ответственный')
                    ->icon('heroicon-o-user-circle')
                    ->placeholder('—')
                    ->getStateUsing(fn ($record) => $record->manager
                        ? $record->manager->name.($record->assigned_to ? ' ('.$record->assigned_to.')' : '')
                        : ($record->assigned_to ?? null))
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
                    ->state(fn ($record) => $record->items->map(fn ($item) => $item->product_name.
                        ($item->product_sku ? ' ['.$item->product_sku.']' : '')
                    )->filter()->join("\n"))
                    ->wrap()
                    ->lineClamp(2)
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
                    ->options(Order::OPERATIONAL_PROBLEMS)
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->withOperationalProblem($data['value'] ?? null)),

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
                ViewAction::make(),
                ActionGroup::make([
                    Action::make('confirmed')
                        ->label('Подтвердить')
                        ->icon('heroicon-o-check')
                        ->color('warning')
                        ->visible(fn ($record) => $record->status === 'new')
                        ->action(fn ($record) => $record->update(['status' => 'confirmed'])),
                    Action::make('processing')
                        ->label('В обработке')
                        ->icon('heroicon-o-arrow-path')
                        ->color('primary')
                        ->visible(fn ($record) => in_array($record->status, ['new', 'confirmed']))
                        ->action(fn ($record) => $record->update(['status' => 'processing'])),
                    Action::make('shipped')
                        ->label('Отправлен')
                        ->icon('heroicon-o-truck')
                        ->color('primary')
                        ->visible(fn ($record) => in_array($record->status, ['new', 'confirmed', 'processing']))
                        ->action(fn ($record) => $record->update(['status' => 'shipped'])),
                    Action::make('delivered')
                        ->label('Доставлен')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(fn ($record) => in_array($record->status, ['new', 'confirmed', 'processing', 'shipped']))
                        ->action(fn ($record) => $record->update(['status' => 'delivered'])),
                    EditAction::make(),
                ]),
            ])
            ->toolbarActions([
                ExportAction::make()
                    ->label('Экспорт')
                    ->exporter(OrderExporter::class),
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
