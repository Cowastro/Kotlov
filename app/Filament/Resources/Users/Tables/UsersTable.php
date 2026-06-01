<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('avatar')
                    ->label('Фото')
                    ->circular(),

                TextColumn::make('name')
                    ->label('Имя')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),

                TextColumn::make('phone')
                    ->label('Телефон')
                    ->searchable(),

                TextColumn::make('role')
                    ->label('Роль')
                    ->badge()
                    ->color(fn(string $state) => match($state) {
                        'admin'     => 'danger',
                        'supplier'  => 'warning',
                        'installer' => 'info',
                        'client'    => 'success',
                        default     => 'gray',
                    })
                    ->formatStateUsing(fn(string $state) => match($state) {
                        'admin'     => 'Администратор',
                        'supplier'  => 'Поставщик',
                        'installer' => 'Монтажник',
                        'client'    => 'Клиент',
                        default     => $state,
                    }),

                IconColumn::make('is_active')
                    ->label('Активен')
                    ->boolean(),

                TextColumn::make('email_verified_at')
                    ->label('Верифицирован')
                    ->dateTime('d.m.Y')
                    ->sortable()
                    ->placeholder('Нет'),

                TextColumn::make('orders_count')
                    ->label('Заказов')
                    ->counts('orders')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Зарегистрирован')
                    ->dateTime('d.m.Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('role')
                    ->label('Роль')
                    ->options([
                        'admin'     => 'Администратор',
                        'supplier'  => 'Поставщик',
                        'installer' => 'Монтажник',
                        'client'    => 'Клиент',
                    ]),

                TernaryFilter::make('is_active')
                    ->label('Активность'),

                TernaryFilter::make('email_verified_at')
                    ->label('Верификация email')
                    ->nullable(),
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