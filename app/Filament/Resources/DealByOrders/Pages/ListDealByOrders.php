<?php

namespace App\Filament\Resources\DealByOrders\Pages;

use App\Filament\Resources\DealByOrders\DealByOrderResource;
use App\Filament\Resources\Orders\Pages\ListOrders;

class ListDealByOrders extends ListOrders
{
    protected static string $resource = DealByOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
