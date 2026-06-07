<?php

namespace App\Filament\Resources\Orders\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        $paymentNames = collect(config('shop.payment_methods', []))
            ->mapWithKeys(fn($m, $k) => [$k => $m['name']])
            ->toArray();

        $deliveryNames = collect(config('shop.delivery_methods', []))
            ->mapWithKeys(fn($m, $k) => [$k => $m['name']])
            ->toArray();

        return $table
            ->columns([
                TextColumn::make('number')
                    ->label('Номер')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->color(fn(string $state) => match($state) {
                        'new'        => 'info',
                        'confirmed'  => 'warning',
                        'processing' => 'warning',
                        'shipped'    => 'primary',
                        'delivered'  => 'success',
                        'cancelled'  => 'danger',
                        default      => 'gray',
                    })
                    ->formatStateUsing(fn(string $state) => \App\Models\Order::STATUSES[$state] ?? $state),

                TextColumn::make('customer_name')
                    ->label('Клиент')
                    ->searchable(),

                TextColumn::make('customer_phone')
                    ->label('Телефон')
                    ->searchable(),

                TextColumn::make('payment_type')
                    ->label('Оплата')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn(string $state) => $paymentNames[$state] ?? $state),

                TextColumn::make('payment_status')
                    ->label('Статус оплаты')
                    ->badge()
                    ->color(fn(string $state) => match($state) {
                        'paid'    => 'success',
                        'pending' => 'warning',
                        default   => 'gray',
                    })
                    ->formatStateUsing(fn(string $state) => match($state) {
                        'paid'    => 'Оплачен',
                        'pending' => 'Ожидает',
                        default   => $state,
                    }),

                TextColumn::make('delivery_city')
                    ->label('Город')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('total')
                    ->label('Итого')
                    ->sortable()
                    ->formatStateUsing(fn($state) => number_format($state, 2) . ' BYN'),

                TextColumn::make('created_at')
                    ->label('Дата')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Статус')
                    ->options(\App\Models\Order::STATUSES),

                SelectFilter::make('payment_type')
                    ->label('Способ оплаты')
                    ->options($paymentNames),

                SelectFilter::make('payment_status')
                    ->label('Статус оплаты')
                    ->options([
                        'pending' => 'Ожидает',
                        'paid'    => 'Оплачен',
                    ]),

                SelectFilter::make('delivery_type')
                    ->label('Доставка')
                    ->options($deliveryNames),
            ])
            ->recordActions([
                ViewAction::make(),
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
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
