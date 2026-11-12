<?php

namespace App\Filament\Resources\CityCategorySeos\Pages;

use App\Filament\Resources\CityCategorySeos\CityCategorySeoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCityCategorySeos extends ListRecords
{
    protected static string $resource = CityCategorySeoResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
