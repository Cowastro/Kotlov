<?php

namespace App\Filament\Resources\Evropochta\Pages;

use App\Filament\Concerns\HasEvropochtaNavigation;
use App\Filament\Resources\Evropochta\ShipmentResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use App\Support\Shipping\EvropochtaStatus;
use Illuminate\Database\Eloquent\Builder;

class ListShipments extends ListRecords
{
    use HasEvropochtaNavigation;

    protected static string $resource = ShipmentResource::class;

    public function getDefaultActiveTab(): string|int|null
    {
        return 'work';
    }

    public function getTabs(): array
    {
        $tabs = [];
        foreach (['work' => 'В работе', 'closed' => 'Завершённые / отменённые', 'all' => 'Все'] as $key => $label) {
            $tabs[$key] = Tab::make($label)
                ->badge(EvropochtaStatus::scope(ShipmentResource::getEloquentQuery(), $key)->count())
                ->modifyQueryUsing(fn (Builder $query): Builder => EvropochtaStatus::scope($query, $key));
        }
        return $tabs;
    }

    public function getSubheading(): ?string
    {
        return 'По умолчанию — только рабочие отправления. Архив и отмены — в отдельной вкладке. Действия с посылкой доступны в меню «Управление».';
    }

    protected function getHeaderActions(): array
    {
        return [ShipmentResource::quoteAction()];
    }
}
