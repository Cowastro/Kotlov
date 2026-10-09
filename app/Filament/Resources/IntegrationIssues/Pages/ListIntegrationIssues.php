<?php

namespace App\Filament\Resources\IntegrationIssues\Pages;

use App\Filament\Resources\IntegrationIssues\IntegrationIssueResource;
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

    public function getTabs(): array
    {
        return [
            'open' => Tab::make('Открытые')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->open())
                ->badge(IntegrationIssue::query()->open()->count())
                ->badgeColor('warning'),
            'danger' => Tab::make('Критичные')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->open()
                    ->where('severity', 'danger'))
                ->badge(IntegrationIssue::query()->open()->where('severity', 'danger')->count())
                ->badgeColor('danger'),
            'products' => Tab::make('Товары')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->open()->products())
                ->badge(IntegrationIssue::query()->open()->products()->count())
                ->badgeColor('warning'),
            'orders' => Tab::make('Заказы')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->open()->orders())
                ->badge(IntegrationIssue::query()->open()->orders()->count())
                ->badgeColor('danger'),
            'exchange' => Tab::make('Обмен')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->open()->exchange())
                ->badge(IntegrationIssue::query()->open()->exchange()->count())
                ->badgeColor('info'),
            'all' => Tab::make('История')
                ->badge(IntegrationIssue::query()->count()),
        ];
    }
}
