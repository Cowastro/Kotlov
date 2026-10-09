<?php

namespace App\Filament\Supplier\Resources\SupplierSyncChanges\Pages;

use App\Filament\Supplier\Resources\SupplierSyncChanges\SupplierSyncChangeResource;
use Filament\Resources\Pages\ListRecords;

class ListSupplierSyncChanges extends ListRecords
{
    protected static string $resource = SupplierSyncChangeResource::class;

    public function getTitle(): string
    {
        return 'История изменений';
    }

    public function getSubheading(): ?string
    {
        return 'Что изменилось в ваших ценах, остатках и привязках после синхронизации. Данные других поставщиков скрыты.';
    }
}
