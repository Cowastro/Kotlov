<?php

namespace App\Filament\Resources\B2bPriceLevels\Pages;

use App\Filament\Resources\B2bPriceLevels\B2bPriceLevelResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListB2bPriceLevels extends ListRecords
{
    protected static string $resource = B2bPriceLevelResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Добавить уровень')];
    }
}
