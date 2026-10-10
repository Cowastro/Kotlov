<?php

namespace App\Filament\Supplier\Resources\Orders\Pages;

use App\Filament\Supplier\Resources\Orders\OrderResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Builder;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    public function getSubheading(): ?string
    {
        return 'Только заказы с товарами ваших поставщиков. В смешанном заказе вы увидите исключительно свои позиции и свою сумму.';
    }

    public function getTabs(): array
    {
        $query = fn (): Builder => OrderResource::getEloquentQuery();

        return [
            'active' => Tab::make('Требуют работы')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn('status', ['new', 'confirmed', 'processing']))
                ->badge($query()->whereIn('status', ['new', 'confirmed', 'processing'])->count())
                ->badgeColor('warning'),
            'all' => Tab::make('Все')->badge($query()->count()),
            'shipped' => Tab::make('Отправлены')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'shipped'))
                ->badge($query()->where('status', 'shipped')->count())
                ->badgeColor('info'),
            'delivered' => Tab::make('Доставлены')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'delivered'))
                ->badge($query()->where('status', 'delivered')->count())
                ->badgeColor('success'),
            'cancelled' => Tab::make('Отменены')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'cancelled'))
                ->badge($query()->where('status', 'cancelled')->count())
                ->badgeColor('danger'),
        ];
    }
}
