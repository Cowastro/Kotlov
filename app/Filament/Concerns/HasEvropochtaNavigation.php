<?php

namespace App\Filament\Concerns;

use App\Filament\Resources\Evropochta\PackagingResource;
use App\Filament\Resources\Evropochta\ShipmentResource;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Enums\SubNavigationPosition;

trait HasEvropochtaNavigation
{
    public static function getSubNavigationPosition(): SubNavigationPosition
    {
        return SubNavigationPosition::Top;
    }

    public function getSubNavigation(): array
    {
        return [
            NavigationItem::make('Отправления')->icon('heroicon-o-truck')
                ->url(ShipmentResource::getUrl('index'))->visible(ShipmentResource::canViewAny())
                ->isActiveWhen(fn (): bool => request()->routeIs(ShipmentResource::getRouteBaseName().'.*')),
            NavigationItem::make('Упаковка')->icon('heroicon-o-archive-box')
                ->url(PackagingResource::getUrl('index'))->visible(PackagingResource::canViewAny())
                ->isActiveWhen(fn (): bool => request()->routeIs(PackagingResource::getRouteBaseName().'.*')),
        ];
    }
}
