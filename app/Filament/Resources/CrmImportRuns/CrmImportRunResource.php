<?php

namespace App\Filament\Resources\CrmImportRuns;

use App\Filament\Resources\CrmImportRuns\Pages\ListCrmImportRuns;
use App\Models\CrmExternalIdentity;
use App\Models\CrmImportRun;
use App\Services\Crm\Imports\OneCCrmImporter;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CrmImportRunResource extends Resource
{
    protected static ?string $model = CrmImportRun::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPath;

    protected static ?string $navigationLabel = 'Импорты 1С';

    protected static ?string $modelLabel = 'импорт 1С';

    protected static ?string $pluralModelLabel = 'Импорты 1С';

    protected static ?int $navigationSort = 3;

    public static function getNavigationGroup(): ?string
    {
        return 'CRM';
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->isAdmin() === true;
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('created_at')->label('Запущен')->dateTime('d.m.Y H:i', 'Europe/Minsk')->sortable(),
            TextColumn::make('source')->label('База 1С')->badge()
                ->formatStateUsing(fn (string $state): string => CrmExternalIdentity::SOURCES[$state] ?? $state),
            TextColumn::make('import_type')->label('Тип')
                ->formatStateUsing(fn (string $state): string => OneCCrmImporter::TYPES[$state] ?? $state),
            TextColumn::make('file_name')->label('Файл')->searchable(),
            TextColumn::make('status')->label('Статус')->badge()
                ->formatStateUsing(fn (string $state): string => match ($state) {
                    'completed' => 'Завершён', 'failed' => 'Ошибка', default => 'Выполняется',
                })->color(fn (string $state): string => match ($state) {
                    'completed' => 'success', 'failed' => 'danger', default => 'warning',
                }),
            TextColumn::make('rows_count')->label('Строк')->numeric(),
            TextColumn::make('created_count')->label('Новых')->numeric(),
            TextColumn::make('matched_count')->label('Совпало')->numeric(),
            TextColumn::make('conflict_count')->label('Разобрать')->numeric()->color('danger'),
            TextColumn::make('contact_point_count')->label('Контактов')->numeric(),
            TextColumn::make('call_match_count')->label('Звонков распознано')->numeric(),
            TextColumn::make('importedBy.name')->label('Запустил')->placeholder('Консоль'),
            TextColumn::make('error')->label('Ошибка')->limit(80)->toggleable(isToggledHiddenByDefault: true),
        ])->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ListCrmImportRuns::route('/')];
    }
}
