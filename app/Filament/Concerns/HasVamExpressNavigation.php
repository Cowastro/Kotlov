<?php

namespace App\Filament\Concerns;

use App\Filament\Pages\VamExpressCalculator;
use App\Filament\Resources\VamExpress\VamExpressShipmentResource;
use App\Filament\Resources\VamExpressDrafts\VamExpressDraftResource;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Enums\SubNavigationPosition;

trait HasVamExpressNavigation
{
    public static function getSubNavigationPosition(): SubNavigationPosition
    {
        return SubNavigationPosition::Top;
    }

    public function getSubNavigation(): array
    {
        return [
            NavigationItem::make('Отправления')->icon('heroicon-o-truck')
                ->url(VamExpressShipmentResource::getUrl('index'))
                ->isActiveWhen(fn (): bool => request()->routeIs(VamExpressShipmentResource::getRouteBaseName().'.*')),
            NavigationItem::make('Калькулятор')->icon('heroicon-o-calculator')
                ->url(VamExpressCalculator::getUrl())
                ->isActiveWhen(fn (): bool => request()->routeIs(VamExpressCalculator::getRouteName())),
            NavigationItem::make('Черновики')->icon('heroicon-o-document-text')
                ->url(VamExpressDraftResource::getUrl('index'))
                ->isActiveWhen(fn (): bool => request()->routeIs(VamExpressDraftResource::getRouteBaseName().'.*')),
        ];
    }
}
