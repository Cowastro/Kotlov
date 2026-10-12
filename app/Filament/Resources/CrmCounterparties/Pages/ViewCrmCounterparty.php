<?php

namespace App\Filament\Resources\CrmCounterparties\Pages;

use App\Filament\Resources\CrmCounterparties\CrmCounterpartyResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewCrmCounterparty extends ViewRecord
{
    protected static string $resource = CrmCounterpartyResource::class;

    protected function getHeaderActions(): array
    {
        return [EditAction::make()];
    }
}
