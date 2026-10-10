<?php

namespace App\Filament\Supplier\Resources\IntegrationIssues;

use App\Filament\Supplier\Resources\IntegrationIssues\Pages\ListIntegrationIssues;
use App\Models\IntegrationIssue;
use App\Models\IntegrationSource;
use App\Services\Integrations\SupplierIntegrationIssueAdvisor;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class IntegrationIssueResource extends Resource
{
    protected static ?string $model = IntegrationIssue::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static ?string $navigationLabel = 'Требует внимания';

    protected static ?string $modelLabel = 'проблема интеграции';

    protected static ?string $pluralModelLabel = 'Требует внимания';

    protected static ?int $navigationSort = 3;

    public static function getNavigationGroup(): ?string
    {
        return 'Интеграции';
    }

    public static function getNavigationBadge(): ?string
    {
        $count = self::getEloquentQuery()->open()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return self::getEloquentQuery()->open()->where('severity', 'danger')->exists()
            ? 'danger'
            : 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('severity')
                    ->label('Важность')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'danger' => 'Критично',
                        'info' => 'Информация',
                        default => 'Внимание',
                    })
                    ->color(fn (string $state): string => $state),
                TextColumn::make('title')
                    ->label('Проблема')
                    ->weight('bold')
                    ->description(fn (IntegrationIssue $record): ?string => $record->message)
                    ->searchable()
                    ->wrap(),
                TextColumn::make('source.name')
                    ->label('Источник')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('object')
                    ->label('Объект')
                    ->state(fn (IntegrationIssue $record): string => $record->integrationProduct?->name
                        ?? $record->order?->number
                        ?? 'Источник интеграции')
                    ->limit(55)
                    ->wrap(),
                TextColumn::make('recommended_action')
                    ->label('Следующий шаг')
                    ->state(fn (IntegrationIssue $record): string => self::advice($record)['title'])
                    ->description(fn (IntegrationIssue $record): ?string => self::advice($record)['steps'][0] ?? null)
                    ->icon(Heroicon::OutlinedLightBulb)
                    ->color('info')
                    ->wrap(),
                TextColumn::make('next_step_owner')
                    ->label('Кто исправляет')
                    ->state(fn (IntegrationIssue $record): string => self::advice($record)['owner'])
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Поставщик' => 'warning',
                        'KOTLOV' => 'info',
                        default => 'primary',
                    }),
                TextColumn::make('status')
                    ->label('Состояние')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'resolved' => 'Решено',
                        'ignored' => 'Игнорируется',
                        default => 'Открыто',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'resolved' => 'success',
                        'ignored' => 'gray',
                        default => 'warning',
                    }),
                TextColumn::make('last_detected_at')
                    ->label('Обнаружено')
                    ->dateTime('d.m.Y H:i:s', 'Europe/Minsk')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('severity')
                    ->label('Важность')
                    ->options([
                        'danger' => 'Критично',
                        'warning' => 'Внимание',
                        'info' => 'Информация',
                    ]),
                SelectFilter::make('integration_source_id')
                    ->label('Источник')
                    ->options(fn (): array => self::allowedSources()->pluck('name', 'id')->all()),
                SelectFilter::make('status')
                    ->label('Состояние')
                    ->options([
                        'open' => 'Открыто',
                        'resolved' => 'Решено',
                        'ignored' => 'Игнорируется',
                    ]),
            ])
            ->defaultSort('last_detected_at', 'desc')
            ->recordActions([
                Action::make('advice')
                    ->label('Что делать')
                    ->icon(Heroicon::OutlinedLightBulb)
                    ->color('info')
                    ->modalHeading(fn (IntegrationIssue $record): string => self::advice($record)['title'])
                    ->form([
                        Placeholder::make('responsible_party')
                            ->label('Ответственный за следующий шаг')
                            ->content(fn (IntegrationIssue $record): string => self::advice($record)['owner']),
                        Placeholder::make('recommended_steps')
                            ->label('Рекомендуемые шаги')
                            ->content(function (IntegrationIssue $record): HtmlString {
                                $advice = self::advice($record);

                                return new HtmlString(
                                    '<ol class="list-decimal space-y-2 ps-5">'.
                                    collect($advice['steps'])
                                        ->map(fn (string $step): string => '<li>'.e($step).'</li>')
                                        ->implode('').
                                    '</ol>'
                                );
                            }),
                        Placeholder::make('safety_note')
                            ->label('Важно')
                            ->content(fn (IntegrationIssue $record): string => self::advice($record)['note']),
                    ])
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Закрыть'),
            ])
            ->poll('60s');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['source', 'integrationProduct', 'order'])
            ->whereIn('integration_source_id', self::allowedSources()->select('id'));
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListIntegrationIssues::route('/'),
        ];
    }

    private static function allowedSources(): Builder
    {
        $supplierIds = auth()->user()?->suppliers()->pluck('suppliers.id') ?? collect();

        return IntegrationSource::query()->whereIn('supplier_id', $supplierIds);
    }

    /** @return array{owner:string,title:string,steps:array<int,string>,note:string} */
    private static function advice(IntegrationIssue $issue): array
    {
        return app(SupplierIntegrationIssueAdvisor::class)->advise($issue);
    }
}
