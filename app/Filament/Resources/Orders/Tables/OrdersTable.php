<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Models\Order;
use App\Filament\Exports\OrderExporter;
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
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        $paymentNames = [
            'cash'          => 'Наличными',
            'card'          => 'Картой',
            'bank_transfer' => 'Безналичный расчет',
            'installment'   => 'Рассрочка',
            'installment_6' => 'Рассрочка',
            'credit'        => 'Кредит',
            'credit_3_years' => 'Кредит',
        ] + collect(config('shop.payment_methods', []))
            ->mapWithKeys(fn($m, $k) => [$k => $m['name'] ?? $k])
            ->toArray();

        $deliveryNames = [
            'pickup'            => 'Самовывоз',
            'courier'           => 'Курьер по Минску',
            'courier_minsk'     => 'Курьер по Минску',
            'transport'         => 'ТК по Беларуси',
            'transport_company' => 'ТК по Беларуси',
            'kit'               => 'ТК КИТ / международная доставка',
        ] + collect(config('shop.delivery_methods', []))
            ->mapWithKeys(fn($m, $k) => [$k => $m['name'] ?? $k])
            ->toArray();

        $paymentStatuses = [
            'pending'  => 'Ожидает оплаты',
            'paid'     => 'Оплачен',
            'failed'   => 'Ошибка оплаты',
            'refunded' => 'Возврат',
        ];

        $statusLabelOverrides = [
            'new'             => 'Новый',
            'confirmed'       => 'Подтвержден',
            'processing'      => 'В обработке',
            'waiting_payment' => 'Ожидает оплаты',
            'paid'            => 'Оплачен',
            'shipped'         => 'Отправлен',
            'delivered'       => 'Доставлен',
            'completed'       => 'Выполнен',
            'cancelled'       => 'Отменен',
        ];

        $statusNames = collect(Order::STATUSES)
            ->mapWithKeys(fn($label, $status) => [$status => $statusLabelOverrides[$status] ?? $label])
            ->toArray();

        return $table
            ->modifyQueryUsing(fn(Builder $query) => $query
                ->withSum('items', 'quantity')
                ->with('items:id,order_id,product_name'))
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
                    ->searchable(),

                TextColumn::make('customer_phone')
                    ->label('Телефон')
                    ->searchable(),

                TextColumn::make('customer_email')
                    ->label('Email')
                    ->searchable()
                    ->copyable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('delivery_city')
                    ->label('Город')
                    ->searchable()
                    ->placeholder('—'),

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
                    ->formatStateUsing(fn(?string $state) => $state ? ($deliveryNames[$state] ?? $state) : '—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('payment_type')
                    ->label('Оплата')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn(?string $state) => $state ? ($paymentNames[$state] ?? $state) : '—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('payment_status')
                    ->label('Статус оплаты')
                    ->badge()
                    ->color(fn(?string $state) => match($state) {
                        'paid'     => 'success',
                        'pending'  => 'warning',
                        'failed'   => 'danger',
                        'refunded' => 'info',
                        default    => 'gray',
                    })
                    ->icon(fn(?string $state) => match($state) {
                        'paid'     => 'heroicon-o-check-circle',
                        'pending'  => 'heroicon-o-clock',
                        'failed'   => 'heroicon-o-x-circle',
                        'refunded' => 'heroicon-o-arrow-uturn-left',
                        default    => null,
                    })
                    ->formatStateUsing(fn(?string $state) => $state ? ($paymentStatuses[$state] ?? $state) : '—'),

                TextColumn::make('total')
                    ->label('Сумма')
                    ->sortable()
                    ->formatStateUsing(fn($state) => number_format((float) $state, 0, '.', ' ') . ' BYN'),

                TextColumn::make('items_sum_quantity')
                    ->label('Товаров')
                    ->state(fn($record) => (int) ($record->items_sum_quantity ?? 0))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->color(fn(?string $state) => match($state) {
                        'new'             => 'info',
                        'confirmed'       => 'warning',
                        'processing'      => 'warning',
                        'waiting_payment' => 'warning',
                        'paid'            => 'success',
                        'shipped'         => 'primary',
                        'delivered'       => 'success',
                        'completed'       => 'success',
                        'cancelled'       => 'danger',
                        default           => 'gray',
                    })
                    ->icon(fn(?string $state) => match($state) {
                        'new'             => 'heroicon-o-sparkles',
                        'confirmed'       => 'heroicon-o-check',
                        'processing'      => 'heroicon-o-arrow-path',
                        'waiting_payment' => 'heroicon-o-clock',
                        'paid'            => 'heroicon-o-banknotes',
                        'shipped'         => 'heroicon-o-truck',
                        'delivered'       => 'heroicon-o-check-circle',
                        'completed'       => 'heroicon-o-star',
                        'cancelled'       => 'heroicon-o-x-circle',
                        default           => null,
                    })
                    ->formatStateUsing(fn(?string $state) => $state ? ($statusNames[$state] ?? $statusLabelOverrides[$state] ?? $state) : '—'),

                TextColumn::make('assigned_to')
                    ->label('Ответственный')
                    ->placeholder('—')
                    ->icon('heroicon-o-user-circle')
                    ->searchable(),

                TextColumn::make('updated_at')
                    ->label('Обновлен')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('items_list')
                    ->label('Товары')
                    ->state(fn($record) => $record->items->map(fn($item) =>
                        $item->product_name .
                        ($item->product_sku ? ' [' . $item->product_sku . ']' : '')
                    )->filter()->join("\n"))
                    ->wrap()
                    ->lineClamp(3)
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

                Filter::make('created_at')
                    ->label('Дата создания')
                    ->schema([
                        DatePicker::make('created_from')
                            ->label('С даты'),
                        DatePicker::make('created_until')
                            ->label('По дату'),
                    ])
                    ->query(fn(Builder $query, array $data): Builder => $query
                        ->when($data['created_from'] ?? null, fn(Builder $query, $date) => $query->whereDate('created_at', '>=', $date))
                        ->when($data['created_until'] ?? null, fn(Builder $query, $date) => $query->whereDate('created_at', '<=', $date))),
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
