<?php

namespace App\Filament\Resources\IntegrationIssues;

use App\Filament\Resources\IntegrationIssues\Pages\ListIntegrationIssues;
use App\Filament\Resources\IntegrationProducts\IntegrationProductResource;
use App\Filament\Resources\IntegrationSources\IntegrationSourceResource;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\IntegrationIssue;
use App\Services\Integrations\IntegrationIssueAdvisor;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Placeholder;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

class IntegrationIssueResource extends Resource
{
    protected static ?string $model = IntegrationIssue::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static ?string $navigationLabel = 'Требует внимания';

    protected static ?string $modelLabel = 'проблема интеграции';

    protected static ?string $pluralModelLabel = 'Требует внимания';

    protected static ?int $navigationSort = 8;

    public static function getNavigationGroup(): ?string
    {
        return 'Интеграции';
    }

    public static function getNavigationBadge(): ?string
    {
        $count = IntegrationIssue::query()->where('status', 'open')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return IntegrationIssue::query()
            ->where('status', 'open')
            ->where('severity', 'danger')
            ->exists() ? 'danger' : 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['source', 'integrationProduct', 'order', 'assignee']))
            ->columns([
                TextColumn::make('severity')->label('Важность')->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'danger' => 'Критично',
                        'info' => 'Информация',
                        default => 'Внимание',
                    })
                    ->color(fn (string $state): string => $state),
                TextColumn::make('title')->label('Проблема')->searchable()->weight('bold')
                    ->description(fn (IntegrationIssue $record): ?string => $record->message)
                    ->wrap(),
                TextColumn::make('source.name')->label('Источник')->placeholder('Сайт')->badge()->color('gray'),
                TextColumn::make('object')->label('Объект')
                    ->state(fn (IntegrationIssue $record): string => $record->order?->number
                        ?? $record->integrationProduct?->name
                        ?? 'Источник интеграции')
                    ->limit(55)
                    ->tooltip(fn (IntegrationIssue $record): string => $record->order?->number
                        ?? $record->integrationProduct?->name
                        ?? $record->source?->name
                        ?? '—'),
                TextColumn::make('status')->label('Состояние')->badge()
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
                TextColumn::make('assignee.name')->label('Ответственный')->placeholder('Не назначен')
                    ->toggleable(),
                TextColumn::make('last_detected_at')->label('Обнаружено')
                    ->dateTime('d.m.Y H:i:s', 'Europe/Minsk')->sortable(),
            ])
            ->filters([
                SelectFilter::make('severity')->label('Важность')->options([
                    'danger' => 'Критично',
                    'warning' => 'Внимание',
                    'info' => 'Информация',
                ]),
                SelectFilter::make('type')->label('Тип')->options([
                    'integration_stale' => 'Нет свежего обмена',
                    'product_unmatched' => 'Товар не привязан',
                    'product_missing_category' => 'Нет категории',
                    'product_missing_price' => 'Нет цены',
                    'product_attention' => 'Товар требует решения',
                    'product_identity_collision' => 'Возможный дубль товара',
                    'order_not_exported' => 'Заказ не передан',
                    'order_no_1c_response' => 'Нет ответа 1С',
                    'order_status_conflict' => 'Конфликт статусов заказа',
                ]),
                SelectFilter::make('integration_source_id')->label('Источник')
                    ->relationship('source', 'name')->searchable()->preload(),
                SelectFilter::make('assigned_to_user_id')->label('Ответственный')
                    ->relationship('assignee', 'name')->searchable()->preload(),
                SelectFilter::make('status')->label('Состояние')->options([
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
                    ->modalHeading(fn (IntegrationIssue $record): string => app(IntegrationIssueAdvisor::class)->advise($record)['title'])
                    ->form([
                        Placeholder::make('recommended_steps')
                            ->label('Рекомендуемые шаги')
                            ->content(function (IntegrationIssue $record): HtmlString {
                                $advice = app(IntegrationIssueAdvisor::class)->advise($record);

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
                            ->content(fn (IntegrationIssue $record): string => app(IntegrationIssueAdvisor::class)->advise($record)['note']),
                    ])
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Закрыть'),
                Action::make('openObject')
                    ->label('Открыть')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(fn (IntegrationIssue $record): ?string => self::objectUrl($record)),
                Action::make('claim')
                    ->label('Взять в работу')
                    ->icon(Heroicon::OutlinedUserPlus)
                    ->color('info')
                    ->visible(fn (IntegrationIssue $record): bool => $record->status === 'open'
                        && $record->assigned_to_user_id !== (int) auth()->id())
                    ->action(fn (IntegrationIssue $record) => $record->update([
                        'assigned_to_user_id' => auth()->id(),
                    ])),
                Action::make('unclaim')
                    ->label('Снять с себя')
                    ->icon(Heroicon::OutlinedUserMinus)
                    ->color('gray')
                    ->visible(fn (IntegrationIssue $record): bool => $record->status === 'open'
                        && $record->assigned_to_user_id === (int) auth()->id())
                    ->action(fn (IntegrationIssue $record) => $record->update([
                        'assigned_to_user_id' => null,
                    ])),
                Action::make('resolve')
                    ->label('Решено')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->visible(fn (IntegrationIssue $record): bool => $record->status === 'open')
                    ->action(fn (IntegrationIssue $record) => $record->update([
                        'status' => 'resolved',
                        'resolved_at' => now(),
                    ])),
                Action::make('ignore')
                    ->label('Игнорировать')
                    ->icon(Heroicon::OutlinedEyeSlash)
                    ->color('gray')
                    ->visible(fn (IntegrationIssue $record): bool => $record->status !== 'ignored')
                    ->requiresConfirmation()
                    ->action(fn (IntegrationIssue $record) => $record->update([
                        'status' => 'ignored',
                        'resolved_at' => now(),
                    ])),
                Action::make('reopen')
                    ->label('Вернуть в работу')
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->visible(fn (IntegrationIssue $record): bool => $record->status !== 'open')
                    ->action(fn (IntegrationIssue $record) => $record->update([
                        'status' => 'open',
                        'resolved_at' => null,
                    ])),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('claim')
                        ->label('Взять в работу')
                        ->icon(Heroicon::OutlinedUserPlus)
                        ->color('info')
                        ->action(fn (Collection $records) => $records->each->update([
                            'assigned_to_user_id' => auth()->id(),
                        ]))
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('resolve')
                        ->label('Отметить решёнными')
                        ->icon(Heroicon::OutlinedCheckCircle)
                        ->color('success')
                        ->action(fn (Collection $records) => $records->each->update([
                            'status' => 'resolved',
                            'resolved_at' => now(),
                        ]))
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('ignore')
                        ->label('Игнорировать')
                        ->icon(Heroicon::OutlinedEyeSlash)
                        ->color('gray')
                        ->requiresConfirmation()
                        ->action(fn (Collection $records) => $records->each->update([
                            'status' => 'ignored',
                            'resolved_at' => now(),
                        ]))
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->poll('30s');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return ['index' => ListIntegrationIssues::route('/')];
    }

    private static function objectUrl(IntegrationIssue $issue): ?string
    {
        return match (true) {
            filled($issue->integration_product_id) => IntegrationProductResource::getUrl('edit', ['record' => $issue->integration_product_id]),
            filled($issue->order_id) => OrderResource::getUrl('view', ['record' => $issue->order_id]),
            filled($issue->integration_source_id) => IntegrationSourceResource::getUrl('edit', ['record' => $issue->integration_source_id]),
            default => null,
        };
    }
}
