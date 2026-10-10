<?php

namespace App\Filament\Supplier\Resources\SupplierOrderRequests\Pages;

use App\Filament\Supplier\Resources\SupplierOrderRequests\SupplierOrderRequestResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Builder;

class ListSupplierOrderRequests extends ListRecords
{
    protected static string $resource = SupplierOrderRequestResource::class;

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    public function getSubheading(): ?string
    {
        return 'Только подтверждённо переданные вам заявки. Черновики KOTLOV и заявки других поставщиков недоступны.';
    }

    public function getTabs(): array
    {
        $query = fn (): Builder => SupplierOrderRequestResource::getEloquentQuery();

        return [
            'attention' => Tab::make('Новые')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'sent'))
                ->badge($query()->where('status', 'sent')->count())
                ->badgeColor('warning'),
            'active' => Tab::make('В работе')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'acknowledged'))
                ->badge($query()->where('status', 'acknowledged')->count())
                ->badgeColor('info'),
            'all' => Tab::make('Все')->badge($query()->count()),
            'fulfilled' => Tab::make('Исполнены')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'fulfilled'))
                ->badge($query()->where('status', 'fulfilled')->count())
                ->badgeColor('success'),
            'rejected' => Tab::make('Отклонены')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'rejected'))
                ->badge($query()->where('status', 'rejected')->count())
                ->badgeColor('danger'),
        ];
    }
}
