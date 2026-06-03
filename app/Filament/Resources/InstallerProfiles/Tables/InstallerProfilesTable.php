<?php

namespace App\Filament\Resources\InstallerProfiles\Tables;

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

class InstallerProfilesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('photo')
                    ->label('Фото')
                    ->circular()
                    ->defaultImageUrl(fn () => null)
                    ->toggleable(),
                TextColumn::make('company_name')
                    ->label('Компания')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('contact_name')
                    ->label('Контакт')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('phone')
                    ->label('Телефон')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('city')
                    ->label('Город')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('region')
                    ->label('Регион')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'active'    => 'success',
                        'pending'   => 'warning',
                        'suspended' => 'info',
                        'blocked'   => 'danger',
                        default     => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'active'    => 'Активен',
                        'pending'   => 'На проверке',
                        'suspended' => 'Приостановлен',
                        'blocked'   => 'Заблокирован',
                        default     => $state,
                    })
                    ->sortable(),
                IconColumn::make('is_published')
                    ->label('Опубл.')
                    ->boolean()
                    ->sortable(),
                IconColumn::make('is_verified')
                    ->label('Верифицирован')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('rating')
                    ->label('Рейтинг')
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),
                TextColumn::make('reviews_count')
                    ->label('Отзывов')
                    ->sortable(),
                TextColumn::make('orders_count')
                    ->label('Заказов')
                    ->sortable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('unp')
                    ->label('УНП')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Добавлен')
                    ->dateTime('d.m.Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->searchPlaceholder('Компания, контакт, телефон, email, УНП, город...')
            ->filters([
                SelectFilter::make('status')
                    ->label('Статус')
                    ->options([
                        'pending'   => 'На проверке',
                        'active'    => 'Активен',
                        'suspended' => 'Приостановлен',
                        'blocked'   => 'Заблокирован',
                    ]),
                TernaryFilter::make('is_published')
                    ->label('Публикация')
                    ->trueLabel('Опубликованные')
                    ->falseLabel('Не опубликованные'),
                TernaryFilter::make('is_verified')
                    ->label('Верификация')
                    ->trueLabel('Верифицированные')
                    ->falseLabel('Не верифицированные'),
                SelectFilter::make('region')
                    ->label('Регион')
                    ->options([
                        'Минск'               => 'Минск',
                        'Минская область'     => 'Минская область',
                        'Гомельская область'  => 'Гомельская область',
                        'Гродненская область' => 'Гродненская область',
                        'Брестская область'   => 'Брестская область',
                        'Витебская область'   => 'Витебская область',
                        'Могилёвская область' => 'Могилёвская область',
                    ]),
                TernaryFilter::make('nationwide')
                    ->label('По всей Беларуси')
                    ->trueLabel('Работает по всей Беларуси')
                    ->falseLabel('Только по региону'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
