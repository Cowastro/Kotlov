<?php

namespace App\Filament\Resources\InstallerProfiles\Pages;

use App\Filament\Resources\InstallerProfiles\InstallerProfileResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditInstallerProfile extends EditRecord
{
    protected static string $resource = InstallerProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
