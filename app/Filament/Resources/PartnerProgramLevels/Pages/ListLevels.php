<?php

namespace App\Filament\Resources\PartnerProgramLevels\Pages;

use App\Filament\Resources\PartnerProgramLevels\PartnerProgramLevelResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions\CreateAction;

class ListLevels extends ListRecords
{
    protected static string $resource = PartnerProgramLevelResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
