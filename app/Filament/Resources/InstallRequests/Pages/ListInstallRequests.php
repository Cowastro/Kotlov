<?php

namespace App\Filament\Resources\InstallRequests\Pages;

use App\Filament\Resources\InstallRequests\InstallRequestResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListInstallRequests extends ListRecords
{
    protected static string $resource = InstallRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
