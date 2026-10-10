<?php

namespace App\Filament\Resources\MarketPriceSources\Pages;

use App\Filament\Resources\MarketPriceSources\MarketPriceSourceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;

class ListMarketPriceSources extends ListRecords
{
    protected static string $resource = MarketPriceSourceResource::class;

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Добавить разрешённый источник')];
    }
}
