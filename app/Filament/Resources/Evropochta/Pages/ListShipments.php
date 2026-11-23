<?php

namespace App\Filament\Resources\Evropochta\Pages;

use App\Filament\Concerns\HasEvropochtaNavigation;
use App\Filament\Resources\Evropochta\ShipmentResource;
use Filament\Resources\Pages\ListRecords;

class ListShipments extends ListRecords
{
    use HasEvropochtaNavigation;

    protected static string $resource = ShipmentResource::class;

    protected function getHeaderActions(): array
    {
        return [ShipmentResource::quoteAction()];
    }
}
