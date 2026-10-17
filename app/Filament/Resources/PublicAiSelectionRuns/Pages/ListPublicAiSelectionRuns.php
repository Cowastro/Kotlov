<?php

namespace App\Filament\Resources\PublicAiSelectionRuns\Pages;

use App\Filament\Resources\PublicAiSelectionRuns\PublicAiSelectionRunResource;
use App\Filament\Widgets\AiSelectionOverview;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;

class ListPublicAiSelectionRuns extends ListRecords
{
    protected static string $resource = PublicAiSelectionRunResource::class;

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    protected function getHeaderWidgets(): array
    {
        return [AiSelectionOverview::class];
    }
}
