<?php

namespace App\Filament\Resources\MarketAnalysis\Pages;

use App\Filament\Resources\MarketAnalysis\MarketAnalysisResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;

class ListMarketAnalysis extends ListRecords
{
    protected static string $resource = MarketAnalysisResource::class;

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    public function getTitle(): string
    {
        return 'Рынок и цены';
    }

    public function getSubheading(): ?string
    {
        return 'Розничные предложения рынка отделены от закупочных цен. Вывод строится только по свежим подтверждённым сопоставлениям.';
    }
}
