<?php

namespace App\Filament\Resources\DealByOrders\Pages;

use App\Filament\Resources\DealByOrders\DealByOrderResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;

class ListDealByOrders extends ListRecords
{
    protected static string $resource = DealByOrderResource::class;

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }
}
