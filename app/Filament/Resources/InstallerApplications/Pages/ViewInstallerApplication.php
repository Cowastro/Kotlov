<?php

namespace App\Filament\Resources\InstallerApplications\Pages;

use App\Filament\Resources\InstallerApplications\InstallerApplicationResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewInstallerApplication extends ViewRecord
{
    protected static string $resource = InstallerApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [EditAction::make()];
    }
}
