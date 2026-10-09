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
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'open'))
                ->badge(IntegrationIssue::query()->where('status', 'open')->count())
                ->badgeColor('warning'),
            'danger' => Tab::make('Критичные')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where('status', 'open')
                    ->where('severity', 'danger'))
                ->badge(IntegrationIssue::query()->where('status', 'open')->where('severity', 'danger')->count())
                ->badgeColor('danger'),
            'all' => Tab::make('Все')
                ->badge(IntegrationIssue::query()->count()),
        ];
    }
}
