<?php

namespace App\Filament\Resources\MarketPriceSources\Pages;

use App\Filament\Resources\MarketPriceSources\MarketPriceSourceResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Width;

class CreateMarketPriceSource extends CreateRecord
{
    protected static string $resource = MarketPriceSourceResource::class;

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }
}
