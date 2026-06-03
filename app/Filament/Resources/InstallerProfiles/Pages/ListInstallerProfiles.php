<?php

namespace App\Filament\Resources\InstallerProfiles\Pages;

use App\Filament\Resources\InstallerProfiles\InstallerProfileResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListInstallerProfiles extends ListRecords
{
    protected static string $resource = InstallerProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
