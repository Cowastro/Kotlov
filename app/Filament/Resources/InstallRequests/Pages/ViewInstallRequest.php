<?php

namespace App\Filament\Resources\InstallRequests\Pages;

use App\Filament\Resources\InstallRequests\InstallRequestResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewInstallRequest extends ViewRecord
{
    protected static string $resource = InstallRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
