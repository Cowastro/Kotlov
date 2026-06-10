<?php

namespace App\Filament\Resources\SupplierSyncs\Pages;

use App\Filament\Resources\SupplierSyncs\SupplierSyncResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSupplierSync extends EditRecord
{
    protected static string $resource = SupplierSyncResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
