<?php

namespace App\Filament\Resources\IntegrationSources\Pages;

use App\Filament\Resources\IntegrationSources\IntegrationSourceResource;
use Filament\Resources\Pages\CreateRecord;

class CreateIntegrationSource extends CreateRecord
{
    protected static string $resource = IntegrationSourceResource::class;

    protected function afterFill(): void
    {
        $supplierId = request()->integer('supplier_id');

        if ($supplierId > 0) {
            data_set($this->data, 'supplier_id', $supplierId);
        }
    }
}
