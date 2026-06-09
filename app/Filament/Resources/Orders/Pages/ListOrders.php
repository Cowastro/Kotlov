<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use Filament\Actions\CreateAction;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Builder;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        $colors = [
            'new'        => 'info',
            'confirmed'  => 'warning',
            'processing' => 'warning',
            'shipped'    => 'primary',
            'delivered'  => 'success',
            'completed'  => 'success',
            'cancelled'  => 'danger',
        ];

        $tabs = [
            'all' => Tab::make('Все')
                ->badge(Order::count()),
        ];

        foreach (Order::STATUSES as $key => $label) {
            $count = Order::where('status', $key)->count();
            $tabs[$key] = Tab::make($label)
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', $key))
                ->badge($count ?: null)
                ->badgeColor($colors[$key] ?? 'gray');
        }

        return $tabs;
    }
}
