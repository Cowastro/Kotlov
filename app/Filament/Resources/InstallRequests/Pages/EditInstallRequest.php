<?php

namespace App\Filament\Resources\InstallRequests\Pages;

use App\Filament\Resources\InstallRequests\InstallRequestResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditInstallRequest extends EditRecord
{
    protected static string $resource = InstallRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
