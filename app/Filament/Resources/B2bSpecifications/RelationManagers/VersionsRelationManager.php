<?php

namespace App\Filament\Resources\B2bSpecifications\RelationManagers;

use App\Models\B2bSpecification;
use App\Models\B2bSpecificationVersion;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class VersionsRelationManager extends RelationManager
{
    protected static string $relationship = 'versions';

    protected static ?string $title = 'История редакций';

    protected static ?string $label = 'Редакция';

    protected static ?string $pluralLabel = 'Редакции';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('creator'))
            ->columns([
                TextColumn::make('number')->label('Версия')->formatStateUsing(fn (int $state): string => '№'.$state)->weight('bold'),
                TextColumn::make('source')->label('Источник')->formatStateUsing(
                    fn (string $state): string => B2bSpecificationVersion::SOURCES[$state] ?? $state,
                ),
                TextColumn::make('parsing_status')->label('Разбор')->badge()->formatStateUsing(
                    fn (string $state): string => B2bSpecification::PARSING_STATUSES[$state] ?? $state,
                )->color(fn (string $state): string => match ($state) {
                    'parsed' => 'success',
                    'manual' => 'warning',
                    'failed' => 'danger',
                    default => 'gray',
                }),
                TextColumn::make('item_count')->label('Позиций')->numeric(),
                TextColumn::make('matched_count')->label('Найдено')->numeric()->color('success'),
                TextColumn::make('changes')->label('Изменения')->state(function (B2bSpecificationVersion $record): string {
                    $diff = $record->diff_summary ?? [];

                    return collect([
                        '+'.($diff['added'] ?? 0),
                        '±'.($diff['changed'] ?? 0),
                        '−'.($diff['removed'] ?? 0),
                    ])->implode(' · ');
                })->description('+ добавлено · ± изменено · − удалено'),
                TextColumn::make('note')->label('Комментарий клиента')->placeholder('—')->wrap()->limit(80),
                TextColumn::make('creator.name')->label('Кто загрузил')->placeholder('Система'),
                TextColumn::make('created_at')->label('Создана')->dateTime('d.m.Y H:i', 'Europe/Minsk')->sortable(),
            ])
            ->defaultSort('number', 'desc')
            ->emptyStateHeading('История пока не создана');
    }
}
