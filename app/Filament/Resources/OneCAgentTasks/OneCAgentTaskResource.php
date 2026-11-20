<?php

namespace App\Filament\Resources\OneCAgentTasks;

use App\Filament\Resources\OneCAgentTasks\Pages\ListOneCAgentTasks;
use App\Filament\Resources\OneCAgentTasks\Pages\ViewOneCAgentTask;
use App\Models\OneCAgentTask;
use App\Services\Integrations\OneCBridge\DocumentStatusTaskHandler;
use App\Services\Integrations\OneCBridge\ODataReadTaskHandler;
use App\Services\Integrations\OneCBridge\OneCAgentTasks;
use BackedEnum;
use DomainException;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** «Мост 1С»: what the site asked the agent on the 1C server to do, and what 1C answered. */
class OneCAgentTaskResource extends Resource
{
    protected static ?string $model = OneCAgentTask::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static ?string $navigationLabel = 'Мост 1С';

    protected static ?string $modelLabel = 'задача 1С';

    protected static ?string $pluralModelLabel = 'Мост 1С';

    protected static ?string $slug = 'onec-bridge';

    protected static ?int $navigationSort = 12;

    public static function getNavigationGroup(): ?string
    {
        return 'Интеграции';
    }

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->isManager();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    /** Red when tasks failed or the agent has been silent while tasks wait. */
    public static function getNavigationBadge(): ?string
    {
        $health = app(OneCAgentTasks::class)->health();
        if ($health['failed'] > 0) {
            return (string) $health['failed'];
        }

        return $health['stuck'] > 0 && ! $health['online'] ? '!' : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Задачи с ошибкой или агент 1С не на связи';
    }

    public static function table(Table $table): Table
    {
        $tasks = app(OneCAgentTasks::class);

        return $table
            ->columns([
                TextColumn::make('id')->label('№')->sortable(),
                TextColumn::make('type')->label('Что')->badge()->color('gray')
                    ->formatStateUsing(fn (string $state): string => $tasks->types()[$state] ?? $state),
                TextColumn::make('title')->label('Задача')->wrap()
                    ->url(fn (OneCAgentTask $record): ?string => rescue(fn () => $tasks->handler($record->type)->url($record), null, false)),
                TextColumn::make('status')->label('Состояние')->badge()
                    ->formatStateUsing(fn (string $state): string => OneCAgentTask::STATUSES[$state] ?? $state)
                    ->color(fn (string $state): string => self::color($state)),
                TextColumn::make('summary')->label('Результат')->wrap()->placeholder('—')
                    ->description(fn (OneCAgentTask $record): ?string => $record->error ? mb_strimwidth($record->error, 0, 300, '…') : null),
                TextColumn::make('attempts')->label('Попыток')->alignRight()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('creator.name')->label('Кто')->placeholder('—')->toggleable(),
                TextColumn::make('created_at')->label('Поставлена')->dateTime('d.m.Y H:i', 'Europe/Minsk')->sortable(),
                TextColumn::make('finished_at')->label('Ответ агента')->dateTime('d.m.Y H:i', 'Europe/Minsk')->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')->label('Состояние')->options(OneCAgentTask::STATUSES),
                SelectFilter::make('type')->label('Что')->options(fn (): array => $tasks->types()),
            ])
            // The regular checks of document states are many and say nothing new; only a failed one is shown.
            ->modifyQueryUsing(fn ($query) => $query->whereNot(fn ($q) => $q->where('type', DocumentStatusTaskHandler::TYPE)->where('status', OneCAgentTask::DONE)))
            ->defaultSort('id', 'desc')
            ->poll('30s')
            ->recordActions([
                ViewAction::make()->visible(fn (OneCAgentTask $record): bool => in_array($record->type, [ODataReadTaskHandler::TYPE, DocumentStatusTaskHandler::TYPE], true)),
                Action::make('retry')->label('Повторить')->icon('heroicon-o-arrow-path')
                    ->visible(fn (OneCAgentTask $record): bool => $record->status === OneCAgentTask::FAILED)
                    ->requiresConfirmation()
                    ->modalDescription('Задача снова встанет в очередь, агент выполнит её в течение 5 минут.')
                    ->action(fn (OneCAgentTask $record) => self::run(fn () => $tasks->retry($record), 'Задача снова в очереди')),
                Action::make('cancel')->label('Отменить')->icon('heroicon-o-x-mark')->color('gray')
                    ->visible(fn (OneCAgentTask $record): bool => in_array($record->status, [OneCAgentTask::QUEUED, OneCAgentTask::FAILED], true))
                    ->requiresConfirmation()
                    ->modalDescription('Агент не будет её выполнять. Пакет цен вернётся в черновик.')
                    ->action(fn (OneCAgentTask $record) => self::run(fn () => $tasks->cancel($record), 'Задача отменена')),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOneCAgentTasks::route('/'),
            'view' => ViewOneCAgentTask::route('/{record}'),
        ];
    }

    public static function color(string $state): string
    {
        return match ($state) {
            OneCAgentTask::DONE => 'success',
            OneCAgentTask::FAILED => 'danger',
            OneCAgentTask::QUEUED, OneCAgentTask::SENDING => 'warning',
            default => 'gray',
        };
    }

    private static function run(callable $action, string $title): void
    {
        try {
            $action();
            Notification::make()->success()->title($title)->send();
        } catch (DomainException $e) {
            Notification::make()->danger()->title('Не получилось')->body($e->getMessage())->send();
        }
    }
}
