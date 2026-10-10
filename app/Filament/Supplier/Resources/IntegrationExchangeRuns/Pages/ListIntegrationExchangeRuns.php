<?php

namespace App\Filament\Supplier\Resources\IntegrationExchangeRuns\Pages;

use App\Filament\Supplier\Resources\IntegrationExchangeRuns\IntegrationExchangeRunResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Builder;

class ListIntegrationExchangeRuns extends ListRecords
{
    protected static string $resource = IntegrationExchangeRunResource::class;

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    public function getSubheading(): ?string
    {
        return 'История получения каталога, передачи заказов и возврата статусов. Журнал доступен только по вашим источникам.';
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return IntegrationExchangeRunResource::getEloquentQuery()->where('status', 'failed')->exists()
            ? 'failed'
            : 'all';
    }

    public function getTabs(): array
    {
        $query = fn (): Builder => IntegrationExchangeRunResource::getEloquentQuery();

        return [
            'failed' => Tab::make('Ошибки')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'failed'))
                ->badge($query()->where('status', 'failed')->count())
                ->badgeColor('danger'),
            'running' => Tab::make('Выполняются')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'running'))
                ->badge($query()->where('status', 'running')->count())
                ->badgeColor('info'),
            'all' => Tab::make('Все')->badge($query()->count()),
            'success' => Tab::make('Успешные')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'success'))
                ->badge($query()->where('status', 'success')->count())
                ->badgeColor('success'),
        ];
    }
}
