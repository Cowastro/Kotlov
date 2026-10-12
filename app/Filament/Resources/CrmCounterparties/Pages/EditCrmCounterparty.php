<?php

namespace App\Filament\Resources\CrmCounterparties\Pages;

use App\Filament\Resources\CrmCounterparties\CrmCounterpartyResource;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditCrmCounterparty extends EditRecord
{
    protected static string $resource = CrmCounterpartyResource::class;

    protected function getHeaderActions(): array
    {
        return [ViewAction::make()];
    }
}
