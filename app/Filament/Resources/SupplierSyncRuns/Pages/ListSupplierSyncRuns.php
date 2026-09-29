<?php

namespace App\Filament\Resources\SupplierSyncRuns\Pages;

use App\Filament\Resources\SupplierSyncRuns\SupplierSyncRunResource;
use Filament\Resources\Pages\ListRecords;

class ListSupplierSyncRuns extends ListRecords
{
    protected static string $resource = SupplierSyncRunResource::class;

    public function getTitle(): string
    {
        return 'Журнал синхронизаций';
    }

    public function getSubheading(): ?string
    {
        return 'История запусков и изменений цен и остатков по поставщикам';
    }
}
