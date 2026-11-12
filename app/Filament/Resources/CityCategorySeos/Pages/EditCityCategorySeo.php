<?php

namespace App\Filament\Resources\CityCategorySeos\Pages;

use App\Filament\Resources\CityCategorySeos\CityCategorySeoResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCityCategorySeo extends EditRecord
{
    protected static string $resource = CityCategorySeoResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
