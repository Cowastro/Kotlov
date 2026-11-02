<?php

namespace App\Filament\Resources\B2bOrders\RelationManagers;

use App\Models\B2bOrder;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EventsRelationManager extends RelationManager
{
    protected static string $relationship = 'events';
    protected static ?string $title = 'История этапов и действий';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('actor')->latest('created_at')->latest('id'))
            ->columns([
                TextColumn::make('created_at')->label('Когда')->dateTime('d.m.Y H:i', 'Europe/Minsk')->sortable(),
                TextColumn::make('actor.name')->label('Кто')->placeholder('Система'),
                TextColumn::make('event')->label('Действие')->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'created' => 'Заказ создан',
                        'status_changed' => 'Изменён этап',
                        'client_confirmed' => 'Подтверждено партнёром',
                        'item_checked' => 'Проверена позиция',
                        'manager_assigned' => 'Назначен менеджер',
                        'legacy_migrated' => 'Перенесено из старого модуля',
                        default => $state,
                    }),
                TextColumn::make('from_status')->label('Было')
                    ->formatStateUsing(fn (?string $state): string => $state ? (B2bOrder::STATUSES[$state] ?? $state) : '—'),
                TextColumn::make('to_status')->label('Стало')
                    ->formatStateUsing(fn (?string $state): string => $state ? (B2bOrder::STATUSES[$state] ?? $state) : '—'),
                TextColumn::make('comment')->label('Комментарий')->wrap()->placeholder('—'),
            ]);
    }
}
