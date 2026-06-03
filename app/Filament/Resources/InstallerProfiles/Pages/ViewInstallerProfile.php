<?php

namespace App\Filament\Resources\InstallerProfiles\Pages;

use App\Filament\Resources\InstallerProfiles\InstallerProfileResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewInstallerProfile extends ViewRecord
{
    protected static string $resource = InstallerProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
