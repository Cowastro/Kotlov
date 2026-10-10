<?php

namespace App\Filament\Resources\MarketPriceObservations\Pages;

use App\Filament\Resources\MarketPriceObservations\MarketPriceObservationResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Width;

class CreateMarketPriceObservation extends CreateRecord
{
    protected static string $resource = MarketPriceObservationResource::class;

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }
}
