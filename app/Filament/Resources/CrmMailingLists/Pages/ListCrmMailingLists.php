<?php

namespace App\Filament\Resources\CrmMailingLists\Pages;

use App\Filament\Resources\CrmMailingLists\CrmMailingListResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCrmMailingLists extends ListRecords
{
    protected static string $resource = CrmMailingListResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Новый список')];
    }
}
