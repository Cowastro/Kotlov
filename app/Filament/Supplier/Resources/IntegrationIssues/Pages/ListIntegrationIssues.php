<?php

namespace App\Filament\Supplier\Resources\IntegrationIssues\Pages;

use App\Filament\Supplier\Resources\IntegrationIssues\IntegrationIssueResource;
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
        return 'Проблемы только ваших источников: видно, кто выполняет следующий шаг — поставщик, KOTLOV или обе стороны. Изменение карточек остаётся за администратором KOTLOV.';
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'open';
    }

    public function getTabs(): array
    {
        $query = fn (): Builder => IntegrationIssueResource::getEloquentQuery();

        return [
            'open' => Tab::make('Открытые')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'open'))
                ->badge($query()->where('status', 'open')->count())
                ->badgeColor('warning'),
            'critical' => Tab::make('Критичные')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where('status', 'open')
                    ->where('severity', 'danger'))
                ->badge($query()->where('status', 'open')->where('severity', 'danger')->count())
                ->badgeColor('danger'),
            'all' => Tab::make('Все')->badge($query()->count()),
            'resolved' => Tab::make('Решённые')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'resolved'))
                ->badge($query()->where('status', 'resolved')->count())
                ->badgeColor('success'),
        ];
    }
}
