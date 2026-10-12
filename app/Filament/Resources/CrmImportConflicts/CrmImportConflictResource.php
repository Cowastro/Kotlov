<?php

namespace App\Filament\Resources\CrmImportConflicts;

use App\Filament\Resources\CrmImportConflicts\Pages\ListCrmImportConflicts;
use App\Models\CrmCounterparty;
use App\Models\CrmExternalIdentity;
use App\Models\CrmImportConflict;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CrmImportConflictResource extends Resource
{
    protected static ?string $model = CrmImportConflict::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static ?string $navigationLabel = 'Сверка 1С';

    protected static ?string $modelLabel = 'конфликт импорта';

    protected static ?string $pluralModelLabel = 'Сверка 1С';

    protected static ?int $navigationSort = 4;

    public static function getNavigationGroup(): ?string
    {
        return 'CRM';
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->isAdmin() === true;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = self::getEloquentQuery()->where('status', 'pending')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'warning';
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('external_name')->label('Название в 1С')->searchable()->weight('bold'),
            TextColumn::make('source')->label('Источник')->badge()
                ->formatStateUsing(fn (string $state): string => CrmExternalIdentity::SOURCES[$state] ?? $state),
            TextColumn::make('reason')->label('Почему требуется разбор')->wrap(),
            TextColumn::make('candidate_count')->label('Возможных карточек')
                ->state(fn (CrmImportConflict $record): int => count($record->candidate_ids ?? [])),
            TextColumn::make('status')->label('Статус')->badge()
                ->formatStateUsing(fn (string $state): string => CrmImportConflict::STATUSES[$state] ?? $state),
            TextColumn::make('resolvedCounterparty.name')->label('Связано с')->placeholder('—'),
            TextColumn::make('created_at')->label('Создан')->dateTime('d.m.Y H:i', 'Europe/Minsk')->sortable(),
        ])->filters([
            SelectFilter::make('status')->label('Статус')->options(CrmImportConflict::STATUSES)->default('pending'),
            SelectFilter::make('source')->label('Источник')->options(CrmExternalIdentity::SOURCES),
        ])->recordActions([
            Action::make('resolve')->label('Связать')->icon('heroicon-o-link')->color('success')
                ->visible(fn (CrmImportConflict $record): bool => $record->status === 'pending')
                ->form([
                    Select::make('counterparty_id')->label('Карточка CRM')->options(fn (): array => CrmCounterparty::query()->orderBy('name')->pluck('name', 'id')->all()
                    )->searchable()->required(),
                ])->action(function (CrmImportConflict $record, array $data): void {
                    if (filled($record->external_id)) {
                        CrmExternalIdentity::query()->updateOrCreate(
                            ['source' => $record->source, 'external_id' => $record->external_id],
                            [
                                'counterparty_id' => $data['counterparty_id'],
                                'external_name' => $record->external_name,
                                'payload' => $record->payload,
                                'synced_at' => now(),
                            ],
                        );
                    }
                    $record->update([
                        'status' => 'resolved',
                        'resolved_counterparty_id' => $data['counterparty_id'],
                        'resolved_by' => auth()->id(),
                        'resolved_at' => now(),
                    ]);
                    Notification::make()->title('Связь сохранена')
                        ->body('При следующем импорте данные 1С обновят выбранную карточку.')->success()->send();
                }),
            Action::make('ignore')->label('Игнорировать')->color('gray')
                ->visible(fn (CrmImportConflict $record): bool => $record->status === 'pending')
                ->requiresConfirmation()
                ->action(fn (CrmImportConflict $record) => $record->update([
                    'status' => 'ignored', 'resolved_by' => auth()->id(), 'resolved_at' => now(),
                ])),
        ])->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ListCrmImportConflicts::route('/')];
    }
}
