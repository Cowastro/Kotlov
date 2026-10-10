<?php

namespace App\Filament\Resources\IntegrationSources\Pages;

use App\Filament\Resources\IntegrationSources\IntegrationSourceResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Width;

class CreateIntegrationSource extends CreateRecord
{
    protected static string $resource = IntegrationSourceResource::class;

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    protected function afterFill(): void
    {
        $supplierId = request()->integer('supplier_id');

        if ($supplierId > 0) {
            data_set($this->data, 'supplier_id', $supplierId);
        }
    }
}
