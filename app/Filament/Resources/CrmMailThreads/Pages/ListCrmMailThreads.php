<?php

namespace App\Filament\Resources\CrmMailThreads\Pages;

use App\Filament\Resources\CrmMailThreads\CrmMailThreadResource;
use Filament\Resources\Pages\ListRecords;

class ListCrmMailThreads extends ListRecords
{
    protected static string $resource = CrmMailThreadResource::class;

    public function getSubheading(): ?string
    {
        return 'Каждое новое письмо становится контролируемым лидом и связывается с контактами CRM по email.';
    }
}
