<?php

namespace App\Filament\Resources\CrmContacts\Pages;

use App\Filament\Resources\CrmContacts\CrmContactResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCrmContact extends CreateRecord
{
    protected static string $resource = CrmContactResource::class;
}
