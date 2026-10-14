<?php

namespace App\Filament\Resources\MarketResearchReports\Pages;

use App\Filament\Resources\MarketResearchReports\MarketResearchReportResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;

class ListMarketResearchReports extends ListRecords
{
    protected static string $resource = MarketResearchReportResource::class;

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    public function getTitle(): string
    {
        return 'Архив анализа рынка';
    }

    public function getSubheading(): ?string
    {
        return 'История исследований хранится отдельно по товару и пакету: источники, отклонённые варианты, вывод и принятое решение не перезаписываются следующим прогоном.';
    }
}
