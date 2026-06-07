<?php

namespace App\Filament\Resources\ContactRequests\Pages;

use App\Filament\Resources\ContactRequests\ContactRequestResource;
use Filament\Resources\Pages\EditRecord;

class EditContactRequest extends EditRecord
{
    protected static string $resource = ContactRequestResource::class;
}
