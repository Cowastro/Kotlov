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
                    ->state(fn (SupplierSync $record): ?string => filled($record->source_url) ? 'Открыть Google Таблицу' : null)
                    ->tooltip(fn (SupplierSync $record): ?string => $record->source_url)
                    ->url(fn (SupplierSync $record): ?string => $record->source_url)
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('info')
                    ->openUrlInNewTab(),
                TextColumn::make('last_run_at')
                    ->label('Последний ручной запуск')
                    ->dateTime('d.m.Y H:i', 'Europe/Minsk')
                    ->placeholder('Нет запусков')
                    ->sortable(),
                TextColumn::make('last_status')
                    ->label('Статус')
                    ->badge()
                    ->state(fn (SupplierSync $record): string => $record->statusLabel())
                    ->color(fn (SupplierSync $record): string => $record->statusColor()),
                IconColumn::make('is_active')
                    ->label('Включён')
                    ->boolean(),
            ])
            ->emptyStateHeading('Автоматическая синхронизация не привязана')
            ->emptyStateDescription('Ручные ссылки на прайсы остаются выше. Технические сценарии появятся здесь после привязки к поставщику.');
    }
}
