<?php

namespace App\Filament\Resources\CrmCounterparties\Pages;

use App\Filament\Resources\CrmCounterparties\CrmCounterpartyResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;

class ListCrmCounterparties extends ListRecords
{
    protected static string $resource = CrmCounterpartyResource::class;

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Создать контрагента')];
    }
}
