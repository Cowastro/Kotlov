<?php

namespace App\Filament\Concerns;

use App\Filament\Resources\DealByOrders\DealByOrderResource;
use App\Filament\Resources\Orders\OrderResource;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Enums\SubNavigationPosition;

trait HasOrdersNavigation
{
    public static function getSubNavigationPosition(): SubNavigationPosition
    {
        return SubNavigationPosition::Top;
    }

    public function getSubNavigation(): array
    {
        return [
            NavigationItem::make('Kotlov.by')->icon('heroicon-o-shopping-cart')
                ->url(OrderResource::getUrl('index'))->visible(OrderResource::canViewAny())
                ->badge(fn (): ?string => OrderResource::getNavigationBadge(), 'warning')
                ->isActiveWhen(fn (): bool => request()->routeIs(OrderResource::getRouteBaseName().'.*')),
            NavigationItem::make('DEAL.by')->icon('heroicon-o-shopping-bag')
                ->url(DealByOrderResource::getUrl('index'))->visible(DealByOrderResource::canViewAny())
                ->badge(fn (): ?string => DealByOrderResource::getNavigationBadge(), 'warning')
                ->isActiveWhen(fn (): bool => request()->routeIs(DealByOrderResource::getRouteBaseName().'.*')),
        ];
    }
}
