<?php

namespace App\Filament\Resources\CrmMailingLists\Pages;

use App\Filament\Resources\CrmMailingLists\CrmMailingListResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCrmMailingList extends CreateRecord
{
    protected static string $resource = CrmMailingListResource::class;

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
