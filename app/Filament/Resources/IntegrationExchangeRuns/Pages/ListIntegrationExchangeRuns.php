<?php

namespace App\Filament\Resources\IntegrationExchangeRuns\Pages;

use App\Filament\Resources\IntegrationExchangeRuns\IntegrationExchangeRunResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;

class ListIntegrationExchangeRuns extends ListRecords
{
    protected static string $resource = IntegrationExchangeRunResource::class;

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    public function getSubheading(): ?string
    {
        return 'Каталог, цены, остатки и заказы: направление, результат, объём и ошибки каждого обмена.';
    }
}
