<?php

namespace App\Filament\Resources\SupplierApplications\Pages;

use App\Filament\Resources\SupplierApplications\SupplierApplicationResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewSupplierApplication extends ViewRecord
{
    protected static string $resource = SupplierApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [EditAction::make()];
    }
}
