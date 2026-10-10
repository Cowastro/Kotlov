<?php

namespace App\Filament\Resources\MarketPriceObservations\Pages;

use App\Filament\Resources\MarketPriceObservations\MarketPriceObservationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;

class ListMarketPriceObservations extends ListRecords
{
    protected static string $resource = MarketPriceObservationResource::class;

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Добавить наблюдение')];
    }
}
