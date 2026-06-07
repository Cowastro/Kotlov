<?php

namespace App\Filament\Resources\InstallRequests\Tables;

use App\Models\InstallerProfile;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class InstallRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('customer_name')
                    ->label('Клиент')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('customer_phone')
                    ->label('Телефон')
                    ->searchable(),
                TextColumn::make('customer_email')
                    ->label('Email')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('specialization')
                    ->label('Тип работ')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'heating'       => 'warning',
                        'heatpump'      => 'info',
                        'fireplace'     => 'danger',
                        'chimney'       => 'gray',
                        'sauna'         => 'success',
                        'service'       => 'primary',
                        'commissioning' => 'primary',
                        default         => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'heating'       => 'Монтаж котла',
                        'heatpump'      => 'Монтаж насоса',
                        'fireplace'     => 'Монтаж камина',
                        'chimney'       => 'Монтаж дымохода',
                        'sauna'         => 'Монтаж банной печи',
                        'service'       => 'Сервис',
                        'commissioning' => 'Пусконаладка',
                        'other'         => 'Другое',
                        default         => $state,
                    })
                    ->placeholder('-'),
                TextColumn::make('city')
                    ->label('Город')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('address')
                    ->label('Адрес')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('installerProfile.company_name')
                    ->label('Монтажник')
                    ->searchable()
                    ->placeholder('-')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'new'         => 'info',
                        'accepted'    => 'warning',
                        'in_progress' => 'primary',
                        'done'        => 'success',
                        'cancelled'   => 'danger',
                        default       => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'new'         => 'Новая',
                        'accepted'    => 'Принята',
                        'in_progress' => 'В работе',
                        'done'        => 'Выполнена',
                        'cancelled'   => 'Отменена',
                        default       => $state,
                    })
                    ->sortable(),
                TextColumn::make('budget')
                    ->label('Бюджет, BYN')
                    ->numeric()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('source')
                    ->label('Источник')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'installers_page'   => 'Монтажники',
                        'installer_profile' => 'Профиль',
                        'product_page'      => 'Товар',
                        'cart'              => 'Корзина',
                        'admin'             => 'Админка',
                        'other'             => 'Другое',
                        default             => $state,
                    })
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('preferred_date')
                    ->label('Дата')
                    ->date('d.m.Y')
                    ->sortable()
                    ->placeholder('-'),
                TextColumn::make('created_at')
                    ->label('Создана')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                // Скрытые searchable-колонки
                TextColumn::make('notes')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Статус')
                    ->options([
                        'new'         => 'Новая',
                        'accepted'    => 'Принята',
                        'in_progress' => 'В работе',
                        'done'        => 'Выполнена',
                        'cancelled'   => 'Отменена',
                    ]),
                SelectFilter::make('specialization')
                    ->label('Тип работ')
                    ->options([
                        'heating'       => 'Монтаж котла',
                        'heatpump'      => 'Монтаж теплового насоса',
                        'fireplace'     => 'Монтаж камина',
                        'chimney'       => 'Монтаж дымохода',
                        'sauna'         => 'Монтаж банной печи',
                        'service'       => 'Сервис',
                        'commissioning' => 'Пусконаладка',
                        'other'         => 'Другое',
                    ]),
                SelectFilter::make('source')
                    ->label('Источник')
                    ->options([
                        'installers_page'   => 'Страница монтажников',
                        'installer_profile' => 'Профиль монтажника',
                        'product_page'      => 'Карточка товара',
                        'cart'              => 'Корзина',
                        'admin'             => 'Админка',
                        'other'             => 'Другое',
                    ]),
                SelectFilter::make('installer_profile_id')
                    ->label('Монтажник')
                    ->options(
                        InstallerProfile::query()
                            ->get()
                            ->mapWithKeys(fn ($p) => [
                                $p->id => $p->company_name
                                    ?: ($p->contact_name ?: "Монтажник #{$p->id}"),
                            ])
                    ),
                SelectFilter::make('city')
                    ->label('Город')
                    ->options([
                        'Минск'    => 'Минск',
                        'Гродно'   => 'Гродно',
                        'Брест'    => 'Брест',
                        'Витебск'  => 'Витебск',
                        'Гомель'   => 'Гомель',
                        'Могилёв'  => 'Могилёв',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('accepted')
                    ->label('Принята')
                    ->icon('heroicon-o-phone')
                    ->color('warning')
                    ->visible(fn ($record) => $record->status === 'new')
                    ->action(fn ($record) => $record->update(['status' => 'accepted'])),
                Action::make('in_progress')
                    ->label('В работе')
                    ->icon('heroicon-o-wrench-screwdriver')
                    ->color('primary')
                    ->visible(fn ($record) => in_array($record->status, ['new', 'accepted']))
                    ->action(fn ($record) => $record->update(['status' => 'in_progress'])),
                Action::make('done')
                    ->label('Выполнена')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record) => in_array($record->status, ['new', 'accepted', 'in_progress']))
                    ->action(fn ($record) => $record->update(['status' => 'done'])),
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
