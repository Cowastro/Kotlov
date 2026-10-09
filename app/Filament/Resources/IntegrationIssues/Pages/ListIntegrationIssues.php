<?php

namespace App\Filament\Resources\IntegrationIssues\Pages;

use App\Filament\Resources\IntegrationIssues\IntegrationIssueResource;
use App\Filament\Widgets\IntegrationIssueTriageOverview;
use App\Models\IntegrationIssue;
use App\Services\Integrations\IntegrationIssueDetector;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Builder;

class ListIntegrationIssues extends ListRecords
{
    protected static string $resource = IntegrationIssueResource::class;

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    public function getSubheading(): ?string
    {
        return 'Единая очередь ошибок обмена, товарных данных и заказов. Исчезнувшие проблемы закрываются автоматически.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('scan')
                ->label('Проверить сейчас')
                ->icon('heroicon-o-arrow-path')
                ->action(function (): void {
                    $stats = app(IntegrationIssueDetector::class)->scan();

                    Notification::make()
                        ->success()
                        ->title('Проверка завершена')
                        ->body("Обнаружено: {$stats['detected']}; новых: {$stats['opened']}; закрыто: {$stats['resolved']}.")
                        ->send();
                }),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            IntegrationIssueTriageOverview::class,
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'priority';
    }

    public function getTabs(): array
    {
        return [
            'priority' => Tab::make('Приоритет')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->open()->priority())
                ->badge(IntegrationIssue::query()->open()->priority()->count())
                ->badgeColor('danger'),
            'mine' => Tab::make('Мои')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->open()->assignedTo((int) auth()->id()))
                ->badge(IntegrationIssue::query()->open()->assignedTo((int) auth()->id())->count() ?: null)
                ->badgeColor('info'),
            'recommended' => Tab::make('Рекомендации')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->open()->recommendedMatches())
                ->badge(IntegrationIssue::query()->open()->recommendedMatches()->count() ?: null)
                ->badgeColor('info'),
            'ready-to-link' => Tab::make('Можно привязать')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->open()->readyToLink())
                ->badge(IntegrationIssue::query()->open()->readyToLink()->count() ?: null)
                ->badgeColor('warning'),
            'missing-price' => Tab::make('Без цены')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->open()->missingPrice())
                ->badge(IntegrationIssue::query()->open()->missingPrice()->count())
                ->badgeColor('danger'),
            'duplicates' => Tab::make('Возможные дубли')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->open()->possibleDuplicates())
                ->badge(IntegrationIssue::query()->open()->possibleDuplicates()->count())
                ->badgeColor('warning'),
            'orders' => Tab::make('Заказы')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->open()->orders())
                ->badge(IntegrationIssue::query()->open()->orders()->count())
                ->badgeColor('danger'),
            'exchange' => Tab::make('Обмен')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->open()->exchange())
                ->badge(IntegrationIssue::query()->open()->exchange()->count())
                ->badgeColor('info'),
            'all' => Tab::make('Вся история')
                ->badge(IntegrationIssue::query()->count()),
        ];
    }
}
