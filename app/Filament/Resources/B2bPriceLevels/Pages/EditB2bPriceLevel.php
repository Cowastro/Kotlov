<?php

namespace App\Filament\Resources\B2bPriceLevels\Pages;

use App\Filament\Resources\B2bPriceLevels\B2bPriceLevelResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditB2bPriceLevel extends EditRecord
{
    protected static string $resource = B2bPriceLevelResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
