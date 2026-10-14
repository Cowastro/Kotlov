<?php

namespace App\Filament\Resources\Suppliers\RelationManagers;

use App\Models\SupplierSync;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PriceSyncsRelationManager extends RelationManager
{
    protected static string $relationship = 'syncs';

    protected static ?string $title = 'Автоматическое обновление';

    protected static ?string $label = 'сценарий обновления';

    protected static ?string $pluralLabel = 'Автоматическое обновление';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('Сценарий')
                    ->description(fn (SupplierSync $record): ?string => $record->description)
                    ->weight('bold')
                    ->wrap(),
                TextColumn::make('source_url')
                    ->label('Источник')
                    ->placeholder('Встроенный канал')
                    ->limit(55)
                    ->tooltip(fn (?string $state): ?string => $state)
                    ->url(fn (?string $state): ?string => $state)
                    ->openUrlInNewTab(),
                TextColumn::make('last_run_at')
                    ->label('Последний ручной запуск')
                    ->dateTime('d.m.Y H:i', 'Europe/Minsk')
                    ->placeholder('Нет запусков')
                    ->sortable(),
                TextColumn::make('last_status')
                    ->label('Статус')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'updated' => 'Обновлено',
                        'dry-run' => 'Проверено',
                        'failed' => 'Ошибка',
                        default => 'Нет запусков',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'updated' => 'success',
                        'dry-run' => 'info',
                        'failed' => 'danger',
                        default => 'gray',
                    }),
                IconColumn::make('is_active')
                    ->label('Включён')
                    ->boolean(),
            ])
            ->emptyStateHeading('Автоматическая синхронизация не привязана')
            ->emptyStateDescription('Ручные ссылки на прайсы остаются выше. Технические сценарии появятся здесь после привязки к поставщику.');
    }
}
