<?php

namespace App\Filament\Resources\Orders\Tables;

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
                    ->formatStateUsing(fn(string $state) => match($state) {
                        'new'        => 'Новый',
                        'confirmed'  => 'Подтверждён',
                        'processing' => 'В обработке',
                        'shipped'    => 'Отправлен',
                        'delivered'  => 'Доставлен',
                        'cancelled'  => 'Отменён',
                        default      => $state,
                    }),

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
                    ->formatStateUsing(fn(string $state) => match($state) {
                        'cash'    => 'Наличными',
                        'card'    => 'Картой',
                        'invoice' => 'По счёту',
                        default   => $state,
                    }),

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
                    ->options([
                        'new'        => 'Новый',
                        'confirmed'  => 'Подтверждён',
                        'processing' => 'В обработке',
                        'shipped'    => 'Отправлен',
                        'delivered'  => 'Доставлен',
                        'cancelled'  => 'Отменён',
                    ]),

                SelectFilter::make('payment_status')
                    ->label('Оплата')
                    ->options([
                        'pending' => 'Ожидает',
                        'paid'    => 'Оплачен',
                    ]),

                SelectFilter::make('delivery_type')
                    ->label('Доставка')
                    ->options([
                        'pickup'    => 'Самовывоз',
                        'courier'   => 'Курьером',
                        'transport' => 'Транспортной компанией',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}