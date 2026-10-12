<?php

namespace App\Filament\Resources\CrmContacts\Pages;

use App\Filament\Resources\CrmContacts\CrmContactResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewCrmContact extends ViewRecord
{
    protected static string $resource = CrmContactResource::class;

    protected function getHeaderActions(): array
    {
        return [EditAction::make()];
    }
}
