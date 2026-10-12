<?php

namespace App\Filament\Resources\CrmImportRuns\Pages;

use App\Filament\Resources\CrmImportRuns\CrmImportRunResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;

class ListCrmImportRuns extends ListRecords
{
    protected static string $resource = CrmImportRunResource::class;

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }
}
